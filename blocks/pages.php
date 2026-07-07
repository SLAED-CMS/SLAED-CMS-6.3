<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $db, $tpl;
$content = '';
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_pages WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($pid, $title) = $db->getSqlRow($result)) {
    $title = getDecodedText($title);
    $content .= $tpl->getHtmlFrag('block-list-item', [
        'url' => getSeoUrl(['name' => 'pages', 'op' => 'view', 'id' => $pid]),
        'title' => $title,
        'label' => $title,
        'target_attr' => '',
    ]);
}
