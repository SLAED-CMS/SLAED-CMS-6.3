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

    if (!function_exists('getThemeImagePath')) {
        function getThemeImagePath(string $path): string { return '/img/'.$path; }
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
    if (!function_exists('getUploadRuleData')) {
        function getUploadRuleData(string $mod): array { return ['thumbwidth' => 250]; }
    }
}

namespace Tests\Unit {

    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    # Byte-exact fixture tests for Parser::filterDoc(): each deterministic case asserts the exact HTML output, runtime-dependent cases run as smoke-tests only
    final class ParserFixturesTest extends TestCase
    {
        private static \Parser $p;

        # Every element the parser emits is rendered by the theme, so the fixtures are meaningless without an engine: the expected bytes are what a theme produces, not what PHP concatenates
        public static function setUpBeforeClass(): void
        {
            if (!class_exists('Template', false)) {
                require_once BASE_DIR.'/core/classes/template.php';
            }
            $GLOBALS['tpl'] = new \Template('lite');
            self::$p = new \Parser();
        }

        public static function tearDownAfterClass(): void
        {
            unset($GLOBALS['tpl']);
        }

        public static function deterministicCases(): array
        {
            return [
                'empty string'    => ['',       true,  '', ''],
                'whitespace only' => ["\n\n\n", true,  '', ''],

                'h1' => ['# H1',  true, '', '<h1 id="h1">H1</h1>'],
                'h2' => ['## H2', true, '', '<h2 id="h2">H2</h2>'],
                'h1 cyrillic' => ['# Привет мир', true, '', '<h1 id="привет-мир">Привет мир</h1>'],
                'h2 mixed script' => ['## Раздел API v2', true, '', '<h2 id="раздел-api-v2">Раздел API v2</h2>'],
                'heading id dedup' => [
                    "# Привет мир\n\n# Привет мир", true, '',
                    "<h1 id=\"привет-мир\">Привет мир</h1>\n\n<h1 id=\"привет-мир-1\">Привет мир</h1>",
                ],

                'bold md'         => ['**bold**',   true, '', '<p><strong>bold</strong></p>'],
                'italic md'       => ['*italic*',   true, '', '<p><em>italic</em></p>'],
                'del mark'        => ['~~del~~ ==mark==', true, '', '<p><del>del</del> <mark>mark</mark></p>'],
                'link md'         => ['[link](https://example.com)', true, '', '<p><a href="https://example.com">link</a></p>'],
                # Stage 2 of docs/COMMENTS-REDESIGN-2026.md renders comments at safe = true, so the inline BB pairs the parser reads for old content survive the safe escape
                'bold md + bb bold' => ['**bold** и [b]bb-bold[/b]', true, '', '<p><strong>bold</strong> и <strong>bb-bold</strong></p>'],
                'bb pairs safe'     => ['[i]i[/i] [u]u[/u] [s]s[/s]', true, '', '<p><em>i</em> <u>u</u> <del>s</del></p>'],
                'bb color safe'     => ['[color=red]r[/color]', true, '', '<p><span style="color:red">r</span></p>'],
                'bb color bad safe' => ['[color=x:y]r[/color]', true, '', '<p>r</p>'],
                'bb size safe'      => ['[size=99]big[/size]', true, '', '<p><span style="font-size:48px">big</span></p>'],
                'bb html stays out' => ['[b]<img src=x onerror=alert(1)>[/b]', true, '', '<p><strong>&lt;img src=x onerror=alert(1)&gt;</strong></p>'],

                'ul basic' => ["- a\n- b\n- c", true, '', "<ul>\n<li>a</li>\n<li>b</li>\n<li>c</li>\n</ul>"],
                'ol basic' => ["1. a\n2. b",     true, '', "<ol>\n<li>a</li>\n<li>b</li>\n</ol>"],
                'ul nested' => ["- item1\n  - nested\n- item2", true, '',
                    "<ul>\n<li><p>item1</p>\n<ul>\n<li>nested</li>\n</ul>\n</li>\n<li>item2</li>\n</ul>"],

                'fenced code php'     => ["```php\necho 1;\n```", true, '', '<pre><code class="language-php">echo 1;</code></pre>'],
                'fenced code plain'   => ["```\nplain\n```",       true, '', '<pre><code>plain</code></pre>'],
                'inline code'         => ['`inline`',              true, '', '<code>inline</code>'],
                'inline code in text' => ['текст `code` текст',    true, '', '<p>текст <code>code</code> текст</p>'],

                'table basic' => [
                    "| A | B |\n|---|---|\n| 1 | 2 |", true, '',
                    "<table>\n<thead>\n<tr><th>A</th><th>B</th></tr>\n</thead>\n<tbody>\n<tr><td>1</td><td>2</td></tr>\n</tbody>\n</table>",
                ],

                'table align' => [
                    "| L | C | R |\n|:--|:--:|--:|\n| a | b | c |", true, '',
                    "<table>\n<thead>\n<tr><th style=\"text-align:left\">L</th><th style=\"text-align:center\">C</th><th style=\"text-align:right\">R</th></tr>\n</thead>\n<tbody>\n<tr><td style=\"text-align:left\">a</td><td style=\"text-align:center\">b</td><td style=\"text-align:right\">c</td></tr>\n</tbody>\n</table>",
                ],

                'url bb safe javascript' => ['[url]javascript:x[/url]',       true, '', '<p><a href="#">javascript:x</a></p>'],
                'url bb safe data html'  => ['[url]data:text/html,x[/url]',    true, '', '<p><a href="#">data:text/html,x</a></p>'],
                'url md safe data html'  => ['[l](data:text/html,x)',          true, '', '<p><a href="#">l</a></p>'],
                'url bb unsafe data html' => ['[url]data:text/html,x[/url]',   false, '', '<p><a href="#">data:text/html,x</a></p>'],
                'url md unsafe data html' => ['[l](data:text/html,x)',         false, '', '<p><a href="#">l</a></p>'],
                'url bb unsafe uppercase data' => ['[url]DATA:text/html,x[/url]', false, '', '<p><a href="#">DATA:text/html,x</a></p>'],
                'url bb unsafe other scheme'   => ['[url]ftp://ok.com/f[/url]',   false, '', '<p><a href="ftp://ok.com/f">ftp://ok.com/f</a></p>'],
                'url bb safe https'      => ['[url]https://ok.com[/url]',      true, '', '<p><a href="https://ok.com">https://ok.com</a></p>'],
                'url bb safe mailto'     => ['[url]mailto:a@b.com[/url]',      true, '', '<p><a href="mailto:a@b.com">mailto:a@b.com</a></p>'],
                'url bb safe local'      => ['[url]/local/path[/url]',         true, '', '<p><a href="/local/path">/local/path</a></p>'],
                'url bb safe relative'   => ['[url]../uploads/file.pdf[/url]', true, '', '<p><a href="../uploads/file.pdf">../uploads/file.pdf</a></p>'],

                # Script-bearing schemes must die in trusted mode too: comments render at safe=false, so an allowlist that only applies to safe mode leaves stored XSS reachable
                'url bb unsafe javascript'        => ['[url]javascript:x[/url]',      false, '', '<p><a href="#">javascript:x</a></p>'],
                'url md unsafe javascript'        => ['[l](javascript:x)',            false, '', '<p><a href="#">l</a></p>'],
                'url bb unsafe vbscript'          => ['[url]vbscript:x[/url]',        false, '', '<p><a href="#">vbscript:x</a></p>'],
                'url bb safe vbscript'            => ['[url]vbscript:x[/url]',        true,  '', '<p><a href="#">vbscript:x</a></p>'],
                'url bb unsafe mixed case js'     => ['[url]JaVaScRiPt:x[/url]',      false, '', '<p><a href="#">JaVaScRiPt:x</a></p>'],
                'url bb unsafe javascript space'  => ['[url] javascript:x[/url]',     false, '', '<p><a href="#">javascript:x</a></p>'],
                'url bb unsafe javascript tab'    => ["[url]java\tscript:x[/url]",    false, '', "<p><a href=\"#\">java\tscript:x</a></p>"],
                'url bb unsafe javascript entity' => ['[url]java&#9;script:x[/url]',  false, '', "<p><a href=\"#\">java\tscript:x</a></p>"],

                'safe script tag' => ['<script>alert(1)</script>',              true,  '', '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>'],
                'safe b tag'      => ['<b>ok</b>',                              true,  '', '<p>&lt;b&gt;ok&lt;/b&gt;</p>'],
                'safe a js href'  => ['<a href="javascript:x">click</a>',       true,  '', '<p>&lt;a href=&quot;javascript:x&quot;&gt;click&lt;/a&gt;</p>'],

                'unsafe script tag' => ['<script>x</script>',                false, '', '<script>x</script>'],
                'unsafe div block'  => ["<div class=\"x\">\ntext\n</div>",    false, '', "<div class=\"x\">\ntext\n</div>"],
                'usehtml tag'       => ['[usehtml]<b>html</b>[/usehtml]',     false, '', '<b>html</b>'],

                # Code wins over the bracket layer, which is what lets a document write a tag as an example instead of executing it
                'code span keeps bb pair'   => ['`[quote]x[/quote]`',   true,  '', '<code>[quote]x[/quote]</code>'],
                'code span keeps lone tag'  => ['`[hr]` and `[li]`',    true,  '', '<p><code>[hr]</code> and <code>[li]</code></p>'],
                'code span keeps smilie'    => ['`*01`',                true,  '', '<code>*01</code>'],
                'code span keeps trusted'   => ['`[usehtml]x[/usehtml]`', false, '', '<code>[usehtml]x[/usehtml]</code>'],
                'bare tag still runs'       => ['[hr]',                 true,  '', '<hr>'],

                # A conversation channel breaks on the line ending its author typed; plain Markdown joins those lines and plain format recognizes no Markdown at all
                'markdown joins soft lines' => ["one\ntwo",             true,  '', '<p>one'."\n".'two</p>'],
                'breaks keep soft lines'    => ["one\ntwo",             true,  '', '<p>one<br>'."\n".'two</p>', 'breaks'],
                'breaks still read markdown'=> ["**bold**\nnext",       true,  '', '<p><strong>bold</strong><br>'."\n".'next</p>', 'breaks'],
                'breaks keep the hard one'  => ["one\\\ntwo",           true,  '', '<p>one<br>'."\n".'two</p>', 'breaks'],

                # The super administrator capability is a write-boundary rule, so the parser still runs the tag it was handed; safe mode leaves it as the literal text an author typed
                'usephp trusted'    => ['[usephp]echo 6*7;[/usephp]',         false, '', '42'],
                'usephp safe'       => ['[usephp]echo 6*7;[/usephp]',         true,  '', '<p>[usephp]echo 6*7;[/usephp]</p>'],

                'unclosed backtick' => ['незакрытый `backtick', true, '', '<p>незакрытый `backtick</p>'],

                'literal emphasis'        => ['\*not em\*',        true,  '', '<p>*not em*</p>'],
                'literal backslash pair'  => ['a\\\\b',            true,  '', '<p>a\\b</p>'],
                'literal bb pair'         => ['\[b]x\[/b]',        true,  '', '<p>[b]x[/b]</p>'],
                'literal hide trusted'    => ['\[hide]x\[/hide]',  false, '', '<p>[hide]x[/hide]</p>'],
                'literal heading'         => ['\# not heading',    true,  '', '<p># not heading</p>'],
                'literal list'            => ['\- item',           true,  '', '<p>- item</p>'],
                'literal tag safe'        => ['\<b\>',             true,  '', '<p>&lt;b&gt;</p>'],
                'literal tag trusted'     => ['\<b\>x',            false, '', '<p>&lt;b&gt;x</p>'],
                'literal alone on a line' => ['\*',                true,  '', '<p>*</p>'],
                'literal smilie'          => ['\*01',              true,  '', '<p>*01</p>'],
                'literal plain format'    => ['\*a\*',             true,  '', '<p>*a*</p>', 'plain'],
                'literal heading id'      => ['# A \# B',          true,  '', '<h1 id="a-b">A # B</h1>'],
                'letter keeps backslash'  => ['C:\new',            true,  '', '<p>C:\new</p>'],
                'code span keeps pair'    => ['`a\*b`',            true,  '', '<code>a\*b</code>'],
                'escaped backtick'        => ['\`x`',              true,  '', '<p>`x`</p>'],
                'trusted script is raw'   => ['<script>var q = "\"";</script>', false, '', '<script>var q = "\"";</script>'],
                'trusted attribute is raw'=> ['<a title="x\*y">t</a>', false, '', '<a title="x\*y">t</a>'],
            ];
        }

