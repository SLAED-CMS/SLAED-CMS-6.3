<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function getLangPath(string $mod = '', string $typ = ''): string {
    $base = BASE_DIR;
    $module = $mod ? '/modules/'.$mod : '';
    $type = $typ ? '/'.$typ : '';
    return $base.$module.$type.'/lang';
}

function lang(): void {
    global $conf, $afile, $tpl;
    $modbase = [];
    $who_view = [];
    foreach ($conf['modules'] as $ttl => $info) {
        $modbase[$ttl] = !empty($info['active']) ? 1 : 0;
        $view = (int)($info['view'] ?? 0);
        if ($view === 0) {
            $who_view[$ttl] = _MVALL;
        } elseif ($view === 1) {
            $who_view[$ttl] = _MVUSERS;
        } elseif ($view === 2) {
            $who_view[$ttl] = _MVADMIN;
        }
    }

    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=lang', 'name=lang&amp;op=config', 'name=lang&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO]]);
    $head = getTplAdminTableHead([_ID, _NAME, _MODUL, _VIEW, [_STATUS, 'nosort'], [_FUNCTIONS, 'nosort']]);
    $rows = '';
    $sys_admin = getTplLinkAction($afile.'.php?name=lang&amp;op=fileedit&amp;typ=admin', _FULLEDIT, _ADMIN);
    $sys_modul = getTplLinkAction($afile.'.php?name=lang&amp;op=fileedit', _FULLEDIT, _MODUL);
    $rows .= getTplAdminTableRow($tpl->getHtmlFrag('admin-lang-list-row', [
        'actions_html' => getTplAdminActionMenu([$sys_admin, $sys_modul]),
        'id_value' => '1',
        'module_label' => _SYSTEM,
        'module_name' => _ALL,
        'status_html' => ad_status('', 1),
        'view_label' => _MVALL,
    ]));
    $mod = [];
    $files = scandir(BASE_DIR.'/modules');
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && is_dir(BASE_DIR.'/modules/'.$file) && is_file(BASE_DIR.'/modules/'.$file.'/index.php')) $mod[] = $file;
    }
    sort($mod);
    $ci = count($mod);
    for ($i = 0; $i < $ci; $i++) {
        $a = $i + 2;
        $act = isset($modbase[$mod[$i]]) && $modbase[$mod[$i]] ? 1 : 0;
        $view = $who_view[$mod[$i]] ?? _MVALL;
        $mod_path = BASE_DIR.'/modules/'.$mod[$i];
        $acts = [];
        if (is_dir($mod_path.'/admin/lang')) $acts[] = getTplLinkAction($afile.'.php?name=lang&amp;op=fileedit&amp;mod='.$mod[$i].'&amp;typ=admin', _FULLEDIT, _ADMIN);
        if (is_dir($mod_path.'/lang')) $acts[] = getTplLinkAction($afile.'.php?name=lang&amp;op=fileedit&amp;mod='.$mod[$i], _FULLEDIT, _MODUL);
        $rows .= getTplAdminTableRow($tpl->getHtmlFrag('admin-lang-list-row', [
            'actions_html' => getTplAdminActionMenu($acts),
            'id_value' => (string)$a,
            'module_label' => getModuleName($mod[$i]),
            'module_name' => $mod[$i],
            'status_html' => ad_status('', $act),
            'view_label' => $view,
        ]));
    }
    $cont .= getTplAdminTable($head, $rows);
    echo $cont;
    setFoot();
}

