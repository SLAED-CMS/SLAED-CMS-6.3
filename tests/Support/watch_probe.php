<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the request reading of core/security.php, the info page of core/helpers.php and the login limit of the captcha class, lifted out of their sources over stubs
# The stubs record instead of stopping the request, and nothing but the work folder of the argument is written
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
$pwork = str_replace('\\', '/', (string)($argv[1] ?? ''));
$psite = str_replace('\\', '/', dirname(__DIR__, 2));
if ($pwork === '') {
    echo json_encode(['error' => 'usage']);
    exit;
}
if (!is_dir($pwork)) mkdir($pwork, 0777, true);
define('BASE_DIR', $psite);
define('PUBLIC_DIR', $psite.'/public');
define('CACHE_DIR', $pwork.'/cache');
define('CAPTCHA_DIR', $pwork.'/captcha');
define('LOGS_DIR', $pwork);
define('FUNC_FILE', true);
define('HOME_PATH', '/');
define('PREFIX_DB', 'probe');
define('_TOKENMISS', 'token');
define('_SAVECHANGES', 'save');
define('_NO_INFO', 'none');
define('_SUCCSAVE', 'saved');
define('_HACK', 'hack');
define('_ANONYM', 'guest');
define('_NOPASS', 'nopass');
define('_ERROR_PASS', 'differ');
define('_CHARMIN', 'short');
set_time_limit(60);
$pdone = ['error' => '', 'data' => []];
$conf = ['adminfo' => '1', 'censor' => 0, 'security' => ['url_get' => '1', 'url_post' => '0', 'write_h' => '1', 'blocker_ip' => '']];
$pseen = [];
$psuper = false;
$ptoken = false;
$pscope = '';

# Cut one top-level function out of a shipped source and load it into this process
function setProbeLift(string $file, string $func, string $into): void {
    $code = (string)file_get_contents($file);
    $from = strpos($code, "\nfunction ".$func.'(');
    $end = ($from === false) ? false : strpos($code, "\n}\n", $from);
    if ($end === false) throw new RuntimeException('Function '.$func.' was not found');
    file_put_contents($into, "<?php\n".substr($code, $from, $end - $from + 3));
    require $into;
}

# Record a report that stops the request
function addHackReport(string $msg): void {
    $GLOBALS['pseen'][] = 'stop: '.$msg;
}

# Record a line of the journal
function addWatchReport(string $msg): void {
    $GLOBALS['pseen'][] = 'watch: '.$msg;
}

# Answer the rights of the scenario
function isAdmin(bool $super = false): bool {
    return $super && $GLOBALS['psuper'];
}

# Answer the token check of the scenario and note the scope it was asked for
function checkSiteToken(string $tok = '', string $scope = 'ajax'): bool {
    $GLOBALS['pscope'] = $scope;
    return $GLOBALS['ptoken'];
}

# A text of any length fits the store of the scenario
function checkEditorTextRoom(string $text, string $key): string {
    return '';
}

# The bbcode editor of a default site
function getEditorMode(?string $key = null): string {
    return 'bb';
}

# No html editor on a default site
function checkHtmlEditor(?string $key = null): bool {
    return false;
}

# Keep the line ends the parser would keep
function replace_break(string $text): string {
    return $text;
}

# The secret of the scenario, a key is all a caller asks of it
function getSecret(string $purpose): string {
    return hash('sha256', 'probe'.$purpose);
}

# Run one value through the real reader and answer what it reported
function getProbeSeen(string $val, string $from, string $name = 'q'): array {
    $GLOBALS['pseen'] = [];
    checkRequestData([$name => $val], $from);
    return $GLOBALS['pseen'];
}

final class ProbeTpl {
    # Answer the content a part or a fragment would frame, so the answer of the page can be read
    public function __call(string $name, array $args): string {
        return (string)($args[1]['content_html'] ?? $args[1]['text'] ?? '');
    }
}

