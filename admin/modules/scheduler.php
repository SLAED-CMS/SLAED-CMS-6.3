<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function scheduler(): void {
    global $afile, $conf, $tpl;
    $jobs = getSchedulerJobs();
    $cont = getTplAdminTabs(['ops' => ['name=scheduler', 'name=scheduler&op=add', 'name=scheduler&op=info'], 'tabs' => [_HOME, _ADD, _DOCS]]);
    $wargo = $tpl->getHtmlFrag('link', [
        'href' => $afile.'.php?name=security&op=config',
        'label' => _SCHEDULER_WARN_GO,
        'title' => _SCHEDULER_WARN_GO,
    ]);
    if (!$conf['security']['log_b']) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _SCHEDULER_WARN_DB.' '.$wargo.'.']);
    if (!$conf['security']['log_d']) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _SCHEDULER_WARNLOG.' '.$wargo.'.']);
    $head = [
        ['content' => _TITLE],
        ['content' => _SCHEDULER_NEXTRUN, 'is_col_date' => true],
        ['content' => _SCHEDULER_RESULT, 'is_col_status' => true],
        ['content' => _SCHEDULER_PRIO, 'is_col_count' => true],
        ['content' => _STATUS, 'is_col_status' => true],
        ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
    ];
    $rows = '';
    foreach ($jobs as $job) {
        $name = $job['name'];
        $state = getSchedulerState($name);
        $next = getSchedulerPlannedTime($job, $state);
        $type = (($job['type'] ?? '') === 'custom') ? _SCHEDULER_CUSTOM : _SCHEDULER_SYSTEM;
        $held = checkSchedulerLock($name);
        $stat = match (true) {
            $held => _SCHEDULER_RUNNING,
            !empty($state['running']) => _SCHEDULER_CRASH,
            default => match ($state['last_status'] ?? 'idle') {
                'success' => _YES,
                'failed' => _NO,
                'crashed' => _SCHEDULER_CRASH,
                'disabled' => _SCHEDULER_OFF,
                default => _SCHEDULER_IDLE,
            },
        };
        $run = (int)($state['last_run'] ?? 0);
        $ok = (int)($state['last_success'] ?? 0);
        $last = $run > 0 ? date(_TIMESTRING, $run) : _NO;
        $lastok = $ok > 0 ? date(_TIMESTRING, $ok) : _NO;
        $nextr = ($next > 0) ? date(_TIMESTRING, $next) : _NO;
        $trig = $state['last_trigger'] ?? _NO;
        $start = (int)($state['started_at'] ?? 0);
        $time = ($held && $start > 0) ? (time() - $start) : ($state['last_duration'] ?? 0);
        $fail = $state['fail_count'] ?? 0;
        $note = trim($state['last_message'] ?? '');
        if ($note === '') $note = trim($state['last_error'] ?? '');
        $sched = trim($job['schedule'] ?? '');
        $isactive = $job['active'] ?? 1;
        $tips = [
            ['label' => _SCHEDULER_JOBKEY, 'value' => $name, 'is_last' => false],
            ['label' => _TYPE, 'value' => $type, 'is_last' => false],
            ['label' => _STATUS, 'value' => $stat, 'is_last' => false],
        ];
        if ($sched !== '') $tips[] = ['label' => _SCHEDULER_SCHED, 'value' => $sched, 'is_last' => false];
        $tips[] = ['label' => _SCHEDULER_LASTRUN, 'value' => $last, 'is_last' => false];
        $tips[] = ['label' => _SCHEDULER_LAST_OK, 'value' => $lastok, 'is_last' => false];
        $tips[] = ['label' => _SCHEDULER_NEXTRUN, 'value' => $nextr, 'is_last' => false];
        $tips[] = ['label' => _SCHEDULER_TRIGGER, 'value' => ($trig !== '' ? $trig : _NO), 'is_last' => false];
        $tips[] = ['label' => _SCHEDULER_RUNTIME, 'value' => (string)$time, 'is_last' => false];
        $tips[] = ['label' => _SCHEDULER_FAILS, 'value' => (string)$fail, 'is_last' => $note === ''];
        if ($note !== '') $tips[] = ['label' => _DESCRIPTION, 'value' => $note, 'is_last' => true];
        $title = $job['title'];
        $acts = [[
            'href' => $afile.'.php?name=scheduler&op=add&job='.$name,
            'icon_name' => 'pencil',
            'title' => _EDIT,
        ]];
        $keys = ['name' => 'scheduler', 'job' => $name];
        if ((int)($job['manual'] ?? 0) === 1) $acts[] = getTplPostAction(['op' => 'run'] + $keys, 'play-fill', _SCHEDULER_RUN);
        $acts[] = getTplPostAction(['op' => 'unlock'] + $keys, 'unlock', _SCHEDULER_UNLOCK);
        if (($job['type'] ?? '') === 'custom') $acts[] = getTplPostAction(['op' => 'delete'] + $keys, 'trash', _DELETE, _DELETE.' "'.(string)$title.'"?');
        $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['is_truncate' => true, 'title_text' => (string)$title, 'has_content_text' => true, 'content_text' => (string)$title,
                    'prefix_html' => $tpl->getHtmlFrag('popover', ['items' => $tips])],
                ['is_col_date' => true, 'has_content_text' => true, 'content_text' => (string)$nextr],
                ['is_col_status' => true, 'has_content_text' => true, 'content_text' => (string)$stat],
                ['is_col_count' => true, 'has_content_text' => true, 'content_text' => (string)($job['priority'] ?? '100')],
                ['is_col_status' => true, 'content_html' => ((int)$isactive === 1) ? _YES : _NO],
                ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _EDITOR, 'dial' => $acts])],
            ],
        ])]);
    }
    $head[0]['is_truncate'] = true;
    $cont .= $tpl->getHtmlFrag('table', ['is_fixed' => true, 'head' => $head, 'rows_html' => $rows]);
    setHead();
    echo $cont;
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
    $cont = getTplAdminTabs(['ops' => ['name=scheduler', 'name=scheduler&op=add', 'name=scheduler&op=info'], 'tabs' => [_HOME, _ADD, _DOCS], 'tab' => 1]);
    $cont .= checkPerms(CONFIG_DIR.'/scheduler.php');
    $cont .= $tpl->getHtmlFrag('alert', ['text' => $info]);
    $rows = [[
        'label_for' => 'f-job',
        'label_html' => _SCHEDULER_JOBKEY,
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'name_attr' => 'job',
            'input_id' => 'f-job',
            'value_attr' => (string)$key,
            'maxlength_num' => 32,
            'is_required' => true,
            'is_config' => true,
            'input_attr' => $readonly,
        ]),
    ], [
        'label_for' => 'f-title',
        'label_html' => _TITLE,
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'name_attr' => 'title',
            'input_id' => 'f-title',
            'value_attr' => (string)$job['title'],
            'maxlength_num' => 100,
            'is_required' => true,
            'is_config' => true,
        ]),
    ], [
        'label_html' => _TYPE,
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'value_attr' => (($job['type'] ?? '') === 'custom') ? _SCHEDULER_CUSTOM : _SCHEDULER_SYSTEM,
            'is_config' => true,
            'input_attr' => 'disabled',
        ]),
    ]];
    if ($iscustom) {
        $rows[] = [
            'label_for' => 'f-url',
            'label_html' => _SCHEDULER_URL,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'url',
                'name_attr' => 'url',
                'input_id' => 'f-url',
                'value_attr' => $url,
                'maxlength_num' => 255,
                'placeholder_text' => 'https://example.com/task',
                'is_required' => true,
                'is_config' => true,
            ]),
        ];
    } else {
        $rows[] = [
            'label_html' => _SCHEDULER_SYSTEM,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'value_attr' => (string)($job['system'] ?? ''),
                'is_config' => true,
                'input_attr' => 'disabled',
            ]),
        ];
    }
    $rows[] = [
        'label_for' => 'f-schedule',
        'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _SCHEDULER_SCHED, 'hint' => _SCHEDULER_CRONFMT]),
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'name_attr' => 'schedule',
            'input_id' => 'f-schedule',
            'value_attr' => $schedule,
            'maxlength_num' => 100,
            'placeholder_text' => '0 2 * * *',
            'is_required' => true,
            'is_config' => true,
        ]),
    ];
    $rows[] = [
        'label_for' => 'f-priority',
        'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _SCHEDULER_PRIO, 'hint' => _SCHEDULER_PRIOTIP]),
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'number',
            'name_attr' => 'priority',
            'input_id' => 'f-priority',
            'value_attr' => (string)$job['priority'],
            'is_required' => true,
            'is_config' => true,
            'input_attr' => 'min="1" max="999"',
        ]),
    ];
    $rows[] = [
        'label_for' => 'f-lock-timeout',
        'label_html' => _SCHEDULER_LOCK,
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'number',
            'name_attr' => 'lock_timeout',
            'input_id' => 'f-lock-timeout',
            'value_attr' => (string)$job['lock_timeout'],
            'is_required' => true,
            'is_config' => true,
            'input_attr' => 'min="60"',
        ]),
    ];
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows[] = ['label_html' => _ACTIVATE2, 'field_html' => getTplRadioGroup(['name' => 'active', 'value' => (string)(int)$job['active'], 'options' => $yesno])];
    $rows[] = ['label_html' => _SCHEDULER_MANUAL, 'field_html' => getTplRadioGroup(['name' => 'manual', 'value' => (string)(int)$job['manual'], 'options' => $yesno])];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'scheduler'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'type', 'valueattr' => (string)$job['type']],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('scheduler')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVE,
    ])]);
    setHead();
    echo $cont;
    setFoot();
}

