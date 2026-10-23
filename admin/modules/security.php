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
    'ops' => ['name=security', 'name=security&op=logins', 'name=security&op=banlist', 'name=security&op=passwd', 'name=security&op=config', 'name=security&op=info'],
    'tabs' => [_HOME, _ACCOUNT_LOGINS, _BANNED, _SEC_PASS, _PREFERENCES, _MANUAL],
];

function security(): void {
    global $afile, $labels, $tabset, $tpl;
    $rows = [];
    $files = is_dir(LOGS_DIR) ? scandir(LOGS_DIR) : [];
    foreach ($files as $file) {
        if (!preg_match('#(.*)\.log$#', $file)) continue;
        $name = (string)pathinfo($file, PATHINFO_FILENAME);
        $title = $labels[$name] ?? $name;
        $path = LOGS_DIR.'/'.$file;
        $rows[] = ['name' => $title, 'cells' => [
            ['is_truncate' => true, 'title_text' => $title, 'has_content_text' => true, 'content_text' => $title,
                'prefix_html' => $tpl->getHtmlFrag('popover', ['items' => [['label' => _FILE, 'value' => 'storage/logs/'.$file, 'is_last' => true]]])],
            ['is_col_count' => true, 'has_content_text' => true, 'content_text' => filterSize(filesize($path))],
            ['is_col_date' => true, 'stamp' => date('c', filemtime($path)), 'content_text' => date(_TIMESTRING, filemtime($path))],
            ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _EDITOR, 'dial' => [
                ['href' => $afile.'.php?name=security&op=logview&file='.urlencode($name), 'icon_name' => 'file-text', 'title' => _SHOW],
                ['href' => $afile.'.php?name=security&op=download&file='.urlencode($name), 'icon_name' => 'download', 'title' => _DOWN],
                getTplPostAction(['name' => 'security', 'op' => 'delete', 'file' => $name], 'trash', _ONDELETE) + ['later' => 'gone del'],
            ]])],
        ]];
    }
    setHead();
    echo getTplAdminList([
        'head_html' => getTplAdminTabs($tabset + ['part' => [0, 1, 2]]),
        'note_html' => checkPerms(CONFIG_DIR.'/security.php').getSelfCheckAlert(),
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
        $day = static fn(string $v): string => (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $v) && strtotime($v) !== false) ? $v : '';
        $isadm = $file === 'admin';
        $levl = getVar('get', 'level', 'var', '');
        $levl = in_array($levl, $levs, true) ? $levl : '';
        $ip = getVar('get', 'ip', 'text', '');
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
        $from = $day(getVar('get', 'from', 'text', ''));
        $to = $day(getVar('get', 'to', 'text', ''));
        $event = $isadm ? getVar('get', 'event', 'var', '') : '';
        $event = isset($events[$event]) ? $event : '';
        $modul = $isadm ? getVar('get', 'modul', 'var', '') : '';
        $modul = isset($mods[$modul]) ? $modul : '';
        $res = $isadm ? getVar('get', 'res', 'var', '') : '';
        $res = isset($results[$res]) ? $res : '';
        $num = max(1, getVar('get', 'num', 'num', 1));
        $filt = array_filter(['level' => $levl ?: $levs[0], 'ip' => $ip, 'from' => $from, 'to' => $to, 'event' => $isadm ? ($event === '' ? 'action' : $event) : '',
            'modul' => $modul, 'result' => $res], static fn($v) => $v !== '' && $v !== 'all');
        $lim = getListLimit(50, fn(): int => Logger::getEntries($chans[$file], $filt, 1, 0)['total']);
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
            'hidden' => [['nameattr' => 'name', 'valueattr' => 'security'], ['nameattr' => 'op', 'valueattr' => 'logview'], ['nameattr' => 'file', 'valueattr' => $file]],
            'fields' => $fields,
        ]);
        $rows = [];
        foreach ($log['rows'] as $row) {
            $cells = [['is_col_date' => true, 'has_content_text' => true, 'content_text' => format_time((string)$row['ts'], _TIMESTRING)],
                ['is_col_status' => true, 'has_content_text' => true, 'content_text' => (string)$row['level']]];
            if ($isadm) {
                $login = ($row['event'] ?? '') === 'login';
                $who = $login ? (string)($row['login'] ?? '').(empty($row['uid']) ? '' : ' #'.intval($row['uid'])) : (string)($row['aname'] ?? '').' #'.intval($row['aid'] ?? 0);
                $op = (string)($row['op'] ?? '');
                $tid = intval($row['target'] ?? 0);
                $more = intval($row['num'] ?? 0) - 1;
                $cells[] = ['is_truncate' => true, 'has_content_text' => true, 'content_text' => trim($who)];
                $cells[] = ['has_content_text' => true, 'content_text' => $mods[$row['modul'] ?? ''] ?? (string)($row['modul'] ?? '')];
                $cells[] = ['has_content_text' => true, 'content_text' => $login ? _SEC_ENTRY : ($words[$op] ?? $op)];
                $cells[] = ['is_col_status' => true, 'has_content_text' => true, 'content_text' => $tid ? '#'.$tid.($more > 0 ? ' +'.$more : '') : ''];
                $cells[] = ['has_content_text' => true, 'content_text' => $results[$row['result'] ?? ''] ?? (string)($row['result'] ?? '')];
            } else {
                $cells[] = ['is_truncate' => true, 'has_content_text' => true, 'title_text' => (string)$row['msg'], 'content_text' => (string)$row['msg']];
            }
            $cells[] = ['content_html' => Geoip::getIpHtml((string)($row['ip'] ?? ''))];
            $rows[] = ['cells' => $cells];
        }
        $head = $isadm ? [['content' => _ADMIN, 'is_truncate' => true], ['content' => _MODUL], ['content' => _CHECKOP], ['content' => _SEC_OBJECT, 'is_col_status' => true],
            ['content' => _STATUS]] : [['content' => _MESSAGE, 'is_truncate' => true]];
        $head = array_merge([['content' => _DATE, 'is_col_date' => true], ['content' => _SEC_LEVEL, 'is_col_status' => true]], $head, [['content' => _IP, 'is_col_date' => true]]);
        $link = 'name=security&op=logview&file='.$file.($event !== '' ? '&event='.$event : '').($modul !== '' ? '&modul='.$modul : '').($res !== '' ? '&res='.$res : '')
            .($levl !== '' ? '&level='.$levl : '').($ip !== '' ? '&ip='.urlencode($ip) : '').($from !== '' ? '&from='.$from : '').($to !== '' ? '&to='.$to : '').'&';
        setHead();
        echo getTplAdminList([
            'head_html' => getTplAdminTabs($tabset + ['part' => [0, 1, 2], 'subtitle_html' => $form]),
            'key' => 'security-'.$file,
            'note_html' => checkPerms(LOGS_DIR.'/'.$file.'.log').$tpl->getHtmlFrag('alert', ['text' => $labels[$file] ?? $file]),
            'is_fixed' => true,
            'head' => $head,
            'rows' => $rows,
            'href' => $afile.'.php?'.$link,
            'page' => $num,
            'pages' => $pages,
            'maxpg' => 10,
            'count' => $log['total'],
            'limit' => $lim,
            'base' => 50,
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
    $day = static fn(string $v): string => (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $v) && strtotime($v) !== false) ? $v : '';
    $uid = getVar('get', 'uid', 'num', 0);
    $side = getVar('get', 'side', 'word', '');
    $res = getVar('get', 'res', 'word', '');
    $way = getVar('get', 'way', 'word', '');
    $from = $day(getVar('get', 'from', 'text', ''));
    $to = $day(getVar('get', 'to', 'text', ''));
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
    $lim = getListLimit(50, fn(): int => getLoginLog($filt, 1, 0)['total']);
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
        'hidden' => [['nameattr' => 'name', 'valueattr' => 'security'], ['nameattr' => 'op', 'valueattr' => 'logins']],
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
            ['is_col_date' => true, 'has_content_text' => true, 'content_text' => format_time((string)$row['time'], _TIMESTRING)],
            ['is_truncate' => true, 'has_content_text' => true, 'content_text' => trim((string)$row['name'].' #'.intval($row['uid']))],
            ['has_content_text' => true, 'content_text' => $row['admin'] ? _ADMIN : _USER],
            ['has_content_text' => true, 'content_text' => $results[$row['result']] ?? (string)$row['result']],
            ['has_content_text' => true, 'content_text' => $ways[$row['way']] ?? (string)$row['way']],
            ['content_html' => Geoip::getIpHtml((string)$row['ip'])],
            ['is_truncate' => true, 'has_content_text' => true, 'title_text' => $agent, 'content_text' => $info['browser'].' / '.$info['os']],
        ]];
    }
    $link = 'name=security&op=logins'.($uid ? '&uid='.$uid : '').($side !== '' ? '&side='.$side : '').($res !== '' ? '&res='.$res : '')
        .($way !== '' ? '&way='.$way : '').($from !== '' ? '&from='.$from : '').($to !== '' ? '&to='.$to : '').'&';
    setHead();
    echo getTplAdminList([
        'head_html' => getTplAdminTabs($tabset + ['tab' => 1, 'part' => [0, 1, 2], 'subtitle_html' => $form]),
        'key' => 'security-logins',
        'head' => [['content' => _DATE, 'is_col_date' => true], ['content' => _ACCOUNT, 'is_truncate' => true], ['content' => _ADMIN.' / '._USER], ['content' => _STATUS],
            ['content' => _TYPE], ['content' => _IP], ['content' => _BROWSER, 'is_truncate' => true]],
        'rows' => $rows,
        'href' => $afile.'.php?'.$link,
        'page' => $num,
        'pages' => $pages,
        'maxpg' => 10,
        'count' => $log['total'],
        'limit' => $lim,
        'base' => 50,
    ]);
    setFoot();
}

