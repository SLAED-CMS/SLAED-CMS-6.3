<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');
require_once CONFIG_DIR.'/rss.php';

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0, string $extra = ''): string {
    $ops = ($opt == 1) ? ['name=rss', 'name=rss', 'name=rss&amp;op=info'] : ['', '', 'name=rss&amp;op=info'];
    $lang = [_RSS, _PREFERENCES, _INFO];
    return getAdminTabs(_RSS, 'rss.png', '', $ops, $lang, [], [], $tab, $subtab, $legacy, $extra);
}

function rss(): void {
    global $aroute, $confrs;
    head();
    $cont = navi(0, 0, 0, 0, 'rss');
    $cont .= checkConfigFile('rss.php');
    $content = '';
    $fieldc = explode('||', $confrs['rss']);
    for ($c = 0; $c < 50; $c++) {
        preg_match('#(.*)\|(.*)\|(.*)#i', $fieldc[$c], $out);
        $field = '<select name="field3[]" class="sl_conf">';
        for ($i = 0; $i < 2; $i++) {
            $fieldname = ($i == 0) ? _RSSSITE : _RSSHOME;
            $sel = (isset($out[3]) && $out[3] == $i) ? ' selected' : '';
            $field .= '<option value="'.$i.'"'.$sel.'>'.$fieldname.'</option>';
        }
        $field .= '</select>';
        $b = $c + 1;
        $out1 = $out[1] ?? '';
        $display = (empty($out1) && $c != 0) ? ' class="sl_none"' : '';
        $hr = ($c == 0) ? '' : '<hr>';
        $out2 = $out[2] ?? '';
        
        $content .= '<div id="rss'.$c.'"'.$display.'>'.$hr
        .'<table class="sl_table_conf">'
        .'<tr><td><a OnClick="HideShow(\'rss'.$b.'\', \'slide\', \'up\', 500);" title="'._ADD.'" class="sl_plus">'._RSSC.': '.$b.'</a></td><td>'
        .'<table><tr><td>'._NAME.':</td><td><input type="text" name="field1[]" value="'.$out1.'" class="sl_conf" placeholder="'._NAME.'" required></td></tr>'
        .'<tr><td>'._ADDRESS.':</td><td><input type="text" name="field2[]" value="'.$out2.'" class="sl_conf" placeholder="'._ADDRESS.'"></td></tr>'
        .'<tr><td>'._USES.':</td><td>'.$field.'</td></tr></table>'
        .'</td></tr></table></div>';
    }
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _RSSDESC]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$aroute.'.php?name=rss" method="post">'
    .'<input type="hidden" name="op" value="save">'
    .'<div id="tabc0" class="tabcont">'.$content.'</div>'
    .'<div id="tabc1" class="tabcont"><table class="sl_table_conf">'
    .'<tr><td>'._RSSMIN.':</td><td><input type="number" name="min" value="'.$confrs['min'].'" class="sl_conf" placeholder="'._RSSMIN.'" required></td></tr>'
    .'<tr><td>'._RSSMAX.':</td><td><input type="number" name="max" value="'.$confrs['max'].'" class="sl_conf" placeholder="'._RSSMAX.'" required></td></tr>'
    .'<tr><td>'._RSSTEMP.':<div class="sl_small">'._RSSTEMPINFO.'</div></td><td><textarea name="temp" cols="65" rows="5" class="sl_conf" placeholder="'._RSSTEMP.'" required>'.$confrs['temp'].'</textarea></td></tr>'
    .'<tr><td>'._RSSACT.':</td><td>'.radio_form($confrs['act'], 'act').'</td></tr>'
    .'<tr><td>'._RSSUSE.'</td><td>'.radio_form($confrs['use'], 'use').'</td></tr>'
    .'</table></div>'
    .'<table class="sl_table_conf"><tr><td class="sl_center"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>'
    .'<script>
        var countries=new ddtabcontent("rss")
        countries.setpersist(true)
        countries.setselectedClassTarget("link")
        countries.init()
    </script>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function save(): void {
    global $aroute;
    $cont = [
        'min' => getVar('post', 'min', 'num', 10),
        'max' => getVar('post', 'max', 'num', 100),
        'temp' => getVar('post', 'temp', '', ''),
        'act' => getVar('post', 'act', 'bool', 0),
        'use' => getVar('post', 'use', 'bool', 0),
    ];
    $rss = '';
    for ($i = 0; $i < 50; $i++) {
        $ident = ($i == 0) ? '' : '||';
        $field1 = getVar('post', 'field1['.$i.']', '', '0');
        $field2 = getVar('post', 'field2['.$i.']', '', '0');
        $field3 = getVar('post', 'field3['.$i.']', 'num', '0');
        $rss .= $ident.$field1.'|'.$field2.'|'.$field3;
    }
    $cont['rss'] = $rss;
    setConfigFile('rss.php', 'confrs', $cont);
    header('Location: '.$aroute.'.php?name=rss');
    exit;
}

function info(): void {
    head();
    echo navi(1, 2, 0, 0).'<div id="repadm_info">'.adm_info(1, 0, 'rss').'</div>';
    foot();
}

switch ($op) {
    default: rss(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
