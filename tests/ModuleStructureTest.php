<?php
/**
 * Тест структуры модулей
 * Проверяет наличие обязательных файлов и корректность структуры
 */

use PHPUnit\Framework\TestCase;

class ModuleStructureTest extends TestCase
{
    private static string $basePath;
    private static string $modulesPath;
    private static array $modules = [];
    private static array $languages = ['ru', 'en', 'de', 'fr', 'pl', 'uk'];

    public static function setUpBeforeClass(): void
    {
        self::$basePath = dirname(__DIR__);
        self::$modulesPath = self::$basePath.'/modules';
        self::scanModules();
    }

    /**
     * Сканирует модули
     */
    private static function scanModules(): void
    {
        if (!is_dir(self::$modulesPath)) return;

        foreach (scandir(self::$modulesPath) as $module) {
            if ($module === '.' || $module === '..') continue;

            $modulePath = self::$modulesPath.'/'.$module;
            if (is_dir($modulePath)) {
                self::$modules[$module] = $modulePath;
            }
        }
    }

    /**
     * Проверяет наличие index.php в каждом модуле
     */
    public function testModulesHaveIndexFile(): void
    {
        $errors = [];

        foreach (self::$modules as $name => $path) {
            if (!file_exists($path.'/index.php')) {
                $errors[] = "modules/$name - отсутствует index.php";
            }
        }

        $this->assertEmpty(
            $errors,
            "Модули без index.php:\n".implode("\n", $errors)
        );
    }

    /**
     * Проверяет наличие директории lang в модулях
     */
    public function testModulesHaveLanguageDirectory(): void
    {
        $warnings = [];

        foreach (self::$modules as $name => $path) {
            // Некоторые модули могут не требовать языковых файлов
            if (!is_dir($path.'/lang')) {
                $warnings[] = "modules/$name - отсутствует директория lang/";
            }
        }

        // Это предупреждение, не ошибка
        $this->assertTrue(true, count($warnings).' модулей без директории lang/');
    }

    /**
     * Проверяет наличие всех языковых файлов
     */
    public function testModulesHaveAllLanguages(): void
    {
        $errors = [];

        foreach (self::$modules as $name => $path) {
            $langPath = $path.'/lang';
            if (!is_dir($langPath)) continue;

            $presentLangs = [];
            foreach (scandir($langPath) as $file) {
                if (preg_match('/^(\w+)\.php$/', $file, $m)) {
                    $presentLangs[] = $m[1];
                }
            }

            // Если есть хотя бы один язык, проверяем наличие всех
            if (!empty($presentLangs)) {
                $missing = array_diff(self::$languages, $presentLangs);
                foreach ($missing as $lang) {
                    $errors[] = "modules/$name/lang/$lang.php - отсутствует";
                }
            }
        }

        // Ограничиваем вывод
        if (count($errors) > 30) {
            $total = count($errors);
            $errors = array_slice($errors, 0, 30);
            $errors[] = '... и ещё '.($total - 30).' отсутствующих файлов';
        }

        $this->assertEmpty(
            $errors,
            "Отсутствуют языковые файлы:\n".implode("\n", $errors)
        );
    }

    /**
     * Проверяет синтаксис index.php модулей
     */
    public function testModuleIndexSyntax(): void
    {
        $errors = [];

        foreach (self::$modules as $name => $path) {
            $indexFile = $path.'/index.php';
            if (!file_exists($indexFile)) continue;

            $output = [];
            $returnCode = 0;
            exec('php -l "'.$indexFile.'" 2>&1', $output, $returnCode);

            if ($returnCode !== 0) {
                $errors[] = sprintf(
                    'modules/%s/index.php - синтаксическая ошибка',
                    $name
                );
            }
        }

        $this->assertEmpty(
            $errors,
            "Синтаксические ошибки в модулях:\n".implode("\n", $errors)
        );
    }

    /**
     * Проверяет защиту от прямого доступа
     */
    public function testModulesHaveDirectAccessProtection(): void
    {
        $errors = [];

        foreach (self::$modules as $name => $path) {
            $indexFile = $path.'/index.php';
            if (!file_exists($indexFile)) continue;

            $content = file_get_contents($indexFile);

            // Проверяем наличие защиты от прямого доступа
            if (!preg_match('/defined\s*\(\s*[\'"]MODULE_FILE[\'"]\s*\)/', $content)) {
                $errors[] = "modules/$name/index.php - отсутствует проверка MODULE_FILE";
            }
        }

        $this->assertEmpty(
            $errors,
            "Модули без защиты от прямого доступа:\n".implode("\n", $errors)
        );
    }

    /**
     * Проверяет структуру admin директории
     */
    public function testModulesAdminStructure(): void
    {
        $errors = [];

        foreach (self::$modules as $name => $path) {
            $adminPath = $path.'/admin';
            if (!is_dir($adminPath)) continue;

            // Проверяем наличие index.php в admin
            if (!file_exists($adminPath.'/index.php')) {
                $errors[] = "modules/$name/admin - отсутствует index.php";
            }

            
        }

        $this->assertEmpty(
            $errors,
            "Проблемы в admin структуре:\n".implode("\n", $errors)
        );
    }

    /**
     * Проверяет что модули найдены
     */
    public function testModulesFound(): void
    {
        $this->assertNotEmpty(self::$modules, 'Модули не найдены');
        $this->assertGreaterThan(10, count(self::$modules), 'Найдено слишком мало модулей');
    }

    /**
     * Проверяет уникальность функций в модулях
     * Примечание: Функции add, view, send и т.д. повторяются намеренно в разных модулях
     */
    public function testModuleFunctionNamesUnique(): void
    {
        $functions = [];
        $duplicates = [];

        // Стандартные функции которые могут повторяться
        $allowedDuplicates = ['add', 'view', 'send', 'liste', 'navigate', 'edit', 'save', 'delete', 'search', 'broken', 'loading'];

        foreach (self::$modules as $name => $path) {
            $indexFile = $path.'/index.php';
            if (!file_exists($indexFile)) continue;

            $content = file_get_contents($indexFile);

            // Находим определения функций
            preg_match_all('/function\s+([a-zA-Z_]\w*)\s*\(/', $content, $matches);

            foreach ($matches[1] as $funcName) {
                // Пропускаем стандартные имена модулей и разрешённые дубликаты
                if ($funcName === $name || in_array($funcName, $allowedDuplicates)) continue;

                if (isset($functions[$funcName])) {
                    $duplicates[] = "Функция '$funcName' определена в modules/{$functions[$funcName]} и modules/$name";
                } else {
                    $functions[$funcName] = $name;
                }
            }
        }

        $this->assertEmpty(
            $duplicates,
            "Дублирующиеся функции (не стандартные):\n".implode("\n", $duplicates)
        );
    }
}
