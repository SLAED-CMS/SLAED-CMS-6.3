<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Markdown + BB-code parser; converts user/admin content to safe HTML.
# Entry point: filterDoc(). Exception to the 8-char class name rule (like Editor).
# Pipeline: filterBbBlocks → filterCode → filterBlocks (stashes output) → filterSafe → filterStash.
# filterInline() applies filterText() pre-escape + mid-inline safe check (before markdown),
# so block wrappers from filterBlocks() must be stashed to survive the top-level filterSafe().
class Parser {

    # Shared per-request parse cache keyed by md5(src + safe + mod)
    private static array $pcache = [];

    # Stash maps "{salt}:{n}" → protected HTML fragment
    private array $stash = [];

    # Random salt isolates tokens across parallel parse calls
    private string $salt = '';

    # Monotonic counter for stash slot allocation
    private int $scnt = 0;

    # Safe mode: true = escape user-injected HTML (user content), false = trust HTML (admin)
    private bool $safe = true;

    # Module context (e.g. 'news', 'forum') — affects attach handler
    private string $mod = '';

    # Heading id deduplication registry for the current parse session
    private array $hids = [];

    # Parse src through the full pipeline and return the resulting HTML.
    # $safe=true for user content, false for admin/trusted content.
    # $mod identifies the module context.
    public function filterDoc(string $src, bool $safe = true, string $mod = ''): string {
        $key = md5($src.(int)$safe.$mod);
        if (isset(self::$pcache[$key])) return self::$pcache[$key];
        $this->stash = [];
        $this->hids  = [];
        $this->salt  = bin2hex(random_bytes(8));
        $this->scnt  = 0;
        $this->safe  = $safe;
        $this->mod   = $mod;
        $src = str_replace(["\r\n", "\r"], "\n", $src);
        $src = $this->filterBbBlocks($src);  # BB-blocks first (stash [quote]/[hide]/[code]/...)
        $src = $this->filterCode($src);       # fenced, indented (safe only), inline code
        $out = $this->filterBlocks($src);     # blocks stashed; filterInline() called per element
        $out = $this->filterSafe($out);       # no-op in practice (block HTML stashed, inline already safe); belt-and-suspenders
        $out = $this->filterStash($out);      # iterative restore
        $out = trim($out);
        return self::$pcache[$key] = $out;
    }

    # Convenience wrapper: filterDoc() + replaceText() for the standard rendering pipeline.
    # Use filterDoc() directly when replacement rules must not apply (e.g. changelog, search).
    public function filterContent(string $src, bool $safe, string $mod): string {
        return $this->normalizeHtmlImages(
            $this->replaceText($this->filterDoc($src, $safe, $mod), $mod)
        );
    }

    # Apply module-specific search-and-replace rules from $conf['replace'][$mod].
    # Stashes existing HTML tags before replacement to avoid corrupting markup.
    private function replaceText(string $src, string $mod): string {
        global $conf;
        $rules = ($mod && isset($conf['replace'][$mod])) ? $conf['replace'][$mod] : '';
        if ($rules) {
            $rules = explode('||', $rules);
            foreach ($rules as $word) {
                if ($word != '') {
                    $warray = explode('|', $word);
                    if ($warray[0]) {
                        preg_match_all('#<[^>]*>#', $src, $tags);
                        $taglist = [];
                        $k = 0;
                        foreach ($tags[0] as $i) {
                            $k++;
                            $taglist[$k] = $i;
                            $src = str_replace($i, '<'.$k.'>', $src);
                        }
                        $src = preg_replace('#'.$warray[0].'#i', $warray[1], $src);
                        foreach ($taglist as $k => $i) $src = str_replace('<'.$k.'>', $i, $src);
                    }
                }
            }
        }
        return $src;
    }

    # Store a protected fragment and return its stash token.
    private function addStash(string $val): string {
        $tok = "\x02{$this->salt}:{$this->scnt}\x03";
        $this->stash["{$this->salt}:{$this->scnt}"] = $val;
        $this->scnt++;
        return $tok;
    }

    # Restore all stash tokens iteratively to handle nested fragments.
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

    # Full re-parse of nested block content for [quote]/[hide] etc.
    # Reuses $this->stash and $this->salt without reset — tokens are compatible.
    # Does NOT call filterStash() or trim() — those are top-level only.
    private function filterNest(string $src): string {
        $src = $this->filterBbBlocks($src);
        $src = $this->filterCode($src);
        $src = $this->filterBlocks($src);
        $src = $this->filterSafe($src);
        return $src;
    }

