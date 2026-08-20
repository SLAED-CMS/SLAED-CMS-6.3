<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The theme audit. It measures what `.rules/theme.md` demands and `tools/ui-contract.php` declares,
# and it is the authority over every number written in docs/THEME-ETALON-2026.md.
# Usage: php tools/ui-audit.php --theme=admin [--count|--bare|--dist=padding|--dup|--names|--ramp|--cross|--markup]
#        php tools/ui-audit.php --file=templates/admin/assets/css/theme.css --migrating
#        php tools/ui-audit.php --store   rewrites tools/ui-audit-baseline.json from the current tree

const UI_ROOT = __DIR__.'/..';
const UI_BASE = __DIR__.'/ui-audit-baseline.json';

# Colour keywords a value may spell instead of a hex triple; the ones a theme actually paints with
const UI_COLORS = [
    'aqua', 'beige', 'black', 'blue', 'brown', 'coral', 'crimson', 'cyan', 'darkblue', 'darkgray',
    'darkgreen', 'darkgrey', 'darkred', 'fuchsia', 'gold', 'gray', 'green', 'grey', 'indigo',
    'ivory', 'khaki', 'lavender', 'lightblue', 'lightgray', 'lightgreen', 'lightgrey', 'lime',
    'magenta', 'maroon', 'navy', 'olive', 'orange', 'orchid', 'pink', 'plum', 'purple', 'red',
    'salmon', 'silver', 'sienna', 'skyblue', 'snow', 'tan', 'teal', 'tomato', 'violet', 'wheat',
    'white', 'yellow',
];

# Functions whose whole call is one colour decision
const UI_CFUNC = ['rgb', 'rgba', 'hsl', 'hsla', 'hwb', 'lab', 'lch', 'oklab', 'oklch', 'color'];

# Functions that take colours and give one back. Their arguments are walked, because a mix of two tokens is tokenised
# and a mix of two literals is not - the whole call cannot answer for both. The ratio is a decision of its own
const UI_MFUNC = ['color-mix', 'light-dark'];

# Functions whose arguments are walked, because the decisions sit inside them
const UI_WFUNC = [
    'linear-gradient', 'radial-gradient', 'conic-gradient', 'repeating-linear-gradient', 'repeating-radial-gradient',
    'repeating-conic-gradient', 'calc', 'min', 'max', 'clamp', 'minmax', 'fit-content', 'env',
];

# HTML element names the markup scan recognises. An XML element of a feed or a sitemap is not one of them,
# because batch 9 asks what a theme cannot restyle, and a theme never styles a feed
const UI_TAGS = [
    'a', 'abbr', 'address', 'article', 'aside', 'audio', 'b', 'blockquote', 'body', 'br', 'button',
    'canvas', 'caption', 'cite', 'code', 'col', 'colgroup', 'datalist', 'dd', 'del', 'details',
    'dfn', 'dialog', 'div', 'dl', 'dt', 'em', 'embed', 'fieldset', 'figcaption', 'figure', 'footer',
    'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hr', 'html', 'i', 'iframe', 'img',
    'input', 'ins', 'kbd', 'label', 'legend', 'li', 'main', 'mark', 'menu', 'meta', 'meter', 'nav',
    'noscript', 'object', 'ol', 'optgroup', 'option', 'output', 'p', 'param', 'picture', 'pre',
    'progress', 'q', 's', 'samp', 'script', 'section', 'select', 'small', 'source', 'span', 'strong',
    'style', 'sub', 'summary', 'sup', 'svg', 'table', 'tbody', 'td', 'textarea', 'tfoot', 'th',
    'thead', 'time', 'tr', 'u', 'ul', 'var', 'video', 'wbr',
];

# Easing spellings a transition or animation may carry
const UI_EASE = ['ease', 'linear', 'ease-in', 'ease-out', 'ease-in-out', 'step-start', 'step-end'];

# Weight and line-height keywords that spell a ladder step in words
const UI_WORDS = ['normal', 'bold', 'bolder', 'lighter'];

# Read the machine contract, the tracked authority behind every check here
function getContract(): array {
    static $cont = null;
    if ($cont !== null) return $cont;
    $cont = require __DIR__.'/ui-contract.php';
    if (is_file(__DIR__.'/ui-contrast.json')) {
        $data = json_decode((string)file_get_contents(__DIR__.'/ui-contrast.json'), true);
        if (is_array($data['pairs'] ?? null)) $cont['contrast']['pairs'] = $data['pairs'];
    }
    return $cont;
}

# Read one file from the repository root and normalise its line endings
function getFileText(string $path): string {
    $full = str_starts_with($path, UI_ROOT) ? $path : UI_ROOT.'/'.$path;
    if (!is_file($full)) return '';
    return str_replace("\r\n", "\n", (string)file_get_contents($full));
}

# Blank every comment while keeping newlines, so offsets and line numbers survive the strip
function filterComments(string $text): string {
    return (string)preg_replace_callback('~/\*.*?\*/~s', fn($m) => preg_replace('/[^\n]/', ' ', $m[0]), $text);
}

# Offsets of every line start, so a position maps to a line without rescanning the file
function getLineIndex(string $text): array {
    $out = [0];
    $pos = 0;
    while (($pos = strpos($text, "\n", $pos)) !== false) {
        $out[] = $pos + 1;
        $pos++;
    }
    return $out;
}

# Line number of one absolute offset, by binary search over the line index
function getLineNumber(array $offs, int $pos): int {
    $low = 0;
    $high = count($offs) - 1;
    while ($low < $high) {
        $mid = intdiv($low + $high + 1, 2);
        if ($offs[$mid] <= $pos) $low = $mid; else $high = $mid - 1;
    }
    return $low + 1;
}

# Offset of the brace closing the block that opens at $pos, skipping strings and nested blocks
function getBlockEnd(string $text, int $pos): int {
    $len = strlen($text);
    $depth = 0;
    for ($i = $pos; $i < $len; $i++) {
        $chr = $text[$i];
        if ($chr === '"' || $chr === "'") {
            for ($i++; $i < $len && $text[$i] !== $chr; $i++) if ($text[$i] === '\\') $i++;
            continue;
        }
        if ($chr === '{') $depth++;
        if ($chr === '}') {
            $depth--;
            if ($depth === 0) return $i;
        }
    }
    return $len - 1;
}

# Flatten one CSS text into rules, descending into nesting at-rules and carrying their context
function getCssRules(string $text, string $ctx = '', int $base = 0): array {
    $out = [];
    $len = strlen($text);
    $buf = '';
    $pos = 0;
    while ($pos < $len) {
        $chr = $text[$pos];
        if ($chr === '"' || $chr === "'") {
            $end = $pos + 1;
            while ($end < $len && $text[$end] !== $chr) {
                if ($text[$end] === '\\') $end++;
                $end++;
            }
            $buf .= substr($text, $pos, $end - $pos + 1);
            $pos = $end + 1;
            continue;
        }
        if ($chr === '{') {
            $prel = trim(preg_replace('/\s+/', ' ', $buf) ?? '');
            $end = getBlockEnd($text, $pos);
            $body = substr($text, $pos + 1, $end - $pos - 1);
            if (preg_match('/^@(media|supports|container|layer|document|scope|keyframes|-webkit-keyframes|-moz-keyframes)\b/i', $prel)) {
                $ctxt = $ctx === '' ? $prel : $ctx.' && '.$prel;
                $out = array_merge($out, getCssRules($body, $ctxt, $base + $pos + 1));
            } else {
                $out[] = ['sel' => $prel, 'ctx' => $ctx, 'body' => $body, 'pos' => $base + $pos];
            }
            $buf = '';
            $pos = $end + 1;
            continue;
        }
        if ($chr === ';') {
            $buf = '';
            $pos++;
            continue;
        }
        $buf .= $chr;
        $pos++;
    }
    return $out;
}

