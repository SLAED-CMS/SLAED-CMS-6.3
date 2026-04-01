<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function comments(): void {
    setHead();
    $status = getVar('get', 'status', 'num') ? 1 : 0;
    $cont = getTplAdminNavi(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=config', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO], 'tab' => $status]);
    echo $cont.ashowcom();
    setFoot();
}

function edit(): void {
    global $db, $afile, $tpl;
    $id = getVar('get', 'id', 'num');
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=config', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO]]);
    $result = $db->getSqlQuery('SELECT id, modul, body FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $id]);
    [$id, $modul, $com_text] = $db->getSqlRow($result);
    $hide = getTplHiddenInput('id', (string)$id).getTplHiddenInput('name', 'comments').getTplHiddenInput('op', 'editsave');
    $rows = $tpl->getHtmlFrag('admin-comments-edit-rows', [
        'comment_html' => textarea('1', 'comment', $com_text, $modul, '10', _COMMENT, '1'),
        'save_label' => _SAVECHANGES,
    ]);
    $cont .= getTplAdminForm($afile.'.php', $rows, $hide);
    echo $cont;
    setFoot();
}

function editsave(): void {
    global $db, $afile;
    $id = getVar('post', 'id', 'num');
    $com_text = getVar('post', 'comment', 'text', '');
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET body = :comment WHERE id = :id', ['comment' => $com_text, 'id' => $id]);
    setRedirect($afile.'.php?name=comments');
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=config', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/comments.php');
    $sval = (string)($conf['comments']['sort'] ?? '1');
    $sort_sel = getTplSelect('sort',
        getTplOption('1', _ASC, $sval === '1').getTplOption('0', _DESC, $sval === '0'), 'sl_conf');
    $lval = (string)($conf['comments']['link'] ?? '0');
    $link_sel = getTplSelect('link',
        getTplOption('0', _NO, $lval === '0').getTplOption('1', _ANONIMP, $lval === '1').getTplOption('2', _ALLUSER, $lval === '2'), 'sl_conf');
    $alval = (string)($conf['comments']['alink'] ?? '0');
    $alink_sel = getTplSelect('alink',
        getTplOption('0', _NO, $alval === '0').getTplOption('1', _ANONIMP, $alval === '1').getTplOption('2', _ALLUSER, $alval === '2'), 'sl_conf');
    $rows = [
        ['label_html' => _C_33, 'field_html' => getTplNumberInput((string)$conf['comments']['num'], 'num', 'sl_conf')],
        ['label_html' => _C_34, 'field_html' => getTplNumberInput((string)$conf['comments']['anum'], 'anum', 'sl_conf')],
        ['label_html' => _C_35, 'field_html' => getTplNumberInput((string)$conf['comments']['nump'], 'nump', 'sl_conf')],
        ['label_html' => _C_36, 'field_html' => getTplNumberInput((string)$conf['comments']['anump'], 'anump', 'sl_conf')],
        ['label_html' => _COMLETTER, 'field_html' => getTplNumberInput((string)$conf['comments']['letter'], 'letter', 'sl_conf')],
        ['label_html' => _CEDITT, 'field_html' => getTplNumberInput((string)intval($conf['comments']['edit'] / 60), 'edit', 'sl_conf')],
        ['label_html' => _CSEND, 'field_html' => getTplNumberInput((string)$conf['comments']['send'], 'send', 'sl_conf')],
        ['label_html' => _SORT, 'field_html' => $sort_sel],
        ['label_html' => _ALLOWANONPOST, 'field_html' => com_access('anonpost', $conf['comments']['anonpost'], 'sl_conf')],
        ['label_html' => getTplAdminHintLabel(_NOLINKP, _NOAUM), 'field_html' => $link_sel],
        ['label_html' => _NOALINKP, 'field_html' => $alink_sel],
        ['label_html' => _ADDAMAIL, 'field_html' => radio_form($conf['comments']['addmail'], 'addmail')],
        ['label_html' => _VPRIVAT, 'field_html' => radio_form($conf['comments']['privat'], 'privat')],
        ['label_html' => _VPROFIL, 'field_html' => radio_form($conf['comments']['profil'], 'profil')],
        ['label_html' => _VWEB, 'field_html' => radio_form($conf['comments']['web'], 'web')],
    ];
    $confv = $tpl->getHtmlFrag('config-div', [
        'action_url' => $afile.'.php',
        'hidden_html' => getTplHiddenInput('name', 'comments').getTplHiddenInput('op', 'save').getTplHiddenInput('token', getSiteToken()),
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.getTplBox($confv);
    setFoot();
}

function save(): void {
    global $afile;
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
    $cont = getTplAdminNavi(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=config', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO], 'tab' => 3]);
    setAdminInfoPage($cont);
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
