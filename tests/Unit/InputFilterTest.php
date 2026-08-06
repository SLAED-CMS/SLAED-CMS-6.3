<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Safety net for the input filters in core/security.php. The scenarios run against the booted core
 * through tests/Support/contract_probe.php, so the assertions describe the shipped functions rather
 * than a replica: the earlier revision of this file copied the algorithms and drifted away from them.
 */
final class InputFilterTest extends TestCase
{
    private static array $probe = [];

    # Run the filter probe once and memoize its report for every scenario in this class
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' filters 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe filters did not return JSON: '.$out);
        return self::$probe = $data;
    }

    # filterNum() keeps digits only and always yields an int, so a sign or letters can never reach a query
    #[Test]
    public function numFilterExtractsDigitsOnly(): void
    {
        $this->assertSame([123, 123, 0, 0, 5, 999999999], $this->getProbe()['num']);
    }

    # filterWord() keeps letters, digits and a small punctuation set; angle brackets and quotes are dropped
    #[Test]
    public function wordFilterStripsMarkupAndQuotes(): void
    {
        $word = $this->getProbe()['word'];
        $this->assertSame('hello123', $word[0]);
        $this->assertSame('a%b&c/d', $word[1]);
        $this->assertStringNotContainsString('<', $word[2]);
        $this->assertStringNotContainsString('>', $word[2]);
        $this->assertSame('Привет', $word[3]);
        $this->assertStringNotContainsString('"', $word[4]);
        $this->assertStringNotContainsString("'", $word[4]);
        $this->assertSame('hello world', $word[5]);
    }

    # filterVar() passes a strict identifier through and blanks anything else; a single space is enough to reject
    #[Test]
    public function varFilterAcceptsIdentifiersOnly(): void
    {
        $this->assertSame(['hello-world_123', '', '', ''], $this->getProbe()['var']);
    }

    # An array keeps its type: a clean list survives, a list holding one bad element collapses to an empty array
    #[Test]
    public function varFilterReturnsArrayForArrayInput(): void
    {
        $this->assertSame([['one', 'two-three'], []], $this->getProbe()['vararr']);
    }

    # filterText() strips tags by default, escapes quotes, keeps escaped markup for type 2 and drops the trusted tokens an author without the super capability may not store
    #[Test]
    public function textFilterEscapesAndStripsMarkup(): void
    {
        $text = $this->getProbe()['text'];
        $this->assertSame('bold', $text[0]);
        $this->assertStringContainsString('&quot;', $text[1]);
        $this->assertSame('&lt;b&gt;tag&lt;/b&gt;', $text[2]);
        $this->assertStringNotContainsString('[usehtml]', $text[3]);
        $this->assertStringNotContainsString('[usephp]', $text[3]);
        $this->assertStringContainsString('normal text', $text[3]);
        $this->assertSame('hello', $text[4]);
    }

    # filterUrl() forces a scheme, keeps an existing https one and returns an empty string for a bare protocol
    #[Test]
    public function urlFilterNormalizesScheme(): void
    {
        $this->assertSame(['http://example.com', 'https://example.com', '', ''], $this->getProbe()['url']);
    }

    # filterHtml() encodes the dollar sign and quotes; a lone backslash is consumed by stripslashes before the encoding runs
    #[Test]
    public function htmlFilterEncodesTemplateCharacters(): void
    {
        $html = $this->getProbe()['html'];
        $this->assertStringContainsString('&#036;', $html[0]);
        $this->assertSame('backslash', $html[1]);
        $this->assertStringContainsString('&quot;', $html[2]);
        $this->assertStringContainsString('&#039;', $html[2]);
        $this->assertSame('', $html[3]);
    }

    # filterFields() joins an array with the pipe separator and stays a string for empty and scalar input
    #[Test]
    public function fieldsFilterJoinsArraysAndSurvivesScalars(): void
    {
        $this->assertSame(['one |two', '', 'plain'], $this->getProbe()['fields']);
    }
}
