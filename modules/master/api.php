<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The service master of slaed.net: the daily check of a site and the archive of a release; both public, the check records only the state a site reports
return [
    'title' => 'Master',
    'scopes' => [],
    'events' => [],
    'actions' => [
        'check' => ['method' => 'GET', 'auth' => 'public', 'call' => 'getMasterCheck', 'params' => [
            'sid' => ['hex', 32], 'v' => 'ver', 'ch' => ['word', ['stable', 'beta'], 'def' => 'stable'], 'kseq' => ['num', 1000000, 'def' => 0],
            'php' => ['ver', 'def' => null], 'db' => ['word', ['mysql', 'mariadb'], 'def' => null], 'dbv' => ['ver', 'def' => null], 'ext' => ['num', 7, 'def' => 0],
            'ep' => ['num', 1000000, 'def' => 0], 'es' => ['num', 1000000, 'def' => 0], 'host' => ['host', 'def' => ''], 'fv' => ['ver', 'def' => null],
            'fs' => ['num', 9, 'def' => 0],
        ]],
        'file' => ['method' => 'GET', 'auth' => 'public', 'reply' => 'file', 'cache' => 3600, 'call' => 'getMasterFile',
            'params' => ['v' => 'ver', 'kind' => ['word', ['full', 'delta']]]],
    ],
];
