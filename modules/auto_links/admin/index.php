<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('auto_links')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    global $afile, $confr;
    $a_id = getVar('req', 'a_id', 'num');
    $ops = ['name=auto_links', 'name=auto_links&amp;op=add', 'name=auto_links&amp;op=nullhits', 'name=auto_links&amp;op=noindel', 'name=auto_links&amp;op=conf', 'name=auto_links&amp;op=info'];
    $lang = [_HOME, _ADD, _NULLHITS, _NOINDEL, _PREFERENCES, _INFO];
    $search = '';
    if ($a_id) {
        $search = '<form method="post" action="'.$afile.'.php">'._SORTE.': <select name="sort">';
        $priv = [_REF_ID, _REF_URL, _IN_ID, _IN_URL, _NAME_ID, _NAME_REF, _IP_ID, _IP_REF, _TIME_ID, _TIME_REF];
        foreach ($priv as $key => $value) {
            $sort = $key + 1;
            $sel = (getVar('post', 'sort', 'num') == $sort) ? ' selected' : '';
            $search .= '<option value="'.$sort.'"'.$sel.'>'.$value.'</option>';
        }
        $search .= '</select><select name="order">';
        $privs = [_ASC, _DESC];
        foreach ($privs as $key => $value) {
            $sort = $key + 1;
            $sel = (getVar('post', 'order', 'num') == $sort) ? ' selected' : '';
            $search .= '<option value="'.$sort.'"'.$sel.'>'.$value.'</option>';
        }
        $search .= '</select> <input type="hidden" name="name" value="auto_links"><input type="hidden" name="op" value="stats">'
           .'<input type="hidden" name="a_id" value="'.$a_id.'">'
           .'<input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
        $search = setTemplateBasic('searchbox', ['{%searchbox%}' => $search]);
    }
    $cont = getAdminTabs(_A_LINKS, 'auto_links.png', $search, $ops, $lang, [], [], $tab, (bool)$subtab);
    $cont .= (!$confr['refer']) ? setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _A_NOTE]) : '';
    return $cont;
}

