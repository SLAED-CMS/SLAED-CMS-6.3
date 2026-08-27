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

function getUploadsSearch(string $mod): string {
    global $afile, $tpl;
    $opts = '';
    foreach (scandir(UPLOADS_DIR) as $file) {
        if (preg_match('/\./', $file)) continue;
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $file,
            'label_text' => 'uploads/'.$file,
            'is_selected' => $mod === $file,
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
    global $conf, $stop, $tpl;
    getAdminFileMode('uploads');
    $walk = getAdminFilePath('dir');
    if ($walk === '') $walk = getAdminFilePath('dir', 'post');
    $dir = ($walk === '') ? $conf['uploads']['dir'] : explode('/', $walk)[0];
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=uploads', 'name=uploads&op=sysfiles', 'name=uploads&op=tplconfig', 'name=uploads&op=config', 'name=uploads&op=info'],
        'tabs' => [_HOME, _UPLOADS_SYSTEM, _TEMPLATES, _PREFERENCES, _DOCS],
        'subtitle_html' => getUploadsSearch($dir),
    ]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
    echo $cont.checkPerms(UPLOADS_DIR).getAdminFileShell(true);
    setFoot();
}

function fmupload(): void {
    global $admin, $afile;
    getAdminFileMode('uploads');
    $dir = getAdminFilePath('dir', 'post');
    $site = getVar('post', 'sitefile', 'raw', '');
    $site = is_string($site) ? trim($site) : '';
    $json = getVar('post', 'ajax', 'num', 0) === 1;
    $back = $afile.'.php?name=uploads'.(($dir === '') ? '' : '&dir='.rawurlencode($dir));
    $rule = getAdminUploadRule($dir);
    $mod = ($dir === '') ? '' : explode('/', $dir)[0];
    $errs = array_filter((array)($_FILES['userfile']['error'] ?? []), static fn($v): bool => (int)$v !== UPLOAD_ERR_NO_FILE);
    $pass = checkAdminPost('uploads');
    unset($_POST['token']);
    if (!$pass && $json) getEditorJson(['ok' => false, 'error' => _TOKENMISS]);
    if (!$pass) setRedirect($back, false, 302, _TOKENMISS, true);
    $okay = $rule['ok'] && getAdminFileManager()->checkFileAccess($dir, 'write');
    $sent = ($okay && $errs !== []) ? getUploadService()->addUploadedFiles($_FILES['userfile'], $rule, $dir, $mod, null) : [];
    if ($sent === [] && $site !== '' && $okay) $sent = [getUploadService()->addRemoteFile($site, $rule, $dir, $mod, null)];
    if ($sent === []) $sent = [['ok' => false, 'error' => $okay ? 'missing' : 'destination', 'file' => '']];
    $done = 0;
    $note = '';
    foreach ($sent as $res) {
        if ($res['ok']) $done++;
        elseif ($note === '') $note = getUploadFailText((string)$res['error'], $rule);
        Logger::addFile($res['ok'] ? 'notice' : 'warning', 'Upload file operation', [
            'admin' => substr((string)($admin[1] ?? ''), 0, 25),
            'ctx' => getAdminFileMode(),
            'op' => 'fmupload',
            'path' => $dir,
            'target' => (string)($res['file'] ?? ''),
            'result' => $res['ok'] ? 'ok' : (string)$res['error'],
        ]);
    }
    if ($json) getEditorJson(['ok' => $done > 0, 'done' => $done, 'error' => ($done > 0) ? '' : $note]);
    setRedirect($back, false, 302, ($done > 0) ? _SUCCUPLOAD : $note, $done < 1);
}

function sysfiles(): void {
    getAdminFileMode('system');
    setHead();
    echo getTplAdminTabs([
        'ops' => ['name=uploads', 'name=uploads&op=sysfiles', 'name=uploads&op=tplconfig', 'name=uploads&op=config', 'name=uploads&op=info'],
        'tabs' => [_HOME, _UPLOADS_SYSTEM, _TEMPLATES, _PREFERENCES, _DOCS],
        'tab' => 1,
    ]).getAdminFileShell(true);
    setFoot();
}

