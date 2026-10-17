<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The test keys of tests/Fixtures/update as UPDATE_KEYS, so a test run trusts the keys it signs with while the core ships the real factory keys
# Every boot of a test includes this before the core: the PHPUnit bootstrap, the shared probe boot and the routers of the built-in server
if (!defined('UPDATE_KEYS')) {
    $tkeys = [];
    foreach (['primary', 'recovery'] as $trole) {
        $tkeys[$trole] = (string)(json_decode((string)file_get_contents(dirname(__DIR__).'/Fixtures/update/'.$trole.'.key'), true)['pub'] ?? '');
    }
    define('UPDATE_KEYS', $tkeys);
    unset($tkeys, $trole);
}
