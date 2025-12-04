<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');
include('config/config_global.php');

function configNavi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0, string $extra = ''): string {
	panel();
	$ops = ($opt == 1) ? ['name=config&amp;op=show', 'name=config&amp;op=show', 'name=config&amp;op=show', 'name=config&amp;op=show', 'name=config&amp;op=show', 'name=config&amp;op=show', 'name=config&amp;op=show', 'name=config&amp;op=show', 'name=config&amp;op=info'] : ['', '', '', '', '', '', '', '', 'name=config&amp;op=info'];
	$lang = [_GENPREF, _SEO, _MULTILINGUAL, _CENSORS, _SEARCH, _BOTSOPT, _OPTIMIZE, _MAILOPT, _INFO];
	return getAdminTabs(_PREFERENCES, 'preferences.png', 'name=config', $ops, $lang, [], [], $tab, $subtab, $legacy, $extra);
}

function config() {
	global $prefix, $db, $admin_file, $conf;
	head();
	$cont = configNavi(0, 0, 0, 0, 'configure');
	$permtest = end_chmod('config/config_global.php', 666);
	if ($permtest) $cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'warn', 'text' => $permtest));
	$cont .= tpl_eval('open');
	$cont .= "<form name=\"post\" action=\"".$admin_file.".php\" method=\"post\">"
	."<div id=\"tabc0\" class=\"tabcont\">"
	."<table class=\"sl_table_conf\">"
	."<tr><td>"._VERSION.":</td><td><a href=\"//slaed.net\" target=\"_blank\" title=\""._VERSION."\">SLAED CMS ".$conf['version']."</a></td></tr>"
	."<tr><td>"._SITENAME.":</td><td><input type=\"text\" name=\"sitename\" value=\"".$conf['sitename']."\" maxlength=\"255\" class=\"sl_conf\" placeholder=\""._SITENAME."\" required></td></tr>"
	."<tr><td>"._SITEURL.":</td><td><input type=\"url\" name=\"homeurl\" value=\"".$conf['homeurl']."\" maxlength=\"255\" class=\"sl_conf\" placeholder=\""._SITEURL."\" required></td></tr>"
	."<tr><td>"._LOGO.":</td><td><select name=\"site_logo\" id=\"img_replace\" class=\"sl_conf\">";
	$path = "templates/".$conf['theme']."/images/logos/";
	$dir = opendir($path);
	while (false !== ($entry = readdir($dir))) {
		if (preg_match("/(\.gif|\.png|\.jpg|\.jpeg)$/is", $entry) && $entry != "." && $entry != "..") {
			$sel = ($conf['site_logo'] == $entry) ? " selected" : "";
			$cont .= "<option value=\"".$path.$entry."\"".$sel.">".$entry."</option>";
		}
	}
	closedir($dir);
	$cont .= "</select></td></tr>"
	."<tr><td>"._SITELOGO.":</td><td><img src=\"".$path.$conf['site_logo']."\" id=\"picture\" alt=\""._SITELOGO."\"></td></tr>"
	."<tr><td>"._DESCRIPTION.":</td><td><textarea name=\"slogan\" cols=\"65\" rows=\"5\" class=\"sl_conf\" placeholder=\""._DESCRIPTION."\" required>".$conf['slogan']."</textarea></td></tr>"
	."<tr><td>"._ADMININFO.":<div class=\"sl_small\">"._ADMININFODES."</div></td><td><textarea name=\"admininfo\" cols=\"65\" rows=\"5\" class=\"sl_conf\" placeholder=\""._ADMININFO."\">".$conf['admininfo']."</textarea></td></tr>"
	."<tr><td>"._STARTDATE.":</td><td>".datetime(1, "startdate", $conf['startdate'], 16, "sl_conf")."</td></tr>"
	."<tr><td>"._ADMINEMAIL.":</td><td><input type=\"email\" name=\"adminmail\" value=\"".$conf['adminmail']."\" maxlength=\"255\" class=\"sl_conf\" placeholder=\""._ADMINEMAIL."\" required></td></tr>"
	."<tr><td>"._USER_COOKIE.":</td><td><input type=\"text\" name=\"user_c\" value=\"".$conf['user_c']."\" maxlength=\"255\" class=\"sl_conf\" placeholder=\""._USER_COOKIE."\" required></td></tr>"
	."<tr><td>"._ADMIN_SESSION.":</td><td><input type=\"text\" name=\"admin_c\" value=\"".$conf['admin_c']."\" maxlength=\"255\" class=\"sl_conf\" placeholder=\""._ADMIN_SESSION."\" required></td></tr>"
	."<tr><td>"._USER_COOKIE_T.":</td><td><input type=\"number\" name=\"user_c_t\" value=\"".intval($conf['user_c_t'] / 86400)."\" class=\"sl_conf\" placeholder=\""._USER_COOKIE_T."\" required></td></tr>"
	."<tr><td>"._SESS_T.":</td><td><input type=\"number\" name=\"sess_t\" value=\"".intval($conf['sess_t'] / 60)."\" class=\"sl_conf\" placeholder=\""._SESS_T."\" required></td></tr>"
	."<tr><td>"._FORUM_LINK.":</td><td><input type=\"text\" name=\"forum_link\" value=\"".$conf['forum_link']."\" maxlength=\"255\" class=\"sl_conf\" placeholder=\""._FORUM_LINK."\"></td></tr>"
	."<tr><td>"._FORUM_MESS.":</td><td><input type=\"text\" name=\"forum_mess\" value=\"".$conf['forum_mess']."\" maxlength=\"255\" class=\"sl_conf\" placeholder=\""._FORUM_MESS."\"></td></tr>"
	."<tr><td>"._IP_LINK.":</td><td><input type=\"url\" name=\"ip_link\" value=\"".$conf['ip_link']."\" maxlength=\"255\" class=\"sl_conf\" placeholder=\""._IP_LINK."\" required></td></tr>"
	."<tr><td>"._INT_FORUM.":</td><td><select name=\"forum\" class=\"sl_conf\">"
	."<option value=\"\"";
	if ($conf['forum'] == "") $cont .= " selected";
	$cont .= ">"._FORUM." - "._NO."</option>"
	."<option value=\"phpbb3\"";
	if ($conf['forum'] == "phpbb3") $cont .= " selected";
	$cont .= ">"._FORUM." - phpBB 3.0.x</option>"
	."<option value=\"phpbb\"";
	if ($conf['forum'] == "phpbb") $cont .= " selected";
	$cont .= ">"._FORUM." - phpBB 2.0.x</option>"
	."<option value=\"ipb1.3.1\"";
	if ($conf['forum'] == "ipb1.3.1") $cont .= " selected";
	$cont .= ">"._FORUM." - Invision Power Board 1.3.1</option>"
	."<option value=\"ipb2.0.x\"";
	if ($conf['forum'] == "ipb2.0.x") $cont .= " selected";
	$cont .= ">"._FORUM." - Invision Power Board 2.0.x</option>"
	."<option value=\"ipb2.1.x\"";
	if ($conf['forum'] == "ipb2.1.x") $cont .= " selected";
	$cont .= ">"._FORUM." - Invision Power Board 2.1.x</option>"
	."<option value=\"ipb2.2.x\"";
	if ($conf['forum'] == "ipb2.2.x") $cont .= " selected";
	$cont .= ">"._FORUM." - Invision Power Board 2.2.x</option>"
	."<option value=\"ipb2.3.x\"";
	if ($conf['forum'] == "ipb2.3.x") $cont .= " selected";
	$cont .= ">"._FORUM." - Invision Power Board 2.3.x</option>"
	."<option value=\"vb\"";
	if ($conf['forum'] == "vb") $cont .= " selected";
	$cont .= ">"._FORUM." - vBulletin</option>"
	."<option value=\"smf\"";
	if ($conf['forum'] == "smf") $cont .= " selected";
	$cont .= ">"._FORUM." - Simple Machines Forum</option>"
	."</select></td></tr>"
	."<tr><td>"._THEME.":</td><td><select name=\"theme\" class=\"sl_conf\">";
	$tdir = opendir("templates");
	while ($tfile = readdir($tdir)) {
		if (!preg_match("/\./", $tfile) && $tfile != "admin") {
			$selected = ($tfile == $conf['theme']) ? "selected" : "";
			$cont .= "<option value=\"".$tfile."\" ".$selected.">".$tfile."</option>";
		}
	}
	closedir($tdir);
	$cont .= "</select></td></tr><tr><td>"._PUTINHOME.":<div class=\"sl_small\">"._PUTINHOMEINFO." "._CTRLINFO."</div></td><td>".modul("module", "sl_conf", $conf['module'], 1)."</td></tr>";
	$mods = array("auto_links", "faq", "files", "links", "media", "news", "order", "page", "shop_clients", "voting");
	$mname = array("auto_links", "faq", "files", "links", "media", "news", "order", "pages", "shop", "voting");
	$i = 0;
	$ocont = "";
	foreach ($mods as $val) {
		if ($val != "") {
			if (file_exists("modules/".$mname[$i]."/admin/index.php")) {
				$selected = ($conf['amod'] == $val) ? "selected" : "";
				$ocont .= "<option value=\"".$val."\" ".$selected.">".deflmconst($mname[$i])."</option>";
			}
			$i++;
		}
	}
	$cont .= "<tr><td>"._PUTINAHOME.":</td><td><select name=\"amod\" class=\"sl_conf\">".$ocont."</select></td></tr>"
	."<tr><td colspan=\"2\"><hr></td></tr>"
	."<tr><td>"._CAPTCHA.":</td><td><select name=\"gfx_chk\" class=\"sl_conf\">"
	."<option value=\"0\"";
	if ($conf['gfx_chk'] == "0") $cont .= " selected";
	$cont .= ">"._CAPSEC0."</option>"
	."<option value=\"1\"";
	if ($conf['gfx_chk'] == "1") $cont .= " selected";
	$cont .= ">"._CAPSEC1."</option>"
	."<option value=\"2\"";
	if ($conf['gfx_chk'] == "2") $cont .= " selected";
	$cont .= ">"._CAPSEC2."</option>"
	."<option value=\"3\"";
	if ($conf['gfx_chk'] == "3") $cont .= " selected";
	$cont .= ">"._CAPSEC3."</option>"
	."<option value=\"4\"";
	if ($conf['gfx_chk'] == "4") $cont .= " selected";
	$cont .= ">"._CAPSEC4."</option>"
	."<option value=\"5\"";
	if ($conf['gfx_chk'] == "5") $cont .= " selected";
	$cont .= ">"._CAPSEC5."</option>"
	."<option value=\"6\"";
	if ($conf['gfx_chk'] == "6") $cont .= " selected";
	$cont .= ">"._CAPSEC6."</option>"
	."<option value=\"7\"";
	if ($conf['gfx_chk'] == "7") $cont .= " selected";
	$cont .= ">"._CAPSEC7."</option>"
	."</select></td></tr>"
	."<tr><td>"._CAPQUALITY.": <div class=\"sl_small\">"._CAPQUALITYI."</div></td><td><select name=\"quality\" class=\"sl_conf\">";
	$xquality = 1;
	while ($xquality <= 9) {
		$sel = ($xquality == $conf['quality']) ? " selected" : "";
		$cont .= "<option value=\"".$xquality."\"".$sel.">0.".$xquality."</option>";
		$xquality++;
	}
	$cont .= "</select></td></tr>"
	."<tr><td>"._CAPKEY.": <div class=\"sl_small\">"._CAPKEYI."</div></td><td><input type=\"text\" name=\"capkey\" value=\"".$conf['capkey']."\" maxlength=\"255\" class=\"sl_conf\" placeholder=\""._CAPKEY."\"></td></tr>"
	."<tr><td>"._CAPSECKEY.": <div class=\"sl_small\">"._CAPKEYI."</div></td><td><input type=\"text\" name=\"capsec\" value=\"".$conf['capsec']."\" maxlength=\"255\" class=\"sl_conf\" placeholder=\""._CAPSECKEY."\"></td></tr>"
	."<tr><td colspan=\"2\"><hr></td></tr>"
	."<tr><td>"._REDAKTOR.":</td><td>".redaktor("2", "redaktor", "sl_conf", $conf['redaktor'], 0)."</td></tr>";
	if (PHP_VERSION >= "5.1.0") {
		$gtime = array("Africa/Abidjan", "Africa/Accra", "Africa/Addis_Ababa", "Africa/Algiers", "Africa/Asmara", "Africa/Asmera", "Africa/Bamako", "Africa/Bangui", "Africa/Banjul", "Africa/Bissau", "Africa/Blantyre", "Africa/Brazzaville", "Africa/Bujumbura", "Africa/Cairo", "Africa/Casablanca", "Africa/Ceuta", "Africa/Conakry", "Africa/Dakar", "Africa/Dar_es_Salaam", "Africa/Djibouti", "Africa/Douala", "Africa/El_Aaiun", "Africa/Freetown", "Africa/Gaborone", "Africa/Harare", "Africa/Johannesburg", "Africa/Kampala", "Africa/Khartoum", "Africa/Kigali", "Africa/Kinshasa", "Africa/Lagos", "Africa/Libreville", "Africa/Lome", "Africa/Luanda", "Africa/Lubumbashi", "Africa/Lusaka", "Africa/Malabo", "Africa/Maputo", "Africa/Maseru", "Africa/Mbabane", "Africa/Mogadishu", "Africa/Monrovia", "Africa/Nairobi", "Africa/Ndjamena", "Africa/Niamey", "Africa/Nouakchott", "Africa/Ouagadougou", "Africa/Porto-Novo", "Africa/Sao_Tome", "Africa/Timbuktu", "Africa/Tripoli", "Africa/Tunis", "Africa/Windhoek", "America/Adak", "America/Anchorage", "America/Anguilla", "America/Antigua", "America/Araguaina", "America/Argentina/Buenos_Aires", "America/Argentina/Catamarca", "America/Argentina/ComodRivadavia", "America/Argentina/Cordoba", "America/Argentina/Jujuy", "America/Argentina/La_Rioja", "America/Argentina/Mendoza", "America/Argentina/Rio_Gallegos", "America/Argentina/Salta", "America/Argentina/San_Juan", "America/Argentina/San_Luis", "America/Argentina/Tucuman", "America/Argentina/Ushuaia", "America/Aruba", "America/Asuncion", "America/Atikokan", "America/Atka", "America/Bahia", "America/Bahia_Banderas", "America/Barbados", "America/Belem", "America/Belize", "America/Blanc-Sablon", "America/Boa_Vista", "America/Bogota", "America/Boise", "America/Buenos_Aires", "America/Cambridge_Bay", "America/Campo_Grande", "America/Cancun", "America/Caracas", "America/Catamarca", "America/Cayenne", "America/Cayman", "America/Chicago", "America/Chihuahua", "America/Coral_Harbour", "America/Cordoba", "America/Costa_Rica", "America/Cuiaba", "America/Curacao", "America/Danmarkshavn", "America/Dawson", "America/Dawson_Creek", "America/Denver", "America/Detroit", "America/Dominica", "America/Edmonton", "America/Eirunepe", "America/El_Salvador", "America/Ensenada", "America/Fort_Wayne", "America/Fortaleza", "America/Glace_Bay", "America/Godthab", "America/Goose_Bay", "America/Grand_Turk", "America/Grenada", "America/Guadeloupe", "America/Guatemala", "America/Guayaquil", "America/Guyana", "America/Halifax", "America/Havana", "America/Hermosillo", "America/Indiana/Indianapolis", "America/Indiana/Knox", "America/Indiana/Marengo", "America/Indiana/Petersburg", "America/Indiana/Tell_City", "America/Indiana/Vevay", "America/Indiana/Vincennes", "America/Indiana/Winamac", "America/Indianapolis", "America/Inuvik", "America/Iqaluit", "America/Jamaica", "America/Jujuy", "America/Juneau", "America/Kentucky/Louisville", "America/Kentucky/Monticello", "America/Knox_IN", "America/La_Paz", "America/Lima", "America/Los_Angeles", "America/Louisville", "America/Maceio", "America/Managua", "America/Manaus", "America/Marigot", "America/Martinique", "America/Matamoros", "America/Mazatlan", "America/Mendoza", "America/Menominee", "America/Merida", "America/Metlakatla", "America/Mexico_City", "America/Miquelon", "America/Moncton", "America/Monterrey", "America/Montevideo", "America/Montreal", "America/Montserrat", "America/Nassau", "America/New_York", "America/Nipigon", "America/Nome", "America/Noronha", "America/North_Dakota/Beulah", "America/North_Dakota/Center", "America/North_Dakota/New_Salem", "America/Ojinaga", "America/Panama", "America/Pangnirtung", "America/Paramaribo", "America/Phoenix", "America/Port-au-Prince", "America/Port_of_Spain", "America/Porto_Acre", "America/Porto_Velho", "America/Puerto_Rico", "America/Rainy_River", "America/Rankin_Inlet", "America/Recife", "America/Regina", "America/Resolute", "America/Rio_Branco", "America/Rosario", "America/Santa_Isabel", "America/Santarem", "America/Santiago", "America/Santo_Domingo", "America/Sao_Paulo", "America/Scoresbysund", "America/Shiprock", "America/Sitka", "America/St_Barthelemy", "America/St_Johns", "America/St_Kitts", "America/St_Lucia", "America/St_Thomas", "America/St_Vincent", "America/Swift_Current", "America/Tegucigalpa", "America/Thule", "America/Thunder_Bay", "America/Tijuana", "America/Toronto", "America/Tortola", "America/Vancouver", "America/Virgin", "America/Whitehorse", "America/Winnipeg", "America/Yakutat", "America/Yellowknife", "Antarctica/Casey", "Antarctica/Davis", "Antarctica/DumontDUrville", "Antarctica/Macquarie", "Antarctica/Mawson", "Antarctica/McMurdo", "Antarctica/Palmer", "Antarctica/Rothera", "Antarctica/South_Pole", "Antarctica/Syowa", "Antarctica/Vostok", "Arctic/Longyearbyen", "Asia/Aden", "Asia/Almaty", "Asia/Amman", "Asia/Anadyr", "Asia/Aqtau", "Asia/Aqtobe", "Asia/Ashgabat", "Asia/Ashkhabad", "Asia/Baghdad", "Asia/Bahrain", "Asia/Baku", "Asia/Bangkok", "Asia/Beirut", "Asia/Bishkek", "Asia/Brunei", "Asia/Calcutta", "Asia/Choibalsan", "Asia/Chongqing", "Asia/Chungking", "Asia/Colombo", "Asia/Dacca", "Asia/Damascus", "Asia/Dhaka", "Asia/Dili", "Asia/Dubai", "Asia/Dushanbe", "Asia/Gaza", "Asia/Harbin", "Asia/Ho_Chi_Minh", "Asia/Hong_Kong", "Asia/Hovd", "Asia/Irkutsk", "Asia/Istanbul", "Asia/Jakarta", "Asia/Jayapura", "Asia/Jerusalem", "Asia/Kabul", "Asia/Kamchatka", "Asia/Karachi", "Asia/Kashgar", "Asia/Kathmandu", "Asia/Katmandu", "Asia/Kolkata", "Asia/Krasnoyarsk", "Asia/Kuala_Lumpur", "Asia/Kuching", "Asia/Kuwait", "Asia/Macao", "Asia/Macau", "Asia/Magadan", "Asia/Makassar", "Asia/Manila", "Asia/Muscat", "Asia/Nicosia", "Asia/Novokuznetsk", "Asia/Novosibirsk", "Asia/Omsk", "Asia/Oral", "Asia/Phnom_Penh", "Asia/Pontianak", "Asia/Pyongyang", "Asia/Qatar", "Asia/Qyzylorda", "Asia/Rangoon", "Asia/Riyadh", "Asia/Saigon", "Asia/Sakhalin", "Asia/Samarkand", "Asia/Seoul", "Asia/Shanghai", "Asia/Singapore", "Asia/Taipei", "Asia/Tashkent", "Asia/Tbilisi", "Asia/Tehran", "Asia/Tel_Aviv", "Asia/Thimbu", "Asia/Thimphu", "Asia/Tokyo", "Asia/Ujung_Pandang", "Asia/Ulaanbaatar", "Asia/Ulan_Bator", "Asia/Urumqi", "Asia/Vientiane", "Asia/Vladivostok", "Asia/Yakutsk", "Asia/Yekaterinburg", "Asia/Yerevan", "Atlantic/Azores", "Atlantic/Bermuda", "Atlantic/Canary", "Atlantic/Cape_Verde", "Atlantic/Faeroe", "Atlantic/Faroe", "Atlantic/Jan_Mayen", "Atlantic/Madeira", "Atlantic/Reykjavik", "Atlantic/South_Georgia", "Atlantic/St_Helena", "Atlantic/Stanley", "Australia/ACT", "Australia/Adelaide", "Australia/Brisbane", "Australia/Broken_Hill", "Australia/Canberra", "Australia/Currie", "Australia/Darwin", "Australia/Eucla", "Australia/Hobart", "Australia/LHI", "Australia/Lindeman", "Australia/Lord_Howe", "Australia/Melbourne", "Australia/North", "Australia/NSW", "Australia/Perth", "Australia/Queensland", "Australia/South", "Australia/Sydney", "Australia/Tasmania", "Australia/Victoria", "Australia/West", "Australia/Yancowinna", "Europe/Amsterdam", "Europe/Andorra", "Europe/Athens", "Europe/Belfast", "Europe/Belgrade", "Europe/Berlin", "Europe/Bratislava", "Europe/Brussels", "Europe/Bucharest", "Europe/Budapest", "Europe/Chisinau", "Europe/Copenhagen", "Europe/Dublin", "Europe/Gibraltar", "Europe/Guernsey", "Europe/Helsinki", "Europe/Isle_of_Man", "Europe/Istanbul", "Europe/Jersey", "Europe/Kaliningrad", "Europe/Kiev", "Europe/Lisbon", "Europe/Ljubljana", "Europe/London", "Europe/Luxembourg", "Europe/Madrid", "Europe/Malta", "Europe/Mariehamn", "Europe/Minsk", "Europe/Monaco", "Europe/Moscow", "Europe/Nicosia", "Europe/Oslo", "Europe/Paris", "Europe/Podgorica", "Europe/Prague", "Europe/Riga", "Europe/Rome", "Europe/Samara", "Europe/San_Marino", "Europe/Sarajevo", "Europe/Simferopol", "Europe/Skopje", "Europe/Sofia", "Europe/Stockholm", "Europe/Tallinn", "Europe/Tirane", "Europe/Tiraspol", "Europe/Uzhgorod", "Europe/Vaduz", "Europe/Vatican", "Europe/Vienna", "Europe/Vilnius", "Europe/Volgograd", "Europe/Warsaw", "Europe/Zagreb", "Europe/Zaporozhye", "Europe/Zurich", "Indian/Antananarivo", "Indian/Chagos", "Indian/Christmas", "Indian/Cocos", "Indian/Comoro", "Indian/Kerguelen", "Indian/Mahe", "Indian/Maldives", "Indian/Mauritius", "Indian/Mayotte", "Indian/Reunion", "Pacific/Apia", "Pacific/Auckland", "Pacific/Chatham", "Pacific/Chuuk", "Pacific/Easter", "Pacific/Efate", "Pacific/Enderbury", "Pacific/Fakaofo", "Pacific/Fiji", "Pacific/Funafuti", "Pacific/Galapagos", "Pacific/Gambier", "Pacific/Guadalcanal", "Pacific/Guam", "Pacific/Honolulu", "Pacific/Johnston", "Pacific/Kiritimati", "Pacific/Kosrae", "Pacific/Kwajalein", "Pacific/Majuro", "Pacific/Marquesas", "Pacific/Midway", "Pacific/Nauru", "Pacific/Niue", "Pacific/Norfolk", "Pacific/Noumea", "Pacific/Pago_Pago", "Pacific/Palau", "Pacific/Pitcairn", "Pacific/Pohnpei", "Pacific/Ponape", "Pacific/Port_Moresby", "Pacific/Rarotonga", "Pacific/Saipan", "Pacific/Samoa", "Pacific/Tahiti", "Pacific/Tarawa", "Pacific/Tongatapu", "Pacific/Truk", "Pacific/Wake", "Pacific/Wallis", "Pacific/Yap");
		$gcont = "";
		foreach ($gtime as $gval) {
			if ($gval != "") {
				$selected = ($conf['gtime'] == $gval) ? "selected" : "";
				$gcont .= "<option value=\"".$gval."\" ".$selected.">".$gval."</option>";
			}
		}
		$cont .= "<tr><td>"._GTIME.":</td><td><select name=\"gtime\" class=\"sl_conf\">".$gcont."</select></td></tr>";
	} else {
		$cont .= "<input type=\"hidden\" name=\"gtime\" value=\"Europe/Berlin\">";
	}
	$cont .= "<tr><td>"._VARIABLES.":<div class=\"sl_small\">"._CTRLINFO."</div></td><td><select name=\"variables[]\" multiple=\"multiple\" class=\"sl_conf\">";
	$variables = explode(",", $conf['variables']);
	$varconst = array(_DEACTIVATE, _SYSTEM_INFO, _AVARIABLES.": POST", _AVARIABLES.": GET", _AVARIABLES.": COOKIE", _AVARIABLES.": FILES", _AVARIABLES.": SESSION", _AVARIABLES.": SERVER", _AQUERY_DB.": MySQL");
	foreach ($varconst as $key => $val) {
		if ($val != "") {
			$selected = ($variables[$key]) ? " selected" : "";
			$cont .= "<option value=\"".$key."\"".$selected.">".$val."</option>";
		}
	}
	$cont .= "</select></td></tr>"
	."<tr><td>"._VAR_VIEW.":</td><td><select name=\"var_view\" class=\"sl_conf\">"
	."<option value=\"0\"";
	if ($conf['var_view'] == "0") $cont .= " selected";
	$cont .= ">"._MVADMIN."</option>"
	."<option value=\"1\"";
	if ($conf['var_view'] == "1") $cont .= " selected";
	$cont .= ">"._MVALL."</option>"
	."</select></td></tr>"
	."<tr><td>"._SYNTAX.":</td><td><select name=\"syntax\" class=\"sl_conf\">"
	."<option value=\"0\"";
	if ($conf['syntax'] == "0") $cont .= " selected";
	$cont .= ">"._SYNTAXP."</option>"
	."<option value=\"1\"";
	if ($conf['syntax'] == "1") $cont .= " selected";
	$cont .= ">"._SYNTAXPN."</option>"
	."<option value=\"2\"";
	if ($conf['syntax'] == "2") $cont .= " selected";
	$cont .= ">"._SYNTAXSH."</option>"
	."</select></td></tr>"
	."<tr><td>"._ADMCOL.":</td><td><input type=\"number\" name=\"admcol\" value=\"".$conf['admcol']."\" class=\"sl_conf\" placeholder=\""._ADMCOL."\" required></td></tr>"
	."<tr><td>"._DB_SYNC."</td><td>".radio_form($conf['dbsync'], "dbsync")."</td></tr>"
	."<tr><td>"._SESSION."</td><td>".radio_form($conf['session'], "session")."</td></tr>"
	."<tr><td>"._MESSAGE_BOX."</td><td>".radio_form($conf['message'], "message")."</td></tr>"
	."<tr><td>"._TIME_DB."</td><td>".radio_form($conf['db_t'], "db_t")."</td></tr>"
	."<tr><td>"._ADMIN_SBLOCK."</td><td>".radio_form($conf['sblock'], "sblock")."</td></tr>"
	."<tr><td>"._ADMINFOEDIT."</td><td>".radio_form($conf['adminfo'], "adminfo")."</td></tr>"
	."<tr><td>"._SITE_CLOSE."</td><td>".radio_form($conf['close'], "close")."</td></tr></table>"
	."</div>"
	."<div id=\"tabc1\" class=\"tabcont\">"
	."<table class=\"sl_table_conf\">"
	."<tr><td>"._DEFIS.":</td><td><input type=\"text\" name=\"defis\" value=\"".urldecode($conf['defis'])."\" maxlength=\"255\" class=\"sl_conf\" placeholder=\""._DEFIS."\" required></td></tr>"
	."<tr><td>"._SKWORDS.":<div class=\"sl_small\">"._SKWORDSI." "._NOKOMA."</div></td><td><textarea name=\"keys\" cols=\"65\" rows=\"5\" class=\"sl_conf\" placeholder=\""._SKWORDS."\" required>".$conf['keys']."</textarea></td></tr>"
	."<tr><td>"._SKDWORDS.":<div class=\"sl_small\">"._NOKOMA."</div></td><td><textarea name=\"dkeys\" cols=\"65\" rows=\"5\" class=\"sl_conf\" placeholder=\""._SKDWORDS."\" required>".$conf['dkeys']."</textarea></td></tr>"
	."<tr><td>"._KWORDS.":<div class=\"sl_small\">"._KWORDSI."</div></td><td><input type=\"number\" name=\"kwords\" value=\"".$conf['kwords']."\" class=\"sl_conf\" placeholder=\""._KWORDS."\" required></td></tr>"
	."<tr><td>"._KLETTER.":<div class=\"sl_small\">"._KLETTERI."</div></td><td><input type=\"number\" name=\"kletter\" value=\"".$conf['kletter']."\" class=\"sl_conf\" placeholder=\""._KLETTER."\" required></td></tr>"
	."<tr><td>"._DLETTER.":</td><td><input type=\"number\" name=\"dletter\" value=\"".$conf['dletter']."\" class=\"sl_conf\" placeholder=\""._DLETTER."\" required></td></tr>"
	."<tr><td>"._KEY_STAT."<div class=\"sl_small\">"._KEY_STATI."</div></td><td>".radio_form($conf['akeys'], "akeys")."</td></tr>"
	."<tr><td>"._KEY_SHUFFLE."<div class=\"sl_small\">"._KEY_SHUFFLEI."</div></td><td>".radio_form($conf['kmix'], "kmix")."</td></tr>"
	."<tr><td>"._KEY_SEP."</td><td>".radio_form($conf['ksep'], "ksep")."</td></tr>"
	."<tr><td>"._LTITLE."</td><td>".radio_form($conf['ltitle'], "ltitle")."</td></tr>"
	."<tr><td>"._ADESC."</td><td>".radio_form($conf['adesc'], "adesc")."</td></tr>"
	."<tr><td colspan=\"2\"><hr></td></tr>"
	."<tr><td>"._RSEP.":</td><td><input type=\"text\" name=\"sep\" value=\"".urldecode($conf['sep'])."\" maxlength=\"255\" class=\"sl_conf\" placeholder=\""._RSEP."\" required></td></tr>"
	."<tr><td>"._REWRITE_MOD."<div class=\"sl_small\">"._REWRITE_MODI."</div></td><td>".radio_form($conf['rewrite'], "rewrite")."</td></tr></table>"
	."</div>"
	."<div id=\"tabc2\" class=\"tabcont\">"
	."<table class=\"sl_table_conf\">"
	."<tr><td>"._SELLANGUAGE.":</td><td><select name=\"language\" class=\"sl_conf\">";
	$dir = opendir("language");
	while (false !== ($file = readdir($dir))) {
		if (preg_match("/^(.+)\.php/", $file, $matches)) {
			$langfound = $matches[1];
			$selected = ($conf['language'] == $langfound) ? "selected" : "";
			$cont .= "<option value=\"".$langfound."\" ".$selected.">".deflang($langfound)."</option>";
		}
	}
	closedir($dir);
	$cont .= "</select></td></tr>"
	."<tr><td>"._ACTMULTILINGUAL."</td><td>".radio_form($conf['multilingual'], "multilingual")."</td></tr>"
	."<tr><td>"._ACTUSEFLAGS."</td><td>".radio_form($conf['flags'], "flags")."</td></tr>"
	."<tr><td>"._GEO_IP."</td><td>".radio_form($conf['geo_ip'], "geo_ip")."</td></tr>"
	."<tr><td>"._ACTUSELANG."</td><td>".radio_form($conf['alang'], "alang")."</td></tr></table>"
	."</div>"
	."<div id=\"tabc3\" class=\"tabcont\">"
	."<table class=\"sl_table_conf\">"
	."<tr><td>"._CENSORMODE.":</td><td>"
	."<select name=\"censor\" class=\"sl_conf\">"
	."<option value=\"0\"";
	if ($conf['censor'] == 0) $cont .= " selected";
	$cont .= ">"._NO."</option>"
	."<option value=\"1\"";
	if ($conf['censor'] == 1) $cont .= " selected";
	$cont .= ">"._MATCHANY."</option>"
	."</select></td></tr>"
	."<tr><td>"._CENSORREPLACE.":</td><td><input type=\"text\" name=\"censor_r\" value=\"".$conf['censor_r']."\" maxlength=\"10\" class=\"sl_conf\" placeholder=\""._CENSORREPLACE."\" required></td></tr>"
	."<tr><td>"._CENSOR.":<div class=\"sl_small\">"._NOKOMA."</div></td><td><textarea name=\"censor_l\" cols=\"65\" rows=\"5\" class=\"sl_conf\" placeholder=\""._CENSOR."\" required>".$conf['censor_l']."</textarea></td></tr>"
	."<tr><td>"._CLICABLE."<div class=\"sl_small\">"._CLICABLEINFO."</div></td><td>".radio_form($conf['clickable'], "clickable")."</td></tr></table>"
	."</div>"
	."<div id=\"tabc4\" class=\"tabcont\">"
	."<table class=\"sl_table_conf\">"
	."<tr><td>"._SMODULE.":<div class=\"sl_small\">"._CTRLINFO."</div></td><td>".modul("search", "sl_conf", $conf['search'], 1)."</td></tr>"
	."<tr><td>"._SEARCHLETMIN.":<div class=\"sl_small\">"._SEARCHLETINFO."</div></td><td><input type=\"number\" name=\"slet\" value=\"".$conf['slet']."\" class=\"sl_conf\" placeholder=\""._SEARCHLETMIN."\" required></td></tr>"
	."<tr><td>"._SEARCHNUM.":</td><td><input type=\"number\" name=\"snum\" value=\"".$conf['snum']."\" class=\"sl_conf\" placeholder=\""._SEARCHNUM."\" required></td></tr>"
	."<tr><td>"._C_35.":</td><td><input type=\"number\" name=\"snump\" value=\"".$conf['snump']."\" class=\"sl_conf\" placeholder=\""._C_35."\" required></td></tr>"
	."<tr><td>"._ASEARCH."</td><td>".radio_form($conf['asearch'], "asearch")."</td></tr></table>"
	."</div>"
	."<div id=\"tabc5\" class=\"tabcont\">"
	."<table class=\"sl_table_conf\">"
	."<tr><td>"._BOTSLIST.":<div class=\"sl_small\">"._NOKOMA." "._BOTSINFO."</div></td><td><textarea name=\"bots\" cols=\"65\" rows=\"10\" class=\"sl_conf\" placeholder=\""._BOTSLIST."\" required>".$conf['bots']."</textarea></td></tr>"
	."<tr><td>"._BOTSSITE.":<div class=\"sl_small\">"._NOKOMA."</div></td><td><textarea name=\"fbots\" cols=\"65\" rows=\"10\" class=\"sl_conf\" placeholder=\""._BOTSSITE."\" required>".$conf['fbots']."</textarea></td></tr>"
	."<tr><td>"._BOTSACT."</td><td>".radio_form($conf['botsact'], "botsact")."</td></tr></table>"
	."</div>"
	."<div id=\"tabc6\" class=\"tabcont\">";
	$f = $asize = 0;
	foreach (glob('config/cache/*.txt') as $file) {
		$size = filesize($file);
		$f++;
		$asize += $size;
	}
	$cont .= setTemplateWarning('warn', array('time' => '', 'url' => '', 'id' => 'info', 'text' => _DIR.': config/cache<br>'._FILE_M.': '.$f.'<br>'._FILE_S.': '.files_size($asize)));
	$cont .= "<table class=\"sl_table_conf\">"
	."<tr><td>"._CACHE.":</td><td>"
	."<select name=\"cache\" class=\"sl_conf\">"
	."<option value=\"0\"";
	if ($conf['cache'] == 0) $cont .= " selected";
	$cont .= ">"._NO."</option>"
	."<option value=\"1\"";
	if ($conf['cache'] == 1) $cont .= " selected";
	$cont .= ">"._CACHE_1."</option>"
	."<option value=\"2\"";
	if ($conf['cache'] == 2) $cont .= " selected";
	$cont .= ">"._CACHE_2."</option>"
	."</select></td></tr>"
	."<tr><td>"._CACHETIME.":</td><td><input type=\"number\" name=\"cache_t\" value=\"".$conf['cache_t']."\" class=\"sl_conf\" placeholder=\""._CACHETIME."\" required></td></tr>"
	."<tr><td>"._CACHEDEL.":</td><td><input type=\"number\" name=\"cache_d\" value=\"".$conf['cache_d']."\" class=\"sl_conf\" placeholder=\""._CACHEDEL."\" required></td></tr>"
	."<tr><td>"._CACHECOMP."</td><td>".radio_form($conf['cache_c'], "cache_c")."</td></tr>"
	."<tr><td>"._CACHEBROW."</td><td>".radio_form($conf['cache_b'], "cache_b")."</td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\"><hr></td></tr>"
	."<tr><td>"._CACHECSS."</td><td>".radio_form($conf['cache_css'], 'cache_css')."</td></tr>"
	."<tr><td>"._CSSDIR.":<div class=\"sl_small\">"._CSSDIRINFO." "._NOKOMA."</div></td><td><textarea name=\"css_f\" cols=\"65\" rows=\"5\" class=\"sl_conf\" placeholder=\""._CSSDIRINFO."\" required>".$conf['css_f']."</textarea></td></tr>"
	."<tr><td>"._CSSHEAD."</td><td>".radio_form($conf['css_h'], 'css_h')."</td></tr>"
	."<tr><td>"._CSSCOMP."</td><td>".radio_form($conf['css_c'], 'css_c')."</td></tr>"
	."<tr><td>"._CSSENC."</td><td>".radio_form($conf['css_e'], 'css_e')."</td></tr>"
	."<tr><td colspan=\"2\" class=\"sl_center\"><hr></td></tr>"
	."<tr><td>"._CACHESCRIPT."</td><td>".radio_form($conf['cache_script'], 'cache_script')."</td></tr>"
	."<tr><td>"._SCRIPTFILE.":<div class=\"sl_small\">"._SCRIPTFILEINFO." "._NOKOMA."</div></td><td><textarea name=\"script_f\" cols=\"65\" rows=\"5\" class=\"sl_conf\" placeholder=\""._SCRIPTFILEINFO."\" required>".$conf['script_f']."</textarea></td></tr>"
	."<tr><td>"._SCRIPTHEAD."</td><td>".radio_form($conf['script_h'], 'script_h')."</td></tr>"
	."<tr><td>"._SCRIPTCOMP."</td><td>".radio_form($conf['script_c'], 'script_c')."</td></tr>"
	."<tr><td>"._SCRIPTASIN."</td><td>".radio_form($conf['script_a'], 'script_a')."</td></tr>"
	."<tr><td>"._SCRIPTBOT."</td><td>".radio_form($conf['script_b'], "script_b")."</td></tr></table>"
	."</div>"
	."<div id=\"tabc7\" class=\"tabcont\">"
	."<table class=\"sl_table_conf\">"
	."<tr><td>"._MAILTEMP.":<div class=\"sl_small\">"._MAILTEMPINFO."</div></td><td><textarea name=\"mtemp\" cols=\"65\" rows=\"10\" class=\"sl_conf\" placeholder=\""._MAILTEMP."\" required>".$conf['mtemp']."</textarea></td></tr></table>"
	."</div>"
	."<script>
		var countries=new ddtabcontent(\"configure\")
		countries.setpersist(true)
		countries.setselectedClassTarget(\"link\")
		countries.init()
	</script>"
	."<table class=\"sl_table_conf\"><tr><td class=\"sl_center\"><input type=\"hidden\" name=\"name\" value=\"config\"><input type=\"hidden\" name=\"op\" value=\"save\"><input type=\"submit\" value=\""._SAVECHANGES."\" class=\"sl_but_blue\"></td></tr></table></form>";
	$cont .= tpl_eval("close", "");
	echo $cont;
	foot();
}

function save() {
	global $admin_file, $conf;
	$protect = array('\n' => '', '\t' => '', '\r' => '', ' ' => '');
	$kprotect = array(', ' => ',', ' ,' => ',', ' , ' => ',', ',,' => ',', '\n' => ',', '\t' => ',', '\r' => ',');
	$xurl = getVar('post', 'homeurl', 'url');
	$xhomeurl = ($xurl[strlen($xurl) - 1] == '/') ? substr($xurl, 0, -1) : $xurl;
	$xsite_logo = str_replace('templates/'.$conf['theme'].'/images/logos/', '', getVar('post', 'site_logo', 'text'));
	$xuser_c = getVar('post', 'user_c', 'text');
	$xadmin_c = getVar('post', 'admin_c', 'text');
	if ($xuser_c == $xadmin_c) {
		$xuser_c = 'user-'.$xuser_c;
		$xadmin_c = 'admin-'.$xadmin_c;
	}
	$xuser_c_t = getVar('post', 'user_c_t', 'num');
	$xuser_c_t = ($xuser_c_t) ? $xuser_c_t * 86400 : 2592000;
	$xsess_t = getVar('post', 'sess_t', 'num');
	$xsess_t = ($xsess_t) ? $xsess_t * 60 : 600;
	$xip_link = getVar('post', 'ip_link', 'url');
	$xip_link = ($xip_link) ? htmlspecialchars($xip_link) : 'http://whois.domaintools.com/';
	$xmodule = getVar('post', 'module', 'var');
	$xmodule = ($xmodule) ? implode(',', $xmodule) : '0';
	$xadmcol = getVar('post', 'admcol', 'num');
	$xadmcol = ($xadmcol) ? $xadmcol : 5;
	$xvar = getVar('post', 'variables', 'var');
	for ($i = 0; $i < 9; $i++) {
		foreach ($xvar as $val) {
			if ($val == $i) {
				$fvar = '1';
				break;
			} else {
				$fvar = '0';
			}
		}
		$var[] = $fvar;
	}
	$xvariables = implode(',', $var);
	
	$xdefis = getVar('post', 'defis', 'let');
	$xdefis = ($xdefis) ? urlencode($xdefis) : '%7C';
	$xkeys = getVar('post', 'keys', 'text');
	$xkeys = strtolower(strtr($xkeys, $kprotect));
	$xdkeys = getVar('post', 'dkeys', 'text');
	$xdkeys = strtolower(strtr($xdkeys, $kprotect));
	$xkwords = getVar('post', 'kwords', 'num');
	$xkwords = ($xkwords) ? $xkwords : 15;
	$xkletter = getVar('post', 'kletter', 'num');
	$xkletter = ($xkletter) ? $xkletter : 3;
	$xdletter = getVar('post', 'dletter', 'num');
	$xdletter = ($xdletter) ? $xdletter : 160;
	$xsep = getVar('post', 'sep', 'let');
	$xsep = ($xsep) ? urlencode($xsep) : '%7C';
	
	$xcensor_r = getVar('post', 'censor_r', 'text');
	$xcensor_r = strtolower(strtr($xcensor_r, $protect));
	$xcensor_l = getVar('post', 'censor_l', 'text');
	$xcensor_l = strtolower(strtr($xcensor_l, $protect));
	$xcensor = (!$xcensor_r || !$xcensor_l) ? 0 : getVar('post', 'censor', 'num');
	$xsearch = getVar('post', 'search', 'var');
	$xsearch = ($xsearch) ? implode(',', $xsearch) : '0';
	
	$xbots = strtr(getVar('post', 'bots', 'text'), $kprotect);
	$xfbots = strtr(getVar('post', 'fbots', 'text'), $kprotect);
	
	$xcache_t = getVar('post', 'cache_t', 'num');
	$xcache_t = ($xcache_t) ? $xcache_t : 60;
	$xcache_d = getVar('post', 'cache_d', 'num');
	$xcache_d = ($xcache_d) ? $xcache_d : 30;
	$xcss_f = getVar('post', 'css_f', 'text');
	$xcss_f = ($xcss_f) ? strtr($xcss_f, $kprotect) : 'templates/[theme]/,plugins/jquery/ui/,plugins/fancybox/,plugins/uploadify/,plugins/syntaxhighlighter/styles/';
	$xscript_f = getVar('post', 'script_f', 'text');
	$xscript_f = ($xscript_f) ? strtr($xscript_f, $kprotect) : 'plugins/system/global-func.js,plugins/jquery/jquery.js,plugins/jquery/ui/jquery-ui.js,plugins/jquery/jquery.tablesorter.js,plugins/jquery/jquery.cookie.js,plugins/fancybox/jquery.mousewheel.js,plugins/fancybox/jquery.fancybox.js,plugins/jquery/jquery.slaed.js';
	
	# DELETE
	/*$content = "\$conf = array();\n"
	."\$conf['version'] = \"6.3.0 Phoenix\";\n"
	."\$conf['sitename'] = \"".$_POST['sitename']."\";\n"
	."\$conf['homeurl'] = \"".$xhomeurl."\";\n"
	."\$conf['site_logo'] = \"".$xsite_logo."\";\n"
	."\$conf['slogan'] = \"".$_POST['slogan']."\";\n"
	."\$conf['admininfo'] = \"".$_POST['admininfo']."\";\n"
	#."\$conf['startdate'] = \"".$xstartdate."\";\n"
	."\$conf['adminmail'] = \"".$_POST['adminmail']."\";\n"
	."\$conf['user_c'] = \"".$xuser_c."\";\n"
	."\$conf['admin_c'] = \"".$xadmin_c."\";\n"
	."\$conf['user_c_t'] = \"".$xuser_c_t."\";\n"
	."\$conf['sess_t'] = \"".$xsess_t."\";\n"
	."\$conf['forum_link'] = \"".$_POST['forum_link']."\";\n"
	."\$conf['forum_mess'] = \"".$_POST['forum_mess']."\";\n"
	."\$conf['ip_link'] = \"".$xip_link."\";\n"
	."\$conf['forum'] = \"".$_POST['forum']."\";\n"
	."\$conf['theme'] = \"".$_POST['theme']."\";\n"
	."\$conf['module'] = \"".$xmodule."\";\n"
	."\$conf['amod'] = \"".$_POST['amod']."\";\n"
	."\$conf['gfx_chk'] = \"".$_POST['gfx_chk']."\";\n"
	."\$conf['quality'] = \"".$_POST['quality']."\";\n"
	."\$conf['capkey'] = \"".$_POST['capkey']."\";\n"
	."\$conf['capsec'] = \"".$_POST['capsec']."\";\n"
	."\$conf['redaktor'] = \"".$_POST['redaktor']."\";\n"
	."\$conf['gtime'] = \"".$_POST['gtime']."\";\n"
	."\$conf['var_view'] = \"".$_POST['var_view']."\";\n"
	."\$conf['syntax'] = \"".$_POST['syntax']."\";\n"
	."\$conf['variables'] = \"".$xvariables."\";\n"
	."\$conf['admcol'] = \"".$xadmcol."\";\n"
	."\$conf['dbsync'] = \"".$_POST['dbsync']."\";\n"
	."\$conf['session'] = \"".$_POST['session']."\";\n"
	."\$conf['message'] = \"".$_POST['message']."\";\n"
	."\$conf['db_t'] = \"".$_POST['db_t']."\";\n"
	."\$conf['sblock'] = \"".$_POST['sblock']."\";\n"
	."\$conf['adminfo'] = \"".$_POST['adminfo']."\";\n"
	."\$conf['close'] = \"".$_POST['close']."\";\n"
	
	."\$conf['defis'] = \"".$xdefis."\";\n"
	."\$conf['keys'] = \"".$xkeys."\";\n"
	."\$conf['kwords'] = \"".$xkwords."\";\n"
	."\$conf['kletter'] = \"".$xkletter."\";\n"
	."\$conf['akeys'] = \"".$_POST['akeys']."\";\n"
	."\$conf['kmix'] = \"".$_POST['kmix']."\";\n"
	."\$conf['rewrite'] = \"".$_POST['rewrite']."\";\n"	
	
	."\$conf['language'] = \"".$_POST['language']."\";\n"
	."\$conf['multilingual'] = \"".$_POST['multilingual']."\";\n"
	."\$conf['flags'] = \"".$_POST['flags']."\";\n"
	."\$conf['geo_ip'] = \"".$_POST['geo_ip']."\";\n"
	."\$conf['alang'] = \"".$_POST['alang']."\";\n"
	
	."\$conf['censor'] = \"".$xcensor."\";\n"
	."\$conf['censor_r'] = \"".$xcensor_r."\";\n"
	."\$conf['censor_l'] = \"".$xcensor_l."\";\n"
	."\$conf['clickable'] = \"".$_POST['clickable']."\";\n"
	
	."\$conf['search'] = \"auto_links,faq,files,forum,jokes,links,media,news,pages,shop\";\n"
	."\$conf['slet'] = \"".$_POST['slet']."\";\n"
	."\$conf['snum'] = \"".$_POST['snum']."\";\n"
	."\$conf['snump'] = \"".$_POST['snump']."\";\n"
	
	."\$conf['bots'] = \"".$xbots."\";\n"
	."\$conf['fbots'] = \"".$xfbots."\";\n"
	."\$conf['botsact'] = \"".$_POST['botsact']."\";\n"
	
	."\$conf['cache'] = \"".$_POST['cache']."\";\n"
	."\$conf['cache_t'] = \"".$xcache_t."\";\n"
	."\$conf['cache_d'] = \"".$xcache_d."\";\n"
	."\$conf['cache_c'] = \"".$_POST['cache_c']."\";\n"
	."\$conf['cache_b'] = \"".$_POST['cache_b']."\";\n"
	."\$conf['cache_css'] = \"".$_POST['cache_css']."\";\n"
	."\$conf['css_f'] = \"".$xcss_f."\";\n"
	."\$conf['css_h'] = \"".$_POST['css_h']."\";\n"
	."\$conf['css_c'] = \"".$_POST['css_c']."\";\n"
	."\$conf['css_e'] = \"".$_POST['css_e']."\";\n"
	."\$conf['cache_script'] = \"".$_POST['cache_script']."\";\n"
	."\$conf['script_f'] = \"".$xscript_f."\";\n"
	."\$conf['script_h'] = \"".$_POST['script_h']."\";\n"
	."\$conf['script_c'] = \"".$_POST['script_c']."\";\n"
	."\$conf['script_a'] = \"".$_POST['script_a']."\";\n"
	."\$conf['script_b'] = \"".$_POST['script_b']."\";\n"
	
	."\$conf['newsletter'] = \"".$conf['newsletter']."\";\n"
	."\$conf['newslettercount'] = \"".$conf['newslettercount']."\";\n"
	."\$conf['sitekey'] = \"".getPass(25)."\";\n"
	."\$conf['lic_h'] = \"UG93ZXJlZCBieSA8YSBocmVmPSJodHRwczovL3NsYWVkLm5ldCIgdGFyZ2V0PSJfYmxhbmsiIHRpdGxlPSJTTEFFRCBDTVMiPlNMQUVEIENNUzwvYT4gJmNvcHk7IDIwMDUt\";\n"
	."\$conf['lic_f'] = \"IFNMQUVELiBBbGwgcmlnaHRzIHJlc2VydmVkLg==\";\n"
	."\$conf['mtemp'] = <<<HTML\n".stripslashes($_POST['mtemp'])."\nHTML;";
	#save_conf("config/config_global.php", $content); */
	
	$cont = array('version' => '6.3.0 Phoenix', 'sitename' => getVar('post', 'sitename', 'text'), 'homeurl' => $xhomeurl, 'site_logo' => $xsite_logo, 'slogan' => getVar('post', 'slogan', 'text'), 'admininfo' => getVar('post', 'admininfo', 'text'), 'startdate' => save_datetime(1, 'startdate'), 'adminmail' => getVar('post', 'adminmail', 'text'), 'user_c' => $xuser_c, 'admin_c' => $xadmin_c, 'user_c_t' => $xuser_c_t, 'sess_t' => $xsess_t, 'forum_link' => getVar('post', 'forum_link', 'url'), 'forum_mess' => getVar('post', 'forum_mess', 'url'), 'ip_link' => $xip_link, 'forum' => getVar('post', 'forum', 'text'), 'theme' => getVar('post', 'theme', 'var'), 'module' => $xmodule, 'amod' => getVar('post', 'amod', 'var'), 'gfx_chk' => getVar('post', 'gfx_chk', 'num'), 'quality' => getVar('post', 'quality', 'num'), 'capkey' => getVar('post', 'capkey', 'text'), 'capsec' => getVar('post', 'capsec', 'text'), 'redaktor' => getVar('post', 'redaktor', 'num'), 'gtime' => getVar('post', 'gtime', 'text'), 'var_view' => getVar('post', 'var_view', 'num'), 'syntax' => getVar('post', 'syntax', 'num'), 'variables' => $xvariables, 'admcol' => $xadmcol, 'dbsync' => getVar('post', 'dbsync', 'num'), 'session' => getVar('post', 'session', 'num'), 'message' => getVar('post', 'message', 'num'), 'db_t' => getVar('post', 'db_t', 'num'), 'sblock' => getVar('post', 'sblock', 'num'), 'adminfo' => getVar('post', 'adminfo', 'num'), 'close' => getVar('post', 'close', 'num'), 
	
	'defis' => $xdefis, 
	'keys' => $xkeys, 
	'dkeys' => $xdkeys, 
	'kwords' => $xkwords, 
	'kletter' => $xkletter, 
	'dletter' => $xdletter, 
	'akeys' => getVar('post', 'akeys', 'num'), 
	'kmix' => getVar('post', 'kmix', 'num'), 
	'ksep' => getVar('post', 'ksep', 'num'), 
	'ltitle' => getVar('post', 'ltitle', 'num'), 
	'adesc' => getVar('post', 'adesc', 'num'),
	'sep' => $xsep, 
	'rewrite' => getVar('post', 'rewrite', 'num'), 
	
	'language' => getVar('post', 'language', 'var'), 
	'multilingual' => getVar('post', 'multilingual', 'num'), 
	'flags' => getVar('post', 'flags', 'num'), 
	'geo_ip' => getVar('post', 'geo_ip', 'num'), 
	'alang' => getVar('post', 'alang', 'num'), 
	
	'censor' => $xcensor, 
	'censor_r' => $xcensor_r, 
	'censor_l' => $xcensor_l, 
	'clickable' => getVar('post', 'clickable', 'num'), 
	
	'search' => $xsearch, 
	'slet' => getVar('post', 'slet', 'num'), 
	'snum' => getVar('post', 'snum', 'num'), 
	'snump' => getVar('post', 'snump', 'num'), 
	'asearch' => getVar('post', 'asearch', 'num'), 
	
	'bots' => $xbots, 
	'fbots' => $xfbots, 
	'botsact' => getVar('post', 'botsact', 'num'), 
	
	'cache' => getVar('post', 'cache', 'num'), 
	'cache_t' => $xcache_t, 
	'cache_d' => $xcache_d, 
	'cache_c' => getVar('post', 'cache_c', 'num'), 
	'cache_b' => getVar('post', 'cache_b', 'num'), 
	'cache_css' => getVar('post', 'cache_css', 'num'), 
	'css_f' => $xcss_f, 
	'css_h' => getVar('post', 'css_h', 'num'), 
	'css_c' => getVar('post', 'css_c', 'num'), 
	'css_e' => getVar('post', 'css_e', 'num'), 
	'cache_script' => getVar('post', 'cache_script', 'num'), 
	'script_f' => $xscript_f, 
	'script_h' => getVar('post', 'script_h', 'num'), 
	'script_c' => getVar('post', 'script_c', 'num'), 
	'script_a' => getVar('post', 'script_a', 'num'), 
	'script_b' => getVar('post', 'script_b', 'num'), 
	
	'mtemp' => preg_replace('#\<br(\s*)?\/?\>#i', '', getVar('post', 'mtemp', 'text')), 
	
	'newsletter' => $conf['newsletter'], 
	'newslettercount' => $conf['newslettercount'], 
	'sitekey' => getPass(25), 
	'lic_h' => 'UG93ZXJlZCBieSA8YSBocmVmPSJodHRwczovL3NsYWVkLm5ldCIgdGFyZ2V0PSJfYmxhbmsiIHRpdGxlPSJTTEFFRCBDTVMiPlNMQUVEIENNUzwvYT4gJmNvcHk7IDIwMDUt', 
	'lic_f' => 'IFNMQUVELiBBbGwgcmlnaHRzIHJlc2VydmVkLg==');
	
	#echo '<pre>';
	#var_dump($cont);
	#exit;
	doConfig('config/config_global.php', 'conf', $cont, '', '');
	header("Location: ".$admin_file.".php?name=config&op=show");
}

function info() {
	head();
	echo configNavi(1, 8, 0, 0, '').'<div id="repadm_info">'.adm_info(1, 0, 'configure').'</div>';
	foot();
}

switch($op) {
	default: config(); break;
	case 'show': config(); break;
	case 'save': save(); break;
	case 'info': info(); break;
}