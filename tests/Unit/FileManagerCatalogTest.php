<?php

namespace Tests\Unit;

use FileManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Stage 6 of docs/FILE-MANAGER-CONCEPT-2026.md: the catalogue of the administration - the upload context, the marked
 * set and the archive it is packed into. Every filesystem scenario runs against a disposable tree below the system
 * temp directory that is rebuilt before each test, so nothing below the site is ever touched. The route side - one
 * POST route per operation, the scoped token before the upload service, the marked set read out of the body and the
 * journal entry of §24 - is proven off the sources, because a handler cannot be called without an administrative
 * session.
 */
final class FileManagerCatalogTest extends TestCase
{
    private const DIRS = ['all', 'files', 'files/thumb', 'news'];
    private const BODIES = [
        'all/index.html' => "\n",
        'files/note-abcdefghij-7.txt' => "one\n",
        'files/free-abcdefghij.txt' => "two\n",
        'files/plain.txt' => "three\n",
        'files/thumb/note-abcdefghij-7.txt' => "small\n",
        'files/.upload-ab.part' => "half\n",
        'news/story.txt' => "four\n",
    ];

    private static string $work = '';
    private static string $root = '';
    private static array $files = [];

    # Build the disposable tree before every test: the operations of this stage create and remove objects, so a shared tree would make one test depend on the one before it
    protected function setUp(): void
    {
        if (!class_exists('FileManager', false)) require_once dirname(__DIR__, 2).'/core/classes/filemanager.php';
        self::$work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_filemanager_cat';
        self::$root = self::$work.'/uploads';
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

    # Return one context over the disposable upload tree
    private function getManager(string $mode = 'uploads'): FileManager
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

    # Return the body of one function of a repository file, so a claim is made about the handler and not about the file around it
    private function getBody(string $path, string $name): string
    {
        $code = $this->getFile($path);
        $from = strpos($code, 'function '.$name.'(');
        $this->assertNotFalse($from, $name.'() is gone from '.$path);
        $stop = strpos($code, "\n}", $from);
        $this->assertNotFalse($stop, $name.'() in '.$path.' has no closing brace of its own');
        return substr($code, $from, $stop - $from);
    }

    # The catalogue answers exactly what §14 promises the administration over its own uploads, and the source editor is not part of it
    #[Test]
    public function theCatalogueAnswersTheCapabilitiesOfItsOwnArea(): void
    {
        $able = $this->getManager()->getCapabilities();
        foreach (['browse', 'preview', 'upload', 'download', 'mkdir', 'rename', 'copy', 'move', 'delete', 'compress'] as $key) {
            $this->assertTrue($able[$key], 'The catalogue of the administration does not allow '.$key);
        }
        foreach (['edit', 'create', 'insert', 'embed'] as $key) {
            $this->assertFalse($able[$key], 'The catalogue of the administration allows '.$key.', which belongs to another screen');
        }
        $this->assertFalse($this->getManager()->isFileEditable('files/plain.txt'), 'A file of the upload tree opens in the source editor');
        $this->assertSame([], $this->getManager()->getFileBody('files/plain.txt'), 'The upload catalogue hands over the source of a file');
    }

    # The upload root is the whole upload tree and nothing above it, whatever spelling the client sends the path in
    #[Test]
    public function nothingAboveTheUploadRootIsReachable(): void
    {
        $man = $this->getManager();
        file_put_contents(self::$work.'/outside.txt', "out\n");
        foreach (['../outside.txt', '..\\outside.txt', 'files/../../outside.txt', '/etc/passwd', 'C:/windows/win.ini', 'php://input', "files/no\0.txt"] as $path) {
            $this->assertSame([], $man->getFileData($path), 'The path '.$path.' answered a descriptor of its own');
            $this->assertFalse($man->checkFileAccess($path, 'read'), 'The path '.$path.' was allowed to be read');
            $this->assertFalse($man->checkFileAccess($path, 'delete'), 'The path '.$path.' was allowed to be deleted');
        }
        $this->assertSame([], $man->getFileList('../'), 'The listing of the parent of the root answered entries');
        $this->assertFileExists(self::$work.'/outside.txt', 'A refused path still reached the object above the root');
    }

    # A directory of the tree lists what stands in it, with the partials of the upload service closed in every context
    #[Test]
    public function theListingHidesThePartialsOfTheService(): void
    {
        $names = array_column($this->getManager()->getFileList('files'), 'name');
        $this->assertContains('plain.txt', $names, 'The listing of a directory of the tree is missing a file standing in it');
        $this->assertContains('thumb', $names, 'The listing of a directory of the tree is missing the directory standing in it');
        $this->assertNotContains('.upload-ab.part', $names, 'A partial of the upload service is listed as an object of the catalogue');
        $this->assertSame([], $this->getManager()->getFileData('files/.upload-ab.part'), 'A partial of the upload service answers a descriptor');
        $this->assertFalse($this->getManager()->checkFileAccess('files/.upload-ab.part', 'download'), 'A partial of the upload service can be downloaded');
    }

    # The descriptor of a stored file names its owner through the one parser of the project, and a name outside the managed format carries none
    #[Test]
    public function aStoredNameAnswersItsOwnerThroughTheOneParser(): void
    {
        $one = $this->getManager()->getFileData('files/note-abcdefghij-7.txt');
        $this->assertTrue($one['managed'], 'A file published by the upload service is not recognised as one');
        $this->assertSame('7', FileManager::getFileOwner($one['name']), 'The owner segment of a stored name is not answered');
        $free = $this->getManager()->getFileData('files/free-abcdefghij.txt');
        $this->assertTrue($free['managed'], 'A stored name without an owner segment is not recognised as a managed one');
        $this->assertNull(FileManager::getFileOwner($free['name']), 'A stored name without an owner segment answers an owner');
        $this->assertFalse($this->getManager()->getFileData('files/plain.txt')['managed'], 'A name outside the format is taken for a managed one');
    }

    # The descriptor of the catalogue carries the absolute path for the administration and never the one thing the editor context must not see
    #[Test]
    public function theCatalogueDescriptorCarriesTheServerPath(): void
    {
        $one = $this->getManager()->getFileData('files/plain.txt');
        $this->assertArrayHasKey('realpath', $one, 'The administrative descriptor lost the absolute path §7 grants it');
        $this->assertSame(self::$root.'/files/plain.txt', $one['realpath'], 'The absolute path of the descriptor names another object');
        $this->assertArrayNotHasKey('realpath', (new FileManager('editor', self::$root))->getFileData('files/plain.txt'), 'The editor context receives an absolute path');
        $this->assertArrayNotHasKey('critical', $one, 'The catalogue marks a file of the upload tree as one whose loss stops the site');
    }

    # Deleting one stored file takes it away and leaves everything else standing, and a directory holding objects is refused by its own name
    #[Test]
    public function aStoredFileIsDeletedAndAFilledDirectoryIsNot(): void
    {
        $man = $this->getManager();
        $this->assertTrue($man->deleteFileEntry('files/free-abcdefghij.txt')['ok'], 'A stored file of the catalogue was not deleted');
        $this->assertFileDoesNotExist(self::$root.'/files/free-abcdefghij.txt');
        $this->assertFileExists(self::$root.'/files/plain.txt', 'The delete took a second object with it');
        $this->assertSame('filled', $man->deleteFileEntry('files')['error'], 'A directory holding objects was deleted');
        $this->assertSame('closed', $man->deleteFileEntry('')['error'], 'The root of the context deleted itself');
    }

    # A thumbnail published beside a picture is found by the descriptor, so a listing of photographs asks for the small copy and not for the original
    #[Test]
    public function aStoredThumbnailIsFoundBesideItsFile(): void
    {
        $code = $this->getFile('core/classes/filemanager.php');
        $near = 'The thumbnail of a picture is looked for somewhere other than the additional directory';
        $this->assertStringContainsString("dirname(\$full).'/thumb/'.basename(\$full)", $code, $near);
        $shot = $this->getBody('core/admin.php', 'getAdminFileShot');
        $this->assertStringContainsString("\$one['thumbnail']", $shot, 'The listing never asks for the stored thumbnail');
        $this->assertStringContainsString("\$one['url']", $shot, 'A public file of the upload tree is served through the route instead of by the web server');
    }

    # The marked set is packed into one archive that carries every member once, and the archive is an archive whatever name the administration asked for
    #[Test]
    public function theMarkedSetIsPackedIntoOneArchive(): void
    {
        if (!class_exists('ZipArchive')) $this->markTestSkipped('The build carries no ZipArchive');
        $man = $this->getManager();
        $res = $man->addFilesArchive(['files/plain.txt', 'files/note-abcdefghij-7.txt'], 'files/set');
        $this->assertTrue($res['ok'], 'The marked set was not packed: '.var_export($res['error'], true));
        $this->assertSame('files/set.zip', $res['path'], 'The archive of the set is not the object the answer names');
        $zip = new ZipArchive();
        $this->assertTrue($zip->open(self::$root.'/files/set.zip') === true, 'The archive of the set does not open');
        $this->assertSame(2, $zip->numFiles, 'The archive of the set does not hold one entry per marked file');
        $this->assertNotFalse($zip->locateName('plain.txt'), 'A marked file is missing from the archive of the set');
        $this->assertSame("three\n", $zip->getFromName('plain.txt'), 'The entry of the archive does not carry the content of its file');
        $zip->close();
        $this->assertFileExists(self::$root.'/files/plain.txt', 'Packing the set took its members away');
    }

    # Two members of one set carrying the same name both survive, because an entry overwriting another makes the archive shorter than the set that was marked
    #[Test]
    public function twoMarkedFilesOfOneNameBothSurvive(): void
    {
        if (!class_exists('ZipArchive')) $this->markTestSkipped('The build carries no ZipArchive');
        $res = $this->getManager()->addFilesArchive(['files/note-abcdefghij-7.txt', 'files/thumb/note-abcdefghij-7.txt'], 'files/twin.zip');
        $this->assertTrue($res['ok'], 'A set of two files of one name was refused: '.var_export($res['error'], true));
        $zip = new ZipArchive();
        $zip->open(self::$root.'/files/twin.zip');
        $this->assertSame(2, $zip->numFiles, 'One member of the set overwrote the other inside the archive');
        $this->assertNotFalse($zip->locateName('files/thumb/note-abcdefghij-7.txt'), 'The repeated name did not carry its own path into the archive');
        $zip->close();
    }

    # An archive is refused everything the policy refuses: a closed member, a name already taken, a set of nothing and a destination outside the root
    #[Test]
    public function theArchiveOfASetObeysTheSamePolicy(): void
    {
        $man = $this->getManager();
        $this->assertSame('closed', $man->addFilesArchive([], 'files/empty.zip')['error'], 'A set of nothing was packed');
        $this->assertSame('closed', $man->addFilesArchive(['files/.upload-ab.part'], 'files/part.zip')['error'], 'A partial of the upload service was packed');
        $this->assertSame('closed', $man->addFilesArchive(['files/plain.txt'], '../away.zip')['error'], 'An archive was written above the root of the context');
        $this->assertSame('closed', $man->addFilesArchive(['files'], 'files/dir.zip')['error'], 'A directory was packed as if it were a file');
        $this->assertFileDoesNotExist(self::$work.'/away.zip', 'The archive that walked out of the root was written anyway');
        if (!class_exists('ZipArchive')) $this->markTestSkipped('The build carries no ZipArchive');
        $this->assertSame('files/evil.php.zip', $man->addFilesArchive(['files/plain.txt'], 'files/evil.php')['path'], 'A set was packed into a name the web server would execute');
        $this->assertFileDoesNotExist(self::$root.'/files/evil.php', 'The archive of the set was written under an active extension');
        $this->assertTrue($man->addFilesArchive(['files/plain.txt'], 'files/one.zip')['ok'], 'A set of one file was refused');
        $this->assertSame('exists', $man->addFilesArchive(['files/plain.txt'], 'files/one.zip')['error'], 'An archive was written over an archive of the same name');
        $this->assertSame("three\n", file_get_contents(self::$root.'/files/plain.txt'), 'A refused pack changed the file it was refused over');
    }

    # A context that cannot pack does not pack, so a screen offering no archive cannot be talked into building one through its route
    #[Test]
    public function aContextWithoutTheCapabilityPacksNothing(): void
    {
        $man = new FileManager('editor', self::$root);
        $this->assertSame('closed', $man->addFilesArchive(['files/plain.txt'], 'files/set.zip')['error'], 'A context without the capability packed a set');
        $this->assertFileDoesNotExist(self::$root.'/files/set.zip', 'The refused pack wrote its archive anyway');
    }

    # Which of the two areas a request works in is decided once and by the server, and each of the two roots is named in exactly one place
    #[Test]
    public function theAreaOfTheScreenDecidesTheRoot(): void
    {
        $body = $this->getBody('core/admin.php', 'getAdminFileManager');
        $this->assertStringContainsString("new FileManager('uploads', UPLOADS_DIR)", $body, 'The catalogue is not rooted at the upload tree');
        $this->assertStringContainsString("new FileManager('system', BASE_DIR)", $body, 'The system area is not rooted at the site');
        $code = $this->getFile('core/admin.php');
        $same = 'A file context is built outside the one accessor, so a second place names a root of its own';
        $this->assertSame(substr_count($body, 'new FileManager('), substr_count($code, 'new FileManager('), $same);
        $mode = $this->getBody('core/admin.php', 'getAdminFileMode');
        $this->assertStringContainsString("getVar('get', 'ctx'", $mode, 'The area of a reading route is not read off the request');
        $this->assertStringContainsString("getVar('post', 'ctx'", $mode, 'The area of a writing route is not read off the request');
        $this->assertStringContainsString("getAdminFileMode('uploads')", $this->getBody('admin/modules/uploads.php', 'uploads'), 'The catalogue screen does not name its own area');
        $this->assertStringContainsString("getAdminFileMode('system')", $this->getBody('admin/modules/uploads.php', 'sysfiles'), 'The system screen does not name its own area');
    }

    # No address of the module changes a file any more: the quick lists answer a directory, and every operation of §17 has one POST route of its own
    #[Test]
    public function noAddressOfTheModuleChangesAFile(): void
    {
        $body = $this->getBody('core/admin.php', 'getAdminUploadFiles');
        foreach (['unlink(', 'addCompress(', 'rename(', 'rmdir('] as $call) {
            $this->assertStringNotContainsString($call, $body, 'The quick list of the module still changes the filesystem on a GET');
        }
        $this->assertStringNotContainsString('cid=1', $body, 'The quick list of the module still carries an address that packs a file');
        $this->assertStringNotContainsString('cid=0', $body, 'The quick list of the module still carries an address that deletes a file');
        $mod = $this->getFile('admin/modules/uploads.php');
        foreach (['fmcreate', 'fmmkdir', 'fmrename', 'fmcopy', 'fmmove', 'fmdelete', 'fmcompress', 'fmpack', 'fmupload'] as $name) {
            $this->assertStringContainsString("case '".$name."': ".$name."(); break;", $mod, 'The operation '.$name.' has no route of its own');
        }
        $this->assertStringNotContainsString('uploadsave', $mod, 'The upload route that ran beside the file manager is still wired');
    }

    # The upload route checks the scoped token before the service is reached, and it publishes through the accessor and never through a class of its own
    #[Test]
    public function theUploadRouteGuardsThePublishCall(): void
    {
        $body = $this->getBody('admin/modules/uploads.php', 'fmupload');
        $gate = strpos($body, "checkAdminPost('uploads')");
        $call = strpos($body, 'getUploadService()->add');
        $this->assertNotFalse($gate, 'The upload route of the catalogue checks no token at all');
        $this->assertNotFalse($call, 'The upload route of the catalogue does not publish through the service');
        $this->assertLessThan($call, $gate, 'The upload route reaches the upload service before it checks its token');
        $this->assertStringContainsString('unset($_POST[', $body, 'The token stays in the request array, so the journal writes a live credential');
        $this->assertStringContainsString('Logger::addFile(', $body, 'An upload of the catalogue leaves no journal entry at all');
        $this->assertStringNotContainsString("getVar('get'", $body, 'The upload route reads a value of its own out of the query string');
        $this->assertStringContainsString('getAdminUploadRule(', $body, 'The upload route builds a rule of its own instead of the one of the module');
    }

    # A marked set travels in the body of the POST, every path of it is journalled on its own, and the same routes serve one object and many
    #[Test]
    public function theMarkedSetTravelsInTheBodyAndIsJournalled(): void
    {
        $body = $this->getBody('admin/modules/uploads.php', 'setFileAction');
        $this->assertStringContainsString("getVar('post', 'mark[]'", $body, 'The marked set is read from somewhere other than the body of the POST');
        $late = 'The marked set is worked through before the token of the request is checked';
        $this->assertLessThan(strpos($body, 'setFileActions('), strpos($body, "checkAdminPost('uploads')"), $late);
        $many = $this->getBody('admin/modules/uploads.php', 'setFileActions');
        $this->assertStringContainsString('Logger::addFile(', $many, 'An operation over a marked set leaves no journal entry at all');
        foreach (['admin', 'ctx', 'op', 'path', 'target', 'result'] as $key) {
            $this->assertStringContainsString("'".$key."' =>", $many, 'The journal entry of a marked set names no '.$key);
        }
        $this->assertStringContainsString('foreach ($runs as $i => $res)', $many, 'The journal of a marked set does not write one entry per member');
        $this->assertStringContainsString('addFilesArchive(', $many, 'The marked set cannot be packed into one archive');
    }

    # A fan answered by a fragment names forms of its own: a fragment counts from one again, and slpost1 in a swapped list would own the first form of the page around it
    # The defect is invisible until a list arrives by itself: the button then submits a form of the sidebar, and one of them changes the state of a module
    #[Test]
    public function aFanOfAFragmentNamesNoFormOfThePage(): void
    {
        $body = $this->getBody('core/helpers.php', 'getTplPostAction');
        $this->assertStringNotContainsString("'slpost'.(++\$seq)", $body, 'The identifier of a fan form is a bare counter, so a fragment repeats the identifiers of its page');
        $this->assertStringContainsString('random_bytes(', $body, 'The identifier of a fan form carries no mark of the answer it was built in');
        $this->assertStringContainsString('$salt.(++$seq)', $body, 'The identifier of a fan form is no longer unique inside one answer');
        $acts = $this->getBody('core/admin.php', 'getAdminFileActs');
        $this->assertStringContainsString('getTplPostAction(', $acts, 'A changing action of the fan is an address again instead of a form of its own');
    }

    # What the page takes off the screen stays off it: the reset of both themes answers the attribute, and no component keeps a hidden element visible with a display of its own
    # The rule is one and not one per class: the browser hides a panel, a queue, a skeleton and a list by the same attribute, and every one of them carries a display in its class
    #[Test]
    public function theAttributeThatHidesBeatsEveryClass(): void
    {
        foreach (['admin', 'lite'] as $skin) {
            $base = $this->getFile('templates/'.$skin.'/assets/css/base.css');
            $note = 'The theme '.$skin.' has no reset for the attribute that hides, so a class with a display keeps a hidden element on the screen';
            $this->assertMatchesRegularExpression('#\[hidden\] \{\s*display: none !important;#', $base, $note);
            $theme = $this->getFile('templates/'.$skin.'/assets/css/theme.css');
            $one = 'The theme '.$skin.' patches the same trap class by class again beside the one reset of its base';
            $this->assertDoesNotMatchRegularExpression('#\.[a-z0-9-]+\[hidden\] \{\s*display: none;\s*\}#', $theme, $one);
        }
    }

    # The list answers four states and not one: it is there, it is on its way, it is empty for a reason, and it never arrived (§31)
    #[Test]
    public function theListNamesTheStateItIsIn(): void
    {
        $part = $this->getFile('templates/admin/partials/file-browser-list.html');
        foreach (['sl-skel', 'data-sl-fm-fail', 'data-sl-fm-real', 'sl-fm-empty'] as $mark) {
            $this->assertStringContainsString($mark, $part, 'The list of the browser carries no '.$mark.', so one state of §31 has nowhere to be shown');
        }
        $code = $this->getFile('templates/admin/assets/js/admin-ui.js');
        foreach (['htmx:beforeRequest', 'htmx:responseError', 'htmx:sendError'] as $hook) {
            $this->assertStringContainsString($hook, $code, 'Nothing listens for '.$hook.', so the browser cannot tell a list on its way from one that never arrived');
        }
        $shell = $this->getBody('core/admin.php', 'getAdminFileShell');
        foreach (['_UPLOADS_FAIL', '_UPLOADS_FAILTXT', '_RETRY', '_UPLOADS_EMPTY', '_UPLOADS_NOFIND'] as $word) {
            $this->assertStringContainsString($word, $shell, 'The state of the list is drawn without '.$word.', so one of them speaks with the words of another');
        }
    }

    # The rule of the upload takes the module out of the path it publishes into, and the shared quota of that module is not a limit on the administration
    #[Test]
    public function theRuleOfTheUploadComesFromThePath(): void
    {
        $body = $this->getBody('core/admin.php', 'getAdminUploadRule');
        $this->assertStringContainsString("explode('/', \$dir)[0]", $body, 'The module of an upload is taken from somewhere other than the path it goes into');
        $this->assertStringContainsString('getUploadRuleData(', $body, 'The upload rule is built beside the settings of the module');
        $this->assertStringContainsString("'maxquota' => 0", $body, 'The shared quota of the module is applied to the administration');
    }
}
