<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The one helper of a fraction, filterDecimal(), on the six languages of the system; filterSize() goes through it
final class DecimalTextTest extends TestCase
{
    # The six languages of the system with the mark of a fraction each writes
    public static function getLanguages(): array
    {
        return ['ru' => ['ru', ','], 'uk' => ['uk', ','], 'de' => ['de', ','], 'fr' => ['fr', ','], 'pl' => ['pl', ','], 'en' => ['en', '.']];
    }

    #[Test]
    #[DataProvider('getLanguages')]
    public function fractionTakesTheMarkOfItsLanguage(string $lang, string $mark): void
    {
        $script = dirname(__DIR__).'/Support/decimal_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($lang).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
        $this->assertStringStartsWith($lang, $data['locale']);
        $this->assertSame('96'.$mark.'6', $data['free']);
        $this->assertSame('1234567', $data['whole']);
        $this->assertSame("1\u{00A0}234\u{00A0}567".$mark.'89', $data['fixed']);
        $this->assertSame('3'.$mark.'0', $data['zero']);
        $this->assertSame('1'.$mark.'5 KB', $data['kilo']);
        $this->assertSame('1 MB', $data['mega']);
        $this->assertSame('0 Bytes', $data['none']);
    }
}
