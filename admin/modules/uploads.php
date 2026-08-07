<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getUploadModuleList(): array {
    global $conf;
    $mods = array_keys(array_filter($conf['uploads'], static fn($v) => is_string($v) && str_contains($v, '|')));
    sort($mods);
    $rest = array_values(array_diff($mods, ['all']));
    return in_array('all', $mods, true) ? array_merge(['all'], $rest) : $rest;
}

function getUploadsSearch(): string {
    global $afile, $conf, $tpl;
    $dir = getVar('post', 'dir', 'var', $conf['uploads']['dir']);
    $opts = '';
    foreach (scandir(UPLOADS_DIR) as $file) {
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
    return $tpl->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => $form]);
}

function uploads(): void {
    global $afile, $conf, $stop, $tpl;
    $dir = getVar('post', 'dir', 'var', '');
    if ($dir === '') $dir = getVar('get', 'dir', 'var', $conf['uploads']['dir']);
    # The file panels are read through the shared go=5 endpoint, which validates the global ajax scope, so this one keeps that scope while the module forms use their own
    $token = '&token='.getSiteToken();
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=uploads', 'name=uploads&op=tplconfig', 'name=uploads&op=config', 'name=uploads&op=info'],
        'tabs' => [_FILES, _TEMPLATES, _PREFERENCES, _DOCS],
        'subtitle_html' => getUploadsSearch(),
    ]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
    $cont .= checkPerms(UPLOADS_DIR);
    $tabone = $tpl->getHtmlFrag('alert', [
        'is_warn' => false,
        'messages' => [
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
            ['nameattr' => 'token', 'valueattr' => getSiteToken('uploads')],
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
    $fdir = UPLOADS_DIR.'/'.$dir;
    $tabtwo = checkPerms($fdir);
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
        $tabtwo .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'messages' => [
            _MODUL.': '.getModuleName($dir),
            _DIR.': '.$fdir,
            _FILE_M.': '.$f,
            _FILE_S.': '.filterSize($affilesize),
        ]]);
    } else {
        $tabtwo .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    $tabtwo .= $tpl->getHtmlPart('box', ['box_id' => 'repf1']);
    $tdir = $fdir.'/thumb';
    $tabthr = checkPerms($tdir);
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
        $tabthr .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'messages' => [
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
        ['label' => _EUPLOAD, 'target' => 'uploads-panel-0', 'active' => true, 'hx_get' => '', 'hx_target' => ''],
        ['label' => _DGEN, 'target' => 'uploads-panel-1', 'active' => false, 'hx_get' => 'index.php?go=5&op=getAdminUploadFiles&id=1&dir='.$dir.$token, 'hx_target' => '#repf1'],
        ['label' => _DTHUMB, 'target' => 'uploads-panel-2', 'active' => false, 'hx_get' => 'index.php?go=5&op=getAdminUploadFiles&id=2&dir='.$dir.$token, 'hx_target' => '#repf2'],
    ];
    $tabsHtml = '';
    foreach ($tabs as $tab) {
        $tabsHtml .= $tpl->getHtmlFrag('tabs-link', [
            'href' => '#',
            'label' => $tab['label'],
            'rel' => $tab['target'],
            'is_active' => $tab['active'],
            'hx_get' => $tab['hx_get'],
            'hx_target' => $tab['hx_target'],
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
    $site = getVar('post', 'sitefile', 'raw', '');
    $site = is_string($site) ? trim($site) : '';
    $warn = !checkAdminPost('uploads');
    if (!$warn) {
        $rule = array_merge(getUploadRuleData('all'), ['maxquota' => 0]);
        $sent = (int)($_FILES['userfile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $res = ['ok' => false, 'error' => 'missing'];
        if ($sent) $res = getUploadService()->addUploadedFile($_FILES['userfile'], $rule, $dir, $dir, null);
        elseif ($site !== '') $res = getUploadService()->addRemoteFile($site, $rule, $dir, $dir, null);
        if (!$res['ok']) $stop = match ((string)$res['error']) {
            'size' => _ERROR_BIG,
            'extension', 'mime', 'image', 'unsupported' => _ERROR_FILE,
            'dimensions' => _ERROR_SIZE,
            'exists' => _ERROR_EXIST,
            'destination', 'write' => _ERROR_UP,
            default => _ERROR_DOWN,
        };
    }
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
        'ops' => ['name=uploads', 'name=uploads&op=tplconfig', 'name=uploads&op=config', 'name=uploads&op=info'],
        'tabs' => [_FILES, _TEMPLATES, _PREFERENCES, _DOCS],
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
            ['nameattr' => 'token', 'valueattr' => getSiteToken('uploads')],
        ],
        'content_html' => $blocks,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tplv]);
    setFoot();
}

function tplsave(): void {
    global $afile, $conf;
    $warn = !checkAdminPost('uploads');
    if (!$warn) {
        $cont = [];
        $typm = explode(',', $conf['uploads']['typ']);
        $tmp = getVar('post', 'tmp[]');
        for ($i = 0; $i < count($typm); $i++) $cont[$typm[$i]] = $tmp[$i] ?? '';
        setConfigFile('filetype.php', $cont);
    }
    setRedirect($afile.'.php?name=uploads&op=tplconfig', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=uploads', 'name=uploads&op=tplconfig', 'name=uploads&op=config', 'name=uploads&op=info'],
        'tabs' => [_FILES, _TEMPLATES, _PREFERENCES, _DOCS],
        'tab' => 2,
        'subtitle_html' => getUploadsSearch(),
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/uploads.php');
    $serv = getUploadService();
    $typs = implode(', ', $serv::getSupportedTypes());
    $flab = $tpl->getHtmlFrag('label-hint', ['label' => _FTYPE, 'hint' => $typs]);
    $directory = '';
    foreach (scandir(UPLOADS_DIR) as $file) {
        if (preg_match('/\./', $file)) continue;
        $directory .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $file,
            'label_text' => 'uploads/'.$file,
            'is_selected' => $conf['uploads']['dir'] == $file,
        ]);
    }
    $tlab = $tpl->getHtmlFrag('label-hint', ['label' => _TPFORM, 'hint' => _TPFORMIN.' '.$typs]);
    $tarea = $tpl->getHtmlFrag('textarea', ['name_attr' => 'ttyp', 'is_config' => true, 'is_required' => true, 'value_text' => $conf['uploads']['typ']]);
    $rows = [
        ['label_html' => _DIRDEF, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'dir', 'is_config' => true, 'options_html' => $directory])],
        ['label_html' => $tlab, 'field_html' => $tarea],
        ['label_html' => _TPWIDTH, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'twidth', 'is_config' => true, 'is_required' => true, 'value_attr' => (string)$conf['uploads']['width']])],
        ['label_html' => _TPHEIGHT, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'theight', 'is_config' => true, 'is_required' => true, 'value_attr' => (string)$conf['uploads']['height']])],
    ];
    $tabone = $tpl->getHtmlPart('div', ['rows' => $rows]);
    $blocks = '';
    $mods = getUploadModuleList();
    $i = 0;
    foreach ($mods as $val) {
        if ($val != '') {
            $rul = getUploadRuleData($val);
            $tfld = $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'type[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['extensions']]);
            $mrows = [
                ['label_html' => _MODUL, 'field_html' => getModuleName($val)],
                ['label_html' => $flab, 'field_html' => $tfld],
                ['label_html' => _FSIZEALL._FIN, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'allsize[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['maxquota']])],
                ['label_html' => _FSIZE._FIN, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'size[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['maxbytes']])],
                ['label_html' => _AWIDTH._AIN, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'width[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['maxwidth']])],
                ['label_html' => _AHEIGHT._AIN, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'height[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['maxheight']])],
                ['label_html' => _FILEUP, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'up[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['maxfiles']])],
                ['label_html' => _GDWIDTH, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'gdwidth[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['thumbwidth']])],
                ['label_html' => _F_5, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['adminlist']])],
                ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _EDFILEA, 'hint' => _CONFINES]), 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'asum[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['moderfiles']])],
                ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _EDFILEU, 'hint' => _CONFINES]), 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'usum[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['userfiles']])],
                ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _EDFILEG, 'hint' => _CONFINES]), 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'gsum[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['guestfiles']])],
                ['label_html' => _F_8, 'field_html' => getTplRadioGroup(['name' => $i.'upload', 'value' => $rul['userupload'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
                ['label_html' => _F_9, 'field_html' => getTplRadioGroup(['name' => $i.'upguest', 'value' => $rul['guestupload'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
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
            ['nameattr' => 'token', 'valueattr' => getSiteToken('uploads')],
        ],
        'content_html' => $conts,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $confv]);
    setFoot();
}

function configsave(): void {
    global $afile;
    $warn = !checkAdminPost('uploads');
    $drop = [];
    if (!$warn) {
    $serv = getUploadService();
    $known = $serv::getSupportedTypes();
    $filter = static function (string $list, string $back) use ($known, &$drop): string {
        $keep = [];
        foreach (explode(',', $list) as $ext) {
            if ($ext === '') continue;
            if (in_array($ext, $known, true)) $keep[] = $ext;
            elseif (!in_array($ext, $drop, true)) $drop[] = $ext;
        }
        return $keep ? implode(',', $keep) : $back;
    };
    $protect = ["\n" => '', "\t" => '', "\r" => '', ' ' => ''];
    $ttyp = getVar('post', 'ttyp', 'text');
    $xttyp = $filter($ttyp ? strtolower(strtr($ttyp, $protect)) : '', 'gif,jpg,jpeg,png');
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
    $mods = getUploadModuleList();
    $type = getVar('post', 'type[]');
    $allsize = getVar('post', 'allsize[]');
    $size = getVar('post', 'size[]');
    $width = getVar('post', 'width[]');
    $height = getVar('post', 'height[]');
    $up = getVar('post', 'up[]');
    $gdwidth = getVar('post', 'gdwidth[]');
    $num = getVar('post', 'num[]');
    $asum = getVar('post', 'asum[]');
    $usum = getVar('post', 'usum[]');
    $gsum = getVar('post', 'gsum[]');
    $i = 0;
    foreach ($mods as $val) {
        if ($val != '') {
            $xtype = $filter((empty($type[$i]) || !is_string($type[$i])) ? '' : strtolower(strtr($type[$i], $protect)), 'gif,jpg,jpeg,png,zip,rar');
            $xallsize = (!intval($allsize[$i] ?? 0)) ? 104857600 : intval($allsize[$i]);
            $xsize = (!intval($size[$i] ?? 0)) ? 1048576 : intval($size[$i]);
            $xwidth = (!intval($width[$i] ?? 0)) ? 500 : intval($width[$i]);
            $xheight = (!intval($height[$i] ?? 0)) ? 500 : intval($height[$i]);
            $xup = (!intval($up[$i] ?? 0)) ? 10 : intval($up[$i]);
            $xgdwidth = (!intval($gdwidth[$i] ?? 0)) ? 150 : intval($gdwidth[$i]);
            $xnum = (!intval($num[$i] ?? 0)) ? 10 : intval($num[$i]);
            $xasum = (!intval($asum[$i] ?? 0)) ? 250 : intval($asum[$i]);
            $xusum = (!intval($usum[$i] ?? 0)) ? 100 : intval($usum[$i]);
            $xgsum = (!intval($gsum[$i] ?? 0)) ? $xusum : intval($gsum[$i]);
            $upload = getVar('post', $i.'upload', 'num');
            $upguest = getVar('post', $i.'upguest', 'num');
            $cont[$val] = setUploadRuleData([
                'extensions' => $xtype,
                'maxquota' => $xallsize,
                'maxbytes' => $xsize,
                'maxwidth' => $xwidth,
                'maxheight' => $xheight,
                'maxfiles' => $xup,
                'thumbwidth' => $xgdwidth,
                'adminlist' => $xnum,
                'moderfiles' => $xasum,
                'userfiles' => $xusum,
                'userupload' => $upload,
                'guestupload' => $upguest,
                'guestfiles' => $xgsum,
            ]);
            $i++;
        }
    }
    setConfigFile('uploads.php', $cont);
    }
    $done = $drop ? _SUCCSAVE.' '._ERROR_FILE.': '.implode(', ', $drop) : _SUCCSAVE;
    setRedirect($afile.'.php?name=uploads&op=config', false, 302, $warn ? _TOKENMISS : $done, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=uploads', 'name=uploads&op=tplconfig', 'name=uploads&op=config', 'name=uploads&op=info'],
        'tabs' => [_FILES, _TEMPLATES, _PREFERENCES, _DOCS],
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
