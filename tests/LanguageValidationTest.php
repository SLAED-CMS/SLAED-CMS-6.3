<?php
/**
 * Тест валидации языковых файлов
 * Проверяет плейсхолдеры sprintf и полноту переводов
 */

use PHPUnit\Framework\TestCase;

class LanguageValidationTest extends TestCase
{
    private static string $basePath;
    private static array $languages = ['ru', 'en', 'de', 'fr', 'pl', 'uk'];
    private static array $languageFiles = [];
    private static array $constants = [];

    public static function setUpBeforeClass(): void
    {
        self::$basePath = dirname(__DIR__);
        self::scanLanguageFiles();
        self::parseConstants();
    }

    /**
     * Сканирует все языковые файлы
     */
    private static function scanLanguageFiles(): void
    {
        $directories = [
            self::$basePath . '/language',
            self::$basePath . '/admin/language',
        ];

        // Добавляем языковые директории модулей
        $modulesDir = self::$basePath . '/modules';
        if (is_dir($modulesDir)) {
            foreach (scandir($modulesDir) as $module) {
                if ($module === '.' || $module === '..') continue;
                $langDir = $modulesDir . '/' . $module . '/language';
                if (is_dir($langDir)) {
                    $directories[] = $langDir;
                }
            }
        }

        foreach ($directories as $dir) {
            if (!is_dir($dir)) continue;

            foreach (self::$languages as $lang) {
                $file = $dir . '/' . $lang . '.php';
                if (file_exists($file)) {
                    self::$languageFiles[] = [
                        'path' => $file,
                        'lang' => $lang,
                        'dir' => str_replace(self::$basePath . DIRECTORY_SEPARATOR, '', $dir)
                    ];
                }
            }
        }
    }

    /**
     * Парсит константы из языковых файлов
     */
    private static function parseConstants(): void
    {
        foreach (self::$languageFiles as $fileInfo) {
            $content = file_get_contents($fileInfo['path']);

            // Находим все define() константы
            preg_match_all('/define\s*\(\s*["\']([^"\']+)["\']\s*,\s*["\'](.*)["\']\s*\)/Us', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                $constName = $match[1][0];
                $constValue = $match[2][0];
                $offset = $match[0][1];
                $line = substr_count(substr($content, 0, $offset), "\n") + 1;

                $key = $fileInfo['dir'] . '/' . $fileInfo['lang'];

                if (!isset(self::$constants[$key])) {
                    self::$constants[$key] = [];
                }

                self::$constants[$key][$constName] = [
                    'value' => $constValue,
                    'line' => $line,
                    'file' => $fileInfo['path']
                ];
            }
        }
    }

    /**
     * Проверяет корректность плейсхолдеров sprintf
     */
    public function testSprintfPlaceholders(): void
    {
        $errors = [];

        foreach (self::$constants as $fileKey => $constants) {
            foreach ($constants as $name => $info) {
                $value = $info['value'];

                // Проверяем на некорректные плейсхолдеры (пробел после %)
                if (preg_match('/% \d+\\\?\$[sdf]/', $value)) {
                    $errors[] = sprintf(
                        "%s:%d - константа '%s' содержит некорректный плейсхолдер (пробел после %%)",
                        str_replace(self::$basePath . DIRECTORY_SEPARATOR, '', $info['file']),
                        $info['line'],
                        $name
                    );
                }

                // Проверяем на одиночный % без плейсхолдера (кроме %%)
                if (preg_match('/%(?![%\d])/', $value) && !preg_match('/%\d*\\\?\$?[sdfbcoxXeEgGuU]/', $value)) {
                    // Пропускаем если это просто символ процента в тексте
                    if (!preg_match('/\d+\s*%/', $value) && strpos($value, '%%') === false) {
                        // Это может быть ошибкой, но не всегда
                    }
                }
            }
        }

        $this->assertEmpty(
            $errors,
            "Найдены ошибки в плейсхолдерах sprintf:\n" . implode("\n", $errors)
        );
    }

    /**
     * Проверяет полноту переводов между языками
     */
    public function testTranslationCompleteness(): void
    {
        $errors = [];
        $directories = [];

        // Группируем константы по директориям
        foreach (self::$constants as $fileKey => $constants) {
            $parts = explode('/', $fileKey);
            $lang = array_pop($parts);
            $dir = implode('/', $parts);

            if (!isset($directories[$dir])) {
                $directories[$dir] = [];
            }
            $directories[$dir][$lang] = array_keys($constants);
        }

        // Сравниваем наборы констант между языками
        foreach ($directories as $dir => $langs) {
            if (count($langs) < 2) continue;

            // Берём русский как эталон, или первый доступный
            $referenceLang = isset($langs['ru']) ? 'ru' : array_key_first($langs);
            $referenceConstants = $langs[$referenceLang];

            foreach ($langs as $lang => $constants) {
                if ($lang === $referenceLang) continue;

                $missing = array_diff($referenceConstants, $constants);
                $extra = array_diff($constants, $referenceConstants);

                foreach ($missing as $const) {
                    $errors[] = sprintf(
                        "%s/%s.php - отсутствует константа '%s' (есть в %s)",
                        $dir,
                        $lang,
                        $const,
                        $referenceLang
                    );
                }
            }
        }

        // Ограничиваем вывод первыми 20 ошибками
        if (count($errors) > 20) {
            $total = count($errors);
            $errors = array_slice($errors, 0, 20);
            $errors[] = "... и ещё " . ($total - 20) . " проблем";
        }

        $this->assertEmpty(
            $errors,
            "Найдены неполные переводы:\n" . implode("\n", $errors)
        );
    }

