<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The service system of the core, its declaration and its handlers in one file: the probe of a living API and its live reference

# Answer that the API lives, with no data; the meta of the envelope carries the server time
function getSystemPing(array $in, ApiClient $cli): array {
    return [];
}

# The live reference of the API, built from the declarations of the services and never written by hand
function getSystemSchema(array $in, ApiClient $cli): array {
    return ApiRouter::getSchemaData();
}

return [
    'title' => 'System',
    'scopes' => [],
    'events' => [],
    'actions' => [
        'ping' => ['method' => 'GET', 'auth' => 'public', 'call' => 'getSystemPing'],
        'schema' => ['method' => 'GET', 'auth' => 'public', 'cache' => 60, 'call' => 'getSystemSchema'],
    ],
];
