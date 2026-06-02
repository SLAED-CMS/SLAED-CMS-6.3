<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

return [
    'security' => [
        'admin_ip' => '',
        'afile' => 'admin',
        'block' => '1',
        'blocker_cookie' => 'banned',
        'blocker_ip' => '192.0.2.0/24|dummy-c|1778015715|test ip ban C||198.51.100.1/32|fan-ip-01|1778015994|fan ip ban 01||198.51.100.2/32|fan-ip-02|1778015994|fan ip ban 02||198.51.100.3/32|fan-ip-03|1778015994|fan ip ban 03||198.51.100.4/32|fan-ip-04|1778015994|fan ip ban 04||198.51.100.5/32|fan-ip-05|1778015994|fan ip ban 05||198.51.100.6/32|fan-ip-06|1778015994|fan ip ban 06||198.51.100.7/32|fan-ip-07|1778015994|fan ip ban 07||198.51.100.8/32|fan-ip-08|1778015995|fan ip ban 08||198.51.100.9/32|fan-ip-09|1778015995|fan ip ban 09||198.51.100.10/32|fan-ip-10|1778015995|fan ip ban 10||198.51.100.11/32|fan-ip-11|1778015995|fan ip ban 11||198.51.100.12/32|fan-ip-12|1778015995|fan ip ban 12||198.51.100.13/32|fan-ip-13|1778015995|fan ip ban 13||198.51.100.14/32|fan-ip-14|1778015995|fan ip ban 14||198.51.100.15/32|fan-ip-15|1778015995|fan ip ban 15||198.51.100.16/32|fan-ip-16|1778015995|fan ip ban 16||198.51.100.17/32|fan-ip-17|1778015995|fan ip ban 17||198.51.100.18/32|fan-ip-18|1778015995|fan ip ban 18||198.51.100.19/32|fan-ip-19|1778015995|fan ip ban 19||198.51.100.20/32|fan-ip-20|1778015995|fan ip ban 20||198.51.100.21/32|fan-ip-21|1778015995|fan ip ban 21||198.51.100.22/32|fan-ip-22|1778015995|fan ip ban 22||198.51.100.23/32|fan-ip-23|1778015995|fan ip ban 23||198.51.100.24/32|fan-ip-24|1778015995|fan ip ban 24||198.51.100.25/32|fan-ip-25|1778015995|fan ip ban 25||',
        'blocker_user' => 'fanuser01|1778015995|fan user ban 01||fanuser02|1778015995|fan user ban 02||fanuser03|1778015995|fan user ban 03||fanuser04|1778015995|fan user ban 04||fanuser05|1778015995|fan user ban 05||fanuser06|1778015995|fan user ban 06||fanuser07|1778015995|fan user ban 07||fanuser08|1778015995|fan user ban 08||fanuser09|1778015995|fan user ban 09||fanuser10|1778015995|fan user ban 10||fanuser11|1778015995|fan user ban 11||fanuser12|1778015995|fan user ban 12||fanuser13|1778015995|fan user ban 13||fanuser14|1778015996|fan user ban 14||fanuser15|1778015996|fan user ban 15||fanuser16|1778015996|fan user ban 16||fanuser17|1778015996|fan user ban 17||fanuser18|1778015996|fan user ban 18||fanuser19|1778015996|fan user ban 19||fanuser20|1778015996|fan user ban 20||fanuser21|1778015996|fan user ban 21||fanuser22|1778015996|fan user ban 22||fanuser23|1778015996|fan user ban 23||fanuser24|1778015996|fan user ban 24||fanuser25|1778015996|fan user ban 25||test|1775510434|sfsdfsdfsd||test|1776288072|wqerqweqweqw||',
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
        'log_b' => '0',
        'log_d' => '0',
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
