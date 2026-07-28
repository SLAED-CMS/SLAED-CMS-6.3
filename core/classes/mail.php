<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Outgoing mail service: sender identity resolution, MIME header assembly, subject and body encoding, address validation, delivery and the shared error channel
class Mail {

    public const CHARSET = 'UTF-8';
    public const CRLF = "\r\n";
    private const ERRLEN = 255;

    private ?Database $db;
    private array $conf;
    private string $site;
    private string $error = '';
    private bool $fback = false;
    private bool $ftry = false;

    # Build the service from the site config; the database handle is kept for the queue and is unused while mail still leaves inside the request
    public function __construct(?Database $db, array $conf) {
        $this->db = $db;
        $this->conf = is_array($conf['mail'] ?? null) ? $conf['mail'] : [];
        $this->site = $conf['sitename'] ?? '';
    }

    # Accept one message from a call site and deliver it; from stage 2 the same call stores a queue row instead, which is why no caller learns more than accepted or rejected
    # The client block is appended here, inside the request that owns the visitor data, and never at send time where only the scheduler's own address would be available
    public function addQueue(array $mesg): bool {
        $kind = $mesg['kind'] ?? '';
        $rcpt = $this->filterAddress($mesg['email'] ?? '');
        if ($rcpt === '') return $this->setError('rejected recipient address', ['kind' => $kind]);
        $smail = $mesg['sender'] ?? '';
        if ($this->getSender($smail) === '') return $this->setError('rejected sender address', ['kind' => $kind]);
        $body = $mesg['body'] ?? '';
        if (!empty($mesg['client'])) $body .= $this->getClientBlock();
        return $this->setDelivery($rcpt, $smail, $mesg['title'] ?? '', $body, ($mesg['prio'] ?? 0) ?: 3);
    }

    # Return the description of the last failure
    public function getError(): string {
        return $this->error;
    }

    # Record the last failure in the site log and return false so a caller can return it directly
    # The text is bounded in characters rather than bytes, because a transport answers with a response of its own length and a multibyte one must not be cut mid-character
    private function setError(string $mesg, array $ctx = []): bool {
        $this->error = mb_substr($mesg, 0, self::ERRLEN);
        Logger::addSite('error', 'Mail: '.$mesg, $ctx);
        return false;
    }