# Stores one job, self-healing every other job on the way, so legacy keys and drifted system handlers cannot survive a save
function save(): void {
    global $conf, $afile;
    $rawname = getVar('post', 'job', 'var', '');
    $name = preg_replace('#[^a-z]#', '', strtolower($rawname));
    $warn = !checkAdminPost('scheduler');
    if (!$warn) {
        $type = getVar('post', 'type', 'var', 'custom');
        $title = trim(getVar('post', 'title', 'text', ''));
        $url = trim(getVar('post', 'url', 'url', ''));
        $sched = trim(getVar('post', 'schedule', 'raw', ''));
        $cfg = is_readable(CONFIG_DIR.'/scheduler.php') ? (require CONFIG_DIR.'/scheduler.php') : [];
        $schedcfg = is_array($cfg) && isset($cfg['scheduler']) && is_array($cfg['scheduler']) ? $cfg['scheduler'] : ($conf['scheduler'] ?? []);
        $jobs = $schedcfg['jobs'] ?? [];
        $curr = $jobs[$name] ?? [];
        $issys = (getSchedulerJob($name, $curr)['type'] ?? '') === 'system';
        if ($name === '' || $title === '' || (!$issys && ($type !== 'custom' || $url === ''))) {
            setRedirect($afile.'.php?name=scheduler&op=add&job='.$name);
            return;
        }
        $sched = getSchedulerSchedule($sched);
        if ($sched === '') {
            setRedirect($afile.'.php?name=scheduler&op=add&job='.$name);
            return;
        }
        $priority = (int)getVar('post', 'priority', 'num', 100);
        foreach ($jobs as $jkey => $jval) {
            if ($jkey !== $name && (int)($jval['priority'] ?? 100) === $priority) {
                setRedirect($afile.'.php?name=scheduler&op=add&job='.$name);
                return;
            }
        }
        $item = [
            'title' => $title,
            'type' => $issys ? 'system' : 'custom',
            'active' => getVar('post', 'active', 'num', 0),
            'system' => $issys ? (string)($curr['system'] ?? '') : '',
            'schedule' => $sched,
            'priority' => $priority,
            'lock_timeout' => getVar('post', 'lock_timeout', 'num', 1800),
            'manual' => getVar('post', 'manual', 'num', 1),
            'settings' => $issys ? ((isset($curr['settings']) && is_array($curr['settings'])) ? $curr['settings'] : []) : ['url' => $url],
        ];
        $data = $schedcfg;
        $data['jobs'][$name] = $item;
        foreach ($data['jobs'] as $jkey => $jval) {
            if (is_array($jval)) $data['jobs'][$jkey] = getSchedulerJob((string)$jkey, $jval);
        }
        ksort($data['jobs']);
        setConfigFile('scheduler.php', $data);
    }
    setRedirect($afile.'.php?name=scheduler&op=add&job='.$name, false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function run(): void {
    global $afile;
    $warn = !checkAdminPost('scheduler');
    $name = preg_replace('#[^a-z]#', '', strtolower(getVar('post', 'job', 'var', '')));
    $text = $warn ? _TOKENMISS : _SUCCSAVE;
    if (!$warn) {
        $jobs = getSchedulerJobs();
        if ($name === '' || !isset($jobs[$name]) || (int)($jobs[$name]['manual'] ?? 0) !== 1) {
            setRedirect($afile.'.php?name=scheduler');
            return;
        }
        $result = addSchedulerRun($name, 'manual');
        if (($result['status'] ?? '') !== 'success') {
            $warn = true;
            $mess = trim((string)($result['message'] ?? ''));
            $text = ($mess !== '') ? $mess : (string)($result['status'] ?? '');
        }
    }
    setRedirect($afile.'.php?name=scheduler', false, 302, $text, $warn);
}

# Clear a crashed run, but only report success once the repair is stored: a reconciliation nobody could write leaves the job running while the operator is told it was cleared
function unlock(): void {
    global $afile;
    $warn = !checkAdminPost('scheduler');
    $name = preg_replace('#[^a-z]#', '', strtolower(getVar('post', 'job', 'var', '')));
    $text = $warn ? _TOKENMISS : _SUCCSAVE;
    if (!$warn && $name !== '') {
        $lock = getSchedulerLockHandle($name);
        if ($lock === false) {
            $warn = true;
            $text = _SCHEDULER_RUNNING;
        } else {
            $state = getSchedulerState($name);
            $done = empty($state['running']) || updateSchedulerCrash($name, $state) !== null;
            deleteSchedulerHandle($lock);
            $warn = !$done;
            $text = $done ? _SCHEDULER_UNLOCKD : _ERROR.': '.LOGS_DIR.'/scheduler';
        }
    }
    setRedirect($afile.'.php?name=scheduler', false, 302, $text, $warn);
}

function delete(): void {
    global $conf, $afile;
    $warn = !checkAdminPost('scheduler');
    $name = preg_replace('#[^a-z]#', '', strtolower(getVar('post', 'job', 'var', '')));
    if (!$warn) {
        $cfg = is_readable(CONFIG_DIR.'/scheduler.php') ? (require CONFIG_DIR.'/scheduler.php') : [];
        $schedcfg = is_array($cfg) && isset($cfg['scheduler']) && is_array($cfg['scheduler']) ? $cfg['scheduler'] : ($conf['scheduler'] ?? []);
        $jobs = $schedcfg['jobs'] ?? [];
        if ($name !== '' && isset($jobs[$name]) && (($jobs[$name]['type'] ?? '') === 'custom')) {
            unset($jobs[$name]);
            $data = $schedcfg;
            $data['jobs'] = $jobs;
            setConfigFile('scheduler.php', $data);
        }
    }
    setRedirect($afile.'.php?name=scheduler', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=scheduler', 'name=scheduler&op=add', 'name=scheduler&op=info'],
        'tabs' => [_HOME, _ADD, _DOCS],
    ]);
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
