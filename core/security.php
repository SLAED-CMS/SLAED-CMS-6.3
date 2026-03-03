<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Output buffering on — must precede all bootstrap operations.
if (ob_get_level() === 0) ob_start();

# Security: block /index.php/... PATH_INFO abuse to avoid routing bypass and unexpected module execution via malformed URLs.
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (!empty($_SERVER['PATH_INFO']) || strpos($uri, '/index.php/') !== false) {
    header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
    $_GET['error'] = 404;
}

# Load unified config - merges all /config/*.php into $conf, applies local.php overrides
$conf = getConfig();

# Set the default timezone
date_default_timezone_set($conf['gtime']);

# Language on — init locale, load main language file, set cookie
setLang();

# Database connection using unified config
require_once BASE_DIR.'/core/classes/pdo.php';
$db = new Database($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $conf['db']['name'], $conf['db']['charset']);
if ($conf['db']['sync']) $db->getSqlQuery("SET LOCAL time_zone = '".date('P')."'");
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
    if (is_array($user) && isset($user[1]) && $user[1] !== null) {
        $luser = substr((string)$user[1], 0, 25);
    } else {
        $luser = substr(_ANONYM, 0, 25);
    }
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
                $binfo = explode('|', $val);
                if (time() <= $binfo[3]) {
                    $ipt = $iptbase;
                    $ipb = $binfo[0];
                    if ($binfo[1] <= 3) {
                        $ipt = substr($ipt, 0, strrpos($ipt, '.'));
                        $ipb = substr($ipb, 0, strrpos($ipb, '.'));
                    }
                    if ($binfo[1] <= 2) {
                        $ipt = substr($ipt, 0, strrpos($ipt, '.'));
                        $ipb = substr($ipb, 0, strrpos($ipb, '.'));
                    }
                    if ($binfo[1] == 1) {
                        $ipt = substr($ipt, 0, strrpos($ipt, '.'));
                        $ipb = substr($ipb, 0, strrpos($ipb, '.'));
                    }
                    if ((!$binfo[2] && $ipt == $ipb) || ($binfo[2] && $ipt == $ipb && $uagt == $binfo[2])) {
                        setCookies($conf['security']['blocker_cookie'], $binfo[3], 'block');
                        $btext = _BANN_INFO.'<br>'._BANN_TERM.': '.getTimeLeft($binfo[3]).'<br>'._BANN_REAS.': '.$binfo[4];
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
                $tus = substr($user[1], 0, 25);
                $uinfo = explode('|', $val);
                if (time() <= $uinfo[1]) {
                    if ($tus == $uinfo[0]) {
                        setCookies($conf['security']['blocker_cookie'], $uinfo[1], 'block');
                        $utext = _BANN_INFO.'<br>'._BANN_TERM.': '.getTimeLeft($uinfo[1]).'<br>'._BANN_REAS.': '.$uinfo[2];
                        setExit($utext);
                    }
                }
            }
        }
    }
}

