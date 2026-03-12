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
    $to = count($f);
    if ($day > 15) {
        $from = 0;
        $to = 15;
    } else {
        $from = (!$file && date('d') <= 15) ? 0 : 15;
        if ($from < 0) $from = 0;
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
            $w = round((230 / $max2) * $day[2]);
            if ($w < 4) $w = 4;
            $off = 134;
            imagefilledrectangle($image, $off+$conf['statistic']['bet']*$i+1, 250-$w+1, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi'], 249, $yellow);
            imagerectangle($image, $off+$conf['statistic']['bet']*$i, 250-$w, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi'], 249, $black);
            imagerectangle($image, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+1, 250-$w+3, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+2, 249, $gray);
            $w = round((230 / $max1) * $day[1]);
            if ($w < 5) $w = 1;
            $off = 120;

            imagefilledrectangle($image, $off+$conf['statistic']['bet']*$i+1, 250-$w+1, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+3, 249, $wblue);
            imagerectangle($image, $off+$conf['statistic']['bet']*$i,250-$w, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+3, 249, $black);
            imagerectangle($image, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+4, 250-$w+4, $off+$conf['statistic']['bet']*$i+$conf['statistic']['shi']+5, 249, $black);
            $zzz = $day[1] - ($day[4] + $day[5]);
            $w = round((230 / $max1) * $zzz);
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

    imagestring($image, 1, 5, 120, 'PAGES PER VIS.: '.round($today/$unique, 2), $wblue);
    imagestring($image, 1, 5, 130, 'AVR. AUDIENCE: '.round($auditory/$i), $wblue);

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

# Build admin tabs navigation with automatic module title and icon fallback
function getAdminTabs(string $sub, array $ops, array $tabs, array $sops = [], array $stabs = [], int $act = 0, bool $hassub = false, int $actsub = 0, string $mtab = 'menutab'): string {
    global $afile, $conf;
    $ttl = _ADMINMENU;
    $ico = 'components.png';
    $name = getVar('req', 'name', 'var');
    if ($name !== '' && isset($conf['modules'][$name]) && is_array($conf['modules'][$name])) {
        $lang = trim($conf['modules'][$name]['lang'] ?? '');
        if ($lang !== '') $ttl = defined($lang) ? constant($lang) : $lang;
        $img = basename(trim($conf['modules'][$name]['img'] ?? ''));
        if ($img !== '' && file_exists(BASE_DIR.'/templates/admin/images/admin/'.$img)) $ico = $img;
    }
    $cnt = '<ul id="'.$mtab.'" class="reset tabmenu">';
    $scnt = '';
    $k = 0;
    foreach ($tabs as $tab) {
        if ($tab === '') { $k++; continue; }
        $sel = ($k === $act) ? ' class="selected"' : '';
        if ($hassub && !empty($stabs)) {
            $scnt = '<ul id="'.$mtab.'s" class="reset tabsubmenu">';
            $l = 0;
            foreach ($stabs as $stab) {
                if ($stab === '') { $l++; continue; }
                $ssel = ($l === $actsub) ? ' class="selected"' : '';
                $hrefsub = !empty($sops[$l])
                    ? 'href="'.$afile.'.php?'.$sops[$l].'"'
                    : 'rel="tabcs'.$l.'" href="#"';
                $scnt .= '<li><a '.$hrefsub.$ssel.'><b>'.$stab.'</b></a></li>';
                $l++;
            }
            $scnt .= '</ul>';
        }
        $href = !empty($ops[$k])
            ? 'href="'.$afile.'.php?'.$ops[$k].'"'
            : 'rel="tabc'.$k.'" href="#"';
        $cnt .= '<li><a '.$href.$sel.'><b>'.$tab.'</b></a></li>';
        $k++;
    }
    $cnt .= ($scnt !== '') ? '</ul>'.$scnt : '</ul>';
    return setTemplateBasic('title', ['{%title%}' => $ttl, '{%icon%}' => $ico, '{%subtitle%}' => $sub, '{%content%}' => $cnt]);
}

function admininfo() {
 global $db, $admin, $afile, $conf, $panel;
    if (isAdmin()) {
        $ablocks = '';
        if ($panel) {
            $n_cont = '<table class="sl_tab_bl">';
            if (is_active('account') && is_admin_modul('account')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_users_temp'));
                $num = (is_numeric($num)) ? (($num >= 1) ? '<span class="sl_red">'.$num.'</span>' : '<span class="sl_green">'.$num.'</span>') : '-';
                $n_cont .= '<tr><td><a href="'.$afile.'.php?name=account&op=newuser" title="'._NEW_USER.'">'._USERS.'</a>:</td><td>'.$num.'</td></tr>';
            }
            if (is_active('album') && is_admin_modul('album')) {
                #$num = $db->getSqlRowCount($db->getSqlQuery("SELECT pid FROM ".PREFIX_DB."_album_pictures_newpicture"));
                #$num = (is_numeric($num)) ? (($num >= 1) ? "<span class=\"sl_red\">".$num."</span>" : "<span class=\"sl_green\">".$num."</span>") : "-";
                #$n_cont .= "<tr><td><a href=\"".$afile.".php?op=album&amp;do=validnew&amp;type=checknew\" title=\""._ALBUM."\">"._ALBUM."</a>:</td><td>".$num."</td></tr>";
            }
            if (is_active('faq') && is_admin_modul('faq')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_faq WHERE status = '0'"));
                $num = (is_numeric($num)) ? (($num >= 1) ? '<span class="sl_red">'.$num.'</span>' : '<span class="sl_green">'.$num.'</span>') : '-';
                $n_cont .= '<tr><td><a href="'.$afile.'.php?name=faq&status=1" title="'._FAQ.'">'._FAQ.'</a>:</td><td>'.$num.'</td></tr>';
            }
            if (is_active('files') && is_admin_modul('files')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_files WHERE status = '0'"));
                $num = (is_numeric($num)) ? (($num >= 1) ? '<span class="sl_red">'.$num.'</span>' : '<span class="sl_green">'.$num.'</span>') : '-';
                $n_cont .= '<tr><td><a href="'.$afile.'.php?name=files&status=1" title="'._FILES.'">'._FILES.'</a>:</td><td>'.$num.'</td></tr>';
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_files WHERE status = '2'"));
                $num = (is_numeric($num)) ? (($num >= 1) ? '<span class="sl_red">'.$num.'</span>' : '<span class="sl_green">'.$num.'</span>') : '-';
                $n_cont .= '<tr><td><a href="'.$afile.'.php?name=files&status=2" title="'._BROCFILES.'">'._BROCFILES.'</a>:</td><td>'.$num.'</td></tr>';
            }
            if (is_active('help') && is_admin_modul('help')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_help WHERE pid = '0' AND status = '0'"));
                $num = (is_numeric($num)) ? (($num >= 1) ? '<span class="sl_red">'.$num.'</span>' : '<span class="sl_green">'.$num.'</span>') : '-';
                $n_cont .= '<tr><td><a href="'.$afile.'.php?name=help" title="'._HELP.'">'._HELP.'</a>:</td><td>'.$num.'</td></tr>';
            }
            if (is_active('jokes') && is_admin_modul('jokes')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_jokes WHERE status = '0'"));
                $num = (is_numeric($num)) ? (($num >= 1) ? '<span class="sl_red">'.$num.'</span>' : '<span class="sl_green">'.$num.'</span>') : '-';
                $n_cont .= '<tr><td><a href="'.$afile.'.php?name=jokes&status=1" title="'._JOKES.'">'._JOKES.'</a>:</td><td>'.$num.'</td></tr>';
            }
            if (is_active('links') && is_admin_modul('links')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_links WHERE status = '0'"));
                $num = (is_numeric($num)) ? (($num >= 1) ? '<span class="sl_red">'.$num.'</span>' : '<span class="sl_green">'.$num.'</span>') : '-';
                $n_cont .= '<tr><td><a href="'.$afile.'.php?name=links&status=1" title="'._LINKS.'">'._LINKS.'</a>:</td><td>'.$num.'</td></tr>';
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_links WHERE status = '2'"));
                $num = (is_numeric($num)) ? (($num >= 1) ? '<span class="sl_red">'.$num.'</span>' : '<span class="sl_green">'.$num.'</span>') : '-';
                $n_cont .= '<tr><td><a href="'.$afile.'.php?name=links&status=2" title="'._BROCLINKS.'">'._BROCLINKS.'</a>:</td><td>'.$num.'</td></tr>';
            }
            if (is_active('media') && is_admin_modul('media')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_media WHERE status = '0'"));
                $num = (is_numeric($num)) ? (($num >= 1) ? '<span class="sl_red">'.$num.'</span>' : '<span class="sl_green">'.$num.'</span>') : '-';
                $n_cont .= '<tr><td><a href="'.$afile.'.php?name=media&status=1" title="'._MEDIA.'">'._MEDIA.'</a>:</td><td>'.$num.'</td></tr>';
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_media WHERE status = '2'"));
                $num = (is_numeric($num)) ? (($num >= 1) ? '<span class="sl_red">'.$num.'</span>' : '<span class="sl_green">'.$num.'</span>') : '-';
                $n_cont .= '<tr><td><a href="'.$afile.'.php?name=media&status=2" title="'._BROCMFILES.'">'._BROCMFILES.'</a>:</td><td>'.$num.'</td></tr>';
            }
            if (is_active('news') && is_admin_modul('news')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_news WHERE status = '0'"));
                $num = (is_numeric($num)) ? (($num >= 1) ? '<span class="sl_red">'.$num.'</span>' : '<span class="sl_green">'.$num.'</span>') : '-';
                $n_cont .= '<tr><td><a href="'.$afile.'.php?name=news&status=1" title="'._NEWS.'">'._NEWS.'</a>:</td><td>'.$num.'</td></tr>';
            }
            if (is_active('pages') && is_admin_modul('pages')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_pages WHERE status = '0'"));
                $num = (is_numeric($num)) ? (($num >= 1) ? '<span class="sl_red">'.$num.'</span>' : '<span class="sl_green">'.$num.'</span>') : '-';
                $n_cont .= '<tr><td><a href="'.$afile.'.php?name=pages&status=1" title="'._PAGES.'">'._PAGES.'</a>:</td><td>'.$num.'</td></tr>';
            }
            if (is_active('shop') && is_admin_modul('shop')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_clients WHERE status = '2'"));
                $num = (is_numeric($num)) ? (($num >= 1) ? '<span class="sl_red">'.$num.'</span>' : '<span class="sl_green">'.$num.'</span>') : '-';
                $n_cont .= '<tr><td><a href="'.$afile.'.php?name=shop&op=clients" title="'._CLIENTS.'">'._CLIENTS.'</a>:</td><td>'.$num.'</td></tr>';
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_partners WHERE status = '2'"));
                $num = (is_numeric($num)) ? (($num >= 1) ? '<span class="sl_red">'.$num.'</span>' : '<span class="sl_green">'.$num.'</span>') : '-';
                $n_cont .= '<tr><td><a href="'.$afile.'.php?name=shop&op=partners" title="'._PARTNERS.'">'._PARTNERS.'</a>:</td><td>'.$num.'</td></tr>';
            }
            if (is_active('whois') && is_admin_modul('whois')) {
                $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_whois WHERE status = '0'"));
                $num = (is_numeric($num)) ? (($num >= 1) ? '<span class="sl_red">'.$num.'</span>' : '<span class="sl_green">'.$num.'</span>') : '-';
                $n_cont .= '<tr><td><a href="'.$afile.'.php?name=whois&status=1" title="'._WHOIS.'">'._WHOIS.'</a>:</td><td>'.$num.'</td></tr>';
            }
            $n_cont .= '</table>';
            $ablocks = setTemplateBlock('block-left', ['{%title%}' => _NEW, '{%content%}' => $n_cont, '{%id%}' => '3']);
            
            $w_cont = '<table class="sl_tab_bl">';
            $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_comment WHERE status = '0'"));
            $num = (is_numeric($num)) ? (($num >= 1) ? '<span class="sl_red">'.$num.'</span>' : '<span class="sl_green">'.$num.'</span>') : '-';
            $w_cont .= '<tr><td><a href="'.$afile.'.php?name=comments&status=1" title="'._COMMENTS.'">'._COMMENTS.'</a>:</td><td>'.$num.'</td></tr>';
            $w_cont .= '</table>';
            $ablocks .= setTemplateBlock('block-left', ['{%title%}' => _WAITINGCONT, '{%content%}' => $w_cont, '{%id%}' => '4']);
            
        }
        $editor = (isset($admin[3])) ? intval(substr($admin[3], 0, 1)) : 0;
        $e_cont = '<form method="post" action="'.$afile.'.php"><table><tr><td>'.redaktor('1', 'editor', '', $editor, 1).'<input type="hidden" name="refer" value="1"><input type="hidden" name="op" value="changeeditor"></td></tr></table></form>';
        $ablocks .= setTemplateBlock('block-left', ['{%title%}' => _EDITOR, '{%content%}' => $e_cont, '{%id%}' => '6']);
        return $ablocks;
    }
}

function db_version() {
 global $db;
    list($dbv) = $db->getSqlRow($db->getSqlQuery('SELECT VERSION()'));
    return $dbv;
}

function ajax_cat(string $modul = '', int $obj = 0): string {
 global $db, $afile, $conf;
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
        $fcont = '';
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
            $active = ($parentid) ? '<div class="sl_green">'._YES.'</div>' : '<div class="sl_red">'._NO.'</div>';
            $img = ($imgcat) ? '<div class="sl_green">'._YES.'</div>' : '<div class="sl_red">'._NO.'</div>';
            $flag = $parentid;
            while ($flag != '0') {
                $title = $massiv[$flag][2].' / '.$title;
                $flag = $massiv[$flag][6];
            }
            $descript = ($description) ? $description : _NO;
            $subcat = ($ispid) ? $ispid : _NO;
            $clang = ($conf['multilingual'] == 1) ? ((!$language) ? '<br>'._LANGUAGE.': '._ALL : '<br>'._LANGUAGE.': '.getLangName($language)) : '';
            $delete = (!$pnum && !$ispid) ? '||<a href="'.$afile.'.php?op=cat_del&amp;id='.$id.$modlink."&amp;refer=1\" OnClick=\"return DelCheck(this, '"._DELETE.' &quot;'.$title."&quot;?');\" title=\""._ONDELETE.'">'._ONDELETE.'</a>' : '';
            $fcont .= '<tr><td>'.$id.'</td>'
            .'<td>'.title_tip(_DESCRIPTION.': '.$descript.'<br>'._CATEGORIES.': '.$subcat.$clang).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 50).'</span></td>'
            .'<td>'.$pnum.'</td>'
            .'<td>'.$active.'</td>'
            .'<td>'.$img.'</td>'
            .'<td>'.$ordern.'</td><td>';
            $fcont .= ($con1) ? "<span OnClick=\"AjaxLoad('GET', '0', 'ajax_cat', 'go=5&amp;op=cat_order&amp;id=".$id.'&amp;cid='.$con1.'&amp;typ='.$ordernm.'&amp;mod='.$modul.'&amp;ordern='.$ordern."', ''); return false;\" title=\""._BLOCKUP.'" class="sl_bl_up"></span>' : '';
            $fcont .= ($con2) ? "<span OnClick=\"AjaxLoad('GET', '0', 'ajax_cat', 'go=5&amp;op=cat_order&amp;id=".$id.'&amp;cid='.$con2.'&amp;typ='.$ordernp.'&amp;mod='.$modul.'&amp;ordern='.$ordern."', ''); return false;\" title=\""._BLOCKDOWN.'" class="sl_bl_down"></span>' : '';
            $fcont .= '</td><td>'.ad_status('', $cstatus).'</td>'
            .'<td>'.add_menu('<a href="'.$afile.'.php?name=categories&amp;op=edit&amp;cid='.$id.$modlink.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>'.$delete).'</td></tr>';
        }
        $cont = '<table class="sl_table_list"><thead><tr><th>'._ID.'</th><th>'._CATEGORY.'</th><th>'.cutstr(_CONTENT, 3, 1).'</th><th>'.cutstr(_SUBCATEGORY, 3, 1).'</th><th>'.cutstr(_IMG, 2, 1).'</th><th colspan="2">'._WEIGHT.'</th><th>'._STATUS.'</th><th>'._FUNCTIONS.'</th></tr></thead><tbody>'.$fcont.'</tbody></table>';
    } else {
        $cont = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
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
    $cont = '<select name="'.$name.'[]" multiple="multiple" class="'.$class.'">';
    if ($limit < 1) {
        $cont .= '<option value="0|0"';
        $cont .= ($selected == '0|0') ? ' selected' : '';
        $cont .= '>'._ALL.'</option>';
    }
    if ($limit < 2) {
        $cont .= '<option value="1|0"';
        $cont .= ($selected == '1|0') ? ' selected' : '';
        $cont .= '>'._USERS.'</option>';
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
        $cont .= '<option value="2|'.$id.'"'.$sel.'>'.$title.'</option>';
    }
    $cont .= '<option value="3|0"';
    $cont .= ($selected == '3|0') ? ' selected' : '';
    $cont .= '>'._ADMIN.'</option></select>';
    return $cont;
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
    $fcont  = '';
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
        $blang = ($conf['multilingual'] == 1) ? ((!$lang) ? '<br>'._LANGUAGE.': '._ALL : '<br>'._LANGUAGE.': '.getLangName($lang)) : '';
        $fcont .= '<tr><td>'.$bid.'</td><td>'.title_tip(_NAME.': '.$title.'<br>'._PURCHASED.': '.$exp.$blang).cutstr(getConst($title), 15).'</td>';
        if ($bpos == 'l') {
            $bpos = '<span title="'._LEFTBLOCK.'" class="sl_note">'._LEFT.'</span>';
        } elseif ($bpos == 'r') {
            $bpos = '<span title="'._RIGHTBLOCK.'" class="sl_note">'._RIGHT.'</span>';
        } elseif ($bpos == 'c') {
            $bpos = '<span title="'._CENTERBLOCK.'" class="sl_note">'._CENTERUP.'</span>';
        } elseif ($bpos == 'd') {
            $bpos = '<span title="'._CENTERBLOCK.'" class="sl_note">'._CENTERDOWN.'</span>';
        } elseif ($bpos == 'b') {
            $bpos = '<span title="'._BANNER.'" class="sl_note">'._BANNERUP.'</span>';
        } elseif ($bpos == 'f') {
            $bpos = '<span title="'._BANNER.'" class="sl_note">'._BANNERDOWN.'</span>';
        }
        if ($bkey == '') {
            $type = ($url) ? 'RSS/RDF' : 'HTML';
            if ($bfile != '') $type = _BLOCKFILE2;
        } elseif ($bkey != '') {
            $type = _BLOCKSYSTEM;
        }
        $fcont .= '<td>'.$type.'</td>';
        if ($view == 0) {
            $who_view = _MVALL;
        } elseif ($view == 1) {
            $who_view = _MVUSERS;
        } elseif ($view == 2) {
            $who_view = _MVADMIN;
        } elseif ($view == 3) {
            $who_view = _MVANON;
        }
        $fcont .= '<td>'.$who_view.'</td>'
        .'<td>'.$bpos.'</td>'
        .'<td>'.$weight.'</td><td>';
        $fcont .= ($con1) ? "<span OnClick=\"AjaxLoad('GET', '0', 'ajax_block', 'go=5&amp;op=blocks_order&amp;id=".$bid.'&amp;cid='.$con1.'&amp;typ='.$weight_minus.'&amp;ordern='.$weight."', ''); return false;\" title=\""._BLOCKUP.'" class="sl_bl_up"></span>' : '';
        $fcont .= ($con2) ? "<span OnClick=\"AjaxLoad('GET', '0', 'ajax_block', 'go=5&amp;op=blocks_order&amp;id=".$bid.'&amp;cid='.$con2.'&amp;typ='.$weight_plus.'&amp;ordern='.$weight."', ''); return false;\" title=\""._BLOCKDOWN.'" class="sl_bl_down"></span>' : '';
        $fcont .= '</td><td>'.ad_status('', $active).'</td><td>'.add_menu(ad_status($afile.'.php?name=blocks&amp;op=change&amp;id='.$bid.'&amp;act='.$active, $active).'||<a href="'.$afile.'.php?name=blocks&amp;op=edit&amp;id='.$bid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=blocks&amp;op=del&amp;id='.$bid."\" OnClick=\"return DelCheck(this, '"._DELETE.' &quot;'.$title."&quot;?');\" title=\""._ONDELETE.'">'._ONDELETE.'</a>').'</td></tr>';
    }
    $cont = '<table class="sl_table_list"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._TYPE.'</th><th>'._VIEW.'</th><th>'._POSITION.'</th><th colspan="2">'._WEIGHT.'</th><th>'._STATUS.'</th><th>'._FUNCTIONS.'</th></tr></thead><tbody>'.$fcont.'</tbody></table>';
    return $cont;
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
 global $db, $conf;
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
            $cont = '<table class="sl_table_list"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._MODUL.'</th><th>'._POSTEDBY.'</th><th>'._FUNCTIONS.'</th></tr></thead><tbody>';
            foreach ($ffmassiv as $key => $val) {
                $id = $val[0];
                $fid = $val[1];
                $modul = $val[2];
                $title = $val[3];
                $uname = ($val[4]) ? user_info($val[4]) : _ANONYM;
                $cont .= '<tr>'
                .'<td>'.$id.'</td>'
                .'<td><span title="'.$title.'" class="sl_note">'.cutstr($title, 60).'</span></td>'
                .'<td>'.getModuleName($modul).'</td>'
                .'<td>'.$uname.'</td>'
                .'<td>'.add_menu('<a href="index.php?name='.$modul.'&amp;op=view&amp;id='.$fid.'#'.$fid.'" title="'._MVIEW.'">'._MVIEW."</a>||<a OnClick=\"AjaxLoad('GET', '0', 'fav_aliste', 'go=5&amp;op=fav_adel&amp;id=".$id."', ''); return false;\" title=\""._ONDELETE.'">'._ONDELETE.'</a>').'</td>';
            }
            $cont .= '</tbody></table>';
            $numpages = ceil($fav_num / $newlistnum);
            $cont .= num_ajax('pagenum', $fav_num, $numpages, $newlistnum, $conf['favorites']['anump'], $cid, '0', 5, 'fav_aliste', 'fav_aliste', 0, '', '');
        } else {
            $cont = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
        }
    } else {
        $cont = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
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
    global $db, $conf;
    $newlistnum = intval($conf['privat']['anum']);
    $cid    = getVar('get', 'cid', 'num', 1);
    $offset = intval(($cid - 1) * $newlistnum);
    list($fav_num) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat'));

    $result = $db->getSqlQuery('SELECT p.id, p.title, p.body, p.time, p.status, i.name, o.name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS i ON (p.uidin = i.id) LEFT JOIN '.PREFIX_DB.'_users AS o ON (p.uidout = o.id) ORDER BY p.time DESC LIMIT '.intval($offset).', '.intval($newlistnum));
    if ($db->getSqlRowCount($result) > 0) {
        $cont = '<table class="sl_table_list"><thead><tr><th>'._ID.'</th><th>'._TITLE.'</th><th>'._PRSE.'</th><th>'._PRRE.'</th><th>'._DATE.'</th><th>'._STATUS.'</th><th>'._FUNCTIONS.'</th></tr></thead><tbody>';
        while (list($id, $title, $body, $date, $status, $user_re, $user_se) = $db->getSqlRow($result)) {
            $unre = ($user_re) ? user_info($user_re) : _ANONYM;
            $unse = ($user_se) ? user_info($user_se) : _ANONYM;
            $date = format_time($date, _TIMESTRING);
            $info = filterReplaceText(filterMarkdown($body, 'privat', false), 'privat');
            $cont .= '<tr>'
            .'<td>'.$id.'</td>'
            .'<td>'.title_tip($info).'<span title="'.$title.'" class="sl_note">'.cutstr($title, 30).'</span></td>'
            .'<td>'.$unse.'</td>'
            .'<td>'.$unre.'</td>'
            .'<td>'.$date.'</td>'
            .'<td>'.ad_status('', $status, 1).'</td>'
            .'<td>'.add_menu("<a OnClick=\"AjaxLoad('GET', '0', 'ajax_privat', 'go=5&amp;op=ajax_privat_del&amp;id=".$id."', ''); return false;\" title=\""._ONDELETE.'">'._ONDELETE.'</a>').'</td>';
        }
        $cont .= '</tbody></table>';
        $numpages = ceil($fav_num / $newlistnum);
        $cont .= num_ajax('pagenum', $fav_num, $numpages, $newlistnum, $conf['privat']['anump'], $cid, '0', 5, 'ajax_privat', 'ajax_privat', 0, '', '');
    } else {
        $cont = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
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
 global $user, $conf;
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
        $dh = opendir($path);
        while ($entry = readdir($dh)) {
            if ($entry != '.' && $entry != '..' && $entry != 'index.html' && !is_dir($path.$entry)) $files[] = [filemtime($path.$entry), $entry];
        }
        closedir($dh);
        if (is_array($files)) {
            $a = 0;
            rsort($files);
            foreach ($files as $entry) {
                $filesize = filesize($path.$entry[1]);
                list($imgwidth, $imgheight) = getimagesize($path.$entry[1]);
                $type = strtolower(substr(strrchr($entry[1], '.'), 1));
                $ftype = ['png', 'jpg', 'jpeg', 'gif', 'bmp'];
                $dirfile = (preg_match('#php.*|js|htm|html|phtml|cgi|pl|perl|asp#i', $type)) ? '<span class="sl_red">'.$entry[1].'</span>' : $entry[1];
                if (in_array($type, $ftype) && $imgwidth && $imgheight) {
                    $img = "<div OnClick=\"HideShow('sf-form-".$a."', 'fold', 'up', 500);\" class=\"sl_drop sl_preview_mini\" style=\"background-image: url(".$path.$entry[1].');" title="'._IMG.'"><span id="sf-form-'.$a.'" class="sl_drop-form"><img src="'.$path.$entry[1].'" alt="'._IMG.'" title="'._IMG.'"></span></div>';
                    $isize = $imgwidth.' x '.$imgheight;
                } else {
                    $img = '<div class="sl_preview_mini" style="background-image: url(templates/admin/images/admin/no.png);" title="'._NO.'"></div>';
                    $isize = _NO;
                }
                $show = (in_array(true, checkCompress(), true)) ? "||<a OnClick=\"AjaxLoad('GET', '0', 'f".$id."', 'go=5&amp;op=ashow_files&amp;id=".$id.'&amp;dir='.$dir.'&amp;cid=1&amp;file='.$entry[1]."', ''); return false;\" title=\""._ZIP.'">'._ZIP.'</a>' : '';
                $show .= "||<a OnClick=\"AjaxLoad('GET', '0', 'f".$id."', 'go=5&amp;op=ashow_files&amp;id=".$id.'&amp;dir='.$dir.'&amp;cid=0&amp;file='.$entry[1]."', ''); return false;\" title=\""._ONDELETE.'">'._ONDELETE.'</a>';
                $contents[] = '<tr><td>'.$img.'</td><td>'.$dirfile.'</td><td>'.date(_TIMESTRING, $entry[0]).'</td><td>'.filterSize($filesize).'</td><td>'.$isize.'</td><td>'.add_menu($show).'</td></tr>';
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
        $content = ($cont) ? '<table class="sl_table_list"><thead><tr><th>'.cutstr(_IMG, 4, 1).'</th><th>'._FILE.'</th><th>'._DATE.'</th><th>'._SIZE.'</th><th>'._WIDTH.' x '._HEIGHT.'</th><th>'._FUNCTIONS.'</th></tr></thead><tbody>'.$cont.'</tbody></table>'.$contnum : '';
    } else {
        $content = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $content;
}

# Format comments access
function com_access(string $name, int $selected, string $extraClass = ''): string {
    $class = $extraClass ? ' class="'.$extraClass.'"' : '';
    $cont  = '<select name="'.$name.'"'.$class.'>';
    $mods  = [_DEACTIVATE, _APOSTMOD, _APOSTNOMOD];
    for ($i = 0; $i < count($mods); $i++) {
        $sel   = ($selected == $i) ? ' selected' : '';
        $cont .= '<option value="'.$i.'"'.$sel.'>'.$mods[$i].'</option>';
    }
    $cont .= '</select>';
    return $cont;
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
    $cont   = '<select name="'.$selectName.'" class="'.$class.'"><option value="0">'._NO.'</option>';
    $result = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_voting WHERE '.$where.' ORDER BY id DESC', $params);
    if ($db->getSqlRowCount($result) > 0) {
        while (list($id, $title) = $db->getSqlRow($result)) {
            $sel   = ($selectedId == $id) ? ' selected' : '';
            $cont .= '<option value="'.$id.'"'.$sel.'>'.$title.'</option>';
        }
    }
    $cont .= '</select>';
    return $cont;
}

# Edit select list
function edit_list(string $modul, string $name, string $extraClass = ''): string {
    $modul = filterVar($modul);
    $class = $extraClass ? ' class="'.$extraClass.'"' : '';
    $cont  = '<select name="'.$name.'" title="'._CHECKOP.'"'.$class.'>';
    $cont .= '<optgroup label="'._OPMOD.'" class="sl_label">';
    $mass = [_ACTIVATE => 'a1', _DEACTIVATE => 'a0', _FIXED => 'f1', _LNFIX => 'f0', _LHOME => 'h1', _LNHOME => 'h0', _LADATE => 't', _DELETE => 'd'];
    foreach ($mass as $var_n => $var_v) $cont .= '<option value="'.$var_v.'">'.$var_n.'</option>';
    $cont .= '</optgroup><optgroup label="'._COMMENTS.'" class="sl_label">';
    $coms = [_DEACTIVATE => 'c0', _APOSTMOD => 'c1', _APOSTNOMOD => 'c2'];
    foreach ($coms as $var_n => $var_v) $cont .= '<option value="'.$var_v.'">'.$var_n.'</option>';
    $cont .= '</optgroup><optgroup label="'._MOVETO.'" class="sl_label">'.getcat($modul, 0, '', '', '', '1').'</optgroup>';
    $cont .= '</select>';
    return $cont;
}

# Renders the info/help page for the current admin module
function getAdminInfo(): string {
    global $locale, $conf;
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
            $fp = fopen($fpdir, 'wb');
            fwrite($fp, $content);
            fclose($fp);
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
    $cont .= setTemplateBasic('open');
    $cont .= filterReplaceText(filterMarkdown($thefile, 'info', false), 'info');
    if ($conf['adminfo']) {
        $cont .= '<hr><form name="post" id="formadm_info" method="post"><table class="sl_table_edit">'
        .'<tr><td>'.textarea('1', 'text', $thefile, 'info', '25').'</td></tr>'
        ."<tr><td class=\"sl_center\"><input type=\"submit\" OnClick=\"AjaxLoad('POST', '1', 'adm_info', 'go=5&amp;op=adm_info&amp;id=1&amp;type=".$type.'&amp;name='.$name."', { 'text':'"._CERROR1."' }); return false;\" value=\""._SAVECHANGES.'" title="'._SAVECHANGES.'" class="sl_but_blue"></td></tr>'
        .'</table></form>';
    }
    $cont .= setTemplateBasic('close');
    return $cont;
}
