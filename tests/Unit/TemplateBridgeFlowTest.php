<?php
declare(strict_types=1);

namespace {
    require_once __DIR__.'/../Support/ViewTestBootstrap.php';
}

namespace Tests\Unit {
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    final class TemplateBridgeFlowTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['__test_theme'] = 'default';
            $GLOBALS['__test_is_user'] = false;
            $GLOBALS['__test_user_info'] = [];
            $GLOBALS['__test_captcha'] = '';
            $GLOBALS['__test_token'] = ['account' => 'token-account'];
            $GLOBALS['__test_templates'] = [
                'warn' => 'LEGACY-WARN:{%text%}:{%id%}:{%meta%}',
                'login-without' => '<div class="legacy-login-without">{%register%}</div>',
                'login-logged' => '<div class="legacy-login-logged">{%user%}:{%logout%}:{%avatar%}</div>',
                'login' => '<div class="legacy-login">{%login%}:{%nickname%}:{%password%}:{%captcha%}:{%token%}:{%lost%}:{%register%}</div>',
            ];
            $GLOBALS['user'] = [0, 'Tester'];
            $GLOBALS['conf'] = [
                'theme' => 'default',
                'sitename' => 'SLAED',
                'homeurl' => 'https://slaed.loc',
                'slogan' => 'Fast CMS',
                'site_logo' => 'logo.png',
                'gfx_chk' => 2,
                'users' => [
                    'enter' => false,
                    'adirectory' => 'uploads/avatars',
                ],
            ];
        }

        #[Test]
        public function setTemplateWarningUsesLegacyWarnTemplateFlow(): void
        {
            $html = \setTemplateWarning('warn', [
                'id' => 'warn',
                'text' => 'Warn text',
                'url' => '?mod=test',
                'time' => 3,
            ]);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('LEGACY-WARN', $html);
            $this->assertStringContainsString('Warn text', $html);
            $this->assertStringContainsString('<meta http-equiv="refresh" content="3; url=index.php?mod=test">', $html);
            $this->assertStringNotContainsString('<!doctype html>', $html);
        }

        #[Test]
        public function setTemplateHeadUsesLegacyLoginWithoutTemplate(): void
        {
            $GLOBALS['__test_is_user'] = false;
            $GLOBALS['conf']['users']['enter'] = false;

            $html = \setTemplateHead('X {%login%} Y');

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('legacy-login-without', $html);
            $this->assertStringContainsString('Register', $html);
        }

        #[Test]
        public function setTemplateHeadUsesLegacyLoginLoggedTemplate(): void
        {
            $GLOBALS['__test_is_user'] = true;
            $GLOBALS['user'] = [1, 'BridgeUser'];
            $GLOBALS['__test_user_info'] = ['avatar' => 'missing.gif'];

            $html = \setTemplateHead('X {%login%} Y');

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('legacy-login-logged', $html);
            $this->assertStringContainsString('BridgeUser', $html);
            $this->assertStringContainsString('Logout', $html);
        }

        #[Test]
        public function setTemplateHeadUsesLegacyLoginTemplate(): void
        {
            $GLOBALS['__test_is_user'] = false;
            $GLOBALS['conf']['users']['enter'] = true;
            $GLOBALS['conf']['gfx_chk'] = 2;
            $GLOBALS['__test_captcha'] = '<div class="captcha-box">captcha</div>';
            $GLOBALS['__test_token'] = ['account' => 'account-token'];

            $html = \setTemplateHead('X {%login%} Y');

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('legacy-login', $html);
            $this->assertStringContainsString('Login', $html);
            $this->assertStringContainsString('Nickname', $html);
            $this->assertStringContainsString('Password', $html);
            $this->assertStringContainsString('account-token', $html);
            $this->assertStringContainsString('captcha-box', $html);
        }
    }
}
