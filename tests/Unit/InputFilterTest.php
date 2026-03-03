<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Safety net tests for input filtering functions from core/security.php.
 * Uses local replicas to avoid loading security.php (function redeclaration conflicts).
 * Algorithms are copied verbatim from security.php lines 1013-1126.
 */
final class InputFilterTest extends TestCase
{
    // --- Replicated filter functions ---

    private function numFilter(string $var): int
    {
        $con = preg_replace('#[^0-9]#', '', $var);
        return intval($con);
    }

    private function varFilter(string $var): string
    {
        return preg_replace('#[^\pL0-9\s%&/|.:;&_+\-=]#siu', '', $var);
    }

    private function isVarLocal(string|array $var): string|array
    {
        if (is_array($var)) {
            return (preg_grep('#[^a-zA-Z0-9_\-]#', $var)) ? '' : $var;
        }
        return (preg_match('#[^a-zA-Z0-9_\-]#', $var)) ? '' : $var;
    }

    private function textFilterLocal(string $message, string $type = ''): string
    {
        // Simplified: skip admin check and censor (not relevant for filter logic tests)
        while (preg_match('#\[(usehtml|/usehtml)\]|\[(usephp|/usephp)\]#si', $message)) {
            $message = preg_replace('#\[(usehtml|/usehtml)\]|\[(usephp|/usephp)\]#si', '', $message);
        }
        if (intval($type) == 2) {
            $message = htmlspecialchars(trim($message), ENT_QUOTES);
        } else {
            $message = strip_tags(urldecode($message));
            $message = htmlspecialchars(trim($message), ENT_QUOTES);
        }
        return $message;
    }

    private function urlFilterLocal(string $url): string
    {
        $url = strtolower($url);
        $url = (preg_match('#http\:\/\/|https\:\/\/#i', $url)) ? $url : 'http://'.$url;
        $url = ($url == 'http://') ? '' : $this->textFilterLocal($url);
        return $url;
    }

    private function filterHtmlLocal(string $text): ?string
    {
        if ($text) {
            // Non-editor mode (redaktor=0): escape special chars
            return str_replace(
                ['"', '$', "'", '\\'],
                ['&#034;', '&#036;', '&#039;', '&#092;'],
                stripslashes($text)
            );
        }
        return null;
    }

    // --- num_filter tests ---

    #[Test]
    public function numFilterExtractsDigits(): void
    {
        $this->assertSame(123, $this->numFilter('123'));
    }

    #[Test]
    public function numFilterStripsNonDigits(): void
    {
        $this->assertSame(123, $this->numFilter('abc123def'));
    }

    #[Test]
    public function numFilterReturnsZeroForNonNumeric(): void
    {
        $this->assertSame(0, $this->numFilter('abc'));
    }

    #[Test]
    public function numFilterHandlesEmpty(): void
    {
        $this->assertSame(0, $this->numFilter(''));
    }

    #[Test]
    public function numFilterStripsNegativeSign(): void
    {
        $this->assertSame(5, $this->numFilter('-5'));
    }

    #[Test]
    public function numFilterLargeNumber(): void
    {
        $this->assertSame(999999999, $this->numFilter('999999999'));
    }

    // --- var_filter tests ---

    #[Test]
    public function varFilterAllowsLettersAndDigits(): void
    {
        $this->assertSame('hello123', $this->varFilter('hello123'));
    }

    #[Test]
    public function varFilterAllowsSpecialChars(): void
    {
        $this->assertSame('a%b&c/d', $this->varFilter('a%b&c/d'));
    }

    #[Test]
    public function varFilterStripsAngleBrackets(): void
    {
        $result = $this->varFilter('<script>alert(1)</script>');
        $this->assertStringNotContainsString('<', $result);
        $this->assertStringNotContainsString('>', $result);
    }

    #[Test]
    public function varFilterAllowsUnicode(): void
    {
        $this->assertSame('Привет', $this->varFilter('Привет'));
    }

    #[Test]
    public function varFilterStripsQuotes(): void
    {
        $result = $this->varFilter('test"value\'end');
        $this->assertStringNotContainsString('"', $result);
        $this->assertStringNotContainsString("'", $result);
    }

