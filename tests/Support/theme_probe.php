<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the theme-creation gate of docs/THEME-ETALON-2026.md: the runtime file list a theme package has to satisfy
# boots the real core like index.php, so checkThemeAssets() reads the shipped editor manifests rather than a list a fixture invented
# The caller hands the theme names to ask about, because the copy under test is made and removed by the test and never by this file
# Nothing is written: every answer is one call into checkThemeAssets(), which only ever stats files
$probework = (string)($argv[2] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';

# The runtime verdict for every theme name the caller asked about, keyed by the name so a missing one is visible rather than silent
function getProbeThemes(array $list): array {
    $out = [];
    foreach ($list as $name) $out[(string)$name] = checkThemeAssets((string)$name);
    return $out;
}

$job = (string)($argv[1] ?? '');
$args = array_slice($argv, 3);
$data = match ($job) {
    'assets' => getProbeThemes($args),
    default => ['error' => 'unknown job: '.$job],
};
echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
