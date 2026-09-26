<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S15 of docs/node: the external materials of the extension sync. The behaviour is driven by tests/Support/route_probe.php with the
 * argument sync: the disposable database and scratch configuration of the S13 probe with two types of the extension, content active and
 * feeds disabled, real HTTP requests of the administrators to the form, the manual check and the scheduler, then the child mode syncext that
 * boots the core and asks NodeSync with a scripted transport of Feed, so no request reaches the network. The static half reads the files.
 */
final class NodeSyncTest extends TestCase
{
    private static array $probe = [];

    # The root of the tree
    private static function getRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 2));
    }

    # Run the probe once in its sync mode and memoize the run; a probe that cannot create its database or start its server is a failure, not a skip
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_node_sync';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' sync 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The routes wrote PHP or SQL errors');
            $this->assertIsArray($data['runs']['sync']['ext'] ?? null, 'The child mode syncext did not answer');
            self::$probe = $data['runs']['sync'];
        }
        return self::$probe;
    }

    # The administrative form of an external material takes the address and the period of its source instead of a body; a posted body is dropped,
    # the address is stored in the canonical form Feed requests, a source with a period is due at once and a manual one never
    #[Test]
    public function theFormStoresOneSourceInsteadOfABody(): void
    {
        $run = $this->getRun();
        $this->assertSame([200, true, true, false], $run['form'], 'The form of the type does not offer the source instead of the body');
        [$code, $body, $intro, $state, $src] = $run['add'];
        $this->assertSame([303, '', 'Own intro', 2], [$code, $body, $intro, $state], 'The new material kept a posted body or lost its own intro');
        $this->assertSame(['https://example.com/feed.xml', 600, '', '', 0, '', 0, 0, 0], [$src['url'], $src['refresh'], $src['etag'], $src['modified'], $src['fails'],
            $src['error'], $src['seen'], $src['done'], $src['never']]);
        $this->assertLessThanOrEqual(1, abs($src['lag']), 'A new source with a period is not due at once');
        $this->assertSame([1, null], [$run['manual']['never'], $run['manual']['gap']], 'A manual source got a due time');
        $this->assertSame([true, true], $run['named'], 'A refused period does not name its field');
        $this->assertSame([422, 422, 422, 422, 404, 0], $run['bad'], 'A period out of range, a foreign scheme, credentials, a missing source or a foreign moderator was accepted');
    }

    # A new period alone keeps the validators and the history and moves the next check from the last one; a new address forgets both and is due at once;
    # the body a form sends is never stored
    #[Test]
    public function anEditKeepsOrClearsTheValidatorsByWhatChanged(): void
    {
        $run = $this->getRun();
        $this->assertSame([200, true, true, false], $run['edit'], 'The edit screen misses the check button or the stored source, or offers the body');
        [$code, $body, $src] = $run['period'];
        $this->assertSame([303, ''], [$code, $body], 'The change of the period stored a posted body');
        $this->assertSame([1200, '"v1"', 'Mon, 01 Jan 2024 00:00:00 GMT', 1, 1, 1200], [$src['refresh'], $src['etag'], $src['modified'], $src['seen'], $src['done'], $src['gap']],
            'A new period dropped the validators or the history, or did not count from the last check');
        [$code, $src] = $run['address'];
        $this->assertSame([303, 'https://example.org/other.xml', '', '', 0, 0, 0], [$code, $src['url'], $src['etag'], $src['modified'], $src['seen'], $src['done'], $src['fails']],
            'A new address kept the validators or the history of the old one');
        $this->assertLessThanOrEqual(1, abs($src['lag']), 'A new address is not due at once');
    }

    # The manual check answers POST alone with the token of a moderator of the type, and a failure of the source keeps the text, counts, and waits five then ten minutes
    #[Test]
    public function theManualCheckRefusesWhatItMustAndKeepsTheTextOnFailure(): void
    {
        $run = $this->getRun();
        [$get, $notoken, $foreign, $moder, $boss, $tokens, $fails] = $run['refuse'];
        $this->assertTrue($tokens, 'The probe could not read the tokens of the other administrators');
        $this->assertSame([405, 403, 404, 404, 404, 0], [$get, $notoken, $foreign, $moder, $boss, $fails]);
        [$code, $safe, $body, $moved, $one, $again, $two] = $run['fail'];
        $this->assertSame([502, true, 'Kept body', 0], [$code, $safe, $body, $moved], 'A failed source changed the text or the version, or its answer hides the safe code');
        $this->assertSame([1, 'address', 300, '', 0], [$one['fails'], $one['error'], $one['gap'], $one['etag'], $one['done']]);
        $this->assertSame([502, 2, 600], [$again, $two['fails'], $two['gap']], 'The retry after a second failure does not double');
        $this->assertSame([200, true, false], $run['public'], 'The public page does not show the kept text or shows the source address');
    }

    # The scheduler carries nodesync with its limit bounded to 1..50 and nodepublish to 1..500, and a manual run checks only the due sources of active types outside the trash
    #[Test]
    public function theSchedulerRunsTheQueueWithItsLimit(): void
    {
        $run = $this->getRun();
        $this->assertSame([200, true, true, true, true, 303, '10', 303, '20', 'nodesync'], $run['scheduler']);
        [$code, $two, $one, $trash, $off, $manual, $state, $text] = $run['run'];
        $this->assertSame([303, 1, 'address', 300], [$code, $two['fails'], $two['error'], $two['gap']], 'The due source was not checked by the run');
        $this->assertSame([2, 0, 0, 0], [$one, $trash, $off, $manual], 'The run checked a source that was not due, in the trash, of a disabled type or manual');
        $this->assertSame(['failed', 'Node sync: 1 checked, 0 updated, 0 unchanged, 1 failed, 0 skipped'], [$state, $text]);
    }

    # The extension accepts exactly the address and the period from a moderator, has no settings, scope or action rules, and shows its row to a moderator in the admin view only
    #[Test]
    public function theExtensionChecksItsInputAndItsReaders(): void
    {
        $ext = $this->getRun()['ext'];
        $bad = ['ok' => false, 'code' => 3];
        $this->assertSame(['ok' => true, 'value' => []], $ext['config'][0]);
        $this->assertSame($bad, array_slice($ext['config'][1], 0, 2));
        $this->assertSame(['ok' => true, 'value' => ['url' => 'https://example.com/a%7Cb?q=1', 'refresh' => 0]], $ext['input']['good']);
        $this->assertSame(['ok' => true, 'value' => ['url' => 'https://example.com/feed', 'refresh' => 31536000]], $ext['input']['intl']);
        foreach (['low', 'high', 'text', 'lost', 'extra', 'port', 'long'] as $key) $this->assertSame($bad, array_slice($ext['input'][$key], 0, 2), $key);
        $this->assertSame(['ok' => false, 'code' => 2], array_slice($ext['input']['anna'], 0, 2), 'A visitor who does not moderate the type set a source');
        $this->assertSame([['join' => '', 'where' => '', 'params' => []], ['ok' => true, 'value' => true]], array_slice($ext['scope'], 0, 2));
        $this->assertSame($bad, array_slice($ext['scope'][2], 0, 2), 'An action outside the closed set was accepted');
        $this->assertSame([['url', 'refresh', 'due', 'checked', 'synced', 'fails', 'error'], [], []], $ext['data'], 'A public view or a foreign moderator got the source');
    }

    # A new text changes the body, the version and the cache generation once; 304 sends the stored validators and the same text changes nothing but the source row
    #[Test]
    public function aFetchWritesOnlyANewText(): void
    {
        $ext = $this->getRun()['ext'];
        [$res, $body, $ver, $epoch, $src, $cond] = $ext['new'];
        $this->assertSame(['status' => 'updated', 'error' => ''], array_slice($res, 1), 'A new text was not stored');
        $this->assertSame([true, 1, 1, false], [$body, $ver, $epoch, $cond], 'The new text did not reach the body once, the cache generation or the version');
        $this->assertSame(['"e1"', 'Tue, 02 Jan 2024 00:00:00 GMT', 0, 1, 1, 1200], [$src['etag'], $src['modified'], (int)$src['fails'], (int)$src['seen'], (int)$src['done'],
            (int)$src['gap']]);
        [$res, $etag, $since, $same, $ver, $epoch, $kept] = $ext['same'];
        $this->assertSame(['unchanged', '"e1"', 'Tue, 02 Jan 2024 00:00:00 GMT', true, 0, 0, '"e1"'], [$res['status'], $etag, $since, $same, $ver, $epoch, $kept]);
        $this->assertSame(['unchanged', 0, '"e2"'], [$ext['equal'][0]['status'], $ext['equal'][1], $ext['equal'][2]], 'The same text wrote the body or kept the old validator');
    }

    # A failure keeps the text and the validators and waits longer; a concurrent change of the version or the address writes nothing; a text the column cannot hold fails
    #[Test]
    public function aFailureOrARaceKeepsTheStoredText(): void
    {
        $ext = $this->getRun()['ext'];
        [$one, $first, $two, $second, $kept, $ver] = $ext['error'];
        $this->assertSame(['failed', 'status', 1, 'status', 300, '"e2"'], [$one['status'], $one['error'], (int)$first['fails'], $first['error'], (int)$first['gap'],
            $first['etag']]);
        $this->assertSame(['failed', 'xml', 2, 600, true, 0], [$two['status'], $two['error'], (int)$second['fails'], (int)$second['gap'], $kept, $ver]);
        [$stale, $wrote, $moved, $src, $epoch] = $ext['race'];
        $this->assertSame(['skipped', false, 'skipped', 'https://example.net/moved.xml', 0, 0], [$stale['status'], $wrote, $moved['status'], $src['url'],
            (int)$src['fails'], $epoch], 'A result fetched before a concurrent change was written or dropped the page cache');
        $this->assertSame([['id' => $ext['new'][0]['id'], 'status' => 'failed', 'error' => 'body'], true], $ext['huge'], 'A text beyond nodes.body was stored');
        $this->assertSame([['id' => $ext['new'][0]['id'], 'status' => 'failed', 'error' => 'storage'], true, 1], $ext['undo'],
            'A rollback whose outcome is unknown stored the text or freed the cache guard');
    }

    # The manual check is the moderator's and the queue the background context's alone; the queue takes 1..50 and checks the due sources in due order
    #[Test]
    public function theQueueBelongsToTheBackgroundContext(): void
    {
        $ext = $this->getRun()['ext'];
        $this->assertSame([2, 2, 1, 1, 2], array_map(fn($v) => $v['code'], $ext['refused']),
            'A background context, a reader, a foreign or missing material or a moderator was served');
        $this->assertSame([3, 3], array_map(fn($v) => $v['code'], $ext['limits']), 'A limit outside 1..50 was accepted');
        [$list, $urls, $two, $manual, $trash, $off] = $ext['queue'];
        $this->assertSame(['status' => 'success', 'message' => 'Node sync: 2 checked, 1 updated, 1 unchanged, 0 failed, 0 skipped',
            'extra' => ['checked' => 2, 'updated' => 1, 'unchanged' => 1, 'failed' => 0, 'skipped' => 0]], $list);
        $this->assertSame(['https://example.com/two.xml', 'https://example.com/feed.xml'], $urls, 'The queue did not fetch the due sources in due order');
        $this->assertSame([true, 0, 0, 0], [$two, (int)$manual, (int)$trash, (int)$off], 'The queue checked a manual, trashed or disabled source');
    }

    # The hooks refuse a body from a form inside the transaction of the material and roll the whole write back; the page cache drops the old list after a new text
    #[Test]
    public function theHooksRollBackAndTheCacheFollows(): void
    {
        $run = $this->getRun();
        [$add, $rows, $edit, [$url, $body]] = $run['ext']['hooks'];
        $this->assertSame([['ok' => false, 'code' => 3, 'msg' => 'Invalid sync input: body'], 0], [$add, $rows], 'A new material with a body was stored');
        $this->assertSame(['ok' => false, 'code' => 3, 'msg' => 'Invalid sync input: body'], $edit, 'A changed body was accepted');
        $this->assertSame(['https://example.com/feed.xml', true], [$url, $body], 'The refused edit changed the source or the body');
        $this->assertSame([true, true, true, true], $run['cache'], 'The list was not cached, or the new text did not invalidate it, or the page misses the text');
    }

    # The stage keeps its files and contracts: the closed factory hands Feed to sync alone, the class never calls the writer of Node, Feed shares one normalizer,
    # and the system job nodesync is registered in all four places with the same settings
    #[Test]
    public function theStageFilesKeepTheirContract(): void
    {
        $root = self::getRoot();
        $load = (string)file_get_contents($root.'/core/classes/node/ext/load.php');
        $this->assertStringContainsString("\$map = ['support' => ['support.php', 'NodeSupport'], 'sync' => ['sync.php', 'NodeSync']];", $load);
        $this->assertStringContainsString("return new NodeSync(\$db, \$context, new Feed(\$conf['rss'] ?? []));", $load);
        $code = (string)file_get_contents($root.'/core/classes/node/ext/sync.php');
        $this->assertStringContainsString('final class NodeSync implements NodeExtension', $code);
        $this->assertStringContainsString('public function __construct(Database $db, NodeContext $context, Feed $feed)', $code);
        foreach (['public function updateNodeSync(int $id): array', 'public function updateNodeSyncList(int $limit): array'] as $one) {
            $this->assertStringContainsString($one, $code);
        }
        $this->assertSame(12, preg_match_all('/^    public function /m', $code),
            'The class has other public methods than its constructor, the nine of the contract and its two commands');
        $this->assertSame(0, preg_match('/new NodeService|NodeService::|NodeService $/', $code), 'The extension depends on the writer of Node');
        $this->assertStringNotContainsString('syncbatch', $code, 'The network queue uses the batch of the global subsystems');
        $this->assertStringNotContainsString('curl_', $code, 'The extension carries its own transport');
        $this->assertSame(1, substr_count($code, '$this->feed->getFeedContent('), 'The feed is fetched from more than one place');
        $from = strpos($code, 'private function setSourceCheck(');
        $body = substr($code, $from, strpos($code, "\n    }\n", $from) - $from);
        $this->assertStringNotContainsString('setSqlBegin', $body, 'The fetch runs inside a transaction');
        $from = strpos($code, 'public function updateNodeSyncList(');
        $body = substr($code, $from, strpos($code, "\n    }\n", $from) - $from);
        $this->assertSame(1, substr_count($body, 'getSourceSnaps('), 'The queue is not prepared by one statement');
        $sys = (string)file_get_contents($root.'/core/system.php');
        $this->assertStringContainsString("'nodepublish' => 'nodepublish', 'nodesync' => 'nodesync'];", $sys);
        $this->assertStringContainsString("'nodesync' => addNodeSyncTask(),", $sys);
        $this->assertStringContainsString('$ext->updateNodeSyncList(max(1, min(50, $lim)))', $sys);
        $job = ['title' => 'Node sync', 'type' => 'system', 'active' => '1', 'system' => 'nodesync', 'schedule' => '*/5 * * * *', 'priority' => '7', 'lock_timeout' => '180',
            'manual' => '1', 'settings' => ['limit' => '10']];
        $this->assertSame($job, (require $root.'/config/scheduler.php')['scheduler']['jobs']['nodesync']);
        $setup = (string)file_get_contents($root.'/setup/index.php');
        $this->assertStringContainsString("if (is_array(\$sched) && !isset(\$sched['jobs']['nodesync'])) {", $setup);
        $this->assertStringContainsString("'system' => 'nodesync',", $setup);
        $this->assertStringContainsString("const SCHED_LIMITS = ['nodepublish' => 500, 'nodesync' => 50];", (string)file_get_contents($root.'/admin/modules/scheduler.php'));
        $this->assertStringContainsString("case 'sync': sync(); break;", (string)file_get_contents($root.'/modules/node/admin/index.php'));
        $this->assertStringNotContainsString("'sync' =>", substr((string)file_get_contents($root.'/modules/node/index.php'), 0, 2000), 'The public routes carry an operation sync');
    }
}