function fileedit(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=lang', 'name=lang&amp;op=config', 'name=lang&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO]]);
    $mod = getVar('get', 'mod', 'var', '');
    $typ = getVar('get', 'typ', 'var', '');
    $page = getVar('get', 'page', 'num', 1);
    $per_page = $conf['lang']['per_page'] ?? 100;
    $lng_cn = [];
    $cnst_arr = [];
    $lang_path = getLangPath($mod, $typ);
    if (!is_dir($lang_path)) {
        echo $cont.$tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _NO_INFO]);
        setFoot();
        return;
    }
    foreach (scandir($lang_path) as $file) {
        if (preg_match('#^(.+)\.php#', $file, $matches)) $lng_cn[] = $matches[1];
    }
    $gl_tmp = $cnst_arr;
    $cnst_arr = [];
    $cj = count($lng_cn);
    for ($j = 0; $j < $cj; $j++) {
        $lng_src = $lang_path.'/'.$lng_cn[$j].'.php';
        checkPerms($lng_src);
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
    $total = count($cnst_arr);
    $total_pages = max(1, (int)ceil($total / $per_page));
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $per_page;
    $hide = '';
    $cj = count($lng_cn);
    for ($j = 0; $j < $cj; $j++) $hide .= getTplHiddenInput('lcn[]', $lng_cn[$j]);
    $hide .= getTplHiddenInput('typ', $typ).getTplHiddenInput('mod', $mod).getTplHiddenInput('page', (string)$page).getTplHiddenInput('name', 'lang').getTplHiddenInput('op', 'save').getTplHiddenInput('refer', '1');
    $rows = '';
    $ci = min($per_page, $total - $offset);
    for ($i = 0; $i < $ci; $i++) {
        $idx = $offset + $i;
        $n = $idx + 1;
        $valc = isset($cnst_arr[$idx]) ? $cnst_arr[$idx] : '';
        if ($i !== 0) $rows .= getTplAdminFormWide(getTplHrLine());
        $rows .= getTplAdminFormRow(_CONST.':', getTplTextInput('cnst[]', $valc, 'sl_form', 'placeholder="'._CONST.'"').' '.getTplAdminTextLink('#'.$n, (string)$n, '', _ID.': '.$n, 'sl_pnum'), 'id="'.$n.'"');
        $cj = count($lng_cn);
        for ($j = 0; $j < $cj; $j++) {
            $val = ($valc) ? trim(str_replace('\"', '&quot;', $lng_arr[$lng_cn[$j]][$cnst_arr[$idx]])) : '';
            if ($lng_cn[$j] == $conf['lang']['lang']) {
                $class = 'from_'.$i;
                $btn = '';
            } else {
                $class = 'to_'.$i.'-'.$j;
                $floc = substr($conf['lang']['lang'], 0, 2);
                $tloc = substr($lng_cn[$j], 0, 2);
                $btn = $tpl->getHtmlFrag('admin-lang-translate-button', [
                    'api_key' => $conf['lang']['key'],
                    'error_text' => _ERRORTR,
                    'from_class' => 'from_'.$i,
                    'label' => _OK,
                    'locale_pair' => $floc.'-'.$tloc,
                    'title' => _EAUTOTR,
                    'to_class' => 'to_'.$i.'-'.$j,
                ]);
            }
            $rows .= getTplAdminFormRow(getLangName($lng_cn[$j]).':', getTplTextInput('lng['.$lng_cn[$j].'][]', $val, 'sl_form '.$class, 'placeholder="'.getLangName($lng_cn[$j]).'"').$btn);
        }
    }
    $rows .= getTplAdminFormWide(getTplAdminSubmitButton(_SAVECHANGES), '', 'sl_center');
    $box = getTplAdminForm($afile.'.php', $rows, $hide);
    $url = 'name=lang&op=fileedit&mod='.urlencode($mod).'&typ='.urlencode($typ).'&';
    $box .= setPageNumbers('pagenum', 'lang', $total, $total_pages, $per_page, $url, 10, $page, '', 'page');
    echo $cont.getTplBox($box);
    setFoot();
}

