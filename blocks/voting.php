<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db, $locale, $conf;
$querylang = ($conf['multilingual'] == 1) ? "(lang = '".$locale."' OR lang = '') AND modul = '' AND time <= now()" : "modul = '' AND time <= now()";
if ($conf['voting']['block'] <= 1) {
	$querylang = ($conf['voting']['block'] == 1) ? $querylang." AND enddate <= now() AND status = '1'" : $querylang.' AND enddate >= now()';
	$result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_voting WHERE '.$querylang.' ORDER BY id DESC LIMIT 0, 1');
	list($id) = $db->getSqlRow($result);
	$bid = $id;
} elseif ($conf['voting']['block'] >= 2) {
	$querylang = ($conf['voting']['block'] == 3) ? $querylang." AND enddate <= now() AND status = '1'" : $querylang.' AND enddate >= now()';
	$result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_voting WHERE '.$querylang);
	while (list($id) = $db->getSqlRow($result)) $input[] = $id;
	if (is_array($input)) {
		$rkey = array_rand($input, 1);
		$bid = $input[$rkey];
	} else {
		$bid = '';
	}
}
$content = ($bid) ? '<div id="repblockvoting">'.getVotingView($bid, 'blockvoting').'</div>' : '';
