<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The handlers behind the foot of a panel list take every row of id[] and refuse the whole post without its token
final class ListPickTest extends TestCase
{
    # Run one scenario of tests/Support/pick_probe.php, which lifts one shipped handler over stubs, and answer its report
    private function getProbe(array $case): array
    {
        $script = dirname(__DIR__).'/Support/pick_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_pick_'.md5((string)json_encode($case)).'_'.getmypid();
        $cmd = escapeshellarg(PHP_BINARY).' -d opcache.enable_cli=0 '.escapeshellarg($script).' '.escapeshellarg($work)
            .' '.escapeshellarg(base64_encode((string)json_encode($case)));
        $out = (string)shell_exec($cmd.' 2>&1');
        foreach (['logs', 'config', ''] as $dir) {
            foreach (glob($work.'/'.$dir.'/*.*') ?: [] as $file) unlink($file);
            if ($dir !== '' && is_dir($work.'/'.$dir)) rmdir($work.'/'.$dir);
        }
        if (is_dir($work)) rmdir($work);
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
        $this->assertSame('', $data['error'], 'The probe failed');
        return $data['data'];
    }

    # One scenario of every handler a foot of the canon posts to, with the two rows 3 and 5 picked
    public static function getHandlers(): array
    {
        $jobs = ['scheduler' => ['jobs' => ['alpha' => ['type' => 'custom'], 'beta' => ['type' => 'custom'], 'gamma' => ['type' => 'system']]]];
        $bans = ['security' => ['blocker_ip' => '10.0.0.1|0|100|a||10.0.0.2|h|200|b||10.0.0.3|0|300|c||', 'blocker_user' => 'ann|100|a||bob|200|b||cid|300|c||']];
        return [
            'favorites' => [['file' => 'admin/modules/favorites.php', 'func' => 'delete', 'post' => ['id' => [3, 5]]]],
            'privat' => [['file' => 'admin/modules/privat.php', 'func' => 'delete', 'post' => ['id' => [3, 5]]]],
            'messages delete' => [['file' => 'admin/modules/messages.php', 'func' => 'delete', 'post' => ['id' => [3, 5]]]],
            'messages status' => [['file' => 'admin/modules/messages.php', 'func' => 'status', 'post' => ['id' => [3, 5], 'act' => 1]]],
            'newsletter campaigns' => [['file' => 'admin/modules/newsletter.php', 'func' => 'delete', 'post' => ['id' => [3, 5]]]],
            'newsletter queue' => [['file' => 'admin/modules/newsletter.php', 'func' => 'drop', 'lift' => ['getQueueQuery'], 'post' => ['id' => [3, 5]]]],
            'newsletter dead' => [['file' => 'admin/modules/newsletter.php', 'func' => 'revive', 'post' => ['id' => [3, 5]]]],
            'search' => [['file' => 'modules/search/admin/index.php', 'func' => 'drop', 'lift' => ['getSearchlink', 'getSearchwhere'], 'conf' => ['search' => ['mods' => '']],
                'post' => ['id' => [3, 5]]]],
            'new users' => [['file' => 'modules/account/admin/index.php', 'func' => 'newdrop', 'lift' => ['getAccountScope'], 'post' => ['id' => [3, 5]]]],
            'oauth' => [['file' => 'modules/account/admin/index.php', 'func' => 'oauthunlink', 'lift' => ['getAccountScope'], 'post' => ['id' => [3, 5]],
                'read' => ['3' => ['uid' => 7, 'provider' => 'github'], '5' => ['uid' => 8, 'provider' => 'google']]]],
            'security logs' => [['file' => 'admin/modules/security.php', 'func' => 'delete', 'post' => ['id' => ['hack', 'warn']], 'logs' => ['hack', 'user', 'warn']]],
            'ip bans' => [['file' => 'admin/modules/security.php', 'func' => 'bansave', 'conf' => $bans,
                'post' => ['kind' => 'ip', 'id' => ['10.0.0.1/32|0|100', '10.0.0.3/32|0|300']]]],
            'nick bans' => [['file' => 'admin/modules/security.php', 'func' => 'bansave', 'conf' => $bans, 'post' => ['kind' => 'user', 'id' => ['ann|100', 'cid|300']]]],
            'scheduler' => [['file' => 'admin/modules/scheduler.php', 'func' => 'delete', 'conf' => $jobs, 'config' => ['file' => 'scheduler.php', 'data' => $jobs],
                'post' => ['id' => ['alpha', 'beta']]]],
        ];
    }

    # A post without its token touches nothing at all, whatever rows it names, and says so
    #[Test]
    #[DataProvider('getHandlers')]
    public function aPostWithoutTokenIsRefusedWhole(array $case): void
    {
        $data = $this->getProbe($case + ['token' => false]);
        $this->assertSame([], $data['write'], 'A refused post wrote to the database');
        $this->assertSame([], array_filter($data['call'], static fn(array $one): bool => $one[0] !== 'getCampLeft'), 'A refused post called its class');
        $this->assertSame([], $data['cfg'], 'A refused post wrote its configuration');
        $this->assertSame(($case['logs'] ?? []), $data['logs'], 'A refused post deleted a log');
        $this->assertSame('_tokenmiss', $data['text']);
        $this->assertTrue($data['warn']);
    }

