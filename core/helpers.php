<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Temporary home for new helper functions while shared APIs are stabilized

# Build one ajax query string from named params and skip empty values
function getAjaxQuery(array $data): string {
    $list = [];
    foreach ($data as $name => $valu) {
        if ($valu === '' || $valu === null) continue;
        $list[] = $name.'='.rawurlencode((string)$valu);
    }
    return implode('&amp;', $list);
}

# Render one shared admin table wrapper from prepared header and row markup
function getTplAdminTable(string $head, string $rows, string $type = 'sl_table_list_sort'): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-table', [
        'head_html' => $head,
        'rows_html' => $rows,
        'table_class' => $type,
    ]);
}

# Render one admin table row from prepared cell markup
function getTplAdminTableRow(string $cells, string $type = '', string $attr = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-table-row', [
        'cells_html' => $cells,
        'row_attr' => $attr,
        'row_class' => $type,
    ]);
}

# Render one shared admin form wrapper from prepared hidden fields and row markup
function getTplAdminForm(string $action, string $rows, string $hide = '', string $type = 'sl_table_form', string $meth = 'post', string $name = 'post', string $attr = ''): string {
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
function getTplAdminFormRow(string $label, string $field, string $type = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-form-row', [
        'field_html' => $field,
        'label_html' => $label,
        'row_class' => $type,
    ]);
}

# Render one full-width admin form row from prepared content markup
function getTplAdminFormWide(string $cont, string $type = '', string $cell = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-form-wide', [
        'cell_class' => $cell,
        'content_html' => $cont,
        'row_class' => $type,
    ]);
}

# Render one shared admin content box from prepared inner markup
function getTplBox(string $cont): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-box', [
        'content_html' => $cont,
    ]);
}

# Render one shared admin rows-only table wrapper with a configurable table class
function getTplAdminRowsTable(string $rows, string $type = 'sl_table_conf'): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-rows-table', [
        'rows_html' => $rows,
        'table_class' => $type,
    ]);
}

# Render one shared admin info wrapper from prepared legacy info markup
function getTplAdminInfoBox(string $info): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-info-box', [
        'info_html' => $info,
    ]);
}

# Render one shared admin searchbox wrapper from prepared search form markup
function getTplAdminSearchBox(string $search): string {
    global $tpl;
    return $tpl->getHtmlPart('searchbox', [
        'searchbox' => $search,
    ]);
}

# Render one shared admin placeholder box from stable id and optional inner markup
function getTplAdminPlaceholder(string $id, string $cont = ''): string {
    global $tpl;
    return getTplBox($tpl->getHtmlFrag('admin-placeholder-box', [
        'box_id' => $id,
        'content_html' => $cont,
    ]));
}

# Render one shared admin select shell from prepared option markup
function getTplSelect(string $name, string $opts, string $clas = '', string $attr = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-select', [
        'name_attr' => $name,
        'options_html' => $opts,
        'select_attr' => $attr,
        'select_class' => $clas,
    ]);
}

# Render one shared admin option row with optional selected state
function getTplOption(string $valu, string $text, bool $isel = false): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-select-option', [
        'is_selected' => $isel,
        'label_text' => $text,
        'value_attr' => $valu,
    ]);
}

# Render one shared admin state flag label from a boolean state and yes/no labels
function getTplAdminFlagBox(bool $state, string $yes, string $no): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-flag-box', [
        'css_class' => $state ? 'sl_green' : 'sl_red',
        'label_text' => $state ? $yes : $no,
    ]);
}

# Render one shared admin note label with a plain-text title attribute
function getTplAdminNoteLabel(string $title, string $label): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-note-label', [
        'label_text' => $label,
        'title_attr' => $title,
    ]);
}

# Render one shared admin danger text span for suspicious filenames
function getTplAdminDangerText(string $text): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-danger-text', [
        'text' => $text,
    ]);
}

# Render one shared admin title-tip popup from prepared inner markup
function getTplAdminTitleTip(string $cont): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-title-tip', [
        'content_html' => $cont,
    ]);
}

# Render one shared admin colored label from a CSS color value and label text
function getTplAdminColorLabel(string $color, string $label): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-color-label', [
        'color_val' => $color,
        'label_text' => $label,
    ]);
}

