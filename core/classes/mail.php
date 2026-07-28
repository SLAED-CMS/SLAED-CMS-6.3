<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Outgoing mail service: the queue every message is stored in, the drain that delivers it, sender identity, MIME header assembly, encoding, address validation and the error channel
class Mail {

    public const CHARSET = 'UTF-8';
    public const CRLF = "\r\n";
    private const ERRLEN = 255;
    private const MAXLINE = 100;
    private const MAXWAIT = 86400;
    private const KINDLEN = 20;
    private const FROMLEN = 100;
    private const MAILLEN = 255;
    private const SUBJLEN = 255;
    private const PRUNENUM = 1000;

    private ?Database $db;
    private array $conf;
    private string $site;
    private int $wait;
    private string $error = '';
    private bool $fback = false;
    private bool $ftry = false;
    private mixed $sock = null;
    private string $warn = '';
    private string $phase = '';
    private string $code = '';
    private string $repl = '';
    private string $mask = '';
    private string $lock = '';
    private array $refs = [];
    private int $rsec = 0;
    private int $rnum = 0;

    # Build the service from the site config; the database handle is what the queue is stored in and the lock window is read from the drain job, whose claims this object releases
    public function __construct(?Database $db, array $conf) {
        $this->db = $db;
        $this->conf = is_array($conf['mail'] ?? null) ? $conf['mail'] : [];
        $this->site = $conf['sitename'] ?? '';
        $job = $conf['scheduler']['jobs']['maildrain'] ?? [];
        $this->wait = max(60, intval(is_array($job) ? ($job['lock_timeout'] ?? 0) : 0) ?: 900);
    }

    # Close an open relay session when the service goes away, because a request or a drain run may end while the last message still holds the connection
    public function __destruct() {
        $this->deleteSmtpLink();
    }

    # Accept one message from a call site and store it as a queue row; the answer means accepted into the queue, and no caller learns the delivery outcome synchronously any more
    # The client block is appended here, inside the request that owns the visitor data, and never at send time where only the scheduler's own address would be available
    # Every value is bounded against the column that stores it, because under strict SQL mode an oversized write fails and would take the message with it
    public function addQueue(array $mesg): bool {
        $kind = substr($this->filterHeader($mesg['kind'] ?? ''), 0, self::KINDLEN);
        $rcpt = $this->filterAddress($mesg['email'] ?? '');
        if ($rcpt === '' || strlen($rcpt) > self::MAILLEN) return $this->setError('rejected recipient address', ['kind' => $kind]);
        $smail = $this->filterAddress($mesg['sender'] ?? '');
        if ($this->getSender($smail) === '' || strlen($smail) > self::FROMLEN) return $this->setError('rejected sender address', ['kind' => $kind]);
        $subj = $this->filterHeader($mesg['title'] ?? '');
        if (mb_strlen($subj) > self::SUBJLEN) return $this->setError('rejected subject, longer than '.self::SUBJLEN.' characters', ['kind' => $kind]);
        if (!$this->db instanceof Database) return $this->setError('the queue is unreachable, there is no database connection', ['kind' => $kind]);
        $ref = max(0, intval($mesg['ref'] ?? 0));
        $body = ($ref > 0) ? '' : (string)($mesg['body'] ?? '');
        if ($ref === 0 && !empty($mesg['client'])) $body .= $this->getClientBlock();
        $sql = 'INSERT INTO '.PREFIX_DB.'_mail (kind, sender, email, title, body, ref, prio, time, ntime) VALUES (:kind, :from, :mail, :subj, :body, :ref, :prio, NOW(), NOW())';
        $pars = ['kind' => $kind, 'from' => $smail, 'mail' => $rcpt, 'subj' => $subj, 'body' => $body, 'ref' => $ref, 'prio' => (intval($mesg['prio'] ?? 0) ?: 3)];
        if (!$this->db->getSqlQuery($sql, $pars)) return $this->setError('the message could not be stored in the queue', ['kind' => $kind, 'mail' => $this->getMaskedMail($rcpt)]);
        return true;
    }

