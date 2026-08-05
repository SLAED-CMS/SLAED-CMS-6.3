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
        ['href' => '/', 'label' => _SITE, 'blank' => true, 'icon' => 'globe2'],
        ['href' => 'index.php?name=account', 'label' => _ACCOUNT, 'blank' => true, 'icon' => 'person'],
        ['href' => $afile.'.php?op=logout', 'label' => _LOGOUT, 'blank' => false, 'icon' => 'box-arrow-right'],
    ] : [
        ['href' => $afile.'.php', 'label' => _HOME, 'blank' => false, 'icon' => 'house-door'],
        ['href' => $afile.'.php?name=blocks', 'label' => _BLOCKS, 'blank' => false, 'icon' => 'grid-3x3-gap'],
        ['href' => $afile.'.php?name=modules', 'label' => _MODULES, 'blank' => false, 'icon' => 'gpu-card'],
        ['href' => $afile.'.php?name=categories', 'label' => _CATEGORIES, 'blank' => false, 'icon' => 'folder'],
        ['href' => '/', 'label' => _SITE, 'blank' => true, 'icon' => 'globe2'],
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
# A caller whose table is owned by a subsystem class hands the number over in $num and leaves the table empty, so the row can be built without this helper reaching that table
function getAdminCountRow(string $href, string $titlec, string $labelc, string $icon, string $table = '', string $where = '', ?int $num = null): string {
    global $db, $afile, $tpl;
    if ($num === null) [$num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_'.$table.($where !== '' ? ' WHERE '.$where : '')));
    return $tpl->getHtmlFrag('block-sidebar-count-row', [
        'label_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?'.$href, 'title' => constant($titlec), 'label' => constant($labelc), 'icon_name' => $icon]),
        'value_html' => $tpl->getHtmlFrag('inline-badge', ['chip_tone' => (int)$num >= 1 ? 'warn' : 'success', 'label' => (string)$num]),
    ]);
}

# Build the admin sidebar info blocks: pending-content counters, waiting content, and the editor selector
function getAdminInfo() {
 global $admin, $afile, $com, $conf, $panel, $tpl;
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
            $info = $prs->filterContent($one['body'], true, 'privat', 0, $one['format']);
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

# Show uploads files for admin
function getAdminUploadFiles(): void {
 global $user, $tpl;
    $id   = filterVar(getVar('get', 'id',   'text', ''));
    $dir  = strtolower(getVar('get', 'dir',  'text', ''));
    $cid  = getVar('get', 'cid',  'num',  0);
    $rul = getUploadRuleData($dir);
    $connum = $rul['adminlist'] ?: 50;
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
                $ftype = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif'];
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
                    $zhref = 'index.php?go=5&op=getAdminUploadFiles&id='.$id.'&dir='.$dir.'&cid=1&file='.$entry[1];
                    $show[] = [
                        'href' => $zhref,
                        'icon_name' => 'file-zip',
                        'title' => _ZIP,
                        'link_attr' => 'hx-get="'.$zhref.'" hx-target="#repf'.$id.'" hx-swap="innerHTML" hx-push-url="false"',
                    ];
                }
                $dhref = 'index.php?go=5&op=getAdminUploadFiles&id='.$id.'&dir='.$dir.'&cid=0&file='.$entry[1];
                $show[] = [
                    'href' => $dhref,
                    'icon_name' => 'trash',
                    'title' => _ONDELETE,
                    'link_attr' => 'hx-get="'.$dhref.'" hx-target="#repf'.$id.'" hx-swap="innerHTML" hx-push-url="false"',
                ];
                $contents[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                    'cells' => [
                        ['content_html' => $img],
                        ['content_html' => $dirfile],
                        ['content_html' => date(_TIMESTRING, $entry[0])],
                        ['content_html' => filterSize($filesize)],
                        ['content_html' => $isize],
                        ['content_html' => $tpl->getHtmlFrag('dial', [
                            'dial_title' => _EDITOR,
                            'dial' => $show,
                        ]), 'is_col_actions' => true],
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
