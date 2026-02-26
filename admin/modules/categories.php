<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0, string $id = ''): string {
    global $afile;
    $modul = getVar('req', 'modul', 'var', 'forum');
    $modlink = '&amp;modul='.$modul;
    $ops = ['name=categories'.$modlink, 'name=categories&amp;op=add'.$modlink, 'name=categories&amp;op=subadd'.$modlink, 'name=categories&amp;op=addedit'.$modlink, 'name=categories&amp;op=fix'.$modlink, 'name=categories&amp;op=info'.$modlink];
    $lang = [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _INFO];
    $sops = ['', '', ''];
    $slang = [_CATEGORY, _ACESS, _ACESSF];
    $search = setTemplateBasic('searchbox', ['{%searchbox%}' => '<form method="post" action="'.$afile.'.php"><input type="hidden" name="name" value="categories">'._MODUL.': '.cat_modul('modul', '', $modul, 1).'</form>']);
    return getAdminTabs(_CATEGORIES, 'categories.png', $search, $ops, $lang, $sops, $slang, $tab, $subtab, $legacy, $id);
}

function categories(): void {
    $modul = getVar('req', 'modul', 'var', 'forum');
    head();
    echo navi(0, 0, 0, 0).setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _INFOCATDEL]).setTemplateBasic('open').'<div id="repajax_cat">'.ajax_cat($modul, 1).'</div>'.setTemplateBasic('close');
    foot();
}

function fix(): void {
    global $db, $afile;
    $modul = getVar('req', 'modul', 'var', 'forum');
    $result = $db->sql_query('SELECT id FROM '.PREFIX_DB.'_categories WHERE modul = :modul ORDER BY ordern ASC', ['modul' => $modul]);
    $ordern = 0;
    while ([$id] = $db->sql_fetchrow($result)) {
        $ordern++;
        $db->sql_query('UPDATE '.PREFIX_DB.'_categories SET ordern = :ordern WHERE id = :id', ['ordern' => $ordern, 'id' => $id]);
    }
    setRedirect($afile.'.php?name=categories&modul='.$modul);
}

function add(): void {
    global $db, $conf, $afile;
    $modul = getVar('get', 'modul', 'var', 'forum');
    $path = 'templates/'.$conf['theme'].'/images/categories/';
    head();
    $cont = navi(0, 1, 1, 0, 'add');
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _CACESSI]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post">'
    .'<div id="tabcs0" class="tabcont">'
    .'<table class="sl_table_form">'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._DESCRIPTION.':</td><td><textarea name="description" cols="65" rows="5" class="sl_form" placeholder="'._DESCRIPTION.'"></textarea></td></tr>';
    if ($conf['multilingual'] == 1) $cont .= '<tr><td>'._LANGUAGE.':</td><td><select name="language" class="sl_form">'.language().'</select></td></tr>';
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
    .'<tr><td>'._ACTIVATE2.'</td><td>'.radio_form('', 'cstatus').'</td></tr></table>'
    .'</div>'
    .'<div id="tabcs1" class="tabcont">'
    .'<table class="sl_table_form">'
    .'<tr><td>'._CAN.' '._AUTH_VIEW.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_view', 'sl_form', '', '').'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_READ.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_read', 'sl_form', '', '').'</td></tr></table>'
    .'</div>'
    .'<div id="tabcs2" class="tabcont">'
    .'<table class="sl_table_form">'
    .'<tr><td>'._CAN.' '._AUTH_POST.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_post', 'sl_form', '', '').'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_REPLY.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_reply', 'sl_form', '', '').'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_EDIT.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_edit', 'sl_form', '', 1).'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_DELETE.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_delete', 'sl_form', '', 1).'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_MOD.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_mod', 'sl_form', '', 2).'</td></tr></table>'
    .'</div>'
    .'<script>
        var countries=new ddtabcontent("adds")
        countries.setpersist(true)
        countries.setselectedClassTarget("link")
        countries.init()
    </script>'
    .'<table class="sl_table_form"><tr><td class="sl_center"><input type="hidden" name="name" value="categories"><input type="hidden" name="op" value="addsave"><input type="submit" value="'._ADD.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}
    
