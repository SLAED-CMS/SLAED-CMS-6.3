<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

define('MODULE_FILE', true);
$sgtime = microtime(true);
include('core/core.php');

if (!defined('ADMIN_FILE') && $conf['close'] && !is_admin()) setExit(_CLOSE_TEXT);
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
                header('Location: index.php?newlang=english');
            } elseif ($userip == 'France') {
                header('Location: index.php?newlang=french');
            } elseif ($userip == 'Germany') {
                header('Location: index.php?newlang=german');
            } elseif ($userip == 'Poland') {
                header('Location: index.php?newlang=polish');
            } elseif ($userip == 'Russian Federation') {
                header('Location: index.php?newlang=russian');
            } elseif ($userip == 'Ukraine') {
                header('Location: index.php?newlang=ukrainian');
            }
        }
    }
    $file = (getVar('req', 'file', 'var')) ? $file : 'index';
    $theme = get_theme();
    if ($name) {
        $conf['name'] = $name;
        $conf['style'] = 'sl_mod_'.strtolower($name);
        $module = 1;
        list($mod_active, $view, $blocks, $blocks_c) = $db->sql_fetchrow($db->sql_query("SELECT active, view, blocks, blocks_c FROM ".$prefix."_modules WHERE title='".$name."'"));
        if (intval($mod_active) || is_moder($name)) {
            if ($view == 0 && file_exists('modules/'.$name.'/'.$file.'.php')) {
                include('modules/'.$name.'/'.$file.'.php');
            } elseif (($view == 1 && (is_user() && is_mod_group($name)) || is_moder($name)) && file_exists('modules/'.$name.'/'.$file.'.php')) {
                include('modules/'.$name.'/'.$file.'.php');
            } elseif ($view == 1 && !is_moder($name)) {
                if (!is_user()) $info = _MODULEUSERS.' ';
                list($gname) = $db->sql_fetchrow($db->sql_query("SELECT name FROM ".$prefix."_modules LEFT JOIN ".$prefix."_groups ON (mod_group=id) WHERE title='".$name."'"));
                if ($gname) $info .= _ADDITIONALYGRP.': '.$gname;
                head();
                echo setTemplateBasic('title', array('{%title%}' => _ACCESSDENIED)).setTemplateWarning('warn', array('time' => '15', 'url' => '?name=account&op=newuser', 'id' => 'info', 'text' => $info));
                foot();
                exit;
            } elseif ($view == 2 && is_moder($name) && file_exists('modules/'.$name.'/'.$file.'.php')) {
                include('modules/'.$name.'/'.$file.'.php');
            } elseif ($view == 2 && !is_moder($name)) {
                head();
                echo setTemplateBasic('title', array('{%title%}' => _ACCESSDENIED)).setTemplateWarning('warn', array('time' => '5', 'url' => '', 'id' => 'info', 'text' => _MODULESADMINS));
                foot();
                exit;
            } else {
                header('Location: index.php');
                exit;
            }
        } else {
            header('Location: index.php');
            exit;
        }
    } else {
        $home = 1;
        if (empty($conf['module'])) {
            $conf['name'] = '';
            head();
            foot();
            exit;
        } else {
            $hmodul = explode(',', $conf['module']);
            $hi = mt_rand(0, count($hmodul) - 1);
            $name = $hmodul[$hi];
            $conf['name'] = $name;
            if (file_exists('modules/'.$name.'/'.$file.'.php')) {
                include('modules/'.$name.'/'.$file.'.php');
                exit;
            } else {
                head();
                echo tpl_warn('warn', _HOMEPROBLEMUSER, '', '', 'warn');
                foot();
                exit;
            }
        }
    }
} elseif (is_numeric($go)) {
    $fdsize = isset($_FILES['file']['size']) ? $_FILES['file']['size'] : '';
    if (!intval($fdsize) && !stristr(getenv('HTTP_REFERER'), get_host())) die('Illegal file access');
    if ($go == 1) {
        setThemeInclude();
        setCache('0');
        switch($op) {
            case 'rating': rating(); break;
            case 'show_files': show_files(); break;
            case 'user_sainfo': user_sainfo(); break;
            case 'user_sinfo': user_sinfo(); break;
            case 'get_user': get_user(); break;
            case 'editcom': editcom(); break;
            case 'savecom': savecom(); break;
            case 'closecom': closecom(); break;
            case 'editpost': editpost(); break;
            case 'prmess': prmess(); break;
            case 'prmesssend': prmesssend(); break;
            case 'prmesssave': prmesssave(); break;
            case 'prmessdel': prmessdel(); break;
            case 'favoradd': favoradd(); break;
            case 'favorliste': favorliste(); break;
            case 'favordel': favordel(); break;
            case 'avoting_view': getVoting(); break;
            case 'avoting_save': avoting_save(); break;
        }
    } elseif ($go == 2) {
        get_lang('shop');
        setThemeInclude();
        setCache('0');
        include('config/config_shop.php');
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
        include('config/config_uploads.php');
        $mod = (getVar('get', 'mod', 'var')) ? strtolower(getVar('get', 'mod', 'var')) : '';
        if ($mod) {
            $userid = (getVar('get', 'userid', 'num')) ? getVar('get', 'userid', 'num') : '0';
            switch($go) {
                default:
                $con = explode('|', $confup[$mod]);
                upload(2, 'uploads/'.$mod, $con[0], $con[2], $mod, $con[3], $con[4], $userid);
                break;
            }
        } else {
            die('Illegal file access');
        }
    } elseif ($go == 5) {
        if (is_admin_god()) {
            define('ADMIN_FILE', true);
            get_lang('admin');
            setThemeInclude();
            setCache('0');
            include('core/admin.php');
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
                case 'adm_info': adm_info(); break;
            }
        } else {
            die('Illegal file access');
        }
    }
    $cvar = explode(',', $conf['variables']);
    if (!$cvar[0] && is_moder()) echo getVariables();
} elseif ($go == 'rss') {
    setCache('0');
    echo rss_channel();
} elseif ($go == 'search') {
    setCache('1');
    echo open_search();
} elseif ($go == 'xsl') {
    setCache('1');
    echo open_xsl();
} elseif ($go == 'css') {
    setCache('1');
    setCss();
} elseif ($go == 'script') {
    setCache('1');
    setScript();
}
