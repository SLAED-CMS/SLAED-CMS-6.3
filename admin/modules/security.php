<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

$labels = [
    'database' => _SEC_STAT_DB,
    'dump' => _SEC_STAT_DUM,
    'dump_log' => _SEC_STAT_DUML,
    'error_php' => _SEC_STAT_ERROR_D,
    'error_file' => _SEC_STAT_ERROR_FILE,
    'error_site' => _SEC_STAT_ERROR_S,
    'error_sql' => _SEC_STAT_ERROR_SQL,
    'hack' => _SEC_STAT_HACK,
    'log' => _SEC_STAT_LOG,
    'log_admin' => _SEC_STAT_A,
    'log_user' => _SEC_STAT_U,
    'warn' => _SEC_STAT_WARN
];

function security(): void {
    global $afile, $labels, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=security', 'name=security&op=banlist', 'name=security&op=passwd', 'name=security&op=config', 'name=security&op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _DOCS]]);
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    $head = [
        ['content' => _TITLE],
        ['content' => _SIZE],
        ['content' => _DATE],
        ['content' => _FUNCTIONS, 'nosort' => true],
    ];
    $rows = '';
    $files = is_dir(LOGS_DIR) ? scandir(LOGS_DIR) : [];
    foreach ($files as $file) {
        if (preg_match('#(.*)\.log$#', $file)) {
            $name = (string)pathinfo($file, PATHINFO_FILENAME);
            $title = $labels[$name] ?? $name;
            $path = LOGS_DIR.'/'.$file;
            $filesize = filesize($path);
            $acts = $tpl->getHtmlFrag('popover', [
                'trigger_label' => _EDITOR,
                'items' => [
                    [
                        'href' => $afile.'.php?name=security&op=logview&file='.urlencode($name),
                        'label' => _DOCS,
                        'title' => _DOCS,
                    ],
                    [
                        'href' => $afile.'.php?name=security&op=download&file='.urlencode($name),
                        'label' => _DOWN,
                        'title' => _DOWN,
                    ],
                    [
                        'href' => $afile.'.php?name=security&op=delete&file='.urlencode($name).'&token='.getSiteToken(),
                        'label' => _ONDELETE,
                        'title' => _ONDELETE,
                        'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"',
                    ],
                ],
            ]);
            $titleHtml = $tpl->getHtmlFrag('popover', [
                'items' => [
                    ['label' => _FILE, 'value' => 'storage/logs/'.$file, 'is_last' => true],
                ],
            ]).$title;
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['is_truncate' => true, 'title_text' => $title, 'content_html' => $titleHtml],
                    ['content_html' => filterSize($filesize)],
                    ['content_html' => date(_TIMESTRING, filemtime($path))],
                    ['content_html' => $acts],
                ],
            ])]);
        }
    }
    $head[0]['is_truncate'] = true;
    $cont .= $tpl->getHtmlFrag('table', [
        'is_fixed' => true,'head' => $head, 'rows_html' => $rows]);
    echo $cont;
    setFoot();
}

