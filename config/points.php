<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

return [
    'points' => [
        'active' => '1',
        'actions' => [
            'publish' => ['points' => '10', 'period' => '86400', 'limit' => '10'],
            'comment' => ['points' => '5', 'period' => '86400', 'limit' => '30'],
            'view' => ['points' => '0', 'period' => '86400', 'limit' => '50'],
            'download' => ['points' => '3', 'period' => '86400', 'limit' => '20'],
            'visit' => ['points' => '0', 'period' => '86400', 'limit' => '20'],
            'poll' => ['points' => '3', 'period' => '86400', 'limit' => '10'],
            'order' => ['points' => '10', 'period' => '0', 'limit' => '0'],
            'favorite' => ['points' => '0', 'period' => '86400', 'limit' => '20'],
            'message' => ['points' => '0', 'period' => '86400', 'limit' => '20'],
            'recommend' => ['points' => '0', 'period' => '86400', 'limit' => '5'],
            'register' => ['points' => '0', 'period' => '0', 'limit' => '0'],
            'login' => ['points' => '0', 'period' => '86400', 'limit' => '1'],
            'report' => ['points' => '3', 'period' => '86400', 'limit' => '5'],
            'moderate' => ['points' => '0', 'period' => '86400', 'limit' => '100'],
            'adjust' => ['points' => '0', 'period' => '0', 'limit' => '0'],
        ],
    ],
];
