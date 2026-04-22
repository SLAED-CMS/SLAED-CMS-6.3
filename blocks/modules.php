<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $conf, $tpl;
$mods_1 = $tpl->getHtmlFrag('link', ['href' => 'index.php', 'title' => _HOME, 'label' => _HOME, 'is_module' => true]);
$mods_2 = '';
$mods_3 = '';
$mods_4 = '';
$mod_list = $conf['modules'];
ksort($mod_list);
foreach ($mod_list as $m_title => $info) {
    $type = (int)($info['type'] ?? 1);
    if ($type !== 1) continue;
    $view = (int)($info['view'] ?? 0);
    $active = (int)($info['active'] ?? 0);
    $inmenu = (int)($info['menu'] ?? 1);
    $m_title2 = getModuleName($m_title);
    if ($inmenu == 1 && $active == 1 && $view != 2) {
        if ((is_moder($m_title) && $view == 2) || $view != 2) {
            $mods_1 .= $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$m_title, 'title' => $m_title2, 'label' => $m_title2, 'is_module' => true]);
        }
    } elseif (is_moder($m_title) && $inmenu == 0 && $active == 1) {
        $mods_2 .= $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$m_title, 'title' => $m_title2, 'label' => $m_title2, 'is_module' => true]);
    } elseif (is_moder($m_title) && $active == 0) {
        $mods_3 .= $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$m_title, 'title' => $m_title2, 'label' => $m_title2, 'is_module' => true]);
    } elseif (is_moder($m_title) && $view == 2) {
        $mods_4 .= $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$m_title, 'title' => $m_title2, 'label' => $m_title2, 'is_module' => true]);
    }
}
$mods_2 = ($mods_2) ? $tpl->getHtmlFrag('content-block', ['class' => 'block-mod-section', 'content' => '<b>'._INVISIBLEMODULES.'</b><br>'._ACTIVEBUTNOTSEE]).$mods_2 : '';
$mods_3 = ($mods_3) ? $tpl->getHtmlFrag('content-block', ['class' => 'block-mod-section', 'content' => '<b>'._NOACTIVEMODULES.'</b><br>'._FORADMINTESTS]).$mods_3 : '';
$mods_4 = ($mods_4) ? $tpl->getHtmlFrag('content-block', ['class' => 'block-mod-section', 'content' => '<b>'._ADMINS.'</b><br>'._FORADMINTESTS]).$mods_4 : '';
$content = $tpl->getHtmlFrag('content-block', ['class' => 'block-modules', 'content' => $mods_1.$mods_2.$mods_3.$mods_4]);
