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
    global $conf;
    $path = SITEMAP_DIR.'/sitemap.txt';
    setHead(['title' => _SITEMAP]);
    $cont = setTemplateBasic('title', ['{%title%}' => _SITEMAP]);
    if (file_exists($path)) {
        $cont .= setTemplateBasic('open').file_get_contents($path).setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

switch ($op) {
    default: sitemap(); break;
}
