<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the data of the home cockpit: the signals of the lamp over journals, states and queues in scratch, the snapshot and the one store of the monitor
$probework = (string)($argv[1] ?? '');
require_once __DIR__.'/probe_boot.php';
if (!is_dir($probework.'/config')) {
    mkdir($probework.'/config', 0777, true);
    foreach (glob(BASE_DIR.'/config/*.php') ?: [] as $file) {
        if (basename($file) !== 'local.php') copy($file, $probework.'/config/'.basename($file));
    }
}
define('CONFIG_DIR', $probework.'/config');
define('CACHE_DIR', $probework.'/cache');
define('BACKUP_DIR', $probework.'/backup');
define('UPDATE_DIR', $probework.'/update');
foreach (['backup', 'update/client', 'logs/scheduler'] as $one) if (!is_dir($probework.'/'.$one)) mkdir($probework.'/'.$one, 0777, true);
require_once BASE_DIR.'/core/system.php';
require_once BASE_DIR.'/admin/lang/en.php';

# The signals keyed by check, each as its level and fact, read fresh
function getProbeSignals(): array {
    $out = [];
    foreach (getSystemSignals(0) as $one) $out[$one['key']] = [$one['level'], $one['fact']];
    return $out;
}

# Write a journal of Logger with the given number of error lines stamped now
function setProbeJournal(string $name, int $num): void {
    $line = json_encode(['ts' => date('c'), 'level' => 'error', 'channel' => 'probe', 'message' => 'probe']);
    file_put_contents(LOGS_DIR.'/'.$name, str_repeat($line."\n", $num));
}

$sgtime = microtime(true);
$conf['security']['log_b'] = '1';
$conf['security']['dump_age'] = '7';
$pout = ['calm' => getProbeSignals(), 'order' => array_column(getSystemSignals(0), 'key'), 'levels' => array_column(getSystemSignals(0), 'level')];
setProbeJournal('error_php.log', 3);
setProbeJournal('error_sql.log', 1);
$pout['few'] = getProbeSignals();
setProbeJournal('error_php.log', 100);
setProbeJournal('error_sql.log', 10);
$pout['many'] = getProbeSignals();
setSchedulerState('selfcheck', ['last_run' => time(), 'roots' => [['url' => 'https://probe.test/config/check.txt', 'state' => 'open']]]);
setSchedulerState('dbbackup', ['last_success' => time() - 8 * 86400]);
$pout['open'] = getProbeSignals();
setSchedulerState('selfcheck', ['last_run' => time(), 'roots' => [['url' => 'https://probe.test/config/check.txt', 'state' => 'closed']]]);
setSchedulerState('dbbackup', ['last_success' => time() - 3600]);
$pout['closed'] = getProbeSignals();
$conf['security']['log_b'] = '0';
$pout['nobackup'] = getProbeSignals();
$conf['security']['log_b'] = '1';
$conf['scheduler']['jobs']['probejob'] = ['active' => 1, 'schedule' => '0 * * * *', 'title' => 'Probe'];
setSchedulerState('probejob', ['last_run' => time() - 3 * 86400]);
file_put_contents(UPDATE_DIR.'/client/state.json', json_encode(['found' => ['version' => '9.9.9', 'security' => true]]));
$pout['late'] = getProbeSignals();
$db->setSqlBegin();
for ($i = 0; $i < 20; $i++) addLoginLog(0, true, 'password', 'probe');
$db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_mail (kind, email, title, body, time) VALUES (\'probe\', \'probe@probe.test\', \'probe\', \'probe\', NOW() - INTERVAL 2 HOUR)');
$pout['queue'] = getProbeSignals();
$db->setSqlRollback();
$pout['after'] = getProbeSignals();
$phome = $conf['homeurl'];
$conf['homeurl'] = 'https://probe.test/';
$pout['https'] = [getProbeSignals()['https']];
$conf['homeurl'] = 'http://probe.test/';
$pout['https'][] = getProbeSignals()['https'];
$conf['homeurl'] = $phome;
$pout['text'] = ['day' => sprintf(_ADM_SIG_DAY, '%s'), 'open' => _ADM_SIG_OPEN, 'none' => _ADM_SIG_NONE, 'off' => _OFF, 'late' => _ADM_SIG_LATE, 'wait' => _ADM_SIG_WAIT,
    'root' => _ADM_SIG_ROOT];
$pout['setup'] = is_file(PUBLIC_DIR.'/setup.php');
$pcalls = 0;
$pmake = static function () use (&$pcalls): array {
    $pcalls++;
    return ['num' => $pcalls];
};
$pout['store'] = [getMonitorCache('probe', 60, $pmake), getMonitorCache('probe', 60, $pmake), getMonitorCache('probe', 0, $pmake), $pcalls];
$pfile = Cache::getFile(['monitor', 'probe'], 'json');
$pout['file'] = is_file($pfile) ? json_decode((string)file_get_contents($pfile), true) : null;
$pout['bare'] = getCockpitData()['strip'];
$phour = (int)date('G');
$pline = array_fill(0, 20, '');
$pline[0] = date('d.m.Y');
foreach ([13 => 2, 17 => 100, 18 => 15, 19 => 6144] as $pidx => $pval) {
    $pline[$pidx] = implode(',', array_replace(array_fill(0, 24, 0), [$phour => $pval]));
}
file_put_contents(COUNTER_DIR.'/statistic.log', implode('|', $pline));
$pout['cockpit'] = getCockpitData();
$pcalm = [['level' => 'ok'], ['level' => 'ok']];
$pout['tone'] = ['lull' => getCockpitTone($pcalm), 'warn' => getCockpitTone([['level' => 'ok'], ['level' => 'warn']])];
$pline[13] = implode(',', array_replace(array_fill(0, 24, 0), [$phour => 40]));
file_put_contents(COUNTER_DIR.'/statistic.log', implode('|', $pline));
$pout['tone']['busy'] = getCockpitTone($pcalm);
$pline[0] = date('d.m.Y', strtotime('-1 day'));
file_put_contents(COUNTER_DIR.'/statistic.log', implode('|', $pline));
$pout['tone']['stale'] = getCockpitTone($pcalm);
echo json_encode($pout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
