<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $confmd;
$mods_1 = '<tr><td><a href="index.php" title="'._HOME.'" class="sl_modul">'._HOME.'</a></td></tr>';
$mods_2 = '';
$mods_3 = '';
$mods_4 = '';
$mod_list = $confmd;
ksort($mod_list);
foreach ($mod_list as $m_title => $info) {
    $type = (int)($info['type'] ?? 1);
    if ($type !== 1) continue;
    $view = (int)($info['view'] ?? 0);
    $active = (int)($info['active'] ?? 0);
    $inmenu = (int)($info['menu'] ?? 1);
    $m_title2 = deflmconst($m_title);
    if ($inmenu == 1 && $active == 1 && $view != 2) {
        if ((is_moder($m_title) && $view == 2) || $view != 2) {
            $mods_1 .= '<tr><td><a href="index.php?name='.$m_title.'" title="'.$m_title2.'" class="sl_modul">'.$m_title2.'</a></td></tr>';
        }
    } elseif (is_moder($m_title) && $inmenu == 0 && $active == 1) {
        $mods_2 .= '<tr><td><a href="index.php?name='.$m_title.'" class="sl_modul">'.$m_title2.'</a></td></tr>';
    } elseif (is_moder($m_title) && $active == 0) {
        $mods_3 .= '<tr><td><a href="index.php?name='.$m_title.'" class="sl_modul">'.$m_title2.'</a></td></tr>';
    } elseif (is_moder($m_title) && $view == 2) {
        $mods_4 .= '<tr><td><a href="index.php?name='.$m_title.'" class="sl_modul">'.$m_title2.'</a></td></tr>';
    }
}
$mods_2 = ($mods_2) ? '<tr><td><b>'._INVISIBLEMODULES.'</b><br>'._ACTIVEBUTNOTSEE.'</td></tr>'.$mods_2 : '';
$mods_3 = ($mods_3) ? '<tr><td><b>'._NOACTIVEMODULES.'</b><br>'._FORADMINTESTS.'</td></tr>'.$mods_3 : '';
$mods_4 = ($mods_4) ? '<tr><td><b>'._ADMINS.'</b><br>'._FORADMINTESTS.'</td></tr>'.$mods_4 : '';
$content = '<table class="sl_table_block">'.$mods_1.$mods_2.$mods_3.$mods_4.'</table>';
