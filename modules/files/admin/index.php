<?php
# Author: Eduard Laas
# Copyright © 2005 - 2018 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined("ADMIN_FILE") || !is_admin_modul("files")) die("Illegal file access");


function files_navi() {
	panel();
	$narg = func_get_args();
	$ops = array("files", "files_add", "files&amp;status=1", "files&amp;status=2", "files_conf", "files_info");
	$lang = array(_HOME, _ADD, _NEW, _BROCFILES, _PREFERENCES, _INFO);
	return navi_gen(_FILES, "files.png", "", $ops, $lang, "", "", $narg[0], $narg[1], $narg[2], $narg[3]);
}

function files() {
	global $db, $admin_file, $conff, $confu;
	head();
	$num = getVar('get', 'num', 'num', 1);
	$offset = ($num-1) * $conff['anum'];
	$offset = intval($offset);
	$status_get = getVar('get', 'status', 'num');
	if ($status_get == 1) {
		$status = "0";
		$field = "op=files&amp;status=1&amp;";
		$refer = "&amp;refer=1";
		$cont = files_navi(0, 2, 0, 0);
	} elseif ($status_get == 2) {
		$status = "2";
		$field = "op=files&amp;status=2&amp;";
		$refer = "";
		$cont = files_navi(0, 3, 0, 0);
	} else {
		$status = "1";
		$field = "op=files&amp;";
		$refer = "";
		$cont = files_navi(0, 0, 0, 0);
	}
	$result = $db->sql_query('SELECT f.lid, f.cid, f.name, f.title, f.date, f.ip_sender, c.title, u.user_name FROM '.PREFIX_DB.'_files AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.user_id) WHERE f.status = :status ORDER BY f.date DESC LIMIT '.$offset.', '.$conff['anum'], ['status' => $status]);
	if ($db->sql_numrows($result) > 0) {
		$cont .= tpl_eval("open");
		$cont .= "<table class=\"sl_table_list_sort\"><thead><tr><th>"._ID."</th><th>"._TITLE."</th><th>"._POSTEDBY."</th><th class=\"{sorter: false}\">"._STATUS."</th><th class=\"{sorter: false}\">"._FUNCTIONS."</th></tr></thead><tbody>";
		while (list($id, $cid, $uname, $title, $date, $ip_sender, $ctitle, $user_name) = $db->sql_fetchrow($result)) {
			$post = ($user_name) ? user_info($user_name) : (($uname) ? $uname : $confu['anonym']);
			$ctitle = ($cid) ? $ctitle : _NO;
			$ip_sender = ($ip_sender) ? user_geo_ip($ip_sender, 4) : _NO;
			$broc = ($status == 2) ? "<a href=\"".$admin_file.".php?op=files_ignore&amp;id=".$id."\" title=\""._IGNORE."\">"._IGNORE."</a>||" : "";
			if ($status && time() >= strtotime($date)) {
				$ad_view = "<a href=\"index.php?name=files&amp;op=view&amp;id=".$id."\" title=\""._MVIEW."\">"._MVIEW."</a>||";
				$active = "1";
			} else {
				$ad_view = "";
				$active = "0";
			}
			$cont .= "<tr><td>".$id."</td>"
			."<td>".title_tip(_CATEGORY.": ".$ctitle."<br>"._DATE.": ".format_time($date, _TIMESTRING)."<br>"._IP.": ".$ip_sender)."<span title=\"".$title."\" class=\"sl_note\">".cutstr($title, 60)."</span></td>"
			."<td>".$post."</td>"
			."<td>".ad_status("", $active)."</td>"
			."<td>".add_menu($ad_view.$broc."<a href=\"".$admin_file.".php?op=files_add&amp;id=".$id."\" title=\""._FULLEDIT."\">"._FULLEDIT."</a>||<a href=\"".$admin_file.".php?op=files_delete&amp;id=".$id.$refer."\" OnClick=\"return DelCheck(this, '"._DELETE." &quot;".$title."&quot;?');\" title=\""._ONDELETE."\">"._ONDELETE."</a>")."</td></tr>";
		}
		$cont .= "</tbody></table>";
		$cont .= setArticleNumbers("pagenum", "", $conff['anum'], $field, "lid", "_files", "", "status = '".$status."'", $conff['anump']);
		$cont .= tpl_eval("close");
	} else {
		$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO));
	}
	echo $cont;
	foot();
}

