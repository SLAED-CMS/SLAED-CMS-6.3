<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

function getAdminHeadVars(): array {
    global $conf, $db, $admin, $afile, $tpl;
    $langs = $menu = $blocks = $login = '';
    if (isAdmin()) {
        if ($conf['multilingual'] == 1) {
            foreach (scandir(BASE_DIR.'/lang') as $file) {
                if (preg_match('#^(.+)\.php$#', $file, $matches)) {
                    $lfound = $matches[1];
                    $langs .= $tpl->getHtmlFrag('admin-lang-item', [
                        'href' => $afile.'.php?newlang='.$lfound,
                        'src' => img_find('lang/'.$lfound.'_mini.png'),
                        'alt' => getLangName($lfound),
                    ]);
                }
            }
        }
        if (!isAdmin(true)) {
            $items = [
                ['cls' => 'sl_first', 'href' => '#', 'label' => _HELLO.', '.substr($admin[1], 0, 25).'!', 'blank' => false],
                ['cls' => '', 'href' => $afile.'.php', 'label' => _HOME, 'blank' => false],
                ['cls' => '', 'href' => 'index.php', 'label' => _SITE, 'blank' => true],
                ['cls' => '', 'href' => 'index.php?name=account', 'label' => _ACCOUNT, 'blank' => true],
                ['cls' => '', 'href' => $afile.'.php?op=logout', 'label' => _LOGOUT, 'blank' => false],
            ];
        } else {
            $items = [
                ['cls' => 'sl_first', 'href' => $afile.'.php', 'label' => _HOME, 'blank' => false],
                ['cls' => '', 'href' => $afile.'.php?name=blocks', 'label' => _BLOCKS, 'blank' => false],
                ['cls' => '', 'href' => $afile.'.php?name=modules', 'label' => _MODULES, 'blank' => false],
                ['cls' => '', 'href' => $afile.'.php?name=categories', 'label' => _CATEGORIES, 'blank' => false],
                ['cls' => '', 'href' => 'index.php', 'label' => _SITE, 'blank' => true],
                ['cls' => '', 'href' => 'index.php?name=account', 'label' => _ACCOUNT, 'blank' => true],
                ['cls' => '', 'href' => $afile.'.php?op=logout', 'label' => _LOGOUT, 'blank' => false],
            ];
        }
        foreach ($items as $item) {
            $menu .= $tpl->getHtmlFrag('admin-menu-item', $item);
        }
        $blocks = getAdminPanelBlocks().admininfo().adminblock();
    } else {
        $login = ($db->getSqlRowCount($db->getSqlQuery('SELECT 1 FROM '.PREFIX_DB.'_admins LIMIT 1')) == 0) ? _ADMINLOGIN_NEW : _ADMINLOGIN;
    }
    return [
        'admin_langs' => $langs,
        'menu' => $menu,
        'admin_blocks' => $blocks,
        'login' => $login,
    ];
}

function getThemeFootVars(): array {
    return ['upper' => _PAGETOP];
}
