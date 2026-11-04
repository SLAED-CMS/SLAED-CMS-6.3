<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Template;

require_once dirname(__DIR__, 2).'/core/classes/template.php';

# The compiled cache of a template file: the cache job may remove a compiled file while a request still renders it
final class TemplateCacheTest extends TestCase
{
    private static string $dir = '';

    # A template double over a private theme folder and a private cache, so no theme file and no site cache take part
    private static function getTemplate(): object
    {
        self::$dir = sys_get_temp_dir().'/slaed_tpl_cache_'.getmypid();
        if (!is_dir(self::$dir.'/theme/fragments')) mkdir(self::$dir.'/theme/fragments', 0777, true);
        file_put_contents(self::$dir.'/theme/fragments/probe.html', "<b>{{ text }}</b>\n");
        return new class (self::$dir) extends Template {
            public function __construct(string $dir)
            {
                $this->theme = 'probe';
                $this->base = str_replace('\\', '/', $dir).'/theme';
                $this->real = rtrim(str_replace('\\', '/', (string)realpath($this->base)), '/');
                $this->cache = str_replace('\\', '/', $dir).'/cache';
            }

            public function getProbe(array $data): string
            {
                return $this->getHtml('fragments', 'probe', $data);
            }
        };
    }

    public static function tearDownAfterClass(): void
    {
        foreach ([self::$dir.'/cache/*', self::$dir.'/theme/fragments/*'] as $mask) foreach (glob($mask) ?: [] as $file) unlink($file);
        foreach (['/cache', '/theme/fragments', '/theme', ''] as $sub) if (is_dir(self::$dir.$sub)) rmdir(self::$dir.$sub);
    }

    # A compiled file removed between two renders of one request is compiled again, and the second render neither warns nor comes out empty
    #[Test]
    public function aRemovedCompiledFileIsCompiledAgain(): void
    {
        $tpl = self::getTemplate();
        $this->assertSame("<b>one</b>\n", $tpl->getProbe(['text' => 'one']));
        foreach (glob(self::$dir.'/cache/*.php') ?: [] as $file) unlink($file);
        $seen = [];
        set_error_handler(static function (int $num, string $text) use (&$seen): bool {
            $seen[] = $text;
            return true;
        });
        try {
            $html = $tpl->getProbe(['text' => 'two']);
        } finally {
            restore_error_handler();
        }
        $this->assertSame([], $seen, 'The render included the removed compiled file');
        $this->assertSame("<b>two</b>\n", $html);
        $this->assertNotSame([], glob(self::$dir.'/cache/*.php') ?: [], 'The template was not compiled again');
    }
}
