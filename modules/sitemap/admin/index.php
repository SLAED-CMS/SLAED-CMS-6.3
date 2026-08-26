<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('sitemap')) die('Illegal file access');

function getSitemapFreqSelect(string $name, string $value): string {
    global $tpl;
    $opts = '';
    foreach (['0' => _NO, 'always' => _ALWAYS, 'hourly' => _HOURLY, 'daily' => _DAILY, 'weekly' => _WEEKLY, 'monthly' => _MONTHLY, 'yearly' => _YEARLY, 'never' => _NEVER] as $key => $label) {
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$key,
            'label_text' => $label,
            'is_selected' => $value === (string)$key,
        ]);
    }
    return $tpl->getHtmlFrag('select', [
        'name_attr' => $name,
        'is_config' => true,
        'options_html' => $opts,
    ]);
}

function getSitemapPrioritySelect(string $name, string $value): string {
    global $tpl;
    $opts = '';
    foreach (['1.0', '0.9', '0.8', '0.7', '0.6', '0.5', '0.4', '0.3', '0.2', '0.1', '0'] as $val) {
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $val,
            'label_text' => $val,
            'is_selected' => $value === $val,
        ]);
    }
    return $tpl->getHtmlFrag('select', [
        'name_attr' => $name,
        'is_config' => true,
        'options_html' => $opts,
    ]);
}

function sitemap(): void {
    global $afile, $conf, $tpl;
    setHead();
    $file = 'sitemap.xml';
    $cont = getTplAdminTabs([
        'ops' => ['name=sitemap', 'name=sitemap&op=xsledit', 'name=sitemap&op=config', 'name=sitemap&op=info'],
        'tabs' => [_HOME, _TEMPLATE, _PREFERENCES, _DOCS],
    ]);
    $cont .= checkPerms(BASE_DIR.'/'.$file);
    $conts = is_readable($file) ? file_get_contents($file) : '';
    $f = 0;
    $asize = 0;
    $lines = [_SITEMAP.': '.$conf['homeurl'].'/'.$file];
    foreach (glob('sitemap*.xml*') as $cfile) {
        $cont .= checkPerms(BASE_DIR.'/'.$cfile);
        $handle = fopen($cfile, 'rb');
        $n = 0;
        if ($handle) {
            while (!feof($handle)) {
                $bufer = fread($handle, 1048576);
                $n += substr_count($bufer, '</loc>');
            }
            fclose($handle);
        }
        $size = filesize($cfile);
        $lines[] = _FILE.': '.$cfile;
        $lines[] = _DATE.': '.date(_TIMESTRING, filemtime($cfile));
        $lines[] = _SIZE.': '.filterSize($size);
        $lines[] = _URLS.': '.$n;
        $f++;
        $asize += $size;
    }
    $lines[] = _FILE_M.': '.$f;
    $lines[] = _FILE_S.': '.filterSize($asize);
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'messages' => $lines])]);
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'sitemap'],
            ['nameattr' => 'op', 'valueattr' => 'add'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'content_html' => Editor::getCode([
            'id' => 'code',
            'lang' => 'xml',
            'text' => str_replace('&', '&amp;', $conts),
        ]),
        'submit_label' => _UPDATE,
    ])]);
    echo $cont;
    setFoot();
}

function add(): void {
    global $afile;
    $iswarn = !checkSiteToken();
    if (!$iswarn) addSchedulerRun('sitemap', 'manual');
    setRedirect($afile.'.php?name=sitemap', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function xsledit(): void {
    global $afile, $tpl;
    setHead();
    $file = SITEMAP_DIR.'/sitemap.xsl';
    $cont = getTplAdminTabs([
        'ops' => ['name=sitemap', 'name=sitemap&op=xsledit', 'name=sitemap&op=config', 'name=sitemap&op=info'],
        'tabs' => [_HOME, _TEMPLATE, _PREFERENCES, _DOCS],
        'tab' => 1,
    ]);
    $cont .= checkPerms($file);
    $conts = is_readable($file) ? file_get_contents($file) : '';
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => sprintf(_XSL_INFO, $file)])]);
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'sitemap'],
            ['nameattr' => 'op', 'valueattr' => 'xslsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'content_html' => Editor::getCode([
            'id' => 'code',
            'name' => 'template',
            'lang' => 'xml',
            'text' => $conts,
        ]),
        'submit_label' => _SAVE,
    ])]);
    echo $cont;
    setFoot();
}