# Render one shared admin ajax action item with GET load mode and optional CSS class
function getTplAdminAjaxAction(string $target, string $query, string $title, string $label, string $clas = ''): string {
    global $tpl;
    $route = $query;
    if (!str_contains($route, 'token=')) $route .= '&amp;token='.getSiteToken();
    return $tpl->getHtmlFrag('admin-action-ajax', [
        'class' => $clas,
        'label' => $label,
        'query' => $route,
        'target' => $target,
        'title' => $title,
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

# Render one shared admin delete action item with JS confirm text
function getTplAdminDeleteAction(string $href, string $text, string $title, string $label): string {
    global $tpl;
    return $tpl->getHtmlFrag('action-delete', [
        'confirm_text' => $text,
        'href' => $href,
        'label' => $label,
        'title' => $title,
    ]);
}

# Render one shared admin move-controls block with HTMX transport
function getTplAdminMoveControls(string $target, string $up = '', string $down = ''): string {
    global $tpl;
    $up = ($up && !str_contains($up, 'token=')) ? $up.'&amp;token='.getSiteToken() : $up;
    $down = ($down && !str_contains($down, 'token=')) ? $down.'&amp;token='.getSiteToken() : $down;
    return $tpl->getHtmlFrag('admin-move-controls', [
        'down_query' => $down,
        'down_title' => _BLOCKDOWN,
        'target' => $target,
        'up_query' => $up,
        'up_title' => _BLOCKUP,
    ]);
}

# Render one shared admin action-menu wrapper from prepared action item markup
function getTplAdminActionMenu(array $items): string {
    global $tpl;
    $items = array_values(array_filter($items, static fn($item) => $item !== ''));
    if (!$items) return '';
    return $tpl->getHtmlFrag('action-menu', [
        'editor_label' => _EDITOR,
        'items_html' => implode('', array_map(static fn($item) => getTplMenuItem($item), $items)),
    ]);
}

# Render one admin info page or HTMX fragment from prepared navigation markup
function setAdminInfoPage(string $cont): void {
    $head = strtolower($_SERVER['HTTP_HX_REQUEST'] ?? '');
    if ($head === 'true') {
        echo getTplAdminInfoBox(getAdminInfo());
        return;
    }
    setHead();
    echo $cont.getTplAdminInfoBox(getAdminInfo());
    setFoot();
}

# Render one shared admin hidden input from a field name and value
function getTplHiddenInput(string $name, string $valu): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-hidden-input', [
        'name_attr' => $name,
        'value_attr' => $valu,
    ]);
}

# Render one shared admin text input with optional class and extra attributes
function getTplTextInput(string $name, string $valu, string $clas = 'sl_form', string $attr = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-input', [
        'input_attr' => $attr,
        'input_class' => $clas,
        'itype' => 'text',
        'name_attr' => $name,
        'value_attr' => $valu,
    ]);
}

# Render one shared admin number input with optional class and extra attributes
function getTplNumberInput(int|string $valu, string $name, string $clas = 'sl_form', string $attr = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-input', [
        'input_attr' => $attr,
        'input_class' => $clas,
        'itype' => 'number',
        'name_attr' => $name,
        'value_attr' => (string)$valu,
    ]);
}

# Render one shared admin url input with optional class and extra attributes
function getTplUrlInput(string $name, string $valu, string $clas = 'sl_form', string $attr = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-input', [
        'input_attr' => $attr,
        'input_class' => $clas,
        'itype' => 'url',
        'name_attr' => $name,
        'value_attr' => $valu,
    ]);
}

# Render one shared admin email input with optional class and extra attributes
function getTplEmailInput(string $name, string $valu, string $clas = 'sl_form', string $attr = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-input', [
        'input_attr' => $attr,
        'input_class' => $clas,
        'itype' => 'email',
        'name_attr' => $name,
        'value_attr' => $valu,
    ]);
}

# Render one shared admin textarea with optional class and extra attributes
function getTplTextarea(string $name, string $valu, string $clas = 'sl_form', string $attr = '', int $cols = 65, int $rows = 5): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-textarea', [
        'cols_num' => $cols,
        'input_attr' => $attr,
        'input_class' => $clas,
        'name_attr' => $name,
        'rows_num' => $rows,
        'value_text' => $valu,
    ]);
}

