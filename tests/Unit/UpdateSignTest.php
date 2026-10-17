<?php

declare(strict_types=1);

namespace Tests\Unit;

use Signature;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Update;

# The trust of updates in Update: manifests, key statements and notes signed in-process with keys of the run, the fallback without sodium, the path rules
final class UpdateSignTest extends TestCase
{
    private static array $pri = [];
    private static array $rec = [];

    public static function setUpBeforeClass(): void
    {
        require_once BASE_DIR.'/core/classes/update.php';
        require_once BASE_DIR.'/core/classes/signature.php';
        require_once BASE_DIR.'/core/version.php';
        self::$pri = self::getPair();
        self::$rec = self::getPair();
    }

    protected function tearDown(): void
    {
        foreach (glob(UPDATE_DIR.'/client/*') ?: [] as $file) unlink($file);
    }

    # A fresh Ed25519 pair with its public key in base64
    private static function getPair(): array
    {
        $pair = sodium_crypto_sign_keypair();
        return ['sec' => sodium_crypto_sign_secretkey($pair), 'pub' => sodium_crypto_sign_publickey($pair), 'b64' => base64_encode(sodium_crypto_sign_publickey($pair))];
    }

    # The keys of a site that trusts the pairs of this run
    private function getKeys(): array
    {
        return ['kseq' => 0, 'primary' => self::$pri['b64'], 'recovery' => self::$rec['b64'], 'revoked' => []];
    }

    # A manifest of 8.0.1 with the given fields replaced, signed by $sec under the manifest tag: its bytes and base64 signature
    private function getManifest(array $over = [], string $sec = ''): array
    {
        $now = time();
        $data = array_merge(['type' => 'release', 'version' => '8.0.1', 'channel' => 'stable', 'min' => '8.0.0', 'php' => '8.4', 'security' => false,
            'full' => ['size' => 100, 'usize' => 300, 'sha256' => str_repeat('a', 64)],
            'delta' => ['size' => 10, 'usize' => 30, 'sha256' => str_repeat('b', 64), 'from' => '8.0.0'],
            'commit' => str_repeat('c', 40), 'time' => $now, 'expires' => $now + 86400, 'kid' => substr(Update::getKeyPrint(self::$pri['pub']), 0, 16)], $over);
        $body = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        return [$body, base64_encode(sodium_crypto_sign_detached(Update::MANIFEST.$body, $sec !== '' ? $sec : self::$pri['sec']))];
    }

    # The site a manifest is held against
    private function getSite(array $over = []): array
    {
        return array_merge(['version' => '8.0.0', 'channel' => 'stable', 'seen' => [], 'now' => time()], $over);
    }

    # A key statement with its recovery signature and, when $psec is given, the primary signature
    private function getStatement(array $data, string $rsec, string $psec = ''): array
    {
        $body = json_encode(array_merge(['type' => 'keychange', 'time' => time()], $data), JSON_PRETTY_PRINT)."\n";
        return ['body' => $body, 'sig' => base64_encode(sodium_crypto_sign_detached(Update::KEYCHANGE.$body, $rsec)),
            'psig' => $psec !== '' ? base64_encode(sodium_crypto_sign_detached(Update::KEYCHANGE.$body, $psec)) : ''];
    }

    # A note signed under the note tag
    private function getNote(array $over = [], string $sec = ''): array
    {
        $data = array_merge(['type' => 'note', 'id' => 1760000000, 'level' => 'warn', 'ver' => '<8.0.2', 'sid' => '', 'until' => 0, 'ru' => 'Текст', 'en' => 'Text'], $over);
        $body = json_encode($data, JSON_UNESCAPED_UNICODE)."\n";
        return [$body, base64_encode(sodium_crypto_sign_detached(Update::NOTE.$body, $sec !== '' ? $sec : self::$pri['sec']))];
    }