function fmedit(): void {
    global $afile;
    getAdminFileMode('system');
    $file = getAdminFilePath('file');
    $body = getAdminFileManager()->getFileBody($file);
    if ($body === []) setRedirect($afile.'.php?name=uploads&op=sysfiles', false, 302, _UPLOADS_NOEDIT, true);
    setHead();
    echo getTplAdminTabs([
        'ops' => ['name=uploads', 'name=uploads&op=sysfiles', 'name=uploads&op=tplconfig', 'name=uploads&op=config', 'name=uploads&op=info'],
        'tabs' => [_HOME, _UPLOADS_SYSTEM, _TEMPLATES, _PREFERENCES, _DOCS],
        'tab' => 1,
    ]).getAdminFileShell(true, ['path' => $file, 'text' => $body['text'], 'version' => $body['version']]);
    setFoot();
}

function fmsave(): void {
    global $admin, $afile;
    getAdminFileMode('system');
    $file = getAdminFilePath('file', 'post');
    $text = getVar('post', 'text', 'raw', '');
    $text = is_string($text) ? $text : '';
    $ver = getVar('post', 'ver', 'word', '');
    $pass = checkAdminPost('uploads');
    unset($_POST['text'], $_POST['token']);
    if (!$pass) setRedirect($afile.'.php?name=uploads&op=sysfiles', false, 302, _TOKENMISS, true);
    $res = getAdminFileManager()->setFileBody($file, $text, $ver);
    Logger::addFile($res['ok'] ? 'notice' : 'warning', 'System file save', [
        'admin' => substr((string)($admin[1] ?? ''), 0, 25),
        'op' => 'fmsave',
        'path' => $file,
        'target' => $file,
        'result' => $res['ok'] ? 'ok' : $res['error'],
    ]);
    if ($res['ok']) setRedirect($afile.'.php?name=uploads&op=fmedit&file='.rawurlencode($file), false, 302, _SUCCSAVE, false);
    if ($res['error'] === 'closed' || $res['error'] === 'read') setRedirect($afile.'.php?name=uploads&op=sysfiles', false, 302, _ACCESSDENIED, true);
    $stale = $res['error'] === 'conflict';
    if ($stale) http_response_code(409);
    setHead();
    echo getTplAdminTabs([
        'ops' => ['name=uploads', 'name=uploads&op=sysfiles', 'name=uploads&op=tplconfig', 'name=uploads&op=config', 'name=uploads&op=info'],
        'tabs' => [_HOME, _UPLOADS_SYSTEM, _TEMPLATES, _PREFERENCES, _DOCS],
        'tab' => 1,
    ]).getAdminFileShell(true, [
        'path' => $file,
        'text' => $text,
        'version' => $stale ? $res['version'] : $ver,
        'note' => $stale ? _UPLOADS_STALE : (($res['error'] === 'body') ? _ERROR_FILE : _ERROR_UP),
    ]);
    setFoot();
}

function getFileNote(array $res, string $op): string {
    return match ($res['error']) {
        '' => ($op === 'fmdelete') ? _SUCCDELETE : _SUCCSAVE,
        'exists' => _UPLOADS_TAKEN,
        'filled' => _UPLOADS_FILLED,
        'loop' => _UPLOADS_BADDEST,
        'closed' => _ACCESSDENIED,
        default => _ERROR_UP,
    };
}

function setFileAction(string $op): void {
    global $admin, $afile;
    $ctx = getAdminFileMode();
    $man = getAdminFileManager();
    $file = getAdminFilePath('file', 'post');
    $arg = getAdminFilePath('arg', 'post');
    $dir = getAdminFilePath('back', 'post');
    $mark = getVar('post', 'mark[]', 'raw', []);
    $mark = is_array($mark) ? array_values(array_filter($mark, 'is_string')) : [];
    $page = ($ctx === 'uploads') ? '' : '&op=sysfiles';
    $back = $afile.'.php?name=uploads'.$page.(($dir === '') ? '' : '&dir='.rawurlencode($dir));
    $pass = checkAdminPost('uploads');
    unset($_POST['token']);
    if (!$pass) setRedirect($back, false, 302, _TOKENMISS, true);
    $dest = ($dir === '') ? $arg : $dir.'/'.$arg;
    if ($mark !== []) {
        setFileActions($op, $mark, ($op === 'fmpack') ? $dest : $arg, $back);
        return;
    }
    $res = match ($op) {
        'fmcreate' => $man->addFileEntry($dest),
        'fmmkdir' => $man->addDirectory($dest),
        'fmrename' => $man->updateFileName($file, $arg),
        'fmcopy' => $man->addFileCopy($file, $arg),
        'fmmove' => $man->updateFilePath($file, $arg),
        'fmdelete' => $man->deleteFileEntry($file),
        'fmpack' => $man->addFilesArchive([$file], $dest),
        default => $man->addFileArchive($file),
    };
    Logger::addFile($res['ok'] ? 'notice' : 'warning', 'File manager operation', [
        'admin' => substr((string)($admin[1] ?? ''), 0, 25),
        'ctx' => $ctx,
        'op' => $op,
        'path' => ($file === '') ? $dest : $file,
        'target' => ($res['path'] === '') ? $arg : $res['path'],
        'result' => $res['ok'] ? 'ok' : $res['error'],
    ]);
    $note = getFileNote($res, $op);
    setRedirect($back, false, 302, $note, !$res['ok']);
}

