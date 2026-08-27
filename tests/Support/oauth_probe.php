<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the one page of the screenshot manifest a request cannot ask for. index.php?name=account&op=oauth_finish
# renders the two OAuth cards only for a browser carrying a pending flow, and a pending flow is the far end of a round trip
# to a provider: without one the handler sends the visitor back to the account page, so the route captures the wrong screen
# and proves nothing about the box the row fold is measured in
# `make` writes that one row and answers with the token, the cookie name the browser has to carry and whether it is secure;
# `gone` removes the row again. Nothing else on the stand is touched, and a row left behind by a run that died expires by
# itself: the stamp is pushed forward by the seconds the caller asks for and the handler drops anything past its own ttl
$probework = (string)($argv[2] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';

# Seed one pending OAuth flow and answer with what a browser needs to be handed the page it renders
function setOauthPending(string $prov, int $keep): array {
    global $conf, $db;
    $tok = bin2hex(random_bytes(32));
    $data = [
        'provider' => $prov,
        'uid' => 'probe-'.substr($tok, 0, 12),
        'email' => 'probe@slaed.loc',
        'uname' => 'Probe',
        'redirect' => 'index.php',
    ];
    if (!Oauth::setTemp('pending', $tok, $data)) return ['error' => 'the pending row could not be written'];
    if ($keep > 0) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_oauth_temp SET time = :time WHERE token = :tok', ['time' => time() + $keep, 'tok' => $tok]);
    $ssl = str_starts_with((string)($conf['homeurl'] ?? ''), 'https://');
    return ['token' => $tok, 'cookie' => ($ssl ? '__Host-' : '').'oauth-pt', 'secure' => $ssl];
}

# Take the seeded row away again, and say whether there was a token to take away at all
function deleteOauthPending(string $tok): bool {
    if ($tok === '') return false;
    Oauth::deleteTemp($tok);
    return true;
}

$job = (string)($argv[1] ?? '');
$args = array_slice($argv, 3);
$data = match ($job) {
    'make' => setOauthPending((string)($args[0] ?? 'google'), (int)($args[1] ?? 3600)),
    'gone' => ['gone' => deleteOauthPending((string)($args[0] ?? ''))],
    default => ['error' => 'unknown job: '.$job],
};
echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
