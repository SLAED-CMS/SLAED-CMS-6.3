<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# One material as a read returned it: the main row with its decoded state, comment mode and fields, the related sets the projection asked for, and two joined names at the end
# A null body, fields, category list, relation list or asset list means the projection did not load it, an empty string or array means it is loaded and empty
# The ip is null whenever the context may not see it; the object runs no query, is never changed after it was built and carries no getters or setters
final readonly class Node {

    # Hold one read material exactly in the order of the node table, with pubdate standing for the published column
    public function __construct(
        public int $id,
        public int $tid,
        public int $cid,
        public int $uid,
        public string $aname,
        public ?string $ip,
        public string $title,
        public string $intro,
        public ?string $body,
        public ?array $fields,
        public int $poll,
        public bool $home,
        public CommentMode $comon,
        public bool $pinned,
        public int $comnum,
        public int $views,
        public int $score,
        public int $ratings,
        public NodeStatus $status,
        public int $version,
        public string $created,
        public string $updated,
        public ?string $pubdate,
        public ?string $expires,
        public ?array $cids,
        public ?array $rels,
        public ?array $assets,
        public ?string $uname,
        public ?string $ctitle
    ) {}
}
