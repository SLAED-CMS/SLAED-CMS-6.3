<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The 6.3 update: the module registry, the removal of unregistered Node types and the preflight of setup/index.php
final class UpdateSetupTest extends TestCase
{
    private static array $probe = [];

    # The registry keeps the switches of the 6.2 _modules table over config/modules.php on the first run and leaves the switches of the owner alone after it
    # The types of config/node.php leave their four areas unless the type table registers them
    # The preflight refuses a clean installation over the tables of its prefix or on an old server, and an update without users and admins tables
    # It also refuses an update whose admins and newsletter tables are not InnoDB
    # The probe tests/Support/update_probe.php lifts the shipped functions out of the installer by name onto a scratch site and a disposable schema, never the stand
    # Run the probe once in its setup mode and memoize the report for every test in this class
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/update_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_update_setup';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' setup 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left its schema on the server');
            self::$probe = $data['runs']['clean'];
        }
        return self::$probe;
    }

    # A module switched off, shown to a group and placed in blocks by the 6.2 site keeps that after the update, although the shipped record says otherwise
    # A module without a row keeps the shipped record, a module without a record gets the default, and node gets the record of a clean installation
    # The record of a module that left the tree is dropped and named, the numbered rights become names, and a repeat changes nothing
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

    # A clean installation needs a server as new as the update does and no table of its prefix; the update needs both the users and the admins table of its prefix
    # A prefix that only starts like a taken one, or holds the underscore LIKE would take for any character, is free
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
        $want = 'These tables are not InnoDB, convert them and start the update again: ALTER TABLE `probe_admins` ENGINE=InnoDB;'
            .' ALTER TABLE `probe_newsletter` ENGINE=InnoDB;';
        $this->assertSame($want, $run['update']['engine']);
    }

    # A repeat after the mark modules reads the _modules table of 6.2 no more: forum, which the owner switched on between the runs, stays on, the rights stay names
    # The answer says the switches were carried by the first run
    #[Test]
    public function aRepeatKeepsTheSwitchesOfTheOwner(): void
    {
        $run = $this->getRun();
        $this->assertSame(['active' => 1] + $run['site']['mods']['forum'], $run['owned']['mods']['forum']);
        $this->assertSame(array_diff_key($run['site']['mods'], ['forum' => 0]), array_diff_key($run['owned']['mods'], ['forum' => 0]));
        $this->assertSame('forum,news,shop', $run['owned']['rights']);
        $this->assertStringContainsString('switches of the 6.2 table probe_modules were carried by the first run', $run['owned']['text']);
    }

    # The update takes every type of config/node.php its type table does not register out of node.types, fields.node, uploads and ratings and names them
    # A registered type keeps its areas, every other area stays, and a repeat has nothing left to take
    #[Test]
    public function theUpdateDropsUnregisteredTypes(): void
    {
        $run = $this->getRun()['types'];
        $this->assertSame(['content', 'news'], $run['gone']);
        $this->assertSame([['docs'], ['account', 'node'], ['docs'], ['all', 'docs'], ['account', 'node.docs']],
            [$run['node'], $run['fields'], $run['node.fields'], $run['uploads'], $run['ratings']]);
        $this->assertSame([], $run['again']);
    }

    # A registry step that cannot read the admins table answers a red row naming it and leaves config/modules.php alone
    # The update then stops before the schema, and the next run still counts as the first
    # A type step that cannot write uploads.php answers false and keeps node.php, so a repeat finds the types again
    #[Test]
    public function aRegistryOrTypeFaultStopsTheUpdate(): void
    {
        $run = $this->getRun();
        $this->assertTrue($run['fault']['red']);
        $this->assertStringContainsString('module registry: probe_admins could not be read or written, the update stops before the schema', $run['fault']['text']);
        $this->assertTrue($run['fault']['same'], 'A failed registry step still wrote config/modules.php');
        $this->assertSame([false, ['content', 'docs', 'news'], ['content', 'news']], $run['types']['locked']);
    }

    # A read-only config/ratings.php is a red row: the manifest stays at applying and no mark is written
    # Once the file is writable again the repeat publishes the rules, seals the manifest and writes the mark
    #[Test]
    public function aReadOnlyRulesFileHoldsTheMark(): void
    {
        $run = $this->getRun()['locked'];
        $this->assertFalse($run['first']['done']);
        $this->assertStringContainsString('ratings: config/ratings.php could not be written, the rules are not published', $run['first']['text']);
        $this->assertSame('applying', $run['first']['state']);
        $this->assertArrayNotHasKey('ratings', $run['first']['mark'] ?? []);
        $this->assertTrue($run['again']['done'], $run['again']['text']);
        $this->assertSame(['verified', true, '6.3.0'], [$run['again']['state'], $run['again']['sealed'], $run['again']['mark']['ratings'] ?? null]);
        $this->assertNotSame($run['first']['rules'], $run['again']['rules'], 'The repeat did not publish the rules');
    }

    # The installer never prints the stored password into its form and asks an installed site for the code of its key before the form and before any write
    # It checks every code through the one counting check under the lock of the key and offers a random panel name instead of admin
    # It runs the schema file without the zero date modes of MySQL 8 and gives the session its mode back
    # It checks the prefix, the panel name, the writable configuration and a pending configuration journal before it connects, and never changes permissions
    # It writes the mark modules only while no row failed, and a clean installation writes its marks only after both SQL files ran without a failed statement
    #[Test]
    public function theInstallerChecksBeforeItWrites(): void
    {
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/setup/index.php');
        $this->assertStringNotContainsString('chmod(', $code, 'The installer changes permissions');
        $from = strpos($code, 'function config(): void {');
        $form = substr($code, $from, strpos($code, "\n}\n", $from) - $from);
        $this->assertStringContainsString('name="xpass" value=""', $form);
        $this->assertStringNotContainsString("['pass']", $form, 'The form reads the stored password');
        $pick = "\$xafile = (\$spanel !== 'admin') ? \$spanel : strtolower(getRandomString('10'));";
        $this->assertStringContainsString($pick, $form, 'The form offers the guessable name admin');
        $sql = (string)file_get_contents(dirname(__DIR__, 2).'/setup/sql/table_update6_3.sql');
        $mode = "SET SESSION sql_mode = TRIM(BOTH ',' FROM REPLACE(REPLACE(REPLACE(REPLACE(@@SESSION.sql_mode, 'NO_ZERO_IN_DATE', ''), 'NO_ZERO_DATE', '')";
        $text = 'The schema file does not leave the zero date modes of MySQL 8 before its first ALTER';
        $this->assertLessThan(strpos($sql, 'CREATE PROCEDURE rencol'), strpos($sql, $mode), $text);
        $this->assertStringEndsWith("SET SESSION sql_mode = @SLAED_MODE;\n", $sql, 'The schema file does not give the session its mode back');
        $gate = strpos($form, "if (\$scode !== '' && (\$xcode === '' || !checkSetupCode(\$xcode))) {");
        $this->assertNotFalse($gate, 'The form lost the check of the code');
        $this->assertLessThan(strpos($form, 'name="xhost"'), $gate, 'The form shows the connection data before the code');
        $save = strpos($code, 'function save(): void {');
        $conn = strpos($code, 'new Database(', $save);
        $head = substr($code, $save, $conn - $save);
        $this->assertSame(1, substr_count($code, 'password_verify('), 'A code is checked outside the counting check');
        $this->assertStringContainsString("if (\$scode !== '' && !checkSetupCode(\$xcode)) setExit(_SETUPCODE);", $head);
        $want = ['setExit(_SETUPCODE)', 'setExit(_SETUPTYPE)', 'setExit(_SETUPPREFIX)', 'setExit(_SETUPAFILE)', 'checkWritableConfig($name)', 'setExit(_SETUPJOUR)'];
        foreach ($want as $one) $this->assertStringContainsString($one, $head);
        $this->assertStringContainsString("if (\$first && !str_contains(\$bodytext, 'sl_red') && !setConfigFile('update.php', ['modules' => '6.3.0']", $code);
        $fresh = strpos($code, '$stop = checkUpdateBase($db, $xprefix, true);', $conn);
        $this->assertNotFalse($fresh, 'A clean installation lost its preflight');
        $this->assertSame(0, substr_count(substr($code, $save, $fresh - $save), 'setConfigFile('), 'A file is written before the preflight of a clean installation');
        $new = strpos($code, "if (\$setup == 'new') {\n        \$title = _SAVE_NEW;");
        $mark = strpos($code, "setConfigFile('update.php', ['points' => '6.3.0'", $new);
        $this->assertStringContainsString("if (\$ddl !== '' && !str_contains(\$ddl, 'sl_red')) {", substr($code, $new, $mark - $new), 'The marks do not wait for the SQL files');
    }
}
