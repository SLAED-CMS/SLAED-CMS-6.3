<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getTemplateSearch(string $templ): string {
    global $afile, $tpl;
    $search = '<form method="post" action="'.$afile.'.php">'._THEME.': <select name="templ">';
    foreach (scandir('templates') as $file) {
        if (!preg_match('/\./', $file)) {
            $selected = ($file == $templ) ? ' selected' : '';
            $search .= '<option value="'.$file.'"'.$selected.'>'.$file.'</option>';
        }
    }
    $search .= '</select> <input type="hidden" name="name" value="template"><input type="hidden" name="op" value="template"><input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
    return $tpl->getHtmlPart('searchbox', ['searchbox' => $search]);
}

function template(): void {
    global $afile, $conf, $tpl;
    $templ = getVar('post', 'templ', 'var', '');
    if ($templ === '') $templ = getVar('get', 'templ', 'var', $conf['theme']);
    setHead();
    $cont = setAdminNavi(['ops' => ['name=template&amp;templ='.$templ, 'name=template&amp;op=style&amp;templ='.$templ, 'name=template&amp;op=info'], 'tabs' => [_TEMPLATES, _STYLES, _INFO], 'sub' => getTemplateSearch($templ)]);
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
                    $permtest = checkPerms(BASE_DIR.'/'.$filelink);
                    if ($permtest) $cont .= $permtest;
                    $comp = getModuleName(strtr($file, $langs));
                    $conts .= '<table class="sl_bodyline"><tr><th class="sl_right"><a OnClick="CloseOpen(\'sl_open_'.$i.'\', 0);" title="'._EDIT.'" class="sl_plus">'.$comp.' | '._FILE.': '.$file.' | '.date(_TIMESTRING, filemtime($filelink)).'</a></th></tr></table>'
                    .'<div id="sl_open_'.$i.'"><form action="'.$afile.'.php" method="post"><table class="sl_blockline"><tr><td>'.textarea_code('code_'.$i.'', 'template', 'sl_form', 'text/html', file_get_contents($filelink)).'</td></tr>'
                    .'<tr><td class="sl_center"><input type="hidden" name="name" value="template"><input type="hidden" name="op" value="save"><input type="hidden" name="templ" value="'.$templ.'"><input type="hidden" name="filelink" value="'.$filelink.'"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form></div>';
                    $i++;
                }
            }
            closedir($handle);
        }
        $cont .= $tpl->getHtmlFrag('open', []).$conts.$tpl->getHtmlFrag('close', []);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function style(): void {
    global $afile, $conf, $tpl;
    $templ = getVar('get', 'templ', 'var', $conf['theme']);
    setHead();
    $cont = setAdminNavi(['ops' => ['name=template&amp;templ='.$templ, 'name=template&amp;op=style&amp;templ='.$templ, 'name=template&amp;op=info'], 'tabs' => [_TEMPLATES, _STYLES, _INFO], 'tab' => 1, 'sub' => getTemplateSearch($templ)]);
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
                    $permtest = checkPerms(BASE_DIR.'/'.$filelink);
                    if ($permtest) $cont .= $permtest;
                    $comp = getModuleName(strtr($file, $langs));
                    $conts .= '<table class="sl_bodyline"><tr><th class="sl_right"><a OnClick="CloseOpen(\'sl_open_'.$i.'\', 0);" title="'._EDIT.'" class="sl_plus">'.$comp.' | '._FILE.': '.$file.' | '.date(_TIMESTRING, filemtime($filelink)).'</a></th></tr></table>'
                    .'<div id="sl_open_'.$i.'"><form action="'.$afile.'.php" method="post"><table class="sl_blockline"><tr><td>'.textarea_code('code_'.$i.'', 'template', 'sl_form', 'text/css', file_get_contents($filelink)).'</td></tr>'
                    .'<tr><td class="sl_center"><input type="hidden" name="name" value="template"><input type="hidden" name="op" value="stylesave"><input type="hidden" name="templ" value="'.$templ.'"><input type="hidden" name="filelink" value="'.$filelink.'"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form></div>';
                    $i++;
                }
            }
            closedir($handle);
        }
        $cont .= $tpl->getHtmlFrag('open', []).$conts.$tpl->getHtmlFrag('close', []);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function save(): void {
    global $afile;
    $templ = getVar('post', 'templ', 'var');
    $filelink = getVar('post', 'filelink', 'text');
    $template = getVar('post', 'template', 'raw');
    if ($filelink && $template) {
        $handle = fopen($filelink, 'wb');
        fwrite($handle, $template);
        fclose($handle);
    }
    $templParam = $templ ? '&templ='.$templ : '';
    setRedirect($afile.'.php?name=template'.$templParam);
}

function stylesave(): void {
    global $afile;
    $templ = getVar('post', 'templ', 'var');
    $filelink = getVar('post', 'filelink', 'text');
    $template = getVar('post', 'template', 'raw');
    if ($filelink && $template) {
        $handle = fopen($filelink, 'wb');
        fwrite($handle, $template);
        fclose($handle);
    }
    $templParam = $templ ? '&templ='.$templ : '';
    setRedirect($afile.'.php?name=template&op=style'.$templParam);
}

function info(): void {
    setHead();
    global $conf;
    $templ = getVar('get', 'templ', 'var', $conf['theme']);
    $cont = setAdminNavi(['ops' => ['name=template&amp;templ='.$templ, 'name=template&amp;op=style&amp;templ='.$templ, 'name=template&amp;op=info'], 'tabs' => [_TEMPLATES, _STYLES, _INFO], 'tab' => 2, 'sub' => getTemplateSearch($templ)]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: template(); break;
    case 'save': save(); break;
    case 'style': style(); break;
    case 'stylesave': stylesave(); break;
    case 'info': info(); break;
}
