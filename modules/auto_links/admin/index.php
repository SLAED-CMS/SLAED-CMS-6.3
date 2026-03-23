<?php
# Author: Eduard Laas
# Copyright � 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('auto_links')) die('Illegal file access');


function auto_links(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi([
        'ops'  => ['name=auto_links', 'name=auto_links&amp;op=add', 'name=auto_links&amp;op=hitreset', 'name=auto_links&amp;op=zerodel', 'name=auto_links&amp;op=config', 'name=auto_links&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NULLHITS, _NOINDEL, _PREFERENCES, _INFO],
    ]);
    if (!$conf['referers']['refer']) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _A_NOTE]);
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $conf['auto_links']['anum'];
    $result = $db->getSqlQuery('SELECT id, title, url, hits, outs, added FROM '.PREFIX_DB.'_auto_links ORDER BY hits ASC LIMIT '.$offset.', '.$conf['auto_links']['anum']);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= $tpl->getHtmlFrag('open', []);
        $cont .= '<table class="sl_table_list_sort"><thead><tr>'
           .'<th>'._ID.'</th><th>'._SITENAME.'</th><th>'._SITEURL.'</th>'
           .'<th>'._HITS.'</th><th>'._OUTS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th>'
           .'</tr></thead><tbody>';
        while ([$id, $name, $url, $hits, $outs, $added] = $db->getSqlRow($result)) {
            $vhits = ($hits) ? '<a href="'.$afile.'.php?name=auto_links&amp;op=stats&amp;id='.$id.'" title="'._MVIEW.'">'._MVIEW.'</a>||' : '';
            $edit = '<a href="'.$afile.'.php?name=auto_links&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>';
            $drop = '<a href="'.$afile.'.php?name=auto_links&amp;op=delete&amp;id='.$id.'&amp;refer=1"'
               .' OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$name.'&quot;?\');"'
               .' title="'._ONDELETE.'">'._ONDELETE.'</a>';
            $cont .= '<tr>'
               .'<td>'.$id.'</td>'
               .'<td>'.title_tip(_REG.': '.format_time($added, _TIMESTRING)).'<span title="'.$name.'" class="sl_note">'.cutstr($name, 40).'</span></td>'
               .'<td>'.domain($url).'</td>'
               .'<td>'.$hits.'</td>'
               .'<td>'.$outs.'</td>'
               .'<td>'.add_menu($vhits.$edit.'||'.$drop).'</td>'
               .'</tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $conf['auto_links']['anum'], 'name=auto_links&amp;', 'id', '_auto_links', '', '', $conf['auto_links']['anump']);
        $cont .= $tpl->getHtmlFrag('close', []);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function stats(): void {
    global $db, $afile, $conf, $tpl;
    $id = getVar('req', 'id', 'num');
    $sort = getVar('req', 'sort', 'num');
    $order = getVar('req', 'order', 'num');
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $conf['auto_links']['anum'];
    $tnum = ($offset) ? $conf['auto_links']['anum'] + $offset : $conf['auto_links']['anum'];
    if ($sort == 1) { $count = 'referer'; $ordby = 'hits'; }
    elseif ($sort == 2) { $count = 'referer'; $ordby = 'referer'; }
    elseif ($sort == 3) { $count = 'url'; $ordby = 'hits'; }
    elseif ($sort == 4) { $count = 'url'; $ordby = 'url'; }
    elseif ($sort == 5) { $count = 'name'; $ordby = 'hits'; }
    elseif ($sort == 6) { $count = 'name'; $ordby = 'name'; }
    elseif ($sort == 7) { $count = 'ip'; $ordby = 'hits'; }
    elseif ($sort == 8) { $count = 'ip'; $ordby = 'ip'; }
    elseif ($sort == 9) { $count = 'time'; $ordby = 'hits'; }
    else { $count = 'time'; $ordby = 'time'; }
    $ordsc = ($order == 1) ? 'ASC' : 'DESC';
    $result = $db->getSqlQuery('SELECT Count('.$count.') AS hits, uid, name, ip, referer, url, time FROM '.PREFIX_DB.'_referer WHERE lid = :lid GROUP BY '.$count.' ORDER BY '.$ordby.' '.$ordsc, ['lid' => $id]);
    setHead();
    $_id = getVar('req', 'id', 'num');
    $box = '';
    if ($_id) {
        $box = '<form method="post" action="'.$afile.'.php?name=auto_links">'._SORTE.': <select name="sort">';
        foreach ([_REF_ID, _REF_URL, _IN_ID, _IN_URL, _NAME_ID, _NAME_REF, _IP_ID, _IP_REF, _TIME_ID, _TIME_REF] as $_k => $_v) {
            $_sort = $_k + 1;
            $box .= '<option value="'.$_sort.'"'.(getVar('post', 'sort', 'num') == $_sort ? ' selected' : '').'>'.$_v.'</option>';
        }
        $box .= '</select><select name="order">';
        foreach ([_ASC, _DESC] as $_k => $_v) {
            $_sort = $_k + 1;
            $box .= '<option value="'.$_sort.'"'.(getVar('post', 'order', 'num') == $_sort ? ' selected' : '').'>'.$_v.'</option>';
        }
        $box .= '</select> <input type="hidden" name="op" value="stats"><input type="hidden" name="id" value="'.$_id.'"><input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
        $box = $tpl->getHtmlPart('searchbox', ['searchbox' => $box]);
    }
    $cont = setAdminNavi([
        'ops'  => ['name=auto_links', 'name=auto_links&amp;op=add', 'name=auto_links&amp;op=hitreset', 'name=auto_links&amp;op=zerodel', 'name=auto_links&amp;op=config', 'name=auto_links&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NULLHITS, _NOINDEL, _PREFERENCES, _INFO],
        'sub'  => $box,
    ]);
    if (!$conf['referers']['refer']) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _A_NOTE]);
    $list = [];
    $a = 0;
    while ([$hits, $uid, $name, $ip, $referer, $url, $date] = $db->getSqlRow($result)) {
        $list[] = [$hits, $uid, $name, $ip, $referer, $url, $date];
        $a++;
    }
    if (isArray($list)) {
        $cont .= $tpl->getHtmlFrag('open', []);
        $cont .= '<table class="sl_table_list_sort"><thead><tr>'
           .'<th>'._ID.'</th><th>'._NICKNAME.'</th><th>'._IP.'</th><th>'._REF_URL.'</th><th>'._IN_URL.'</th>'
           .'</tr></thead><tbody>';
        for ($i = $offset; $i < $tnum; $i++) {
            if (isset($list[$i])) {
                $name = ($list[$i][1]) ? user_info($list[$i][2]) : $list[$i][2];
                $cont .= '<tr>'
                   .'<td>'.$list[$i][0].'</td>'
                   .'<td>'.title_tip(_DATE.': '.date(_TIMESTRING, $list[$i][6])).$name.'</td>'
                   .'<td>'.user_geo_ip($list[$i][3], 4).'</td>'
                   .'<td>'.domain($list[$i][4], 35).'</td>'
                   .'<td>'.domain($list[$i][5], 15).'</td>'
                   .'</tr>';
            }
        }
        $cont .= '</tbody></table>';
        $pages = ceil($a / $conf['auto_links']['anum']);
        $cont .= setPageNumbers('pagenum', '', $a, $pages, $conf['auto_links']['anum'], 'name=auto_links&amp;op=stats&amp;id='.$id.'&amp;sort='.$sort.'&amp;order='.$order.'&amp;', $conf['auto_links']['anump']);
        $cont .= $tpl->getHtmlFrag('close', []);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop, $tpl;
    $stop = $stop ?? [];
    $id = getVar('req', 'id', 'num');
    if ($id) {
        $result = $db->getSqlQuery('SELECT id, title, intro, url, email, hits, outs FROM '.PREFIX_DB.'_auto_links WHERE id = :id', ['id' => $id]);
        [$id, $name, $desc, $site, $email, $hits, $outs] = $db->getSqlRow($result);
    } else {
        $id = getVar('post', 'id', 'num');
        $name = getVar('post', 'name', 'title', '');
        $email = getVar('post', 'mail', 'var', '');
        $desc = getVar('post', 'desc', 'text', '');
        $site = getVar('post', 'site', 'url', 'https://');
        $hits = getVar('post', 'hits', 'num', 0);
        $outs = getVar('post', 'outs', 'num', 0);
    }
    setHead();
    $cont = setAdminNavi([
        'ops'  => ['name=auto_links', 'name=auto_links&amp;op=add', 'name=auto_links&amp;op=hitreset', 'name=auto_links&amp;op=zerodel', 'name=auto_links&amp;op=config', 'name=auto_links&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NULLHITS, _NOINDEL, _PREFERENCES, _INFO],
        'tab'  => 1,
    ]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => implode('<br>', $stop)]);
    if ($desc) $cont .= preview($name, $desc, '', '', 'auto_links');
    $cont .= $tpl->getHtmlFrag('open', []);
    $cont .= '<form name="post" action="'.$afile.'.php?name=auto_links" method="post"><table class="sl_table_form">'
       .'<tr><td>'._SITENAME.':</td><td><input type="text" name="name" value="'.$name.'" maxlength="255" class="sl_form" placeholder="'._SITENAME.'" required></td></tr>'
       .'<tr><td>'._A_LINKS_E.':</td><td><input type="email" name="mail" value="'.$email.'" maxlength="100" class="sl_form" placeholder="'._A_LINKS_E.'" required></td></tr>'
       .'<tr><td>'._A_LINKS_TEXT.':</td><td>'.textarea('1', 'desc', $desc, 'auto_links', '5', _A_LINKS_TEXT, '1').'</td></tr>'
       .'<tr><td>'._A_LINKS_L.':</td><td><input type="url" name="site" value="'.$site.'" maxlength="100" class="sl_form" placeholder="'._A_LINKS_L.'" required></td></tr>'
       .'<tr><td>'._HITS.':</td><td><input type="number" name="hits" value="'.$hits.'" class="sl_form" placeholder="'._HITS.'"></td></tr>'
       .'<tr><td>'._OUTS.':</td><td><input type="number" name="outs" value="'.$outs.'" class="sl_form" placeholder="'._OUTS.'"></td></tr>'
       .'<tr><td colspan="2" class="sl_center">'.ad_save('id', $id, 'save').'</td></tr></table></form>';
    $cont .= $tpl->getHtmlFrag('close', []);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $id = getVar('post', 'id', 'num');
    $name = getVar('post', 'name', 'title', '');
    $desc = getVar('post', 'desc', 'text', '');
    $site = getVar('post', 'site', 'url', 'https://');
    $email = getVar('post', 'mail', 'var', '');
    $hits = getVar('post', 'hits', 'num', 0);
    $outs = getVar('post', 'outs', 'num', 0);
    $stop = [];
    if (!$name) $stop[] = _CERROR10;
    if (!$desc) $stop[] = _CERROR11;
    if (!$site) $stop[] = _CERROR4;
    $posttype = getVar('post', 'posttype', 'var', '');
    if (!$stop && $posttype === 'save') {
        if ($id) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_auto_links SET title = :name, intro = :desc, url = :url, email = :email, hits = :hits, outs = :outs WHERE id = :id', ['name' => $name, 'desc' => $desc, 'url' => $site, 'email' => $email, 'hits' => $hits, 'outs' => $outs, 'id' => $id]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_auto_links (title, intro, url, email, hits, outs, added) VALUES (:name, :desc, :url, :email, :hits, :outs, now())', ['name' => $name, 'desc' => $desc, 'url' => $site, 'email' => $email, 'hits' => $hits, 'outs' => $outs]);
        }
        setRedirect($afile.'.php?name=auto_links');
    } elseif ($posttype === 'delete') {
        delete($id);
    } else {
        add();
    }
}

