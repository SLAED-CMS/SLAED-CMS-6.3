<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

return [
    'fields' => [
        'account' => [
            'field1' => [
                'title' => 'Версия системы',
                'intro' => '',
                'type' => 'select',
                'default' => '',
                'options' => [
                    'items' => [
                        'option1' => [
                            'title' => '2.3 Lite',
                            'active' => true,
                            'sort' => 10,
                        ],
                        'option2' => [
                            'title' => '2.4 Lite',
                            'active' => true,
                            'sort' => 20,
                        ],
                        'option3' => [
                            'title' => '3.3 Pro',
                            'active' => true,
                            'sort' => 30,
                        ],
                    ],
                ],
                'req' => false,
                'multi' => false,
                'active' => true,
                'sort' => 10,
            ],
            'field2' => [
                'title' => 'Ваше имя',
                'intro' => '',
                'type' => 'text',
                'default' => 'Андрей',
                'options' => [
                ],
                'req' => false,
                'multi' => false,
                'active' => true,
                'sort' => 20,
            ],
            'field3' => [
                'title' => 'Ваше мыло',
                'intro' => '',
                'type' => 'text',
                'default' => 'email@domain.net',
                'options' => [
                ],
                'req' => true,
                'multi' => false,
                'active' => true,
                'sort' => 30,
            ],
        ],
        'forum' => [
            'field1' => [
                'title' => 'Test',
                'intro' => '',
                'type' => 'select',
                'default' => '',
                'options' => [
                    'items' => [
                        'option1' => [
                            'title' => 'Test',
                            'active' => true,
                            'sort' => 10,
                        ],
                        'option2' => [
                            'title' => 'Test2',
                            'active' => true,
                            'sort' => 20,
                        ],
                        'option3' => [
                            'title' => 'Test3',
                            'active' => true,
                            'sort' => 30,
                        ],
                        'option4' => [
                            'title' => 'Test11',
                            'active' => true,
                            'sort' => 40,
                        ],
                    ],
                ],
                'req' => false,
                'multi' => false,
                'active' => true,
                'sort' => 10,
            ],
            'field2' => [
                'title' => 'Test2',
                'intro' => '',
                'type' => 'select',
                'default' => '',
                'options' => [
                    'items' => [
                        'option1' => [
                            'title' => 'Tes4',
                            'active' => true,
                            'sort' => 10,
                        ],
                        'option2' => [
                            'title' => 'Test5',
                            'active' => true,
                            'sort' => 20,
                        ],
                        'option3' => [
                            'title' => 'Test6',
                            'active' => true,
                            'sort' => 30,
                        ],
                        'option4' => [
                            'title' => 'Test22',
                            'active' => true,
                            'sort' => 40,
                        ],
                    ],
                ],
                'req' => false,
                'multi' => false,
                'active' => true,
                'sort' => 20,
            ],
        ],
        'order' => [
            'field1' => [
                'title' => 'WebMoney Z',
                'intro' => '',
                'type' => 'text',
                'default' => 'Кошелек Z',
                'options' => [
                ],
                'req' => true,
                'multi' => false,
                'active' => true,
                'sort' => 10,
            ],
        ],
    ],
];
