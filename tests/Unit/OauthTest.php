<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for the Oauth client (core/classes/oauth.php).
 *
 * Covers the pure, DB-free surface: redirect normalization, base64url,
 * claim mapping and sanitization, Microsoft issuer validation and the full
 * id_token (JWT) validation pipeline against a locally generated RSA key.
 * The JWT cases use a synthetic 'unittest' provider so only jwks_unittest.json
 * is written, never the real google/microsoft caches. Signature-dependent
 * cases self-skip when the runtime cannot generate a key.
 */
final class OauthTest extends TestCase
{
    private static mixed $key = null;
    private static string $kid = 'unit-kid';
    private static string $jwksFile = '';

    public static function setUpBeforeClass(): void
    {
        require_once BASE_DIR.'/core/classes/oauth.php';
        self::$jwksFile = CACHE_DIR.'/jwks_unittest.json';
        $GLOBALS['conf']['security'] = ['log_size' => 262144];
        $GLOBALS['conf']['homeurl'] = 'https://slaed.loc';
        $GLOBALS['conf']['oauth'] = [
            'active' => '1',
            'unittest' => [
                'active' => '1', 'clientid' => 'client-123.apps', 'secret' => 's',
                'scopes' => 'openid email profile', 'auth' => 'x', 'token' => 'x', 'userinfo' => 'x',
                'jwks' => 'https://invalid.local/jwks',
                'iss' => 'https://accounts.google.com,accounts.google.com', 'isstpl' => '', 'tenant' => '', 'prompt' => '',
            ],
        ];
        self::$key = self::makeKey();
        if (self::$key !== null) {
            $det = openssl_pkey_get_details(self::$key);
            $jwk = [
                'kty' => 'RSA', 'kid' => self::$kid, 'alg' => 'RS256',
                'n' => \Oauth::getEncode($det['rsa']['n']), 'e' => \Oauth::getEncode($det['rsa']['e']),
            ];
            file_put_contents(self::$jwksFile, json_encode(['time' => time(), 'keys' => [$jwk]]));
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$jwksFile !== '' && is_file(self::$jwksFile)) unlink(self::$jwksFile);
    }

    private static function makeKey(): mixed
    {
        $cfgs = glob('C:/OSPanel/modules/PHP-*/Apache/conf/openssl.cnf') ?: [];
        $opts = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        set_error_handler(static fn (): bool => true);
        $res = false;
        foreach ([...$cfgs, null] as $cfg) {
            $res = openssl_pkey_new($cfg !== null ? $opts + ['config' => $cfg] : $opts);
            if ($res !== false) break;
        }
        restore_error_handler();
        return ($res !== false) ? $res : null;
    }

