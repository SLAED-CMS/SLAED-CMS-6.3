<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Build the control, caption and hint ids of one form row; an existing id passes through untouched and is never derived from the field name, which JS and CSS hang on
function getFieldIds(string $id, string $mint = ''): array {
    static $seq = 0;
    if ($id === '') {
        $seq++;
        $seed = $mint !== '' ? strtolower(preg_replace('#[^a-zA-Z0-9]+#', '-', $mint)) : 'field';
        $id = 'f-'.trim($seed, '-').'-'.$seq;
    }
    return ['input' => $id, 'label' => $id.'-label', 'hint' => $id.'-hint'];
}

# Answer the checked extra field definitions of one area; empty until the 6.3 data update has left its mark, and a set failing the check counts as empty after one log entry
function getFieldRules(string $mod): array {
    global $conf, $fld;
    static $memo = [];
    $mod = strtolower($mod);
    if (isset($memo[$mod])) return $memo[$mod];
    $set = (($conf['update']['fields'] ?? '') === '6.3.0') ? ($conf['fields'][$mod] ?? []) : [];
    try {
        return $memo[$mod] = $fld->filterFieldList(is_array($set) ? $set : []);
    } catch (InvalidArgumentException $err) {
        Logger::addSite('error', 'Field: the definitions of '.$mod.' are refused', ['path' => $err->getMessage()]);
        return $memo[$mod] = [];
    }
}

# Read the posted extra fields of one area; before the 6.3 data update the stored text comes back untouched, and a switched-off field keeps its stored value
function getFieldsPost(string $mod, string $old = ''): array {
    global $conf, $fld;
    if (($conf['update']['fields'] ?? '') !== '6.3.0') {
        Logger::addSite('error', 'Field: the 6.3 data update has not run, extra fields are closed for writing', ['area' => $mod]);
        return ['json' => $old, 'errors' => [], 'stop' => []];
    }
    $rules = getFieldRules($mod);
    if (!$rules && !empty($conf['fields'][strtolower($mod)])) return ['json' => $old, 'errors' => [], 'stop' => []];
    $post = getVar('post', 'field[]', '', []);
    $trust = isAdmin(true);
    array_walk_recursive($post, function (mixed &$val) use ($trust): void {
        if (is_string($val)) $val = filterTrustedTags($val, $trust);
    });
    $errs = $fld->checkFieldValues($rules, $post);
    $texts = ['required' => _FIELDS_REQ, 'type' => _FIELDS_TYPE, 'format' => _FIELDS_FORMAT, 'choice' => _FIELDS_CHOICE, 'min' => _FIELDS_MIN, 'max' => _FIELDS_MAX];
    $stop = [];
    foreach ($errs as $name => $code) $stop[] = getConst($rules[$name]['title']).': '.$texts[$code];
    if ($errs) return ['json' => $old, 'errors' => $errs, 'stop' => $stop];
    $vals = $fld->filterFieldValues($rules, $post);
    $prev = json_decode($old, true);
    $keep = [];
    foreach ($rules as $name => $rule) {
        if ($rule['active'] && isset($vals[$name])) $keep[$name] = $vals[$name];
        if (!$rule['active'] && is_array($prev) && isset($prev[$name])) $keep[$name] = $prev[$name];
    }
    $json = $keep ? (string)json_encode($keep, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    return (strlen($json) > 1048576) ? ['json' => $old, 'errors' => [], 'stop' => [_FIELDS_MAX]] : ['json' => $json, 'errors' => [], 'stop' => []];
}

# Build add-div rows of the extra fields of one area for an administration form; the caption and the hint are escaped here because the row fragment takes them as markup
function getTplAddFieldRows(array $data = []): array {
    $rows = [];
    foreach (getFieldsInRows($data) as $row) {
        $rows[] = [
            'label_for' => $row['label_for'],
            'label_html' => htmlspecialchars($row['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'hint_html' => htmlspecialchars($row['hint'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'hint_id' => $row['hint_id'],
            'field_html' => $row['control_html'],
        ];
    }
    return $rows;
}

# Render one shared add-div date or datetime control with hidden canonical value field
function getTplAddDateTime(array $data = []): string {
    global $tpl;
    $name = $data['name'] ?? '';
    $time = $data['time'] ?? '';
    $with = $data['with'] ?? true;
    $max = $data['max'] ?? 16;
    $iscfg = !empty($data['is_config']);
    $time = $time ? substr($time, 0, $max) : ($with ? date('Y-m-d H:i') : '');
    static $fieldid = 0;
    $fieldid++;
    $type = $with ? 'datetime-local' : 'date';
    $pvalu = $with ? str_replace(' ', 'T', substr($time, 0, 16)) : substr($time, 0, 10);
    $hid = 'sl_datetime_hidden_'.$fieldid;
    $pid = 'sl_datetime_picker_'.$fieldid;
    $phold = $with ? 'YYYY-MM-DD HH:MM' : 'YYYY-MM-DD';
    return $tpl->getHtmlFrag('hidden', [
            'name_attr' => (string)$name,
            'value_attr' => (string)$time,
            'input_attr' => 'id="'.$hid.'"',
        ]).$tpl->getHtmlFrag('input', [
            'itype' => $type,
            'name_attr' => $pid,
            'value_attr' => $pvalu,
            'maxlength_num' => $max,
            'placeholder_text' => $phold,
            'is_config' => $iscfg,
            'aria_label' => (string)($data['label'] ?? ''),
            'input_attr' => 'id="'.$pid.'" data-sl-datetime-target="'.$hid.'" data-sl-datetime-kind="'.$type.'"',
        ]);
}

# Render the stored extra field values of one area as value rows; the shared class decides what is shown, and only a textarea arrives as parser output
function getTplViewFieldRows(array $data = []): string {
    global $tpl, $prs, $fld;
    $mod = strtolower($data['mod'] ?? '');
    $vals = json_decode($data['field'] ?? '', true);
    if (!$mod || !is_array($vals)) return '';
    $rows = '';
    foreach ($fld->getFieldView($prs, getFieldRules($mod), $vals, $mod) as $row) {
        $rows .= $tpl->getHtmlFrag('field-value', ['label' => $row['label_text'], 'value_html' => $row['value_html'], 'value_text' => $row['value_text']]);
    }
    return $rows;
}

# Render one full preview block from prepared source texts and dynamic fields; own is the file context of the parser, such as [forum, 0] of an unsaved post
function getTplPreviewContent(array $data = []): string {
    global $tpl, $prs;
    $title = $data['title'] ?? '';
    $texta = $data['texta'] ?? '';
    $textb = $data['textb'] ?? '';
    $field = $data['field'] ?? '';
    $mod = $data['mod'] ?? '';
    $own = $data['own'] ?? [];
    if ($title === '' && $texta === '' && $textb === '' && $field === '') return '';
    $bodya = $texta ? $prs->filterContent($texta, true, $mod, 0, '', $own, true) : '';
    $bodyb = $textb ? $prs->filterContent($textb, true, $mod, 0, '', $own, true) : '';
    $bodyc = $field ? getTplViewFieldRows(['field' => $field, 'mod' => $mod]) : '';
    return $tpl->getHtmlPart('preview', [
        'title' => _PREVIEW,
        'title_text' => (string)$title,
        'body_a' => $bodya,
        'body_b' => $bodyb,
        'body_c' => $bodyc,
    ]);
}

# Answer an editor text as the site renders it, after the save filter and room check, attachments in the file context of an unsaved text; it ends the answer for the frame
function getEditorPreview(): void {
    global $tpl;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        exit;
    }
    $text = filterSourceText(getVar('post', 'text', 'raw', ''), isAdmin(true));
    $store = getVar('post', 'store', 'raw', '');
    $mod = getVar('post', 'mod', 'var');
    $own = getUploadOwner($mod);
    $room = checkEditorTextRoom($text, is_string($store) ? $store : '');
    if ($room !== '') echo $tpl->getHtmlFrag('alert', ['text' => $room, 'is_warn' => true]);
    else echo getTplPreviewContent(['texta' => $text, 'mod' => $mod, 'own' => ($own !== '') ? [$own, 0] : []]);
    exit;
}

# The one pager of both themes: maxpg numbers round the current page between the first and the last, each marked far or near so a narrow pager can drop them
function getTplPagerView(int $num, int $pages, int $maxpg, callable $target, array $meta = []): string {
    global $tpl;
    if ($pages <= 1 && empty($meta['rows'])) return '';
    $num = max(1, min($num, $pages));
    $maxpg = max(1, $maxpg);
    $from = max(1, $num - intdiv($maxpg, 2));
    $to = min($pages, $from + $maxpg - 1);
    $from = max(1, $to - $maxpg + 1);
    $link = static function(int $page, array $opt) use ($tpl, $target): string {
        if ($page > 0) $opt += $target($page);
        return $tpl->getHtmlFrag('pager-link', $opt);
    };
    $step = static fn(int $page, string $label, string $rel): string => $link($page, [
        'label' => $label, 'title' => $label, 'is_cur' => $page === 0, 'is_nav' => true, 'icon_name' => 'chevron-'.($rel === 'prev' ? 'left' : 'right'), 'rel' => $rel,
        'key_name' => $rel === 'prev' ? 'ArrowLeft' : 'ArrowRight',
    ]);
    $dots = $tpl->getHtmlFrag('pager-link', ['is_dots' => true]);
    $items = '';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === $num || $i === 1 || $i === $pages || ($i >= $from && $i <= $to)) {
            $gap = ($i === 1 || $i === $pages) ? 0 : abs($i - $num);
            $items .= $link($i === $num ? 0 : $i, [
                'label' => (string)$i, 'title' => (string)$i, 'is_cur' => $i === $num, 'is_far' => $gap > 1, 'is_near' => $gap === 1,
            ]);
        }
        if ($i === 1 && $from > 2) $items .= $dots;
        if ($i === $to && $to < $pages - 1) $items .= $dots;
    }
    $view = [
        'overall' => _OVERALL, 'page_label' => _PAGE, 'page_of' => sprintf(_NUMOF, $num, $pages),
        'prev' => $step($num > 1 ? $num - 1 : 0, _BACK, 'prev'), 'items' => $items, 'next' => $step($num < $pages ? $num + 1 : 0, _NEXT, 'next'),
    ];
    $limit = intval($meta['limit'] ?? 0);
    $count = intval($meta['count'] ?? 0);
    if ($limit > 0 && $count > 0) {
        $view['range_from'] = ($num - 1) * $limit + 1;
        $view['range_to'] = min($num * $limit, $count);
        $view['range_text'] = sprintf(_PAGERANGE, $view['range_from'], $view['range_to']);
    }
    return $tpl->getHtmlFrag('pager', array_merge($view, $meta));
}

