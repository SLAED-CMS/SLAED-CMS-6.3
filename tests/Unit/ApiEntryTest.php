<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The one entry of the API, the branch api/ of public/index.php over the light core, against the site and the panel beside it, over route_probe.php entry
final class ApiEntryTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once in its entry mode and memoize the run; no request may write a PHP or SQL error
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_api_entry';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' entry 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The requests wrote PHP or SQL errors');
            self::$probe = $data['runs']['entry'];
        }
        return self::$probe;
    }

    # A refusal of the API as the envelope of .rules/api.md: the status, the JSON type, no copy kept, no cookie, the code with its text and the meta of the request
    private function assertRefusal(array $one, int $code, string $name, string $text): void
    {
        $this->assertSame($code, $one['code'], 'The status of '.$name.' is wrong');
        $this->assertSame('application/json; charset=UTF-8', $one['type'], 'The API answered something else than JSON');
        $this->assertSame('no-store', $one['cache'], 'A refusal of the API may be kept');
        $this->assertFalse($one['cookie'], 'The API set a cookie');
        $this->assertFalse($one['session'], 'The API opened a session');
        $this->assertSame(['ok', 'error', 'meta'], array_keys($one['json']), 'The envelope of a refusal is not the one of the contract');
        $this->assertSame(['code' => $name, 'message' => $text, 'details' => []], $one['json']['error'], 'The error of the envelope is wrong');
        $this->assertFalse($one['json']['ok']);
        $this->assertSame(['id', 'ver', 'ms', 'now'], array_keys($one['json']['meta']), 'The meta of the envelope is not the one of the contract');
        $this->assertSame($one['rid'], $one['json']['meta']['id'], 'The request id of the header and of the meta differ');
        $this->assertSame('v1', $one['json']['meta']['ver']);
        $this->assertIsInt($one['json']['meta']['ms']);
        $this->assertEqualsWithDelta(time() * 1000, $one['json']['meta']['now'], 600000, 'The server time of the meta is not in unix milliseconds');
    }

    # system/ping answers the envelope of a success with no data, and a valid request id comes back as it was sent while an invalid one is replaced
    #[Test]
    public function aPathUnderApiAnswersTheEnvelope(): void
    {
        $run = $this->getRun();
        foreach (['ping', 'badid'] as $name) {
            $this->assertSame(200, $run[$name]['code'], 'system/ping did not answer');
            $this->assertSame('application/json; charset=UTF-8', $run[$name]['type']);
            $this->assertFalse($run[$name]['cookie'], 'The API set a cookie');
            $this->assertFalse($run[$name]['session'], 'The API opened a session');
            $this->assertStringContainsString('"ok":true,"data":{},"meta":', $run[$name]['body'], 'The ping is not the empty success of the envelope');
        }
        $this->assertSame('probe-request-01', $run['ping']['rid'], 'A valid request id was not echoed');
        $this->assertSame('probe-request-01', $run['ping']['json']['meta']['id'], 'The request id of the header and of the meta differ');
        $this->assertMatchesRegularExpression('#^[0-9a-f]{16}$#D', $run['badid']['rid'], 'An invalid request id was not replaced by a generated one');
    }

    # The light core: config, database, security, logger, cache, mail and the router load, and no theme, no visual class and no layer of the user or the panel
    #[Test]
    public function theLightCoreLoadsNoThemeAndNoTemplate(): void
    {
        $files = $this->getRun()['ping']['files'];
        foreach (['public/index.php', 'core/system.php', 'core/security.php', 'core/classes/api/router.php', 'core/classes/pdo.php', 'core/classes/logger.php',
            'core/classes/cache.php', 'core/classes/mail.php', 'core/helpers.php'] as $file) {
            $this->assertContains($file, $files, 'The light core did not load '.$file);
        }
        $this->assertSame([], array_values(array_filter($files, fn(string $v): bool => str_starts_with($v, 'public/templates/'))), 'The API loaded a file of a theme');
        foreach (['template', 'parser', 'captcha', 'oauth', 'field', 'point', 'rating', 'comment', 'quick', 'privat', 'editor'] as $name) {
            $this->assertNotContains('core/classes/'.$name.'.php', $files, 'The API loaded the visual class '.$name);
        }
        $this->assertNotContains('core/user.php', $files, 'The API loaded the layer of the user');
        $this->assertNotContains('core/admin.php', $files, 'The API loaded the layer of the panel');
    }

    # A path too deep for the cut of the site, the parameter of the error page, a POST of a form and a HEAD all stay in the API and get its JSON, never a page
    #[Test]
    public function everyRequestUnderApiStaysInTheApi(): void
    {
        $run = $this->getRun();
        foreach (['deep', 'query'] as $name) $this->assertRefusal($run[$name], 404, 'no_route', 'Not found');
        $this->assertRefusal($run['post'], 405, 'no_method', 'Method not allowed');
        $this->assertSame(200, $run['head']['code']);
        $this->assertSame('application/json; charset=UTF-8', $run['head']['type']);
        $this->assertSame('', $run['head']['body'], 'A HEAD of the API carried a body');
    }

    # The request scan of the site never sees the API: a dot segment and SQL words in its input write no hack report and ban nobody, ApiInput is its filter
    #[Test]
    public function theRequestScanNeverBansACaller(): void
    {
        $this->assertSame(['ip' => true, 'hack' => false, 'site' => 200], $this->getRun()['kept'], 'The input of the API was judged by the scan of the site');
    }

    # A banned address gets a bare 403 and a database that refuses the connection a bare 500, both in JSON with no word of the reason, whatever the error display says
    #[Test]
    public function aBanAndALostDatabaseAnswerInJson(): void
    {
        $run = $this->getRun();
        $this->assertRefusal($run['ban'], 403, 'no_scope', 'Forbidden');
        $this->assertRefusal($run['lost'], 500, 'internal', 'Internal error');
        foreach (['SQLSTATE', 'probe-wrong-pass', 'Access denied', 'pdo.php'] as $word) {
            $this->assertStringNotContainsString($word, $run['lost']['body'], 'The reply of a lost database named its reason');
        }
        $this->assertSame(200, $run['back'], 'The API did not come back with the database');
    }

    # The site and the panel answer their pages as before, and a path that only begins like api, or api without its slash, is a page of the site that is not found
    #[Test]
    public function theSiteAndThePanelAnswerAsBefore(): void
    {
        $run = $this->getRun();
        foreach (['site' => 200, 'start' => 200, 'admin' => 200, 'bare' => 404, 'beside' => 404] as $name => $code) {
            $this->assertSame($code, $run[$name]['code'], 'The status of '.$name.' changed');
            $this->assertStringStartsWith('text/html', $run[$name]['type'], $name.' is no page any more');
        }
        $this->assertTrue($run['site']['cookie'], 'The first page of a visitor set no cookie, so the cookie check of the API proves nothing');
    }
}
