<?php
# Author: Eduard Laas
# Copyright (c) 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function filterAdminmods(array $mods): array {
    $list = [];
    global $conf;
    $allow = [];
    foreach ($conf['modules'] as $name => $info) {
        if ((int)($info['type'] ?? 1) !== 1) continue;
        if (!file_exists(BASE_DIR.'/modules/'.$name.'/admin/index.php')) continue;
        $allow[] = (string)$name;
    }
    sort($allow);
    foreach ($mods as $name) {
        $name = filterVar((string)$name);
        if ($name === '' || $name === '0') continue;
        if (!in_array($name, $allow, true)) continue;
        $list[] = $name;
    }
    $list = array_values(array_unique($list));
    sort($list);
    return $list;
}

function getAdminrow(int $aid): array {
    global $db;
    $row = $db->getSqlRow($db->getSqlQuery(
        'SELECT id, name, title, url, email, super, editor, smail, modules, lang FROM '.PREFIX_DB.'_admins WHERE id = :id',
        ['id' => $aid]
    ));
    return is_array($row) ? $row : [];
}

function checkAdminlast(int $aid): bool {
    global $db;
    [$super] = $db->getSqlRow($db->getSqlQuery('SELECT super FROM '.PREFIX_DB.'_admins WHERE id = :id', ['id' => $aid])) ?? [0];
    if ((int)$super !== 1) return false;
    [$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) FROM '.PREFIX_DB.'_admins WHERE super = 1')) ?? [0];
    return intval($count) <= 1;
}



function admins(): void {
    global $db, $afile, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=admins', 'name=admins&amp;op=add', 'name=admins&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO]]);
    $head = [
        ['content' => _NICKNAME],
        ['content' => _URANK],
        ['content' => _EMAIL],
        ['content' => _LANGUAGE],
        ['content' => _SUPERUSER],
        ['content' => _FUNCTIONS, 'nosort' => true],
    ];
    $rows = '';
    $result = $db->getSqlQuery(
        'SELECT id, name, title, email, lang, regdate, lastvis, super FROM '.PREFIX_DB.'_admins ORDER BY id'
    );
    while ([$aid, $name, $title, $email, $lang, $rdate, $vdate, $super] = $db->getSqlRow($result)) {
        $lang = $lang ? getLangName($lang) : _ALL;
        $show = htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
        $tip = $tpl->getHtmlFrag('title-tip', [
            'items' => [
                ['label' => _REG, 'value' => format_time((string)$rdate, _TIMESTRING), 'is_last' => false],
                ['label' => _LAST_VISIT, 'value' => format_time((string)$vdate, _TIMESTRING), 'is_last' => true],
            ],
        ]).$show;
        $acts = $tpl->getHtmlFrag('row-actions', [
            'trigger_label' => _EDITOR,
            'items' => [
                [
                    'href' => $afile.'.php?name=admins&amp;op=add&amp;id='.$aid,
                    'label' => _FULLEDIT,
                    'title' => _FULLEDIT,
                ],
                [
                    'href' => $afile.'.php?name=admins&amp;op=delete&amp;aid='.$aid.'&amp;token='.getSiteToken(),
                    'label' => _ONDELETE,
                    'title' => _ONDELETE,
                    'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars((string)$name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"',
                ],
            ],
        ]);
        $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['content_html' => $tip],
                ['content_html' => htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8')],
                ['content_html' => getMailLink($email)],
                ['content_html' => htmlspecialchars((string)$lang, ENT_QUOTES, 'UTF-8')],
                ['content_html' => ((int)$super === 1) ? _YES : _NO],
                ['content_html' => $acts],
            ],
        ])]);
    }
    $cont .= $tpl->getHtmlFrag('table', ['head' => $head, 'rows_html' => $rows]);
    echo $cont;
    setFoot();
}

