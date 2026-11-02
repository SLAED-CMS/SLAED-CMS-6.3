<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The answers of a poll are plain text: the panel stores them as typed and every view prints them escaped, so an answer never runs as markup
final class VotingViewTest extends TestCase
{
    # The raw answer of the probe, kept before any assertion so a failed probe is not started again by the next test
    private static array $outs = [];

    private static ?array $probe = null;

    # Run the probe once: it renders one live poll with markup in its answers inside a transaction it rolls back
    private function getProbe(): array
    {
        if (self::$probe !== null) return self::$probe;
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = self::$outs[__FUNCTION__] ??= (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' votingview 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe votingview did not return JSON: '.$out);
        return self::$probe = $data;
    }

    # Both the form and the results show the answers as text, with no live tag or attribute of theirs
    #[Test]
    public function anAnswerIsPrintedAsText(): void
    {
        $data = $this->getProbe();
        if ($data['poll'] === 0) $this->markTestSkipped('This installation holds no poll');
        foreach (['form', 'view'] as $kind) {
            $html = (string)$data[$kind];
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html, 'The '.$kind.' lost the answer');
            $this->assertStringNotContainsString('<script', $html, 'The '.$kind.' prints an answer as a tag');
            $this->assertStringNotContainsString('<b onmouseover', $html, 'The '.$kind.' prints an answer as a tag');
            $this->assertStringNotContainsString('&amp;lt;', $html, 'The '.$kind.' escapes an answer twice');
        }
    }

    # The save keeps the answer as typed, with no tag stripped, and only the separator of the column and a line end replaced
    #[Test]
    public function theSaveKeepsTheAnswerAsTyped(): void
    {
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/modules/voting/admin/index.php');
        $save = substr($code, (int)strpos($code, 'function save('), 1500);
        $this->assertStringContainsString("str_replace(['|', \"\\n\"], ['/', ' '], filterSourceText(\$body[\$q]))", $save);
        $this->assertStringNotContainsString('strip_tags(', $save);
    }
}
