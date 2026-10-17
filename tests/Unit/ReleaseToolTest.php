<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Update;
use ZipArchive;

# tools/release.php and tools/sign.php on a small repository of the run: builds from tags, signatures, the refusals of build and publish, notes and key statements
final class ReleaseToolTest extends TestCase
{
    private static string $work = '';
    private static array $run = [];

    public static function setUpBeforeClass(): void
    {
        require_once BASE_DIR.'/core/classes/update.php';
        self::$work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_release_'.getmypid();
        self::deleteTree(self::$work);
        mkdir(self::$work, 0777, true);
        self::setScenario();
    }

    public static function tearDownAfterClass(): void
    {
        self::deleteTree(self::$work);
    }

    # Remove a scratch tree whole, read-only git objects included
    private static function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($walk as $item) {
            chmod($item->getPathname(), 0777);
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    # Run a program in the scratch repository with the given input: exit code and both streams together
    private static function getRun(array $cmd, string $input = ''): array
    {
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::$work.'/repo');
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $out = (string)stream_get_contents($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['code' => proc_close($proc), 'out' => $out.$err];
    }

    # One tool of the scratch repository without OPcache: on Windows CLI processes share one cache, and a rewritten update.php could run as compiled two seconds before
    private static function getTool(string $tool, array $args, string $input = ''): array
    {
        return self::getRun(array_merge([PHP_BINARY, '-d', 'opcache.enable_cli=0', 'tools/'.$tool.'.php'], $args), $input);
    }

    # git in the scratch repository, quiet and with a fixed author
    private static function setGit(array $args): void
    {
        $res = self::getRun(array_merge(['git', '-c', 'user.name=Test', '-c', 'user.email=test@slaed.net', '-c', 'core.autocrlf=false'], $args));
        if ($res['code'] !== 0) throw new \RuntimeException('git '.implode(' ', $args).': '.$res['out']);
    }

    # Write a file of the scratch repository
    private static function setFile(string $path, string $text): void
    {
        $file = self::$work.'/repo/'.$path;
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0777, true);
        file_put_contents($file, $text);
    }

    # The version a tree names, committed with a tag
    private static function setRelease(string $ver, string $text = ''): void
    {
        self::setFile('core/version.php', "<?php\ndefine('SL_VERSION', '".$ver."');\ndefine('SL_CODENAME', 'Test');\n");
        self::setGit(['add', '-A']);
        self::setGit(['commit', '-q', '-m', $text !== '' ? $text : 'Release '.$ver]);
        self::setGit(['tag', 'v'.$ver]);
    }

