<?php
// Scan all config/*.php for configuration keys and report unused keys across the codebase (excluding config/, setup/, plugins/, uploads/, language/).

declare(strict_types=1);

$root = __DIR__;
$ds = DIRECTORY_SEPARATOR;

function starts_with(string $hay, string $needle): bool { return strncmp($hay, $needle, strlen($needle)) === 0; }
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) { return $needle === '' ? true : strpos($haystack, $needle) !== false; }
}

// 1) Collect config files
$configDir = $root.$ds.'config';
if (!is_dir($configDir)) { echo "config/ not found\n"; exit(1); }

$configFiles = [];
$it = new DirectoryIterator($configDir);
foreach ($it as $f) {
    if ($f->isDot() || !$f->isFile()) continue;
    if (pathinfo($f->getFilename(), PATHINFO_EXTENSION) !== 'php') continue;
    $configFiles[] = $f->getPathname();
}

// 2) Extract keys from arrays like $conf['key'], $confdb['key'], $confs['key'], module-specific arrays ($conf* ...)
$keys = [];
$arrays = [];
$keySource = [];
foreach ($configFiles as $p) {
    $code = @file_get_contents($p);
    if ($code === false) continue;
    // Detect array variables on left side
    if (preg_match_all('/\$(conf[a-z_]*)\s*\[\s*[\'\"]([^\'\"]+)[\'\"]\s*\]\s*=\s*/iu', $code, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $arr = $hit[1];
            $key = $hit[2];
            $arrays[$arr] = true;
            $keys[$arr.'['.$key.']'] = [$arr, $key];
            $keySource[$arr.'['.$key.']'] = str_replace($root.$ds, '', $p);
        }
    }
}

// 3) Collect non-config PHP files to search usages
$excludeTop = ['config'.$ds, 'setup'.$ds, 'plugins'.$ds, 'uploads'.$ds, 'language'.$ds, 'admin'.$ds.'language'.$ds];
$searchPhp = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    if ($f->isDir()) continue;
    $path = $f->getPathname();
    if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') continue;
    $rel = str_replace($root.$ds, '', $path);
    $skip = false;
    foreach ($excludeTop as $s) { if (starts_with($rel, $s)) { $skip = true; break; } }
    if ($skip) continue;
    $searchPhp[] = $path;
}

// 4) Count usages of each key like $conf['key'] across codebase
$unused = [];
$counts = [];
foreach ($keys as $flat => [$arr, $key]) {
    $re = '/\$'.preg_quote($arr,'/').'\s*\[\s*[\'\"]'.preg_quote($key,'/').'[\'\"]\s*\]/u';
    $count = 0;
    foreach ($searchPhp as $p) {
        $c = @file_get_contents($p);
        if ($c === false) continue;
        $count += preg_match_all($re, $c);
        if ($count > 0) break;
    }
    $counts[$flat] = $count;
    if ($count === 0) $unused[] = $flat;
}

sort($unused);
ksort($counts);

// Enrich unused with source file (conf-file - conf-variable)
$reportUnused = [];
foreach ($unused as $flat) {
    $src = $keySource[$flat] ?? '';
    if ($src === '') {
        // try to locate defining file heuristically
        [$arr,$key] = $keys[$flat] ?? [null,null];
        if ($arr !== null) {
            $needle = '$'.$arr.'['.var_export($key, true).']';
            foreach ($configFiles as $p) {
                $c = @file_get_contents($p);
                if ($c !== false && strpos($c, $needle) !== false) { $src = str_replace($root.$ds, '', $p); break; }
            }
        }
        if ($src === '') $src = 'config/*.php';
    }
    $reportUnused[] = $src.' - '.$flat;
}
file_put_contents('unused_config_keys.txt', implode(PHP_EOL, $reportUnused));
$rep = [];
foreach ($counts as $k => $v) $rep[] = $k.' = '.$v;
file_put_contents('checked_config_keys.txt', implode(PHP_EOL, $rep));

echo "Done. Keys: ".count($keys).", unused: ".count($unused)."\n";