function banlist(): void {
    global $conf, $afile, $tabset, $tpl;
    $time = getVar('req', 'time', 'num');
    $info = getVar('req', 'info', 'text');
    $hash = getVar('req', 'hash', 'text');
    $cidr = getVar('req', 'cidr', 'text', getVar('get', 'new_ip', 'text', ''));
    $user = getVar('get', 'kind', 'word', '') === 'user' || getVar('get', 'uname', 'name', '') !== '';
    $base = $afile.'.php?name=security&op=banlist&kind=';
    $subs = '';
    foreach (['ip' => _BANNED_IP, 'user' => _BANNED_USERS] as $key => $label) {
        $subs .= $tpl->getHtmlFrag('tabs-link', ['href' => $base.$key, 'hx_get' => $base.$key, 'is_active' => ($key === 'user') === $user, 'label' => $label, 'title' => $label]);
    }
    $note = checkPerms(CONFIG_DIR.'/security.php');
    if (getVar('get', 'send', 'var')) $note .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _MAIL_SEND]);
    $rows = [];
    if (!$user) {
        foreach (explode('||', (string)$conf['security']['blocker_ip']) as $val) {
            $binfo = explode('|', $val, 4);
            if ($val === '' || count($binfo) < 4) continue;
            $tcidr = getIpCidr($binfo[0]);
            if ($tcidr === false) continue;
            [$tip] = explode('/', $tcidr, 2);
            $reason = $tpl->getHtmlFrag('popover', ['items' => [
                ['label' => _IP_CIDR, 'has_value_text' => true, 'value_text' => $tcidr, 'is_last' => false],
                ['label' => _BANN_REAS, 'has_value_text' => true, 'value_text' => (string)$binfo[3], 'is_last' => true],
            ]]);
            $rows[] = ['name' => $tcidr, 'cells' => [
                ['is_truncate' => true, 'title_text' => $tcidr, 'content_html' => $reason.Geoip::getIpHtml($tip)],
                ['is_truncate' => true, 'title_text' => (string)$binfo[1], 'has_content_text' => true, 'content_text' => (string)$binfo[1]],
                ['is_col_date' => true, 'has_content_text' => true, 'content_text' => getTimeLeft((int)$binfo[2])],
                ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _EDITOR, 'dial' => [getTplPostAction(['name' => 'security',
                    'op' => 'bansave', 'cidr' => $tcidr, 'hash' => $binfo[1], 'time' => (int)$binfo[2], 'id' => 1], 'trash', _ONDELETE) + ['later' => 'gone del']]])],
            ]];
        }
        $head = [['content' => _IP, 'is_truncate' => true], ['content' => _HASH, 'is_truncate' => true]];
        $iprows = [
            [
                'label_for' => 'f-cidr',
                'label_html' => _IP_CIDR, 'hint_html' => _IP_CIDR_TIP, 'hint_id' => $hntid = getFieldIds('f-cidr')['hint'],
                'field_html' => $tpl->getHtmlFrag('textarea', ['describedby' => $hntid,
                    'name_attr' => 'cidr',
                    'input_id' => 'f-cidr',
                    'value_text' => $cidr,
                ]),
            ],
            [
                'label_for' => 'f-hash',
                'label_html' => _HASH,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'itype' => 'text',
                    'name_attr' => 'hash',
                    'input_id' => 'f-hash',
                    'placeholder_text' => _HASH,
                    'value_attr' => $hash,
                ]),
            ],
            [
                'label_for' => 'f-time',
                'label_html' => _TIME,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'is_required' => true,
                    'itype' => 'number',
                    'name_attr' => 'time',
                    'input_id' => 'f-time',
                    'placeholder_text' => _TIME,
                    'value_attr' => (string)$time,
                ]),
            ],
            [
                'label_for' => 'f-info',
                'label_html' => _BANN_REAS,
                'field_html' => $tpl->getHtmlFrag('textarea', [
                    'name_attr' => 'info',
                    'input_id' => 'f-info',
                    'value_text' => $info,
                ]),
            ],
        ];
        $form = $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php?name=security&op=bansave',
            'hidden' => [
                ['nameattr' => 'id', 'valueattr' => '2'],
                ['nameattr' => 'token', 'valueattr' => getSiteToken('security')],
            ],
            'rows' => $iprows,
            'submit_label' => _ADD,
        ]);
    } else {
        foreach (explode('||', (string)$conf['security']['blocker_user']) as $val) {
            if ($val === '') continue;
            $binfo = explode('|', $val);
            $rows[] = ['name' => (string)$binfo[0], 'cells' => [
                ['is_truncate' => true, 'title_text' => (string)$binfo[0], 'content_html' => user_info($binfo[0], null)],
                ['is_truncate' => true, 'title_text' => (string)($binfo[2] ?? ''), 'has_content_text' => true, 'content_text' => (string)($binfo[2] ?? '')],
                ['is_col_date' => true, 'has_content_text' => true, 'content_text' => getTimeLeft((int)($binfo[1] ?? 0))],
                ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _EDITOR, 'dial' => [getTplPostAction(['name' => 'security',
                    'op' => 'bansave', 'uname' => (string)$binfo[0], 'time' => (int)($binfo[1] ?? 0), 'id' => 3], 'trash', _ONDELETE) + ['later' => 'gone del']]])],
            ]];
        }
        $head = [['content' => _NICKNAME, 'is_truncate' => true], ['content' => _BANN_REAS, 'is_truncate' => true]];
        $name = getVar('get', 'uname', 'name');
        $mailTextId = 'sl_form_security_mail';
        $check = '';
        $mailtext = replace_break(str_replace('[text]', _BANN_INFO."\n\n"._BANN_TERM.': [time]'."\n"._BANN_REAS.': [info]', $conf['mtemp']));
        $userrows = [
            [
                'label_html' => _NICKNAME,
                'field_html' => getTplUserSearchInput([
                    'input_id' => 'uname',
                    'list_id' => 'uname_list',
                    'maxlength' => 25,
                    'minlength' => (int)$conf['search']['slet'],
                    'name' => 'uname',
                    'tip' => sprintf(_USERSEARCHTIP, (int)$conf['search']['slet']),
                    'value' => $name,
                ]),
            ],
            [
                'label_for' => 'f-utime',
                'label_html' => _TIME,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'is_required' => true,
                    'itype' => 'number',
                    'name_attr' => 'time',
                    'input_id' => 'f-utime',
                    'placeholder_text' => _TIME,
                    'value_attr' => (string)$time,
                ]),
            ],
            [
                'label_for' => 'f-uinfo',
                'label_html' => _BANN_REAS,
                'field_html' => $tpl->getHtmlFrag('textarea', [
                    'input_attr' => 'placeholder="'._BANN_REAS.'" required',
                    'name_attr' => 'info',
                    'input_id' => 'f-uinfo',
                    'value_text' => $info,
                ]),
            ],
            [
                'label_for' => 'f-mail',
                'label_html' => _MAIL_SENDE,
                'field_html' => $tpl->getHtmlFrag('checkbox', [
                    'input_attr' => 'data-sl-toggle-control="'.$mailTextId.'"',
                    'is_checked' => $check !== '',
                    'name_attr' => 'mail',
                    'input_id' => 'f-mail',
                    'value_attr' => '1',
                ]),
            ],
            [
                'label_for' => 'f-mailtext',
                'label_html' => _MAIL_TEXT, 'hint_html' => _MAIL_INFO, 'hint_id' => 'f-mailtext-hint',
                'field_html' => $tpl->getHtmlPart('div', [
                    'content_html' => $tpl->getHtmlFrag('textarea', [
                        'name_attr' => 'mailtext',
                        'input_id' => 'f-mailtext',
                        'describedby' => 'f-mailtext-hint',
                        'rows_num' => 10,
                        'value_text' => $mailtext,
                    ]),
                    'id' => $mailTextId,
                    'is_collapsible' => true,
                ]),
                'is_full' => true,
            ],
        ];
        $form = $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php?name=security&op=bansave',
            'hidden' => [
                ['nameattr' => 'id', 'valueattr' => '4'],
                ['nameattr' => 'token', 'valueattr' => getSiteToken('security')],
            ],
            'rows' => $userrows,
            'submit_label' => _ADD,
        ]);
    }
    setHead();
    echo getTplAdminList([
        'head_html' => getTplAdminTabs($tabset + ['tab' => 2, 'part' => [0, 1, 2]]).$tpl->getHtmlPart('tabs', ['id' => 'sl-ban-kind', 'is_subtabs' => true, 'tabs_html' => $subs]),
        'note_html' => $note,
        'key' => 'security-ban-'.($user ? 'user' : 'ip'),
        'is_fixed' => true,
        'head' => array_merge($head, [['content' => _DATE, 'is_col_date' => true], ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true]]),
        'rows' => $rows,
        'count' => count($rows),
        'foot_html' => $tpl->getHtmlPart('box', ['content_html' => $form]),
    ]);
    setFoot();
}

