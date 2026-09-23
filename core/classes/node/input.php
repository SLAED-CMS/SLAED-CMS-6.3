<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The content of one material as a create or an update hands it to the service: full sets of categories, fields, relations and assets, never a partial change
# The state goes to a create separately, and ids, author, address, counters, version and dates are assigned by the system; the last array belongs to the extension of the type
# The constructor fixes the top-level types only; the service checks every value against the type, the context and the stored material
final readonly class NodeInput {

    # Fix the sixteen top-level values in the order the contract names them
    public function __construct(
        public int $cid,
        public array $cids,
        public string $aname,
        public string $title,
        public string $intro,
        public string $body,
        public array $fields,
        public int $poll,
        public bool $home,
        public CommentMode $comon,
        public bool $pinned,
        public ?string $pubdate,
        public ?string $expires,
        public array $rels,
        public array $assets,
        public array $ext
    ) {}
}
