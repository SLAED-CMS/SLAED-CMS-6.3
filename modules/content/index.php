<?php
# Author: Eduard Laas
# Copyright © 2005 - 2022 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
	header('Location: ../../index.php');
	exit;
}
include('config/config_content.php');

function content() {
	global $prefix, $db, $admin_file, $conf, $confcn;
	head();
	$cont = setTemplateBasic('title', array('{%title%}' => _CONTENT));
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $confcn['num'];
	$result = $db->sql_query("SELECT id, title, text, time, counter FROM ".$prefix."_content WHERE time <= NOW() ORDER BY time DESC LIMIT ".$offset.", ".$confcn['num']);
	if ($db->sql_numrows($result) > 0) {
		$cont .= setTemplateBasic('open');
		$cont .= '<table class="sl_table_list_sort"><thead class="sl_table_list_head"><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._FUNCTIONS.'</th></tr></thead><tbody class="sl_table_list_body">';
		while (list($id, $title, $text, $time, $counter)= $db->sql_fetchrow($result)) {
			$moder = (is_moder($conf['name'])) ? '<a href="'.$admin_file.'.php?op=content_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$admin_file.'.php?op=content_delete&amp;id='.$id.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>||' : '';
			$edit = add_menu($moder.'<a href="index.php?name=content&amp;op=view&amp;id='.$id.'" title="'._SHOW.'">'._SHOW.'</a>');
			$cont .= '<tr id="'.$id.'">'
			.'<td><a href="#'.$id.'" title="'.$id.'" class="sl_pnum">'.$id.'</a></td>'
			.'<td>'.title_tip(_DATE.': '.format_time($time, _TIMESTRING).'<br>'._READS.': '.$counter).'<a href="'.getHref(array('name='.$conf['name'].'&op=view&id='.$id, $time, '', $title, $text, '', '', '')).'" title="'.$title.'">'.$title.'</a> '.new_graphic($time).'</td>'
			.'<td>'.$edit.'</td></tr>';
		}
		$cont .= '</tbody></table>';
		$cont .= setArticleNumbers('pagenum', $conf['name'], $confcn['num'], '', 'id', '_content', '', '', $confcn['nump']);
		$cont .= setTemplateBasic('close');
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function view() {
	global $prefix, $db, $conf, $confn, $admin_file;
	$id = getVar('get', 'id', 'num');
	$word = getVar('get', 'word', 'word');
	$result = $db->sql_query("SELECT id, title, text, field, url, time, refresh FROM ".$prefix."_content WHERE id = '".$id."' AND time <= NOW()");
	if ($db->sql_numrows($result) == 1) {
		$db->sql_query("UPDATE ".$prefix."_content SET counter = counter+1 WHERE id = '".$id."'");
		list($id, $title, $text, $field, $url, $time, $refresh) = $db->sql_fetchrow($result);
		if ($url) {
			$past = time() - $refresh;
			if (strtotime($time) < $past) {
				$content = rss_read($url, 1);
				$db->sql_query("UPDATE ".$prefix."_content SET text = '".$content."', time = NOW() WHERE id = '".$id."'");
			}
		}
		$fields = fields_out($field, $conf['name']);
		$fields = ($fields) ? '<br><br>'.$fields : '';
		$hometext = $text.$fields;
		head();
		echo setTemplateBasic('title', array('{%title%}' => $title)).setTemplateBasic('open').search_color(bb_decode($hometext, $conf['name']), $word).setTemplateBasic('close');
		foot();
	} else {
		header('Location: index.php?name='.$conf['name']);
	}
}

switch($op) {
	default: content(); break;
	case 'view': view(); break;
}