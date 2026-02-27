# filterMarkdown() — SLAED Markdown Parser

**Zieldatei:** `core/system.php` (direkt nach `bb_decode()`)
**Zweck:** Markdown → HTML, analog zu `bb_decode()` für BB-Code
**Standard:** SLAED Refactoring Standards §5 — `verbNoun()`, verb = `filter`

---

## Verwendung

```php
// Admin — alles erlaubt inkl. Raw-HTML-Blöcke
echo filterMarkdown($article['text'], false);

// User — sicher, kein Raw-HTML, keine javascript:-URLs
echo filterMarkdown($comment['text'], true);

// Format-Switch neben bb_decode()
echo $article['format'] === 'md'
    ? filterMarkdown($text, false)
    : bb_decode($text, $conf['name']);
```

---

## Abgedeckte Markdown-Elemente

| Element | Syntax | Abgedeckt |
|---------|--------|-----------|
| H1–H6 (ATX) | `# … ######` | ✓ |
| H1–H2 (Setext) | `text\n===` | ✓ |
| Absätze | Leerzeile | ✓ |
| Zeilenumbrüche | `  \n` / `\\\n` | ✓ |
| Horizontal rule | `---` / `***` | ✓ |
| Blockquote | `> text` | ✓ |
| Ungeordnete Liste | `- / * / +` | ✓ |
| Geordnete Liste | `1.` | ✓ |
| Task-Liste (GFM) | `- [x]` | ✓ |
| Tabellen (GFM) | `\| col \|` | ✓ |
| Fenced Code | ` ``` lang ``` ` | ✓ |
| Indented Code | 4 Leerzeichen | ✓ |
| Inline Code | `` `code` `` | ✓ |
| Fett | `**text**` | ✓ |
| Kursiv | `*text*` | ✓ |
| Fett+Kursiv | `***text***` | ✓ |
| Durchgestrichen | `~~text~~` | ✓ |
| Hervorhebung | `==text==` | ✓ |
| Links | `[text](url)` | ✓ |
| Bilder | `![alt](src)` | ✓ |
| Auto-Links | `<https://…>` | ✓ |
| Raw HTML | `<div>…</div>` | nur `safe=false` |
| Fußnoten | `[^1]` | ✗ (two-pass) |
| Referenz-Links | `[text][ref]` | ✗ (two-pass) |

---

## Sicherheit

| Maßnahme | Beschreibung |
|----------|-------------|
| `safe=true` | User-Modus: kein Raw-HTML, URL-Allowlist, filterText |
| `safe=false` | Admin-Modus: Raw-HTML-Blöcke erlaubt |
| `filterUrl()` | Nur `https?://`, `mailto:`, relative Pfade — kein `javascript:` |
| `filterText()` | Escaped HTML, aber Stash-Tokens (`\x02salt:N\x03`) bleiben intakt |
| Stash-Salt | `random_bytes(4)` pro Request — verhindert Token-Kollision |
| Double-Escape | `html_entity_decode()` vor `filterEsc()` in Links/Images |

---

## Code

