<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');

function editor_navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    panel();
    $ops = ['editor_function', 'editor_header', 'editor_rewrite', 'editor_htaccess', 'editor_robots', 'editor_info'];
    $lang = [_EFUNCN, _EHEADN, _EREWN, _EHTN, _ERON, _INFO];
    return getAdminTabs(_EDITOR_IN, 'editor.png', '', $ops, $lang, [], [], $tab, $subtab);
}

function editor_function(): void {
    global $admin_file;
    head();
    $cont = editor_navi(0, 0, 0, 0);
    $file = 'config/config_core.php';
    $conts = trim(str_replace(['<?php', 'if (!defined(\'FUNC_FILE\')) die(\'Illegal file access\');', '?>'], '', file_get_contents($file)));
    $permtest = end_chmod($file, 666);
    if ($permtest) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $permtest]);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _EFUNC.': '.$file.' '._EINFO]);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _EINFOPHP]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$admin_file.'.php" method="post"><table class="sl_table_edit"><tr><td>'.textarea_code('code', 'template', 'sl_form', 'text/x-php', $conts).'</td></tr>'
    .'<tr><td class="sl_center"><input type="hidden" name="op" value="editor_save"><input type="hidden" name="editor" value="editor_function"><input type="hidden" name="file" value="'.$file.'"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function editor_header(): void {
    global $admin_file;
    head();
    $cont = editor_navi(0, 1, 0, 0);
    $file = 'config/config_header.php';
    $conts = trim(str_replace(['<?php', 'if (!defined(\'FUNC_FILE\')) die(\'Illegal file access\');', '?>'], '', file_get_contents($file)));
    $permtest = end_chmod($file, 666);
    if ($permtest) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $permtest]);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _EHEAD.': '.$file.' '._EINFO2]);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _EINFOPHP]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$admin_file.'.php" method="post"><table class="sl_table_edit"><tr><td>'.textarea_code('code', 'template', 'sl_form', 'text/x-php', $conts).'</td></tr>'
    .'<tr><td class="sl_center"><input type="hidden" name="op" value="editor_save"><input type="hidden" name="editor" value="editor_header"><input type="hidden" name="file" value="'.$file.'"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function editor_rewrite(): void {
    global $admin_file;
    head();
    $cont = editor_navi(0, 2, 0, 0);
    $file = 'config/config_rewrite.php';
    $conts = trim(str_replace(['<?php', 'if (!defined(\'FUNC_FILE\')) die(\'Illegal file access\');', '?>'], '', file_get_contents($file)));
    $permtest = end_chmod($file, 666);
    if ($permtest) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $permtest]);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _EREW.': '.$file.' '._EINFO3]);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _EINFOPHP]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$admin_file.'.php" method="post"><table class="sl_table_edit"><tr><td>'.textarea_code('code', 'template', 'sl_form', 'text/x-php', $conts).'</td></tr>'
    .'<tr><td class="sl_center"><input type="hidden" name="op" value="editor_save"><input type="hidden" name="editor" value="editor_rewrite"><input type="hidden" name="file" value="'.$file.'"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function editor_htaccess(): void {
    global $admin_file;
    head();
    $cont = editor_navi(0, 3, 0, 0);
    $file = '.htaccess';
    $conts = file_get_contents($file);
    $permtest = end_chmod($file, 666);
    if ($permtest) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $permtest]);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _EHT.': '.$file.' '._EINFO4]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$admin_file.'.php" method="post"><table class="sl_table_edit"><tr><td>'.textarea_code('code', 'template', 'sl_form', 'text/x-php', $conts).'</td></tr>'
    .'<tr><td class="sl_center"><input type="hidden" name="op" value="editor_save"><input type="hidden" name="editor" value="editor_htaccess"><input type="hidden" name="file" value="'.$file.'"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function editor_robots(): void {
    global $admin_file;
    head();
    $cont = editor_navi(0, 4, 0, 0);
    $file = 'robots.txt';
    $conts = file_get_contents($file);
    $permtest = end_chmod($file, 666);
    if ($permtest) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $permtest]);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _EROB.': '.$file.' '._EINFO5]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$admin_file.'.php" method="post"><table class="sl_table_edit"><tr><td>'.textarea_code('code', 'template', 'sl_form', 'text/plain', $conts).'</td></tr>'
    .'<tr><td class="sl_center"><input type="hidden" name="op" value="editor_save"><input type="hidden" name="editor" value="editor_robots"><input type="hidden" name="file" value="'.$file.'"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function editor_info(): void {
    head();
    echo editor_navi(1, 5, 0, 0).'<div id="repadm_info">'.adm_info(1, 0, 'editor').'</div>';
    foot();
}

switch($op) {
    case 'editor_function':
    editor_function();
    break;

    case 'editor_header':
    editor_header();
    break;

    case 'editor_rewrite':
    editor_rewrite();
    break;

    case 'editor_htaccess':
    editor_htaccess();
    break;

    case 'editor_robots':
    editor_robots();
    break;

    case 'editor_save':
    $editor = getVar('post', 'editor', 'var');
    $file = getVar('post', 'file');
    $template = filter_input(INPUT_POST, 'template', FILTER_UNSAFE_RAW);
    $type = ['.htaccess', 'robots.txt'];
    $template = (in_array($file, $type)) ? $template : '<?php\r\nif (!defined(\'FUNC_FILE\')) die(\'Illegal file access\');\r\n'.$template.'\r\n?>';
    if ($file && $template) {
        $handle = fopen($file, 'wb');
        fwrite($handle, $template);
        fclose($handle);
    }
    header('Location: '.$admin_file.'.php?op='.$editor);
    break;

    case 'editor_info':
    editor_info();
    break;
}