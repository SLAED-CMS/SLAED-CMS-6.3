<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
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

function admininfo() {
 global $db, $admin, $afile, $conf, $panel, $tpl;
    if (isAdmin()) {
        $ablocks = '';
        if ($panel) {
            $newRows = [];
            if (is_active('account') && is_admin_modul('account')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_users_temp'));
                $newRows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['content_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=account&op=newuser', 'title' => _NEW_USER, 'label' => _USERS])],
                    ['content_html' => is_numeric($num) ? $tpl->getHtmlFrag('inline-badge', ['class' => ((int)$num >= 1) ? 'sl-text-danger' : 'sl-text-success', 'label' => (string)$num]) : '-'],
                ]])]);
            }
            if (is_active('album') && is_admin_modul('album')) {
                #$num = $db->getSqlRowCount($db->getSqlQuery("SELECT pid FROM ".PREFIX_DB."_album_pictures_newpicture"));
                #$num = (is_numeric($num)) ? (($num >= 1) ? "<span class=\"sl-text-danger\">".$num."</span>" : "<span class=\"sl-text-success\">".$num."</span>") : "-";
                #$n_cont .= "<tr><td><a href=\"".$afile.".php?op=album&amp;do=validnew&amp;type=checknew\" title=\""._ALBUM."\">"._ALBUM."</a>:</td><td>".$num."</td></tr>";
            }
            if (is_active('faq') && is_admin_modul('faq')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_faq WHERE status = '0'"));
                $newRows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['content_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=faq&status=1', 'title' => _FAQ, 'label' => _FAQ])],
                    ['content_html' => is_numeric($num) ? $tpl->getHtmlFrag('inline-badge', ['class' => ((int)$num >= 1) ? 'sl-text-danger' : 'sl-text-success', 'label' => (string)$num]) : '-'],
                ]])]);
            }
            if (is_active('files') && is_admin_modul('files')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_files WHERE status = '0'"));
                $newRows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['content_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=files&status=1', 'title' => _FILES, 'label' => _FILES])],
                    ['content_html' => is_numeric($num) ? $tpl->getHtmlFrag('inline-badge', ['class' => ((int)$num >= 1) ? 'sl-text-danger' : 'sl-text-success', 'label' => (string)$num]) : '-'],
                ]])]);
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_files WHERE status = '2'"));
                $newRows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['content_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=files&status=2', 'title' => _BROCFILES, 'label' => _BROCFILES])],
                    ['content_html' => is_numeric($num) ? $tpl->getHtmlFrag('inline-badge', ['class' => ((int)$num >= 1) ? 'sl-text-danger' : 'sl-text-success', 'label' => (string)$num]) : '-'],
                ]])]);
            }
            if (is_active('help') && is_admin_modul('help')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_help WHERE pid = '0' AND status = '0'"));
                $newRows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['content_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=help', 'title' => _HELP, 'label' => _HELP])],
                    ['content_html' => is_numeric($num) ? $tpl->getHtmlFrag('inline-badge', ['class' => ((int)$num >= 1) ? 'sl-text-danger' : 'sl-text-success', 'label' => (string)$num]) : '-'],
                ]])]);
            }
            if (is_active('jokes') && is_admin_modul('jokes')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_jokes WHERE status = '0'"));
                $newRows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['content_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=jokes&status=1', 'title' => _JOKES, 'label' => _JOKES])],
                    ['content_html' => is_numeric($num) ? $tpl->getHtmlFrag('inline-badge', ['class' => ((int)$num >= 1) ? 'sl-text-danger' : 'sl-text-success', 'label' => (string)$num]) : '-'],
                ]])]);
            }
            if (is_active('links') && is_admin_modul('links')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_links WHERE status = '0'"));
                $newRows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['content_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=links&status=1', 'title' => _LINKS, 'label' => _LINKS])],
                    ['content_html' => is_numeric($num) ? $tpl->getHtmlFrag('inline-badge', ['class' => ((int)$num >= 1) ? 'sl-text-danger' : 'sl-text-success', 'label' => (string)$num]) : '-'],
                ]])]);
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_links WHERE status = '2'"));
                $newRows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['content_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=links&status=2', 'title' => _BROCLINKS, 'label' => _BROCLINKS])],
                    ['content_html' => is_numeric($num) ? $tpl->getHtmlFrag('inline-badge', ['class' => ((int)$num >= 1) ? 'sl-text-danger' : 'sl-text-success', 'label' => (string)$num]) : '-'],
                ]])]);
            }
            if (is_active('media') && is_admin_modul('media')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_media WHERE status = '0'"));
                $newRows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['content_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=media&status=1', 'title' => _MEDIA, 'label' => _MEDIA])],
                    ['content_html' => is_numeric($num) ? $tpl->getHtmlFrag('inline-badge', ['class' => ((int)$num >= 1) ? 'sl-text-danger' : 'sl-text-success', 'label' => (string)$num]) : '-'],
                ]])]);
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_media WHERE status = '2'"));
                $newRows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['content_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=media&status=2', 'title' => _BROCMFILES, 'label' => _BROCMFILES])],
                    ['content_html' => is_numeric($num) ? $tpl->getHtmlFrag('inline-badge', ['class' => ((int)$num >= 1) ? 'sl-text-danger' : 'sl-text-success', 'label' => (string)$num]) : '-'],
                ]])]);
            }
            if (is_active('news') && is_admin_modul('news')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_news WHERE status = '0'"));
                $newRows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['content_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=news&status=1', 'title' => _NEWS, 'label' => _NEWS])],
                    ['content_html' => is_numeric($num) ? $tpl->getHtmlFrag('inline-badge', ['class' => ((int)$num >= 1) ? 'sl-text-danger' : 'sl-text-success', 'label' => (string)$num]) : '-'],
                ]])]);
            }
            if (is_active('pages') && is_admin_modul('pages')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_pages WHERE status = '0'"));
                $newRows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['content_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=pages&status=1', 'title' => _PAGES, 'label' => _PAGES])],
                    ['content_html' => is_numeric($num) ? $tpl->getHtmlFrag('inline-badge', ['class' => ((int)$num >= 1) ? 'sl-text-danger' : 'sl-text-success', 'label' => (string)$num]) : '-'],
                ]])]);
            }
            if (is_active('shop') && is_admin_modul('shop')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_clients WHERE status = '2'"));
                $newRows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['content_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=shop&op=clients', 'title' => _CLIENTS, 'label' => _CLIENTS])],
                    ['content_html' => is_numeric($num) ? $tpl->getHtmlFrag('inline-badge', ['class' => ((int)$num >= 1) ? 'sl-text-danger' : 'sl-text-success', 'label' => (string)$num]) : '-'],
                ]])]);
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_partners WHERE status = '2'"));
                $newRows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['content_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=shop&op=partners', 'title' => _PARTNERS, 'label' => _PARTNERS])],
                    ['content_html' => is_numeric($num) ? $tpl->getHtmlFrag('inline-badge', ['class' => ((int)$num >= 1) ? 'sl-text-danger' : 'sl-text-success', 'label' => (string)$num]) : '-'],
                ]])]);
            }
            if (is_active('whois') && is_admin_modul('whois')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_whois WHERE status = '0'"));
                $newRows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['content_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=whois&status=1', 'title' => _WHOIS, 'label' => _WHOIS])],
                    ['content_html' => is_numeric($num) ? $tpl->getHtmlFrag('inline-badge', ['class' => ((int)$num >= 1) ? 'sl-text-danger' : 'sl-text-success', 'label' => (string)$num]) : '-'],
                ]])]);
            }
            $ablocks = $tpl->getHtmlPart('sidebar-block', ['title' => _NEW, 'content_html' => $tpl->getHtmlFrag('table', ['is_wrapless' => true, 'disable_sort' => true, 'head' => [['content' => '', 'nosort' => true], ['content' => '', 'nosort' => true]], 'rows_html' => implode('', $newRows)]), 'id' => '3', 'close' => _OPCL]);
            
            $waitingRows = [];
            $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_comment WHERE status = '0'"));
            $waitingRows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                ['content_html' => $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=comments&status=1', 'title' => _COMMENTS, 'label' => _COMMENTS])],
                ['content_html' => is_numeric($num) ? $tpl->getHtmlFrag('inline-badge', ['class' => ((int)$num >= 1) ? 'sl-text-danger' : 'sl-text-success', 'label' => (string)$num]) : '-'],
            ]])]);
            $ablocks .= $tpl->getHtmlPart('sidebar-block', ['title' => _WAITINGCONT, 'content_html' => $tpl->getHtmlFrag('table', ['is_wrapless' => true, 'disable_sort' => true, 'head' => [['content' => '', 'nosort' => true], ['content' => '', 'nosort' => true]], 'rows_html' => implode('', $waitingRows)]), 'id' => '4', 'close' => _OPCL]);
            
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
        $ablocks .= $tpl->getHtmlPart('sidebar-block', ['title' => _EDITOR, 'content_html' => $econt, 'id' => '6', 'close' => _OPCL]);
        return $ablocks;
    }
}

