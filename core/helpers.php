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
                $safe = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $opts .= '<option value="'.$safe.'"'.(($name == $text) ? ' selected' : '').'>'.$safe.'</option>';
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
    return '<input type="hidden" name="'.htmlspecialchars((string)$name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" value="'.htmlspecialchars((string)$time, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" id="'.$hid.'">'
        .$tpl->getHtmlFrag('input', [
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
        $opts .= '<option value="'.htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"'.(($valu === $value) ? ' selected' : '').'>'.htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</option>';
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
function getTplBbEditor(array $opt = []): string {
    global $conf, $user, $op;
    $id = (string)($opt['id'] ?? '1');
    $name = $opt['name'] ?? '';
    $value = replace_break($opt['value'] ?? '');
    $rows = (int)($opt['rows'] ?? 25);
    $style = $opt['style'] ?? '';
    $placeholder = $opt['placeholder'] ?? '';
    $required = $opt['required'] ?? '';
    $stloc = $opt['stloc'] ?? substr(_LOCALE, 0, 2);
    $mod = $opt['mod'] ?? '';
    $con = $opt['con'] ?? [];
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
    $ta = '<textarea id="'.$e($id).'" name="'.$e($name).'" cols="65" rows="'.$rows.'"'
        .' OnKeyPress="TransliteFeld(this, event)"'
        .' OnSelect="FieldName(this, \''.$e($id).'\')"'
        .' OnClick="FieldName(this, \''.$e($id).'\')"'
        .' OnKeyUp="FieldName(this, \''.$e($id).'\')"'
        .' class="sl_field'.$style.'"'.$placeholder.$required.'>'.$value.'</textarea>';

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
    $fonts = '<option value="">'._FONT.'</option>';
    foreach (['Arial', 'Courier', 'Mistral', 'Impact', 'Sans Serif', 'Tahoma', 'Helvetica', 'Verdana'] as $f) {
        $fonts .= '<option style="font-family: '.$f.';" value="'.$f.'">'.$f.'</option>';
    }
    $colors = '<option value="">'._ECOLOR.'</option>';
    foreach (['black', 'gray', 'silver', 'white', 'maroon', 'red', 'orangered', 'orange', 'yellow', 'purple', 'fuchsia', 'violet', 'darkgreen', 'green', 'lime', 'navy', 'blue', 'teal', 'aqua'] as $c) {
        $colors .= '<option style="background: '.$c.';" value="'.$c.'">'.$c.'</option>';
    }
    $fsizes = '<option value="">'._ESIZE.'</option>';
    foreach (['8', '10', '12', '14', '16', '18', '20', '22', '24', '26', '28', '30', '32'] as $fs) {
        $fsizes .= '<option value="'.$fs.'">'.$fs.'</option>';
    }
    $ei = $e($id);
    $bottom .= $drop(
        $btn("HideShow('t-form-".$id."', 'blind', 'up', 500);", 'sl_bb_text', _TEXT),
        '<div id="t-form-'.$ei.'" class="sl_drop-form"><ul>'
        .'<li><select name="family" OnChange="InsertCode(\'family\', this.options[this.selectedIndex].value, \'\', \'\', \''.$ei.'\'); this.selectedIndex=0;" class="sl_field" multiple>'.$fonts.'</select></li>'
        .'<li><select name="color" OnChange="InsertCode(\'color\', this.options[this.selectedIndex].value, \'\', \'\', \''.$ei.'\'); this.selectedIndex=0;" class="sl_field" multiple>'.$colors.'</select></li>'
        .'<li><select name="size" OnChange="InsertCode(\'size\', this.options[this.selectedIndex].value, \'\', \'\', \''.$ei.'\'); this.selectedIndex=0;" class="sl_field" multiple>'.$fsizes.'</select></li>'
        .'</ul></div>'
    );

    # Code syntax panel
    $fcodes = '<option value="">'._CODE.'</option>';
    foreach (['Bash', 'Cpp', 'CSharp', 'Css', 'Delphi', 'Diff', 'Groovy', 'Java', 'JScript', 'Php', 'Plain', 'Python', 'Ruby', 'Scala', 'Sql', 'Vb', 'Xml'] as $fc) {
        $fcodes .= '<option value="'.strtolower($fc).'">'.$fc.'</option>';
    }
    $bottom .= $drop(
        $btn("HideShow('c-form-".$id."', 'blind', 'up', 500);", 'sl_bb_code', _CODE),
        '<div id="c-form-'.$ei.'" class="sl_drop-form"><ul>'
        .'<li><select name="code" OnChange="InsertCode(\'code\', this.options[this.selectedIndex].value, \'\', \'\', \''.$ei.'\'); this.selectedIndex=0;" class="sl_field" multiple>'.$fcodes.'</select></li>'
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
                .'<input type="hidden" name="upload_token" value="'.$tok.'">'
                .'<input type="file" id="file_upload" name="file[]" multiple="multiple" class="sl_field">'
                .'</form>'
                .'<input type="button" value="'._UPDATE.'" OnClick="htmx.ajax(&quot;GET&quot;, &quot;index.php?go=1&op=getEditorFiles&id='.$ei.'&dir='.$e($mod).'&quot;, {target:&quot;#repf'.$ei.'&quot;, swap:&quot;innerHTML&quot;}); return false;" class="sl_but_green">'
                .'</div>';
        } else {
            $inner = '<div class="sl_pos_center"><input type="button" value="'._UPDATE.'"'
                .' OnClick="htmx.ajax(&quot;GET&quot;, &quot;index.php?go=1&op=getEditorFiles&id='.$ei.'&dir='.$e($mod).'&quot;, {target:&quot;#repf'.$ei.'&quot;, swap:&quot;innerHTML&quot;}); return false;" class="sl_but_green"></div>';
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
function getTplPager(array $opt): string {
    global $db, $afile, $tpl;
    $limit  = (int)($opt['limit'] ?? 10);
    $maxpg  = (int)($opt['maxpg'] ?? 10);
    $table  = $opt['table'] ?? '';
    $field  = $opt['field'] ?? 'id';
    $where  = $opt['where'] ?? '';
    $mod    = $opt['mod'] ?? '';
    $anchor = $opt['anchor'] ?? '';
    $n      = $opt['n'] ?? 'num';
    $url    = html_entity_decode($opt['url'] ?? '', ENT_QUOTES, 'UTF-8');
    $targetid = (string)($opt['target_id'] ?? '');
    $pushurl = !empty($opt['push_url']);
    $prefix = (string)($opt['prefix'] ?? '');
    [$cnt]  = $db->getSqlRow($db->getSqlQuery('SELECT COUNT('.$field.') FROM '.PREFIX_DB.$table.($where ? ' WHERE '.$where : '')));
    $cnt    = (int)$cnt;
    if ($cnt <= $limit) return '';
    $pages  = (int)ceil($cnt / $limit);
    $num    = max(1, min(getVar('get', $n, 'num', 1), $pages));
    $nnum   = $maxpg + 1;
    $mkurl  = static function(int $i) use ($mod, $url, $n, $anchor, $afile): string {
        if (defined('ADMIN_FILE')) return $afile.'.php?'.$url.$n.'='.$i.$anchor;
        return getSeoUrl($mod ? ['name' => $mod, $url.$n => $i] : [$url.$n => $i]).$anchor;
    };
    $link   = static function(string $lh, string $label, bool $cur = false, bool $nav = false) use ($tpl, $targetid, $pushurl, $prefix): string {
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
    $dots   = $tpl->getHtmlFrag($prefix.'pager-dots', []);
    $prev   = ($num > 1) ? $link($mkurl($num - 1), _BACK, false, true) : $link('', _BACK, true, true);
    $items  = '';
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
    $next   = ($num < $pages) ? $link($mkurl($num + 1), _NEXT, false, true) : $link('', _NEXT, true, true);
    return $tpl->getHtmlFrag($prefix.'pager', [
        'count'   => $cnt,
        'pages'   => $pages,
        'limit'   => $limit,
        'page'    => $limit,
        'overall' => _OVERALL,
        'by'      => _BY,
        'page_s'  => _PAGE_S,
        'perpage' => _PERPAGE,
        'prev'    => $prev,
        'items'   => $items,
        'next'    => $next,
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
            'field_html' => textarea('1', 'text', $text, $mod, '25'),
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
    $minlength = (int)($data['minlength'] ?? 1);
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
        'minlength_num' => $minlength,
        'name_attr' => $name,
        'tip_html' => $tiphtml,
        'token_attr' => $data['token'] ?? getSiteToken(),
        'value_attr' => (string)($data['value'] ?? ''),
    ]);
}


# Render one shared admin move-controls block with HTMX transport
function getTplMoveControls(string $target, string $up = '', string $down = ''): string {
    global $tpl;
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

# End of new, stable helper functions for building admin and frontend HTML from prepared data cuts and shared templates

# Old, deprecated functions below — will be removed in future releases
include_once 'helpers-old.php';
