<?php
# Copyright © 2005 - 2015 SLAED
# Website: http://www.slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db;
list($count) = $db->getSqlRow($db->getSqlQuery('SELECT Count(jokeid) FROM '.PREFIX_DB."_jokes WHERE date <= now() AND status != '0'"));
$random = mt_rand(0, $count - 1);
$result = $db->getSqlQuery('SELECT joke FROM '.PREFIX_DB.'_jokes ORDER BY jokeid DESC LIMIT '.$random.', 1');
list($joke) = $db->getSqlRow($result);
$content = $joke;