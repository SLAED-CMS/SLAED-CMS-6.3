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
            imagerectangle($image, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+1, 250-$w+3, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+2, 249, $gray);
            $w = ($max1 > 0) ? round((230 / $max1) * $day[1]) : 0;
            if ($w < 5) $w = 1;
            $off = 120;

            imagefilledrectangle($image, $off+$conf['statistic']['bet']*$i+1, 250-$w+1, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+3, 249, $wblue);
            imagerectangle($image, $off+$conf['statistic']['bet']*$i,250-$w, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+3, 249, $black);
            imagerectangle($image, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+4, 250-$w+4, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+5, 249, $black);
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

# Render one row of the settings window: the caption, the icon and the list of one axis, in the form that posts it to the handler that already owns that axis
# The three axes differ in the handler, the field name, the icon, the caption and where the options come from, so they are one table rather than three functions
# An axis with nothing to offer returns an empty string and the window draws one row fewer: the language axis when the site is not multilingual, any axis with no options
function getAdminSettingsRow(string $axis): string {
    global $admin, $conf, $afile, $locale, $tpl;
    $opts = '';
    if ($axis === 'mode') {
        $mode = getThemeMode();
        $icon = ['light' => 'sun', 'auto' => 'circle-half', 'dark' => 'moon'];
        $name = ['light' => _MODE_LIGHT, 'auto' => _MODE_AUTO, 'dark' => _MODE_DARK];
        foreach (['light', 'auto', 'dark'] as $step) {
            $opts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $step, 'label_text' => $name[$step], 'is_selected' => $step === $mode]);
        }
        $row = ['op' => 'mode', 'field' => 'mode', 'icon' => $icon[$mode], 'title' => _THEME, 'hint' => $name[$mode]];
    } elseif ($axis === 'editor') {
        $edkey = (string)($admin[3] ?? $conf['editor']['admin'] ?? 'plain');
        $row = ['op' => 'changeeditor', 'field' => 'editor', 'icon' => 'pencil-square', 'title' => _EDITOR, 'hint' => _EDITOR];
    } else {
        if (($conf['multilingual'] ?? 0) != 1) return '';
        foreach (getLanguageList() as $lang => $label) {
            $opts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $lang, 'label_text' => $label, 'is_selected' => $lang === $locale]);
        }
        $row = ['op' => 'newlang', 'field' => 'newlang', 'icon' => 'translate', 'title' => _LANGUAGE, 'hint' => _LANGUAGE];
    }
    $safe = htmlspecialchars($row['hint'], ENT_QUOTES, 'UTF-8');
    $send = 'this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()';
    $attr = 'onchange="'.$send.'" title="'.$safe.'" aria-label="'.$safe.'"';
    if ($axis === 'editor') {
        $pick = Editor::getSelect('editor', $edkey, 'content', 'admin', $attr);
    } else {
        if ($opts === '') return '';
        $pick = $tpl->getHtmlFrag('select', ['name_attr' => $row['field'], 'select_class' => '', 'select_attr' => $attr, 'options_html' => $opts]);
    }
    $hide = ['op' => $row['op'], 'refer' => '1', 'token' => getSiteToken($row['op'])];
    $html = '';
    foreach ($hide as $key => $val) $html .= $tpl->getHtmlFrag('hidden', ['name_attr' => $key, 'value_attr' => $val, 'input_attr' => '']);
    return $tpl->getHtmlFrag('settings-row', [
        'action' => $afile.'.php',
        'hidden' => $html,
        'is_mode' => $axis === 'mode',
        'icon_name' => $row['icon'],
        'title' => $row['title'],
        'pick_html' => $pick,
    ]);
}

# Render the settings window of the panel: the three answers an administrator changes from any screen, each row posting to the handler that already owns it
# Nothing is stored here and no handler learns about the others: the colour mode writes its cookie, the editor writes the administrator row, the language writes its own cookie
function getAdminSettingsWindow(): string {
    global $tpl;
    $rows = getAdminSettingsRow('mode').getAdminSettingsRow('editor').getAdminSettingsRow('language');
    return $tpl->getHtmlFrag('window', [
        'win_id' => 'sl-settings',
        'size_class' => 'sl-modal-sm',
        'icon_name' => 'sliders',
        'title_text' => _FUNCTIONS,
        'close_text' => _CLOSE,
        'body_html' => $rows,
    ]);
}

# Render the icon picker window: one window for every screen that offers an icon field, so the four callers name the
# screen they stand on and nothing about the window itself
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

