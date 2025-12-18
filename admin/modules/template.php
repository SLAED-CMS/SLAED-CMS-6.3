<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    global $aroute, $conf;
    $templ = getVar('post', 'templ', 'var') ?: (getVar('get', 'templ', 'var') ?: $conf['theme']);
    $ops = ['name=template&amp;templ='.$templ, 'name=template&amp;op=style&amp;templ='.$templ, 'name=template&amp;op=info'];
    $lang = [_TEMPLATES, _STYLES, _INFO];
    $search = '<form method="post" action="'.$aroute.'.php">'._THEME.': <select name="templ">';
    $handle = opendir('templates');
    if ($handle !== false) {
        while (($file = readdir($handle)) !== false) {
            if (!preg_match('/\./', $file)) {
                $selected = ($file == $templ) ? ' selected' : '';
                $search .= '<option value="'.$file.'"'.$selected.'>'.$file.'</option>';
            }
        }
        closedir($handle);
    }
    $search .= '</select> <input type="hidden" name="name" value="template"><input type="hidden" name="op" value="template"><input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
    $search = setTemplateBasic('searchbox', ['{%searchbox%}' => $search]);
    return getAdminTabs(_THEME, 'template.png', $search, $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function template(): void {
    global $aroute, $conf;
    $templ = getVar('post', 'templ', 'var') ?: (getVar('get', 'templ', 'var') ?: $conf['theme']);
    head();
    $cont = navi(0, 0, 0, 0);
    $dir = 'templates/'.$templ;
    if (is_dir($dir)) {
        $langs = ['.html' => '', 'assoc' => _ASSOTOPIC, 'all' => _ALL, 'admin' => _ADMIN, 'basic' => _CONTENT, 'block' => _BLOCK, 'bottom' => _BOTTOM, 'categories' => _CATEGORIES, 'cat' => _CATEGORIES, 'center' => _CENTER, 'code' => _CODE, 'comment' => _COMMENTS, 'change' => _CHANGE, 'index' => _INDEX, 'img' => _IMG, 'hide' => _HIDE, 'home' => _HOME, 'listing' => _LISTING, 'list' => _LISTING, 'login' => _INPUT, 'logged' => _LOGGED, 'kasse' => _PBASKET, 'messagebox' => _TMESS, 'message' => _MESSAGE, 'modul' => _MODUL, 'navi' => _NAVI, 'pagenum' => _PAGENUM, 'panel' => _ADMINMENU, 'post' => _SEND, 'prcenter' => _CENTERDOWN, 'prints' => _PRINTS, 'privat' => _PRIVAT, 'close' => _TCLOSE, 'open' => _TOPEN, 'title' => _TTITLE, 'warn' => _TWARNING, 'preview' => _PREVIEW, 'view' => _MVIEW, 'left' => _LEFT, 'right' => _RIGHT, 'down' => _CENTERDOWN, 'info' => _INFO, 'spoiler' => _SPOILER, 'quote' => _QUOTE, 'without' => _LOGINL, '-' => ' &raquo; '];
        $i = 0;
        $conts = '';
        $handle = opendir($dir);
        if ($handle !== false) {
            while (($file = readdir($handle)) !== false) {
                if (strpos($file, '.html')) {
                    $filelink = $dir.'/'.$file;
                    $permtest = checkPerms($filelink, 1);
                    if ($permtest) $cont .= $permtest;
                    $comp = deflmconst(strtr($file, $langs));
                    $conts .= '<table class="sl_bodyline"><tr><th class="sl_right"><a OnClick="CloseOpen(\'sl_open_'.$i.'\', 0);" title="'._EDIT.'" class="sl_plus">'.$comp.' | '._FILE.': '.$file.' | '.date(_TIMESTRING, filemtime($filelink)).'</a></th></tr></table>'
                    .'<div id="sl_open_'.$i.'"><form action="'.$aroute.'.php" method="post"><table class="sl_blockline"><tr><td>'.textarea_code('code_'.$i.'', 'template', 'sl_form', 'text/html', file_get_contents($filelink)).'</td></tr>'
                    .'<tr><td class="sl_center"><input type="hidden" name="name" value="template"><input type="hidden" name="op" value="save"><input type="hidden" name="templ" value="'.$templ.'"><input type="hidden" name="filelink" value="'.$filelink.'"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form></div>';
                    $i++;
                }
            }
            closedir($handle);
        }
        $cont .= setTemplateBasic('open').$conts.setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    foot();
}

function style(): void {
    global $aroute, $conf;
    $templ = getVar('get', 'templ', 'var') ?: $conf['theme'];
    head();
    $cont = navi(0, 1, 0, 0);
    $dir = is_dir('templates/'.$templ.'/css') ? 'templates/'.$templ.'/css' : 'templates/'.$templ;
    if (is_dir($dir)) {
        $langs = ['.css' => '', 'all' => _ALL, 'basic' => _CONTENT, 'blocks' => _BLOCKS, 'calendar' => _CALENDAR, 'index' => _INDEX, 'home' => _HOME, 'styles' => _STYLES, 'style' => _STYLE, 'system' => _SYSTEM, 'engine' => _SYSTEM, 'theme' => _THEME, 'main' => _GENPREF, '-' => ' &raquo; '];
        $i = 0;
        $conts = '';
        $handle = opendir($dir);
        if ($handle !== false) {
            while (($file = readdir($handle)) !== false) {
                if (strpos($file, '.css')) {
                    $filelink = $dir.'/'.$file;
                    $permtest = checkPerms($filelink, 1);
                    if ($permtest) $cont .= $permtest;
                    $comp = deflmconst(strtr($file, $langs));
                    $conts .= '<table class="sl_bodyline"><tr><th class="sl_right"><a OnClick="CloseOpen(\'sl_open_'.$i.'\', 0);" title="'._EDIT.'" class="sl_plus">'.$comp.' | '._FILE.': '.$file.' | '.date(_TIMESTRING, filemtime($filelink)).'</a></th></tr></table>'
                    .'<div id="sl_open_'.$i.'"><form action="'.$aroute.'.php" method="post"><table class="sl_blockline"><tr><td>'.textarea_code('code_'.$i.'', 'template', 'sl_form', 'text/css', file_get_contents($filelink)).'</td></tr>'
                    .'<tr><td class="sl_center"><input type="hidden" name="name" value="template"><input type="hidden" name="op" value="stylesave"><input type="hidden" name="templ" value="'.$templ.'"><input type="hidden" name="filelink" value="'.$filelink.'"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form></div>';
                    $i++;
                }
            }
            closedir($handle);
        }
        $cont .= setTemplateBasic('open').$conts.setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    foot();
}

function save(): void {
    global $aroute;
    $templ = getVar('post', 'templ', 'var');
    $filelink = getVar('post', 'filelink', 'text');
    $template = getVar('post', 'template', 'raw');
    if ($filelink && $template) {
        $handle = fopen($filelink, 'wb');
        fwrite($handle, $template);
        fclose($handle);
    }
    $templParam = $templ ? '&templ='.$templ : '';
    header('Location: '.$aroute.'.php?name=template'.$templParam);
    exit;
}

function stylesave(): void {
    global $aroute;
    $templ = getVar('post', 'templ', 'var');
    $filelink = getVar('post', 'filelink', 'text');
    $template = getVar('post', 'template', 'raw');
    if ($filelink && $template) {
        $handle = fopen($filelink, 'wb');
        fwrite($handle, $template);
        fclose($handle);
    }
    $templParam = $templ ? '&templ='.$templ : '';
    header('Location: '.$aroute.'.php?name=template&op=style'.$templParam);
    exit;
}

function info(): void {
    head();
    echo navi(0, 2, 0, 0).'<div id="repadm_info">'.adm_info(1, 0, 'template').'</div>';
    foot();
}

switch ($op) {
    default: template(); break;
    case 'save': save(); break;
    case 'style': style(); break;
    case 'stylesave': stylesave(); break;
    case 'info': info(); break;
}