# Build a pager from a table COUNT query, or from the count the caller already holds, reading the current page from the request
function getTplPager(array $data = []): string {
    global $db, $afile;
    $limit = (int)($data['limit'] ?? 10);
    $maxpg = (int)($data['maxpg'] ?? 10);
    $table = $data['table'] ?? '';
    $field = $data['field'] ?? 'id';
    $where = $data['where'] ?? '';
    $mod = $data['mod'] ?? '';
    $anchor = $data['anchor'] ?? '';
    $n = $data['n'] ?? (defined('ADMIN_FILE') ? 'num' : 'page');
    $url = html_entity_decode($data['url'] ?? '', ENT_QUOTES, 'UTF-8');
    $targetid = (string)($data['target_id'] ?? '');
    $pushurl = !empty($data['push_url']);
    $wparams = (array)($data['where_params'] ?? []);
    $urlx = (array)($data['url_extra'] ?? []);
    if (isset($data['count'])) {
        $cnt = intval($data['count']);
    } else {
        [$cnt] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT('.$field.') FROM '.PREFIX_DB.$table.($where ? ' WHERE '.$where : ''), $wparams));
        $cnt = intval($cnt);
    }
    if ($cnt <= $limit) return '';
    $pages = (int)ceil($cnt / $limit);
    $num = max(1, min(getVar('get', $n, 'num', 1), $pages));
    $mkurl = static function(int $i) use ($mod, $url, $n, $anchor, $afile, $urlx): string {
        if (defined('ADMIN_FILE')) return HOME_PATH.$afile.'.php?'.$url.$n.'='.$i.$anchor;
        $params = $mod ? ['name' => $mod] : [];
        if ($urlx) $params = array_merge($params, $urlx);
        $params[$n] = $i;
        return getSeoUrl($params).$anchor;
    };
    $target = static function(int $i) use ($mkurl, $targetid, $pushurl): array {
        return $targetid !== ''
            ? ['query' => $mkurl($i), 'target_id' => $targetid, 'push_url' => $pushurl ? 'true' : 'false']
            : ['href' => $mkurl($i)];
    };
    return getTplPagerView($num, $pages, $maxpg, $target, ['count' => $cnt, 'limit' => $limit, 'page' => $limit]);
}

# Render one full admin module header with title, icon, tabs and a settings find, the action log for a super-admin; the title and a later open tab name the browser tab
function getTplAdminTabs(array $data = []): string {
    global $afile, $conf, $tpl, $adminvars;
    $title = _ADMINMENU;
    $icon = 'grid';
    $subtitle = (string)($data['subtitle_html'] ?? '');
    $name = filterWord(getVar('req', 'name', 'text', ''));
    $known = $name !== '' && isset($conf['modules'][$name]) && is_array($conf['modules'][$name]);
    if ($known) {
        if (trim((string)($conf['modules'][$name]['lang'] ?? '')) !== '') $title = getModuleLabel($name);
        $ico = trim((string)($conf['modules'][$name]['icon'] ?? ''));
        if (preg_match('/^[a-z0-9-]+$/', $ico)) $icon = $ico;
    }
    $links = $data['links'] ?? [];
    if (!$links) {
        $ops = $data['ops'] ?? [];
        $tabs = $data['tabs'] ?? [];
        $active = (int)($data['tab'] ?? 0);
        $part = (array)($data['part'] ?? []);
        foreach ($tabs as $idx => $label) {
            $href = HOME_PATH.$afile.'.php?'.($ops[$idx] ?? '');
            $links[] = [
                'href' => $href,
                'hx_get' => in_array($idx, $part, true) ? $href : '',
                'is_active' => $idx === $active,
                'label' => $label,
                'title' => $label,
            ];
        }
    }
    if ($known && isAdmin(true)) $links[] = ['href' => HOME_PATH.$afile.'.php?name=security&op=logview&file=admin&modul='.$name, 'label' => _SEC_STAT_A];
    $adminvars['section'] = $title;
    $adminvars['view'] = '';
    $tabs = '';
    foreach ($links as $idx => $link) {
        if ($idx > 0 && !empty($link['is_active'])) $adminvars['view'] = (string)($link['label'] ?? '');
        $tabs .= $tpl->getHtmlFrag('tabs-link', [
            'href' => (string)($link['href'] ?? '#'),
            'hx_get' => (string)($link['hx_get'] ?? ''),
            'is_active' => !empty($link['is_active']),
            'label' => (string)($link['label'] ?? ''),
            'link_attr' => (string)($link['link_attr'] ?? ''),
            'rel' => (string)($link['rel'] ?? ''),
            'title' => (string)($link['title'] ?? ($link['label'] ?? '')),
        ]);
    }
    return $tpl->getHtmlPart('module-head', [
        'find' => (string)($data['find'] ?? ''),
        'flash_html' => getFlashHtml(),
        'icon' => $icon,
        'is_runtime' => !empty($data['is_runtime']),
        'subtitle_html' => $subtitle,
        'tabs_id' => (string)($data['tabs_id'] ?? ($data['id'] ?? '')),
        'tabs_index' => array_key_exists('tabs_index', $data) ? (string)$data['tabs_index'] : (array_key_exists('tab', $data) ? (string)$data['tab'] : ''),
        'tabs_html' => $tabs,
        'tabs_sync_selector' => (string)($data['tabs_sync_selector'] ?? ''),
        'title' => $title,
    ]);
}

# The rows of one panel list page from the cookie rows, else the list's own number; "all" holds while the filter fits in LIST_MAX, and a closure counts it only then
function getListLimit(int $base, int|Closure $total): int {
    $base = max(1, $base);
    if (!defined('ADMIN_FILE')) return $base;
    $pick = getCookies('rows');
    if ($pick === 'all') return ((is_int($total) ? $total : $total()) <= LIST_MAX) ? LIST_MAX : $base;
    return ((string)max(5, min(LIST_MAX, intval($pick))) === $pick) ? intval($pick) : $base;
}

# One meter used of a real limit; a limit that is no pressure, as a progress or a rotating log, gives its own tone, a row of a list its own figure
function getTplMeter(string $label, int|float $part, int|float $whole, string $note = '', string $tone = '', string $value = '', bool $row = false): string {
    global $tpl;
    $pct = ($whole > 0) ? $part * 100 / $whole : 0;
    return $tpl->getHtmlFrag('meter', [
        'label' => $label,
        'value' => ($value !== '') ? $value : filterDecimal($pct, ($pct < 10) ? 1 : 0).'%',
        'pct' => ($pct > 0) ? (int)round(max(1, min(100, $pct))) : 0,
        'tone' => ($tone !== '') ? $tone : getPercentTone($pct),
        'note' => $note,
        'is_row' => $row,
    ]);
}

# The keys of a cell cut short: a long value opens whole behind the mark of the system, a short one keeps the title, since both on one cell would show twice
function getTplLongCell(string $text, string $label): array {
    global $tpl;
    if (mb_strlen($text) < CELL_LONG) return ($text === '') ? [] : ['title_text' => $text];
    return ['prefix_html' => $tpl->getHtmlFrag('popover', ['tip_text' => $text, 'tip_label' => _DETAILS.': '.$label])];
}