function save(): void {
    global $afile;
    $mod = getVar('post', 'mod', 'var', '');
    $typ = getVar('post', 'typ', 'var', '');
    $lng_cn = getVar('post', 'lcn[]', 'var', []);
    $page = getVar('post', 'page', 'num', 1);
    $cnst = getVar('post', 'cnst', 'var', []);
    $lng = getVar('post', 'lng', 'var', []);
    $lang_path = getLangPath($mod, $typ);
    $cj = count($lng_cn);
    for ($j = 0; $j < $cj; $j++) {
        $lng_cnj = $lng_cn[$j];
        $lng_src = $lang_path.'/'.$lng_cnj.'.php';
        $existing = [];
        if (file_exists($lng_src)) {
            $lng = file_get_contents($lng_src);
            preg_match_all('#define\(["\']([^"\']+)["\']\s*,\s*["\'](.*)["\']\);#sU', $lng, $matches);
            $ck = count($matches[1]);
            for ($k = 0; $k < $ck; $k++) {
                $existing[$matches[1][$k]] = $matches[2][$k];
            }
        }
        $ci = count($cnst);
        for ($i = 0; $i < $ci; $i++) {
            if (empty($cnst[$i])) continue;
            if (empty($lng[$lng_cnj][$i])) continue;
            $cons = trim($cnst[$i]);
            $in = ['\\\'', '\\$', '<?php', '?>'];
            $ou = ['\'', '\$', '&lt;?php', '?&gt;'];
            $cont = trim(str_replace($in, $ou, $lng[$lng_cnj][$i]));
            $existing[$cons] = $cont;
        }
        $lng_str = '<?php'.PHP_EOL.'# Author: Eduard Laas'.PHP_EOL.'# Copyright (c) 2005 - '.date('Y').' SLAED'.PHP_EOL.'# License: GNU GPL 3'.PHP_EOL.'# Website: slaed.net'.PHP_EOL.PHP_EOL;
        foreach ($existing as $cons => $cont) {
            $cons_esc = str_replace("'", "\\'", $cons);
            $cont_esc = str_replace("'", "\\'", $cont);
            $lng_str .= 'define(\''.$cons_esc.'\',\''.$cont_esc.'\');'.PHP_EOL;
        }
        $handle = fopen($lng_src, 'wb');
        fwrite($handle, $lng_str);
        fclose($handle);
    }
    $url = $afile.'.php?name=lang&op=fileedit&mod='.urlencode($mod).'&typ='.urlencode($typ).'&page='.$page;
    setRedirect($url);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=lang', 'name=lang&amp;op=config', 'name=lang&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'tab' => 1]);
    $cont .= checkPerms(CONFIG_DIR.'/lang.php');
    $s_lang = getTplSelect('lang', language($conf['lang']['lang'], 1), 'sl_conf');
    $confv = $tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'lang',
        'op' => 'configsave',
        'save' => _SAVECHANGES,
        'fields' => getTplHiddenInput('token', $token),
        '_langkey' => _LANGKEY,
        'key' => $conf['lang']['key'],
        '_langtr' => _LANGTR,
        's_lang' => $s_lang,
        '_langcount' => _LANGCOUNT,
        'count' => $conf['lang']['count'],
        'per_page' => $conf['lang']['per_page'] ?? 100,
        'lang' => true,
    ]);
    echo $cont.getTplBox($confv);
    setFoot();
}

function configsave(): void {
    global $afile, $conf;
    $cont = [
        'key' => getVar('post', 'key', 'text', ''),
        'lang' => getVar('post', 'lang', 'var', 'russian'),
        'count' => getVar('post', 'count', 'num', 0),
        'per_page' => getVar('post', 'per_page', 'num', 100)
    ];
    setConfigFile('lang.php', $cont, $conf['lang']);
    setRedirect($afile.'.php?name=lang&op=config');
}

function info(): void {
    $cont = getTplAdminNavi(['ops' => ['name=lang', 'name=lang&amp;op=config', 'name=lang&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'tab' => 2]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: lang(); break;
    case 'fileedit': fileedit(); break;
    case 'save': save(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
