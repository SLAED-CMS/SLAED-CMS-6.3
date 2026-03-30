<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function scheduler(): void {
    global $afile, $conf, $tpl;
    $jobs = getSchedulerJobs();
    $navi = setAdminNavi(['ops' => ['name=scheduler', 'name=scheduler&amp;op=add', 'name=scheduler&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO]]);
    $cont = '';
    $seclink = ' <a href="'.$afile.'.php?name=security&amp;op=config">'.htmlspecialchars(_SCHEDULER_WARN_GO, ENT_QUOTES, 'UTF-8').'</a>.';
    $cont .= (!$conf['security']['log_b']) ? $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _SCHEDULER_WARN_DB.$seclink]) : '';
    $cont .= (!$conf['security']['log_d']) ? $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _SCHEDULER_WARNLOG.$seclink]) : '';
    $head = '<th>'._TITLE.'</th><th>'._SCHEDULER_NEXTRUN.'</th><th>'._SCHEDULER_RESULT.'</th><th>'._SCHEDULER_PRIO.'</th><th>'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th>';
    $rows = '';
    foreach ($jobs as $job) {
        $name = $job['name'];
        $state = getSchedulerState($name);
        $next = getSchedulerPlannedTime($job, $state);
        $type = (($job['type'] ?? '') === 'custom') ? _SCHEDULER_CUSTOM : _SCHEDULER_SYSTEM;
        $stat = match ($state['last_status'] ?? 'idle') {
            'success' => _YES,
            'failed' => _NO,
            'running' => _SCHEDULER_RUNNING,
            default => _SCHEDULER_IDLE,
        };
        $run = (int)($state['last_run'] ?? 0);
        $ok = (int)($state['last_success'] ?? 0);
        $last = $run > 0 ? date(_TIMESTRING, $run) : _NO;
        $lastok = $ok > 0 ? date(_TIMESTRING, $ok) : _NO;
        $nextr = ($next > 0) ? date(_TIMESTRING, $next) : _NO;
        $trig = $state['last_trigger'] ?? _NO;
        $time = $state['last_duration'] ?? 0;
        $fail = $state['fail_count'] ?? 0;
        $note = trim($state['last_message'] ?? '');
        if ($note === '') $note = trim($state['last_error'] ?? '');
        $sched = trim($job['schedule'] ?? '');
        $isactive = $job['active'] ?? 1;
        $tip = _SCHEDULER_JOBKEY.': '.$name;
        $tip .= '<br>'._TYPE.': '.$type;
        $tip .= '<br>'._STATUS.': '.$stat;
        if ($sched !== '') $tip .= '<br>'._SCHEDULER_SCHED.': '.$sched;
        $tip .= '<br>'._SCHEDULER_LASTRUN.': '.$last;
        $tip .= '<br>'._SCHEDULER_LAST_OK.': '.$lastok;
        $tip .= '<br>'._SCHEDULER_NEXTRUN.': '.$nextr;
        $tip .= '<br>'._SCHEDULER_TRIGGER.': '.($trig !== '' ? $trig : _NO);
        $tip .= '<br>'._SCHEDULER_RUNTIME.': '.$time;
        $tip .= '<br>'._SCHEDULER_FAILS.': '.$fail;
        if ($note !== '') $tip .= '<br>'._DESCRIPTION.': '.$note;
        $title = $job['title'];
        $aops = ['run' => _SCHEDULER_RUN, 'unlock' => _SCHEDULER_UNLOCK];
        if (($job['type'] ?? '') === 'custom') $aops['del'] = _DELETE;
        $aforms = '';
        $amenu = '<a href="'.$afile.'.php?name=scheduler&amp;op=add&amp;job='.$name.'" title="'._EDIT.'">'._EDIT.'</a>';
        foreach ($aops as $aop => $alabel) {
            $aid = 'sch'.$aop.$name;
            $aforms .= '<form action="'.$afile.'.php" method="post" id="'.$aid.'" class="sl_none">'.getAdminHidden('name', 'scheduler').getAdminHidden('op', $aop).getAdminHidden('job', $name).getAdminHidden('token', getSiteToken('scheduler')).'</form>';
            $amenu .= '||<a href="#" OnClick="document.getElementById(\''.$aid.'\').submit(); return false;" title="'.$alabel.'">'.$alabel.'</a>';
        }
        $acts = [];
        foreach (explode('||', $amenu) as $item) {
            if ($item !== '') $acts[] = $item;
        }
        $cols = '<td>'.adminTitleTipLabel($tip, $title, cutstr($title, 22)).'</td>'
        .'<td>'.htmlspecialchars($nextr, ENT_QUOTES, 'UTF-8').'</td>'
        .'<td>'.$stat.'</td>'
        .'<td>'.htmlspecialchars($job['priority'] ?? '100', ENT_QUOTES, 'UTF-8').'</td>'
        .'<td>'.ad_status('', (int)$isactive).'</td>'
        .'<td>'.$aforms.adminMenuItems($acts).'</td>';
        $rows .= getAdminTableRow($cols);
    }
    $cont .= getAdminTable($head, $rows);
    setHead();
    echo $navi.$cont;
    setFoot();
}

