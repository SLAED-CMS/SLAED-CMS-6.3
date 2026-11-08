<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

# The largest limit of each system job whose settings carry one; the adapter of the job bounds the value the same way
const SCHED_LIMITS = ['nodepublish' => 500, 'nodesync' => 50];

function scheduler(): void {
    global $afile, $conf, $tpl;
    $jobs = getSchedulerJobs();
    $cont = '';
    $wargo = static fn(string $job): string => $tpl->getHtmlFrag('link', [
        'href' => $afile.'.php?name=scheduler&op=add&job='.$job,
        'label' => _SCHEDULER_WARN_GO,
        'title' => _SCHEDULER_WARN_GO,
    ]);
    if (!$conf['security']['log_b']) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _SCHEDULER_WARN_DB.' '.$wargo('dbbackup').'.']);
    if (!$conf['security']['log_d']) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _SCHEDULER_WARNLOG.' '.$wargo('filescan').'.']);
    $head = [
        ['content' => _TITLE],
        ['content' => _SCHEDULER_NEXT, 'is_col_date' => true],
        ['content' => _SCHEDULER_RESULT, 'is_col_status' => true],
        ['content' => _SCHEDULER_PRIO, 'is_col_count' => true],
        ['content' => _STATUS, 'is_col_status' => true, 'is_head_status' => true],
        ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
    ];
    $rows = [];
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
        $tone = match (true) {
            $held => 'is_wait',
            !empty($state['running']) => 'is_fail',
            default => match ($state['last_status'] ?? 'idle') {
                'success' => 'is_on',
                'failed', 'crashed' => 'is_fail',
                default => 'is_off',
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
            ['label' => _SCHEDULER_JOBKEY, 'value' => $name],
            ['label' => _TYPE, 'value' => $type],
            ['label' => _STATUS, 'value' => $stat],
        ];
        if ($sched !== '') $tips[] = ['label' => _SCHEDULER_SCHED, 'value' => $sched];
        $tips[] = ['label' => _SCHEDULER_LASTRUN, 'value' => $last];
        $tips[] = ['label' => _SCHEDULER_LAST_OK, 'value' => $lastok];
        $tips[] = ['label' => _SCHEDULER_NEXTRUN, 'value' => $nextr];
        $tips[] = ['label' => _SCHEDULER_TRIGGER, 'value' => ($trig !== '' ? $trig : _NO)];
        $tips[] = ['label' => _SCHEDULER_RUNTIME, 'value' => (string)$time];
        $tips[] = ['label' => _SCHEDULER_FAILS, 'value' => (string)$fail];
        if ($note !== '') $tips[] = ['label' => _DESCRIPTION, 'value' => $note];
        $title = $job['title'];
        $acts = [[
            'href' => $afile.'.php?name=scheduler&op=add&job='.$name,
            'icon_name' => 'pencil',
            'title' => _EDIT,
            'is_edit' => true,
        ]];
        $keys = ['name' => 'scheduler', 'job' => $name];
        if ((int)($job['manual'] ?? 0) === 1) $acts[] = getTplPostAction(['op' => 'run'] + $keys, 'play-fill', _SCHEDULER_RUN);
        $acts[] = getTplPostAction(['op' => 'unlock'] + $keys, 'unlock', _SCHEDULER_UNLOCK);
        $drop = (($job['type'] ?? '') === 'custom') ? getTplPostAction(['name' => 'scheduler', 'op' => 'delete', 'id[]' => $name], 'trash', _DELETE) : [];
        $rows[] = ['name' => (string)$title, 'details' => $tips, 'drop' => $drop, 'cells' => [
            ['is_truncate' => true, 'title_text' => (string)$title, 'has_content_text' => true, 'content_text' => (string)$title],
            ['is_col_date' => true, 'moment' => $next, 'has_content_text' => true, 'content_text' => _NO],
            ['is_col_status' => true, 'stat' => ['text' => _SCHEDULER_RESULT.': '.$stat, $tone => true]],
            ['is_col_count' => true, 'has_content_text' => true, 'content_text' => (string)($job['priority'] ?? '100')],
            ['is_col_status' => true, 'stat' => ['is_on' => (int)$isactive === 1]],
            ['is_col_actions' => true, 'dial' => $acts],
        ]];
    }
    $head[0]['is_truncate'] = true;
    setHead();
    echo getTplAdminList([
        'head_html' => getTplAdminTabs(['ops' => ['name=scheduler', 'name=scheduler&op=add', 'name=scheduler&op=info'], 'tabs' => [_HOME, _ADD, _MANUAL], 'part' => [0]]),
        'note_html' => $cont,
        'is_fixed' => true,
        'head' => $head,
        'rows' => $rows,
        'count' => count($rows),
    ]);
    setFoot();
}

