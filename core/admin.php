<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') && !defined('SETUP_FILE')) die('Illegal file access');

# Format statistic image
function getStatistic(): void {
 global $conf;
    $report = getVar('get', 'report', 'num', 0);
    $day = getVar('get', 'day', 'num', 15);
    $file = getVar('get', 'file', 'text', '');
    $off = 1;

    if (!$report) header('Content-type: image/png');
    $image = imagecreate(800, 340);

    $white = imagecolorallocate($image, 255, 255, 255);
    $red = imagecolorallocate($image, 255, 0, 0);
    $green = imagecolorallocate($image, 0, 128, 0);
    $purple = imagecolorallocate($image, 200, 0, 200);
    $black = imagecolorallocate($image, 0, 0, 0);
    $wblue = imagecolorallocate($image, 34, 122, 199);
    $wgreen = imagecolorallocate($image, 44, 135, 16);
    $gray = imagecolorallocate($image, 203, 218, 226);
    $yellow = imagecolorallocate($image, 207, 179, 31);
    $llgray = imagecolorallocate($image, 250, 250, 250);

    imagefilledrectangle($image, 0, 252, 800, 340, $llgray);

    $daysLog = COUNTER_DIR.'/days.log';
    $statLog = COUNTER_DIR.'/statistic.log';
    if ($report) {
        $f = (file_exists($daysLog) && is_readable($daysLog)) ? file($daysLog) : ((file_exists($statLog) && is_readable($statLog)) ? file($statLog) : []);
    } else {
        if ($file) {
            $path = COUNTER_DIR.'/statistic/'.$file;
            $f = (is_file($path) && is_readable($path)) ? file($path) : [];
        } else {
            if (file_exists($daysLog) && is_readable($daysLog)) {
                $f = file($daysLog);
                $stat = (file_exists($statLog) && is_readable($statLog)) ? file($statLog) : false;
                if ($stat !== false) $f = array_merge($f, $stat);
            } else {
                $f = (file_exists($statLog) && is_readable($statLog)) ? file($statLog) : [];
            }
        }
    }
    $f = ($f !== false) ? $f : [];
    $f = array_values(array_filter(array_map(static function ($row) {
        $row = trim((string)$row);
        if ($row === '') {
            return null;
        }

        $parts = explode('|', $row);
        return (count($parts) >= 8) ? implode('|', array_slice($parts, 0, 8)) : null;
    }, $f)));
    $to = count($f);
    if ($day > 15) {
        $from = 0;
        $to = min(15, $to);
    } else {
        $from = (!$file && date('d') <= 15) ? 0 : 15;
        if ($from < 0) $from = 0;
        if ($from > $to) $from = $to;
    }
    $regusers = $unique = $today = $engines = $sites = $homepage = $auditory = $max1 = $max2 = 0;
    for ($i = $from; $i < $to; $i++) {
        $day = explode('|', $f[$i]);
        if ($day[1] > $max1) $max1 = $day[1];
        if ($day[2] > $max2) $max2 = $day[2];
        $unique = $unique + $day[1];
        $today = $today + $day[2];
        $engines = $engines + $day[4];
        $sites = $sites + $day[5];
        $homepage = $homepage + $day[6];
        $auditory = $auditory + $day[1] - ($day[4] + $day[5]);
        if ($auditory < 0) $auditory = 0;
        $regusers = $regusers + $day[7];
    }
    $i = 0;
    for ($z = $from; $z < $to; $z++) {
        $day = explode('|', $f[$z]);
        if ($day[2] != '') {
            $w = ($max2 > 0) ? round((230 / $max2) * $day[2]) : 0;
            if ($w < 4) $w = 4;
            $off = 134;
            imagefilledrectangle($image, $off+$conf['statistic']['bet']*$i+1, 250-$w+1, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi'], 249, $yellow);
            imagerectangle($image, $off+$conf['statistic']['bet']*$i, 250-$w, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi'], 249, $black);
            imagerectangle(
                $image,
                $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+1,
                250-$w+3,
                $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+2,
                249,
                $gray
            );
            $w = ($max1 > 0) ? round((230 / $max1) * $day[1]) : 0;
            if ($w < 5) $w = 1;
            $off = 120;

            imagefilledrectangle($image, $off+$conf['statistic']['bet']*$i+1, 250-$w+1, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+3, 249, $wblue);
            imagerectangle($image, $off+$conf['statistic']['bet']*$i,250-$w, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+3, 249, $black);
            imagerectangle(
                $image,
                $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+4,
                250-$w+4,
                $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+5,
                249,
                $black
            );
            $zzz = $day[1] - ($day[4] + $day[5]);
            $w = ($max1 > 0) ? round((230 / $max1) * $zzz) : 0;
            if ($w < 4) $w = $w + 31;

            imagefilledrectangle($image, $off+$conf['statistic']['bet']*$i+1, 250-$w+1, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+3, 249, $wgreen);
            imagerectangle($image, $off+$conf['statistic']['bet']*$i, 250-$w, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+3, 249, $black);
            imagestring($image, 1, $off+$conf['statistic']['bet']*$i+2, 250-$w+1-10, $day[1], $white);

            $d = explode('.', $day[0]);
            $d = $d[0].'.'.$d[1];

            imagestring($image, 1, $off+$conf['statistic']['bet']*$i+1, 255, $d, $wblue);
            imagestring($image, 1, $off+$conf['statistic']['bet']*$i+1, 265, $day[1], $red);
            imagestring($image, 1, $off+$conf['statistic']['bet']*$i+1, 275, $day[2], $green);
            imagestring($image, 1, $off+$conf['statistic']['bet']*$i+1, 285, $day[6], $purple);

            imagestring($image, 1, $off+$conf['statistic']['bet']*$i+1, 300, $day[5], $wblue);
            imagestring($image, 1, $off+$conf['statistic']['bet']*$i+1, 310, $day[4], $red);
            imagestring($image, 1, $off+$conf['statistic']['bet']*$i+1, 320, $zzz, $green);
            imagestring($image, 1, $off+$conf['statistic']['bet']*$i+1, 330, rtrim($day[7]), $purple);

            imagestring($image, 1, 3, 255, 'DATE:', $wblue);
            imagestring($image, 1, 3, 265, 'UNIQUE VISITORS:', $red);
            imagestring($image, 1, 3, 275, 'SITE HITS:', $green);
            imagestring($image, 1, 3, 285, 'HOMEPAGE HITS:', $purple);

            imagestring($image, 1, 3, 300, 'OTHER SITES:', $wblue);
            imagestring($image, 1, 3, 310, 'SEARCH ENGINES:', $red);
            imagestring($image, 1, 3, 320, 'AUDIENCE:', $green);
            imagestring($image, 1, 3, 330, 'REGISTERED USERS:', $purple);
        }
        $i++;
    }

    imagefilledrectangle($image, 5, 170, 20, 180, $wblue);
    imagerectangle($image, 5, 170, 20, 180, $black);
    imagestring($image, 1, 25, 171, 'UNIQUE VISITORS', $black);

    imagefilledrectangle($image, 5, 185, 20, 195, $wgreen);
    imagerectangle($image, 5, 185, 20, 195, $black);
    imagestring($image, 1, 25, 186, 'SITE AUDIENCE', $black);

    imagefilledrectangle($image, 5, 200, 20, 210, $yellow);
    imagerectangle($image, 5, 200, 20, 210, $black);
    imagestring($image, 1, 25, 202, 'SITE HITS', $black);

    imagerectangle($image, 0, 296, 799, 339, $gray);
    imagerectangle($image, 0, 252, 800, 252, $gray);
    imagerectangle($image, 0, 0, 799, 339, $gray);

    imagestring($image, 1, 5, 5, 'VISITS BY DAYS FOR '.strtoupper($conf['homeurl']).' BY SLAED CMS '.$conf['version'].' - '.date(_TIMESTRING), $wblue);

    imagestring($image, 1, 5, 30, 'UNIQUES TOTAL: '.$unique, $red);
    imagestring($image, 1, 5, 40, 'HITS TOTAL: '.$today, $green);
    imagestring($image, 1, 5, 50, 'HOMEPAGE HITS: '.$homepage, $purple);

    imagestring($image, 1, 5, 70, 'OTHER SITES: '.$sites, $wblue);
    imagestring($image, 1, 5, 80, 'SEARCH ENGINES: '.$engines, $red);
    imagestring($image, 1, 5, 90, 'AUDIENCE: '.$auditory, $green);
    imagestring($image, 1, 5, 100, 'REG. USERS: '.$regusers, $purple);

    imagestring($image, 1, 5, 120, 'PAGES PER VIS.: '.(($unique > 0) ? round($today / $unique, 2) : 0), $wblue);
    imagestring($image, 1, 5, 130, 'AVR. AUDIENCE: '.(($i > 0) ? round($auditory / $i) : 0), $wblue);

    $sdir = COUNTER_DIR.'/statistic';
    if ($report && !is_dir($sdir) && !mkdir($sdir, 0755, true)) $report = 0;
    if ($report) {
        imagepng($image, $sdir.'/'.date('m-Y').'.png');
    } else {
        imagepng($image);
    }
    exit;
}

# Authenticate and IP address check
function checkAccess() {
    global $conf;
    if ($conf['security']['admin_ip'] != '') {
        $admin_ip = explode(',', $conf['security']['admin_ip']);
        $temp_ip = getIp();
        foreach ($admin_ip as $val) {
            $ruleIp = trim($val);
            if ($ruleIp !== '' && getIpMatch($temp_ip, $ruleIp)) {
                $ip_check = true;
                break;
            } else {
                $ip_check = false;
            }
        }
        if (!$ip_check) setExit(_AUTH_ERROR_IP);
    }
    if (!empty($conf['security']['login']) && !empty($conf['security']['password'])) {
        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) setUnauthorized();
        if (!password_verify($_SERVER['PHP_AUTH_USER'], $conf['security']['login']) || !password_verify($_SERVER['PHP_AUTH_PW'], $conf['security']['password'])) setUnauthorized();
    }
}

# Denial of Authenticate
function setUnauthorized() {
    header('WWW-Authenticate: Basic realm="SLAED CMS"');
    header('HTTP/1.0 401 Unauthorized');
    setExit(_LOGININCOR);
}

# The locales the tree ships, as code => translated name; the flag links of the plain panel and the language row of the settings window read one list
function getLanguageList(): array {
    $list = [];
    foreach (scandir(BASE_DIR.'/lang') ?: [] as $file) {
        if (!preg_match('#^(.+)\.php$#', $file, $part)) continue;
        $list[$part[1]] = getLangName($part[1]);
    }
    return $list;
}

# Render one settings row captioned by its field: the language posts newlang and reloads, the menu edge, sound and screensaver stay in the browser, the rest go to op option
function getAdminSettingsRow(string $axis): string {
    global $admin, $conf, $afile, $locale, $tpl;
    $list = [];
    if ($axis === 'mode') {
        $list = ['light' => _MODE_LIGHT, 'auto' => _MODE_AUTO, 'dark' => _MODE_DARK];
        $row = ['icon' => 'circle-half', 'title' => _THEME, 'now' => getThemeMode()];
    } elseif ($axis === 'still') {
        $list = ['0' => _ON, '1' => _OFF];
        $row = ['icon' => 'magic', 'title' => _ADM_MOTION, 'now' => isThemeFlag('still') ? '1' : '0'];
    } elseif ($axis === 'sound') {
        $list = ['1' => _ON, '0' => _OFF];
        $row = ['icon' => 'volume-up', 'title' => _ADM_SOUND, 'now' => '1'];
    } elseif ($axis === 'saver') {
        $list = ['0' => _OFF];
        foreach ([1, 5, 15] as $min) $list[$min] = sprintf(getPluralForm($min, _ADM_SAVERIN), $min);
        $row = ['icon' => 'fire', 'title' => _ADM_SAVER, 'now' => '1'];
    } elseif ($axis === 'dock-off') {
        $list = ['0' => _ON, '1' => _OFF];
        $row = ['icon' => 'capsule', 'title' => _ADM_DOCK, 'now' => isThemeFlag('dock-off') ? '1' : '0'];
    } elseif ($axis === 'dock-site-off') {
        $list = ['0' => _ON, '1' => _OFF];
        $row = ['icon' => 'globe2', 'title' => _ADM_DOCKSITE, 'now' => isThemeFlag('dock-site-off') ? '1' : '0'];
    } elseif ($axis === 'dock-side') {
        $list = ['' => _ADM_DOCKFREE, 'top' => _TOP, 'right' => _RIGHT, 'bottom' => _BOTTOM, 'left' => _LEFT];
        $row = ['icon' => 'arrows-move', 'title' => _ADM_DOCKPOS, 'now' => 'bottom'];
    } elseif ($axis === 'editor') {
        $row = ['icon' => getIconName('editor'), 'title' => _EDITOR, 'now' => (string)($admin[3] ?? $conf['editor']['admin'] ?? 'plain')];
    } else {
        if (($conf['multilingual'] ?? 0) != 1) return '';
        $list = getLanguageList();
        $row = ['icon' => getIconName('language'), 'title' => _LANGUAGE, 'now' => $locale];
    }
    $op = $axis === 'language' ? 'newlang' : 'option';
    $field = $axis === 'language' ? 'newlang' : 'value';
    $pid = 'sl-set-'.$axis;
    if ($axis === 'editor') {
        $pick = Editor::getSelect($field, $row['now'], 'content', 'admin', 'id="'.$pid.'"');
    } else {
        if (!$list) return '';
        $opts = '';
        foreach ($list as $val => $label) {
            $opts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $val, 'label_text' => $label, 'is_selected' => (string)$val === $row['now'], 'is_hidden' => $val === '']);
        }
        $pick = $tpl->getHtmlFrag('select', ['name_attr' => $field, 'select_class' => '', 'selectid' => $pid, 'options_html' => $opts]);
    }
    $hide = in_array($axis, ['dock-side', 'sound', 'saver'], true) ? [] : ['op' => $op, 'token' => getSiteToken($op)] + ($op === 'option' ? ['key' => $axis] : ['refer' => '1']);
    $html = '';
    foreach ($hide as $key => $val) $html .= $tpl->getHtmlFrag('hidden', ['name_attr' => $key, 'value_attr' => $val, 'input_attr' => '']);
    return $tpl->getHtmlFrag('settings-row', [
        'action' => $afile.'.php',
        'hidden' => $html,
        'axis' => $axis,
        'is_mode' => $axis === 'mode',
        'icon_name' => $row['icon'],
        'title' => $row['title'],
        'pick_id' => $pid,
        'pick_html' => $pick,
    ]);
}