function bansave(): void {
    global $db, $conf, $afile, $tpl, $prs, $mailer;
    $warn = !checkAdminPost('security');
    $send = '';
    $id = getVar('post', 'id', 'num');
    $done = false;
    if (!$warn) {
        $cidr = getVar('post', 'cidr', 'text');
        $name = getVar('post', 'uname', 'name');
        $mail = getVar('post', 'mail', 'bool');
        $info = trim(getVar('post', 'info', 'text'));
        $info = ($info) ? $info : _BANN_INFO;
        $mailtext = trim(getVar('post', 'mailtext', 'text'));
        $hash = getVar('post', 'hash', 'text', '0');
        $time = getVar('post', 'time', 'num');
        $cidr = $cidr ? getIpCidr($cidr) : '';
        $cont = $conf['security'];
        if ($id == 1 && $cidr) {
            $bip = explode('||', $cont['blocker_ip']);
            $new = '';
            foreach ($bip as $val) {
                if ($val == '') continue;
                $binfo = explode('|', $val, 4);
                if (count($binfo) < 4) continue;
                $tcidr = getIpCidr($binfo[0]);
                if ($tcidr === false) continue;
                if ($tcidr === $cidr && $binfo[1] === $hash && (int)$binfo[2] === (int)$time) continue;
                $new .= $val.'||';
            }
            $done = $new !== $cont['blocker_ip'];
            $cont['blocker_ip'] = $new;
        } elseif ($id == 2 && $cidr) {
            $time = (is_numeric($time)) ? time() + ($time * 86400) : time() + 2592000;
            $cont['blocker_ip'] .= $cidr.'|'.$hash.'|'.$time.'|'.$info.'||';
            $done = true;
        } elseif ($id == 3 && $name) {
            $left = (string)preg_replace('#'.preg_quote($name, '#').'\|'.$time.'\|(.*)\|\|#iU', '', $cont['blocker_user']);
            $done = $left !== $cont['blocker_user'];
            $cont['blocker_user'] = $left;
        } elseif ($id == 4 && $name) {
            $time = (is_numeric($time)) ? time() + ($time * 86400) : time() + 2592000;
            $cont['blocker_user'] .= $name.'|'.$time.'|'.$info.'||';
            $done = true;
            if ($mail) {
                [$mail_addr] = $db->getSqlRow($db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users WHERE name = :name', ['name' => $name]));
                $subject = $conf['sitename'].' - '._SECURITY;
                $msg = nl2br($prs->filterContent(str_replace('[time]', getTimeLeft($time), str_replace('[info]', $info, $mailtext)), false, 'all'), false);
                $mailer->addQueue(['kind' => 'security', 'email' => $mail_addr, 'title' => $subject, 'body' => $msg, 'sender' => $conf['adminmail'], 'prio' => 3]);
                $send = '&send=1';
            }
        }
        if ($done) setConfigFile('security.php', $cont);
    }
    $kind = ($id === 3 || $id === 4) ? 'user' : 'ip';
    setRedirect($afile.'.php?name=security&op=banlist&kind='.$kind.$send, false, 302, $warn ? _TOKENMISS : ($done ? '' : _ERROR), !$done);
}

function passwd(): void {
    global $conf, $afile, $tabset, $tpl;
    setHead();
    $cont = getTplAdminTabs($tabset + ['tab' => 3]);
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    $cont .= (!$conf['security']['login'] || !$conf['security']['password'])
        ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _SEC_AUTH_INFO])
        : $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SEC_AUTH_OK]);
    $rows = [[
        'label_for' => 'f-admin-ip',
        'label_html' => _SEC_ADMIN_IP, 'hint_html' => _IP_CIDR_TIP, 'hint_id' => $hntid = getFieldIds('f-admin-ip')['hint'],
        'field_html' => $tpl->getHtmlFrag('textarea', ['describedby' => $hntid,
            'name_attr' => 'admin_ip',
            'input_id' => 'f-admin-ip',
            'value_text' => $conf['security']['admin_ip'],
        ]),
    ]];
    if (!$conf['security']['login'] || !$conf['security']['password']) {
        $rows[] = [
            'label_for' => 'f-login',
            'label_html' => _SEC_LOGIN,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'login',
                'input_id' => 'f-login',
                'value_attr' => '',
            ]),
        ];
        $rows[] = [
            'label_for' => 'f-password',
            'label_html' => _SEC_PASSWORD,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'password',
                'input_id' => 'f-password',
                'value_attr' => '',
            ]),
        ];
        $hidden = [
            ['nameattr' => 'op', 'valueattr' => 'passsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('security')],
        ];
    } else {
        $hidden = [
            ['nameattr' => 'op', 'valueattr' => 'passsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('security')],
            ['nameattr' => 'login', 'valueattr' => ''],
            ['nameattr' => 'password', 'valueattr' => ''],
        ];
    }
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=security',
        'hidden' => $hidden,
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function passsave(): void {
    global $conf, $afile;
    $warn = !checkAdminPost('security');
    if (!$warn) {
        $protect = ["\r" => '', "\n" => '', ' ' => ''];
        $admin_ip = getVar('post', 'admin_ip', 'text');
        $login = getVar('post', 'login', 'text');
        $password = getVar('post', 'password', 'text');
        $xadmin_ip = strtr($admin_ip, $protect);
        $xlogin = empty($login) ? $conf['security']['login'] : password_hash($login, PASSWORD_DEFAULT);
        $xpassword = empty($password) ? $conf['security']['password'] : password_hash($password, PASSWORD_DEFAULT);
        $ips = [];
        foreach (explode(',', $xadmin_ip) as $val) {
            $val = trim($val);
            if ($val === '') continue;
            $cidr = getIpCidr($val);
            if ($cidr !== false) $ips[] = $cidr;
        }
        $cont = $conf['security'];
        $cont['admin_ip'] = implode(',', $ips);
        $cont['login'] = $xlogin;
        $cont['password'] = $xpassword;
        setConfigFile('security.php', $cont);
    }
    setRedirect($afile.'.php?name=security&op=passwd', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function config(): void {
    global $conf, $afile, $tabset, $tpl;
    setHead();
    $cont = getTplAdminTabs($tabset + ['tab' => 4]);
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    $ainfo = sprintf(_ADMIN_FILE_INFO, strtolower(getRandomString('10')));
    $floodhtml = $tpl->getHtmlFrag('select', [
        'name_attr' => 'flood',
        'is_config' => true,
        'options_html' =>
            $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _NO, 'is_selected' => $conf['security']['flood'] == 0])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _SFLOOD_1, 'is_selected' => $conf['security']['flood'] == 1])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _SFLOOD_2, 'is_selected' => $conf['security']['flood'] == 2])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _SFLOOD_3, 'is_selected' => $conf['security']['flood'] == 3]),
    ]);
    $errorhtml = $tpl->getHtmlFrag('select', [
        'name_attr' => 'error',
        'is_config' => true,
        'options_html' =>
            $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _NO, 'is_selected' => $conf['security']['error'] == 0])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _SEC_VIEW_1, 'is_selected' => $conf['security']['error'] == 1])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _SEC_VIEW_2, 'is_selected' => $conf['security']['error'] == 2]),
    ]);
    $rows = [
        ['label_html' => _SFLOOD, 'field_html' => $floodhtml],
        ['label_html' => _SEC_VIEW, 'field_html' => $errorhtml],
        [
            'label_for' => 'f-flood-t',
            'label_html' => _SFLOD_T,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'flood_t', 'input_id' => 'f-flood-t', 'value_attr' => $conf['security']['flood_t']]),
        ],
        [
            'label_for' => 'f-blocker-cookie',
            'label_html' => _SEC_COOKIE,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'blocker_cookie',
                'input_id' => 'f-blocker-cookie',
                'value_attr' => $conf['security']['blocker_cookie'],
            ]),
        ],
        [
            'label_for' => 'f-afile',
            'label_html' => _ADMIN_FILE,
            'hint_html' => $ainfo,
            'hint_id' => $hntid = getFieldIds('f-afile')['hint'],
            'field_html' => $tpl->getHtmlFrag('input', [
                'describedby' => $hntid,
                'itype' => 'text',
                'name_attr' => 'afile',
                'input_id' => 'f-afile',
                'value_attr' => $conf['security']['afile'],
            ]),
        ],
        [
            'label_for' => 'f-log-size',
            'label_html' => _SEC_LOG_SIZE,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'log_size', 'input_id' => 'f-log-size', 'value_attr' => $conf['security']['log_size']]),
        ],
        [
            'label_for' => 'f-idle',
            'label_html' => _SEC_IDLE,
            'hint_html' => _SEC_IDLE_ON.': '.intval(getAdminIdle() / 60),
            'hint_id' => $hntid = getFieldIds('f-idle')['hint'],
            'field_html' => $tpl->getHtmlFrag('input', [
                'describedby' => $hntid,
                'itype' => 'number',
                'name_attr' => 'idle',
                'input_id' => 'f-idle',
                'value_attr' => intval($conf['security']['idle'] / 60),
            ]),
        ],
        [
            'label_for' => 'f-sess-d',
            'label_html' => _SEC_LOG_DS,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'sess_d',
                'input_id' => 'f-sess-d',
                'value_attr' => intval($conf['security']['sess_d'] / 60),
            ]),
        ],
        [
            'label_for' => 'f-sess-b',
            'label_html' => _SEC_LOG_DB,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'sess_b',
                'input_id' => 'f-sess-b',
                'value_attr' => intval($conf['security']['sess_b'] / 60),
            ]),
        ],
        [
            'label_html' => _SEC_DB,
            'label_id' => $labid = getFieldIds('', 'log_b')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'log_b',
                'value' => $conf['security']['log_b'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_for' => 'f-dump-age',
            'label_html' => _SEC_DUMP_AGE,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'dump_age',
                'input_id' => 'f-dump-age',
                'value_attr' => intval($conf['security']['dump_age']),
            ]),
        ],
        [
            'label_html' => _SEC_STAT,
            'label_id' => $labid = getFieldIds('', 'error_log')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'error_log',
                'value' => $conf['security']['error_log'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _SEC_URL_GET,
            'label_id' => $labid = getFieldIds('', 'url_get')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'url_get',
                'value' => $conf['security']['url_get'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _SEC_URL_POST,
            'label_id' => $labid = getFieldIds('', 'url_post')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'url_post',
                'value' => $conf['security']['url_post'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _SEC_REF_POST,
            'label_id' => $labid = getFieldIds('', 'ref_post')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'ref_post',
                'value' => $conf['security']['ref_post'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _SEC_MAIL_SEND,
            'label_id' => $labid = getFieldIds('', 'mail')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'mail',
                'value' => $conf['security']['mail'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _SEC_MAIL_W_SEND,
            'label_id' => $labid = getFieldIds('', 'mail_w')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'mail_w',
                'value' => $conf['security']['mail_w'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _SEC_MAIL_D_SEND,
            'label_id' => $labid = getFieldIds('', 'mail_d')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'mail_d',
                'value' => $conf['security']['mail_d'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _SEC_HACK_STAT,
            'label_id' => $labid = getFieldIds('', 'write_h')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'write_h',
                'value' => $conf['security']['write_h'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _SEC_WARN_STAT,
            'label_id' => $labid = getFieldIds('', 'write_w')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'write_w',
                'value' => $conf['security']['write_w'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _SEC_LOG,
            'label_id' => $labid = getFieldIds('', 'log')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'log',
                'value' => $conf['security']['log'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _SEC_LOG_D,
            'label_id' => $labid = getFieldIds('', 'log_d')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'log_d',
                'value' => $conf['security']['log_d'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
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
        ],
        [
            'label_html' => _SEC_LOG_A,
            'label_id' => $labid = getFieldIds('', 'log_a')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'log_a',
                'value' => $conf['security']['log_a'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _SEC_LOG_U,
            'label_id' => $labid = getFieldIds('', 'log_u')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'log_u',
                'value' => $conf['security']['log_u'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _SEC_WARN_BLOCK,
            'label_id' => $labid = getFieldIds('', 'block')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'block',
                'value' => $conf['security']['block'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
    ];
    $cap = is_array($conf['security']['captcha'] ?? null) ? $conf['security']['captcha'] : [];
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    if (!Captcha::isStoreWritable()) {
        $rows[] = ['label_html' => _CAPTCHA, 'field_html' => $tpl->getHtmlFrag('alert', ['text' => _CAPTCHA_STORE_WARN, 'meta' => '', 'type' => 'warn', 'is_warn' => true])];
    }
    $rows[] = [
        'label_html' => _CAPTCHA_ACTIVE,
        'label_id' => $labid = getFieldIds('', 'cap_active')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'cap_active', 'value' => (string)(int)!empty($cap['active']), 'options' => $yesno]),
    ];
    $opts = $tpl->getHtmlFrag('select-option', ['value_attr' => 'altcha', 'label_text' => 'ALTCHA', 'is_selected' => (string)($cap['provider'] ?? 'altcha') === 'altcha']);
    $rows[] = [
        'label_for' => 'f-cap-provider',
        'label_html' => _CAPTCHA_PROVIDER,
        'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'cap_provider', 'selectid' => 'f-cap-provider', 'options_html' => $opts, 'is_config' => true]),
    ];
    $rows[] = [
        'label_html' => _CAPTCHA_REGISTER,
        'label_id' => $labid = getFieldIds('', 'cap_register')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'cap_register', 'value' => (string)(int)!empty($cap['register']), 'options' => $yesno]),
    ];
    $rows[] = [
        'label_html' => _CAPTCHA_CONTACT,
        'label_id' => $labid = getFieldIds('', 'cap_contact')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'cap_contact', 'value' => (string)(int)!empty($cap['contact']), 'options' => $yesno]),
    ];
    $rows[] = [
        'label_html' => _CAPTCHA_COMMENTS,
        'label_id' => $labid = getFieldIds('', 'cap_comments')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'cap_comments', 'value' => (string)(int)!empty($cap['comments']), 'options' => $yesno]),
    ];
    $capmode = [['value' => 'never', 'label' => _CAPTCHA_NEVER], ['value' => 'after-fail', 'label' => _CAPTCHA_AFTERFAIL], ['value' => 'always', 'label' => _CAPTCHA_ALWAYS]];
    $opts = '';
    foreach ($capmode as $item) {
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $item['value'],
            'label_text' => $item['label'],
            'is_selected' => (string)($cap['login_user'] ?? 'after-fail') === $item['value'],
        ]);
    }
    $rows[] = [
        'label_for' => 'f-cap-login-user',
        'label_html' => _CAPTCHA_LOGIN_USER,
        'hint_html' => _CAPTCHA_LOGIN_HINT,
        'hint_id' => $hntid = getFieldIds('f-cap-login-user')['hint'],
        'field_html' => $tpl->getHtmlFrag('select', [
            'describedby' => $hntid,
            'name_attr' => 'cap_login_user',
            'selectid' => 'f-cap-login-user',
            'options_html' => $opts,
            'is_config' => true,
        ]),
    ];
    $opts = '';
    foreach ($capmode as $item) {
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $item['value'],
            'label_text' => $item['label'],
            'is_selected' => (string)($cap['login_admin'] ?? 'always') === $item['value'],
        ]);
    }
    $rows[] = [
        'label_for' => 'f-cap-login-admin',
        'label_html' => _CAPTCHA_LOGIN_ADMIN,
        'hint_html' => _CAPTCHA_LOGIN_HINT,
        'hint_id' => $hntid = getFieldIds('f-cap-login-admin')['hint'],
        'field_html' => $tpl->getHtmlFrag('select', [
            'describedby' => $hntid,
            'name_attr' => 'cap_login_admin',
            'selectid' => 'f-cap-login-admin',
            'options_html' => $opts,
            'is_config' => true,
        ]),
    ];
    $opts = '';
    foreach ([['low', _CAPTCHA_LOW], ['normal', _CAPTCHA_NORMAL], ['high', _CAPTCHA_HIGH]] as $item) {
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $item[0],
            'label_text' => $item[1],
            'is_selected' => (string)($cap['difficulty'] ?? 'normal') === $item[0],
        ]);
    }
    $rows[] = [
        'label_for' => 'f-cap-difficulty',
        'label_html' => _CAPTCHA_DIFFICULTY,
        'hint_html' => _CAPTCHA_DIFFICULTY_HINT,
        'hint_id' => $hntid = getFieldIds('f-cap-difficulty')['hint'],
        'field_html' => $tpl->getHtmlFrag('select', [
            'describedby' => $hntid,
            'name_attr' => 'cap_difficulty',
            'selectid' => 'f-cap-difficulty',
            'options_html' => $opts,
            'is_config' => true,
        ]),
    ];
    $rows[] = [
        'label_for' => 'f-cap-ttl',
        'label_html' => _CAPTCHA_TTL,
        'hint_html' => _CAPTCHA_TTL_HINT,
        'hint_id' => $hntid = getFieldIds('f-cap-ttl')['hint'],
        'field_html' => $tpl->getHtmlFrag('input', [
            'describedby' => $hntid,
            'itype' => 'number',
            'name_attr' => 'cap_ttl',
            'input_id' => 'f-cap-ttl',
            'value_attr' => (string)($cap['ttl'] ?? 600),
            'is_config' => true,
        ]),
    ];
    $rows[] = [
        'label_html' => _SECRET_REGEN,
        'hint_html' => _SECRET_REGEN_HINT,
        'label_id' => ($fids = getFieldIds('', 'secret_regen'))['label'],
        'hint_id' => $fids['hint'],
        'field_html' => getTplRadioGroup(['describedby' => $fids['hint'], 'labelledby' => $fids['label'], 'name' => 'secret_regen', 'value' => '0', 'options' => $yesno]),
    ];
    $confv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'security'],
            ['nameattr' => 'op', 'valueattr' => 'configsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('security')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $confv]);
    setFoot();
}

