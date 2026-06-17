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
$handle = opendir(BASE_DIR.'/lang');
while (false !== ($file = readdir($handle))) {
    if (preg_match("/^(.+)\.php/", $file, $matches)) {
        $langlist[] = $matches[1];
    }
}
closedir($handle);
sort($langlist);
if ($conf['flags'] == 1) {
    $cont = '';
    for ($i = 0; $i < count($langlist); $i++) {
        if ($langlist[$i] != '') {
            $altlang = getLangName($langlist[$i]);
            $cont .= $tpl->getHtmlFrag('link', [
                'href' => 'index.php?newlang='.$langlist[$i],
                'title' => $altlang,
                'img_src' => getLanguageFlagSrc($langlist[$i]),
                'img_alt' => $altlang,
            ]);
        }
    }
    $content = $tpl->getHtmlFrag('block-content', ['is_block_flags' => true, 'content' => $cont]);
} else {
    $opts = '';
    for ($i = 0; $i < count($langlist); $i++) {
        if ($langlist[$i] != '') {
            $opts .= $tpl->getHtmlFrag('select-option', [
                'value_attr' => 'index.php?newlang='.$langlist[$i],
                'label_text' => getLangName($langlist[$i]),
                'is_selected' => $langlist[$i] == $locale,
            ]);
        }
    }
    $sel = $tpl->getHtmlFrag('select', ['name_attr' => 'newlang', 'select_attr' => 'onchange="location.href=this.value"', 'options_html' => $opts]);
    $content = $tpl->getHtmlPart('form-wrap', [
        'action' => 'index.php',
        'method' => 'get',
        'is_block_languages' => true,
        'content_html' => $sel,
    ]);
}
