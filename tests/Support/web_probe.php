<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The web server of the probes, run as the router of the built-in server `php -S` with the scratch root in SLAED_WEB_ROOT; it answers a closed set of paths and nothing else
# /uploads/<path> runs the light path of core/stream.php over the upload root of SLAED_WEB_UPLOADS: a public folder is served, every other address answers 404
# The file open in the scratch root switches the light path off and serves the file as it is, which is a server whose document root still holds the upload root
# /stream answers one fixture of <root>/files through the shipped getFileStream() of core/stream.php, and records what the callback and the shutdown saw
# The directory is served by the stand as well, so anything but the built-in server gets a plain 404 before a single line of it runs
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
error_reporting(0);
ini_set('display_errors', '0');
$wroot = str_replace('\\', '/', (string)getenv('SLAED_WEB_ROOT'));
$wups = str_replace('\\', '/', (string)(getenv('SLAED_WEB_UPLOADS') ?: $wroot.'/uploads'));
$wpath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if ($wroot === '' || !is_dir($wroot)) {
    http_response_code(500);
    exit;
}
if ($wpath !== '/stream' && !str_starts_with($wpath, '/uploads/')) {
    http_response_code(404);
    exit;
}
define('MODULE_FILE', true);
define('FUNC_FILE', true);
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__, 2)));
define('UPLOADS_DIR', $wups);
require_once BASE_DIR.'/core/classes/cache.php';
require_once BASE_DIR.'/core/stream.php';
if ($wpath !== '/stream') {
    $wrel = substr($wpath, 9);
    if (is_file($wroot.'/open')) {
        if (!in_array('..', explode('/', $wrel), true) && is_file($wups.'/'.$wrel)) readfile($wups.'/'.$wrel);
        else http_response_code(404);
        exit;
    }
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    setUploadStream((string)getUploadRequest());
}
parse_str((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY), $wq);
$wfile = (string)($wq['f'] ?? '');
if (!preg_match('#^[a-z]+\.[a-z0-9]+$#D', $wfile)) $wfile = 'none.bin';
$wtime = microtime(true);
register_shutdown_function(static function () use ($wroot, $wtime): void {
    file_put_contents($wroot.'/last.json', json_encode(['status' => connection_status(), 'peak' => memory_get_peak_usage(), 'time' => microtime(true) - $wtime]));
});
if (isset($wq['cookie'])) header('Set-Cookie: probe=1; path=/');
$wstart = isset($wq['start']) ? static function () use ($wroot): void {
    file_put_contents($wroot.'/start.log', '1', FILE_APPEND);
} : null;
if (isset($wq['mime'])) {
    getFileStream($wroot.'/files/'.$wfile, (string)($wq['n'] ?? $wfile), (string)$wq['mime'], isset($wq['inline']), (string)($wq['cache'] ?? 'none'), $wstart);
}
getFileStream($wroot.'/files/'.$wfile, (string)($wq['n'] ?? $wfile));
