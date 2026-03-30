<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getUploadsSearch(): string {
    global $afile, $conf, $tpl;
    $dir = getVar('post', 'dir', 'var', $conf['uploads']['dir']);
    $opts = '';
    foreach (scandir('uploads') as $file) {
        if (!preg_match('/\./', $file)) {
            $opts .= getAdminOption($file, 'uploads/'.$file, $dir == $file);
        }
    }
    return getAdminSearchBox($tpl->getHtmlFrag('admin-uploads-search-form', [
        'dir_label' => _DIR.':',
        'route' => $afile,
        'select_html' => getAdminSelect('dir', $opts, 'sl_form', 'OnChange="submit()"'),
    ]));
}

function uploads(): void {
    global $afile, $conf, $stop, $tpl;
    $dir = getVar('post', 'dir', 'var', '');
    if ($dir === '') $dir = getVar('get', 'dir', 'var', $conf['uploads']['dir']);
    $token = getSiteToken();
    $sattrs = [
        ' hx-get="index.php?go=5&amp;op=getAdminUploadFiles&amp;id=1&amp;dir='.$dir.'&amp;token='.$token.'" hx-target="#repf1" hx-swap="innerHTML" hx-push-url="false"',
        ' hx-get="index.php?go=5&amp;op=getAdminUploadFiles&amp;id=2&amp;dir='.$dir.'&amp;token='.$token.'" hx-target="#repf2" hx-swap="innerHTML" hx-push-url="false"',
        '',
    ];
    setHead();
    $cont = setAdminNavi(['ops' => ['name=uploads', 'name=uploads&amp;op=tplconfig', 'name=uploads&amp;op=config', 'name=uploads&amp;op=info'], 'tabs' => [_FILES, _TEMPLATES, _PREFERENCES, _INFO], 'sops' => ['', '', ''], 'stabs' => [
        _EUPLOAD,
        _DGEN,
        _DTHUMB,
    ], 'sattrs' => $sattrs, 'subtab' => 1, 'sub' => getUploadsSearch(), 'id' => 'uploads']);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => $stop]);
    $cont .= checkPerms(BASE_DIR.'/uploads/');
    $tabone = $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _MODUL.': '.getModuleName($dir).'<br>'._DIR.': uploads/'.$dir]);
    $uphide = getAdminHidden('name', 'uploads').getAdminHidden('op', 'uploadsave').getAdminHidden('dir', $dir);
    $uprows = $tpl->getHtmlFrag('admin-uploads-upload-rows', [
        'execute_label' => _EXECUTE,
        'filesite_label' => _FILE_SITE.':',
        'filesite_placeholder' => _FILE_SITE,
        'fileuser_label' => _FILE_USER.':',
    ]);
    $tabone .= getAdminBox(getAdminForm($afile.'.php', $uprows, $uphide, 'sl_table_form', 'post', 'post', 'enctype="multipart/form-data"'));
    $fdir = 'uploads/'.$dir;
    $tabtwo = checkPerms(BASE_DIR.'/'.$fdir);
    if (is_dir($fdir)) {
        $f = 0;
        $affilesize = 0;
        foreach (scandir($fdir) as $file) {
            if ($file != '.' && $file != '..' && $file != 'index.html' && !is_dir($fdir.'/'.$file)) {
                $filesize = filesize($fdir.'/'.$file);
                $f++;
                $affilesize += $filesize;
            }
        }
        $tabtwo .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _MODUL.': '.getModuleName($dir).'<br>'._DIR.': '.$fdir.'<br>'._FILE_M.': '.$f.'<br>'._FILE_S.': '.filterSize($affilesize)]);
    } else {
        $tabtwo .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _NO_INFO]);
    }
    $tabtwo .= getAdminPlaceholderBox('repf1');
    $tdir = 'uploads/'.$dir.'/thumb';
    $tabthr = checkPerms(BASE_DIR.'/'.$tdir);
    if (is_dir($tdir)) {
        $t = 0;
        $atfilesize = 0;
        foreach (scandir($tdir) as $file) {
            if ($file != '.' && $file != '..' && $file != 'index.html' && !is_dir($tdir.'/'.$file)) {
                $filesize = filesize($tdir.'/'.$file);
                $t++;
                $atfilesize += $filesize;
            }
        }
        $tabthr .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _MODUL.': '.getModuleName($dir).'<br>'._DIR.': '.$tdir.'<br>'._FILE_M.': '.$t.'<br>'._FILE_S.': '.filterSize($atfilesize)]);
    } else {
        $tabthr .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _NO_INFO]);
    }
    $tabthr .= getAdminPlaceholderBox('repf2');
    $uplv = $tpl->getHtmlFrag('admin-uploads-tabs-content', [
        'tab_one_id' => getAdminTabName('uploads', 0, true),
        'tab_two_id' => getAdminTabName('uploads', 1, true),
        'tab_three_id' => getAdminTabName('uploads', 2, true),
        'tab_one_html' => $tabone,
        'tab_two_html' => $tabtwo,
        'tab_three_html' => $tabthr,
    ]);
    $uplv .= getAdminTabsSetup('uploadss');
    echo $cont.$uplv;
    setFoot();
}