    # A manifest signed by the primary key passes; a forged byte, a forged signature, the recovery key or the wrong tag are refused before anything is decoded
    #[Test]
    public function onlyThePrimaryKeySignsARelease(): void
    {
        [$body, $sig] = $this->getManifest();
        $res = Update::getManifest($body, $sig, $this->getKeys(), $this->getSite());
        $this->assertTrue($res['ok'], 'A good manifest was refused: '.$res['error']);
        $this->assertSame('8.0.1', $res['data']['version']);
        $this->assertSame('sign', Update::getManifest(str_replace('8.0.1', '8.0.9', $body), $sig, $this->getKeys(), $this->getSite())['error'], 'A forged manifest passed');
        $this->assertSame('sign', Update::getManifest($body, base64_encode(str_repeat("\0", 64)), $this->getKeys(), $this->getSite())['error'], 'A forged signature passed');
        [$rbody, $rsig] = $this->getManifest([], self::$rec['sec']);
        $this->assertSame('sign', Update::getManifest($rbody, $rsig, $this->getKeys(), $this->getSite())['error'], 'A release signed by the recovery key passed');
        $tag = base64_encode(sodium_crypto_sign_detached(Update::NOTE.$body, self::$pri['sec']));
        $this->assertSame('sign', Update::getManifest($body, $tag, $this->getKeys(), $this->getSite())['error'], 'A signature under another type tag passed');
        $this->assertSame('sign', Update::getManifest($body, $sig.' ', $this->getKeys(), $this->getSite())['error'], 'A non-canonical base64 signature passed');
    }

    # The site takes a manifest only of its channel, above its version, not older than the newest seen and not expired, and with exactly the fields of the format
    #[Test]
    public function aManifestIsHeldAgainstTheSite(): void
    {
        $keys = $this->getKeys();
        [$body, $sig] = $this->getManifest(['channel' => 'beta']);
        $this->assertSame('channel', Update::getManifest($body, $sig, $keys, $this->getSite())['error'], 'A stable site took a beta release');
        $this->assertTrue(Update::getManifest($body, $sig, $keys, $this->getSite(['channel' => 'beta']))['ok'], 'A beta site refused a beta release');
        [$body, $sig] = $this->getManifest();
        $this->assertTrue(Update::getManifest($body, $sig, $keys, $this->getSite(['channel' => 'beta']))['ok'], 'A beta site refused a stable release');
        $this->assertSame('version', Update::getManifest($body, $sig, $keys, $this->getSite(['version' => '8.0.1']))['error'], 'The installed version was offered again');
        $this->assertSame('version', Update::getManifest($body, $sig, $keys, $this->getSite(['version' => '8.1.0']))['error'], 'An older version was offered');
        $seen = $this->getSite(['seen' => ['stable' => time() + 10]]);
        $this->assertSame('stale', Update::getManifest($body, $sig, $keys, $seen)['error'], 'A manifest older than the newest seen passed');
        $this->assertSame('expired', Update::getManifest($body, $sig, $keys, $this->getSite(['now' => time() + 86400]))['error'], 'An expired manifest passed');
        foreach ([['kid' => str_repeat('0', 16)], ['extra' => 1], ['version' => '8.0.1-beta'], ['full' => ['size' => 1, 'usize' => 1, 'sha256' => 'x']],
            ['delta' => ['size' => 1, 'usize' => 1, 'sha256' => str_repeat('b', 64), 'from' => '8.0.1']], ['security' => 'no'], ['min' => '8.0.2']] as $bad) {
            [$body, $sig] = $this->getManifest($bad);
            $this->assertSame('format', Update::getManifest($body, $sig, $keys, $this->getSite())['error'], 'A malformed manifest passed: '.json_encode($bad));
        }
        [$body, $sig] = $this->getManifest(['delta' => null]);
        $this->assertTrue(Update::getManifest($body, $sig, $keys, $this->getSite())['ok'], 'A release without a delta was refused');
    }

