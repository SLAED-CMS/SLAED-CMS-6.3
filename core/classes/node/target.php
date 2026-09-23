<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The light target a global subsystem works on: the shared type, the identity, the stored author, the title, the comment mode and the counters, without any text or related set
# The uid is the author of the material and not the current reader; the object builds no URL and runs no query
final readonly class NodeTarget {

    # Hold one accessible target with the type snapshot it shares with every other target of the same type
    public function __construct(
        public NodeType $type,
        public int $id,
        public int $uid,
        public string $title,
        public CommentMode $comon,
        public int $comnum,
        public int $score,
        public int $ratings
    ) {}
}
