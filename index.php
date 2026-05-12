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
        $coun = Geoip::getCountry(getIp());
        if ($coun !== '' && !is_bot() && empty(getCookies('language'))) {
            $lang = ['GB' => 'en', 'US' => 'en', 'CA' => 'en', 'AU' => 'en', 'FR' => 'fr', 'DE' => 'de', 'PL' => 'pl', 'RU' => 'ru', 'UA' => 'uk'][$coun] ?? '';
            if ($lang !== '') setRedirect('index.php?newlang='.$lang);
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
                $meta = '<meta http-equiv="refresh" content="15; url=index.php?name=account&op=newuser">';
                echo $tpl->getHtmlFrag('title', ['title' => _ACCESSDENIED])
                    .$tpl->getHtmlFrag('alert', ['text' => $info, 'meta' => $meta, 'type' => 'info', 'is_warn' => false]);
                setFoot();
                exit;
            } elseif ($view == 2 && is_moder($name) && file_exists($path)) {
                getLang($name);
                require_once $path;
            } elseif ($view == 2 && !is_moder($name)) {
                setHead();
                $meta = '<meta http-equiv="refresh" content="5; url=index.php">';
                echo $tpl->getHtmlFrag('title', ['title' => _ACCESSDENIED])
                    .$tpl->getHtmlFrag('alert', ['text' => _MODULESADMINS, 'meta' => $meta, 'type' => 'info', 'is_warn' => false]);
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
                echo $tpl->getHtmlFrag('alert', ['text' => _HOMEPROBLEMUSER, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
                setFoot();
                exit;
            }
        }
    }
} elseif (is_numeric($go)) {
    if ($go != 3) {
        $fdsize = intval($_FILES['file']['size'] ?? 0);
        $tok    = getVar('req', 'token', 'raw', '')
            ?: getVar('req', 'upload_token', 'raw', '')
            ?: ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!$fdsize && !checkSiteToken($tok)) die('Illegal file access');
    }
    if ($go == 1) {
        setCache('0');
        switch($op) {
            case 'getRatingView': getRatingView(); break;
            case 'getUserSessionAdminInfo': getUserSessionAdminInfo(); break;
            case 'getUserSessionInfo': getUserSessionInfo(); break;
            case 'getUserList': getUserList(); break;
            case 'updateComment': updateComment(); break;
            case 'addComment': addComment(); break;
            case 'updateCommentStatus': updateCommentStatus(); break;
            case 'updatePost': updatePost(); break;
            case 'getPrivateMessageView': getPrivateMessageView(); break;
            case 'addPrivateMessage': addPrivateMessage(); break;
            case 'setPrivateMessageSaved': setPrivateMessageSaved(); break;
            case 'deletePrivateMessage': deletePrivateMessage(); break;
            case 'addFavorite': addFavorite(); break;
            case 'getFavoriteList': getFavoriteList(); break;
            case 'deleteFavorite': deleteFavorite(); break;
            case 'getVotingView': getVotingView(); break;
            case 'updateVotingResult': updateVotingResult(); break;
        }
    } elseif ($go == 2) {
        getLang('shop');
        setCache('0');
        switch($op) {
            default: getCartSummary(); break;
            case 'addCartItem': addCartItem(); break;
            case 'deleteCartItem': deleteCartItem(); break;
        }
    } elseif ($go == 3) {
        setCache('0');
        switch($op) {
            case 'scheduler':
            $name = getVar('req', 'job', 'var');
            $type = getVar('req', 'trigger', 'var', 'manual');
            $stok = getVar('req', 'token', 'text');
            header('Content-Type: application/json; charset=UTF-8');
            if (!checkSchedulerAccess($type, $stok)) {
                echo json_encode(['status' => 'denied', 'message' => 'Access denied'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            echo json_encode(addSchedulerRun($name ?: null, $type), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); 
            exit;
        }
    } elseif ($go == 4) {
        setCache('0');
        $mod = (getVar('get', 'mod', 'var')) ? strtolower(getVar('get', 'mod', 'var')) : '';
        if ($mod) {
            $userid = (getVar('get', 'userid', 'num')) ? getVar('get', 'userid', 'num') : '0';
            switch($op) {
                case 'editorUpload':
                addEditorUpload();
                break;
                case 'editorFiles':
                getEditorFileJson();
                break;
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
            $tpl = new Template('admin');
            switch($op) {
                case 'getAdminCategoryList': getAdminCategoryList(); break;
                case 'updateAdminCategoryOrder': updateAdminCategoryOrder(); break;
                case 'getAdminBlockList': getAdminBlockList(); break;
                case 'updateAdminBlockOrder': updateAdminBlockOrder(); break;
                case 'getAdminFavoriteList': getAdminFavoriteList(); break;
                case 'getAdminPrivateList': getAdminPrivateList(); break;
                case 'deleteAdminPrivate': deleteAdminPrivate(); break;
                case 'getAdminUploadFiles': getAdminUploadFiles(); break;
                case 'getAdminInfo': echo getAdminInfo(); break;
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