# Render the settings window of the panel: the answers an administrator changes from any screen, applied in place except the language, which reloads
function getAdminSettingsWindow(): string {
    global $tpl, $afile;
    $rows = '';
    foreach (['mode', 'still', 'sound', 'saver', 'dock-off', 'dock-site-off', 'dock-side', 'editor', 'language'] as $axis) $rows .= getAdminSettingsRow($axis);
    return $tpl->getHtmlFrag('window', [
        'win_id' => 'sl-settings',
        'size_class' => 'sl-modal-sm',
        'icon_name' => 'sliders',
        'title_text' => _FUNCTIONS,
        'close_text' => _CLOSE,
        'body_html' => $rows,
        'foot_html' => $tpl->getHtmlFrag('window-foot-options', ['help_url' => HOME_PATH.$afile.'.php?op=info', 'help_text' => _MANUAL]),
    ]);
}

# Render the icon picker window: one window for every screen that offers an icon field, so the four callers name only the screen they stand on
function getAdminIconWindow(): string {
    global $tpl;
    return $tpl->getHtmlFrag('window', [
        'win_id' => 'sl-icon-window',
        'size_class' => 'sl-modal-lg',
        'icon_name' => 'grid-3x3-gap-fill',
        'title_text' => _ICONPICK,
        'close_text' => _CLOSE,
        'bar_html' => $tpl->getHtmlFrag('window-bar-icons', ['search_text' => _SEARCH]),
        'is_flush' => true,
        'body_html' => $tpl->getHtmlFrag('window-body-icons', []),
    ]);
}

# Render the standard admin top menu
function getAdminTopMenu(): string {
    global $admin, $afile, $tpl;
    $items = !isAdmin(true) ? [
        ['href' => '#', 'label' => _HELLO.', '.substr((string)($admin[1] ?? ''), 0, 25).'!', 'blank' => false, 'icon' => getIconName('profile')],
        ['href' => $afile.'.php', 'label' => _HOME, 'blank' => false, 'icon' => getIconName('home')],
        ['href' => '/', 'label' => _SITE, 'blank' => true, 'icon' => getIconName('site'), 'split' => true],
        ['href' => 'index.php?name=account', 'label' => _ACCOUNT, 'blank' => true, 'icon' => getIconName('account')],
        ['href' => $afile.'.php?op=logout', 'label' => _LOGOUT, 'blank' => false, 'icon' => getIconName('logout'), 'split' => true],
    ] : [
        ['href' => $afile.'.php', 'label' => _HOME, 'blank' => false, 'icon' => getIconName('home')],
        ['href' => $afile.'.php?name=blocks', 'label' => _BLOCKS, 'blank' => false, 'icon' => getIconName('blocks')],
        ['href' => $afile.'.php?name=modules', 'label' => _MODULES, 'blank' => false, 'icon' => getIconName('modules')],
        ['href' => $afile.'.php?name=categories', 'label' => _CATEGORIES, 'blank' => false, 'icon' => getIconName('categories')],
        ['href' => '/', 'label' => _SITE, 'blank' => true, 'icon' => getIconName('site'), 'split' => true],
        ['href' => 'index.php?name=account', 'label' => _ACCOUNT, 'blank' => true, 'icon' => getIconName('account')],
        ['href' => $afile.'.php?op=logout', 'label' => _LOGOUT, 'blank' => false, 'icon' => getIconName('logout'), 'split' => true],
    ];
    $html = '';
    foreach ($items as $item) {
        $html .= $tpl->getHtmlFrag('list-item', ['is_split' => !empty($item['split']), 'content_html' => $tpl->getHtmlFrag('link', [
            'href' => (string)$item['href'],
            'title' => (string)$item['label'],
            'icon_name' => (string)($item['icon'] ?? ''),
            'label' => (string)$item['label'],
            'is_label_strong' => true,
            'is_blank' => !empty($item['blank']),
        ])]);
    }
    return $html;
}

# Return standard variables used by admin layouts
function getAdminLayoutVars(): array {
    global $db, $afile, $tpl, $panel;
    if (!isAdmin()) {
        $login = ($db->getSqlRowCount($db->getSqlQuery('SELECT 1 FROM '.PREFIX_DB.'_admins LIMIT 1')) == 0) ? _ADMINLOGIN_NEW : _ADMINLOGIN;
        return ['login' => $login];
    }
    $rows = getAdminQueues();
    $bell = ['rows' => $rows, 'sum' => array_sum(array_column($rows, 'num')), 'url' => 'index.php?go=5&op=getAdminQueueData&token='.getSiteToken()];
    return [
        'settings_win' => getAdminSettingsWindow(),
        'dock_html' => getAdminDock(),
        'menu' => getAdminTopMenu(),
        'bell_html' => $tpl->getHtmlFrag('bell', $bell),
        'admin_blocks' => getAdminPanelBlocks().getAdminInfo().($panel ? adminblock() : ''),
        'help_url' => HOME_PATH.$afile.'.php?op=info',
        'sess_max' => getAdminIdle(),
        'sess_url' => $afile.'.php',
        'sess_token' => getSiteToken('keep'),
    ];
}

# Render the waiting block of the home: every queue of getAdminQueues(), the waiting ones first and the empty ones green below
function getAdminInfo(): string {
    global $panel, $tpl;
    if (!$panel || !isAdmin()) return '';
    $rows = '';
    foreach (getAdminQueues() as $row) $rows .= $tpl->getHtmlFrag('block-sidebar-count-row', $row);
    if ($rows === '') return '';
    return $tpl->getHtmlPart('block-sidebar', ['title' => _ADM_WAIT, 'icon_name' => 'bell', 'content_html' => $tpl->getHtmlFrag('block-content', [
        'is_sidebar_count_list' => true, 'content' => $rows]), 'id' => '3', 'close' => _OPCL, 'is_queue' => true]);
}

# Answer the poll of the bell: the number of every queue this administrator may open, with the module whose chip it feeds, and the seconds the session has left
function getAdminQueueData(): void {
    global $conf;
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'left' => getAdminIdle() - time() + intval($_SESSION[$conf['admin_c'].'-time'] ?? 0),
        'rows' => array_map(static fn(array $row): array => ['key' => $row['key'], 'mod' => $row['mod'], 'num' => $row['num']], getAdminQueues()),
    ]);
}

# Answer "All sections" of the menu on the site: the sections this administrator may open in the two groups of the panel menu
function getAdminSectionData(): void {
    $out = [];
    foreach (getAdminSectionList() as $key => $row) {
        $grp = $row['is_panel'] ? 0 : 1;
        $out[$grp] ??= ['title' => $row['is_panel'] ? _ADMIN : _MODULES, 'rows' => []];
        $out[$grp]['rows'][] = ['key' => $key, 'href' => $row['href'], 'label' => getModuleLabel($key), 'icon' => $row['icon']];
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array_values($out));
}

# Return the database server version string reported by the SQL engine
function getDbVersion() {
    global $db;
    [$dbv] = $db->getSqlRow($db->getSqlQuery('SELECT VERSION()'));
    return $dbv;
}

