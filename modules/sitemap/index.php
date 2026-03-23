<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function sitemap(): void {
    global $conf, $tpl;
    $path = SITEMAP_DIR.'/sitemap.txt';
    setHead(['title' => _SITEMAP]);
    $cont = $tpl->getHtmlFrag('title', ['title' => _SITEMAP]);
    if (is_readable($path)) {
        $map = file_get_contents($path);
        $cont .= ($map !== false ? $map : '');
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

switch ($op) {
    default: sitemap(); break;
}
