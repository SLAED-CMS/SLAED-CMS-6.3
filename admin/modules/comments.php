<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function comments(): void {
    setHead();
    $status = getVar('get', 'status', 'num') ? 1 : 0;
    $cont = setAdminNavi(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=config', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO], 'tab' => $status]);
    echo $cont.ashowcom();
    setFoot();
}

function edit(): void {
    global $db, $afile, $tpl;
    $id = getVar('get', 'id', 'num');
    setHead();
    $cont = setAdminNavi(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=config', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO]]);
    $result = $db->getSqlQuery('SELECT id, modul, body FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $id]);
    [$id, $modul, $com_text] = $db->getSqlRow($result);
    $hide = '<input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="name" value="comments"><input type="hidden" name="op" value="editsave">';
    $rows = $tpl->getHtmlFrag('admin-comments-edit-rows', [
        'comment_html' => textarea('1', 'comment', $com_text, $modul, '10', _COMMENT, '1'),
        'save_label' => _SAVECHANGES,
    ]);
    $cont .= getAdminForm($afile.'.php', $rows, $hide);
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
    $cont = setAdminNavi(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=config', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/comments.php');
    $sval = (string)($conf['comments']['sort'] ?? '1');
    $sort_sel = getAdminSelect('sort',
        getAdminOption('1', _ASC, $sval === '1').getAdminOption('0', _DESC, $sval === '0'), 'sl_conf');
    $lval = (string)($conf['comments']['link'] ?? '0');
    $link_sel = getAdminSelect('link',
        getAdminOption('0', _NO, $lval === '0').getAdminOption('1', _ANONIMP, $lval === '1').getAdminOption('2', _ALLUSER, $lval === '2'), 'sl_conf');
    $alval = (string)($conf['comments']['alink'] ?? '0');
    $alink_sel = getAdminSelect('alink',
        getAdminOption('0', _NO, $alval === '0').getAdminOption('1', _ANONIMP, $alval === '1').getAdminOption('2', _ALLUSER, $alval === '2'), 'sl_conf');
    $confv = $tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'comments',
        'op' => 'save',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_c33' => _C_33,
        'num' => $conf['comments']['num'],
        '_c34' => _C_34,
        'anum' => $conf['comments']['anum'],
        '_c35' => _C_35,
        'nump' => $conf['comments']['nump'],
        '_c36' => _C_36,
        'anump' => $conf['comments']['anump'],
        '_comletter' => _COMLETTER,
        'letter' => $conf['comments']['letter'],
        '_ceditt' => _CEDITT,
        'edit' => intval($conf['comments']['edit'] / 60),
        '_csend' => _CSEND,
        'send' => $conf['comments']['send'],
        '_sort' => _SORT,
        's_sort' => $sort_sel,
        '_allowanonpost' => _ALLOWANONPOST,
        's_anonpost' => com_access('anonpost', $conf['comments']['anonpost'], 'sl_conf'),
        '_nolinkp' => _NOLINKP,
        '_noaum' => _NOAUM,
        's_link' => $link_sel,
        '_noalinkp' => _NOALINKP,
        's_alink' => $alink_sel,
        '_addamail' => _ADDAMAIL,
        'r_addmail' => radio_form($conf['comments']['addmail'], 'addmail'),
        '_vprivat' => _VPRIVAT,
        'r_privat' => radio_form($conf['comments']['privat'], 'privat'),
        '_vprofil' => _VPROFIL,
        'r_profil' => radio_form($conf['comments']['profil'], 'profil'),
        '_vweb' => _VWEB,
        'r_web' => radio_form($conf['comments']['web'], 'web'),
        'comments' => true,
    ]);
    echo $cont.getAdminBox($confv);
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
    setHead();
    $cont = setAdminNavi(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=config', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO], 'tab' => 3]);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
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
