<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for PageCacheContractTest: boots the real core exactly like index.php and reports the behavior of the production contract functions as JSON, one scenario per process because getCacheRouteVars memoizes per request; LOGS_DIR is redirected to a scratch directory before bootstrap so intentional failure-path log writes never touch the runtime logs and stay assertable
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
define('MODULE_FILE', true);
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__, 2)));
define('LOGS_DIR', str_replace('\\', '/', sys_get_temp_dir()).'/slaed_probe_logs');
if (!is_dir(LOGS_DIR)) mkdir(LOGS_DIR, 0777, true);
if (is_file(LOGS_DIR.'/error_file.log')) unlink(LOGS_DIR.'/error_file.log');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTPS'] = 'on';
require_once BASE_DIR.'/core/system.php';

# Build one signed stats cookie value from raw fields using the real purpose-scoped secret
function getProbeCookie(array $part): string {
    $body = implode('|', $part);
    return rtrim(strtr(base64_encode($body.'|'.hash_hmac('sha256', $body, getSecret('stats'))), '+/', '-_'), '=');
}

# Prepare one cacheable-route request context and report the real contract decision and identity
function getProbeRoute(string $uri, array $get, string $host): array {
    global $name, $op, $home, $theme;
    putenv('HTTP_HOST='.$host);
    $_SERVER['REQUEST_URI'] = $uri;
    $_GET = $get;
    $name = 'news';
    $op = '';
    $home = 0;
    $theme = $theme ?? getTheme();
    $vars = getCacheRouteVars();
    return ['vars' => $vars, 'cache' => checkPageCache(), 'hash' => ($vars !== null) ? getPageHash() : ''];
}

$mode = $argv[1] ?? 'core';
$conf = $GLOBALS['conf'];
$chost = strtolower((string)parse_url((string)$conf['homeurl'], PHP_URL_HOST));
$out = [];
if ($mode === 'core') {
    $out['valid'] = [
        'token_ajax' => checkDynamicMark('token', 'ajax'),
        'token_account' => checkDynamicMark('token', 'account'),
        'token_scheduler' => checkDynamicMark('token', 'scheduler'),
        'captcha_login' => checkDynamicMark('captcha', 'login'),
        'voting_id' => checkDynamicMark('voting', '17'),
        'token_empty' => checkDynamicMark('token', ''),
        'token_admin' => checkDynamicMark('token', 'admin'),
        'captcha_comment' => checkDynamicMark('captcha', 'comment'),
        'voting_zero' => checkDynamicMark('voting', '0'),
        'voting_neg' => checkDynamicMark('voting', '-5'),
        'voting_huge' => checkDynamicMark('voting', '1234567890'),
        'voting_inject' => checkDynamicMark('voting', '1;drop'),
        'shell' => checkDynamicMark('shell', 'id'),
    ];
    $mark = getDynamicMark('token', 'ajax');
    $sub = setDynamicRegions('A '.$mark.' B');
    $out['mark_shape'] = (bool)preg_match('#^\[\[sldyn:token:ajax:[a-f0-9]{16}\]\]$#', $mark);
    $out['sub_token'] = (bool)preg_match('#^A [a-f0-9]{64} B$#', $sub);
    $forged = '[[sldyn:token:ajax:'.str_repeat('0', 16).']]';
    $out['forged_literal'] = (setDynamicRegions($forged) === $forged);
    $junk = '[[sldyn:shell:id:'.substr(hash_hmac('sha256', 'shell:id', getSecret('dynreg')), 0, 16).']]';
    $out['junk_empty'] = (setDynamicRegions($junk) === '');
    $now = time();
    $iph = substr(hash_hmac('sha256', getIp(), getSecret('stats')), 0, 16);
    $sid = str_repeat('ab', 8);
    $_COOKIE[$conf['user_c'].'-stats'] = getProbeCookie(['v2', $sid, $now - 100, $now - 50, 4, 'DE', $now - 10, $iph]);
    $state = updateStatsCookie(0);
    $out['v2_keys'] = array_keys($state);
    $out['v2_depth'] = $state['sess']['depth'] ?? 0;
    $out['v2_isnew'] = $state['sess']['is_new'] ?? true;
    $out['v2_country'] = $state['country'];
    $_COOKIE[$conf['user_c'].'-stats'] = getProbeCookie(['v1', $sid, $now - 100, $now - 50, 4, 'DE', $now - 10, date('d.m.Y'), $iph]);
    $vone = updateStatsCookie(0);
    $out['v1_isnew'] = $vone['sess']['is_new'] ?? false;
    $out['v1_depth'] = $vone['sess']['depth'] ?? 0;
    $out['poison_before'] = checkCachePoison();
    $out['reject_empty'] = (getDynamicMark('shell', 'id') === '');
    $out['poison_after'] = checkCachePoison();
    $rlog = is_file(LOGS_DIR.'/error_file.log') ? (string)file_get_contents(LOGS_DIR.'/error_file.log') : '';
    $out['reject_logged'] = str_contains($rlog, 'Rejected dynamic-region marker: shell');
} elseif ($mode === 'route') {
    $out = getProbeRoute('/index.php?name=news&num=1', ['name' => 'news', 'num' => '1'], $chost);
} elseif ($mode === 'routenum') {
    $out = getProbeRoute('/index.php?name=news', ['name' => 'news'], $chost);
} elseif ($mode === 'routebad') {
    $out = getProbeRoute('/index.php?name=news&foo=1', ['name' => 'news', 'foo' => '1'], $chost);
} elseif ($mode === 'routehost') {
    $out = getProbeRoute('/index.php?name=news', ['name' => 'news'], 'evil.example');
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($out, JSON_UNESCAPED_SLASHES);
