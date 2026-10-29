<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

# The one file block of Node, set by its types, mode and limit in _blocks.param; its materials come from getNodeFeed(), which the feed section of the site builder reads too
global $fld, $tpl, $prs;
$set = getNodeBlockParam($param);
$content = null;
if ($set === null) {
    if (is_moder()) $content = $tpl->getHtmlFrag('block-content', ['is_center' => true, 'content' => _BLOCKPROBLEM]);
} else {
    [$types, $list] = getNodeFeed($set, ['bid' => intval($bid)]);
    $items = '';
    $prep = new NodeView($prs, $fld);
    foreach ($list as $node) $items .= $tpl->getHtmlFrag(getNodeTplName('fragments', 'block', $types[$node->tid]), $prep->getNodeView($types[$node->tid], $node, 'card'));
    $content = ($items !== '') ? $tpl->getHtmlFrag('list', ['items_html' => $items]) : null;
}
