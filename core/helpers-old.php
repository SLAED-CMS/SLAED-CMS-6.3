<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Resolve admin-only generic fragments from the shared fragments directory when available
function getTplFragmentName(string $name): string {
    if ($name === '') return $name;
    if (!defined('BASE_DIR')) return $name;
    $base = rtrim(str_replace('\\', '/', BASE_DIR), '/');
    $file = $base.'/templates/admin/fragments/'.$name.'.html';
    if (defined('ADMIN_FILE') && is_file($file)) return $name;
    return $name;
}

# DELETE Old
# Build one ajax query string from named params and skip empty values
function getAjaxQuery(array $data): string {
    $list = [];
    foreach ($data as $name => $valu) {
        if ($valu === '' || $valu === null) continue;
        $list[] = $name.'='.rawurlencode((string)$valu);
    }
    return implode('&amp;', $list);
}

# Render one shared admin select shell from prepared option markup
function getTplSelect(string $name, string $opts, string $clas = '', string $attr = ''): string {
    global $tpl;
    $selectAttr = trim(($clas !== '' ? 'class="'.htmlspecialchars($clas, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"' : '').($attr !== '' ? ' '.$attr : ''));
    return $tpl->getHtmlFrag('new/select', [
        'name_attr' => $name,
        'options_html' => $opts,
        'select_attr' => $selectAttr,
    ]);
}

# Render one shared admin option row with optional selected state
function getTplOption(string $valu, string $text, bool $isel = false): string {
    global $tpl;
    return $tpl->getHtmlFrag('new/select-option', [
        'is_selected' => $isel,
        'label_text' => $text,
        'value_attr' => $valu,
    ]);
}

# Render a safe HTMX GET action item for getTplMenuItems()
function getTplAjaxAction(string $target, string $query, string $title, string $label, string $clas = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('comment-action-ajax', [
        'target' => $target,
        'query'  => $query,
        'title'  => $title,
        'label'  => $label,
        'class'  => $clas,
    ]);
}


# Render one shared admin text input with optional extra class and extra attributes
function getTplTextInput(string $name, string $valu, string $clas = '', string $attr = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('new/input', [
        'input_attr' => $attr,
        'input_class' => $clas,
        'itype' => 'text',
        'name_attr' => $name,
        'value_attr' => $valu,
    ]);
}

# Render one shared admin email input with optional extra class and extra attributes
function getTplEmailInput(string $name, string $valu, string $clas = '', string $attr = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('new/input', [
        'input_attr' => $attr,
        'input_class' => $clas,
        'itype' => 'email',
        'name_attr' => $name,
        'value_attr' => $valu,
    ]);
}

# Render one shared frontend content card from one shared data cut
function getTplContentCard(array $data): string {
    global $tpl;
    return $tpl->getHtmlFrag('content-card', $data);
}

# Render one shared frontend content view from one shared data cut
function getTplContentView(array $data): string {
    global $tpl;
    return $tpl->getHtmlFrag('content-view', $data);
}

# Render one shared frontend form row from prepared label and field markup
function getTplFormAddRow(string $label, string $field): string {
    global $tpl;
    return $tpl->getHtmlFrag('form-add-row', [
        'field_html' => $field,
        'label_text' => $label,
    ]);
}

# Render one shared frontend submit block from prepared hidden fields

# Render one shared frontend select from prepared option/optgroup markup
function getTplFormSelect(string $name, string $opts, string $clas = '', string $attr = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('form-select', [
        'name_attr' => $name,
        'options_html' => $opts,
        'select_attr' => $attr,
        'select_class' => $clas,
    ]);
}

# Render a centered submit row inside a form table
function getTplFormCenterRow(string $content): string {
    global $tpl;
    return $tpl->getHtmlFrag('form-center-row', ['content_html' => $content]);
}

# Render one select option with optional selected state
function getTplSelectOption(string $value, string $label, bool $selected = false): string {
    global $tpl;
    return $tpl->getHtmlFrag('form-option', [
        'value'    => $value,
        'label'    => $label,
        'selected' => $selected ? ' selected' : '',
    ]);
}

# Render one forum topic icon link from href, topic title, icon class and optional status label
function getTplForumIcon(string $href, string $title, string $icon, string $lbl = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('forum-topic-icon', [
        'href' => $href,
        'icon' => $icon,
        'lbl' => $lbl ?: $title,
        'title' => $title,
    ]);
}

# Render the forum reply/add form wrapper from module name and prepared row markup
function getTplForumReplyForm(string $mod, string $rows): string {
    global $tpl;
    return $tpl->getHtmlFrag('forum-reply-form', [
        'mod_name' => $mod,
        'rows_html' => $rows,
    ]);
}

# Render one meta-refresh tag from a URL and optional delay in seconds
function getTplMetaRefresh(string $url, int $secs = 10): string {
    return '<meta http-equiv="refresh" content="'.(int)$secs.'; url='.htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
}

# Render a categories image select from a path and optional pre-selected filename
function getTplImageSelect(string $path, string $selected = ''): string {
    $files = is_dir($path) ? scandir($path) : [];
    $imgs  = [];
    foreach ($files as $entry) {
        if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg)$/is', $entry) && $entry !== 'no.png') {
            $imgs[] = getTplOption($path.$entry, $entry, $selected === $entry);
        }
    }
    asort($imgs);
    $opts = getTplOption($path.'no.png', _NO).implode('', $imgs);
    return getTplSelect('imgcat', $opts, '', 'id="img_replace"');
}

# Render a categories image preview tag from a full image path
function getTplCategoryPreview(string $src): string {
    global $tpl;
    return $tpl->getHtmlFrag('img-preview', [
        'alt' => _IMG,
        'src' => $src,
    ]);
}

# Join non-empty stop/error messages into a single HTML string separated by line breaks
function getStopText(array $stop): string {
    global $tpl;
    $sep = '<br>';
    return implode($sep, array_filter($stop, 'strlen'));
}


# Return an inline span with a CSS class and optional title attribute; raw_content is not escaped
function getTplSpan(string $class, string $raw_content, string $title = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('span-raw', ['class' => $class, 'content' => $raw_content, 'title' => $title]);
}

# Render a <link rel="stylesheet"> tag for an external CSS file
function getHtmlCssLink(string $href): string {
    return '<link rel="stylesheet" href="'.htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
}

# Render an inline <style> block
function getHtmlCssInline(string $css): string {
    return '<style type="text/css">'.$css.'</style>';
}

# Render a <script src="..."> tag, optionally with async attribute string ('async ' or '')
function getHtmlScriptSrc(string $src, string $async = ''): string {
    $attr = trim($async) !== '' ? ' '.trim($async) : '';
    return '<script'.$attr.' src="'.htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"></script>';
}

# Render an inline <script> block
function getHtmlScriptInline(string $js): string {
    return '<script>'.$js.'</script>';
}

# Render a generic <link> head tag with optional type and title attributes
function getHtmlHeadLink(string $rel, string $href, string $type = '', string $title = ''): string {
    $html = '<link rel="'.htmlspecialchars($rel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
    if ($type !== '') $html .= ' type="'.htmlspecialchars($type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
    if ($title !== '') $html .= ' title="'.htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
    return $html.' href="'.htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
}

# Render a safe link action item for getTplMenuItems()
function getTplLinkAction(string $href, string $title, string $label): string {
    global $tpl;
    return $tpl->getHtmlFrag('comment-action-link', [
        'href' => $href,
        'title' => $title,
        'label' => $label,
        'class' => '',
        'target' => '',
    ]);
}

# Render a safe delete action item with JS confirm for getTplMenuItems()
function getTplDeleteAction(string $href, string $confirmText, string $title, string $label): string {
    global $tpl;
    return $tpl->getHtmlFrag('action-delete', [
        'href' => $href,
        'confirm_text' => $confirmText,
        'title' => $title,
        'label' => $label,
    ]);
}

# Render a safe external (new window) link action item for getTplMenuItems()
function getTplExternalAction(string $href, string $title, string $label): string {
    global $tpl;
    return $tpl->getHtmlFrag('comment-action-link', [
        'href' => $href,
        'title' => $title,
        'label' => $label,
        'class' => '',
        'target' => ' target="_blank"',
    ]);
}

# Render one action-menu item wrapper from prepared action markup
function getTplMenuItem(string $item): string {
    global $tpl;
    return $tpl->getHtmlFrag('action-menu-item', [
        'item_html' => $item,
    ]);
}

# Render an editor action dropdown menu from an array of item HTML strings
function getTplMenuItems(array $items): string {
    global $tpl;
    $items = array_values(array_filter($items, static fn($item) => $item !== ''));
    if (!$items) return '';
    return $tpl->getHtmlFrag('editor-action-menu', [
        'editor_label' => _EDITOR,
        'items_html' => implode('', array_map(static fn($item) => getTplMenuItem($item), $items)),
    ]);
}


# Build module navigation — defaults from $conf[$conf['name']], any $p key overrides
function setModuleNavi(array $p): string {
    global $conf, $tpl;
    $mconf = $conf[$conf['name']] ?? [];
    $cat = getVar('get', 'cat', 'num');
    $cpar = $cat ? ['cat' => $cat] : [];
    $title = $p['title'] ?? '';
    $htitle = $p['htitle'] ?? $title;
    $bop = $p['bop'] ?? 'best';
    $always = $p['always'] ?? false;
    $addquest = $p['addquest'] ?? true;
    $showrate = $always || !empty($mconf['rate']);
    $canadd = (is_user() && ($mconf['add'] ?? 0) == 1)
           || (!is_user() && $addquest && ($mconf['addquest'] ?? 0) == 1);
    return $tpl->getHtmlFrag('navi', [
        'title' => $title,
        'htitle' => $htitle,
        'lbl_home' => _HOME,
        'home_href' => $p['home_href'] ?? getSeoUrl(['name' => $conf['name']]),
        'best_href' => $p['best_href'] ?? ($showrate ? getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => $bop]) : ''),
        'lbl_best' => $p['btitle'] ?? _BEST,
        'pop_href' => $p['pop_href'] ?? ($showrate ? getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'pop']) : ''),
        'lbl_pop' => $p['ptitle'] ?? _POP,
        'liste_href' => $p['liste_href'] ?? getSeoUrl(['name' => $conf['name'], 'op' => 'liste']),
        'lbl_liste' => _LIST,
        'add_href' => $p['add_href'] ?? ($canadd ? getSeoUrl(['name' => $conf['name'], 'op' => 'add']) : ''),
        'lbl_add' => _ADD,
        'catshow' => $p['catshow'] ?? $cat,
        'lbl_catvorh' => _CATVORH,
        'lbl_cats' => _CATEGORIES,
    ]);
}