function xslsave(): void {
    global $afile;
    $iswarn = !checkSiteToken();
    $file = SITEMAP_DIR.'/sitemap.xsl';
    $template = getVar('post', 'template', 'raw', '');
    if (!$iswarn && $template !== '') {
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            setRedirect($afile.'.php?name=sitemap&op=xsledit', false, 302, _NO_INFO, true);
            return;
        }
        file_put_contents($file, $template);
    }
    setRedirect($afile.'.php?name=sitemap&op=xsledit', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=sitemap', 'name=sitemap&op=xsledit', 'name=sitemap&op=config', 'name=sitemap&op=info'],
        'tabs' => [_HOME, _TEMPLATE, _PREFERENCES, _DOCS],
        'tab' => 2,
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/sitemap.php');
    $yesno = static fn(string $name, int|string $value): string => getTplRadioGroup([
        'name' => $name,
        'value' => $value,
        'options' => [
            ['value' => '1', 'label' => _YES],
            ['value' => '0', 'label' => _NO],
        ],
    ]);
    $rows = [
        ['label_html' => _MODULES, 'field_html' => getTplModuleSelect('mod', $conf['sitemap']['mod'] ?? '', 1), 'is_full' => true],
        ['label_for' => 'f-auto-t', 'label_html' => _MAP_AUTO_T, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'auto_t', 'input_id' => 'f-auto-t', 'value_attr' => (string)intval(($conf['sitemap']['auto_t'] ?? 0) / 3600), 'is_config' => true])],
        ['label_html' => _MAP_AUTO, 'field_html' => $yesno('auto', $conf['sitemap']['auto'] ?? 0)],
        ['label_html' => _MAP_FR_H, 'field_html' => getSitemapFreqSelect('fr_h', (string)($conf['sitemap']['fr_h'] ?? '0'))],
        ['label_html' => _MAP_FR_M, 'field_html' => getSitemapFreqSelect('fr_m', (string)($conf['sitemap']['fr_m'] ?? '0'))],
        ['label_html' => _MAP_FR_C, 'field_html' => getSitemapFreqSelect('fr_c', (string)($conf['sitemap']['fr_c'] ?? '0'))],
        ['label_html' => _MAP_FR_P, 'field_html' => getSitemapFreqSelect('fr_p', (string)($conf['sitemap']['fr_p'] ?? '0'))],
        ['label_html' => _MAP_PR_H, 'field_html' => getSitemapPrioritySelect('pr_h', (string)($conf['sitemap']['pr_h'] ?? '0'))],
        ['label_html' => _MAP_PR_M, 'field_html' => getSitemapPrioritySelect('pr_m', (string)($conf['sitemap']['pr_m'] ?? '0'))],
        ['label_html' => _MAP_PR_C, 'field_html' => getSitemapPrioritySelect('pr_c', (string)($conf['sitemap']['pr_c'] ?? '0'))],
        ['label_html' => _MAP_PR_P, 'field_html' => getSitemapPrioritySelect('pr_p', (string)($conf['sitemap']['pr_p'] ?? '0'))],
        ['label_html' => _MAP_DAT_H, 'field_html' => $yesno('dat_h', $conf['sitemap']['dat_h'] ?? 0)],
        ['label_html' => _MAP_DAT_M, 'field_html' => $yesno('dat_m', $conf['sitemap']['dat_m'] ?? 0)],
        ['label_html' => _MAP_DAT_C, 'field_html' => $yesno('dat_c', $conf['sitemap']['dat_c'] ?? 0)],
        ['label_html' => _MAP_DAT_P, 'field_html' => $yesno('dat_p', $conf['sitemap']['dat_p'] ?? 0)],
        ['label_html' => _MAP_GEN_H, 'field_html' => $yesno('gen_h', $conf['sitemap']['gen_h'] ?? 0)],
        ['label_html' => _MAP_GEN_M, 'field_html' => $yesno('gen_m', $conf['sitemap']['gen_m'] ?? 0)],
        ['label_html' => _MAP_GEN_C, 'field_html' => $yesno('gen_c', $conf['sitemap']['gen_c'] ?? 0)],
        ['label_html' => _MAP_GEN_P, 'field_html' => $yesno('gen_p', $conf['sitemap']['gen_p'] ?? 0)],
        ['label_html' => _MAP_XSL, 'field_html' => $yesno('xsl', $conf['sitemap']['xsl'] ?? 0)],
        ['label_html' => _MAP_SITE, 'field_html' => $yesno('txt', $conf['sitemap']['txt'] ?? 0)],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'sitemap'],
            ['nameattr' => 'op', 'valueattr' => 'configsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
        $mod = getVar('post', 'mod[]', 'var');
        $cont = [
            'mod' => empty($mod[0]) ? '0' : implode(',', $mod),
            'auto_t' => getVar('post', 'auto_t', 'num', 1) * 3600,
            'auto' => getVar('post', 'auto', 'num', 0),
            'fr_h' => getVar('post', 'fr_h', 'var', '0'),
            'fr_m' => getVar('post', 'fr_m', 'var', '0'),
            'fr_c' => getVar('post', 'fr_c', 'var', '0'),
            'fr_p' => getVar('post', 'fr_p', 'var', '0'),
            'pr_h' => getVar('post', 'pr_h', 'var', '0'),
            'pr_m' => getVar('post', 'pr_m', 'var', '0'),
            'pr_c' => getVar('post', 'pr_c', 'var', '0'),
            'pr_p' => getVar('post', 'pr_p', 'var', '0'),
            'dat_h' => getVar('post', 'dat_h', 'num', 0),
            'dat_m' => getVar('post', 'dat_m', 'num', 0),
            'dat_c' => getVar('post', 'dat_c', 'num', 0),
            'dat_p' => getVar('post', 'dat_p', 'num', 0),
            'gen_h' => getVar('post', 'gen_h', 'num', 0),
            'gen_m' => getVar('post', 'gen_m', 'num', 0),
            'gen_c' => getVar('post', 'gen_c', 'num', 0),
            'gen_p' => getVar('post', 'gen_p', 'num', 0),
            'xsl' => getVar('post', 'xsl', 'num', 0),
            'txt' => getVar('post', 'txt', 'num', 0),
        ];
        setConfigFile('sitemap.php', $cont);
    }
    setRedirect($afile.'.php?name=sitemap&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=sitemap', 'name=sitemap&op=xsledit', 'name=sitemap&op=config', 'name=sitemap&op=info'],
        'tabs' => [_HOME, _TEMPLATE, _PREFERENCES, _DOCS],
    ]);
}

switch ($op) {
    default: sitemap(); break;
    case 'add': add(); break;
    case 'xsledit': xsledit(); break;
    case 'xslsave': xslsave(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
