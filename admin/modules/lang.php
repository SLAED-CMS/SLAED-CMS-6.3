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
    $cont = getTplAdminTabs(['ops' => ['name=lang', 'name=lang&amp;op=config', 'name=lang&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO]]);
    $rows = [];
    $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
        'cells' => [
            ['is_col_id' => true, 'content_html' => '1'],
            ['content_html' => _SYSTEM],
            ['content_html' => _ALL],
            ['content_html' => _MVALL],
            ['is_col_status' => true, 'content_html' => ad_status('', 1)],
            ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('popover', [
                'trigger_label' => _FUNCTIONS,
                'items' => [
                    ['href' => $afile.'.php?name=lang&amp;op=fileedit&amp;typ=admin', 'label' => _ADMIN, 'title' => _FULLEDIT],
                    ['href' => $afile.'.php?name=lang&amp;op=fileedit', 'label' => _MODUL, 'title' => _FULLEDIT],
                ],
            ])],
        ],
    ])]);
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
        $items = [];
        if (is_dir($mod_path.'/admin/lang')) $items[] = ['href' => $afile.'.php?name=lang&amp;op=fileedit&amp;mod='.$mod[$i].'&amp;typ=admin', 'label' => _ADMIN, 'title' => _FULLEDIT];
        if (is_dir($mod_path.'/lang')) $items[] = ['href' => $afile.'.php?name=lang&amp;op=fileedit&amp;mod='.$mod[$i], 'label' => _MODUL, 'title' => _FULLEDIT];
        $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['is_col_id' => true, 'content_html' => (string)$a],
                ['is_truncate' => true, 'title_text' => getModuleName($mod[$i]), 'content_html' => getModuleName($mod[$i])],
                ['is_truncate' => true, 'title_text' => $mod[$i], 'content_html' => $mod[$i]],
                ['content_html' => $view],
                ['is_col_status' => true, 'content_html' => ad_status('', $act)],
                ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('popover', ['trigger_label' => _FUNCTIONS, 'items' => $items])],
            ],
        ])]);
    }
    $cont .= $tpl->getHtmlFrag('table', [
        'is_fixed' => true,
        'head' => [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _NAME, 'is_truncate' => true],
            ['content' => _MODUL, 'is_truncate' => true],
            ['content' => _VIEW],
            ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ],
        'rows_html' => implode('', $rows),
    ]);
    echo $cont;
    setFoot();
}

function fileedit(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=lang', 'name=lang&amp;op=config', 'name=lang&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO]]);
    $mod = getVar('get', 'mod', 'var', '');
    $typ = getVar('get', 'typ', 'var', '');
    $page = getVar('get', 'page', 'num', 1);
    $per_page = $conf['lang']['per_page'] ?? 100;
    $lng_cn = [];
    $cnst_arr = [];
    $lang_path = getLangPath($mod, $typ);
    if (!is_dir($lang_path)) {
        echo $cont.$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _NO_INFO]);
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
    $groups = [];
    $ci = min($per_page, $total - $offset);
    for ($i = 0; $i < $ci; $i++) {
        $idx = $offset + $i;
        $n = $idx + 1;
        $valc = isset($cnst_arr[$idx]) ? $cnst_arr[$idx] : '';
        $rows = [[
            'label_html' => _CONST.': #'.$n,
            'is_lang_edit' => true,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'cnst[]',
                'value_attr' => $valc,
                'placeholder_text' => _CONST,
                'is_form_control' => true,
            ]),
        ]];
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
                $btn = $tpl->getHtmlFrag('button', [
                    'label' => _OK,
                    'button_attr' => ' title="'.htmlspecialchars(_EAUTOTR, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" onclick="TranslateLang(\'from_'.$i.'\', \'to_'.$i.'-'.$j.'\', \''.$floc.'-'.$tloc.'\', \''._ERRORTR.'\', \''.$conf['lang']['key'].'\');"',
                ]);
            }
            $rows[] = [
                'label_html' => getLangName($lng_cn[$j]),
                'is_lang_edit' => true,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'itype' => 'text',
                    'name_attr' => 'lng['.$lng_cn[$j].'][]',
                    'value_attr' => $val,
                    'placeholder_text' => getLangName($lng_cn[$j]),
                    'is_form_control' => true,
                    'translate_target' => $class,
                ]).$btn,
            ];
        }
        $groups[] = $tpl->getHtmlPart('div', ['rows' => $rows]);
    }
    $pager = getPageNumbers('', $total, $total_pages, $per_page, 'name=lang&op=fileedit&mod='.urlencode($mod).'&typ='.urlencode($typ).'&', $total_pages, $page, '', 'page');
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => array_merge(
            array_map(static fn($code) => ['nameattr' => 'lcn[]', 'valueattr' => $code], $lng_cn),
            [
                ['nameattr' => 'typ', 'valueattr' => $typ],
                ['nameattr' => 'mod', 'valueattr' => $mod],
                ['nameattr' => 'page', 'valueattr' => (string)$page],
                ['nameattr' => 'name', 'valueattr' => 'lang'],
                ['nameattr' => 'op', 'valueattr' => 'save'],
                ['nameattr' => 'refer', 'valueattr' => '1'],
                ['nameattr' => 'token', 'valueattr' => getSiteToken()],
            ]
        ),
        'content_html' => implode('', $groups).$pager,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $form]);
    setFoot();
}