# Render one shared admin preview image tag from src, alt text and optional id
function getTplImagePreview(string $src, string $alt, string $id = 'picture'): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-image-preview', [
        'alt_text' => $alt,
        'image_id' => $id,
        'src_attr' => $src,
    ]);
}

# Render one shared admin horizontal separator line
function getTplHrLine(): string {
    return '<hr>';
}

# Render one shared admin tab-content wrapper from a tab id and prepared inner markup
function getTplAdminTabContent(string $tabid, string $cont): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-tab-content', [
        'items_html' => $cont,
        'tab_id' => $tabid,
    ]);
}

# Render one shared admin tab-panel id from a group id, index and submenu flag
function getTplAdminTabName(string $group, int $index, bool $sub = false): string {
    return $sub ? $group.'-sub-panel-'.$index : $group.'-panel-'.$index;
}

# Render one shared modern admin tabs init block for a given admin tab group id
function getTplAdminTabsSetup(string $group): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-tabs-setup', [
        'group_id' => $group,
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
function getTplFormSubmit(string $op, string $label, string $hide = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('form-submit', [
        'hidden_html' => $hide,
        'op_value' => $op,
        'submit_label' => $label,
    ]);
}

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

# Render a search result title link with highlighted text and new-badge
function getTplSearchResultTitle(string $url, string $title, string $word, string $time): string {
    global $tpl;
    return $tpl->getHtmlFrag('search-result-title', [
        'url'               => $url,
        'title'             => $title,
        'highlighted_title' => filterTextHighlight($title, $word),
        'new_badge'         => new_graphic($time),
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
    global $tpl;
    return $tpl->getHtmlFrag('meta-refresh', [
        'secs' => (string)$secs,
        'url' => $url,
    ]);
}

# Render one categories permission row with label, hint and pre-built field markup
function getTplCatPermRow(string $label, string $hint, string $field): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-categories-perm-row', [
        'field_html' => $field,
        'hint_text' => $hint,
        'label_text' => $label,
    ]);
}

# Render one categories tab container wrapping a table of prepared row markup
function getTplCatTab(string $tabid, string $rows): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-categories-tab', [
        'rows_html' => $rows,
        'tab_id' => $tabid,
    ]);
}

# Render the categories form submit footer with pre-built hidden fields markup
function getTplCatSubmitRow(string $hide, string $label): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-categories-submit', [
        'hidden_html' => $hide,
        'submit_label' => $label,
    ]);
}

