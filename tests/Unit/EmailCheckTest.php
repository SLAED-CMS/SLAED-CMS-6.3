<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The one check of a typed address, checkemail(), and the registration that must store the address it passed
final class EmailCheckTest extends TestCase
{
    # Run tests/Support/email_probe.php over the addresses and return which of them passed
    private function getVerdicts(array $mails): array
    {
        $script = dirname(__DIR__).'/Support/email_probe.php';
        $file = tempnam(sys_get_temp_dir(), 'slaed_mail');
        file_put_contents($file, json_encode($mails));
        $code = '$argv[1] = file_get_contents('.var_export($file, true).'); require '.var_export($script, true).';';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code).' 2>&1');
        unlink($file);
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
        return $data;
    }

    #[Test]
    public function addressOfTodayPasses(): void
    {
        $data = $this->getVerdicts([
            'plain' => 'ivan@example.com',
            'plus' => 'ivan.petrov+news@gmail.com',
            'zone' => 'ivan@example.photography',
            'case' => 'Ivan_P-1@Mail.Example.co.uk',
            'space' => ' ivan@example.com ',
        ]);
        foreach ($data as $key => $pass) $this->assertTrue($pass, 'The address "'.$key.'" was refused');
        $this->assertCount(5, $data);
    }

    #[Test]
    public function brokenAddressIsRefused(): void
    {
        $data = $this->getVerdicts([
            'empty' => '',
            'nozone' => 'user@domain',
            'short' => 'ivan@example.c',
            'inner' => 'ivan petrov@example.com',
            'coded' => 'ivan%2Bnews@example.com',
            'tag' => '<b>ivan@example.com',
            'quote' => 'ivan"@example.com',
            'www' => 'www.ivan@example.com',
            'lines' => "ivan@example.com\nbcc@example.com",
            'long' => str_repeat('a', 250).'@example.com',
        ]);
        foreach ($data as $key => $pass) $this->assertFalse($pass, 'The address "'.$key.'" passed');
        $this->assertCount(10, $data);
    }

    #[Test]
    public function registrationStoresTheAddressItChecked(): void
    {
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/modules/account/index.php');
        $this->assertMatchesRegularExpression('/function finnewuser\(\).*?checkuser\(\$nick, \$mail, \$rules\);/s', $code);
        $this->assertSame(1, preg_match('/function newuser\(\): void \{.*?\n\}\n\nfunction activate/s', $code, $hit));
        $this->assertStringNotContainsString('filterText($mail)', $hit[0], 'filterText() decodes a URL, so a plus of the address would be stored and shown as a space');
    }

    #[Test]
    public function recoveryLinkCarriesTheAddressEncoded(): void
    {
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/modules/account/index.php');
        $this->assertSame(1, preg_match('/function passmail\(\): void \{.*?\n\}\n/s', $code, $hit));
        $this->assertSame(5, substr_count($hit[0], "'email' => rawurlencode(\$email)]"), 'PHP reads a bare plus of a query as a space');
        $this->assertDoesNotMatchRegularExpression('/\'op\' => \'passlost\'[^\]]*\'email\' => \$email\]/', $hit[0]);
    }
}
