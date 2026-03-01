<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('sitemap')) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=sitemap', 'name=sitemap&amp;op=xsl', 'name=sitemap&amp;op=conf', 'name=sitemap&amp;op=info'];
    $lang = [_HOME, _TEMPLATE, _PREFERENCES, _INFO];
    return getAdminTabs(_SITEMAP, 'sitemap.png', '', $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function sitemap(): void {
    global $afile, $conf;
    setHead();
    $file = 'sitemap.xml';
    $cont = navi(0, 0, 0, 0);
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
        $acont .= _FILE.': '.$cfile .'<br>'._DATE.': '.date(_TIMESTRING, filemtime($cfile)).'<br>'._SIZE.': '.files_size($size).'<br>'._URLS.': '.$n.'<br><br>';
        $f++;
        $asize += $size;
    }
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _SITEMAP.': <a href="'.$conf['homeurl'].'/'.$file.'" target="_blank" title="'._SITEMAP.'">'.$conf['homeurl'].'/'.$file.'</a><br><br>'.$acont._FILE_M.': '.$f.'<br>'._FILE_S.': '.files_size($asize)]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_edit"><tr><td>'.textarea_code('code', '', 'sl_form', 'application/xml', str_replace('&', '&amp;', $conts)).'</td></tr>'
    .'<tr><td class="sl_center"><input type="hidden" name="name" value="sitemap"><input type="hidden" name="op" value="add"><input type="submit" value="'._UPDATE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function add(): void {
    global $afile;
    doSitemap();
    setRedirect($afile.'.php?name=sitemap');
}

function xsl(): void {
    global $afile;
    setHead();
    $file = SITEMAP_DIR.'/sitemap.xsl';
    $cont = navi(0, 1, 0, 0);
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
    setRedirect($afile.'.php?name=sitemap&op=xsl');
}

function conf(): void {
    global $afile, $conf;
        setHead();
    $cont = navi(0, 2, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/sitemap.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._MODULES.':<div class="sl_small">'._CTRLINFO.'</div></td><td>'.modul('mod', 'sl_conf', $conf['sitemap']['mod'] ?? '', 1).'</td></tr>';
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
    $cont .= '<tr><td>'._MAP_FR_H.':<div class="sl_small">'._INFO_NO.'</div></td><td><select name="fr_h" class="sl_conf">'.$h.'</select></td></tr>'
    .'<tr><td>'._MAP_FR_M.':<div class="sl_small">'._INFO_NO.'</div></td><td><select name="fr_m" class="sl_conf">'.$m.'</select></td></tr>'
    .'<tr><td>'._MAP_FR_C.':<div class="sl_small">'._INFO_NO.'</div></td><td><select name="fr_c" class="sl_conf">'.$c.'</select></td></tr>'
    .'<tr><td>'._MAP_FR_P.':<div class="sl_small">'._INFO_NO.'</div></td><td><select name="fr_p" class="sl_conf">'.$popt.'</select></td></tr>';
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
    $cont .= '<tr><td>'._MAP_PR_H.':<div class="sl_small">'._INFO_NULL.'</div></td><td><select name="pr_h" class="sl_conf">'.$h.'</select></td></tr>'
    .'<tr><td>'._MAP_PR_M.':<div class="sl_small">'._INFO_NULL.'</div></td><td><select name="pr_m" class="sl_conf">'.$m.'</select></td></tr>'
    .'<tr><td>'._MAP_PR_C.':<div class="sl_small">'._INFO_NULL.'</div></td><td><select name="pr_c" class="sl_conf">'.$c.'</select></td></tr>'
    .'<tr><td>'._MAP_PR_P.':<div class="sl_small">'._INFO_NULL.'</div></td><td><select name="pr_p" class="sl_conf">'.$popt.'</select></td></tr>'
    .'<tr><td>'._MAP_AUTO_T.':</td><td><input type="number" name="auto_t" value="'.(int)(($conf['sitemap']['auto_t'] ?? 0) / 3600).'" class="sl_conf" placeholder="'._MAP_AUTO_T.'" required></td></tr>'
    .'<tr><td>'._MAP_AUTO.'</td><td>'.radio_form($conf['sitemap']['auto'] ?? 0, 'auto').'</td></tr>'
    .'<tr><td>'._MAP_DAT_H.'</td><td>'.radio_form($conf['sitemap']['dat_h'] ?? 0, 'dat_h').'</td></tr>'
    .'<tr><td>'._MAP_DAT_M.'</td><td>'.radio_form($conf['sitemap']['dat_m'] ?? 0, 'dat_m').'</td></tr>'
    .'<tr><td>'._MAP_DAT_C.'</td><td>'.radio_form($conf['sitemap']['dat_c'] ?? 0, 'dat_c').'</td></tr>'
    .'<tr><td>'._MAP_DAT_P.'</td><td>'.radio_form($conf['sitemap']['dat_p'] ?? 0, 'dat_p').'</td></tr>'
    .'<tr><td>'._MAP_GEN_H.'</td><td>'.radio_form($conf['sitemap']['gen_h'] ?? 0, 'gen_h').'</td></tr>'
    .'<tr><td>'._MAP_GEN_M.'</td><td>'.radio_form($conf['sitemap']['gen_m'] ?? 0, 'gen_m').'</td></tr>'
    .'<tr><td>'._MAP_GEN_C.'</td><td>'.radio_form($conf['sitemap']['gen_c'] ?? 0, 'gen_c').'</td></tr>'
    .'<tr><td>'._MAP_GEN_P.'</td><td>'.radio_form($conf['sitemap']['gen_p'] ?? 0, 'gen_p').'</td></tr>'
    .'<tr><td>'._MAP_XSL.'</td><td>'.radio_form($conf['sitemap']['xsl'] ?? 0, 'xsl').'</td></tr>'
    .'<tr><td>'._MAP_SITE.'</td><td>'.radio_form($conf['sitemap']['txt'] ?? 0, 'txt').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="sitemap"><input type="hidden" name="op" value="saveconf"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function saveconf(): void {
    global $afile;
    $mod = getVar('post', 'mod', 'num', []);
    $cont = [
        'mod' => empty($mod[0]) ? '0' : implode(',', $mod),
        'fr_h' => getVar('post', 'fr_h', 'var', '0'),
        'fr_m' => getVar('post', 'fr_m', 'var', '0'),
        'fr_c' => getVar('post', 'fr_c', 'var', '0'),
        'fr_p' => getVar('post', 'fr_p', 'var', '0'),
        'pr_h' => getVar('post', 'pr_h', 'var', '0'),
        'pr_m' => getVar('post', 'pr_m', 'var', '0'),
        'pr_c' => getVar('post', 'pr_c', 'var', '0'),
        'pr_p' => getVar('post', 'pr_p', 'var', '0'),
        'auto_t' => getVar('post', 'auto_t', 'num', 1) * 3600,
        'auto' => getVar('post', 'auto', 'num', 0),
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
    setRedirect($afile.'.php?name=sitemap&op=conf');
}

function info(): void {
    setHead();
    echo navi(0, 3, 0, 0).'<div id="repadm_info">'.adm_info(1, 'sitemap', 0).'</div>';
    setFoot();
}

switch ($op) {
    default: sitemap(); break;
    case 'add': add(); break;
    case 'xsl': xsl(); break;
    case 'xslsave': xslsave(); break;
    case 'conf': conf(); break;
    case 'saveconf': saveconf(); break;
    case 'info': info(); break;
}




