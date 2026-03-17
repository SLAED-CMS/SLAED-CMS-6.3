<?php
# Author: Eduard Laas
# Copyright (c) 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

return [
    'scheduler' => [
        'active' => '1',
        'cron_timeout' => '600',
        'jobs' => [
            'dbbackup' => [
                'title' => 'Database backup',
                'type' => 'system',
                'active' => '1',
                'system' => 'backup',
                'schedule' => '30 3 * * *',
                'priority' => '4',
                'lock_timeout' => '1800',
                'manual' => '1',
                'settings' => [
                ],
            ],
            'filescan' => [
                'title' => 'File scan',
                'type' => 'system',
                'active' => '1',
                'system' => 'filescan',
                'schedule' => '0 3 * * *',
                'priority' => '2',
                'lock_timeout' => '1800',
                'manual' => '1',
                'settings' => [
                ],
            ],
            'newsletter' => [
                'title' => 'Newsletter',
                'type' => 'system',
                'active' => '0',
                'system' => 'newsletter',
                'schedule' => '1 * * * *',
                'priority' => '1',
                'lock_timeout' => '900',
                'manual' => '1',
                'settings' => [
                ],
            ],
            'sitemap' => [
                'title' => 'Sitemap',
                'type' => 'system',
                'active' => '0',
                'system' => 'sitemap',
                'schedule' => '15 3 * * *',
                'priority' => '3',
                'lock_timeout' => '1800',
                'manual' => '1',
                'settings' => [
                ],
            ],
        ],
        'lock_timeout' => '1800',
        'pseudo' => '1',
        'token' => '',
        'trigger_cooldown' => '60',
    ],
];
