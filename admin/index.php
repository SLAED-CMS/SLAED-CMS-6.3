<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE')) die('Illegal file access');
require_once BASE_DIR.'/core/system.php';
getLang('admin');
setCache('0');
checkAccess();

function getAdminMenu(string $url, string $title, string $image, string $class = ''): string {
 global $conf, $panel, $count, $tpl;
    $ltitle = ($class !== '') ? $title.' - '._DEACT : $title;
    $path = img_find('admin/'.$image);
    $image = file_exists($path) ? $path : img_find('admin/components.png');
    if ($panel) {
        $cont = (($count - 1) % $conf['admcol'] === 0) ? '<tr>' : '';
        $cont .= $tpl->getHtmlFrag('admin-panel-grid-item', [
            'image' => $image,
            'title' => $title,
            'title_attr' => $ltitle,
            'url' => $url,
            'wrap_class' => 'sl_td_mod'.$class,
        ]);
        if ($count % $conf['admcol'] === 0) $cont .= '</tr>';
        $count++;
        return $cont;
    }
    return $tpl->getHtmlFrag('admin-panel-list-item', [
        'image' => $image,
        'title' => $title,
        'title_attr' => $ltitle,
        'url' => $url,
        'wrap_class' => 'sl_tab_blm'.$class,
    ]);
}

function getAdminPanelBlocks(): string {
 global $panel, $afile, $conf, $tpl;
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
            $block = $tpl->getHtmlFrag('block-left', ['title' => _ADMIN, 'content' => $cont, 'id' => '1', 'close' => _OPCL]);
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
        $block .= $tpl->getHtmlFrag('block-left', ['title' => _MODULES, 'content' => $cont, 'id' => '2', 'close' => _OPCL]);
        return $block;
    }
    return '';
}

function getAdminPanel(): void {
 global $conf, $panel, $count, $afile, $class, $tpl;
    setHead();
    $content = '';
    $minver = '8.1.0';
    $info = sprintf(_PHPSETUP, $minver);
    if (file_exists('setup.php')) $content .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _DELSETUP]);
    if (PHP_VERSION < $minver) $content .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => $info]);
    if ($conf['admininfo']) $content .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => $conf['admininfo']]);
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
            $content .= getTplAdminPanel('sl_close_1', _MODULESADMIN, $cont);
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
        $content .= getTplAdminPanel('sl_close_2', _MODULESADMIN, $cont);
    }
    echo $content;
    setFoot();
}

function add_admin() {
    global $db, $afile, $conf, $stop;
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_admins LIMIT 1')) == 0) {
        $aname     = filterText(trim(substr($_POST['aname'] ?? '', 0, 25)));
        $aurl      = filterUrl($_POST['aurl'] ?? '');
        $aemail    = filterText($_POST['aemail'] ?? '');
        $apwdraw   = trim(substr($_POST['apwd'] ?? '', 0, 25));
        $apwd2raw  = trim(substr($_POST['apwd2'] ?? '', 0, 25));
        $auser_new = intval($_POST['auser_new'] ?? 0);
        $aeditor   = intval($conf['redaktor']);
        $alang     = getCookies('language');
        $aip       = getip();
        if (!$aname || !analyze_name($aname)) $stop = _ERRORINVNICK;
        if (!$apwdraw && !$apwd2raw) $stop = _NOPASS;
        if ($apwdraw !== $apwd2raw) $stop = _ERROR_PASS;
        if (strlen($aname) > 25) $stop = _NICKLONG;
        if (!$stop) {
            $apwd = getPassHash($apwdraw);
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_admins VALUES (NULL, :name, \'Admin\', :url, :email, :pass, \'1\', :editor, \'1\', \'\', :lang, :ip, now(), now())',
                ['name' => $aname, 'url' => $aurl, 'email' => $aemail, 'pass' => $apwd, 'editor' => $aeditor, 'lang' => $alang, 'ip' => $aip]
            );
            if ($auser_new == 1) {
                $user_exist = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_users WHERE name = :name', ['name' => $aname]));
                if ($user_exist) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_users WHERE name = :name', ['name' => $aname]);
                $db->getSqlQuery(
                    'INSERT INTO '.PREFIX_DB.'_users (id, name, email, website, avatar, regdate, password, lang, ip, block, warnings, field) VALUES (NULL, :name, :email, :website, :avatar, now(), :pass, :lang, :ip, \'\', \'\', \'\')',
                    ['name' => $aname, 'email' => $aemail, 'website' => $aurl, 'avatar' => 'default/00.gif', 'pass' => $apwd, 'lang' => $alang, 'ip' => $aip]
                );
            }
            setRedirect($afile.'.php');
        } else {
            login();
        }
    } else {
        setRedirect($afile.'.php');
    }
}

