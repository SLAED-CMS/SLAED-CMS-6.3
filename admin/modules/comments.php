<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');

function comm_navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    panel();
    $ops = ['comm_show', 'comm_show&amp;status=1', 'comm_conf', 'comm_info'];
    $lang = [_HOME, _WAITINGCONT, _PREFERENCES, _INFO];
    return getAdminTabs(_COMMENTS, 'comments.png', '', $ops, $lang, [], [], $tab, $subtab);
}

function comm_show(): void {
    head();
    $id = getVar('get', 'status', 'num') ? 1 : 0;
    echo comm_navi(0, $id, 0, 0).ashowcom();
    foot();
}

function comm_edit(): void {
    global $db, $prefix, $admin_file;
    $id = getVar('get', 'id', 'num');
    head();
    $cont = comm_navi(0, 0, 0, 0);
    $result = $db->sql_query('SELECT id, modul, comment FROM '.$prefix.'_comment WHERE id = :id', ['id' => $id]);
    list($id, $modul, $com_text) = $db->sql_fetchrow($result);
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$admin_file.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._COMMENT.':</td><td>'.textarea('1', 'comment', $com_text, $modul, '10', _COMMENT, '1').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="op" value="comm_edit_save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function comm_edit_save(): void {
    global $prefix, $db, $admin_file;
    $id = getVar('post', 'id', 'num');
    $com_text = save_text($_POST['comment']);
    $db->sql_query('UPDATE '.$prefix.'_comment SET comment = :comment WHERE id = :id', ['comment' => $com_text, 'id' => $id]);
    header('Location: '.$admin_file.'.php?op=comm_show');
}

function comm_conf(): void {
    global $admin_file, $confc;
    head();
    $cont = comm_navi(0, 2, 0, 0);
    $cont .= checkConfigFile('comments.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$admin_file.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._C_33.':</td><td><input type="number" name="num" value="'.$confc['num'].'" class="sl_conf" placeholder="'._C_33.'" required></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.$confc['anum'].'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_35.':</td><td><input type="number" name="nump" value="'.$confc['nump'].'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.$confc['anump'].'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td>'._COMLETTER.':</td><td><input type="number" name="letter" value="'.$confc['letter'].'" class="sl_conf" placeholder="'._COMLETTER.'" required></td></tr>'
    .'<tr><td>'._CEDITT.':</td><td><input type="number" name="edit" value="'.intval($confc['edit'] / 60).'" class="sl_conf" placeholder="'._CEDITT.'" required></td></tr>'
    .'<tr><td>'._CSEND.':</td><td><input type="number" name="send" value="'.$confc['send'].'" class="sl_conf" placeholder="'._CSEND.'" required></td></tr>'
    .'<tr><td>'._SORT.':</td><td><select name="sort" class="sl_conf">'
    .'<option value="1"';
    if ($confc['sort'] == '1') $cont .= ' selected';
    $cont .= '>'._ASC.'</option>'
    .'<option value="0"';
    if ($confc['sort'] == '0') $cont .= ' selected';
    $cont .= '>'._DESC.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._ALLOWANONPOST.'</td><td>'.com_access('anonpost', $confc['anonpost'], 'sl_conf').'</td></tr>'
    .'<tr><td>'._NOLINKP.':<div class="sl_small">'._NOAUM.'</div></td><td><select name="link" class="sl_conf">'
    .'<option value="0"';
    if ($confc['link'] == '0') $cont .= ' selected';
    $cont .= '>'._NO.'</option>'
    .'<option value="1"';
    if ($confc['link'] == '1') $cont .= ' selected';
    $cont .= '>'._ANONIMP.'</option>'
    .'<option value="2"';
    if ($confc['link'] == '2') $cont .= ' selected';
    $cont .= '>'._ALLUSER.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._NOALINKP.':<div class="sl_small">'._NOAUM.'</div></td><td><select name="alink" class="sl_conf">'
    .'<option value="0"';
    if ($confc['alink'] == '0') $cont .= ' selected';
    $cont .= '>'._NO.'</option>'
    .'<option value="1"';
    if ($confc['alink'] == '1') $cont .= ' selected';
    $cont .= '>'._ANONIMP.'</option>'
    .'<option value="2"';
    if ($confc['alink'] == '2') $cont .= ' selected';
    $cont .= '>'._ALLUSER.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._ADDAMAIL.'</td><td>'.radio_form($confc['addmail'], 'addmail').'</td></tr>'
    .'<tr><td>'._VPRIVAT.'</td><td>'.radio_form($confc['privat'], 'privat').'</td></tr>'
    .'<tr><td>'._VPROFIL.'</td><td>'.radio_form($confc['profil'], 'profil').'</td></tr>'
    .'<tr><td>'._VWEB.'</td><td>'.radio_form($confc['web'], 'web').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="op" value="comm_save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function comm_save(): void {
    global $admin_file;
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
        'web' => getVar('post', 'web', 'num')
    ];
    setConfigFile('comments.php', 'confc', $cont);
    header('Location: '.$admin_file.'.php?op=comm_conf');
}

function comm_info(): void {
    head();
    echo comm_navi(0, 3, 0, 0).'<div id="repadm_info">'.adm_info(1, 0, 'comments').'</div>';
    foot();
}

switch($op) {
    case 'comm_show':
    comm_show();
    break;

    case 'comm_edit':
    comm_edit();
    break;

    case 'comm_edit_save':
    comm_edit_save();
    break;

    case 'comm_act':
    $get_id = getVar('get', 'id', 'num');
    $id = getVar('post', 'id[]', 'num') ?: ($get_id ? [$get_id] : []);
    if (is_array($id)) {
        foreach ($id as $val) {
            if (intval($val)) {
                list($cid, $mod, $uid, $status) = $db->sql_fetchrow($db->sql_query('SELECT cid, modul, uid, status FROM '.$prefix.'_comment WHERE id = :id', ['id' => $val]));
                if (!$status && $cid && $mod) {
                    $db->sql_query('UPDATE '.$prefix.'_comment SET status = \'1\' WHERE id = :id', ['id' => $val]);
                    numcom($cid, $mod, 0, $uid);
                }
            }
        }
    }
    referer($admin_file.'.php?op=comm_show');
    break;

    case 'comm_del':
    $get_id = getVar('get', 'id', 'num');
    $id = getVar('post', 'id[]', 'num') ?: ($get_id ? [$get_id] : []);
    if (is_array($id)) {
        foreach ($id as $val) {
            if (intval($val)) {
                list($cid, $mod, $uid, $status) = $db->sql_fetchrow($db->sql_query('SELECT cid, modul, uid, status FROM '.$prefix.'_comment WHERE id = :id', ['id' => $val]));
                if ($cid && $mod) {
                    $db->sql_query('DELETE FROM '.$prefix.'_comment WHERE id = :id', ['id' => $val]);
                    if ($status) numcom($cid, $mod, 1, $uid);
                }
            }
        }
    }
    referer($admin_file.'.php?op=comm_show');
    break;

    case 'comm_conf':
    comm_conf();
    break;

    case 'comm_save':
    comm_save();
    break;

    case 'comm_info':
    comm_info();
    break;
}