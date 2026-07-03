<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Output buffering on — must precede all bootstrap operations.
if (ob_get_level() === 0) ob_start();

# Security: block /index.php/... PATH_INFO abuse to avoid routing bypass and unexpected module execution via malformed URLs.
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (!empty($_SERVER['PATH_INFO']) || strpos($uri, '/index.php/') !== false) {
    $_GET['error'] = 404;
}

# Ensure configuration exists when this file is analyzed or included directly
if (!isset($conf) || !is_array($conf)) $conf = getConfig();

# Set the default timezone
date_default_timezone_set($conf['gtime']);

# Language on — init locale, load main language file, set cookie
setLang();

# Database connection using unified config
require_once BASE_DIR.'/core/classes/pdo.php';
$db = new Database($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $conf['db']['name'], $conf['db']['charset']);
define('PREFIX_DB', $conf['db']['prefix']);

# Security and routing aliases
$afile = $conf['security']['afile'];

# Report PHP errors
if ($conf['security']['error'] === 2) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} elseif ($conf['security']['error'] === 1) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL & ~E_NOTICE);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

# Session start
if (session_status() === PHP_SESSION_NONE) session_start();

# Flood Protection
if (!defined('ADMIN_FILE') && $conf['security']['flood']) {
    $ctime = time();
    $ftime = $ctime - intval($conf['security']['flood_t']);
    $flood = (isset($_SESSION['flood']) && $_SESSION['flood'] > $ftime) ? 1 : 0;
    if ($conf['security']['flood'] == 3 && $flood) addWarnReport('Flood attack');
    if ($conf['security']['flood'] == 2 && isset($_GET) && $flood) addWarnReport('Flood in GET - '.print_r($_GET, true));
    if (isset($_POST) && $flood) addWarnReport('Flood in POST - '.print_r($_POST, true));
    unset($_SESSION['flood']);
    $_SESSION['flood'] = $ctime;
}

# Format admin variable
$admin = (($tmp = base64_decode($_SESSION[$conf['admin_c']] ?? '', true)) && $tmp !== '') ? explode(':', $tmp) : [];

# Format user variable
$user = (($tmp = base64_decode($_COOKIE[$conf['user_c'].'-account'] ?? '', true)) && $tmp !== '') ? explode(':', $tmp) : [];

# Analyzer of variables
function getVariablesInfo(): string {
    $cont = [];
    foreach (['POST', 'GET', 'COOKIE', 'FILES', 'SESSION'] as $var) {
        $arr = $GLOBALS['_'.$var] ?? [];
        if ($arr) $cont[] = $var.': '.print_r($arr, true);
    }
    return implode(PHP_EOL, $cont);
}

# Add security log entry (IP, user, URL, agent; auto-rotates on size limit)
function addLog(): bool {
    global $user, $conf;
    $ip = getIp();
    $agent = getAgent();
    $url = filterText((string)getenv('REQUEST_URI'));
    $refer = getReferer();
    $ref = $refer ? PHP_EOL._REFERER.': '.$refer : '';
    $luser = (is_array($user) && isset($user[1])) ? substr((string)$user[1], 0, 25) : substr(_ANONYM, 0, 25);
    $log = LOGS_DIR.'/log.log';
    $max = $conf['security']['log_size'] ?? 10485760;
    $fhandle = fopen($log, 'ab');
    if ($fhandle === false) return false;
    clearstatcache(true, $log);
    if (filesize($log) >= $max) {
        fclose($fhandle);
        $safe = pathinfo($log, PATHINFO_FILENAME).'_'.date('Y-m-d_H-i-s');
        addCompress(dirname($log), $log, $safe, 'auto', true, true);
        $fhandle = fopen($log, 'ab');
        if ($fhandle === false) return false;
    }
    $vars = getVariablesInfo();
    $entry = ($vars ? $vars.PHP_EOL : '')._IP.': '.$ip.PHP_EOL._USER.': '.$luser.PHP_EOL._URL.': '.$url.$ref.PHP_EOL._BROWSER.': '.$agent.PHP_EOL._DATE.': '.date(_TIMESTRING).PHP_EOL.'----'.PHP_EOL;
    fwrite($fhandle, $entry);
    fclose($fhandle);
    return true;
}
if ($conf['security']['log']) addLog();

# Security cookies blocker or ip blocker and member blocker
$bcookie = getCookies($conf['security']['blocker_cookie']);
if ($bcookie == 'block') {
    setExit(_BANN_INFO);
} else {
    $bip = explode('||', $conf['security']['blocker_ip']);
    if ($bip) {
        $iptbase = getIp();
        $uagt = md5(getAgent());
        foreach ($bip as $val) {
            if ($val != '') {
                $binfo = explode('|', $val, 4);
                if (count($binfo) < 4) continue;
                $cidr = getIpCidr($binfo[0]);
                if ($cidr === false) continue;
                if (time() <= (int)$binfo[2]) {
                    if ((!$binfo[1] && getIpMatch($iptbase, $cidr)) || ($binfo[1] && getIpMatch($iptbase, $cidr) && $uagt == $binfo[1])) {
                        setCookies($conf['security']['blocker_cookie'], $binfo[2], 'block');
                        $btext = _BANN_INFO.'. '._BANN_TERM.': '.getTimeLeft($binfo[2]).'. '._BANN_REAS.': '.$binfo[3];
                        setExit($btext);
                    }
                }
            }
        }
    }
    $bus = explode('||', $conf['security']['blocker_user']);
    if ($bus && $user) {
        foreach ($bus as $val) {
            if ($val != '') {
                $tus = isset($user[1]) ? substr((string)$user[1], 0, 25) : '';
                $uinfo = explode('|', $val);
                if (count($uinfo) < 3) continue;
                if (time() <= (int)$uinfo[1]) {
                    if ($tus == $uinfo[0]) {
                        setCookies($conf['security']['blocker_cookie'], $uinfo[1], 'block');
                        $utext = _BANN_INFO.'. '._BANN_TERM.': '.getTimeLeft($uinfo[1]).'. '._BANN_REAS.': '.$uinfo[2];
                        setExit($utext);
                    }
                }
            }
        }
    }
}

