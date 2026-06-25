<?php
declare(strict_types=1);

namespace {
    if (!class_exists('Parser', false)) {
        require_once BASE_DIR.'/core/classes/parser.php';
    }

    if (!defined('_QUOTE'))    define('_QUOTE',    'Quote');
    if (!defined('_HIDE'))     define('_HIDE',     'Hidden');
    if (!defined('_HIDETEXT')) define('_HIDETEXT', 'Show');
    if (!defined('_SMILIE'))   define('_SMILIE',   'Smilie');
    if (!defined('_CODE'))     define('_CODE',     'Code');

    if (!function_exists('img_find')) {
        function img_find(string $path): string { return '/img/'.$path; }
    }
    if (!function_exists('replace_break')) {
        function replace_break(string $s): string { return $s; }
    }
    if (!function_exists('is_user')) {
        function is_user(): bool { return false; }
    }
    if (!function_exists('getDecodedText')) {
        function getDecodedText(string $text): string { return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
    }
}

namespace Tests\Unit {

    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    /**
     * Byte-exact fixture tests for Parser::filterDoc().
     * Each case verifies that a given input produces the exact expected HTML output.
     * Cases marked [~] are runtime-dependent and run as smoke-tests only (no exception, returns string).
     */
    final class ParserFixturesTest extends TestCase
    {
        private static \Parser $p;

        public static function setUpBeforeClass(): void
        {
            self::$p = new \Parser();
        }

        public static function deterministicCases(): array
        {
            return [
                # Empty / whitespace
                'empty string'    => ['',       true,  '', ''],
                'whitespace only' => ["\n\n\n", true,  '', ''],

                # Headings
                'h1' => ['# H1',  true, '', '<h1 id="1">H1</h1>'],
                'h2' => ['## H2', true, '', '<h2 id="2">H2</h2>'],

                # Inline — wrapped in <p>
                'bold md'         => ['**bold**',   true, '', '<p><strong>bold</strong></p>'],
                'italic md'       => ['*italic*',   true, '', '<p><em>italic</em></p>'],
                'del mark'        => ['~~del~~ ==mark==', true, '', '<p><del>del</del> <mark>mark</mark></p>'],
                'link md'         => ['[link](https://example.com)', true, '', '<p><a href="https://example.com">link</a></p>'],
                'bold md + bb bold' => ['**bold** и [b]bb-bold[/b]', true, '', '<p><strong>bold</strong> и &lt;strong&gt;bb-bold&lt;/strong&gt;</p>'],

                # Lists
                'ul basic' => ["- a\n- b\n- c", true, '', "<ul>\n<li>a</li>\n<li>b</li>\n<li>c</li>\n</ul>"],
                'ol basic' => ["1. a\n2. b",     true, '', "<ol>\n<li>a</li>\n<li>b</li>\n</ol>"],
                'ul nested' => ["- item1\n  - nested\n- item2", true, '',
                    "<ul>\n<li><p>item1</p>\n<ul>\n<li>nested</li>\n</ul>\n</li>\n<li>item2</li>\n</ul>"],

                # Fenced code — NOT wrapped in <p>
                'fenced code php'     => ["```php\necho 1;\n```", true, '', '<pre><code class="language-php">echo 1;</code></pre>'],
                'fenced code plain'   => ["```\nplain\n```",       true, '', '<pre><code>plain</code></pre>'],
                'inline code'         => ['`inline`',              true, '', '<code>inline</code>'],
                'inline code in text' => ['текст `code` текст',    true, '', '<p>текст <code>code</code> текст</p>'],

                # Table — basic
                'table basic' => [
                    "| A | B |\n|---|---|\n| 1 | 2 |", true, '',
                    "<table>\n<thead>\n<tr><th>A</th><th>B</th></tr>\n</thead>\n<tbody>\n<tr><td>1</td><td>2</td></tr>\n</tbody>\n</table>",
                ],

                # Table — alignment
                'table align' => [
                    "| L | C | R |\n|:--|:--:|--:|\n| a | b | c |", true, '',
                    "<table>\n<thead>\n<tr><th style=\"text-align:left\">L</th><th style=\"text-align:center\">C</th><th style=\"text-align:right\">R</th></tr>\n</thead>\n<tbody>\n<tr><td style=\"text-align:left\">a</td><td style=\"text-align:center\">b</td><td style=\"text-align:right\">c</td></tr>\n</tbody>\n</table>",
                ],

                # URL policy
                'url bb safe javascript' => ['[url]javascript:x[/url]',       true, '', '<p><a href="#">javascript:x</a></p>'],
                'url bb safe https'      => ['[url]https://ok.com[/url]',      true, '', '<p><a href="https://ok.com">https://ok.com</a></p>'],
                'url bb safe mailto'     => ['[url]mailto:a@b.com[/url]',      true, '', '<p><a href="mailto:a@b.com">mailto:a@b.com</a></p>'],
                'url bb safe local'      => ['[url]/local/path[/url]',         true, '', '<p><a href="/local/path">/local/path</a></p>'],
                'url bb safe relative'   => ['[url]../uploads/file.pdf[/url]', true, '', '<p><a href="../uploads/file.pdf">../uploads/file.pdf</a></p>'],

                # Safe mode = true — HTML escaped
                'safe script tag' => ['<script>alert(1)</script>',              true,  '', '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>'],
                'safe b tag'      => ['<b>ok</b>',                              true,  '', '<p>&lt;b&gt;ok&lt;/b&gt;</p>'],
                'safe a js href'  => ['<a href="javascript:x">click</a>',       true,  '', '<p>&lt;a href=&quot;javascript:x&quot;&gt;click&lt;/a&gt;</p>'],

                # Safe mode = false — HTML passes through
                'unsafe script tag' => ['<script>x</script>',                false, '', '<script>x</script>'],
                'unsafe div block'  => ["<div class=\"x\">\ntext\n</div>",    false, '', "<div class=\"x\">\ntext\n</div>"],
                'usehtml tag'       => ['[usehtml]<b>html</b>[/usehtml]',     false, '', '<b>html</b>'],

                # Edge cases
                'unclosed backtick' => ['незакрытый `backtick', true, '', '<p>незакрытый `backtick</p>'],
            ];
        }

