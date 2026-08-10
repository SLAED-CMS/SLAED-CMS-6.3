<?php

namespace Tests\Unit;

use FileManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage 4 of docs/FILE-MANAGER-CONCEPT-2026.md: the source editor of the system area, the first write of the whole
 * plan. Every scenario runs against a disposable tree below the system temp directory that carries one file of each
 * language the resolver of §11 names, an empty one, a binary one and the closed paths of the policy table, so nothing
 * below the site is ever written. The route side of the stage - POST only, scoped token, super administrator, the 409
 * of a version conflict and the journal entry of §24 - is proven off the sources, because a handler cannot be called
 * without an administrative session and what is asserted there is the shape of the route and not the reply of one run.
 */
final class FileManagerEditTest extends TestCase
{
    private const BODIES = [
        'index.php' => "<?php echo 1;\n",
        'templates/theme.css' => "body { color: red; }\n",
        'plugins/app.js' => "var num = 1;\n",
        'config/data.json' => "{\"key\": 1}\n",
        'docs/note.md' => "# note\n",
        'files/empty.txt' => '',
        'files/crlf.txt' => "one\r\ntwo\r\n",
        '.env' => "SECRET=1\n",
        'storage/logs/error_php.log' => "line\n",
    ];

    private static string $work = '';
    private static string $root = '';
    private static array $files = [];

    # Build the disposable tree once for the whole class: one file of every editable language, one empty file, one binary and the closed paths of the policy
    public static function setUpBeforeClass(): void
    {
        if (!class_exists('FileManager', false)) require_once dirname(__DIR__, 2).'/core/classes/filemanager.php';
        self::$work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_filemanager_edit';
        self::$root = self::$work.'/root';
        self::deleteTestTree(self::$work);
        foreach (['root/templates', 'root/plugins', 'root/config', 'root/docs', 'root/files', 'root/storage/logs'] as $dir) {
            mkdir(self::$work.'/'.$dir, 0777, true);
        }
        foreach (self::BODIES as $file => $body) file_put_contents(self::$root.'/'.$file, $body);
        file_put_contents(self::$root.'/files/pic.png', "\x89PNG\r\n\x1a\n\x00\x00\x00\x0d");
        file_put_contents(self::$root.'/files/nul.txt', "text\x00tail");
        file_put_contents(self::$work.'/outside.txt', "away\n");
    }