# Render the standard admin top menu.
function getAdminTopMenu(): string {
    global $admin, $afile, $tpl;
    $items = !isAdmin(true) ? [
        ['href' => '#', 'label' => _HELLO.', '.substr((string)($admin[1] ?? ''), 0, 25).'!', 'blank' => false, 'icon' => 'person-badge'],
        ['href' => $afile.'.php', 'label' => _HOME, 'blank' => false, 'icon' => 'house-door'],
        ['href' => '/', 'label' => _SITE, 'blank' => true, 'icon' => 'globe2', 'split' => true],
        ['href' => 'index.php?name=account', 'label' => _ACCOUNT, 'blank' => true, 'icon' => 'person'],
        ['href' => $afile.'.php?op=logout', 'label' => _LOGOUT, 'blank' => false, 'icon' => 'box-arrow-right', 'split' => true],
    ] : [
        ['href' => $afile.'.php', 'label' => _HOME, 'blank' => false, 'icon' => 'house-door'],
        ['href' => $afile.'.php?name=blocks', 'label' => _BLOCKS, 'blank' => false, 'icon' => 'grid-3x3-gap'],
        ['href' => $afile.'.php?name=modules', 'label' => _MODULES, 'blank' => false, 'icon' => 'gpu-card'],
        ['href' => $afile.'.php?name=categories', 'label' => _CATEGORIES, 'blank' => false, 'icon' => 'folder'],
        ['href' => '/', 'label' => _SITE, 'blank' => true, 'icon' => 'globe2', 'split' => true],
        ['href' => 'index.php?name=account', 'label' => _ACCOUNT, 'blank' => true, 'icon' => 'person'],
        ['href' => $afile.'.php?op=logout', 'label' => _LOGOUT, 'blank' => false, 'icon' => 'box-arrow-right', 'split' => true],
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

# Return standard variables used by admin layouts.
function getAdminLayoutVars(): array {
    global $db, $afile;
    if (!isAdmin()) {
        $login = ($db->getSqlRowCount($db->getSqlQuery('SELECT 1 FROM '.PREFIX_DB.'_admins LIMIT 1')) == 0) ? _ADMINLOGIN_NEW : _ADMINLOGIN;
        return ['login' => $login];
    }
    return [
        'settings_win' => getAdminSettingsWindow(),
        'menu' => getAdminTopMenu(),
        'admin_blocks' => getAdminPanelBlocks().getAdminInfo().adminblock(),
    ];
}

# Render one sidebar pending-count row: active-content link plus a live COUNT chip; title and label are constant names resolved here for the active module
# A caller whose table is owned by a subsystem class hands the number over in $num and leaves the table empty, so the row can be built without this helper reaching that table
function getAdminCountRow(string $href, string $titlec, string $labelc, string $icon, string $table = '', string $where = '', ?int $num = null): string {
    global $db, $afile, $tpl;
    if ($num === null) [$num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_'.$table.($where !== '' ? ' WHERE '.$where : '')));
    return $tpl->getHtmlFrag('block-sidebar-count-row', [
        'label_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?'.$href, 'title' => constant($titlec), 'label' => constant($labelc), 'icon_name' => $icon]),
        'value_html' => $tpl->getHtmlFrag('inline-badge', ['chip_tone' => (int)$num >= 1 ? 'warn' : 'success', 'label' => (string)$num]),
    ]);
}

# Build the admin sidebar info blocks: pending-content counters and waiting content; the editor moved to the settings window and is offered there alone
function getAdminInfo(): string {
    global $com, $panel, $tpl;
    if (isAdmin()) {
        $ablocks = '';
        if ($panel) {
            $groups = [
                'account' => [['name=account&op=newuser', '_NEW_USER', '_USERS', 'person-plus', 'users_temp', '']],
                'faq'     => [['name=faq&status=1', '_FAQ', '_FAQ', 'question-circle', 'faq', "status = '0'"]],
                'files'   => [
                    ['name=files&status=1', '_FILES', '_FILES', 'file-earmark-plus', 'files', "status = '0'"],
                    ['name=files&status=2', '_BROCFILES', '_BROCFILES', 'file-earmark-x', 'files', "status = '2'"],
                ],
                'help'    => [['name=help', '_HELP', '_HELP', 'info-circle', 'help', "pid = '0' AND status = '0'"]],
                'jokes'   => [['name=jokes&status=1', '_JOKES', '_JOKES', 'emoji-laughing', 'jokes', "status = '0'"]],
                'links'   => [
                    ['name=links&status=1', '_LINKS', '_LINKS', 'link-45deg', 'links', "status = '0'"],
                    ['name=links&status=2', '_BROCLINKS', '_BROCLINKS', 'slash-circle', 'links', "status = '2'"],
                ],
                'media'   => [
                    ['name=media&status=1', '_MEDIA', '_MEDIA', 'camera', 'media', "status = '0'"],
                    ['name=media&status=2', '_BROCMFILES', '_BROCMFILES', 'camera-video-off', 'media', "status = '2'"],
                ],
                'news'    => [['name=news&status=1', '_NEWS', '_NEWS', 'newspaper', 'news', "status = '0'"]],
                'pages'   => [['name=pages&status=1', '_PAGES', '_PAGES', 'file-richtext', 'pages', "status = '0'"]],
                'shop'    => [
                    ['name=shop&op=clients', '_CLIENTS', '_CLIENTS', 'bag-plus', 'clients', "status = '2'"],
                    ['name=shop&op=partners', '_PARTNERS', '_PARTNERS', 'shop', 'partners', "status = '2'"],
                ],
                'whois'   => [['name=whois&status=1', '_WHOIS', '_WHOIS', 'person-badge', 'whois', "status = '0'"]],
            ];
            $newRows = [];
            foreach ($groups as $mod => $defs) {
                if (!is_active($mod) || !is_admin_modul($mod)) continue;
                foreach ($defs as $d) $newRows[] = getAdminCountRow(...$d);
            }
            $ablocks = $tpl->getHtmlPart('block-sidebar', ['title' => _NEW, 'icon_name' => 'stars', 'content_html' => $tpl->getHtmlFrag('block-content', ['is_sidebar_count_list' => true, 'content' => implode('', $newRows)]), 'id' => '3', 'close' => _OPCL]);
            $waitingRows = [getAdminCountRow('name=comments&status=1', '_COMMENTS', '_COMMENTS', 'chat-dots', '', '', $com->getStatusCount(CommentStatus::Pending))];
            $ablocks .= $tpl->getHtmlPart('block-sidebar', ['title' => _WAITINGCONT, 'icon_name' => 'hourglass-split', 'content_html' => $tpl->getHtmlFrag('block-content', ['is_sidebar_count_list' => true, 'content' => implode('', $waitingRows)]), 'id' => '4', 'close' => _OPCL]);
        }
        return $ablocks;
    }
    return '';
}

# Return the database server version string reported by the SQL engine
function getDbVersion() {
    global $db;
    list($dbv) = $db->getSqlRow($db->getSqlQuery('SELECT VERSION()'));
    return $dbv;
}

# Render the admin category list grouped by module as an indented tree with drag ordering, collapsible groups and dial actions
function getAdminCategoryList(string $modul = '', int $obj = 0): string {
    global $db, $afile, $tpl;
    $modul = filterVar($modul);
    $where = ($modul) ? 'WHERE modul = :modul' : '';
    $params = ($modul) ? ['modul' => $modul] : [];
    $modlink = ($modul) ? '&modul='.$modul : '';
    $tabs = ['faq' => '_faq', 'files' => '_files', 'forum' => '_forum', 'help' => '_help', 'jokes' => '_jokes', 'links' => '_links', 'media' => '_media', 'news' => '_news', 'pages' => '_pages', 'shop' => '_products'];
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
        if (isset($tabs[$cmod])) {
            $count = $db->getSqlQuery('SELECT cid, COUNT(id) FROM '.PREFIX_DB.$tabs[$cmod].' GROUP BY cid');
            while ([$ncid, $cnt] = $db->getSqlRow($count)) $nums[(int)$ncid] = (int)$cnt;
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
            $keep = ['name' => 'categories', 'id' => $cid] + ($cmod !== '' ? ['modul' => $cmod] : []);
            $dial = [
                getTplPostAction(['op' => 'change', 'act' => $cat['status']] + $keep, 'power', $cat['status'] ? _DEACTIVATE : _ACTIVATE),
                [
                    'href' => $afile.'.php?name=categories&op=edit&cid='.$cid.$modlink,
                    'icon_name' => 'pencil',
                    'title' => _FULLEDIT,
                ],
            ];
            if (!$pnum && !$subs) {
                $dial[] = getTplPostAction(['op' => 'delete'] + $keep, 'trash', _ONDELETE, _DELETE.' "'.$cat['title'].'"?');
            }
            $rows[] = $tpl->getHtmlFrag('table-row', ['attr' => 'data-sl-drag-id="'.$cid.'" data-sl-drag-group="'.$cmod.'-'.$cat['parent'].'" data-sl-drag-scope="'.$cmod.'" data-sl-drag-parent="'.$cat['parent'].'"', 'cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['content_html' => (string)$cid],
                    ['content_html' => $name],
                    ['content_html' => (string)$pnum],
                    ['content_html' => $img],
                    ['content_html' => $tpl->getHtmlFrag('span', ['is_drag_handle' => true]).' '.$num],
                    ['content_html' => ad_status('', $cat['status']), 'is_col_status' => true],
                    ['content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _EDITOR, 'dial' => $dial]), 'is_col_actions' => true],
                ],
            ])]);
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
    if (checkSiteToken()) {
        $ids = array_values(array_filter(array_map('intval', explode('-', getVar('post', 'ids', 'var')))));
        if ($ids) {
            [$cmod] = $db->getSqlRow($db->getSqlQuery('SELECT modul FROM '.PREFIX_DB.'_categories WHERE id = :cid', ['cid' => $ids[0]]));
            $ordern = 0;
            foreach ($ids as $cid) {
                $ordern++;
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET ordern = :ordern WHERE id = :cid AND modul = :cmod', ['ordern' => $ordern, 'cid' => $cid, 'cmod' => $cmod]);
            }
        }
    }
    getAdminCategoryList(filterVar(getVar('get', 'mod', 'var', '')), 0);
}