# Render the category tree grouped by module; a Node type whose reader refuses the count offers no deletion of its categories
function getAdminCategoryList(string $modul = '', int $obj = 0): string {
    global $db, $afile, $tpl;
    if (!in_array($modul, getCategoryModules(), true)) $modul = '';
    $where = ($modul) ? 'WHERE modul = :modul' : '';
    $params = ($modul) ? ['modul' => $modul] : [];
    $modlink = ($modul) ? '&modul='.$modul : '';
    $tabs = ['forum' => '_forum'];
    $cats = [];
    $result = $db->getSqlQuery('SELECT id, modul, title, intro, img, lang, parent, ordern, status FROM '.PREFIX_DB.'_categories '.$where.' ORDER BY modul, ordern', $params);
    while ([$cid, $cmod, $title, $intro, $img, $lang, $parent, , $status] = $db->getSqlRow($result)) {
        $cats[$cmod][] = ['id' => (int)$cid, 'title' => $title, 'intro' => $intro, 'img' => $img, 'lang' => $lang, 'parent' => (int)$parent, 'status' => (int)$status];
    }
    if (!$cats) {
        $cont = $tpl->getHtmlFrag('alert', ['text' => _NO_INFO]);
        if ($obj) return $cont;
        echo $cont;
        return '';
    }
    $rows = [];
    foreach ($cats as $cmod => $list) {
        $rows[] = getAdminGroupRow(getModuleName($cmod), 'categories-'.$cmod, 7);
        $kids = [];
        foreach ($list as $cat) $kids[$cat['parent']] = ($kids[$cat['parent']] ?? 0) + 1;
        $nums = [];
        $held = false;
        $type = getNodeTypeMap()[$cmod] ?? null;
        if (isset($tabs[$cmod])) {
            $count = $db->getSqlQuery('SELECT cid, COUNT(id) FROM '.PREFIX_DB.$tabs[$cmod].' GROUP BY cid');
            while ([$ncid, $cnt] = $db->getSqlRow($count)) $nums[(int)$ncid] = (int)$cnt;
        } elseif ($type !== null) {
            try {
                $nums = getNodeReader()->getNodeCategoryCount($type);
            } catch (NodeException $err) {
                $held = true;
            }
        }
        $tree = [];
        $seen = [];
        $walk = function(int $parent, int $level) use (&$walk, &$tree, &$seen, $list): void {
            foreach ($list as $cat) {
                if ($cat['parent'] === $parent && !isset($seen[$cat['id']])) {
                    $seen[$cat['id']] = true;
                    $tree[] = [$cat, $level];
                    $walk($cat['id'], $level + 1);
                }
            }
        };
        $walk(0, 0);
        foreach ($list as $cat) if (!isset($seen[$cat['id']])) $tree[] = [$cat, 0];
        $num = 0;
        foreach ($tree as [$cat, $level]) {
            $num++;
            $cid = $cat['id'];
            $pnum = $nums[$cid] ?? 0;
            $subs = $kids[$cid] ?? 0;
            $img = $cat['img'] ? getCategoryIcon($cat['img']) : $tpl->getHtmlFrag('inline-badge', ['is_danger' => true, 'label' => _NO]);
            $tips = [
                ['label' => _DESCRIPTION, 'value' => (string)($cat['intro'] ?: _NO), 'is_last' => false],
                ['label' => _CATEGORIES, 'value' => (string)($subs ?: _NO), 'is_last' => $cat['lang'] === ''],
            ];
            if ($cat['lang'] !== '') $tips[] = ['label' => _LANGUAGE, 'value' => getLangName($cat['lang']), 'is_last' => true];
            $name = $tpl->getHtmlFrag('tree-node', ['pads' => array_fill(0, max(0, $level - 1), []), 'is_child' => $level > 0]).$tpl->getHtmlFrag('popover', [
                'items' => $tips,
                'label_text' => $cat['title'],
                'title_text' => $cat['title'],
            ]);
            $keep = ['name' => 'categories', 'id' => $cid] + ($modul !== '' ? ['modul' => $modul] : []);
            $dial = [
                [
                    'href' => $afile.'.php?name=categories&op=edit&cid='.$cid.$modlink,
                    'icon_name' => 'pencil',
                    'title' => _FULLEDIT,
                ],
            ];
            if (!$pnum && !$subs && !$held) {
                $dial[] = getTplPostAction(['op' => 'delete'] + $keep, 'trash', _ONDELETE) + ['later' => 'gone del'];
            }
            $rows[] = $tpl->getHtmlFrag('table-row', [
                'name' => $cat['title'],
                'attr' => 'data-sl-drag-id="'.$cid.'" data-sl-drag-group="'.$cmod.'-'.$cat['parent'].'"'
                    .' data-sl-drag-scope="'.$cmod.'" data-sl-drag-parent="'.$cat['parent'].'"',
                'cells_html' => $tpl->getHtmlFrag('table-cells', [
                    'cells' => [
                        ['content_html' => (string)$cid],
                        ['content_html' => $name],
                        ['content_html' => (string)$pnum],
                        ['content_html' => $img],
                        ['content_html' => $tpl->getHtmlFrag('span', ['is_drag_handle' => true]).' '.$num],
                        ['is_col_status' => true, 'stat' => getTplPostAction(['op' => 'change', 'act' => $cat['status']] + $keep, 'power',
                            $cat['status'] ? _DEACTIVATE : _ACTIVATE) + ['is_on' => (bool)$cat['status'], 'label' => _STATUS.': '.$cat['title'], 'later' => 'lamp act']],
                        ['content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _EDITOR, 'dial' => $dial]), 'is_col_actions' => true],
                    ],
                ]),
            ]);
        }
    }
    $cont = $tpl->getHtmlFrag('table', [
        'attr' => 'data-sl-admin-table="categories" data-sl-drag-url="index.php?go=5&op=updateAdminCategoryOrder&mod='.$modul.'"'
            .' data-sl-drag-token="'.getSiteToken().'" data-sl-drag-target="repajax_cat"',
        'disable_sort' => true,
        'head' => [
            ['content' => _ID],
            ['content' => _CATEGORY],
            ['content' => cutstr(_CONTENT, 3, 1)],
            ['content' => cutstr(_ICON, 2, 1)],
            ['content' => _POSITION],
            ['content' => _STATUS],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ],
        'rows_html' => implode('', $rows),
        'is_wrapless' => true,
    ]);
    if ($obj) return $cont;
    echo $cont;
    return '';
}

# Persist a full drag-and-drop tree order for one category module after a token check and echo the refreshed list fragment
function updateAdminCategoryOrder(): void {
    global $db;
    if (checkAdminPost('categories')) {
        $ids = array_values(array_filter(array_map('intval', explode('-', getVar('post', 'ids', 'var')))));
        if ($ids) {
            [$cmod] = $db->getSqlRow($db->getSqlQuery('SELECT modul FROM '.PREFIX_DB.'_categories WHERE id = :cid', ['cid' => $ids[0]]));
            $ordern = 0;
            foreach ($ids as $cid) {
                $ordern++;
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET ordern = :ordern WHERE id = :cid AND modul = :cmod', ['ordern' => $ordern, 'cid' => $cid, 'cmod' => $cmod]);
            }
            deleteCategoryMap((string)$cmod);
        }
    }
    getAdminCategoryList(filterVar(getVar('get', 'mod', 'var', '')), 0);
}

# Render the check list of who may do something in a category or a Node type: everyone, users, each group and the admins, the limit dropping the open levels
function getTplAccessChecks(string $name, string $selected, int $limit, string $label = '', string $desc = ''): string {
    global $db, $tpl;
    $gids = explode('|', $selected);
    $boxes = [];
    if ($limit < 1) $boxes[] = ['value_attr' => '0|0', 'label_text' => _ALL, 'is_checked' => $selected == '0|0'];
    if ($limit < 2) {
        $boxes[] = ['value_attr' => '1|0', 'label_text' => _USERS, 'is_checked' => $selected == '1|0'];
        $where  = '';
        $params = [];
    } else {
        $where  = "WHERE extra = '1'";
        $params = [];
    }
    $result = $db->getSqlQuery('SELECT id, name, extra FROM '.PREFIX_DB.'_groups '.$where.' ORDER BY extra, points', $params);
    while ([$id, $gname, $extra] = $db->getSqlRow($result)) {
        $sel = '';
        if ($gids[0] == 2) {
            $massiv = explode(',', $gids[1]);
            foreach ($massiv as $val) {
                if ($val != '' && $val == $id) {
                    $sel = ' selected';
                    break;
                }
            }
        }
        $title = ($extra) ? _SPEC_GROUP.' "'.$gname.'"' : _GROUP.' "'.$gname.'"';
        $boxes[] = ['value_attr' => '2|'.$id, 'label_text' => $title, 'is_checked' => $sel !== ''];
    }
    $boxes[] = ['value_attr' => '3|0', 'label_text' => _ADMIN, 'is_checked' => $selected == '3|0'];
    $boxes = array_map(fn(array $v): array => $v + ['name_attr' => $name.'[]'], $boxes);
    return $tpl->getHtmlFrag('check-list', ['labelledby' => $label, 'describedby' => $desc, 'items' => $boxes]);
}

# Fold the posted level|group values of one category access select into its stored rule: groups collect every selected group, any other level keeps its first value
function getCategoryAccess(array $auth): string {
    foreach ($auth as $val) {
        $gids = explode('|', $val);
        if ($gids[0] == 2) {
            $acess = '2';
            $select[] = $gids[1];
        } else {
            $acess = $gids[0];
            $select = [];
            $select[] = $gids[1];
            break;
        }
    }
    return $acess.'|'.implode(',', $select);
}

# The places a block may name in which: site modules and Node types by the name their pages carry, never node, then the builder's pages at an address of their own
function getBlockPlaces(): array {
    global $conf;
    $out = [];
    foreach ($conf['modules'] as $name => $info) {
        if ((int)($info['type'] ?? 1) === 1 && $name !== 'node') $out[$name] = [_MODUL, getModuleName($name)];
    }
    foreach (array_keys($conf['node']['types'] ?? []) as $name) $out[$name] = [_TYPE, getModuleName($name)];
    ksort($out);
    $lout = new Layout($conf['layout'] ?? [], getTheme(), false, false);
    foreach ($lout->getAddressKeys() as $key) $out['page-'.$key] = [_LAYOUT_PAGE, (string)($lout->getPage($key)['title'] ?? '') ?: 'page-'.$key];
    return $out;
}

# The values of which no page of the site answers: not a mark of getBlockWhich() and no place of getBlockPlaces(); a 6.x name that stays never shows its block
function getBlockStray(array $where, array $places): array {
    return array_values(array_diff($where, ['', 'all', 'home', 'ihome', 'otricanie', 'infly', 'flyfix'], array_keys($places)));
}

# Render the admin block list by position with drag ordering, the free blocks last, a lamp, the expiry, the marks of a lost file or place and the actions; it never writes
function getAdminBlockList(): string {
    global $db, $afile, $tpl;
    $posmap = [
        'l' => [_LEFT, _LEFTBLOCK],
        'r' => [_RIGHT, _RIGHTBLOCK],
        'c' => [_CENTERUP, _CENTERBLOCK],
        'd' => [_CENTERDOWN, _CENTERBLOCK],
    ];
    $places = getBlockPlaces();
    $rows = [];
    $free = [];
    $group = '';
    $result = $db->getSqlQuery('SELECT id, bkey, title, url, bpos, weight, status, lang, bfile, view, expire, action, which FROM '.PREFIX_DB.'_blocks ORDER BY bpos, weight');
    while ([$bid, $bkey, $title, $url, $bpos, $weight, $active, $lang, $bfile, $view, $expire, $action, $which] = $db->getSqlRow($result)) {
        $exp = $expire ? strtotime($expire) - time() : 0;
        $gone = $expire && $exp < 0;
        $live = $active && !$gone;
        $marks = explode(',', (string)$which);
        $isfly = in_array('infly', $marks, true);
        $isfix = $isfly && in_array('flyfix', $marks, true);
        $exp = $gone ? _EXPIRED : (($exp > 0) ? getDuration($exp) : _UNLIMITED);
        $tips = [['label' => _NAME, 'value' => $title, 'is_last' => false]];
        if ($lang !== '') $tips[] = ['label' => _LANGUAGE, 'value' => getLangName($lang), 'is_last' => false];
        $tips[] = ['label' => _PURCHASED, 'value' => $exp, 'is_last' => true];
        if ($bkey == '') {
            $type = ($url) ? 'RSS/RDF' : 'HTML';
            if ($bfile != '') $type = _BLOCKFILE2;
        } else {
            $type = _BLOCKSYSTEM;
        }
        $stray = getBlockStray($marks, $places);
        if ($bfile != '' && !is_file(BASE_DIR.'/blocks/'.$bfile)) {
            $type .= ' '.$tpl->getHtmlFrag('inline-badge', ['chip_tone' => 'warn', 'label' => _BLOCK_NOFILE, 'title_text' => 'blocks/'.$bfile]);
        }
        if ($stray) $type .= ' '.$tpl->getHtmlFrag('inline-badge', ['chip_tone' => 'warn', 'label' => _BLOCK_NOWHERE, 'title_text' => implode(', ', $stray)]);
        if ($view == 0) {
            $who = _MVALL;
        } elseif ($view == 1) {
            $who = _MVUSERS;
        } elseif ($view == 2) {
            $who = _MVADMIN;
        } else {
            $who = _MVANON;
        }
        if ($isfly) {
            $order = $tpl->getHtmlFrag('inline-badge', [
                'chip_tone' => 'accent',
                'is_infly' => !$isfix,
                'is_flyfix' => $isfix,
                'label' => _INFLY,
                'title_text' => $isfix ? _FLY_FIX : _INFLY,
            ]);
        } else {
            $order = $tpl->getHtmlFrag('span', ['is_drag_handle' => true]).' '.$weight;
        }
        $row = $tpl->getHtmlFrag('table-row', [
            'name' => getConst($title),
            'attr' => $isfly ? '' : 'data-sl-drag-id="'.$bid.'" data-sl-drag-group="'.$bpos.'" data-sl-drag-scope="'.$bpos.'"',
            'cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['content_html' => (string) $bid],
                    ['content_html' => $tpl->getHtmlFrag('popover', [
                        'items' => $tips,
                        'label_text' => getConst($title),
                        'title_text' => $title,
                    ])],
                    ['content_html' => $type],
                    ['content_html' => $who],
                    ['content_html' => $order],
                    ['is_col_status' => true, 'stat' => getTplPostAction(['name' => 'blocks', 'op' => 'change', 'id' => $bid, 'act' => $live ? 1 : 0], 'power',
                        $live ? _DEACTIVATE : _ACTIVATE) + ['is_on' => $live, 'label' => _STATUS.': '.getConst($title), 'later' => 'lamp act']],
                    ['content_html' => $tpl->getHtmlFrag('dial', [
                        'dial_title' => _EDITOR,
                        'dial' => [
                            [
                                'href' => $afile.'.php?name=blocks&op=edit&id='.$bid,
                                'icon_name' => 'pencil',
                                'title' => _FULLEDIT,
                            ],
                            getTplPostAction(['name' => 'blocks', 'op' => 'delete', 'id' => $bid], 'trash', _ONDELETE) + ['later' => 'gone del'],
                        ],
                    ]), 'is_col_actions' => true],
                ],
            ]),
        ]);
        if ($isfly) {
            $free[] = $row;
        } elseif ($bpos !== $group) {
            $group = $bpos;
            $label = isset($posmap[$bpos]) ? $posmap[$bpos][0].' — '.mb_strtolower($posmap[$bpos][1], 'UTF-8') : $bpos;
            $rows[] = getAdminGroupRow($label, 'blocks-'.$bpos, 7);
            $rows[] = $row;
        } else {
            $rows[] = $row;
        }
    }
    if ($free) {
        $rows[] = getAdminGroupRow(_INFLYINFO, 'blocks-fly', 7);
        $rows = array_merge($rows, $free);
    }
    return $tpl->getHtmlFrag('table', [
        'attr' => 'data-sl-admin-table="blocks" data-sl-drag-url="index.php?go=5&op=updateAdminBlockOrder"'
            .' data-sl-drag-token="'.getSiteToken().'" data-sl-drag-target="repajax_block"',
        'disable_sort' => true,
        'head' => [
            ['content' => _ID],
            ['content' => _TITLE],
            ['content' => _TYPE],
            ['content' => _VIEW],
            ['content' => _POSITION],
            ['content' => _STATUS],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ],
        'rows_html' => implode('', $rows),
        'is_wrapless' => true,
    ]);
}