# Render one panel list of the canon from the meaning of its rows; the foot sends one field group per action, and an own number in base brings the rows chip
function getTplAdminList(array $data): string {
    global $afile, $tpl;
    $href = (string)($data['href'] ?? '');
    $count = intval($data['count'] ?? 0);
    $limit = intval($data['limit'] ?? 0);
    $base = intval($data['base'] ?? 0);
    $page = max(1, intval($data['page'] ?? 1));
    $pages = intval($data['pages'] ?? 1);
    $maxpg = intval($data['maxpg'] ?? 8);
    $list = array_values((array)($data['rows'] ?? []));
    $form = 'sl-list-pick';
    $check = array_filter($list, static fn(array $row): bool => !empty($row['drop'])) !== [];
    $head = (array)($data['head'] ?? []);
    if ($check) {
        $head[] = ['is_col_check' => true, 'nosort' => true, 'content' => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'markcheck', 'input_id' => 'markcheck', 'is_check' => true,
            'aria_label' => _CHECKALL, 'input_attr' => 'title="'._CHECKALL.'" form="'.$form.'"'])];
    }
    $names = array_map(static fn(array $col): string => trim(html_entity_decode(strip_tags((string)($col['content'] ?? '')), ENT_QUOTES, 'UTF-8')), $head);
    $rows = (string)($data['rows_html'] ?? '');
    $sets = [];
    $words = [];
    foreach ($list as $at => $row) {
        $drop = (array)($row['drop'] ?? []);
        $tip = (array)($row['details'] ?? []);
        $cells = [];
        foreach ((array)($row['cells'] ?? []) as $i => $cell) {
            if (array_key_exists('moment', $cell)) {
                $mark = is_int($cell['moment']) ? $cell['moment'] : (int)strtotime((string)$cell['moment']);
                $time = ['stamp' => date('c', $mark), 'content_text' => date(_TIMESTRING, $mark)];
                $cell = ($mark > 0) ? $time + $cell : $cell + ['has_content_text' => true, 'content_text' => ''];
            }
            if ($tip && empty($cell['is_col_id'])) {
                $cell['prefix_html'] = $tpl->getHtmlFrag('popover', ['items' => $tip, 'tip_label' => _DETAILS.': '.($row['name'] ?? ''), 'tip_id' => 'sl-tip-'.($at + 1)])
                    .($cell['prefix_html'] ?? '');
                $tip = [];
            }
            if (isset($cell['dial'])) {
                $items = $drop ? [...$cell['dial'], $drop + ['later' => 'gone del']] : $cell['dial'];
                $cell['content_html'] = $tpl->getHtmlFrag('dial', ['dial_title' => $names[$i] ?? '', 'dial' => $items]);
            }
            $stat = (array)($cell['stat'] ?? []);
            if (preg_match('#^(gone|lamp) #', (string)($stat['later'] ?? ''))) $sets[empty($stat['is_on']) ? 'on' : 'off'] ??= (array)($stat['fields'] ?? []);
            $cells[] = $cell + ['label' => $names[$i] ?? ''];
        }
        if ($drop) {
            $verb = (($drop['later'] ?? '') === 'gone move') ? 'move' : 'del';
            $sets[$verb] ??= (array)($drop['fields'] ?? []);
            $words[$verb] ??= ($verb === 'move') ? (string)($drop['title'] ?? '') : _DELETE;
        }
        if ($check) {
            $box = ['name_attr' => 'id[]', 'value_attr' => (string)($drop['fields']['id[]'] ?? ''), 'is_check' => true, 'aria_label' => (string)($row['name'] ?? ''),
                'input_attr' => 'form="'.$form.'"'];
            $cells[] = ['is_col_check' => true, 'content_html' => $drop ? $tpl->getHtmlFrag('checkbox', $box) : ''];
        }
        $keep = array_diff_key($row, ['cells' => 0, 'details' => 0, 'drop' => 0]);
        $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => $cells])] + $keep);
    }
    $bulk = [];
    if ($check) {
        $acts = array_intersect_key(['on' => _ACTIVATE, 'off' => _DEACTIVATE] + $words, $sets);
        $groups = [];
        foreach (array_keys($acts) as $key) {
            $hide = array_diff_key($sets[$key], ['id[]' => 0]);
            $groups[] = ['key' => $key, 'is_off' => $groups !== [], 'hidden' => array_map(static fn($k, $v): array => ['nameattr' => (string)$k, 'valueattr' => (string)$v],
                array_keys($hide), $hide)];
        }
        $bulk = [
            'form_id' => $form,
            'action_url' => HOME_PATH.$afile.'.php',
            'groups' => $groups,
            'options' => array_map(static fn(string $key, string $label): array => ['value_attr' => $key, 'label_text' => $label], array_keys($acts), $acts),
            'later' => 'gone '.implode(' ', array_map(static fn(string $key): string => $key.'='.$key, array_keys($acts))),
            'upto' => intval($data['upto'] ?? 0),
        ];
    }
    $density = getCookies('density');
    if (!in_array($density, ['full', 'compact', 'cards'], true)) $density = 'standard';
    $views = [];
    $kinds = ['standard' => [_ADM_STD, 'table'], 'full' => [_ADM_FULL, 'text-wrap'], 'compact' => [_ADM_DENSE, 'list'], 'cards' => [_ADM_CARDS, 'grid-3x2-gap']];
    foreach ($kinds as $key => [$title, $icon]) $views[] = ['key' => $key, 'title' => $title, 'icon_name' => $icon, 'is_now' => $key === $density];
    $none = (string)($data['none_text'] ?? _NO_INFO);
    $link = static fn(int $i): array => ['href' => $href.'num='.$i];
    $meta = ['count' => $count, 'limit' => $limit, 'is_list' => true];
    $range = ($limit > 0) ? sprintf(_PAGERANGE, ($page - 1) * $limit + 1, min($page * $limit, $count)) : '';
    $said = ($range !== '') ? $range.' · ' : '';
    $wide = number_format($count, 0, '', ' ');
    $per = [];
    if ($base > 0 && $limit > 0) {
        $fmt = static fn(int $num): string => number_format($num, 0, '', ' ');
        $pick = getCookies('rows');
        $all = $pick === 'all' && $count <= LIST_MAX;
        $word = mb_strtolower(_ALL);
        $ladder = [];
        foreach ([10, 25, 50, 100, 250] as $num) {
            $ladder[] = ['value' => $num, 'key' => $num, 'text' => _ADM_ROWWORD, 'is_now' => !$all && $num === $limit, 'is_home' => $num === $base, 'is_same' => $num >= $count,
                'hint' => ($num >= $count) ? '= '.$word : sprintf(_ADM_ROWSPG, $fmt((int)ceil($count / $num)))];
        }
        $ladder[] = ['value' => 'all', 'key' => $word, 'text' => _ADM_ROWSONE, 'is_now' => $all, 'is_off' => $count > LIST_MAX,
            'hint' => ($count > LIST_MAX) ? sprintf(_ADM_ROWSCAP, $fmt(LIST_MAX)) : $wide];
        $per = [
            'pick' => $pick, 'base' => $base, 'limit' => $limit, 'page' => $page, 'href' => $href, 'ladder' => $ladder, 'said' => trim(strstr(_PAGERANGE, '%', true)),
            'shown' => $all ? $word : (($page - 1) * $limit + 1).'–'.min($page * $limit, $count),
            'own' => (!in_array($limit, [10, 25, 50, 100, 250], true) && (string)$limit === $pick) ? $limit : '', 'is_free' => $pick === '', 'max' => LIST_MAX,
            'span' => '5–'.$fmt(LIST_MAX), 'tip' => sprintf(_ADM_ROWSTIP, 5, $fmt(LIST_MAX)), 'back' => sprintf(_ADM_ROWSBACK, $base), 'note' => _ADM_ROWSNOTE,
            'say_new' => _ADM_ROWSNEW, 'say_all' => sprintf(_ADM_ROWSALLN, $fmt(LIST_MAX)), 'say_def' => _ADM_ROWSDEF,
        ];
    }
    return $tpl->getHtmlPart('admin-list', [
        'head_html' => (string)($data['head_html'] ?? ''),
        'note_html' => (string)($data['note_html'] ?? ''),
        'pane' => (array)($data['pane'] ?? []),
        'top_html' => ($rows !== '') ? getTplPagerView($page, $pages, $maxpg, $link, $meta + ['is_top' => true, 'rows' => $per ? $per + ['id' => 'sl-rows-top'] : []]) : '',
        'pager_html' => ($rows !== '') ? getTplPagerView($page, $pages, $maxpg, $link, $meta + ['rows' => $per ? $per + ['id' => 'sl-rows-end', 'is_up' => true] : []]) : '',
        'is_list' => true,
        'bulk' => $bulk,
        'count' => $count,
        'picked' => sprintf(_ADM_PICKED, 0),
        'picked_wide' => sprintf(_ADM_PICKED, $wide),
        'is_sweep' => !empty($bulk['upto']) && $pages > 1,
        'all_text' => sprintf(_ADM_ALLN, $wide),
        'table_html' => ($rows !== '') ? $tpl->getHtmlFrag('table', [
            'is_wrapless' => true,
            'is_fixed' => !empty($data['is_fixed']),
            'density' => $density,
            'sort_key' => (string)($data['key'] ?? filterWord(getVar('get', 'name', 'text', ''))),
            'head' => $head,
            'rows_html' => $rows,
        ]) : '',
        'views' => $views,
        'none_text' => $none,
        'is_calm' => !empty($data['is_calm']),
        'foot_html' => (string)($data['foot_html'] ?? ''),
        'said' => ($rows !== '') ? $said._OVERALL.' '.$count : $none,
    ]);
}

# Answer one portion of "pick all N" when a foot handler is posted with upto: ascending keys of the list above the cursor up to the bound, each passed to the action
function setListPortion(string $key, string $from, string $where, array $pars, Closure $act, int $limit = 100): bool {
    global $db;
    $upto = getVar('post', 'upto', 'num', 0);
    if ($upto < 1) return false;
    $after = getVar('post', 'after', 'num', 0);
    $cond = (string)preg_replace('/^WHERE\s+/i', '', trim($where));
    $sql = 'SELECT '.$key.' AS id FROM '.$from.' WHERE '.(($cond !== '') ? '('.$cond.') AND ' : '').$key.' > :after AND '.$key.' <= :upto'
        .' ORDER BY '.$key.' ASC LIMIT '.($limit + 1);
    $ids = array_map('intval', array_column($db->getSqlRows($db->getSqlQuery($sql, $pars + ['after' => $after, 'upto' => $upto])) ?: [], 'id'));
    $out = ['done' => 0, 'fail' => 0, 'after' => $after, 'more' => count($ids) > $limit];
    foreach (array_slice($ids, 0, $limit) as $id) {
        $out[$act($id) ? 'done' : 'fail']++;
        $out['after'] = $id;
    }
    setAdminLog(['result' => $out['fail'] ? 'refused' : 'done', 'num' => $out['done'] + $out['fail']]);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($out);
    return true;
}

# Render one admin info page with an optional in-place editor; the doc is stored raw with LF endings, as filterHtml would mangle Markdown and a form submits CRLF
function setTplAdminInfoPage(array $data = []): void {
    global $afile, $locale, $conf, $tpl, $prs;
    $name = filterWord(getVar('get', 'name', 'text', ''));
    $ops = $data['ops'] ?? [];
    $tabs = $data['tabs'] ?? [];
    $tab = array_key_exists('tab', $data) ? (int)$data['tab'] : ($tabs ? count($tabs) - 1 : 0);
    $mod = $data['mod'] ?? 'info';
    $action = $data['action_url'] ?? (HOME_PATH.$afile.'.php?name='.$name.'&op=info');
    $save = $data['save_flag'] ?? 'save_info';
    $submit = $data['submit_label'] ?? _SAVECHANGES;
    $edit = !empty($conf['adminfo']) && ($data['can_edit'] ?? true);
    $fdoc = static function(string $path): string {
        foreach (['.html', '.md'] as $ext) {
            if (file_exists($path.$ext)) return $path.$ext;
        }
        return '';
    };
    if (!empty($data['base'])) {
        $base = (string)$data['base'];
    } elseif ($name) {
        $mbase = BASE_DIR.'/modules/'.$name.'/admin/info';
        $abase = BASE_DIR.'/admin/info/'.$name;
        if ($fdoc($mbase.'/'.$locale) !== '') {
            $base = $mbase;
        } elseif ($fdoc($abase.'/'.$locale) !== '') {
            $base = $abase;
        } else {
            $base = $mbase;
        }
    } else {
        $base = '';
    }
    $path = $fdoc($base.'/'.$locale);
    if ($path === '') $path = $base.'/'.$locale.'.html';
    $text = file_exists($path) ? (string)file_get_contents($path) : _NO_INFO;
    $alert = '';
    if ($edit && getVar('post', $save, 'num', 0)) {
        if (!checkSiteToken()) {
            $alert = $tpl->getHtmlFrag('alert', [
                'alert_attr' => 'data-sl-autohide="5000"',
                'is_flash' => true,
                'is_warn' => true,
                'text' => _TOKENMISS,
            ]);
            $text = getVar('post', 'text', 'raw', $text);
        } else {
            $content = trim(str_replace(["\r\n", "\r"], "\n", getVar('post', 'text', 'raw', '')));
            $room = checkEditorTextRoom($content, 'config');
            if ($room !== '') {
                $text = $content;
                $alert = $tpl->getHtmlFrag('alert', [
                    'alert_attr' => 'data-sl-autohide="5000"',
                    'is_flash' => true,
                    'is_warn' => true,
                    'text' => $room,
                ]);
            } elseif ($content !== '') {
                $dir = dirname($path);
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $fp = fopen($path, 'wb');
                if ($fp !== false) {
                    fwrite($fp, $content);
                    fclose($fp);
                    $text = $content;
                    $alert = $tpl->getHtmlFrag('alert', [
                        'alert_attr' => 'data-sl-autohide="5000"',
                        'is_flash' => true,
                        'type' => 'success',
                        'text' => _SUCCSAVE,
                    ]);
                }
            }
        }
    }
    $info = $tpl->getHtmlPart('div', ['class' => 'sl-markdown', 'content_html' => $prs->filterContent($text, false, $mod)]);
    $body = ($alert !== '' ? $alert : '').$tpl->getHtmlPart('box', ['content_html' => $info]);
    $head = strtolower($_SERVER['HTTP_HX_REQUEST'] ?? '');
    if ($head === 'true') {
        echo $body;
        return;
    }
    setHead();
    $cont = getTplAdminTabs([
        'ops' => $ops,
        'subtitle_html' => (string)($data['subtitle_html'] ?? ''),
        'tabs' => $tabs,
        'tab' => $tab,
    ]);
    if ($edit && file_exists($path)) $cont .= checkPerms($path);
    $cont .= $body;
    if ($edit) {
        $rows = [[
            'label_html' => '',
            'field_html' => getTplTextarea(['label' => _ADMININFO, 'id' => '1', 'name' => 'text', 'value' => $text, 'mod' => $mod, 'rows' => '25', 'store' => 'config']),
            'is_full' => true,
            'field_unwrapped' => true,
        ]];
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
            'action_url' => $action,
            'hidden' => [
                ['nameattr' => $save, 'valueattr' => '1'],
                ['nameattr' => 'token', 'valueattr' => getSiteToken()],
            ],
            'rows' => $rows,
            'submit_label' => $submit,
        ])]);
    }
    echo $cont;
    setFoot();
}

