<?php

declare(strict_types=1);

namespace Tests\Unit;

use Database;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Update;

# The client of updates in Update: the id of a site, what a check sends, how an answer of the master is applied, the notes and the database steps waiting after a mark
final class UpdateClientTest extends TestCase
{
    private static array $pri = [];
    private static array $rec = [];

    public static function setUpBeforeClass(): void
    {
        require_once BASE_DIR.'/core/classes/update.php';
        require_once BASE_DIR.'/core/classes/pdo.php';
        require_once BASE_DIR.'/core/gate.php';
        self::$pri = self::getPair();
        self::$rec = self::getPair();
    }

    protected function setUp(): void
    {
        $this->assertTrue(Update::setKeyFile(['kseq' => 0, 'primary' => self::$pri['b64'], 'recovery' => self::$rec['b64'], 'revoked' => []]));
    }

    protected function tearDown(): void
    {
        foreach (array_merge(glob(UPDATE_DIR.'/client/*') ?: [], glob(UPDATE_DIR.'/step/*') ?: [], glob(LOGS_DIR.'/error_*.log') ?: []) as $file) unlink($file);
    }

    # A fresh Ed25519 pair with its public key in base64
    private static function getPair(): array
    {
        $pair = sodium_crypto_sign_keypair();
        return ['sec' => sodium_crypto_sign_secretkey($pair), 'pub' => sodium_crypto_sign_publickey($pair), 'b64' => base64_encode(sodium_crypto_sign_publickey($pair))];
    }

