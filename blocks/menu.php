<?php
# 2005 - 2026 SLAED
# Website: http://www.slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $tpl;
$content = $tpl->getHtmlFrag('block-menu', ['content' => $content]);
