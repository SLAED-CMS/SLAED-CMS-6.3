<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage 2b of docs/FILE-MANAGER-CONCEPT-2026.md: the destination lock and the managed name format left the upload
 * service and belong to FileManager now. Half of the stage is provable off the sources, because its whole point is
 * that neither the protocol nor the pattern survives anywhere else. The serialization itself runs through
 * tests/Support/upload_probe.php: the parent takes the lock of one destination through the file layer while a child
 * process publishes into that same destination through Upload, so the two writers are shown to stand in one queue
 * behind one lock file. Nothing below the site is read or written; every scenario lives in its own scratch tree.
 */
final class FileManagerLockTest extends TestCase
{
    private static array $probe = [];
    private static array $files = [];

    # Run one probe scenario in a fresh process and memoize its report for every test in this class
    private function getProbe(string $mode, string $file = 'upload_probe.php'): array
    {
        if (isset(self::$probe[$mode])) return self::$probe[$mode];
        $script = dirname(__DIR__).'/Support/'.$file;
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_upload_lock_'.$mode;
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($mode).' '.escapeshellarg($work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe '.$mode.' did not return JSON: '.$out);
        if (!empty($data['error'])) $this->markTestSkipped('Probe '.$mode.': '.$data['error']);
        return self::$probe[$mode] = $data;
    }

    # Read one repository file once per run
    private function getFile(string $path): string
    {
        if (isset(self::$files[$path])) return self::$files[$path];
        $full = dirname(__DIR__, 2).'/'.$path;
        $this->assertFileExists($full);
        return self::$files[$path] = (string)file_get_contents($full);
    }

    # The lock protocol exists once: the file layer takes and releases it, and the upload service carries no second implementation of the same thing
    #[Test]
    public function theLockProtocolLivesInTheFileLayerAlone(): void
    {
        $file = $this->getFile('core/classes/filemanager.php');
        $upl = $this->getFile('core/classes/upload.php');
        $this->assertStringContainsString('public static function getPathLock(string $dir): mixed {', $file, 'The file layer has no public lock');
        $this->assertStringContainsString('public static function deletePathLock(mixed $lock): void {', $file, 'The file layer has no public release');
        $this->assertStringContainsString('flock($lock, LOCK_EX)', $file, 'The lock of the file layer is not an exclusive one');
        $this->assertSame(0, substr_count($upl, 'flock('), 'The upload service still locks on its own, so its writers and the file layer stand in two queues');
        $this->assertSame(0, substr_count($upl, 'lockdir'), 'The upload service still names a lock directory of its own');
        $this->assertStringContainsString('FileManager::deletePathLock($lock)', $upl, 'The upload service does not release the shared lock');
    }

    # The key of a lock is the canonical directory, because the upload service draws a free name below it and the file it is about to write does not exist yet
    # Canonical means resolved and, on Windows, in one letter case, so a junction or a second spelling of one directory cannot open a second lock file
    #[Test]
    public function theKeyOfALockIsTheDirectoryItGuards(): void
    {
        $file = $this->getFile('core/classes/filemanager.php');
        $this->assertStringContainsString('$key = self::getLockKey($dir);', $file, 'The lock key is no longer the canonical directory path');
        $this->assertStringContainsString('$real = realpath($path);', $file, 'The lock key no longer resolves the directory it names');
        $this->assertStringContainsString("return (DIRECTORY_SEPARATOR === '\\\\') ? strtolower(\$key) : \$key;", $file, 'A Windows key keeps the letter case of the request');
        $this->assertStringContainsString("substr(sha1(\$key), 0, 16).'.lock'", $file, 'The lock file is no longer named by its key');
        $note = 'The upload service locks something other than its destination directory';
        $this->assertStringContainsString('FileManager::getPathLock($canon)', $this->getFile('core/classes/upload.php'), $note);
    }

    # The dependency runs one way: the upload service brings the file layer with it, and the file layer never names the upload service at all
    #[Test]
    public function theDependencyRunsOneWayOnly(): void
    {
        $upl = $this->getFile('core/classes/upload.php');
        $note = 'The upload service does not require the file layer, so a route that loads it directly fatals on the first lock';
        $this->assertStringContainsString("if (!class_exists('FileManager')) require_once __DIR__.'/filemanager.php';", $upl, $note);
        $file = $this->getFile('core/classes/filemanager.php');
        $this->assertStringNotContainsString('Upload::', $file, 'The file layer calls the upload service, so the two classes now depend on each other');
        $this->assertStringNotContainsString('new Upload(', $file, 'The file layer builds the upload service');
        $this->assertStringNotContainsString('getUploadService(', $file, 'The file layer reaches for the upload service');
    }

    # The managed name format exists once: both former readers ask the file layer, and neither keeps a pattern or a salt length of its own
    #[Test]
    public function theManagedNameIsReadInOnePlace(): void
    {
        $upl = $this->getFile('core/classes/upload.php');
        $sys = $this->getFile('core/system.php');
        foreach (['core/classes/upload.php' => $upl, 'core/system.php' => $sys] as $name => $code) {
            $this->assertStringNotContainsString('[a-zA-Z0-9_]+-', $code, $name.' still takes a stored name apart with a pattern of its own');
        }
        $this->assertStringNotContainsString('const SALTLEN', $upl, 'The upload service still declares the salt length instead of reading it');
        $this->assertStringContainsString('getRandomString(FileManager::SALTLEN)', $upl, 'The stored name is no longer drawn at the length the file layer publishes');
        $this->assertStringContainsString('FileManager::checkFileName($file)', $upl, 'The compensation no longer asks the file layer what a managed name is');
        $this->assertStringContainsString("FileManager::getFileOwner(\$one['name']) !== \$tok", $sys, 'The editor listing no longer asks the file layer who owns a stored file');
        $this->assertStringContainsString('public const SALTLEN = 10;', $this->getFile('core/classes/filemanager.php'), 'The file layer does not publish the salt length');
    }

    # The lock is owned by the request: a second entry of one key answers the same handle without waiting, the flock goes with the last release, and another process still queues
    # Without this an owner holding the root of a type would wait for itself the moment Upload or a file operation below it took the same key
    #[Test]
    public function theLockIsReentrantWithinOneRequest(): void
    {
        $data = $this->getProbe('lock', 'config_probe.php')['data'];
        $this->assertTrue($data['held'], 'The file layer could not take the lock at all');
        $this->assertTrue($data['same'], 'A second entry of one key opened a second handle');
        $this->assertTrue($data['sub'], 'A directory below the key shares its lock, so root and subdirectory are no longer two keys');
        $this->assertSame([true, true], $data['spell'], 'A second spelling or another letter case of one directory opened a second lock');
        $this->assertFalse($data['both'], 'Another process took the key while this request held it twice');
        $this->assertFalse($data['inner'], 'The inner release freed the lock its outer owner still holds');
        $this->assertSame([true, true, true], $data['queue'], 'A second process did not wait for the last release');
        $this->assertTrue($data['outer'], 'The last release left the key locked');
        $this->assertTrue($data['again'], 'The key cannot be taken again after it was released');
    }

    # Two writers of one directory stand in one queue: while the file layer holds the lock, an upload of another process publishes nothing and gets through only once it is released
    #[Test]
    public function twoWritersOfOneDirectoryStandInOneQueue(): void
    {
        $data = $this->getProbe('queue');
        $child = $data['child'];
        if (($child['error'] ?? '') === 'nofixture') $this->markTestSkipped('This build cannot encode the PNG fixture the child publishes');
        $this->assertTrue($data['held'], 'The file layer could not take the lock of the destination at all');
        $this->assertNotSame([], $child, 'The child process reported nothing back');
        $this->assertTrue($child['ok'], 'The child publication failed with '.var_export($child['error'] ?? null, true));
        $this->assertSame([], $data['during'], 'A second writer published into the destination while the lock was held');
        $this->assertCount(1, $data['after'], 'The child published a different number of files than one');
        $this->assertLessThan($data['free'], $child['from'], 'The child entered its publication only after the lock was released, so nothing was serialized');
        $this->assertGreaterThan($data['free'], $child['done'], 'The child got through while the lock was still held');
        $this->assertSame($data['want'], $data['locks'], 'The two writers opened something other than the one lock file of that directory');
    }

    # The root of an upload area is locked before any of its subdirectories, so an upload of another process into a subdirectory waits while an owner holds the area
    # That owner is the deletion of a type: nothing is published below the area until it is released
    #[Test]
    public function anUploadIntoASubdirectoryWaitsForTheRootOfItsArea(): void
    {
        $data = $this->getProbe('area');
        $child = $data['child'];
        if (($child['error'] ?? '') === 'nofixture') $this->markTestSkipped('This build cannot encode the PNG fixture the child publishes');
        $this->assertTrue($data['held'], 'The file layer could not take the root of the area');
        $this->assertTrue($child['ok'], 'The child publication failed with '.var_export($child['error'] ?? null, true));
        $this->assertSame([], $data['during'], 'An upload published into a subdirectory while the root of its area was held');
        $this->assertCount(1, $data['after'], 'The child published a different number of files than one');
        $this->assertLessThan($data['free'], $child['from'], 'The child entered its publication only after the root was released, so nothing was serialized');
        $this->assertGreaterThan($data['free'], $child['done'], 'The child got through while the root of its area was still held');
        $this->assertSame($data['want'], $data['locks'], 'The upload locked something other than the root of the area and its own directory');
    }
}
