<?php
# Author: Eduard Laas
# Copyright © 2005 - 2018 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0): string {
	$ops = ($opt == 1) ? ['replace', 'replace', 'replace_info'] : ['', '', 'replace_info'];
	$lang = [_CONTENT, _NEWS, _INFO];
	return getAdminTabs(_REPLACE, 'replace.png', '', $ops, $lang, [], [], $tab, (bool)$subtab);
}

function replace(): void {
	global $aroute;
	head();
	$cont = navi(0, 0, 0, 0);
	$cont .= setTemplateWarning('warn', _REPLACEINFO, '', '', 'info');
	include('config/replace.php');
	$permtest = end_chmod('config/replace.php', 666);
	if ($permtest) $cont .= setTemplateWarning('warn', $permtest, '', '', 'warn');
	$mods = ['content', 'news'];
	$content = '';
	$k = 0;
	foreach ($mods as $val) {
		if ($val != '') {
			$content .= '<div id="tabc'.$k.'" class="tabcont">';
			$fieldc = explode('||', $confre[$val]);
			for ($c = 0; $c < 50; $c++) {
				preg_match('#(.*)\|(.*)#i', $fieldc[$c], $out);
				$b = $c + 1;
				$display = (empty($out[1]) && empty($out[1][$c]) != '0' && $c != '0') ? ' class="sl_none"' : '';
				$hr = ($c == '0') ? '' : '<hr>';
				$content .= '<div id="fi'.$k.$c.'"'.$display.'>'.$hr
				.'<table class="sl_table_conf">'
				.'<tr><td><a OnClick="HideShow(\'fi'.$k.$b.'\', \'slide\', \'up\', 500);" title="'._ADD.'" class="sl_plus">'._REPLACE_FIELD.': '.$b.'</a></td><td>'
				.'<table><tr><td>'._WORD.':</td><td><input type="text" name="field1'.$k.'[]" value="'.$out[1].'" class="sl_conf" placeholder="'._WORD.'" required></td></tr>'
				.'<tr><td>'._CONTENT.':<div class="sl_small">'._REPLACEIN.'</div></td><td><textarea name="field2'.$k.'[]" cols="65" rows="5" class="sl_conf" placeholder="'._CONTENT.'" required>'.$out[2].'</textarea></td></tr></table></td>'
				.'</tr></table></div>';
			}
			$content .= '</div>';
			$k++;
		}
	}
	$cont .= setTemplateBasic('open');
	$cont .= '<form action="'.$aroute.'.php" method="post">'.$content.'<table class="sl_table_conf"><tr><td class="sl_center"><input type="hidden" name="op" value="replace_save_conf"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>'
	.'<script>
		var countries=new ddtabcontent("replace")
		countries.setpersist(true)
		countries.setselectedClassTarget("link")
		countries.init()
	</script>';
	$cont .= setTemplateBasic('close', '');
	echo $cont;
	foot();
}

function save_conf_data(): void {
	global $aroute;
	include('config/replace.php');
	$content = "\$confre = array();\n";
	$mods = ['content', 'news'];
	$a = 0;
	foreach ($mods as $val) {
		if ($val != '') {
			$fields = '';
			for ($i = 0; $i < 50; $i++) {
				$ident = ($i == 0) ? '' : '||';
				$field1 = ($_POST['field1'.$a][$i] != '') ? $_POST['field1'.$a][$i] : 0;
				$field2 = ($_POST['field2'.$a][$i] != '') ? $_POST['field2'.$a][$i] : 0;
				$fields .= $ident.$field1.'|'.$field2;
			}
			$a++;
			$content .= "\$confre['".$val."'] = \"".$fields."\";\n";
		}
	}
	save_conf('config/replace.php', $content);
	header('Location: '.$aroute.'.php?op=replace');
	exit;
}

function info(): void {
	head();
	echo navi(1, 2, 0, 0).'<div id="repadm_info">'.adm_info(1, 0, 'replace').'</div>';
	foot();
}

switch ($op) {
	default: replace(); break;
	case 'replace_save_conf': save_conf_data(); break;
	case 'replace_info': info(); break;
}
?>
