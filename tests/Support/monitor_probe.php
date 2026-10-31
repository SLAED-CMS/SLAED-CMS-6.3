<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the signals of the server: every rule reads the facts it is given, so one calm server is moved a fact at a time
$probework = (string)($argv[1] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';
require_once BASE_DIR.'/lang/en.php';
require_once BASE_DIR.'/admin/lang/en.php';
$pwant = ['ctype', 'curl', 'filter', 'gd', 'json', 'mbstring', 'PDO', 'pdo_mysql', 'session',
    'dom', 'fileinfo', 'intl', 'Zend OPcache', 'openssl', 'Phar', 'sodium', 'zip', 'zlib'];
$pcalm = [
    'time' => mktime(12, 0, 0, 10, 30, 2026),
    'cpu' => 20.0,
    'ram' => 50.0,
    'disk' => 30.0,
    'op_on' => true,
    'op_hit' => 96.6,
    'op_used' => 1048576,
    'op_total' => 2097152,
    'conn' => 2,
    'max_conn' => 100,
    'pool' => 2097152,
    'dbsize' => 1048576,
    'peak' => 1048576,
    'limit' => 104857600,
    'gen' => 0.1,
    'php' => '8.4.27',
    'db' => '11.4.5-MariaDB-1:11.4.5+maria~ubu2404',
    'web' => 'Nginx 1.18.0',
    'https' => true,
    'proto' => 'HTTP/2.0',
    'exts' => array_merge($pwant, ['Core', 'apcu']),
    'uptime' => 7200,
];

# The level and the fact of one signal with the given facts changed
function getProbeSignal(string $key, array $over): array {
    global $pcalm;
    foreach (getServerSignalList($over + $pcalm) as $one) if ($one['key'] === $key) return [$one['level'], $one['fact']];
    return [];
}

$pout = [
    'calm' => getServerSignalList($pcalm),
    'moved' => getServerSignalList(['ram' => 80.0, 'cpu' => 95.0, 'proto' => 'HTTP/1.1'] + $pcalm),
];
$pout['cpu'] = array_map(static fn(float $num): string => getProbeSignal('cpu', ['cpu' => $num])[0], [74.9, 75.0, 89.9, 90.0]);
$pout['ram'] = array_map(static fn(float $num): string => getProbeSignal('ram', ['ram' => $num])[0], [74.9, 75.0, 89.9, 90.0]);
$pout['disk'] = array_map(static fn(float $num): string => getProbeSignal('disk', ['disk' => $num])[0], [79.9, 80.0, 94.9, 95.0]);
$pout['opcache'] = [getProbeSignal('opcache', ['op_hit' => 79.9])[0], getProbeSignal('opcache', ['op_hit' => 80.0])[0], getProbeSignal('opcache', ['op_on' => false])];
$pout['conn'] = [getProbeSignal('conn', ['conn' => 74])[0], getProbeSignal('conn', ['conn' => 75])[0], getProbeSignal('conn', ['conn' => 90])[0],
    getProbeSignal('conn', ['conn' => null, 'max_conn' => null])];
$pout['innodb'] = [getProbeSignal('innodb', ['pool' => 1048576])[0], getProbeSignal('innodb', ['pool' => 1048575])[0], getProbeSignal('innodb', ['pool' => null])];
$pout['phpmem'] = [getProbeSignal('phpmem', ['peak' => 78643200])[0], getProbeSignal('phpmem', ['peak' => 94371840])[0], getProbeSignal('phpmem', ['limit' => 0])];
$pout['gen'] = [getProbeSignal('gen', ['gen' => 0.5])[0], getProbeSignal('gen', ['gen' => 0.51])[0], getProbeSignal('gen', ['gen' => 2.0])[0],
    getProbeSignal('gen', ['gen' => 2.01])[0],
    getProbeSignal('gen', ['gen' => null])];
$pout['php'] = [getProbeSignal('php', []), getProbeSignal('php', ['php' => '8.1.30']), getProbeSignal('php', ['php' => '9.0.1'])];
$pout['soft'] = [getProbeSignal('soft', []), getProbeSignal('soft', ['db' => '10.5.20-MariaDB']), getProbeSignal('soft', ['db' => '8.0.36-0ubuntu0.24.04.1']),
    getProbeSignal('soft', ['db' => '11.7.2-MariaDB']),
    getProbeSignal('soft', ['db' => '8.4.0', 'time' => mktime(12, 0, 0, 5, 1, 2032)])];
$pout['proto'] = [getProbeSignal('proto', ['proto' => 'HTTP/3.0'])[0], getProbeSignal('proto', ['proto' => 'HTTP/1.1']), getProbeSignal('proto', ['https' => false]),
    getProbeSignal('proto', ['https' => false, 'proto' => ''])];
$pout['ext'] = [
    getProbeSignal('ext', []),
    getProbeSignal('ext', ['exts' => array_diff($pwant, ['sodium'])]),
    getProbeSignal('ext', ['exts' => array_diff($pwant, ['gd', 'sodium'])]),
];
$pout['uptime'] = [getProbeSignal('uptime', ['uptime' => 59]), getProbeSignal('uptime', ['uptime' => 60])[0], getProbeSignal('uptime', ['uptime' => null])];
$pout['weight'] = getExtensionWeight(array_merge(array_diff($pwant, ['gd', 'zip']), ['apcu', 'Core']));
$pout['minutes'] = [getUptimeMinutes('11d 21h 23m'), getUptimeMinutes('N/A')];
$plist = static fn(array $lvls): array => array_map(static fn(string $one): array => ['level' => $one], $lvls);
$pfine = $plist(array_fill(0, 13, 'ok'));
$pout['lamp'] = [
    'busy' => getServerLamp($pfine, 600.0, 20.0),
    'lull' => getServerLamp($pfine, 19.0, 20.0),
    'flat' => getServerLamp($pfine, 0.0, 20.0),
    'tiny' => getServerLamp($pfine, 0.4, 20.0),
    'none' => getServerLamp($pfine, null, 20.0),
    'warn' => getServerLamp($plist(array_merge(['warn'], array_fill(0, 12, 'ok'))), 0.0, 20.0),
    'fail' => getServerLamp($plist(array_merge(['danger', 'warn'], array_fill(0, 11, 'ok'))), 0.0, 60.0),
];
echo json_encode($pout, JSON_UNESCAPED_UNICODE);
