<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The installation from the panel over tests/Support/apply_probe.php: the preflight, the closed site, the executor of the package, the rescuer and every rollback
final class UpdateApplyTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once over all its runs and memoize the report; it must clean up its database, and no run but the failed step may log an error
    private function getRun(string $key): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/apply_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_update_apply';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' all 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left its disposable database on the server');
            self::$probe = $data['runs'];
        }
        $this->assertArrayHasKey($key, self::$probe, 'The probe has no run '.$key);
        return self::$probe[$key];
    }

    # The look of a site back on 6.3.0 with nothing of the package and nothing of the run left over
    private function assertOldSite(array $look, string $what): void
    {
        $this->assertSame('6.3.0', $look['ver'], $what.': the version did not return');
        foreach (['new', 'folder', 'lang', 'theme', 'rescue', 'executor', 'option', 'lock', 'run', 'stage', 'now'] as $key) $this->assertFalse($look[$key], $what.': '.$key);
        $this->assertFalse($look['gone'], $what.': the deleted file did not return');
        $this->assertTrue($look['third'], $what.': the theme of a third party was touched');
        $this->assertSame(['6.3.0'], $look['marks'], $what.': the step mark of 6.3.1 survived');
        $this->assertSame('6.3.0', $look['list'], $what.': files.json did not return');
        $this->assertSame([], $look['backups'], $what.': the backup of the installation was left');
    }

    # The look of a site on 6.3.1: the package in place, its settings merged, its step marked, the rescuer swapped on step 8, the run cleaned up
    private function assertNewSite(array $look, string $what): void
    {
        $this->assertSame('6.3.1', $look['ver'], $what);
        foreach (['new', 'folder', 'gone', 'lang', 'theme', 'third', 'rescue', 'executor', 'option'] as $key) $this->assertTrue($look[$key], $what.': '.$key);
        foreach (['local', 'lock', 'run', 'stage', 'now'] as $key) $this->assertFalse($look[$key], $what.': '.$key.' left over');
        $this->assertSame(['6.3.0', '6.3.1'], $look['marks'], $what.': the step of 6.3.1 is not marked');
        $this->assertSame('6.3.1', $look['list'], $what.': files.json is not the new one');
        $this->assertSame(['6.3.0-6.3.1'], $look['backups'], $what.': the backup for going back is missing');
    }

    # The opcache rule, the steps a package brings and the order of the plan: the entries and the run copy last, the panel entry under its name, site files and the rescuer kept
    #[Test]
    public function thePlanAndTheRulesOfThePreflight(): void
    {
        $run = $this->getRun('unit');
        $this->assertSame(['off' => true, 'check' => true, 'reset' => true, 'closed' => false], $run['opcache']);
        $this->assertSame(['6.3.1'], $run['steps']);
        $this->assertSame(['sql' => true, 'php' => true], $run['files']);
        $this->assertSame('public/panel.php', $run['plan']['entry'], 'The new panel entry does not take the name of the site');
        $this->assertSame('core/apply.php', $run['plan']['last'], 'The executor is not moved last');
        $this->assertSame(['public/panel.php', 'core/apply.php'], array_slice($run['plan']['tail'], -2));
        $this->assertSame(['core/probegone.php'], $run['plan']['drops']);
        $this->assertFalse($run['plan']['rescue'], 'The rescuer moves with the files instead of on step 8');
        $this->assertFalse($run['plan']['site'], 'A settings file of the site is overwritten');
        $this->assertSame([], $run['plan']['deny']);
        $this->assertSame(($run['space']['same'] ? 1000 : 1030) + 52428800, $run['space']['need'], 'The space formula changed');
    }

    # The dry run writes nothing and names every move; a changed file stops without its mark and a file PHP cannot write stops before the site closes
    #[Test]
    public function thePreflightRefusesAndTheDryRunWritesNothing(): void
    {
        $run = $this->getRun('happy');
        $this->assertSame(303, $run['login']['login']);
        $this->assertTrue($run['login']['box'], 'The overview shows no installation under the ready package');
        $this->assertSame(200, $run['test']['code']);
        $this->assertTrue($run['test']['ready'], 'The dry run refused');
        $this->assertSame([], $run['test']['tree'], 'The dry run changed files');
        $this->assertTrue($run['test']['data'], 'The dry run changed the database');
        $this->assertFalse($run['test']['lock']);
        $this->assertSame([], $run['plan'], 'The installation moved files the dry run did not name');
        $this->assertSame([303, [], false], [$run['nomark']['code'], $run['nomark']['apply'], $run['nomark']['lock']], 'A changed file passed without its mark');
        $this->assertSame(303, $run['deny']['code']);
        $this->assertTrue($run['deny']['named'], 'The refusal does not name the file PHP cannot write');
        $this->assertSame([], $run['deny']['apply'], 'An unwritable file let the installation be confirmed');
        $this->assertSame(200, $run['code']['code']);
        $this->assertMatchesRegularExpression('#^[0-9a-f]{64}$#', $run['code']['apply']);
        $this->assertMatchesRegularExpression('#^[0-9a-f]{4}(-[0-9a-f]{4}){4}$#', $run['code']['rescue'], 'The code page shows no rescue code');
    }

    # While the lock lies every entry answers 503 before the core, the API in JSON, the pseudo-cron and a forged token too, and a second start is refused
    #[Test]
    public function theLockClosesEveryEntry(): void
    {
        $run = $this->getRun('happy');
        $this->assertSame([200, '/admin.php', 'files'], [$run['first']['code'], $run['first']['form'], $run['first']['lock']], 'The start did not close the site and dump');
        foreach ($run['closed'] as $key => $got) {
            $this->assertSame(503, $got['code'], $key.' is open during the installation');
            $this->assertSame('600', $got['retry'], $key.' names no Retry-After');
        }
        $this->assertSame('application/json; charset=UTF-8', $run['closed']['api']['type']);
        $this->assertSame('text/html; charset=utf-8', $run['closed']['site']['type']);
    }

    # The executor of the package runs the phases, no core while the files move, the new core only for the database steps, and opens the site on 6.3.1
    #[Test]
    public function theExecutorOfThePackageInstalls(): void
    {
        $run = $this->getRun('happy');
        $this->assertSame([['files', 200, ''], ['core', 200, 'new'], ['open', 200, '']], array_map(static fn(array $one): array => array_values($one), $run['walk']),
            'A phase loaded a core it must not');
        $this->assertNewSite($run['done']['look'], 'install');
        $this->assertTrue($run['done']['table'], 'The step of 6.3.1 created no table');
        $this->assertTrue($run['done']['column'], 'The step of 6.3.1 added no column');
        $this->assertTrue($run['done']['kept'], 'The installation changed rows of the site or of the foreign installation');
        $this->assertTrue($run['done']['oldrescue'], 'The old rescuer is not in the backup');
        $this->assertSame(['6.3.0', '6.3.1', 'storage/backup/update/6.3.0-6.3.1', 'wait'], [$run['done']['state']['from'], $run['done']['state']['to'],
            $run['done']['state']['back'], $run['done']['state']['check']]);
        $this->assertSame(200, $run['view']['code']);
        $this->assertContains($run['view']['check'], ['ok', 'none'], 'The self-check did not run on the overview');
        $this->assertTrue($run['view']['revert'], 'The overview offers no going back');
        $this->assertSame([303, 'ok'], [$run['selfok']['code'], $run['selfok']['check']]);
        $this->assertSame([], $run['logs'], 'The installation or going back wrote a PHP or SQL error');
    }

    # Going back returns the files, the settings and the database of 6.3.0 exactly, clears the caches and spends the backup
    #[Test]
    public function goingBackRestoresThePreviousVersion(): void
    {
        $run = $this->getRun('happy')['revert'];
        $this->assertSame(200, $run['code']['code']);
        $this->assertMatchesRegularExpression('#^[0-9a-f]{4}(-[0-9a-f]{4}){4}$#', $run['code']['rescue']);
        $this->assertSame(200, $run['first']);
        $this->assertOldSite($run['look'], 'revert');
        $this->assertSame([], $run['tree'], 'Going back left files of 6.3.1 or lost files of 6.3.0');
        $this->assertTrue($run['data'], 'Going back did not restore the database');
        $this->assertSame([[], []], [$run['state']['done'], $run['state']['fail']], 'Going back counts as a failed installation or keeps the installation');
        $this->assertSame(['6.3.1', '6.3.0', 'revert', 0], [$run['state']['undo']['from'], $run['state']['undo']['to'], $run['state']['undo']['why'],
            $run['state']['undo']['step']]);
        $this->assertSame(0, $run['look']['cache'], 'Going back left cached pages or assets');
        $this->assertFalse($run['look']['local'], 'Going back left the cached configuration');
        $this->assertSame(200, $run['site']);
    }

    # A database step that fails after creating a table and a column rolls back by itself: files, config/ with its marks and the database, the site open again
    #[Test]
    public function aFailedStepRollsBackByItself(): void
    {
        $run = $this->getRun('step');
        $this->assertSame(['files', 'core', 'roll'], array_column($run['walk'], 'phase'));
        $this->assertSame('new', $run['walk'][1]['boot']);
        $this->assertOldSite($run['look'], 'step');
        $this->assertSame([], $run['tree'], 'The rollback left files of 6.3.1');
        $this->assertTrue($run['data'], 'The rollback did not drop the table and the column of the failed step');
        $this->assertSame(['ver' => '6.3.1', 'step' => 6], $run['state']['fail'], 'The failure is not kept for the master');
        $this->assertSame(['steps', 6], [$run['state']['undo']['why'], $run['state']['undo']['step']]);
        $this->assertStringContainsString('probe_nosuch', $run['state']['undo']['error']);
        $this->assertCount(1, $run['logs'], 'The failed step wrote more than its SQL error');
        $this->assertStringContainsString('probe_nosuch', $run['logs'][0]);
        $this->assertSame(1, substr_count($run['logs'][0], "\n"));
        $this->assertFalse($run['look']['local']);
        $this->assertSame(0, $run['look']['cache'], 'The rollback left cached pages or assets');
        $this->assertSame(200, $run['site']);
    }

    # A dump that cannot be taken stops on step 4: nothing changed, the backup, the run copy and the lock gone, the site open and the package kept for another try
    #[Test]
    public function aFailedDumpStopsWithNothingChanged(): void
    {
        $run = $this->getRun('dump');
        $this->assertSame(200, $run['code']);
        $this->assertTrue($run['stopped'], 'The failed dump did not stop the installation');
        $this->assertSame(['6.3.0', false, false, [], true, ['6.3.0']], [$run['look']['ver'], $run['look']['lock'], $run['look']['run'], $run['look']['backups'],
            $run['look']['stage'], $run['look']['marks']]);
        $this->assertStringContainsString('beyond the integer range', $run['state']['undo']['error']);
        $this->assertSame([], $run['tree']);
        $this->assertTrue($run['data']);
        $this->assertSame(['ver' => '6.3.1', 'step' => 4], $run['state']['fail']);
        $this->assertSame(['dump', 4], [$run['state']['undo']['why'], $run['state']['undo']['step']]);
        $this->assertSame(200, $run['site']);
    }

    # A batch cut midway: a wrong code is refused, the right code continues the installation under a new token and the old token is dead
    #[Test]
    public function theRescueCodeContinuesACutInstallation(): void
    {
        $run = $this->getRun('crashgo');
        $this->assertSame('files', $run['phase']);
        $this->assertGreaterThan(2, $run['cut']['moves']);
        $this->assertSame([503, true, 403, 'files'], array_values($run['form']), 'The rescue page or the refusal of a wrong code is wrong');
        $this->assertSame(503, $run['old'], 'The token of the cut installation still works');
        $this->assertSame(200, $run['first']);
        $this->assertSame(['files', 'core', 'open'], array_column($run['walk'], 'phase'));
        $this->assertNewSite($run['look'], 'continued');
        $this->assertSame([], $run['state']['fail']);
        $this->assertSame(200, $run['site']);
    }

    # A batch cut midway rolled back by the code: every file back, the half moved one too, the database untouched since no step ran
    #[Test]
    public function theRescueCodeRollsBackACutInstallation(): void
    {
        $run = $this->getRun('crashback');
        $this->assertSame([503, true, 403, 'files'], array_values($run['form']));
        $this->assertSame(200, $run['first']);
        $this->assertOldSite($run['look'], 'code');
        $this->assertSame([], $run['tree'], 'The rollback of a cut batch lost or left files');
        $this->assertTrue($run['data']);
        $this->assertSame(['ver' => '6.3.1', 'step' => 5], $run['state']['fail']);
        $this->assertSame(['code', 5], [$run['state']['undo']['why'], $run['state']['undo']['step']]);
        $this->assertSame(200, $run['site']);
    }

    # A file changed after the unpacking stops the installation and reaches the overview with its mark; a package without core/apply.php installs with the installed one
    #[Test]
    public function theStopRestsOnTheFilesAsTheyAreNow(): void
    {
        $run = $this->getRun('delta');
        $this->assertSame(303, $run['nomark']);
        $this->assertFalse($run['lock'], 'A file changed after the unpacking passed without its mark');
        $this->assertSame(['core/system.php', 'core/version.php'], $run['changed'], 'The overview kept the list of the unpacking');
        $this->assertTrue($run['box'], 'The overview offers no mark to overwrite');
        $this->assertSame(200, $run['start'], 'A package without its executor could not start');
        $this->assertSame(['files', 'core', 'open'], $run['walk']);
        $this->assertSame('6.3.1', $run['look']['ver']);
        $this->assertTrue($run['table']);
        $this->assertFalse($run['look']['lock']);
    }

    # A run copy that no longer matches its hash is refused by the gate, and rescue.now put by FTP rolls back without any code and is removed
    #[Test]
    public function rescueNowRollsBackABrokenExecutor(): void
    {
        $run = $this->getRun('now');
        $this->assertSame(503, $run['broken'], 'The gate ran a run copy with a wrong hash');
        $this->assertSame(200, $run['first']);
        $this->assertOldSite($run['look'], 'now');
        $this->assertSame([], $run['tree']);
        $this->assertFalse($run['look']['local']);
        $this->assertSame(['ver' => '6.3.1', 'step' => 5], $run['state']['fail']);
        $this->assertSame(['now', 5], [$run['state']['undo']['why'], $run['state']['undo']['step']]);
        $this->assertSame(200, $run['site']);
    }
}
