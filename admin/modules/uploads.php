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
            $opts .= getTplOption($file, 'uploads/'.$file, $dir == $file);
        }
    }
    return getTplAdminSearchBox($tpl->getHtmlFrag('admin-uploads-search-form', [
        'dir_label' => _DIR.':',
        'route' => $afile,
        'select_html' => getTplSelect('dir', $opts, 'sl_form', 'OnChange="submit()"'),
    ]));
}

function uploads(): void {
    global $afile, $conf, $stop, $tpl;
    $dir = getVar('post', 'dir', 'var', '');
    if ($dir === '') $dir = getVar('get', 'dir', 'var', $conf['uploads']['dir']);
    $token = '&amp;token='.getSiteToken();
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=uploads', 'name=uploads&amp;op=tplconfig', 'name=uploads&amp;op=config', 'name=uploads&amp;op=info'],
        'tabs' => [_FILES, _TEMPLATES, _PREFERENCES, _INFO],
        'subtitle_html' => getUploadsSearch(),
    ]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => $stop]);
    $cont .= checkPerms(BASE_DIR.'/uploads/');
    $tabone = $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _MODUL.': '.getModuleName($dir).getTplAdminTipLine(_DIR, 'uploads/'.$dir)]);
    $uphide = getTplHiddenInput('name', 'uploads').getTplHiddenInput('op', 'uploadsave').getTplHiddenInput('dir', $dir);
    $uprows = $tpl->getHtmlFrag('admin-uploads-upload-rows', [
        'execute_label' => _EXECUTE,
        'filesite_label' => _FILE_SITE.':',
        'filesite_placeholder' => _FILE_SITE,
        'fileuser_label' => _FILE_USER.':',
    ]);
    $tabone .= getTplBox(getTplAdminForm($afile.'.php', $uprows, $uphide, 'sl_table_form', 'post', 'post', 'enctype="multipart/form-data"'));
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
        $tabtwo .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _MODUL.': '.getModuleName($dir).getTplAdminTipLine(_DIR, $fdir).getTplAdminTipLine(_FILE_M, (string)$f).getTplAdminTipLine(_FILE_S, filterSize($affilesize))]);
    } else {
        $tabtwo .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _NO_INFO]);
    }
    $tabtwo .= $tpl->getHtmlPart('box', ['box_id' => 'repf1']);
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
        $tabthr .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _MODUL.': '.getModuleName($dir).getTplAdminTipLine(_DIR, $tdir).getTplAdminTipLine(_FILE_M, (string)$t).getTplAdminTipLine(_FILE_S, filterSize($atfilesize))]);
    } else {
        $tabthr .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _NO_INFO]);
    }
    $tabthr .= $tpl->getHtmlPart('box', ['box_id' => 'repf2']);
    $tabs = [
        ['label' => _EUPLOAD, 'target' => 'uploads-panel-0', 'active' => true, 'link_attr' => ''],
        ['label' => _DGEN, 'target' => 'uploads-panel-1', 'active' => false, 'link_attr' => 'hx-get="index.php?go=5&amp;op=getAdminUploadFiles&amp;id=1&amp;dir='.$dir.$token.'" hx-target="#repf1" hx-swap="innerHTML" hx-push-url="false"'],
        ['label' => _DTHUMB, 'target' => 'uploads-panel-2', 'active' => false, 'link_attr' => 'hx-get="index.php?go=5&amp;op=getAdminUploadFiles&amp;id=2&amp;dir='.$dir.$token.'" hx-target="#repf2" hx-swap="innerHTML" hx-push-url="false"'],
    ];
    $tabsHtml = '';
    foreach ($tabs as $tab) {
        $tabsHtml .= $tpl->getHtmlFrag('new/tabs-link', [
            'href' => '#',
            'label' => $tab['label'],
            'rel' => $tab['target'],
            'is_active' => $tab['active'],
            'link_attr' => $tab['link_attr'],
        ]);
    }
    $uplv = $tpl->getHtmlFrag('new/tabs', [
        'id' => 'uploads-tabs',
        'is_runtime' => true,
        'tabs_html' => $tabsHtml,
        'content_html' =>
            $tpl->getHtmlFrag('new/tabs-panel', ['panel_id' => 'uploads-panel-0', 'content_html' => $tabone])
            .$tpl->getHtmlFrag('new/tabs-panel', ['panel_id' => 'uploads-panel-1', 'content_html' => $tabtwo])
            .$tpl->getHtmlFrag('new/tabs-panel', ['panel_id' => 'uploads-panel-2', 'content_html' => $tabthr]),
    ]);
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
    $cont = getTplAdminTabs([
        'ops' => ['name=uploads', 'name=uploads&amp;op=tplconfig', 'name=uploads&amp;op=config', 'name=uploads&amp;op=info'],
        'tabs' => [_FILES, _TEMPLATES, _PREFERENCES, _INFO],
        'tab' => 1,
        'subtitle_html' => getUploadsSearch(),
    ]);
    $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _TPINFO]);
    $cont .= checkPerms(CONFIG_DIR.'/filetype.php');
    $typm = explode(',', $conf['uploads']['typ']);
    $rows = [];
    for ($i = 0; $i < count($typm); $i++) {
        $rows[] = [
            'raw_html' => $tpl->getHtmlFrag('admin-uploads-tplconfig-block', [
                'editor_html' => textarea_code('code_'.$i.'', 'tmp[]', 'sl_form', 'text/html', $conf['filetype'][$typm[$i]] ?? ''),
                'show_hr' => $i > 0,
                'module_text' => $typm[$i],
                'tpfor_label' => _TPFOR.':',
            ]),
        ];
    }
    $tplv = $tpl->getHtmlFrag('new/form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'uploads'],
            ['nameattr' => 'op', 'valueattr' => 'tplsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'content_html' => $tpl->getHtmlFrag('config-div-content', ['rows' => $rows]),
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.getTplBox($tplv);
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
    $cont = getTplAdminTabs([
        'ops' => ['name=uploads', 'name=uploads&amp;op=tplconfig', 'name=uploads&amp;op=config', 'name=uploads&amp;op=info'],
        'tabs' => [_FILES, _TEMPLATES, _PREFERENCES, _INFO],
        'tab' => 2,
        'subtitle_html' => getUploadsSearch(),
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/uploads.php');
    $directory = '';
    foreach (scandir('uploads') as $file) {
        if (!preg_match('/\./', $file)) {
            $directory .= getTplOption($file, 'uploads/'.$file, $conf['uploads']['dir'] == $file);
        }
    }
    $rows = [
        ['label_html' => _DIRDEF.':', 'field_html' => getTplSelect('dir', $directory, 'sl_conf')],
        ['label_html' => getTplAdminHintLabel(_TPFORM.':', _TPFORMIN), 'field_html' => getTplTextarea('ttyp', $conf['uploads']['typ'], 'sl_conf', 'placeholder="'._TPFORM.'" required')],
        ['label_html' => _TPWIDTH.':', 'field_html' => getTplNumberInput('twidth', (string)$conf['uploads']['width'], 'sl_conf', 'placeholder="'._TPWIDTH.'" required')],
        ['label_html' => _TPHEIGHT.':', 'field_html' => getTplNumberInput('theight', (string)$conf['uploads']['height'], 'sl_conf', 'placeholder="'._TPHEIGHT.'" required')],
    ];
    $tabone = $tpl->getHtmlFrag('config-div-content', ['rows' => $rows]);
    $rows = [];
    $mods = ['all', 'account', 'album', 'auto_links', 'content', 'faq', 'files', 'forum', 'help', 'info', 'links', 'media', 'news', 'pages', 'shop', 'voting'];
    $i = 0;
    foreach ($mods as $val) {
        if ($val != '') {
            $con = explode('|', $conf['uploads'][$val]);
            $rows[] = [
                'raw_html' => $tpl->getHtmlFrag('admin-uploads-config-module-block', [
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
                ]),
            ];
            $i++;
        }
    }
    $tabtwo = $tpl->getHtmlFrag('config-div-content', ['rows' => $rows]);
    $tabsHtml = $tpl->getHtmlFrag('new/tabs-link', [
        'href' => '#',
        'label' => _GENPREF,
        'rel' => 'uploads-config-panel-0',
        'is_active' => true,
    ]).$tpl->getHtmlFrag('new/tabs-link', [
        'href' => '#',
        'label' => _MODULES,
        'rel' => 'uploads-config-panel-1',
        'is_active' => false,
    ]);
    $conts = $tpl->getHtmlFrag('new/tabs', [
        'id' => 'uploads-config-tabs',
        'is_runtime' => true,
        'tabs_html' => $tabsHtml,
        'content_html' =>
            $tpl->getHtmlFrag('new/tabs-panel', ['panel_id' => 'uploads-config-panel-0', 'content_html' => $tabone])
            .$tpl->getHtmlFrag('new/tabs-panel', ['panel_id' => 'uploads-config-panel-1', 'content_html' => $tabtwo]),
    ]);
    $confv = $tpl->getHtmlFrag('new/form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'uploads'],
            ['nameattr' => 'op', 'valueattr' => 'configsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'content_html' => $conts,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.getTplBox($confv);
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
    setTplAdminInfoPage([
        'ops' => ['name=uploads', 'name=uploads&amp;op=tplconfig', 'name=uploads&amp;op=config', 'name=uploads&amp;op=info'],
        'tabs' => [_FILES, _TEMPLATES, _PREFERENCES, _INFO],
        'subtitle_html' => getUploadsSearch(),
    ]);
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