function subadd(): void {
    global $db, $conf, $afile;
    $modul = getVar('get', 'modul', 'var', 'forum');
    $path = 'templates/'.$conf['theme'].'/images/categories/';
    head();
    if ($db->sql_numrows($db->sql_query('SELECT * FROM '.PREFIX_DB.'_categories WHERE modul = :modul', ['modul' => $modul])) > 0) {
        $cont = navi(0, 2, 1, 0, 'subadd');
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _CACESSI]);
        $cont .= setTemplateBasic('open');
        $cont .= '<form name="post2" action="'.$afile.'.php" method="post">'
        .'<div id="tabcs0" class="tabcont">'
        .'<table class="sl_table_form">'
        .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" maxlength="100" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
        .'<tr><td>'._DESCRIPTION.':</td><td><textarea name="description" cols="65" rows="5" class="sl_form" placeholder="'._DESCRIPTION.'"></textarea></td></tr>';
        if ($conf['multilingual'] == 1) $cont .= '<tr><td>'._LANGUAGE.':</td><td><select name="language" class="sl_form">'.language().'</select></td></tr>';
        $cont .= '<tr><td>'._MODUL.':</td><td>'.cat_modul('modul', 'sl_form', $modul).'</td></tr>'
        .'<tr><td>'._CATEGORY.':</td><td>'.getcat($modul, '', 'cid', 'sl_form').'</td></tr>'
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
        .'<tr><td>'._ACTIVATE2.'</td><td>'.radio_form('', 'cstatus').'</td></tr></table>'
        .'</div>'
        .'<div id="tabcs1" class="tabcont">'
        .'<table class="sl_table_form">'
        .'<tr><td>'._CAN.' '._AUTH_VIEW.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_view', 'sl_form', '', '').'</td></tr>'
        .'<tr><td>'._CAN.' '._AUTH_READ.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_read', 'sl_form', '', '').'</td></tr></table>'
        .'</div>'
        .'<div id="tabcs2" class="tabcont">'
        .'<table class="sl_table_form">'
        .'<tr><td>'._CAN.' '._AUTH_POST.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_post', 'sl_form', '', '').'</td></tr>'
        .'<tr><td>'._CAN.' '._AUTH_REPLY.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_reply', 'sl_form', '', '').'</td></tr>'
        .'<tr><td>'._CAN.' '._AUTH_EDIT.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_edit', 'sl_form', '', 1).'</td></tr>'
        .'<tr><td>'._CAN.' '._AUTH_DELETE.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_delete', 'sl_form', '', 1).'</td></tr>'
        .'<tr><td>'._CAN.' '._AUTH_MOD.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_mod', 'sl_form', '', 2).'</td></tr></table>'
        .'</div>'
        .'<script>
            var countries=new ddtabcontent("subadds")
            countries.setpersist(true)
            countries.setselectedClassTarget("link")
            countries.init()
        </script>'
        .'<table class="sl_table_form"><tr><td class="sl_center"><input type="hidden" name="name" value="categories"><input type="hidden" name="op" value="addsave"><input type="submit" value="'._ADD.'" class="sl_but_blue"></td></tr></table></form>';
        $cont .= setTemplateBasic('close');
    } else {
        $cont = navi(0, 2, 0, 0);
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => sprintf(_ERROR_SUBCAT, deflmconst($modul))]);
    }
    echo $cont;
    foot();
}

function addedit(): void {
    global $db, $afile;
    $modul = getVar('get', 'modul', 'var', 'forum');
    head();
    $cont = navi(0, 3, 0, 0);
    if ($db->sql_numrows($db->sql_query('SELECT * FROM '.PREFIX_DB.'_categories WHERE modul = :modul', ['modul' => $modul])) > 0) {
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_form"><form action="'.$afile.'.php" method="post">'
        .'<tr><td>'._CATEGORY.':</td><td>'.getcat($modul, '', 'cid', 'sl_form').'</td></tr>'
        .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="categories"><input type="hidden" name="op" value="edit"><input type="submit" value="'._EDIT.'" class="sl_but_blue"></td></tr></form></table>';
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => sprintf(_ERROR_SUBCAT, deflmconst($modul))]);
    }
    echo $cont;
    foot();
}

