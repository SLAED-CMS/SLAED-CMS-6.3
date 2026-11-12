<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for checkemail(): each address given as JSON in the first argument is checked alone, and the report says whether it passed with no refusal
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/lang/en.php';
require_once BASE_DIR.'/core/system.php';
$pout = [];
foreach ((array)json_decode((string)($argv[1] ?? '[]'), true) as $pkey => $pmail) {
    $stop = [];
    $pout[$pkey] = checkemail((string)$pmail) === [];
}
echo json_encode($pout);
