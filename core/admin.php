<?php
# Author: Eduard Laas
# Copyright Â© 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE')) die('Illegal file access');

# Format statistic image
function getStatistic(): void {
 global $conf;
    $report = getVar('get', 'report', 'num', 0);
    $day    = getVar('get', 'day', 'num', 15);
    $file   = getVar('get', 'file', 'text', '');
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

    if ($report) {
        imagepng($image, COUNTER_DIR.'/statistic/'.date('m-Y').'.png');
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

# Build admin tabs navigation — accepts named array, reads module title/icon from config automatically
function setAdminNavi(array $p): string {
    global $afile, $conf, $tpl;
    $ttl = _ADMINMENU;
    $ico = 'components.png';
    $name = getVar('req', 'name', 'var');
    if ($name !== '' && isset($conf['modules'][$name]) && is_array($conf['modules'][$name])) {
        $lang = trim($conf['modules'][$name]['lang'] ?? '');
        if ($lang !== '') $ttl = defined($lang) ? constant($lang) : $lang;
        $img = basename(trim($conf['modules'][$name]['img'] ?? ''));
        if ($img !== '' && file_exists(BASE_DIR.'/templates/admin/images/admin/'.$img)) $ico = $img;
    }
    $ops    = $p['ops']    ?? [];
    $tabs   = $p['tabs']   ?? [];
    $sops   = $p['sops']   ?? [];
    $stabs  = $p['stabs']  ?? [];
    $sub    = $p['sub']    ?? '';
    $act    = $p['tab']    ?? 0;
    $hassub = (bool)($p['subtab'] ?? false);
    $actsub = $p['legacy'] ?? 0;
    $mtab   = $p['id']     ?? 'menutab';
    $cnt = adminTabListOpen($mtab, 'tabmenu');
    $scnt = '';
    $k = 0;
    foreach ($tabs as $tab) {
        if ($tab === '') { $k++; continue; }
        if ($hassub && !empty($stabs)) {
            $scnt = adminTabListOpen($mtab.'s', 'tabsubmenu');
            $l = 0;
            foreach ($stabs as $stab) {
                if ($stab === '') { $l++; continue; }
                $hrefsub = !empty($sops[$l]) ? $afile.'.php?'.$sops[$l] : '#';
                $relsub = !empty($sops[$l]) ? '' : 'tabcs'.$l;
                $scnt .= adminTabLink($hrefsub, $stab, $l === $actsub, $relsub);
                $l++;
            }
            $scnt .= adminTabListClose();
        }
        $href = !empty($ops[$k]) ? $afile.'.php?'.$ops[$k] : '#';
        $rel = !empty($ops[$k]) ? '' : 'tabc'.$k;
        $cnt .= adminTabLink($href, $tab, $k === $act, $rel);
        $k++;
    }
    $cnt .= adminTabListClose();
    if ($scnt !== '') $cnt .= $scnt;
    return $tpl->getHtmlFrag('title', ['title' => $ttl, 'icon' => $ico, 'subtitle' => $sub, 'content' => $cnt]);
}

function adminTabListOpen(string $id, string $class): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-tab-list-open', [
        'list_class' => $class,
        'list_id' => $id,
    ]);
}

function adminTabListClose(): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-tab-list-close', []);
}

function adminTabLink(string $href, string $label, bool $selected = false, string $rel = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-tab-link', [
        'href' => $href,
        'is_selected' => $selected,
        'label' => $label,
        'rel' => $rel,
    ]);
}

function adminInfoCountValue(int|string $count): string {
    global $tpl;
    if (!is_numeric($count)) {
        return '-';
    }
    $css = ((int) $count >= 1) ? 'sl_red' : 'sl_green';
    return $tpl->getHtmlFrag('admin-info-count', [
        'count_text' => (string)$count,
        'css_class' => $css,
    ]);
}

function adminInfoRow(string $href, string $title, string $label, int|string $count): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-info-row', [
        'count_html' => adminInfoCountValue($count),
        'href' => $href,
        'label' => $label,
        'title' => $title,
    ]);
}

function adminInfoTable(array $rows): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-info-table', [
        'rows_html' => implode('', $rows),
    ]);
}

