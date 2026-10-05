<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

# The one file block of Node, set by type, mode and limit in _blocks.param; a feed of one type puts pinned materials first, then the newest publication, whatever its list sorts
global $db, $conf, $fld, $tpl, $prs;
$set = getNodeBlockParam($param);
$content = null;
if ($set === null) {
    if (is_moder()) $content = $tpl->getHtmlFrag('block-content', ['is_center' => true, 'content' => _BLOCKPROBLEM]);
} else {
    $types = [];
    $size = min($set['limit'], intval($conf['node']['limits']['maxlist'] ?? 0));
    foreach (getNodeTypeMap() as $type) {
        if (!$type->active || !$type->settings['integrations']['blocks'] || ($set['type'] !== '' && $type->name !== $set['type'])) continue;
        if ($set['mode'] === 'home' && !$type->settings['features']['home']) continue;
        $types[$type->id] = $type;
        $size = min($size, $type->settings['list']['limit']);
    }
    $list = [];
    if ($types && $size > 0) {
        try {
            $query = getNodeReader()->setNodePage(1, $size);
            if (count($types) === 1) {
                $one = reset($types);
                $query->setNodeType($one)->setNodeExtension(getNodeHandler($one))->setNodeOrder('published', 'desc');
            } else {
                $query->setNodeTypes(array_values($types));
            }
            if ($set['mode'] === 'home') $query->setNodeHome();
            $list = $query->getNodeList();
        } catch (NodeException $err) {
            Logger::addSite('error', 'Block node.php: the materials cannot be read', ['bid' => intval($bid), 'code' => $err->getCode()]);
        }
    }
    $items = '';
    $prep = new NodeView($prs, $fld);
    foreach ($list as $node) $items .= $tpl->getHtmlFrag(getNodeTplName('fragments', 'block', $types[$node->tid]), $prep->getNodeView($types[$node->tid], $node, 'card'));
    $content = ($items !== '') ? $tpl->getHtmlFrag('list', ['items_html' => $items]) : null;
}
