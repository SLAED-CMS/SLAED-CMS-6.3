<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for password hashing after migration to password_hash(PASSWORD_BCRYPT).
 *
 * md5_salt() has been removed. The legacy md5 algorithm is inlined in
 * checkPassHash() for transparent upgrade on first login with an old hash.
 */
final class PasswordHashTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Bcrypt (current)
    // -------------------------------------------------------------------------

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

    #[Test]
    public function bcryptHashesAreUnique(): void
    {
        $hash1 = password_hash('same', PASSWORD_BCRYPT);
        $hash2 = password_hash('same', PASSWORD_BCRYPT);

        $this->assertNotSame($hash1, $hash2, 'Each bcrypt call should produce a unique hash (different salt)');
        $this->assertTrue(password_verify('same', $hash1));
        $this->assertTrue(password_verify('same', $hash2));
    }

    // -------------------------------------------------------------------------
    // Legacy md5 detection (inlined in checkPassHash)
    // -------------------------------------------------------------------------

    #[Test]
    public function legacyMd5HashIsDetectedByFormat(): void
    {
        $legacyHash = md5(md5('test_salt') . md5('password'));

        $this->assertSame(32, strlen($legacyHash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $legacyHash);
        $this->assertFalse((bool) password_verify('password', $legacyHash), 'Legacy hash must not pass password_verify');
    }

    #[Test]
    public function bcryptHashIsNotMistakenForLegacy(): void
    {
        $hash = password_hash('test', PASSWORD_BCRYPT);

        $this->assertNotSame(32, strlen($hash));
        $this->assertStringStartsWith('$2y$', $hash);
    }
}