    # Claim one batch of due rows under a fresh lock identifier and return them in send order; the claim is one conditional UPDATE, so two runs can never take the same row
    # Taking a row moves its next attempt behind the lock window, which keeps a claimed row out of every other claim and gives a dead run its rows back when the window passes
    # The predicate is exactly what the claim index leads with, so it stays one ordered range read: a marker column in it sends the optimizer to another index and adds a filesort
    # Claims of a dead run are cleared first, which only tidies the marker columns, because the window has already made those rows claimable again
    public function getBatch(int $num): array {
        if (!$this->db instanceof Database || $num < 1) return [];
        $this->deleteClaim();
        $this->lock = bin2hex(random_bytes(16));
        $sql = 'UPDATE '.PREFIX_DB.'_mail SET locked = NOW(), lockid = :lock, ntime = DATE_ADD(NOW(), INTERVAL :wait SECOND)'
            .' WHERE hold = 0 AND status = 0 AND ntime <= NOW() ORDER BY prio ASC, ntime ASC, id ASC LIMIT '.intval($num);
        if (!$this->db->getSqlQuery($sql, ['lock' => $this->lock, 'wait' => $this->wait]) || intval($this->db->getSqlAffected()) < 1) return [];
        $sql = 'SELECT id, kind, sender, email, title, body, ref, prio, tries FROM '.PREFIX_DB.'_mail WHERE lockid = :lock ORDER BY prio ASC, id ASC';
        return $this->db->getSqlRows($this->db->getSqlQuery($sql, ['lock' => $this->lock])) ?: [];
    }

    # Record what one attempt answered: an accepted row is done, a refused one waits behind a growing backoff until the attempt cap turns it into a failure the admin view keeps
    # A hard failure skips the retries, because a message whose shared body is gone would be refused the same way on every attempt
    # Every stored value is bounded here rather than by its column, since the write that records a failure must never be able to fail itself and leave the row claimed
    public function setResult(int $id, bool $sent, string $mesg = '', bool $hard = false): bool {
        if (!$this->db instanceof Database || $id < 1) return false;
        if ($sent) {
            $sql = 'UPDATE '.PREFIX_DB.'_mail SET status = 1, tries = tries + 1, locked = NULL, lockid = \'\', phase = \'\', code = \'\', error = \'\' WHERE id = :id';
            return (bool)$this->db->getSqlQuery($sql, ['id' => $id]);
        }
        $row = $this->db->getSqlRow($this->db->getSqlQuery('SELECT tries FROM '.PREFIX_DB.'_mail WHERE id = :id', ['id' => $id]));
        $try = intval($row['tries'] ?? 0) + 1;
        $cap = max(1, intval($this->conf['tries'] ?? 0) ?: 5);
        $back = max(1, intval($this->conf['backoff'] ?? 0) ?: 300);
        $sql = 'UPDATE '.PREFIX_DB.'_mail SET status = :stat, tries = tries + 1, ntime = DATE_ADD(NOW(), INTERVAL :wait SECOND), locked = NULL, lockid = \'\','
            .' phase = :phase, code = :code, error = :err WHERE id = :id';
        $pars = [
            'stat' => ($hard || $try >= $cap) ? 2 : 0,
            'wait' => min(self::MAXWAIT, $back * (2 ** min($try - 1, 10))),
            'phase' => mb_substr($this->phase, 0, 10),
            'code' => mb_substr($this->code, 0, 20),
            'err' => mb_substr($this->filterHeader($mesg), 0, self::ERRLEN),
            'id' => $id,
        ];
        return (bool)$this->db->getSqlQuery($sql, $pars);
    }

