<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The lifecycle of a scratch theme, which is the goal of docs/THEME-ETALON-2026.md carried out in code: copy an etalon whole,
# repaint only the API block of its base.css, and remove the copy afterwards against a path this file built and never one it guessed
# It lives here rather than inside a test because two gates ask the same question of the same copy - the static half in
# ThemeCreationTest and the HTTP half the screenshot runner drives - and a lifecycle spelled twice drifts into two lifecycles
require_once dirname(__DIR__, 2).'/tools/ui-audit.php';

# Copy one etalon under a name nothing else uses and repaint its palette, which is the whole of "creating a theme"
function setScratchTheme(string $etalon): array {
    $root = dirname(__DIR__, 2);
    $from = $root.'/templates/'.$etalon;
    if (!is_dir($from)) throw new RuntimeException('No etalon at '.$from);
    $name = 'scratch-'.substr(sha1($etalon.getmypid().microtime(true)), 0, 8);
    $path = $root.'/templates/'.$name;
    if (is_dir($path)) deleteScratchTheme($path);
    setScratchTree($from, $path);
    setScratchPalette($path.'/assets/css/base.css');
    return ['name' => $name, 'path' => $path];
}

# Copy a directory tree whole, which is step one of creating a theme
function setScratchTree(string $from, string $to): void {
    mkdir($to, 0777, true);
    foreach (scandir($from) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        is_dir($from.'/'.$item) ? setScratchTree($from.'/'.$item, $to.'/'.$item) : copy($from.'/'.$item, $to.'/'.$item);
    }
}

# Remove a scratch theme, refusing any path that is not one of ours: the guard is the name this file writes, not the caller's word
# The guard is asked once, of the root, and the walk below it carries none: a check that has to pass for every nested directory
# refuses the first one and leaves the tree half removed, which is how a copy outlives the test that made it
function deleteScratchTheme(string $path): bool {
    $safe = str_replace('\\', '/', $path);
    if (!preg_match('#/templates/scratch-[0-9a-f]{8}$#', $safe) || !is_dir($path)) return false;
    deleteScratchTree($path);
    return !is_dir($path);
}

# Remove one directory tree whole, called only for a path deleteScratchTheme() has already vouched for
function deleteScratchTree(string $path): void {
    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        is_dir($path.'/'.$item) ? deleteScratchTree($path.'/'.$item) : unlink($path.'/'.$item);
    }
    rmdir($path);
}

# Repaint every colour of the API block by turning its hue half way round and then pulling it back to the relative luminance it had
# Hue alone does not hold a contrast ratio: the three channels carry different weight, so the same HSL lightness in orange is brighter
# than in blue and #111827 turned to #272011 gains half again as much luminance. Luminance is what a ratio is made of, so a repaint that
# holds it holds every pair the etalon measured, in both halves of light-dark() and without a browser to ask
function setScratchPalette(string $file): void {
    $text = str_replace("\r\n", "\n", (string)file_get_contents($file));
    $mark = strpos($text, getContract()['marker']);
    if ($mark === false) throw new RuntimeException('The etalon has no marker, so it has no API block to repaint');
    $head = (string)preg_replace_callback('/#([0-9a-fA-F]{6})\b/', static fn($m) => '#'.getScratchColor($m[1]), substr($text, 0, $mark));
    file_put_contents($file, $head.substr($text, $mark));
}

# One hex colour turned half way round the wheel, then searched back onto the relative luminance it started with
function getScratchColor(string $hex): string {
    $rgb = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    [$deg, $pct, $val] = getHslValues($rgb);
    if ($pct <= 0.0) return $hex;
    $want = getHexLuminance($hex);
    $hue = fmod($deg + 180.0, 360.0) / 360.0;
    $sat = $pct / 100.0;
    $low = 0.0;
    $high = 1.0;
    $out = getScratchHsl($hue, $sat, $val / 100.0);
    for ($i = 0; $i < 40; $i++) {
        $mid = ($low + $high) / 2;
        $out = getScratchHsl($hue, $sat, $mid);
        if (getHexLuminance($out) < $want) $low = $mid;
        else $high = $mid;
    }
    return $out;
}

# One HSL triple written back as a hex colour
function getScratchHsl(float $hue, float $sat, float $lum): string {
    $two = $lum < 0.5 ? $lum * (1.0 + $sat) : $lum + $sat - $lum * $sat;
    $one = 2.0 * $lum - $two;
    $part = static function (float $at) use ($one, $two): int {
        if ($at < 0.0) $at += 1.0;
        if ($at > 1.0) $at -= 1.0;
        if ($at < 1 / 6) return (int)round(255 * ($one + ($two - $one) * 6.0 * $at));
        if ($at < 1 / 2) return (int)round(255 * $two);
        if ($at < 2 / 3) return (int)round(255 * ($one + ($two - $one) * (2 / 3 - $at) * 6.0));
        return (int)round(255 * $one);
    };
    return sprintf('%02x%02x%02x', $part($hue + 1 / 3), $part($hue), $part($hue - 1 / 3));
}

# WCAG relative luminance, the quantity a contrast ratio is built from
function getHexLuminance(string $hex): float {
    $sum = 0.0;
    $part = [0.2126, 0.7152, 0.0722];
    foreach ([0, 2, 4] as $i => $at) {
        $one = hexdec(substr($hex, $at, 2)) / 255;
        $sum += $part[$i] * ($one <= 0.03928 ? $one / 12.92 : (($one + 0.055) / 1.055) ** 2.4);
    }
    return $sum;
}
