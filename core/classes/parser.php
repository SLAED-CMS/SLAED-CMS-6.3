<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

class Parser {
    public const EMBEDMAX = 65536;
    public const EMBEDIMG = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif'];
    private const CACHETTL = 86400;
    # The shortest source worth storing, counted in bytes because parsing cost scales with bytes: below it one write costs more than the hits it saves before the entry ages out
    private const CACHEMIN = 2048;
    # The highlight scripts a code block loads, whose versioned addresses a stored rendering carries
    private const HLJS = ['plugins/highlightjs/highlight.min.js', 'plugins/highlightjs/highlight-line-numbers.min.js'];
    # The call that highlights the blocks not yet highlighted; in a pane htmx inserts it runs before the two scripts load, so it waits for the second one
    private const HLJSRUN = '(function(s){var go=function(){document.querySelectorAll(\'pre code:not([data-highlighted])\').forEach(function(c){'
        .'hljs.highlightElement(c);hljs.lineNumbersBlock(c);});};var run=function(){if(document.readyState===\'loading\')'
        .'document.addEventListener(\'DOMContentLoaded\',go);else go();};if(window.hljs&&hljs.lineNumbersBlock)run();'
        .'else s.addEventListener(\'load\',run);})(document.currentScript.previousElementSibling);';
    # The name the head of a code block shows for the language of its fence; a language outside the list shows in capitals
    private const CODENAME = ['bash' => 'Bash', 'cpp' => 'C++', 'csharp' => 'C#', 'css' => 'CSS', 'delphi' => 'Delphi', 'diff' => 'Diff', 'groovy' => 'Groovy',
        'java' => 'Java', 'jscript' => 'JavaScript', 'javascript' => 'JavaScript', 'js' => 'JavaScript', 'typescript' => 'TypeScript', 'php' => 'PHP',
        'python' => 'Python', 'ruby' => 'Ruby', 'scala' => 'Scala', 'sql' => 'SQL', 'vb' => 'VB', 'xml' => 'XML', 'shell' => 'Shell', 'sh' => 'Shell',
        'apache' => 'Apache', 'nginx' => 'Nginx', 'plain' => '', 'plaintext' => '', 'text' => ''];
    # A code block of more lines than this opens folded, with a button to show the rest
    private const CODELONG = 25;
    # A backslash makes the ASCII punctuation after it literal; the pair stays the first alternative of LITERAL and ESCAPE, so group 1 is set for a literal alone
    private const PAIR = '\\\\([!-\/:-@\[-`{-~])';
    private const LITERAL = '/'.self::PAIR.'|\[(usephp|usehtml)\].*?\[\/\2\]|<(script|style)\b.*?<\/\3\s*>|<[a-zA-Z\/!][^>]*>/si';
    # The raw pairs of the bracket layer keep every backslash as typed, so an escape inside them hides no tag from the bracket layer
    private const ESCAPE = '/'.self::PAIR.'|\[(usephp|usehtml)\].*?\[\/\2\]/si';
    # The attachment grammar: file name, alignment and title always, width and height together, a relation only after both, and last the full-size form of an image
    private const ATTACH = '\[attach=([a-zA-Z0-9_\-\. ]+) align=([a-zA-Z]+) title=([\pL0-9_\-\.\"\s]+)';
    private const ATTSIZE = ' width=([0-5]?[0-9]?[0-9]+) height=([0-5]?[0-9]?[0-9]+)';
    private const ATTREL = ' rel=([a-zA-Z0-9_\-]+)';
    private const ATTFULL = ' size=(full)';
    private const ATTTAG = '/'.self::ATTACH.'(?:'.self::ATTSIZE.'(?:'.self::ATTREL.')?)?(?:'.self::ATTFULL.')?\]/siu';
    # The bracket layer reads a copy whose code, link targets and escaped brackets carry these two bytes for [ and *, which no source keeps
    private const UNMASK = ["\x04" => '[', "\x05" => '*'];
    private const PUNCT = '!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~';
    private const SPECIAL = "\n`[]\\!<&*_~=";
    private const ENTITY = '&(?:#[xX][0-9a-fA-F]{1,6}|#[0-9]{1,7}|[A-Za-z][A-Za-z0-9]{1,31});';
    private const OPENTAG = '<[A-Za-z][A-Za-z0-9-]*+(?:\s+[a-zA-Z_:][a-zA-Z0-9:._-]*+(?:\s*=\s*(?:[^"\'=<>`\x00-\x20]++|\'[^\']*+\'|"[^"]*+"))?)*+\s*\/?>';
    private const CLOSETAG = '<\/[A-Za-z][A-Za-z0-9-]*+\s*>';
    private const HTMLTAG = '/(?:'.self::OPENTAG.'|'.self::CLOSETAG.'|<!-->|<!--->|<!--[\s\S]*?-->|<\?[\s\S]*?\?>|<![A-Za-z][^>]*>|<!\[CDATA\[[\s\S]*?\]\]>)/A';
    # The seven start conditions of a CommonMark HTML block, and below them the end conditions of the first five
    private const HTMLOPEN = [
        1 => '/^<(?:pre|script|style|textarea)(?:\s|>|$)/i',
        2 => '/^<!--/',
        3 => '/^<\?/',
        4 => '/^<![A-Za-z]/',
        5 => '/^<!\[CDATA\[/',
        6 => '/^<\/?(?:address|article|aside|base|basefont|blockquote|body|caption|center|col|colgroup|dd|details|dialog|dir|div|dl|dt|fieldset|figcaption|figure'
            .'|footer|form|frame|frameset|h[1-6]|head|header|hr|html|iframe|legend|li|link|main|menu|menuitem|nav|noframes|ol|optgroup|option|p|param|search'
            .'|section|summary|table|tbody|td|tfoot|th|thead|title|tr|track|ul)(?:\s|\/?>|$)/i',
        7 => '/^(?!<\/?(?:pre|script|style|textarea)\b)(?:'.self::OPENTAG.'|'.self::CLOSETAG.')[ \t]*$/i',
    ];
    private const HTMLEND = [1 => '/<\/(?:script|pre|style|textarea)>/i', 2 => '/-->/', 3 => '/\?>/', 4 => '/>/', 5 => '/\]\]>/'];
    private const AUTOLINK = '/(?<![^\s*_~(])www\.|(?<![a-zA-Z])(?i:https?|ftp):\/\//';
    private const TABLEROW = '/^\|?[ \t]*:?-+:?[ \t]*(?:\|[ \t]*:?-+:?[ \t]*)*\|?[ \t]*$/';
    public static bool $freeoff = false;
    # Off the disk check of a local image address, for the conformance test that compares the parse alone
    public static bool $fileoff = false;
    # Off the tag filter and the extended autolinks of GFM, for the conformance test of the CommonMark examples, which the spec runner of cmark-gfm reads without them
    public static bool $gfmoff = false;
    private static array $pcache = [];
    private array $stash = [];
    private string $salt = '';
    private int $scnt = 0;
    private bool $safe = true;
    private bool $trust = false;
    private string $mod = '';
    private array $own = [];
    private int $hoff = 0;
    private string $fmt = '';
    private array $hids = [];
    private bool $vary = false;
    private ?object $doc = null;
    private ?object $tip = null;
    private ?object $prev = null;
    private ?object $last = null;
    private string $line = '';
    private int $lpos = 0;
    private int $lnum = 0;
    private int $off = 0;
    private int $col = 0;
    private int $nsp = 0;
    private int $nscol = 0;
    private int $indent = 0;
    private bool $blank = false;
    private bool $ptab = false;
    private bool $closed = true;
    private array $refs = [];
    private bool $scan = false;
    private array $spans = [];
    private array $ispan = [];
    private string $subj = '';
    private int $pos = 0;
    private ?object $delims = null;
    private ?object $brack = null;

    # Parse src through the pipeline; the format names how the source is read, not who wrote it, and trust makes a safe rendering honour [usehtml] and [usephp] alone
    public function filterDoc(string $src, bool $safe = true, string $mod = '', int $hoff = 0, string $fmt = '', array $own = [], bool $trust = false): string {
        $hoff = max(0, min(5, $hoff));
        $fmt = in_array($fmt, ['plain', 'breaks'], true) ? $fmt : '';
        $own = (is_string($own[0] ?? null) && is_int($own[1] ?? null) && $own[1] >= 0) ? [$own[0], $own[1]] : [];
        $trust = $safe && $trust;
        $key = md5($src.(int)$safe.(int)$trust.$mod.$hoff.$fmt.'|'.implode(':', $own).(int)self::$fileoff.(int)self::$gfmoff);
        if (isset(self::$pcache[$key])) {
            $this->vary = self::$pcache[$key][1];
            return self::$pcache[$key][0];
        }
        $this->vary = false;
        $this->stash = [];
        $this->hids  = [];
        $this->salt  = bin2hex(random_bytes(8));
        $this->scnt  = 0;
        $this->safe  = $safe;
        $this->trust = $trust;
        $this->mod   = $mod;
        $this->own   = $own;
        $this->hoff  = $hoff;
        $this->fmt   = $fmt;
        $src = strtr(str_replace(["\r\n", "\r"], "\n", $src), ["\0" => "\u{FFFD}", "\x04" => "\u{FFFD}", "\x05" => "\u{FFFD}"]);
        if ($fmt === 'plain') {
            $src = $this->filterLiteral($src);
            $src = $this->filterBbBlocks($src);
            $src = $this->filterFreeBlocks($src);
            $out = $this->filterSafe($this->filterPlain($src));
        } else {
            $out = $this->filterMarkdown($src, true);
        }
        $out = $this->filterStash($out);
        $out = trim($out);
        self::$pcache[$key] = [$out, $this->vary];
        return $out;
    }

    # Replace trusted [block=id] tags with rendered free (infly) block output; frontend only, skipped for unsafe content, block-content filtering, nesting and test runs
    private function filterFreeBlocks(string $src): string {
        static $depth = 0;
        if ($this->safe || self::$freeoff || $depth > 0 || defined('ADMIN_FILE') || !str_contains($src, '[block=') || !function_exists('getBlocks')) return $src;
        return preg_replace_callback('#\[block=(\d{1,10})\]#', function (array $m) use (&$depth): string {
            $depth++;
            ob_start();
            getBlocks('d', $m[1]);
            $out = ob_get_clean();
            $depth--;
            $this->vary = true;
            return $this->addStash((string)$out);
        }, $src) ?? $src;
    }

    # The standard pipeline, filterDoc() plus replace rules; the stored rendering lives here, as only this class knows whether a parse may be reused
    public function filterContent(string $src, bool $safe, string $mod, int $hoff = 0, string $fmt = '', array $own = [], bool $trust = false): string {
        $own = (is_string($own[0] ?? null) && is_int($own[1] ?? null) && $own[1] >= 0) ? [$own[0], $own[1]] : [];
        $trust = $safe && $trust;
        $file = $this->getCachePath($src, $safe, $mod, $hoff, $fmt, $own, $trust);
        if ($file !== '' && Cache::isFresh($file, self::CACHETTL)) {
            $out = Cache::getBody($file);
            if ($out !== '') return $out;
        }
        $out = $this->normalizeHtmlImages(
            $this->replaceText($this->filterDoc($src, $safe, $mod, $hoff, $fmt, $own, $trust), $mod)
        );
        if ($file !== '' && $out !== '' && !$this->vary) Cache::setBody($file, $out);
        return $out;
    }

    # Report whether a rendering may be stored at all: the cache has to be switched on and every helper the key is built from has to exist, which it does not in a standalone parse
    private function checkCacheReady(): bool {
        global $conf;
        static $ready = null;
        if ($ready !== null) return $ready;
        return $ready = (!empty($conf['cache']) && class_exists('Cache') && defined('CACHE_DIR') && defined('_LOCALE') && function_exists('getTheme'));
    }

