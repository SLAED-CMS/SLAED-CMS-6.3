<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !defined('PUBLIC_DIR')) die('Illegal file access');
require_once BASE_DIR.'/core/system.php';
if (defined('UPDATE_FILE')) require_once BASE_DIR.'/admin/update.php';
getLang('admin');
Cache::setHeaders();
checkAccess();

# Check that a module exposes an editable configuration: a config file plus a config handler in its admin routing
function checkModuleConfig(string $name, int $typ): bool {
    if (!is_file(BASE_DIR.'/config/'.$name.'.php')) return false;
    $file = ($typ === 0) ? BASE_DIR.'/admin/modules/'.$name.'.php' : BASE_DIR.'/modules/'.$name.'/admin/index.php';
    if (!is_file($file)) return false;
    return strpos((string)file_get_contents($file), "case 'config'") !== false;
}

# Build one dashboard card or sidebar row of a section; a Node type opens its list and turns on in the types of Node, and the module node gets no site link
function getAdminMenu(string $name, array $sec, bool $open = false): string {
    global $panel, $afile, $tpl, $conf;
    $type = $sec['node'] ?? null;
    $mod = $conf['modules'][$name] ?? [];
    $url = $type ? $afile.'.php?name=node&type='.$name : $afile.'.php?name='.$name;
    $title = getModuleLabel($name);
    $icon = $sec['icon'];
    $off = $type ? !$type->active : empty($mod['active']);
    $rows = array_filter(getAdminQueues(), static fn(array $row): bool => $row['mod'] === $name);
    $queue = $rows ? $name : '';
    $qnum = array_sum(array_column($rows, 'num'));
    $ltitle = $off ? $title.' - '._DEACT : $title;
    $dial = [];
    $pinned = in_array($name, explode('-', getCookies('pins')), true);
    $pin = ['pin' => $name, 'is_pinned' => $pinned, 'title' => $pinned ? _ADM_UNPIN : _ADM_PIN, 'icon_name' => $pinned ? 'pin-angle-fill' : 'pin-angle'];
    if (!isAdmin(true)) $dial[] = $pin;
    if (isAdmin(true) && $type) {
        $dial[] = ['href' => $afile.'.php?name=node&op=type&type='.$name, 'title' => _FULLEDIT, 'icon_name' => 'pencil-square'];
        if ($type->settings['features']['categories']) $dial[] = ['href' => $afile.'.php?name=categories&modul='.$name, 'title' => _CATEGORIES, 'icon_name' => 'folder2'];
        if (!$off) $dial[] = ['href' => getSeoUrl(['name' => $name]), 'title' => _VIEWSITE, 'icon_name' => 'arrow-up-right-circle'];
        $dial[] = ['href' => $afile.'.php?name=node&op=info', 'title' => _MANUAL, 'icon_name' => 'info-circle'];
        $dial[] = $pin;
    } elseif (isAdmin(true)) {
        $typ = (int)($mod['type'] ?? 1);
        $dial[] = ['href' => $afile.'.php?name=modules&op=edit&mod='.$name, 'title' => _FULLEDIT, 'icon_name' => 'pencil-square'];
        if (checkModuleConfig($name, $typ)) $dial[] = ['href' => $url.'&op=config', 'title' => _PREFERENCES, 'icon_name' => 'gear'];
        if (in_array($name, getCategoryModules(), true)) $dial[] = ['href' => $afile.'.php?name=categories&modul='.$name, 'title' => _CATEGORIES, 'icon_name' => 'folder2'];
        if ($typ === 1 && !$off && $name !== 'node') $dial[] = ['href' => 'index.php?name='.$name, 'title' => _VIEWSITE, 'icon_name' => 'arrow-up-right-circle'];
        $dial[] = ['href' => $url.'&op=info', 'title' => _MANUAL, 'icon_name' => 'info-circle'];
        $dial[] = $pin;
        $dial[] = getTplPostAction(['name' => 'modules', 'op' => 'status', 'mod' => $name, 'act' => $off ? '1' : '0', 'refer' => 1], 'power', $off ? _ACTIVATE : _DEACTIVATE)
            + ['later' => 'flip '.($off ? 'on' : 'off')];
    }
    if ($panel) {
        $view = (int)($mod['view'] ?? 0);
        $who = ($view === 2) ? _MVADMIN : (($view === 1) ? _MVUSERS : _MVALL);
        return $tpl->getHtmlFrag('menu-grid-item', [
            'key' => $name,
            'url' => $url,
            'title' => $title,
            'title_attr' => $ltitle,
            'icon_name' => $icon,
            'subtitle' => $type ? $name : $name.' · '.$who,
            'is_off' => $off,
            'led_title' => $off ? _DEACT : _ACT,
            'dial' => $dial,
            'dial_title' => _FUNCTIONS,
            'queue_key' => $queue,
            'queue_num' => $qnum,
        ]);
    }
    return $tpl->getHtmlFrag('menu-list-item', [
        'key' => $name,
        'is_off' => $off,
        'link' => ['href' => $url, 'title' => $ltitle, 'icon_name' => $icon, 'label' => $title, 'is_current' => $open],
        'dial' => $dial,
        'dial_title' => _FUNCTIONS,
        'queue_key' => $queue,
        'queue_num' => $qnum,
        'is_drag' => true,
    ]);
}

