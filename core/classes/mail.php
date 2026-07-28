<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Outgoing mail service: sender identity resolution, MIME header assembly, subject and body encoding, address validation and the shared error channel
class Mail {

    public const CHARSET = 'UTF-8';
    public const CRLF = "\r\n";

    private ?Database $db;
    private array $conf;
    private string $site;
    private string $error = '';

    # Build the service from the site config; the database handle is kept for the queue and is unused while mail still leaves inside the request
    public function __construct(?Database $db, array $conf) {
        $this->db = $db;
        $this->conf = is_array($conf['mail'] ?? null) ? $conf['mail'] : [];
        $this->site = $conf['sitename'] ?? '';
    }

    # Return the description of the last failure
    public function getError(): string {
        return $this->error;
    }

    # Record the last failure in the site log and return false so a caller can return it directly
    private function setError(string $mesg, array $ctx = []): bool {
        $this->error = $mesg;
        Logger::addSite('error', 'Mail: '.$mesg, $ctx);
        return false;
    }

    # Validate one address for header and envelope use; control characters are refused before whitespace is trimmed, so a trailing CRLF cannot be trimmed into a valid address
    private function filterAddress(string $mail): string {
        if (preg_match('/[\x00-\x1F\x7F]/', $mail)) return '';
        $mail = trim($mail);
        return (filter_var($mail, FILTER_VALIDATE_EMAIL) === false) ? '' : $mail;
    }

    # Replace control characters in a header text value with a space, so neither a line break nor a NUL can reach a header
    # The match is deliberately bytewise: a UTF-8 aware pattern returns null on a malformed byte and would drop the whole value instead of cleaning it
    private function filterHeader(string $text): string {
        return trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $text) ?? '');
    }

    # Resolve the From address: the configured identity when it is set and valid, otherwise the address the caller sends on behalf of
    private function getSender(string $smail): string {
        $from = $this->filterAddress($this->conf['frommail'] ?? '');
        return ($from !== '') ? $from : $this->filterAddress($smail);
    }

    # Encode a display name as an RFC 2047 encoded word, or quote it when it is plain ASCII, because an encoded word inside a quoted string is never decoded
    private function getSenderName(string $name): string {
        $name = $this->filterHeader($name);
        if ($name === '') return '';
        if (preg_match('/[^\x20-\x7E]/', $name)) return mb_encode_mimeheader($name, self::CHARSET, 'B', self::CRLF, 6);
        return '"'.addcslashes($name, '"\\').'"';
    }

    # Encode a subject as an RFC 2047 header value folded inside the 75-character encoded-word limit; the indent accounts for the Subject label
    private function getSubject(string $text): string {
        return mb_encode_mimeheader($this->filterHeader($text), self::CHARSET, 'B', self::CRLF, 9);
    }

    # Encode a body as base64 wrapped at 76 characters, because an unwrapped line breaks the 1000-octet transport limit
    private function getBody(string $text): string {
        return chunk_split(base64_encode($text), 76, self::CRLF);
    }

    # Assemble the MIME header block for one already validated sender; no Return-Path is written because the receiving MTA owns that header
    # An empty recipient means the transport writes its own To, which is what PHP mail() does and what a second To header would duplicate
    private function getHeaders(string $rcpt, string $smail, int $prio): string {
        $rcpt = $this->filterAddress($rcpt);
        $from = $this->getSender($smail);
        $name = ($this->conf['fromname'] ?? '') !== '' ? $this->conf['fromname'] : $this->site;
        $reply = ($this->conf['replyto'] ?? '') !== '' ? $this->conf['replyto'] : $smail;
        $name = $this->getSenderName($name);
        $reply = $this->filterAddress($reply);
        $head = ['MIME-Version: 1.0'];
        $head[] = 'Content-Type: text/html; charset='.self::CHARSET;
        $head[] = 'Content-Transfer-Encoding: base64';
        $head[] = 'From: '.(($name !== '') ? $name.' ' : '').'<'.$from.'>';
        if ($rcpt !== '') $head[] = 'To: <'.$rcpt.'>';
        if ($reply !== '') $head[] = 'Reply-To: <'.$reply.'>';
        $head[] = 'X-Priority: '.$prio;
        $head[] = 'X-Mailer: SLAED CMS';
        return implode(self::CRLF, $head);
    }
}