# Set bottom navigation
function setNaviLower(string $mod): string {
    global $tpl;
    return $tpl->getHtmlFrag('navi-lower', [
        'back_title' => _BACK,
        'back_label' => _BACK,
        'home_href' => 'index.php?name='.$mod,
        'home_title' => _PAGEHOME,
        'home_label' => _PAGEHOME,
        'top_title' => _PAGETOP,
        'top_label' => _PAGETOP,
    ]);
}

# Generation of page numbers
function setPageNumbers(string $frag, string $mod, int $count, int $pages, int $limit, string $url = '', int $maxpg = 8, int $num = 0, string $anchor = '', string $n = 'num'): string {
    global $afile, $tpl;
    $num  = $num ?: getVar('get', $n, 'num', 1);
    $nnum = $maxpg + 1;
    $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
    if ($pages > 1) {
        $cont = '';
        if ($num > 1) {
            $prev  = $num - 1;
            $prevHref = (!defined('ADMIN_FILE')) ? getSeoUrl(['name' => $mod, $url.$n => $prev]).$anchor : $afile.'.php?'.$url.$n.'='.$prev.$anchor;
            $cprev = getTplPagerLink($prevHref, _BACK, _BACK, 'sl_num');
        } else {
            $cprev = getTplPagerCurrent(_BACK, _BACK, 'sl_num');
        }
        for ($i = 1; $i < $pages+1; $i++) {
            if ($i == $num) {
                $cont .= getTplPagerCurrent((string)$i, (string)$i);
            } else {
                if ((($i > ($num - $maxpg)) && ($i < ($num + $maxpg))) || ($i == $pages) || ($i == 1)) {
                    $href = (!defined('ADMIN_FILE')) ? getSeoUrl(['name' => $mod, $url.$n => $i]).$anchor : $afile.'.php?'.$url.$n.'='.$i.$anchor;
                    $cont .= getTplPagerLink($href, (string)$i, (string)$i);
                }
            }
            if ($i < $pages) {
                if (($i > ($num - $nnum)) && ($i < ($num + $maxpg))) $cont .= ' ';
                if (($num > $nnum) && ($i == 1)) $cont .= getTplPagerDots();
                if (($num < ($pages - $maxpg)) && ($i == ($pages - 1))) $cont .= getTplPagerDots();
            }
        }
        if ($num < $pages) {
            $next  = $num + 1;
            $nextHref = (!defined('ADMIN_FILE')) ? getSeoUrl(['name' => $mod, $url.$n => $next]).$anchor : $afile.'.php?'.$url.$n.'='.$next.$anchor;
            $cnext = getTplPagerLink($nextHref, _NEXT, _NEXT, 'sl_num');
        } else {
            $cnext = getTplPagerCurrent(_NEXT, _NEXT, 'sl_num');
        }
        $data = ['overall' => _OVERALL, 'count' => $count, 'by' => _BY, 'pages' => $pages, 'page_s' => _PAGE_S, 'page' => $limit, 'perpage' => _PERPAGE, 'pager' => $cont, 'prev' => $cprev, 'next' => $cnext];
        return $tpl->getHtmlFrag($frag, $data);
    }
    return '';
}