    # A statement of the recovery key with a higher kseq replaces the primary; a lower or equal kseq, the primary's signature alone and a revoked key are refused
    #[Test]
    public function aKeyStatementNeedsTheRecoveryKey(): void
    {
        $keys = $this->getKeys();
        $new = self::getPair();
        $one = $this->getStatement(['kseq' => 1, 'primary' => $new['b64'], 'recovery' => self::$rec['b64']], self::$rec['sec']);
        $res = Update::getKeyChain($keys, [$one]);
        $this->assertTrue($res['ok'], 'A good statement was refused: '.$res['error']);
        $this->assertSame(['kseq' => 1, 'primary' => $new['b64'], 'recovery' => self::$rec['b64'], 'revoked' => [self::$pri['b64']]], $res['keys']);
        [$body, $sig] = $this->getManifest();
        $this->assertSame('sign', Update::getManifest($body, $sig, $res['keys'], $this->getSite())['error'], 'The replaced primary still signs a release');
        [$body, $sig] = $this->getManifest(['kid' => substr(Update::getKeyPrint($new['pub']), 0, 16)], $new['sec']);
        $this->assertTrue(Update::getManifest($body, $sig, $res['keys'], $this->getSite())['ok'], 'The new primary does not sign a release');
        $this->assertSame('kseq', Update::getKeyChain($res['keys'], [$one])['error'], 'A statement of the same kseq was applied twice');
        $low = $this->getStatement(['kseq' => 0, 'primary' => $new['b64'], 'recovery' => self::$rec['b64']], self::$rec['sec']);
        $this->assertSame('kseq', Update::getKeyChain($keys, [$low])['error'], 'A statement of a lower kseq passed');
        $prim = $this->getStatement(['kseq' => 1, 'primary' => $new['b64'], 'recovery' => self::$rec['b64']], self::$pri['sec']);
        $this->assertSame('sign', Update::getKeyChain($keys, [$prim])['error'], 'A statement signed by the primary key passed');
        $back = $this->getStatement(['kseq' => 2, 'primary' => self::$pri['b64'], 'recovery' => self::$rec['b64']], self::$rec['sec']);
        $chain = Update::getKeyChain($keys, [$one, $back]);
        $this->assertSame('revoked', $chain['error'], 'A revoked key came back');
        $this->assertSame(1, $chain['keys']['kseq'], 'The statements before the refused one were lost');
        $junk = $this->getStatement(['kseq' => 1, 'primary' => 'x', 'recovery' => self::$rec['b64']], self::$rec['sec']);
        $this->assertSame('format', Update::getKeyChain($keys, [$junk])['error'], 'A statement with a malformed key passed');
    }

    # Replacing the recovery key needs both signatures: the recovery key in force and the primary in force
    #[Test]
    public function aNewRecoveryKeyNeedsTwoSignatures(): void
    {
        $keys = $this->getKeys();
        $rnew = self::getPair();
        $data = ['kseq' => 1, 'primary' => self::$pri['b64'], 'recovery' => $rnew['b64']];
        $this->assertSame('sign', Update::getKeyChain($keys, [$this->getStatement($data, self::$rec['sec'])])['error'], 'The recovery key replaced itself alone');
        $this->assertSame('sign', Update::getKeyChain($keys, [$this->getStatement($data, self::$rec['sec'], $rnew['sec'])])['error'], 'A foreign second signature passed');
        $res = Update::getKeyChain($keys, [$this->getStatement($data, self::$rec['sec'], self::$pri['sec'])]);
        $this->assertTrue($res['ok'], 'Both signatures were refused: '.$res['error']);
        $this->assertSame($rnew['b64'], $res['keys']['recovery']);
        $this->assertSame([self::$rec['b64']], $res['keys']['revoked']);
        $next = $this->getStatement(['kseq' => 2, 'primary' => self::getPair()['b64'], 'recovery' => $rnew['b64']], self::$rec['sec']);
        $this->assertSame('sign', Update::getKeyChain($res['keys'], [$next])['error'], 'The replaced recovery key still signs a statement');
    }

