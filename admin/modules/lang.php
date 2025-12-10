<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');
require_once CONFIG_DIR.'/lang.php';

function navi(int $tab = 0, int $subtab = 0): string {
    $ops = ['name=lang', 'name=lang&amp;op=conf', 'name=lang&amp;op=info'];
    $lang = [_HOME, _PREFERENCES, _INFO];
    return getAdminTabs(_LANG_EDIT, 'lang.png', '', $ops, $lang, [], [], $tab, $subtab);
}

function getLangPath(string $mod = '', string $typ = ''): string {
    $base = BASE_DIR;
    $module = $mod ? '/modules/'.$mod : '';
    $type = $typ ? '/'.$typ : '';
    return $base.$module.$type.'/language';
}

function lang(): void {
    global $prefix, $db, $aroute;
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
    $cont = navi(0, 0);
    $cont .= setTemplateBasic('open');
    $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._NAME.'</th><th>'._MODUL.'</th><th>'._VIEW.'</th><th class="{sorter: false}">'._STATUS.'</th><th class="{sorter: false}">'._FUNCTIONS.'</th></tr></thead><tbody>';

    $sys_admin = '<a href="'.$aroute.'.php?name=lang&amp;op=editfile&amp;typ=admin" title="'._FULLEDIT.'">'._ADMIN.'</a>';
    $sys_modul = '<a href="'.$aroute.'.php?name=lang&amp;op=editfile" title="'._FULLEDIT.'">'._MODUL.'</a>';
    $cont .= '<tr><td>1</td><td>'._SYSTEM.'</td><td>'._ALL.'</td><td>'._MVALL.'</td><td>'.ad_status('', 1).'</td><td>'.add_menu($sys_admin.'||'.$sys_modul).'</td></tr>';

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
        $cont .= '<tr><td>'.$a.'</td><td>'.deflmconst($mod[$i]).'</td><td>'.$mod[$i].'</td><td>'.$view.'</td><td>'.ad_status('', $act).'</td>';
        $mod_path = BASE_DIR.'/modules/'.$mod[$i];
        $eadmin = '';
        $emodul = '';
        if (is_dir($mod_path.'/admin/language')) $eadmin = '<a href="'.$aroute.'.php?name=lang&amp;op=editfile&amp;mod='.$mod[$i].'&amp;typ=admin" title="'._FULLEDIT.'">'._ADMIN.'</a>';
        if (is_dir($mod_path.'/language')) {
            $sep = $eadmin ? '||' : '';
            $emodul = $sep.'<a href="'.$aroute.'.php?name=lang&amp;op=editfile&amp;mod='.$mod[$i].'" title="'._FULLEDIT.'">'._MODUL.'</a>';
        }
        $cont .= '<td>'.add_menu($eadmin.$emodul).'</td></tr>';
    }
    $cont .= '</tbody></table>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function editfile(): void {
    global $aroute, $confla;
    head();
    $cont = navi(0, 0);
    $mod = getVar('get', 'mod', 'var', '');
    $typ = getVar('get', 'typ', 'var', '');
    $page = getVar('get', 'page', 'num', 1);
    $per_page = $confla['per_page'] ?? 100;
    $lng_cn = [];
    $cnst_arr = [];
    $lang_path = getLangPath($mod, $typ);
    foreach (scandir($lang_path) as $file) {
        if (preg_match('#^(.+)\.php#', $file, $matches)) $lng_cn[] = $matches[1];
    }
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
    $cont .= '<form action="'.$aroute.'.php" method="post"><table class="sl_table_form">';
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
                $floc = substr($confla['lang'], 0, 2);
                $tloc = substr($lng_cn[$j], 0, 2);
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
    $cont .= '<input type="hidden" name="name" value="lang">';
    $cont .= '<input type="hidden" name="op" value="save">';
    $cont .= '<input type="hidden" name="refer" value="1">';
    $cont .= '<input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';

    // Pagination via setPageNumbers()
    $url = 'name=lang&op=editfile&mod='.urlencode($mod).'&typ='.urlencode($typ).'&';
    $cont .= setPageNumbers('pagenum', 'lang', $total, $total_pages, $per_page, $url, 10, $page, '', 'page');

    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function save(): void {
    global $aroute;
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
    $url = $aroute.'.php?name=lang&op=editfile&mod='.urlencode($mod).'&typ='.urlencode($typ).'&page='.$page;
    header('Location: '.$url);
    exit;
}

function conf(): void {
    global $aroute, $confla;
    head();
    checkConfigFile('lang.php');
    $cont = navi(1, 0);
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$aroute.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._LANGKEY.':</td><td><input type="text" name="key" value="'.$confla['key'].'" class="sl_conf" placeholder="'._LANGKEY.'" required></td></tr>'
    .'<tr><td>'._LANGTR.':</td><td><select name="lang" class="sl_conf">'.language($confla['lang'], 1).'</select></td></tr>'
    .'<tr><td>'._LANGCOUNT.':</td><td><input type="number" name="count" value="'.$confla['count'].'" class="sl_conf" placeholder="'._LANGCOUNT.'" required></td></tr>'
    .'<tr><td>Konstanten pro Seite:<div class="sl_small">Max. Konstanten pro Seite (empfohlen: 100)</div></td><td><input type="number" name="per_page" value="'.($confla['per_page'] ?? 100).'" class="sl_conf" placeholder="100" min="10" max="500" required></td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="lang"><input type="hidden" name="op" value="confsave"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function confsave(): void {
    global $aroute, $confla;
    $cont = [
        'key' => getVar('post', 'key', 'text', ''),
        'lang' => getVar('post', 'lang', 'var', 'russian'),
        'count' => getVar('post', 'count', 'num', 0),
        'per_page' => getVar('post', 'per_page', 'num', 100)
    ];
    setConfigFile('lang.php', 'confla', $cont, $confla);
    header('Location: '.$aroute.'.php?name=lang&op=conf');
    exit;
}

function info(): void {
    head();
    echo navi(2, 0).'<div id="repadm_info">'.adm_info(1, 0, 'lang').'</div>';
    foot();
}

switch ($op) {
    default: lang(); break;
    case 'editfile': editfile(); break;
    case 'save': save(); break;
    case 'conf': conf(); break;
    case 'confsave': confsave(); break;
    case 'info': info(); break;
}