# Render the branded error page for whitelisted ?error= codes (web server routes error_page/ErrorDocument to it); the whitelist blocks forged or invalid statuses
$ecode = isset($_GET['error']) ? (int)$_GET['error'] : 0;
if ($ecode && in_array($ecode, [400, 401, 402, 403, 404, 500, 502, 503, 504], true)) setError($ecode);

# Add PHP errors to error_php.log
function addPhpLog($no, $mesg, $file, $line) {
    $map = [
        1 => ['error', 'ERROR'],
        2 => ['warning', 'WARNING'],
        4 => ['error', 'PARSE'],
        8 => ['notice', 'NOTICE'],
        16 => ['error', 'CORE_ERROR'],
        32 => ['warning', 'CORE_WARNING'],
        64 => ['error', 'COMPILE_ERROR'],
        128 => ['warning', 'COMPILE_WARNING'],
        256 => ['error', 'USER_ERROR'],
        512 => ['warning', 'USER_WARNING'],
        1024 => ['notice', 'USER_NOTICE'],
        2048 => ['notice', 'STRICT'],
        4096 => ['error', 'RECOVERABLE_ERROR'],
        8192 => ['notice', 'DEPRECATED'],
        16384 => ['notice', 'USER_DEPRECATED'],
    ];
    [$levl, $perr] = $map[$no] ?? ['error', 'UNKNOWN'];
    Logger::addPhp($levl, (string)$mesg, [
        'php_errno' => (int)$no,
        'php_err' => $perr,
        'file' => (string)$file,
        'line' => (int)$line,
    ]);
    return true;
}

# Add SQL errors to error_sql.log
function addSqlLog($no, $mesg, $sql) {
    $sql = (string)$sql;
    $info = (string)$no;
    Logger::addSql('error', 'SQL error '.$info.': '.(string)$mesg, [
        'sql_info' => $info,
        'sql_errno' => is_numeric($no) ? (int)$no : null,
        'sql_state' => null,
        'sql' => $sql,
        'sql_hash' => hash('sha256', $sql),
        'sql_bytes' => strlen($sql),
    ]);
}

# Render a consistent response for uncaught errors: visible detail in debug, clean 500 in production, never inside non-HTML responses
function setErrorOut(string $detail = ''): void {
    global $conf;
    static $busy = false;
    if ($busy) return;
    $busy = true;
    if (in_array((string)($_REQUEST['go'] ?? ''), ['1', '2', '3', '4', '5', 'asset', 'captcha', 'rss', 'xsl'], true) || headers_sent()) {
        if (!headers_sent()) http_response_code(500);
        return;
    }
    if ($detail !== '' && (int)($conf['security']['error'] ?? 0) > 0) setExit($detail);
    setError(500);
}

# Non-fatal PHP errors go to error_php.log when logging is enabled
if ($conf['security']['error_log']) set_error_handler('addPhpLog');