        #[Test]
        #[DataProvider('deterministicCases')]
        public function filterDocMatchesExpected(string $src, bool $safe, string $mod, string $expected, string $fmt = ''): void
        {
            $this->assertSame($expected, self::$p->filterDoc($src, $safe, $mod, 0, $fmt));
        }

        # A rendering may only be stored when it answers for nobody in particular; a hidden block is the case that would leak, because a stored copy would show a visitor what only a member may read
        public static function varyCases(): array
        {
            return [
                'plain text is storable'     => ['just **text** and a [b]tag[/b]',      true,  false],
                'code keeps it storable'     => ['`[hide]secret[/hide]`',               true,  false],
                'hidden block varies'        => ['[hide]secret[/hide]',                 true,  true],
                'executed php varies'        => ['[usephp]echo 1;[/usephp]',            false, true],
                'markdown image varies'      => ['![alt](uploads/all/x.png)',           true,  true],
                'bb image varies'            => ['[img]uploads/all/x.png[/img]',        true,  true],
                'attachment varies'          => ['[attach=nosuchfile.pdf align=left title=t]', true, true],
            ];
        }

        #[Test]
        #[DataProvider('varyCases')]
        public function onlyContextFreeRenderingsMayBeStored(string $src, bool $safe, bool $vary): void
        {
            self::$p->filterDoc($src, $safe, 'all');
            $flag = new \ReflectionProperty(\Parser::class, 'vary');
            $this->assertSame($vary, $flag->getValue(self::$p), 'The storable verdict of this document is wrong');
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
        public function checkHeadingOffsetsPreserveIdsAndCapAtH6(): void
        {
            $src = "# Раздел API v2\n\n## Детали\n\n###### Предел";
            $html = self::$p->filterDoc($src, true, '', 1);
            $this->assertStringContainsString('<h2 id="раздел-api-v2">Раздел API v2</h2>', $html);
            $this->assertStringContainsString('<h3 id="детали">Детали</h3>', $html);
            $this->assertStringContainsString('<h6 id="предел">Предел</h6>', $html);

            $setext = self::$p->filterDoc("Раздел\n=======\n\nПодраздел\n----------", true, '', 2);
            $this->assertStringContainsString('<h3 id="раздел">Раздел</h3>', $setext);
            $this->assertStringContainsString('<h4 id="подраздел">Подраздел</h4>', $setext);
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
        public function checkDataUriImagePolicy(): void
        {
            if (!class_exists('Template', false)) {
                require_once BASE_DIR.'/core/classes/template.php';
            }
            $hadtpl = array_key_exists('tpl', $GLOBALS);
            $oldtpl = $GLOBALS['tpl'] ?? null;
            $GLOBALS['tpl'] = new \Template('lite');

            $png = base64_encode(str_repeat('a', 1024));
            $limit = base64_encode(str_repeat('a', \Parser::EMBEDMAX));
            $over = base64_encode(str_repeat('a', \Parser::EMBEDMAX + 1));
            $svg = base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

            $pass = [
                'small png' => 'data:image/png;base64,'.$png,
                'jpeg' => 'data:image/jpeg;base64,'.$png,
                'jpg' => 'data:image/jpg;base64,'.$png,
                'gif' => 'data:image/gif;base64,'.$png,
                'webp' => 'data:image/webp;base64,'.$png,
                'uppercase scheme and mime' => 'DATA:IMAGE/PNG;BASE64,'.$png,
                'exact size limit' => 'data:image/png;base64,'.$limit,
            ];
            $block = [
                'oversized png' => 'data:image/png;base64,'.$over,
                'svg with script' => 'data:image/svg+xml;base64,'.$svg,
                'text html' => 'data:text/html,%3Cscript%3Ealert(1)%3C/script%3E',
                'text html base64' => 'data:text/html;base64,'.$png,
                'octet stream' => 'data:application/octet-stream;base64,'.$png,
                'no base64 marker' => 'data:image/png,'.$png,
            ];
            $tricks = [
                'space before mime' => 'data: image/png;base64,'.$png,
                'payload with whitespace' => 'data:image/png;base64,'.substr($png, 0, 8).' '.substr($png, 8),
            ];

            try {
                $parser = new \Parser();
                foreach ([true, false] as $safe) {
                    $mode = $safe ? ' safe' : ' unsafe';
                    foreach ($pass as $case => $uri) {
                        $html = $parser->filterContent('![x]('.$uri.')', $safe, '');
                        $this->assertStringContainsString('src="'.$uri.'"', $html, 'markdown pass '.$case.$mode);
                        $this->assertStringNotContainsString('sl-img-placeholder sl-img', $html, 'markdown pass '.$case.$mode);

                        $bbc = $parser->filterContent('[img]'.$uri.'[/img]', $safe, '');
                        $this->assertStringContainsString('src="'.$uri.'"', $bbc, 'bbcode pass '.$case.$mode);
                    }
                    foreach ($block + $tricks as $case => $uri) {
                        $bbc = $parser->filterContent('[img]'.$uri.'[/img]', $safe, '');
                        $this->assertStringContainsString('sl-img-placeholder', $bbc, 'bbcode block '.$case.$mode);
                        $this->assertStringNotContainsString('data:', $bbc, 'bbcode block '.$case.$mode);
                    }
                }
                foreach ($block as $case => $uri) {
                    $html = $parser->filterContent('![x]('.$uri.')', false, '');
                    $this->assertStringContainsString('sl-img-placeholder', $html, 'markdown block '.$case);
                    $this->assertStringNotContainsString('data:', $html, 'markdown block '.$case);
                }
                foreach ($block + $tricks as $case => $uri) {
                    $raw = $parser->filterContent('<img src="'.$uri.'">', false, '');
                    $this->assertStringContainsString('sl-img-placeholder', $raw, 'html block '.$case);
                    $this->assertStringNotContainsString('data:', $raw, 'html block '.$case);
                }
                $huge = 'data:image/png;base64,'.base64_encode(str_repeat('a', 3145728));
                foreach ([true, false] as $safe) {
                    $md = $parser->filterContent('![x]('.$huge.')', $safe, '');
                    $this->assertStringContainsString('sl-img-placeholder', $md, 'huge markdown');
                    $this->assertStringNotContainsString('src="data:', $md, 'huge markdown');
                    $this->assertStringNotContainsString('src="data:', $parser->filterContent('[img]'.$huge.'[/img]', $safe, ''), 'huge bbcode');
                }
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

        #[Test]
        public function checkReplaceTextRules(): void
        {
            $hadconf = array_key_exists('conf', $GLOBALS);
            $oldconf = $GLOBALS['conf'] ?? null;
            try {
                $GLOBALS['conf'] = ['replace' => ['tmod' => 'foo|bar']];
                $this->assertSame(
                    '<p>literal <2> text <b>one</b> bar</p>',
                    (new \Parser())->filterContent('literal <2> text <b>one</b> foo', false, 'tmod')
                );
                $GLOBALS['conf'] = ['replace' => ['tmod' => 'colou?r|color']];
                $this->assertSame('<p>my color text</p>', (new \Parser())->filterContent('my colour text', false, 'tmod'));
                $GLOBALS['conf'] = ['replace' => ['tmod' => '#tag|link']];
                $this->assertSame('<p>text link here</p>', (new \Parser())->filterContent('text #tag here', false, 'tmod'));
                $GLOBALS['conf'] = ['replace' => ['tmod' => '(broken|x']];
                set_error_handler(static fn(): bool => true);
                try {
                    $out = (new \Parser())->filterContent('plain rule text', false, 'tmod');
                } finally {
                    restore_error_handler();
                }
                $this->assertSame('<p>plain rule text</p>', $out);
            } finally {
                if ($hadconf) $GLOBALS['conf'] = $oldconf;
                else unset($GLOBALS['conf']);
            }
        }

        #[Test]
        public function checkBbTypographyNestingLimit(): void
        {
            $this->assertSame(
                '<p><strong><strong><strong>x</strong></strong></strong></p>',
                self::$p->filterDoc('[b][b][b]x[/b][/b][/b]', false, '')
            );
            $this->assertSame(
                '<p><strong><strong><strong>[b]x</strong></strong></strong>[/b]</p>',
                self::$p->filterDoc(str_repeat('[b]', 4).'x'.str_repeat('[/b]', 4), false, '')
            );
        }

        #[Test]
        public function checkBbImageVariantsMatrix(): void
        {
            if (!class_exists('Template', false)) {
                require_once BASE_DIR.'/core/classes/template.php';
            }
            $hadtpl = array_key_exists('tpl', $GLOBALS);
            $oldtpl = $GLOBALS['tpl'] ?? null;
            $GLOBALS['tpl'] = new \Template('lite');

            try {
                $parser = new \Parser();
                $icon = '/templates/lite/images/favicon.svg';

                $plain = $parser->filterContent('[img]'.$icon.'[/img]', true, '');
                $this->assertStringContainsString('alt="favicon.svg"', $plain);
                $this->assertStringNotContainsString('sl-img-left', $plain);
                $this->assertStringNotContainsString('sl-img-right', $plain);

                $bad = $parser->filterContent('[img=bad]'.$icon.'[/img]', true, '');
                $this->assertStringContainsString('sl-img-left', $bad);

                $named = $parser->filterContent('[img alt=My Image]'.$icon.'[/img]', true, '');
                $this->assertStringContainsString('alt="My Image"', $named);

                $keyword = $parser->filterContent('[img alt=title]'.$icon.'[/img]', true, '');
                $this->assertStringContainsString('alt="favicon.svg"', $keyword);

                $both = $parser->filterContent('[img=right alt=Both Set]'.$icon.'[/img]', true, '');
                $this->assertStringContainsString('sl-img-right', $both);
                $this->assertStringContainsString('alt="Both Set"', $both);

                $md = $parser->filterContent('![]('.$icon.')', true, '');
                $this->assertStringContainsString('alt="favicon.svg"', $md);
            } finally {
                if ($hadtpl) $GLOBALS['tpl'] = $oldtpl;
                else unset($GLOBALS['tpl']);
            }
        }

        #[Test]
        public function checkBbAlignBlockSpansParagraphs(): void
        {
            $html = self::$p->filterDoc("[justify]\n\nFirst para.\n\nSecond para.\n\n[/justify]", true, '');
            $this->assertStringContainsString('<div style="text-align:justify;">', $html);
            $this->assertStringContainsString('<p>First para.</p>', $html);
            $this->assertStringContainsString('<p>Second para.</p>', $html);
            $this->assertStringNotContainsString('[justify]', $html);

            $center = self::$p->filterDoc('[center]Mid[/center]', true, '');
            $this->assertStringContainsString('<div style="text-align:center;">', $center);
        }

        # One set of valid, escaped and broken tags for both readers: a tag filterAttach() renders is always listed, and outside code the two answer the same
        public static function attachCases(): array
        {
            return [
                'short form'         => ['[attach=a.pdf align=left title=A]',                              'a.pdf', true,  true],
                'upper case tag'     => ['[ATTACH=a.pdf align=left title=A]',                              'a.pdf', true,  true],
                'size form'          => ['[attach=a.pdf align=left title=A width=10 height=20]',           'a.pdf', true,  true],
                'relation form'      => ['[attach=a.pdf align=left title=A width=1 height=2 rel=g]',       'a.pdf', true,  true],
                'escaped bracket'    => ['\[attach=a.pdf align=left title=A]',                             'a.pdf', false, false],
                'escaped backslash'  => ['\\\\[attach=a.pdf align=left title=A]',                          'a.pdf', true,  true],
                'escaped trusted'    => ['\[attach=a.pdf align=left title=A]',                             'a.pdf', false, false, false],
                'slash in name'      => ['[attach=x/a.pdf align=left title=A]',                            'x/a.pdf', false, false],
                'no alignment'       => ['[attach=a.pdf title=A]',                                         'a.pdf', false, false],
                'relation alone'     => ['[attach=a.pdf align=left title=A rel=g]',                        'a.pdf', false, false],
                'trusted raw pair'   => ['[usehtml]\[attach=a.pdf align=left title=A][/usehtml]',          'a.pdf', true,  true],
                'inside code span'   => ['`[attach=a.pdf align=left title=A]`',                            'a.pdf', true,  false],
                'inside bb code'     => ['[code][attach=a.pdf align=left title=A][/code]',                 'a.pdf', true,  false],
                'tag across code'    => ['<a `x>` \[attach=a.pdf align=left title=A] >',                   'a.pdf', true,  true],
                'tag across trusted' => ['<a `x>` \[attach=a.pdf align=left title=A] >',                   'a.pdf', true,  true, false],
                'escape after code'  => ['`x` \[attach=a.pdf align=left title=A]',                         'a.pdf', false, false],
            ];
        }

        #[Test]
        #[DataProvider('attachCases')]
        public function checkAttachListFollowsTheRenderedGrammar(string $src, string $name, bool $listed, bool $shown, bool $safe = true): void
        {
            $html = self::$p->filterDoc($src, $safe, '');
            $this->assertSame($shown, str_contains($html, 'uploads/all/'.$name), 'The rendered verdict of this tag is wrong');
            $this->assertSame($listed, in_array($name, self::$p->getAttachList($src), true), 'The listed verdict of this tag is wrong');
        }

        #[Test]
        public function checkAttachListKeepsOrderAndUniqueness(): void
        {
            $src = "[attach=b.png align=left title=B] [attach=a.pdf align=right title=A width=10 height=20]\n"
                .'[attach=b.png align=left title=again] [attach=c.jpg align=center title=C width=1 height=2 rel=g]';
            $this->assertSame(['b.png', 'a.pdf', 'c.jpg'], self::$p->getAttachList($src));
            $this->assertSame([], self::$p->getAttachList('no tag at all'));
        }

        # A stored Node material (nid above zero) links the controlled attach route of its type with an encoded key, escaped once for the attribute, and never the closed directory;
        # the thumb gets thumb=1 for the copy that exists, the old call keeps the direct address, and the memory of a request keeps both renderings apart
        #[Test]
        public function checkAttachOfAStoredMaterialUsesTheControlledRoute(): void
        {
            if (!defined('UPLOADS_DIR')) define('UPLOADS_DIR', BASE_DIR.'/uploads');
            $mod = 'zzparsernid';
            $dir = BASE_DIR.'/uploads/'.$mod;
            $keep = $GLOBALS['conf']['filetype'] ?? null;
            $GLOBALS['conf']['filetype'] = ['pdf' => '<a href="[src]">[title]</a>', 'png' => '<a href="[src]"><img src="[tsrc]" alt="[title]"></a>'];
            mkdir($dir.'/thumb', 0777, true);
            try {
                $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');
                file_put_contents($dir.'/a-abcdefghij-2.png', $png);
                file_put_contents($dir.'/thumb/a-abcdefghij-2.png', $png);
                $src = '[attach=my file.pdf align=left title=Doc] [attach=a-abcdefghij-2.png align=left title=A]';
                $old = self::$p->filterDoc($src, true, $mod);
                $new = self::$p->filterDoc($src, true, $mod, 0, '', 7);
                $this->assertStringContainsString('href="uploads/'.$mod.'/my file.pdf"', $old, 'The old call lost its direct address');
                $this->assertStringContainsString('src="uploads/'.$mod.'/thumb/a-abcdefghij-2.png"', $old);
                $base = 'index.php?name='.$mod.'&amp;op=attach&amp;id=7&amp;key=';
                $this->assertStringContainsString('href="'.$base.'my%20file.pdf"', $new);
                $this->assertStringContainsString('href="'.$base.'a-abcdefghij-2.png"><img src="'.$base.'a-abcdefghij-2.png&amp;thumb=1"', $new);
                $this->assertStringNotContainsString('uploads/', $new, 'A stored material links the closed directory');
                $this->assertSame($new, self::$p->filterDoc($src, true, $mod, 0, '', 7));
                $this->assertSame($old, self::$p->filterDoc($src, true, $mod, 0, '', 0));
                $this->assertSame($old, self::$p->filterDoc($src, true, $mod, 0, '', -3), 'A negative nid is no material');
            } finally {
                $GLOBALS['conf']['filetype'] = $keep;
                if ($keep === null) unset($GLOBALS['conf']['filetype']);
                foreach (['thumb/a-abcdefghij-2.png', 'a-abcdefghij-2.png'] as $one) if (is_file($dir.'/'.$one)) unlink($dir.'/'.$one);
                if (is_dir($dir.'/thumb')) rmdir($dir.'/thumb');
                if (is_dir($dir)) rmdir($dir);
            }
        }

        # A safe rendering that trusts its tags makes markup of what [usehtml] encloses and runs what [usephp] encloses, while foreign markup, a free block and an
        # unsafe link around them stay text; an indented line inside a tag is no code block, and without safe mode the argument changes nothing
        #[Test]
        public function checkTrustedTagsTrustOnlyTheirContent(): void
        {
            $html = self::$p->filterDoc('<b>foreign</b> [url=javascript:alert(1)]u[/url] [block=1] [usehtml]<i>own</i>[/usehtml]', true, '', 0, '', 0, true);
            $this->assertStringContainsString('<i>own</i>', $html, 'The content of a trusted tag was not trusted');
            $this->assertStringContainsString('&lt;b&gt;foreign', $html, 'Markup around a trusted tag was trusted');
            $this->assertStringNotContainsString('<b>foreign', $html);
            $this->assertStringNotContainsString('javascript:', $html, 'An unsafe link around a trusted tag survived');
            $this->assertStringContainsString('[block=1]', $html, 'A free block around a trusted tag was rendered');
            $run = self::$p->filterDoc('[usephp]echo 6*7;[/usephp] <script>x()</script>', true, '', 0, '', 0, true);
            $this->assertStringContainsString('42', $run, 'The content of [usephp] did not run');
            $this->assertStringNotContainsString('<script>', $run, 'A script around [usephp] was trusted');
            $flag = new \ReflectionProperty(\Parser::class, 'vary');
            $this->assertTrue($flag->getValue(self::$p), 'Executed php of a trusting safe rendering may be stored');
            $deep = self::$p->filterDoc("[usehtml]\n    <div>x</div>\n[/usehtml]\n\n    code", true, '', 0, '', 0, true);
            $this->assertStringContainsString('<div>x</div>', $deep, 'An indented line inside a trusted tag became code');
            $this->assertStringContainsString('<code', $deep, 'An indented line outside the tags stopped being code');
            $this->assertSame('<p>[usehtml]a[/usehtml]</p>', self::$p->filterDoc('[usehtml]a[/usehtml]', true), 'A safe rendering without trust honoured a tag');
            $this->assertSame(self::$p->filterDoc('<b>x</b> [usehtml]y[/usehtml]', false), self::$p->filterDoc('<b>x</b> [usehtml]y[/usehtml]', false, '', 0, '', 0, true));
        }

        # A text mixing the three forms of the attachment grammar renders every attachment, the same set getAttachList() names
        #[Test]
        public function checkMixedAttachFormsRenderEveryOne(): void
        {
            $src = '[attach=a.pdf align=left title=A] [attach=b.pdf align=left title=B width=10 height=20] [attach=c.pdf align=left title=C width=1 height=2 rel=g]';
            $html = self::$p->filterDoc($src, true, '');
            foreach (['a.pdf', 'b.pdf', 'c.pdf'] as $name) $this->assertStringContainsString('uploads/all/'.$name, $html, 'A mixed form was not rendered: '.$name);
            $this->assertSame(['a.pdf', 'b.pdf', 'c.pdf'], self::$p->getAttachList($src));
        }

        #[Test]
        public function checkRawBbPairsKeepTheirBackslashes(): void
        {
            $this->assertStringContainsString('a\.b', self::$p->filterDoc('[code]a\.b[/code]', true));
            $this->assertStringContainsString('<i>a\*b</i>', self::$p->filterDoc('[usehtml]<i>a\*b</i>[/usehtml]', false));
            $this->assertSame('<p>[usehtml]a\*b[/usehtml]</p>', self::$p->filterDoc('[usehtml]a\*b[/usehtml]', true));
        }

        # The canonical feed document escapes everything it received, so neither mode of the full parser lets a remote tag, link, heading or smilie through
        #[Test]
        public function checkFeedDocumentRendersAsLiteralTextInBothModes(): void
        {
            require_once BASE_DIR.'/core/classes/feed.php';
            $title = '[hide]H[/hide] [attach=a.pdf align=left title=A] [url=javascript:alert(1)]u[/url] **s** ~~d~~ ==m== *01 [block=1] [usephp]echo 1;[/usephp] `c` # h';
            $desc = '<p>&lt;script&gt;alert(1)&lt;/script&gt; [quote]q[/quote] | a | b |</p><p>- item</p><p>1. item</p><p>&gt; quote</p><p>    code</p>';
            $xml = '<?xml version="1.0"?><rss version="2.0"><channel><item><title>'.htmlspecialchars($title, ENT_XML1).'</title>'
                .'<link>https://ex.org/a(b)[c]*01`d\\e</link><description>'.htmlspecialchars($desc, ENT_XML1).'</description></item></channel></rss>';
            $send = fn(string $op, array $req): array => ($op === 'resolve') ? ['addresses' => ['93.184.216.34']] : ['code' => 200, 'headers' => [], 'body' => $xml];
            $body = (new \Feed(['bytes' => '2097152', 'timeout' => '10', 'redirects' => '3', 'max' => '50'], $send))->getFeedContent('https://ex.org/f')['body'];
            foreach ([true, false] as $safe) {
                $html = self::$p->filterDoc($body, $safe, '');
                $mode = $safe ? ' (safe)' : ' (trusted)';
                $tags = ['<script', 'href="javascript', '<strong>', '<del>', '<mark>', '<code>', '<blockquote', '<ul>', '<ol>', '<pre>', '<table>', 'uploads/', '<img', '<h1'];
                foreach ($tags as $bad) {
                    $this->assertStringNotContainsString($bad, $html, $bad.$mode);
                }
                $want = '[hide]H[/hide] [attach=a.pdf align=left title=A] [url=javascript:alert(1)]u[/url] **s** ~~d~~ ==m== *01';
                $this->assertStringContainsString($want, html_entity_decode($html), 'The title is shown as typed'.$mode);
                $this->assertSame(1, substr_count($html, '<h2'), 'Exactly the one heading Feed wrote'.$mode);
                $this->assertStringContainsString('href="https://ex.org/a%28b%29%5Bc%5D%2A01%60d%5Ce"', $html, 'The link keeps the whole address'.$mode);
                $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html, $mode);
            }
        }
    }
}
