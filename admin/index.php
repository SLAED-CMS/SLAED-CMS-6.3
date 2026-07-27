<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE')) die('Illegal file access');
require_once BASE_DIR.'/core/system.php';
getLang('admin');
Cache::setHeaders(false);
checkAccess();

# Check that a module exposes an editable configuration: a config file plus a config handler in its admin routing
function checkModuleConfig(string $name, int $typ): bool {
    if (!is_file(BASE_DIR.'/config/'.$name.'.php')) return false;
    $file = ($typ === 0) ? BASE_DIR.'/admin/modules/'.$name.'.php' : BASE_DIR.'/modules/'.$name.'/admin/index.php';
    if (!is_file($file)) return false;
    return strpos((string)file_get_contents($file), "case 'config'") !== false;
}

# Build one dashboard module card or one sidebar menu row from a module config entry
function getAdminMenu(string $name, array $mod): string {
 global $panel, $afile, $tpl;
    $url = $afile.'.php?name='.$name;
    $title = defined($mod['lang']) ? constant($mod['lang']) : $mod['lang'];
    $icon = preg_match('/^[a-z0-9-]+$/', $mod['icon'] ?? '') ? $mod['icon'] : 'puzzle';
    $off = empty($mod['active']);
    $ltitle = $off ? $title.' - '._DEACT : $title;
    $dial = [];
    if (isAdmin(true)) {
        $typ = (int)($mod['type'] ?? 1);
        $dial[] = ['href' => $afile.'.php?name=modules&op=edit&mod='.$name, 'title' => _FULLEDIT, 'icon_name' => 'pencil-square'];
        if (checkModuleConfig($name, $typ)) $dial[] = ['href' => $url.'&op=config', 'title' => _PREFERENCES, 'icon_name' => 'gear'];
        if (in_array($name, getCategoryModules(), true)) $dial[] = ['href' => $afile.'.php?name=categories&modul='.$name, 'title' => _CATEGORIES, 'icon_name' => 'folder2'];
        if ($typ === 1 && !$off) $dial[] = ['href' => 'index.php?name='.$name, 'title' => _VIEWSITE, 'icon_name' => 'arrow-up-right-circle'];
        $dial[] = ['href' => $url.'&op=info', 'title' => _DOCS, 'icon_name' => 'info-circle'];
        $stat = $afile.'.php?name=modules&op=status&mod='.$name.'&act='.($off ? '1' : '0').'&refer=1&token='.getSiteToken();
        $dial[] = ['href' => $stat, 'title' => $off ? _ACTIVATE : _DEACTIVATE, 'icon_name' => 'power'];
    }
    if ($panel) {
        $view = (int)($mod['view'] ?? 0);
        $who = ($view === 2) ? _MVADMIN : (($view === 1) ? _MVUSERS : _MVALL);
        return $tpl->getHtmlFrag('menu-grid-item', [
            'url' => $url,
            'title' => $title,
            'title_attr' => $ltitle,
            'icon_name' => $icon,
            'subtitle' => $name.' · '.$who,
            'is_off' => $off,
            'led_title' => $off ? _DEACT : _ACT,
            'dial' => $dial,
            'dial_title' => _FUNCTIONS,
        ]);
    }
    return $tpl->getHtmlFrag('menu-list-item', [
        'is_off' => $off,
        'link' => ['href' => $url, 'title' => $ltitle, 'icon_name' => $icon, 'label' => $title],
        'dial' => $dial,
        'dial_title' => _FUNCTIONS,
    ]);
}

function getAdminPanelBlocks(): string {
 global $panel, $conf, $tpl;
    if (!$panel) {
        $cont = '';
        if (isAdmin(true)) {
            foreach ($conf['modules'] as $name => $mod) {
                if (($mod['type'] ?? 1) == 0) $cont .= getAdminMenu($name, $mod);
            }
            $block = $tpl->getHtmlPart('block-sidebar', ['title' => _ADMIN, 'icon_name' => 'person-gear', 'content_html' => $cont, 'id' => '1', 'close' => _OPCL]);
            $cont = '';
        }
        foreach ($conf['modules'] as $name => $mod) {
            if (($mod['type'] ?? 1) == 1) {
                if (isAdmin(true) || is_admin_modul($name)) {
                    $path = BASE_DIR.'/modules/'.$name.'/admin';
                    if (file_exists($path.'/index.php')) {
                        $cont .= getAdminMenu($name, $mod);
                        getLang($name, true);
                    }
                }
            }
        }
        $block .= $tpl->getHtmlPart('block-sidebar', ['title' => _MODULES, 'icon_name' => 'people', 'content_html' => $cont, 'id' => '2', 'close' => _OPCL]);
        return $block;
    }
    return '';
}