    # Drain the queue until nothing is due, the time budget is spent or the rate cap for the current minute is reached, then prune and report what the run did
    # The run is time-boxed rather than count-boxed: under pseudo-cron it executes inside a web request and has to end politely instead of being killed with a row in hand
    # One transport connection spans the whole run, which is what makes a drain one handshake rather than one per message
    public function updateQueue(): array {
        $out = ['sent' => 0, 'fail' => 0, 'kept' => 0, 'left' => 0, 'stop' => 'empty'];
        if (!$this->db instanceof Database) {
            $this->setError('the queue is unreachable, there is no database connection');
            $out['stop'] = 'database';
            return $out;
        }
        $cli = PHP_SAPI === 'cli';
        if ($cli && function_exists('set_time_limit')) set_time_limit(0);
        $tend = microtime(true) + $this->getBudget($cli);
        $num = (max(1, intval($this->conf['batch'] ?? 0) ?: 25)) * ($cli ? 10 : 1);
        $this->getRateWindow();
        while (true) {
            if (microtime(true) >= $tend) {
                $out['stop'] = 'time';
                break;
            }
            $rows = $this->getBatch($num);
            if (!$rows) break;
            $this->refs = [];
            foreach ($rows as $row) {
                if (microtime(true) >= $tend) {
                    $out['stop'] = 'time';
                    break 2;
                }
                if (!$this->checkRate()) {
                    $out['stop'] = 'rate';
                    break 2;
                }
                $this->rnum++;
                if ($this->setQueueSend($row)) $out['sent']++;
                else $out['fail']++;
            }
            $this->setRateWindow();
        }
        $this->deleteLock();
        $this->setRateWindow();
        $out['kept'] = $this->deleteQueue();
        $out['left'] = $this->getPending();
        return $out;
    }

    # Prune accepted rows past their retention window, per kind: one mailing is one row per account, so bulk history is kept far shorter than the transactional one
    # A failed row is never pruned here, because it is exactly what the admin view exists to show
    public function deleteQueue(): int {
        if (!$this->db instanceof Database) return 0;
        $keep = max(1, intval($this->conf['keep'] ?? 0) ?: 30);
        $bulk = max(1, intval($this->conf['keepbulk'] ?? 0) ?: 3);
        $num = 0;
        $sql = 'DELETE FROM '.PREFIX_DB.'_mail WHERE status = 1 AND kind = \'newsletter\' AND time < DATE_SUB(NOW(), INTERVAL :days DAY) LIMIT '.self::PRUNENUM;
        if ($this->db->getSqlQuery($sql, ['days' => $bulk])) $num += intval($this->db->getSqlAffected());
        $sql = 'DELETE FROM '.PREFIX_DB.'_mail WHERE status = 1 AND kind <> \'newsletter\' AND time < DATE_SUB(NOW(), INTERVAL :days DAY) LIMIT '.self::PRUNENUM;
        if ($this->db->getSqlQuery($sql, ['days' => $keep])) $num += intval($this->db->getSqlAffected());
        return $num;
    }

    # Deliver one claimed row and record what the transport answered, which is the only place a stored message turns into a delivered one
    private function setQueueSend(array $row): bool {
        $id = intval($row['id']);
        $body = (string)$row['body'];
        $ref = intval($row['ref']);
        if ($ref > 0) {
            $text = $this->getRefBody((string)$row['kind'], $ref);
            if ($text === null) {
                $this->setResult($id, false, 'the shared body this message refers to no longer exists', true);
                return false;
            }
            $body = $text;
        }
        $sent = $this->setDelivery((string)$row['email'], (string)$row['sender'], (string)$row['title'], $body, intval($row['prio']));
        $this->setResult($id, $sent, $sent ? '' : $this->error);
        return $sent;
    }

    # Resolve the shared body of a bulk message once per batch, because a mailing stores its text on its own row and its queue rows carry only the reference to it
    private function getRefBody(string $kind, int $ref): ?string {
        global $prs;
        if (isset($this->refs[$ref])) return $this->refs[$ref];
        if ($kind !== 'newsletter') return null;
        $row = $this->db->getSqlRow($this->db->getSqlQuery('SELECT body FROM '.PREFIX_DB.'_newsletter WHERE id = :id', ['id' => $ref]));
        if (!$row) return null;
        $body = (string)$row['body'];
        $this->refs[$ref] = ($prs instanceof Parser) ? $prs->filterContent($body, false, 'all') : $body;
        return $this->refs[$ref];
    }

