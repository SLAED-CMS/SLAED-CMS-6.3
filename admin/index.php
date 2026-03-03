<?php
# Author: Eduard Laas
# Copyright (c) 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE')) die('Illegal file access');
require_once BASE_DIR.'/core/system.php';
getLang('admin');
setCache('0');
checkAccess();

function getAdminMenu(string $url, string $title, string $image, string $class = ''): string {
 global $conf, $panel, $count;
    $ltitle = ($class !== '') ? $title.' - '._DEACT : $title;
    $path = img_find('admin/'.$image);
    $image = file_exists($path) ? $path : img_find('admin/components.png');
    if ($panel) {
        $cont = '';
        if (($count - 1) % $conf['admcol'] == 0) $cont = '<tr>';
        $cont .= '<td class="sl_td_mod'.$class.'"><a href="'.$url.'" title="'.$ltitle.'"><img src="'.$image.'" alt="'.$ltitle.'" title="'.$ltitle.'" class="sl_img_mod"><br>'.$title.'</a></td>';
        if ($count % $conf['admcol'] == 0) $cont .= '</tr>';
        $count++;
        return $cont;
    }
    return '<table class="sl_tab_blm'.$class.'"><tr><td><a href="'.$url.'" title="'.$ltitle.'"><img src="'.$image.'" alt="'.$ltitle.'" title="'.$ltitle.'" class="sl_img_blm"></a></td><td><a href="'.$url.'" title="'.$ltitle.'">'.$title.'</a></td></tr></table>';
}

function getAdminPanelBlocks(): string {
 global $panel, $afile, $conf;
    if (!$panel) {
        $cont = '';
        if (isAdmin(true)) {
            foreach ($conf['modules'] as $name => $mod) {
                if (($mod['type'] ?? 1) == 0) {
                    $class = (!$mod['active']) ? ' sl_hidden' : '';
                    $cont .= getAdminMenu(
                        $afile.'.php?name='.$name,
                        (defined($mod['lang']) ? constant($mod['lang']) : $mod['lang']),
                        $mod['img'],
                        $class,
                    );
                }
            }
            $block = setTemplateBlock('block-left', ['{%title%}' => _ADMIN, '{%content%}' => $cont, '{%id%}' => '1']);
            $cont = '';
        }
        foreach ($conf['modules'] as $name => $mod) {
            if (($mod['type'] ?? 1) == 1) {
                if (isAdmin(true) || is_admin_modul($name)) {
                    $path = BASE_DIR.'/modules/'.$name.'/admin';
                    if (file_exists($path.'/index.php')) {
                        $class = (!$mod['active']) ? ' sl_hidden' : '';
                        $cont .= getAdminMenu(
                            $afile.'.php?name='.$name,
                            (defined($mod['lang']) ? constant($mod['lang']) : $mod['lang']),
                            $mod['img'],
                            $class,
                        );
                        getLang($name, true);
                    }
                }
            }
        }
        $block .= setTemplateBlock('block-left', ['{%title%}' => _MODULES, '{%content%}' => $cont, '{%id%}' => '2']);
        return $block;
    }
    return '';
}

function getAdminPanel(): void {
 global $conf, $panel, $count, $afile, $class;
    setHead();
    $content = '';
    $minver = '8.1.0';
    $info = sprintf(_PHPSETUP, $minver);
    if (file_exists('setup.php')) $content .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _DELSETUP]);
    if (PHP_VERSION < $minver) $content .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $info]);
    if ($conf['admininfo']) $content .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $conf['admininfo']]);
    if ($panel) {
        $count = 1;
        if (isAdmin(true)) {
            $cont = '';
            foreach ($conf['modules'] as $name => $mod) {
                if (($mod['type'] ?? 1) == 0) {
                    $class = (!$mod['active']) ? ' sl_hidden' : '';
                    $cont .= getAdminMenu(
                        $afile.'.php?name='.$name,
                        (defined($mod['lang']) ? constant($mod['lang']) : $mod['lang']),
                        $mod['img'],
                        $class,
                    );
                }
            }
            $content .= setTemplateBasic('panel-admin', ['{%title%}' => _MODULESADMIN, '{%content%}' => $cont]);
        }
        $count = 1;
        $cont = '';
        foreach ($conf['modules'] as $name => $mod) {
            if (($mod['type'] ?? 1) == 1) {
                if (isAdmin(true) || is_admin_modul($name)) {
                    $path = BASE_DIR.'/modules/'.$name.'/admin';
                    if (file_exists($path.'/index.php')) {
                        $class = (!$mod['active']) ? ' sl_hidden' : '';
                        $cont .= getAdminMenu(
                            $afile.'.php?name='.$name,
                            (defined($mod['lang']) ? constant($mod['lang']) : $mod['lang']),
                            $mod['img'],
                            $class,
                        );
                        getLang($name, true);
                    }
                }
            }
        }
        $content .= setTemplateBasic('panel-modul', ['{%title%}' => _MODULESADMIN, '{%content%}' => $cont]);
    }
    echo $content;
    setFoot();
}

