<?php
declare(strict_types=1);

namespace {
    require_once __DIR__.'/../Support/ViewTestBootstrap.php';
}

namespace Tests\Unit {
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    final class ViewBridgeSmokeTest extends TestCase
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
                'users' => [
                    'adirectory' => 'uploads/avatars',
                ],
            ];
        }

        #[Test]
        public function viewRendersLoginWithoutPartial(): void
        {
            $tpl = new \Template('default');
            $html = $tpl->getHtmlPart('login-without', ['register' => 'Register']);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('login-without', $html);
            $this->assertStringContainsString('Register', $html);
        }

        #[Test]
        public function viewRendersLoginLoggedPartial(): void
        {
            $tpl = new \Template('default');
            $html = $tpl->getHtmlPart('login-logged', [
                'title' => 'Account',
                'avatar' => 'uploads/avatars/default/00.gif',
                'user' => 'Tester',
                'logout' => 'Logout',
            ]);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('Tester', $html);
            $this->assertStringContainsString('uploads/avatars/default/00.gif', $html);
            $this->assertStringContainsString('Logout', $html);
        }

        #[Test]
        public function viewRendersMessageFragment(): void
        {
            $tpl = new \Template('default');
            $html = $tpl->getHtmlFrag('message', [
                'text' => 'Hello',
                'is_warn' => true,
                'is_error' => false,
            ]);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('Hello', $html);
            $this->assertStringContainsString('view-frag-message', $html);
            $this->assertStringContainsString('alert-warning', $html);
        }

        #[Test]
        public function viewRendersErrorPageWithBareLayout(): void
        {
            $tpl = new \Template('default');
            $html = $tpl->getHtmlPage('error', [
                'lang' => 'en',
                'sitename' => 'SLAED',
                'title' => 'Error',
                'message_html' => '<div class="view-frag-message">Hello</div>',
                'meta' => '<meta http-equiv="refresh" content="3; url=index.php?test=1">',
                'url' => 'index.php?test=1',
                'url_text' => 'Continue',
                'time' => 3,
                'time_text' => '3 sec',
            ], 'bare');

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('<!doctype html>', $html);
            $this->assertMatchesRegularExpression('/<title>\s*Error\s*<\/title>/', $html);
            $this->assertStringContainsString('view-frag-message', $html);
            $this->assertStringContainsString('Continue', $html);
            $this->assertStringContainsString('3 sec', $html);
            $this->assertStringContainsString('<meta http-equiv="refresh" content="3; url=index.php?test=1">', $html);
        }

        #[Test]
        public function viewDirectCallsRenderSmokeOutputs(): void
        {
            $tpl = new \Template('default');
            $part = $tpl->getHtmlPart('login-without', ['register' => 'Join']);
            $frag = $tpl->getHtmlFrag('message', [
                'text' => 'Warn',
                'is_warn' => true,
                'is_error' => false,
            ]);
            $page = $tpl->getHtmlPage('error', [
                'lang' => 'en',
                'sitename' => 'SLAED',
                'title' => 'Error',
                'message_html' => '<div class="view-frag-message">Warn</div>',
                'meta' => '<meta http-equiv="refresh" content="1; url=index.php">',
                'url' => 'index.php',
                'url_text' => 'Home',
                'time' => 1,
                'time_text' => '1 sec',
            ], 'bare');

            $this->assertNotSame('', $part);
            $this->assertNotSame('', $frag);
            $this->assertNotSame('', $page);
        }

        #[Test]
        public function viewRendersModulePageWithAppLayout(): void
        {
            $tpl = new \Template('default');
            $html = $tpl->getHtmlPage('module', [
                'lang' => 'en',
                'sitename' => 'SLAED',
                'meta' => '<meta charset="utf-8">',
                'links' => '<link rel="stylesheet" href="theme.css">',
                'scripts' => '<script src="theme.js"></script>',
                'head_html' => '<nav>Main nav</nav>',
                'content' => '<section class="module-body">Module body</section>',
                'blocks_left' => '<div class="left-col">Left</div>',
                'blocks_right' => '<div class="right-col">Right</div>',
                'blocks_down' => '<div class="down-col">Down</div>',
                'foot_html' => '<div class="footer-note">Footer</div>',
            ], 'app');

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('module-body', $html);
            $this->assertStringContainsString('Main nav', $html);
            $this->assertStringContainsString('left-col', $html);
            $this->assertStringContainsString('right-col', $html);
            $this->assertStringContainsString('down-col', $html);
            $this->assertStringContainsString('theme.css', $html);
            $this->assertStringContainsString('theme.js', $html);
        }

        #[Test]
        public function viewRendersHomePageWithHomeLayout(): void
        {
            $tpl = new \Template('default');
            $html = $tpl->getHtmlPage('home', [
                'lang' => 'en',
                'sitename' => 'SLAED',
                'meta' => '<meta charset="utf-8">',
                'links' => '<link rel="stylesheet" href="theme.css">',
                'scripts' => '<script src="home.js"></script>',
                'head_html' => '<div class="home-head">Home head</div>',
                'content' => '<section class="home-body">Home body</section>',
                'blocks_left' => '',
                'blocks_right' => '',
                'blocks_down' => '<div class="home-down">Down</div>',
                'foot_html' => '<div class="home-foot">Foot</div>',
            ], 'home');

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('sl_home', $html);
            $this->assertStringContainsString('home-head', $html);
            $this->assertStringContainsString('home-body', $html);
            $this->assertStringContainsString('home-down', $html);
            $this->assertStringContainsString('home-foot', $html);
            $this->assertStringContainsString('home.js', $html);
        }

        #[Test]
        public function simpleThemeRendersModulePage(): void
        {
            $tpl = new \Template('simple');
            $html = $tpl->getHtmlPage('module', [
                'lang' => 'en',
                'sitename' => 'Simple',
                'meta' => '<meta charset="utf-8">',
                'links' => '<link rel="stylesheet" href="simple.css">',
                'scripts' => '<script src="simple.js"></script>',
                'head_html' => '<div class="simple-head">Head</div>',
                'content' => '<section class="simple-body">Body</section>',
                'blocks_left' => '<div class="simple-left">Left</div>',
                'blocks_right' => '<div class="simple-right">Right</div>',
                'blocks_down' => '<div class="simple-down">Down</div>',
                'foot_html' => '<div class="simple-foot">Foot</div>',
            ], 'app');

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('simple-head', $html);
            $this->assertStringContainsString('simple-body', $html);
            $this->assertStringContainsString('simple-left', $html);
            $this->assertStringContainsString('simple-right', $html);
            $this->assertStringContainsString('simple-down', $html);
            $this->assertStringContainsString('simple-foot', $html);
        }

        #[Test]
        public function liteThemeRendersModulePage(): void
        {
            $tpl = new \Template('lite');
            $html = $tpl->getHtmlPage('module', [
                'lang' => 'en',
                'sitename' => 'Lite',
                'slogan' => 'Fast CMS',
                'meta' => '<meta charset="utf-8">',
                'links' => '<link rel="stylesheet" href="lite.css">',
                'scripts' => '<script src="lite.js"></script>',
                'login' => '<div class="lite-login">Login</div>',
                'menu' => '<nav class="lite-menu">Menu</nav>',
                'head_html' => '<div class="lite-head">Head</div>',
                'content' => '<section class="lite-body">Body</section>',
                'blocks_left' => '<div class="lite-left">Left</div>',
                'blocks_right' => '<div class="lite-right">Right</div>',
                'blocks_down' => '<div class="lite-down">Down</div>',
                'foot_html' => '<div class="lite-foot">Foot</div>',
                'faqtitle' => '<span class="lite-faq">FAQ</span>',
                'forumblock' => '<div class="lite-forum">Forum</div>',
                'contactblock' => '<div class="lite-contact">Contact</div>',
            ], 'app');

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('lite-login', $html);
            $this->assertStringContainsString('lite-head', $html);
            $this->assertStringContainsString('lite-body', $html);
            $this->assertStringContainsString('lite-left', $html);
            $this->assertStringContainsString('lite-right', $html);
            $this->assertStringContainsString('lite-down', $html);
            $this->assertStringContainsString('lite-foot', $html);
        }
    }
}
