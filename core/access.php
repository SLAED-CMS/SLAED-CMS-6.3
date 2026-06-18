<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# Optional drop-in guard for third-party admin tools: a plugin defines ADMIN_FILE
# (and BASE_DIR or $path), includes this file, and execution continues only when the
# admin IP allowlist and HTTP basic auth from config/security.php are satisfied. All
# checks reuse the canonical core guard (checkAccess) instead of duplicating it.
if (!defined('ADMIN_FILE')) die('Illegal file access');
if (!defined('BASE_DIR')) define('BASE_DIR', realpath($GLOBALS['path'] ?? __DIR__.'/..') ?: __DIR__.'/..');
require_once BASE_DIR.'/core/system.php';
getLang('admin');
checkAccess();
