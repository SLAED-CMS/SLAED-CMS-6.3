<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE')) die('Illegal file access');

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

# Render the standard admin language switcher
function getAdminLanguageLinks(): string {
    global $conf, $afile, $tpl;
    if (($conf['multilingual'] ?? 0) != 1) return '';
    $html = '';
    foreach (scandir(BASE_DIR.'/lang') ?: [] as $file) {
        if (!preg_match('#^(.+)\.php$#', $file, $matches)) continue;
        $lang = $matches[1];
        $label = getLangName($lang);
        $html .= $tpl->getHtmlFrag('link', [
            'href' => $afile.'.php?newlang='.$lang,
            'title' => $label,
            'img_src' => getLanguageFlagSrc($lang),
            'img_alt' => $label,
            'is_menu_list_image' => true,
            'is_admin_language_link' => true,
        ]);
    }
    return $html;
}

# Render the standard admin top menu.
function getAdminTopMenu(): string {
    global $admin, $afile, $tpl;
    $items = !isAdmin(true) ? [
        ['href' => '#', 'label' => _HELLO.', '.substr((string)($admin[1] ?? ''), 0, 25).'!', 'blank' => false, 'icon' => 'person-badge'],
        ['href' => $afile.'.php', 'label' => _HOME, 'blank' => false, 'icon' => 'house-door'],
        ['href' => 'index.php', 'label' => _SITE, 'blank' => true, 'icon' => 'globe2'],
        ['href' => 'index.php?name=account', 'label' => _ACCOUNT, 'blank' => true, 'icon' => 'person'],
        ['href' => $afile.'.php?op=logout', 'label' => _LOGOUT, 'blank' => false, 'icon' => 'box-arrow-right'],
    ] : [
        ['href' => $afile.'.php', 'label' => _HOME, 'blank' => false, 'icon' => 'house-door'],
        ['href' => $afile.'.php?name=blocks', 'label' => _BLOCKS, 'blank' => false, 'icon' => 'grid-3x3-gap'],
        ['href' => $afile.'.php?name=modules', 'label' => _MODULES, 'blank' => false, 'icon' => 'gpu-card'],
        ['href' => $afile.'.php?name=categories', 'label' => _CATEGORIES, 'blank' => false, 'icon' => 'folder'],
        ['href' => 'index.php', 'label' => _SITE, 'blank' => true, 'icon' => 'globe2'],
        ['href' => 'index.php?name=account', 'label' => _ACCOUNT, 'blank' => true, 'icon' => 'person'],
        ['href' => $afile.'.php?op=logout', 'label' => _LOGOUT, 'blank' => false, 'icon' => 'box-arrow-right'],
    ];
    $html = '';
    foreach ($items as $item) {
        $label = htmlspecialchars((string)$item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html .= '<li>'.$tpl->getHtmlFrag('link', [
            'href' => (string)$item['href'],
            'title' => (string)$item['label'],
            'icon_name' => (string)($item['icon'] ?? ''),
            'label_html' => '<b>'.$label.'</b>',
            'is_blank' => !empty($item['blank']),
        ]).'</li>';
    }
    return $html;
}

# Return standard variables used by admin layouts.
function getAdminLayoutVars(): array {
    global $db;
    if (!isAdmin()) {
        $login = ($db->getSqlRowCount($db->getSqlQuery('SELECT 1 FROM '.PREFIX_DB.'_admins LIMIT 1')) == 0) ? _ADMINLOGIN_NEW : _ADMINLOGIN;
        return ['login' => $login];
    }
    return [
        'admin_langs' => getAdminLanguageLinks(),
        'menu' => getAdminTopMenu(),
        'admin_blocks' => getAdminPanelBlocks().getAdminInfo().adminblock(),
    ];
}

# Render one sidebar pending-count row: active-content link plus a live COUNT chip; title and label are constant names resolved here for the active module
function getAdminCountRow(string $href, string $titlec, string $labelc, string $icon, string $table, string $where): string {
    global $db, $afile, $tpl;
    [$num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_'.$table.($where !== '' ? ' WHERE '.$where : '')));
    return $tpl->getHtmlFrag('block-sidebar-count-row', [
        'label_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?'.$href, 'title' => constant($titlec), 'label' => constant($labelc), 'icon_name' => $icon]),
        'value_html' => $tpl->getHtmlFrag('inline-badge', ['chip_tone' => (int)$num >= 1 ? 'warn' : 'success', 'label' => (string)$num]),
    ]);
}

