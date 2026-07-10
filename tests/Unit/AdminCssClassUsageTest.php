<?php
/**
 * Informational theme-local CSS usage audit
 *
 * CSS definitions are collected independently for every installed theme
 * Usage sources include theme HTML and hooks, PHP emitters, and shared JS state hooks
 * The unused report is conservative and must never be treated as deletion evidence
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminCssClassUsageTest extends TestCase
{
    private static string $base;
    private static array $stats = [];

    public static function setUpBeforeClass(): void
    {
        self::$base = BASE_DIR;
        foreach (self::getThemes() as $theme) self::$stats[$theme] = self::getStats($theme);
    }

    public function testThemeCssClassScansFoundDefinitions(): void
    {
        $this->assertNotEmpty(self::$stats, 'Не найдены темы с theme-local CSS');
        foreach (self::$stats as $theme => $stats) {
            $this->assertGreaterThan(0, $stats['total'], 'Не найдены CSS-классы темы '.$theme);
            $this->assertGreaterThan(0, $stats['emitters'], 'Не найдены источники CSS-классов темы '.$theme);
        }
    }

    public function testRuntimeEmitterClassesAreClassified(): void
    {
        $known = [
            'admin' => ['sl-chart-svg', 'sl-sort-asc', 'sl-sort-desc', 'sl-is-closed'],
            'lite' => ['sl-sort-asc', 'sl-sort-desc', 'sl-winter', 'sl-spring', 'sl-summer', 'sl-autumn'],
        ];
        foreach ($known as $theme => $classes) {
            if (!isset(self::$stats[$theme])) continue;
            foreach ($classes as $class) {
                $this->assertNotContains($class, self::$stats[$theme]['unused'], $theme.' runtime class was reported unused: '.$class);
            }
        }
    }

    public function testThemeCssClassUsageSummary(): void
    {
        foreach (self::$stats as $theme => $stats) {
            $msg = sprintf(
                "%s CSS class usage audit\nCSS classes: %d\nEmitter classes/prefixes: %d\nUsed: %d\nUnused: %d\nTop unused: %s\n",
                ucfirst($theme),
                $stats['total'],
                $stats['emitters'],
                $stats['used'],
                count($stats['unused']),
                implode(', ', array_slice($stats['unused'], 0, 40))
            );
            fwrite(STDERR, $msg);
        }
        $this->assertTrue(true);
    }

    private static function getThemes(): array
    {
        $themes = [];
        foreach (scandir(self::$base.'/templates') ?: [] as $theme) {
            $path = self::$base.'/templates/'.$theme.'/assets/css';
            if ($theme === '.' || $theme === '..' || !is_file($path.'/base.css') || !is_file($path.'/theme.css')) continue;
            $themes[] = $theme;
        }
        sort($themes);
        return $themes;
    }

    private static function getStats(string $theme): array
    {
        $css = self::getCssClasses($theme);
        $usage = self::getClassUsage($theme);
        $unused = [];
        $used = 0;
        foreach (array_keys($css) as $class) {
            if (isset($usage['exact'][$class]) || self::isPrefixMatch($class, $usage['prefix'])) {
                $used++;
            } else {
                $unused[] = $class;
            }
        }
        sort($unused);
        return [
            'total' => count($css),
            'emitters' => count($usage['exact']) + count($usage['prefix']),
            'used' => $used,
            'unused' => $unused,
        ];
    }

    private static function getCssClasses(string $theme): array
    {
        $classes = [];
        foreach (glob(self::$base.'/templates/'.$theme.'/assets/css/*.css') ?: [] as $file) {
            $text = file_get_contents($file);
            if ($text === false) continue;
            $text = preg_replace('~/\*.*?\*/~s', '', $text) ?? $text;
            if (!preg_match_all('/(?<![\w-])\.(sl-[A-Za-z0-9_-]+)/', $text, $match)) continue;
            foreach ($match[1] as $class) $classes[$class] = true;
        }
        ksort($classes);
        return $classes;
    }

    private static function getClassUsage(string $theme): array
    {
        $exact = [];
        $prefix = [];
        foreach (self::getEmitterFiles($theme) as $file) {
            $text = file_get_contents($file);
            if ($text === false) continue;
            if (pathinfo($file, PATHINFO_EXTENSION) === 'html') {
                if (!preg_match_all('/class\s*=\s*(["\'])(.*?)\1/is', $text, $attrs)) continue;
                $text = implode(' ', $attrs[2]);
                $text = preg_replace('/\{\{\{.*?\}\}\}|\{\{.*?\}\}|\{%.*?%\}/s', '', $text) ?? $text;
            }
            if (!preg_match_all('/(?<![A-Za-z0-9_-])(sl-[A-Za-z0-9_-]+)/', $text, $match)) continue;
            foreach ($match[1] as $class) {
                if (str_ends_with($class, '-')) {
                    $prefix[$class] = true;
                } else {
                    $exact[$class] = true;
                }
            }
        }
        ksort($exact);
        ksort($prefix);
        return ['exact' => $exact, 'prefix' => $prefix];
    }

    private static function getEmitterFiles(string $theme): array
    {
        $files = [];
        $roots = [self::$base.'/templates/'.$theme, self::$base.'/plugins/system'];
        if ($theme === 'admin') {
            $roots[] = self::$base.'/admin';
            $roots[] = self::$base.'/modules';
        } else {
            $roots[] = self::$base.'/blocks';
            $roots[] = self::$base.'/core';
            $roots[] = self::$base.'/modules';
        }
        foreach ($roots as $root) {
            if (!is_dir($root)) continue;
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iter as $file) {
                if (!in_array($file->getExtension(), ['html', 'php', 'js'], true)) continue;
                $path = str_replace('\\', '/', $file->getPathname());
                $base = rtrim(str_replace('\\', '/', self::$base), '/').'/';
                $rel = str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
                $mod = str_starts_with($rel, 'modules/');
                $admin = preg_match('#^modules/[^/]+/admin/#', $rel) === 1;
                if ($theme === 'admin' && $mod && !$admin) continue;
                if ($theme !== 'admin' && $admin) continue;
                $files[$path] = $file->getPathname();
            }
        }
        ksort($files);
        return array_values($files);
    }

    private static function isPrefixMatch(string $class, array $prefix): bool
    {
        foreach (array_keys($prefix) as $item) {
            if (str_starts_with($class, $item)) return true;
        }
        return false;
    }
}