function add(string $name = ''): void {
    global $conf, $afile, $tpl;
    if ($name === '') $name = getVar('get', 'job', 'var', '');
    $name = preg_replace('#[^a-z]#', '', strtolower($name));
    $jobs = getSchedulerJobs();
    $job = ($name !== '' && isset($jobs[$name])) ? $jobs[$name] : [
        'name' => '', 'title' => '', 'type' => 'custom', 'active' => '1',
        'system' => '', 'schedule' => '*/5 * * * *', 'priority' => '100',
        'lock_timeout' => (string)($conf['scheduler']['lock_timeout'] ?? '1800'),
        'manual' => '1', 'settings' => ['url' => ''],
    ];
    $isnew = ($name === '');
    $iscustom = (($job['type'] ?? 'custom') === 'custom');
    $key = $isnew ? '' : $job['name'];
    $url = $job['settings']['url'] ?? '';
    $schedule = (string)($job['schedule'] ?? '');
    $info = $iscustom ? _SCHEDULER_URLINFO : _SCHEDULER_SYSINFO;
    $readonly = $isnew ? '' : ' readonly';
    $cont = checkPerms(CONFIG_DIR.'/scheduler.php');
    $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => $info]);
    $hide = getAdminHidden('name', 'scheduler').getAdminHidden('op', 'save').getAdminHidden('token', getSiteToken('scheduler'));
    $rows = '';
    $rows .= getAdminFormRow(_SCHEDULER_JOBKEY.':', getAdminTextInput('job', $key, 'sl_form', 'maxlength="32"'.$readonly.' required'));
    $rows .= getAdminFormRow(_TITLE.':', getAdminTextInput('title', (string)$job['title'], 'sl_form', 'maxlength="100" required'));
    $rows .= getAdminFormRow(_TYPE.':', getAdminTextInput('', (($job['type'] ?? '') === 'custom') ? _SCHEDULER_CUSTOM : _SCHEDULER_SYSTEM, 'sl_form', 'disabled').getAdminHidden('type', (string)$job['type']));
    if ($iscustom) {
        $rows .= getAdminFormRow(_SCHEDULER_URL.':', getAdminTextInput('url', $url, 'sl_form', 'maxlength="255" placeholder="https://example.com/task" required'));
    } else {
        $rows .= getAdminFormRow(_SCHEDULER_SYSTEM.':', getAdminTextInput('', (string)($job['system'] ?? ''), 'sl_form', 'disabled'));
    }
    $rows .= getAdminFormRow(getAdminHintLabel(_SCHEDULER_SCHED, _SCHEDULER_CRONFMT), getAdminTextInput('schedule', $schedule, 'sl_form', 'maxlength="100" placeholder="0 2 * * *" required'));
    $rows .= getAdminFormRow(getAdminHintLabel(_SCHEDULER_PRIO, _SCHEDULER_PRIOTIP), getAdminNumberInput('priority', (string)$job['priority'], 'sl_form', 'min="1" max="999" required'));
    $rows .= getAdminFormRow(_SCHEDULER_LOCK.':', getAdminNumberInput('lock_timeout', (string)$job['lock_timeout'], 'sl_form', 'min="60" required'));
    $rows .= getAdminFormRow(_ACTIVATE2, radio_form((int)$job['active'], 'active'));
    $rows .= getAdminFormRow(_SCHEDULER_MANUAL.':', radio_form((int)$job['manual'], 'manual'));
    $rows .= getAdminFormWide('<input type="submit" value="'._SAVE.'" class="sl_but_blue">', '', 'sl_center');
    $cont .= getAdminForm($afile.'.php', $rows, $hide);
    setHead();
    $navi = setAdminNavi(['ops' => ['name=scheduler', 'name=scheduler&amp;op=add', 'name=scheduler&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO], 'tab' => 1]);
    echo $navi.$cont;
    setFoot();
}

function save(): void {
    global $conf, $afile;
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'scheduler')) { setRedirect($afile.'.php?name=scheduler'); return; }
    $rawname = getVar('post', 'job', 'var', '');
    $name = preg_replace('#[^a-z]#', '', strtolower($rawname));
    $type = getVar('post', 'type', 'var', 'custom');
    $title = trim(getVar('post', 'title', 'text', ''));
    $url = trim(getVar('post', 'url', 'url', ''));
    $sched = trim(getVar('post', 'schedule', 'raw', ''));
    $jobs = $conf['scheduler']['jobs'] ?? [];
    $curr = $jobs[$name] ?? [];
    $issys = isset($curr['type']) && $curr['type'] === 'system';
    if ($name === '' || $title === '' || (!$issys && ($type !== 'custom' || $url === ''))) { setRedirect($afile.'.php?name=scheduler&op=add&job='.$name); return; }
    $sched = getSchedulerSchedule($sched);
    if ($sched === '') { setRedirect($afile.'.php?name=scheduler&op=add&job='.$name); return; }
    $priority = (int)getVar('post', 'priority', 'num', 100);
    foreach ($jobs as $jkey => $jval) {
        if ($jkey !== $name && (int)($jval['priority'] ?? 100) === $priority) { setRedirect($afile.'.php?name=scheduler&op=add&job='.$name); return; }
    }
    $item = [
        'title' => $title,
        'type' => $issys ? 'system' : 'custom',
        'active' => getVar('post', 'active', 'num', 0),
        'system' => $issys ? ($curr['system'] ?? '') : '',
        'schedule' => $sched,
        'priority' => $priority,
        'lock_timeout' => getVar('post', 'lock_timeout', 'num', 1800),
        'manual' => getVar('post', 'manual', 'num', 1),
        'settings' => $issys ? ((isset($curr['settings']) && is_array($curr['settings'])) ? $curr['settings'] : []) : ['url' => $url],
    ];
    $data = $conf['scheduler'];
    $data['jobs'][$name] = $item;
    ksort($data['jobs']);
    setConfigFile('scheduler.php', $data);
    setRedirect($afile.'.php?name=scheduler&op=add&job='.$name);
}