    # Release the claims of a run that died before it recorded a result, so its rows become claimable again once the lock window of the drain job has passed
    private function deleteClaim(): void {
        $sql = 'UPDATE '.PREFIX_DB.'_mail SET locked = NULL, lockid = \'\' WHERE status = 0 AND locked IS NOT NULL AND locked < DATE_SUB(NOW(), INTERVAL :wait SECOND)';
        $this->db->getSqlQuery($sql, ['wait' => $this->wait]);
    }

    # Give the rows this run still holds back to the queue, which is what a run stopped by its time budget or by the rate cap leaves behind for the next one
    # The next attempt goes back to now, because a released row is due again immediately and must not sit out the lock window the claim moved it behind
    private function deleteLock(): void {
        if ($this->lock === '') return;
        $sql = 'UPDATE '.PREFIX_DB.'_mail SET locked = NULL, lockid = \'\', ntime = NOW() WHERE lockid = :lock AND status = 0';
        $this->db->getSqlQuery($sql, ['lock' => $this->lock]);
        $this->lock = '';
    }

    # Count what is still waiting, which is the number a drain reports and the one that says whether a run ended because the queue was empty
    private function getPending(): int {
        $row = $this->db->getSqlRow($this->db->getSqlQuery('SELECT COUNT(id) AS num FROM '.PREFIX_DB.'_mail WHERE status = 0 AND hold = 0'));
        return intval($row['num'] ?? 0);
    }

    # Size the run against the context it was started in: a CLI run may use the lock window of its job, a run inside a web request only part of what the request itself has
    private function getBudget(bool $cli): int {
        if ($cli) return max(60, $this->wait - 60);
        $max = intval(ini_get('max_execution_time'));
        return ($max > 0) ? max(10, intval($max * 0.6)) : 120;
    }

    # Read the rate window the runs share, so a cap of messages per minute holds across two consecutive runs and across a manual trigger instead of only inside one run
    private function getRateWindow(): void {
        $state = function_exists('getSchedulerState') ? getSchedulerState('maildrain') : [];
        $this->rsec = intval($state['rate_start'] ?? 0);
        $this->rnum = intval($state['rate_count'] ?? 0);
    }

    # Persist the window this run leaves behind, which is what the next run resumes instead of opening a window of its own and doubling the cap
    private function setRateWindow(): void {
        if (!function_exists('getSchedulerState') || !function_exists('setSchedulerState')) return;
        setSchedulerState('maildrain', array_replace(getSchedulerState('maildrain'), ['rate_start' => $this->rsec, 'rate_count' => $this->rnum]));
    }

