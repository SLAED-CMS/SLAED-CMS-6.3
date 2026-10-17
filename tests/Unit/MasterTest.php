<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Update;

# The module master of slaed.net over route_probe.php master: its panel, master/check and master/file, the two jobs; the refusals of inbox/ and the cohort in-process
final class MasterTest extends TestCase
{
    private static array $probe = [];

    public static function setUpBeforeClass(): void
    {
        require_once BASE_DIR.'/core/classes/update.php';
        require_once BASE_DIR.'/modules/master/common.php';
    }

    protected function tearDown(): void
    {
        self::deleteTree(MASTER_DIR);
    }

    # Remove a scratch tree whole
    private static function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($walk as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir($dir);
    }

    # Run the probe once in its master mode and memoize the run; it leaves no database and the requests no PHP or SQL error
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_master_route';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' master 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The requests wrote PHP or SQL errors');
            self::$probe = $data['runs'];
        }
        return self::$probe;
    }

    # The secret key of a test key, opened by its known password
    private static function getKey(string $role): string
    {
        $data = json_decode((string)file_get_contents(BASE_DIR.'/tests/Fixtures/update/'.$role.'.key'), true);
        $kek = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'slaed-test', base64_decode($data['salt']), (int)$data['ops'], (int)$data['mem'],
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
        $seed = (string)sodium_crypto_secretbox_open(base64_decode($data['box']), base64_decode($data['nonce']), $kek);
        return sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($seed));
    }

    # One release folder in inbox/ signed by $sec, with fields of both manifests replaced by $over
    private static function setPack(string $ver, string $sec, array $over = []): string
    {
        $dir = MASTER_DIR.'/inbox/'.$ver;
        mkdir($dir, 0777, true);
        $full = 'archive '.$ver;
        file_put_contents($dir.'/slaed-'.$ver.'.zip', $full);
        foreach (['beta', 'stable'] as $chan) {
            $data = array_replace(['type' => 'release', 'version' => $ver, 'channel' => $chan, 'min' => '8.0.0', 'php' => '8.4', 'security' => false,
                'full' => ['size' => strlen($full), 'usize' => 100, 'sha256' => hash('sha256', $full)], 'delta' => null, 'commit' => str_repeat('c', 40), 'time' => time(),
                'expires' => time() + 86400, 'kid' => substr(Update::getKeyPrint(substr($sec, 32)), 0, 16)], $over);
            $body = json_encode($data, JSON_PRETTY_PRINT)."\n";
            file_put_contents($dir.'/manifest-'.$chan.'.json', $body);
            file_put_contents($dir.'/manifest-'.$chan.'.json.sig', base64_encode(sodium_crypto_sign_detached(Update::MANIFEST.$body, $sec)));
        }
        return $dir;
    }

    # The panel accepts a release only with its token; an accepted release moves out of inbox/ whole and waits, the forged, recovery-signed and expired ones stay
    #[Test]
    public function theInboxIsAcceptedOnlyWhenItVerifies(): void
    {
        $run = $this->getRun()['master'];
        $this->assertSame([200, 5], [$run['page']['code'], $run['page']['inbox']], 'The tab of releases does not list the five folders of inbox/');
        $this->assertSame(0, (int)$run['notokenrow'], 'A post without a token accepted a release');
        $this->assertSame([['ver' => '8.0.1', 'state' => 'wait', 'share' => 100], ['ver' => '8.0.2', 'state' => 'wait', 'share' => 100]], $run['releases']);
        $this->assertSame(['manifest-beta.json', 'manifest-beta.json.sig', 'manifest-stable.json', 'manifest-stable.json.sig', 'slaed-8.0.1-from-8.0.0.zip', 'slaed-8.0.1.zip'],
            $run['moved']['8.0.1']);
        $this->assertFalse($run['moved']['inbox'], 'The accepted folder stayed in inbox/');
        $this->assertSame(['8.0.3', '8.0.4', '8.0.5', 'notes'], $run['moved']['left']);
        $this->assertFalse($run['moder'], 'An administrator without the right of master saw its panel');
        $this->assertGreaterThan(0, $run['journal']['state'], 'The moves of releases are not in admin.log');
        $this->assertTrue($run['journal']['ver'], 'A move of a release in admin.log does not name its version');
    }

    # Each refusal of inbox/ in-process: a forged archive, the recovery key, an expired manifest, another version, a missing signature and a path that is no version
    #[Test]
    public function aRefusedReleaseNamesItsReason(): void
    {
        $pri = self::getKey('primary');
        $dir = self::setPack('8.0.3', $pri);
        file_put_contents($dir.'/slaed-8.0.3.zip', 'x', FILE_APPEND);
        $this->assertSame('archive', addMasterRelease('8.0.3'));
        self::setPack('8.0.4', self::getKey('recovery'));
        $this->assertSame('sign', addMasterRelease('8.0.4'));
        self::setPack('8.0.5', $pri, ['time' => time() - 100, 'expires' => time() - 10]);
        $this->assertSame('expired', addMasterRelease('8.0.5'));
        self::setPack('8.0.6', $pri, ['version' => '8.0.7']);
        $this->assertSame('format', addMasterRelease('8.0.6'));
        unlink(self::setPack('8.0.8', $pri).'/manifest-stable.json.sig');
        $this->assertSame('manifest', addMasterRelease('8.0.8'));
        $this->assertSame('missing', addMasterRelease('8.0.9'));
        $this->assertSame('missing', addMasterRelease('../8.0.3'));
        foreach (['8.0.3', '8.0.4', '8.0.5', '8.0.6', '8.0.8'] as $ver) $this->assertDirectoryExists(MASTER_DIR.'/inbox/'.$ver, 'A refused release left inbox/');
        $this->assertDirectoryDoesNotExist(MASTER_DIR.'/8.0.3');
    }

    # A site is offered the highest release its channel follows, its version may reach and its cohort includes; share 0 falls back, a pause withdraws, the bytes verify
    #[Test]
    public function aSiteIsOfferedWhatItsChannelAndCohortAllow(): void
    {
        $run = $this->getRun()['master'];
        $this->assertSame(['wait' => '', 'betabeta' => '8.0.1/beta', 'betastable' => '', 'stablebeta' => '8.0.2/stable', 'stable' => '8.0.2/stable', 'above' => '', 'old' => '',
            'nobody' => '', 'nobodybeta' => '8.0.1/beta'], $run['offer']);
        $keys = Update::getFactoryKeys();
        $res = Update::getManifest((string)base64_decode($run['signed']['body']), $run['signed']['sig'], $keys, ['version' => '8.0.0', 'channel' => 'stable']);
        $this->assertTrue($res['ok'], 'The served manifest does not verify under the factory key: '.$res['error']);
        $this->assertSame(['offer' => '', 'file' => 404, 'row' => ['state' => 'pause', 'pauseby' => 'owner']], $run['paused'], 'A paused release is still served');
        $this->assertSame('8.0.2/stable', $run['back'], 'A release moved back to stable is not served again');
    }

    # The cohort of a release is its own: share 0 holds no site, 100 every site, 10 about a tenth, and two releases at 10 have different first sites
    #[Test]
    public function eachReleaseHasItsOwnCohort(): void
    {
        $sids = array_map(static fn(int $i): string => md5('site'.$i), range(1, 2000));
        $one = array_filter($sids, static fn(string $sid): bool => isMasterCohort($sid, '8.0.1', 10));
        $two = array_filter($sids, static fn(string $sid): bool => isMasterCohort($sid, '8.0.2', 10));
        $this->assertGreaterThan(140, count($one));
        $this->assertLessThan(260, count($one));
        $this->assertLessThan(count($one) / 2, count(array_intersect($one, $two)), 'Two releases start with the same sites');
        $this->assertSame([], array_filter($sids, static fn(string $sid): bool => isMasterCohort($sid, '8.0.1', 0)));
        $this->assertCount(2000, array_filter($sids, static fn(string $sid): bool => isMasterCohort($sid, '8.0.1', 100)));
    }

    # master/file streams an archive of a served release with ranges, HEAD and an entity tag; a delta it lacks, an unknown or unaccepted version is 404, a path-like value 422
    #[Test]
    public function theArchiveComesOnlyFromAServedRelease(): void
    {
        $run = $this->getRun()['master'];
        $this->assertSame([200, true], [$run['file']['code'], $run['file']['same']]);
        $this->assertNotSame('', $run['file']['etag']);
        $this->assertStringStartsWith('private', $run['file']['cache']);
        $this->assertSame([206, 'bytes 0-3/421'], [$run['range']['code'], $run['range']['head']['content-range'] ?? '']);
        $this->assertSame([200, '421'], [$run['fhead']['code'], $run['fhead']['head']['content-length'] ?? '']);
        $this->assertSame([200, 404, 404, 404], $run['delta'], 'delta of 8.0.1, delta of 8.0.2 without one, 8.0.9 unknown, 8.0.3 not accepted');
        $this->assertSame(['dots' => [422, 'invalid', 'v'], 'beta' => [422, 'invalid', 'v'], 'kind' => [422, 'invalid', 'kind'], 'sid' => [422, 'invalid', 'sid'],
            'upper' => [422, 'invalid', 'sid'], 'local' => [422, 'invalid', 'host'], 'ip' => [422, 'invalid', 'host'], 'chan' => [422, 'invalid', 'ch'],
            'post' => [405, 'no_method', '']], $run['bad']);
    }

    # The check records the last state of a site and no address; an empty domain erases the stored one, and a failed installation stays until the next one
    #[Test]
    public function theCheckRecordsTheStateOfASite(): void
    {
        $site = $this->getRun()['master']['site'];
        $this->assertSame(['host' => 'example.com', 'ver' => '8.0.0', 'chan' => 'beta', 'php' => '8.4.13', 'db' => 'mariadb', 'dbver' => '10.11.6', 'ext' => 3, 'ep' => 2,
            'es' => 1, 'failver' => '', 'failstep' => 0, 'fail' => 0], $site['first']);
        $this->assertSame(['8.0.1', 6, 1], [$site['fail']['failver'], $site['fail']['failstep'], $site['fail']['fail']]);
        $this->assertSame(['', '8.0.1', '8.0.1', 6], [$site['after']['host'], $site['after']['ver'], $site['after']['failver'], $site['after']['failstep']]);
        $this->assertSame(0, (int)$site['ip'], 'The table of sites has a column for an address');
    }

    # Notes go to the sites they address and range over, a forged one stays in inbox/, a withdrawn one disappears; key statements go to the sites below their kseq
    #[Test]
    public function notesAndKeysReachTheirSites(): void
    {
        $run = $this->getRun()['master'];
        $this->assertSame(['a' => [1001, 1002], 'b' => [1001], 'anew' => [1002]], array_intersect_key($run['notes'], ['a' => 1, 'b' => 1, 'anew' => 1]));
        $this->assertSame(['1003.json', '1003.sig'], $run['notes']['left'], 'A note signed by the recovery key was accepted');
        $this->assertSame([1001], $run['notes']['off'], 'A withdrawn note is still served');
        $keys = Update::getFactoryKeys();
        $res = Update::getNote($run['notes']['body'], $run['notes']['sig'], $keys, ['sid' => str_repeat('a', 32), 'version' => '8.0.0']);
        $this->assertTrue($res['ok'], 'The served note does not verify: '.$res['error']);
        $this->assertSame(['zero' => [1], 'one' => []], $run['keys'], 'Key statements are not served by kseq, or a forged one passed');
        $this->assertSame(['error' => true, 'one' => true, 'two' => false], $run['keytab'], 'The tab of keys hides the failed statement or shows it in force');
    }

    # Every tab and the manual answer; the sites tab finds by domain, hides the active sites among the silent and gives the command of a personal note
    #[Test]
    public function thePanelShowsEveryTab(): void
    {
        $run = $this->getRun()['master'];
        foreach ($run['tabs'] as $name => $code) $this->assertSame(200, $code, 'The panel page '.$name.' did not answer');
        $this->assertSame(['hint' => true, 'find' => true, 'quiet' => true], $run['sitetab']);
    }

    # Saving the settings writes config/master.php and puts both jobs into the scheduler of this site, which the shipped scheduler never carries
    #[Test]
    public function theSettingsRegisterTheJobs(): void
    {
        $run = $this->getRun()['master'];
        $this->assertSame(['master' => ['keep' => '365', 'limit' => '7', 'silent' => '90']], $run['config']);
        $this->assertSame(['masterclean' => ['masterclean', '1'], 'masterwatch' => ['masterwatch', '1']], $run['jobs']);
        $ship = require BASE_DIR.'/config/scheduler.php';
        $this->assertArrayNotHasKey('masterclean', $ship['scheduler']['jobs'], 'The shipped scheduler carries a job of the master');
        $this->assertArrayNotHasKey('masterwatch', $ship['scheduler']['jobs'], 'The shipped scheduler carries a job of the master');
    }

    # masterclean writes today's totals and removes what outlived the saved terms; without the module the job is disabled, never failed
    #[Test]
    public function theDailyTotalsAreWritten(): void
    {
        $jobs = $this->getRun()['jobs'];
        $this->assertSame('success', $jobs['clean']['status']);
        $this->assertSame([['9.0.0', '8.4', 'mariadb 10.11', 5, 0, 5, 5, 5], ['9.0.1', '8.4', 'mariadb 10.11', 54, 0, 54, 54, 4], ['9.0.2', '8.4', 'mariadb 10.11', 3, 0, 3, 3, 3],
            ['9.0.3', '8.4', 'mariadb 10.11', 5, 0, 5, 5, 5]], $jobs['stat']);
        $this->assertSame([0, 0], [$jobs['old'], $jobs['silent']], 'Totals or sites older than their terms were kept');
        $this->assertSame('disabled', $jobs['off']['status']);
    }

    # masterwatch pauses 5 failed on 54 successful above the threshold, with a mark, a mail and a journal line; 4 failed do not, other versions and later successes do not count
    #[Test]
    public function theAutomaticPauseNeedsEnoughFailures(): void
    {
        $jobs = $this->getRun()['jobs'];
        $this->assertSame([['9.0.1', 'pause', 'auto', 5, 54], ['9.0.2', 'beta', '', 0, 0], ['9.0.3', 'stable', '', 0, 0]], $jobs['release']);
        $this->assertSame(['Paused: 9.0.1', 'No release paused'], [$jobs['watch']['message'], $jobs['again']['message']]);
        $this->assertCount(1, $jobs['mail'], 'The owner did not get exactly one mail');
        $this->assertSame(['master', $jobs['adminmail'], true], [$jobs['mail'][0][0], $jobs['mail'][0][1], $jobs['mail'][0][3]]);
        $this->assertSame(1, $jobs['journal'], 'The automatic pause is not one line of admin.log');
    }

    # The module never reaches a site: modules/master is export-ignore, so git archive and tools/release.php build leave it out
    #[Test]
    public function theMasterIsNotShipped(): void
    {
        $this->assertMatchesRegularExpression('#^/modules/master export-ignore$#m', (string)file_get_contents(BASE_DIR.'/.gitattributes'));
    }
}
