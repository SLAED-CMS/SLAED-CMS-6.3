<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Create the registered extension of a type key from the closed map of key to file and class, with the current database and context
# The stored key is looked up and never turned into a path; an empty key is a standard type and answers null, any other unknown key is refused
# The map lists existing extensions only, each one arriving together with its file; the class of sync alone takes a third argument, the shared Feed of the loaded rss scope
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
