<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

return [
    'node' => [
        'version' => '1',
        'limits' => [
            'maxassets' => 100,
            'maxlist' => 100,
            'syncbatch' => 500,
        ],
        'support' => [
            'state' => [
                'staff' => 0,
                'author' => 1,
                'closed' => 2,
            ],
            'prio' => [
                'low' => 0,
                'normal' => 1,
                'high' => 2,
                'urgent' => 3,
            ],
        ],
        'defaults' => [
            'list' => [
                'orders' => [
                    0 => 'published',
                ],
                'order' => 'published',
                'dir' => 'desc',
                'limit' => 10,
                'alpha' => false,
                'show' => [
                    0 => 'category',
                    1 => 'author',
                    2 => 'date',
                    3 => 'views',
                ],
            ],
            'view' => [
                'mode' => 'default',
            ],
            'form' => [
            ],
            'workflow' => [
                'access' => 'user',
                'groups' => [
                ],
                'publish' => [
                ],
                'notify' => [
                    'pending' => true,
                    'result' => true,
                ],
            ],
            'admin' => [
            ],
            'features' => [
            ],
            'assets' => [
            ],
            'integrations' => [
                'search' => false,
                'rss' => false,
                'sitemap' => false,
                'blocks' => false,
                'seo' => 'website',
            ],
        ],
        'types' => [
        ],
    ],
];
