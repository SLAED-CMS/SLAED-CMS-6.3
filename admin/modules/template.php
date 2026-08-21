<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getTemplateFiles(string $dir, string $ext): array {
    if (!is_dir($dir)) return [];
    $list = [];
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iter as $item) {
        if (!$item->isFile()) continue;
        if (strtolower($item->getExtension()) !== $ext) continue;
        $list[] = str_replace('\\', '/', $item->getPathname());
    }
    sort($list, SORT_NATURAL | SORT_FLAG_CASE);
    return $list;
}

function getTemplateHtmlFiles(string $templ): array {
    $dirs = [];
    $base = BASE_DIR.'/templates/'.$templ;
    foreach (['fragments', 'partials', 'layouts', 'pages'] as $part) {
        $path = $base.'/'.$part;
        if (is_dir($path)) $dirs[] = $path;
    }
    $list = [];
    foreach ($dirs as $dir) {
        $list = array_merge($list, getTemplateFiles($dir, 'html'));
    }
    sort($list, SORT_NATURAL | SORT_FLAG_CASE);
    return array_values(array_unique($list));
}

function getTemplateCssFiles(string $templ): array {
    $dir = BASE_DIR.'/templates/'.$templ.'/assets/css';
    return getTemplateFiles($dir, 'css');
}

function getTemplateTabsOps(string $templ): array {
    return [
        'name=template&templ='.$templ,
        'name=template&op=style&templ='.$templ,
        'name=template&op=info&templ='.$templ,
    ];
}

function getTemplateSearch(string $templ): string {
    global $afile, $tpl;
    $opts = '';
    foreach (scandir(BASE_DIR.'/templates') as $file) {
        if ($file === '.' || $file === '..' || !is_dir(BASE_DIR.'/templates/'.$file)) continue;
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $file,
            'label_text' => $file,
            'is_selected' => $file === $templ,
        ]);
    }
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=template',
        'content_html' => _THEME.': '.$tpl->getHtmlFrag('select', [
            'name_attr' => 'templ',
            'options_html' => $opts,
        ]).' '.$tpl->getHtmlFrag('button', [
            'submit_label' => _OK,
            'button_type' => 'submit',
        ]),
    ]);
    return $tpl->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => $form]);
}

function getTemplateEditorBlock(string $templ, string $filelink, string $mode, string $op): string {
    global $afile, $tpl;
    $body = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=template&op='.$op,
        'hidden' => [
            ['nameattr' => 'templ', 'valueattr' => $templ],
            ['nameattr' => 'filelink', 'valueattr' => $filelink],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => [[
            'label_html' => '',
            'field_html' => getTplLines([_FILE.': '.$filelink, _DATE.': '.date(_TIMESTRING, filemtime($filelink))]),
            'is_full' => true,
        ], [
            'label_html' => '',
            'field_html' => Editor::getCode([
                'id' => 'code_'.md5($filelink),
                'name' => 'template',
                'lang' => str_ends_with($filelink, '.css') ? 'css' : 'html',
                'text' => (string)file_get_contents($filelink),
            ]),
            'is_full' => true,
        ]],
        'submit_label' => _SAVECHANGES,
    ]);
    return $tpl->getHtmlPart('box', ['content_html' => $body]);
}

function getTemplateFilePath(string $templ, string $filelink, bool $iscss): string {
    $templ = basename(trim($templ));
    if ($templ === '') return '';
    $base = BASE_DIR.'/templates/'.$templ;
    $path = str_replace(['\\', '//'], ['/', '/'], trim($filelink));
    if ($path === '') return '';
    $realbase = realpath($base);
    $realpath = realpath(BASE_DIR.'/'.$path);
    if ($realbase === false || $realpath === false) return '';
    $realbase = str_replace('\\', '/', $realbase);
    $realpath = str_replace('\\', '/', $realpath);
    if (!str_starts_with($realpath, $realbase.'/') && $realpath !== $realbase) return '';
    $ext = strtolower((string)pathinfo($realpath, PATHINFO_EXTENSION));
    if ($iscss) {
        return $ext === 'css' ? $realpath : '';
    }
    return $ext === 'html' ? $realpath : '';
}

