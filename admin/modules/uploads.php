<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

require_once BASE_DIR.'/core/classes/update.php';

function getUploadsTabs(): array {
    return [
        'ops' => ['name=uploads', 'name=uploads&op=sysfiles', 'name=uploads&op=theme', 'name=uploads&op=tplconfig', 'name=uploads&op=config', 'name=uploads&op=info'],
        'tabs' => [_HOME, _SYSTEM, _THEME_TAB, _UPLOADS_TPLS, _PREFERENCES, _MANUAL],
    ];
}

function getUploadModuleList(): array {
    global $conf;
    $mods = array_keys(array_filter($conf['uploads'], static fn($v) => is_string($v) && str_contains($v, '|')));
    sort($mods);
    $rest = array_values(array_diff($mods, ['all']));
    return in_array('all', $mods, true) ? array_merge(['all'], $rest) : $rest;
}

# The folders of the upload root relative to it, and below the root of the Node types each type folder, as the screen offers them for browsing and as the default folder
function getUploadsFolders(): array {
    $out = [];
    foreach (scandir(UPLOADS_DIR) as $file) {
        if (preg_match('/\./', $file)) continue;
        $out[] = $file;
        if ($file !== basename(NODE_DIR)) continue;
        foreach (scandir(NODE_DIR) as $one) if (!preg_match('/\./', $one) && is_dir(NODE_DIR.'/'.$one)) $out[] = $file.'/'.$one;
    }
    return $out;
}

function getUploadsSearch(string $mod): string {
    global $afile, $tpl;
    $opts = [];
    foreach (getUploadsFolders() as $file) $opts[] = ['value_attr' => $file, 'label_text' => basename(UPLOADS_DIR).'/'.$file, 'is_selected' => $mod === $file];
    return $tpl->getHtmlFrag('list-filter', [
        'action_url' => $afile.'.php',
        'label' => _SEARCH,
        'hidden' => [['nameattr' => 'name', 'valueattr' => 'uploads']],
        'fields' => [['field_id' => 'sl-up-dir', 'label' => _DIR, 'name_attr' => 'dir', 'options' => $opts]],
    ]);
}

function uploads(): void {
    global $conf;
    getAdminFileMode('uploads');
    $walk = getAdminFilePath('dir');
    if ($walk === '') $walk = getAdminFilePath('dir', 'post');
    $part = explode('/', $walk);
    $dir = ($walk === '') ? getUploadFolder($conf['uploads']['dir']) : implode('/', array_slice($part, 0, ($part[0] === basename(NODE_DIR)) ? 2 : 1));
    setHead();
    $cont = getTplAdminTabs(getUploadsTabs() + ['subtitle_html' => getUploadsSearch($dir)]);
    echo $cont.checkPerms(UPLOADS_DIR).getAdminFileShell(true, ($walk === '') ? ['dir' => $dir] : []);
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
    $mod = $rule['mod'];
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

function getUploadsThemes(string $templ): string {
    global $afile, $tpl;
    $opts = [];
    foreach (scandir(PUBLIC_DIR.'/templates') as $file) {
        if ($file === '.' || $file === '..' || !is_dir(PUBLIC_DIR.'/templates/'.$file)) continue;
        $opts[] = ['value_attr' => $file, 'label_text' => $file, 'is_selected' => $file === $templ];
    }
    return $tpl->getHtmlFrag('list-filter', [
        'action_url' => $afile.'.php',
        'label' => _THEME,
        'hidden' => [['nameattr' => 'name', 'valueattr' => 'uploads'], ['nameattr' => 'op', 'valueattr' => 'theme']],
        'fields' => [['field_id' => 'sl-fm-theme', 'label' => _THEME, 'name_attr' => 'templ', 'options' => $opts]],
    ]);
}

# The head of a screen of the system or the theme area; a refused theme names its reasons and a shipped one warns that an update replaces the file being edited here
function getUploadsFileHead(): string {
    global $tpl;
    if (getAdminFileMode() !== 'theme') return getTplAdminTabs(getUploadsTabs() + ['tab' => 1]);
    $templ = getAdminFileTheme();
    $cont = getTplAdminTabs(getUploadsTabs() + ['tab' => 2, 'subtitle_html' => getUploadsThemes($templ)]);
    $list = [];
    foreach (getThemeFaults($templ) as $row) {
        $list[] = match ($row['code']) {
            'manifest' => _UPLOADS_THMAN,
            'field' => sprintf(_UPLOADS_THKEY, $row['name']),
            'api' => sprintf(_UPLOADS_THAPI, $row['name'], implode(', ', THEME_API)),
            default => sprintf(_UPLOADS_THFILE, $row['name']),
        };
    }
    if ($list) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _UPLOADS_THBAD, 'messages' => $list]);
    if (in_array($templ, Update::THEMES, true)) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _UPDATE_THEMES]);
    return $cont.checkPerms(PUBLIC_DIR.'/templates/'.$templ);
}

