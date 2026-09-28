<?php

use PHPUnit\Framework\TestCase;

# Validates the templates: placeholders, conditionals, HTML structure, required files, theme independence, references, styles and encoding
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
            if ($theme === '.' || $theme === '..' || $theme === 'admin' || isTreeSkipped(self::$templatesPath.'/'.$theme)) continue;
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

    # Loads the placeholders known from core/classes/template.php and adds the standard ones
    private static function loadKnownPlaceholders(): void
    {
        $templateFile = self::$basePath.'/core/classes/template.php';
        if (!file_exists($templateFile)) return;

        $content = file_get_contents($templateFile);

        preg_match_all('/\{\s*%\s*(\w+)\s*%\s*\}/', $content, $matches);
        self::$knownPlaceholders = array_unique($matches[1]);

        $standard = [
            'theme', 'lang', 'sitename', 'logo', 'homeurl', 'slogan',
            'home', 'account', 'news', 'admin', 'search', 'login',
            'logout', 'register', 'profile', 'settings', 'messages',
            'title', 'content', 'text', 'name', 'date', 'time',
            'user', 'email', 'url', 'id', 'avatar', 'comment'
        ];
        self::$knownPlaceholders = array_unique(array_merge(self::$knownPlaceholders, $standard));
    }

    # Collects the html, htm, tpl and php files under templates/
    private static function scanTemplates(): void
    {
        if (!is_dir(self::$templatesPath)) return;

        $iterator = getTreeFiles(self::$templatesPath);

        foreach ($iterator as $file) {
            $ext = $file->getExtension();
            if (!in_array($ext, ['html', 'htm', 'tpl', 'php'])) continue;

            self::$templates[] = $file->getPathname();
        }
    }

    # Checks that every template has as many if as endif tags
    public function testTemplateConditionalSyntax(): void
    {
        $errors = [];

        foreach (self::$templates as $file) {
            $content = file_get_contents($file);
            $relativePath = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $file);

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

    # Checks critical HTML tags for balance in non-PHP templates without paired open/close files, template tokens stripped; lists at most 20
    public function testTemplateHtmlStructure(): void
    {
        $errors = [];

        foreach (self::$templates as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') continue;

            $fileName = basename($file);
            if (preg_match('/(^|-)(open|close)\.html$/', $fileName)) continue;

            $content = file_get_contents($file);
            $relativePath = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $file);

            $content = preg_replace('/\{%[^%]*%\}/', '', $content);

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

    # Checks that every frontend theme (all except admin) carries the required templates
    public function testRequiredTemplatesExist(): void
    {
        $errors = [];

        $themes = [];
        foreach (scandir(self::$templatesPath) as $item) {
            if ($item === '.' || $item === '..' || isTreeSkipped(self::$templatesPath.'/'.$item)) continue;
            if (is_dir(self::$templatesPath.'/'.$item)) {
                $themes[] = $item;
            }
        }

        $required = [
            'fragments/title.html',
            'partials/content-list.html',
            'partials/view.html',
            'pages/module.html',
            'layouts/app.html',
        ];

        foreach ($themes as $theme) {
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
        $this->assertSame('modules/shop/index.php', self::getRelativePath($base.'\\modules\\shop\\index.php'));
        $this->assertSame('templates/lite/partials/menu.html', self::getRelativePath($base.'\\templates/lite\\partials\\menu.html'));
        $this->assertSame('modules/shop/index.php', self::getRelativePath(str_replace('\\', '/', self::$basePath).'/modules/shop/index.php'));
        $this->assertSame($front, self::getThemesForPath('modules/shop/index.php', $front));
        $this->assertSame(['admin'], self::getThemesForPath('admin/modules/blocks.php', $front));
        $this->assertSame(['admin'], self::getThemesForPath('modules/shop/admin/index.php', $front));
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
            $iterator = getTreeFiles($path);
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
            $iter = getTreeFiles($root);
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

    # A <style> block is never allowed, an inline style="" only for a dynamic {{ }} value like a progress width or avatar URL; static styling lives in CSS
    public function testHtmlTemplatesDoNotContainInlineStyles(): void
    {
        $errors = [];
        $paths = self::$templates;

        $moduleTemplatePath = self::$basePath.'/modules';
        if (is_dir($moduleTemplatePath)) {
            $iterator = getTreeFiles($moduleTemplatePath);
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
            if (preg_match('/<style\b|<\/style>/i', $content)) {
                $errors[] = $relative.' (<style> block)';
            }
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

    # Checks that templates are valid UTF-8 and carry no BOM
    public function testTemplateEncoding(): void
    {
        $errors = [];

        foreach (self::$templates as $file) {
            $content = file_get_contents($file);
            $relativePath = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $file);

            if (!mb_check_encoding($content, 'UTF-8')) {
                $errors[] = "$relativePath - некорректная кодировка (не UTF-8)";
            }

            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $errors[] = "$relativePath - содержит BOM";
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с кодировкой:\n".implode("\n", $errors)
        );
    }

    # Checks that templates were found and that there are more than ten of them
    public function testTemplatesFound(): void
    {
        $this->assertNotEmpty(self::$templates, 'Шаблоны не найдены');
        $this->assertGreaterThan(10, count(self::$templates), 'Найдено слишком мало шаблонов');
    }

    # A compiled template written in place under LOCK_EX made a parallel include fail with errno=13 on Windows; both writers go through a temporary file and a rename
    # Both writers compile under compile.lock with a second freshness check, because Windows also refuses a rename over a file another request includes
    public function testCompiledTemplatesAreRenamedIntoPlace(): void
    {
        $code = (string)file_get_contents(self::$basePath.'/core/classes/template.php');
        $this->assertDoesNotMatchRegularExpression('/file_put_contents\([^;]*LOCK_EX/', $code, 'A compiled template is still written in place under a lock');
        $this->assertSame(2, substr_count($code, "bin2hex(random_bytes(6)).'.tmp'"), 'A writer of compiled code skips the temporary file');
        $this->assertSame(2, substr_count($code, 'rename($temp, '), 'A temporary file is not renamed into place');
        $this->assertSame(2, substr_count($code, "/compile.lock', 'c')"), 'A writer compiles without the lock of the theme');
        $this->assertSame(2, substr_count($code, 'clearstatcache(true, '), 'A writer does not check again once it holds the lock');
    }

    # The select fragment of the site read input_id and the admin one selectid, so a caller naming one key left the other theme without an id and its label pointing nowhere
    # Both fragments read selectid, and no caller of the select fragment names input_id
    public function testSelectFragmentsShareTheirIdKey(): void
    {
        foreach (['admin', 'lite'] as $theme) {
            $frag = (string)file_get_contents(self::$templatesPath.'/'.$theme.'/fragments/select.html');
            $this->assertStringContainsString('{% if selectid %}id="{{ selectid }}"{% endif %}', $frag, 'The select fragment of '.$theme.' does not read selectid');
            $this->assertStringNotContainsString('input_id', $frag, 'The select fragment of '.$theme.' reads a second id key');
        }
        $errors = [];
        foreach (getTreeFiles(self::$basePath) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if ($file->getExtension() !== 'php' || preg_match('#/(tests|plugins)/#', $path)) continue;
            $code = (string)file_get_contents($path);
            if (!preg_match_all("#getHtmlFrag\('select', \[(.{0,600}?)\]\)#s", $code, $hits, PREG_OFFSET_CAPTURE)) continue;
            foreach ($hits[1] as [$args, $at]) {
                if (str_contains($args, "'input_id'")) $errors[] = substr($path, strlen(self::$basePath) + 1).':'.(substr_count(substr($code, 0, $at), "\n") + 1);
            }
        }
        $this->assertSame([], $errors, 'A caller of the select fragment names input_id, which neither theme reads');
    }
}
