<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stages 2a and 2b of docs/FILE-MANAGER-CONCEPT-2026.md: the reading half of FileManager and the managed name
 * format that moved into it from the upload service. The class is driven against a disposable tree below the
 * system temp directory that carries one object of every shape the plan names - a normal
 * file, a directory, a UTF-8 name, a hidden file, an empty file, an unreadable file, an unwritable directory, both
 * symlink directions and the closed paths of the policy table. Nothing below the site is written; the two tests that
 * need a real document root read the repository and never change it.
 */
final class FileManagerPathTest extends TestCase
{
    private const CLOSED = ['.git/config', '.env', 'storage/sessions/sess_probe', 'files/.upload-aaaa.part'];

    private static string $work = '';
    private static string $root = '';
    private static array $made = [];
    private static array $links = [];

    # Build the disposable tree once for the whole class, and record which of the platform-dependent fixtures could be created at all
    public static function setUpBeforeClass(): void
    {
        if (!class_exists('FileManager', false)) require_once dirname(__DIR__, 2).'/core/classes/filemanager.php';
        self::$work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_filemanager';
        self::$root = self::$work.'/root';
        self::deleteTestTree(self::$work);
        $dirs = ['root/files/thumb', 'root/.git', 'root/config', 'root/core', 'root/storage/logs', 'root/storage/sessions', 'root/templates', 'root/files/readonly', 'away'];
        foreach ($dirs as $dir) {
            mkdir(self::$work.'/'.$dir, 0777, true);
        }
        $rows = [
            'index.php' => '<?php echo 1;', '.htaccess' => 'Options -Indexes', '.env' => 'SECRET=1', '.git/config' => '[core]', 'config/main.php' => '<?php $conf = [];',
            'core/system.php' => '<?php', 'storage/logs/error_php.log' => 'line', 'storage/sessions/sess_probe' => 'payload', 'templates/theme.css' => 'body{color:red}',
            'files/note.md' => '# note', 'files/empty.txt' => '', 'files/.hidden' => 'dot', 'files/пример.txt' => 'utf', 'files/.upload-aaaa.part' => 'partial',
            'files/pack.zip' => "PK\x03\x04", 'files/locked.txt' => 'secret', 'files/readonly/kept.txt' => 'kept',
            'files/news-abcdefghij-42.txt' => 'owned', 'files/news-abcdefghij.txt' => 'unowned',
        ];
        foreach ($rows as $file => $body) file_put_contents(self::$root.'/'.$file, $body);
        self::$made = ['image' => self::addTestImage(), 'big' => self::addTestBig(), 'locked' => self::addTestLocked(), 'readonly' => self::addTestReadonly()];
        self::$made += self::addTestLinks();
    }

    # Leave nothing of the disposable tree behind: the links go first, so no walk of the tree ever descends through one into what it points at
    public static function tearDownAfterClass(): void
    {
        chmod(self::$root.'/files/locked.txt', 0666);
        chmod(self::$root.'/files/readonly', 0777);
        foreach (self::$links as $link) {
            is_link($link) ? unlink($link) : rmdir($link);
        }
        self::deleteTestTree(self::$work);
    }