# Build the admin sidebar info blocks: pending-content counters, waiting content, and the editor selector
function getAdminInfo() {
 global $admin, $afile, $conf, $panel, $tpl;
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
            $waitingRows = [getAdminCountRow('name=comments&status=1', '_COMMENTS', '_COMMENTS', 'chat-dots', 'comment', "status = '0'")];
            $ablocks .= $tpl->getHtmlPart('block-sidebar', ['title' => _WAITINGCONT, 'icon_name' => 'hourglass-split', 'content_html' => $tpl->getHtmlFrag('block-content', ['is_sidebar_count_list' => true, 'content' => implode('', $waitingRows)]), 'id' => '4', 'close' => _OPCL]);
        }
        $key = (string)($admin[3] ?? $conf['editor']['admin'] ?? 'plain');
        $edit = Editor::getSelect('editor', $key, 'content', 'admin', 'onchange="this.form.submit()"');
        $econt = $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'hidden' => [
                ['nameattr' => 'op', 'valueattr' => 'changeeditor'],
                ['nameattr' => 'refer', 'valueattr' => '1'],
            ],
            'content_html' => $edit,
        ]);
        $ablocks .= $tpl->getHtmlPart('block-sidebar', ['title' => _EDITOR, 'icon_name' => 'pencil-square', 'content_html' => $econt, 'id' => '6', 'close' => _OPCL]);
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