    # Escape HTML special characters.
    private function filterEsc(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    # Decode HTML entities.
    private function filterDec(string $s): string {
        return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    # Public web root for resolving relative asset paths.
    private function getRootPath(): string {
        static $root = '';
        if ($root === '') $root = dirname(__DIR__, 2);
        return $root;
    }

    # Theme-aware local placeholder with cross-theme fallback.
    private function getFallbackImage(): string {
        static $fallback = '';
        if ($fallback !== '') return $fallback;
        $candidates = [
            img_find('misc/no-image.png'),
            img_find('misc/loading.gif'),
            'templates/default/images/misc/no-image.png',
            'templates/default/images/misc/loading.gif',
            'templates/lite/images/misc/no-image.png',
            'templates/lite/images/misc/loading.gif',
            'templates/admin/images/misc/no-image.png',
            'templates/admin/images/misc/loading.gif',
        ];
        foreach ($candidates as $candidate) {
            if (is_file($this->getRootPath().'/'.ltrim($candidate, '/'))) {
                return $fallback = $candidate;
            }
        }
        return $fallback = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
    }

    # Build the resilient onerror fallback used across parser-generated images.
    private function buildImageError(string $file): string {
        return ' onerror="this.onerror=null;this.src=\''.$this->getFallbackImage()
            .'\';this.alt=\''.$file.'\';this.title=\''.$file.'\'"';
    }

    # Convert a local/absolute image source into a stable public path.
    private function normalizeImageSource(string $src): string {
        global $conf;
        $raw = trim($this->filterDec($src));
        if ($raw === '' || str_starts_with($raw, 'data:') || str_starts_with($raw, '#')) return $raw;

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

        return $this->getFallbackImage();
    }

    # Repair persisted raw HTML img tags so broken local sources do not emit frontend 404 placeholders.
    private function normalizeHtmlImages(string $src): string {
        return preg_replace_callback(
            '#<img\b[^>]*>#i',
            function(array $m): string {
                $tag = $m[0];
                if (!preg_match('#\bsrc\s*=\s*(["\'])(.*?)\1#i', $tag, $sm)) return $tag;
                $file = $this->filterEsc(
                    basename(rawurldecode((string)(parse_url($sm[2], PHP_URL_PATH) ?: $sm[2]))) ?: 'image'
                );
                $resolved = $this->filterEsc($this->normalizeImageSource($sm[2]));
                $tag = preg_replace('#\bsrc\s*=\s*(["\']).*?\1#i', 'src="'.$resolved.'"', $tag, 1) ?? $tag;
                if (!preg_match('#\bonerror\s*=#i', $tag)) {
                    $tag = rtrim($tag, ' >').$this->buildImageError($file).'>';
                }
                return $tag;
            },
            $src
        ) ?? $src;
    }

    # Escape non-stash portions of text before inline BB/markdown processing.
    # Splits on stash tokens, escapes only the literal text parts.
    private function filterText(string $s): string {
        $pat   = '/(\x02'.preg_quote($this->salt, '/').':\d+\x03)/';
        $parts = preg_split($pat, $s, -1, PREG_SPLIT_DELIM_CAPTURE) ?? [$s];
        return implode('', array_map(fn($p) => preg_match($pat, $p) ? $p : $this->filterEsc($p), $parts));
    }

    # Validate URL: allow http/https/mailto/relative; everything else → '#'.
    private function filterUrl(string $url): string {
        $url = trim($url);
        return preg_match('/^(?:https?:\/\/|mailto:|[\/\.#?])/i', $url) ? $url : '#';
    }

    # Generate unique id from heading text; deduplicate with numeric suffix.
    private function getHeadingId(string $raw, int $lvl): string {
        $txt  = preg_replace('/\x02'.preg_quote($this->salt, '/').':\d+\x03/', '', $raw);
        $id   = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', strip_tags($txt)), '-'));
        if ($id === '') $id = 'h'.$lvl;
        $base = $id;
        if (isset($this->hids[$base])) $id = $base.'-'.(++$this->hids[$base]);
        else $this->hids[$base] = 0;
        return $id;
    }

    # Collect consecutive blockquote lines (starting with '>') into a flat array.
    # Skips blank separator lines when a '>' line follows.
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

    # Build a list element (<ul>/<ol>) from lines starting at $i with indent $ind.
    # Supports nested lists (deeper indent), task-list syntax [x]/[ ], and multi-line items.
    private function filterList(array $lines, int $i, int $ind): array {
        global $tpl;
        $n   = count($lines);
        $ord = (bool)preg_match('/^\s*\d+\./', $lines[$i]);
        $tag = $ord ? 'ol' : 'ul';
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
        $html = '<'.$tag.">\n";
        foreach ($it as $item) {
            $item = trim($item);
            if (preg_match('/^\[(x| )\]\s+(.*)/si', $item, $tm)) {
                $chk = $tm[1] === 'x' ? ' checked' : '';
                $lbl = trim($tm[2]);
                $lbl = str_contains($lbl, "\n") ? $this->filterBlocks($lbl) : $this->filterInline($lbl);
                $html .= '<li>'.$tpl->getHtmlFrag('checkbox', ['input_attr' => 'disabled'.$chk]).' '.$lbl."</li>\n";
            } elseif (str_contains($item, "\n")) {
                $html .= '<li>'.$this->filterBlocks($item)."</li>\n";
            } else {
                $html .= '<li>'.$this->filterInline($item)."</li>\n";
            }
        }
        return [$html.'</'.$tag.">\n", $i];
    }

    # Build a table from the header row ($lines[$i]), separator ($lines[$i+1]), and body rows.
    private function filterTable(array $lines, int $i): array {
        $heads = array_map('trim', explode('|', trim($lines[$i],   " |\t")));
        $seps  = array_map('trim', explode('|', trim($lines[$i+1], " |\t")));
        $cols  = max(count($heads), count($seps));
        $al    = array_map(fn($a) =>
            preg_match('/^:-+:$/', $a) ? ' style="text-align:center"' :
           (preg_match('/^-+:$/', $a)  ? ' style="text-align:right"'  :
           (preg_match('/^:-+$/', $a)  ? ' style="text-align:left"'   : '')),
            $seps
        );
        $i += 2;
        $html = "<table>\n<thead>\n<tr>";
        foreach (array_pad($heads, $cols, '') as $j => $h) {
            $html .= '<th'.($al[$j] ?? '').'>'.$this->filterInline($h).'</th>';
        }
        $html .= "</tr>\n</thead>\n<tbody>\n";
        while (isset($lines[$i]) && str_contains($lines[$i], '|') && trim($lines[$i]) !== '') {
            $cells = array_map('trim', explode('|', trim($lines[$i], " |\t")));
            $html .= '<tr>';
            foreach (array_pad($cells, $cols, '') as $j => $c) {
                $html .= '<td'.($al[$j] ?? '').'>'.$this->filterInline($c).'</td>';
            }
            $html .= "</tr>\n"; $i++;
        }
        return [$html."</tbody>\n</table>\n", $i];
    }

    # Process BB block-level tags: [hr], [li], smilies, [usehtml], [usephp],
    # [tabs=N][tab=T]...[/tabs], [code], [code=lang], [php],
    # [quote] and [hide] (recursive via filterNest()), [attach=...].
    private function filterBbBlocks(string $src): string {
        $src = preg_replace('/\[hr\]/si', $this->addStash('<hr>'), $src) ?? $src;
        $src = preg_replace('/\[li\]/si', $this->addStash('&bull; '), $src) ?? $src;

        # *NN smilies
        if (preg_match('/\*(\d{2})/', $src)) {
            $src = preg_replace_callback(
                '/\*(\d{2})/',
                function(array $m): string {
                    $num = $this->filterEsc($m[1]);
                    $img = img_find('smilies/'.$num.'.gif');
                    return $this->addStash('<img src="'.$this->filterEsc($img).'" alt="'._SMILIE.' - '.$num.'" title="'._SMILIE.' - '.$num.'">');
                },
                $src
            ) ?? $src;
        }

        # [usehtml]...[/usehtml] — admin-only raw HTML passthrough
        $src = preg_replace_callback(
            '/\[usehtml\](.*?)\[\/usehtml\]/si',
            function(array $m): string {
                if ($this->safe) return $m[0];
                $html = htmlspecialchars_decode(replace_break($m[1]), ENT_QUOTES);
                return $this->addStash($html);
            },
            $src
        ) ?? $src;

        # [usephp]...[/usephp] — admin-only PHP execution
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
                return $this->addStash((string)$out);
            },
            $src
        ) ?? $src;

        # [tabs=N][tab=Title]...[/tab][/tabs]
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

        # [code]...[/code]
        $src = preg_replace_callback(
            '/\[code\](.*?)\[\/code\]/si',
            function(array $m): string {
                $txt  = str_replace('?', '&#063;', (string)$m[1]);
                $html = '<div class="code" title="'.htmlspecialchars(_CODE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">'.$this->filterEsc($txt).'</div>';
                return $this->addStash($html);
            },
            $src
        ) ?? $src;

        # [code=lang]...[/code]
        $src = preg_replace_callback(
            '/\[code=(.*?)\](.*?)\[\/code\]/si',
            fn(array $m): string => $this->addStash((string)encode_php([0 => $m[0], 1 => $m[1], 2 => $m[2]])),
            $src
        ) ?? $src;

        # [php]...[/php]
        $src = preg_replace_callback(
            '/\[php\](.*?)\[\/php\]/si',
            fn(array $m): string => $this->addStash((string)encode_php([0 => $m[0], 1 => $m[1]])),
            $src
        ) ?? $src;

        # [quote]...[/quote] — iterative to handle all nesting levels
        while (preg_match('/\[quote\](.*?)\[\/quote\]/si', $src)) {
            $src = preg_replace_callback(
                '/\[quote\](.*?)\[\/quote\]/si',
                function(array $m): string {
                    $txt  = $this->filterNest($m[1]);
                    $html = '<blockquote><p title="'.htmlspecialchars(_QUOTE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">'.$txt.'</p></blockquote>';
                    return $this->addStash($html);
                },
                $src
            ) ?? $src;
        }

        # [hide]...[/hide] — iterative to handle all nesting levels
        while (preg_match('/\[hide\](.*?)\[\/hide\]/si', $src)) {
            $src = preg_replace_callback(
                '/\[hide\](.*?)\[\/hide\]/si',
                function(array $m): string {
                    $show = (defined('ADMIN_FILE') || is_user());
                    $txt  = $show ? $this->filterNest($m[1]) : (string)_HIDETEXT;
                    $html = '<blockquote class="hide"><p title="'.htmlspecialchars(_HIDE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">'.$txt.'</p></blockquote>';
                    return $this->addStash($html);
                },
                $src
            ) ?? $src;
        }

        # [attach=...] — image or link from uploads
        if (stripos($src, '[attach=') !== false) $src = $this->filterAttach($src);

        return $src;
    }

    # Resolve [attach=file align=X title=Y ...] to image or file link HTML.
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
        $con = explode('|', (string)($conf['uploads'][$mod] ?? ''));
        $twd = $con[6] ?? ($conf['uploads']['width'] ?? '250');
        $img = ['png', 'jpg', 'jpeg', 'gif', 'bmp'];
        foreach ($mm as $m) {
            $fn  = (string)$m[1];
            $al  = (string)$m[2];
            $tl  = (string)$m[3];
            $wd  = $m[4] ?? '';
            $hg  = $m[5] ?? '';
            $rl  = $m[6] ?? '';
            $ext = strtolower((string)substr((string)strrchr($fn, '.'), 1));
            $file = 'uploads/'.$mod.'/'.$fn;
            $timg = $file;
            if ($tl === '' || strtolower($tl) === 'title') $tl = $fn;
            if (in_array($ext, $img, true)) {
                $tfile = 'uploads/'.$mod.'/thumb/'.$fn;
                $tdir  = 'uploads/'.$mod.'/thumb';
                if ($mod !== '' && file_exists($file) && !file_exists($tfile)) {
                    if (!file_exists($tdir)) mkdir($tdir);
                    $ok   = create_img_gd($file, $tfile, $twd);
                    $timg = $ok ? $tfile : $file;
                } else {
                    $timg = $tfile;
                }
                if (file_exists($file)) {
                    [$wd, $hg] = getimagesize($file);
                } else {
                    $file = $this->getFallbackImage();
                    $timg = $file;
                }
            }
            $tmp = $conf['filetype'][$ext] ?? '<a href="[src]" target="_blank" title="[title]">[title]</a>';
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

    # Protect code spans and fenced/indented blocks from further parsing.
    # Order: fenced (``` / ~~~) → indented (4 spaces or tab, safe only) → inline (`...`).
    # Unclosed fences/backticks are left as-is.
    private function filterCode(string $src): string {
        # Fenced code blocks with optional language class
        $src = preg_replace_callback(
            '/(^(`{3,}|~{3,})[ \t]*([\w\-]*)[^\n]*\n(.*?)\n^\2[ \t]*$)/ms',
            function(array $m): string {
                $cls = $m[3] ? ' class="language-'.$this->filterEsc($m[3]).'"' : '';
                return $this->addStash('<pre><code'.$cls.'>'.$this->filterEsc($m[4]).'</code></pre>');
            },
            $src
        ) ?? $src;

        # Indented code blocks (4 spaces or tab) — safe mode only
        if ($this->safe) {
            $src = preg_replace_callback(
                '/(?:^(?:    |\t).+\n?)+/m',
                fn(array $m): string => $this->addStash(
                    '<pre><code>'.$this->filterEsc(preg_replace('/^(?:    |\t)/m', '', rtrim($m[0]))).'</code></pre>'
                )."\n",
                $src
            ) ?? $src;
        }

        # Inline code (`...` and ``...``)
        $src = preg_replace_callback(
            '/``(.+?)``|`([^`\n]+)`/s',
            function(array $m): string {
                $txt = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');
                return $this->addStash('<code>'.$this->filterEsc($txt).'</code>');
            },
            $src
        ) ?? $src;

        return $src;
    }

    # Convert block-level Markdown to HTML.
    # Each output element is stashed so filterSafe() at top level does not escape parser HTML.
    # Raw HTML blocks are only processed when safe=false.
    private function filterBlocks(string $src): string {
        $lines = explode("\n", $src);
        $n     = count($lines);
        $pat   = '/^\x02'.preg_quote($this->salt, '/').':\d+\x03$/';
        $out   = '';
        $i     = 0;

        while ($i < $n) {
            $line = $lines[$i];
            $trim = ltrim($line);

            # Lone stash token on its line — pass through unchanged
            if (preg_match($pat, trim($line))) { $out .= $line."\n"; $i++; continue; }
            # Blank line
            if ($trim === '') { $out .= "\n"; $i++; continue; }

            # ATX heading (#...######)
            if (preg_match('/^(#{1,6})\s+(.*?)(?:\s+#+)?$/', $trim, $m)) {
                $lvl  = strlen($m[1]);
                $id   = $this->getHeadingId($m[2], $lvl);
                $out .= $this->addStash('<h'.$lvl.' id="'.$id.'">'.$this->filterInline($m[2]).'</h'.$lvl.'>')."\n";
                $i++; continue;
            }

            # Horizontal rule (*** or --- or ___)
            if (preg_match('/^(?:\*{3,}|-{3,}|_{3,})\s*$/', $trim)) {
                $out .= $this->addStash('<hr>')."\n"; $i++; continue;
            }

            # Blockquote / GitHub callout (>)
            if (str_starts_with($trim, '>')) {
                [$bq, $i] = $this->getBlockquote($lines, $i, $n);
                $map  = ['note' => 'sl_callout_note', 'tip' => 'sl_callout_tip', 'important' => 'sl_callout_important', 'warning' => 'sl_callout_warning', 'caution' => 'sl_callout_caution'];
                $segs = [[]];
                foreach ($bq as $ln) {
                    if ($ln === '' && end($segs) !== []) $segs[] = [];
                    elseif ($ln !== '') $segs[count($segs) - 1][] = $ln;
                }
                foreach ($segs as $seg) {
                    if ($seg === []) continue;
                    $hd = trim($seg[0]);
                    if (preg_match('/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]$/i', $hd, $cm)) {
                        $cls = $map[strtolower($cm[1])];
                        array_shift($seg);
                        $inner = $this->filterBlocks(implode("\n", $seg));
                        $out .= $this->addStash('<div class="'.$cls.'">'."\n".$inner."</div>")."\n";
                    } else {
                        $inner = $this->filterBlocks(implode("\n", $seg));
                        $out .= $this->addStash("<blockquote>\n".$inner."</blockquote>")."\n";
                    }
                }
                continue;
            }

            # List (unordered or ordered)
            if (preg_match('/^([ \t]*)([*+\-]|\d+\.)\s+/', $line, $m)) {
                [$html, $i] = $this->filterList($lines, $i, strlen($m[1]));
                $out .= $this->addStash($html); continue;
            }

            # Table (header + separator on next line)
            if (isset($lines[$i + 1]) && str_contains($trim, '|')
                && preg_match('/^\|?[ \t]*:?-{2,}:?[ \t]*(?:\|[ \t]*:?-{2,}:?[ \t]*)+\|?$/', $lines[$i + 1])
            ) {
                [$html, $i] = $this->filterTable($lines, $i);
                $out .= $this->addStash($html); continue;
            }

            # Setext heading (underline = or -)
            if (isset($lines[$i + 1]) && $trim !== '') {
                if (preg_match('/^=+\s*$/', $lines[$i + 1])) {
                    $id   = $this->getHeadingId($trim, 1);
                    $out .= $this->addStash('<h1 id="'.$id.'">'.$this->filterInline($trim).'</h1>')."\n";
                    $i += 2; continue;
                }
                if (preg_match('/^-+\s*$/', $lines[$i + 1]) && !preg_match('/^[*+\-]\s/', $trim)) {
                    $id   = $this->getHeadingId($trim, 2);
                    $out .= $this->addStash('<h2 id="'.$id.'">'.$this->filterInline($trim).'</h2>')."\n";
                    $i += 2; continue;
                }
            }

            # Raw HTML block (safe=false only) — collect until blank line, resolve inner stash tokens
            if (!$this->safe && preg_match('/^<\/?[a-zA-Z]/', $trim)) {
                $raw = '';
                while ($i < $n && trim($lines[$i]) !== '') { $raw .= $lines[$i++]."\n"; }
                $map = [];
                foreach ($this->stash as $k => $v) { $map["\x02{$k}\x03"] = $v; }
                $raw = strtr($raw, $map);
                $out .= $this->addStash(str_replace(['&#034;', '&#039;'], ['"', "'"], $raw));
                continue;
            }

            # Paragraph — collect until blank line or heading/HR
            $para = [];
            while ($i < $n && trim($lines[$i]) !== ''
                && !preg_match('/^#{1,6}\s|^(?:\*{3,}|-{3,}|_{3,})\s*$/', ltrim($lines[$i]))
            ) {
                $para[] = $lines[$i++];
            }
            $out .= $this->addStash('<p>'.$this->filterInline(implode("\n", $para)).'</p>')."\n";
        }

        return $out;
    }

    # Apply inline processing: pre-escape user text (safe mode), then BB + markdown.
    # Safe-mode HTML escape happens after BB tags but before markdown (matches reference order).
    private function filterInline(string $src): string {
        return $this->filterInlines($this->safe ? $this->filterText($src) : $src);
    }

    # Full inline pipeline: BB tags → safe HTML escape (safe mode) → markdown.
    # BB stashed tags ([url], [img], [mail]) bypass the safe check via stash tokens.
    # BB non-stashed tags ([b], [color] etc.) are escaped by the safe check in safe mode.
    # Markdown bold/italic/del/mark come AFTER the safe check, so their HTML is preserved.
    private function filterInlines(string $src): string {
        # ed2k links — must precede generic [url]
        $src = preg_replace_callback(
            '/\[url\](ed2k:\/\/\|file\|(.*?)\|\d+\|\w+\|(h=\w+\|)?\/?)\[\/url\]/si',
            function(array $m): string {
                $url = $this->filterEsc($this->filterDec($m[1]));
                $ttl = $this->filterEsc($this->filterDec($m[2]));
                return $this->addStash('eMule/eDonkey: <a href="'.$url.'" target="_blank" title="'.$ttl.'">'.$ttl.'</a>');
            },
            $src
        ) ?? $src;

        $src = preg_replace_callback(
            '/\[url=(ed2k:\/\/\|file\|(.*?)\|\d+\|\w+\|(h=\w+\|)?\/?)\](.*?)\[\/url\]/si',
            function(array $m): string {
                $url = $this->filterEsc($this->filterDec($m[1]));
                $ttl = $this->filterEsc($this->filterDec($m[2]));
                return $this->addStash('<a href="'.$url.'" target="_blank" title="'.$ttl.'">'.(string)$m[4].'</a>');
            },
            $src
        ) ?? $src;

        # [b], [i], [u], [s] — raw HTML (not stashed); safe check below will escape in safe mode
        for ($i = 0; $i < 3; $i++) {
            $src = preg_replace('/\[b\](.*?)\[\/b\]/si', '<strong>$1</strong>', $src) ?? $src;
            $src = preg_replace('/\[i\](.*?)\[\/i\]/si', '<em>$1</em>', $src) ?? $src;
            $src = preg_replace('/\[u\](.*?)\[\/u\]/si', '<u>$1</u>', $src) ?? $src;
            $src = preg_replace('/\[s\](.*?)\[\/s\]/si', '<del>$1</del>', $src) ?? $src;
        }

        # [color=X] — raw span; unsafe values fall back to inner content
        $src = preg_replace_callback(
            '/\[color=([^\]]+)\](.*?)\[\/color\]/si',
            function(array $m): string {
                $color = strtolower(trim($m[1]));
                if (!preg_match('/^#[0-9a-f]{6}$/', $color) && !preg_match('/^[a-z]+$/', $color)) return $m[2];
                return '<span style="color:'.$this->filterEsc($color).'">'.$m[2].'</span>';
            },
            $src
        ) ?? $src;

        # [family=X]
        $src = preg_replace_callback(
            '/\[family=([A-Za-z ]+)\](.*?)\[\/family\]/si',
            fn(array $m): string => '<span style="font-family:'.$this->filterEsc(trim($m[1])).'">'.$m[2].'</span>',
            $src
        ) ?? $src;

        # [size=N] — clamped to 8–48px
        $src = preg_replace_callback(
            '/\[size=([0-9]{1,2})\](.*?)\[\/size\]/si',
            function(array $m): string {
                $size = max(8, min(48, (int)$m[1]));
                return '<span style="font-size:'.$size.'px">'.$m[2].'</span>';
            },
            $src
        ) ?? $src;

        # [left/right/center/justify]
        $src = preg_replace_callback(
            '/\[(left|right|center|justify)\](.*?)\[\/\1\]/si',
            function(array $m): string {
                $align = strtolower(trim($m[1]));
                if (!in_array($align, ['left', 'right', 'center', 'justify'], true)) return $m[2];
                return '<div style="text-align:'.$align.';">'.$m[2].'</div>';
            },
            $src
        ) ?? $src;

        # [mail]...[/mail]
        $src = preg_replace_callback(
            '/\[mail\](.*?)\[\/mail\]/si',
            function(array $m): string {
                $mail = trim($this->filterDec($m[1]));
                if (!preg_match('/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $mail)) return $m[1];
                $mail = $this->filterEsc($mail);
                return $this->addStash('<a href="mailto:'.$mail.'">'.$mail.'</a>');
            },
            $src
        ) ?? $src;

        # [mail=addr]...[/mail]
        $src = preg_replace_callback(
            '/\[mail\s*=\s*([^\]]+)\](.*?)\[\/mail\]/si',
            function(array $m): string {
                $mail = trim($this->filterDec($m[1]));
                if (!preg_match('/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $mail)) return $m[2];
                $mail = $this->filterEsc($mail);
                return $this->addStash('<a href="mailto:'.$mail.'">'.$m[2].'</a>');
            },
            $src
        ) ?? $src;

        # [url]...[/url]
        $src = preg_replace_callback(
            '/\[url\](.*?)\[\/url\]/si',
            function(array $m): string {
                $url  = trim($this->filterDec($m[1]));
                if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                $href = $this->filterEsc($this->safe ? $this->filterUrl($url) : $url);
                return $this->addStash('<a href="'.$href.'">'.$this->filterEsc($url).'</a>');
            },
            $src
        ) ?? $src;

        # [url=href]...[/url]
        $src = preg_replace_callback(
            '/\[url=([^\]]+)\](.*?)\[\/url\]/si',
            function(array $m): string {
                $url  = trim($this->filterDec($m[1]));
                if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                $href = $this->filterEsc($this->safe ? $this->filterUrl($url) : $url);
                return $this->addStash('<a href="'.$href.'">'.$m[2].'</a>');
            },
            $src
        ) ?? $src;

        # [img]...[/img]
        $src = preg_replace_callback(
            '/\[img\](.*?)\[\/img\]/si',
            function(array $m): string {
                $url  = trim($this->filterDec($m[1]));
                if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                $src2 = $this->filterEsc($this->safe ? $this->filterUrl($url) : $url);
                $path = parse_url($url, PHP_URL_PATH);
                $path = is_string($path) && $path !== '' ? $path : $url;
                $file = $this->filterEsc(basename(rawurldecode($path)) ?: 'image');
                $src2 = $this->filterEsc($this->normalizeImageSource($src2));
                $err  = $this->buildImageError($file);
                return $this->addStash('<img src="'.$src2.'" alt="'.$file.'" title="'.$file.'" class="sl-img"'.$err.'>');
            },
            $src
        ) ?? $src;

        # [img=align]...[/img]
        $src = preg_replace_callback(
            '/\[img=([a-zA-Z]+)\](.*?)\[\/img\]/si',
            function(array $m): string {
                $align = strtolower(trim($m[1]));
                if (!in_array($align, ['left', 'right'], true)) $align = 'left';
                $url  = trim($this->filterDec($m[2]));
                if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                $src2 = $this->filterEsc($this->safe ? $this->filterUrl($url) : $url);
                $path = parse_url($url, PHP_URL_PATH);
                $path = is_string($path) && $path !== '' ? $path : $url;
                $file = $this->filterEsc(basename(rawurldecode($path)) ?: 'image');
                $src2 = $this->filterEsc($this->normalizeImageSource($src2));
                $err  = $this->buildImageError($file);
                return $this->addStash('<img src="'.$src2.'" style="float:'.$align.';" alt="'.$file.'" title="'.$file.'" class="sl-img"'.$err.'>');
            },
            $src
        ) ?? $src;

        # [img alt=X]...[/img]
        $src = preg_replace_callback(
            '/\[img\s+alt=([\pL0-9_\-\.\"\s]+)\](.*?)\[\/img\]/siu',
            function(array $m): string {
                $alt  = trim($this->filterDec($m[1]));
                $url  = trim($this->filterDec($m[2]));
                if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                $src2 = $this->filterEsc($this->safe ? $this->filterUrl($url) : $url);
                $path = parse_url($url, PHP_URL_PATH);
                $path = is_string($path) && $path !== '' ? $path : $url;
                $file = $this->filterEsc(basename(rawurldecode($path)) ?: 'image');
                $alt  = ($alt === '' || strtolower($alt) === 'title' || strtolower($alt) === 'alt') ? $file : $this->filterEsc($alt);
                $src2 = $this->filterEsc($this->normalizeImageSource($src2));
                $err  = $this->buildImageError($file);
                return $this->addStash('<img src="'.$src2.'" alt="'.$alt.'" title="'.$alt.'" class="sl-img"'.$err.'>');
            },
            $src
        ) ?? $src;

        # [img=align alt=X]...[/img]
        $src = preg_replace_callback(
            '/\[img=([a-zA-Z]+)\s+alt=([\pL0-9_\-\.\"\s]+)\](.*?)\[\/img\]/siu',
            function(array $m): string {
                $align = strtolower(trim($m[1]));
                if (!in_array($align, ['left', 'right'], true)) $align = 'left';
                $alt  = trim($this->filterDec($m[2]));
                $url  = trim($this->filterDec($m[3]));
                if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                $src2 = $this->filterEsc($this->safe ? $this->filterUrl($url) : $url);
                $path = parse_url($url, PHP_URL_PATH);
                $path = is_string($path) && $path !== '' ? $path : $url;
                $file = $this->filterEsc(basename(rawurldecode($path)) ?: 'image');
                $alt  = ($alt === '' || strtolower($alt) === 'title' || strtolower($alt) === 'alt') ? $file : $this->filterEsc($alt);
                $src2 = $this->filterEsc($this->normalizeImageSource($src2));
                $err  = $this->buildImageError($file);
                return $this->addStash('<img src="'.$src2.'" style="float:'.$align.';" alt="'.$alt.'" title="'.$alt.'" class="sl-img"'.$err.'>');
            },
            $src
        ) ?? $src;

        # Markdown image: ![alt](src "title")
        $src = preg_replace_callback(
            '/!\[([^\]]*)\]\(([^\s)]+)(?:\s+(?:"|&quot;)(.*?)(?:"|&quot;))?\)/',
            function(array $m): string {
                $raw  = $this->filterDec($m[2]);
                $url  = $this->filterEsc($this->safe ? $this->filterUrl($raw) : $raw);
                $path = parse_url($raw, PHP_URL_PATH);
                $path = is_string($path) && $path !== '' ? $path : $raw;
                $file = $this->filterEsc(basename(rawurldecode($path)) ?: 'image');
                $alt  = trim($this->filterDec($m[1]));
                $alt  = ($alt === '' || strtolower($alt) === 'title' || strtolower($alt) === 'alt') ? $file : $this->filterEsc($alt);
                $ttl  = isset($m[3]) ? trim($this->filterDec($m[3])) : '';
                $url  = $this->filterEsc($this->normalizeImageSource($url));
                $ttl  = ($ttl === '' || strtolower($ttl) === 'title' || strtolower($ttl) === 'alt') ? ' title="'.$file.'"' : ' title="'.$this->filterEsc($ttl).'"';
                $err  = $this->buildImageError($file);
                return $this->addStash('<img src="'.$url.'" alt="'.$alt.'"'.$ttl.$err.'>');
            },
            $src
        ) ?? $src;

        # Markdown link: [text](url "title")
        $src = preg_replace_callback(
            '/\[([^\]]+)\]\(([^\s)]+)(?:\s+(?:"|&quot;)(.*?)(?:"|&quot;))?\)/',
            function(array $m): string {
                $href = $this->filterEsc($this->safe ? $this->filterUrl($this->filterDec($m[2])) : $this->filterDec($m[2]));
                $ttl  = isset($m[3]) ? ' title="'.$this->filterEsc($this->filterDec($m[3])).'"' : '';
                return $this->addStash('<a href="'.$href.'"'.$ttl.'>'.$m[1].'</a>');
            },
            $src
        ) ?? $src;

        # Auto-link <https://...>
        $src = preg_replace_callback(
            '/<(https?:\/\/[^\s>]+)>/',
            fn(array $m): string => $this->addStash('<a href="'.$this->filterEsc($m[1]).'">'.$this->filterEsc($m[1]).'</a>'),
            $src
        ) ?? $src;

        # Auto-link <email@...>
        $src = preg_replace_callback(
            '/<([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})>/',
            fn(array $m): string => $this->addStash('<a href="mailto:'.$this->filterEsc($m[1]).'">'.$this->filterEsc($m[1]).'</a>'),
            $src
        ) ?? $src;

        # Safe-mode HTML escape for remaining <...> tags (catches raw [b]/[color] HTML, user-injected HTML)
        # Markdown bold/italic comes AFTER this check → preserved
        if ($this->safe) {
            $src = preg_replace_callback('/<[^>]+>/', fn(array $m): string => $this->filterEsc($m[0]), $src) ?? $src;
        }

        # Markdown emphasis (must follow safe check so generated HTML is not escaped)
        $src = preg_replace(['/\*{3}(.+?)\*{3}/s', '/_{3}(.+?)_{3}/s'], '<strong><em>$1</em></strong>', $src);
        $src = preg_replace(['/\*{2}(.+?)\*{2}/s', '/_{2}(.+?)_{2}/s'], '<strong>$1</strong>', $src);
        $src = preg_replace(['/\*([^*\n]+)\*/', '/(?<![_\w])_([^_\n]+)_(?![_\w])/'], '<em>$1</em>', $src);
        $src = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $src);
        $src = preg_replace('/==(.+?)==/s', '<mark>$1</mark>', $src);
        $src = preg_replace(['/  \n/', '/\\\\\n/'], "<br>\n", $src);

        return $src;
    }

    # Escape remaining HTML tags in safe mode.
    # No-op when !$this->safe (block HTML already stashed, inline HTML already handled by filterInlines()).
    private function filterSafe(string $src): string {
        if (!$this->safe) return $src;
        return preg_replace_callback(
            '/<[^>]+>/',
            fn(array $m): string => htmlspecialchars($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $src
        ) ?? $src;
    }
}