```php
# Convert Markdown source to safe HTML.
# Safe mode (true): escapes HTML, URL allowlist — for user content.
# Safe mode (false): allows raw HTML blocks — for admin content only.
function filterMarkdown(string $src, bool $safe = true): string {
    static $md = null;
    $md ??= new class {

        private array  $stash = [];
        private string $salt  = '';
        private array  $hids  = []; // heading id dedup table

        // ── Entry point ────────────────────────────────────────

        public function filterHtml(string $src, bool $safe): string {
            $this->stash = [];
            $this->hids  = [];
            $this->salt  = bin2hex(random_bytes(4)); // per-request salt prevents stash token collision
            $src = str_replace(["\r\n", "\r"], "\n", $src);
            $src = $this->filterFencedCode($src);
            $src = $this->filterIndentedCode($src);
            $src = $this->filterInlineCode($src);
            $src = $this->filterBlocks($src, $safe);
            return trim(strtr($src, $this->stash));
        }

        // ── Helpers ────────────────────────────────────────────

        private function addStash(string $html): string {
            $k = "\x02{$this->salt}:" . count($this->stash) . "\x03";
            $this->stash[$k] = $html;
            return $k;
        }

        private function filterEsc(string $s): string {
            return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Escape text but leave stash tokens intact
        private function filterText(string $s): string {
            $pat   = '/(\x02' . preg_quote($this->salt, '/') . ':\d+\x03)/';
            $parts = preg_split($pat, $s, -1, PREG_SPLIT_DELIM_CAPTURE) ?? [$s];
            return implode('', array_map(fn($p) => preg_match($pat, $p) ? $p : $this->filterEsc($p), $parts));
        }

        // Escape (if safe) then run inline Markdown — called for every text fragment
        private function filterInline(string $text, bool $safe): string {
            return $this->filterInlines($safe ? $this->filterText($text) : $text, $safe);
        }

        // Allow only safe URL schemes (no javascript:, data:, vbscript:)
        private function filterUrl(string $url): string {
            $u = trim($url);
            return preg_match('/^(?:https?:\/\/|mailto:|[\/\.#?])/i', $u) ? $u : '#';
        }

        // ── Code protection (extracted before any other processing) ────

        // Fix: ^ before \1 with m-flag = closing fence must be at line start.
        //      [^\n]* = ignore rest of info string (e.g. ```php extra-info)
        private function filterFencedCode(string $src): string {
            return preg_replace_callback(
                '/^(`{3,}|~{3,})[ \t]*([\w\-]*)[^\n]*\n(.*?)\n^\1[ \t]*$/ms',
                function($m) {
                    $class = $m[2] ? ' class="language-' . $this->filterEsc($m[2]) . '"' : '';
                    return $this->addStash("<pre><code{$class}>" . $this->filterEsc($m[3]) . '</code></pre>');
                },
                $src
            ) ?? $src;
        }

        private function filterIndentedCode(string $src): string {
            return preg_replace_callback(
                '/(?:^(?:    |\t).+\n?)+/m',
                fn($m) => $this->addStash(
                    '<pre><code>'
                    . $this->filterEsc(preg_replace('/^(?:    |\t)/m', '', rtrim($m[0])))
                    . '</code></pre>'
                ) . "\n",
                $src
            ) ?? $src;
        }

        // Fix: isset() check prevents "undefined array key" Notice on alternative match
        private function filterInlineCode(string $src): string {
            return preg_replace_callback(
                '/``(.+?)``|`([^`\n]+)`/s',
                function($m) {
                    $code = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');
                    return $this->addStash('<code>' . $this->filterEsc($code) . '</code>');
                },
                $src
            ) ?? $src;
        }

        // ── Block elements ─────────────────────────────────────

        private function filterBlocks(string $src, bool $safe): string {
            $lines    = explode("\n", $src);
            $n        = count($lines);
            $stashpat = '/^\x02' . preg_quote($this->salt, '/') . ':\d+\x03$/';
            $out      = '';
            $i        = 0;

            while ($i < $n) {
                $line    = $lines[$i];
                $trimmed = ltrim($line);

                // Pass stashed blocks (code, etc.) through unchanged
                if (preg_match($stashpat, trim($line))) { $out .= $line . "\n"; $i++; continue; }
                if ($trimmed === '')                     { $out .= "\n";         $i++; continue; }

                // ATX heading  # … ######
                if (preg_match('/^(#{1,6})\s+(.*?)(?:\s+#+)?$/', $trimmed, $m)) {
                    $lvl  = strlen($m[1]);
                    $slug = $this->getHeadingId($m[2], $lvl);
                    $out .= "<h{$lvl} id=\"{$slug}\">" . $this->filterInline($m[2], $safe) . "</h{$lvl}>\n";
                    $i++; continue;
                }

                // Horizontal rule  --- / *** / ___
                if (preg_match('/^(?:\*{3,}|-{3,}|_{3,})\s*$/', $trimmed)) {
                    $out .= "<hr>\n"; $i++; continue;
                }

                // Blockquote  >
                if (str_starts_with($trimmed, '>')) {
                    [$bqlines, $i] = $this->getBlockquote($lines, $i, $n);
                    $out .= "<blockquote>\n" . $this->filterBlocks(implode("\n", $bqlines), $safe) . "</blockquote>\n";
                    continue;
                }

                // List  - / * / + / 1.
                if (preg_match('/^([ \t]*)([*+\-]|\d+\.)\s+/', $line, $m)) {
                    [$html, $i] = $this->filterList($lines, $i, strlen($m[1]), $safe);
                    $out .= $html; continue;
                }

                // Table (GFM)  | col | col |
                if (isset($lines[$i + 1]) && str_contains($trimmed, '|')
                    && preg_match('/^\|?[ \t]*:?-{2,}:?[ \t]*(?:\|[ \t]*:?-{2,}:?[ \t]*)+\|?$/', $lines[$i + 1])
                ) {
                    [$html, $i] = $this->filterTable($lines, $i, $safe);
                    $out .= $html; continue;
                }

                // Setext heading  text\n===  or  text\n---
                if (isset($lines[$i + 1]) && $trimmed !== '') {
                    if (preg_match('/^=+\s*$/', $lines[$i + 1])) {
                        $slug = $this->getHeadingId($trimmed, 1);
                        $out .= '<h1 id="' . $slug . '">' . $this->filterInline($trimmed, $safe) . "</h1>\n";
                        $i += 2; continue;
                    }
                    if (preg_match('/^-+\s*$/', $lines[$i + 1]) && !preg_match('/^[*+\-]\s/', $trimmed)) {
                        $slug = $this->getHeadingId($trimmed, 2);
                        $out .= '<h2 id="' . $slug . '">' . $this->filterInline($trimmed, $safe) . "</h2>\n";
                        $i += 2; continue;
                    }
                }

                // Raw HTML block — admin only (safe=false)
                // Note: collected until next empty line; HTML with blank lines requires manual stashing
                if (!$safe && preg_match('/^<\/?(?:div|section|article|aside|nav|header|footer|main|pre|ul|ol|table|figure)[\s>\/]/i', $trimmed)) {
                    $raw = '';
                    while ($i < $n && trim($lines[$i]) !== '') { $raw .= $lines[$i++] . "\n"; }
                    $out .= $this->addStash($raw);
                    continue;
                }

                // Paragraph — collect until empty line or new block-level element
                $para = [];
                while ($i < $n && trim($lines[$i]) !== ''
                    && !preg_match('/^#{1,6}\s|^(?:\*{3,}|-{3,}|_{3,})\s*$/', ltrim($lines[$i]))
                ) {
                    $para[] = $lines[$i++];
                }
                $out .= '<p>' . $this->filterInline(implode("\n", $para), $safe) . "</p>\n";
            }

            return $out;
        }

        // Fix: non-'>' non-empty line ends blockquote immediately (was "sticky" before)
        private function getBlockquote(array $lines, int $i, int $n): array {
            $bq = [];
            while ($i < $n) {
                $t = ltrim($lines[$i]);
                if (str_starts_with($t, '>')) {
                    $bq[] = preg_replace('/^[ \t]*>[ \t]?/', '', $lines[$i++]);
                } elseif (trim($lines[$i]) === '') {
                    // Empty line: continue only if next non-empty line also starts with >
                    $j = $i + 1;
                    while ($j < $n && trim($lines[$j]) === '') $j++;
                    if ($j < $n && str_starts_with(ltrim($lines[$j]), '>')) {
                        $bq[] = ''; $i++;
                    } else {
                        break;
                    }
                } else {
                    break; // non-empty, non-'>' line → blockquote ends here
                }
            }
            return [$bq, $i];
        }

        // Fix: dedup heading IDs + fallback h1/h2/… for non-latin text (Cyrillic, etc.)
        private function getHeadingId(string $raw, int $lvl): string {
            $text = preg_replace('/\x02' . preg_quote($this->salt, '/') . ':\d+\x03/', '', $raw);
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', strip_tags($text)), '-'));
            if ($slug === '') $slug = 'h' . $lvl;
            $base = $slug;
            if (isset($this->hids[$base])) {
                $slug = $base . '-' . (++$this->hids[$base]);
            } else {
                $this->hids[$base] = 0;
            }
            return $slug;
        }

        // Fix: always apply filterInlines() on list items (even simple single-line items)
        private function filterList(array $lines, int $i, int $indent, bool $safe): array {
            $n       = count($lines);
            $ordered = (bool)preg_match('/^\s*\d+\./', $lines[$i]);
            $tag     = $ordered ? 'ol' : 'ul';
            $items   = [];
            $cur     = null;

            while ($i < $n) {
                $line = $lines[$i];
                if (trim($line) === '') { if ($cur !== null) $cur .= "\n"; $i++; continue; }
                $ind  = strlen($line) - strlen(ltrim($line));
                if ($ind === $indent && preg_match('/^[ \t]*(?:[*+\-]|\d+\.)\s+(.*)$/', $line, $m)) {
                    if ($cur !== null) $items[] = $cur;
                    $cur = $m[1]; $i++;
                } elseif ($ind > $indent) {
                    $cur .= "\n" . $line; $i++;
                } else { break; }
            }
            if ($cur !== null) $items[] = $cur;

            $html = "<{$tag}>\n";
            foreach ($items as $item) {
                $item = trim($item);
                if (preg_match('/^\[(x| )\]\s+(.*)/si', $item, $tm)) {
                    // Task list item  - [x] done  /  - [ ] todo
                    $checked = $tm[1] === 'x' ? ' checked' : '';
                    $label   = trim($tm[2]);
                    $label   = str_contains($label, "\n")
                        ? $this->filterBlocks($label, $safe)
                        : $this->filterInline($label, $safe);
                    $html .= "<li><input type=\"checkbox\" disabled{$checked}> {$label}</li>\n";
                } elseif (str_contains($item, "\n")) {
                    // Multi-line item — may contain sub-lists, blockquotes, etc.
                    $html .= '<li>' . $this->filterBlocks($item, $safe) . "</li>\n";
                } else {
                    // Simple item: escape + inline always applied
                    $html .= '<li>' . $this->filterInline($item, $safe) . "</li>\n";
                }
            }
            return [$html . "</{$tag}>\n", $i];
        }

        // Fix: column count normalized via array_pad + escaping on all cells/headers
        private function filterTable(array $lines, int $i, bool $safe): array {
            $heads  = array_map('trim', explode('|', trim($lines[$i],   " |\t")));
            $seps   = array_map('trim', explode('|', trim($lines[$i+1], " |\t")));
            $ncols  = max(count($heads), count($seps)); // normalize: fill missing columns
            $aligns = array_map(fn($a) =>
                preg_match('/^:-+:$/', $a) ? ' style="text-align:center"' :
               (preg_match('/^-+:$/', $a)  ? ' style="text-align:right"'  :
               (preg_match('/^:-+$/', $a)  ? ' style="text-align:left"'   : '')),
                $seps
            );
            $i += 2;
            $html = "<table>\n<thead>\n<tr>";
            foreach (array_pad($heads, $ncols, '') as $j => $h) {
                $html .= '<th' . ($aligns[$j] ?? '') . '>' . $this->filterInline($h, $safe) . '</th>';
            }
            $html .= "</tr>\n</thead>\n<tbody>\n";
            while (isset($lines[$i]) && str_contains($lines[$i], '|') && trim($lines[$i]) !== '') {
                $cells = array_map('trim', explode('|', trim($lines[$i], " |\t")));
                $html .= '<tr>';
                foreach (array_pad($cells, $ncols, '') as $j => $c) {
                    $html .= '<td' . ($aligns[$j] ?? '') . '>' . $this->filterInline($c, $safe) . '</td>';
                }
                $html .= "</tr>\n"; $i++;
            }
            return [$html . "</tbody>\n</table>\n", $i];
        }

        // ── Inline elements ────────────────────────────────────
        //
        // IMPORTANT: $src must be HTML-escaped before calling this in safe mode.
        // Always call via filterInline() — never filterInlines($rawUserText, true) directly.

        private function filterInlines(string $src, bool $safe): string {
            // Fix double-escaping: decode first, then filterUrl, then filterEsc — exactly once
            $dec = fn(string $s): string => html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Images  ![alt](url "title")
            $src = preg_replace_callback(
                '/!\[([^\]]*)\]\(([^\s)]+)(?:\s+"([^"]*)")?\)/',
                function($m) use ($safe, $dec) {
                    $url   = $this->filterEsc($safe ? $this->filterUrl($dec($m[2])) : $dec($m[2]));
                    $alt   = $this->filterEsc($dec($m[1]));
                    $title = isset($m[3]) ? ' title="' . $this->filterEsc($dec($m[3])) . '"' : '';
                    return $this->addStash("<img src=\"{$url}\" alt=\"{$alt}\"{$title}>");
                },
                $src
            ) ?? $src;

            // Links  [text](url "title")
            // Note: $m[1] (link text) comes pre-escaped from filterText() — no double-decode needed
            $src = preg_replace_callback(
                '/\[([^\]]+)\]\(([^\s)]+)(?:\s+"([^"]*)")?\)/',
                function($m) use ($safe, $dec) {
                    $href  = $this->filterEsc($safe ? $this->filterUrl($dec($m[2])) : $dec($m[2]));
                    $title = isset($m[3]) ? ' title="' . $this->filterEsc($dec($m[3])) . '"' : '';
                    return $this->addStash("<a href=\"{$href}\"{$title}>{$m[1]}</a>");
                },
                $src
            ) ?? $src;

            // Auto-links  <https://…>  — already restricted to http(s), no filterUrl needed
            $src = preg_replace_callback('/<(https?:\/\/[^\s>]+)>/',
                fn($m) => $this->addStash('<a href="' . $this->filterEsc($m[1]) . '">' . $this->filterEsc($m[1]) . '</a>'),
                $src) ?? $src;

            // Auto-links  <email@…>
            $src = preg_replace_callback('/<([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})>/',
                fn($m) => $this->addStash('<a href="mailto:' . $this->filterEsc($m[1]) . '">' . $this->filterEsc($m[1]) . '</a>'),
                $src) ?? $src;

            // Safety net: escape any remaining raw HTML tags that survived filterText()
            if ($safe) {
                $src = preg_replace_callback('/<[^>]+>/', fn($m) => $this->filterEsc($m[0]), $src) ?? $src;
            }

            // Bold+Italic  ***text***  ___text___
            $src = preg_replace(['/\*{3}(.+?)\*{3}/s', '/_{3}(.+?)_{3}/s'],  '<strong><em>$1</em></strong>', $src);
            // Bold  **text**  __text__
            $src = preg_replace(['/\*{2}(.+?)\*{2}/s', '/_{2}(.+?)_{2}/s'],  '<strong>$1</strong>', $src);
            // Italic  *text*  _text_  (word-boundary guard on _)
            $src = preg_replace(['/\*([^*\n]+)\*/', '/(?<![_\w])_([^_\n]+)_(?![_\w])/'], '<em>$1</em>', $src);
            // Strikethrough (GFM)  ~~text~~
            $src = preg_replace('/~~(.+?)~~/s',  '<del>$1</del>', $src);
            // Highlight  ==text==
            $src = preg_replace('/==(.+?)==/s',  '<mark>$1</mark>', $src);
            // Hard line breaks: two trailing spaces or backslash before newline
            $src = preg_replace(['/  \n/', '/\\\\\n/'], "<br>\n", $src);

            return $src;
        }
    };

    return $md->filterHtml($src, $safe);
}
```

---

## Bekannte Einschränkungen

- **Fußnoten** `[^1]` — erfordern Two-Pass-Verarbeitung, nicht implementiert
- **Referenz-Links** `[text][ref]` — erfordern Two-Pass-Verarbeitung, nicht implementiert
- **Raw-HTML mit Leerzeilen** — wird am leeren Absatz getrennt (admin-Modus)
- **Nicht-lateinische Heading-IDs** (Kyrillisch, etc.) — Fallback auf `h1`, `h2`, …

## Implementierungsnotizen

- **Anonyme Klasse** intern — kein Klassenname im globalen Scope
- **`static $md`** — Instanz wird einmalig pro Request erstellt (kein Overhead)
- **Stash-Salt** — `random_bytes(4)` pro `filterHtml()`-Aufruf verhindert Token-Kollision
- **`filterInline()`** — alle Textfragmente laufen durch `filterText()` + `filterInlines()`
- Kein Composer, keine Extra-Datei — vollständig self-contained in `system.php`
