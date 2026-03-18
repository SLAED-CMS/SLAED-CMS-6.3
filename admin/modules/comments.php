<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function comments(): void {
    setHead();
    $status = getVar('get', 'status', 'num') ? 1 : 0;
    $cont = setAdminNavi(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=conf', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO], 'tab' => $status]);
    echo $cont.ashowcom();
    setFoot();
}

function edit(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    setHead();
    $cont = setAdminNavi(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=conf', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO]]);
    $result = $db->getSqlQuery('SELECT id, modul, body FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $id]);
    [$id, $modul, $com_text] = $db->getSqlRow($result);
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._COMMENT.':</td><td>'.textarea('1', 'comment', $com_text, $modul, '10', _COMMENT, '1').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="name" value="comments"><input type="hidden" name="op" value="editsave"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
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

function conf(): void {
    global $afile, $conf;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=conf', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/comments.php');
    $cont .= setTemplateBasic('open');
    $sval = (string)($conf['comments']['sort'] ?? '1');
    $sort_sel = '<select name="sort" class="sl_conf">'
        .'<option value="1"'.($sval === '1' ? ' selected' : '').'>'._ASC.'</option>'
        .'<option value="0"'.($sval === '0' ? ' selected' : '').'>'._DESC.'</option>'
        .'</select>';
    $lval = (string)($conf['comments']['link'] ?? '0');
    $link_sel = '<select name="link" class="sl_conf">'
        .'<option value="0"'.($lval === '0' ? ' selected' : '').'>'._NO.'</option>'
        .'<option value="1"'.($lval === '1' ? ' selected' : '').'>'._ANONIMP.'</option>'
        .'<option value="2"'.($lval === '2' ? ' selected' : '').'>'._ALLUSER.'</option>'
        .'</select>';
    $alval = (string)($conf['comments']['alink'] ?? '0');
    $alink_sel = '<select name="alink" class="sl_conf">'
        .'<option value="0"'.($alval === '0' ? ' selected' : '').'>'._NO.'</option>'
        .'<option value="1"'.($alval === '1' ? ' selected' : '').'>'._ANONIMP.'</option>'
        .'<option value="2"'.($alval === '2' ? ' selected' : '').'>'._ALLUSER.'</option>'
        .'</select>';
    $cont .= setTemplateBasic('form-conf', [
        '{%route%}'          => $afile,
        '{%module%}'         => 'comments',
        '{%op%}'             => 'save',
        '{%save%}'           => _SAVECHANGES,
        '{%fields%}'         => '',
        '{%_c33%}'           => _C_33,
        '{%num%}'            => $conf['comments']['num'],
        '{%_c34%}'           => _C_34,
        '{%anum%}'           => $conf['comments']['anum'],
        '{%_c35%}'           => _C_35,
        '{%nump%}'           => $conf['comments']['nump'],
        '{%_c36%}'           => _C_36,
        '{%anump%}'          => $conf['comments']['anump'],
        '{%_comletter%}'     => _COMLETTER,
        '{%letter%}'         => $conf['comments']['letter'],
        '{%_ceditt%}'        => _CEDITT,
        '{%edit%}'           => intval($conf['comments']['edit'] / 60),
        '{%_csend%}'         => _CSEND,
        '{%send%}'           => $conf['comments']['send'],
        '{%_sort%}'          => _SORT,
        '{%s_sort%}'         => $sort_sel,
        '{%_allowanonpost%}' => _ALLOWANONPOST,
        '{%s_anonpost%}'     => com_access('anonpost', $conf['comments']['anonpost'], 'sl_conf'),
        '{%_nolinkp%}'       => _NOLINKP,
        '{%_noaum%}'         => _NOAUM,
        '{%s_link%}'         => $link_sel,
        '{%_noalinkp%}'      => _NOALINKP,
        '{%s_alink%}'        => $alink_sel,
        '{%_addamail%}'      => _ADDAMAIL,
        '{%r_addmail%}'      => radio_form($conf['comments']['addmail'], 'addmail'),
        '{%_vprivat%}'       => _VPRIVAT,
        '{%r_privat%}'       => radio_form($conf['comments']['privat'], 'privat'),
        '{%_vprofil%}'       => _VPROFIL,
        '{%r_profil%}'       => radio_form($conf['comments']['profil'], 'profil'),
        '{%_vweb%}'          => _VWEB,
        '{%r_web%}'          => radio_form($conf['comments']['web'], 'web'),
        'if_flag'            => ['comments' => true],
    ]);
    $cont .= setTemplateBasic('close');
    echo $cont;
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
    setRedirect($afile.'.php?name=comments&op=conf');
}

function act(): void {
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

function del(): void {
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
    $cont = setAdminNavi(['ops' => ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=conf', 'name=comments&amp;op=info'], 'tabs' => [_HOME, _WAITINGCONT, _PREFERENCES, _INFO], 'tab' => 3]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: comments(); break;
    case 'edit': edit(); break;
    case 'editsave': editsave(); break;
    case 'conf': conf(); break;
    case 'save': save(); break;
    case 'act': act(); break;
    case 'del': del(); break;
    case 'info': info(); break;
}
