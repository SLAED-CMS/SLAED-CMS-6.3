<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Temporary home for new helper functions while shared APIs are stabilized

# Render one shared admin table wrapper from prepared header and row markup
function getAdminTable(string $head, string $rows, string $type = 'sl_table_list_sort'): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-table', [
        'head_html' => $head,
        'rows_html' => $rows,
        'table_class' => $type,
    ]);
}

# Render one admin table row from prepared cell markup
function getAdminTableRow(string $cells, string $type = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-table-row', [
        'cells_html' => $cells,
        'row_class' => $type,
    ]);
}

# Render one shared admin form wrapper from prepared hidden fields and row markup
function getAdminForm(string $action, string $rows, string $hide = '', string $type = 'sl_table_form', string $meth = 'post', string $name = 'post', string $attr = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-form', [
        'action_url' => $action,
        'form_attr' => $attr,
        'form_method' => $meth,
        'form_name' => $name,
        'hidden_html' => $hide,
        'rows_html' => $rows,
        'table_class' => $type,
    ]);
}

# Render one admin form row from prepared label and field markup
function getAdminFormRow(string $label, string $field, string $type = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-form-row', [
        'field_html' => $field,
        'label_html' => $label,
        'row_class' => $type,
    ]);
}

# Render one full-width admin form row from prepared content markup
function getAdminFormWide(string $cont, string $type = '', string $cell = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-form-wide', [
        'cell_class' => $cell,
        'content_html' => $cont,
        'row_class' => $type,
    ]);
}

# Render one shared admin content box from prepared inner markup
function getAdminBox(string $cont): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-box', [
        'content_html' => $cont,
    ]);
}

# Render one shared admin info wrapper from prepared legacy info markup
function getAdminInfoBox(string $info): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-info-box', [
        'info_html' => $info,
    ]);
}

# Render one shared admin searchbox wrapper from prepared search form markup
function getAdminSearchBox(string $search): string {
    global $tpl;
    return $tpl->getHtmlPart('searchbox', [
        'searchbox' => $search,
    ]);
}

# Render one shared admin placeholder box from stable id and optional inner markup
function getAdminPlaceholderBox(string $id, string $cont = ''): string {
    global $tpl;
    return getAdminBox($tpl->getHtmlFrag('admin-placeholder-box', [
        'box_id' => $id,
        'content_html' => $cont,
    ]));
}

# Render one shared admin select shell from prepared option markup
function getAdminSelect(string $name, string $opts, string $clas = '', string $attr = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-select', [
        'name_attr' => $name,
        'options_html' => $opts,
        'select_attr' => $attr,
        'select_class' => $clas,
    ]);
}

# Render one shared admin option row with optional selected state
function getAdminOption(string $valu, string $text, bool $isel = false): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-select-option', [
        'is_selected' => $isel,
        'label_text' => $text,
        'value_attr' => $valu,
    ]);
}

# Render one shared frontend content card from one shared data cut
function getContentCard(array $data): string {
    global $tpl;
    return $tpl->getHtmlFrag('content-card', $data);
}

# Render one shared frontend content view from one shared data cut
function getContentView(array $data): string {
    global $tpl;
    return $tpl->getHtmlFrag('content-view', $data);
}

# Render one shared frontend form row from prepared label and field markup
function getFormAddRow(string $label, string $field): string {
    global $tpl;
    return $tpl->getHtmlFrag('form-add-row', [
        'field_html' => $field,
        'label_text' => $label,
    ]);
}

# Render one shared frontend submit block from prepared hidden fields
function getFormSubmit(string $op, string $label, string $hide = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('form-submit', [
        'hidden_html' => $hide,
        'op_value' => $op,
        'submit_label' => $label,
    ]);
}

# Render one shared frontend select from prepared option/optgroup markup
function getFormSelect(string $name, string $opts, string $clas = '', string $attr = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('form-select', [
        'name_attr' => $name,
        'options_html' => $opts,
        'select_attr' => $attr,
        'select_class' => $clas,
    ]);
}

# Render one forum topic icon link from href, topic title, icon class and optional status label
function getForumIcon(string $href, string $title, string $icon, string $lbl = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('forum-topic-icon', [
        'href' => $href,
        'icon' => $icon,
        'lbl' => $lbl ?: $title,
        'title' => $title,
    ]);
}

# Render the forum reply/add form wrapper from module name and prepared row markup
function getForumReplyForm(string $mod, string $rows): string {
    global $tpl;
    return $tpl->getHtmlFrag('forum-reply-form', [
        'mod_name' => $mod,
        'rows_html' => $rows,
    ]);
}

# Render one meta-refresh tag from a URL and optional delay in seconds
function getMetaRefresh(string $url, int $secs = 10): string {
    global $tpl;
    return $tpl->getHtmlFrag('meta-refresh', [
        'secs' => (string)$secs,
        'url' => $url,
    ]);
}

# Render one categories permission row with label, hint and pre-built field markup
function getCatPermRow(string $label, string $hint, string $field): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-categories-perm-row', [
        'field_html' => $field,
        'hint_text' => $hint,
        'label_text' => $label,
    ]);
}