# Render one radio group that carries the role and the caption reference of its row, so a reader landing on a radio hears the question and not only Yes
function getTplRadioGroup(array $data = []): string {
    global $tpl;
    $name = $data['name'] ?? '';
    $value = $data['value'] ?? '';
    $options = $data['options'] ?? [];
    $vals = array_map(static fn(array $opt): string => (string)($opt['value'] ?? ''), $options);
    $swit = array_key_exists('switch', $data) ? (bool)$data['switch'] : count($vals) === 2 && in_array('1', $vals, true) && in_array('0', $vals, true);
    $items = '';
    foreach ($options as $option) {
        $valu = (string)($option['value'] ?? '');
        $items .= $tpl->getHtmlFrag('radio', [
            'input_attr' => (string)($option['input_attr'] ?? ''),
            'is_checked' => (string)$value === $valu,
            'label_html' => (string)($option['label_html'] ?? ''),
            'label_text' => (string)($option['label'] ?? ''),
            'name_attr' => $name,
            'value_attr' => $valu,
        ]);
    }
    return $tpl->getHtmlFrag('block-content', [
        'switch' => $swit,
        'is_radio_group' => true,
        'labelledby' => (string)($data['labelledby'] ?? ''),
        'describedby' => (string)($data['describedby'] ?? ''),
        'content' => $items,
    ]);
}

# Render the user autocomplete input; a caller naming a card container gets the richer bounded answer of the route, every other caller the flat list of names
function getTplUserSearchInput(array $data = []): string {
    global $tpl;
    $name = $data['name'] ?? 'uname';
    $inpid = $data['input_id'] ?? $name;
    $list = $data['list_id'] ?? ($inpid.'_list');
    $endpoint = $data['endpoint'] ?? HOME_PATH.'index.php?go=1&op=getUserList';
    $mlen = (int)($data['minlength'] ?? 1);
    $tip = (string)($data['tip'] ?? '');
    $tiphtml = '';
    if ($tip !== '') {
        $tiphtml = getTplTitleTip($tip);
    }
    return $tpl->getHtmlFrag('input', [
        'card_attr' => (string)($data['card'] ?? ''),
        'endpoint_attr' => $endpoint,
        'is_required' => true,
        'is_user_search' => true,
        'itype' => 'text',
        'input_id' => $inpid,
        'list_id' => $list,
        'maxlength_num' => (int)($data['maxlength'] ?? 25),
        'minlength_num' => $mlen,
        'name_attr' => $name,
        'tip_html' => $tiphtml,
        'token_attr' => $data['token'] ?? getSiteToken(),
        'value_attr' => (string)($data['value'] ?? ''),
    ]);
}


# Render the extra field rows of one area for a site form; the hint of a row carries the message of a refused value as well
function getTplFieldsIn(array $data = []): string {
    global $tpl;
    $out = '';
    foreach (getFieldsInRows($data) as $row) {
        $out .= $tpl->getHtmlFrag('form-field-row', ['field_html' => $row['control_html']] + $row);
    }
    return $out;
}

# Answer the extra fields of an area as rows of data; values come from the posted form, else the stored JSON, else the defaults for a new entity only
function getFieldsInRows(array $data = []): array {
    global $tpl, $fld;
    $rules = getFieldRules($data['mod'] ?? '');
    $post = getVar('post', 'field[]', '', []);
    $vals = $post ?: json_decode($data['field'] ?? '', true);
    if (!is_array($vals)) $vals = [];
    foreach ((!$post && !empty($data['new'])) ? $rules : [] as $name => $rule) {
        if (!in_array($rule['default'], ['', [], null], true)) $vals[$name] = $rule['default'];
    }
    $rows = [];
    foreach ($fld->getFieldForm($tpl, $rules, $vals, $post ? $fld->checkFieldValues($rules, $post) : []) as $row) {
        $hint = trim($row['hint_text'].' '.$row['error_text']);
        $rows[] = ['label' => $row['label_text'], 'label_for' => $row['label_for'], 'label_id' => $row['label_id'], 'hint' => $hint, 'hint_id' => $row['hint_id'],
            'control_html' => $row['field_html']];
    }
    return $rows;
}

# Map a declared storage to its room by column type, which a test compares with storage/update/sql/table.sql; an unknown store gets TEXT, the narrowest
function getEditorRoomData(string $store): array {
    $room = [
        'comment.body' => 'mediumtext', 'forum.body' => 'mediumtext', 'message.body' => 'mediumtext', 'newsletter.body' => 'mediumtext',
        'privat.body' => 'mediumtext', 'nodes.body' => 'mediumtext', 'nodes.intro' => 'text',
        'users.block' => 'text', 'users.sig' => 'text',
        'config' => 'config',
    ];
    $byte = ['text' => 65535, 'mediumtext' => 16777215, 'config' => 65535];
    $kind = $room[$store] ?? 'text';
    $size = $byte[$kind];
    return ['kind' => $kind, 'bytes' => $size, 'embed' => $size >= intdiv(Parser::EMBEDMAX + 2, 3) * 4 + 32];
}

# Refuse a text its column cannot hold before the query runs; length is strlen(), never mb_strlen(), because a column bounds bytes and not characters
function checkEditorTextRoom(string $text, string $store): string {
    $room = getEditorRoomData($store);
    if (strlen($text) > $room['bytes']) return sprintf(_ETEXTLONG, filterSize($room['bytes']));
    if (stripos($text, 'data:') === false) return '';
    if (!preg_match_all('#data:([a-z0-9.+\-]+)/([a-z0-9.+\-]+);base64,([A-Za-z0-9+/]+={0,2})#i', $text, $mats, PREG_SET_ORDER)) return '';
    if (!$room['embed']) return _ENOEMBED;
    foreach ($mats as $mat) {
        if (strtolower($mat[1]) !== 'image' || !in_array(strtolower($mat[2]), Parser::EMBEDIMG, true)) return _ERROR_FILE;
        $bin = base64_decode($mat[3], true);
        if ($bin === false || strlen($bin) > Parser::EMBEDMAX) return _ERROR_SIZE;
    }
    return '';
}

# Render the rich-text editor textarea over the stored source as it is; the call site declares the store, because neither the field name nor the upload folder names a column
function getTplTextarea(array $data = []): string {
    $id = (string)($data['id'] ?? '1');
    $name = (string)($data['name'] ?? '');
    $value = (string)($data['value'] ?? '');
    $mod = (string)($data['mod'] ?? '');
    $rows = (int)($data['rows'] ?? 5);
    $phld = (string)($data['placeholder'] ?? '');
    $required = in_array($data['required'] ?? '', [true, 1, '1', 'required'], true);
    $stloc = substr(_LOCALE, 0, 2);
    $key = getEditorKey();
    $fmt = getEditorMode($key);
    $desc = $value ?: filterSourceText(getVar('post', $name, 'raw', ''), isAdmin(true));
    $rul = getUploadRuleData(strtolower($mod));
    $store = (string)($data['store'] ?? '');
    return Editor::getContent([
        'labelledby' => (string)($data['labelledby'] ?? ''),
        'describedby' => (string)($data['describedby'] ?? ''),
        'label' => (string)($data['label'] ?? ''),
        'editor' => $key,
        'format' => $fmt,
        'id' => $id,
        'name' => $name,
        'value' => $desc,
        'rows' => $rows,
        'placeholder' => $phld,
        'required' => $required,
        'autofocus' => !empty($data['autofocus']),
        'stloc' => $stloc,
        'mod' => $mod,
        'rule' => $rul,
        'store' => $store,
        'room' => getEditorRoomData($store),
        'preview' => 'text',
    ]);
}

# Build data attributes for inserting text into the active content editor
function getTplEditorInsertAttr(string $command, string $value, string $editorId = '1'): string {
    $esc = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return 'data-sl-editor-insert="'.$esc($command).'" data-sl-editor-id="'.$esc($editorId).'" data-sl-editor-value="'.$esc($value).'"';
}

# Render the shared "new" badge for fresh content
function getTplNewGraphic(string $time): string {
    global $tpl;
    $mark = strtotime($time);
    $age = time() - $mark;
    if ($age >= 2592000) return '';
    if ($age < 3600) [$tier, $label] = ['now', _NEWNOW];
    elseif ($age < 86400) [$tier, $label] = ['day', _NEWTODAY];
    elseif ($age < 259200) [$tier, $label] = ['days', _NEWLAST3DAYS];
    elseif ($age < 604800) [$tier, $label] = ['week', _NEWTHISWEEK];
    else [$tier, $label] = ['month', _NEWMONTH];
    return $tpl->getHtmlFrag('fresh', ['tier' => $tier, 'is_'.$tier => true, 'title' => (string)$label, 'date_text' => format_time($time), 'date_iso' => date('c', $mark)]);
}

