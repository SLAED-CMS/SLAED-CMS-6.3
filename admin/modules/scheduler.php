<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function scheduler(): void {
    global $afile, $conf, $tpl;
    $jobs = getSchedulerJobs();
    $cont = getTplAdminTabs(['ops' => ['name=scheduler', 'name=scheduler&amp;op=add', 'name=scheduler&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO]]);
    $wargo = $tpl->getHtmlFrag('link', [
        'href' => $afile.'.php?name=security&amp;op=config',
        'label' => _SCHEDULER_WARN_GO,
        'title' => _SCHEDULER_WARN_GO,
    ]);
    if (!$conf['security']['log_b']) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _SCHEDULER_WARN_DB.' '.$wargo.'.']);
    if (!$conf['security']['log_d']) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _SCHEDULER_WARNLOG.' '.$wargo.'.']);
    $head = [
        ['content' => _TITLE],
        ['content' => _SCHEDULER_NEXTRUN],
        ['content' => _SCHEDULER_RESULT],
        ['content' => _SCHEDULER_PRIO],
        ['content' => _STATUS],
        ['content' => _FUNCTIONS, 'nosort' => true],
    ];
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
            'href' => $afile.'.php?name=scheduler&amp;op=add&amp;job='.$name,
            'label' => _EDIT,
            'title' => _EDIT,
        ]];
        if ((int)($job['manual'] ?? 0) === 1) {
            $acts[] = [
                'href' => $afile.'.php?name=scheduler&amp;op=run&amp;job='.$name.'&amp;token='.getSiteToken(),
                'label' => _SCHEDULER_RUN,
                'title' => _SCHEDULER_RUN,
            ];
        }
        $acts[] = [
            'href' => $afile.'.php?name=scheduler&amp;op=unlock&amp;job='.$name.'&amp;token='.getSiteToken(),
            'label' => _SCHEDULER_UNLOCK,
            'title' => _SCHEDULER_UNLOCK,
        ];
        if (($job['type'] ?? '') === 'custom') {
            $acts[] = [
                'href' => $afile.'.php?name=scheduler&amp;op=delete&amp;job='.$name.'&amp;token='.getSiteToken(),
                'label' => _DELETE,
                'title' => _DELETE,
                'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars((string)$title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"',
            ];
        }
        $label = $tpl->getHtmlFrag('title-tip', ['items' => $tips]).htmlspecialchars(cutstr((string)$title, 22), ENT_QUOTES, 'UTF-8');
        $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['content_html' => $label],
                ['content_html' => htmlspecialchars((string)$nextr, ENT_QUOTES, 'UTF-8')],
                ['content_html' => htmlspecialchars((string)$stat, ENT_QUOTES, 'UTF-8')],
                ['content_html' => htmlspecialchars((string)($job['priority'] ?? '100'), ENT_QUOTES, 'UTF-8')],
                ['content_html' => ((int)$isactive === 1) ? _YES : _NO],
                ['content_html' => $tpl->getHtmlFrag('row-actions', ['trigger_label' => _EDITOR, 'items' => $acts])],
            ],
        ])]);
    }
    $cont .= $tpl->getHtmlFrag('table', ['head' => $head, 'rows_html' => $rows]);
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
    $cont = getTplAdminTabs(['ops' => ['name=scheduler', 'name=scheduler&amp;op=add', 'name=scheduler&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO], 'tab' => 1]);
    $cont .= checkPerms(CONFIG_DIR.'/scheduler.php');
    $cont .= $tpl->getHtmlFrag('alert', ['text' => $info]);
    $rows = [[
        'label_html' => _SCHEDULER_JOBKEY.':',
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'name_attr' => 'job',
            'value_attr' => (string)$key,
            'maxlength_num' => 32,
            'is_required' => true,
            'is_config' => true,
            'input_attr' => $readonly,
        ]),
    ], [
        'label_html' => _TITLE.':',
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'name_attr' => 'title',
            'value_attr' => (string)$job['title'],
            'maxlength_num' => 100,
            'is_required' => true,
            'is_config' => true,
        ]),
    ], [
        'label_html' => _TYPE.':',
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'value_attr' => (($job['type'] ?? '') === 'custom') ? _SCHEDULER_CUSTOM : _SCHEDULER_SYSTEM,
            'is_config' => true,
            'input_attr' => 'disabled',
        ]),
    ]];
    if ($iscustom) {
        $rows[] = [
            'label_html' => _SCHEDULER_URL.':',
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'url',
                'name_attr' => 'url',
                'value_attr' => $url,
                'maxlength_num' => 255,
                'placeholder_text' => 'https://example.com/task',
                'is_required' => true,
                'is_config' => true,
            ]),
        ];
    } else {
        $rows[] = [
            'label_html' => _SCHEDULER_SYSTEM.':',
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'value_attr' => (string)($job['system'] ?? ''),
                'is_config' => true,
                'input_attr' => 'disabled',
            ]),
        ];
    }
    $rows[] = [
        'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _SCHEDULER_SCHED, 'hint' => _SCHEDULER_CRONFMT]),
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'name_attr' => 'schedule',
            'value_attr' => $schedule,
            'maxlength_num' => 100,
            'placeholder_text' => '0 2 * * *',
            'is_required' => true,
            'is_config' => true,
        ]),
    ];
    $rows[] = [
        'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _SCHEDULER_PRIO, 'hint' => _SCHEDULER_PRIOTIP]),
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'number',
            'name_attr' => 'priority',
            'value_attr' => (string)$job['priority'],
            'is_required' => true,
            'is_config' => true,
            'input_attr' => 'min="1" max="999"',
        ]),
    ];
    $rows[] = [
        'label_html' => _SCHEDULER_LOCK.':',
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'number',
            'name_attr' => 'lock_timeout',
            'value_attr' => (string)$job['lock_timeout'],
            'is_required' => true,
            'is_config' => true,
            'input_attr' => 'min="60"',
        ]),
    ];
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows[] = ['label_html' => _ACTIVATE2, 'field_html' => getTplRadioGroup(['name' => 'active', 'value' => (string)(int)$job['active'], 'options' => $yesno])];
    $rows[] = ['label_html' => _SCHEDULER_MANUAL.':', 'field_html' => getTplRadioGroup(['name' => 'manual', 'value' => (string)(int)$job['manual'], 'options' => $yesno])];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'scheduler'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'type', 'valueattr' => (string)$job['type']],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVE,
    ])]);
    setHead();
    echo $cont;
    setFoot();
}

