<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The scheduler jobs of the master, the one place they are declared: the core reads them from here and runs them while the module is active
return [
    # masterclean writes the daily totals and drops the sites and totals past their terms; masterwatch pauses a release whose installations fail
    'masterclean' => ['title' => 'Master totals', 'schedule' => '20 4 * * *', 'priority' => '20', 'lock_timeout' => '600', 'run' => static function (): array {
        require_once __DIR__.'/common.php';
        return addMasterClean();
    }],
    'masterwatch' => ['title' => 'Master autopause', 'schedule' => '7 * * * *', 'priority' => '21', 'lock_timeout' => '300', 'run' => static function (): array {
        require_once __DIR__.'/common.php';
        return addMasterWatch();
    }],
];
