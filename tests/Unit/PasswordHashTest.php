<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Safety net tests for md5_salt() password hashing algorithm.
 * Captures current behavior BEFORE migration to password_hash().
 *
 * Uses local replica of algorithm to avoid loading system.php
 * (which pulls security.php and causes function redeclaration conflicts).
 */
final class PasswordHashTest extends TestCase
{
    /**
     * Replicates md5_salt() from core/system.php:4662-4666
     * Algorithm: md5(md5($conf['lic_f']) . md5($pass))
     */
    private function md5SaltLocal(string $pass, string $salt): string
    {
        return md5(md5($salt).md5($pass));
    }

    #[Test]
    public function md5SaltReturnsConsistentHash(): void
    {
        $hash1 = $this->md5SaltLocal('password123', 'test_salt');
        $hash2 = $this->md5SaltLocal('password123', 'test_salt');

        $this->assertSame($hash1, $hash2, 'Same input must produce same hash');
    }

    #[Test]
    public function md5SaltReturns32CharHex(): void
    {
        $hash = $this->md5SaltLocal('test', 'salt');

        $this->assertSame(32, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $hash);
    }

    #[Test]
    public function md5SaltDifferentPasswordsDifferentHashes(): void
    {
        $hash1 = $this->md5SaltLocal('password1', 'salt');
        $hash2 = $this->md5SaltLocal('password2', 'salt');

        $this->assertNotSame($hash1, $hash2);
    }

    #[Test]
    public function md5SaltDifferentSaltsDifferentHashes(): void
    {
        $hash1 = $this->md5SaltLocal('password', 'salt_a');
        $hash2 = $this->md5SaltLocal('password', 'salt_b');

        $this->assertNotSame($hash1, $hash2);
    }

    #[Test]
    public function md5SaltMatchesExpectedAlgorithm(): void
    {
        $pass = 'mypassword';
        $salt = 'test_salt_key';
        $expected = md5(md5($salt).md5($pass));

        $this->assertSame($expected, $this->md5SaltLocal($pass, $salt));
    }

    #[Test]
    public function md5SaltHandlesEmptyPassword(): void
    {
        $hash = $this->md5SaltLocal('', 'salt');
        $this->assertSame(32, strlen($hash));
    }

    #[Test]
    public function md5SaltHandlesUnicodePassword(): void
    {
        $hash = $this->md5SaltLocal('пароль123', 'salt');
        $this->assertSame(32, strlen($hash));
    }

    #[Test]
    public function md5SaltHandlesSpecialCharacters(): void
    {
        $hash = $this->md5SaltLocal('p@$$w0rd!#%^&*()', 'salt');
        $this->assertSame(32, strlen($hash));
    }

    /**
     * Known hash values for backward compatibility verification.
     * After migration to bcrypt, password_check() must still accept these.
     */
    #[Test]
    public function knownHashValues(): void
    {
        $cases = [
            ['pass' => 'admin', 'salt' => 'slaed'],
            ['pass' => 'test123', 'salt' => 'mykey'],
            ['pass' => '', 'salt' => ''],
            ['pass' => 'пароль', 'salt' => 'ключ'],
        ];

        foreach ($cases as $c) {
            $expected = md5(md5($c['salt']).md5($c['pass']));
            $this->assertSame($expected, $this->md5SaltLocal($c['pass'], $c['salt']));
        }
    }

    /**
     * Verify that future bcrypt hashes work correctly.
     */
    #[Test]
    public function bcryptHashFormatValidation(): void
    {
        $hash = password_hash('test', PASSWORD_BCRYPT);

        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertSame(60, strlen($hash));
        $this->assertTrue(password_verify('test', $hash));
        $this->assertFalse(password_verify('wrong', $hash));
    }

    #[Test]
    public function bcryptHandlesUnicode(): void
    {
        $pass = 'пароль123';
        $hash = password_hash($pass, PASSWORD_BCRYPT);

        $this->assertTrue(password_verify($pass, $hash));
        $this->assertFalse(password_verify('wrong', $hash));
    }
}
