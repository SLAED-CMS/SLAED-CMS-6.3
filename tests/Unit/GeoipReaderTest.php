<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The GeoIP reader contract: resolved countries and the peak memory of the streaming reader are measured on production code against the real corpus
final class GeoipReaderTest extends TestCase
{
    private static array $probe = [];

    # Run tests/Support/contract_probe.php once in an isolated CLI process and memoize its report, skipping when the optional country corpus is not shipped
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
