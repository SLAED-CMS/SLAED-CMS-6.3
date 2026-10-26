<?php
declare(strict_types=1);

namespace {
    if (!class_exists('Parser', false)) {
        require_once BASE_DIR.'/core/classes/parser.php';
    }

    if (!defined('_QUOTE'))    define('_QUOTE',    'Quote');
    if (!defined('_HIDE'))     define('_HIDE',     'Hidden');
    if (!defined('_HIDETEXT')) define('_HIDETEXT', 'Show');
    if (!defined('_CODE'))     define('_CODE',     'Code');
    if (!defined('_COPY'))     define('_COPY',     'Copy');
    if (!defined('_COPYDONE')) define('_COPYDONE', 'Copied');
    if (!defined('_SHOWALL'))  define('_SHOWALL',  'Show all');
    if (!defined('_COLLAPSE')) define('_COLLAPSE', 'Collapse');

    if (!function_exists('replace_break')) {
        function replace_break(string $s): string { return $s; }
    }
    if (!function_exists('is_user')) {
        function is_user(): bool { return false; }
    }
    # The hidden block of a guest links the login page through the router of core/system.php, which a unit run does not load
    if (!function_exists('getSeoUrl')) {
        function getSeoUrl(array $params): string { return '/index.php?'.http_build_query($params); }
    }
    if (!function_exists('getDecodedText')) {
        function getDecodedText(string $text): string { return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
    }
    if (!function_exists('getUploadRuleData')) {
        function getUploadRuleData(string $mod): array { return ['thumbwidth' => 250]; }
    }
    if (!function_exists('getUploadFolder')) {
        function getUploadFolder(string $mod, bool $node = false): string { return $node ? 'node/'.$mod : $mod; }
    }
    # The adapters of the real factory plus one closed owner, so a parse is checked against every form of the address a file context answers
    if (!function_exists('getFileService')) {
        function getFileService(): FileAccess
        {
            static $fac = null;
            require_once BASE_DIR.'/core/classes/access.php';
            $none = fn(string $mod, int $id, string $key): bool => false;
            return $fac ??= new FileAccess([
                'node' => ['folder' => fn(string $mod, int $id): string => getUploadFolder($mod, true), 'grant' => $none],
                'forum' => ['folder' => fn(string $mod, int $id): string => 'forum', 'grant' => $none],
                'public' => ['folder' => fn(string $mod, int $id): string => getUploadFolder($mod)],
            ]);
        }
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

        # Every element the parser emits is rendered by the theme, so the expected bytes of the fixtures are what a theme produces, not what PHP concatenates
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

        # The BB tags the parser no longer reads are text in every mode, so a stray one shows as the author typed it and never as markup
        # Script-bearing schemes must die in trusted mode too: comments render at safe=false, so an allowlist that only applies to safe mode leaves stored XSS reachable
        # Code wins over the SLAED tags, which is what lets a document write a tag as an example instead of executing it
        # A conversation channel breaks on the line ending its author typed; plain Markdown joins those lines and plain format recognizes no Markdown at all
        # The super administrator capability is a write-boundary rule, so the parser still runs the tag it was handed; safe mode leaves it as the typed literal text
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
                    "<h1 id=\"привет-мир\">Привет мир</h1>\n<h1 id=\"привет-мир-1\">Привет мир</h1>",
                ],

                'bold md'         => ['**bold**',   true, '', '<p><strong>bold</strong></p>'],
                'italic md'       => ['*italic*',   true, '', '<p><em>italic</em></p>'],
                'del mark'        => ['~~del~~ ==mark==', true, '', '<p><del>del</del> <mark>mark</mark></p>'],
                'link md'         => ['[link](https://example.com)', true, '', '<p><a href="https://example.com">link</a></p>'],
                'bold md + bb bold' => ['**bold** и [b]bb-bold[/b]', true, '', '<p><strong>bold</strong> и [b]bb-bold[/b]</p>'],
                'bb pairs are text' => ['[i]i[/i] [u]u[/u] [s]s[/s]', false, '', '<p>[i]i[/i] [u]u[/u] [s]s[/s]</p>'],
                'bb spans are text' => ['[color=red]r[/color] [size=9]s[/size] [family=Arial]f[/family]', true, '',
                    '<p>[color=red]r[/color] [size=9]s[/size] [family=Arial]f[/family]</p>'],
                'bb html stays out' => ['[b]<img src=x onerror=alert(1)>[/b]', true, '', '<p>[b]&lt;img src=x onerror=alert(1)&gt;[/b]</p>'],
                'bb blocks are text' => ["[quote]q[/quote]\n\n[center]c[/center]\n\n[hr]\n\n[li]x", false, '',
                    "<p>[quote]q[/quote]</p>\n<p>[center]c[/center]</p>\n<p>[hr]</p>\n<p>[li]x</p>"],
                'bb code is markdown' => ['[code]a\.b[/code] [php]echo 1;[/php]', true, '', '<p>[code]a.b[/code] [php]echo 1;[/php]</p>'],
                'bb media are text'  => ['[img]/x.png[/img] [img=left alt=A]/x.png[/img] [url=/a]t[/url] [mail=a]m[/mail]', true, '',
                    '<p>[img]/x.png[/img] [img=left alt=A]/x.png[/img] [url=/a]t[/url] [mail=a]m[/mail]</p>'],
                'ed2k is text'       => ['[url]ed2k://|file|a.zip|10|ABC|/[/url]', true, '', '<p>[url]ed2k://|file|a.zip|10|ABC|/[/url]</p>'],
                'smilie is text'     => ['*01 text *18', false, '', '<p>*01 text *18</p>'],

                'ul basic' => ["- a\n- b\n- c", true, '', "<ul>\n<li>a</li>\n<li>b</li>\n<li>c</li>\n</ul>"],
                'ol basic' => ["1. a\n2. b",     true, '', "<ol>\n<li>a</li>\n<li>b</li>\n</ol>"],
                'ul nested' => ["- item1\n  - nested\n- item2", true, '',
                    "<ul>\n<li>item1\n<ul>\n<li>nested</li>\n</ul>\n</li>\n<li>item2</li>\n</ul>"],
                'ol from three' => ["3. a\n4. b", true, '', "<ol start=\"3\">\n<li>a</li>\n<li>b</li>\n</ol>"],
                'ul loose'      => ["- a\n\n- b", true, '', "<ul>\n<li>\n<p>a</p>\n</li>\n<li>\n<p>b</p>\n</li>\n</ul>"],

                'inline code'         => ['`inline`',              true, '', '<p><code>inline</code></p>'],
                'inline code in text' => ['текст `code` текст',    true, '', '<p>текст <code>code</code> текст</p>'],

                'table basic' => [
                    "| A | B |\n|---|---|\n| 1 | 2 |", true, '',
                    "<table>\n<thead>\n<tr><th>A</th><th>B</th></tr>\n</thead>\n<tbody>\n<tr><td>1</td><td>2</td></tr>\n</tbody>\n</table>",
                ],

                'table align' => [
                    "| L | C | R |\n|:--|:--:|--:|\n| a | b | c |", true, '',
                    "<table>\n<thead>\n<tr><th style=\"text-align:left\">L</th><th style=\"text-align:center\">C</th>"
                    ."<th style=\"text-align:right\">R</th></tr>\n</thead>\n<tbody>\n"
                    ."<tr><td style=\"text-align:left\">a</td><td style=\"text-align:center\">b</td><td style=\"text-align:right\">c</td></tr>\n</tbody>\n</table>",
                ],

                'url md safe javascript' => ['[l](javascript:x)',              true, '', '<p><a href="#">l</a></p>'],
                'url autolink safe javascript' => ['<javascript:x>',           true, '', '<p><a href="#">javascript:x</a></p>'],
                'url md safe data html'  => ['[l](data:text/html,x)',          true, '', '<p><a href="#">l</a></p>'],
                'url md unsafe data html' => ['[l](data:text/html,x)',         false, '', '<p><a href="#">l</a></p>'],
                'url md unsafe uppercase data' => ['[l](DATA:text/html,x)',    false, '', '<p><a href="#">l</a></p>'],
                'url md unsafe other scheme'   => ['[l](ftp://ok.com/f)',      false, '', '<p><a href="ftp://ok.com/f">l</a></p>'],
                'url md safe other scheme'     => ['[l](ftp://ok.com/f)',      true,  '', '<p><a href="#">l</a></p>'],
                'url autolink safe https' => ['<https://ok.com>',              true, '', '<p><a href="https://ok.com">https://ok.com</a></p>'],
                'url md safe mailto'     => ['[l](mailto:a@b.com)',            true, '', '<p><a href="mailto:a@b.com">l</a></p>'],
                'url md safe local'      => ['[l](/local/path)',               true, '', '<p><a href="/local/path">l</a></p>'],
                'url md safe relative'   => ['[l](../uploads/file.pdf)',       true, '', '<p><a href="../uploads/file.pdf">l</a></p>'],

                'url md unsafe javascript'        => ['[l](javascript:x)',            false, '', '<p><a href="#">l</a></p>'],
                'url autolink unsafe javascript'  => ['<javascript:x>',               false, '', '<p><a href="#">javascript:x</a></p>'],
                'url md unsafe vbscript'          => ['[l](vbscript:x)',              false, '', '<p><a href="#">l</a></p>'],
                'url md safe vbscript'            => ['[l](vbscript:x)',              true,  '', '<p><a href="#">l</a></p>'],
                'url md unsafe mixed case js'     => ['[l](JaVaScRiPt:x)',            false, '', '<p><a href="#">l</a></p>'],
                'url md unsafe javascript space'  => ['[l]( javascript:x)',           false, '', '<p><a href="#">l</a></p>'],
                'url md unsafe javascript tab'    => ["[l](<java\tscript:x>)",        false, '', '<p><a href="java%09script:x">l</a></p>'],
                'url md unsafe javascript entity' => ['[l](java&#9;script:x)',        false, '', '<p><a href="java%09script:x">l</a></p>'],

                'safe script tag' => ['<script>alert(1)</script>',              true,  '', '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>'],
                'safe b tag'      => ['<b>ok</b>',                              true,  '', '<p>&lt;b&gt;ok&lt;/b&gt;</p>'],
                'safe a js href'  => ['<a href="javascript:x">click</a>',       true,  '', '<p>&lt;a href=&quot;javascript:x&quot;&gt;click&lt;/a&gt;</p>'],

                'unsafe script tag' => ['<script>x</script>',                false, '', '&lt;script>x&lt;/script>'],
                'unsafe div block'  => ["<div class=\"x\">\ntext\n</div>",    false, '', "<div class=\"x\">\ntext\n</div>"],
                'usehtml tag'       => ['[usehtml]<b>html</b>[/usehtml]',     false, '', '<b>html</b>'],

                'code span keeps tag pair'  => ['`[hide]x[/hide]`',     true,  '', '<p><code>[hide]x[/hide]</code></p>'],
                'code span keeps lone tag'  => ['`[tabs=1]` and `[tab=a]`', true, '', '<p><code>[tabs=1]</code> and <code>[tab=a]</code></p>'],
                'code span keeps trusted'   => ['`[usehtml]x[/usehtml]`', false, '', '<p><code>[usehtml]x[/usehtml]</code></p>'],
                'link target keeps tag'     => ['[a](/x[hide]y[/hide]) <https://y/[hide]>', true, '',
                    '<p><a href="/x%5Bhide%5Dy%5B/hide%5D">a</a> <a href="https://y/%5Bhide%5D">https://y/[hide]</a></p>'],

                'markdown joins soft lines' => ["one\ntwo",             true,  '', '<p>one'."\n".'two</p>'],
                'breaks keep soft lines'    => ["one\ntwo",             true,  '', '<p>one<br>'."\n".'two</p>', 'breaks'],
                'breaks still read markdown'=> ["**bold**\nnext",       true,  '', '<p><strong>bold</strong><br>'."\n".'next</p>', 'breaks'],
                'breaks keep the hard one'  => ["one\\\ntwo",           true,  '', '<p>one<br>'."\n".'two</p>', 'breaks'],
                'breaks keep a typed self-closing break' => ["one<br/>\ntwo<br />\nthree", false, '', "<p>one<br/>\ntwo<br />\nthree</p>", 'breaks'],

                'plain editor text after a list is parsed' => [
                    "a<br>\n<br>\n- x<br>\n<br>\n[l](https://e.com)<br>\nz", false, '',
                    "<p>a<br>\n<br></p>\n<ul>\n<li>x<br>\n<br>\n<a href=\"https://e.com\">l</a><br>\nz</li>\n</ul>",
                ],
                'raw html block keeps its breaks' => ["<div>\nx<br>\ny\n</div>", false, '', "<div>\nx<br>\ny\n</div>"],

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
                'code span keeps pair'    => ['`a\*b`',            true,  '', '<p><code>a\*b</code></p>'],
                'escaped backtick'        => ['\`x`',              true,  '', '<p>`x`</p>'],
                'trusted script is raw'   => ['<script>var q = "\"";</script>', false, '', '&lt;script>var q = "\"";&lt;/script>'],
                'trusted attribute is raw'=> ['<a title="x\*y">t</a>', false, '', '<p><a title="x\*y">t</a></p>'],
            ];
        }

        #[Test]
        #[DataProvider('deterministicCases')]
        public function filterDocMatchesExpected(string $src, bool $safe, string $mod, string $expected, string $fmt = ''): void
        {
            $this->assertSame($expected, self::$p->filterDoc($src, $safe, $mod, 0, $fmt));
        }

        # A rendering may only be stored when it answers for nobody in particular; a hidden block would leak, as a stored copy shows a visitor what only members read
        public static function varyCases(): array
        {
            return [
                'plain text is storable'     => ['just **text** and a [b]tag[/b]',      true,  false],
                'code keeps it storable'     => ['`[hide]secret[/hide]`',               true,  false],
                'hidden block varies'        => ['[hide]secret[/hide]',                 true,  true],
                'executed php varies'        => ['[usephp]echo 1;[/usephp]',            false, true],
                'markdown image varies'      => ['![alt](uploads/all/x.png)',           true,  true],
                'bb image is storable text'  => ['[img]uploads/all/x.png[/img]',        true,  false],
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
                '[hide]секрет[/hide]'                           => ['[hide]секрет[/hide]'],
                '[hide] with a quote'                           => ["[hide]\n> > q\n[/hide]"],
                '[hide] with list'                              => ["[hide]\n- item1\n- item2\n[/hide]"],
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
                $missing = $parser->filterContent('![gone](/uploads/parser-missing.png)', true, '');
                $this->assertStringContainsString('<i class="bi bi-image sl-img-placeholder" role="img" aria-label="gone">', $missing);

                $existing = $parser->filterContent('![](/templates/lite/images/favicon.svg)', true, '');
                $this->assertStringContainsString('src="/templates/lite/images/favicon.svg"', $existing);
                $this->assertStringContainsString('alt=""', $existing, 'A Markdown image without a description took an alt CommonMark does not give it');
                $this->assertStringContainsString('onerror="this.onerror=null;this.hidden=true;this.nextElementSibling.hidden=false"', $existing);
                $this->assertStringNotContainsString('class="sl-img', $existing);
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
                        $this->assertStringNotContainsString('sl-img-placeholder" role="img" aria-label="x">', $html, 'markdown pass '.$case.$mode);
                    }
                    foreach ($block + $tricks as $case => $uri) {
                        $html = $parser->filterContent('![x](<'.$uri.'>)', $safe, '');
                        $this->assertStringContainsString('sl-img-placeholder', $html, 'markdown block '.$case.$mode);
                        $this->assertStringNotContainsString('data:', $html, 'markdown block '.$case.$mode);
                    }
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
                    '<p>literal &lt;2&gt; text <b>one</b> bar</p>',
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

        # A code block is written by any author, so every highlighter mode must escape it: mode 2 hands the code to its fragment as markup and once let a tag through
        #[Test]
        public function checkCodeBlocksEscapeInEveryMode(): void
        {
            $hadconf = array_key_exists('conf', $GLOBALS);
            $oldconf = $GLOBALS['conf'] ?? null;
            try {
                foreach (['0', '1', '2'] as $mode) {
                    $GLOBALS['conf'] = ['syntax' => $mode];
                    foreach (["```php\necho \"<script>x</script>\";\n```", "```js\nvar a = \"<script>x</script>\";\n```"] as $src) {
                        foreach ([true, false] as $safe) {
                            $html = (new \Parser())->filterDoc($src, $safe, '');
                            $this->assertStringNotContainsString('<script>x', $html, "mode $mode: $src");
                            $this->assertStringContainsString('script&gt;', $html, "mode $mode: $src");
                        }
                    }
                }
            } finally {
                if ($hadconf) $GLOBALS['conf'] = $oldconf;
                else unset($GLOBALS['conf']);
            }
        }

        # A fenced and an indented block render through the code block: the block with its head, the language named, the code escaped once in every mode
        #[Test]
        public function checkFencedCodeTakesTheCodeBlock(): void
        {
            $hadconf = array_key_exists('conf', $GLOBALS);
            $oldconf = $GLOBALS['conf'] ?? null;
            try {
                foreach (['0', '1', '2'] as $mode) {
                    $GLOBALS['conf'] = ['syntax' => $mode];
                    $html = (new \Parser())->filterDoc("```php\necho '<b>';\n```", true, '');
                    $this->assertStringContainsString('data-sl-code', $html, "mode $mode: no block");
                    $this->assertStringContainsString('<span>PHP</span>', $html, "mode $mode: the language is not named");
                    $this->assertStringNotContainsString('<b>', $html, "mode $mode: the code is not escaped");
                    $this->assertStringNotContainsString('&amp;lt;', $html, "mode $mode: the code is escaped twice");
                    $html = (new \Parser())->filterDoc("```\nplain\n```", true, '');
                    $this->assertStringContainsString('<span>Code</span>', $html, "mode $mode: a block without a language is not named as code");
                    $this->assertStringNotContainsString('?php', $html, "mode $mode: the highlighter prefix shows");
                    $html = (new \Parser())->filterDoc("text\n\n    indented();\n", true, '');
                    $this->assertStringContainsString('data-sl-code', $html, "mode $mode: an indented block is no code block");
                }
                $html = (new \Parser())->filterDoc("```js\n\n    a();\nb();\n```", true, '');
                $this->assertStringContainsString("<code class=\"language-js\">\n    a();\nb();</code>", $html, 'the code lost its first blank line or an indent');
                $html = (new \Parser())->filterDoc("```php\necho '<i>';\n```", true, '');
                $this->assertStringContainsString('<code class="language-php">echo &#039;&lt;i&gt;&#039;;</code>', $html, 'highlight.js receives no language');
                $html = (new \Parser())->filterDoc("> ```\n> [hide]x[/hide]\n> ```", true, '');
                $this->assertStringContainsString('<code>[hide]x[/hide]</code>', $html, 'A fence inside a quote handed its tag to the bracket layer');
                $this->assertStringStartsWith('<blockquote>', $html, 'A fence inside a quote is no block of the quote');
            } finally {
                if ($hadconf) $GLOBALS['conf'] = $oldconf;
                else unset($GLOBALS['conf']);
            }
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
                'full size'          => ['[attach=a.pdf align=left title=A size=full]',                    'a.pdf', true,  true],
                'full after rel'     => ['[attach=a.pdf align=left title=A width=1 height=2 rel=g size=full]', 'a.pdf', true, true],
                'full before width'  => ['[attach=a.pdf align=left title=A size=full width=1 height=2]',   'a.pdf', false, false],
                'size not full'      => ['[attach=a.pdf align=left title=A size=thumb]',                   'a.pdf', false, false],
                'trusted raw pair'   => ['[usehtml]\[attach=a.pdf align=left title=A][/usehtml]',          'a.pdf', true,  true],
                'inside code span'   => ['`[attach=a.pdf align=left title=A]`',                            'a.pdf', true,  false],
                'inside a dropped tag' => ['[code][attach=a.pdf align=left title=A][/code]',               'a.pdf', true,  true],
                'tag across code'    => ['<a `x>` \[attach=a.pdf align=left title=A] >',                   'a.pdf', false, false],
                'tag across trusted' => ['<a `x>` \[attach=a.pdf align=left title=A] >',                   'a.pdf', false, false, false],
                'tag in a raw tag'   => ['<span title="[attach=a.pdf align=left title=A]">x</span>',      'a.pdf', true,  false, false],
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

        # A file context [owner, id] links the file route with an encoded key, escaped once for the attribute, for Node and any closed owner alike, and never a closed folder
        # The thumb gets thumb=1 for the copy that exists, the old call of a private folder gets no direct address, target 0 the preview, and the memory keeps them apart
        #[Test]
        public function checkAttachOfAStoredMaterialUsesTheControlledRoute(): void
        {
            if (!defined('UPLOADS_DIR')) define('UPLOADS_DIR', BASE_DIR.'/uploads');
            $mod = 'zzparsernid';
            $dir = UPLOADS_DIR.'/node/'.$mod;
            $keep = $GLOBALS['conf']['filetype'] ?? null;
            $GLOBALS['conf']['filetype'] = ['pdf' => '<a href="[src]">[title]</a>', 'png' => '<a href="[src]"><img src="[tsrc]" alt="[title]"></a>'];
            mkdir($dir.'/thumb', 0777, true);
            try {
                $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');
                file_put_contents($dir.'/a-abcdefghij-2.png', $png);
                file_put_contents($dir.'/thumb/a-abcdefghij-2.png', $png);
                $src = '[attach=my file.pdf align=left title=Doc] [attach=a-abcdefghij-2.png align=left title=A]';
                $old = self::$p->filterDoc($src, true, $mod);
                $new = self::$p->filterDoc($src, true, $mod, 0, '', ['node', 7]);
                $this->assertStringNotContainsString('uploads/', $old, 'The old call spelled a direct address of a private folder, which only the route of its owner may build');
                $base = '/index.php?go=file&amp;own=node&amp;id=7&amp;key=';
                $this->assertStringContainsString('href="'.$base.'my%20file.pdf"', $new);
                $this->assertStringContainsString('href="'.$base.'a-abcdefghij-2.png"><img src="'.$base.'a-abcdefghij-2.png&amp;thumb=1"', $new);
                $this->assertStringNotContainsString('uploads/', $new, 'A stored material links the closed directory');
                $this->assertSame($new, self::$p->filterDoc($src, true, $mod, 0, '', ['node', 7]));
                $draft = self::$p->filterDoc($src, true, $mod, 0, '', ['node', 0]);
                $this->assertStringContainsString('href="/index.php?go=file&amp;own=node&amp;name='.$mod.'&amp;key=my%20file.pdf&amp;preview=1"', $draft,
                    'An unsaved text did not take the preview of its owner');
                $draft = self::$p->filterDoc('[attach=my file.pdf align=left title=Doc]', true, 'forum', 0, '', ['forum', 0]);
                $this->assertStringContainsString('href="/index.php?go=file&amp;own=forum&amp;name=forum&amp;key=my%20file.pdf&amp;preview=1"', $draft,
                    'An unsaved post did not take the preview of the forum');
                $this->assertSame($old, self::$p->filterDoc($src, true, $mod, 0, '', ['node', -3]), 'A negative id is no target');
                $this->assertSame($old, self::$p->filterDoc($src, true, $mod, 0, '', ['node', '7']), 'An id that is no number is no target');
                $shut = self::$p->filterDoc('[attach=my file.pdf align=left title=Doc]', true, 'forum', 0, '', ['forum', 5]);
                $this->assertStringContainsString('href="/index.php?go=file&amp;own=forum&amp;id=5&amp;key=my%20file.pdf"', $shut, 'A closed owner did not link its file route');
            } finally {
                $GLOBALS['conf']['filetype'] = $keep;
                if ($keep === null) unset($GLOBALS['conf']['filetype']);
                foreach (['thumb/a-abcdefghij-2.png', 'a-abcdefghij-2.png'] as $one) if (is_file($dir.'/'.$one)) unlink($dir.'/'.$one);
                if (is_dir($dir.'/thumb')) rmdir($dir.'/thumb');
                if (is_dir($dir)) rmdir($dir);
            }
        }

        # A safe rendering that trusts its tags makes markup of what [usehtml] encloses and runs what [usephp] encloses
        # Foreign markup, a free block and an unsafe link around them stay text; an indented line inside a tag is no code block, and without safe mode the argument changes nothing
        #[Test]
        public function checkTrustedTagsTrustOnlyTheirContent(): void
        {
            $html = self::$p->filterDoc('<b>foreign</b> [u](javascript:alert(1)) [block=1] [usehtml]<i>own</i>[/usehtml]', true, '', 0, '', [], true);
            $this->assertStringContainsString('<i>own</i>', $html, 'The content of a trusted tag was not trusted');
            $this->assertStringContainsString('&lt;b&gt;foreign', $html, 'Markup around a trusted tag was trusted');
            $this->assertStringNotContainsString('<b>foreign', $html);
            $this->assertStringNotContainsString('javascript:', $html, 'An unsafe link around a trusted tag survived');
            $this->assertStringContainsString('[block=1]', $html, 'A free block around a trusted tag was rendered');
            $run = self::$p->filterDoc('[usephp]echo 6*7;[/usephp] <script>x()</script>', true, '', 0, '', [], true);
            $this->assertStringContainsString('42', $run, 'The content of [usephp] did not run');
            $this->assertStringNotContainsString('<script>', $run, 'A script around [usephp] was trusted');
            $flag = new \ReflectionProperty(\Parser::class, 'vary');
            $this->assertTrue($flag->getValue(self::$p), 'Executed php of a trusting safe rendering may be stored');
            $deep = self::$p->filterDoc("[usehtml]\n    <div>x</div>\n[/usehtml]\n\n    code", true, '', 0, '', [], true);
            $this->assertStringContainsString('<div>x</div>', $deep, 'An indented line inside a trusted tag became code');
            $this->assertStringContainsString('<code', $deep, 'An indented line outside the tags stopped being code');
            $this->assertSame('<p>[usehtml]a[/usehtml]</p>', self::$p->filterDoc('[usehtml]a[/usehtml]', true), 'A safe rendering without trust honoured a tag');
            $this->assertSame(self::$p->filterDoc('<b>x</b> [usehtml]y[/usehtml]', false), self::$p->filterDoc('<b>x</b> [usehtml]y[/usehtml]', false, '', 0, '', [], true));
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

        # The full-size form of an image renders the template full with the image itself and its own size, makes no thumb, and falls back to the thumbnail without that template
        #[Test]
        public function checkFullSizeAttachRendersTheImageItself(): void
        {
            if (!defined('UPLOADS_DIR')) define('UPLOADS_DIR', BASE_DIR.'/uploads');
            $mod = 'zzparserfull';
            $dir = UPLOADS_DIR.'/node/'.$mod;
            $keep = $GLOBALS['conf']['filetype'] ?? null;
            $thumb = '<a href="[src]"><img src="[tsrc]" alt="[title]"></a>';
            $full = '<a href="[src]"><img src="[src]" width="[width]" height="[height]"></a>';
            $GLOBALS['conf']['filetype'] = ['pdf' => '<a href="[src]">[title]</a>', 'png' => $thumb, 'full' => $full];
            mkdir($dir, 0777, true);
            try {
                $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');
                file_put_contents($dir.'/f-abcdefghij-1.png', $png);
                $src = '[attach=f-abcdefghij-1.png align=left title=F size=full] [attach=d.pdf align=left title=D size=full]';
                $this->assertSame(['f-abcdefghij-1.png', 'd.pdf'], self::$p->getAttachList($src), 'A name of the full-size form is not listed, so the route would refuse it');
                $base = '/index.php?go=file&amp;own=node&amp;id=7&amp;key=';
                $html = self::$p->filterDoc($src, true, $mod, 0, '', ['node', 7]);
                $this->assertStringContainsString('<a href="'.$base.'f-abcdefghij-1.png"><img src="'.$base.'f-abcdefghij-1.png" width="1" height="1"></a>', $html);
                $this->assertStringNotContainsString('thumb=1', $html, 'The full-size form showed a thumbnail');
                $this->assertStringContainsString('<a href="'.$base.'d.pdf">D</a>', $html, 'A file that is no image left its own template');
                $this->assertDirectoryDoesNotExist($dir.'/thumb', 'The full-size form made a thumb nobody shows');
                unset($GLOBALS['conf']['filetype']['full']);
                $html = self::$p->filterDoc($src, true, $mod, 0, '', ['node', 8]);
                $want = '<img src="/index.php?go=file&amp;own=node&amp;id=8&amp;key=f-abcdefghij-1.png" alt="F">';
                $this->assertStringContainsString($want, $html, 'Without the template full the image did not fall back to its thumbnail template');
                $html = self::$p->filterContent($src, true, $mod, 0, '', ['node', 8]);
                $this->assertStringContainsString($want, $html, 'The image repair of a rendered page cut the query off the route of the attachment');
            } finally {
                $GLOBALS['conf']['filetype'] = $keep;
                if ($keep === null) unset($GLOBALS['conf']['filetype']);
                if (is_file($dir.'/f-abcdefghij-1.png')) unlink($dir.'/f-abcdefghij-1.png');
                if (is_dir($dir)) rmdir($dir);
            }
            $code = (string)file_get_contents(BASE_DIR.'/core/system.php');
            $body = substr($code, (int)strpos($code, 'function getImgText('), 1500);
            $this->assertStringContainsString("\$thumb = !\$type && stripos(\$match[0], ' size=full]') === false;", $body,
                'The image of a text names a thumb the full-size form never made');
            $this->assertStringContainsString("(\$thumb ? 'thumb/' : '')", $body, 'The direct address of a public folder lost its thumb');
        }

        #[Test]
        public function checkRawBbPairsKeepTheirBackslashes(): void
        {
            $this->assertStringContainsString('<i>a\*b</i>', self::$p->filterDoc('[usehtml]<i>a\*b</i>[/usehtml]', false));
            $this->assertSame('<p>[usehtml]a\*b[/usehtml]</p>', self::$p->filterDoc('[usehtml]a\*b[/usehtml]', true));
            $html = self::$p->filterDoc("[usehtml]a();\n#\tb();\n        c();\n\t[/usehtml]", false);
            $this->assertStringNotContainsString('[usehtml]', $html, 'A heading and an indented block read inside a raw pair hid its closing tag');
            $this->assertStringNotContainsString('<h1', $html, 'A line inside a raw pair was read as Markdown');
            $this->assertSame('<p><code>[usephp]x[/usephp]</code></p>', self::$p->filterDoc('`[usephp]x[/usephp]`', false), 'A raw pair inside a code span left it');
            $this->assertSame('<p>[code]a.b[/code]</p>', self::$p->filterDoc('[code]a\.b[/code]', true), 'A dropped tag kept its content from Markdown');
        }

        # The canonical feed document escapes everything it received, so neither mode of the full parser lets a remote tag, link or heading through
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

        # The examples of CommonMark 0.31.2 (spec.json as published) and GFM 0.29-gfm, CC-BY-SA 4.0, in both modes; safe skips the raw HTML it escapes and the schemes it refuses
        public static function specCases(): array
        {
            $dir = BASE_DIR.'/tests/Fixtures/spec/';
            $rows = [];
            foreach (['commonmark', 'gfm'] as $spec) {
                foreach (json_decode((string)file_get_contents($dir.$spec.'.json'), true) as $row) {
                    $key = $spec.':'.$row['example'];
                    $md = $row['markdown'];
                    $rows[$key.' trusted'] = [$key, $md, $row['html'], false];
                    preg_match_all('#<(?:/?[A-Za-z][A-Za-z0-9-]*(?:\s|/?>)|[!?])#', $md, $tags);
                    $kept = array_filter($tags[0], fn($tag) => str_contains($row['html'], $tag));
                    $raw = in_array($row['section'], ['HTML blocks', 'Raw HTML', 'Disallowed Raw HTML'], true) || $kept;
                    preg_match_all('#\b(?:href|src)="([A-Za-z][A-Za-z0-9+.\-]*):#', $row['html'], $schemes);
                    $other = array_diff(array_map('strtolower', $schemes[1]), ['http', 'https', 'mailto']);
                    if (!$raw && !$other) $rows[$key.' safe'] = [$key, $md, $row['html'], true];
                }
            }
            return $rows;
        }

        # An example that fails today is listed in failing.json; the list only shrinks, so a listed example that passes fails the test until it leaves the list
        #[Test]
        #[DataProvider('specCases')]
        public function filterDocMeetsTheSpec(string $key, string $md, string $html, bool $safe): void
        {
            static $list = null;
            $list ??= json_decode((string)file_get_contents(BASE_DIR.'/tests/Fixtures/spec/failing.json'), true);
            $mode = $safe ? 'safe' : 'trusted';
            $keep = $GLOBALS['conf']['syntax'] ?? null;
            $GLOBALS['conf']['syntax'] = '2';
            \Parser::$fileoff = true;
            \Parser::$gfmoff = str_starts_with($key, 'commonmark:');
            try {
                $got = self::getSpecHtml(self::$p->filterDoc($md, $safe), true);
            } finally {
                \Parser::$fileoff = false;
                \Parser::$gfmoff = false;
                $GLOBALS['conf']['syntax'] = $keep;
                if ($keep === null) unset($GLOBALS['conf']['syntax']);
            }
            $want = self::getSpecHtml($html, false);
            if (in_array($key, $list[$mode], true)) {
                $this->assertNotSame($want, $got, $key.' ('.$mode.') passes now: remove it from tests/Fixtures/spec/failing.json');
                return;
            }
            $this->assertSame($want, $got, $key.' ('.$mode.') '.json_encode($md));
        }

        # One serialization for both sides: ours loses theme markup alone, the expected side its trimmed last break and a ./ link, which stored texts mean from the site root
        private static function getSpecHtml(string $html, bool $ours): string
        {
            static $names = null;
            $names ??= (new \ReflectionClassConstant('Parser', 'CODENAME'))->getValue();
            if (!$ours) $html = rtrim($html, "\n");
            $doc = \Dom\HTMLDocument::createFromString('<!doctype html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);
            $body = $doc->body;
            if ($ours) {
                foreach (iterator_to_array($body->querySelectorAll('figure[data-sl-code]')) as $fig) {
                    $label = trim((string)$fig->querySelector('figcaption span')?->textContent);
                    $pre = $doc->createElement('pre');
                    $code = $doc->createElement('code');
                    $pre->appendChild($code);
                    if ($label !== '' && $label !== _CODE) $code->setAttribute('class', 'language-'.strtolower($label));
                    $code->textContent = $fig->querySelector('pre')?->textContent."\n";
                    $fig->replaceWith($pre);
                }
                foreach (iterator_to_array($body->querySelectorAll('h1, h2, h3, h4, h5, h6')) as $head) $head->removeAttribute('id');
                foreach (iterator_to_array($body->querySelectorAll('img[onerror] + i[role="img"][hidden]')) as $icon) {
                    $gap = $icon->previousSibling;
                    if ($gap instanceof \Dom\Text && trim($gap->data) === '') $gap->remove();
                    $icon->remove();
                }
                foreach (iterator_to_array($body->querySelectorAll('img[onerror]')) as $img) $img->removeAttribute('onerror');
                foreach (iterator_to_array($body->querySelectorAll('th[style^="text-align:"], td[style^="text-align:"]')) as $cell) {
                    $cell->setAttribute('align', substr((string)$cell->getAttribute('style'), 11));
                    $cell->removeAttribute('style');
                }
                foreach (iterator_to_array($body->querySelectorAll('[class]')) as $node) {
                    $all = preg_split('#\s+#', trim((string)$node->getAttribute('class')));
                    $own = preg_grep('#^(?:sl-|bi$|bi-)#', $all, PREG_GREP_INVERT);
                    if (!$own) $node->removeAttribute('class');
                    elseif (count($own) < count($all)) $node->setAttribute('class', implode(' ', $own));
                }
            } else {
                foreach (iterator_to_array($body->querySelectorAll('code[class^="language-"]')) as $code) {
                    $lang = strtolower(substr((string)$code->getAttribute('class'), 9));
                    $label = $names[$lang] ?? strtoupper($lang);
                    if ($label === '') $code->removeAttribute('class');
                    if ($label !== '') $code->setAttribute('class', 'language-'.strtolower($label));
                }
                foreach (iterator_to_array($body->querySelectorAll('a[href^="./"]')) as $link) $link->setAttribute('href', '/'.substr((string)$link->getAttribute('href'), 2));
            }
            $out = preg_replace('#\x00+#', "\n", trim(self::getSpecNode($body, false), "\x00"));
            $out = str_replace("\n</code></pre>", '</code></pre>', $out);
            return preg_replace('#[ \t]+<br>#', '<br>', $out);
        }

        # A block tag is a boundary whose surrounding blanks are formatting, so they collapse to one line break; text keeps every character
        private static function getSpecNode(\Dom\Node $node, bool $pre): string
        {
            $block = ['p', 'div', 'ul', 'ol', 'li', 'blockquote', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'figure'];
            $out = '';
            foreach ($node->childNodes as $item) {
                if ($item instanceof \Dom\Text) $out .= htmlspecialchars($item->data, ENT_NOQUOTES);
                if (!$item instanceof \Dom\Element) continue;
                $name = $item->localName;
                $attr = [];
                foreach ($item->attributes as $one) $attr[$one->name] = $one->value;
                ksort($attr);
                $tag = '<'.$name;
                foreach ($attr as $an => $av) $tag .= ' '.$an.'="'.htmlspecialchars($av, ENT_QUOTES).'"';
                $tag .= '>';
                $edge = in_array($name, $block, true) && !$pre;
                $inner = self::getSpecNode($item, $pre || $name === 'pre');
                if (!in_array($name, ['br', 'hr', 'img', 'input'], true)) $tag .= ($edge && $name !== 'pre' ? "\x00".$inner."\x00" : $inner).'</'.$name.'>';
                $out .= $edge ? "\x00".$tag."\x00" : $tag;
            }
            return $pre ? $out : preg_replace('#\s*\x00\s*#', "\x00", $out);
        }
    }
}
