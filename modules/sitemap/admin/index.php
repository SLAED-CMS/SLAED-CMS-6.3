<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('sitemap')) die('Illegal file access');

function sitemap(): void {
    global $afile, $conf;
    setHead();
    $file = 'sitemap.xml';
    $cont = setAdminNavi(['ops' => ['name=sitemap', 'name=sitemap&amp;op=xsledit', 'name=sitemap&amp;op=config', 'name=sitemap&amp;op=info'], 'tabs' => [_HOME, _TEMPLATE, _PREFERENCES, _INFO]]);
    $cont .= checkPerms(BASE_DIR.'/'.$file);
    $conts = is_readable($file) ? file_get_contents($file) : '';
    $f = $asize = 0;
    $acont = '';
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
        $acont .= _FILE.': '.$cfile.'<br>'._DATE.': '.date(_TIMESTRING, filemtime($cfile)).'<br>'._SIZE.': '.filterSize($size).'<br>'._URLS.': '.$n.'<br><br>';
        $f++;
        $asize += $size;
    }
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _SITEMAP.': <a href="'.$conf['homeurl'].'/'.$file.'" target="_blank" title="'._SITEMAP.'">'.$conf['homeurl'].'/'.$file.'</a><br><br>'.$acont._FILE_M.': '.$f.'<br>'._FILE_S.': '.filterSize($asize)]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_edit"><tr><td>'.textarea_code('code', '', 'sl_form', 'application/xml', str_replace('&', '&amp;', $conts)).'</td></tr>'
    .'<tr><td class="sl_center"><input type="hidden" name="name" value="sitemap"><input type="hidden" name="op" value="add"><input type="submit" value="'._UPDATE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function add(): void {
    global $afile;
    addSchedulerRun('sitemap', 'manual');
    setRedirect($afile.'.php?name=sitemap');
}

function xsledit(): void {
    global $afile;
    setHead();
    $file = SITEMAP_DIR.'/sitemap.xsl';
    $cont = setAdminNavi(['ops' => ['name=sitemap', 'name=sitemap&amp;op=xsledit', 'name=sitemap&amp;op=config', 'name=sitemap&amp;op=info'], 'tabs' => [_HOME, _TEMPLATE, _PREFERENCES, _INFO], 'tab' => 1]);
    $cont .= checkPerms($file);
    $conts = is_readable($file) ? file_get_contents($file) : '';
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => sprintf(_XSL_INFO, $file)]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_edit"><tr><td>'.textarea_code('code', 'template', 'sl_form', 'application/xml', $conts).'</td></tr>'
    .'<tr><td class="sl_center"><input type="hidden" name="name" value="sitemap"><input type="hidden" name="op" value="xslsave"><input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function xslsave(): void {
    global $afile;
    $file = SITEMAP_DIR.'/sitemap.xsl';
    $template = getVar('post', 'template', 'raw', '');
    if ($template !== '') {
        file_put_contents($file, $template);
    }
    setRedirect($afile.'.php?name=sitemap&op=xsledit');
}

