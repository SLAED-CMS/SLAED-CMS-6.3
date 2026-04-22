<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $conf, $locale, $tpl;
$handle = opendir('lang');
while (false !== ($file = readdir($handle))) {
	if (preg_match("/^(.+)\.php/", $file, $matches)) {
		$langlist[] = $matches[1];
	}
}
closedir($handle);
sort($langlist);
if ($conf['flags'] == 1) {
	$cont = '';
	for ($i = 0; $i < count($langlist); $i++) {
		if ($langlist[$i] != '') {
			$altlang = getLangName($langlist[$i]);
			$cont .= $tpl->getHtmlFrag('link', [
				'href' => 'index.php?newlang='.$langlist[$i],
				'title' => $altlang,
				'img_src' => img_find('lang/'.$langlist[$i].'.png'),
				'img_alt' => $altlang,
			]);
		}
	}
	$content = $tpl->getHtmlFrag('content-block', ['class' => 'block-flags', 'content' => $cont]);
} else {
	$opts = '';
	for ($i = 0; $i < count($langlist); $i++) {
		if ($langlist[$i] != '') {
			$opts .= $tpl->getHtmlFrag('select-option', [
				'value_attr' => 'index.php?newlang='.$langlist[$i],
				'label_text' => getLangName($langlist[$i]),
				'is_selected' => $langlist[$i] == $locale,
			]);
		}
	}
	$sel = $tpl->getHtmlFrag('select', ['name_attr' => 'newlang', 'select_attr' => 'onchange="location.href=this.value"', 'options_html' => $opts]);
	$content = '<form action="index.php" method="get" class="block-languages">'.$sel.'</form>';
}
