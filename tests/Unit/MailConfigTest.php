<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage 1, batch 5 of docs/MAIL-2026.md: the shipped config/mail.php. The file is what a fresh
 * install carries and what an upgrade adds, so its key set and its defaults are a contract rather
 * than a convenience — a divergence between the two is how the plan's dual paths reappear. The keys
 * are also asserted to be sorted, because the admin save rewrites the file through setConfigFile(),
 * which ksort()s it, and a shipped file in another order would differ from a saved one on key order
 * alone.
 */
final class MailConfigTest extends TestCase
{
    private const DEFAULTS = [
        'auth' => '0',
        'backoff' => '300',
        'batch' => '25',
        'dnsttl' => '604800',
        'frommail' => '',
        'fromname' => '',
        'host' => '',
        'keep' => '30',
        'keepbulk' => '3',
        'pass' => '',
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
        foreach (['fromname', 'frommail', 'replyto', 'host', 'user', 'pass'] as $key) {
            $this->assertSame('', $sect[$key], $key.' must ship empty');
        }
    }

    # Keys are stored in the order setConfigFile() writes them, so saving the tab never reorders the file it shipped as
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
}