        #[Test]
        #[DataProvider('deterministicCases')]
        public function filterDocMatchesExpected(string $src, bool $safe, string $mod, string $expected): void
        {
            $this->assertSame($expected, self::$p->filterDoc($src, $safe, $mod));
        }

        public static function runtimeCases(): array
        {
            return [
                '[quote]текст[/quote]'                          => ['[quote]текст[/quote]'],
                '[hide]секрет[/hide]'                           => ['[hide]секрет[/hide]'],
                '[quote][quote]inner[/quote][/quote]'           => ['[quote][quote]inner[/quote][/quote]'],
                '[hide][quote]q[/quote][/hide]'                 => ['[hide][quote]q[/quote][/hide]'],
                '[quote][quote][quote]deep[/quote][/quote][/quote]' => ['[quote][quote][quote]deep[/quote][/quote][/quote]'],
                '[quote] with list'                             => ["[quote]\n- item1\n- item2\n[/quote]"],
                'img alt'                                       => ['![alt text](img.jpg)'],
                'img title'                                     => ['![](img.jpg "My title")'],
            ];
        }

        #[Test]
        #[DataProvider('runtimeCases')]
        public function filterDocRuntimeSmokeTest(string $src): void
        {
            $got = self::$p->filterDoc($src, true, '');
            $this->assertIsString($got);
        }

        #[Test]
        public function checkParserImageFallback(): void
        {
            if (!class_exists('Template', false)) {
                require_once BASE_DIR.'/core/classes/template.php';
            }
            $hadtpl = array_key_exists('tpl', $GLOBALS);
            $oldtpl = $GLOBALS['tpl'] ?? null;
            $GLOBALS['tpl'] = new \Template('lite');

            try {
                $parser = new \Parser();
                $missing = $parser->filterContent('[img]/uploads/parser-missing.png[/img]', true, '');
                $this->assertStringContainsString('class="bi bi-image sl-img-placeholder sl-img"', $missing);

                $aligned = $parser->filterContent('[img=right]/uploads/parser-missing.png[/img]', true, '');
                $this->assertStringContainsString('class="bi bi-image sl-img-placeholder sl-img sl-img-right"', $aligned);

                $existing = $parser->filterContent('[img=right]/templates/lite/images/favicon.svg[/img]', true, '');
                $this->assertStringContainsString('class="sl-img sl-img-right"', $existing);
                $this->assertStringContainsString('onerror="this.onerror=null;this.hidden=true;this.nextElementSibling.hidden=false"', $existing);
                $this->assertStringNotContainsString('style=', $existing);
            } finally {
                if ($hadtpl) $GLOBALS['tpl'] = $oldtpl;
                else unset($GLOBALS['tpl']);
            }
        }

        #[Test]
        public function checkGfmCalloutsRenderAsAlerts(): void
        {
            if (!class_exists('Template', false)) {
                require_once BASE_DIR.'/core/classes/template.php';
            }
            $hadtpl = array_key_exists('tpl', $GLOBALS);
            $oldtpl = $GLOBALS['tpl'] ?? null;
            $GLOBALS['tpl'] = new \Template('lite');

            try {
                $parser = new \Parser();
                $map = [
                    'NOTE' => 'sl-alert sl-alert-info',
                    'TIP' => 'sl-alert sl-alert-success',
                    'IMPORTANT' => 'sl-alert sl-alert-accent',
                    'WARNING' => 'sl-alert sl-alert-warn',
                    'CAUTION' => 'sl-alert sl-alert-error',
                ];
                foreach ($map as $kind => $expected) {
                    $html = $parser->filterContent("> [!$kind]\n> body text", true, '');
                    $this->assertStringContainsString($expected, $html, "callout $kind");
                    $this->assertStringContainsString('sl-alert-body', $html, "callout $kind body");
                }
            } finally {
                if ($hadtpl) $GLOBALS['tpl'] = $oldtpl;
                else unset($GLOBALS['tpl']);
            }
        }
    }
}
