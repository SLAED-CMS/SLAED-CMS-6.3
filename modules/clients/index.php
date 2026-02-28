<?php
# Copyright © 2005 - 2026 SLAED
# Website: http://www.slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function systems(): void {
	global $db, $conf, $afile, $user, $stop, $info;
	setHead(['title' => _PRODUCTSINFO]);
	$cont = setTemplateBasic('title', ['{%title%}' => _PRODUCTSINFO]);
	$cont .= navi();
	if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
	if ($info) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $info]);
	$result = $db->sql_query('SELECT id, title, infotext, url, num, hits, prod_id, status FROM '.PREFIX_DB.'_clients_down WHERE status != \'0\'');
	if ($db->sql_numrows($result) > 0) {
		$uid = (int)($user[0] ?? 0);
		$conts = '';
		$cont .= setTemplateBasic('open');
		$cont .= '<table class="sl_table_list_sort"><thead class="sl_table_list_head"><tr><th>'._ID.'</th><th>'._CTITLE.'</th><th>'._CVERSION.'</th><th>'._CLOADS.'</th><th>'._FUNCTIONS.'</th></tr></thead><tbody class="sl_table_list_body">';
		$i = 0;
		$a = 1;
		while ([$id, $title, $infotext, $url, $num, $hits, $prod, $status] = $db->sql_fetchrow($result)) {
			$tpath = 'uploads/clients/thumb/'.$id.'_'.$uid.'.zip';
			$dtitle = (file_exists($tpath)) ? _CDOWN : _GZIPGEN;
			$moder = (is_moder($conf['name'])) ? '<a href="'.$afile.'.php?op=clients_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||' : '';
			$acont = add_menu($moder.'<a OnClick="HideShow(\'cl'.$i.'\', \'blind\', \'up\', 500);" title="'._CINFO.'">'._CINFO.'</a>||<a href="index.php?name='.$conf['name'].'&amp;op=download&amp;id='.$id.'&amp;prod_id='.$prod.'" title="'.$dtitle.'">'.$dtitle.'</a>||<a href="index.php?name='.$conf['name'].'&amp;op=generator&amp;id='.$id.'&amp;prod_id='.$prod.'" title="'._CLIZENS.'">'._CLIZENS.'</a>');
			$time = (file_exists('uploads/clients/'.$url)) ? date(_TIMESTRING, filemtime('uploads/clients/'.$url)) : _NO_INFO;
			$cont .= '<tr id="'.$a.'">'
			.'<td><a href="#'.$a.'" title="'.$a.'" class="sl_pnum">'.$a.'</a></td>'
			.'<td>'.title_tip(_CDATE.': '.$time).$title.'</td>'
			.'<td>'.$num.'</td>'
			.'<td>'.$hits.'</td>'
			.'<td>'.$acont.'</td></tr>';
			$conts .= '<div id="cl'.$i.'" class="sl_none">'.bb_decode($infotext, $conf['name']).'</div>';
			$i++;
			$a++;
		}
		$cont .= '</tbody></table>'.$conts;
		$cont .= setTemplateBasic('close');
	} else {
		$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
	}
	echo $cont;
	setFoot();
}