function files_add() {
	global $db, $admin_file, $conff, $confu, $stop;
	if (isset($_REQUEST['id'])) {
		$fid = getVar('req', 'id', 'num');
		$result = $db->sql_query('SELECT f.cid, f.name, f.title, f.description, f.bodytext, f.url, f.date, f.filesize, f.version, f.email, f.homepage, f.ihome, f.acomm, u.user_name FROM '.PREFIX_DB.'_files AS f LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.user_id) WHERE lid = :fid', ['fid' => $fid]);
		list($cid, $uname, $title, $description, $bodytext, $url, $date, $filesize, $version, $email, $homepage, $ihome, $acomm, $user_name) = $db->sql_fetchrow($result);
		$postname = ($user_name) ? $user_name : (($uname) ? $uname : $confu['anonym']);
	} else {
		$fid = getVar('post', 'fid', 'num');
		$cid = getVar('post', 'cid', 'num');
		$title = save_text(getVar('post', 'title', 'text'), 1);
		$description = save_text(getVar('post', 'description', 'text'));
		$bodytext = save_text(getVar('post', 'bodytext', 'text'));
		$url = getVar('post', 'url', 'text');
		$path = text_filter(getVar('post', 'path', 'text'));
		$date = save_datetime(1, "date");
		$ihome = getVar('post', 'ihome', 'num');
		$acomm = getVar('post', 'acomm', 'num');
		$filesize = getVar('post', 'filesize', 'num');
		$version = getVar('post', 'version', 'text');
		$postname = getVar('post', 'postname', 'name');
		$email = getVar('post', 'email', 'text');
		$homepage = getVar('post', 'homepage', 'text', "http://");
	}
	head();
	$cont = files_navi(0, 1, 0, 0);
	if ($stop) $cont .= tpl_warn("warn", $stop, "", "", "warn");
	if ($description) $cont .= preview($title, $description, $bodytext, "", "files");
	$link_url = ($url) ? "<a href=\"".$url."\" target=\"_blank\" title=\""._DOWNLLINK."\">"._URL."</a>" : _URL;
	if (file_exists($url)) {
		$handle = opendir($conff['path']);
		$directory = "";
		while (false !== ($file = readdir($handle))) {
			$selected = ($path == $conff['path']."/".$file) ? "selected" : "";
			if (!preg_match("/\./", $file)) $directory .= "<option value=\"".$conff['path']."/".$file."\" ".$selected.">".$conff['path']."/".$file."</option>";
		}
		closedir($handle);
	}
	$cont .= tpl_eval("open");
	$cont .= "<form name=\"post\" enctype=\"multipart/form-data\" action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_form\">"
	."<tr><td>"._POSTEDBY.":</td><td>".get_user_search("postname", $postname, "25", "sl_form", "1")."</td></tr>"
	."<tr><td>"._TITLE.":</td><td><input type=\"text\" name=\"title\" value=\"".$title."\" class=\"sl_form\" placeholder=\""._TITLE."\" required></td></tr>"
	."<tr><td>"._CATEGORY.":</td><td>".getcat("files", $cid, "cid", "sl_form", "<option value=\"\">"._HOMECAT."</option>")."</td></tr>"
	."<tr><td>"._TEXT.":</td><td>".textarea("1", "description", $description, "files", "5", _TEXT, "1")."</td></tr>"
	."<tr><td>"._ENDTEXT.":</td><td>".textarea("2", "bodytext", $bodytext, "files", "15", _ENDTEXT, "0")."</td></tr>"
	."<tr><td>"._AUEMAIL.":</td><td><input type=\"email\" name=\"email\" value=\"".$email."\" class=\"sl_form\" placeholder=\""._AUEMAIL."\"></td></tr>"
	."<tr><td>"._SITE.":</td><td><input type=\"url\" name=\"homepage\" value=\"".$homepage."\" class=\"sl_form\" placeholder=\""._SITE."\"></td></tr>"
	."<tr><td>"._FILE_USER.":</td><td><input type=\"file\" name=\"userfile\" class=\"sl_form\"></td></tr>"
	."<tr><td>"._FILE_SITE.":</td><td><input type=\"text\" name=\"sitefile\" class=\"sl_form\" placeholder=\""._FILE_SITE."\"></td></tr>"
	."<tr><td>".$link_url.":</td><td><input type=\"text\" name=\"url\" value=\"".$url."\" class=\"sl_form\" placeholder=\""._URL."\"></td></tr>";
	if (file_exists($url)) $cont .= "<tr><td>"._FILE_DIR.":</td><td><select name=\"path\" class=\"sl_form\"><option value=\"\">"._NO."</option><option value=\"".$conff['path']."\">".$conff['path']."</option>".$directory."</select></td></tr>";
	$cont .= "<tr><td>"._VERSION.":</td><td><input type=\"text\" name=\"version\" value=\"".$version."\" class=\"sl_form\" placeholder=\""._VERSION."\"></td></tr>"
	."<tr><td>"._SIZENOTE.":</td><td><input type=\"number\" name=\"filesize\" value=\"".$filesize."\" class=\"sl_form\" placeholder=\""._SIZENOTE."\"></td></tr>"
	."<tr><td>"._CHNGSTORY.":</td><td>".datetime(1, "date", $date, 16, "sl_form")."</td></tr>"
	."<tr><td>"._COMMENTS.":</td><td>".com_access("acomm", $acomm, "sl_form")."</td></tr>"
	."<tr><td>"._PUBHOME."</td><td>".radio_form($ihome, "ihome")."</td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\">".ad_save("fid", $fid, "files_save")."</td></tr></table></form>";
	$cont .= tpl_eval("close");
	echo $cont;
	foot();
}