# Render the admin category list as an HTML table with reorder controls, flags and action links
function getAdminCategoryList(string $modul = '', int $obj = 0): string {
    global $db, $afile, $tpl;
    $modul = filterVar($modul);
    $where = ($modul) ? 'WHERE a.modul = :modul' : '';
    $params = ($modul) ? ['modul' => $modul] : [];
    $modlink = ($modul) ? '&modul='.$modul : '';
    $result = $db->getSqlQuery('SELECT a.id, a.modul, a.title, a.intro, a.img, a.lang, a.parent, a.ordern, a.status, b.id, b.modul, b.ordern, c.id, c.modul, c.ordern FROM '.PREFIX_DB.'_categories AS a LEFT JOIN '.PREFIX_DB.'_categories AS b ON (b.modul = a.modul AND b.ordern = a.ordern-1) LEFT JOIN '.PREFIX_DB.'_categories AS c ON (c.modul = a.modul AND c.ordern = a.ordern+1) '.$where.' ORDER BY a.modul, a.ordern', $params);
    if ($db->getSqlRowCount($result) > 0) {
        while (list($id, $modul, $title, $description, $imgcat, $language, $parentid, $ordern, $cstatus, $prev, , , $next) = $db->getSqlRow($result)) {
            $massiv[$id] = [$id, $modul, $title, $description, $imgcat, $language, $parentid, $ordern, $cstatus, $prev, $next];
            unset($id, $modul, $title, $description, $imgcat, $language, $parentid, $ordern, $cstatus, $prev, $next);
        }
        $rows = [];
        foreach ($massiv as $key => $val) {
            $id = $val[0];
            $modul = $val[1];
            $title = $val[2];
            $description = $val[3];
            $imgcat = $val[4];
            $language = $val[5];
            $parentid = $val[6];
            $ordern = $val[7];
            $cstatus = $val[8];
            $prev = $val[9];
            $next = $val[10];
            if ($modul == 'faq') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_faq WHERE cid = :id', ['id' => $id]));
            } elseif ($modul == 'files') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_files WHERE cid = :id', ['id' => $id]));
            } elseif ($modul == 'forum') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_forum WHERE cid = :id', ['id' => $id]));
            } elseif ($modul == 'help') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_help WHERE cid = :id', ['id' => $id]));
            } elseif ($modul == 'jokes') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_jokes WHERE cid = :id', ['id' => $id]));
            } elseif ($modul == 'links') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_links WHERE cid = :id', ['id' => $id]));
            } elseif ($modul == 'media') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_media WHERE cid = :id', ['id' => $id]));
            } elseif ($modul == 'news') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_news WHERE cid = :id', ['id' => $id]));
            } elseif ($modul == 'pages') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_pages WHERE cid = :id', ['id' => $id]));
            } elseif ($modul == 'shop') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_products WHERE cid = :id', ['id' => $id]));
            }
            list($ispid) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_categories WHERE parent = :id', ['id' => $id]));
            $ordernm = $ordern - 1;
            $ordernp = $ordern + 1;
            $active = $tpl->getHtmlFrag('inline-badge', [
                'is_success' => (bool)$parentid,
                'is_danger' => !$parentid,
                'label' => $parentid ? _YES : _NO,
            ]);
            $img = $imgcat ? getCategoryIcon($imgcat) : $tpl->getHtmlFrag('inline-badge', ['is_danger' => true, 'label' => _NO]);
            $flag = $parentid;
            while ($flag != '0') {
                $title = $massiv[$flag][2].' / '.$title;
                $flag = $massiv[$flag][6];
            }
            $descript = ($description) ? $description : _NO;
            $subcat = ($ispid) ? $ispid : _NO;
            $tipItems = [
                ['label' => _DESCRIPTION, 'value' => (string)$descript, 'is_last' => false],
                ['label' => _CATEGORIES, 'value' => (string)$subcat, 'is_last' => $language === ''],
            ];
            if ($language !== '') {
                $tipItems[] = ['label' => _LANGUAGE, 'value' => getLangName($language), 'is_last' => true];
            }
            $mup = ($prev) ? 'go=5&op=updateAdminCategoryOrder&id='.$id.'&cid='.$prev.'&typ='.$ordernm.'&mod='.$modul.'&ordern='.$ordern : '';
            $mdn = ($next) ? 'go=5&op=updateAdminCategoryOrder&id='.$id.'&cid='.$next.'&typ='.$ordernp.'&mod='.$modul.'&ordern='.$ordern : '';
            $items = [[
                'href' => $afile.'.php?name=categories&op=edit&cid='.$id.$modlink,
                'label' => _FULLEDIT,
                'title' => _FULLEDIT,
            ]];
            if (!$pnum && !$ispid) {
                $items[] = [
                    'href' => $afile.'.php?name=categories&op=delete&id='.$id.$modlink.'&token='.getSiteToken(),
                    'label' => _ONDELETE,
                    'title' => _ONDELETE,
                    'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"',
                ];
            }
            $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['content_html' => (string)$id],
                    ['content_html' => $tpl->getHtmlFrag('popover', [
                        'items' => $tipItems,
                        'label_text' => $title,
                        'title_text' => $title,
                    ])],
                    ['content_html' => (string)$pnum],
                    ['content_html' => $active],
                    ['content_html' => $img],
                    ['content_html' => (string)$ordern],
                    ['content_html' => getTplMoveControls(['target' => 'ajax_cat', 'up' => $mup, 'down' => $mdn])],
                    ['content_html' => ad_status('', $cstatus), 'is_col_status' => true, 'attr' => 'data-sort-column-key="category-status" data-sort="'.((int)$cstatus === 1 ? '1' : '0').'"'],
                    ['content_html' => $tpl->getHtmlFrag('popover', [
                        'trigger_label' => _EDITOR,
                        'items' => $items,
                    ]), 'is_col_actions' => true],
                ],
            ])]);
        }
        $cont = $tpl->getHtmlFrag('table', [
            'attr' => 'data-sl-admin-table="categories"',
            'head' => [
            ['content' => _ID],
            ['content' => _CATEGORY],
            ['content' => cutstr(_CONTENT, 3, 1)],
            ['content' => cutstr(_SUBCATEGORY, 3, 1), 'nosort' => true],
            ['content' => cutstr(_ICON, 2, 1), 'nosort' => true],
            ['content' => _WEIGHT, 'colspan' => 2],
            ['content' => _STATUS, 'attr' => 'data-sort-column-key="category-status" data-sort-reverse'],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
            ],
            'rows_html' => implode('', $rows),
            'is_wrapless' => true,
        ]);
    } else {
        $cont = $tpl->getHtmlFrag('alert', ['text' => _NO_INFO]);
    }
    if ($obj) { return $cont; }
    echo $cont;
    return '';
}

