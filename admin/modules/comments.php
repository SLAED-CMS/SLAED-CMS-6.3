<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function comments(): void {
    global $conf, $afile, $tpl;
    setHead();
    $status = getVar('get', 'status', 'num') ? 1 : 0;
    $cont = getTplAdminTabs(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=config', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO], 'tab' => $status]);
    $list = ashowcom();
    $list = preg_replace('~<table class="searchboxtab">.*?</table>~is', '', $list);
    if (trim(strip_tags($list)) === '') {
        $list = $tpl->getHtmlFrag('new/alert', ['is_warn' => false, 'text' => _NO_INFO]);
    } else {
        $bulk = $tpl->getHtmlFrag('comment-bulk-actions', [
            'label' => _CHECKOP,
            'activate_value' => 'comm_act',
            'activate_label' => _ACTIVATE,
            'delete_value' => 'comm_del',
            'delete_label' => _DELETE,
            'refer_value' => '1',
            'submit_label' => _OK,
        ]);
        $list = preg_replace('~<div class="searchbox">.*?</div>\s*</form>~is', $bulk.'</form>', $list, 1) ?? $list;
        if (!str_contains($list, $bulk)) $list = str_replace('</form>', $bulk.'</form>', $list);
        $list = str_replace('<div class="searchbox">'.$bulk.'</div>', $bulk, $list);
        $list = preg_replace('~<div class="searchbox">(.*)</div>\s*</form>~is', '$1</form>', $list, 1) ?? $list;
        $list .= getTplPager([
            'field' => 'cid',
            'limit' => (int)($conf['comments']['anum'] ?? 25),
            'maxpg' => (int)($conf['comments']['anump'] ?? 8),
            'n' => 'com',
            'table' => '_comment',
            'url' => 'name=comments&amp;'.($status ? 'status=1&amp;' : ''),
            'where' => $status ? 'status = 0' : 'status != 0',
        ]);
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
    global $db, $afile, $tpl;
    if (!checkSiteToken()) {
        setHead();
        $cont = getTplAdminTabs(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=config', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO]]);
        echo $cont.$tpl->getHtmlFrag('new/alert', ['is_warn' => true, 'text' => _TOKENMISS]);
        setFoot();
        return;
    }
    $id = getVar('post', 'id', 'num');
    $com_text = getVar('post', 'comment', 'text', '');
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET body = :comment WHERE id = :id', ['comment' => $com_text, 'id' => $id]);
    setRedirect($afile.'.php?name=comments');
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
    global $afile, $tpl;
    if (!checkSiteToken()) {
        setHead();
        $cont = getTplAdminTabs(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=config', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO], 'tab' => 2]);
        echo $cont.$tpl->getHtmlFrag('new/alert', ['is_warn' => true, 'text' => _TOKENMISS]);
        setFoot();
        return;
    }
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
    setRedirect($afile.'.php?name=comments&op=config');
}

function approve(): void {
    global $db, $afile;
    if (!checkSiteToken()) {
        setRedirect($afile.'.php?name=comments', true);
    }
    $get_id = getVar('get', 'id', 'num');
    $id = getVar('post', 'id', 'num', []);
    if (!$id && $get_id) $id = [$get_id];
    if (is_array($id)) {
        foreach ($id as $val) {
            if (intval($val)) {
                [$cid, $mod, $uid, $status] = $db->getSqlRow($db->getSqlQuery('SELECT cid, modul, uid, status FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $val]));
                if (!$status && $cid && $mod) {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET status = \'1\' WHERE id = :id', ['id' => $val]);
                    numcom($cid, $mod, 0, $uid);
                }
            }
        }
    }
    setRedirect($afile.'.php?name=comments', true);
}

function delete(): void {
    global $db, $afile;
    if (!checkSiteToken()) {
        setRedirect($afile.'.php?name=comments', true);
    }
    $get_id = getVar('get', 'id', 'num');
    $id = getVar('post', 'id', 'num', []);
    if (!$id && $get_id) $id = [$get_id];
    if (is_array($id)) {
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
    setRedirect($afile.'.php?name=comments', true);
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