function setFileActions(string $op, array $mark, string $arg, string $back): void {
    global $admin;
    $ctx = getAdminFileMode();
    $man = getAdminFileManager();
    $done = 0;
    $fail = [];
    $runs = ($op === 'fmpack') ? [$man->addFilesArchive($mark, $arg)] : [];
    foreach (($op === 'fmpack') ? [] : $mark as $path) {
        $runs[] = match ($op) {
            'fmmove' => $man->updateFilePath($path, ($arg === '') ? basename($path) : $arg.'/'.basename($path)),
            'fmdelete' => $man->deleteFileEntry($path),
            'fmcompress' => $man->addFileArchive($path),
            default => ['ok' => false, 'error' => 'closed', 'path' => ''],
        };
    }
    foreach ($runs as $i => $res) {
        if ($res['ok']) $done++;
        elseif (!in_array($res['error'], $fail, true)) $fail[] = $res['error'];
        Logger::addFile($res['ok'] ? 'notice' : 'warning', 'File manager operation', [
            'admin' => substr((string)($admin[1] ?? ''), 0, 25),
            'ctx' => $ctx,
            'op' => $op,
            'path' => ($op === 'fmpack') ? implode(', ', $mark) : (string)($mark[$i] ?? ''),
            'target' => ($res['path'] === '') ? $arg : $res['path'],
            'result' => $res['ok'] ? 'ok' : $res['error'],
        ]);
    }
    $note = ($done === count($runs)) ? getFileNote(['error' => ''], $op) : getFileNote(['error' => $fail[0] ?? 'write'], $op);
    setRedirect($back, false, 302, $note.' '.$done.'/'.count($runs), $done < count($runs));
}

function fmcreate(): void {
    setFileAction('fmcreate');
}

function fmmkdir(): void {
    setFileAction('fmmkdir');
}

function fmrename(): void {
    setFileAction('fmrename');
}

function fmcopy(): void {
    setFileAction('fmcopy');
}

function fmmove(): void {
    setFileAction('fmmove');
}

function fmdelete(): void {
    setFileAction('fmdelete');
}

function fmcompress(): void {
    setFileAction('fmcompress');
}

function fmpack(): void {
    setFileAction('fmpack');
}