# The form of one job, new or stored; a refused save hands back what was sent and why, so nothing typed is lost and the refusal names its field
function add(string $name = '', array $sent = [], string $note = ''): void {
    global $conf, $afile, $tpl;
    if ($name === '' && $sent === []) $name = getVar('get', 'job', 'var', '');
    $name = preg_replace('#[^a-z]#', '', strtolower($name));
    $jobs = getSchedulerJobs();
    $isnew = ($sent === []) ? !isset($jobs[$name]) : !empty($sent['isnew']);
    $job = (!$isnew && isset($jobs[$name])) ? $jobs[$name] : [
        'name' => '', 'title' => '', 'type' => 'custom', 'active' => '1',
        'system' => '', 'schedule' => '*/5 * * * *', 'priority' => '100',
        'lock_timeout' => (string)($conf['scheduler']['lock_timeout'] ?? '1800'),
        'manual' => '1', 'settings' => ['url' => ''],
    ];
    $job = array_replace($job, array_diff_key($sent, ['isnew' => 1, 'key' => 1]));
    $iscustom = (($job['type'] ?? 'custom') === 'custom');
    $key = $isnew ? (string)($sent['key'] ?? '') : $job['name'];
    $url = $job['settings']['url'] ?? '';
    $schedule = (string)($job['schedule'] ?? '');
    $info = $iscustom ? _SCHEDULER_URLINFO : _SCHEDULER_SYSINFO;
    $readonly = $isnew ? '' : ' readonly';
    $cont = getTplAdminTabs(['ops' => ['name=scheduler', 'name=scheduler&op=add', 'name=scheduler&op=info'], 'tabs' => [_HOME, _ADD, _MANUAL], 'tab' => 1]);
    $cont .= checkPerms(CONFIG_DIR.'/scheduler.php');
    if (!$iscustom && in_array($key, ['dbbackup', 'filescan'], true)) $cont .= checkPerms(CONFIG_DIR.'/security.php');
    if ($note !== '') $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $note]);
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
        'label_for' => 'f-type',
        'label_html' => _TYPE,
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'input_id' => 'f-type',
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
            'label_for' => 'f-system',
            'label_html' => _SCHEDULER_SYSTEM,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'input_id' => 'f-system',
                'value_attr' => (string)($job['system'] ?? ''),
                'is_config' => true,
                'input_attr' => 'disabled',
            ]),
        ];
    }
    $rows[] = [
        'label_for' => 'f-schedule',
        'label_html' => _SCHEDULER_SCHED, 'hint_html' => _SCHEDULER_CRONFMT, 'hint_id' => $hntid = getFieldIds('f-schedule')['hint'],
        'field_html' => $tpl->getHtmlFrag('input', ['describedby' => $hntid,
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
    if (isset(SCHED_LIMITS[$key], $job['settings']['limit'])) {
        $rows[] = [
            'label_for' => 'f-limit',
            'label_html' => _SCHEDULER_LIMIT,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'limit',
                'input_id' => 'f-limit',
                'value_attr' => (string)$job['settings']['limit'],
                'is_required' => true,
                'is_config' => true,
                'input_attr' => 'min="1" max="'.SCHED_LIMITS[$key].'"',
            ]),
        ];
    }
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $switch = static fn(string $field, string $label): array => [
        'label_html' => $label,
        'label_id' => $labid = getFieldIds('', $field)['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => $field, 'value' => (string)$conf['security'][$field], 'options' => $yesno]),
    ];
    if (!$iscustom && $key === 'dbbackup') {
        $rows[] = $switch('log_b', _SEC_DB);
        $rows[] = [
            'label_for' => 'f-dump-age',
            'label_html' => _SEC_DUMP_AGE,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'dump_age',
                'input_id' => 'f-dump-age',
                'value_attr' => intval($conf['security']['dump_age']),
            ]),
        ];
    }
    if (!$iscustom && $key === 'filescan') {
        $rows[] = $switch('log_d', _SEC_LOG_D);
        $rows[] = [
            'label_for' => 'f-dump-skip',
            'label_html' => _SEC_DUMP_SKIP,
            'hint_html' => _SEC_DUMP_SKIP_INFO,
            'hint_id' => $hntid = getFieldIds('f-dump-skip')['hint'],
            'field_html' => $tpl->getHtmlFrag('textarea', [
                'describedby' => $hntid,
                'name_attr' => 'dump_skip',
                'input_id' => 'f-dump-skip',
                'rows_num' => 8,
                'value_text' => (string)($conf['security']['dump_skip'] ?? ''),
            ]),
            'is_full' => true,
        ];
        $rows[] = $switch('mail_d', _SEC_MAIL_D_SEND);
    }
    $rows[] = [
        'label_for' => 'f-priority',
        'label_html' => _SCHEDULER_PRIO, 'hint_html' => _SCHEDULER_PRIOTIP, 'hint_id' => $hntid = getFieldIds('f-priority')['hint'],
        'field_html' => $tpl->getHtmlFrag('input', ['describedby' => $hntid,
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
    $rows[] = [
        'label_html' => _ACTIVATE2,
        'label_id' => $labid = getFieldIds('', 'active')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'active', 'value' => (string)(int)$job['active'], 'options' => $yesno]),
    ];
    $rows[] = [
        'label_html' => _SCHEDULER_MANUAL,
        'label_id' => $labid = getFieldIds('', 'manual')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'manual', 'value' => (string)(int)$job['manual'], 'options' => $yesno]),
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'scheduler'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'type', 'valueattr' => (string)$job['type']],
            ['nameattr' => 'isnew', 'valueattr' => $isnew ? '1' : '0'],
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
    $text = $warn ? _TOKENMISS : _SUCCSAVE;
    if (!$warn) {
        $type = getVar('post', 'type', 'var', 'custom');
        $title = trim(getVar('post', 'title', 'text', ''));
        $link = trim((string)getVar('post', 'url', 'raw', ''));
        $path = str_starts_with($link, '/') && !str_starts_with($link, '//');
        $url = $path ? filterText($link) : filterWebUrl($link);
        $isurl = $path ? !preg_match('#\s#', $link) : filter_var($url, FILTER_VALIDATE_URL) !== false;
        $sched = trim(getVar('post', 'schedule', 'raw', ''));
        $cfg = is_readable(CONFIG_DIR.'/scheduler.php') ? (require CONFIG_DIR.'/scheduler.php') : [];
        $schedcfg = is_array($cfg) && isset($cfg['scheduler']) && is_array($cfg['scheduler']) ? $cfg['scheduler'] : ($conf['scheduler'] ?? []);
        $jobs = $schedcfg['jobs'] ?? [];
        $curr = $jobs[$name] ?? [];
        $issys = (getSchedulerJob($name, $curr)['type'] ?? '') === 'system';
        $isnew = getVar('post', 'isnew', 'num', 0) === 1;
        $sett = $issys ? ((isset($curr['settings']) && is_array($curr['settings'])) ? $curr['settings'] : []) : ['url' => $url];
        $limit = ($issys && isset(SCHED_LIMITS[$name], $sett['limit'])) ? getVar('post', 'limit', 'num', 0) : null;
        if ($limit !== null) $sett['limit'] = (string)$limit;
        $priority = (int)getVar('post', 'priority', 'num', 100);
        $owner = '';
        foreach ($jobs as $jkey => $jval) if ($jkey !== $name && (int)($jval['priority'] ?? 100) === $priority) $owner = (string)($jval['title'] ?? $jkey);
        $cron = getSchedulerSchedule($sched);
        $note = match (true) {
            $name === '' || $name !== $rawname => _SCHEDULER_BADKEY,
            $isnew && (isset($jobs[$name]) || isset(getSchedulerJobs()[$name])) => sprintf(_SCHEDULER_KEYUSE, $name),
            $title === '' => _CERROR,
            !$issys && ($type !== 'custom' || !$isurl) => _SCHEDULER_NOURL,
            $cron === '' => _SCHEDULER_BADCRON,
            $limit !== null && ($limit < 1 || $limit > SCHED_LIMITS[$name]) => sprintf(_SCHEDULER_BADLIM, SCHED_LIMITS[$name] ?? 0),
            $owner !== '' => sprintf(_SCHEDULER_PRIOUSE, $priority, htmlspecialchars($owner, ENT_QUOTES, 'UTF-8')),
            default => '',
        };
        if ($note !== '') {
            $sent = ['isnew' => $isnew, 'key' => $rawname, 'title' => $title, 'schedule' => $sched, 'priority' => (string)$priority,
                'lock_timeout' => (string)getVar('post', 'lock_timeout', 'num', 1800), 'active' => (string)getVar('post', 'active', 'num', 0),
                'manual' => (string)getVar('post', 'manual', 'num', 0), 'settings' => $issys ? $sett : ['url' => $link]];
            add($name, $sent, $note);
            return;
        }
        $sched = $cron;
        $item = [
            'title' => $title,
            'type' => $issys ? 'system' : 'custom',
            'active' => getVar('post', 'active', 'num', 0),
            'system' => $issys ? (string)($curr['system'] ?? '') : '',
            'schedule' => $sched,
            'priority' => $priority,
            'lock_timeout' => getVar('post', 'lock_timeout', 'num', 1800),
            'manual' => getVar('post', 'manual', 'num', 1),
            'settings' => $sett,
        ];
        $data = $schedcfg;
        $data['jobs'][$name] = $item;
        foreach ($data['jobs'] as $jkey => $jval) {
            if (is_array($jval)) $data['jobs'][$jkey] = getSchedulerJob((string)$jkey, $jval);
        }
        ksort($data['jobs']);
        $warn = !setConfigFile('scheduler.php', $data);
        if (!$warn && $issys && $name === 'dbbackup') {
            $sets = ['log_b' => getVar('post', 'log_b', 'num'), 'dump_age' => max(1, intval(getVar('post', 'dump_age', 'num', 7)))];
            $warn = !setConfigFile('security.php', $sets, $conf['security']);
        }
        if (!$warn && $issys && $name === 'filescan') {
            $dskip = [];
            foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", getVar('post', 'dump_skip', 'raw', ''))) as $line) {
                $line = trim(str_replace('\\', '/', $line));
                $line = preg_replace('#/+#', '/', $line);
                $line = preg_replace('#^\./#', '', (string)$line);
                $line = trim((string)$line, " \t\n\r\0\x0B");
                if ($line === '' || $line === '.' || $line === './' || str_contains($line, '..')) continue;
                $dskip[] = str_ends_with($line, '/') ? $line : $line.'/';
            }
            $sets = ['log_d' => getVar('post', 'log_d', 'num'), 'mail_d' => getVar('post', 'mail_d', 'num'), 'dump_skip' => implode("\n", array_unique($dskip))];
            $warn = !setConfigFile('security.php', $sets, $conf['security']);
        }
        if ($warn) $text = getConfigJournal() ? _CONFIG_PENDING : _ERROR_UP;
    }
    setRedirect($afile.'.php?name=scheduler&op=add&job='.$name, false, 302, $text, $warn);
}

