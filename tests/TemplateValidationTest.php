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

    # Returns a normalized repository-relative path and rejects paths outside the repository
    private static function getRelativePath(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', self::$basePath), '/');
        $norm = str_replace('\\', '/', $path);
        if (!str_starts_with($norm, $base.'/')) {
            throw new RuntimeException('Path is outside the repository: '.$norm);
        }
        return substr($norm, strlen($base) + 1);
    }

    # Discovers installed frontend themes without assuming names or shared implementations
    private static function getFrontendThemes(): array
    {
        $themes = [];
        foreach (scandir(self::$templatesPath) ?: [] as $theme) {
            if ($theme === '.' || $theme === '..' || $theme === 'admin') continue;
            if (is_dir(self::$templatesPath.'/'.$theme)) $themes[] = $theme;
        }
        sort($themes);
        return $themes;
    }

    # Maps a PHP or theme-hook emitter to the theme contracts it can use
    private static function getThemesForPath(string $path, array $front): array
    {
        if (str_starts_with($path, 'admin/') || preg_match('#^modules/[^/]+/admin/#', $path)) return ['admin'];
        if (str_starts_with($path, 'blocks/') || preg_match('#^modules/[^/]+/index\.php$#', $path)) return $front;
        if (preg_match('#^templates/([^/]+)/#', $path, $match)) return [$match[1]];
        return [];
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

        // Обязательные шаблоны для каждой frontend-темы
        $required = [
            'fragments/title.html',
            'partials/content-list.html',
            'partials/view.html',
            'pages/module.html',
            'layouts/app.html',
        ];

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

    public function testThemeRuntimeContractsAreIndependent(): void
    {
        $errors = [];
        $themes = array_merge(['admin'], self::getFrontendThemes());
        $this->assertGreaterThan(1, count($themes), 'Admin and at least one frontend theme must be installed');
        foreach ($themes as $theme) {
            foreach (['layouts', 'pages', 'partials', 'fragments', 'assets/css/base.css', 'assets/css/theme.css'] as $item) {
                $path = self::$templatesPath.'/'.$theme.'/'.$item;
                if (!file_exists($path)) $errors[] = 'templates/'.$theme.'/'.$item.' - отсутствует';
            }
        }
        $this->assertEmpty($errors, "Нарушен самостоятельный runtime-контракт темы:\n".implode("\n", $errors));
    }

    public function testRelativePathNormalizationAndEmitterClassification(): void
    {
        $base = str_replace('/', '\\', self::$basePath);
        $front = self::getFrontendThemes();
        $this->assertSame('modules/news/index.php', self::getRelativePath($base.'\\modules\\news\\index.php'));
        $this->assertSame('templates/lite/partials/main-slider.html', self::getRelativePath($base.'\\templates/lite\\partials\\main-slider.html'));
        $this->assertSame('modules/news/index.php', self::getRelativePath(str_replace('\\', '/', self::$basePath).'/modules/news/index.php'));
        $this->assertSame($front, self::getThemesForPath('modules/news/index.php', $front));
        $this->assertSame(['admin'], self::getThemesForPath('admin/modules/news.php', $front));
        $this->assertSame(['admin'], self::getThemesForPath('modules/news/admin/index.php', $front));
    }

    public function testConcreteTemplateReferencesExist(): void
    {
        $errors = [];
        $front = self::getFrontendThemes();
        $roots = ['admin', 'blocks', 'modules', 'templates/admin'];
        $found = 0;
        $known = 0;
        $inner = 0;
        foreach ($front as $theme) $roots[] = 'templates/'.$theme;

        foreach ($roots as $root) {
            $path = self::$basePath.'/'.$root;
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!in_array($file->getExtension(), ['php', 'html'], true)) {
                    continue;
                }
                $relativePath = self::getRelativePath($file->getPathname());
                $content = file_get_contents($file->getPathname());
                if (!preg_match_all("/getHtml(Frag|Part)\(\s*'([^']+)'/", $content, $matches, PREG_SET_ORDER)) {
                    continue;
                }
                foreach ($matches as $match) {
                    $found++;
                    $type = $match[1] === 'Frag' ? 'fragments' : 'partials';
                    $name = $match[2];
                    $themes = self::getThemesForPath($relativePath, $front);
                    if ($themes !== []) $known++;
                    foreach ($themes as $theme) {
                        $target = self::$templatesPath.'/'.$theme.'/'.$type.'/'.$name.'.html';
                        if (!is_file($target)) {
                            $errors[] = $relativePath.' -> templates/'.$theme.'/'.$type.'/'.$name.'.html';
                        }
                    }
                }
            }
        }

        foreach (self::$templates as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'html') {
                continue;
            }
            $relativePath = self::getRelativePath($file);
            if (!preg_match('#^templates/([^/]+)/#', $relativePath, $themeMatch)) {
                continue;
            }
            $theme = $themeMatch[1];
            $content = file_get_contents($file);
            if (preg_match_all("/\{%\s*(?:include|extends|component)\s+'([^']+)'/", $content, $matches)) {
                foreach ($matches[1] as $target) {
                    $inner++;
                    if (str_starts_with($target, 'templates/') || str_contains('/'.$target.'/', '/../')) {
                        $errors[] = $relativePath.' -> forbidden cross-theme template path '.$target;
                        continue;
                    }
                    if (!is_file(self::$templatesPath.'/'.$theme.'/'.$target)) {
                        $errors[] = $relativePath.' -> templates/'.$theme.'/'.$target;
                    }
                }
            }
        }

        $errors = array_values(array_unique($errors));
        sort($errors);

        $this->assertGreaterThan(0, $found, 'Не найдено ни одной PHP template reference');
        $this->assertGreaterThan(0, $known, 'Ни одна PHP template reference не классифицирована по теме');
        $this->assertGreaterThan(0, $inner, 'Не найдено ни одной include/extends/component template reference');
        $this->assertEmpty(
            $errors,
            "Отсутствуют используемые template references:\n".implode("\n", $errors)
        );
    }

    public function testThemesDoNotReferenceOtherThemeAssets(): void
    {
        $errors = [];
        $themes = array_merge(['admin'], self::getFrontendThemes());
        foreach ($themes as $theme) {
            $root = self::$templatesPath.'/'.$theme;
            $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iter as $file) {
                if (!in_array($file->getExtension(), ['html', 'css', 'js', 'php'], true)) continue;
                $text = file_get_contents($file->getPathname());
                foreach ($themes as $other) {
                    if ($other === $theme) continue;
                    if (preg_match('#(?:@import\s+[^;]*|(?:src|href)\s*=\s*["\'][^"\']*)templates/'.$other.'/#i', $text)) {
                        $errors[] = self::getRelativePath($file->getPathname()).' -> templates/'.$other.'/';
                    }
                }
                if (is_link($file->getPathname())) $errors[] = self::getRelativePath($file->getPathname()).' -> symbolic link';
            }
        }
        $this->assertEmpty($errors, "Обнаружена межтемовая зависимость:\n".implode("\n", $errors));
    }

    public function testRuntimeHtmlDoesNotHardcodeItsThemeDirectory(): void
    {
        $errors = [];
        foreach (self::$templates as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'html') continue;
            $relative = self::getRelativePath($file);
            if (!preg_match('#^templates/([^/]+)/(?:layouts|pages|partials|fragments)/#', $relative, $match)) continue;
            $content = file_get_contents($file);
            if (str_contains($content, 'templates/'.$match[1].'/')) $errors[] = $relative;
        }
        $this->assertEmpty($errors, "Runtime HTML содержит hardcoded theme directory:\n".implode("\n", $errors));
    }

    public function testHtmlTemplatesDoNotContainInlineStyles(): void
    {
        $errors = [];
        $paths = self::$templates;

        $moduleTemplatePath = self::$basePath.'/modules';
        if (is_dir($moduleTemplatePath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($moduleTemplatePath, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                $path = str_replace('\\', '/', $file->getPathname());
                if ($file->getExtension() === 'html' && str_contains($path, '/templates/')) {
                    $paths[] = $file->getPathname();
                }
            }
        }

        foreach (array_unique($paths) as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'html') {
                continue;
            }
            $content = file_get_contents($file);
            $relative = str_replace('\\', '/', str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $file));
            // Block-level <style> tags are never allowed inside templates
            if (preg_match('/<style\b|<\/style>/i', $content)) {
                $errors[] = $relative.' (<style> block)';
            }
            // Inline style="" is allowed only when it injects a dynamic template value ({{ ... }}),
            // e.g. progress widths or avatar URLs; static inline styling must live in CSS
            if (preg_match_all('/style\s*=\s*(["\'])(.*?)\1/is', $content, $matches)) {
                foreach ($matches[2] as $value) {
                    if (!str_contains($value, '{{')) {
                        $errors[] = $relative.' (static inline style: '.trim($value).')';
                    }
                }
            }
        }

        sort($errors);

        $this->assertEmpty(
            $errors,
            "Статичные inline styles должны быть вынесены в CSS (динамические {{ }} допустимы):\n".implode("\n", $errors)
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
