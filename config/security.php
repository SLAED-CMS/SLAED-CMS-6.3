<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

return [
    'security' => [
        'admin_ip' => '',
        'afile' => 'admin',
        'block' => '0',
        'blocker_cookie' => 'banned',
        'blocker_ip' => '',
        'blocker_user' => '',
        'captcha' => [
            'active' => '0',
            'provider' => 'altcha',
            'register' => '1',
            'contact' => '1',
            'comments' => '1',
            'login_user' => 'after-fail',
            'login_admin' => 'always',
            'ttl' => '600',
            'difficulty' => 'normal',
            'storage' => 'file',
        ],
        'dump_skip' => '.git/
vendor/
tests/',
        'error' => '2',
        'error_log' => '1',
        'flood' => '0',
        'flood_t' => '1',
        'log' => '0',
        'log_a' => '1',
        'log_b' => '1',
        'log_d' => '1',
        'log_size' => '10485760',
        'log_u' => '0',
        'login' => '',
        'mail' => '1',
        'mail_d' => '1',
        'mail_w' => '0',
        'password' => '',
        'ref_post' => '1',
        'secret' => '',
        'sess_b' => '86400',
        'sess_d' => '86400',
        'url_get' => '1',
        'url_post' => '0',
        'write_h' => '1',
        'write_w' => '0',
    ],
];
