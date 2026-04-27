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
        if (preg_match('/\./', $file)) continue;
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $file,
            'label_text' => 'uploads/'.$file,
            'is_selected' => $dir == $file,
        ]);
    }
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=uploads',
        'content_html' => _DIR.': '.$tpl->getHtmlFrag('select', [
            'name_attr' => 'dir',
            'options_html' => $opts,
            'select_attr' => ' onchange="submit()"',
        ]),
    ]);
    return $tpl->getHtmlPart('searchbox', ['searchbox' => $form]);
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
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
    $cont .= checkPerms(BASE_DIR.'/uploads/');
    $tabone = $tpl->getHtmlFrag('alert', [
        'is_warn' => false,
        'lines' => [
            _MODUL.': '.getModuleName($dir),
            _DIR.': uploads/'.$dir,
        ],
    ]);
    $tabone .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'form_attr' => 'enctype="multipart/form-data"',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'uploads'],
            ['nameattr' => 'op', 'valueattr' => 'uploadsave'],
            ['nameattr' => 'dir', 'valueattr' => $dir],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => [
            [
                'label_html' => _FILE_USER,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'itype' => 'file',
                    'name_attr' => 'userfile',
                    'value_attr' => '',
                ]),
            ],
            [
                'label_html' => _FILE_SITE,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'itype' => 'text',
                    'name_attr' => 'sitefile',
                    'placeholder_text' => _FILE_SITE,
                    'value_attr' => '',
                ]),
            ],
        ],
        'submit_label' => _EXECUTE,
    ])]);
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
        $tabtwo .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'lines' => [
            _MODUL.': '.getModuleName($dir),
            _DIR.': '.$fdir,
            _FILE_M.': '.$f,
            _FILE_S.': '.filterSize($affilesize),
        ]]);
    } else {
        $tabtwo .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
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
        $tabthr .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'lines' => [
            _MODUL.': '.getModuleName($dir),
            _DIR.': '.$tdir,
            _FILE_M.': '.$t,
            _FILE_S.': '.filterSize($atfilesize),
        ]]);
    } else {
        $tabthr .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    $tabthr .= $tpl->getHtmlPart('box', ['box_id' => 'repf2']);
    $tabs = [
        ['label' => _EUPLOAD, 'target' => 'uploads-panel-0', 'active' => true, 'link_attr' => ''],
        ['label' => _DGEN, 'target' => 'uploads-panel-1', 'active' => false, 'link_attr' => 'hx-get="index.php?go=5&amp;op=getAdminUploadFiles&amp;id=1&amp;dir='.$dir.$token.'" hx-target="#repf1" hx-swap="innerHTML" hx-push-url="false"'],
        ['label' => _DTHUMB, 'target' => 'uploads-panel-2', 'active' => false, 'link_attr' => 'hx-get="index.php?go=5&amp;op=getAdminUploadFiles&amp;id=2&amp;dir='.$dir.$token.'" hx-target="#repf2" hx-swap="innerHTML" hx-push-url="false"'],
    ];
    $tabsHtml = '';
    foreach ($tabs as $tab) {
        $tabsHtml .= $tpl->getHtmlFrag('tabs-link', [
            'href' => '#',
            'label' => $tab['label'],
            'rel' => $tab['target'],
            'is_active' => $tab['active'],
            'link_attr' => $tab['link_attr'],
        ]);
    }
    $uplv = $tpl->getHtmlPart('tabs', [
        'id' => 'uploads-tabs',
        'is_runtime' => true,
        'is_subtabs' => true,
        'tabs_html' => $tabsHtml,
        'content_html' =>
            $tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'uploads-panel-0', 'content_html' => $tabone])
            .$tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'uploads-panel-1', 'content_html' => $tabtwo])
            .$tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'uploads-panel-2', 'content_html' => $tabthr]),
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $uplv]);
    setFoot();
}

function uploadsave(): void {
    global $afile, $stop;
    $dir = getVar('post', 'dir', 'var');
    $warn = !checkSiteToken();
    if (!$warn) upload(3, 'uploads/'.$dir, 'gif,jpg,jpeg,png,zip,rar', '104857600', $dir, '1600', '1600', '1');
    if (!$warn && $stop) {
        uploads();
    } else {
        setRedirect($afile.'.php?name=uploads&dir='.$dir, false, 302, $warn ? _TOKENMISS : _SUCCUPLOAD, $warn);
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
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _TPINFO]);
    $cont .= checkPerms(CONFIG_DIR.'/filetype.php');
    $typm = explode(',', $conf['uploads']['typ']);
    $blocks = '';
    for ($i = 0; $i < count($typm); $i++) {
        $blocks .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('div', [
            'rows' => [[
                'label_html' => '',
                'field_html' => _TPFOR.': '.$typm[$i],
                'is_full' => true,
            ], [
                'label_html' => '',
                'field_html' => Editor::getCode([
                    'id' => 'code_'.$i,
                    'name' => 'tmp[]',
                    'lang' => 'html',
                    'text' => $conf['filetype'][$typm[$i]] ?? '',
                ]),
                'is_full' => true,
            ]],
        ])]);
    }
    $tplv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'uploads'],
            ['nameattr' => 'op', 'valueattr' => 'tplsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'content_html' => $blocks,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tplv]);
    setFoot();
}