# Render one categories tab container wrapping a table of prepared row markup
function getCatTab(string $tabid, string $rows): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-categories-tab', [
        'rows_html' => $rows,
        'tab_id' => $tabid,
    ]);
}

# Render the ddtabcontent init script block for a given tab group id
function getCatTabScript(string $id): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-categories-tabscript', [
        'tab_group_id' => $id,
    ]);
}

# Render the categories form submit footer with pre-built hidden fields markup
function getCatSubmitRow(string $hide, string $label): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-categories-submit', [
        'hidden_html' => $hide,
        'submit_label' => $label,
    ]);
}

# Render a ddtabcontent conf-save form from inner content, module name, op value and optional submit label
function getAdminConfSave(string $cont, string $mod, string $op, string $label = ''): string {
    global $afile, $tpl;
    return $tpl->getHtmlFrag('admin-conf-save', [
        'action_url' => $afile.'.php',
        'content_html' => $cont,
        'mod_name' => $mod,
        'op_value' => $op,
        'submit_label' => $label ?: _SAVECHANGES,
    ]);
}

# Render a categories tab form wrapper from a form name and prepared tabs markup
function getCatForm(string $fname, string $cont): string {
    global $afile, $tpl;
    return $tpl->getHtmlFrag('admin-cat-form', [
        'action_url' => $afile.'.php',
        'form_name' => $fname,
        'tabs_html' => $cont,
    ]);
}

# Render the account admin search box from the currently selected field and search term
function getAccountSearchBox(int $search, string $chng): string {
    global $afile, $tpl;
    $opts = '';
    foreach ([_ID, _NICKNAME, _EMAIL, _IP, _URL] as $k => $v) {
        $n = $k + 1;
        $opts .= getAdminOption((string)$n, $v, $search === $n || (!$search && $n === 2));
    }
    return getAdminSearchBox($tpl->getHtmlFrag('admin-account-search-form', [
        'action_url' => $afile.'.php',
        'input_html' => get_user_search('chng', $chng, '30'),
        'ok_label' => _OK,
        'search_label' => _SEARCH,
        'select_html' => getAdminSelect('search', $opts),
    ]));
}

# Render the 2-column block visibility checkbox grid from an active-values list
function getBlockViewGrid(array $where = []): string {
    global $tpl;
    $cols = 2;
    $idx = 1;
    $rows = '';
    $mods = getBlockModules();
    foreach ($mods as $name) {
        $isch = in_array($name, $where);
        $wide = intval(100 / $cols);
        if (($idx - 1) % $cols === 0) $rows .= '<tr>';
        $rows .= '<td style="width: '.$wide.'%;"><input type="checkbox" name="blockwhere[]" value="'.$name.'"'.($isch ? ' checked' : '').'> '
            .'<span title="'._MODUL.': '.$name.'" class="sl_note">'.getModuleName($name).'</span></td>';
        if ($idx % $cols === 0) $rows .= '</tr>';
        $idx++;
    }
    $iel = in_array('ihome', $where) ? ' checked' : '';
    $hel = in_array('home', $where) ? ' checked' : '';
    $cel = (in_array('all', $where) && $hel === '') ? ' checked' : '';
    $oel = in_array('otricanie', $where) ? ' checked' : '';
    $fel = in_array('infly', $where) ? ' checked' : '';
    $xel = in_array('flyfix', $where) ? ' checked' : '';
    $rows .= '<tr><td><input type="checkbox" name="blockwhere[]" value="ihome"'.$iel.'> <b>'._HOME.'</b></td>'
        .'<td><input type="checkbox" name="blockwhere[]" value="home"'.$hel.'> <b>'._INHOME.'</b></td></tr>'
        .'<tr><td><input type="checkbox" name="blockwhere[]" value="all"'.$cel.'> <b>'._BLOCK_ALL.'</b></td>'
        .'<td><input type="checkbox" name="blockwhere[]" value="otricanie"'.$oel.'> <b>'._DENYING.'</b></td></tr>'
        .'<tr><td><input type="checkbox" name="blockwhere[]" value="infly"'.$fel.'> <b>'._INFLY.'</b></td>'
        .'<td><input type="checkbox" name="blockwhere[]" value="flyfix"'.$xel.'> <b>'._FLY_FIX.'</b></td></tr>';
    return $tpl->getHtmlFrag('admin-blocks-view-grid', ['rows_html' => $rows]);
}

# Render one money calculator form with a JS function name, to-currency label and currency code
function getMoneyCalcForm(string $fnname, string $tolbl, string $tocur): string {
    global $conf, $tpl;
    return $tpl->getHtmlFrag('money-calculator-form', [
        'btn_label' => _MO_4,
        'fn_name' => $fnname,
        'from_label' => _MO_2,
        'style' => $conf['style'],
        'to_cur' => $tocur,
        'to_label' => $tolbl,
    ]);
}

# Render a named list form wrapping a table and optional bottom/hidden markup
function getAdminListForm(string $table, string $bottom, string $hide): string {
    global $afile, $tpl;
    return $tpl->getHtmlFrag('admin-list-form', [
        'action_url' => $afile.'.php',
        'bottom_html' => $bottom,
        'hide_html' => $hide,
        'table_html' => $table,
    ]);
}
