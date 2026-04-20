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
	$flags_html = '';
	for ($i = 0; $i < count($langlist); $i++) {
		if ($langlist[$i] != '') {
			$altlang = getLangName($langlist[$i]);
			$flags_html .= $tpl->getHtmlFrag('block-languages-flag-item', [
				'url'   => 'index.php?newlang='.$langlist[$i],
				'src'   => img_find('lang/'.$langlist[$i].'.png'),
				'alt'   => $altlang,
				'title' => $altlang,
			]);
		}
	}
	$content = $tpl->getHtmlFrag('block-languages-flags', ['flags_html' => $flags_html]);
} else {
	$options_html = '';
	for ($i = 0; $i < count($langlist); $i++) {
		if ($langlist[$i] != '') {
			$selected = ($langlist[$i] == $locale) ? ' selected' : '';
			$options_html .= $tpl->getHtmlFrag('block-languages-option', [
				'url'      => 'index.php?newlang='.$langlist[$i],
				'label'    => getLangName($langlist[$i]),
				'selected' => $selected,
			]);
		}
	}
	$content = $tpl->getHtmlFrag('block-languages-select', ['options_html' => $options_html]);
}
