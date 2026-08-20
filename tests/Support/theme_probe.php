<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the theme-creation gate of docs/THEME-ETALON-2026.md: the runtime file list a theme package has to satisfy
# boots the real core like index.php, so checkThemeAssets() reads the shipped editor manifests rather than a list a fixture invented
# The caller hands the theme names to ask about; the scratch copy itself is built and removed through tests/Support/theme_scratch.php,
# which is the one lifecycle both halves of the theme-creation gate share - the static half in ThemeCreationTest and the HTTP half of
# the screenshot runner, which has no PHP of its own and reaches the lifecycle through the `make`, `pick` and `gone` jobs below
# `assets` writes nothing. `make` and `gone` touch only a templates/scratch-* directory this file named, and `pick` moves one users row
# back and forth and answers with the value it replaced, so the caller can put it back in its own finally
$probework = (string)($argv[2] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';
require_once __DIR__.'/theme_scratch.php';

# The runtime verdict for every theme name the caller asked about, keyed by the name so a missing one is visible rather than silent
function getProbeThemes(array $list): array {
    $out = [];
    foreach ($list as $name) $out[(string)$name] = checkThemeAssets((string)$name);
    return $out;
}

# Point one account at one theme and answer with the theme it was pointed at before, which is what lets the caller put it back
# getTheme() reads the column of the signed-in visitor before it falls back to the site default, so this is the one lever that
# selects a theme for an HTTP request without editing the configuration of a running stand
function setUserThemeName(string $user, string $theme): array {
    global $db;
    $row = $db->getSqlRow($db->getSqlQuery('SELECT id, theme FROM '.PREFIX_DB.'_users WHERE name = :name', [':name' => $user]));
    if (!$row) return ['error' => 'no account named '.$user];
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET theme = :theme WHERE id = :id', [':theme' => $theme, ':id' => (int)$row[0]]);
    return ['was' => (string)$row[1], 'now' => $theme];
}

$job = (string)($argv[1] ?? '');
$args = array_slice($argv, 3);
$data = match ($job) {
    'assets' => getProbeThemes($args),
    'make' => setScratchTheme((string)($args[0] ?? 'lite')),
    'gone' => ['gone' => deleteScratchTheme((string)($args[0] ?? ''))],
    'pick' => setUserThemeName((string)($args[0] ?? ''), (string)($args[1] ?? '')),
    default => ['error' => 'unknown job: '.$job],
};
echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
