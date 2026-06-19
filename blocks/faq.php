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
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_faq WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($fid, $title) = $db->getSqlRow($result)) {
    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $content .= $tpl->getHtmlFrag('block-list-item', [
        'url' => 'index.php?name=faq&amp;op=view&amp;id='.$fid,
        'title' => $title,
        'label' => $title,
        'target_attr' => '',
    ]);
}
