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
            $GLOBALS['__test_theme'] = 'default';
            $GLOBALS['conf'] = [
                'theme' => 'default',
                'sitename' => 'SLAED',
                'homeurl' => 'https://slaed.loc',
                'slogan' => 'Fast CMS',
                'site_logo' => 'logo.png',
            ];
        }

        #[Test]
        public function happyPathUsesNewPartial(): void
        {
            $html = (new \Template('admin'))->getHtmlPart('searchbox', ['searchbox' => '<form class="search-form">Find</form>']);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('admin-searchbox', $html);
            $this->assertStringContainsString('search-form', $html);
        }

        #[Test]
        public function missingAdminThemeReturnsEmptyString(): void
        {
            $html = (new \Template('missing-theme'))->getHtmlPart('searchbox', ['searchbox' => '<form class="missing-form">Find</form>']);

            $this->assertSame('', $html);
        }

        #[Test]
        public function mappedUserVisibleValuesArePreserved(): void
        {
            $html = (new \Template('admin'))->getHtmlPart('searchbox', ['searchbox' => '<form><input value="search"></form>']);

            $this->assertStringContainsString('<form><input value="search"></form>', $html);
        }

        #[Test]
        public function conditionalRenderingWorksForEmptyOptionalValue(): void
        {
            $html = (new \Template('admin'))->getHtmlPart('searchbox', ['searchbox' => '']);

            $this->assertSame('', trim($html));
        }
    }
}
