<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');
require_once CONFIG_DIR.'/lang.php';

function lang_navi(int $tab = 0, int $subtab = 0): string {
    panel();
    $ops = ['lang_main', 'lang_conf', 'lang_info'];
    $lang = [_HOME, _PREFERENCES, _INFO];
    return getAdminTabs(_LANG_EDIT, 'lang.png', '', $ops, $lang, [], [], $tab, $subtab);
}

function getLangPath(string $mod = '', string $typ = ''): string {
    $base = BASE_DIR;
    $module = $mod ? '/modules/'.$mod : '';
    $type = $typ ? '/'.$typ : '';
    return $base.$module.$type.'/language';
}

function lang_main(): void {
    global $prefix, $db, $admin_file;
    $modbase = [];
    $who_view = [];
    $result = $db->sql_query('SELECT title, active, view FROM '.$prefix.'_modules ORDER BY title ASC');
    while (list($ttl, $act, $view) = $db->sql_fetchrow($result)) {
        $modbase[$ttl] = $act;
        if ($view == 0) {
            $who_view[] = _MVALL;
        } elseif ($view == 1) {
            $who_view[] = _MVUSERS;
        } elseif ($view == 2) {
            $who_view[] = _MVADMIN;
        }
    }

    head();
    $cont = lang_navi(0, 0);
    $cont .= setTemplateBasic('open');
    $cont .= '<table class="sl_table_list_sort"><thead><tr>'
        .'<th>'._ID.'</th>'
        .'<th>'._NAME.'</th>'
        .'<th>'._MODUL.'</th>'
        .'<th>'._VIEW.'</th>'
        .'<th class="{sorter: false}">'._STATUS.'</th>'
        .'<th class="{sorter: false}">'._FUNCTIONS.'</th>'
    .'</tr></thead><tbody>';

    $sys_admin = '<a href="'.$admin_file.'.php?op=lang_file&amp;typ=admin" title="'._FULLEDIT.'">'._ADMIN.'</a>';
    $sys_modul = '<a href="'.$admin_file.'.php?op=lang_file" title="'._FULLEDIT.'">'._MODUL.'</a>';
    $cont .= '<tr>'
        .'<td>1</td>'
        .'<td>'._SYSTEM.'</td>'
        .'<td>'._ALL.'</td>'
        .'<td>'._MVALL.'</td>'
        .'<td>'.ad_status('', 1).'</td>'
        .'<td>'.add_menu($sys_admin.'||'.$sys_modul).'</td>'
    .'</tr>';

    $mod = [];
    $files = scandir(BASE_DIR.'/modules');
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && is_file(BASE_DIR.'/modules/'.$file.'/index.php')) $mod[] = $file;
    }
    sort($mod);
    $ci = count($mod);
    for ($i = 0; $i < $ci; $i++) {
        $a = $i + 2;
        $act = isset($modbase[$mod[$i]]) && $modbase[$mod[$i]] ? 1 : 0;
        $view = isset($who_view[$i]) ? $who_view[$i] : _MVALL;
        $cont .= '<tr>'
            .'<td>'.$a.'</td>'
            .'<td>'.deflmconst($mod[$i]).'</td>'
            .'<td>'.$mod[$i].'</td>'
            .'<td>'.$view.'</td>'
            .'<td>'.ad_status('', $act).'</td>';
        $mod_path = BASE_DIR.'/modules/'.$mod[$i];
        $eadmin = '';
        $emodul = '';
        if (is_dir($mod_path.'/admin/language')) $eadmin = '<a href="'.$admin_file.'.php?op=lang_file&amp;mod='.$mod[$i].'&amp;typ=admin" title="'._FULLEDIT.'">'._ADMIN.'</a>';
        if (is_dir($mod_path.'/language')) {
            $sep = $eadmin ? '||' : '';
            $emodul = $sep.'<a href="'.$admin_file.'.php?op=lang_file&amp;mod='.$mod[$i].'" title="'._FULLEDIT.'">'._MODUL.'</a>';
        }
        $cont .= '<td>'.add_menu($eadmin.$emodul).'</td></tr>';
    }
    $cont .= '</tbody></table>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function lang_file(): void {
    global $admin_file, $confla;
    head();
    $cont = lang_navi(0, 0);
    $mod = getVar('get', 'mod', 'var', '');
    $typ = getVar('get', 'typ', 'var', '');
    $page = getVar('get', 'page', 'num', 1);
    $per_page = $confla['per_page'] ?? 100;
    $lng_cn = [];
    $cnst_arr = [];
    $lang_path = getLangPath($mod, $typ);
    $dir = opendir($lang_path);
    while (($file = readdir($dir)) !== false) if (preg_match('#^(.+)\.php#', $file, $matches)) $lng_cn[] = $matches[1];
    closedir($dir);
    $gl_tmp = $cnst_arr;
    $cnst_arr = [];
    $cj = count($lng_cn);
    for ($j = 0; $j < $cj; $j++) {
        $lng_src = $lang_path.'/'.$lng_cn[$j].'.php';
        checkConfigFile($lng_src);
        $lng = file_get_contents($lng_src);
        preg_match_all('#define\(["\']([^"\']+)["\']\s*,\s*["\'](.*)["\']\);#sU', $lng, $out);
        unset($out[0]);
        $ci = count($out[1]);
        for ($i = 0; $i < $ci; $i++) {
            $lng_arr[$lng_cn[$j]][$out[1][$i]] = $out[2][$i];
            $cnst_tmp[$out[1][$i]] = '';
        }
        $cnst_arr = array_merge($cnst_arr, $cnst_tmp);
        unset($cnst_tmp);
    }
    $sch_tmp = [];
    unset($out);
    $gl_tmp = array_keys($gl_tmp);
    $cnst_arr = array_merge($cnst_arr, $sch_tmp);
    $cnst_arr = array_keys($cnst_arr);
    $cnst_arr = array_diff($cnst_arr, $gl_tmp);
    unset($gl_tmp, $sch_tmp, $cnst_tmp);
    sort($cnst_arr);

    // Pagination
    $total = count($cnst_arr);
    $total_pages = max(1, (int)ceil($total / $per_page));
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $per_page;

    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$admin_file.'.php" method="post"><table class="sl_table_form">';
    $ci = min($per_page, $total - $offset);
    for ($i = 0; $i < $ci; $i++) {
        $idx = $offset + $i;
        $n = $idx + 1;
        $hr = ($i == '0') ? '' : '<tr><td colspan="3"><hr></td></tr>';
        $valc = isset($cnst_arr[$idx]) ? $cnst_arr[$idx] : '';
        $cont .= $hr.'<tr id="'.$n.'"><td>'._CONST.':</td><td><input type="text" name="cnst[]" value="'.$valc.'" class="sl_form" placeholder="'._CONST.'"></td><td><a href="#'.$n.'" title="'._ID.': '.$n.'" class="sl_pnum">'.$n.'</a></td></tr>';
        $cj = count($lng_cn);
        for ($j = 0; $j < $cj; $j++) {
            $val = ($valc) ? trim(str_replace('\"', '&quot;', $lng_arr[$lng_cn[$j]][$cnst_arr[$idx]])) : '';
            if ($lng_cn[$j] == $confla['lang']) {
                $class = 'from_'.$i;
                $button = '';
            } else {
                $class = 'to_'.$i.'-'.$j;
                $langs = ['german' => 'de', 'polish' => 'pl'];
                $floc = substr(strtr($confla['lang'], $langs), 0, 2);
                $tloc = substr(strtr($lng_cn[$j], $langs), 0, 2);
                $button = '<input type="button" OnClick="TranslateLang(\'from_'.$i.'\', \'to_'.$i.'-'.$j.'\', \''.$floc.'-'.$tloc.'\', \''._ERRORTR.'\', \''.$confla['key'].'\');" value="'._OK.'" title="'._EAUTOTR.'" class="sl_but_blue">';
            }
            $cont .= '<tr><td>'.deflang($lng_cn[$j]).':</td><td><input type="text" name="lng['.$lng_cn[$j].'][]" value="'.$val.'" class="sl_form '.$class.'" placeholder="'.deflang($lng_cn[$j]).'"></td><td>'.$button.'</td></tr>';
        }
    }
    $cont .= '<tr><td colspan="3" class="sl_center">';
    $cj = count($lng_cn);
    for ($j = 0; $j < $cj; $j++) $cont .= '<input type="hidden" name="lcn[]" value="'.$lng_cn[$j].'">';
    $cont .= '<input type="hidden" name="typ" value="'.$typ.'">';
    $cont .= '<input type="hidden" name="mod" value="'.$mod.'">';
    $cont .= '<input type="hidden" name="page" value="'.$page.'">';
    $cont .= '<input type="hidden" name="op" value="lang_save">';
    $cont .= '<input type="hidden" name="refer" value="1">';
    $cont .= '<input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';

    // Pagination via setPageNumbers()
    $url = 'op=lang_file&mod='.urlencode($mod).'&typ='.urlencode($typ).'&';
    $cont .= setPageNumbers(
        'pagenum',
        'lang',
        $total,
        $total_pages,
        $per_page,
        $url,
        10,
        $page,
        '',
        'page'
    );

    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function lang_save(): void {
    global $admin_file;
    $mod = getVar('post', 'mod', 'var', '');
    $typ = getVar('post', 'typ', 'var', '');
    $lng_cn = getVar('post', 'lcn[]', 'var') ?: [];
    $page = getVar('post', 'page', 'num', 1);
    $cnst = getVar('post', 'cnst[]', 'var') ?: [];
    $translations = getVar('post', 'lng', 'var', []);
    $lang_path = getLangPath($mod, $typ);
    $cj = count($lng_cn);
    for ($j = 0; $j < $cj; $j++) {
        $lng_cnj = $lng_cn[$j];
        $lng_src = $lang_path.'/'.$lng_cnj.'.php';

        // Read existing constants from file
        $existing = [];
        if (file_exists($lng_src)) {
            $lng = file_get_contents($lng_src);
            preg_match_all('#define\(["\']([^"\']+)["\']\s*,\s*["\'](.*)["\']\);#sU', $lng, $matches);
            $ck = count($matches[1]);
            for ($k = 0; $k < $ck; $k++) {
                $existing[$matches[1][$k]] = $matches[2][$k];
            }
        }

        // Update with submitted constants
        $ci = count($cnst);
        for ($i = 0; $i < $ci; $i++) {
            if (empty($cnst[$i])) continue;
            if (empty($translations[$lng_cnj][$i])) continue;
            $cons = trim($cnst[$i]);
            $in = ['\\\'', '\\$', '<?php', '?>'];
            $ou = ['\'', '\$', '&lt;?php', '?&gt;'];
            $cont = trim(str_replace($in, $ou, $translations[$lng_cnj][$i]));
            $existing[$cons] = $cont;
        }

        // Write all constants back (submitted + existing)
        $lng_str = '<?php'.PHP_EOL.'# Author: Eduard Laas'.PHP_EOL.'# Copyright © 2005 - '.date('Y').' SLAED'.PHP_EOL.'# License: GNU GPL 3'.PHP_EOL.'# Website: slaed.net'.PHP_EOL.PHP_EOL;
        foreach ($existing as $cons => $cont) {
            $cons_esc = str_replace("'", "\\'", $cons);
            $cont_esc = str_replace("'", "\\'", $cont);
            $lng_str .= 'define(\''.$cons_esc.'\',\''.$cont_esc.'\');'.PHP_EOL;
        }
        $handle = fopen($lng_src, 'wb');
        fwrite($handle, $lng_str);
        fclose($handle);
    }
    $url = $admin_file.'.php?op=lang_file&mod='.urlencode($mod).'&typ='.urlencode($typ).'&page='.$page;
    header('Location: '.$url);
}