function configsave(): void {
    global $conf, $afile;
    $warn = !checkAdminPost('security');
    $text = $warn ? _TOKENMISS : _SUCCSAVE;
    $tafile = ($conf['security']['afile']) ? $conf['security']['afile'] : 'admin';
    $nfile = $warn ? $tafile : getVar('post', 'afile', 'text');
    $move = $nfile !== $tafile;
    if ($move && (!preg_match('#^[a-z0-9_-]+$#D', $nfile) || file_exists(PUBLIC_DIR.'/'.$nfile.'.php') || !rename(PUBLIC_DIR.'/'.$tafile.'.php', PUBLIC_DIR.'/'.$nfile.'.php'))) {
        $warn = true;
        $text = _ADMIN_FILE_ERR;
    }
    if (!$warn) {
        $afile = $nfile;
        $flood_t = getVar('post', 'flood_t', 'num', '1');
        $log_size = getVar('post', 'log_size', 'num', '1048576');
        $idle = getVar('post', 'idle', 'num', 60) * 60;
        $sess_d = getVar('post', 'sess_d', 'num', 1440) * 60;
        $sess_b = getVar('post', 'sess_b', 'num', 1440) * 60;
        $rawskip = str_replace(["\r\n", "\r"], "\n", getVar('post', 'dump_skip', 'raw', ''));
        $lines = explode("\n", $rawskip);
        $dskip = [];
        foreach ($lines as $line) {
            $line = trim(str_replace('\\', '/', (string)$line));
            $line = preg_replace('#/+#', '/', $line);
            $line = preg_replace('#^\./#', '', (string)$line);
            $line = trim((string)$line, " \t\n\r\0\x0B");
            if ($line === '' || $line === '.' || $line === './') continue;
            if (str_contains($line, '..')) continue;
            if (!str_ends_with($line, '/')) $line .= '/';
            $dskip[] = $line;
        }
        $dskip = array_values(array_unique($dskip));
        $cont = [
            'flood' => getVar('post', 'flood', 'num'),
            'error' => getVar('post', 'error', 'num'),
            'flood_t' => $flood_t,
            'idle' => $idle,
            'blocker_cookie' => getVar('post', 'blocker_cookie', 'text'),
            'afile' => $afile,
            'log_size' => $log_size,
            'sess_d' => $sess_d,
            'sess_b' => $sess_b,
            'log_b' => getVar('post', 'log_b', 'num'),
            'dump_age' => max(1, intval(getVar('post', 'dump_age', 'num', 7))),
            'error_log' => getVar('post', 'error_log', 'num'),
            'url_get' => getVar('post', 'url_get', 'num'),
            'url_post' => getVar('post', 'url_post', 'num'),
            'ref_post' => getVar('post', 'ref_post', 'num'),
            'mail' => getVar('post', 'mail', 'num'),
            'mail_w' => getVar('post', 'mail_w', 'num'),
            'mail_d' => getVar('post', 'mail_d', 'num'),
            'write_h' => getVar('post', 'write_h', 'num'),
            'write_w' => getVar('post', 'write_w', 'num'),
            'log' => getVar('post', 'log', 'num'),
            'log_d' => getVar('post', 'log_d', 'num'),
            'dump_skip' => implode("\n", $dskip),
            'log_a' => getVar('post', 'log_a', 'num'),
            'log_u' => getVar('post', 'log_u', 'num'),
            'block' => getVar('post', 'block', 'num')
        ];
        $cont['blocker_ip'] = $conf['security']['blocker_ip'];
        $cont['blocker_user'] = $conf['security']['blocker_user'];
        $cont['admin_ip'] = $conf['security']['admin_ip'];
        $cont['login'] = $conf['security']['login'];
        $cont['password'] = $conf['security']['password'];
        $master = (string)($conf['security']['secret'] ?? '');
        $cont['secret'] = ($master === '' || getVar('post', 'secret_regen', 'num', 0)) ? bin2hex(random_bytes(32)) : $master;
        $cont['captcha'] = [
            'active' => getVar('post', 'cap_active', 'num', 0),
            'provider' => getVar('post', 'cap_provider', 'var', 'altcha'),
            'register' => getVar('post', 'cap_register', 'num', 0),
            'contact' => getVar('post', 'cap_contact', 'num', 0),
            'comments' => getVar('post', 'cap_comments', 'num', 0),
            'login_user' => getVar('post', 'cap_login_user', 'var', 'after-fail'),
            'login_admin' => getVar('post', 'cap_login_admin', 'var', 'always'),
            'ttl' => getVar('post', 'cap_ttl', 'num', 600),
            'difficulty' => getVar('post', 'cap_difficulty', 'var', 'normal'),
            'storage' => 'file',
        ];
        setConfigFile('security.php', $cont);
    }
    setRedirect($afile.'.php?name=security&op=config', false, 302, $text, $warn);
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
        setRedirect($afile.'.php?name=security');
    } else {
        setRedirect($afile.'.php?name=security');
    }
}

function delete(): void {
    global $afile;
    $warn = !checkAdminPost('security');
    $file = getVar('post', 'file', 'var');
    $path = LOGS_DIR.'/'.$file.'.log';
    $done = !$warn && $file && is_file($path) && unlink($path);
    setRedirect($afile.'.php?name=security', false, 302, $warn ? _TOKENMISS : ($done ? _SUCCDELETE : _ERROR), !$done);
}

switch ($op) {
    default: security(); break;
    case 'logview': logview(); break;
    case 'logins': logins(); break;
    case 'download': download(); break;
    case 'delete': delete(); break;
    case 'banlist': banlist(); break;
    case 'bansave': bansave(); break;
    case 'passwd': passwd(); break;
    case 'passsave': passsave(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
