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

# A member cookie of a seeded account from its row id:name:hash, with the proof the core reads; a scratch site without a master gets one here, as each child would mint its own
function getProbeAccount(string $row): string {
    [$uid, $name, $hash] = explode(':', $row, 3);
    $file = CONFIG_DIR.'/security.php';
    $data = require $file;
    if (($data['security']['secret'] ?? '') === '' && realpath(CONFIG_DIR) !== realpath(dirname(__DIR__, 2).'/config')) {
        $data['security']['secret'] = bin2hex(random_bytes(32));
        file_put_contents($file, "<?php\nreturn ".var_export($data, true).";\n", LOCK_EX);
        if (is_file(CONFIG_DIR.'/local.php')) unlink(CONFIG_DIR.'/local.php');
    }
    return base64_encode($uid.':'.$name.':'.hash_hmac('sha256', $hash, hash_hmac('sha256', 'user', (string)($data['security']['secret'] ?? ''))));
}
