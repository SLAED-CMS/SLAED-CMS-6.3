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
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list"><thead><tr><th>'._ID.'</th><th>'._POSTEDBY.'</th><th colspan="2">'._SITE.'</th><th colspan="2">'._HOST.'</th><th colspan="2">'._DC.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $uname, $ipSender, $time, $domain, $host, $dc, $hometext, $statusDomain, $statusHost, $statusDc, $userName] = $db->getSqlRow($result)) {
            $post = $userName ? user_info($userName) : ($uname ?: _ANONYM);
            $ipSender = $ipSender ? user_geo_ip($ipSender, 4) : _NO;
            $hometext = $hometext ?: _NO;
            $host = $host ? domain($host) : _NO_INFO;
            $dc = $dc ? domain($dc) : _NO_INFO;
            $actions = ad_status($afile.'.php?name=whois&amp;op=toggle&amp;id='.$id.'&amp;fid=1&amp;refer=1', $statusDomain, '', _SITE)
                .'||'.ad_status($afile.'.php?name=whois&amp;op=toggle&amp;id='.$id.'&amp;fid=2&amp;refer=1', $statusHost, '', _HOST)
                .'||'.ad_status($afile.'.php?name=whois&amp;op=toggle&amp;id='.$id.'&amp;fid=3&amp;refer=1', $statusDc, '', _DC)
                .'||<a href="'.$afile.'.php?name=whois&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>'
                .'||<a href="'.$afile.'.php?name=whois&amp;op=delete&amp;id='.$id.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$domain.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>';
            $cont .= '<tr><td>'.$id.'</td>'
                .'<td>'.title_tip(_DATE.': '.format_time($time, _TIMESTRING).'<br>'._IP.': '.$ipSender.'<br>'._COMMENT.': '.$hometext).$post.'</td>'
                .'<td>'.domain($domain).'</td><td>'.ad_status('', $statusDomain).'</td>'
                .'<td>'.$host.'</td><td>'.ad_status('', $statusHost).'</td>'
                .'<td>'.$dc.'</td><td>'.ad_status('', $statusDc).'</td>'
                .'<td>'.add_menu($actions).'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $anum, $field, 'id', '_whois', '', "status = '".$status."'", $anump);
        $cont .= setTemplateBasic('close');
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
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
        .'<tr><td>'._POSTEDBY.':</td><td>'.get_user_search('postname', $postname, '25', 'sl_form', '1').'</td></tr>'
        .'<tr><td>'._SITE.':</td><td><input type="url" name="domain" value="'.$domain.'" maxlength="255" class="sl_form" placeholder="'._SITE.'" required></td></tr>'
        .'<tr><td>'._HOST.':</td><td><input type="url" name="host" value="'.$host.'" maxlength="255" class="sl_form" placeholder="'._HOST.'"></td></tr>'
        .'<tr><td>'._DC.':</td><td><input type="url" name="dc" value="'.$dc.'" maxlength="255" class="sl_form" placeholder="'._DC.'"></td></tr>'
        .'<tr><td>'._COMMENT.':</td><td><textarea name="hometext" cols="65" rows="5" class="sl_form" placeholder="'._COMMENT.'">'.$hometext.'</textarea></td></tr>'
        .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="whois">'.ad_save('wid', $wid, 'save', 1).'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
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
    global $afile, $conf;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=whois', 'name=whois&amp;op=add', 'name=whois&amp;status=1', 'name=whois&amp;op=config', 'name=whois&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 3]);
    $cont .= checkPerms(CONFIG_DIR.'/whois.php');
    $cont .= setTemplateBasic('open');
    $cont .= setTemplateBasic('form-conf', [
        '{%route%}'      => $afile,
        '{%module%}'     => 'whois',
        '{%op%}'         => 'configsave',
        '{%save%}'       => _SAVECHANGES,
        '{%fields%}'     => '',
        '{%_c34%}'       => _C_34,
        '{%anum%}'       => $conf['whois']['anum'] ?? 10,
        '{%_c36%}'       => _C_36,
        '{%anump%}'      => $conf['whois']['anump'] ?? 10,
        '{%_addamail%}'  => _ADDAMAIL,
        '{%r_addmail%}'  => radio_form($conf['whois']['addmail'] ?? 0, 'addmail'),
        '{%_whoisadd%}'  => _WHOISADD,
        '{%r_add%}'      => radio_form($conf['whois']['add'] ?? 0, 'add'),
        '{%_whoisaddg%}' => _WHOISADDG,
        '{%r_addquest%}' => radio_form($conf['whois']['addquest'] ?? 0, 'addquest'),
        'if_flag'        => ['whois' => true],
    ]);
    $cont .= setTemplateBasic('close');
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
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
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





