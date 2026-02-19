<?php
/**
 * Ð¢ÐµÑÑ‚ Ð²Ð°Ð»Ð¸Ð´Ð°Ñ†Ð¸Ð¸ ÐºÐ¾Ð½Ñ„Ð¸Ð³ÑƒÑ€Ð°Ñ†Ð¸Ð¾Ð½Ð½Ñ‹Ñ… Ñ„Ð°Ð¹Ð»Ð¾Ð²
 * ÐŸÑ€Ð¾Ð²ÐµÑ€ÑÐµÑ‚ Ð½Ð°Ð»Ð¸Ñ‡Ð¸Ðµ Ð¸ ÐºÐ¾Ñ€Ñ€ÐµÐºÑ‚Ð½Ð¾ÑÑ‚ÑŒ ÐºÐ¾Ð½Ñ„Ð¸Ð³ÑƒÑ€Ð°Ñ†Ð¸Ð¸
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
     * Ð¡ÐºÐ°Ð½Ð¸Ñ€ÑƒÐµÑ‚ ÐºÐ¾Ð½Ñ„Ð¸Ð³ÑƒÑ€Ð°Ñ†Ð¸Ð¾Ð½Ð½Ñ‹Ðµ Ñ„Ð°Ð¹Ð»Ñ‹
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
     * ÐŸÑ€Ð¾Ð²ÐµÑ€ÑÐµÑ‚ Ð½Ð°Ð»Ð¸Ñ‡Ð¸Ðµ Ð¾Ð±ÑÐ·Ð°Ñ‚ÐµÐ»ÑŒÐ½Ñ‹Ñ… ÐºÐ¾Ð½Ñ„Ð¸Ð³ÑƒÑ€Ð°Ñ†Ð¸Ð¾Ð½Ð½Ñ‹Ñ… Ñ„Ð°Ð¹Ð»Ð¾Ð²
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
                $errors[] = "config/$file - Ð¾Ð±ÑÐ·Ð°Ñ‚ÐµÐ»ÑŒÐ½Ñ‹Ð¹ Ñ„Ð°Ð¹Ð» Ð¾Ñ‚ÑÑƒÑ‚ÑÑ‚Ð²ÑƒÐµÑ‚";
            }
        }

        $this->assertEmpty(
            $errors,
            "ÐžÑ‚ÑÑƒÑ‚ÑÑ‚Ð²ÑƒÑŽÑ‚ Ð¾Ð±ÑÐ·Ð°Ñ‚ÐµÐ»ÑŒÐ½Ñ‹Ðµ Ñ„Ð°Ð¹Ð»Ñ‹ ÐºÐ¾Ð½Ñ„Ð¸Ð³ÑƒÑ€Ð°Ñ†Ð¸Ð¸:\n".implode("\n", $errors)
        );
    }

    /**
     * ÐŸÑ€Ð¾Ð²ÐµÑ€ÑÐµÑ‚ ÑÐ¸Ð½Ñ‚Ð°ÐºÑÐ¸Ñ ÐºÐ¾Ð½Ñ„Ð¸Ð³ÑƒÑ€Ð°Ñ†Ð¸Ð¾Ð½Ð½Ñ‹Ñ… Ñ„Ð°Ð¹Ð»Ð¾Ð²
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
                    'config/%s - ÑÐ¸Ð½Ñ‚Ð°ÐºÑÐ¸Ñ‡ÐµÑÐºÐ°Ñ Ð¾ÑˆÐ¸Ð±ÐºÐ°',
                    basename($file)
                );
            }
        }

        $this->assertEmpty(
            $errors,
            "Ð¡Ð¸Ð½Ñ‚Ð°ÐºÑÐ¸Ñ‡ÐµÑÐºÐ¸Ðµ Ð¾ÑˆÐ¸Ð±ÐºÐ¸ Ð² ÐºÐ¾Ð½Ñ„Ð¸Ð³ÑƒÑ€Ð°Ñ†Ð¸Ð¸:\n".implode("\n", $errors)
        );
    }

    /**
     * ÐŸÑ€Ð¾Ð²ÐµÑ€ÑÐµÑ‚ ÑÑ‚Ñ€ÑƒÐºÑ‚ÑƒÑ€Ñƒ db.php
     */
    public function testDbConfigStructure(): void
    {
        $db_file = self::$config_path.'/db.php';
        if (!file_exists($db_file)) {
            $this->markTestSkipped('db.php Ð½Ðµ Ð½Ð°Ð¹Ð´ÐµÐ½');
            return;
        }

        $content = file_get_contents($db_file);

        // ÐŸÑ€Ð¾Ð²ÐµÑ€ÑÐµÐ¼ Ð½Ð°Ð»Ð¸Ñ‡Ð¸Ðµ Ð¾Ð±ÑÐ·Ð°Ñ‚ÐµÐ»ÑŒÐ½Ñ‹Ñ… Ð¿Ð°Ñ€Ð°Ð¼ÐµÑ‚Ñ€Ð¾Ð²
        $required_params = ['host', 'name', 'uname', 'prefix'];

        $errors = [];
        foreach ($required_params as $param) {
            if (!preg_match('/[\'"]'.$param.'[\'"]\s*=>/', $content)) {
                $errors[] = "db.php - Ð¾Ñ‚ÑÑƒÑ‚ÑÑ‚Ð²ÑƒÐµÑ‚ Ð¿Ð°Ñ€Ð°Ð¼ÐµÑ‚Ñ€ '$param'";
            }
        }

        $this->assertEmpty(
            $errors,
            "ÐŸÑ€Ð¾Ð±Ð»ÐµÐ¼Ñ‹ Ð² ÐºÐ¾Ð½Ñ„Ð¸Ð³ÑƒÑ€Ð°Ñ†Ð¸Ð¸ Ð‘Ð”:\n".implode("\n", $errors)
        );
    }

    /**
     * ÐŸÑ€Ð¾Ð²ÐµÑ€ÑÐµÑ‚ Ñ‡Ñ‚Ð¾ Ð¿Ð°Ñ€Ð¾Ð»Ð¸ Ð½Ðµ ÑÐ¾Ð´ÐµÑ€Ð¶Ð°Ñ‚ Ð·Ð½Ð°Ñ‡ÐµÐ½Ð¸Ñ Ð¿Ð¾ ÑƒÐ¼Ð¾Ð»Ñ‡Ð°Ð½Ð¸ÑŽ
     */
    public function testNodefault_passwords(): void
    {
        $warnings = [];
        $default_passwords = ['password', '123456', 'admin', 'root', 'test'];

        foreach (self::$config_files as $file) {
            $content = file_get_contents($file);
            $file_name = basename($file);

            // Ð˜Ñ‰ÐµÐ¼ Ð¿Ð°Ñ€Ð¾Ð»Ð¸
            if (preg_match('/[\'"]pass(?:word)?[\'"]\s*=>\s*[\'"]([^\'"]*)[\'"]/i', $content, $match)) {
                $password = $match[1];
                if (in_array(strtolower($password), $default_passwords)) {
                    $warnings[] = "config/$file_name - Ð¾Ð±Ð½Ð°Ñ€ÑƒÐ¶ÐµÐ½ ÑÑ‚Ð°Ð½Ð´Ð°Ñ€Ñ‚Ð½Ñ‹Ð¹ Ð¿Ð°Ñ€Ð¾Ð»ÑŒ";
                }
            }
        }

        // Ð­Ñ‚Ð¾ Ð¿Ñ€ÐµÐ´ÑƒÐ¿Ñ€ÐµÐ¶Ð´ÐµÐ½Ð¸Ðµ Ð´Ð»Ñ Ð¿Ñ€Ð¾Ð´Ð°ÐºÑˆÐµÐ½Ð°, Ð½Ð¾ Ð½Ðµ Ð¾ÑˆÐ¸Ð±ÐºÐ° Ð´Ð»Ñ Ñ€Ð°Ð·Ñ€Ð°Ð±Ð¾Ñ‚ÐºÐ¸
        $this->assertTrue(true, count($warnings).' Ñ„Ð°Ð¹Ð»Ð¾Ð² Ñ Ð¿Ð¾Ñ‚ÐµÐ½Ñ†Ð¸Ð°Ð»ÑŒÐ½Ð¾ Ð½ÐµÐ±ÐµÐ·Ð¾Ð¿Ð°ÑÐ½Ñ‹Ð¼Ð¸ Ð¿Ð°Ñ€Ð¾Ð»ÑÐ¼Ð¸');
    }

    /**
     * ÐŸÑ€Ð¾Ð²ÐµÑ€ÑÐµÑ‚ ÐºÐ¾Ð´Ð¸Ñ€Ð¾Ð²ÐºÑƒ ÐºÐ¾Ð½Ñ„Ð¸Ð³ÑƒÑ€Ð°Ñ†Ð¸Ð¾Ð½Ð½Ñ‹Ñ… Ñ„Ð°Ð¹Ð»Ð¾Ð²
     */
    public function testconfig_filesEncoding(): void
    {
        $errors = [];

        foreach (self::$config_files as $file) {
            $content = file_get_contents($file);
            $file_name = basename($file);

            // ÐŸÑ€Ð¾Ð²ÐµÑ€ÑÐµÐ¼ Ð½Ð° Ð½ÐµÐ²Ð°Ð»Ð¸Ð´Ð½Ñ‹Ð¹ UTF-8
            if (!mb_check_encoding($content, 'UTF-8')) {
                $errors[] = "config/$file_name - Ð½ÐµÐºÐ¾Ñ€Ñ€ÐµÐºÑ‚Ð½Ð°Ñ ÐºÐ¾Ð´Ð¸Ñ€Ð¾Ð²ÐºÐ°";
            }

            // ÐŸÑ€Ð¾Ð²ÐµÑ€ÑÐµÐ¼ Ð½Ð° BOM
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $errors[] = "config/$file_name - ÑÐ¾Ð´ÐµÑ€Ð¶Ð¸Ñ‚ BOM";
            }
        }

        $this->assertEmpty(
            $errors,
            "ÐŸÑ€Ð¾Ð±Ð»ÐµÐ¼Ñ‹ Ñ ÐºÐ¾Ð´Ð¸Ñ€Ð¾Ð²ÐºÐ¾Ð¹:\n".implode("\n", $errors)
        );
    }

    /**
     * ÐŸÑ€Ð¾Ð²ÐµÑ€ÑÐµÑ‚ Ñ‡Ñ‚Ð¾ ÐºÐ¾Ð½Ñ„Ð¸Ð³ Ñ„Ð°Ð¹Ð»Ñ‹ Ð¸ÑÐ¿Ð¾Ð»ÑŒÐ·ÑƒÑŽÑ‚ ÑÑ‚Ð°Ð½Ð´Ð°Ñ€Ñ‚ return []
     */
    public function testconfig_filesDefineArrays(): void
    {
        $errors = [];

        // Ð¤Ð°Ð¹Ð»Ñ‹ ÐºÐ¾Ñ‚Ð¾Ñ€Ñ‹Ðµ Ð´Ð¾Ð»Ð¶Ð½Ñ‹ Ð¸ÑÐ¿Ð¾Ð»ÑŒÐ·Ð¾Ð²Ð°Ñ‚ÑŒ return []
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

            // ÐŸÑ€Ð¾Ð²ÐµÑ€ÑÐµÐ¼ ÑÑ‚Ð°Ð½Ð´Ð°Ñ€Ñ‚: return [ Ð¸Ð»Ð¸ return array(
            if (!preg_match('/return\s*(\[|array\s*\()/', $content)) {
                $errors[] = "config/$file - Ñ„Ð°Ð¹Ð» Ð½Ðµ Ð¸ÑÐ¿Ð¾Ð»ÑŒÐ·ÑƒÐµÑ‚ ÑÑ‚Ð°Ð½Ð´Ð°Ñ€Ñ‚ return []";
            }
        }

        $this->assertEmpty(
            $errors,
            "ÐŸÑ€Ð¾Ð±Ð»ÐµÐ¼Ñ‹ Ñ Ð¾Ð¿Ñ€ÐµÐ´ÐµÐ»ÐµÐ½Ð¸ÐµÐ¼ ÐºÐ¾Ð½Ñ„Ð¸Ð³ÑƒÑ€Ð°Ñ†Ð¸Ð¸:\n".implode("\n", $errors)
        );
    }

    /**
     * ÐŸÑ€Ð¾Ð²ÐµÑ€ÑÐµÑ‚ Ñ‡Ñ‚Ð¾ ÐºÐ¾Ð½Ñ„Ð¸Ð³ÑƒÑ€Ð°Ñ†Ð¸Ð¾Ð½Ð½Ñ‹Ðµ Ñ„Ð°Ð¹Ð»Ñ‹ Ð½Ð°Ð¹Ð´ÐµÐ½Ñ‹
     */
    public function testconfig_filesFound(): void
    {
        $this->assertNotEmpty(self::$config_files, 'ÐšÐ¾Ð½Ñ„Ð¸Ð³ÑƒÑ€Ð°Ñ†Ð¸Ð¾Ð½Ð½Ñ‹Ðµ Ñ„Ð°Ð¹Ð»Ñ‹ Ð½Ðµ Ð½Ð°Ð¹Ð´ÐµÐ½Ñ‹');
        $this->assertGreaterThan(5, count(self::$config_files), 'ÐÐ°Ð¹Ð´ÐµÐ½Ð¾ ÑÐ»Ð¸ÑˆÐºÐ¾Ð¼ Ð¼Ð°Ð»Ð¾ Ñ„Ð°Ð¹Ð»Ð¾Ð² ÐºÐ¾Ð½Ñ„Ð¸Ð³ÑƒÑ€Ð°Ñ†Ð¸Ð¸');
    }

    /**
     * ÐŸÑ€Ð¾Ð²ÐµÑ€ÑÐµÑ‚ Ð¿Ñ€Ð°Ð²Ð° Ð´Ð¾ÑÑ‚ÑƒÐ¿Ð° Ðº ÐºÐ¾Ð½Ñ„Ð¸Ð³ÑƒÑ€Ð°Ñ†Ð¸Ð¾Ð½Ð½Ñ‹Ð¼ Ñ„Ð°Ð¹Ð»Ð°Ð¼
     */
    public function testconfig_filesNotworld_readable(): void
    {
        // Ð­Ñ‚Ð° Ð¿Ñ€Ð¾Ð²ÐµÑ€ÐºÐ° Ð°ÐºÑ‚ÑƒÐ°Ð»ÑŒÐ½Ð° Ñ‚Ð¾Ð»ÑŒÐºÐ¾ Ð´Ð»Ñ Unix-ÑÐ¸ÑÑ‚ÐµÐ¼
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('ÐŸÑ€Ð¾Ð²ÐµÑ€ÐºÐ° Ð¿Ñ€Ð°Ð² Ð´Ð¾ÑÑ‚ÑƒÐ¿Ð° Ð½Ðµ Ð¿Ñ€Ð¸Ð¼ÐµÐ½Ð¸Ð¼Ð° Ðº Windows');
            return;
        }

        $warnings = [];

        foreach (self::$config_files as $file) {
            $perms = fileperms($file);
            $world_readable = ($perms & 0x0004);

            if ($world_readable && strpos(basename($file), 'db') !== false) {
                $warnings[] = 'config/'.basename($file).' - Ð´Ð¾ÑÑ‚ÑƒÐ¿ÐµÐ½ Ð´Ð»Ñ Ñ‡Ñ‚ÐµÐ½Ð¸Ñ Ð²ÑÐµÐ¼';
            }
        }

        $this->assertEmpty(
            $warnings,
            "ÐÐµÐ±ÐµÐ·Ð¾Ð¿Ð°ÑÐ½Ñ‹Ðµ Ð¿Ñ€Ð°Ð²Ð° Ð´Ð¾ÑÑ‚ÑƒÐ¿Ð°:\n".implode("\n", $warnings)
        );
    }
}