function admininfo() {
 global $db, $admin, $afile, $conf, $panel, $tpl;
    if (isAdmin()) {
        $ablocks = '';
        if ($panel) {
            $newRows = [];
            if (is_active('account') && is_admin_modul('account')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_users_temp'));
                $newRows[] = adminInfoRow($afile.'.php?name=account&op=newuser', _NEW_USER, _USERS, $num);
            }
            if (is_active('album') && is_admin_modul('album')) {
                #$num = $db->getSqlRowCount($db->getSqlQuery("SELECT pid FROM ".PREFIX_DB."_album_pictures_newpicture"));
                #$num = (is_numeric($num)) ? (($num >= 1) ? "<span class=\"sl_red\">".$num."</span>" : "<span class=\"sl_green\">".$num."</span>") : "-";
                #$n_cont .= "<tr><td><a href=\"".$afile.".php?op=album&amp;do=validnew&amp;type=checknew\" title=\""._ALBUM."\">"._ALBUM."</a>:</td><td>".$num."</td></tr>";
            }
            if (is_active('faq') && is_admin_modul('faq')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_faq WHERE status = '0'"));
                $newRows[] = adminInfoRow($afile.'.php?name=faq&status=1', _FAQ, _FAQ, $num);
            }
            if (is_active('files') && is_admin_modul('files')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_files WHERE status = '0'"));
                $newRows[] = adminInfoRow($afile.'.php?name=files&status=1', _FILES, _FILES, $num);
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_files WHERE status = '2'"));
                $newRows[] = adminInfoRow($afile.'.php?name=files&status=2', _BROCFILES, _BROCFILES, $num);
            }
            if (is_active('help') && is_admin_modul('help')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_help WHERE pid = '0' AND status = '0'"));
                $newRows[] = adminInfoRow($afile.'.php?name=help', _HELP, _HELP, $num);
            }
            if (is_active('jokes') && is_admin_modul('jokes')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_jokes WHERE status = '0'"));
                $newRows[] = adminInfoRow($afile.'.php?name=jokes&status=1', _JOKES, _JOKES, $num);
            }
            if (is_active('links') && is_admin_modul('links')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_links WHERE status = '0'"));
                $newRows[] = adminInfoRow($afile.'.php?name=links&status=1', _LINKS, _LINKS, $num);
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_links WHERE status = '2'"));
                $newRows[] = adminInfoRow($afile.'.php?name=links&status=2', _BROCLINKS, _BROCLINKS, $num);
            }
            if (is_active('media') && is_admin_modul('media')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_media WHERE status = '0'"));
                $newRows[] = adminInfoRow($afile.'.php?name=media&status=1', _MEDIA, _MEDIA, $num);
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_media WHERE status = '2'"));
                $newRows[] = adminInfoRow($afile.'.php?name=media&status=2', _BROCMFILES, _BROCMFILES, $num);
            }
            if (is_active('news') && is_admin_modul('news')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_news WHERE status = '0'"));
                $newRows[] = adminInfoRow($afile.'.php?name=news&status=1', _NEWS, _NEWS, $num);
            }
            if (is_active('pages') && is_admin_modul('pages')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_pages WHERE status = '0'"));
                $newRows[] = adminInfoRow($afile.'.php?name=pages&status=1', _PAGES, _PAGES, $num);
            }
            if (is_active('shop') && is_admin_modul('shop')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_clients WHERE status = '2'"));
                $newRows[] = adminInfoRow($afile.'.php?name=shop&op=clients', _CLIENTS, _CLIENTS, $num);
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_partners WHERE status = '2'"));
                $newRows[] = adminInfoRow($afile.'.php?name=shop&op=partners', _PARTNERS, _PARTNERS, $num);
            }
            if (is_active('whois') && is_admin_modul('whois')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_whois WHERE status = '0'"));
                $newRows[] = adminInfoRow($afile.'.php?name=whois&status=1', _WHOIS, _WHOIS, $num);
            }
            $ablocks = $tpl->getHtmlFrag('block-left', ['title' => _NEW, 'content' => adminInfoTable($newRows), 'id' => '3', 'close' => _OPCL]);
            
            $waitingRows = [];
            $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_comment WHERE status = '0'"));
            $waitingRows[] = adminInfoRow($afile.'.php?name=comments&status=1', _COMMENTS, _COMMENTS, $num);
            $ablocks .= $tpl->getHtmlFrag('block-left', ['title' => _WAITINGCONT, 'content' => adminInfoTable($waitingRows), 'id' => '4', 'close' => _OPCL]);
            
        }
        $editor = (isset($admin[3])) ? intval(substr($admin[3], 0, 1)) : 0;
        $e_cont = $tpl->getHtmlFrag('admin-editor-form', [
            'action_url' => $afile.'.php',
            'editor_html' => redaktor('1', 'editor', '', $editor, 1),
        ]);
        $ablocks .= $tpl->getHtmlFrag('block-left', ['title' => _EDITOR, 'content' => $e_cont, 'id' => '6', 'close' => _OPCL]);
        return $ablocks;
    }
}

function db_version() {
 global $db;
    list($dbv) = $db->getSqlRow($db->getSqlQuery('SELECT VERSION()'));
    return $dbv;
}

