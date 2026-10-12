<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The file journals of docs/ARCHITECTURE.md, "Journal Boundary", over real requests, driven by tests/Support/route_probe.php with the argument journal
final class AdminLogTest extends TestCase
{
    private static array $probe = [];

    private static string $work = '';

    # Run the probe once and memoize its run; the requests may write no PHP and no SQL error
    private function getRun(): array
    {
        if (self::$probe === []) {
            self::$work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_adminlog_'.bin2hex(random_bytes(4));
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg(self::$work).' journal 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left its database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs']);
            self::$probe = $data['runs']['journal'];
        }
        return self::$probe;
    }

    # Remove the scratch tree of the probe
    public static function tearDownAfterClass(): void
    {
        $drop = static function (string $dir) use (&$drop): void {
            if (!is_dir($dir)) return;
            foreach (scandir($dir) ?: [] as $name) {
                if ($name === '.' || $name === '..') continue;
                is_dir($dir.'/'.$name) ? $drop($dir.'/'.$name) : unlink($dir.'/'.$name);
            }
            rmdir($dir);
        };
        if (self::$work !== '') $drop(self::$work);
    }

    # Reduce journal lines to the fields an action of the panel carries
    private function getActions(array $lines): array
    {
        return array_map(static fn(array $v): array => [$v['event'], $v['aname'], $v['modul'], $v['op'], $v['target'], $v['num'], $v['result'], $v['body']], $lines);
    }

    # Every POST of the panel that passes its token is one line with scope, operation, object and result and no request body; a refused token and the site write none
    #[Test]
    public function aPassedTokenWritesOneLine(): void
    {
        $run = $this->getRun()['actions'];
        $this->assertSame([303, 303, 303, 200, 303, 303, 200, 200, 303, 200], $run['codes']);
        $this->assertSame([true, true, true, true, true], $run['tokens'], 'a form of the run lost its token');
        $this->assertSame([true, 0, 0], $run['gone'], 'the message and the comments were not removed');
        $want = [
            ['action', 'root', 'messages', 'save', 0, 0, 'done', false],
            ['action', 'root', 'messages', 'status', 1, 1, 'done', false],
            ['action', 'root', 'messages', 'save', 1, 1, 'open', false],
            ['action', 'root', 'messages', 'save', 1, 1, 'done', false],
            ['action', 'root', 'comments', 'delete', 1, 2, 'done', false],
            ['action', 'root', 'blocks', 'updateAdminBlockOrder', 0, 0, 'open', false],
            ['action', 'root', 'database', 'dump', 0, 0, 'open', false],
        ];
        $this->assertSame($want, $this->getActions($run['lines']), 'one line per passed POST: a refused token, a second check and the mode of the site add none');
        $this->assertSame(['info'], array_values(array_unique(array_column($run['lines'], 'level'))));
    }

    # The database tool writes its run through Logger, without the posted dump
    #[Test]
    public function theDatabaseToolWritesItsRun(): void
    {
        $run = $this->getRun()['actions']['database'];
        $this->assertSame([['info', 'Run started', 1, false], ['info', 'Block done', 1, false]], array_map(static fn(array $v): array => [$v['level'], $v['msg'],
            $v['blocks'] ?? $v['block'], $v['body']], $run));
        $this->assertSame('SELECT', $run[1]['kind']);
    }

    # A journal that cannot be written leaves the action done and its redirect intact
    #[Test]
    public function aFailedWriteLeavesTheActionDone(): void
    {
        $this->assertSame([303, 1, 0], $this->getRun()['broken']);
    }

    # A login of the panel is one event of admin.log, refused at warning and accepted at info, with the name tried and no request body
    #[Test]
    public function aPanelLoginIsOneEvent(): void
    {
        [$gate, $open, $lines] = $this->getRun()['panellogin'];
        $this->assertSame([200, 303], [$gate, $open]);
        $want = [
            ['warning', 'login', 'password', 'login', 4, 'docsman', false],
            ['info', 'login', 'ok', 'login', 4, 'docsman', false],
        ];
        $this->assertSame($want, array_map(static fn(array $v): array => [$v['level'], $v['event'], $v['result'], $v['way'], $v['uid'], $v['login'], $v['body']], $lines));
    }

    # A login of the site, by password or through OAuth, is one event of user.log, and oauth.log keeps the protocol alone
    #[Test]
    public function aSiteLoginIsWrittenOnce(): void
    {
        $run = $this->getRun();
        $this->assertSame([true, 200, 303], $run['sitelogin']);
        $this->assertSame([302, true, 303, 303], $run['oauth']['codes']);
        $this->assertSame(1, $run['oauth']['linked'], 'the right password did not link the account');
        $want = [
            ['warning', 'password', 'login'],
            ['info', 'ok', 'login'],
            ['warning', 'password', 'login'],
            ['info', 'ok', 'oauth'],
        ];
        $this->assertSame($want, array_map(static fn(array $v): array => [$v['level'], $v['result'], $v['way']], $run['oauth']['user']));
        $this->assertSame([false], array_values(array_unique(array_column($run['oauth']['user'], 'body'))));
        $this->assertSame(['clara'], array_values(array_unique(array_column($run['oauth']['user'], 'login'))));
        $want = [['info', 'oauth_init', 'google', null, false], ['info', 'oauth_link', 'google', 4, false]];
        $this->assertSame($want, array_map(static fn(array $v): array => [$v['level'], $v['msg'], $v['prov'], $v['uid'] ?? null, $v['body']], $run['oauth']['events']),
            'the login itself and a refused password of the link form are no events of oauth.log');
    }

    # admin.log opens as a table of its actions newest first: who, object and result in words, an unknown operation by its name, the logins only on their filter
    #[Test]
    public function theActionLogReadsAsTable(): void
    {
        $run = $this->getRun()['read'];
        $this->assertSame([['root #1', 'dump', ''], ['root #1', '', ''], ['root #1', '', '#1 +1'], ['root #1', '', '#1'], ['root #1', '', '#1'], ['root #1', '', '#1'],
            ['root #1', '', '']], array_map(static fn(array $v): array => [$v[2], $v[4] === 'dump' ? 'dump' : '', $v[5]], $run['actions']));
        $this->assertSame([], array_intersect(array_column($run['actions'], 4), ['save', 'status', 'delete', 'updateAdminBlockOrder']), 'a common operation shows its name');
        $this->assertSame([], array_intersect(array_column($run['actions'], 3), ['messages', 'comments', 'blocks', 'database']), 'a module shows its scope');
        $this->assertSame([['docsman #4', 'info'], ['docsman #4', 'warning']], array_map(static fn(array $v): array => [$v[2], $v[1]], $run['logins']));
        $this->assertSame([9, 4, 3], [$run['all'], $run['modul'], $run['open']], 'the filters of events, module and result');
        $this->assertFalse($run['foreign'], 'a line that is not JSON is skipped');
    }

    # A journal pages by 50 newest first, its level filter is a threshold and its period takes whole days
    #[Test]
    public function aJournalPagesAndFilters(): void
    {
        $run = $this->getRun()['read'];
        $this->assertSame([50, true, 20, 10], $run['pages']);
        $this->assertSame(['Probe line 119', 'Probe line 119', ['error']], $run['order']);
        $this->assertSame([50, 0, 0], $run['days']);
    }

    # Every module links its actions on the security screen for a super-admin; an administrator who may not open it sees no link and no table
    #[Test]
    public function theLinkIsForSuperAdmins(): void
    {
        $this->assertSame([true, 200, false, false], $this->getRun()['read']['link']);
    }

    # The two order handlers of the panel pass through the shared guard with their scopes, and Oauth keeps no journal of its own
    #[Test]
    public function theWritersAreTheChannels(): void
    {
        $root = dirname(__DIR__, 2);
        $admin = (string)file_get_contents($root.'/core/admin.php');
        $this->assertStringContainsString("if (checkAdminPost('categories')) {", $admin);
        $this->assertStringContainsString("if (checkAdminPost('blocks')) {", $admin);
        $code = '';
        foreach (['core', 'admin', 'modules'] as $dir) {
            $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($walk as $file) if ($file->getExtension() === 'php') $code .= (string)file_get_contents($file->getPathname());
        }
        $this->assertSame(0, preg_match('/\bsetLog\(|\baddDblog\(|\bwrite_log\(|Logger::addEntry\(|oauth_callback_success|link_login_failed/', $code));
    }
}