    # A post with its token acts on every picked row and on nothing else
    #[Test]
    #[DataProvider('getHandlers')]
    public function aPickedPostActsOnEveryRow(array $case): void
    {
        $data = $this->getProbe($case + ['token' => true]);
        $this->assertFalse($data['warn'], 'The post of two rows was refused: '.$data['text']);
        $name = $case['file'].' '.$case['func'].'()';
        $writes = array_values(array_filter($data['write'], static fn(array $one): bool => in_array($one['sql'], ['DELETE', 'UPDATE'], true)));
        $calls = array_values(array_filter($data['call'], static fn(array $one): bool => in_array($one[0], ['deleteAdminMessage', 'deleteQueueRows', 'deleteDeadMail'], true)));
        match ($case['file'].' '.$case['func']) {
            'admin/modules/privat.php delete' => $this->assertSame([['deleteAdminMessage', [3, 5]]], $calls, $name),
            'admin/modules/newsletter.php drop' => $this->assertSame([['deleteQueueRows', [3, 5]]], $calls, $name),
            'admin/modules/newsletter.php revive' => $this->assertSame([['deleteDeadMail', [3, 5]]], $calls, $name),
            'admin/modules/security.php delete' => $this->assertSame(['user'], $data['logs'], $name),
            'admin/modules/scheduler.php delete' => $this->assertSame(['gamma'], array_keys($data['cfg']['scheduler.php']['jobs']), $name.' kept the wrong jobs'),
            'admin/modules/security.php bansave' => $this->assertSame(($case['post']['kind'] === 'ip') ? '10.0.0.2|h|200|b||' : 'bob|200|b||',
                $data['cfg']['security.php'][($case['post']['kind'] === 'ip') ? 'blocker_ip' : 'blocker_user'], $name.' kept the wrong bans'),
            default => $this->assertSame([3, 5], array_map(static fn(array $one): int => intval($one['pars']['id'] ?? $one['pars']['mid'] ?? 0), $writes),
                $name.' did not act on both rows'),
        };
    }

    # Every paged list of the foot with a sweep: its handler posted with upto and no id[], the keys 3 and 5 of the portion, the table it reads and what it acted on
    public static function getSweeps(): array
    {
        $cases = self::getHandlers();
        $sweep = static fn(array $case, string $from, array $acted, array $post = []): array => [array_replace($case, ['post' => $post + ['upto' => 9, 'later' => 1],
            'keys' => [3, 5], 'from' => $from, 'acted' => $acted])];
        return [
            'favorites' => $sweep($cases['favorites'][0], 'probe_favorites', [3, 5]),
            'privat' => $sweep($cases['privat'][0], 'probe_privat', [3, 5]),
            'newsletter queue' => $sweep($cases['newsletter queue'][0], 'probe_getQueueScope WHERE (kind = :kind OR status = :stat) AND id > :after', [3, 5]),
            'newsletter dead' => $sweep($cases['newsletter dead'][0], 'probe_maildead', [3, 5]),
            'search' => $sweep($cases['search'][0], 'probe_search', [3, 5]),
            'search top' => $sweep($cases['search'][0] + ['read' => ['3' => 'alpha', '5' => 'beta']], 'GROUP BY word) AS q', ['alpha', 'beta'], ['top' => 1]),
            'new users' => $sweep($cases['new users'][0], 'probe_users_temp', [3, 5]),
            'oauth' => $sweep($cases['oauth'][0], 'probe_user_oauth AS o', [3, 5]),
        ];
    }

    # A post with upto runs one portion of its own list: the keys of the portion are acted on one by one and the answer is the JSON of the run, not a redirect
    #[Test]
    #[DataProvider('getSweeps')]
    public function aSweepActsOnThePortionOfItsList(array $case): void
    {
        $data = $this->getProbe($case + ['token' => true]);
        $name = $case['file'].' '.$case['func'].'()';
        $this->assertArrayNotHasKey('url', $data, $name.' redirected a sweep');
        $this->assertSame(['done' => 2, 'fail' => 0, 'after' => 5, 'more' => false], json_decode($data['out'], true), $name.' answered another run');
        $this->assertStringContainsString($case['from'], $data['sweep']['sql'], $name.' swept another list');
        $this->assertSame(['after' => 0, 'upto' => 9], array_intersect_key($data['sweep']['pars'], ['after' => 0, 'upto' => 0]), $name.' lost its cursor');
        $writes = array_filter($data['write'], static fn(array $one): bool => $one['sql'] === 'DELETE');
        $calls = array_filter($data['call'], static fn(array $one): bool => in_array($one[0], ['deleteAdminMessage', 'deleteQueueRows', 'deleteDeadMail'], true));
        $acted = [...array_map(static fn(array $one): mixed => $one['pars']['id'] ?? $one['pars']['word'] ?? null, $writes),
            ...array_map(static fn(array $one): mixed => $one[1][0], $calls)];
        $this->assertSame($case['acted'], $acted, $name.' did not act on the keys of its portion');
        $this->assertSame(['result' => 'done', 'num' => 2], $data['log'], $name.' did not close its journal record with the rows of its portion');
        $this->assertMatchesRegularExpression('#WHERE (\(.+\) AND )?\S+ > :after AND \S+ <= :upto#', $data['sweep']['sql'],
            $name.' left the condition of its list open to the cursor');
    }

