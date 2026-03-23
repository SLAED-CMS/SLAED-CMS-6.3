<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getCategoriesSearch(string $modul): string {
    global $afile, $tpl;
    return $tpl->getHtmlPart('searchbox', ['searchbox' => '<form method="post" action="'.$afile.'.php"><input type="hidden" name="name" value="categories">'._MODUL.': '.cat_modul('modul', '', $modul, 1).'</form>']);
}

function categories(): void {
    global $tpl;
    $modul = getVar('req', 'modul', 'var', 'forum');
    $modlink = '&amp;modul='.$modul;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=categories'.$modlink, 'name=categories&amp;op=add'.$modlink, 'name=categories&amp;op=subadd'.$modlink, 'name=categories&amp;op=addedit'.$modlink, 'name=categories&amp;op=fix'.$modlink, 'name=categories&amp;op=info'.$modlink], 'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _INFO], 'sops' => ['', '', ''], 'stabs' => [_CATEGORY, _ACESS, _ACESSF], 'sub' => getCategoriesSearch($modul)]);
    echo $cont.$tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _INFOCATDEL]).$tpl->getHtmlFrag('open', []).'<div id="repajax_cat">'.ajax_cat($modul, 1).'</div>'.$tpl->getHtmlFrag('close', []);
    setFoot();
}

function fix(): void {
    global $db, $afile;
    $modul = getVar('req', 'modul', 'var', 'forum');
    $result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_categories WHERE modul = :modul ORDER BY ordern ASC', ['modul' => $modul]);
    $ordern = 0;
    while ([$id] = $db->getSqlRow($result)) {
        $ordern++;
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET ordern = :ordern WHERE id = :id', ['ordern' => $ordern, 'id' => $id]);
    }
    setRedirect($afile.'.php?name=categories&modul='.$modul);
}

function add(): void {
    global $db, $conf, $afile, $tpl;
    $modul = getVar('get', 'modul', 'var', 'forum');
    $modlink = '&amp;modul='.$modul;
    $path = 'templates/'.$conf['theme'].'/images/categories/';
    setHead();
    $cont = setAdminNavi(['ops' => ['name=categories'.$modlink, 'name=categories&amp;op=add'.$modlink, 'name=categories&amp;op=subadd'.$modlink, 'name=categories&amp;op=addedit'.$modlink, 'name=categories&amp;op=fix'.$modlink, 'name=categories&amp;op=info'.$modlink], 'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _INFO], 'sops' => ['', '', ''], 'stabs' => [_CATEGORY, _ACESS, _ACESSF], 'tab' => 1, 'subtab' => 1, 'sub' => getCategoriesSearch($modul), 'id' => 'add']);
    $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _CACESSI]);
    $cont .= $tpl->getHtmlFrag('open', []);
    $cont .= '<form name="post" action="'.$afile.'.php" method="post">'
    .'<div id="tabcs0" class="tabcont">'
    .'<table class="sl_table_form">'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._DESCRIPTION.':</td><td><textarea name="description" cols="65" rows="5" class="sl_form" placeholder="'._DESCRIPTION.'"></textarea></td></tr>';
    if ($conf['multilingual'] == 1) $cont .= '<tr><td>'._LANGUAGE.':</td><td><select name="lang" class="sl_form">'.language().'</select></td></tr>';
    $cont .= '<tr><td>'._MODUL.':</td><td>'.cat_modul('modul', 'sl_form', $modul).'</td></tr>'
    .'<tr><td>'._IMG.':</td><td><select name="imgcat" id="img_replace" class="sl_form">'
    .'<option value="'.$path.'no.png">'._NO.'</option>';
    $files = scandir($path);
    $conts = [];
    foreach ($files as $entry) {
        if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg)$/is', $entry) && $entry != 'no.png') $conts[] = '<option value="'.$path.$entry.'">'.$entry.'</option>';
    }
    asort($conts);
    $cont .= implode('', $conts).'</select></td></tr>'
    .'<tr><td>'._PREVIEW.':</td><td><img src="'.$path.'no.png" id="picture" alt="'._IMG.'"></td></tr>'
    .'<tr><td>'._ACTIVATE2.'</td><td>'.radio_form('', 'status').'</td></tr></table>'
    .'</div>'
    .'<div id="tabcs1" class="tabcont">'
    .'<table class="sl_table_form">'
    .'<tr><td>'._CAN.' '._AUTH_VIEW.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('pview', 'sl_form', '', 0).'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_READ.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('pread', 'sl_form', '', 0).'</td></tr></table>'
    .'</div>'
    .'<div id="tabcs2" class="tabcont">'
    .'<table class="sl_table_form">'
    .'<tr><td>'._CAN.' '._AUTH_POST.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('ppost', 'sl_form', '', 0).'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_REPLY.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('preply', 'sl_form', '', 0).'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_EDIT.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('pedit', 'sl_form', '', 1).'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_DELETE.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('pdelete', 'sl_form', '', 1).'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_MOD.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('pmod', 'sl_form', '', 2).'</td></tr></table>'
    .'</div>'
    .'<script>
        var countries=new ddtabcontent("adds")
        countries.setpersist(true)
        countries.setselectedClassTarget("link")
        countries.init()
    </script>'
    .'<table class="sl_table_form"><tr><td class="sl_center"><input type="hidden" name="name" value="categories"><input type="hidden" name="op" value="addsave"><input type="submit" value="'._ADD.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= $tpl->getHtmlFrag('close', []);
    echo $cont;
    setFoot();
}
    
