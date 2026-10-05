<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The web server of PublicTreeTest, run as the router of the built-in server `php -S -t public/` with the scratch root in SLAED_WEB_ROOT, a server whose document root is public/
# A file of the document root is served as it is and any other address outside uploads/ answers 404, which is what a server rooted at public/ answers for the rest of the project
# An address under uploads/ runs the entry public/index.php over the scratch upload root, and the files the request loaded go to files.json, so the test sees the core never booted
# The directory is served by the stand as well, so anything but the built-in server gets a plain 404 before a single line of it runs
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
error_reporting(0);
ini_set('display_errors', '0');
$lroot = str_replace('\\', '/', (string)getenv('SLAED_WEB_ROOT'));
$lpath = rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
$lpub = str_replace('\\', '/', dirname(__DIR__, 2)).'/public';
if ($lroot === '' || !is_dir($lroot)) {
    http_response_code(500);
    exit;
}
if (!str_starts_with($lpath, '/uploads/')) {
    if (!in_array('..', explode('/', $lpath), true) && is_file($lpub.$lpath) && pathinfo($lpath, PATHINFO_EXTENSION) !== 'php') return false;
    http_response_code(404);
    exit;
}
define('UPLOADS_DIR', $lroot.'/uploads');
register_shutdown_function(static function () use ($lroot): void {
    file_put_contents($lroot.'/files.json', json_encode(array_map(static fn(string $v): string => str_replace('\\', '/', $v), get_included_files())));
});
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $lpub.'/index.php';
