<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function sitemap(): void {
    global $tpl;
    $path = SITEMAP_DIR.'/sitemap.txt';
    setHead(['title' => _SITEMAP, 'kind' => 'collection']);
    $cont = $tpl->getHtmlFrag('title', ['title' => _SITEMAP, 'is_level_one' => true]);
    if (is_readable($path)) {
        $map = file_get_contents($path);
        $map = ($map !== false) ? trim($map) : '';
        $cont .= ($map !== '') ? $tpl->getHtmlFrag('block-content', ['content' => $map]) : $tpl->getHtmlFrag('empty', []);
    } else {
        $cont .= $tpl->getHtmlFrag('empty', []);
    }
    echo $cont;
    setFoot();
}

switch ($op) {
    default: sitemap(); break;
}
