<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Output buffering on — must precede all bootstrap operations
if (ob_get_level() === 0) ob_start();

# The cut before the database: up to five ASCII words go lowercased to $urlroute for public/index.php, a retired address 301s, any other path is 404
$urlroute = ['seg' => []];
if (PHP_SAPI !== 'cli' && !defined('API_FILE')) {
    [$rpath, $rquery] = array_pad(explode('?', (string)($_SERVER['REQUEST_URI'] ?? ''), 2), 2, '');
    $sname = basename(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')));
    $sbase = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    $rhome = [];
    $rrest = null;
    foreach (array_unique([$sbase, (string)preg_replace('#/public$#', '', $sbase)]) as $rone) {
        array_push($rhome, $rone.'/', $rone.'/'.$sname);
        if ($rrest === null && str_starts_with($rpath, $rone.'/')) $rrest = substr($rpath, strlen($rone) + 1);
    }
    $rcut = ($sname === 'index.php') ? (string)preg_replace('#^(?:index\.php/)+#', '', (string)$rrest) : '';
    $rdest = '';
    if ($sname !== 'index.php') {
        if (preg_match('#^(.*?/'.preg_quote($sname, '#').')/#', $rpath, $rhit) && in_array($rhit[1], $rhome, true)) $rdest = $rhit[1];
        if ($rdest === '' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && in_array($rpath, $rhome, true)) $rdest = $rpath;
    }
    if ($rdest !== '' && $rdest !== $rpath) {
        header('Location: '.$rdest.(($rquery !== '') ? '?'.$rquery : ''), true, 301);
        exit;
    }
    $rsegs = ($rcut === '' || $rcut === 'index.php') ? [] : explode('/', str_ends_with($rcut, '/') ? substr($rcut, 0, -1) : $rcut);
    if ($rsegs !== [] && count($rsegs) <= 5 && preg_grep('#^[A-Za-z0-9_-]+$#D', $rsegs, PREG_GREP_INVERT) === []) $urlroute = ['seg' => array_map('strtolower', $rsegs)];
    $rself = $sname === 'index.php' && $rrest !== null && $rsegs === [];
    if (!in_array($rpath, $rhome, true) && !$rself && $urlroute['seg'] === [] && !isset($_GET['error'])) $_GET['error'] = 404;
    unset($rpath, $rquery, $sname, $sbase, $rhome, $rrest, $rcut, $rdest, $rone, $rhit, $rsegs, $rself);
}

# Ensure configuration exists when this file is analyzed or included directly
if (!isset($conf) || !is_array($conf)) $conf = getConfig();

# Optional canonical host and scheme, proxy-aware; a page of index.php is left to setSeoMove(), so a wrong host and an old address cost one hop
$rpage = basename(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''))) === 'index.php' && getVar('req', 'go', 'var') === '';
if (!$rpage && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && (!empty($conf['forcessl']) || !empty($conf['forcehost']))) {
    $rhost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $chome = parse_url((string)($conf['homeurl'] ?? '')) ?: [];
    $chost = strtolower((string)($chome['host'] ?? '')).(isset($chome['port']) ? ':'.$chome['port'] : '');
    $badssl = !empty($conf['forcessl']) && !$secure;
    $badhost = !empty($conf['forcehost']) && $rhost !== '' && $chost !== '' && $rhost !== $chost;
    if ($badssl || $badhost) {
        $scheme = (!empty($conf['forcessl']) || $secure) ? 'https' : 'http';
        $target = !empty($conf['forcehost']) && $chost !== '' ? $chost : $rhost;
        header('Location: '.$scheme.'://'.$target.((string)($_SERVER['REQUEST_URI'] ?? '/')), true, 301);
        exit;
    }
}
unset($rpage);

# Set the default timezone
date_default_timezone_set($conf['gtime']);

# Language on — init locale, load main language file, set cookie
setLang();

# Database connection using unified config
require_once BASE_DIR.'/core/classes/pdo.php';
$db = new Database($conf['db']['host'], $conf['db']['uname'], $conf['db']['pass'], $conf['db']['name'], $conf['db']['collate']);
define('PREFIX_DB', $conf['db']['prefix']);

# Outgoing mail service, created here because the request scan below reports through it and runs long before the rest of the bootstrap
$mailer = new Mail($db, $conf);

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

# The API prints no error into its JSON, whatever the setting: the detail stays in the journal
if (defined('API_FILE')) ini_set('display_errors', '0');