function getRobotsTemplate(): string {
    global $conf;
    $base = rtrim((string)($conf['homeurl'] ?? ''), '/');
    $rows = [
        'User-agent: *',
        '',
        'Disallow: /admin/',
        'Disallow: /blocks/',
        'Disallow: /config/',
        'Disallow: /core/',
        'Disallow: /lang/',
        'Disallow: /modules/',
        'Disallow: /setup.php',
        'Disallow: /storage/',
    ];
    $rows[] = '';
    $rows[] = 'Sitemap: '.($base ? $base.'/sitemap.xml' : '/sitemap.xml');
    return implode("\n", $rows);
}

# The button of the standard robots rules, in the foot of the editor of that one file
function getRobotsButton(string $file): string {
    global $tpl;
    if (getAdminFileMode() !== 'system' || $file !== getAdminFileRel(PUBLIC_DIR.'/robots.txt')) return '';
    return $tpl->getHtmlFrag('editor-robots-button', [
        'label' => _EROBSTD,
        'target_id' => 'slfmcode',
        'template_json' => json_encode(getRobotsTemplate(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function sysfiles(): void {
    getAdminFileMode('system');
    setHead();
    echo getUploadsFileHead().getAdminFileShell(true);
    setFoot();
}

function theme(): void {
    getAdminFileMode('theme');
    setHead();
    echo getUploadsFileHead().getAdminFileShell(true);
    setFoot();
}

function fmedit(): void {
    if (getAdminFileMode() === 'uploads') getAdminFileMode('system');
    $file = getAdminFilePath('file');
    $body = getAdminFileManager()->getFileBody($file);
    if ($body === []) setRedirect(getAdminFileBack(), false, 302, _UPLOADS_NOEDIT, true);
    setHead();
    echo getUploadsFileHead().getAdminFileShell(true, ['path' => $file, 'text' => $body['text'], 'version' => $body['version'], 'extra_html' => getRobotsButton($file)]);
    setFoot();
}

function fmsave(): void {
    global $admin, $afile;
    if (getAdminFileMode() === 'uploads') getAdminFileMode('system');
    $file = getAdminFilePath('file', 'post');
    $text = getVar('post', 'text', 'raw', '');
    $text = is_string($text) ? $text : '';
    $ver = getVar('post', 'ver', 'word', '');
    $pass = checkAdminPost('uploads');
    unset($_POST['text'], $_POST['token']);
    if (!$pass) setRedirect(getAdminFileBack(), false, 302, _TOKENMISS, true);
    $res = getAdminFileManager()->setFileBody($file, $text, $ver);
    Logger::addFile($res['ok'] ? 'notice' : 'warning', 'System file save', [
        'admin' => substr((string)($admin[1] ?? ''), 0, 25),
        'ctx' => getAdminFileMode(),
        'op' => 'fmsave',
        'path' => $file,
        'target' => $file,
        'result' => $res['ok'] ? 'ok' : $res['error'],
    ]);
    if ($res['ok']) setRedirect($afile.'.php?name=uploads&op=fmedit'.getAdminFileQuery().'&file='.rawurlencode($file), false, 302, _SUCCSAVE, false);
    if ($res['error'] === 'closed' || $res['error'] === 'read') setRedirect(getAdminFileBack(), false, 302, _ACCESSDENIED, true);
    $stale = $res['error'] === 'conflict';
    if ($stale) http_response_code(409);
    setHead();
    echo getUploadsFileHead().getAdminFileShell(true, [
        'extra_html' => getRobotsButton($file),
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
        'name' => _UPLOADS_BADNAME,
        'closed' => _ACCESSDENIED,
        default => _ERROR_UP,
    };
}

function setFileAction(string $op): void {
    global $admin;
    $ctx = getAdminFileMode();
    $man = getAdminFileManager();
    $file = getAdminFilePath('file', 'post');
    $arg = getAdminFilePath('arg', 'post');
    $dir = getAdminFilePath('back', 'post');
    $mark = getVar('post', 'mark[]', 'raw', []);
    $mark = is_array($mark) ? array_values(array_filter($mark, 'is_string')) : [];
    $back = getAdminFileBack($dir).((getVar('post', 'unused', 'num', 0) === 1) ? '&unused=1' : '');
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

# The values a file template is previewed with, as the parser fills them for one file: the bundled sample of the kind, its size, its name; a PDF is opened, never embedded
function getTemplateSample(string $type): array {
    global $conf, $theme;
    require_once BASE_DIR.'/core/classes/filemanager.php';
    $kind = ($type === 'full') ? 'image' : FileManager::getFileKind(false, $type);
    $file = ['image' => 'sample.webp', 'audio' => 'sample.wav', 'video' => 'sample.webm', 'document' => 'sample.pdf'][$kind] ?? '';
    $path = 'templates/'.$theme.'/assets/samples/'.$file;
    $url = ($file !== '') ? Template::getAssetUrl($path) : '#';
    $size = ($kind === 'image' && is_file(PUBLIC_DIR.'/'.$path)) ? getimagesize(PUBLIC_DIR.'/'.$path) : false;
    $wide = (string)($conf['uploads']['width'] ?? '500');
    return [
        'vals' => [
            '[src]' => $url,
            '[tsrc]' => $url,
            '[width]' => $size ? (string)$size[0] : $wide,
            '[twidth]' => $wide,
            '[height]' => $size ? (string)$size[1] : (string)($conf['uploads']['height'] ?? '500'),
            '[align]' => '',
            '[title]' => ($file !== '') ? $file : 'sample.'.$type,
            '[quot]' => '&quot;',
            '[rel]' => 'alternate',
        ],
        'open' => ($kind === 'document') ? $url : '',
    ];
}

function tplconfig(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(getUploadsTabs() + ['tab' => 3]);
    $vars = [
        '[src]' => _UPLOADS_SRC,
        '[tsrc]' => _UPLOADS_TSRC,
        '[width]' => _UPLOADS_WIDTH,
        '[twidth]' => _UPLOADS_TWIDTH,
        '[height]' => _UPLOADS_HEIGHT,
        '[theight]' => _UPLOADS_THEIGHT,
        '[align]' => _UPLOADS_ALIGN,
        '[title]' => _UPLOADS_TITLE,
        '[quot]' => _UPLOADS_QUOT,
        '[rel]' => _UPLOADS_REL,
    ];
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'messages' => array_map(fn($k, $v) => $k.' - '.$v, array_keys($vars), $vars)]);
    $cont .= checkPerms(CONFIG_DIR.'/filetype.php');
    $typm = [...explode(',', $conf['uploads']['typ']), 'full'];
    $blocks = '';
    require_once BASE_DIR.'/core/classes/filemanager.php';
    $names = ['image' => _UPLOADS_IMAGES, 'audio' => _UPLOADS_AUDIO, 'video' => _UPLOADS_VIDEO, 'document' => _UPLOADS_PAPERS, 'archive' => _UPLOADS_PACKS];
    foreach ($typm as $i => $type) {
        $form = ($type === 'full') ? _EDITOR_FULLSIZE.' (size=full)' : $type;
        $text = (string)($conf['filetype'][$type] ?? '');
        $kind = ($type === 'full') ? 'image' : FileManager::getFileKind(false, $type);
        $facts = [['text' => $names[$kind] ?? '']];
        foreach (explode(' · ', sprintf(_EDITOR_SIZE, ($text === '') ? 0 : substr_count($text, "\n") + 1, mb_strlen($text))) as $one) {
            $facts[] = ['text' => mb_strtoupper(mb_substr($one, 0, 1)).mb_substr($one, 1)];
        }
        $blocks .= $tpl->getHtmlPart('toggle-form-block', [
            'block_id' => 'upt-'.$type,
            'is_fold' => true,
            'title' => _DETAILS.': '.$form,
            'label_html' => htmlspecialchars($form, ENT_QUOTES, 'UTF-8'),
            'facts' => $facts,
            'content_html' => $tpl->getHtmlPart('div', ['rows' => [[
                'label_html' => '',
                'field_html' => Editor::getCode([
                    'id' => 'code_'.$i,
                    'name' => 'tmp[]',
                    'label' => _TPFOR.': '.$form,
                    'lang' => 'html',
                    'text' => $text,
                    'vars' => $vars,
                    'lint' => true,
                    'preview' => getTemplateSample($type),
                ]),
                'is_full' => true,
            ]]]),
        ]);
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
    $text = $warn ? _TOKENMISS : _SUCCSAVE;
    if (!$warn) {
        $cont = [];
        $typm = [...explode(',', $conf['uploads']['typ']), 'full'];
        $tmp = getVar('post', 'tmp[]');
        for ($i = 0; $i < count($typm); $i++) $cont[$typm[$i]] = $tmp[$i] ?? '';
        $warn = !setConfigFile('filetype.php', $cont);
        if ($warn) $text = getConfigJournal() ? _CONFIG_PENDING : _ERROR_UP;
    }
    setRedirect($afile.'.php?name=uploads&op=tplconfig', false, 302, $text, $warn);
}

function config(): void {
    global $afile, $conf, $tpl;
    require_once BASE_DIR.'/core/classes/filemanager.php';
    setHead();
    $cont = getTplAdminTabs(getUploadsTabs() + ['tab' => 4]);
    $cont .= checkPerms(CONFIG_DIR.'/uploads.php');
    $names = ['image' => _UPLOADS_IMAGES, 'audio' => _UPLOADS_AUDIO, 'video' => _UPLOADS_VIDEO, 'document' => _UPLOADS_PAPERS, 'archive' => _UPLOADS_PACKS];
    $kinds = array_fill_keys(array_keys($names), []);
    $exts = getUploadService()::getSupportedTypes();
    sort($exts);
    foreach ($exts as $ext) $kinds[FileManager::getFileKind(false, $ext)][] = $ext;
    $boxes = function (string $name, string $list, string $label) use ($tpl, $kinds, $names): array {
        $ids = getFieldIds('', $name);
        $have = explode(',', $list);
        $out = '';
        foreach ($names as $kind => $text) {
            $line = '';
            foreach ($kinds[$kind] as $ext) {
                $line .= $tpl->getHtmlFrag('checkbox', ['name_attr' => $name.'[]', 'value_attr' => $ext, 'label_text' => $ext, 'is_checked' => in_array($ext, $have, true)]);
            }
            $out .= $tpl->getHtmlFrag('span', ['label' => $text, 'content_html' => $line, 'is_line_break' => true]);
        }
        return ['label_html' => $label, 'label_id' => $ids['label'],
            'field_html' => $tpl->getHtmlFrag('block-content', ['is_radio_group' => true, 'switch' => false, 'labelledby' => $ids['label'], 'content' => $out])];
    };
    $limit = function (string $name, string $label, mixed $val, string $hint = '', string $attr = 'min="0"') use ($tpl): array {
        $ids = getFieldIds('', $name);
        $row = ['label_for' => $ids['input'], 'label_html' => $label];
        if ($hint !== '') $row += ['hint_html' => $hint, 'hint_id' => $ids['hint']];
        return $row + ['field_html' => $tpl->getHtmlFrag('input', ['describedby' => ($hint !== '') ? $ids['hint'] : '', 'itype' => 'number', 'input_id' => $ids['input'],
            'name_attr' => $name, 'is_config' => true, 'input_attr' => $attr, 'value_attr' => $val])];
    };
    $mega = static fn(int $size): string => rtrim(rtrim(number_format($size / 1048576, 2, '.', ''), '0'), '.');
    $dirs = [];
    foreach (getUploadsFolders() as $file) {
        if ($file === basename(NODE_DIR)) continue;
        $name = basename($file);
        $dirs[] = ['value_attr' => $name, 'label_text' => basename(UPLOADS_DIR).'/'.$file, 'is_selected' => $conf['uploads']['dir'] == $name];
    }
    $blocks = $tpl->getHtmlPart('toggle-form-block', ['block_id' => 'upb-catalog', 'label_html' => _UPLOADS_CATALOG, 'content_html' => $tpl->getHtmlPart('div', ['rows' => [[
        'label_for' => 'f-dir',
        'label_html' => _DIRDEF,
        'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'dir', 'selectid' => 'f-dir', 'is_config' => true, 'options' => $dirs]),
    ]]])]);
    $tform = $boxes('ttyp', $conf['uploads']['typ'], _TPFORM);
    $blocks .= $tpl->getHtmlPart('toggle-form-block', ['block_id' => 'upb-output', 'label_html' => _UPLOADS_OUTPUT, 'content_html' => $tpl->getHtmlPart('div', ['rows' => [
        $tform + ['hint_html' => sprintf(_TPFORMIN, _UPLOADS_TPLS)],
        $limit('twidth', _TPWIDTH, $conf['uploads']['width'], '', 'min="1"'),
        $limit('theight', _TPHEIGHT, $conf['uploads']['height'], '', 'min="1"'),
    ]])]);
    $mods = getUploadModuleList();
    $types = getNodeTypeMap();
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rooms = ['account' => _PRIVAT, 'layout' => _LAYOUT_BUILDER, 'profile' => _ACCOUNT];
    $radio = static fn(string $name, string $labid, int $val): string => getTplRadioGroup(['labelledby' => $labid, 'name' => $name, 'value' => $val, 'options' => $yesno]);
    foreach ($mods as $i => $val) {
        $rul = getUploadRuleData($val);
        $name = $rooms[$val] ?? getModuleTitle($val);
        $ulab = getFieldIds('', $i.'upload')['label'];
        $glab = getFieldIds('', $i.'upguest')['label'];
        $mrows = [
            ['label_html' => _F_8, 'label_id' => $ulab, 'field_html' => $radio($i.'upload', $ulab, $rul['userupload'])],
            ['label_html' => _F_9, 'label_id' => $glab, 'field_html' => $radio($i.'upguest', $glab, $rul['guestupload'])],
            $boxes($i.'ext', $rul['extensions'], _FTYPE),
            $limit('size[]', _FSIZE._FIN, $mega($rul['maxbytes']), '', 'min="0.01" step="0.01"'),
            $limit('width[]', _AWIDTH._AIN, $rul['maxwidth'], '', 'min="1"'),
            $limit('height[]', _AHEIGHT._AIN, $rul['maxheight'], _UPLOADS_FIT, 'min="1"'),
            $limit('up[]', _FILEUP, $rul['maxfiles'], '', 'min="1"'),
            $limit('allsize[]', _FSIZEALL._FIN, $mega($rul['maxquota']), '', 'min="0.01" step="0.01"'),
            $limit('gdwidth[]', _GDWIDTH._AIN, $rul['thumbwidth'], '', 'min="1"'),
            $limit('asum[]', _EDFILEA, $rul['moderfiles'], _CONFINES),
            $limit('usum[]', _EDFILEU, $rul['userfiles']),
            $limit('gsum[]', _EDFILEG, $rul['guestfiles']),
        ];
        $sum = sprintf(_UPLOADS_SUM, count(explode(',', $rul['extensions'])), filterSize($rul['maxbytes']), filterSize($rul['maxquota']));
        $facts = [['text' => basename(UPLOADS_DIR).'/'.getUploadFolder($val)]];
        foreach (explode(' · ', $sum) as $one) $facts[] = ['text' => $one];
        $chips = [];
        foreach ([_F_8 => $rul['userupload'], _F_9 => $rul['guestupload']] as $text => $on) {
            if ($on === 1) $chips[] = ['tone' => 'success', 'label' => $text];
        }
        $blocks .= $tpl->getHtmlPart('toggle-form-block', [
            'block_id' => 'upb-'.$val,
            'is_fold' => true,
            'title' => _DETAILS.': '.$name,
            'label_html' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            'facts' => $facts,
            'chips' => $chips,
            'content_html' => $tpl->getHtmlPart('div', ['rows' => $mrows]),
        ]);
    }
    $hidden = [
        ['nameattr' => 'name', 'valueattr' => 'uploads'],
        ['nameattr' => 'op', 'valueattr' => 'configsave'],
        ['nameattr' => 'token', 'valueattr' => getSiteToken('uploads')],
    ];
    $hidden[] = ['nameattr' => 'mods', 'valueattr' => implode(',', $mods)];
    foreach ($types as $name => $type) $hidden[] = ['nameattr' => 'ver['.$name.']', 'valueattr' => (string)$type->version];
    $confv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => $hidden,
        'content_html' => $blocks,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $confv]);
    setFoot();
}

function configsave(): void {
    global $afile;
    $warn = !checkAdminPost('uploads');
    $text = _TOKENMISS;
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
    $bytes = static function (mixed $val, int $back): int {
        $num = (int)round(min((float)str_replace(',', '.', is_string($val) ? $val : '0'), 1e9) * 1048576);
        return ($num > 0) ? $num : $back;
    };
    $xttyp = $filter(implode(',', array_filter(getVar('post', 'ttyp[]'), 'is_string')), 'gif,jpg,jpeg,png');
    $twidth = getVar('post', 'twidth', 'num', 640);
    $xtwidth = (!$twidth) ? 640 : $twidth;
    $theight = getVar('post', 'theight', 'num', 360);
    $xtheight = (!$theight) ? 360 : $theight;
    $dir = getVar('post', 'dir', 'var');
    $cont = [];
    $cont['dir'] = is_string($dir) ? $dir : '';
    $cont['typ'] = $xttyp;
    $cont['width'] = (string)$xtwidth;
    $cont['height'] = (string)$xtheight;
    $mods = getUploadModuleList();
    $allsize = getVar('post', 'allsize[]');
    $size = getVar('post', 'size[]');
    $width = getVar('post', 'width[]');
    $height = getVar('post', 'height[]');
    $up = getVar('post', 'up[]');
    $gdwidth = getVar('post', 'gdwidth[]');
    $asum = getVar('post', 'asum[]');
    $usum = getVar('post', 'usum[]');
    $gsum = getVar('post', 'gsum[]');
    $vers = getVar('post', 'ver[]', '', []);
    $types = getNodeTypeMap();
    $parts = [];
    $i = 0;
    foreach ($mods as $val) {
        if ($val != '') {
            $xtype = $filter(implode(',', array_filter(getVar('post', $i.'ext[]'), 'is_string')), 'gif,jpeg,jpg,png,rar,zip');
            $xallsize = $bytes($allsize[$i] ?? '', 536870912);
            $xsize = $bytes($size[$i] ?? '', 5242880);
            $xwidth = (!intval($width[$i] ?? 0)) ? 4096 : intval($width[$i]);
            $xheight = (!intval($height[$i] ?? 0)) ? 4096 : intval($height[$i]);
            $xup = (!intval($up[$i] ?? 0)) ? 10 : intval($up[$i]);
            $xgdwidth = (!intval($gdwidth[$i] ?? 0)) ? 320 : intval($gdwidth[$i]);
            $xasum = intval($asum[$i] ?? 0);
            $xusum = intval($usum[$i] ?? 0);
            $xgsum = intval($gsum[$i] ?? 0);
            $upload = getVar('post', $i.'upload', 'num');
            $upguest = getVar('post', $i.'upguest', 'num');
            $rule = [
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
            ];
            if (isset($types[$val])) $parts[$val] = array_replace($rule, ['userupload' => intval($upload), 'guestupload' => intval($upguest)]);
            else $cont[$val] = setUploadRuleData($rule);
            $i++;
        }
    }
    $sent = getVar('post', 'mods', 'raw', '');
    $seen = explode(',', is_string($sent) ? $sent : '');
    $shift = array_merge(array_diff($mods, $seen), array_diff($seen, $mods));
    $text = ($sent !== implode(',', $mods)) ? sprintf(_NODE_STALE, htmlspecialchars(implode(', ', $shift), ENT_QUOTES, 'UTF-8'))
        : updateNodeTypeParts(setConfigFile(static function (array $base, Closure $save) use ($cont): string {
            $base['uploads'] = array_replace($base['uploads'], $cont);
            ksort($base['uploads']);
            return $save($base) ? 'committed' : 'aborted';
        }), 'uploads', $parts, $vers);
    $warn = $text !== '';
    if (!$warn) $text = $drop ? _SUCCSAVE.' '._ERROR_FILE.': '.implode(', ', $drop) : _SUCCSAVE;
    }
    setRedirect($afile.'.php?name=uploads&op=config', false, 302, $text, $warn);
}

function info(): void {
    setTplAdminInfoPage(getUploadsTabs());
}

switch ($op) {
    default: uploads(); break;
    case 'sysfiles': sysfiles(); break;
    case 'theme': theme(); break;
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