# Build the sidebar menu blocks of a module screen: the panel modules for a super-admin, the site modules, the Node types, a row dragged only within its block
function getAdminPanelBlocks(): string {
    global $panel, $tpl, $name;
    if (!$panel) {
        $cont = $tcont = '';
        $block = '';
        $list = getAdminSectionList();
        $kind = ($name === 'node') ? getVar('get', 'type', 'var', '') : '';
        $cur = isset($list[$kind]) ? $kind : $name;
        if (isAdmin(true)) {
            foreach ($list as $key => $row) if ($row['is_panel']) $cont .= getAdminMenu($key, $row, $key === $cur);
            $block = $tpl->getHtmlPart('block-sidebar', [
                'title' => _ADMIN, 'icon_name' => 'person-gear', 'content_html' => $cont, 'id' => '1', 'close' => _OPCL, 'is_find' => true, 'is_drag' => true,
            ]);
            $cont = '';
        }
        foreach ($list as $key => $row) {
            if ($row['is_panel']) continue;
            if (isset($row['node'])) {
                $tcont .= getAdminMenu($key, $row, $key === $cur);
                continue;
            }
            $cont .= getAdminMenu($key, $row, $key === $cur);
            getLang($key, true);
        }
        $block .= $tpl->getHtmlPart('block-sidebar', [
            'title' => _MODULES, 'icon_name' => 'people', 'content_html' => $cont, 'id' => '2', 'close' => _OPCL, 'is_find' => true, 'is_drag' => true,
        ]);
        if ($tcont !== '') {
            $block .= $tpl->getHtmlPart('block-sidebar', [
                'title' => _ADM_TYPES, 'icon_name' => 'collection', 'content_html' => $tcont, 'id' => '4', 'close' => _OPCL, 'is_find' => true, 'is_drag' => true,
            ]);
        }
        return $block;
    }
    return '';
}

