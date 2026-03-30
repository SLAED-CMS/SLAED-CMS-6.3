<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function messages(): void {
    global $db, $afile, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=messages', 'name=messages&amp;op=add', 'name=messages&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO]]);
    $result = $db->getSqlQuery('SELECT id, title, body, expire, status, view, lang FROM '.PREFIX_DB.'_message ORDER BY id');
    if ($db->getSqlRowCount($result) > 0) {
        $head = $tpl->getHtmlFrag('admin-messages-list-head', [
            'functions_label' => _FUNCTIONS,
            'id_label' => _ID,
            'lang_label' => _LANGUAGE,
            'purchased_label' => _PURCHASED,
            'status_label' => _STATUS,
            'title_label' => _TITLE,
            'view_label' => _VIEW,
        ]);
        $rows = '';
        while ([$mid, $title, $body, $expire, $active, $view, $lang] = $db->getSqlRow($result)) {
            if (($expire && $expire < time()) || (!$active && $expire)) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_message SET status = :active, expire = :expire WHERE id = :mid', ['active' => 0, 'expire' => 0, 'mid' => $mid]);
            $act = ($active) ? '0' : '1';
            if ($view == 1) {
                $mview = _MVALL;
            } elseif ($view == 2) {
                $mview = _MVANON;
            } elseif ($view == 3) {
                $mview = _MVUSERS;
            } elseif ($view == 4) {
                $mview = _MVADMIN;
            }
            $lang = (!$lang) ? _ALL : $lang;
            $exp = intval($expire - time());
            $exp = ($exp > 0) ? getDuration($exp) : _UNLIMITED;
            $acts = adminMenuItems([
                ad_status($afile.'.php?name=messages&amp;op=status&amp;id='.$mid.'&amp;act='.$act, $active),
                adminLinkAction($afile.'.php?name=messages&amp;op=add&amp;id='.$mid, _FULLEDIT, _FULLEDIT),
                adminDeleteAction($afile.'.php?name=messages&amp;op=delete&amp;id='.$mid, _DELETE.' "'.$title.'"?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getTplAdminTableRow($tpl->getHtmlFrag('admin-messages-list-row', [
                'actions_html' => $acts,
                'id_text' => (string)$mid,
                'lang_text' => getLangName($lang),
                'purchased_text' => $exp,
                'status_html' => ad_status('', $active),
                'title_attr' => $title,
                'title_text' => cutstr($title, 35),
                'view_text' => $mview,
            ]));
        }
        $cont .= getTplAdminTable($head, $rows);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $conf, $afile, $stop, $tpl;
    $mid = getVar('req', 'id', 'num');
    if ($mid) {
        [$title, $body, $expire, $active, $view, $lang] = $db->getSqlRow($db->getSqlQuery('SELECT title, body, expire, status, view, lang FROM '.PREFIX_DB.'_message WHERE id = :mid', ['mid' => $mid]));
    } else {
        $mid = getVar('post', 'mid', 'num');
        $title = getVar('post', 'title', 'title');
        $body = getVar('post', 'body', 'text');
        $newexpire = getVar('post', 'newexpire', 'num');
        $expire_input = getVar('post', 'expire', 'num');
        $expire = ($newexpire == 1 && $expire_input) ? time() + ($expire_input * 86400) : $expire_input;
        $active = getVar('post', 'status', 'num');
        $view = getVar('post', 'view', 'num');
        $lang = getVar('post', 'lang', 'var');
    }
    setHead();
    $cont = setAdminNavi(['ops' => ['name=messages', 'name=messages&amp;op=add', 'name=messages&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => $stop]);
    if ($body) $cont .= preview($title, $body, '', '', 'all');
    $hide = getTplHiddenInput('mid', (string)$mid).getTplHiddenInput('name', 'messages').getTplHiddenInput('op', 'save').getTplHiddenInput('posttype', 'save');
    $langsel = '';
    if ($conf['multilingual'] == 1) $langsel = getTplSelect('lang', language($lang), 'sl_form');
    if ($expire != 0) {
        $newexpire = 0;
        $oldexpire = $expire;
        $expire = intval($expire - time());
        $exp_day = $expire / 86400;
        $expire_text = getTplHiddenInput('expire', (string)$oldexpire)._PURCHASED.': '.getDuration($expire).' ('.round($exp_day, 3).' '._DAYS.')';
    } else {
        $newexpire = 1;
        $expire_text = getTplNumberInput(0, 'expire', 'sl_form', 'placeholder="'._EXPIRATION.'" required');
    }
    $privs = [_MVALL, _MVANON, _MVUSERS, _MVADMIN];
    $privopts = '';
    foreach ($privs as $key => $value) {
        $privopts .= getTplOption((string)($key + 1), $value, $view == ($key + 1));
    }
    $rows = $tpl->getHtmlFrag('admin-messages-add-rows', [
        'active_html' => radio_form($active, 'status'),
        'active_label' => _ACTIVATE2,
        'body_html' => textarea('1', 'body', $body, 'all', '10', _TEXT, '1'),
        'body_label' => _TEXT.':',
        'expire_html' => $expire_text,
        'expire_label_html' => getTplAdminHintLabel(_EXPIRATION, _CONFINES),
        'lang_html' => $langsel,
        'lang_label' => _LANGUAGE.':',
        'newexpire_value' => (string)$newexpire,
        'save_label' => _SAVE,
        'title_label' => _TITLE.':',
        'title_value' => $title,
        'view_html' => getTplSelect('view', $privopts, 'sl_form'),
        'view_label' => _VIEWPRIV,
    ]);
    $cont .= getTplAdminForm($afile.'.php', $rows, $hide);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $mid = getVar('post', 'mid', 'num');
    $title = getVar('post', 'title', 'title');
    $body = getVar('post', 'body', 'text');
    $newexpire = getVar('post', 'newexpire', 'num');
    $expire = getVar('post', 'expire', 'num');
    $active = getVar('post', 'status', 'num');
    $view = getVar('post', 'view', 'num');
    $lang = getVar('post', 'lang', 'var');
    $posttype = getVar('post', 'posttype', 'var');
    $expire = ($newexpire == 1 && $expire) ? time() + ($expire * 86400) : $expire;
    if (!$title) $stop[] = _CERROR;
    if (!$body) $stop[] = _CERROR1;
    if (!$stop && $posttype == 'save') {
        if ($mid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_message SET title = :title, body = :body, expire = :expire, status = :active, view = :view, lang = :lang WHERE id = :mid', ['title' => $title, 'body' => $body, 'expire' => $expire, 'active' => $active, 'view' => $view, 'lang' => $lang, 'mid' => $mid]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_message (id, title, body, expire, status, view, lang) VALUES (NULL, :title, :body, :expire, :active, :view, :lang)', ['title' => $title, 'body' => $body, 'expire' => $expire, 'active' => $active, 'view' => $view, 'lang' => $lang]);
        }
        setRedirect($afile.'.php?name=messages');
    } elseif ($posttype == 'delete') {
        delete($mid);
    } else {
        add();
    }
}

function status(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    $act = getVar('get', 'act', 'num');
    if ($id) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_message SET status = :active WHERE id = :mid', ['active' => $act, 'mid' => $id]);
    setRedirect($afile.'.php?name=messages');
}

function delete(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_message WHERE id = :mid', ['mid' => $id]);
    setRedirect($afile.'.php?name=messages');
}

function info(): void {
    $cont = setAdminNavi(['ops' => ['name=messages', 'name=messages&amp;op=add', 'name=messages&amp;op=info'], 'tabs' => [_HOME, _ADD, _INFO], 'tab' => 2]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: messages(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'status': status(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
