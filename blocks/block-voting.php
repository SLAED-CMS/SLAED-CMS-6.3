<?php
# Author: Eduard Laas
# Copyright © 2005 - 2021 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db, $locale, $conf;
$querylang = ($conf['multilingual'] == 1) ? "(language = '".$locale."' OR language = '') AND modul = '' AND date <= now()" : "modul = '' AND date <= now()";
if ($conf['voting']['block'] <= 1) {
	$querylang = ($conf['voting']['block'] == 1) ? $querylang." AND enddate <= now() AND status = '1'" : $querylang." AND enddate >= now()";
	$result = $db->sql_query("SELECT id FROM ".PREFIX_DB."_voting WHERE ".$querylang." ORDER BY id DESC LIMIT 0, 1");
	list($id) = $db->sql_fetchrow($result);
	$bid = $id;
} elseif ($conf['voting']['block'] >= 2) {
	$querylang = ($conf['voting']['block'] == 3) ? $querylang." AND enddate <= now() AND status = '1'" : $querylang." AND enddate >= now()";
	$result = $db->sql_query("SELECT id FROM ".PREFIX_DB."_voting WHERE ".$querylang);
	while (list($id) = $db->sql_fetchrow($result)) $input[] = $id;
	if (is_array($input)) {
		$rkey = array_rand($input, 1);
		$bid = $input[$rkey];
	} else {
		$bid = '';
	}
}
$content = ($bid) ? '<div id="repblockvoting">'.getVoting($bid, 'blockvoting').'</div>' : '';
?>