# Build one collapsible group header row spanning the given number of admin list table columns
function getAdminGroupRow(string $label, string $key, int $span): string {
    global $tpl;
    return $tpl->getHtmlFrag('table-row', ['is_group' => true, 'attr' => 'data-sl-group="'.$key.'"', 'cells_html' => $tpl->getHtmlFrag('table-cells', [
        'is_summary' => true,
        'cells' => [['content_html' => $tpl->getHtmlFrag('group-toggle', ['label' => $label]), 'attr' => 'colspan="'.$span.'"']],
    ])]);
}

# Persist a full drag-and-drop order for one block position group after a token check and echo the refreshed list fragment
function updateAdminBlockOrder(): void {
    global $db;
    if (checkAdminPost('blocks')) {
        $ids = array_values(array_filter(array_map('intval', explode('-', getVar('post', 'ids', 'var')))));
        $row = $ids ? $db->getSqlRow($db->getSqlQuery('SELECT bpos FROM '.PREFIX_DB.'_blocks WHERE id = :bid', ['bid' => $ids[0]])) : null;
        if ($row) setBlockOrder((string)$row[0], $ids, 1);
    }
    echo getAdminBlockList();
}

# The one writer of the position and the order of _blocks: a zone numbered from 1, the given rows moved in from $place on (0 after the others), each zone they left closed up
function setBlockOrder(string $bpos, array $ids = [], int $place = 0): bool {
    global $db;
    $zones = ['l', 'r', 'c', 'd'];
    if (!in_array($bpos, $zones, true)) return false;
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $v): bool => $v > 0)));
    $pars = ['bpos' => $bpos];
    foreach ($ids as $i => $bid) $pars['id'.$i] = $bid;
    $in = $ids ? ' OR id IN (:'.implode(', :', array_slice(array_keys($pars), 1)).')' : '';
    $own = !$db->checkSqlActive();
    if ($own && !$db->setSqlBegin()) return false;
    $res = $db->getSqlQuery('SELECT id, bpos, weight FROM '.PREFIX_DB.'_blocks WHERE bpos = :bpos'.$in.' ORDER BY weight, id', $pars);
    $rows = $res ? ($db->getSqlRows($res) ?: []) : [];
    $have = [];
    $rest = [];
    $left = [];
    foreach ($rows as [$bid, $pos, $weight]) {
        $have[(int)$bid] = [(string)$pos, (int)$weight];
        if (!in_array((int)$bid, $ids, true)) $rest[] = (int)$bid;
        elseif ($pos !== $bpos && in_array($pos, $zones, true)) $left[$pos] = true;
    }
    $move = array_values(array_filter($ids, static fn(int $v): bool => isset($have[$v])));
    array_splice($rest, ($place > 0) ? min($place - 1, count($rest)) : count($rest), 0, $move);
    $done = $res !== false;
    $sql = 'UPDATE '.PREFIX_DB.'_blocks SET bpos = :bpos, weight = :weight WHERE id = :bid';
    foreach ($rest as $i => $bid) {
        if ($done && $have[$bid] !== [$bpos, $i + 1]) $done = $db->getSqlQuery($sql, ['bpos' => $bpos, 'weight' => $i + 1, 'bid' => $bid]) !== false;
    }
    foreach (array_keys($left) as $pos) $done = $done && setBlockOrder((string)$pos);
    if (!$own) return $done;
    if ($done && $db->setSqlCommit()) return true;
    $db->setSqlRollback();
    return false;
}

# The one writer of _blocks.which from the checked boxes: all and home push the list out, the denial and the fixed mark follow them, infly leads, ihome leads any list but home
function getBlockWhich(array $marks): string {
    $which = '';
    if (in_array('all', $marks, true)) $which = 'all';
    if (in_array('home', $marks, true)) $which = 'home';
    if ($which === '') {
        $which = implode(',', $marks);
    } else {
        if (in_array('otricanie', $marks, true)) $which .= ',otricanie';
        if (in_array('flyfix', $marks, true)) $which .= ',flyfix';
    }
    if (in_array('infly', $marks, true)) $which = in_array('flyfix', $marks, true) ? 'infly,'.str_replace('infly,', '', $which) : 'infly,';
    if (in_array('ihome', $marks, true) && $which !== 'home') $which = 'ihome,'.str_replace(',ihome', '', $which);
    return $which;
}

# Add voting
function add_voting(string $modul, string $selectName, int $selectedId): string {
 global $db, $locale, $conf, $tpl;
    $modul  = filterVar($modul);
    $params = ['modul' => $modul];
    if ($conf['multilingual'] == 1) {
        $where  = "(lang = :locale OR lang = '') AND modul = :modul AND time <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')";
        $params['locale'] = $locale;
    } else {
        $where  = "modul = :modul AND time <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')";
    }
    $opts   = $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _NO, 'is_selected' => false]);
    $result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_voting WHERE '.$where.' ORDER BY id DESC', $params);
    if ($db->getSqlRowCount($result) > 0) {
        while ([$id, $title] = $db->getSqlRow($result)) {
            $opts .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$id, 'label_text' => $title, 'is_selected' => $selectedId == $id]);
        }
    }
    return $tpl->getHtmlFrag('select', ['name_attr' => $selectName, 'options_html' => $opts]);
}

