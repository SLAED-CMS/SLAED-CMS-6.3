<?php
# Author: Eduard Laas
# Copyright © 2005 - 2017 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $tpl;
$path = img_find('banners/random');
$dir = opendir($path);
while (false !== ($file = readdir($dir))) {
	if ($file != '.' && $file != '..' && $file != 'index.html' && !is_dir($path.'/'.$file)) $ban[] = $file;
}
closedir($dir);
$i = mt_rand(0, count($ban) - 1);
$url = preg_split('#-#', $ban[$i]);
$content = $tpl->getHtmlFrag('block-banner-random', [
	'url'   => 'https://'.str_replace(['_', '+'], ['/', '?'], $url[0]),
	'title' => 'SLAED CMS',
	'src'   => img_find('banners/random/'.$ban[$i]),
	'alt'   => 'SLAED CMS',
]);