function catacess(string $name, string $class, string $selected, int $limit): string {
    global $db, $tpl;
    $gids = explode('|', $selected);
    $opts = '';
    if ($limit < 1) {
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => '0|0',
            'label_text' => _ALL,
            'is_selected' => $selected == '0|0',
        ]);
    }
    if ($limit < 2) {
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => '1|0',
            'label_text' => _USERS,
            'is_selected' => $selected == '1|0',
        ]);
        $where  = '';
        $params = [];
    } else {
        $where  = "WHERE extra = '1'";
        $params = [];
    }
    $result = $db->getSqlQuery('SELECT id, name, extra FROM '.PREFIX_DB.'_groups '.$where.' ORDER BY extra, points', $params);
    while (list($id, $gname, $extra) = $db->getSqlRow($result)) {
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
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => '2|'.$id,
            'label_text' => $title,
            'is_selected' => $sel !== '',
        ]);
    }
    $opts .= $tpl->getHtmlFrag('select-option', [
        'value_attr' => '3|0',
        'label_text' => _ADMIN,
        'is_selected' => $selected == '3|0',
    ]);
    return $tpl->getHtmlFrag('select', [
        'name_attr' => $name.'[]',
        'options_html' => $opts,
        'select_attr' => 'multiple="multiple"',
    ]);
}

