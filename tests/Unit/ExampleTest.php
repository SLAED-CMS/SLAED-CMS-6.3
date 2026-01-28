<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Example unit test to verify PHPUnit is working
 */
class ExampleTest extends TestCase
{
    #[Test]
    public function phpunitIsWorking(): void
    {
        $this->assertTrue(true);
    }

    #[Test]
    public function baseDirConstantIsDefined(): void
    {
        $this->assertTrue(defined('BASE_DIR'));
        $this->assertDirectoryExists(BASE_DIR);
    }

    #[Test]
    public function globalConfArrayExists(): void
    {
        $this->assertIsArray($GLOBALS['conf']);
        $this->assertArrayHasKey('homeurl', $GLOBALS['conf']);
    }
}