function logview(): void {
    global $labels, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=security', 'name=security&op=banlist', 'name=security&op=passwd', 'name=security&op=config', 'name=security&op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _DOCS]]);
    $file = getVar('get', 'file', 'var');
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
            'lang' => 'text',
            'text' => $content,
        ])]);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function banlist(): void {
    global $conf, $afile, $tpl;
    $time = getVar('req', 'time', 'num');
    $info = getVar('req', 'info', 'text');
    $hash = getVar('req', 'hash', 'text');
    $cidr = getVar('req', 'cidr', 'text');
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=security', 'name=security&op=banlist', 'name=security&op=passwd', 'name=security&op=config', 'name=security&op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _DOCS], 'tab' => 1]);
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    if (getVar('get', 'send', 'var')) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _MAIL_SEND]);
    $tabone = '';
    $bip = explode('||', $conf['security']['blocker_ip']);
    if ($conf['security']['blocker_ip']) {
        $head = [
            ['content' => _IP],
            ['content' => _HASH],
            ['content' => _DATE],
            ['content' => _FUNCTIONS, 'nosort' => true],
        ];
        $rows = '';
        foreach ($bip as $val) {
            if ($val != '') {
                $binfo = explode('|', $val, 4);
                if (count($binfo) < 4) continue;
                $tcidr = getIpCidr($binfo[0]);
                if ($tcidr === false) continue;
                [$tip] = explode('/', $tcidr, 2);
                $reason = $tpl->getHtmlFrag('popover', [
                    'items' => [
                        ['label' => _IP_CIDR, 'has_value_text' => true, 'value_text' => $tcidr, 'is_last' => false],
                        ['label' => _BANN_REAS, 'has_value_text' => true, 'value_text' => (string)$binfo[3], 'is_last' => true],
                    ],
                ]).Geoip::getIpHtml($tip);
                $acts = $tpl->getHtmlFrag('popover', [
                    'trigger_label' => _EDITOR,
                    'items' => [[
                        'href' => $afile.'.php?name=security&op=bansave&cidr='.urlencode($tcidr).'&hash='.urlencode($binfo[1]).'&time='.(int)$binfo[2].'&id=1&token='.getSiteToken(),
                        'label' => _ONDELETE,
                        'title' => _ONDELETE,
                        'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars($tcidr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"',
                    ]],
                ]);
                $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                    'cells' => [
                        ['is_truncate' => true, 'title_text' => $tcidr, 'content_html' => $reason],
                        ['is_truncate' => true, 'title_text' => (string)$binfo[1], 'content_html' => $binfo[1]],
                        ['content_html' => getTimeLeft((int)$binfo[2])],
                        ['content_html' => $acts],
                    ],
                ])]);
            }
        }
        $head[0]['is_truncate'] = true;
        $head[1]['is_truncate'] = true;
        $tabone .= $tpl->getHtmlFrag('table', [
        'is_fixed' => true,'head' => $head, 'rows_html' => $rows]);
    }
    $iprows = [
        [
            'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _IP_CIDR, 'hint' => _IP_CIDR_TIP]),
            'field_html' => $tpl->getHtmlFrag('textarea', [
                'name_attr' => 'cidr',
                'value_text' => $cidr,
            ]),
        ],
        [
            'label_html' => _HASH,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'hash',
                'placeholder_text' => _HASH,
                'value_attr' => $hash,
            ]),
        ],
        [
            'label_html' => _TIME,
            'field_html' => $tpl->getHtmlFrag('input', [
                'is_required' => true,
                'itype' => 'number',
                'name_attr' => 'time',
                'placeholder_text' => _TIME,
                'value_attr' => (string)$time,
            ]),
        ],
        [
            'label_html' => _BANN_REAS,
            'field_html' => $tpl->getHtmlFrag('textarea', [
                'name_attr' => 'info',
                'value_text' => $info,
            ]),
        ],
    ];
    $tabone .= $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=security&op=bansave',
        'hidden' => [
            ['nameattr' => 'id', 'valueattr' => '2'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $iprows,
        'submit_label' => _ADD,
    ]);
    $tabtwo = '';
    $busers = explode('||', $conf['security']['blocker_user']);
    if ($conf['security']['blocker_user']) {
        $head = [
            ['content' => _NICKNAME],
            ['content' => _BANN_REAS],
            ['content' => _DATE],
            ['content' => _FUNCTIONS, 'nosort' => true],
        ];
        $rows = '';
        foreach ($busers as $val) {
            if ($val != '') {
                $binfo = explode('|', $val);
                $acts = $tpl->getHtmlFrag('popover', [
                    'trigger_label' => _EDITOR,
                    'items' => [[
                        'href' => $afile.'.php?name=security&op=bansave&name='.urlencode((string)$binfo[0]).'&time='.(int)($binfo[1] ?? 0).'&id=3&token='.getSiteToken(),
                        'label' => _ONDELETE,
                        'title' => _ONDELETE,
                        'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars((string)$binfo[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"',
                    ]],
                ]);
                $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                    'cells' => [
                        ['is_truncate' => true, 'title_text' => (string)$binfo[0], 'content_html' => user_info($binfo[0])],
                        ['is_truncate' => true, 'title_text' => (string)($binfo[2] ?? ''), 'has_content_text' => true, 'content_text' => (string)($binfo[2] ?? '')],
                        ['content_html' => getTimeLeft((int)($binfo[1] ?? 0))],
                        ['content_html' => $acts],
                    ],
                ])]);
            }
        }
        $head[0]['is_truncate'] = true;
        $head[1]['is_truncate'] = true;
        $tabtwo .= $tpl->getHtmlFrag('table', [
        'is_fixed' => true,'head' => $head, 'rows_html' => $rows]);
    }
    $name = getVar('get', 'uname', 'name');
    $mailTextId = 'sl_form_security_mail';
    $check = '';
    $mailtext = replace_break(str_replace('[text]', _BANN_INFO.PHP_EOL.PHP_EOL._BANN_TERM.': [time]'.PHP_EOL._BANN_REAS.': [info]', $conf['mtemp']));
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
            'label_html' => _TIME,
            'field_html' => $tpl->getHtmlFrag('input', [
                'is_required' => true,
                'itype' => 'number',
                'name_attr' => 'time',
                'placeholder_text' => _TIME,
                'value_attr' => (string)$time,
            ]),
        ],
        [
            'label_html' => _BANN_REAS,
            'field_html' => $tpl->getHtmlFrag('textarea', [
                'input_attr' => 'placeholder="'._BANN_REAS.'" required',
                'name_attr' => 'info',
                'value_text' => $info,
            ]),
        ],
        [
            'label_html' => _MAIL_SENDE,
            'field_html' => $tpl->getHtmlFrag('checkbox', [
                'input_attr' => 'data-sl-toggle-control="'.$mailTextId.'"',
                'is_checked' => $check !== '',
                'name_attr' => 'mail',
                'value_attr' => '1',
            ]),
        ],
        [
            'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _MAIL_TEXT, 'hint' => _MAIL_INFO]),
            'field_html' => $tpl->getHtmlPart('div', [
                'content_html' => $tpl->getHtmlFrag('textarea', [
                    'name_attr' => 'mailtext',
                    'rows_num' => 10,
                    'value_text' => $mailtext,
                ]),
                'id' => $mailTextId,
                'is_collapsible' => true,
            ]),
            'is_full' => true,
        ],
    ];
    $tabtwo .= $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=security&op=bansave',
        'hidden' => [
            ['nameattr' => 'id', 'valueattr' => '4'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $userrows,
        'submit_label' => _ADD,
    ]);
    $banv = $tpl->getHtmlPart('tabs', [
        'id' => 'securitys',
        'is_runtime' => true,
        'is_subtabs' => true,
        'tabs_html' =>
            $tpl->getHtmlFrag('tabs-link', [
                'href' => '#',
                'is_active' => true,
                'label' => _BANNED_IP,
                'rel' => 'security-sub-panel-0',
                'title' => _BANNED_IP,
            ])
            .$tpl->getHtmlFrag('tabs-link', [
                'href' => '#',
                'label' => _BANNED_USERS,
                'rel' => 'security-sub-panel-1',
                'title' => _BANNED_USERS,
            ]),
        'content_html' =>
            $tpl->getHtmlFrag('tabs-panel', [
                'panel_id' => 'security-sub-panel-0',
                'content_html' => $tabone,
            ])
            .$tpl->getHtmlFrag('tabs-panel', [
                'panel_id' => 'security-sub-panel-1',
                'content_html' => $tabtwo,
            ]),
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $banv]);
    setFoot();
}