# Render the panel home: the update warning, the administrator notice, the cockpit of a super-administrator with its lamp drawn now and its rings by the poll, and the module grids
function getAdminPanel(): void {
    global $conf, $panel, $tpl, $adminvars, $afile;
    setHead();
    $adminvars['section'] = _HOME;
    $adminvars['is_home'] = true;
    $content = getUpdateAlert();
    if ($conf['admininfo']) $content .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => $conf['admininfo']]);
    if (isAdmin(true)) {
        $sigs = getSystemSignals();
        $tone = getCockpitTone($sigs);
        $every = getLiveEvery('panel');
        $flag = static fn(string $level): array => ['is_ok' => $level === 'ok', 'is_info' => $level === 'info', 'is_warn' => $level === 'warn', 'is_danger' => $level === 'danger'];
        $loud = count(array_filter($sigs, static fn(array $v): bool => $v['level'] !== 'ok'));
        $content .= $tpl->getHtmlPart('cockpit', [
            'url' => 'index.php?go=5&op=getCockpitData',
            'monitor_url' => HOME_PATH.$afile.'.php?name=monitor',
            'every' => $every,
            'tone' => $tone,
            'loud' => $loud,
            'pass' => count($sigs) - $loud,
            'total' => count($sigs),
            'passed' => sprintf(_ADM_PASSED, count($sigs)),
            'pass_of' => _ADM_PASSOF,
            'pass_text' => sprintf(_ADM_PASSOF, count($sigs) - $loud, count($sigs)),
            'signals' => array_map(static fn(array $v, int $i): array => $v + $flag($v['level']) + ['num' => $i], $sigs, array_keys($sigs)),
            'live_html' => $tpl->getHtmlFrag('live-chip', ['live_every' => $every]),
            'cap_icon' => 'display',
            'cap_text' => _ADM_STATE,
        ] + getCockpitParts() + $flag($tone));
    }
    if ($panel) {
        if (isAdmin(true)) {
            $items = [];
            foreach (getAdminSectionList() as $name => $row) if ($row['is_panel']) $items[] = getAdminMenu($name, $row);
            $content .= $tpl->getHtmlPart('dashboard-panel', [
                'panel_id' => 'sl_panel_admin',
                'title' => _MODADMINPANEL,
                'icon_name' => 'person-gear',
                'is_find' => true,
                'content_html' => $tpl->getHtmlPart('div', ['is_menu_grid' => true, 'content_html' => implode('', $items)]),
            ]);
        }
        $items = $types = [];
        foreach (getAdminSectionList() as $name => $row) {
            if ($row['is_panel']) continue;
            if (isset($row['node'])) {
                $types[] = getAdminMenu($name, $row);
                continue;
            }
            $items[] = getAdminMenu($name, $row);
            getLang($name, true);
        }
        $content .= $tpl->getHtmlPart('dashboard-panel', [
            'panel_id' => 'sl_panel_site',
            'title' => _MODSITEPANEL,
            'icon_name' => 'people',
            'is_find' => true,
            'content_html' => $tpl->getHtmlPart('div', ['is_menu_grid' => true, 'content_html' => implode('', $items)]),
        ]);
        if ($types) {
            $content .= $tpl->getHtmlPart('dashboard-panel', [
                'panel_id' => 'sl_panel_node',
                'title' => _ADM_TYPESPANEL,
                'icon_name' => 'collection',
                'is_find' => true,
                'content_html' => $tpl->getHtmlPart('div', ['is_menu_grid' => true, 'content_html' => implode('', $types)]),
            ]);
        }
    }
    echo $content;
    setFoot();
}

# Render the help of the panel itself, the shell every screen shares: every administrator reads it, a super administrator edits it
function getAdminPanelInfo(): void {
    global $afile;
    setTplAdminInfoPage([
        'base' => BASE_DIR.'/admin/info/panel',
        'ops' => ['', 'op=info'],
        'tabs' => [_HOME, _MANUAL],
        'action_url' => HOME_PATH.$afile.'.php?op=info',
    ]);
}