# One admin dial action as a POST form, never a link a prefetcher follows; the form id carries a salt, since a swapped fragment counts from one again
function getTplPostAction(array $hide, string $icon, string $title, string $confirm = ''): array {
    global $afile, $tpl;
    static $seq = 0;
    static $salt = '';
    if ($salt === '') $salt = substr(sha1(random_bytes(8)), 0, 6);
    $hide['token'] = getSiteToken((string)($hide['name'] ?? 'ajax'));
    $html = '';
    foreach ($hide as $key => $val) {
        $html .= $tpl->getHtmlFrag('hidden', ['name_attr' => (string)$key, 'value_attr' => (string)$val, 'input_attr' => '']);
    }
    $out = ['href' => HOME_PATH.$afile.'.php', 'form_id' => 'slpost'.$salt.(++$seq), 'hidden' => $html, 'fields' => $hide, 'icon_name' => $icon, 'title' => $title];
    if ($confirm !== '') $out['confirm_text'] = $confirm;
    return $out;
}

# Answer every word of the file window for the editor driver and the form row alike; the limit sentence comes from the place rule and is empty without one
function getFileManagerText(array $rule = []): array {
    $exts = array_values(array_filter(array_map('trim', explode(',', (string)($rule['extensions'] ?? '')))));
    $mbyt = (int)($rule['maxbytes'] ?? 0);
    $mwid = (int)($rule['maxwidth'] ?? 0);
    $mhei = (int)($rule['maxheight'] ?? 0);
    $lims = [implode(', ', $exts)];
    if ($mbyt > 0) $lims[] = filterSize($mbyt);
    if ($mwid > 0 && $mhei > 0) $lims[] = $mwid.' × '.$mhei.' px';
    return [
        'labels' => [
            'upload' => _ERROR_UP,
            'load' => _ERROR,
            'fileup' => _FILEUP,
            'toobig' => _ERROR_SIZE,
            'big' => _ERROR_BIG,
            'badtype' => _ERROR_FILE,
            'noembed' => _ENOEMBED,
            'insert' => _INSERTIMG,
            'insobj' => _EINSOBJ,
            'download' => _DOWNLOAD,
            'zip' => _EDITOR_ZIP,
            'delete' => _DELETE,
            'acts' => _FUNCTIONS,
            'mark' => _EDITOR_MARK,
            'marked' => _EDITOR_MARKED,
            'insimgs' => _EDITOR_INSIMGS,
            'insobjs' => _EDITOR_INSOBJS,
            'askdel' => _EDITOR_ASKDEL,
            'askdels' => _EDITOR_ASKDELS,
            'preview' => _PREVIEW,
            'empty' => _EDITOR_EMPTY,
            'emptywhy' => _EDITOR_EMPTYWHY,
            'none' => _NONE_FOUND,
            'nonewhy' => _EDITOR_NONEWHY,
            'fail' => _EDITOR_FAIL,
            'failwhy' => _EDITOR_FAILWHY,
            'reset' => _FRESET,
            'retry' => _RETRY,
            'queue' => _EDITOR_QUEUE,
            'queueend' => _EDITOR_QUEUEEND,
            'quota' => _NUMOF,
            'more' => _EDITOR_MORE,
            'mynote' => _EDITOR_MYNOTE,
            'name' => _NAME,
            'type' => _TYPE,
            'size' => _SIZE,
            'dim' => _EDITOR_DIM,
            'date' => _DATE,
            'perms' => _EDITOR_PERMS,
            'owner' => _EDITOR_OWNER,
            'addr' => _EDITOR_ADDR,
        ],
        'panes' => [
            'up' => [_EMODEUPLOAD, _EMODEUPLOADINFO],
            'url' => [_EDITOR_LINK, _EDITOR_LINKINFO],
            'lib' => [_EDITOR_MY, _EDITOR_MYINFO],
        ],
        'limits' => implode(' · ', $lims),
    ];
}

# Answer what one upload place lets a caller draw; the extensions are lowercased here, because the client compares a picked name against them in lower case
function getUploadPlaceView(array $rule): array {
    $mod = (string)$rule['mod'];
    $upl = $mod !== '' && checkEditorUploadAccess($mod, $rule);
    $exts = array_values(array_filter(array_map('trim', explode(',', strtolower((string)$rule['extensions'])))));
    return [
        'mod' => $mod,
        'ops' => (array)$rule['ops'],
        'able' => $upl,
        'moder' => $upl && checkUploadModer($mod),
        'exts' => $exts,
        'accept' => implode(',', array_map(static fn(string $ext): string => '.'.$ext, $exts)),
    ];
}

# Build the file manager window of one place; its rights come from the place rule and its routes, never from the caller, and its runtime loads once per request
function getFileManagerWindow(array $opt): string {
    global $tpl;
    static $done = false;
    $head = '';
    if (!$done) {
        $done = true;
        $head = $tpl->getHtmlPart('file-manager-templates', []).Editor::getAssetTags([], ['plugins/system/filemanager.js']);
    }
    $rul = getUploadPlaceRule((string)($opt['place'] ?? ''));
    $fld = !empty($opt['is_field']);
    $see = getUploadPlaceView($rul);
    $mod = $see['mod'];
    $ops = $see['ops'];
    $upl = $see['able'];
    $mdr = $see['moder'];
    $txt = getFileManagerText($rul);
    return $head.$tpl->getHtmlPart('file-manager', [
        'panel_id' => (string)($opt['panel'] ?? ''),
        'title_id' => (string)($opt['title'] ?? ''),
        'msg_id' => (string)($opt['msg'] ?? ''),
        'file_id' => (string)($opt['upfile'] ?? ''),
        'embed_id' => (string)($opt['embed'] ?? ''),
        'object_id' => (string)($opt['object'] ?? ''),
        'url_id' => (string)($opt['url'] ?? ''),
        'alt_id' => (string)($opt['alt'] ?? ''),
        'urlalt_id' => (string)($opt['urlalt'] ?? ''),
        'editor_id' => (string)($opt['editor'] ?? ''),
        'is_field' => $fld,
        'can_upload' => $upl,
        'can_list' => $upl,
        'can_link' => !empty($rul['canlink']) && empty($opt['is_nolink']),
        'can_embed' => !$fld && !empty($opt['can_embed']),
        'can_zip' => $mdr && in_array('editorArchive', $ops, true),
        'can_delete' => $mdr && in_array('editorDelete', $ops, true),
        'accept_attr' => $see['accept'],
        'embed_accept' => (string)($opt['embed_accept'] ?? ''),
        'embed_types' => (string)($opt['embed_types'] ?? ''),
        'title_label' => _EUPLOAD,
        'close_label' => _CLOSE,
        'move_label' => _EMOVEWIN,
        'expand_label' => _EEXPAND,
        'restore_label' => _ERESTORE,
        'add_label' => _ADD,
        'store_label' => _EDITOR_STORE,
        'up_label' => _EMODEUPLOAD,
        'up_note' => _EMODEUPLOADINFO,
        'up_why' => _EDITOR_NOUP,
        'link_label' => _EDITOR_LINK,
        'link_note' => _EDITOR_LINKNOTE,
        'link_lead' => _EDITOR_LINKINFO,
        'link_warn' => _EDITOR_LINKWARN,
        'embed_label' => _EMODEEMBED,
        'embed_note' => _EMODEEMBEDINFO,
        'embed_why' => _ENOEMBED,
        'files_label' => _EDITOR_MY,
        'files_note' => '',
        'files_why' => _EDITOR_NOMY,
        'quota_text' => $upl ? sprintf(_NUMOF, '—', filterSize((int)$rul['maxquota'])) : _EDITOR_NOUP,
        'module_text' => sprintf(_EDITOR_MODULE, $mod),
        'address_label' => _EIMGURL,
        'alt_label' => _DESCRIPTION,
        'insas_label' => _EDITOR_INSAS,
        'image_label' => _IMG,
        'object_label' => _EINSOBJ,
        'nofile_label' => _ENOFILE,
        'drop_label' => _EDROPFILES,
        'limits_text' => $txt['limits'],
        'stop_label' => _EDITOR_STOP,
        'used_label' => _EDITOR_USED,
        'room_label' => _EDITOR_ROOM,
        'room_text' => (string)($opt['room_text'] ?? ''),
        'full_label' => _EDITOR_FULL,
        'filter_label' => _EDITOR_FILTER,
        'all_label' => _ALL,
        'images_label' => _EDITOR_IMAGES,
        'others_label' => _EDITOR_OTHERS,
        'list_label' => _EDITOR_ASLIST,
        'tiles_label' => _EDITOR_ASGRID,
        'markall_label' => _EDITOR_MARKALL,
        'name_label' => _NAME,
        'type_label' => _TYPE,
        'size_label' => _SIZE,
        'date_label' => _DATE,
        'props_label' => _EDITOR_PROPS,
        'props_note' => _EDITOR_NOTE,
        'zip_label' => _EDITOR_ZIP,
        'delete_label' => _DELETE,
        'unmark_label' => _EDITOR_UNMARK,
        'wait_text' => _EDITOR_WAIT,
        'insert_label' => _EDITOR_INSERT,
        'refresh_label' => _UPDATE,
    ]);
}

