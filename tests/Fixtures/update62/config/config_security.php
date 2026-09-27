<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The security configuration of the 6.2 site of tests/Fixtures/update62/site.sql; the update probe names the panel file myadm before it runs
if (!defined('FUNC_FILE')) die('Illegal file access');

$confs = [
    'admin_ip' => '',
    'afile' => 'admin',
    'block' => '1',
    'error' => '2',
    'error_log' => '1',
    'password' => '',
    'sess_b' => '86400',
    'sess_d' => '86400',
];