# Create an administrator while the admins table is empty, the way back into a panel without one; the password is hashed as typed, as admins and the login take it
function addAdminAccount(): void {
    global $db, $afile, $conf, $stop, $prv;
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_admins LIMIT 1')) == 0) {
        $aname     = getVar('post', 'aname', 'name', '');
        $aurl      = filterCutText(getVar('post', 'aurl', 'url', ''), 255);
        $aemail    = filterCutText(filterText(getVar('post', 'aemail', 'raw', '')), 255);
        $apass = getVar('post', 'apwd', 'raw', '');
        $atwo = getVar('post', 'apwd2', 'raw', '');
        $auser_new = getVar('post', 'auser_new', 'num', 0);
        $aeditor   = (string)($conf['editor']['admin'] ?? 'plain');
        if (!Editor::isValidEditor($aeditor, 'admin')) $aeditor = 'plain';
        $alang     = getCookies('language');
        if (!isset(getLanguageList()[$alang])) $alang = '';
        $aip       = getip();
        $stop = [];
        if (!checkAdminPost('add_admin')) $stop[] = _TOKENMISS;
        if (!$aname || !analyze_name($aname)) $stop[] = _ERRORINVNICK;
        if (($rule = checkPassRule($apass, $atwo)) !== '') $stop[] = $rule;
        if (strlen($aname) > 25) $stop[] = _NICKLONG;
        if (!$stop) {
            $apwd = getPassHash($apass);
            $db->getSqlQuery(
                'INSERT INTO '.PREFIX_DB.'_admins VALUES (NULL, :name, \'Admin\', :url, :email, :pass, \'1\', :editor, \'1\', \'\', :lang, :ip, now(), now())',
                ['name' => $aname, 'url' => $aurl, 'email' => ($aemail !== '') ? $aemail : null, 'pass' => $apwd, 'editor' => $aeditor, 'lang' => $alang, 'ip' => $aip]
            );
            $aid = intval($db->getSqlLastId());
            $fail = '';
            if ($auser_new == 1) {
                $urow = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_users WHERE name = :name', ['name' => $aname]));
                $uid = ($urow) ? intval($urow['id']) : 0;
                if ($uid) {
                    $gone = $db->setSqlBegin()
                        && $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_users WHERE id = :id', ['id' => $uid]) !== false
                        && (new Route($db))->deleteRoute('user', $uid)
                        && $prv->deleteUser($uid)
                        && $db->setSqlCommit();
                    if (!$gone) $db->setSqlRollback();
                }
                $open = $db->setSqlBegin();
                $made = $open && $db->getSqlQuery(
                    'INSERT INTO '.PREFIX_DB.'_users (id, name, email, website, avatar, regdate, password, lang, ip, sig, block, warnings, field)'
                    .' VALUES (NULL, :name, :email, :website, :avatar, now(), :pass, :lang, :ip, \'\', \'\', \'\', \'\')',
                    ['name' => $aname, 'email' => ($aemail !== '') ? $aemail : null, 'website' => $aurl, 'avatar' => '', 'pass' => $apwd, 'lang' => $alang, 'ip' => $aip]
                ) !== false;
                if (!$made) $fail = ($db->getSqlTwin() === 'email') ? _ERROR_EMAIL : _ERROR;
                $made = $made && (new Route($db))->addRoute('user', intval($db->getSqlLastId()), 'users', $aname) !== '' && $db->setSqlCommit();
                if ($open && !$made) $db->setSqlRollback();
                if (!$made && $fail === '') $fail = _ERROR;
            }
            $fail = implode(' ', array_filter([$fail, ($aid > 0) ? addNodeProfiles($aid) : '']));
            setRedirect($afile.'.php', false, 302, $fail, $fail !== '');
        } else {
            getAdminLoginForm();
        }
    } else {
        setRedirect($afile.'.php');
    }
}

