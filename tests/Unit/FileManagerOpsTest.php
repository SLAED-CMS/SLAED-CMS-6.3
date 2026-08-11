<?php

namespace Tests\Unit;

use FileManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Stage 5 of docs/FILE-MANAGER-CONCEPT-2026.md: the operations of the system area - create, rename, copy, move, delete
 * and pack. Every scenario runs against a disposable tree below the system temp directory that is rebuilt before each
 * test, so nothing below the site is ever touched and the order of the tests never decides their outcome. The route
 * side of the stage - one POST route per operation, the scoped token, the super administrator and the journal entry of
 * §24 - is proven off the sources, because a handler cannot be called without an administrative session.
 */
final class FileManagerOpsTest extends TestCase
{
    private const DIRS = ['templates', 'templates/lite', 'files', 'files/keep', 'files/full', 'storage/logs', 'storage/sessions'];
    private const BODIES = [
        'index.php' => "<?php echo 1;\n",
        '.env' => "SECRET=1\n",
        'templates/theme.css' => "body { color: red; }\n",
        'files/note.txt' => "one\n",
        'files/full/inner.txt' => "kept\n",
        'storage/logs/error_php.log' => "line\n",
        'storage/sessions/sess.txt' => "state\n",
    ];

    private static string $work = '';
    private static string $root = '';
    private static array $files = [];

    # Build the disposable tree before every test: the operations of this stage create and remove objects, so a shared tree would make one test depend on the one before it
    protected function setUp(): void
    {
        if (!class_exists('FileManager', false)) require_once dirname(__DIR__, 2).'/core/classes/filemanager.php';
        self::$work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_filemanager_ops';
        self::$root = self::$work.'/root';
        self::deleteTestTree(self::$work);
        mkdir(self::$root, 0777, true);
        foreach (self::DIRS as $dir) mkdir(self::$root.'/'.$dir, 0777, true);
        foreach (self::BODIES as $file => $body) file_put_contents(self::$root.'/'.$file, $body);
    }

    # Leave nothing behind: the tree goes, and so do the lock files the operations of this class opened below the log root
    public static function tearDownAfterClass(): void
    {
        foreach (array_merge([''], self::DIRS) as $dir) {
            $lock = self::getLockFile(($dir === '') ? self::$root : self::$root.'/'.$dir);
            if (is_file($lock)) unlink($lock);
        }
        self::deleteTestTree(self::$work);
    }

