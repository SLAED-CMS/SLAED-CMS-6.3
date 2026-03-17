<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function navi(int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=scheduler', 'name=scheduler&amp;op=add', 'name=scheduler&amp;op=info'];
    $lang = [_HOME, _ADD, _INFO];
    return getAdminTabs('', $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function scheduler(): void {
    global $afile, $conf;
    $jobs = getSchedulerJobs();
    $cont = navi(0, 0, 0);
    $seclink = ' <a href="'.$afile.'.php?name=security&amp;op=conf">'.htmlspecialchars(_SCHEDULER_WARN_GO, ENT_QUOTES, 'UTF-8').'</a>.';
    $cont .= (!$conf['security']['log_b']) ? setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _SCHEDULER_WARN_DB.$seclink]) : '';
    $cont .= (!$conf['security']['log_d']) ? setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _SCHEDULER_WARNLOG.$seclink]) : '';
    $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._TITLE.'</th><th>'._SCHEDULER_NEXTRUN.'</th><th>'._SCHEDULER_RESULT.'</th><th>'._SCHEDULER_PRIO.'</th><th>'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
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
        $tok = htmlspecialchars(getSiteToken('scheduler'), ENT_QUOTES, 'UTF-8');
        $esc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $aops = ['run' => _SCHEDULER_RUN, 'unlock' => _SCHEDULER_UNLOCK];
        if (($job['type'] ?? '') === 'custom') $aops['del'] = _DELETE;
        $aforms = '';
        $amenu = '<a href="'.$afile.'.php?name=scheduler&amp;op=add&amp;job='.$name.'" title="'._EDIT.'">'._EDIT.'</a>';
        foreach ($aops as $aop => $alabel) {
            $aid = 'sch'.$aop.$name;
            $aforms .= '<form action="'.$afile.'.php" method="post" id="'.$aid.'" class="sl_none"><input type="hidden" name="name" value="scheduler"><input type="hidden" name="op" value="'.$aop.'"><input type="hidden" name="job" value="'.$esc.'"><input type="hidden" name="token" value="'.$tok.'"></form>';
            $amenu .= '||<a href="#" OnClick="document.getElementById(\''.$aid.'\').submit(); return false;" title="'.$alabel.'">'.$alabel.'</a>';
        }
        $cont .= '<tr>'
        .'<td>'.title_tip($tip).'<span title="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'" class="sl_note">'.htmlspecialchars(cutstr($title, 22), ENT_QUOTES, 'UTF-8').'</span></td>'
        .'<td>'.htmlspecialchars($nextr, ENT_QUOTES, 'UTF-8').'</td>'
        .'<td>'.$stat.'</td>'
        .'<td>'.htmlspecialchars($job['priority'] ?? '100', ENT_QUOTES, 'UTF-8').'</td>'
        .'<td>'.ad_status('', (int)$isactive).'</td>'
        .'<td>'.$aforms.add_menu($amenu).'</td></tr>';
    }
    $cont .= '</tbody></table>';
    setHead();
    echo setTemplateBasic('open').$cont.setTemplateBasic('close');
    setFoot();
}

function add(string $name = ''): void {
    global $conf, $afile;
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
    $schedule = htmlspecialchars((string)($job['schedule'] ?? ''), ENT_QUOTES, 'UTF-8');
    $info = $iscustom ? _SCHEDULER_URLINFO : _SCHEDULER_SYSINFO;
    $readonly = $isnew ? '' : ' readonly';
    $cont = checkPerms(CONFIG_DIR.'/scheduler.php');
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $info]);
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._SCHEDULER_JOBKEY.':</td><td><input type="text" name="job" value="'.htmlspecialchars($key, ENT_QUOTES, 'UTF-8').'" maxlength="32" class="sl_form"'.$readonly.' required></td></tr>'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.htmlspecialchars((string)$job['title'], ENT_QUOTES, 'UTF-8').'" maxlength="100" class="sl_form" required></td></tr>'
    .'<tr><td>'._TYPE.':</td><td><input type="text" value="'.htmlspecialchars((($job['type'] ?? '') === 'custom') ? _SCHEDULER_CUSTOM : _SCHEDULER_SYSTEM, ENT_QUOTES, 'UTF-8').'" class="sl_form" disabled><input type="hidden" name="type" value="'.htmlspecialchars((string)$job['type'], ENT_QUOTES, 'UTF-8').'"></td></tr>';
    if ($iscustom) {
        $cont .= '<tr><td>'._SCHEDULER_URL.':</td><td><input type="text" name="url" value="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" maxlength="255" class="sl_form" placeholder="https://example.com/task" required></td></tr>';
    } else {
        $cont .= '<tr><td>'._SCHEDULER_SYSTEM.':</td><td><input type="text" value="'.htmlspecialchars((string)($job['system'] ?? ''), ENT_QUOTES, 'UTF-8').'" class="sl_form" disabled></td></tr>';
    }
    $cont .= '<tr><td>'._SCHEDULER_SCHED.':<div class="sl_small">'._SCHEDULER_CRONFMT.'</div></td><td><input type="text" name="schedule" value="'.$schedule.'" maxlength="100" class="sl_form" placeholder="0 2 * * *" required></td></tr>'
    .'<tr><td>'._SCHEDULER_PRIO.':<div class="sl_small">'._SCHEDULER_PRIOTIP.'</div></td><td><input type="number" name="priority" value="'.htmlspecialchars((string)$job['priority'], ENT_QUOTES, 'UTF-8').'" class="sl_form" min="1" max="999" required></td></tr>'
    .'<tr><td>'._SCHEDULER_LOCK.':</td><td><input type="number" name="lock_timeout" value="'.htmlspecialchars((string)$job['lock_timeout'], ENT_QUOTES, 'UTF-8').'" class="sl_form" min="60" required></td></tr>'
    .'<tr><td>'._ACTIVATE2.'</td><td>'.radio_form((int)$job['active'], 'active').'</td></tr>'
    .'<tr><td>'._SCHEDULER_MANUAL.':</td><td>'.radio_form((int)$job['manual'], 'manual').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="scheduler"><input type="hidden" name="op" value="save"><input type="hidden" name="token" value="'.htmlspecialchars(getSiteToken('scheduler'), ENT_QUOTES, 'UTF-8').'"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    setHead();
    echo navi(1, 0, 0).setTemplateBasic('open').$cont.setTemplateBasic('close');
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

function del(): void {
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
    echo navi(2, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: scheduler(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'run': run(); break;
    case 'unlock': unlock(); break;
    case 'del': del(); break;
    case 'info': info(); break;
}
