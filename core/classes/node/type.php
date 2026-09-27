<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# One registered type as a snapshot: identity, state and version from the type table, then the effective settings, field definitions, upload rule and four rating settings
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
