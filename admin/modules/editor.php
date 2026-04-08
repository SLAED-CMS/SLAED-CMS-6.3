<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
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

function normalizeRawEditorText(string $file, string $text): string {
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
    return $tpl->getHtmlFrag('new/button', [
        'label' => _EROBSTD,
        'button_attr' => 'data-robots-template="'.htmlspecialchars(json_encode($template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8').'" onclick="var src=this.getAttribute(\'data-robots-template\');var code=document.getElementById(\'code\');if(!code||!src)return;code.value=JSON.parse(src);if(window.editor&&typeof window.editor.setValue===\'function\'){window.editor.setValue(code.value);window.editor.focus();}"',
    ]);
}

function isHtmxReq(): bool {
    return strtolower($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true';
}

function getEditbox(string $file, string $info, string $warn, string $mtype, string $edit, int $tab, bool $trim = false, string $extra = '', string $fallback = '', string $note = '', string $noteType = 'info'): string {
    global $afile, $tpl;
    $ops = ['name=editor', 'name=editor&amp;op=editheader', 'name=editor&amp;op=htaccess', 'name=editor&amp;op=robots', 'name=editor&amp;op=info'];
    $tabs = [_EFUNCN, _EHEADN, _EHTN, _ERON, _INFO];
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => $tab]);
    $text = getEdittxt($file, $trim);
    $text = normalizeRawEditorText($file, $text);
    if ($text === '' && $fallback !== '') $text = $fallback;
    $cont .= checkPerms($file);
    $cont .= $tpl->getHtmlFrag('new/alert', ['text' => $info]);
    if ($warn) $cont .= $tpl->getHtmlFrag('new/alert', ['is_warn' => true, 'text' => $warn]);
    $noteHtml = ($note !== '') ? $tpl->getHtmlFrag('new/alert', ['is_warn' => $noteType === 'warn', 'text' => $note]) : '';
    $cont .= $tpl->getHtmlPart('box', ['box_id' => 'repeditornote', 'content_html' => $noteHtml]);
    $attr = 'hx-post="'.$afile.'.php" hx-target="#repeditornote" hx-swap="innerHTML" hx-push-url="false" hx-on:htmx:config-request="if (window.editor && typeof window.editor.save === \'function\') { window.editor.save(); }"';
    return $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('new/form', [
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
            'label_html' => _CONTENT,
            'field_html' => getTplCodeEditor([
                'id' => 'code',
                'name' => 'template',
                'mode' => $mtype,
                'text' => $text,
            ]),
        ]],
        'content_html' => $extra,
        'submit_label' => _SAVE,
    ])]);
}

function getEditorView(string $edit, string $note = '', string $noteType = 'info'): string {
    return match ($edit) {
        'editheader' => getEditbox(CONFIG_DIR.'/header.php', _EHEAD.': '.CONFIG_DIR.'/header.php '._EINFO2, _EINFOPHP, 'text/x-php', 'editheader', 1, true, '', '', $note, $noteType),
        'htaccess' => getEditbox(BASE_DIR.'/.htaccess', _EHT.': '.BASE_DIR.'/.htaccess '._EINFO4, '', 'text/x-php', 'htaccess', 2, false, '', '', $note, $noteType),
        'robots' => getEditbox(BASE_DIR.'/robots.txt', _EROB.': '.BASE_DIR.'/robots.txt '._EINFO5, '', 'text/plain', 'robots', 3, false, getRobotsButton(getRobotsTemplate()), getRobotsTemplate(), $note, $noteType),
        default => getEditbox(CONFIG_DIR.'/system.php', _EFUNC.': '.CONFIG_DIR.'/system.php '._EINFO, _EINFOPHP, 'text/x-php', 'editor', 0, true, '', '', $note, $noteType),
    };
}

function renderEditorPage(string $edit, string $note = '', string $noteType = 'info'): void {
    global $tpl;
    $html = $tpl->getHtmlPart('box', ['box_id' => 'repeditor', 'content_html' => getEditorView($edit, $note, $noteType)]);
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

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=editor', 'name=editor&amp;op=editheader', 'name=editor&amp;op=htaccess', 'name=editor&amp;op=robots', 'name=editor&amp;op=info'],
        'tabs' => [_EFUNCN, _EHEADN, _EHTN, _ERON, _INFO],
    ]);
}

function save(): void {
    global $afile, $tpl;
    $edit = getVar('post', 'editor', 'var');
    $file = getVar('post', 'file');
    if (!checkSiteToken()) {
        if (isHtmxReq()) {
            echo $tpl->getHtmlFrag('new/alert', ['is_warn' => true, 'text' => _TOKENMISS]);
            return;
        }
        setRedirect($afile.'.php?name=editor&op='.$edit);
    }
    $templ = getVar('post', 'template', 'raw');
    $templ = normalizeRawEditorText($file, $templ);
    $templ = isRawEditorFile($file) ? $templ : '<?php'.PHP_EOL.'if (!defined(\'FUNC_FILE\')) die(\'Illegal file access\');'.PHP_EOL.$templ.PHP_EOL;
    $saved = false;
    if ($file && $templ) $saved = file_put_contents($file, $templ, LOCK_EX) !== false;
    if (isHtmxReq()) {
        $note = $saved ? _ESAVED.': '.$file : _ERROR.': '.$file;
        echo $tpl->getHtmlFrag('new/alert', ['is_warn' => !$saved, 'text' => $note]);
        return;
    }
    setRedirect($afile.'.php?name=editor&op='.$edit);
}

switch ($op) {
    default: editor(); break;
    case 'editheader': editheader(); break;
    case 'htaccess': htaccess(); break;
    case 'robots': robots(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
