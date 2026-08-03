<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

return [
    'scheduler' => [
        'active' => '1',
        'cron_timeout' => '600',
        'jobs' => [
            'cachegc' => [
                'title' => 'Page cache cleanup',
                'type' => 'system',
                'active' => '0',
                'system' => 'cachegc',
                'schedule' => '45 3 * * *',
                'priority' => '5',
                'lock_timeout' => '600',
                'manual' => '1',
                'settings' => [
                ],
            ],
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
                    'include' => '*',
                    'exclude' => 'ipb_*',
                    'schemaonly' => 'MRG_MyISAM,MERGE,HEAP,MEMORY',
                    'compress' => 'auto',
                    'keep' => '0',
                    'allow_incomplete' => '0',
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
        'maildrain' => [
                'title' => 'Mail delivery',
                'type' => 'system',
                'active' => '1',
                'system' => 'maildrain',
                'schedule' => '*/5 * * * *',
                'priority' => '2',
                'lock_timeout' => '900',
                'manual' => '1',
                'settings' => [
                ],
            ],
            'newsletter' => [
                'title' => 'Newsletter',
                'type' => 'system',
                'active' => '1',
                'system' => 'newsletter',
                'schedule' => '*/5 * * * *',
                'priority' => '1',
                'lock_timeout' => '900',
                'manual' => '1',
                'settings' => [
                ],
            ],
            'sitemap' => [
                'title' => 'Sitemap',
                'type' => 'system',
                'active' => '1',
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
