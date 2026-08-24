<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $conf, $locale, $tpl;
$langlist = [];
foreach (scandir(BASE_DIR.'/lang') ?: [] as $file) {
    if (preg_match('#^(.+)\.php$#', $file, $part)) $langlist[] = $part[1];
}
sort($langlist);
$hide = '';
foreach (['op' => 'newlang', 'refer' => '1', 'token' => getPageToken('newlang')] as $key => $val) {
    $hide .= $tpl->getHtmlFrag('hidden', ['name_attr' => $key, 'value_attr' => $val, 'input_attr' => '']);
}
if ($conf['flags'] == 1) {
    $langs = [];
    foreach ($langlist as $lang) {
        if ($lang === '') continue;
        $langs[] = ['key' => $lang, 'title' => getLangName($lang), 'img_src' => getLanguageFlagSrc($lang)];
    }
    $cont = $tpl->getHtmlFrag('lang-switch', ['action' => 'index.php', 'hidden' => $hide, 'langs' => $langs]);
    $content = $tpl->getHtmlFrag('block-content', ['is_block_flags' => true, 'content' => $cont]);
} else {
    $opts = '';
    foreach ($langlist as $lang) {
        if ($lang === '') continue;
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $lang,
            'label_text' => getLangName($lang),
            'is_selected' => $lang == $locale,
        ]);
    }
    $sel = $tpl->getHtmlFrag('select', ['name_attr' => 'newlang', 'select_attr' => 'onchange="this.form.submit()"', 'options_html' => $opts]);
    $content = $tpl->getHtmlPart('form-wrap', [
        'action' => 'index.php',
        'method' => 'post',
        'is_block_languages' => true,
        'content_html' => $hide.$sel,
    ]);
}
