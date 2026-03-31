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
$counter_alt = 'Счетчик посещений страниц и уникальных посетителей в сутки';
$slaed_title = 'SLAED CMS - Content Management System';
$rss_title   = 'Экспорт новостей в формате RSS';
$content = $tpl->getHtmlFrag('block-stat-banners', [
	'counter_src'   => 'index.php?stat=1&amp;img=2',
	'counter_alt'   => $counter_alt,
	'counter_title' => $counter_alt,
	'slaed_url'     => 'http://www.slaed.net',
	'slaed_title'   => $slaed_title,
	'slaed_src'     => img_find('banners/slaed_3_2.gif'),
	'rss_url'       => 'index.php?go=rss&amp;num=50',
	'rss_title'     => $rss_title,
	'rss_src'       => img_find('banners/rss_2.gif'),
]);