# Split one rule body into property and value pairs, respecting parentheses and strings
function getCssDecls(string $body): array {
    $out = [];
    $len = strlen($body);
    $buf = '';
    $depth = 0;
    for ($i = 0; $i <= $len; $i++) {
        $chr = $i < $len ? $body[$i] : ';';
        if ($chr === '"' || $chr === "'") {
            $qte = $chr;
            $buf .= $chr;
            for ($i++; $i < $len && $body[$i] !== $qte; $i++) {
                $buf .= $body[$i];
                if ($body[$i] === '\\' && $i + 1 < $len) {
                    $i++;
                    $buf .= $body[$i];
                }
            }
            $buf .= $qte;
            continue;
        }
        if ($chr === '(') $depth++;
        if ($chr === ')') $depth--;
        if ($chr === ';' && $depth <= 0) {
            $decl = trim($buf);
            $buf = '';
            if ($decl === '') continue;
            $cut = strpos($decl, ':');
            if ($cut === false) continue;
            $prop = strtolower(trim(substr($decl, 0, $cut)));
            $val = trim(substr($decl, $cut + 1));
            if ($prop === '') continue;
            $out[] = ['prop' => $prop, 'val' => $val, 'raw' => $decl];
            continue;
        }
        $buf .= $chr;
    }
    return $out;
}

# Strip the priority flag and collapse whitespace, so two spellings of one value compare equal
function filterValue(string $val): string {
    $val = (string)preg_replace('/\s*!\s*important\s*$/i', '', $val);
    return trim((string)preg_replace('/\s+/', ' ', $val));
}

# Split one function argument list at its top-level commas, so a nested function is not cut in half
function getArgParts(string $args): array {
    $out = [];
    $buf = '';
    $depth = 0;
    $len = strlen($args);
    for ($i = 0; $i < $len; $i++) {
        $chr = $args[$i];
        if ($chr === '(') $depth++;
        if ($chr === ')') $depth--;
        if ($chr === ',' && $depth === 0) {
            $out[] = trim($buf);
            $buf = '';
            continue;
        }
        $buf .= $chr;
    }
    if (trim($buf) !== '') $out[] = trim($buf);
    return $out;
}

# Split one value into the atoms a decision is judged on, descending into the functions that hold them
function getValueParts(string $val, bool &$hasvar = false): array {
    $out = [];
    $len = strlen($val);
    $buf = '';
    for ($i = 0; $i < $len; $i++) {
        $chr = $val[$i];
        if ($chr === '"' || $chr === "'") {
            for ($i++; $i < $len && $val[$i] !== $chr; $i++) if ($val[$i] === '\\') $i++;
            $buf = '';
            continue;
        }
        if ($chr === '(') {
            $name = strtolower(trim($buf));
            $depth = 1;
            $args = '';
            for ($i++; $i < $len && $depth > 0; $i++) {
                if ($val[$i] === '(') $depth++;
                if ($val[$i] === ')') $depth--;
                if ($depth > 0) $args .= $val[$i];
            }
            $i--;
            $buf = '';
            if ($name === 'var') {
                $hasvar = true;
                continue;
            }
            if ($name === 'url' || $name === 'format' || $name === 'local') continue;
            if (in_array($name, UI_CFUNC, true)) {
                $out[] = ['raw' => $name.'('.$args.')', 'kind' => 'color'];
                continue;
            }
            if (in_array($name, UI_MFUNC, true)) {
                foreach (getValueParts($args, $hasvar) as $part) {
                    if ($name === 'color-mix' && $part['kind'] === 'length' && str_ends_with($part['raw'], '%')) $part['kind'] = 'mix';
                    $out[] = $part;
                }
                continue;
            }
            if ($name === 'cubic-bezier' || $name === 'steps') {
                $out[] = ['raw' => $name.'('.$args.')', 'kind' => 'ease'];
                continue;
            }
            # The middle of a clamp() is the rate the value travels between its two bounds, and both bounds are
            # decisions of their own. No ladder in the contract carries it: every step is px, seconds or unitless,
            # while a rate is measured against the window. Marked so it is read as arithmetic, like `fr`
            if ($name === 'clamp') {
                $arg = getArgParts($args);
                if (count($arg) === 3) {
                    foreach ([0, 2] as $side) $out = array_merge($out, getValueParts($arg[$side], $hasvar));
                    foreach (getValueParts($arg[1], $hasvar) as $part) {
                        $part['kind'] = 'rate';
                        $out[] = $part;
                    }
                    continue;
                }
            }
            if (in_array($name, UI_WFUNC, true) || $name === '') {
                $out = array_merge($out, getValueParts($args, $hasvar));
                continue;
            }
            continue;
        }
        if ($chr === ' ' || $chr === ',' || $chr === '/') {
            if (trim($buf) !== '') $out[] = ['raw' => trim($buf), 'kind' => getPartKind(trim($buf))];
            $buf = '';
            continue;
        }
        $buf .= $chr;
    }
    if (trim($buf) !== '') $out[] = ['raw' => trim($buf), 'kind' => getPartKind(trim($buf))];
    return $out;
}

# Name the kind of one atom, which is what decides whether the property cares about it
function getPartKind(string $raw): string {
    $low = strtolower($raw);
    if (preg_match('/^#[0-9a-f]{3,8}$/i', $raw)) return 'color';
    if (in_array($low, UI_COLORS, true)) return 'color';
    if (preg_match('/^-?(\d+\.?\d*|\.\d+)(px|em|rem|vh|vw|vmin|vmax|ch|ex|pt|cm|mm|in|pc|%)$/i', $raw)) return 'length';
    if (preg_match('/^-?(\d+\.?\d*|\.\d+)(ms|s)$/i', $raw)) return 'time';
    if (preg_match('/^-?(\d+\.?\d*|\.\d+)(deg|rad|turn|grad)$/i', $raw)) return 'angle';
    if (preg_match('/^-?(\d+\.?\d*|\.\d+)(fr)$/i', $raw)) return 'fraction';
    if (preg_match('/^-?(\d+\.?\d*|\.\d+)$/', $raw)) return 'number';
    if (in_array($low, UI_EASE, true)) return 'ease';
    return 'word';
}

# Reduce a vendor spelling to the property the contract knows
function filterProp(string $prop): string {
    return (string)preg_replace('/^-(webkit|moz|ms|o)-/', '', $prop);
}

# Name the family one property belongs to, which selects the decision rule applied to its atoms
function getPropFamily(string $prop): string {
    $prop = filterProp($prop);
    if (preg_match('/(^|-)(border|outline)-.*radius$|^border-radius$/', $prop)) return 'radius';
    if (preg_match('/^(padding|margin|gap|row-gap|column-gap|scroll-padding|scroll-margin)(-|$)/', $prop)) return 'space';
    if (preg_match('/^(border|outline|column-rule)(-|$)/', $prop)) return 'border';
    if (in_array($prop, ['font-size', 'letter-spacing', 'word-spacing', 'text-indent'], true)) return 'type';
    if (in_array($prop, ['line-height', 'font-weight', 'opacity', 'z-index'], true)) return 'bare';
    if (in_array($prop, ['box-shadow', 'text-shadow', 'filter', 'backdrop-filter'], true)) return 'shadow';
    if (preg_match('/^(transition|animation)(-|$)/', $prop)) return 'motion';
    if (preg_match('/^(width|height|min-width|min-height|max-width|max-height|block-size|inline-size)$/', $prop)) return 'size';
    if (preg_match('/(^|-)color$/', $prop) || in_array($prop, ['background', 'background-image', 'fill', 'stroke'], true)) return 'color';
    if ($prop === 'font') return 'font';
    return 'other';
}