function delete(int $id = 0): void {
    global $db, $afile;
    if (!$id) $id = getVar('req', 'id', 'num');
    if ($id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_auto_links WHERE id = :id', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_referer WHERE lid = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=auto_links');
}

function hitreset(): void {
    global $db, $afile;
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_auto_links SET hits = 0, outs = 0');
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_referer WHERE lid != 0');
    setRedirect($afile.'.php?name=auto_links');
}

function zerodel(): void {
    global $db, $afile;
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_auto_links WHERE hits = 0');
    setRedirect($afile.'.php?name=auto_links');
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi([
        'ops'  => ['name=auto_links', 'name=auto_links&amp;op=add', 'name=auto_links&amp;op=hitreset', 'name=auto_links&amp;op=zerodel', 'name=auto_links&amp;op=config', 'name=auto_links&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NULLHITS, _NOINDEL, _PREFERENCES, _INFO],
        'tab'  => 4,
    ]);
    if (!$conf['referers']['refer']) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _A_NOTE]);
    $cont .= checkPerms(CONFIG_DIR.'/auto_links.php');
    $cont .= $tpl->getHtmlFrag('open', []);
    $path = 'templates/'.$conf['theme'].'/images/banners/';
    $opts = '';
    foreach (scandir($path) as $entry) {
        if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg)$/is', $entry)) {
            $sel = ($conf['auto_links']['img'] == $entry) ? ' selected' : '';
            $opts .= '<option value="'.$path.$entry.'"'.$sel.'>'.$entry.'</option>';
        }
    }
    $cont .= '<form name="post" action="'.$afile.'.php?name=auto_links" method="post"><table class="sl_table_conf">'
       .'<tr><td>'._A_1.':</td><td><select name="img" id="img_replace" class="sl_conf">'.$opts.'</select></td></tr>'
       .'<tr><td>'._A_2.':</td><td><img src="'.$path.$conf['auto_links']['img'].'" id="picture" alt="'._SITELOGO.'"></td></tr>'
       .'<tr><td>'._C_33.':</td><td><input type="number" name="num" value="'.$conf['auto_links']['num'].'" class="sl_conf" placeholder="'._C_33.'" required></td></tr>'
       .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.$conf['auto_links']['anum'].'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
       .'<tr><td>'._C_35.':</td><td><input type="number" name="nump" value="'.$conf['auto_links']['nump'].'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
       .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.$conf['auto_links']['anump'].'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
       .'<tr><td>'._A_4.':</td><td><input type="number" name="strip" value="'.$conf['auto_links']['strip'].'" class="sl_conf" placeholder="'._A_4.'" required></td></tr>'
       .'<tr><td>'._A_5.':</td><td><input type="number" name="limit" value="'.$conf['auto_links']['limit'].'" class="sl_conf" placeholder="'._A_5.'" required></td></tr>'
       .'<tr><td>'._ADDAMAIL.'</td><td>'.radio_form($conf['auto_links']['addmail'], 'addmail').'</td></tr>'
       .'<tr><td colspan="2" class="sl_center">'
       .'<input type="hidden" name="op" value="configsave">'
       .'<input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue">'
       .'</td></tr></table></form>';
    $cont .= $tpl->getHtmlFrag('close', []);
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile, $conf;
    $cont = [
        'img' => str_replace('templates/'.$conf['theme'].'/images/banners/', '', getVar('post', 'img', 'var', '')),
        'num' => getVar('post', 'num', 'num', 10),
        'anum' => getVar('post', 'anum', 'num', 10),
        'nump' => getVar('post', 'nump', 'num', 10),
        'anump' => getVar('post', 'anump', 'num', 10),
        'strip' => getVar('post', 'strip', 'num', 100),
        'limit' => getVar('post', 'limit', 'num', 1),
        'addmail' => getVar('post', 'addmail', 'num', 0),
    ];
    setConfigFile('auto_links.php', $cont);
    setRedirect($afile.'.php?name=auto_links&op=config');
}

function info(): void {
    setHead();
    $cont = setAdminNavi([
        'ops'  => ['name=auto_links', 'name=auto_links&amp;op=add', 'name=auto_links&amp;op=hitreset', 'name=auto_links&amp;op=zerodel', 'name=auto_links&amp;op=config', 'name=auto_links&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NULLHITS, _NOINDEL, _PREFERENCES, _INFO],
        'tab'  => 5,
    ]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: auto_links(); break;
    case 'stats': stats(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'hitreset': hitreset(); break;
    case 'zerodel': zerodel(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