function auto_links(): void {
    global $db, $afile, $conf;
    head();
    $cont = navi(0, 0, 0, 0);
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $conf['auto_links']['anum'];
    $result = $db->sql_query('SELECT id, sitename, link, hits, outs, added FROM '.PREFIX_DB.'_auto_links ORDER BY hits ASC LIMIT '.$offset.', '.$conf['auto_links']['anum']);
    if ($db->sql_numrows($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr>'
           .'<th>'._ID.'</th><th>'._SITENAME.'</th><th>'._SITEURL.'</th>'
           .'<th>'._HITS.'</th><th>'._OUTS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th>'
           .'</tr></thead><tbody>';
        while ([$a_id, $a_sitename, $a_link, $a_hits, $a_outs, $a_added] = $db->sql_fetchrow($result)) {
            $vhits = ($a_hits) ? '<a href="'.$afile.'.php?name=auto_links&amp;op=stats&amp;a_id='.$a_id.'" title="'._MVIEW.'">'._MVIEW.'</a>||' : '';
            $edit = '<a href="'.$afile.'.php?name=auto_links&amp;op=add&amp;id='.$a_id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>';
            $drop = '<a href="'.$afile.'.php?name=auto_links&amp;op=del&amp;id='.$a_id.'&amp;refer=1"'
               .' OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$a_sitename.'&quot;?\');"'
               .' title="'._ONDELETE.'">'._ONDELETE.'</a>';
            $cont .= '<tr>'
               .'<td>'.$a_id.'</td>'
               .'<td>'.title_tip(_REG.': '.format_time($a_added, _TIMESTRING)).'<span title="'.$a_sitename.'" class="sl_note">'.cutstr($a_sitename, 40).'</span></td>'
               .'<td>'.domain($a_link).'</td>'
               .'<td>'.$a_hits.'</td>'
               .'<td>'.$a_outs.'</td>'
               .'<td>'.add_menu($vhits.$edit.'||'.$drop).'</td>'
               .'</tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $conf['auto_links']['anum'], 'name=auto_links&amp;', 'id', '_auto_links', '', '', $conf['auto_links']['anump']);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    foot();
}

function stats(): void {
    global $db, $conf;
    $a_id = getVar('req', 'a_id', 'num');
    $sort = getVar('req', 'sort', 'num');
    $order = getVar('req', 'order', 'num');
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $conf['auto_links']['anum'];
    $tnum = ($offset) ? $conf['auto_links']['anum'] + $offset : $conf['auto_links']['anum'];
    if ($sort == 1) { $count = 'referer'; $ordby = 'hits'; }
    elseif ($sort == 2) { $count = 'referer'; $ordby = 'referer'; }
    elseif ($sort == 3) { $count = 'link'; $ordby = 'hits'; }
    elseif ($sort == 4) { $count = 'link'; $ordby = 'link'; }
    elseif ($sort == 5) { $count = 'name'; $ordby = 'hits'; }
    elseif ($sort == 6) { $count = 'name'; $ordby = 'name'; }
    elseif ($sort == 7) { $count = 'ip'; $ordby = 'hits'; }
    elseif ($sort == 8) { $count = 'ip'; $ordby = 'ip'; }
    elseif ($sort == 9) { $count = 'date'; $ordby = 'hits'; }
    else { $count = 'date'; $ordby = 'date'; }
    $ordsc = ($order == 1) ? 'ASC' : 'DESC';
    // GROUP BY / ORDER BY use allowlisted column names — safe to concatenate
    $result = $db->sql_query('SELECT Count('.$count.') AS hits, uid, name, ip, referer, link, date FROM '.PREFIX_DB.'_referer WHERE lid = :lid GROUP BY '.$count.' ORDER BY '.$ordby.' '.$ordsc, ['lid' => $a_id]);
    head();
    $cont = navi(0, 0, 0, 0);
    $massiv = [];
    $a = 0;
    while ([$hits, $uid, $name, $ip, $referer, $link, $date] = $db->sql_fetchrow($result)) {
        $massiv[] = [$hits, $uid, $name, $ip, $referer, $link, $date];
        $a++;
    }
    if (isArray($massiv)) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr>'
           .'<th>'._ID.'</th><th>'._NICKNAME.'</th><th>'._IP.'</th><th>'._REF_URL.'</th><th>'._IN_URL.'</th>'
           .'</tr></thead><tbody>';
        for ($i = $offset; $i < $tnum; $i++) {
            if (isset($massiv[$i])) {
                $name = ($massiv[$i][1]) ? user_info($massiv[$i][2]) : $massiv[$i][2];
                $cont .= '<tr>'
                   .'<td>'.$massiv[$i][0].'</td>'
                   .'<td>'.title_tip(_DATE.': '.date(_TIMESTRING, $massiv[$i][6])).$name.'</td>'
                   .'<td>'.user_geo_ip($massiv[$i][3], 4).'</td>'
                   .'<td>'.domain($massiv[$i][4], 35).'</td>'
                   .'<td>'.domain($massiv[$i][5], 15).'</td>'
                   .'</tr>';
            }
        }
        $cont .= '</tbody></table>';
        $numpages = ceil($a / $conf['auto_links']['anum']);
        $cont .= setPageNumbers('pagenum', '', $a, $numpages, $conf['auto_links']['anum'], 'name=auto_links&amp;op=stats&amp;a_id='.$a_id.'&amp;sort='.$sort.'&amp;order='.$order.'&amp;', $conf['auto_links']['anump']);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    foot();
}

function add(): void {
    global $db, $afile, $stop;
    $stop = $stop ?? [];
    $id = getVar('req', 'id', 'num');
    if ($id) {
        $result = $db->sql_query('SELECT id, sitename, description, link, mail, hits, outs FROM '.PREFIX_DB.'_auto_links WHERE id = :id', ['id' => $id]);
        [$a_id, $a_sitename, $a_description, $a_sitelink, $a_adminemail, $a_hits, $a_outs] = $db->sql_fetchrow($result);
    } else {
        $a_id = getVar('post', 'a_id', 'num');
        $a_sitename = getVar('post', 'a_sitename', 'title', '');
        $a_adminemail = getVar('post', 'a_adminemail', 'var', '');
        $a_description = getVar('post', 'a_description', 'text', '');
        $a_sitelink = getVar('post', 'a_sitelink', 'url', 'https://');
        $a_hits = getVar('post', 'a_hits', 'num', 0);
        $a_outs = getVar('post', 'a_outs', 'num', 0);
    }
    head();
    $cont = navi(0, 1, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => implode('<br>', $stop)]);
    if ($a_description) $cont .= preview($a_sitename, $a_description, '', '', 'auto_links');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
       .'<tr><td>'._SITENAME.':</td><td><input type="text" name="a_sitename" value="'.$a_sitename.'" maxlength="255" class="sl_form" placeholder="'._SITENAME.'" required></td></tr>'
       .'<tr><td>'._A_LINKS_E.':</td><td><input type="email" name="a_adminemail" value="'.$a_adminemail.'" maxlength="100" class="sl_form" placeholder="'._A_LINKS_E.'" required></td></tr>'
       .'<tr><td>'._A_LINKS_TEXT.':</td><td>'.textarea('1', 'a_description', $a_description, 'auto_links', '5', _A_LINKS_TEXT, '1').'</td></tr>'
       .'<tr><td>'._A_LINKS_L.':</td><td><input type="url" name="a_sitelink" value="'.$a_sitelink.'" maxlength="100" class="sl_form" placeholder="'._A_LINKS_L.'" required></td></tr>'
       .'<tr><td>'._HITS.':</td><td><input type="number" name="a_hits" value="'.$a_hits.'" class="sl_form" placeholder="'._HITS.'"></td></tr>'
       .'<tr><td>'._OUTS.':</td><td><input type="number" name="a_outs" value="'.$a_outs.'" class="sl_form" placeholder="'._OUTS.'"></td></tr>'
       .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="auto_links">'.ad_save('a_id', $a_id, 'save').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function save(): void {
    global $db, $afile, $stop;
    $a_id = getVar('post', 'a_id', 'num');
    $a_sitename = getVar('post', 'a_sitename', 'title', '');
    $a_description = getVar('post', 'a_description', 'text', '');
    $a_sitelink = url_filter(getVar('post', 'a_sitelink', 'url', 'https://'));
    $a_adminemail = getVar('post', 'a_adminemail', 'var', '');
    $a_hits = getVar('post', 'a_hits', 'num', 0);
    $a_outs = getVar('post', 'a_outs', 'num', 0);
    $stop = [];
    if (!$a_sitename) $stop[] = _CERROR10;
    if (!$a_description) $stop[] = _CERROR11;
    if (!$a_sitelink) $stop[] = _CERROR4;
    $posttype = getVar('post', 'posttype', 'var', '');
    if (!$stop && $posttype === 'save') {
        if ($a_id) {
            $db->sql_query('UPDATE '.PREFIX_DB.'_auto_links SET sitename = :name, description = :desc, link = :link, mail = :mail, hits = :hits, outs = :outs WHERE id = :id', ['name' => $a_sitename, 'desc' => $a_description, 'link' => $a_sitelink, 'mail' => $a_adminemail, 'hits' => $a_hits, 'outs' => $a_outs, 'id' => $a_id]);
        } else {
            $db->sql_query('INSERT INTO '.PREFIX_DB.'_auto_links (sitename, description, link, mail, hits, outs, added) VALUES (:name, :desc, :link, :mail, :hits, :outs, now())', ['name' => $a_sitename, 'desc' => $a_description, 'link' => $a_sitelink, 'mail' => $a_adminemail, 'hits' => $a_hits, 'outs' => $a_outs]);
        }
        setRedirect($afile.'.php?name=auto_links');
    } elseif ($posttype === 'delete') {
        del($a_id);
    } else {
        add();
    }
}

function del(int $id = 0): void {
    global $db, $afile;
    if (!$id) $id = getVar('req', 'id', 'num');
    if ($id) {
        $db->sql_query('DELETE FROM '.PREFIX_DB.'_auto_links WHERE id = :id', ['id' => $id]);
        $db->sql_query('DELETE FROM '.PREFIX_DB.'_referer WHERE lid = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=auto_links');
}

function nullhits(): void {
    global $db, $afile;
    $db->sql_query('UPDATE '.PREFIX_DB.'_auto_links SET hits = 0, outs = 0');
    $db->sql_query('DELETE FROM '.PREFIX_DB.'_referer WHERE lid != 0');
    setRedirect($afile.'.php?name=auto_links');
}

function noindel(): void {
    global $db, $afile;
    $db->sql_query('DELETE FROM '.PREFIX_DB.'_auto_links WHERE hits = 0');
    setRedirect($afile.'.php?name=auto_links');
}

function conf(): void {
    global $afile, $conf;
    head();
    $cont = navi(0, 4, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/auto_links.php');
    $cont .= setTemplateBasic('open');
    $path = 'templates/'.$conf['theme'].'/images/banners/';
    $opts = '';
    foreach (scandir($path) as $entry) {
        if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg)$/is', $entry)) {
            $sel = ($conf['auto_links']['img'] == $entry) ? ' selected' : '';
            $opts .= '<option value="'.$path.$entry.'"'.$sel.'>'.$entry.'</option>';
        }
    }
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
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
       .'<input type="hidden" name="name" value="auto_links"><input type="hidden" name="op" value="confsave">'
       .'<input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue">'
       .'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function confsave(): void {
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
    setRedirect($afile.'.php?name=auto_links&op=conf');
}

function info(): void {
    head();
    echo navi(0, 5, 0, 0).'<div id="repadm_info">'.adm_info(1, 'auto_links', 0).'</div>';
    foot();
}

switch ($op) {
    default: auto_links(); break;
    case 'stats': stats(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'del': del(); break;
    case 'nullhits': nullhits(); break;
    case 'noindel': noindel(); break;
    case 'conf': conf(); break;
    case 'confsave': confsave(); break;
    case 'info': info(); break;
}
