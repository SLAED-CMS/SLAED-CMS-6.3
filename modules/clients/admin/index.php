<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('clients')) die('Illegal file access');

function clients(): void {
    global $db, $afile, $stop, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=clients', 'name=clients&amp;op=add', 'name=clients&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO]]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _CERROR]);
    $result = $db->getSqlQuery('SELECT id, title, body, url, num, hits, pid, status FROM '.PREFIX_DB.'_clients_down');
    if ($db->getSqlRowCount($result) > 0) {
        $head = $tpl->getHtmlFrag('admin-clients-list-head', [
            'date_label' => _CDATE,
            'functions_label' => _FUNCTIONS,
            'hits_label' => _CLOADS,
            'id_label' => _ID,
            'prod_label' => _ID,
            'status_label' => _STATUS,
            'title_label' => _CTITLE,
            'version_label' => _CVERSION,
        ]);
        $rows = '';
        while ([$id, $title, $body, $url, $num, $hits, $prod, $status] = $db->getSqlRow($result)) {
            $act = ($status) ? 0 : 1;
            $time = (file_exists('uploads/clients/'.$url)) ? date(_TIMESTRING, filemtime('uploads/clients/'.$url)) : _NO_INFO;
            $acts = adminMenuItems([
                ad_status($afile.'.php?name=clients&amp;op=status&amp;id='.$id.'&amp;act='.$act, $status),
                adminLinkAction($afile.'.php?name=clients&amp;op=add&amp;id='.$id, _FULLEDIT, _FULLEDIT),
                adminDeleteAction($afile.'.php?name=clients&amp;op=delete&amp;id='.$id, _DELETE.' "'.$title.'"?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getTplAdminTableRow($tpl->getHtmlFrag('admin-clients-list-row', [
                'actions_html' => $acts,
                'date_text' => $time,
                'hits_text' => (string)$hits,
                'id_text' => (string)$id,
                'prod_text' => (string)$prod,
                'status_html' => ad_status('', $status),
                'title_text' => $title,
                'version_text' => $num,
            ]));
        }
        $cont .= getTplAdminTable($head, $rows);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop, $tpl;
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
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stopText]);
    }
    if ($body) $cont .= preview($title, $body, '', '', 'all');
    $hide = getTplHiddenInput('name', 'clients');
    $rows = $tpl->getHtmlFrag('admin-clients-add-rows', [
        'body_html' => textarea('1', 'body', $body, 'clients', '15', _TEXT, '1'),
        'body_label' => _TEXT.':',
        'code_label' => _CODE.':',
        'code_value' => $code,
        'prod_label' => _ID.':',
        'prod_value' => (string)$prod,
        'save_html' => ad_save('cid', $cid, 'save'),
        'status_html' => radio_form($status, 'status'),
        'status_label' => _CADOWN,
        'title_label' => _CTITLE.':',
        'title_value' => $title,
        'url_label' => _CURL.':',
        'url_value' => $url,
        'version_label' => _CVERSION.':',
        'version_value' => $num,
    ]);
    $cont .= getTplAdminForm($afile.'.php', $rows, $hide);
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
    $cont = setAdminNavi(['ops' => ['name=clients', 'name=clients&amp;op=add', 'name=clients&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO], 'tab' => 2]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: clients(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'status': status(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