    # keys.json: missing means the factory keys, a written chain is read back whole, a damaged file trusts no key
    #[Test]
    public function theSiteKeepsItsKeysInOneFile(): void
    {
        $this->assertSame(Update::getFactoryKeys(), Update::getKeyFile());
        $keys = ['kseq' => 3, 'primary' => self::$pri['b64'], 'recovery' => self::$rec['b64'], 'revoked' => [Update::getFactoryKeys()['primary']]];
        $this->assertTrue(Update::setKeyFile($keys));
        $this->assertSame($keys, Update::getKeyFile());
        $this->assertSame([UPDATE_DIR.'/client/keys.json'], glob(UPDATE_DIR.'/client/*'), 'The write left a temporary file');
        file_put_contents(UPDATE_DIR.'/client/keys.json', '{"kseq":3');
        $this->assertSame('', Update::getKeyFile()['primary'], 'A damaged keys.json fell back to the factory keys');
    }

    # The core ships real factory keys, never the test keys of tests/Fixtures/update, and README.md publishes the fingerprint of each
    # A test run trusts the test keys by UPDATE_KEYS
    #[Test]
    public function theFactoryKeysAreRealAndPublished(): void
    {
        $readme = strtolower((string)preg_replace('#\s+#', '', (string)file_get_contents(BASE_DIR.'/README.md')));
        foreach (['primary' => Update::PRIMARY, 'recovery' => Update::RECOVERY] as $role => $key) {
            $file = json_decode((string)file_get_contents(BASE_DIR.'/tests/Fixtures/update/'.$role.'.key'), true);
            $this->assertNotSame($file['pub'], $key, 'The factory '.$role.' key is still the test key');
            $this->assertSame(32, strlen(Update::getRawBytes($key, 32)), 'The factory '.$role.' key is no 32-byte key in canonical base64');
            $print = Update::getKeyPrint(Update::getRawBytes($key, 32));
            $this->assertStringContainsString($print, $readme, 'README.md does not publish the fingerprint of the '.$role.' factory key');
            $this->assertSame($file['pub'], Update::getFactoryKeys()[$role], 'The test run does not trust the test '.$role.' key');
        }
        $this->assertNotSame(Update::PRIMARY, Update::RECOVERY);
    }

    # A note passes under the primary key; a foreign signature, another sid, a version out of its range, an expired deadline and HTML-free limits are enforced
    #[Test]
    public function aNoteIsSignedAndAddressed(): void
    {
        $keys = $this->getKeys();
        $site = ['sid' => str_repeat('A', 32), 'version' => '8.0.1', 'now' => time()];
        [$body, $sig] = $this->getNote();
        $res = Update::getNote($body, $sig, $keys, $site);
        $this->assertTrue($res['ok'], 'A good note was refused: '.$res['error']);
        $this->assertSame('Текст', $res['data']['ru']);
        [$body, $sig] = $this->getNote([], self::$rec['sec']);
        $this->assertSame('sign', Update::getNote($body, $sig, $keys, $site)['error'], 'A note of the recovery key passed');
        [$body, $sig] = $this->getNote(['sid' => str_repeat('B', 32)]);
        $this->assertSame('sid', Update::getNote($body, $sig, $keys, $site)['error'], 'A personal note reached another site');
        [$body, $sig] = $this->getNote(['sid' => str_repeat('A', 32)]);
        $this->assertTrue(Update::getNote($body, $sig, $keys, $site)['ok'], 'A personal note missed its site');
        $this->assertSame('version', Update::getNote($body, $sig, $keys, ['version' => '8.0.2'] + $site)['error'], 'A note reached a version out of its range');
        [$body, $sig] = $this->getNote(['until' => time() - 1]);
        $this->assertSame('expired', Update::getNote($body, $sig, $keys, $site)['error'], 'An expired note passed');
        foreach ([['level' => 'loud'], ['ver' => '8.0'], ['en' => str_repeat('x', 1001)], ['ru' => ''], ['link' => 'https://example.com']] as $bad) {
            [$body, $sig] = $this->getNote($bad);
            $this->assertSame('format', Update::getNote($body, $sig, $keys, $site)['error'], 'A malformed note passed: '.json_encode($bad));
        }
        $notag = base64_encode(sodium_crypto_sign_detached(Update::MANIFEST.$body, self::$pri['sec']));
        $this->assertSame('sign', Update::getNote($body, $notag, $keys, $site)['error'], 'A note signed under the manifest tag passed');
    }

