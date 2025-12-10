<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

define('ADMIN_FILE', true);
$sgtime = microtime(true);
define('BASE_DIR', str_replace('\\', '/', __DIR__));
require_once BASE_DIR.'/admin/index.php';