function scatacess($auth) {
    $gids = explode('|', $auth);
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

# Render the admin block list grouped by position with drag ordering, a trailing free (infly) section, expiry state and action links
function getAdminBlockList(): string {
    global $db, $afile, $tpl;
    $posmap = [
        'l' => [_LEFT, _LEFTBLOCK],
        'r' => [_RIGHT, _RIGHTBLOCK],
        'c' => [_CENTERUP, _CENTERBLOCK],
        'd' => [_CENTERDOWN, _CENTERBLOCK],
        'b' => [_BANNERUP, _BANNER],
        'f' => [_BANNERDOWN, _BANNER],
    ];
    $rows = [];
    $free = [];
    $group = '';
    $result = $db->getSqlQuery('SELECT id, bkey, title, url, bpos, weight, status, lang, bfile, view, expire, action, which FROM '.PREFIX_DB.'_blocks ORDER BY bpos, weight');
    while ([$bid, $bkey, $title, $url, $bpos, $weight, $active, $lang, $bfile, $view, $expire, $action, $which] = $db->getSqlRow($result)) {
        if (($expire && $expire < time()) || (!$active && $expire)) {
            if ($action == 'd') {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET status = 0, expire = 0 WHERE id = :bid', ['bid' => $bid]);
            } elseif ($action == 'r') {
                $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_blocks WHERE id = :bid', ['bid' => $bid]);
            }
        }
        $marks = explode(',', (string)$which);
        $isfly = in_array('infly', $marks, true);
        $isfix = $isfly && in_array('flyfix', $marks, true);
        $exp = intval($expire - time());
        $exp = ($exp > 0) ? getDuration($exp) : _UNLIMITED;
        $tips = [['label' => _NAME, 'value' => $title, 'is_last' => false]];
        if ($lang !== '') $tips[] = ['label' => _LANGUAGE, 'value' => getLangName($lang), 'is_last' => false];
        $tips[] = ['label' => _PURCHASED, 'value' => $exp, 'is_last' => true];
        if ($bkey == '') {
            $type = ($url) ? 'RSS/RDF' : 'HTML';
            if ($bfile != '') $type = _BLOCKFILE2;
        } else {
            $type = _BLOCKSYSTEM;
        }
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
            $order = $tpl->getHtmlFrag('inline-badge', ['chip_tone' => 'accent', 'is_infly' => !$isfix, 'is_flyfix' => $isfix, 'label' => _INFLY, 'title_text' => $isfix ? _FLY_FIX : _INFLY]);
        } else {
            $order = $tpl->getHtmlFrag('span', ['is_drag_handle' => true]).' '.$weight;
        }
        $row = $tpl->getHtmlFrag('table-row', ['attr' => $isfly ? '' : 'data-sl-drag-id="'.$bid.'" data-sl-drag-group="'.$bpos.'" data-sl-drag-scope="'.$bpos.'"', 'cells_html' => $tpl->getHtmlFrag('table-cells', [
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
                ['content_html' => ad_status('', $active), 'is_col_status' => true],
                ['content_html' => $tpl->getHtmlFrag('dial', [
                    'dial_title' => _EDITOR,
                    'dial' => [
                        getTplPostAction(['name' => 'blocks', 'op' => 'change', 'id' => $bid, 'act' => $active], 'power', $active ? _DEACTIVATE : _ACTIVATE),
                        [
                            'href' => $afile.'.php?name=blocks&op=edit&id='.$bid,
                            'icon_name' => 'pencil',
                            'title' => _FULLEDIT,
                        ],
                        getTplPostAction(['name' => 'blocks', 'op' => 'delete', 'id' => $bid], 'trash', _ONDELETE, _DELETE.' "'.$title.'"?'),
                    ],
                ]), 'is_col_actions' => true],
            ],
        ])]);
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
    if (checkSiteToken()) {
        $ids = array_values(array_filter(array_map('intval', explode('-', getVar('post', 'ids', 'var')))));
        if ($ids) {
            [$bpos] = $db->getSqlRow($db->getSqlQuery('SELECT bpos FROM '.PREFIX_DB.'_blocks WHERE id = :bid', ['bid' => $ids[0]]));
            $weight = 0;
            foreach ($ids as $bid) {
                $weight++;
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET weight = :weight WHERE id = :bid AND bpos = :bpos', ['weight' => $weight, 'bid' => $bid, 'bpos' => $bpos]);
            }
        }
    }
    echo getAdminBlockList();
}

