<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

define('MODULE_FILE', true);
$sgtime = microtime(true);
define('BASE_DIR', str_replace('\\', '/', __DIR__));
require_once BASE_DIR.'/core/system.php';

if (!defined('ADMIN_FILE') && $conf['close'] && !isAdmin()) { http_response_code(503); setExit(_CLOSE_TEXT); }

$go = getVar('req', 'go', 'var');
$name = getVar('req', 'name', 'var');
$op = getVar('req', 'op', 'var');

# The colour mode toggle of the site header posts here, the same route and the same token scope the panel uses
if (empty($go) && $op === 'mode') {
    if (checkAdminPost('mode')) setThemeMode(getVar('post', 'mode', 'var'));
    setRedirect('index.php', true);
}

# The language block posts here; the choice is stored and the POST turns back into a GET, so the redirected request is the one that renders in the new locale
if (empty($go) && $op === 'newlang') {
    setLangChoice();
    setRedirect('index.php', true);
}

if (empty($go)) {
    Cache::setHeaders(false);
    if ($conf['alang']) {
        $coun = Geoip::getCountry(getIp());
        if ($coun !== '' && !is_bot() && empty(getCookies('language'))) {
            $lang = ['GB' => 'en', 'US' => 'en', 'CA' => 'en', 'AU' => 'en', 'FR' => 'fr', 'DE' => 'de', 'PL' => 'pl', 'RU' => 'ru', 'UA' => 'uk'][$coun] ?? '';
            if ($lang !== '') {
                setCookies('language', time() + (int)($conf['user_c_t'] ?? 0), $lang);
                setRedirect('index.php');
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
        # The block positions the module allows; setFoot() reads these globals and skips the sides the module switched off
        $blocks = (string)($mconf['side'] ?? '');
        $blocks_c = (string)($mconf['top'] ?? '');
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
                http_response_code(403);
                setHead();
                echo $tpl->getHtmlFrag('title', ['title' => _ACCESSDENIED, 'is_level_one' => true])
                    .$tpl->getHtmlFrag('alert', ['text' => $info, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
                setFoot();
                exit;
            } elseif ($view == 2 && is_moder($name) && file_exists($path)) {
                getLang($name);
                require_once $path;
            } elseif ($view == 2 && !is_moder($name)) {
                http_response_code(403);
                setHead();
                echo $tpl->getHtmlFrag('title', ['title' => _ACCESSDENIED, 'is_level_one' => true])
                    .$tpl->getHtmlFrag('alert', ['text' => _MODULESADMINS, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
                setFoot();
                exit;
            } else {
                setError(404);
            }
        } else {
            setError(404);
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
    # Reads that guard themselves: each answers nothing to a visitor who may not see it, so a token would only add one to an address
    $public = ($go == 1 && in_array($op, ['getUserSessionInfo', 'getUserSessionRows', 'getPrivateMessageView'], true))
        || (($go == 1 || $go == 5) && $op === 'getUserSessionAdminInfo');
    if ($go != 3 && !$public) {
        $fdsize = intval($_FILES['file']['size'] ?? 0);
        $tok = getVar('req', 'token', 'raw', '')
            ?: ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!($go == 4 && $fdsize) && !checkSiteToken($tok)) die($tpl->getHtmlFrag('alert', ['text' => _TOKENMISS, 'is_warn' => true]));
    }
    if ($go == 1) {
        Cache::setHeaders(false);
        if (in_array($op, ['addComment', 'updateCommentStatus', 'deleteComment', 'addPrivateMessage',
            'setPrivateMessageRead', 'updatePrivatBox'], true) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            die($tpl->getHtmlFrag('alert', ['text' => _ERROR, 'is_warn' => true]));
        }
        switch($op) {
            case 'getRatingView': getRatingView(); break;
            case 'getUserSessionAdminInfo': getUserSessionAdminInfo(); break;
            case 'getUserSessionInfo': getUserSessionInfo(); break;
            case 'getUserSessionRows': getUserSessionRows(); break;
            case 'getUserList': getUserList(); break;
            case 'updateComment': updateComment(); break;
            case 'addComment': addComment(); break;
            case 'updateCommentStatus': updateCommentStatus(); break;
            case 'deleteComment': deleteComment(); break;
            case 'getCommentPage': getCommentPage(); break;
            case 'getCommentBranch': getCommentBranch(); break;
            case 'updatePost': updatePost(); break;
            case 'getPrivateMessageView': echo getPrivateMessageView(); break;
            case 'setPrivateMessageRead': setPrivateMessageRead(); break;
            case 'addPrivateMessage': addPrivateMessage(); break;
            case 'updatePrivatBox': updatePrivatBox(); break;
            case 'addFavorite': addFavorite(); break;
            case 'getFavoriteList': getFavoriteList(); break;
            case 'deleteFavorite': deleteFavorite(); break;
            case 'getVotingView': echo getVotingView(); break;
            case 'updateVotingResult': updateVotingResult(); break;
        }
        if (in_array($op, ['updatePost', 'updateVotingResult'], true)) Cache::addEpoch();
    } elseif ($go == 2) {
        getLang('shop');
        Cache::setHeaders(false);
        switch($op) {
            default: getCartSummary(); break;
            case 'addCartItem': addCartItem(); break;
            case 'deleteCartItem': deleteCartItem(); break;
        }
    } elseif ($go == 3) {
        Cache::setHeaders(false);
        switch($op) {
            case 'scheduler':
            header('Content-Type: application/json; charset=UTF-8');
            $type = getVar('post', 'trigger', 'var', '');
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($type !== 'pseudo' && $type !== 'cron')) {
                echo json_encode(['status' => 'denied', 'message' => 'Access denied'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            $name = getVar('post', 'job', 'var');
            $stok = getVar('post', 'token', 'text');
            if (!checkSchedulerAccess($type, $stok)) {
                echo json_encode(['status' => 'denied', 'message' => 'Access denied'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            # Release the session lock so long-running jobs do not block parallel requests of the same visitor
            session_write_close();
            echo json_encode(addSchedulerRun($name ?: null, $type), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    } elseif ($go == 4) {
        Cache::setHeaders(false);
        $place = getVar('get', 'place', 'raw', '');
        if ($place) {
            switch($op) {
                case 'editorUpload':
                addEditorUpload();
                break;
                case 'editorFiles':
                getEditorFileJson();
                break;
                case 'editorDelete':
                setEditorFileRun('editorDelete');
                break;
                case 'editorArchive':
                setEditorFileRun('editorArchive');
                break;
                default:
                http_response_code(400);
                getEditorJson(['ok' => false, 'error' => _ERROR]);
            }
        } else {
            die('Illegal file access');
        }
    } elseif ($go == 5) {
        if (isAdmin(true)) {
            define('ADMIN_FILE', true);
            getLang('admin');
            Cache::setHeaders(false);
            require_once BASE_DIR.'/core/admin.php';
            $tpl = new Template('admin');
            switch($op) {
                case 'getAdminCategoryList': getAdminCategoryList(); break;
                case 'updateAdminCategoryOrder': updateAdminCategoryOrder(); break;
                case 'getAdminBlockList': getAdminBlockList(); break;
                case 'updateAdminBlockOrder': updateAdminBlockOrder(); break;
                case 'getAdminFavoriteList': getAdminFavoriteList(); break;
                case 'getAdminPrivateList': getAdminPrivateList(); break;
                case 'getUserSessionAdminInfo': getUserSessionAdminInfo(); break;
                case 'getAdminFileList': getAdminFileList(); break;
                case 'getAdminFileData': getAdminFileData(); break;
                case 'getAdminFilePreview': getAdminFilePreview(); break;
                case 'getAdminFileDownload': getAdminFileDownload(); break;
            }
        } else {
            die('Illegal file access');
        }
    }
    $cvar = explode(',', $conf['variables']);
    if (!$cvar[0] && is_moder()) echo getVariables();
} elseif ($go == 'rss') {
    Cache::setHeaders(false);
    echo getRssChannel();
} elseif ($go == 'search') {
    Cache::setHeaders(true, Cache::STATICDAYS);
    echo getOpenSearch();
} elseif ($go == 'xsl') {
    Cache::setHeaders(true, Cache::STATICDAYS, 'text/xsl');
    echo getOpenXsl();
} elseif ($go == 'asset') {
    $hash = getVar('req', 'file', 'var');
    $type = getVar('req', 'type', 'var');
    if (!in_array($type, ['css', 'js'], true)) die('Illegal file access');
    $afile = Cache::getPath('assets', $hash, $type);
    if ($afile === '' || !is_file($afile)) die('Illegal file access');
    Cache::setHeaders(true, 0, ($type === 'css') ? 'text/css' : 'text/javascript', 0, true);
    echo Cache::getBody($afile);
} elseif ($go == 'captcha') {
    Cache::setHeaders(false);
    getCaptchaChallenge(getVar('req', 'act', 'var') ?: 'default');
}
