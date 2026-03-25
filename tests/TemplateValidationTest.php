<?php
/**
 * Тест валидации шаблонов
 * Проверяет плейсхолдеры и структуру шаблонов
 */

use PHPUnit\Framework\TestCase;

class TemplateValidationTest extends TestCase
{
    private static string $basePath;
    private static string $templatesPath;
    private static array $templates = [];
    private static array $knownPlaceholders = [];

    public static function setUpBeforeClass(): void
    {
        self::$basePath = dirname(__DIR__);
        self::$templatesPath = self::$basePath.'/templates';
        self::loadKnownPlaceholders();
        self::scanTemplates();
    }

    /**
     * Загружает известные плейсхолдеры из template.php
     */
    private static function loadKnownPlaceholders(): void
    {
        $templateFile = self::$basePath.'/core/classes/template.php';
        if (!file_exists($templateFile)) return;

        $content = file_get_contents($templateFile);

        preg_match_all('/\{\s*%\s*(\w+)\s*%\s*\}/', $content, $matches);
        self::$knownPlaceholders = array_unique($matches[1]);

        // Стандартные плейсхолдеры
        $standard = [
            'theme', 'lang', 'sitename', 'logo', 'homeurl', 'slogan',
            'home', 'account', 'news', 'admin', 'search', 'login',
            'logout', 'register', 'profile', 'settings', 'messages',
            'title', 'content', 'text', 'name', 'date', 'time',
            'user', 'email', 'url', 'id', 'avatar', 'comment'
        ];
        self::$knownPlaceholders = array_unique(array_merge(self::$knownPlaceholders, $standard));
    }

    /**
     * Сканирует шаблоны
     */
    private static function scanTemplates(): void
    {
        if (!is_dir(self::$templatesPath)) return;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::$templatesPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $ext = $file->getExtension();
            if (!in_array($ext, ['html', 'htm', 'tpl', 'php'])) continue;

            self::$templates[] = $file->getPathname();
        }
    }

    /**
     * Проверяет синтаксис условных конструкций в шаблонах
     */
    public function testTemplateConditionalSyntax(): void
    {
        $errors = [];

        foreach (self::$templates as $file) {
            $content = file_get_contents($file);
            $relativePath = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $file);

            // Считаем открывающие и закрывающие теги
            preg_match_all('/\{%\s*if\s+[^%]+%\}/', $content, $ifMatches);
            preg_match_all('/\{%\s*endif\s*%\}/', $content, $endifMatches);

            $ifCount = count($ifMatches[0]);
            $endifCount = count($endifMatches[0]);

            if ($ifCount !== $endifCount) {
                $errors[] = sprintf(
                    '%s - несбалансированные if/endif (%d if, %d endif)',
                    $relativePath,
                    $ifCount,
                    $endifCount
                );
            }
        }

        $this->assertEmpty(
            $errors,
            "Ошибки в условных конструкциях:\n".implode("\n", $errors)
        );
    }

    /**
     * Проверяет корректность HTML в шаблонах
     */
    public function testTemplateHtmlStructure(): void
    {
        $errors = [];

        foreach (self::$templates as $file) {
            // Пропускаем PHP файлы
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') continue;

            // Пропускаем open/close шаблоны (они парные)
            $fileName = basename($file);
            if (preg_match('/(^|-)(open|close)\.html$/', $fileName)) continue;

            $content = file_get_contents($file);
            $relativePath = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $file);

            // Убираем template-токены перед подсчётом HTML-тегов
            $content = preg_replace('/\{%[^%]*%\}/', '', $content);

            // Проверяем незакрытые теги (базовая проверка)
            $openTags = [];
            $selfClosing = ['br', 'hr', 'img', 'input', 'meta', 'link', 'area', 'base', 'col', 'embed', 'param', 'source', 'track', 'wbr'];

            preg_match_all('/<(\w+)(?:\s[^>]*)?>/', $content, $openMatches);
            preg_match_all('/<\/(\w+)>/', $content, $closeMatches);

            foreach ($openMatches[1] as $tag) {
                $tag = strtolower($tag);
                if (!in_array($tag, $selfClosing)) {
                    if (!isset($openTags[$tag])) $openTags[$tag] = 0;
                    $openTags[$tag]++;
                }
            }

            foreach ($closeMatches[1] as $tag) {
                $tag = strtolower($tag);
                if (isset($openTags[$tag])) {
                    $openTags[$tag]--;
                }
            }

            // Проверяем только критичные теги
            $criticalTags = ['div', 'table', 'tr', 'td', 'form', 'ul', 'ol', 'li'];
            foreach ($criticalTags as $tag) {
                if (isset($openTags[$tag]) && $openTags[$tag] !== 0) {
                    $errors[] = sprintf(
                        '%s - несбалансированный тег <%s> (разница: %d)',
                        $relativePath,
                        $tag,
                        $openTags[$tag]
                    );
                }
            }
        }

        // Ограничиваем вывод
        if (count($errors) > 20) {
            $total = count($errors);
            $errors = array_slice($errors, 0, 20);
            $errors[] = '... и ещё '.($total - 20).' проблем';
        }

        $this->assertEmpty(
            $errors,
            "Проблемы HTML структуры:\n".implode("\n", $errors)
        );
    }

    /**
     * Проверяет наличие обязательных шаблонов
     */
    public function testRequiredTemplatesExist(): void
    {
        $errors = [];

        // Получаем список тем
        $themes = [];
        foreach (scandir(self::$templatesPath) as $item) {
            if ($item === '.' || $item === '..') continue;
            if (is_dir(self::$templatesPath.'/'.$item)) {
                $themes[] = $item;
            }
        }

        // Обязательные фрагменты для каждой frontend-темы
        $required = ['fragments/basic.html', 'fragments/title.html'];

        foreach ($themes as $theme) {
            // Пропускаем admin тему
            if ($theme === 'admin') continue;

            $themePath = self::$templatesPath.'/'.$theme;

            foreach ($required as $template) {
                if (!file_exists($themePath.'/'.$template)) {
                    $errors[] = "templates/$theme/$template - отсутствует";
                }
            }
        }

        $this->assertEmpty(
            $errors,
            "Отсутствуют обязательные шаблоны:\n".implode("\n", $errors)
        );
    }

    /**
     * Проверяет кодировку шаблонов
     */
    public function testTemplateEncoding(): void
    {
        $errors = [];

        foreach (self::$templates as $file) {
            $content = file_get_contents($file);
            $relativePath = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $file);

            // Проверяем на невалидный UTF-8
            if (!mb_check_encoding($content, 'UTF-8')) {
                $errors[] = "$relativePath - некорректная кодировка (не UTF-8)";
            }

            // Проверяем на BOM
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $errors[] = "$relativePath - содержит BOM";
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с кодировкой:\n".implode("\n", $errors)
        );
    }

    /**
     * Проверяет что шаблоны найдены
     */
    public function testTemplatesFound(): void
    {
        $this->assertNotEmpty(self::$templates, 'Шаблоны не найдены');
        $this->assertGreaterThan(10, count(self::$templates), 'Найдено слишком мало шаблонов');
    }
}
