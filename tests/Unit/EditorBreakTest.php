<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# Line breaks of docs/EDITORS.md: a stored <br> survives the mount by getTplTextarea() and the save of the plain and the markdown format
final class EditorBreakTest extends TestCase
{
    private const SAVED = [
        'plain' => [
            'lines' => "alpha<br>\r\nbeta<br>\r\ngamma",
            'inline' => "one<br>\r\ntwo",
            'blank' => "para<br>\r\n<br>\r\nnext",
            'entity' => "a &amp; b<br>\r\nc",
            'typed' => 'a &lt;br&gt; b',
        ],
        'markdown' => [
            'lines' => "alpha  \r\nbeta  \r\ngamma",
            'inline' => "one  \r\ntwo",
            'blank' => "para  \r\n  \r\nnext",
            'entity' => "a &amp; b  \r\nc",
            'typed' => 'a &lt;br&gt; b',
        ],
    ];

    private static ?array $probe = null;

    # Run tests/Support/break_probe.php once in a fresh process and memoize its report
    private function getProbe(): array
    {
        if (self::$probe !== null) return self::$probe;
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_break';
        $script = dirname(__DIR__).'/Support/break_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
        $this->assertArrayNotHasKey('error', $data, (string)($data['error'] ?? ''));
        return self::$probe = $data;
    }

    # One render with every break written the same way and no white space around it, so the spelling of a tag is not compared
    private function getBreakForm(string $html): string
    {
        return (string)preg_replace('#\s*<br\s*/?>\s*#i', '<br>', $html);
    }

    # The probe ran each format under the editor that writes it
    #[Test]
    public function eachFormatRunsUnderItsEditor(): void
    {
        $data = $this->getProbe();
        $this->assertSame('plain', $data['plain']['format']);
        $this->assertSame('markdown', $data['markdown']['format']);
    }

    # One save writes the bytes the format stores a break as: <br> before a line end for plain, two spaces before it for markdown
    #[Test]
    public function saveWritesEveryBreakBack(): void
    {
        $data = $this->getProbe();
        foreach (self::SAVED as $fmt => $list) {
            foreach ($list as $name => $want) $this->assertSame($want, $data[$fmt]['trips'][$name]['saved'], $fmt.' '.$name);
        }
    }

    # A second mount and save leaves the value byte for byte as the first save wrote it
    #[Test]
    public function secondSaveChangesNothing(): void
    {
        $data = $this->getProbe();
        foreach (['plain', 'markdown'] as $fmt) {
            foreach ($data[$fmt]['trips'] as $name => $trip) $this->assertSame($trip['saved'], $trip['again'], $fmt.' '.$name);
        }
    }

    # The signature renders after the save as it rendered before it; an empty line of breaks becomes the paragraph Markdown writes it as
    #[Test]
    public function signatureRendersTheSame(): void
    {
        $data = $this->getProbe();
        foreach (['plain', 'markdown'] as $fmt) {
            foreach ($data[$fmt]['trips'] as $name => $trip) {
                if ($fmt === 'markdown' && $name === 'blank') continue;
                $this->assertSame($this->getBreakForm($trip['sign'][0]), $this->getBreakForm($trip['sign'][1]), $fmt.' '.$name);
            }
        }
        $this->assertSame(2, substr_count($data['markdown']['trips']['blank']['sign'][1], '<p>'));
    }

    # A markdown save leaves no raw tag that an escaping render would print as text, and a tag a member typed stays text
    #[Test]
    public function markdownSaveRendersBreaksWhenEscaped(): void
    {
        $data = $this->getProbe();
        foreach (['lines' => 2, 'inline' => 1, 'entity' => 1] as $name => $count) {
            $html = $data['markdown']['trips'][$name]['safe'][1];
            $this->assertStringNotContainsString('&lt;br', $html, $name);
            $this->assertSame($count, substr_count($html, '<br>'), $name);
        }
        $this->assertSame('a <br> b', $data['markdown']['trips']['typed']['mount']);
    }
}