    # Report whether one more message may leave inside the current minute, opening a fresh window when the last one has expired; a spent window stops the run rather than slowing it
    private function checkRate(): bool {
        $time = time();
        if ($time - $this->rsec >= 60) {
            $this->rsec = $time;
            $this->rnum = 0;
        }
        $cap = max(0, intval($this->conf['rate'] ?? 0));
        return $cap < 1 || $this->rnum < $cap;
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

    # Capture warnings instead of letting them print, because an unhandled warning inside a pseudo-cron request leaks into the response body a visitor receives
    private function setWarnCatch(): void {
        $this->warn = '';
        set_error_handler(function (int $code, string $text): bool {
            $this->warn = $text;
            return true;
        }, E_WARNING);
    }

    # Stop capturing and return the text of the last warning, stripped of control characters so nothing it carries can reshape a log line
    private function getWarnText(): string {
        restore_error_handler();
        return $this->filterHeader($this->warn);
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

    # Encode the transport-independent parts of one message and hand it to the configured transport, which is the only path out of the queue and is reachable from the drain alone
    # An unset or unknown transport delivers through PHP mail(), so an installation that upgrades and configures nothing keeps sending the way it did before
    # The phase and the code are cleared per message, so what a failed row records is what its own attempt answered and never what an earlier row left behind
    private function setDelivery(string $rcpt, string $smail, string $text, string $body, int $prio): bool {
        $this->phase = '';
        $this->code = '';
        $subj = $this->getSubject($text);
        $body = $this->getBody($body);
        return match ($this->conf['transport'] ?? '') {
            'sendmail' => $this->addSendMail($rcpt, $smail, $subj, $body, $prio),
            'smtp' => $this->addSmtpMail($rcpt, $smail, $subj, $body, $prio),
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
        $back = false;
        $this->setWarnCatch();
        $sent = ($args !== '') ? mail($rcpt, $subj, $body, $head, $args) : mail($rcpt, $subj, $body, $head);
        if (!$sent && $args !== '' && !$this->ftry) {
            $this->ftry = true;
            $this->warn = '';
            $sent = mail($rcpt, $subj, $body, $head);
            $back = $sent;
        }
        $warn = $this->getWarnText();
        if ($back) {
            $this->fback = true;
            Logger::addSite('warning', 'Mail: the host refused the envelope sender, delivered without it', ['transport' => 'php']);
        }
        if ($sent) return true;
        $mesg = 'php mail() refused the message'.(($warn !== '') ? ': '.$warn : '');
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
        $this->setWarnCatch();
        $done = fwrite($pipe[0], $mesg) ?: 0;
        fclose($pipe[0]);
        $warn = $this->getWarnText();
        stream_get_contents($pipe[1]);
        $note = stream_get_contents($pipe[2]);
        fclose($pipe[1]);
        fclose($pipe[2]);
        $stat = proc_close($proc);
        $note = ($note === false) ? '' : $this->filterHeader($note);
        if ($stat !== 0) return $this->setError('sendmail exited with status '.$stat.(($note !== '') ? ': '.$note : ''), $ctx);
        if ($done === strlen($mesg)) return true;
        $text = 'sendmail closed its input after '.$done.' of '.strlen($mesg).' octets';
        return $this->setError($text.(($warn !== '') ? ': '.$warn : ''), $ctx);
    }

    # Deliver over a raw SMTP dialogue, the only transport that reaches a relay this installation chooses rather than the one the host happens to provide
    # The connection is opened once and kept, so a drain run spends one handshake on a whole batch instead of one per message
    # The recipient and the resolved sender have both passed the address sanitiser, so neither the envelope nor the header block can carry an injected line
    private function addSmtpMail(string $rcpt, string $smail, string $subj, string $body, int $prio): bool {
        $this->mask = $this->getMaskedMail($rcpt);
        if (!$this->checkSmtpLink()) return false;
        if (!$this->checkSmtpStep('MAIL FROM:<'.$this->getSender($smail).'>', '2', 'from')) return false;
        if (!$this->checkSmtpStep('RCPT TO:<'.$rcpt.'>', '2', 'rcpt')) return false;
        if (!$this->checkSmtpStep('DATA', '354', 'data')) return false;
        $mesg = $this->getHeaders($rcpt, $smail, $subj, $prio).self::CRLF.self::CRLF.$body;
        if (!$this->setSmtpText($this->getSmtpData($mesg))) return $this->setSmtpFail('the connection was closed while the message was being written');
        return $this->checkSmtpStep('', '2', 'data');
    }

    # Keep the connection this object already holds and open a new one only when there is none or the relay dropped the one there was
    # Encryption is refused rather than silently skipped when openssl is missing, because a transport that quietly sends in clear is worse than one that says it cannot
    private function checkSmtpLink(): bool {
        if (is_resource($this->sock) && !feof($this->sock)) return true;
        $this->deleteSmtpLink();
        $this->phase = 'connect';
        $this->code = '';
        $host = $this->filterHeader($this->conf['host'] ?? '');
        if ($host === '') return $this->setSmtpFail('no smtp host is configured');
        $encr = $this->conf['secure'] ?? 'none';
        if ($encr !== 'none' && !function_exists('stream_socket_enable_crypto')) return $this->setSmtpFail('smtp encryption is unavailable, openssl is missing on this host');
        $port = (int)($this->conf['port'] ?? 0) ?: 25;
        $tout = (int)($this->conf['timeout'] ?? 0) ?: 10;
        $opts = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host, 'SNI_enabled' => true]]);
        $this->setWarnCatch();
        $sock = stream_socket_client((($encr === 'ssl') ? 'ssl://' : 'tcp://').$host.':'.$port, $ecod, $etxt, $tout, STREAM_CLIENT_CONNECT, $opts);
        $warn = $this->getWarnText();
        if (!is_resource($sock)) return $this->setSmtpFail('the relay could not be reached: '.($this->filterHeader($etxt ?? '') ?: $warn));
        $this->sock = $sock;
        stream_set_timeout($sock, $tout);
        return $this->checkSmtpGreet();
    }

