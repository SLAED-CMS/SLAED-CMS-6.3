<?php
declare(strict_types=1);

namespace {
    if (!defined('_LOCALE')) define('_LOCALE', 'en_US');
    if (!defined('_HOME')) define('_HOME', 'Home');
    if (!defined('_ACCOUNT')) define('_ACCOUNT', 'Account');
    if (!defined('_ALBUM')) define('_ALBUM', 'Album');
    if (!defined('_A_LINKS')) define('_A_LINKS', 'Links');
    if (!defined('_FEEDBACK')) define('_FEEDBACK', 'Feedback');
    if (!defined('_CONTENT')) define('_CONTENT', 'Content');
    if (!defined('_FAQ')) define('_FAQ', 'FAQ');
    if (!defined('_FILES')) define('_FILES', 'Files');
    if (!defined('_FORUM')) define('_FORUM', 'Forum');
    if (!defined('_HELP')) define('_HELP', 'Help');
    if (!defined('_RADIO')) define('_RADIO', 'Radio');
    if (!defined('_JOKES')) define('_JOKES', 'Jokes');
    if (!defined('_LINKS')) define('_LINKS', 'Links');
    if (!defined('_MEDIA')) define('_MEDIA', 'Media');
    if (!defined('_USERS')) define('_USERS', 'Users');
    if (!defined('_NEWS')) define('_NEWS', 'News');
    if (!defined('_ORDER')) define('_ORDER', 'Order');
    if (!defined('_PAGES')) define('_PAGES', 'Pages');
    if (!defined('_RECOMMEND')) define('_RECOMMEND', 'Recommend');
    if (!defined('_RSS')) define('_RSS', 'RSS');
    if (!defined('_SEARCH')) define('_SEARCH', 'Search');
    if (!defined('_SHOP')) define('_SHOP', 'Shop');
    if (!defined('_TOPUSERS')) define('_TOPUSERS', 'Top');
    if (!defined('_VOTING')) define('_VOTING', 'Voting');
    if (!defined('_S_FAVORITEN')) define('_S_FAVORITEN', 'Favorites');
    if (!defined('_S_STARTSEITE')) define('_S_STARTSEITE', 'Start');
    if (!defined('_ERROR')) define('_ERROR', 'Error');
    if (!defined('_ERRORTPL')) define('_ERRORTPL', 'Template not found: %1$s');

    if (!function_exists('getTheme')) {
        function getTheme(): string
        {
            return $GLOBALS['__test_theme'] ?? 'default';
        }
    }

    if (!function_exists('getThemeLoad')) {
        function getThemeLoad(string $tpl): string
        {
            return $GLOBALS['__test_templates'][$tpl] ?? '';
        }
    }

    if (!function_exists('getThemeFile')) {
        function getThemeFile(string $tpl): string|false
        {
            return isset($GLOBALS['__test_templates'][$tpl]) ? $tpl : false;
        }
    }

    require_once BASE_DIR.'/core/template.php';
}

namespace Tests\Unit {
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    /**
     * Unit tests for template helpers.
     */
    final class TemplateTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['conf'] = [
                'sitename' => 'SLAED',
                'homeurl' => 'https://slaed.loc',
                'slogan' => 'Fast CMS',
                'site_logo' => 'logo.png',
            ];

            $GLOBALS['__test_theme'] = 'default';
            $GLOBALS['__test_templates'] = [
                'open' => '{%theme%}:{%sitename%}:{%homeurl%}:{%lang%}',
                'warn' => 'WARN:{%text%}:{%id%}',
            ];
        }

        #[Test]
        public function templateVarsIncludeThemeAndSiteData(): void
        {
            $vars = \getTemplateVars();
            $this->assertSame('default', $vars['{%theme%}']);
            $this->assertSame('en', $vars['{%lang%}']);
            $this->assertSame('SLAED', $vars['{%sitename%}']);
            $this->assertSame('https://slaed.loc', $vars['{%homeurl%}']);
        }

        #[Test]
        public function setTemplateBasicReplacesPlaceholders(): void
        {
            $out = \setTemplateBasic('open', ['{%sitename%}' => 'Override']);
            $this->assertSame('default:Override:https://slaed.loc:en', $out);
        }

        #[Test]
        public function setTemplateWarningRendersFallbackTemplate(): void
        {
            $out = \setTemplateWarning('warn', ['text' => 'oops', 'id' => 'warn']);
            $this->assertSame('WARN:oops:warn', $out);
        }
    }
}