# Favorites list view
function getAdminFavoriteList(int $obj = 0): string {
    global $db, $conf, $tpl;
    $newlistnum = intval($conf['favorites']['anum']);
    $cid = getVar('get', 'num', 'num', getVar('get', 'cid', 'num', 1));
    $offset = ($cid - 1) * $newlistnum;
    $offset = intval($offset);
    list($fav_num) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_favorites'));
    
    $result = $db->getSqlQuery('SELECT id, modul FROM '.PREFIX_DB.'_favorites ORDER BY id DESC LIMIT '.intval($offset).', '.intval($newlistnum));
    while (list($id, $modul) = $db->getSqlRow($result)) $fmassiv[$modul][] = $id;
    
    if (is_array($fmassiv)) {
        foreach ($fmassiv as $key => $val) {
            $ids = array_values(array_filter(array_map('intval', $val), static fn($v) => $v > 0));
            if (!$ids) continue;
            $pp = [];
            $pm = [];
            foreach ($ids as $k => $v) {
                $ph = 'f'.$k;
                $pp[] = ':'.$ph;
                $pm[$ph] = $v;
            }
            $in = implode(', ', $pp);
            $numl = count($val);
            if ($key == 'faq') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title, u.name FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_faq AS n ON (f.fid = n.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title, $uname) = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title, $uname];
            } elseif ($key == 'files') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title, u.name FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_files AS n ON (f.fid = n.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title, $uname) = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title, $uname];
            } elseif ($key == 'forum') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title, u.name FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_forum AS n ON (f.fid = n.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title, $uname) = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title, $uname];
            } elseif ($key == 'help') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title, u.name FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_help AS n ON (f.fid = n.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title, $uname) = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title, $uname];
            } elseif ($key == 'links') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title, u.name FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_links AS n ON (f.fid = n.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title, $uname) = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title, $uname];
            } elseif ($key == 'media') {
                $conf['media'] = $conf['media'] ?? [];
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title, n.subtitle, u.name FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_media AS n ON (f.fid = n.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title, $subtitle, $uname) = $db->getSqlRow($result)) {
                    $title = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
                    $ffmassiv[] = [$id, $fid, $modul, $title, $uname];
                }
            } elseif ($key == 'news') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title, u.name FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_news AS n ON (f.fid = n.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title, $uname) = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title, $uname];
            } elseif ($key == 'pages') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title, u.name FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_pages AS n ON (f.fid = n.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title, $uname) = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title, $uname];
            } elseif ($key == 'shop') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title, u.name FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_products AS n ON (f.fid = n.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while (list($id, $fid, $modul, $title, $uname) = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title, $uname];
            }
        }
        if ($ffmassiv) {
            $rows = [];
            foreach ($ffmassiv as $key => $val) {
                $id = $val[0];
                $fid = $val[1];
                $modul = $val[2];
                $title = getDecodedText((string)$val[3]);
                $uname = ($val[4]) ? user_info($val[4]) : _ANONYM;
                $delattr = getTplPostVals(['name' => 'favorites', 'op' => 'delete', 'id' => $id, 'num' => $cid], '#repadminFavoriteList');
                $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                    'cells' => [
                        ['content_html' => (string) $id],
                        ['content_html' => $tpl->getHtmlFrag('inline-badge', ['is_note' => true, 'label' => $title, 'title_text' => $title])],
                        ['content_html' => getModuleName($modul)],
                        ['content_html' => $uname],
                        ['content_html' => $tpl->getHtmlFrag('dial', [
                            'dial_title' => _FUNCTIONS,
                            'dial' => [[
                                'href' => 'index.php?name='.$modul.'&op=view&id='.$fid.'#'.$fid,
                                'icon_name' => 'eye',
                                'title' => _MVIEW,
                            ], [
                                'href' => '#',
                                'icon_name' => 'trash',
                                'title' => _ONDELETE,
                                'link_attr' => $delattr,
                                'confirm_text' => _DELETE.' "'.$title.'"?',
                            ]],
                        ]), 'is_col_actions' => true],
                    ],
                ])]);
            }
            $cont = $tpl->getHtmlFrag('table', [
                'head' => [
                    ['content' => _ID],
                    ['content' => _TITLE],
                    ['content' => _MODUL],
                    ['content' => _POSTEDBY],
                    ['content' => _FUNCTIONS, 'nosort' => true],
                ],
                'rows_html' => implode('', $rows),
                'is_wrapless' => true,
            ]);
            $cont .= getTplPager([
                'table' => '_favorites',
                'field' => 'id',
                'limit' => $newlistnum,
                'maxpg' => intval($conf['favorites']['anump']),
                'url' => 'name=favorites&',
                'n' => 'num',
                'target_id' => 'repadminFavoriteList',
                'push_url' => true,
            ]);
        } else {
            $cont = $tpl->getHtmlFrag('alert', ['text' => _NO_INFO]);
        }
    } else {
        $cont = $tpl->getHtmlFrag('alert', ['text' => _NO_INFO]);
    }
    if ($obj) { return $cont; }
    echo $cont;
    return '';
}