# Work out the file window of one text editor once for Toast UI, Plain Plus and CodeMirror: the options its runtime reads and the windows it draws
function getEditorFileKit(string $id, array $data): array {
    global $tpl;
    $mod = strtolower((string)($data['mod'] ?? ''));
    $plc = ($mod !== '') ? $mod.'.attach' : '';
    $rul = (array)($data['rule'] ?? []);
    $plr = ($plc !== '') ? getUploadPlaceRule($plc) : [];
    $room = (array)($data['room'] ?? []);
    $upl = $mod !== '' && checkEditorUploadAccess($mod, $rul);
    $emb = !empty($room['embed']);
    $mdr = $upl && checkUploadModer($mod);
    $eid = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    $pid = $id.'_fm';
    $sid = $id.'_fm_insert';
    $txt = getFileManagerText();
    $opt = [
        'labels' => $txt['labels'],
        'panes' => $txt['panes'] + ['emb' => [_EMODEEMBED, _EMODEEMBEDINFO]],
        'embedmax' => Parser::EMBEDMAX,
        'embedimg' => Parser::EMBEDIMG,
        'canupload' => $upl,
        'canlist' => $upl,
        'canembed' => $emb,
        'canlink' => !empty($plr['canlink']),
        'canzip' => $mdr,
        'candel' => $mdr,
        'room' => (int)($room['bytes'] ?? 65535),
        'panel' => $pid,
        'opts' => $sid,
        'msg' => $id.'_fm_msg',
        'object' => $id.'_fm_object',
        'url' => $id.'_fm_url',
        'alt' => $id.'_fm_alt',
        'urlalt' => $id.'_fm_urlalt',
        'last' => 6,
        'maxfiles' => (int)($rul['maxfiles'] ?? 0),
        'exts' => array_values(array_filter(array_map('trim', explode(',', strtolower((string)($rul['extensions'] ?? '')))))),
        'maxbytes' => (int)($rul['maxbytes'] ?? 0),
        'maxwidth' => (int)($rul['maxwidth'] ?? 0),
        'maxheight' => (int)($rul['maxheight'] ?? 0),
        'token' => $upl ? getSiteToken('upload') : '',
        'ajax' => $upl ? getSiteToken() : '',
        'upload' => $upl ? HOME_PATH.'index.php?go=4&op=editorUpload&place='.rawurlencode($plc) : '',
        'files' => $upl ? HOME_PATH.'index.php?go=4&op=editorFiles&place='.rawurlencode($plc) : '',
        'remove' => $mdr ? HOME_PATH.'index.php?go=4&op=editorDelete&place='.rawurlencode($plc) : '',
        'archive' => $mdr ? HOME_PATH.'index.php?go=4&op=editorArchive&place='.rawurlencode($plc) : '',
    ];
    $html = getFileManagerWindow([
        'place' => $plc,
        'panel' => $pid,
        'title' => $id.'_fm_title',
        'msg' => $opt['msg'],
        'upfile' => $id.'_fm_file',
        'embed' => $id.'_fm_embed',
        'object' => $opt['object'],
        'url' => $opt['url'],
        'alt' => $opt['alt'],
        'urlalt' => $opt['urlalt'],
        'editor' => $id,
        'can_embed' => $emb,
        'embed_accept' => implode(',', array_map(static fn(string $ext): string => '.'.$ext, Parser::EMBEDIMG)),
        'embed_types' => implode(', ', Parser::EMBEDIMG).' · '.filterSize(Parser::EMBEDMAX),
        'room_text' => filterSize((int)($room['bytes'] ?? 0)),
    ]);
    $acts = [
        ['key' => 'image', 'icon' => 'image', 'name' => _INSERTIMG, 'tone' => 'info'],
        ['key' => 'attach', 'icon' => 'paperclip', 'name' => _EINSOBJ, 'tone' => 'neutral'],
        ['icon' => 'download', 'name' => _DOWNLOAD, 'tone' => 'neutral', 'is_load' => true],
    ];
    if ($mdr) $acts[] = ['key' => 'zip', 'icon' => 'file-zip', 'name' => _EDITOR_ZIP, 'tone' => 'warn'];
    if ($mdr) $acts[] = ['key' => 'delete', 'icon' => 'trash3', 'name' => _DELETE, 'tone' => 'danger'];
    if ($upl) $html .= getWindowShot([
        'own' => 'editor',
        'editor' => $eid,
        'prev_text' => _EDITOR_PREV,
        'next_text' => _EDITOR_NEXT,
        'can_walk' => true,
        'can_props' => true,
        'acts' => $acts,
    ]);
    if ($upl) $html .= $tpl->getHtmlFrag('window', [
        'win_id' => htmlspecialchars($sid, ENT_QUOTES, 'UTF-8'),
        'size_class' => 'sl-modal-sm',
        'win_class' => 'sl-fm-win sl-toastui-upload',
        'win_attr' => 'data-editor="'.$eid.'"',
        'icon_name' => 'sliders',
        'title_text' => _EDITOR_OPTS,
        'has_sub' => true,
        'sub_attr' => 'data-sl-opts="name"',
        'close_text' => _CLOSE,
        'body_html' => $tpl->getHtmlFrag('window-body-insert', [
            'opts_id' => htmlspecialchars($sid, ENT_QUOTES, 'UTF-8'),
            'align_label' => _EDITOR_ALIGN,
            'alignno_label' => _EDITOR_ALIGNNO,
            'alignleft_label' => _EDITOR_ALIGNLEFT,
            'alignright_label' => _EDITOR_ALIGNRIGHT,
            'size_label' => _SIZE,
            'sizethumb_label' => _EDITOR_THUMB,
            'sizefull_label' => _EDITOR_FULLSIZE,
            'caption_label' => _EDITOR_CAPTION,
        ]),
        'foot_html' => $tpl->getHtmlFrag('window-foot-insert', [
            'editor_id' => $eid,
            'close_label' => _CLOSE,
            'insert_label' => _EDITOR_INSERT,
        ]),
    ]);
    return ['opt' => $opt, 'html' => $html];
}

# Build the file window door of one form row, registered once a row swapped in by htmx has loaded its runtime; its three hidden carriers are exclusive, read in a fixed order
function getFileManagerField(array $opt): string {
    global $tpl;
    $plc = (string)($opt['place'] ?? '');
    $rul = getUploadPlaceRule($plc);
    $see = getUploadPlaceView($rul);
    $txt = getFileManagerText($rul);
    $ops = $see['ops'];
    $upl = $see['able'];
    $mdr = $see['moder'];
    $link = !empty($rul['canlink']) && empty($opt['is_nolink']);
    $ids = getFieldIds((string)($opt['id'] ?? ''), 'file');
    $eid = $ids['input'];
    $exts = $see['exts'];
    $take = (string)($opt['path_value'] ?? '');
    $addr = $link ? (string)($opt['url_value'] ?? '') : '';
    $run = [
        'labels' => $txt['labels'],
        'panes' => $txt['panes'],
        'canupload' => $upl,
        'canlist' => $upl,
        'canembed' => false,
        'canlink' => $link,
        'panel' => $eid.'-fm',
        'msg' => $eid.'-fm-msg',
        'url' => $eid.'-fm-url',
        'alt' => $eid.'-fm-alt',
        'urlalt' => $eid.'-fm-urlalt',
        'last' => 6,
        'maxfiles' => (int)$rul['maxfiles'],
        'exts' => $exts,
        'maxbytes' => (int)$rul['maxbytes'],
        'maxwidth' => (int)$rul['maxwidth'],
        'maxheight' => (int)$rul['maxheight'],
        'token' => $upl ? getSiteToken('upload') : '',
        'ajax' => $upl ? getSiteToken() : '',
        'upload' => ($upl && in_array('editorUpload', $ops, true)) ? HOME_PATH.'index.php?go=4&op=editorUpload&place='.rawurlencode($plc) : '',
        'files' => ($upl && in_array('editorFiles', $ops, true)) ? HOME_PATH.'index.php?go=4&op=editorFiles&place='.rawurlencode($plc) : '',
        'remove' => ($mdr && in_array('editorDelete', $ops, true)) ? HOME_PATH.'index.php?go=4&op=editorDelete&place='.rawurlencode($plc) : '',
        'archive' => ($mdr && in_array('editorArchive', $ops, true)) ? HOME_PATH.'index.php?go=4&op=editorArchive&place='.rawurlencode($plc) : '',
    ];
    $box = $tpl->getHtmlFrag('file-field', [
        'box_id' => $eid.'-box',
        'input_id' => $eid,
        'editor_id' => $eid,
        'open_label' => _EUPLOAD,
        'clear_label' => _DELETE,
        'limits_text' => $txt['limits'],
        'file_name' => (string)($opt['name'] ?? ''),
        'path_name' => (string)($opt['path'] ?? ''),
        'url_name' => $link ? (string)($opt['url'] ?? '') : '',
        'path_value' => $take,
        'url_value' => $addr,
        'chip_text' => ($take !== '') ? $take : $addr,
        'accept_attr' => $see['accept'],
    ]);
    $win = getFileManagerWindow([
        'place' => $plc,
        'is_field' => true,
        'is_nolink' => !$link,
        'panel' => $eid.'-fm',
        'title' => $eid.'-fm-title',
        'msg' => $eid.'-fm-msg',
        'upfile' => $eid.'-fm-file',
        'url' => $eid.'-fm-url',
        'alt' => $eid.'-fm-alt',
        'urlalt' => $eid.'-fm-urlalt',
        'editor' => $eid,
    ]);
    $js = '(function(){var go=function(){var box=document.getElementById('.json_encode($eid.'-box').');';
    $js .= 'if(box&&window.SlaedFileManager){window.SlaedFileManager.addField('.json_encode($eid).',box,'
        .json_encode($run, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).');}};';
    $js .= 'if(window.SlaedEditors){window.SlaedEditors.ready(go);}else{go();}})();';
    return $box.$win.$tpl->getHtmlFrag('head-script-inline', ['js' => $js]);
}

# Build the gallery window from the one window frame; its owner decides the walk, the property panel and the row of actions
function getWindowShot(array $data = []): string {
    global $tpl;
    $own = (string)($data['own'] ?? 'view');
    $eid = (string)($data['editor'] ?? '');
    $acts = (array)($data['acts'] ?? []);
    return $tpl->getHtmlFrag('window', [
        'size_class' => 'sl-modal-xl',
        'win_attr' => 'data-sl-shot="'.$own.'"'.($eid === '' ? '' : ' data-editor="'.$eid.'"'),
        'icon_name' => 'image',
        'title_text' => _PREVIEW,
        'has_sub' => true,
        'sub_attr' => 'data-sl-shot-name',
        'close_text' => _CLOSE,
        'is_flush' => true,
        'body_html' => $tpl->getHtmlFrag('window-body-shot', [
            'can_walk' => !empty($data['can_walk']),
            'can_props' => !empty($data['can_props']),
            'prev_text' => (string)($data['prev_text'] ?? ''),
            'next_text' => (string)($data['next_text'] ?? ''),
        ]),
        'foot_html' => ($acts === []) ? '' : $tpl->getHtmlFrag('window-foot-shot', ['acts' => $acts]),
    ]);
}

