<?php
# Author: Eduard Laas
# Copyright © 2005 - 2018 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $tpl;
$alt = 'Счетчик посещений страниц и уникальных посетителей в сутки';
$titl = 'SLAED CMS - Content Management System';
$rss = 'Экспорт новостей в формате RSS';
$content = $tpl->getHtmlFrag('image', [
	'src' => 'index.php?stat=1&amp;img=2',
	'alt' => $alt,
	'title' => $alt,
]);
$content .= $tpl->getHtmlFrag('link', [
	'href' => 'http://www.slaed.net',
	'title' => $titl,
	'img_src' => img_find('banners/slaed_3_2.gif'),
	'img_alt' => $titl,
]);
$content .= $tpl->getHtmlFrag('link', [
	'href' => 'index.php?go=rss&amp;num=50',
	'title' => $rss,
	'img_src' => img_find('banners/rss_2.gif'),
	'img_alt' => $rss,
]);