function run(): void {
    global $afile;
    $warn = !checkAdminPost('scheduler');
    $name = preg_replace('#[^a-z]#', '', strtolower(getVar('post', 'job', 'var', '')));
    $text = $warn ? _TOKENMISS : _SUCCSAVE;
    if (!$warn) {
        $jobs = getSchedulerJobs();
        if ($name === '' || !isset($jobs[$name]) || (int)($jobs[$name]['manual'] ?? 0) !== 1) setRedirect($afile.'.php?name=scheduler', false, 302, _ERROR, true);
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

# Remove custom jobs with the state of their runs, so a job added later under the same key starts without the history of the gone one; the lock file stays, as its rule says
function delete(): void {
    global $conf, $afile;
    $warn = !checkAdminPost('scheduler');
    $names = array_map(static fn(mixed $v): string => is_string($v) ? (string)preg_replace('#[^a-z]#', '', strtolower($v)) : '', getVar('post', 'id[]', 'var'));
    $done = false;
    $text = $warn ? _TOKENMISS : _ERROR;
    if (!$warn) {
        $cfg = is_readable(CONFIG_DIR.'/scheduler.php') ? (require CONFIG_DIR.'/scheduler.php') : [];
        $schedcfg = is_array($cfg) && isset($cfg['scheduler']) && is_array($cfg['scheduler']) ? $cfg['scheduler'] : ($conf['scheduler'] ?? []);
        $jobs = $schedcfg['jobs'] ?? [];
        $drop = array_filter($names, static fn(string $v): bool => ($jobs[$v]['type'] ?? '') === 'custom');
        $done = $names && count($drop) === count($names);
        if ($drop) {
            $jobs = array_diff_key($jobs, array_flip($drop));
            $data = $schedcfg;
            $data['jobs'] = $jobs;
            $kept = setConfigFile('scheduler.php', $data);
            if (!$kept) $text = getConfigJournal() ? _CONFIG_PENDING : _ERROR_UP;
            $done = $kept && $done;
            foreach ($done ? $drop : [] as $one) if (is_file(LOGS_DIR.'/scheduler/'.$one.'.json')) unlink(LOGS_DIR.'/scheduler/'.$one.'.json');
        }
        if ($done) $text = _SUCCDELETE;
    }
    setRedirect($afile.'.php?name=scheduler', false, 302, $text, !$done);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=scheduler', 'name=scheduler&op=add', 'name=scheduler&op=info'],
        'tabs' => [_HOME, _ADD, _MANUAL],
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