function tplconfig(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=uploads', 'name=uploads&op=sysfiles', 'name=uploads&op=tplconfig', 'name=uploads&op=config', 'name=uploads&op=info'],
        'tabs' => [_HOME, _UPLOADS_SYSTEM, _TEMPLATES, _PREFERENCES, _DOCS],
        'tab' => 2,
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
        'ops' => ['name=uploads', 'name=uploads&op=sysfiles', 'name=uploads&op=tplconfig', 'name=uploads&op=config', 'name=uploads&op=info'],
        'tabs' => [_HOME, _UPLOADS_SYSTEM, _TEMPLATES, _PREFERENCES, _DOCS],
        'tab' => 3,
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/uploads.php');
    $serv = getUploadService();
    $typs = implode(', ', $serv::getSupportedTypes());
    $directory = '';
    foreach (scandir(UPLOADS_DIR) as $file) {
        if (preg_match('/\./', $file)) continue;
        $directory .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $file,
            'label_text' => 'uploads/'.$file,
            'is_selected' => $conf['uploads']['dir'] == $file,
        ]);
    }
    $tarea = $tpl->getHtmlFrag('textarea', ['name_attr' => 'ttyp', 'input_id' => 'f-ttyp', 'describedby' => 'f-ttyp-hint', 'is_config' => true, 'is_required' => true, 'value_text' => $conf['uploads']['typ']]);
    $rows = [
        ['label_for' => 'f-dir', 'label_html' => _DIRDEF, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'dir', 'selectid' => 'f-dir', 'is_config' => true, 'options_html' => $directory])],
        ['label_for' => 'f-ttyp', 'label_html' => _TPFORM, 'hint_html' => _TPFORMIN.' '.$typs, 'hint_id' => getFieldIds('f-ttyp')['hint'], 'field_html' => $tarea],
        ['label_for' => 'f-twidth', 'label_html' => _TPWIDTH, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'twidth', 'input_id' => 'f-twidth', 'is_config' => true, 'is_required' => true, 'value_attr' => (string)$conf['uploads']['width']])],
        ['label_for' => 'f-theight', 'label_html' => _TPHEIGHT, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'theight', 'input_id' => 'f-theight', 'is_config' => true, 'is_required' => true, 'value_attr' => (string)$conf['uploads']['height']])],
    ];
    $tabone = $tpl->getHtmlPart('div', ['rows' => $rows]);
    $blocks = '';
    $mods = getUploadModuleList();
    $i = 0;
    foreach ($mods as $val) {
        if ($val != '') {
            $rul = getUploadRuleData($val);
            $fids = getFieldIds('', 'ftype');
            $tfld = $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'type[]', 'input_id' => $fids['input'], 'describedby' => $fids['hint'], 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['extensions']]);
            $mrows = [
                ['label_html' => _MODUL, 'field_html' => getModuleName($val)],
                ['label_for' => $fids['input'], 'label_html' => _FTYPE, 'hint_html' => $typs, 'hint_id' => $fids['hint'], 'field_html' => $tfld],
                ['label_html' => _FSIZEALL._FIN, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'allsize[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['maxquota']])],
                ['label_html' => _FSIZE._FIN, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'size[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['maxbytes']])],
                ['label_html' => _AWIDTH._AIN, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'width[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['maxwidth']])],
                ['label_html' => _AHEIGHT._AIN, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'height[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['maxheight']])],
                ['label_html' => _FILEUP, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'up[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['maxfiles']])],
                ['label_html' => _GDWIDTH, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'gdwidth[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['thumbwidth']])],
                ['label_html' => _EDFILEA, 'hint_html' => _CONFINES, 'hint_id' => $hntid = getFieldIds('', 'asum[]')['hint'], 'field_html' => $tpl->getHtmlFrag('input', ['describedby' => $hntid, 'itype' => 'number', 'name_attr' => 'asum[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['moderfiles']])],
                ['label_html' => _EDFILEU, 'hint_html' => _CONFINES, 'hint_id' => $hntid = getFieldIds('', 'usum[]')['hint'], 'field_html' => $tpl->getHtmlFrag('input', ['describedby' => $hntid, 'itype' => 'number', 'name_attr' => 'usum[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['userfiles']])],
                ['label_html' => _EDFILEG, 'hint_html' => _CONFINES, 'hint_id' => $hntid = getFieldIds('', 'gsum[]')['hint'], 'field_html' => $tpl->getHtmlFrag('input', ['describedby' => $hntid, 'itype' => 'number', 'name_attr' => 'gsum[]', 'is_config' => true, 'is_required' => true, 'value_attr' => $rul['guestfiles']])],
                ['label_html' => _F_8, 'label_id' => $labid = getFieldIds('', $i.'upload')['label'], 'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => $i.'upload', 'value' => $rul['userupload'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
                ['label_html' => _F_9, 'label_id' => $labid = getFieldIds('', $i.'upguest')['label'], 'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => $i.'upguest', 'value' => $rul['guestupload'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
            ];
            $blocks .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('div', ['rows' => $mrows])]);
            $i++;
        }
    }
    $confv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'uploads'],
            ['nameattr' => 'op', 'valueattr' => 'configsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('uploads')],
        ],
        'content_html' => $tpl->getHtmlPart('box', ['content_html' => $tabone]).$blocks,
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
        'ops' => ['name=uploads', 'name=uploads&op=sysfiles', 'name=uploads&op=tplconfig', 'name=uploads&op=config', 'name=uploads&op=info'],
        'tabs' => [_HOME, _UPLOADS_SYSTEM, _TEMPLATES, _PREFERENCES, _DOCS],
    ]);
}

switch ($op) {
    default: uploads(); break;
    case 'sysfiles': sysfiles(); break;
    case 'fmedit': fmedit(); break;
    case 'fmsave': fmsave(); break;
    case 'fmcreate': fmcreate(); break;
    case 'fmmkdir': fmmkdir(); break;
    case 'fmrename': fmrename(); break;
    case 'fmcopy': fmcopy(); break;
    case 'fmmove': fmmove(); break;
    case 'fmdelete': fmdelete(); break;
    case 'fmcompress': fmcompress(); break;
    case 'fmpack': fmpack(); break;
    case 'fmupload': fmupload(); break;
    case 'tplconfig': tplconfig(); break;
    case 'tplsave': tplsave(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
