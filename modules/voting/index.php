<?php
# Author: Eduard Laas
# Copyright © 2005 - 2018 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
	header('Location: ../../index.php');
	exit;
}

function voting() {
	global $prefix, $db, $admin_file, $locale, $conf, $confv;
	$onum = ($conf['multilingual'] == 1) ? "(language = '".$locale."' OR language = '') AND modul = '' AND date <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')" : "modul = '' AND date <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')";
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $confv['num'];
	head();
	$cont = setTemplateBasic('title', array('{%title%}' => _VOTING));
	$result = $db->sql_query("SELECT id, title, questions, answer, date, enddate, comments, acomm, typ FROM ".$prefix."_voting WHERE ".$onum." ORDER BY id DESC LIMIT ".$offset.", ".$confv['num']);
	if ($db->sql_numrows($result) > 0) {
		$cont .= setTemplateBasic('voting-home-open', array('{%id%}' => _ID, '{%title%}' => _TITLE, '{%comm%}' => cutstr(_COMMENTS, 4, 1), '{%votes%}' => cutstr(_VOTES, 3, 1)));
		while (list($id, $stitle, $questions, $answer, $date, $enddate, $comm, $acomm, $typ) = $db->sql_fetchrow($result)) {
			$title = '<a href="'.getHref(array('name='.$conf['name'].'&op=view&id='.$id, $date, '', $stitle, str_replace('|', ' ', $questions), '', '', '')).'" title="'.$stitle.'">'.cutstr($stitle, 60).'</a> '.new_graphic($date);
			$comm = ($acomm && $comm) ? $comm : _NO;
			$vote = array_sum(explode('|', $answer));
			$type = ($typ == '1') ? _VOPEN : _VCLOSE;
			$report = _CHNGSTORY.': '.format_time($date, _TIMESTRING).'<br>'._ENDDATE.': '.format_time($enddate, _TIMESTRING).'<br>'._TYPE.': '.$type;
			$admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$admin_file.'.php?op=voting_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$admin_file.'.php?op=voting_delete&amp;id='.$id.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$stitle.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>', 1) : '';
			$cont .= setTemplateBasic('voting-home', array('{%id%}' => $id, '{%title%}' => $title, '{%comm%}' => $comm, '{%vote%}' => $vote, '{%info%}' => _INFO, '{%report%}' => $report, '{%admin%}' => $admin));
		}
		$cont .= setTemplateBasic('voting-home-close');
		$cont .= setArticleNumbers('pagenum', $conf['name'], $confv['num'], '', 'id', '_voting', '', $onum, $confv['nump']);
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function view() {
	global $prefix, $db, $conf, $confv;
	$id = getVar('get', 'id', 'num');
	head();
	$result = $db->sql_query("SELECT acomm FROM ".$prefix."_voting WHERE id = '".$id."' AND modul = '' AND date <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')");
	if ($db->sql_numrows($result) > 0) {
		list($acomm) = $db->sql_fetchrow($result);
		$cont = setTemplateBasic('title', array('{%title%}' => _VOTING)).setTemplateBasic('voting-basic', array('{%content%}' => '<div id="rep'.$conf['name'].'">'.getVoting($id, $conf['name']).'</div>'));
		if ($acomm) $cont .= setComShow($id, $acomm);
	} else {
		$cont = setTemplateWarning('warn', array('time' => '3', 'url' => '?name='.$conf['name'], 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

switch($op) {
	default: voting(); break;
	case 'view': view(); break;
}
?>