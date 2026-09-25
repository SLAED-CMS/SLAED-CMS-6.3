<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

# The areas whose extra fields this manager owns, as area => caption and saved definitions; a later owner of fields joins here and nowhere else
# Every registered Node type is the area node.<name> with the checked definitions of its type, which only its service writes
function getFieldAreas(): array {
    global $conf;
    $out = [];
    foreach (['account' => _ACCOUNT, 'forum' => _FORUM, 'order' => _ORDER] as $area => $label) {
        $out[$area] = ['label' => $label, 'defs' => is_array($conf['fields'][$area] ?? null) ? $conf['fields'][$area] : []];
    }
    foreach (getNodeTypeMap() as $name => $type) {
        $out['node.'.$name] = ['label' => (str_starts_with($type->title, '_') && defined($type->title)) ? constant($type->title) : $type->title, 'defs' => $type->fields];
    }
    return $out;
}

# Turn one posted block into a definition with native types; a text that is no whole number or no switch stays a string, so the shared check refuses it by its path
function getFieldInput(array $post): array {
    $num = fn(string $val): int|string => (preg_match('/^-?(?:0|[1-9][0-9]{0,18})$/D', $val) && is_int($int = filter_var($val, FILTER_VALIDATE_INT))) ? $int : $val;
    $text = fn(string $key): string => is_string($post[$key] ?? null) ? trim($post[$key]) : '';
    $type = $text('type');
    $multi = !empty($post['multi']);
    $opts = [];
    $items = [];
    foreach (is_array($post['items'] ?? null) ? $post['items'] : [] as $item) {
        $key = is_string($item['key'] ?? null) ? trim($item['key']) : '';
        $name = is_string($item['title'] ?? null) ? trim($item['title']) : '';
        if ($key === '' && $name === '') continue;
        $items[$key] = ['title' => $name, 'active' => !empty($item['active']), 'sort' => $num(is_string($item['sort'] ?? null) ? trim($item['sort']) : '')];
    }
    if ($items || $type === 'select') $opts['items'] = $items;
    $exact = in_array($type, ['decimal', 'date', 'datetime'], true);
    foreach (['min' => 'low', 'max' => 'top'] as $key => $from) {
        if ($text($from) !== '') $opts[$key] = $exact ? $text($from) : $num($text($from));
    }
    if ($text('scale') !== '') $opts['scale'] = $num($text('scale'));
    $raw = $text('default');
    $default = match (true) {
        $multi => ($raw === '') ? [] : array_map('trim', explode(',', $raw)),
        $type === 'bool' => match ($raw) {
            '' => null,
            '0' => false,
            '1' => true,
            default => $raw,
        },
        $type === 'int' => ($raw === '') ? null : $num($raw),
        default => $raw,
    };
    return [
        'title' => $text('title'), 'intro' => $text('intro'), 'type' => $type, 'default' => $default, 'options' => $opts,
        'req' => !empty($post['req']), 'multi' => $multi, 'active' => !empty($post['active']), 'sort' => $num($text('sort')),
    ];
}

