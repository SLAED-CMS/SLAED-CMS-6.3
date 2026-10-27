<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $tpl, $layout;
$items = ($layout instanceof Layout) ? $layout->getMenu('main') : [];
$content = $items ? $tpl->getHtmlFrag('block-menu', ['items' => $items]) : '';