# Decide whether one atom of one declaration is a visual decision that a token must own
function isDecisionPart(string $prop, array $part, string $val): bool {
    $cont = getContract();
    $fam = getPropFamily($prop);
    $raw = strtolower($part['raw']);
    $kind = $part['kind'];
    if ($fam !== 'bare') {
        if (preg_match('/^-?0*\.?0+[a-z%]*$/', $raw)) return false;
        if (isset($cont['allowlist']['values'][$raw]) && $kind !== 'color') return false;
    }
    if ($raw === 'transparent' || $raw === 'currentcolor') return false;
    if ($kind === 'angle' || $kind === 'fraction' || $kind === 'rate') return false;
    # How much of a colour a mix carries is a decision whatever property reads the result
    if ($kind === 'mix') return true;
    # A percentage gap is measured against the width of the container, not against the rhythm of the page. Every
    # step of the spacing ladder is a pixel figure, so no step can express one and no rename would give it an address
    if ($fam === 'space') return $kind === 'length' && $raw !== '0' && !str_ends_with($raw, '%');
    if ($fam === 'radius') return $kind === 'length' && $raw !== '50%';
    if ($fam === 'border') {
        if ($kind === 'color') return true;
        if ($kind !== 'length') return false;
        if ($raw === '1px') return false;
        return !preg_match('/^-?[\d.]+(px|em|rem) solid transparent$/i', $val);
    }
    if ($fam === 'type') return $kind === 'length';
    if ($fam === 'bare') {
        $prop = filterProp($prop);
        if ($kind === 'number') return !($raw === '0' && $prop !== 'z-index') && !($raw === '1' && $prop !== 'z-index');
        return $kind === 'word' && in_array($raw, UI_WORDS, true);
    }
    if ($fam === 'shadow') return $kind === 'color' || $kind === 'length';
    if ($fam === 'motion') return $kind === 'time' || $kind === 'ease';
    if ($fam === 'size') return $kind === 'length';
    if ($fam === 'color') return $kind === 'color';
    if ($fam === 'font') return $kind === 'length';
    return $kind === 'color' || $kind === 'length' || $kind === 'time';
}

# Count the decisions one declaration leaves untokenised; a shadow is one decision, not one per offset
function getDeclCount(string $prop, string $val, bool &$hasvar = false): array {
    $cont = getContract();
    if (str_starts_with($prop, '--')) return [];
    if (isset($cont['allowlist']['properties'][filterProp($prop)])) return [];
    $val = filterValue($val);
    $hasvar = false;
    $parts = getValueParts($val, $hasvar);
    $out = [];
    foreach ($parts as $part) if (isDecisionPart($prop, $part, $val)) $out[] = $part;
    if (getPropFamily($prop) === 'shadow' && count($out) > 1) $out = [$out[0]];
    return $out;
}

# Build one theme model: its files, its API tokens and every rule outside the API block
function getThemeModel(string $name): array {
    $cont = getContract();
    if (!isset($cont['themes'][$name])) {
        fwrite(STDERR, 'Unknown theme: '.$name."\n");
        exit(2);
    }
    $conf = $cont['themes'][$name];
    $list = [];
    foreach ($conf['css'] as $path) {
        $text = getFileText($path);
        if ($text !== '') $list[$path] = $text;
    }
    $out = getTextModel($list, $conf['api']);
    $out['name'] = $name;
    $out['conf'] = $conf;
    return $out;
}

# Build one model from CSS already in hand, which is what lets the tool be tested on fixtures with known answers
function getTextModel(array $list, string $api = ''): array {
    $cont = getContract();
    $out = ['name' => 'fixture', 'conf' => [], 'api' => [], 'rules' => [], 'files' => [], 'marker' => false, 'scoped' => [], 'clash' => []];
    foreach ($list as $path => $text) {
        $text = str_replace("\r\n", "\n", $text);
        $mark = strpos($text, $cont['marker']);
        if ($path === $api) $out['marker'] = $mark !== false;
        $body = filterComments($text);
        $offs = getLineIndex($body);
        $out['files'][$path] = ['text' => $text, 'body' => $body];
        foreach (getCssRules($body) as $rule) {
            $rule['file'] = $path;
            $rule['line'] = getLineNumber($offs, $rule['pos']);
            $rule['api'] = $path === $api && $rule['sel'] === ':root' && $rule['ctx'] === '' && ($mark === false || $rule['pos'] < $mark);
            $rule['decls'] = getCssDecls($rule['body']);
            if ($rule['api']) {
                foreach ($rule['decls'] as $decl) {
                    if (!str_starts_with($decl['prop'], '--')) continue;
                    $val = filterValue($decl['val']);
                    if (isset($out['api'][$decl['prop']])) $out['clash'][] = ['name' => $decl['prop'], 'was' => $out['api'][$decl['prop']], 'now' => $val, 'line' => $rule['line']];
                    $out['api'][$decl['prop']] = $val;
                }
                continue;
            }
            # A descriptor block is not a rule. @font-face and its kin hold descriptors, where var() is invalid,
            # so a figure inside one is not a decision a theme can move. @media and @keyframes never reach here
            if (str_starts_with($rule['sel'], '@')) continue;
            foreach ($rule['decls'] as $decl) {
                if (!str_starts_with($decl['prop'], '--')) continue;
                $out['scoped'][] = ['name' => $decl['prop'], 'sel' => $rule['sel'], 'file' => $path, 'line' => $rule['line']];
            }
            $out['rules'][] = $rule;
        }
    }
    return $out;
}

# Every untokenised decision of one theme, with the file, line, selector and replacing token
function checkThemeCount(array $model): array {
    $out = ['sites' => [], 'byprop' => [], 'byfile' => [], 'half' => 0, 'tokenised' => 0];
    foreach ($model['rules'] as $rule) {
        foreach ($rule['decls'] as $decl) {
            $hasvar = false;
            $hits = getDeclCount($decl['prop'], $decl['val'], $hasvar);
            if ($hits === [] && $hasvar) $out['tokenised']++;
            if ($hits !== [] && $hasvar) $out['half']++;
            foreach ($hits as $part) {
                $prop = filterProp($decl['prop']);
                $out['sites'][] = [
                    'file' => $rule['file'], 'line' => $rule['line'], 'sel' => $rule['sel'],
                    'prop' => $prop, 'raw' => $part['raw'], 'kind' => $part['kind'],
                    'fix' => getReplacement($decl['prop'], $part, $model),
                ];
                $out['byprop'][$prop] = ($out['byprop'][$prop] ?? 0) + 1;
                $out['byfile'][$rule['file']] = ($out['byfile'][$rule['file']] ?? 0) + 1;
            }
        }
    }
    arsort($out['byprop']);
    arsort($out['byfile']);
    return $out;
}

# Name the token that replaces one violation, because an error that only says "literal found" gets switched off
function getReplacement(string $prop, array $part, array $model): string {
    $fam = getPropFamily($prop);
    $raw = strtolower($part['raw']);
    if ($part['kind'] === 'mix') return 'a --sl-<component>-mix token in base.css';
    if ($part['kind'] === 'color') {
        foreach ($model['api'] as $name => $val) if (strtolower($val) === $raw) return 'var('.$name.')';
        return 'a colour token in base.css';
    }
    if ($fam === 'space') return getLadderToken('space', $raw);
    if ($fam === 'radius') return getLadderToken('border-radius', $raw);
    if ($fam === 'type' && filterProp($prop) === 'font-size') return getLadderToken('font-size', $raw);
    if ($fam === 'type') return 'var(--sl-track-normal)';
    if ($fam === 'motion' && $part['kind'] === 'time') return getLadderToken('transition', $raw);
    if ($fam === 'motion') return 'var(--sl-ease-out)';
    if ($fam === 'shadow') return 'var(--sl-shadow-raised)';
    if ($fam === 'size') return 'var(--sl-size-control) or a component token';
    if ($fam === 'bare') {
        $prop = filterProp($prop);
        if ($prop === 'z-index') return 'var(--sl-z-dropdown) or the layer it belongs to';
        if ($prop === 'opacity') return getLadderToken('opacity', $raw);
        if ($prop === 'line-height') return getLadderToken('line-height', getWordValue($raw, 'line-height'));
        return getLadderToken('font-weight', getWordValue($raw, 'font-weight'));
    }
    if ($fam === 'font') return 'var(--sl-font-body) with var(--sl-line-normal)';
    return 'a token in base.css';
}

