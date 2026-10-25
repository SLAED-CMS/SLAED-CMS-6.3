<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Create the registered extension of a type key; the stored key is looked up in a closed map and never turned into a path
function getNodeExtension(string $key, Database $db, NodeContext $context): ?NodeExtension {
    global $conf;
    $map = ['support' => ['support.php', 'NodeSupport'], 'sync' => ['sync.php', 'NodeSync']];
    if ($key === '') return null;
    if (!isset($map[$key])) throw new NodeException('Unknown node extension key', NodeException::INVALID);
    [$file, $class] = $map[$key];
    require_once __DIR__.'/'.$file;
    if ($class !== 'NodeSync') return new $class($db, $context);
    require_once BASE_DIR.'/core/classes/feed.php';
    return new NodeSync($db, $context, new Feed($conf['rss'] ?? []));
}