    # The entries of a zip and the files.json inside it
    private static function getZip(string $file): array
    {
        $zip = new ZipArchive();
        $zip->open($file);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) $names[] = $zip->getNameIndex($i);
        $list = json_decode((string)$zip->getFromName('storage/update/files.json'), true);
        $zip->close();
        return ['names' => $names, 'list' => $list];
    }

    # The two signed manifests of a version as a site of $ver would read them under $keys
    private static function getSigned(string $ver, array $keys, string $site): array
    {
        $out = [];
        foreach (['beta', 'stable'] as $chan) {
            $file = self::$work.'/repo/storage/release/'.$ver.'/manifest-'.$chan.'.json';
            $out[$chan] = Update::getManifest((string)file_get_contents($file), is_file($file.'.sig') ? (string)file_get_contents($file.'.sig') : '', $keys,
                ['version' => $site, 'channel' => 'beta']);
        }
        return $out;
    }

    # The keys of a key file pair: the factory keys a site of that tree trusts
    private static function getFileKeys(string $pri, string $rec): array
    {
        $read = static fn(string $file): string => (string)json_decode((string)file_get_contents($file), true)['pub'];
        return ['kseq' => 0, 'primary' => $read($pri), 'recovery' => $read($rec), 'revoked' => []];
    }

    # The whole history of the scratch repository, run once; every test reads its part
    private static function setScenario(): void
    {
        $repo = self::$work.'/repo';
        mkdir($repo);
        $copy = ['tools/release.php', 'tools/sign.php', 'core/classes/update.php', 'tests/Fixtures/update/primary.key', 'tests/Fixtures/update/recovery.key', '.gitattributes',
            '.gitignore'];
        foreach ($copy as $path) {
            self::setFile($path, (string)file_get_contents(BASE_DIR.'/'.$path));
        }
        $test = self::getFileKeys($repo.'/tests/Fixtures/update/primary.key', $repo.'/tests/Fixtures/update/recovery.key');
        $code = (string)file_get_contents($repo.'/core/classes/update.php');
        $code = preg_replace("#const PRIMARY = '[^']*'#", "const PRIMARY = '".$test['primary']."'", $code);
        self::setFile('core/classes/update.php', preg_replace("#const RECOVERY = '[^']*'#", "const RECOVERY = '".$test['recovery']."'", $code));
        $files = ['public/sitemap.xml' => "<urlset/>\n", 'composer.json' => '{"require":{"php":">=8.4"}}', 'README.md' => "# Test\n", 'config/global.php' => "<?php\nreturn [];\n",
            'config/system.php' => "<?php\n", 'public/robots.txt' => "User-agent: *\n", 'public/index.php' => "<?php\n", 'public/update.php' => "<?php\n# one\n",
            'storage/update/sql/table.sql' => "-- table\n", 'storage/update/sql/table_update6_3.sql' => "-- 6.3\n", 'core/system.php' => "<?php\n# one\n",
            'modules/news/index.php' => "<?php\n# news\n", 'docs/a.md' => "# a\n", 'modules/master/api.php' => "<?php\n# master\n"];
        foreach ($files as $path => $text) self::setFile($path, $text);
        self::setGit(['init', '-q']);
        self::setRelease('8.0.0');
        $key = ['--key=tests/Fixtures/update/primary.key'];
        $run = [];
        $run['nomax'] = self::getTool('release', ['build', 'v8.0.0']);
        $run['first'] = self::getTool('release', ['build', 'v8.0.0', '--max=5']);
        $run['firstzip'] = self::getZip($repo.'/storage/release/8.0.0/slaed-8.0.0.zip');
        $run['firstman'] = json_decode((string)file_get_contents($repo.'/storage/release/8.0.0/manifest-stable.json'), true);
        $run['firsthash'] = hash_file('sha256', $repo.'/storage/release/8.0.0/slaed-8.0.0.zip');
        $run['nogo'] = self::getTool('sign', array_merge(['8.0.0'], $key), "n\n");
        $run['sign'] = self::getTool('sign', array_merge(['8.0.0'], $key), "y\nslaed-test\n");
        $run['signed'] = self::getSigned('8.0.0', self::getFileKeys($repo.'/tests/Fixtures/update/primary.key', $repo.'/tests/Fixtures/update/recovery.key'), '6.3.0');
        $run['twice'] = self::getTool('sign', array_merge(['8.0.0'], $key), "y\nslaed-test\n");
        $run['badpass'] = self::getTool('sign', array_merge(['--refresh', '8.0.0'], $key), "y\nwrong-pass\n");
        $run['testkeys'] = self::getTool('release', ['publish', 'v8.0.0']);
        rename($repo.'/modules/news/index.php', $repo.'/modules/news/main.php');
        self::setFile('core/system.php', "<?php\n# two\n");
        self::setFile('public/update.php', "<?php\n# two\n");
        self::setFile('docs/big.txt', bin2hex(random_bytes(8000)));
        self::setGit(['add', '-A']);
        self::setGit(['commit', '-q', '-m', 'Work']);
        self::setRelease('8.0.1');
        self::setFile('README.md', "# Dirty\n");
        $run['dirty'] = self::getTool('release', ['build', 'v8.0.1', '--max=5']);
        self::setGit(['checkout', '-q', '--', 'README.md']);
        $run['grew'] = self::getTool('release', ['build', 'v8.0.1']);
        $run['second'] = self::getTool('release', ['build', 'v8.0.1', '--max=5']);
        $run['delta'] = self::getZip($repo.'/storage/release/8.0.1/slaed-8.0.1-from-8.0.0.zip');
        $run['secondman'] = json_decode((string)file_get_contents($repo.'/storage/release/8.0.1/manifest-stable.json'), true);
        self::setGit(['tag', 'v8.0.5']);
        $run['mismatch'] = self::getTool('release', ['build', 'v8.0.5', '--max=5']);
        self::setGit(['tag', '-d', 'v8.0.5']);
        $run['notag'] = self::getTool('release', ['build', 'v8.0.6', '--max=5']);
        $blob = self::getRun(['git', 'hash-object', '-w', '--stdin'], 'system.php');
        self::setFile('core/version.php', "<?php\ndefine('SL_VERSION', '8.0.2');\ndefine('SL_CODENAME', 'Test');\n");
        self::setGit(['add', 'core/version.php']);
        self::setGit(['update-index', '--add', '--cacheinfo', '120000,'.trim($blob['out']).',core/link']);
        self::setGit(['commit', '-q', '-m', 'Link']);
        self::setGit(['checkout', '-q', '--', 'core/link']);
        self::setGit(['tag', 'v8.0.2']);
        $run['symlink'] = self::getTool('release', ['build', 'v8.0.2', '--max=5']);
        self::setGit(['tag', '-d', 'v8.0.2']);
        self::setGit(['reset', '-q', '--hard', 'v8.0.1']);
        self::setFile('junk/x.txt', "x\n");
        self::setRelease('8.0.2', 'Junk');
        $run['junk'] = self::getTool('release', ['build', 'v8.0.2', '--max=5']);
        self::setGit(['tag', '-d', 'v8.0.2']);
        self::setGit(['reset', '-q', '--hard', 'v8.0.1']);
        $kdir = self::$work.'/keys';
        mkdir($kdir);
        foreach ([['primary', 'p'], ['recovery', 'r'], ['primary', 'p2'], ['recovery', 'r2']] as [$role, $name]) {
            $run['new'.$name] = self::getTool('sign', ['--keys', 'new', $role, $kdir.'/'.$name.'.key'], "real-secret\nreal-secret\n");
        }
        $real = self::getFileKeys($kdir.'/p.key', $kdir.'/r.key');
        $code = (string)file_get_contents($repo.'/core/classes/update.php');
        $code = preg_replace("#const PRIMARY = '[^']*'#", "const PRIMARY = '".$real['primary']."'", $code);
        self::setFile('core/classes/update.php', preg_replace("#const RECOVERY = '[^']*'#", "const RECOVERY = '".$real['recovery']."'", $code));
        self::setRelease('8.0.2', 'Real keys');
        $run['third'] = self::getTool('release', ['build', 'v8.0.2', '--max=5']);
        $run['wrongkey'] = self::getTool('sign', ['8.0.2', '--key=tests/Fixtures/update/primary.key'], "y\nslaed-test\n");
        $run['thirdsign'] = self::getTool('sign', ['8.0.2', '--key='.$kdir.'/p.key'], "y\nreal-secret\n");
        $run['noreadme'] = self::getTool('release', ['publish', 'v8.0.2']);
        $prints = '';
        foreach (['primary', 'recovery'] as $role) $prints .= '- '.$role.': `'.trim(chunk_split(Update::getKeyPrint(base64_decode($real[$role])), 4, ' '))."`\n";
        self::setFile('README.md', "# Test\n\n".$prints);
        self::setGit(['tag', '-d', 'v8.0.2']);
        self::setGit(['add', '-A']);
        self::setGit(['commit', '-q', '-m', 'Fingerprints']);
        self::setGit(['tag', 'v8.0.2']);
        self::getTool('release', ['build', 'v8.0.2', '--max=5']);
        self::getTool('sign', ['8.0.2', '--key='.$kdir.'/p.key'], "y\nreal-secret\n");
        $run['norehearse'] = self::getTool('release', ['publish', 'v8.0.2']);
        $man = json_decode((string)file_get_contents($repo.'/storage/release/8.0.2/manifest-stable.json'), true);
        $green = ['version' => '8.0.2', 'from' => '8.0.1', 'full' => $man['full']['sha256'], 'delta' => $man['delta']['sha256']];
        self::setFile('storage/release/8.0.2/rehearsal.json', json_encode(['full' => str_repeat('0', 64)] + $green));
        $run['otherhash'] = self::getTool('release', ['publish', 'v8.0.2']);
        self::setFile('storage/release/8.0.2/rehearsal.json', json_encode($green));
        $run['noenv'] = self::getTool('release', ['publish', 'v8.0.2']);
        $run['note'] = self::getTool('release', ['note', '--level', 'warn', '--ver', '<8.0.2', '--until', '2099-12-31'], "Обновитесь\n.\nPlease update\n.\n");
        preg_match('#Note (\d+) written#', $run['note']['out'], $hit);
        $run['noteid'] = $hit[1] ?? '';
        $run['notesign'] = self::getTool('sign', ['--note', $run['noteid'], '--key='.$kdir.'/p.key'], "y\nreal-secret\n");
        $nfile = $repo.'/storage/release/notes/'.$run['noteid'];
        $run['notepub'] = self::getTool('release', ['publish', '--note='.$run['noteid']]);
        $run['noteres'] = is_file($nfile.'.sig') ? Update::getNote((string)file_get_contents($nfile.'.json'), (string)file_get_contents($nfile.'.sig'), $real,
            ['sid' => '', 'version' => '8.0.1']) : ['ok' => false, 'error' => 'missing'];
        $run['change'] = self::getTool('sign', ['--keys', 'change', '--primary='.$kdir.'/p2.key', '--with='.$kdir.'/r.key'], "y\nreal-secret\n");
        $run['change2'] = self::getTool('sign', ['--keys', 'change', '--recovery='.$kdir.'/r2.key', '--with='.$kdir.'/r.key', '--key='.$kdir.'/p2.key'],
            "y\nreal-secret\nreal-secret\n");
        $run['chain'] = Update::getKeyChain($real, Update::getKeyList($repo.'/storage/release/keys'));
        $run['keyfiles'] = array_map('basename', glob($repo.'/storage/release/keys/*') ?: []);
        $run['oldsign'] = self::getTool('sign', ['--refresh', '8.0.2', '--key='.$kdir.'/p.key'], "y\nreal-secret\n");
        $run['p2'] = self::getFileKeys($kdir.'/p2.key', $kdir.'/r2.key');
        self::$run = $run;
    }

    # The first release confirms its size with --max; the archive is the tag without export-ignore, with files.json, and the site files carry their mark
    #[Test]
    public function aBuildIsTheTagWithItsFileList(): void
    {
        $run = self::$run;
        $this->assertSame(1, $run['nomax']['code'], 'A first release built without --max');
        $this->assertStringContainsString('--max', $run['nomax']['out']);
        $this->assertSame(0, $run['first']['code'], 'The first release did not build: '.$run['first']['out']);
        $names = $run['firstzip']['names'];
        $this->assertContains('storage/update/files.json', $names);
        $this->assertContains('core/system.php', $names);
        $this->assertNotContains('public/update.php', $names, 'public/update.php is in the archive');
        $this->assertNotContains('storage/update/sql/table_update6_3.sql', $names, 'table_update6_3.sql is in the archive');
        $this->assertNotContains('public/sitemap.xml', $names, 'public/sitemap.xml, which each site writes for itself, is in the archive');
        $this->assertNotContains('modules/master/api.php', $names, 'The module master of slaed.net is in the archive');
        $this->assertArrayNotHasKey('modules/master/api.php', $run['firstzip']['list']['files'], 'files.json lists the module master');
        $this->assertContains('storage/update/sql/table.sql', $names);
        $list = $run['firstzip']['list'];
        $this->assertSame('8.0.0', $list['version']);
        $this->assertTrue($list['files']['config/global.php']['site'] ?? false, 'config/global.php is not marked as a file of the site');
        $this->assertTrue($list['files']['public/robots.txt']['site'] ?? false, 'public/robots.txt is not marked as a file of the site');
        $this->assertArrayNotHasKey('site', $list['files']['config/system.php'], 'config/system.php is code, not a setting');
        $this->assertArrayNotHasKey('storage/update/files.json', $list['files'], 'files.json lists itself');
        $this->assertSame(hash('sha256', "<?php\n# one\n"), $list['files']['core/system.php']['sha256']);
        $man = $run['firstman'];
        $this->assertSame(['8.0.0', 'stable', '8.0.0', '8.4', null, false], [$man['version'], $man['channel'], $man['min'], $man['php'], $man['delta'], $man['security']]);
        $this->assertSame($run['firsthash'], $man['full']['sha256']);
        $this->assertSame(45 * 86400, $man['expires'] - $man['time']);
    }

    # A build needs a local tag, a clean tree and the version of the code; symlinks and folders that ship nowhere stop it
    #[Test]
    public function aBuildRefusesAWrongTree(): void
    {
        $run = self::$run;
        foreach (['dirty' => 'not clean', 'mismatch' => 'names 8.0.1', 'notag' => 'no local tag', 'symlink' => 'symlink core/link', 'junk' => 'junk/x.txt'] as $name => $why) {
            $this->assertSame(1, $run[$name]['code'], 'The build '.$name.' was not refused');
            $this->assertStringContainsString($why, $run[$name]['out'], 'The build '.$name.' was refused for another reason');
        }
    }

    # Growth beyond 20 % stops the build until --max; the delta holds changed, new and renamed files, every settings file and the full list, no other unchanged one
    #[Test]
    public function theDeltaFollowsTheTwoLists(): void
    {
        $run = self::$run;
        $this->assertSame(1, $run['grew']['code'], 'A grown archive built without --max');
        $this->assertStringContainsString('grew', $run['grew']['out']);
        $this->assertSame(0, $run['second']['code'], 'The second release did not build: '.$run['second']['out']);
        $names = $run['delta']['names'];
        sort($names);
        $this->assertSame(['config/global.php', 'core/system.php', 'core/version.php', 'docs/big.txt', 'modules/news/main.php', 'storage/update/files.json'], $names,
            'The delta lost an unchanged settings file the merge reads, or took unchanged code, robots.txt or an export-ignore file');
        $this->assertArrayNotHasKey('modules/news/index.php', $run['delta']['list']['files'], 'The renamed file is still in the list');
        $this->assertArrayHasKey('docs/a.md', $run['delta']['list']['files'], 'The list of the delta is not the full list');
        $this->assertSame('8.0.0', $run['secondman']['delta']['from']);
        $this->assertSame('8.0.0', $run['secondman']['min'], 'The lowest release with an updater is not the default min');
    }

    # sign.php shows the release and waits for "y", signs both manifests under the key in force, refuses a second signing, a wrong password and a key not in force
    #[Test]
    public function signingIsTheGoForARelease(): void
    {
        $run = self::$run;
        $this->assertSame(1, $run['nogo']['code'], 'Signing went on without the go');
        $this->assertStringContainsString($run['firsthash'], $run['nogo']['out'], 'The hash of the archive was not shown before the go');
        $this->assertSame(0, $run['sign']['code'], 'Signing failed: '.$run['sign']['out']);
        foreach ($run['signed'] as $chan => $res) $this->assertTrue($res['ok'], 'The '.$chan.' manifest does not verify: '.$res['error']);
        $this->assertStringContainsString('--refresh', $run['twice']['out'], 'A signed release was signed a second time');
        $this->assertStringContainsString('wrong password', $run['badpass']['out']);
        $this->assertStringContainsString('not the primary key in force', $run['wrongkey']['out'], 'The test key signed a tree with real keys');
        $this->assertSame(0, $run['thirdsign']['code'], 'The real key did not sign: '.$run['thirdsign']['out']);
    }

    # publish stops on test keys, on fingerprints missing from the README and without a green rehearsal of these archives; a sound release gets to the SFTP settings
    #[Test]
    public function publishingRefusesTestKeys(): void
    {
        $run = self::$run;
        $this->assertSame(1, $run['testkeys']['code']);
        $this->assertStringContainsString('is the test key', $run['testkeys']['out'], 'A release with test keys got past publish');
        $this->assertSame(1, $run['noreadme']['code']);
        $this->assertStringContainsString('README.md does not publish the fingerprint', $run['noreadme']['out']);
        foreach (['norehearse' => 'a release never rehearsed', 'otherhash' => 'a rehearsal of another archive'] as $name => $what) {
            $this->assertSame(1, $run[$name]['code'], 'publish let through '.$what);
            $this->assertStringContainsString('no green rehearsal', $run[$name]['out'], 'publish refused '.$what.' for another reason');
        }
        $this->assertSame(1, $run['noenv']['code']);
        $this->assertStringContainsString('SLAED_SFTP', $run['noenv']['out'], 'A sound release was refused before the SFTP settings: '.$run['noenv']['out']);
    }

    # A note is written from the console, signed and verified as a site verifies it; key statements chain under the recovery key and retire the old primary
    #[Test]
    public function notesAndKeyStatementsAreSigned(): void
    {
        $run = self::$run;
        $this->assertSame(0, $run['note']['code'], 'The note was not written: '.$run['note']['out']);
        $this->assertSame(0, $run['notesign']['code'], 'The note was not signed: '.$run['notesign']['out']);
        $this->assertTrue($run['noteres']['ok'], 'The signed note does not verify: '.$run['noteres']['error']);
        $this->assertStringContainsString('SLAED_SFTP', $run['notepub']['out'], 'publish did not find the signed note sign.php wrote: '.$run['notepub']['out']);
        $this->assertSame(['Обновитесь', 'Please update', 'warn'], [$run['noteres']['data']['ru'], $run['noteres']['data']['en'], $run['noteres']['data']['level']]);
        foreach (['newp', 'newr', 'newp2', 'newr2', 'change', 'change2'] as $name) $this->assertSame(0, $run[$name]['code'], $name.' failed: '.$run[$name]['out']);
        $this->assertStringContainsString('seed for paper', $run['newr']['out'], 'A new recovery key showed no seed for paper');
        $this->assertStringNotContainsString('seed for paper', $run['newp']['out']);
        $this->assertSame(['1.json', '1.sig', '2.json', '2.psig', '2.sig'], $run['keyfiles']);
        $this->assertTrue($run['chain']['ok'], 'The statements do not chain: '.$run['chain']['error']);
        $this->assertSame([2, $run['p2']['primary'], $run['p2']['recovery']], [$run['chain']['keys']['kseq'], $run['chain']['keys']['primary'], $run['chain']['keys']['recovery']]);
        $this->assertStringContainsString('not the primary key in force', $run['oldsign']['out'], 'The replaced primary still signs');
    }
}