    # Run the opening dialogue on a fresh connection: the greeting, then EHLO with a HELO fallback for a relay that speaks no ESMTP
    # STARTTLS follows when it is configured, and authentication runs last so it is offered only over the encryption the installation asked for
    # The second EHLO is not optional: the capability list before STARTTLS is not the one that counts, and the mechanisms AUTH may use are read from the encrypted one
    private function checkSmtpGreet(): bool {
        if (!$this->checkSmtpStep('', '2', 'connect')) return false;
        $name = $this->getSmtpName();
        if (!$this->isSmtpAnswer('EHLO '.$name, '2', 'ehlo') && !$this->checkSmtpStep('HELO '.$name, '2', 'ehlo')) return false;
        if (($this->conf['secure'] ?? 'none') === 'tls') {
            if (!$this->checkSmtpStep('STARTTLS', '2', 'tls')) return false;
            if (!$this->setSmtpCrypto()) return false;
            if (!$this->isSmtpAnswer('EHLO '.$name, '2', 'ehlo') && !$this->checkSmtpStep('HELO '.$name, '2', 'ehlo')) return false;
        }
        return empty($this->conf['auth']) || $this->checkSmtpAuth();
    }

    # Raise TLS on the open socket with peer and peer-name verification left on, because the option to disable it exists mainly to make a broken configuration look like it works
    private function setSmtpCrypto(): bool {
        $this->phase = 'tls';
        $this->setWarnCatch();
        $done = stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $warn = $this->getWarnText();
        if ($done === true) return true;
        return $this->setSmtpFail('the tls handshake failed'.(($warn !== '') ? ': '.$warn : ''));
    }

    # Authenticate with a mechanism the relay actually advertised; both credentials travel base64 encoded, so nothing about them can reach the wire as protocol text
    # Neither the password nor the encoded payload is ever passed to a failure message, which is what keeps them out of the log at every level
    private function checkSmtpAuth(): bool {
        $this->phase = 'auth';
        $user = $this->filterHeader($this->conf['user'] ?? '');
        $pass = $this->filterHeader($this->conf['pass'] ?? '');
        if ($user === '' || $pass === '') return $this->setSmtpFail('smtp authentication is configured without a user or a password');
        $mech = $this->getSmtpMech();
        if ($mech === 'PLAIN') return $this->checkSmtpStep('AUTH PLAIN '.base64_encode("\0".$user."\0".$pass), '2', 'auth');
        if ($mech !== 'LOGIN') return $this->setSmtpFail('the relay advertises no supported authentication mechanism');
        if (!$this->checkSmtpStep('AUTH LOGIN', '334', 'auth')) return false;
        if (!$this->checkSmtpStep(base64_encode($user), '334', 'auth')) return false;
        return $this->checkSmtpStep(base64_encode($pass), '2', 'auth');
    }

    # Pick an authentication mechanism out of the capability list the last EHLO returned, preferring the single-command form; a relay that advertises neither gets no guess
    # The match stops at the next reply code, because the lines of a multi-line answer are read into one string and the capability after AUTH is not part of its argument list
    private function getSmtpMech(): string {
        if (!preg_match('/AUTH[ =]([A-Za-z0-9 \-]+?)(?= \d\d\d[ -]|$)/i', $this->repl, $mtch)) return '';
        $list = array_map('strtoupper', explode(' ', $mtch[1]));
        if (in_array('PLAIN', $list, true)) return 'PLAIN';
        return in_array('LOGIN', $list, true) ? 'LOGIN' : '';
    }

    # Name this host in EHLO, taking the system name when it looks like one, because a relay may refuse a greeting carrying anything else
    private function getSmtpName(): string {
        $name = gethostname() ?: '';
        return preg_match('/^[A-Za-z0-9.\-]+$/', $name) ? $name : 'localhost';
    }

