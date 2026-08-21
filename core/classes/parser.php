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
    public static bool $freeoff = false;
    private static array $pcache = [];
    private array $stash = [];
    private string $salt = '';
    private int $scnt = 0;
    private bool $safe = true;
    private string $mod = '';
    private int $hoff = 0;
    private string $fmt = '';
    private array $hids = [];
    private bool $vary = false;

    # Parse src through the pipeline; heading offset raises Markdown levels inside an already titled container and caps them at H6
    # The format names how the source is to be read, not who wrote it: plain recognizes no Markdown construct and turns every line ending into a break, breaks is Markdown that
    # also breaks on a single line ending, and anything else is plain Markdown, where a lone line ending joins the lines around it
    # breaks is what a conversation channel asks for: a reader of a comment or a message typed the line endings they meant, and every one of them renders the same way whoever wrote it
    public function filterDoc(string $src, bool $safe = true, string $mod = '', int $hoff = 0, string $fmt = ''): string {
        $hoff = max(0, min(5, $hoff));
        $fmt = in_array($fmt, ['plain', 'breaks'], true) ? $fmt : '';
        $key = md5($src.(int)$safe.$mod.$hoff.$fmt);
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
        $this->mod   = $mod;
        $this->hoff  = $hoff;
        $this->fmt   = $fmt;
        $src = str_replace(["\r\n", "\r"], "\n", $src);
        if ($fmt !== 'plain') $src = $this->filterCode($src);
        $src = $this->filterBbBlocks($src);
        $src = $this->filterFreeBlocks($src);
        $out = ($fmt === 'plain') ? $this->filterPlain($src) : $this->filterBlocks($src);
        $out = $this->filterSafe($out);
        $out = $this->filterStash($out);
        $out = trim($out);
        self::$pcache[$key] = [$out, $this->vary];
        return $out;
    }

    # Replace trusted [block=id] tags with rendered free (infly) block output; frontend only, skipped for unsafe content, block-content filtering, nested rendering and standalone test runs
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
            return $out;
        }, $src) ?? $src;
    }

    # Standard rendering pipeline: filterDoc() plus replace rules and img repair; call filterDoc() directly when replacement rules must not apply (changelog, search)
    # Stored is the finished rendering of a source of at least CACHEMIN bytes whose parse does not vary; a [block=] or [usephp] source is rendered anew on every serve
    # The stored rendering lives here and not in a caller, because the key is built from what this class itself reads and only this class knows whether a parse may be reused at all
    public function filterContent(string $src, bool $safe, string $mod, int $hoff = 0, string $fmt = ''): string {
        $file = $this->getCachePath($src, $safe, $mod, $hoff, $fmt);
        if ($file !== '' && Cache::isFresh($file, self::CACHETTL)) {
            $out = Cache::getBody($file);
            if ($out !== '') return $out;
        }
        $out = $this->normalizeHtmlImages(
            $this->replaceText($this->filterDoc($src, $safe, $mod, $hoff, $fmt), $mod)
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
        ]));
    }

    # The cache path of one rendering, or an empty string when nothing may be stored; the key carries every input the output depends on, the class version included
    private function getCachePath(string $src, bool $safe, string $mod, int $hoff, string $fmt): string {
        static $ver = '';
        if (strlen($src) < self::CACHEMIN || !$this->checkCacheReady()) return '';
        if ($ver === '') $ver = (string)filemtime(__FILE__);
        return Cache::getPath('data', Cache::getHash([
            'parser', $ver, $this->getConfigHash($mod), sha1($src), (int)$safe, $mod, $hoff, $fmt, getTheme(), _LOCALE,
        ]), 'html');
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

    # Store a protected fragment and return its salted control-char stash token
    private function addStash(string $val): string {
        $tok = "\x02{$this->salt}:{$this->scnt}\x03";
        $this->stash["{$this->salt}:{$this->scnt}"] = $val;
        $this->scnt++;
        return $tok;
    }

    # Restore all stash tokens iteratively to handle nested fragments
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
        return $src;
    }

    # Full re-parse of nested block content for [quote]/[hide]; reuses the live stash and salt and leaves filterStash()/trim() to the top level
    private function filterNest(string $src): string {
        $src = $this->filterCode($src);
        $src = $this->filterBbBlocks($src);
        $src = $this->filterBlocks($src);
        $src = $this->filterSafe($src);
        return $src;
    }

    # Escape HTML special characters
    private function filterEsc(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    # Decode HTML entities
    private function filterDec(string $s): string {
        return getDecodedText($s);
    }

    # Public web root for resolving relative asset paths
    private function getRootPath(): string {
        static $root = '';
        if ($root === '') $root = dirname(__DIR__, 2);
        return $root;
    }

    # Render parser images through the theme fragment so markup stays out of PHP
    private function getParserImage(?string $src, string $alt, string $title = '', string $align = '', bool $isbb = false): string {
        global $tpl;
        if (!isset($tpl) || !is_object($tpl) || !method_exists($tpl, 'getHtmlFrag')) return $this->filterEsc($alt);
        return (string) $tpl->getHtmlFrag('parser-image', [
            'src' => $src ?? '',
            'alt' => $alt,
            'title' => $title,
            'missing' => $src === null,
            'fallback' => $src !== null,
            'bbcode' => $isbb,
            'left' => $align === 'left',
            'right' => $align === 'right',
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

    # One inline element whose content is a placeholder instead of text, so the flags are a closed set and the answer may be memoized
    # The engine instance is part of the key: two themes can spell one element differently and a request may hold more than one of them
    private function getPartTag(array $data): string {
        global $tpl;
        static $memo = [];
        $key = (is_object($tpl) ? spl_object_id($tpl) : 0).'|'.serialize($data);
        return $memo[$key] ??= $this->getPartHtml('parser-inline', $data);
    }

    # The two halves of one inline element, split where the template rendered its content, so the theme keeps a whole element while the parser wraps text it has not finished reading
    private function getPartPair(array $data): array {
        $data['content_html'] = "\x01";
        $part = explode("\x01", $this->getPartTag($data), 2);
        return [$part[0], $part[1] ?? ''];
    }

    # One anchor through the theme fragment; the address, the title and a plain label arrive unescaped, because escaping them is what the template is for
    private function getPartLink(string $href, string $lbl, string $ttl = '', bool $blank = false, bool $raw = false): string {
        $data = ['href' => $href, 'title' => $ttl, 'is_blank' => $blank];
        $data[$raw ? 'label_html' : 'label'] = $lbl;
        return $this->getPartHtml('parser-link', $data);
    }

    # Memoized wrapper so repeated image paths hit the filesystem only once per request; the key is hashed because an inline data URI would otherwise be kept twice in memory
    # Resolving an image reads the filesystem, so the rendering it produces answers for a file that may appear or vanish later and is marked as one no stored copy may answer for
    private function normalizeImageSource(string $src): ?string {
        static $memo = [];
        $this->vary = true;
        $key = md5($src);
        if (array_key_exists($key, $memo)) return $memo[$key];
        return $memo[$key] = $this->checkImageSource($src);
    }

    # Convert a local/absolute image source into a stable public path; data URIs survive only as whitelisted base64 raster images and are length-capped before any regex or decode allocates a copy
    # The whitelist is EMBEDIMG and is matched as a subtype rather than spelled into the pattern, so the editor, the upload adapter and this render bound read one list and cannot disagree
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

        $host = parse_url($raw, PHP_URL_HOST);
        $path = parse_url($raw, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : $raw;
        $path = ltrim($path, '/');

        if ($host) {
            $homeHost = parse_url((string)($conf['homeurl'] ?? ''), PHP_URL_HOST);
            if ($homeHost && strcasecmp((string)$host, (string)$homeHost) !== 0) return $raw;
        } elseif (str_starts_with($raw, '//')) {
            return $raw;
        }

        if ($path === '' || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $path)) return $raw;

        $full = $this->getRootPath().'/'.$path;
        if (is_file($full)) return $path;

        if (preg_match('#^(uploads/[^/]+)/([^/]+)$#', $path, $m)) {
            $thumb = $m[1].'/thumb/'.$m[2];
            if (is_file($this->getRootPath().'/'.$thumb)) return $thumb;
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

    # Escape non-stash text before inline BB/markdown processing by splitting on stash tokens and escaping only the literal parts
    private function filterText(string $s): string {
        $pat   = '/(\x02'.preg_quote($this->salt, '/').':\d+\x03)/';
        $parts = preg_split($pat, $s, -1, PREG_SPLIT_DELIM_CAPTURE) ?? [$s];
        return implode('', array_map(fn($p) => preg_match($pat, $p) ? $p : $this->filterEsc($p), $parts));
    }

    # Validate a link URL: data:/javascript:/vbscript: never pass, in any mode; safe mode also allows only http/https/mailto/relative, trusted content keeps its href
    private function filterUrl(string $url): string {
        $url = trim($url);
        $bare = preg_replace('#[\s\x00-\x1f]+#', '', $url) ?? $url;
        if (preg_match('#^(?:data|javascript|vbscript):#i', $bare)) return '#';
        if (!$this->safe) return $url;
        return preg_match('/^(?:https?:\/\/|mailto:|[\/\.#?])/i', $url) ? $url : '#';
    }

    # Generate a unique heading id: unicode letters and digits are kept (cyrillic included), the rest collapses to hyphens, duplicates get a numeric suffix
    private function getHeadingId(string $raw, int $lvl): string {
        $txt = preg_replace('/\x02'.preg_quote($this->salt, '/').':\d+\x03/', '', $raw);
        $id = preg_replace('/[^\p{L}\p{N}]+/u', '-', strip_tags($txt)) ?? '';
        $id = mb_strtolower(trim($id, '-'), 'UTF-8');
        if ($id === '') $id = 'h'.$lvl;
        $base = $id;
        if (isset($this->hids[$base])) $id = $base.'-'.(++$this->hids[$base]);
        else $this->hids[$base] = 0;
        return $id;
    }

    # Collect consecutive blockquote lines starting with '>' into a flat array, skipping blank separators when another '>' line follows
    private function getBlockquote(array $lines, int $i, int $n): array {
        $bq = [];
        while ($i < $n) {
            $t = ltrim($lines[$i]);
            if (str_starts_with($t, '>')) {
                $bq[] = preg_replace('/^[ \t]*>[ \t]?/', '', $lines[$i++]);
            } elseif (trim($lines[$i]) === '') {
                $j = $i + 1;
                while ($j < $n && trim($lines[$j]) === '') $j++;
                if ($j < $n && str_starts_with(ltrim($lines[$j]), '>')) { $bq[] = ''; $i++; }
                else break;
            } else break;
        }
        return [$bq, $i];
    }

    # Build a <ul>/<ol> from lines starting at $i with indent $ind; supports nested lists, task-list syntax [x]/[ ] and multi-line items
    private function filterList(array $lines, int $i, int $ind): array {
        global $tpl;
        $n   = count($lines);
        $ord = (bool)preg_match('/^\s*\d+\./', $lines[$i]);
        $it  = [];
        $cur = null;
        while ($i < $n) {
            $line = $lines[$i];
            if (trim($line) === '') { if ($cur !== null) $cur .= "\n"; $i++; continue; }
            $sp = strlen($line) - strlen(ltrim($line));
            if ($sp === $ind && preg_match('/^[ \t]*(?:[*+\-]|\d+\.)\s+(.*)$/', $line, $m)) {
                if ($cur !== null) $it[] = $cur;
                $cur = $m[1]; $i++;
            } elseif ($sp > $ind) {
                $cur .= "\n".$line; $i++;
            } else break;
        }
        if ($cur !== null) $it[] = $cur;
        $rows = '';
        foreach ($it as $item) {
            $item = trim($item);
            if (preg_match('/^\[(x| )\]\s+(.*)/si', $item, $tm)) {
                $chk = $tm[1] === 'x' ? ' checked' : '';
                $lbl = trim($tm[2]);
                $lbl = str_contains($lbl, "\n") ? $this->filterBlocks($lbl) : $this->filterInline($lbl);
                $lbl = $tpl->getHtmlFrag('checkbox', ['input_attr' => 'disabled'.$chk]).' '.$lbl;
            } elseif (str_contains($item, "\n")) {
                $lbl = $this->filterBlocks($item);
            } else {
                $lbl = $this->filterInline($item);
            }
            $rows .= "\n".$this->getPartHtml('list-item', ['content_html' => $lbl]);
        }
        return [$this->getPartHtml('list', ['is_unordered' => !$ord, 'is_classless' => true, 'items_html' => $rows."\n"])."\n", $i];
    }

    # Build a table from the header row, the separator on the next line and the body rows
    private function filterTable(array $lines, int $i): array {
        $heads = array_map('trim', explode('|', trim($lines[$i],   " |\t")));
        $seps  = array_map('trim', explode('|', trim($lines[$i+1], " |\t")));
        $cols  = max(count($heads), count($seps));
        $al    = array_map(fn($a) =>
            preg_match('/^:-+:$/', $a) ? 'center' :
           (preg_match('/^-+:$/', $a)  ? 'right'  :
           (preg_match('/^:-+$/', $a)  ? 'left'   : '')),
            $seps
        );
        $i += 2;
        $head = [];
        foreach (array_pad($heads, $cols, '') as $j => $h) {
            $head[] = ['align' => $al[$j] ?? '', 'content_html' => $this->filterInline($h)];
        }
        $rows = [];
        while (isset($lines[$i]) && str_contains($lines[$i], '|') && trim($lines[$i]) !== '') {
            $cells = array_map('trim', explode('|', trim($lines[$i], " |\t")));
            $cell = [];
            foreach (array_pad($cells, $cols, '') as $j => $c) {
                $cell[] = ['align' => $al[$j] ?? '', 'content_html' => $this->filterInline($c)];
            }
            $rows[] = ['cells' => $cell];
            $i++;
        }
        return [$this->getPartHtml('parser-table', ['head' => $head, 'rows' => $rows])."\n", $i];
    }

    # Process BB block tags: bracket-free *NN smilies first, then behind the [ guard: [hr], [li], [usehtml], [usephp], [tabs], [code], [php], [quote]/[hide]/alignment, [attach=]
    # Both trusted tags only act in trusted rendering mode; the right to author them belongs to the super administrator alone and is settled by filterTrustedTags() at every write
    private function filterBbBlocks(string $src): string {
        if (preg_match('/(?<!\*)\*(0[1-9]|1[0-8])(?!\d)/', $src)) {
            $src = preg_replace_callback(
                '/(?<!\*)\*(0[1-9]|1[0-8])(?!\d)/',
                function(array $m): string {
                    $num = $this->filterEsc($m[1]);
                    $img = getThemeImagePath('smilies/'.$num.'.gif');
                    $ttl = _SMILIE.' - '.$num;
                    return $this->addStash($this->getParserImage($img, $ttl, $ttl));
                },
                $src
            ) ?? $src;
        }

        if (!str_contains($src, '[')) return $src;

        $src = preg_replace('/\[hr\]/si', $this->addStash($this->getPartHtml('parser-block', ['is_rule' => true])), $src) ?? $src;
        $src = preg_replace('/\[li\]/si', $this->addStash('&bull; '), $src) ?? $src;

        $src = preg_replace_callback(
            '/\[usehtml\](.*?)\[\/usehtml\]/si',
            function(array $m): string {
                if ($this->safe) return $m[0];
                $html = htmlspecialchars_decode(replace_break($m[1]), ENT_QUOTES);
                return $this->addStash($html);
            },
            $src
        ) ?? $src;

        $src = preg_replace_callback(
            '/\[usephp\](.*?)\[\/usephp\]/si',
            function(array $m): string {
                if ($this->safe) return $m[0];
                $rep = str_replace(['&#036;', '&#092;'], ['$', '\\'], $m[1]);
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

        $src = preg_replace_callback(
            '/\[code\](.*?)\[\/code\]/si',
            function(array $m): string {
                $txt  = str_replace('?', '&#063;', getDecodedText((string)$m[1]));
                $html = $this->getPartHtml('div', ['is_code' => true, 'title' => _CODE, 'content_html' => $this->filterEsc($txt)], true);
                return $this->addStash($html);
            },
            $src
        ) ?? $src;

        $src = preg_replace_callback(
            '/\[code=(.*?)\](.*?)\[\/code\]/si',
            fn(array $m): string => $this->addStash((string)encode_php([0 => $m[0], 1 => $m[1], 2 => $m[2]])),
            $src
        ) ?? $src;

        $src = preg_replace_callback(
            '/\[php\](.*?)\[\/php\]/si',
            fn(array $m): string => $this->addStash((string)encode_php([0 => $m[0], 1 => $m[1]])),
            $src
        ) ?? $src;

        while (preg_match('/\[quote\](.*?)\[\/quote\]/si', $src)) {
            $src = preg_replace_callback(
                '/\[quote\](.*?)\[\/quote\]/si',
                function(array $m): string {
                    $txt = $this->filterNest($m[1]);
                    return $this->addStash($this->getQuoteHtml(['is_quote' => true, 'content_html' => $txt, 'title_text' => _QUOTE]));
                },
                $src
            ) ?? $src;
        }

        while (preg_match('/\[hide\](.*?)\[\/hide\]/si', $src)) {
            $src = preg_replace_callback(
                '/\[hide\](.*?)\[\/hide\]/si',
                function(array $m): string {
                    $show = (defined('ADMIN_FILE') || is_user());
                    $this->vary = true;
                    $txt = $show ? $this->filterNest($m[1]) : (string) _HIDETEXT;
                    return $this->addStash($this->getQuoteHtml(['is_hide' => true, 'content_html' => $txt, 'title_text' => _HIDE]));
                },
                $src
            ) ?? $src;
        }

        while (preg_match('/\[(left|right|center|justify)\](.*?)\[\/\1\]/si', $src)) {
            $src = preg_replace_callback(
                '/\[(left|right|center|justify)\](.*?)\[\/\1\]/si',
                fn(array $m): string => $this->addStash($this->getPartHtml('parser-block', ['is_align' => true, 'align' => strtolower($m[1]), 'content_html' => $this->filterNest($m[2])])),
                $src
            ) ?? $src;
        }

        if (stripos($src, '[attach=') !== false) $src = $this->filterAttach($src);

        return $src;
    }

    # Resolve [attach=file align=X title=Y ...] to image or file link HTML with per-request file probe memoization and atomic thumb regeneration
    # An attachment is resolved against the upload directory, so like an image it renders what the filesystem holds right now and the result is never stored
    private function filterAttach(string $src): string {
        global $conf;
        $mod = $this->mod !== '' ? $this->mod : 'all';
        if (stripos($src, 'rel=') !== false && stripos($src, 'width=') !== false) {
            $re = '/\[attach=([a-zA-Z0-9_\-\. ]+) align=([a-zA-Z]+) title=([\pL0-9_\-\.\"\s]+) width=([0-5]?[0-9]?[0-9]+) height=([0-5]?[0-9]?[0-9]+) rel=([a-zA-Z0-9_\-]+)\]/siu';
        } elseif (stripos($src, 'width=') !== false) {
            $re = '/\[attach=([a-zA-Z0-9_\-\. ]+) align=([a-zA-Z]+) title=([\pL0-9_\-\.\"\s]+) width=([0-5]?[0-9]?[0-9]+) height=([0-5]?[0-9]?[0-9]+)\]/siu';
        } else {
            $re = '/\[attach=([a-zA-Z0-9_\-\. ]+) align=([a-zA-Z]+) title=([\pL0-9_\-\.\"\s]+)\]/siu';
        }
        if (!preg_match_all($re, $src, $mm, PREG_SET_ORDER)) return $src;
        $this->vary = true;
        static $fex = [];
        static $isz = [];
        $twd = getUploadRuleData($mod)['thumbwidth'] ?: ($conf['uploads']['width'] ?? '250');
        $img = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif'];
        foreach ($mm as $m) {
            $fn  = (string)$m[1];
            $al  = (string)$m[2];
            $tl  = (string)$m[3];
            $wd  = $m[4] ?? '';
            $hg  = $m[5] ?? '';
            $rl  = $m[6] ?? '';
            $ext = strtolower((string)substr((string)strrchr($fn, '.'), 1));
            $file = 'uploads/'.$mod.'/'.$fn;
            $path = BASE_DIR.'/'.ltrim(str_replace('\\', '/', $file), '/');
            $timg = $file;
            if ($tl === '' || strtolower($tl) === 'title') $tl = $fn;
            if (in_array($ext, $img, true)) {
                $tfile = 'uploads/'.$mod.'/thumb/'.$fn;
                $tpath = BASE_DIR.'/'.ltrim(str_replace('\\', '/', $tfile), '/');
                $tdir  = UPLOADS_DIR.'/'.$mod.'/thumb';
                if ($mod !== '' && ($fex[$path] ??= file_exists($path)) && !($fex[$tpath] ??= file_exists($tpath))) {
                    if (!file_exists($tdir)) mkdir($tdir, 0777, true);
                    $tmp = $tpath.'.'.getmypid().'.tmp';
                    if (getImageThumb($path, $tmp, $twd) === $tmp && is_file($tmp) && rename($tmp, $tpath)) $fex[$tpath] = true;
                    elseif (is_file($tmp)) unlink($tmp);
                }
                if ($fex[$tpath] ?? false) $timg = $tfile;
                if ($fex[$path] ??= file_exists($path)) {
                    $isz[$path] ??= getimagesize($path);
                    [$wd, $hg] = $isz[$path];
                } else {
                    $src = str_replace($m[0], $this->addStash($this->getParserImage(null, $tl, $tl)), $src);
                    continue;
                }
            }
            $tmp = (string)($conf['filetype'][$ext] ?? '');
            if ($tmp === '') {
                $src = str_replace($m[0], $this->addStash($this->getPartLink($file, $tl, $tl, true)), $src);
                continue;
            }
            $tmp = str_replace('[src]',    $file, $tmp);
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

    # Protect code from parsing in order fenced → indented (safe mode only) → inline; unclosed fences and backticks stay as-is
    # This runs before the bracket layer, so a BB tag written inside code stays the text the author typed instead of being executed as markup
    private function filterCode(string $src): string {
        $src = preg_replace_callback(
            '/(^(`{3,}|~{3,})[ \t]*([\w\-]*)[^\n]*\n(.*?)\n^\2[ \t]*$)/ms',
            function(array $m): string {
                return $this->addStash($this->getPartHtml('code-highlight', ['lang' => $m[3], 'code_html' => $this->filterEsc($m[4])]));
            },
            $src
        ) ?? $src;

        if ($this->safe) {
            $src = preg_replace_callback(
                '/(?:^(?:    |\t).+\n?)+/m',
                fn(array $m): string => $this->addStash(
                    $this->getPartHtml('code-highlight', ['code_html' => $this->filterEsc(preg_replace('/^(?:    |\t)/m', '', rtrim($m[0])))])
                )."\n",
                $src
            ) ?? $src;
        }

        $src = preg_replace_callback(
            '/``(.+?)``|`([^`\n]+)`/s',
            function(array $m): string {
                $txt = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');
                return $this->addStash($this->getPartHtml('parser-inline', ['is_code' => true, 'content_html' => $this->filterEsc($txt)]));
            },
            $src
        ) ?? $src;

        return $src;
    }

    # Convert block-level Markdown to HTML; each element is stashed so the top-level filterSafe() never escapes parser output, raw HTML blocks only pass when safe=false
    private function filterBlocks(string $src): string {
        $lines = explode("\n", $src);
        $n     = count($lines);
        $pat   = '/^\x02'.preg_quote($this->salt, '/').':\d+\x03$/';
        $out   = '';
        $i     = 0;

        while ($i < $n) {
            $line = $lines[$i];
            $trim = ltrim($line);

            if (preg_match($pat, trim($line))) { $out .= $line."\n"; $i++; continue; }
            if ($trim === '') { $out .= "\n"; $i++; continue; }

            if (preg_match('/^(#{1,6})\s+(.*?)(?:\s+#+)?$/', $trim, $m)) {
                $lvl  = min(6, strlen($m[1]) + $this->hoff);
                $id   = $this->getHeadingId($m[2], $lvl);
                $out .= $this->addStash($this->getPartHtml('parser-block', ['is_head' => true, 'level' => $lvl, 'id' => $id, 'content_html' => $this->filterInline($m[2])]))."\n";
                $i++; continue;
            }

            if (preg_match('/^(?:\*{3,}|-{3,}|_{3,})\s*$/', $trim)) {
                $out .= $this->addStash($this->getPartHtml('parser-block', ['is_rule' => true]))."\n"; $i++; continue;
            }

            if (str_starts_with($trim, '>')) {
                [$bq, $i] = $this->getBlockquote($lines, $i, $n);
                $segs = [[]];
                foreach ($bq as $ln) {
                    if ($ln === '' && end($segs) !== []) $segs[] = [];
                    elseif ($ln !== '') $segs[count($segs) - 1][] = $ln;
                }
                foreach ($segs as $seg) {
                    if ($seg === []) continue;
                    $hd = trim($seg[0]);
                    if (preg_match('/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]$/i', $hd, $cm)) {
                        $kind = strtolower($cm[1]);
                        $tone = match ($kind) {
                            'tip' => 'success',
                            'important' => 'accent',
                            'warning' => 'warn',
                            'caution' => 'error',
                            default => 'info',
                        };
                        array_shift($seg);
                        $inner = $this->filterBlocks(implode("\n", $seg));
                        $out .= $this->addStash($this->getQuoteHtml(['is_callout' => true, 'content_html' => $inner, 'callout_type' => $tone]))."\n";
                    } else {
                        $inner = $this->filterBlocks(implode("\n", $seg));
                        $out .= $this->addStash($this->getQuoteHtml(['is_plain' => true, 'content_html' => $inner]))."\n";
                    }
                }
                continue;
            }

            if (preg_match('/^([ \t]*)([*+\-]|\d+\.)\s+/', $line, $m)) {
                [$html, $i] = $this->filterList($lines, $i, strlen($m[1]));
                $out .= $this->addStash($html); continue;
            }

            if (isset($lines[$i + 1]) && str_contains($trim, '|')
                && preg_match('/^\|?[ \t]*:?-{2,}:?[ \t]*(?:\|[ \t]*:?-{2,}:?[ \t]*)+\|?$/', $lines[$i + 1])
            ) {
                [$html, $i] = $this->filterTable($lines, $i);
                $out .= $this->addStash($html); continue;
            }

            if (isset($lines[$i + 1]) && $trim !== '') {
                if (preg_match('/^=+\s*$/', $lines[$i + 1])) {
                    $lvl  = min(6, 1 + $this->hoff);
                    $id   = $this->getHeadingId($trim, $lvl);
                    $out .= $this->addStash($this->getPartHtml('parser-block', ['is_head' => true, 'level' => $lvl, 'id' => $id, 'content_html' => $this->filterInline($trim)]))."\n";
                    $i += 2; continue;
                }
                if (preg_match('/^-+\s*$/', $lines[$i + 1]) && !preg_match('/^[*+\-]\s/', $trim)) {
                    $lvl  = min(6, 2 + $this->hoff);
                    $id   = $this->getHeadingId($trim, $lvl);
                    $out .= $this->addStash($this->getPartHtml('parser-block', ['is_head' => true, 'level' => $lvl, 'id' => $id, 'content_html' => $this->filterInline($trim)]))."\n";
                    $i += 2; continue;
                }
            }

            if (!$this->safe && preg_match('/^<\/?[a-zA-Z]/', $trim)) {
                $raw = '';
                while ($i < $n && trim($lines[$i]) !== '') { $raw .= $lines[$i++]."\n"; }
                $map = [];
                foreach ($this->stash as $k => $v) { $map["\x02{$k}\x03"] = $v; }
                $raw = strtr($raw, $map);
                $out .= $this->addStash(str_replace(['&#034;', '&#039;'], ['"', "'"], $raw));
                continue;
            }

            $para = [];
            while ($i < $n && trim($lines[$i]) !== ''
                && !preg_match('/^#{1,6}\s|^(?:\*{3,}|-{3,}|_{3,})\s*$/', ltrim($lines[$i]))
                && !preg_match('/^[ \t]*(?:[*+\-]|1\.)\s+/', $lines[$i])
            ) {
                $para[] = $lines[$i++];
            }
            $out .= $this->addStash($this->getPartHtml('parser-block', ['is_para' => true, 'content_html' => $this->filterInline(implode("\n", $para))]))."\n";
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

    # Inline entry point: pre-escape user text via filterText() in safe mode, then run the inline pipeline
    private function filterInline(string $src): string {
        return $this->filterInlines($this->safe ? $this->filterText($src) : $src);
    }

    # Inline pipeline: bracket tags → auto-links + safe HTML escape → markdown emphasis; char guards skip groups, the safe check precedes emphasis so its HTML survives
    private function filterInlines(string $src): string {
        if (str_contains($src, '[')) $src = $this->filterBbInline($src);

        if (str_contains($src, '<')) {
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

            if ($this->safe) {
                $src = preg_replace_callback('/<[^>]+>/', fn(array $m): string => $this->filterEsc($m[0]), $src) ?? $src;
            }
        }

        if ($this->fmt !== 'plain' && strpbrk($src, '*_~=') !== false) {
            $src = preg_replace(['/\*{3}(.+?)\*{3}/s', '/_{3}(.+?)_{3}/s'], $this->getPartTag(['is_strong_em' => true, 'content_html' => '$1']), $src);
            $src = preg_replace(['/\*{2}(.+?)\*{2}/s', '/_{2}(.+?)_{2}/s'], $this->getPartTag(['is_strong' => true, 'content_html' => '$1']), $src);
            $src = preg_replace(['/\*([^*\n]+)\*/', '/(?<![_\w])_([^_\n]+)_(?![_\w])/'], $this->getPartTag(['is_em' => true, 'content_html' => '$1']), $src);
            $src = preg_replace('/~~(.+?)~~/s', $this->getPartTag(['is_del' => true, 'content_html' => '$1']), $src);
            $src = preg_replace('/==(.+?)==/s', $this->getPartTag(['is_mark' => true, 'content_html' => '$1']), $src);
        }
        if (str_contains($src, "\n")) {
            $brk = $this->getPartTag(['is_break' => true])."\n";
            $src = preg_replace(['/  \n/', '/\\\\\n/'], $brk, $src);
            if ($this->fmt === 'breaks') $src = preg_replace('/(?<!<br>)\n/', $brk, $src) ?? $src;
        }

        return $src;
    }

    # Bracket inline tags: ed2k before generic [url], then BB pairs and markdown links/images; stashed tags bypass the safe check, non-stashed ([b], [color]) get escaped
    # A plain body returns before the markdown pair, because [t](u) is Markdown syntax and means nothing in the format the author wrote in
    private function filterBbInline(string $src): string {
        $src = preg_replace_callback(
            '/\[url\](ed2k:\/\/\|file\|(.*?)\|\d+\|\w+\|(h=\w+\|)?\/?)\[\/url\]/si',
            fn(array $m): string => $this->getEdLink($m[1], $m[2]),
            $src
        ) ?? $src;

        $src = preg_replace_callback(
            '/\[url=(ed2k:\/\/\|file\|(.*?)\|\d+\|\w+\|(h=\w+\|)?\/?)\](.*?)\[\/url\]/si',
            fn(array $m): string => $this->getEdLink($m[1], $m[2], (string)$m[4]),
            $src
        ) ?? $src;

        foreach (['b' => 'is_strong', 'i' => 'is_em', 'u' => 'is_under', 's' => 'is_del'] as $tag => $flag) {
            if (stripos($src, '['.$tag.']') === false) continue;
            [$open, $shut] = $this->getPartPair([$flag => true]);
            $beg = $this->addStash($open);
            $end = $this->addStash($shut);
            for ($i = 0; $i < 3; $i++) {
                $prev = $src;
                $src = preg_replace('/\['.$tag.'\](.*?)\[\/'.$tag.'\]/si', $beg.'$1'.$end, $src) ?? $src;
                if ($prev === $src) break;
            }
        }

        $src = preg_replace_callback(
            '/\[color=([^\]]+)\](.*?)\[\/color\]/si',
            function(array $m): string {
                $color = strtolower(trim($m[1]));
                if (!preg_match('/^#[0-9a-f]{6}$/', $color) && !preg_match('/^[a-z]+$/', $color)) return $m[2];
                [$open, $shut] = $this->getPartPair(['is_color' => true, 'value' => $color]);
                return $this->addStash($open).$m[2].$this->addStash($shut);
            },
            $src
        ) ?? $src;

        $src = preg_replace_callback(
            '/\[family=([A-Za-z ]+)\](.*?)\[\/family\]/si',
            function(array $m): string {
                [$open, $shut] = $this->getPartPair(['is_family' => true, 'value' => trim($m[1])]);
                return $this->addStash($open).$m[2].$this->addStash($shut);
            },
            $src
        ) ?? $src;

        $src = preg_replace_callback(
            '/\[size=([0-9]{1,2})\](.*?)\[\/size\]/si',
            function(array $m): string {
                $size = max(8, min(48, (int)$m[1]));
                [$open, $shut] = $this->getPartPair(['is_size' => true, 'value' => $size]);
                return $this->addStash($open).$m[2].$this->addStash($shut);
            },
            $src
        ) ?? $src;

        $src = preg_replace_callback(
            '/\[mail\](.*?)\[\/mail\]/si',
            fn(array $m): string => $this->getBbMail($m[1]) ?? $m[1],
            $src
        ) ?? $src;

        $src = preg_replace_callback(
            '/\[mail\s*=\s*([^\]]+)\](.*?)\[\/mail\]/si',
            fn(array $m): string => $this->getBbMail($m[1], $m[2]) ?? $m[2],
            $src
        ) ?? $src;

        $src = preg_replace_callback(
            '/\[url\](.*?)\[\/url\]/si',
            fn(array $m): string => $this->getBbLink($m[1]),
            $src
        ) ?? $src;

        $src = preg_replace_callback(
            '/\[url=([^\]]+)\](.*?)\[\/url\]/si',
            fn(array $m): string => $this->getBbLink($m[1], $m[2]),
            $src
        ) ?? $src;

        $src = preg_replace_callback(
            '/\[img\](.*?)\[\/img\]/si',
            fn(array $m): string => $this->getBbImage($m[1]),
            $src
        ) ?? $src;

        $src = preg_replace_callback(
            '/\[img=([a-zA-Z]+)\](.*?)\[\/img\]/si',
            fn(array $m): string => $this->getBbImage($m[2], '', $m[1]),
            $src
        ) ?? $src;

        $src = preg_replace_callback(
            '/\[img\s+alt=([\pL0-9_\-\.\"\s]+)\](.*?)\[\/img\]/siu',
            fn(array $m): string => $this->getBbImage($m[2], $m[1]),
            $src
        ) ?? $src;

        $src = preg_replace_callback(
            '/\[img=([a-zA-Z]+)\s+alt=([\pL0-9_\-\.\"\s]+)\](.*?)\[\/img\]/siu',
            fn(array $m): string => $this->getBbImage($m[3], $m[2], $m[1]),
            $src
        ) ?? $src;

        if ($this->fmt === 'plain') return $src;
        $src = preg_replace_callback(
            '/!\[([^\]]*)\]\(([^\s)]+)(?:\s+(?:"|&quot;)(.*?)(?:"|&quot;))?\)/',
            function(array $m): string {
                $raw  = $this->filterDec($m[2]);
                $url  = stripos(trim($raw), 'data:') === 0 ? $raw : $this->filterUrl($raw);
                $path = parse_url($raw, PHP_URL_PATH);
                $path = is_string($path) && $path !== '' ? $path : $raw;
                $file = stripos($raw, 'data:') === 0 ? 'image' : (basename(rawurldecode($path)) ?: 'image');
                $alt  = trim($this->filterDec($m[1]));
                $alt  = ($alt === '' || strtolower($alt) === 'title' || strtolower($alt) === 'alt') ? $file : $alt;
                $ttl  = isset($m[3]) ? trim($this->filterDec($m[3])) : '';
                $url  = $this->normalizeImageSource($url);
                $ttl  = ($ttl === '' || strtolower($ttl) === 'title' || strtolower($ttl) === 'alt') ? $file : $ttl;
                return $this->addStash($this->getParserImage($url, $alt, $ttl));
            },
            $src
        ) ?? $src;

        $src = preg_replace_callback(
            '/\[([^\]]+)\]\(([^\s)]+)(?:\s+(?:"|&quot;)(.*?)(?:"|&quot;))?\)/',
            function(array $m): string {
                $href = $this->filterUrl($this->filterDec($m[2]));
                $ttl  = isset($m[3]) ? $this->filterDec($m[3]) : '';
                return $this->addStash($this->getPartLink($href, $m[1], $ttl, false, true));
            },
            $src
        ) ?? $src;

        return $src;
    }

    # Shared BB [img] renderer: www prefix, safe URL policy, alt keywords (title/alt/empty) fall back to the file name, align validates to left/right (else left, empty stays empty)
    private function getBbImage(string $url, string $alt = '', string $align = ''): string {
        $url = trim($this->filterDec($url));
        if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
        $link = stripos($url, 'data:') === 0 ? $url : $this->filterUrl($url);
        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : $url;
        $file = stripos($url, 'data:') === 0 ? 'image' : (basename(rawurldecode($path)) ?: 'image');
        $alt = trim($this->filterDec($alt));
        $alt = ($alt === '' || strtolower($alt) === 'title' || strtolower($alt) === 'alt') ? $file : $alt;
        $align = strtolower(trim($align));
        if ($align !== '' && !in_array($align, ['left', 'right'], true)) $align = 'left';
        return $this->addStash($this->getParserImage($this->normalizeImageSource($link), $alt, $alt, $align, true));
    }

    # Shared BB [url] renderer: bare www links get https, safe mode applies the URL policy; the bare form labels with the escaped url
    private function getBbLink(string $url, ?string $lbl = null): string {
        $url = trim($this->filterDec($url));
        if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
        $href = $this->filterUrl($url);
        return $this->addStash($lbl === null ? $this->getPartLink($href, $url) : $this->getPartLink($href, $lbl, '', false, true));
    }

    # Shared BB [mail] renderer: returns a stashed mailto anchor or null when the address fails validation
    private function getBbMail(string $mail, ?string $lbl = null): ?string {
        $mail = trim($this->filterDec($mail));
        if (!preg_match('/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $mail)) return null;
        return $this->addStash($lbl === null ? $this->getPartLink('mailto:'.$mail, $mail) : $this->getPartLink('mailto:'.$mail, $lbl, '', false, true));
    }

    # Shared ed2k renderer: escapes url and file name; the bare form is prefixed and labeled with the file name
    private function getEdLink(string $url, string $name, ?string $lbl = null): string {
        $url = $this->filterDec($url);
        $ttl = $this->filterDec($name);
        $tag = ($lbl === null) ? $this->getPartLink($url, $ttl, $ttl, true) : $this->getPartLink($url, $lbl, $ttl, true, true);
        return $this->addStash(($lbl === null ? 'eMule/eDonkey: ' : '').$tag);
    }

    # Escape remaining HTML tags in safe mode; unsafe mode is a no-op because block HTML is stashed and inline HTML was already handled
    private function filterSafe(string $src): string {
        if (!$this->safe) return $src;
        return preg_replace_callback(
            '/<[^>]+>/',
            fn(array $m): string => htmlspecialchars($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $src
        ) ?? $src;
    }
}