    # Mask an address for the log, because a log is read by more people than a queue row and must not accumulate readable address lists
    # A value that does not validate is logged as a mask alone: masking its local part would still copy whatever an injection appended behind the address into the log
    private function getMaskedMail(string $mail): string {
        $mail = $this->filterAddress($mail);
        if ($mail === '') return '***';
        return substr($mail, 0, 1).'***'.substr($mail, strpos($mail, '@'));
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

    # Build the originating IP, browser and agent hash block a caller asks for with client, keeping the template form when a template is available and the plain form when it is not
    private function getClientBlock(): string {
        global $tpl;
        $agent = getAgent();
        if ($tpl instanceof Template) {
            return $tpl->getHtmlPart('message-block', [
                'title' => '',
                'lines' => [
                    ['label' => _IP, 'value' => getIp()],
                    ['label' => _BROWSER, 'value' => $agent],
                    ['label' => _HASH, 'value' => md5($agent)],
                ],
            ]);
        }
        return PHP_EOL._IP.': '.getIp().PHP_EOL._BROWSER.': '.$agent.PHP_EOL._HASH.': '.md5($agent);
    }

    # Assemble the MIME header block for one already validated sender; no Return-Path is written because the receiving MTA owns that header
    # An empty recipient means the transport writes its own To, which is what PHP mail() does and what a second To header would duplicate
    # An empty subject means the same for Subject, which PHP mail() takes as its own parameter while a piped or dialogued message has to carry it inside the block
    private function getHeaders(string $rcpt, string $smail, string $subj, int $prio): string {
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
        if ($subj !== '') $head[] = 'Subject: '.$subj;
        $head[] = 'X-Priority: '.$prio;
        $head[] = 'X-Mailer: SLAED CMS';
        return implode(self::CRLF, $head);
    }

    # Encode the transport-independent parts of one message and hand it to the configured transport, which is the only path from stage 2 the drain uses
    # An unset or unknown transport delivers through PHP mail(), so an installation that upgrades and configures nothing keeps sending the way it did before
    private function setDelivery(string $rcpt, string $smail, string $text, string $body, int $prio): bool {
        $subj = $this->getSubject($text);
        $body = $this->getBody($body);
        return match ($this->conf['transport'] ?? '') {
            'sendmail' => $this->addSendMail($rcpt, $smail, $subj, $body, $prio),
            default => $this->addPhpMail($rcpt, $smail, $subj, $body, $prio),
        };
    }

    # Deliver through PHP mail(), the default transport, with the envelope sender following the resolved From so the receiving side authenticates the address the message claims
    # A host refusing the fifth parameter is expected rather than fatal: the message goes out without it and the parameter is dropped for the rest of the run
    # The fallback is attempted and logged once per run, so a transport failing for its own reasons costs one attempt per message rather than two
    # The warning handler captures the text instead of discarding it, because an unhandled warning inside a pseudo-cron request would leak into the response body
    private function addPhpMail(string $rcpt, string $smail, string $subj, string $body, int $prio): bool {
        $head = $this->getHeaders('', $smail, '', $prio);
        $from = $this->getSender($smail);
        $args = ($from !== '' && !$this->fback) ? '-f'.$from : '';
        $warn = '';
        $back = false;
        set_error_handler(static function (int $code, string $text) use (&$warn): bool {
            $warn = $text;
            return true;
        }, E_WARNING);
        $sent = ($args !== '') ? mail($rcpt, $subj, $body, $head, $args) : mail($rcpt, $subj, $body, $head);
        if (!$sent && $args !== '' && !$this->ftry) {
            $this->ftry = true;
            $warn = '';
            $sent = mail($rcpt, $subj, $body, $head);
            $back = $sent;
        }
        restore_error_handler();
        if ($back) {
            $this->fback = true;
            Logger::addSite('warning', 'Mail: the host refused the envelope sender, delivered without it', ['transport' => 'php']);
        }
        if ($sent) return true;
        $mesg = 'php mail() refused the message'.(($warn !== '') ? ': '.$this->filterHeader($warn) : '');
        return $this->setError($mesg, ['transport' => 'php', 'mail' => $this->getMaskedMail($rcpt)]);
    }

    # Deliver by piping the message into the configured Sendmail binary through proc_open(), which reports an exit status where popen() would hide it
    # The command is an argument array and never a string, so no shell is involved: mail.sendmail holds a path and every argument is supplied here
    # The binary is started with -t so it takes its recipient from the To header, -i so a lone dot cannot end the message, and -f for the envelope sender
    # proc_open() is probed rather than assumed because shared hosting lists it in disable_functions, and the path must be an existing executable file
    # Both output pipes are drained before the exit status is read, because a binary writing to stdout would otherwise block on a full pipe while we wait for it to finish
    # A short write is a failure of its own: a binary that died before reading the message can still exit zero, and an unchecked fwrite() would report that as an accepted delivery
    # The write uses the same warning handler as PHP mail(), because a closed pipe raises one and an unhandled warning would leak into the response body
    private function addSendMail(string $rcpt, string $smail, string $subj, string $body, int $prio): bool {
        $ctx = ['transport' => 'sendmail', 'mail' => $this->getMaskedMail($rcpt)];
        if (!function_exists('proc_open')) return $this->setError('sendmail is unavailable, proc_open is disabled on this host', $ctx);
        $path = $this->filterHeader($this->conf['sendmail'] ?? '');
        if ($path === '' || !is_file($path) || !is_executable($path)) return $this->setError('the configured sendmail path is not an executable file', $ctx);
        $from = $this->getSender($smail);
        $args = [$path, '-t', '-i'];
        if ($from !== '') $args[] = '-f'.$from;
        $proc = proc_open($args, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipe);
        if (!is_resource($proc)) return $this->setError('the sendmail binary could not be started', $ctx);
        $mesg = $this->getHeaders($rcpt, $smail, $subj, $prio).self::CRLF.self::CRLF.$body;
        $warn = '';
        set_error_handler(static function (int $code, string $text) use (&$warn): bool {
            $warn = $text;
            return true;
        }, E_WARNING);
        $done = fwrite($pipe[0], $mesg) ?: 0;
        fclose($pipe[0]);
        restore_error_handler();
        stream_get_contents($pipe[1]);
        $note = stream_get_contents($pipe[2]);
        fclose($pipe[1]);
        fclose($pipe[2]);
        $stat = proc_close($proc);
        $note = ($note === false) ? '' : $this->filterHeader($note);
        if ($stat !== 0) return $this->setError('sendmail exited with status '.$stat.(($note !== '') ? ': '.$note : ''), $ctx);
        if ($done === strlen($mesg)) return true;
        $text = 'sendmail closed its input after '.$done.' of '.strlen($mesg).' octets';
        return $this->setError($text.(($warn !== '') ? ': '.$this->filterHeader($warn) : ''), $ctx);
    }
}