function uploadsave(): void {
    global $afile, $stop;
    $dir = getVar('post', 'dir', 'var');
    upload(3, 'uploads/'.$dir, 'gif,jpg,jpeg,png,zip,rar', '104857600', $dir, '1600', '1600', '1');
    if ($stop) {
        uploads();
    } else {
        setRedirect($afile.'.php?name=uploads&dir='.$dir);
    }
}

function tplconfig(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=uploads', 'name=uploads&amp;op=tplconfig', 'name=uploads&amp;op=config', 'name=uploads&amp;op=info'], 'tabs' => [_FILES, _TEMPLATES, _PREFERENCES, _INFO], 'sops' => ['', '', ''], 'stabs' => [_EUPLOAD, _DGEN, _DTHUMB], 'tab' => 1, 'sub' => getUploadsSearch(), 'id' => 'tplconfig']);
    $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _TPINFO]);
    $cont .= checkPerms(CONFIG_DIR.'/filetype.php');
    $typm = explode(',', $conf['uploads']['typ']);
    $conts = '';
    for ($i = 0; $i < count($typm); $i++) {
        $conts .= $tpl->getHtmlFrag('admin-uploads-tplconfig-block', [
            'editor_html' => textarea_code('code_'.$i.'', 'tmp[]', 'sl_form', 'text/html', $conf['filetype'][$typm[$i]] ?? ''),
            'show_hr' => $i > 0,
            'module_text' => $typm[$i],
            'tpfor_label' => _TPFOR.':',
        ]);
    }
    $tplv = getAdminConfSave($conts, 'uploads', 'tplsave');
    echo $cont.getAdminBox($tplv);
    setFoot();
}

function tplsave(): void {
    global $afile, $conf;
    $cont = [];
    $typm = explode(',', $conf['uploads']['typ']);
    $tmp = getVar('post', 'tmp', 'raw');
    for ($i = 0; $i < count($typm); $i++) $cont[$typm[$i]] = $tmp[$i];
    setConfigFile('filetype.php', $cont);
    setRedirect($afile.'.php?name=uploads&op=tplconfig');
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=uploads', 'name=uploads&amp;op=tplconfig', 'name=uploads&amp;op=config', 'name=uploads&amp;op=info'], 'tabs' => [_FILES, _TEMPLATES, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_GENPREF, _MODULES], 'tab' => 2, 'subtab' => 1, 'sub' => getUploadsSearch(), 'id' => 'config']);
    $cont .= checkPerms(CONFIG_DIR.'/uploads.php');
    $directory = '';
    foreach (scandir('uploads') as $file) {
        if (!preg_match('/\./', $file)) {
            $directory .= getAdminOption($file, 'uploads/'.$file, $conf['uploads']['dir'] == $file);
        }
    }
    $tabone = $tpl->getHtmlFrag('admin-uploads-config-general-tab', [
        'dir_html' => getAdminSelect('dir', $directory, 'sl_conf'),
        'dir_label' => _DIRDEF.':',
        'theight_label' => _TPHEIGHT.':',
        'theight_value' => (string)$conf['uploads']['height'],
        'tpform_hint' => _TPFORMIN,
        'tpform_label' => _TPFORM.':',
        'tpform_placeholder' => _TPFORM,
        'tpform_value' => $conf['uploads']['typ'],
        'twidth_label' => _TPWIDTH.':',
        'twidth_value' => (string)$conf['uploads']['width'],
    ]);
    $tabtwo = '';
    $mods = ['all', 'account', 'album', 'auto_links', 'content', 'faq', 'files', 'forum', 'help', 'info', 'links', 'media', 'news', 'pages', 'shop', 'voting'];
    $i = 0;
    foreach ($mods as $val) {
        if ($val != '') {
            $con = explode('|', $conf['uploads'][$val]);
            $tabtwo .= $tpl->getHtmlFrag('admin-uploads-config-module-block', [
                'allsize_label' => _FSIZEALL._FIN.':',
                'allsize_value' => $con[1],
                'asum_hint' => _CONFINES,
                'asum_label' => _EDFILEA.':',
                'asum_value' => $con[8],
                'five_label' => _F_5.':',
                'gdwidth_label' => _GDWIDTH.':',
                'gdwidth_value' => $con[6],
                'height_label' => _AHEIGHT._AIN.':',
                'height_value' => $con[4],
                'show_hr' => $i > 0,
                'module_label' => _MODUL.':',
                'module_text' => getModuleName($val),
                'num_value' => $con[7],
                'size_label' => _FSIZE._FIN.':',
                'size_value' => $con[2],
                'type_label' => _FTYPE.':',
                'type_value' => $con[0],
                'up_label' => _FILEUP.':',
                'upload_html' => radio_form($con[10], $i.'upload'),
                'upload_label' => _F_8,
                'upguest_html' => radio_form($con[11], $i.'upguest'),
                'upguest_label' => _F_9,
                'up_value' => $con[5],
                'usum_hint' => _CONFINES,
                'usum_label' => _EDFILEU.':',
                'usum_value' => $con[9],
                'width_label' => _AWIDTH._AIN.':',
                'width_value' => $con[3],
            ]);
            $i++;
        }
    }
    $conts = $tpl->getHtmlFrag('admin-uploads-config-tabs', [
        'tab_one_id' => getAdminTabName('config', 0, true),
        'tab_two_id' => getAdminTabName('config', 1, true),
        'tab_one_html' => $tabone,
        'tab_two_html' => $tabtwo,
    ]);
    $conts .= getAdminTabsSetup('configs');
    $confv = getAdminConfSave($conts, 'uploads', 'configsave');
    echo $cont.getAdminBox($confv);
    setFoot();
}

