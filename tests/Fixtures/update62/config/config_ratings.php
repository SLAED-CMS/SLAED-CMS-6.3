<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The rating rules of the 6.2 site of tests/Fixtures/update62/site.sql as period, switch and detail per module, two of them of modules the release removed
if (!defined('FUNC_FILE')) die('Illegal file access');

$confra = [
    'account' => '2592000|1|0',
    'files' => '2592000|1|0',
    'forum' => '2592000|1|0',
    'news' => '2592000|1|0',
    'shop' => '86400|1|1',
];