# Split one SQL script into statements, honouring quotes, comments and DELIMITER; a statement stays as written, an escape in a literal is never unwrapped
function getSqlbatch(string $sql): array {
    $sql = str_replace("\r\n", "\n", str_replace("\r", "\n", $sql));
    $len = strlen($sql);
    $dlim = ';';
    $buff = '';
    $list = [];
    $quot = '';
    $lcom = false;
    $bcom = false;
    $sol = true;
    for ($num = 0; $num < $len; $num++) {
        $char = $sql[$num];
        $next = ($num + 1 < $len) ? $sql[$num + 1] : '';
        if ($sol && !$quot && !$lcom && !$bcom) {
            $lend = strpos($sql, "\n", $num);
            $lend = ($lend === false) ? $len : $lend;
            $line = substr($sql, $num, $lend - $num);
            if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $mass)) {
                $dlim = $mass[1];
                $num = $lend;
                $sol = true;
                continue;
            }
        }
        if ($lcom) {
            $buff .= $char;
            $sol = ($char === "\n");
            if ($char === "\n") $lcom = false;
            continue;
        }
        if ($bcom) {
            $buff .= $char;
            if ($char === '*' && $next === '/') {
                $buff .= '/';
                $num++;
                $bcom = false;
                $sol = false;
                continue;
            }
            $sol = ($char === "\n");
            continue;
        }
        if ($quot !== '') {
            $buff .= $char;
            if ($char === '\\' && $quot !== '`' && $next !== '') {
                $buff .= $next;
                $num++;
                $sol = false;
                continue;
            }
            if ($char === $quot) {
                if ($quot !== '`' && $next === $quot) {
                    $buff .= $next;
                    $num++;
                } else {
                    $quot = '';
                }
            }
            $sol = ($char === "\n");
            continue;
        }
        if ($char === '-' && $next === '-' && (($num + 2 >= $len) || preg_match('/\s/', $sql[$num + 2]))) {
            $buff .= $char.$next;
            $num++;
            $lcom = true;
            $sol = false;
            continue;
        }
        if ($char === '#') {
            $buff .= $char;
            $lcom = true;
            $sol = false;
            continue;
        }
        if ($char === '/' && $next === '*') {
            $buff .= $char.$next;
            $num++;
            $bcom = true;
            $sol = false;
            continue;
        }
        if ($char === '\'' || $char === '"' || $char === '`') {
            $buff .= $char;
            $quot = $char;
            $sol = false;
            continue;
        }
        if ($dlim !== '' && substr($sql, $num, strlen($dlim)) === $dlim) {
            $stmt = getSqlclean($buff);
            if ($stmt !== '') $list[] = $stmt;
            $buff = '';
            $num += strlen($dlim) - 1;
            $sol = false;
            continue;
        }
        $buff .= $char;
        $sol = ($char === "\n");
    }
    if ($quot !== '') return ['statements' => $list, 'error' => 'Unclosed quoted string in SQL input'];
    if ($bcom) return ['statements' => $list, 'error' => 'Unclosed block comment in SQL input'];
    $stmt = getSqlclean($buff);
    if ($stmt !== '') $list[] = $stmt;
    return ['statements' => $list, 'error' => ''];
}

# Drop the comments in front of the first token, so the preview and getSqlinfo() read the query; a block of nothing but comments returns empty and is dropped
function getSqlclean(string $sql): string {
    while ($sql !== '') {
        if (preg_match('/^\s+/', $sql, $mass)) {
            $sql = substr($sql, strlen($mass[0]));
            continue;
        }
        if (preg_match('/^(?:#|--(?:\s|$))[^\n]*(?:\n|$)/', $sql, $mass)) {
            $sql = substr($sql, strlen($mass[0]));
            continue;
        }
        if (str_starts_with($sql, '/*')) {
            $stop = strpos($sql, '*/');
            if ($stop === false) break;
            $sql = substr($sql, $stop + 2);
            continue;
        }
        break;
    }
    return trim($sql);
}

# The keyword and the table of one statement; the table is anchored to its keyword, as the first backtick is as often a column or a routine body
function getSqlinfo(string $sql): array {
    $type = 'SQL';
    $table = '';
    $lead = '(?:IF\s+(?:NOT\s+)?EXISTS\s+)?';
    $verb = 'INSERT\s+INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM|TRUNCATE(?:\s+TABLE)?|ALTER\s+TABLE|CREATE\s+(?:TEMPORARY\s+)?TABLE|DROP\s+TABLE|SELECT\b.*?\bFROM';
    if (preg_match('/^\s*([A-Za-z]+)/', $sql, $mass)) $type = strtoupper($mass[1]);
    if (preg_match('/^\s*(?:'.$verb.')\s+'.$lead.'`?([A-Za-z0-9_$]+)`?/is', $sql, $mass)) $table = $mass[1];
    return ['type' => $type, 'table' => $table];
}

# Fill the install placeholders of one SQL script; a table.sql names its engine and collation through them, so an unfilled script is invalid SQL
function getSqlFilled(string $sql): string {
    global $conf;
    $map = [
        '{prefix}' => PREFIX_DB,
        '{engine}' => (string)($conf['db']['engine'] ?? ''),
        '{charset}' => (string)($conf['db']['charset'] ?? ''),
        '{collate}' => (string)($conf['db']['collate'] ?? ''),
    ];
    return str_replace(array_keys($map), array_values($map), $sql);
}

# The tables one SQL script names, in order and without repeats, optionally of one statement type; its CREATE set is what a module owns
function getSqlFileTables(string $sql, string $verb = ''): array {
    $out = [];
    foreach (getSqlbatch(getSqlFilled($sql))['statements'] as $one) {
        $info = getSqlinfo($one);
        if ($info['table'] === '' || ($verb !== '' && $info['type'] !== $verb)) continue;
        if (!in_array($info['table'], $out, true)) $out[] = $info['table'];
    }
    return $out;
}

# Whether one table exists, asked of the catalogue, so a module that is not installed costs the list page no SQL error
function checkSqlTable(string $name): bool {
    global $db;
    if ($name === '') return false;
    $row = $db->getSqlRow($db->getSqlQuery(
        'SELECT COUNT(*) AS num FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :name',
        ['name' => $name]
    ));
    return (int)($row['num'] ?? 0) > 0;
}

# Save one part of a Node type with the version its form was built from; a refusal answers a safe text, never the message of the exception
function updateNodeTypePart(string $name, string $part, array $value, int $version): string {
    $type = getNodeTypeMap()[$name] ?? null;
    $label = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($type === null || !in_array($part, ['fields', 'uploads', 'rating', 'integrations'], true)) return sprintf(_NODE_BAD, $label);
    $data = ['fields' => $type->fields, 'uploads' => $type->uploads, 'rating' => $type->rating, 'settings' => $type->settings];
    if ($part === 'integrations') $data['settings']['integrations'] = $value;
    else $data[$part] = $value;
    try {
        $input = new NodeTypeInput($type->title, $type->intro, $type->ext, $type->sort, $data['settings'], $data['fields'], $data['uploads'], $data['rating']);
        getNodeWriter()->updateNodeType($name, $input, $version);
    } catch (NodeException $err) {
        return match ($err->getCode()) {
            NodeException::CONFLICT => sprintf(_NODE_STALE, $label),
            NodeException::STORAGE => getConfigJournal() ? _CONFIG_PENDING : _ERROR_UP,
            default => sprintf(_NODE_BAD, $label),
        };
    }
    return '';
}

# The file area of the request, named once by its page; the client names only its screen, never a root, and the context decides what it may mean
function getAdminFileMode(string $set = ''): string {
    static $ctx = '';
    if ($set !== '') $ctx = $set;
    if ($ctx === '') $ctx = (getVar('get', 'ctx', 'word', '') === 'uploads' || getVar('post', 'ctx', 'word', '') === 'uploads') ? 'uploads' : 'system';
    return $ctx;
}

# The file context of one area, both roots named here alone; core/classes has no autoload, so the file layer is required on first use
function getAdminFileManager(string $mode = ''): FileManager {
    static $mans = [];
    $mode = ($mode === '') ? getAdminFileMode() : $mode;
    if (!isset($mans[$mode])) {
        require_once BASE_DIR.'/core/classes/filemanager.php';
        $mans[$mode] = ($mode === 'uploads') ? new FileManager('uploads', UPLOADS_DIR) : new FileManager('system', BASE_DIR);
    }
    return $mans[$mode];
}

# The upload rule of one browser path, by module or below the Node root by type; the module quota limits users, not the administration, so it is dropped
function getAdminUploadRule(string $dir): array {
    $part = explode('/', $dir);
    $mod = ($dir === '') ? '' : (($part[0] === basename(NODE_DIR)) ? ($part[1] ?? '') : $part[0]);
    return array_merge(getUploadRuleData($mod), ['mod' => $mod, 'maxquota' => 0]);
}

# The owner folder of an upload path and the paths below it that any stored row references, read as the route reads them, or null for a folder no reader covers
function getAdminFileRefs(string $dir): ?array {
    global $db, $conf, $com, $prv;
    $part = explode('/', $dir);
    $node = $part[0] === basename(NODE_DIR);
    $mod = $node ? ($part[1] ?? '') : $part[0];
    $type = $node ? (getNodeTypeMap()[$mod] ?? null) : null;
    $ava = (string)preg_replace('#^uploads(?:/|$)#', '', trim(str_replace('\\', '/', (string)($conf['users']['adirectory'] ?? '')), '/'));
    $room = match (true) {
        $type !== null => getUploadFolder($mod, true),
        !$node && !in_array(getUploadOwner($mod), ['', 'node'], true) => $mod,
        $ava !== '' && ($dir === $ava || str_starts_with($dir, $ava.'/')) => $ava,
        default => '',
    };
    if ($room === '') return null;
    $cmod = ($mod === 'profile') ? 'account' : $mod;
    $texts = ($room !== $ava && getCommentPlace($cmod) === $mod) ? $com->getAttachTexts($cmod, 0, true) : [];
    if ($room === 'account') array_push($texts, ...$prv->getAttachBodies(0, true));
    $sql = match ($room) {
        'forum' => 'SELECT body FROM '.PREFIX_DB.'_forum WHERE body LIKE \'%[attach=%\'',
        'profile' => 'SELECT sig, block FROM '.PREFIX_DB.'_users WHERE sig LIKE \'%[attach=%\' OR block LIKE \'%[attach=%\'',
        default => '',
    };
    $res = ($sql === '') ? false : $db->getSqlQuery($sql);
    while ($res && ($row = $db->getSqlRow($res))) array_push($texts, ...array_values(array_filter($row, 'is_string', ARRAY_FILTER_USE_KEY)));
    $prs = new Parser();
    $refs = [];
    foreach ($texts as $text) array_push($refs, ...$prs->getAttachList($text));
    try {
        if ($type !== null) array_push($refs, ...getNodeWriter($type)->getTypeFiles($type));
    } catch (NodeException) {
        return null;
    }
    if ($room === $ava) {
        $res = $db->getSqlQuery('SELECT avatar FROM '.PREFIX_DB.'_users WHERE avatar != \'\' AND avatar NOT LIKE \'presets/%\'');
        while ($res && ($row = $db->getSqlRow($res))) $refs[] = $row['avatar'];
    }
    return ['room' => $room, 'refs' => array_values(array_unique($refs))];
}

