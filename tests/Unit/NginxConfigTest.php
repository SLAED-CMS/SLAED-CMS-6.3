<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# nginx.conf.example says for nginx what public/.htaccess says for Apache, file by file, so the shipped server block cannot drift into a false protection
final class NginxConfigTest extends TestCase
{
    # The text of the shipped server block
    private function getConfig(): string
    {
        return (string)file_get_contents(BASE_DIR.'/nginx.conf.example');
    }

    # The refusing patterns of public/.htaccess: a rule answering [F] that no RewriteCond narrows, which is what refuses a path whatever the query says
    private function getApacheRefusals(): array
    {
        $out = [];
        $prev = '';
        foreach (file(PUBLIC_DIR.'/.htaccess', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if (preg_match('#^RewriteRule\s+(\S+)\s+-\s+\[F#', $line, $hit) && !str_starts_with($prev, 'RewriteCond')) $out[] = $hit[1];
            if ($line !== '' && !str_starts_with($line, '#')) $prev = $line;
        }
        return $out;
    }

    # The refusing locations of the server block: a regex location that answers 403, 404 or deny all
    private function getNginxRefusals(): array
    {
        preg_match_all('#location\s+~\*?\s+(\S+)\s*\{\s*(?:return\s+40[34]|deny\s+all)\s*;#', $this->getConfig(), $hits);
        return $hits[1];
    }

    # Every file of the document root is refused by both files or by neither; the walk covers the themes, the plugins and the stubs as they are shipped
    #[Test]
    public function theServerBlockRefusesWhatTheHtaccessRefuses(): void
    {
        $apache = $this->getApacheRefusals();
        $nginx = $this->getNginxRefusals();
        $this->assertNotEmpty($apache, 'public/.htaccess refuses nothing, so there is nothing to compare');
        $this->assertNotEmpty($nginx, 'nginx.conf.example refuses nothing');
        $base = str_replace('\\', '/', PUBLIC_DIR).'/';
        $diff = [];
        $hits = 0;
        foreach (getTreeFiles(PUBLIC_DIR) as $file) {
            if (!$file->isFile()) continue;
            $rel = substr(str_replace('\\', '/', $file->getPathname()), strlen($base));
            $one = (bool)array_filter($apache, static fn(string $v): bool => preg_match('#'.str_replace('#', '\#', $v).'#i', $rel) === 1);
            $two = (bool)array_filter($nginx, static fn(string $v): bool => preg_match('#'.str_replace('#', '\#', $v).'#i', '/'.$rel) === 1);
            if ($one) $hits++;
            if ($one !== $two) $diff[] = $rel.($one ? ' is refused by public/.htaccess only' : ' is refused by nginx.conf.example only');
        }
        $this->assertGreaterThan(10, $hits, 'The walk found almost no refused file, so the comparison proves nothing');
        $this->assertSame([], array_slice($diff, 0, 20), 'The two server configurations refuse different files of public/');
    }

    # The block puts the root on public/, sends every upload and every missing path to the front controller, a path after a script to that script, and runs PHP by its name
    #[Test]
    public function theServerBlockCarriesTheRootAndTheFrontController(): void
    {
        $text = $this->getConfig();
        $this->assertMatchesRegularExpression('#^\s*root\s+\S+/public;#m', $text, 'The document root is not the folder public/');
        $upload = '#location\s+\^~\s+/uploads/\s*\{\s*rewrite\s+\^\s+/index\.php\s+last;\s*\}#';
        $this->assertMatchesRegularExpression($upload, $text, 'An upload address does not reach the light path');
        $this->assertLessThan(strpos($text, 'location ~* ^/templates/'), strpos($text, 'location ^~ /uploads/'), 'A regex location precedes the upload prefix');
        $this->assertMatchesRegularExpression('#try_files\s+\$uri\s+\$uri/\s+/index\.php\?\$args;#', $text, 'A missing path does not reach the front controller');
        $tail = 'location ~ ^(.+?\.php)/ {';
        $this->assertMatchesRegularExpression('#location\s+~\s+\^\(\.\+\?\\\\\.php\)/\s*\{\s*rewrite\s+\^\(\.\+\?\\\\\.php\)/\s+\$1\s+last;\s*\}#', $text,
            'A path after a script does not reach that script, so its 301 is lost in the 404 of nginx');
        $this->assertLessThan(strpos($text, 'location ~ \.php$'), strpos($text, $tail), 'The PHP location takes a path after a script first');
        $this->assertStringContainsString('fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;', $text);
        $this->assertStringContainsString('fastcgi_intercept_errors off;', $text, 'nginx would replace the error page of the site with its own');
        $this->assertStringNotContainsString('.htaccess', preg_replace('/^\s*#.*$/m', '', $text), 'A rule of the block names a guard file, which no folder carries');
    }

    # The lifetime the table of the asset cache plan gives a static file by its kind, or null where no static rule may answer
    private function getPlannedCache(string $rel, bool $ver): ?string
    {
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if (in_array($ext, ['css', 'js', 'mjs'], true)) return $ver ? 'public, max-age=31536000, immutable' : 'public, max-age=604800';
        $week = ['woff2', 'woff', 'ttf', 'otf', 'eot', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'mp3', 'ogg', 'wav'];
        return in_array($ext, $week, true) ? 'public, max-age=604800' : null;
    }

    # The Cache-Control public/.htaccess sets on a file of the document root: the last FilesMatch of mod_headers that matches wins
    private function getApacheCache(string $rel, string $query): ?string
    {
        $text = (string)file_get_contents(PUBLIC_DIR.'/.htaccess');
        if (!preg_match('#<IfModule mod_headers\.c>(.*?)\n</IfModule>#s', $text, $hit)) return null;
        preg_match_all('#<FilesMatch "([^"]+)">(.*?)</FilesMatch>#s', $hit[1], $rows, PREG_SET_ORDER);
        $out = null;
        foreach ($rows as $row) {
            if (preg_match('#'.$row[1].'#', basename($rel)) !== 1) continue;
            $cond = '#<If "%\{QUERY_STRING\} =~ /(.+?)/">\s*Header set Cache-Control "([^"]+)"\s*</If>\s*<Else>\s*Header set Cache-Control "([^"]+)"\s*</Else>#';
            if (preg_match($cond, $row[2], $part)) $out = preg_match('#'.$part[1].'#', $query) === 1 ? $part[2] : $part[3];
            elseif (preg_match('#Header set Cache-Control "([^"]+)"#', $row[2], $part)) $out = $part[1];
        }
        return $out;
    }

    # The Cache-Control nginx.conf.example sends for a path: a ^~ prefix first, then the first regex location, the server variable resolved by $arg_v
    private function getNginxCache(string $path, string $query): ?string
    {
        $text = $this->getConfig();
        preg_match_all('#location\s+(\^~|~\*?)\s+(\S+)\s*\{([^{}]*)\}#', $text, $rows, PREG_SET_ORDER);
        $pick = null;
        foreach ($rows as $row) if ($row[1] === '^~' && str_starts_with($path, $row[2])) $pick ??= $row;
        foreach ($rows as $row) if ($row[1] !== '^~' && preg_match('#'.$row[2].'#'.($row[1] === '~*' ? 'i' : ''), $path) === 1) $pick ??= $row;
        if (!$pick || !preg_match('#add_header\s+Cache-Control\s+("([^"]+)"|\$(\w+));#', $pick[3], $hit)) return null;
        if (($hit[3] ?? '') === '') return $hit[2];
        $vars = '#set\s+\$'.$hit[3].'\s+"([^"]+)";\s*if\s+\(\$arg_v\)\s*\{\s*set\s+\$'.$hit[3].'\s+"([^"]+)";\s*\}#';
        if (!preg_match($vars, $text, $set)) return null;
        parse_str($query, $args);
        return in_array((string)($args['v'] ?? ''), ['', '0'], true) ? $set[1] : $set[2];
    }

    # Every file of the document root gets from both servers the lifetime of its kind, with and without a version; a page and an upload get none
    #[Test]
    public function bothServersGiveEveryStaticKindItsLifetime(): void
    {
        $base = str_replace('\\', '/', PUBLIC_DIR).'/';
        $diff = [];
        $kinds = [];
        foreach (getTreeFiles(PUBLIC_DIR) as $file) {
            if (!$file->isFile()) continue;
            $rel = substr(str_replace('\\', '/', $file->getPathname()), strlen($base));
            foreach (['' => false, 'v=3fa2c19d0b' => true] as $query => $ver) {
                $want = $this->getPlannedCache($rel, $ver);
                $one = $this->getApacheCache($rel, $query);
                $two = $this->getNginxCache('/'.$rel, $query);
                if ($want !== null) $kinds[pathinfo($rel, PATHINFO_EXTENSION).($ver ? '?v' : '')] = true;
                if ($one !== $want) $diff[] = $rel.'?'.$query.': public/.htaccess sends '.var_export($one, true);
                if ($two !== $want) $diff[] = $rel.'?'.$query.': nginx.conf.example sends '.var_export($two, true);
            }
        }
        foreach (['css', 'css?v', 'js', 'js?v', 'svg', 'png', 'webp', 'woff2', 'mp3', 'ico'] as $one) $this->assertArrayHasKey($one, $kinds, 'The walk met no file of kind '.$one);
        $this->assertSame([], array_slice($diff, 0, 20), 'A static file of public/ is kept for another lifetime than the asset cache plan names');
        $this->assertSame([$this->getPlannedCache('a.png', false)], array_unique([$this->getApacheCache('img/Logo.PNG', ''), $this->getNginxCache('/img/Logo.PNG', '')]),
            'A file name in capitals gets another lifetime than its kind');
        $this->assertNull($this->getNginxCache('/uploads/node/news/a.png', ''), 'An upload must keep the header of the light path');
        $this->assertNull($this->getNginxCache('/index.php', 'v=1'), 'A page must keep the no-store of PHP');
    }

    # nginx compresses the types public/.htaccess deflates, text/html being always compressed by gzip
    #[Test]
    public function theServerBlockCompressesWhatTheHtaccessDeflates(): void
    {
        preg_match_all('#^AddOutputFilterByType DEFLATE (.+)$#m', (string)file_get_contents(PUBLIC_DIR.'/.htaccess'), $rows);
        $apache = array_diff(preg_split('#\s+#', implode(' ', $rows[1])) ?: [], ['text/html']);
        $this->assertMatchesRegularExpression('#^\s*gzip\s+on;#m', $this->getConfig(), 'nginx.conf.example compresses nothing');
        $this->assertSame(1, preg_match('#gzip_types\s+([^;]+);#', $this->getConfig(), $hit), 'nginx.conf.example names no gzip type');
        $nginx = preg_split('#\s+#', trim($hit[1])) ?: [];
        sort($apache);
        sort($nginx);
        $this->assertNotEmpty($apache);
        $this->assertSame(array_values($apache), $nginx, 'The two servers compress different types');
    }

    # Where an nginx binary is at hand, in SLAED_NGINX or on the PATH, the block passes nginx -t inside a minimal http context
    #[Test]
    public function theServerBlockPassesTheSyntaxCheck(): void
    {
        $bin = (string)getenv('SLAED_NGINX');
        $win = PHP_OS_FAMILY === 'Windows';
        if ($bin === '') $bin = trim((string)shell_exec(($win ? 'where nginx' : 'command -v nginx').' 2>'.($win ? 'NUL' : '/dev/null')));
        $bin = strtok($bin, "\r\n") ?: '';
        if ($bin === '' || !is_file($bin)) $this->markTestSkipped('No nginx binary: set SLAED_NGINX to run the syntax check');
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_nginx_check';
        foreach (['', '/logs', '/temp'] as $one) if (!is_dir($work.$one)) mkdir($work.$one, 0777, true);
        file_put_contents($work.'/fastcgi_params', '');
        $block = str_replace('include fastcgi_params;', 'include "'.$work.'/fastcgi_params";', $this->getConfig());
        if ($win) $block = str_replace('unix:/run/php/php8.4-fpm.sock', '127.0.0.1:9000', $block);
        file_put_contents($work.'/test.conf', "events {}\nhttp {\n".$block."\n}\n");
        $out = (string)shell_exec(escapeshellarg($bin).' -t -p '.escapeshellarg($work.'/').' -c '.escapeshellarg($work.'/test.conf').' 2>&1');
        $this->assertStringContainsString('test is successful', $out, $out);
    }
}
