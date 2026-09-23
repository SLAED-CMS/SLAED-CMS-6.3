<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
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
    if ($type !== 1 || $m_title === 'node') continue;
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
# The registered Node types stand beside the modules under their public names: an active type for everyone, a disabled one for those the registry hands it to
foreach (getNodeTypeMap() as $tname => $ntype) {
    $label = getModuleName($tname);
    $link = $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$tname, 'title' => $label, 'label' => $label, 'is_module' => true]);
    if ($ntype->active) $mods_1 .= $link;
    else $mods_3 .= $link;
}
$mods_2 = ($mods_2) ? $tpl->getHtmlFrag('block-content', ['is_block_module_section' => true, 'content' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'is_line_break' => true, 'text' => _INVISIBLEMODULES])._ACTIVEBUTNOTSEE]).$mods_2 : '';
$mods_3 = ($mods_3) ? $tpl->getHtmlFrag('block-content', ['is_block_module_section' => true, 'content' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'is_line_break' => true, 'text' => _NOACTIVEMODULES])._FORADMINTESTS]).$mods_3 : '';
$mods_4 = ($mods_4) ? $tpl->getHtmlFrag('block-content', ['is_block_module_section' => true, 'content' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'is_line_break' => true, 'text' => _ADMINS])._FORADMINTESTS]).$mods_4 : '';
$content = $tpl->getHtmlFrag('block-content', ['is_block_modules' => true, 'content' => $mods_1.$mods_2.$mods_3.$mods_4]);
