<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0): string {
    $ops = ['name=editor', 'name=editor&amp;op=editheader', 'name=editor&amp;op=htaccess', 'name=editor&amp;op=robots', 'name=editor&amp;op=info'];
    $lang = [_EFUNCN, _EHEADN, _EHTN, _ERON, _INFO];
    return getAdminTabs('', $ops, $lang, [], [], $tab, $subtab);
}

function getEdittxt(string $file, bool $trim = false): string {
    $text = is_readable($file) ? file_get_contents($file) : '';
    if ($text === false) return '';
    if (!$trim) return $text;
    return trim(str_replace(['<?php', 'if (!defined(\'FUNC_FILE\')) die(\'Illegal file access\');', '?>'], '', $text));
}

function getEditbox(string $file, string $info, string $warn, string $mtype, string $edit, int $tab, bool $trim = false): string {
    global $afile;
    $cont = navi(0, $tab, 0);
    $text = getEdittxt($file, $trim);
    $cont .= checkPerms($file);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $info]);
    if ($warn) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $warn]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_edit"><tr><td>'.textarea_code('code', 'template', 'sl_form', $mtype, $text).'</td></tr>'
    .'<tr><td class="sl_center"><input type="hidden" name="name" value="editor"><input type="hidden" name="op" value="save"><input type="hidden" name="editor" value="'.$edit.'"><input type="hidden" name="file" value="'.$file.'"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    return $cont;
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
    setHead();
    echo navi(1, 4, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
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
