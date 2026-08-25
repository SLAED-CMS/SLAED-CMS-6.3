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
            $GLOBALS['__test_theme'] = 'lite';
            $GLOBALS['conf'] = [
                'theme' => 'lite',
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
        public function viewRendersLoginNavPartial(): void
        {
            $tpl = new \Template('lite');
            $html = $tpl->getHtmlPart('login-nav', [
                'login' => 'Login',
                'name_field' => ['itype' => 'text', 'name_attr' => 'user_name', 'placeholder_text' => 'Nickname'],
                'password_field' => ['itype' => 'password', 'name_attr' => 'user_password', 'placeholder_text' => 'Password'],
                'submit_button' => ['button_type' => 'submit', 'label' => 'Send'],
                'lost_link' => ['href' => 'index.php?name=account&op=pass_lost', 'label' => 'Lost'],
                'register_link' => ['href' => 'index.php?name=account&op=registration', 'label' => 'Register'],
                'refer_field' => ['name_attr' => 'refer', 'value_attr' => '1'],
                'op_field' => ['name_attr' => 'op', 'value_attr' => 'login'],
            ]);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('login-form', $html);
            $this->assertStringContainsString('Nickname', $html);
            $this->assertStringContainsString('Register', $html);
        }

        #[Test]
        public function viewRendersMessageBlockPartial(): void
        {
            $tpl = new \Template('lite');
            $html = $tpl->getHtmlPart('message-block', [
                'title' => 'Hello',
                'intro_text' => 'Warn',
            ]);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('Hello', $html);
            $this->assertStringContainsString('Warn', $html);
        }

        #[Test]
        public function viewRendersAlertFragment(): void
        {
            $tpl = new \Template('lite');
            $html = $tpl->getHtmlFrag('alert', [
                'text' => 'Hello',
                'is_warn' => true,
            ]);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('Hello', $html);
            $this->assertStringContainsString('sl-alert sl-alert-warn', $html);

            $info = $tpl->getHtmlFrag('alert', ['text' => 'Info', 'type' => 'info']);
            $this->assertStringContainsString('sl-alert sl-alert-info', $info);

            $error = $tpl->getHtmlFrag('alert', ['text' => 'Boom', 'type' => 'error']);
            $this->assertStringContainsString('sl-alert sl-alert-error', $error);
        }

        #[Test]
        public function viewRendersErrorPageWithBareLayout(): void
        {
            $tpl = new \Template('lite');
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
            $tpl = new \Template('lite');
            $part = $tpl->getHtmlPart('login-nav', [
                'login' => 'Login',
                'name_field' => ['itype' => 'text', 'name_attr' => 'user_name'],
                'password_field' => ['itype' => 'password', 'name_attr' => 'user_password'],
                'submit_button' => ['button_type' => 'submit', 'label' => 'Send'],
                'lost_link' => ['href' => 'index.php?name=account&op=pass_lost', 'label' => 'Lost'],
                'register_link' => ['href' => 'index.php?name=account&op=registration', 'label' => 'Join'],
                'refer_field' => ['name_attr' => 'refer', 'value_attr' => '1'],
                'op_field' => ['name_attr' => 'op', 'value_attr' => 'login'],
            ]);
            $frag = $tpl->getHtmlFrag('alert', [
                'text' => 'Warn',
                'is_warn' => true,
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
            $tpl = new \Template('lite');
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
            $tpl = new \Template('lite');
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
            $this->assertStringContainsString('sl-home', $html);
            $this->assertStringContainsString('home-head', $html);
            $this->assertStringContainsString('home-body', $html);
            $this->assertStringContainsString('home-down', $html);
            $this->assertStringContainsString('home-foot', $html);
            $this->assertStringContainsString('home.js', $html);
        }

        #[Test]
        public function mainSliderUsesExplicitThemeAssetPaths(): void
        {
            $html = (new \Template('lite'))->getHtmlPart('main-slider', ['is_home' => true, 'theme' => 'lite']);

            $this->assertSame(4, substr_count($html, 'templates/lite/images/slide/'));
            $this->assertStringNotContainsString('templates//', $html);
            $this->assertStringContainsString('slaed_1.webp', $html);
            $this->assertStringContainsString('slaed_4.webp', $html);
        }

        #[Test]
        public function linkAndListItemCompositionPreservesEscapingAndBlankTarget(): void
        {
            $tpl = new \Template('lite');
            $link = $tpl->getHtmlFrag('link', [
                'href' => 'index.php?name=news&id=1',
                'title' => '<Title>',
                'label' => '<Label>',
                'is_blank' => true,
            ]);
            $html = $tpl->getHtmlFrag('list-item', ['content_html' => $link]);

            $this->assertStringStartsWith('<li>', trim($html));
            $this->assertStringEndsWith('</li>', trim($html));
            $this->assertStringContainsString('href="index.php?name=news&amp;id=1"', $html);
            $this->assertStringContainsString('title="&lt;Title&gt;"', $html);
            $this->assertStringContainsString('&lt;Label&gt;', $html);
            $this->assertStringContainsString('target="_blank"', $html);
            $this->assertStringContainsString('rel="noopener noreferrer"', $html);
            $this->assertStringNotContainsString('<Title>', $html);
            $this->assertStringNotContainsString('<Label>', $html);
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
