<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

# The one file block of Node: the type, the mode and the limit are the parameters of this instance in _blocks.param, so the code names no type
# An empty type is a mixed feed of every active type with the blocks integration; the mode home reads only the materials marked for the home page of types with that feature
# Invalid or stale parameters switch the instance off: a visitor sees nothing, a moderator the problem notice, and the site log names the block
global $db, $conf, $fld, $tpl, $prs;
$set = getNodeBlockParam($param);
$content = null;
if ($set === null) {
    Logger::addSite('warning', 'Block node.php: the parameters of an instance are invalid and the block is off', ['bid' => intval($bid)]);
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
                $query->setNodeType($one)->setNodeExtension(getNodeHandler($one));
                if (in_array('published', $one->settings['list']['orders'], true)) $query->setNodeOrder('published', 'desc');
            } else {
                $query->setNodeTypes(array_values($types));
            }
            if ($set['mode'] === 'home') $query->setNodeHome();
            $list = $query->getNodeList();
            if (checkPageCache()) Cache::setPageUntil($query->getNodeDeadline());
        } catch (NodeException $err) {
            Logger::addSite('error', 'Block node.php: the materials cannot be read', ['bid' => intval($bid), 'code' => $err->getCode()]);
        }
    }
    $items = '';
    $prep = new NodeView($prs, $fld);
    foreach ($list as $node) $items .= $tpl->getHtmlFrag(getNodeTplName('fragments', 'block', $types[$node->tid]), $prep->getNodeView($types[$node->tid], $node, 'card'));
    $content = ($items !== '') ? $tpl->getHtmlFrag('list', ['items_html' => $items]) : '';
}
