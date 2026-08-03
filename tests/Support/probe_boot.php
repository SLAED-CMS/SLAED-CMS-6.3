<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# Shared boot for every CLI probe: brings the real core up the way index.php does, with the directories a probe writes to redirected into scratch
# A probe includes this, then requires core/system.php itself, because some probes have to prepare a session before the core reads it
# $probework is the scratch root the caller passed; everything below it belongs to that one probe run
if (!isset($probework)) $probework = '';
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
if (!defined('MODULE_FILE')) define('MODULE_FILE', true);
if (!defined('BASE_DIR')) define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__, 2)));
$probework = str_replace('\\', '/', (string)$probework);
if ($probework === '') $probework = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_probe';
if (!defined('COUNTER_DIR')) define('COUNTER_DIR', $probework);
if (!defined('LOGS_DIR')) define('LOGS_DIR', $probework.'/logs');
if (!is_dir(LOGS_DIR)) mkdir(LOGS_DIR, 0777, true);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) probe';
$_SERVER['HTTPS'] = 'on';
