<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S20.2 of docs/node: the module registry of the 6.3 update keeps the switches a 6.2 site stored in its _modules table over the shipped
 * config/modules.php, and the preflight of setup/index.php refuses a clean installation over the tables of its prefix or on an old server and an update
 * whose prefix has no users and admins tables. tests/Support/update_probe.php lifts the shipped functions out of the installer by name and drives them
 * on a scratch site and a disposable schema, so the stand is never touched.
 */
final class UpdateSetupTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once in its setup mode and memoize the report for every test in this class
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/update_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_update_setup';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' setup 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            if (!empty($data['error'])) $this->markTestSkipped('Probe: '.$data['error']);
            $this->assertTrue($data['clean'], 'The probe left its schema on the server');
            self::$probe = $data['runs']['clean'];
        }
        return self::$probe;
    }

    # A module switched off, shown to a group and placed in blocks by the 6.2 site keeps that after the update, although the shipped record says otherwise;
    # a module without a row keeps the shipped record, a module without a record gets the default, node gets the record of a clean installation,
    # the record of a module that left the tree is dropped and named, the numbered rights become names, and a repeat changes nothing
    #[Test]
    public function theSiteRegistryWinsOverTheRelease(): void
    {
        $run = $this->getRun()['site'];
        $ship = $run['ship'];
        $this->assertSame(['1', '0', '0'], [$ship['forum']['active'], $ship['forum']['view'], $ship['forum']['group']], 'The shipped forum record no longer differs from the site');
        $this->assertSame(['config', 'extra', 'forum', 'node', 'shop'], $run['names']);
        $this->assertSame(['active' => 0, 'view' => 1, 'menu' => 0, 'group' => 3, 'side' => 1, 'top' => 1, 'lang' => '_FORUM', 'icon' => $ship['forum']['icon']],
            $run['mods']['forum']);
        $this->assertSame(['active' => 1, 'view' => 2, 'menu' => 1, 'group' => 0, 'side' => 2, 'top' => 0], array_slice($run['mods']['shop'], 0, 6));
        $this->assertSame(['active' => 0, 'view' => 0, 'menu' => 1, 'group' => 0, 'side' => 0, 'top' => 0, 'lang' => '_EXTRA', 'icon' => 'puzzle'], $run['mods']['extra']);
        $this->assertSame(['active' => 1, 'view' => 0, 'menu' => 0, 'group' => 0, 'side' => 2, 'top' => 0], array_slice($run['mods']['node'], 0, 6));
        $this->assertStringContainsString('records of removed modules dropped:', $run['text']);
        $this->assertStringContainsString(' news,', $run['text']);
        $this->assertSame('forum,news,shop', $run['rights']);
        $again = $this->getRun()['again'];
        $this->assertSame([$run['mods'], $run['rights'], ''], [$again['mods'], $again['rights'], $again['text']], 'A repeat changed the registry');
    }

    # A site without the _modules table keeps the records of its config/modules.php, as before the change
    #[Test]
    public function aSiteWithoutTheTableKeepsItsRecords(): void
    {
        $run = $this->getRun()['plain'];
        $ship = $run['ship'];
        foreach (['forum', 'shop', 'config'] as $name) {
            $want = array_map('intval', array_intersect_key($ship[$name], array_flip(['active', 'view', 'menu', 'group', 'side', 'top'])));
            $this->assertSame($want, array_slice($run['mods'][$name], 0, 6), 'The record of '.$name.' changed');
        }
        $this->assertSame('', $run['rights']);
    }

    # A clean installation needs a server as new as the update does and no table of its prefix; a prefix that only starts like a taken one, or holds the
    # underscore LIKE would take for any character, is free; the update needs both the users and the admins table of its prefix
    #[Test]
    public function thePreflightRefusesAWrongBase(): void
    {
        $run = $this->getRun();
        $this->assertStringStartsWith('The database already holds tables of the prefix probe_', $run['fresh']['taken']);
        $this->assertSame(['', '', ''], [$run['fresh']['free'], $run['fresh']['near'], $run['fresh']['wild']]);
        $this->assertStringContainsString('older than 10.5.2', $run['fresh']['old']);
        $this->assertSame('', $run['update']['real']);
        $this->assertStringStartsWith('The tables free_users and free_admins are not both in the database', $run['update']['none']);
        $this->assertStringStartsWith('The tables probe_users and probe_admins are not both in the database', $run['update']['half']);
    }

    # The installer never prints the stored password into its form, checks the prefix and the panel name before it connects, and a clean installation
    # writes its marks only after both SQL files ran without a failed statement
    #[Test]
    public function theInstallerChecksBeforeItWrites(): void
    {
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/setup/index.php');
        $from = strpos($code, 'function config(): void {');
        $form = substr($code, $from, strpos($code, "\n}\n", $from) - $from);
        $this->assertStringContainsString('name="xpass" value=""', $form);
        $this->assertStringNotContainsString("['pass']", $form, 'The form reads the stored password');
        $save = strpos($code, 'function save(): void {');
        $conn = strpos($code, 'new Database(', $save);
        $head = substr($code, $save, $conn - $save);
        foreach (['setExit(_SETUPPREFIX)', 'setExit(_SETUPAFILE)'] as $want) $this->assertStringContainsString($want, $head);
        $fresh = strpos($code, '$stop = checkUpdateBase($db, $xprefix, true);', $conn);
        $this->assertNotFalse($fresh, 'A clean installation lost its preflight');
        $this->assertSame(0, substr_count(substr($code, $save, $fresh - $save), 'setConfigFile('), 'A file is written before the preflight of a clean installation');
        $new = strpos($code, "if (\$setup == 'new') {\n        \$title = _SAVE_NEW;");
        $mark = strpos($code, "setConfigFile('update.php', ['points' => '6.3.0'", $new);
        $this->assertStringContainsString("if (\$ddl !== '' && !str_contains(\$ddl, 'sl_red')) {", substr($code, $new, $mark - $new), 'The marks do not wait for the SQL files');
    }
}
