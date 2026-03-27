<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function categories(): void {
    global $tpl;
    $modul = getVar('req', 'modul', 'var', 'forum');
    $modlink = '&amp;modul='.$modul;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=categories'.$modlink, 'name=categories&amp;op=add'.$modlink, 'name=categories&amp;op=subadd'.$modlink, 'name=categories&amp;op=addedit'.$modlink, 'name=categories&amp;op=fix'.$modlink, 'name=categories&amp;op=info'.$modlink], 'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _INFO], 'sops' => ['', '', ''], 'stabs' => [_CATEGORY, _ACESS, _ACESSF], 'sub' => getCategoriesSearch($modul)]);
    echo $cont.$tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _INFOCATDEL]).getAdminPlaceholderBox('repajax_cat', ajax_cat($modul, 1));
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
    global $conf, $afile, $tpl;
    $modul = getVar('get', 'modul', 'var', 'forum');
    $modlink = '&amp;modul='.$modul;
    $path = 'templates/'.$conf['theme'].'/images/categories/';
    setHead();
    $cont = setAdminNavi(['ops' => ['name=categories'.$modlink, 'name=categories&amp;op=add'.$modlink, 'name=categories&amp;op=subadd'.$modlink, 'name=categories&amp;op=addedit'.$modlink, 'name=categories&amp;op=fix'.$modlink, 'name=categories&amp;op=info'.$modlink], 'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _INFO], 'sops' => ['', '', ''], 'stabs' => [_CATEGORY, _ACESS, _ACESSF], 'tab' => 1, 'subtab' => 1, 'sub' => getCategoriesSearch($modul), 'id' => 'add']);
    $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _CACESSI]);
    $hint = _ACESSI.' '._CTRLINFO;
    $rows0 = $tpl->getHtmlFrag('admin-categories-add-rows', [
        'activate_html' => radio_form('', 'status'),
        'activate_label' => _ACTIVATE2,
        'description_label' => _DESCRIPTION.':',
        'description_placeholder' => _DESCRIPTION,
        'img_html' => getCategoryImageSelect($path),
        'img_label' => _IMG.':',
        'lang_html' => $conf['multilingual'] == 1 ? getAdminFormRow(_LANGUAGE.':', getAdminSelect('lang', language(), 'sl_form')) : '',
        'modul_html' => cat_modul('modul', 'sl_form', $modul),
        'modul_label' => _MODUL.':',
        'preview_html' => getCategoryImgPreview($path.'no.png'),
        'preview_label' => _PREVIEW.':',
        'title_label' => _TITLE.':',
        'title_placeholder' => _TITLE,
    ]);
    $rows1 = getCatPermRow(_CAN.' '._AUTH_VIEW, $hint, catacess('pview', 'sl_form', '', 0));
    $rows1 .= getCatPermRow(_CAN.' '._AUTH_READ, $hint, catacess('pread', 'sl_form', '', 0));
    $rows2 = getCatPermRow(_CAN.' '._AUTH_POST, $hint, catacess('ppost', 'sl_form', '', 0));
    $rows2 .= getCatPermRow(_CAN.' '._AUTH_REPLY, $hint, catacess('preply', 'sl_form', '', 0));
    $rows2 .= getCatPermRow(_CAN.' '._AUTH_EDIT, $hint, catacess('pedit', 'sl_form', '', 1));
    $rows2 .= getCatPermRow(_CAN.' '._AUTH_DELETE, $hint, catacess('pdelete', 'sl_form', '', 1));
    $rows2 .= getCatPermRow(_CAN.' '._AUTH_MOD, $hint, catacess('pmod', 'sl_form', '', 2));
    $hide = '<input type="hidden" name="name" value="categories"><input type="hidden" name="op" value="addsave">';
    $formv = getCatForm('post',
        getCatTab('tabcs0', $rows0)
        .getCatTab('tabcs1', $rows1)
        .getCatTab('tabcs2', $rows2)
        .getCatTabScript('adds')
        .getCatSubmitRow($hide, _ADD)
    );
    echo $cont.getAdminBox($formv);
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
        $hint = _ACESSI.' '._CTRLINFO;
        $rows0 = $tpl->getHtmlFrag('admin-categories-subadd-rows', [
            'activate_html' => radio_form('', 'status'),
            'activate_label' => _ACTIVATE2,
            'category_html' => getcat($modul, 0, 'cid', 'sl_form'),
            'category_label' => _CATEGORY.':',
            'description_label' => _DESCRIPTION.':',
            'description_placeholder' => _DESCRIPTION,
            'img_html' => getCategoryImageSelect($path),
            'img_label' => _IMG.':',
            'lang_html' => $conf['multilingual'] == 1 ? getAdminFormRow(_LANGUAGE.':', getAdminSelect('lang', language(), 'sl_form')) : '',
            'modul_html' => cat_modul('modul', 'sl_form', $modul),
            'modul_label' => _MODUL.':',
            'preview_html' => getCategoryImgPreview($path.'no.png'),
            'preview_label' => _PREVIEW.':',
            'title_label' => _TITLE.':',
            'title_placeholder' => _TITLE,
        ]);
        $rows1 = getCatPermRow(_CAN.' '._AUTH_VIEW, $hint, catacess('pview', 'sl_form', '', 0));
        $rows1 .= getCatPermRow(_CAN.' '._AUTH_READ, $hint, catacess('pread', 'sl_form', '', 0));
        $rows2 = getCatPermRow(_CAN.' '._AUTH_POST, $hint, catacess('ppost', 'sl_form', '', 0));
        $rows2 .= getCatPermRow(_CAN.' '._AUTH_REPLY, $hint, catacess('preply', 'sl_form', '', 0));
        $rows2 .= getCatPermRow(_CAN.' '._AUTH_EDIT, $hint, catacess('pedit', 'sl_form', '', 1));
        $rows2 .= getCatPermRow(_CAN.' '._AUTH_DELETE, $hint, catacess('pdelete', 'sl_form', '', 1));
        $rows2 .= getCatPermRow(_CAN.' '._AUTH_MOD, $hint, catacess('pmod', 'sl_form', '', 2));
        $hide = '<input type="hidden" name="name" value="categories"><input type="hidden" name="op" value="addsave">';
        $formv = getCatForm('post2',
            getCatTab('tabcs0', $rows0)
            .getCatTab('tabcs1', $rows1)
            .getCatTab('tabcs2', $rows2)
            .getCatTabScript('subadds')
            .getCatSubmitRow($hide, _ADD)
        );
        $cont .= getAdminBox($formv);
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
        $hide = '<input type="hidden" name="name" value="categories"><input type="hidden" name="op" value="edit">';
        $rows = $tpl->getHtmlFrag('admin-categories-editpick-rows', [
            'category_html' => getcat($modul, 0, 'cid', 'sl_form'),
            'category_label' => _CATEGORY.':',
            'edit_label' => _EDIT,
        ]);
        $cont .= getAdminBox(getAdminForm($afile.'.php', $rows, $hide));
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
    [$modul, $title, $desc, $imgcat, $lang, $parent, $status, $pview, $pread, $ppost, $preply, $pedit, $pdelete, $pmod] = $db->getSqlRow($result);
    $modlink = '&amp;modul='.$modul;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=categories'.$modlink, 'name=categories&amp;op=add'.$modlink, 'name=categories&amp;op=subadd'.$modlink, 'name=categories&amp;op=addedit'.$modlink, 'name=categories&amp;op=fix'.$modlink, 'name=categories&amp;op=info'.$modlink], 'tabs' => [_HOME, _ADDCATEGORY, _ADDSUBCATEGORY, _EDIT, _FIX, _INFO], 'sops' => ['', '', ''], 'stabs' => [_CATEGORY, _ACESS, _ACESSF], 'tab' => 3, 'subtab' => 1, 'sub' => getCategoriesSearch($modul), 'id' => 'edit']);
    $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _CACESSI]);
    $hint = _ACESSI.' '._CTRLINFO;
    $imgcat = (!$imgcat) ? 'no.png' : $imgcat;
    $rows0 = $tpl->getHtmlFrag('admin-categories-edit-rows', [
        'activate_html' => radio_form($status, 'status'),
        'activate_label' => _ACTIVATE2,
        'category_html' => $parent != 0 ? getAdminFormRow(_CATEGORY.':', getcat($modul, $parent, 'parent', 'sl_form')) : '',
        'description_label' => _DESCRIPTION.':',
        'description_placeholder' => _DESCRIPTION,
        'description_value' => $desc,
        'hidden_parent_html' => $parent == 0 ? '<input type="hidden" name="parent" value="0">' : '',
        'img_html' => getCategoryImageSelect($path, $imgcat === 'no.png' ? '' : $imgcat),
        'img_label' => _IMG.':',
        'lang_html' => $conf['multilingual'] == 1 ? getAdminFormRow(_LANGUAGE.':', getAdminSelect('lang', language($lang), 'sl_form')) : '',
        'modul_html' => cat_modul('modul', 'sl_form', $modul),
        'modul_label' => _MODUL.':',
        'preview_html' => getCategoryImgPreview($path.$imgcat),
        'preview_label' => _PREVIEW.':',
        'title_label' => _TITLE.':',
        'title_placeholder' => _TITLE,
        'title_value' => $title,
    ]);
    $rows1 = getCatPermRow(_CAN.' '._AUTH_VIEW, $hint, catacess('pview', 'sl_form', $pview, 0));
    $rows1 .= getCatPermRow(_CAN.' '._AUTH_READ, $hint, catacess('pread', 'sl_form', $pread, 0));
    $rows2 = getCatPermRow(_CAN.' '._AUTH_POST, $hint, catacess('ppost', 'sl_form', $ppost, 0));
    $rows2 .= getCatPermRow(_CAN.' '._AUTH_REPLY, $hint, catacess('preply', 'sl_form', $preply, 0));
    $rows2 .= getCatPermRow(_CAN.' '._AUTH_EDIT, $hint, catacess('pedit', 'sl_form', $pedit, 1));
    $rows2 .= getCatPermRow(_CAN.' '._AUTH_DELETE, $hint, catacess('pdelete', 'sl_form', $pdelete, 1));
    $rows2 .= getCatPermRow(_CAN.' '._AUTH_MOD, $hint, catacess('pmod', 'sl_form', $pmod, 2));
    $hide = '<input type="hidden" name="id" value="'.$cid.'"><input type="hidden" name="name" value="categories"><input type="hidden" name="op" value="save">';
    $formv = getCatForm('post',
        getCatTab('tabcs0', $rows0)
        .getCatTab('tabcs1', $rows1)
        .getCatTab('tabcs2', $rows2)
        .getCatTabScript('edits')
        .getCatSubmitRow($hide, _SAVECHANGES)
    );
    echo $cont.getAdminBox($formv);
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
    echo $cont.getAdminInfoBox(getAdminInfo());
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