    # Leave nothing behind: the tree goes, and so does the one lock file the saves of this class opened below the log root
    public static function tearDownAfterClass(): void
    {
        foreach (['files', 'templates', 'config', 'plugins', 'docs', ''] as $dir) {
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

    # Restore one fixture file to the body the class started with, so the order of the tests never decides their outcome
    private function setFixture(string $file): string
    {
        $body = self::BODIES[$file] ?? '';
        file_put_contents(self::$root.'/'.$file, $body);
        return $body;
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
        $from = strpos($code, 'public function '.$name.'(');
        $this->assertNotFalse($from, 'The file layer carries no '.$name.'()');
        $to = strpos($code, "\n    }", $from);
        $this->assertNotFalse($to, 'The body of '.$name.'() does not end');
        return substr($code, $from, $to - $from);
    }

    # A source file opens with its content, the number of lines it holds and the version the save has to hand back, and the version is the digest of the bytes on disk
    #[Test]
    public function aSourceFileOpensWithItsVersion(): void
    {
        $body = $this->setFixture('index.php');
        $one = $this->getManager()->getFileBody('index.php');
        $this->assertSame($body, $one['text'], 'The editor received something other than the file');
        $this->assertSame(sha1($body), $one['version'], 'The version is not the digest of the content on disk');
        $this->assertSame(1, $one['lines'], 'A single line of source was counted as something else');
    }

    # Every language the resolver names opens and saves: the write goes through, the file on disk carries the new body and the answer carries the version of what was written
    #[Test]
    public function everyEditableLanguageOpensAndSaves(): void
    {
        $man = $this->getManager();
        foreach (['index.php' => 'php', 'templates/theme.css' => 'css', 'plugins/app.js' => 'js', 'config/data.json' => 'json', 'docs/note.md' => 'text'] as $file => $lang) {
            $this->setFixture($file);
            $this->assertSame($lang, $man->getCodeLanguage($file), $file.' resolves to another highlighting mode');
            $open = $man->getFileBody($file);
            $this->assertNotSame([], $open, $file.' does not open in the editor at all');
            $text = $open['text']."/* saved */\n";
            $res = $man->setFileBody($file, $text, $open['version']);
            $this->assertTrue($res['ok'], $file.' refused the save with '.var_export($res['error'], true));
            $this->assertSame($text, file_get_contents(self::$root.'/'.$file), $file.' on disk is not what was saved');
            $this->assertSame(sha1($text), $res['version'], $file.' answered a version other than the one it wrote');
            $this->setFixture($file);
        }
    }

    # An empty file saves as an empty file: the write is not mistaken for a missing body, and the file stays where it was
    #[Test]
    public function anEmptySaveIsStored(): void
    {
        $man = $this->getManager();
        $this->setFixture('files/empty.txt');
        $open = $man->getFileBody('files/empty.txt');
        $this->assertSame('', $open['text'], 'An empty file did not open as an empty one');
        $this->assertSame(0, $open['lines'], 'An empty file was counted as holding lines');
        $res = $man->setFileBody('files/empty.txt', '', $open['version']);
        $this->assertTrue($res['ok'], 'An empty save was refused with '.var_export($res['error'], true));
        $this->assertFileExists(self::$root.'/files/empty.txt');
        $this->assertSame(0, filesize(self::$root.'/files/empty.txt'), 'The empty save left something in the file');
    }

    # A binary never reaches the editor: an extension carrying no mode does not open, a NUL byte in the body is not written, and text over binary content opens for nobody
    #[Test]
    public function aBinaryIsRefused(): void
    {
        $man = $this->getManager();
        $this->assertSame([], $man->getFileBody('files/pic.png'), 'A binary opened in the source editor');
        $this->assertSame('closed', $man->setFileBody('files/pic.png', 'text', sha1('text'))['error'], 'A binary accepted a source save');
        $this->assertSame([], $man->getFileBody('files/nul.txt'), 'A file carrying a NUL byte opened as source');
        $this->setFixture('templates/theme.css');
        $open = $man->getFileBody('templates/theme.css');
        $res = $man->setFileBody('templates/theme.css', "body\x00{}", $open['version']);
        $this->assertSame('body', $res['error'], 'A body carrying a NUL byte was written');
        $this->assertSame(self::BODIES['templates/theme.css'], file_get_contents(self::$root.'/templates/theme.css'), 'The refused body still changed the file');
    }

    # A file changed after it was opened is a conflict: nothing is written, and the answer carries the version the file holds now, so the interface can say what happened
    #[Test]
    public function aFileChangedAfterOpenIsAConflict(): void
    {
        $man = $this->getManager();
        $this->setFixture('templates/theme.css');
        $open = $man->getFileBody('templates/theme.css');
        $other = "body { color: blue; }\n";
        file_put_contents(self::$root.'/templates/theme.css', $other);
        $res = $man->setFileBody('templates/theme.css', "body { color: green; }\n", $open['version']);
        $this->assertFalse($res['ok'], 'A stale version was allowed to write');
        $this->assertSame('conflict', $res['error'], 'A stale version answered something other than a conflict');
        $this->assertSame(sha1($other), $res['version'], 'The conflict did not answer the version the file holds now');
        $this->assertSame($other, file_get_contents(self::$root.'/templates/theme.css'), 'The refused write changed the file anyway');
        $this->setFixture('templates/theme.css');
    }

    # The save replaces the file that is already there and leaves no partial behind: the window in which the file does not exist at all is never opened
    #[Test]
    public function theSaveReplacesTheExistingFile(): void
    {
        $man = $this->getManager();
        $this->setFixture('docs/note.md');
        $full = self::$root.'/docs/note.md';
        $this->assertFileExists($full, 'The fixture the save is supposed to replace is missing');
        $open = $man->getFileBody('docs/note.md');
        $this->assertTrue($man->setFileBody('docs/note.md', "# replaced\n", $open['version'])['ok'], 'The replacement of an existing file was refused');
        $this->assertSame("# replaced\n", file_get_contents($full), 'The existing file was not replaced by the new body');
        $this->assertFileDoesNotExist($full.'.part', 'The temporary file of the save stayed behind');
        $this->setFixture('docs/note.md');
    }

    # The version is compared inside the lock of the destination directory and released after the rename: comparing it beside the lock protects against nothing
    #[Test]
    public function theSaveRunsUnderTheLockOfTheDirectory(): void
    {
        $body = $this->getMethod($this->getFile('core/classes/filemanager.php'), 'setFileBody');
        $lock = strpos($body, 'self::getPathLock(dirname(');
        $read = strpos($body, 'file_get_contents($pair[\'full\'])');
        $test = strpos($body, 'hash_equals(sha1($now), $ver)');
        $move = strpos($body, 'rename($temp, $pair[\'full\'])');
        $free = strrpos($body, 'self::deletePathLock($lock)');
        $this->assertNotFalse($lock, 'The save takes no lock of the destination directory at all');
        $this->assertLessThan($read, $lock, 'The content is re-read before the lock is taken, so two writers read one version');
        $this->assertLessThan($test, $read, 'The version is compared against something other than a fresh read');
        $this->assertLessThan($move, $test, 'The file is replaced before the version is known to agree');
        $this->assertLessThan($free, $move, 'The lock is released before the replacement it guards');
        $man = $this->getManager();
        $this->setFixture('files/empty.txt');
        $open = $man->getFileBody('files/empty.txt');
        $this->assertTrue($man->setFileBody('files/empty.txt', "kept\n", $open['version'])['ok'], 'The save of the lock scenario was refused');
        $this->assertFileExists(self::getLockFile(self::$root.'/files'), 'The save opened no lock file for the directory it wrote in');
        $this->setFixture('files/empty.txt');
    }

    # The permissions of the file survive the save, because the body travels through a temporary file and the mode of the destination is carried over to it before the rename
    #[Test]
    public function thePermissionsSurviveTheSave(): void
    {
        $man = $this->getManager();
        $this->setFixture('plugins/app.js');
        $full = self::$root.'/plugins/app.js';
        chmod($full, 0640);
        clearstatcache(true, $full);
        $mode = fileperms($full) & 0777;
        $open = $man->getFileBody('plugins/app.js');
        $this->assertTrue($man->setFileBody('plugins/app.js', "var num = 2;\n", $open['version'])['ok'], 'The save of the permission scenario was refused');
        clearstatcache(true, $full);
        $this->assertSame($mode, fileperms($full) & 0777, 'The saved file carries other permissions than the one it replaced');
        chmod($full, 0666);
        $this->setFixture('plugins/app.js');
    }

    # The line separator of the file on disk is what the new body gets: a form submits its lines the way the browser spells them, and a save must not rewrite every line of a file
    #[Test]
    public function theSeparatorOfTheFileSurvivesTheSave(): void
    {
        $man = $this->getManager();
        $this->setFixture('files/crlf.txt');
        $open = $man->getFileBody('files/crlf.txt');
        $this->assertTrue($man->setFileBody('files/crlf.txt', "one\ntwo\nthree\n", $open['version'])['ok'], 'The save into the CRLF file was refused');
        $this->assertSame("one\r\ntwo\r\nthree\r\n", file_get_contents(self::$root.'/files/crlf.txt'), 'The file that held CRLF was rewritten with another separator');
        $this->setFixture('templates/theme.css');
        $open = $man->getFileBody('templates/theme.css');
        $this->assertTrue($man->setFileBody('templates/theme.css', "a\r\nb\r\n", $open['version'])['ok'], 'The save into the LF file was refused');
        $this->assertSame("a\nb\n", file_get_contents(self::$root.'/templates/theme.css'), 'The file that held LF was rewritten with CRLF');
        $this->setFixture('templates/theme.css');
    }

    # A path the policy closes to writing refuses the save, and so does one that leaves the root: the same decision that hides a path from a listing refuses it on a direct write
    #[Test]
    public function aClosedPathRefusesTheWrite(): void
    {
        $man = $this->getManager();
        foreach (['.env', 'storage/logs/error_php.log', '../outside.txt', 'files/missing.txt'] as $path) {
            $res = $man->setFileBody($path, "written\n", sha1("written\n"));
            $this->assertFalse($res['ok'], $path.' accepted a write it must refuse');
            $this->assertSame('closed', $res['error'], $path.' refused the write for another reason than the policy');
        }
        $this->assertSame("SECRET=1\n", file_get_contents(self::$root.'/.env'), 'The closed file changed anyway');
        $this->assertSame("line\n", file_get_contents(self::$root.'/storage/logs/error_php.log'), 'The write-closed log changed anyway');
        $this->assertFileDoesNotExist(self::$root.'/files/missing.txt', 'The save created a file that did not exist');
    }

    # Only the system context edits at all: the upload catalogue and the window of the editor carry no edit capability, so neither of them opens or writes a source file
    #[Test]
    public function onlyTheSystemContextWritesSource(): void
    {
        foreach (['uploads', 'editor'] as $mode) {
            $man = $this->getManager($mode);
            $this->assertFalse($man->getCapabilities()['edit'], 'The '.$mode.' context claims the edit capability');
            $this->assertSame([], $man->getFileBody('templates/theme.css'), 'The '.$mode.' context opened a source file');
            $res = $man->setFileBody('templates/theme.css', "body {}\n", sha1(self::BODIES['templates/theme.css']));
            $this->assertSame('closed', $res['error'], 'The '.$mode.' context was allowed to write a source file');
        }
        $this->assertSame(self::BODIES['templates/theme.css'], file_get_contents(self::$root.'/templates/theme.css'), 'A context without the edit capability changed the file');
    }

    # The route of the save is a POST of the module carrying the scoped token, and the module itself is closed to everyone but the super administrator
    #[Test]
    public function theSaveRouteIsAScopedPost(): void
    {
        $mod = $this->getFile('admin/modules/uploads.php');
        $guard = "if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');";
        $this->assertStringContainsString($guard, $mod, 'The module of the file browser is open to more than the super administrator');
        $this->assertStringContainsString("case 'fmsave': fmsave(); break;", $mod, 'The save of the editor has no route of its own');
        $from = strpos($mod, 'function fmsave(): void {');
        $this->assertNotFalse($from, 'The module carries no save handler');
        $body = substr($mod, $from, (int)strpos($mod, "\n}", $from) - $from);
        $this->assertStringContainsString("checkAdminPost('uploads')", $body, 'The save does not check the scoped token of the module, so a GET or a foreign form reaches it');
        $this->assertStringContainsString('http_response_code(409)', $body, 'A version conflict answers something other than 409');
        $this->assertStringContainsString("getAdminFilePath('file', 'post')", $body, 'The saved path is read from somewhere other than the body of the POST');
        $token = strpos($body, "checkAdminPost('uploads')");
        $write = strpos($body, 'setFileBody(');
        $this->assertLessThan($write, $token, 'The file is written before the token of the request is checked');
        $this->assertMatchesRegularExpression('#if \(!\$pass\) setRedirect\(#', $body, 'A request that failed the token check still reaches the write');
    }

    # An open editor guards the work it holds: every way out asks first, the question comes from a language constant and a save is not treated as a way out
    #[Test]
    public function theOpenEditorGuardsUnsavedWork(): void
    {
        $part = $this->getFile('templates/admin/partials/file-browser-editor.html');
        $this->assertStringContainsString('data-sl-fm-ask="{{ ask_text }}"', $part, 'The editor carries no question for the guard, so the guard has nothing to ask with');
        $note = 'The editor does not name its widget, so the guard cannot find the document';
        $this->assertStringContainsString('data-sl-fm-code="{{ code_id }}"', $part, $note);
        $this->assertStringContainsString('_UPLOADS_LEAVE', $this->getFile('core/admin.php'), 'The question of the guard is not taken from a language constant');
        $js = $this->getFile('templates/admin/assets/js/admin-ui.js');
        foreach (['htmx:confirm', 'beforeunload', 'setConfirmTask', 'fmfree'] as $mark) {
            $this->assertStringContainsString($mark, $js, 'The guard of the editor misses '.$mark);
        }
        $only = 'The guard answers every request of the page, so refreshing a panel of its own asks about leaving an editor it does not touch';
        $this->assertStringContainsString("from.closest('#slfmbody, .sl-fm-bar')", $js, $only);
        $task = 'window.setConfirmTask = function (text, run)';
        $this->assertStringContainsString($task, $this->getFile('plugins/system/slaed.js'), 'The shared confirm protocol takes no task of a caller');
    }

    # Every save leaves one journal entry naming the administrator, the operation, the path and the result, and the content of the file never travels into it
    #[Test]
    public function everySaveLeavesAJournalEntry(): void
    {
        $mod = $this->getFile('admin/modules/uploads.php');
        $from = strpos($mod, 'function fmsave(): void {');
        $body = substr($mod, $from, (int)strpos($mod, "\n}", $from) - $from);
        $this->assertStringContainsString('Logger::addFile(', $body, 'A save of the system area leaves no journal entry at all');
        $entry = substr($body, (int)strpos($body, 'Logger::addFile('));
        $entry = substr($entry, 0, (int)strpos($entry, ']);'));
        foreach (['admin', 'op', 'path', 'target', 'result'] as $key) {
            $this->assertStringContainsString("'".$key."' =>", $entry, 'The journal entry names no '.$key);
        }
        $this->assertStringNotContainsString('$text', $entry, 'The journal entry carries the content of the file');
        $drop = strpos($body, "unset(\$_POST['text'], \$_POST['token'])");
        $this->assertNotFalse($drop, 'The body and the token stay in the request array, so the journal writes the content and a live token with the entry');
        $this->assertLessThan((int)strpos($body, 'Logger::addFile('), $drop, 'The body is dropped from the request only after the journal entry was written');
    }
}