function subadd(): void {
    global $db, $conf, $afile, $tpl;
    $modul = getVar('get', 'modul', 'var', 'forum');
    $modlink = '&amp;modul='.$modul;
    $path = 'templates/'.$conf['theme'].'/images/categories/';
    setHead();
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_categories WHERE modul = :modul', ['modul' => $modul])) > 0) {
        $cont = setAdminNavi(['ops' => ['name=categories'.$modlink, 'name=categories&amp;op=add'.$modlink, 'name=categories&amp;op=subadd'.$modlink, 'name=categories&amp;op=addedit'.$modlink, 'name=categories&amp;op=fix'.$modlink, 'name=categories&amp;op=info'.$modlink], 'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _INFO], 'sops' => ['', '', ''], 'stabs' => [_CATEGORY, _ACESS, _ACESSF], 'tab' => 2, 'subtab' => 1, 'sub' => getCategoriesSearch($modul), 'id' => 'subadd']);
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _CACESSI]);
        $cont .= $tpl->getHtmlFrag('open', []);
        $cont .= '<form name="post2" action="'.$afile.'.php" method="post">'
        .'<div id="tabcs0" class="tabcont">'
        .'<table class="sl_table_form">'
        .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" maxlength="100" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
        .'<tr><td>'._DESCRIPTION.':</td><td><textarea name="description" cols="65" rows="5" class="sl_form" placeholder="'._DESCRIPTION.'"></textarea></td></tr>';
        if ($conf['multilingual'] == 1) $cont .= '<tr><td>'._LANGUAGE.':</td><td><select name="lang" class="sl_form">'.language().'</select></td></tr>';
        $cont .= '<tr><td>'._MODUL.':</td><td>'.cat_modul('modul', 'sl_form', $modul).'</td></tr>'
        .'<tr><td>'._CATEGORY.':</td><td>'.getcat($modul, 0, 'cid', 'sl_form').'</td></tr>'
        .'<tr><td>'._IMG.':</td><td><select name="imgcat" id="img_replace" class="sl_form">'
        .'<option value="'.$path.'no.png">'._NO.'</option>';
        $files = scandir($path);
        $conts = [];
        foreach ($files as $entry) {
            if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg)$/is', $entry) && $entry != 'no.png') $conts[] = '<option value="'.$path.$entry.'">'.$entry.'</option>';
        }
        asort($conts);
        $cont .= implode('', $conts).'</select></td></tr>'
        .'<tr><td>'._PREVIEW.':</td><td><img src="'.$path.'no.png" id="picture" alt="'._IMG.'"></td></tr>'
        .'<tr><td>'._ACTIVATE2.'</td><td>'.radio_form('', 'status').'</td></tr></table>'
        .'</div>'
        .'<div id="tabcs1" class="tabcont">'
        .'<table class="sl_table_form">'
        .'<tr><td>'._CAN.' '._AUTH_VIEW.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('pview', 'sl_form', '', 0).'</td></tr>'
        .'<tr><td>'._CAN.' '._AUTH_READ.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('pread', 'sl_form', '', 0).'</td></tr></table>'
        .'</div>'
        .'<div id="tabcs2" class="tabcont">'
        .'<table class="sl_table_form">'
        .'<tr><td>'._CAN.' '._AUTH_POST.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('ppost', 'sl_form', '', 0).'</td></tr>'
        .'<tr><td>'._CAN.' '._AUTH_REPLY.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('preply', 'sl_form', '', 0).'</td></tr>'
        .'<tr><td>'._CAN.' '._AUTH_EDIT.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('pedit', 'sl_form', '', 1).'</td></tr>'
        .'<tr><td>'._CAN.' '._AUTH_DELETE.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('pdelete', 'sl_form', '', 1).'</td></tr>'
        .'<tr><td>'._CAN.' '._AUTH_MOD.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('pmod', 'sl_form', '', 2).'</td></tr></table>'
        .'</div>'
        .'<script>
            var countries=new ddtabcontent("subadds")
            countries.setpersist(true)
            countries.setselectedClassTarget("link")
            countries.init()
        </script>'
        .'<table class="sl_table_form"><tr><td class="sl_center"><input type="hidden" name="name" value="categories"><input type="hidden" name="op" value="addsave"><input type="submit" value="'._ADD.'" class="sl_but_blue"></td></tr></table></form>';
        $cont .= $tpl->getHtmlFrag('close', []);
    } else {
        $navi = setAdminNavi(['ops' => ['name=categories'.$modlink, 'name=categories&amp;op=add'.$modlink, 'name=categories&amp;op=subadd'.$modlink, 'name=categories&amp;op=addedit'.$modlink, 'name=categories&amp;op=fix'.$modlink, 'name=categories&amp;op=info'.$modlink], 'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _INFO], 'sops' => ['', '', ''], 'stabs' => [_CATEGORY, _ACESS, _ACESSF], 'tab' => 2, 'sub' => getCategoriesSearch($modul)]);
        $cont = $navi.$tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => sprintf(_ERROR_SUBCAT, getModuleName($modul))]);
    }
    echo $cont;
    setFoot();
}

