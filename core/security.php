<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

#define('BASE_DIR', dirname(__DIR__));

# Global config file include
require_once BASE_DIR.'/config/config_global.php';

# Users config file include
require_once BASE_DIR.'/config/users.php';

# SEO config file include
require_once BASE_DIR.'/config/config_seo.php';

# Murder variables
unset($name, $file, $admin, $user, $admintrue, $godtrue, $usertrue, $aid, $uname, $guest, $userinfo, $stop);

# Set the default timezone to use. Available since PHP 5.1
date_default_timezone_set($conf['gtime']);

# Language on
get_lang();

# SQL class file include
require_once CONFIG_DIR.'/db.php';
require_once BASE_DIR.'/core/classes/pdo.php';
$db = new sql_db($confdb['host'], $confdb['uname'], $confdb['pass'], $confdb['name'], $confdb['charset']);
if ($confdb['sync']) $db->sql_query("SET LOCAL time_zone = '".date('P')."'");
if ($confdb['mode']) $db->sql_query("SET SESSION sql_mode=''");
$prefix = $confdb['prefix'];

# Security config file include
require_once CONFIG_DIR.'/config_security.php';
$admin_file = $confs['afile'];

# Report PHP errors
$confs['error'] = 2;
$emode = isset($confs['error']) ? (int)$confs['error'] : 0;
if ($emode === 2) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} elseif ($emode === 1) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL & ~E_NOTICE);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

# Output buffering on
if (ob_get_level() === 0) ob_start();

# Session start
if (session_status() === PHP_SESSION_NONE) session_start();

# Flood Protection
if (!defined('ADMIN_FILE') && $confs['flood']) {
    $ctime = time();
    $ftime = $ctime - intval($confs['flood_t']);
    $flood = (isset($_SESSION['flood']) && $_SESSION['flood'] > $ftime) ? 1 : 0;
    if ($confs['flood'] == 3 && $flood) doWarnReport('Flood attack');
    if ($confs['flood'] == 2 && isset($_GET) && $flood) doWarnReport('Flood in GET - '.print_r($_GET, true));
    if (isset($_POST) && $flood) doWarnReport('Flood in POST - '.print_r($_POST, true));
    unset($_SESSION['flood']);
    $_SESSION['flood'] = $ctime;
}

# Format admin variable
$admin = (($tmp = base64_decode($_SESSION[$conf['admin_c']] ?? '', true)) && $tmp !== '') ? explode(':', $tmp) : [];

# Format user variable
$user  = (($tmp = base64_decode($_COOKIE[$conf['user_c'].'-account'] ?? '', true)) && $tmp !== '') ? explode(':', $tmp) : [];

# Analyzer of variables
function getVariablesInfo(): string {
    $cont = [];
    foreach (['POST', 'GET', 'COOKIE', 'FILES', 'SESSION'] as $var) {
        $arr = $GLOBALS['_'.$var] ?? [];
        if ($arr) $cont[] = $var.': '.print_r($arr, true);
    }
    return implode(PHP_EOL, $cont);
}

# Add log report entry
function log_report(): bool {
    global $user, $confu, $confs;

    $ip = getIp();
    $agent = getAgent();
    $url = text_filter((string)getenv('REQUEST_URI'));
    $refer = get_referer();
    $ref = $refer ? PHP_EOL._REFERER.': '.$refer : '';

    // Safe user name (avoid undefined key and substr(null,...))
    if (is_array($user) && isset($user[1]) && $user[1] !== null) {
        $luser = substr((string)$user[1], 0, 25);
    } else {
        $anon = $confu['anonym'] ?? 'Guest';
        $luser = substr((string)$anon, 0, 25);
    }

    $path = 'config/logs/log.txt';
    $maxsize = $confs['log_size'] ?? 1048576; // fallback 1 MB

    $fhandle = fopen($path, 'ab');
    if ($fhandle === false) {
        return false;
    }

    // Ensure up-to-date file size information
    clearstatcache(true, $path);
    if (filesize($path) > $maxsize) {
        // rotate
        fclose($fhandle);
        zip_compress($path, 'config/logs/log_'.date('Y-m-d_H-i').'.txt');
        unlink($path);

        // recreate log file
        $fhandle = fopen($path, 'ab');
        if ($fhandle === false) {
            return false;
        }
    }

    $entry = getVariablesInfo()._IP.': '.$ip.PHP_EOL.
        _USER.': '.$luser.PHP_EOL.
        _URL.': '.$url.$ref.PHP_EOL.
        _BROWSER.': '.$agent.PHP_EOL.
        _DATE.': '.date(_TIMESTRING).PHP_EOL.
        '----'.PHP_EOL;

    fwrite($fhandle, $entry);
    fclose($fhandle);

    return true;
}

# Log report
/*function log_report() {
    global $user, $confu, $confs;
    $ip = getIp();
    $agent = getAgent();
    $url = text_filter(getenv('REQUEST_URI'));
    $refer = get_referer();
    $ref = ($refer) ? PHP_EOL._REFERER.': '.$refer : '';
    $luser = ($user) ? substr($user[1], 0, 25) : substr($confu['anonym'], 0, 25);
    $path = 'config/logs/log.txt';
    if ($fhandle = @fopen($path, 'ab')) {
        if (filesize($path) > $confs['log_size']) {
            zip_compress($path, 'config/logs/log_'.date('Y-m-d_H-i').'.txt');
            @unlink($path);
        }
        fwrite($fhandle, getVariablesInfo()._IP.': '.$ip.PHP_EOL._USER.': '.$luser.PHP_EOL._URL.': '.$url.$ref.PHP_EOL._BROWSER.': '.$agent.PHP_EOL._DATE.': '.date(_TIMESTRING).PHP_EOL.'----'.PHP_EOL);
        fclose($fhandle);
    }
}*/

if ($confs['log']) log_report();

