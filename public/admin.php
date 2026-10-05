<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The entry of the panel in the document root: it names the project and its own folder, then runs admin/index.php; setup and the security section rename this file
define('ADMIN_FILE', true);
$sgtime = microtime(true);
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__)));
define('PUBLIC_DIR', str_replace('\\', '/', __DIR__));
require_once BASE_DIR.'/admin/index.php';
