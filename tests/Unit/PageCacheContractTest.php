<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the 2026 page-cache performance contracts running against production code:
 * Cache::getQueryVars() is called directly, while the dynamic-region marker contract, the v2 stats
 * cookie, and the cacheable-route decision are exercised through tests/Support/contract_probe.php,
 * which boots the real core in an isolated CLI process (requires the local OSPanel stack, like the
 * browser checks). The num=1 identity collapse runs at the HTTP layer and is covered by the live
 * checks recorded in docs/PERFORMANCE-REMEDIATION-2026.md because filter_input() cannot be driven
 * from CLI.
 */
final class PageCacheContractTest extends TestCase
{
    private const NEWS_ALLOW = ['name' => '#^news$#', 'op' => '#^$#', 'cat' => '#^[1-9][0-9]{0,8}$#', 'num' => '#^[1-9][0-9]{0,8}$#'];
    private static array $probes = [];
    private array $temps = [];

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2).'/core/classes/cache.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->temps as $file) {
            if (is_file($file)) unlink($file);
            if (is_file($file.'.json')) unlink($file.'.json');
        }
        $this->temps = [];
    }

    # Reserve one scratch cache path for a sidecar scenario and register it for cleanup
    private function getScratchFile(): string
    {
        $file = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_sidecar_'.bin2hex(random_bytes(6)).'.html';
        $this->temps[] = $file;
        return $file;
    }

    # Run one probe scenario in a fresh PHP process and decode its JSON report, memoized per scenario
    private function getProbe(string $mode): array
    {
        if (isset(self::$probes[$mode])) return self::$probes[$mode];
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(PHP_BINARY.' '.$script.' '.$mode.' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe '.$mode.' did not return JSON: '.$out);
        return self::$probes[$mode] = $data;
    }

    # A URL without any query part is valid and yields an empty parameter map
    #[Test]
    public function pathWithoutQueryIsValidAndEmpty(): void
    {
        $this->assertSame([], \Cache::getQueryVars('/index.php', self::NEWS_ALLOW));
        $this->assertSame([], \Cache::getQueryVars('/index.php?', self::NEWS_ALLOW));
        $this->assertSame([], \Cache::getQueryVars('/', self::NEWS_ALLOW));
    }

    # Known tracking parameters are silently dropped instead of rejecting the request
    #[Test]
    public function trackingParametersAreDroppedNotRejected(): void
    {
        $url = '/index.php?utm_source=x&utm_medium=y&gclid=abc&fbclid=def&yclid=1&_openstat=z';
        $this->assertSame([], \Cache::getQueryVars($url, self::NEWS_ALLOW));
        $url = '/index.php?name=news&utm_campaign=promo&cat=3';
        $this->assertSame(['name' => 'news', 'cat' => '3'], \Cache::getQueryVars($url, self::NEWS_ALLOW));
    }

    # A fully valid news list query returns the decoded parameter map
    #[Test]
    public function validNewsListQueryReturnsDecodedMap(): void
    {
        $vars = \Cache::getQueryVars('/index.php?name=news&cat=3&num=2', self::NEWS_ALLOW);
        $this->assertSame(['name' => 'news', 'cat' => '3', 'num' => '2'], $vars);
        $this->assertSame(['name' => 'news', 'op' => ''], \Cache::getQueryVars('/index.php?name=news&op=', self::NEWS_ALLOW));
    }

    # Any query key outside the route contract makes the request non-cacheable
    #[Test]
    public function unknownKeysRejectTheRequest(): void
    {
        $this->assertNull(\Cache::getQueryVars('/index.php?name=news&foo=1', self::NEWS_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?rnd991234', self::NEWS_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?=x', self::NEWS_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?name=news&amp;cat=1', self::NEWS_ALLOW));
    }

    # A semantic key appearing more than once makes the request non-cacheable
    #[Test]
    public function duplicateKeysRejectTheRequest(): void
    {
        $this->assertNull(\Cache::getQueryVars('/index.php?cat=1&cat=2', self::NEWS_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?name=news&%6Eame=news', self::NEWS_ALLOW));
    }

    # Values that do not match the per-key format make the request non-cacheable
    #[Test]
    public function malformedValuesRejectTheRequest(): void
    {
        $this->assertNull(\Cache::getQueryVars('/index.php?cat=abc', self::NEWS_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?cat=0', self::NEWS_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?cat=-1', self::NEWS_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?cat=1e3', self::NEWS_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?cat=1234567890', self::NEWS_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?cat', self::NEWS_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?num=+1', self::NEWS_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?op=liste', self::NEWS_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?name=NEWS', self::NEWS_ALLOW));
    }

    # Percent-encoded keys and values decode to the same parameter map as their plain form
    #[Test]
    public function alternateEncodingsDecodeToTheSameMap(): void
    {
        $plain = \Cache::getQueryVars('/index.php?name=news&cat=3', self::NEWS_ALLOW);
        $coded = \Cache::getQueryVars('/index.php?name=%6E%65%77%73&cat=%33', self::NEWS_ALLOW);
        $this->assertSame($plain, $coded);
    }

    # The real checkDynamicMark() accepts exactly the approved type and parameter combinations
    #[Test]
    public function markerContractAcceptsOnlyApprovedCombinations(): void
    {
        $valid = $this->getProbe('core')['valid'];
        $pass = ['token_ajax', 'token_account', 'token_scheduler', 'captcha_login', 'captcha_register', 'captcha_comment', 'captcha_contact', 'voting_id'];
        foreach ($pass as $key) {
            $this->assertTrue($valid[$key], $key.' must be accepted');
        }
        $fail = ['token_empty', 'token_admin', 'captcha_admin', 'captcha_empty', 'voting_zero', 'voting_neg', 'voting_huge', 'voting_inject', 'shell'];
        foreach ($fail as $key) {
            $this->assertFalse($valid[$key], $key.' must be rejected');
        }
    }

    # The real getDynamicMark()/setDynamicRegions() sign, substitute, and neutralize markers correctly
    #[Test]
    public function realMarkersSignSubstituteAndStayInertWhenForged(): void
    {
        $data = $this->getProbe('core');
        $this->assertTrue($data['mark_shape'], 'valid marker must have the signed shape');
        $this->assertTrue($data['sub_token'], 'signed token marker must substitute to a live 64-hex token');
        $this->assertTrue($data['forged_literal'], 'forged marker must stay literal text');
        $this->assertTrue($data['junk_empty'], 'well-signed but unapproved marker must render empty');
    }

    # The real getDynamicMark() poisons the cacheable build, logs the rejection to the probe-scoped log, and returns nothing for invalid input
    #[Test]
    public function rejectedMarkerPoisonsBuildAndReturnsNothing(): void
    {
        $data = $this->getProbe('core');
        $this->assertFalse($data['poison_before']);
        $this->assertTrue($data['reject_empty']);
        $this->assertTrue($data['poison_after']);
        $this->assertTrue($data['reject_logged'], 'rejection must be logged through the site logger');
    }

    # The real updateStatsCookie() continues a v2 session, keeps the country cache, and exposes no uniqueness state
    #[Test]
    public function statsCookieVtwoRoundTripsWithoutUniquenessState(): void
    {
        $data = $this->getProbe('core');
        $this->assertSame(['sess', 'country'], $data['v2_keys']);
        $this->assertSame(5, $data['v2_depth']);
        $this->assertFalse($data['v2_isnew']);
        $this->assertSame('DE', $data['v2_country']);
    }

    # The real updateStatsCookie() discards legacy v1 cookies and starts a fresh session
    #[Test]
    public function statsCookieVoneIsDiscarded(): void
    {
        $data = $this->getProbe('core');
        $this->assertTrue($data['v1_isnew']);
        $this->assertSame(1, $data['v1_depth']);
    }

    # The real getCacheRouteVars()/checkPageCache() accept a clean request and produce a stable identity
    #[Test]
    public function cacheableRouteProducesStableIdentity(): void
    {
        $route = $this->getProbe('route');
        $again = $this->getProbe('routenum');
        $this->assertIsArray($route['vars']);
        $this->assertTrue($route['cache']);
        $this->assertMatchesRegularExpression('#^[a-f0-9]{40}$#', $route['hash']);
        $this->assertSame($route['hash'], $again['hash'], 'identity must be stable across processes');
    }

    # A sidecar written next to one body validates that exact body and reports its dynamic flag
    #[Test]
    public function sidecarValidatesTheStoredBody(): void
    {
        $file = $this->getScratchFile();
        $body = '<html>cached body</html>';
        $this->assertTrue(\Cache::setBody($file, $body));
        $this->assertTrue(\Cache::setMeta($file, $body, false));
        $this->assertSame(['dyn' => false, 'valid' => true], \Cache::getMeta($file, $body));
        $this->assertTrue(\Cache::setMeta($file, $body, true));
        $this->assertSame(['dyn' => true, 'valid' => true], \Cache::getMeta($file, $body));
    }

    # A missing, corrupt, or mismatched sidecar fails closed to dynamic so the body is never served with public headers
    #[Test]
    public function brokenSidecarFailsClosedToDynamic(): void
    {
        $file = $this->getScratchFile();
        $body = '<html>cached body</html>';
        $this->assertSame(['dyn' => true, 'valid' => false], \Cache::getMeta($file, $body), 'a missing sidecar must fail closed');
        \Cache::setMeta($file, $body, false);
        $this->assertSame(['dyn' => true, 'valid' => false], \Cache::getMeta($file, $body.'tampered'), 'a mismatched body must fail closed');
        file_put_contents($file.'.json', '{"sha1":');
        $this->assertSame(['dyn' => true, 'valid' => false], \Cache::getMeta($file, $body), 'a corrupt sidecar must fail closed');
        file_put_contents($file.'.json', json_encode(['dyn' => 0]));
        $this->assertSame(['dyn' => true, 'valid' => false], \Cache::getMeta($file, $body), 'an incomplete sidecar must fail closed');
    }

    # The real contract makes unknown query keys and foreign hosts non-cacheable
    #[Test]
    public function unknownKeysAndForeignHostsRenderLive(): void
    {
        $bad = $this->getProbe('routebad');
        $this->assertNull($bad['vars']);
        $this->assertFalse($bad['cache']);
        $evil = $this->getProbe('routehost');
        $this->assertNull($evil['vars']);
        $this->assertFalse($evil['cache']);
    }
}