    # Without the extension Update hands the check to Signature, which takes the same signatures and refuses the same forgeries; with it Signature is never loaded
    #[Test]
    public function withoutSodiumSignatureVerifies(): void
    {
        $msg = Update::MANIFEST.'{"type":"release"}';
        $sig = sodium_crypto_sign_detached($msg, self::$pri['sec']);
        $this->assertTrue(Update::isSignValid($msg, $sig, self::$pri['pub']));
        $args = array_map(static fn(string $one): string => escapeshellarg(base64_encode($one)), [$msg, $sig, $msg.' ', self::$pri['pub'], self::$rec['pub']]);
        $cmd = escapeshellarg(BASE_DIR.'/tests/Support/sign_probe.php').' '.implode(' ', $args).' 2>&1';
        $probe = static fn(string $flag): string => (string)shell_exec(escapeshellarg(PHP_BINARY).$flag.' '.$cmd);
        $out = $probe('');
        $this->assertSame(['ext' => true, 'good' => true, 'forged' => false, 'foreign' => false, 'signature' => false], json_decode($out, true), 'With sodium: '.$out);
        $out = $probe(' -n');
        $res = json_decode($out, true);
        $this->assertIsArray($res, 'The probe did not return JSON: '.substr($out, 0, 600));
        if ($res['ext']) $this->markTestSkipped('This PHP has sodium built in, so php -n still loads it');
        $this->assertSame(['ext' => false, 'good' => true, 'forged' => false, 'foreign' => false, 'signature' => true], $res);
    }

    # Signature passes the vectors of RFC 8032 and answers as libsodium on fresh signatures, flipped bits, small-order points, non-canonical keys and S above L
    #[Test]
    public function signatureAnswersAsLibsodium(): void
    {
        $vec = [['d75a980182b10ab7d54bfed3c964073a0ee172f3daa62325af021a68f707511a', '',
            'e5564300c360ac729086e2cc806e828a84877f1eb8e5d974d873e065224901555fb8821590a33bacc61e39701cf9b46bd25bf5f0595bbe24655141438e7a100b'],
            ['3d4017c3e843895a92b70aa74d1b7ebc9c982ccf2ec4968cc0cd55f12af4660c', '72',
            '92a009a9f0d4cab8720e820b5f642540a2b27b5416503f8fb3762223ebdb69da085ac1e43e15996e458f3613d0f11d8c387b2eaeb4302aeeb00d291612bb0c00']];
        foreach ($vec as [$pub, $msg, $sig]) $this->assertTrue(Signature::isSignValid((string)hex2bin($msg), (string)hex2bin($sig), (string)hex2bin($pub)), 'RFC 8032 '.$pub);
        $sig = sodium_crypto_sign_detached('m', self::$pri['sec']);
        $cases = [[substr($sig, 0, 63).chr(0x1f), 'm', self::$pri['pub']]];
        $small = ['00', '01', '26e8958fc2b227b045c3f489f2ef98f0d5dfac05d3c63339b13802886d53fc05', 'c7176a703d4dd84fba3c0b760d10670f2a2053fa2c39ccc64ec7fd7792ac037a',
            'ecffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff7f', 'edffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff7f'];
        foreach ($small as $hex) {
            $pnt = str_pad((string)hex2bin($hex), 32, chr(0));
            foreach ([$pnt, substr($pnt, 0, 31).chr(ord($pnt[31]) | 0x80)] as $one) array_push($cases, [$sig, 'm', $one], [$one.substr($sig, 32), 'm', self::$pri['pub']]);
        }
        for ($i = 0xee; $i <= 0xff; ++$i) $cases[] = [$sig, 'm', chr($i).str_repeat(chr(0xff), 30).chr(0x7f)];
        for ($k = 0; $k < 8; ++$k) {
            $pair = self::getPair();
            $msg = random_bytes(random_int(0, 2048));
            $sig = sodium_crypto_sign_detached($msg, $pair['sec']);
            $bad = $sig;
            $bad[$k * 8] = chr(ord($bad[$k * 8]) ^ (1 << $k));
            $key = $pair['pub'];
            $key[$k * 4] = chr(ord($key[$k * 4]) ^ 1);
            array_push($cases, [$sig, $msg, $pair['pub']], [$bad, $msg, $pair['pub']], [$sig, $msg.'x', $pair['pub']], [$sig, $msg, $key], [random_bytes(64), $msg, $pair['pub']]);
        }
        foreach ($cases as [$sig, $msg, $key]) {
            try {
                $ref = sodium_crypto_sign_verify_detached($sig, $msg, $key);
            } catch (\SodiumException) {
                $ref = false;
            }
            $this->assertSame($ref, Signature::isSignValid($msg, $sig, $key), 'sig '.bin2hex($sig).' key '.bin2hex($key));
        }
    }

