<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('clients')) die('Illegal file access');

function clients(): void {
    global $db, $afile, $stop;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=clients', 'name=clients&amp;op=add', 'name=clients&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO]]);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _CERROR]);
    $result = $db->getSqlQuery('SELECT id, title, body, url, num, hits, pid, status FROM '.PREFIX_DB.'_clients_down');
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._CTITLE.'</th><th>'._CVERSION.'</th><th>'._CDATE.'</th><th>'._ID.'</th><th>'._CLOADS.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $title, $body, $url, $num, $hits, $prod, $status] = $db->getSqlRow($result)) {
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
            .'||<a href="'.$afile.'.php?name=clients&amp;op=delete&amp;id='.$id.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>')
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
        $result = $db->getSqlQuery('SELECT id, title, body, url, num, code, pid, status FROM '.PREFIX_DB.'_clients_down WHERE id = :id', ['id' => $id]);
        [$cid, $title, $body, $url, $num, $code, $prod, $status] = $db->getSqlRow($result);
    } else {
        $cid = getVar('post', 'cid', 'num');
        $title = getVar('post', 'title', 'title', '');
        $body = getVar('post', 'body', 'text', '');
        $url = getVar('post', 'url', 'text', '');
        $num = getVar('post', 'num', 'text', '');
        $code = getVar('post', 'code', 'text', '');
        $prod = getVar('post', 'prod', 'num', 0);
        $status = getVar('post', 'status', 'num', 0);
    }
    setHead();
    $cont = setAdminNavi(['ops' => ['name=clients', 'name=clients&amp;op=add', 'name=clients&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO], 'tab' => 1]);
    if ($stop) {
        $stopText = is_array($stop) ? implode('<br>', $stop) : $stop;
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stopText]);
    }
    if ($body) $cont .= preview($title, $body, '', '', 'all');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._CTITLE.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_form" placeholder="'._CTITLE.'" required></td></tr>'
    .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'body', $body, 'clients', '15', _TEXT, '1').'</td></tr>'
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
    $body = getVar('post', 'body', 'text', '');
    $url = getVar('post', 'url', 'text', '');
    $num = getVar('post', 'num', 'text', '');
    $code = getVar('post', 'code', 'text', '');
    $prod = getVar('post', 'prod', 'num', 0);
    $status = getVar('post', 'status', 'num', 0);
    $stop = [];
    if (!$title) $stop[] = _CERROR;
    if (!$body) $stop[] = _CERROR1;
    $posttype = getVar('post', 'posttype', 'var', '');
    if (!$stop && $posttype === 'save') {
        if ($cid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_clients_down SET title = :title, body = :body, url = :url, num = :num, code = :code, pid = :pid, status = :status WHERE id = :id', ['title' => $title, 'body' => $body, 'url' => $url, 'num' => $num, 'code' => $code, 'pid' => $prod, 'status' => $status, 'id' => $cid]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_clients_down (title, body, url, num, code, hits, pid, status) VALUES (:title, :body, :url, :num, :code, :hits, :pid, :status)', ['title' => $title, 'body' => $body, 'url' => $url, 'num' => $num, 'code' => $code, 'hits' => 0, 'pid' => $prod, 'status' => $status]);
        }
        setRedirect($afile.'.php?name=clients');
    } elseif ($posttype === 'delete') {
        delete($cid);
    } else {
        add();
    }
}

function delete(int $id = 0): void {
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
    $cont = setAdminNavi(['ops' => ['name=clients', 'name=clients&amp;op=add', 'name=clients&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO], 'tab' => 2]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: clients(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'status': status(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}