if ($conf['security']['error_log']) {
    $ls = fn(string $s, int $max = 2048): string => substr(str_replace(["\r", "\n", "\0"], ' ', trim($s)), 0, $max);
    $lredact = function(array $arr): array {
        foreach (array_keys($arr) as $k) {
            if (preg_match('/(pass|token|auth|secret|key|session|csrf)/i', (string)$k)) {
                $arr[$k] = '[REDACTED]';
            }
        }
        return $arr;
    };

    // Bound array: max 50 keys, string values max 1024 chars, then redact
    $lbound = function(array $arr) use ($lredact): array {
        if (count($arr) > 50) {
            $arr = array_slice($arr, 0, 50, true);
            $arr['*_truncated'] = true;
        }
        foreach ($arr as $k => $v) {
            if (is_string($v) && strlen($v) > 1024) {
                $arr[$k] = substr($v, 0, 1024).'[...]';
            } elseif (is_array($v)) {
                $arr[$k] = '[array:'.count($v).']';
            }
        }
        return $lredact($arr);
    };

    // Bounded request context (structured, no raw superglobal dumps)
    $lctx = function() use ($lbound): array {
        $q = $lbound($_GET ?? []);
        if ($q === []) $q = new stdClass();
        $p = $lbound($_POST ?? []);
        if ($p === []) $p = new stdClass();
        $ck = array_keys($_COOKIE ?? []);
        $cktr = count($ck) > 50;
        if ($cktr) $ck = array_slice($ck, 0, 50);
        $sk = (session_status() === PHP_SESSION_ACTIVE) ? array_keys($_SESSION ?? []) : [];
        $sktr = count($sk) > 50;
        if ($sktr) $sk = array_slice($sk, 0, 50);
        $ctx = ['query' => $q, 'post' => $p, 'cookie_keys' => $ck, 'session_keys' => $sk];
        if ($cktr) $ctx['cookie_keys_truncated'] = true;
        if ($sktr) $ctx['session_keys_truncated'] = true;
        return $ctx;
    };

    // Common request fields
    $lreq = function() use ($ls): array {
        $ua = $ls($_SERVER['HTTP_USER_AGENT'] ?? '', 512);
        $url = $ls($_SERVER['REQUEST_URI'] ?? (string)getenv('REQUEST_URI'), 2048);
        $ref = $ls(getReferer() ?: '', 2048);
        return [
            'req_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['UNIQUE_ID'] ?? null,
            'ip' => getIp(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'url' => $url ?: null,
            'referer' => $ref ?: null,
            'ua' => $ua ?: null,
        ];
    };

    // Memory metrics
    $lmem = fn(): array => [
        'mem_mb' => round(memory_get_usage(true) / 1048576, 2),
        'mem_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
    ];

    // Stable fingerprint: type|file|line|msg-normalized — no errno (unstable for exceptions)
    $lfp = function(string $type, string $file, string $line, string $msg): string {
        $norm = preg_replace(['/\d+/', '/\s+/'], ['#', ' '], substr($msg, 0, 200));
        return substr(sha1($type.'|'.$file.'|'.$line.'|'.$norm), 0, 8);
    };

    // Rotation guard (prevents parallel-worker double-rotate) + atomic append
    $lwrite = function(string $log, string $line) use ($conf): void {
        $max = (int)($conf['security']['log_size'] ?? 10485760);
        clearstatcache(true, $log);
        $sz = filesize($log);
        if ($sz !== false && $sz >= $max) {
            $guard = $log.'.rotating';
            if (file_put_contents($guard, '1', LOCK_EX) !== false) {
                $safe = pathinfo($log, PATHINFO_FILENAME).'_'.date('Y-m-d_H-i-s');
                addCompress(dirname($log), $log, $safe, 'auto', true, true);
                unlink($guard);
            }
        }
        file_put_contents($log, $line.PHP_EOL, FILE_APPEND | LOCK_EX);
    };

    # HTTP error → error_site.log
    if (isset($_GET['error'])) {
        $error = intval($_GET['error']);
        $http = [
            100 => 'HTTP/1.1 100 Continue',
            101 => 'HTTP/1.1 101 Switching Protocols',
            200 => 'HTTP/1.1 200 OK',
            201 => 'HTTP/1.1 201 Created',
            202 => 'HTTP/1.1 202 Accepted',
            203 => 'HTTP/1.1 203 Non-Authoritative Information',
            204 => 'HTTP/1.1 204 No Content',
            205 => 'HTTP/1.1 205 Reset Content',
            206 => 'HTTP/1.1 206 Partial Content',
            300 => 'HTTP/1.1 300 Multiple Choices',
            301 => 'HTTP/1.1 301 Moved Permanently',
            302 => 'HTTP/1.1 302 Found',
            303 => 'HTTP/1.1 303 See Other',
            304 => 'HTTP/1.1 304 Not Modified',
            305 => 'HTTP/1.1 305 Use Proxy',
            307 => 'HTTP/1.1 307 Temporary Redirect',
            400 => 'HTTP/1.1 400 Bad Request',
            401 => 'HTTP/1.1 401 Unauthorized',
            402 => 'HTTP/1.1 402 Payment Required',
            403 => 'HTTP/1.1 403 Forbidden',
            404 => 'HTTP/1.1 404 Not Found',
            405 => 'HTTP/1.1 405 Method Not Allowed',
            406 => 'HTTP/1.1 406 Not Acceptable',
            407 => 'HTTP/1.1 407 Proxy Authentication Required',
            408 => 'HTTP/1.1 408 Request Time-out',
            409 => 'HTTP/1.1 409 Conflict',
            410 => 'HTTP/1.1 410 Gone',
            411 => 'HTTP/1.1 411 Length Required',
            412 => 'HTTP/1.1 412 Precondition Failed',
            413 => 'HTTP/1.1 413 Request Entity Too Large',
            414 => 'HTTP/1.1 414 Request-URI Too Large',
            415 => 'HTTP/1.1 415 Unsupported Media Type',
            416 => 'HTTP/1.1 416 Requested range not satisfiable',
            417 => 'HTTP/1.1 417 Expectation Failed',
            500 => 'HTTP/1.1 500 Internal Server Error',
            501 => 'HTTP/1.1 501 Not Implemented',
            502 => 'HTTP/1.1 502 Bad Gateway',
            503 => 'HTTP/1.1 503 Service Unavailable',
            504 => 'HTTP/1.1 504 Gateway Time-out',
        ];
        $httpmsg = $http[$error] ?? null;
        if ($httpmsg) {
            $log = LOGS_DIR.'/error_site.log';
            $fp = $lfp('site', '', '', $httpmsg);
            $row = array_merge([
                'ts' => date('c'),
                'level' => 'error',
                'type' => 'site',
                'msg' => $httpmsg,
                'http_code' => $error,
                'module' => isset($_GET['name']) ? $ls((string)$_GET['name'], 50) : null,
                'op' => isset($_GET['op']) ? $ls((string)$_GET['op'], 50) : null,
                'fingerprint' => $fp,
            ], $lreq(), $lctx(), $lmem());
            $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($line !== false) { $lwrite($log, $line); }
        }
        unset($http, $httpmsg);
        setExit('Error '.$error, 1);
    }

    # PHP errors → error_php.log
    function addPhpLog($errno, $errmsg, $errfile, $errline) {
        global $ls, $lctx, $lreq, $lmem, $lwrite, $lfp;
        $levelmap = [
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
        [$level, $phperr] = $levelmap[$errno] ?? ['error', 'UNKNOWN'];
        $log = LOGS_DIR.'/error_php.log';
        $fp = $lfp('php', $errfile, (string)$errline, (string)$errmsg);
        $row = array_merge([
            'ts' => date('c'),
            'level' => $level,
            'type' => 'php',
            'msg' => $ls((string)$errmsg, 1024),
            'php_errno' => $errno,
            'php_err' => $phperr,
            'file' => $errfile,
            'line' => (int)$errline,
            'fingerprint' => $fp,
        ], $lreq(), $lctx(), $lmem());
        $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($line !== false) { $lwrite($log, $line); }
        return true;
    }
    set_error_handler('addPhpLog');

    // Guard: exception handler sets this flag so shutdown handler skips the same event
    $lexcepted = false;

    set_exception_handler(function(Throwable $e) use ($ls, $lctx, $lreq, $lmem, $lwrite, $lfp, &$lexcepted) {
        $log = LOGS_DIR.'/error_php.log';
        $msg = get_class($e).': '.$e->getMessage();
        $fp = $lfp('php', $e->getFile(), (string)$e->getLine(), $msg);
        $row = array_merge([
            'ts' => date('c'),
            'level' => 'error',
            'type' => 'php',
            'msg' => $ls($msg, 1024),
            'php_errno' => $e->getCode(),
            'php_err' => 'EXCEPTION',
            'ex_class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'fingerprint' => $fp,
        ], $lreq(), $lctx(), $lmem());
        $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($line !== false) { $lwrite($log, $line); }
        $lexcepted = true;
    });

    // Shutdown: real fatals only (E_ERROR etc.) — skip if exception handler already logged this event
    register_shutdown_function(function() use ($ls, $lwrite, $lfp, &$lexcepted) {
        if ($lexcepted) return;
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $log = LOGS_DIR.'/error_php.log';
            $ua = $ls($_SERVER['HTTP_USER_AGENT'] ?? '', 512);
            $url = $ls((string)($_SERVER['REQUEST_URI'] ?? ''), 2048);
            $fp = $lfp('php', $e['file'], (string)$e['line'], $e['message']);
            $row = [
                'ts' => date('c'),
                'level' => 'error',
                'type' => 'php',
                'msg' => $ls($e['message'], 1024),
                'php_errno' => $e['type'],
                'php_err' => 'FATAL',
                'file' => $e['file'],
                'line' => $e['line'],
                'fingerprint' => $fp,
                'req_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['UNIQUE_ID'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                'url' => $url ?: null,
                'referer' => null,
                'ua' => $ua ?: null,
                'mem_mb' => round(memory_get_usage(true) / 1048576, 2),
                'mem_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            ];
            $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($line !== false) { $lwrite($log, $line); }
        }
    });

    # SQL errors → error_sql.log
    function addSqlLog($errno, $error, $sql) {
        global $ls, $lctx, $lreq, $lmem, $lwrite, $lfp;
        $log = LOGS_DIR.'/error_sql.log';
        $sqlorig = (string)$sql;
        $sqlbytes = strlen($sqlorig);
        $sqlhash = hash('sha256', $sqlorig);
        // Redact quoted string literals, then truncate
        $sqlsafe = preg_replace("/'[^']{0,256}'/u", "'?'", $sqlorig);
        $tr = strlen($sqlsafe) > 2000;
        $sqlsafe = substr($sqlsafe, 0, 2000).($tr ? ' [TRUNCATED]' : '');
        $msg = 'SQL error '.$errno.': '.$ls((string)$error, 256);
        $fp = $lfp('sql', '', '', (string)$error);
        $row = array_merge([
            'ts' => date('c'),
            'level' => 'error',
            'type' => 'sql',
            'msg' => $msg,
            'sql_errno' => (int)$errno,
            'sql_state' => null,
            'sql' => $sqlsafe,
            'sql_hash' => $sqlhash,
            'sql_bytes' => $sqlbytes,
            'fingerprint' => $fp,
        ], $lreq(), $lctx(), $lmem());
        $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($line !== false) { $lwrite($log, $line); }
    }
}

# Checking URL, GET, POST, COOKIE, FILES variables for safety
if (!isAdmin(true)) {
    $ruri = mb_strlen($_SERVER['REQUEST_URI'], 'utf-8');
    if ($ruri > 2048) addWarnReport('Spam in URL - '.$ruri.' > 2048');
    if (isset($_GET)) {
        function checkGet($name, $val) {
            global $conf;
            $links = '#^(http\:\/\/|https\:\/\/|ftp\:\/\/|php\:\/\/|\/\/)#i';
            $script = '#<.*?(script|body|object|iframe|applet|meta|form|style|img).*?>#i';
            $char = '#\([^>]*\"?[^)]*\)#';
            $quote = '#\"|\'|\.\.\/|\*#';
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
            global $conf, $admin;
            $flag = is_array($admin) ? ($admin[3] ?? '') : '';
            $editor = (int)substr($flag, 0, 1);
            $links = '#^(http\:\/\/|https\:\/\/|ftp\:\/\/|php\:\/\/|\/\/)#i';
            $script = '#<.*?(script|body|object|iframe|applet|meta|form).*?>#i';
            $string = '#'.PREFIX_DB.'_admins|'.PREFIX_DB.'_users#i';
            $decode = base64_decode($val);
            $slash = preg_replace('#\/\*.*?\*\/#', '', $val);
            if ($conf['security']['ref_post'] && isset($_FILES['file']['size'])) if (!intval($_FILES['file']['size']) && !stristr(getenv('HTTP_REFERER'), getHost())) addWarnReport('POST from referer - '.$name.' = '.$val);
            if ($conf['security']['url_post']) if (preg_match($links, $val)) addWarnReport('URL in POST - '.$name.' = '.$val);
            if (((defined('ADMIN_FILE') && $editor != 1) || (!defined('ADMIN_FILE') && $conf['redaktor'] != 1)) && preg_match($script, urldecode($val))) addWarnReport('HTML in POST - '.$name.' = '.$val);
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
                $val = strtolower(substr(strrchr($_FILES['userfile']['name'], '.'), 1));
                if (preg_match($type, $val)) addHackReport('Hack in FILES - '.$name.' = '.$val);
            } elseif (isset($_FILES['file'])) {
                if (is_array($_FILES['file'])) {
                    $files = count($_FILES['file']['name']);
                    for ($i = 0; $i < $files; $i++) {
                        $val = strtolower(substr(strrchr($_FILES['file']['name'][$i], '.'), 1));
                        if (preg_match($type, $val)) addHackReport('Hack in FILES - '.$name.' = '.$val);
                    }
                } else {
                    $val = strtolower(substr(strrchr($_FILES['file']['name'], '.'), 1));
                    if (preg_match($type, $val)) addHackReport('Hack in FILES - '.$name.' = '.$val);
                }
            } else {
                $val = strtolower(substr(strrchr($_FILES[$name]['name'], '.'), 1));
                if (preg_match($type, $val)) addHackReport('Hack in FILES - '.$name.' = '.$val);
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
    $pwd = substr($admin[2], 0, 40);
    $ip = getIp();
    if ($id && $name && $pwd && $ip) {
        [$aname, $apwd, $aip, $asuper] = $db->getSqlRow($db->getSqlQuery('SELECT name, pwd, ip, super FROM '.PREFIX_DB.'_admins WHERE id = :id', ['id' => $id])) ?? ['', '', '', '0'];
        if ($aname !== '' && $aname === $name && $apwd !== '' && hash_equals($apwd, $pwd) && $aip !== '' && $aip === $ip) {
            $cache[0] = true;
            $cache[1] = ($asuper === '1');
            return $cache[$key];
        }
    }
    return $cache[0] = $cache[1] = false;
}

# Format exit and displaying information
function setExit(string $msg, string $typ = ''): never {
    global $conf;
    $cont = '<!doctype html>'.PHP_EOL
    .'<html lang="'.substr(_LOCALE, 0, 2).'">'.PHP_EOL
    .'<head>'.PHP_EOL
    .'<meta charset="'._CHARSET.'">'.PHP_EOL
    .'<meta name="viewport" content="width=device-width, initial-scale=1.0">'.PHP_EOL
    .'<title>'.$conf['sitename'].' '.urldecode($conf['defis']).' '.$conf['slogan'].'</title>'.PHP_EOL
    .'<meta name="author" content="'.$conf['sitename'].'">'.PHP_EOL
    .'<meta name="generator" content="SLAED CMS '.$conf['version'].'">'.PHP_EOL;
    $cont .= ($typ) ? '<meta http-equiv="refresh" content="5; url='.$conf['homeurl'].'/index.php">'.PHP_EOL : '';
    $cont .= '</head>'.PHP_EOL
    .'<body style="margin: 0; height: 100vh; display: flex; justify-content: center; align-items: center; flex-direction: column;">'.PHP_EOL
    .'<img src="'.$conf['homeurl'].'/templates/'.$conf['theme'].'/images/logos/'.$conf['site_logo'].'" alt="'.$conf['sitename'].'" title="'.$conf['sitename'].'" style="max-width: 90%; height: auto;">'.PHP_EOL
    .'<div style="margin-top: 40px; font: 24px Arial, Tahoma, Verdana, sans-serif; color: #1a4674; font-weight: bold; text-align: center;">'.$msg.'</div>'.PHP_EOL
    .'</body>'.PHP_EOL
    .'</html>';
    die($cont);
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
    $mlang = (string)($conf['language'] ?? 'en');
    $mult = ((int)($conf['multilingual'] ?? 0) === 1);
    if ($mult) {
        $newlang = getVar('req', 'newlang', 'var', '');
        $clang = getCookies('language');
        if ($newlang && is_readable('language/'.$newlang.'.php')) {
            $locale = $newlang;
        } elseif ($clang && is_readable('language/'.$clang.'.php')) {
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
    $file = 'language/'.$locale.'.php';
    require_once is_readable($file) ? $file : 'language/'.$mlang.'.php';
}

# Load module language file and return the active locale
function getLang(string $module = '', bool $admin = false): string {
    global $locale, $conf;
    static $lmods = [];
    if ($module === '') return $locale;
    $mlang = (string)($conf['language'] ?? 'en');
    $ctx = $admin ? 'a' : 'f';
    $key = $module.'|'.$ctx.'|'.$locale;
    if (!array_key_exists($key, $lmods)) {
        if ($module === 'admin') {
            $list = ['admin/language/'.$locale.'.php', 'admin/language/'.$mlang.'.php'];
        } elseif ($admin) {
            $list = [
                'modules/'.$module.'/admin/language/'.$locale.'.php',
                'modules/'.$module.'/admin/language/'.$mlang.'.php',
            ];
        } else {
            $list = [
                'modules/'.$module.'/language/'.$locale.'.php',
                'modules/'.$module.'/language/'.$mlang.'.php',
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
    return ($value !== '' && $value !== null) ? $value : false;
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
    return preg_replace('#[^\pL0-9\s%&/|.:;&_+\-=]#siu', '', $var);
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
            $message = preg_replace('#'.$val.'#i', $conf['censor_r'], $message);
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
    $size = files_size($m[4]);
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
    global $admin, $conf;
    if ($text) {
        $flag = is_array($admin) ? ($admin[3] ?? '') : '';
        $editor = (int)substr($flag, 0, 1);
        if ((defined('ADMIN_FILE') && $editor == 1) || (!defined('ADMIN_FILE') && $conf['redaktor'] == 1)) {
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

# Return HTML span showing remaining time until a Unix timestamp expires
function getTimeLeft(int $time): string {
    $now = time();
    $end = date(_DATESTRING, $time);
    $expire = $time - $now;
    $days = round($expire / 86400, 3).' '._DAYS;
    return ($now < $time) ? '<span title="'.getDuration($expire).'" class="sl_green sl_note">'.$days.' - '.$end.'</span>' : '<span class="sl_red">'.$end.' - '._END.'</span>';
}

# Add an outgoing HTML email (base64-encoded); appends IP/browser info when $id is truthy
function addMail(string $email, string $smail, string $subject, string $message, int $id = 0, int $pr = 0): void {
    global $conf;
    $email = filterText($email);
    $smail = filterText($smail);
    $subject = '=?'._CHARSET.'?b?'.base64_encode(filterText($subject)).'?=';
    $pr = $pr ?: 3;
    $agent = getAgent();
    $message = (!$id) ? $message : $message.'<br><br>'._IP.': '.getIp().'<br>'._BROWSER.': '.$agent.'<br>'._HASH.': '.md5($agent);
    $mheader = "MIME-Version: 1.0\n"
    .'Content-Type: text/html; charset='._CHARSET."\n"
    ."Content-Transfer-Encoding: base64\n"
    .'From: "=?'._CHARSET.'?b?'.base64_encode($conf['sitename']).'?=" <'.$smail.">\n"
    .'Reply-To: "'.$smail.'" <'.$smail.">\n"
    .'Return-Path: <'.$smail.">\n"
    .'X-Priority: '.$pr."\n"
    ."X-Mailer: SLAED CMS\n";
    mail($email, $subject, base64_encode($message), $mheader);
}

# Log a hack attempt: block IP, send alert email, append to hack.log, then exit
function addHackReport(string $msg): void {
    global $user, $conf;
    $msg = filterText(substr($msg, 0, 500));
    $url = filterText(getenv('REQUEST_URI'));
    $refer = getReferer();
    $ref = ($refer) ? PHP_EOL._REFERER.': '.$refer : '';
    $ip = getIp();
    $agent = getAgent();
    $dtime = date(_TIMESTRING);
    $luser = is_array($user) ? substr($user[1], 0, 25) : substr(_ANONYM, 0, 25);
    if ($conf['security']['block']) {
        $btime = time() + 86400;
        $cont = ['blocker_ip' => $conf['security']['blocker_ip'].$ip.'|4|'.md5($agent).'|'.$btime.'|'._HACK.'||'];
        setConfigFile('security.php', $cont, $conf['security']);
        setCookies($conf['security']['blocker_cookie'], $btime, 'block');
    }
    if ($conf['security']['mail']) {
        $subject = $conf['sitename'].' - '._SECURITY;
        $mmsg = $conf['sitename'].' - '._SECURITY.'<br><br>'._HACK.': '.$msg.'<br>'._IP.': '.$ip.'<br>'._USER.': '.$luser.'<br>'._URL.': '.$url.$ref.'<br>'._BROWSER.': '.$agent.'<br>'._DATE.': '.$dtime;
        addMail($conf['adminmail'], $conf['adminmail'], $subject, $mmsg, 0, 1);
    }
    if ($conf['security']['write_h']) {
        $log = LOGS_DIR.'/hack.log';
        $max = $conf['security']['log_size'] ?? 10485760;
        $fhandle = fopen($log, 'ab');
        if ($fhandle !== false) {
            clearstatcache(true, $log);
            if (filesize($log) >= $max) {
                fclose($fhandle);
                $safe = pathinfo($log, PATHINFO_FILENAME).'_'.date('Y-m-d_H-i-s');
                addCompress(dirname($log), $log, $safe, 'auto', true, true);
                $fhandle = fopen($log, 'ab');
            }
            if ($fhandle !== false) {
                fwrite($fhandle, _HACK.': '.$msg.PHP_EOL._IP.': '.$ip.PHP_EOL._USER.': '.$luser.PHP_EOL._URL.': '.$url.$ref.PHP_EOL._BROWSER.': '.$agent.PHP_EOL._DATE.': '.$dtime.PHP_EOL.'----'.PHP_EOL);
                fclose($fhandle);
            }
        }
    }
    setExit(_HACK.'!', 1);
}

# Log a security warning: send alert email, append to warn.log, then exit
function addWarnReport(string $msg): void {
    global $user, $conf;
    $msg = filterText(substr($msg, 0, 500));
    $url = filterText(getenv('REQUEST_URI'));
    $refer = getReferer();
    $ref = ($refer) ? PHP_EOL._REFERER.': '.$refer : '';
    $ip = getIp();
    $agent = getAgent();
    $dtime = date(_TIMESTRING);
    $luser = is_array($user) ? substr($user[1], 0, 25) : substr(_ANONYM, 0, 25);
    if ($conf['security']['mail_w']) {
        $subject = $conf['sitename'].' - '._SECURITY;
        $mmsg = $conf['sitename'].' - '._SECURITY.'<br><br>'._WARN.': '.$msg.'<br>'._IP.': '.$ip.'<br>'._USER.': '.$luser.'<br>'._URL.': '.$url.$ref.'<br>'._BROWSER.': '.$agent.'<br>'._DATE.': '.$dtime;
        addMail($conf['adminmail'], $conf['adminmail'], $subject, $mmsg, 0, 1);
    }
    if ($conf['security']['write_w']) {
        $log = LOGS_DIR.'/warn.log';
        $max = $conf['security']['log_size'] ?? 10485760;
        $fhandle = fopen($log, 'ab');
        if ($fhandle !== false) {
            clearstatcache(true, $log);
            if (filesize($log) >= $max) {
                fclose($fhandle);
                $safe = pathinfo($log, PATHINFO_FILENAME).'_'.date('Y-m-d_H-i-s');
                addCompress(dirname($log), $log, $safe, 'auto', true, true);
                $fhandle = fopen($log, 'ab');
            }
            if ($fhandle !== false) {
                fwrite($fhandle, _WARN.': '.$msg.PHP_EOL._IP.': '.$ip.PHP_EOL._USER.': '.$luser.PHP_EOL._URL.': '.$url.$ref.PHP_EOL._BROWSER.': '.$agent.PHP_EOL._DATE.': '.$dtime.PHP_EOL.'----'.PHP_EOL);
                fclose($fhandle);
            }
        }
    }
    setExit(_WARN.'!', 1);
}