function edit(): void {
    global $db, $conf, $afile;
    $cid = getVar('req', 'cid', 'num');
    $path = 'templates/'.$conf['theme'].'/images/categories/';
    $result = $db->sql_query('SELECT modul, title, description, img, language, parentid, cstatus, auth_view, auth_read, auth_post, auth_reply, auth_edit, auth_delete, auth_mod FROM '.PREFIX_DB.'_categories WHERE id = :cid', ['cid' => $cid]);
    [$modul, $title, $description, $imgcat, $language, $parentid, $cstatus, $auth_view, $auth_read, $auth_post, $auth_reply, $auth_edit, $auth_delete, $auth_mod] = $db->sql_fetchrow($result);
    head();
    $cont = navi(0, 3, 1, 0, 'edit');
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _CACESSI]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post">'
    .'<div id="tabcs0" class="tabcont">'
    .'<table class="sl_table_form">'
    .'<tr><td>'._TITLE.':</td><td><input type="text" name="title" value="'.$title.'" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._DESCRIPTION.':</td><td><textarea name="description" cols="65" rows="5" class="sl_form" placeholder="'._DESCRIPTION.'">'.$description.'</textarea></td></tr>'
    .'<tr><td>'._MODUL.':</td><td>'.cat_modul('modul', 'sl_form', $modul).'</td></tr>';
    if ($conf['multilingual'] == 1) $cont .= '<tr><td>'._LANGUAGE.':</td><td><select name="language" class="sl_form">'.language($language).'</select></td></tr>';
    if ($parentid != 0) {
        $cont .= '<tr><td>'._CATEGORY.':</td><td>'.getcat($modul, $parentid, 'parentid', 'sl_form').'</td></tr>';
    } else {
        $cont .= '<input type="hidden" name="parentid" value="0">';
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
    .'<tr><td>'._ACTIVATE2.'</td><td>'.radio_form($cstatus, 'cstatus').'</td></tr></table>'
    .'</div>'
    .'<div id="tabcs1" class="tabcont">'
    .'<table class="sl_table_form">'
    .'<tr><td>'._CAN.' '._AUTH_VIEW.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_view', 'sl_form', $auth_view, '').'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_READ.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_read', 'sl_form', $auth_read, '').'</td></tr></table>'
    .'</div>'
    .'<div id="tabcs2" class="tabcont">'
    .'<table class="sl_table_form">'
    .'<tr><td>'._CAN.' '._AUTH_POST.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_post', 'sl_form', $auth_post, '').'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_REPLY.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_reply', 'sl_form', $auth_reply, '').'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_EDIT.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_edit', 'sl_form', $auth_edit, 1).'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_DELETE.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_delete', 'sl_form', $auth_delete, 1).'</td></tr>'
    .'<tr><td>'._CAN.' '._AUTH_MOD.':<div class="sl_small">'._ACESSI.' '._CTRLINFO.'</div></td><td>'.catacess('auth_mod', 'sl_form', $auth_mod, 2).'</td></tr></table>'
    .'</div>'
    .'<script>
        var countries=new ddtabcontent("edits")
        countries.setpersist(true)
        countries.setselectedClassTarget("link")
        countries.init()
    </script>'
    .'<table class="sl_table_form"><tr><td class="sl_center"><input type="hidden" name="id" value="'.$cid.'"><input type="hidden" name="name" value="categories"><input type="hidden" name="op" value="save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function addsave(): void {
    global $db, $conf, $afile;
    $modul = getVar('post', 'modul', 'var');
    $title = getVar('post', 'title', 'title');
    $description = getVar('post', 'description', 'text');
    $imgcat = getVar('post', 'imgcat', 'var');
    $language = getVar('post', 'language', 'var');
    $cid = getVar('post', 'cid', 'num', 0);
    $imgcat = str_replace('templates/'.$conf['theme'].'/images/categories/', '', $imgcat);
    $imgcat = (!$imgcat || $imgcat == 'no.png') ? '' : $imgcat;
    $cstatus = getVar('post', 'cstatus', 'num');
    [$ordern] = $db->sql_fetchrow($db->sql_query('SELECT ordern FROM '.PREFIX_DB.'_categories WHERE modul = :modul ORDER BY ordern DESC', ['modul' => $modul]));
    $ordern++;
    $auth_view_raw = getVar('post', 'auth_view[]', 'var', []);
    $auth_read_raw = getVar('post', 'auth_read[]', 'var', []);
    $auth_post_raw = getVar('post', 'auth_post[]', 'var', []);
    $auth_reply_raw = getVar('post', 'auth_reply[]', 'var', []);
    $auth_edit_raw = getVar('post', 'auth_edit[]', 'var', []);
    $auth_delete_raw = getVar('post', 'auth_delete[]', 'var', []);
    $auth_mod_raw = getVar('post', 'auth_mod[]', 'var', []);
    $auth_view = (is_array($auth_view_raw) && $auth_view_raw) ? scatacess($auth_view_raw) : '0|0';
    $auth_read = (is_array($auth_read_raw) && $auth_read_raw) ? scatacess($auth_read_raw) : '0|0';
    $auth_post = (is_array($auth_post_raw) && $auth_post_raw) ? scatacess($auth_post_raw) : '0|0';
    $auth_reply = (is_array($auth_reply_raw) && $auth_reply_raw) ? scatacess($auth_reply_raw) : '0|0';
    $auth_edit = (is_array($auth_edit_raw) && $auth_edit_raw) ? scatacess($auth_edit_raw) : '3|0';
    $auth_delete = (is_array($auth_delete_raw) && $auth_delete_raw) ? scatacess($auth_delete_raw) : '3|0';
    $auth_mod = (is_array($auth_mod_raw) && $auth_mod_raw) ? scatacess($auth_mod_raw) : '3|0';
    $db->sql_query('INSERT INTO '.PREFIX_DB.'_categories (id, modul, title, description, img, language, parentid, cstatus, ordern, auth_view, auth_read, auth_post, auth_reply, auth_edit, auth_delete, auth_mod) VALUES (NULL, :modul, :title, :description, :img, :language, :parentid, :cstatus, :ordern, :auth_view, :auth_read, :auth_post, :auth_reply, :auth_edit, :auth_delete, :auth_mod)', [
        'modul' => $modul, 'title' => $title, 'description' => $description, 'img' => $imgcat, 'language' => $language, 'parentid' => $cid, 'cstatus' => $cstatus, 'ordern' => $ordern, 'auth_view' => $auth_view, 'auth_read' => $auth_read, 'auth_post' => $auth_post, 'auth_reply' => $auth_reply, 'auth_edit' => $auth_edit, 'auth_delete' => $auth_delete, 'auth_mod' => $auth_mod
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
    $language = getVar('post', 'language', 'var');
    $parentid = getVar('post', 'parentid', 'num');
    $imgcat = str_replace('templates/'.$conf['theme'].'/images/categories/', '', $imgcat);
    $imgcat = (!$imgcat || $imgcat == 'no.png') ? '' : $imgcat;
    $cstatus = getVar('post', 'cstatus', 'num');
    $auth_view_raw = getVar('post', 'auth_view[]', 'var', []);
    $auth_read_raw = getVar('post', 'auth_read[]', 'var', []);
    $auth_post_raw = getVar('post', 'auth_post[]', 'var', []);
    $auth_reply_raw = getVar('post', 'auth_reply[]', 'var', []);
    $auth_edit_raw = getVar('post', 'auth_edit[]', 'var', []);
    $auth_delete_raw = getVar('post', 'auth_delete[]', 'var', []);
    $auth_mod_raw = getVar('post', 'auth_mod[]', 'var', []);
    $auth_view = (is_array($auth_view_raw) && $auth_view_raw) ? scatacess($auth_view_raw) : '0|0';
    $auth_read = (is_array($auth_read_raw) && $auth_read_raw) ? scatacess($auth_read_raw) : '0|0';
    $auth_post = (is_array($auth_post_raw) && $auth_post_raw) ? scatacess($auth_post_raw) : '0|0';
    $auth_reply = (is_array($auth_reply_raw) && $auth_reply_raw) ? scatacess($auth_reply_raw) : '0|0';
    $auth_edit = (is_array($auth_edit_raw) && $auth_edit_raw) ? scatacess($auth_edit_raw) : '3|0';
    $auth_delete = (is_array($auth_delete_raw) && $auth_delete_raw) ? scatacess($auth_delete_raw) : '3|0';
    $auth_mod = (is_array($auth_mod_raw) && $auth_mod_raw) ? scatacess($auth_mod_raw) : '3|0';
    $db->sql_query('UPDATE '.PREFIX_DB.'_categories SET modul = :modul, title = :title, description = :description, img = :img, language = :language, parentid = :parentid, cstatus = :cstatus, auth_view = :auth_view, auth_read = :auth_read, auth_post = :auth_post, auth_reply = :auth_reply, auth_edit = :auth_edit, auth_delete = :auth_delete, auth_mod = :auth_mod WHERE id = :id', [
        'modul' => $modul, 'title' => $title, 'description' => $description, 'img' => $imgcat, 'language' => $language, 'parentid' => $parentid, 'cstatus' => $cstatus, 'auth_view' => $auth_view, 'auth_read' => $auth_read, 'auth_post' => $auth_post, 'auth_reply' => $auth_reply, 'auth_edit' => $auth_edit, 'auth_delete' => $auth_delete, 'auth_mod' => $auth_mod, 'id' => $id
    ]);
    setRedirect($afile.'.php?name=categories&modul='.$modul);
}

function del(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    $db->sql_query('DELETE FROM '.PREFIX_DB.'_categories WHERE id = :id', ['id' => $id]);
    $db->sql_query('DELETE FROM '.PREFIX_DB.'_categories WHERE parentid = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=categories', true);
}

function info(): void {
    head();
    echo navi(0, 5, 0, 0).'<div id="repadm_info">'.adm_info(1, 0, 'categories').'</div>';
    foot();
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
    case 'del': del(); break;
    case 'info': info(); break;
}
