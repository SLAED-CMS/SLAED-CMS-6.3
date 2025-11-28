<?php
# Author: Eduard Laas
# Copyright © 2005 - 2017 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');

include('config/config_lang.php');

function lang_navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
	panel();
	$ops = ['lang_main', 'lang_conf', 'lang_info'];
	$lang = [_HOME, _PREFERENCES, _INFO];
	return navi_gen(_LANG_EDIT, 'lang.png', '', $ops, $lang, '', '', $opt, $tab, $subtab, $legacy);
}

function lang_main(): void {
	global $prefix, $db, $admin_file;
	$result = $db->sql_query("SELECT title, active, view FROM ".$prefix."_modules ORDER BY title ASC");
	while (list($ttl, $act, $view) = $db->sql_fetchrow($result)) {
		$modbase[$ttl] = $act;
		if ($view == 0) {
			$who_view[] = _MVALL;
		} elseif ($view == 1) {
			$who_view[] = _MVUSERS;
		} elseif ($view == 2) {
			$who_view[] = _MVADMIN;
		}
	}
	head();
	$cont = lang_navi(0, 0, 0, 0);
	$cont .= setTemplateBasic('open');
	$cont .= "<table class=\"sl_table_list_sort\"><thead><tr><th>"._ID."</th><th>"._NAME."</th><th>"._MODUL."</th><th>"._VIEW."</th><th class=\"{sorter: false}\">"._STATUS."</th><th class=\"{sorter: false}\">"._FUNCTIONS."</th></tr></thead><tbody>"
	."<tr><td>1</td><td>"._SYSTEM."</td><td>"._ALL."</td><td>"._MVALL."</td><td>".ad_status("", 1)."</td><td>".add_menu("<a href=\"".$admin_file.".php?op=lang_file&amp;lng_wh=admin/\" title=\""._FULLEDIT."\">"._ADMIN."</a>||<a href=\"".$admin_file.".php?op=lang_file\" title=\""._FULLEDIT."\">"._MODUL."</a>")."</td></tr>";
	$handle = opendir("modules");
	while (($file = readdir($handle)) !== false) {
		if (!preg_match("/\./", $file) && file_exists("modules/".$file."/index.php")) $mod[] = $file;
	}
	closedir($handle);
	sort($mod);
	$ci = count($mod);
	for ($i = 0; $i < $ci; $i++) {
		$a = $i + 2;
		$act = ($modbase[$mod[$i]]) ? 1 : 0;
		$cont .= "<tr><td>".$a."</td><td>".deflmconst($mod[$i])."</td><td>".$mod[$i]."</td><td>".$who_view[$i]."</td><td>".ad_status("", $act)."</td>";
		
		$eadmin = "";
		$emodul = "";
		$sep = 0;
		if (is_dir("modules/".$mod[$i]."/admin")) {
			if (is_dir("modules/".$mod[$i]."/admin/language")) {
				$eadmin = "<a href=\"".$admin_file.".php?op=lang_file&amp;mod_dir=modules/".$mod[$i]."/&amp;lng_wh=admin/\" title=\""._FULLEDIT."\">"._ADMIN."</a>";
				$sep = 1;
			}
		}
		if (is_file("modules/".$mod[$i]."/index.php")) {
			if (is_dir("modules/".$mod[$i]."/language")) {
				$sep = ($sep) ? "||" : "";
				$emodul = $sep."<a href=\"".$admin_file.".php?op=lang_file&amp;mod_dir=modules/".$mod[$i]."/\" title=\""._FULLEDIT."\">"._MODUL."</a>";
			}
		}
		$cont .= "<td>".add_menu($eadmin.$emodul)."</td></tr>";
	}
	$cont .= "</tbody></table>";
	$cont .= setTemplateBasic('close');
	echo $cont;
	foot();
}

