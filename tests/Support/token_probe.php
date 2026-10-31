<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the token rules of core/security.php: the three functions are lifted out over a fixed secret and a fixed session id, so each scenario is the request alone
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
$pwork = str_replace('\\', '/', (string)($argv[1] ?? ''));
$psite = str_replace('\\', '/', dirname(__DIR__, 2));
if ($pwork === '') {
    echo json_encode(['error' => 'usage']);
    exit;
}
if (!is_dir($pwork)) mkdir($pwork, 0777, true);
$pdone = ['error' => '', 'data' => []];

# Cut one top-level function out of a shipped source and load it into this process
function setProbeLift(string $file, string $func, string $into): void {
    $code = (string)file_get_contents($file);
    $from = strpos($code, "\nfunction ".$func.'(');
    $end = ($from === false) ? false : strpos($code, "\n}\n", $from);
    if ($end === false) throw new RuntimeException('Function '.$func.' was not found');
    file_put_contents($into, "<?php\n".substr($code, $from, $end - $from + 3));
    require $into;
}

# A fixed key is all the token rules ask of the secret
function getSecret(string $purpose): string {
    return hash('sha256', 'probe'.$purpose);
}

try {
    foreach (['getSiteToken', 'getRequestToken', 'checkSiteToken'] as $i => $one) setProbeLift($psite.'/core/security.php', $one, $pwork.'/lift_'.$i.'.php');
    session_id('probesession');
    $ajax = getSiteToken();
    $panel = getSiteToken('blocks');
    $pdone['data']['scope'] = [
        'own' => checkSiteToken($panel, 'blocks'),
        'ajax_for_scope' => checkSiteToken($ajax, 'blocks'),
        'scope_for_ajax' => checkSiteToken($panel),
        'other_scope' => checkSiteToken(getSiteToken('voting'), 'blocks'),
        'plain_ajax' => checkSiteToken($ajax),
        'empty' => checkSiteToken('', 'blocks'),
    ];
    $_GET = ['token' => $ajax];
    $_REQUEST = ['token' => $ajax];
    $pdone['data']['address'] = [getRequestToken(), checkSiteToken()];
    $_GET = $_REQUEST = [];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $ajax;
    $pdone['data']['header'] = checkSiteToken();
    $pdone['data']['header_for_scope'] = checkSiteToken('', 'blocks');
} catch (Throwable $err) {
    $pdone['error'] = get_class($err).': '.$err->getMessage();
}
echo json_encode($pdone);
