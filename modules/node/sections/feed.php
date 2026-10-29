<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The rows of a feed through getNodeFeed(), the engine of the block node.php; a type takes the tone of its place among the types read, as the pulse of the presentation
return static function (array $sec): array {
    global $fld, $prs;
    getLang('node');
    $names = is_array($sec['source'] ?? null) ? array_values(array_filter($sec['source'], 'is_string')) : [];
    $set = ['type' => $names, 'mode' => (($sec['mode'] ?? '') === 'home') ? 'home' : 'last', 'limit' => min(8, max(2, intval($sec['count'] ?? 4)))];
    [$types, $list] = getNodeFeed($set, ['section' => intval($sec['id'] ?? 0)]);
    $place = array_flip(array_keys($types));
    $tones = ['primary', 'accent', 'success', 'warning'];
    $prep = new NodeView($prs, $fld);
    $rows = [];
    foreach ($list as $node) {
        $type = $types[$node->tid];
        $one = $prep->getNodeView($type, $node, 'card');
        $kind = getModuleName($type->name);
        $text = (string)$one['intro'];
        $rows[] = ['href' => $one['href'], 'kind' => $kind, 'title' => $one['title'], 'cat' => $one['ctitle'], 'iso' => $one['date_iso'], 'when' => $one['date'],
            'tone' => $tones[$place[$type->id] % count($tones)], 'icon' => getIconName($type->name), 'text' => (mb_strlen($text) > 160) ? mb_substr($text, 0, 159).'…' : $text,
            'all' => sprintf(_NODE_FEEDALL, $kind), 'all_href' => getSeoUrl(['name' => $type->name])];
    }
    return ['rows' => $rows];
};
