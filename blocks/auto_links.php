<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $db, $tpl, $conf, $prs;
$content = '';
$result = $db->getSqlQuery('SELECT id, title, intro FROM '.PREFIX_DB."_auto_links WHERE hits != '0' ORDER BY hits DESC LIMIT 0,".intval($conf['auto_links']['limit']).'');
while (list($a_id, $a_site, $a_description) = $db->getSqlRow($result)) {
    $a_site = cutstr(getDecodedText($a_site), $conf['auto_links']['strip']);
    $title = filterText(cutstr($prs->filterContent($a_description, false, ''), 250), 1);
    $link = $tpl->getHtmlFrag('link', [
        'href' => getSeoUrl(['name' => 'auto_links', 'op' => 'view', 'id' => $a_id]),
        'title' => $title,
        'label' => $a_site,
        'is_blank' => true,
    ]);
    $content .= $tpl->getHtmlFrag('list-item', ['content_html' => $link]);
}
$content .= $tpl->getHtmlFrag('block-center-link', [
    'url' => getSeoUrl(['name' => 'auto_links', 'op' => 'add']),
    'title' => _A_LINKS,
    'label' => _ADD,
]);
