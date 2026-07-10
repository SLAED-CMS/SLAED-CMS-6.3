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
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_jokes WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($jokeid, $title) = $db->getSqlRow($result)) {
    $title = getDecodedText($title);
    $link = $tpl->getHtmlFrag('link', [
        'href' => 'index.php?name=jokes#'.$jokeid,
        'title' => $title,
        'label' => $title,
    ]);
    $content .= $tpl->getHtmlFrag('list-item', ['content_html' => $link]);
}