function save(): void {
    global $afile;
    $warn = !checkSiteToken();
    $mod = getVar('post', 'mod', 'var', '');
    $typ = getVar('post', 'typ', 'var', '');
    $lng_cn = getVar('post', 'lcn[]', 'var', []);
    $page = getVar('post', 'page', 'num', 1);
    $cnst = getVar('post', 'cnst', 'var', []);
    $lng = getVar('post', 'lng', 'var', []);
    $lang_path = getLangPath($mod, $typ);
    if (!$warn) {
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
    }
    $url = $afile.'.php?name=lang&op=fileedit&mod='.urlencode($mod).'&typ='.urlencode($typ).'&page='.$page;
    setRedirect($url, false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=lang', 'name=lang&amp;op=config', 'name=lang&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'tab' => 1]);
    $cont .= checkPerms(CONFIG_DIR.'/lang.php');
    $s_lang = $tpl->getHtmlFrag('select', ['name_attr' => 'lang', 'options_html' => getTplLanguageOptions($conf['lang']['lang'], 1), 'is_config' => true]);
    $rows = [
        ['label_html' => _LANGKEY, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'key', 'value_attr' => (string)$conf['lang']['key'], 'is_config' => true])],
        ['label_html' => _LANGTR, 'field_html' => $s_lang],
        ['label_html' => _LANGCOUNT, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'count', 'value_attr' => (string)$conf['lang']['count'], 'is_config' => true])],
        ['label_html' => _PERPAGE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'per_page', 'value_attr' => (string)($conf['lang']['per_page'] ?? 100), 'is_config' => true])],
    ];
    $confv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'lang'],
            ['nameattr' => 'op', 'valueattr' => 'configsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $confv]);
    setFoot();
}

function configsave(): void {
    global $afile, $conf;
    $warn = !checkSiteToken();
    if (!$warn) {
        $cont = [
            'key' => getVar('post', 'key', 'text', ''),
            'lang' => getVar('post', 'lang', 'var', 'russian'),
            'count' => getVar('post', 'count', 'num', 0),
            'per_page' => getVar('post', 'per_page', 'num', 100)
        ];
        setConfigFile('lang.php', $cont, $conf['lang']);
    }
    setRedirect($afile.'.php?name=lang&op=config', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=lang', 'name=lang&amp;op=config', 'name=lang&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _INFO],
    ]);
}

switch ($op) {
    default: lang(); break;
    case 'fileedit': fileedit(); break;
    case 'save': save(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
