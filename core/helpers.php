<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Temporary home for new helper functions while shared APIs are stabilized

# New, stable helper functions for building admin and frontend HTML from prepared data cuts and shared templates
# Build add-div rows from dynamic module field definitions without table markup
function getTplAddFieldRows(array $data = []): array {
    global $conf, $tpl;
    $field = $data['field'] ?? '';
    $mod = $data['mod'] ?? '';
    $mod = strtolower($mod);
    if (is_array($field)) $field = filterFields($field);
    $vals = explode('|', $field ?? '');
    $defs = explode('||', $conf['fields'][$mod] ?? '');
    $rows = [];
    $pos = 0;
    foreach ($defs as $item) {
        if ($item == '') {
            $pos++;
            continue;
        }
        preg_match('#(.*)\|(.*)\|(.*)\|(.*)#i', $item, $out);
        if (($out[1] ?? '0') == '0') {
            $pos++;
            continue;
        }
        $text = !empty($vals[$pos]) ? $vals[$pos] : ($out[2] ?? '');
        $need = (($out[4] ?? '0') == '1') ? ' required' : '';
        $type = $out[3] ?? '';
        if ($type == '1') {
            $dval = $text ? getConst($text) : '';
            $html = $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'field[]',
                'value_attr' => $dval,
                'placeholder_text' => $dval,
                'input_attr' => trim($need),
            ]);
        } elseif ($type == '2') {
            $html = $tpl->getHtmlFrag('textarea', [
                'name_attr' => 'field[]',
                'rows_num' => 5,
                'value_text' => $text,
                'input_attr' => trim($need),
            ]);
        } elseif ($type == '3') {
            $opts = '';
            $list = explode(',', $out[2] ?? '');
            foreach ($list as $name) {
                if ($name == '') continue;
                $opts .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => $name,
                    'label_text' => $name,
                    'is_selected' => $name == $text,
                ]);
            }
            $html = $tpl->getHtmlFrag('select', [
                'name_attr' => 'field[]',
                'options_html' => $tpl->getHtmlFrag('select-option', [
                    'value_attr' => '',
                    'label_text' => _NO,
                    'is_selected' => $text === '',
                ]).$opts,
                'select_attr' => trim($need),
            ]);
        } elseif ($type == '4') {
            $html = getTplAddDateTime(['name' => 'field[]', 'time' => $text, 'with' => true, 'max' => 16]);
        } elseif ($type == '5') {
            $html = getTplAddDateTime(['name' => 'field[]', 'time' => $text, 'with' => false, 'max' => 10]);
        } else {
            $html = '';
        }
        if ($html != '') $rows[] = ['label_html' => getConst($out[1]), 'field_html' => $html];
        $pos++;
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
    $time = $time ? substr($time, 0, $max) : ($with ? date('Y-m-d H:i') : date('Y-m-d'));
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
            'input_attr' => 'id="'.$pid.'" data-sl-datetime-target="'.$hid.'" data-sl-datetime-kind="'.$type.'"',
        ]);
}

# Render preview field rows from dynamic module field definitions
function getTplViewFieldRows(array $data = []): string {
    global $conf, $tpl, $prs;
    $field = $data['field'] ?? '';
    $mod = $data['mod'] ?? '';
    $mod = strtolower($mod);
    if (is_array($field)) $field = filterFields($field);
    if (!$field || !$mod) return '';
    $vals = explode('|', (string)$field);
    $defs = explode('||', $conf['fields'][$mod] ?? '');
    $rows = '';
    $pos = 0;
    foreach ($defs as $item) {
        if ($item == '' || empty($vals[$pos])) {
            $pos++;
            continue;
        }
        preg_match('#(.*)\|(.*)\|(.*)\|(.*)#i', $item, $out);
        if (($out[1] ?? '0') != '0') {
            $valu = $vals[$pos];
            $type = $out[3] ?? '';
            if ($type == '2') {
                $valu = $prs->filterContent($valu, false, $mod);
            } else {
                $valu = htmlspecialchars((string)$valu, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            $rows .= $tpl->getHtmlFrag('field-value', [
                'label' => getConst($out[1]),
                'value_html' => $valu,
            ]);
        }
        $pos++;
    }
    return $rows;
}

# Render one shared refresh-time select with fixed interval choices
function getTplRefreshTimeSelect(array $data = []): string {
    global $tpl;
    $valu = $data['valu'] ?? '3600';
    $name = $data['name'] ?? 'refresh';
    $valu = ($valu === '' || $valu === '0' || $valu === 0) ? '3600' : (string)$valu;
    $opts = '';
    $times = [
        '900' => '15 '._MIN.'.',
        '1800' => '30 '._MIN.'.',
        '3600' => '1 '._HOUR.'.',
        '18000' => '5 '._HOUR.'.',
        '36000' => '10 '._HOUR.'.',
        '86400' => '24 '._HOUR.'.',
    ];
    foreach ($times as $value => $label) {
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$value,
            'label_text' => (string)$label,
            'is_selected' => $valu === $value,
        ]);
    }
    return $tpl->getHtmlFrag('select', [
        'name_attr' => $name,
        'options_html' => $opts,
    ]);
}

