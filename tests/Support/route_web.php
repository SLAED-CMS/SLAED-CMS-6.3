<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The web server of tests/Support/route_probe.php, run as the router of the built-in server `php -S` with the scratch root in SLAED_ROUTE_ROOT
# It serves the real index.php and admin.php with every writable directory and the configuration redirected into scratch, over the probe's disposable database
# The visitor comes from the header X-Probe-Who and is one of the accounts the probe seeded
# The account helper is both a site account and the administrator of the support type, the way an operator answers requests on the site
# The document root is public/, as on the stand: a file at its top is served as it is and every other path reaches index.php, or admin.php by its own address
# There the light path serves a public folder of the scratch upload root and the core answers a path that is no address of the site
# A stylesheet, script, font or picture under templates/ or plugins/ is left to the built-in server started on public/, so a browser on the probe sees the page as the site does
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
if (preg_match('#^/(templates|plugins)/[A-Za-z0-9_/-]+(\.[A-Za-z0-9_-]+)*\.(css|js|woff2?|png|webp|svg|jpg|gif|ico)$#D', $rpath)) return false;
if (preg_match('#^/[A-Za-z0-9_-]+\.(html|txt|ico|xml)$#D', $rpath) && is_file(dirname(__DIR__, 2).'/public'.$rpath)) return false;
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__, 2)));
define('PUBLIC_DIR', BASE_DIR.'/public');
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
chdir(PUBLIC_DIR);
$radmin = $rpath === '/admin.php' || str_starts_with($rpath, '/admin.php/');
unset($rroot, $rpath, $rdirs, $rkey, $rdir, $rglob, $rusers, $radmins);
$_SERVER['SCRIPT_NAME'] = $radmin ? '/admin.php' : '/index.php';
if ($radmin) {
    define('ADMIN_FILE', true);
    $sgtime = microtime(true);
}
require $radmin ? BASE_DIR.'/admin/index.php' : PUBLIC_DIR.'/index.php';