# Authorize one login; a stored hash that matches only the old cut, trimmed and escaped form still opens, so no working login is lost, and an md5 one is renewed
function checkAdminLogin(): void {
    global $db, $afile, $conf, $stop;
    $block = checkCaptcha('adminlogin');
    if ($block) $stop = _SECCODEINCOR;
    $name = htmlspecialchars(trim(substr($_POST['name'] ?? '', 0, 25)));
    $pwd = str_replace("\0", '', getVar('post', 'pwd', 'raw', ''));
    $old = htmlspecialchars(trim(substr($pwd, 0, 25)));
    if (!$name || $pwd === '') $stop = _LOGININCOR;
    if (Captcha::isLoginLimited('admin', $name)) [$stop, $block] = [_LOGINLIMIT, true];
    $aid = $aname = $apwd = $aeditor = null;
    $back = false;
    if ($name) {
        $result = $db->getSqlQuery('SELECT id, name, password, editor FROM '.PREFIX_DB.'_admins WHERE name = :name', ['name' => $name]);
        if ($db->getSqlRowCount($result) == 1) {
            [$aid, $aname, $apwd, $aeditor] = $db->getSqlRow($result);
        }
    }
    $lid = ($aname === $name) ? intval($aid) : 0;
    if (!$stop) {
        $good = $aid && $aname === $name && checkPassHash($pwd, (string)$apwd);
        $back = !$good && $aid && $aname === $name && $old !== $pwd && checkPassHash($old, (string)$apwd);
        if (!$good && !$back) $stop = _LOGININCOR;
    }
    if (!$stop) {
        if (strlen((string)$apwd) === 32 && ctype_xdigit((string)$apwd)) {
            $hash = getPassHash($back ? $old : $pwd);
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_admins SET password = :pass WHERE id = :id', ['pass' => $hash, 'id' => $aid]);
            $apwd = $hash;
        }
        unset($_SESSION[$conf['admin_c']]);
        session_regenerate_id(true);
        $info = base64_encode($aid.':'.$aname.':'.$apwd.':'.$aeditor);
        $_SESSION[$conf['admin_c']] = $info;
        $_SESSION[$conf['admin_c'].'-time'] = time();
        $ip = getip();
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :ip', ['ip' => $ip]);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_admins SET ip = :ip, lastvis = now() WHERE id = :id', ['ip' => $ip, 'id' => $aid]);
        Captcha::clearLoginFailures('admin', $name);
        addLoginLog($lid, true, 'ok', $name);
        setRedirect(getBackUrl() ?: $afile.'.php');
    } else {
        Captcha::registerLoginFailure('admin', $name);
        addLoginLog($lid, true, $block ? 'blocked' : 'password', $name);
        getAdminLoginForm();
    }
}

