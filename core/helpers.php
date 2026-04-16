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
        ]).$tpl->getHtmlFrag('input', [
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
        $opts .= $tpl->getHtmlFrag('select-option', [
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

# Render a search result title link with highlighted text and new-badge
function getTplSearchResultTitle(string $url, string $title, string $word, string $time): string {
    global $tpl;
    return $tpl->getHtmlFrag('search-result-title', [
        'url' => $url,
        'title' => $title,
        'highlighted_title' => filterTextHighlight($title, $word),
        'new_badge' => getTplNewGraphic($time),
    ]);
}

# Render one money calculator form with a JS function name, to-currency label and currency code
function getTplMoneyCalcForm(string $fnname, string $tolbl, string $tocur): string {
    global $tpl;
    return $tpl->getHtmlFrag('money-calculator-form', [
        'btn_label' => _MO_4,
        'fn_name' => $fnname,
        'from_label' => _MO_2,
        'to_cur' => $tocur,
        'to_label' => $tolbl,
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
                'label_text' => getConst($out[1]),
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
    $ta = $tpl->getHtmlFrag('textarea', [
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
    $fonts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _FONT, 'is_selected' => false]);
    foreach (['Arial', 'Courier', 'Mistral', 'Impact', 'Sans Serif', 'Tahoma', 'Helvetica', 'Verdana'] as $f) {
        $fonts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $f, 'label_text' => $f, 'is_selected' => false]);
    }
    $colors = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _ECOLOR, 'is_selected' => false]);
    foreach (['black', 'gray', 'silver', 'white', 'maroon', 'red', 'orangered', 'orange', 'yellow', 'purple', 'fuchsia', 'violet', 'darkgreen', 'green', 'lime', 'navy', 'blue', 'teal', 'aqua'] as $c) {
        $colors .= $tpl->getHtmlFrag('select-option', ['value_attr' => $c, 'label_text' => $c, 'is_selected' => false]);
    }
    $fsizes = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _ESIZE, 'is_selected' => false]);
    foreach (['8', '10', '12', '14', '16', '18', '20', '22', '24', '26', '28', '30', '32'] as $fs) {
        $fsizes .= $tpl->getHtmlFrag('select-option', ['value_attr' => $fs, 'label_text' => $fs, 'is_selected' => false]);
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
    $fcodes = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _CODE, 'is_selected' => false]);
    foreach (['Bash', 'Cpp', 'CSharp', 'Css', 'Delphi', 'Diff', 'Groovy', 'Java', 'JScript', 'Php', 'Plain', 'Python', 'Ruby', 'Scala', 'Sql', 'Vb', 'Xml'] as $fc) {
        $fcodes .= $tpl->getHtmlFrag('select-option', ['value_attr' => strtolower($fc), 'label_text' => $fc, 'is_selected' => false]);
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
        $tiphtml = getTplTitleTip([['label' => _INFO, 'value' => $tip]]);
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
                    $fhtml = getTplTextInput('field[]', $dval, '', 'placeholder="'.$dval.'"'.$requir);
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

function getTplHiddenInput(array $data = []): string {
    global $tpl;
    return $tpl->getHtmlFrag('hidden', [
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
    return $tpl->getHtmlFrag('form-submit', [
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
    return $tpl->getHtmlFrag('ajax-textarea-form', [
        'form_id'          => $formId,
        'textarea_html'    => $tpl->getHtmlFrag('textarea', [
            'name_attr'   => 'text',
            'rows_num'    => $rows,
            'value_text'  => $desc,
            'input_class' => 'sl_earea',
            'input_attr'  => 'id="'.$fieldId.'"',
        ]),
        'save_button_html' => $tpl->getHtmlFrag('button', [
            'button_type'  => 'submit',
            'submit_label' => _SAVE,
            'button_class' => 'sl_but_green',
            'button_attr'  => 'hx-post="'.$query.'" hx-include="#'.$formId.'" hx-target="#rep'.$obj.'" hx-swap="innerHTML" hx-push-url="false" hx-on:click="if (!document.getElementById(\''.$formId.'\').querySelector(\'[name=&quot;text&quot;]\').value.trim()) { alert(\''.$cerror.'\'); event.preventDefault(); }"',
        ]),
        'back_button_html' => $tpl->getHtmlFrag('button', [
            'button_type'  => 'submit',
            'submit_label' => _BACK,
            'button_class' => 'sl-but-blue',
            'button_attr'  => 'hx-get="'.$query.'" hx-target="#rep'.$obj.'" hx-swap="innerHTML" hx-push-url="false"',
        ]),
    ]);
}

# Render the shared "new" badge for fresh content
function getTplNewGraphic(string $time): string {
    global $tpl;
    $data = time() - strtotime($time);
    $cls = '';
    $ttl = '';
    if ($data < 86400) { $cls = 'sl_n_day'; $ttl = (string)_NEWTODAY; }
    elseif ($data < 259200) { $cls = 'sl_n_days'; $ttl = (string)_NEWLAST3DAYS; }
    elseif ($data < 604800) { $cls = 'sl_n_week'; $ttl = (string)_NEWTHISWEEK; }
    if (!$cls) return '';
    return $tpl->getHtmlFrag('graphic', ['icon_class' => $cls, 'icon_title' => $ttl]);
}

# Render a yes/no radio control pair
function getTplRadioForm(mixed $var, string $name, string $id = ''): string {
    global $tpl;
    $state = ($var === 0 || $var === '0') ? '0' : (($var === 1 || $var === '1') ? '1' : '');
    if ($id == '1') {
        return $tpl->getHtmlFrag('radio-option', ['name_attr' => $name, 'value_attr' => '0', 'label_text' => _YES, 'is_checked' => $state !== '1'])
            .$tpl->getHtmlFrag('radio-option', ['name_attr' => $name, 'value_attr' => '1', 'label_text' => _NO, 'is_checked' => $state === '1']);
    }
    return $tpl->getHtmlFrag('radio-option', ['name_attr' => $name, 'value_attr' => '1', 'label_text' => _YES, 'is_checked' => $state !== '0'])
        .$tpl->getHtmlFrag('radio-option', ['name_attr' => $name, 'value_attr' => '0', 'label_text' => _NO, 'is_checked' => $state === '0']);
}

# Render a gender select control
function getTplGenderSelect(string $name, int $typ, string $clas = ''): string {
    global $tpl;
    $list = [_NO_INFO, _MAN, _WOMAN];
    $cont = '';
    foreach ($list as $key => $val) {
        $cont .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$key,
            'label_text' => $val,
            'is_selected' => $key == $typ,
        ]);
    }
    return $tpl->getHtmlFrag('select', ['name_attr' => $name, 'select_class' => $clas, 'options_html' => $cont]);
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
    if (!is_array($data)) $data = [['value' => (string)$data]];
    $last = count($data) - 1;
    $cont = '';
    foreach ($data as $idx => $item) {
        $cont .= $tpl->getHtmlFrag('title-tip-item', [
            'label' => (string)($item['label'] ?? _INFO),
            'value' => (string)($item['value'] ?? ''),
            'is_last' => $idx === $last,
        ]);
    }
    return $tpl->getHtmlFrag('title-tip', ['content' => $cont]);
}

# Render preview rows for dynamic fields in the legacy inline format
function getTplFieldsOut(mixed $fieldb, string $mod): string {
    global $conf;
    $mod = strtolower($mod);
    if (!$fieldb || !$mod) return '';
    $fieldc = explode('||', $conf['fields'][$mod] ?? '');
    $fieldb = explode('|', (string)$fieldb);
    $i = 0;
    $cont = '';
    foreach ($fieldc as $val) {
        if ($val != '' && !empty($fieldb[$i])) {
            preg_match('#(.*)\|(.*)\|(.*)\|(.*)#i', $val, $out);
            $cont .= getConst($out[1]).': '.$fieldb[$i].'<br>';
        }
        $i++;
    }
    return $cont;
}

# Render the shared ajax rating block
function getRatingAsync(mixed $typ, mixed $id, mixed $mod, mixed $rat, mixed $scor, string $obj = '', string $stl = ''): string {
    global $conf;
    if (intval($rat)) {
        $votnum = $rat;
        $votes = $rat;
    } else {
        $votnum = 0;
        $votes = 1;
    }
    $width = number_format($scor / $votes, 2) * 20;
    $result = substr($scor / $votes, 0, 4);
    if (intval($votes) && intval($scor)) {
        $title = _RATING.': '.$result.'/'.$votes.' '._AVERAGESCORE.': '.$result;
        $nrate = 'sl_rate-num sl_rate-is';
    } else {
        $title = _RATING.': 0/0 '._AVERAGESCORE.': 0';
        $nrate = 'sl_rate-num';
    }
    if ($stl == '1') {
        $img = getTplRatingLike($result, $title, $nrate);
        $imgr = getTplRatingHover($result, $title, $nrate, $id.$obj, 'go=1&amp;op=getRatingView&amp;id='.$id.'&amp;typ='.$obj.'&amp;mod='.$mod.'&amp;stl=1');
        $crate = 'sl_rate-like';
    } else {
        $img = getTplRatingBar($result, $title, $nrate, (string)$width, $votnum);
        $imgr = getTplRatingBarHover($result, $title, $nrate, (string)$width, $votnum, $id.$obj, 'go=1&amp;op=getRatingView&amp;id='.$id.'&amp;typ='.$obj.'&amp;mod='.$mod);
        $crate = 'sl_rate';
    }
    if ($typ == 2) return getTplRatingWrap($crate, $img);
    $con = explode('|', $conf['ratings'][strtolower((string)$mod)] ?? '');
    if ((($con[1] ?? '') && $id && $mod) || ($rat && $scor)) {
        return ((($con[1] ?? '') && $typ) || (($con[1] ?? '') && !($con[2] ?? '') && !$typ)) ? getTplRatingWrap($crate, $imgr, 'rep'.$id.$obj) : getTplRatingWrap($crate, $img);
    }
    return '';
}

# Render the editor file preview block
function getTplEditorPreview(int $index, string $url, string $fallback, bool $show): string {
    global $tpl;
    return $tpl->getHtmlFrag('image-preview', [
        'preview_id' => 'sf-form-'.$index,
        'toggle_onclick' => "HideShow('sf-form-".$index."', 'fold', 'up', 500);",
        'image_url' => $url,
        'fallback_url' => $fallback,
        'image_title' => _IMG,
        'no_title' => _NO,
        'show_toggle' => $show,
        'show_fallback' => !$show,
    ]);
}

# Render one editor async action link
function getEditorAsyncAction(string $target, string $query, string $title, string $label): string {
    return getTplAjaxAction($target, $query, $title, $label);
}

# Render one editor insert action button
function getTplEditorInsert(string $cmd, string $valu, string $id, string $title, string $label): string {
    global $tpl;
    return $tpl->getHtmlFrag('editor-action-insert', [
        'command' => $cmd,
        'value' => $valu,
        'editor_id' => $id,
        'title' => $title,
        'label' => $label,
    ]);
}

# Render the editor row action menu
function getTplEditorMenu(array $list): string {
    global $tpl;
    $list = array_values(array_filter($list, static fn($item) => $item !== ''));
    if (!$list) return '';
    return $tpl->getHtmlFrag('row-actions', [
        'trigger_label' => _EDITOR,
        'items_html' => implode('', array_map(fn($item) => $tpl->getHtmlFrag('action-menu-item', ['item_html' => $item]), $list)),
    ]);
}

# Render one editor files table row
function getTplEditorRow(array $data): string {
    global $tpl;
    return $tpl->getHtmlFrag('editor-file-row', [
        'preview_html' => (string)($data['preview_html'] ?? ''),
        'file_name' => (string)($data['file_name'] ?? ''),
        'size_value' => (string)($data['size_value'] ?? ''),
        'functions_html' => (string)($data['functions_html'] ?? ''),
    ]);
}

# Render the editor files table shell
function getTplEditorTable(string $rows): string {
    global $tpl;
    $head = $tpl->getHtmlFrag('table', ['open' => true, 'col_id' => ' ', 'col_title' => _FILE, 'col_poster' => _SIZE, 'col_func' => _FUNCTIONS]);
    $foot = $tpl->getHtmlFrag('table', []);
    return $head.$rows.$foot;
}

# Render a comment action link
function getTplCommentLink(string $href, string $title, string $label, string $clas = '', string $target = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('comment-action-link', [
        'href' => $href,
        'title' => $title,
        'label' => $label,
        'class' => $clas,
        'target' => $target,
    ]);
}

# Render one async comment action link
function getCommentAsyncAction(string $target, string $query, string $title, string $label, string $clas = ''): string {
    return getTplAjaxAction($target, $query, $title, $label, $clas);
}

# Render a comment javascript action link
function getTplCommentJs(string $href, string $title, string $label, string $clas = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('comment-action-link', [
        'href' => $href,
        'title' => $title,
        'label' => $label,
        'class' => $clas,
        'target' => '',
    ]);
}

# Render a comment delete action link
function getTplCommentDelete(string $href, string $text, string $title, string $label): string {
    global $tpl;
    return $tpl->getHtmlFrag('action-delete', [
        'href' => $href,
        'confirm_text' => $text,
        'title' => $title,
        'label' => $label,
    ]);
}

# Render the comment row action menu
function getTplCommentMenu(array $list): string {
    global $tpl;
    $list = array_values(array_filter($list, static fn($item) => $item !== ''));
    if (!$list) return '';
    return $tpl->getHtmlFrag('row-actions', [
        'trigger_label' => _EDITOR,
        'items_html' => implode('', array_map(fn($item) => $tpl->getHtmlFrag('action-menu-item', ['item_html' => $item]), $list)),
    ]);
}

# Render one comment meta text item
function getTplCommentMeta(string $label, string $valu): string {
    return htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').': '.htmlspecialchars($valu, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

# Render one colored comment meta item
function getTplCommentColor(string $label, string $valu, string $color): string {
    global $tpl;
    return $tpl->getHtmlFrag('comment-meta-color', ['label' => $label, 'value' => $valu, 'color' => $color]);
}

# Render one comment avatar block
function getTplCommentAvatar(string $name, string $avatar): string {
    global $tpl;
    return $tpl->getHtmlFrag('comment-avatar', ['username' => $name, 'avatar' => $avatar]);
}

# Render one comment rank image
function getTplCommentRank(string $src, string $title): string {
    global $tpl;
    return $tpl->getHtmlFrag('comment-rank-image', ['src' => $src, 'title' => $title]);
}

# Render one comment signature block
function getTplCommentSign(string $cont): string {
    global $tpl;
    return $tpl->getHtmlFrag('comment-signature', ['content' => $cont]);
}

# Render one alpha navigation link
function getTplAlphaLink(string $href, string $title, string $label): string {
    global $tpl;
    return $tpl->getHtmlFrag('alpha-nav-link', ['href' => $href, 'title' => $title, 'label' => $label]);
}

# Render one alpha navigation text item
function getTplAlphaText(string $label): string {
    global $tpl;
    return $tpl->getHtmlFrag('alpha-nav-text', ['label' => $label]);
}

# Render one tabs navigation link
function getTplTabLink(string $href, string $label): string {
    global $tpl;
    $lhtml = preg_match('/<[^>]+>/', $label) ? $label : htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return $tpl->getHtmlFrag('navi-tab-link', ['href' => $href, 'label_html' => $lhtml]);
}

# Render one tabs content item
function getTplTabContent(string $id, string $cont): string {
    global $tpl;
    return $tpl->getHtmlFrag('navi-tab-content', ['tab_id' => $id, 'content' => $cont]);
}

# Render the tabs wrapper
function getTplTabWrap(string $tabs, string $cont, int $id): string {
    global $tpl;
    return $tpl->getHtmlFrag('navi-tabs-wrap', ['tabs_html' => $tabs, 'content_html' => $cont, 'id' => $id]);
}

# Render a pager link
function getTplPagerLink(string $href, string $title, string $label, string $clas = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('pager-link', [
        'href' => $href,
        'title' => $title,
        'label' => $label,
        'is_nav' => $clas !== '',
    ]);
}

# Render the current pager marker
function getTplPagerCurrent(string $title, string $label, string $clas = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('pager-link', [
        'title' => $title,
        'label' => $label,
        'is_cur' => true,
        'is_nav' => $clas !== '',
    ]);
}

# Render pager dots
function getTplPagerDots(): string {
    global $tpl;
    return $tpl->getHtmlFrag('pager-dots', []);
}

# Render an async pager link
function getAsyncPagerLink(string $loadid, string $targetid, string $query, string $title, string $label, string $clas = ''): string {
    global $tpl;
    $route = $query ? 'index.php?'.$query : '';
    return $tpl->getHtmlFrag('pager-link', [
        'query' => $route,
        'target_id' => $targetid,
        'title' => $title,
        'label' => $label,
        'is_nav' => $clas !== '',
    ]);
}

# Render one category icon link
function getTplCatIcon(string $href, string $title, string $src = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('category-icon', ['href' => $href, 'title' => $title, 'src' => $src]);
}

# Render one category icon text item
function getTplCatIconText(string $title, string $src = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('category-icon', ['href' => '', 'title' => $title, 'src' => $src]);
}

# Render one category title link
function getTplCatTitle(string $href, string $title): string {
    global $tpl;
    return $tpl->getHtmlFrag('category-title', ['href' => $href, 'title' => $title]);
}

# Render one category text link
function getTplCatText(string $href, string $title): string {
    global $tpl;
    return $tpl->getHtmlFrag('category-link', ['href' => $href, 'title' => $title, 'text' => $title]);
}

# Render one plain category title item
function getTplCatTitleText(string $title): string {
    global $tpl;
    return $tpl->getHtmlFrag('category-title', ['href' => '', 'title' => $title]);
}

# Render one category sub-item
function getTplCatSubitem(string $cont): string {
    global $tpl;
    return $tpl->getHtmlFrag('category-sub-item', ['content' => $cont]);
}

# Render one category row
function getTplCatRow(string $img, string $title, string $desc = '', string $subs = '', string $style = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('category-row', [
        'image_html' => $img,
        'title_html' => $title,
        'description_html' => $desc,
        'subitems_html' => $subs,
        'style' => $style,
    ]);
}

# Render the category select shell
function getTplCatPicker(string $name, string $clas, string $title, string $opts): string {
    global $tpl;
    return $tpl->getHtmlFrag('category-select', [
        'select_name' => $name,
        'class' => $clas,
        'title' => $title,
        'options_html' => $opts,
    ]);
}

# Render one category select option
function getTplCatOption(string $valu, string $label, bool $isel = false): string {
    global $tpl;
    return $tpl->getHtmlFrag('select-option', [
        'value_attr' => $valu,
        'label_text' => $label,
        'is_selected' => $isel,
    ]);
}

# Render one breadcrumb link
function getTplBreadLink(string $href, string $title, string $label): string {
    global $tpl;
    return $tpl->getHtmlFrag('link', ['href' => $href, 'title' => $title, 'label_html' => $label]);
}

# Render one voting action link
function getTplVotingLink(string $href, string $title, string $label, string $clas = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('comment-action-link', [
        'href' => $href,
        'title' => $title,
        'label' => $label,
        'class' => $clas,
        'target' => '',
    ]);
}

# Render one async voting action link
function getVotingAsyncAction(string $target, string $query, string $title, string $label, string $clas = '', string $text = ''): string {
    return getTplAjaxAction($target, $query, $title, $label, $clas);
}

# Render one voting delete action link
function getTplVotingDelete(string $href, string $text, string $title, string $label): string {
    global $tpl;
    return $tpl->getHtmlFrag('action-delete', [
        'href' => $href,
        'confirm_text' => $text,
        'title' => $title,
        'label' => $label,
    ]);
}

# Render the voting row action menu
function getTplVotingMenu(array $list): string {
    global $tpl;
    $list = array_values(array_filter($list, static fn($item) => $item !== ''));
    if (!$list) return '';
    return $tpl->getHtmlFrag('row-actions', [
        'trigger_label' => _EDITOR,
        'items_html' => implode('', array_map(fn($item) => $tpl->getHtmlFrag('action-menu-item', ['item_html' => $item]), $list)),
    ]);
}

# Render a rating like view
function getTplRatingLike(string $result, string $title, string $nrate): string {
    global $tpl;
    return $tpl->getHtmlFrag('rating-like', [
        'result' => $result,
        'title' => $title,
        'nrate' => $nrate,
        'rate1_title' => _RATE1,
        'rate5_title' => _RATE5,
        'hover_query' => '',
        'target_id' => '',
    ]);
}

# Render a rating like hover view
function getTplRatingHover(string $result, string $title, string $nrate, string $target, string $query): string {
    global $tpl;
    return $tpl->getHtmlFrag('rating-like-live', [
        'result' => $result,
        'title' => $title,
        'nrate' => $nrate,
        'target_id' => $target,
        'rate1_query' => $query.'&amp;rate=1',
        'rate5_query' => $query.'&amp;rate=5',
        'rate1_title' => _RATE1,
        'rate5_title' => _RATE5,
    ]);
}

# Render a rating bar view
function getTplRatingBar(string $result, string $title, string $nrate, string $width, int|string $votes): string {
    global $tpl;
    return $tpl->getHtmlFrag('rating-bar', [
        'result' => $result,
        'title' => $title,
        'nrate' => $nrate,
        'width' => $width,
        'votes' => (string)$votes,
        'votes_title' => _VOTES,
        'hover_query' => '',
        'target_id' => '',
    ]);
}

# Render a rating bar hover view
function getTplRatingBarHover(string $result, string $title, string $nrate, string $width, int|string $votes, string $target, string $query): string {
    global $tpl;
    return $tpl->getHtmlFrag('rating-bar', [
        'result' => $result,
        'title' => $title,
        'nrate' => $nrate,
        'width' => $width,
        'votes' => (string)$votes,
        'votes_title' => _VOTES,
        'target_id' => $target,
        'hover_query' => $query,
    ]);
}

# Render the rating wrapper
function getTplRatingWrap(string $clas, string $cont, string $id = ''): string {
    global $tpl;
    return $tpl->getHtmlFrag('rating-wrap', ['wrap_class' => $clas, 'wrap_id' => $id, 'content' => $cont]);
}

# Render a live rating like block
function getTplRatingLive(string $result, string $title, string $nrate, string $target, string $qone, string $qfive): string {
    global $tpl;
    return $tpl->getHtmlFrag('rating-like-live', [
        'result' => $result,
        'title' => $title,
        'nrate' => $nrate,
        'target_id' => $target,
        'rate1_query' => $qone,
        'rate5_query' => $qfive,
        'rate1_title' => _RATE1,
        'rate5_title' => _RATE5,
    ]);
}

# Render a live rating stars block
function getTplRatingStars(string $width, string $target, string $query, string $nrate, int|string $votes): string {
    global $tpl;
    return $tpl->getHtmlFrag('rating-bar-live', [
        'width' => $width,
        'target_id' => $target,
        'hover_query' => $query,
        'nrate' => $nrate,
        'votes' => (string)$votes,
        'votes_title' => _VOTES,
    ]);
}

# Render the shared category select from database categories
function getTplCategorySelect(string $mod = '', int $id = 0, string $name = '', string $clas = '', string $empty = '', string $raw = ''): string {
    global $db, $conf;
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
        while ([$cid, $title, $parent, $pview] = $db->getSqlRow($res)) {
            if (is_acess($pview)) $mass[$cid] = [getConst($title), $parent];
        }
        foreach ($mass as $key => $val) {
            $cont[$key] = $val[0];
            $flag = $val[1];
            while ($flag != 0) {
                $cont[$key] = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'.$cont[$key];
                $flag = intval($mass[$flag][1]);
            }
            $opts .= getTplCatOption((string)$key, $cont[$key], $id == $key);
        }
        return !$raw ? getTplCatPicker($name, $clas, _CATEGORIES, $opts) : $opts;
    }
    if ($empty) return getTplCatPicker($name, $clas, _CATEGORIES, $empty);
    return '';
}

# Render the shared category breadcrumb trail
function getTplCategoryTrail(string $mod = '', int $id = 0, string $sep = '', string $home = ''): string {
    global $db, $conf;
    $mod = filterVar($mod);
    $sep = $sep ? ' '.urldecode($sep).' ' : ' '.urldecode($conf['defis']).' ';
    $cont = $home ? getTplBreadLink('index.php?name='.$conf['name'], $home, $home).$sep : '';
    if ($mod) {
        $where = 'WHERE modul = :modul';
        $pars = ['modul' => $mod];
    } else {
        $where = '';
        $pars = [];
    }
    $res = $db->getSqlQuery('SELECT id, title, parent FROM '.PREFIX_DB.'_categories '.$where, $pars);
    if ($db->getSqlRowCount($res) > 0) {
        $mass = [];
        while ([$cid, $title, $parent] = $db->getSqlRow($res)) $mass[$cid] = [getConst($title), $parent];
        foreach ($mass as $key => $val) {
            $flag = $val[1];
            $path[$key] = ($flag != 0) ? $val[0] : getTplBreadLink('index.php?name='.$conf['name'].'&amp;cat='.$key, $val[0], $val[0]);
            while ($flag != 0) {
                $path[$key] = getTplBreadLink('index.php?name='.$conf['name'].'&amp;cat='.$flag, $mass[$flag][0], $mass[$flag][0]).$sep
                    .getTplBreadLink('index.php?name='.$conf['name'].'&amp;cat='.$key, $val[0], $path[$key]);
                $flag = intval($mass[$flag][1]);
            }
            if ($id == $key) $cont .= $path[$key];
        }
    }
    return $cont;
}

# Render language options from installed language files
function getTplLanguageOptions(string $lang = '', string $typ = ''): string {
    global $tpl;
    $dir = opendir('lang');
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
function getTplModuleSelect(string $name, string $clas, string $mod, string $no = '', array $allow = []): string {
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
    return $tpl->getHtmlFrag('multi-select', ['name_attr' => $name, 'select_class' => $clas, 'options_html' => $cont]);
}

# Render a select with category-enabled modules
function getTplCategoryModule(string $name, string $clas = '', string $sel = '', bool $auto = false): string {
    global $tpl;
    $attr = $auto ? 'OnChange="submit()"' : '';
    $cont = '';
    $mods = ['faq', 'files', 'forum', 'help', 'jokes', 'links', 'media', 'news', 'pages', 'shop'];
    foreach ($mods as $mod) {
        $cont .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $mod,
            'label_text' => getModuleName($mod).' - '.$mod,
            'is_selected' => $sel == $mod,
        ]);
    }
    return $tpl->getHtmlFrag('select', ['name_attr' => $name, 'select_class' => $clas, 'select_attr' => $attr, 'options_html' => $cont]);
}

# Render a mailto link
function getMailLink(string $mail): string {
    global $conf;
    return '<a href="mailto:'.$mail.'?subject='.$conf['sitename'].'" target="_blank">'.$mail.'</a>';
}

# End of new, stable helper functions for building admin and frontend HTML from prepared data cuts and shared templates

# Old, deprecated functions below — will be removed in future releases
include_once 'helpers-old.php';