# Session start; the API is session-less, every request of it answers for itself
if (session_status() === PHP_SESSION_NONE && !defined('API_FILE')) session_start();

# Flood protection; the report names the fields and never quotes them, as Logger stores the request data masked and a dump would restore the password
if (!defined('ADMIN_FILE') && !defined('API_FILE') && $conf['security']['flood']) {
    $ctime = time();
    $ftime = $ctime - intval($conf['security']['flood_t']);
    $flood = (isset($_SESSION[$conf['user_c'].'-flood']) && $_SESSION[$conf['user_c'].'-flood'] > $ftime) ? 1 : 0;
    if ($conf['security']['flood'] == 3 && $flood) addWarnReport('Flood attack');
    if ($conf['security']['flood'] == 2 && isset($_GET) && $flood) addWarnReport('Flood in GET - '.implode(', ', array_keys($_GET)));
    if (isset($_POST) && $flood) addWarnReport('Flood in POST - '.implode(', ', array_keys($_POST)));
    $_SESSION[$conf['user_c'].'-flood'] = $ctime;
}

# Format admin variable
$admin = (($tmp = base64_decode($_SESSION[$conf['admin_c']] ?? '', true)) && $tmp !== '') ? explode(':', $tmp) : [];

# The panel session ends after the idle limit on the panel and the site alike; a request of the panel is an action unless it marks itself a poll by X-SL-Poll
if ($admin && time() - intval($_SESSION[$conf['admin_c'].'-time'] ?? 0) >= getAdminIdle()) {
    unset($_SESSION[$conf['admin_c']], $_SESSION[$conf['admin_c'].'-time']);
    $admin = [];
} elseif ($admin && (defined('ADMIN_FILE') || getVar('req', 'go', 'num') == 5) && !isset($_SERVER['HTTP_X_SL_POLL'])) {
    $_SESSION[$conf['admin_c'].'-time'] = time();
}

# A poll only reads the session from here on, so it lets it go at once and never holds the next request of the same visitor behind its own work
if (isset($_SERVER['HTTP_X_SL_POLL']) && session_status() === PHP_SESSION_ACTIVE) session_write_close();

# Format user variable; the API knows no visitor by a cookie, its rights come from the client of the request
$user = (!defined('API_FILE') && ($tmp = base64_decode($_COOKIE[$conf['user_c'].'-account'] ?? '', true)) && $tmp !== '') ? explode(':', $tmp) : [];

# Request journal: every request is one Logger line of request.log, so posted secrets are masked and cookies and the session leave only their names
if ($conf['security']['log']) Logger::addRequest('info', 'Request', ['user' => $user[1] ?? '']);

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

# The branded error page for the whitelisted ?error= codes the web server routes to it; the API has no error page, so the parameter means nothing there
$ecode = (isset($_GET['error']) && !defined('API_FILE')) ? (int)$_GET['error'] : 0;
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

# Render a consistent response for uncaught errors: visible detail in debug, clean 500 in production, never inside non-HTML responses; the API answers its bare 500 in JSON
function setErrorOut(string $detail = ''): void {
    global $conf;
    static $busy = false;
    if ($busy) return;
    $busy = true;
    if (defined('API_FILE')) ApiRouter::setApiReply(['status' => 500, 'code' => 'internal']);
    if (in_array((string)($_REQUEST['go'] ?? ''), ['1', '3', '4', '5', 'captcha', 'file', 'rss', 'xsl'], true) || headers_sent()) {
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

# Fatal shutdown errors are logged when enabled and carry a 500 status; no heavy render in shutdown (state may be broken), the API gets its bare 500 in JSON
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
        if (defined('API_FILE')) ApiRouter::setApiReply(['status' => 500, 'code' => 'internal']);
        if (!headers_sent()) http_response_code(500);
    }
});