# Uncaught exceptions get a consistent response; logging stays gated by error_log, rendering does not
$lexc = false;
set_exception_handler(function(Throwable $e) use (&$lexc) {
    global $conf;
    if (!empty($conf['security']['error_log'])) {
        Logger::addPhp('error', get_class($e).': '.$e->getMessage(), [
            'php_errno' => $e->getCode(),
            'php_err' => 'EXCEPTION',
            'ex_class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
    $lexc = true;
    setErrorOut(get_class($e).': '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
});

# Fatal shutdown errors are logged when enabled and carry a 500 status; no heavy render in shutdown (state may be broken)
register_shutdown_function(function() use (&$lexc) {
    global $conf;
    if ($lexc) return;
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!empty($conf['security']['error_log'])) {
            Logger::addPhp('error', (string)$e['message'], [
                'php_errno' => $e['type'],
                'php_err' => 'FATAL',
                'file' => $e['file'],
                'line' => $e['line'],
            ]);
        }
        if (!headers_sent()) http_response_code(500);
    }
});

# Checking URL, GET, POST, COOKIE, FILES variables for safety
if (!isAdmin(true)) {
    $ruri = mb_strlen((string)($_SERVER['REQUEST_URI'] ?? ''), 'utf-8');
    if ($ruri > 2048) addWarnReport('Spam in URL - '.$ruri.' > 2048');
    if (isset($_GET)) {
        function checkGet($name, $val) {
            global $conf;
            $links = '#^(http\:\/\/|https\:\/\/|ftp\:\/\/|php\:\/\/|\/\/)#i';
            $script = '#<.*?(script|body|object|iframe|applet|meta|form|style|img).*?>#i';
            $char = '#\([^>]*\"?[^)]*\)#';
            $quote = '#\'|\.\.[/\\\\%]#';
            $string = '#ALTER|DROP|INSERT|OUTFILE|SELECT|TRUNCATE|UNION|'.PREFIX_DB.'_admins|'.PREFIX_DB.'_users|admins_show|admins_add|admins_save|admins_del#i';
            $decode = base64_decode($val);
            $slash = preg_replace('#\/\*.*?\*\/#', '', $val);
            if ($conf['security']['url_get']) if (preg_match($links, $val)) addWarnReport('URL in GET - '.$name.' = '.$val);
            if (preg_match($script, urldecode($val)) || preg_match($char, $val)) addWarnReport('HTML in GET - '.$name.' = '.$val);
            if (preg_match($quote, $val)) addHackReport('Hack in GET - '.$name.' = '.$val);
            if (preg_match($string, $val)) addHackReport('XSS in GET - '.$name.' = '.$val);
            if (preg_match($string, $decode)) addHackReport('XSS base64 in GET - '.$name.' = '.$val);
            if (preg_match($string, $slash)) addHackReport('XSS slash in GET - '.$name.' = '.$val);
        }
        function getGet($in) {
            if (is_array($in)) {
                foreach ($in as $key => $val) {
                    if (is_array($val)) {
                        getGet($val);
                    } else {
                        checkGet($key, $val);
                    }
                }
            } else {
                checkGet(_NO, $in);
            }
        }
        getGet($_GET);
    }
    if (isset($_POST)) {
        function checkPost($name, $val) {
            global $conf;
            $links = '#^(http\:\/\/|https\:\/\/|ftp\:\/\/|php\:\/\/|\/\/)#i';
            $script = '#<.*?(script|body|object|iframe|applet|meta|form).*?>#i';
            $string = '#'.PREFIX_DB.'_admins|'.PREFIX_DB.'_users#i';
            $decode = base64_decode($val);
            $slash = preg_replace('#\/\*.*?\*\/#', '', $val);
            if ($conf['security']['ref_post'] && isset($_FILES['file']['size'])) if (!intval($_FILES['file']['size']) && !stristr(getenv('HTTP_REFERER'), getHost())) addWarnReport('POST from referer - '.$name.' = '.$val);
            if ($conf['security']['url_post']) if (preg_match($links, $val)) addWarnReport('URL in POST - '.$name.' = '.$val);
            if (!checkHtmlEditor() && preg_match($script, urldecode($val))) addWarnReport('HTML in POST - '.$name.' = '.$val);
            if (preg_match($string, $val)) addHackReport('XSS in POST - '.$name.' = '.$val);
            if (preg_match($string, $decode)) addHackReport('XSS base64 in POST - '.$name.' = '.$val);
            if (preg_match($string, $slash)) addHackReport('XSS slash in POST - '.$name.' = '.$val);
        }
        function getPost($in) {
            if (is_array($in)) {
                foreach ($in as $key => $val) {
                    if (is_array($val)) {
                        getPost($val);
                    } else {
                        checkPost($key, $val);
                    }
                }
            } else {
                checkPost(_NO, $in);
            }
        }
        getPost($_POST);
    }
    if (isset($_COOKIE)) {
        function checkCookie($name, $val) {
            $links = '#^(http\:\/\/|https\:\/\/|ftp\:\/\/|php\:\/\/|\/\/)#i';
            $script = '#<.*?(script|body|object|iframe|applet|meta|form|style|img).*?>#i';
            $string = '#ALTER|DROP|INSERT|OUTFILE|SELECT|TRUNCATE|UNION|'.PREFIX_DB.'_admins|'.PREFIX_DB.'_users|admins_show|admins_add|admins_save|admins_del#i';
            $decode = base64_decode($val);
            $slash = preg_replace('#\/\*.*?\*\/#', '', $val);
            if (preg_match($links, $val)) addHackReport('URL in COOKIE - '.$name.' = '.$val);
            if (preg_match($script, $val)) addHackReport('HTML in COOKIE - '.$name.' = '.$val);
            if (preg_match($string, $val)) addHackReport('XSS in COOKIE - '.$name.' = '.$val);
            if (preg_match($string, $decode)) addHackReport('XSS base64 in COOKIE - '.$name.' = '.$val);
            if (preg_match($string, $slash)) addHackReport('XSS slash in COOKIE - '.$name.' = '.$val);
        }
        function getCookie($in) {
            if (is_array($in)) {
                foreach ($in as $key => $val) {
                    if (is_array($val)) {
                        getCookie($val);
                    } else {
                        checkCookie($key, $val);
                    }
                }
            } else {
                checkCookie(_NO, $in);
            }
        }
        getCookie($_COOKIE);
    }
    if (isset($_FILES)) {
        function checkFiles($name, $val) {
            $type = '#php.*|js|htm|html|phtml|cgi|pl|perl|asp#i';
            if (isset($_FILES['userfile'])) {
                $dot = strrchr($_FILES['userfile']['name'], '.');
                $val = ($dot !== false) ? strtolower(substr($dot, 1)) : '';
                if ($val !== '' && preg_match($type, $val)) addHackReport('Hack in FILES - '.$name.' = '.$val);
            } elseif (isset($_FILES['file'])) {
                if (is_array($_FILES['file']['name'])) {
                    $files = count($_FILES['file']['name']);
                    for ($i = 0; $i < $files; $i++) {
                        $dot = strrchr($_FILES['file']['name'][$i], '.');
                        $val = ($dot !== false) ? strtolower(substr($dot, 1)) : '';
                        if ($val !== '' && preg_match($type, $val)) addHackReport('Hack in FILES - '.$name.' = '.$val);
                    }
                } else {
                    $dot = strrchr($_FILES['file']['name'], '.');
                    $val = ($dot !== false) ? strtolower(substr($dot, 1)) : '';
                    if ($val !== '' && preg_match($type, $val)) addHackReport('Hack in FILES - '.$name.' = '.$val);
                }
            } elseif (isset($_FILES[$name]['name'])) {
                $dot = strrchr($_FILES[$name]['name'], '.');
                $val = ($dot !== false) ? strtolower(substr($dot, 1)) : '';
                if ($val !== '' && preg_match($type, $val)) addHackReport('Hack in FILES - '.$name.' = '.$val);
            }
        }
        function getFiles($in) {
            if (is_array($in)) {
                foreach ($in as $key => $val) {
                    if (is_array($val)) {
                        getFiles($val);
                    } else {
                        checkFiles($key, $val);
                    }
                }
            } else {
                checkFiles(_NO, $in);
            }
        }
        getFiles($_FILES);
    }
}

# Return true if the current session has valid admin credentials (result cached per request)
function isAdmin(bool $super = false): bool {
    global $db, $admin;
    static $cache = [];
    $key = (int)$super;
    if (isset($cache[$key])) return $cache[$key];
    if (empty($admin)) return $cache[0] = $cache[1] = false;
    $id = intval(substr($admin[0], 0, 11));
    $name = substr($admin[1], 0, 25);
    $pwd = $admin[2];
    $ip = getIp();
    if ($id && $name && $pwd && $ip) {
        [$aname, $apwd, $aip, $asuper] = $db->getSqlRow($db->getSqlQuery('SELECT name, password, ip, super FROM '.PREFIX_DB.'_admins WHERE id = :id', ['id' => $id])) ?? ['', '', '', '0'];
        if ($aname !== '' && $aname === $name && $apwd !== '' && hash_equals($apwd, $pwd) && $aip !== '' && $aip === $ip) {
            $cache[0] = true;
            $cache[1] = ((int)$asuper === 1);
            return $cache[$key];
        }
    }
    return $cache[0] = $cache[1] = false;
}

# Return true when the debug panel may be collected: variables[0] disables it, var_view 1 opens it to all visitors, 0 limits it to super-admins
function checkDebugView(): bool {
    global $conf;
    static $view = null;
    static $busy = false;
    if ($view !== null) return $view;
    if ($busy || !isset($GLOBALS['admin'])) return false;
    $busy = true;
    $cvar = explode(',', $conf['variables'] ?? '');
    $view = empty($cvar[0]) && (($conf['var_view'] ?? '') == '1' || isAdmin(true));
    $busy = false;
    return $view;
}

# Format exit and displaying information with an optional page heading
function setExit(string $msg, string $typ = '', string $title = ''): never {
    global $conf;
    $file = defined('BASE_DIR') ? BASE_DIR.'/core/classes/template.php' : '';
    if (!class_exists('Template') && $file !== '' && is_file($file)) require_once $file;
    if (!class_exists('Template')) die('Template engine unavailable');
    $theme = getTheme();
    $tpl = new Template($theme);
    $text = $tpl->getHtmlFrag('alert', ['text' => $msg, 'is_warn' => true]);
    $base = rtrim((string)($conf['homeurl'] ?? ''), '/').'/';
    $meta = $tpl->getHtmlFrag('head-base', ['href' => $base]) . "\n"
        . $tpl->getHtmlFrag('head-meta', ['name' => 'author', 'content' => (string)($conf['sitename'] ?? '')]) . "\n"
        . $tpl->getHtmlFrag('head-meta', ['name' => 'generator', 'content' => 'SLAED CMS '.($conf['version'] ?? '')]) . "\n";
    $license = getLicenseHtml();
    $adlogo = basename((string)($conf['admin_logo'] ?? 'slaed_logo_256x73.png'));
    $adpath = img_find('logos/'.$adlogo);
    if (!is_file($adpath)) $adpath = img_find('logos/slaed_logo_256x73.png');
    $linksrc = [];
    $favicon = 'templates/'.$theme.'/images/favicon.svg';
    if (is_file(BASE_DIR.'/'.$favicon)) {
        $linksrc[] = $tpl->getHtmlFrag('head-link', ['rel' => 'shortcut icon', 'href' => $favicon, 'type' => 'image/svg+xml', 'title' => '']);
    }
    foreach (getThemeCssFiles($theme) as $asset) {
        $linksrc[] = $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => $asset, 'type' => '', 'title' => '']);
    }
    $iconcss = 'templates/'.$theme.'/assets/vendor/bootstrap/css/bootstrap-icons.min.css';
    if (is_file(BASE_DIR.'/'.$iconcss)) {
        $linksrc[] = $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => $iconcss, 'type' => '', 'title' => '']);
    }
    $links = implode("\n", $linksrc);
    die($tpl->getHtmlPage('message', [
        'lang' => substr(_LOCALE, 0, 2),
        'theme' => $theme,
        'sitename' => $conf['sitename'] ?? '',
        'homeurl' => $conf['homeurl'] ?? '',
        'slogan' => $conf['slogan'] ?? '',
        'meta' => $meta,
        'links' => $links,
        'scripts' => '',
        'login' => '',
        'is_bare' => true,
        'license' => $license,
        'home' => ($typ !== '') ? _HOME : '',
        'search' => ($typ !== '') ? _SEARCH : '',
        'feedback' => _FEEDBACK,
        'recommend' => _RECOMMEND,
        'head_html' => '',
        'foot_html' => $license,
        'blocks_left' => '',
        'blocks_right' => '',
        'blocks_down' => '',
        'adlogo' => $adpath,
        'adalt' => $conf['sitename'] ?? 'SLAED CMS',
        'adtitle' => $conf['sitename'] ?? 'SLAED CMS',
        'logo' => $conf['site_logo'] ?? '',
        'title' => $title,
        'message_html' => $text,
    ]));
}

