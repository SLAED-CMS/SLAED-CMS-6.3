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
	$kwords = '';
	foreach ($words as $val) {
		if ($val != '') $kwords .= '<a href="index.php?name=search&amp;word='.urlencode($val).'" title="'.$val.'">'.$val.'</a> ';
	}
	$content = $kwords;
}