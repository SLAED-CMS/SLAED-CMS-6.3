<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('content')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=conf', 'name=content&amp;op=info'];
    $lang = [_HOME, _ADD, _PREFERENCES, _INFO];
    return getAdminTabs(_CONTENT, 'content.png', '', $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function content(): void {
    global $db, $afile, $conf;
        head();
    $cont = navi(0, 0, 0, 0);
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['content']['anum'] ?? 10;
    $anump = $conf['content']['anump'] ?? 10;
    $offset = ($num - 1) * $anum;
    $result = $db->sql_query('SELECT id, title, time, counter FROM '.PREFIX_DB.'_content ORDER BY id DESC LIMIT '.$offset.', '.$anum);
    if ($db->sql_numrows($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._DATE.'</th><th>'.cutstr(_READS, 4, 1).'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $title, $time, $counter] = $db->sql_fetchrow($result)) {
            if (time() >= strtotime($time)) {
                $view = '<a href="index.php?name=content&amp;op=view&amp;id='.$id.'" title="'._MVIEW.'">'._MVIEW.'</a>||';
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $cont .= '<tr><td>'.$id.'</td>'
            .'<td>'.title_tip(_URL.': '.$conf['homeurl'].'/index.php?name=content&amp;op=view&amp;id='.$id.'<br>'._ORTYPEURL.': '.$conf['homeurl'].'/index.php?go=rss&amp;name=content&amp;id='.$id).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 50).'</span></td>'
            .'<td>'.format_time($time, _TIMESTRING).'</td>'
            .'<td>'.$counter.'</td>'
            .'<td>'.ad_status('', $active).'</td>'
            .'<td>'.add_menu($view.'<a href="'.$afile.'.php?name=content&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=content&amp;op=del&amp;id='.$id.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', '', $anum, 'name=content&amp;', 'id', '_content', '', '', $anump);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    foot();
}

function add(): void {
    global $db, $afile, $stop;
    $id = getVar('req', 'id', 'num', 0);
    if ($id) {
        $result = $db->sql_query('SELECT id, title, text, field, url, time, refresh FROM '.PREFIX_DB.'_content WHERE id = :id', ['id' => $id]);
        [$cid, $title, $text, $field, $url, $time, $refresh] = $db->sql_fetchrow($result);
    } else {
        $cid = getVar('post', 'cid', 'num', 0);
        $title = getVar('post', 'title', 'title', '');
        $text = getVar('post', 'text', 'text', '');
        $field = getVar('post', 'field', 'field');
        $url = getVar('post', 'url', 'text', '');
        $time = save_datetime(1, 'time');
        $refresh = getVar('post', 'refresh', 'num', 0);
    }
    head();
    $cont = navi(0, 1, 0, 0);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
    $fields = ($field) ? '<br><br>'.fields_out($field, 'content') : '';
    if ($text) $cont .= preview($title, $text, '', $field, 'content');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.$title.'" maxlength="100" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._RSSFILE.':<div class="sl_small">'._RSSINFO.'</div></td><td><input type="text" name="url" value="'.$url.'" maxlength="200" class="sl_form" placeholder="'._RSSFILE.'"></td></tr>'
    .'<tr><td>'._REFRESHTIME.':<div class="sl_small">'._REFINFO.'</div></td><td><select name="refresh" class="sl_form">'
    .'<option value="1800"';
    if ($refresh == '1800') $cont .= ' selected';
    $cont .= '>30 '._MIN.'.</option>'
    .'<option value="3600"';
    if ($refresh == '3600' || !$refresh) $cont .= ' selected';
    $cont .= '>1 '._HOUR.'</option>'
    .'<option value="18000"';
    if ($refresh == '18000') $cont .= ' selected';
    $cont .= '>5 '._HOUR.'.</option>'
    .'<option value="36000"';
    if ($refresh == '36000') $cont .= ' selected';
    $cont .= '>10 '._HOUR.'.</option>'
    .'<option value="86400"';
    if ($refresh == '86400') $cont .= ' selected';
    $cont .= '>24 '._HOUR.'.</option>'
    .'</select></td></tr>'
    .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'text', $text.$fields, 'content', '25', _TEXT, '0').'</td></tr>'
    .fields_in($field, 'content')
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'time', $time, 16, 'sl_form').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="content">'.ad_save('cid', $cid, 'save').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function save(): void {
    global $db, $afile, $stop;
    $cid = getVar('post', 'cid', 'num', 0);
    $title = getVar('post', 'title', 'title', '');
    $url = getVar('post', 'url', 'text', '');
    $text = getVar('post', 'text', 'text', '');
    $text = ($url) ? rss_read($url, 1) : $text;
    $field = getVar('post', 'field', 'field');
    $time = save_datetime(1, 'time');
    $refresh = getVar('post', 'refresh', 'num', 0);
    if (!$title) $stop[] = _CERROR;
    if (!$text && !$url) $stop[] = _CERROR1;
    if (!$text && $url) $stop[] = _RSSFAIL;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype == 'save') {
        if ($cid) {
            $db->sql_query('UPDATE '.PREFIX_DB.'_content SET title = :title, text = :text, field = :field, url = :url, time = :time, refresh = :refresh WHERE id = :cid', ['title' => $title, 'text' => $text, 'field' => $field, 'url' => $url, 'time' => $time, 'refresh' => $refresh, 'cid' => $cid]);
        } else {
            $db->sql_query('INSERT INTO '.PREFIX_DB.'_content (title, text, field, url, time, refresh, counter) VALUES (:title, :text, :field, :url, :time, :refresh, \'0\')', ['title' => $title, 'text' => $text, 'field' => $field, 'url' => $url, 'time' => $time, 'refresh' => $refresh]);
        }
        setRedirect($afile.'.php?name=content');
    } elseif ($posttype == 'delete') {
        del($cid);
    } else {
        add();
    }
}

function del(int $cid = 0): void {
    global $db, $afile;
    $id = $cid ? $cid : getVar('req', 'id', 'num', 0);
    if ($id) $db->sql_query('DELETE FROM '.PREFIX_DB.'_content WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=content');
}

function conf(): void {
    global $afile, $conf;
        head();
    $cont = navi(0, 2, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/content.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._C_33.':</td><td><input type="number" name="num" value="'.$conf['content']['num'].'" class="sl_conf" placeholder="'._C_33.'" required></td></tr>'
    .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.$conf['content']['anum'].'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
    .'<tr><td>'._C_35.':</td><td><input type="number" name="nump" value="'.$conf['content']['nump'].'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
    .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.$conf['content']['anump'].'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="content"><input type="hidden" name="op" value="saveconf"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function saveconf(): void {
    global $afile;
    $cont = [
        'num' => getVar('post', 'num', 'num', 25),
        'anum' => getVar('post', 'anum', 'num', 25),
        'nump' => getVar('post', 'nump', 'num', 10),
        'anump' => getVar('post', 'anump', 'num', 10),
    ];
    setConfigFile('content.php', $cont);
    setRedirect($afile.'.php?name=content&op=conf');
}

function info(): void {
    head();
    echo navi(0, 3, 0, 0).'<div id="repadm_info">'.adm_info(1, 'content', 0).'</div>';
    foot();
}

switch ($op) {
    default: content(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'del': del(); break;
    case 'conf': conf(); break;
    case 'saveconf': saveconf(); break;
    case 'info': info(); break;
}




