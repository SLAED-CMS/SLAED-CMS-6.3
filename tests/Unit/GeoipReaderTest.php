<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the 2026 GeoIP reader contract: the lookups run against the real corpus
 * through tests/Support/contract_probe.php in an isolated CLI process, so both the resolved
 * countries and the peak memory of the streaming reader are measured on production code. The
 * scenario is skipped when an installation ships without the optional country corpus.
 */
final class GeoipReaderTest extends TestCase
{
    private static array $probe = [];

    # Run the GeoIP probe once and memoize its report for every scenario in this class
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' geoip 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe geoip did not return JSON: '.$out);
        if (($data['size'] ?? 0) < 1) $this->markTestSkipped('country corpus is not installed');
        return self::$probe = $data;
    }

    # IPv4 and IPv6 addresses resolve to plain two-letter country codes
    #[Test]
    public function ipvFourAndIpvSixResolveToCountryCodes(): void
    {
        $data = $this->getProbe();
        $this->assertMatchesRegularExpression('#^[A-Z]{2}$#', $data['country']);
        $this->assertMatchesRegularExpression('#^[A-Z]{2}$#', $data['four']);
        $this->assertMatchesRegularExpression('#^[A-Z]{2}$#', $data['sixte']);
    }

    # Repeated lookups of one address stay stable and malformed input resolves to an empty result
    #[Test]
    public function lookupsAreStableAndMalformedInputStaysEmpty(): void
    {
        $data = $this->getProbe();
        $this->assertTrue($data['stable'], 'repeated lookups must return the same country');
        $this->assertSame('', $data['bad'], 'a malformed address must not resolve');
    }

    # The reader streams the corpus instead of loading it, so lookups add no corpus-sized allocation
    #[Test]
    public function lookupsDoNotAllocateTheWholeCorpus(): void
    {
        $data = $this->getProbe();
        $this->assertGreaterThan(1048576, $data['size'], 'the corpus must be large enough for this check');
        $this->assertLessThan((int)($data['size'] / 4), $data['grow'], 'lookups must not allocate the corpus');
    }
}
