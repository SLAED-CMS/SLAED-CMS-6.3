<?php
# Author: Eduard Laas
# Copyright © 2005 - 2022 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $conf;
$words = $conf['keys'];
if (is_array($words)) {
	$kwords = '<style scoped>.keyw { background: none; color: #000000; font-size: 12px; font-weight: normal; font-family: Verdana, Helvetica; text-decoration: none; margin: 0 0 0 3; float: left }</style>';
	foreach ($words as $val) {
		if ($val != '') {
			$num = mt_rand(1, 5);
			$kwords .= '<h'.$num.' class="keyw">'.$val.'</h'.$num.'>';
		}
	}
	$content = $kwords;
}