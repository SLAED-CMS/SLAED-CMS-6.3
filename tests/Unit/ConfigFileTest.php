<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S03 of docs/node: the configuration protocol of docs/node/06-types.md. setConfigFile() keeps its string form
 * for independent sources and gains the Closure form for the shared node, fields, uploads and ratings; both run one
 * pipeline under the shared lock with a journal, a marker and an atomic publication of local.php. The behaviour runs
 * through tests/Support/config_probe.php against a scratch copy of the configuration, so nothing below config/ or
 * storage/ of the site is written; a writer that dies is a real child process and two writers are two processes.
 * The administrative entry and the three panel writers are proven off their sources.
 */
final class ConfigFileTest extends TestCase
{
    private static array $probe = [];
    private static array $files = [];

    # Run one probe scenario in a fresh process and memoize its report for every test in this class
    private function getProbe(string $mode): array
    {
        if (isset(self::$probe[$mode])) return self::$probe[$mode];
        $script = dirname(__DIR__).'/Support/config_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_config_'.$mode.'_'.getmypid();
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($mode).' '.escapeshellarg($work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe '.$mode.' did not return JSON: '.$out);
        $this->assertSame('', $data['error'], 'Probe '.$mode.' failed');
        $this->assertFalse($data['site'], 'The probe left a marker in the journal of the site itself');
        return self::$probe[$mode] = $data['data'];
    }

    # Read one repository file once per run
    private function getFile(string $path): string
    {
        if (isset(self::$files[$path])) return self::$files[$path];
        $full = dirname(__DIR__, 2).'/'.$path;
        $this->assertFileExists($full);
        return self::$files[$path] = (string)file_get_contents($full);
    }

    # Cut one top-level function out of a source file
    private function getBody(string $path, string $name): string
    {
        $code = $this->getFile($path);
        $from = strpos($code, 'function '.$name.'(');
        $this->assertNotFalse($from, $name.'() is gone from '.$path);
        return substr($code, $from, (int)strpos($code, "\n}\n", $from) - $from);
    }

    # The contract of docs/node/06-types.md is the signature: a string or a Closure first, a bool back, and no array form
    #[Test]
    public function theWriterHasTheContractSignature(): void
    {
        $code = $this->getFile('core/system.php');
        $this->assertStringContainsString('function setConfigFile(string|Closure $fp, array $arr = [], array $act = []): bool {', $code);
        $this->assertStringContainsString('function getConfig(bool $fresh = false): array {', $code);
        $this->assertStringContainsString('function setConfigRestore(string $force = \'\'): bool {', $code);
        $this->assertSame(1, substr_count($code, 'function (array $arr, int $dep = 0)'), 'The exporter of a configuration file exists more than once again');
        $this->assertStringNotContainsString("unlink(CONFIG_DIR.'/local.php')", $code, 'The working snapshot is removed before its replacement is published');
        $this->assertStringContainsString('FileManager::getPathLock(CONFIG_DIR)', $this->getBody('core/system.php', 'setConfigFile'), 'The writer takes something other than the shared lock');
        $this->assertStringContainsString('FileManager::getPathLock(CONFIG_DIR)', $this->getBody('core/system.php', 'getConfig'), 'The rebuild runs outside the shared lock');
    }

    # The string form stores what it always stored: sorted keys, every scalar a string, LF only, and the snapshot published in the same call
    #[Test]
    public function theStringFormKeepsItsStoredShape(): void
    {
        $data = $this->getProbe('string');
        $want = ['whois' => ['alpha' => '1', 'deep' => ['num' => '7', 'flag' => '0'], 'text' => "one\ntwo\nthree", 'zeta' => '2']];
        $this->assertTrue($data['done'], 'A plain save of an independent source was refused');
        $this->assertSame($want, $data['data'], 'The stored shape of the string form changed');
        $this->assertFalse($data['crlf'], 'A carriage return reached a stored source');
        $this->assertSame(['whois.php'], $data['others'], 'A save of one source touched another');
        $this->assertSame($want['whois'], $data['local'], 'local.php was not published with the save');
        $this->assertTrue($data['same'], 'An unchanged save was refused');
        $this->assertTrue($data['bytes'], 'An unchanged save did not reproduce the file byte for byte');
        $this->assertSame(['whois' => ['kept' => 'yes', 'zeta' => '3']], $data['merge'], 'The third argument no longer keeps what the form did not post');
        $this->assertSame(['marker' => false, 'dirs' => 0], $data['trace'], 'A finished operation left its journal behind');
        $this->assertSame([], $data['temps'], 'A temporary file stayed beside the sources');
    }

    # The reserved files, the four shared sources and anything that is not a plain lowercase file name are refused by the string form
    #[Test]
    public function theStringFormRefusesSharedAndForeignNames(): void
    {
        foreach ($this->getProbe('string')['deny'] as $name => $done) $this->assertFalse($done, $name.' was accepted by the string form');
    }

    # The Closure form hands over the four fresh areas without their wrappers and stores native types as they are, replacing only the source that changed
    #[Test]
    public function theClosureFormKeepsNativeTypes(): void
    {
        $data = $this->getProbe('closure');
        $this->assertTrue($data['done'], 'A valid package was refused');
        $this->assertSame(['node', 'fields', 'uploads', 'ratings'], $data['seen']['keys'], 'The base is not the four shared areas');
        $this->assertSame([], $data['seen']['node'], 'A missing node.php is not an empty area');
        $this->assertFalse($data['seen']['wrapped'], 'The base still carries the root wrapper of its file');
        $this->assertGreaterThan(0, $data['seen']['rules'], 'The uploads area arrived empty');
        $this->assertTrue($data['kept'], 'string, int, bool, null or a nested array changed on the way into the source');
        $this->assertSame(['fields.php'], $data['changed'], 'An area that did not change was rewritten, or node.php was created empty');
        $this->assertTrue($data['local'], 'local.php does not carry the package');
        $this->assertSame(['marker' => false, 'dirs' => 0], $data['trace'], 'The scenario left a journal behind');
    }

    # float, object, closure, resource, a missing or a foreign area and the mixed argument form are refused before anything is written
    #[Test]
    public function theClosureFormRefusesWhatItCannotStore(): void
    {
        $data = $this->getProbe('closure');
        foreach ($data['deny'] as $name => $done) $this->assertFalse($done, 'The package case '.$name.' was accepted');
        $this->assertSame([true, [true, false], 'first'], $data['twice'], 'The save of one operation could be called twice');
        $this->assertFalse($data['nested'], 'A writer could call setConfigFile() again from inside its closure');
    }

    # An aborted operation, a thrown one and an aborted one with a proof all return to the old snapshot in the same call, including a file the operation created
    #[Test]
    public function anAbortedOperationReturnsToTheOldSnapshot(): void
    {
        $data = $this->getProbe('closure');
        $this->assertSame([true, ['node' => ['types' => ['probe' => ['version' => 1]]]]], $data['born'], 'node.php is not born with its first area');
        $this->assertSame([false, true], $data['back'], 'An aborted creation left node.php behind');
        $this->assertSame([false, false], $data['throw'], 'An exception after the save left the new source in place');
        $this->assertSame([false, false], $data['abort'], 'An aborted operation with a proof left the new source in place');
    }

    # A writer that died after moving the sources leaves a marker: the old snapshot keeps being served, every save is refused, and the restore returns the old sources
    #[Test]
    public function aDeadWriterIsRestoredToTheOldSnapshot(): void
    {
        $data = $this->getProbe('crash')['none'];
        $this->assertTrue($data['marker'], 'The marker was not on disk before the sources moved');
        $this->assertSame(['prepared', 'old', ''], [$data['phase'], $data['verdict'], $data['why']]);
        $this->assertSame(['node.php' => 'new', 'fields.php' => 'new'], $data['states']);
        $this->assertTrue($data['served'], 'local.php was rebuilt from half finished sources');
        $this->assertSame([false, false], $data['refused'], 'A save was accepted over an unfinished operation');
        $this->assertSame([null, false, true, false], $data['memory'], 'With the marker and no local.php the old snapshot is not assembled in memory, or local.php was written');
        $this->assertSame([true, true], [$data['restore'], $data['again']], 'The restore failed or is not repeatable');
        $this->assertSame(['crash' => null, 'node' => false, 'local' => null, 'cache' => false, 'trace' => ['marker' => false, 'dirs' => 0], 'save' => true], $data['after']);
    }

    # A writer that died between two sources leaves one on each side, and the restore still returns both to the old snapshot
    #[Test]
    public function aHalfReplacedOperationIsRestoredToTheOldSnapshot(): void
    {
        $data = $this->getProbe('crash')['partial'];
        $this->assertSame(['node.php' => 'new', 'fields.php' => 'old'], $data['states']);
        $this->assertSame(['old', ''], [$data['verdict'], $data['why']]);
        $this->assertSame([true, true], [$data['restore'], $data['again']]);
        $this->assertSame(['crash' => null, 'node' => false, 'local' => null, 'cache' => false, 'trace' => ['marker' => false, 'dirs' => 0], 'save' => true], $data['after']);
    }

    # The durable committed mark turns the same crash into a completion of the new snapshot
    #[Test]
    public function aCommittedOperationIsFinishedToTheNewSnapshot(): void
    {
        $data = $this->getProbe('crash')['committed'];
        $this->assertSame(['committed', 'new', ''], [$data['phase'], $data['verdict'], $data['why']]);
        $this->assertSame(['new', true, true, false], $data['memory'], 'With the marker and no local.php the new snapshot is not assembled in memory');
        $this->assertTrue($data['restore']);
        $this->assertSame(['crash' => 'new', 'node' => true, 'local' => 'new', 'cache' => false, 'trace' => ['marker' => false, 'dirs' => 0], 'save' => true], $data['after']);
    }

    # A forged snapshot, a source that is neither side, a lost journal and a proof only the database can check are never resolved by a guess
    #[Test]
    public function anUnprovableStateIsNeverGuessed(): void
    {
        $all = $this->getProbe('crash');
        foreach (['backup', 'source', 'journal'] as $case) {
            $data = $all[$case];
            $this->assertSame(['', $case], [$data['verdict'], $data['why']], 'The case '.$case.' got a verdict');
            $this->assertSame([false, false], [$data['restore'], $data['again']], 'The case '.$case.' was restored');
            $this->assertTrue($data['after']['trace']['marker'], 'The case '.$case.' lost its marker');
            $this->assertTrue($data['after']['cache'], 'A refused restore cleared the HTML cache');
            $this->assertSame([false, false], $data['refused'], 'A save was accepted in the case '.$case);
            $this->assertFalse($data['memory'][3], 'local.php was published in the case '.$case);
        }
        $want = [false, 'proof', ['probe'], ['name' => 'probe', 'id' => 7, 'old' => 1, 'new' => 2, 'kind' => 'update'], false, true];
        $this->assertSame($want, $all['proof'], 'An uncertain operation with a proof was resolved without the database');
    }

    # A missing local.php without a marker stays the ordinary path of a fresh install, an update and a manual edit
    #[Test]
    public function aMissingSnapshotWithoutAMarkerIsRebuilt(): void
    {
        $this->assertSame([true, true, true], $this->getProbe('crash')['rebuild']);
    }

    # Two writers of two areas in two processes: every operation succeeds and neither loses a key of its own or an area of the other
    #[Test]
    public function twoWritersOfTwoAreasLoseNothing(): void
    {
        $data = $this->getProbe('race');
        $this->assertSame([array_fill(0, 6, true), array_fill(0, 6, true)], $data['runs'], 'A writer was refused while another one worked');
        $this->assertSame([6, 6], [$data['fields'], $data['ratings']], 'A key of one writer was lost');
        $this->assertSame([6, 6], $data['local'], 'local.php does not carry the last state of both areas');
        $this->assertTrue($data['kept'], 'An area lost the keys it had before');
        $this->assertSame(['marker' => false, 'dirs' => 0], $data['trace']);
    }

    # The three shared screens write through the Closure form, merge into the fresh area and report a refusal instead of a success
    #[Test]
    public function theSharedWritersUseTheClosureForm(): void
    {
        foreach (['fields' => 'save', 'uploads' => 'configsave', 'ratings' => 'save'] as $name => $func) {
            $body = $this->getBody('admin/modules/'.$name.'.php', $func);
            $this->assertStringContainsString('setConfigFile(static function (array $base, Closure $save)', $body, $name.' still posts a prepared file');
            $this->assertStringContainsString("\$base['".$name."'] = array_replace(\$base['".$name."'], ", $body, $name.' replaces its whole area instead of its own keys');
            $this->assertStringContainsString('_CONFIG_PENDING', $body, $name.' reports a refused save as a success');
            $this->assertStringNotContainsString("setConfigFile('".$name.".php'", $body);
        }
    }

    # The restore entry belongs to the main administrator alone: the module guard, the scoped POST token, and a screen that reads nothing but the journal
    #[Test]
    public function theRestoreEntryIsGuarded(): void
    {
        $code = $this->getFile('admin/modules/config.php');
        $this->assertStringContainsString("if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');", $code, 'The module is open to more than the main administrator');
        $this->assertStringContainsString("case 'restore': restore(); break;", $code, 'The restore entry is not routed');
        $body = $this->getBody('admin/modules/config.php', 'restore');
        $this->assertStringContainsString("checkAdminPost('config')", $body, 'The restore runs without the scoped POST token');
        $this->assertLessThan(strpos($body, 'setConfigRestore()'), strpos($body, "checkAdminPost('config')"), 'The restore runs before its token is checked');
        $this->assertStringContainsString("getTplPostButton(['name' => 'config', 'op' => 'restore']", $body, 'The restore is not sent as a POST form');
        $this->assertStringContainsString('getConfigJournal()', $body, 'The screen shows no diagnostics of the journal');
        $this->assertStringNotContainsString('$conf', $body, 'The diagnostic screen depends on the configuration it repairs');
        foreach (['de', 'en', 'fr', 'pl', 'ru', 'uk'] as $loc) {
            $this->assertSame(8, substr_count($this->getFile('admin/lang/'.$loc.'.php'), "define('_CONFIG_"), 'The locale '.$loc.' misses a constant of the restore screen');
        }
    }
}