# Spell a keyword as the number its ladder holds, so normal and 400 reach the same step
function getWordValue(string $raw, string $prop): string {
    if ($prop === 'font-weight') return match ($raw) { 'normal' => '400', 'bold' => '700', 'bolder' => '700', 'lighter' => '400', default => $raw };
    return $raw === 'normal' ? '1.45' : $raw;
}

# The ladder token nearest to one value, in the ladder unit
function getLadderToken(string $ladder, string $raw): string {
    $cont = getContract();
    if (!isset($cont['ladders'][$ladder])) return 'a token in base.css';
    $num = getNumberValue($raw, $cont['ladders'][$ladder]['unit']);
    if ($num === null) return 'var('.$cont['ladders'][$ladder]['tokens'][0].')';
    $best = 0;
    $near = null;
    foreach ($cont['ladders'][$ladder]['steps'] as $idx => $step) {
        $gap = abs($step - $num);
        if ($near === null || $gap < $near) {
            $near = $gap;
            $best = $idx;
        }
    }
    return 'var('.$cont['ladders'][$ladder]['tokens'][$best].')';
}

# Read one value as a number in the ladder unit, converting em and rem against a 16px root
function getNumberValue(string $raw, string $unit): ?float {
    if (!preg_match('/^(-?(?:\d+\.?\d*|\.\d+))([a-z%]*)$/i', trim($raw), $hit)) return null;
    $num = (float)$hit[1];
    $has = strtolower($hit[2]);
    if ($unit === 'px' && ($has === 'em' || $has === 'rem')) return $num * 16;
    if ($unit === 's' && $has === 'ms') return $num / 1000;
    return $num;
}

# Bare numbers in the four properties that carry no unit and no colour, so the first counter is blind to them
function checkBareValues(array $model): array {
    $out = ['sites' => [], 'byprop' => []];
    foreach ($model['rules'] as $rule) {
        foreach ($rule['decls'] as $decl) {
            $prop = filterProp($decl['prop']);
            if (!in_array($prop, ['line-height', 'font-weight', 'opacity', 'z-index'], true)) continue;
            $val = filterValue($decl['val']);
            if (str_contains($val, 'var(')) continue;
            if ($prop !== 'z-index' && ($val === '0' || $val === '1')) continue;
            if (!preg_match('/^-?[\d.]+$/', $val) && !in_array(strtolower($val), UI_WORDS, true)) continue;
            $out['sites'][] = ['file' => $rule['file'], 'line' => $rule['line'], 'sel' => $rule['sel'], 'prop' => $prop, 'raw' => $val];
            $out['byprop'][$prop] = ($out['byprop'][$prop] ?? 0) + 1;
        }
    }
    arsort($out['byprop']);
    return $out;
}

# One property's value distribution, which is what places a ladder step where values already cluster
function getDistList(array $model, string $prop): array {
    $out = [];
    foreach ($model['rules'] as $rule) {
        foreach ($rule['decls'] as $decl) {
            if (filterProp($decl['prop']) !== $prop) continue;
            $val = filterValue($decl['val']);
            $hasvar = false;
            foreach (getValueParts($val, $hasvar) as $part) {
                if (!isDecisionPart($decl['prop'], $part, $val)) continue;
                $out[strtolower($part['raw'])] = ($out[strtolower($part['raw'])] ?? 0) + 1;
            }
        }
    }
    arsort($out);
    return $out;
}

# Rule bodies repeated verbatim inside one @media context; a body repeated across contexts is not a duplicate
function checkDupBlocks(array $model): array {
    $seen = [];
    foreach ($model['rules'] as $rule) {
        $keys = [];
        foreach ($rule['decls'] as $decl) $keys[] = $decl['prop'].':'.filterValue($decl['val']);
        if ($keys === []) continue;
        $key = $rule['ctx']."\x00".implode(';', $keys);
        $seen[$key][] = ['sel' => $rule['sel'], 'file' => $rule['file'], 'line' => $rule['line'], 'ctx' => $rule['ctx'], 'body' => implode('; ', $keys)];
    }
    $out = ['groups' => [], 'blocks' => 0, 'inmedia' => 0];
    $cont = getContract();
    foreach ($seen as $list) {
        if (count($list) < 2) continue;
        $sels = array_map(fn($v) => $v['sel'], $list);
        sort($sels);
        if (in_array(implode(', ', $sels), $cont['duplicates'], true)) continue;
        $out['groups'][] = $list;
        $out['blocks'] += count($list) - 1;
        if ($list[0]['ctx'] !== '') $out['inmedia'] += count($list) - 1;
    }
    return $out;
}

# Split one token name into the segments the grammar counts
function getNameParts(string $name): array {
    $cont = getContract();
    return explode('-', substr($name, strlen($cont['prefix'])));
}

# Grammar violations over the declared tokens: a name that cannot invert, names its value, or is unregistered
function checkNameGrammar(array $model): array {
    $cont = getContract();
    $out = [];
    foreach (array_keys($model['api']) as $name) {
        if (!str_starts_with($name, $cont['prefix'])) {
            $out[] = ['name' => $name, 'why' => 'outside the '.$cont['prefix'].' namespace'];
            continue;
        }
        $part = getNameParts($name);
        $low = strtolower($name);
        if (count($part) > 3) $out[] = ['name' => $name, 'why' => count($part).' segments after '.$cont['prefix'].', the grammar allows three'];
        if (preg_match('/-(white|black|light|lighter|dark|darker)(-|$)/', $low)) $out[] = ['name' => $name, 'why' => 'the name cannot survive inversion; say the role it plays'];
        if (preg_match('/-(hover|focus|active|visited)(-|$)/', $low) && !isKnownName($name)) $out[] = ['name' => $name, 'why' => 'state is not an axis; it lives in the selector'];
        if (preg_match('/-(soft|muted|subtle|strong)-(soft|muted|subtle|strong)(-|$)/', $low)) $out[] = ['name' => $name, 'why' => 'modifiers do not stack'];
        if (!isKnownName($name)) $out[] = ['name' => $name, 'why' => 'not registered in tools/ui-contract.php'];
    }
    return $out;
}

# Whether one token name is registered: an axis role, a declared component prop, a primitive step, a set member or a data token
function isKnownName(string $name): bool {
    $cont = getContract();
    if (isset($cont['data'][$name])) return true;
    $tail = substr($name, strlen($cont['prefix']));
    foreach ($cont['axes'] as $axis) {
        $head = $axis['prefix'] === '' ? '' : $axis['prefix'].'-';
        if ($head !== '' && !str_starts_with($tail, $head)) continue;
        $rest = substr($tail, strlen($head));
        foreach ($axis['roles'] as $role) {
            if ($rest === $role) return true;
            foreach ($axis['steps'] ?? [] as $step) if ($step !== '' && $rest === $role.'-'.$step) return true;
        }
    }
    foreach ($cont['categorical'] as $set => $conf) {
        foreach ($conf['members'] as $item) if ($tail === $set.'-'.$item) return true;
    }
    foreach ($cont['components'] as $item) {
        if (!str_starts_with($tail, $item.'-')) continue;
        if (in_array(substr($tail, strlen($item) + 1), $cont['props'], true)) return true;
    }
    if (preg_match('/^(gray|blue|green|red|orange|teal|violet)-(50|100|200|300|400|500|600|700|800|900)$/', $tail)) return true;
    return false;
}

