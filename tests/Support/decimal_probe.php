<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the numbers of a language: _LOCALE is a constant, so each language takes a process of its own with its real language file first
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/lang/'.basename((string)($argv[1] ?? 'en')).'.php';
require_once BASE_DIR.'/core/system.php';
echo json_encode([
    'locale' => _LOCALE,
    'free' => filterDecimal(96.6),
    'whole' => filterDecimal(1234567),
    'fixed' => filterDecimal(1234567.891, 2),
    'zero' => filterDecimal(3, 1),
    'kilo' => filterSize(1536),
    'mega' => filterSize(1048576),
    'none' => filterSize(0),
], JSON_UNESCAPED_UNICODE);
