<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

$labels = [
    'admin' => _SEC_STAT_A,
    'database' => _SEC_STAT_DB,
    'error_php' => _SEC_STAT_ERROR_D,
    'error_file' => _SEC_STAT_ERROR_FILE,
    'error_site' => _SEC_STAT_ERROR_S,
    'error_sql' => _SEC_STAT_ERROR_SQL,
    'file' => _SEC_STAT_FILE,
    'filescan' => _SEC_STAT_DUML,
    'filescan_tree' => _SEC_STAT_DUM,
    'hack' => _SEC_STAT_HACK,
    'oauth' => _SEC_STAT_OAUTH,
    'request' => _SEC_STAT_LOG,
    'user' => _SEC_STAT_U,
    'warn' => _SEC_STAT_WARN
];

$tabset = [
    'ops' => ['name=logs', 'name=logs&op=logins', 'name=logs&op=config', 'name=logs&op=info'],
    'tabs' => [_HOME, _ACCOUNT_LOGINS, _PREFERENCES, _MANUAL],
];

function logs(): void {
    global $afile, $conf, $labels, $tabset;
    $rows = [];
    $files = is_dir(LOGS_DIR) ? scandir(LOGS_DIR) : [];
    foreach ($files as $file) {
        if (!preg_match('#(.*)\.log$#', $file)) continue;
        $name = (string)pathinfo($file, PATHINFO_FILENAME);
        $title = $labels[$name] ?? $name;
        $path = LOGS_DIR.'/'.$file;
        $rows[] = ['name' => $title, 'details' => [['label' => _FILE, 'value' => 'storage/logs/'.$file]],
            'drop' => getTplPostAction(['name' => 'logs', 'op' => 'delete', 'id[]' => $name], 'trash', _ONDELETE), 'cells' => [
            ['is_truncate' => true, 'title_text' => $title, 'has_content_text' => true, 'content_text' => $title],
            ['is_col_count' => true, 'content_html' => getTplMeter(_SIZE, filesize($path), intval($conf['security']['log_size']), '', 'ok', filterSize(filesize($path)), true)],
            ['is_col_date' => true, 'moment' => filemtime($path)],
            ['is_col_actions' => true, 'dial' => [
                ['href' => $afile.'.php?name=logs&op=logview&file='.urlencode($name), 'icon_name' => 'file-text', 'title' => _SHOW],
                ['href' => $afile.'.php?name=logs&op=download&file='.urlencode($name), 'icon_name' => 'download', 'title' => _DOWN],
            ]],
        ]];
    }
    setHead();
    echo getTplAdminList([
        'head_html' => getTplAdminTabs($tabset + ['part' => [0, 1]]),
        'is_fixed' => true,
        'head' => [
            ['content' => _TITLE, 'is_truncate' => true],
            ['content' => _SIZE, 'is_col_count' => true],
            ['content' => _DATE, 'is_col_date' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ],
        'rows' => $rows,
        'count' => count($rows),
    ]);
    setFoot();
}

function logview(): void {
    global $afile, $conf, $labels, $tabset, $tpl;
    $chans = ['admin' => 'admin', 'database' => 'database', 'error_file' => 'file', 'error_php' => 'php', 'error_site' => 'site', 'error_sql' => 'sql', 'file' => 'file',
        'filescan' => 'filescan', 'hack' => 'hack', 'oauth' => 'oauth', 'request' => 'request', 'user' => 'user', 'warn' => 'warn'];
    $file = getVar('get', 'file', 'var');
    if ($file && isset($chans[$file])) {
        $levs = array_slice(['debug', 'info', 'notice', 'warning', 'error', 'critical'], $file === 'error_file' ? 4 : 0);
        $events = ['' => _SEC_ACTS, 'login' => _SEC_LOGINS, 'all' => _ALL];
        $results = ['done' => _SEC_DONE, 'refused' => _SEC_REFUSED, 'open' => _SEC_OPEN, 'ok' => _ACCOUNT_LOGOK, 'password' => _ACCOUNT_LOGPASS, 'blocked' => _ACCOUNT_LOGBLOCK];
        $words = ['add' => _ADD, 'save' => _SAVE, 'delete' => _DELETE, 'status' => _STATUS, 'updateAdminBlockOrder' => _SORTORDER, 'updateAdminCategoryOrder' => _SORTORDER];
        $mods = [];
        foreach (array_keys($conf['modules']) as $key) $mods[$key] = str_starts_with($name = getModuleLabel($key), '_') ? $key : $name;
        asort($mods);
        $isadm = $file === 'admin';
        $levl = getVar('get', 'level', 'var', '');
        $levl = in_array($levl, $levs, true) ? $levl : '';
        $ip = getVar('get', 'ip', 'text', '');
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
        $from = getVar('get', 'from', 'date', '');
        $to = getVar('get', 'to', 'date', '');
        $event = $isadm ? getVar('get', 'event', 'var', '') : '';
        $event = isset($events[$event]) ? $event : '';
        $modul = $isadm ? getVar('get', 'modul', 'var', '') : '';
        $modul = isset($mods[$modul]) ? $modul : '';
        $res = $isadm ? getVar('get', 'res', 'var', '') : '';
        $res = isset($results[$res]) ? $res : '';
        $num = max(1, getVar('get', 'num', 'num', 1));
        $filt = array_filter(['level' => $levl ?: $levs[0], 'ip' => $ip, 'from' => $from, 'to' => $to, 'event' => $isadm ? ($event === '' ? 'action' : $event) : '',
            'modul' => $modul, 'result' => $res], static fn($v) => $v !== '' && $v !== 'all');
        $lim = getListLimit(fn(): int => Logger::getEntries($chans[$file], $filt, 1, 0)['total']);
        $log = Logger::getEntries($chans[$file], $filt, $num, $lim);
        $pages = max(1, (int)ceil($log['total'] / $lim));
        $num = min($num, $pages);
        $pick = static function(string $name, string $label, array $list, string $cur, string $all): array {
            $opts = ($all !== '') ? [['value_attr' => '', 'label_text' => $all, 'is_selected' => $cur === '']] : [];
            foreach ($list as $key => $text) $opts[] = ['value_attr' => (string)$key, 'label_text' => $text, 'is_selected' => (string)$key === $cur];
            return ['field_id' => 'sl-log-'.$name, 'label' => $label, 'name_attr' => $name, 'options' => $opts];
        };
        $fields = $isadm ? [$pick('event', _TYPE, $events, $event, ''), $pick('modul', _MODUL, $mods, $modul, _ALL), $pick('res', _STATUS, $results, $res, _ALL)] : [];
        $fields[] = $pick('level', _SEC_LEVEL, array_combine($levs, array_map(static fn($v) => "\u{2265} ".$v, $levs)), $levl, _ALL);
        $fields[] = ['field_id' => 'sl-log-ip', 'label' => _IP, 'name_attr' => 'ip', 'itype' => 'text', 'value' => $ip];
        $fields[] = ['field_id' => 'sl-log-from', 'label' => _DATEFROM, 'name_attr' => 'from', 'itype' => 'date', 'value' => $from];
        $fields[] = ['field_id' => 'sl-log-to', 'label' => _DATETO, 'name_attr' => 'to', 'itype' => 'date', 'value' => $to];
        $form = $tpl->getHtmlFrag('list-filter', [
            'action_url' => $afile.'.php',
            'label' => _SEARCH,
            'is_part' => true,
            'hidden' => [['nameattr' => 'name', 'valueattr' => 'logs'], ['nameattr' => 'op', 'valueattr' => 'logview'], ['nameattr' => 'file', 'valueattr' => $file]],
            'fields' => $fields,
        ]);
        $rows = [];
        foreach ($log['rows'] as $row) {
            $cells = [['is_col_date' => true, 'moment' => $row['ts']],
                ['is_col_status' => true, 'has_content_text' => true, 'content_text' => (string)$row['level']]];
            if ($isadm) {
                $login = ($row['event'] ?? '') === 'login';
                $who = $login ? (string)($row['login'] ?? '').(empty($row['uid']) ? '' : ' #'.intval($row['uid'])) : (string)($row['aname'] ?? '').' #'.intval($row['aid'] ?? 0);
                $op = (string)($row['op'] ?? '');
                $tid = intval($row['target'] ?? 0);
                $more = intval($row['num'] ?? 0) - 1;
                $cells[] = ['is_truncate' => true, 'has_content_text' => true, 'content_text' => trim($who)] + getTplLongCell(trim($who), _ADMIN);
                $cells[] = ['has_content_text' => true, 'content_text' => $mods[$row['modul'] ?? ''] ?? (string)($row['modul'] ?? '')];
                $cells[] = ['has_content_text' => true, 'content_text' => $login ? _SEC_ENTRY : ($words[$op] ?? $op)];
                $cells[] = ['is_col_status' => true, 'has_content_text' => true, 'content_text' => $tid ? '#'.$tid.($more > 0 ? ' +'.$more : '') : ''];
                $cells[] = ['has_content_text' => true, 'content_text' => $results[$row['result'] ?? ''] ?? (string)($row['result'] ?? '')];
            } else {
                $cells[] = ['is_truncate' => true, 'has_content_text' => true, 'content_text' => (string)$row['msg']] + getTplLongCell((string)$row['msg'], _MESSAGE);
            }
            $cells[] = ['content_html' => Geoip::getIpHtml((string)($row['ip'] ?? ''))];
            $rows[] = ['cells' => $cells];
        }
        $head = $isadm ? [['content' => _ADMIN, 'is_truncate' => true], ['content' => _MODUL], ['content' => _CHECKOP], ['content' => _SEC_OBJECT, 'is_col_status' => true],
            ['content' => _STATUS]] : [['content' => _MESSAGE, 'is_truncate' => true]];
        $head = array_merge([['content' => _DATE, 'is_col_date' => true], ['content' => _SEC_LEVEL, 'is_col_status' => true]], $head, [['content' => _IP, 'is_col_date' => true]]);
        $link = 'name=logs&op=logview&file='.$file.($event !== '' ? '&event='.$event : '').($modul !== '' ? '&modul='.$modul : '').($res !== '' ? '&res='.$res : '')
            .($levl !== '' ? '&level='.$levl : '').($ip !== '' ? '&ip='.urlencode($ip) : '').($from !== '' ? '&from='.$from : '').($to !== '' ? '&to='.$to : '').'&';
        setHead();
        echo getTplAdminList([
            'head_html' => getTplAdminTabs($tabset + ['part' => [0, 1], 'subtitle_html' => $form]),
            'key' => 'logs-'.$file,
            'note_html' => checkPerms(LOGS_DIR.'/'.$file.'.log').$tpl->getHtmlFrag('alert', ['text' => $labels[$file] ?? $file]),
            'is_fixed' => true,
            'head' => $head,
            'rows' => $rows,
            'href' => $afile.'.php?'.$link,
            'page' => $num,
            'pages' => $pages,
            'count' => $log['total'],
            'limit' => $lim,
            'none_text' => ($event.$modul.$res.$levl.$ip.$from.$to !== '') ? _NONE_FOUND : _NO_INFO,
        ]);
        setFoot();
        return;
    }
    setHead();
    $cont = getTplAdminTabs($tabset);
    if ($file) {
        $title = $labels[$file] ?? $file;
        $path = LOGS_DIR.'/'.$file.'.log';
        $content = (is_file($path) && is_readable($path)) ? file_get_contents($path) : false;
        if ($content === false) {
            $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
            echo $cont;
            setFoot();
            return;
        }
        $cont .= checkPerms($path);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => Editor::getCode([
            'id' => 'code',
            'label' => $title,
            'lang' => 'text',
            'text' => $content,
        ])]);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function logins(): void {
    global $afile, $tabset, $tpl;
    $sides = ['admin' => _ADMIN, 'site' => _USER];
    $results = ['ok' => _ACCOUNT_LOGOK, 'password' => _ACCOUNT_LOGPASS, 'blocked' => _ACCOUNT_LOGBLOCK];
    $ways = ['login' => _PASSWORD, 'oauth' => _ACCOUNT_LOGOAUTH];
    $uid = getVar('get', 'uid', 'num', 0);
    $side = getVar('get', 'side', 'word', '');
    $res = getVar('get', 'res', 'word', '');
    $way = getVar('get', 'way', 'word', '');
    $from = getVar('get', 'from', 'date', '');
    $to = getVar('get', 'to', 'date', '');
    $num = max(1, getVar('get', 'num', 'num', 1));
    $side = isset($sides[$side]) ? $side : '';
    $res = isset($results[$res]) ? $res : '';
    $way = isset($ways[$way]) ? $way : '';
    $filt = array_filter([
        'uid' => $uid ?: null,
        'admin' => ($side !== '') ? $side === 'admin' : null,
        'result' => $res,
        'way' => $way,
        'from' => ($from !== '') ? $from.' 00:00:00' : '',
        'to' => ($to !== '') ? date('Y-m-d', strtotime($to.' +1 day')).' 00:00:00' : '',
    ], static fn($v) => $v !== null && $v !== '');
    $lim = getListLimit(fn(): int => getLoginLog($filt, 1, 0)['total']);
    $log = getLoginLog($filt, $num, $lim);
    $pages = max(1, (int)ceil($log['total'] / $lim));
    $num = min($num, $pages);
    $pick = static function(string $name, string $label, array $list, string $cur): array {
        $opts = [['value_attr' => '', 'label_text' => _ALL, 'is_selected' => $cur === '']];
        foreach ($list as $key => $text) $opts[] = ['value_attr' => $key, 'label_text' => $text, 'is_selected' => $key === $cur];
        return ['field_id' => 'sl-login-'.$name, 'label' => $label, 'name_attr' => $name, 'options' => $opts];
    };
    $form = $tpl->getHtmlFrag('list-filter', [
        'action_url' => $afile.'.php',
        'label' => _SEARCH,
        'is_part' => true,
        'hidden' => [['nameattr' => 'name', 'valueattr' => 'logs'], ['nameattr' => 'op', 'valueattr' => 'logins']],
        'fields' => [
            ['field_id' => 'sl-login-uid', 'label' => _ACCOUNT.' '._ID, 'name_attr' => 'uid', 'itype' => 'number', 'value' => $uid ? (string)$uid : '', 'is_count' => true],
            $pick('side', _ADMIN.' / '._USER, $sides, $side), $pick('res', _STATUS, $results, $res), $pick('way', _TYPE, $ways, $way),
            ['field_id' => 'sl-login-from', 'label' => _DATEFROM, 'name_attr' => 'from', 'itype' => 'date', 'value' => $from],
            ['field_id' => 'sl-login-to', 'label' => _DATETO, 'name_attr' => 'to', 'itype' => 'date', 'value' => $to],
        ],
    ]);
    $rows = [];
    foreach ($log['rows'] as $row) {
        $agent = html_entity_decode((string)$row['agent'], ENT_QUOTES, 'UTF-8');
        $info = getAgentInfo($agent, 0);
        $rows[] = ['cells' => [
            ['is_col_date' => true, 'moment' => $row['time']],
            ['is_truncate' => true, 'has_content_text' => true, 'content_text' => trim((string)$row['name'].' #'.intval($row['uid']))]
                + getTplLongCell(trim((string)$row['name'].' #'.intval($row['uid'])), _ACCOUNT),
            ['has_content_text' => true, 'content_text' => $row['admin'] ? _ADMIN : _USER],
            ['has_content_text' => true, 'content_text' => $results[$row['result']] ?? (string)$row['result']],
            ['has_content_text' => true, 'content_text' => $ways[$row['way']] ?? (string)$row['way']],
            ['content_html' => Geoip::getIpHtml((string)$row['ip'])],
            ['is_truncate' => true, 'has_content_text' => true, 'content_text' => $info['browser'].' / '.$info['os']] + getTplLongCell($agent, _BROWSER),
        ]];
    }
    $link = 'name=logs&op=logins'.($uid ? '&uid='.$uid : '').($side !== '' ? '&side='.$side : '').($res !== '' ? '&res='.$res : '')
        .($way !== '' ? '&way='.$way : '').($from !== '' ? '&from='.$from : '').($to !== '' ? '&to='.$to : '').'&';
    setHead();
    echo getTplAdminList([
        'head_html' => getTplAdminTabs($tabset + ['tab' => 1, 'part' => [0, 1], 'subtitle_html' => $form]),
        'key' => 'logs-logins',
        'head' => [['content' => _DATE, 'is_col_date' => true], ['content' => _ACCOUNT, 'is_truncate' => true], ['content' => _ADMIN.' / '._USER], ['content' => _STATUS],
            ['content' => _TYPE], ['content' => _IP], ['content' => _BROWSER, 'is_truncate' => true]],
        'rows' => $rows,
        'href' => $afile.'.php?'.$link,
        'page' => $num,
        'pages' => $pages,
        'count' => $log['total'],
        'limit' => $lim,
        'none_text' => $filt ? _NONE_FOUND : _NO_INFO,
    ]);
    setFoot();
}

function config(): void {
    global $conf, $afile, $tabset, $tpl;
    setHead();
    $cont = getTplAdminTabs($tabset + ['tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows = [[
        'label_for' => 'f-log-size',
        'label_html' => _SEC_LOG_SIZE,
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'log_size', 'input_id' => 'f-log-size', 'value_attr' => $conf['security']['log_size']]),
    ]];
    $sets = ['error_log' => _SEC_STAT, 'write_h' => _SEC_HACK_STAT, 'write_w' => _SEC_WARN_STAT, 'log' => _SEC_LOG, 'log_a' => _SEC_LOG_A, 'log_u' => _SEC_LOG_U];
    foreach ($sets as $name => $label) {
        $rows[] = [
            'label_html' => $label,
            'label_id' => $labid = getFieldIds('', $name)['label'],
            'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => $name, 'value' => $conf['security'][$name], 'options' => $yesno]),
        ];
    }
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'logs'],
            ['nameattr' => 'op', 'valueattr' => 'configsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('logs')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $conf, $afile;
    $warn = !checkAdminPost('logs');
    $text = $warn ? _TOKENMISS : _SUCCSAVE;
    if (!$warn) {
        $cont = ['log_size' => getVar('post', 'log_size', 'num', '1048576')];
        foreach (['error_log', 'write_h', 'write_w', 'log', 'log_a', 'log_u'] as $name) $cont[$name] = getVar('post', $name, 'num');
        $warn = !setConfigFile('security.php', $cont, $conf['security']);
        if ($warn) $text = getConfigJournal() ? _CONFIG_PENDING : _ERROR_UP;
    }
    setRedirect($afile.'.php?name=logs&op=config', false, 302, $text, $warn);
}

function info(): void {
    global $tabset;
    setTplAdminInfoPage($tabset);
}

function download(): void {
    global $afile;
    $file = getVar('get', 'file', 'var');
    if ($file) {
        $path = LOGS_DIR.'/'.$file.'.log';
        if (is_file($path)) getFileStream($path, date('d.m.Y').'_'.$file.'.log');
        setRedirect($afile.'.php?name=logs');
    } else {
        setRedirect($afile.'.php?name=logs');
    }
}

function delete(): void {
    global $afile;
    $warn = !checkAdminPost('logs');
    $files = getVar('post', 'id[]', 'var');
    $done = !$warn && $files;
    foreach ($done ? $files : [] as $file) {
        if (!is_string($file) || !is_file(LOGS_DIR.'/'.$file.'.log') || !unlink(LOGS_DIR.'/'.$file.'.log')) $done = false;
    }
    setRedirect($afile.'.php?name=logs', false, 302, $warn ? _TOKENMISS : ($done ? _SUCCDELETE : _ERROR), !$done);
}

switch ($op) {
    default: logs(); break;
    case 'logview': logview(); break;
    case 'logins': logins(); break;
    case 'download': download(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