    private function token(array $pay, ?array $head = null): string
    {
        $head ??= ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => self::$kid];
        $one = \Oauth::getEncode(json_encode($head));
        $two = \Oauth::getEncode(json_encode($pay));
        openssl_sign($one.'.'.$two, $sig, self::$key, OPENSSL_ALGO_SHA256);
        return $one.'.'.$two.'.'.\Oauth::getEncode($sig);
    }

    private function base(): array
    {
        $now = time();
        return [
            'iss' => 'https://accounts.google.com', 'aud' => 'client-123.apps', 'sub' => 'g-sub-1',
            'email' => 'user@gmail.com', 'email_verified' => true, 'name' => 'Test User',
            'iat' => $now, 'exp' => $now + 3600, 'nonce' => 'nonce-abc',
        ];
    }

    private function requireKey(): void
    {
        if (self::$key === null) $this->markTestSkipped('OpenSSL key generation unavailable in this runtime');
    }

    #[Test]
    public function redirectRejectsSchemesHostsAndControlChars(): void
    {
        $this->assertSame('index.php?name=news', \Oauth::getRedirect('index.php?name=news'));
        $this->assertSame('index.php', \Oauth::getRedirect('https://evil.example/x'));
        $this->assertSame('index.php', \Oauth::getRedirect('https:evil.example'));
        $this->assertSame('index.php', \Oauth::getRedirect('JaVaScRiPt:alert(1)'));
        $this->assertSame('index.php', \Oauth::getRedirect('data:text/html,x'));
        $this->assertSame('index.php', \Oauth::getRedirect('//evil.example'));
        $this->assertSame('index.php', \Oauth::getRedirect('/index.php'));
        $this->assertSame('index.php', \Oauth::getRedirect(''));
        $this->assertStringNotContainsString("\n", \Oauth::getRedirect("index.php?a=1\r\nSet-Cookie: x=y"));
    }

    #[Test]
    public function base64UrlRoundTrips(): void
    {
        $raw = random_bytes(48);
        $enc = \Oauth::getEncode($raw);
        $this->assertDoesNotMatchRegularExpression('#[+/=]#', $enc);
        $this->assertSame($raw, \Oauth::getDecode($enc));
    }

    #[Test]
    public function claimsMapGoogleVerification(): void
    {
        $c = \Oauth::getClaims($this->base(), 'google');
        $this->assertSame('g-sub-1', $c['sub']);
        $this->assertTrue($c['verified']);
        $this->assertSame('Test User', $c['name']);
        $c = \Oauth::getClaims(['email_verified' => false] + $this->base(), 'google');
        $this->assertFalse($c['verified']);
    }

    #[Test]
    public function claimsMicrosoftTrustsOnlyEdovOrConsumerTenant(): void
    {
        $consumer = '9188040d-6c67-4c5b-b112-36a304b66dad';
        $ms = ['sub' => 'ms', 'email' => 'u@outlook.com', 'tid' => $consumer];
        $this->assertTrue(\Oauth::getClaims($ms, 'microsoft')['verified']);
        $org = ['sub' => 'ms', 'email' => 'u@corp.com', 'tid' => 'org-tenant'];
        $this->assertFalse(\Oauth::getClaims($org, 'microsoft')['verified']);
        $this->assertTrue(\Oauth::getClaims(['xms_edov' => true] + $org, 'microsoft')['verified']);
    }

    #[Test]
    public function claimsAreSanitized(): void
    {
        $c = \Oauth::filterClaims(['sub' => ' s ', 'email' => ' User@Ex.COM ', 'verified' => true, 'name' => "Bad\x01  Name\n"]);
        $this->assertSame('s', $c['sub']);
        $this->assertSame('user@ex.com', $c['email']);
        $this->assertSame('Bad Name', $c['name']);
        $c = \Oauth::filterClaims(['email' => 'not-an-email', 'verified' => true]);
        $this->assertSame('', $c['email']);
        $this->assertFalse($c['verified']);
    }

    #[Test]
    public function checkMsIssuerEnforcesTenantTemplate(): void
    {
        $tpl = 'https://login.microsoftonline.com/{tid}/v2.0';
        \Oauth::checkMsIssuer('https://login.microsoftonline.com/abc/v2.0', 'abc', $tpl, 'common');
        $this->expectException(RuntimeException::class);
        \Oauth::checkMsIssuer('https://login.microsoftonline.com/other/v2.0', 'abc', $tpl, 'common');
    }

    #[Test]
    public function jwtValidatesToken(): void
    {
        $this->requireKey();
        $pay = \Oauth::getJwtPayload($this->token($this->base()), 'unittest', 'nonce-abc');
        $this->assertSame('g-sub-1', $pay['sub']);
        $pay = \Oauth::getJwtPayload($this->token(['iss' => 'accounts.google.com'] + $this->base()), 'unittest', 'nonce-abc');
        $this->assertSame('accounts.google.com', $pay['iss']);
        $pay = \Oauth::getJwtPayload($this->token(['aud' => ['x', 'client-123.apps']] + $this->base()), 'unittest', 'nonce-abc');
        $this->assertSame('g-sub-1', $pay['sub']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: array<string, mixed>|null}>
     */
    public static function badTokenProvider(): array
    {
        $now = time();
        return [
            'expired' => [['exp' => $now - 120], 'jwt_expired', null],
            'nbf future' => [['nbf' => $now + 300], 'jwt_not_yet', null],
            'iat future' => [['iat' => $now + 300], 'jwt_bad_iat', null],
            'bad iss' => [['iss' => 'https://evil.example'], 'jwt_bad_iss', null],
            'bad aud' => [['aud' => 'other'], 'jwt_bad_aud', null],
            'bad azp' => [['azp' => 'other'], 'jwt_bad_azp', null],
            'hs alg' => [[], 'jwt_bad_sig', ['alg' => 'HS256', 'kid' => 'unit-kid']],
        ];
    }

    #[Test]
    #[DataProvider('badTokenProvider')]
    public function jwtRejectsInvalidTokens(array $override, string $code, ?array $head): void
    {
        $this->requireKey();
        try {
            \Oauth::getJwtPayload($this->token($override + $this->base(), $head), 'unittest', 'nonce-abc');
            $this->fail('Expected RuntimeException '.$code);
        } catch (RuntimeException $e) {
            $this->assertSame($code, $e->getMessage());
        }
    }

    #[Test]
    public function jwtRejectsBadNonceAndTamperedSignature(): void
    {
        $this->requireKey();
        try {
            \Oauth::getJwtPayload($this->token($this->base()), 'unittest', 'wrong-nonce');
            $this->fail('Expected jwt_bad_nonce');
        } catch (RuntimeException $e) {
            $this->assertSame('jwt_bad_nonce', $e->getMessage());
        }
        $tok = $this->token($this->base());
        $tampered = substr($tok, 0, strrpos($tok, '.')).'.'.\Oauth::getEncode(str_repeat('x', 256));
        try {
            \Oauth::getJwtPayload($tampered, 'unittest', 'nonce-abc');
            $this->fail('Expected jwt_bad_sig');
        } catch (RuntimeException $e) {
            $this->assertSame('jwt_bad_sig', $e->getMessage());
        }
    }
}
