<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function content(): void {
    global $db, $afile, $conf;
    $limit = (int)($conf['content']['num'] ?? ($conf['content']['num'] ?? 10));
    $nump = (int)($conf['content']['nump'] ?? ($conf['content']['nump'] ?? 5));
    if ($limit < 1) $limit = 10;
    if ($nump < 1) $nump = 5;
    setHead(['title' => _CONTENT]);
    $cont = setTemplateBasic('title', ['{%title%}' => _CONTENT]);
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $limit;
    $result = $db->getSqlQuery('SELECT id, title, text, time, counter FROM '.PREFIX_DB.'_content WHERE time <= NOW() ORDER BY time DESC LIMIT '.$offset.', '.$limit);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead class="sl_table_list_head"><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._FUNCTIONS.'</th></tr></thead><tbody class="sl_table_list_body">';
        while ([$id, $title, $text, $time, $counter]= $db->getSqlRow($result)) {
            $moder = (is_moder($conf['name'])) ? '<a href="'.$afile.'.php?op=content_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=content_delete&amp;id='.$id.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>||' : '';
            $edit = add_menu($moder.'<a href="index.php?name=content&amp;op=view&amp;id='.$id.'" title="'._SHOW.'">'._SHOW.'</a>');
            $cont .= '<tr id="'.$id.'">'
            .'<td><a href="#'.$id.'" title="'.$id.'" class="sl_pnum">'.$id.'</a></td>'
            .'<td>'.title_tip(_DATE.': '.format_time($time, _TIMESTRING).'<br>'._READS.': '.$counter).'<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title]).'" title="'.$title.'">'.$title.'</a> '.new_graphic($time).'</td>'
            .'<td>'.$edit.'</td></tr>';
        }
        $cont .= '</tbody></table>';
        $cont .= setArticleNumbers('pagenum', $conf['name'], $limit, '', 'id', '_content', '', '', $nump);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $conf;
    $id = getVar('get', 'id', 'num');
    $word = getVar('get', 'word', 'word');
    $result = $db->getSqlQuery('SELECT id, title, text, field, url, time, refresh FROM '.PREFIX_DB.'_content WHERE id = :id AND time <= NOW()', ['id' => $id]);
    if ($db->getSqlRowCount($result) == 1) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_content SET counter = counter+1 WHERE id = :id', ['id' => $id]);
        [$id, $title, $text, $field, $url, $time, $refresh] = $db->getSqlRow($result);
        if ($url) {
            $past = time() - $refresh;
            if (strtotime($time) < $past) {
                $conf['content'] = rss_read($url, 1);
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_content SET text = :text, time = NOW() WHERE id = :id', ['text' => $conf['content'], 'id' => $id]);
            }
        }
        $fields = fields_out($field, $conf['name']);
        $fields = ($fields) ? '<br><br>'.$fields : '';
        $hometext = $text.$fields;
        $seodesc = cutstr(trim(strip_tags(bb_decode($hometext, $conf['name']))), 160);
        $seoimg = getImgText($hometext, '', false);
        $seoimg = $seoimg ? $conf['homeurl'].'/'.$seoimg : '';
        setHead([
            'title' => $title,
            'desc' => $seodesc,
            'img' => $seoimg,
            'time' => $time,
            'author' => $conf['sitename'],
        ]);
        echo setTemplateBasic('title', ['if_flag' => ['is_view' => true], '{%title%}' => $title]).setTemplateBasic('open').search_color(filterMarkdown($hometext, $conf['name'], false), $word).setTemplateBasic('close');
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch($op) {
    default: content(); break;
    case 'view': view(); break;
}
