<?php
declare(strict_types=1);

namespace {
    require_once __DIR__.'/../Support/ViewTestBootstrap.php';

    if (!function_exists('getSecret')) {
        function getSecret(string $purpose): string
        {
            return 'secret-of-'.$purpose;
        }
    }

    require_once BASE_DIR.'/core/classes/captcha.php';
}

namespace Tests\Unit {
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    # What Captcha::html() prints for each look of security.captcha.display, through the real fragment of both themes
    final class CaptchaLookTest extends TestCase
    {
        private const ACTS = ['register', 'contact', 'comment', 'login', 'adminlogin'];

        private array $keep = [];

        protected function setUp(): void
        {
            foreach (['conf', 'tpl', 'locale', '__test_theme', '__test_is_user'] as $name) $this->keep[$name] = $GLOBALS[$name] ?? null;
        }

        protected function tearDown(): void
        {
            foreach ($this->keep as $name => $value) $GLOBALS[$name] = $value;
        }

        # Render the captcha of one action as a guest sees it, with the look the settings name
        private function getHtml(string $theme, ?string $look, string $act): string
        {
            $cap = ['active' => '1', 'provider' => 'altcha', 'register' => '1', 'contact' => '1', 'comments' => '1', 'login_user' => 'always', 'login_admin' => 'always'];
            if ($look !== null) $cap['display'] = $look;
            $GLOBALS['__test_theme'] = $theme;
            $GLOBALS['__test_is_user'] = false;
            $GLOBALS['conf']['security']['captcha'] = $cap;
            $GLOBALS['locale'] = 'en';
            $GLOBALS['tpl'] = new \Template($theme);
            return \Captcha::html($act);
        }

        # Read the configuration the widget is given back out of its attribute
        private function getSetup(string $html): array
        {
            $this->assertSame(1, preg_match('/<altcha-widget [^>]*configuration="([^"]*)"/', $html, $hit), 'The widget carries no configuration');
            $data = json_decode(html_entity_decode($hit[1], ENT_QUOTES), true);
            $this->assertIsArray($data, 'The configuration of the widget is no JSON');
            return $data;
        }

        # An installation that names no look, and one that names an unknown one, hides the widget on every form
        #[Test]
        public function theCaptchaIsHiddenUnlessTheVisibleLookIsChosen(): void
        {
            foreach (['admin', 'aurora'] as $theme) {
                foreach ([null, 'floating', 'overlay', ''] as $look) {
                    foreach (self::ACTS as $act) {
                        $html = $this->getHtml($theme, $look, $act);
                        $note = $theme.', '.var_export($look, true).', '.$act;
                        $this->assertSame(['hideFooter' => true, 'hideLogo' => true, 'display' => 'floating', 'floatingPersist' => true], $this->getSetup($html), $note);
                        $this->assertStringContainsString('<div class="sl-captcha" data-display="floating">', $html, $note);
                    }
                }
            }
        }

        # The visible look keeps the frame with the checkbox on every form, the login of the panel included
        #[Test]
        public function theVisibleLookKeepsTheFrame(): void
        {
            foreach (['admin', 'aurora'] as $theme) {
                foreach (self::ACTS as $act) {
                    $html = $this->getHtml($theme, 'standard', $act);
                    $this->assertSame(['hideFooter' => true, 'hideLogo' => true, 'display' => 'standard', 'floatingPersist' => true], $this->getSetup($html), $theme.', '.$act);
                    $this->assertStringContainsString('<div class="sl-captcha" data-display="standard">', $html, $theme.', '.$act);
                }
            }
        }

        # Either look proves on the press of the button and sends the honeypot and the time token with the form
        #[Test]
        public function eitherLookKeepsTheProofAndTheTraps(): void
        {
            foreach (['admin', 'aurora'] as $theme) {
                foreach (['floating', 'standard'] as $look) {
                    $html = $this->getHtml($theme, $look, 'comment');
                    $this->assertStringContainsString(' auto="onsubmit"', $html, $theme.', '.$look);
                    $this->assertStringContainsString('challenge="/index.php?go=captcha&amp;act=comment"', $html, $theme.', '.$look);
                    $this->assertStringContainsString('name="'.\Captcha::honeypotField().'"', $html, $theme.', '.$look);
                    $this->assertSame(1, preg_match('/name="'.\Captcha::timeField().'" value="\d+\.[0-9a-f]{64}"/', $html), $theme.', '.$look);
                }
            }
        }

        # The two themes ship one fragment, so a look is never the affair of one theme
        #[Test]
        public function theThemesShareTheFragment(): void
        {
            $this->assertFileEquals(PUBLIC_DIR.'/templates/admin/fragments/captcha-altcha.html', PUBLIC_DIR.'/templates/aurora/fragments/captcha-altcha.html');
        }
    }
}