function adminCategoryTable(string $rowsHtml): string {
 global $tpl;
    return $tpl->getHtmlFrag('admin-category-table', [
        'id_label' => _ID,
        'category_label' => _CATEGORY,
        'content_label' => cutstr(_CONTENT, 3, 1),
        'subcategory_label' => cutstr(_SUBCATEGORY, 3, 1),
        'image_label' => cutstr(_IMG, 2, 1),
        'weight_label' => _WEIGHT,
        'status_label' => _STATUS,
        'functions_label' => _FUNCTIONS,
        'rows_html' => $rowsHtml,
    ]);
}

function adminCategoryRow(array $row): string {
 global $tpl;
    return $tpl->getHtmlFrag('admin-category-row', $row);
}

function adminBlockTable(string $rowsHtml): string {
 global $tpl;
    return $tpl->getHtmlFrag('admin-block-table', [
        'id_label' => _ID,
        'title_label' => _TITLE,
        'type_label' => _TYPE,
        'view_label' => _VIEW,
        'position_label' => _POSITION,
        'weight_label' => _WEIGHT,
        'status_label' => _STATUS,
        'functions_label' => _FUNCTIONS,
        'rows_html' => $rowsHtml,
    ]);
}

function adminBlockRow(array $row): string {
 global $tpl;
    return $tpl->getHtmlFrag('admin-block-row', $row);
}

function adminFavoritesTable(string $rowsHtml): string {
 global $tpl;
    return $tpl->getHtmlFrag('admin-favorites-table', [
        'id_label' => _ID,
        'title_label' => _TITLE,
        'module_label' => _MODUL,
        'posted_by_label' => _POSTEDBY,
        'functions_label' => _FUNCTIONS,
        'rows_html' => $rowsHtml,
    ]);
}

function adminFavoritesRow(array $row): string {
 global $tpl;
    return $tpl->getHtmlFrag('admin-favorites-row', $row);
}

function adminPrivateTable(string $rowsHtml): string {
 global $tpl;
    return $tpl->getHtmlFrag('admin-private-table', [
        'id_label' => _ID,
        'title_label' => _TITLE,
        'sender_label' => _PRSE,
        'receiver_label' => _PRRE,
        'date_label' => _DATE,
        'status_label' => _STATUS,
        'functions_label' => _FUNCTIONS,
        'rows_html' => $rowsHtml,
    ]);
}

function adminPrivateRow(array $row): string {
 global $tpl;
    return $tpl->getHtmlFrag('admin-private-row', $row);
}

function adminFilesTable(string $rowsHtml): string {
 global $tpl;
    return $tpl->getHtmlFrag('admin-files-table', [
        'image_label' => cutstr(_IMG, 4, 1),
        'file_label' => _FILE,
        'date_label' => _DATE,
        'size_label' => _SIZE,
        'dimensions_label' => _WIDTH.' x '._HEIGHT,
        'functions_label' => _FUNCTIONS,
        'rows_html' => $rowsHtml,
    ]);
}

function adminFilesRow(array $row): string {
 global $tpl;
    return $tpl->getHtmlFrag('admin-files-row', $row);
}

function adminInfoForm(array $data): string {
 global $tpl;
    return $tpl->getHtmlFrag('admin-info-form', [
        'submit_label' => (string)($data['submit_label'] ?? ''),
        'submit_onclick' => (string)($data['submit_onclick'] ?? ''),
        'submit_title' => (string)($data['submit_title'] ?? ''),
        'textarea_html' => (string)($data['textarea_html'] ?? ''),
    ]);
}

function adminFlagBox(bool $state, string $yesLabel, string $noLabel): string {
 global $tpl;
    return $tpl->getHtmlFrag('admin-flag-box', [
        'css_class' => $state ? 'sl_green' : 'sl_red',
        'label_text' => $state ? $yesLabel : $noLabel,
    ]);
}

function adminNoteLabel(string $title, string $label): string {
 global $tpl;
    return $tpl->getHtmlFrag('admin-note-label', [
        'label_text' => $label,
        'title_attr' => $title,
    ]);
}

function adminMoveControls(string $target, string $upQuery = '', string $downQuery = ''): string {
 global $tpl;
    return $tpl->getHtmlFrag('admin-move-controls', [
        'down_query' => $downQuery,
        'down_title' => _BLOCKDOWN,
        'target' => $target,
        'up_query' => $upQuery,
        'up_title' => _BLOCKUP,
    ]);
}