function files_save() {
	global $db, $admin_file, $stop, $conff;
	$fid = getVar('post', 'fid', 'num');
	$cid = getVar('post', 'cid', 'num');
	$postname = getVar('post', 'postname', 'name');
	$title = save_text(getVar('post', 'title', 'text'), 1);
	$description = save_text(getVar('post', 'description', 'text'));
	$bodytext = save_text(getVar('post', 'bodytext', 'text'));
	$url = getVar('post', 'url', 'text');
	$path = text_filter(getVar('post', 'path', 'text'));
	$date = save_datetime(1, "date");
	$ihome = getVar('post', 'ihome', 'num');
	$acomm = getVar('post', 'acomm', 'num');
	$filesize = getVar('post', 'filesize', 'num');
	$version = text_filter(getVar('post', 'version', 'text'));
	$email = text_filter(getVar('post', 'email', 'text'));
	$homepage = url_filter(getVar('post', 'homepage', 'text'));
	$stop = array();
	if (!$title) $stop[] = _CERROR;
	if (!$description) $stop[] = _CERROR1;
	if (!$postname) $stop[] = _CERROR3;
	if (!$fid && $db->sql_numrows($db->sql_query('SELECT title FROM '.PREFIX_DB.'_files WHERE title = :title', ['title' => $title])) > 0) $stop[] = _MEDIAEXIST;
	$filename = upload(1, $conff['path'], $conff['typefile'], $conff['max_size'], "files", "1600", "1600", '1');
	$url = ($filename) ? $conff['path']."/".$filename : $url;
	$filesize = ($filename) ? filesize($url) : $filesize;
	if ($stop) {
		$stop = $stop;
	} elseif (!$url && getVar('post', 'posttype', 'text') == "save") {
		$stop[] = _UPLOADEROR2;
	}
	if (!$stop && getVar('post', 'posttype', 'text') == "save") {
		$postid = (is_user_id($postname)) ? is_user_id($postname) : "";
		$postname = (!is_user_id($postname)) ? text_filter(substr($postname, 0, 25)) : "";
		if ($fid) {
			if ($path) {
				$filel = array_reverse(explode("/", $url));
				if (file_exists($url)) {
					$newfile = $path."/".$filel[0];
					rename($url, $newfile);
					$url = $path."/".$filel[0];
				}
			}
			$db->sql_query('UPDATE '.PREFIX_DB.'_files SET cid = :cid, uid = :postid, name = :postname, title = :title, description = :description, bodytext = :bodytext, url = :url, date = :date, filesize = :filesize, version = :version, email = :email, homepage = :homepage, ihome = :ihome, acomm = :acomm, status = \'1\' WHERE lid = :fid', ['cid' => $cid, 'postid' => $postid, 'postname' => $postname, 'title' => $title, 'description' => $description, 'bodytext' => $bodytext, 'url' => $url, 'date' => $date, 'filesize' => $filesize, 'version' => $version, 'email' => $email, 'homepage' => $homepage, 'ihome' => $ihome, 'acomm' => $acomm, 'fid' => $fid]);
		} else {
			$ip = getip();
			$db->sql_query('INSERT INTO '.PREFIX_DB.'_files (lid, cid, uid, name, title, description, bodytext, url, date, filesize, version, email, homepage, ip_sender, ihome, acomm, status) VALUES (NULL, :cid, :postid, :postname, :title, :description, :bodytext, :url, :date, :filesize, :version, :email, :homepage, :ip, :ihome, :acomm, \'1\')', ['cid' => $cid, 'postid' => $postid, 'postname' => $postname, 'title' => $title, 'description' => $description, 'bodytext' => $bodytext, 'url' => $url, 'date' => $date, 'filesize' => $filesize, 'version' => $version, 'email' => $email, 'homepage' => $homepage, 'ip' => $ip, 'ihome' => $ihome, 'acomm' => $acomm]);
		}
		header("Location: ".$admin_file.".php?op=files");
	} elseif (getVar('post', 'posttype', 'text') == "delete") {
		files_delete($fid);
	} else {
		files_add();
	}
}