# Swap the ordern values of two adjacent categories and return the refreshed list fragment
function updateAdminCategoryOrder(): void {
    global $db;
    $modul = filterVar(getVar('get', 'mod', 'text', ''));
    if ($modul) {
        $typ = getVar('get', 'typ', 'num', 0);
        $ordern = getVar('get', 'ordern', 'num', 0);
        $id = getVar('get', 'id', 'num', 0);
        $cid = getVar('get', 'cid', 'num', 0);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET ordern = :typ WHERE id = :id', ['typ' => $typ, 'id' => $id]);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET ordern = :ordern WHERE id = :cid', ['ordern' => $ordern, 'cid' => $cid]);
    }
    getAdminCategoryList($modul, 0);
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

# Render the admin block list as an HTML table with reorder controls, expiry state and action links
function getAdminBlockList(string $token = ''): string {
    global $db, $afile, $tpl;
    $rows = [];
    $result = $db->getSqlQuery('SELECT a.id, a.bkey, a.title, a.url, a.bpos, a.weight, a.status, a.lang, a.bfile, a.view, a.expire, a.action, b.id, b.bpos, b.weight, c.id, c.bpos, c.weight FROM '.PREFIX_DB.'_blocks AS a LEFT JOIN '.PREFIX_DB.'_blocks AS b ON (b.bpos = a.bpos AND b.weight = a.weight-1) LEFT JOIN '.PREFIX_DB.'_blocks AS c ON (c.bpos = a.bpos AND c.weight = a.weight+1) ORDER BY a.bpos, a.weight');
    while (list($bid, $bkey, $title, $url, $bpos, $weight, $active, $lang, $bfile, $view, $expire, $action, $prev, , , $next) = $db->getSqlRow($result)) {
        if (($expire && $expire < time()) || (!$active && $expire)) {
            if ($action == 'd') {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET status = 0, expire = 0 WHERE id = :bid', ['bid' => $bid]);
            } elseif ($action == 'r') {
                $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_blocks WHERE id = :bid', ['bid' => $bid]);
            }
        }
        $wminus = $weight - 1;
        $wplus = $weight + 1;
        $exp = intval($expire - time());
        $exp = ($exp > 0) ? getDuration($exp) : _UNLIMITED;
        $tipItems = [
            ['label' => _NAME, 'value' => $title, 'is_last' => false],
        ];
        if ($lang !== '') {
            $tipItems[] = ['label' => _LANGUAGE, 'value' => getLangName($lang), 'is_last' => false];
        }
        $tipItems[] = ['label' => _PURCHASED, 'value' => $exp, 'is_last' => true];
        if ($bpos == 'l') {
            $bpos = $tpl->getHtmlFrag('inline-badge', ['is_note' => true, 'label' => _LEFT, 'title_text' => _LEFTBLOCK]);
        } elseif ($bpos == 'r') {
            $bpos = $tpl->getHtmlFrag('inline-badge', ['is_note' => true, 'label' => _RIGHT, 'title_text' => _RIGHTBLOCK]);
        } elseif ($bpos == 'c') {
            $bpos = $tpl->getHtmlFrag('inline-badge', ['is_note' => true, 'label' => _CENTERUP, 'title_text' => _CENTERBLOCK]);
        } elseif ($bpos == 'd') {
            $bpos = $tpl->getHtmlFrag('inline-badge', ['is_note' => true, 'label' => _CENTERDOWN, 'title_text' => _CENTERBLOCK]);
        } elseif ($bpos == 'b') {
            $bpos = $tpl->getHtmlFrag('inline-badge', ['is_note' => true, 'label' => _BANNERUP, 'title_text' => _BANNER]);
        } elseif ($bpos == 'f') {
            $bpos = $tpl->getHtmlFrag('inline-badge', ['is_note' => true, 'label' => _BANNERDOWN, 'title_text' => _BANNER]);
        }
        if ($bkey == '') {
            $type = ($url) ? 'RSS/RDF' : 'HTML';
            if ($bfile != '') $type = _BLOCKFILE2;
        } elseif ($bkey != '') {
            $type = _BLOCKSYSTEM;
        }
        if ($view == 0) {
            $who_view = _MVALL;
        } elseif ($view == 1) {
            $who_view = _MVUSERS;
        } elseif ($view == 2) {
            $who_view = _MVADMIN;
        } elseif ($view == 3) {
            $who_view = _MVANON;
        }
        $mup = ($prev) ? 'go=5&op=updateAdminBlockOrder&id='.$bid.'&cid='.$prev.'&typ='.$wminus.'&ordern='.$weight : '';
        $mdn = ($next) ? 'go=5&op=updateAdminBlockOrder&id='.$bid.'&cid='.$next.'&typ='.$wplus.'&ordern='.$weight : '';
        $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['content_html' => (string) $bid],
                ['content_html' => $tpl->getHtmlFrag('popover', [
                    'items' => $tipItems,
                    'label_text' => getConst($title),
                    'title_text' => $title,
                ])],
                ['content_html' => $type],
                ['content_html' => $who_view],
                ['content_html' => $bpos],
                ['content_html' => (string) $weight],
                ['content_html' => getTplMoveControls(['target' => 'ajax_block', 'up' => $mup, 'down' => $mdn])],
                ['content_html' => ad_status('', $active), 'is_col_status' => true, 'attr' => 'data-sort-column-key="block-status" data-sort="'.((int)$active === 1 ? '1' : '0').'"'],
                ['content_html' => $tpl->getHtmlFrag('popover', [
                    'trigger_label' => _EDITOR,
                    'items' => [[
                        'href' => $afile.'.php?name=blocks&op=change&id='.$bid.'&act='.$active.'&token='.getSiteToken(),
                        'label' => $active ? _DEACTIVATE : _ACTIVATE,
                        'title' => $active ? _DEACTIVATE : _ACTIVATE,
                    ], [
                        'href' => $afile.'.php?name=blocks&op=edit&id='.$bid,
                        'label' => _FULLEDIT,
                        'title' => _FULLEDIT,
                    ], [
                        'href' => $afile.'.php?name=blocks&op=delete&id='.$bid.'&token='.getSiteToken(),
                        'label' => _ONDELETE,
                        'title' => _ONDELETE,
                        'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"',
                    ]],
                ]), 'is_col_actions' => true],
            ],
        ])]);
    }
    return $tpl->getHtmlFrag('table', [
        'attr' => 'data-sl-admin-table="blocks"',
        'head' => [
            ['content' => _ID],
            ['content' => _TITLE],
            ['content' => _TYPE],
            ['content' => _VIEW],
            ['content' => _POSITION],
            ['content' => _WEIGHT, 'colspan' => 2],
            ['content' => _STATUS, 'attr' => 'data-sort-column-key="block-status" data-sort-reverse'],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ],
        'rows_html' => implode('', $rows),
        'is_wrapless' => true,
    ]);
}