function getAdminPanel(): void {
 global $conf, $panel, $tpl;
    setHead();
    $content = '';
    $minver = '8.1.0';
    $info = sprintf(_PHPSETUP, $minver);
    if (file_exists('setup.php')) $content .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _DELSETUP]);
    if (PHP_VERSION < $minver) $content .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $info]);
    if ($conf['admininfo']) $content .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => $conf['admininfo']]);
    if ($panel) {
        if (isAdmin(true)) {
            $items = [];
            foreach ($conf['modules'] as $name => $mod) {
                if (($mod['type'] ?? 1) == 0) $items[] = getAdminMenu($name, $mod);
            }
            $content .= $tpl->getHtmlPart('dashboard-panel', ['panel_id' => 'sl_panel_admin', 'title' => _MODADMINPANEL, 'icon_name' => 'person-gear', 'content_html' => $tpl->getHtmlPart('div', ['is_menu_grid' => true, 'content_html' => implode('', $items)])]);
        }
        $items = [];
        foreach ($conf['modules'] as $name => $mod) {
            if (($mod['type'] ?? 1) == 1) {
                if (isAdmin(true) || is_admin_modul($name)) {
                    $path = BASE_DIR.'/modules/'.$name.'/admin';
                    if (file_exists($path.'/index.php')) {
                        $items[] = getAdminMenu($name, $mod);
                        getLang($name, true);
                    }
                }
            }
        }
        $content .= $tpl->getHtmlPart('dashboard-panel', ['panel_id' => 'sl_panel_site', 'title' => _MODSITEPANEL, 'icon_name' => 'people', 'content_html' => $tpl->getHtmlPart('div', ['is_menu_grid' => true, 'content_html' => implode('', $items)])]);
    }
    echo $content;
    setFoot();
}

function add_admin() {
    global $db, $afile, $conf, $stop;
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_admins LIMIT 1')) == 0) {
        $aname     = filterText(trim(substr($_POST['aname'] ?? '', 0, 25)));
        $aurl      = filterWebUrl($_POST['aurl'] ?? '');
        $aemail    = filterText($_POST['aemail'] ?? '');
        $apwdraw   = trim(substr($_POST['apwd'] ?? '', 0, 25));
        $apwd2raw  = trim(substr($_POST['apwd2'] ?? '', 0, 25));
        $auser_new = intval($_POST['auser_new'] ?? 0);
        $aeditor   = (string)($conf['editor']['admin'] ?? 'plain');
        if (!isValidEditor($aeditor, 'admin')) $aeditor = 'plain';
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
                    ['name' => $aname, 'email' => $aemail, 'website' => $aurl, 'avatar' => '', 'pass' => $apwd, 'lang' => $alang, 'ip' => $aip]
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
    if (checkCaptcha('adminlogin')) $stop = _SECCODEINCOR;
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
        Captcha::clearLoginFailures('admin');
        login_report(1, 1, $name, '');
        setRedirect($afile.'.php');
    } else {
        Captcha::registerLoginFailure('admin');
        login_report(1, 0, $name, $pwd);
        login();
    }
}

