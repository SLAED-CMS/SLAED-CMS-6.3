<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function comments(): void {
    global $conf, $db, $afile, $tpl;
    setHead();
    $status = getVar('get', 'status', 'num') ? 1 : 0;
    $modul = getVar('get', 'modul', 'var');
    $modlink = ($modul) ? '&amp;modul='.$modul : '';
    $options = $tpl->getHtmlFrag('new/select-option', [
        'value_attr' => '',
        'label_text' => _ALL,
        'is_selected' => $modul === '',
    ]);
    $result = $db->getSqlQuery('SELECT DISTINCT modul FROM '.PREFIX_DB.'_comment ORDER BY modul ASC');
    while ([$m] = $db->getSqlRow($result)) {
        if (!$m) continue;
        $options .= $tpl->getHtmlFrag('new/select-option', [
            'value_attr' => $m,
            'label_text' => getModuleName($m).' - '.$m,
            'is_selected' => $modul === $m,
        ]);
    }
    $subtitle = $tpl->getHtmlPart('searchbox', ['searchbox' => $tpl->getHtmlFrag('new/form', [
        'action_url' => $afile.'.php',
        'hidden' => array_values(array_filter([
            ['nameattr' => 'name', 'valueattr' => 'comments'],
            $status ? ['nameattr' => 'status', 'valueattr' => '1'] : null,
        ])),
        'content_html' => _MODUL.': '.$tpl->getHtmlFrag('new/select', [
            'name_attr' => 'modul',
            'select_attr' => ' OnChange="submit()"',
            'options_html' => $options,
        ]),
    ])]);
    $cont = getTplAdminTabs([
        'ops' => ['name=comments'.$modlink, 'name=comments&amp;status=1'.$modlink, 'name=comments&amp;op=config'.$modlink, 'name=comments&amp;op=info'.$modlink],
        'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO],
        'tab' => $status,
        'subtitle_html' => $subtitle,
    ]);
    $list = ashowcom();
    if (trim(strip_tags($list)) === '') {
        $list = $tpl->getHtmlFrag('new/alert', ['is_warn' => false, 'text' => _NO_INFO]);
    } else {
        $bulk = $tpl->getHtmlFrag('new/div', ['rows' => [[
            'label_html' => _CHECKOP.':',
            'field_html' => $tpl->getHtmlFrag('new/select', [
                'name_attr' => 'op',
                'options_html' => $tpl->getHtmlFrag('new/select-option', [
                    'value_attr' => 'approve',
                    'label_text' => $status ? _ACTIVATE : _DEACTIVATE,
                ]).$tpl->getHtmlFrag('new/select-option', [
                    'value_attr' => 'comm_del',
                    'label_text' => _DELETE,
                ]),
            ]).$tpl->getHtmlFrag('new/hidden', [
                'nameattr' => 'typ',
                'valueattr' => $status ? '1' : '0',
            ]).$tpl->getHtmlFrag('new/hidden', [
                'nameattr' => 'refer',
                'valueattr' => '1',
            ]).$tpl->getHtmlFrag('new/submit', [
                'submit_label' => _OK,
            ]),
        ]]]);
        $footer = $tpl->getHtmlFrag('new/module-foot', [
            'pager_html' => getTplPager([
                'field' => 'cid',
                'limit' => (int)($conf['comments']['anum'] ?? 25),
                'maxpg' => (int)($conf['comments']['anump'] ?? 8),
                'n' => 'com',
                'table' => '_comment',
                'url' => 'name=comments&amp;'.($status ? 'status=1&amp;' : '').($modul ? 'modul='.$modul.'&amp;' : ''),
                'where' => ($status ? 'status = 0' : 'status != 0').($modul ? ' AND modul = \''.$modul.'\'' : ''),
            ]),
            'select_html' => $bulk,
        ]);
        $list = $tpl->getHtmlPart('box', ['content_html' => $list.$footer]);
    }
    echo $cont.$list;
    setFoot();
}

