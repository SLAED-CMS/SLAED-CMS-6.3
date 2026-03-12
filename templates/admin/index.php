<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

function setTemplateHead($sub, $val = ''): string {
    global $theme, $conf, $db, $blocks, $admin, $afile;
    $langs = $menu = $blocks = $login = '';
    if (isAdmin()) {
        if ($conf['multilingual'] == 1) {
            foreach (scandir(BASE_DIR.'/lang') as $file) {
                if (preg_match('#^(.+)\.php$#', $file, $matches)) {
                    $lfound = $matches[1];
                    $title = getLangName($lfound);
                    $langs .= '<a href="'.$afile.'.php?newlang='.$lfound.'"><img src="'.img_find('lang/'.$lfound.'_mini.png').'" alt="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'" title="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'"></a>';
                }
            }
        }
        if (!isAdmin(true)) {
            $uname = htmlspecialchars(_HELLO.', '.substr($admin[1], 0, 25).'!', ENT_QUOTES, 'UTF-8');
            $menu = '<li class="sl_first"><a href="#" title="'.$uname.'"><b>'.$uname.'</b></a></li>'
            .'<li><a href="'.$afile.'.php" title="'._ADMINMENU.'"><b>'._HOME.'</b></a></li>'
            .'<li><a href="index.php" target="_blank" title="'._SITE.'"><b>'._SITE.'</b></a></li>'
            .'<li><a href="index.php?name=account" target="_blank" title="'._ACCOUNT.'"><b>'._ACCOUNT.'</b></a></li>'
            .'<li><a href="'.$afile.'.php?op=logout" title="'._LOGOUT.'"><b>'._LOGOUT.'</b></a></li>';
        } else {
            $menu = '<li class="sl_first"><a href="'.$afile.'.php" title="'._ADMINMENU.'"><b>'._HOME.'</b></a></li>'
            .'<li><a href="'.$afile.'.php?name=blocks" title="'._BLOCKS.'"><b>'._BLOCKS.'</b></a></li>'
            .'<li><a href="'.$afile.'.php?name=modules" title="'._MODULES.'"><b>'._MODULES.'</b></a></li>'
            .'<li><a href="'.$afile.'.php?name=categories" title="'._CATEGORIES.'"><b>'._CATEGORIES.'</b></a></li>'
            .'<li><a href="index.php" target="_blank" title="'._SITE.'"><b>'._SITE.'</b></a></li>'
            .'<li><a href="index.php?name=account" target="_blank" title="'._ACCOUNT.'"><b>'._ACCOUNT.'</b></a></li>'
            .'<li><a href="'.$afile.'.php?op=logout" title="'._LOGOUT.'"><b>'._LOGOUT.'</b></a></li>';
        }
        $blocks = getAdminPanelBlocks().admininfo().adminblock();
    } else {
        $login = ($db->getSqlRowCount($db->getSqlQuery('SELECT 1 FROM '.PREFIX_DB.'_admins LIMIT 1')) == 0) ? _ADMINLOGIN_NEW : _ADMINLOGIN;
    }
    $value = ['{%langs%}' => $langs, '{%menu%}' => $menu, '{%blocks%}' => $blocks, '{%login%}' => $login, '{%theme%}' => $theme, '{%lang%}' => substr(_LOCALE, 0, 2)];
    $value = is_array($val) ? array_merge($value, $val) : $value;
    return str_replace(array_keys($value), array_values($value), $sub);
}

function setTemplateBlock($tpl, $val = ''): string {
    static $argc = null, $cach = '';
    if ($argc !== $tpl) {
        $argc = $tpl;
        $cont = getThemeFile($argc);
        if ($cont) $cach = file_get_contents($cont);
    }
    $value = ['{%close%}' => _OPCL];
    $value = is_array($val) ? array_merge($value, $val) : $value;
    return str_replace(array_keys($value), array_values($value), $cach);
}

function setTemplateFoot($sub, $val = ''): string {
    $value = ['{%upper%}' => _PAGETOP];
    $value = is_array($val) ? array_merge($value, $val) : $value;
    return str_replace(array_keys($value), array_values($value), $sub);
}