# The windows the layout prints on every page, one frame filled differently: notice, question, watch window of a super administrator; on the site share, QR, image viewer
function getWindowSet(bool $admin = false): string {
    global $conf, $tpl, $afile;
    $out = $tpl->getHtmlFrag('toast', ['undo_text' => _UNDO, 'close_text' => _CLOSE]);
    if ($conf['session'] && isAdmin(true)) {
        $out .= $tpl->getHtmlFrag('window', [
            'win_id' => 'sl-stats',
            'size_class' => 'sl-modal-lg',
            'win_class' => 'sl-stats',
            'win_attr' => 'data-sl-window data-sl-live-box',
            'icon_name' => 'activity',
            'title_text' => _WHO,
            'has_sub' => true,
            'sub_text' => _ADM_LIVE,
            'move_text' => _EMOVEWIN,
            'full_text' => _EEXPAND,
            'restore_text' => _ERESTORE,
            'close_text' => _CLOSE,
            'bar_html' => $tpl->getHtmlPart('session-stats', ['is_bar' => true, 'live_html' => $tpl->getHtmlFrag('live-chip', ['live_every' => 10])]),
            'body_html' => $tpl->getHtmlPart('session-stats', [
                'is_wait' => true,
                'url' => HOME_PATH.'index.php?go='.($admin ? '5' : '1').'&op=getSessionStats&token='.getSiteToken(),
            ]),
            'foot_html' => $tpl->getHtmlPart('session-stats', ['is_foot' => true, 'url' => HOME_PATH.$afile.'.php?name=statistic']),
        ]);
    }
    $out .= $tpl->getHtmlFrag('window', [
        'win_id' => 'sl-confirm',
        'size_class' => 'sl-modal-sm',
        'tone_class' => 'sl-modal-danger',
        'icon_name' => 'exclamation-triangle-fill',
        'title_text' => _CONFIRM,
        'close_text' => _CLOSE,
        'body_html' => $tpl->getHtmlFrag('window-body-confirm', []),
        'foot_html' => $tpl->getHtmlFrag('window-foot-confirm', ['no_text' => _NO, 'yes_text' => _YES]),
    ]);
    if ($admin) return $out;
    $out .= $tpl->getHtmlFrag('window', [
        'win_id' => 'sl-share-sheet',
        'icon_name' => 'share-fill',
        'title_text' => _SHARE,
        'close_text' => _CLOSE,
        'body_html' => $tpl->getHtmlFrag('window-body-share', []),
    ]);
    $out .= $tpl->getHtmlFrag('window', [
        'win_id' => 'sl-share-qr',
        'size_class' => 'sl-modal-sm',
        'icon_name' => 'qr-code',
        'title_text' => _QRCODE,
        'close_text' => _CLOSE,
        'body_html' => $tpl->getHtmlFrag('window-body-qr', []),
        'foot_html' => $tpl->getHtmlFrag('window-foot-qr', ['done_text' => _COPIED, 'copy_text' => _COPYLINK]),
    ]);
    return $out.getWindowShot(['own' => 'view', 'can_walk' => true]);
}

