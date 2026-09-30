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
                'version' => 5,
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
                'version' => 3,
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
                    'comments' => true,
                    'rating' => true,
                    'favorites' => true,
                    'poll' => false,
                    'home' => true,
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
            'faq' => [
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
                    'mode' => 'faq',
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
                        'sort' => 10,
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
            'files' => [
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
                    'mode' => 'files',
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
                    'cover' => [
                        'title' => '_NODE_COVER',
                        'kinds' => [
                            0 => 'image',
                        ],
                        'max' => 1,
                        'mode' => 'image',
                        'sort' => 10,
                    ],
                    'download' => [
                        'title' => '_DOWNLOAD',
                        'kinds' => [
                            0 => 'file',
                            1 => 'image',
                            2 => 'audio',
                            3 => 'video',
                        ],
                        'min' => 1,
                        'max' => 10,
                        'canlink' => true,
                        'report' => true,
                        'mode' => 'download',
                        'sort' => 20,
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
            'help' => [
                'version' => 2,
                'list' => [
                    'limit' => 20,
                    'show' => [
                        0 => 'category',
                        1 => 'date',
                    ],
                ],
                'view' => [
                    'mode' => 'support',
                ],
                'workflow' => [
                    'notify' => [
                        'pending' => false,
                        'result' => false,
                    ],
                ],
                'features' => [
                    'categories' => true,
                    'comments' => true,
                    'rating' => false,
                    'favorites' => false,
                    'poll' => false,
                    'home' => false,
                    'pinned' => false,
                    'submit' => true,
                    'moderation' => false,
                    'schedule' => false,
                    'related' => false,
                    'tree' => false,
                ],
                'ext' => [
                    'mail' => true,
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
            'links' => [
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
                    'cover' => [
                        'title' => '_NODE_COVER',
                        'kinds' => [
                            0 => 'image',
                        ],
                        'max' => 1,
                        'mode' => 'image',
                        'sort' => 10,
                    ],
                    'link' => [
                        'title' => '_URL',
                        'kinds' => [
                            0 => 'file',
                        ],
                        'min' => 1,
                        'max' => 1,
                        'canlink' => true,
                        'report' => true,
                        'mode' => 'link',
                        'sort' => 20,
                    ],
                ],
                'integrations' => [
                    'search' => true,
                    'rss' => true,
                    'sitemap' => true,
                    'blocks' => true,
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
            'news' => [
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
                    'poll' => true,
                    'home' => true,
                    'pinned' => true,
                    'submit' => true,
                    'moderation' => true,
                    'schedule' => true,
                    'related' => true,
                    'tree' => false,
                ],
                'assets' => [
                    'cover' => [
                        'title' => '_NODE_COVER',
                        'kinds' => [
                            0 => 'image',
                        ],
                        'max' => 1,
                        'mode' => 'image',
                        'sort' => 10,
                    ],
                    'gallery' => [
                        'title' => '_ALBUM',
                        'kinds' => [
                            0 => 'image',
                        ],
                        'max' => 20,
                        'mode' => 'gallery',
                        'sort' => 20,
                    ],
                ],
                'integrations' => [
                    'search' => true,
                    'rss' => true,
                    'sitemap' => true,
                    'blocks' => true,
                    'seo' => 'news',
                ],
            ],
        ],
    ],
];