try {
    $lifts = ['checkRequestData', 'checkRequestValue', 'filterVar', 'filterNum', 'filterWord', 'getVar', 'filterTrustedTags', 'filterText', 'filterHtml', 'filterSourceText',
        'filterWebUrl', 'getBanList', 'getReportSkip', 'checkPassRule'];
    foreach ($lifts as $i => $one) setProbeLift($psite.'/core/security.php', $one, $pwork.'/lift_'.$i.'.php');
    setProbeLift($psite.'/core/helpers.php', 'setTplAdminInfoPage', $pwork.'/lift_info.php');
    require_once $psite.'/core/classes/cache.php';
    require_once $psite.'/core/classes/parser.php';
    require_once $psite.'/core/classes/captcha.php';
    $pget = fn(string $v): array => getProbeSeen($v, 'GET');
    $ppost = fn(string $v): array => getProbeSeen($v, 'POST');
    $pdone['data']['stops'] = array_map($pget, ['../../etc/passwd', '..\\win.ini', '%2e%2e/', '.%2e/x', '<script>x', "<script\n>", 'php://filter', 'PHAR://x']);
    $pdone['data']['words'] = array_map($pget, ['Soviet Union', 'drop shipping', "O'Reilly", 'Windows (64-bit)', 'https://example.com']);
    $pdone['data']['clean'] = array_map($pget, ['hello world', 'Eyedropper.gif', 'Reunion', 'a/b.jpg', 'x..y']);
    $pdone['data']['posts'] = array_map($ppost, ['../../etc/passwd', '<script>x</script>', 'select a plan', "it's", 'probe_users']);
    $pdone['data']['long'] = getProbeSeen('<'.str_repeat('a', 2097152).'<script>', 'POST');
    $pcook = [base64_encode('12:Alter-Ego:hash'), 'https://ref.example/landing', '<script>x', '../x'];
    $pdone['data']['cookie'] = array_map(fn(string $v): array => getProbeSeen($v, 'COOKIE'), $pcook);
    $pdone['data']['named'] = getProbeSeen('secretvalue <script>', 'POST', 'pass');
    $pdone['data']['nest'] = (function(): array {
        $GLOBALS['pseen'] = [];
        checkRequestData(['a' => ['b' => ['c' => '../x']]], 'GET');
        return $GLOBALS['pseen'];
    })();
    $purls = ['javascript:alert(1)//http://a', 'JaVaScRiPt:alert(1)', 'data:text/html,x//http://a', 'vbscript:x', 'https://ok.example/', 'ok.example',
        'ok.example:8080/x', 'localhost:8080'];
    $pdone['data']['urls'] = array_map('filterWebUrl', $purls);
    $pdone['data']['tags'] = [filterHtml('[use\\php]x[/use\\php]'), filterHtml('[usephp]x[/usephp]'), filterText('%5Busephp%5Dx%5B/usephp%5D'), filterText('[use<b>php]x'),
        filterSourceText('[use[usephp]php]x')];
    $ban = fn(int $age): string => '198.51.100.'.$age.'/32|0|'.(time() + $age - 50).'|hack';
    $conf['security']['blocker_ip'] = '192.0.2.1/32|0|'.(time() + 900).'|manual||'.$ban(1).'||'.$ban(90).'||';
    $pdone['data']['ban'] = explode('||', getBanList('203.0.113.1/32|0|9|hack'));
    $pauto = array_map(fn(int $v): string => '198.51.100.1/32|0|'.(time() + 900).'|hack', range(1, 1200));
    $conf['security']['blocker_ip'] = '192.0.2.1/32|0|'.(time() + 900).'|manual||'.implode('||', $pauto).'||';
    $pdone['data']['cap'] = explode('||', getBanList('203.0.113.1/32|0|9|hack'));
    $pdone['data']['hand'] = explode('||', getBanList('203.0.113.2/32|0|'.(time() + 900).'|manual'));
    $conf['users']['minpass'] = 8;
    $pdone['data']['rule'] = array_map(fn(array $v): string => checkPassRule(...$v), [['', ''], ['abcdefgh', ''], ['abcdefgh', 'abcdefgx'], ['abcdefg', 'abcdefg'],
        ['abcdefgh', 'abcdefgh'], ['пароль', 'пароль']]);
    $pdone['data']['skip'] = [getReportSkip(), getReportSkip(), getReportSkip()];
    file_put_contents(LOGS_DIR.'/report_mail.json', json_encode(['time' => time() - 3601, 'num' => 2]));
    $pdone['data']['skip'][] = getReportSkip();
    $tpl = new ProbeTpl();
    $prs = new Parser();
    $locale = 'ru';
    $afile = 'admin';
    $side = $pwork.'/side.txt';
    $mark = '[usephp]file_put_contents('.var_export($side, true).', "1"); echo "RAN";[/usephp]';
    $_SERVER['HTTP_HX_REQUEST'] = 'true';
    mkdir($pwork.'/doc', 0777, true);
    $page = function(bool $super, bool $token) use ($mark, $side, $pwork): array {
        $GLOBALS['psuper'] = $super;
        $GLOBALS['ptoken'] = $token;
        $_POST = ['save_info' => '1', 'text' => $mark];
        if (is_file($side)) unlink($side);
        foreach (glob($pwork.'/doc/*') ?: [] as $file) unlink($file);
        ob_start();
        setTplAdminInfoPage(['base' => $pwork.'/doc']);
        ob_get_clean();
        return ['ran' => is_file($side), 'file' => glob($pwork.'/doc/*') !== []];
    };
    $pdone['data']['info'] = ['super_no_token' => $page(true, false), 'admin_token' => $page(false, true), 'admin_no_token' => $page(false, false)];
    $pdone['data']['info']['super_token'] = $page(true, true);
    setProbeLift($psite.'/core/security.php', 'checkSitePost', $pwork.'/lift_site.php');
    $site = function(string $method, array $post) {
        $GLOBALS['ptoken'] = true;
        $GLOBALS['pscope'] = '';
        $_SERVER['REQUEST_METHOD'] = $method;
        $_POST = $post;
        $_GET = ['token' => 'query'];
        return [checkSitePost('forum'), $GLOBALS['pscope']];
    };
    $pdone['data']['site'] = [$site('GET', ['token' => 'body']), $site('POST', []), $site('POST', ['token' => 'body'])];
    setProbeLift($psite.'/core/system.php', 'checkPassHash', $pwork.'/lift_hash.php');
    setProbeLift($psite.'/modules/account/index.php', 'getPassMatch', $pwork.'/lift_match.php');
    $pform = 'pa$$<w&"ord';
    $pkept = ['raw' => $pform, 'login' => htmlspecialchars(trim(substr(filterHtml($pform), 0, 25))), 'register' => htmlspecialchars(substr(filterHtml($pform), 0, 40)),
        'profile' => filterHtml($pform)];
    $pdone['data']['forms'] = array_map(fn(string $v): bool => getPassMatch($pform, password_hash($v, PASSWORD_BCRYPT)) === $v, $pkept);
    $pdone['data']['forms']['wrong'] = getPassMatch('other', password_hash($pform, PASSWORD_BCRYPT));
    $pdone['data']['forms']['empty'] = getPassMatch('', password_hash($pform, PASSWORD_BCRYPT));
    $pdone['data']['limit'] = [];
    $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
    foreach (['getIpFamily', 'getIpNorm', 'getIpCidr', 'getIpMatch', 'getIp'] as $i => $one) setProbeLift($psite.'/core/security.php', $one, $pwork.'/lift_ip'.$i.'.php');
    $pip = function(string $proxy, string $peer, ?string $hops, ?string $real = null): string {
        $GLOBALS['conf']['security']['proxy'] = $proxy;
        $_SERVER['REMOTE_ADDR'] = $peer;
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP']);
        if ($hops !== null) $_SERVER['HTTP_X_FORWARDED_FOR'] = $hops;
        if ($real !== null) $_SERVER['HTTP_X_REAL_IP'] = $real;
        return getIp();
    };
    $pdone['data']['ip'] = [
        'no_list' => $pip('', '203.0.113.5', '198.51.100.9'),
        'stranger' => $pip('10.0.0.0/8', '203.0.113.5', '198.51.100.9'),
        'proxy' => $pip('10.0.0.0/8', '10.0.0.2', '198.51.100.9'),
        'forged' => $pip('10.0.0.0/8', '10.0.0.2', '6.6.6.6, 198.51.100.9'),
        'chain' => $pip('10.0.0.0/8,192.0.2.0/24', '10.0.0.2', '198.51.100.9, 192.0.2.7'),
        'garbage' => $pip('10.0.0.0/8', '10.0.0.2', 'unknown'),
        'real' => $pip('10.0.0.0/8', '10.0.0.2', null, '198.51.100.9'),
        'none' => $pip('10.0.0.0/8', '10.0.0.2', null),
        'ipv6' => $pip('2001:db8::/32', '2001:db8::1', '2001:db8:1::5, 198.51.100.9'),
        'bad_peer' => $pip('', 'unix:', '198.51.100.9'),
    ];
    $GLOBALS['conf']['security']['proxy'] = '';
    $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
    for ($i = 0; $i < 4; $i++) Captcha::registerLoginFailure('user', 'Anna');
    $pdone['data']['limit'][] = Captcha::isLoginLimited('user', 'anna');
    Captcha::registerLoginFailure('user', 'Anna');
    $pdone['data']['limit'][] = Captcha::isLoginLimited('user', 'ANNA');
    $pdone['data']['limit'][] = Captcha::isLoginLimited('user', 'boris');
    $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
    $pdone['data']['limit'][] = Captcha::isLoginLimited('user', 'anna');
    $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
    Captcha::clearLoginFailures('user', 'own');
    $pdone['data']['limit'][] = Captcha::isLoginLimited('user', 'anna');
    Captcha::clearLoginFailures('user', 'anna');
    $pdone['data']['limit'][] = Captcha::isLoginLimited('user', 'anna');
    for ($i = 0; $i < 30; $i++) Captcha::registerLoginFailure('admin', 'name'.$i);
    Captcha::clearLoginFailures('admin', 'name0');
    $pdone['data']['limit'][] = Captcha::isLoginLimited('admin', 'fresh');
} catch (Throwable $err) {
    $pdone['error'] = get_class($err).': '.$err->getMessage().' @ '.basename($err->getFile()).':'.$err->getLine();
}
echo json_encode($pdone);