function login() {
 global $db, $afile, $conf, $stop, $tpl;
    setHead();
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_admins')) == 0) {
        $cont = ($stop) ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]) : '';
        $cont .= $tpl->getHtmlPart('auth-form', [
            'route' => $afile,
            'rows' => [
                ['has_colon' => true, 'label' => _NICKNAME, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'aname', 'value_attr' => getVar('post', 'aname', 'var'), 'maxlength_num' => 25, 'placeholder_text' => _NICKNAME, 'is_required' => true])],
                ['has_colon' => true, 'label' => _HOMEPAGE, 'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'aurl', 'value_attr' => 'http://'.getHost(), 'maxlength_num' => 255, 'placeholder_text' => _HOMEPAGE, 'is_required' => true])],
                ['has_colon' => true, 'label' => _EMAIL, 'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'aemail', 'value_attr' => getVar('post', 'aemail', 'text'), 'maxlength_num' => 255, 'placeholder_text' => _EMAIL, 'is_required' => true])],
                ['has_colon' => true, 'label' => _PASSWORD, 'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'apwd', 'maxlength_num' => 25, 'placeholder_text' => _PASSWORD, 'is_required' => true])],
                ['has_colon' => true, 'label' => _RETYPEPASSWORD, 'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'apwd2', 'maxlength_num' => 25, 'placeholder_text' => _RETYPEPASSWORD, 'is_required' => true])],
                ['label' => _CREATEUSERDATA, 'field_html' => $tpl->getHtmlFrag('radio', ['name_attr' => 'auser_new', 'value_attr' => '1', 'label_text' => _YES, 'is_checked' => true]).' '.$tpl->getHtmlFrag('radio', ['name_attr' => 'auser_new', 'value_attr' => '0', 'label_text' => _NO])],
            ],
            'hidden' => ['name_attr' => 'op', 'value_attr' => 'add_admin'],
            'submit' => ['button_type' => 'submit', 'submit_label' => _SEND],
        ]);
    } else {
        $cont = ($stop) ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]) : '';
        $capt = getCaptcha('adminlogin');
        $rows = [
            ['has_colon' => true, 'label' => _NICKNAME, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'name', 'maxlength_num' => 25, 'placeholder_text' => _NICKNAME, 'autocomplete_attr' => 'username', 'is_required' => true])],
            ['has_colon' => true, 'label' => _PASSWORD, 'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'pwd', 'maxlength_num' => 25, 'placeholder_text' => _PASSWORD, 'autocomplete_attr' => 'current-password', 'is_required' => true])],
        ];
        if ($capt) $rows[] = ['field_html' => $capt];
        $cont .= $tpl->getHtmlPart('auth-form', [
            'route' => $afile,
            'rows' => $rows,
            'hidden' => ['name_attr' => 'op', 'value_attr' => 'check_admin'],
            'submit' => ['button_type' => 'submit', 'submit_label' => _LOGIN],
        ]);
    }
    echo $cont;
    setFoot();
}

function isValidEditor(string $key, string $role): bool {
    if ($key === '') return false;
    $path = BASE_DIR.'/plugins/editors/'.$key.'/manifest.json';
    if (!is_file($path)) return false;
    $json = file_get_contents($path);
    if ($json === false || $json === '') return false;
    $data = json_decode($json, true);
    if (!is_array($data)) return false;
    if (($data['id'] ?? '') !== $key) return false;
    if (($data['enabled'] ?? false) !== true) return false;
    if (($data['type'] ?? '') !== 'content') return false;
    $roles = $data['roles'] ?? [];
    if (!is_array($roles)) return false;
    return in_array($role, $roles, true);
}

function changeeditor(): void {
    global $db, $admin, $afile, $conf;
    $key = getVar('post', 'editor', 'var', $conf['editor']['admin'] ?? 'plain');
    $aid = (int)($admin[0] ?? 0);
    $raw = base64_decode($_SESSION[$conf['admin_c']] ?? '', true);
    if ($raw === false) {
        setRedirect($afile.'.php', true);
    }
    $part = explode(':', $raw, 4);
    if (count($part) !== 4) {
        setRedirect($afile.'.php', true);
    }
    if (!isValidEditor($key, 'admin')) {
        $key = $conf['editor']['admin'] ?? 'plain';
        if (!isValidEditor($key, 'admin')) $key = 'plain';
    }
    $part[3] = $key;
    unset($_SESSION[$conf['admin_c']]);
    $_SESSION[$conf['admin_c']] = base64_encode(implode(':', $part));
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_admins SET editor = :editor WHERE id = :id', ['editor' => $key, 'id' => $aid]);
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
        case 'check_admin': check_admin(); break;
    }
}