# Convert one colour value to red, green and blue, or null when it is not a plain colour.
# A colour carrying both modes is read in the half $mode names, because every check downstream - the ramp, the
# distinguishability of a categorical set - measures one mode at a time and a two-mode value would silently skip it
function getRgbValues(string $val, string $mode = 'light'): ?array {
    $val = trim(strtolower($val));
    if (preg_match('/^light-dark\(\s*(.+?)\s*,\s*(.+?)\s*\)$/is', $val, $part)) return getRgbValues($mode === 'dark' ? $part[2] : $part[1], $mode);
    if (preg_match('/^#([0-9a-f]{3})$/', $val, $hit)) {
        $hex = $hit[1];
        return [hexdec($hex[0].$hex[0]), hexdec($hex[1].$hex[1]), hexdec($hex[2].$hex[2])];
    }
    if (preg_match('/^#([0-9a-f]{6})([0-9a-f]{2})?$/', $val, $hit)) {
        $hex = $hit[1];
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
    if (preg_match('/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)/', $val, $hit)) return [(float)$hit[1], (float)$hit[2], (float)$hit[3]];
    return null;
}

# Hue, saturation and lightness of one colour, the axes a ramp is clustered on
function getHslValues(array $rgb): array {
    $red = $rgb[0] / 255;
    $grn = $rgb[1] / 255;
    $blu = $rgb[2] / 255;
    $max = max($red, $grn, $blu);
    $min = min($red, $grn, $blu);
    $lum = ($max + $min) / 2;
    if ($max === $min) return [0.0, 0.0, $lum * 100];
    $gap = $max - $min;
    $sat = $lum > 0.5 ? $gap / (2 - $max - $min) : $gap / ($max + $min);
    $hue = match (true) {
        $max === $red => (($grn - $blu) / $gap + ($grn < $blu ? 6 : 0)),
        $max === $grn => (($blu - $red) / $gap + 2),
        default => (($red - $grn) / $gap + 4),
    };
    return [$hue * 60, $sat * 100, $lum * 100];
}

# Name the colour family one hue belongs to, after saturation has already separated the neutrals.
# Chroma decides beside the ratio: HSL saturation is chroma divided by what the lightness still allows,
# so a near-white with three points of chroma reads 100 and would be filed as a colour it is not
function getFamilyName(float $hue, float $sat, float $chr, array $conf): string {
    if ($sat < $conf['saturation'] || $chr < $conf['chroma']) return 'gray';
    return match (true) {
        $hue < 15 || $hue >= 330 => 'red',
        $hue < 45 => 'orange',
        $hue < 70 => 'yellow',
        $hue < 160 => 'green',
        $hue < 200 => 'teal',
        $hue < 260 => 'blue',
        default => 'violet',
    };
}

# The colour ramp of one theme, clustered by saturation first and hue second, with each step in its role
function getColorRamp(array $model): array {
    $cont = getContract();
    $out = [];
    foreach ($model['api'] as $name => $val) {
        $rgb = getRgbValues($val);
        if ($rgb === null) continue;
        $hsl = getHslValues($rgb);
        $chr = (max($rgb) - min($rgb)) / 255 * 100;
        $fam = getFamilyName($hsl[0], $hsl[1], $chr, $cont['ramp']);
        $out[$fam][] = ['name' => $name, 'val' => $val, 'hue' => round($hsl[0], 1), 'sat' => round($hsl[1], 1), 'chr' => round($chr, 1), 'lum' => round($hsl[2], 1)];
    }
    foreach ($out as $fam => $list) {
        usort($list, fn($one, $two) => $two['lum'] <=> $one['lum']);
        $step = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900'];
        $size = count($list);
        foreach ($list as $idx => $item) {
            $slot = $size < 2 ? 0 : (int)round($idx * (count($step) - 1) / ($size - 1));
            $list[$idx]['step'] = $step[$slot];
            $list[$idx]['role'] = $cont['ramp']['roles'][$step[$slot]];
        }
        $out[$fam] = $list;
    }
    ksort($out);
    return $out;
}

# Tokens declared but never read, read exactly once, or aliasing another alias
function checkTokenUse(array $model): array {
    $cont = getContract();
    $text = '';
    foreach ($model['files'] as $file) $text .= $file['body']."\n";
    foreach ($cont['places'] as $path) $text .= getFileText($path)."\n";
    $out = ['dead' => [], 'single' => [], 'alias' => [], 'unsat' => []];
    foreach ($model['api'] as $name => $val) {
        $hits = substr_count($text, 'var('.$name.')') + substr_count($text, 'var('.$name.',');
        if ($hits === 0) $out['dead'][] = $name;
        if ($hits === 1) $out['single'][] = $name;
        if (preg_match('/^var\((--[a-z0-9-]+)\)$/i', $val, $hit)) {
            $next = $model['api'][$hit[1]] ?? '';
            if (preg_match('/^var\(--[a-z0-9-]+\)$/i', $next)) $out['alias'][] = $name.' -> '.$hit[1].' -> '.$next;
        }
    }
    foreach ($model['rules'] as $rule) {
        foreach ($rule['decls'] as $decl) {
            if (!preg_match('/^var\((--[a-z0-9-]+)\)$/i', filterValue($decl['val']), $hit)) continue;
            $val = $model['api'][$hit[1]] ?? null;
            if ($val === null) continue;
            $want = getPropFamily($decl['prop']);
            $kind = getValueKind(getResolvedValue($val, $model['api']));
            $bad = ($want === 'shadow' && $kind === 'color')
                || (in_array($want, ['space', 'radius', 'type', 'size'], true) && $kind === 'color')
                || ($want === 'color' && in_array($kind, ['shadow', 'length'], true));
            if ($bad) $out['unsat'][] = ['name' => $hit[1], 'prop' => $decl['prop'], 'file' => $rule['file'], 'line' => $rule['line'], 'kind' => $kind];
        }
    }
    return $out;
}

# Names a theme reads and declares nowhere. `dead` is the other half of this: a token declared and never
# read is loud, while a name read and never declared is silent - CSS answers an unknown var() by dropping
# the whole declaration, with no error, no warning and no pixel that says why. A read carrying a fallback
# is not counted: the author said what happens when the name is absent. A name registered under `data` is
# not counted either, because something outside CSS writes it, which is exactly what that list records
function checkUnmetNames(array $model): array {
    $cont = getContract();
    $known = $model['api'];
    foreach ($model['scoped'] as $item) $known[$item['name']] = true;
    $out = [];
    foreach ($model['files'] as $path => $file) {
        if (!preg_match_all('/var\(\s*(--[a-z0-9-]+)\s*\)/i', $file['body'], $hits, PREG_OFFSET_CAPTURE)) continue;
        foreach ($hits[1] as $hit) {
            $name = strtolower($hit[0]);
            if (isset($known[$name]) || isset($cont['data'][$name])) continue;
            $out[$name][] = $path;
        }
    }
    ksort($out);
    return $out;
}

# Follow a chain of plain aliases to the value it ends on, so an alias is judged by what it finally holds
function getResolvedValue(string $val, array $api): string {
    for ($i = 0; $i < 8; $i++) {
        if (!preg_match('/^var\((--[a-z0-9-]+)\)$/i', filterValue($val), $hit)) return $val;
        if (!isset($api[$hit[1]])) return $val;
        $val = $api[$hit[1]];
    }
    return $val;
}

# Whether one value is a shadow: an offset list, where a colour and a length may each arrive through a token.
# Reading the colour off a literal alone would file a shadow built from the theme's own scrim or ring as something
# else, and the same name would then hold two kinds across two themes for no reason a reader could see
function isShadowValue(string $val): bool {
    if (str_contains(strtolower($val), 'gradient(')) return false;
    foreach (getArgParts($val) as $layer) {
        $atom = preg_split('/\s+/', trim((string)preg_replace('/\b(var|rgba?|hsla?|color-mix|light-dark|calc)\([^()]*(\([^()]*\)[^()]*)*\)/i', 'X', $layer)));
        if (count($atom) < 3) return false;
    }
    return (bool)preg_match('/^inset\s/i', $val)
        || (bool)(preg_match('/(^|\s)-?[\d.]+(px|em|rem)(\s|$)/i', $val) && preg_match('/(#|rgba?\(|hsla?\(|currentcolor|var\()/i', $val))
        || (bool)(preg_match('/^[0\s]+var\(/i', $val) && preg_match('/var\(.*var\(/is', $val));
}

# Name the kind of one token value, which is how one name holding two kinds across themes is caught
function getValueKind(string $val): string {
    $val = filterValue($val);
    if ($val === '') return 'empty';
    if (str_contains($val, 'gradient(')) return 'gradient';
    if (getRgbValues($val) !== null) return 'color';
    if (preg_match('/^#|^(rgb|hsl)a?\(/i', $val)) return 'color';
    # A colour carrying both modes and a colour mixed from two others are still colours; without this a token
    # that gains its dark half reads as a different kind from the same name in a theme that has not gained it yet
    if (preg_match('/^(light-dark|color-mix)\(/i', $val)) return 'color';
    if (isShadowValue($val)) return 'shadow';
    if (getPartKind($val) === 'length') return 'length';
    if (getPartKind($val) === 'time') return 'time';
    if (getPartKind($val) === 'number') return 'number';
    if (str_starts_with($val, 'var(')) return 'alias';
    return 'other';
}

# One name declared in both themes holding values of a different kind: a rule written against one is wrong in the other
function checkNameKinds(): array {
    $cont = getContract();
    $seen = [];
    foreach (array_keys($cont['themes']) as $name) {
        $api = getThemeModel($name)['api'];
        foreach ($api as $tok => $val) $seen[$tok][$name] = getValueKind(getResolvedValue($val, $api));
    }
    $out = [];
    foreach ($seen as $tok => $list) {
        if (count($list) < 2) continue;
        if (count(array_unique($list)) === 1) continue;
        $out[] = ['name' => $tok, 'kinds' => $list];
    }
    return $out;
}

# Classes a theme paints against what the repository names. A class assembled from a prefix and a suffix
# is named nowhere in one piece, so a prefix match is reported apart instead of counted as dead
function checkClassUse(array $model): array {
    $seen = [];
    foreach ($model['rules'] as $rule) {
        if (preg_match_all('/\.(sl-[a-z0-9-]+)/i', $rule['sel'], $hits)) foreach ($hits[1] as $item) $seen[$item] = true;
    }
    $text = '';
    foreach (getRepoFiles(['html', 'php', 'js', 'json'], ['templates', 'admin', 'core', 'modules', 'plugins', 'config']) as $path) $text .= getFileText($path)."\n";
    $out = ['unused' => [], 'composed' => []];
    foreach (array_keys($seen) as $item) {
        if (preg_match('/[\'"\s>({\[.]'.preg_quote($item, '/').'(?![a-z0-9-])/i', $text)) continue;
        $part = explode('-', $item);
        $made = false;
        for ($i = count($part) - 1; $i >= 2; $i--) {
            $head = implode('-', array_slice($part, 0, $i)).'-';
            if (str_contains($text, $head)) {
                $made = true;
                break;
            }
        }
        $out[$made ? 'composed' : 'unused'][] = $item;
    }
    sort($out['unused']);
    sort($out['composed']);
    return $out;
}

# Every repository file of the given extensions under the given directories, vendor and storage excluded
function getRepoFiles(array $exts, array $dirs): array {
    $out = [];
    foreach ($dirs as $dir) {
        $full = UI_ROOT.'/'.$dir;
        if (!is_dir($full)) continue;
        $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));
        foreach ($walk as $item) {
            $path = str_replace('\\', '/', $item->getPathname());
            if (preg_match('#/(vendor|node_modules|storage|\.git)/#', $path)) continue;
            if (!in_array(strtolower($item->getExtension()), $exts, true)) continue;
            $out[] = substr($path, strlen(str_replace('\\', '/', UI_ROOT)) + 1);
        }
    }
    sort($out);
    return $out;
}

