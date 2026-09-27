<?php

use PHPUnit\Framework\TestCase;

# Validates the language files: sprintf placeholders, translation completeness, syntax, encoding and presence of every language
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

    # Collects the language files of lang/, admin/lang/ and every module lang/ directory for each supported language
    private static function scanLanguageFiles(): void
    {
        $directories = [
            self::$basePath.'/lang',
            self::$basePath.'/admin/lang',
        ];

        $modulesDir = self::$basePath.'/modules';
        if (is_dir($modulesDir)) {
            foreach (scandir($modulesDir) as $module) {
                if ($module === '.' || $module === '..') continue;
                $langDir = $modulesDir.'/'.$module.'/lang';
                if (is_dir($langDir)) {
                    $directories[] = $langDir;
                }
            }
        }

        foreach ($directories as $dir) {
            if (!is_dir($dir)) continue;

            foreach (self::$languages as $lang) {
                $file = $dir.'/'.$lang.'.php';
                if (file_exists($file)) {
                    self::$languageFiles[] = [
                        'path' => $file,
                        'lang' => $lang,
                        'dir' => str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $dir)
                    ];
                }
            }
        }
    }

    # Parses the define() constants of every language file with value, line and file
    private static function parseConstants(): void
    {
        foreach (self::$languageFiles as $fileInfo) {
            $content = file_get_contents($fileInfo['path']);

            preg_match_all('/define\s*\(\s*["\']([^"\']+)["\']\s*,\s*["\'](.*)["\']\s*\)/Us', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                $constName = $match[1][0];
                $constValue = $match[2][0];
                $offset = $match[0][1];
                $line = substr_count(substr($content, 0, $offset), "\n") + 1;

                $key = $fileInfo['dir'].'/'.$fileInfo['lang'];

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

    # Checks sprintf placeholders: a space after % is an error; a lone % without a placeholder is only inspected, since it may be plain text
    public function testSprintfPlaceholders(): void
    {
        $errors = [];

        foreach (self::$constants as $fileKey => $constants) {
            foreach ($constants as $name => $info) {
                $value = $info['value'];

                if (preg_match('/% \d+\\\?\$[sdf]/', $value)) {
                    $errors[] = sprintf(
                        "%s:%d - константа '%s' содержит некорректный плейсхолдер (пробел после %%)",
                        str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $info['file']),
                        $info['line'],
                        $name
                    );
                }
            }
        }

        $this->assertEmpty(
            $errors,
            "Найдены ошибки в плейсхолдерах sprintf:\n".implode("\n", $errors)
        );
    }

    # Compares the constant sets per directory against Russian (or the first language) as reference and lists at most 20 problems
    public function testTranslationCompleteness(): void
    {
        $errors = [];
        $directories = [];

        foreach (self::$constants as $fileKey => $constants) {
            $parts = explode('/', $fileKey);
            $lang = array_pop($parts);
            $dir = implode('/', $parts);

            if (!isset($directories[$dir])) {
                $directories[$dir] = [];
            }
            $directories[$dir][$lang] = array_keys($constants);
        }

        foreach ($directories as $dir => $langs) {
            if (count($langs) < 2) continue;

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

        if (count($errors) > 20) {
            $total = count($errors);
            $errors = array_slice($errors, 0, 20);
            $errors[] = '... и ещё '.($total - 20).' проблем';
        }

        $this->assertEmpty(
            $errors,
            "Найдены неполные переводы:\n".implode("\n", $errors)
        );
    }

    # Checks the syntax of every language file with php -l
    public function testLanguageFileSyntax(): void
    {
        $errors = [];

        foreach (self::$languageFiles as $fileInfo) {
            $output = [];
            $returnCode = 0;
            exec('php -l "'.$fileInfo['path'].'" 2>&1', $output, $returnCode);

            if ($returnCode !== 0) {
                $errors[] = sprintf(
                    '%s - синтаксическая ошибка: %s',
                    str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $fileInfo['path']),
                    implode(' ', $output)
                );
            }
        }

        $this->assertEmpty(
            $errors,
            "Найдены синтаксические ошибки:\n".implode("\n", $errors)
        );
    }

    # Checks that language files are valid UTF-8 and carry no BOM
    public function testLanguageFilesEncoding(): void
    {
        $errors = [];

        foreach (self::$languageFiles as $fileInfo) {
            $content = file_get_contents($fileInfo['path']);
            $relative = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $fileInfo['path']);

            if (!mb_check_encoding($content, 'UTF-8')) {
                $errors[] = "$relative - некорректная кодировка (не UTF-8)";
            }

            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $errors[] = "$relative - содержит BOM";
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с кодировкой:\n".implode("\n", $errors)
        );
    }

    # Checks that every language directory holds a file for each supported language and lists at most 20 missing files
    public function testAllLanguagesPresent(): void
    {
        $errors = [];
        $directories = [];

        foreach (self::$languageFiles as $fileInfo) {
            $dir = $fileInfo['dir'];
            if (!isset($directories[$dir])) {
                $directories[$dir] = [];
            }
            $directories[$dir][] = $fileInfo['lang'];
        }

        foreach ($directories as $dir => $presentLangs) {
            $missing = array_diff(self::$languages, $presentLangs);
            foreach ($missing as $lang) {
                $errors[] = sprintf('%s/%s.php - файл отсутствует', $dir, $lang);
            }
        }

        if (count($errors) > 20) {
            $total = count($errors);
            $errors = array_slice($errors, 0, 20);
            $errors[] = '... и ещё '.($total - 20).' отсутствующих файлов';
        }

        $this->assertEmpty(
            $errors,
            "Отсутствуют языковые файлы:\n".implode("\n", $errors)
        );
    }

    # Skipped: the unused constant check is replaced by the token-based audit in LanguageConstantsUsageTest
    public function testNoUnusedConstants(): void
    {
        $this->markTestSkipped(
            'Проверка отключена: заменена на token-based аудит в LanguageConstantsUsageTest::testLanguageConstantsUsageSummary'
        );
    }

    # Checks that language files were found
    public function testLanguageFilesFound(): void
    {
        $this->assertNotEmpty(
            self::$languageFiles,
            'Языковые файлы не найдены'
        );
    }

    # _BACK labels the pager, the admin buttons and the document tree, so it has to say back and never ago
    public function testBackMeansBack(): void
    {
        $want = ['de' => 'Zurück', 'en' => 'Back', 'fr' => 'Retour', 'pl' => 'Wstecz', 'ru' => 'Назад', 'uk' => 'Назад'];
        foreach ($want as $lang => $word) {
            $this->assertStringContainsString("define('_BACK','".$word."');", (string)file_get_contents(dirname(__DIR__).'/lang/'.$lang.'.php'), $lang);
        }
    }
}