# Render the panel login page, or the first-administrator form while the table is still empty
function getAdminLoginForm(): void {
    global $db, $afile, $conf, $stop, $tpl;
    setHead();
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_admins')) == 0) {
        $cont = ($stop) ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]) : '';
        $cont .= $tpl->getHtmlPart('auth-form', [
            'route' => $afile,
            'rows' => [
                [
                    'label' => _NICKNAME,
                    'field_html' => $tpl->getHtmlFrag('input', [
                        'itype' => 'text',
                        'name_attr' => 'aname',
                        'value_attr' => getVar('post', 'aname', 'var'),
                        'maxlength_num' => 25,
                        'placeholder_text' => _NICKNAME,
                        'is_required' => true,
                    ]),
                ],
                [
                    'label' => _HOMEPAGE,
                    'field_html' => $tpl->getHtmlFrag('input', [
                        'name_attr' => 'aurl',
                        'value_attr' => 'http://'.getHost(),
                        'maxlength_num' => 255,
                        'placeholder_text' => _HOMEPAGE,
                        'is_required' => true,
                    ]),
                ],
                [
                    'label' => _EMAIL,
                    'field_html' => $tpl->getHtmlFrag('input', [
                        'itype' => 'email',
                        'name_attr' => 'aemail',
                        'value_attr' => getVar('post', 'aemail', 'text'),
                        'maxlength_num' => 255,
                        'placeholder_text' => _EMAIL,
                        'is_required' => true,
                    ]),
                ],
                [
                    'label' => _PASSWORD,
                    'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'password', 'name_attr' => 'apwd', 'placeholder_text' => _PASSWORD, 'is_required' => true,
                        'autocomplete_attr' => 'new-password']),
                ],
                [
                    'label' => _RETYPEPASSWORD,
                    'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'password', 'name_attr' => 'apwd2', 'placeholder_text' => _RETYPEPASSWORD, 'is_required' => true,
                        'autocomplete_attr' => 'new-password']),
                ],
                [
                    'label' => _CREATEUSERDATA,
                    'field_html' => $tpl->getHtmlFrag('radio', [
                        'name_attr' => 'auser_new',
                        'value_attr' => '1',
                        'label_text' => _YES,
                        'is_checked' => true,
                    ]).' '.$tpl->getHtmlFrag('radio', ['name_attr' => 'auser_new', 'value_attr' => '0', 'label_text' => _NO]),
                ],
            ],
            'hidden' => ['name_attr' => 'op', 'value_attr' => 'add_admin'],
            'token' => ['name_attr' => 'token', 'value_attr' => getSiteToken('add_admin')],
            'submit' => ['button_type' => 'submit', 'submit_label' => _SEND, 'icon_name' => 'send'],
        ]);
    } else {
        $cont = ($stop) ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]) : '';
        $capt = getCaptcha('adminlogin');
        $rows = [
            [
                'label' => _NICKNAME,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'itype' => 'text',
                    'name_attr' => 'name',
                    'maxlength_num' => 25,
                    'placeholder_text' => _NICKNAME,
                    'autocomplete_attr' => 'username',
                    'is_required' => true,
                ]),
            ],
            [
                'label' => _PASSWORD,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'name_attr' => 'pwd',
                    'placeholder_text' => _PASSWORD,
                    'autocomplete_attr' => 'current-password',
                    'is_required' => true,
                ]),
            ],
        ];
        if ($capt) $rows[] = ['field_html' => $capt];
        $cont .= $tpl->getHtmlPart('auth-form', [
            'route' => $afile,
            'rows' => $rows,
            'hidden' => ['name_attr' => 'op', 'value_attr' => 'check_admin'],
            'back' => ['name_attr' => 'back', 'value_attr' => getBackUrl()],
            'submit' => ['button_type' => 'submit', 'submit_label' => _LOGIN, 'icon_name' => 'box-arrow-in-right'],
        ]);
    }
    echo $cont;
    setFoot();
}

# Store one answer the page has already applied, so the reply is a status: shell states, menu pins and list view as cookies, the editor on the row and in the session
function setAdminOption(): void {
    global $db, $admin, $conf;
    if (!checkAdminPost('option')) {
        http_response_code(403);
        exit;
    }
    $key = getVar('post', 'key', 'var', '');
    $val = getVar('post', 'value', 'var', '');
    $part = explode(':', base64_decode($_SESSION[$conf['admin_c']] ?? '', true), 4);
    $keys = in_array($key, ['pins', 'menu'], true) ? array_unique(array_filter(explode('-', $val))) : [];
    if (!in_array($key, ['mode', 'still', 'rail', 'dock-off', 'dock-site-off', 'density', 'rows', 'pins', 'menu', 'editor'], true)
        || ($key === 'editor' && (count($part) !== 4 || !Editor::isValidEditor($val, 'admin')))
        || count($keys) > (($key === 'pins') ? 8 : count($conf['modules']) + count($conf['node']['types'] ?? [])) || preg_grep('#[^a-z0-9_]#', $keys)) {
        setAdminLog(['result' => 'refused']);
        http_response_code(400);
        exit;
    }
    if ($key === 'mode') {
        setThemeMode($val);
    } elseif ($key === 'density') {
        if (in_array($val, ['full', 'compact', 'cards'], true)) setCookies($key, time() + 31536000, $val);
        else setCookiesDelete($key);
    } elseif ($key === 'rows') {
        if ($val === 'all' || (string)max(5, min(LIST_MAX, intval($val))) === $val) setCookies($key, time() + 31536000, $val);
        else setCookiesDelete($key);
    } elseif ($key === 'pins' || $key === 'menu') {
        if ($keys) setCookies($key, time() + 31536000, implode('-', $keys));
        else setCookiesDelete($key);
    } elseif ($key !== 'editor') {
        if ($val === '1') setCookies($key, time() + 31536000, '1');
        else setCookiesDelete($key);
    } else {
        $part[3] = $val;
        $_SESSION[$conf['admin_c']] = base64_encode(implode(':', $part));
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_admins SET editor = :editor WHERE id = :id', ['editor' => $val, 'id' => (int)($admin[0] ?? 0)]);
    }
    setAdminLog(['result' => 'done']);
    http_response_code(204);
    exit;
}

