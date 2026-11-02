<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The audit of unused code: the plain functions of core/*.php, the language constants and the local variables nobody reads
# Run by hand, never by PHPUnit or the commit hook; every list is a signal of a token scan, and a name goes only after a search of the whole repository
# A name reached only through a string built at run time, a template key or another repository reads as unused here
# Usage: php tools/unused.php [--only=func|const|var]; the exit code is always 0, since no gate rests on these counts
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__.'/../tests/Support/tree_walk.php';

define('ROOT_DIR', str_replace('\\', '/', dirname(__DIR__)));

# Every file of one extension in the repository whose path holds none of the folders named, keyed by its path from the root
function getRepoFiles(string $ext, array $skip): array {
    $out = [];
    foreach (getTreeFiles(ROOT_DIR) as $file) {
        if (!$file->isFile() || $file->getExtension() !== $ext) continue;
        $rel = substr(str_replace('\\', '/', $file->getPathname()), strlen(ROOT_DIR) + 1);
        if (array_intersect($skip, explode('/', $rel))) continue;
        $out[$rel] = $file->getPathname();
    }
    return $out;
}

# The nearest token before ($dir -1) or after ($dir 1) the one at $at that is no whitespace or comment, null past an end
function getNextToken(array $toks, int $at, int $dir): array|string|null {
    $cnt = count($toks);
    for ($i = $at + $dir; $i >= 0 && $i < $cnt; $i += $dir) {
        if (is_array($toks[$i]) && in_array($toks[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
        return $toks[$i];
    }
    return null;
}

# The value of a PHP string literal as the source spells it; a token that is no plain literal comes back unchanged
function getPlainText(string $raw): string {
    $mark = $raw[0] ?? '';
    if (strlen($raw) < 2 || ($mark !== "'" && $mark !== '"') || substr($raw, -1) !== $mark) return $raw;
    $body = substr($raw, 1, -1);
    return ($mark === "'") ? str_replace(["\\'", '\\\\'], ["'", '\\'], $body) : stripcslashes($body);
}

# The name of the function whose argument list holds the token at $at, or null when that list belongs to no plain call
function getCallName(array $toks, int $at): ?string {
    $depth = 0;
    for ($i = $at - 1; $i >= 0; $i--) {
        if (is_array($toks[$i]) || ($toks[$i] !== '(' && $toks[$i] !== ')')) continue;
        if ($toks[$i] === ')') {
            $depth++;
        } elseif ($depth > 0) {
            $depth--;
        } else {
            $prev = getNextToken($toks, $i, -1);
            return (is_array($prev) && $prev[0] === T_STRING) ? $prev[1] : null;
        }
    }
    return null;
}

# The variables a function receives up to its body: under args the parameters, under uses the names a closure captures with use()
function getSignVars(array $toks, int $at): array {
    $out = ['args' => [], 'uses' => []];
    $part = 'args';
    $cnt = count($toks);
    for ($i = $at + 1; $i < $cnt && $toks[$i] !== '{' && $toks[$i] !== ';'; $i++) {
        if (is_array($toks[$i]) && $toks[$i][0] === T_USE) $part = 'uses';
        if (is_array($toks[$i]) && $toks[$i][0] === T_VARIABLE) $out[$part][$toks[$i][1]] = true;
    }
    return $out;
}

# The depth a brace token moves to; the opening brace of {$var} in a string is a token of its own whose closing brace is a plain one
function getBraceDepth(array|string $tok, int $depth): int {
    if ($tok === '{' || (is_array($tok) && in_array($tok[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) return $depth + 1;
    return ($tok === '}') ? max(0, $depth - 1) : $depth;
}

# The plain functions of core/*.php, methods left out, each with where it is defined and its calls: direct ones and names handed to a callback consumer
function getFuncUsage(): array {
    $defs = [];
    foreach (glob(ROOT_DIR.'/core/*.php') ?: [] as $path) {
        $toks = token_get_all((string)file_get_contents($path));
        $cnt = count($toks);
        $depth = 0;
        $scope = [];
        $pend = false;
        for ($i = 0; $i < $cnt; $i++) {
            $tok = $toks[$i];
            $was = $depth;
            $depth = getBraceDepth($tok, $depth);
            if ($depth > $was && $pend) {
                $scope[] = $depth;
                $pend = false;
            }
            if ($depth < $was && $scope && end($scope) === $was) array_pop($scope);
            if (!is_array($tok)) continue;
            if (in_array($tok[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) $pend = true;
            if ($tok[0] !== T_FUNCTION || $scope) continue;
            $name = getNextToken($toks, $i, 1);
            if (!is_array($name) || $name[0] !== T_STRING || str_starts_with($name[1], '__')) continue;
            $defs[$name[1]] = ['uses' => 0, 'at' => 'core/'.basename($path).':'.$name[2]];
        }
    }
    $hooks = array_flip(['preg_replace_callback', 'preg_replace_callback_array', 'call_user_func', 'call_user_func_array', 'array_map', 'array_filter',
        'array_reduce', 'array_walk', 'array_walk_recursive', 'usort', 'uasort', 'uksort', 'register_shutdown_function', 'set_error_handler',
        'set_exception_handler', 'spl_autoload_register']);
    foreach (getRepoFiles('php', ['vendor', 'tests', '.git', '.reports']) as $path) {
        $toks = token_get_all((string)file_get_contents($path));
        foreach ($toks as $i => $tok) {
            if (!is_array($tok)) continue;
            if ($tok[0] === T_STRING && isset($defs[$tok[1]]) && getNextToken($toks, $i, 1) === '(') {
                $prev = getNextToken($toks, $i, -1);
                if (!is_array($prev) || !in_array($prev[0], [T_FUNCTION, T_NEW, T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) $defs[$tok[1]]['uses']++;
            } elseif ($tok[0] === T_CONSTANT_ENCAPSED_STRING && isset($defs[$name = getPlainText($tok[1])]) && isset($hooks[getCallName($toks, $i) ?? ''])) {
                $defs[$name]['uses']++;
            }
        }
    }
    return $defs;
}

# The language constants with their uses, counting a string of config/, a {{ _NAME }} of a template and a name of a Node profile in modules/*/profiles/*.json
function getConstUsage(): array {
    $defs = [];
    $files = getRepoFiles('php', ['vendor', 'tests', '.git', '.reports']);
    foreach ($files as $rel => $path) {
        if (!preg_match('#^(lang|admin/lang|modules/[^/]+/lang|modules/[^/]+/admin/lang)/[^/]+\.php$#', $rel)) continue;
        $toks = token_get_all((string)file_get_contents($path));
        foreach ($toks as $i => $tok) {
            if (!is_array($tok) || $tok[0] !== T_STRING || strcasecmp($tok[1], 'define') !== 0 || getNextToken($toks, $i, 1) !== '(') continue;
            $arg = getNextToken($toks, array_search('(', array_slice($toks, $i, null, true), true), 1);
            if (!is_array($arg) || $arg[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
            $name = getPlainText($arg[1]);
            if (preg_match('/^_[A-Z0-9_]+$/', $name) && !isset($defs[$name])) $defs[$name] = ['uses' => 0, 'at' => $rel.':'.$arg[2]];
        }
    }
    foreach ($files as $rel => $path) {
        $isconf = str_starts_with($rel, 'config/');
        foreach (token_get_all((string)file_get_contents($path)) as $tok) {
            if (!is_array($tok)) continue;
            if ($tok[0] === T_STRING && isset($defs[$tok[1]])) $defs[$tok[1]]['uses']++;
            if ($isconf && $tok[0] === T_CONSTANT_ENCAPSED_STRING && isset($defs[$name = getPlainText($tok[1])])) $defs[$name]['uses']++;
        }
    }
    $texts = [];
    foreach (getRepoFiles('html', ['vendor', 'tests', '.git', '.reports']) as $rel => $path) {
        if (str_starts_with($rel, 'public/templates/')) $texts[] = [$path, '/\{\{\{?\s*(_[A-Z0-9_]+)\s*\}\}\}?/'];
    }
    foreach (getRepoFiles('json', []) as $rel => $path) if (preg_match('#^modules/[^/]+/profiles/[^/]+\.json$#', $rel)) $texts[] = [$path, '/"(_[A-Z][A-Z0-9_]*)"/'];
    foreach ($texts as [$path, $rule]) {
        preg_match_all($rule, (string)file_get_contents($path), $hit);
        foreach ($hit[1] as $name) if (isset($defs[$name])) $defs[$name]['uses']++;
    }
    return $defs;
}

# Local variables a function or closure body writes with = and never reads; a closure that captures a name with use() counts as a read of the outer function
function getDeadVars(): array {
    $skip = array_flip(['$this', '$_GET', '$_POST', '$_REQUEST', '$_SERVER', '$_COOKIE', '$_SESSION', '$_FILES', '$_ENV', '$GLOBALS', '$argv', '$argc']);
    $out = [];
    foreach (getRepoFiles('php', ['vendor', 'tests', '.git', '.reports', 'plugins']) as $rel => $path) {
        $toks = token_get_all((string)file_get_contents($path));
        $depth = 0;
        $stack = [];
        foreach ($toks as $i => $tok) {
            $depth = getBraceDepth($tok, $depth);
            $top = array_key_last($stack);
            if ($top !== null && !$stack[$top]['open']) {
                if ($depth >= $stack[$top]['depth']) $stack[$top]['open'] = true;
                if ($tok === ';' && !$stack[$top]['open']) array_pop($stack);
            }
            while ($stack && end($stack)['open'] && $depth < end($stack)['depth']) {
                $done = array_pop($stack);
                foreach ($done['vars'] as $name => $seen) if ($seen['set'] && !$seen['get']) $out[$name.' in '.$done['func'].'()'] = ['uses' => 0, 'at' => $rel.':'.$seen['line']];
            }
            if (!is_array($tok)) continue;
            if ($tok[0] === T_FUNCTION) {
                $next = getNextToken($toks, $i, 1);
                $func = is_array($next) && $next[0] === T_STRING ? $next[1] : 'closure@'.$tok[2];
                $sign = getSignVars($toks, $i);
                $stack[] = ['func' => $func, 'depth' => $depth + 1, 'open' => false, 'args' => $sign['args'], 'sign' => $sign['args'] + $sign['uses'], 'vars' => []];
                continue;
            }
            if ($tok[0] !== T_VARIABLE || isset($skip[$tok[1]])) continue;
            $prev = getNextToken($toks, $i, -1);
            if (is_array($prev) && $prev[0] === T_DOUBLE_COLON) continue;
            $top = array_key_last($stack);
            if ($top !== null && !$stack[$top]['open'] && isset($stack[$top]['args'][$tok[1]])) continue;
            for ($k = count($stack) - 1; $k >= 0 && $stack[$k]['depth'] > $depth; $k--);
            if ($k < 0 || isset($stack[$k]['sign'][$tok[1]])) continue;
            $seen = $stack[$k]['vars'][$tok[1]] ?? ['set' => 0, 'get' => 0, 'line' => $tok[2]];
            $seen[(getNextToken($toks, $i, 1) === '=') ? 'set' : 'get']++;
            $stack[$k]['vars'][$tok[1]] = $seen;
        }
    }
    ksort($out, SORT_STRING);
    return $out;
}

# Print one section: the names nobody uses, each with where it is defined
function setSection(string $title, array $defs): void {
    ksort($defs, SORT_STRING);
    $none = array_filter($defs, static fn(array $v): bool => $v['uses'] === 0);
    echo "\n".$title.': '.count($none).' unused'.((count($none) < count($defs)) ? ' of '.count($defs) : '')."\n";
    foreach ($none as $name => $info) echo '  '.str_pad($name, 40).' '.$info['at']."\n";
}

$only = '';
foreach (array_slice($argv, 1) as $arg) if (str_starts_with($arg, '--only=')) $only = substr($arg, 7);
if (!in_array($only, ['', 'func', 'const', 'var'], true)) {
    fwrite(STDERR, "Usage: php tools/unused.php [--only=func|const|var]\n");
    exit(2);
}
if ($only === '' || $only === 'func') setSection('plain functions of core/*.php', getFuncUsage());
if ($only === '' || $only === 'const') setSection('language constants', getConstUsage());
if ($only === '' || $only === 'var') setSection('local variables written and never read, a heuristic', getDeadVars());
exit(0);
