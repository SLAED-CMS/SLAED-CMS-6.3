<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# Optional drop-in guard for third-party admin tools defining ADMIN_FILE (and BASE_DIR or $path): checkAccess() enforces the IP allowlist and basic auth of config/security.php
if (!defined('ADMIN_FILE')) die('Illegal file access');
if (!defined('BASE_DIR')) define('BASE_DIR', realpath($GLOBALS['path'] ?? __DIR__.'/..') ?: __DIR__.'/..');
require_once BASE_DIR.'/core/system.php';
getLang('admin');
checkAccess();