function bansave(): void {
    global $db, $conf, $afile, $tpl, $prs;
    $warn = !checkSiteToken();
    $send = '';
    if (!$warn) {
        $id = getVar('req', 'id', 'num');
        $cidr = getVar('req', 'cidr', 'text');
        $name = getVar('post', 'uname', 'name', getVar('req', 'name', 'name'));
        $mail = getVar('post', 'mail', 'bool');
        $info = trim(getVar('post', 'info', 'text'));
        $info = ($info) ? $info : _BANN_INFO;
        $mailtext = trim(getVar('post', 'mailtext', 'text'));
        $hash = getVar('req', 'hash', 'text', '0');
        $time = getVar('req', 'time', 'num');
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
            $cont['blocker_ip'] = $new;
        } elseif ($id == 2 && $cidr) {
            $time = (is_numeric($time)) ? time() + ($time * 86400) : time() + 2592000;
            $cont['blocker_ip'] .= $cidr.'|'.$hash.'|'.$time.'|'.$info.'||';
        } elseif ($id == 3 && $name) {
            $blocker_user = preg_replace('#'.$name.'\|'.$time.'\|(.*)\|\|#iU', '', $cont['blocker_user']);
            $cont['blocker_user'] = $blocker_user;
        } elseif ($id == 4 && $name) {
            $time = (is_numeric($time)) ? time() + ($time * 86400) : time() + 2592000;
            $cont['blocker_user'] .= $name.'|'.$time.'|'.$info.'||';
            if ($mail) {
                [$mail_addr] = $db->getSqlRow($db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users WHERE name = :name', ['name' => $name]));
                $subject = $conf['sitename'].' - '._SECURITY;
                $msg = nl2br($prs->filterContent(str_replace('[time]', getTimeLeft($time), str_replace('[info]', $info, $mailtext)), false, 'all'), false);
                addMail($mail_addr, $conf['adminmail'], $subject, $msg, 0, 3);
                $send = '&send=1';
            }
        }
        setConfigFile('security.php', $cont);
    }
    setRedirect($afile.'.php?name=security&op=banlist'.$send, false, 302, $warn ? _TOKENMISS : '', $warn);
}

