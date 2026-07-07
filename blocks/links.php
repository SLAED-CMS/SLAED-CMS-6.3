<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $db, $tpl, $prs;
$strip = 40;

# Last added links
$col1 = '';
$result = $db->getSqlQuery('SELECT id, title, description FROM '.PREFIX_DB."_links WHERE status != '0' ORDER BY time DESC LIMIT 0,10");
while (list($l_lid, $l_title, $l_description) = $db->getSqlRow($result)) {
    $l_title = getDecodedText($l_title);
    $col1 .= $tpl->getHtmlFrag('link', [
        'href' => getSeoUrl(['name' => 'links', 'op' => 'view', 'id' => $l_lid]),
        'title' => filterText(cutstr($prs->filterContent($l_description, false, 'links'), 250), 1),
        'label' => cutstr($l_title, $strip),
        'is_line_break' => true,
    ]);
}

# Last best links
$col2 = '';
$result = $db->getSqlQuery('SELECT id, title, description FROM '.PREFIX_DB."_links WHERE status != '0' ORDER BY tvotes DESC LIMIT 0,10");
while (list($l_lid, $l_title, $l_description) = $db->getSqlRow($result)) {
    $l_title = getDecodedText($l_title);
    $col2 .= $tpl->getHtmlFrag('link', [
        'href' => getSeoUrl(['name' => 'links', 'op' => 'view', 'id' => $l_lid]),
        'title' => filterText(cutstr($prs->filterContent($l_description, false, 'links'), 250), 1),
        'label' => cutstr($l_title, $strip),
        'is_line_break' => true,
    ]);
}

$content = $tpl->getHtmlFrag('table', ['headers' => [['text' => 'Новые сайты', 'is_col_half' => true], ['text' => 'Лучшие сайты']], 'rows_html' => $tpl->getHtmlFrag('table-row', ['cells' => [['content_html' => $col1], ['content_html' => $col2]]])]);