# Checking URL, GET, POST, COOKIE, FILES variables for safety; the API is left to ApiInput, its own filter, so a refused value is a 422 there and never a ban here
if (!isAdmin(true) && !defined('API_FILE')) {
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
            if ($conf['security']['ref_post'] && isset($_FILES['file']['size'])) {
                if (!intval($_FILES['file']['size']) && !stristr(getenv('HTTP_REFERER'), getHost())) addWarnReport('POST from referer - '.$name.' = '.$val);
            }
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

# Return the idle limit of the panel session in seconds: the lesser of the key idle and the life PHP gives the session file, never under a minute
function getAdminIdle(): int {
    global $conf;
    return max(60, min(intval($conf['security']['idle']), intval(ini_get('session.gc_maxlifetime'))));
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
        [$aname, $apwd, $aip, $asuper] = $db->getSqlRow($db->getSqlQuery(
            'SELECT name, password, ip, super FROM '.PREFIX_DB.'_admins WHERE id = :id',
            ['id' => $id]
        )) ?? ['', '', '', '0'];
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

# Format exit and displaying information with an optional page heading; the API answers a bare 403 in JSON, since the text may name what no client may read
function setExit(string $msg, string $typ = '', string $title = ''): never {
    global $conf, $tpl;
    if (defined('API_FILE')) ApiRouter::setApiReply(['status' => 403, 'code' => 'no_scope']);
    $file = defined('BASE_DIR') ? BASE_DIR.'/core/classes/template.php' : '';
    if (!class_exists('Template') && $file !== '' && is_file($file)) require_once $file;
    if (!class_exists('Template')) die('Template engine unavailable');
    $theme = getTheme();
    $tpl = new Template($theme);
    $text = $tpl->getHtmlFrag('alert', ['text' => $msg, 'is_warn' => true]);
    $base = rtrim((string)($conf['homeurl'] ?? ''), '/').'/';
    $meta = $tpl->getHtmlFrag('head-base', ['href' => $base])."\n"
        .$tpl->getHtmlFrag('head-meta', ['name' => 'robots', 'content' => 'noindex, follow'])."\n";
    $license = getLicenseHtml();
    $adlogo = basename((string)($conf['admin_logo'] ?? 'slaed_logo_256x73.png'));
    $adpath = getThemeFilePath('images/logos/'.$adlogo);
    if (!is_file(PUBLIC_DIR.'/'.$adpath)) $adpath = getThemeFilePath('images/logos/slaed_logo_256x73.png');
    $linksrc = [];
    $favicon = 'templates/'.$theme.'/images/favicon.svg';
    if (is_file(PUBLIC_DIR.'/'.$favicon)) {
        $linksrc[] = $tpl->getHtmlFrag('head-link', ['rel' => 'shortcut icon', 'href' => $base.$favicon, 'type' => 'image/svg+xml', 'title' => '']);
    }
    foreach (getThemeAssets($theme, 'css') as $asset) {
        $linksrc[] = $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => Template::getAssetUrl($asset), 'type' => '', 'title' => '']);
    }
    $links = implode("\n", $linksrc);
    die($tpl->getHtmlPage('message', [
        'lang' => substr(_LOCALE, 0, 2),
        'theme' => $theme,
        'mode' => getThemeMode(),
        'is_still' => isThemeFlag('still'),
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
        'adlogo' => $base.$adpath,
        'adalt' => $conf['sitename'] ?? 'SLAED CMS',
        'adtitle' => $conf['sitename'] ?? 'SLAED CMS',
        'logo' => $conf['site_logo'] ?? '',
        'title' => $title,
        'message_html' => $text,
    ]));
}