# Private messages list view
# The rows come from the private-message subsystem, which owns that table alone, so this list restates no mailbox predicate and filters no state: an administrator sees the deleted copies too
# The state of one row is the one the four columns add up to, and the page is read from the request itself so a list rebuilt by a POST action stays on the page it was asked from
# An administrator reads a message through the renderer its recipient does: the body is source rendered safe in the format its own row names, the title escaped by its template
function getAdminPrivateList(int $obj = 0): string {
    global $afile, $conf, $tpl, $prs, $prv;
    $cid = getVar('req', 'num', 'num', getVar('req', 'cid', 'num', 1));
    $data = $prv->getAdminList($cid);
    if ($data['rows']) {
        $rows = [];
        foreach ($data['rows'] as $one) {
            $title = $one['title'];
            $tone = match ($one['state']) {'delin', 'delout' => 'danger', 'saved' => 'accent', 'read' => 'success', default => 'warn'};
            $note = match ($one['state']) {
                'delin' => (string)_PRIVAT_DELIN,
                'delout' => (string)_PRIVAT_DELOUT,
                'saved' => (string)_PRMOVE,
                'read' => (string)_PROLD,
                default => (string)_PROUTNEW,
            };
            $info = $prs->filterContent($one['body'], true, 'privat', 0, 'breaks');
            $delattr = getTplPostVals(['name' => 'privat', 'op' => 'delete', 'id' => $one['id'], 'num' => $data['page']], '#repadminPrivateList');
            $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['content_html' => (string)$one['id']],
                    ['content_html' => $tpl->getHtmlFrag('popover', ['content_html' => $info]).$tpl->getHtmlFrag('inline-badge', ['is_note' => true, 'label' => $title, 'title_text' => $title])],
                    ['content_html' => ($one['nameout'] !== '') ? user_info($one['nameout']) : (string)_ANONYM],
                    ['content_html' => ($one['namein'] !== '') ? user_info($one['namein']) : (string)_ANONYM],
                    ['content_html' => format_time($one['time'], _TIMESTRING)],
                    ['content_html' => $tpl->getHtmlFrag('inline-badge', ['chip_tone' => $tone, 'label' => $note, 'title_text' => $note]), 'is_col_status' => true],
                    ['content_html' => $tpl->getHtmlFrag('dial', [
                        'dial_title' => _FUNCTIONS,
                        'dial' => [[
                            'href' => '#',
                            'icon_name' => 'trash',
                            'title' => _ONDELETE,
                            'link_attr' => $delattr,
                            'confirm_text' => _DELETE.' "'.$title.'"?',
                        ]],
                    ]), 'is_col_actions' => true],
                ],
            ])]);
        }
        $cont = $tpl->getHtmlFrag('table', [
            'head' => [
                ['content' => _ID],
                ['content' => _TITLE],
                ['content' => _PRSE],
                ['content' => _PRRE],
                ['content' => _DATE],
                ['content' => _STATUS, 'nosort' => true],
                ['content' => _FUNCTIONS, 'nosort' => true],
            ],
            'rows_html' => implode('', $rows),
            'is_wrapless' => true,
        ]);
        $cont .= getTplPagerView($data['page'], $data['pages'], intval($conf['privat']['anump']), static fn(int $i): array => [
            'query' => $afile.'.php?name=privat&num='.$i,
            'target_id' => 'repadminPrivateList',
            'push_url' => 'true',
        ], ['count' => $data['total'], 'limit' => $data['limit'], 'page' => $data['limit']]);
    } else {
        $cont = $tpl->getHtmlFrag('alert', ['text' => _NO_INFO]);
    }
    if ($obj) { return $cont; }
    echo $cont;
    return '';
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
        while (list($id, $title) = $db->getSqlRow($result)) {
            $opts .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$id, 'label_text' => $title, 'is_selected' => $selectedId == $id]);
        }
    }
    return $tpl->getHtmlFrag('select', ['name_attr' => $selectName, 'options_html' => $opts]);
}

# Split one SQL script into the statements a driver can take one at a time, honouring quoting, comments and the DELIMITER directive of a stored routine
# Three callers run scripts written by hand and none of them may split on a bare semicolon: the Inquiry tab, the module installer and setup/index.php
# A statement is returned exactly as the file wrote it: an escape inside a string literal is part of the statement and is never unwrapped on the way through
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

# Drop the comment block a statement carries in front of its first SQL token, which is what a commented migration file puts there
# The comment is not the statement: leaving it in front makes the preview show the file header instead of the query and makes getSqlinfo() read the wrong type
# A block that turns out to be nothing but comments is returned empty, so the caller drops it instead of sending a query with no statement in it
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

# Describe one statement for the report: the leading keyword and the table it addresses, both read from the head of the statement and never from its body
# The table is anchored to the keyword that introduces it, because the first backtick of a statement is just as often a column of an index list or a fragment of a procedure body
function getSqlinfo(string $sql): array {
    $type = 'SQL';
    $table = '';
    $lead = '(?:IF\s+(?:NOT\s+)?EXISTS\s+)?';
    $verb = 'INSERT\s+INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM|TRUNCATE(?:\s+TABLE)?|ALTER\s+TABLE|CREATE\s+(?:TEMPORARY\s+)?TABLE|DROP\s+TABLE|SELECT\b.*?\bFROM';
    if (preg_match('/^\s*([A-Za-z]+)/', $sql, $mass)) $type = strtoupper($mass[1]);
    if (preg_match('/^\s*(?:'.$verb.')\s+'.$lead.'`?([A-Za-z0-9_$]+)`?/is', $sql, $mass)) $table = $mass[1];
    return ['type' => $type, 'table' => $table];
}

# Fill the installation placeholders one SQL script carries, which is the same set the Inquiry tab and the module installer both feed to the driver
# A module table.sql declares its storage engine and collation through these, so a script run without them reaches the driver as invalid SQL
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

# Return the tables one SQL script addresses, in the order it names them and without repeats, optionally only those introduced by one statement type
# The module list asks for the CREATE set to decide whether a module is installed, and the uninstall asks for the same set, because those are the tables the script owns
function getSqlFileTables(string $sql, string $verb = ''): array {
    $out = [];
    foreach (getSqlbatch(getSqlFilled($sql))['statements'] as $one) {
        $info = getSqlinfo($one);
        if ($info['table'] === '' || ($verb !== '' && $info['type'] !== $verb)) continue;
        if (!in_array($info['table'], $out, true)) $out[] = $info['table'];
    }
    return $out;
}

# Return whether one table exists in the current database, asked of the catalogue rather than of the table itself
# A module that is not installed would otherwise make the list page run a query against a missing table and write an SQL error for every render
function checkSqlTable(string $name): bool {
    global $db;
    if ($name === '') return false;
    $row = $db->getSqlRow($db->getSqlQuery(
        'SELECT COUNT(*) AS num FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :name',
        ['name' => $name]
    ));
    return (int)($row['num'] ?? 0) > 0;
}

