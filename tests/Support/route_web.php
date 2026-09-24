<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The web server of tests/Support/route_probe.php, run as the router of the built-in server `php -S` with the scratch root in SLAED_ROUTE_ROOT
# It serves the real index.php and admin.php of the tree with every writable directory and the configuration redirected into scratch, so a route answers exactly as
# on a site whose database is the disposable one of the probe; the visitor comes from the header X-Probe-Who and is one of the accounts the probe seeded,
# helper being both a site account and the administrator of the support type, the way an operator answers requests on the site
# /uploads/<dir>/<file> behaves like a web server carrying the shared nginx rule of docs/node/09: 403 for a directory holding the .htaccess guard, the file otherwise
# The directory is served by the stand as well, so anything but the built-in server gets a plain 404 before a single line of it runs
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}
ini_set('display_errors', '0');
ini_set('log_errors', '0');
$rroot = str_replace('\\', '/', (string)getenv('SLAED_ROUTE_ROOT'));
$rpath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if ($rroot === '' || !is_dir($rroot.'/config')) {
    http_response_code(500);
    exit;
}
if (preg_match('#^/uploads/([a-z0-9]+)/([A-Za-z0-9_.-]+)$#D', $rpath, $rhit)) {
    $rdir = $rroot.'/uploads/'.$rhit[1];
    if (is_file($rdir.'/.htaccess')) http_response_code(403);
    elseif (is_file($rdir.'/'.$rhit[2])) readfile($rdir.'/'.$rhit[2]);
    else http_response_code(404);
    exit;
}
if (!in_array($rpath, ['/', '/index.php', '/admin.php'], true)) {
    http_response_code(404);
    exit;
}
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__, 2)));
$rdirs = ['CONFIG_DIR' => 'config', 'BACKUP_DIR' => 'backup', 'CACHE_DIR' => 'cache', 'COUNTER_DIR' => 'counter', 'LOGS_DIR' => 'logs', 'SITEMAP_DIR' => 'sitemap',
    'CAPTCHA_DIR' => 'captcha', 'UPLOADS_DIR' => 'uploads'];
foreach ($rdirs as $rkey => $rdir) define($rkey, $rroot.'/'.$rdir);
$rglob = require CONFIG_DIR.'/global.php';
$rwho = (string)($_SERVER['HTTP_X_PROBE_WHO'] ?? '');
$rusers = ['anna' => '2:anna:hash-anna', 'boris' => '3:boris:hash-boris', 'clara' => '4:clara:hash-clara', 'helper' => '5:helper:hash-helper'];
$radmins = ['root' => '1:root:hash-root', 'moder' => '2:moder:hash-moder', 'boss' => '3:boss:hash-boss', 'docsman' => '4:docsman:hash-docsman',
    'helper' => '5:helper:hash-helper'];
if (isset($rusers[$rwho])) $_COOKIE[$rglob['user_c'].'-account'] = base64_encode($rusers[$rwho]);
session_start();
if (isset($radmins[$rwho])) $_SESSION[$rglob['admin_c']] = base64_encode($radmins[$rwho]);
foreach (['HTTP_HOST', 'REQUEST_URI', 'HTTP_REFERER', 'HTTP_USER_AGENT'] as $rkey) if (isset($_SERVER[$rkey])) putenv($rkey.'='.$_SERVER[$rkey]);
chdir(BASE_DIR);
unset($rroot, $rpath, $rdirs, $rkey, $rdir, $rglob, $rusers, $radmins, $rhit);
require BASE_DIR.((parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) === '/admin.php') ? '/admin.php' : '/index.php');
