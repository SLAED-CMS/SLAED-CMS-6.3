<?php
// Scan for language constants defined in language/ folders and report those unused outside excluded dirs.
// Excludes: setup/, plugins/, uploads/, config/

declare(strict_types=1);

$root = __DIR__;
$ds = DIRECTORY_SEPARATOR;

function starts_with(string $hay, string $needle): bool { return strncmp($hay, $needle, strlen($needle)) === 0; }
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) { return $needle === '' ? true : strpos($haystack, $needle) !== false; }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) { return strncmp($haystack, $needle, strlen($needle)) === 0; }
}

// Collect PHP files excluding certain top-level dirs
$skipTop = [
    'setup'.$ds,
    'plugins'.$ds,
    'uploads'.$ds,
    'config'.$ds,
];

$allPhp = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isDir()) continue;
    $path = $f->getPathname();
    if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') continue;
    $rel = str_replace($root.$ds, '', $path);
    $skip = false;
    foreach ($skipTop as $s) { if (starts_with($rel, $s)) { $skip = true; break; } }
    if ($skip) continue;
    $allPhp[] = $path;
}

// Gather language files from language/ locations
$langPhp = [];
$it2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it2 as $f) {
    if ($f->isDir()) continue;
    $path = $f->getPathname();
    if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') continue;
    $rel = str_replace($root.$ds, '', $path);
    $isLang = false;
    if (starts_with($rel, 'language'.$ds)) $isLang = true;
    if (starts_with($rel, 'admin'.$ds.'language'.$ds)) $isLang = true;
    if (starts_with($rel, 'modules'.$ds) && str_contains($rel, $ds.'language'.$ds)) $isLang = true;
    if ($isLang) $langPhp[] = $path;
}

// Extract constants like _CONSTANT from language files
$consts = [];
foreach ($langPhp as $p) {
    $c = @file_get_contents($p);
    if ($c === false) continue;
    if (preg_match_all('/define\s*\(\s*[\'\"](_[A-Z][A-Z0-9_]*)[\'\"]\s*,/u', $c, $m)) {
        foreach ($m[1] as $k) $consts[$k] = true;
    }
}

$unused = [];
$checked = array_keys($consts);
sort($checked);
foreach (array_keys($consts) as $k) {
    $used = false;
    $re = '/\b'.preg_quote($k,'/').'\b/u';
    foreach ($allPhp as $p) {
        $c = @file_get_contents($p);
        if ($c === false) continue;
        if (preg_match($re, $c)) { $used = true; break; }
    }
    if (!$used) $unused[] = $k;
}

sort($unused);
$outUnused = $root.$ds.'unused_language_constants.txt';
@file_put_contents($outUnused, implode(PHP_EOL, $unused));

$outChecked = $root.$ds.'checked_language_constants.txt';
@file_put_contents($outChecked, implode(PHP_EOL, $checked));

echo "Done. See unused_language_constants.txt (".count($unused).") and checked_language_constants.txt (".count($checked).")\n";

/* EOF */