function addedit(): void {
    global $db, $afile, $tpl;
    $modul = getVar('get', 'modul', 'var', 'forum');
    $modlink = '&amp;modul='.$modul;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=categories'.$modlink, 'name=categories&amp;op=add'.$modlink, 'name=categories&amp;op=subadd'.$modlink, 'name=categories&amp;op=addedit'.$modlink, 'name=categories&amp;op=fix'.$modlink, 'name=categories&amp;op=info'.$modlink], 'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _INFO], 'sops' => ['', '', ''], 'stabs' => [_CATEGORY, _ACESS, _ACESSF], 'tab' => 3, 'sub' => getCategoriesSearch($modul)]);
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_categories WHERE modul = :modul', ['modul' => $modul])) > 0) {
        $cont .= $tpl->getHtmlFrag('open', []);
        $cont .= '<table class="sl_table_form"><form action="'.$afile.'.php" method="post">'
        .'<tr><td>'._CATEGORY.':</td><td>'.getcat($modul, 0, 'cid', 'sl_form').'</td></tr>'
        .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="categories"><input type="hidden" name="op" value="edit"><input type="submit" value="'._EDIT.'" class="sl_but_blue"></td></tr></form></table>';
        $cont .= $tpl->getHtmlFrag('close', []);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => sprintf(_ERROR_SUBCAT, getModuleName($modul))]);
    }
    echo $cont;
    setFoot();
}