function db_version() {
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
    $modlink = ($modul) ? '&amp;modul='.$modul : '';
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
                'class' => $parentid ? 'sl-text-success' : 'sl-text-danger',
                'label' => $parentid ? _YES : _NO,
            ]);
            $img = $tpl->getHtmlFrag('inline-badge', [
                'class' => $imgcat ? 'sl-text-success' : 'sl-text-danger',
                'label' => $imgcat ? _YES : _NO,
            ]);
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
            $mup = ($prev) ? 'go=5&amp;op=updateAdminCategoryOrder&amp;id='.$id.'&amp;cid='.$prev.'&amp;typ='.$ordernm.'&amp;mod='.$modul.'&amp;ordern='.$ordern : '';
            $mdn = ($next) ? 'go=5&amp;op=updateAdminCategoryOrder&amp;id='.$id.'&amp;cid='.$next.'&amp;typ='.$ordernp.'&amp;mod='.$modul.'&amp;ordern='.$ordern : '';
            $items = [[
                'href' => $afile.'.php?name=categories&amp;op=edit&amp;cid='.$id.$modlink,
                'label' => _FULLEDIT,
                'title' => _FULLEDIT,
            ]];
            if (!$pnum && !$ispid) {
                $items[] = [
                    'href' => $afile.'.php?name=categories&amp;op=delete&amp;id='.$id.$modlink.'&amp;token='.getSiteToken(),
                    'label' => _ONDELETE,
                    'title' => _ONDELETE,
                    'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"',
                ];
            }
            $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['content_html' => (string)$id],
                    ['content_html' => $tpl->getHtmlFrag('info-tooltip', [
                        'items' => $tipItems,
                        'label_text' => cutstr($title, 45),
                        'title_text' => $title,
                    ])],
                    ['content_html' => (string)$pnum],
                    ['content_html' => $active],
                    ['content_html' => $img],
                    ['content_html' => (string)$ordern],
                    ['content_html' => getTplMoveControls(['target' => 'ajax_cat', 'up' => $mup, 'down' => $mdn])],
                    ['content_html' => ad_status('', $cstatus)],
                    ['content_html' => $tpl->getHtmlFrag('row-actions', [
                        'trigger_label' => _EDITOR,
                        'items' => $items,
                    ])],
                ],
            ])]);
        }
        $cont = $tpl->getHtmlFrag('table', [
            'head' => [
            ['content' => _ID],
            ['content' => _CATEGORY],
            ['content' => cutstr(_CONTENT, 3, 1)],
            ['content' => cutstr(_SUBCATEGORY, 3, 1), 'nosort' => true],
            ['content' => cutstr(_IMG, 2, 1), 'nosort' => true],
            ['content' => _WEIGHT, 'colspan' => 2],
            ['content' => _STATUS, 'nosort' => true],
            ['content' => _FUNCTIONS, 'nosort' => true],
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
            ['label' => _NAME, 'value' => $title, 'is_last' => $lang === ''],
        ];
        if ($lang !== '') {
            $tipItems[] = ['label' => _LANGUAGE, 'value' => getLangName($lang), 'is_last' => false];
        }
        $tipItems[] = ['label' => _PURCHASED, 'value' => $exp, 'is_last' => true];
        if ($bpos == 'l') {
            $bpos = $tpl->getHtmlFrag('inline-badge', ['class' => 'sl-note', 'label' => _LEFT, 'title_text' => _LEFTBLOCK]);
        } elseif ($bpos == 'r') {
            $bpos = $tpl->getHtmlFrag('inline-badge', ['class' => 'sl-note', 'label' => _RIGHT, 'title_text' => _RIGHTBLOCK]);
        } elseif ($bpos == 'c') {
            $bpos = $tpl->getHtmlFrag('inline-badge', ['class' => 'sl-note', 'label' => _CENTERUP, 'title_text' => _CENTERBLOCK]);
        } elseif ($bpos == 'd') {
            $bpos = $tpl->getHtmlFrag('inline-badge', ['class' => 'sl-note', 'label' => _CENTERDOWN, 'title_text' => _CENTERBLOCK]);
        } elseif ($bpos == 'b') {
            $bpos = $tpl->getHtmlFrag('inline-badge', ['class' => 'sl-note', 'label' => _BANNERUP, 'title_text' => _BANNER]);
        } elseif ($bpos == 'f') {
            $bpos = $tpl->getHtmlFrag('inline-badge', ['class' => 'sl-note', 'label' => _BANNERDOWN, 'title_text' => _BANNER]);
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
        $mup = ($prev) ? 'go=5&amp;op=updateAdminBlockOrder&amp;id='.$bid.'&amp;cid='.$prev.'&amp;typ='.$wminus.'&amp;ordern='.$weight : '';
        $mdn = ($next) ? 'go=5&amp;op=updateAdminBlockOrder&amp;id='.$bid.'&amp;cid='.$next.'&amp;typ='.$wplus.'&amp;ordern='.$weight : '';
        $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['content_html' => (string) $bid],
                ['content_html' => $tpl->getHtmlFrag('info-tooltip', [
                    'items' => $tipItems,
                    'label_text' => cutstr(getConst($title), 15),
                    'title_text' => $title,
                ])],
                ['content_html' => $type],
                ['content_html' => $who_view],
                ['content_html' => $bpos],
                ['content_html' => (string) $weight],
                ['content_html' => getTplMoveControls(['target' => 'ajax_block', 'up' => $mup, 'down' => $mdn])],
                ['content_html' => ad_status('', $active)],
                ['content_html' => $tpl->getHtmlFrag('row-actions', [
                    'trigger_label' => _EDITOR,
                    'items' => [[
                        'href' => $afile.'.php?name=blocks&amp;op=change&amp;id='.$bid.'&amp;act='.$active.'&amp;token='.getSiteToken(),
                        'label' => $active ? _DEACTIVATE : _ACTIVATE,
                        'title' => $active ? _DEACTIVATE : _ACTIVATE,
                    ], [
                        'href' => $afile.'.php?name=blocks&amp;op=edit&amp;id='.$bid,
                        'label' => _FULLEDIT,
                        'title' => _FULLEDIT,
                    ], [
                        'href' => $afile.'.php?name=blocks&amp;op=delete&amp;id='.$bid.'&amp;token='.getSiteToken(),
                        'label' => _ONDELETE,
                        'title' => _ONDELETE,
                        'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"',
                    ]],
                ])],
            ],
        ])]);
    }
    return $tpl->getHtmlFrag('table', [
        'head' => [
            ['content' => _ID],
            ['content' => _TITLE],
            ['content' => _TYPE],
            ['content' => _VIEW],
            ['content' => _POSITION],
            ['content' => _WEIGHT, 'colspan' => 2],
            ['content' => _STATUS, 'nosort' => true],
            ['content' => _FUNCTIONS, 'nosort' => true],
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
                $title = html_entity_decode((string)$val[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $uname = ($val[4]) ? user_info($val[4]) : _ANONYM;
                $delhref = 'admin.php?name=favorites&amp;op=delete&amp;id='.$id.'&amp;num='.$cid.'&amp;token='.getSiteToken();
                $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                    'cells' => [
                        ['content_html' => (string) $id],
                        ['content_html' => $tpl->getHtmlFrag('inline-badge', ['class' => 'sl-note', 'label' => cutstr($title, 60), 'title_text' => $title])],
                        ['content_html' => getModuleName($modul)],
                        ['content_html' => $uname],
                        ['content_html' => $tpl->getHtmlFrag('row-actions', [
                            'trigger_label' => _FUNCTIONS,
                            'items' => [[
                                'href' => 'index.php?name='.$modul.'&amp;op=view&amp;id='.$fid.'#'.$fid,
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
            $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $delhref = 'admin.php?name=privat&amp;op=delete&amp;id='.$id.'&amp;num='.$cid.'&amp;token='.getSiteToken();
            $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['content_html' => (string) $id],
                    ['content_html' => $tpl->getHtmlFrag('info-tooltip', ['content' => $info]).$tpl->getHtmlFrag('inline-badge', ['class' => 'sl-note', 'label' => cutstr($title, 30), 'title_text' => $title])],
                    ['content_html' => $unse],
                    ['content_html' => $unre],
                    ['content_html' => $date],
                    ['content_html' => ad_status('', $status, 1)],
                    ['content_html' => $tpl->getHtmlFrag('row-actions', [
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
                $dirfile = (preg_match('#php.*|js|htm|html|phtml|cgi|pl|perl|asp#i', $type))
                    ? $tpl->getHtmlFrag('inline-badge', ['class' => 'sl-text-danger', 'label' => $entry[1]])
                    : $entry[1];
                if (in_array($type, $ftype) && $imgwidth && $imgheight) {
                    $img = $tpl->getHtmlFrag('image-preview', [
                        'preview_id' => 'sf-form-'.$a,
                        'toggle_onclick' => "HideShow('sf-form-".$a."', 'fold', 'up', 500);",
                        'image_url' => $path.$entry[1],
                        'fallback_url' => 'templates/admin/images/admin/no.png',
                        'image_title' => _IMG,
                        'no_title' => _NO,
                        'show_toggle' => true,
                        'show_fallback' => false,
                    ]);
                    $isize = $imgwidth.' x '.$imgheight;
                } else {
                    $img = $tpl->getHtmlFrag('image-preview', [
                        'preview_id' => 'sf-form-'.$a,
                        'toggle_onclick' => "HideShow('sf-form-".$a."', 'fold', 'up', 500);",
                        'image_url' => '',
                        'fallback_url' => 'templates/admin/images/admin/no.png',
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
                        'href' => 'index.php?go=5&amp;op=getAdminUploadFiles&amp;id='.$id.'&amp;dir='.$dir.'&amp;cid=1&amp;file='.$entry[1],
                        'label' => _ZIP,
                        'title' => _ZIP,
                        'link_attr' => 'hx-get="index.php?go=5&amp;op=getAdminUploadFiles&amp;id='.$id.'&amp;dir='.$dir.'&amp;cid=1&amp;file='.$entry[1].'" hx-target="#repf'.$id.'" hx-swap="innerHTML" hx-push-url="false"',
                    ];
                }
                $show[] = [
                    'href' => 'index.php?go=5&amp;op=getAdminUploadFiles&amp;id='.$id.'&amp;dir='.$dir.'&amp;cid=0&amp;file='.$entry[1],
                    'label' => _ONDELETE,
                    'title' => _ONDELETE,
                    'link_attr' => 'hx-get="index.php?go=5&amp;op=getAdminUploadFiles&amp;id='.$id.'&amp;dir='.$dir.'&amp;cid=0&amp;file='.$entry[1].'" hx-target="#repf'.$id.'" hx-swap="innerHTML" hx-push-url="false"',
                ];
                $contents[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                    'cells' => [
                        ['content_html' => $img],
                        ['content_html' => $dirfile],
                        ['content_html' => date(_TIMESTRING, $entry[0])],
                        ['content_html' => filterSize($filesize)],
                        ['content_html' => $isize],
                        ['content_html' => $tpl->getHtmlFrag('row-actions', [
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

# Renders the info/help page for the current admin module
function getAdminInfo(): string {
    global $afile, $locale, $conf, $tpl, $prs;
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
    $html = $prs->filterContent($thefile, false, 'info');
    if ($conf['adminfo']) {
        $html .= $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php?name='.$name.'&op=info',
            'hidden' => [
                ['nameattr' => 'id', 'valueattr' => '1'],
                ['nameattr' => 'type', 'valueattr' => (string)$type],
                ['nameattr' => 'name', 'valueattr' => $name],
            ],
            'content_html' => getTplTextarea(['id' => '1', 'name' => 'text', 'value' => $thefile, 'mod' => 'info', 'rows' => '25']),
            'submit_label' => _SAVECHANGES,
        ]);
    }
    $cont .= $tpl->getHtmlPart('box', [
        'content_html' => $html,
    ]);
    return $cont;
}
