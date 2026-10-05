<?php
declare(strict_types=1);

# PHPUnit bootstrap for SLAED CMS: autoloader, test constants, test configuration and global function stubs

# Load the Composer autoloader and the tree walk every source gate shares
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/Support/tree_walk.php';

# Define the constants the tested code expects
define('MODULE_FILE', true);
define('FUNC_FILE', true);
define('BASE_DIR', dirname(__DIR__));
define('CONFIG_DIR', BASE_DIR.'/config');
define('CACHE_DIR', BASE_DIR.'/storage/cache');
define('COUNTER_DIR', BASE_DIR.'/storage/counter');
define('UPLOADS_DIR', BASE_DIR.'/uploads');
define('NODE_DIR', UPLOADS_DIR.'/node');

# Write the logs of in-process tests into a directory of this run: the mail tests refuse addresses on purpose, and their lines landed in the site log of the stand
define('LOGS_DIR', str_replace('\\', '/', sys_get_temp_dir()).'/slaed-phpunit-'.getmypid().'/logs');
if (!is_dir(LOGS_DIR)) mkdir(LOGS_DIR, 0777, true);

# Remove that directory when the run ends, whatever the tests left in it
register_shutdown_function(static function (): void {
    $root = dirname(LOGS_DIR);
    if (!is_dir($root)) return;
    $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($walk as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($root);
});

# Provide a minimal site configuration for the tests
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

# Stub global functions the tested code calls when the application does not define them
if (!function_exists('getIp')) {
    function getIp(): string
    {
        return '127.0.0.1';
    }
}

if (!function_exists('getAgent')) {
    function getAgent(): string
    {
        return 'PHPUnit';
    }
}

# The real normalizer lives in core/system.php, which a unit test does not load; tests/Support/mail_probe.php asserts it on the booted core
if (!function_exists('getOutputHtml')) {
    function getOutputHtml(string $html): string
    {
        return $html;
    }
}
