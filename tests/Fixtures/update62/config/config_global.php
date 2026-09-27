<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The global configuration of the 6.2 site of tests/Fixtures/update62/site.sql in the variable form 6.2 wrote, reduced to the keys the update has to carry or drop
if (!defined('FUNC_FILE')) die('Illegal file access');

$conf = [
    'adminmail' => 'admin@site62.test',
    'amod' => 'news',
    'css_f' => 'templates/default/css/system.css',
    'homeurl' => 'https://site62.test',
    'language' => 'russian',
    'module' => 'news',
    'sitename' => 'Site 62',
    'theme' => 'default',
    'version' => '6.2.0',
];