# Render a ddtabcontent conf-save form from inner content, module name, op value and optional submit label
function getTplAdminConfSave(string $cont, string $mod, string $op, string $label = ''): string {
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
function getTplCatForm(string $fname, string $cont): string {
    global $afile, $tpl;
    return $tpl->getHtmlFrag('admin-cat-form', [
        'action_url' => $afile.'.php',
        'form_name' => $fname,
        'tabs_html' => $cont,
    ]);
}

# Render the account admin search box from the currently selected field and search term
function getTplAdminAccountSearch(int $search, string $chng): string {
    global $afile, $tpl;
    $opts = '';
    foreach ([_ID, _NICKNAME, _EMAIL, _IP, _URL] as $k => $v) {
        $n = $k + 1;
        $opts .= getTplOption((string)$n, $v, $search === $n || (!$search && $n === 2));
    }
    return getTplAdminSearchBox($tpl->getHtmlFrag('admin-account-search-form', [
        'action_url' => $afile.'.php',
        'input_html' => getUserSearch('chng', $chng, '30'),
        'ok_label' => _OK,
        'search_label' => _SEARCH,
        'select_html' => getTplSelect('search', $opts),
    ]));
}

# Return frontend-capable block module names from shared module config
function getBlockModules(): array {
    global $conf;
    static $mods = null;
    if ($mods === null) {
        $mods = [];
        foreach ($conf['modules'] as $name => $info) {
            if ((int)($info['type'] ?? 1) !== 1) continue;
            $mods[] = $name;
        }
        sort($mods);
    }
    return $mods;
}

# Render the 2-column block visibility checkbox grid from an active-values list
function getTplAdminBlockGrid(array $where = []): string {
    global $tpl;
    $cols = 2;
    $idx  = 1;
    $rows = '';
    $wide = intval(100 / $cols);
    $mods = getBlockModules();
    foreach ($mods as $name) {
        if (($idx - 1) % $cols === 0) $rows .= '<tr>';
        $rows .= $tpl->getHtmlFrag('admin-blocks-view-module-cell', [
            'checked'    => in_array($name, $where),
            'label_text' => getModuleName($name),
            'mod_label'  => _MODUL,
            'name_attr'  => $name,
            'width_num'  => $wide,
        ]);
        if ($idx % $cols === 0) $rows .= '</tr>';
        $idx++;
    }
    $home_on  = in_array('home', $where);
    $specials = [
        ['ihome',     in_array('ihome',     $where),              _HOME],
        ['home',      $home_on,                                   _INHOME],
        ['all',       in_array('all',       $where) && !$home_on, _BLOCK_ALL],
        ['otricanie', in_array('otricanie', $where),              _DENYING],
        ['infly',     in_array('infly',     $where),              _INFLY],
        ['flyfix',    in_array('flyfix',    $where),              _FLY_FIX],
    ];
    for ($i = 0; $i < count($specials); $i += 2) {
        $rows .= '<tr>';
        $rows .= $tpl->getHtmlFrag('admin-blocks-view-special-cell', [
            'checked'    => $specials[$i][1],
            'label_text' => $specials[$i][2],
            'value_attr' => $specials[$i][0],
        ]);
        $rows .= $tpl->getHtmlFrag('admin-blocks-view-special-cell', [
            'checked'    => $specials[$i + 1][1],
            'label_text' => $specials[$i + 1][2],
            'value_attr' => $specials[$i + 1][0],
        ]);
        $rows .= '</tr>';
    }
    return $tpl->getHtmlFrag('admin-blocks-view-grid', ['rows_html' => $rows]);
}

# Render the search drop-form and delete link from row state and display word
function getTplAdminSearchDrop(int $id, string $action, int $sort, int $order, int $num, string $find, string $fmod, string $show): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-search-drop-form', [
        'action_url'   => $action,
        'confirm_text' => _DELETE.' "'.$show.'"?',
        'find'         => $find,
        'fmod'         => $fmod,
        'form_id'      => (string)$id,
        'id'           => (string)$id,
        'label'        => _ONDELETE,
        'num'          => (string)$num,
        'order'        => (string)$order,
        'sort'         => (string)$sort,
        'title'        => _ONDELETE,
        'token'        => getSiteToken('search'),
    ]);
}

# Render the categories module-filter search form from the active module name
function getTplAdminCatSearch(string $modul): string {
    global $afile, $tpl;
    return getTplAdminSearchBox($tpl->getHtmlFrag('admin-categories-search-form', [
        'action_url'  => $afile.'.php',
        'modul_label' => _MODUL,
        'select_html' => cat_modul('modul', '', $modul, 1),
    ]));
}

# Render a categories image select from a path and optional pre-selected filename
function getTplCategorySelect(string $path, string $selected = ''): string {
    $files = is_dir($path) ? scandir($path) : [];
    $imgs  = [];
    foreach ($files as $entry) {
        if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg)$/is', $entry) && $entry !== 'no.png') {
            $imgs[] = getTplOption($path.$entry, $entry, $selected === $entry);
        }
    }
    asort($imgs);
    $opts = getTplOption($path.'no.png', _NO).implode('', $imgs);
    return getTplSelect('imgcat', $opts, 'sl_form', 'id="img_replace"');
}

# Render a categories image preview tag from a full image path
function getTplCategoryPreview(string $src): string {
    global $tpl;
    return $tpl->getHtmlFrag('img-preview', [
        'alt' => _IMG,
        'src' => $src,
    ]);
}

# Render a block position select with optional pre-selected value
function getTplBlockPosition(string $selected = ''): string {
    $opts = getTplOption('l', _LEFT,       $selected === 'l')
          . getTplOption('c', _CENTERUP,   $selected === 'c')
          . getTplOption('d', _CENTERDOWN, $selected === 'd')
          . getTplOption('r', _RIGHT,      $selected === 'r')
          . getTplOption('b', _BANNERUP,   $selected === 'b')
          . getTplOption('f', _BANNERDOWN, $selected === 'f');
    return getTplSelect('bpos', $opts, 'sl_form');
}