# OLD FUNCTIONS - REFAKTORING NEEDED
function add_admin() {
 global $db, $afile, $conf, $stop;
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_admins')) == 0) {
        $aname = $_POST['aname'];
        $aurl = filterUrl($_POST['aurl']);
        $aemail = $_POST['aemail'];
        $apwd = md5_salt($_POST['apwd']);
        $apwd2 = md5_salt($_POST['apwd2']);
        $auser_new = intval($_POST['auser_new']);
        $aeditor = intval($conf['redaktor']);
        $alang = getCookies('language');
        $aip = getip();
        if (!$aname || !analyze_name($aname)) $stop = _ERRORINVNICK;
        if (!$_POST['apwd'] && !$_POST['apwd2']) $stop = _NOPASS;
        if ($apwd != $apwd2) $stop = _ERROR_PASS;
        if (strlen($aname) > 25) $stop = _NICKLONG;
        if (!$stop) {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB."_admins VALUES (NULL, '".$aname."', 'Admin', '".$aurl."', '".$aemail."', '".$apwd."', '1', '".$aeditor."', '1', '', '".$alang."', '".$aip."', now(), now())");
            if ($auser_new == 1) {
                $auser_avatar = 'default/00.gif';
                $user_exist = $db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB."_users WHERE user_name = '".$aname."'"));
                if ($user_exist) $db->getSqlQuery('DELETE FROM '.PREFIX_DB."_users WHERE user_name='".$aname."'");
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB."_users (user_id, user_name, user_email, user_website, user_avatar, user_regdate, user_password, user_lang, user_last_ip, user_block, user_warnings, user_field) VALUES (NULL, '".$aname."', '".$aemail."', '".$aurl."', '".$auser_avatar."', now(), '".$apwd."', '".$alang."', '".$aip."', '', '', '')");
            }
            header('Location: '.$afile.'.php');
        } else {
            login();
        }
    } else {
        header('Location: '.$afile.'.php');
    }
}

function check_admin() {
 global $db, $afile, $conf, $stop;
    if (($conf['gfx_chk'] == 1 || $conf['gfx_chk'] == 5 || $conf['gfx_chk'] == 6 || $conf['gfx_chk'] == 7) && checkCaptcha(2)) $stop = _SECCODEINCOR;
    $name = htmlspecialchars(trim(substr($_POST['name'], 0, 25)));
    $pwd = htmlspecialchars(trim(substr($_POST['pwd'], 0, 25)));
    if (!$name || !$pwd) $stop = _LOGININCOR;
    $result = $db->getSqlQuery('SELECT id, name, pwd, editor FROM '.PREFIX_DB."_admins WHERE name = '".$name."' AND pwd = '".md5_salt($pwd)."'");
    if ($db->getSqlRowCount($result) != 1) $stop = _LOGININCOR;
    list($aid, $aname, $apwd, $aeditor) = $db->getSqlRow($result);
    if (!$aid || $aname != $name || $apwd != md5_salt($pwd)) $stop = _LOGININCOR;
    if (!$stop) {
        unset($_SESSION[$conf['admin_c']]);
        $info = base64_encode($aid.':'.$aname.':'.$apwd.':'.$aeditor);
        $_SESSION[$conf['admin_c']] = $info;
        $ip = getip();
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB."_session WHERE uname = '".$ip."'");
        $db->getSqlQuery('UPDATE '.PREFIX_DB."_admins SET ip = '".$ip."', lastvisit = now() WHERE id = '".$aid."'");
        login_report(1, 1, $name, '');
        header('Location: '.$afile.'.php');
    } else {
        login_report(1, 0, $name, $pwd);
        login();
    }
}