# Build the htmx attributes of one in-place admin action: the values travel in a POST body, so the credential never becomes part of an address the browser may prefetch or store
function getTplPostVals(array $hide, string $target): string {
    $hide['token'] = getSiteToken((string)($hide['name'] ?? 'ajax'));
    $vals = htmlspecialchars((string)json_encode($hide, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    return 'hx-post="admin.php" hx-vals="'.$vals.'" hx-target="'.$target.'" hx-swap="innerHTML" hx-push-url="false"';
}

# Build one standalone admin button that submits a POST form; $attr carries form behaviour, $look 'act' or 'danger' draws it as the action of an alert
function getTplPostButton(array $hide, string $icon, string $label, string $attr = '', string $look = ''): string {
    global $afile, $tpl;
    $hide['token'] = getSiteToken((string)($hide['name'] ?? 'ajax'));
    $html = '';
    foreach ($hide as $key => $val) {
        $html .= $tpl->getHtmlFrag('hidden', ['name_attr' => (string)$key, 'value_attr' => (string)$val, 'input_attr' => '']);
    }
    return $tpl->getHtmlFrag('post-button', [
        'action' => HOME_PATH.$afile.'.php',
        'hidden' => $html,
        'icon_name' => $icon,
        'title' => $label,
        'label' => $label,
        'form_attr' => $attr,
        'is_act' => $look !== '',
        'is_danger' => $look === 'danger',
    ]);
}

# Build the moderator dial of a content row; the delete half becomes a form with a fresh token, and id[]=5 of the address goes back as fields of key[]
function getTplEditMenu(string $edithref, string $delhref, string $title): array {
    global $tpl;
    static $seq = 0;
    $parts = explode('?', $delhref, 2);
    $pars = [];
    parse_str($parts[1] ?? '', $pars);
    unset($pars['token']);
    $pars['token'] = getSiteToken();
    $hide = '';
    foreach ($pars as $key => $val) {
        foreach ((array)$val as $one) {
            $hide .= $tpl->getHtmlFrag('hidden', ['name_attr' => is_array($val) ? $key.'[]' : (string)$key, 'value_attr' => (string)$one, 'input_attr' => '']);
        }
    }
    return [
        'is_moder' => true,
        'dial_title' => _EDITOR,
        'dial' => [
            ['href' => $edithref, 'icon_name' => 'pencil', 'title' => _FULLEDIT],
            ['href' => $parts[0], 'form_id' => 'sldel'.(++$seq), 'hidden' => $hide, 'icon_name' => 'trash', 'title' => _ONDELETE, 'confirm_text' => _DELETE.' "'.$title.'"?'],
        ],
    ];
}

# Format a gender value for display
function getGenderText(int $gender): string {
    if ($gender == 2) return (string)_WOMAN;
    if ($gender == 1) return (string)_MAN;
    return (string)_NO_INFO;
}

# Render one title tip from label-value items; a plain string is rendered as it is, without the grid of a list
function getTplTitleTip(mixed $data): string {
    global $tpl;
    if (!is_array($data)) return $tpl->getHtmlFrag('popover', ['content_html' => (string)$data]);
    $last = count($data) - 1;
    $items = [];
    foreach ($data as $idx => $item) {
        $items[] = [
            'label' => (string)($item['label'] ?? ''),
            'value' => (string)($item['value'] ?? ''),
            'is_last' => $idx === $last,
        ];
    }
    return $tpl->getHtmlFrag('popover', ['items' => $items]);
}

# Build the hover tip before a user name; without a bound account it states the status, and $deleted marks a post whose uid points to a removed user
function getUserTip(string $gname, string|int $points, string $regdate, int $gender, string $from, string $warnings, bool $anon = false, bool $deleted = false): string {
    global $pnt;
    if ($anon) return getTplTitleTip([['label' => _STATUS, 'value' => (string)($deleted ? _USERDEL : _ANONYM)]]);
    $items = [];
    if ($gname !== '') $items[] = ['label' => _GROUP, 'value' => htmlspecialchars($gname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')];
    if ($pnt->active && $points) $items[] = ['label' => _POINTS, 'value' => htmlspecialchars((string)$points, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')];
    $items[] = ['label' => _REG, 'value' => ($regdate !== '') ? format_time($regdate) : (string)_NO_INFO];
    if ($gender) $items[] = ['label' => _GENDER, 'value' => getGenderText($gender)];
    if ($from !== '') $items[] = ['label' => _FROM, 'value' => htmlspecialchars($from, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')];
    if ($warnings !== '') $items[] = ['label' => _UWARNS, 'value' => warnings($warnings)];
    return getTplTitleTip($items);
}

# Build the five star states from a 0-100 fill width, rounded to half stars
function getRatingStars(int $width): array {
    $half = round($width / 10) / 2;
    $stars = [];
    for ($i = 1; $i <= 5; $i++) {
        $stars[] = [
            'value' => $i,
            'is_full' => $half >= $i,
            'is_half' => $half >= $i - 0.5 && $half < $i,
        ];
    }
    return $stars;
}

# Render the rating block; type 2 answers the inner block alone, and the average is Rating::getAverage() with two digits, the rule a vote answers with
function getRatingAsync(mixed $typ, mixed $id, mixed $mod, mixed $rat, mixed $scor, string $obj = '', string $stl = ''): string {
    global $tpl;
    if (intval($rat)) {
        $votnum = $rat;
        $votes = $rat;
    } else {
        $votnum = 0;
        $votes = 1;
    }
    $width = (int)max(0, min(100, round(($scor / $votes) * 20)));
    $result = Rating::getAverage(intval($scor), intval($votes), 2) ?? '0';
    if (intval($votes) && intval($scor)) {
        $title = _RATING.': '.$result.'/'.$votes.' '._AVERAGESCORE.': '.$result;
        $scored = true;
    } else {
        $title = _RATING.': 0/0 '._AVERAGESCORE.': 0';
        $scored = false;
    }
    $rule = getRatingService()->getRule(strtolower((string)$mod));
    $on = !empty($rule['active']);
    if ($typ != 2 && !(($on && $id && $mod) || ($rat && $scor))) return '';
    $live = $typ != 2 && $on && ($typ || !$rule['detail']);
    $part = $stl == '1'
        ? ['rate1_title' => _RATE1, 'rate5_title' => _RATE5]
        : ['width' => (string)$width, 'stars' => getRatingStars($width), 'votes' => (string)$votnum, 'votes_title' => _VOTES];
    $body = $tpl->getHtmlFrag($stl == '1' ? 'rating-like' : 'rating-bar', [
        'result' => $result,
        'title' => $title,
        'has_score' => $scored,
        'target_id' => 'rep'.$id.$obj,
        'mod' => strtolower((string)$mod),
        'mid' => (string)intval($id),
        'token' => $live ? getSiteToken() : '',
        'is_live' => $live,
    ] + $part);
    if ($typ == 2) return $body;
    $wrap = $live ? ['id' => 'rep'.$id.$obj] : [];
    return $tpl->getHtmlPart('div', $wrap + ['is_rate' => true, 'content_html' => $body]);
}

# Render the category select; the indent walk stops at an unseen parent and after as many steps as there are categories, so a stored loop never hangs
function getTplCategorySelect(string $mod = '', int $id = 0, string $name = '', string $clas = '', string $empty = '', string $raw = ''): string {
    global $db, $conf, $tpl;
    $mod = filterVar($mod);
    $conf['name'] = $conf['name'] ?? $mod;
    if ($mod) {
        $where = 'WHERE modul = :modul ORDER BY ordern';
        $pars = ['modul' => $mod];
    } else {
        $where = 'ORDER BY ordern';
        $pars = [];
    }
    $res = $db->getSqlQuery('SELECT id, title, parent, pview FROM '.PREFIX_DB.'_categories '.$where, $pars);
    if ($db->getSqlRowCount($res) > 0) {
        $opts = $empty;
        $mass = [];
        $pref = str_repeat(getDecodedText('&nbsp;'), 5);
        while ([$cid, $title, $parent, $pview] = $db->getSqlRow($res)) {
            if (is_acess($pview)) $mass[$cid] = [getConst($title), $parent];
        }
        foreach ($mass as $key => $val) {
            $cont[$key] = $val[0];
            $flag = $val[1];
            for ($i = 0; $flag != 0 && isset($mass[$flag]) && $i < count($mass); $i++) {
                $cont[$key] = $pref.$cont[$key];
                $flag = intval($mass[$flag][1]);
            }
            $opts .= $tpl->getHtmlFrag('select-option', [
                'value_attr' => (string)$key,
                'label_text' => $cont[$key],
                'is_selected' => $id == $key,
            ]);
        }
        return !$raw ? $tpl->getHtmlFrag('select', [
            'name_attr' => $name,
            'selectid' => 'f-'.$name,
            'select_class' => $clas,
            'title' => _CATEGORIES,
            'options_html' => $opts,
        ]) : $opts;
    }
    if ($empty) return $tpl->getHtmlFrag('select', ['name_attr' => $name, 'selectid' => 'f-'.$name, 'select_class' => $clas, 'title' => _CATEGORIES, 'options_html' => $empty]);
    return '';
}

# Render the shared category breadcrumb trail; rich mode emits tone-aware crumbs, plain mode keeps flat links for the head banner
function getTplCategoryTrail(string $mod = '', int $id = 0, string $sep = '', string $home = '', bool $rich = true): string {
    global $conf, $tpl;
    $mod = filterVar($mod);
    $name = $mod ?: $conf['name'];
    $symbol = urldecode($sep ?: $conf['defis']);
    static $cache = [];
    if (!isset($cache[$name])) {
        $mass = [];
        foreach (getCategoryMap($mod) as $cid => $row) $mass[$cid] = [getConst($row['title']), $row['parent'], $row['ordern']];
        $cache[$name] = $mass;
    }
    $mass = $cache[$name];
    $chain = [];
    $cur = $id;
    $guard = 0;
    while ($cur && isset($mass[$cur]) && $guard++ < 50) {
        $chain[] = $cur;
        $cur = $mass[$cur][1];
    }
    $chain = array_reverse($chain);
    if (!$chain && !$home) return '';
    $crumbs = [];
    if ($home) $crumbs[] = $rich
        ? $tpl->getHtmlFrag('category-crumb', ['is_sep' => false, 'href' => getSeoUrl(['name' => $name]), 'name' => $home, 'tone' => 0, 'is_current' => false])
        : $tpl->getHtmlFrag('link', ['href' => getSeoUrl(['name' => $name]), 'title' => $home, 'label_html' => $home]);
    foreach ($chain as $key) {
        $iscur = $key === $id;
        $crumbs[] = $rich
            ? $tpl->getHtmlFrag('category-crumb', [
                'is_sep' => (bool)$crumbs, 'sep_html' => $symbol,
                'href' => $iscur ? '' : getSeoUrl(['name' => $mod, 'cat' => $key]),
                'name' => $mass[$key][0], 'tone' => $mass[$key][2] % 6, 'is_current' => $iscur,
            ])
            : $tpl->getHtmlFrag('link', ['href' => getSeoUrl(['name' => $mod, 'cat' => $key]), 'title' => $mass[$key][0], 'label_html' => $mass[$key][0]]);
    }
    return $rich ? implode('', $crumbs) : implode(' '.$symbol.' ', $crumbs);
}

# Render language options from installed language files
function getTplLanguageOptions(string $lang = '', string $typ = ''): string {
    global $tpl;
    $dir = opendir(BASE_DIR.'/lang');
    $cont = !$typ ? $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _ALL, 'is_selected' => false]) : '';
    while (false !== ($file = readdir($dir))) {
        if (preg_match('#^(.+)\.php#', $file, $match)) {
            $langf = $match[1];
            $cont .= $tpl->getHtmlFrag('select-option', [
                'value_attr' => $langf,
                'label_text' => getLangName($langf),
                'is_selected' => $lang == $langf,
            ]);
        }
    }
    closedir($dir);
    return $cont;
}

# Render a check list of modules: the directory node is the code of every registered type and has no route of its own, the active types stand beside the modules
function getTplModuleChecks(string $name, string $mod, array $allow = [], string $label = '', string $desc = ''): string {
    global $tpl;
    $mods = explode(',', $mod);
    $list = array_merge(array_diff(scandir(BASE_DIR.'/modules') ?: [], ['node']), array_keys(array_filter(getNodeTypeMap(), fn(NodeType $v): bool => $v->active)));
    $boxes = [];
    foreach ($list as $file) {
        if (str_contains($file, '.') || ($allow && !in_array($file, $allow, true))) continue;
        $boxes[] = ['name_attr' => $name.'[]', 'value_attr' => $file, 'label_text' => getModuleName($file), 'is_checked' => in_array($file, $mods, true)];
    }
    return $tpl->getHtmlFrag('check-list', ['labelledby' => $label, 'describedby' => $desc, 'items' => $boxes]);
}

# Return the modules with categories, the forum and every registered Node type with the feature; no type is named, so a new one appears once registered
function getCategoryModules(): array {
    $types = array_filter(getNodeTypeMap(), fn(NodeType $v): bool => $v->settings['features']['categories']);
    return array_merge(['forum'], array_keys($types));
}

# The select of the modules with categories for a form row; $auto answers the filter of a screen instead, whose choice opens the list of that module
function getTplCategoryModule(string $name, string $clas = '', string $sel = '', bool $auto = false): string {
    global $afile, $tpl;
    $opts = [];
    foreach (getCategoryModules() as $mod) $opts[] = ['value_attr' => $mod, 'label_text' => getModuleName($mod).' - '.$mod, 'is_selected' => $sel == $mod];
    if ($auto) {
        return $tpl->getHtmlFrag('list-filter', [
            'action_url' => $afile.'.php',
            'label' => _CATEGORIES,
            'hidden' => [['nameattr' => 'name', 'valueattr' => 'categories']],
            'fields' => [['field_id' => 'sl-cat-mod', 'label' => _MODUL, 'name_attr' => $name, 'options' => $opts, 'is_now' => true]],
        ]);
    }
    return $tpl->getHtmlFrag('select', ['name_attr' => $name, 'selectid' => 'f-'.$name, 'select_class' => $clas, 'options' => $opts]);
}

# Build a query string fragment from key value pairs
function getQueryString(array $data = [], bool $tail = false, string $hash = ''): string {
    $sep = '&';
    $qry = '';
    foreach ($data as $name => $value) {
        if ($value === '' || $value === null || $value === false) continue;
        $qry .= ($qry === '' ? '' : $sep).rawurlencode((string)$name).'='.rawurlencode((string)$value);
    }
    if ($hash !== '') return $qry.'#'.ltrim($hash, '#');
    if ($tail && $qry !== '') $qry .= $sep;
    return $qry;
}

# Render a module navigation block; a Node type has no best, pop or liste operation and no module config, so its links come only from the overrides
function getModuleNavi(array $p): string {
    global $conf, $tpl;
    $node = isset($conf['node']['types'][$conf['name']]);
    $mconf = $node ? [] : ($conf[$conf['name']] ?? []);
    $cat = getVar('get', 'cat', 'num');
    $cpar = $cat ? ['cat' => $cat] : [];
    $title = $p['title'] ?? '';
    $htitle = $p['htitle'] ?? $title;
    $bop = $p['bop'] ?? 'best';
    $always = !$node && ($p['always'] ?? false);
    $addquest = $p['addquest'] ?? true;
    $showrate = $always || !empty($mconf['rate']);
    $canadd = (is_user() && ($mconf['add'] ?? 0) == 1) || (!is_user() && $addquest && ($mconf['addquest'] ?? 0) == 1);
    $home = $p['home_href'] ?? getSeoUrl(['name' => $conf['name']]);
    $best = $p['best_href'] ?? ($showrate ? getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => $bop]) : '');
    $pop = $p['pop_href'] ?? ($showrate ? getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'pop']) : '');
    $list = $p['liste_href'] ?? ($node ? '' : getSeoUrl(['name' => $conf['name'], 'op' => 'liste']));
    $add = $p['add_href'] ?? ($canadd ? getSeoUrl(['name' => $conf['name'], 'op' => 'add']) : '');
    $btit = $p['btitle'] ?? _BEST;
    $ptit = $p['ptitle'] ?? _POP;
    return $tpl->getHtmlPart('navi', [
        'title' => $title,
        'nav_label' => $htitle,
        'is_heading' => $p['is_heading'] ?? true,
        'home_link' => ['href' => $home, 'title' => $htitle, 'label' => _HOME, 'is_navi_button' => true],
        'best_link' => $best ? ['href' => $best, 'title' => $btit, 'label' => $btit, 'is_navi_button' => true] : [],
        'pop_link' => $pop ? ['href' => $pop, 'title' => $ptit, 'label' => $ptit, 'is_navi_button' => true] : [],
        'list_link' => $list ? ['href' => $list, 'title' => _LIST, 'label' => _LIST, 'is_navi_button' => true] : [],
        'add_link' => $add ? ['href' => $add, 'title' => _ADD, 'label' => _ADD, 'is_navi_button' => true] : [],
    ]);
}

# Render page numbers from known counters: the site takes the keys of its page as an array and the number as page, the panel a query prefix and num
function getPageNumbers(string $mod, int $count, int $pages, int $limit, array|string $url = '', int $maxpg = 8, int $num = 0, string $anchor = '', string $n = ''): string {
    global $afile;
    $site = !defined('ADMIN_FILE');
    $n = $n ?: ($site ? 'page' : 'num');
    $num = $num ?: getVar('get', $n, 'num', 1);
    $url = is_array($url) ? $url : html_entity_decode($url, ENT_QUOTES, 'UTF-8');
    $target = static function(int $i) use ($mod, $url, $n, $anchor, $afile, $site): array {
        $href = $site ? getSeoUrl(['name' => $mod] + (is_array($url) ? $url : []) + [$n => $i]).$anchor : HOME_PATH.$afile.'.php?'.$url.$n.'='.$i.$anchor;
        return ['href' => $href];
    };
    return getTplPagerView((int)$num, $pages, $maxpg, $target, ['count' => $count, 'limit' => $limit]);
}

# Render text parts as one block, so the break between parts is the theme's decision; $iswide puts a blank line between, $israw passes markup through
function getTplLines(array $line, bool $iswide = false, bool $israw = false): string {
    global $tpl;
    $rows = [];
    $next = false;
    foreach ($line as $val) {
        $rows[] = $israw ? ['html' => (string)$val, 'is_next' => $next] : ['has_text' => true, 'text' => (string)$val, 'is_next' => $next];
        $next = true;
    }
    return $tpl->getHtmlFrag('lines', ['lines' => $rows, 'is_wide' => $iswide]);
}