function files_delete() {
	global $db, $admin_file, $id;
	$arg = func_get_args();
	$id = ($arg[0]) ? $arg[0] : $id;
	if ($id) {
		list($url) = $db->sql_fetchrow($db->sql_query('SELECT url FROM '.PREFIX_DB.'_files WHERE lid = :id', ['id' => $id]));
		if (file_exists($url)) unlink($url);
		$db->sql_query('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = \'files\'', ['id' => $id]);
		$db->sql_query('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'files\'', ['id' => $id]);
		$db->sql_query('DELETE FROM '.PREFIX_DB.'_files WHERE lid = :id', ['id' => $id]);
	}
	referer($admin_file.".php?op=files");
}

function files_conf() {
	global $db, $admin_file, $conff;
	head();
	$cont = files_navi(0, 4, 0, 0);
	$cont .= checkPerms('files.php');
	$cont .= tpl_eval("open");
	$cont .= "<form action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_conf\">"
	."<tr><td>"._CDEFIS.":</td><td><input type=\"text\" name=\"defis\" value=\"".urldecode($conff['defis'])."\" maxlength=\"25\" class=\"sl_conf\" placeholder=\""._CDEFIS."\" required></td></tr>"
	."<tr><td>"._F_0.":</td><td><input type=\"text\" name=\"temp\" value=\"".$conff['temp']."\" class=\"sl_conf\" placeholder=\""._F_0."\" required></td></tr>"
	."<tr><td>"._F_1.":</td><td><input type=\"text\" name=\"path\" value=\"".$conff['path']."\" class=\"sl_conf\" placeholder=\""._F_1."\" required></td></tr>"
	."<tr><td>"._FSIZE._FIN.":</td><td><input type=\"number\" name=\"max_size\" value=\"".$conff['max_size']."\" class=\"sl_conf\" placeholder=\""._FSIZE._FIN."\" required></td></tr>"
	."<tr><td>"._FTYPE.":<div class=\"sl_small\">"._NOKOMA."</div></td><td><input type=\"text\" name=\"typefile\" value=\"".$conff['typefile']."\" class=\"sl_conf\" placeholder=\""._FTYPE."\" required></td></tr>"
	."<tr><td>"._PAGELINKNUM.":</td><td><input type=\"number\" name=\"linknum\" value=\"".$conff['linknum']."\" class=\"sl_conf\" placeholder=\""._PAGELINKNUM."\" required></td></tr>"
	."<tr><td>"._C_13.":</td><td><input type=\"number\" name=\"listnum\" value=\"".$conff['listnum']."\" class=\"sl_conf\" placeholder=\""._C_13."\" required></td></tr>"
	."<tr><td>"._C_33.":</td><td><input type=\"number\" name=\"num\" value=\"".$conff['num']."\" class=\"sl_conf\" placeholder=\""._C_33."\" required></td></tr>"
	."<tr><td>"._C_34.":</td><td><input type=\"number\" name=\"anum\" value=\"".$conff['anum']."\" class=\"sl_conf\" placeholder=\""._C_34."\" required></td></tr>"
	."<tr><td>"._C_35.":</td><td><input type=\"number\" name=\"nump\" value=\"".$conff['nump']."\" class=\"sl_conf\" placeholder=\""._C_35."\" required></td></tr>"
	."<tr><td>"._C_36.":</td><td><input type=\"number\" name=\"anump\" value=\"".$conff['anump']."\" class=\"sl_conf\" placeholder=\""._C_36."\" required></td></tr>"
	."<tr><td>"._STREAM.":</td><td><select name=\"stream\" class=\"sl_conf\">"
	."<option value=\"0\"";
	if ($conff['stream'] == "0") $cont .= " selected";
	$cont .= ">"._STREAM_NO."</option>"
	."<option value=\"1\"";
	if ($conff['stream'] == "1") $cont .= " selected";
	$cont .= ">"._STREAM_1."</option>"
	."<option value=\"2\"";
	if ($conff['stream'] == "2") $cont .= " selected";
	$cont .= ">"._STREAM_2."</option>"
	."</select></td></tr>"
	."<tr><td>"._HOMCAT."</td><td>".radio_form($conff['homcat'], "homcat")."</td></tr>"
	."<tr><td>"._VIEWCAT."</td><td>".radio_form($conff['viewcat'], "viewcat")."</td></tr>"
	."<tr><td>"._C_32."</td><td>".radio_form($conff['catdesc'], "catdesc")."</td></tr>"
	."<tr><td>"._C_15."</td><td>".radio_form($conff['subcat'], "subcat")."</td></tr>"
	."<tr><td>"._ADDAMAIL."</td><td>".radio_form($conff['addmail'], "addmail")."</td></tr>"
	."<tr><td>"._F_8."</td><td>".radio_form($conff['add'], "add")."</td></tr>"
	."<tr><td>"._F_9."</td><td>".radio_form($conff['addquest'], "addquest")."</td></tr>"
	."<tr><td>"._F_11."</td><td>".radio_form($conff['broc'], "broc")."</td></tr>"
	."<tr><td>"._F_12."</td><td>".radio_form($conff['down'], "down")."</td></tr>"
	."<tr><td>"._UPFILE."</td><td>".radio_form($conff['upload'], "upload")."</td></tr>"
	."<tr><td>"._C_37."</td><td>".radio_form($conff['autor'], "autor")."</td></tr>"
	."<tr><td>"._C_17."</td><td>".radio_form($conff['date'], "date")."</td></tr>"
	."<tr><td>"._C_18."</td><td>".radio_form($conff['read'], "read")."</td></tr>"
	."<tr><td>"._F_2."</td><td>".radio_form($conff['hits'], "hits")."</td></tr>"
	."<tr><td>"._C_19."</td><td>".radio_form($conff['rate'], "rate")."</td></tr>"
	."<tr><td>"._C_20."</td><td>".radio_form($conff['letter'], "letter")."</td></tr>"
	."<tr><td>"._PAGELINK."</td><td>".radio_form($conff['link'], "link")."</td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\"><input type=\"hidden\" name=\"op\" value=\"files_conf_save\"><input type=\"submit\" value=\""._SAVECHANGES."\" class=\"sl_but_blue\"></td></tr></table></form>";
	$cont .= tpl_eval("close");
	echo $cont;
	foot();
}