function run(): void {
    global $afile;
    $name = preg_replace('#[^a-z]#', '', strtolower(getVar('post', 'job', 'var', '')));
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'scheduler')) { setRedirect($afile.'.php?name=scheduler'); return; }
    $jobs = getSchedulerJobs();
    if ($name === '' || !isset($jobs[$name]) || (int)($jobs[$name]['manual'] ?? 0) !== 1) { setRedirect($afile.'.php?name=scheduler'); return; }
    addSchedulerRun($name, 'manual');
    setRedirect($afile.'.php?name=scheduler');
}

function unlock(): void {
    global $afile;
    $name = preg_replace('#[^a-z]#', '', strtolower(getVar('post', 'job', 'var', '')));
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'scheduler')) { setRedirect($afile.'.php?name=scheduler'); return; }
    if ($name !== '') {
        $state = getSchedulerState($name);
        $state['running'] = 0; $state['started_at'] = 0;
        $state['last_status'] = 'idle'; $state['last_message'] = _SCHEDULER_UNLOCKD;
        setSchedulerState($name, $state);
    }
    setRedirect($afile.'.php?name=scheduler');
}

function delete(): void {
    global $conf, $afile;
    $name = preg_replace('#[^a-z]#', '', strtolower(getVar('post', 'job', 'var', '')));
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'scheduler')) { setRedirect($afile.'.php?name=scheduler'); return; }
    $jobs = $conf['scheduler']['jobs'] ?? [];
    if ($name !== '' && isset($jobs[$name]) && (($jobs[$name]['type'] ?? '') === 'custom')) {
        unset($jobs[$name]);
        $data = $conf['scheduler'];
        $data['jobs'] = $jobs;
        setConfigFile('scheduler.php', $data);
    }
    setRedirect($afile.'.php?name=scheduler');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=scheduler', 'name=scheduler&amp;op=add', 'name=scheduler&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO], 'tab' => 2]);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
}

switch ($op) {
    default: scheduler(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'run': run(); break;
    case 'unlock': unlock(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}

