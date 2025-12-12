<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');
require_once CONFIG_DIR.'/replace.php';

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0, string $extra = ''): string {
    $ops = ($opt == 1) ? ['name=replace', 'name=replace', 'name=replace&amp;op=info'] : ['', '', 'name=replace&amp;op=info'];
    $lang = [_CONTENT, _NEWS, _INFO];
    return getAdminTabs(_REPLACE, 'replace.png', '', $ops, $lang, [], [], $tab, $subtab, $legacy, $extra);
}

function replace(): void {
    global $aroute, $confre;
    head();
    $cont = navi(0, 0, 0, 0, 'replace');
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _REPLACEINFO]);
    $cont .= checkConfigFile('replace.php');
    $mods = ['content', 'news'];
    $content = '';
    $k = 0;
    foreach ($mods as $val) {
        if ($val != '') {
            $content .= '<div id="tabc'.$k.'" class="tabcont">';
            $fieldc = explode('||', $confre[$val]);
            for ($c = 0; $c < 50; $c++) {
                preg_match('#(.*)\|(.*)#i', $fieldc[$c], $out);
                $b = $c + 1;
                $display = (empty($out[1]) && empty($out[1][$c]) != '0' && $c != '0') ? ' class="sl_none"' : '';
                $hr = ($c == '0') ? '' : '<hr>';
                $content .= '<div id="fi'.$k.$c.'"'.$display.'>'.$hr
                .'<table class="sl_table_conf">'
                .'<tr><td><a OnClick="HideShow(\'fi'.$k.$b.'\', \'slide\', \'up\', 500);" title="'._ADD.'" class="sl_plus">'._REPLACE_FIELD.': '.$b.'</a></td><td>'
                .'<table><tr><td>'._WORD.':</td><td><input type="text" name="field1'.$k.'[]" value="'.$out[1].'" class="sl_conf" placeholder="'._WORD.'" required></td></tr>'
                .'<tr><td>'._CONTENT.':<div class="sl_small">'._REPLACEIN.'</div></td><td><textarea name="field2'.$k.'[]" cols="65" rows="5" class="sl_conf" placeholder="'._CONTENT.'" required>'.$out[2].'</textarea></td></tr></table></td>'
                .'</tr></table></div>';
            }
            $content .= '</div>';
            $k++;
        }
    }
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$aroute.'.php" method="post">'.$content.'<table class="sl_table_conf"><tr><td class="sl_center"><input type="hidden" name="name" value="replace"><input type="hidden" name="op" value="save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>'
    .'<script>
        var countries=new ddtabcontent("replace")
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
    $cont = [];
    $mods = ['content', 'news'];
    $a = 0;
    foreach ($mods as $val) {
        $fields = '';
        for ($i = 0; $i < 50; $i++) {
            $ident = ($i == 0) ? '' : '||';
            $field1 = getVar('post', 'field1'.$a.'['.$i.']', 'word', '0') ?: 0;
            $field2 = getVar('post', 'field2'.$a.'['.$i.']', '', '0') ?: 0;
            $fields .= $ident.$field1.'|'.$field2;
        }
        $a++;
        $cont[$val] = $fields;
    }
    setConfigFile('replace.php', 'confre', $cont);
    header('Location: '.$aroute.'.php?name=replace');
    exit;
}

function info(): void {
    head();
    echo navi(1, 2, 0, 0).'<div id="repadm_info">'.adm_info(1, 0, 'replace').'</div>';
    foot();
}

switch ($op) {
    default: replace(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
