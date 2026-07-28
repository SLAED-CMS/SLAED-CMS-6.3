<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Stage 1, batch 2 of docs/MAIL-2026.md: the enqueue contract and the PHP Mail transport of
 * core/classes/mail.php. Nothing here reaches mail() itself — the covered behaviour is what has to
 * happen before a transport is entered (an address that fails validation aborts the send instead of
 * being cleaned) and what the transport builds around it (the masked log address and the client
 * block that only the originating request can supply).
 */
final class MailTransportTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once BASE_DIR.'/core/classes/logger.php';
        require_once BASE_DIR.'/core/classes/mail.php';
        if (!defined('_IP')) define('_IP', 'IP');
        if (!defined('_BROWSER')) define('_BROWSER', 'Browser');
        if (!defined('_HASH')) define('_HASH', 'Hash');
    }

    # Build a Mail service over a given mail config section, with no database and a known site name
    private function getMail(array $conf = []): \Mail
    {
        return new \Mail(null, ['sitename' => 'SLAED CMS', 'mail' => $conf]);
    }

    # Call one of the private methods, which are private by design so nothing outside the class can compose or deliver a message
    private function getCall(\Mail $mailer, string $meth, array $args): mixed
    {
        return (new ReflectionMethod(\Mail::class, $meth))->invokeArgs($mailer, $args);
    }

    # A recipient carrying a line break is refused before any transport is entered, so an injected header can never reach mail()
    #[Test]
    public function anInjectedRecipientIsRefusedBeforeDelivery(): void
    {
        $mailer = $this->getMail();
        $sent = $mailer->addQueue([
            'kind' => 'account',
            'email' => "user@slaed.net\r\nBcc: victim@example.com",
            'title' => 'Password reset',
            'body' => '<p>Hello</p>',
            'sender' => 'info@slaed.net',
        ]);
        $this->assertFalse($sent);
        $this->assertSame('rejected recipient address', $mailer->getError());
    }

    # An address that is merely malformed is refused the same way, because a send that cannot be addressed must fail loudly rather than silently
    #[Test]
    public function aMalformedRecipientIsRefusedBeforeDelivery(): void
    {
        $mailer = $this->getMail();
        $this->assertFalse($mailer->addQueue(['kind' => 'order', 'email' => 'not-an-address', 'sender' => 'info@slaed.net']));
        $this->assertSame('rejected recipient address', $mailer->getError());
    }

    # With no configured identity and an unusable caller address there is no From to send under, so the message is refused instead of going out as From: <>
    #[Test]
    public function aMessageWithoutAnyValidSenderIsRefused(): void
    {
        $mailer = $this->getMail();
        $this->assertFalse($mailer->addQueue(['kind' => 'contact', 'email' => 'user@slaed.net', 'sender' => 'not-an-address']));
        $this->assertSame('rejected sender address', $mailer->getError());
    }

    # The log carries an address it cannot be used to read, since a log file is kept longer and read by more people than a queue row
    #[Test]
    public function theLoggedAddressIsMasked(): void
    {
        $mailer = $this->getMail();
        $this->assertSame('u***@slaed.net', $this->getCall($mailer, 'getMaskedMail', ['user@slaed.net']));
        $this->assertSame('***', $this->getCall($mailer, 'getMaskedMail', ['not-an-address']));
        $this->assertSame('***', $this->getCall($mailer, 'getMaskedMail', ['']));
        $this->assertSame('***', $this->getCall($mailer, 'getMaskedMail', ["user@slaed.net\r\nBcc: victim@example.com"]));
    }

    # Without a template the client block keeps the exact plain-text form addMail() produced, so the six call sites that ask for it read the same
    #[Test]
    public function theClientBlockKeepsItsPlainTextForm(): void
    {
        $text = $this->getCall($this->getMail(), 'getClientBlock', []);
        $this->assertSame(PHP_EOL.'IP: 127.0.0.1'.PHP_EOL.'Browser: PHPUnit'.PHP_EOL.'Hash: '.md5('PHPUnit'), $text);
    }
}