function check_admin() {
    global $db, $afile, $conf, $stop;
    if (($conf['gfx_chk'] == 1 || $conf['gfx_chk'] == 5 || $conf['gfx_chk'] == 6 || $conf['gfx_chk'] == 7) && checkCaptcha(2)) $stop = _SECCODEINCOR;
    $name = htmlspecialchars(trim(substr($_POST['name'] ?? '', 0, 25)));
    $pwd  = htmlspecialchars(trim(substr($_POST['pwd'] ?? '', 0, 25)));
    if (!$name || !$pwd) $stop = _LOGININCOR;
    $aid = $aname = $apwd = $aeditor = null;
    if (!$stop) {
        $result = $db->getSqlQuery('SELECT id, name, password, editor FROM '.PREFIX_DB.'_admins WHERE name = :name', ['name' => $name]);
        if ($db->getSqlRowCount($result) == 1) {
            [$aid, $aname, $apwd, $aeditor] = $db->getSqlRow($result);
        }
        if (!$aid || $aname !== $name || !checkPassHash($pwd, (string)$apwd)) $stop = _LOGININCOR;
    }
    if (!$stop) {
        if (strlen((string)$apwd) === 32 && ctype_xdigit((string)$apwd)) {
            $newHash = getPassHash($pwd);
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_admins SET password = :pass WHERE id = :id', ['pass' => $newHash, 'id' => $aid]);
            $apwd = $newHash;
        }
        unset($_SESSION[$conf['admin_c']]);
        $info = base64_encode($aid.':'.$aname.':'.$apwd.':'.$aeditor);
        $_SESSION[$conf['admin_c']] = $info;
        $ip = getip();
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :ip', ['ip' => $ip]);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_admins SET ip = :ip, lastvis = now() WHERE id = :id', ['ip' => $ip, 'id' => $aid]);
        login_report(1, 1, $name, '');
        setRedirect($afile.'.php');
    } else {
        login_report(1, 0, $name, $pwd);
        login();
    }
}

function login() {
 global $db, $afile, $conf, $stop, $tpl;
    setHead();
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_admins')) == 0) {
        $cont = ($stop) ? $tpl->getHtmlFrag('alert', ['type' => 'atten', 'text' => $stop]) : '';
        $cont .= $tpl->getHtmlPart('registration', [
            'route' => $afile,
            'nickname' => _NICKNAME,
            'aname' => getVar('post', 'aname', 'var'),
            'homepage' => _HOMEPAGE,
            'host' => getHost(),
            'email' => _EMAIL,
            'aemail' => getVar('post', 'aemail', 'text'),
            'password' => _PASSWORD,
            'retype' => _RETYPEPASSWORD,
            'createuserdata' => _CREATEUSERDATA,
            'yes' => _YES,
            'no' => _NO,
            'send' => _SEND,
        ]);
    } else {
        $cont = ($stop) ? $tpl->getHtmlFrag('alert', ['type' => 'atten', 'text' => $stop]) : '';
        $cont .= $tpl->getHtmlPart('login', [
            'route' => $afile,
            'nickname' => _NICKNAME,
            'password' => _PASSWORD,
            'captcha' => in_array((int)($conf['gfx_chk'] ?? 0), [1, 5, 6, 7], true) ? getCaptcha(2) : '',
            'login' => _LOGIN,
        ]);
    }
    echo $cont;
    setFoot();
}

function changeeditor() {
    global $db, $admin, $afile, $conf;
    $editor = getVar('post', 'editor', 'num', intval($conf['redaktor']));
    $aid = intval(substr($admin[0], 0, 11));
    $info = base64_decode($_SESSION[$conf['admin_c']]);
    $sinfo = base64_encode(substr($info, 0, -1).$editor);
    unset($_SESSION[$conf['admin_c']]);
    $_SESSION[$conf['admin_c']] = $sinfo;
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_admins SET editor = :editor WHERE id = :id', ['editor' => $editor, 'id' => $aid]);
    setRedirect($afile.'.php', true);
}

function logout() {
    global $db, $admin, $afile, $conf;
    $aname = filterText(substr($admin[1], 0, 25), 1);
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :name AND guest = :guest', ['name' => $aname, 'guest' => '3']);
    unset($_SESSION[$conf['admin_c']], $admin);
    setRedirect($afile.'.php');
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
