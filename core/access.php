<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE')) die('Illegal file access');

global $path;

$conf = require $path.'config/global.php';
$conf = array_replace_recursive($conf, require $path.'config/security.php');
if (defined('ADMIN_FILE')) $conf['theme'] = 'admin';
require_once $path.'lang/'.$conf['language'].'.php';

# Denial of Authenticate
function setUnauthorized(): never {
    header('WWW-Authenticate: Basic realm="SLAED"');
    header('HTTP/1.0 401 Unauthorized');
    setExit(_LOGININCOR);
}

# Get IP
function getIp(): string {
    if (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
        $ip = getenv('REMOTE_ADDR');
    } elseif (!empty($_SERVER['REMOTE_ADDR']) && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
        $ip = $_SERVER['REMOTE_ADDR'];
    } else {
        $ip = '0.0.0.0';
    }
    return $ip;
}

# Format exit info
function setExit(string $msg, string $typ = ''): never {
    global $conf, $path;
    $file = $path.'core/classes/template.php';
    if (!class_exists('Template') && is_file($file)) require_once $file;
    if (!class_exists('Template')) die('Template engine unavailable');
    if (!defined('BASE_DIR')) define('BASE_DIR', realpath($path) ?: $path);
    $theme = $conf['theme'] ?? 'default';
    $tpl = new Template($theme);
    $text = $tpl->getHtmlFrag('alert', ['text' => $msg, 'is_warn' => true]);
    $jump = ($typ !== '') ? $tpl->getHtmlFrag('meta-refresh', ['secs' => '5', 'url' => ($conf['homeurl'] ?? '').'/index.php']).PHP_EOL : '';
    $meta = $tpl->getHtmlFrag('head-meta-base', ['author' => $conf['sitename'] ?? '', 'generator' => 'SLAED CMS '.($conf['version'] ?? '')]).PHP_EOL.$jump;
    $license = base64_decode((string)($conf['lic_h'] ?? '')).date('Y').base64_decode((string)($conf['lic_f'] ?? ''));
    $basedir = realpath($path) ?: $path;
    $themedir = 'templates/'.$theme;
    $linksrc = [];
    if (is_file($basedir.'/'.$themedir.'/favicon.png')) {
        $linksrc[] = $tpl->getHtmlFrag('head-link-icon', ['href' => $themedir.'/favicon.png']);
    } elseif (is_file($basedir.'/'.$themedir.'/favicon.ico')) {
        $linksrc[] = $tpl->getHtmlFrag('head-link-icon', ['href' => $themedir.'/favicon.ico']);
    }
    foreach (['theme.css', 'system.css', 'new.css', 'blocks.css'] as $asset) {
        if (is_file($basedir.'/'.$themedir.'/'.$asset)) {
            $linksrc[] = $tpl->getHtmlFrag('head-link-css', ['href' => $themedir.'/'.$asset]);
        }
    }
    $links = implode(PHP_EOL, $linksrc);
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
        'license' => $license,
        'home' => _HOME,
        'search' => _SEARCH,
        'feedback' => _FEEDBACK,
        'recommend' => _RECOMMEND,
        'favorites' => _S_FAVORITEN,
        'head_html' => '',
        'foot_html' => $license,
        'blocks_left' => '',
        'blocks_right' => '',
        'blocks_down' => '',
        'title' => _MESSAGE,
        'message_html' => $text,
    ]));
}

if ($conf['security']['admin_ip'] != '') {
    $cidr = static function(string $cidr): string|false {
        $cidr = trim($cidr);
        if ($cidr === '') return false;
        if (!str_contains($cidr, '/')) {
            if (!filter_var($cidr, FILTER_VALIDATE_IP)) return false;
            $pack = inet_pton($cidr);
            if ($pack === false) return false;
            $ip = inet_ntop($pack);
            if ($ip === false) return false;
            $pre = (str_contains($ip, ':')) ? 128 : 32;
            return $ip.'/'.$pre;
        }
        [$ip, $pre] = explode('/', $cidr, 2);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        if (!preg_match('#^\d+$#', $pre)) return false;
        $pre = (int)$pre;
        $pack = inet_pton($ip);
        if ($pack === false) return false;
        $ip = inet_ntop($pack);
        if ($ip === false) return false;
        $max = (str_contains($ip, ':')) ? 128 : 32;
        if ($pre < 0 || $pre > $max) return false;
        return $ip.'/'.$pre;
    };
    $match = static function(string $ip, string $rule) use ($cidr): bool {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        $rcidr = $cidr($rule);
        if ($rcidr === false) return false;
        [$rip, $pre] = explode('/', $rcidr, 2);
        $pre = (int)$pre;
        $ipp = inet_pton($ip);
        $rpp = inet_pton($rip);
        if ($ipp === false || $rpp === false) return false;
        if (strlen($ipp) !== strlen($rpp)) return false;
        $bytes = intdiv($pre, 8);
        $rest = $pre % 8;
        if ($bytes > 0 && substr($ipp, 0, $bytes) !== substr($rpp, 0, $bytes)) return false;
        if ($rest === 0) return true;
        $mbyte = (0xFF << (8 - $rest)) & 0xFF;
        $ibyte = ord($ipp[$bytes]);
        $rbyte = ord($rpp[$bytes]);
        return ($ibyte & $mbyte) === ($rbyte & $mbyte);
    };

    $admin_ip = explode(',', $conf['security']['admin_ip']);
    $temp_ip = getIp();
    foreach ($admin_ip as $val) {
        $ruleIp = trim($val);
        if ($ruleIp !== '' && $match($temp_ip, $ruleIp)) {
            $ip_check = true;
            break;
        } else {
            $ip_check = false;
        }
    }
    if (!$ip_check) setExit(_AUTH_ERROR_IP);
}

if ($conf['security']['login'] != '' && $conf['security']['password'] != '') {
    if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) setUnauthorized();
    if (!password_verify($_SERVER['PHP_AUTH_USER'], $conf['security']['login']) || !password_verify($_SERVER['PHP_AUTH_PW'], $conf['security']['password'])) setUnauthorized();
} else {
    setExit(_AUTH_ERROR);
}

unset($conf, $path);
