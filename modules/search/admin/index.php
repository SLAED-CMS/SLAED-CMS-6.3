<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('search')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0, string $id = ''): string {
    $ops = ($opt == 1) ? ['name=search', 'name=search&amp;op=info'] : ['', 'name=search&amp;op=info'];
    $lang = [_PREFERENCES, _INFO];
    return getAdminTabs('', $ops, $lang, [], [], $tab, $subtab, $legacy, $id);
}

function search(): void {
    global $afile, $conf;
    $allow = ['auto_links', 'faq', 'files', 'forum', 'jokes', 'links', 'media', 'news', 'pages', 'shop'];
    setHead();
    $cont = navi(0, 0, 0, 0, 'search');
    $cont .= checkPerms(CONFIG_DIR.'/search.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php?name=search" method="post">'
    .'<input type="hidden" name="op" value="save">'
    .'<div id="tabc0" class="tabcont"><table class="sl_table_conf">'
    .'<tr><td>'._SMODULE.':<div class="sl_small">'._CTRLINFO.'</div></td><td>'.modul('search', 'sl_conf', $conf['search']['mods'], 1, $allow).'</td></tr>'
    .'<tr><td>'._SEARCHLETMIN.':<div class="sl_small">'._SEARCHLETINFO.'</div></td><td><input type="number" name="slet" value="'.$conf['search']['slet'].'" class="sl_conf" placeholder="'._SEARCHLETMIN.'" required></td></tr>'
    .'<tr><td>'._SEARCHNUM.':</td><td><input type="number" name="snum" value="'.$conf['search']['snum'].'" class="sl_conf" placeholder="'._SEARCHNUM.'" required></td></tr>'
    .'<tr><td>'._C_35.':</td><td><input type="number" name="snump" value="'.$conf['search']['snump'].'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
    .'<tr><td>'._SEARCHLIMIT.':<div class="sl_small">'._SEARCHLIMITINFO.'</div></td><td><input type="number" name="slimit" value="'.$conf['search']['slimit'].'" class="sl_conf" placeholder="'._SEARCHLIMIT.'" required></td></tr>'
    .'<tr><td>'._ASEARCH.'</td><td>'.radio_form($conf['search']['asearch'], 'asearch').'</td></tr>'
    .'</table></div>'
    .'<table class="sl_table_conf"><tr><td class="sl_center"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>'
    .'<script>
        var countries=new ddtabcontent("search")
        countries.setpersist(true)
        countries.setselectedClassTarget("link")
        countries.init()
    </script>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function save(): void {
    global $afile;
    $mods = getVar('post', 'search[]', 'var');
    setConfigFile('search.php', [
        'asearch' => getVar('post', 'asearch', 'num'),
        'mods'    => $mods ? implode(',', $mods) : '0',
        'slet'    => getVar('post', 'slet', 'num', 3),
        'slimit'  => getVar('post', 'slimit', 'num', 500),
        'snum'    => getVar('post', 'snum', 'num', 25),
        'snump'   => getVar('post', 'snump', 'num', 5),
    ]);
    setRedirect($afile.'.php?name=search');
}

function info(): void {
    setHead();
    echo navi(1, 1, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: search(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