# Render one full preview block from prepared source texts and dynamic fields
function getTplPreviewContent(array $data = []): string {
    global $tpl, $prs;
    $title = $data['title'] ?? '';
    $texta = $data['texta'] ?? '';
    $textb = $data['textb'] ?? '';
    $field = $data['field'] ?? '';
    $mod = $data['mod'] ?? '';
    if ($title === '' && $texta === '' && $textb === '' && $field === '') return '';
    $bodya = $texta ? $prs->filterContent($texta, false, $mod) : '';
    $bodyb = $textb ? $prs->filterContent($textb, false, $mod) : '';
    $bodyc = $field ? getTplViewFieldRows(['field' => $field, 'mod' => $mod]) : '';
    return $tpl->getHtmlPart('preview', [
        'title' => _PREVIEW,
        'title_text' => (string)$title,
        'body_a' => $bodya,
        'body_b' => $bodyb,
        'body_c' => $bodyc,
    ]);
}

# Universal pager — works in both admin and front-end contexts
# Single source of truth for rendering a pager: prev/next nav, numbered links and dots,
# wrapped in the 'pager' fragment. $target(int $page): array yields the link target for a
# page — ['href' => ...] for URL navigation, or ['query' => ..., 'target_id' => ...,
# 'push_url' => ...] for HTMX navigation. Renders via $tpl->getHtmlFrag(), so each theme
# keeps its own pager fragments (admin and lite stay independent).
function getTplPagerView(int $num, int $pages, int $maxpg, callable $target, array $meta = []): string {
    global $tpl;
    if ($pages <= 1) return '';
    $num  = max(1, min($num, $pages));
    $nnum = $maxpg + 1;
    $link = static function(int $page, string $label, bool $cur, bool $nav, string $icon) use ($tpl, $target): string {
        $opt = ['label' => $label, 'title' => $label, 'is_cur' => $cur, 'is_nav' => $nav, 'icon_name' => $icon];
        if (!$cur) $opt += $target($page);
        return $tpl->getHtmlFrag('pager-link', $opt);
    };
    $dots = $tpl->getHtmlFrag('inline-badge', ['is_pager_dots' => true]);
    $prev = ($num > 1) ? $link($num - 1, _BACK, false, true, 'chevron-left') : $link(0, _BACK, true, true, 'chevron-left');
    $items = '';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === $num) {
            $items .= $link($i, (string)$i, true, false, '').' ';
        } elseif ($i === 1 || $i === $pages || (($i > ($num - $maxpg)) && ($i < ($num + $maxpg)))) {
            $items .= $link($i, (string)$i, false, false, '').' ';
        }
        if ($i < $pages) {
            if (($num > $nnum) && ($i === 1)) $items .= $dots;
            if (($num < ($pages - $maxpg)) && ($i === ($pages - 1))) $items .= $dots;
        }
    }
    $next = ($num < $pages) ? $link($num + 1, _NEXT, false, true, 'chevron-right') : $link(0, _NEXT, true, true, 'chevron-right');
    return $tpl->getHtmlFrag('pager', array_merge([
        'overall' => _OVERALL, 'by' => _BY, 'page_s' => _PAGE_S, 'perpage' => _PERPAGE,
        'pages' => $pages, 'prev' => $prev, 'items' => $items, 'next' => $next,
    ], $meta));
}