# One relative path of the request unfiltered, as the file layer canonicalizes it; the caller names the source, the query for a read, the body for a write
function getAdminFilePath(string $key, string $src = 'get'): string {
    $path = getVar($src, $key, 'raw', '');
    return is_string($path) ? mb_substr(trim($path), 0, 512) : '';
}

# The address of one browser route with the area and the ajax token a numeric go demands; without the area the route would build the system context
function getAdminFileLink(string $op, array $args = []): string {
    static $tok = '';
    if ($tok === '') $tok = getSiteToken();
    $out = 'index.php?go=5&op='.$op.((getAdminFileMode() === 'uploads') ? '&ctx=uploads' : '');
    foreach ($args as $key => $val) {
        if ((string)$val === '') continue;
        $out .= '&'.$key.'='.rawurlencode((string)$val);
    }
    return $out.'&token='.$tok;
}

# The address one image is shown at: its own where the file has one, else the preview route that checks it again; a listing takes the stored thumbnail
function getAdminFileShot(array $one, bool $small = false): string {
    if (($one['kind'] ?? '') !== 'image' || empty($one['capabilities']['preview'])) return '';
    if ($small && $one['thumbnail'] !== '') return $one['thumbnail'];
    return ($one['url'] !== '') ? $one['url'] : getAdminFileLink('getAdminFilePreview', ['file' => $one['path']]);
}

# Return the icon and the tone one object is drawn with, chosen by the kind of the descriptor, so the type resolver of the file layer stays the only one in the project
function getAdminFileIcon(string $kind): array {
    return match ($kind) {
        'dir' => ['folder', 'dir'],
        'image' => ['image', 'img'],
        'audio' => ['file-earmark-music', 'doc'],
        'video' => ['file-earmark-play', 'doc'],
        'archive' => ['file-earmark-zip', 'zip'],
        'document' => ['file-earmark-pdf', 'doc'],
        'text' => ['file-earmark-text', 'code'],
        'code' => ['file-earmark-code', 'code'],
        default => ['file-earmark', 'doc'],
    };
}

# Render the system tree along the open path alone, the root level and the children of each ancestor, never a walk of the whole site
function getAdminFileNodes(string $dir): string {
    global $tpl;
    $man = getAdminFileManager();
    $open = ($dir === '') ? [] : explode('/', $dir);
    $out = [];
    $cur = '';
    $at = 0;
    $step = 0;
    while (true) {
        $kids = [];
        foreach ($man->getFileList($cur) as $row) {
            if ($row['kind'] !== 'dir') continue;
            $kids[] = [
                'name' => $row['name'],
                'hint' => $row['path'],
                'path' => $row['path'],
                'url' => getAdminFileLink('getAdminFileList', ['dir' => $row['path']]),
                'pads' => array_fill(0, $step + 1, true),
                'icon' => ($row['path'] === $dir) ? 'folder-fill' : 'folder',
                'is_cur' => $row['path'] === $dir,
            ];
        }
        array_splice($out, $at, 0, $kids);
        if ($step >= count($open)) break;
        $next = ($cur === '') ? $open[$step] : $cur.'/'.$open[$step];
        $pos = -1;
        foreach ($out as $i => $row) {
            if ($row['path'] === $next) $pos = $i;
        }
        if ($pos < 0) break;
        $at = $pos + 1;
        $cur = $next;
        $step++;
    }
    $isup = getAdminFileMode() === 'uploads';
    array_unshift($out, [
        'name' => $isup ? basename(UPLOADS_DIR) : _UPLOADS_ROOT,
        'hint' => $isup ? basename(UPLOADS_DIR) : _UPLOADS_ROOT,
        'path' => '',
        'url' => getAdminFileLink('getAdminFileList'),
        'pads' => [],
        'icon' => $isup ? 'folder-fill' : 'hdd-stack',
        'is_cur' => $dir === '',
    ]);
    return $tpl->getHtmlPart('file-browser-tree', ['cap_text' => $isup ? _UPLOADS_DIRS : _UPLOADS_TREE, 'nodes' => $out]);
}

# Render the capability row as the file layer answers it, so no screen derives a permission from a role of its own
function getAdminFileGrants(): string {
    global $tpl;
    $names = [
        'browse' => _UPLOADS_BROWSE, 'preview' => _UPLOADS_PREVIEW, 'upload' => _UPLOADS_UPLOAD, 'download' => _UPLOADS_DOWNLOAD,
        'edit' => _UPLOADS_EDIT, 'create' => _UPLOADS_CREATE, 'mkdir' => _UPLOADS_MKDIR, 'rename' => _UPLOADS_RENAME,
        'copy' => _UPLOADS_COPY, 'move' => _UPLOADS_MOVE, 'delete' => _UPLOADS_DELETE, 'compress' => _UPLOADS_COMPRESS,
    ];
    $out = '';
    foreach (getAdminFileManager()->getCapabilities() as $key => $val) {
        if (!isset($names[$key])) continue;
        $out .= $tpl->getHtmlFrag('inline-badge', ['chip_tone' => $val ? 'success' : 'neutral', 'label' => $names[$key]]);
    }
    return $out;
}

# Render the fan of one object from its own capability set, so a forbidden operation is not drawn; a critical path adds its cost to the delete question
function getAdminFileActs(array $one): string {
    global $afile, $tpl;
    $able = $one['capabilities'];
    $ctx = getAdminFileMode();
    $path = $one['path'];
    $name = $one['name'];
    $dir = str_contains($path, '/') ? substr($path, 0, (int)strrpos($path, '/')) : '';
    $ask = _DELETE.' "'.$name.'"?'.(empty($one['critical']) ? '' : ' '._UPLOADS_CRITDEL);
    $dial = [];
    if (!empty($able['edit'])) $dial[] = [
        'href' => $afile.'.php?name=uploads&op=fmedit&file='.rawurlencode($path),
        'run' => 'fmedit',
        'icon_name' => 'pencil-square',
        'title' => _EDIT,
    ];
    if (!empty($able['preview']) && $one['kind'] === 'image') $dial[] = ['href' => '#', 'run' => 'preview', 'icon_name' => 'zoom-in', 'title' => _UPLOADS_PREVIEW];
    if (!empty($able['download'])) $dial[] = ['href' => getAdminFileLink('getAdminFileDownload', ['file' => $path]), 'icon_name' => 'download', 'title' => _DOWNLOAD];
    if (!empty($able['rename'])) $dial[] = ['href' => '#', 'act' => 'fmrename', 'file' => $path, 'arg' => $name, 'icon_name' => 'input-cursor-text', 'title' => _UPLOADS_TORENAME];
    if (!empty($able['copy'])) $dial[] = ['href' => '#', 'act' => 'fmcopy', 'file' => $path, 'arg' => $path, 'icon_name' => 'files', 'title' => _UPLOADS_TOCOPY];
    if (!empty($able['move'])) $dial[] = ['href' => '#', 'act' => 'fmmove', 'file' => $path, 'arg' => $path, 'icon_name' => 'folder-symlink', 'title' => _UPLOADS_TOMOVE];
    $post = ['name' => 'uploads', 'op' => '', 'ctx' => $ctx, 'file' => $path, 'back' => $dir];
    if (!empty($able['compress'])) $dial[] = getTplPostAction(['op' => 'fmcompress'] + $post, 'file-zip', _UPLOADS_TOZIP) + ['run' => 'fmcompress'];
    if (!empty($able['delete'])) $dial[] = getTplPostAction(['op' => 'fmdelete'] + $post, 'trash3', _DELETE, $ask) + ['run' => 'fmdelete'];
    return ($dial === []) ? '' : $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => $dial]);
}

# Render the properties of one object from its descriptor; a missing or closed path answers the empty panel, as every other route refuses it
function getAdminFileProps(string $path): string {
    global $afile, $tpl;
    $man = getAdminFileManager();
    $one = ($path === '') ? [] : $man->getFileData($path);
    if ($one === []) return $tpl->getHtmlFrag('file-browser-props', ['cap_text' => _UPLOADS_PROPS, 'hint_text' => _NO_INFO]);
    [$icon, $tone] = getAdminFileIcon($one['kind']);
    $body = empty($one['editable']) ? [] : $man->getFileBody($one['path']);
    $rows = [
        ['label' => _NAME, 'value' => $one['name']],
        ['label' => _TYPE, 'value' => ($one['extension'] === '') ? $one['kind'] : $one['kind'].' · '.$one['extension']],
    ];
    if ($one['kind'] !== 'dir') $rows[] = ['label' => _SIZE, 'value' => filterSize($one['size'])];
    if ($one['width']) $rows[] = ['label' => _UPLOADS_DIMS, 'value' => $one['width'].' × '.$one['height']];
    if ($body !== []) $rows[] = ['label' => _UPLOADS_LINES, 'value' => number_format($body['lines'], 0, '', ' ')];
    $rows[] = ['label' => _DATE, 'value' => date(_TIMESTRING, $one['mtime'])];
    $rows[] = ['label' => ($one['url'] === '') ? _UPLOADS_PATH : _UPLOADS_ADDR, 'value' => ($one['path'] === '') ? '/' : ($one['url'] ?: $one['path'])];
    $rows[] = ['label' => _UPLOADS_FULL, 'value' => $one['realpath'] ?? ''];
    if ($one['perms'] !== '') $rows[] = ['label' => _UPLOADS_PERMS, 'value' => $one['perms']];
    if ($one['owner'] !== '') $rows[] = ['label' => _UPLOADS_USER, 'value' => $one['owner']];
    if ($body !== []) $rows[] = ['label' => _UPLOADS_VERSION, 'value' => substr($body['version'], 0, 6)];
    if ($one['managed']) $rows[] = ['label' => _UPLOADS_OWNER, 'value' => FileManager::getFileOwner($one['name']) ?? _UPLOADS_OWNSITE];
    $warn = empty($one['critical']) ? '' : _UPLOADS_CRIT;
    if ($warn === '' && $one['managed'] && !empty($one['capabilities']['rename'])) $warn = _UPLOADS_LINKWARN;
    return $tpl->getHtmlFrag('file-browser-props', [
        'dial_html' => getAdminFileActs($one),
        'cap_text' => _UPLOADS_PROPS,
        'name' => $one['name'],
        'icon' => $icon,
        'tone' => $tone,
        'image_url' => getAdminFileShot($one),
        'pick_value' => $one['path'],
        'info_url' => getAdminFileLink('getAdminFileData', ['file' => $one['path']]),
        'down_url' => empty($one['capabilities']['download']) ? '' : getAdminFileLink('getAdminFileDownload', ['file' => $one['path']]),
        'shot_text' => _UPLOADS_PREVIEW,
        'rows' => $rows,
        'note_html' => ($warn === '') ? '' : $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $warn]),
    ]);
}

