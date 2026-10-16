<?php

declare(strict_types=1);

namespace Tests\Unit;

use Backup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

require_once BASE_DIR.'/core/classes/backup.php';

# The portion mode of Backup, contract v1: a dump taken one request per portion and restored the same way, both without the core
final class BackupPartTest extends TestCase
{
    private const SITE = ['ab_admins', 'ab_child', 'ab_empty', 'ab_gen', 'ab_keyed', 'ab_multi', 'ab_myisam', 'ab_nokey'];
    private const ALIEN = ['ab_x_admins', 'ab_x_users', 'abc_admins', 'other_t'];
    private const LOAD = ['dump_probe.php', 'db.php', 'backup.php'];
    private static ?array $probe = null;

    # Run tests/Support/dump_probe.php once: it seeds a disposable schema and runs every portion of the dump and of both restores in a child process of its own
    private function getProbe(): array
    {
        if (self::$probe !== null) return self::$probe;
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_part_probe';
        $this->deleteWork($work);
        $script = dirname(__DIR__).'/Support/dump_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' cycle '.escapeshellarg($work).' 2>&1');
        $this->deleteWork($work);
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
        if (!empty($data['skip'])) $this->markTestSkipped('No database server: '.$data['skip']);
        $this->assertSame('', $data['error'], 'The probe cycle failed');
        return self::$probe = $data;
    }