function configsave(): void {
    global $afile;
    $protect = ["\n" => '', "\t" => '', "\r" => '', ' ' => ''];
    $ttyp = getVar('post', 'ttyp', 'text');
    $xttyp = (!$ttyp) ? 'gif,jpg,jpeg,png,bmp' : strtolower(strtr($ttyp, $protect));
    $twidth = getVar('post', 'twidth', 'num', 500);
    $xtwidth = (!$twidth) ? 500 : $twidth;
    $theight = getVar('post', 'theight', 'num', 500);
    $xtheight = (!$theight) ? 500 : $theight;
    $dir = getVar('post', 'dir', 'var');
    $cont = [];
    $cont['dir'] = $dir;
    $cont['typ'] = $xttyp;
    $cont['width'] = $xtwidth;
    $cont['height'] = $xtheight;
    $mods = ['all', 'account', 'album', 'auto_links', 'content', 'faq', 'files', 'forum', 'help', 'info', 'links', 'media', 'news', 'pages', 'shop', 'voting'];
    $type = getVar('post', 'type', 'raw');
    $allsize = getVar('post', 'allsize', 'raw');
    $size = getVar('post', 'size', 'raw');
    $width = getVar('post', 'width', 'raw');
    $height = getVar('post', 'height', 'raw');
    $up = getVar('post', 'up', 'raw');
    $gdwidth = getVar('post', 'gdwidth', 'raw');
    $num = getVar('post', 'num', 'raw');
    $asum = getVar('post', 'asum', 'raw');
    $usum = getVar('post', 'usum', 'raw');
    $i = 0;
    foreach ($mods as $val) {
        if ($val != '') {
            $xtype = (!$type[$i]) ? 'gif,jpg,jpeg,png,zip,rar' : strtolower(strtr($type[$i], $protect));
            $xallsize = (!intval($allsize[$i])) ? 104857600 : $allsize[$i];
            $xsize = (!intval($size[$i])) ? 1048576 : $size[$i];
            $xwidth = (!intval($width[$i])) ? 500 : $width[$i];
            $xheight = (!intval($height[$i])) ? 500 : $height[$i];
            $xup = (!intval($up[$i])) ? 10 : $up[$i];
            $xgdwidth = (!intval($gdwidth[$i])) ? 150 : $gdwidth[$i];
            $xnum = (!intval($num[$i])) ? 10 : $num[$i];
            $xasum = (!intval($asum[$i])) ? 250 : $asum[$i];
            $xusum = (!intval($usum[$i])) ? 100 : $usum[$i];
            $upload = getVar('post', $i.'upload', 'num');
            $upguest = getVar('post', $i.'upguest', 'num');
            $cont[$val] = $xtype.'|'.$xallsize.'|'.$xsize.'|'.$xwidth.'|'.$xheight.'|'.$xup.'|'.$xgdwidth.'|'.$xnum.'|'.$xasum.'|'.$xusum.'|'.$upload.'|'.$upguest;
            $i++;
        }
    }
    setConfigFile('uploads.php', $cont);
    setRedirect($afile.'.php?name=uploads&op=config');
}

function info(): void {
    $cont = setAdminNavi(['ops' => ['name=uploads', 'name=uploads&amp;op=tplconfig', 'name=uploads&amp;op=config', 'name=uploads&amp;op=info'], 'tabs' => [_FILES, _TEMPLATES, _PREFERENCES, _INFO], 'sops' => ['', '', ''], 'stabs' => [_EUPLOAD, _DGEN, _DTHUMB], 'tab' => 3, 'sub' => getUploadsSearch()]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: uploads(); break;
    case 'uploadsave': uploadsave(); break;
    case 'tplconfig': tplconfig(); break;
    case 'tplsave': tplsave(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
