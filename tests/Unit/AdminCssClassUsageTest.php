<?php
/**
 * Informational usage audit for admin CSS classes.
 * Scans only admin CSS sources:
 * - templates/admin/assets/css/*.css
 * and only these admin templates:
 * - templates/admin/fragments/*.html
 * - templates/admin/layouts/*.html
 * - templates/admin/pages/*.html
 * - templates/admin/partials/*.html
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminCssClassUsageTest extends TestCase
{
    private static string $basePath;
    private static array $stats = [];

    public static function setUpBeforeClass(): void
    {
        self::$basePath = BASE_DIR;
        self::$stats = self::collectStats();
    }

    public function testAdminCssClassScanFoundDefinitions(): void
    {
        $this->assertGreaterThan(
            0,
            self::$stats['total_classes'],
            'Не найдены CSS-классы в templates/admin/assets/css/*.css'
        );
    }

    public function testAdminCssClassUsageSummary(): void
    {
        $msg = sprintf(
            "Admin CSS class usage audit\nCSS classes: %d\nHTML classes: %d\nUnused: %d\nTop unused: %s\n",
            self::$stats['total_classes'],
            self::$stats['used_classes'],
            self::$stats['unused_count'],
            implode(', ', self::$stats['top_unused'])
        );

        fwrite(STDERR, $msg);

        // Informational only: does not fail CI by design.
        $this->assertTrue(true, $msg);
    }

    private static function collectStats(): array
    {
        $cssClasses = self::collectCssClassDefinitions();
        $htmlUsage = self::collectHtmlClassUsages();
        $htmlClasses = $htmlUsage['exact'];
        $htmlPrefixes = $htmlUsage['prefixes'];

        $unused = array_values(array_filter(
            array_keys($cssClasses),
            static fn(string $class): bool => !isset($htmlClasses[$class]) && !self::matchesAnyPrefix($class, $htmlPrefixes)
        ));
        sort($unused, SORT_STRING);

        $used = 0;
        foreach (array_keys($cssClasses) as $class) {
            if (isset($htmlClasses[$class]) || self::matchesAnyPrefix($class, $htmlPrefixes)) {
                $used++;
            }
        }

        return [
            'total_classes' => count($cssClasses),
            'used_classes' => $used,
            'unused_count' => count($unused),
            'top_unused' => array_slice($unused, 0, 40),
        ];
    }

    /**
     * @return array<string, true>
     */
    private static function collectCssClassDefinitions(): array
    {
        $classes = [];

        foreach (glob(self::$basePath.'/templates/admin/assets/css/*.css') ?: [] as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $content = self::stripCssComments($content);
            if (preg_match_all('/(?<![\\w-])\\.([A-Za-z_][A-Za-z0-9_-]*)/', $content, $matches)) {
                foreach ($matches[1] as $class) {
                    if (!str_starts_with($class, 'sl-')) {
                        continue;
                    }
                    $classes[$class] = true;
                }
            }
        }

        ksort($classes, SORT_STRING);

        return $classes;
    }

    /**
     * @return array{exact: array<string, true>, prefixes: array<string, true>}
     */
    private static function collectHtmlClassUsages(): array
    {
        $classes = [];
        $prefixes = [];
        $roots = [
            self::$basePath.'/templates/admin/fragments',
            self::$basePath.'/templates/admin/layouts',
            self::$basePath.'/templates/admin/pages',
            self::$basePath.'/templates/admin/partials',
        ];

        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            foreach (glob($root.'/*.html') ?: [] as $file) {
                $content = file_get_contents($file);
                if ($content === false) {
                    continue;
                }

                $content = self::stripTemplateSyntax($content);
                if (!preg_match_all('/class\\s*=\\s*([\"\'])(.*?)\\1/is', $content, $matches)) {
                    continue;
                }

                foreach ($matches[2] as $attr) {
                    foreach (preg_split('/\\s+/', trim($attr)) ?: [] as $class) {
                        if ($class === '') {
                            continue;
                        }

                        if (!str_starts_with($class, 'sl-')) {
                            continue;
                        }

                        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_-]*[A-Za-z0-9_]$/', $class)) {
                            if (strlen($class) > 4 && preg_match('/^[A-Za-z_][A-Za-z0-9_-]*-$/', $class)) {
                                $prefixes[$class] = true;
                            }
                            continue;
                        }

                        $classes[$class] = true;
                    }
                }
            }
        }

        ksort($classes, SORT_STRING);
        ksort($prefixes, SORT_STRING);

        return [
            'exact' => $classes,
            'prefixes' => $prefixes,
        ];
    }

    private static function stripCssComments(string $content): string
    {
        return preg_replace('~/\\*.*?\\*/~s', '', $content) ?? $content;
    }

    private static function stripTemplateSyntax(string $content): string
    {
        $content = preg_replace('/\\{\\{\\{.*?\\}\\}\\}|\\{\\{.*?\\}\\}|\\{%.*?%\\}/s', ' ', $content);
        return $content ?? '';
    }

    /**
     * @param array<string, true> $prefixes
     */
    private static function matchesAnyPrefix(string $class, array $prefixes): bool
    {
        foreach (array_keys($prefixes) as $prefix) {
            if (str_starts_with($class, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
