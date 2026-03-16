<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=scheduler', 'name=scheduler&amp;op=add', 'name=scheduler&amp;op=info'];
    $lang = [_HOME, _ADD, _INFO];
    return getAdminTabs('', $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function token(): string {
    static $token = '';
    if ($token === '') $token = hash('sha256', session_id().'|scheduler-admin');
    return $token;
}

function istoken(): bool {
    return hash_equals(token(), getVar('post', 'token', 'raw', ''));
}

function jobkey(string $name): string {
    return preg_replace('#[^a-z]#', '', strtolower($name));
}

function actions(string $name, bool $del = false): string {
    global $afile;
    $edit = '<a href="'.$afile.'.php?name=scheduler&amp;op=add&amp;job='.$name.'" title="'._EDIT.'">'._EDIT.'</a>';
    $runf = 'schrun'.$name;
    $unlf = 'schunl'.$name;
    $delf = 'schdel'.$name;
    $menu = $edit
    .'||<a href="#" OnClick="document.getElementById(\''.$runf.'\').submit(); return false;" title="'._SCHEDULER_RUN.'">'._SCHEDULER_RUN.'</a>'
    .'||<a href="#" OnClick="document.getElementById(\''.$unlf.'\').submit(); return false;" title="'._SCHEDULER_UNLOCK.'">'._SCHEDULER_UNLOCK.'</a>';
    if ($del) $menu .= '||<a href="#" OnClick="document.getElementById(\''.$delf.'\').submit(); return false;" title="'._DELETE.'">'._DELETE.'</a>';
    $cont = '<form action="'.$afile.'.php" method="post" id="'.$runf.'" class="sl_none"><input type="hidden" name="name" value="scheduler"><input type="hidden" name="op" value="run"><input type="hidden" name="job" value="'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'"><input type="hidden" name="token" value="'.htmlspecialchars(token(), ENT_QUOTES, 'UTF-8').'"></form>'
    .'<form action="'.$afile.'.php" method="post" id="'.$unlf.'" class="sl_none"><input type="hidden" name="name" value="scheduler"><input type="hidden" name="op" value="unlock"><input type="hidden" name="job" value="'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'"><input type="hidden" name="token" value="'.htmlspecialchars(token(), ENT_QUOTES, 'UTF-8').'"></form>';
    if ($del) $cont .= '<form action="'.$afile.'.php" method="post" id="'.$delf.'" class="sl_none"><input type="hidden" name="name" value="scheduler"><input type="hidden" name="op" value="del"><input type="hidden" name="job" value="'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'"><input type="hidden" name="token" value="'.htmlspecialchars(token(), ENT_QUOTES, 'UTF-8').'"></form>';
    return $cont.add_menu($menu);
}

function formpanel(string $name = ''): string {
    global $conf, $afile;
    $name = jobkey($name);
    $jobs = getSchedulerJobs();
    $job = ($name !== '' && isset($jobs[$name])) ? $jobs[$name] : [
        'name' => '',
        'title' => '',
        'type' => 'custom',
        'active' => '1',
        'handler' => 'custom',
        'schedule' => '*/5 * * * *',
        'priority' => '100',
        'lock_timeout' => (string)($conf['scheduler']['lock_timeout'] ?? '1800'),
        'manual' => '1',
        'settings' => ['url' => ''],
    ];
    $isnew = ($name === '');
    $iscustom = (($job['type'] ?? 'custom') === 'custom');
    $key = $isnew ? '' : (string)$job['name'];
    $url = (string)($job['settings']['url'] ?? '');
    $schedule = htmlspecialchars((string)($job['schedule'] ?? ''), ENT_QUOTES, 'UTF-8');
    $info = $iscustom ? _SCHEDULER_URL_INFO : _SCHEDULER_SYSTEM_INFO;
    $readonly = $isnew ? '' : ' readonly';
    $cont = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $info]);
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._SCHEDULER_JOBKEY.':</td><td><input type="text" name="job" value="'.htmlspecialchars($key, ENT_QUOTES, 'UTF-8').'" maxlength="32" class="sl_form"'.$readonly.' required></td></tr>'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.htmlspecialchars((string)$job['title'], ENT_QUOTES, 'UTF-8').'" maxlength="100" class="sl_form" required></td></tr>'
    .'<tr><td>'._TYPE.':</td><td><input type="text" value="'.htmlspecialchars((($job['type'] ?? '') === 'custom') ? _SCHEDULER_CUSTOM : _SCHEDULER_SYSTEM, ENT_QUOTES, 'UTF-8').'" class="sl_form" disabled><input type="hidden" name="type" value="'.htmlspecialchars((string)$job['type'], ENT_QUOTES, 'UTF-8').'"></td></tr>';
    if ($iscustom) {
        $cont .= '<tr><td>'._SCHEDULER_URL.':</td><td><input type="text" name="url" value="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" maxlength="255" class="sl_form" placeholder="https://example.com/task" required></td></tr>';
    } else {
        $cont .= '<tr><td>'._SCHEDULER_HANDLER.':</td><td><input type="text" value="'.htmlspecialchars((string)$job['handler'], ENT_QUOTES, 'UTF-8').'" class="sl_form" disabled></td></tr>';
    }
    $cont .= '<tr><td>'._SCHEDULER_SCHEDULE.':<div class="sl_small">'._SCHEDULER_SCHEDULE_INFO.'</div></td><td><input type="text" name="schedule" value="'.$schedule.'" maxlength="100" class="sl_form" placeholder="0 2 * * *" required></td></tr>'
    .'<tr><td>'._SCHEDULER_PRIORITY.':</td><td><input type="number" name="priority" value="'.htmlspecialchars((string)$job['priority'], ENT_QUOTES, 'UTF-8').'" class="sl_form" min="1" max="999" required></td></tr>'
    .'<tr><td>'._SCHEDULER_LOCK.':</td><td><input type="number" name="lock_timeout" value="'.htmlspecialchars((string)$job['lock_timeout'], ENT_QUOTES, 'UTF-8').'" class="sl_form" min="60" required></td></tr>'
    .'<tr><td>'._STATUS.':</td><td>'.radio_form((int)$job['active'], 'active').'</td></tr>'
    .'<tr><td>'._SCHEDULER_MANUAL.':</td><td>'.radio_form((int)$job['manual'], 'manual').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="scheduler"><input type="hidden" name="op" value="save"><input type="hidden" name="token" value="'.htmlspecialchars(token(), ENT_QUOTES, 'UTF-8').'"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    if (!$isnew) {
        $cont .= '<table class="sl_table_form">'
        .'<tr><td colspan="2"><strong>'._FUNCTIONS.'</strong></td></tr>'
        .'<tr><td class="sl_center">'.actions($key, false).'</td></tr></table>';
    }
    return $cont;
}