# Swap the weight values of two adjacent blocks and echo the refreshed list fragment
function updateAdminBlockOrder(): void {
    global $db;
    $typ = getVar('get', 'typ', 'num', 0);
    $ordern = getVar('get', 'ordern', 'num', 0);
    $id = getVar('get', 'id', 'num', 0);
    $cid = getVar('get', 'cid', 'num', 0);
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET weight = :typ WHERE id = :id', ['typ' => $typ, 'id' => $id]);
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET weight = :ordern WHERE id = :cid', ['ordern' => $ordern, 'cid' => $cid]);
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
                $delhref = 'admin.php?name=favorites&op=delete&id='.$id.'&num='.$cid.'&token='.getSiteToken();
                $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                    'cells' => [
                        ['content_html' => (string) $id],
                        ['content_html' => $tpl->getHtmlFrag('inline-badge', ['is_note' => true, 'label' => $title, 'title_text' => $title])],
                        ['content_html' => getModuleName($modul)],
                        ['content_html' => $uname],
                        ['content_html' => $tpl->getHtmlFrag('popover', [
                            'trigger_label' => _FUNCTIONS,
                            'items' => [[
                                'href' => 'index.php?name='.$modul.'&op=view&id='.$fid.'#'.$fid,
                                'label' => _MVIEW,
                                'title' => _MVIEW,
                            ], [
                                'href' => $delhref,
                                'label' => _ONDELETE,
                                'title' => _ONDELETE,
                                'link_attr' => 'hx-get="'.$delhref.'" hx-target="#repadminFavoriteList" hx-swap="innerHTML" hx-push-url="false"',
                                'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"',
                            ]],
                        ])],
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
function getAdminPrivateList(int $obj = 0): string {
    global $db, $conf, $tpl, $prs;
    $newlistnum = intval($conf['privat']['anum']);
    $cid = getVar('get', 'num', 'num', getVar('get', 'cid', 'num', 1));
    $offset = intval(($cid - 1) * $newlistnum);
    list($fav_num) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat'));

    $result = $db->getSqlQuery('SELECT p.id, p.title, p.body, p.time, p.status, i.name, o.name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS i ON (p.uidin = i.id) LEFT JOIN '.PREFIX_DB.'_users AS o ON (p.uidout = o.id) ORDER BY p.time DESC LIMIT '.intval($offset).', '.intval($newlistnum));
    if ($db->getSqlRowCount($result) > 0) {
        $rows = [];
        while (list($id, $title, $body, $date, $status, $user_re, $user_se) = $db->getSqlRow($result)) {
            $unre = ($user_re) ? user_info($user_re) : _ANONYM;
            $unse = ($user_se) ? user_info($user_se) : _ANONYM;
            $date = format_time($date, _TIMESTRING);
            $info = $prs->filterContent($body, false, 'privat');
            $title = getDecodedText($title);
            $delhref = 'admin.php?name=privat&op=delete&id='.$id.'&num='.$cid.'&token='.getSiteToken();
            $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['content_html' => (string) $id],
                    ['content_html' => $tpl->getHtmlFrag('popover', ['content' => $info]).$tpl->getHtmlFrag('inline-badge', ['is_note' => true, 'label' => $title, 'title_text' => $title])],
                    ['content_html' => $unse],
                    ['content_html' => $unre],
                    ['content_html' => $date],
                    ['content_html' => ad_status('', $status, 1), 'is_col_status' => true],
                    ['content_html' => $tpl->getHtmlFrag('popover', [
                        'trigger_label' => _FUNCTIONS,
                        'items' => [[
                            'href' => $delhref,
                            'label' => _ONDELETE,
                            'title' => _ONDELETE,
                            'link_attr' => 'hx-get="'.$delhref.'" hx-target="#repadminPrivateList" hx-swap="innerHTML" hx-push-url="false"',
                            'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"',
                        ]],
                    ])],
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
        $cont .= getTplPager([
            'table' => '_privat',
            'field' => 'id',
            'limit' => $newlistnum,
            'maxpg' => intval($conf['privat']['anump']),
            'url' => 'name=privat&',
            'n' => 'num',
            'target_id' => 'repadminPrivateList',
            'push_url' => true,
        ]);
    } else {
        $cont = $tpl->getHtmlFrag('alert', ['text' => _NO_INFO]);
    }
    if ($obj) { return $cont; }
    echo $cont;
    return '';
}

# Private message delete
function deleteAdminPrivate(): void {
 global $db;
    $id = getVar('get', 'id', 'num', 0);
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_privat WHERE id = :id', ['id' => $id]);
    getAdminPrivateList(0);
}

# Show uploads files for admin
function getAdminUploadFiles(): void {
 global $user, $conf, $tpl;
    $conf['uploads'] = $conf['uploads'] ?? [];
    $id   = filterVar(getVar('get', 'id',   'text', ''));
    $dir  = strtolower(getVar('get', 'dir',  'text', ''));
    $cid  = getVar('get', 'cid',  'num',  0);
    $con  = explode('|', (string)($conf['uploads'][$dir] ?? ''));
    $connum = (!empty($con[7]) && intval($con[7])) ? $con[7] : '50';
    $file = filterText(getVar('get', 'file', 'text', ''));
    $num  = ($cid) ? $cid : '1';
    $path = ($id == 1) ? UPLOADS_DIR.'/'.$dir.'/' : UPLOADS_DIR.'/'.$dir.'/thumb/';
    $pub = ($id == 1) ? 'uploads/'.$dir.'/' : 'uploads/'.$dir.'/thumb/';
    if (is_dir($path)) {
        if ($file && $dir) {
            if (!$cid) {
                if (file_exists($path.$file)) unlink($path.$file);
            } else {
                addCompress($path, $path.$file, $file);
            }
        }
        $files = [];
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'index.html' || is_dir($path.$entry)) continue;
            $files[] = [filemtime($path.$entry), $entry];
        }
        if (is_array($files)) {
            $a = 0;
            rsort($files);
            foreach ($files as $entry) {
                $filesize = filesize($path.$entry[1]);
                list($imgwidth, $imgheight) = getimagesize($path.$entry[1]);
                $type = strtolower(substr(strrchr($entry[1], '.'), 1));
                $ftype = ['png', 'jpg', 'jpeg', 'gif', 'bmp'];
                $dirfile = (preg_match('#php.*|js|htm|html|phtml|cgi|pl|perl|asp#i', $type))
                    ? $tpl->getHtmlFrag('inline-badge', ['is_danger' => true, 'label' => $entry[1]])
                    : $entry[1];
                if (in_array($type, $ftype) && $imgwidth && $imgheight) {
                    $img = $tpl->getHtmlFrag('image-preview', [
                        'preview_id' => 'sf-form-'.$a,
                        'image_url' => $pub.$entry[1],
                        'fallback_url' => 'templates/admin/images/icons/no.png',
                        'image_title' => _IMG,
                        'no_title' => _NO,
                        'show_toggle' => true,
                        'show_fallback' => false,
                    ]);
                    $isize = $imgwidth.' x '.$imgheight;
                } else {
                    $img = $tpl->getHtmlFrag('image-preview', [
                        'preview_id' => 'sf-form-'.$a,
                        'image_url' => '',
                        'fallback_url' => 'templates/admin/images/icons/no.png',
                        'image_title' => _IMG,
                        'no_title' => _NO,
                        'show_toggle' => false,
                        'show_fallback' => true,
                    ]);
                    $isize = _NO;
                }
                $show = [];
                if (in_array(true, checkCompress(), true)) {
                    $show[] = [
                        'href' => 'index.php?go=5&op=getAdminUploadFiles&id='.$id.'&dir='.$dir.'&cid=1&file='.$entry[1],
                        'label' => _ZIP,
                        'title' => _ZIP,
                        'is_htmx' => true,
                        'hx_target' => '#repf'.$id,
                    ];
                }
                $show[] = [
                    'href' => 'index.php?go=5&op=getAdminUploadFiles&id='.$id.'&dir='.$dir.'&cid=0&file='.$entry[1],
                    'label' => _ONDELETE,
                    'title' => _ONDELETE,
                    'is_htmx' => true,
                    'hx_target' => '#repf'.$id,
                ];
                $contents[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                    'cells' => [
                        ['content_html' => $img],
                        ['content_html' => $dirfile],
                        ['content_html' => date(_TIMESTRING, $entry[0])],
                        ['content_html' => filterSize($filesize)],
                        ['content_html' => $isize],
                        ['content_html' => $tpl->getHtmlFrag('popover', [
                            'trigger_label' => _EDITOR,
                            'items' => $show,
                        ])],
                    ],
                ])]);
                $a++;
            }
        }
        $numpages = ceil($a / $connum);
        $offset = ($num - 1) * $connum;
        $tnum = ($offset) ? $connum + $offset : $connum;
        $cont = '';
        for ($i = $offset; $i < $tnum; $i++) {
            if (!empty($contents[$i])) $cont .= $contents[$i];
        }
        $contnum = ($a > $connum) ? getAsyncPager('pagenum', $a, $numpages, $connum, 8, $num, '0', 5, 'getAdminUploadFiles', 'f'.$id, $id, '', $dir) : '';
        $content = ($cont) ? $tpl->getHtmlFrag('table', [
            'head' => [
                ['content' => cutstr(_IMG, 4, 1)],
                ['content' => _FILE],
                ['content' => _DATE],
                ['content' => _SIZE],
                ['content' => _WIDTH.' x '._HEIGHT],
                ['content' => _FUNCTIONS, 'nosort' => true],
            ],
            'rows_html' => $cont,
            'disable_sort' => true,
        ]).$contnum : '';
    } else {
        $content = $tpl->getHtmlFrag('alert', ['text' => _NO_INFO]);
    }
    echo $content;
}

# Add voting
function add_voting(string $modul, string $selectName, int $selectedId, string $extraClass = ''): string {
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
    $attr = $extraClass ? ' class="'.htmlspecialchars('sl-field '.$extraClass, ENT_QUOTES, 'UTF-8').'"' : '';
    return $tpl->getHtmlFrag('select', ['name_attr' => $selectName, 'options_html' => $opts, 'select_attr' => $attr]);
}
