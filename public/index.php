<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The front controller in the document root: the project is the folder above it; a test or a tool may name both folders first
if (!defined('BASE_DIR')) define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__)));
if (!defined('PUBLIC_DIR')) define('PUBLIC_DIR', str_replace('\\', '/', __DIR__));
define('MODULE_FILE', true);
$sgtime = microtime(true);

# The gate comes first: while an installation lies or the code waits for its database steps the site, its API and the pseudo-cron answer 503 before the core
require_once BASE_DIR.'/core/gate.php';
if (($sgfile = getSiteGate()) !== '') require $sgfile;
checkSiteGate();

# The light path: no file lies under uploads/ in the document root, so such a request lands here and is answered before the core boots
require_once BASE_DIR.'/core/stream.php';
$upath = getRequestPath('uploads');
if ($upath !== null) setUploadStream($upath);

# The API: a path under api/ boots the light core without theme, template or session, and its router answers in JSON before a line of the site runs
if (getRequestPath('api') !== null) {
    define('API_FILE', true);
    require_once BASE_DIR.'/core/system.php';
    ApiRouter::setApiReply(ApiRouter::getResponse());
}
require_once BASE_DIR.'/core/system.php';

if (!defined('ADMIN_FILE') && $conf['close'] && !isAdmin()) { http_response_code(503); setExit(_CLOSE_TEXT); }

# A path the cut of core/security.php kept resolves by one grammar, first match wins, and its keys replace the same keys of the query in $_GET
if ($urlroute['seg'] !== []) {
    $rseg = $urlroute['seg'];
    $rlen = count($rseg);
    $rte = new Route($db);
    $rown = isset($conf['node']['types'][$rseg[0]]) || isset($conf['modules'][$rseg[0]]);
    $rops = $rown && $rlen <= 3 && $rte->isRouteReserved($rseg[0], $rseg[1] ?? '');
    if ($rown && $rlen === 1) {
        $rkeys = ['name' => $rseg[0]];
    } elseif ($rops && ($rlen === 2 || ctype_digit($rseg[2]))) {
        $rkeys = ['name' => $rseg[0], 'op' => $rseg[1]] + (($rlen === 3) ? ['id' => $rseg[2]] : []);
    } elseif (!$rown || $rlen === 2) {
        $rhit = $rte->getRoute(implode('/', $rseg));
        if ($rhit === []) setError(404);
        if ($rhit['state'] === 2) setError(410);
        $rpre = explode('/', $rhit['path'] ?: implode('/', $rseg))[0];
        $roid = (string)$rhit['oid'];
        $rkeys = match ($rhit['kind']) {
            'type' => ['name' => $rpre],
            'node' => ['name' => $rpre, 'op' => 'view', 'id' => $roid],
            'cat' => ['name' => $rpre, 'cat' => $roid],
            'topic' => ['name' => 'forum', 'op' => 'view', 'id' => $roid],
            'user' => ['name' => 'account', 'op' => 'view', 'id' => $roid],
            default => ['name' => 'voting', 'op' => 'view', 'id' => $roid],
        };
    } else {
        setError(404);
    }
    $_GET = array_replace($_GET, $rkeys);
    unset($rseg, $rlen, $rte, $rown, $rops, $rkeys, $rhit, $rpre, $roid);
}

$go =getVar('req', 'go', 'var');
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

