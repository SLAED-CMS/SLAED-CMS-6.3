<?php
declare(strict_types=1);

/**
 * PHPUnit Bootstrap File for SLAED CMS
 */

// Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Konstanten für Tests
define('MODULE_FILE', true);
define('FUNC_FILE', true);
define('BASE_DIR', dirname(__DIR__));
define('CONFIG_DIR', BASE_DIR . '/config');
define('CACHE_DIR', BASE_DIR . '/storage/cache');
define('LOGS_DIR', BASE_DIR . '/storage/logs');

// Test-Konfiguration laden
$GLOBALS['conf'] = [
    'homeurl' => 'http://localhost',
    'language' => 'en',
    'multilingual' => '0',
    'user_c' => 'test',
    'user_c_t' => '3600',
    'rewrite' => '0',
    'name' => 'test',
    'defis' => '-',
];

// Mock für globale Funktionen
if (!function_exists('getIp')) {
    function getIp(): string
    {
        return '127.0.0.1';
    }
}
