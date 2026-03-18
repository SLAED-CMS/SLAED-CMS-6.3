<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('content')) die('Illegal file access');

function content(): void {
    global $db, $afile, $conf;
        setHead();
    $cont = setAdminNavi(['ops' => ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=conf', 'name=content&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO]]);
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['content']['anum'] ?? 10;
    $anump = $conf['content']['anump'] ?? 10;
    $offset = ($num - 1) * $anum;
    $result = $db->getSqlQuery('SELECT id, title, time, counter FROM '.PREFIX_DB.'_content ORDER BY id DESC LIMIT '.$offset.', '.$anum);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._DATE.'</th><th>'.cutstr(_READS, 4, 1).'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';
        while ([$id, $title, $time, $counter] = $db->getSqlRow($result)) {
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
    setFoot();
}

function add(): void {
    global $db, $afile, $stop;
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
    $cont = setAdminNavi(['ops' => ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=conf', 'name=content&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
    $fields = ($field) ? '<br><br>'.fields_out($field, 'content') : '';
    if ($body) $cont .= preview($title, $body, '', $field, 'content');
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
    .'<tr><td>'._TEXT.':</td><td>'.textarea('1', 'body', $body.$fields, 'content', '25', _TEXT, '0').'</td></tr>'
    .fields_in($field, 'content')
    .'<tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'time', $time, 16, 'sl_form').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="content">'.ad_save('cid', $cid, 'save').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
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
        del($cid);
    } else {
        add();
    }
}

function del(int $cid = 0): void {
    global $db, $afile;
    $id = $cid ? $cid : getVar('req', 'id', 'num', 0);
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_content WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=content');
}

function conf(): void {
    global $afile, $conf;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=conf', 'name=content&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/content.php');
    $cont .= setTemplateBasic('open');
    $cont .= setTemplateBasic('form-conf', [
        '{%route%}'  => $afile,
        '{%module%}' => 'content',
        '{%op%}'     => 'saveconf',
        '{%save%}'   => _SAVECHANGES,
        '{%fields%}' => '',
        '{%_c33%}'   => _C_33,
        '{%num%}'    => $conf['content']['num'],
        '{%_c34%}'   => _C_34,
        '{%anum%}'   => $conf['content']['anum'],
        '{%_c35%}'   => _C_35,
        '{%nump%}'   => $conf['content']['nump'],
        '{%_c36%}'   => _C_36,
        '{%anump%}'  => $conf['content']['anump'],
        'if_flag'    => ['content' => true],
    ]);
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
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
    setHead();
    $cont = setAdminNavi(['ops' => ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=conf', 'name=content&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 3]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
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






