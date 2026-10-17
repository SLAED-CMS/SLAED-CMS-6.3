<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The entry of the panel in the document root: it names the project and its own folder, then runs admin/index.php; setup and the security section rename this file
# A test or a tool may name both folders first; while the code waits for its database steps the gate sends the panel into its light mode
# While an installation lies the gate names the rescuer or the executor, required here at the top level, so the new core it boots holds its globals
if (!defined('BASE_DIR')) define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__)));
if (!defined('PUBLIC_DIR')) define('PUBLIC_DIR', str_replace('\\', '/', __DIR__));
define('ADMIN_FILE', true);
$sgtime = microtime(true);
require_once BASE_DIR.'/core/gate.php';
if (($sgfile = getSiteGate()) !== '') require $sgfile;
if (checkSiteGate(true)) define('UPDATE_FILE', true);
require_once BASE_DIR.'/admin/index.php';