# Return which of the two administrative file areas the current request works in; a page names its own area once, and every route of it reads the name back out of the request
# The client never names a root, only which of the two screens it is on, and what that screen is allowed to mean is decided by the context the file layer is built with
function getAdminFileMode(string $set = ''): string {
    static $ctx = '';
    if ($set !== '') $ctx = $set;
    if ($ctx === '') $ctx = (getVar('get', 'ctx', 'word', '') === 'uploads' || getVar('post', 'ctx', 'word', '') === 'uploads') ? 'uploads' : 'system';
    return $ctx;
}

# Return the file context of one administrative area; core/classes carries no runtime autoload, so the file layer is required on first use and both roots are named here alone
# The upload area is rooted at the whole upload tree and the system area at BASE_DIR: the client passes a path relative to the context and never the physical root itself
function getAdminFileManager(string $mode = ''): FileManager {
    static $mans = [];
    $mode = ($mode === '') ? getAdminFileMode() : $mode;
    if (!isset($mans[$mode])) {
        require_once BASE_DIR.'/core/classes/filemanager.php';
        $mans[$mode] = ($mode === 'uploads') ? new FileManager('uploads', UPLOADS_DIR) : new FileManager('system', BASE_DIR);
    }
    return $mans[$mode];
}

# Return the upload rule one browser path publishes under: the first segment of the path names the module, so a directory of the tree carries the settings of its own module
# The shared quota of a module counts what its users stored and is not a limit on the administration, so the one rule value the catalogue drops is that ceiling
function getAdminUploadRule(string $dir): array {
    $mod = ($dir === '') ? '' : explode('/', $dir)[0];
    return array_merge(getUploadRuleData($mod), ['maxquota' => 0]);
}

# Return one relative path of the request unfiltered, because what a path means is decided by the canonicalization of the file layer and never by a filter of the input side
# A reading route carries its path in the query and a write carries it in the body, so the source is named by the caller and the two never read each other by accident
function getAdminFilePath(string $key, string $src = 'get'): string {
    $path = getVar($src, $key, 'raw', '');
    return is_string($path) ? mb_substr(trim($path), 0, 512) : '';
}

# Return the address of one browser route with the values it carries and the ajax token every numeric go route is asked for before it answers anything
# The area of the screen travels with every address, because the answering route builds its own context and must not fall back to the system one on a catalogue request
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

# Return the address one image is shown at: a file of the upload tree is public and is served by the web server, and a system file exists only behind the route that checks it again
# A listing asks for the stored thumbnail where one was published, because a directory of full-sized photographs drawn at icon size is the one place this screen can waste a network
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

# Render the tree of the system area along the open path alone: the root level and the children of every ancestor of the current directory, and never a walk of the whole site
# Each level is spliced in under the node it belongs to, so the depth of the request equals the depth of the path and a closed directory never contributes a node
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

# Render the capability row of the context: what the interface prints is the answer of the file layer, so no screen derives a permission from a role of its own
# A capability this stage does not route yet is still shown, because the row states what the context allows and not which button happens to exist
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

# Render the fan of one object: what it offers is the capability set of its own descriptor, so an operation the context forbids is not drawn at all instead of failing on the route
# Reading actions are addresses and the two that change nothing but the name of a thing open the shared form, while a delete and a pack are POST forms of the module with its token
# The question of a delete names the object, and a critical path adds what its loss costs and that the journal keeps the answer, because a stray click there stops the site
function getAdminFileActs(array $one): string {
    global $afile, $tpl;
    $able = $one['capabilities'];
    $ctx = getAdminFileMode();
    $path = $one['path'];
    $name = $one['name'];
    $dir = str_contains($path, '/') ? substr($path, 0, (int)strrpos($path, '/')) : '';
    $ask = _DELETE.' "'.$name.'"?'.(empty($one['critical']) ? '' : ' '._UPLOADS_CRITDEL);
    $dial = [];
    if (!empty($able['edit'])) $dial[] = ['href' => $afile.'.php?name=uploads&op=fmedit&file='.rawurlencode($path), 'run' => 'fmedit', 'icon_name' => 'pencil-square', 'title' => _EDIT];
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

# Render the properties of one object from its descriptor: the absolute path is shown because an administrator is full-handed, and a critical path says so next to its own name
# An object that does not exist or that the path policy closes answers the empty panel, so a closed path is refused here exactly as it is refused on every other route
# A source file also states how many lines it holds and which version it carries, and both come from reading it once, which is why no listing ever asks for them
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
    // Windows answers no account for a file at all, so the row is written only where the host has one to give
    if ($one['owner'] !== '') $rows[] = ['label' => _UPLOADS_USER, 'value' => $one['owner']];
    if ($body !== []) $rows[] = ['label' => _UPLOADS_VERSION, 'value' => substr($body['version'], 0, 6)];
    if ($one['managed']) $rows[] = ['label' => _UPLOADS_OWNER, 'value' => FileManager::getFileOwner($one['name']) ?? _UPLOADS_OWNSITE];
    $warn = empty($one['critical']) ? '' : _UPLOADS_CRIT;
    if ($warn === '' && $one['managed'] && !empty($one['capabilities']['rename'])) $warn = _UPLOADS_LINKWARN;
    // The panel offers the fan of the object and nothing beside it: the fan already answers for every capability the
    // descriptor grants, and a second row of links over the same three or four of them is the same answer written twice
    return $tpl->getHtmlFrag('file-browser-props', [
        'dial_html' => getAdminFileActs($one),
        'cap_text' => _UPLOADS_PROPS,
        'name' => $one['name'],
        'icon' => $icon,
        'tone' => $tone,
        'image_url' => getAdminFileShot($one),
        // The picture of the panel opens the gallery the list opens, so it carries the same four addresses a row carries
        'pick_value' => $one['path'],
        'info_url' => getAdminFileLink('getAdminFileData', ['file' => $one['path']]),
        'down_url' => empty($one['capabilities']['download']) ? '' : getAdminFileLink('getAdminFileDownload', ['file' => $one['path']]),
        'shot_text' => _UPLOADS_PREVIEW,
        'rows' => $rows,
        'note_html' => ($warn === '') ? '' : $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $warn]),
    ]);
}