# Render a block RSS refresh-interval select with optional pre-selected value
function getTplBlockRefresh(string $selected = '3600'): string {
    $times = [
        '1800'  => '30 '._MIN.'.',
        '3600'  => '1 '._HOUR,
        '18000' => '5 '._HOUR.'.',
        '36000' => '10 '._HOUR.'.',
        '86400' => '24 '._HOUR.'.',
    ];
    $opts = '';
    foreach ($times as $val => $label) {
        $opts .= getTplOption($val, $label, $selected === $val);
    }
    return getTplSelect('refresh', $opts, 'sl_form');
}

# Render a block after-expiration action select with optional pre-selected value
function getTplBlockAction(string $selected = ''): string {
    $opts = getTplOption('d', _DEACTIVATE, $selected === 'd')
          . getTplOption('r', _DELETE,     $selected === 'r');
    return getTplSelect('action', $opts, 'sl_form');
}

# Render a block view-privilege select with optional pre-selected value
function getTplBlockView(int $selected = 0): string {
    $privs = [0 => _MVALL, 1 => _MVUSERS, 2 => _MVADMIN, 3 => _MVANON];
    $opts  = '';
    foreach ($privs as $key => $label) {
        $opts .= getTplOption((string)$key, $label, $selected === $key);
    }
    return getTplSelect('view', $opts, 'sl_form');
}

# Return a label string with an inline sl_small help-text div for admin form rows
function getTplAdminHintLabel(string $label, string $hint): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-hint-label', ['label' => $label, 'hint' => $hint]);
}

# Return a standalone sl_small note div for admin form rows that have no label
function getTplAdminSmallNote(string $text): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-small-note', ['text' => $text]);
}

# Join non-empty stop/error messages into a single HTML string separated by line breaks
function getStopText(array $stop): string {
    global $tpl;
    $sep = '<br>';
    return implode($sep, array_filter($stop, 'strlen'));
}

# Return an anchor tag for use inside raw HTML slots such as alert text or tooltip content
# href must be pre-encoded by the caller; label and optional title/class are HTML-escaped here
function getTplAdminTextLink(string $href, string $label, string $target = '', string $title = '', string $class = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-text-link', [
        'href'   => $href,
        'label'  => $label,
        'cls'    => $class,
        'target' => $target,
        'title'  => $title,
    ]);
}

# Return one line-break-prefixed key-value pair for building tooltip content_html strings
function getTplAdminTipLine(string $key, string $val): string {
    global $tpl;
    return $tpl->getHtmlFrag('br-line', ['label' => $key, 'value' => $val]);
}

# Return one label-value line with a trailing line break for building info block content strings
function getTplAdminInfoLine(string $label, string $value): string {
    global $tpl;
    return $tpl->getHtmlFrag('info-line', ['label' => $label, 'value' => $value]);
}

# Build the head_html string for getTplAdminTable from an array of column labels
# Each entry is either a plain string (sortable) or [label, 'nosort'] (non-sortable column)
function getTplAdminTableHead(array $cols): string {
    global $tpl;
    $html = '';
    foreach ($cols as $col) {
        if (is_array($col)) {
            $html .= $tpl->getHtmlFrag('th-nosort', ['content' => $col[0]]);
        } else {
            $html .= $tpl->getHtmlFrag('th', ['content' => $col]);
        }
    }
    return $html;
}

# Build the cells_html string for getTplAdminTableRow from an array of already-rendered cell contents
function getTplAdminTableCells(array $cells): string {
    global $tpl;
    $html = '';
    foreach ($cells as $cell) {
        $html .= $tpl->getHtmlFrag('td', ['content' => $cell]);
    }
    return $html;
}

# Return an inline span with a CSS class and optional title attribute; raw_content is not escaped
function getTplSpan(string $class, string $raw_content, string $title = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('span-raw', ['class' => $class, 'content' => $raw_content, 'title' => $title]);
}

# Return an inline status badge span (sl_green / sl_red) for use inside raw HTML slots
function getTplAdminStatusBadge(bool $state, string $yes, string $no): string {
    global $tpl;
    return $tpl->getHtmlFrag('span-btn', [
        'class' => $state ? 'sl_green' : 'sl_red',
        'label' => $state ? $yes : $no,
        'title' => '',
    ]);
}