# Render the source editor of one system file; the version field is compared under the directory lock on save, so it tells a stale save and is never dropped
function getAdminFileEditor(array $edit): string {
    global $afile, $tpl;
    $man = getAdminFileManager();
    $path = (string)($edit['path'] ?? '');
    $one = $man->getFileData($path);
    $dir = str_contains($path, '/') ? substr($path, 0, (int)strrpos($path, '/')) : '';
    $ver = (string)($edit['version'] ?? '');
    $code = 'slfmcode';
    $back = $afile.'.php?name=uploads&op=sysfiles'.(($dir === '') ? '' : '&dir='.rawurlencode($dir));
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'form_attr' => 'id="slfmeditform"',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'uploads'],
            ['nameattr' => 'op', 'valueattr' => 'fmsave'],
            ['nameattr' => 'file', 'valueattr' => $path],
            ['nameattr' => 'ver', 'valueattr' => $ver],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('uploads')],
        ],
        'content_html' => (($edit['note'] ?? '') === '' ? '' : $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $edit['note']]))
            .Editor::getCode([
                'id' => $code,
                'name' => 'text',
                'label' => _FILE.': '.$path,
                'lang' => $man->getCodeLanguage($path),
                'text' => (string)($edit['text'] ?? ''),
            ]),
    ]);
    $foot = $tpl->getHtmlFrag('window-foot-edit', [
        'ver_hint' => _UPLOADS_VERHINT,
        'ver_label' => _UPLOADS_VERSION,
        'ver_text' => substr($ver, 0, 6),
        'back_url' => $back,
        'back_text' => _BACK,
        'form_id' => 'slfmeditform',
        'save_text' => _SAVECHANGES,
        'confirm_text' => empty($one['critical']) ? '' : _UPLOADS_CRITASK,
    ]);
    return $tpl->getHtmlFrag('window', [
        'win_id' => 'slfmedit',
        'size_class' => 'sl-modal-xl',
        'win_class' => 'sl-fm-edit',
        'is_static' => true,
        'win_attr' => 'data-sl-fm-code="'.$code.'" data-sl-fm-ask="'.htmlspecialchars(_UPLOADS_LEAVE, ENT_QUOTES, 'UTF-8').'"',
        'icon_name' => 'pencil-square',
        'title_text' => _EDIT,
        'has_sub' => true,
        'sub_text' => $path,
        'close_url' => $back,
        'close_text' => _BACK,
        'body_html' => $form,
        'foot_html' => $foot,
    ]);
}

# Render the file browser of one directory, whole and unpaged; the fan is built again for the tile, as a form id shared with the row would submit the row form
function getAdminFileShell(bool $full = false, array $edit = []): string {
    global $afile, $tpl;
    $ctx = getAdminFileMode();
    $man = getAdminFileManager();
    $able = $man->getCapabilities();
    $pick = (string)($edit['path'] ?? '');
    $sent = getAdminFilePath('dir');
    if ($sent === '') $sent = getAdminFilePath('dir', 'post');
    $dir = ($pick === '') ? $sent : (str_contains($pick, '/') ? substr($pick, 0, (int)strrpos($pick, '/')) : '');
    $find = ($pick === '') ? mb_substr(getAdminFilePath('find'), 0, 60) : '';
    $one = $man->getFileData($dir);
    if (($one['kind'] ?? '') !== 'dir') $dir = '';
    $all = $man->getFileList($dir);
    $used = ($ctx === 'uploads') ? getAdminFileRefs($dir) : null;
    $lone = $used !== null && getVar('get', 'unused', 'num', 0) === 1;
    $idle = [];
    $refs = array_flip($used['refs'] ?? []);
    $age = time() - 86400;
    foreach (($used === null) ? [] : $all as $row) {
        $rel = substr($row['path'], strlen($used['room']) + 1);
        $rel = str_starts_with($rel, 'thumb/') ? substr($rel, 6) : $rel;
        if ($row['kind'] !== 'dir' && $row['mtime'] < $age && !isset($refs[$rel])) $idle[$row['path']] = true;
    }
    if ($lone) $all = array_values(array_filter($all, static fn(array $row): bool => isset($idle[$row['path']])));
    if ($find !== '') $all = array_values(array_filter($all, static fn(array $row): bool => mb_stripos($row['name'], $find) !== false));
    $sum = 0;
    foreach ($all as $row) $sum += $row['size'];
    $rule = getAdminUploadRule($dir);
    $show = $all;
    $mark = !empty($able['delete']) || !empty($able['compress']) || !empty($able['move']);
    $rows = '';
    $tiles = '';
    foreach ($show as $row) {
        [$icon, $tone] = getAdminFileIcon($row['kind']);
        $isdir = $row['kind'] === 'dir';
        $data = [
            'name' => $row['name'],
            'hint_text' => $row['path'],
            'url' => $isdir ? getAdminFileLink('getAdminFileList', ['dir' => $row['path']]) : getAdminFileLink('getAdminFileData', ['file' => $row['path']]),
            'image_url' => getAdminFileShot($row, true),
            'full_url' => getAdminFileShot($row),
            'info_url' => getAdminFileLink('getAdminFileData', ['file' => $row['path']]),
            'down_url' => empty($row['capabilities']['download']) ? '' : getAdminFileLink('getAdminFileDownload', ['file' => $row['path']]),
            'icon' => $icon,
            'tone' => $tone,
            'is_dir' => $isdir,
            'is_mark' => $mark && !$isdir,
            'is_move' => !empty($row['capabilities']['move']),
            'pick_value' => $row['path'],
            'mark_text' => _UPLOADS_MARK.': '.$row['name'],
            'shot_text' => _UPLOADS_PREVIEW,
            'kind_text' => $isdir ? _DIR : strtoupper($row['extension']),
            'size_text' => $isdir ? '—' : filterSize($row['size']),
            'size_num' => (string)($isdir ? -1 : (int)$row['size']),
            'date_num' => (string)(int)$row['mtime'],
            'date_text' => date(_TIMESTRING, $row['mtime']),
            'day_text' => date(_DATESTRING, $row['mtime']),
            'acts_html' => getAdminFileActs($row),
        ];
        $rows .= $tpl->getHtmlFrag('file-browser-row', $data);
        $data['acts_html'] = getAdminFileActs($row);
        $tiles .= $tpl->getHtmlFrag('file-browser-tile', $data);
    }
    $home = ($ctx === 'uploads') ? basename(UPLOADS_DIR) : _UPLOADS_ROOT;
    $crumbs = [['name' => $home, 'url' => ($dir === '') ? '' : getAdminFileLink('getAdminFileList')]];
    $walk = '';
    foreach (($dir === '') ? [] : explode('/', $dir) as $part) {
        $walk = ($walk === '') ? $part : $walk.'/'.$part;
        $crumbs[] = ['name' => $part, 'url' => ($walk === $dir) ? '' : getAdminFileLink('getAdminFileList', ['dir' => $walk])];
    }
    $acts = [['icon' => getIconName('download'), 'name' => _DOWNLOAD, 'tone' => 'neutral', 'is_load' => true]];
    if (!empty($able['edit'])) $acts[] = ['key' => 'fmedit', 'icon' => getIconName('fmedit'), 'name' => _EDIT, 'tone' => 'info'];
    if (!empty($able['rename'])) $acts[] = ['key' => 'fmrename', 'icon' => getIconName('fmrename'), 'name' => _UPLOADS_TORENAME, 'tone' => 'neutral'];
    if (!empty($able['copy'])) $acts[] = ['key' => 'fmcopy', 'icon' => getIconName('fmcopy'), 'name' => _UPLOADS_TOCOPY, 'tone' => 'neutral'];
    if (!empty($able['move'])) $acts[] = ['key' => 'fmmove', 'icon' => getIconName('fmmove'), 'name' => _UPLOADS_TOMOVE, 'tone' => 'neutral'];
    if (!empty($able['compress'])) $acts[] = ['key' => 'fmcompress', 'icon' => getIconName('fmcompress'), 'name' => _UPLOADS_TOZIP, 'tone' => 'neutral'];
    if (!empty($able['delete'])) $acts[] = ['key' => 'fmdelete', 'icon' => getIconName('fmdelete'), 'name' => _DELETE, 'tone' => 'danger'];
    $shot = !$full ? '' : getWindowShot([
        'own' => 'files',
        'prev_text' => _BACK,
        'next_text' => _NEXT,
        'can_walk' => true,
        'can_props' => true,
        'acts' => $acts,
    ]);
    return $shot.$tpl->getHtmlPart('file-browser', [
        'is_full' => $full,
        'is_swap' => !$full,
        'ctx' => $ctx,
        'dir' => $dir,
        'crumbs' => $crumbs,
        'can_upload' => !empty($able['upload']),
        'is_upload' => !empty($able['upload']) && $rule['ok'],
        'can_mark' => $mark,
        'can_move' => !empty($able['move']),
        'can_zip' => !empty($able['compress']),
        'can_delete' => !empty($able['delete']),
        'load_text' => _UPLOADS_LOAD,
        'drop_text' => _UPLOADS_DROP,
        'types_text' => $rule['ok'] ? _FTYPE.': '.$rule['extensions'].' · '._FSIZE.': '.filterSize($rule['maxbytes']) : '',
        'link_text' => _FILE_SITE,
        'queue_text' => _UPLOADS_QUEUE,
        'stop_text' => _UPLOADS_STOP,
        'marks_text' => _UPLOADS_MARKED,
        'zip_text' => _UPLOADS_TOZIP,
        'pack_text' => _UPLOADS_TOPACK,
        'move_text' => _UPLOADS_TOMOVE,
        'delete_text' => _DELETE,
        'clear_text' => _UPLOADS_UNMARK,
        'ask_text' => _UPLOADS_MANYDEL,
        'pack_name' => 'archive.zip',
        'self_url' => getAdminFileLink('getAdminFileList', ['dir' => $dir, 'find' => $find, 'unused' => $lone ? 1 : '']),
        'is_unused' => $lone,
        'unused_url' => ($used === null) ? '' : getAdminFileLink('getAdminFileList', ['dir' => $dir, 'find' => $find, 'unused' => $lone ? '' : 1]),
        'unused_text' => _UPLOADS_UNUSED.': '.count($idle),
        'unused_hint' => _UPLOADS_UNUSEDTXT,
        'up_url' => getAdminFileLink('getAdminFileList', ['dir' => str_contains($dir, '/') ? substr($dir, 0, (int)strrpos($dir, '/')) : '']),
        'find_url' => getAdminFileLink('getAdminFileList'),
        'find_text' => $find,
        'back_text' => _BACK,
        'up_text' => _UPLOADS_UP,
        'reload_text' => _UPDATE,
        'filter_text' => _UPLOADS_FILTER,
        'list_text' => _LIST,
        'tiles_text' => _UPLOADS_TILES,
        'post_url' => $afile.'.php',
        'token' => getSiteToken('uploads'),
        'newfile_text' => _UPLOADS_NEWFILE,
        'newdir_text' => _UPLOADS_NEWDIR,
        'apply_text' => _EXECUTE,
        'cancel_text' => _CLOSE,
        'can_create' => !empty($able['create']),
        'can_mkdir' => !empty($able['mkdir']),
        'caps_label' => _UPLOADS_GRANTS,
        'caps_html' => getAdminFileGrants(),
        'info_text' => _OVERALL.': '.count($all).' · '.filterSize($sum),
        'tree_html' => getAdminFileNodes($dir),
        'props_html' => getAdminFileProps($pick),
        'edit_html' => ($pick === '') ? '' : getAdminFileEditor($edit),
        'list_html' => $tpl->getHtmlPart('file-browser-list', [
            'is_empty' => $rows === '',
            'empty_icon' => ($find === '' && !$lone) ? 'folder' : 'search',
            'empty_title' => ($find === '' && !$lone) ? _UPLOADS_EMPTY : _UPLOADS_NOFIND,
            'empty_text' => ($find === '' && !$lone) ? _UPLOADS_EMPTYTXT : _UPLOADS_NOFINDTXT,
            'reset_url' => ($find === '' && !$lone) ? '' : getAdminFileLink('getAdminFileList', ['dir' => $dir]),
            'reset_text' => _FRESET,
            'fail_title' => _UPLOADS_FAIL,
            'fail_text' => _UPLOADS_FAILTXT,
            'retry_text' => _RETRY,
            'can_mark' => $mark,
            'mark_all_text' => _UPLOADS_MARKALL,
            'name_text' => _NAME,
            'kind_text' => _TYPE,
            'size_text' => _SIZE,
            'date_text' => _DATE,
            'acts_text' => _FUNCTIONS,
            'rows_html' => $rows,
            'tiles_html' => $tiles,
        ]),
    ]);
}