function passwd(): void {
    global $conf, $afile, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=security', 'name=security&op=banlist', 'name=security&op=passwd', 'name=security&op=config', 'name=security&op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _DOCS], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    $cont .= (!$conf['security']['login'] || !$conf['security']['password'])
        ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _SEC_AUTH_INFO])
        : $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _SEC_AUTH_OK]);
    $rows = [[
        'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _SEC_ADMIN_IP, 'hint' => _IP_CIDR_TIP]),
        'field_html' => $tpl->getHtmlFrag('textarea', [
            'name_attr' => 'admin_ip',
            'value_text' => $conf['security']['admin_ip'],
        ]),
    ]];
    if (!$conf['security']['login'] || !$conf['security']['password']) {
        $rows[] = [
            'label_html' => _SEC_LOGIN,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'login',
                'value_attr' => '',
            ]),
        ];
        $rows[] = [
            'label_html' => _SEC_PASSWORD,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'password',
                'value_attr' => '',
            ]),
        ];
        $hidden = [
            ['nameattr' => 'op', 'valueattr' => 'passsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ];
    } else {
        $hidden = [
            ['nameattr' => 'op', 'valueattr' => 'passsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
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
    $warn = !checkSiteToken();
    if (!$warn) {
        $protect = [PHP_EOL => '', ' ' => ''];
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
    global $conf, $afile, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=security', 'name=security&op=banlist', 'name=security&op=passwd', 'name=security&op=config', 'name=security&op=info'], 'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _DOCS], 'tab' => 3]);
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
        ['label_html' => _SFLOD_T, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'flood_t', 'value_attr' => $conf['security']['flood_t']])],
        ['label_html' => _SEC_COOKIE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'blocker_cookie', 'value_attr' => $conf['security']['blocker_cookie']])],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _ADMIN_FILE, 'hint' => $ainfo]), 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'afile', 'value_attr' => $conf['security']['afile']])],
        ['label_html' => _SEC_LOG_SIZE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'log_size', 'value_attr' => $conf['security']['log_size']])],
        ['label_html' => _SEC_LOG_DS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'sess_d', 'value_attr' => intval($conf['security']['sess_d'] / 60)])],
        ['label_html' => _SEC_LOG_DB, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'sess_b', 'value_attr' => intval($conf['security']['sess_b'] / 60)])],
        ['label_html' => _SEC_DB, 'field_html' => getTplRadioGroup(['name' => 'log_b', 'value' => $conf['security']['log_b'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _SEC_STAT, 'field_html' => getTplRadioGroup(['name' => 'error_log', 'value' => $conf['security']['error_log'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _SEC_URL_GET, 'field_html' => getTplRadioGroup(['name' => 'url_get', 'value' => $conf['security']['url_get'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _SEC_URL_POST, 'field_html' => getTplRadioGroup(['name' => 'url_post', 'value' => $conf['security']['url_post'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _SEC_REF_POST, 'field_html' => getTplRadioGroup(['name' => 'ref_post', 'value' => $conf['security']['ref_post'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _SEC_MAIL_SEND, 'field_html' => getTplRadioGroup(['name' => 'mail', 'value' => $conf['security']['mail'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _SEC_MAIL_W_SEND, 'field_html' => getTplRadioGroup(['name' => 'mail_w', 'value' => $conf['security']['mail_w'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _SEC_MAIL_D_SEND, 'field_html' => getTplRadioGroup(['name' => 'mail_d', 'value' => $conf['security']['mail_d'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _SEC_HACK_STAT, 'field_html' => getTplRadioGroup(['name' => 'write_h', 'value' => $conf['security']['write_h'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _SEC_WARN_STAT, 'field_html' => getTplRadioGroup(['name' => 'write_w', 'value' => $conf['security']['write_w'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _SEC_LOG, 'field_html' => getTplRadioGroup(['name' => 'log', 'value' => $conf['security']['log'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _SEC_LOG_D, 'field_html' => getTplRadioGroup(['name' => 'log_d', 'value' => $conf['security']['log_d'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _SEC_DUMP_SKIP, 'hint' => _SEC_DUMP_SKIP_INFO]), 'field_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'dump_skip', 'rows_num' => 8, 'value_text' => (string)($conf['security']['dump_skip'] ?? '')]), 'is_full' => true],
        ['label_html' => _SEC_LOG_A, 'field_html' => getTplRadioGroup(['name' => 'log_a', 'value' => $conf['security']['log_a'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _SEC_LOG_U, 'field_html' => getTplRadioGroup(['name' => 'log_u', 'value' => $conf['security']['log_u'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _SEC_WARN_BLOCK, 'field_html' => getTplRadioGroup(['name' => 'block', 'value' => $conf['security']['block'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
    ];
    $cap = is_array($conf['security']['captcha'] ?? null) ? $conf['security']['captcha'] : [];
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    if (!Captcha::isStoreWritable()) {
        $rows[] = ['label_html' => _CAPTCHA, 'field_html' => $tpl->getHtmlFrag('alert', ['text' => _CAPTCHA_STORE_WARN, 'meta' => '', 'type' => 'warn', 'is_warn' => true])];
    }
    $rows[] = ['label_html' => _CAPTCHA_ACTIVE, 'field_html' => getTplRadioGroup(['name' => 'cap_active', 'value' => (string)(int)!empty($cap['active']), 'options' => $yesno])];
    $opts = $tpl->getHtmlFrag('select-option', ['value_attr' => 'altcha', 'label_text' => 'ALTCHA', 'is_selected' => (string)($cap['provider'] ?? 'altcha') === 'altcha']);
    $rows[] = ['label_html' => _CAPTCHA_PROVIDER, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'cap_provider', 'options_html' => $opts, 'is_config' => true])];
    $rows[] = ['label_html' => _CAPTCHA_REGISTER, 'field_html' => getTplRadioGroup(['name' => 'cap_register', 'value' => (string)(int)!empty($cap['register']), 'options' => $yesno])];
    $rows[] = ['label_html' => _CAPTCHA_CONTACT, 'field_html' => getTplRadioGroup(['name' => 'cap_contact', 'value' => (string)(int)!empty($cap['contact']), 'options' => $yesno])];
    $rows[] = ['label_html' => _CAPTCHA_COMMENTS, 'field_html' => getTplRadioGroup(['name' => 'cap_comments', 'value' => (string)(int)!empty($cap['comments']), 'options' => $yesno])];
    $capmode = [['value' => 'never', 'label' => _CAPTCHA_NEVER], ['value' => 'after-fail', 'label' => _CAPTCHA_AFTERFAIL], ['value' => 'always', 'label' => _CAPTCHA_ALWAYS]];
    $opts = '';
    foreach ($capmode as $item) $opts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $item['value'], 'label_text' => $item['label'], 'is_selected' => (string)($cap['login_user'] ?? 'after-fail') === $item['value']]);
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAPTCHA_LOGIN_USER, 'hint' => _CAPTCHA_LOGIN_HINT]), 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'cap_login_user', 'options_html' => $opts, 'is_config' => true])];
    $opts = '';
    foreach ($capmode as $item) $opts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $item['value'], 'label_text' => $item['label'], 'is_selected' => (string)($cap['login_admin'] ?? 'always') === $item['value']]);
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAPTCHA_LOGIN_ADMIN, 'hint' => _CAPTCHA_LOGIN_HINT]), 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'cap_login_admin', 'options_html' => $opts, 'is_config' => true])];
    $opts = '';
    foreach ([['low', _CAPTCHA_LOW], ['normal', _CAPTCHA_NORMAL], ['high', _CAPTCHA_HIGH]] as $item) $opts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $item[0], 'label_text' => $item[1], 'is_selected' => (string)($cap['difficulty'] ?? 'normal') === $item[0]]);
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAPTCHA_DIFFICULTY, 'hint' => _CAPTCHA_DIFFICULTY_HINT]), 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'cap_difficulty', 'options_html' => $opts, 'is_config' => true])];
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CAPTCHA_TTL, 'hint' => _CAPTCHA_TTL_HINT]), 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'cap_ttl', 'value_attr' => (string)($cap['ttl'] ?? 600), 'is_config' => true])];
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _SECRET_REGEN, 'hint' => _SECRET_REGEN_HINT]), 'field_html' => getTplRadioGroup(['name' => 'secret_regen', 'value' => '0', 'options' => $yesno])];
    $confv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'security'],
            ['nameattr' => 'op', 'valueattr' => 'configsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $confv]);
    setFoot();
}

function configsave(): void {
    global $conf, $afile;
    $warn = !checkSiteToken();
    if (!$warn) {
        $flood_t = getVar('post', 'flood_t', 'num', '1');
        $afile = getVar('post', 'afile', 'text');
        $tafile = ($conf['security']['afile']) ? $conf['security']['afile'] : 'admin';
        if ($afile != $tafile) rename($tafile.'.php', $afile.'.php');
        $afile = (file_exists($afile.'.php')) ? $afile : $tafile;
        $log_size = getVar('post', 'log_size', 'num', '1048576');
        $sess_d = getVar('post', 'sess_d', 'num', 1440) * 60;
        $sess_b = getVar('post', 'sess_b', 'num', 1440) * 60;
        $rawskip = str_replace(["\r\n", "\r"], "\n", (string)getVar('post', 'dump_skip', 'raw', ''));
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
            'blocker_cookie' => getVar('post', 'blocker_cookie', 'text'),
            'afile' => $afile,
            'log_size' => $log_size,
            'sess_d' => $sess_d,
            'sess_b' => $sess_b,
            'log_b' => getVar('post', 'log_b', 'num'),
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
    setRedirect($afile.'.php?name=security&op=config', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=security', 'name=security&op=banlist', 'name=security&op=passwd', 'name=security&op=config', 'name=security&op=info'],
        'tabs' => [_HOME, _BANNED, _SEC_PASS, _PREFERENCES, _DOCS],
    ]);
}

function download(): void {
    global $afile;
    $file = getVar('get', 'file', 'var');
    if ($file) {
        $path = LOGS_DIR.'/'.$file.'.log';
        if (is_file($path)) {
            stream($path, date('d.m.Y').'_'.$file.'.log');
            return;
        }
        setRedirect($afile.'.php?name=security');
    } else {
        setRedirect($afile.'.php?name=security');
    }
}

function delete(): void {
    global $afile;
    $warn = !checkSiteToken();
    if (!$warn) {
        $file = getVar('get', 'file', 'var');
        if ($file) {
            $path = LOGS_DIR.'/'.$file.'.log';
            if (is_file($path)) unlink($path);
        }
    }
    setRedirect($afile.'.php?name=security', false, 302, $warn ? _TOKENMISS : _SUCCDELETE, $warn);
}

switch ($op) {
    default: security(); break;
    case 'logview': logview(); break;
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
