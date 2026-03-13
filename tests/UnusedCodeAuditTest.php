<?php

use PHPUnit\Framework\TestCase;

final class UnusedCodeAuditTest extends TestCase
{
    private static string $base_path;
    private static array $stats = [];

    public static function setUpBeforeClass(): void
    {
        self::$base_path = dirname(__DIR__);
        self::$stats = self::collectStats();
    }

    public function testUnusedFunctionsSummary(): void
    {
        $msg = sprintf(
            "Unused functions audit (core/*.php)\nTotal: %d\nUnused: %d\nLow(1-2): %d\nTop unused: %s\nTop low-used: %s\n",
            self::$stats['functions']['total'],
            self::$stats['functions']['unused_count'],
            self::$stats['functions']['low_count'],
            implode(', ', self::$stats['functions']['top_unused']),
            implode(', ', self::$stats['functions']['top_low'])
        );

        fwrite(STDERR, $msg);

        // Informational only.
        $this->assertTrue(true, $msg);
    }

    public function testUnusedLocalVariablesSummary(): void
    {
        $msg = sprintf(
            "Unused local variables audit (heuristic)\nFiles scanned: %d\nCandidates: %d\nTop candidates: %s\n",
            self::$stats['variables']['files_scanned'],
            self::$stats['variables']['unused_candidates'],
            implode(', ', self::$stats['variables']['top_candidates'])
        );

        fwrite(STDERR, $msg);

        // Informational only.
        $this->assertTrue(true, $msg);
    }

    private static function collectStats(): array
    {
        return [
            'functions' => self::collectFunctionUsageStats(),
            'variables' => self::collectUnusedVariableCandidates(),
        ];
    }