function edit(): void {
    global $db, $conf, $afile, $tpl;
    $cid = getVar('req', 'cid', 'num');
    $path = 'templates/'.$conf['theme'].'/images/categories/';
    $result = $db->getSqlQuery('SELECT modul, title, intro, img, lang, parent, status, pview, pread, ppost, preply, pedit, pdelete, pmod FROM '.PREFIX_DB.'_categories WHERE id = :cid', ['cid' => $cid]);
    [$modul, $title, $description, $imgcat, $lang, $parent, $status, $pview, $pread, $ppost, $preply, $pedit, $pdelete, $pmod] = $db->getSqlRow($result);
    $modlink = '&amp;modul='.$modul;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=categories'.$modlink, 'name=categories&amp;op=add'.$modlink, 'name=categories&amp;op=subadd'.$modlink, 'name=categories&amp;op=addedit'.$modlink, 'name=categories&amp;op=fix'.$modlink, 'name=categories&amp;op=info'.$modlink], 'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _INFO], 'sops' => ['', '', ''], 'stabs' => [_CATEGORY, _ACESS, _ACESSF], 'tab' => 3, 'subtab' => 1, 'sub' => getCategoriesSearch($modul), 'id' => 'edit']);
    $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _CACESSI]);
    $cont .= $tpl->getHtmlFrag('open', []);
    $cont .= '<form name="post" action="'.$afile.'.php" method="post">'
    .'<div id="tabcs0" class="tabcont">'
    .'<table class="sl_table_form">'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.$title.'" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._DESCRIPTION.':</td><td><textarea name="description" cols="65" rows="5" class="sl_form" placeholder="'._DESCRIPTION.'">'.$description.'</textarea></td></tr>'
    .'<tr><td>'._MODUL.':</td><td>'.cat_modul('modul', 'sl_form', $modul).'</td></tr>';
    if ($conf['multilingual'] == 1) $cont .= '<tr><td>'._LANGUAGE.':</td><td><select name="lang" class="sl_form">'.language($lang).'</select></td></tr>';
    if ($parent != 0) {
        $cont .= '<tr><td>'._CATEGORY.':</td><td>'.getcat($modul, $parent, 'parent', 'sl_form').'</td></tr>';
    } else {
        $cont .= '<input type="hidden" name="parent" value="0">';
    }
    $cont .= '<tr><td>'._IMG.':</td><td><select name="imgcat" id="img_replace" class="sl_form">'
    .'<option value="'.$path.'no.png">'._NO.'</option>';
    $files = scandir($path);
    $conts = [];
    foreach ($files as $entry) {
        if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg)$/is', $entry) && $entry != 'no.png') {
            $sel = ($imgcat == $entry) ? ' selected' : '';
            $conts[] = '<option value="'.$path.$entry.'"'.$sel.'>'.$entry.'</option>';
        }
    }
    $imgcat = (!$imgcat) ? 'no.png' : $imgcat;
    asort($conts);
    $cont .= implode('', $conts).'</select></td></tr>'
    .'<tr><td>'._PREVIEW.':</td><td><img src="'.$path.$imgcat.'" id="picture" alt="'._IMG.'"></td></tr>'
    .'<tr><td>'._ACTIVATE2.'</td><td>'.radio_form($status, 'status').'</td></tr></table>'
    .'</div>'
    .'<div id="tabcs1" class="tabcont">'
    .'<table class="sl_table_form">'
    .'<tr><td>'._CAN.' '._AUTH_VIEW.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('pview', 'sl_form', $pview, 0).'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_READ.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('pread', 'sl_form', $pread, 0).'</td></tr></table>'
    .'</div>'
    .'<div id="tabcs2" class="tabcont">'
    .'<table class="sl_table_form">'
    .'<tr><td>'._CAN.' '._AUTH_POST.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('ppost', 'sl_form', $ppost, 0).'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_REPLY.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('preply', 'sl_form', $preply, 0).'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_EDIT.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('pedit', 'sl_form', $pedit, 1).'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_DELETE.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('pdelete', 'sl_form', $pdelete, 1).'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_MOD.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('pmod', 'sl_form', $pmod, 2).'</td></tr></table>'
    .'</div>'
    .'<script>
        var countries=new ddtabcontent("edits")
        countries.setpersist(true)
        countries.setselectedClassTarget("link")
        countries.init()
    </script>'
    .'<table class="sl_table_form"><tr><td class="sl_center"><input type="hidden" name="id" value="'.$cid.'"><input type="hidden" name="name" value="categories"><input type="hidden" name="op" value="save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= $tpl->getHtmlFrag('close', []);
    echo $cont;
    setFoot();
}

