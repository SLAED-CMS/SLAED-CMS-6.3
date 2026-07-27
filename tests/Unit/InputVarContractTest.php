<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for the real getVar() helper: every scenario runs against the booted core through
 * tests/Support/contract_probe.php in an isolated CLI process, so the assertions cover production
 * code instead of a replica. The scalar branch reads filter_input(), which has no request payload
 * in CLI, therefore only array and nested-array keys are exercised here.
 */
final class InputVarContractTest extends TestCase
{
    private static array $probe = [];

    # Run the getVar probe once and memoize its report for every scenario in this class
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' getvar 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe getvar did not return JSON: '.$out);
        return self::$probe = $data;
    }

    # A typed array key keeps dropping empty values, an untyped one returns the payload untouched so index alignment survives
    #[Test]
    public function arrayKeyKeepsIndexesOnlyWithoutType(): void
    {
        $data = $this->getProbe();
        $this->assertSame(['red', '', 'blue'], $data['all_raw']);
        $this->assertSame(['red', 'blue'], $data['all_typed']);
        $this->assertSame(['one', 'three'], $data['branch_untouched']);
    }

    # A single element is addressed by index, missing and empty elements fall back to the default
    #[Test]
    public function indexedKeyReturnsOneElement(): void
    {
        $data = $this->getProbe();
        $this->assertSame('blue', $data['idx_one']);
        $this->assertSame('fallback', $data['idx_empty']);
        $this->assertSame('fallback', $data['idx_missing']);
    }

    # Nested form fields are addressable through leading path segments, for the whole branch and for one element
    #[Test]
    public function nestedKeysWalkTheFormPath(): void
    {
        $data = $this->getProbe();
        $this->assertSame(['_A' => 'Привет', 'plain' => ''], $data['nest_all']);
        $this->assertSame('Привет', $data['nest_named']);
        $this->assertSame('Hallo', $data['nest_other']);
        $this->assertSame('found', $data['nest_deep']);
        $this->assertSame(['c' => 'found'], $data['nest_deep_all']);
    }

    # A missing branch yields an empty array, and the get/req sources honour the same nested syntax
    #[Test]
    public function nestedKeysRespectSourceAndMissingBranch(): void
    {
        $data = $this->getProbe();
        $this->assertSame([], $data['nest_missing']);
        $this->assertSame('from get', $data['nest_get']);
        $this->assertSame(['red', '', 'blue'], $data['nest_req']);
    }

    # An array reached through a scalar key counts as missing, and an array default never reaches a scalar filter
    #[Test]
    public function arrayValueNeverReachesScalarFilter(): void
    {
        $data = $this->getProbe();
        $this->assertSame('fallback', $data['scalar_key_on_array']);
        $this->assertSame([], $data['array_default_scalar_filter']);
    }
}
