<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# Verifies signatures the way a site without the extension sodium does, run by UpdateSignTest under php -n so no extension loads
# Arguments: the message, its signature, a forged message and two public keys, each in base64; the answer is one JSON line

define('FUNC_FILE', true);
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__, 2)));
define('UPDATE_DIR', BASE_DIR.'/storage/update');
require BASE_DIR.'/core/classes/update.php';

[$msg, $good, $bad, $key, $other] = array_map(static fn(string $one): string => (string)base64_decode($one, true), array_slice($argv, 1, 5));
$out = ['ext' => extension_loaded('sodium'), 'good' => Update::isSignValid($msg, $good, $key), 'forged' => Update::isSignValid($bad, $good, $key),
    'foreign' => Update::isSignValid($msg, $good, $other)];
$out['signature'] = class_exists('Signature', false);
echo json_encode($out);