function add(): void {
    global $afile, $conf, $tpl;
    $aid = getVar('req', 'id', 'num', 0);
    $stop = [];
    if ($aid) {
        $row = getAdminrow($aid);
        if (!$row) {
            setRedirect($afile.'.php?name=admins');
            return;
        }
        [$aid, $name, $title, $url, $email, $super, $editor, $smail, $mods, $lang] = $row;
        $mods = implode(',', filterAdminmods(getAdminModuleNames((string)$mods)));
    } else {
        $name = getVar('post', 'aname', 'name', '');
        $title = getVar('post', 'title', 'title', '');
        $url = getVar('post', 'url', 'url', 'https://');
        $email = getVar('post', 'email', 'email', '');
        $super = getVar('post', 'super', 'bool', 0) ? 1 : 0;
        $editor = getVar('post', 'editor', 'var', (string)($conf['editor']['admin'] ?? 'plain'));
        $smail = getVar('post', 'smail', 'bool', 0) ? 1 : 0;
        $mods = implode(',', filterAdminmods(getVar('post', 'modules[]', 'var', [])));
        $lang = getVar('post', 'lang', 'var', $conf['language']);
        $stop = $GLOBALS['stop'] ?? [];
    }
    if (!Editor::isValidEditor((string)$editor, 'admin')) $editor = (string)($conf['editor']['admin'] ?? 'plain');
    if (!Editor::isValidEditor((string)$editor, 'admin')) $editor = 'plain';
    $need = $aid ? '' : ' required';
    $check = '';
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=admins', 'name=admins&amp;op=add', 'name=admins&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => getStopText($stop)]);
    $items = '';
    $mods = getAdminModuleNames((string)$mods);
    $allow = [];
    foreach ($conf['modules'] as $mod => $info) {
        if ((int)($info['type'] ?? 1) !== 1) continue;
        if (!file_exists(BASE_DIR.'/modules/'.$mod.'/admin/index.php')) continue;
        $allow[] = (string)$mod;
    }
    sort($allow);
    foreach ($allow as $mod) {
        $items .= $tpl->getHtmlFrag('label-item', [
            'input_html' => $tpl->getHtmlFrag('checkbox', [
                'is_checked' => in_array($mod, $mods, true),
                'name_attr' => 'modules[]',
                'value_attr' => $mod,
            ]),
            'label_html' => $tpl->getHtmlFrag('title-tip', [
                'label_text' => getModuleName($mod),
                'title_text' => _MODUL.': '.$mod,
            ]),
        ]);
    }
    $perm = $tpl->getHtmlFrag('radio-group', ['items_html' => $items]);
    $mailtext = replace_break(str_replace('[text]', _FOLLOWINGMEM."\n\n"._NICKNAME.': [login]\n'._PASSWORD.': [pass]', $conf['mtemp']));
    $langv = $conf['multilingual'] == 1
        ? $tpl->getHtmlFrag('select', ['name_attr' => 'lang', 'options_html' => getTplLanguageOptions((string)$lang)])
        : '';
    $nameField = $aid
        ? $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'is_required' => true,
            'maxlength_num' => 25,
            'name_attr' => 'aname',
            'placeholder_text' => _NICKNAME,
            'value_attr' => (string)$name,
        ])
        : getTplUserSearchInput([
            'input_id' => 'aname',
            'list_id' => 'aname_list',
            'maxlength' => 25,
            'minlength' => (int)$conf['search']['slet'],
            'name' => 'aname',
            'tip' => sprintf(_USERSEARCHTIP, (int)$conf['search']['slet']),
            'value' => (string)$name,
        ]);
    $rows = [
        [
            'label_html' => _NICKNAME.':',
            'field_html' => $nameField,
        ],
        [
            'label_html' => _URANK.':',
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'maxlength_num' => 50,
                'name_attr' => 'title',
                'placeholder_text' => _URANK,
                'value_attr' => (string)$title,
            ]),
        ],
        [
            'label_html' => _EMAIL.':',
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'email',
                'is_required' => true,
                'maxlength_num' => 255,
                'name_attr' => 'email',
                'placeholder_text' => _EMAIL,
                'value_attr' => (string)$email,
            ]),
        ],
        [
            'label_html' => _URL.':',
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'url',
                'maxlength_num' => 255,
                'name_attr' => 'url',
                'placeholder_text' => _URL,
                'value_attr' => (string)$url,
            ]),
        ],
        [
            'label_html' => $aid
                ? $tpl->getHtmlFrag('label-hint', ['label' => _PASSWORD.':', 'hint' => _ADMINPASSKEEP])
                : _PASSWORD.':',
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'password',
                'name_attr' => 'pwd',
                'placeholder_text' => _PASSWORD,
                'input_attr' => $need,
                'value_attr' => '',
            ]),
        ],
        [
            'label_html' => _RETYPEPASSWORD.':',
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'password',
                'name_attr' => 'pwdtwo',
                'placeholder_text' => _RETYPEPASSWORD,
                'input_attr' => $need,
                'value_attr' => '',
            ]),
        ],
        [
            'label_html' => _SMAIL,
            'field_html' => getTplRadioGroup(['name' => 'smail', 'value' => (string)(int)$smail, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
        ],
        [
            'label_html' => _SUPERUSER,
            'field_html' => $tpl->getHtmlFrag('checkbox', [
                'is_checked' => (int)$super === 1,
                'name_attr' => 'super',
                'value_attr' => '1',
            ]),
        ],
        [
            'label_html' => _MAIL_SENDE,
            'field_html' => $tpl->getHtmlFrag('checkbox', [
                'input_attr' => 'data-sl-toggle-control="sl_form_admin_mail"',
                'is_checked' => $check !== '',
                'name_attr' => 'mail',
                'value_attr' => '1',
            ]),
        ],
        [
            'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _MAIL_TEXT, 'hint' => _MAIL_PASS_INFO]),
            'field_html' => $tpl->getHtmlFrag('div-collapse', [
                'content_html' => $tpl->getHtmlFrag('textarea', [
                    'name_attr' => 'mailtext',
                    'rows_num' => 10,
                    'value_text' => $mailtext,
                ]),
                'target_id' => 'sl_form_admin_mail',
            ]),
            'is_full' => true,
        ],
        [
            'label_html' => _EDITOR.':',
            'field_html' => Editor::getSelect('editor', (string)$editor, 'content', 'admin'),
        ],
    ];
    if ($conf['multilingual'] == 1) {
        $rows[] = [
            'label_html' => _LANGUAGE.':',
            'field_html' => $langv,
        ];
    }
    $rows[] = [
        'label_html' => _PERMISSIONS,
        'field_html' => $perm,
        'is_full' => true,
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('form', [
        'action_url' => $afile.'.php?name=admins&amp;op=save',
        'hidden' => [
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'aid', 'valueattr' => (string)$aid],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVE,
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $conf, $stop, $admin, $prs;
    $aid = getVar('post', 'aid', 'num', 0);
    $warn = !checkSiteToken();
    $name = getVar('post', 'aname', 'name', '');
    $title = getVar('post', 'title', 'title', '');
    $url = getVar('post', 'url', 'url', 'https://');
    $email = getVar('post', 'email', 'email', '');
    $pwd = getVar('post', 'pwd', 'raw', '');
    $ptwo = getVar('post', 'pwdtwo', 'raw', '');
    $lang = getVar('post', 'lang', 'var', $conf['language']);
    $mods = filterAdminmods(getVar('post', 'modules[]', 'var', []));
    $mods = $mods ? implode(',', $mods) : '';
    $super = getVar('post', 'super', 'bool', 0) ? 1 : 0;
    $edit = getVar('post', 'editor', 'var', (string)($conf['editor']['admin'] ?? 'plain'));
    $smail = getVar('post', 'smail', 'bool', 0) ? 1 : 0;
    $mail = getVar('post', 'mail', 'bool', 0) ? 1 : 0;
    $stop = [];
    if (!Editor::isValidEditor($edit, 'admin')) $edit = (string)($conf['editor']['admin'] ?? 'plain');
    if (!Editor::isValidEditor($edit, 'admin')) $edit = 'plain';
    if (!$aid && ($pwd === '' || $ptwo === '')) $stop[] = _NOPASS;
    if ($name) {
        [$adid, $aname] = $db->getSqlRow($db->getSqlQuery('SELECT id, name FROM '.PREFIX_DB.'_admins WHERE name = :name', ['name' => $name])) ?? [0, ''];
        if ($aid != $adid && $name === $aname) $stop[] = _USEREXIST;
        [$adid, $amail] = $db->getSqlRow($db->getSqlQuery('SELECT id, email FROM '.PREFIX_DB.'_admins WHERE email = :email', ['email' => $email])) ?? [0, ''];
        if ($aid != $adid && $email === $amail) $stop[] = _ERROR_EMAIL;
    } else {
        $stop[] = _ERROR_ALL;
    }
    if (!analyze_name($name)) $stop[] = _ERRORINVNICK;
    checkemail($email);
    if ($pwd !== $ptwo) $stop[] = _ERROR_PASS;
    $self = empty($admin[0]) ? 0 : intval(substr((string)$admin[0], 0, 11));
    if ($aid && $aid === $self && !$super) $stop[] = _ADMINSELFSUPER;
    if ($aid && !$super && checkAdminlast($aid)) $stop[] = _ADMINLASTSUPER;
    if ($warn) {
        setRedirect($afile.'.php?name=admins&op=add'.($aid ? '&id='.$aid : ''), false, 302, _TOKENMISS, true);
    }
    if (!$stop) {
        if ($aid) {
            if ($pwd !== '') {
                $pass = getPassHash($pwd);
                $db->getSqlQuery(
                    'UPDATE '.PREFIX_DB.'_admins SET name = :name, title = :title, url = :url, email = :email, password = :pass, super = :super, editor = :edit, smail = :smail, modules = :mods, lang = :lang WHERE id = :id',
                    ['name' => $name, 'title' => $title, 'url' => $url, 'email' => $email, 'pass' => $pass, 'super' => $super, 'edit' => $edit, 'smail' => $smail, 'mods' => $mods, 'lang' => $lang, 'id' => $aid]
                );
            } else {
                $db->getSqlQuery(
                    'UPDATE '.PREFIX_DB.'_admins SET name = :name, title = :title, url = :url, email = :email, super = :super, editor = :edit, smail = :smail, modules = :mods, lang = :lang WHERE id = :id',
                    ['name' => $name, 'title' => $title, 'url' => $url, 'email' => $email, 'super' => $super, 'edit' => $edit, 'smail' => $smail, 'mods' => $mods, 'lang' => $lang, 'id' => $aid]
                );
            }
        } else {
            $pass = getPassHash($pwd);
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_admins (name, title, url, email, password, super, editor, smail, modules, lang, regdate) VALUES (:name, :title, :url, :email, :pass, :super, :edit, :smail, :mods, :lang, now())',
                ['name' => $name, 'title' => $title, 'url' => $url, 'email' => $email, 'pass' => $pass, 'super' => $super, 'edit' => $edit, 'smail' => $smail, 'mods' => $mods, 'lang' => $lang]
            );
        }
        if ($mail) {
            $subj = $conf['sitename'].' - '._USERPASSWORD.' '.$name;
            $text = getVar('post', 'mailtext', 'text', '');
            $text = str_replace('[pass]', $pwd, str_replace('[login]', $name, $text));
            $text = $prs->filterContent($text, false, 'account');
            addMail($email, $conf['adminmail'], $subj, nl2br($text, false), 0, 3);
        }
        setRedirect($afile.'.php?name=admins', false, 302, $mail ? _MAIL_SEND : _SUCCSAVE);
    }
    add();
}

function delete(): void {
    global $db, $afile, $admin;
    $aid = getVar('req', 'aid', 'num', 0);
    if (!$aid) {
        setRedirect($afile.'.php?name=admins');
    }
    $warn = !checkSiteToken();
    $text = _SUCCDELETE;
    if (!$warn && $aid) {
        if ($aid === (empty($admin[0]) ? 0 : intval(substr((string)$admin[0], 0, 11)))) {
            $warn = true;
            $text = _ADMINSELFDEL;
        } elseif (checkAdminlast($aid)) {
            $warn = true;
            $text = _ADMINLASTSUPER;
        } else {
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_admins WHERE id = :id', ['id' => $aid]);
        }
    } else {
        $text = _TOKENMISS;
    }
    setRedirect($afile.'.php?name=admins', false, 302, $text, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=admins', 'name=admins&amp;op=add', 'name=admins&amp;op=info'],
        'tabs' => [_HOME, _ADD, _INFO],
    ]);
}

switch ($op) {
    default: admins(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
