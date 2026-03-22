<?php
declare(strict_types=1);

namespace {
    require_once __DIR__.'/../Support/ViewTestBootstrap.php';

    if (!defined('ADMIN_FILE')) define('ADMIN_FILE', true);
    if (!defined('_HOMEPAGE')) define('_HOMEPAGE', 'Homepage');
    if (!defined('_EMAIL')) define('_EMAIL', 'Email');
    if (!defined('_RETYPEPASSWORD')) define('_RETYPEPASSWORD', 'Retype password');
    if (!defined('_CREATEUSERDATA')) define('_CREATEUSERDATA', 'Create user data');
    if (!defined('_YES')) define('_YES', 'Yes');
    if (!defined('_NO')) define('_NO', 'No');
    if (!defined('_SEND')) define('_SEND', 'Send');

    if (!function_exists('getVar')) {
        function getVar(string $source, string $name, string $type, mixed $default = ''): mixed
        {
            return $GLOBALS['__test_get_var'][$source][$name] ?? $default;
        }
    }

    if (!function_exists('getHost')) {
        function getHost(): string
        {
            return $GLOBALS['__test_host'] ?? 'example.test';
        }
    }

    if (!function_exists('setHead')) {
        function setHead(array $seo = []): void
        {
            $GLOBALS['__test_set_head'] = $seo;
        }
    }

    if (!function_exists('setFoot')) {
        function setFoot(): void
        {
            $GLOBALS['__test_set_foot'] = true;
        }
    }
}

namespace Tests\Unit {
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    final class AdminLoginBridgeFlowTest extends TestCase
    {
        protected function setUp(): void
        {
            $GLOBALS['__test_theme'] = 'admin';
            $GLOBALS['__test_is_user'] = false;
            $GLOBALS['__test_user_info'] = [];
            $GLOBALS['__test_captcha'] = '';
            $GLOBALS['__test_token'] = [];
            $GLOBALS['__test_get_var'] = [
                'post' => [
                    'aname' => 'AdminUser',
                    'aemail' => 'admin@example.test',
                ],
            ];
            $GLOBALS['__test_host'] = 'admin.example.test';
            $GLOBALS['conf'] = [
                'theme' => 'default',
                'sitename' => 'SLAED',
                'homeurl' => 'https://slaed.loc',
                'slogan' => 'Fast CMS',
                'site_logo' => 'logo.png',
                'gfx_chk' => 1,
                'users' => [
                    'adirectory' => 'uploads/avatars',
                ],
            ];
        }

        #[Test]
        public function adminLoginFlowUsesNewLoginPartialHappyPath(): void
        {
            $GLOBALS['__test_captcha'] = '<div class="admin-captcha">captcha</div>';

            $html = (new \Template('admin'))->getHtmlPart('login', [
                'route' => 'admin',
                'nickname' => 'Nickname',
                'password' => 'Password',
                'captcha' => $GLOBALS['__test_captcha'],
                'login' => 'Login',
            ]);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('admin-login-form', $html);
            $this->assertStringContainsString('Nickname', $html);
            $this->assertStringContainsString('Password', $html);
            $this->assertStringContainsString('Login', $html);
            $this->assertStringContainsString('admin-captcha', $html);
        }

        #[Test]
        public function adminRegistrationFlowUsesNewRegistrationPartialHappyPath(): void
        {
            $html = (new \Template('admin'))->getHtmlPart('registration', [
                'route' => 'admin',
                'nickname' => 'Nickname',
                'aname' => 'AdminUser',
                'homepage' => 'Homepage',
                'host' => 'admin.example.test',
                'email' => 'Email',
                'aemail' => 'admin@example.test',
                'password' => 'Password',
                'retype' => 'Retype password',
                'createuserdata' => 'Create user data',
                'yes' => 'Yes',
                'no' => 'No',
                'send' => 'Send',
            ]);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('admin-registration-form', $html);
            $this->assertStringContainsString('AdminUser', $html);
            $this->assertStringContainsString('admin.example.test', $html);
            $this->assertStringContainsString('Create user data', $html);
            $this->assertStringContainsString('Send', $html);
        }

        #[Test]
        public function adminLoginFlowReturnsEmptyStringWhenNewThemeIsMissing(): void
        {
            $GLOBALS['__test_captcha'] = '<div class="legacy-captcha">captcha</div>';

            $html = (new \Template('missing-theme'))->getHtmlPart('login', [
                'route' => 'admin',
                'nickname' => 'Nickname',
                'password' => 'Password',
                'captcha' => $GLOBALS['__test_captcha'],
                'login' => 'Login',
            ]);

            $this->assertSame('', $html);
        }

        #[Test]
        public function adminRegistrationFlowReturnsEmptyStringWhenNewThemeIsMissing(): void
        {
            $html = (new \Template('missing-theme'))->getHtmlPart('registration', [
                'route' => 'admin',
                'nickname' => 'Nickname',
                'aname' => 'AdminUser',
                'homepage' => 'Homepage',
                'host' => 'admin.example.test',
                'email' => 'Email',
                'aemail' => 'admin@example.test',
                'password' => 'Password',
                'retype' => 'Retype password',
                'createuserdata' => 'Create user data',
                'yes' => 'Yes',
                'no' => 'No',
                'send' => 'Send',
            ]);

            $this->assertSame('', $html);
        }
    }
}
