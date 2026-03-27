<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('content')) die('Illegal file access');

function content(): void {
    global $db, $afile, $conf, $tpl;
        setHead();
    $cont = setAdminNavi(['ops' => ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=config', 'name=content&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO]]);
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['content']['anum'] ?? 10;
    $anump = $conf['content']['anump'] ?? 10;
    $offset = ($num - 1) * $anum;
    $result = $db->getSqlQuery('SELECT id, title, time, counter FROM '.PREFIX_DB.'_content ORDER BY id DESC LIMIT '.$offset.', '.$anum);
    if ($db->getSqlRowCount($result) > 0) {
        $head = $tpl->getHtmlFrag('admin-content-list-head', [
            'date_label' => _DATE,
            'functions_label' => _FUNCTIONS,
            'id_label' => _ID,
            'reads_label' => cutstr(_READS, 4, 1),
            'status_label' => _STATUS,
            'title_label' => _TITLE,
        ]);
        $rows = '';
        while ([$id, $title, $time, $counter] = $db->getSqlRow($result)) {
            if (time() >= strtotime($time)) {
                $view = adminLinkAction('index.php?name=content&amp;op=view&amp;id='.$id, _MVIEW, _MVIEW);
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $acts = adminMenuItems([
                $view,
                adminLinkAction($afile.'.php?name=content&amp;op=add&amp;id='.$id, _FULLEDIT, _FULLEDIT),
                adminDeleteAction($afile.'.php?name=content&amp;op=delete&amp;id='.$id, _DELETE.' "'.$title.'"?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-content-list-row', [
                'actions_html' => $acts,
                'date_text' => format_time($time, _TIMESTRING),
                'id_text' => (string)$id,
                'reads_text' => (string)$counter,
                'status_html' => ad_status('', $active),
                'title_html' => title_tip(_URL.': '.$conf['homeurl'].'/index.php?name=content&amp;op=view&amp;id='.$id.'<br>'._ORTYPEURL.': '.$conf['homeurl'].'/index.php?go=rss&amp;name=content&amp;id='.$id).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 50).'</span>',
            ]));
        }
        $cont .= getAdminTable($head, $rows);
        $cont .= setArticleNumbers('pagenum', '', $anum, 'name=content&amp;', 'id', '_content', '', '', $anump);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop, $tpl;
    $id = getVar('req', 'id', 'num', 0);
    if ($id) {
        $result = $db->getSqlQuery('SELECT id, title, body, field, url, time, refresh FROM '.PREFIX_DB.'_content WHERE id = :id', ['id' => $id]);
        [$cid, $title, $body, $field, $url, $time, $refresh] = $db->getSqlRow($result);
    } else {
        $cid = getVar('post', 'cid', 'num', 0);
        $title = getVar('post', 'title', 'title', '');
        $body = getVar('post', 'body', 'text', '');
        $field = getVar('post', 'field', 'field');
        $url = getVar('post', 'url', 'text', '');
        $time = getVar('req', 'time', 'time');
        $refresh = getVar('post', 'refresh', 'num', 0);
    }
    setHead();
    $cont = setAdminNavi(['ops' => ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=config', 'name=content&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
    $fields = ($field) ? '<br><br>'.fields_out($field, 'content') : '';
    if ($body) $cont .= preview($title, $body, '', $field, 'content');
    $rows = '';
    $rows .= getAdminFormRow(_TITLE.':', getAdminTextInput('title', $title, 'sl_form', 'maxlength="100" placeholder="'._TITLE.'" required'));
    $rows .= getAdminFormRow(getAdminHintLabel(_RSSFILE, _RSSINFO), getAdminTextInput('url', $url, 'sl_form', 'maxlength="200" placeholder="'._RSSFILE.'"'));
    $opts = getAdminOption('1800', '30 '._MIN.'.', $refresh == '1800')
        .getAdminOption('3600', '1 '._HOUR, $refresh == '3600' || !$refresh)
        .getAdminOption('18000', '5 '._HOUR.'.', $refresh == '18000')
        .getAdminOption('36000', '10 '._HOUR.'.', $refresh == '36000')
        .getAdminOption('86400', '24 '._HOUR, $refresh == '86400');
    $opts = getAdminSelect('refresh', $opts, 'sl_form');
    $rows .= $tpl->getHtmlFrag('admin-content-add-rows', [
        'body_html' => textarea('1', 'body', $body.$fields, 'content', '25', _TEXT, '0'),
        'date_html' => datetime(1, 'time', $time, 16, 'sl_form'),
        'fields_html' => fields_in($field, 'content'),
        'refresh_html' => $opts,
        'refresh_label_html' => getAdminHintLabel(_REFRESHTIME, _REFINFO),
        'save_html' => ad_save('cid', $cid, 'save'),
    ]);
    $hide = getAdminHidden('name', 'content');
    $cont .= getAdminForm($afile.'.php', $rows, $hide);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $cid = getVar('post', 'cid', 'num', 0);
    $title = getVar('post', 'title', 'title', '');
    $url = getVar('post', 'url', 'text', '');
    $body = getVar('post', 'body', 'text', '');
    $body = ($url) ? rss_read($url, 1) : $body;
    $field = getVar('post', 'field', 'field');
    $time = getVar('req', 'time', 'time');
    $refresh = getVar('post', 'refresh', 'num', 0);
    if (!$title) $stop[] = _CERROR;
    if (!$body && !$url) $stop[] = _CERROR1;
    if (!$body && $url) $stop[] = _RSSFAIL;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype == 'save') {
        if ($cid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_content SET title = :title, body = :body, field = :field, url = :url, time = :time, refresh = :refresh WHERE id = :cid', ['title' => $title, 'body' => $body, 'field' => $field, 'url' => $url, 'time' => $time, 'refresh' => $refresh, 'cid' => $cid]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_content (title, body, field, url, time, refresh, counter) VALUES (:title, :body, :field, :url, :time, :refresh, \'0\')', ['title' => $title, 'body' => $body, 'field' => $field, 'url' => $url, 'time' => $time, 'refresh' => $refresh]);
        }
        setRedirect($afile.'.php?name=content');
    } elseif ($posttype == 'delete') {
        delete($cid);
    } else {
        add();
    }
}

function delete(int $cid = 0): void {
    global $db, $afile;
    $id = $cid ? $cid : getVar('req', 'id', 'num', 0);
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_content WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=content');
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=config', 'name=content&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/content.php');
    $cont .= getAdminBox($tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'content',
        'op' => 'configsave',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_c33' => _C_33,
        'num' => $conf['content']['num'],
        '_c34' => _C_34,
        'anum' => $conf['content']['anum'],
        '_c35' => _C_35,
        'nump' => $conf['content']['nump'],
        '_c36' => _C_36,
        'anump' => $conf['content']['anump'],
        'content' => true,
    ]));
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $cont = [
        'num' => getVar('post', 'num', 'num', 25),
        'anum' => getVar('post', 'anum', 'num', 25),
        'nump' => getVar('post', 'nump', 'num', 10),
        'anump' => getVar('post', 'anump', 'num', 10),
    ];
    setConfigFile('content.php', $cont);
    setRedirect($afile.'.php?name=content&op=config');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=config', 'name=content&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 3]);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
}

switch ($op) {
    default: content(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}