function statuspanel(): string {
    $jobs = getSchedulerJobs();
    $cont = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _SCHEDULER_STATUS_INFO]);
    if ($jobs === []) return $cont.setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._TITLE.'</th><th>'._STATUS.'</th><th>'._SCHEDULER_NEXT_RUN.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
    foreach ($jobs as $job) {
        $name = (string)$job['name'];
        $state = getSchedulerState($name);
        $next = getSchedulerPlannedTime($job, $state);
        $type = (($job['type'] ?? '') === 'custom') ? _SCHEDULER_CUSTOM : _SCHEDULER_SYSTEM;
        $stat = match ((string)($state['last_status'] ?? 'idle')) {
            'success' => _YES,
            'failed' => _NO,
            'running' => _SCHEDULER_RUNNING,
            default => _SCHEDULER_IDLE,
        };
        $last = ((int)($state['last_run'] ?? 0) > 0) ? date(_TIMESTRING, (int)$state['last_run']) : _NO;
        $lastok = ((int)($state['last_success'] ?? 0) > 0) ? date(_TIMESTRING, (int)$state['last_success']) : _NO;
        $nextr = ($next > 0) ? date(_TIMESTRING, $next) : _NO;
        $trig = (string)($state['last_trigger'] ?? _NO);
        $time = (string)($state['last_duration'] ?? 0);
        $fail = (string)($state['fail_count'] ?? 0);
        $note = trim((string)($state['last_message'] ?? ''));
        if ($note === '') $note = trim((string)($state['last_error'] ?? ''));
        $sched = trim((string)($job['schedule'] ?? ''));
        $tip = _SCHEDULER_JOBKEY.': '.$name;
        $tip .= '<br>'._TYPE.': '.$type;
        $tip .= '<br>'._STATUS.': '.$stat;
        if ($sched !== '') $tip .= '<br>'._SCHEDULER_SCHEDULE.': '.$sched;
        $tip .= '<br>'._SCHEDULER_LAST_RUN.': '.$last;
        $tip .= '<br>'._SCHEDULER_LAST_OK.': '.$lastok;
        $tip .= '<br>'._SCHEDULER_NEXT_RUN.': '.$nextr;
        $tip .= '<br>'._SCHEDULER_TRIGGER.': '.($trig !== '' ? $trig : _NO);
        $tip .= '<br>'._SCHEDULER_DURATION.': '.$time;
        $tip .= '<br>'._SCHEDULER_FAILS.': '.$fail;
        if ($note !== '') $tip .= '<br>'._DESCRIPTION.': '.$note;
        $title = (string)$job['title'];
        $cont .= '<tr><td>'.title_tip($tip).'<span title="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'" class="sl_note">'.htmlspecialchars(cutstr($title, 22), ENT_QUOTES, 'UTF-8').'</span></td>'
        .'<td>'.$stat.'</td>'
        .'<td>'.htmlspecialchars($nextr, ENT_QUOTES, 'UTF-8').'</td>'
        .'<td>'.actions($name, (($job['type'] ?? '') === 'custom')).'</td></tr>';
    }
    return $cont.'</tbody></table>';
}

