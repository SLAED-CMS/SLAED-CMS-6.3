<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $db, $locale, $conf, $tpl;
$where = "modul = '' AND time <= now()";
$params = [];
if ($conf['multilingual'] == 1) {
    $where = "(lang = :lang OR lang = '') AND ".$where;
    $params['lang'] = $locale;
}
$mode = intval($conf['voting']['block'] ?? 0);
$where .= ($mode == 1 || $mode == 3) ? " AND enddate <= now() AND status = '1'" : ' AND enddate >= now()';
$order = ($mode >= 2) ? 'RAND()' : 'id DESC';
# The poll id is kept apart from $bid of the block, and the poll takes the dial of the block into its own
[$vid] = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_voting WHERE '.$where.' ORDER BY '.$order.' LIMIT 1', $params)) ?: [''];
$content = $vid ? $tpl->getHtmlFrag('block-content', [
    'id' => 'repblockvoting',
    'content' => getVotingView((int)$vid, 'blockvoting', false, $bmenu ?? []),
]) : '';
if ($vid) $bmenu = [];
