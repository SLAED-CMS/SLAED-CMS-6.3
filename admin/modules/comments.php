<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=comments', 'name=comments&amp;status=1', 'name=comments&amp;op=conf', 'name=comments&amp;op=info'];
    $lang = [_HOME, _WAITINGCONT, _PREFERENCES, _INFO];
    return getAdminTabs('', $ops, $lang, [], [], $tab, $subtab);
}

function comments(): void {
    setHead();
    $status = getVar('get', 'status', 'num') ? 1 : 0;
    echo navi(0, $status, 0, 0).ashowcom();
    setFoot();
}

function edit(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    setHead();
    $cont = navi(0, 0, 0, 0);
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
    $cont = navi(0, 2, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/comments.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._C_33.':</td><td><input type="number" name="num" value="'.$conf['comments']['num'].'" class="sl_conf" placeholder="'._C_33.'" required></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.$conf['comments']['anum'].'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_35.':</td><td><input type="number" name="nump" value="'.$conf['comments']['nump'].'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.$conf['comments']['anump'].'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td>'._COMLETTER.':</td><td><input type="number" name="letter" value="'.$conf['comments']['letter'].'" class="sl_conf" placeholder="'._COMLETTER.'" required></td></tr>'
    .'<tr><td>'._CEDITT.':</td><td><input type="number" name="edit" value="'.intval($conf['comments']['edit'] / 60).'" class="sl_conf" placeholder="'._CEDITT.'" required></td></tr>'
    .'<tr><td>'._CSEND.':</td><td><input type="number" name="send" value="'.$conf['comments']['send'].'" class="sl_conf" placeholder="'._CSEND.'" required></td></tr>'
    .'<tr><td>'._SORT.':</td><td><select name="sort" class="sl_conf">'
    .'<option value="1"';
    if ($conf['comments']['sort'] == '1') $cont .= ' selected';
    $cont .= '>'._ASC.'</option>'
    .'<option value="0"';
    if ($conf['comments']['sort'] == '0') $cont .= ' selected';
    $cont .= '>'._DESC.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._ALLOWANONPOST.'</td><td>'.com_access('anonpost', $conf['comments']['anonpost'], 'sl_conf').'</td></tr>'
    .'<tr><td>'._NOLINKP.':<div class="sl_small">'._NOAUM.'</div></td><td><select name="link" class="sl_conf">'
    .'<option value="0"';
    if ($conf['comments']['link'] == '0') $cont .= ' selected';
    $cont .= '>'._NO.'</option>'
    .'<option value="1"';
    if ($conf['comments']['link'] == '1') $cont .= ' selected';
    $cont .= '>'._ANONIMP.'</option>'
    .'<option value="2"';
    if ($conf['comments']['link'] == '2') $cont .= ' selected';
    $cont .= '>'._ALLUSER.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._NOALINKP.':<div class="sl_small">'._NOAUM.'</div></td><td><select name="alink" class="sl_conf">'
    .'<option value="0"';
    if ($conf['comments']['alink'] == '0') $cont .= ' selected';
    $cont .= '>'._NO.'</option>'
    .'<option value="1"';
    if ($conf['comments']['alink'] == '1') $cont .= ' selected';
    $cont .= '>'._ANONIMP.'</option>'
    .'<option value="2"';
    if ($conf['comments']['alink'] == '2') $cont .= ' selected';
    $cont .= '>'._ALLUSER.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._ADDAMAIL.'</td><td>'.radio_form($conf['comments']['addmail'], 'addmail').'</td></tr>'
    .'<tr><td>'._VPRIVAT.'</td><td>'.radio_form($conf['comments']['privat'], 'privat').'</td></tr>'
    .'<tr><td>'._VPROFIL.'</td><td>'.radio_form($conf['comments']['profil'], 'profil').'</td></tr>'
    .'<tr><td>'._VWEB.'</td><td>'.radio_form($conf['comments']['web'], 'web').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="comments"><input type="hidden" name="op" value="save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
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
    echo navi(0, 3, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
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
