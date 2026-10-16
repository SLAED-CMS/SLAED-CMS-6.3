<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The gate each entry passes first, before the core and the light path of uploads: it reads only core/version.php and config/update.php
require_once BASE_DIR.'/core/version.php';

# The newest version whose database steps are marked in config/update.php, '' for a site without a mark, null without the file, where no site is installed yet
function getStepMark(string $dir): ?string {
    $file = $dir.'/update.php';
    if (!is_file($file)) return null;
    $data = (static fn(string $path): mixed => include $path)($file);
    $list = is_array($data) && is_array($data['update']['steps'] ?? null) ? array_map('strval', array_keys($data['update']['steps'])) : [];
    $vers = array_filter($list, static fn(string $v): bool => preg_match('#^\d{1,5}\.\d{1,5}\.\d{1,5}$#D', $v) === 1);
    usort($vers, 'version_compare');
    return (string)(end($vers) ?: '');
}

# Whether the code waits for its database steps; the panel then opens its light mode, every other entry answers the static 503 here and ends
function checkSiteGate(bool $admin = false): bool {
    $mark = getStepMark(defined('CONFIG_DIR') ? CONFIG_DIR : BASE_DIR.'/config');
    if ($mark === null || ($mark !== '' && version_compare(SL_VERSION, $mark, '<='))) return false;
    if ($admin) return true;
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(503);
    header('Retry-After: 600');
    header('Cache-Control: no-store');
    if (preg_match('#/api(/|$)#', (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH)) === 1) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'error' => ['code' => 'maintenance', 'message' => 'Maintenance', 'details' => new stdClass()],
            'meta' => ['id' => bin2hex(random_bytes(8)), 'ver' => 'v1', 'ms' => 0, 'now' => (int)floor(microtime(true) * 1000)]]);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        readfile(PUBLIC_DIR.'/error.html');
    }
    exit;
}
