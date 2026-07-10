<?php
declare(strict_types=1);

namespace {
    require_once __DIR__.'/../Support/ViewTestBootstrap.php';

    if (!defined('ADMIN_FILE')) define('ADMIN_FILE', true);
}

namespace Tests\Unit {
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    final class AdminSearchboxBridgeFlowTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['__test_theme'] = 'admin';
            $GLOBALS['conf'] = [
                'theme' => 'admin',
                'sitename' => 'SLAED',
                'homeurl' => 'https://slaed.loc',
                'slogan' => 'Fast CMS',
                'site_logo' => 'logo.png',
            ];
        }

        #[Test]
        public function happyPathUsesDivVariant(): void
        {
            $html = (new \Template('admin'))->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => '<form class="search-form">Find</form>']);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('sl-searchbox', $html);
            $this->assertStringContainsString('search-form', $html);
        }

        #[Test]
        public function missingAdminThemeReturnsEmptyString(): void
        {
            $html = (new \Template('missing-theme'))->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => '<form class="missing-form">Find</form>']);

            $this->assertSame('', $html);
        }

        #[Test]
        public function mappedUserVisibleValuesArePreserved(): void
        {
            $html = (new \Template('admin'))->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => '<form><input value="search"></form>']);

            $this->assertStringContainsString('<form><input value="search"></form>', $html);
        }

        #[Test]
        public function conditionalRenderingWorksForEmptyOptionalValue(): void
        {
            $html = (new \Template('admin'))->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => '']);

            $this->assertSame('', trim($html));
        }

        #[Test]
        public function menuGridVariantPreservesEmptyAndFilledWrappers(): void
        {
            $tpl = new \Template('admin');
            $empty = $tpl->getHtmlPart('div', ['is_menu_grid' => true, 'content_html' => '']);
            $filled = $tpl->getHtmlPart('div', ['is_menu_grid' => true, 'content_html' => '<article class="sl-menu-grid-item">Item</article>']);

            $this->assertSame('<div class="sl-menu-grid"></div>', trim($empty));
            $this->assertStringContainsString('<div class="sl-menu-grid"><article class="sl-menu-grid-item">Item</article></div>', $filled);
            $this->assertStringNotContainsString('sl-div-grid', $empty.$filled);
        }

        #[Test]
        public function genericContentAndRowsModesKeepTheirContracts(): void
        {
            $tpl = new \Template('admin');
            $content = $tpl->getHtmlPart('div', ['content_html' => '<span>Content</span>']);
            $rows = $tpl->getHtmlPart('div', ['rows' => [[
                'label_html' => '<strong>Label</strong>',
                'field_html' => '<input name="field">',
            ]]]);

            $this->assertStringContainsString('<span>Content</span>', $content);
            $this->assertStringNotContainsString('sl-div-grid', $content);
            $this->assertStringContainsString('class="sl-div-grid"', $rows);
            $this->assertStringContainsString('sl-div-item', $rows);
            $this->assertStringContainsString('<strong>Label</strong>', $rows);
            $this->assertStringContainsString('<input name="field">', $rows);
        }
    }
}
