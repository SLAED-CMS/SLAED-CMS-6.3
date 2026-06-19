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
$strip = 40;

# Last added jokes
$col1 = '';
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_jokes WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($jokeid, $title) = $db->getSqlRow($result)) {
    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $col1 .= $tpl->getHtmlFrag('link', [
        'href' => 'index.php?name=jokes#'.$jokeid,
        'title' => $title,
        'label' => cutstr($title, $strip),
        'is_line_break' => true,
    ]);
}

# Last added faq
$col2 = '';
$result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB."_faq WHERE time <= now() AND status != '0' ORDER BY time DESC LIMIT 5");
while (list($fid, $title) = $db->getSqlRow($result)) {
    $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $col2 .= $tpl->getHtmlFrag('link', [
        'href' => 'index.php?name=faq&amp;op=view&amp;id='.$fid,
        'title' => $title,
        'label' => cutstr($title, $strip),
        'is_line_break' => true,
    ]);
}

$content = $tpl->getHtmlFrag('table', ['headers' => [['text' => _JOKES, 'is_col_half' => true], ['text' => _FAQ]], 'rows_html' => $tpl->getHtmlFrag('table-row', ['cells' => [['content_html' => $col1], ['content_html' => $col2]]])]);
