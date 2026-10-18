<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The closed site of a code newer than its step mark over route_probe.php gate: the entries at 503, the light mode of the panel, then the notices and settings it opens to
final class UpdateGateTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once in its gate mode and memoize the run; the only SQL error is the step the run breaks on purpose, and no request writes a PHP error
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_update_gate';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' gate 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame([], $data['logs']['error_php.log'], 'The requests wrote PHP errors');
            $this->assertCount(1, $data['logs']['error_sql.log'], 'Only the broken step may write an SQL error');
            $this->assertStringContainsString('probe_nosuch', $data['logs']['error_sql.log'][0]);
            self::$probe = $data['runs']['gate'];
        }
        return self::$probe;
    }

    # While the code is newer than the last step mark the pages, the API, the pseudo-cron and the installer answer 503 before the core, the API in its envelope
    #[Test]
    public function aCodeAheadOfItsStepsClosesEveryEntry(): void
    {
        $run = $this->getRun()['closed'];
        foreach (['site', 'news', 'cron'] as $key) {
            $this->assertSame(503, $run[$key]['code'], $key.' is not closed');
            $this->assertSame('text/html; charset=utf-8', $run[$key]['type']);
            $this->assertSame('600', $run[$key]['retry'], $key.' names no Retry-After');
        }
        $this->assertSame(503, $run['api']['code']);
        $this->assertSame('application/json; charset=UTF-8', $run['api']['type'], 'The closed API answered something else than JSON');
        $this->assertSame(503, $run['setup'], 'setup.php is not closed');
    }

    # The panel alone opens, in its light mode on the card of the admin theme: a super-administrator gets the steps, anyone else the own login form
    #[Test]
    public function onlyTheLightModeOfThePanelOpens(): void
    {
        $run = $this->getRun();
        foreach (['root', 'module'] as $key) {
            $this->assertSame(200, $run['closed'][$key]['code']);
            $this->assertTrue($run['closed'][$key]['card'], 'The light mode rendered the panel instead of its card');
            $this->assertSame('steps', $run['closed'][$key]['op'], 'A super-administrator did not get the steps');
        }
        $this->assertSame('login', $run['closed']['guest']['op'], 'A visitor without a session did not get the login of the light mode');
        $this->assertSame('login', $run['wrong']['op'], 'A wrong password passed');
        $this->assertTrue($run['wrong']['error'], 'A wrong password named no refusal');
        $this->assertSame(303, $run['login']['code'], 'The right password did not log in');
        $this->assertSame('steps', $run['steps']['op'], 'The login did not lead to the steps');
    }

    # A failed step stops the run with the site closed and the versions before it marked; the repeat runs the rest, marks the code without a step file and opens everything
    #[Test]
    public function theStepsRunInOrderAndOpenTheSite(): void
    {
        $run = $this->getRun();
        $this->assertTrue($run['fail']['error'], 'The failed step named no refusal');
        $this->assertSame(['6.2.0', '6.2.5'], $run['fail']['marks'], 'The version before the failed step is not marked or the failed one is');
        $this->assertSame(1, $run['fail']['table'], 'The step before the failure did not run');
        $this->assertFalse($run['fail']['ran'], 'The PHP step of a failed version ran');
        $this->assertSame(503, $run['fail']['site'], 'A failed step opened the site');
        $this->assertSame([303, '/admin.php'], [$run['done']['code'], $run['done']['move']]);
        $this->assertSame(['6.2.0', '6.2.5', '6.2.7', '6.3.0'], $run['done']['marks'], 'The code without a step file got no mark');
        $this->assertTrue($run['done']['ran'], 'The PHP step did not run');
        $this->assertSame(1, $run['done']['rows'], 'The repaired SQL step did not run');
        $this->assertGreaterThan(0, $run['done']['backup'], 'No backup of the database was made before the steps');
        $this->assertCount(1, $run['done']['dump'], 'The click after the failed step took a second backup of a half-changed database');
        $this->assertMatchesRegularExpression('#^6\.3\.0-\d{8}-\d{6}$#', $run['done']['kept'][0] ?? '', 'The used backup was not set aside under its time');
        $tabs = $run['done']['dump'][0] ?? [];
        $this->assertContains('probe_admins', $tabs, 'The portion dump left out a table of the site');
        $this->assertSame([], array_values(array_filter($tabs, static fn(string $v): bool => !str_starts_with($v, 'probe_'))), 'The dump took a table of another program');
        $this->assertSame(['local' => false, 'cache' => false], $run['done']['clean'], 'The light mode left the configuration or a page of the old code cached');
        $this->assertSame([200, 200], [$run['done']['site'], $run['done']['api']], 'The site did not open after the steps');
        $this->assertFalse($run['done']['panel']['card'], 'The panel stayed in its light mode');
        $this->assertSame(['Login refused', 'Login accepted', 'update steps', 'update steps'], $run['log'], 'The light mode left its logins or steps out of admin.log');
        $this->assertSame($run['done']['backup'], $run['again']['backup'], 'A repeated click after the steps made a second backup');
        $this->assertSame($run['done']['marks'], $run['again']['marks'], 'A repeated click changed the marks');
    }

    # The release found shows red for a security fix with its page on GitHub; a note is plain text, linked only to slaed.net or the project, and hidden on the site alone
    #[Test]
    public function theNoticesAreEscapedAndHidden(): void
    {
        $run = $this->getRun();
        $this->assertTrue($run['notice']['red'], 'A security release is not red on the home of the panel');
        $this->assertTrue($run['notice']['page'], 'The release links no page on GitHub');
        $this->assertTrue($run['notice']['escaped'], 'The markup of a note reached the page');
        $this->assertTrue($run['notice']['link'], 'An address on slaed.net is no link');
        $this->assertTrue($run['notice']['text'], 'An address on another host became a link');
        $this->assertSame(303, $run['hide']['code']);
        $this->assertTrue($run['hide']['gone'], 'A hidden note is still shown');
        $this->assertSame([1760000001], $run['hide']['file'], 'The site did not keep the hidden note');
    }

    # The page shows the id of the site; its settings write update.check beside the marks of the data updates and the steps, and the daily job obeys the switch
    #[Test]
    public function theSettingsLiveBesideTheMarks(): void
    {
        $run = $this->getRun()['panel'];
        $this->assertSame(200, $run['code']);
        $this->assertTrue($run['sid'], 'The page shows no id of the installation');
        $this->assertSame(303, $run['save']);
        $this->assertSame(['upcheck' => '0', 'channel' => 'beta', 'keepbak' => '1', 'upsite' => '0'], $run['check']);
        $this->assertSame(['fields' => '6.3.0', 'points' => '6.3.0', 'ratings' => '6.3.0'], $run['kept'], 'Saving the settings changed a mark of a data update');
        $this->assertSame(['6.2.0', '6.2.5', '6.2.7', '6.3.0'], $run['steps'], 'Saving the settings lost the step marks');
        $this->assertSame('disabled', $run['job'], 'The daily job ran with the check switched off');
    }
}