function adminFilePreview(int $index, string $path, bool $hasImage): string {
 global $tpl;
    return $tpl->getHtmlFrag('editor-file-preview', [
        'preview_id' => 'sf-form-'.$index,
        'toggle_onclick' => "HideShow('sf-form-".$index."', 'fold', 'up', 500);",
        'image_url' => $path,
        'fallback_url' => 'templates/admin/images/admin/no.png',
        'image_title' => _IMG,
        'no_title' => _NO,
        'show_image' => $hasImage,
    ]);
}

function adminDangerText(string $text): string {
 global $tpl;
    return $tpl->getHtmlFrag('admin-danger-text', [
        'text' => $text,
    ]);
}

function adminTitleTip(string $data): string {
 global $tpl;
    return $tpl->getHtmlFrag('admin-title-tip', [
        'content_html' => $data,
    ]);
}

function adminTitleTipLabel(string $tip, string $title, string $label): string {
    return adminTitleTip($tip).adminNoteLabel($title, $label);
}

function adminLinkAction(string $href, string $title, string $label): string {
 global $tpl;
    return $tpl->getHtmlFrag('comment-action-link', [
        'href' => $href,
        'title' => $title,
        'label' => $label,
        'class' => '',
        'target' => '',
    ]);
}

function adminAjaxAction(string $target, string $query, string $title, string $label): string {
 global $tpl;
    return $tpl->getHtmlFrag('comment-action-ajax', [
        'load_id' => '0',
        'target' => $target,
        'query' => $query,
        'title' => $title,
        'label' => $label,
        'class' => '',
    ]);
}

function adminDeleteAction(string $href, string $confirmText, string $title, string $label): string {
 global $tpl;
    return $tpl->getHtmlFrag('action-delete', [
        'href' => $href,
        'confirm_text' => $confirmText,
        'title' => $title,
        'label' => $label,
    ]);
}

function adminMenuItems(array $items): string {
 global $tpl;
    $items = array_values(array_filter($items, static fn($item) => $item !== ''));
    if (!$items) {
        return '';
    }
    return $tpl->getHtmlFrag('action-menu', [
        'editor_label' => _EDITOR,
        'items_html' => implode('', array_map(static fn($item) => getMenuItem($item), $items)),
    ]);
}

