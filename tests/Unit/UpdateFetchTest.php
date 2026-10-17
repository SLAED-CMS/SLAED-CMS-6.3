<?php

declare(strict_types=1);

namespace Tests\Unit;

use Database;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Update;
use ZipArchive;

# The way of a release to the site: a resumed download, an upload under the offer, the own central directory, both readers, files.json and integrity
final class UpdateFetchTest extends TestCase
{
    private const VER = '9.0.1';

    private static array $pri = [];

    private string $root = '';

    public static function setUpBeforeClass(): void
    {
        require_once BASE_DIR.'/core/classes/update.php';
        require_once BASE_DIR.'/core/classes/pdo.php';
        require_once BASE_DIR.'/core/version.php';
        $pair = sodium_crypto_sign_keypair();
        self::$pri = ['sec' => sodium_crypto_sign_secretkey($pair), 'pub' => sodium_crypto_sign_publickey($pair)];
    }

    protected function setUp(): void
    {
        $this->root = dirname(UPDATE_DIR).'/root';
        $this->setTree(['core/a.php' => "<?php\n# a\n", 'admin/b.php' => "<?php\n# b\n", 'lang/en.php' => "<?php\n# en\n", 'config/global.php' => "<?php\nreturn [];\n",
            'public/robots.txt' => "User-agent: *\n", 'modules/news/index.php' => "<?php\n# news\n"]);
        $this->setKeys();
    }

    protected function tearDown(): void
    {
        foreach ([UPDATE_DIR.'/client', UPDATE_DIR.'/files.json', $this->root, dirname(UPDATE_DIR).'/zip'] as $path) self::setGone($path);
    }

    # Trust the test key as the primary
    private function setKeys(): void
    {
        $this->assertTrue(Update::setKeyFile(['kseq' => 0, 'primary' => base64_encode(self::$pri['pub']), 'recovery' => base64_encode(self::$pri['pub']), 'revoked' => []]));
    }

