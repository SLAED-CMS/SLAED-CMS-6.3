<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# One structured resource of a material exactly as the asset table stores it: the checked source, the stored metadata and the state of the first open report
# Unknown or inapplicable metadata and the absence of a report are null; no computed flag is added and no query runs from here
final readonly class NodeAsset {

    # Hold one stored resource in the order of its columns, dates as canonical database strings
    public function __construct(
        public int $id,
        public int $nid,
        public string $kind,
        public string $role,
        public string $src,
        public string $name,
        public string $title,
        public string $intro,
        public ?string $mime,
        public ?int $size,
        public ?int $width,
        public ?int $height,
        public ?int $duration,
        public int $hits,
        public ?string $reported,
        public int $ruid,
        public int $sort,
        public string $created,
        public string $updated
    ) {}
}
