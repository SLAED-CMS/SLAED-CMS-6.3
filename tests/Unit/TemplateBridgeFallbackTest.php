<?php
declare(strict_types=1);

namespace {
    require_once __DIR__.'/../Support/ViewTestBootstrap.php';
}

namespace Tests\Unit {
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    final class TemplateBridgeFallbackTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['__test_theme'] = 'default';
            $GLOBALS['__test_is_user'] = false;
            $GLOBALS['__test_user_info'] = [];
            $GLOBALS['__test_captcha'] = '';
            $GLOBALS['__test_token'] = ['account' => 'token-account'];
            $GLOBALS['user'] = [0, 'FallbackUser'];
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
            $GLOBALS['__test_templates'] = [
                'warn' => 'LEGACY-WARN:{%text%}:{%id%}',
                'login-without' => '<div class="legacy-login-without">{%register%}</div>',
                'login-logged' => '<div class="legacy-login-logged">{%user%}:{%logout%}:{%avatar%}</div>',
                'login' => '<div class="legacy-login">{%login%}:{%nickname%}:{%password%}:{%captcha%}:{%token%}:{%lost%}:{%register%}</div>',
            ];
        }

        #[Test]
        public function setTemplateWarningUsesLegacyWarnTemplateWhenThemeChanges(): void
        {
            $GLOBALS['__test_theme'] = 'missing-theme';

            $html = \setTemplateWarning('warn', [
                'id' => 'warn',
                'text' => 'Warn text',
            ]);

            $this->assertNotSame('', $html);
            $this->assertStringNotContainsString('<!doctype html>', $html);
            $this->assertStringContainsString('Warn text', $html);
            $this->assertStringContainsString('LEGACY-WARN', $html);
        }

        #[Test]
        public function setTemplateHeadGuestUsesLegacyTemplateWhenThemeChanges(): void
        {
            $GLOBALS['__test_is_user'] = false;
            $GLOBALS['conf']['users']['enter'] = false;
            $GLOBALS['conf']['theme'] = 'missing-theme';

            $html = \setTemplateHead('A {%login%} B');

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('Register', $html);
            $this->assertStringContainsString('legacy-login-without', $html);
        }

        #[Test]
        public function setTemplateHeadLoggedUsesLegacyTemplateWhenThemeChanges(): void
        {
            $GLOBALS['__test_is_user'] = true;
            $GLOBALS['user'] = [1, 'BridgeFallback'];
            $GLOBALS['__test_user_info'] = ['avatar' => 'missing.gif'];
            $GLOBALS['conf']['theme'] = 'missing-theme';

            $html = \setTemplateHead('A {%login%} B');

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('BridgeFallback', $html);
            $this->assertStringContainsString('Logout', $html);
            $this->assertStringContainsString('legacy-login-logged', $html);
        }

        #[Test]
        public function setTemplateHeadLoginUsesLegacyTemplateWhenThemeChanges(): void
        {
            $GLOBALS['__test_is_user'] = false;
            $GLOBALS['conf']['users']['enter'] = true;
            $GLOBALS['conf']['theme'] = 'missing-theme';
            $GLOBALS['conf']['gfx_chk'] = 2;
            $GLOBALS['__test_captcha'] = '<div class="legacy-captcha">captcha</div>';
            $GLOBALS['__test_token'] = ['account' => 'legacy-token'];

            $html = \setTemplateHead('A {%login%} B');

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('legacy-login', $html);
            $this->assertStringContainsString('Login', $html);
            $this->assertStringContainsString('legacy-token', $html);
        }
    }
}
