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

function getEditbox(string $file, string $info, string $warn, string $mtype, string $edit, int $tab, bool $trim = false): string {
    global $afile, $tpl;
    $cont = setAdminNavi(['ops' => ['name=editor', 'name=editor&amp;op=editheader', 'name=editor&amp;op=htaccess', 'name=editor&amp;op=robots', 'name=editor&amp;op=info'], 'tabs' => [_EFUNCN, _EHEADN, _EHTN, _ERON, _INFO], 'tab' => $tab]);
    $text = getEdittxt($file, $trim);
    $cont .= checkPerms($file);
    $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => $info]);
    if ($warn) $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => $warn]);
    $hide = getAdminHidden('name', 'editor').getAdminHidden('op', 'save').getAdminHidden('editor', $edit).getAdminHidden('file', $file);
    $rows = getAdminFormWide(textarea_code('code', 'template', 'sl_form', $mtype, $text));
    $rows .= getAdminFormWide(getAdminSubmitButton(_SAVE), '', 'sl_center');
    return $cont.getAdminBox(getAdminForm($afile.'.php', $rows, $hide, 'sl_table_edit'));
}

function editor(): void {
    $file = CONFIG_DIR.'/system.php';
    $info = _EFUNC.': '.$file.' '._EINFO;
    setHead();
    echo getEditbox($file, $info, _EINFOPHP, 'text/x-php', 'editor', 0, true);
    setFoot();
}

function editheader(): void {
    $file = CONFIG_DIR.'/header.php';
    $info = _EHEAD.': '.$file.' '._EINFO2;
    setHead();
    echo getEditbox($file, $info, _EINFOPHP, 'text/x-php', 'editheader', 1, true);
    setFoot();
}

function htaccess(): void {
    $file = BASE_DIR.'/.htaccess';
    $info = _EHT.': '.$file.' '._EINFO4;
    setHead();
    echo getEditbox($file, $info, '', 'text/x-php', 'htaccess', 2);
    setFoot();
}

function robots(): void {
    $file = BASE_DIR.'/robots.txt';
    $info = _EROB.': '.$file.' '._EINFO5;
    setHead();
    echo getEditbox($file, $info, '', 'text/plain', 'robots', 3);
    setFoot();
}

function info(): void {
    $cont = setAdminNavi(['ops' => ['name=editor', 'name=editor&amp;op=editheader', 'name=editor&amp;op=htaccess', 'name=editor&amp;op=robots', 'name=editor&amp;op=info'], 'tabs' => [_EFUNCN, _EHEADN, _EHTN, _ERON, _INFO], 'tab' => 4]);
    setAdminInfoPage($cont);
}

function save(): void {
    global $afile;
    $edit = getVar('post', 'editor', 'var');
    $file = getVar('post', 'file');
    $templ = getVar('post', 'template', 'raw');
    $type = ['.htaccess', 'robots.txt'];
    $templ = in_array($file, $type) ? $templ : '<?php'.PHP_EOL.'if (!defined(\'FUNC_FILE\')) die(\'Illegal file access\');'.PHP_EOL.$templ.PHP_EOL;
    if ($file && $templ) file_put_contents($file, $templ, LOCK_EX);
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