# Return an inline language hint string for admin title tips; empty string when multilingual is off or lang is empty
function getTplAdminLangHint(string $lang): string {
    global $conf;
    if ($conf['multilingual'] != 1) return '';
    return getTplAdminTipLine(_LANGUAGE, $lang ? getLangName($lang) : _ALL);
}

# Render one money calculator form with a JS function name, to-currency label and currency code
function getTplMoneyCalcForm(string $fnname, string $tolbl, string $tocur): string {
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
function getTplAdminListForm(string $table, string $bottom, string $hide): string {
    global $afile, $tpl;
    return $tpl->getHtmlFrag('admin-list-form', [
        'action_url' => $afile.'.php',
        'bottom_html' => $bottom,
        'hide_html' => $hide,
        'table_html' => $table,
    ]);
}

# Render one shared admin submit button from a label string
function getTplAdminSubmitButton(string $label): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-submit-button', [
        'label_text' => $label,
    ]);
}

# Render one shared admin section heading from a label string
function getTplAdminSection(string $label): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-section-heading', [
        'label_text' => $label,
    ]);
}

# Render a <link rel="stylesheet"> tag for an external CSS file
function getHtmlCssLink(string $href): string {
    global $tpl;
    return $tpl->getHtmlFrag('head-link-css', ['href' => $href]);
}

# Render an inline <style> block
function getHtmlCssInline(string $css): string {
    global $tpl;
    return $tpl->getHtmlFrag('style-inline', ['css' => $css]);
}

# Render a <script src="..."> tag, optionally with async attribute string ('async ' or '')
function getHtmlScriptSrc(string $src, string $async = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('script-src', ['src' => $src, 'async' => $async]);
}

# Render an inline <script> block
function getHtmlScriptInline(string $js): string {
    global $tpl;
    return $tpl->getHtmlFrag('script-inline', ['js' => $js]);
}

# Render a generic <link> head tag with optional type and title attributes
function getHtmlHeadLink(string $rel, string $href, string $type = '', string $title = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('head-link-generic', ['rel' => $rel, 'href' => $href, 'type' => $type, 'title' => $title]);
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

# Render the admin category list table header and rows from prepared row markup
function getTplAdminCategoryTable(string $rows): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-category-table', [
        'id_label' => _ID,
        'category_label' => _CATEGORY,
        'content_label' => cutstr(_CONTENT, 3, 1),
        'subcategory_label' => cutstr(_SUBCATEGORY, 3, 1),
        'image_label' => cutstr(_IMG, 2, 1),
        'weight_label' => _WEIGHT,
        'status_label' => _STATUS,
        'functions_label' => _FUNCTIONS,
        'rows_html' => $rows,
    ]);
}

# Render one admin category list row from a prepared data map
function getTplAdminCategoryRow(array $row): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-category-row', $row);
}

# Render the admin block list table header and rows from prepared row markup
function getTplAdminBlockTable(string $rows): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-block-table', [
        'id_label' => _ID,
        'title_label' => _TITLE,
        'type_label' => _TYPE,
        'view_label' => _VIEW,
        'position_label' => _POSITION,
        'weight_label' => _WEIGHT,
        'status_label' => _STATUS,
        'functions_label' => _FUNCTIONS,
        'rows_html' => $rows,
    ]);
}

# Render one admin block list row from a prepared data map
function getTplAdminBlockRow(array $row): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-block-row', $row);
}

# Render the admin favorites list table header and rows from prepared row markup
function getTplAdminFavoriteTable(string $rows): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-favorites-table', [
        'id_label' => _ID,
        'title_label' => _TITLE,
        'module_label' => _MODUL,
        'posted_by_label' => _POSTEDBY,
        'functions_label' => _FUNCTIONS,
        'rows_html' => $rows,
    ]);
}

# Render one admin favorites list row from a prepared data map
function getTplAdminFavoriteRow(array $row): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-favorites-row', $row);
}

# Render the admin private messages list table header and rows from prepared row markup
function getTplAdminPrivateTable(string $rows): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-private-table', [
        'id_label' => _ID,
        'title_label' => _TITLE,
        'sender_label' => _PRSE,
        'receiver_label' => _PRRE,
        'date_label' => _DATE,
        'status_label' => _STATUS,
        'functions_label' => _FUNCTIONS,
        'rows_html' => $rows,
    ]);
}