# Generation of article numbers
function setArticleNumbers(string $name, string $mod, int $limit, string $url, string $cntfld, string $tbl, string $catfld = '', string $where = '', int $maxpg = 10, array $params = []): string {
    global $db, $conf, $locale;
    if (!defined('ADMIN_FILE') && $catfld && $where) {
        if ($conf['multilingual']) {
            $lng_where = 'WHERE modul = :mod AND (lang = :loc OR lang = \'\')';
            $lng_params = ['mod' => $mod, 'loc' => $locale];
        } else {
            $lng_where = 'WHERE modul = :mod';
            $lng_params = ['mod' => $mod];
        }
        $res = $db->getSqlQuery('SELECT id, pread FROM '.PREFIX_DB.'_categories '.$lng_where.' ORDER BY id', $lng_params);
        $catid = [];
        while (list($cid, $auth) = $db->getSqlRow($res)) {
            if (is_acess($auth)) $catid[] = (int)$cid;
        }
        $where = (!empty($catid)) ? ' WHERE '.$catfld.' IN ('.implode(', ',$catid).') AND '.$where : ' WHERE '.$where;
    } else {
        $where = $where ? ' WHERE '.$where : '';
    }
    $sql = 'SELECT COUNT('.$cntfld.') FROM '.PREFIX_DB.$tbl.$where;
    list($cnt) = $db->getSqlRow($db->getSqlQuery($sql,$params));
    $cnt = (int)$cnt;
    $pages = $cnt > 0 ? (int)ceil($cnt / $limit) : 1;
    return setPageNumbers($name, $mod, $cnt, $pages, $limit, $url, $maxpg);
}
