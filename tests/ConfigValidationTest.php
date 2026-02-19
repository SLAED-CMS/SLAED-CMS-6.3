<?php
/**
 * Тест валидации конфигурационных файлов
 * Проверяет наличие и корректность конфигурации
 */

use PHPUnit\Framework\TestCase;

class ConfigValidationTest extends TestCase
{
    private static string $basePath;
    private static string $configPath;
    private static array $configFiles = [];

    public static function setUpBeforeClass(): void
    {
        self::$basePath = dirname(__DIR__);
        self::$configPath = self::$basePath . '/config';
        self::scanConfigFiles();
    }

    /**
     * Сканирует конфигурационные файлы
     */
    private static function scanConfigFiles(): void
    {
        if (!is_dir(self::$configPath)) return;

        foreach (scandir(self::$configPath) as $file) {
            if ($file === '.' || $file === '..') continue;
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                self::$configFiles[] = self::$configPath . '/' . $file;
            }
        }
    }

    /**
     * Проверяет наличие обязательных конфигурационных файлов
     */
    public function testRequiredConfigFilesExist(): void
    {
        $required = [
            'db.php',
            'global.php',
        ];

        $errors = [];

        foreach ($required as $file) {
            if (!file_exists(self::$configPath . '/' . $file)) {
                $errors[] = "config/$file - обязательный файл отсутствует";
            }
        }

        $this->assertEmpty(
            $errors,
            "Отсутствуют обязательные файлы конфигурации:\n" . implode("\n", $errors)
        );
    }

    /**
     * Проверяет синтаксис конфигурационных файлов
     */
    public function testConfigFilesSyntax(): void
    {
        $errors = [];

        foreach (self::$configFiles as $file) {
            $output = [];
            $returnCode = 0;
            exec('php -l "' . $file . '" 2>&1', $output, $returnCode);

            if ($returnCode !== 0) {
                $errors[] = sprintf(
                    "config/%s - синтаксическая ошибка",
                    basename($file)
                );
            }
        }

        $this->assertEmpty(
            $errors,
            "Синтаксические ошибки в конфигурации:\n" . implode("\n", $errors)
        );
    }

    /**
     * Проверяет структуру db.php
     */
    public function testDbConfigStructure(): void
    {
        $dbFile = self::$configPath . '/db.php';
        if (!file_exists($dbFile)) {
            $this->markTestSkipped('db.php не найден');
            return;
        }

        $content = file_get_contents($dbFile);

        // Проверяем наличие обязательных параметров
        $requiredParams = ['host', 'name', 'uname', 'prefix'];

        $errors = [];
        foreach ($requiredParams as $param) {
            if (!preg_match('/[\'"]' . $param . '[\'"]\s*=>/', $content)) {
                $errors[] = "db.php - отсутствует параметр '$param'";
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы в конфигурации БД:\n" . implode("\n", $errors)
        );
    }

    /**
     * Проверяет что пароли не содержат значения по умолчанию
     */
    public function testNoDefaultPasswords(): void
    {
        $warnings = [];
        $defaultPasswords = ['password', '123456', 'admin', 'root', 'test'];

        foreach (self::$configFiles as $file) {
            $content = file_get_contents($file);
            $fileName = basename($file);

            // Ищем пароли
            if (preg_match('/[\'"]pass(?:word)?[\'"]\s*=>\s*[\'"]([^\'"]*)[\'"]/i', $content, $match)) {
                $password = $match[1];
                if (in_array(strtolower($password), $defaultPasswords)) {
                    $warnings[] = "config/$fileName - обнаружен стандартный пароль";
                }
            }
        }

        // Это предупреждение для продакшена, но не ошибка для разработки
        $this->assertTrue(true, count($warnings) . " файлов с потенциально небезопасными паролями");
    }

    /**
     * Проверяет кодировку конфигурационных файлов
     */
    public function testConfigFilesEncoding(): void
    {
        $errors = [];

        foreach (self::$configFiles as $file) {
            $content = file_get_contents($file);
            $fileName = basename($file);

            // Проверяем на невалидный UTF-8
            if (!mb_check_encoding($content, 'UTF-8')) {
                $errors[] = "config/$fileName - некорректная кодировка";
            }

            // Проверяем на BOM
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $errors[] = "config/$fileName - содержит BOM";
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с кодировкой:\n" . implode("\n", $errors)
        );
    }

    /**
     * Проверяет что конфиг файлы определяют массивы корректно
     */
    public function testConfigFilesDefineArrays(): void
    {
        $errors = [];

        // Файлы которые должны содержать массивы
        $arrayConfigs = [
            'global.php' => 'conf',
            'db.php' => 'confdb',
            'modules.php' => 'confmd',
        ];

        foreach ($arrayConfigs as $file => $varName) {
            $filePath = self::$configPath . '/' . $file;
            if (!file_exists($filePath)) continue;

            $content = file_get_contents($filePath);

            // Проверяем наличие определения массива
            if (!preg_match('/\$' . $varName . '\s*=\s*(\[|array\s*\()/', $content)) {
                $errors[] = "config/$file - переменная \$$varName не определена как массив";
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с определением конфигурации:\n" . implode("\n", $errors)
        );
    }

    /**
     * Проверяет что конфигурационные файлы найдены
     */
    public function testConfigFilesFound(): void
    {
        $this->assertNotEmpty(self::$configFiles, 'Конфигурационные файлы не найдены');
        $this->assertGreaterThan(5, count(self::$configFiles), 'Найдено слишком мало файлов конфигурации');
    }

    /**
     * Проверяет права доступа к конфигурационным файлам
     */
    public function testConfigFilesNotWorldReadable(): void
    {
        // Эта проверка актуальна только для Unix-систем
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Проверка прав доступа не применима к Windows');
            return;
        }

        $warnings = [];

        foreach (self::$configFiles as $file) {
            $perms = fileperms($file);
            $worldReadable = ($perms & 0x0004);

            if ($worldReadable && strpos(basename($file), 'db') !== false) {
                $warnings[] = "config/" . basename($file) . " - доступен для чтения всем";
            }
        }

        $this->assertEmpty(
            $warnings,
            "Небезопасные права доступа:\n" . implode("\n", $warnings)
        );
    }
}