# Emit an HTTP error status, log it when enabled, and render the standard error page before stopping, for SEO-correct responses such as out-of-range pagination
function setError(int $code = 404): never {
    global $conf;
    $msg = [400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required', 403 => 'Forbidden', 404 => 'Not Found', 410 => 'Gone', 500 => 'Internal Server Error', 502 => 'Bad Gateway', 503 => 'Service Unavailable', 504 => 'Gateway Timeout'][$code] ?? 'Error';
    if (!headers_sent()) http_response_code($code);
    if (!empty($conf['security']['error_log'])) {
        Logger::addSite('error', ($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1').' '.$code.' '.$msg, [
            'http_code' => $code,
            'module' => isset($_GET['name']) ? substr((string)$_GET['name'], 0, 50) : null,
            'op' => isset($_GET['op']) ? substr((string)$_GET['op'], 0, 50) : null,
        ]);
    }
    if (ob_get_level() > 0) ob_clean();
    setExit(sprintf(_ERROR_PAGE, $code), '1', _ERROR.' '.$code);
}

# Cookie set
function setCookies(string $name, int $time, string|array $value): void {
    global $conf;
    $info = is_array($value) ? base64_encode(implode(':', array_slice($value, 0, 6))) : $value;
    $url = parse_url($conf['homeurl']);
    $sec = ($url['scheme'] == 'http') ? false : true;
    $options = ['expires' => $time, 'path' => '/', 'domain' => $url['host'], 'secure' => $sec, 'httponly' => true, 'samesite' => 'Lax'];
    setcookie($conf['user_c'].'-'.$name, $info, $options);
}

# Delete cookie set
function setCookiesDelete(string $name): void {
    global $conf;
    setcookie($conf['user_c'].'-'.$name, '', time() - 3600, '/', parse_url($conf['homeurl'], PHP_URL_HOST));
}

# Get cookie
function getCookies(string $name): string {
    global $conf;
    $cookie = isset($_COOKIE[$conf['user_c'].'-'.$name]) ? filterVar($_COOKIE[$conf['user_c'].'-'.$name]) : '';
    return $cookie;
}

# Get the client's real IP address
function getIp(): string {
    foreach (['REMOTE_ADDR', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED'] as $header) {
        if (isset($_SERVER[$header])) {
            foreach (explode(',', $_SERVER[$header]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
    }
    return '0.0.0.0';
}

# Return IP family: 4, 6 or 0 (invalid)
function getIpFamily(string $ip): int {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return 4;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return 6;
    return 0;
}

# Normalize IP to canonical text representation
function getIpNorm(string $ip): string|false {
    $fam = getIpFamily($ip);
    if (!$fam) return false;
    $pack = inet_pton($ip);
    if ($pack === false) return false;
    $norm = inet_ntop($pack);
    return $norm === false ? false : $norm;
}

# Normalize CIDR (accepts "ip" and converts to host prefix)
function getIpCidr(string $cidr): string|false {
    $cidr = trim($cidr);
    if ($cidr === '') return false;

    if (!str_contains($cidr, '/')) {
        $ip = getIpNorm($cidr);
        if ($ip === false) return false;
        $fam = getIpFamily($ip);
        $pre = ($fam === 4) ? 32 : 128;
        return $ip.'/'.$pre;
    }

    [$ip, $pre] = explode('/', $cidr, 2);
    $ip = getIpNorm($ip);
    if ($ip === false) return false;
    if (!preg_match('#^\d+$#', $pre)) return false;
    $pre = (int)$pre;
    $fam = getIpFamily($ip);
    $max = ($fam === 4) ? 32 : 128;
    if ($pre < 0 || $pre > $max) return false;
    return $ip.'/'.$pre;
}

# Match IP against CIDR
function getIpMatch(string $ip, string $cidr): bool {
    $ip = getIpNorm($ip);
    $cidr = getIpCidr($cidr);
    if ($ip === false || $cidr === false) return false;

    [$rip, $pre] = explode('/', $cidr, 2);
    $pre = (int)$pre;
    $fam = getIpFamily($ip);
    if (!$fam || $fam !== getIpFamily($rip)) return false;

    $ipp = inet_pton($ip);
    $rpp = inet_pton($rip);
    if ($ipp === false || $rpp === false) return false;

    $bytes = intdiv($pre, 8);
    $rest = $pre % 8;
    if ($bytes > 0 && substr($ipp, 0, $bytes) !== substr($rpp, 0, $bytes)) return false;
    if ($rest === 0) return true;

    $mbyte = (0xFF << (8 - $rest)) & 0xFF;
    $ibyte = ord($ipp[$bytes]);
    $rbyte = ord($rpp[$bytes]);
    return ($ibyte & $mbyte) === ($rbyte & $mbyte);
}

# Return host CIDR for an IP (/32 or /128)
function getIpHostCidr(string $ip): string|false {
    $ip = getIpNorm($ip);
    if ($ip === false) return false;
    $fam = getIpFamily($ip);
    $pre = ($fam === 4) ? 32 : 128;
    return $ip.'/'.$pre;
}

# Get user agent
function getAgent(): string {
    $uagt = getenv('HTTP_USER_AGENT');
    if ($uagt && strcasecmp($uagt, 'unknown')) {
        return filterText($uagt);
    } elseif (!empty($_SERVER['HTTP_USER_AGENT']) && strcasecmp($_SERVER['HTTP_USER_AGENT'], 'unknown')) {
        return filterText($_SERVER['HTTP_USER_AGENT']);
    }
    return 'unknown';
}

# Return current HTTP host name (HTTP_HOST with SERVER_NAME fallback)
function getHost(): string {
    return getenv('HTTP_HOST') ?: getenv('SERVER_NAME') ?: '';
}

# Return a purpose-scoped key derived from the site master secret.
# The master is a 256-bit CSPRNG value, generated and persisted on first use (lazy bootstrap).
function getSecret(string $purpose): string {
    global $conf;
    $master = (string)($conf['security']['secret'] ?? '');
    if ($master === '') {
        $master = bin2hex(random_bytes(32));
        $sec = is_array($conf['security'] ?? null) ? $conf['security'] : [];
        $sec['secret'] = $master;
        $conf['security'] = $sec;
        if (function_exists('setConfigFile')) setConfigFile('security.php', $sec);
    }
    return hash_hmac('sha256', $purpose, $master);
}

# Returns a session-bound HMAC-SHA256 CSRF token scoped to the given context.
# Token is valid for the lifetime of the PHP session; invalidated when the master secret changes.
function getSiteToken(string $scope = 'ajax'): string {
    $sid  = session_id();
    $data = $sid !== '' ? $scope.'|'.$sid : $scope;
    return hash_hmac('sha256', $data, getSecret('csrf'));
}

# Return CSRF token from request context:
# 1) Header `X-CSRF-Token` (HTMX/global)
# 2) Header `X-XSRF-Token`
# 3) `token` param (POST/GET/REQUEST)
function getRequestToken(): string {
    $tok = '';

    $h1 = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? getenv('HTTP_X_CSRF_TOKEN') ?: '';
    if (is_string($h1) && $h1 !== '') $tok = trim($h1);

    if ($tok === '') {
        $h2 = $_SERVER['HTTP_X_XSRF_TOKEN'] ?? getenv('HTTP_X_XSRF_TOKEN') ?: '';
        if (is_string($h2) && $h2 !== '') $tok = trim($h2);
    }

    if ($tok === '') {
        $p = filter_input(INPUT_POST, 'token', FILTER_UNSAFE_RAW);
        if (is_string($p) && $p !== '') $tok = trim($p);
    }

    if ($tok === '') {
        $g = filter_input(INPUT_GET, 'token', FILTER_UNSAFE_RAW);
        if (is_string($g) && $g !== '') $tok = trim($g);
    }

    if ($tok === '' && isset($_REQUEST['token']) && is_string($_REQUEST['token'])) {
        $tok = trim($_REQUEST['token']);
    }

    return $tok;
}

# Validates CSRF token for a scope using timing-safe comparison.
# Smart behavior:
# - if `$tok` is empty, auto-read token from request (header/param)
# - accepts exact scope token
# - for scoped checks, accepts global `ajax` token as fallback
function checkSiteToken(string $tok = '', string $scope = 'ajax'): bool {
    if ($tok === '') $tok = getRequestToken();
    if ($tok === '') return false;
    if (hash_equals(getSiteToken($scope), $tok)) return true;
    if ($scope !== 'ajax' && hash_equals(getSiteToken('ajax'), $tok)) return true;
    return false;
}

# Return external HTTP referer URL, or empty string if internal/invalid/unknown
function getReferer(): string {
    $referer = filterText(getenv('HTTP_REFERER'));
    if (!empty($referer) && !preg_match('#^unknown#i', $referer) && !preg_match('#^bookmark#i', $referer) && !stristr($referer, getHost())) {
        return $referer;
    }
    return '';
}

# Determine active locale, load main language file, set language cookie
function setLang(): void {
    global $locale, $conf;
    $mlang = $conf['language'] ?? 'en';
    $mult = ((int)($conf['multilingual'] ?? 0) === 1);
    if ($mult) {
        $newlang = getVar('req', 'newlang', 'var', '');
        $clang = getCookies('language');
        if ($newlang && is_readable('lang/'.$newlang.'.php')) {
            $locale = $newlang;
        } elseif ($clang && is_readable('lang/'.$clang.'.php')) {
            $locale = $clang;
        } else {
            $locale = $mlang;
        }
        if (!$clang || $clang !== $locale) {
            setCookies('language', time() + (int)($conf['user_c_t'] ?? 0), $locale);
        }
    } else {
        $locale = $mlang;
    }
    $file = 'lang/'.$locale.'.php';
    require_once is_readable($file) ? $file : 'lang/'.$mlang.'.php';
}

# Load module language file and return the active locale
function getLang(string $module = '', bool $admin = false): string {
    global $locale, $conf;
    static $lmods = [];
    if ($module === '') return $locale;
    $mlang = $conf['language'] ?? 'en';
    $ctx = $admin ? 'a' : 'f';
    $key = $module.'|'.$ctx.'|'.$locale;
    if (!array_key_exists($key, $lmods)) {
        if ($module === 'admin') {
            $list = ['admin/lang/'.$locale.'.php', 'admin/lang/'.$mlang.'.php'];
        } elseif ($admin) {
            $list = [
                'modules/'.$module.'/admin/lang/'.$locale.'.php',
                'modules/'.$module.'/admin/lang/'.$mlang.'.php',
            ];
        } else {
            $list = [
                'modules/'.$module.'/lang/'.$locale.'.php',
                'modules/'.$module.'/lang/'.$mlang.'.php',
            ];
        }
        $done = false;
        foreach ($list as $p) {
            if (is_readable($p)) {
                require_once $p;
                $done = true;
                break;
            }
        }
        $lmods[$key] = $done;
    }
    return $locale;
}

# Clean access to POST, GET or Request parameters
# A 'key[]' name returns the whole array value; 'key[n]' returns one element; other keys return a filtered scalar
function getVar(string $var, string $key, string $type = '', mixed $default = ''): mixed {
    $arridx = null;
    $allarr = false;
    if (preg_match('/^([^\[]+)\[(\d*)\]$/', $key, $matches)) {
        $key = $matches[1];
        if ($matches[2] === '') {
            $allarr = true;
        } else {
            $arridx = (int)$matches[2];
        }
    }
    $filters = [
        'num' => fn($v) => filterNum($v),
        'let' => fn($v) => is_string($v) ? mb_substr(trim($v), 0, 1, 'utf-8') : $v,
        'word' => fn($v) => is_string($v) ? filterWord(urldecode(trim($v))) : $v,
        'name' => fn($v) => is_string($v) ? filterText(mb_substr(trim($v), 0, 25, 'utf-8')) : $v,
        'title' => fn($v) => is_string($v) ? filterHtml(trim($v), 1) : $v,
        'text' => fn($v) => is_string($v) ? filterHtml(trim($v)) : $v,
        'field' => fn($v) => is_string($v) ? filterFields(trim($v)) : $v,
        'url' => fn($v) => is_string($v) ? filterUrl(trim($v)) : $v,
        'var' => fn($v) => is_string($v) ? filterVar($v) : $v,
        'bool' => fn($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN),
        'defis' => fn($v) => is_string($v) ? (($v = trim($v)) !== '' ? urlencode($v) : '') : $v,
        'time' => function($v) {
            $v = is_string($v) ? trim($v) : '';
            $ts = strtotime($v);
            return ($ts !== false && date('Y-m-d H:i', $ts) === $v) ? $v.':00' : date('Y-m-d H:i:s');
        },
        'date' => function($v) {
            $v = is_string($v) ? trim($v) : '';
            $ts = strtotime($v);
            return ($ts !== false && date('Y-m-d', $ts) === $v) ? $v : date('Y-m-d');
        },
        'raw' => fn($v) => $v,
    ];
    if ($allarr) {
        $p = $_POST[$key] ?? [];
        $g = $_GET[$key] ?? [];
        $value = match(strtolower($var)) {
            'post' => $p,
            'get' => $g,
            'req' => (!empty($p)) ? $p : $g,
            default => [],
        };
        if (!is_array($value)) return $default ?: [];
        if ($type) {
            $filtered = [];
            foreach ($value as $item) {
                $lt = strtolower($type);
                if (isset($filters[$lt])) $item = $filters[$lt]($item);
                if ($item !== false && $item !== null && $item !== '') $filtered[] = $item;
            }
            return $filtered;
        }
        return $value;
    }
    if ($arridx !== null) {
        $p = $_POST[$key][$arridx] ?? '';
        $g = $_GET[$key][$arridx] ?? '';
    } else {
        $p = filter_input(INPUT_POST, $key, FILTER_DEFAULT) ?? '';
        $g = filter_input(INPUT_GET, $key, FILTER_DEFAULT) ?? '';
    }
    $value = match(strtolower($var)) {
        'post' => $p,
        'get' => $g,
        'req' => ($p !== null && $p !== '') ? $p : $g,
        default => null,
    };
    if (strtolower($type) === 'defis') {
        if ($value === null || $value === '') {
            return ($default !== '' && $default !== null) ? $default : false;
        }
        $value = $filters['defis']($value);
        return ($value !== '' && $value !== null) ? $value : (($default !== '' && $default !== null) ? $default : false);
    }
    $value = ($value !== null && $value !== '') ? $value : $default;
    $lt = strtolower($type);
    if ($lt && isset($filters[$lt])) {
        $value = $filters[$lt]($value);
    } else {
        if (is_string($value)) $value = trim($value);
    }
    return ($value !== '' && $value !== null) ? $value : $default;
}

# Is there any content in the array
function isArray(mixed $arr): bool {
    if (!is_array($arr)) return !empty($arr);
    foreach ($arr as $a) {
        if (isArray($a)) return true;
    }
    return false;
}

# Filter string or array: return value unchanged if only [a-zA-Z0-9_-], else return ''
function filterVar(string|array $var): string|array {
    if (is_array($var)) return preg_grep('#[^a-zA-Z0-9_\-]#', $var) ? [] : $var;
    return preg_match('#[^a-zA-Z0-9_\-]#', $var) ? '' : $var;
}

# Normalize URL: ensure http(s) prefix, lowercase, run through text_filter; return '' if bare protocol
function filterUrl(string $url): string {
    $url = strtolower($url);
    $url = preg_match('#https?://#i', $url) ? $url : 'http://'.$url;
    return ($url === 'http://') ? '' : filterText($url);
}

# Strip non-digits and return as integer
function filterNum(mixed $var): int {
    return intval(preg_replace('#[^0-9]#', '', (string)$var));
}

# Strip chars outside [Unicode letters, digits, whitespace, %&/|.:;&_+-=]
function filterWord(string $var): string {
    return preg_replace('#[^\pL0-9\s%&/|.:;&_+\-=]#siu', '', $var) ?? '';
}

# Strip tags, HTML-encode, apply censor; $type=2 skips strip_tags (HTML allowed), $type=1 skips censor
function filterText(string|array $message, int $type = 0): string {
    global $conf;
    if (is_array($message)) $message = filterFields($message);
    if (!isAdmin()) {
        while (preg_match('#\[(usehtml|/usehtml)\]|\[(usephp|/usephp)\]#si', $message)) {
            $message = preg_replace('#\[(usehtml|/usehtml)\]|\[(usephp|/usephp)\]#si', '', $message);
        }
    }
    if ($type === 2) {
        $message = htmlspecialchars(trim($message), ENT_QUOTES);
    } else {
        $message = htmlspecialchars(trim(strip_tags(urldecode($message ?? ''))), ENT_QUOTES);
    }
    if (!isAdmin() && $conf['censor'] && $type !== 1) {
        foreach (explode(',', $conf['censor_l']) as $val) {
            $val = trim($val);
            if ($val === '') continue;
            $message = preg_replace('#'.preg_quote($val, '#').'#i', $conf['censor_r'], $message);
        }
    }
    return $message;
}

# Length center filter
function filterCut(string $linkstrip, int $strip): string {
    if (strlen($linkstrip) > $strip) $linkstrip = substr($linkstrip, 0, $strip - 19).'...'.substr($linkstrip, -16);
    return $linkstrip;
}

# Format ed2k links
function getEd2kLink(array $m): string {
    $href = 'url='.$m[2];
    $fname = rawurldecode($m[3]);
    $fname = str_replace(['&#038;', '&amp;'], '&', $fname);
    $size = filterSize($m[4]);
    return ' eMule/eDonkey: ['.$href.']'.filterCut($fname, 50).'[/url] - '._SIZE.': '.$size;
}

# Convert plain URLs, ed2k links and email addresses in text to BBCode tags
function filterClickable(string $text): string {
    $ret = $text;
    if (!preg_match("#\[php\](.*)\[/php\]|\[code\](.*)\[/code\]#si", $text)) {
        $ret = preg_replace_callback("#([\n ])(?<=[^\w\"'])(ed2k://\|file\|([^\\/\|:<>\*\?\"]+?)\|(\d+?)\|([a-f0-9]{32})\|(.*?)/?)(?![\"'])(?=([,\.]*?[\s<\[])|[,\.]*?$)#i", 'getEd2kLink', ' '.$text);
        $ret = preg_replace("#([\n ])(?<=[^\w\"'])(ed2k://\|server\|([\d\.]+?)\|(\d+?)\|/?)#i", 'ed2k Server: [url=\\2]\\3[/url] - Port: \\4', $ret);
        $ret = preg_replace("#([\n ])(?<=[^\w\"'])(ed2k://\|friend\|([^\\/\|:<>\*\?\"]+?)\|([\d\.]+?)\|(\d+?)\|/?)#i", 'Friend: [url=\\2]\\3[/url]', $ret);
        $ret = preg_replace("#([\n ])([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", '\\1[url=\\2]\\2[/url]', $ret);
        $ret = preg_replace("#([\n ])((www|ftp)\.[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", '\\1[url=http://\\2]\\2[/url]', $ret);
        $ret = preg_replace("#([\n ])([a-z0-9&\-_.]+?)@([\w\-]+\.([\w\-\.]+\.)*[\w]+)#i", '\\1[mail=\\2@\\3]\\2@\\3[/mail]', $ret);
        $ret = substr($ret, 1);
    } else {
        if (preg_match('#(.*)\[php\](.*)\[/php\](.*)#si', $text, $matches)) {
            $ret = filterClickable($matches[1]).'[php]'.$matches[2].'[/php]'.filterClickable($matches[3]);
        } elseif (preg_match('#(.*)\[code(.*)\](.*)\[/code\](.*)#si', $text, $matches)) {
            $ret = filterClickable($matches[1]).'[code'.$matches[2].']'.$matches[3].'[/code]'.filterClickable($matches[4]);
        }
    }
    return $ret;
}

# Convert raw user text to HTML-safe output; applies nl2br, escaping and URL linking; skips URL auto-linking when $id === 1
function filterHtml(string $text, mixed $id = ''): string {
    global $conf;
    if ($text) {
        if (!checkHtmlEditor()) {
            $text = ($conf['clickable'] && $id != 1) ? filterClickable($text) : $text;
            $out = nl2br(str_replace(['$', '\\'], ['&#036;', '&#092;'], stripslashes(filterText($text, 2))), false);
        } else {
            $out = str_replace(['"', '$', '\'', '\\'], ['&#034;', '&#036;', '&#039;', '&#092;'], stripslashes($text));
        }
        return $out;
    }
    return '';
}

# Filter and join an array of custom fields into a pipe-separated string
function filterFields(mixed $field): string {
    if (isArray($field)) return stripslashes(filterText(implode('|', $field), 2));
    return '';
}

# Format a duration in seconds as human-readable hours/minutes/seconds
function getDuration(int $sec): string {
    $min = floor($sec / 60);
    $hours = floor($min / 60);
    $seconds = $sec % 60;
    $minutes = $min % 60;
    return ($hours == 0) ? (($min == 0) ? $seconds.' '._SEC.'.' : $min.' '._MIN.'. '.$seconds.' '._SEC.'.') : $hours.' '._HOUR.'. '.$minutes.' '._MIN.'. '.$seconds.' '._SEC.'.';
}

# Return one formatted remaining-time string
function getTimeLeft(int $time): string {
    $now = time();
    $end = date(_DATESTRING, $time);
    $expire = $time - $now;
    $days = round($expire / 86400, 3).' '._DAYS;
    return ($now < $time) ? getDuration($expire).' ('.$days.') - '.$end : $end.' - '._END;
}

# Add an outgoing HTML email (base64-encoded); appends IP/browser info when $id is truthy
function addMail(string $email, string $smail, string $subject, string $message, int $id = 0, int $pr = 0): void {
    global $conf;
    $email = filterText($email);
    $smail = filterText($smail);
    $subject = '=?'._CHARSET.'?b?'.base64_encode(filterText($subject)).'?=';
    $pr = $pr ?: 3;
    $agent = getAgent();
    if ($id) {
        global $tpl;
        if ($tpl instanceof Template) {
            $message .= $tpl->getHtmlPart('message-block', [
                'title' => '',
                'lines' => [
                    ['label' => _IP, 'value' => getIp()],
                    ['label' => _BROWSER, 'value' => $agent],
                    ['label' => _HASH, 'value' => md5($agent)],
                ],
            ]);
        } else {
            $message .= PHP_EOL._IP.': '.getIp().PHP_EOL._BROWSER.': '.$agent.PHP_EOL._HASH.': '.md5($agent);
        }
    }
    $mheader = "MIME-Version: 1.0\n"
    .'Content-Type: text/html; charset='._CHARSET."\n"
    ."Content-Transfer-Encoding: base64\n"
    .'From: "=?'._CHARSET.'?b?'.base64_encode($conf['sitename']).'?=" <'.$smail.">\n"
    .'Reply-To: "'.$smail.'" <'.$smail.">\n"
    .'Return-Path: <'.$smail.">\n"
    .'X-Priority: '.$pr."\n"
    ."X-Mailer: SLAED CMS\n";
    set_error_handler(static function (): bool {
        return true;
    }, E_WARNING);
    mail($email, $subject, base64_encode($message), $mheader);
    restore_error_handler();
}

# Add a hack report with optional block and email
function addHackReport(string $msg): void {
    global $user, $conf, $tpl;
    $msg = filterText(substr($msg, 0, 500));
    $url = filterText(getenv('REQUEST_URI'));
    $refer = getReferer();
    $ref = ($refer) ? PHP_EOL._REFERER.': '.$refer : '';
    $ip = getIp();
    $agent = getAgent();
    $dtime = date(_TIMESTRING);
    $luser = (is_array($user) && isset($user[1])) ? substr((string)$user[1], 0, 25) : substr(_ANONYM, 0, 25);
    if ($conf['security']['block']) {
        $btime = time() + 86400;
        $cidr = getIpHostCidr($ip);
        if ($cidr !== false) {
            $cont = ['blocker_ip' => $conf['security']['blocker_ip'].$cidr.'|'.md5($agent).'|'.$btime.'|'._HACK.'||'];
            setConfigFile('security.php', $cont, $conf['security']);
        }
        setCookies($conf['security']['blocker_cookie'], $btime, 'block');
    }
    if ($conf['security']['mail']) {
        $subject = $conf['sitename'].' - '._SECURITY;
        if ($tpl instanceof Template) {
            $lines = [
                ['label' => _IP, 'value' => $ip],
                ['label' => _USER, 'value' => $luser],
                ['label' => _URL, 'value' => $url],
            ];
            if ($refer) $lines[] = ['label' => _REFERER, 'value' => $refer];
            $lines[] = ['label' => _BROWSER, 'value' => $agent];
            $lines[] = ['label' => _DATE, 'value' => $dtime];
            $mmsg = $tpl->getHtmlPart('message-block', ['title' => $subject, 'intro_text' => _HACK.': '.$msg, 'lines' => $lines]);
        } else {
            $mmsg = $conf['sitename'].' - '._SECURITY.PHP_EOL._HACK.': '.$msg.PHP_EOL._IP.': '.$ip.PHP_EOL._USER.': '.$luser.PHP_EOL._URL.': '.$url.$ref.PHP_EOL._BROWSER.': '.$agent.PHP_EOL._DATE.': '.$dtime;
        }
        addMail($conf['adminmail'], $conf['adminmail'], $subject, $mmsg, 0, 1);
    }
    if ($conf['security']['write_h']) {
        Logger::addHack('warning', $msg, ['user' => $luser, 'hash' => md5($agent), 'blocked' => (bool)$conf['security']['block']]);
    }
    setExit(_HACK.'!', 1);
}

# Add a warning report with optional email
function addWarnReport(string $msg): void {
    global $user, $conf, $tpl;
    $msg = filterText(substr($msg, 0, 500));
    $url = filterText(getenv('REQUEST_URI'));
    $refer = getReferer();
    $ref = ($refer) ? PHP_EOL._REFERER.': '.$refer : '';
    $ip = getIp();
    $agent = getAgent();
    $dtime = date(_TIMESTRING);
    $luser = (is_array($user) && isset($user[1])) ? substr((string)$user[1], 0, 25) : substr(_ANONYM, 0, 25);
    if ($conf['security']['mail_w']) {
        $subject = $conf['sitename'].' - '._SECURITY;
        if ($tpl instanceof Template) {
            $lines = [
                ['label' => _IP, 'value' => $ip],
                ['label' => _USER, 'value' => $luser],
                ['label' => _URL, 'value' => $url],
            ];
            if ($refer) $lines[] = ['label' => _REFERER, 'value' => $refer];
            $lines[] = ['label' => _BROWSER, 'value' => $agent];
            $lines[] = ['label' => _DATE, 'value' => $dtime];
            $mmsg = $tpl->getHtmlPart('message-block', ['title' => $subject, 'intro_text' => _WARN.': '.$msg, 'lines' => $lines]);
        } else {
            $mmsg = $conf['sitename'].' - '._SECURITY.PHP_EOL._WARN.': '.$msg.PHP_EOL._IP.': '.$ip.PHP_EOL._USER.': '.$luser.PHP_EOL._URL.': '.$url.$ref.PHP_EOL._BROWSER.': '.$agent.PHP_EOL._DATE.': '.$dtime;
        }
        addMail($conf['adminmail'], $conf['adminmail'], $subject, $mmsg, 0, 1);
    }
    if ($conf['security']['write_w']) {
        Logger::addWarn('warning', $msg, ['user' => $luser]);
    }
    setExit(_WARN.'!', 1);
}
