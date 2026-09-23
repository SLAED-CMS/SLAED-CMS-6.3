<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The closed loading map of Node: an exact class name leads to a file known in advance, and nothing else leads anywhere
# No path is ever built from a requested name, so an unknown or crafted name loads nothing; a class is loaded only when the request really needs it
# The map lists existing files only: every later stage adds its line together with its class
spl_autoload_register(static function (string $name): void {
    $map = [
        'Node' => 'entity.php',
        'NodeAsset' => 'asset.php',
        'NodeContext' => 'context.php',
        'NodeException' => 'exception.php',
        'NodeExtension' => 'extension.php',
        'NodeInput' => 'input.php',
        'NodeQuery' => 'query.php',
        'NodeRelation' => 'relation.php',
        'NodeService' => 'service.php',
        'NodeStatus' => 'status.php',
        'NodeTarget' => 'target.php',
        'NodeType' => 'type.php',
        'NodeTypeInput' => 'typeinput.php',
        'NodeView' => 'view.php',
    ];
    if (isset($map[$name])) require_once __DIR__.'/'.$map[$name];
});
