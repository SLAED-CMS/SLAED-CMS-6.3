<?php
# Copyright © 2005 - 2014 SLAED
# Website: http://www.slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $tpl;
$path = 'uploads/screens/thumb';
$dir = opendir($path);
while (false !== ($file = readdir($dir))) {
	if ($file != '.' && $file != '..' && $file != 'index.html' && !is_dir($path.'/'.$file)) $ban[] = $file;
}
closedir($dir);

$items_html = '';
$sarray = array_rand($ban, count($ban));
shuffle($sarray);
foreach ($sarray as $val) {
	$img_html = (!$s) ? '<img src="uploads/screens/thumb/'.$ban[$val].'">' : '';
	$items_html .= $tpl->getHtmlFrag('block-img-item', [
		'title'    => 'Лучшие сайты системы',
		'href'     => 'uploads/screens/'.$ban[$val],
		'img_html' => $img_html,
	]);
	$s++;
}
$content = $tpl->getHtmlFrag('block-img-wrap', ['items_html' => $items_html]);