function template(): void {
    global $afile, $conf, $tpl;
    $templ = getVar('post', 'templ', 'var', '');
    if ($templ === '') $templ = getVar('get', 'templ', 'var', $conf['theme']);
    setHead();
    $cont = getTplAdminTabs([
        'ops' => getTemplateTabsOps($templ),
        'tabs' => [_TEMPLATES, _STYLES, _DOCS],
        'subtitle_html' => getTemplateSearch($templ),
    ]);
    $dir = BASE_DIR.'/templates/'.$templ;
    if (is_dir($dir)) {
        $conts = '';
        $files = getTemplateHtmlFiles($templ);
        foreach ($files as $path) {
            $rel = str_replace(str_replace('\\', '/', BASE_DIR.'/'), '', $path);
            $permtest = checkPerms($path);
            if ($permtest) $cont .= $permtest;
            $conts .= getTemplateEditorBlock($templ, $rel, 'text/html', 'save');
        }
        $cont .= $conts !== '' ? $conts : $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function style(): void {
    global $afile, $conf, $tpl;
    $templ = getVar('get', 'templ', 'var', $conf['theme']);
    setHead();
    $cont = getTplAdminTabs([
        'ops' => getTemplateTabsOps($templ),
        'tabs' => [_TEMPLATES, _STYLES, _DOCS],
        'tab' => 1,
        'subtitle_html' => getTemplateSearch($templ),
    ]);
    $dir = BASE_DIR.'/templates/'.$templ.'/assets/css';
    if (is_dir($dir)) {
        $conts = '';
        $files = getTemplateCssFiles($templ);
        foreach ($files as $path) {
            $rel = str_replace(str_replace('\\', '/', BASE_DIR.'/'), '', $path);
            $permtest = checkPerms($path);
            if ($permtest) $cont .= $permtest;
            $conts .= getTemplateEditorBlock($templ, $rel, 'text/css', 'stylesave');
        }
        $cont .= $conts !== '' ? $conts : $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function save(): void {
    global $afile, $conf;
    $templ = getVar('post', 'templ', 'var', $conf['theme']);
    $warn = !checkSiteToken();
    if (!$warn) {
        $filelink = getVar('post', 'filelink', 'text', '');
        $text = (string)getVar('post', 'template', 'raw', '');
        $path = getTemplateFilePath($templ, $filelink, false);
        if ($path !== '' && $text !== '') {
            $handle = fopen($path, 'wb');
            if ($handle !== false) {
                fwrite($handle, $text);
                fclose($handle);
            }
        }
    }
    $templparam = $templ ? '&templ='.$templ : '';
    setRedirect($afile.'.php?name=template'.$templparam, false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function stylesave(): void {
    global $afile, $conf;
    $templ = getVar('post', 'templ', 'var', $conf['theme']);
    $warn = !checkSiteToken();
    if (!$warn) {
        $filelink = getVar('post', 'filelink', 'text', '');
        $text = (string)getVar('post', 'template', 'raw', '');
        $path = getTemplateFilePath($templ, $filelink, true);
        if ($path !== '' && $text !== '') {
            $handle = fopen($path, 'wb');
            if ($handle !== false) {
                fwrite($handle, $text);
                fclose($handle);
            }
        }
    }
    $templparam = $templ ? '&templ='.$templ : '';
    setRedirect($afile.'.php?name=template&op=style'.$templparam, false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function info(): void {
    global $conf;
    $templ = getVar('get', 'templ', 'var', $conf['theme']);
    setTplAdminInfoPage([
        'ops' => getTemplateTabsOps($templ),
        'tabs' => [_TEMPLATES, _STYLES, _DOCS],
        'subtitle_html' => getTemplateSearch($templ),
    ]);
}

switch ($op) {
    default: template(); break;
    case 'save': save(); break;
    case 'style': style(); break;
    case 'stylesave': stylesave(); break;
    case 'info': info(); break;
}
