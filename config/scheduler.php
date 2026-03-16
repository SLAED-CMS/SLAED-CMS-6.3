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
                'active' => '0',
                'handler' => 'addSchedulerBackup',
                'schedule' => '30 3 * * *',
                'priority' => '50',
                'lock_timeout' => '1800',
                'manual' => '1',
                'settings' => [
                ],
            ],
            'filescan' => [
                'title' => 'File scan',
                'type' => 'system',
                'active' => '1',
                'handler' => 'addSchedulerFilescan',
                'schedule' => '0 3 * * *',
                'priority' => '40',
                'lock_timeout' => '1800',
                'manual' => '1',
                'settings' => [
                ],
            ],
            'newsletter' => [
                'title' => 'Newsletter',
                'type' => 'system',
                'active' => '0',
                'handler' => 'addSchedulerNewsletter',
                'schedule' => '* * * * *',
                'priority' => '20',
                'lock_timeout' => '900',
                'manual' => '1',
                'settings' => [
                ],
            ],
            'sitemap' => [
                'title' => 'Sitemap',
                'type' => 'system',
                'active' => '0',
                'handler' => 'addSchedulerSitemap',
                'schedule' => '15 3 * * *',
                'priority' => '30',
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