    # A sweep without its token runs no portion and is refused like any deferred post
    #[Test]
    #[DataProvider('getSweeps')]
    public function aSweepWithoutTokenRunsNothing(array $case): void
    {
        $data = $this->getProbe($case + ['token' => false]);
        $this->assertArrayNotHasKey('sweep', $data, 'A refused sweep read its list');
        $this->assertSame([], $data['write'], 'A refused sweep wrote to the database');
        $this->assertSame('_tokenmiss', $data['text']);
    }

    # A campaign whose mail still waits in the queue keeps its row, and the post says it was refused
    #[Test]
    public function aBusyCampaignStays(): void
    {
        $data = $this->getProbe(['file' => 'admin/modules/newsletter.php', 'func' => 'delete', 'post' => ['id' => [3, 5]], 'left' => ['5' => 2], 'token' => true]);
        $this->assertSame([['sql' => 'DELETE', 'pars' => ['id' => 3]]], $data['write']);
        $this->assertTrue($data['warn'], 'A busy campaign counted as deleted');
    }

    # A system job among the picked ones keeps its place, the custom one beside it goes, and the post says not all of it was done
    #[Test]
    public function aSystemJobStays(): void
    {
        $case = self::getHandlers()['scheduler'][0];
        $case['post']['id'] = ['alpha', 'gamma'];
        $data = $this->getProbe($case + ['token' => true]);
        $this->assertSame(['beta', 'gamma'], array_keys($data['cfg']['scheduler.php']['jobs']));
        $this->assertTrue($data['warn'], 'A system job counted as deleted');
    }

    # Every handler a row drop of a list posts to reads the rows of id[] after its token, the ones of Node, the users and the polls included
    #[Test]
    public function everyDropHandlerReadsIds(): void
    {
        $root = dirname(__DIR__, 2);
        $seen = [];
        foreach ([...glob($root.'/admin/modules/*.php'), ...glob($root.'/modules/*/admin/index.php')] as $file) {
            $code = (string)file_get_contents($file);
            $name = substr($file, strlen($root) + 1);
            preg_match_all("#\[[^\[\]]*'id\[\]' => [^\[\]]*\]#", $code, $acts);
            foreach (preg_grep("#'op' => '\w+'#", $acts[0]) as $act) {
                preg_match("#'op' => '(\w+)'#", $act, $op);
                $this->assertMatchesRegularExpression("#case '".$op[1]."': (\w+)\(\); break;#", $code, $name.' has no handler for op '.$op[1]);
                preg_match("#case '".$op[1]."': (\w+)\(\); break;#", $code, $func);
                $from = strpos($code, "\nfunction ".$func[1].'(');
                $body = substr($code, $from, strpos($code, "\n}\n", $from) - $from);
                $this->assertStringContainsString("getVar('post', 'id[]'", $body, $name.' '.$func[1].'() does not read id[]');
                $this->assertStringContainsString('checkAdminPost(', $body, $name.' '.$func[1].'() checks no token');
                $seen[$name.' '.$func[1]] = true;
            }
        }
        foreach (['modules/node/admin/index.php delete', 'modules/account/admin/index.php delete', 'modules/voting/admin/index.php delete', 'admin/modules/comments.php approve']
            as $one) $this->assertArrayHasKey($one, $seen, $one.'() is not reached by a row drop');
        $this->assertGreaterThanOrEqual(12, count($seen), 'The scan found too few handlers to hold anything');
    }

    # The unlink of an account is journalled with the account and the provider its row held
    #[Test]
    public function anUnlinkNamesItsAccount(): void
    {
        $case = self::getHandlers()['oauth'][0] + ['token' => true];
        $data = $this->getProbe($case);
        $logs = array_values(array_filter($data['call'], static fn(array $one): bool => $one[0] === 'addOauth'));
        $this->assertSame([['addOauth', 'oauth_admin_unlink', ['prov' => 'github', 'uid' => 7, 'actor' => 1]],
            ['addOauth', 'oauth_admin_unlink', ['prov' => 'google', 'uid' => 8, 'actor' => 1]]], $logs);
    }
}