    # Remove a file or a folder with all below it
    private static function setGone(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) return;
        foreach (scandir($path) ?: [] as $name) if ($name !== '.' && $name !== '..') self::setGone($path.'/'.$name);
        rmdir($path);
    }

    # Write the files of a site below the test root
    private function setTree(array $files): void
    {
        foreach ($files as $path => $data) {
            if (!is_dir(dirname($this->root.'/'.$path))) mkdir(dirname($this->root.'/'.$path), 0755, true);
            file_put_contents($this->root.'/'.$path, $data);
        }
    }

    # The files.json of a version over the given files, config/ and robots.txt marked as files of the site
    private static function getList(string $ver, array $files): string
    {
        $out = [];
        foreach ($files as $path => $data) $out[$path] = ['sha256' => hash('sha256', $data), 'size' => strlen($data)] + (Update::isSiteFile($path) ? ['site' => true] : []);
        ksort($out);
        return json_encode(['version' => $ver, 'files' => $out], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    # A new file name below the folder of test archives
    private static function getZipName(): string
    {
        $dir = dirname(UPDATE_DIR).'/zip';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return $dir.'/'.bin2hex(random_bytes(4)).'.zip';
    }

    # A release archive written by ZipArchive with deflate, its folders as entries and its files.json, as tools/release.php builds it
    private static function getPack(array $files, ?string $list = null, string $ver = self::VER): string
    {
        $file = self::getZipName();
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $dirs = [];
        foreach (array_keys($files) as $path) for ($dir = dirname($path); $dir !== '.'; $dir = dirname($dir)) $dirs[$dir] = true;
        ksort($dirs);
        foreach (array_keys($dirs) as $dir) $zip->addEmptyDir($dir);
        foreach ($files as $path => $data) $zip->addFromString($path, $data);
        $zip->addFromString('storage/update/files.json', $list ?? self::getList($ver, $files));
        $zip->close();
        return $file;
    }

    # A zip written byte by byte with stored entries, so a test sets any name, host, mode or declared size it needs
    private static function getRawPack(array $ents): string
    {
        $body = '';
        $dir = '';
        foreach ($ents as $one) {
            $one += ['data' => '', 'made' => 0x0314, 'attr' => 0100644 << 16];
            $size = strlen($one['data']);
            $crc = crc32($one['data']);
            $dir .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, $one['made'], 20, 0, 0, 0, 0x21, $crc, $size, $one['usize'] ?? $size, strlen($one['name']), 0, 0, 0, 0, $one['attr'],
                strlen($body)).$one['name'];
            $body .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0x21, $crc, $size, $size, strlen($one['name']), 0).$one['name'].$one['data'];
        }
        $file = self::getZipName();
        file_put_contents($file, $body.$dir.pack('VvvvvVVv', 0x06054b50, 0, 0, count($ents), count($ents), strlen($dir), strlen($body), 0));
        return $file;
    }

    # A decoded manifest of 9.0.1 whose full archive is $file, its unpacked size $usize or 16 MB
    private static function getMan(string $file, int $usize = 0): array
    {
        return ['type' => 'release', 'version' => self::VER, 'channel' => 'stable', 'min' => '6.3.0', 'php' => '8.4', 'security' => false,
            'full' => ['size' => (int)filesize($file), 'usize' => ($usize > 0) ? $usize : 1 << 24, 'sha256' => hash_file('sha256', $file)], 'delta' => null,
            'commit' => str_repeat('c', 40), 'time' => time(), 'expires' => time() + 86400, 'kid' => substr(Update::getKeyPrint(self::$pri['pub']), 0, 16)];
    }

    # The hashes of every file below a folder by its relative path
    private static function getHashes(string $dir): array
    {
        $out = [];
        $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($walk as $item) $out[str_replace('\\', '/', substr($item->getPathname(), strlen($dir) + 1))] = hash_file('sha256', $item->getPathname());
        ksort($out);
        return $out;
    }

    # A transport that answers once with the given status, headers and body, and records the byte it was asked from
    private static function getOpen(int $code, string $body, array $head = [], ?int &$asked = null): callable
    {
        return static function (string $path, array $query, int $from) use ($code, $body, $head, &$asked): array {
            $asked = $from;
            $link = fopen('php://memory', 'w+b');
            fwrite($link, $body);
            rewind($link);
            return ['code' => $code, 'link' => $link, 'head' => array_merge(['HTTP/1.1 '.$code.' X'], $head)];
        };
    }

    # Every forbidden entry is refused by the own central directory before a reader opens the archive, the same through ZipArchive and PharData, and nothing lands
    #[Test]
    public function theUnpackingRefusesEveryForbiddenEntry(): void
    {
        $cases = [
            'parent' => [[['name' => '../x.php', 'data' => 'x']], 'path'],
            'deep parent' => [[['name' => 'core/../../x.php', 'data' => 'x']], 'path'],
            'absolute' => [[['name' => '/x.php', 'data' => 'x']], 'path'],
            'backslash' => [[['name' => 'core\\x.php', 'data' => 'x']], 'path'],
            'drive' => [[['name' => 'C:/x.php', 'data' => 'x']], 'path'],
            'top folder' => [[['name' => 'other/x.php', 'data' => 'x']], 'path'],
            'client' => [[['name' => 'storage/update/client/keys.json', 'data' => '{}']], 'path'],
            'master' => [[['name' => 'storage/update/master/x.zip', 'data' => 'x']], 'path'],
            'symlink' => [[['name' => 'core/link.php', 'data' => '../config/db.php', 'attr' => 0120777 << 16]], 'link'],
            'twin' => [[['name' => 'core/a.php', 'data' => 'a'], ['name' => 'core/A.php', 'data' => 'b']], 'zip'],
            'bomb' => [[['name' => 'core/big.php', 'data' => 'x', 'usize' => 1 << 30]], 'usize'],
        ];
        foreach ($cases as $label => [$ents, $why]) {
            foreach (['zip', 'phar'] as $reader) {
                $file = self::getRawPack($ents);
                $res = Update::addStage($file, self::getMan($file, 1000), 'full', $reader, $this->root);
                $this->assertSame($why, $res['error'], $label.' through '.$reader);
                $this->assertFileDoesNotExist($file, 'A refused archive was kept: '.$label);
                $this->assertDirectoryDoesNotExist(UPDATE_DIR.'/client/stage/'.self::VER, 'A refused archive was unpacked: '.$label);
                $this->assertDirectoryDoesNotExist(UPDATE_DIR.'/client/stage/'.self::VER.'.part', 'A refused archive left a part: '.$label);
            }
        }
        $this->assertFileDoesNotExist(dirname(UPDATE_DIR).'/x.php');
        $file = self::getRawPack([['name' => 'core/a.php', 'data' => 'a']]);
        $man = self::getMan($file);
        $man['full']['sha256'] = str_repeat('0', 64);
        $this->assertSame('hash', Update::addStage($file, $man, 'full', 'zip', $this->root)['error'], 'An archive under another hash was opened');
        $file = self::getRawPack([['name' => 'core/a.php', 'data' => 'a']]);
        $man = self::getMan($file);
        $man['full']['size']++;
        $this->assertSame('size', Update::addStage($file, $man, 'full', 'zip', $this->root)['error'], 'An archive of another size was opened');
    }

    # An entry that yields more bytes than its central directory declares is refused while unpacking, by either reader
    #[Test]
    public function anEntryLongerThanDeclaredIsRefused(): void
    {
        foreach (['zip', 'phar'] as $reader) {
            $file = self::getRawPack([['name' => 'core/a.php', 'data' => str_repeat('a', 4096), 'usize' => 10]]);
            $res = Update::addStage($file, self::getMan($file), 'full', $reader, $this->root);
            $this->assertSame(['ok' => false, 'error' => 'entry', 'name' => 'core/a.php'], $res, $reader.' unpacked an entry longer than declared');
            $this->assertDirectoryDoesNotExist(UPDATE_DIR.'/client/stage/'.self::VER.'.part');
        }
    }

    # A release built like tools/release.php builds it unpacks to the same bytes through ZipArchive and PharData, with 0644 files, and the archive is gone after
    #[Test]
    public function bothReadersUnpackTheSameTree(): void
    {
        $files = ['core/a.php' => "<?php\n# a2\n", 'core/classes/z.php' => str_repeat("<?php\n# z\n", 500), 'public/index.php' => "<?php\n", 'readme.md' => "# x\n",
            'config/global.php' => "<?php\nreturn ['x' => 1];\n"];
        $trees = [];
        foreach (['zip', 'phar'] as $reader) {
            $file = self::getPack($files);
            $res = Update::addStage($file, self::getMan($file), 'full', $reader, $this->root);
            $this->assertSame(['ok' => true, 'error' => '', 'name' => ''], $res, $reader);
            $this->assertFileDoesNotExist($file, 'The archive stayed after unpacking');
            $dir = UPDATE_DIR.'/client/stage/'.self::VER;
            $trees[$reader] = self::getHashes($dir);
            if (DIRECTORY_SEPARATOR === '/') $this->assertSame('644', substr(sprintf('%o', fileperms($dir.'/core/a.php')), -3));
            $stage = Update::getState()['stage'];
            $this->assertSame([self::VER, 'full', 5, true], [$stage['version'], $stage['kind'], $stage['files'], $stage['nolist']]);
            self::setGone($dir);
        }
        $this->assertSame($trees['zip'], $trees['phar'], 'The two readers unpacked different trees');
        $this->assertSame(hash('sha256', $files['core/classes/z.php']), $trees['zip']['core/classes/z.php']);
        $this->assertCount(6, $trees['zip']);
    }

    # The package is held to its own files.json: a file it does not list, a list under another hash or version, a listed file the full archive lacks
    #[Test]
    public function thePackageIsHeldToItsList(): void
    {
        $files = ['core/a.php' => "<?php\n", 'core/b.php' => "<?php\n# b\n"];
        $cases = [
            'unlisted' => [self::getList(self::VER, ['core/a.php' => $files['core/a.php']]), 'core/b.php'],
            'other hash' => [self::getList(self::VER, ['core/a.php' => $files['core/a.php'], 'core/b.php' => 'other']), 'core/b.php'],
            'other version' => [self::getList('9.0.0', $files), 'storage/update/files.json'],
            'missing file' => [self::getList(self::VER, $files + ['core/c.php' => 'c']), 'core/c.php'],
            'no list' => ['not json', 'storage/update/files.json'],
        ];
        foreach ($cases as $label => [$list, $name]) {
            $file = self::getPack($files, $list);
            $res = Update::addStage($file, self::getMan($file), 'full', 'zip', $this->root);
            $this->assertSame(['ok' => false, 'error' => 'list', 'name' => $name], $res, $label);
            $this->assertDirectoryDoesNotExist(UPDATE_DIR.'/client/stage/'.self::VER, $label);
        }
    }

    # A delta brings only what changed and takes the rest from the installed list; changed code it replaces is named, a language file and files of the site never
    #[Test]
    public function aDeltaLeansOnTheInstalledList(): void
    {
        $old = ['core/a.php' => "<?php\n# a\n", 'admin/b.php' => "<?php\n# b\n", 'lang/en.php' => "<?php\n# en\n", 'config/global.php' => "<?php\nreturn [];\n"];
        file_put_contents(UPDATE_DIR.'/files.json', self::getList(SL_VERSION, $old));
        $this->setTree(['core/a.php' => "<?php\n# edited\n", 'admin/b.php' => "<?php\n# also edited\n", 'lang/en.php' => "<?php\n# en edited\n",
            'config/global.php' => "<?php\nreturn ['site' => 1];\n"]);
        $new = ['core/a.php' => "<?php\n# a2\n", 'admin/b.php' => $old['admin/b.php'], 'lang/en.php' => "<?php\n# en2\n", 'config/global.php' => $old['config/global.php']];
        $file = self::getPack(['core/a.php' => $new['core/a.php'], 'lang/en.php' => $new['lang/en.php']], self::getList(self::VER, $new));
        $man = self::getMan($file);
        $man['delta'] = $man['full'] + ['from' => SL_VERSION];
        $this->assertSame('delta', Update::getPackKind($man));
        $this->assertSame(['ok' => true, 'error' => '', 'name' => ''], Update::addStage($file, $man, 'delta', 'phar', $this->root));
        $stage = Update::getState()['stage'];
        $this->assertSame(['delta', SL_VERSION, ['core/a.php'], false], [$stage['kind'], $stage['from'], $stage['changed'], $stage['nolist']]);
        $wrong = $new;
        $wrong['admin/b.php'] = "<?php\n# b3\n";
        $file = self::getPack(['core/a.php' => $new['core/a.php']], self::getList(self::VER, $wrong));
        $man = self::getMan($file);
        $man['delta'] = $man['full'] + ['from' => SL_VERSION];
        $this->assertSame(['ok' => false, 'error' => 'list', 'name' => 'admin/b.php'], Update::addStage($file, $man, 'delta', 'zip', $this->root));
        $man['delta']['from'] = '1.0.0';
        $this->assertSame('full', Update::getPackKind($man), 'A site off the base version took the delta');
    }

    # A download stops where the master stopped and resumes by a range from there; a server that ignores the range starts over, too many bytes or the wrong ones are refused
    #[Test]
    public function theDownloadResumesFromWhereItStopped(): void
    {
        $file = self::getPack(['core/a.php' => str_repeat("<?php\n# a\n", 2000)]);
        $data = (string)file_get_contents($file);
        $man = self::getMan($file);
        unlink($file);
        $half = intdiv(strlen($data), 2);
        $res = Update::addDownload($man, self::getOpen(200, substr($data, 0, $half)), $this->root);
        $this->assertSame([true, false, $half], [$res['ok'], $res['done'], $res['got']]);
        $this->assertSame($half, filesize(Update::getTempFile(self::VER, 'full')));
        $range = ['Content-Range: bytes '.$half.'-'.(strlen($data) - 1).'/'.strlen($data)];
        $res = Update::addDownload($man, self::getOpen(206, substr($data, $half), $range, $asked), $this->root);
        $this->assertSame($half, $asked, 'The download did not resume from its part');
        $this->assertSame([true, true, ''], [$res['ok'], $res['done'], $res['error']]);
        $this->assertDirectoryExists(UPDATE_DIR.'/client/stage/'.self::VER);
        $this->assertFileDoesNotExist(Update::getTempFile(self::VER, 'full'));
        self::setGone(UPDATE_DIR.'/client');
        $this->setKeys();
        Update::addDownload($man, self::getOpen(200, substr($data, 0, 100)), $this->root);
        $res = Update::addDownload($man, self::getOpen(200, $data, [], $asked), $this->root);
        $this->assertSame([100, true], [$asked, $res['done']], 'A server that ignored the range was appended to');
        $res = Update::addDownload($man, self::getOpen(200, $data.'tail'), $this->root);
        $this->assertSame([false, 'size'], [$res['ok'], $res['error']]);
        $this->assertFileDoesNotExist(Update::getTempFile(self::VER, 'full'));
        $this->assertSame('size', Update::getState()['fault']['error'], 'The refusal was not kept for the panel');
        $this->assertSame('hash', Update::addDownload($man, self::getOpen(200, strrev($data)), $this->root)['error']);
        $this->assertSame('master', Update::addDownload($man, self::getOpen(404, '{}'), $this->root)['error']);
    }

    # A hand upload is taken only as the full archive or the delta of the offered release, a delta only on its base version
    #[Test]
    public function theUploadTakesOnlyTheOfferedArchive(): void
    {
        $file = self::getPack(['core/a.php' => "<?php\n# a2\n"]);
        $man = self::getMan($file);
        $temp = dirname(UPDATE_DIR).'/zip/upload.tmp';
        file_put_contents($temp, 'PK other archive');
        $this->assertSame(['ok' => false, 'error' => 'offer', 'name' => ''], Update::addUpload($temp, $man, $this->root));
        $this->assertSame('offer', Update::getState()['fault']['error']);
        copy($file, $temp);
        $delta = $man;
        $delta['delta'] = $man['full'] + ['from' => '1.0.0'];
        $delta['full']['sha256'] = str_repeat('0', 64);
        $this->assertSame('delta', Update::addUpload($temp, $delta, $this->root)['error'], 'A delta of another base was taken');
        $this->assertSame(['ok' => true, 'error' => '', 'name' => ''], Update::addUpload($temp, $man, $this->root));
        $this->assertFileDoesNotExist($temp);
        $this->assertSame([], Update::getState()['fault'], 'A taken upload left the refusal');
        $this->assertSame(self::VER, Update::getState()['stage']['version']);
    }

    # The offer is the manifest found, verified again; a pause, an answer without a manifest, drops it, closes the upload and deletes the package of that release
    #[Test]
    public function aPauseClosesTheOfferAndDropsThePackage(): void
    {
        $db = $this->createStub(Database::class);
        $db->method('getSqlQuery')->willReturn(false);
        $body = json_encode(self::getMan(self::getPack(['core/a.php' => 'a'])), JSON_UNESCAPED_SLASHES)."\n";
        $man = ['body' => base64_encode($body), 'sig' => base64_encode(sodium_crypto_sign_detached(Update::MANIFEST.$body, self::$pri['sec']))];
        $check = static function (?array $man) use ($db): array {
            $fetch = static fn(): array => ['code' => 200, 'body' => json_encode(['ok' => true, 'data' => ['manifest' => $man, 'keys' => [], 'notes' => []]]), 'error' => ''];
            return Update::addCheck($db, ['homeurl' => 'https://slaed.loc'], Update::getSite('https://slaed.loc'), $fetch);
        };
        $this->assertSame(self::VER, $check($man)['found']);
        $offer = Update::getOffer(Update::getState(), 'stable');
        $this->assertTrue($offer['ok']);
        $this->assertSame(self::VER, $offer['data']['version']);
        $state = Update::getState();
        $state['found']['body'] = base64_encode(str_replace(self::VER, '9.0.2', $body));
        $this->assertSame('sign', Update::getOffer($state, 'stable')['error'], 'A found manifest was trusted without its signature');
        mkdir(UPDATE_DIR.'/client/stage/'.self::VER, 0755, true);
        mkdir(UPDATE_DIR.'/client/tmp', 0755, true);
        file_put_contents(Update::getTempFile(self::VER, 'full'), 'part');
        $this->assertSame('', $check(null)['found']);
        $this->assertSame('offer', Update::getOffer(Update::getState(), 'stable')['error'], 'A paused release is still offered');
        $this->assertDirectoryDoesNotExist(UPDATE_DIR.'/client/stage/'.self::VER, 'The package of a paused release stayed');
        $this->assertFileDoesNotExist(Update::getTempFile(self::VER, 'full'));
    }

    # The integrity check finds a changed, a missing and a foreign PHP file of the core; settings, robots.txt and the deleted installer are no finding on a clean site
    #[Test]
    public function theIntegrityCheckFindsChangedAndForeignFiles(): void
    {
        $this->assertSame('nolist', Update::addAudit($this->root)['error'], 'A site without files.json passed the check');
        $files = [];
        foreach (['core/a.php', 'admin/b.php', 'lang/en.php', 'config/global.php', 'public/robots.txt'] as $path) $files[$path] = (string)file_get_contents($this->root.'/'.$path);
        file_put_contents(UPDATE_DIR.'/files.json', self::getList(SL_VERSION, $files + ['public/setup.php' => "<?php\n# the installer, deleted at its end\n"]));
        $res = Update::addAudit($this->root);
        $this->assertSame(['', [], [], []], [$res['error'], $res['changed'], $res['missing'], $res['extra']], 'A clean site has findings');
        $this->setTree(['core/a.php' => "<?php\n# edited\n", 'config/global.php' => "<?php\nreturn ['site' => 1];\n", 'public/robots.txt' => "Sitemap: https://x/s.xml\n",
            'core/classes/own.php' => "<?php\n# foreign\n", 'public/x.PHTML' => "<?php\n", 'modules/own/index.php' => "<?php\n"]);
        unlink($this->root.'/admin/b.php');
        $res = Update::addAudit($this->root);
        $this->assertSame([['core/a.php'], ['admin/b.php'], ['core/classes/own.php', 'public/x.PHTML']], [$res['changed'], $res['missing'], $res['extra']]);
        $this->assertSame($res, Update::getState()['audit'], 'The result was not kept for the panel');
    }

    # The panel entry renamed by the installer or the security section is held as the shipped public/admin.php under its own name, never as a foreign file
    #[Test]
    public function aRenamedPanelEntryIsNoForeignFile(): void
    {
        $this->setTree(['public/admin.php' => "<?php\n# panel\n"]);
        $files = [];
        foreach (['public/admin.php', 'core/a.php', 'admin/b.php', 'lang/en.php'] as $path) $files[$path] = (string)file_get_contents($this->root.'/'.$path);
        file_put_contents(UPDATE_DIR.'/files.json', self::getList(SL_VERSION, $files));
        rename($this->root.'/public/admin.php', $this->root.'/public/door.php');
        $res = Update::addAudit($this->root, 'door');
        $this->assertSame([[], [], []], [$res['changed'], $res['missing'], $res['extra']], 'The renamed panel entry was reported');
        $res = Update::addAudit($this->root);
        $this->assertSame([['public/admin.php'], ['public/door.php']], [$res['missing'], $res['extra']]);
        file_put_contents($this->root.'/public/door.php', "<?php\n# edited\n");
        $this->assertSame(['public/door.php'], Update::addAudit($this->root, 'door')['changed'], 'An edited panel entry passed under its new name');
    }

    # The space of a download is the archive and the package less what is on disk, plus 10 per cent but at least 50 MB
    #[Test]
    public function theSpaceCountsArchivePackageAndReserve(): void
    {
        $mb = 1048576;
        $this->assertSame(450 * $mb, Update::getSpaceNeed(['size' => 100 * $mb, 'usize' => 300 * $mb])['need']);
        $this->assertSame(350 * $mb, Update::getSpaceNeed(['size' => 100 * $mb, 'usize' => 300 * $mb], 100 * $mb)['need']);
        $this->assertSame(1100 * $mb, Update::getSpaceNeed(['size' => 200 * $mb, 'usize' => 800 * $mb])['need']);
        $room = Update::getSpaceNeed(['size' => 1, 'usize' => 1]);
        $this->assertIsInt($room['free']);
        $this->assertTrue($room['ok']);
        $this->assertFalse(Update::getSpaceNeed(['size' => PHP_INT_MAX >> 2, 'usize' => 1])['ok'], 'An archive larger than the disk fits');
    }
}
