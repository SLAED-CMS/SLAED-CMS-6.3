<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('whois')) die('Illegal file access');

function whois(): void {
    global $db, $afile, $conf, $tpl;
        $anum = $conf['whois']['anum'] ?? 10;
    $anump = $conf['whois']['anump'] ?? 10;

    setHead();
        $num = getVar('get', 'num', 'num', 1);
    $offset = intval(($num - 1) * $anum);
    $status = getVar('get', 'status', 'num');
    if ($status == 1) {
        $status = 0;
        $field = 'name=whois&amp;status=1&amp;';
        $cont = setAdminNavi(['ops' => ['name=whois', 'name=whois&amp;op=add', 'name=whois&amp;status=1', 'name=whois&amp;op=config', 'name=whois&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 2]);
    } else {
        $status = 1;
        $field = 'name=whois&amp;';
        $cont = setAdminNavi(['ops' => ['name=whois', 'name=whois&amp;op=add', 'name=whois&amp;status=1', 'name=whois&amp;op=config', 'name=whois&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO]]);
    }

    $result = $db->getSqlQuery('SELECT w.id, w.name, w.ip, w.time, w.domain, w.host, w.dc, w.body, w.sdomain, w.shost, w.sdc, u.name FROM '.PREFIX_DB.'_whois AS w LEFT JOIN '.PREFIX_DB.'_users AS u ON (w.uid = u.id) WHERE status = :status ORDER BY w.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $head = $tpl->getHtmlFrag('admin-whois-list-head', [
            'dc_label' => _DC,
            'functions_label' => _FUNCTIONS,
            'host_label' => _HOST,
            'id_label' => _ID,
            'postedby_label' => _POSTEDBY,
            'site_label' => _SITE,
        ]);
        $rows = '';
        while ([$id, $uname, $ipSender, $time, $domain, $host, $dc, $hometext, $statusDomain, $statusHost, $statusDc, $userName] = $db->getSqlRow($result)) {
            $post = $userName ? user_info($userName) : ($uname ?: _ANONYM);
            $ipSender = $ipSender ? user_geo_ip($ipSender, 4) : _NO;
            $hometext = $hometext ?: _NO;
            $host = $host ? domain($host) : _NO_INFO;
            $dc = $dc ? domain($dc) : _NO_INFO;
            $acts = adminMenuItems([
                ad_status($afile.'.php?name=whois&amp;op=toggle&amp;id='.$id.'&amp;fid=1&amp;refer=1', $statusDomain, '', _SITE),
                ad_status($afile.'.php?name=whois&amp;op=toggle&amp;id='.$id.'&amp;fid=2&amp;refer=1', $statusHost, '', _HOST),
                ad_status($afile.'.php?name=whois&amp;op=toggle&amp;id='.$id.'&amp;fid=3&amp;refer=1', $statusDc, '', _DC),
                adminLinkAction($afile.'.php?name=whois&amp;op=add&amp;id='.$id, _FULLEDIT, _FULLEDIT),
                adminDeleteAction($afile.'.php?name=whois&amp;op=delete&amp;id='.$id.'&amp;refer=1', _DELETE.' "'.$domain.'"?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-whois-list-row', [
                'actions_html' => $acts,
                'dc_html' => $dc,
                'dc_status_html' => ad_status('', $statusDc),
                'domain_html' => domain($domain),
                'domain_status_html' => ad_status('', $statusDomain),
                'host_html' => $host,
                'host_status_html' => ad_status('', $statusHost),
                'id_text' => (string)$id,
                'postedby_html' => adminTitleTip(_DATE.': '.format_time($time, _TIMESTRING).'<br>'._IP.': '.$ipSender.'<br>'._COMMENT.': '.$hometext).$post,
            ]));
        }
        $cont .= getAdminTable($head, $rows, 'sl_table_list');
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'id', '_whois', '', "status = '".$status."'", $anump);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function toggle(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    $fid = getVar('get', 'fid', 'num');
    $field = match ($fid) {
        1 => 'sdomain',
        2 => 'shost',
        3 => 'sdc',
        default => '',
    };
    if ($id && $field) {
        [$active] = $db->getSqlRow($db->getSqlQuery('SELECT '.$field.' FROM '.PREFIX_DB.'_whois WHERE id = :id', ['id' => $id]));
        $active = $active ? 0 : 1;
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_whois SET '.$field.' = :active WHERE id = :id', ['active' => $active, 'id' => $id]);
    }
    setRedirect($afile.'.php?name=whois', true);
}

function add(): void {
    global $db, $afile, $stop, $tpl;
    $stop = $stop ?? [];
    $wid = 0;
    $id = getVar('req', 'id', 'num');
    if ($wid) {
        $result = $db->getSqlQuery('SELECT w.id, w.name, w.domain, w.host, w.dc, w.body, u.name FROM '.PREFIX_DB.'_whois AS w LEFT JOIN '.PREFIX_DB.'_users AS u ON (w.uid = u.id) WHERE w.id = :id', ['id' => $id]);
        [$id, $uname, $domain, $host, $dc, $hometext, $userName] = $db->getSqlRow($result);
        $postname = $userName ?: ($uname ?: _ANONYM);
    } else {
        $wid = getVar('post', 'wid', 'num');
        $postname = getVar('post', 'postname', 'name', '');
        $domain = getVar('post', 'domain', 'url', 'http://');
        $host = getVar('post', 'host', 'url', 'http://');
        $dc = getVar('post', 'dc', 'url', 'http://');
        $hometext = getVar('post', 'hometext', 'text', '');
    }
    setHead();
    $cont = setAdminNavi(['ops' => ['name=whois', 'name=whois&amp;op=add', 'name=whois&amp;status=1', 'name=whois&amp;op=config', 'name=whois&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => implode('<br>', $stop)]);
    $rows = $tpl->getHtmlFrag('admin-whois-add-rows', [
        'comment_value' => $hometext,
        'dc_value' => $dc,
        'domain_value' => $domain,
        'host_value' => $host,
        'postname_html' => get_user_search('postname', $postname, '25', 'sl_form', '1'),
        'save_html' => getAdminHidden('name', 'whois').ad_save('wid', $wid, 'save', 1),
    ]);
    $cont .= getAdminForm($afile.'.php', $rows);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $wid = getVar('post', 'wid', 'num');
    $postname = getVar('post', 'postname', 'name', '');
    $domain = getVar('post', 'domain', 'url', '');
    $host = getVar('post', 'host', 'url', '');
    $dc = getVar('post', 'dc', 'url', '');
    $hometext = getVar('post', 'hometext', 'text', '');
    $posttype = getVar('post', 'posttype', 'var', '');
    $stop = [];
    if (!$postname) $stop[] = _CERROR3;
    if (!$domain) $stop[] = _CERROR4;

    if (!$stop && $posttype == 'save') {
        $postid = is_user_id($postname);
        $uid = $postid ? $postid : '';
        $name = $postid ? '' : filterText(substr($postname, 0, 25));
        if ($wid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB."_whois SET uid = :uid, name = :name, domain = :domain, host = :host, dc = :dc, body = :body, status = '1' WHERE id = :id", ['uid' => $uid, 'name' => $name, 'domain' => $domain, 'host' => $host, 'dc' => $dc, 'body' => $hometext, 'id' => $wid]);
        } else {
            $ip = getIp();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB."_whois (id, uid, name, ip, time, domain, host, dc, body, sdomain, shost, sdc, status) VALUES (NULL, :uid, :name, :ip, now(), :domain, :host, :dc, :body, '0', '0', '0', '1')", ['uid' => $uid, 'name' => $name, 'ip' => $ip, 'domain' => $domain, 'host' => $host, 'dc' => $dc, 'body' => $hometext]);
        }
        setRedirect($afile.'.php?name=whois');
    } elseif ($posttype == 'delete') {
        delete($wid);
    } else {
        add();
    }
}

function delete(int $id = 0): void {
    global $db, $afile;
    if (!$id) $id = getVar('req', 'id', 'num');
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_whois WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=whois', true);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=whois', 'name=whois&amp;op=add', 'name=whois&amp;status=1', 'name=whois&amp;op=config', 'name=whois&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 3]);
    $cont .= checkPerms(CONFIG_DIR.'/whois.php');
    $cont .= getAdminBox($tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'whois',
        'op' => 'configsave',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_c34' => _C_34,
        'anum' => $conf['whois']['anum'] ?? 10,
        '_c36' => _C_36,
        'anump' => $conf['whois']['anump'] ?? 10,
        '_addamail' => _ADDAMAIL,
        'r_addmail' => radio_form($conf['whois']['addmail'] ?? 0, 'addmail'),
        '_whoisadd' => _WHOISADD,
        'r_add' => radio_form($conf['whois']['add'] ?? 0, 'add'),
        '_whoisaddg' => _WHOISADDG,
        'r_addquest' => radio_form($conf['whois']['addquest'] ?? 0, 'addquest'),
        'whois' => true,
    ]));
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $cont = [
        'anum' => getVar('post', 'anum', 'num', 10),
        'anump' => getVar('post', 'anump', 'num', 10),
        'addmail' => getVar('post', 'addmail', 'num', 0),
        'add' => getVar('post', 'add', 'num', 0),
        'addquest' => getVar('post', 'addquest', 'num', 0),
    ];
    setConfigFile('whois.php', $cont);
    setRedirect($afile.'.php?name=whois&op=config');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=whois', 'name=whois&amp;op=add', 'name=whois&amp;status=1', 'name=whois&amp;op=config', 'name=whois&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 4]);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
}

switch ($op) {
    default: whois(); break;
    case 'toggle': toggle(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}



