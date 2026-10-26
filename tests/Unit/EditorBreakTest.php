<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# Stored text of docs/EDITORS.md: getTplTextarea() mounts the stored Markdown unchanged and a save without a change stores the same bytes, in the plain and the markdown format
final class EditorBreakTest extends TestCase
{
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

    # The probe ran each format under the editor that writes it
    #[Test]
    public function eachFormatRunsUnderItsEditor(): void
    {
        $data = $this->getProbe();
        $this->assertSame('plain', $data['plain']['format']);
        $this->assertSame('markdown', $data['markdown']['format']);
    }

    # The editor receives the stored text byte for byte: no break rewritten, no entity decoded
    #[Test]
    public function mountGivesTheStoredText(): void
    {
        $data = $this->getProbe();
        foreach (['plain', 'markdown'] as $fmt) {
            foreach ($data[$fmt]['trips'] as $name => $trip) $this->assertSame($data['values'][$name], $trip['mount'], $fmt.' '.$name);
        }
    }

    # A save without a change, submitted with the CRLF of a browser, stores the bytes it mounted, and so does a second one
    #[Test]
    public function saveWithoutChangeKeepsTheBytes(): void
    {
        $data = $this->getProbe();
        foreach (['plain', 'markdown'] as $fmt) {
            foreach ($data[$fmt]['trips'] as $name => $trip) {
                $this->assertSame($data['values'][$name], $trip['saved'], $fmt.' '.$name);
                $this->assertSame($data['values'][$name], $trip['again'], $fmt.' '.$name);
            }
        }
    }

    # The stored text is escaped at render: a tag a member typed stays text, both hard breaks break, an entity reads as CommonMark reads it
    #[Test]
    public function renderEscapesTheTypedText(): void
    {
        $trips = $this->getProbe()['markdown']['trips'];
        $this->assertStringContainsString('&lt;h4&gt;Head&lt;/h4&gt;', $trips['tags']['sign']);
        $this->assertStringNotContainsString('<h4', $trips['tags']['sign']);
        $this->assertSame(2, substr_count($trips['breaks']['sign'], '<br>'));
        $this->assertStringContainsString('a &amp; b &amp; c &lt;x&gt;', $trips['entity']['sign']);
        $this->assertSame(2, substr_count($trips['blank']['sign'], '<p>'));
    }

    # A code block highlights once its two scripts ran, also in a pane htmx inserts, where the inline call runs before the scripts load
    #[Test]
    public function codeHighlightWaitsForItsScripts(): void
    {
        $html = $this->getProbe()['markdown']['trips']['quote']['sign'];
        $this->assertMatchesRegularExpression('#highlight-line-numbers\.min\.js[^"]*"\s*></script><script>\(function\(s\)#', $html, 'The call does not follow its scripts');
        foreach (["s.addEventListener('load',run)", 'document.currentScript.previousElementSibling', 'pre code:not([data-highlighted])'] as $part) {
            $this->assertStringContainsString($part, $html);
        }
    }

    # Every writer of a Markdown store keeps the typed text through filterSourceText(), and every reader of one renders safe with trust as Node does
    #[Test]
    public function storesKeepTheTypedTextAndRenderSafe(): void
    {
        $base = dirname(__DIR__, 2);
        $need = [
            'modules/forum/index.php' => ["filterSourceText(getVar('post', 'hometext', 'raw', ''), isAdmin(true))" => 3,
                "filterContent(\$rows[0][7], true, \$conf['name'], 0, '', [], true)" => 1],
            'modules/search/index.php' => ["'content' => \$cont, 'safe' => true, 'trust' => true," => 1],
            'core/user.php' => ['$body = filterSourceText($text, isAdmin(true));' => 1, "filterContent(\$body, true, 'forum', 2, '', ['forum', \$id], true)" => 1,
                "filterContent(\$userblock, true, 'profile', 2, '', ['profile', \$uid], true)" => 1, "filterContent(\$sig, true, 'profile'" => 1,
                "\$body = \$prs->filterContent(\$body, true, 'all', 2, '', [], true);" => 1],
            'modules/account/index.php' => ["filterSourceText(getVar('post', 'sig', 'raw', ''), isAdmin(true))" => 1,
                "filterSourceText(getVar('post', 'block', 'raw', ''), isAdmin(true))" => 1],
            'modules/account/admin/index.php' => ["filterSourceText(getVar('post', 'sig', 'raw', ''), isAdmin(true))" => 2,
                "filterSourceText(getVar('post', 'block', 'raw', ''), isAdmin(true))" => 2],
            'admin/modules/blocks.php' => ["filterSourceText(getVar('post', 'content', 'raw', ''), isAdmin(true))" => 2],
            'core/system.php' => ["filterContent(\$content, true, 'all', 2, '', [], true)" => 1],
            'admin/modules/messages.php' => ["filterSourceText(getVar('post', 'body', 'raw', ''), isAdmin(true))" => 2, 'getTplLanguageOptions($lang)' => 1],
            'admin/modules/newsletter.php' => ["filterSourceText(getVar('post', 'body', 'raw', " => 2],
            'core/classes/mail.php' => ["filterContent(\$body, true, 'all', 0, '', [], true)" => 1],
            'modules/contact/admin/index.php' => ["filterSourceText(getVar('post', 'info', 'raw', ''), isAdmin(true))" => 1],
            'modules/contact/index.php' => ["filterContent(\$conf['contact']['info'], true, \$conf['name'], 0, 'breaks', [], true)" => 1],
            'core/helpers.php' => ["filterContent(\$texta, true, \$mod, 0, '', \$own, true)" => 1, "filterContent(\$textb, true, \$mod, 0, '', \$own, true)" => 1,
                "\$desc = \$value ?: filterSourceText(getVar('post', \$name, 'raw', ''), isAdmin(true));" => 1],
            'core/classes/comment.php' => ['filterSourceText($body)' => 2, 'filterSourceText($body, false, false)' => 1],
            'core/classes/privat.php' => ['filterSourceText($body)' => 1],
            'core/classes/node/service.php' => ['filterSourceText($text, $this->ctx->super, false)' => 1],
        ];
        foreach ($need as $file => $parts) {
            $code = (string)file_get_contents($base.'/'.$file);
            foreach ($parts as $part => $count) $this->assertSame($count, substr_count($code, $part), $file.': '.$part);
            $this->assertDoesNotMatchRegularExpression("#getVar\('post', '(?:hometext|sig|block|content|body|info)', 'text'#", $code, $file.' stores a Markdown text escaped');
        }
    }
}
