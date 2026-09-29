<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

return [
    'fields' => [
        'account' => [
        ],
        'forum' => [
        ],
        'node' => [
            'media' => [
                'subtitle' => [
                    'title' => '_NODE_SUBTITLE',
                    'intro' => '',
                    'type' => 'text',
                    'default' => '',
                    'options' => [
                        'max' => 100,
                    ],
                    'req' => false,
                    'multi' => false,
                    'active' => true,
                    'sort' => 10,
                ],
                'year' => [
                    'title' => '_NODE_YEAR',
                    'intro' => '',
                    'type' => 'int',
                    'default' => NULL,
                    'options' => [
                        'min' => 1,
                        'max' => 9999,
                    ],
                    'req' => false,
                    'multi' => false,
                    'active' => true,
                    'sort' => 20,
                ],
                'director' => [
                    'title' => '_NODE_DIRECTOR',
                    'intro' => '',
                    'type' => 'text',
                    'default' => '',
                    'options' => [
                        'max' => 100,
                    ],
                    'req' => false,
                    'multi' => false,
                    'active' => true,
                    'sort' => 30,
                ],
                'cast' => [
                    'title' => '_NODE_CAST',
                    'intro' => '',
                    'type' => 'text',
                    'default' => '',
                    'options' => [
                        'max' => 255,
                    ],
                    'req' => false,
                    'multi' => false,
                    'active' => true,
                    'sort' => 40,
                ],
                'creator' => [
                    'title' => '_NODE_CREATOR',
                    'intro' => '',
                    'type' => 'text',
                    'default' => '',
                    'options' => [
                        'max' => 100,
                    ],
                    'req' => false,
                    'multi' => false,
                    'active' => true,
                    'sort' => 50,
                ],
                'runtime' => [
                    'title' => '_NODE_RUNTIME',
                    'intro' => '',
                    'type' => 'text',
                    'default' => '',
                    'options' => [
                        'max' => 100,
                    ],
                    'req' => false,
                    'multi' => false,
                    'active' => true,
                    'sort' => 60,
                ],
                'language' => [
                    'title' => '_LANGUAGE',
                    'intro' => '',
                    'type' => 'text',
                    'default' => '',
                    'options' => [
                        'max' => 100,
                    ],
                    'req' => false,
                    'multi' => false,
                    'active' => true,
                    'sort' => 70,
                ],
                'notes' => [
                    'title' => '_NOTE',
                    'intro' => '',
                    'type' => 'textarea',
                    'default' => '',
                    'options' => [
                        'max' => 262144,
                    ],
                    'req' => false,
                    'multi' => false,
                    'active' => true,
                    'sort' => 80,
                ],
                'format' => [
                    'title' => '_NODE_MFORMAT',
                    'intro' => '',
                    'type' => 'text',
                    'default' => '',
                    'options' => [
                        'max' => 100,
                    ],
                    'req' => false,
                    'multi' => false,
                    'active' => true,
                    'sort' => 90,
                ],
                'quality' => [
                    'title' => '_NODE_QUALITY',
                    'intro' => '',
                    'type' => 'text',
                    'default' => '',
                    'options' => [
                        'max' => 100,
                    ],
                    'req' => false,
                    'multi' => false,
                    'active' => true,
                    'sort' => 100,
                ],
                'filesize' => [
                    'title' => '_SIZE',
                    'intro' => '',
                    'type' => 'text',
                    'default' => '',
                    'options' => [
                        'max' => 100,
                    ],
                    'req' => false,
                    'multi' => false,
                    'active' => true,
                    'sort' => 110,
                ],
                'release' => [
                    'title' => '_NODE_RELEASE',
                    'intro' => '',
                    'type' => 'text',
                    'default' => '',
                    'options' => [
                        'max' => 100,
                    ],
                    'req' => false,
                    'multi' => false,
                    'active' => true,
                    'sort' => 120,
                ],
            ],
        ],
    ],
];