function lang_conf(): void {
    global $admin_file, $confla;
    head();
    checkConfigFile('lang.php');
    $cont = lang_navi(1, 0);
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$admin_file.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._LANGKEY.':</td><td><input type="text" name="key" value="'.$confla['key'].'" class="sl_conf" placeholder="'._LANGKEY.'" required></td></tr>'
    .'<tr><td>'._LANGTR.':</td><td><select name="lang" class="sl_conf">'.language($confla['lang'], 1).'</select></td></tr>'
    .'<tr><td>'._LANGCOUNT.':</td><td><input type="number" name="count" value="'.$confla['count'].'" class="sl_conf" placeholder="'._LANGCOUNT.'" required></td></tr>'
    .'<tr><td>Konstanten pro Seite:<div class="sl_small">Max. Konstanten pro Seite (empfohlen: 100)</div></td><td><input type="number" name="per_page" value="'.($confla['per_page'] ?? 100).'" class="sl_conf" placeholder="100" min="10" max="500" required></td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="op" value="lang_conf_save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function lang_conf_save(): void {
    global $admin_file, $confla;
    $cont = [
        'key' => getVar('post', 'key', 'text', ''),
        'lang' => getVar('post', 'lang', 'var', 'russian'),
        'count' => getVar('post', 'count', 'num', 0),
        'per_page' => getVar('post', 'per_page', 'num', 100)
    ];
    setConfigFile('lang.php', 'confla', $cont, $confla);
    header('Location: '.$admin_file.'.php?op=lang_conf');
}

function lang_info(): void {
    head();
    echo lang_navi(2, 0).'<div id="repadm_info">'.adm_info(1, 0, 'lang').'</div>';
    foot();
}

switch($op) {
    case 'lang_main':
    lang_main();
    break;

    case 'lang_file':
    lang_file();
    break;

    case 'lang_save':
    lang_save();
    break;

    case 'lang_conf':
    lang_conf();
    break;

    case 'lang_conf_save':
    lang_conf_save();
    break;

    case 'lang_info':
    lang_info();
    break;
}
