<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
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
            $html = $tpl->getHtmlFrag('new/input', [
                'itype' => 'text',
                'name_attr' => 'field[]',
                'value_attr' => $dval,
                'placeholder_text' => $dval,
                'input_attr' => trim($need),
            ]);
        } elseif ($type == '2') {
            $html = $tpl->getHtmlFrag('new/textarea', [
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
                $opts .= $tpl->getHtmlFrag('new/select-option', [
                    'value_attr' => $name,
                    'label_text' => $name,
                    'is_selected' => $name == $text,
                ]);
            }
            $html = $tpl->getHtmlFrag('new/select', [
                'name_attr' => 'field[]',
                'options_html' => $tpl->getHtmlFrag('new/select-option', [
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
        if ($html != '') $rows[] = ['label_html' => getConst($out[1]).':', 'field_html' => $html];
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
    $attr = $data['attr'] ?? '';
    $time = $time ? substr($time, 0, $max) : ($with ? date('Y-m-d H:i') : date('Y-m-d'));
    static $fieldid = 0;
    $fieldid++;
    $type = $with ? 'datetime-local' : 'date';
    $pvalu = $with ? str_replace(' ', 'T', substr($time, 0, 16)) : substr($time, 0, 10);
    $hid = 'sl_datetime_hidden_'.$fieldid;
    $pid = 'sl_datetime_picker_'.$fieldid;
    $phold = $with ? 'YYYY-MM-DD HH:MM' : 'YYYY-MM-DD';
    return getTplHiddenInput([
            'name' => (string)$name,
            'value' => (string)$time,
            'attr' => 'id="'.$hid.'"',
        ]).$tpl->getHtmlFrag('new/input', [
            'itype' => $type,
            'name_attr' => $pid,
            'value_attr' => $pvalu,
            'maxlength_num' => $max,
            'placeholder_text' => $phold,
            'input_attr' => 'id="'.$pid.'" data-sl-datetime-target="'.$hid.'" data-sl-datetime-kind="'.$type.'"'.($attr ? ' '.$attr : ''),
        ]);
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
        $opts .= $tpl->getHtmlFrag('new/select-option', [
            'value_attr' => (string)$value,
            'label_text' => (string)$label,
            'is_selected' => $valu === $value,
        ]);
    }
    return $tpl->getHtmlFrag('refresh-select-time', [
        'name_attr' => $name,
        'options_html' => $opts,
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
            $rows .= $tpl->getHtmlFrag('view-field', [
                'label_text' => getConst($out[1]).':',
                'value_html' => $valu,
            ]);
        }
        $pos++;
    }
    return $rows;
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
    $titlhtml = $title ? '<b>'.htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</b>' : '';
    $bodya = $texta ? $prs->filterContent($texta, false, $mod) : '';
    $bodyb = $textb ? $prs->filterContent($textb, false, $mod) : '';
    $bodyc = $field ? getTplViewFieldRows(['field' => $field, 'mod' => $mod]) : '';
    return $tpl->getHtmlPart('preview-content', [
        'title' => _PREVIEW,
        'title_html' => $titlhtml,
        'body_a' => $bodya,
        'body_b' => $bodyb,
        'body_c' => $bodyc,
    ]);
}

# Build the complete BB editor shell with toolbars, textarea and upload panel as a single inline HTML string
function getTplBbEditor(array $data = []): string {
    global $conf, $user, $op, $tpl;
    $id = (string)($data['id'] ?? '1');
    $name = $data['name'] ?? '';
    $value = replace_break($data['value'] ?? '');
    $rows = (int)($data['rows'] ?? 25);
    $phld = $data['placeholder'] ?? '';
    $required = $data['required'] ?? '';
    $stloc = $data['stloc'] ?? substr(_LOCALE, 0, 2);
    $mod = $data['mod'] ?? '';
    $con = $data['con'] ?? [];
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $btn = static function(string $onclick, string $cls, string $title, string $over = '') use ($e): string {
        $ov = ($over !== '') ? ' OnMouseOver="'.$e($over).'"' : '';
        return '<span'.$ov.' OnClick="'.$e($onclick).'" class="'.$e($cls).'" title="'.$e($title).'"></span>';
    };
    $sep = '<div class="sl_bb_sep"></div>';
    $drop = static fn(string $b, string $c): string => '<div class="sl_drop">'.$b.$c.'</div>';

    # Top toolbar
    $top = '<div class="sl_pos_right">'
        .$btn("RowsTextarea(1, '".$id."')", 'sl_bb_plus', _EPLUS)
        .$btn("RowsTextarea(0, '".$id."')", 'sl_bb_minus', _EMINUS)
        .'</div>'
        .$btn("InsertCode('b', '', '', '', '".$id."')", 'sl_bb_b', _EBOLD)
        .$btn("InsertCode('i', '', '', '', '".$id."')", 'sl_bb_i', _EITALIC)
        .$btn("InsertCode('u', '', '', '', '".$id."')", 'sl_bb_u', _EUNDERLINE)
        .$btn("InsertCode('s', '', '', '', '".$id."')", 'sl_bb_s', _ESTRIKET)
        .$btn("InsertCode('li', '', '', '', '".$id."')", 'sl_bb_li', _ELI)
        .$btn("InsertCode('hr', '', '', '', '".$id."')", 'sl_bb_hr', _EHR)
        .$sep
        .$btn("InsertCode('left', '', '', '', '".$id."')", 'sl_bb_left', _ELEFT)
        .$btn("InsertCode('center', '', '', '', '".$id."')", 'sl_bb_center', _ECENTER)
        .$btn("InsertCode('right', '', '', '', '".$id."')", 'sl_bb_right', _ERIGHT)
        .$btn("InsertCode('justify', '', '', '', '".$id."')", 'sl_bb_justify', _EYUSTIFY)
        .$sep
        .$btn("InsertCode('hide', '', '', '', '".$id."')", 'sl_bb_hide', _HIDE)
        .$btn("InsertCode('url', '"._JINFO."', '"._JTYPE."', '"._JERROR."', '".$id."')", 'sl_bb_link', _EURL)
        .$btn("InsertCode('mail', '"._JINFO."', '"._JTYPE."', '"._JERROR."', '".$id."')", 'sl_bb_mail', _EEMAIL)
        .$btn("InsertCode('img', '"._JINFO."', '"._JTYPE."', '"._JERROR."', '".$id."')", 'sl_bb_img', _EIMG)
        .$btn("InsertCode('quote', '"._JQUOTE."', '', '', '".$id."')", 'sl_bb_quote', _EQUOTE, 'CopyText();');

    # Textarea
    $ta = $tpl->getHtmlFrag('new/textarea', [
        'name_attr' => $name,
        'rows_num' => $rows,
        'value_text' => $value,
        'input_attr' => 'id="'.$e($id).'" OnKeyPress="TransliteFeld(this, event)" OnSelect="FieldName(this, \''.$e($id).'\')" OnClick="FieldName(this, \''.$e($id).'\')" OnKeyUp="FieldName(this, \''.$e($id).'\')"'.$phld.$required,
    ]);

    # Bottom toolbar — info panel
    $bottom = '<div class="sl_pos_right">'
        .$drop(
            $btn("HideShow('i-form-".$id."', 'blind', 'up', 500);", 'sl_bb_info', _INFO),
            '<div id="i-form-'.$e($id).'" class="sl_drop-form">'.(_INFO_BB.' '.($conf['version'] ?? '')).'</div>'
        )
        .'</div>';

    # Upload button (file picker trigger)
    if ((defined('ADMIN_FILE') && ($con[10] ?? 0) == 1) || (is_user() && ($con[10] ?? 0) == 1) || (!is_user() && ($con[11] ?? 0) == 1)) {
        $bottom .= $btn("HideShow('af-form-".$id."', 'slide', 'up', 500); htmx.ajax('GET', 'index.php?go=1&op=getEditorFiles&id=".$id.'&dir='.$mod."', {target:'#repf".$id."', swap:'innerHTML'}); return false;", 'sl_bb_file', _EUPLOAD);
    }

    # Smilies panel
    $smilies = '';
    $si = 1;
    $smdir = img_find('smilies');
    if (!is_dir($smdir)) {
        foreach (['templates/admin/images/smilies', 'templates/lite/images/smilies'] as $fdir) {
            if (is_dir($fdir)) { $smdir = $fdir; break; }
        }
    }
    $slist = is_dir($smdir) ? scandir($smdir) : false;
    if ($slist !== false) {
        foreach ($slist as $entry) {
            if (preg_match('#(\.gif)$#i', $entry) && $entry !== '.' && $entry !== '..') {
                $si = ($si < 10) ? '0'.$si : $si;
                $smsrc = is_file($smdir.'/'.$si.'.gif') ? $smdir.'/'.$si.'.gif' : img_find('smilies/'.$si.'.gif');
                $smilies .= ' <img src="'.$e($smsrc).'" OnClick="InsertCode(\'smilies\', \' *'.$si.'\', \'\', \'\', \''.$e($id).'\');"'
                    .' style="cursor: pointer; margin: 3px 2px 0px 0px;"'
                    .' alt="'._SMILIE.' - '.$si.'" title="'._SMILIE.' - '.$si.'">';
                $si++;
            }
        }
    }
    $bottom .= $drop(
        $btn("HideShow('s-form-".$id."', 'blind', 'up', 500);", 'sl_bb_smile', _ESMILIE),
        '<div id="s-form-'.$e($id).'" class="sl_drop-form">'.$smilies.'</div>'
    );

    # Translate panel (Russian locale only)
    if ($stloc === 'ru') {
        $cyr = '<td>А</td><td>Б</td><td>В</td><td>Г</td><td>Д</td><td>Е</td><td>Ё</td><td>Ж</td><td>З</td><td>И</td><td>Й</td><td>К</td><td>Л</td><td>М</td><td>Н</td><td>О</td><td>П</td><td>Р</td><td>С</td><td>Т</td><td>У</td><td>Ф</td><td>Х</td><td>Ц</td><td>Ч</td><td>Ш</td><td>Щ</td><td>Ъ</td><td>Ы</td><td>Ь</td><td>Э</td><td>Ю</td><td>Я</td>';
        $lat = '<td>A</td><td>B</td><td>V</td><td>G</td><td>D</td><td>E</td><td>JO</td><td>ZH</td><td>Z</td><td>I</td><td>J</td><td>K</td><td>L</td><td>M</td><td>N</td><td>O</td><td>P</td><td>R</td><td>S</td><td>T</td><td>U</td><td>F</td><td>X</td><td>C</td><td>CH</td><td>SH</td><td>W</td><td>\'</td><td>Y</td><td>#</td><td>JE</td><td>JU</td><td>JA</td>';
        $trans = '<div id="l-form-'.$e($id).'" class="sl_drop-form"><table class="sl_bb_trans"><tr>'.$cyr.'</tr><tr>'.$lat.'</tr></table></div>';
        $bottom .= $drop($btn("HideShow('l-form-".$id."', 'blind', 'up', 500); changelanguage();", 'sl_bb_translate', _EAUTOTR), $trans)
            .$btn('translateAlltoCyrillic()', 'sl_bb_translit', _ERUS)
            .$btn('translateAlltoLatin()', 'sl_bb_trans', _ELAT);
    }

    # Text formatting panel (font / color / size)
    $fonts = $tpl->getHtmlFrag('new/select-option', ['value_attr' => '', 'label_text' => _FONT, 'is_selected' => false]);
    foreach (['Arial', 'Courier', 'Mistral', 'Impact', 'Sans Serif', 'Tahoma', 'Helvetica', 'Verdana'] as $f) {
        $fonts .= $tpl->getHtmlFrag('new/select-option', ['value_attr' => $f, 'label_text' => $f, 'is_selected' => false]);
    }
    $colors = $tpl->getHtmlFrag('new/select-option', ['value_attr' => '', 'label_text' => _ECOLOR, 'is_selected' => false]);
    foreach (['black', 'gray', 'silver', 'white', 'maroon', 'red', 'orangered', 'orange', 'yellow', 'purple', 'fuchsia', 'violet', 'darkgreen', 'green', 'lime', 'navy', 'blue', 'teal', 'aqua'] as $c) {
        $colors .= $tpl->getHtmlFrag('new/select-option', ['value_attr' => $c, 'label_text' => $c, 'is_selected' => false]);
    }
    $fsizes = $tpl->getHtmlFrag('new/select-option', ['value_attr' => '', 'label_text' => _ESIZE, 'is_selected' => false]);
    foreach (['8', '10', '12', '14', '16', '18', '20', '22', '24', '26', '28', '30', '32'] as $fs) {
        $fsizes .= $tpl->getHtmlFrag('new/select-option', ['value_attr' => $fs, 'label_text' => $fs, 'is_selected' => false]);
    }
    $ei = $e($id);
    $bottom .= $drop(
        $btn("HideShow('t-form-".$id."', 'blind', 'up', 500);", 'sl_bb_text', _TEXT),
        '<div id="t-form-'.$ei.'" class="sl_drop-form"><ul>'
        .'<li>'.$tpl->getHtmlFrag('multi-select', ['name_attr' => 'family', 'options_html' => $fonts, 'select_attr' => 'OnChange="InsertCode(\'family\', this.options[this.selectedIndex].value, \'\', \'\', \''.$ei.'\'); this.selectedIndex=0;"']).'</li>'
        .'<li>'.$tpl->getHtmlFrag('multi-select', ['name_attr' => 'color', 'options_html' => $colors, 'select_attr' => 'OnChange="InsertCode(\'color\', this.options[this.selectedIndex].value, \'\', \'\', \''.$ei.'\'); this.selectedIndex=0;"']).'</li>'
        .'<li>'.$tpl->getHtmlFrag('multi-select', ['name_attr' => 'size', 'options_html' => $fsizes, 'select_attr' => 'OnChange="InsertCode(\'size\', this.options[this.selectedIndex].value, \'\', \'\', \''.$ei.'\'); this.selectedIndex=0;"']).'</li>'
        .'</ul></div>'
    );

    # Code syntax panel
    $fcodes = $tpl->getHtmlFrag('new/select-option', ['value_attr' => '', 'label_text' => _CODE, 'is_selected' => false]);
    foreach (['Bash', 'Cpp', 'CSharp', 'Css', 'Delphi', 'Diff', 'Groovy', 'Java', 'JScript', 'Php', 'Plain', 'Python', 'Ruby', 'Scala', 'Sql', 'Vb', 'Xml'] as $fc) {
        $fcodes .= $tpl->getHtmlFrag('new/select-option', ['value_attr' => strtolower($fc), 'label_text' => $fc, 'is_selected' => false]);
    }
    $bottom .= $drop(
        $btn("HideShow('c-form-".$id."', 'blind', 'up', 500);", 'sl_bb_code', _CODE),
        '<div id="c-form-'.$ei.'" class="sl_drop-form"><ul>'
        .'<li>'.$tpl->getHtmlFrag('multi-select', ['name_attr' => 'code', 'options_html' => $fcodes, 'select_attr' => 'OnChange="InsertCode(\'code\', this.options[this.selectedIndex].value, \'\', \'\', \''.$ei.'\'); this.selectedIndex=0;"']).'</li>'
        .'</ul></div>'
    );

    # Admin-only: HTML, PHP, page-break buttons
    if (isAdmin()) {
        $confname = $conf['name'] ?? '';
        $bottom .= $sep
            .$btn("InsertCode('usehtml', '', '', '', '".$id."')", 'sl_bb_html', _EUSEHTML)
            .$btn("InsertCode('usephp', '', '', '', '".$id."')", 'sl_bb_php', _EUSEPHP);
        if ($op === 'faq_add' || $op === 'news_add' || $op === 'page_add' || $confname === 'faq' || $confname === 'news' || $confname === 'page') {
            $bottom .= $btn("InsertCode('pagebreak', '', '', '', '".$id."')", 'sl_bb_break', _EBREAK);
        }
    }

    # Upload panel (file manager)
    $upload = '';
    if ((defined('ADMIN_FILE') && ($con[10] ?? 0) == 1) || (is_user() && ($con[10] ?? 0) == 1) || (!is_user() && ($con[11] ?? 0) == 1)) {
        $uid = intval($user[0] ?? 0);
        if ($id === '1') {
            $uinfo = '<div class="ico sl_info sl_left"><b>'._UPLOADINFO.'</b><br>'
                ._FTYPE.': '.str_replace(',', ', ', $con[0] ?? '').'<br>'
                ._FSIZEALL.': '.filterSize($con[1] ?? 0).'<br>'
                ._FSIZE.': '.filterSize($con[2] ?? 0).'<br>'
                ._AWIDTH.': '.($con[3] ?? '').' px<br>'
                ._AHEIGHT.': '.($con[4] ?? '').' px<br>'
                ._FILEUP.': '.($con[5] ?? '').'<br></div>';
            $tok = $e(getSiteToken('upload'));
            $inner = '<div id="msg">'.$uinfo.'</div>'
                .'<div class="sl_pos_center">'
                .'<form id="formfile'.$ei.'" hx-post="index.php?go=4&amp;mod='.$e($mod).'&amp;userid='.$uid.'"'
                .' hx-encoding="multipart/form-data" hx-target="#msg" hx-swap="innerHTML" hx-trigger="change from:#file_upload"'
                .' hx-on:htmx:before-request="document.getElementById(&quot;msg&quot;).innerHTML=&quot;&lt;div class=\&quot;sl_loading\&quot;&gt;&lt;/div&gt;&lt;br&gt;&quot;"'
                .' hx-on:htmx:after-request="htmx.ajax(&quot;GET&quot;, &quot;index.php?go=1&amp;op=getEditorFiles&amp;id='.$ei.'&amp;dir='.$e($mod).'&quot;, {target:&quot;#repf'.$ei.'&quot;, swap:&quot;innerHTML&quot;})">'
                .getTplHiddenInput(['name' => 'upload_token', 'value' => $tok])
                .$tpl->getHtmlFrag('file-input', ['name_attr' => 'file[]', 'input_id' => 'file_upload', 'is_multiple' => true])
                .'</form>'
                .$tpl->getHtmlFrag('button', ['button_type' => 'button', 'submit_label' => _UPDATE, 'button_class' => 'sl_but_green', 'button_attr' => 'OnClick="htmx.ajax(&quot;GET&quot;, &quot;index.php?go=1&op=getEditorFiles&id='.$ei.'&dir='.$e($mod).'&quot;, {target:&quot;#repf'.$ei.'&quot;, swap:&quot;innerHTML&quot;}); return false;"'])
                .'</div>';
        } else {
            $inner = '<div class="sl_pos_center">'.$tpl->getHtmlFrag('button', ['button_type' => 'button', 'submit_label' => _UPDATE, 'button_class' => 'sl_but_green', 'button_attr' => 'OnClick="htmx.ajax(&quot;GET&quot;, &quot;index.php?go=1&op=getEditorFiles&id='.$ei.'&dir='.$e($mod).'&quot;, {target:&quot;#repf'.$ei.'&quot;, swap:&quot;innerHTML&quot;}); return false;"']).'</div>';
        }
        $inner .= '<div id="repf'.$ei.'" style="margin: 5px;"></div>';
        $upload = '<div id="af-form-'.$ei.'" class="sl_bbup-panel sl_none">'.$inner.'</div>';
    }

    return '<div class="sl_bb-editor">'
        .'<div class="sl_bb-panel">'.$top.'</div>'
        .$ta
        .'<div class="sl_bb-panel">'.$bottom.'</div>'
        .$upload
        .'</div>';
}

# Universal pager — works in both admin and front-end contexts
function getTplPager(array $data = []): string {
    global $db, $afile, $tpl;
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
    $prefix = (string)($data['prefix'] ?? '');
    $wparams = (array)($data['where_params'] ?? []);
    $urlx = (array)($data['url_extra'] ?? []);
    [$cnt] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT('.$field.') FROM '.PREFIX_DB.$table.($where ? ' WHERE '.$where : ''), $wparams));
    $cnt = (int)$cnt;
    if ($cnt <= $limit) return '';
    $pages = (int)ceil($cnt / $limit);
    $num = max(1, min(getVar('get', $n, 'num', 1), $pages));
    $nnum = $maxpg + 1;
    $mkurl = static function(int $i) use ($mod, $url, $n, $anchor, $afile, $urlx): string {
        if (defined('ADMIN_FILE')) return $afile.'.php?'.$url.$n.'='.$i.$anchor;
        $params = $mod ? ['name' => $mod] : [];
        if ($urlx) $params = array_merge($params, $urlx);
        $params[$n] = $i;
        return getSeoUrl($params).$anchor;
    };
    $link = static function(string $lh, string $label, bool $cur = false, bool $nav = false) use ($tpl, $targetid, $pushurl, $prefix): string {
        $opt = ['label' => $label, 'title' => $label, 'is_cur' => $cur, 'is_nav' => $nav];
        if ($targetid && !$cur && $lh !== '') {
            $opt['query'] = $lh;
            $opt['target_id'] = $targetid;
            $opt['push_url'] = $pushurl ? 'true' : 'false';
        } else {
            $opt['href'] = $lh;
        }
        return $tpl->getHtmlFrag($prefix.'pager-link', $opt);
    };
    $dots = $tpl->getHtmlFrag($prefix.'pager-dots', []);
    $prev = ($num > 1) ? $link($mkurl($num - 1), _BACK, false, true) : $link('', _BACK, true, true);
    $items = '';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === $num) {
            $items .= $link('', (string)$i, true).' ';
        } elseif ($i === 1 || $i === $pages || (($i > ($num - $maxpg)) && ($i < ($num + $maxpg)))) {
            $items .= $link($mkurl($i), (string)$i).' ';
        }
        if ($i < $pages) {
            if (($num > $nnum) && ($i === 1)) $items .= $dots;
            if (($num < ($pages - $maxpg)) && ($i === ($pages - 1))) $items .= $dots;
        }
    }
    $next = ($num < $pages) ? $link($mkurl($num + 1), _NEXT, false, true) : $link('', _NEXT, true, true);
    return $tpl->getHtmlFrag($prefix.'pager', [
        'count' => $cnt,
        'pages' => $pages,
        'limit' => $limit,
        'page' => $limit,
        'overall' => _OVERALL,
        'by' => _BY,
        'page_s' => _PAGE_S,
        'perpage' => _PERPAGE,
        'prev' => $prev,
        'items' => $items,
        'next' => $next,
    ]);
}

# Render one full admin module header with title, icon and top-level tabs
function getTplAdminTabs(array $data = []): string {
    global $afile, $conf, $tpl;
    $title = _ADMINMENU;
    $icon = 'components.png';
    $subtitle = (string)($data['subtitle_html'] ?? '');
    $name = filterWord(getVar('req', 'name', 'text', ''));
    if ($name !== '' && isset($conf['modules'][$name]) && is_array($conf['modules'][$name])) {
        $lang = trim((string)($conf['modules'][$name]['lang'] ?? ''));
        if ($lang !== '') $title = defined($lang) ? constant($lang) : $lang;
        $img = basename(trim((string)($conf['modules'][$name]['img'] ?? '')));
        if ($img !== '' && file_exists(BASE_DIR.'/templates/admin/images/admin/'.$img)) $icon = $img;
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
    return $tpl->getHtmlFrag('module-head', [
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
    $action = $data['action_url'] ?? ($afile.'.php?name='.$name.'&amp;op=info');
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
                'alert_attr' => 'data-sl-autohide="15000"',
                'is_flash' => true,
                'is_warn' => true,
                'text' => _TOKENMISS,
            ]);
            $text = (string)getVar('post', 'text', 'raw', $text);
        } else {
            $content = filterHtml(trim(getVar('post', 'text', 'raw', '')));
            if ($content !== '') {
                $dir = dirname($path);
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $fp = fopen($path, 'wb');
                if ($fp !== false) {
                    fwrite($fp, $content);
                    fclose($fp);
                    $text = $content;
                    $alert = $tpl->getHtmlFrag('alert', [
                        'alert_attr' => 'data-sl-autohide="15000"',
                        'is_flash' => true,
                        'text' => _SUCCSAVE,
                    ]);
                }
            }
        }
    }
    $info = $prs->filterContent($text, false, $mod);
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
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('form', [
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
    return $tpl->getHtmlFrag('radio-group', [
        'items_html' => $items,
    ]);
}

# Render one shared user autocomplete input with datalist-backed lookup
function getTplUserSearchInput(array $data = []): string {
    global $tpl;
    $name = $data['name'] ?? 'uname';
    $inpid = $data['input_id'] ?? $name;
    $list = $data['list_id'] ?? ($inpid.'_list');
    $endpoint = $data['endpoint'] ?? 'index.php?go=1&amp;op=getUserList';
    $mlen = (int)($data['minlength'] ?? 1);
    $tip = (string)($data['tip'] ?? '');
    $tiphtml = '';
    if ($tip !== '') {
        $tiphtml = $tpl->getHtmlFrag('title-tip', [
            'items' => [
                ['label' => _INFO, 'value' => $tip],
            ],
        ]);
    }
    return $tpl->getHtmlFrag('user-search', [
        'endpoint_attr' => $endpoint,
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
    $up = ($up && !str_contains($up, 'token=')) ? $up.'&amp;token='.getSiteToken() : $up;
    $down = ($down && !str_contains($down, 'token=')) ? $down.'&amp;token='.getSiteToken() : $down;
    return $tpl->getHtmlFrag('move-controls', [
        'down_query' => $down,
        'down_title' => _BLOCKDOWN,
        'target' => $target,
        'up_query' => $up,
        'up_title' => _BLOCKUP,
    ]);
}

# Render extra field rows for new/ form layout (fields_in() replacement for new/form-add)
function getTplFieldsIn(array $data = []): string {
    global $conf, $tpl;
    $field  = $data['field'] ?? '';
    $mod    = strtolower($data['mod'] ?? '');
    $fieldc = $conf['fields'][$mod] ?? '';
    $posted = getVar('post', 'field', 'raw', '');
    if ($posted !== '') $field = filterFields($posted);
    $fieldb = explode('|', is_string($field) ? $field : '');
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
                    $fhtml = getTplTextInput('field[]', $dval, '', 'placeholder="'.$dval.'"'.$requir);
                } elseif ($m[3] == '2') {
                    $fhtml = $tpl->getHtmlFrag('new/textarea', ['name_attr' => 'field[]', 'rows_num' => 5, 'value_text' => $fieldin, 'input_attr' => trim($requir)]);
                } elseif ($m[3] == '3') {
                    $opts = $tpl->getHtmlFrag('new/select-option', ['value_attr' => '', 'label_text' => _NO, 'is_selected' => $fieldin === '']);
                    foreach (explode(',', $m[2] ?? '') as $opt) {
                        if ($opt === '') continue;
                        $opts .= $tpl->getHtmlFrag('new/select-option', ['value_attr' => $opt, 'label_text' => $opt, 'is_selected' => $opt === $fieldin]);
                    }
                    $fhtml = $tpl->getHtmlFrag('new/select', ['name_attr' => 'field[]', 'options_html' => $opts, 'select_attr' => trim($requir)]);
                } elseif ($m[3] == '4') {
                    $fhtml = getTplAddDateTime(['name' => 'field[]', 'time' => $fieldin, 'with' => true, 'max' => 16]);
                } elseif ($m[3] == '5') {
                    $fhtml = getTplAddDateTime(['name' => 'field[]', 'time' => $fieldin, 'with' => false, 'max' => 10]);
                }
                if ($fhtml !== '') {
                    $out .= $tpl->getHtmlFrag('new/form-field-row', ['label' => getConst($m[1]), 'field_html' => $fhtml]);
                }
            }
        }
        $i++;
    }
    return $out;
}

function getTplHiddenInput(array $data = []): string {
    global $tpl;
    return $tpl->getHtmlFrag('new/hidden', [
        'name_attr'  => (string)($data['name']  ?? ''),
        'value_attr' => (string)($data['value'] ?? ''),
        'input_attr' => (string)($data['attr'] ?? ''),
    ]);
}

function getTplFormSubmit(array $data = []): string {
    global $tpl;
    $op = (string)($data['op'] ?? '');
    $label = (string)($data['label'] ?? _OK);
    $extra = (string)($data['extra'] ?? '');
    $name = (string)($data['name'] ?? '');
    $val = (string)($data['val'] ?? '');
    $select = !empty($data['select']);
    $preview = !empty($data['no_preview']);
    return $tpl->getHtmlFrag('new/form-submit', [
        'op'            => $op,
        'extra'         => $extra,
        'name'          => $name,
        'val'           => $val,
        'select'        => $select,
        'show_preview'  => $select && !$preview,
        'show_delete'   => $select && $val !== '',
        'label_preview' => _PREVIEW,
        'label_save'    => _SEND,
        'label_delete'  => _DELETE,
        'label'         => $label,
    ]);
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
        'stloc' => $stloc,
        'mod' => $mod,
        'con' => $con,
    ]);
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
    $query   = 'index.php?go='.$esc($go).'&amp;op='.$esc($op).'&amp;id='.$esc($id).'&amp;cid='.$esc($cid).'&amp;typ='.$esc($typ).'&amp;mod='.$esc($mod);
    $cerror  = addslashes((string)_CERROR1);
    return $tpl->getHtmlFrag('new/ajax-textarea-form', [
        'form_id'          => $formId,
        'textarea_html'    => $tpl->getHtmlFrag('new/textarea', [
            'name_attr'   => 'text',
            'rows_num'    => $rows,
            'value_text'  => $desc,
            'input_class' => 'sl_earea',
            'input_attr'  => 'id="'.$fieldId.'"',
        ]),
        'save_button_html' => $tpl->getHtmlFrag('new/button', [
            'button_type'  => 'submit',
            'submit_label' => _SAVE,
            'button_class' => 'sl_but_green',
            'button_attr'  => 'hx-post="'.$query.'" hx-include="#'.$formId.'" hx-target="#rep'.$obj.'" hx-swap="innerHTML" hx-push-url="false" hx-on:click="if (!document.getElementById(\''.$formId.'\').querySelector(\'[name=&quot;text&quot;]\').value.trim()) { alert(\''.$cerror.'\'); event.preventDefault(); }"',
        ]),
        'back_button_html' => $tpl->getHtmlFrag('new/button', [
            'button_type'  => 'submit',
            'submit_label' => _BACK,
            'button_class' => 'sl-but-blue',
            'button_attr'  => 'hx-get="'.$query.'" hx-target="#rep'.$obj.'" hx-swap="innerHTML" hx-push-url="false"',
        ]),
    ]);
}

# End of new, stable helper functions for building admin and frontend HTML from prepared data cuts and shared templates

# Old, deprecated functions below — will be removed in future releases
include_once 'helpers-old.php';
