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
            'send' => 60,
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
            'content' => [
                'version' => 2,
                'list' => [
                    'orders' => [
                        0 => 'published',
                        1 => 'updated',
                        2 => 'title',
                        3 => 'views',
                        4 => 'rating',
                    ],
                ],
                'view' => [
                    'mode' => 'article',
                ],
                'features' => [
                    'categories' => true,
                    'comments' => true,
                    'rating' => true,
                    'favorites' => true,
                    'poll' => false,
                    'home' => false,
                    'pinned' => true,
                    'submit' => false,
                    'moderation' => false,
                    'schedule' => true,
                    'related' => true,
                    'tree' => false,
                ],
                'integrations' => [
                    'search' => true,
                    'rss' => true,
                    'sitemap' => true,
                    'blocks' => true,
                    'seo' => 'article',
                ],
            ],
            'docs' => [
                'version' => 2,
                'list' => [
                    'orders' => [
                        0 => 'updated',
                        1 => 'title',
                        2 => 'views',
                    ],
                    'order' => 'title',
                    'dir' => 'asc',
                    'limit' => 50,
                    'alpha' => true,
                    'show' => [
                        0 => 'category',
                        1 => 'date',
                        2 => 'views',
                    ],
                ],
                'view' => [
                    'mode' => 'docs',
                ],
                'features' => [
                    'categories' => true,
                    'comments' => false,
                    'rating' => false,
                    'favorites' => true,
                    'poll' => false,
                    'home' => false,
                    'pinned' => false,
                    'submit' => false,
                    'moderation' => false,
                    'schedule' => false,
                    'related' => true,
                    'tree' => true,
                ],
                'integrations' => [
                    'search' => true,
                    'sitemap' => true,
                    'blocks' => true,
                    'seo' => 'article',
                ],
            ],
            'jokes' => [
                'version' => 2,
                'list' => [
                    'orders' => [
                        0 => 'published',
                        1 => 'updated',
                        2 => 'title',
                        3 => 'views',
                        4 => 'rating',
                    ],
                ],
                'features' => [
                    'categories' => true,
                    'comments' => true,
                    'rating' => true,
                    'favorites' => true,
                    'poll' => false,
                    'home' => true,
                    'pinned' => true,
                    'submit' => true,
                    'moderation' => true,
                    'schedule' => true,
                    'related' => true,
                    'tree' => false,
                ],
                'integrations' => [
                    'search' => true,
                    'rss' => true,
                    'sitemap' => true,
                    'blocks' => true,
                    'seo' => 'article',
                ],
            ],
            'media' => [
                'version' => 2,
                'list' => [
                    'orders' => [
                        0 => 'published',
                        1 => 'updated',
                        2 => 'title',
                        3 => 'views',
                        4 => 'rating',
                    ],
                    'limit' => 25,
                    'alpha' => true,
                ],
                'view' => [
                    'mode' => 'media',
                ],
                'features' => [
                    'categories' => true,
                    'comments' => true,
                    'rating' => true,
                    'favorites' => true,
                    'poll' => false,
                    'home' => true,
                    'pinned' => true,
                    'submit' => true,
                    'moderation' => true,
                    'schedule' => true,
                    'related' => true,
                    'tree' => false,
                ],
                'assets' => [
                    'poster' => [
                        'title' => '_NODE_POSTER',
                        'kinds' => [
                            0 => 'image',
                        ],
                        'max' => 1,
                        'mode' => 'none',
                        'sort' => 10,
                    ],
                    'source' => [
                        'title' => '_NODE_PLAY',
                        'kinds' => [
                            0 => 'audio',
                            1 => 'video',
                        ],
                        'min' => 1,
                        'max' => 10,
                        'canlink' => true,
                        'mode' => 'player',
                        'sort' => 20,
                    ],
                    'gallery' => [
                        'title' => '_ALBUM',
                        'kinds' => [
                            0 => 'image',
                        ],
                        'max' => 30,
                        'mode' => 'gallery',
                        'sort' => 30,
                    ],
                    'download' => [
                        'title' => '_DOWNLOAD',
                        'kinds' => [
                            0 => 'file',
                            1 => 'image',
                            2 => 'audio',
                            3 => 'video',
                        ],
                        'max' => 10,
                        'canlink' => true,
                        'report' => true,
                        'mode' => 'download',
                        'sort' => 40,
                    ],
                ],
                'integrations' => [
                    'search' => true,
                    'rss' => true,
                    'sitemap' => true,
                    'blocks' => true,
                    'seo' => 'article',
                ],
            ],
        ],
    ],
];
