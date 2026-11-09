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
            $GLOBALS['__test_theme'] = 'aurora';
            $GLOBALS['conf'] = [
                'theme' => 'aurora',
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
            $tpl = new \Template('aurora');
            $html = $tpl->getHtmlPart('login-nav', [
                'login' => 'Login',
                'name_field' => ['itype' => 'text', 'name_attr' => 'user_name', 'placeholder_text' => 'Nickname'],
                'password_field' => ['itype' => 'password', 'name_attr' => 'user_password', 'placeholder_text' => 'Password'],
                'submit_button' => ['button_type' => 'submit', 'label' => 'Send'],
                'lost_link' => ['href' => 'index.php?name=account&op=pass_lost', 'label' => 'Lost'],
                'register_link' => ['href' => 'index.php?name=account&op=registration', 'label' => 'Register'],
                'back_field' => ['name_attr' => 'back', 'value_attr' => '/index.php?name=news'],
                'op_field' => ['name_attr' => 'op', 'value_attr' => 'login'],
            ]);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('login-form', $html);
            $this->assertStringContainsString('Nickname', $html);
            $this->assertStringContainsString('Register', $html);
            $this->assertStringContainsString('name="back" value="/index.php?name=news"', $html);
        }

        #[Test]
        public function viewRendersMessageBlockPartial(): void
        {
            $tpl = new \Template('aurora');
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
            $tpl = new \Template('aurora');
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
        public function viewRendersAlertActionSlot(): void
        {
            foreach (['admin', 'aurora'] as $theme) {
                $tpl = new \Template($theme);
                $bare = $tpl->getHtmlFrag('alert', ['text' => 'Plain']);
                $this->assertStringContainsString('Plain', $bare);
                $this->assertStringNotContainsString('sl-alert-acts', $bare, 'An alert without actions grew a slot in '.$theme);
                $this->assertMatchesRegularExpression('#</div>\R\s*</div>#', $bare, 'An alert without actions lost the line break after its body in '.$theme);
                $html = $tpl->getHtmlFrag('alert', ['text' => 'Drift', 'is_warn' => true, 'acts_html' => '<button>Fix</button>']);
                $this->assertMatchesRegularExpression('#Drift.*</div>\s*<div class="sl-alert-acts">\s*<button>Fix</button>\s*</div>\s*</div>#s', $html,
                    'The actions of an alert do not follow its body in '.$theme);
                $act = $tpl->getHtmlFrag('post-button', ['action' => 'admin.php', 'hidden' => '', 'icon_name' => 'trash', 'title' => 'Clear', 'label' => 'Clear',
                    'is_act' => true, 'is_danger' => true]);
                $this->assertStringContainsString('class="sl-alert-act sl-alert-act-danger"', $act, 'The dangerous action of an alert is no chip in '.$theme);
                $btn = $tpl->getHtmlFrag('post-button', ['action' => 'admin.php', 'hidden' => '', 'icon_name' => 'trash', 'title' => 'Clear', 'label' => 'Clear']);
                $this->assertStringContainsString('class="sl-but"', $btn, 'A form button lost its look in '.$theme);
            }
        }

        #[Test]
        public function viewRendersErrorPageWithBareLayout(): void
        {
            $tpl = new \Template('aurora');
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
            $tpl = new \Template('aurora');
            $part = $tpl->getHtmlPart('login-nav', [
                'login' => 'Login',
                'name_field' => ['itype' => 'text', 'name_attr' => 'user_name'],
                'password_field' => ['itype' => 'password', 'name_attr' => 'user_password'],
                'submit_button' => ['button_type' => 'submit', 'label' => 'Send'],
                'lost_link' => ['href' => 'index.php?name=account&op=pass_lost', 'label' => 'Lost'],
                'register_link' => ['href' => 'index.php?name=account&op=registration', 'label' => 'Join'],
                'back_field' => ['name_attr' => 'back', 'value_attr' => '/index.php?name=news'],
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
            $tpl = new \Template('aurora');
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
            $tpl = new \Template('aurora');
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
        public function linkAndListItemCompositionPreservesEscapingAndBlankTarget(): void
        {
            $tpl = new \Template('aurora');
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
        public function auroraThemeRendersModulePage(): void
        {
            $tpl = new \Template('aurora');
            $html = $tpl->getHtmlPage('module', [
                'lang' => 'en',
                'sitename' => 'Aurora',
                'slogan' => 'Fast CMS',
                'meta' => '<meta charset="utf-8">',
                'links' => '<link rel="stylesheet" href="aurora.css">',
                'scripts' => '<script src="aurora.js"></script>',
                'login' => '<div class="aurora-login">Login</div>',
                'menu' => '<nav class="aurora-menu">Menu</nav>',
                'head_html' => '<div class="aurora-head">Head</div>',
                'content' => '<section class="aurora-body">Body</section>',
                'blocks_left' => '<div class="aurora-left">Left</div>',
                'blocks_right' => '<div class="aurora-right">Right</div>',
                'blocks_down' => '<div class="aurora-down">Down</div>',
                'foot_html' => '<div class="aurora-foot">Foot</div>',
                'faqtitle' => '<span class="aurora-faq">FAQ</span>',
                'forumblock' => '<div class="aurora-forum">Forum</div>',
                'contactblock' => '<div class="aurora-contact">Contact</div>',
            ], 'app');

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('aurora-login', $html);
            $this->assertStringContainsString('aurora-head', $html);
            $this->assertStringContainsString('aurora-body', $html);
            $this->assertStringContainsString('aurora-left', $html);
            $this->assertStringContainsString('aurora-right', $html);
            $this->assertStringContainsString('aurora-down', $html);
            $this->assertStringContainsString('aurora-foot', $html);
        }
    }
}
