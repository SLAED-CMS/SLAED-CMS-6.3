<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('whois')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=whois', 'name=whois&amp;op=add', 'name=whois&amp;status=1', 'name=whois&amp;op=conf', 'name=whois&amp;op=info'];
    $lang = [_HOME, _ADD, _NEW, _PREFERENCES, _INFO];
    return getAdminTabs(_WHOIS, 'whois.png', '', $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function whois(): void {
    global $db, $afile, $conf;
        $anum = $conf['whois']['anum'] ?? 10;
    $anump = $conf['whois']['anump'] ?? 10;

    setHead();
        $num = getVar('get', 'num', 'num', 1);
    $offset = intval(($num - 1) * $anum);
    $status = getVar('get', 'status', 'num');
    if ($status == 1) {
        $status = 0;
        $field = 'name=whois&amp;status=1&amp;';
        $cont = navi(0, 2, 0, 0);
    } else {
        $status = 1;
        $field = 'name=whois&amp;';
        $cont = navi(0, 0, 0, 0);
    }

    $result = $db->getSqlQuery('SELECT w.id, w.name, w.ip, w.time, w.domain, w.host, w.dc, w.hometext, w.st_domain, w.st_host, w.st_dc, u.user_name FROM '.PREFIX_DB.'_whois AS w LEFT JOIN '.PREFIX_DB.'_users AS u ON (w.uid = u.user_id) WHERE status = :status ORDER BY w.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list"><thead><tr><th>'._ID.'</th><th>'._POSTEDBY.'</th><th colspan="2">'._SITE.'</th><th colspan="2">'._HOST.'</th><th colspan="2">'._DC.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $uname, $ipSender, $time, $domain, $host, $dc, $hometext, $statusDomain, $statusHost, $statusDc, $userName] = $db->getSqlRow($result)) {
            $post = $userName ? user_info($userName) : ($uname ?: _ANONYM);
            $ipSender = $ipSender ? user_geo_ip($ipSender, 4) : _NO;
            $hometext = $hometext ?: _NO;
            $host = $host ? domain($host) : _NO_INFO;
            $dc = $dc ? domain($dc) : _NO_INFO;
            $actions = ad_status($afile.'.php?name=whois&amp;op=act&amp;id='.$id.'&amp;fid=1&amp;refer=1', $statusDomain, '', _SITE)
                .'||'.ad_status($afile.'.php?name=whois&amp;op=act&amp;id='.$id.'&amp;fid=2&amp;refer=1', $statusHost, '', _HOST)
                .'||'.ad_status($afile.'.php?name=whois&amp;op=act&amp;id='.$id.'&amp;fid=3&amp;refer=1', $statusDc, '', _DC)
                .'||<a href="'.$afile.'.php?name=whois&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>'
                .'||<a href="'.$afile.'.php?name=whois&amp;op=del&amp;id='.$id.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$domain.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>';
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
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function act(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    $fid = getVar('get', 'fid', 'num');
    $field = match ($fid) {
        1 => 'st_domain',
        2 => 'st_host',
        3 => 'st_dc',
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
    global $db, $afile, $stop;
    $stop = $stop ?? [];
    $wid = 0;
    $id = getVar('req', 'id', 'num');
    if ($wid) {
        $result = $db->getSqlQuery('SELECT w.id, w.name, w.domain, w.host, w.dc, w.hometext, u.user_name FROM '.PREFIX_DB.'_whois AS w LEFT JOIN '.PREFIX_DB.'_users AS u ON (w.uid = u.user_id) WHERE w.id = :id', ['id' => $id]);
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
        $cont = navi(0, 1, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => implode('<br>', $stop)]);
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
            $db->getSqlQuery('UPDATE '.PREFIX_DB."_whois SET uid = :uid, name = :name, domain = :domain, host = :host, dc = :dc, hometext = :hometext, status = '1' WHERE id = :id", ['uid' => $uid, 'name' => $name, 'domain' => $domain, 'host' => $host, 'dc' => $dc, 'hometext' => $hometext, 'id' => $wid]);
        } else {
            $ip = getIp();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB."_whois (id, uid, name, ip, time, domain, host, dc, hometext, st_domain, st_host, st_dc, status) VALUES (NULL, :uid, :name, :ip, now(), :domain, :host, :dc, :hometext, '0', '0', '0', '1')", ['uid' => $uid, 'name' => $name, 'ip' => $ip, 'domain' => $domain, 'host' => $host, 'dc' => $dc, 'hometext' => $hometext]);
        }
        setRedirect($afile.'.php?name=whois');
    } elseif ($posttype == 'delete') {
        del($wid);
    } else {
        add();
    }
}

function del(int $id = 0): void {
    global $db, $afile;
    if (!$id) $id = getVar('req', 'id', 'num');
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_whois WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=whois', true);
}

function conf(): void {
    global $afile, $conf;
        setHead();
        $cont = navi(0, 3, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/whois.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
        .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.($conf['whois']['anum'] ?? 10).'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
        .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.($conf['whois']['anump'] ?? 10).'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
        .'<tr><td>'._ADDAMAIL.'</td><td>'.radio_form($conf['whois']['addmail'] ?? 0, 'addmail').'</td></tr>'
        .'<tr><td>'._WHOISADD.'</td><td>'.radio_form($conf['whois']['add'] ?? 0, 'add').'</td></tr>'
        .'<tr><td>'._WHOISADDG.'</td><td>'.radio_form($conf['whois']['addquest'] ?? 0, 'addquest').'</td></tr>'
        .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="whois"><input type="hidden" name="op" value="saveconf"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function saveconf(): void {
    global $afile;
    $cont = [
        'anum' => getVar('post', 'anum', 'num', 10),
        'anump' => getVar('post', 'anump', 'num', 10),
        'addmail' => getVar('post', 'addmail', 'num', 0),
        'add' => getVar('post', 'add', 'num', 0),
        'addquest' => getVar('post', 'addquest', 'num', 0),
    ];
    setConfigFile('whois.php', $cont);
    setRedirect($afile.'.php?name=whois&op=conf');
}

function info(): void {
    setHead();
        echo navi(0, 4, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: whois(); break;
    case 'act': act(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'del': del(); break;
    case 'conf': conf(); break;
    case 'saveconf': saveconf(); break;
    case 'info': info(); break;
}