function save(): void {
    global $conf, $afile;
    $rawname = getVar('post', 'job', 'var', '');
    $name = preg_replace('#[^a-z]#', '', strtolower($rawname));
    $warn = !checkSiteToken();
    if (!$warn) {
        $type = getVar('post', 'type', 'var', 'custom');
        $title = trim(getVar('post', 'title', 'text', ''));
        $url = trim(getVar('post', 'url', 'url', ''));
        $sched = trim(getVar('post', 'schedule', 'raw', ''));
        $cfg = is_readable(CONFIG_DIR.'/scheduler.php') ? (require CONFIG_DIR.'/scheduler.php') : [];
        $schedcfg = is_array($cfg) && isset($cfg['scheduler']) && is_array($cfg['scheduler']) ? $cfg['scheduler'] : ($conf['scheduler'] ?? []);
        $jobs = $schedcfg['jobs'] ?? [];
        $curr = $jobs[$name] ?? [];
        $issys = isset($curr['type']) && $curr['type'] === 'system';
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
            'system' => $issys ? ($curr['system'] ?? '') : '',
            'schedule' => $sched,
            'priority' => $priority,
            'lock_timeout' => getVar('post', 'lock_timeout', 'num', 1800),
            'manual' => getVar('post', 'manual', 'num', 1),
            'settings' => $issys ? ((isset($curr['settings']) && is_array($curr['settings'])) ? $curr['settings'] : []) : ['url' => $url],
        ];
        $data = $schedcfg;
        $data['jobs'][$name] = $item;
        ksort($data['jobs']);
        setConfigFile('scheduler.php', $data);
    }
    setRedirect($afile.'.php?name=scheduler&op=add&job='.$name, false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function run(): void {
    global $afile;
    $warn = !checkSiteToken();
    $name = preg_replace('#[^a-z]#', '', strtolower(getVar('req', 'job', 'var', '')));
    if (!$warn) {
        $jobs = getSchedulerJobs();
        if ($name === '' || !isset($jobs[$name]) || (int)($jobs[$name]['manual'] ?? 0) !== 1) {
            setRedirect($afile.'.php?name=scheduler');
            return;
        }
        addSchedulerRun($name, 'manual');
    }
    setRedirect($afile.'.php?name=scheduler', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function unlock(): void {
    global $afile;
    $warn = !checkSiteToken();
    $name = preg_replace('#[^a-z]#', '', strtolower(getVar('req', 'job', 'var', '')));
    if (!$warn && $name !== '') {
        $state = getSchedulerState($name);
        $state['running'] = 0; $state['started_at'] = 0;
        $state['last_status'] = 'idle'; $state['last_message'] = _SCHEDULER_UNLOCKD;
        setSchedulerState($name, $state);
    }
    setRedirect($afile.'.php?name=scheduler', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function delete(): void {
    global $conf, $afile;
    $warn = !checkSiteToken();
    $name = preg_replace('#[^a-z]#', '', strtolower(getVar('req', 'job', 'var', '')));
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
        'ops' => ['name=scheduler', 'name=scheduler&amp;op=add', 'name=scheduler&amp;op=info'],
        'tabs' => [_HOME, _ADD, _INFO],
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
