<?php
/**
 * Informational usage audit for language constants.
 * Scans only language sources:
 * - lang/*.php
 * - admin/lang/*.php
 * - modules/<module>/lang/*.php
 * - modules/<module>/admin/lang/*.php
 */

use PHPUnit\Framework\TestCase;

final class LanguageConstantsUsageTest extends TestCase
{
    private static string $basePath;
    private static array $stats = [];

    public static function setUpBeforeClass(): void
    {
        self::$basePath = dirname(__DIR__);
        self::$stats = self::collectStats();
    }

    public function testLanguageConstantsScanFoundDefinitions(): void
    {
        $this->assertGreaterThan(
            0,
            self::$stats['total_constants'],
            'Не найдены языковые константы в lang sources'
        );
    }

    public function testLanguageConstantsUsageSummary(): void
    {
        $msg = sprintf(
            "Language constants usage audit\nTotal: %d\nUnused: %d\nLow(1-2): %d\nTop unused: %s\nTop low-used: %s\n",
            self::$stats['total_constants'],
            self::$stats['unused_count'],
            self::$stats['low_count'],
            implode(', ', self::$stats['top_unused']),
            implode(', ', self::$stats['top_low'])
        );

        fwrite(STDERR, $msg);

        // Informational only: does not fail CI by design.
        $this->assertTrue(true, $msg);
    }

    private static function collectStats(): array
    {
        $defs = []; // CONST => [file:line, ...]
        $phpFiles = [];
        $tplFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::$basePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $skipDirs = ['vendor', 'tests', '.git', '.reports'];

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if ($file->getExtension() === 'html') {
                $path = $file->getPathname();
                $rel = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $path);
                $parts = preg_split('#[/\\\\]+#', $rel);
                if (array_intersect($skipDirs, $parts)) {
                    continue;
                }
                if (str_starts_with(str_replace('\\', '/', $rel), 'templates/')) {
                    $tplFiles[] = $path;
                }
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $rel = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $path);
            $parts = preg_split('#[/\\\\]+#', $rel);
            if (array_intersect($skipDirs, $parts)) {
                continue;
            }

            $phpFiles[] = $path;

            if (!self::isLanguageSource($rel)) {
                continue;
            }

            $tokens = token_get_all(file_get_contents($path));
            $count = count($tokens);

            for ($i = 0; $i < $count; $i++) {
                $tok = $tokens[$i];
                if (!(is_array($tok) && $tok[0] === T_STRING && strcasecmp($tok[1], 'define') === 0)) {
                    continue;
                }

                $j = $i + 1;
                while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $j++;
                }
                if ($j >= $count || $tokens[$j] !== '(') {
                    continue;
                }

                $j++;
                while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $j++;
                }
                if ($j >= $count || !is_array($tokens[$j]) || $tokens[$j][0] !== T_CONSTANT_ENCAPSED_STRING) {
                    continue;
                }

                $name = self::unquotePhpString($tokens[$j][1]);
                if (!preg_match('/^_[A-Z0-9_]+$/', $name)) {
                    continue;
                }

                $defs[$name][] = str_replace('\\', '/', $rel).':'.$tokens[$j][2];
            }
        }

        ksort($defs, SORT_STRING);
        $use = array_fill_keys(array_keys($defs), 0);

        $configDir = self::$basePath.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR;
        foreach ($phpFiles as $path) {
            $isConfig = str_starts_with($path, $configDir);
            $tokens = token_get_all(file_get_contents($path));
            foreach ($tokens as $tok) {
                if (!is_array($tok)) continue;
                if ($tok[0] === T_STRING && isset($use[$tok[1]])) {
                    $use[$tok[1]]++;
                } elseif ($isConfig && $tok[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $val = self::unquotePhpString($tok[1]);
                    if (isset($use[$val])) {
                        $use[$val]++;
                    }
                }
            }
        }

        foreach ($tplFiles as $path) {
            $text = file_get_contents($path);
            if ($text === false) {
                continue;
            }

            if (preg_match_all('/\{\{\{?\s*(_[A-Z0-9_]+)\s*\}\}\}?/', $text, $all) < 1) {
                continue;
            }

            foreach ($all[1] as $name) {
                if (isset($use[$name])) {
                    $use[$name]++;
                }
            }
        }

        $rows = [];
        foreach ($defs as $name => $locs) {
            $total = $use[$name] ?? 0;
            // T_STRING scan counts constant usages, but not define('_CONST', ...) declarations.
            $usage = $total;

            $rows[] = [
                'name' => $name,
                'usage_count' => $usage,
            ];
        }

        usort($rows, static fn(array $a, array $b): int => ($a['usage_count'] <=> $b['usage_count']) ?: strcmp($a['name'], $b['name']));

        $unused = array_values(array_filter($rows, static fn(array $r): bool => $r['usage_count'] === 0));
        $low = array_values(array_filter($rows, static fn(array $r): bool => $r['usage_count'] >= 1 && $r['usage_count'] <= 2));

        return [
            'total_constants' => count($rows),
            'unused_count' => count($unused),
            'low_count' => count($low),
            'top_unused' => array_map(static fn(array $r): string => $r['name'], array_slice($unused, 0, 20)),
            'top_low' => array_map(static fn(array $r): string => $r['name'], array_slice($low, 0, 20)),
        ];
    }

    private static function isLanguageSource(string $rel): bool
    {
        $rel = str_replace('\\', '/', $rel);
        return (bool) preg_match(
            '#^(lang/[^/]+\.php|admin/lang/[^/]+\.php|modules/[^/]+/lang/[^/]+\.php|modules/[^/]+/admin/lang/[^/]+\.php)$#',
            $rel
        );
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
}