# Selectors and templates that differ between the two themes, which is what canon has to reconcile
function checkCrossTheme(): array {
    $cont = getContract();
    $keys = array_keys($cont['themes']);
    $side = [];
    foreach ($keys as $name) {
        $model = getThemeModel($name);
        foreach ($model['rules'] as $rule) {
            if (str_contains($rule['file'], 'skin.css')) continue;
            $body = [];
            foreach ($rule['decls'] as $decl) $body[] = $decl['prop'].':'.filterValue($decl['val']);
            $side[$name][$rule['ctx']."\x00".$rule['sel']] = implode(';', $body);
        }
    }
    $out = ['same' => 0, 'diff' => [], 'shared' => 0, 'templates' => []];
    foreach ($side[$keys[0]] as $key => $body) {
        if (!isset($side[$keys[1]][$key])) continue;
        $out['shared']++;
        if ($side[$keys[1]][$key] === $body) $out['same']++;
        else $out['diff'][] = str_replace("\x00", ' ', $key);
    }
    foreach (['fragments', 'partials', 'layouts', 'pages'] as $dir) {
        $one = UI_ROOT.'/'.$cont['themes'][$keys[0]]['root'].'/'.$dir;
        $two = UI_ROOT.'/'.$cont['themes'][$keys[1]]['root'].'/'.$dir;
        if (!is_dir($one) || !is_dir($two)) continue;
        $stat = ['shared' => 0, 'same' => 0, 'diff' => []];
        foreach (scandir($one) ?: [] as $file) {
            if (!is_file($one.'/'.$file) || !is_file($two.'/'.$file)) continue;
            $stat['shared']++;
            if (file_get_contents($one.'/'.$file) === file_get_contents($two.'/'.$file)) $stat['same']++;
            else $stat['diff'][] = $dir.'/'.$file;
        }
        $out['templates'][$dir] = $stat;
    }
    return $out;
}

# Class attributes, inline styles and HTML tags hardcoded in PHP, found by tokenising and folding concatenations
function checkPhpMarkup(): array {
    $cont = getContract();
    $out = [];
    foreach (getRepoFiles(['php'], ['admin', 'core', 'modules', 'plugins']) as $path) {
        $skip = false;
        foreach (array_keys($cont['markup']['exclude']) as $item) if (str_contains($path, $item)) $skip = true;
        if ($skip) continue;
        $hits = getFileMarkup($path);
        if ($hits['class'] + $hits['style'] + $hits['tag'] > 0) $out[$path] = $hits;
    }
    uasort($out, fn($one, $two) => ($two['class'] + $two['style'] + $two['tag']) <=> ($one['class'] + $one['style'] + $one['tag']));
    return $out;
}

# What one PHP file hardcodes, counted over its folded string literals
function getFileMarkup(string $path): array {
    $out = ['class' => 0, 'style' => 0, 'tag' => 0, 'lines' => []];
    $text = getFileText($path);
    if ($text === '') return $out;
    foreach (getFoldedStrings($text) as $item) {
        $kind = getMarkupKind($item['text']);
        if ($kind === '') continue;
        $out[$kind]++;
        $out['lines'][] = ['line' => $item['line'], 'kind' => $kind, 'text' => substr(trim($item['text']), 0, 70)];
    }
    return $out;
}