    # Remove one directory with everything below it, links included and never followed
    private static function deleteTestTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $dir.'/'.$file;
            if (is_link($path)) {
                if (!unlink($path)) rmdir($path);
                continue;
            }
            is_dir($path) ? self::deleteTestTree($path) : unlink($path);
        }
        rmdir($dir);
    }

    # One real PNG with a thumbnail beside it, or nothing on a build without an encoder
    private static function addTestImage(): bool
    {
        if (!function_exists('imagepng')) return false;
        $img = imagecreatetruecolor(40, 30);
        imagefilledrectangle($img, 0, 0, 39, 29, imagecolorallocate($img, 20, 120, 200));
        imagepng($img, self::$root.'/files/pic.png');
        imagepng($img, self::$root.'/files/thumb/pic.png');
        return is_file(self::$root.'/files/pic.png');
    }

    # One source file past the editor limit, written without holding it in memory
    private static function addTestBig(): bool
    {
        $hand = fopen(self::$root.'/templates/big.css', 'wb');
        if ($hand === false) return false;
        fseek($hand, 2097152);
        fwrite($hand, 'x');
        fclose($hand);
        return (int)filesize(self::$root.'/templates/big.css') > 2097152;
    }

    # One file the process may not read, which Windows will not produce through chmod
    private static function addTestLocked(): bool
    {
        chmod(self::$root.'/files/locked.txt', 0000);
        clearstatcache();
        return !is_readable(self::$root.'/files/locked.txt');
    }

    # One directory the process may not write into, which Windows will not produce through chmod either
    private static function addTestReadonly(): bool
    {
        chmod(self::$root.'/files/readonly', 0555);
        clearstatcache();
        return !is_writable(self::$root.'/files/readonly');
    }

    # Three links: one below the root, one out of it and one at a closed path; a Windows host without the symlink privilege gets a junction, which realpath() resolves the same way
    private static function addTestLinks(): array
    {
        $out = [];
        foreach (['inside' => self::$root.'/files', 'escape' => self::$work.'/away', 'peek' => self::$root.'/storage/sessions'] as $name => $dest) {
            $link = self::$root.'/'.$name;
            set_error_handler(static fn(): bool => true);
            try {
                $done = symlink($dest, $link);
            } finally {
                restore_error_handler();
            }
            if (!$done && DIRECTORY_SEPARATOR === '\\') {
                shell_exec('cmd /c mklink /J '.escapeshellarg(str_replace('/', '\\', $link)).' '.escapeshellarg(str_replace('/', '\\', $dest)).' 2>&1');
                $done = is_dir($link);
            }
            if ($done) self::$links[] = $link;
            $out[$name] = $done;
        }
        return $out;
    }

    # One context over the disposable tree
    private function getManager(string $mode = 'system', array $flags = []): \FileManager
    {
        return new \FileManager($mode, self::$root, $flags);
    }

    # Not one spelling of a path that leaves the context is accepted, and neither is a path that never resolves
    #[Test]
    public function noPathOutsideTheContextIsAccepted(): void
    {
        $rows = [
            'traversal' => '../away/x.txt',
            'nested' => 'files/../../away/x.txt',
            'windows' => '..\\away\\x.txt',
            'dot' => '.',
            'updot' => '..',
            'unix' => '/etc/passwd',
            'drive' => 'C:/Windows/win.ini',
            'unc' => '\\\\server\\share\\x.txt',
            'nul' => "files\x00/pic.png",
            'control' => "files/\x1fpic.png",
            'wrapper' => 'php://filter/resource=index.php',
            'local' => 'file:///etc/passwd',
            'data' => 'data://text/plain,slaed',
            'stream' => 'zip://files/pack.zip',
            'absolute' => self::$root.'/index.php',
            'empties' => 'files//pic.png',
            'noparent' => 'nosuchdir/x.txt',
            'gone' => 'files/nosuch.txt',
        ];
        $fm = $this->getManager();
        foreach ($rows as $key => $path) {
            $this->assertSame([], $fm->getFileData($path), 'The '.$key.' path answered a descriptor');
            foreach (\FileManager::OPS as $op) {
                $this->assertFalse($fm->checkFileAccess($path, $op), 'The '.$key.' path was allowed to '.$op);
            }
        }
    }

    # A plain relative path resolves to its own object, and the descriptor names the path the client passed rather than the one the server holds
    #[Test]
    public function aPlainRelativePathResolvesInsideTheRoot(): void
    {
        if (!self::$made['image']) $this->markTestSkipped('This build cannot encode a PNG fixture');
        $data = $this->getManager()->getFileData('files/pic.png');
        $this->assertSame('files/pic.png', $data['path'], 'The descriptor does not carry the relative path it was asked for');
        $this->assertSame('pic.png', $data['name'], 'The descriptor does not carry the file name');
        $this->assertSame('image', $data['kind'], 'A PNG is not answered as an image');
        $this->assertSame('png', $data['extension'], 'The extension is not normalized');
        $this->assertSame([40, 30], [$data['width'], $data['height']], 'The pixel size of an image is not read');
        $this->assertGreaterThan(0, $data['size'], 'The file size is not read');
        $this->assertGreaterThan(0, $data['mtime'], 'The change time is not read');
        $this->assertTrue($data['previewable'], 'An image is not previewable');
        $this->assertFalse($data['editable'], 'An image opens in the source editor');
    }

    # The root of a context is written into but never taken away: an upload and a new directory land in it, and it is itself never downloaded, renamed or removed
    #[Test]
    public function theRootOfAContextIsWrittenIntoButNeverTakenAway(): void
    {
        $fm = $this->getManager();
        foreach (['list', 'read', 'write'] as $op) {
            $this->assertTrue($fm->checkFileAccess('', $op), 'The root of the context may not be '.$op.'ed');
        }
        foreach (['download', 'delete'] as $op) {
            $this->assertFalse($fm->checkFileAccess('', $op), 'The root of the context may be '.$op.'d');
        }
        $this->assertNotEmpty($fm->getFileList(''), 'The root of the context lists nothing at all');
        $grant = $this->getManager('uploads')->getFileData('')['capabilities'];
        $this->assertSame(['browse', 'upload', 'mkdir'], $this->getGrantNames($grant), 'The root of an upload context does not carry exactly what may be done to it');
    }

    # A link is judged by where it leads: one below the root is an ordinary directory, one that leaves it is refused, and one at a closed path is as closed as that path
    # The descriptor of an object reached through a link carries the resolved path, so the policy and both views of the same object always speak about one identity
    #[Test]
    public function aSymlinkIsJudgedByWhereItLeads(): void
    {
        if (!self::$made['inside'] || !self::$made['escape'] || !self::$made['peek']) $this->markTestSkipped('This host does not allow the test process to create links');
        $fm = $this->getManager();
        $this->assertNotEmpty($fm->getFileList('inside'), 'A link inside the root does not resolve');
        $this->assertSame('files/note.md', $fm->getFileData('inside/note.md')['path'], 'An object reached through a link does not carry its resolved path');
        $this->assertSame([], $fm->getFileData('escape'), 'A link out of the root answers a descriptor');
        $this->assertSame([], $fm->getFileList('escape'), 'A link out of the root can be listed');
        $this->assertSame([], $fm->getFileList('peek'), 'A link to the closed session store lists it');
        foreach (\FileManager::OPS as $op) {
            $this->assertFalse($fm->checkFileAccess('escape', $op), 'A link out of the root was allowed to '.$op);
            $this->assertFalse($fm->checkFileAccess('peek/sess_probe', $op), 'A link to the closed session store was allowed to '.$op);
        }
    }

    # A closed path stays closed under a second spelling: a filesystem that ignores case hands out the same file, and the policy is asked about what the disk resolved to
    #[Test]
    public function aClosedPathStaysClosedUnderASecondSpelling(): void
    {
        $fm = $this->getManager();
        foreach (['.ENV', '.Git/config', 'storage/SESSIONS/sess_probe', 'STORAGE/Sessions'] as $path) {
            $this->assertSame([], $fm->getFileData($path), 'The second spelling '.$path.' answered a descriptor');
            foreach (\FileManager::OPS as $op) {
                $this->assertFalse($fm->checkFileAccess($path, $op), 'The second spelling '.$path.' was allowed to '.$op);
            }
        }
    }

    # The descriptor is one shape, and the absolute path is the one field an administrative context adds; the editor window never receives it in any field
    #[Test]
    public function theDescriptorIsOneShapeAndTheEditorNeverSeesTheServerPath(): void
    {
        $keys = ['name', 'path', 'kind', 'extension', 'size', 'mtime', 'url', 'thumbnail', 'width', 'height', 'managed', 'editable', 'previewable', 'capabilities'];
        $edit = $this->getManager('editor', ['list' => true])->getFileData('files/note.md');
        $this->assertSame($keys, array_keys($edit), 'The editor descriptor is not the model of the plan');
        $this->assertStringNotContainsString(self::$root, json_encode($edit), 'The editor descriptor carries the server path of the file');
        $upl = $this->getManager('uploads')->getFileData('files/note.md');
        $this->assertSame([...$keys, 'realpath'], array_keys($upl), 'The administrative descriptor does not add the absolute path and nothing else');
        $this->assertSame(self::$root.'/files/note.md', $upl['realpath'], 'The absolute path of the administrative descriptor is not the canonical one');
        $sys = $this->getManager()->getFileData('files/note.md');
        $this->assertSame([...$keys, 'realpath', 'critical'], array_keys($sys), 'The system descriptor does not mark whether the path is a critical one');
    }

    # Every closed path of the policy table is refused on each of the five operations, not merely left out of the listing
    #[Test]
    public function everyClosedPathIsRefusedOnEveryOperation(): void
    {
        $fm = $this->getManager();
        foreach (self::CLOSED as $path) {
            $this->assertSame([], $fm->getFileData($path), 'The closed path '.$path.' answered a descriptor');
            foreach (\FileManager::OPS as $op) {
                $this->assertFalse($fm->checkFileAccess($path, $op), 'The closed path '.$path.' was allowed to '.$op);
            }
        }
        $this->assertSame([], $fm->getFileList('.git'), 'The closed directory .git can be listed');
        $this->assertSame([], $fm->getFileList('storage/sessions'), 'The closed session store can be listed');
    }

    # A closed path is also absent from every listing that would otherwise show it, which is the convenience half of the same policy
    #[Test]
    public function noClosedPathAppearsInAListing(): void
    {
        $fm = $this->getManager();
        $this->assertSame([], array_intersect(['.env', '.git'], $this->getListNames($fm->getFileList(''))), 'A closed path of the root is listed');
        $this->assertNotContains('sessions', $this->getListNames($fm->getFileList('storage')), 'The closed session store is listed');
        $this->assertNotContains('.upload-aaaa.part', $this->getListNames($fm->getFileList('files')), 'A partial of the upload service is listed');
    }

    # The log row of the table is readable and removable but never writable, and the configuration row stays open and is marked critical instead
    #[Test]
    public function eachOpenRowOfTheTableAnswersItsOwnColumns(): void
    {
        $fm = $this->getManager();
        foreach (['list', 'read', 'download', 'delete'] as $op) {
            $this->assertTrue($fm->checkFileAccess('storage/logs/error_php.log', $op), 'A log file may not be '.$op.'ed');
        }
        $this->assertFalse($fm->checkFileAccess('storage/logs/error_php.log', 'write'), 'A log file may be written through the browser');
        foreach (\FileManager::OPS as $op) {
            $this->assertTrue($fm->checkFileAccess('config/main.php', $op), 'A configuration file may not be '.$op.'ed');
        }
        $this->assertTrue($fm->getFileData('config/main.php')['critical'], 'A configuration file is not marked critical');
        $this->assertTrue($fm->getFileData('index.php')['critical'], 'The front controller is not marked critical');
        $this->assertTrue($fm->getFileData('core/system.php')['critical'], 'A file of the core is not marked critical');
        $this->assertFalse($fm->getFileData('templates/theme.css')['critical'], 'An ordinary template file is marked critical');
    }

    # The system table belongs to the system context alone: an upload context knows no .env row, and the artifacts of the writers are closed in every context
    #[Test]
    public function theSystemTableBelongsToTheSystemContext(): void
    {
        $upl = $this->getManager('uploads');
        $this->assertTrue($upl->checkFileAccess('storage/logs/error_php.log', 'write'), 'The upload context applies a row of the system table');
        $this->assertTrue($upl->checkFileAccess('files/note.md', 'delete'), 'The upload context refuses to delete an ordinary file');
        foreach (['uploads', 'editor', 'system'] as $mode) {
            $this->assertFalse((new \FileManager($mode, self::$root))->checkFileAccess('files/.upload-aaaa.part', 'read'), 'The '.$mode.' context opens a partial');
        }
    }

    # An unknown mode and an unresolvable root leave a closed context, so a miswired route browses nothing rather than everything
    #[Test]
    public function aContextThatDidNotBuildRefusesEverything(): void
    {
        foreach ([new \FileManager('nosuchmode', self::$root), new \FileManager('system', self::$root.'/nosuchdir')] as $fm) {
            $this->assertSame([], $fm->getFileList(''), 'A closed context lists objects');
            $this->assertSame([], $fm->getFileData('index.php'), 'A closed context answers a descriptor');
            $this->assertFalse($fm->checkFileAccess('index.php', 'read'), 'A closed context allows reading');
            $this->assertSame([], array_filter($fm->getCapabilities()), 'A closed context allows a capability');
        }
    }

    # Each context answers the capability table of the plan, and the editor answers the flags the route computed from the module rule instead of a role of its own
    #[Test]
    public function everyContextAnswersItsOwnCapabilities(): void
    {
        $upl = $this->getManager('uploads')->getCapabilities();
        $this->assertSame(\FileManager::GRANTS, array_keys($upl), 'A context does not answer one shape of capabilities');
        $want = ['browse', 'preview', 'upload', 'download', 'mkdir', 'rename', 'copy', 'move', 'delete', 'compress'];
        $this->assertSame($want, $this->getGrantNames($upl), 'The administrative catalogue does not match the plan');
        $sys = $this->getManager()->getCapabilities();
        $want = ['browse', 'preview', 'download', 'edit', 'create', 'mkdir', 'rename', 'copy', 'move', 'delete', 'compress'];
        $this->assertSame($want, $this->getGrantNames($sys), 'The system context does not match the plan');
        $full = $this->getManager('editor', ['list' => true, 'upload' => true, 'embed' => true, 'moder' => true])->getCapabilities();
        $want = ['browse', 'preview', 'upload', 'download', 'insert', 'embed', 'delete', 'compress'];
        $this->assertSame($want, $this->getGrantNames($full), 'The moderator window does not match the plan');
        $lean = $this->getManager('editor', ['list' => true])->getCapabilities();
        $this->assertSame(['browse', 'preview', 'download', 'insert'], $this->getGrantNames($lean), 'The author window does not match the plan');
        foreach (['upload' => 'upload', 'embed' => 'embed', 'list' => 'browse', 'moder' => 'delete'] as $flag => $grant) {
            $this->assertFalse($lean[$grant] && $grant !== 'browse', 'The window of an author already carries '.$grant.' without its flag');
            $this->assertTrue($this->getManager('editor', [$flag => true])->getCapabilities()[$grant], 'The '.$flag.' flag does not open '.$grant);
        }
    }

    # The capabilities of one object are the capabilities of the context narrowed by what the object is and by what the policy leaves open on its path
    #[Test]
    public function theCapabilitiesOfOneObjectNarrowToWhatItIs(): void
    {
        $sys = $this->getManager();
        $dir = $sys->getFileData('files')['capabilities'];
        $this->assertTrue($dir['browse'], 'A directory cannot be browsed');
        $this->assertFalse($dir['download'], 'A directory can be downloaded');
        $this->assertFalse($dir['edit'], 'A directory opens in the source editor');
        $this->assertFalse($dir['copy'], 'A directory can be copied, which the first version does not walk');
        $this->assertFalse($dir['compress'], 'A directory can be packed, which the first version does not walk');
        $this->assertTrue($sys->getFileData('files/note.md')['capabilities']['copy'], 'A file cannot be copied');
        $css = $sys->getFileData('templates/theme.css')['capabilities'];
        $this->assertTrue($css['edit'], 'A stylesheet of the system area cannot be edited');
        $this->assertFalse($css['browse'], 'A file can be browsed');
        $log = $sys->getFileData('storage/logs/error_php.log')['capabilities'];
        $this->assertFalse($log['edit'], 'A log file the table closes for writing can still be saved');
        $this->assertTrue($log['delete'], 'A log file cannot be removed');
        if (self::$made['image']) {
            $fm = $this->getManager('editor', ['list' => true, 'embed' => true]);
            $this->assertTrue($fm->getFileData('files/pic.png')['capabilities']['embed'], 'An image cannot be embedded');
            $this->assertFalse($fm->getFileData('files/note.md')['capabilities']['embed'], 'A text file can be embedded');
        }
    }

    # The highlighting mode follows the extension resolver of the plan, and everything it does not name carries none at all
    #[Test]
    public function theHighlightingModeFollowsTheExtension(): void
    {
        $rows = [
            'a.php' => 'php', 'a.html' => 'html', 'a.htm' => 'html', 'a.tpl' => 'html', 'a.css' => 'css', 'a.js' => 'js', 'a.json' => 'json',
            'a.sql' => 'sql', 'a.xml' => 'xml', 'a.txt' => 'text', 'a.md' => 'text', 'a.ini' => 'text', 'a.log' => 'text',
            'a.PHP' => 'php', 'dir/a.css' => 'css', 'a.png' => '', 'a.zip' => '', 'a.exe' => '', 'noext' => '', 'a.php.png' => '',
        ];
        $fm = $this->getManager();
        foreach ($rows as $path => $lang) {
            $this->assertSame($lang, $fm->getCodeLanguage($path), 'The mode of '.$path.' is not the one the resolver names');
        }
    }

    # Only the system context opens sources, and only where the file is text, readable and small enough for a code editor
    #[Test]
    public function onlyTheSystemContextOpensSources(): void
    {
        $this->assertTrue($this->getManager()->isFileEditable('templates/theme.css'), 'A stylesheet of the system area does not open in the editor');
        $this->assertTrue($this->getManager()->getFileData('templates/theme.css')['editable'], 'The descriptor disagrees with the editor decision');
        foreach (['uploads', 'editor'] as $mode) {
            $this->assertFalse((new \FileManager($mode, self::$root, ['list' => true]))->isFileEditable('templates/theme.css'), 'The '.$mode.' context opens a source file');
        }
        $fm = $this->getManager();
        $this->assertFalse($fm->isFileEditable('files/pack.zip'), 'A binary file opens in the editor');
        $this->assertFalse($fm->isFileEditable('files'), 'A directory opens in the editor');
        $this->assertFalse($fm->isFileEditable('.env'), 'A closed path opens in the editor');
        $this->assertTrue($fm->isFileEditable('files/empty.txt'), 'An empty text file does not open in the editor');
        if (self::$made['big']) $this->assertFalse($fm->isFileEditable('templates/big.css'), 'A source file past the size limit opens in the editor');
        if (self::$made['locked']) $this->assertFalse($fm->isFileEditable('files/locked.txt'), 'A file the process may not read opens in the editor');
    }

    # A UTF-8 name, a hidden file and an empty file are ordinary objects, and a file the process may not read is still described rather than hidden
    #[Test]
    public function everyOrdinaryShapeOfAFileIsDescribed(): void
    {
        $fm = $this->getManager();
        $this->assertSame('пример.txt', $fm->getFileData('files/пример.txt')['name'], 'A UTF-8 name does not resolve');
        $this->assertSame('text', $fm->getFileData('files/пример.txt')['kind'], 'A UTF-8 text file is not answered as text');
        $this->assertSame('.hidden', $fm->getFileData('files/.hidden')['name'], 'A hidden file does not resolve');
        $this->assertSame(0, $fm->getFileData('files/empty.txt')['size'], 'An empty file does not answer a size of zero');
        $this->assertContains('пример.txt', $this->getListNames($fm->getFileList('files')), 'A UTF-8 name is missing from the listing');
        $this->assertContains('.hidden', $this->getListNames($fm->getFileList('files')), 'A hidden file is missing from the listing');
        if (self::$made['locked']) $this->assertNotEmpty($fm->getFileData('files/locked.txt'), 'An unreadable file is not described at all');
        if (self::$made['readonly']) $this->assertSame(['kept.txt'], $this->getListNames($fm->getFileList('files/readonly')), 'An unwritable directory cannot be listed');
    }

    # A listing carries directories first and each group by name, so the two views of the same directory never disagree about its order
    #[Test]
    public function aListingCarriesDirectoriesFirst(): void
    {
        $rows = $this->getManager()->getFileList('files');
        $kind = array_map(static fn(array $one): string => ($one['kind'] === 'dir') ? 'dir' : 'file', $rows);
        $want = $kind;
        sort($want);
        $this->assertSame($want, $kind, 'The listing does not carry directories first');
        $name = $this->getListNames(array_values(array_filter($rows, static fn(array $one): bool => $one['kind'] !== 'dir')));
        $sort = $name;
        sort($sort);
        $this->assertSame($sort, $name, 'The files of a listing are not ordered by name');
    }

    # The address of one object is its path below the document root, and the system context answers none at all because its files are served by a route
    #[Test]
    public function theAddressOfAnObjectIsRelativeToTheDocumentRoot(): void
    {
        $upl = new \FileManager('uploads', BASE_DIR);
        $this->assertSame('index.php', $upl->getFileData('index.php')['url'], 'The address below the document root is not the relative path');
        $this->assertSame('uploads/all', $upl->getFileData('uploads/all')['url'], 'A directory below the document root carries no address');
        $this->assertSame('', (new \FileManager('system', BASE_DIR))->getFileData('index.php')['url'], 'The system context hands out a direct address');
        $this->assertSame('', $this->getManager('uploads')->getFileData('files/note.md')['url'], 'A root outside the document root answers an address');
    }

    # The managed name is taken apart here and nowhere else: the identifier of a member, the token of a guest, a name that carries no owner and a name that is not managed at all
    # Every answer is a string, because the token of a guest is alphanumeric and an integer cast would turn every one of them into zero and make one guest the owner of another
    #[Test]
    public function theManagedNameAnswersItsOwner(): void
    {
        $this->assertSame('42', \FileManager::getFileOwner('news-abcdefghij-42.txt'), 'The identifier of a member is not read out of the stored name');
        $this->assertSame('a1b2c3d4e5f6a7b8', \FileManager::getFileOwner('files/news-abcdefghij-a1b2c3d4e5f6a7b8.png'), 'The session token of a guest is not read whole');
        $this->assertNull(\FileManager::getFileOwner('news-abcdefghij.txt'), 'A name without an owner segment answers an owner');
        foreach (['note.md', '.env', 'news-abcdefghi-42.txt', 'news-abcdefghijk-42.txt', 'news-abcdefghij-42'] as $name) {
            $this->assertFalse(\FileManager::checkFileName($name), 'The name '.$name.' was accepted as a managed one');
            $this->assertNull(\FileManager::getFileOwner($name), 'The name '.$name.' answered an owner');
        }
    }

    # The length of the random segment is the one the file layer publishes, so a name drawn at that length is managed and a name one character short is not
    #[Test]
    public function theSaltLengthIsReadFromTheFileLayer(): void
    {
        $salt = str_repeat('a', \FileManager::SALTLEN);
        $this->assertTrue(\FileManager::checkFileName('news-'.$salt.'.png'), 'A name drawn at the published salt length is not managed');
        $this->assertTrue(\FileManager::checkFileName('news-'.$salt.'-42.png'), 'An owned name drawn at the published salt length is not managed');
        $this->assertFalse(\FileManager::checkFileName('news-'.substr($salt, 1).'.png'), 'A name one character short of the published salt length is managed');
    }

    # The descriptor says whether the object is a file this project stored, so no reader of a listing ever takes a stored name apart itself
    #[Test]
    public function theDescriptorMarksAManagedFile(): void
    {
        $fm = $this->getManager();
        $this->assertTrue($fm->getFileData('files/news-abcdefghij-42.txt')['managed'], 'A stored file of the upload service is not marked as managed');
        $this->assertTrue($fm->getFileData('files/news-abcdefghij.txt')['managed'], 'A stored file without an owner segment is not marked as managed');
        $this->assertFalse($fm->getFileData('files/note.md')['managed'], 'A file that was never stored by the upload service is marked as managed');
        $this->assertFalse($fm->getFileData('files')['managed'], 'A directory is marked as a managed file');
    }

    # The names of one listing, in the order the listing carries them
    private function getListNames(array $rows): array
    {
        return array_map(static fn(array $one): string => $one['name'], $rows);
    }

    # The capabilities one context grants, in the order of the shared shape, so a table of the plan is compared as a list instead of key by key
    private function getGrantNames(array $rows): array
    {
        return array_values(array_keys(array_filter($rows)));
    }
}