    private static function collectFunctionUsageStats(): array
    {
        $core_dir = self::$base_path.DIRECTORY_SEPARATOR.'core';
        $defs = []; // name => list of file:line
        $core_names = [];

        foreach (glob($core_dir.DIRECTORY_SEPARATOR.'*.php') as $file) {
            $tokens = token_get_all((string) file_get_contents($file));
            $n = count($tokens);
            $brace_depth = 0;
            $class_depths = [];
            $pending_class_open = false;
            for ($i = 0; $i < $n; $i++) {
                $t = $tokens[$i];

                if (is_string($t)) {
                    if ($t === '{') {
                        $brace_depth++;
                        if ($pending_class_open) {
                            $class_depths[] = $brace_depth;
                            $pending_class_open = false;
                        }
                    } elseif ($t === '}') {
                        if (!empty($class_depths) && end($class_depths) === $brace_depth) {
                            array_pop($class_depths);
                        }
                        $brace_depth = max(0, $brace_depth - 1);
                    }
                    continue;
                }

                // Track class-like scopes so methods are excluded from this audit.
                if (in_array($t[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
                    $pending_class_open = true;
                    continue;
                }

                if (!(is_array($t) && $t[0] === T_FUNCTION)) {
                    continue;
                }

                $prev_decl = self::prevSignificantToken($tokens, $i);
                if (is_array($prev_decl) && in_array($prev_decl[0], [T_PRIVATE, T_PROTECTED, T_PUBLIC, T_STATIC, T_FINAL, T_ABSTRACT], true)) {
                    continue; // class/trait/interface method
                }

                if (!empty($class_depths)) {
                    continue; // method inside class/anonymous class
                }

                for ($j = $i + 1; $j < $n; $j++) {
                    $x = $tokens[$j];
                    if (is_array($x) && in_array($x[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG], true)) {
                        continue;
                    }
                    if (is_array($x) && $x[0] === T_STRING) {
                        $name = $x[1];
                        if (str_starts_with($name, '__')) {
                            break; // skip magic methods from function-usage audit
                        }
                        $rel = str_replace(self::$base_path.DIRECTORY_SEPARATOR, '', $file);
                        $defs[$name][] = str_replace('\\', '/', $rel).':'.$x[2];
                        $core_names[$name] = true;
                    }
                    break;
                }
            }
        }

        $direct = array_fill_keys(array_keys($core_names), 0);
        $callback = array_fill_keys(array_keys($core_names), 0);
        $callbacks_consumers = array_flip([
            'preg_replace_callback',
            'preg_replace_callback_array',
            'call_user_func',
            'call_user_func_array',
            'array_map',
            'array_filter',
            'array_reduce',
            'array_walk',
            'array_walk_recursive',
            'usort',
            'uasort',
            'uksort',
            'register_shutdown_function',
            'set_error_handler',
            'set_exception_handler',
            'spl_autoload_register',
        ]);

        foreach (self::iterPhpFiles(['vendor', 'tests', '.git', '.reports']) as $path) {
            $tokens = token_get_all((string) file_get_contents($path));
            $n = count($tokens);
            for ($i = 0; $i < $n; $i++) {
                $t = $tokens[$i];

                if (is_array($t) && $t[0] === T_STRING && isset($core_names[$t[1]])) {
                    $next = self::nextSignificantToken($tokens, $i, 1);
                    if ($next !== '(') {
                        continue;
                    }

                    $prev = self::prevSignificantToken($tokens, $i);
                    if (is_array($prev) && in_array($prev[0], [T_FUNCTION, T_NEW, T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                        continue;
                    }

                    $direct[$t[1]]++;
                    continue;
                }

                if (is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $value = self::unquotePhpString($t[1]);
                    if (!isset($core_names[$value])) {
                        continue;
                    }

                    $call_name = self::enclosingCallName($tokens, $i);
                    if ($call_name !== null && isset($callbacks_consumers[$call_name])) {
                        $callback[$value]++;
                    }
                }
            }
        }

        $rows = [];
        foreach ($defs as $name => $locations) {
            // Direct scanner already excludes function declarations, so do not subtract def count here.
            $usage = ($direct[$name] ?? 0) + ($callback[$name] ?? 0);
            $rows[] = [
                'name' => $name,
                'usage' => $usage,
            ];
        }

        usort($rows, static fn(array $a, array $b): int => ($a['usage'] <=> $b['usage']) ?: strcmp($a['name'], $b['name']));
        $unused = array_values(array_filter($rows, static fn(array $r): bool => $r['usage'] === 0));
        $low = array_values(array_filter($rows, static fn(array $r): bool => $r['usage'] >= 1 && $r['usage'] <= 2));

        return [
            'total' => count($rows),
            'unused_count' => count($unused),
            'low_count' => count($low),
            'top_unused' => array_map(static fn(array $r): string => $r['name'], array_slice($unused, 0, 20)),
            'top_low' => array_map(static fn(array $r): string => $r['name'], array_slice($low, 0, 20)),
        ];
    }

    private static function collectUnusedVariableCandidates(): array
    {
        $ignored_vars = array_flip([
            '$this',
            '$_GET',
            '$_POST',
            '$_REQUEST',
            '$_SERVER',
            '$_COOKIE',
            '$_SESSION',
            '$_FILES',
            '$_ENV',
            '$GLOBALS',
            '$argv',
            '$argc',
        ]);
        $known_false_pos = array_flip([
            'core/system.php|addBackupDb|$backup_start',
            'core/system.php|addBackupDb|$bsize',
            'core/system.php|addBackupDb|$bonly_create',
            'core/system.php|addBackupDb|$last_charset',
            'core/system.php|addStash|$key',
        ]);

        $candidates = [];
        $files_scanned = 0;

        foreach (self::iterPhpFiles(['vendor', 'tests', '.git', '.reports', 'setup', 'plugins']) as $path) {
            $files_scanned++;
            $tokens = token_get_all((string) file_get_contents($path));
            $n = count($tokens);
            $in_function = false;
            $depth = 0;
            $func_depth = 0;
            $func_name = '';
            $next_func_name = false;
            $func_params = [];
            $vars = []; // $var => ['writes'=>int, 'reads'=>int, 'line'=>int]

            for ($i = 0; $i < $n; $i++) {
                $tok = $tokens[$i];

                if (is_string($tok)) {
                    if ($tok === '{') {
                        $depth++;
                    } elseif ($tok === '}') {
                        $depth--;
                        if ($in_function && $depth < $func_depth) {
                            foreach ($vars as $name => $st) {
                                if ($st['writes'] > 0 && $st['reads'] === 0) {
                                    $rel = str_replace(self::$base_path.DIRECTORY_SEPARATOR, '', $path);
                                    $rel_norm = str_replace('\\', '/', $rel);
                                    $fp_key = $rel_norm.'|'.$func_name.'|'.$name;
                                    if (isset($known_false_pos[$fp_key])) {
                                        continue;
                                    }
                                    $candidates[] = sprintf('%s:%d %s() %s', $rel_norm, $st['line'], $func_name, $name);
                                }
                            }
                            $vars = [];
                            $in_function = false;
                            $func_name = '';
                        }
                    }
                    continue;
                }

                [$id, $text, $line] = $tok;
                if ($id === T_FUNCTION) {
                    $func_params = self::extractFunctionParamVars($tokens, $i);
                    $next_sig = self::nextSignificantToken($tokens, $i, 1);
                    if ($next_sig === '(') {
                        $func_params += self::extractClosureUseVars($tokens, $i);
                        $in_function = true;
                        $func_depth = $depth + 1;
                        $func_name = 'closure@'.$line;
                        $vars = [];
                        $next_func_name = false;
                        continue;
                    }
                    $next_func_name = true;
                    continue;
                }

                if ($next_func_name && $id === T_STRING) {
                    $prev_sig = self::prevSignificantToken($tokens, $i);
                    if (!is_array($prev_sig) || $prev_sig[0] !== T_FUNCTION) {
                        continue;
                    }
                    $next_func_name = false;
                    $in_function = true;
                    $func_depth = $depth + 1;
                    $func_name = $text;
                    $vars = [];
                    continue;
                }

                if ($next_func_name && !in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $next_func_name = false;
                }

                if (!$in_function || $id !== T_VARIABLE) {
                    continue;
                }

                // Ignore variables in function signature (parameters/defaults)
                // and analyse only function body scope.
                if ($depth < $func_depth) {
                    continue;
                }

                if (isset($ignored_vars[$text])) {
                    continue;
                }
                if (isset($func_params[$text])) {
                    continue;
                }

                $next = self::nextSignificantToken($tokens, $i, 1);
                $prev = self::prevSignificantToken($tokens, $i);
                $is_write = in_array($next, ['=', '+=', '-=', '*=', '/=', '.=', '%=', '&=', '|=', '^=', '??='], true)
                    || $prev === T_AS;

                if (!isset($vars[$text])) {
                    $vars[$text] = ['writes' => 0, 'reads' => 0, 'line' => $line];
                }

                if ($is_write) {
                    $vars[$text]['writes']++;
                } else {
                    $vars[$text]['reads']++;
                }
            }
        }

        sort($candidates, SORT_STRING);

        return [
            'files_scanned' => $files_scanned,
            'unused_candidates' => count($candidates),
            'top_candidates' => array_slice($candidates, 0, 20),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function iterPhpFiles(array $excluded_top_dirs): array
    {
        $files = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::$base_path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($it as $f) {
            if (!$f->isFile() || $f->getExtension() !== 'php') {
                continue;
            }

            $path = $f->getPathname();
            $rel = str_replace(self::$base_path.DIRECTORY_SEPARATOR, '', $path);
            $normalized = str_replace('\\', '/', $rel);
            $parts = preg_split('#[/\\\\]+#', $rel);
            if (array_intersect($excluded_top_dirs, $parts)) {
                continue;
            }

            $files[] = $path;
        }

        return $files;
    }

    /**
     * @return array{0:int,1:string,2:int}|string|null
     */
    private static function prevSignificantToken(array $tokens, int $index)
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $t = $tokens[$i];
            if (is_array($t)) {
                if (in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                return $t;
            }

            return $t;
        }

        return null;
    }

    /**
     * @return array{0:int,1:string,2:int}|string|null
     */
    private static function nextSignificantToken(array $tokens, int $index, int $direction = 1)
    {
        $i = $index + $direction;
        $count = count($tokens);
        while ($i >= 0 && $i < $count) {
            $t = $tokens[$i];
            if (is_array($t)) {
                if (in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $i += $direction;
                    continue;
                }

                return $t;
            }

            return $t;
        }

        return null;
    }

    private static function unquotePhpString(string $raw): string
    {
        if (strlen($raw) < 2) {
            return $raw;
        }
        $q = $raw[0];
        if (($q !== "'" && $q !== '"') || substr($raw, -1) !== $q) {
            return $raw;
        }
        $body = substr($raw, 1, -1);
        if ($q === "'") {
            return str_replace(["\\'", '\\\\'], ["'", '\\'], $body);
        }
        return stripcslashes($body);
    }

    private static function enclosingCallName(array $tokens, int $index): ?string
    {
        $depth = 0;
        for ($i = $index - 1; $i >= 0; $i--) {
            $t = $tokens[$i];
            if (is_array($t)) {
                continue;
            }
            if ($t === ')') {
                $depth++;
                continue;
            }
            if ($t !== '(') {
                continue;
            }
            if ($depth > 0) {
                $depth--;
                continue;
            }

            $prev = self::prevSignificantToken($tokens, $i);
            if (is_array($prev) && $prev[0] === T_STRING) {
                return $prev[1];
            }
            return null;
        }
        return null;
    }

    /**
     * @return array<string, bool>
     */
    private static function extractFunctionParamVars(array $tokens, int $function_index): array
    {
        $params = [];
        $open_index = null;
        $n = count($tokens);

        for ($i = $function_index + 1; $i < $n; $i++) {
            $t = $tokens[$i];
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if ($t === '(') {
                $open_index = $i;
                break;
            }
            if ($t === '{' || $t === ';') {
                break;
            }
        }

        if ($open_index === null) {
            return $params;
        }

        $depth = 0;
        for ($i = $open_index; $i < $n; $i++) {
            $t = $tokens[$i];
            if ($t === '(') {
                $depth++;
                continue;
            }
            if ($t === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
                continue;
            }
            if ($depth > 0 && is_array($t) && $t[0] === T_VARIABLE) {
                $params[$t[1]] = true;
            }
        }

        return $params;
    }

    /**
     * @return array<string, bool>
     */
    private static function extractClosureUseVars(array $tokens, int $function_index): array
    {
        $captured = [];
        $n = count($tokens);
        $param_close = null;
        $depth = 0;

        for ($i = $function_index + 1; $i < $n; $i++) {
            $t = $tokens[$i];
            if ($t === '(') {
                $depth++;
                continue;
            }
            if ($t === ')') {
                $depth--;
                if ($depth === 0) {
                    $param_close = $i;
                    break;
                }
                continue;
            }
        }

        if ($param_close === null) {
            return $captured;
        }

        $use_pos = null;
        for ($i = $param_close + 1; $i < $n; $i++) {
            $t = $tokens[$i];
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if (is_array($t) && $t[0] === T_USE) {
                $use_pos = $i;
            }
            break;
        }

        if ($use_pos === null) {
            return $captured;
        }

        $open = null;
        for ($i = $use_pos + 1; $i < $n; $i++) {
            $t = $tokens[$i];
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if ($t === '(') {
                $open = $i;
            }
            break;
        }

        if ($open === null) {
            return $captured;
        }

        $depth = 0;
        for ($i = $open; $i < $n; $i++) {
            $t = $tokens[$i];
            if ($t === '(') {
                $depth++;
                continue;
            }
            if ($t === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
                continue;
            }
            if ($depth > 0 && is_array($t) && $t[0] === T_VARIABLE) {
                $captured[$t[1]] = true;
            }
        }

        return $captured;
    }
}