# String literals of one PHP file with constant concatenations folded, so '<di'.'v>' is read as one string
function getFoldedStrings(string $text): array {
    $list = token_get_all($text);
    $out = [];
    $buf = '';
    $line = 0;
    $open = false;
    foreach ($list as $item) {
        if (is_array($item) && in_array($item[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
        if (is_array($item) && $item[0] === T_CONSTANT_ENCAPSED_STRING) {
            $val = substr($item[1], 1, -1);
            if ($open) $buf .= $val;
            else {
                $buf = $val;
                $line = $item[2];
                $open = true;
            }
            continue;
        }
        if ($item === '.' && $open) continue;
        if ($open) {
            $out[] = ['text' => $buf, 'line' => $line];
            $buf = '';
            $open = false;
        }
    }
    if ($open) $out[] = ['text' => $buf, 'line' => $line];
    return $out;
}

# Name what one string literal hardcodes: a class attribute, an inline style or an HTML tag.
# A regular expression that matches markup does not emit it, and an XML element is not something a theme styles,
# so the tag test names HTML elements instead of accepting anything shaped like a tag
function getMarkupKind(string $text): string {
    if (preg_match('/^([#~\/%@!+])(.*)\1[imsuxADSUXJn]*$/s', $text) && preg_match('/[\[\](){}\\\\|^$*+?]/', $text)) return '';
    if (preg_match('/\bclass\s*=\s*["\']/i', $text)) return 'class';
    if (preg_match('/\bstyle\s*=\s*["\']/i', $text)) return 'style';
    if (preg_match('#</?('.implode('|', UI_TAGS).')(\s[^<>]*)?/?>#i', $text)) return 'tag';
    return '';
}

# Contrast of two colours by the WCAG relative luminance formula
function getContrastRatio(array $one, array $two): float {
    $lum = static function (array $rgb): float {
        $out = 0.0;
        foreach ([0.2126, 0.7152, 0.0722] as $idx => $part) {
            $val = $rgb[$idx] / 255;
            $out += $part * ($val <= 0.03928 ? $val / 12.92 : (($val + 0.055) / 1.055) ** 2.4);
        }
        return $out;
    };
    $one = $lum($one);
    $two = $lum($two);
    return (max($one, $two) + 0.05) / (min($one, $two) + 0.05);
}

# Contrast over the pairs the crawler saw meet on screen, never over the cross product of every token
function checkContrastPairs(string $theme): array {
    $cont = getContract();
    $out = [];
    foreach ($cont['contrast']['pairs'] as $pair) {
        if (($pair['theme'] ?? '') !== $theme) continue;
        $one = getRgbValues($pair['fg'] ?? '');
        $two = getRgbValues($pair['bg'] ?? '');
        if ($one === null || $two === null) continue;
        $big = ($pair['size'] ?? 0) >= $cont['contrast']['large']['size'] || (($pair['size'] ?? 0) >= $cont['contrast']['large']['boldsize'] && ($pair['weight'] ?? 400) >= 700);
        $want = $big ? $cont['contrast']['aalarge'] : $cont['contrast']['aa'];
        $rate = getContrastRatio($one, $two);
        if ($rate + 0.005 >= $want) continue;
        $out[] = ['sel' => $pair['sel'] ?? '', 'mode' => $pair['mode'] ?? '', 'fg' => $pair['fg'], 'bg' => $pair['bg'], 'ratio' => round($rate, 2), 'want' => $want];
    }
    return $out;
}

# Every count one theme is judged on, in the shape the baseline stores
function getThemeAudit(string $name): array {
    $model = getThemeModel($name);
    $cnt = checkThemeCount($model);
    $bare = checkBareValues($model);
    $dup = checkDupBlocks($model);
    $names = checkNameGrammar($model);
    $use = checkTokenUse($model);
    $unmet = checkUnmetNames($model);
    $class = checkClassUse($model);
    $fail = checkContrastPairs($name);
    $text = '';
    foreach ($model['files'] as $file) $text .= $file['body']."\n";
    return [
        'model' => $model,
        'count' => $cnt,
        'bare' => $bare,
        'dup' => $dup,
        'names' => $names,
        'use' => $use,
        'unmet' => $unmet,
        'classes' => $class,
        'contrast' => $fail,
        'totals' => [
            'count' => count($cnt['sites']),
            'bare' => count($bare['sites']),
            'dup' => $dup['blocks'],
            'names' => count($names),
            'dead' => count($use['dead']),
            'single' => count($use['single']),
            'alias' => count($use['alias']),
            'unsat' => count($use['unsat']),
            'unmet' => count($unmet),
            'scoped' => count($model['scoped']),
            'clash' => count($model['clash']),
            'classes' => count($class['unused']),
            'important' => substr_count($text, '!important'),
            'tokens' => count($model['api']),
            'contrast' => count($fail),
        ],
    ];
}

# Read the committed baseline, the only thing that makes the ratchet survive a clone
function getBaselineData(): array {
    if (!is_file(UI_BASE)) return [];
    return json_decode((string)file_get_contents(UI_BASE), true) ?: [];
}

# Write the baseline, refusing to lower a number while the tree is still above it
function setBaselineData(array $now): int {
    $old = getBaselineData();
    $bad = [];
    $held = getContract()['ratchet'];
    foreach ($now['themes'] as $name => $list) {
        foreach ($list as $key => $val) {
            $was = $old['themes'][$name][$key] ?? null;
            if ($was !== null && $val > $was && in_array($key, $held, true)) $bad[] = $name.'.'.$key.': '.$val.' is above the stored '.$was;
        }
    }
    foreach ($now['global'] ?? [] as $key => $val) {
        $was = $old['global'][$key] ?? null;
        if ($was !== null && $val > $was) $bad[] = 'global.'.$key.': '.$val.' is above the stored '.$was;
    }
    if ($bad !== []) {
        fwrite(STDERR, "Refusing to store a baseline while a count is above the stored one:\n  ".implode("\n  ", $bad)."\n");
        return 1;
    }
    file_put_contents(UI_BASE, json_encode($now, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    echo 'Stored '.UI_BASE."\n";
    return 0;
}

# Print one report section only when it has something to say
function setSection(string $head, array $lines): void {
    if ($lines === []) return;
    echo "\n".$head."\n";
    foreach ($lines as $line) echo '  '.$line."\n";
}

# Parse the command line into flags and their values
function getArgList(array $argv): array {
    $out = [];
    foreach (array_slice($argv, 1) as $item) {
        if (!str_starts_with($item, '--')) continue;
        $cut = strpos($item, '=');
        if ($cut === false) $out[substr($item, 2)] = true;
        else $out[substr($item, 2, $cut - 2)] = substr($item, $cut + 1);
    }
    return $out;
}

# Audit one file on its own, for the edit hook: every violation with the token that replaces it
function checkOneFile(string $path, bool $soft): int {
    $cont = getContract();
    $path = str_replace('\\', '/', realpath($path) ?: $path);
    $root = str_replace('\\', '/', realpath(UI_ROOT) ?: UI_ROOT);
    if (stripos($path, $root) === 0) $path = ltrim(substr($path, strlen($root)), '/');
    $name = '';
    foreach ($cont['themes'] as $key => $conf) if (in_array($path, $conf['css'], true)) $name = $key;
    if ($name === '') {
        echo 'Not a theme CSS file, nothing to check: '.$path."\n";
        return 0;
    }
    $model = getThemeModel($name);
    $cnt = checkThemeCount($model);
    $out = [];
    foreach ($cnt['sites'] as $site) {
        if ($site['file'] !== $path) continue;
        $out[] = $path.':'.$site['line'].'  '.$site['sel'].' { '.$site['prop'].': '.$site['raw'].' }  ->  '.$site['fix'];
    }
    if ($out === []) {
        echo $path.': no untokenised decision.'."\n";
        return 0;
    }
    echo $path.': '.count($out).' untokenised decision'.(count($out) === 1 ? '' : 's').".\n";
    foreach (array_slice($out, 0, 40) as $line) echo '  '.$line."\n";
    if (count($out) > 40) echo '  ... '.(count($out) - 40)." more\n";
    return $soft ? 0 : 1;
}

if (PHP_SAPI !== 'cli' || realpath((string)($argv[0] ?? '')) !== realpath(__FILE__)) return;

$args = getArgList($argv);
$cont = getContract();

if (isset($args['file'])) exit(checkOneFile((string)$args['file'], isset($args['migrating'])));

if (isset($args['markup'])) {
    $list = checkPhpMarkup();
    $sum = ['class' => 0, 'style' => 0, 'tag' => 0];
    echo "Markup hardcoded in PHP, by file:\n";
    foreach ($list as $path => $hits) {
        echo '  '.str_pad($path, 46).' class '.$hits['class'].'  style '.$hits['style'].'  tags '.$hits['tag']."\n";
        foreach ($sum as $key => $val) $sum[$key] = $val + $hits[$key];
    }
    echo "\n  files ".count($list).', occurrences '.array_sum($sum).' ('.$sum['class'].' class, '.$sum['style'].' style, '.$sum['tag'].' tags)'."\n";
    exit(array_sum($sum) > ($cont['markup']['limit'] ?? PHP_INT_MAX) ? 1 : 0);
}

if (isset($args['cross'])) {
    $data = checkCrossTheme();
    echo 'Selectors in both themes: '.$data['shared'].', identical '.$data['same'].', divergent '.count($data['diff'])."\n";
    foreach (array_slice($data['diff'], 0, 200) as $item) echo '  '.$item."\n";
    foreach ($data['templates'] as $dir => $stat) {
        echo "\n".$dir.': shared '.$stat['shared'].', identical '.$stat['same'].', divergent '.count($stat['diff'])."\n";
        foreach ($stat['diff'] as $item) echo '  '.$item."\n";
    }
    exit(0);
}

$want = isset($args['theme']) && is_string($args['theme']) ? [$args['theme']] : array_keys($cont['themes']);
$only = isset($args['count']) || isset($args['bare']) || isset($args['dup']) || isset($args['names']) || isset($args['ramp']) || isset($args['dist']);
$fail = 0;
$store = ['generated' => gmdate('Y-m-d\TH:i:s\Z'), 'themes' => []];
$base = getBaselineData();

foreach ($want as $name) {
    $data = getThemeAudit($name);
    $store['themes'][$name] = $data['totals'];

    if (isset($args['dist'])) {
        $prop = is_string($args['dist']) ? $args['dist'] : 'padding';
        echo "\n[".$name.'] '.$prop.' distribution:'."\n";
        foreach (getDistList($data['model'], $prop) as $val => $hits) echo '  '.str_pad($val, 14).$hits."\n";
        continue;
    }
    if (isset($args['ramp'])) {
        echo "\n[".$name."] colour ramp, saturation first and hue second:\n";
        foreach (getColorRamp($data['model']) as $fam => $list) {
            echo '  '.$fam.' ('.count($list).")\n";
            foreach ($list as $item) {
                echo '    '.str_pad($item['step'], 4).str_pad($item['val'], 26).'L '.str_pad((string)$item['lum'], 6);
                echo 'S '.str_pad((string)$item['sat'], 6).$item['name'].'  ['.$item['role'].']'."\n";
            }
        }
        continue;
    }
    if (isset($args['count'])) {
        echo "\n[".$name.'] untokenised visual decisions: '.$data['totals']['count'];
        echo ' (half tokenised '.$data['count']['half'].', fully tokenised '.$data['count']['tokenised'].")\n";
        echo "  by property:\n";
        foreach ($data['count']['byprop'] as $prop => $hits) echo '    '.str_pad($prop, 26).$hits."\n";
        echo "  by file:\n";
        foreach ($data['count']['byfile'] as $path => $hits) echo '    '.str_pad($path, 52).$hits."\n";
        continue;
    }
    if (isset($args['bare'])) {
        echo "\n[".$name.'] bare numbers: '.$data['totals']['bare']."\n";
        foreach ($data['bare']['byprop'] as $prop => $hits) {
            $vals = [];
            foreach ($data['bare']['sites'] as $site) if ($site['prop'] === $prop) $vals[$site['raw']] = true;
            echo '  '.str_pad($prop, 14).'sites '.str_pad((string)$hits, 6).'values '.count($vals).'   '.implode(' ', array_slice(array_keys($vals), 0, 24))."\n";
        }
        continue;
    }
    if (isset($args['dup'])) {
        echo "\n[".$name.'] identical rule bodies: '.count($data['dup']['groups']).' groups, '.$data['dup']['blocks'];
        echo ' redundant blocks, '.$data['dup']['inmedia'].' of them inside @media'."\n";
        foreach ($data['dup']['groups'] as $list) {
            echo '  { '.substr($list[0]['body'], 0, 90).' }'."\n";
            foreach ($list as $item) echo '      '.$item['sel'].'   '.$item['file'].':'.$item['line'].($item['ctx'] === '' ? '' : '   '.$item['ctx'])."\n";
        }
        continue;
    }
    if (isset($args['names'])) {
        echo "\n[".$name.'] grammar violations: '.$data['totals']['names']."\n";
        foreach ($data['names'] as $item) echo '  '.str_pad($item['name'], 40).$item['why']."\n";
        continue;
    }

    echo "\n=== ".$name." ===\n";
    if (!$data['model']['marker']) echo '  MARKER MISSING: base.css has no '.$cont['marker']." and its API block has no end\n";
    foreach ($data['totals'] as $key => $val) {
        $was = $base['themes'][$name][$key] ?? null;
        $held = in_array($key, $cont['ratchet'], true);
        $note = $was === null ? '' : ($val > $was ? ($held ? '   GREW from '.$was : '   up from '.$was.', not ratcheted') : ($val < $was ? '   down from '.$was : ''));
        if ($was !== null && $val > $was && $held) $fail = 1;
        echo '  '.str_pad($key, 12).str_pad((string)$val, 8).$note."\n";
    }
    $clash = array_map(fn($v) => $v['name'].' is declared twice in the API block, '.$v['was'].' then '.$v['now'].' at line '.$v['line'], $data['model']['clash']);
    setSection('one name declared twice, where the last one silently wins:', $clash);
    setSection('dead tokens:', $data['use']['dead']);
    setSection('alias chains:', $data['use']['alias']);
    $unsat = array_map(fn($v) => $v['name'].' is a '.$v['kind'].', read by '.$v['prop'].' at '.$v['file'].':'.$v['line'], $data['use']['unsat']);
    setSection('tokens that cannot satisfy their property:', $unsat);
    $unmet = array_map(fn($v, $k) => $k.' is read '.count($v).' time'.(count($v) === 1 ? '' : 's').' in '.implode(', ', array_unique(array_map('basename', $v))).' and declared nowhere', $data['unmet'], array_keys($data['unmet']));
    setSection('names read but declared nowhere, where the browser drops the declaration without a word:', $unmet);
    setSection('classes never referenced:', $data['classes']['unused']);
    setSection('classes assembled from a prefix, to be looked at by hand before removal:', $data['classes']['composed']);
    setSection('contrast below AA:', array_map(fn($v) => $v['sel'].' ['.$v['mode'].'] '.$v['fg'].' on '.$v['bg'].' = '.$v['ratio'].':1, needs '.$v['want'], $data['contrast']));
    if (isset($args['strict']) && !isset($base['themes'][$name])) {
        foreach ($data['totals'] as $key => $val) if (in_array($key, ['count', 'bare', 'dead', 'alias', 'unsat', 'unmet', 'contrast'], true) && $val > 0) $fail = 1;
    }
}

if (!$only && count($want) === count($cont['themes'])) {
    $kinds = checkNameKinds();
    $sum = 0;
    foreach (checkPhpMarkup() as $hits) $sum += $hits['class'] + $hits['style'] + $hits['tag'];
    $store['global'] = ['kinds' => count($kinds), 'markup' => $sum];
    $said = array_map(fn($v) => $v['name'].': '.implode(', ', array_map(fn($k, $t) => $t.' is a '.$k, $v['kinds'], array_keys($v['kinds']))), $kinds);
    setSection('one name, two kinds across themes:', $said);
    echo "\nglobal\n";
    foreach ($store['global'] as $key => $val) {
        $was = $base['global'][$key] ?? null;
        if ($was !== null && $val > $was) $fail = 1;
        echo '  '.str_pad($key, 12).str_pad((string)$val, 8).($was === null ? '' : ($val > $was ? '   GREW from '.$was : ($val < $was ? '   down from '.$was : '')))."\n";
    }
}

if (isset($args['store'])) {
    if ($only || count($want) !== count($cont['themes'])) {
        fwrite(STDERR, "A baseline is stored from a full run only: drop --theme and any report flag.\n");
        exit(2);
    }
    exit(setBaselineData($store));
}
exit($fail);