# Render one block of the form from a definition, stored or just posted: every value is shown as text, so a refused form comes back exactly as it was sent
# A stored field keeps its name and its type, because the name is the key of every stored value and another type would turn those values into refusals
function getFieldBlock(string $area, int $pos, string $name, array $def, bool $kept, bool $hide): string {
    global $tpl, $fld;
    $base = 'def['.$area.']['.$pos.']';
    $slug = str_replace('.', '-', $area);
    $fid = 'f-'.$slug.'-'.$pos.'-';
    $show = fn(mixed $val): string => is_array($val) ? implode(',', array_filter($val, 'is_scalar')) : (is_bool($val) ? ($val ? '1' : '0') : (is_scalar($val) ? (string)$val : ''));
    $opts = is_array($def['options'] ?? null) ? $def['options'] : [];
    $types = '';
    foreach ($fld->getFieldTypeList() as $type => $info) {
        if ($kept && $type !== ($def['type'] ?? '')) continue;
        $types .= $tpl->getHtmlFrag('select-option', ['value_attr' => $type, 'label_text' => constant($info['title']), 'is_selected' => $type === ($def['type'] ?? 'text')]);
    }
    $line = fn(string $key, string $label, string $val, array $more = []): array => [
        'label_for' => $fid.$key,
        'label_html' => $label,
        'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => $base.'['.$key.']', 'input_id' => $fid.$key, 'value_attr' => $val, 'is_config' => true] + $more),
    ];
    $flag = fn(string $key, string $label, bool $on): array => [
        'label_for' => $fid.$key,
        'label_html' => $label,
        'field_html' => $tpl->getHtmlFrag('checkbox', ['name_attr' => $base.'['.$key.']', 'input_id' => $fid.$key, 'value_attr' => '1', 'is_checked' => $on]),
    ];
    $list = is_array($opts['items'] ?? null) ? $opts['items'] : [];
    for ($i = 0; $i < 3; $i++) $list[] = ['title' => '', 'active' => true, 'sort' => ''];
    $irows = '';
    $num = 0;
    foreach ($list as $key => $item) {
        $cell = $base.'[items]['.$num.']';
        $cells = [];
        foreach (['key' => is_string($key) ? $key : '', 'title' => $show($item['title'] ?? ''), 'sort' => $show($item['sort'] ?? '')] as $part => $val) {
            $lock = $kept && $part === 'key' && $val !== '';
            $cells[] = ['content_html' => $tpl->getHtmlFrag('input', ['name_attr' => $cell.'['.$part.']', 'value_attr' => $val, 'is_readonly' => $lock])];
        }
        $cells[] = ['content_html' => $tpl->getHtmlFrag('checkbox', ['name_attr' => $cell.'[active]', 'value_attr' => '1', 'is_checked' => !empty($item['active'])])];
        $irows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => $cells])]);
        $num++;
    }
    $head = [['content' => _FIELDS_KEY, 'nosort' => 1], ['content' => _TITLE, 'nosort' => 1], ['content' => _POSITION, 'nosort' => 1], ['content' => _ACTIVATE2, 'nosort' => 1]];
    $pick = $tpl->getHtmlFrag('select', ['name_attr' => $base.'[type]', 'selectid' => $fid.'type', 'options_html' => $types, 'is_config' => true]);
    $grid = $tpl->getHtmlFrag('table', ['is_fixed' => true, 'head' => $head, 'rows_html' => $irows, 'is_wrapless' => true]);
    $lead = $line('name', _FIELDS_KEY, $name, ['is_readonly' => $kept, 'maxlength_num' => 32]);
    if ($kept) $lead['field_html'] .= $tpl->getHtmlFrag('hidden', ['name_attr' => $base.'[orig]', 'value_attr' => $name]);
    $rows = [
        $lead,
        $line('title', _TITLE, $show($def['title'] ?? ''), ['maxlength_num' => 255]),
        $line('intro', _DESCRIPTION, $show($def['intro'] ?? '')),
        ['label_for' => $fid.'type', 'label_html' => _TYPE, 'field_html' => $pick],
        $line('default', _FIELDS_DEFAULT, $show($def['default'] ?? '')),
        $line('low', _FIELDS_LOW, $show($opts['min'] ?? '')),
        $line('top', _FIELDS_TOP, $show($opts['max'] ?? '')),
        $line('scale', _FIELDS_SCALE, $show($opts['scale'] ?? '')),
        ['label_html' => _FIELDS_ITEMS, 'field_html' => $grid, 'is_full' => true],
        $flag('req', _FIELDIN, !empty($def['req'])),
        $flag('multi', _FIELDS_MULTI, !empty($def['multi'])),
        $flag('active', _ACTIVATE2, !isset($def['active']) || !empty($def['active'])),
        $line('sort', _POSITION, $show($def['sort'] ?? ($pos + 1) * 10)),
    ];
    if ($kept) $rows[] = $flag('drop', _DELETE, false);
    return $tpl->getHtmlPart('toggle-form-block', [
        'block_id' => 'fi-'.$slug.'-'.$pos,
        'is_toggle_block' => true,
        'is_hidden' => $hide,
        'toggle_target_id' => $kept ? 'fi-'.$slug.'-'.($pos + 1) : '',
        'title' => _ADD,
        'label_html' => $kept ? _FIELD.': '.htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : _FIELD.': '._ADD,
        'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows]),
    ]);
}

