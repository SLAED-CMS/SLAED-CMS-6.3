<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# Regression tests of the cache store against production code: the directory race of Cache
final class CacheContractTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2).'/core/classes/cache.php';
    }

    # A request that loses the race to create a cache directory takes it as created without a warning; a wrapper replays the race: missing, then File exists
    #[Test]
    public function aLostDirectoryRaceIsNoWarning(): void
    {
        $race = new class {
            public static int $seen = 0;
            public mixed $context;

            public function url_stat(string $path, int $flags): array|false
            {
                return self::$seen++ === 0 ? false : ['mode' => 0040777];
            }

            public function mkdir(string $path, int $mode, int $options): bool
            {
                trigger_error('mkdir(): File exists', E_USER_WARNING);
                return false;
            }
        };
        stream_wrapper_register('slaedrace', get_class($race));
        $warns = [];
        set_error_handler(function (int $code, string $text) use (&$warns): bool {
            $warns[] = $text;
            return true;
        });
        try {
            $made = (new \ReflectionMethod(\Cache::class, 'setDirPath'))->invoke(null, 'slaedrace://cache/data');
        } finally {
            restore_error_handler();
            stream_wrapper_unregister('slaedrace');
        }
        $this->assertTrue($made, 'A directory created by the parallel request counts as missing');
        $this->assertSame([], $warns, 'The lost race still raises a warning');
    }

    # The sweep removes what was not rewritten within the window, everything for zero, and never a lock file or a protected marker, however old
    #[Test]
    public function theSweepKeepsLocksAndMarkers(): void
    {
        $dir = sys_get_temp_dir().'/slaed-sweep-'.bin2hex(random_bytes(4));
        mkdir($dir.'/sub', 0777, true);
        $old = time() - 7200;
        foreach (['old.json', 'sub/old.php', 'compile.lock', 'sub/index.html', '.htaccess'] as $name) {
            file_put_contents($dir.'/'.$name, 'x');
            touch($dir.'/'.$name, $old);
        }
        file_put_contents($dir.'/new.json', 'x');
        try {
            $this->assertSame(2, \Cache::deleteStale($dir, 3600), 'The sweep does not remove exactly the two stale entries');
            $this->assertFileExists($dir.'/new.json', 'The sweep removed a fresh entry');
            $this->assertSame(1, \Cache::deleteStale($dir, 0), 'A zero window does not remove every entry');
            foreach (['compile.lock', 'sub/index.html', '.htaccess'] as $name) $this->assertFileExists($dir.'/'.$name, 'The sweep removed '.$name);
        } finally {
            foreach (['old.json', 'sub/old.php', 'compile.lock', 'sub/index.html', '.htaccess', 'new.json'] as $name) if (is_file($dir.'/'.$name)) unlink($dir.'/'.$name);
            rmdir($dir.'/sub');
            rmdir($dir);
        }
    }
}
