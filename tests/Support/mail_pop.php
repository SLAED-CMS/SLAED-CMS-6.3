<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# Minimal POP3 mailbox for tests/Support/mail_probe.php: it serves the messages it was given over loopback and records which of them a session deleted
# Deletions take effect only on QUIT, as in a real server, and the drop mode closes the connection in the middle of the first message to stand for a broken session
error_reporting(0);
ini_set('display_errors', '0');
$port = intval($argv[1] ?? 0);
$file = (string)($argv[2] ?? '');
$stop = time() + max(5, intval($argv[3] ?? 30));
$data = json_decode((string)file_get_contents($file), true);
$serv = stream_socket_server('tcp://127.0.0.1:'.$port, $ecod, $etxt);
if (!$serv || !is_array($data)) exit(1);
$mesg = array_values($data['mesg'] ?? []);
$data += ['links' => 0, 'deleted' => [], 'login' => false];
file_put_contents($file, json_encode($data));
while (time() < $stop) {
    $link = stream_socket_accept($serv, 1);
    if (!$link) continue;
    $data['links']++;
    stream_set_timeout($link, 5);
    fwrite($link, "+OK probe POP3 ready\r\n");
    $user = '';
    $mark = [];
    while (($line = fgets($link, 4096)) !== false) {
        $line = rtrim($line, "\r\n");
        $cmnd = strtoupper(strtok($line, ' '));
        $arg = trim(substr($line, strlen($cmnd)));
        if ($cmnd === 'USER') {
            $user = $arg;
            fwrite($link, "+OK\r\n");
        } elseif ($cmnd === 'PASS') {
            $data['login'] = $user === 'bounces' && $arg === (string)($data['pass'] ?? '');
            fwrite($link, $data['login'] ? "+OK logged in\r\n" : "-ERR authentication failed\r\n");
        } elseif (!$data['login']) {
            fwrite($link, "-ERR not logged in\r\n");
        } elseif ($cmnd === 'STAT') {
            fwrite($link, '+OK '.count($mesg).' '.strlen(implode('', $mesg))."\r\n");
        } elseif ($cmnd === 'RETR' && isset($mesg[intval($arg) - 1])) {
            $body = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $mesg[intval($arg) - 1]));
            $body = preg_replace('/^\./m', '..', $body);
            if (($data['mode'] ?? '') === 'drop') {
                fwrite($link, "+OK message follows\r\n".substr($body, 0, 40));
                break;
            }
            fwrite($link, "+OK message follows\r\n".rtrim($body, "\r\n")."\r\n.\r\n");
        } elseif ($cmnd === 'DELE' && isset($mesg[intval($arg) - 1])) {
            $mark[] = intval($arg);
            fwrite($link, "+OK marked\r\n");
        } elseif ($cmnd === 'QUIT') {
            $data['deleted'] = array_merge($data['deleted'], $mark);
            fwrite($link, "+OK bye\r\n");
            break;
        } else {
            fwrite($link, "-ERR unknown\r\n");
        }
    }
    fclose($link);
    file_put_contents($file, json_encode($data));
}
fclose($serv);
file_put_contents($file, json_encode($data));