    # One signed item of an answer of the master: the bytes in base64 and the signature under the given tag
    private static function getSigned(string $tag, array $data, string $sec = ''): array
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
        return ['body' => base64_encode($body), 'sig' => base64_encode(sodium_crypto_sign_detached($tag.$body, $sec !== '' ? $sec : self::$pri['sec']))];
    }

    # A manifest of 9.0.1 above the code, with the given fields replaced
    private static function getOffer(array $over = []): array
    {
        $now = time();
        return self::getSigned(Update::MANIFEST, array_merge(['type' => 'release', 'version' => '9.0.1', 'channel' => 'stable', 'min' => '6.3.0', 'php' => '8.4',
            'security' => true, 'full' => ['size' => 100, 'usize' => 300, 'sha256' => str_repeat('a', 64)], 'delta' => null, 'commit' => str_repeat('c', 40),
            'time' => $now, 'expires' => $now + 86400, 'kid' => substr(Update::getKeyPrint(self::$pri['pub']), 0, 16)], $over));
    }

    # A note for every version with the given fields replaced
    private static function getNote(array $over = [], string $sec = ''): array
    {
        $data = array_merge(['type' => 'note', 'id' => 1760000000, 'level' => 'info', 'ver' => '', 'sid' => '', 'until' => 0, 'ru' => 'Текст', 'en' => 'Text'], $over);
        return ['id' => $data['id']] + self::getSigned(Update::NOTE, $data, $sec);
    }

    # A database that answers no version, so a check reports the bare engine
    private function getBase(): Database
    {
        $db = $this->createStub(Database::class);
        $db->method('getSqlQuery')->willReturn(false);
        return $db;
    }

    # Run one check against a master that answers $data, or fails when it is null; the query it was sent comes back by reference
    private function getCheck(array $conf, ?array $data, ?array &$sent = null): array
    {
        $fetch = static function (string $path, array $query) use (&$sent, $data): array {
            $sent = ['path' => $path] + $query;
            return ($data === null) ? ['code' => 0, 'body' => '', 'error' => 'master'] : ['code' => 200, 'body' => json_encode(['ok' => true, 'data' => $data]), 'error' => ''];
        };
        return Update::addCheck($this->getBase(), $conf + ['homeurl' => 'https://slaed.loc'], Update::getSite($conf['homeurl'] ?? 'https://slaed.loc'), $fetch);
    }

    # One id per address: kept while the address stays, renewed for another address or a fresh installation, and its daily minute a valid cron line of that id
    #[Test]
    public function theSiteKeepsOneIdPerAddress(): void
    {
        $one = Update::getSite('https://site.example.org/');
        $this->assertMatchesRegularExpression('#^[0-9a-f]{32}$#D', $one['sid']);
        $this->assertTrue($one['new']);
        $two = Update::getSite('https://site.example.org');
        $this->assertSame($one['sid'], $two['sid'], 'The same address got another id');
        $this->assertArrayNotHasKey('new', $two);
        $copy = Update::getSite('https://copy.example.org');
        $this->assertNotSame($one['sid'], $copy['sid'], 'A copy on another address kept the id');
        $this->assertTrue($copy['new']);
        $this->assertNotSame($copy['sid'], Update::getSite('https://copy.example.org', true)['sid'], 'A fresh installation kept the id');
        $line = Update::getCheckTime($one['sid']);
        $this->assertMatchesRegularExpression('#^([0-9]|[1-5][0-9]) ([0-9]|1[0-9]|2[0-3]) \* \* \*$#D', $line);
        $this->assertSame($line, Update::getCheckTime($one['sid']));
        $this->assertArrayNotHasKey('new', json_decode((string)file_get_contents(UPDATE_DIR.'/client/state.json'), true));
    }

    # Only a public domain is reported, lowercase and in punycode; localhost, an IP and the zones of stands never leave the site
    #[Test]
    public function onlyAPublicDomainIsReported(): void
    {
        $this->assertSame('www.example.org', Update::getHostName('https://WWW.Example.ORG:8443/sub/'));
        foreach (['http://localhost', 'https://127.0.0.1', 'https://[::1]/', 'https://site.local', 'https://slaed.test', 'https://a.localhost', 'https://intranet', ''] as $url) {
            $this->assertSame('', Update::getHostName($url), $url.' was reported');
        }
        if (function_exists('idn_to_ascii')) $this->assertSame('xn--d1acufc.xn--p1ai', Update::getHostName('https://домен.рф'));
    }

    # A journal counts the lines it gained after its mark; one shorter than its mark was rotated and counts from its start
    #[Test]
    public function theJournalsCountFromTheirMark(): void
    {
        file_put_contents(LOGS_DIR.'/error_php.log', "{\"a\":1}\n{\"a\":2}\n{\"a\":3}\n");
        $this->assertSame(3, Update::getLogCount('error_php.log', 0));
        $size = Update::getLogSizes()['php'];
        $this->assertSame(0, Update::getLogCount('error_php.log', $size));
        file_put_contents(LOGS_DIR.'/error_php.log', "{\"a\":4}\n{\"a\":5}\n", FILE_APPEND);
        $this->assertSame(2, Update::getLogCount('error_php.log', $size));
        $this->assertSame(5, Update::getLogCount('error_php.log', $size * 10));
        $this->assertSame(0, Update::getLogCount('error_sql.log', 0));
    }

    # A check sends exactly the fields of master/check: the domain only while reporting it is on, a failed installation once, the errors since the last check
    #[Test]
    public function aCheckSendsOnlyWhatTheListNames(): void
    {
        file_put_contents(LOGS_DIR.'/error_php.log', "{}\n{}\n");
        $state = Update::getSite('https://www.example.org');
        Update::setState(array_replace($state, ['fail' => ['ver' => '9.0.0', 'step' => 6]]));
        $conf = ['homeurl' => 'https://www.example.org', 'update' => ['check' => ['channel' => 'beta', 'upsite' => '1']]];
        $res = $this->getCheck($conf, ['manifest' => null, 'keys' => [], 'notes' => []], $sent);
        $this->assertTrue($res['ok']);
        $this->assertSame(['path', 'sid', 'v', 'ch', 'kseq', 'php', 'db', 'ext', 'ep', 'es', 'host', 'fv', 'fs'], array_keys($sent));
        $this->assertSame(['/api/v1/master/check', $state['sid'], SL_VERSION, 'beta', 0], [$sent['path'], $sent['sid'], $sent['v'], $sent['ch'], $sent['kseq']]);
        $this->assertSame(['www.example.org', '9.0.0', 6, 2, 0], [$sent['host'], $sent['fv'], $sent['fs'], $sent['ep'], $sent['es']]);
        $this->assertMatchesRegularExpression('#^\d+\.\d+\.\d+$#D', $sent['php']);
        $conf['update']['check']['upsite'] = '0';
        $this->getCheck($conf, ['manifest' => null, 'keys' => [], 'notes' => []], $sent);
        $this->assertArrayNotHasKey('host', $sent, 'The domain was sent with reporting it switched off');
        $this->assertArrayNotHasKey('fv', $sent, 'A failed installation was reported twice');
        $this->assertSame(0, $sent['ep'], 'Errors of an earlier check were counted again');
    }

    # A master that does not answer leaves the release found and the journal marks as they were and names its reason
    #[Test]
    public function aSilentMasterChangesNothingButTheTime(): void
    {
        $this->getCheck([], ['manifest' => self::getOffer(), 'keys' => [], 'notes' => []]);
        file_put_contents(LOGS_DIR.'/error_sql.log', "{}\n");
        $res = $this->getCheck([], null);
        $this->assertSame(['ok' => false, 'error' => 'master', 'found' => '9.0.1'], $res);
        $state = Update::getState();
        $this->assertSame('master', $state['error']);
        $this->assertGreaterThan(0, $state['last']);
        $this->getCheck([], ['manifest' => null, 'keys' => [], 'notes' => []], $sent);
        $this->assertSame(1, $sent['es'], 'A failed check advanced the mark of the journal');
    }

    # The release offered becomes the version found with its flags; no manifest, as after a pause, drops it, and a forged or an older one is refused with its reason
    #[Test]
    public function thePauseOfTheMasterDropsTheRelease(): void
    {
        $res = $this->getCheck([], ['manifest' => self::getOffer(), 'keys' => [], 'notes' => []]);
        $this->assertSame(['ok' => true, 'error' => '', 'found' => '9.0.1'], $res);
        $found = Update::getState()['found'];
        $this->assertSame(['9.0.1', 'stable', true], [$found['version'], $found['channel'], $found['security']]);
        $this->assertSame('', $this->getCheck([], ['manifest' => null, 'keys' => [], 'notes' => []])['found'], 'A paused release is still offered');
        $this->assertSame([], Update::getState()['found']);
        $forged = self::getOffer();
        $forged['sig'] = self::getOffer(['version' => '9.0.2'])['sig'];
        $this->assertSame(['ok' => false, 'error' => 'sign', 'found' => ''], $this->getCheck([], ['manifest' => $forged, 'keys' => [], 'notes' => []]));
        $old = self::getOffer(['time' => time() - 3600, 'expires' => time() + 3600]);
        $this->assertSame(['ok' => false, 'error' => 'stale', 'found' => ''], $this->getCheck([], ['manifest' => $old, 'keys' => [], 'notes' => []]));
        $this->assertSame('', $this->getCheck([], ['manifest' => self::getOffer(['version' => SL_VERSION]), 'keys' => [], 'notes' => []])['error'], 'The own version is a refusal');
        $beta = self::getOffer(['channel' => 'beta', 'time' => time() + 5, 'expires' => time() + 86400]);
        $this->assertSame('channel', $this->getCheck([], ['manifest' => $beta, 'keys' => [], 'notes' => []])['error'], 'A stable site took a beta release');
    }

    # Only the notes signed for this site and its version and not expired are kept; a hidden note stays hidden until the master drops it
    #[Test]
    public function theNotesAreHeldAgainstTheSite(): void
    {
        $sid = Update::getSite('https://slaed.loc')['sid'];
        $notes = [self::getNote(), self::getNote(['id' => 1760000002, 'sid' => $sid, 'level' => 'danger']), self::getNote(['id' => 1760000003, 'sid' => str_repeat('a', 32)]),
            self::getNote(['id' => 1760000004, 'ver' => '<6.0.0']), self::getNote(['id' => 1760000005, 'until' => time() - 60]),
            self::getNote(['id' => 1760000006], self::$rec['sec'])];
        $this->getCheck([], ['manifest' => null, 'keys' => [], 'notes' => $notes]);
        $this->assertSame([1760000002, 1760000000], array_column(Update::getNoteList(), 'id'), 'A note for another site, version or time or with a forged signature was kept');
        $this->assertTrue(Update::setNoteHide(1760000000));
        $this->assertFalse(Update::setNoteHide(1760000003), 'A note the site never accepted was hidden');
        $this->assertSame([1760000002], array_column(Update::getNoteList(), 'id'));
        $this->getCheck([], ['manifest' => null, 'keys' => [], 'notes' => $notes]);
        $this->assertSame([1760000002], array_column(Update::getNoteList(), 'id'), 'A hidden note came back with the next check');
        $this->getCheck([], ['manifest' => null, 'keys' => [], 'notes' => [$notes[1]]]);
        $this->assertSame(['list' => 1, 'hide' => 0], array_map('count', Update::getNoteFile()), 'A note the master dropped is still kept or hidden');
    }

    # A key statement of the recovery key with a higher kseq replaces the primary key on the site; the next release verifies only under the new one
    #[Test]
    public function aKeyStatementReachesTheSite(): void
    {
        $new = self::getPair();
        $body = json_encode(['type' => 'keychange', 'kseq' => 1, 'primary' => $new['b64'], 'recovery' => self::$rec['b64'], 'time' => time()])."\n";
        $one = ['kseq' => 1, 'body' => base64_encode($body), 'sig' => base64_encode(sodium_crypto_sign_detached(Update::KEYCHANGE.$body, self::$rec['sec'])), 'psig' => ''];
        $bad = ['kseq' => 2] + self::getSigned(Update::KEYCHANGE, ['type' => 'keychange', 'kseq' => 2, 'primary' => self::$pri['b64'], 'recovery' => $new['b64'],
            'time' => time()], self::$pri['sec']) + ['psig' => ''];
        $this->getCheck([], ['manifest' => null, 'keys' => [$one, $bad], 'notes' => []], $sent);
        $keys = Update::getKeyFile();
        $this->assertSame([1, $new['b64'], [self::$pri['b64']]], [$keys['kseq'], $keys['primary'], $keys['revoked']], 'The statement was not applied or the forged one was');
        $this->assertSame('sign', $this->getCheck([], ['manifest' => self::getOffer(['time' => time() + 10]), 'keys' => [], 'notes' => []], $sent)['error']);
        $this->assertSame(1, $sent['kseq'], 'The check does not report the kseq it holds');
    }

    # The steps after a mark: each step file up to the code in the order of versions, then the code itself even without a file; nothing after the code or at the mark
    #[Test]
    public function theStepsWaitAfterTheMark(): void
    {
        mkdir(UPDATE_DIR.'/step', 0777, true);
        foreach (['6.2.0.sql', '6.2.5.sql', '6.2.10.php', '6.2.10.sql', '6.3.1.sql', 'note.sql'] as $name) file_put_contents(UPDATE_DIR.'/step/'.$name, "SELECT 1;\n");
        $this->assertSame(['6.2.5' => ['sql' => true, 'php' => false], '6.2.10' => ['sql' => true, 'php' => true], SL_VERSION => ['sql' => false, 'php' => false]],
            Update::getStepList('6.2.0'));
        $this->assertSame(['6.2.0', '6.2.5', '6.2.10', SL_VERSION], array_keys(Update::getStepList('')));
        $this->assertSame([], Update::getStepList(SL_VERSION));
    }

    # The gate reads the newest mark by the order of versions, none without marks, and no installation at all without the file
    #[Test]
    public function theGateReadsTheNewestMark(): void
    {
        $dir = dirname(UPDATE_DIR).'/gate';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $this->assertNull(getStepMark($dir));
        file_put_contents($dir.'/update.php', "<?php\nreturn ['update' => ['points' => '6.3.0']];\n");
        $this->assertSame('', getStepMark($dir));
        file_put_contents($dir.'/update.php', "<?php\nreturn ['update' => ['steps' => ['6.9.0' => 'x', '6.10.0' => 'y', 'bad' => 'z']]];\n");
        $this->assertSame('6.10.0', getStepMark($dir));
        unlink($dir.'/update.php');
        rmdir($dir);
    }
}
