<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

define('MODULE_FILE', true);
$sgtime = microtime(true);
define('BASE_DIR', str_replace('\\', '/', __DIR__));
require_once BASE_DIR.'/core/system.php';

if (!defined('ADMIN_FILE') && $conf['close'] && !isAdmin()) setExit(_CLOSE_TEXT);
if (isset($_GET['error'])) setExit(sprintf(_ERROR404, $_GET['error'], $conf['homeurl']), 1);

$go = getVar('req', 'go', 'var');
$name = getVar('req', 'name', 'var');
$op = getVar('req', 'op', 'var');

if (empty($go)) {
    setCache($conf['cache_b']);
    if ($conf['alang']) {
        $userip = user_geo_ip(getip(), 2);
        if ($userip != '?' && !is_bot() && empty(getCookies('language'))) {
            if ($userip == 'United Kingdom' || $userip == 'United States of America' || $userip == 'Canada' || $userip == 'Australia') {
                setRedirect('index.php?newlang=en');
            } elseif ($userip == 'France') {
                setRedirect('index.php?newlang=fr');
            } elseif ($userip == 'Germany') {
                setRedirect('index.php?newlang=de');
            } elseif ($userip == 'Poland') {
                setRedirect('index.php?newlang=pl');
            } elseif ($userip == 'Russian Federation') {
                setRedirect('index.php?newlang=ru');
            } elseif ($userip == 'Ukraine') {
                setRedirect('index.php?newlang=uk');
            }
        }
    }
    $file = getVar('req', 'file', 'var') ?: 'index';
    $theme = getTheme();
    if ($name) {
        $conf['name'] = $name;
        $conf['style'] = 'sl_mod_'.strtolower($name);
        $module = 1;
        $mconf = $conf['modules'][$name] ?? [];
        $active = $mconf['active'] ?? 0;
        $view = $mconf['view'] ?? 0;
        $path = BASE_DIR.'/modules/'.$name.'/'.$file.'.php';
        if (intval($active) || is_moder($name)) {
            if ($view == 0 && file_exists($path)) {
                getLang($name);
                require_once $path;
            } elseif (($view == 1 && (is_user() && isModGroup($name)) || is_moder($name)) && file_exists($path)) {
                getLang($name);
                require_once $path;
            } elseif ($view == 1 && !is_moder($name)) {
                if (!is_user()) $info = _MODULEUSERS.' ';
                $group = $mconf['group'] ?? 0;
                $gname = '';
                if ($group) {
                    $grp = $db->getSqlRow($db->getSqlQuery('SELECT name FROM '.PREFIX_DB.'_groups WHERE id = :id', ['id' => $group]));
                    $gname = $grp['name'] ?? '';
                }
                if ($gname) $info .= _ADDITIONALYGRP.': '.$gname;
                setHead();
                echo setTemplateBasic('title', ['{%title%}' => _ACCESSDENIED]).setTemplateWarning('warn', ['time' => '15', 'url' => '?name=account&op=newuser', 'id' => 'info', 'text' => $info]);
                setFoot();
                exit;
            } elseif ($view == 2 && is_moder($name) && file_exists($path)) {
                getLang($name);
                require_once $path;
            } elseif ($view == 2 && !is_moder($name)) {
                setHead();
                echo setTemplateBasic('title', ['{%title%}' => _ACCESSDENIED]).setTemplateWarning('warn', ['time' => '5', 'url' => '', 'id' => 'info', 'text' => _MODULESADMINS]);
                setFoot();
                exit;
            } else {
                setRedirect('index.php');
            }
        } else {
            setRedirect('index.php');
        }
    } else {
        $home = 1;
        if (empty($conf['module'])) {
            $conf['name'] = '';
            setHead();
            setFoot();
            exit;
        } else {
            $hmodul = explode(',', $conf['module']);
            $hi = mt_rand(0, count($hmodul) - 1);
            $name = $hmodul[$hi];
            $conf['name'] = $name;
            $path = BASE_DIR.'/modules/'.$name.'/'.$file.'.php';
            if (file_exists($path)) {
                getLang($name);
                require_once $path;
                exit;
            } else {
                setHead();
                echo setTemplateWarning('warn', ['text' => _HOMEPROBLEMUSER, 'url' => '', 'time' => 0, 'id' => 'warn']);
                setFoot();
                exit;
            }
        }
    }
} elseif (is_numeric($go)) {
    $fdsize = isset($_FILES['file']['size']) ? $_FILES['file']['size'] : '';
    if (!intval($fdsize) && !stristr(getenv('HTTP_REFERER'), getHost())) die('Illegal file access');
    if ($go == 1) {
        setCache('0');
        switch($op) {
            case 'rating': rating(); break;
            case 'show_files': show_files(); break;
            case 'user_sainfo': user_sainfo(); break;
            case 'user_sinfo': user_sinfo(); break;
            case 'get_user': get_user(); break;
            case 'editcom': editcom(); break;
            case 'savecom': addComment(); break;
            case 'closecom': closecom(); break;
            case 'editpost': updatePost(); break;
            case 'prmess': getPmView(); break;
            case 'prmesssend': addPmMsg(); break;
            case 'prmesssave': setPmSaved(); break;
            case 'prmessdel': deletePmMsg(); break;
            case 'favoradd': addFavor(); break;
            case 'favorliste': getFavorList(); break;
            case 'favordel': deleteFavor(); break;
            case 'avoting_view': getVoting(); break;
            case 'avoting_save': avoting_save(); break;
        }
    } elseif ($go == 2) {
        getLang('shop');
        setCache('0');
        require_once CONFIG_DIR.'/shop.php';
        switch($op) {
            default: show_kasse(); break;
            case 'add_kasse': add_kasse(); break;
            case 'del_kasse': del_kasse(); break;
        }
    } elseif ($go == 3) {
        setCache('0');
        switch($op) {
            case 'filereport': filereport(); break;
            case 'backup': addBackupDb(); break;
            case 'sitemap': doSitemap(); break;
            case 'newsletter': updateNewsletter(); break;
        }
    } elseif ($go == 4) {
        setCache('0');
        require_once CONFIG_DIR.'/uploads.php';
        $mod = (getVar('get', 'mod', 'var')) ? strtolower(getVar('get', 'mod', 'var')) : '';
        if ($mod) {
            $userid = (getVar('get', 'userid', 'num')) ? getVar('get', 'userid', 'num') : '0';
            switch($go) {
                default:
                $con = explode('|', $conf['uploads'][$mod]);
                upload(2, 'uploads/'.$mod, $con[0], $con[2], $mod, $con[3], $con[4], $userid);
                break;
            }
        } else {
            die('Illegal file access');
        }
    } elseif ($go == 5) {
        if (isAdmin(true)) {
            define('ADMIN_FILE', true);
            getLang('admin');
            setCache('0');
            require_once BASE_DIR.'/core/admin.php';
            switch($op) {
                case 'ajax_cat': ajax_cat(); break;
                case 'cat_order': cat_order(); break;
                case 'ajax_block': ajax_block(); break;
                case 'blocks_order': blocks_order(); break;
                case 'fav_aliste': fav_aliste(); break;
                case 'fav_adel': fav_adel(); break;
                case 'ajax_privat': ajax_privat(); break;
                case 'ajax_privat_del': ajax_privat_del(); break;
                case 'ashow_files': ashow_files(); break;
                case 'adm_info': echo getAdminInfo(); break;
            }
        } else {
            die('Illegal file access');
        }
    }
    $cvar = explode(',', $conf['variables']);
    if (!$cvar[0] && is_moder()) echo getVariables();
} elseif ($go == 'rss') {
    setCache('0');
    echo getRssChannel();
} elseif ($go == 'search') {
    setCache('1');
    echo getOpenSearch();
} elseif ($go == 'xsl') {
    setCache('1');
    echo getOpenXsl();
} elseif ($go == 'css') {
    setCache('1');
    setCss();
} elseif ($go == 'script') {
    setCache('1');
    setScript();
}
