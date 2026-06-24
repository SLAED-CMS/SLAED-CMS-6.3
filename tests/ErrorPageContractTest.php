<?php
/**
 * Контракт вывода ошибок.
 * Гарантирует, что недавняя переработка вывода ошибок не деградирует:
 * - статический error.html (для 502/504, когда PHP недоступен) полностью автономен: CSS, иконки и логотип встроены;
 * - whitelist кодов в ?error=NNN (bootstrap core/security.php) целиком покрыт картой причин в setError().
 */

use PHPUnit\Framework\TestCase;

class ErrorPageContractTest extends TestCase
{
    private static string $basePath;

    public static function setUpBeforeClass(): void
    {
        self::$basePath = dirname(__DIR__);
    }

    /**
     * Статическая страница error.html должна существовать, не зависеть от PHP и быть брендированной:
     * её отдаёт веб-сервер (nginx/Apache), когда приложение лежит (502/504).
     */
    public function testStaticErrorPageIsStaticAndBranded(): void
    {
        $path = self::$basePath.'/error.html';
        $this->assertFileExists($path, 'Отсутствует статическая страница error.html для error_page 502/504');

        $html = (string)file_get_contents($path);

        $this->assertStringNotContainsString('<?php', $html, 'error.html должна быть полностью статической (PHP в момент 502/504 недоступен)');
        $this->assertDoesNotMatchRegularExpression('/<script\b[^>]*\bsrc=/i', $html, 'error.html не должна подключать внешний JS (инлайн-скрипт допустим)');

        $this->assertStringContainsString('sl-msg', $html, 'error.html должна нести фирменную разметку страницы-сообщения');
        $this->assertStringContainsString('SLAED', $html, 'error.html должна быть брендирована');
        $this->assertMatchesRegularExpression('/<meta\b[^>]*name=["\']robots["\'][^>]*noindex/i', $html, 'error.html не должна индексироваться');
    }

    /**
     * Страница не должна зависеть ни от одного внешнего файла (CSS, шрифт, favicon, логотип):
     * любого из них может не быть на конкретном сервере, поэтому всё встроено в сам документ.
     */
    public function testStaticErrorPageHasNoExternalDependencies(): void
    {
        $html = (string)file_get_contents(self::$basePath.'/error.html');

        $this->assertMatchesRegularExpression('/<style\b/i', $html, 'CSS должен быть встроен в error.html');
        $this->assertMatchesRegularExpression('/<svg\b/i', $html, 'Логотип и иконки должны быть встроены SVG-кодом');

        $this->assertDoesNotMatchRegularExpression('/<link\b/i', $html, 'error.html не должна подключать внешние ресурсы через <link> (CSS/favicon)');
        $this->assertDoesNotMatchRegularExpression('/<img\b/i', $html, 'error.html не должна ссылаться на файл изображения через <img>');
        $this->assertDoesNotMatchRegularExpression('/<script\b[^>]*\bsrc=/i', $html, 'error.html не должна подключать внешний JS');
        $this->assertDoesNotMatchRegularExpression('#(href|src)=["\']\.?/?templates/#i', $html, 'error.html не должна ссылаться на файлы темы');
    }

    /**
     * Каждый код из whitelist обработчика ?error= (bootstrap в core/security.php)
     * должен иметь человекочитаемую причину в карте статусов setError(); иначе
     * вместо корректной фразы выводится дефолтное 'Error'.
     */
    public function testErrorParamWhitelistCoveredBySetError(): void
    {
        $security = (string)file_get_contents(self::$basePath.'/core/security.php');

        $this->assertSame(
            1,
            preg_match('/\$_GET\[[\'"]error[\'"]\].*?in_array\(\s*\$ecode\s*,\s*\[([0-9,\s]+)\]/s', $security, $wm),
            'Обработчик ?error= с whitelist не найден в core/security.php'
        );
        $whitelist = array_map('intval', array_filter(array_map('trim', explode(',', $wm[1])), 'strlen'));

        $this->assertNotEmpty($whitelist, 'Whitelist кодов ошибок пуст');
        $this->assertContains(404, $whitelist, 'Whitelist должен включать канонический 404');

        $this->assertSame(
            1,
            preg_match('/\$msg\s*=\s*\[(.*?)\]\[\$code\]/s', $security, $mm),
            'Карта статусов setError() не найдена в core/security.php'
        );
        preg_match_all('/(\d+)\s*=>/', $mm[1], $keys);
        $reasons = array_map('intval', $keys[1]);

        $missing = array_values(array_diff($whitelist, $reasons));
        $this->assertSame(
            [],
            $missing,
            'Коды из whitelist без причины в setError(): '.implode(', ', $missing)
        );
    }
}
