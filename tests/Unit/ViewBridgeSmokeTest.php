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
    }
}