    # Fingerprint every configuration value this class reads, so a changed replace rule, upload setting or file type retires the stored renderings without anyone clearing a cache
    private function getConfigHash(string $mod): string {
        global $conf;
        static $memo = [];
        if (isset($memo[$mod])) return $memo[$mod];
        return $memo[$mod] = sha1(serialize([
            $conf['replace'][$mod] ?? '',
            $conf['uploads'][$mod] ?? '',
            $conf['uploads']['width'] ?? '',
            $conf['uploads']['height'] ?? '',
            $conf['filetype'] ?? [],
            $conf['homeurl'] ?? '',
            $conf['syntax'] ?? '',
            array_map(Template::getAssetUrl(...), self::HLJS),
        ]));
    }

    # The cache path of one rendering, or an empty string when nothing may be stored; the key carries every input the output depends on, the class version included
    private function getCachePath(string $src, bool $safe, string $mod, int $hoff, string $fmt, array $own, bool $trust): string {
        static $ver = '';
        if (strlen($src) < self::CACHEMIN || !$this->checkCacheReady()) return '';
        if ($ver === '') $ver = (string)filemtime(__FILE__);
        return Cache::getFile([
            'parser', $ver, $this->getConfigHash($mod), sha1($src), (int)$safe, (int)$trust, $mod, $hoff, $fmt, implode(':', $own), getTheme(), _LOCALE,
        ], 'html');
    }

    # Apply module regex replace rules from $conf['replace'][$mod]; tags are stashed once with salted tokens, the # delimiter is escaped and invalid patterns are skipped
    private function replaceText(string $src, string $mod): string {
        global $conf;
        $rules = ($mod && isset($conf['replace'][$mod])) ? $conf['replace'][$mod] : '';
        if (!$rules) return $src;
        $tags = [];
        $salt = bin2hex(random_bytes(4));
        $src = preg_replace_callback('#<[^>]*>#', function (array $m) use (&$tags, $salt): string {
            $tok = "\x02R{$salt}:".count($tags)."\x03";
            $tags[$tok] = $m[0];
            return $tok;
        }, $src) ?? $src;
        foreach (explode('||', $rules) as $word) {
            if ($word === '') continue;
            $warray = explode('|', $word);
            if (empty($warray[0])) continue;
            $src = preg_replace('#'.str_replace('#', '\#', $warray[0]).'#i', $warray[1] ?? '', $src) ?? $src;
        }
        return $tags ? strtr($src, $tags) : $src;
    }

    # Stash a protected fragment behind a salted control-char token; a literal token carries an L, so the block scanner reads its line as text
    private function addStash(string $val, bool $lit = false): string {
        $key = $this->salt.':'.($lit ? 'L' : '').$this->scnt;
        $this->stash[$key] = $val;
        $this->scnt++;
        return "\x02{$key}\x03";
    }

    # Turn backslash pairs of the plain format outside raw regions into literal tokens; when the engine gives up on raw regions every pair becomes literal, hiding markup
    private function filterLiteral(string $src): string {
        if (!str_contains($src, '\\')) return $src;
        $put = fn(array $m): string => ($m[1] ?? '') !== '' ? $this->addStash($this->filterEsc($m[1]), true) : $m[0];
        return preg_replace_callback(self::LITERAL, $put, $src) ?? preg_replace_callback('/'.self::PAIR.'/', $put, $src) ?? $src;
    }

    # The unique attachment names of a source, read the way each rendering reads it; a failed match lists more rather than less, which costs only a check
    public function getAttachList(string $src): array {
        if (stripos($src, '[attach=') === false) return [];
        $src = strtr(str_replace(["\r\n", "\r"], "\n", $src), ["\0" => "\u{FFFD}", "\x04" => "\u{FFFD}", "\x05" => "\u{FFFD}"]);
        $out = [];
        $this->trust = false;
        foreach ([['plain', true], ['', false], ['', true]] as [$fmt, $safe]) {
            $this->salt = bin2hex(random_bytes(8));
            $this->stash = [];
            $this->scnt = 0;
            $this->safe = $safe;
            $this->fmt = $fmt;
            if ($fmt !== 'plain') $txt = $this->filterMask($src);
            elseif (str_contains($src, '\\')) $txt = preg_replace_callback(self::LITERAL, fn(array $m): string => ($m[1] ?? '') !== '' ? "\x02" : $m[0], $src) ?? $src;
            else $txt = $src;
            if (preg_match_all(self::ATTTAG, $txt, $mm)) $out = array_merge($out, $mm[1]);
        }
        $out = array_values(array_unique($out));
        usort($out, fn(string $a, string $b): int => stripos($src, '[attach='.$a) <=> stripos($src, '[attach='.$b));
        return $out;
    }

    # Restore all stash tokens iteratively to handle nested fragments, then the masked brackets a stashed fragment carried
    private function filterStash(string $src): string {
        $map = [];
        foreach ($this->stash as $k => $v) {
            $map["\x02{$k}\x03"] = $v;
        }
        $prev = null;
        while ($prev !== $src) {
            $prev = $src;
            $src  = strtr($src, $map);
        }
        return strtr($src, self::UNMASK);
    }

    # Full re-parse of nested block content for [hide] and [tab]; reuses the live stash and salt and leaves filterStash()/trim() to the top level
    private function filterNest(string $src): string {
        return $this->filterMarkdown(strtr($src, self::UNMASK), false);
    }

    # Read a Markdown source: the SLAED tags are read on a copy whose code, link targets and escaped brackets are masked, then CommonMark with GFM renders the result
    private function filterMarkdown(string $src, bool $top): string {
        if (str_contains($src, '[')) {
            $src = $this->filterBbBlocks($this->filterMask($src));
            if ($top) $src = $this->filterFreeBlocks($src);
            $src = strtr($src, self::UNMASK);
        }
        return $this->getBlockHtml($this->getBlockTree($src));
    }

