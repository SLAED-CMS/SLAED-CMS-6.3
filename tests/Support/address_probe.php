<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the label crawl of tools/label-audit.mjs: what a readable path of the stand names, read the way public/index.php reads it
# The job paths answers for every path the operation its second part names under its prefix, else the kind of the entity the registry holds for it
# It reads the site database and writes nothing
$probework = (string)($argv[2] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';

# The operation or the registry kind of every path, keyed by the path without its leading slash; a path that is neither answers two empty strings
function getProbePaths(array $list): array {
    global $db;
    $rte = new Route($db);
    $out = [];
    foreach ($list as $one) {
        $path = trim((string)$one, '/');
        $parts = explode('/', $path);
        $op = (count($parts) > 1 && $rte->isRouteReserved($parts[0], $parts[1])) ? $parts[1] : '';
        $row = ($op === '') ? $rte->getRoute($path) : [];
        $out[$path] = ['op' => $op, 'kind' => (string)($row['kind'] ?? '')];
    }
    return $out;
}

$job = (string)($argv[1] ?? '');
$args = array_slice($argv, 3);
$data = match ($job) {
    'paths' => getProbePaths($args),
    default => ['error' => 'unknown job: '.$job],
};
echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
