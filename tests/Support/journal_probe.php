<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for batch 1 of docs/0-PRIVATE-DATA-2026.md: no secret of a request reaches a journal
# It boots the real core with the configuration copied into scratch and every journal of the batch switched on there, so nothing below config/ or storage/ is written
# The request carries a password field, a cookie and a session value, each a random string the test then looks for in every file the run left in LOGS_DIR
# Modes: write drives both writers and the dashboard reader once; rotate fills the user login journal past its limit first and reports where the next entry went
$pmode = (string)($argv[1] ?? '');
$probework = (string)($argv[2] ?? '');
require_once __DIR__.'/probe_boot.php';
if (!is_dir($probework.'/config')) {
    mkdir($probework.'/config', 0777, true);
    foreach (glob(BASE_DIR.'/config/*.php') ?: [] as $file) {
        if (basename($file) !== 'local.php') copy($file, $probework.'/config/'.basename($file));
    }
}
$psec = require $probework.'/config/security.php';
$psec['security'] = array_merge($psec['security'], ['log' => '1', 'log_a' => '1', 'log_u' => '1', 'log_size' => ($pmode === 'rotate') ? '64' : '10485760']);
file_put_contents($probework.'/config/security.php', "<?php\nreturn ".var_export($psec, true).";\n");
if (is_file($probework.'/config/local.php')) unlink($probework.'/config/local.php');
define('CONFIG_DIR', $probework.'/config');
define('CACHE_DIR', $probework.'/cache');
$pkeys = ['pass' => 'pw'.bin2hex(random_bytes(8)), 'cookie' => 'ck'.bin2hex(random_bytes(8)), 'session' => 'ss'.bin2hex(random_bytes(8))];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/index.php?name=account&op=login';
$_POST = ['user_name' => 'probeuser', 'user_password' => $pkeys['pass']];
$_COOKIE = ['probe_cookie' => $pkeys['cookie']];
session_start();
$_SESSION['probe_session'] = $pkeys['session'];
if ($pmode === 'rotate') file_put_contents(LOGS_DIR.'/log_user.log', str_repeat("old entry\n", 20));
require_once BASE_DIR.'/core/system.php';

# Read every journal the run left in the scratch folder, keyed by name, so the test can search all of them for a secret; a rotation archive is binary and is reported by name
function getProbeFiles(): array {
    $out = [];
    foreach (scandir(LOGS_DIR) ?: [] as $name) {
        if (is_file(LOGS_DIR.'/'.$name)) $out[$name] = str_ends_with($name, '.log') ? (string)file_get_contents(LOGS_DIR.'/'.$name) : '';
    }
    return $out;
}

addLoginReport(0, 0, 'probeuser');
if ($pmode === 'rotate') {
    echo json_encode(['files' => getProbeFiles()]);
    exit;
}
addLoginReport(1, 0, 'probeadmin');
$pfiles = getProbeFiles();
foreach (array_keys($pfiles) as $name) {
    if ($name !== 'request.log') unlink(LOGS_DIR.'/'.$name);
}
echo json_encode([
    'keys' => $pkeys,
    'files' => $pfiles,
    'params' => (new ReflectionFunction('addLoginReport'))->getNumberOfParameters(),
    'event' => getSecurityEventHours(24),
]);