# Emit an HTTP error status, log it when enabled, and render the error page before stopping; a 404 of a page shows the page 404 of the layout while it has a section
function setError(int $code = 404): never {
    global $conf, $locale, $layout, $lkey, $name, $home;
    static $busy = false;
    $msg = [400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required', 403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 409 => 'Conflict',
        410 => 'Gone', 422 => 'Unprocessable Content', 429 => 'Too Many Requests', 500 => 'Internal Server Error', 502 => 'Bad Gateway', 503 => 'Service Unavailable',
        504 => 'Gateway Timeout'][$code] ?? 'Error';
    if (!headers_sent()) http_response_code($code);
    if (!empty($conf['security']['error_log'])) {
        Logger::addSite('error', ($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1').' '.$code.' '.$msg, [
            'http_code' => $code,
            'module' => isset($_GET['name']) ? substr((string)$_GET['name'], 0, 50) : null,
            'op' => isset($_GET['op']) ? substr((string)$_GET['op'], 0, 50) : null,
        ]);
    }
    if (defined('API_FILE')) ApiRouter::setApiReply(['status' => $code]);
    if (ob_get_level() > 0) ob_clean();
    $page = $code === 404 && !$busy && !defined('ADMIN_FILE') && class_exists('Layout', false) && getVar('req', 'go', 'var') === '' && empty($_SERVER['HTTP_HX_REQUEST']);
    if ($page) {
        $busy = true;
        $level = ob_get_level();
        try {
            $read = new Layout($conf['layout'] ?? [], getTheme(), is_user() > 0, is_moder() > 0, $conf['multilingual'] ? (string)$locale : '');
            $miss = (int)$read->getData()['notfound'];
            if ($miss > 0 && !$read->isPageEmpty($miss)) {
                [$layout, $lkey, $name, $home, $conf['name']] = [$read, $miss, 'page-'.$miss, 0, 'page-'.$miss];
                $title = (string)($read->getPage($miss)['title'] ?? '');
                setHead(($title !== '') ? ['title' => $title] : []);
                setFoot();
                exit;
            }
        } catch (Throwable $err) {
            while (ob_get_level() > $level) ob_end_clean();
            if (ob_get_level() > 0) ob_clean();
            Logger::addSite('error', 'The page 404 of the layout failed', ['error' => $err->getMessage()]);
        }
    }
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

# A purpose-scoped key from the master secret; an empty master, as config/security.php ships it, is generated and written there on first use
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

# The CSRF token of one scope, an HMAC over the session id; it lives as long as the session and dies with a new master secret
function getSiteToken(string $scope = 'ajax'): string {
    $sid  = session_id();
    $data = $sid !== '' ? $scope.'|'.$sid : $scope;
    return hash_hmac('sha256', $data, getSecret('csrf'));
}

# The CSRF token of the request: the X-CSRF-Token header global-func.js sends, then X-XSRF-Token, then the token parameter
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

# Check a token against its scope in constant time, read from the request when none is passed; a scoped check accepts the ajax token too
function checkSiteToken(string $tok = '', string $scope = 'ajax'): bool {
    if ($tok === '') $tok = getRequestToken();
    if ($tok === '') return false;
    if (hash_equals(getSiteToken($scope), $tok)) return true;
    if ($scope !== 'ajax' && hash_equals(getSiteToken('ajax'), $tok)) return true;
    return false;
}

# Authorize one state-changing request by POST and a body token of the form scope, as a query token leaks into history, logs and Referer and is prefetched
function checkAdminPost(string $scope): bool {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return false;
    if (!checkSiteToken(getVar('post', 'token', 'text'), $scope)) return false;
    setAdminLog(['modul' => $scope]);
    return true;
}

# Note one action of the panel: the passed token check opens the record once with its scope, operation and object, a redirect sets its result on an open one
function setAdminLog(array $data): void {
    global $admin, $alog;
    if (!isset($data['modul'])) {
        if ($alog) $alog = array_replace($alog, $data);
        return;
    }
    if ($alog || !defined('ADMIN_FILE')) return;
    $ids = getVar('req', 'id[]', 'num', []);
    $tid = 0;
    foreach (['id', 'bid', 'mid', 'cid', 'nid', 'uid', 'aid'] as $key) if ($tid = getVar('req', $key, 'num', 0)) break;
    $alog = [
        'event' => 'action',
        'aid' => intval($admin[0] ?? 0),
        'aname' => substr((string)($admin[1] ?? ''), 0, 25),
        'modul' => $data['modul'],
        'op' => getVar('req', 'op', 'var', ''),
        'target' => $tid ?: intval($ids[0] ?? 0),
        'num' => $tid ? 1 : count($ids),
        'result' => 'open',
    ];
    register_shutdown_function('addAdminLog');
}

# Write the noted action of the panel as one line of admin.log when the request ends, so a handler that stops without a redirect is recorded too
function addAdminLog(): bool {
    global $alog, $conf;
    if (!$alog || empty($conf['security']['log_a'])) return false;
    [$data, $alog] = [$alog, []];
    return Logger::addAdmin('info', $data['modul'].' '.$data['op'], $data);
}

# Return external HTTP referer URL, or empty string if internal/invalid/unknown
function getReferer(): string {
    $referer = filterText(getenv('HTTP_REFERER'));
    if (!empty($referer) && !preg_match('#^unknown#i', $referer) && !preg_match('#^bookmark#i', $referer) && !stristr($referer, getHost())) {
        return $referer;
    }
    return '';
}

# Pick the locale from the cookie and load its language file; it runs before the bootstrap is up, so it never takes a switch, which is setLangChoice()
function setLang(): void {
    global $locale, $conf;
    $mlang = $conf['language'] ?? 'en';
    $mult = ((int)($conf['multilingual'] ?? 0) === 1) && !defined('API_FILE');
    if ($mult) {
        $clang = getCookies('language');
        $locale = ($clang && is_readable(BASE_DIR.'/lang/'.$clang.'.php')) ? $clang : $mlang;
        if (!$clang || $clang !== $locale) {
            setCookies('language', time() + (int)($conf['user_c_t'] ?? 0), $locale);
        }
    } else {
        $locale = $mlang;
    }
    $file = BASE_DIR.'/lang/'.$locale.'.php';
    require_once is_readable($file) ? $file : BASE_DIR.'/lang/'.$mlang.'.php';
}

# Store the locale a switch posted under its own token scope; the caller redirects, so setLang() reads the cookie on the next request
function setLangChoice(): void {
    global $conf;
    if (((int)($conf['multilingual'] ?? 0)) !== 1 || !checkAdminPost('newlang')) return;
    $want = getVar('post', 'newlang', 'var', '');
    if ($want !== '' && is_readable(BASE_DIR.'/lang/'.$want.'.php')) setCookies('language', time() + (int)($conf['user_c_t'] ?? 0), $want);
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
            $list = [BASE_DIR.'/admin/lang/'.$locale.'.php', BASE_DIR.'/admin/lang/'.$mlang.'.php'];
        } elseif ($admin) {
            $list = [
                BASE_DIR.'/modules/'.$module.'/admin/lang/'.$locale.'.php',
                BASE_DIR.'/modules/'.$module.'/admin/lang/'.$mlang.'.php',
            ];
        } else {
            $list = [
                BASE_DIR.'/modules/'.$module.'/lang/'.$locale.'.php',
                BASE_DIR.'/modules/'.$module.'/lang/'.$mlang.'.php',
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

# Read one filtered value of $_GET or $_POST, so the keys public/index.php resolves from a path count; 'a[]' returns the array, an array under a plain key the default
function getVar(string $var, string $key, string $type = '', mixed $default = ''): mixed {
    $arridx = null;
    $allarr = false;
    $path = [];
    if (preg_match('/^([^\[\]]+)((?:\[[^\[\]]*\])+)$/', $key, $matches)) {
        preg_match_all('/\[([^\[\]]*)\]/', $matches[2], $parts);
        $key = $matches[1];
        $path = $parts[1];
        $last = array_pop($path);
        if ($last === '') $allarr = true;
        else $arridx = $last;
    }
    static $filters = null;
    $filters ??= [
        'num' => fn($v) => filterNum($v),
        'let' => fn($v) => is_string($v) ? mb_substr(trim($v), 0, 1, 'utf-8') : $v,
        'word' => fn($v) => is_string($v) ? filterWord(urldecode(trim($v))) : $v,
        'name' => fn($v) => is_string($v) ? filterCutText(filterText(trim($v)), 25) : $v,
        'title' => fn($v) => is_string($v) ? filterHtml(trim($v)) : $v,
        'text' => fn($v) => is_string($v) ? filterHtml(trim($v)) : $v,
        'url' => fn($v) => is_string($v) ? filterWebUrl(trim($v)) : $v,
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
            return ($ts !== false && date('Y-m-d', $ts) === $v) ? $v : '';
        },
        'raw' => fn($v) => $v,
    ];
    if ($allarr || $arridx !== null) {
        if ($arridx !== null) $path[] = $arridx;
        $p = $_POST[$key] ?? null;
        $g = $_GET[$key] ?? null;
        foreach ($path as $step) {
            $p = (is_array($p) && isset($p[$step])) ? $p[$step] : null;
            $g = (is_array($g) && isset($g[$step])) ? $g[$step] : null;
        }
    }
    if ($allarr) {
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
    if ($arridx === null) {
        $p = $_POST[$key] ?? null;
        $g = $_GET[$key] ?? null;
    }
    $p = is_array($p) ? '' : ($p ?? '');
    $g = is_array($g) ? '' : ($g ?? '');
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
    if (is_array($value)) return $default;
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

# Normalize a submitted web address to http(s) with scheme and host lowercased, path and query keep their case; the parser link policy is Parser::filterUrl()
function filterWebUrl(string $url): string {
    $url = preg_match('#https?://#i', $url) ? $url : 'http://'.$url;
    $url = preg_replace_callback('#^https?://[^/?\#]*#i', fn(array $v): string => strtolower($v[0]), $url) ?? '';
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

# Drop [usehtml], [usephp] and [block=id] unless the writer is trusted; the pass repeats, as one pass over [use[usehtml]html] rebuilds the tag it removed
function filterTrustedTags(string $text, bool $trust = false): string {
    if ($text === '' || $trust) return $text;
    while (preg_match('#\[/?use(?:html|php)\]|\[block=\d+\]#si', $text)) {
        $text = (string)preg_replace('#\[/?use(?:html|php)\]|\[block=\d+\]#si', '', $text);
    }
    return $text;
}

# Turn a typed text into the source a store keeps, never HTML-escaped, as the parser escapes at render; a browser sends every line end of a textarea as CRLF
function filterSourceText(string $text, bool $trust = false, bool $cens = true): string {
    global $conf;
    $text = filterTrustedTags(str_replace(["\r\n", "\r"], "\n", $text), $trust);
    if ($cens && !isAdmin() && !empty($conf['censor'])) {
        foreach (explode(',', (string)($conf['censor_l'] ?? '')) as $one) {
            $one = trim($one);
            if ($one !== '') $text = (string)preg_replace('#'.preg_quote($one, '#').'#i', (string)($conf['censor_r'] ?? ''), $text);
        }
    }
    return trim($text);
}

# Strip tags, HTML-encode, apply censor; $type=2 skips strip_tags (HTML allowed), $type=1 skips censor
function filterText(string $message, int $type = 0): string {
    global $conf;
    $message = filterTrustedTags($message, isAdmin(true));
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

# Cut an escaped value to the characters of its column, dropping an HTML entity the cut would split
function filterCutText(string $text, int $len): string {
    if (mb_strlen($text, 'utf-8') <= $len) return $text;
    return preg_replace('#&[\#a-z0-9]*$#i', '', mb_substr($text, 0, $len, 'utf-8')) ?? '';
}

# Normalize submitted content for the editor format; the trusted tags go before the branch, as the html editor stores its input almost verbatim
function filterHtml(string $text): string {
    if ($text) {
        $text = filterTrustedTags($text, isAdmin(true));
        $mode = getEditorMode();
        if ($mode !== 'html') {
            $out = str_replace(['$', '\\'], ['&#036;', '&#092;'], stripslashes(filterText($text, 2)));
            if ($mode === 'plain') $out = nl2br($out, false);
        } else {
            $out = str_replace(['"', '$', '\'', '\\'], ['&#034;', '&#036;', '&#039;', '&#092;'], stripslashes($text));
        }
        return $out;
    }
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

# Add a hack report with optional block and email
function addHackReport(string $msg): void {
    global $user, $conf, $tpl, $mailer;
    $msg = filterText(substr($msg, 0, 500));
    $url = filterText(getenv('REQUEST_URI'));
    $refer = getReferer();
    $ref = ($refer) ? "\n"._REFERER.': '.$refer : '';
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
            $mmsg = $conf['sitename'].' - '._SECURITY."\n"._HACK.': '.$msg."\n"._IP.': '.$ip."\n"._USER.': '.$luser
                ."\n"._URL.': '.$url.$ref."\n"._BROWSER.': '.$agent."\n"._DATE.': '.$dtime;
        }
        $mailer->addQueue(['kind' => 'security', 'email' => $conf['adminmail'], 'title' => $subject, 'body' => $mmsg, 'sender' => $conf['adminmail'], 'prio' => 1]);
    }
    if ($conf['security']['write_h']) {
        Logger::addHack('warning', $msg, ['user' => $luser, 'hash' => md5($agent), 'blocked' => (bool)$conf['security']['block']]);
    }
    setExit(_HACK.'!', 1);
}

# Add a warning report with optional email
function addWarnReport(string $msg): void {
    global $user, $conf, $tpl, $mailer;
    $msg = filterText(substr($msg, 0, 500));
    $url = filterText(getenv('REQUEST_URI'));
    $refer = getReferer();
    $ref = ($refer) ? "\n"._REFERER.': '.$refer : '';
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
            $mmsg = $conf['sitename'].' - '._SECURITY."\n"._WARN.': '.$msg."\n"._IP.': '.$ip."\n"._USER.': '.$luser
                ."\n"._URL.': '.$url.$ref."\n"._BROWSER.': '.$agent."\n"._DATE.': '.$dtime;
        }
        $mailer->addQueue(['kind' => 'security', 'email' => $conf['adminmail'], 'title' => $subject, 'body' => $mmsg, 'sender' => $conf['adminmail'], 'prio' => 1]);
    }
    if ($conf['security']['write_w']) {
        Logger::addWarn('warning', $msg, ['user' => $luser]);
    }
    setExit(_WARN.'!', 1);
}