function tplsave(): void {
    global $afile, $conf;
    $warn = !checkSiteToken();
    if (!$warn) {
        $cont = [];
        $typm = explode(',', $conf['uploads']['typ']);
        $tmp = getVar('post', 'tmp', 'raw');
        for ($i = 0; $i < count($typm); $i++) $cont[$typm[$i]] = $tmp[$i];
        setConfigFile('filetype.php', $cont);
    }
    setRedirect($afile.'.php?name=uploads&op=tplconfig', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
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
        if (preg_match('/\./', $file)) continue;
        $directory .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $file,
            'label_text' => 'uploads/'.$file,
            'is_selected' => $conf['uploads']['dir'] == $file,
        ]);
    }
    $rows = [
        ['label_html' => _DIRDEF, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'dir', 'is_config' => true, 'options_html' => $directory])],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _TPFORM, 'hint' => _TPFORMIN]), 'field_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'ttyp', 'is_config' => true, 'is_required' => true, 'value_text' => $conf['uploads']['typ']])],
        ['label_html' => _TPWIDTH, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'twidth', 'is_config' => true, 'is_required' => true, 'value_attr' => (string)$conf['uploads']['width']])],
        ['label_html' => _TPHEIGHT, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'theight', 'is_config' => true, 'is_required' => true, 'value_attr' => (string)$conf['uploads']['height']])],
    ];
    $tabone = $tpl->getHtmlPart('div', ['rows' => $rows]);
    $blocks = '';
    $mods = ['all', 'account', 'album', 'auto_links', 'content', 'faq', 'files', 'forum', 'help', 'info', 'links', 'media', 'news', 'pages', 'shop', 'voting'];
    $i = 0;
    foreach ($mods as $val) {
        if ($val != '') {
            $con = explode('|', $conf['uploads'][$val]);
            $mrows = [
                ['label_html' => _MODUL, 'field_html' => getModuleName($val)],
                ['label_html' => _FTYPE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'type[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $con[0]])],
                ['label_html' => _FSIZEALL._FIN, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'allsize[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $con[1]])],
                ['label_html' => _FSIZE._FIN, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'size[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $con[2]])],
                ['label_html' => _AWIDTH._AIN, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'width[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $con[3]])],
                ['label_html' => _AHEIGHT._AIN, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'height[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $con[4]])],
                ['label_html' => _FILEUP, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'up[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $con[5]])],
                ['label_html' => _GDWIDTH, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'gdwidth[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $con[6]])],
                ['label_html' => _F_5, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $con[7]])],
                ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _EDFILEA, 'hint' => _CONFINES]), 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'asum[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $con[8]])],
                ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _EDFILEU, 'hint' => _CONFINES]), 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'usum[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $con[9]])],
                ['label_html' => _F_8, 'field_html' => getTplRadioGroup(['name' => $i.'upload', 'value' => $con[10], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
                ['label_html' => _F_9, 'field_html' => getTplRadioGroup(['name' => $i.'upguest', 'value' => $con[11], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
            ];
            $blocks .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('div', ['rows' => $mrows])]);
            $i++;
        }
    }
    $tabtwo = $blocks;
    $tabsHtml = $tpl->getHtmlFrag('tabs-link', [
        'href' => '#',
        'label' => _GENPREF,
        'rel' => 'uploads-config-panel-0',
        'is_active' => true,
    ]).$tpl->getHtmlFrag('tabs-link', [
        'href' => '#',
        'label' => _MODULES,
        'rel' => 'uploads-config-panel-1',
        'is_active' => false,
    ]);
    $conts = $tpl->getHtmlPart('tabs', [
        'id' => 'uploads-config-tabs',
        'is_runtime' => true,
        'tabs_html' => $tabsHtml,
        'content_html' =>
            $tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'uploads-config-panel-0', 'content_html' => $tabone])
            .$tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'uploads-config-panel-1', 'content_html' => $tabtwo]),
    ]);
    $confv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'uploads'],
            ['nameattr' => 'op', 'valueattr' => 'configsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'content_html' => $conts,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $confv]);
    setFoot();
}

function configsave(): void {
    global $afile;
    $warn = !checkSiteToken();
    if (!$warn) {
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
    }
    setRedirect($afile.'.php?name=uploads&op=config', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
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