    # The paths a package may hold: relative, below a shipped folder or a plain top file, never a dot segment, a backslash, a drive or the client and master folders
    #[Test]
    public function aPackageHoldsOnlyShippedPaths(): void
    {
        foreach (['core/system.php', 'public/', 'README.md', 'storage/update/files.json', 'modules/news/lang/ru.php'] as $good) {
            $this->assertTrue(Update::isPackagePath($good), $good.' was refused');
        }
        foreach (['../x', 'core/../x', '/core/x', 'core\\x', 'C:/x', 'junk/x', 'core//x', 'core/./x', 'storage/update/client/keys.json', 'storage/update/master/', ''] as $bad) {
            $this->assertFalse(Update::isPackagePath($bad), $bad.' passed');
        }
        $this->assertTrue(Update::isSiteFile('config/global.php'));
        $this->assertTrue(Update::isSiteFile('public/robots.txt'));
        foreach (['config/system.php', 'config/header.php', 'config/chmod.php', 'public/sitemap.xml', 'core/system.php'] as $code) {
            $this->assertFalse(Update::isSiteFile($code), $code.' passed for a file of the site');
        }
    }

    # The version is a constant of the code in X.Y.Z with its code name; no setting carries it and the general settings no longer write it
    #[Test]
    public function theVersionLivesInTheCode(): void
    {
        $this->assertTrue(Update::isVersion(SL_VERSION), 'SL_VERSION is not X.Y.Z');
        $this->assertMatchesRegularExpression('#^[A-Za-z]+$#', SL_CODENAME);
        $this->assertArrayNotHasKey('version', require BASE_DIR.'/config/global.php', 'config/global.php still keeps the version');
        $this->assertStringNotContainsString("'version' =>", (string)file_get_contents(BASE_DIR.'/admin/modules/config.php'), 'Saving the general settings writes a version');
        $this->assertStringContainsString("\$conf['version'] = SL_VERSION.' '.SL_CODENAME;", (string)file_get_contents(BASE_DIR.'/core/system.php'));
        $this->assertStringContainsString("\$conf['version'] = SL_VERSION.' '.SL_CODENAME;", (string)file_get_contents(BASE_DIR.'/public/setup.php'));
        foreach (['8.0.1', '10.20.30'] as $good) $this->assertTrue(Update::isVersion($good));
        foreach (['8.0', '8.0.1-beta', ' 8.0.1', '../8.0.1', 8] as $bad) $this->assertFalse(Update::isVersion($bad));
        $this->assertTrue(Update::isVersionIn('8.0.1', '>=8.0.0 <8.0.2'));
        $this->assertFalse(Update::isVersionIn('8.0.2', '>=8.0.0 <8.0.2'));
        $this->assertFalse(Update::isVersionIn('8.0.1', ' <8.0.2'), 'A malformed range held a version');
    }
}