    # Mask what the bracket layer must not read: code, targets, autolinks and raw tags a scan finds, an escaped bracket or star; a raw pair whose opener shows is no Markdown
    private function filterMask(string $src): string {
        $this->scan = true;
        $this->spans = [];
        $this->setLeafMask($this->getBlockTree($src));
        $this->scan = false;
        usort($this->spans, fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $out = '';
        $at = 0;
        foreach ($this->spans as [$beg, $end]) {
            if ($end <= $at) continue;
            $beg = max($beg, $at);
            $out .= substr($src, $at, $beg - $at).strtr(substr($src, $beg, $end - $beg), '[*', "\x04\x05");
            $at = $end;
        }
        $out .= substr($src, $at);
        for ($at = 0; preg_match('/\[(usephp|usehtml)\]/i', $out, $m, PREG_OFFSET_CAPTURE, $at); $at = $end) {
            $beg = $m[0][1];
            $end = stripos($src, '[/'.$m[1][0].']', $beg + strlen($m[0][0]));
            if ($end === false) break;
            $end += strlen($m[1][0]) + 3;
            $out = substr_replace($out, substr($src, $beg, $end - $beg), $beg, $end - $beg);
        }
        $put = fn(array $m): string => in_array($m[1] ?? '', ['[', '*'], true) ? '\\'.strtr($m[1], '[*', "\x04\x05") : $m[0];
        return preg_replace_callback(self::ESCAPE, $put, $out) ?? preg_replace_callback('/'.self::PAIR.'/', $put, $out) ?? $out;
    }

    # Walk the scanned tree: the inline leaves are parsed for their code spans, link targets, autolinks and raw tags, while code blocks were masked line by line already
    private function setLeafMask(object $node): void {
        if ($node->type === 'paragraph' || $node->type === 'heading') {
            $lead = strlen($node->text) - strlen(ltrim($node->text, " \t\n"));
            $this->addMaskSpans(trim($node->text, " \t\n"), $node->map, $node->base + $lead);
        } elseif ($node->type === 'table') {
            foreach (array_merge([$node->head], $node->rows) as $row) foreach ($row as [$txt, $map]) $this->addMaskSpans($txt, $map, 0);
        }
        foreach ($node->kids as $kid) $this->setLeafMask($kid);
    }

    # Parse one inline leaf as a scan and queue every range it reports for masking
    private function addMaskSpans(string $src, array $map, int $base): void {
        $this->ispan = [];
        $this->getInlineTree($src);
        foreach ($this->ispan as [$beg, $end]) $this->addMaskRange($map, $base + $beg, $base + $end);
    }

    # Map one range of a leaf's text back to the source through the line map of the leaf and queue it for masking
    private function addMaskRange(array $map, int $beg, int $end): void {
        $cnt = count($map);
        for ($k = 0; $k < $cnt; $k++) {
            $from = $map[$k][0];
            $lo = max($beg, $from);
            $hi = min($end, $map[$k + 1][0] ?? PHP_INT_MAX);
            if ($lo < $hi) $this->spans[] = [$map[$k][1] + $lo - $from, $map[$k][1] + $hi - $from];
        }
    }

    # Escape HTML special characters
    private function filterEsc(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    # Decode HTML entities
    private function filterDec(string $s): string {
        return getDecodedText($s);
    }

    # The file behind one site-relative address: an address under uploads/ lies below UPLOADS_DIR, the one upload root, and any other below the document root
    private function getAddressFile(string $path): string {
        $root = dirname(__DIR__, 2);
        if (str_starts_with($path, 'uploads/')) return (defined('UPLOADS_DIR') ? UPLOADS_DIR : $root.'/uploads').substr($path, 7);
        return (defined('PUBLIC_DIR') ? PUBLIC_DIR : $root.'/public').'/'.$path;
    }

    # Render parser images through the theme fragment so markup stays out of PHP
    private function getParserImage(?string $src, string $alt, string $title = ''): string {
        global $tpl;
        if (!isset($tpl) || !is_object($tpl) || !method_exists($tpl, 'getHtmlFrag')) return $this->filterEsc($alt);
        return (string) $tpl->getHtmlFrag('parser-image', [
            'src' => $src ?? '',
            'alt' => $alt,
            'title' => $title,
            'missing' => $src === null,
            'fallback' => $src !== null,
        ]);
    }

    # Render the blockquote fragment through the theme template; a quote, a spoiler and a callout are one element with three faces and the theme owns all three
    private function getQuoteHtml(array $data): string {
        return $this->getPartHtml('blockquote', $data);
    }

    # Render one parser element through the theme fragment; a template composes its own line endings and the parser composes elements, so what comes back is trimmed
    private function getPartHtml(string $name, array $data, bool $iswrap = false): string {
        global $tpl;
        if (!isset($tpl) || !is_object($tpl) || !method_exists($tpl, 'getHtmlFrag')) return '';
        return trim((string)($iswrap ? $tpl->getHtmlPart($name, $data) : $tpl->getHtmlFrag($name, $data)));
    }

    # One element with a placeholder for content, memoized; the engine instance is part of the key, since two themes can spell one element differently
    private function getPartTag(array $data, string $name = 'parser-inline'): string {
        global $tpl;
        static $memo = [];
        $key = (is_object($tpl) ? spl_object_id($tpl) : 0).'|'.$name.'|'.serialize($data);
        return $memo[$key] ??= $this->getPartHtml($name, $data);
    }

    # The two halves of one element, split where the template rendered its content, so the theme keeps a whole element while the parser wraps unread text
    private function getPartPair(array $data, string $name = 'parser-inline'): array {
        $data['content_html'] = "\x01";
        $part = explode("\x01", $this->getPartTag($data, $name), 2);
        return [$part[0], $part[1] ?? ''];
    }

    # One anchor through the theme fragment; the address, the title and a plain label arrive unescaped, because escaping them is what the template is for
    private function getPartLink(string $href, string $lbl, string $ttl = '', bool $blank = false, bool $raw = false): string {
        $data = ['href' => $href, 'title' => $ttl, 'is_blank' => $blank];
        $data[$raw ? 'label_html' : 'label'] = $lbl;
        return $this->getPartHtml('parser-link', $data);
    }

    # Memoized image resolution; it reads the filesystem, so its rendering is marked as one no stored copy may answer for
    private function normalizeImageSource(string $src): ?string {
        static $memo = [];
        $this->vary = true;
        $key = md5($src.(int)self::$fileoff);
        if (array_key_exists($key, $memo)) return $memo[$key];
        return $memo[$key] = $this->checkImageSource($src);
    }

    # A local image source as an address from the site root; data URIs pass only as EMBEDIMG rasters, the one list the editor and the upload adapter read too
    private function checkImageSource(string $src): ?string {
        global $conf;
        $raw = trim($this->filterDec($src));
        if ($raw === '' || str_starts_with($raw, '#')) return $raw;
        if (stripos($raw, 'data:') === 0) {
            if (strlen($raw) > intdiv(self::EMBEDMAX + 2, 3) * 4 + 32) return null;
            if (!preg_match('#^data:image/([a-z0-9.+\-]+);base64,([A-Za-z0-9+/]+={0,2})$#i', $raw, $dm)) return null;
            if (!in_array(strtolower($dm[1]), self::EMBEDIMG, true)) return null;
            $bin = base64_decode($dm[2], true);
            return ($bin !== false && strlen($bin) <= self::EMBEDMAX) ? $raw : null;
        }
        if (self::$fileoff) return $raw;
        $host = parse_url($raw, PHP_URL_HOST);
        $path = parse_url($raw, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : $raw;
        $home = defined('HOME_PATH') ? HOME_PATH : '/';
        $path = str_starts_with($path, $home) ? substr($path, strlen($home)) : ltrim($path, '/');

        if ($host) {
            $homeHost = parse_url((string)($conf['homeurl'] ?? ''), PHP_URL_HOST);
            if ($homeHost && strcasecmp((string)$host, (string)$homeHost) !== 0) return $raw;
        } elseif (str_starts_with($raw, '//')) {
            return $raw;
        }

        if ($path === '' || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $path)) return $raw;

        $query = parse_url($raw, PHP_URL_QUERY);
        if (is_file($this->getAddressFile($path))) return $home.(is_string($query) ? $path.'?'.$query : $path);

        if (preg_match('#^(uploads/[^/]+)/([^/]+)$#', $path, $m)) {
            $thumb = $m[1].'/thumb/'.$m[2];
            if (is_file($this->getAddressFile($thumb))) return $home.$thumb;
        }

        return null;
    }

    # Repair persisted raw HTML img tags so broken local sources do not emit frontend 404 placeholders
    private function normalizeHtmlImages(string $src): string {
        return preg_replace_callback(
            '#<img\b[^>]*>#i',
            function(array $m): string {
                $tag = $m[0];
                if (!preg_match('#\bsrc\s*=\s*(["\'])(.*?)\1#i', $tag, $sm)) return $tag;
                $file = stripos($sm[2], 'data:') === 0 ? 'image' : (basename(rawurldecode((string)(parse_url($sm[2], PHP_URL_PATH) ?: $sm[2]))) ?: 'image');
                $resolved = $this->normalizeImageSource($sm[2]);
                if ($resolved === null) return $this->getParserImage(null, $file, $file);
                $resolved = $this->filterEsc($resolved);
                $tag = preg_replace('#\bsrc\s*=\s*(["\']).*?\1#i', 'src="'.$resolved.'"', $tag, 1) ?? $tag;
                return $tag;
            },
            $src
        ) ?? $src;
    }

    # Escape non-stash text of the plain format before its autolinks by splitting on stash tokens and escaping only the literal parts
    private function filterText(string $s): string {
        $pat   = '/(\x02'.preg_quote($this->salt, '/').':\d+\x03)/';
        $parts = preg_split($pat, $s, -1, PREG_SPLIT_DELIM_CAPTURE) ?? [$s];
        return implode('', array_map(fn($p) => preg_match($pat, $p) ? $p : $this->filterEsc($p), $parts));
    }

    # Validate a link URL: data:, javascript: and vbscript: never pass, a safe rendering passes a scheme as http, https or mailto alone, a stored ./ address is from the site root
    private function filterUrl(string $url): string {
        $url = trim($url);
        $bare = preg_replace('#[\s\x00-\x1f]+#', '', $url) ?? $url;
        if (preg_match('#^(?:data|javascript|vbscript):#i', $bare)) return '#';
        if (str_starts_with($url, './')) return (defined('HOME_PATH') ? HOME_PATH : '/').substr($url, 2);
        if (!$this->safe) return $url;
        return (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url) && !preg_match('#^(?:https?|mailto):#i', $url)) ? '#' : $url;
    }

    # Generate a unique heading id: unicode letters and digits are kept (cyrillic included), the rest collapses to hyphens, duplicates get a numeric suffix
    private function getHeadingId(string $raw, int $lvl): string {
        $txt = preg_replace('/\x02'.preg_quote($this->salt, '/').':L?\d+\x03/', '', $raw);
        $id = preg_replace('/[^\p{L}\p{N}]+/u', '-', strip_tags($txt)) ?? '';
        $id = mb_strtolower(trim($id, '-'), 'UTF-8');
        if ($id === '') $id = 'h'.$lvl;
        $base = $id;
        if (isset($this->hids[$base])) $id = $base.'-'.(++$this->hids[$base]);
        else $this->hids[$base] = 0;
        return $id;
    }

    # Render one code block by the configured highlighter; the code is literal, and mode 2 escapes it itself
    private function getCodeHtml(string $code, string $lang): string {
        global $conf;
        static $sname = '';
        $mode = intval($conf['syntax'] ?? 0);
        $from = ['bash', 'cpp', 'csharp', 'css', 'delphi', 'diff', 'groovy', 'java', 'jscript', 'php', 'plain', 'python', 'ruby', 'scala', 'sql', 'vb', 'xml'];
        $to = ['Bash', 'Cpp', 'CSharp', 'Css', 'Delphi', 'Diff', 'Groovy', 'Java', 'JScript', 'Php', 'Plain', 'Python', 'Ruby', 'Scala', 'Sql', 'Vb', 'Xml'];
        $cname = str_ireplace($from, $to, preg_match('#[^a-zA-Z0-9_\-]#', $lang) ? '' : $lang);
        if ($mode === 0) {
            $html = preg_match("#<\?(php)?[^[:graph:]]#", $code) ? highlight_string($code, true)
                : preg_replace('#&lt;\?php(?:&nbsp;| )#', '', highlight_string('<?php '.$code, true), 1);
            $format = str_replace('&nbsp;&nbsp;', '&nbsp; ', (string)$html);
        } elseif ($mode === 1) {
            $rows = '';
            foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $code)) as $i => $line) {
                $html = preg_match("#<\?(php)?[^[:graph:]]#", $line) ? highlight_string($line, true)
                    : preg_replace('#&lt;\?php(?:&nbsp;| )#', '', highlight_string('<?php '.$line, true), 1);
                $rows .= $this->getPartHtml('code-row', ['is_odd' => $i % 2 === 0, 'row_num' => $i + 1, 'code_html' => (string)$html]);
            }
            $format = $this->getPartHtml('table', ['is_form' => true, 'rows_html' => str_replace('&nbsp;&nbsp;', '&nbsp; ', $rows)]);
        } else {
            $scripts = '';
            if ($sname !== 'hljs') {
                foreach (self::HLJS as $one) $scripts .= $this->getPartHtml('head-script-src', ['src' => Template::getAssetUrl($one), 'attr' => '']);
                $scripts .= $this->getPartHtml('head-script-inline', ['js' => self::HLJSRUN]);
                $sname = 'hljs';
            }
            $hlang = ['jscript' => 'javascript', 'vb' => 'vbnet', 'plain' => 'plaintext'][strtolower($cname)] ?? strtolower($cname);
            $format = $this->getPartHtml('code-highlight', ['scripts_html' => $scripts, 'lang' => $hlang,
                'code_html' => htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')]);
        }
        $name = self::CODENAME[strtolower($lang)] ?? strtoupper($lang);
        return $this->getPartHtml('code-block', [
            'lang_text' => ($name !== '') ? $name : _CODE,
            'label_text' => trim(_CODE.' '.$name),
            'copy_text' => _COPY,
            'done_text' => _COPYDONE,
            'is_long' => substr_count($code, "\n") >= self::CODELONG,
            'more_text' => _SHOWALL,
            'less_text' => _COLLAPSE,
            'content_html' => $format,
        ]);
    }

    # Process the SLAED tags; the right to author the trusted tags is settled by filterTrustedTags() at every write, never here, and a tag not honoured keeps its backslashes
    private function filterBbBlocks(string $src): string {
        if (!str_contains($src, '[')) return $src;
        $keep = fn(string $txt): string => preg_replace_callback('/\\\\(?=[!-\/:-@\[-`{-~])/', fn(array $m): string => $this->addStash('\\', true), $txt) ?? $txt;
        $src = preg_replace_callback(
            '/\[usehtml\](.*?)\[\/usehtml\]/si',
            function(array $m) use ($keep): string {
                if ($this->safe && !$this->trust) return $keep($m[0]);
                $html = htmlspecialchars_decode(replace_break(strtr($m[1], self::UNMASK)), ENT_QUOTES);
                return $this->addStash($html);
            },
            $src
        ) ?? $src;
        $src = preg_replace_callback(
            '/\[usephp\](.*?)\[\/usephp\]/si',
            function(array $m) use ($keep): string {
                if ($this->safe && !$this->trust) return $keep($m[0]);
                $rep = str_replace(['&#036;', '&#092;'], ['$', '\\'], strtr($m[1], self::UNMASK));
                ob_start();
                try {
                    eval(htmlspecialchars_decode(replace_break($rep), ENT_QUOTES));
                    $out = ob_get_clean();
                } catch (Throwable) {
                    ob_end_clean();
                    $out = '';
                }
                $this->vary = true;
                return $this->addStash((string)$out);
            },
            $src
        ) ?? $src;
        $src = preg_replace_callback(
            '/\[tabs=(.*?)\](.*?)\[\/tabs\]/si',
            function(array $m): string {
                $num = (int)trim($m[1]);
                $rep = (string)$m[2];
                $cnt = preg_match_all('/\[tab=([\pL0-9_\-\.\"\s]+)\](.*?)\[\/tab\]/siu', $rep, $mm);
                if (!$cnt) return $m[0];
                $ttl = [];
                $txt = [];
                for ($i = 0; $i < $cnt; $i++) {
                    $ttl[] = $mm[1][$i];
                    $txt[] = $this->filterNest($mm[2][$i]);
                }
                return $this->addStash((string)getNaviTabs($num, 'tab', $ttl, $txt));
            },
            $src
        ) ?? $src;
        while (preg_match('/\[hide\](.*?)\[\/hide\]/si', $src)) {
            $src = preg_replace_callback(
                '/\[hide\](.*?)\[\/hide\]/si',
                function(array $m): string {
                    $show = (defined('ADMIN_FILE') || is_user());
                    $this->vary = true;
                    $txt = $show ? $this->filterNest($m[1]) : sprintf(_HIDETEXT, getSeoUrl(['name' => 'account']));
                    return $this->addStash($this->getQuoteHtml(['is_hide' => true, 'content_html' => $txt, 'title_text' => _HIDE]));
                },
                $src
            ) ?? $src;
        }
        if (stripos($src, '[attach=') !== false) $src = $this->filterAttach($src);
        return $src;
    }

    # Render every [attach] form from config/filetype.php at the address FileAccess answers for the file context; the full-size form of an image takes the template full
    private function filterAttach(string $src): string {
        global $conf;
        $mod = $this->mod !== '' ? $this->mod : 'all';
        if (!preg_match_all(self::ATTTAG, $src, $mm, PREG_SET_ORDER)) return $src;
        $this->vary = true;
        static $fex = [];
        static $isz = [];
        $twd = getUploadRuleData($mod)['thumbwidth'] ?: ($conf['uploads']['width'] ?? '250');
        [$own, $tid] = $this->own ?: ['public', 0];
        $fac = getFileService();
        $room = $fac->getFileFolder($own, $mod, $tid);
        $img = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif'];
        foreach ($mm as $m) {
            $fn  = (string)$m[1];
            $al  = (string)$m[2];
            $tl  = (string)$m[3];
            $wd  = $m[4] ?? '';
            $hg  = $m[5] ?? '';
            $rl  = $m[6] ?? '';
            $ext = strtolower((string)substr((string)strrchr($fn, '.'), 1));
            $pic = in_array($ext, $img, true);
            $full = $pic && ($m[7] ?? '') !== '';
            $path = UPLOADS_DIR.'/'.$room.'/'.$fn;
            $link = $fac->getFileUrl($own, $mod, $tid, $fn);
            $href = str_replace('&', '&amp;', $link);
            $timg = $href;
            if ($tl === '' || strtolower($tl) === 'title') $tl = $fn;
            if ($pic) {
                $tpath = UPLOADS_DIR.'/'.$room.'/thumb/'.$fn;
                $tdir  = UPLOADS_DIR.'/'.$room.'/thumb';
                if (!$full && $room !== '' && ($fex[$path] ??= file_exists($path)) && !($fex[$tpath] ??= file_exists($tpath))) {
                    if (!file_exists($tdir)) mkdir($tdir, 0777, true);
                    $tmp = $tpath.'.'.getmypid().'.tmp';
                    if (getImageThumb($path, $tmp, $twd) === $tmp && is_file($tmp) && rename($tmp, $tpath)) $fex[$tpath] = true;
                    elseif (is_file($tmp)) unlink($tmp);
                }
                if ($fex[$tpath] ?? false) $timg = str_replace('&', '&amp;', $fac->getFileUrl($own, $mod, $tid, $fn, true));
                if ($fex[$path] ??= file_exists($path)) {
                    $isz[$path] ??= getimagesize($path);
                    [$wd, $hg] = $isz[$path];
                } else {
                    $src = str_replace($m[0], $this->addStash($this->getParserImage(null, $tl, $tl)), $src);
                    continue;
                }
            }
            $tmp = $full ? (string)($conf['filetype']['full'] ?? '') : '';
            if ($tmp === '') $tmp = (string)($conf['filetype'][$ext] ?? '');
            if ($tmp === '') {
                $src = str_replace($m[0], $this->addStash($this->getPartLink($link, $tl, $tl, true)), $src);
                continue;
            }
            $tmp = str_replace('[src]',    $href, $tmp);
            $tmp = str_replace('[tsrc]',   (string)$timg, $tmp);
            $tmp = (!empty($wd) && (int)$wd)
                 ? str_replace('[width]',  (string)$wd, $tmp)
                 : str_replace('[width]',  (string)($conf['uploads']['width'] ?? '500'), $tmp);
            $tmp = str_replace('[twidth]', (string)$twd, $tmp);
            $tmp = (!empty($hg) && (int)$hg)
                 ? str_replace('[height]', (string)$hg, $tmp)
                 : str_replace('[height]', (string)($conf['uploads']['height'] ?? '500'), $tmp);
            $tmp = str_replace('[align]',  $al, $tmp);
            $tmp = str_replace('[title]',  $tl, $tmp);
            $tmp = str_replace('[quot]',   '&quot;', $tmp);
            $tmp = str_replace('[rel]',    $rl !== '' ? $rl : 'alternate', $tmp);
            $src = str_replace($m[0], $this->addStash($tmp), $src);
        }
        return $src;
    }

    # Read the block structure line by line as the reference implementation of CommonMark does, with the table of GFM and a line of one stashed fragment as leaves
    private function getBlockTree(string $src): object {
        $this->doc = $this->getBlockNode('document');
        $this->tip = $this->doc;
        $this->refs = [];
        $this->lnum = 0;
        $this->lpos = 0;
        $lines = explode("\n", $src);
        if (end($lines) === '') array_pop($lines);
        foreach ($lines as $line) {
            $this->addBlockLine($line);
            $this->lpos += strlen($line) + 1;
        }
        while ($this->tip !== null) $this->setBlockFinal($this->tip);
        return $this->doc;
    }

    # A new block node; the fields a type needs beyond these are added by the start that opens it
    private function getBlockNode(string $type): object {
        return (object)['type' => $type, 'kids' => [], 'parent' => null, 'open' => true, 'text' => '', 'map' => [], 'base' => 0, 'lblank' => false, 'refd' => false,
            'line' => $this->lnum];
    }

    # Add one source line to the open blocks: match the open containers, open new blocks, then hand the rest of the line to a leaf, a lazy paragraph or a new paragraph
    private function addBlockLine(string $line): void {
        $this->line = $line;
        $this->off = 0;
        $this->col = 0;
        $this->blank = false;
        $this->ptab = false;
        $this->lnum++;
        $this->prev = $this->tip;
        $node = $this->doc;
        while ($node->kids && ($kid = $node->kids[count($node->kids) - 1])->open) {
            $node = $kid;
            $this->setNextNonspace();
            $res = $this->checkBlockMatch($node);
            if ($res === 2) return;
            if ($res === 1) {
                $node = $node->parent;
                break;
            }
        }
        $this->closed = $node === $this->prev;
        $this->last = $node;
        $leaf = $node->type === 'code' || $node->type === 'html';
        while (!$leaf) {
            $this->setNextNonspace();
            if ($this->indent < 4 && !preg_match('/^[#`~*+_=<>0-9\-|:\x02]/', substr($line, $this->nsp, 1))) {
                $this->setNonspaceOffset();
                break;
            }
            $res = $this->checkBlockStart($node);
            if ($res === 0) {
                $this->setNonspaceOffset();
                break;
            }
            $node = $this->tip;
            $leaf = $res === 2;
        }
        if ($this->blank && $node->kids) $node->kids[count($node->kids) - 1]->lblank = true;
        $type = $node->type;
        $node->lblank = $this->blank && !in_array($type, ['blockquote', 'heading', 'rule'], true) && !($type === 'code' && $node->fchar !== '')
            && !($type === 'item' && !$node->kids && $node->line === $this->lnum);
        for ($up = $node->parent; $up !== null; $up = $up->parent) $up->lblank = false;
        if (!$this->closed && !$this->blank && $this->tip->type === 'paragraph') {
            $this->addLeafLine();
            return;
        }
        $this->setUnmatchedClosed();
        if ($type === 'code' || $type === 'html' || $type === 'paragraph') {
            $this->addLeafLine();
            if ($type === 'html' && $node->hbtype <= 5 && preg_match(self::HTMLEND[$node->hbtype], substr($line, $this->off))) $this->setBlockFinal($node);
        } elseif ($type === 'table') {
            if ($this->off < strlen($line)) $node->rows[] = $this->getTableCells(substr($line, $this->off), $this->lpos + $this->off);
        } elseif ($this->off < strlen($line) && !$this->blank) {
            $this->addBlockChild('paragraph');
            $this->setNonspaceOffset();
            $this->addLeafLine();
        }
    }

    # Whether an open block goes on with this line: 0 it does, 1 it does not, 2 the line closed it; a matched container prefix is consumed
    private function checkBlockMatch(object $node): int {
        $line = $this->line;
        $chr = $line[$this->nsp] ?? '';
        switch ($node->type) {
            case 'blockquote':
                if ($this->indent >= 4 || $chr !== '>') return 1;
                $this->setNonspaceOffset();
                $this->setLineOffset(1, false);
                if (in_array($line[$this->off] ?? '', [' ', "\t"], true)) $this->setLineOffset(1, true);
                return 0;
            case 'item':
                if ($this->blank) {
                    if (!$node->kids) return 1;
                    $this->setNonspaceOffset();
                    return 0;
                }
                if ($this->indent < $node->moff + $node->mpad) return 1;
                $this->setLineOffset($node->moff + $node->mpad, true);
                return 0;
            case 'code':
                if ($node->fchar === '') {
                    if ($this->indent >= 4) $this->setLineOffset(4, true);
                    elseif ($this->blank) $this->setNonspaceOffset();
                    else return 1;
                    return 0;
                }
                if ($this->indent < 4 && $chr === $node->fchar && preg_match('/^(?:`{3,}|~{3,})(?=[ \t]*$)/', substr($line, $this->nsp), $m) && strlen($m[0]) >= $node->flen) {
                    $this->setBlockFinal($node);
                    return 2;
                }
                for ($i = $node->foff; $i > 0 && in_array($line[$this->off] ?? '', [' ', "\t"], true); $i--) $this->setLineOffset(1, true);
                return 0;
            case 'html':
                return ($this->blank && $node->hbtype >= 6) ? 1 : 0;
            case 'paragraph':
            case 'table':
                return $this->blank ? 1 : 0;
            case 'heading':
            case 'rule':
            case 'stash':
                return 1;
        }
        return 0;
    }

    # Try the block starts on the rest of the line in the order of the reference implementation; 0 is none, 1 a container that may hold more starts, 2 a leaf
    private function checkBlockStart(object $node): int {
        $line = $this->line;
        $rest = substr($line, $this->nsp);
        $chr = $rest[0] ?? '';
        $ind = $this->indent >= 4;
        $lazy = !$this->closed && !$this->blank && $this->tip->type === 'paragraph';
        if (!$ind && $chr === '>') {
            $this->setNonspaceOffset();
            $this->setLineOffset(1, false);
            if (in_array($line[$this->off] ?? '', [' ', "\t"], true)) $this->setLineOffset(1, true);
            $this->setUnmatchedClosed();
            $this->addBlockChild('blockquote');
            return 1;
        }
        if (!$ind && $chr === '#' && preg_match('/^#{1,6}(?:[ \t]+|$)/', $rest, $m)) {
            $this->setNonspaceOffset();
            $this->setLineOffset(strlen($m[0]), false);
            $this->setUnmatchedClosed();
            $head = $this->addBlockChild('heading');
            $head->level = strlen(trim($m[0]));
            $head->text = preg_replace(['/^[ \t]*#+[ \t]*$/', '/[ \t]+#+[ \t]*$/'], '', substr($line, $this->off)) ?? '';
            $head->map = [[0, $this->lpos + $this->off]];
            $this->setLineOffset(strlen($line) - $this->off, false);
            return 2;
        }
        if (!$ind && ($chr === '`' || $chr === '~') && preg_match('/^(?:`{3,}+(?!.*`)|~{3,}+)/', $rest, $m)) {
            $this->setUnmatchedClosed();
            $code = $this->addBlockChild('code');
            $code->fchar = $chr;
            $code->flen = strlen($m[0]);
            $code->foff = $this->indent;
            $this->setNonspaceOffset();
            $this->setLineOffset($code->flen, false);
            return 2;
        }
        if (!$ind && $chr === '<' && !$this->safe) {
            foreach (self::HTMLOPEN as $kind => $pat) {
                if (!preg_match($pat, $rest) || ($kind === 7 && ($node->type === 'paragraph' || $lazy))) continue;
                $this->setUnmatchedClosed();
                $this->addBlockChild('html')->hbtype = $kind;
                return 2;
            }
        }
        if (!$ind && $chr === "\x02" && $node->type !== 'paragraph' && !$lazy && preg_match('/^\x02'.preg_quote($this->salt, '/').':\d+\x03[ \t]*$/', $rest)) {
            $this->setUnmatchedClosed();
            $this->addBlockChild('stash')->text = rtrim($rest, " \t");
            $this->setLineOffset(strlen($line) - $this->off, false);
            return 2;
        }
        if (!$ind && $node->type === 'paragraph' && preg_match(self::TABLEROW, $rest) && $this->checkTableStart($node, $rest)) return 2;
        if (!$ind && $node->type === 'paragraph' && preg_match('/^(?:=+|-+)[ \t]*$/', $rest)) {
            $this->setUnmatchedClosed();
            $this->setParaRefs($node);
            if ($node->text === '') return 0;
            $node->type = 'heading';
            $node->level = $chr === '=' ? 1 : 2;
            $this->setLineOffset(strlen($line) - $this->off, false);
            return 2;
        }
        if (!$ind && preg_match('/^(?:(?:\*[ \t]*){3,}|(?:_[ \t]*){3,}|(?:-[ \t]*){3,})$/', $rest)) {
            $this->setUnmatchedClosed();
            $this->addBlockChild('rule');
            $this->setLineOffset(strlen($line) - $this->off, false);
            return 2;
        }
        if ((!$ind || $node->type === 'list') && ($data = $this->getListMarker($node)) !== null) {
            $this->setUnmatchedClosed();
            $tip = $this->tip;
            if ($tip->type !== 'list' || $tip->ltype !== $data['ltype'] || $tip->delim !== $data['delim'] || $tip->bullet !== $data['bullet']) {
                $list = $this->addBlockChild('list');
                foreach ($data as $key => $val) $list->$key = $val;
            }
            $item = $this->addBlockChild('item');
            foreach ($data as $key => $val) $item->$key = $val;
            return 1;
        }
        if ($ind && $this->tip->type !== 'paragraph' && !$this->blank) {
            $this->setLineOffset(4, true);
            $this->setUnmatchedClosed();
            $this->addBlockChild('code')->fchar = '';
            return 2;
        }
        return 0;
    }

    # A GFM table opens when the last line of a paragraph is a header row with as many cells as the delimiter row under it; the lines above stay a paragraph
    private function checkTableStart(object $node, string $rest): bool {
        $text = substr($node->text, 0, -1);
        $cut = strrpos($text, "\n");
        $head = $cut === false ? $text : substr($text, $cut + 1);
        if ($head === '' || (!str_contains($rest, '|') && !str_contains($head, '|'))) return false;
        $seps = $this->getTableCells($rest, 0);
        $cells = $this->getTableCells($head, $node->map[count($node->map) - 1][1]);
        if (count($cells) !== count($seps)) return false;
        $this->setUnmatchedClosed();
        if ($cut === false) {
            $this->tip = $node->parent;
            $this->deleteBlockNode($node);
        } else {
            $node->text = substr($text, 0, $cut + 1);
            array_pop($node->map);
            $this->setBlockFinal($node);
        }
        $table = $this->addBlockChild('table');
        $table->head = $cells;
        $table->rows = [];
        $table->aligns = array_map(fn(array $cell): string => match (true) {
            str_starts_with($cell[0], ':') && str_ends_with($cell[0], ':') => 'center',
            str_ends_with($cell[0], ':') => 'right',
            str_starts_with($cell[0], ':') => 'left',
            default => '',
        }, $seps);
        $this->setLineOffset(strlen($this->line) - $this->off, false);
        return true;
    }

    # The cells of one table row as text with a map to the source: a pipe splits unless escaped, the outer pipes open and close the row, \| is a pipe of the cell
    private function getTableCells(string $row, int $spos): array {
        $len = strlen($row);
        $at = strspn($row, " \t");
        if (($row[$at] ?? '') === '|') $at++;
        $cells = [];
        while (true) {
            $at += strspn($row, " \t", $at);
            $txt = '';
            $map = [[0, $spos + $at]];
            while ($at < $len && $row[$at] !== '|') {
                $run = strcspn($row, '\\|', $at);
                $txt .= substr($row, $at, $run);
                $at += $run;
                if ($at >= $len || $row[$at] === '|') break;
                if (($row[$at + 1] ?? '') === '|') {
                    $txt .= '|';
                    $at += 2;
                    $map[] = [strlen($txt), $spos + $at];
                } else {
                    $txt .= substr($row, $at, 2);
                    $at += 2;
                }
            }
            $cells[] = [rtrim($txt, " \t"), $map];
            if ($at >= $len || trim(substr($row, $at + 1), " \t") === '') break;
            $at++;
        }
        return $cells;
    }

    # The marker of a list item at the next nonspace, with its padding, or null; an item that interrupts a paragraph must not be empty and an ordered one must start at 1
    private function getListMarker(object $node): ?array {
        $line = $this->line;
        $rest = substr($line, $this->nsp);
        if ($this->indent >= 4) return null;
        if (preg_match('/^[*+\-]/', $rest, $m)) $data = ['ltype' => 'bullet', 'bullet' => $m[0], 'delim' => '', 'start' => 0];
        elseif (preg_match('/^(\d{1,9})([.)])/', $rest, $m) && ($node->type !== 'paragraph' || (int)$m[1] === 1)) {
            $data = ['ltype' => 'ordered', 'bullet' => '', 'delim' => $m[2], 'start' => (int)$m[1]];
        }
        else return null;
        $len = strlen($m[0]);
        $next = $line[$this->nsp + $len] ?? '';
        if ($next !== '' && $next !== ' ' && $next !== "\t") return null;
        if ($node->type === 'paragraph' && trim(substr($line, $this->nsp + $len), " \t") === '') return null;
        $data['moff'] = $this->indent;
        $this->setNonspaceOffset();
        $this->setLineOffset($len, true);
        $scol = $this->col;
        $soff = $this->off;
        do {
            $this->setLineOffset(1, true);
            $next = $line[$this->off] ?? '';
        } while ($this->col - $scol < 5 && ($next === ' ' || $next === "\t"));
        $gap = $this->col - $scol;
        if ($gap >= 5 || $gap < 1 || ($line[$this->off] ?? '') === '') {
            $data['mpad'] = $len + 1;
            $this->col = $scol;
            $this->off = $soff;
            if (in_array($line[$this->off] ?? '', [' ', "\t"], true)) $this->setLineOffset(1, true);
        } else {
            $data['mpad'] = $len + $gap;
        }
        return $data;
    }

    # Find the next nonspace of the line from the offset, counting a tab to the next stop of four, and whether the rest is blank
    private function setNextNonspace(): void {
        $line = $this->line;
        $len = strlen($line);
        $at = $this->off;
        $cols = $this->col;
        while ($at < $len) {
            $run = strspn($line, ' ', $at);
            $at += $run;
            $cols += $run;
            if (($line[$at] ?? '') !== "\t") break;
            $cols += 4 - ($cols % 4);
            $at++;
        }
        $this->blank = $at >= $len;
        $this->nsp = $at;
        $this->nscol = $cols;
        $this->indent = $cols - $this->col;
    }

    # Move the offset to the next nonspace found last
    private function setNonspaceOffset(): void {
        $this->off = $this->nsp;
        $this->col = $this->nscol;
        $this->ptab = false;
    }

    # Advance the offset by characters or by columns; a tab advanced by columns in part is left half consumed, and the leaf that takes the line expands its rest
    private function setLineOffset(int $cnt, bool $cols): void {
        $line = $this->line;
        $len = strlen($line);
        while ($cnt > 0 && $this->off < $len) {
            if ($line[$this->off] !== "\t") {
                $this->ptab = false;
                $this->off++;
                $this->col++;
                $cnt--;
                continue;
            }
            $tab = 4 - ($this->col % 4);
            if ($cols) {
                $this->ptab = $tab > $cnt;
                $step = min($tab, $cnt);
                $this->col += $step;
                $this->off += $this->ptab ? 0 : 1;
                $cnt -= $step;
            } else {
                $this->ptab = false;
                $this->col += $tab;
                $this->off++;
                $cnt--;
            }
        }
    }

    # Open a new block under the tip, closing the blocks that cannot hold it
    private function addBlockChild(string $type): object {
        while (!$this->checkBlockContain($this->tip, $type)) $this->setBlockFinal($this->tip);
        $node = $this->getBlockNode($type);
        $node->parent = $this->tip;
        $this->tip->kids[] = $node;
        $this->tip = $node;
        return $node;
    }

    # Which block may hold which: a list holds items alone, the other containers anything but an item, a leaf nothing
    private function checkBlockContain(object $node, string $type): bool {
        if ($node->type === 'list') return $type === 'item';
        return in_array($node->type, ['document', 'blockquote', 'item'], true) && $type !== 'item';
    }

    # Close the open blocks the line did not match, down to the last matched container
    private function setUnmatchedClosed(): void {
        if ($this->closed) return;
        while ($this->prev !== $this->last) {
            $up = $this->prev->parent;
            $this->setBlockFinal($this->prev);
            $this->prev = $up;
        }
        $this->closed = true;
    }

    # Take a block out of its parent
    private function deleteBlockNode(object $node): void {
        $kids = &$node->parent->kids;
        array_splice($kids, (int)array_search($node, $kids, true), 1);
    }

    # Append the rest of the line to the open leaf, expanding a tab the container prefix consumed in part, and remember where the line sits in the source
    private function addLeafLine(): void {
        $tip = $this->tip;
        if ($this->ptab) {
            $this->off++;
            $tip->text .= str_repeat(' ', 4 - ($this->col % 4));
        }
        $tip->map[] = [strlen($tip->text), $this->lpos + $this->off];
        $tip->text .= substr($this->line, $this->off)."\n";
        if ($this->scan && $tip->type === 'code') $this->spans[] = [$this->lpos + $this->off, $this->lpos + strlen($this->line)];
    }

    # Close one block: a paragraph gives up its leading link reference definitions, a code block splits off its info string, a list learns whether it is tight
    private function setBlockFinal(object $node): void {
        $node->open = false;
        if ($node->type === 'paragraph') {
            $this->setParaRefs($node);
            if ($node->refd && trim($node->text) === '') $this->deleteBlockNode($node);
        } elseif ($node->type === 'code' && $node->fchar !== '') {
            $cut = (int)strpos($node->text, "\n");
            $node->info = $this->getUnescapedText(trim(substr($node->text, 0, $cut)));
            $node->lit = substr($node->text, $cut + 1);
        } elseif ($node->type === 'code') {
            $node->lit = (preg_replace('/(?:\n[ \t]*)+\z/', '', $node->text) ?? $node->text)."\n";
        } elseif ($node->type === 'html') {
            $node->lit = preg_replace('/(?:\n *)+\z/', '', $node->text) ?? $node->text;
        } elseif ($node->type === 'list') {
            $node->tight = $this->checkListTight($node);
        }
        $this->tip = $node->parent;
    }

    # A list is loose when a blank line parts two of its items or two blocks of one item, the end of the last item aside
    private function checkListTight(object $list): bool {
        $cnt = count($list->kids);
        foreach ($list->kids as $i => $item) {
            $more = $i < $cnt - 1;
            if ($item->lblank && $more) return false;
            $sub = count($item->kids);
            foreach ($item->kids as $j => $kid) {
                if (!$more && $j === $sub - 1) continue;
                for ($end = $kid; $end !== null; $end = ($end->type === 'list' || $end->type === 'item') ? ($end->kids[count($end->kids) - 1] ?? null) : null) {
                    if ($end->lblank) return false;
                }
            }
        }
        return true;
    }

    # Strip the link reference definitions that open a paragraph into the map of references; a scan masks them, since a target is no text for the bracket layer
    private function setParaRefs(object $node): void {
        $at = 0;
        while (($node->text[$at] ?? '') === '[' && ($end = $this->getReferenceEnd($node->text, $at)) > $at) $at = $end;
        if ($at === 0) return;
        if ($this->scan) $this->addMaskRange($node->map, $node->base, $node->base + $at);
        $node->text = substr($node->text, $at);
        $node->base += $at;
        $node->refd = true;
    }

    # The end of one link reference definition at the offset, which joins the map of references unless its label is defined already, or the offset itself
    private function getReferenceEnd(string $src, int $from): int {
        $this->subj = $src;
        $this->pos = $from;
        $size = $this->getLinkLabel();
        if ($size === 0 || ($src[$this->pos] ?? '') !== ':') return $from;
        $label = substr($src, $from, $size);
        $this->pos++;
        $this->setSpaceSkip();
        $dest = $this->getLinkDest();
        if ($dest === null) return $from;
        $before = $this->pos;
        $this->setSpaceSkip();
        $title = $this->pos !== $before ? $this->getLinkTitle() : null;
        if ($title === null) {
            $title = '';
            $this->pos = $before;
        }
        if (!preg_match('/[ \t]*(?:\n|\z)/A', $src, $m, 0, $this->pos)) {
            if ($title === '') return $from;
            $title = '';
            $this->pos = $before;
            if (!preg_match('/[ \t]*(?:\n|\z)/A', $src, $m, 0, $this->pos)) return $from;
        }
        $key = $this->getLabelKey($label);
        if ($key === '') return $from;
        $this->refs[$key] ??= [$dest, $title];
        return $this->pos + strlen($m[0]);
    }

    # The key a link label is matched by: the brackets off, inner whitespace collapsed, Unicode case folded
    private function getLabelKey(string $lbl): string {
        $txt = preg_replace('/[ \t\r\n]+/', ' ', trim(substr($lbl, 1, -1), " \t\r\n")) ?? '';
        return mb_convert_case($txt, MB_CASE_FOLD, 'UTF-8');
    }

    # Render one block through the theme fragments; a tight list renders its paragraphs without the paragraph element
    private function getBlockHtml(object $node, bool $tight = false): string {
        switch ($node->type) {
            case 'paragraph':
                $txt = $this->getLeafHtml($node->text);
                return $tight ? $txt : implode($txt, $this->getPartPair(['is_para' => true], 'parser-block'));
            case 'heading':
                $lvl = min(6, $node->level + $this->hoff);
                $tree = $this->getInlineTree(trim($node->text, " \t\n"));
                return $this->getPartHtml('parser-block', [
                    'is_head' => true,
                    'level' => $lvl,
                    'id' => $this->getHeadingId($this->getPlainText($tree), $lvl),
                    'content_html' => $this->getInlineHtml($tree),
                ]);
            case 'rule':
                return $this->getPartHtml('parser-block', ['is_rule' => true]);
            case 'code':
                $lang = preg_split('/\s+/', $node->info ?? '')[0] ?? '';
                return $this->getCodeHtml(str_ends_with($node->lit, "\n") ? substr($node->lit, 0, -1) : $node->lit, $lang);
            case 'html':
                return $this->filterRawTags(str_replace(['&#034;', '&#039;'], ['"', "'"], $node->lit));
            case 'stash':
                return $node->text;
            case 'blockquote':
                return $this->getQuoteBlock($node);
            case 'list':
                [$beg, $end] = $this->getPartPair([], 'list-item');
                $rows = '';
                foreach ($node->kids as $item) $rows .= "\n".$beg.$this->getItemHtml($item, $node->tight).$end;
                return $this->getPartHtml('list', [
                    'is_unordered' => $node->ltype === 'bullet',
                    'is_classless' => true,
                    'has_start' => $node->ltype === 'ordered' && $node->start !== 1,
                    'start_num' => $node->start,
                    'items_html' => $rows."\n",
                ]);
            case 'table':
                return $this->getTableHtml($node);
        }
        return implode("\n", array_map(fn(object $kid): string => $this->getBlockHtml($kid), $node->kids));
    }

    # A blockquote, or a GFM alert when its first line is [!NOTE], [!TIP], [!IMPORTANT], [!WARNING] or [!CAUTION]
    private function getQuoteBlock(object $node): string {
        $kids = $node->kids;
        $tone = '';
        $first = $kids[0] ?? null;
        if ($first && $first->type === 'paragraph' && preg_match('/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\][ \t]*(?:\n|$)/i', $first->text, $m)) {
            $tone = match (strtolower($m[1])) {
                'tip' => 'success',
                'important' => 'accent',
                'warning' => 'warn',
                'caution' => 'error',
                default => 'info',
            };
            $first->text = substr($first->text, strlen($m[0]));
            if (trim($first->text) === '') array_shift($kids);
        }
        $txt = implode("\n", array_map(fn(object $kid): string => $this->getBlockHtml($kid), $kids));
        $txt = $txt === '' ? "\n" : "\n".$txt."\n";
        return $this->getQuoteHtml($tone !== '' ? ['is_callout' => true, 'content_html' => $txt, 'callout_type' => $tone] : ['is_plain' => true, 'content_html' => $txt]);
    }

    # The content of one list item: a tight paragraph runs inline, every other block stands on its own lines; a GFM task marker opens the first paragraph as a checkbox
    private function getItemHtml(object $item, bool $tight): string {
        $first = $item->kids[0] ?? null;
        $box = '';
        if ($first && $first->type === 'paragraph' && preg_match('/^\[([ xX])\](?=[ \t\n])[ \t]*/', $first->text, $m)) {
            $box = $this->getPartHtml('checkbox', ['input_attr' => 'disabled'.($m[1] === ' ' ? '' : ' checked')]).' ';
            $first->text = substr($first->text, strlen($m[0]));
        }
        $out = '';
        foreach ($item->kids as $kid) {
            if ($kid === $first && $box !== '') {
                $html = $box.$this->getLeafHtml($kid->text);
                if (!$tight) $html = implode($html, $this->getPartPair(['is_para' => true], 'parser-block'));
            } else {
                $html = $this->getBlockHtml($kid, $tight);
            }
            if ($tight && $kid->type === 'paragraph') $out .= $html;
            else $out .= (str_ends_with($out, "\n") ? '' : "\n").$html."\n";
        }
        return $out;
    }

    # A GFM table: a row has as many cells as the header, the missing ones empty and the extra ones dropped
    private function getTableHtml(object $node): string {
        $cols = count($node->head);
        $head = [];
        foreach ($node->head as $j => $cell) $head[] = ['align' => $node->aligns[$j] ?? '', 'content_html' => $this->getLeafHtml($cell[0])];
        $rows = [];
        foreach ($node->rows as $row) {
            $cells = [];
            for ($j = 0; $j < $cols; $j++) $cells[] = ['align' => $node->aligns[$j] ?? '', 'content_html' => isset($row[$j]) ? $this->getLeafHtml($row[$j][0]) : ''];
            $rows[] = ['cells' => $cells];
        }
        return $this->getPartHtml('parser-table', ['head' => $head, 'rows' => $rows]);
    }

    # Render the inline content of one leaf
    private function getLeafHtml(string $src): string {
        return $this->getInlineHtml($this->getInlineTree(trim($src, " \t\n")));
    }

    # Escape the tags GFM disallows in raw HTML, so a trusted text can neither run nor swallow the page with them
    private function filterRawTags(string $html): string {
        if (self::$gfmoff) return $html;
        return preg_replace('/<(?=\/?(?:title|textarea|style|xmp|iframe|noembed|noframes|script|plaintext)(?:[\s\/>]|$))/i', '&lt;', $html) ?? $html;
    }

    # Parse inline content into a node tree by the CommonMark rules with the GFM extensions and the mark of SLAED; a scan records where code, targets and raw tags lie
    private function getInlineTree(string $src): object {
        $root = $this->getInlineNode('root');
        $this->subj = $src;
        $this->pos = 0;
        $this->delims = null;
        $this->brack = null;
        $len = strlen($src);
        while ($this->pos < $len) $this->addInlineToken($root);
        $this->setEmphasis(null);
        if (!$this->scan && !self::$gfmoff) $this->setEmailLinks($root);
        return $root;
    }

    # A new inline node
    private function getInlineNode(string $type, string $text = ''): object {
        return (object)['type' => $type, 'text' => $text, 'first' => null, 'last' => null, 'next' => null, 'prev' => null, 'parent' => null, 'dest' => '', 'title' => ''];
    }

    # Append an inline node to a parent
    private function addInlineChild(object $par, object $node): void {
        $node->parent = $par;
        $node->next = null;
        $node->prev = $par->last;
        if ($par->last !== null) $par->last->next = $node;
        else $par->first = $node;
        $par->last = $node;
    }

    # Take an inline node out of its parent
    private function deleteInlineNode(object $node): void {
        if ($node->prev !== null) $node->prev->next = $node->next;
        elseif ($node->parent !== null) $node->parent->first = $node->next;
        if ($node->next !== null) $node->next->prev = $node->prev;
        elseif ($node->parent !== null) $node->parent->last = $node->prev;
        $node->parent = $node->next = $node->prev = null;
    }

    # Insert an inline node right after another
    private function addInlineAfter(object $node, object $sib): void {
        $sib->parent = $node->parent;
        $sib->prev = $node;
        $sib->next = $node->next;
        if ($node->next !== null) $node->next->prev = $sib;
        elseif ($node->parent !== null) $node->parent->last = $sib;
        $node->next = $sib;
    }

    # Read one inline construct at the cursor; a character that opens nothing becomes text
    private function addInlineToken(object $root): void {
        $chr = $this->subj[$this->pos];
        $done = match ($chr) {
            "\n" => $this->addLineBreak($root),
            '\\' => $this->addBackslash($root),
            '`' => $this->addCodeSpan($root),
            '*', '_', '~', '=' => $this->addInlineDelim($root, $chr),
            '[', '!' => $this->addOpenBracket($root, $chr === '!'),
            ']' => $this->addCloseBracket($root),
            '<' => $this->addAutoLink($root) || (!$this->safe && $this->addHtmlTag($root)),
            '&' => $this->addEntityText($root),
            default => $this->addInlineText($root),
        };
        if ($done) return;
        $this->pos++;
        $this->addInlineChild($root, $this->getInlineNode('text', $chr));
    }

    # A line ending: a hard break after two spaces, a soft one otherwise; the spaces around it go
    private function addLineBreak(object $root): bool {
        $this->pos++;
        $last = $root->last;
        $type = 'soft';
        if ($last !== null && $last->type === 'text' && str_ends_with($last->text, ' ')) {
            if (str_ends_with($last->text, '  ')) $type = 'hard';
            $last->text = rtrim($last->text, ' ');
        }
        $this->addInlineChild($root, $this->getInlineNode($type));
        $this->pos += strspn($this->subj, ' ', $this->pos);
        return true;
    }

    # A backslash: before a line ending a hard break, before ASCII punctuation that character as text, otherwise itself
    private function addBackslash(object $root): bool {
        $chr = $this->subj[$this->pos + 1] ?? '';
        if ($chr === "\n") {
            $this->pos += 2;
            $this->addInlineChild($root, $this->getInlineNode('hard'));
        } elseif ($chr !== '' && str_contains(self::PUNCT, $chr)) {
            $this->pos += 2;
            $this->addInlineChild($root, $this->getInlineNode('text', $chr));
        } else {
            $this->pos++;
            $this->addInlineChild($root, $this->getInlineNode('text', '\\'));
        }
        return true;
    }

    # A code span closes at the next backtick run of the same length; line endings become spaces and one space is stripped from each side of a padded content
    private function addCodeSpan(object $root): bool {
        $start = $this->pos;
        $ticks = strspn($this->subj, '`', $start);
        $after = $start + $ticks;
        for ($at = $after; ($at = strpos($this->subj, '`', $at)) !== false; $at += $run) {
            $run = strspn($this->subj, '`', $at);
            if ($run !== $ticks) continue;
            $txt = str_replace("\n", ' ', substr($this->subj, $after, $at - $after));
            if (strlen($txt) > 1 && $txt[0] === ' ' && str_ends_with($txt, ' ') && trim($txt, ' ') !== '') $txt = substr($txt, 1, -1);
            $this->addInlineChild($root, $this->getInlineNode('code', $txt));
            $this->pos = $at + $run;
            if ($this->scan) $this->ispan[] = [$start, $this->pos];
            return true;
        }
        $this->pos = $after;
        $this->addInlineChild($root, $this->getInlineNode('text', str_repeat('`', $ticks)));
        return true;
    }

    # A delimiter run of * or _ for emphasis, of one or two ~ for GFM strikethrough or of two = for the mark of SLAED, with its flanking read from the characters around it
    private function addInlineDelim(object $root, string $chr): bool {
        $start = $this->pos;
        $num = strspn($this->subj, $chr, $start);
        $this->pos += $num;
        $node = $this->getInlineNode('text', substr($this->subj, $start, $num));
        $this->addInlineChild($root, $node);
        if (($chr === '~' && $num > 2) || ($chr === '=' && $num !== 2)) return true;
        $before = $this->getNearChar($start, true);
        $after = $this->getNearChar($this->pos, false);
        $bsp = $this->isSpaceChar($before);
        $bpu = $this->isPunctChar($before);
        $asp = $this->isSpaceChar($after);
        $apu = $this->isPunctChar($after);
        $left = !$asp && (!$apu || $bsp || $bpu);
        $right = !$bsp && (!$bpu || $asp || $apu);
        $open = $chr === '_' ? $left && (!$right || $bpu) : $left;
        $close = $chr === '_' ? $right && (!$left || $apu) : $right;
        if (!$open && !$close) return true;
        $this->delims = (object)['cc' => $chr, 'num' => $num, 'orig' => $num, 'node' => $node, 'prev' => $this->delims, 'next' => null, 'open' => $open, 'close' => $close];
        if ($this->delims->prev !== null) $this->delims->prev->next = $this->delims;
        return true;
    }

    # The UTF-8 character before or at an offset of the subject; the edges count as a line ending
    private function getNearChar(int $at, bool $back): string {
        $subj = $this->subj;
        if ($back) {
            if ($at <= 0) return "\n";
            $beg = $at - 1;
            while ($beg > 0 && $at - $beg < 4 && (ord($subj[$beg]) & 0xC0) === 0x80) $beg--;
            return substr($subj, $beg, $at - $beg);
        }
        if ($at >= strlen($subj)) return "\n";
        $byte = ord($subj[$at]);
        return substr($subj, $at, $byte < 0x80 ? 1 : ($byte >= 0xF0 ? 4 : ($byte >= 0xE0 ? 3 : 2)));
    }

    # Unicode whitespace by CommonMark: the Zs category, tab, line feed, form feed and carriage return
    private function isSpaceChar(string $chr): bool {
        if (strlen($chr) === 1) return str_contains(" \t\n\f\r", $chr);
        return (bool)preg_match('/^\p{Zs}/u', $chr);
    }

    # Unicode punctuation by CommonMark: the P and S categories
    private function isPunctChar(string $chr): bool {
        if (strlen($chr) === 1) return ctype_punct($chr);
        return (bool)preg_match('/^[\p{P}\p{S}]/u', $chr);
    }

    # Process the delimiter runs above the bottom of the stack into emphasis, strong emphasis, strikethrough and mark by the rules of CommonMark and GFM
    private function setEmphasis(?object $bottom): void {
        $floor = [];
        $closer = $this->delims;
        while ($closer !== null && $closer->prev !== $bottom) $closer = $closer->prev;
        while ($closer !== null) {
            if (!$closer->close) {
                $closer = $closer->next;
                continue;
            }
            $cc = $closer->cc;
            $star = $cc === '*' || $cc === '_';
            $key = $star ? $cc.(int)$closer->open.($closer->orig % 3) : $cc;
            $low = array_key_exists($key, $floor) ? $floor[$key] : $bottom;
            $found = false;
            for ($open = $closer->prev; $open !== null && $open !== $bottom && $open !== $low; $open = $open->prev) {
                if ($open->cc !== $cc || !$open->open) continue;
                if ($star && ($closer->open || $open->close) && $closer->orig % 3 !== 0 && ($open->orig + $closer->orig) % 3 === 0) continue;
                if (!$star && $open->num !== $closer->num) continue;
                $found = true;
                break;
            }
            $old = $closer;
            if ($found) {
                $use = $star ? (($closer->num >= 2 && $open->num >= 2) ? 2 : 1) : $closer->num;
                $type = match (true) {
                    $cc === '~' => 'del',
                    $cc === '=' => 'mark',
                    $use === 1 => 'em',
                    default => 'strong',
                };
                $onode = $open->node;
                $cnode = $closer->node;
                $open->num -= $use;
                $closer->num -= $use;
                $onode->text = substr($onode->text, 0, -$use);
                $cnode->text = substr($cnode->text, 0, -$use);
                $wrap = $this->getInlineNode($type);
                for ($tmp = $onode->next; $tmp !== null && $tmp !== $cnode; $tmp = $next) {
                    $next = $tmp->next;
                    $this->deleteInlineNode($tmp);
                    $this->addInlineChild($wrap, $tmp);
                }
                $this->addInlineAfter($onode, $wrap);
                if ($open->next !== $closer) {
                    $open->next = $closer;
                    $closer->prev = $open;
                }
                if ($open->num === 0) {
                    $this->deleteInlineNode($onode);
                    $this->deleteDelimiter($open);
                }
                if ($closer->num === 0) {
                    $this->deleteInlineNode($cnode);
                    $next = $closer->next;
                    $this->deleteDelimiter($closer);
                    $closer = $next;
                }
                continue;
            }
            $closer = $closer->next;
            $floor[$key] = $old->prev;
            if (!$old->open) $this->deleteDelimiter($old);
        }
        while ($this->delims !== null && $this->delims !== $bottom) $this->deleteDelimiter($this->delims);
    }

    # Take one entry out of the delimiter stack
    private function deleteDelimiter(object $delim): void {
        if ($delim->prev !== null) $delim->prev->next = $delim->next;
        if ($delim->next === null) $this->delims = $delim->prev;
        else $delim->next->prev = $delim->prev;
    }

    # An opening bracket of a link, or of an image after !, as text on the bracket stack; a ! before anything else is text
    private function addOpenBracket(object $root, bool $bang): bool {
        $start = $this->pos;
        if ($bang && ($this->subj[$start + 1] ?? '') !== '[') {
            $this->pos++;
            $this->addInlineChild($root, $this->getInlineNode('text', '!'));
            return true;
        }
        $node = $this->getInlineNode('text', $bang ? '![' : '[');
        $this->pos += $bang ? 2 : 1;
        $this->addInlineChild($root, $node);
        if ($this->brack !== null) $this->brack->after = true;
        $this->brack = (object)['node' => $node, 'prev' => $this->brack, 'pdelim' => $this->delims, 'index' => $bang ? $start + 1 : $start, 'image' => $bang,
            'active' => true, 'after' => false];
        return true;
    }

    # A closing bracket: with the opener on the stack it makes an inline link, a full, collapsed or shortcut reference link, or the image of one; otherwise it is text
    private function addCloseBracket(object $root): bool {
        $this->pos++;
        $start = $this->pos;
        $open = $this->brack;
        if ($open === null || !$open->active) {
            if ($open !== null) $this->brack = $open->prev;
            $this->addInlineChild($root, $this->getInlineNode('text', ']'));
            return true;
        }
        $dest = null;
        $title = null;
        $found = false;
        if (($this->subj[$this->pos] ?? '') === '(') {
            $this->pos++;
            $this->setSpaceSkip();
            $dest = $this->getLinkDest();
            if ($dest !== null) {
                $this->setSpaceSkip();
                if ($this->isSpaceChar($this->subj[$this->pos - 1])) $title = $this->getLinkTitle();
                $this->setSpaceSkip();
                $found = ($this->subj[$this->pos] ?? '') === ')';
            }
            if ($found) {
                $this->pos++;
                if ($this->scan) $this->ispan[] = [$start, $this->pos];
            } else {
                $this->pos = $start;
            }
        }
        if (!$found) {
            $size = $this->getLinkLabel();
            $label = $size > 2 ? substr($this->subj, $start, $size) : (!$open->after ? substr($this->subj, $open->index, $start - $open->index) : '');
            if ($size === 0) $this->pos = $start;
            $key = $label !== '' ? $this->getLabelKey($label) : '';
            if ($key !== '' && isset($this->refs[$key])) {
                [$dest, $title] = $this->refs[$key];
                $found = true;
            }
        }
        if (!$found) {
            $this->brack = $open->prev;
            $this->pos = $start;
            $this->addInlineChild($root, $this->getInlineNode('text', ']'));
            return true;
        }
        $link = $this->getInlineNode($open->image ? 'image' : 'link');
        $link->dest = $dest;
        $link->title = $title ?? '';
        for ($tmp = $open->node->next; $tmp !== null; $tmp = $next) {
            $next = $tmp->next;
            $this->deleteInlineNode($tmp);
            $this->addInlineChild($link, $tmp);
        }
        $this->addInlineChild($root, $link);
        $this->setEmphasis($open->pdelim);
        $this->brack = $open->prev;
        $this->deleteInlineNode($open->node);
        if (!$open->image) for ($up = $this->brack; $up !== null; $up = $up->prev) if (!$up->image) $up->active = false;
        return true;
    }

    # Skip spaces and tabs with at most one line ending among them
    private function setSpaceSkip(): void {
        if (preg_match('/[ \t]*(?:\n[ \t]*)?/A', $this->subj, $m, 0, $this->pos)) $this->pos += strlen($m[0]);
    }

    # A link destination at the cursor, <in brackets> or bare with balanced parentheses, unescaped and percent-encoded, or null; 32 levels of them, as cmark allows, bound the scan
    private function getLinkDest(): ?string {
        $subj = $this->subj;
        $start = $this->pos;
        if (($subj[$start] ?? '') === '<') {
            if (!preg_match('/<((?:[^<>\n\\\\\x00]|\\\\.)*+)>/A', $subj, $m, 0, $start)) return null;
            $this->pos += strlen($m[0]);
            return $this->getEncodedUrl($this->getUnescapedText($m[1]));
        }
        $len = strlen($subj);
        $at = $start;
        $depth = 0;
        while ($at < $len) {
            preg_match('/[^\\\\()\x00-\x20\x7f]*+/A', $subj, $m, 0, $at);
            $at += strlen($m[0]);
            $chr = $subj[$at] ?? '';
            if ($chr === '\\') $at += str_contains(self::PUNCT, $subj[$at + 1] ?? ' ') ? 2 : 1;
            elseif ($chr === '(') {
                $depth++;
                $at++;
            } elseif ($chr === ')' && $depth > 0) {
                $depth--;
                $at++;
            } else break;
            if ($depth > 32) return null;
        }
        if (($at === $start && ($subj[$at] ?? '') !== ')') || $depth !== 0) return null;
        $this->pos = $at;
        return $this->getEncodedUrl($this->getUnescapedText(substr($subj, $start, $at - $start)));
    }

    # A link title at the cursor in double quotes, single quotes or parentheses, unescaped, or null
    private function getLinkTitle(): ?string {
        $pat = '/"((?:\\\\[\s\S]|[^\\\\"\x00])*+)"|\'((?:\\\\[\s\S]|[^\\\\\'\x00])*+)\'|\(((?:\\\\[\s\S]|[^\\\\()\x00])*+)\)/A';
        if (!preg_match($pat, $this->subj, $m, 0, $this->pos)) return null;
        $this->pos += strlen($m[0]);
        return $this->getUnescapedText(substr($m[0], 1, -1));
    }

    # The length of a link label at the cursor, which it passes, or 0
    private function getLinkLabel(): int {
        if (!preg_match('/\[(?:[^\\\\\[\]]|\\\\.){0,1000}\]/sA', $this->subj, $m, 0, $this->pos) || strlen($m[0]) > 1001) return 0;
        $this->pos += strlen($m[0]);
        return strlen($m[0]);
    }

    # A CommonMark autolink, <scheme:target> or <name@host>, whose target is taken as typed: no escape and no entity works inside
    private function addAutoLink(object $root): bool {
        $start = $this->pos;
        $mail = '/<([a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*)>/A';
        if (preg_match($mail, $this->subj, $m, 0, $start)) $dest = 'mailto:'.$m[1];
        elseif (preg_match('/<([A-Za-z][A-Za-z0-9.+-]{1,31}:[^<>\x00-\x20]*)>/A', $this->subj, $m, 0, $start)) $dest = $m[1];
        else return false;
        $link = $this->getInlineNode('link');
        $link->dest = $this->getEncodedUrl($dest);
        $this->addInlineChild($link, $this->getInlineNode('text', $m[1]));
        $this->addInlineChild($root, $link);
        $this->pos += strlen($m[0]);
        if ($this->scan) $this->ispan[] = [$start, $this->pos];
        return true;
    }

    # A raw HTML tag, comment, processing instruction, declaration or CDATA section of a trusted text
    private function addHtmlTag(object $root): bool {
        if (!preg_match(self::HTMLTAG, $this->subj, $m, 0, $this->pos)) return false;
        if ($this->scan) $this->ispan[] = [$this->pos, $this->pos + strlen($m[0])];
        $this->pos += strlen($m[0]);
        $this->addInlineChild($root, $this->getInlineNode('html', $m[0]));
        return true;
    }

    # An entity or numeric character reference as the text it stands for; an unknown name stays as typed
    private function addEntityText(object $root): bool {
        if (!preg_match('/'.self::ENTITY.'/A', $this->subj, $m, 0, $this->pos)) return false;
        $this->pos += strlen($m[0]);
        $this->addInlineChild($root, $this->getInlineNode('text', $this->getEntityText($m[0])));
        return true;
    }

    # A run of plain text up to the next character that may open a construct; a GFM extended autolink inside it is cut out, and no scan masks one, since [url] encloses a bare one
    private function addInlineText(object $root): bool {
        $start = $this->pos;
        $end = $start + max(1, strcspn($this->subj, self::SPECIAL, $start));
        $run = substr($this->subj, $start, $end - $start);
        if ($this->brack === null && !self::$gfmoff && (str_contains($run, 'www.') || str_contains($run, '://'))
            && preg_match(self::AUTOLINK, $this->subj, $m, PREG_OFFSET_CAPTURE, $start) && $m[0][1] < $end
        ) {
            $at = $m[0][1];
            $www = $m[0][0] === 'www.';
            $stop = $at > $start ? 0 : $this->getAutoLinkEnd($at, $www);
            if ($stop > 0) {
                $txt = substr($this->subj, $at, $stop - $at);
                $link = $this->getInlineNode('link');
                $link->dest = $this->getEncodedUrl($www ? 'http://'.$txt : $txt);
                $this->addInlineChild($link, $this->getInlineNode('text', $txt));
                $this->addInlineChild($root, $link);
                $this->pos = $stop;
                return true;
            }
            $end = $at > $start ? $at : $start + 1;
        }
        $this->addInlineChild($root, $this->getInlineNode('text', substr($this->subj, $start, $end - $start)));
        $this->pos = $end;
        return true;
    }

    # The end of a GFM extended autolink at the offset or 0: a valid domain, the path to a space, < or a stash, less trailing punctuation, an unmatched ) and an entity
    private function getAutoLinkEnd(int $at, bool $www): int {
        $subj = $this->subj;
        $dom = $www ? $at : strpos($subj, '://', $at) + 3;
        if (!preg_match('/(?:[^\s\p{P}\p{S}]|[\-_.])+/uA', $subj, $m, 0, $dom)) return 0;
        $part = explode('.', $m[0]);
        $cnt = count($part);
        if (($www && ($cnt < 2 || strlen($m[0]) <= 4)) || str_contains($part[$cnt - 1], '_') || ($cnt > 1 && str_contains($part[$cnt - 2], '_'))) return 0;
        $len = strcspn($subj, " \t\n\r\f\v<\x02", $at);
        $txt = substr($subj, $at, $len);
        $open = substr_count($txt, '(');
        $shut = substr_count($txt, ')');
        while ($len > 0) {
            $chr = $txt[$len - 1];
            if ($chr === ')' && $shut > $open) {
                $shut--;
                $len--;
            } elseif (str_contains('?!.,:*_~\'"', $chr)) {
                $len--;
            } elseif ($chr === ';') {
                $amp = $len - 2;
                while ($amp > 0 && ctype_alpha($txt[$amp])) $amp--;
                $len = ($amp < $len - 2 && $txt[$amp] === '&') ? $amp : $len - 1;
            } else break;
        }
        return $len > $dom - $at ? $at + $len : 0;
    }

    # Turn the e-mail addresses of the text outside links into GFM extended autolinks; adjacent text merges first, as an address may span what the parser read apart
    private function setEmailLinks(object $par): void {
        for ($node = $par->first; $node !== null; $node = $node->next) {
            if ($node->type === 'link' || $node->type === 'image') continue;
            if ($node->type !== 'text') {
                if ($node->first !== null) $this->setEmailLinks($node);
                continue;
            }
            while ($node->next !== null && $node->next->type === 'text') {
                $node->text .= $node->next->text;
                $this->deleteInlineNode($node->next);
            }
            if (!str_contains($node->text, '@') || ($span = $this->getEmailSpan($node->text)) === null) continue;
            [$beg, $end] = $span;
            $mail = substr($node->text, $beg, $end - $beg);
            $rest = $this->getInlineNode('text', substr($node->text, $end));
            $link = $this->getInlineNode('link');
            $link->dest = $this->getEncodedUrl('mailto:'.$mail);
            $this->addInlineChild($link, $this->getInlineNode('text', $mail));
            $node->text = substr($node->text, 0, $beg);
            $this->addInlineAfter($node, $link);
            $this->addInlineAfter($link, $rest);
            $node = $link;
        }
    }

    # The first e-mail address of a text as GFM reads one: a local part of letters, digits and .+-_, one @, a domain with a dot that ends in a letter
    private function getEmailSpan(string $txt): ?array {
        $len = strlen($txt);
        for ($at = 0; ($at = strpos($txt, '@', $at)) !== false; $at++) {
            $beg = $at;
            while ($beg > 0 && (ctype_alnum($txt[$beg - 1]) || str_contains('.+-_', $txt[$beg - 1]))) $beg--;
            if ($beg === $at) continue;
            $end = $at + 1;
            $dots = 0;
            while ($end < $len) {
                $chr = $txt[$end];
                if ($chr === '.' && $end + 1 < $len && ctype_alnum($txt[$end + 1])) $dots++;
                elseif (!ctype_alnum($chr) && $chr !== '-' && $chr !== '_') break;
                $end++;
            }
            if ($dots === 0 || ($txt[$end] ?? '') === '@' || !ctype_alpha($txt[$end - 1])) continue;
            return [$beg, $end];
        }
        return null;
    }

    # The text an entity or numeric reference stands for; zero and invalid code points become U+FFFD, an unknown name stays as typed
    private function getEntityText(string $ent): string {
        if ($ent[1] !== '#') return html_entity_decode($ent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $num = ($ent[2] === 'x' || $ent[2] === 'X') ? intval(substr($ent, 3, -1), 16) : intval(substr($ent, 2, -1));
        if ($num === 0 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF)) $num = 0xFFFD;
        return mb_chr($num, 'UTF-8');
    }

    # Resolve the backslash escapes and the references of a link target, a title or an info string
    private function getUnescapedText(string $src): string {
        if (!str_contains($src, '\\') && !str_contains($src, '&')) return $src;
        $pat = '/\\\\([!-\/:-@\[-`{-~])|'.self::ENTITY.'/';
        return preg_replace_callback($pat, fn(array $m): string => ($m[1] ?? '') !== '' ? $m[1] : $this->getEntityText($m[0]), $src) ?? $src;
    }

    # Percent-encode a link target as CommonMark normalizes one: what a URL may carry stays, a valid %XX stays, every other byte is encoded
    private function getEncodedUrl(string $url): string {
        return preg_replace_callback('/%(?![0-9a-fA-F]{2})|[^A-Za-z0-9;\/?:@&=+$,\-_.!~*\'()#%]/', fn(array $m): string => sprintf('%%%02X', ord($m[0])), $url) ?? $url;
    }

    # Render an inline tree through the theme fragments; text is escaped here, and a soft break of the breaks format becomes a break unless a typed one stands before it
    private function getInlineHtml(object $par): string {
        $out = '';
        for ($node = $par->first; $node !== null; $node = $node->next) {
            $out .= match ($node->type) {
                'text' => $this->filterEsc($node->text),
                'soft' => ($this->fmt === 'breaks' && !$this->isRawBreak($node->prev)) ? $this->getPartTag(['is_break' => true])."\n" : "\n",
                'hard' => $this->getPartTag(['is_break' => true])."\n",
                'code' => implode($this->filterEsc($node->text), $this->getPartPair(['is_code' => true])),
                'em', 'strong', 'del', 'mark' => implode($this->getInlineHtml($node), $this->getPartPair(['is_'.$node->type => true])),
                'link' => $this->getPartLink($this->filterUrl($node->dest), $this->getInlineHtml($node), $node->title, false, true),
                'image' => $this->getImageHtml($node),
                'html' => $this->filterRawTags($node->text),
                default => '',
            };
        }
        return $out;
    }

    # Whether an inline node is a break typed as raw HTML
    private function isRawBreak(?object $node): bool {
        return $node !== null && $node->type === 'html' && preg_match('/^<br\s*\/?>$/i', $node->text) === 1;
    }

    # One Markdown image: the alt is the plain text of its description, the title only the one given, and a local source is resolved on disk
    private function getImageHtml(object $node): string {
        $url = stripos(trim($node->dest), 'data:') === 0 ? $node->dest : $this->filterUrl($node->dest);
        return trim($this->getParserImage($this->normalizeImageSource($url), $this->getPlainText($node), $node->title));
    }

    # The plain text of an inline tree, for an image description and a heading id
    private function getPlainText(object $par): string {
        $out = '';
        for ($node = $par->first; $node !== null; $node = $node->next) {
            $out .= match ($node->type) {
                'text', 'code', 'html' => $node->text,
                'soft', 'hard' => "\n",
                default => $this->getPlainText($node),
            };
        }
        return $out;
    }

    # Render a plain-format body: no Markdown block is recognized, a blank line separates paragraphs and every other line ending becomes a break, exactly as the author typed it
    private function filterPlain(string $src): string {
        $pat = '/^\x02'.preg_quote($this->salt, '/').':\d+\x03$/';
        $out = '';
        $para = [];
        $brk = $this->getPartTag(['is_break' => true])."\n";
        foreach (explode("\n", $src) as $line) {
            if (preg_match($pat, trim($line)) || trim($line) === '') {
                if ($para) { $out .= $this->addStash($this->getPartHtml('parser-block', ['is_para' => true, 'content_html' => implode($brk, $para)]))."\n"; $para = []; }
                $out .= (trim($line) === '') ? "\n" : $line."\n";
                continue;
            }
            $para[] = $this->filterInline($line);
        }
        if ($para) $out .= $this->addStash($this->getPartHtml('parser-block', ['is_para' => true, 'content_html' => implode($brk, $para)]))."\n";
        return $out;
    }

    # Inline entry point of the plain format: pre-escape user text via filterText() in safe mode, then run the autolinks
    private function filterInline(string $src): string {
        return $this->filterInlines($this->safe ? $this->filterText($src) : $src);
    }

    # Inline pipeline of the plain format: autolinks, then the safe HTML escape
    private function filterInlines(string $src): string {
        if (!str_contains($src, '<')) return $src;
        $src = preg_replace_callback(
            '/<(https?:\/\/[^\s>]+)>/',
            fn(array $m): string => $this->addStash($this->getPartLink($m[1], $m[1])),
            $src
        ) ?? $src;
        $src = preg_replace_callback(
            '/<([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})>/',
            fn(array $m): string => $this->addStash($this->getPartLink('mailto:'.$m[1], $m[1])),
            $src
        ) ?? $src;
        if ($this->safe) $src = preg_replace_callback('/<[^>]+>/', fn(array $m): string => $this->filterEsc($m[0]), $src) ?? $src;
        return $src;
    }

    # Escape remaining HTML tags of the plain format in safe mode; unsafe mode is a no-op because its HTML is the author's
    private function filterSafe(string $src): string {
        if (!$this->safe) return $src;
        return preg_replace_callback(
            '/<[^>]+>/',
            fn(array $m): string => htmlspecialchars($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $src
        ) ?? $src;
    }
}
