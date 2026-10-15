<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The core of the connector over route_probe.php router and its three modules written for the run, which no line of the core names; input and buckets also in-process
final class ApiRouterTest extends TestCase
{
    private static array $probe = [];

    public static function setUpBeforeClass(): void
    {
        require_once BASE_DIR.'/core/classes/cache.php';
        require_once BASE_DIR.'/core/classes/api/router.php';
    }

    # Run the probe once in its router mode and memoize the run; the run removes its modules and the PHP errors it provoked itself, so none may be left
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_api_router';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' router 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The requests wrote PHP or SQL errors');
            foreach (['apiprobe', 'apibroken', 'apiidle'] as $name) $this->assertDirectoryDoesNotExist(BASE_DIR.'/modules/'.$name, 'The probe left its module '.$name);
            self::$probe = $data['runs']['router'];
        }
        return self::$probe;
    }

    # A version, a service and an action that are declared reach the handler; any other path, an inactive module and a version the action lacks are 404, a broken declaration 500
    #[Test]
    public function aPathReachesOnlyADeclaredAction(): void
    {
        $run = $this->getRun();
        foreach (['action', 'service', 'upper', 'slash', 'bare', 'version', 'twice', 'idle'] as $name) {
            $this->assertSame([404, 'no_route', []], $run['route'][$name], 'The path '.$name.' found an action');
        }
        $this->assertSame([500, 'internal', []], $run['route']['broken'], 'A key action without scope was served');
        $this->assertTrue($run['log']['broken'], 'The refused declaration was not logged');
        $this->assertSame(200, $run['twice']['code'], 'An action of v2 did not answer under v2');
        $this->assertSame('v2', $run['twice']['json']['meta']['ver'], 'The meta does not name the version of the path');
        $this->assertSame(405, $run['method']['code'], 'A GET of a POST action was not refused');
        $this->assertSame('no_method', $run['method']['json']['error']['code']);
        $this->assertSame('POST, OPTIONS', $run['method']['head']['allow'], 'The 405 does not name the method of the action');
        $this->assertSame(200, $run['head']['code']);
        $this->assertSame('', $run['head']['body'], 'A HEAD carried a body');
    }

    # The success is ok, data and meta; an empty result is an object; a thrown ApiError keeps its code and details; a crash is a bare 500 whose reason lands in the log only
    #[Test]
    public function theReplyIsTheEnvelope(): void
    {
        $run = $this->getRun();
        $this->assertStringContainsString('{"ok":true,"data":{},"meta":{"id":"', $run['ping']['body'], 'An empty result is no JSON object');
        $this->assertSame(['ok', 'data', 'meta'], array_keys($run['echo']['json']));
        $this->assertSame(['id', 'ver', 'ms', 'now'], array_keys($run['echo']['json']['meta']));
        $this->assertSame($run['echo']['head']['x-request-id'], $run['echo']['json']['meta']['id']);
        $this->assertSame('no-store', $run['echo']['head']['cache-control'], 'An action without cache may be kept');
        $this->assertSame('Origin', $run['echo']['head']['vary']);
        $this->assertSame(409, $run['fail']['code']);
        $this->assertSame(['code' => 'conflict', 'message' => 'Conflict', 'details' => ['why' => 'probe']], $run['fail']['json']['error']);
        $this->assertSame(500, $run['crash']['code']);
        $this->assertSame(['code' => 'internal', 'message' => 'Internal error', 'details' => []], $run['crash']['json']['error']);
        $this->assertStringNotContainsString('probe secret detail', $run['crash']['body'], 'The reply named the reason of the crash');
        $this->assertSame('probe-crash-0001', $run['crash']['json']['meta']['id']);
        $this->assertTrue($run['log']['crash'], 'The crash was not logged with its request id and reason');
    }

    # Every declared param arrives by its type with its default, an undeclared one is dropped, and each refusal is the 422 that names the param
    #[Test]
    public function theInputIsTyped(): void
    {
        $run = $this->getRun();
        $this->assertSame(['v' => '8.0.1', 'kind' => 'full', 'days' => 7, 'note' => ''], $run['echo']['json']['data'], 'The defaults or the drop of extra failed');
        $this->assertSame(['v' => '8.0.1', 'kind' => 'delta', 'days' => 90, 'note' => 'hi'], $run['days']['json']['data']);
        $want = ['dots' => 'v', 'beta' => 'v', 'kind' => 'kind', 'miss' => 'kind', 'max' => 'days', 'list' => 'v', 'ctrl' => 'note', 'long' => 'note'];
        foreach ($want as $name => $param) $this->assertSame([422, 'invalid', ['param' => $param]], $run['bad'][$name], 'The input '.$name.' was not refused');
    }

    # Route, maintenance, CORS, flood, auth, size and input refuse in this order: each case breaks two guards at once and gets the refusal of the earlier one
    #[Test]
    public function theGuardsKeepTheirOrder(): void
    {
        $run = $this->getRun();
        $this->assertSame(404, $run['cors']['prenone']['code'], 'A preflight of no route passed the route');
        $this->assertSame(204, $run['flood']['pre'], 'A preflight met the flood before CORS');
        $this->assertSame([503, 'disabled', []], $run['flood']['off'], 'A switched-off action met the flood before maintenance');
        $this->assertSame([404, 'no_route', []], $run['flood']['action'], 'No route met the flood');
        $this->assertSame([429, 'too_many', []], $run['flood']['save'], 'A key action met auth before the flood');
        $this->assertSame([401, 'no_auth', []], $run['order']['save'], 'A key action without clients was not 401');
        $this->assertSame([401, 'no_auth', []], $run['order']['hook'], 'A secret action without clients was not 401');
        $this->assertSame([401, 'no_auth', []], $run['order']['big'], 'An oversized body met the size before auth');
        $this->assertSame([413, 'too_large', []], $run['order']['size'], 'An oversized body with a bad param met the input before the size');
    }

    # A foreign origin is 403 and a listed one echoed on every reply, a 404 included; the own origin needs no echo; a preflight is 204 with the methods, without auth
    #[Test]
    public function corsAnswersByTheGlobalList(): void
    {
        $cors = $this->getRun()['cors'];
        $this->assertSame(204, $cors['pre']['code']);
        $this->assertSame('', $cors['pre']['body']);
        $this->assertArrayNotHasKey('content-type', $cors['pre']['head'], 'The 204 named a type for no body');
        $this->assertSame(['https://partner.example', 'POST', 'POST, OPTIONS'], [$cors['pre']['head']['access-control-allow-origin'] ?? '',
            $cors['pre']['head']['access-control-allow-methods'] ?? '', $cors['pre']['head']['allow'] ?? '']);
        $this->assertSame([403, 'Origin'], [$cors['foreign']['code'], $cors['foreign']['head']['vary'] ?? ''], 'A foreign origin was served');
        $this->assertArrayNotHasKey('access-control-allow-origin', $cors['foreign']['head']);
        $this->assertSame([200, 'https://partner.example'], [$cors['partner']['code'], $cors['partner']['head']['access-control-allow-origin'] ?? '']);
        $this->assertSame(200, $cors['own']['code'], 'The own origin of the site was refused');
        $this->assertArrayNotHasKey('access-control-allow-origin', $cors['own']['head']);
        $this->assertSame([404, 'https://partner.example'], [$cors['lost']['code'], $cors['lost']['head']['access-control-allow-origin'] ?? ''], 'A refusal lost its CORS echo');
    }

    # Maintenance closes every service but system, the switch active closes all, a switched-off action is disabled; the bucket of an address gives its burst, then 429
    #[Test]
    public function theSwitchesAndTheFloodRefuse(): void
    {
        $run = $this->getRun();
        $this->assertSame([503, 'maintenance', []], $run['maint']['echo']);
        $this->assertSame(200, $run['maint']['ping'][0], 'Maintenance closed the service system');
        $this->assertSame([503, 'disabled', []], $run['maint']['inactive']);
        $this->assertSame([503, 'disabled', []], $run['order']['off']);
        $this->assertSame([200, 200, 200], $run['flood']['pass'], 'The burst of three did not pass');
        $stop = $run['flood']['stop'];
        $this->assertSame([429, 'too_many', '60'], [$stop['code'], $stop['json']['error']['code'], $stop['head']['retry-after'] ?? '']);
    }

    # The mode file streams through getFileStream(): attachment, length, entity tag, a range, HEAD and 304; a missing file is the JSON 404, a file outside storage a logged 500
    #[Test]
    public function theModeFileStreamsAStoredFile(): void
    {
        $run = $this->getRun();
        $file = $run['file'];
        $this->assertSame([200, 1000, '1000'], [$file['code'], strlen($file['body']), $file['head']['content-length'] ?? '']);
        $this->assertStringStartsWith('attachment; filename="probe.bin"', $file['head']['content-disposition'] ?? '');
        $this->assertStringStartsWith('private', $file['head']['cache-control'] ?? '', 'A cached file action is not the private mode');
        $this->assertNotSame('', $file['head']['etag'] ?? '');
        $this->assertSame('Origin', $file['head']['vary'] ?? '', 'The file lost the headers of the guards');
        $this->assertMatchesRegularExpression('#^[0-9a-f]{16}$#D', $file['head']['x-request-id'] ?? '');
        $this->assertSame([206, '0123', 'bytes 0-3/1000'], [$run['range']['code'], $run['range']['body'], $run['range']['head']['content-range'] ?? '']);
        $this->assertSame([200, '', '1000'], [$run['fhead']['code'], $run['fhead']['body'], $run['fhead']['head']['content-length'] ?? '']);
        $this->assertSame(304, $run['same']['code']);
        $this->assertSame([404, 'no_route'], [$run['fmiss']['code'], $run['fmiss']['json']['error']['code'] ?? '']);
        $this->assertSame([500, 'internal'], [$run['fout']['code'], $run['fout']['json']['error']['code'] ?? '']);
        $this->assertTrue($run['log']['out'], 'A file outside storage was not logged as a fault of the handler');
    }

    # system/schema lists the public actions of the running services with their params and no handler, kept a minute by a public cache but never for a request with a key
    # A module service is found without a line of the core naming it, and its common.php loads for its action, not for the schema
    #[Test]
    public function theSchemaIsBuiltFromTheDeclarations(): void
    {
        $run = $this->getRun();
        $data = $run['schema']['json']['data']['services'];
        $this->assertSame(['apiprobe', 'system'], array_keys($data), 'The schema lists a refused, inactive or missing service');
        $this->assertSame(['echo', 'fail', 'crash', 'file', 'twice'], array_keys($data['apiprobe']['actions']), 'A key, secret or switched-off action is in the schema');
        $this->assertSame(['ping', 'schema'], array_keys($data['system']['actions']));
        $this->assertSame(['type' => 'num', 'req' => false, 'max' => 90, 'def' => 7], $data['apiprobe']['actions']['echo']['params']['days']);
        $this->assertSame(['type' => 'word', 'req' => true, 'values' => ['full', 'delta']], $data['apiprobe']['actions']['echo']['params']['kind']);
        $this->assertSame('file', $data['apiprobe']['actions']['file']['reply']);
        foreach (['getProbe', 'getSystem', '"call"'] as $word) $this->assertStringNotContainsString($word, $run['schema']['body'], 'The schema named a handler');
        $this->assertSame('public, max-age=60', $run['schema']['head']['cache-control']);
        $this->assertSame('private, no-store', $run['auth']['head']['cache-control'], 'A reply to a request with a key may be kept by a shared cache');
        $this->assertNotContains('modules/apiprobe/common.php', $run['schema']['files'], 'The schema loaded the handlers of a module');
        $this->assertContains('modules/apiprobe/common.php', $run['echo']['files'], 'The handlers of the module were not loaded for its action');
    }

    # ApiInput in-process: the types, the defaults and the body; a declaration of a param is accepted only in the shapes the filter reads
    #[Test]
    public function apiInputReadsOnlyTheDeclaredTypes(): void
    {
        $params = ['n' => ['num', 10], 'w' => ['word', ['a', 'b'], 'def' => 'a'], 't' => ['text', 3, 'def' => ''], 'j' => ['json', 'def' => []], 'v' => 'ver'];
        $this->assertSame(['n' => 5, 'w' => 'a', 't' => '', 'j' => [], 'v' => '1.2.3'], \ApiInput::getValues($params, ['n' => '5', 'v' => '1.2.3', 'x' => 'drop']));
        $this->assertSame(['n' => 0, 'w' => 'b', 't' => 'äöü', 'j' => ['k' => 1], 'v' => '0.0.1'],
            \ApiInput::getValues($params, ['n' => 0, 'w' => 'b', 't' => 'äöü', 'j' => '{"k":1}', 'v' => '0.0.1']));
        $bad = [['n' => '11'], ['n' => '-1'], ['n' => 1.5], ['n' => '1', 'w' => 'c'], ['n' => '1', 't' => 'abcd'], ['n' => '1', 'j' => '7'], ['n' => '1', 'v' => '1.2'],
            ['n' => '1', 'v' => ['1.2.3']], ['v' => '1.2.3']];
        foreach ($bad as $one) {
            try {
                \ApiInput::getValues($params, $one + ['v' => '1.2.3']);
                $this->fail('Accepted '.json_encode($one));
            } catch (\ApiError $err) {
                $this->assertSame([422, 'invalid'], [$err->getCode(), $err->name], json_encode($one));
            }
        }
        $this->assertSame(['a' => 1], \ApiInput::getBodyData('{"a":1}', 64));
        $this->assertSame([], \ApiInput::getBodyData(' ', 64));
        foreach (['[]' => 'bad_request', '[1,2]' => 'bad_request', '"x"' => 'bad_request', '{"a":' => 'bad_request', str_repeat(' ', 65) => 'too_large'] as $body => $code) {
            try {
                \ApiInput::getBodyData((string)$body, 64);
                $this->fail('Accepted the body '.$body);
            } catch (\ApiError $err) {
                $this->assertSame($code, $err->name);
            }
        }
        foreach (['num', ['num', 5, 'def' => 1], ['word', ['a']], ['text', 9], ['json', 'def' => []], 'ver'] as $spec) {
            $this->assertTrue(\ApiInput::checkSpec($spec), json_encode($spec));
        }
        foreach (['int', ['num', -1], ['num', 'def' => 'x'], ['word', []], ['word', ['a b']], ['text', 0], ['ver', 'x'], ['num', 'max' => 1]] as $spec) {
            $this->assertFalse(\ApiInput::checkSpec($spec), json_encode($spec));
        }
    }

    # ApiLimit in-process: IPv6 is counted by its /64 and mapped IPv4 as IPv4; a bucket gives its burst and then the seconds until a token is back
    #[Test]
    public function apiLimitCountsAnAddressByItsHost(): void
    {
        $this->assertSame('192.0.2.7', \ApiLimit::getIpKey('192.0.2.7'));
        $this->assertSame('192.0.2.7', \ApiLimit::getIpKey('::ffff:192.0.2.7'));
        $this->assertSame('2001:db8:1:2::/64', \ApiLimit::getIpKey('2001:db8:1:2:aaaa::1'));
        $this->assertSame(\ApiLimit::getIpKey('2001:db8:1:2::ffff'), \ApiLimit::getIpKey('2001:db8:1:2:9999:1:2:3'), 'Two hosts of one /64 got two buckets');
        $this->assertNotSame(\ApiLimit::getIpKey('2001:db8:1:2::1'), \ApiLimit::getIpKey('2001:db8:1:3::1'));
        $key = 'test|'.bin2hex(random_bytes(8));
        $this->assertSame([0, 0], [\ApiLimit::getWait($key, 6, 2), \ApiLimit::getWait($key, 6, 2)]);
        $this->assertSame(10, \ApiLimit::getWait($key, 6, 2), 'An empty bucket did not name the wait for one token');
        $this->assertSame(0, \ApiLimit::getWait($key, 0, 2), 'A limit of zero did not switch the bucket off');
    }
}