function addsave(): void {
    global $db, $conf, $afile;
    $modul = getVar('post', 'modul', 'var');
    $title = getVar('post', 'title', 'title');
    $description = getVar('post', 'description', 'text');
    $imgcat = getVar('post', 'imgcat', 'var');
    $lang = getVar('post', 'lang', 'var');
    $cid = getVar('post', 'cid', 'num', 0);
    $imgcat = str_replace('templates/'.$conf['theme'].'/images/categories/', '', $imgcat);
    $imgcat = (!$imgcat || $imgcat == 'no.png') ? '' : $imgcat;
    $status = getVar('post', 'status', 'num');
    [$ordern] = $db->getSqlRow($db->getSqlQuery('SELECT ordern FROM '.PREFIX_DB.'_categories WHERE modul = :modul ORDER BY ordern DESC', ['modul' => $modul]));
    $ordern++;
    $pview_raw = getVar('post', 'pview[]', 'var', []);
    $pread_raw = getVar('post', 'pread[]', 'var', []);
    $ppost_raw = getVar('post', 'ppost[]', 'var', []);
    $preply_raw = getVar('post', 'preply[]', 'var', []);
    $pedit_raw = getVar('post', 'pedit[]', 'var', []);
    $pdelete_raw = getVar('post', 'pdelete[]', 'var', []);
    $pmod_raw = getVar('post', 'pmod[]', 'var', []);
    $pview = (is_array($pview_raw) && $pview_raw) ? scatacess($pview_raw) : '0|0';
    $pread = (is_array($pread_raw) && $pread_raw) ? scatacess($pread_raw) : '0|0';
    $ppost = (is_array($ppost_raw) && $ppost_raw) ? scatacess($ppost_raw) : '0|0';
    $preply = (is_array($preply_raw) && $preply_raw) ? scatacess($preply_raw) : '0|0';
    $pedit = (is_array($pedit_raw) && $pedit_raw) ? scatacess($pedit_raw) : '3|0';
    $pdelete = (is_array($pdelete_raw) && $pdelete_raw) ? scatacess($pdelete_raw) : '3|0';
    $pmod = (is_array($pmod_raw) && $pmod_raw) ? scatacess($pmod_raw) : '3|0';
    $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_categories (id, modul, title, intro, img, lang, parent, status, ordern, pview, pread, ppost, preply, pedit, pdelete, pmod) VALUES (NULL, :modul, :title, :intro, :img, :lang, :parent, :status, :ordern, :pview, :pread, :ppost, :preply, :pedit, :pdelete, :pmod)', [
        'modul' => $modul, 'title' => $title, 'intro' => $description, 'img' => $imgcat, 'lang' => $lang, 'parent' => $cid, 'status' => $status, 'ordern' => $ordern, 'pview' => $pview, 'pread' => $pread, 'ppost' => $ppost, 'preply' => $preply, 'pedit' => $pedit, 'pdelete' => $pdelete, 'pmod' => $pmod
    ]);
    setRedirect($afile.'.php?name=categories&modul='.$modul);
}

