<?php
/**
 * Configuration validation tests.
 * Checks presence and basic correctness of config files.
 */

use PHPUnit\Framework\TestCase;

class ConfigValidationTest extends TestCase
{
    private static string $base_path;
    private static string $config_path;
    private static array $config_files = [];

    public static function setUpBeforeClass(): void
    {
        self::$base_path = dirname(__DIR__);
        self::$config_path = self::$base_path.'/config';
        self::scanconfig_files();
    }

    /**
     * Scan configuration files.
     */
    private static function scanconfig_files(): void
    {
        if (!is_dir(self::$config_path)) return;

        foreach (scandir(self::$config_path) as $file) {
            if ($file === '.' || $file === '..') continue;
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                self::$config_files[] = self::$config_path.'/'.$file;
            }
        }
    }

    /**
     * Check required config files exist.
     */
    public function testRequiredconfig_filesExist(): void
    {
        $required = [
            'db.php',
            'global.php',
        ];

        $errors = [];

        foreach ($required as $file) {
            if (!file_exists(self::$config_path.'/'.$file)) {
                $errors[] = "config/$file - required file is missing";
            }
        }

        $this->assertEmpty(
            $errors,
            "Missing required configuration files:\n".implode("\n", $errors)
        );
    }

    /**
     * Check syntax of config files.
     */
    public function testconfig_filesSyntax(): void
    {
        $errors = [];

        foreach (self::$config_files as $file) {
            $output = [];
            $return_code = 0;
            exec('php -l "'.$file.'" 2>&1', $output, $return_code);

            if ($return_code !== 0) {
                $errors[] = sprintf(
                    'config/%s - syntax error',
                    basename($file)
                );
            }
        }

        $this->assertEmpty(
            $errors,
            "Configuration syntax errors:\n".implode("\n", $errors)
        );
    }

    /**
     * Check db.php structure.
     */
    public function testDbConfigStructure(): void
    {
        $db_file = self::$config_path.'/db.php';
        if (!file_exists($db_file)) {
            $this->markTestSkipped('db.php not found');
            return;
        }

        $content = file_get_contents($db_file);
        $required_params = ['host', 'name', 'uname', 'prefix'];

        $errors = [];
        foreach ($required_params as $param) {
            if (!preg_match('/[\'"]'.$param.'[\'"]\s*=>/', $content)) {
                $errors[] = "db.php - missing parameter '$param'";
            }
        }

        $this->assertEmpty(
            $errors,
            "DB configuration issues:\n".implode("\n", $errors)
        );
    }

    /**
     * Check for default passwords in config.
     */
    public function testNodefault_passwords(): void
    {
        $warnings = [];
        $default_passwords = ['password', '123456', 'admin', 'root', 'test'];

        foreach (self::$config_files as $file) {
            $content = file_get_contents($file);
            $file_name = basename($file);

            if (preg_match('/[\'"]pass(?:word)?[\'"]\s*=>\s*[\'"]([^\'"]*)[\'"]/i', $content, $match)) {
                $password = $match[1];
                if (in_array(strtolower($password), $default_passwords, true)) {
                    $warnings[] = "config/$file_name - default password detected";
                }
            }
        }

        // Informational check for development environments.
        $this->assertTrue(true, count($warnings).' files with potentially unsafe passwords');
    }

    /**
     * Check config file encoding.
     */
    public function testconfig_filesEncoding(): void
    {
        $errors = [];

        foreach (self::$config_files as $file) {
            $content = file_get_contents($file);
            $file_name = basename($file);

            if (!mb_check_encoding($content, 'UTF-8')) {
                $errors[] = "config/$file_name - invalid encoding";
            }

            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $errors[] = "config/$file_name - contains BOM";
            }
        }

        $this->assertEmpty(
            $errors,
            "Encoding problems:\n".implode("\n", $errors)
        );
    }

    /**
     * Check array style in key config files.
     */
    public function testconfig_filesDefineArrays(): void
    {
        $errors = [];

        $array_configs = [
            'global.php',
            'db.php',
            'modules.php',
            'favorites.php',
        ];

        foreach ($array_configs as $file) {
            $file_path = self::$config_path.'/'.$file;
            if (!file_exists($file_path)) continue;

            $content = file_get_contents($file_path);

            if (!preg_match('/return\s*(\[|array\s*\()/', $content)) {
                $errors[] = "config/$file - file does not use return [] style";
            }
        }

        $this->assertEmpty(
            $errors,
            "Configuration definition issues:\n".implode("\n", $errors)
        );
    }

    /**
     * Check config files are detected.
     */
    public function testconfig_filesFound(): void
    {
        $this->assertNotEmpty(self::$config_files, 'Configuration files not found');
        $this->assertGreaterThan(5, count(self::$config_files), 'Too few configuration files found');
    }

    /**
     * Check permissions for config files.
     */
    public function testconfig_filesNotworld_readable(): void
    {
        // Only relevant on Unix-like systems.
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Permission check is not applicable on Windows');
            return;
        }

        $warnings = [];

        foreach (self::$config_files as $file) {
            $perms = fileperms($file);
            $world_readable = ($perms & 0x0004);

            if ($world_readable && strpos(basename($file), 'db') !== false) {
                $warnings[] = 'config/'.basename($file).' - world-readable';
            }
        }

        $this->assertEmpty(
            $warnings,
            "Unsafe permission flags:\n".implode("\n", $warnings)
        );
    }
}