    # Remove one directory with everything below it
    private static function deleteTestTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') continue;
            $full = $dir.'/'.$file;
            if (is_dir($full)) self::deleteTestTree($full);
            else {
                chmod($full, 0666);
                unlink($full);
            }
        }
        rmdir($dir);
    }

    # Return the path of the lock file one directory serializes on, drawn exactly the way the file layer draws it
    private static function getLockFile(string $dir): string
    {
        return rtrim(str_replace('\\', '/', LOGS_DIR), '/').'/uploads/'.substr(sha1(rtrim(str_replace('\\', '/', $dir), '/')), 0, 16).'.lock';
    }

    # Return one context over the disposable tree; the mode decides what the context is allowed to do at all
    private function getManager(string $mode = 'system'): FileManager
    {
        return new FileManager($mode, self::$root);
    }

    # Read one repository file once per run
    private function getFile(string $path): string
    {
        if (isset(self::$files[$path])) return self::$files[$path];
        $full = dirname(__DIR__, 2).'/'.$path;
        $this->assertFileExists($full);
        return self::$files[$path] = (string)file_get_contents($full);
    }

    # Return the body of one method of the file layer, which is where the order of the steps inside it is asserted
    private function getMethod(string $code, string $name): string
    {
        $from = strpos($code, 'function '.$name.'(');
        $this->assertNotFalse($from, 'The file layer carries no '.$name.'()');
        $to = strpos($code, "\n    }", $from);
        $this->assertNotFalse($to, 'The body of '.$name.'() does not end');
        return substr($code, $from, $to - $from);
    }

    # A create makes an empty file and a directory below the open one, and the answer names the object by the relative path the browser goes on working with
    #[Test]
    public function aFileAndADirectoryAreCreated(): void
    {
        $man = $this->getManager();
        $res = $man->addFileEntry('files/fresh.txt');
        $this->assertTrue($res['ok'], 'A new file was refused with '.var_export($res['error'], true));
        $this->assertSame('files/fresh.txt', $res['path'], 'The answer names another object than the one that was created');
        $this->assertFileExists(self::$root.'/files/fresh.txt');
        $this->assertSame(0, filesize(self::$root.'/files/fresh.txt'), 'The new file was not created empty');
        $res = $man->addDirectory('files/fresh');
        $this->assertTrue($res['ok'], 'A new directory was refused with '.var_export($res['error'], true));
        $this->assertDirectoryExists(self::$root.'/files/fresh');
        $this->assertFalse($man->addDirectory('files/gone/deep')['ok'], 'A directory was created below a parent that does not exist');
        $this->assertFalse($man->addFileEntry('files/../../away.txt')['ok'], 'A create walked out of the root of the context');
        $this->assertFileDoesNotExist(self::$work.'/away.txt', 'The create that walked out of the root still wrote a file');
    }

    # A name already taken is refused by every operation that would write over it, and the object standing there keeps the content it had
    #[Test]
    public function aTakenNameIsRefused(): void
    {
        $man = $this->getManager();
        $this->assertSame('exists', $man->addFileEntry('files/note.txt')['error'], 'A create wrote over an existing file');
        $this->assertSame('exists', $man->addDirectory('files/keep')['error'], 'A directory was created over an existing one');
        $this->assertSame('exists', $man->updateFileName('files/note.txt', 'full')['error'], 'A rename wrote over an object standing under the new name');
        $this->assertSame('exists', $man->addFileCopy('index.php', 'files/note.txt')['error'], 'A copy wrote over an existing file');
        $this->assertSame('exists', $man->updateFilePath('index.php', 'files/note.txt')['error'], 'A move wrote over an existing file');
        $this->assertSame("one\n", file_get_contents(self::$root.'/files/note.txt'), 'A refused operation changed the object it was refused over');
        $this->assertFileExists(self::$root.'/index.php', 'The refused move took the source away anyway');
    }

    # A rename keeps the object in its own directory: the new name is a name, and a name carrying a separator is refused instead of turning into a move
    #[Test]
    public function aRenameKeepsTheObjectInItsDirectory(): void
    {
        $man = $this->getManager();
        $res = $man->updateFileName('files/note.txt', 'renamed.txt');
        $this->assertTrue($res['ok'], 'A rename was refused with '.var_export($res['error'], true));
        $this->assertSame('files/renamed.txt', $res['path'], 'The rename answered another path than the one it wrote');
        $this->assertFileExists(self::$root.'/files/renamed.txt');
        $this->assertFileDoesNotExist(self::$root.'/files/note.txt', 'The renamed file is still under its old name too');
        $this->assertSame('closed', $man->updateFileName('files/renamed.txt', '../escaped.txt')['error'], 'A rename was allowed to carry a path');
        $this->assertSame('closed', $man->updateFileName('files/renamed.txt', 'sub/deep.txt')['error'], 'A rename was allowed to move the object');
        $this->assertSame('closed', $man->updateFileName('files/renamed.txt', 'sub\\deep.txt')['error'], 'A rename spelled with the other separator moved the object');
        $this->assertSame('closed', $man->updateFileName('files/renamed.txt', '')['error'], 'A rename to an empty name was taken');
        $same = 'A rename to the name the object already carries answered something other than a taken name';
        $this->assertSame('exists', $man->updateFileName('files/renamed.txt', 'renamed.txt')['error'], $same);
        $this->assertSame('closed', $man->updateFileName('', 'root.txt')['error'], 'The root of the context was renamed');
    }

    # A copy leaves the source where it stood and writes the second object; a directory is not copied at all, because that would be a walk of a tree the plan does not walk
    #[Test]
    public function aCopyLeavesTheSourceStanding(): void
    {
        $man = $this->getManager();
        $res = $man->addFileCopy('files/note.txt', 'templates/lite/note.txt');
        $this->assertTrue($res['ok'], 'A copy was refused with '.var_export($res['error'], true));
        $this->assertSame("one\n", file_get_contents(self::$root.'/templates/lite/note.txt'), 'The copy does not carry the content of the source');
        $this->assertFileExists(self::$root.'/files/note.txt', 'The copy took the source away');
        $this->assertSame('closed', $man->addFileCopy('files/keep', 'files/second')['error'], 'A directory was copied');
    }

    # A move takes the object away from where it stood, and a directory is never moved inside itself, because it would be buried below its own name
    #[Test]
    public function aMoveTakesTheObjectAway(): void
    {
        $man = $this->getManager();
        $res = $man->updateFilePath('files/note.txt', 'templates/note.txt');
        $this->assertTrue($res['ok'], 'A move was refused with '.var_export($res['error'], true));
        $this->assertFileExists(self::$root.'/templates/note.txt');
        $this->assertFileDoesNotExist(self::$root.'/files/note.txt', 'The moved file stayed where it was');
        $this->assertSame('loop', $man->updateFilePath('files/keep', 'files/keep/inner')['error'], 'A directory was moved inside itself');
        $this->assertSame('exists', $man->updateFilePath('files/keep', 'files/keep')['error'], 'A directory was moved onto itself');
        $this->assertSame('closed', $man->updateFilePath('files/full/inner.txt', 'templates/')['error'], 'A destination naming a directory instead of an object was taken');
        $this->assertDirectoryExists(self::$root.'/files/keep', 'The refused move lost the directory');
        $this->assertSame('closed', $man->updateFilePath('files/gone.txt', 'templates/gone.txt')['error'], 'A source that does not exist was moved');
    }

    # A delete removes one file and one empty directory; a directory still holding objects is refused by name, because recursive deletion is out of the first version
    #[Test]
    public function aDeleteRemovesOnlyAnEmptyDirectory(): void
    {
        $man = $this->getManager();
        $this->assertTrue($man->deleteFileEntry('files/note.txt')['ok'], 'A file was not deleted');
        $this->assertFileDoesNotExist(self::$root.'/files/note.txt');
        $this->assertTrue($man->deleteFileEntry('files/keep')['ok'], 'An empty directory was not deleted');
        $this->assertDirectoryDoesNotExist(self::$root.'/files/keep');
        $this->assertSame('filled', $man->deleteFileEntry('files/full')['error'], 'A directory holding objects was deleted');
        $this->assertFileExists(self::$root.'/files/full/inner.txt', 'The refused delete removed what stood inside the directory');
        $this->assertSame('closed', $man->deleteFileEntry('')['error'], 'The root of the context was deleted');
    }

    # A file is packed into a ZIP beside it, the archive holds the file under its own name, and packing a second time is refused instead of replacing the first archive
    #[Test]
    public function aFileIsPackedIntoAnArchive(): void
    {
        if (!class_exists('ZipArchive')) $this->markTestSkipped('The build carries no archive extension');
        $man = $this->getManager();
        $res = $man->addFileArchive('files/note.txt');
        $this->assertTrue($res['ok'], 'A file was not packed: '.var_export($res['error'], true));
        $this->assertSame('files/note.txt.zip', $res['path'], 'The archive was answered under another name than the one it was written under');
        $zip = new ZipArchive();
        $this->assertTrue($zip->open(self::$root.'/files/note.txt.zip') === true, 'The archive does not open');
        $this->assertSame("one\n", $zip->getFromName('note.txt'), 'The archive does not carry the file it was made of');
        $zip->close();
        $this->assertSame('exists', $man->addFileArchive('files/note.txt')['error'], 'A second pack replaced the archive of the first');
        $this->assertSame('closed', $man->addFileArchive('files/keep')['error'], 'A directory was packed');
    }

    # The paths the policy closes refuse every operation of the stage, on both sides: a closed file is not copied out of its place and nothing is written into a closed directory
    #[Test]
    public function theClosedPathsRefuseEveryOperation(): void
    {
        $man = $this->getManager();
        $this->assertSame('closed', $man->deleteFileEntry('.env')['error'], 'A closed file was deleted');
        $this->assertSame('closed', $man->updateFileName('.env', 'env.txt')['error'], 'A closed file was renamed');
        $this->assertSame('closed', $man->addFileCopy('.env', 'files/env.txt')['error'], 'A closed file was copied into an open directory');
        $this->assertSame('closed', $man->updateFilePath('.env', 'files/env.txt')['error'], 'A closed file was moved into an open directory');
        $this->assertSame('closed', $man->addFileArchive('.env')['error'], 'A closed file was packed into an archive that could then be downloaded');
        $this->assertSame('closed', $man->addFileCopy('files/note.txt', 'storage/sessions/note.txt')['error'], 'An open file was copied into a closed directory');
        $this->assertSame('closed', $man->updateFilePath('files/note.txt', 'storage/sessions/note.txt')['error'], 'An open file was moved into a closed directory');
        $this->assertSame('closed', $man->addFileEntry('storage/sessions/new.txt')['error'], 'A file was created inside a closed directory');
        $this->assertSame('closed', $man->addFileEntry('files/half.part')['error'], 'A file was created under a name the writers of the project reserve');
        $this->assertSame('closed', $man->deleteFileEntry('storage/sessions/sess.txt')['error'], 'A closed file was deleted');
        $this->assertSame('closed', $man->updateFileName('storage/logs/error_php.log', 'old.log')['error'], 'A write-closed log was renamed');
        $this->assertFileExists(self::$root.'/.env', 'A refused operation removed the closed file anyway');
        $this->assertFileDoesNotExist(self::$root.'/files/env.txt', 'The closed file reached an open directory anyway');
        $this->assertFileDoesNotExist(self::$root.'/storage/sessions/note.txt', 'An object was written into the closed directory anyway');
    }

    # Only a context carrying the capability writes: the window of the editor creates nothing, renames nothing and moves nothing, whatever path it is handed
    #[Test]
    public function aContextWithoutTheCapabilityRefuses(): void
    {
        $man = $this->getManager('editor');
        foreach (['create', 'mkdir', 'rename', 'copy', 'move'] as $able) {
            $this->assertFalse($man->getCapabilities()[$able], 'The window of the editor claims the '.$able.' capability');
        }
        $this->assertSame('closed', $man->addFileEntry('files/fresh.txt')['error'], 'The window of the editor created a file');
        $this->assertSame('closed', $man->addDirectory('files/fresh')['error'], 'The window of the editor created a directory');
        $this->assertSame('closed', $man->updateFileName('files/note.txt', 'other.txt')['error'], 'The window of the editor renamed a file');
        $this->assertSame('closed', $man->addFileCopy('files/note.txt', 'templates/note.txt')['error'], 'The window of the editor copied a file');
        $this->assertSame('closed', $man->updateFilePath('files/note.txt', 'templates/note.txt')['error'], 'The window of the editor moved a file');
        $this->assertSame('closed', $man->deleteFileEntry('files/note.txt')['error'], 'The window of the editor deleted a file of a user who moderates nothing');
        $this->assertFileExists(self::$root.'/files/note.txt', 'A context without the capability changed the tree anyway');
        $this->assertFileDoesNotExist(self::$root.'/files/fresh.txt', 'A context without the capability created a file anyway');
    }

    # Every operation of the stage runs under the lock of the directory it writes in, and one over two directories takes both keys sorted, so two opposite moves cannot deadlock
    #[Test]
    public function everyWriteRunsUnderTheSortedLocks(): void
    {
        $code = $this->getFile('core/classes/filemanager.php');
        foreach (['addPathEntry', 'deleteFileEntry', 'addFileArchive'] as $name) {
            $this->assertStringContainsString('self::getPathLock(', $this->getMethod($code, $name), $name.'() writes without taking the lock of its directory');
        }
        $pair = $this->getMethod($code, 'updatePathEntry');
        $this->assertStringContainsString('self::getPathLocks([dirname(', $pair, 'An operation over two paths does not take two locks');
        $body = $this->getMethod($code, 'getPathLocks');
        $sort = strpos($body, 'sort($keys)');
        $take = strpos($body, 'self::getPathLock($key)');
        $this->assertNotFalse($sort, 'The keys of an operation are taken in the order they were named in, so two opposite moves wait for each other forever');
        $this->assertLessThan($take, $sort, 'The first key is taken before the keys are sorted');
        $this->assertStringContainsString('array_reverse($locks)', $this->getMethod($code, 'deletePathLocks'), 'The locks are released in the order they were taken in');
        $man = $this->getManager();
        $this->assertTrue($man->updateFilePath('files/note.txt', 'templates/note.txt')['ok'], 'The move of the lock scenario was refused');
        $this->assertFileExists(self::getLockFile(self::$root.'/files'), 'The move opened no lock file for the directory it took the object from');
        $this->assertFileExists(self::getLockFile(self::$root.'/templates'), 'The move opened no lock file for the directory it wrote in');
    }

    # Every operation of the stage has exactly one POST route of the module, and the token of that scope is checked before the file layer is called at all
    #[Test]
    public function theRoutesArePostOnlyAndScoped(): void
    {
        $mod = $this->getFile('admin/modules/uploads.php');
        $guard = "if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');";
        $this->assertStringContainsString($guard, $mod, 'The module of the file browser is open to more than the super administrator');
        foreach (['fmcreate', 'fmmkdir', 'fmrename', 'fmcopy', 'fmmove', 'fmdelete', 'fmcompress'] as $name) {
            $this->assertStringContainsString("case '".$name."': ".$name.'(); break;', $mod, 'The operation '.$name.' has no route of its own');
        }
        $from = strpos($mod, 'function setFileAction(string $op): void {');
        $this->assertNotFalse($from, 'The module carries no handler for the operations of the stage');
        $body = substr($mod, $from, (int)strpos($mod, "\n}", $from) - $from);
        $token = strpos($body, "checkAdminPost('uploads')");
        $this->assertNotFalse($token, 'The operations do not check the scoped token of the module, so a GET or a foreign form reaches them');
        $this->assertLessThan(strpos($body, '$res = match ($op)'), $token, 'The operation runs before the token of the request is checked');
        $this->assertMatchesRegularExpression('#if \(!\$pass\) setRedirect\(#', $body, 'A request that failed the token check still reaches the operation');
        $this->assertStringContainsString("getAdminFilePath('file', 'post')", $body, 'The path of the operation is read from somewhere other than the body of the POST');
        $this->assertStringContainsString("getAdminFilePath('arg', 'post')", $body, 'The value of the operation is read from somewhere other than the body of the POST');
        $this->assertStringNotContainsString("getVar('get'", $body, 'An operation of the stage reads its own values out of the query string');
    }

    # Every operation leaves one journal entry naming the administrator, the operation, both paths and the result, and no content of a file travels into it
    #[Test]
    public function everyOperationLeavesAJournalEntry(): void
    {
        $mod = $this->getFile('admin/modules/uploads.php');
        $from = strpos($mod, 'function setFileAction(string $op): void {');
        $body = substr($mod, $from, (int)strpos($mod, "\n}", $from) - $from);
        $this->assertStringContainsString('Logger::addFile(', $body, 'An operation of the system area leaves no journal entry at all');
        $entry = substr($body, (int)strpos($body, 'Logger::addFile('));
        $entry = substr($entry, 0, (int)strpos($entry, ']);'));
        foreach (['admin', 'op', 'path', 'target', 'result'] as $key) {
            $this->assertStringContainsString("'".$key."' =>", $entry, 'The journal entry names no '.$key);
        }
        $drop = strpos($body, "unset(\$_POST['token'])");
        $this->assertNotFalse($drop, 'The token stays in the request array, so the journal writes a live credential with the entry');
        $this->assertLessThan((int)strpos($body, 'Logger::addFile('), $drop, 'The token is dropped from the request only after the journal entry was written');
        $late = 'The answer is sent before the operation is written into the journal';
        $this->assertLessThan(strpos($body, 'setRedirect($back, false, 302, $note'), strpos($body, 'Logger::addFile('), $late);
    }
}
