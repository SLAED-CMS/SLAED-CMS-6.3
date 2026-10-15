<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The caller of one request as a handler sees it; until the clients of the connector exist every caller is the guest
final class ApiClient {

    # The guest is the client without id, site user or scope, the one a public action is called with
    public function __construct(public readonly int $id = 0, public readonly string $name = '', public readonly int $uid = 0, public readonly array $scopes = []) {
    }
}