# Security cookies blocker or ip blocker and member blocker
$bcookie = getCookies($confs['blocker_cookie']);
if ($bcookie == 'block') {
    setExit(_BANN_INFO);
} else {
    $bip = explode('||', $confs['blocker_ip']);
    if ($bip) {
        foreach ($bip as $val) {
            if ($val != '') {
                $binfo = explode('|', $val);
                if (time() <= $binfo[3]) {
                    $ipt = getIp();
                    $ipb = $binfo[0];
                    $uagt = md5(getAgent());
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
                        setCookies($confs['blocker_cookie'], $binfo[3], 'block');
                        $btext = _BANN_INFO.'<br>'._BANN_TERM.': '.rest_time($binfo[3]).'<br>'._BANN_REAS.': '.$binfo[4];
                        setExit($btext);
                    }
                }
            }
        }
    }
    $bus = explode('||', $confs['blocker_user']);
    if ($bus && $user) {
        foreach ($bus as $val) {
            if ($val != '') {
                $tus = substr($user[1], 0, 25);
                $uinfo = explode('|', $val);
                if (time() <= $uinfo[1]) {
                    if ($tus == $uinfo[0]) {
                        setCookies($confs['blocker_cookie'], $uinfo[1], 'block');
                        $utext = _BANN_INFO.'<br>'._BANN_TERM.': '.rest_time($uinfo[1]).'<br>'._BANN_REAS.': '.$uinfo[2];
                        setExit($utext);
                    }
                }
            }
        }
    }
}

$confs['error_log'] = 1;
# Error reporting log
if ($confs['error_log']) {
    # HTTP error reporting log
    if (isset($_GET['error'])) {
        $error = intval($_GET['error']);
        unset($error_log, $http);
        static $http = array (
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
            504 => 'HTTP/1.1 504 Gateway Time-out'
        );
        $error_log = $http[$error];
        if ($error_log) {
            $ip = getIp();
            $agent = getAgent();
            $url = text_filter(getenv('REQUEST_URI'));
            $refer = get_referer();
            $ref = ($refer) ? PHP_EOL._REFERER.': '.$refer : '';
            $path = 'config/logs/error_site.txt';
            if ($fhandle = @fopen($path, 'ab')) {
                if (filesize($path) > $confs['log_size']) {
                    zip_compress($path, 'config/logs/error_site_'.date('Y-m-d_H-i').'.txt');
                    @unlink($path);
                }
                fwrite($fhandle, getVariablesInfo()._ERROR.': '.$error_log.PHP_EOL._IP.': '.$ip.PHP_EOL._URL.': '.$url.$ref.PHP_EOL._BROWSER.': '.$agent.PHP_EOL._DATE.': '.date(_TIMESTRING).PHP_EOL.'----'.PHP_EOL);
                fclose($fhandle);
            }
        }
        unset($error_log, $http);
    }
    # PHP error reporting log
    function error_reporting_log($error_num, $error_var, $error_file, $error_line) {
        global $confs;
        $error_write = false;
        switch ($error_num) {
            case 1:
            $error_desc = 'ERROR';
            $error_write = true;
            break;
            case 2:
            $error_desc = 'WARNING';
            $error_write = true;
            break;
            case 4:
            $error_desc = 'PARSE';
            $error_write = true;
            break;
            case 8:
            $error_desc = 'NOTICE';
            $error_write = false;
            break;
            case 2048:
            $error_desc = 'STRICT';
            $error_write = true;
            break;
            case 8192:
            $error_desc = 'DEPRECATED';
            $error_write = true;
            break;
        }
        if ($error_write) {
            $ip = getIp();
            $agent = getAgent();
            $url = text_filter(getenv('REQUEST_URI'));
            $refer = get_referer();
            $ref = ($refer) ? PHP_EOL._REFERER.': '.$refer : '';
            $path = 'config/logs/error.txt';
            if ($fhandle = @fopen($path, 'ab')) {
                if (filesize($path) > $confs['log_size']) {
                    zip_compress($path, 'config/logs/error_'.date('Y-m-d_H-i').'.txt');
                    @unlink($path);
                }
                fwrite($fhandle, getVariablesInfo()._ERROR.': '.$error_desc.': '.$error_var.' Line: '.$error_line.' in file '.$error_file.PHP_EOL._IP.': '.$ip.PHP_EOL._URL.': '.$url.$ref.PHP_EOL._BROWSER.': '.$agent.PHP_EOL._DATE.': '.date(_TIMESTRING).PHP_EOL.'----'.PHP_EOL);
                fclose($fhandle);
            }
        }
    }
    set_error_handler('error_reporting_log');
    # SQL error reporting log
    function error_sql_log($errno, $error, $log) {
        global $confs;
        $ip = getIp();
        $agent = getAgent();
        $url = text_filter(getenv('REQUEST_URI'));
        $refer = get_referer();
        $ref = ($refer) ? PHP_EOL._REFERER.': '.$refer : '';
        $log = text_filter(trim($log));
        $path = 'config/logs/error_sql.txt';
        if ($fhandle = @fopen($path, 'ab')) {
            if (filesize($path) > $confs['log_size']) {
                zip_compress($path, 'config/logs/error_sql_'.date('Y-m-d_H-i').'.txt');
                @unlink($path);
            }
            fwrite($fhandle, getVariablesInfo()._ERROR.': '.$errno.' - '.$error.PHP_EOL.'SQL: '.$log.PHP_EOL._IP.': '.$ip.PHP_EOL._URL.': '.$url.$ref.PHP_EOL._BROWSER.': '.$agent.PHP_EOL._DATE.': '.date(_TIMESTRING).PHP_EOL.'----'.PHP_EOL);
            fclose($fhandle);
        }
    }
}

