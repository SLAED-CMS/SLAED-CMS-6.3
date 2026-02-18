<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net
#
# local.php — site-specific overrides only.
# Keys not present in the default config are ignored (see filterConfigMerge).
# base_fingerprint is updated automatically when dev_mode = true.
# Do not add logic, guards, or variable assignments here.

return [
    '_meta' => [
        'base_fingerprint' => '',
    ],

    # Example: enable dev mode on this server only
    # 'dev_mode' => true,

    # Example: override DB credentials for this environment
    # 'db' => [
    #     'host' => 'localhost',
    #     'name' => 'mydb',
    #     'uname' => 'myuser',
    #     'pass'  => 'secret',
    # ],
];
