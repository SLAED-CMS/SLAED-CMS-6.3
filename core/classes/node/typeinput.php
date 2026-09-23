<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The changeable data of one type as a create or an update hands it to the service; the immutable name and the expected version travel separately
# Identity, state, version and dates are assigned by the system and cannot be passed here; the service checks the content of every array against the fresh configuration
final readonly class NodeTypeInput {

    # Fix the eight top-level types without converting anything
    public function __construct(
        public string $title,
        public string $intro,
        public string $ext,
        public int $sort,
        public array $settings,
        public array $fields,
        public array $uploads,
        public array $rating
    ) {}
}
