<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=editor', 'name=editor&amp;op=editheader', 'name=editor&amp;op=htaccess', 'name=editor&amp;op=robots', 'name=editor&amp;op=info'];
    $lang = [_EFUNCN, _EHEADN, _EHTN, _ERON, _INFO];
    return getAdminTabs(_EDITOR_IN, 'editor.png', '', $ops, $lang, [], [], $tab, $subtab);
}

function editor(): void {
    global $afile;
    $file = CONFIG_DIR.'/system.php';
    $conts = is_readable($file) ? trim(str_replace(['<?php', 'if (!defined(\'FUNC_FILE\')) die(\'Illegal file access\');', '?>'], '', file_get_contents($file))) : '';
    setHead();
    $cont = navi(0, 0, 0, 0);
    $cont .= checkPerms($file);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _EFUNC.': '.$file.' '._EINFO]);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _EINFOPHP]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_edit"><tr><td>'.textarea_code('code', 'template', 'sl_form', 'text/x-php', $conts).'</td></tr>'
    .'<tr><td class="sl_center"><input type="hidden" name="name" value="editor"><input type="hidden" name="op" value="save"><input type="hidden" name="editor" value="editor"><input type="hidden" name="file" value="'.$file.'"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function editheader(): void {
    global $afile;
    $file = CONFIG_DIR.'/header.php';
    $conts = is_readable($file) ? trim(str_replace(['<?php', 'if (!defined(\'FUNC_FILE\')) die(\'Illegal file access\');', '?>'], '', file_get_contents($file))) : '';
    setHead();
    $cont = navi(0, 1, 0, 0);
    $cont .= checkPerms($file);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _EHEAD.': '.$file.' '._EINFO2]);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _EINFOPHP]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_edit"><tr><td>'.textarea_code('code', 'template', 'sl_form', 'text/x-php', $conts).'</td></tr>'
    .'<tr><td class="sl_center"><input type="hidden" name="name" value="editor"><input type="hidden" name="op" value="save"><input type="hidden" name="editor" value="editheader"><input type="hidden" name="file" value="'.$file.'"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function htaccess(): void {
    global $afile;
    $file = BASE_DIR.'/.htaccess';
    $conts = is_readable($file) ? file_get_contents($file) : '';
    setHead();
    $cont = navi(0, 2, 0, 0);
    $cont .= checkPerms($file);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _EHT.': '.$file.' '._EINFO4]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_edit"><tr><td>'.textarea_code('code', 'template', 'sl_form', 'text/x-php', $conts).'</td></tr>'
    .'<tr><td class="sl_center"><input type="hidden" name="name" value="editor"><input type="hidden" name="op" value="save"><input type="hidden" name="editor" value="htaccess"><input type="hidden" name="file" value="'.$file.'"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function robots(): void {
    global $afile;
    $file = BASE_DIR.'/robots.txt';
    $conts = is_readable($file) ? file_get_contents($file) : '';
    setHead();
    $cont = navi(0, 3, 0, 0);
    $cont .= checkPerms($file);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _EROB.': '.$file.' '._EINFO5]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_edit"><tr><td>'.textarea_code('code', 'template', 'sl_form', 'text/plain', $conts).'</td></tr>'
    .'<tr><td class="sl_center"><input type="hidden" name="name" value="editor"><input type="hidden" name="op" value="save"><input type="hidden" name="editor" value="robots"><input type="hidden" name="file" value="'.$file.'"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function info(): void {
    setHead();
    echo navi(1, 4, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

function save(): void {
    global $afile;
    $editor = getVar('post', 'editor', 'var');
    $file = getVar('post', 'file');
    $template = filter_input(INPUT_POST, 'template', FILTER_UNSAFE_RAW);
    $type = ['.htaccess', 'robots.txt'];
    $template = (in_array($file, $type)) ? $template : '<?php'.PHP_EOL.'if (!defined(\'FUNC_FILE\')) die(\'Illegal file access\');'.PHP_EOL.$template.PHP_EOL;
    if ($file && $template) file_put_contents($file, $template, LOCK_EX);
    setRedirect($afile.'.php?name=editor&op='.$editor);
}

switch ($op) {
    default: editor(); break;
    case 'editheader': editheader(); break;
    case 'htaccess': htaccess(); break;
    case 'robots': robots(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}