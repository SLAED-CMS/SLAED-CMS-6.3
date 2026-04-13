<?php
declare(strict_types=1);

namespace {
    require_once __DIR__.'/../Support/ViewTestBootstrap.php';

    if (!defined('ADMIN_FILE')) define('ADMIN_FILE', true);
    if (!defined('_PREVIEW')) define('_PREVIEW', 'Preview');

    if (!function_exists('fields_out')) {
        function fields_out(string $text, string $mod = ''): string
        {
            return $GLOBALS['__test_fields_out'][$text] ?? $text;
        }
    }

}

namespace Tests\Unit {
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    final class AdminPreviewBridgeFlowTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['__test_theme'] = 'default';
            $GLOBALS['__test_is_user'] = false;
            $GLOBALS['__test_user_info'] = [];
            $GLOBALS['__test_captcha'] = '';
            $GLOBALS['__test_token'] = [];
            $GLOBALS['__test_templates'] = [];
            $GLOBALS['__test_filter_replace'] = [];
            $GLOBALS['__test_fields_out'] = [];
            $GLOBALS['conf'] = [
                'theme' => 'default',
                'sitename' => 'SLAED',
                'homeurl' => 'https://slaed.loc',
                'slogan' => 'Fast CMS',
                'site_logo' => 'logo.png',
                'users' => [
                    'adirectory' => 'uploads/avatars',
                ],
            ];
        }

        #[Test]
        public function previewHappyPathUsesNewAdminPartial(): void
        {
            $tpl = new \Template('default');
            $html = $tpl->getHtmlPart('preview', [
                'title' => 'Preview',
                'title_html' => '<b>Title</b>',
                'body_a' => 'Text one',
                'body_b' => 'Text two',
                'body_c' => 'Text three',
            ]);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('admin-preview', $html);
            $this->assertStringContainsString('Preview', $html);
            $this->assertStringContainsString('<b>Title</b>', $html);
            $this->assertStringContainsString('Text one', $html);
            $this->assertStringContainsString('Text two', $html);
            $this->assertStringContainsString('Text three', $html);
        }

        #[Test]
        public function previewMissingThemeReturnsEmptyHtml(): void
        {
            $tpl = new \Template('missing-theme');
            $html = $tpl->getHtmlPart('preview', [
                'title' => 'Preview',
                'title_html' => '<b>Title</b>',
                'body_a' => 'Text one',
                'body_b' => 'Text two',
                'body_c' => 'Text three',
            ]);

            $this->assertSame('', $html);
        }

        #[Test]
        public function previewPreservesExpectedMappedValues(): void
        {
            $view = [
                'title' => 'Preview',
                'title_html' => '<b>Mapped</b>',
                'body_a' => 'Body one',
                'body_b' => 'Body two',
                'body_c' => 'Body three',
            ];
            $html = (new \Template('default'))->getHtmlPart('preview', $view);

            $this->assertStringContainsString('Preview', $html);
            $this->assertStringContainsString('<b>Mapped</b>', $html);
            $this->assertStringContainsString('Body one', $html);
            $this->assertStringContainsString('Body two', $html);
            $this->assertStringContainsString('Body three', $html);
        }

        #[Test]
        public function previewPartialHandlesHtmlContentCorrectly(): void
        {
            $html = (new \Template('default'))->getHtmlPart('preview', [
                'title' => 'Preview',
                'title_html' => '<b>Title</b>',
                'body_a' => '<em>Html text</em>',
                'body_b' => '',
                'body_c' => '',
            ]);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('<em>Html text</em>', $html);
            $this->assertStringContainsString('admin-preview-fields1', $html);
        }
    }
}