# Answer one directory of the system area for an HTMX swap: the body of the browser and, out of band, the crumbs that live in the toolbar it does not replace
function getAdminFileList(): void {
    echo getAdminFileShell();
}

# Answer the properties panel of one object; the panel is what follows the selection, and an object the policy closes answers the empty panel instead of a descriptor
function getAdminFileData(): void {
    echo getAdminFileProps(getAdminFilePath('file'));
}

# Answer one system image for the preview by a fixed type table, sandboxed under a policy that forbids every source, as an SVG carries scripts
function getAdminFilePreview(): void {
    $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif', 'svg' => 'image/svg+xml'];
    $man = getAdminFileManager();
    $path = getAdminFilePath('file');
    $one = $man->getFileData($path);
    if (!isAdmin(true) || $one === [] || !isset($types[$one['extension']]) || !$man->checkFileAccess($path, 'read')) {
        http_response_code(403);
        exit;
    }
    while (ob_get_level() > 0) ob_end_clean();
    Cache::setHeaders('none', $types[$one['extension']]);
    header('Content-Security-Policy: default-src \'none\'; sandbox');
    header('Content-Disposition: inline; filename="'.rawurlencode($one['name']).'"');
    header('Content-Length: '.$one['size']);
    readfile($one['realpath']);
    exit;
}

# Answer one system file as a download through getFileStream(); a direct link would run a script or reach nothing outside public/
function getAdminFileDownload(): void {
    $man = getAdminFileManager();
    $path = getAdminFilePath('file');
    $one = $man->getFileData($path);
    if (!isAdmin(true) || $one === [] || $one['kind'] === 'dir' || !$man->checkFileAccess($path, 'download')) {
        http_response_code(403);
        exit;
    }
    getFileStream($one['realpath'], $one['name']);
}

# Turn every shipped Node profile into an active type, news with starter material; only the mark node => new the installer writes to config/update.php opens it
function addNodeProfiles(int $aid): string {
    global $db, $conf, $fld, $pnt;
    if (($conf['update']['node'] ?? '') !== 'new') return '';
    $srv = new NodeService($db, new NodeContext(0, [], $aid, [], true, true, getIp(), ''), $fld, $pnt);
    $fail = [];
    foreach (glob(BASE_DIR.'/modules/node/profiles/*.json') ?: [] as $file) {
        $name = basename($file, '.json');
        $step = 'import';
        try {
            $type = $srv->addNodeTypeImport((string)file_get_contents($file));
            $step = 'status';
            $type = $srv->updateNodeTypeStatus($type->name, true, $type->version);
            if ($type->name !== 'news') continue;
            $step = 'starter';
            $input = new NodeInput(0, [], 'SLAED', _NODE_START_TITLE, _NODE_START_INTRO, _NODE_START_BODY, [], 0, true, CommentMode::Open, false, null, null, [], [], []);
            $srv->addNode($type, $input, NodeStatus::Published);
        } catch (NodeException $err) {
            $fail[] = $name;
            $info = ['name' => $name, 'step' => $step, 'code' => $err->getCode(), 'path' => $err->getMessage()];
            Logger::addSite('error', 'Node: the installation could not finish a profile', $info);
        }
    }
    if (!setConfigFile('update.php', array_diff_key($conf['update'], ['node' => '']))) Logger::addSite('error', 'Node: the installation mark could not be removed');
    return $fail ? sprintf(_NODE_SETUP, implode(', ', $fail)) : '';
}

# The link tags of the admin theme for a card page, gathered as the core gathers them: the icon, the stylesheets of its vendor packages and its own
function getCardLinks(): string {
    global $tpl;
    $base = PUBLIC_DIR.'/templates/admin/';
    $out = [$tpl->getHtmlFrag('head-link', ['rel' => 'shortcut icon', 'href' => 'templates/admin/images/favicon.svg', 'type' => 'image/svg+xml', 'title' => ''])];
    $list = glob($base.'*.css') ?: [];
    foreach (glob($base.'assets/vendor/*/', GLOB_ONLYDIR) ?: [] as $sub) $list = array_merge($list, glob($sub.'*.css') ?: [], glob($sub.'*/*.css') ?: []);
    foreach (array_merge($list, glob($base.'assets/css/*.css') ?: []) as $file) {
        $out[] = $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => Template::getAssetUrl(substr($file, strlen(PUBLIC_DIR) + 1)), 'type' => '', 'title' => '']);
    }
    return implode("\n", $out);
}

# Render one stop on the card of the admin theme without the panel, as the installer and the light mode of the updater do; a stop outside $names has no counter
function setCardPage(string $title, array $names, int $stop, array $view): void {
    global $conf, $tpl;
    $road = [];
    foreach ($names as $i => $name) $road[] = ['title' => $name, 'is_done' => $stop >= 0 && $i < $stop, 'is_current' => $i === $stop];
    $meta = $tpl->getHtmlFrag('head-title', ['title' => $title])."\n".$tpl->getHtmlFrag('head-meta', ['name' => 'robots', 'content' => 'noindex, nofollow']);
    $script = $tpl->getHtmlFrag('head-script-src', ['src' => Template::getAssetUrl('plugins/system/slaed.js'), 'attr' => 'defer'])."\n"
        .$tpl->getHtmlFrag('head-script-src', ['src' => Template::getAssetUrl('templates/admin/assets/js/admin-ui.js'), 'attr' => 'defer']);
    $logo = 'templates/admin/images/logos/'.basename((string)($conf['admin_logo'] ?? ''));
    if (!is_file(PUBLIC_DIR.'/'.$logo)) $logo = 'templates/admin/images/logos/slaed-logo-wordmark-gradient-blue.svg';
    $link = $tpl->getHtmlFrag('link', ['href' => 'https://slaed.net', 'title' => 'SLAED CMS', 'label' => 'SLAED CMS', 'is_blank' => true]);
    $view += ['road' => $road, 'title' => $names[$stop] ?? $title, 'step_text' => isset($names[$stop]) ? sprintf(_SETUP_STEP, $stop + 1, count($names)) : ''];
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo $tpl->getHtmlPage('setup', $view + ['lang' => substr(_LOCALE, 0, 2), 'mode' => 'auto', 'meta' => $meta, 'links' => getCardLinks(), 'scripts' => $script,
        'adlogo' => $logo, 'adalt' => 'SLAED CMS', 'adtitle' => 'SLAED CMS', 'license' => $link.' © 2005-'.date('Y').' Eduard Laas. Released under MIT License.']);
}

# The notices of the updater for a super-administrator: the release found, red when it fixes security, then the notes of the master, each with its hide button
function getUpdateAlert(): string {
    global $afile, $tpl;
    if (!isAdmin(true)) return '';
    require_once BASE_DIR.'/core/classes/update.php';
    $found = Update::getState()['found'];
    $cont = '';
    $ver = (string)($found['version'] ?? '');
    if (Update::isVersion($ver)) {
        $page = $tpl->getHtmlFrag('link', ['href' => Update::PAGE.$ver, 'title' => _UPDATE_PAGE, 'label' => _UPDATE_PAGE, 'is_blank' => true]);
        $open = $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=update', 'title' => _UPDATE_TITLE, 'label' => _UPDATE_TITLE]);
        $text = sprintf(empty($found['security']) ? _UPDATE_NEW : _UPDATE_SECNEW, htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'));
        $cont .= $tpl->getHtmlFrag('alert', ['type' => empty($found['security']) ? 'info' : 'error', 'text' => $text.' '.$page.' · '.$open]);
    }
    $lang = (substr(_LOCALE, 0, 2) === 'ru') ? 'ru' : 'en';
    $link = static fn(array $hit): string => preg_match('#^https://(slaed\.net/|github\.com/SLAED-CMS/)#', $url = html_entity_decode($hit[0], ENT_QUOTES, 'UTF-8')) === 1
        ? $tpl->getHtmlFrag('link', ['href' => $url, 'title' => $url, 'label' => $url, 'is_blank' => true]) : $hit[0];
    foreach (Update::getNoteList() as $one) {
        $text = (string)preg_replace_callback('#https?://[^\s<>"\']+#', $link, htmlspecialchars($one[$lang], ENT_QUOTES, 'UTF-8'));
        $hide = getTplPostButton(['name' => 'update', 'op' => 'hide', 'id' => $one['id'], 'refer' => 1], 'eye-slash', _UPDATE_HIDE);
        $cont .= $tpl->getHtmlFrag('alert', ['type' => ($one['level'] === 'danger') ? 'error' : $one['level'], 'text' => $text.$hide]);
    }
    return $cont;
}
