<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');
require_once CONFIG_DIR.'/config_fields.php';

function fields_navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0, string $extra = ''): string {
	panel();
	$ops = ($opt == 1) ? ['fields', 'fields', 'fields', 'fields', 'fields', 'fields', 'fields_info'] : ['', '', '', '', '', '', 'fields_info'];
	$lang = [_ACCOUNT, _CONTENT, _FORUM, _HELP, _NEWS, _ORDER, _INFO];
	return getAdminTabs(_FIELDS, 'fields.png', '', $ops, $lang, [], [], $tab, $subtab);
}

function fields(): void {
	global $admin_file, $conffi;
	head();
	checkConfigFile('config_fields.php');
	$cont = fields_navi(0, 0, 0, 0, 'fields');

	$mods = ['account', 'content', 'forum', 'help', 'news', 'order'];
	$content = '';
	$k = 0;

	foreach ($mods as $val) {
		if ($val != '') {
			$fieldc = explode('||', $conffi[$val]);
			$content .= '<div id="tabc'.$k.'" class="tabcont">';

			for ($c = 0; $c < 10; $c++) {
				preg_match('#(.*)\|(.*)\|(.*)\|(.*)#i', $fieldc[$c], $out);

				$field = '<select name="field3'.$k.'[]" class="sl_conf">';
				$fieldname = [_FIELDINPUT, _FIELDAREA, _FIELDSELECT, _FIELDTIME, _FIELDDATE];
				foreach ($fieldname as $key => $val2) {
					$i = $key + 1;
					$sel = ($out[3] == $i) ? ' selected' : '';
					$field .= '<option value="'.$i.'"'.$sel.'>'.$val2.'</option>';
				}
				$field .= '</select>';

				$field2 = '<select name="field4'.$k.'[]" class="sl_conf">';
				$fieldname2 = [_FIELDIN, _FIELDOUT];
				foreach ($fieldname2 as $key => $val3) {
					$a = $key + 1;
					$sel2 = ($out[4] == $a) ? ' selected' : '';
					$field2 .= '<option value="'.$a.'"'.$sel2.'>'.$val3.'</option>';
				}
				$field2 .= '</select>';

				$b = $c + 1;
				$display = (empty($out[1]) && empty($out[1][$c]) != '0' && $c != '0') ? ' class="sl_none"' : '';
				$hr = ($c == '0') ? '' : '<hr>';

				$content .= '<div id="fi'.$k.$c.'"'.$display.'>'.$hr
				.'<table class="sl_table_conf">'
				.'<tr><td><a OnClick="HideShow(\'fi'.$k.$b.'\', \'slide\', \'up\', 500);" title="'._ADD.'" class="sl_plus">'._FIELD.': '.$b.'</a></td><td>'
				.'<table><tr><td>'._NAME.':</td><td><input type="text" name="field1'.$k.'[]" value="'.$out[1].'" class="sl_conf" placeholder="'._NAME.'" required></td></tr>'
				.'<tr><td>'._CONTENT.':</td><td><input type="text" name="field2'.$k.'[]" value="'.$out[2].'" class="sl_conf" placeholder="'._CONTENT.'" required></td></tr>'
				.'<tr><td>'._TYPE.':</td><td>'.$field.'</td></tr>'
				.'<tr><td>'._USES.':</td><td>'.$field2.'</td></tr></table>'
				.'</td></tr></table></div>';
			}

			$content .= '</div>';
			$k++;
		}
	}

	$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _FIELDINFO]);
	$cont .= setTemplateBasic('open');
	$cont .= '<form action="'.$admin_file.'.php" method="post">'.$content.'<table class="sl_table_conf"><tr><td class="sl_center"><input type="hidden" name="op" value="fields_save_conf"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>'
	.'<script>
		var countries=new ddtabcontent("fields")
		countries.setpersist(true)
		countries.setselectedClassTarget("link")
		countries.init()
	</script>';
	$cont .= setTemplateBasic('close');
	echo $cont;
	foot();
}

function fields_save_conf(): void {
	global $admin_file;
	require_once CONFIG_DIR.'/config_fields.php';

	$cont = [];
	$mods = ['account', 'content', 'forum', 'help', 'news', 'order'];
	$a = 0;

	foreach ($mods as $val) {
		if ($val != '') {
			$fields = '';
			for ($i = 0; $i < 10; $i++) {
				$ident = ($i == 0) ? '' : '||';
				$field1 = getVar('post', 'field1'.$a, 'arr', [], $i);
				$field2 = getVar('post', 'field2'.$a, 'arr', [], $i);
				$field3 = getVar('post', 'field3'.$a, 'arr', [], $i);
				$field4 = getVar('post', 'field4'.$a, 'arr', [], $i);
				$field1 = ($field1 !== '') ? $field1 : 0;
				$field2 = ($field2 !== '') ? $field2 : 0;
				$field3 = ($field3 !== '') ? $field3 : 0;
				$field4 = ($field4 !== '') ? $field4 : 0;
				$fields .= $ident.$field1.'|'.$field2.'|'.$field3.'|'.$field4;
			}
			$a++;
			$cont[$val] = $fields;
		}
	}

	setConfigFile('config_fields.php', 'conffi', $cont);
	header('Location: '.$admin_file.'.php?op=fields');
}

function fields_info(): void {
	head();
	echo fields_navi(1, 6, 0, 0, '').'<div id="repadm_info">'.adm_info(1, 0, 'fields').'</div>';
	foot();
}

switch($op) {
	case 'fields':
	fields();
	break;

	case 'fields_save_conf':
	fields_save_conf();
	break;

	case 'fields_info':
	fields_info();
	break;
}