    # Remove the work directory of the probe with everything in it
    private function deleteWork(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') continue;
            is_dir($dir.'/'.$file) ? $this->deleteWork($dir.'/'.$file) : unlink($dir.'/'.$file);
        }
        rmdir($dir);
    }

    # Call one private static method of the class under test
    private function getCall(string $name, array $args): mixed
    {
        return (new ReflectionMethod(Backup::class, $name))->invokeArgs(null, $args);
    }

    # Assert that two stamps describe the same tables: rows, count, definition and engine always, CHECKSUM TABLE where MariaDB computes it stably
    private function assertSameTables(array $want, array $have, string $where): void
    {
        foreach (self::SITE as $name) {
            $this->assertIsArray($have[$name] ?? null, $name.' is missing '.$where);
            foreach (['rows', 'count', 'ddl', 'engine'] as $key) $this->assertSame($want[$name][$key], $have[$name][$key], $name.' differs in '.$key.' '.$where);
            if (!$want[$name]['gen']) $this->assertSame($want[$name]['sum'], $have[$name]['sum'], 'CHECKSUM TABLE of '.$name.' differs '.$where);
        }
    }

    # The version of the contract is frozen in 8.0.0: a change is version 2, and version 1 stays readable forever
    #[Test]
    public function theContractIsVersionOne(): void
    {
        $this->assertSame(1, Backup::FORMAT);
    }

    # The fixed scope is the site prefix alone: a longer prefix with its own admins table, a prefix that only starts alike and a stranger stay out
    #[Test]
    public function theScopeIsTheSitePrefixAlone(): void
    {
        $data = $this->getProbe();
        $this->assertSame(self::SITE, $data['fix']['tabs']);
        $this->assertSame([], $data['fix']['miss'], 'The stand account was found lacking rights it has');
        $this->assertSame(self::SITE, $data['index']['tabs'], 'The index does not carry the fixed list');
    }

    # Every library call ran in a process that loaded the class and config/db.php and nothing else, which is what core/rescue.php can offer
    #[Test]
    public function everyPortionRunsWithoutTheCore(): void
    {
        $data = $this->getProbe();
        $want = json_encode(['files' => self::LOAD, 'core' => false]);
        foreach (['dump', 'restore', 'empty'] as $key) $this->assertSame([$want], $data[$key]['loads'], $key.' loaded more than the portion mode needs');
        foreach ([$data['fix'], $data['refuse'], $data['grant']['select']] as $one) $this->assertSame(['files' => self::LOAD, 'core' => false], $one['load']);
    }

    # A table larger than one part is split into several, every other table takes one part, and each part is a file listed in the index
    #[Test]
    public function aLargeTableSpansSeveralParts(): void
    {
        $index = $this->getProbe()['index'];
        $this->assertSame('gz', $index['pack']);
        foreach (['ab_keyed', 'ab_multi', 'ab_nokey'] as $name) $this->assertGreaterThan(1, $index['parts'][$name], $name.' did not span parts');
        foreach (['ab_admins', 'ab_child', 'ab_empty', 'ab_gen', 'ab_myisam'] as $name) $this->assertSame(1, $index['parts'][$name]);
        $this->assertCount(array_sum($index['parts']) + 1, $index['files'], 'A part is missing, or a temporary file was left');
        $this->assertContains('dump.json', $index['files']);
        $this->assertSame(16105, $index['rows']);
    }

    # The dump restored portion by portion into an empty database is the database it was taken from, MyISAM, a composite key, a keyless table and odd values included
    #[Test]
    public function aDumpRestoresIntoAnEmptyDatabase(): void
    {
        $data = $this->getProbe();
        $this->assertTrue($data['empty']['done'], $data['empty']['error']);
        $this->assertSameTables($data['source'], $data['copied'], 'in the empty database');
        $this->assertSame(count(self::SITE), (int)$data['strays'], 'The restore created a table the dump does not hold');
    }

    # Restored in place after a failed step, the site tables are back, the table the step created is gone and no stranger was touched
    #[Test]
    public function theRestoreUndoesAFailedStepAndNothingElse(): void
    {
        $data = $this->getProbe();
        $this->assertTrue($data['restore']['done'], $data['restore']['error']);
        $this->assertSameTables($data['source'], $data['inplace'], 'after the restore in place');
        $this->assertSame('missing', $data['inplace']['ab_new'], 'The table created after the list was fixed survived');
        $this->assertSame('MyISAM', $data['inplace']['ab_myisam']['engine']);
        $this->assertSame($data['alien'], $data['after'], 'The restore touched a table of another prefix');
    }

    # A part whose result never reached the caller is replayed from the head of its table, and the table comes back without a duplicate row
    #[Test]
    public function aLostResultResumesAtTheHeadOfItsTable(): void
    {
        $data = $this->getProbe();
        $this->assertNotSame('', $data['retry'], 'The probe never lost the result of a part inside a table');
        $this->assertSame($data['source']['ab_keyed']['count'], $data['inplace']['ab_keyed']['count']);
    }

    # A damaged part, an unknown format, a finished dump, a silent connection and a prefix outside its grammar are refused by name
    #[Test]
    public function everyBreachOfTheContractIsRefused(): void
    {
        $refuse = $this->getProbe()['refuse'];
        $this->assertStringContainsString('is missing or damaged', $refuse['damaged']);
        $this->assertStringContainsString('is damaged', $refuse['gap'], 'An index with a table that has no part was accepted, part 0 would drop it for good');
        $this->assertStringContainsString('does not know dump format 2', $refuse['format']);
        $this->assertStringContainsString('does not know this dump state', $refuse['dumpver']);
        $this->assertStringContainsString('does not know this restore state', $refuse['restver']);
        $this->assertStringContainsString('already holds a finished dump', $refuse['finished']);
        $this->assertStringContainsString('ERRMODE_EXCEPTION', $refuse['silent']);
        $this->assertStringContainsString('is not a table prefix', $refuse['prefix']);
    }

    # The preflight sees what an account lacks before the site closes, and a hosting grant on an escaped name pattern counts as a grant on the schema
    #[Test]
    public function thePreflightNamesTheMissingRights(): void
    {
        $grant = $this->getProbe()['grant'];
        $this->assertSame(['INSERT', 'CREATE', 'DROP'], $grant['select']['miss']);
        $this->assertSame([], $grant['pattern']['miss']);
    }

    # The probe leaves no schema and no account behind
    #[Test]
    public function theProbeCleansUpAfterItself(): void
    {
        $this->assertTrue($this->getProbe()['clean']);
    }

    # A grant names its database as a LIKE pattern: \_ is literal, _ and % are wildcards, and a denial on the schema removes a global grant
    #[Test]
    public function grantSchemasFollowTheLikeGrammar(): void
    {
        $this->assertTrue($this->getCall('checkGrantBase', ['user\_db', 'user_db']));
        $this->assertFalse($this->getCall('checkGrantBase', ['user\_db', 'userXdb']));
        $this->assertTrue($this->getCall('checkGrantBase', ['user_db', 'userXdb']));
        $this->assertTrue($this->getCall('checkGrantBase', ['user\_%', 'user_wp']));
        $this->assertFalse($this->getCall('checkGrantBase', ['user\_%', 'other_wp']));
        $mtrx = $this->getCall('getGrantMatrix', [['GRANT ALL PRIVILEGES ON *.* TO `p`@`%`', 'REVOKE INSERT ON `user_db`.* FROM `p`@`%`']]);
        $this->assertFalse($this->getCall('checkGrantPriv', [$mtrx, 'user_db', 'INSERT']));
        $this->assertTrue($this->getCall('checkGrantPriv', [$mtrx, 'user_db', 'SELECT']));
        $mtrx = $this->getCall('getGrantMatrix', [['GRANT SELECT ON `user\_db`.`user_news` TO `p`@`%`']]);
        $this->assertFalse($this->getCall('checkGrantPriv', [$mtrx, 'user_db', 'SELECT']), 'An object grant proved a schema-wide right');
    }

    # A dump state is opened only for a list of table names and a pack this server can write
    #[Test]
    public function aDumpStateNeedsAListAndAKnownPack(): void
    {
        $dir = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_part_state_'.bin2hex(random_bytes(4));
        $bad = [['ab', [], 'auto'], ['ab', ['a' => 'ab_users'], 'auto'], ['ab', ['ab_users', ''], 'auto'], ['ab', ['ab_users'], 'zip'], ['ab%', ['ab_users'], 'none']];
        foreach ($bad as [$prefix, $tabs, $pack]) {
            try {
                Backup::getDumpState($dir, $prefix, $tabs, $pack);
                $this->fail('A dump state was opened for '.json_encode([$prefix, $tabs, $pack]));
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        $state = Backup::getDumpState($dir, 'ab', ['ab_users'], 'none');
        rmdir($dir);
        $this->assertSame(['ver' => 1, 'dir' => $dir, 'prefix' => 'ab', 'pack' => 'none', 'tabs' => ['ab_users'], 'tab' => 0, 'skip' => 0, 'last' => null,
            'parts' => [], 'done' => false], $state);
    }
}
