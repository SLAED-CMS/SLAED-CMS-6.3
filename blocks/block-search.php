<?php
# Copyright © 2005 - 2015 SLAED
# Website: http://www.slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $tpl;
$content = $tpl->getHtmlFrag('block-search-form', [
	'search_label' => _SEARCH,
	'ok_label'     => _OK,
]);
