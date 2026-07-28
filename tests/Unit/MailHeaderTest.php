<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Stage 1, batch 1 of docs/MAIL-2026.md: the message-composition half of core/classes/mail.php.
 * These are pure functions over one message, so they are exercised directly rather than through a
 * transport: identity resolution against the plan's From/Reply-To table, RFC 2047 subject encoding
 * and folding, RFC 2045 base64 wrapping, CRLF line endings and the address sanitiser that has to
 * abort a send before a CR or LF can reach a header or an SMTP envelope.
 */
final class MailHeaderTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once BASE_DIR.'/core/classes/logger.php';
        require_once BASE_DIR.'/core/classes/mail.php';
    }

    # Build a Mail service over a given mail config section, with no database and a known site name
    private function getMail(array $conf = []): \Mail
    {
        return new \Mail(null, ['sitename' => 'SLAED CMS', 'mail' => $conf]);
    }

    # Call one of the private composition methods, which are private by design so nothing outside the class can compose a message
    private function getCall(\Mail $mailer, string $meth, array $args): mixed
    {
        return (new ReflectionMethod(\Mail::class, $meth))->invokeArgs($mailer, $args);
    }

    # With no identity configured the caller's address carries From and Reply-To, exactly as it does today
    #[Test]
    public function headersFallBackToTheCallerAddress(): void
    {
        $head = $this->getCall($this->getMail(), 'getHeaders', ['', 'info@slaed.net', '', 3]);
        $this->assertStringContainsString('From: "SLAED CMS" <info@slaed.net>', $head);
        $this->assertStringContainsString('Reply-To: <info@slaed.net>', $head);
        $this->assertStringContainsString('X-Priority: 3', $head);
        $this->assertStringContainsString('Content-Transfer-Encoding: base64', $head);
        $this->assertStringContainsString('Content-Type: text/html; charset=UTF-8', $head);
        $this->assertStringContainsString('X-Mailer: SLAED CMS', $head);
    }

    # A configured identity wins over the caller for From and Reply-To, per the plan's resolution table
    #[Test]
    public function headersPreferTheConfiguredIdentity(): void
    {
        $conf = ['fromname' => 'Support', 'frommail' => 'no-reply@slaed.net', 'replyto' => 'help@slaed.net'];
        $head = $this->getCall($this->getMail($conf), 'getHeaders', ['', 'info@slaed.net', '', 1]);
        $this->assertStringContainsString('From: "Support" <no-reply@slaed.net>', $head);
        $this->assertStringContainsString('Reply-To: <help@slaed.net>', $head);
        $this->assertStringNotContainsString('info@slaed.net', $head);
    }

    # An invalid configured sender falls back rather than emitting a broken From
    #[Test]
    public function headersIgnoreAnInvalidConfiguredSender(): void
    {
        $head = $this->getCall($this->getMail(['frommail' => 'not-an-address']), 'getHeaders', ['', 'info@slaed.net', '', 3]);
        $this->assertStringContainsString('From: "SLAED CMS" <info@slaed.net>', $head);
    }

    # Return-Path is written by the receiving MTA, so no message may carry one of ours
    #[Test]
    public function headersNeverCarryReturnPath(): void
    {
        $conf = ['fromname' => 'Support', 'frommail' => 'no-reply@slaed.net', 'replyto' => 'help@slaed.net'];
        $this->assertStringNotContainsString('Return-Path', $this->getCall($this->getMail($conf), 'getHeaders', ['', 'info@slaed.net', '', 3]));
    }

    # Every header line ends with CRLF, which is what the SMTP transport requires and what a bare LF is not
    #[Test]
    public function headerLinesEndWithCrlf(): void
    {
        $head = $this->getCall($this->getMail(), 'getHeaders', ['', 'info@slaed.net', '', 3]);
        $this->assertSame(0, preg_match('/(?<!\r)\n/', $head), 'A header line ended with a bare LF');
        $this->assertSame(7, substr_count($head, "\r\n") + 1);
    }

    # A transport that reads its recipients from the message, as Sendmail does with -t, gets a To header
    #[Test]
    public function theRecipientBecomesAToHeader(): void
    {
        $head = $this->getCall($this->getMail(), 'getHeaders', ['user@slaed.net', 'info@slaed.net', '', 3]);
        $this->assertStringContainsString("\r\nTo: <user@slaed.net>\r\n", $head);
    }

    # PHP mail() writes the To header itself, so passing no recipient must leave the block without one
    #[Test]
    public function noToHeaderIsWrittenWhenTheTransportSuppliesIt(): void
    {
        $head = $this->getCall($this->getMail(), 'getHeaders', ['', 'info@slaed.net', '', 3]);
        $this->assertStringNotContainsString("\r\nTo:", $head);
    }

    # A transport that pipes the whole message, as Sendmail does, carries its subject inside the block rather than beside it
    #[Test]
    public function theSubjectBecomesAHeaderWhenTheTransportNeedsIt(): void
    {
        $head = $this->getCall($this->getMail(), 'getHeaders', ['user@slaed.net', 'info@slaed.net', 'Password reset', 3]);
        $this->assertStringContainsString("\r\nSubject: Password reset\r\n", $head);
    }

    # PHP mail() takes the subject as its own parameter, so passing none must leave the block without a Subject line
    #[Test]
    public function noSubjectHeaderIsWrittenWhenTheTransportSuppliesIt(): void
    {
        $this->assertStringNotContainsString("\r\nSubject:", $this->getCall($this->getMail(), 'getHeaders', ['', 'info@slaed.net', '', 3]));
    }

    # A recipient carrying a line break is dropped by the same sanitiser instead of splitting the block
    #[Test]
    public function anInjectedRecipientNeverReachesTheHeaders(): void
    {
        $head = $this->getCall($this->getMail(), 'getHeaders', ["user@slaed.net\r\nBcc: victim@example.com", 'info@slaed.net', '', 3]);
        $this->assertStringNotContainsString("\r\nTo:", $head);
        $this->assertStringNotContainsString('victim@example.com', $head);
    }

    # A display name is quoted when it is ASCII, because an unquoted comma or dot would end the phrase
    #[Test]
    public function anAsciiDisplayNameIsQuoted(): void
    {
        $name = $this->getCall($this->getMail(), 'getSenderName', ['SLAED, Inc.']);
        $this->assertSame('"SLAED, Inc."', $name);
    }

    # A quote inside a display name is escaped rather than closing the quoted string early
    #[Test]
    public function aQuoteInsideADisplayNameIsEscaped(): void
    {
        $this->assertSame('"The \\"Best\\" Shop"', $this->getCall($this->getMail(), 'getSenderName', ['The "Best" Shop']));
    }

    # A non-ASCII display name becomes a bare B encoded word, which a quoted string would stop the client from decoding
    #[Test]
    public function aNonAsciiDisplayNameIsAnEncodedWord(): void
    {
        $name = $this->getCall($this->getMail(), 'getSenderName', ['Портал SLAED']);
        $this->assertStringStartsWith('=?UTF-8?B?', $name);
        $this->assertStringNotContainsString('"', $name);
    }

    # A long non-ASCII display name folds, and every encoded word stays inside the RFC 2047 limit of 75 characters
    #[Test]
    public function aLongDisplayNameFoldsWithinTheEncodedWordLimit(): void
    {
        $name = $this->getCall($this->getMail(), 'getSenderName', [str_repeat('Портал новостей ', 8)]);
        $this->assertStringContainsString("\r\n ", $name);
        foreach (explode("\r\n", $name) as $line) {
            $this->assertLessThanOrEqual(75, strlen(trim($line)), 'Encoded word longer than 75 characters: '.$line);
        }
    }

    # A long non-ASCII subject folds the same way, and stays B encoded rather than switching scheme
    #[Test]
    public function aLongSubjectFoldsWithinTheEncodedWordLimit(): void
    {
        $subj = $this->getCall($this->getMail(), 'getSubject', [str_repeat('Уведомление о комментарии ', 6)]);
        $this->assertStringStartsWith('=?UTF-8?B?', $subj);
        $this->assertStringContainsString("\r\n ", $subj);
        foreach (explode("\r\n", $subj) as $line) {
            $this->assertLessThanOrEqual(75, strlen(trim($line)), 'Encoded word longer than 75 characters: '.$line);
        }
    }

    # A subject decodes back to what the caller passed, so the encoding is reversible and not merely well-formed
    #[Test]
    public function aSubjectRoundTripsThroughItsEncoding(): void
    {
        $text = 'Новый комментарий к статье';
        $this->assertSame($text, mb_decode_mimeheader($this->getCall($this->getMail(), 'getSubject', [$text])));
    }

    # A control character in a subject is neutralised before encoding, so no header can be split
    #[Test]
    public function aSubjectCannotCarryALineBreak(): void
    {
        $subj = $this->getCall($this->getMail(), 'getSubject', ["Order\r\nBcc: victim@example.com"]);
        $this->assertSame('Order Bcc: victim@example.com', mb_decode_mimeheader($subj));
    }

    # A subject carrying a malformed byte still sends: it degrades to a substitution character instead of vanishing
    #[Test]
    public function aSubjectWithBrokenEncodingSurvives(): void
    {
        $subj = $this->getCall($this->getMail(), 'getSubject', ['Zahlung f'.chr(0xFC).'r Bestellung']);
        $this->assertNotSame('', $subj);
        $this->assertStringContainsString('Zahlung', mb_decode_mimeheader($subj));
        $this->assertStringContainsString('Bestellung', mb_decode_mimeheader($subj));
    }

    # The same holds for a display name, which reaches the header block through the same filter
    #[Test]
    public function aDisplayNameWithBrokenEncodingSurvives(): void
    {
        $head = $this->getCall($this->getMail(['fromname' => 'M'.chr(0xFC).'ller Shop']), 'getHeaders', ['', 'info@slaed.net', '', 3]);
        $this->assertStringContainsString('Shop', mb_decode_mimeheader($head));
        $this->assertStringContainsString('<info@slaed.net>', $head);
    }

    # A 5 KB body wraps at 76 characters instead of leaving one line above the 1000-octet transport limit
    #[Test]
    public function aLargeBodyWrapsAtSeventySixCharacters(): void
    {
        $body = $this->getCall($this->getMail(), 'getBody', [str_repeat('<p>Hello world.</p>', 270)]);
        $this->assertGreaterThan(1, substr_count($body, "\r\n"));
        foreach (explode("\r\n", trim($body)) as $line) {
            $this->assertLessThanOrEqual(76, strlen($line), 'Base64 line longer than 76 characters');
        }
    }

    # A body stored with bare LF is encoded as base64, so the stored line endings cannot reach the wire unwrapped
    #[Test]
    public function aBodyRoundTripsThroughItsEncoding(): void
    {
        $text = "Строка один\nСтрока два";
        $this->assertSame($text, base64_decode(str_replace("\r\n", '', $this->getCall($this->getMail(), 'getBody', [$text]))));
    }

    # Every form of injected line break in an address is refused, which is what aborts the send before a header exists
    #[Test]
    public function anAddressCarryingALineBreakIsRefused(): void
    {
        $bad = [
            "victim@example.com\r\nBcc: other@example.com",
            "victim@example.com\nBcc: other@example.com",
            "victim@example.com\rBcc: other@example.com",
            "victim@example.com\0",
            "victim@example.com\r\n",
            "\tvictim@example.com",
            'not-an-address',
            '',
        ];
        foreach ($bad as $mail) {
            $this->assertSame('', $this->getCall($this->getMail(), 'filterAddress', [$mail]), 'Accepted: '.rawurlencode($mail));
        }
    }

    # A valid address survives the sanitiser untouched, surrounding whitespace apart
    #[Test]
    public function aValidAddressIsKept(): void
    {
        $this->assertSame('user+tag@slaed.net', $this->getCall($this->getMail(), 'filterAddress', ['  user+tag@slaed.net  ']));
    }

    # An injected Reply-To never reaches the header block, because the same sanitiser gates it
    #[Test]
    public function anInjectedReplyToNeverReachesTheHeaders(): void
    {
        $conf = ['replyto' => "help@slaed.net\r\nBcc: victim@example.com"];
        $head = $this->getCall($this->getMail($conf), 'getHeaders', ['', 'info@slaed.net', '', 3]);
        $this->assertStringNotContainsString('Bcc', $head);
        $this->assertStringNotContainsString('victim@example.com', $head);
    }

    # An injected sender name never reaches the header block either, since it is a header value like any other
    #[Test]
    public function anInjectedSenderNameNeverReachesTheHeaders(): void
    {
        $conf = ['fromname' => "Support\r\nBcc: victim@example.com"];
        $head = $this->getCall($this->getMail($conf), 'getHeaders', ['', 'info@slaed.net', '', 3]);
        $this->assertStringContainsString('From: "Support Bcc: victim@example.com" <info@slaed.net>', $head);
        $this->assertSame(0, preg_match('/(?<!\r)\n/', $head), 'A header line ended with a bare LF');
    }
}
