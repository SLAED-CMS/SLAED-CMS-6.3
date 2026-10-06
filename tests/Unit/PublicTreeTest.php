<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The project out of web reach: a server on public/ reaches nothing else, an upload leaves through the light path of the real entry, two .htaccess files hold both modes
final class PublicTreeTest extends TestCase
{
    private static $proc = null;
    private static int $port = 0;
    private static string $work = '';
    private static string $png = '';

    # Start the built-in server on public/ over a scratch upload root with a public and two private folders, a picture with its thumb, an SVG and a file outside the root
    public static function setUpBeforeClass(): void
    {
        self::$work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_public_tree';
        self::deleteTree(self::$work);
        self::$png = (string)base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        foreach (['uploads/presentation/thumb', 'uploads/all', 'uploads/node/news', 'uploads/media'] as $dir) mkdir(self::$work.'/'.$dir, 0777, true);
        foreach (['presentation/shot.png', 'presentation/thumb/shot.png', 'node/news/shot.png', 'media/shot.png'] as $one) {
            file_put_contents(self::$work.'/uploads/'.$one, self::$png);
        }
        file_put_contents(self::$work.'/uploads/all/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        file_put_contents(self::$work.'/uploads/presentation/.hidden', 'hidden');
        file_put_contents(self::$work.'/secret.txt', 'secret');
        $sock = stream_socket_server('tcp://127.0.0.1:0');
        self::$port = (int)substr(strrchr((string)stream_socket_get_name($sock, false), ':'), 1);
        fclose($sock);
        $root = dirname(__DIR__, 2);
        $env = getenv() + ['SLAED_WEB_ROOT' => self::$work];
        $cmd = [PHP_BINARY, '-S', '127.0.0.1:'.self::$port, '-t', $root.'/public', $root.'/tests/Support/light_web.php'];
        self::$proc = proc_open($cmd, [1 => ['file', self::$work.'/server.log', 'a'], 2 => ['file', self::$work.'/server.log', 'a']], $pipes, self::$work, $env);
        set_error_handler(static fn(): bool => true);
        for ($i = 0; $i < 50; $i++) {
            $test = stream_socket_client('tcp://127.0.0.1:'.self::$port, $no, $err, 1);
            if ($test) break;
            usleep(100000);
        }
        restore_error_handler();
        if (!$test) self::fail('The built-in server did not start');
        fclose($test);
    }

    # Stop the server and remove the scratch root
    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$proc)) {
            proc_terminate(self::$proc);
            proc_close(self::$proc);
        }
        self::deleteTree(self::$work);
    }

    # Remove one directory tree
    private static function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $one) {
            if ($one === '.' || $one === '..') continue;
            is_dir($dir.'/'.$one) ? self::deleteTree($dir.'/'.$one) : unlink($dir.'/'.$one);
        }
        rmdir($dir);
    }

    # Send one raw HTTP/1.0 request with the path exactly as given and answer the status, the headers by lowercase name and the body
    private function getReply(string $path, array $head = []): array
    {
        if (is_file(self::$work.'/files.json')) unlink(self::$work.'/files.json');
        $sock = stream_socket_client('tcp://127.0.0.1:'.self::$port, $no, $err, 5);
        $this->assertNotFalse($sock, 'The server refused the connection: '.$err);
        $lines = 'GET '.$path." HTTP/1.0\r\nHost: 127.0.0.1\r\n";
        foreach ($head as $name => $val) $lines .= $name.': '.$val."\r\n";
        fwrite($sock, $lines."\r\n");
        $raw = '';
        while (!feof($sock)) $raw .= (string)fread($sock, 65536);
        fclose($sock);
        [$top, $body] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
        $rows = explode("\r\n", $top);
        $status = (int)(explode(' ', (string)array_shift($rows))[1] ?? 0);
        $heads = [];
        foreach ($rows as $row) {
            [$name, $val] = array_pad(explode(':', $row, 2), 2, '');
            $heads[strtolower(trim($name))] = trim($val);
        }
        usleep(20000);
        $files = is_file(self::$work.'/files.json') ? (array)json_decode((string)file_get_contents(self::$work.'/files.json'), true) : [];
        return ['status' => $status, 'head' => $heads, 'body' => $body, 'files' => $files];
    }

    # A file of a public folder and its thumb copy leave through the light path with the type, nosniff, the sandbox and a day of public cache, and the core is never loaded
    #[Test]
    public function aPublicUploadIsServedWithoutTheCore(): void
    {
        foreach (['/uploads/presentation/shot.png', '/uploads/presentation/thumb/shot.png'] as $path) {
            $one = $this->getReply($path);
            $this->assertSame(200, $one['status'], $path.' was not served');
            $this->assertSame(self::$png, $one['body'], $path.' was not served whole');
            $this->assertSame('image/png', $one['head']['content-type'] ?? null);
            $this->assertSame('nosniff', $one['head']['x-content-type-options'] ?? null);
            $this->assertSame('sandbox', $one['head']['content-security-policy'] ?? null);
            $this->assertSame('public, max-age=86400', $one['head']['cache-control'] ?? null);
            $this->assertNotEmpty($one['head']['etag'] ?? '', 'A public upload carries no entity tag');
            $this->assertNotEmpty($one['head']['last-modified'] ?? '', 'A public upload carries no date');
            $this->assertStringStartsWith('inline;', $one['head']['content-disposition'] ?? '', 'A picture is not shown inline');
            $names = array_map(static fn(string $v): string => basename(dirname($v)).'/'.basename($v), $one['files']);
            $this->assertContains('core/stream.php', $names, 'The light path did not answer the request');
            $this->assertNotContains('core/system.php', $names, 'The light path booted the core');
        }
        $etag = (string)($this->getReply('/uploads/presentation/shot.png')['head']['etag'] ?? '');
        $again = $this->getReply('/uploads/presentation/shot.png', ['If-None-Match' => $etag]);
        $this->assertSame(304, $again['status'], 'The entity tag of a public upload is not answered with 304');
        $this->assertSame('', $again['body']);
    }

    # An SVG is a script carrier, so it never goes out under its own type: it is saved as an opaque attachment and still carries the sandbox
    #[Test]
    public function anSvgGoesOutAsAnAttachment(): void
    {
        $one = $this->getReply('/uploads/all/logo.svg');
        $this->assertSame(200, $one['status']);
        $this->assertSame('application/octet-stream', $one['head']['content-type'] ?? null);
        $this->assertStringStartsWith('attachment;', $one['head']['content-disposition'] ?? '');
        $this->assertSame('sandbox', $one['head']['content-security-policy'] ?? null);
    }

    # A private folder, a folder off the public list, a traversal in plain or escaped form, a dot name and a missing name all answer 410 without a byte of the file
    #[Test]
    public function theLightPathRefusesAnythingButAPublicFile(): void
    {
        $list = ['/uploads/node/news/shot.png', '/uploads/media/shot.png', '/uploads/presentation/../../secret.txt', '/uploads/presentation/%2e%2e/%2e%2e/secret.txt',
            '/uploads/presentation/..%2f..%2fsecret.txt', '/uploads/presentation/.hidden', '/uploads/presentation/none.png', '/uploads/presentation', '/uploads/'];
        foreach ($list as $path) {
            $one = $this->getReply($path);
            $this->assertSame(410, $one['status'], $path.' was not refused as gone');
            $this->assertStringNotContainsString('secret', $one['body'], $path.' leaked a file outside the upload root');
            $this->assertStringNotContainsString("\x89PNG", $one['body'], $path.' leaked a picture of a closed folder');
        }
    }

    # With the root on public/ no file of the project outside it exists for the web, while an asset of a theme is served
    #[Test]
    public function aServerOnPublicReachesNothingOfTheProject(): void
    {
        $list = ['/config/db.php', '/config/global.php', '/storage/geoip/country.mmdb', '/admin/index.php', '/admin/info/security/ru.md', '/core/system.php'];
        foreach ($list as $path) {
            $this->assertSame(404, $this->getReply($path)['status'], $path.' is reachable over HTTP');
        }
        $this->assertSame(200, $this->getReply('/templates/lite/assets/css/base.css')['status'], 'An asset of the theme is not served from public/');
        $this->assertSame(200, $this->getReply('/plugins/system/slaed.js')['status'], 'A script of the plugins is not served from public/');
    }

    # The project-level .htaccess sends every request into public/, and where mod_rewrite is missing it refuses everything through mod_authz_core, outside the rewrite block
    #[Test]
    public function theProjectRewriteSendsEverythingIntoPublic(): void
    {
        $text = (string)file_get_contents(BASE_DIR.'/.htaccess');
        $this->assertMatchesRegularExpression('#<IfModule mod_rewrite\.c>\s*RewriteEngine On\s*RewriteRule \^\(\.\*\)\$ public/\$1 \[L\]\s*</IfModule>#', $text);
        $this->assertMatchesRegularExpression('#<IfModule !mod_rewrite\.c>\s*<IfModule mod_authz_core\.c>\s*Require all denied\s*</IfModule>\s*</IfModule>#', $text);
        $this->assertSame(1, substr_count($text, 'RewriteRule'), 'The project rewrite does more than send every request into public/');
    }

    # The .htaccess of the document root keeps its rules valid in both modes and refuses the PHP and the markup of the themes
    #[Test]
    public function thePublicRewriteRefusesTheThemeSources(): void
    {
        $text = (string)file_get_contents(PUBLIC_DIR.'/.htaccess');
        $rules = (string)preg_replace('/^#.*$/m', '', $text);
        $this->assertStringNotContainsString('RewriteBase', $rules, 'A rewrite base pins the rules to one of the two modes');
        $this->assertStringContainsString('RewriteRule ^templates/.*\.php$ - [F,L,NC]', $text);
        $this->assertStringContainsString('RewriteRule ^templates/[^/]+/.*\.html$ - [F,L,NC]', $text);
        $this->assertStringContainsString('RewriteRule ^.*$ index.php [QSA,L]', $text, 'A missing file of the document root, an upload included, misses the front controller');
        $this->assertStringNotContainsString('uploads', $rules, 'A rule names an upload folder, which no longer lies in the document root');
    }

    # Only a path of a public folder has an address; a private folder, a dot segment and the upload root itself have none
    #[Test]
    public function onlyAPublicFolderHasAnAddress(): void
    {
        foreach (getUploadPublic() as $dir) $this->assertSame('uploads/'.$dir.'/a.png', getUploadUrl($dir.'/a.png'), $dir.' has no address');
        foreach (['node/news/a.png', 'forum/a.png', 'media/a.png', 'a.png', '', '../config/db.php'] as $path) $this->assertSame('', getUploadUrl($path), $path.' has an address');
        $this->assertSame('uploads/all/thumb/a.png', getUploadUrl('/all\\thumb/a.png'), 'A path is not normalized before the list decides');
    }

    # Exactly one .htaccess lies outside public/, the one of the project, and no guard page at all: a folder outside the document root needs no guard
    #[Test]
    public function noGuardLiesOutsideTheDocumentRoot(): void
    {
        $list = explode("\0", (string)shell_exec('git -C '.escapeshellarg(BASE_DIR).' ls-files -co --exclude-standard -z'));
        $left = [];
        foreach ($list as $one) {
            if ($one === '' || str_starts_with($one, 'public/') || $one === '.htaccess') continue;
            if (in_array(basename($one), ['.htaccess', 'index.html'], true)) $left[] = $one;
        }
        $this->assertSame([], $left, 'A guard file lies outside the document root');
        foreach (['index.php', 'admin.php', 'setup.php', 'update.php'] as $one) $this->assertFileExists(PUBLIC_DIR.'/'.$one, 'The entry '.$one.' is missing');
        $this->assertDirectoryDoesNotExist(PUBLIC_DIR.'/uploads', 'An upload folder lies in the document root');
    }
}
