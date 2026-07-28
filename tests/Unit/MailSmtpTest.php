<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Stage 1, batch 4 of docs/MAIL-2026.md: the SMTP transport of core/classes/mail.php. The dialogue is
 * driven over a socket pair standing in for the relay, so the eight named hazards are exercised against
 * real socket semantics rather than a mock: multi-line replies, an unexpected code, dot-stuffing, CRLF
 * line endings, the mechanism actually advertised, the timeout, and the socket being closed on every
 * failure path. No test reaches a real relay, and no credential is allowed into a failure message.
 * Since stage 2 a message enters the transport the way the drain sends it, through the private send
 * path, because addQueue() stores a row and delivers nothing.
 */
final class MailSmtpTest extends TestCase
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

    # Call one of the private dialogue methods, which are private by design so nothing outside the class can deliver a message
    private function getCall(\Mail $mailer, string $meth, array $args = []): mixed
    {
        return (new ReflectionMethod(\Mail::class, $meth))->invokeArgs($mailer, $args);
    }

    # Read one of the private fields the dialogue keeps, so a test can assert what a stage 2 queue row would record
    private function getField(\Mail $mailer, string $name): mixed
    {
        return (new ReflectionProperty(\Mail::class, $name))->getValue($mailer);
    }

    # Stand a socket pair in for the relay: the class writes into one end, the test preloads its answers into the other and reads back what was sent
    # Both ends carry a short timeout so a missing answer fails the test in seconds instead of holding the suite
    private function getRelay(\Mail $mailer, string $answ): mixed
    {
        $pair = stream_socket_pair(STREAM_PF_INET, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if (!is_array($pair)) $this->markTestSkipped('this host cannot create a socket pair');
        stream_set_timeout($pair[0], 2);
        stream_set_timeout($pair[1], 2);
        fwrite($pair[1], $answ);
        (new ReflectionProperty(\Mail::class, 'sock'))->setValue($mailer, $pair[0]);
        return $pair[1];
    }

    # Deliver one message the way the drain does, through the transport rather than through the queue, which from stage 2 is the only path a message takes to a relay
    private function getSend(\Mail $mailer, string $subj = 'Test', string $body = 'Hello'): bool
    {
        return (bool)$this->getCall($mailer, 'setDelivery', ['user@slaed.net', 'info@slaed.net', $subj, $body, 3]);
    }

    # Read everything the class wrote to the relay, without blocking on a connection it left open
    private function getSent(mixed $peer): string
    {
        stream_set_blocking($peer, false);
        $text = stream_get_contents($peer) ?: '';
        fclose($peer);
        return $text;
    }

    # A multi-line reply is read to its final line, which is the classic way a hand-written dialogue desynchronises
    #[Test]
    public function aMultiLineReplyIsReadToItsFinalLine(): void
    {
        $mailer = $this->getMail();
        $peer = $this->getRelay($mailer, "250-slaed.net at your service\r\n250-SIZE 35651584\r\n250 HELP\r\n");
        $text = $this->getCall($mailer, 'getSmtpReply');
        $this->assertSame('250', $this->getField($mailer, 'code'));
        $this->assertStringContainsString('250-SIZE 35651584', $text);
        $this->assertStringContainsString('250 HELP', $text);
        $this->getSent($peer);
    }

    # A single-line reply ends the read at once, so the next command is not answered by a line left in the buffer
    #[Test]
    public function aSingleLineReplyEndsTheRead(): void
    {
        $mailer = $this->getMail();
        $peer = $this->getRelay($mailer, "220 relay ready\r\n250 second line\r\n");
        $this->assertSame('220 relay ready', $this->getCall($mailer, 'getSmtpReply'));
        $this->assertSame('250 second line', $this->getCall($mailer, 'getSmtpReply'));
        $this->getSent($peer);
    }

    # An unexpected code aborts with the server's own text preserved rather than being treated as success
    #[Test]
    public function anUnexpectedCodeAbortsWithTheServerText(): void
    {
        $mailer = $this->getMail();
        $peer = $this->getRelay($mailer, "550 relay access denied\r\n");
        $this->assertFalse($this->getCall($mailer, 'checkSmtpStep', ['', '2', 'from']));
        $this->assertSame('the relay refused at from: 550 relay access denied', $mailer->getError());
        $this->assertSame('550', $this->getField($mailer, 'code'));
        $this->assertSame('from', $this->getField($mailer, 'phase'));
        $this->getSent($peer);
    }

    # A failure closes the socket, so no exit path leaves the relay holding a session or the object holding a dialogue in an unknown state
    #[Test]
    public function everyFailurePathClosesTheSocket(): void
    {
        $mailer = $this->getMail();
        $peer = $this->getRelay($mailer, "554 transaction failed\r\n");
        $this->assertFalse($this->getCall($mailer, 'checkSmtpStep', ['', '2', 'data']));
        $this->assertNull($this->getField($mailer, 'sock'));
        $this->assertStringContainsString('QUIT', $this->getSent($peer));
    }

    # A relay that never ends its reply is dropped instead of being followed for as many lines as it cares to send
    #[Test]
    public function anEndlessReplyIsBounded(): void
    {
        $mailer = $this->getMail();
        $peer = $this->getRelay($mailer, str_repeat("250-still talking\r\n", 120));
        $text = $this->getCall($mailer, 'getSmtpReply');
        $this->assertSame('the relay sent more continuation lines than one reply may carry', $text);
        $this->assertSame('', $this->getField($mailer, 'code'));
        $this->getSent($peer);
    }

    # The opening dialogue runs greeting, EHLO and nothing else when neither encryption nor authentication is configured
    #[Test]
    public function theOpeningDialogueGreetsAndSaysEhlo(): void
    {
        $mailer = $this->getMail();
        $peer = $this->getRelay($mailer, "220 relay ready\r\n250-slaed.net\r\n250 HELP\r\n");
        $this->assertTrue($this->getCall($mailer, 'checkSmtpGreet'));
        $sent = $this->getSent($peer);
        $this->assertStringStartsWith('EHLO ', $sent);
        $this->assertStringEndsWith("\r\n", $sent);
        $this->assertStringNotContainsString('HELO ', $sent);
    }

    # A relay that does not speak ESMTP is greeted again with HELO instead of losing the connection over a capability question
    #[Test]
    public function anEhloRefusalFallsBackToHelo(): void
    {
        $mailer = $this->getMail();
        $peer = $this->getRelay($mailer, "220 relay ready\r\n500 command not recognized\r\n250 slaed.net\r\n");
        $this->assertTrue($this->getCall($mailer, 'checkSmtpGreet'));
        $sent = $this->getSent($peer);
        $this->assertMatchesRegularExpression('/^EHLO [^\r\n]+\r\nHELO [^\r\n]+\r\n$/', $sent);
    }

    # Authentication uses the mechanism the relay advertised, and PLAIN carries the credentials in one command rather than three round trips
    #[Test]
    public function authPlainIsUsedWhenTheRelayAdvertisesIt(): void
    {
        $mailer = $this->getMail(['auth' => '1', 'user' => 'postmaster@slaed.net', 'pass' => 'secretpass']);
        $peer = $this->getRelay($mailer, "220 relay ready\r\n250-slaed.net\r\n250 AUTH PLAIN LOGIN\r\n235 authenticated\r\n");
        $this->assertTrue($this->getCall($mailer, 'checkSmtpGreet'));
        $sent = $this->getSent($peer);
        $this->assertStringContainsString('AUTH PLAIN '.base64_encode("\0".'postmaster@slaed.net'."\0".'secretpass'), $sent);
        $this->assertStringNotContainsString('AUTH LOGIN', $sent);
    }

    # A relay offering only LOGIN gets the three-step form, with the user and the password each sent as their own base64 line
    #[Test]
    public function authLoginIsUsedWhenItIsTheOnlyMechanism(): void
    {
        $mailer = $this->getMail(['auth' => '1', 'user' => 'postmaster@slaed.net', 'pass' => 'secretpass']);
        $answ = "220 relay ready\r\n250-slaed.net\r\n250 AUTH LOGIN\r\n334 VXNlcm5hbWU6\r\n334 UGFzc3dvcmQ6\r\n235 authenticated\r\n";
        $peer = $this->getRelay($mailer, $answ);
        $this->assertTrue($this->getCall($mailer, 'checkSmtpGreet'));
        $sent = $this->getSent($peer);
        $this->assertStringContainsString("AUTH LOGIN\r\n".base64_encode('postmaster@slaed.net')."\r\n".base64_encode('secretpass')."\r\n", $sent);
    }

    # A mechanism nobody advertised is never attempted, because guessing one costs a round trip and tells the relay we do not read its answers
    #[Test]
    public function anUnadvertisedMechanismIsNeverAttempted(): void
    {
        $mailer = $this->getMail(['auth' => '1', 'user' => 'postmaster@slaed.net', 'pass' => 'secretpass']);
        $peer = $this->getRelay($mailer, "220 relay ready\r\n250-slaed.net\r\n250 AUTH CRAM-MD5 GSSAPI\r\n");
        $this->assertFalse($this->getCall($mailer, 'checkSmtpGreet'));
        $this->assertSame('the relay advertises no supported authentication mechanism', $mailer->getError());
        $this->assertStringNotContainsString('AUTH', $this->getSent($peer));
    }

    # A refused login says so without carrying the password or its encoded form into the message that reaches the log
    #[Test]
    public function aRefusedLoginNeverCarriesTheCredentials(): void
    {
        $mailer = $this->getMail(['auth' => '1', 'user' => 'postmaster@slaed.net', 'pass' => 'secretpass']);
        $peer = $this->getRelay($mailer, "220 relay ready\r\n250 AUTH PLAIN\r\n535 authentication failed\r\n");
        $this->assertFalse($this->getCall($mailer, 'checkSmtpGreet'));
        $this->assertSame('the relay refused at auth: 535 authentication failed', $mailer->getError());
        $this->assertStringNotContainsString('secretpass', $mailer->getError());
        $this->assertStringNotContainsString(base64_encode("\0".'postmaster@slaed.net'."\0".'secretpass'), $mailer->getError());
        $this->getSent($peer);
    }

    # Authentication configured without credentials is refused before a command is written, because an empty AUTH only teaches the relay to rate-limit us
    #[Test]
    public function authWithoutCredentialsIsRefusedBeforeAnyCommand(): void
    {
        $mailer = $this->getMail(['auth' => '1', 'user' => '', 'pass' => '']);
        $peer = $this->getRelay($mailer, "220 relay ready\r\n250 AUTH PLAIN\r\n");
        $this->assertFalse($this->getCall($mailer, 'checkSmtpGreet'));
        $this->assertSame('smtp authentication is configured without a user or a password', $mailer->getError());
        $this->assertStringNotContainsString('AUTH', $this->getSent($peer));
    }

    # The message dialogue runs in order, and the whole message leaves inside one DATA phase closed by the terminating dot
    #[Test]
    public function theMessageDialogueRunsInOrder(): void
    {
        $mailer = $this->getMail(['transport' => 'smtp', 'host' => 'relay.slaed.net']);
        $peer = $this->getRelay($mailer, "250 sender ok\r\n250 recipient ok\r\n354 end with a dot\r\n250 queued as 2B1F\r\n");
        $this->assertTrue($this->getSend($mailer, 'Password reset', '<p>Hello</p>'));
        $text = $this->getSent($peer);
        $this->assertStringStartsWith("MAIL FROM:<info@slaed.net>\r\nRCPT TO:<user@slaed.net>\r\nDATA\r\n", $text);
        $this->assertStringEndsWith("\r\n.\r\n", $text);
        $this->assertStringContainsString("\r\nTo: <user@slaed.net>\r\n", $text);
        $this->assertStringContainsString("\r\nSubject: Password reset\r\n", $text);
        $this->assertSame(0, preg_match('/(?<!\r)\n/', $text), 'A line went on the wire with a bare LF');
    }

    # A second message rides the connection the first one opened, which is what makes a stage 2 drain run one handshake rather than one per row
    #[Test]
    public function aSecondMessageReusesTheOpenConnection(): void
    {
        $mailer = $this->getMail(['transport' => 'smtp', 'host' => 'relay.slaed.net']);
        $peer = $this->getRelay($mailer, str_repeat("250 sender ok\r\n250 recipient ok\r\n354 end with a dot\r\n250 queued\r\n", 2));
        $this->assertTrue($this->getSend($mailer));
        $this->assertTrue($this->getSend($mailer));
        $text = $this->getSent($peer);
        $this->assertSame(2, substr_count($text, 'MAIL FROM:<info@slaed.net>'));
        $this->assertStringNotContainsString('EHLO', $text);
        $this->assertStringNotContainsString('QUIT', $text);
    }

    # A relay refusing the recipient fails the message with its own text instead of writing a body nobody accepted
    #[Test]
    public function aRefusedRecipientEndsTheMessage(): void
    {
        $mailer = $this->getMail(['transport' => 'smtp', 'host' => 'relay.slaed.net']);
        $peer = $this->getRelay($mailer, "250 sender ok\r\n550 no such user here\r\n");
        $this->assertFalse($this->getSend($mailer));
        $this->assertSame('the relay refused at rcpt: 550 no such user here', $mailer->getError());
        $this->assertSame('rcpt', $this->getField($mailer, 'phase'));
        $this->assertStringNotContainsString('DATA', $this->getSent($peer));
    }

    # A body line beginning with a dot is stuffed, so no part of a message can end the DATA phase early
    #[Test]
    public function aBodyLineBeginningWithADotIsStuffed(): void
    {
        $data = $this->getCall($this->getMail(), 'getSmtpData', [".hidden\r\ntext\r\n.\r\nmore"]);
        $this->assertStringStartsWith("..hidden\r\ntext\r\n..\r\nmore", $data);
        $this->assertStringEndsWith("\r\n.\r\n", $data);
        $this->assertSame(1, preg_match_all('/\r\n\.\r\n/', $data), 'More than one bare dot line reached the wire');
    }

    # A body stored with bare LF goes on the wire as CRLF, because a lone LF is not a line ending in SMTP
    #[Test]
    public function bareLineFeedsBecomeCrlfOnTheWire(): void
    {
        $data = $this->getCall($this->getMail(), 'getSmtpData', ["first\nsecond\rthird\r\nfourth"]);
        $this->assertSame("first\r\nsecond\r\nthird\r\nfourth\r\n.\r\n", $data);
    }

    # The terminating sequence is added once even when the message already ends with a line break
    #[Test]
    public function theTerminatingDotIsAddedOnce(): void
    {
        $data = $this->getCall($this->getMail(), 'getSmtpData', ["line\r\n"]);
        $this->assertSame("line\r\n.\r\n", $data);
    }

    # An smtp transport without a host is refused by the smtp arm itself, which is also what shows the match selected it
    #[Test]
    public function theSmtpTransportRefusesAnEmptyHost(): void
    {
        $mailer = $this->getMail(['transport' => 'smtp']);
        $this->assertFalse($this->getSend($mailer));
        $this->assertSame('no smtp host is configured', $mailer->getError());
        $this->assertSame('connect', $this->getField($mailer, 'phase'));
    }

    # A black-holed port fails on the configured timeout instead of hanging the request that triggered the send
    #[Test]
    public function aBlackHoledPortFailsOnTheTimeout(): void
    {
        $mailer = $this->getMail(['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => '59991', 'timeout' => '1']);
        $time = microtime(true);
        $this->assertFalse($this->getSend($mailer));
        $this->assertStringStartsWith('the relay could not be reached', $mailer->getError());
        $this->assertNull($this->getField($mailer, 'sock'));
        $this->assertLessThan(10, microtime(true) - $time, 'The connect attempt outlived its timeout');
    }
}
