<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Basic filesystem structure checks for SLAED CMS.
 */
final class StructureTest extends TestCase
{
    #[Test]
    public function entryFilesExist(): void
    {
        $this->assertFileExists(BASE_DIR.'/index.php');
        $this->assertFileExists(BASE_DIR.'/admin.php');
        $this->assertFileExists(BASE_DIR.'/setup.php');
        $this->assertFileExists(BASE_DIR.'/core/system.php');
    }

    #[Test]
    public function coreDirectoriesExist(): void
    {
        $this->assertDirectoryExists(BASE_DIR.'/core');
        $this->assertDirectoryExists(BASE_DIR.'/modules');
        $this->assertDirectoryExists(BASE_DIR.'/templates');
        $this->assertDirectoryExists(BASE_DIR.'/config');
    }

    #[Test]
    public function keyModulesExist(): void
    {
        $this->assertFileExists(BASE_DIR.'/modules/news/index.php');
        $this->assertFileExists(BASE_DIR.'/modules/account/index.php');
        $this->assertFileExists(BASE_DIR.'/modules/forum/index.php');
    }
}
