<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Create the registered extension of a type key from the closed map of key to file and class, with the current database and context
# The stored key is looked up and never turned into a path; an empty key is a standard type and answers null, any other unknown key is refused
# The map lists existing extensions only: each one arrives together with its file, the class support on stage S14 and the class sync on stage S15
function getNodeExtension(string $key, Database $db, NodeContext $context): ?NodeExtension {
    $map = [];
    if ($key === '') return null;
    if (!isset($map[$key])) throw new NodeException('Unknown node extension key', NodeException::INVALID);
    [$file, $class] = $map[$key];
    require_once __DIR__.'/'.$file;
    return new $class($db, $context);
}