function files_conf_save() {
	global $admin_file;
	$post_defis = getVar('post', 'defis', 'text');
	$xdefis = ($post_defis) ? urlencode($post_defis) : "%3E";
	$protect = array("\n" => "", "\t" => "", "\r" => "", " " => "");
	$max_size = getVar('post', 'max_size', 'num', 1048576);
	$xmax_size = (!intval($max_size)) ? 1048576 : $max_size;
	$typefile = getVar('post', 'typefile', 'text');
	$xtypefile = (!$typefile) ? "zip,gzip,7z,rar,tar" : strtolower(strtr($typefile, $protect));
	$cont = [
		'defis' => $xdefis,
		'temp' => getVar('post', 'temp', 'text'),
		'path' => getVar('post', 'path', 'text'),
		'max_size' => $xmax_size,
		'typefile' => $xtypefile,
		'linknum' => getVar('post', 'linknum', 'num'),
		'listnum' => getVar('post', 'listnum', 'num'),
		'num' => getVar('post', 'num', 'num'),
		'anum' => getVar('post', 'anum', 'num'),
		'nump' => getVar('post', 'nump', 'num'),
		'anump' => getVar('post', 'anump', 'num'),
		'stream' => getVar('post', 'stream', 'num'),
		'homcat' => getVar('post', 'homcat', 'num'),
		'viewcat' => getVar('post', 'viewcat', 'num'),
		'catdesc' => getVar('post', 'catdesc', 'num'),
		'subcat' => getVar('post', 'subcat', 'num'),
		'addmail' => getVar('post', 'addmail', 'num'),
		'add' => getVar('post', 'add', 'num'),
		'addquest' => getVar('post', 'addquest', 'num'),
		'broc' => getVar('post', 'broc', 'num'),
		'down' => getVar('post', 'down', 'num'),
		'upload' => getVar('post', 'upload', 'num'),
		'autor' => getVar('post', 'autor', 'num'),
		'date' => getVar('post', 'date', 'num'),
		'read' => getVar('post', 'read', 'num'),
		'hits' => getVar('post', 'hits', 'num'),
		'rate' => getVar('post', 'rate', 'num'),
		'letter' => getVar('post', 'letter', 'num'),
		'link' => getVar('post', 'link', 'num'),
	];
	setConfigFile('files.php', $cont);
	header("Location: ".$admin_file.".php?op=files_conf");
}

function files_info() {
	head();
	echo files_navi(0, 5, 0, 0)."<div id=\"repadm_info\">".adm_info(1, "files", 0)."</div>";
	foot();
}

switch ($op) {
	case "files":
	files();
	break;
	
	case "files_add":
	files_add();
	break;
	
	case "files_save":
	files_save();
	break;
	
	case "files_delete":
	files_delete();
	break;
	
	case "files_ignore":
	$id = getVar('req', 'id', 'num');
	$db->sql_query('UPDATE '.PREFIX_DB.'_files SET status = \'1\' WHERE lid = :id', ['id' => $id]);
	header("Location: ".$admin_file.".php?op=files&status=2");
	break;
	
	case "files_conf":
	files_conf();
	break;
	
	case "files_conf_save":
	files_conf_save();
	break;
	
	case "files_info":
	files_info();
	break;
}
?>