# Ajax handlers of the public list take no token: they guard themselves, and their reads answer nothing to a visitor who may not see them
if (empty($go)) {
    Cache::setHeaders();
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
    $hname = '';
    if (!$name && !empty($conf['module'])) {
        $hmodul = explode(',', $conf['module']);
        $hname = $hmodul[mt_rand(0, count($hmodul) - 1)];
    }
    $nname = $name ?: $hname;
    if ($nname === 'node' || isset($conf['node']['types'][$nname])) {
        if ($nname === 'node' || $file !== 'index') setError(404);
        if (!$name) $home = 1;
        $name = $nname;
        $conf['name'] = $name;
        $mconf = $conf['modules']['node'] ?? [];
        $blocks = (string)($mconf['side'] ?? '');
        $blocks_c = (string)($mconf['top'] ?? '');
        getLang('node');
        require_once BASE_DIR.'/modules/node/index.php';
        exit;
    }
    if ($name) {
        if (!isset($conf['modules'][$name]) && !is_dir(BASE_DIR.'/modules/'.$name)) {
            $lmove = getNodeLegacyUrl($name, (string)$op, getVar('get', 'id', 'num', 0), getVar('get', 'cat', 'num', 0));
            if ($lmove !== '') setSeoMove(['url' => $lmove]);
        }
        $conf['name'] = $name;
        $module = 1;
        $mconf = $conf['modules'][$name] ?? [];
        $active = $mconf['active'] ?? 0;
        $view = $mconf['view'] ?? 0;
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
                if (!is_user()) $info = sprintf(_MODULEUSERS, getSeoUrl(['name' => 'account'])).' ';
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
            $name = $hname;
            $conf['name'] = $name;
            $mconf = $conf['modules'][$name] ?? [];
            $blocks = (string)($mconf['side'] ?? '');
            $blocks_c = (string)($mconf['top'] ?? '');
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
    $public = ($go == 1 && in_array($op, ['getUserSessionInfo', 'getUserSessionRows', 'getPrivateMessageView', 'getRatingView', 'getQuickEdit', 'updateQuickEdit',
        'getFavoriteList', 'getNodeHint'], true))
        || (($go == 1 || $go == 5) && $op === 'getUserSessionAdminInfo');
    if ($go == 1 && in_array($op, ['addComment', 'updateCommentStatus', 'deleteComment', 'addPrivateMessage',
        'setPrivateMessageRead', 'updatePrivatBox', 'addFavorite', 'deleteFavorite'], true) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        Cache::setHeaders();
        header('Allow: POST');
        http_response_code(405);
        die($tpl->getHtmlFrag('alert', ['text' => _ERROR, 'is_warn' => true]));
    }
    if ($go != 3 && !$public) {
        $fdsize = intval($_FILES['file']['size'] ?? 0);
        $tok = getVar('req', 'token', 'raw', '')
            ?: ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!($go == 4 && $fdsize) && !checkSiteToken($tok)) die($tpl->getHtmlFrag('alert', ['text' => _TOKENMISS, 'is_warn' => true]));
    }
    if ($go == 1) {
        Cache::setHeaders();
        switch($op) {
            case 'getRatingView': getRatingView(); break;
            case 'getUserSessionAdminInfo': getUserSessionAdminInfo(); break;
            case 'getSessionStats': getSessionStats(); exit;
            case 'getUserSessionInfo': getUserSessionInfo(); break;
            case 'getUserSessionRows': getUserSessionRows(); break;
            case 'getUserList': getUserList(); break;
            case 'getQuickEdit': getQuickEdit(); break;
            case 'updateQuickEdit': updateQuickEdit(); break;
            case 'addComment': addComment(); break;
            case 'updateCommentStatus': updateCommentStatus(); break;
            case 'deleteComment': deleteComment(); break;
            case 'getCommentPage': getCommentPage(); break;
            case 'getCommentBranch': getCommentBranch(); break;
            case 'getPrivateMessageView': echo getPrivateMessageView(); break;
            case 'setPrivateMessageRead': setPrivateMessageRead(); break;
            case 'addPrivateMessage': addPrivateMessage(); break;
            case 'getPrivateCountData': getPrivateCountData(); exit;
            case 'updatePrivatBox': updatePrivatBox(); break;
            case 'addFavorite': addFavorite(); break;
            case 'getFavoriteList': getFavoriteList(); break;
            case 'deleteFavorite': deleteFavorite(); break;
            case 'getVotingView': echo getVotingView(); break;
            case 'updateVotingResult': updateVotingResult(); break;
            case 'getEditorPreview': getEditorPreview(); break;
            case 'getNodeHint': echo getNodeHint(); break;
        }
    } elseif ($go == 3) {
        Cache::setHeaders();
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
            session_write_close();
            echo json_encode($name ? addSchedulerRun($name, $type) : addSchedulerBatch($type), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    } elseif ($go == 4) {
        Cache::setHeaders();
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
        if (isAdmin(true) || (isAdmin() && in_array($op, ['getAdminQueueData', 'getAdminSectionData'], true))) {
            define('ADMIN_FILE', true);
            getLang('admin');
            Cache::setHeaders();
            require_once BASE_DIR.'/core/admin.php';
            $tpl = new Template('admin');
            switch($op) {
                case 'getAdminCategoryList': getAdminCategoryList(); break;
                case 'updateAdminCategoryOrder': updateAdminCategoryOrder(); break;
                case 'getAdminBlockList': getAdminBlockList(); break;
                case 'updateAdminBlockOrder': updateAdminBlockOrder(); break;
                case 'getUserSessionAdminInfo': getUserSessionAdminInfo(); break;
                case 'getSessionStats': getSessionStats(); exit;
                case 'getAdminQueueData': getAdminQueueData(); exit;
                case 'getAdminSectionData': getAdminSectionData(); exit;
                case 'getCockpitData': header('Content-Type: application/json; charset=UTF-8'); echo json_encode(getCockpitData()); exit;
                case 'getAdminFileList': getAdminFileList(); break;
                case 'getAdminFileData': getAdminFileData(); break;
                case 'getAdminFilePreview': getAdminFilePreview(); break;
                case 'getAdminFileDownload': getAdminFileDownload(); break;
                case 'getEditorPreview': getEditorPreview(); break;
            }
        } else {
            die('Illegal file access');
        }
    }
    $cvar = explode(',', $conf['variables']);
    if (!$cvar[0] && is_moder()) echo getTimedHtml(getVariables());
} elseif ($go == 'rss') {
    Cache::setHeaders();
    echo getRssChannel();
} elseif ($go == 'search') {
    Cache::setHeaders('public');
    echo getOpenSearch();
} elseif ($go == 'xsl') {
    Cache::setHeaders('public', 'text/xsl');
    echo getOpenXsl();
} elseif ($go == 'captcha') {
    Cache::setHeaders();
    getCaptchaChallenge(getVar('req', 'act', 'var') ?: 'default');
} elseif ($go == 'file') {
    setFileRoute();
}
