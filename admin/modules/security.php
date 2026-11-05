<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

$tabset = [
    'ops' => ['name=security', 'name=security&op=passwd', 'name=security&op=oauthkeys', 'name=security&op=config', 'name=security&op=info'],
    'tabs' => [_BANNED, _SEC_PASS, _SEC_OAUTH, _PREFERENCES, _MANUAL],
];

function banlist(): void {
    global $conf, $afile, $tabset, $tpl;
    $time = getVar('req', 'time', 'num') ?: 30;
    $info = getVar('req', 'info', 'text');
    $hash = getVar('req', 'hash', 'text');
    $cidr = getVar('req', 'cidr', 'text', getVar('get', 'new_ip', 'text', ''));
    $user = getVar('get', 'kind', 'word', '') === 'user' || getVar('get', 'uname', 'name', '') !== '';
    $base = $afile.'.php?name=security&op=banlist&kind=';
    $subs = '';
    foreach (['ip' => _BANNED_IP, 'user' => _BANNED_USERS] as $key => $label) {
        $subs .= $tpl->getHtmlFrag('tabs-link', ['href' => $base.$key, 'hx_get' => $base.$key, 'is_active' => ($key === 'user') === $user, 'label' => $label, 'title' => $label]);
    }
    $note = checkPerms(CONFIG_DIR.'/security.php').getSelfCheckAlert();
    $rows = [];
    if (!$user) {
        foreach (explode('||', (string)$conf['security']['blocker_ip']) as $val) {
            $binfo = explode('|', $val, 4);
            if ($val === '' || count($binfo) < 4) continue;
            $tcidr = getIpCidr($binfo[0]);
            if ($tcidr === false) continue;
            [$tip] = explode('/', $tcidr, 2);
            $rows[] = ['name' => $tcidr, 'details' => [
                ['label' => _IP_CIDR, 'has_value_text' => true, 'value_text' => $tcidr],
                ['label' => _BANN_REAS, 'has_value_text' => true, 'value_text' => (string)$binfo[3]],
            ], 'drop' => getTplPostAction(['name' => 'security', 'op' => 'bansave', 'kind' => 'ip', 'id[]' => $tcidr.'|'.$binfo[1].'|'.(int)$binfo[2]], 'trash',
                _ONDELETE), 'cells' => [
                ['is_truncate' => true, 'title_text' => $tcidr, 'content_html' => Geoip::getIpHtml($tip)],
                ['is_truncate' => true, 'title_text' => (string)$binfo[1], 'has_content_text' => true, 'content_text' => (string)$binfo[1]],
                ['is_col_date' => true, 'moment' => (int)$binfo[2]],
                ['is_col_actions' => true, 'dial' => []],
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
                    'input_attr' => 'min="0"',
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
                ['nameattr' => 'kind', 'valueattr' => 'ip'],
                ['nameattr' => 'token', 'valueattr' => getSiteToken('security')],
            ],
            'rows' => $iprows,
            'submit_label' => _ADD,
        ]);
    } else {
        foreach (explode('||', (string)$conf['security']['blocker_user']) as $val) {
            if ($val === '') continue;
            $binfo = explode('|', $val);
            $rows[] = ['name' => (string)$binfo[0],
                'drop' => getTplPostAction(['name' => 'security', 'op' => 'bansave', 'kind' => 'user', 'id[]' => $binfo[0].'|'.(int)($binfo[1] ?? 0)], 'trash', _ONDELETE),
                'cells' => [
                ['is_truncate' => true, 'title_text' => (string)$binfo[0], 'content_html' => user_info($binfo[0], null)],
                ['is_truncate' => true, 'title_text' => (string)($binfo[2] ?? ''), 'has_content_text' => true, 'content_text' => (string)($binfo[2] ?? '')],
                ['is_col_date' => true, 'moment' => (int)($binfo[1] ?? 0)],
                ['is_col_actions' => true, 'dial' => []],
            ]];
        }
        $head = [['content' => _NICKNAME, 'is_truncate' => true], ['content' => _BANN_REAS, 'is_truncate' => true]];
        $name = getVar('get', 'uname', 'name');
        $mailTextId = 'sl_form_security_mail';
        $check = '';
        $mailtext = replace_break(str_replace('[text]', _BANN_INFO."\n\n"._BANN_TERM.': [time]'."\n"._BANN_REAS.': [info]', $conf['mtemp']));
        $userrows = [
            [
                'label_for' => 'uname',
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
                    'input_attr' => 'min="0"',
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
                ['nameattr' => 'kind', 'valueattr' => 'user'],
                ['nameattr' => 'token', 'valueattr' => getSiteToken('security')],
            ],
            'rows' => $userrows,
            'submit_label' => _ADD,
        ]);
    }
    setHead();
    echo getTplAdminList([
        'head_html' => getTplAdminTabs($tabset + ['part' => [0]]).$tpl->getHtmlPart('tabs', ['id' => 'sl-ban-kind', 'is_subtabs' => true, 'tabs_html' => $subs]),
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
    global $db, $conf, $afile;
    $warn = !checkAdminPost('security');
    $kind = getVar('post', 'kind', 'word', '');
    $keys = getVar('post', 'id[]', 'raw');
    $done = false;
    $isban = false;
    $text = $warn ? _TOKENMISS : _ERROR;
    if (!$warn) {
        $cidr = getVar('post', 'cidr', 'text');
        $name = getVar('post', 'uname', 'name');
        $mail = getVar('post', 'mail', 'bool');
        $info = trim(getVar('post', 'info', 'text'));
        $info = ($info) ? $info : _BANN_INFO;
        $hash = getVar('post', 'hash', 'text', '0');
        $time = getVar('post', 'time', 'num');
        $cidr = $cidr ? getIpCidr($cidr) : '';
        $cont = $conf['security'];
        if ($kind === 'ip' && $keys) {
            $bip = explode('||', $cont['blocker_ip']);
            $new = '';
            foreach ($bip as $val) {
                if ($val == '') continue;
                $binfo = explode('|', $val, 4);
                if (count($binfo) < 4) continue;
                $tcidr = getIpCidr($binfo[0]);
                if ($tcidr === false || in_array($tcidr.'|'.$binfo[1].'|'.(int)$binfo[2], $keys, true)) continue;
                $new .= $val.'||';
            }
            $done = $new !== $cont['blocker_ip'];
            $cont['blocker_ip'] = $new;
        } elseif ($kind === 'ip' && $cidr) {
            $time = time() + (($time > 0) ? $time : 30) * 86400;
            $cont['blocker_ip'] = getBanList($cidr.'|'.$hash.'|'.$time.'|'.$info);
            $done = true;
        } elseif ($kind === 'user' && $keys) {
            $left = '';
            foreach (explode('||', (string)$cont['blocker_user']) as $val) {
                $binfo = explode('|', $val);
                if ($val === '' || in_array($binfo[0].'|'.(int)($binfo[1] ?? 0), $keys, true)) continue;
                $left .= $val.'||';
            }
            $done = $left !== $cont['blocker_user'];
            $cont['blocker_user'] = $left;
        } elseif ($kind === 'user' && $name) {
            $time = time() + (($time > 0) ? $time : 30) * 86400;
            $cont['blocker_user'] .= $name.'|'.$time.'|'.$info.'||';
            $done = true;
            $isban = true;
        }
        if ($done) {
            $done = setConfigFile('security.php', ['blocker_ip' => $cont['blocker_ip'], 'blocker_user' => $cont['blocker_user']], $conf['security']);
            $text = $done ? _SUCCSAVE : (getConfigJournal() ? _CONFIG_PENDING : _ERROR_UP);
        }
        if ($done && $isban && $mail) {
            $addr = (string)($db->getSqlRow($db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users WHERE name = :name', ['name' => $name]))[0] ?? '');
            if (addFormMail('security', $addr, _SECURITY, ['[time]' => getTimeLeft($time), '[info]' => $info])) $text = _MAIL_SEND;
        }
    }
    $kind = ($kind === 'user') ? 'user' : 'ip';
    setRedirect($afile.'.php?name=security&op=banlist&kind='.$kind, false, 302, $text, !$done);
}

function passwd(): void {
    global $conf, $afile, $tabset, $tpl;
    setHead();
    $cont = getTplAdminTabs($tabset + ['tab' => 1]);
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
    ], [
        'label_for' => 'f-proxy',
        'label_html' => _SEC_PROXY, 'hint_html' => _SEC_PROXY_TIP, 'hint_id' => $hntpx = getFieldIds('f-proxy')['hint'],
        'field_html' => $tpl->getHtmlFrag('textarea', ['describedby' => $hntpx,
            'name_attr' => 'proxy',
            'input_id' => 'f-proxy',
            'value_text' => (string)($conf['security']['proxy'] ?? ''),
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
    $text = $warn ? _TOKENMISS : _SUCCSAVE;
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
        $hops = array_map('getIpCidr', preg_split('#[\s,]+#', getVar('post', 'proxy', 'text', ''), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $cont = ['proxy' => implode(',', array_filter($hops)), 'admin_ip' => implode(',', $ips), 'login' => $xlogin, 'password' => $xpassword];
        $warn = !setConfigFile('security.php', $cont, $conf['security']);
        if ($warn) $text = getConfigJournal() ? _CONFIG_PENDING : _ERROR_UP;
    }
    setRedirect($afile.'.php?name=security&op=passwd', false, 302, $text, $warn);
}

function oauthkeys(): void {
    global $conf, $afile, $tabset, $tpl;
    setHead();
    $cont = getTplAdminTabs($tabset + ['tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/oauth.php');
    $rows = [
        [
            'label_html' => _OAUTHACT,
            'label_id' => $labid = getFieldIds('', 'oactive')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'oactive',
                'value' => (string)($conf['oauth']['active'] ?? 0),
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _OAUTHGACT,
            'label_id' => $labid = getFieldIds('', 'gactive')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'gactive',
                'value' => (string)($conf['oauth']['google']['active'] ?? 0),
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_for' => 'f-gclientid',
            'label_html' => _OAUTHGID,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'gclientid',
                'input_id' => 'f-gclientid',
                'value_attr' => (string)($conf['oauth']['google']['clientid'] ?? ''),
                'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-gsecret',
            'label_html' => _OAUTHGKEY,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'gsecret',
                'input_id' => 'f-gsecret',
                'value_attr' => (string)($conf['oauth']['google']['secret'] ?? ''),
                'is_config' => true,
            ]),
        ],
        [
            'label_html' => _OAUTHMACT,
            'label_id' => $labid = getFieldIds('', 'mactive')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'mactive',
                'value' => (string)($conf['oauth']['microsoft']['active'] ?? 0),
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_for' => 'f-mclientid',
            'label_html' => _OAUTHMID,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'mclientid',
                'input_id' => 'f-mclientid',
                'value_attr' => (string)($conf['oauth']['microsoft']['clientid'] ?? ''),
                'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-msecret',
            'label_html' => _OAUTHMKEY,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'msecret',
                'input_id' => 'f-msecret',
                'value_attr' => (string)($conf['oauth']['microsoft']['secret'] ?? ''),
                'is_config' => true,
            ]),
        ],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'security'],
            ['nameattr' => 'op', 'valueattr' => 'oauthsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('security')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function oauthsave(): void {
    global $conf, $afile;
    $warn = !checkAdminPost('security');
    $text = $warn ? _TOKENMISS : _SUCCSAVE;
    if (!$warn) {
        $protect = ['\n' => '', '\t' => '', '\r' => '', ' ' => ''];
        $cont = [
            'active' => getVar('post', 'oactive', 'num'),
            'google' => [
                'active' => getVar('post', 'gactive', 'num'),
                'clientid' => strtr(getVar('post', 'gclientid', 'text'), $protect),
                'secret' => strtr(getVar('post', 'gsecret', 'text'), $protect),
            ],
            'microsoft' => [
                'active' => getVar('post', 'mactive', 'num'),
                'clientid' => strtr(getVar('post', 'mclientid', 'text'), $protect),
                'secret' => strtr(getVar('post', 'msecret', 'text'), $protect),
            ],
        ];
        $warn = !setConfigFile('oauth.php', $cont, $conf['oauth'] ?? []);
        if ($warn) $text = getConfigJournal() ? _CONFIG_PENDING : _ERROR_UP;
    }
    setRedirect($afile.'.php?name=security&op=oauthkeys', false, 302, $text, $warn);
}

function config(): void {
    global $conf, $afile, $tabset, $tpl;
    setHead();
    $cont = getTplAdminTabs($tabset + ['tab' => 3]);
    $cont .= checkPerms(CONFIG_DIR.'/security.php');
    $ainfo = sprintf(_ADMIN_FILE_INFO, strtolower(getRandomString('10')));
    $errorhtml = $tpl->getHtmlFrag('select', [
        'name_attr' => 'error',
        'selectid' => 'f-error',
        'is_config' => true,
        'options_html' =>
            $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _NO, 'is_selected' => $conf['security']['error'] == 0])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _SEC_VIEW_1, 'is_selected' => $conf['security']['error'] == 1])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _SEC_VIEW_2, 'is_selected' => $conf['security']['error'] == 2]),
    ]);
    $rows = [
        ['label_for' => 'f-error', 'label_html' => _SEC_VIEW, 'field_html' => $errorhtml],
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
        $master = (string)($conf['security']['secret'] ?? '');
        $cont = [
            'error' => getVar('post', 'error', 'num'),
            'idle' => getVar('post', 'idle', 'num', 60) * 60,
            'blocker_cookie' => getVar('post', 'blocker_cookie', 'text'),
            'afile' => $afile,
            'url_get' => getVar('post', 'url_get', 'num'),
            'url_post' => getVar('post', 'url_post', 'num'),
            'mail' => getVar('post', 'mail', 'num'),
            'mail_w' => getVar('post', 'mail_w', 'num'),
            'block' => getVar('post', 'block', 'num'),
            'secret' => ($master === '' || getVar('post', 'secret_regen', 'num', 0)) ? bin2hex(random_bytes(32)) : $master,
            'captcha' => [
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
            ],
        ];
        $warn = !setConfigFile('security.php', $cont, $conf['security']);
        if ($warn) $text = getConfigJournal() ? _CONFIG_PENDING : _ERROR_UP;
        if ($warn && $move && rename(PUBLIC_DIR.'/'.$nfile.'.php', PUBLIC_DIR.'/'.$tafile.'.php')) $afile = $tafile;
    }
    setRedirect($afile.'.php?name=security&op=config', false, 302, $text, $warn);
}

function info(): void {
    global $tabset;
    setTplAdminInfoPage($tabset);
}

switch ($op) {
    default: banlist(); break;
    case 'banlist': banlist(); break;
    case 'bansave': bansave(); break;
    case 'passwd': passwd(); break;
    case 'passsave': passsave(); break;
    case 'oauthkeys': oauthkeys(); break;
    case 'oauthsave': oauthsave(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