# Build a pager from a table COUNT query, reading the current page from the request.
function getTplPager(array $data = []): string {
    global $db, $afile;
    $limit = (int)($data['limit'] ?? 10);
    $maxpg = (int)($data['maxpg'] ?? 10);
    $table = $data['table'] ?? '';
    $field = $data['field'] ?? 'id';
    $where = $data['where'] ?? '';
    $mod = $data['mod'] ?? '';
    $anchor = $data['anchor'] ?? '';
    $n = $data['n'] ?? 'num';
    $url = html_entity_decode($data['url'] ?? '', ENT_QUOTES, 'UTF-8');
    $targetid = (string)($data['target_id'] ?? '');
    $pushurl = !empty($data['push_url']);
    $wparams = (array)($data['where_params'] ?? []);
    $urlx = (array)($data['url_extra'] ?? []);
    [$cnt] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT('.$field.') FROM '.PREFIX_DB.$table.($where ? ' WHERE '.$where : ''), $wparams));
    $cnt = (int)$cnt;
    if ($cnt <= $limit) return '';
    $pages = (int)ceil($cnt / $limit);
    $num = max(1, min(getVar('get', $n, 'num', 1), $pages));
    $mkurl = static function(int $i) use ($mod, $url, $n, $anchor, $afile, $urlx): string {
        if (defined('ADMIN_FILE')) return $afile.'.php?'.$url.$n.'='.$i.$anchor;
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

# Render one full admin module header with title, icon and top-level tabs
function getTplAdminTabs(array $data = []): string {
    global $afile, $conf, $tpl;
    $title = _ADMINMENU;
    $icon = 'grid';
    $subtitle = (string)($data['subtitle_html'] ?? '');
    $name = filterWord(getVar('req', 'name', 'text', ''));
    if ($name !== '' && isset($conf['modules'][$name]) && is_array($conf['modules'][$name])) {
        $lang = trim((string)($conf['modules'][$name]['lang'] ?? ''));
        if ($lang !== '') $title = defined($lang) ? constant($lang) : $lang;
        $ico = trim((string)($conf['modules'][$name]['icon'] ?? ''));
        if (preg_match('/^[a-z0-9-]+$/', $ico)) $icon = $ico;
    }
    $links = $data['links'] ?? [];
    if (!$links) {
        $ops = $data['ops'] ?? [];
        $tabs = $data['tabs'] ?? [];
        $active = (int)($data['tab'] ?? 0);
        foreach ($tabs as $idx => $label) {
            $links[] = [
                'href' => $afile.'.php?'.($ops[$idx] ?? ''),
                'is_active' => $idx === $active,
                'label' => $label,
                'title' => $label,
            ];
        }
    }
    $tabs = '';
    foreach ($links as $link) {
        $tabs .= $tpl->getHtmlFrag('tabs-link', [
            'href' => (string)($link['href'] ?? '#'),
            'is_active' => !empty($link['is_active']),
            'label' => (string)($link['label'] ?? ''),
            'link_attr' => (string)($link['link_attr'] ?? ''),
            'rel' => (string)($link['rel'] ?? ''),
            'title' => (string)($link['title'] ?? ($link['label'] ?? '')),
        ]);
    }
    return $tpl->getHtmlPart('module-head', [
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

# Render one full admin info page from a module info file with optional in-place editor
function setTplAdminInfoPage(array $data = []): void {
    global $afile, $locale, $conf, $tpl, $prs;
    $name = filterWord(getVar('get', 'name', 'text', ''));
    $ops = $data['ops'] ?? [];
    $tabs = $data['tabs'] ?? [];
    $tab = array_key_exists('tab', $data) ? (int)$data['tab'] : ($tabs ? count($tabs) - 1 : 0);
    $mod = $data['mod'] ?? 'info';
    $action = $data['action_url'] ?? ($afile.'.php?name='.$name.'&op=info');
    $save = $data['save_flag'] ?? 'save_info';
    $submit = $data['submit_label'] ?? _SAVECHANGES;
    $fdoc = static function(string $path): string {
        foreach (['.html', '.md'] as $ext) {
            if (file_exists($path.$ext)) return $path.$ext;
        }
        return '';
    };
    if (!empty($data['base'])) {
        $base = (string)$data['base'];
    } elseif ($name) {
        $mbase = 'modules/'.$name.'/admin/info';
        $abase = 'admin/info/'.$name;
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
    if (!empty($conf['adminfo']) && getVar('post', $save, 'num', 0)) {
        if (!checkSiteToken()) {
            $alert = $tpl->getHtmlFrag('alert', [
                'alert_attr' => 'data-sl-autohide="5000"',
                'is_flash' => true,
                'is_warn' => true,
                'text' => _TOKENMISS,
            ]);
            $text = (string)getVar('post', 'text', 'raw', $text);
        } else {
            // Info docs are Markdown source rendered in trusted mode (filterContent safe=false);
            // store raw with LF line endings — filterHtml would mangle Markdown (nl2br,
            // htmlspecialchars, $/quote escaping), and HTML forms submit CRLF.
            $content = trim(str_replace(["\r\n", "\r"], "\n", (string)getVar('post', 'text', 'raw', '')));
            if ($content !== '') {
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
    if (!empty($conf['adminfo']) && file_exists($path)) $cont .= checkPerms(BASE_DIR.'/'.$path);
    $cont .= $body;
    if (!empty($conf['adminfo'])) {
        $rows = [[
            'label_html' => '',
            'field_html' => getTplTextarea(['id' => '1', 'name' => 'text', 'value' => $text, 'mod' => $mod, 'rows' => '25']),
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

# Render one shared radio group from prepared option rows
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
            'label_text' => (string)($option['label'] ?? ''),
            'name_attr' => $name,
            'value_attr' => $valu,
        ]);
    }
    return $tpl->getHtmlFrag('block-content', ['switch' => $swit, 'is_radio_group' => true, 'content' => $items]);
}

# Render one shared user autocomplete input with datalist-backed lookup
function getTplUserSearchInput(array $data = []): string {
    global $tpl;
    $name = $data['name'] ?? 'uname';
    $inpid = $data['input_id'] ?? $name;
    $list = $data['list_id'] ?? ($inpid.'_list');
    $endpoint = $data['endpoint'] ?? 'index.php?go=1&op=getUserList';
    $mlen = (int)($data['minlength'] ?? 1);
    $tip = (string)($data['tip'] ?? '');
    $tiphtml = '';
    if ($tip !== '') {
        $tiphtml = getTplTitleTip($tip);
    }
    return $tpl->getHtmlFrag('input', [
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


# Render one shared admin move-controls block with HTMX transport
function getTplMoveControls(array $data = []): string {
    global $tpl;
    $target = (string)($data['target'] ?? '');
    $up = (string)($data['up'] ?? '');
    $down = (string)($data['down'] ?? '');
    $up = ($up && !str_contains($up, 'token=')) ? $up.'&token='.getSiteToken() : $up;
    $down = ($down && !str_contains($down, 'token=')) ? $down.'&token='.getSiteToken() : $down;
    return $tpl->getHtmlFrag('move-controls', [
        'down_query' => $down,
        'down_title' => _BLOCKDOWN,
        'target' => $target,
        'up_query' => $up,
        'up_title' => _BLOCKUP,
    ]);
}

# Render extra field rows for new/ form layout (getTplFieldsIn() replacement for new/form-add)
function getTplFieldsIn(array $data = []): string {
    global $conf, $tpl;
    $field  = $data['field'] ?? '';
    $mod    = strtolower($data['mod'] ?? '');
    $fieldc = $conf['fields'][$mod] ?? '';
    $posted = getVar('post', 'field[]', 'raw', []);
    if (is_array($posted) && $posted) {
        $fieldb = array_values(array_map('strval', $posted));
    } else {
        $fieldb = explode('|', is_string($field) ? $field : '');
    }
    $fieldc = explode('||', $fieldc);
    $i = 0;
    $out = '';
    foreach ($fieldc as $item) {
        if ($item !== '') {
            preg_match('#(.*)\|(.*)\|(.*)\|(.*)#i', $item, $m);
            if (($m[1] ?? '0') !== '0') {
                $fieldin   = !empty($fieldb[$i]) ? $fieldb[$i] : ($m[2] ?? '');
                $requir    = (($m[4] ?? '0') == '1') ? ' required' : '';
                $fhtml = '';
                if (($m[3] ?? '') == '1') {
                    $dval = $fieldin ? getConst($fieldin) : '';
                    $fhtml = $tpl->getHtmlFrag('input', [
                        'input_attr' => 'placeholder="'.$dval.'"'.$requir,
                        'itype' => 'text',
                        'name_attr' => 'field[]',
                        'value_attr' => $dval,
                    ]);
                } elseif ($m[3] == '2') {
                    $fhtml = $tpl->getHtmlFrag('textarea', ['name_attr' => 'field[]', 'rows_num' => 5, 'value_text' => $fieldin, 'input_attr' => trim($requir)]);
                } elseif ($m[3] == '3') {
                    $opts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _NO, 'is_selected' => $fieldin === '']);
                    foreach (explode(',', $m[2] ?? '') as $opt) {
                        if ($opt === '') continue;
                        $opts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $opt, 'label_text' => $opt, 'is_selected' => $opt === $fieldin]);
                    }
                    $fhtml = $tpl->getHtmlFrag('select', ['name_attr' => 'field[]', 'options_html' => $opts, 'select_attr' => trim($requir)]);
                } elseif ($m[3] == '4') {
                    $fhtml = getTplAddDateTime(['name' => 'field[]', 'time' => $fieldin, 'with' => true, 'max' => 16]);
                } elseif ($m[3] == '5') {
                    $fhtml = getTplAddDateTime(['name' => 'field[]', 'time' => $fieldin, 'with' => false, 'max' => 10]);
                }
                if ($fhtml !== '') {
                    $out .= $tpl->getHtmlFrag('form-field-row', ['label' => getConst($m[1]), 'field_html' => $fhtml]);
                }
            }
        }
        $i++;
    }
    return $out;
}

# Render a rich-text editor textarea with upload config and locale for the given module
function getTplTextarea(array $data = []): string {
    global $conf;
    $id = (string)($data['id'] ?? '1');
    $name = (string)($data['name'] ?? '');
    $value = (string)($data['value'] ?? '');
    $mod = (string)($data['mod'] ?? '');
    $rows = (int)($data['rows'] ?? 5);
    $phld = (string)($data['placeholder'] ?? '');
    $required = in_array($data['required'] ?? '', [true, 1, '1', 'required'], true);
    $stloc = substr(_LOCALE, 0, 2);
    $desc = $value ?: filterHtml(getVar('post', $name, 'raw', ''));
    $con = explode('|', (string)($conf['uploads'][strtolower($mod)] ?? ''));
    $key = getEditorKey();
    $fmt = getEditorMode($key);
    return Editor::getContent([
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
        'con' => $con,
    ]);
}

# Build data attributes for inserting text into the active content editor
function getTplEditorInsertAttr(string $command, string $value, string $editorId = '1'): string {
    $esc = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return 'data-sl-editor-insert="'.$esc($command).'" data-sl-editor-id="'.$esc($editorId).'" data-sl-editor-value="'.$esc($value).'"';
}

# Render an inline HTMX edit form with a textarea and save/back buttons
function getTplAjaxTextarea(array $data = []): string {
    global $tpl;
    $obj  = (string)($data['obj']  ?? '');
    $go   = (string)($data['go']   ?? '');
    $op   = (string)($data['op']   ?? '');
    $id   = (string)($data['id']   ?? '');
    $cid  = (string)($data['cid']  ?? '0');
    $typ  = (string)($data['typ']  ?? '0');
    $mod  = (string)($data['mod']  ?? '');
    $text = (string)($data['text'] ?? '');
    $rows = (int)   ($data['rows'] ?? 5);
    $desc    = !checkHtmlEditor() ? replace_break($text) : $text;
    $formId  = 'form'.$obj;
    $fieldId = $formId.'_text';
    $esc     = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $query   = 'index.php?go='.$esc($go).'&op='.$esc($op).'&id='.$esc($id).'&cid='.$esc($cid).'&typ='.$esc($typ).'&mod='.$esc($mod);
    $cerror  = addslashes((string)_CERROR1);
    $content = $tpl->getHtmlFrag('textarea', [
            'name_attr'   => 'text',
            'rows_num'    => $rows,
            'value_text'  => $desc,
            'is_editor_area' => true,
            'input_attr'  => 'id="'.$fieldId.'"',
        ])
        .$tpl->getHtmlFrag('button', [
            'button_type'  => 'submit',
            'submit_label' => _SAVE,
            'is_legacy_green' => true,
            'button_attr'  => 'hx-post="'.$query.'" hx-include="#'.$formId.'" hx-target="#rep'.$obj.'" hx-swap="innerHTML" hx-push-url="false" hx-on:click="if (!document.getElementById(\''.$formId.'\').querySelector(\'[name=&quot;text&quot;]\').value.trim()) { alert(\''.$cerror.'\'); event.preventDefault(); }"',
        ])
        .$tpl->getHtmlFrag('button', [
            'button_type'  => 'submit',
            'submit_label' => _BACK,
            'button_attr'  => 'hx-get="'.$query.'" hx-target="#rep'.$obj.'" hx-swap="innerHTML" hx-push-url="false"',
        ]);
    return $tpl->getHtmlPart('form-wrap', ['form_name' => 'textareae', 'form_id' => $formId, 'content_html' => $content]);
}

# Render the shared "new" badge for fresh content
function getTplNewGraphic(string $time): string {
    global $tpl;
    $data = time() - strtotime($time);
    if ($data < 86400) return $tpl->getHtmlFrag('span', ['is_new_today' => true, 'title' => (string)_NEWTODAY]);
    if ($data < 259200) return $tpl->getHtmlFrag('span', ['is_new_days' => true, 'title' => (string)_NEWLAST3DAYS]);
    if ($data < 604800) return $tpl->getHtmlFrag('span', ['is_new_week' => true, 'title' => (string)_NEWTHISWEEK]);
    return '';
}

# Build the standard admin edit/delete menu keys for any front-end content row or card; callers pass the exact admin hrefs
function getTplEditMenu(string $edithref, string $delhref, string $title): array {
    $ask = str_replace(['\\', "'"], ['\\\\', "\\'"], _DELETE.' &quot;'.$title.'&quot;?');
    return [
        'is_moder'     => true,
        'editor_label' => _EDITOR,
        'edit_href'    => $edithref,
        'edit_text'    => _FULLEDIT,
        'delete_href'  => $delhref,
        'delete_text'  => _ONDELETE,
        'delete_ask'   => $ask,
    ];
}

# Format a gender value for display
function getGenderText(int $gender): string {
    if ($gender == 2) return (string)_WOMAN;
    if ($gender == 1) return (string)_MAN;
    return (string)_NO_INFO;
}

# Render one title tip block from one or many label-value items
function getTplTitleTip(mixed $data): string {
    global $tpl;
    # Single plain tip: render the text directly, without a label/definition grid
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

# Build the hover info tip shown before a user name (comments, forum posts, private messages)
function getUserTip(string $gname, string|int $points, string $regdate, int $gender, string $from, string $warnings, bool $anon = false, bool $deleted = false): string {
    global $conf;
    # No bound account: the tip states the status instead of empty profile fields.
    # $deleted marks orphaned posts whose uid points to a removed user.
    if ($anon) return getTplTitleTip([['label' => _STATUS, 'value' => (string)($deleted ? _USERDEL : _ANONYM)]]);
    $items = [];
    if ($gname !== '') $items[] = ['label' => _GROUP, 'value' => htmlspecialchars($gname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')];
    if ($conf['users']['point'] && $points) $items[] = ['label' => _POINTS, 'value' => htmlspecialchars((string)$points, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')];
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

# Render the shared ajax rating block for stars or like-style controls
function getRatingAsync(mixed $typ, mixed $id, mixed $mod, mixed $rat, mixed $scor, string $obj = '', string $stl = ''): string {
    global $conf, $tpl;
    if (intval($rat)) {
        $votnum = $rat;
        $votes = $rat;
    } else {
        $votnum = 0;
        $votes = 1;
    }
    $width = (int)max(0, min(100, round(($scor / $votes) * 20)));
    $result = number_format($scor / $votes, 2);
    if (intval($votes) && intval($scor)) {
        $title = _RATING.': '.$result.'/'.$votes.' '._AVERAGESCORE.': '.$result;
        $scored = true;
    } else {
        $title = _RATING.': 0/0 '._AVERAGESCORE.': 0';
        $scored = false;
    }
    $con = explode('|', $conf['ratings'][strtolower((string)$mod)] ?? '');
    if ($typ != 2 && !((($con[1] ?? '') && $id && $mod) || ($rat && $scor))) return '';
    $live = $typ != 2 && ($con[1] ?? '') && ($typ || !($con[2] ?? ''));
    $vote = 'go=1&op=getRatingView&id='.$id.'&typ='.$obj.'&mod='.$mod;
    $part = $stl == '1'
        ? ['rate1_title' => _RATE1, 'rate5_title' => _RATE5]
        : ['width' => (string)$width, 'stars' => getRatingStars($width), 'votes' => (string)$votnum, 'votes_title' => _VOTES];
    $body = $tpl->getHtmlFrag($stl == '1' ? 'rating-like' : 'rating-bar', [
        'result' => $result,
        'title' => $title,
        'has_score' => $scored,
        'target_id' => 'rep'.$id.$obj,
        'vote_query' => $vote,
        'token' => $live ? getSiteToken() : '',
        'is_live' => $live,
    ] + $part);
    $wrap = $live || $typ == 2 ? ['id' => 'rep'.$id.$obj] : [];
    return $tpl->getHtmlPart('div', $wrap + ['is_rate' => true, 'content_html' => $body]);
}

# Render the shared category select from database categories
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
            while ($flag != 0) {
                $cont[$key] = $pref.$cont[$key];
                $flag = intval($mass[$flag][1]);
            }
            $opts .= $tpl->getHtmlFrag('select-option', [
                'value_attr' => (string)$key,
                'label_text' => $cont[$key],
                'is_selected' => $id == $key,
            ]);
        }
        return !$raw ? $tpl->getHtmlFrag('select', ['name_attr' => $name, 'select_class' => $clas, 'title' => _CATEGORIES, 'options_html' => $opts]) : $opts;
    }
    if ($empty) return $tpl->getHtmlFrag('select', ['name_attr' => $name, 'select_class' => $clas, 'title' => _CATEGORIES, 'options_html' => $empty]);
    return '';
}

# Render the shared category breadcrumb trail; rich mode emits tone-aware crumbs, plain mode keeps flat links for the head banner
function getTplCategoryTrail(string $mod = '', int $id = 0, string $sep = '', string $home = '', bool $rich = true): string {
    global $db, $conf, $tpl;
    $mod = filterVar($mod);
    $name = $mod ?: $conf['name'];
    $symbol = urldecode($sep ?: $conf['defis']);
    static $cache = [];
    if (!isset($cache[$name])) {
        $where = $mod ? 'WHERE modul = :modul' : '';
        $pars = $mod ? ['modul' => $mod] : [];
        $res = $db->getSqlQuery('SELECT id, title, parent, ordern FROM '.PREFIX_DB.'_categories '.$where, $pars);
        $mass = [];
        while ([$cid, $title, $parent, $ordern] = $db->getSqlRow($res)) $mass[(int)$cid] = [getConst($title), (int)$parent, (int)$ordern];
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

# Render a multi-select for modules
function getTplModuleSelect(string $name, string $mod, string $no = '', array $allow = []): string {
    global $tpl;
    $cont = '';
    if ($no !== '') $cont .= $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _NO, 'is_selected' => empty($mod)]);
    $mods = explode(',', $mod);
    foreach (scandir('modules') as $file) {
        if (str_contains($file, '.')) continue;
        if ($allow && !in_array($file, $allow, true)) continue;
        $isel = false;
        foreach ($mods as $val) {
            if ($val !== '' && $val === $file) {
                $isel = true;
                break;
            }
        }
        $cont .= $tpl->getHtmlFrag('select-option', ['value_attr' => $file, 'label_text' => getModuleName($file), 'is_selected' => $isel]);
    }
    return $tpl->getHtmlFrag('select', ['name_attr' => $name, 'is_config' => true, 'options_html' => $cont, 'is_multiple' => true, 'is_name_array' => true]);
}

# Return the names of modules that support categories
function getCategoryModules(): array {
    return ['faq', 'files', 'forum', 'help', 'jokes', 'links', 'media', 'news', 'pages', 'shop'];
}

# Render a select with category-enabled modules
function getTplCategoryModule(string $name, string $clas = '', string $sel = '', bool $auto = false): string {
    global $tpl;
    $attr = $auto ? 'OnChange="submit()"' : '';
    $cont = '';
    $mods = getCategoryModules();
    foreach ($mods as $mod) {
        $cont .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $mod,
            'label_text' => getModuleName($mod).' - '.$mod,
            'is_selected' => $sel == $mod,
        ]);
    }
    return $tpl->getHtmlFrag('select', ['name_attr' => $name, 'select_class' => $clas, 'select_attr' => $attr, 'options_html' => $cont]);
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

# Render a module navigation block from module config and optional overrides
function getModuleNavi(array $p): string {
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
    $home = $p['home_href'] ?? getSeoUrl(['name' => $conf['name']]);
    $best = $p['best_href'] ?? ($showrate ? getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => $bop]) : '');
    $pop = $p['pop_href'] ?? ($showrate ? getSeoUrl(['name' => $conf['name']] + $cpar + ['op' => 'pop']) : '');
    $list = $p['liste_href'] ?? getSeoUrl(['name' => $conf['name'], 'op' => 'liste']);
    $add = $p['add_href'] ?? ($canadd ? getSeoUrl(['name' => $conf['name'], 'op' => 'add']) : '');
    $btit = $p['btitle'] ?? _BEST;
    $ptit = $p['ptitle'] ?? _POP;
    $catshow = $p['catshow'] ?? $cat;
    return $tpl->getHtmlPart('navi', [
        'title' => $title,
        'is_heading' => $p['is_heading'] ?? true,
        'home_link' => ['href' => $home, 'title' => $htitle, 'label' => _HOME, 'is_navi_button' => true],
        'best_link' => $best ? ['href' => $best, 'title' => $btit, 'label' => $btit, 'is_navi_button' => true] : [],
        'pop_link' => $pop ? ['href' => $pop, 'title' => $ptit, 'label' => $ptit, 'is_navi_button' => true] : [],
        'list_link' => $list ? ['href' => $list, 'title' => _LIST, 'label' => _LIST, 'is_navi_button' => true] : [],
        'add_link' => $add ? ['href' => $add, 'title' => _ADD, 'label' => _ADD, 'is_navi_button' => true] : [],
        'cat_link' => $catshow ? ['title' => _CATVORH, 'label' => _CATEGORIES, 'is_navi_button' => true, 'is_category_toggle' => true] : [],
    ]);
}

# Render page numbers from known counters
function getPageNumbers(string $mod, int $count, int $pages, int $limit, string $url = '', int $maxpg = 8, int $num = 0, string $anchor = '', string $n = 'num'): string {
    global $afile;
    $num = $num ?: getVar('get', $n, 'num', 1);
    $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
    $target = static function(int $i) use ($mod, $url, $n, $anchor, $afile): array {
        $href = (!defined('ADMIN_FILE')) ? getSeoUrl(['name' => $mod, $url.$n => $i]).$anchor : $afile.'.php?'.$url.$n.'='.$i.$anchor;
        return ['href' => $href];
    };
    return getTplPagerView((int)$num, $pages, $maxpg, $target, ['count' => $count, 'limit' => $limit]);
}

# End of stable helper functions for building admin and frontend HTML from prepared data cuts and shared templates
