<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The web server of the probes, run as the router of the built-in server `php -S` with the scratch root in SLAED_WEB_ROOT; it answers a closed set of paths and nothing else
# /uploads/<dir>/<file> behaves like a web server carrying the shared nginx rule of docs/node/09: 403 for a directory holding the .htaccess guard, the file otherwise
# The file open in that root switches the rule off, which is a server whose owner never added it; the upload root itself comes from SLAED_WEB_UPLOADS
# /stream answers one fixture of <root>/files through the shipped getFileStream(), lifted out of core/system.php, and records what the callback and the shutdown saw
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
if (preg_match('#^/uploads/([a-z0-9]+)/([A-Za-z0-9_.-]+)$#D', $wpath, $whit)) {
    $wdir = $wups.'/'.$whit[1];
    if (!is_file($wroot.'/open') && is_file($wdir.'/.htaccess')) http_response_code(403);
    elseif (is_file($wdir.'/'.$whit[2])) readfile($wdir.'/'.$whit[2]);
    else http_response_code(404);
    exit;
}
if ($wpath !== '/stream') {
    http_response_code(404);
    exit;
}
define('FUNC_FILE', true);
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__, 2)));
require_once BASE_DIR.'/core/classes/cache.php';
$wlift = $wroot.'/stream_fn.php';
if (!is_file($wlift)) {
    $wcode = (string)file_get_contents(BASE_DIR.'/core/system.php');
    $wfrom = strpos($wcode, "\nfunction getFileStream(");
    $wend = ($wfrom === false) ? false : strpos($wcode, "\n}\n", $wfrom);
    if ($wend === false) {
        http_response_code(500);
        exit;
    }
    file_put_contents($wlift, "<?php\n".substr($wcode, $wfrom, $wend - $wfrom + 3));
}
require $wlift;
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
    getFileStream($wroot.'/files/'.$wfile, (string)($wq['n'] ?? $wfile), (string)$wq['mime'], isset($wq['inline']), isset($wq['cached']), $wstart);
}
getFileStream($wroot.'/files/'.$wfile, (string)($wq['n'] ?? $wfile));
