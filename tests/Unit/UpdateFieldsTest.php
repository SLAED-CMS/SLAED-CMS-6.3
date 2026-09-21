<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S06 of docs/node: the fields unit of the 6.3 data update in setup/index.php, whose contract is the one-off
 * conversion of Field in docs/node/12-migration.md. tests/Support/update_probe.php lifts the shipped functions out of
 * the installer by name and drives them in an isolated CLI process against a disposable schema and a scratch site, so
 * the manifest, the snapshots, the mark, the definitions and the value rows of the stand are never touched.
 */
final class UpdateFieldsTest extends TestCase
{
    private const USERS = [
        2 => '{"field1":"option2","field2":"Änn \"Q\"","field4":"2001-02-03","field5":"2020-05-06 07:08:00","field6":"one"}',
        3 => '{"field6":"0"}',
        4 => '',
    ];

    private const FORUM = [5 => '{"field1":"option1","field3":"option2"}', 7 => '{"field1":"option2","field3":"option1"}', 9 => ''];

    private const ORDER = [1 => '{"field1":"Z1","field3":"Bob"}'];

    private const COUNT = ['account' => 1202, 'forum' => 3, 'order' => 1];

    private static array $probe = [];

    # Run the probe once and memoize its report for every test in this class
    private function getRun(string $name): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/update_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_update_probe_fields';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' fields 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            if (!empty($data['error'])) $this->markTestSkipped('Probe: '.$data['error']);
            $this->assertTrue($data['clean'], 'The probe left its schema on the server');
            self::$probe = $data;
        }
        return self::$probe['runs'][$name];
    }

    # What a finished unit leaves behind, whichever way it got there: every row as canonical JSON, a manifest at its end that seals snapshots and definitions
    private function checkFinished(array $run, string $text): void
    {
        $this->assertTrue($run['done'], $text.': '.$run['text']);
        $this->assertSame(['verified', self::COUNT, self::COUNT], [$run['state'], $run['cursor'], $run['count']], $text.': the manifest did not reach its end');
        $this->assertTrue($run['files'] && $run['sealed'], $text.': the manifest does not seal its snapshots, the stored rows and the published definitions');
        $this->assertSame([1 => 1200], $run['bulk'], $text.': a row of the three batches was not carried over');
        $this->assertSame([self::FORUM, self::ORDER], [$run['forum'], $run['order']], $text.': the forum or the order rows differ');
    }

    # The clean path names every position by its original number, maps the four slots, keeps a text zero, drops placeholders and empty texts, and picks the one layout that fits
    #[Test]
    public function theCleanRunCarriesEveryRowOver(): void
    {
        $run = $this->getRun('clean')['first'];
        $this->checkFinished($run, 'first run');
        $this->assertSame(self::USERS, $run['users'], 'A value row was not carried into its canonical JSON');
        $this->assertEquals(['points' => '6.3.0', 'ratings' => '6.3.0', 'fields' => '6.3.0'], $run['mark'], 'The mark of the unit is missing or an earlier mark was lost');
        $rules = $run['rules'];
        $this->assertSame(['field1', 'field2', 'field4', 'field5', 'field6'], array_keys($rules['account']), 'A switched off position got a key or a gap was closed');
        $this->assertSame(['select', 'text', 'date', 'datetime', 'textarea'], array_column($rules['account'], 'type'));
        $this->assertSame([true, false, false, false, false], array_column($rules['account'], 'req'), 'Only an exact 1 in the fourth slot makes a field required');
        $this->assertSame(['', 'John', '', '', ''], array_column($rules['account'], 'default'), 'The second slot of a text is its default and a 0 is none');
        $this->assertSame([10, 20, 40, 50, 60], array_column($rules['account'], 'sort'));
        $this->assertSame(['option1' => 'A', 'option2' => 'B', 'option3' => 'C'], array_map(fn($v) => $v['title'], $rules['account']['field1']['options']['items']));
        $this->assertSame(['field1', 'field3'], array_keys($rules['forum']));
        $this->assertSame(['field1', 'field3'], array_keys($rules['order']));
        foreach ($rules as $set) {
            foreach ($set as $rule) $this->assertSame([true, true, true], [is_bool($rule['req']), is_bool($rule['active']), is_int($rule['sort'])], 'The published definitions lost their native types');
        }
    }

    # A value saved after the site opened is left alone, a lost mark is written again, and no file of the backup is rewritten
    #[Test]
    public function aRepeatChangesNothing(): void
    {
        $runs = $this->getRun('clean');
        $this->checkFinished($runs['again'], 'repeat');
        $this->assertSame('{"field2":"later"}', $runs['again']['users'][4], 'The repeat rewrote a value saved by the running system');
        $this->checkFinished($runs['nomark'], 'verified without a mark');
        $this->assertSame(['fields' => '6.3.0'], $runs['nomark']['mark']);
        $this->assertTrue($runs['same'], 'A repeat rewrote the manifest or a snapshot');
    }

    # Named definitions over empty columns have nothing to carry over: the unit seals an empty manifest, leaves the file as it is and writes its mark
    #[Test]
    public function namedDefinitionsWithoutRowsAreAccepted(): void
    {
        $run = $this->getRun('clean')['named'];
        $this->assertTrue($run['done'], $run['text']);
        $this->assertSame(['verified', ['account' => 0, 'forum' => 0, 'order' => 0]], [$run['state'], $run['count']]);
        $this->assertSame($this->getRun('clean')['first']['rules'], $run['rules'], 'The named definitions were rewritten');
        $this->assertSame('6.3.0', $run['mark']['fields'] ?? null);
    }

    # The unit continues from the cursor after none, one and two committed batches; rows before it are already targets and rows behind it still sources
    #[Test]
    public function theUnitResumesAfterEveryBatch(): void
    {
        foreach ($this->getRun('resume') as $name => $run) {
            $this->checkFinished($run, $name);
            $this->assertSame(self::USERS, $run['users'], $name);
            $this->assertSame($this->getRun('clean')['first']['rules'], $run['rules'], $name.': the definitions were not published after the resume');
        }
    }

    # A mark without a manifest, named definitions over positional rows, a forged snapshot and a stored row that is neither source nor target each stop the unit
    #[Test]
    public function theStopsHold(): void
    {
        $stop = $this->getRun('stop');
        foreach (['mark' => 'the mark is set and no manifest exists', 'named' => 'already in the 6.3 format', 'forged' => 'order.json does not match its manifest', 'foreign' => 'neither its source nor its target'] as $name => $text) {
            $this->assertFalse($stop[$name]['done'], $name);
            $this->assertStringContainsString($text, $stop[$name]['text'], $name);
        }
        $this->assertSame([null, false], [$stop['named']['state'], $stop['named']['dir']], 'A refused start left a backup behind');
        $this->assertSame('Y|L', $stop['named']['forum'][7], 'A refused start changed a row');
        $this->assertSame(['applying', null], [$stop['foreign']['state'], $stop['foreign']['mark']], 'A refused batch reached the end or left a mark');
        $this->assertSame('{"field1":"foreign"}', $stop['foreign']['forum'][7], 'A foreign row was overwritten');
        $this->assertIsString($stop['foreign']['rules']['account'], 'The definitions were published over a refused batch');
    }

    # The preflight names every row it will not guess in one report - an unknown option, a localized date, data without a definition, two fitting layouts, a line break - and writes nothing
    #[Test]
    public function thePreflightNamesEveryBrokenRowAndWritesNothing(): void
    {
        $run = $this->getRun('stop')['rows'];
        $this->assertFalse($run['done']);
        $want = [
            'probe_users 2 (value 1 is no option of field1)', 'probe_users 3 (field4 is refused by the shared check: format)', 'probe_users 4 (value 7 holds data and has no definition)',
            'probe_forum 5 (value 2 holds data and has no definition)', 'probe_order 1 (the full and the short layout both fit and differ)',
            'probe_order 2 (field1 is refused by the shared check: format)',
        ];
        foreach ($want as $text) $this->assertStringContainsString($text, $run['text']);
        $this->assertStringContainsString('nothing was written (6)', $run['text'], 'The report counts more or fewer rows than are broken');
        $this->assertStringNotContainsString('extra', $run['text'], 'The report carries stored data');
        $this->assertSame([null, false, 'D|x', 'Y'], [$run['state'], $run['dir'], $run['users'][2], $run['forum'][7]], 'The preflight wrote something');
        $this->assertIsString($run['rules']['account'], 'The preflight published definitions');
        $this->assertArrayNotHasKey('fields', $run['mark']);
    }

    # Broken definitions are named by area and position: a repeated option caption, an unknown type, a fifth slot and a default that is no canonical date
    #[Test]
    public function thePreflightNamesEveryBrokenDefinition(): void
    {
        $run = $this->getRun('stop')['defs'];
        $this->assertFalse($run['done']);
        foreach (['account position 1 (an option caption repeats)', 'account position 2 (four slots', 'account position 3 (four slots', 'forum field1.default'] as $text) {
            $this->assertStringContainsString($text, $run['text']);
        }
        $this->assertSame([null, false, 'a||b'], [$run['state'], $run['dir'], $run['order'][1]]);
    }

    # The three columns hold one mebibyte of JSON in a fresh schema, the update widens them and never narrows them again, and the branch calls the unit after the ratings
    #[Test]
    public function theSchemaAndTheBranchCarryTheUnit(): void
    {
        $root = dirname(__DIR__, 2);
        $fresh = (string)file_get_contents($root.'/setup/sql/table.sql');
        foreach (['users' => 'field', 'forum' => 'field', 'order' => 'info'] as $tab => $col) {
            $this->assertSame(1, preg_match('/CREATE TABLE `\{prefix\}_'.$tab.'` \((?:(?!CREATE TABLE).)*?`'.$col.'` MEDIUMTEXT NOT NULL,/s', $fresh), $tab.'.'.$col.' is not MEDIUMTEXT in table.sql');
        }
        $sql = (string)file_get_contents($root.'/setup/sql/table_update6_3.sql');
        $this->assertSame(0, preg_match('/`(?:field|info)`\s+TEXT NOT NULL/', $sql), 'The update narrows a column of extra field values back to TEXT');
        $this->assertSame(4, preg_match_all('/`(?:field|info)`\s+MEDIUMTEXT NOT NULL/', $sql), 'The update does not widen forum.field, order.info and users.field');
        $code = (string)file_get_contents($root.'/setup/index.php');
        $this->assertSame(1, preg_match('/setUpdatePoints\(\$db, \$xprefix\);\s+\$bodytext \.= setUpdateRatings\(\$db, \$xprefix\);\s+\$bodytext \.= setUpdateFields\(\$db, \$xprefix\);/', $code), 'The order of the units changed');
    }
}
