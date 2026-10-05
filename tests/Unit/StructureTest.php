<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# Basic filesystem structure checks for SLAED CMS
final class StructureTest extends TestCase
{
    #[Test]
    public function entryFilesExist(): void
    {
        foreach (['core/system.php', 'core/stream.php', 'admin/index.php', '.htaccess', 'nginx.conf.example'] as $one) $this->assertFileExists(BASE_DIR.'/'.$one);
        foreach (['index.php', 'admin.php', 'setup.php', 'update.php', '.htaccess'] as $one) $this->assertFileExists(PUBLIC_DIR.'/'.$one);
        foreach (['index.php', 'admin.php', 'setup.php', 'update.php'] as $one) $this->assertFileDoesNotExist(BASE_DIR.'/'.$one, 'An entry is left beside the project: '.$one);
    }

    #[Test]
    public function coreDirectoriesExist(): void
    {
        $this->assertDirectoryExists(BASE_DIR.'/core');
        $this->assertDirectoryExists(BASE_DIR.'/modules');
        $this->assertDirectoryExists(BASE_DIR.'/public/templates');
        $this->assertDirectoryExists(BASE_DIR.'/config');
    }

    #[Test]
    public function keyModulesExist(): void
    {
        $this->assertFileExists(BASE_DIR.'/modules/node/index.php');
        $this->assertFileExists(BASE_DIR.'/modules/account/index.php');
        $this->assertFileExists(BASE_DIR.'/modules/forum/index.php');
    }
}