# Keep the panel session from its end window: the request itself is the action core/security.php counts, so the answer is a status
function updateAdminSession(): void {
    if (!checkAdminPost('keep')) {
        http_response_code(403);
        exit;
    }
    setAdminLog(['result' => 'done']);
    http_response_code(204);
    exit;
}

# End the panel session: drop the session row, forget the administrator, and hand the browser back to the login page
function deleteAdminSession(): void {
    global $db, $admin, $afile, $conf;
    if (!checkAdminPost('logout')) setRedirect($afile.'.php', false, 302, _TOKENMISS, true);
    $aname = filterText(substr($admin[1], 0, 25), 1);
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE uname = :name AND guest = :guest', ['name' => $aname, 'guest' => '3']);
    unset($_SESSION[$conf['admin_c']], $_SESSION[$conf['admin_c'].'-time'], $admin);
    session_regenerate_id(true);
    setRedirect($afile.'.php');
}

# The language row of the settings window posts here; the choice is stored and the POST turns back into a GET, so the redirected request is the one that renders in the new locale
function setAdminLanguage(): void {
    global $afile;
    setLangChoice();
    setRedirect($afile.'.php', true);
}

if (isAdmin()) {
    $name = getVar('req', 'name', 'var');
    $op = getVar('req', 'op', 'var', 'show');
    $panel = (empty($name)) ? 1 : 0;
    $id = getVar('req', 'id', 'num');
    $act = getVar('req', 'act', 'num');
    $pagetitle = $conf['defis'].' '._ADMINMENU;
    if ($op == 'option') {
        setAdminOption();
    } elseif ($op == 'newlang') {
        setAdminLanguage();
    } elseif ($op == 'keep') {
        updateAdminSession();
    } elseif ($op == 'logout') {
        deleteAdminSession();
    } elseif ($panel && $op == 'info') {
        getAdminPanelInfo();
    } elseif ($panel) {
        getAdminPanel();
    } else {
        $found = false;
        if (isAdmin(true)) {
            $module_file = BASE_DIR.'/admin/modules/'.$name.'.php';
            if (file_exists($module_file)) {
                require_once $module_file;
                $found = true;
            }
        }
        if (isset($conf['modules'][$name])) {
            if (isAdmin(true) || is_admin_modul($name)) {
                $path = BASE_DIR.'/modules/'.$name.'/admin';
                if (file_exists($path.'/index.php')) {
                    getLang($name, true);
                    $made = is_file(BASE_DIR.'/modules/'.$name.'/sql/table.sql')
                        ? getSqlFileTables((string)file_get_contents(BASE_DIR.'/modules/'.$name.'/sql/table.sql'), 'CREATE') : [];
                    if ($made && !checkSqlTable($made[0])) {
                        setHead();
                        echo $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => sprintf(_DB_NOTABLES, _MODULES, _DB_INSTALL)]);
                        setFoot();
                    } else {
                        require_once $path.'/index.php';
                    }
                    $found = true;
                }
            }
        }
        if (!$found) setError(404);
    }
} else {
    $home = 1;
    $op = getVar('post', 'op', 'var');
    switch($op) {
        default: getAdminLoginForm(); break;
        case 'add_admin': addAdminAccount(); break;
        case 'check_admin': checkAdminLogin(); break;
    }
}
