<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# Minimal SMTP sink for tests/Support/mail_probe.php: it accepts loopback connections, answers the dialogue and records what was delivered, so a drain can be verified end to end
# It is deliberately not a relay — nothing leaves this host, and the transcript it writes is what the drain tests read back
error_reporting(0);
ini_set('display_errors', '0');
$port = intval($argv[1] ?? 0);
$file = (string)($argv[2] ?? '');
$stop = time() + max(5, intval($argv[3] ?? 30));
$serv = stream_socket_server('tcp://127.0.0.1:'.$port, $ecod, $etxt);
if (!$serv || $file === '') exit(1);
$data = ['links' => 0, 'mails' => []];
file_put_contents($file, json_encode($data));
while (time() < $stop) {
    $link = stream_socket_accept($serv, 1);
    if (!$link) continue;
    $data['links']++;
    stream_set_timeout($link, 5);
    fwrite($link, "220 probe ESMTP\r\n");
    $mesg = '';
    $body = false;
    while (($line = fgets($link, 4096)) !== false) {
        if ($body) {
            if (rtrim($line, "\r\n") === '.') {
                $data['mails'][] = $mesg;
                $mesg = '';
                $body = false;
                fwrite($link, "250 2.0.0 Ok: queued\r\n");
                file_put_contents($file, json_encode($data));
                continue;
            }
            $mesg .= $line;
            continue;
        }
        $cmnd = strtoupper(substr(trim($line), 0, 4));
        if ($cmnd === 'EHLO') {
            fwrite($link, "250-probe\r\n250 SIZE 10240000\r\n");
        } elseif ($cmnd === 'HELO') {
            fwrite($link, "250 probe\r\n");
        } elseif ($cmnd === 'MAIL' || $cmnd === 'RCPT') {
            fwrite($link, "250 2.1.0 Ok\r\n");
        } elseif ($cmnd === 'DATA') {
            $body = true;
            fwrite($link, "354 End data with <CR><LF>.<CR><LF>\r\n");
        } elseif ($cmnd === 'QUIT') {
            fwrite($link, "221 2.0.0 Bye\r\n");
            break;
        } else {
            fwrite($link, "502 5.5.2 Unknown command\r\n");
        }
    }
    fclose($link);
    file_put_contents($file, json_encode($data));
}
fclose($serv);
file_put_contents($file, json_encode($data));
