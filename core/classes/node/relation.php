<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# One directed relation between two materials exactly as the relation table stores it; the related material is never nested and no query runs from here
final readonly class NodeRelation {

    # Hold one stored relation in the order of its columns, the creation date as the canonical database string
    public function __construct(
        public int $id,
        public int $nid,
        public int $rid,
        public string $type,
        public int $sort,
        public string $created
    ) {}
}
