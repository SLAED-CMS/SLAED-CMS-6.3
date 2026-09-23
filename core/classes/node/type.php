<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# One registered type as a snapshot: the identity, state and version the type table holds, then the effective settings, the checked field definitions,
# the stored upload rule and the four rating settings the configuration holds for the same immutable name
# The reader assembles it once per request and name; the properties are read directly and nothing changes them afterwards
final readonly class NodeType {

    # Hold the database metadata first and the four configuration sections after it
    public function __construct(
        public int $id,
        public string $name,
        public string $title,
        public string $intro,
        public string $ext,
        public bool $active,
        public int $sort,
        public int $version,
        public string $created,
        public string $updated,
        public array $settings,
        public array $fields,
        public array $uploads,
        public array $rating
    ) {}
}