function lang_file(): void {
	global $admin_file, $confla;
	head();
	$cont = lang_navi(0, 0, 0, 0);
	$mod_dir = getVar('get', 'mod_dir', 'var', '');
	$adm_fl = getVar('get', 'adm_fl', 'bool', false);
	$lng_wh = getVar('get', 'lng_wh', 'var', '');
	$lng_cn = [];
	$cnst_arr = [];
	$dir = opendir($mod_dir.$lng_wh."language");
	while (($file = readdir($dir)) !== false) {
		if (preg_match("#^lang\-(.+)\.php#", $file, $matches)) $lng_cn[] = $matches[1];
	}
	closedir($dir);
	$gl_tmp = $cnst_arr;
	$cnst_arr = [];
	$cj = count($lng_cn);
	for ($j = 0; $j < $cj; $j++) {
		$lng_src = $mod_dir.$lng_wh."language/lang-".$lng_cn[$j].".php";
		$permtest = end_chmod($lng_src, 666);
		if ($permtest) $cont .= tpl_warn("warn", $permtest, "", "", "warn");
		$lng = file_get_contents($lng_src);
		preg_match_all("#define\(\"(.*)\"(.*),(.*)\"(.*)\"\);#iU", $lng, $out);
		unset($out[0]);
		$ci = count($out[1]);
		for ($i = 0; $i < $ci; $i++) {
			$lng_arr[$lng_cn[$j]][$out[1][$i]] = $out[4][$i];
			$cnst_tmp[$out[1][$i]] = "";
		}
		$cnst_arr = array_merge($cnst_arr, $cnst_tmp);
		unset($cnst_tmp);
	}
	$sch_tmp = [];
	unset($out);
	$gl_tmp = array_keys($gl_tmp);
	$cnst_arr = array_merge($cnst_arr, $sch_tmp);
	$cnst_arr = array_keys($cnst_arr);
	$cnst_arr = array_diff($cnst_arr, $gl_tmp);
	unset($gl_tmp, $sch_tmp, $cnst_tmp);
	sort($cnst_arr);
	$cont .= setTemplateBasic('open');
	$cont .= "<form action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_form\">";
	$ci = count($cnst_arr) + $confla['count'];
	for ($i = 0; $i < $ci; $i++) {
		$n = $i + 1;
		$hr = ($i == "0") ? "" : "<tr><td colspan=\"3\"><hr></td></tr>";
		$valc = isset($cnst_arr[$i]) ? $cnst_arr[$i] : "";
		$cont .= $hr."<tr id=\"".$n."\"><td>"._CONST.":</td><td><input type=\"text\" name=\"cnst[]\" value=\"".$valc."\" class=\"sl_form\" placeholder=\""._CONST."\"></td><td><a href=\"#".$n."\" title=\""._ID.": ".$n."\" class=\"sl_pnum\">".$n."</a></td></tr>";
		$cj = count($lng_cn);
		for ($j = 0; $j < $cj; $j++) {
			$val = ($valc) ? trim(str_replace('\"', "&quot;", $lng_arr[$lng_cn[$j]][$cnst_arr[$i]])) : "";
			if ($lng_cn[$j] == $confla['lang']) {
				$class = "from_".$i;
				$button = "";
			} else {
				$class = "to_".$i."-".$j;
				$langs = ["german" => "de", "polish" => "pl"];
				$floc = substr(strtr($confla['lang'], $langs), 0, 2);
				$tloc = substr(strtr($lng_cn[$j], $langs), 0, 2);
				$button = "<input type=\"button\" OnClick=\"TranslateLang('from_".$i."', 'to_".$i."-".$j."', '".$floc."-".$tloc."', '"._ERRORTR."', '".$confla['key']."');\" value=\""._OK."\" title=\""._EAUTOTR."\" class=\"sl_but_blue\">";
			}
			$cont .= "<tr><td>".deflang($lng_cn[$j]).":</td><td><input type=\"text\" name=\"lng[".$lng_cn[$j]."][]\" value=\"".$val."\" class=\"sl_form ".$class."\" placeholder=\"".deflang($lng_cn[$j])."\"></td><td>".$button."</td></tr>";
		}
	}
	$cont .= "<tr><td colspan=\"3\" class=\"sl_center\">";
	$cj = count($lng_cn);
	for ($j = 0; $j < $cj; $j++) {
		$cont .= "<input type=\"hidden\" name=\"lcn[]\" value=\"".$lng_cn[$j]."\">";
	}
	$cont .= "<input type=\"hidden\" name=\"lwh\" value=\"".$lng_wh."\"><input type=\"hidden\" name=\"mod_dir\" value=\"".$mod_dir."\"><input type=\"hidden\" name=\"op\" value=\"lang_save\"><input type=\"hidden\" name=\"refer\" value=\"1\"><input type=\"submit\" value=\""._SAVECHANGES."\" class=\"sl_but_blue\"></td></tr></table></form>";
	$cont .= setTemplateBasic('close');
	echo $cont;
	foot();
}