    /**
     * Проверяет синтаксис языковых файлов
     */
    public function testLanguageFileSyntax(): void
    {
        $errors = [];

        foreach (self::$languageFiles as $fileInfo) {
            $output = [];
            $returnCode = 0;
            exec('php -l "' . $fileInfo['path'] . '" 2>&1', $output, $returnCode);

            if ($returnCode !== 0) {
                $errors[] = sprintf(
                    "%s - синтаксическая ошибка: %s",
                    str_replace(self::$basePath . DIRECTORY_SEPARATOR, '', $fileInfo['path']),
                    implode(' ', $output)
                );
            }
        }

        $this->assertEmpty(
            $errors,
            "Найдены синтаксические ошибки:\n" . implode("\n", $errors)
        );
    }

    /**
     * Проверяет кодировку языковых файлов
     */
    public function testLanguageFilesEncoding(): void
    {
        $errors = [];

        foreach (self::$languageFiles as $fileInfo) {
            $content = file_get_contents($fileInfo['path']);
            $relative = str_replace(self::$basePath . DIRECTORY_SEPARATOR, '', $fileInfo['path']);

            if (!mb_check_encoding($content, 'UTF-8')) {
                $errors[] = "$relative - некорректная кодировка (не UTF-8)";
            }

            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $errors[] = "$relative - содержит BOM";
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с кодировкой:\n" . implode("\n", $errors)
        );
    }

    /**
     * Проверяет наличие языковых файлов для всех языков
     */
    public function testAllLanguagesPresent(): void
    {
        $errors = [];
        $directories = [];

        // Группируем по директориям
        foreach (self::$languageFiles as $fileInfo) {
            $dir = $fileInfo['dir'];
            if (!isset($directories[$dir])) {
                $directories[$dir] = [];
            }
            $directories[$dir][] = $fileInfo['lang'];
        }

        // Проверяем наличие всех языков
        foreach ($directories as $dir => $presentLangs) {
            $missing = array_diff(self::$languages, $presentLangs);
            foreach ($missing as $lang) {
                $errors[] = sprintf("%s/%s.php - файл отсутствует", $dir, $lang);
            }
        }

        // Ограничиваем вывод
        if (count($errors) > 20) {
            $total = count($errors);
            $errors = array_slice($errors, 0, 20);
            $errors[] = "... и ещё " . ($total - 20) . " отсутствующих файлов";
        }

        $this->assertEmpty(
            $errors,
            "Отсутствуют языковые файлы:\n" . implode("\n", $errors)
        );
    }

    /**
     * Проверяет что все константы используются в коде
     */
    public function testNoUnusedConstants(): void
    {
        $searchDirs = ['core', 'admin', 'modules', 'blocks', 'templates', 'setup'];
        $allFiles = [];

        foreach ($searchDirs as $dir) {
            $path = self::$basePath . '/' . $dir;
            if (!is_dir($path)) continue;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            foreach ($it as $file) {
                if ($file->getExtension() === 'php') {
                    $allFiles[] = $file->getPathname();
                }
            }
        }

        // Root PHP files
        foreach (glob(self::$basePath . '/*.php') as $f) {
            $allFiles[] = $f;
        }

        // Exclude all language directories
        $allFiles = array_filter($allFiles, function ($f) {
            $real = realpath($f);
            return $real !== false && !preg_match('#[/\\\\]language[/\\\\]#i', $real);
        });

        $allContent = '';
        foreach ($allFiles as $file) {
            $allContent .= file_get_contents($file) . "\n";
        }

        $errors = [];

        // Find all ru.php language files dynamically
        $langIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::$basePath));
        $langFiles = [];
        foreach ($langIterator as $f) {
            if ($f->getFilename() === 'ru.php' && preg_match('#[/\\\\]language[/\\\\]#i', $f->getPathname())) {
                $langFiles[] = $f->getPathname();
            }
        }

        foreach ($langFiles as $langPath) {
            $langFile = str_replace(self::$basePath . DIRECTORY_SEPARATOR, '', $langPath);

            $content = file_get_contents($langPath);
            preg_match_all('/define\(\s*["\'](_[A-Z0-9_]+)["\']/', $content, $matches);
            $constants = array_unique($matches[1]);

            foreach ($constants as $const) {
                if (strpos($allContent, $const) === false) {
                    $errors[] = sprintf('%s - неиспользуемая константа %s', $langFile, $const);
                }
            }
        }

        $this->assertEmpty(
            $errors,
            "Найдены неиспользуемые константы:\n" . implode("\n", $errors)
        );
    }

    /**
     * Проверяет что языковые файлы найдены
     */
    public function testLanguageFilesFound(): void
    {
        $this->assertNotEmpty(
            self::$languageFiles,
            'Языковые файлы не найдены'
        );
    }
}