# Render the source editor of one system file in the place of the list, with the tree beside it: the way back, the name, the version of the open file and the code widget
# The version travels in the form and is compared under the lock of the directory on the way back, so the field is what tells a stale save from a fresh one and is never dropped
# A critical path asks again through the shared confirm protocol before the write, because an error in one of those files stops the site and a stray click must not reach it
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

# Render the system file browser of one directory: the tree along the open path, the list, the properties, the capability row and the counter of the current directory
# The same body answers the first page and every navigation of it; the toolbar is drawn once and the crumbs travel back out of band, because they live inside it
# An open source file takes the place of the list: the tree, the properties and the capability row stay, because the screen has not changed, only its work area has
# One object is drawn twice, as a row and as a tile, so its fan is built twice as well: two forms of one identifier would make the tile submit the form standing in the row
# The directory is answered whole and scrolled with the page rather than paged, and the administration is given all of it: no ceiling, no page and nothing left out of the answer
# The settings that count files belong to the editor and to the visitor, and none of them reaches this screen: what an administrator may not see is decided by the policy, not by a number
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
    if ($find !== '') $all = array_values(array_filter($all, static fn(array $row): bool => mb_stripos($row['name'], $find) !== false));
    $sum = 0;
    foreach ($all as $row) $sum += $row['size'];
    $rule = getAdminUploadRule($dir);
    // An open source file no longer takes the place of the list: the editor is a window over the screen, so the screen
    // it stands on keeps its own directory drawn underneath and the reader never loses where the file came from
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
            'mark_text' => _UPLOADS_MARK,
            'shot_text' => _UPLOADS_PREVIEW,
            'kind_text' => $isdir ? _DIR : strtoupper($row['extension']),
            'size_text' => $isdir ? '—' : filterSize($row['size']),
            // The printed size and date are read by a human and the two figures beside them by the sort, because
            // "1.2 MB" and "900 Bytes" compare as text in the wrong order and a formatted date compares by its day
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
    // The gallery offers what the fan of the row offers, because it presses that fan: every key here is a key the fan
    // carries, and one the context withholds is absent from both
    $acts = [['icon' => 'download', 'name' => _DOWNLOAD, 'tone' => 'neutral', 'is_load' => true]];
    if (!empty($able['edit'])) $acts[] = ['key' => 'fmedit', 'icon' => 'pencil-square', 'name' => _EDIT, 'tone' => 'info'];
    if (!empty($able['rename'])) $acts[] = ['key' => 'fmrename', 'icon' => 'input-cursor-text', 'name' => _UPLOADS_TORENAME, 'tone' => 'neutral'];
    if (!empty($able['copy'])) $acts[] = ['key' => 'fmcopy', 'icon' => 'files', 'name' => _UPLOADS_TOCOPY, 'tone' => 'neutral'];
    if (!empty($able['move'])) $acts[] = ['key' => 'fmmove', 'icon' => 'folder-symlink', 'name' => _UPLOADS_TOMOVE, 'tone' => 'neutral'];
    if (!empty($able['compress'])) $acts[] = ['key' => 'fmcompress', 'icon' => 'file-zip', 'name' => _UPLOADS_TOZIP, 'tone' => 'neutral'];
    if (!empty($able['delete'])) $acts[] = ['key' => 'fmdelete', 'icon' => 'trash3', 'name' => _DELETE, 'tone' => 'danger'];
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
        'self_url' => getAdminFileLink('getAdminFileList', ['dir' => $dir, 'find' => $find]),
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
            'empty_icon' => ($find === '') ? 'folder' : 'search',
            'empty_title' => ($find === '') ? _UPLOADS_EMPTY : _UPLOADS_NOFIND,
            'empty_text' => ($find === '') ? _UPLOADS_EMPTYTXT : _UPLOADS_NOFINDTXT,
            'reset_url' => ($find === '') ? '' : getAdminFileLink('getAdminFileList', ['dir' => $dir]),
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

# Answer one image of the system area for the preview panel; the type comes from a fixed table and never from the file, and the answer is sandboxed and never sniffed
# A vector carries scripts, so it is served under a policy that forbids every source: shown through <img> it draws, opened by its own address it can no longer run (§20)
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
    Cache::setHeaders(false, 0, $types[$one['extension']]);
    header('Content-Security-Policy: default-src \'none\'; sandbox');
    header('Content-Disposition: inline; filename="'.rawurlencode($one['name']).'"');
    header('Content-Length: '.$one['size']);
    readfile($one['realpath']);
    exit;
}

# Answer one file of the system area as a download; a direct link is no use here, because the web server would execute index.php instead of handing it over (§17)
# The route decides who may have the file and the shared download path decides how it leaves, so the headers of a download are written in one place for the whole project
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