function save(): void {
    global $db, $conf, $afile;
    $id = getVar('post', 'id', 'num');
    $modul = getVar('post', 'modul', 'var');
    $title = getVar('post', 'title', 'title');
    $description = getVar('post', 'description', 'text');
    $imgcat = getVar('post', 'imgcat', 'var');
    $lang = getVar('post', 'lang', 'var');
    $parent = getVar('post', 'parent', 'num');
    $imgcat = str_replace('templates/'.$conf['theme'].'/images/categories/', '', $imgcat);
    $imgcat = (!$imgcat || $imgcat == 'no.png') ? '' : $imgcat;
    $status = getVar('post', 'status', 'num');
    $pview_raw = getVar('post', 'pview[]', 'var', []);
    $pread_raw = getVar('post', 'pread[]', 'var', []);
    $ppost_raw = getVar('post', 'ppost[]', 'var', []);
    $preply_raw = getVar('post', 'preply[]', 'var', []);
    $pedit_raw = getVar('post', 'pedit[]', 'var', []);
    $pdelete_raw = getVar('post', 'pdelete[]', 'var', []);
    $pmod_raw = getVar('post', 'pmod[]', 'var', []);
    $pview = (is_array($pview_raw) && $pview_raw) ? scatacess($pview_raw) : '0|0';
    $pread = (is_array($pread_raw) && $pread_raw) ? scatacess($pread_raw) : '0|0';
    $ppost = (is_array($ppost_raw) && $ppost_raw) ? scatacess($ppost_raw) : '0|0';
    $preply = (is_array($preply_raw) && $preply_raw) ? scatacess($preply_raw) : '0|0';
    $pedit = (is_array($pedit_raw) && $pedit_raw) ? scatacess($pedit_raw) : '3|0';
    $pdelete = (is_array($pdelete_raw) && $pdelete_raw) ? scatacess($pdelete_raw) : '3|0';
    $pmod = (is_array($pmod_raw) && $pmod_raw) ? scatacess($pmod_raw) : '3|0';
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET modul = :modul, title = :title, intro = :intro, img = :img, lang = :lang, parent = :parent, status = :status, pview = :pview, pread = :pread, ppost = :ppost, preply = :preply, pedit = :pedit, pdelete = :pdelete, pmod = :pmod WHERE id = :id', [
        'modul' => $modul, 'title' => $title, 'intro' => $description, 'img' => $imgcat, 'lang' => $lang, 'parent' => $parent, 'status' => $status, 'pview' => $pview, 'pread' => $pread, 'ppost' => $ppost, 'preply' => $preply, 'pedit' => $pedit, 'pdelete' => $pdelete, 'pmod' => $pmod, 'id' => $id
    ]);
    setRedirect($afile.'.php?name=categories&modul='.$modul);
}

function delete(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_categories WHERE id = :id', ['id' => $id]);
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_categories WHERE parent = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=categories', true);
}

function info(): void {
    $modul = getVar('req', 'modul', 'var', 'forum');
    $modlink = '&amp;modul='.$modul;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=categories'.$modlink, 'name=categories&amp;op=add'.$modlink, 'name=categories&amp;op=subadd'.$modlink, 'name=categories&amp;op=addedit'.$modlink, 'name=categories&amp;op=fix'.$modlink, 'name=categories&amp;op=info'.$modlink], 'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _INFO], 'sops' => ['', '', ''], 'stabs' => [_CATEGORY, _ACESS, _ACESSF], 'tab' => 5, 'sub' => getCategoriesSearch($modul)]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: categories(); break;
    case 'fix': fix(); break;
    case 'add': add(); break;
    case 'subadd': subadd(); break;
    case 'addedit': addedit(); break;
    case 'addsave': addsave(); break;
    case 'edit': edit(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