function page(string $message = '', string $job = '', int $tab = 0, string $code = ''): void {
    global $afile;
    $url = $afile.'.php?name=scheduler&amp;op=status';
    $stat = statuspanel();
    $form = formpanel($job);
    setHead();
    echo navi(0, $tab, 0, 0).setTemplateBasic('open')
    .'<script src="templates/admin/js/htmx.min.js"></script>'
    .$message
    .$code
    .'<div id="scheduler-status-panel" hx-get="'.$url.'" hx-trigger="every 5s" hx-swap="outerHTML">'.$stat.'</div>'
    .$form
    .setTemplateBasic('close');
    setFoot();
}

function runscript(string $name): string {
    $name = jobkey($name);
    if ($name === '') return '';
    $url = 'index.php?go=3&amp;op=scheduler&amp;job='.$name.'&amp;trigger=manual';
    return '<script>
window.addEventListener("load", function () {
    fetch("'.$url.'", { credentials: "same-origin" })
        .then(function () {
            if (window.htmx) htmx.ajax("GET", "admin.php?name=scheduler&op=status", { target: "#scheduler-status-panel", swap: "outerHTML" });
        })
        .catch(function () {});
});
</script>';
}

function scheduler(): void {
    page('', '', 0);
}

function addjob(): void {
    $job = getVar('get', 'job', 'var', '');
    page('', $job, 1);
}