function lang_save(): void {
	global $admin_file;
	$mod_dir = getVar('post', 'mod_dir', 'var', '');
	$lng_wh = getVar('post', 'lwh', 'var', '');
	$lng_cn = getVar('post', 'lcn[]', 'var') ?: [];
	$out = [];
	$out[1] = getVar('post', 'cnst[]', 'var') ?: [];
	$out[2] = getVar('post', 'lng', 'var', []);
	$cj = count($lng_cn);
	for ($j = 0; $j < $cj; $j++) {
		$lng_cnj = $lng_cn[$j];
		$lng_str = '<?php'.PHP_EOL.'# Author: Eduard Laas'.PHP_EOL.'# Copyright © 2005 - '.date('Y').' SLAED'.PHP_EOL.'# License: GNU GPL 3'.PHP_EOL.'# Website: slaed.net'.PHP_EOL.PHP_EOL;
		$ci = count($out[1]);
		for ($i = 0; $i < $ci; $i++) {
			if (empty($out[2][$lng_cnj][$i])) continue;
			if (empty($out[1][$i])) continue;
			$cons = trim($out[1][$i]);
			$in = ["\'", "\\$", "<?php", "?>"];
			$ou = ["'", "\$", "&lt;?php", "?&gt;"];
			$cont = trim(str_replace($in, $ou, $out[2][$lng_cnj][$i]));
			$lng_str .= "define(\"".$cons."\",\"".$cont."\");\r\n";
		}
		$lng_str .= "?>";
		$lng_src = $mod_dir.$lng_wh."language/lang-".$lng_cnj.".php";
		$handle = fopen($lng_src, "wb");
		fwrite($handle,$lng_str);
		fclose($handle);
	}
	referer($admin_file.".php?op=lang_main");
}

function lang_conf(): void {
	global $admin_file, $confla;
	head();
	$cont = lang_navi(0, 1, 0, 0);
	$permtest = end_chmod("config/config_lang.php", 666);
	if ($permtest) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $permtest]);
	$cont .= setTemplateBasic('open');
	$cont .= "<form name=\"post\" action=\"".$admin_file.".php\" method=\"post\"><table class=\"sl_table_conf\">"
	."<tr><td>"._LANGKEY.":<div class=\"sl_small\">"._LANGKEYI."</div></td><td><input type=\"text\" name=\"key\" value=\"".$confla['key']."\" class=\"sl_conf\" placeholder=\""._LANGKEY."\" required></td></tr>"
	."<tr><td>"._LANGTR.":</td><td><select name=\"lang\" class=\"sl_conf\">".language($confla['lang'], 1)."</select></td></tr>"
	."<tr><td>"._LANGCOUNT.":</td><td><input type=\"number\" name=\"count\" value=\"".$confla['count']."\" class=\"sl_conf\" placeholder=\""._LANGCOUNT."\" required></td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\"><input type=\"hidden\" name=\"op\" value=\"lang_conf_save\"><input type=\"submit\" value=\""._SAVECHANGES."\" class=\"sl_but_blue\"></td></tr></table></form>";
	$cont .= setTemplateBasic('close');
	echo $cont;
	foot();
}

function lang_conf_save(): void {
	global $admin_file, $confla;
	$cont = [
		'key' => getVar('post', 'key', 'var', ''),
		'lang' => getVar('post', 'lang', 'var', ''),
		'count' => getVar('post', 'count', 'num', 0)
	];
	setConfigFile('config_lang.php', 'confla', $cont, $confla);
	header('Location: '.$admin_file.'.php?op=lang_conf');
}

function lang_info(): void {
	head();
	echo lang_navi(0, 2, 0, 0).'<div id="repadm_info">'.adm_info(1, 0, 'lang').'</div>';
	foot();
}

switch($op) {
	case 'lang_main':
	lang_main();
	break;
	
	case 'lang_file':
	lang_file();
	break;
	
	case 'lang_save':
	lang_save();
	break;
	
	case 'lang_conf':
	lang_conf();
	break;
	
	case 'lang_conf_save':
	lang_conf_save();
	break;
	
	case 'lang_info':
	lang_info();
	break;
}
?>