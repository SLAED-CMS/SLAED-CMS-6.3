<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('clients')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=clients', 'name=clients&amp;op=add', 'name=clients&amp;op=info'];
    $lang = [_HOME, _ADD, _INFO];
    return getAdminTabs(_CLIENTSA, 'clients.png', '', $ops, $lang, [], [], $tab, $subtab);
}

function clients(): void {
    global $db, $afile, $stop;
    setHead();
    $cont = navi(0, 0, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _CERROR]);
    $result = $db->getSqlQuery('SELECT id, title, infotext, url, num, hits, prod_id, status FROM '.PREFIX_DB.'_clients_down');
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._CTITLE.'</th><th>'._CVERSION.'</th><th>'._CDATE.'</th><th>'._ID.'</th><th>'._CLOADS.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $title, $infotext, $url, $num, $hits, $prod, $status] = $db->getSqlRow($result)) {
            $act = ($status) ? 0 : 1;
            $time = (file_exists('uploads/clients/'.$url)) ? date(_TIMESTRING, filemtime('uploads/clients/'.$url)) : _NO_INFO;
            $cont .= '<tr>'
            .'<td>'.$id.'</td>'
            .'<td>'.$title.'</td>'
            .'<td>'.$num.'</td>'
            .'<td>'.$time.'</td>'
            .'<td>'.$prod.'</td>'
            .'<td>'.$hits.'</td>'
            .'<td>'.ad_status('', $status).'</td>'
            .'<td>'.add_menu(ad_status($afile.'.php?name=clients&amp;op=status&amp;id='.$id.'&amp;act='.$act, $status)
            .'||<a href="'.$afile.'.php?name=clients&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>'
            .'||<a href="'.$afile.'.php?name=clients&amp;op=del&amp;id='.$id.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>')
            .'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop;
    $id = getVar('req', 'id', 'num');
    if ($id) {
        $result = $db->getSqlQuery('SELECT id, title, infotext, url, num, code, prod_id, status FROM '.PREFIX_DB.'_clients_down WHERE id = :id', ['id' => $id]);
        [$cid, $title, $infotext, $url, $num, $code, $prod, $status] = $db->getSqlRow($result);
    } else {
        $cid = getVar('post', 'cid', 'num');
        $title = getVar('post', 'title', 'title', '');
        $infotext = getVar('post', 'infotext', 'text', '');
        $url = getVar('post', 'url', 'text', '');
        $num = getVar('post', 'num', 'text', '');
        $code = getVar('post', 'code', 'text', '');
        $prod = getVar('post', 'prod', 'num', 0);
        $status = getVar('post', 'status', 'num', 0);
    }
    setHead();
    $cont = navi(0, 1, 0, 0);
    if ($stop) {
        $stopText = is_array($stop) ? implode('<br>', $stop) : $stop;
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stopText]);
    }
    if ($infotext) $cont .= preview($title, $infotext, '', '', 'all');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._CTITLE.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_form" placeholder="'._CTITLE.'" required></td></tr>'
    .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'infotext', $infotext, 'clients', '15', _TEXT, '1').'</td></tr>'
    .'<tr><td>'._CURL.':</td><td><input type="text" name="url" value="'.$url.'" maxlength="100" class="sl_form" placeholder="'._CURL.'"></td></tr>'
    .'<tr><td>'._CVERSION.':</td><td><input type="text" name="num" value="'.$num.'" maxlength="10" class="sl_form" placeholder="'._CVERSION.'"></td></tr>'
    .'<tr><td>'._CODE.':</td><td><input type="text" name="code" value="'.$code.'" maxlength="100" class="sl_form" placeholder="'._CODE.'"></td></tr>'
    .'<tr><td>'._ID.':</td><td><input type="number" name="prod" value="'.$prod.'" class="sl_form" placeholder="'._ID.'"></td></tr>'
    .'<tr><td>'._CADOWN.'</td><td>'.radio_form($status, 'status').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="clients">'.ad_save('cid', $cid, 'save').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $cid = getVar('post', 'cid', 'num', 0);
    $title = getVar('post', 'title', 'title', '');
    $infotext = getVar('post', 'infotext', 'text', '');
    $url = getVar('post', 'url', 'text', '');
    $num = getVar('post', 'num', 'text', '');
    $code = getVar('post', 'code', 'text', '');
    $prod = getVar('post', 'prod', 'num', 0);
    $status = getVar('post', 'status', 'num', 0);
    $stop = [];
    if (!$title) $stop[] = _CERROR;
    if (!$infotext) $stop[] = _CERROR1;
    $posttype = getVar('post', 'posttype', 'var', '');
    if (!$stop && $posttype === 'save') {
        if ($cid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_clients_down SET title = :title, infotext = :infotext, url = :url, num = :num, code = :code, prod_id = :prod_id, status = :status WHERE id = :id', ['title' => $title, 'infotext' => $infotext, 'url' => $url, 'num' => $num, 'code' => $code, 'prod_id' => $prod, 'status' => $status, 'id' => $cid]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_clients_down (title, infotext, url, num, code, hits, prod_id, status) VALUES (:title, :infotext, :url, :num, :code, :hits, :prod_id, :status)', ['title' => $title, 'infotext' => $infotext, 'url' => $url, 'num' => $num, 'code' => $code, 'hits' => 0, 'prod_id' => $prod, 'status' => $status]);
        }
        setRedirect($afile.'.php?name=clients');
    } elseif ($posttype === 'delete') {
        del($cid);
    } else {
        add();
    }
}

function del(int $id = 0): void {
    global $db, $afile;
    if (!$id) $id = getVar('req', 'id', 'num');
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_clients_down WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=clients');
}

function status(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    $act = getVar('get', 'act', 'num');
    if ($id) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_clients_down SET status = :status WHERE id = :id', ['status' => $act, 'id' => $id]);
    setRedirect($afile.'.php?name=clients');
}

function info(): void {
    setHead();
    echo navi(0, 2, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: clients(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'status': status(); break;
    case 'del': del(); break;
    case 'info': info(); break;
}



