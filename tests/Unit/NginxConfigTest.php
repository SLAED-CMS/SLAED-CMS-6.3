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

    # The block puts the root on public/, sends every upload address and every missing path to the front controller, and runs PHP by its script name
    #[Test]
    public function theServerBlockCarriesTheRootAndTheFrontController(): void
    {
        $text = $this->getConfig();
        $this->assertMatchesRegularExpression('#^\s*root\s+\S+/public;#m', $text, 'The document root is not the folder public/');
        $upload = '#location\s+\^~\s+/uploads/\s*\{\s*rewrite\s+\^\s+/index\.php\s+last;\s*\}#';
        $this->assertMatchesRegularExpression($upload, $text, 'An upload address does not reach the light path');
        $this->assertLessThan(strpos($text, 'location ~* ^/templates/'), strpos($text, 'location ^~ /uploads/'), 'A regex location precedes the upload prefix');
        $this->assertMatchesRegularExpression('#try_files\s+\$uri\s+\$uri/\s+/index\.php\?\$args;#', $text, 'A missing path does not reach the front controller');
        $this->assertStringContainsString('fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;', $text);
        $this->assertStringContainsString('fastcgi_intercept_errors off;', $text, 'nginx would replace the error page of the site with its own');
        $this->assertStringNotContainsString('.htaccess', preg_replace('/^\s*#.*$/m', '', $text), 'A rule of the block names a guard file, which no folder carries');
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
