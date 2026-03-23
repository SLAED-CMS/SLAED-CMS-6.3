<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function voting(): void {
	global $db, $afile, $locale, $conf, $tpl;
	$onum = ($conf['multilingual'] == 1) ? "(lang = '".$locale."' OR lang = '') AND modul = '' AND time <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')" : "modul = '' AND time <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')";
	$num = getVar('get', 'num', 'num', '1');
	$offset = ($num - 1) * $conf['voting']['num'];
	setHead(['title' => _VOTING]);
	$cont = $tpl->getHtmlFrag('title', ['title' => _VOTING]);
	$result = $db->getSqlQuery('SELECT id, title, answer, time, enddate, comments, acomm, typ FROM '.PREFIX_DB.'_voting WHERE '.$onum.' ORDER BY id DESC LIMIT '.$offset.', '.$conf['voting']['num']);
	if ($db->getSqlRowCount($result) > 0) {
		$cont .= $tpl->getHtmlFrag('voting-home-wrap', ['open' => true, 'id' => _ID, 'title' => _TITLE, 'comm' => cutstr(_COMMENTS, 4, 1), 'votes' => cutstr(_VOTES, 3, 1)]);
		while ([$id, $stitle, $answer, $date, $enddate, $comm, $acomm, $typ] = $db->getSqlRow($result)) {
			$thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $stitle]);
			$comm = ($acomm && $comm) ? $comm : _NO;
			$vote = array_sum(explode('|', $answer));
			$type = ($typ == '1') ? _VOPEN : _VCLOSE;
			$report = _CHNGSTORY.': '.format_time($date, _TIMESTRING).'<br>'._ENDDATE.': '.format_time($enddate, _TIMESTRING).'<br>'._TYPE.': '.$type;
			$admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$afile.'.php?name=voting&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=voting&amp;op=delete&amp;id='.$id.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars($stitle, ENT_QUOTES).'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>') : '';
			$cont .= $tpl->getHtmlFrag('voting-home', [
				'id' => $id,
				'title_href' => $thref,
				'title_attr' => htmlspecialchars($stitle, ENT_QUOTES),
				'title_text' => cutstr($stitle, 60),
				'title_new' => new_graphic($date),
				'comm' => $comm,
				'vote' => $vote,
				'info' => _INFO,
				'report' => $report,
				'admin' => $admin,
			]);
		}
		$cont .= $tpl->getHtmlFrag('voting-home-wrap', []);
		$cont .= setArticleNumbers('pagenum', $conf['name'], $conf['voting']['num'], '', 'id', '_voting', '', $onum, $conf['voting']['nump']);
	} else {
		$cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
	}
	echo $cont;
	setFoot();
}

function view(): void {
	global $db, $conf, $tpl;
	$id = getVar('get', 'id', 'num');
	$result = $db->getSqlQuery('SELECT title, time, acomm FROM '.PREFIX_DB.'_voting WHERE id = :id AND modul = \'\' AND time <= NOW() AND (enddate >= NOW() AND status = \'0\' OR status = \'1\')', ['id' => $id]);
	if ($db->getSqlRowCount($result) > 0) {
		[$title, $date, $acomm] = $db->getSqlRow($result);
		setHead([
			'title' => $title,
			'ctitle' => _VOTING,
			'desc' => cutstr(trim(strip_tags($title)), 160),
			'time' => $date,
			'author' => $conf['sitename'],
		]);
		$cont = $tpl->getHtmlFrag('title', ['title' => _VOTING]).setTemplateBasic('voting-basic', ['{%content%}' => '<div id="rep'.$conf['name'].'">'.getVoting($id, $conf['name']).'</div>']);
		if ($acomm) $cont .= setComShow($id, $acomm);
	} else {
		setHead(['title' => _VOTING]);
		$meta = '<meta http-equiv="refresh" content="3; url=index.php?name='.$conf['name'].'">';
		$cont = $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO, 'meta' => $meta]);
	}
	echo $cont;
	setFoot();
}

switch($op) {
	default: voting(); break;
	case 'view': view(); break;
}
