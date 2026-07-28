<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

return [
    'mail' => [
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
    ],
];
