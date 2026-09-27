<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The walk of the repository tree shared by every gate that reads the source, so runtime output and third-party trees are never entered
# Neither is a scratch theme: a gate of another process copies it into templates/ and removes it again while this walk may be under way

# Answer true for a path no source walk may enter: storage, vendor, node_modules and .git of the root, and a scratch theme under templates
function isTreeSkipped(string $path): bool {
    $root = str_replace('\\', '/', dirname(__DIR__, 2)).'/';
    $path = str_replace('\\', '/', $path);
    if (!str_starts_with($path, $root)) return false;
    return preg_match('#^(storage|vendor|node_modules|\.git|templates/scratch-[0-9a-f]{8})(/|$)#', substr($path, strlen($root))) === 1;
}

# Every file below one directory of the repository, the directories isTreeSkipped() names cut off before the walk enters them
# The cut asks the path alone: a directory removed after it was listed is no directory to isDir() any more, and the walk would try to open it
function getTreeFiles(string $dir): RecursiveIteratorIterator {
    $tree = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
    $keep = static fn(SplFileInfo $item): bool => !isTreeSkipped($item->getPathname());
    return new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator($tree, $keep));
}