# Render one admin private message list row from a prepared data map
function getTplAdminPrivateRow(array $row): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-private-row', $row);
}

# Render the admin upload files list table header and rows from prepared row markup
function getTplAdminFilesTable(string $rows): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-files-table', [
        'image_label' => cutstr(_IMG, 4, 1),
        'file_label' => _FILE,
        'date_label' => _DATE,
        'size_label' => _SIZE,
        'dimensions_label' => _WIDTH.' x '._HEIGHT,
        'functions_label' => _FUNCTIONS,
        'rows_html' => $rows,
    ]);
}

# Render one admin upload files list row from a prepared data map
function getTplAdminFilesRow(array $row): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-files-row', $row);
}

# Render the admin info text edit form from a data map with action, hidden fields and textarea
function getTplAdminInfoForm(array $data): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-info-form', [
        'action_url' => (string)($data['action_url'] ?? ''),
        'hidden_html' => (string)($data['hidden_html'] ?? ''),
        'submit_label' => (string)($data['submit_label'] ?? ''),
        'submit_title' => (string)($data['submit_title'] ?? ''),
        'textarea_html' => (string)($data['textarea_html'] ?? ''),
    ]);
}

# Render one admin file upload preview tile from an index, file path and image-presence flag
function getTplAdminFilePreview(int $index, string $path, bool $hasImage): string {
    global $tpl;
    return $tpl->getHtmlFrag('editor-file-preview', [
        'preview_id' => 'sf-form-'.$index,
        'toggle_onclick' => "HideShow('sf-form-".$index."', 'fold', 'up', 500);",
        'image_url' => $path,
        'fallback_url' => 'templates/admin/images/admin/no.png',
        'image_title' => _IMG,
        'no_title' => _NO,
        'show_image' => $hasImage,
    ]);
}

# Render a composite title-tip block joined with a note label from tooltip, title and label texts
function getTplAdminTipLabel(string $tip, string $title, string $label): string {
    return getTplAdminTitleTip($tip).getTplAdminNoteLabel($title, $label);
}

# Render one admin collapsible panel from a panel id, title and prepared content markup
function getTplAdminPanel(string $pid, string $title, string $cont): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-panel', [
        'pid'   => $pid,
        'title' => $title,
        'cont'  => $cont,
    ]);
}

# Render one admin tab list open tag from an id and a CSS class
function getTplAdminTabOpen(string $id, string $cls): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-tab-list-open', [
        'list_class' => $cls,
        'list_id' => $id,
    ]);
}

# Render one admin tab list close tag
function getTplAdminTabClose(): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-tab-list-close', []);
}

# Render one admin tab link from href, label, selected state and optional rel and attr strings
function getTplAdminTabLink(string $href, string $label, bool $isel = false, string $rel = '', string $attr = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-tab-link', [
        'attrs' => $attr,
        'href' => $href,
        'is_selected' => $isel,
        'label' => $label,
        'rel' => $rel,
    ]);
}

# Render one admin info count badge with a red or green CSS class based on value
function getTplAdminInfoCount(int|string $cnt): string {
    global $tpl;
    if (!is_numeric($cnt)) return '-';
    $css = ((int)$cnt >= 1) ? 'sl_red' : 'sl_green';
    return $tpl->getHtmlFrag('admin-info-count', [
        'count_text' => (string)$cnt,
        'css_class' => $css,
    ]);
}

# Render one admin info panel row from href, title, label and count value
function getTplAdminInfoRow(string $href, string $title, string $label, int|string $cnt): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-info-row', [
        'count_html' => getTplAdminInfoCount($cnt),
        'href' => $href,
        'label' => $label,
        'title' => $title,
    ]);
}

# Render one admin info panel table from an array of pre-rendered row strings
function getTplAdminInfoTable(array $rows): string {
    global $tpl;
    return $tpl->getHtmlFrag('admin-info-table', [
        'rows_html' => implode('', $rows),
    ]);
}

