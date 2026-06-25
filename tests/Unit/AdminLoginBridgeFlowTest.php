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
                'theme' => 'admin',
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
        public function adminLoginFlowUsesNewAuthFormPartialHappyPath(): void
        {
            $tpl = new \Template('admin');
            $captcha = '<div class="admin-captcha">captcha</div>';
            $html = $tpl->getHtmlPart('auth-form', [
                'route' => 'admin',
                'rows' => [
                    ['has_colon' => true, 'label' => 'Nickname', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'name', 'placeholder_text' => 'Nickname', 'is_required' => true])],
                    ['has_colon' => true, 'label' => 'Password', 'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'pwd', 'placeholder_text' => 'Password', 'is_required' => true])],
                    ['field_html' => $captcha],
                ],
                'hidden' => ['name_attr' => 'op', 'value_attr' => 'check_admin'],
                'submit' => ['button_type' => 'submit', 'submit_label' => 'Login'],
            ]);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('action="admin.php"', $html);
            $this->assertStringContainsString('Nickname', $html);
            $this->assertStringContainsString('Password', $html);
            $this->assertStringContainsString('Login', $html);
            $this->assertStringContainsString('admin-captcha', $html);
            $this->assertStringContainsString('name="op"', $html);
        }

        #[Test]
        public function adminRegistrationFlowUsesNewAuthFormPartialHappyPath(): void
        {
            $tpl = new \Template('admin');
            $html = $tpl->getHtmlPart('auth-form', [
                'route' => 'admin',
                'rows' => [
                    ['has_colon' => true, 'label' => 'Nickname', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'aname', 'value_attr' => 'AdminUser'])],
                    ['has_colon' => true, 'label' => 'Homepage', 'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'aurl', 'value_attr' => 'http://admin.example.test'])],
                    ['has_colon' => true, 'label' => 'Email', 'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'aemail', 'value_attr' => 'admin@example.test'])],
                    ['label' => 'Create user data', 'field_html' => $tpl->getHtmlFrag('radio', ['name_attr' => 'auser_new', 'value_attr' => '1', 'label_text' => 'Yes', 'is_checked' => true])],
                ],
                'hidden' => ['name_attr' => 'op', 'value_attr' => 'add_admin'],
                'submit' => ['button_type' => 'submit', 'submit_label' => 'Send'],
            ]);

            $this->assertNotSame('', $html);
            $this->assertStringContainsString('action="admin.php"', $html);
            $this->assertStringContainsString('AdminUser', $html);
            $this->assertStringContainsString('admin.example.test', $html);
            $this->assertStringContainsString('Create user data', $html);
            $this->assertStringContainsString('Send', $html);
        }

        #[Test]
        public function adminAuthFormReturnsEmptyStringWhenThemeIsMissing(): void
        {
            $html = (new \Template('missing-theme'))->getHtmlPart('auth-form', [
                'route' => 'admin',
                'rows' => [
                    ['has_colon' => true, 'label' => 'Nickname', 'field_html' => '<input name="name">'],
                    ['has_colon' => true, 'label' => 'Password', 'field_html' => '<input name="pwd">'],
                ],
                'hidden' => ['name_attr' => 'op', 'value_attr' => 'check_admin'],
                'submit' => ['button_type' => 'submit', 'submit_label' => 'Login'],
            ]);

            $this->assertSame('', $html);
        }
    }
}
