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
    private const ROUTE_ALLOW = ['name' => '#^shop$#', 'op' => '#^$#', 'cat' => '#^[1-9][0-9]{0,8}$#', 'num' => '#^[1-9][0-9]{0,8}$#'];
    private static array $probes = [];
    private array $temps = [];

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2).'/core/classes/cache.php';
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(\Cache::class, 'until'))->setValue(null, null);
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

    # Run the comment writer of stage S19.4 through tests/Support/route_probe.php: a disposable database, a real HTTP stand and a child that writes as the admin entry
    private function getCommentRun(): array
    {
        if (isset(self::$probes['comment'])) return self::$probes['comment'];
        $script = dirname(__DIR__).'/Support/route_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_page_comment';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' cache 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The route probe did not return JSON: '.substr($out, 0, 600));
        $this->assertSame('', $data['error'], 'The route probe failed');
        $this->assertTrue($data['clean'], 'The route probe left a disposable database on the server');
        $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The run wrote PHP or SQL errors');
        return self::$probes['comment'] = $data['runs']['cache'];
    }

    # A moderator approving a comment of a Node material holds the write guard from before its transaction until the generation moved after its commit
    # The administrative entry bumps early on its first write statement, so without the guard a list rendered from the old count was stored under the new generation for good
    #[Test]
    public function anApprovedCommentPublishesNoPageOfTheOldCount(): void
    {
        $run = $this->getCommentRun();
        $this->assertSame(['done' => true], $run['child'], 'The approval failed');
        $this->assertSame(1, $run['during']['early'], 'The approval never reached the early bump of the administrative entry');
        $this->assertSame(1, $run['during']['guards'], 'The approval wrote without a guard of the page cache');
        $this->assertSame(200, $run['during']['code']);
        $this->assertSame(0, $run['during']['pages'], 'A list rendered during the approval was stored in the page cache');
        $this->assertSame(['gen' => 1, 'guards' => 0, 'comnum' => 2, 'status' => 1], $run['after'], 'The generation did not move after the commit, or the guard stayed');
    }

    # A URL without any query part is valid and yields an empty parameter map
    #[Test]
    public function pathWithoutQueryIsValidAndEmpty(): void
    {
        $this->assertSame([], \Cache::getQueryVars('/index.php', self::ROUTE_ALLOW));
        $this->assertSame([], \Cache::getQueryVars('/index.php?', self::ROUTE_ALLOW));
        $this->assertSame([], \Cache::getQueryVars('/', self::ROUTE_ALLOW));
    }

    # Known tracking parameters are silently dropped instead of rejecting the request
    #[Test]
    public function trackingParametersAreDroppedNotRejected(): void
    {
        $url = '/index.php?utm_source=x&utm_medium=y&gclid=abc&fbclid=def&yclid=1&_openstat=z';
        $this->assertSame([], \Cache::getQueryVars($url, self::ROUTE_ALLOW));
        $url = '/index.php?name=shop&utm_campaign=promo&cat=3';
        $this->assertSame(['name' => 'shop', 'cat' => '3'], \Cache::getQueryVars($url, self::ROUTE_ALLOW));
    }

    # A fully valid list query returns the decoded parameter map
    #[Test]
    public function validListQueryReturnsDecodedMap(): void
    {
        $vars = \Cache::getQueryVars('/index.php?name=shop&cat=3&num=2', self::ROUTE_ALLOW);
        $this->assertSame(['name' => 'shop', 'cat' => '3', 'num' => '2'], $vars);
        $this->assertSame(['name' => 'shop', 'op' => ''], \Cache::getQueryVars('/index.php?name=shop&op=', self::ROUTE_ALLOW));
    }

    # Any query key outside the route contract makes the request non-cacheable
    #[Test]
    public function unknownKeysRejectTheRequest(): void
    {
        $this->assertNull(\Cache::getQueryVars('/index.php?name=shop&foo=1', self::ROUTE_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?rnd991234', self::ROUTE_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?=x', self::ROUTE_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?name=shop&amp;cat=1', self::ROUTE_ALLOW));
    }

    # A semantic key appearing more than once makes the request non-cacheable
    #[Test]
    public function duplicateKeysRejectTheRequest(): void
    {
        $this->assertNull(\Cache::getQueryVars('/index.php?cat=1&cat=2', self::ROUTE_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?name=shop&%6Eame=shop', self::ROUTE_ALLOW));
    }

    # Values that do not match the per-key format make the request non-cacheable
    #[Test]
    public function malformedValuesRejectTheRequest(): void
    {
        $this->assertNull(\Cache::getQueryVars('/index.php?cat=abc', self::ROUTE_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?cat=0', self::ROUTE_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?cat=-1', self::ROUTE_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?cat=1e3', self::ROUTE_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?cat=1234567890', self::ROUTE_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?cat', self::ROUTE_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?num=+1', self::ROUTE_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?op=liste', self::ROUTE_ALLOW));
        $this->assertNull(\Cache::getQueryVars('/index.php?name=SHOP', self::ROUTE_ALLOW));
    }

    # Percent-encoded keys and values decode to the same parameter map as their plain form
    #[Test]
    public function alternateEncodingsDecodeToTheSameMap(): void
    {
        $plain = \Cache::getQueryVars('/index.php?name=shop&cat=3', self::ROUTE_ALLOW);
        $coded = \Cache::getQueryVars('/index.php?name=%73%68%6F%70&cat=%33', self::ROUTE_ALLOW);
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

    # The real getCacheRouteVars() accepts a clean request and produces a stable identity; only the list of a registered Node type is cached,
    # with the parameters name, cat and num alone, while a module outside the registry, a material and a letter filter are rendered live
    #[Test]
    public function cleanRouteProducesStableIdentity(): void
    {
        $route = $this->getProbe('route');
        $again = $this->getProbe('routenum');
        $this->assertIsArray($route['vars']);
        $this->assertFalse($route['cache'], 'A module outside the Node registry is cached');
        $this->assertMatchesRegularExpression('#^[a-f0-9]{40}$#', $route['hash']);
        $this->assertSame($route['hash'], $again['hash'], 'identity must be stable across processes');
        $node = $this->getProbe('routenode');
        $this->assertTrue($node['cache'], 'The list of a registered Node type is not cached');
        $this->assertIsArray($node['vars'], 'The clean list address of a Node type broke the parameter contract');
        $this->assertFalse($this->getProbe('routenodeop')['cache'], 'A material of a Node type is cached');
        $this->assertFalse($this->getProbe('routenodelet')['cache'], 'A letter filter of a Node type is cached');
    }

    # The start page draws one type of the home list per request, so its identity carries the type it drew and every home type is stored apart
    # None of them shares an entry with the plain list of the same type, whose layout is not the one of the start page
    #[Test]
    public function eachHomeTypeHasItsOwnIdentity(): void
    {
        $news = $this->getProbe('routehomenews');
        $docs = $this->getProbe('routehomedocs');
        $plain = $this->getProbe('routenewsplain');
        $this->assertTrue($news['cache'], 'The start page of a Node type is not cached');
        $this->assertSame('news', $news['vars']['home'] ?? null, 'The identity of the start page does not carry the type it drew');
        $this->assertNotSame($news['hash'], $docs['hash'], 'Two home types share one entry, so the first one drawn is served for both');
        $this->assertNotSame($news['hash'], $plain['hash'], 'The start page shares its entry with the plain list of the same type');
    }

    # A sidecar written next to one body validates that exact body and reports its dynamic flag
    #[Test]
    public function sidecarValidatesTheStoredBody(): void
    {
        $file = $this->getScratchFile();
        $body = '<html>cached body</html>';
        $this->assertTrue(\Cache::setBody($file, $body));
        $this->assertTrue(\Cache::setMeta($file, $body, false));
        $this->assertSame(['dyn' => false, 'valid' => true, 'until' => 0], \Cache::getMeta($file, $body));
        $this->assertTrue(\Cache::setMeta($file, $body, true));
        $this->assertSame(['dyn' => true, 'valid' => true, 'until' => 0], \Cache::getMeta($file, $body));
    }

    # A page bound to the moment its data changes keeps the earliest moment of the request; null binds it without a moment, and the sidecar carries the bound
    #[Test]
    public function boundPageCarriesItsEarliestMoment(): void
    {
        $file = $this->getScratchFile();
        $body = '<html>node list</html>';
        \Cache::setPageUntil(null);
        \Cache::setMeta($file, $body, false);
        $this->assertSame(PHP_INT_MAX, \Cache::getMeta($file, $body)['until'], 'a page bound without a moment is still bound');
        \Cache::setPageUntil(2000000000);
        \Cache::setPageUntil(1900000000);
        \Cache::setPageUntil(null);
        \Cache::setMeta($file, $body, false);
        $this->assertSame(['dyn' => false, 'valid' => true, 'until' => 1900000000], \Cache::getMeta($file, $body));
        file_put_contents($file.'.json', json_encode(['sha1' => sha1($body), 'dyn' => 0, 'until' => -5]));
        $this->assertSame(1, \Cache::getMeta($file, $body)['until'], 'a negative moment is the past');
        file_put_contents($file.'.json', json_encode(['sha1' => sha1($body), 'dyn' => 0, 'until' => '1900000000']));
        $this->assertSame(1, \Cache::getMeta($file, $body)['until'], 'a moment that is no integer is the past');
    }

    # The page head and foot never serve a bound entry once its moment has come, neither fresh nor stale, and never hand a bound page to a browser cache
    #[Test]
    public function boundPageIsNeitherServedPastItsMomentNorPublic(): void
    {
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/core/system.php');
        $head = substr($code, strpos($code, "\nfunction setHead("), 4000);
        $this->assertSame(2, substr_count($head, 'if ($meta && ($meta[\'until\'] === 0 || time() < $meta[\'until\'])) {'), 'the fresh and the stale serve both check the moment');
        $this->assertStringContainsString('if (!$meta[\'dyn\'] && $days > 0 && $meta[\'until\'] === 0) {', $head, 'only an unbound page is public');
        $foot = substr($code, strpos($code, "\nfunction setFoot("), 5000);
        $this->assertStringContainsString('if ($done && !$dyn && !$bound && $days > 0) {', $foot, 'a bound page is never stored public');
        $this->assertStringContainsString('if (($dyn || $bound) && !headers_sent()) Cache::setHeaders(false);', $foot, 'a bound page is sent no-store');
    }

    # A missing, corrupt, or mismatched sidecar fails closed to dynamic and to a past moment, so the body is served neither public nor at all
    #[Test]
    public function brokenSidecarFailsClosedToDynamic(): void
    {
        $file = $this->getScratchFile();
        $body = '<html>cached body</html>';
        $shut = ['dyn' => true, 'valid' => false, 'until' => 1];
        $this->assertSame($shut, \Cache::getMeta($file, $body), 'a missing sidecar must fail closed');
        \Cache::setMeta($file, $body, false);
        $this->assertSame($shut, \Cache::getMeta($file, $body.'tampered'), 'a mismatched body must fail closed');
        file_put_contents($file.'.json', '{"sha1":');
        $this->assertSame($shut, \Cache::getMeta($file, $body), 'a corrupt sidecar must fail closed');
        file_put_contents($file.'.json', json_encode(['dyn' => 0]));
        $this->assertSame($shut, \Cache::getMeta($file, $body), 'an incomplete sidecar must fail closed');
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
