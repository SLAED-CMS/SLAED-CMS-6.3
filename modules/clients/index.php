<?php
# Copyright © 2005 - 2015 SLAED
# Website: http://www.slaed.net

if (!defined("MODULE_FILE")) {
	header("Location: ../../index.php");
	exit;
}
get_lang($conf['name']);

function systems() {
	global $prefix, $db, $conf, $admin_file, $user, $stop, $info;
	head($conf['defis']." "._PRODUCTSINFO);
	$cont = tpl_eval("title", _PRODUCTSINFO);
	$cont .= navi();
	if ($stop) $cont .= tpl_warn("warn", $stop, "", "", "warn");
	if ($info) $cont .= tpl_warn("warn", $info, "", "", "info");
	$result = $db->sql_query("SELECT id, title, infotext, url, num, hits, prod_id, status FROM ".$prefix."_clients_down WHERE status != '0'");
	if ($db->sql_numrows($result) > 0) {
		$user_id = intval($user['0']);
		$cont .= tpl_eval("open");
		$cont .= "<table class=\"sl_table_list_sort\"><thead class=\"sl_table_list_head\"><tr><th>"._ID."</th><th>"._CTITLE."</th><th>"._CVERSION."</th><th>"._CLOADS."</th><th>"._FUNCTIONS."</th></tr></thead><tbody class=\"sl_table_list_body\">";
		$i = 0;
		$a = 1;
		while (list($id, $title, $infotext, $url, $num, $hits, $prod_id, $status) = $db->sql_fetchrow($result)) {
			$tpath = "uploads/clients/thumb/".$id."_".$user_id.".zip";
			$dtitle = (file_exists($tpath)) ? _CDOWN : _GZIPGEN;
			$moder = (is_moder($conf['name'])) ? "<a href=\"".$admin_file.".php?op=clients_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||" : "";
			$acont = add_menu($moder."<a OnClick=\"HideShow('cl".$i."', 'blind', 'up', 500);\" title=\""._CINFO."\">"._CINFO."</a>||<a href=\"index.php?name=".$conf['name']."&amp;op=download&amp;id=".$id."&amp;prod_id=".$prod_id."\" title=\"".$dtitle."\">".$dtitle."</a>||<a href=\"index.php?name=".$conf['name']."&amp;op=generator&amp;id=".$id."&amp;prod_id=".$prod_id."\" title=\""._CLIZENS."\">"._CLIZENS."</a>");
			$time = (file_exists("uploads/clients/".$url)) ? date(_TIMESTRING, filemtime("uploads/clients/".$url)) : _NO_INFO;
			$cont .= "<tr id=\"".$a."\">"
			."<td><a href=\"#".$a."\" title=\"".$a."\" class=\"sl_pnum\">".$a."</a></td>"
			."<td>".title_tip(_CDATE.": ".$time).$title."</td>"
			."<td>".$num."</td>"
			."<td>".$hits."</td>"
			."<td>".$acont."</td></tr>";
			$conts .= "<div id=\"cl".$i."\" class=\"sl_none\">".bb_decode($infotext, $conf['name'])."</div>";
			$i++;
			$a++;
		}
		$cont .= "</tbody></table>".$conts;
		$cont .= tpl_eval("close");
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function download() {
	global $prefix, $db, $user, $conf, $stop, $info;
	$prod_id = intval($_GET['prod_id']);
	$user_id = intval($user['0']);
	$result = $db->sql_query("SELECT website FROM ".$prefix."_clients WHERE active = '1' AND id_user = '".$user_id."'");
	if (is_user() && $db->sql_numrows($result) > 0) {
		$id = intval($_GET['id']);
		list($pid, $url, $num) = $db->sql_fetchrow($db->sql_query("SELECT id, url, num FROM ".$prefix."_clients_down WHERE status != '0' AND id = '".$id."'"));
		$tpath = "uploads/clients/thumb/".$pid."_".$user_id.".zip";
		if (!file_exists($tpath)) {
			$ipath = "uploads/clients/images";
			$path = "uploads/clients/".$url;
			$code = base64_encode($user_id."-".getip()."-".getagent());
		
			# Øèôðóåì ôàéëû
			$input = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S' ,'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '=');
			$output = array('{', '©', '"', '§', '$', 'Ö', '&', '/', '(', '', '¹', '¡', '<', '%', '‹', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'å', 'B', 'ø', 'D', 'E', 'ÿ', 'G', 'ä', 'I', 'J', 'K', 'L', '‡', 'Ø', 'O', 'Æ', 'Q', '·', 'Â' ,'!', 'U', '†', '¶', 'X', 'Y', 'Z', '¿');
			$sourse = str_replace($input, $output, $code);
			if (file_exists($path."/html/templates/admin/images/admin/admins.png")) save_hidden($path."/html/templates/admin/images/admin/admins.png", $ipath."/admins.png", $sourse."IEND®B`‚");
			if (file_exists($path."/html/templates/admin/images/admin/forum.png")) save_hidden($path."/html/templates/admin/images/admin/forum.png", $ipath."/forum.png", $code);
			if (file_exists($path."/html/templates/admin/images/language/german.png")) save_hidden($path."/html/templates/admin/images/language/german.png", $ipath."/german.png", $code);
			if (file_exists($path."/html/templates/admin/images/admin/menu.png")) save_hidden($path."/html/templates/admin/images/admin/menu.png", $ipath."/menu.png", $sourse."IEND®B`‚".$code);
			
			if (file_exists($path."/html/config/license.txt")) generator($path."/html/config");
			if (file_exists($path."/setup/config/license.txt")) generator($path."/setup/config");
			if (file_exists($path."/update/config/license.txt")) generator($path."/update/config");
			require_once("pclzip.lib.php");
			$archive = new PclZip($tpath);
			if ($archive->create($path, "", $path) == 0) {
				$stop = _CLERROR2;
				systems();
				#die("Error: ".$archive->errorInfo(true));
			} else {
				$info = _GZIPOK;
				systems();
			}
		} else {
			$db->sql_query("UPDATE ".$prefix."_clients_down SET hits = hits+1 WHERE id = '".$id."'");
			stream($tpath, date("d.m.Y")."_".str_replace(" ", "_", $num).".zip");
		}
	} else {
		$stop = _CLERROR;
		systems();
	}
}

function save_hidden($path, $ipath, $code) {
	# ×èòàåì è ïåðåçàïèñûâàåì ôàéë
	$content = file_get_contents($ipath);
	$code = $content.$code;
	$fp = fopen($path, "wb");
	fwrite($fp, $code);
	fclose($fp);
	# Ìåíÿåì âðåìÿ ôàéëà
	$atime = filemtime($ipath);
	touch($path, $atime, $atime);
}

function generator($path="") {
	global $prefix, $db, $user, $conf, $stop;
	$prod_id = intval($_GET['prod_id']);
	$user_id = intval($user['0']);
	$result = $db->sql_query("SELECT website FROM ".$prefix."_clients WHERE active = '1' AND id_user = '".$user_id."'");
	if (is_user() && $db->sql_numrows($result) > 0) {
		while (list($domain) = $db->sql_fetchrow($result)) $domains[] = $domain;
		$domains = preg_replace("/http\:\/\/|www\./i", "", implode(",", $domains));
		$id = intval($_GET['id']);
		list($pass) = $db->sql_fetchrow($db->sql_query("SELECT code FROM ".$prefix."_clients_down WHERE status != '0' AND id = '".$id."'"));
		$massiv = explode(",", $domains);
		foreach ($massiv as $val) {
			if ($val != "") {
				$code .= md5($pass.$val.$pass)."\n";
				$code .= md5($pass."www.".$val.$pass)."\n";
			}
		}
		$code .= md5($pass."localhost".$pass)."\n";
		$code .= md5($pass."127.0.0.1".$pass);
		$dir = ($path) ? $path : "uploads/clients/thumb/";
		$nfile = ($path) ? "license" : $user_id;
		$fp = fopen($dir."/".$nfile.".txt", "wb");
		fwrite($fp, $code);
		fclose($fp);
		if (!$path) stream($dir."/".$user_id.".txt", "license.txt");
	} else {
		$stop = _CLERROR;
		systems();
	}
}

switch($op) {
	default:
	systems();
	break;
	
	case "download":
	download();
	break;
	
	case "generator":
	generator();
	break;
}
?>