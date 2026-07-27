<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the page-cache, statistics, and GeoIP contract tests: boots the real core like index.php, one scenario per process, with LOGS_DIR and COUNTER_DIR redirected to scratch
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
define('MODULE_FILE', true);
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__, 2)));
$scratch = str_replace('\\', '/', (string)($argv[2] ?? ''));
define('COUNTER_DIR', ($scratch !== '') ? $scratch : str_replace('\\', '/', sys_get_temp_dir()).'/slaed_probe_counter');
define('LOGS_DIR', ($scratch !== '') ? $scratch.'/logs' : str_replace('\\', '/', sys_get_temp_dir()).'/slaed_probe_logs');
if (!is_dir(LOGS_DIR)) mkdir(LOGS_DIR, 0777, true);
if ($scratch === '' && is_file(LOGS_DIR.'/error_file.log')) unlink(LOGS_DIR.'/error_file.log');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) probe';
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

# Report the scratch counter state after one statistics hit so the test can assert files instead of return values
function getProbeCounter(): array {
    $read = static fn(string $file): string => is_file(COUNTER_DIR.'/'.$file) ? (string)file_get_contents(COUNTER_DIR.'/'.$file) : '';
    $arch = glob(COUNTER_DIR.'/statistic/statistic_*.log') ?: [];
    return [
        'stat' => trim($read('statistic.log')),
        'ips' => $read('ips.log'),
        'users' => $read('user.log'),
        'days' => $read('days.log'),
        'arch' => array_map('basename', $arch),
        'log' => is_file(LOGS_DIR.'/error_file.log') ? (string)file_get_contents(LOGS_DIR.'/error_file.log') : '',
    ];
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
        'captcha_register' => checkDynamicMark('captcha', 'register'),
        'captcha_contact' => checkDynamicMark('captcha', 'contact'),
        'captcha_admin' => checkDynamicMark('captcha', 'adminlogin'),
        'captcha_empty' => checkDynamicMark('captcha', ''),
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
} elseif ($mode === 'stathit') {
    $_SERVER['REMOTE_ADDR'] = $argv[3] ?? '127.0.0.1';
    $rep = max(1, (int)($argv[4] ?? 1));
    for ($num = 0; $num < $rep; $num++) {
        updateStatsTrack('/index.php?name=news', 0, ['sess' => null, 'country' => 'DE']);
    }
    $out = getProbeCounter();
} elseif ($mode === 'appendfail') {
    $out['blocked'] = addFile(COUNTER_DIR.'/blocked', 'payload,', 'none', false, 'a');
    $out['plain'] = addFile(COUNTER_DIR.'/plain.log', 'first,', 'none', false, 'a');
    $out['again'] = addFile(COUNTER_DIR.'/plain.log', 'second,', 'none', false, 'a');
    $out['body'] = is_file(COUNTER_DIR.'/plain.log') ? (string)file_get_contents(COUNTER_DIR.'/plain.log') : '';
    $out['log'] = is_file(LOGS_DIR.'/error_file.log') ? (string)file_get_contents(LOGS_DIR.'/error_file.log') : '';
} elseif ($mode === 'filters') {
    $out['num'] = [filterNum('123'), filterNum('abc123def'), filterNum('abc'), filterNum(''), filterNum('-5'), filterNum('999999999')];
    $out['word'] = [filterWord('hello123'), filterWord('a%b&c/d'), filterWord('test<script>alert</script>'), filterWord('Привет'), filterWord('say "hi" \'now\''), filterWord('hello world')];
    $out['var'] = [filterVar('hello-world_123'), filterVar('hello world'), filterVar('test<script>'), filterVar('test\'injection')];
    $out['vararr'] = [filterVar(['one', 'two-three']), filterVar(['ok', 'bad value'])];
    $out['text'] = [filterText('<b>bold</b>'), filterText('say "hi"'), filterText('<b>tag</b>', 2), filterText('[usephp]echo 1;[/usephp]normal text'), filterText('  hello  ')];
    $out['url'] = [filterWebUrl('example.com'), filterWebUrl('https://example.com'), filterWebUrl(''), filterWebUrl('http://')];
    $out['html'] = [filterHtml('cost $5'), filterHtml('back\\slash'), filterHtml('say "hi" and \'bye\''), filterHtml('')];
    $out['fields'] = [filterFields(['a' => ' one ', 'b' => 'two']), filterFields([]), filterFields('plain')];
} elseif ($mode === 'getvar') {
    $_POST = [
        'flat' => ['red', '', 'blue'],
        'rows' => ['0' => 'one', '1' => '', '2' => 'three'],
        'lng' => ['ru' => ['_A' => 'Привет', 'plain' => ''], 'de' => ['_A' => 'Hallo']],
        'deep' => ['a' => ['b' => ['c' => 'found']]],
        'name' => 'scalar value',
        'digits' => 'a12b34',
    ];
    $_GET = ['flat' => ['get-one'], 'lng' => ['ru' => ['_A' => 'from get']], 'name' => 'get scalar'];
    $out['all_raw'] = getVar('post', 'flat[]', '');
    $out['all_typed'] = getVar('post', 'flat[]', 'var');
    $out['idx_one'] = getVar('post', 'flat[2]', '');
    $out['idx_empty'] = getVar('post', 'flat[1]', '', 'fallback');
    $out['idx_missing'] = getVar('post', 'flat[9]', '', 'fallback');
    $out['nest_all'] = getVar('post', 'lng[ru][]', '');
    $out['nest_named'] = getVar('post', 'lng[ru][_A]', '');
    $out['nest_other'] = getVar('post', 'lng[de][_A]', '');
    $out['nest_missing'] = getVar('post', 'lng[fr][]', '');
    $out['nest_deep'] = getVar('post', 'deep[a][b][c]', '');
    $out['nest_deep_all'] = getVar('post', 'deep[a][b][]', '');
    $out['nest_get'] = getVar('get', 'lng[ru][_A]', '');
    $out['nest_req'] = getVar('req', 'flat[]', '');
    $out['scalar_key_on_array'] = getVar('post', 'flat', 'raw', 'fallback');
    $out['array_default_scalar_filter'] = getVar('post', 'missing', 'num', []);
    $out['branch_untouched'] = getVar('post', 'rows[]', 'raw');
} elseif ($mode === 'geoip') {
    $peak = memory_get_peak_usage(true);
    $out['country'] = Geoip::getCountry((string)($conf['geoip_test'] ?? '217.50.80.228'));
    $out['stable'] = ($out['country'] === Geoip::getCountry((string)($conf['geoip_test'] ?? '217.50.80.228')));
    $out['sixte'] = Geoip::getCountry('2001:4860:4860::8888');
    $out['four'] = Geoip::getCountry('8.8.8.8');
    $out['bad'] = Geoip::getCountry('not-an-ip');
    $out['grow'] = memory_get_peak_usage(true) - $peak;
    $out['size'] = is_file(BASE_DIR.'/'.$conf['geoip_country']) ? (int)filesize(BASE_DIR.'/'.$conf['geoip_country']) : 0;
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($out, JSON_UNESCAPED_SLASHES);
