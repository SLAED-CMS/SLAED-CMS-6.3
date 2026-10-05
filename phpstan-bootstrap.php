<?php
# PHPStan bootstrap for legacy globals and functions used via includes

if (!isset($conf)) {
    $conf = [];
}
if (!isset($conf['users'])) {
    $conf['users'] = [];
}
if (!isset($user)) {
    $user = [];
}
if (!isset($rfl)) {
    $rfl = '';
}

if (!defined('BASE_DIR')) {
    define('BASE_DIR', __DIR__);
}
if (!defined('PUBLIC_DIR')) {
    define('PUBLIC_DIR', __DIR__.'/public');
}

require_once __DIR__.'/phpstan-stubs.php';

if (!function_exists('panel')) {
    function panel(...$args) {}
}
if (!function_exists('adminmenu')) {
    function adminmenu(...$args) {}
}
if (!function_exists('get_modules')) {
    function get_modules(...$args) { return []; }
}
