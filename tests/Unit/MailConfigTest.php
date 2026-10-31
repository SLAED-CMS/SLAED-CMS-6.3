<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The shipped config/mail.php is what a fresh install carries and an upgrade adds, so its key set and defaults are a contract, not a convenience
final class MailConfigTest extends TestCase
{
    private const DEFAULTS = [
        'auth' => '0',
        'backoff' => '300',
        'batch' => '25',
        'bounce' => '',
        'dnsttl' => '604800',
        'frommail' => '',
        'fromname' => '',
        'host' => '',
        'keep' => '30',
        'keepbulk' => '3',
        'pass' => '',
        'pophost' => '',
        'poppass' => '',
        'popport' => '995',
        'popsecure' => 'ssl',
        'popuser' => '',
        'port' => '587',
        'rate' => '60',
        'replyto' => '',
        'secure' => 'none',
        'sendmail' => '/usr/sbin/sendmail',
        'timeout' => '10',
        'transport' => 'php',
        'tries' => '5',
        'user' => '',
        'verify' => '1',
    ];

    # Read the shipped file the way getConfig() does, as a plain array under its own section name
    private function getSection(): array
    {
        $data = require BASE_DIR.'/config/mail.php';
        $this->assertIsArray($data);
        $this->assertArrayHasKey('mail', $data);
        return $data['mail'];
    }

    # Every documented key ships with the documented default, so an installation that configures nothing still has a complete section
    #[Test]
    public function theShippedSectionCarriesEveryDocumentedDefault(): void
    {
        $this->assertSame(self::DEFAULTS, $this->getSection());
    }

    # The default transport is PHP mail(), which is the stage's compatibility claim: an upgrade that configures nothing delivers exactly as before
    #[Test]
    public function theDefaultTransportIsPhpMail(): void
    {
        $this->assertSame('php', $this->getSection()['transport']);
    }

    # The credentials and the site-derived identity fields ship empty, so nothing in the repository carries a secret or a host-specific address
    #[Test]
    public function noCredentialOrIdentityFieldIsShippedFilled(): void
    {
        $sect = $this->getSection();
        foreach (['fromname', 'frommail', 'replyto', 'bounce', 'host', 'user', 'pass', 'pophost', 'popuser', 'poppass'] as $key) {
            $this->assertSame('', $sect[$key], $key.' must ship empty');
        }
    }

    # Keys are stored in the order setConfigFile() writes them after its ksort(), so saving the tab never reorders the file it shipped as
    #[Test]
    public function theKeyOrderMatchesWhatASaveWouldWrite(): void
    {
        $keys = array_keys($this->getSection());
        $sort = $keys;
        sort($sort);
        $this->assertSame($sort, $keys);
    }

    # Every value is a string, because setConfigFile() casts scalars to strings and a shipped integer would change type on the first save
    #[Test]
    public function everyValueIsStoredAsAString(): void
    {
        foreach ($this->getSection() as $key => $val) {
            $this->assertIsString($val, $key.' must be stored as a string');
        }
    }

    # The general settings rewrite global.php whole, so their form keeps mtemp it no longer shows; the mail section holds a password and stays the main administrator's
    #[Test]
    public function theMailFormWritesItsOwnKeysAlone(): void
    {
        $conf = (string)file_get_contents(BASE_DIR.'/admin/modules/config.php');
        $mail = (string)file_get_contents(BASE_DIR.'/admin/modules/newsletter.php');
        $this->assertStringContainsString("'mtemp' => (string)\$conf['mtemp'],", $conf, 'A save of the general settings drops the mail frame from global.php');
        $this->assertStringNotContainsString("'mail.php'", $conf, 'The general settings still write the mail settings');
        $this->assertStringContainsString("setConfigFile('mail.php', \$mail,", $mail, 'A save without a password no longer keeps the stored one');
        $this->assertMatchesRegularExpression("#setConfigFile\('global\.php', \['mtemp' => [^;]+\], \\\$glob\);#", $mail, 'The mail form writes global.php without its other keys');
        $this->assertStringContainsString("|| !isAdmin(true)) die('Illegal file access');", $mail, 'The mail password is open to more than the main administrator');
    }
}
