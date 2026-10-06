<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for batch 3 of docs/0-PRIVATE-DATA-2026.md: the core in scratch, the verdicts over a stubbed transport, the job without a network
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
define('UPLOADS_DIR', $probework.'/uploads');
foreach (['uploads', 'roots/project', 'roots/storage', 'roots/config', 'roots/uploads', 'roots/info'] as $one) mkdir($probework.'/'.$one, 0777, true);
require_once BASE_DIR.'/core/system.php';
require_once BASE_DIR.'/admin/lang/en.php';

# The five watched roots in scratch, under the address paths of the shipped list
function getProbeRoots(): array {
    $work = $GLOBALS['probework'].'/roots';
    return ['' => $work.'/project', 'storage' => $work.'/storage', 'config' => $work.'/config', 'uploads' => $work.'/uploads', 'admin/info' => $work.'/info'];
}

# A stubbed transport that records every address it is asked and answers with the given status; a body of null serves the marker of the asked root as the file is
function getProbeFetch(array &$seen, bool $ok, int $code, ?string $body, string $error = ''): callable {
    return static function (string $url) use (&$seen, $ok, $code, $body, $error): array {
        $seen[] = $url;
        $root = substr($url, strlen(rtrim($GLOBALS['conf']['homeurl'], '/')) + 1, -strlen('/check.txt'));
        $file = (getProbeRoots()[$root] ?? '').'/check.txt';
        $text = ($body === null) ? (is_file($file) ? (string)file_get_contents($file) : '') : $body;
        return ['ok' => $ok, 'code' => $code, 'body' => $ok ? $text : '', 'error' => $error];
    };
}

# The state of each root of one verdict, keyed by root
function getProbeStates(array $list): array {
    return array_map(fn(array $v): string => $v['state'], $list);
}

$conf['homeurl'] = 'https://probe.test/site/';
$proots = getPrivateRoots();
$pout = ['roots' => array_keys($proots), 'shipped' => [$proots[''] === BASE_DIR, $proots['config'] === CONFIG_DIR, $proots['uploads'] === UPLOADS_DIR]];
$pseen = [];
$pout['open'] = checkPrivateRoots(getProbeRoots(), getProbeFetch($pseen, true, 200, null));
$pout['asked'] = $pseen;
$pmark = (string)file_get_contents(getProbeRoots()['uploads'].'/check.txt');
$pout['marks'] = array_map(fn(string $v): string => (string)file_get_contents($v.'/check.txt'), getProbeRoots());
$pout['closed'] = checkPrivateRoots(getProbeRoots(), getProbeFetch($pseen, true, 404, '<html><body>Page not found</body></html>'));
$pout['unknown'] = checkPrivateRoots(getProbeRoots(), getProbeFetch($pseen, false, 0, '', 'Connection refused'));
$pout['hidden'] = getProbeStates(checkPrivateRoots(getProbeRoots(), getProbeFetch($pseen, true, 404, null)));
$pout['page'] = getProbeStates(checkPrivateRoots(getProbeRoots(), getProbeFetch($pseen, true, 200, '<html><body>Error: the page does not exist</body></html>')));
$pout['kept'] = (string)file_get_contents(getProbeRoots()['uploads'].'/check.txt') === $pmark;
unlink(getProbeRoots()['uploads'].'/check.txt');
$pout['again'] = getProbeStates(checkPrivateRoots(['uploads' => getProbeRoots()['uploads']], getProbeFetch($pseen, true, 200, null)));
$pnew = is_file(getProbeRoots()['uploads'].'/check.txt') ? (string)file_get_contents(getProbeRoots()['uploads'].'/check.txt') : '';
$pout['rewritten'] = [(bool)preg_match('#^[0-9a-f]{32}$#D', $pnew), $pnew !== $pmark];
$pseen = [];
$conf['homeurl'] = 'probe.test';
$pout['noweb'] = checkPrivateRoots(['uploads' => $probework.'/uploads'], getProbeFetch($pseen, true, 200, null));
$pout['noweb_asked'] = $pseen;
$pout['noweb_mark'] = is_file($probework.'/uploads/check.txt');
$tpl = new Template('admin');
$pout['none'] = getSelfCheckAlert(true);
$conf['scheduler']['active'] = '1';
$prun = addSchedulerRun('selfcheck', 'manual');
$pstate = getSchedulerState('selfcheck');
$pout['job'] = ['status' => $prun['status'] ?? '', 'message' => $prun['message'] ?? '', 'stored' => $pstate['last_status'], 'roots' => $pstate['roots'] ?? null];
$pout['alert'] = [getSelfCheckAlert(), getSelfCheckAlert(true)];
setSchedulerState('selfcheck', array_replace($pstate, ['roots' => array_map(fn(array $v): array => ['state' => 'closed'] + $v, $pstate['roots'] ?? [])]));
$pout['clear'] = [getSelfCheckAlert(), getSelfCheckAlert(true)];
setSchedulerState('selfcheck', array_replace(getSchedulerState('selfcheck'), ['last_run' => time() - 2 * 86400]));
$pout['stale'] = [getSelfCheckAlert(), getSelfCheckAlert(true)];
$pout['text'] = ['none' => _SEC_CHECK_NONE, 'open' => _SEC_CHECK_OPEN, 'unknown' => _SEC_CHECK_UNK, 'done' => explode('%s', _SEC_CHECK_DONE)[1]];
echo json_encode($pout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