function save(): void {
    global $conf;
    if (!istoken()) {
        page(setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _TOKENMISS]), '', 1);
        return;
    }
    $name = jobkey(getVar('post', 'job', 'var', ''));
    $type = getVar('post', 'type', 'var', 'custom');
    $title = trim((string)getVar('post', 'title', 'text', ''));
    $url = trim((string)getVar('post', 'url', 'url', ''));
    $sched = trim((string)getVar('post', 'schedule', 'raw', ''));
    $jobs = $conf['scheduler']['jobs'] ?? [];
    $curr = $jobs[$name] ?? [];
    $issys = isset($curr['type']) && $curr['type'] === 'system';
    if ($name === '' || $title === '') {
        page(setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _SCHEDULER_SAVEERR]), $name, 1);
        return;
    }
    if (!$issys && ($type !== 'custom' || $url === '')) {
        page(setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _SCHEDULER_SAVEERR]), $name, 1);
        return;
    }
    $sched = getSchedulerSchedule($sched);
    if ($sched === '') {
        page(setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _SCHEDULER_SAVEERR]), $name, 1);
        return;
    }
    $item = [
        'title' => $title,
        'type' => $issys ? 'system' : 'custom',
        'active' => (string)getVar('post', 'active', 'num', 0),
        'handler' => $issys ? (string)($curr['handler'] ?? '') : 'custom',
        'schedule' => $sched,
        'priority' => (string)getVar('post', 'priority', 'num', 100),
        'lock_timeout' => (string)getVar('post', 'lock_timeout', 'num', 1800),
        'manual' => (string)getVar('post', 'manual', 'num', 1),
        'settings' => $issys ? ((isset($curr['settings']) && is_array($curr['settings'])) ? $curr['settings'] : []) : ['url' => $url],
    ];
    $data = $conf['scheduler'];
    $data['jobs'][$name] = $item;
    ksort($data['jobs']);
    setConfigFile('scheduler.php', $data);
    page(setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _SCHEDULER_SAVED]), $name, 1);
}

function run(): void {
    $name = jobkey(getVar('post', 'job', 'var', ''));
    if (!istoken()) {
        page(setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _TOKENMISS]), '', 1);
        return;
    }
    $jobs = getSchedulerJobs();
    if ($name === '' || !isset($jobs[$name])) {
        page(setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _NO_INFO]), '', 0);
        return;
    }
    $job = $jobs[$name];
    if ((int)($job['manual'] ?? 0) !== 1) {
        page(setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _ACCESSDENIED]), '', 0);
        return;
    }
    $text = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _SCHEDULER_RUNNING.': '.htmlspecialchars((string)$job['title'], ENT_QUOTES, 'UTF-8')]);
    page($text, $name, 1, runscript($name));
}

function unlock(): void {
    $name = jobkey(getVar('post', 'job', 'var', ''));
    if (!istoken()) {
        page(setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _TOKENMISS]), '', 0);
        return;
    }
    if ($name !== '') {
        $state = getSchedulerState($name);
        $state['running'] = 0;
        $state['started_at'] = 0;
        $state['last_status'] = 'idle';
        $state['last_message'] = _SCHEDULER_UNLOCKED;
        setSchedulerState($name, $state);
    }
    page(setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _SCHEDULER_UNLOCKED]), $name, 1);
}

function del(): void {
    global $conf;
    $name = jobkey(getVar('post', 'job', 'var', ''));
    if (!istoken()) {
        page(setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _TOKENMISS]), '', 0);
        return;
    }
    $jobs = $conf['scheduler']['jobs'] ?? [];
    if ($name !== '' && isset($jobs[$name]) && (($jobs[$name]['type'] ?? '') === 'custom')) {
        unset($jobs[$name]);
        $data = $conf['scheduler'];
        $data['jobs'] = $jobs;
        setConfigFile('scheduler.php', $data);
    }
    page(setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _SCHEDULER_DELETED]), '', 0);
}

function status(): void {
    echo statuspanel();
}

function info(): void {
    setHead();
    echo navi(0, 2, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: scheduler(); break;
    case 'add': addjob(); break;
    case 'save': save(); break;
    case 'run': run(); break;
    case 'unlock': unlock(); break;
    case 'del': del(); break;
    case 'status': status(); break;
    case 'info': info(); break;
}
