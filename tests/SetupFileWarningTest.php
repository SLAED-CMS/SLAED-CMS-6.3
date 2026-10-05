<?php

use PHPUnit\Framework\TestCase;

# Checks that setup.php is removed in production
class SetupFileWarningTest extends TestCase
{
    public function testSetupFileNotPresentInProduction(): void
    {
        $env = strtolower((string) getenv('SLAED_ENV'));
        if ($env !== 'production') {
            $this->markTestSkipped('SLAED_ENV is not production');
            return;
        }

        $setupFile = dirname(__DIR__).'/public/setup.php';
        $this->assertFileDoesNotExist($setupFile, 'setup.php must be removed in production');
    }
}