function edit(): void {
    global $db, $afile, $tpl;
    $id = getVar('get', 'id', 'num');
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=config', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO]]);
    $result = $db->getSqlQuery('SELECT id, modul, body FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $id]);
    [$id, $modul, $com_text] = $db->getSqlRow($result);
    $rows = [[
        'label_html' => _COMMENT.':',
        'field_html' => $tpl->getHtmlFrag('new/textarea', [
            'name_attr' => 'comment',
            'rows_num' => 10,
            'value_text' => (string)$com_text,
        ]),
        'is_full' => true,
    ]];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('new/form', [
        'action_url' => $afile.'.php?name=comments&amp;op=editsave',
        'hidden' => [
            ['nameattr' => 'id', 'valueattr' => (string)$id],
            ['nameattr' => 'name', 'valueattr' => 'comments'],
            ['nameattr' => 'op', 'valueattr' => 'editsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function editsave(): void {
    global $db, $afile;
    $warn = !checkSiteToken();
    $id = getVar('post', 'id', 'num');
    $com_text = getVar('post', 'comment', 'text', '');
    if (!$warn) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET body = :comment WHERE id = :id', ['comment' => $com_text, 'id' => $id]);
    }
    setRedirect($afile.'.php?name=comments', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=config', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/comments.php');
    $rows = [
        ['label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => (string)$conf['comments']['num']])],
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)$conf['comments']['anum']])],
        ['label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'nump', 'value_attr' => (string)$conf['comments']['nump']])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)$conf['comments']['anump']])],
        ['label_html' => _COMLETTER, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'letter', 'value_attr' => (string)$conf['comments']['letter']])],
        ['label_html' => _CEDITT, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'edit', 'value_attr' => (string)intval($conf['comments']['edit'] / 60)])],
        ['label_html' => _CSEND, 'field_html' => $tpl->getHtmlFrag('new/input', ['itype' => 'number', 'name_attr' => 'send', 'value_attr' => (string)$conf['comments']['send']])],
        ['label_html' => _SORT, 'field_html' => $tpl->getHtmlFrag('new/select', ['name_attr' => 'sort', 'options_html' => $tpl->getHtmlFrag('new/select-option', ['value_attr' => '1', 'label_text' => _ASC, 'is_selected' => (string)($conf['comments']['sort'] ?? '1') === '1']).$tpl->getHtmlFrag('new/select-option', ['value_attr' => '0', 'label_text' => _DESC, 'is_selected' => (string)($conf['comments']['sort'] ?? '1') === '0'])])],
        ['label_html' => _ALLOWANONPOST, 'field_html' => com_access('anonpost', $conf['comments']['anonpost'], 'sl_conf')],
        ['label_html' => $tpl->getHtmlFrag('new/label-hint', ['label' => _NOLINKP, 'hint' => _NOAUM]), 'field_html' => $tpl->getHtmlFrag('new/select', ['name_attr' => 'link', 'options_html' => $tpl->getHtmlFrag('new/select-option', ['value_attr' => '0', 'label_text' => _NO, 'is_selected' => (string)($conf['comments']['link'] ?? '0') === '0']).$tpl->getHtmlFrag('new/select-option', ['value_attr' => '1', 'label_text' => _ANONIMP, 'is_selected' => (string)($conf['comments']['link'] ?? '0') === '1']).$tpl->getHtmlFrag('new/select-option', ['value_attr' => '2', 'label_text' => _ALLUSER, 'is_selected' => (string)($conf['comments']['link'] ?? '0') === '2'])])],
        ['label_html' => _NOALINKP, 'field_html' => $tpl->getHtmlFrag('new/select', ['name_attr' => 'alink', 'options_html' => $tpl->getHtmlFrag('new/select-option', ['value_attr' => '0', 'label_text' => _NO, 'is_selected' => (string)($conf['comments']['alink'] ?? '0') === '0']).$tpl->getHtmlFrag('new/select-option', ['value_attr' => '1', 'label_text' => _ANONIMP, 'is_selected' => (string)($conf['comments']['alink'] ?? '0') === '1']).$tpl->getHtmlFrag('new/select-option', ['value_attr' => '2', 'label_text' => _ALLUSER, 'is_selected' => (string)($conf['comments']['alink'] ?? '0') === '2'])])],
        ['label_html' => _ADDAMAIL, 'field_html' => getTplRadioGroup(['name' => 'addmail', 'value' => (string)(int)$conf['comments']['addmail'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _VPRIVAT, 'field_html' => getTplRadioGroup(['name' => 'privat', 'value' => (string)(int)$conf['comments']['privat'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _VPROFIL, 'field_html' => getTplRadioGroup(['name' => 'profil', 'value' => (string)(int)$conf['comments']['profil'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _VWEB, 'field_html' => getTplRadioGroup(['name' => 'web', 'value' => (string)(int)$conf['comments']['web'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
    ];
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('new/form', [
        'action_url' => $afile.'.php?name=comments&amp;op=save',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'comments'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    setFoot();
}

function save(): void {
    global $afile;
    $warn = !checkSiteToken();
    if (!$warn) {
        $cont = [
            'num' => getVar('post', 'num', 'num', 15),
            'anum' => getVar('post', 'anum', 'num', 15),
            'nump' => getVar('post', 'nump', 'num', 5),
            'anump' => getVar('post', 'anump', 'num', 5),
            'letter' => getVar('post', 'letter', 'num', 50),
            'edit' => getVar('post', 'edit', 'num', 600) * 60,
            'send' => getVar('post', 'send', 'num', 30),
            'sort' => getVar('post', 'sort', 'num'),
            'anonpost' => getVar('post', 'anonpost', 'num'),
            'link' => getVar('post', 'link', 'num'),
            'alink' => getVar('post', 'alink', 'num'),
            'addmail' => getVar('post', 'addmail', 'num'),
            'privat' => getVar('post', 'privat', 'num'),
            'profil' => getVar('post', 'profil', 'num'),
            'web' => getVar('post', 'web', 'num'),
        ];
        setConfigFile('comments.php', $cont);
    }
    setRedirect($afile.'.php?name=comments&op=config', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function approve(): void {
    global $db, $afile;
    $warn = !checkSiteToken();
    $typ = getVar('post', 'typ', 'num') ?: getVar('get', 'typ', 'num');
    $get_id = getVar('get', 'id', 'num');
    $id = getVar('post', 'id', 'num', []);
    if (!$id && $get_id) $id = [$get_id];
    if (!$warn && is_array($id)) {
        foreach ($id as $val) {
            if (intval($val)) {
                [$cid, $mod, $uid, $status] = $db->getSqlRow($db->getSqlQuery('SELECT cid, modul, uid, status FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $val]));
                if ($cid && $mod) {
                    $next = $typ ? 1 : 0;
                    if ((int)$status !== (int)$next) {
                        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET status = :status WHERE id = :id', ['status' => $next, 'id' => $val]);
                        numcom($cid, $mod, $typ ? 0 : 1, $uid);
                    }
                }
            }
        }
    }
    setRedirect($afile.'.php?name=comments'.($typ ? '' : '&status=1'), true, 302, $warn ? _TOKENMISS : _SUCCSTATUS, $warn);
}

function delete(): void {
    global $db, $afile;
    $warn = !checkSiteToken();
    $get_id = getVar('get', 'id', 'num');
    $id = getVar('post', 'id', 'num', []);
    if (!$id && $get_id) $id = [$get_id];
    if (!$warn && is_array($id)) {
        foreach ($id as $val) {
            if (intval($val)) {
                [$cid, $mod, $uid, $status] = $db->getSqlRow($db->getSqlQuery('SELECT cid, modul, uid, status FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $val]));
                if ($cid && $mod) {
                    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $val]);
                    if ($status) numcom($cid, $mod, 1, $uid);
                }
            }
        }
    }
    setRedirect($afile.'.php?name=comments', true, 302, $warn ? _TOKENMISS : _SUCCDELETE, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=config', 'name=comments&amp;op=info'],
        'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO],
    ]);
}

switch ($op) {
    default: comments(); break;
    case 'edit': edit(); break;
    case 'editsave': editsave(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'approve': approve(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