function fields(array $sent = [], string $fail = ''): void {
    global $afile, $conf, $tpl;
    setHead();
    $areas = getFieldAreas();
    $mark = ($conf['update']['fields'] ?? '') === '6.3.0';
    $ctab = getVar('req', 'tab', 'num', 0);
    if ($ctab < 0 || $ctab >= count($areas)) $ctab = 0;
    $links = [];
    $panels = [];
    $k = 0;
    foreach ($areas as $area => $info) {
        $label = $info['label'];
        $links[] = ['href' => '#', 'is_active' => $ctab === $k, 'label' => $label, 'rel' => 'fields-panel-'.$k, 'title' => $label];
        $blok = '';
        $pos = 0;
        $list = $sent[$area] ?? [];
        $saved = (!$sent && $mark) ? $info['defs'] : [];
        foreach ($saved as $name => $def) $list[] = ['name' => $name, 'kept' => '1'] + $def;
        foreach ($list as $def) {
            $kept = !empty($def['kept']);
            if (!$kept && ($def['name'] ?? '') === '' && ($def['title'] ?? '') === '') continue;
            $blok .= getFieldBlock($area, $pos, $def['name'] ?? '', $def, $kept, false);
            $pos++;
        }
        $blok .= getFieldBlock($area, $pos, '', [], false, $pos > 0).$tpl->getHtmlFrag('hidden', ['name_attr' => 'done['.$area.']', 'value_attr' => '1']);
        $panels[] = $tpl->getHtmlFrag('tabs-panel', ['panel_id' => 'fields-panel-'.$k, 'active' => $ctab === $k, 'content_html' => $blok]);
        $k++;
    }
    $links[] = ['href' => $afile.'.php?name=fields&op=info&tab='.$ctab, 'label' => _DOCS, 'link_attr' => 'data-sl-tab-info-link="fields-main"', 'title' => _DOCS];
    $cont = getTplAdminTabs(['is_runtime' => true, 'links' => $links, 'tabs_id' => 'fields-main', 'tabs_index' => $ctab, 'tabs_sync_selector' => 'input[name="tab"]']);
    $cont .= checkPerms(CONFIG_DIR.'/fields.php');
    if (!$mark) {
        echo $cont.$tpl->getHtmlFrag('alert', ['text' => _FIELDS_NOMARK, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
        setFoot();
        return;
    }
    if ($fail !== '') $cont .= $tpl->getHtmlFrag('alert', ['text' => $fail, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
    $cont .= $tpl->getHtmlFrag('alert', ['text' => _FIELDINFO]);
    $hidden = [
        ['nameattr' => 'name', 'valueattr' => 'fields'],
        ['nameattr' => 'op', 'valueattr' => 'save'],
        ['nameattr' => 'tab', 'valueattr' => (string)$ctab],
        ['nameattr' => 'token', 'valueattr' => getSiteToken('fields')],
    ];
    foreach (getNodeTypeMap() as $name => $type) $hidden[] = ['nameattr' => 'ver['.$name.']', 'valueattr' => (string)$type->version];
    $fieldv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => $hidden,
        'content_html' => $tpl->getHtmlPart('tabs', ['content_html' => implode('', $panels)]),
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $fieldv]);
    setFoot();
}

function save(): void {
    global $afile, $conf, $fld;
    $ctab = getVar('post', 'tab', 'num', 0);
    $good = checkAdminPost('fields');
    if (!$good || ($conf['update']['fields'] ?? '') !== '6.3.0') setRedirect($afile.'.php?name=fields&tab='.$ctab, false, 302, $good ? _FIELDS_NOMARK : _TOKENMISS, true);
    $post = getVar('post', 'def[]', '', []);
    $ends = getVar('post', 'done[]', '', []);
    $vers = getVar('post', 'ver[]', '', []);
    $cont = [];
    $sent = [];
    $fail = '';
    foreach (getFieldAreas() as $area => $info) {
        $defs = [];
        $seen = [];
        if (($ends[$area] ?? '') !== '1' && $fail === '') $fail = $area.': form';
        foreach (is_array($post[$area] ?? null) ? $post[$area] : [] as $one) {
            if (!is_array($one)) continue;
            $name = is_string($one['name'] ?? null) ? trim($one['name']) : '';
            $orig = is_string($one['orig'] ?? null) ? $one['orig'] : '';
            $kept = isset($info['defs'][$orig]);
            $def = getFieldInput($one);
            if ($kept) {
                $def['type'] = $info['defs'][$orig]['type'];
                $seen[$orig] = true;
                $keys = array_map('strval', array_keys($def['options']['items'] ?? []));
                $lost = array_diff(array_map('strval', array_keys($info['defs'][$orig]['options']['items'] ?? [])), $keys);
                if ($name !== $orig && $fail === '') $fail = $area.': '.$orig.'.name';
                if ($lost && $fail === '') $fail = $area.': '.$orig.'.options.items.'.reset($lost);
                $name = $orig;
            } elseif ($orig !== '' && $fail === '') {
                $fail = $area.': '.$orig;
            }
            if ($name === '' && $def['title'] === '') continue;
            $sent[$area][] = ['name' => $name, 'kept' => $kept ? '1' : ''] + $def;
            if (!empty($one['drop']) && $kept) continue;
            if (isset($defs[$name]) && $fail === '') $fail = $area.': '.$name;
            $defs[$name] = $def;
        }
        foreach (array_keys(array_diff_key($info['defs'], $seen)) as $gone) {
            if ($fail === '') $fail = $area.': '.$gone;
        }
        try {
            $cont[$area] = $fld->filterFieldList($defs);
        } catch (InvalidArgumentException $err) {
            if ($fail === '') $fail = $area.': '.$err->getMessage();
        }
    }
    if ($fail !== '') {
        fields(['' => []] + $sent, sprintf(_FIELDS_BAD, htmlspecialchars($fail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        return;
    }
    $own = array_filter($cont, fn($v) => !str_starts_with($v, 'node.'), ARRAY_FILTER_USE_KEY);
    $warn = !setConfigFile(static function (array $base, Closure $save) use ($own): string {
        $base['fields'] = array_replace($base['fields'], $own);
        ksort($base['fields']);
        return $save($base) ? 'committed' : 'aborted';
    });
    $text = $warn ? (getConfigJournal() ? _CONFIG_PENDING : _ERROR_UP) : _SUCCSAVE;
    foreach (getNodeTypeMap() as $name => $type) {
        if ($warn || !isset($cont['node.'.$name]) || $cont['node.'.$name] === $type->fields) continue;
        $fail = updateNodeTypePart($name, 'fields', $cont['node.'.$name], intval($vers[$name] ?? 0));
        $warn = $fail !== '';
        if ($warn) $text = $fail;
    }
    setRedirect($afile.'.php?name=fields&tab='.$ctab, false, 302, $text, $warn);
}

function info(): void {
    $ops = [];
    $areas = array_column(getFieldAreas(), 'label');
    foreach (array_keys($areas) as $key) $ops[] = 'name=fields&tab='.$key;
    $ops[] = 'name=fields&op=info';
    setTplAdminInfoPage(['ops' => $ops, 'tabs' => array_merge($areas, [_DOCS])]);
}

switch ($op) {
    default: fields(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