function login() {
 global $db, $afile, $conf, $stop;
    setHead();
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_admins')) == 0) {
        $cont = ($stop) ? setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'atten', 'text' => $stop]) : '';
        $cont .= setTemplateBasic('registration', ['{%route%}' => $afile, '{%nickname%}' => _NICKNAME, '{%aname%}' => $_POST['aname'], '{%homepage%}' => _HOMEPAGE, '{%host%}' => getHost(), '{%email%}' => _EMAIL, '{%aemail%}' => $_POST['aemail'], '{%password%}' => _PASSWORD, '{%retype%}' => _RETYPEPASSWORD, '{%createuserdata%}' => _CREATEUSERDATA, '{%yes%}' => _YES, '{%no%}' => _NO, '{%send%}' => _SEND]);
    } else {
        $captcha = ($conf['gfx_chk'] == 1 || $conf['gfx_chk'] == 5 || $conf['gfx_chk'] == 6 || $conf['gfx_chk'] == 7) ? getCaptcha(2) : '';
        $cont = ($stop) ? setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'atten', 'text' => $stop]) : '';
        $cont .= setTemplateBasic('login', ['{%route%}' => $afile, '{%nickname%}' => _NICKNAME, '{%password%}' => _PASSWORD, '{%captcha%}' => $captcha, '{%login%}' => _LOGIN]);
    }
    echo $cont;
    setFoot();
}

function changeeditor() {
 global $db, $admin, $afile, $conf;
    $editor = (isset($_POST['editor'])) ? intval($_POST['editor']) : intval($conf['redaktor']);
    $aid = intval(substr($admin[0], 0, 11));
    $info = base64_decode($_SESSION[$conf['admin_c']]);
    $sinfo = base64_encode(substr($info, 0, -1).$editor);
    unset($_SESSION[$conf['admin_c']]);
    $_SESSION[$conf['admin_c']] = $sinfo;
    $db->getSqlQuery('UPDATE '.PREFIX_DB."_admins SET editor = '".$editor."' WHERE id = '".$aid."'");
    setRedirect($afile.'.php', true);
}

function logout() {
 global $db, $admin, $afile, $conf;
    $aname = filterText(substr($admin[1], 0, 25), 1);
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB."_session WHERE uname = '".$aname."' AND guest = '3'");
    unset($_SESSION[$conf['admin_c']], $admin);
    header('Location: '.$afile.'.php');
}

if (isAdmin()) {
    $name = getVar('req', 'name', 'var');
    $op = getVar('req', 'op', 'var', 'show');
    $panel = (empty($name)) ? 1 : 0;
    $id = getVar('req', 'id', 'num');
    $act = getVar('req', 'act', 'num');
    $pagetitle = $conf['defis'].' '._ADMINMENU;
    if ($op == 'changeeditor') {
        changeeditor();
    } elseif ($op == 'logout') {
        logout();
    } elseif ($panel) {
        getAdminPanel();
    } else {
        if (isAdmin(true)) {
            $module_file = BASE_DIR.'/admin/modules/'.$name.'.php';
            if (file_exists($module_file)) require_once $module_file;
        }
        if (isset($conf['modules'][$name])) {
            if (isAdmin(true) || is_admin_modul($name)) {
                $path = BASE_DIR.'/modules/'.$name.'/admin';
                if (file_exists($path.'/index.php')) {
                    getLang($name, true);
                    require_once $path.'/index.php';
                }
            }
        }
    }
} else {
    $home = 1;
    $op = getVar('post', 'op', 'var');
    switch($op) {
        default: login(); break;
        case 'add_admin': add_admin(); break;
        case 'check_admin'; check_admin(); break;
    }
}