# Render the admin module navigation header with tabs and optional subtabs
function getTplAdminNavi(array $par): string {
    global $afile, $conf, $tpl;
    $ttl = _ADMINMENU;
    $ico = 'components.png';
    $name = getVar('req', 'name', 'var');
    if ($name !== '' && isset($conf['modules'][$name]) && is_array($conf['modules'][$name])) {
        $lang = trim($conf['modules'][$name]['lang'] ?? '');
        if ($lang !== '') $ttl = defined($lang) ? constant($lang) : $lang;
        $img = basename(trim($conf['modules'][$name]['img'] ?? ''));
        if ($img !== '' && file_exists(BASE_DIR.'/templates/admin/images/admin/'.$img)) $ico = $img;
    }
    $ops    = $par['ops']    ?? [];
    $tabs   = $par['tabs']   ?? [];
    $sops   = $par['sops']   ?? [];
    $sattrs = $par['sattrs'] ?? [];
    $stabs  = $par['stabs']  ?? [];
    $sub    = $par['sub']    ?? '';
    $act    = $par['tab']    ?? 0;
    $hassub = (bool)($par['subtab'] ?? false);
    $actsub = $par['legacy'] ?? 0;
    $mtab   = $par['id']     ?? 'menutab';
    $cnt = getTplAdminTabOpen($mtab, 'tabmenu');
    $scnt = '';
    $k = 0;
    foreach ($tabs as $tab) {
        if ($tab === '') { $k++; continue; }
        if ($hassub && !empty($stabs)) {
            $scnt = getTplAdminTabOpen($mtab.'s', 'tabsubmenu');
            $l = 0;
            foreach ($stabs as $stab) {
                if ($stab === '') { $l++; continue; }
                $hrefsub = !empty($sops[$l]) ? $afile.'.php?'.$sops[$l] : '#';
                $relsub = !empty($sops[$l]) ? '' : getTplAdminTabName($mtab, $l, true);
                $attrsub = $sattrs[$l] ?? '';
                $scnt .= getTplAdminTabLink($hrefsub, $stab, $l === $actsub, $relsub, $attrsub);
                $l++;
            }
            $scnt .= getTplAdminTabClose();
        }
        $href = !empty($ops[$k]) ? $afile.'.php?'.$ops[$k] : '#';
        $rel = !empty($ops[$k]) ? '' : getTplAdminTabName($mtab, $k);
        $cnt .= getTplAdminTabLink($href, $tab, $k === $act, $rel);
        $k++;
    }
    $cnt .= getTplAdminTabClose();
    if ($scnt !== '') $cnt .= $scnt;
    return $tpl->getHtmlFrag('title', ['title' => $ttl, 'icon' => $ico, 'subtitle' => $sub, 'content' => $cnt]);
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
            $cprev = pagerLink($prevHref, _BACK, _BACK, 'sl_num');
        } else {
            $cprev = pagerCurrent(_BACK, _BACK, 'sl_num');
        }
        for ($i = 1; $i < $pages+1; $i++) {
            if ($i == $num) {
                $cont .= pagerCurrent((string)$i, (string)$i);
            } else {
                if ((($i > ($num - $maxpg)) && ($i < ($num + $maxpg))) || ($i == $pages) || ($i == 1)) {
                    $href = (!defined('ADMIN_FILE')) ? getSeoUrl(['name' => $mod, $url.$n => $i]).$anchor : $afile.'.php?'.$url.$n.'='.$i.$anchor;
                    $cont .= pagerLink($href, (string)$i, (string)$i);
                }
            }
            if ($i < $pages) {
                if (($i > ($num - $nnum)) && ($i < ($num + $maxpg))) $cont .= ' ';
                if (($num > $nnum) && ($i == 1)) $cont .= pagerDots();
                if (($num < ($pages - $maxpg)) && ($i == ($pages - 1))) $cont .= pagerDots();
            }
        }
        if ($num < $pages) {
            $next  = $num + 1;
            $nextHref = (!defined('ADMIN_FILE')) ? getSeoUrl(['name' => $mod, $url.$n => $next]).$anchor : $afile.'.php?'.$url.$n.'='.$next.$anchor;
            $cnext = pagerLink($nextHref, _NEXT, _NEXT, 'sl_num');
        } else {
            $cnext = pagerCurrent(_NEXT, _NEXT, 'sl_num');
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
