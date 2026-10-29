<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# One record of Node by the number the section keeps, read through the reader with the checks of its own page, so a closed or a gone record prints nothing
return static function (array $sec): array {
    global $db, $fld, $prs;
    $id = intval($sec['item'] ?? 0);
    $none = ['text_html' => '', 'more' => '', 'more_href' => ''];
    if ($id < 1) return $none;
    $tid = intval($db->getSqlRow($db->getSqlQuery('SELECT tid FROM '.PREFIX_DB.'_nodes WHERE id = :id', ['id' => $id]))['tid'] ?? 0);
    $type = array_values(array_filter(getNodeTypeMap(), static fn(NodeType $one): bool => $one->id === $tid))[0] ?? null;
    if ($type === null) return $none;
    try {
        $node = getNodeReader($type)->getNodeContent($id, $type);
    } catch (NodeException $err) {
        Logger::addSite('error', 'Node item: the record cannot be read', ['section' => intval($sec['id'] ?? 0), 'code' => $err->getCode()]);
        return $none;
    }
    if ($node === null) return $none;
    $one = (new NodeView($prs, $fld))->getNodeView($type, $node, 'view');
    $cut = ($sec['part'] ?? '') === 'intro' && $one['body_html'] !== '';
    $lead = ($one['intro_html'] !== '' || preg_match('#<p\b[^>]*>.*?</p>#s', $one['body_html'], $hit) !== 1) ? $one['intro_html'] : $hit[0];
    $out = ['text_html' => $cut ? $lead : $one['intro_html'].$one['body_html'], 'more' => _READMORE, 'more_href' => $cut ? $one['href'] : ''];
    if (trim((string)($sec['title'] ?? '')) === '') $out['title'] = $one['title'];
    return $out;
};
