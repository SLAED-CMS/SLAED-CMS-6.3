<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $tpl;
$path = UPLOADS_DIR.'/presentation/sites/thumb';
$ban = [];
$dir = opendir($path);
if ($dir) {
    while (false !== ($file = readdir($dir))) {
        if ($file != '.' && $file != '..' && !is_dir($path.'/'.$file)) $ban[] = $file;
    }
    closedir($dir);
}

$cont = '';
if ($ban !== []) {
    $list = (count($ban) > 1) ? array_rand($ban, count($ban)) : array_keys($ban);
    shuffle($list);
    foreach ($list as $val) {
        $img = ($cont === '') ? getUploadUrl('presentation/sites/thumb/'.$ban[$val]) : '';
        $cont .= $tpl->getHtmlFrag('link', [
            'title' => 'Лучшие сайты системы',
            'href' => getUploadUrl('presentation/sites/'.$ban[$val]),
            'img_src' => $img,
            'img_alt' => 'Лучшие сайты системы',
        ]);
    }
}
$content = $tpl->getHtmlFrag('block-content', ['content' => $cont]);