function download(): void {
	global $db, $user, $conf, $stop, $info;
	$uid = (int)($user[0] ?? 0);
	$result = $db->sql_query('SELECT website FROM '.PREFIX_DB.'_clients WHERE active = 1 AND id_user = :user_id', ['user_id' => $uid]);
	if (is_user() && $db->sql_numrows($result) > 0) {
		$id = getVar('get', 'id', 'num');
		[$pid, $url, $num] = $db->sql_fetchrow($db->sql_query('SELECT id, url, num FROM '.PREFIX_DB.'_clients_down WHERE status != 0 AND id = :id', ['id' => $id]));
		$tpath = 'uploads/clients/thumb/'.$pid.'_'.$uid.'.zip';
		if (!file_exists($tpath)) {
			$ipath = 'uploads/clients/images';
			$path = 'uploads/clients/'.$url;
			$code = base64_encode($uid.'-'.getip().'-'.getagent());
		
			# Ð¨Ð¸Ñ„Ñ€ÑƒÐµÐ¼ Ñ„Ð°Ð¹Ð»Ñ‹
			$input = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S' ,'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '='];
			$output = ['{', 'Â©', '"', 'Â§', '$', 'Ð¦', '&', '/', '(', '', 'â„–', 'ÐŽ', '<', '%', 'â€¹', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'Ðµ', 'B', 'Ñˆ', 'D', 'E', 'Ñ', 'G', 'Ð´', 'I', 'J', 'K', 'L', 'â€¡', 'Ð¨', 'O', 'Ð–', 'Q', 'Â·', 'Ð’' ,'!', 'U', 'â€ ', 'Â¶', 'X', 'Y', 'Z', 'Ñ—'];
			$sourse = str_replace($input, $output, $code);
			if (file_exists($path.'/html/templates/admin/images/admin/admins.png')) save_hidden($path.'/html/templates/admin/images/admin/admins.png', $ipath.'/admins.png', $sourse.'IENDÂ®B`â€š');
			if (file_exists($path.'/html/templates/admin/images/admin/forum.png')) save_hidden($path.'/html/templates/admin/images/admin/forum.png', $ipath.'/forum.png', $code);
			if (file_exists($path.'/html/templates/admin/images/language/german.png')) save_hidden($path.'/html/templates/admin/images/language/german.png', $ipath.'/german.png', $code);
			if (file_exists($path.'/html/templates/admin/images/admin/menu.png')) save_hidden($path.'/html/templates/admin/images/admin/menu.png', $ipath.'/menu.png', $sourse.'IENDÂ®B`â€š'.$code);
			
			if (file_exists($path.'/html/config/license.txt')) generator($path.'/html/config');
			if (file_exists($path.'/setup/config/license.txt')) generator($path.'/setup/config');
			if (file_exists($path.'/update/config/license.txt')) generator($path.'/update/config');
			require_once('pclzip.lib.php');
			$archive = new PclZip($tpath);
			if ($archive->create($path, '', $path) == 0) {
				$stop = _CLERROR2;
				systems();
				#die("Error: ".$archive->errorInfo(true));
			} else {
				$info = _GZIPOK;
				systems();
			}
		} else {
			$db->sql_query('UPDATE '.PREFIX_DB.'_clients_down SET hits = hits+1 WHERE id = :id', ['id' => $id]);
			stream($tpath, date('d.m.Y').'_'.str_replace(' ', '_', $num).'.zip');
		}
	} else {
		$stop = _CLERROR;
		systems();
	}
}

function save_hidden(string $path, string $ipath, string $code): void {
	# Ð§Ð¸Ñ‚Ð°ÐµÐ¼ Ð¸ Ð¿ÐµÑ€ÐµÐ·Ð°Ð¿Ð¸ÑÑ‹Ð²Ð°ÐµÐ¼ Ñ„Ð°Ð¹Ð»
	$content = file_get_contents($ipath);
	if ($content === false) {
		return;
	}
	$code = $content.$code;
	$fp = fopen($path, 'wb');
	if ($fp === false) {
		return;
	}
	fwrite($fp, $code);
	fclose($fp);
	# ÐœÐµÐ½ÑÐµÐ¼ Ð²Ñ€ÐµÐ¼Ñ Ñ„Ð°Ð¹Ð»Ð°
	$atime = filemtime($ipath);
	if ($atime !== false) {
		touch($path, $atime, $atime);
	}
}

function generator(string $path = ''): void {
	global $db, $user, $conf, $stop;
	$uid = (int)($user[0] ?? 0);
	$result = $db->sql_query('SELECT website FROM '.PREFIX_DB.'_clients WHERE active = 1 AND id_user = :user_id', ['user_id' => $uid]);
	if (is_user() && $db->sql_numrows($result) > 0) {
		$domains = [];
		$code = '';
		while ([$domain] = $db->sql_fetchrow($result)) $domains[] = $domain;
		$domains = preg_replace('#https?://|www\.#i', '', implode(',', $domains));
		$id = getVar('get', 'id', 'num');
		[$pass] = $db->sql_fetchrow($db->sql_query('SELECT code FROM '.PREFIX_DB.'_clients_down WHERE status != 0 AND id = :id', ['id' => $id]));
		$massiv = explode(',', $domains);
		foreach ($massiv as $val) {
			if ($val != '') {
				$code .= md5($pass.$val.$pass)."\n";
				$code .= md5($pass.'www.'.$val.$pass)."\n";
			}
		}
		$code .= md5($pass.'localhost'.$pass)."\n";
		$code .= md5($pass.'127.0.0.1'.$pass);
		$dir = ($path) ? $path : 'uploads/clients/thumb/';
		$nfile = ($path) ? 'license' : $uid;
		$fp = fopen($dir.'/'.$nfile.'.txt', 'wb');
		if ($fp === false) {
			if (!$path) {
				$stop = _CLERROR2;
				systems();
			}
			return;
		}
		fwrite($fp, $code);
		fclose($fp);
		if (!$path) stream($dir.'/'.$uid.'.txt', 'license.txt');
	} else {
		$stop = _CLERROR;
		systems();
	}
}

switch($op) {
	default:
	systems();
	break;
	
	case 'download':
	download();
	break;
	
	case 'generator':
	generator();
	break;
}