# Checking URL, GET, POST, COOKIE, FILES variables for safety
if (!is_admin_god()) {
    # Checking URL length
    $ruri = mb_strlen($_SERVER['REQUEST_URI'], 'utf-8');
    if ($ruri > 2048) doWarnReport('Spam in URL - '.$ruri.' > 2048');
    # Checking GET variable for safety
    if (isset($_GET)) {
        function checkGet($name, $val) {
            global $prefix, $confs;
            $links = '#^(http\:\/\/|https\:\/\/|ftp\:\/\/|php\:\/\/|\/\/)#i';
            $script = '#<.*?(script|body|object|iframe|applet|meta|form|style|img).*?>#i';
            $char = '#\([^>]*\"?[^)]*\)#';
            $quote = '#\"|\'|\.\.\/|\*#';
            $string = '#ALTER|DROP|INSERT|OUTFILE|SELECT|TRUNCATE|UNION|'.$prefix.'_admins|'.$prefix.'_users|admins_show|admins_add|admins_save|admins_del#i';
            $decode = base64_decode($val);
            $slash = preg_replace('#\/\*.*?\*\/#', '', $val);
            if ($confs['url_get']) if (preg_match($links, $val)) doWarnReport('URL in GET - '.$name.' = '. $val);
            if (preg_match($script, urldecode($val)) || preg_match($char, $val)) doWarnReport('HTML in GET - '.$name.' = '. $val);
            if (preg_match($quote, $val)) doHackReport('Hack in GET - '.$name.' = '. $val);
            if (preg_match($string, $val)) doHackReport('XSS in GET - '.$name.' = '. $val);
            if (preg_match($string, $decode)) doHackReport('XSS base64 in GET - '.$name.' = '. $val);
            if (preg_match($string, $slash)) doHackReport('XSS slash in GET - '.$name.' = '. $val);
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
    # Checking POST variable for safety
    if (isset($_POST)) {
        function checkPost($name, $val) {
            global $prefix, $confs, $conf, $admin;
            #$val = is_array($val) ? fields_save($val) : $val;
            $editor = is_array($admin) ? intval(substr($admin[3], 0, 1)) : 0;
            $links = '#^(http\:\/\/|https\:\/\/|ftp\:\/\/|php\:\/\/|\/\/)#i';
            $script = '#<.*?(script|body|object|iframe|applet|meta|form).*?>#i';
            $string = '#'.$prefix.'_admins|'.$prefix.'_users#i';
            $decode = base64_decode($val);
            $slash = preg_replace('#\/\*.*?\*\/#', '', $val);
            if ($confs['ref_post'] && isset($_FILES['file']['size'])) if (!intval($_FILES['file']['size']) && !stristr(getenv('HTTP_REFERER'), get_host())) doWarnReport('POST from referer - '.$name.' = '. $val);
            if ($confs['url_post']) if (preg_match($links, $val)) doWarnReport('URL in POST - '.$name.' = '. $val);
            if (((defined('ADMIN_FILE') && $editor != 1) || (!defined('ADMIN_FILE') && $conf['redaktor'] != 1)) && preg_match($script, urldecode($val))) doWarnReport('HTML in POST - '.$name.' = '. $val);
            if (preg_match($string, $val)) doHackReport('XSS in POST - '.$name.' = '. $val);
            if (preg_match($string, $decode)) doHackReport('XSS base64 in POST - '.$name.' = '. $val);
            if (preg_match($string, $slash)) doHackReport('XSS slash in POST - '.$name.' = '. $val);
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
    # Checking COOKIE variable for safety
    if (isset($_COOKIE)) {
        function checkCookie($name, $val) {
            global $prefix;
            $links = '#^(http\:\/\/|https\:\/\/|ftp\:\/\/|php\:\/\/|\/\/)#i';
            $script = '#<.*?(script|body|object|iframe|applet|meta|form|style|img).*?>#i';
            $string = '#ALTER|DROP|INSERT|OUTFILE|SELECT|TRUNCATE|UNION|'.$prefix.'_admins|'.$prefix.'_users|admins_show|admins_add|admins_save|admins_del#i';
            $decode = base64_decode($val);
            $slash = preg_replace('#\/\*.*?\*\/#', '', $val);
            if (preg_match($links, $val)) doHackReport('URL in COOKIE - '.$name.' = '. $val);
            if (preg_match($script, $val)) doHackReport('HTML in COOKIE - '.$name.' = '. $val);
            if (preg_match($string, $val)) doHackReport('XSS in COOKIE - '.$name.' = '. $val);
            if (preg_match($string, $decode)) doHackReport('XSS base64 in COOKIE - '.$name.' = '. $val);
            if (preg_match($string, $slash)) doHackReport('XSS slash in COOKIE - '.$name.' = '. $val);
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
    # Checking FILES variable for safety
    if (isset($_FILES)) {
        function checkFiles($name, $val) {
            $type = '#php.*|js|htm|html|phtml|cgi|pl|perl|asp#i';
            if (isset($_FILES['userfile'])) {
                $val = strtolower(substr(strrchr($_FILES['userfile']['name'], '.'), 1));
                if (preg_match($type, $val)) doHackReport('Hack in FILES - '.$name.' = '. $val);
            } elseif (isset($_FILES['file'])) {
                if (is_array($_FILES['file'])) {
                    $files = count($_FILES['file']['name']);
                    for ($i = 0; $i < $files; $i++) {
                        $val = strtolower(substr(strrchr($_FILES['file']['name'][$i], '.'), 1));
                        if (preg_match($type, $val)) doHackReport('Hack in FILES - '.$name.' = '. $val);
                    }
                } else {
                    $val = strtolower(substr(strrchr($_FILES['file']['name'], '.'), 1));
                    if (preg_match($type, $val)) doHackReport('Hack in FILES - '.$name.' = '. $val);
                }
            } else {
                $val = strtolower(substr(strrchr($_FILES[$name]['name'], '.'), 1));
                if (preg_match($type, $val)) doHackReport('Hack in FILES - '.$name.' = '. $val);
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

# Reset all variables
reset($_GET);
reset($_POST);
reset($_COOKIE);
reset($_FILES);

# Check super admin
function is_admin_god() {
    global $prefix, $db, $admin;
    static $godtrue;
    if (!empty($admin)) {
        if (!isset($godtrue)) {
            $id = intval(substr($admin[0], 0, 11));
            $name = htmlspecialchars(substr($admin[1], 0, 25));
            $pwd = htmlspecialchars(substr($admin[2], 0, 40));
            $ip = getIp();
            if ($id && $name && $pwd && $ip) {
                list($aname, $apwd, $aip) = $db->sql_fetchrow($db->sql_query("SELECT name, pwd, ip FROM ".$prefix."_admins WHERE id = '".$id."' AND super = '1'"));
                if ($aname == $name && $aname != '' && $apwd == $pwd && $apwd != '' && $aip == $ip && $aip != '') {
                    $godtrue = 1;
                    return $godtrue;
                }
            }
            $godtrue = 0;
            return $godtrue;
        } else {
            return $godtrue;
        }
    } else {
        return 0;
    }
}

# Format exit and displaying information
function setExit($msg, $typ = '') {
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
    .'<body style="margin:0; height:100vh; display:flex; justify-content:center; align-items:center; flex-direction:column;">'.PHP_EOL
    .'<img src="'.$conf['homeurl'].'/'.img_find('logos/'.$conf['site_logo']).'" alt="'.$conf['sitename'].'" title="'.$conf['sitename'].'" style="max-width:90%; height:auto;">'.PHP_EOL
    .'<div style="margin-top:40px; font:18px Arial, Tahoma, Verdana, sans-serif; color:#1a4674; font-weight:bold; text-align:center;">'.$msg.'</div>'.PHP_EOL
    .'</body>'.PHP_EOL
    .'</html>';
    die($cont);
}

# Cookie set
function setCookies($name, $time, $value) {
    global $conf;
    $info = is_array($value) ? base64_encode($value[0].':'.$value[1].':'.$value[2].':'.$value[3].':'.$value[4].':'.$value[5]) : $value;
    $url = parse_url($conf['homeurl']);
    $sec = ($url['scheme'] == 'http') ? false : true;
    $options = array('expires' => $time, 'path' => '/', 'domain' => $url['host'], 'secure' => $sec, 'httponly' => true, 'samesite' => 'Lax');
    setcookie($conf['user_c'].'-'.$name, $info, $options);
}

# Delete cookie set
function setCookiesDelete($name) {
    global $conf;
    setcookie($conf['user_c'].'-'.$name, '', time() - 3600, '/', parse_url($conf['homeurl'], PHP_URL_HOST));
}

# Get cookie
function getCookies($name) {
    global $conf;
    $cookie = isset($_COOKIE[$conf['user_c'].'-'.$name]) ? analyze($_COOKIE[$conf['user_c'].'-'.$name]) : '';
    return $cookie;
}

# Get the client's real IP address
function getIp() {
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
function getAgent() {
    if (getenv('HTTP_USER_AGENT') && strcasecmp(getenv('HTTP_USER_AGENT'), 'unknown')) {
        return text_filter(getenv('HTTP_USER_AGENT'));
    } elseif (!empty($_SERVER['HTTP_USER_AGENT']) && strcasecmp($_SERVER['HTTP_USER_AGENT'], 'unknown')) {
        return text_filter($_SERVER['HTTP_USER_AGENT']);
    }
    return 'unknown';
}

# Get host
function get_host() {
    $host = (getenv('HTTP_HOST')) ? getenv('HTTP_HOST') : getenv('SERVER_NAME');
    return $host;
}

# Get referer
function get_referer() {
    $referer = text_filter(getenv('HTTP_REFERER'));
    if (!empty($referer) && $referer != '' && !preg_match('#^unknown#i', $referer) && !preg_match('#^bookmark#i', $referer) && !stristr($referer, get_host())) {
        $refer = $referer;
    } else {
        $refer = '';
    }
    return $refer;
}

# Load language files and return the active language
function get_lang(string $module = ''): string {
    global $currentlang, $conf;

    // per-request small caches (variable names follow SLAED: no CamelCase, 4-8 chars)
    static $mload = false;    // main language file loaded flag
    static $lmods = [];       // module load cache (module|lang => bool)

    $mlang   = (string)($conf['language'] ?? 'en');       // main/default language
    $mult    = ((int)($conf['multilingual'] ?? 0) === 1); // multilingual flag (4-8 chars)

    // sanitize incoming requested language
    $rlang = $_REQUEST['newlang'] ?? '';
    $rlang = $rlang !== '' ? isVar($rlang) : '';
    $clang = getCookies('language');

    // determine active language
    if ($mult) {
        if ($rlang && is_readable('language/lang-'.$rlang.'.php')) {
            $currentlang = $rlang;
        } elseif ($clang && is_readable('language/lang-'.$clang.'.php')) {
            $currentlang = $clang;
        } else {
            $currentlang = $mlang;
        }
    } else {
        $currentlang = $mlang;
    }

    // set cookie only when needed
    if (!$clang || $clang !== $currentlang) {
        setCookies('language', time() + (int)($conf['user_c_t'] ?? 0), $currentlang);
    }

    // include main language file once per request
    if (!$mload) {
        $file = 'language/lang-'.$currentlang.'.php';
        if (is_readable($file)) {
            require_once $file;
        } else {
            $fallback = 'language/lang-'.$mlang.'.php';
            if (is_readable($fallback)) {
                require_once $fallback;
            }
        }
        $mload = true;
    }

    // module-specific language loading, cached per-request
    if ($module !== '') {
        $key = $module . '|' . $currentlang;
        if (!array_key_exists($key, $lmods)) {
            $candidates = $module === 'admin'
                ? ['admin/language/lang-'.$currentlang.'.php', 'admin/language/lang-'.$mlang.'.php']
                : ['modules/'.$module.'/language/lang-'.$currentlang.'.php', 'modules/'.$module.'/language/lang-'.$mlang.'.php'];

            $loaded = false;
            foreach ($candidates as $p) {
                if (is_readable($p)) {
                    require_once $p;
                    $loaded = true;
                    break;
                }
            }
            $lmods[$key] = $loaded; // cache result (even false) to avoid repeated fs checks
        }
    }

    return $currentlang;
}


# OLD DELETE
# Format language
/* function get_lang($module='') {
    global $currentlang, $conf;
    $rlang = isset($_REQUEST['newlang']) ? isVar($_REQUEST['newlang']) : '';
    $clang = getCookies('language');
    if ($rlang && $conf['multilingual'] == '1') {
        if (file_exists('language/lang-'.$rlang.'.php')) {
            setCookies('language', time() + intval($conf['user_c_t']), $rlang);
            include_once('language/lang-'.$rlang.'.php');
            $currentlang = $rlang;
        } else {
            setCookies('language', time() + intval($conf['user_c_t']), $conf['language']);
            include_once('language/lang-'.$conf['language'].'.php');
            $currentlang = $conf['language'];
        }
    } elseif ($clang && $conf['multilingual'] == '1') {
        if (file_exists('language/lang-'.$clang.'.php')) {
            include_once('language/lang-'.$clang.'.php');
            $currentlang = $clang;
        } else {
            include_once('language/lang-'.$conf['language'].'.php');
            $currentlang = $conf['language'];
        }
    } else {
        if (!$clang) {
            setCookies('language', time() + intval($conf['user_c_t']), $conf['language']);
        }
        include_once('language/lang-'.$conf['language'].'.php');
        $currentlang = $conf['language'];
    }
    if ($module != '') {
        if (file_exists('modules/'.$module.'/language/lang-'.$currentlang.'.php')) {
            if ($module == 'admin') {
                include_once('admin/language/lang-'.$currentlang.'.php');
            } else {
                include_once('modules/'.$module.'/language/lang-'.$currentlang.'.php');
            }
        } else {
            if ($module == 'admin') {
                include_once('admin/language/lang-'.$currentlang.'.php');
            } else {
                include_once('modules/'.$module.'/language/lang-'.$conf['language'].'.php');
            }
        }
    }
} */

# Zip check
function zip_check() {
    if (function_exists('gzopen')) {
        return 2;
    } elseif (function_exists('bzopen')) {
        return 1;
    } else {
        return 0;
    }
}

# Zip compress
function zip_compress($src, $dst) {
    $check = zip_check();
    if ($check) {
        $fp = @fopen($src, 'rb');
        if ($fp === false) {
            return false;
        }

        $filesize = @filesize($src);
        if ($filesize === false || $filesize === 0) {
            fclose($fp);
            return false;
        }

        $data = fread($fp, $filesize);
        fclose($fp);

        if ($check == 2) {
            $zp = @gzopen($dst.'.gz', 'wb5');
            if ($zp === false) {
                return false;
            }
            gzwrite($zp, $data);
            gzclose($zp);
        } else {
            $zp = @bzopen($dst.'.bz2', 'w');
            if ($zp === false) {
                return false;
            }
            bzwrite($zp, $data);
            bzclose($zp);
        }
        return true;
    }
    return false;
}

# DELETE
# Get url meta contents
function getUrlMeta() {
    global $prefix, $db;
    $url = urlencode(str_replace('index.php?', '', substr(getenv('REQUEST_URI'), 1)));
    #list($link, $time, $mtime, $title, $desc, $keys, $img, $ctitle, $cdesc, $cimg) = $db->sql_fetchrow($db->sql_query("SELECT sl_link, sl_time, sl_mtime, sl_title, sl_desc, sl_keys, sl_img, sl_ctitle, sl_cdesc, sl_cimg FROM ".$prefix."_seo WHERE sl_url = '".$url."'"));
    #$a = array($link, $time, $mtime, $title, $desc, $keys, $img, $ctitle, $cdesc, $cimg);
	$a = 0;
    return is_array($a) ? $a : false;
}

# Clean access to POST, GET or Request parameters
/**
 * Sauberer Zugriff auf POST, GET oder Request-Parameter
 *
 * @param string $var     'post', 'get' oder 'req'
 * @param string $key     Name des Parameters (Bracket-Notation: field[0], field[])
 * @param string $type    Typ für Filterung: num, let, word, name, title, text, field, url, var, bool
 * @param mixed  $default Standardwert, falls Parameter fehlt
 * @return mixed Gefilterter Wert oder Default / false
 */
function getVar(string $var, string $key, string $type = '', mixed $default = ''): mixed {
    global $conf;

    // Bracket-Notation parsen: field[0] oder field[]
    $array_index = null;
    $is_array_all = false;

    if (preg_match('/^([^\[]+)\[(\d*)\]$/', $key, $matches)) {
        $key = $matches[1];  // field
        if ($matches[2] === '') {
            $is_array_all = true;  // field[] → ganzes Array
        } else {
            $array_index = (int)$matches[2];  // field[0] → Index
        }
    }

    // Filter-Definitionen (einmalig für Einzelwerte und Arrays)
    $filters = [
        'num'   => fn($v) => num_filter($v),
        'let'   => fn($v) => is_string($v) ? mb_substr(trim($v), 0, 1, 'utf-8') : $v,
        'word'  => fn($v) => is_string($v) ? text_filter(trim($v)) : $v,
        'name'  => fn($v) => is_string($v) ? text_filter(mb_substr(trim($v), 0, 25, 'utf-8')) : $v,
        'title' => fn($v) => is_string($v) ? save_text(trim($v), 1) : $v,
        'text'  => fn($v) => is_string($v) ? save_text(trim($v)) : $v,
        'field' => fn($v) => is_string($v) ? fields_save(trim($v)) : $v,
        'url'   => fn($v) => is_string($v) ? url_filter(trim($v)) : $v,
        'var'   => fn($v) => is_string($v) ? isVar($v) : $v,
        'bool'  => fn($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN),
    ];

    // Ganzes Array mit Element-Filterung: field[] + type
    if ($is_array_all) {
        $p = $_POST[$key] ?? [];
        $g = $_GET[$key] ?? [];

        $value = match(strtolower($var)) {
            'post' => $p,
            'get'  => $g,
            'req'  => (!empty($p)) ? $p : $g,
            default => [],
        };

        if (!is_array($value)) {
            return $default ?: [];
        }

        // Element-Filterung anwenden
        if ($type) {
            $filtered = [];
            foreach ($value as $item) {
                if (isset($filters[$type])) {
                    $item = $filters[$type]($item);
                }
                if ($item !== false && $item !== null && $item !== '') {
                    $filtered[] = $item;
                }
            }
            return $filtered;
        }

        // Ohne Filterung: Rohes Array
        return $value;
    }

    // Array-Index-Zugriff: field[0]
    if ($array_index !== null) {
        $p = $_POST[$key][$array_index] ?? '';
        $g = $_GET[$key][$array_index] ?? '';
    } else {
        // Normaler Einzelwert
        $p = filter_input(INPUT_POST, $key, FILTER_DEFAULT) ?? '';
        $g = filter_input(INPUT_GET, $key, FILTER_DEFAULT) ?? '';
    }

    // Rewrite-URL Parsing, wenn $g leer
    if (!empty($conf['rewrite']) && !$g) {
        $arg = getUrlMeta();
        if ($arg) {
            parse_str(str_replace('&amp;', '&', $arg[0]), $parsed);
            $g = $parsed[$key] ?? '';
        }
    }

    // Quelle auswählen: POST / GET / REQ
    $value = match(strtolower($var)) {
        'post' => $p,
        'get'  => $g,
        // Strenger Check: nur wenn nicht null/leer, sonst GET
        'req'  => ($p !== null && $p !== '') ? $p : $g,
        default => null,
    };

    // Falls Wert leer, Default nutzen
    $value = ($value !== null && $value !== '') ? $value : $default;

    // Typfilter anwenden
    if ($type && isset($filters[$type])) {
        $value = $filters[$type]($value);
    } else {
        // Wenn kein Typ, trim für Strings
        if (is_string($value)) $value = trim($value);
    }

    // Leere Werte → false
    return ($value !== '' && $value !== null) ? $value : false;
}



# DELETE Get variables
/*
function getVar($var, $val, $typ = '', $obj = '') {
    global $conf;
    
    $p = filter_input(INPUT_POST, $val, FILTER_DEFAULT) ?? '';
    $g = filter_input(INPUT_GET, $val, FILTER_DEFAULT) ?? '';
   
    
    if ($conf['rewrite'] && !$g) {
        
        #$url = urldecode(str_replace('index.php?', '', substr(getenv('REQUEST_URI'), 1)));
        $arg = getUrlMeta();
        if ($arg) {
            $g = false;
            $query = explode('&', str_replace('&amp;', '&', $arg['0']));
            foreach($query as $q) {
                list($key, $value) = explode('=', $q);
                if ($val == $key) {
                    $g = $value;
                    break;
                }
            }
        }
    }
    
    if ($var == 'post') {
        if ($typ == 'num') {
            $out = ($p) ? num_filter($p) : (($obj) ? num_filter($obj) : '');
        } elseif ($typ == 'let') {
            $out = ($p) ? mb_substr($p, 0, 1, 'utf-8') : (($obj) ? mb_substr($obj, 0, 1, 'utf-8') : '');
        } elseif ($typ == 'word') {
            $out = ($p) ? text_filter($p) : (($obj) ? text_filter($obj) : '');
        } elseif ($typ == 'name') {
            $out = ($p) ? text_filter(substr($p, 0, 25)) : (($obj) ? text_filter(substr($obj, 0, 25)) : '');
        } elseif ($typ == 'title') {
            $out = ($p) ? save_text($p, 1) : (($obj) ? save_text($obj, 1) : '');
        } elseif ($typ == 'text') {
            $out = ($p) ? save_text($p) : (($obj) ? save_text($obj) : '');
        } elseif ($typ == 'field') {
            $out = ($p) ? fields_save($p) : (($obj) ? fields_save($obj) : '');
        } elseif ($typ == 'url') {
            $out = ($p) ? url_filter($p) : (($obj) ? $obj : '');
        } elseif ($typ == 'var') {
            $out = ($p) ? isVar($p) : (($obj) ? $obj : '');
        } else {
            $out = ($p) ? $p : (($obj) ? $obj : '');
        }
    } elseif ($var == 'get') {
        if ($typ == 'num') {
            $out = ($g) ? num_filter($g) : (($obj) ? num_filter($obj) : '');
        } elseif ($typ == 'let') {
            $out = ($g) ? mb_substr($g, 0, 1, 'utf-8') : (($obj) ? mb_substr($obj, 0, 1, 'utf-8') : '');
        } elseif ($typ == 'word') {
            $out = ($g) ? text_filter($g) : (($obj) ? text_filter($obj) : '');
        } elseif ($typ == 'name') {
            $out = ($g) ? text_filter(substr($g, 0, 25)) : (($obj) ? text_filter(substr($obj, 0, 25)) : '');
        } elseif ($typ == 'title') {
            $out = ($g) ? save_text($g, 1) : (($obj) ? save_text($obj, 1) : '');
        } elseif ($typ == 'text') {
            $out = ($g) ? save_text($g) : (($obj) ? save_text($obj) : '');
        } elseif ($typ == 'field') {
            $out = ($g) ? fields_save($g) : (($obj) ? fields_save($obj) : '');
        } elseif ($typ == 'url') {
            $out = ($g) ? url_filter($g) : (($obj) ? $obj : '');
        } elseif ($typ == 'var') {
            $out = ($g) ? isVar($g) : (($obj) ? $obj : '');
        } else {
            $out = ($g) ? $g : (($obj) ? $obj : '');
        }
    } elseif ($var == 'req') {
        if ($typ == 'num') {
            $out = ($p) ? num_filter($p) : (($g) ? num_filter($g) : (($obj) ? num_filter($obj) : ''));
        } elseif ($typ == 'let') {
            $out = ($p) ? mb_substr($p, 0, 1, 'utf-8') : (($g) ? mb_substr($g, 0, 1, 'utf-8') : (($obj) ? mb_substr($obj, 0, 1, 'utf-8') : ''));
        } elseif ($typ == 'word') {
            $out = ($p) ? text_filter($p) : (($g) ? text_filter($g) : (($obj) ? text_filter($obj) : ''));
        } elseif ($typ == 'name') {
            $out = ($p) ? text_filter(substr($p, 0, 25)) : (($g) ? text_filter(substr($g, 0, 25)) : (($obj) ? text_filter(substr($obj, 0, 25)) : ''));
        } elseif ($typ == 'title') {
            $out = ($p) ? save_text($p, 1) : (($g) ? save_text($g, 1) : (($obj) ? save_text($obj, 1) : ''));
        } elseif ($typ == 'text') {
            $out = ($p) ? save_text($p) : (($g) ? save_text($g) : (($obj) ? save_text($obj) : ''));
        } elseif ($typ == 'field') {
            $out = ($p) ? fields_save($p) : (($g) ? fields_save($g) : (($obj) ? fields_save($obj) : ''));
        } elseif ($typ == 'url') {
            $out = ($p) ? url_filter($p) : (($g) ? url_filter($g) : (($obj) ? $obj : ''));
        } elseif ($typ == 'var') {
            $out = ($p) ? isVar($p) : (($g) ? isVar($g) : (($obj) ? $obj : ''));
        } else {
            $out = ($p) ? $p : (($g) ? $g : (($obj) ? $obj : ''));
        }
    }
    return ($out) ? $out : false;
}
*/

# Strict variable analyzer
function isVar($var) {
    if (is_array($var)) {
        $out = (preg_grep('#[^a-zA-Z0-9_\-]#', $var)) ? '' : $var;
    } else {
        $out = (preg_match('#[^a-zA-Z0-9_\-]#', $var)) ? '' : $var;
    }
    return $out;
}

# Strict variable analyzer
# Duble from isVar()!
# DELETE
function analyze($var) {
    $var = (preg_match('#[^a-zA-Z0-9_\-]#', $var)) ? '' : $var;
    return $var;
}

# URL filter
function url_filter($url) {
    $url = strtolower($url);
    $url = (preg_match('#http\:\/\/|https\:\/\/#i', $url)) ? $url : 'http://'.$url;
    $url = ($url == 'http://') ? '' : text_filter($url);
    return $url;
}

# Number filter
function num_filter($var) {
    $con = preg_replace('#[^0-9]#', '', $var);
    return intval($con);
}

# Variables filter
function var_filter($var) {
    $con = preg_replace('#[^\pL0-9\s%&/|.:;&_+\-=]#siu', '', $var);
    return $con;
}

# HTML and word filter
function text_filter($message, $type='') {
    global $conf;
    if (!is_admin()) while (preg_match('#\[(usehtml|/usehtml)\]|\[(usephp|/usephp)\]#si', $message)) $message = preg_replace('#\[(usehtml|/usehtml)\]|\[(usephp|/usephp)\]#si', '', $message);
    $message = is_array($message) ? fields_save($message) : $message;
    if (intval($type) == 2) {
        $message = htmlspecialchars(trim($message), ENT_QUOTES);
    } else {
        $message = strip_tags(urldecode($message ?? ''));
        $message = htmlspecialchars(trim($message), ENT_QUOTES);
    }
    if (!is_admin() && $conf['censor'] && intval($type != 1)) {
        $censor_l = explode(',', $conf['censor_l']);
        foreach ($censor_l as $val) $message = preg_replace('#'.$val.'#i', $conf['censor_r'], $message);
    }
    return $message;
}

# Length center filter
function cutstrc($linkstrip, $strip) {
    if (strlen($linkstrip) > $strip) $linkstrip = substr($linkstrip, 0, $strip - 19).'…'.substr($linkstrip, -16);
    return $linkstrip;
}

# Format ed2k links
function ed2k_link($m) {
    $href = 'url='.$m[2];
    $fname = rawurldecode($m[3]);
    $fname = str_replace(array('&#038;', '&amp;'), '&', $fname);
    $size = files_size($m[4]);
    $cont = ' eMule/eDonkey: ['.$href.']'.cutstrc($fname, 50).'[/url] - '._SIZE.': '.$size;
    return $cont;
}

# Make clickable url
function url_clickable($text) {
    if (!preg_match("#\[php\](.*)\[/php\]|\[code\](.*)\[/code\]#si", $text)) {
        $ret = preg_replace_callback("#([\n ])(?<=[^\w\"'])(ed2k://\|file\|([^\\/\|:<>\*\?\"]+?)\|(\d+?)\|([a-f0-9]{32})\|(.*?)/?)(?![\"'])(?=([,\.]*?[\s<\[])|[,\.]*?$)#i", "ed2k_link", " ".$text);
        $ret = preg_replace("#([\n ])(?<=[^\w\"'])(ed2k://\|server\|([\d\.]+?)\|(\d+?)\|/?)#i", "ed2k Server: [url=\\2]\\3[/url] - Port: \\4", $ret);
        $ret = preg_replace("#([\n ])(?<=[^\w\"'])(ed2k://\|friend\|([^\\/\|:<>\*\?\"]+?)\|([\d\.]+?)\|(\d+?)\|/?)#i", "Friend: [url=\\2]\\3[/url]", $ret);
        $ret = preg_replace("#([\n ])([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1[url=\\2]\\2[/url]", $ret);
        $ret = preg_replace("#([\n ])((www|ftp)\.[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", "\\1[url=http://\\2]\\2[/url]", $ret);
        $ret = preg_replace("#([\n ])([a-z0-9&\-_.]+?)@([\w\-]+\.([\w\-\.]+\.)*[\w]+)#i", "\\1[mail=\\2@\\3]\\2@\\3[/mail]", $ret);
        $ret = substr($ret, 1);
    } else {
        if (preg_match('#(.*)\[php\](.*)\[/php\](.*)#si', $text, $matches)) {
            $ret = url_clickable($matches[1]).'[php]'.$matches[2].'[/php]'.url_clickable($matches[3]);
        } elseif (preg_match('#(.*)\[code(.*)\](.*)\[/code\](.*)#si', $text, $matches)) {
            $ret = url_clickable($matches[1]).'[code'.$matches[2].']'.$matches[3].'[/code]'.url_clickable($matches[4]);
        }
    }
    return $ret;
}

# Save text
function save_text($text, $id='') {
    global $admin, $conf;
    if ($text) {
        $editor = is_array($admin) ? intval(substr($admin[3], 0, 1)) : 0;
        if ((defined('ADMIN_FILE') && $editor == 1) || (!defined('ADMIN_FILE') && $conf['redaktor'] == 1)) {
            $text = ($conf['clickable'] && $id != 1) ? url_clickable($text) : $text;
            $out = nl2br(str_replace(array('$', '\\'), array('&#036;', '&#092;'), stripslashes(text_filter($text, 2))), false);
        } else {
            $out = str_replace(array('"', '$', '\'', '\\'), array('&#034;', '&#036;', '&#039;', '&#092;'), stripslashes($text));
        }
        return $out;
    }
}

# Fields save
function fields_save($field) {
    if (isArray($field)) {
        $fields = stripslashes(text_filter(implode('|', $field), 2));
        return $fields;
    }
}

# Display Time filter
function display_time($sec) {
    $min = floor($sec / 60);
    $hours = floor($min / 60);
    $seconds = $sec % 60;
    $minutes = $min % 60;
    $cont = ($hours == 0) ? (($min == 0) ? $seconds.' '._SEC.'.' : $min.' '._MIN.'. '.$seconds.' '._SEC.'.') : $hours.' '._HOUR.'. '.$minutes.' '._MIN.'. '.$seconds.' '._SEC.'.';
    return $cont;
}

# Rest time
function rest_time($time) {
    $end = date(_DATESTRING, $time);
    $expire = $time - time();
    $days = round($expire / 86400, 3).' '._DAYS;
    $date = (time() < $time) ? '<span title="'.display_time($expire).'" class="sl_green sl_note">'.$days.' - '.$end.'</span>' : '<span class="sl_red">'.$end.' - '._END.'</span>';
    return $date;
}

# Mail send
function mail_send($email, $smail, $subject, $message, $id='', $pr='') {
    global $conf;
    $email = text_filter($email);
    $smail = text_filter($smail);
    $subject = '=?'._CHARSET.'?b?'.base64_encode(text_filter($subject)).'?=';
    $id = intval($id);
    $pr = (!$pr) ? '3' : intval($pr);
    $message = (!$id) ? $message : $message.'<br><br>'._IP.': '.getIp().'<br>'._BROWSER.': '.getAgent().'<br>'._HASH.': '.md5(getAgent());
    $mheader = "MIME-Version: 1.0\n"
    ."Content-Type: text/html; charset="._CHARSET."\n"
    ."Content-Transfer-Encoding: base64\n"
    ."From: \"=?"._CHARSET."?b?".base64_encode($conf['sitename'])."?=\" <".$smail.">\n"
    ."Reply-To: \"".$smail."\" <".$smail.">\n"
    ."Return-Path: <".$smail.">\n"
    ."X-Priority: ".$pr."\n"
    ."X-Mailer: SLAED CMS\n";
    mail($email, $subject, base64_encode($message), $mheader);
}

# Hack report
function doHackReport($msg) {
    global $user, $conf, $confu, $confs;
    $msg = text_filter(substr($msg, 0, 500));
    $url = text_filter(getenv('REQUEST_URI'));
    $refer = get_referer();
    $ref = ($refer) ? PHP_EOL._REFERER.': '.$refer : '';
    $ip = getIp();
    $agent = getAgent();
    $date_time = date(_TIMESTRING);
    $user = ($user) ? substr($user[1], 0, 25) : substr($confu['anonym'], 0, 25);
    if ($confs['block']) {
        $btime = time() + 86400;
        $cont = array('blocker_ip' => $confs['blocker_ip'].$ip.'|4|'.md5($agent).'|'.$btime.'|'._HACK.'||');
        doConfig('config/config_security.php', 'confs', $cont, $confs, '');
        setCookies($confs['blocker_cookie'], $btime, 'block');
    }
    if ($confs['mail']) {
        $subject = $conf['sitename'].' - '._SECURITY;
        $mmsg = $conf['sitename'].' - '._SECURITY.'<br><br>'._HACK.': '.$msg.'<br>'._IP.': '.$ip.'<br>'._USER.': '.$user.'<br>'._URL.': '.$url.$ref.'<br>'._BROWSER.': '.$agent.'<br>'._DATE.': '.$date_time;
        mail_send($conf['adminmail'], $conf['adminmail'], $subject, $mmsg, 0, 1);
    }
    if ($confs['write_h']) {
        $path = 'config/logs/hack.txt';
        if ($fhandle = @fopen($path, 'ab')) {
            if (filesize($path) > $confs['log_size']) {
                zip_compress($path, 'config/logs/hack_'.date('Y-m-d_H-i').'.txt');
                @unlink($path);
            }
            fwrite($fhandle, _HACK.': '.$msg.PHP_EOL._IP.': '.$ip.PHP_EOL._USER.': '.$user.PHP_EOL._URL.': '.$url.$ref.PHP_EOL._BROWSER.': '.$agent.PHP_EOL._DATE.': '.$date_time.PHP_EOL.'----'.PHP_EOL);
            fclose($fhandle);
        }
    }
    setExit(_HACK.'!', 1);
}

# Warn report
function doWarnReport($msg) {
    global $user, $conf, $confu, $confs;
    $msg = text_filter(substr($msg, 0, 500));
    $url = text_filter(getenv('REQUEST_URI'));
    $refer = get_referer();
    $ref = ($refer) ? PHP_EOL._REFERER.": ".$refer : "";
    $ip = getIp();
    $agent = getAgent();
    $date_time = date(_TIMESTRING);
    $user = ($user) ? substr($user[1], 0, 25) : substr($confu['anonym'], 0, 25);
    if ($confs['mail_w']) {
        $subject = $conf['sitename'].' - '._SECURITY;
        $mmsg = $conf['sitename'].' - '._SECURITY.'<br><br>'._WARN.': '.$msg.'<br>'._IP.': '.$ip.'<br>'._USER.': '.$user.'<br>'._URL.': '.$url.$ref.'<br>'._BROWSER.': '.$agent.'<br>'._DATE.': '.$date_time;
        mail_send($conf['adminmail'], $conf['adminmail'], $subject, $mmsg, 0, 1);
    }
    if ($confs['write_w']) {
        $path = 'config/logs/warn.txt';
        if ($fhandle = @fopen($path, 'ab')) {
            if (filesize($path) > $confs['log_size']) {
                zip_compress($path, 'config/logs/warn_'.date('Y-m-d_H-i').'.txt');
                @unlink($path);
            }
            fwrite($fhandle, _WARN.': '.$msg.PHP_EOL._IP.': '.$ip.PHP_EOL._USER.': '.$user.PHP_EOL._URL.': '.$url.$ref.PHP_EOL._BROWSER.': '.$agent.PHP_EOL._DATE.': '.$date_time.PHP_EOL.'----'.PHP_EOL);
            fclose($fhandle);
        }
    }
    setExit(_WARN.'!', 1);
}