<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function getEdittxt(string $file, bool $trim = false): string {
    $text = is_readable($file) ? file_get_contents($file) : '';
    if ($text === false) return '';
    if (!$trim) return $text;
    return trim(str_replace(['<?php', 'if (!defined(\'FUNC_FILE\')) die(\'Illegal file access\');', '?>'], '', $text));
}

function isRawEditorFile(string $file): bool {
    return in_array(basename($file), ['.htaccess', 'robots.txt'], true);
}

function filterRawEditorText(string $file, string $text): string {
    if (!isRawEditorFile($file)) return $text;
    $prefix = '<?php'.PHP_EOL.'if (!defined(\'FUNC_FILE\')) die(\'Illegal file access\');'.PHP_EOL;
    if (str_starts_with($text, $prefix)) $text = substr($text, strlen($prefix));
    return ltrim($text);
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
        'Disallow: /setup/',
        'Disallow: /setup.php',
        'Disallow: /sound/',
        'Disallow: /storage/',
    ];
    $rows[] = '';
    $rows[] = 'Sitemap: '.($base ? $base.'/sitemap.xml' : '/sitemap.xml');
    return implode(PHP_EOL, $rows);
}

function getRobotsButton(string $template): string {
    global $tpl;
    return $tpl->getHtmlFrag('head-script-src', [
        'src' => 'templates/admin/assets/js/editor-robots.js',
        'attr' => 'defer',
    ]).$tpl->getHtmlFrag('editor-robots-button', [
        'label' => _EROBSTD,
        'template_json' => json_encode($template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function isHtmxReq(): bool {
    return strtolower($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true';
}

function getEditbox(string $file, string $info, string $warn, string $mtype, string $edit, int $tab, bool $trim = false, string $extra = '', string $fallback = '', string $note = '', string $type = 'info'): string {
    global $afile, $tpl;
    $ops = ['name=editor', 'name=editor&amp;op=editheader', 'name=editor&amp;op=htaccess', 'name=editor&amp;op=robots', 'name=editor&amp;op=info'];
    $tabs = [_EFUNCN, _EHEADN, _EHTN, _ERON, _INFO];
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => $tab]);
    $text = getEdittxt($file, $trim);
    $text = filterRawEditorText($file, $text);
    if ($text === '' && $fallback !== '') $text = $fallback;
    $cont .= checkPerms($file);
    $cont .= $tpl->getHtmlFrag('alert', ['text' => $info]);
    if ($warn) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $warn]);
    $html = ($note !== '') ? $tpl->getHtmlFrag('alert', ['is_warn' => $type === 'warn', 'text' => $note]) : '';
    $cont .= $tpl->getHtmlPart('div', ['id' => 'repeditornote', 'is_collapsible' => true, 'content_html' => $html]);
    $attr = 'hx-post="'.$afile.'.php" hx-target="#repeditornote" hx-swap="innerHTML" hx-push-url="false" hx-on:htmx:config-request="var code=document.getElementById(\'code\');var view=(window.CM6&&CM6.editors)?CM6.editors[\'code\']:null;if(code&&view&&view.state&&view.state.doc){code.value=view.state.doc.toString();}"';
    return $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'form_attr' => $attr,
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'editor'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'editor', 'valueattr' => $edit],
            ['nameattr' => 'file', 'valueattr' => $file],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => [[
            'is_full' => true,
            'label_html' => '',
            'field_html' => Editor::getCode([
                'id' => 'code',
                'name' => 'template',
                'lang' => 'php',
                'text' => $text,
            ]),
        ]],
        'actions_html' => $extra,
        'submit_label' => _SAVE,
    ])]);
}

function getEditorView(string $edit, string $note = '', string $type = 'info'): string {
    return match ($edit) {
        'editheader' => getEditbox(CONFIG_DIR.'/header.php', _EHEAD.': '.CONFIG_DIR.'/header.php '._EINFO2, _EINFOPHP, 'text/x-php', 'editheader', 1, true, '', '', $note, $type),
        'htaccess' => getEditbox(BASE_DIR.'/.htaccess', _EHT.': '.BASE_DIR.'/.htaccess '._EINFO4, '', 'text/x-php', 'htaccess', 2, false, '', '', $note, $type),
        'robots' => getEditbox(BASE_DIR.'/robots.txt', _EROB.': '.BASE_DIR.'/robots.txt '._EINFO5, '', 'text/plain', 'robots', 3, false, getRobotsButton(getRobotsTemplate()), getRobotsTemplate(), $note, $type),
        default => getEditbox(CONFIG_DIR.'/system.php', _EFUNC.': '.CONFIG_DIR.'/system.php '._EINFO, _EINFOPHP, 'text/x-php', 'editor', 0, true, '', '', $note, $type),
    };
}

function renderEditorPage(string $edit, string $note = '', string $type = 'info'): void {
    $html = getEditorView($edit, $note, $type);
    if (isHtmxReq()) {
        echo $html;
        return;
    }
    setHead();
    echo $html;
    setFoot();
}

function editor(): void {
    renderEditorPage('editor');
}

function editheader(): void {
    renderEditorPage('editheader');
}

function htaccess(): void {
    renderEditorPage('htaccess');
}

function robots(): void {
    renderEditorPage('robots');
}

function save(): void {
    global $afile, $tpl;
    $edit = getVar('post', 'editor', 'var');
    $file = getVar('post', 'file');
    if (!checkSiteToken()) {
        if (isHtmxReq()) {
            echo $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _TOKENMISS]);
            return;
        }
        setHead();
        $cont = getTplAdminTabs([
            'ops' => ['name=editor', 'name=editor&amp;op=editheader', 'name=editor&amp;op=htaccess', 'name=editor&amp;op=robots', 'name=editor&amp;op=info'],
            'tabs' => [_EFUNCN, _EHEADN, _EHTN, _ERON, _INFO],
            'tab' => $edit === 'editheader' ? 1 : ($edit === 'htaccess' ? 2 : ($edit === 'robots' ? 3 : 0)),
        ]);
        echo $cont.$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _TOKENMISS]);
        setFoot();
        return;
    }
    $templ = getVar('post', 'template', 'raw');
    $templ = filterRawEditorText($file, $templ);
    $templ = isRawEditorFile($file) ? $templ : '<?php'.PHP_EOL.'if (!defined(\'FUNC_FILE\')) die(\'Illegal file access\');'.PHP_EOL.$templ.PHP_EOL;
    $saved = false;
    if ($file && $templ) $saved = file_put_contents($file, $templ, LOCK_EX) !== false;
    if (isHtmxReq()) {
        $note = $saved ? _ESAVED.': '.$file : _ERROR.': '.$file;
        echo $tpl->getHtmlFrag('alert', ['is_warn' => !$saved, 'text' => $note]);
        return;
    }
    setRedirect($afile.'.php?name=editor&op='.$edit, false, 302, $saved ? _SUCCFILESAVE : _ERROR.': '.$file, !$saved);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=editor', 'name=editor&amp;op=editheader', 'name=editor&amp;op=htaccess', 'name=editor&amp;op=robots', 'name=editor&amp;op=info'],
        'tabs' => [_EFUNCN, _EHEADN, _EHTN, _ERON, _INFO],
    ]);
}

switch ($op) {
    default: editor(); break;
    case 'editheader': editheader(); break;
    case 'htaccess': htaccess(); break;
    case 'robots': robots(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