function ajax_cat(string $modul = '', int $obj = 0): string {
 global $db, $afile, $conf, $tpl;
    $modul   = filterVar($modul);
    $where   = ($modul) ? 'WHERE a.modul = :modul' : '';
    $params  = ($modul) ? ['modul' => $modul] : [];
    $modlink = ($modul) ? '&amp;modul='.$modul : '';
    $result  = $db->getSqlQuery('SELECT a.id, a.modul, a.title, a.intro, a.img, a.lang, a.parent, a.ordern, a.status, b.id, b.modul, b.ordern, c.id, c.modul, c.ordern FROM '.PREFIX_DB.'_categories AS a LEFT JOIN '.PREFIX_DB.'_categories AS b ON (b.modul = a.modul AND b.ordern = a.ordern-1) LEFT JOIN '.PREFIX_DB.'_categories AS c ON (c.modul = a.modul AND c.ordern = a.ordern+1) '.$where.' ORDER BY a.modul, a.ordern', $params);
    if ($db->getSqlRowCount($result) > 0) {
        while (list($id, $modul, $title, $description, $imgcat, $language, $parentid, $ordern, $cstatus, $con1, $modul1, $order1, $con2, $modul2, $order2) = $db->getSqlRow($result)) {
            $massiv[$id] = [$id, $modul, $title, $description, $imgcat, $language, $parentid, $ordern, $cstatus, $con1, $modul1, $order1, $con2, $modul2, $order2];
            unset($id, $modul, $title, $description, $imgcat, $language, $parentid, $ordern, $cstatus, $con1, $modul1, $order1, $con2, $modul2, $order2);
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
            $con1 = $val[9];
            $modul1 = $val[10];
            $order1 = $val[11];
            $con2 = $val[12];
            $modul2 = $val[13];
            $order2 = $val[14];
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
            $active = adminFlagBox((bool) $parentid, _YES, _NO);
            $img = adminFlagBox((bool) $imgcat, _YES, _NO);
            $flag = $parentid;
            while ($flag != '0') {
                $title = $massiv[$flag][2].' / '.$title;
                $flag = $massiv[$flag][6];
            }
            $descript = ($description) ? $description : _NO;
            $subcat = ($ispid) ? $ispid : _NO;
            $clang = getAdminLangHint($language);
            $delete = (!$pnum && !$ispid)
                ? adminDeleteAction(
                    $afile.'.php?op=cat_del&amp;id='.$id.$modlink.'&amp;refer=1',
                    _DELETE.' "'.$title.'"?',
                    _ONDELETE,
                    _ONDELETE
                )
                : '';
            $moveUp = ($con1) ? 'go=5&amp;op=cat_order&amp;id='.$id.'&amp;cid='.$con1.'&amp;typ='.$ordernm.'&amp;mod='.$modul.'&amp;ordern='.$ordern : '';
            $moveDown = ($con2) ? 'go=5&amp;op=cat_order&amp;id='.$id.'&amp;cid='.$con2.'&amp;typ='.$ordernp.'&amp;mod='.$modul.'&amp;ordern='.$ordern : '';
            $rows[] = adminCategoryRow([
                'id' => (string) $id,
                'title_html' => adminTitleTipLabel(_DESCRIPTION.': '.$descript.'<br>'._CATEGORIES.': '.$subcat.$clang, $title, cutstr($title, 50)),
                'content_count' => (string) $pnum,
                'active_html' => $active,
                'image_html' => $img,
                'weight_value' => (string) $ordern,
                'move_html' => adminMoveControls('ajax_cat', $moveUp, $moveDown),
                'status_html' => ad_status('', $cstatus),
                'functions_html' => adminMenuItems([
                    adminLinkAction($afile.'.php?name=categories&amp;op=edit&amp;cid='.$id.$modlink, _FULLEDIT, _FULLEDIT),
                    $delete,
                ]),
            ]);
        }
        $cont = adminCategoryTable(implode('', $rows));
    } else {
        $cont = $tpl->getHtmlFrag('alert', ['text' => _NO_INFO, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
    }
    if ($obj) { return $cont; }
    echo $cont;
    return '';
}

function cat_order(): void {
 global $db;
    $modul = filterVar(getVar('get', 'mod', 'text', ''));
    if ($modul) {
        $typ    = getVar('get', 'typ',    'num', 0);
        $ordern = getVar('get', 'ordern', 'num', 0);
        $id     = getVar('get', 'id',     'num', 0);
        $cid    = getVar('get', 'cid',    'num', 0);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET ordern = :typ    WHERE id = :id',  ['typ' => $typ,    'id' => $id]);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET ordern = :ordern WHERE id = :cid', ['ordern' => $ordern, 'cid' => $cid]);
    }
    ajax_cat($modul, 0);
}

function catacess(string $name, string $class, string $selected, int $limit): string {
 global $db;
    $gids = explode('|', $selected);
    $opts = '';
    if ($limit < 1) {
        $opts .= getAdminOption('0|0', _ALL, $selected == '0|0');
    }
    if ($limit < 2) {
        $opts .= getAdminOption('1|0', _USERS, $selected == '1|0');
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
        $opts .= getAdminOption('2|'.$id, $title, $sel !== '');
    }
    $opts .= getAdminOption('3|0', _ADMIN, $selected == '3|0');
    return getAdminSelect($name.'[]', $opts, $class, 'multiple="multiple"');
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

function ajax_block(): string {
 global $db, $conf, $afile;
    $rows = [];
    $result = $db->getSqlQuery('SELECT a.id, a.bkey, a.title, a.url, a.bpos, a.weight, a.status, a.lang, a.bfile, a.view, a.expire, a.action, b.id, b.bpos, b.weight, c.id, c.bpos, c.weight FROM '.PREFIX_DB.'_blocks AS a LEFT JOIN '.PREFIX_DB.'_blocks AS b ON (b.bpos = a.bpos AND b.weight = a.weight-1) LEFT JOIN '.PREFIX_DB.'_blocks AS c ON (c.bpos = a.bpos AND c.weight = a.weight+1) ORDER BY a.bpos, a.weight');
    while (list($bid, $bkey, $title, $url, $bpos, $weight, $active, $lang, $bfile, $view, $expire, $action, $con1, $bpos1, $weight1, $con2, $bpos2, $weight2) = $db->getSqlRow($result)) {
        if (($expire && $expire < time()) || (!$active && $expire)) {
            if ($action == 'd') {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET status = 0, expire = 0 WHERE id = :bid', ['bid' => $bid]);
            } elseif ($action == 'r') {
                $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_blocks WHERE id = :bid', ['bid' => $bid]);
            }
        }
        $weight_minus = $weight - 1;
        $weight_plus = $weight + 1;
        $exp = intval($expire - time());
        $exp = ($exp > 0) ? getDuration($exp) : _UNLIMITED;
        $blang = getAdminLangHint($lang);
        $titleHtml = adminTitleTip(_NAME.': '.$title.'<br>'._PURCHASED.': '.$exp.$blang).cutstr(getConst($title), 15);
        if ($bpos == 'l') {
            $bpos = adminNoteLabel(_LEFTBLOCK, _LEFT);
        } elseif ($bpos == 'r') {
            $bpos = adminNoteLabel(_RIGHTBLOCK, _RIGHT);
        } elseif ($bpos == 'c') {
            $bpos = adminNoteLabel(_CENTERBLOCK, _CENTERUP);
        } elseif ($bpos == 'd') {
            $bpos = adminNoteLabel(_CENTERBLOCK, _CENTERDOWN);
        } elseif ($bpos == 'b') {
            $bpos = adminNoteLabel(_BANNER, _BANNERUP);
        } elseif ($bpos == 'f') {
            $bpos = adminNoteLabel(_BANNER, _BANNERDOWN);
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
        $moveUp = ($con1) ? 'go=5&amp;op=blocks_order&amp;id='.$bid.'&amp;cid='.$con1.'&amp;typ='.$weight_minus.'&amp;ordern='.$weight : '';
        $moveDown = ($con2) ? 'go=5&amp;op=blocks_order&amp;id='.$bid.'&amp;cid='.$con2.'&amp;typ='.$weight_plus.'&amp;ordern='.$weight : '';
        $rows[] = adminBlockRow([
            'id' => (string) $bid,
            'title_html' => $titleHtml,
            'type_label' => $type,
            'view_label' => $who_view,
            'position_html' => $bpos,
            'weight_value' => (string) $weight,
            'move_html' => adminMoveControls('ajax_block', $moveUp, $moveDown),
            'status_html' => ad_status('', $active),
            'functions_html' => adminMenuItems([
                ad_status($afile.'.php?name=blocks&amp;op=change&amp;id='.$bid.'&amp;act='.$active, $active),
                adminLinkAction($afile.'.php?name=blocks&amp;op=edit&amp;id='.$bid, _FULLEDIT, _FULLEDIT),
                adminDeleteAction($afile.'.php?name=blocks&amp;op=delete&amp;id='.$bid, _DELETE.' "'.$title.'"?', _ONDELETE, _ONDELETE),
            ]),
        ]);
    }
    return adminBlockTable(implode('', $rows));
}

function blocks_order(): void {
 global $db;
    $typ    = getVar('get', 'typ',    'num', 0);
    $ordern = getVar('get', 'ordern', 'num', 0);
    $id     = getVar('get', 'id',     'num', 0);
    $cid    = getVar('get', 'cid',    'num', 0);
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET weight = :typ    WHERE id = :id',  ['typ' => $typ,    'id' => $id]);
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET weight = :ordern WHERE id = :cid', ['ordern' => $ordern, 'cid' => $cid]);
    echo ajax_block();
}

# Favorites list view
function fav_aliste(int $obj = 0): string {
 global $db, $conf, $tpl;
    $newlistnum = intval($conf['favorites']['anum']);
    $cid = getVar('get', 'cid', 'num', 1);
    $offset = ($cid-1) * $newlistnum;
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
                $title = $val[3];
                $uname = ($val[4]) ? user_info($val[4]) : _ANONYM;
                $rows[] = adminFavoritesRow([
                    'id' => (string) $id,
                    'title_html' => adminNoteLabel($title, cutstr($title, 60)),
                    'module_label' => getModuleName($modul),
                    'posted_by_html' => $uname,
                    'functions_html' => adminMenuItems([
                        adminLinkAction('index.php?name='.$modul.'&amp;op=view&amp;id='.$fid.'#'.$fid, _MVIEW, _MVIEW),
                        adminAjaxAction('fav_aliste', 'go=5&amp;op=fav_adel&amp;id='.$id, _ONDELETE, _ONDELETE),
                    ]),
                ]);
            }
            $cont = adminFavoritesTable(implode('', $rows));
            $numpages = ceil($fav_num / $newlistnum);
            $cont .= num_ajax('pagenum', $fav_num, $numpages, $newlistnum, $conf['favorites']['anump'], $cid, '0', 5, 'fav_aliste', 'fav_aliste', 0, '', '');
        } else {
            $cont = $tpl->getHtmlFrag('alert', ['text' => _NO_INFO, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
        }
    } else {
        $cont = $tpl->getHtmlFrag('alert', ['text' => _NO_INFO, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
    }
    if ($obj) { return $cont; }
    echo $cont;
    return '';
}

# Favorites delete
function fav_adel(): void {
 global $db;
    $id = getVar('get', 'id', 'num', 0);
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE id = :id', ['id' => $id]);
    fav_aliste(0);
}

# Private messages list view
function ajax_privat(int $obj = 0): string {
    global $db, $conf, $tpl;
    $newlistnum = intval($conf['privat']['anum']);
    $cid    = getVar('get', 'cid', 'num', 1);
    $offset = intval(($cid - 1) * $newlistnum);
    list($fav_num) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat'));

    $result = $db->getSqlQuery('SELECT p.id, p.title, p.body, p.time, p.status, i.name, o.name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS i ON (p.uidin = i.id) LEFT JOIN '.PREFIX_DB.'_users AS o ON (p.uidout = o.id) ORDER BY p.time DESC LIMIT '.intval($offset).', '.intval($newlistnum));
    if ($db->getSqlRowCount($result) > 0) {
        $rows = [];
        while (list($id, $title, $body, $date, $status, $user_re, $user_se) = $db->getSqlRow($result)) {
            $unre = ($user_re) ? user_info($user_re) : _ANONYM;
            $unse = ($user_se) ? user_info($user_se) : _ANONYM;
            $date = format_time($date, _TIMESTRING);
            $info = filterReplaceText(filterMarkdown($body, 'privat', false), 'privat');
            $rows[] = adminPrivateRow([
                'id' => (string) $id,
                'title_html' => adminTitleTipLabel($info, $title, cutstr($title, 30)),
                'sender_html' => $unse,
                'receiver_html' => $unre,
                'date_value' => $date,
                'status_html' => ad_status('', $status, 1),
                'functions_html' => adminMenuItems([
                    adminAjaxAction('ajax_privat', 'go=5&amp;op=ajax_privat_del&amp;id='.$id, _ONDELETE, _ONDELETE),
                ]),
            ]);
        }
        $cont = adminPrivateTable(implode('', $rows));
        $numpages = ceil($fav_num / $newlistnum);
        $cont .= num_ajax('pagenum', $fav_num, $numpages, $newlistnum, $conf['privat']['anump'], $cid, '0', 5, 'ajax_privat', 'ajax_privat', 0, '', '');
    } else {
        $cont = $tpl->getHtmlFrag('alert', ['text' => _NO_INFO, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
    }
    if ($obj) { return $cont; }
    echo $cont;
    return '';
}

# Private message delete
function ajax_privat_del(): void {
 global $db;
    $id = getVar('get', 'id', 'num', 0);
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_privat WHERE id = :id', ['id' => $id]);
    ajax_privat(0);
}

# Show uploads files for admin
function ashow_files(): void {
 global $user, $conf, $tpl;
    $conf['uploads'] = $conf['uploads'] ?? [];
    $id   = filterVar(getVar('get', 'id',   'text', ''));
    $dir  = strtolower(getVar('get', 'dir',  'text', ''));
    $cid  = getVar('get', 'cid',  'num',  0);
    $con  = explode('|', (string)($conf['uploads'][$dir] ?? ''));
    $connum = (!empty($con[7]) && intval($con[7])) ? $con[7] : '50';
    $file = filterText(getVar('get', 'file', 'text', ''));
    $num  = ($cid) ? $cid : '1';
    $path = ($id == 1) ? 'uploads/'.$dir.'/' : 'uploads/'.$dir.'/thumb/';
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
                $dirfile = (preg_match('#php.*|js|htm|html|phtml|cgi|pl|perl|asp#i', $type)) ? adminDangerText($entry[1]) : $entry[1];
                if (in_array($type, $ftype) && $imgwidth && $imgheight) {
                    $img = adminFilePreview($a, $path.$entry[1], true);
                    $isize = $imgwidth.' x '.$imgheight;
                } else {
                    $img = adminFilePreview($a, '', false);
                    $isize = _NO;
                }
                $show = [];
                if (in_array(true, checkCompress(), true)) {
                    $show[] = adminAjaxAction('f'.$id, 'go=5&amp;op=ashow_files&amp;id='.$id.'&amp;dir='.$dir.'&amp;cid=1&amp;file='.$entry[1], _ZIP, _ZIP);
                }
                $show[] = adminAjaxAction('f'.$id, 'go=5&amp;op=ashow_files&amp;id='.$id.'&amp;dir='.$dir.'&amp;cid=0&amp;file='.$entry[1], _ONDELETE, _ONDELETE);
                $contents[] = adminFilesRow([
                    'preview_html' => $img,
                    'file_html' => $dirfile,
                    'date_value' => date(_TIMESTRING, $entry[0]),
                    'size_value' => filterSize($filesize),
                    'dimensions_value' => $isize,
                    'functions_html' => adminMenuItems($show),
                ]);
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
        $contnum = ($a > $connum) ? num_ajax('pagenum', $a, $numpages, $connum, 8, $num, '0', 5, 'ashow_files', 'f'.$id, $id, '', $dir) : '';
        $content = ($cont) ? adminFilesTable($cont).$contnum : '';
    } else {
        $content = $tpl->getHtmlFrag('alert', ['text' => _NO_INFO, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
    }
    echo $content;
}

# Format comments access
function com_access(string $name, int $selected, string $extraClass = ''): string {
    $opts  = '';
    $mods  = [_DEACTIVATE, _APOSTMOD, _APOSTNOMOD];
    for ($i = 0; $i < count($mods); $i++) {
        $opts .= getAdminOption((string)$i, $mods[$i], $selected == $i);
    }
    return getAdminSelect($name, $opts, $extraClass);
}

# Add voting
function add_voting(string $modul, string $selectName, int $selectedId, string $extraClass = ''): string {
 global $db, $locale, $conf;
    $modul  = filterVar($modul);
    $class  = $extraClass ? 'sl_field '.$extraClass : 'sl_field';
    $params = ['modul' => $modul];
    if ($conf['multilingual'] == 1) {
        $where  = "(lang = :locale OR lang = '') AND modul = :modul AND time <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')";
        $params['locale'] = $locale;
    } else {
        $where  = "modul = :modul AND time <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')";
    }
    $opts   = getAdminOption('0', _NO, false);
    $result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_voting WHERE '.$where.' ORDER BY id DESC', $params);
    if ($db->getSqlRowCount($result) > 0) {
        while (list($id, $title) = $db->getSqlRow($result)) {
            $opts .= getAdminOption((string)$id, $title, $selectedId == $id);
        }
    }
    return getAdminSelect($selectName, $opts, $class);
}

# Edit select list
function edit_list(string $modul, string $name, string $extraClass = ''): string {
 global $tpl;
    $modul = filterVar($modul);
    return $tpl->getHtmlFrag('admin-edit-list', [
        'activate_label' => _ACTIVATE,
        'apostmod_label' => _APOSTMOD,
        'apostnomod_label' => _APOSTNOMOD,
        'categories_html' => getcat($modul, 0, '', '', '', '1'),
        'checkop_label' => _CHECKOP,
        'class' => $extraClass,
        'comments_label' => _COMMENTS,
        'deactivate_label' => _DEACTIVATE,
        'delete_label' => _DELETE,
        'fixed_label' => _FIXED,
        'ladate_label' => _LADATE,
        'lhome_label' => _LHOME,
        'lnfix_label' => _LNFIX,
        'lnhome_label' => _LNHOME,
        'moveto_label' => _MOVETO,
        'name_attr' => $name,
        'ops_label' => _OPMOD,
    ]);
}

# Renders the info/help page for the current admin module
function getAdminInfo(): string {
    global $locale, $conf, $tpl;
    $id   = getVar('post', 'id', 'num', 0);
    $cont = '';
    $fdoc = static function(string $base): string {
        foreach (['.html', '.md'] as $ext) {
            if (file_exists($base.$ext)) return $base.$ext;
        }
        return '';
    };
    if ($conf['adminfo'] && $id) {
        $type    = getVar('post', 'type', 'num', 0);
        $name    = filterWord(getVar('post', 'name', 'text', ''));
        $content = filterHtml(trim(getVar('post', 'text', 'raw', '')));
        $base    = $type
            ? "modules/{$name}/admin/info/{$locale}"
            : "admin/info/{$name}/{$locale}";
        $fpdir   = $fdoc($base) ?: $base.'.html';
        if ($content) {
            $dir = dirname($fpdir);
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $fp = fopen($fpdir, 'wb');
            if ($fp !== false) {
                fwrite($fp, $content);
                fclose($fp);
            }
        }
        $thefile = file_exists($fpdir) ? file_get_contents($fpdir) : _NO_INFO;
    } else {
        $name  = filterWord(getVar('get', 'name', 'text', ''));
        $mpath = $fdoc("modules/{$name}/admin/info/{$locale}");
        $apath = $fdoc("admin/info/{$name}/{$locale}");
        if ($mpath) {
            $dir  = $mpath;
            $type = 1;
        } elseif ($apath) {
            $dir  = $apath;
            $type = 0;
        } else {
            $dir  = '';
            $type = 0;
        }
        $thefile = $dir ? file_get_contents($dir) : _NO_INFO;
        if ($conf['adminfo'] && $dir) {
            $cont .= checkPerms(BASE_DIR.'/'.$dir);
        }
    }
    $html = filterReplaceText(filterMarkdown($thefile, 'info', false), 'info');
    if ($conf['adminfo']) {
        $html .= adminInfoForm([
            'textarea_html' => textarea('1', 'text', $thefile, 'info', '25'),
            'submit_onclick' => "AjaxLoad('POST', '1', 'adm_info', 'go=5&amp;op=adm_info&amp;id=1&amp;type=".$type.'&amp;name='.$name."', { 'text':'"._CERROR1."' }); return false;",
            'submit_label' => _SAVECHANGES,
            'submit_title' => _SAVECHANGES,
        ]);
    }
    $cont .= getAdminBox($html);
    return $cont;
}
