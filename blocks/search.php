<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $tpl;
$content = $tpl->getHtmlFrag('block-search-form', [
    'search_label' => _SEARCH,
    'ok_label' => _OK,
]);