    # Send one command when there is one, read the answer and report whether it carries the expected reply code, without deciding what a mismatch means
    private function isSmtpAnswer(string $cmnd, string $want, string $step): bool {
        $this->phase = $step;
        if ($cmnd !== '' && !$this->setSmtpLine($cmnd)) {
            $this->code = '';
            $this->repl = 'the connection was closed before the command was written';
            return false;
        }
        $this->getSmtpReply();
        return str_starts_with($this->code, $want);
    }

    # Require the expected reply code and abort with the relay's own text when it answers anything else, so no unexpected code is ever read as an acceptance
    private function checkSmtpStep(string $cmnd, string $want, string $step): bool {
        if ($this->isSmtpAnswer($cmnd, $want, $step)) return true;
        return $this->setSmtpFail(($this->code === '') ? $this->repl.' at '.$step : 'the relay refused at '.$step.': '.$this->repl);
    }

    # Read one reply, following continuation lines until the final one, because a reply read a line at a time desynchronises the whole dialogue
    # Continuation is bounded: a relay that never sends its final line would otherwise hold the run for as many reads as it cares to answer
    private function getSmtpReply(): string {
        $this->repl = '';
        $this->code = '';
        if (!is_resource($this->sock)) {
            $this->repl = 'the connection was closed';
            return $this->repl;
        }
        for ($i = 0; $i < self::MAXLINE; $i++) {
            $line = fgets($this->sock, 1024);
            if ($line === false) {
                $meta = stream_get_meta_data($this->sock);
                $this->repl = empty($meta['timed_out']) ? 'the relay closed the connection' : 'the relay did not answer within the timeout';
                return $this->repl;
            }
            $line = $this->filterHeader($line);
            $this->code = substr($line, 0, 3);
            $this->repl = ($this->repl !== '') ? $this->repl.' '.$line : $line;
            if (strlen($line) < 4 || $line[3] !== '-') return $this->repl;
        }
        $this->code = '';
        $this->repl = 'the relay sent more continuation lines than one reply may carry';
        return $this->repl;
    }

    # Prepare the message for the DATA phase: every line ends with CRLF on the wire, whatever the stored body happens to use
    # A leading dot is doubled so no line of the message can end it early, which leaves the terminating sequence as the only bare dot on the wire
    private function getSmtpData(string $mesg): string {
        $mesg = str_replace("\n", self::CRLF, str_replace(["\r\n", "\r"], "\n", $mesg));
        if (str_starts_with($mesg, '.')) $mesg = '.'.$mesg;
        $mesg = str_replace(self::CRLF.'.', self::CRLF.'..', $mesg);
        if (!str_ends_with($mesg, self::CRLF)) $mesg .= self::CRLF;
        return $mesg.'.'.self::CRLF;
    }

    # Write one command line, which is the only form a command may take: a line that lost its terminator would be read as part of the next one
    private function setSmtpLine(string $line): bool {
        return $this->setSmtpText($line.self::CRLF);
    }

    # Write one block and confirm every octet left, because a half-written command desynchronises the dialogue and a closed socket must never be read as a delivery
    private function setSmtpText(string $text): bool {
        if (!is_resource($this->sock)) return false;
        $this->setWarnCatch();
        $done = fwrite($this->sock, $text) ?: 0;
        $this->getWarnText();
        return $done === strlen($text);
    }

    # Record one failure with the phase and the reply code that produced it, because a bare code cannot be interpreted later and the phase is what says whose fault it was
    # The connection is dropped with it: a dialogue that failed mid-step cannot be trusted to be in a known state, and this is the one path every failure passes through
    private function setSmtpFail(string $mesg): bool {
        $ctx = ['transport' => 'smtp', 'phase' => $this->phase, 'code' => $this->code, 'mail' => $this->mask];
        $this->deleteSmtpLink();
        return $this->setError($mesg, $ctx);
    }

    # Close the session, saying QUIT while the socket is still usable, so a relay is not left holding a connection on any exit path
    private function deleteSmtpLink(): void {
        if (is_resource($this->sock)) {
            $this->setSmtpLine('QUIT');
            fclose($this->sock);
        }
        $this->sock = null;
    }
}
