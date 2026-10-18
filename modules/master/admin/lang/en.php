<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

define('_MASTER_RELEASES', 'Releases');
define('_MASTER_SITES', 'Sites');
define('_MASTER_NOTES', 'Notes');
define('_MASTER_KEYS', 'Keys');
define('_MASTER_INBOX', 'Waiting in inbox/');
define('_MASTER_ACCEPT', 'Accept');
define('_MASTER_ACCEPTED', 'Accepted');
define('_MASTER_CHANGED', 'Changed');
define('_MASTER_EXPIRES', 'Manifest expires');
define('_MASTER_EXPWARN', 'The manifest of %1$s expires on %2$s: re-sign it with php tools/sign.php --refresh %1$s, publish it and accept it again.');
define('_MASTER_WAIT', 'Waiting');
define('_MASTER_BYOWNER', 'set by the owner');
define('_MASTER_BYAUTO', 'automatic: %d failed, %d successful');
define('_MASTER_TOBETA', 'To beta');
define('_MASTER_TOSTABLE', 'To stable');
define('_MASTER_SHARE', 'Rollout share');
define('_MASTER_SHAREDESC', 'The share of sites offered release %s, from 0 to 100. A site falls into it by its identifier and the version, so each release has its own first sites.');
define('_MASTER_DONE', 'Accepted: %s');
define('_MASTER_REFUSED', 'Refused: %s');
define('_MASTER_RSIGN', 'the signature does not match the key in force');
define('_MASTER_REXP', 'the manifest has expired');
define('_MASTER_RZIP', 'an archive differs from its signed size or SHA-256');
define('_MASTER_RMISS', 'the files are missing');
define('_MASTER_RWRITE', 'the files could not be written');
define('_MASTER_RFORM', 'a manifest is incomplete or of another format, version or channel');
define('_MASTER_SID', 'Identifier');
define('_MASTER_DOMAIN', 'Domain');
define('_MASTER_NOHOST', 'not reported');
define('_MASTER_CHAN', 'Channel');
define('_MASTER_EXT', 'Extensions');
define('_MASTER_ERRS', 'PHP / DB errors');
define('_MASTER_FIRST', 'First check');
define('_MASTER_LAST', 'Last check');
define('_MASTER_SILENT', 'Silent for over %d days');
define('_MASTER_FAILS', 'Failed installs');
define('_MASTER_STEP', 'step %d');
define('_MASTER_STEPS', 'Step');
define('_MASTER_COUNT', 'Sites');
define('_MASTER_NOSOD', 'Without sodium');
define('_MASTER_NOZIP', 'Without zip');
define('_MASTER_LEVEL', 'Level');
define('_MASTER_TO', 'Addressee');
define('_MASTER_TOALL', 'all sites');
define('_MASTER_RANGE', 'Versions');
define('_MASTER_UNTIL', 'Until');
define('_MASTER_NOEND', 'no deadline');
define('_MASTER_PRIMARY', 'Primary key');
define('_MASTER_RECOVERY', 'Recovery key');
define('_MASTER_FACTORY', 'factory');
define('_MASTER_INFORCE', 'in force');
define('_MASTER_PRINT', 'Fingerprint');
define('_MASTER_KEYERR', 'A key statement in inbox/keys/ fails its check (%s); the keys in force are those before it.');
define('_MASTER_KEEPSITE', 'Keep silent sites, days');
define('_MASTER_KEEPSTAT', 'Keep daily totals, days');
define('_MASTER_LIMIT', 'Automatic pause threshold (share of failed installs in percent)');
define('_MASTER_JOBS', 'Saving also adds the scheduler jobs masterclean (daily totals and cleanup) and masterwatch (automatic pause, hourly) once.');
define('_MASTER', 'Master');