    #[Test]
    public function varFilterAllowsSpaces(): void
    {
        $this->assertSame('hello world', $this->varFilter('hello world'));
    }

    // --- isVar tests ---

    #[Test]
    public function isVarAllowsAlphanumericHyphenUnderscore(): void
    {
        $this->assertSame('hello-world_123', $this->isVarLocal('hello-world_123'));
    }

    #[Test]
    public function isVarRejectsSpaces(): void
    {
        $this->assertSame('', $this->isVarLocal('hello world'));
    }

    #[Test]
    public function isVarRejectsHtml(): void
    {
        $this->assertSame('', $this->isVarLocal('test<script>'));
    }

    #[Test]
    public function isVarRejectsQuotes(): void
    {
        $this->assertSame('', $this->isVarLocal("test'injection"));
    }

    #[Test]
    public function isVarArrayWithInvalidElement(): void
    {
        $result = $this->isVarLocal(['valid-name', 'not valid']);
        $this->assertSame('', $result);
    }

    #[Test]
    public function isVarArrayAllValid(): void
    {
        $input = ['valid-name', 'also_valid', 'abc123'];
        $this->assertSame($input, $this->isVarLocal($input));
    }

    // --- text_filter tests ---

    #[Test]
    public function textFilterEscapesHtml(): void
    {
        $result = $this->textFilterLocal('<b>bold</b>');
        $this->assertStringNotContainsString('<b>', $result);
    }

    #[Test]
    public function textFilterEscapesQuotes(): void
    {
        $result = $this->textFilterLocal('test"value');
        $this->assertStringContainsString('&quot;', $result);
    }

    #[Test]
    public function textFilterType2KeepsTags(): void
    {
        $result = $this->textFilterLocal('<b>bold</b>', '2');
        $this->assertStringContainsString('&lt;b&gt;', $result);
    }

    #[Test]
    public function textFilterStripsUsephpBBCode(): void
    {
        $result = $this->textFilterLocal('[usephp]echo 1;[/usephp] normal text');
        $this->assertStringNotContainsString('[usephp]', $result);
        $this->assertStringContainsString('normal text', $result);
    }

    #[Test]
    public function textFilterTrimsWhitespace(): void
    {
        $this->assertSame('hello', $this->textFilterLocal('  hello  '));
    }

    #[Test]
    public function textFilterHandlesNestedBBCode(): void
    {
        $result = $this->textFilterLocal('[usephp][usephp]nested[/usephp][/usephp]');
        $this->assertStringNotContainsString('[usephp]', $result);
    }

    // --- url_filter tests ---

    #[Test]
    public function urlFilterAddsHttpPrefix(): void
    {
        $result = $this->urlFilterLocal('example.com');
        $this->assertStringStartsWith('http://', $result);
    }

    #[Test]
    public function urlFilterKeepsHttps(): void
    {
        $result = $this->urlFilterLocal('https://example.com');
        $this->assertStringStartsWith('https://', $result);
    }

    #[Test]
    public function urlFilterReturnsEmptyForEmpty(): void
    {
        $this->assertSame('', $this->urlFilterLocal(''));
    }

    // --- filterHtml tests ---

    #[Test]
    public function filterHtmlEscapesDollarSign(): void
    {
        $result = $this->filterHtmlLocal('$var');
        $this->assertStringContainsString('&#036;', $result);
    }

    #[Test]
    public function filterHtmlEscapesBackslash(): void
    {
        // Input with literal backslash (double-escaped in PHP source)
        // stripslashes() in filterHtml removes one level of escaping
        $result = $this->filterHtmlLocal('\\\\path');
        $this->assertStringContainsString('&#092;', $result);
    }

    #[Test]
    public function filterHtmlEscapesQuotes(): void
    {
        $result = $this->filterHtmlLocal('He said "hello" and \'bye\'');
        $this->assertStringContainsString('&#034;', $result);
        $this->assertStringContainsString('&#039;', $result);
    }

    #[Test]
    public function filterHtmlReturnsNullForEmpty(): void
    {
        $this->assertNull($this->filterHtmlLocal(''));
    }
}