function config(): void {
    global $afile, $conf;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=sitemap', 'name=sitemap&amp;op=xsledit', 'name=sitemap&amp;op=config', 'name=sitemap&amp;op=info'], 'tabs' => [_HOME, _TEMPLATE, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/sitemap.php');
    $frs = ['0' => _NO, 'always' => _ALWAYS, 'hourly' => _HOURLY, 'daily' => _DAILY, 'weekly' => _WEEKLY, 'monthly' => _MONTHLY, 'yearly' => _YEARLY, 'never' => _NEVER];
    $h = $m = $c = $popt = '';
    foreach ($frs as $key => $val) {
        $sh = (($conf['sitemap']['fr_h'] ?? '0') === (string)$key) ? ' selected' : '';
        $h .= '<option value="'.$key.'"'.$sh.'>'.$val.'</option>';
        $sm = (($conf['sitemap']['fr_m'] ?? '0') === (string)$key) ? ' selected' : '';
        $m .= '<option value="'.$key.'"'.$sm.'>'.$val.'</option>';
        $sc = (($conf['sitemap']['fr_c'] ?? '0') === (string)$key) ? ' selected' : '';
        $c .= '<option value="'.$key.'"'.$sc.'>'.$val.'</option>';
        $sp = (($conf['sitemap']['fr_p'] ?? '0') === (string)$key) ? ' selected' : '';
        $popt .= '<option value="'.$key.'"'.$sp.'>'.$val.'</option>';
    }
    $s_fr_h = '<select name="fr_h" class="sl_conf">'.$h.'</select>';
    $s_fr_m = '<select name="fr_m" class="sl_conf">'.$m.'</select>';
    $s_fr_c = '<select name="fr_c" class="sl_conf">'.$c.'</select>';
    $s_fr_p = '<select name="fr_p" class="sl_conf">'.$popt.'</select>';
    $prs = ['1.0', '0.9', '0.8', '0.7', '0.6', '0.5', '0.4', '0.3', '0.2', '0.1', '0'];
    $h = $m = $c = $popt = '';
    foreach ($prs as $val) {
        $sh = (($conf['sitemap']['pr_h'] ?? '0') === (string)$val) ? ' selected' : '';
        $h .= '<option value="'.$val.'"'.$sh.'>'.$val.'</option>';
        $sm = (($conf['sitemap']['pr_m'] ?? '0') === (string)$val) ? ' selected' : '';
        $m .= '<option value="'.$val.'"'.$sm.'>'.$val.'</option>';
        $sc = (($conf['sitemap']['pr_c'] ?? '0') === (string)$val) ? ' selected' : '';
        $c .= '<option value="'.$val.'"'.$sc.'>'.$val.'</option>';
        $sp = (($conf['sitemap']['pr_p'] ?? '0') === (string)$val) ? ' selected' : '';
        $popt .= '<option value="'.$val.'"'.$sp.'>'.$val.'</option>';
    }
    $cont .= setTemplateBasic('open');
    $cont .= setTemplateBasic('form-conf', [
        '{%route%}'       => $afile,
        '{%module%}'      => 'sitemap',
        '{%op%}'          => 'configsave',
        '{%save%}'        => _SAVECHANGES,
        '{%fields%}'      => '',
        '{%_modules%}'    => _MODULES,
        '{%_ctrlinfo%}'   => _CTRLINFO,
        '{%s_mod%}'       => modul('mod', 'sl_conf', $conf['sitemap']['mod'] ?? '', 1),
        '{%_map_fr_h%}'   => _MAP_FR_H,
        '{%_info_no%}'    => _INFO_NO,
        '{%s_fr_h%}'      => $s_fr_h,
        '{%_map_fr_m%}'   => _MAP_FR_M,
        '{%s_fr_m%}'      => $s_fr_m,
        '{%_map_fr_c%}'   => _MAP_FR_C,
        '{%s_fr_c%}'      => $s_fr_c,
        '{%_map_fr_p%}'   => _MAP_FR_P,
        '{%s_fr_p%}'      => $s_fr_p,
        '{%_map_auto_t%}' => _MAP_AUTO_T,
        '{%auto_t%}'      => intval(($conf['sitemap']['auto_t'] ?? 0) / 3600),
        '{%_map_auto%}'   => _MAP_AUTO,
        '{%r_auto%}'      => radio_form($conf['sitemap']['auto'] ?? 0, 'auto'),
        '{%_map_pr_h%}'   => _MAP_PR_H,
        '{%_info_null%}'  => _INFO_NULL,
        '{%s_pr_h%}'      => '<select name="pr_h" class="sl_conf">'.$h.'</select>',
        '{%_map_pr_m%}'   => _MAP_PR_M,
        '{%s_pr_m%}'      => '<select name="pr_m" class="sl_conf">'.$m.'</select>',
        '{%_map_pr_c%}'   => _MAP_PR_C,
        '{%s_pr_c%}'      => '<select name="pr_c" class="sl_conf">'.$c.'</select>',
        '{%_map_pr_p%}'   => _MAP_PR_P,
        '{%s_pr_p%}'      => '<select name="pr_p" class="sl_conf">'.$popt.'</select>',
        '{%_map_dat_h%}'  => _MAP_DAT_H,
        '{%r_dat_h%}'     => radio_form($conf['sitemap']['dat_h'] ?? 0, 'dat_h'),
        '{%_map_dat_m%}'  => _MAP_DAT_M,
        '{%r_dat_m%}'     => radio_form($conf['sitemap']['dat_m'] ?? 0, 'dat_m'),
        '{%_map_dat_c%}'  => _MAP_DAT_C,
        '{%r_dat_c%}'     => radio_form($conf['sitemap']['dat_c'] ?? 0, 'dat_c'),
        '{%_map_dat_p%}'  => _MAP_DAT_P,
        '{%r_dat_p%}'     => radio_form($conf['sitemap']['dat_p'] ?? 0, 'dat_p'),
        '{%_map_gen_h%}'  => _MAP_GEN_H,
        '{%r_gen_h%}'     => radio_form($conf['sitemap']['gen_h'] ?? 0, 'gen_h'),
        '{%_map_gen_m%}'  => _MAP_GEN_M,
        '{%r_gen_m%}'     => radio_form($conf['sitemap']['gen_m'] ?? 0, 'gen_m'),
        '{%_map_gen_c%}'  => _MAP_GEN_C,
        '{%r_gen_c%}'     => radio_form($conf['sitemap']['gen_c'] ?? 0, 'gen_c'),
        '{%_map_gen_p%}'  => _MAP_GEN_P,
        '{%r_gen_p%}'     => radio_form($conf['sitemap']['gen_p'] ?? 0, 'gen_p'),
        '{%_map_xsl%}'    => _MAP_XSL,
        '{%r_xsl%}'       => radio_form($conf['sitemap']['xsl'] ?? 0, 'xsl'),
        '{%_map_site%}'   => _MAP_SITE,
        '{%r_txt%}'       => radio_form($conf['sitemap']['txt'] ?? 0, 'txt'),
        'if_flag'         => ['sitemap' => true],
    ]);
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $mod = getVar('post', 'mod', 'num', []);
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
    setRedirect($afile.'.php?name=sitemap&op=config');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=sitemap', 'name=sitemap&amp;op=xsledit', 'name=sitemap&amp;op=config', 'name=sitemap&amp;op=info'], 'tabs' => [_HOME, _TEMPLATE, _PREFERENCES, _INFO], 'tab' => 3]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
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
