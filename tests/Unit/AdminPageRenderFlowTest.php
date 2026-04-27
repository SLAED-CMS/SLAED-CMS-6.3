<?php
declare(strict_types=1);

namespace {
    require_once __DIR__.'/../Support/ViewTestBootstrap.php';

    if (!defined('ADMIN_FILE')) define('ADMIN_FILE', true);
    if (!defined('_ADMIN')) define('_ADMIN', 'Admin');
    if (!defined('_MESSAGE')) define('_MESSAGE', 'Message');
}

namespace Tests\Unit {
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    final class AdminPageRenderFlowTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['__test_theme'] = 'default';
            $GLOBALS['__test_is_user'] = false;
            $GLOBALS['__test_user_info'] = [];
            $GLOBALS['__test_captcha'] = '';
            $GLOBALS['__test_token'] = [];
            $GLOBALS['__test_templates'] = [];
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
        public function adminPageUsesAdminLayoutContract(): void
        {
            $html = (new \Template('default'))->getHtmlPage('admin', [
                'lang' => 'en',
                'theme' => 'default',
                'sitename' => 'SLAED',
                'meta' => '<meta charset="utf-8">',
                'links' => '<link rel="stylesheet" href="theme.css">',
                'scripts' => '<script src="app.js"></script>',
                'license' => 'License',
                'menu' => '<li>Menu</li>',
                'admin_langs' => '<a href="?newlang=en">EN</a>',
                'admin_blocks' => '<div class="left-block">Block</div>',
                'content' => '<section class="module-body">Dashboard</section>',
                'foot_html' => '<a href="#top">Top</a>',
                'debug_html' => '<div id="debug">Debug</div>',
                'time_html' => '0.001 sec',
            ], 'admin');

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('module-body', $html);
            $this->assertStringContainsString('left-block', $html);
            $this->assertStringContainsString('<li>Menu</li>', $html);
            $this->assertStringContainsString('Debug', $html);
            $this->assertStringContainsString('0.001 sec', $html);
        }

        #[Test]
        public function messagePageUsesBareLayoutContract(): void
        {
            $html = (new \Template('default'))->getHtmlPage('message', [
                'lang' => 'en',
                'theme' => 'default',
                'sitename' => 'SLAED',
                'meta' => '<meta charset="utf-8">',
                'links' => '<link rel="stylesheet" href="theme.css">',
                'scripts' => '',
                'license' => 'License',
                'login' => 'Message',
                'title' => '<h2>Denied</h2>',
                'content' => '<div class="sl-warn">Access denied</div>',
            ], 'bare');

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('<body>', $html);
            $this->assertStringContainsString('<h2>Denied</h2>', $html);
            $this->assertStringContainsString('Access denied', $html);
            $this->assertStringContainsString('License', $html);
            $this->assertStringContainsString('theme.css', $html);
        }
    }
}
