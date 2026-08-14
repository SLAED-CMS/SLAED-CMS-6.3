<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE') && !defined('ADMIN_FILE')) die('Illegal file access');

define('BLOCK_FILE', true);
define('FUNC_FILE', true);

# Sentinel baked into cached HTML and replaced with live generation timing right before output
define('GEN_MARK', "\x02SLGEN\x02");
define('DBG_MARK', "\x02SLDBG\x02");

# Frozen salt for verifying legacy md5 password hashes; never change it or stored legacy hashes stop matching
define('PASS_SALT', 'IFNMQUVELiBBbGwgcmlnaHRzIHJlc2VydmVkLg==');

# Configuration directory
define('CONFIG_DIR', BASE_DIR.'/config');

# Storage directories for internal data
define('BACKUP_DIR', BASE_DIR.'/storage/backup');
define('CACHE_DIR', BASE_DIR.'/storage/cache');
define('COUNTER_DIR', BASE_DIR.'/storage/counter');
define('LOGS_DIR', BASE_DIR.'/storage/logs');
define('SITEMAP_DIR', BASE_DIR.'/storage/sitemap');
define('CAPTCHA_DIR', BASE_DIR.'/storage/captcha');

# Uploads directory for user content
define('UPLOADS_DIR', BASE_DIR.'/uploads');

# Asset bundle version; bump on every release that ships changed CSS/JS/fonts so
# cached immutable bundles are invalidated even when deployment preserves mtimes
# Bump it for a change of the builder as well: the key covers the sources and the settings, not the code that joins them, so a stored bundle would outlive the rule that produced it
define('ASSETS_VER', 3);

# Load the runtime config from cache, rebuilding it from source if needed; the rebuild also stores derived data (asset manifests per theme, parsed SEO graph/schema, logo sizes) under $conf['derived'], so theme asset or logo changes need a config rebuild (admin save or deleting config/local.php) to take effect
function getConfig(): array {
    $local_file = CONFIG_DIR.'/local.php';
    if (is_file($local_file) && is_readable($local_file)) {
        $cache = require $local_file;
        $valid = is_array($cache) && isset($cache['_meta'], $cache['_config']) && is_array($cache['_meta']) && is_array($cache['_config']);
        if ($valid && (($cache['_meta']['cache_version'] ?? 0) === 4)) {
            return $cache['_config'];
        }
    }
    $files = glob(CONFIG_DIR.'/*.php') ?: [];
    sort($files);
    $skip = ['local.php', 'system.php', 'header.php', 'chmod.php'];
    $hash = '';
    $conf = [];
    foreach ($files as $file) {
        $name = basename($file);
        if (in_array($name, $skip, true)) continue;
        $data = require $file;
        if (is_array($data)) $conf = array_merge($conf, $data);
        $file_hash = sha1_file($file);
        if ($file_hash !== false) $hash .= $name.$file_hash;
    }
    $conf['dev_mode'] ??= false;
    unset($conf['style']);
    $stat = static function(array $files): string {
        $map = [];
        foreach ($files as $file) $map[$file] = is_file($file) ? filemtime($file).':'.filesize($file) : '0:0';
        return sha1(serialize($map));
    };
    $conf['derived'] = [];
    foreach (glob('templates/*', GLOB_ONLYDIR) ?: [] as $tdir) {
        $tname = basename($tdir);
        $centr = explode(',', str_replace('[theme]', $tname, (string)($conf['css_f'] ?? '')));
        $sentr = explode(',', (string)($conf['script_f'] ?? ''));
        $clist = array_values(array_unique(array_merge(getAssetFiles($centr, 'css'), getThemeAssets($tname, 'css'))));
        $slist = array_values(array_unique(array_merge(getAssetFiles($sentr, 'js'), getThemeAssets($tname, 'js'))));
        $conf['derived']['assets'][$tname] = ['css' => $clist, 'cssfp' => $stat($clist), 'js' => $slist, 'jsfp' => $stat($slist)];
        $conf['derived']['logo'][$tname] = getImageBox($tdir.'/images/logos/'.($conf['site_logo'] ?? ''));
    }
    if (!empty($conf['graph'])) $conf['derived']['graph'] = getSeoGraph((string)$conf['graph'], []);
    if (!empty($conf['schema'])) {
        try {
            $conf['derived']['schema'] = getSeoJsonItems((string)$conf['schema']);
        } catch (Throwable) {
        }
    }
    $export = function (array $arr, int $dep = 0) use (&$export): string {
        $pad = str_repeat('    ', $dep);
        $ind = $pad.'    ';
        $out = '['."\n";
        foreach ($arr as $key => $val) {
            $out .= $ind.var_export($key, true).' => ';
            $out .= is_array($val) ? $export($val, $dep + 1) : var_export($val, true);
            $out .= ','."\n";
        }
        return $out.$pad.']';
    };
    $data = [
        '_meta' => [
            'base_fingerprint' => sha1($hash),
            'cache_version' => 4,
            'generated_at' => time(),
        ],
        '_config' => $conf,
    ];
    $tmp = $local_file.'.tmp';
    $is_new = !file_exists($local_file);
    $cnt = '<?php'."\n"
    .'# Author: Eduard Laas'."\n"
    .'# 2005 - '.date('Y').' SLAED'."\n"
    .'# License: MIT'."\n"
    .'# Website: slaed.net'."\n\n"
    .'return '.$export($data).';'."\n";
    if (file_put_contents($tmp, $cnt, LOCK_EX) !== false) {
        if (!rename($tmp, $local_file)) {
            unlink($tmp);
        } elseif ($is_new) {
            chmod($local_file, 0640);
        }
    }
    return $conf;
}

# Editor bootstrap must load before security POST processing, because security helpers may
# consult editor-aware functions during request analysis
require_once BASE_DIR.'/core/classes/editor.php';
require_once BASE_DIR.'/core/classes/logger.php';

# Load the generated runtime config cache
$conf = getConfig();
if (defined('ADMIN_FILE')) $conf['theme'] = 'admin';

# Outgoing mail service; the class loads here and is instantiated inside core/security.php, where the database connection is created
require_once BASE_DIR.'/core/classes/mail.php';

# System file include
require_once BASE_DIR.'/core/security.php';

$theme = getTheme();
if (is_file(BASE_DIR.'/templates/'.$theme.'/index.php')) require_once BASE_DIR.'/templates/'.$theme.'/index.php';
require_once BASE_DIR.'/core/classes/template.php';
require_once BASE_DIR.'/core/classes/parser.php';
require_once BASE_DIR.'/core/classes/geoip.php';
require_once BASE_DIR.'/core/classes/captcha.php';
require_once BASE_DIR.'/core/classes/cache.php';
require_once BASE_DIR.'/core/classes/oauth.php';
require_once BASE_DIR.'/core/classes/comment.php';
require_once BASE_DIR.'/core/classes/privat.php';
$tpl = new Template($theme);
$prs = new Parser();
$com = new Comment($db, $prs, $conf);
$prv = new Privat($db, $conf);

# Helpers include
require_once BASE_DIR.'/core/helpers.php';

if ($conf['db']['sync']) $db->getSqlQuery("SET LOCAL time_zone = '".date('P')."'");

if (defined('MODULE_FILE')) {
    require_once BASE_DIR.'/core/user.php';
} elseif (defined('ADMIN_FILE')) {
    require_once BASE_DIR.'/core/admin.php';
}

# Call an optional theme hook and return only array payloads.
function getThemeHookVars(string $hook): array {
    if (!function_exists($hook)) return [];
    $vars = $hook();
    return is_array($vars) ? $vars : [];
}

# Returns a normalized 5-part cron schedule or an empty string when invalid
function getSchedulerSchedule(array|string $job): string {
    $schedule = is_array($job) ? (string)($job['schedule'] ?? '') : (string)$job;
    $schedule = trim(preg_replace('#\s+#', ' ', $schedule));
    if ($schedule === '') return '';
    $parts = explode(' ', $schedule);
    if (count($parts) !== 5) return '';
    [$min, $hour, $mday, $mon, $wday] = $parts;
    if (!checkSchedulerCronField($min, 0, 59)) return '';
    if (!checkSchedulerCronField($hour, 0, 23)) return '';
    if (!checkSchedulerCronField($mday, 1, 31)) return '';
    if (!checkSchedulerCronField($mon, 1, 12)) return '';
    if (!checkSchedulerCronField($wday, 0, 7)) return '';
    return implode(' ', [$min, $hour, $mday, $mon, $wday]);
}

# Returns whether a cron field contains only supported segments in range
function checkSchedulerCronField(string $field, int $min, int $max): bool {
    if ($field === '') return false;
    foreach (explode(',', $field) as $part) {
        $part = trim($part);
        if ($part === '') return false;
        $step = 1;
        if (str_contains($part, '/')) {
            [$part, $steps] = explode('/', $part, 2);
            if (!ctype_digit($steps) || (int)$steps < 1) return false;
            $step = (int)$steps;
        }
        if ($part === '*') continue;
        if (str_contains($part, '-')) {
            [$from, $to] = explode('-', $part, 2);
            if (!ctype_digit($from) || !ctype_digit($to)) return false;
            $from = (int)$from;
            $to = (int)$to;
            if ($from < $min || $to > $max || $from > $to || $step < 1) return false;
            continue;
        }
        if (!ctype_digit($part)) return false;
        $num = (int)$part;
        if ($num < $min || $num > $max) return false;
    }
    return true;
}

# Returns whether a value matches a cron field
function checkSchedulerCronValue(string $field, int $value, int $min, int $max): bool {
    foreach (explode(',', $field) as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $step = 1;
        if (str_contains($part, '/')) {
            [$part, $steps] = explode('/', $part, 2);
            $step = max(1, (int)$steps);
        }
        if ($part === '*') {
            if ((($value - $min) % $step) === 0) return true;
            continue;
        }
        if (str_contains($part, '-')) {
            [$from, $to] = explode('-', $part, 2);
            $from = (int)$from;
            $to = (int)$to;
            if ($value >= $from && $value <= $to && (($value - $from) % $step) === 0) return true;
            continue;
        }
        if ((int)$part === $value) return true;
    }
    return false;
}

# Returns whether a unix timestamp matches an already normalized scheduler cron expression from getSchedulerSchedule
function checkSchedulerCronMatch(string $schedule, int $time): bool {
    $parts = explode(' ', $schedule);
    if (count($parts) !== 5) return false;
    [$min, $hour, $mday, $mon, $wday] = $parts;
    $mins = (int)date('i', $time);
    $hourn = (int)date('G', $time);
    $mdayn = (int)date('j', $time);
    $monn = (int)date('n', $time);
    $wdayn = (int)date('w', $time);
    if (!checkSchedulerCronValue($min, $mins, 0, 59)) return false;
    if (!checkSchedulerCronValue($hour, $hourn, 0, 23)) return false;
    $domany = ($mday === '*');
    $dowany = ($wday === '*');
    $domok = checkSchedulerCronValue($mday, $mdayn, 1, 31);
    $dowok = checkSchedulerCronValue($wday, $wdayn, 0, 7) || ($wdayn === 0 && checkSchedulerCronValue($wday, 7, 0, 7));
    if (!checkSchedulerCronValue($mon, $monn, 1, 12)) return false;
    if ($domany && $dowany) return true;
    if ($domany) return $dowok;
    if ($dowany) return $domok;
    if (!$domok && !$dowok) return false;
    return true;
}

# Returns the current planned run timestamp for a scheduler job based on last execution or current time
function getSchedulerPlannedTime(array $job, array $state = []): int {
    $schedule = getSchedulerSchedule($job);
    if ($schedule === '') return 0;
    $last = (int)($state['last_run'] ?? 0);
    $next = ($last > 0 ? $last : time());
    $next = $next - ($next % 60) + 60;
    $max = $next + (60 * 60 * 24 * 366 * 5);
    while ($next <= $max) {
        if (checkSchedulerCronMatch($schedule, $next)) return $next;
        $next += 60;
    }
    return 0;
}

# Reads a job by key from config when $job is null, otherwise normalizes the given array; enforces canonical type/system for built-in jobs and drops legacy keys so stale configs self-heal
function getSchedulerJob(string $name, ?array $job = null): array {
    global $conf;
    static $map = ['dbbackup' => 'backup', 'filescan' => 'filescan', 'maildrain' => 'maildrain', 'newsletter' => 'newsletter', 'sitemap' => 'sitemap', 'cachegc' => 'cachegc'];
    $read = $job === null;
    if ($read) $job = $conf['scheduler']['jobs'][$name] ?? [];
    if (!is_array($job)) $job = [];
    unset($job['handler']);
    if (isset($map[$name])) {
        $job['type'] = 'system';
        $job['system'] = $map[$name];
    }
    if ($read) $job += ['name' => $name];
    return $job;
}

# Returns all scheduler jobs normalized and sorted by priority and key
function getSchedulerJobs(): array {
    global $conf;
    $arr = [];
    foreach ($conf['scheduler']['jobs'] ?? [] as $key => $val) {
        if (!is_string($key) || $key === '' || !is_array($val)) continue;
        $arr[$key] = getSchedulerJob($key);
    }
    uasort($arr, static function (array $aaa, array $bbb): int {
        $one = (int)($aaa['priority'] ?? 100);
        $two = (int)($bbb['priority'] ?? 100);
        if ($one === $two) return strcmp((string)($aaa['name'] ?? ''), (string)($bbb['name'] ?? ''));
        return $one <=> $two;
    });
    return $arr;
}

# Returns the fields every job state carries; anything else in a state file belongs to the last run of that job and is replaced by it
function getSchedulerFields(): array {
    return ['running' => 0, 'started_at' => 0, 'last_run' => 0, 'last_success' => 0, 'last_status' => 'idle', 'last_message' => '', 'last_error' => '',
        'last_duration' => 0, 'last_trigger' => '', 'fail_count' => 0, 'next_run' => 0, 'next_schedule' => '', 'next_last_run' => 0];
}

# Returns the runtime state for a scheduler job merged with defaults
function getSchedulerState(string $name): array {
    $file = LOGS_DIR.'/scheduler/'.$name.'.json';
    $state = getSchedulerFields();
    if (!is_file($file) || filesize($file) === 0) return $state;
    $json = file_get_contents($file);
    if ($json === false || $json === '') return $state;
    $data = json_decode($json, true);
    if (!is_array($data)) return $state;
    return array_replace($state, $data);
}

# Writes the runtime state for a scheduler job atomically
function setSchedulerState(string $name, array $state): bool {
    $file = LOGS_DIR.'/scheduler/'.$name.'.json';
    $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) return false;
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) return false;
    return file_put_contents($file, $json, LOCK_EX) !== false;
}

# Returns the path of the operating system lock file of one job; the file is created once and never deleted, because deleting it would break the lock for a process still holding it
function getSchedulerLockPath(string $name): string {
    return LOGS_DIR.'/scheduler/'.$name.'.lock';
}

# Takes the operating system lock of one job without waiting and returns the open handle, which the caller must hold for as long as it owns the job
function getSchedulerLockHandle(string $name): mixed {
    $file = getSchedulerLockPath($name);
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) return false;
    $lock = fopen($file, 'cb');
    if ($lock === false) return false;
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return false;
    }
    return $lock;
}

# Releases the operating system lock of one job
function deleteSchedulerHandle(mixed $lock): void {
    if (!is_resource($lock)) return;
    flock($lock, LOCK_UN);
    fclose($lock);
}

# Returns whether a process currently owns the job; the operating system lock is the only authority on this, because a crashed process releases it while its JSON still says running
function checkSchedulerLock(string $name): bool {
    $lock = getSchedulerLockHandle($name);
    if ($lock === false) return true;
    deleteSchedulerHandle($lock);
    return false;
}

# Reconciles the state a crashed run left behind, idempotently; last_run keeps its value on purpose, so the job resumes at its next planned slot instead of retrying in a loop
# Returns null when the reconciled state could not be stored, because a caller that reports success on an unwritten repair leaves the job exactly as broken as it found it
function updateSchedulerCrash(string $name, array $state): ?array {
    $state['running'] = 0;
    $state['started_at'] = 0;
    $state['last_status'] = 'crashed';
    $state['fail_count'] = (int)($state['fail_count'] ?? 0) + 1;
    $state['last_error'] = 'Run ended without releasing the job';
    return setSchedulerState($name, $state) ? $state : null;
}

# Returns whether the scheduler job is due; a crashed run leaves no lock behind, so the job becomes due again on its own and is reconciled by the next call that takes the lock
# The planned time is cached in job state, and that cache is only ever written while holding the lock, because job state must never be mutated from a read path
function checkSchedulerDue(string $name, array $job = [], array $state = []): bool {
    if ($job === []) $job = getSchedulerJob($name);
    if ($state === []) $state = getSchedulerState($name);
    if (checkSchedulerLock($name)) return false;
    return checkSchedulerSlot($name, $job, $state);
}

# Returns whether the schedule alone puts this job in the past, without consulting the lock
# A caller that already holds the lock asks this one: the answer it got before locking may have been acted on by another process in the meantime
function checkSchedulerSlot(string $name, array $job, array $state): bool {
    if ((int)($job['active'] ?? 0) !== 1) return false;
    $schedule = getSchedulerSchedule($job);
    if ($schedule === '') return false;
    $last = (int)($state['last_run'] ?? 0);
    $cached = ($state['next_schedule'] ?? '') === $schedule && (int)($state['next_last_run'] ?? 0) === $last;
    if ($cached) return ((int)($state['next_run'] ?? 0)) > 0 && (int)$state['next_run'] <= time();
    $next = getSchedulerPlannedTime($job, $state);
    $lock = getSchedulerLockHandle($name);
    if ($lock !== false) {
        $live = getSchedulerState($name);
        if (empty($live['running'])) setSchedulerState($name, array_replace($live, ['next_run' => $next, 'next_schedule' => $schedule, 'next_last_run' => $last]));
        deleteSchedulerHandle($lock);
    }
    return $next > 0 && $next <= time();
}

# Records the start of a run; the caller must already hold the operating system lock of the job, which is what makes this write safe
function setSchedulerStart(string $name, string $type, array $state): bool {
    $state['running'] = 1;
    $state['started_at'] = time();
    $state['last_run'] = time();
    $state['last_trigger'] = $type;
    $state['last_status'] = 'running';
    $state['last_message'] = '';
    $state['last_error'] = '';
    return setSchedulerState($name, $state);
}

# Records the end of a run and its final status; like the start, this is written while the caller still holds the operating system lock
# Only the fields of the state contract and the metrics of this very run survive, so numbers a previous run reported cannot linger as if they described the current one
function setSchedulerDone(string $name, string $stat, string $mess = '', array $extra = []): bool {
    $state = array_replace(array_intersect_key(getSchedulerState($name), getSchedulerFields()), $extra);
    $done = time();
    $start = (int)($state['started_at'] ?? 0);
    $state['running'] = 0;
    $state['started_at'] = 0;
    $state['last_status'] = $stat;
    $state['last_message'] = $mess;
    $state['last_duration'] = ($start > 0) ? round($done - $start, 2) : 0;
    if ($stat === 'success') {
        $state['last_success'] = $done;
        $state['fail_count'] = 0;
        $state['last_error'] = '';
    } elseif ($stat === 'failed' || $stat === 'crashed') {
        $state['fail_count'] = (int)($state['fail_count'] ?? 0) + 1;
        if ($mess !== '') $state['last_error'] = $mess;
    } else {
        $state['last_error'] = $mess;
    }
    return setSchedulerState($name, $state);
}

# Writes a scheduler heartbeat marker for cron, pseudo-cron, or manual triggers
function addSchedulerHeartbeat(string $type): void {
    $json = json_encode(['trigger' => $type, 'time' => time()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $file = LOGS_DIR.'/scheduler/heartbeat.json';
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) return;
    if (is_string($json)) file_put_contents($file, $json, LOCK_EX);
}

# Returns whether a recent cron heartbeat exists within the configured timeout
function checkSchedulerCronAlive(): bool {
    global $conf;
    $file = LOGS_DIR.'/scheduler/heartbeat.json';
    if (!is_file($file) || filesize($file) === 0) return false;
    $json = file_get_contents($file);
    if ($json === false || $json === '') return false;
    $data = json_decode($json, true);
    if (!is_array($data) || (($data['trigger'] ?? '') !== 'cron')) return false;
    return (time() - (int)($data['time'] ?? 0)) < max(60, (int)($conf['scheduler']['cron_timeout'] ?? 600));
}

# Returns whether the current request may execute the scheduler runner
# Each trigger carries its own credential, and an administrator session is not one of them: the admin path runs through the admin module
function checkSchedulerAccess(string $type, string $stok): bool {
    global $conf;
    if ($stok === '') return false;
    if ($type === 'pseudo') return checkSiteToken($stok, 'scheduler');
    if ($type !== 'cron') return false;
    $stkn = (string)($conf['scheduler']['token'] ?? '');
    return $stkn !== '' && hash_equals($stkn, $stok);
}

# Returns the endpoint and the credential for the asynchronous pseudo trigger when the next due job should be started, or an empty array when it should not
# The credential is returned separately because the trigger is a POST: a token in a query string would travel through history, logs and referrers
function addSchedulerTrigger(): array {
    global $conf;
    if ((int)($conf['scheduler']['active'] ?? 0) !== 1 || (int)($conf['scheduler']['pseudo'] ?? 0) !== 1) return [];
    if (checkSchedulerCronAlive()) return [];
    $file = LOGS_DIR.'/scheduler/trigger.json';
    $last = 0;
    if (is_file($file) && filesize($file) !== 0) {
        $json = file_get_contents($file);
        $data = $json ? json_decode($json, true) : [];
        if (is_array($data)) $last = (int)($data['time'] ?? 0);
    }
    $cool = max(15, (int)($conf['scheduler']['trigger_cooldown'] ?? 15));
    if ($last > 0 && (time() - $last) < $cool) return [];
    $job = getSchedulerNextJob();
    if (!$job) return [];
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) return [];
    $json = json_encode(['time' => time(), 'job' => (string)($job['name'] ?? '')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($json)) file_put_contents($file, $json, LOCK_EX);
    return ['url' => 'index.php?go=3&op=scheduler', 'token' => checkPageCache() ? getDynamicMark('token', 'scheduler') : rawurlencode(getSiteToken('scheduler'))];
}

# Fetches a remote scheduler target through a safe GET request and captures transport errors
function getSchedulerFetch(string $url): array {
    $head = "User-Agent: SLAED Scheduler\r\nAccept: application/json, text/plain, */*\r\n";
    $opts = [
        'http' => [
            'method' => 'GET',
            'timeout' => 15,
            'ignore_errors' => true,
            'header' => $head,
        ],
    ];
    $ctx = stream_context_create($opts);
    $errs = [];
    set_error_handler(static function (int $errno, string $errstr) use (&$errs): bool {
        $errs[] = $errstr;
        return true;
    });
    $link = fopen($url, 'rb', false, $ctx);
    $body = ($link !== false) ? stream_get_contents($link) : false;
    $meta = ($link !== false) ? stream_get_meta_data($link) : [];
    if (is_resource($link)) fclose($link);
    restore_error_handler();
    $code = 0;
    $wrap = (isset($meta['wrapper_data']) && is_array($meta['wrapper_data'])) ? $meta['wrapper_data'] : [];
    foreach ($wrap as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string)$line, $mat)) {
            $code = (int)$mat[1];
            break;
        }
    }
    return [
        'ok' => ($body !== false),
        'code' => $code,
        'body' => ($body === false) ? '' : (string)$body,
        'error' => implode('; ', $errs),
    ];
}

# Executes a custom scheduler job by requesting its configured URL target
function addSchedulerCustom(array $job): array {
    global $conf;
    $url = trim((string)($job['settings']['url'] ?? ''));
    if ($url === '') return ['status' => 'failed', 'message' => 'Custom job URL is empty'];
    if (!preg_match('#^https?://#i', $url)) {
        $base = rtrim((string)($conf['homeurl'] ?? ''), '/');
        if ($base !== '') $url = $base.'/'.ltrim($url, '/');
    }
    $data = getSchedulerFetch($url);
    if (!$data['ok']) {
        $text = ($data['error'] !== '') ? $data['error'] : 'Custom job request failed';
        return ['status' => 'failed', 'message' => $text];
    }
    $text = ($data['code'] > 0) ? 'HTTP '.$data['code'] : 'Request completed';
    return [
        'status' => ($data['code'] >= 400) ? 'failed' : 'success',
        'message' => $text,
        'extra' => [
            'last_remote_code' => $data['code'],
            'last_remote_url' => $url,
        ],
    ];
}

# Returns the next due scheduler job or null when nothing can run
function getSchedulerNextJob(?string $name = null): ?array {
    if ($name !== null && $name !== '') {
        $job = getSchedulerJob($name);
        if (($job['type'] ?? '') !== 'custom' && ($job['system'] ?? '') === '') return null;
        return checkSchedulerDue($name, $job) ? $job : null;
    }
    foreach (getSchedulerJobs() as $job) {
        $name = (string)($job['name'] ?? '');
        if ($name === '' || (($job['type'] ?? '') !== 'custom' && ($job['system'] ?? '') === '')) continue;
        if (checkSchedulerDue($name, $job)) return $job;
    }
    return null;
}

# Runs the database backup through the Backup class; the class is required here rather than globally, because there is no autoloader for core/classes and this job runs once a day
function addBackupJob(): array {
    global $db, $conf;
    if (empty($conf['security']['log_b'])) return ['status' => 'disabled', 'message' => 'Database backup is disabled in the security settings'];
    require_once BASE_DIR.'/core/classes/backup.php';
    $sett = $conf['scheduler']['jobs']['dbbackup']['settings'] ?? [];
    return (new Backup($db, ['name' => (string)($conf['db']['name'] ?? '')], is_array($sett) ? $sett : [], BACKUP_DIR))->addDatabaseBackup();
}

# Dispatches a named system scheduler job by key and returns runtime metadata
function addSchedulerSystemJob(string $name): array {
    return match ($name) {
        'backup' => addBackupJob(),
        'filescan' => addFilescanTask(),
        'sitemap' => addSitemapTask(),
        'maildrain' => addMailTask(),
        'newsletter' => updateNewsletter(),
        'cachegc' => addCacheGcTask(),
        default => ['status' => 'failed', 'message' => 'Unknown system job: '.$name],
    };
}

# Executes the next due scheduler job or a named job and returns a structured result
# The due check that selected the job ran before the lock existed, so a scheduled run asks again while holding it; a manual run is an operator decision and skips that question
# A start or a result that cannot be written fails the run: a job that finishes but still looks due would be run again by the next pass, and a lost result hides what happened
function addSchedulerRun(?string $name = null, string $type = 'manual'): array {
    global $conf;
    if ((int)($conf['scheduler']['active'] ?? 0) !== 1) return ['status' => 'disabled', 'message' => 'Scheduler is disabled'];
    if ($name !== null && $name !== '' && $type === 'manual') {
        $job = getSchedulerJob($name);
        if (($job['system'] ?? '') === '' && ($job['type'] ?? '') !== 'custom') $job = null;
    } else {
        $job = getSchedulerNextJob($name);
    }
    if (!$job) return ['status' => 'idle', 'message' => 'No due jobs'];
    $name = (string)$job['name'];
    $lock = getSchedulerLockHandle($name);
    if ($lock === false) return ['status' => 'locked', 'message' => 'Job is already running', 'job' => $name];
    try {
        $state = getSchedulerState($name);
        if (!empty($state['running'])) {
            $state = updateSchedulerCrash($name, $state);
            if ($state === null) return ['status' => 'failed', 'message' => 'Job state cannot be written, the crashed run was not reconciled', 'job' => $name];
        }
        if ($type !== 'manual' && !checkSchedulerSlot($name, $job, $state)) return ['status' => 'idle', 'message' => 'Another run already covered this slot', 'job' => $name];
        if (!setSchedulerStart($name, $type, $state)) return ['status' => 'failed', 'message' => 'Job state cannot be written, the run was not started', 'job' => $name];
        addSchedulerHeartbeat($type);
        try {
            if (($job['type'] ?? '') === 'custom') {
                $data = addSchedulerCustom($job);
            } else {
                $data = addSchedulerSystemJob($job['system'] ?? '');
            }
            if (!is_array($data)) $data = ['status' => 'failed', 'message' => 'Invalid handler result'];
        } catch (Throwable $error) {
            $data = ['status' => 'failed', 'message' => $error->getMessage()];
        }
        $stat = (string)($data['status'] ?? 'failed');
        $mess = (string)($data['message'] ?? '');
        $extra = (isset($data['extra']) && is_array($data['extra'])) ? $data['extra'] : [];
        if (!setSchedulerDone($name, $stat, $mess, $extra)) {
            $data['status'] = 'failed';
            $data['message'] = trim($mess.' | job state could not be written after the run');
        }
    } finally {
        deleteSchedulerHandle($lock);
    }
    $data['job'] = $name;
    return $data;
}

# Format block
function getBlocks(string $side, string $fly = ''): void {
    global $db, $conf, $locale, $name, $home, $pos, $b_id, $bfile, $prs;
    static $barr;
    if ($conf['multilingual'] == 1) {
        $querylang = "AND (lang = :loc OR lang = '')";
        $qlang_params = ['loc' => $locale];
    } else {
        $querylang = '';
        $qlang_params = [];
    }
    $pos = strtolower($side[0]);
    $side = $pos;
    if (!isset($barr)) {
        $barr = [];
        Parser::$freeoff = true;
        $result = $db->getSqlQuery('SELECT id, bkey, title, content, url, bfile, view, expire, action, bpos, which FROM '.PREFIX_DB."_blocks WHERE status = '1' ".$querylang.' ORDER BY weight ASC', $qlang_params);
        while(list($bid, $bkey, $title, $content, $url, $bfile, $view, $expire, $action, $bpos, $which) = $db->getSqlRow($result)) {
            $bid = intval($bid);
            $content = $prs->filterContent($content, false, 'all', 2);
            $view = intval($view);
            $where_mas = explode(',', $which);
            $barr[] = [$bid, $bkey, $title, $content, $url, $bfile, $view, $expire, $action, $bpos, $where_mas];
        }
        Parser::$freeoff = false;
    }
    if ($fly != '') {
        $b_id = 0;
        $flag = 0;
        $bfile = '';
        if (false === strpos($fly, '-')) {
            $b_id = intval($fly);
        } else {
            $bfile = trim($fly);
        }
        $ci = count($barr);
        for ($i = 0; $i < $ci; $i++) {
            if (($b_id != 0 && $barr[$i][0] == $b_id) || ($bfile != '' && $barr[$i][5] == $bfile)) {
                list($bid, $bkey, $title, $content, $url, $bfile, $view, $expire, $action, $bpos, $where_mas) = $barr[$i];
                $b_id = $bid;
                $flag = 1;
                break;
            }
        }
        if ($flag == 1) {
            if (in_array('flyfix', $where_mas)) {
                switch ($where_mas[0]) {
                    case 'all':
                    $flag_where = 1;
                    break;
                    case '':
                    $flag_where = 1;
                    break;
                    case 'infly':
                    $flag_where = 0;
                    break;
                    case 'home':
                    $flag_where = ($home == 1) ? 1 : 0;
                    break;
                    case 'ihome':
                    if ($home == 1) $flag_where = 1;
                    default:
                    if (empty($home)) {
                        foreach ($where_mas as $val) {
                            if ($val == $name) $flag_where = 1;
                        }
                    }
                    break;
                }
                if (in_array('otricanie', $where_mas)) $flag_where = ($flag_where) ? 0 : 1;
            } else {
                $flag_where = 1;
            }
            if ($flag_where == 1) {
                if ($view == 0) {
                    render_blocks($side, $bfile, $title, $content, $bid, $url); return;
                } elseif ($view == 1 && is_user() || is_moder()) {
                    render_blocks($side, $bfile, $title, $content, $bid, $url); return;
                } elseif ($view == 2 && is_moder()) {
                    render_blocks($side, $bfile, $title, $content, $bid, $url); return;
                } elseif ($view == 3 && !is_user() || is_moder()) {
                    render_blocks($side, $bfile, $title, $content, $bid, $url); return;
                }
            }
        }
    } else {
        $ci = count($barr);
        for ($i = 0; $i < $ci; $i++) {
            if ($barr[$i][9] != $side) continue;
            $flag_where = 0;
            $where_mas = $barr[$i][10];
            switch ($where_mas[0]) {
                case 'all':
                $flag_where = 1;
                break;
                case '':
                $flag_where = 1;
                break;
                case 'infly':
                $flag_where = 0;
                break;
                case 'home':
                $flag_where = ($home == 1) ? 1 : 0;
                break;
                case 'ihome':
                if ($home == 1) $flag_where = 1;
                default:
                if (empty($home)) {
                    foreach ($where_mas as $val) {
                        if ($val == $name) $flag_where = 1;
                    }
                }
                break;
            }
            if (in_array('otricanie', $where_mas)) $flag_where = ($flag_where) ? 0 : 1;
            if ($flag_where == 1) {
                list($bid, $bkey, $title, $content, $url, $bfile, $view, $expire, $action, $bpos, $where_mas) = $barr[$i];
                $b_id = $bid;
                if ($expire && $expire < time()) {
                    if ($action == 'd') {
                        $db->getSqlQuery('UPDATE '.PREFIX_DB."_blocks SET status = '0', expire = '0' WHERE id = :bid", ['bid' => $bid]);
                        return;
                    } elseif ($action == 'r') {
                        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_blocks WHERE id = :bid', ['bid' => $bid]);
                        return;
                    }
                }
                switch ($bkey) {
                    case 'admin':
                    echo adminblock();
                    break;
                    case 'userbox':
                    echo getUserBlock();
                    break;
                    default:
                    if ($view == 0) {
                        render_blocks($side, $bfile, $title, $content, $bid, $url);
                    } elseif ($view == 1 && is_user() || is_moder()) {
                        render_blocks($side, $bfile, $title, $content, $bid, $url);
                    } elseif ($view == 2 && is_moder()) {
                        render_blocks($side, $bfile, $title, $content, $bid, $url);
                    } elseif ($view == 3 && !is_user() || is_moder()) {
                        render_blocks($side, $bfile, $title, $content, $bid, $url);
                    }
                    break;
                }
            }
        }
    }
}

# Get admin module names (stored as names)
function getAdminModuleNames(string $modules): array {
    $list = array_filter(array_map('trim', explode(',', $modules)), 'strlen');
    return array_values(array_unique($list));
}

# Queue one post-response task or return and clear the queue when called without arguments; the shutdown hook backstops exits that skip an explicit drain
function addDeferredTask(?callable $task = null): array {
    static $queue = [];
    static $init = false;
    if ($task !== null) {
        $queue[] = $task;
        if (!$init) {
            $init = true;
            register_shutdown_function('setDeferredTasks');
        }
        return [];
    }
    $out = $queue;
    $queue = [];
    return $out;
}

# Run all queued post-response tracking tasks, releasing the session lock first so the tasks never block the visitor's next request
function setDeferredTasks(): void {
    $tasks = addDeferredTask();
    if ($tasks === []) return;
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    foreach ($tasks as $task) {
        try {
            $task();
        } catch (Throwable $err) {
            if (class_exists('Logger')) Logger::addSite('error', 'Deferred task failed', ['error' => $err->getMessage()]);
        }
    }
}

# Resolve the visitor identity for tracking and queue the session bookkeeping writes for the post-response phase
function updateSessionTrack(int $ctime, string $request, string $name): array {
    global $user, $admin;
    $ip = getIp();
    $url = substr(urlencode($request), 0, 2048);
    $guest = 0;
    $uname = '';
    if (isAdmin()) {
        $uname = filterText(substr($admin[1], 0, 25), 1);
        $guest = 3;
    } elseif (!defined('ADMIN_FILE') && is_user()) {
        $uname = filterText(substr($user[1], 0, 25), 1);
        $guest = 2;
    } elseif (!defined('ADMIN_FILE') && !is_user()) {
        $bname = is_bot();
        if ($bname) {
            $uname = filterText(substr($bname, 0, 25), 1);
            $guest = 1;
        } else {
            $uname = $ip;
            $guest = 0;
        }
    }
    $uid = (!defined('ADMIN_FILE') && is_user()) ? intval($user[0]) : 0;
    $uagent = ($uid) ? getAgent() : '';
    addDeferredTask(static function() use ($ctime, $uname, $guest, $ip, $url, $name, $uid, $uagent): void {
        global $db, $conf;
        $sessf = COUNTER_DIR.'/session.log';
        $sesst = (file_exists($sessf) && filesize($sessf) != 0) ? file_get_contents($sessf) : 0;
        $past = $ctime - intval($conf['sess_t']);
        if ($sesst < $past) {
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE time < :past', ['past' => $past]);
            if (file_exists($sessf)) unlink($sessf);
            $fp = fopen($sessf, 'wb');
            fwrite($fp, $ctime);
            fclose($fp);
        }
        if ($uname !== '') {
            if ($uid) {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET ip = :ip, lastvis = NOW(), agent = :agent WHERE id = :uid AND lastvis < NOW() - INTERVAL 60 SECOND', ['ip' => $ip, 'agent' => $uagent, 'uid' => $uid]);
            }
            $sql = 'INSERT INTO '.PREFIX_DB.'_session (uname, time, ip, guest, modul, url) VALUES (:uname, :time, :ip, :guest, :modul, :url)'
                .' ON DUPLICATE KEY UPDATE time = VALUES(time), ip = VALUES(ip), guest = VALUES(guest), modul = VALUES(modul), url = VALUES(url)';
            $db->getSqlQuery($sql, ['uname' => $uname, 'time' => $ctime, 'ip' => $ip, 'guest' => $guest, 'modul' => $name, 'url' => $url]);
        }
    });
    return ['uname' => $uname, 'guest' => $guest];
}

# Track the current referer hit and optional auto-link attribution
function updateRefererTrack(int $ctime, string $request, string $uname): void {
    global $db, $conf, $user;
    $referer = getReferer();
    if (!$referer) return;
    $referf = COUNTER_DIR.'/referer.log';
    $refert = (file_exists($referf) && filesize($referf) != 0) ? file_get_contents($referf) : 0;
    $past = $ctime - intval($conf['referers']['refer_t']);
    if ($refert < $past) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_referer WHERE lid = :lid', ['lid' => 0]);
        if (file_exists($referf)) unlink($referf);
        $fp = fopen($referf, 'wb');
        fwrite($fp, $ctime);
        fclose($fp);
    }
    $ip = getIp();
    $uid = is_user() ? intval($user[0]) : 0;
    $link = filterText($request);
    $args = ['uid' => $uid, 'name' => $uname, 'ip' => $ip, 'referer' => $referer, 'url' => $link, 'lid' => 0];
    if (is_active('auto_links')) {
        [$exist] = $db->getSqlRow($db->getSqlQuery('SELECT ip FROM '.PREFIX_DB.'_referer WHERE ip = :ip AND lid != :lid', ['ip' => $ip, 'lid' => 0]));
        if ($exist) {
            if ($conf['referers']['referb'] != 1 || ($conf['referers']['referb'] == 1 && from_bot())) {
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_referer (uid, name, ip, referer, url, time, lid) VALUES (:uid, :name, :ip, :referer, :url, NOW(), :lid)', $args);
            }
            return;
        }
        $lid = 0;
        $result = $db->getSqlQuery('SELECT id, url FROM '.PREFIX_DB.'_auto_links');
        while ([$aid, $aurl] = $db->getSqlRow($result)) {
            if ($aurl !== '' && stripos($referer, $aurl) !== false) {
                $lid = intval($aid);
                break;
            }
        }
        if ($lid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_auto_links SET hits = hits + 1 WHERE id = :lid', ['lid' => $lid]);
            $args['lid'] = $lid;
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_referer (uid, name, ip, referer, url, time, lid) VALUES (:uid, :name, :ip, :referer, :url, NOW(), :lid)', $args);
            return;
        }
    }
    if ($conf['referers']['referb'] != 1 || ($conf['referers']['referb'] == 1 && from_bot())) {
        $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_referer (uid, name, ip, referer, url, time, lid) VALUES (:uid, :name, :ip, :referer, :url, NOW(), :lid)', $args);
    }
}

# Parse a key count field into a normalized map
function getCounterField(string $field): array {
    $vals = [];
    if ($field === '') return $vals;
    foreach (explode(',', $field) as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $bits = explode(':', $part, 2);
        $key = trim((string)($bits[0] ?? ''));
        if ($key === '') continue;
        $val = (int)trim((string)($bits[1] ?? '0'));
        $vals[$key] = ($vals[$key] ?? 0) + $val;
    }
    return $vals;
}

# Update one counter field with top ten keys and an overflow bucket
function updateCounterField(string $field, string $key, int $by = 1): string {
    if ($key === '') return $field;
    $vals = getCounterField($field);
    $vals[$key] = ($vals[$key] ?? 0) + $by;
    $oth = (int)($vals['Other'] ?? 0);
    unset($vals['Other']);
    arsort($vals, SORT_NUMERIC);
    $out = [];
    $num = 0;
    foreach ($vals as $name => $val) {
        $val = (int)$val;
        if ($val <= 0) continue;
        if ($num < 10) {
            $out[] = $name.':'.$val;
        } else {
            $oth += $val;
        }
        $num++;
    }
    if ($oth > 0) $out[] = 'Other:'.$oth;
    return implode(',', $out);
}

# Update the 24 hour field with one hit
function updateHoursField(string $field, int $hour): string {
    $hrs = ($field === '') ? [] : explode(',', $field);
    $hrs = array_pad(array_slice($hrs, 0, 24), 24, 0);
    $hour = max(0, min(23, $hour));
    $hrs[$hour] = (int)$hrs[$hour] + 1;
    foreach ($hrs as $key => $val) {
        $hrs[$key] = (string)((int)$val);
    }
    return implode(',', $hrs);
}

# Return the session depth bucket
function getSessionDepthBucket(int $depth): string {
    if ($depth <= 1) return '1';
    if ($depth <= 3) return '2-3';
    if ($depth <= 7) return '4-7';
    return '8+';
}

# Return the session duration bucket
function getSessionDurationBucket(int $secs): string {
    if ($secs < 30) return '<30s';
    if ($secs <= 180) return '30s-3m';
    if ($secs <= 900) return '3m-15m';
    return '15m+';
}

# Return the referral category for a visit
function getRefCategory(string $ref): string {
    global $conf;
    if ($ref === '') return 'direct';
    $home = preg_replace('#^www\.#i', '', strtolower((string)parse_url((string)($conf['homeurl'] ?? ''), PHP_URL_HOST)));
    $host = preg_replace('#^www\.#i', '', strtolower((string)parse_url($ref, PHP_URL_HOST)));
    if ($home !== '' && $host !== '' && $home === $host) return 'direct';
    if ($host === '') return 'direct';
    $search = ['google', 'bing', 'yahoo', 'yandex', 'duckduckgo', 'baidu', 'ecosia', 'qwant'];
    foreach ($search as $key) {
        if (str_contains($host, $key)) return 'search';
    }
    $social = ['facebook', 'twitter', 't.co', 'instagram', 'linkedin', 'vk.com', 'reddit', 'youtube', 'telegram', 'tiktok'];
    foreach ($social as $key) {
        if (str_contains($host, $key)) return 'social';
    }
    return 'referrer';
}

# Parse the user agent into browser, operating system and device
function getAgentInfo(string $ua, int $guest): array {
    $bot = '#bot|crawl|spider|slurp|bingpreview|petalbot#i';
    if ($guest === 1 || preg_match($bot, $ua)) {
        return ['browser' => 'Bot', 'os' => 'Bot', 'device' => 'bot'];
    }
    $browser = 'Other';
    if (preg_match('#(?:edg|edge)/(\d+)#i', $ua, $out)) {
        $browser = 'Edge '.$out[1];
    } elseif (preg_match('#opr/(\d+)#i', $ua, $out)) {
        $browser = 'Opera '.$out[1];
    } elseif (preg_match('#chrome/(\d+)#i', $ua, $out)) {
        $browser = 'Chrome '.$out[1];
    } elseif (preg_match('#firefox/(\d+)#i', $ua, $out)) {
        $browser = 'Firefox '.$out[1];
    } elseif (preg_match('#version/(\d+).*safari#i', $ua, $out) && !preg_match('#(?:chrome|chromium|crios|crmo|edg|edge|opr)/#i', $ua)) {
        $browser = 'Safari '.$out[1];
    } elseif (preg_match('#(?:msie |rv:)(\d+)#i', $ua, $out) && (stripos($ua, 'msie') !== false || stripos($ua, 'trident') !== false)) {
        $browser = 'IE '.$out[1];
    }
    $os = 'Other';
    if (preg_match('#windows nt|windows phone#i', $ua)) {
        $os = 'Windows';
    } elseif (preg_match('#android#i', $ua)) {
        $os = 'Android';
    } elseif (preg_match('#iphone|ipad|ipod#i', $ua)) {
        $os = 'iOS';
    } elseif (preg_match('#mac os x|macintosh#i', $ua)) {
        $os = 'macOS';
    } elseif (preg_match('#linux#i', $ua)) {
        $os = 'Linux';
    }
    $device = 'desktop';
    if (preg_match('#(?:iphone|ipod|windows phone|android.*mobile)#i', $ua)) {
        $device = 'mobile';
    } elseif (preg_match('#ipad#i', $ua) || (preg_match('#android#i', $ua) && !preg_match('#mobile#i', $ua))) {
        $device = 'tablet';
    }
    return ['browser' => $browser, 'os' => $os, 'device' => $device];
}

# Refresh the signed v2 visitor stats cookie in the pre-output phase; it carries only approximate session metrics and the country cache, exact unique counts never read client state
function updateStatsCookie(int $guest): array {
    global $conf;
    $state = ['sess' => null, 'country' => ''];
    if ($guest === 1 || $guest === 3) return $state;
    $ip = getIp();
    $key = getSecret('stats');
    $iph = substr(hash_hmac('sha256', $ip, $key), 0, 16);
    $now = time();
    $sid = '';
    $fst = 0;
    $lst = 0;
    $hits = 0;
    $cc = '';
    $cts = 0;
    $raw = (string)($_COOKIE[$conf['user_c'].'-stats'] ?? '');
    if ($raw !== '' && preg_match('#^[A-Za-z0-9_-]+$#', $raw)) {
        $data = base64_decode(strtr($raw, '-_', '+/'), true);
        $pos = ($data !== false) ? strrpos($data, '|') : false;
        if ($pos !== false && hash_equals(hash_hmac('sha256', substr($data, 0, $pos), $key), substr($data, $pos + 1))) {
            $part = array_pad(explode('|', substr($data, 0, $pos)), 8, '');
            if ($part[0] === 'v2' && preg_match('#^[a-f0-9]{16}$#', $part[1])) {
                [, $sid, $fst, $lst, $hits, $cc, $cts, $oiph] = $part;
                $fst = (int)$fst;
                $lst = (int)$lst;
                $hits = (int)$hits;
                $cts = (int)$cts;
                if ($oiph !== $iph) {
                    $cc = '';
                    $cts = 0;
                }
            }
        }
    }
    if ($sid === '') {
        try {
            $sid = bin2hex(random_bytes(8));
        } catch (Exception) {
            return $state;
        }
    }
    $isnew = ($lst < $now - 1800);
    if ($isnew) {
        $fst = $now;
        $hits = 1;
    } else {
        $hits++;
    }
    $lst = $now;
    $state['sess'] = ['is_new' => $isnew, 'depth' => $hits, 'duration' => max(0, $lst - $fst)];
    if ($cc === '' || $cts < $now - 86400) {
        $cc = class_exists('Geoip') ? Geoip::getCountry($ip) : '';
        $cts = $now;
    }
    $state['country'] = $cc;
    if (!headers_sent()) {
        $body = implode('|', ['v2', $sid, $fst, $lst, $hits, $cc, $cts, $iph]);
        $val = rtrim(strtr(base64_encode($body.'|'.hash_hmac('sha256', $body, $key)), '+/', '-_'), '=');
        setCookies('stats', $now + 31536000, $val);
    }
    return $state;
}

# Write daily statistics under one exclusive statistic.lock; statistic.log is written atomically, unique counters derive from the ips/user sets, rollover aborts on any failure
function updateStatsTrack(string $request, int $guest, array $state): void {
    global $conf, $user;
    $sreferer = getReferer();
    $sreqhom = filterText($request);
    $spath = COUNTER_DIR.'/';
    $slog = $spath.'statistic.log';
    $info = getAgentInfo(getAgent(), $guest);
    $rcat = ($guest !== 1) ? getRefCategory($sreferer) : '';
    $cc = ($state['country'] !== '') ? $state['country'] : (class_exists('Geoip') ? Geoip::getCountry(getIp()) : '');
    $sess = $state['sess'];
    $safeOpen = static function(string $file, string $mode) {
        set_error_handler(static function(): bool {
            return true;
        });
        try {
            $handle = fopen($file, $mode);
        } finally {
            restore_error_handler();
        }
        return $handle ?: false;
    };
    $flk = $safeOpen($spath.'statistic.lock', 'c');
    if ($flk === false) return;
    if (!flock($flk, LOCK_EX)) {
        fclose($flk);
        return;
    }
    try {
        $data = is_file($slog) ? file_get_contents($slog) : '';
        if ($data === false) {
            addErrorFile(_ERR_READ.': '.$slog);
            return;
        }
        $line = trim($data);
        $con = ($line !== '') ? explode('|', $line) : [];
        $today = date('d.m.Y');
        $reqhom = ($sreqhom == '/' || $sreqhom == '/index.html' || $sreqhom == '/index.php') ? 1 : 0;
        $isday = ($con !== [] && $con[0] === $today);
        if (!$isday && $con !== []) {
            $rotate = preg_match('#^\d{2}\.\d{2}\.\d{4}$#', $con[0]) === 1 && substr($con[0], 3) != date('m.Y');
            $sdir = $spath.'statistic';
            $dest = $rotate ? $sdir.'/statistic_'.substr($con[0], 6).'-'.substr($con[0], 3, 2).'.log' : '';
            $done = false;
            if ($rotate && file_exists($dest) && (!is_file($spath.'days.log') || !filesize($spath.'days.log'))) {
                $arch = file_get_contents($dest);
                if ($arch === false || !preg_match('#^'.preg_quote($line, '#').'\r?$#m', $arch)) {
                    addErrorFile('Statistic rotation failed: existing archive misses day '.$con[0]);
                    return;
                }
                $done = true;
            }
            if (!$done) {
                $keep = $line."\n";
                $saved = false;
                $why = 'days.log append';
                $fpd = $safeOpen($spath.'days.log', 'c+');
                if ($fpd) {
                    if (flock($fpd, LOCK_EX)) {
                        $dlog = stream_get_contents($fpd);
                        if ($dlog !== false && $dlog !== '' && !str_ends_with($dlog, "\n")) {
                            $cut = strrpos($dlog, "\n");
                            $len = ($cut === false) ? 0 : $cut + 1;
                            $dlog = ftruncate($fpd, $len) ? substr($dlog, 0, $len) : false;
                        }
                        if ($dlog !== false && preg_match('#^'.preg_quote($line, '#').'\r?$#m', $dlog)) {
                            $saved = true;
                        } elseif ($dlog !== false && preg_match('#^'.preg_quote($con[0], '#').'\|#m', $dlog)) {
                            $why = 'days.log day conflict for '.$con[0];
                        } elseif ($dlog !== false) {
                            fseek($fpd, 0, SEEK_END);
                            $pos = (int)ftell($fpd);
                            if (fwrite($fpd, $keep) === strlen($keep) && fflush($fpd)) {
                                $saved = true;
                            } else {
                                ftruncate($fpd, $pos);
                            }
                        }
                        flock($fpd, LOCK_UN);
                    }
                    fclose($fpd);
                }
                if (!$saved) {
                    addErrorFile('Statistic rollover failed: '.$why);
                    return;
                }
                if ($rotate) {
                    if (!is_dir($sdir) && !mkdir($sdir, 0755, true) && !is_dir($sdir)) {
                        addErrorFile('Statistic rotation failed: archive directory');
                        return;
                    }
                    if (file_exists($dest)) {
                        addErrorFile('Statistic rotation failed: archive already exists for day '.$con[0]);
                        return;
                    }
                    if (!rename($spath.'days.log', $dest)) {
                        addErrorFile('Statistic rotation failed: archive move for day '.$con[0]);
                        return;
                    }
                }
            }
        }
        if (!$isday) {
            if (file_exists($spath.'ips.log') && !unlink($spath.'ips.log')) {
                addErrorFile('Statistic rollover failed: reset ips.log');
                return;
            }
            if (file_exists($spath.'user.log') && !unlink($spath.'user.log')) {
                addErrorFile('Statistic rollover failed: reset user.log');
                return;
            }
        }
        $ip = getIp();
        $uname = ($conf['session'] && $guest == 2 && is_user()) ? filterText(substr((string)($user[1] ?? ''), 0, 25), 1) : '';
        $iplog = is_file($spath.'ips.log') ? file_get_contents($spath.'ips.log') : '';
        if ($iplog === false) {
            addErrorFile(_ERR_READ.': '.$spath.'ips.log');
            return;
        }
        $unlog = is_file($spath.'user.log') ? file_get_contents($spath.'user.log') : '';
        if ($unlog === false) {
            addErrorFile(_ERR_READ.': '.$spath.'user.log');
            return;
        }
        $ipnew = !str_contains(','.$iplog, ','.$ip.',');
        if ($ipnew && addFile($spath.'ips.log', $ip.',', 'none', false, 'a') !== 0) $ipnew = false;
        $unew = $uname !== '' && !str_contains(','.$unlog, ','.$uname.',');
        if ($unew && addFile($spath.'user.log', $uname.',', 'none', false, 'a') !== 0) $unew = false;
        $shost = substr_count($iplog, ',') + ($ipnew ? 1 : 0);
        $suser = substr_count($unlog, ',') + ($unew ? 1 : 0);
        if ($isday) {
            $sengine = ($ipnew && $conf['session'] && $guest == 1) ? intval(($con[4] ?? 0) + 1) : ($con[4] ?? 0);
            $srefer = ($ipnew && $sreferer) ? intval(($con[5] ?? 0) + 1) : ($con[5] ?? 0);
            $shome = ($reqhom) ? intval(($con[6] ?? 0) + 1) : ($con[6] ?? 0);
            $wc = $con[0].'|'.$shost.'|'.intval(($con[2] ?? 0) + 1).'|'.intval(($con[3] ?? 0) + 1).'|'.$sengine.'|'.$srefer.'|'.$shome.'|'.$suser;
            $prev = $con;
        } else {
            $ahits = ($con[3] ?? 0) ? intval(($con[3] ?? 0) + 1) : 1;
            $sengine = ($conf['session'] && $guest == 1) ? 1 : 0;
            $srefer = ($sreferer) ? 1 : 0;
            $wc = $today.'|'.$shost.'|1|'.$ahits.'|'.$sengine.'|'.$srefer.'|'.$reqhom.'|'.$suser;
            $prev = [];
        }
        $ext = explode('|', $wc);
        $ext[8] = updateCounterField($prev[8] ?? '', $info['browser']);
        $ext[9] = updateCounterField($prev[9] ?? '', $info['os']);
        $ext[10] = updateCounterField($prev[10] ?? '', $info['device']);
        $ext[11] = ($cc !== '') ? updateCounterField($prev[11] ?? '', $cc) : ($prev[11] ?? '');
        $ext[12] = ($guest !== 1) ? updateCounterField($prev[12] ?? '', $rcat) : ($prev[12] ?? '');
        $ext[13] = updateHoursField($prev[13] ?? '', (int)date('G'));
        $ext[14] = ($sess !== null) ? updateCounterField($prev[14] ?? '', $sess['is_new'] ? 'new' : 'returning') : ($prev[14] ?? '');
        $ext[15] = ($sess !== null) ? updateCounterField($prev[15] ?? '', getSessionDepthBucket($sess['depth'])) : ($prev[15] ?? '');
        $ext[16] = ($sess !== null) ? updateCounterField($prev[16] ?? '', getSessionDurationBucket($sess['duration'])) : ($prev[16] ?? '');
        $wc = implode('|', $ext);
        if (!Cache::setBody($slog, $wc)) {
            addErrorFile('Statistic counter write failed: statistic.log');
            return;
        }
    } finally {
        flock($flk, LOCK_UN);
        fclose($flk);
    }
}

# Normalize current request parameters for public SEO URLs
function filterCanonicalParams(): array {
    $vars = [];
    $name = getVar('get', 'name', 'var');
    if ($name !== '') $vars['name'] = $name;
    $op = getVar('get', 'op', 'var');
    if ($op !== '') $vars['op'] = $op;
    $id = getVar('get', 'id', 'num');
    if ($id) $vars['id'] = (string)$id;
    $cat = getVar('get', 'cat', 'num');
    if ($cat) $vars['cat'] = (string)$cat;
    $num = getVar('get', 'num', 'num');
    if ($num > 1) $vars['num'] = (string)$num;
    $let = getVar('get', 'let', 'let');
    if ($let !== '') $vars['let'] = $let;
    $uname = getVar('get', 'uname', 'name');
    if ($uname !== '') $vars['uname'] = rawurlencode($uname);
    return $vars;
}

# Build one public site URL from normalized route parameters
function getPublicUrl(array $vars = []): string {
    global $conf;
    $base = rtrim((string)($conf['homeurl'] ?? ''), '/');
    if ($vars === []) return $base.'/';
    $path = ltrim(getSeoUrl($vars), '/');
    return $base.'/'.$path;
}

# Resolve canonical URL and robots defaults for the current frontend request
function getSeoRoute(array $seo = []): array {
    $vars = filterCanonicalParams();
    $name = $vars['name'] ?? '';
    $op = $vars['op'] ?? '';
    if (!empty($vars['id']) && !empty($GLOBALS['conf']['rewrite'])) {
        $vars['title'] = filterSeoText((string)($seo['title'] ?? ''));
        $vars['ctitle'] = filterSeoText((string)($seo['ctitle'] ?? ''));
    }
    $robot = trim((string)($seo['robots'] ?? ''));
    $canon = trim((string)($seo['canon'] ?? ''));
    $services = ['contact', 'money', 'order', 'recommend', 'search', 'whois'];
    $forms = [
        'add', 'activate', 'broc', 'check', 'client', 'edithome', 'favorites', 'kasse',
        'partner', 'passlost', 'preview', 'privat', 'send', 'upload',
    ];
    $noindex = in_array($name, $services, true) || in_array($op, $forms, true) || $op === 'liste' || ($name === 'account' && $op !== 'view');
    if (getVar('get', 'status', 'num')) $noindex = true;
    $status = http_response_code();
    if ($status >= 400) $noindex = true;
    $iscanon = !$noindex;
    if ($robot === '') {
        $robot = $noindex ? 'noindex, follow' : 'index, follow';
    }
    if (stripos($robot, 'noindex') !== false) $iscanon = false;
    if ($canon !== '') {
        if (!preg_match('#^https?://#i', $canon)) {
            $base = rtrim((string)($GLOBALS['conf']['homeurl'] ?? ''), '/');
            $canon = $base.'/'.ltrim($canon, '/');
        }
    } elseif ($iscanon) {
        $canon = getPublicUrl($vars);
    }
    return [
        'robot' => $robot,
        'canon' => $canon,
        'iscanon' => $iscanon && $canon !== '',
        'siteurl' => getPublicUrl($vars),
        'kind' => $noindex ? 'utility' : (($name === 'account' && $op === 'view') ? 'profile' : ''),
    ];
}

# Normalize SEO text for title, meta, Open Graph, and structured-data values
function filterSeoText(string $text): string {
    $text = preg_replace('#<[^>]+>#u', ' ', $text) ?? $text;
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

# Join unique non-empty title segments while preserving their first occurrence and display order
function getSeoTitle(array $parts, string $sep): string {
    $out = [];
    $seen = [];
    foreach ($parts as $part) {
        $part = filterSeoText((string)$part);
        if ($part === '') continue;
        $key = function_exists('mb_strtolower') ? mb_strtolower($part, 'UTF-8') : strtolower($part);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = $part;
    }
    return implode(' '.$sep.' ', $out);
}

# Encode one structured-data object without unsafe HTML-significant JSON characters
function getSeoJson(array $data): string {
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
    $flags |= JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;
    return json_encode($data, $flags);
}

# Decode every JSON-LD object from one configurable script template for safe re-encoding
function getSeoJsonItems(string $html): array {
    $pat = '#<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is';
    $hits = preg_match_all($pat, $html, $rows);
    if (!$hits) throw new UnexpectedValueException('Schema template does not contain JSON-LD');
    $rest = preg_replace($pat, '', $html);
    if (trim((string)$rest) !== '') throw new UnexpectedValueException('Schema template contains markup outside JSON-LD scripts');
    $items = [];
    foreach ($rows[1] as $json) {
        $data = json_decode(trim($json), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_is_list($data)) throw new UnexpectedValueException('Schema JSON-LD must decode to an object');
        $items[] = $data;
    }
    return $items;
}

# Replace legacy placeholders only in decoded structured-data string values
function setSeoJsonVars(array $data, array $vars): array {
    foreach ($data as $key => $val) {
        if (is_array($val)) {
            $data[$key] = setSeoJsonVars($val, $vars);
        } elseif (is_string($val)) {
            $data[$key] = str_replace(array_keys($vars), array_values($vars), $val);
        }
    }
    return $data;
}

# Decode configurable Open Graph meta tags into property values for safe template rendering
function getSeoGraph(string $html, array $vars): array {
    preg_match_all('#<meta\b[^>]*>#is', $html, $rows);
    $data = [];
    foreach ($rows[0] ?? [] as $tag) {
        if (!preg_match('#\bproperty\s*=\s*(["\'])(og:[^"\']+)\1#i', $tag, $prop)) continue;
        if (!preg_match('#\bcontent\s*=\s*(["\'])(.*?)\1#is', $tag, $cont)) continue;
        $valu = html_entity_decode($cont[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $data[$prop[2]] = str_replace(array_keys($vars), array_values($vars), $valu);
    }
    return $data;
}

# Build the route-level structured-data object from explicit page facts
function getSeoSchema(string $kind, array $seo, bool $ishome = false): array {
    $types = [
        'website' => $ishome ? 'WebSite' : 'WebPage',
        'collection' => 'CollectionPage',
        'article' => 'Article',
        'news' => 'NewsArticle',
        'product' => 'WebPage',
        'forum' => 'WebPage',
        'profile' => 'WebPage',
        'utility' => 'WebPage',
    ];
    $data = [
        '@context' => 'https://schema.org',
        '@type' => $types[$kind] ?? 'WebPage',
        'name' => $seo['title'],
        'url' => $seo['url'],
    ];
    if ($seo['desc'] !== '') $data['description'] = $seo['desc'];
    if ($seo['img'] !== '') $data['image'] = $seo['img'];
    if (in_array($kind, ['article', 'news'], true)) {
        $data['headline'] = $seo['title'];
        if ($seo['time'] !== '') $data['datePublished'] = $seo['time'];
        if ($seo['mtime'] !== '') $data['dateModified'] = $seo['mtime'];
        if ($seo['author'] !== '') {
            $isorg = strcasecmp($seo['author'], $seo['site']) === 0;
            $data['author'] = ['@type' => $isorg ? 'Organization' : 'Person', 'name' => $seo['author']];
            if ($isorg) $data['author']['url'] = $seo['home'];
        }
        $data['publisher'] = [
            '@type' => 'Organization',
            'name' => $seo['site'],
            'url' => $seo['home'],
            'logo' => ['@type' => 'ImageObject', 'url' => $seo['logo']],
        ];
        $data['mainEntityOfPage'] = ['@type' => 'WebPage', '@id' => $seo['url']];
    }
    return $data;
}

# Return the shared category map for one module or all modules as id => raw title, parent, ordern; epoch-keyed persistent data cache plus request-static, callers apply getConst and escaping at their own boundary
function getCategoryMap(string $mod = ''): array {
    global $db;
    static $maps = [];
    $key = ($mod === '') ? '*' : $mod;
    if (isset($maps[$key])) return $maps[$key];
    $file = Cache::getPath('data', Cache::getHash(['catmap', $key, Cache::getEpoch()]), 'json');
    if (Cache::isFresh($file, 86400)) {
        $data = json_decode(Cache::getBody($file), true);
        if (is_array($data)) return $maps[$key] = $data;
    }
    $where = ($mod !== '') ? ' WHERE modul = :modul' : '';
    $pars = ($mod !== '') ? ['modul' => $mod] : [];
    $res = $db->getSqlQuery('SELECT id, title, parent, ordern FROM '.PREFIX_DB.'_categories'.$where, $pars);
    $map = [];
    while ([$cid, $title, $parent, $ordern] = $db->getSqlRow($res)) {
        $map[(int)$cid] = ['title' => (string)$title, 'parent' => (int)$parent, 'ordern' => (int)$ordern];
    }
    Cache::setBody($file, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $maps[$key] = $map;
}

# Build a visible module/category/page breadcrumb trail as BreadcrumbList data
function getSeoBreadcrumbSchema(string $name, int $cid, string $title, string $url): array {
    if ($name === '' || $cid < 1) return [];
    $cats = getCategoryMap($name);
    $chain = [];
    $cur = $cid;
    $guard = 0;
    while ($cur && isset($cats[$cur]) && $guard++ < 50) {
        $chain[] = $cur;
        $cur = $cats[$cur]['parent'];
    }
    $chain = array_reverse($chain);
    if (!$chain) return [];
    $items = [];
    $pos = 1;
    $items[] = [
        '@type' => 'ListItem',
        'position' => $pos++,
        'name' => filterSeoText(getModuleName($name)),
        'item' => getPublicUrl(['name' => $name]),
    ];
    foreach ($chain as $id) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $pos++,
            'name' => filterSeoText(getConst($cats[$id]['title'])),
            'item' => getPublicUrl(['name' => $name, 'cat' => $id]),
        ];
    }
    if ($title !== '' && $title !== filterSeoText(getConst($cats[$cid]['title']))) {
        $items[] = ['@type' => 'ListItem', 'position' => $pos, 'name' => $title, 'item' => $url];
    }
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

# Record or report visitor-bound content leaking into a cacheable page build; a poisoned build is never stored in the page cache
function checkCachePoison(bool $mark = false): bool {
    static $bad = false;
    if ($mark) $bad = true;
    return $bad;
}

# Validate one dynamic-region type and parameter against the approved marker contract; only these exact combinations may ever be signed or rendered
function checkDynamicMark(string $type, string $par): bool {
    if ($type === 'token') return in_array($par, ['ajax', 'account', 'scheduler'], true);
    if ($type === 'captcha') return in_array($par, ['login', 'register', 'comment', 'contact'], true);
    if ($type === 'voting') return preg_match('#^[1-9][0-9]{0,8}$#', $par) === 1;
    return false;
}

# Build one signed dynamic-region marker for cacheable builds; an invalid type or parameter poisons the build, is logged, and never yields a signed marker
function getDynamicMark(string $type, string $par = ''): string {
    if (!checkDynamicMark($type, $par)) {
        checkCachePoison(true);
        addErrorFile('Rejected dynamic-region marker: '.$type);
        return '';
    }
    return '[[sldyn:'.$type.':'.$par.':'.substr(hash_hmac('sha256', $type.':'.$par, getSecret('dynreg')), 0, 16).']]';
}

# Return the CSRF token for page markup, or a signed dynamic-region marker when the build is cacheable; a rejected marker falls back to the live token
function getPageToken(string $scope = 'ajax'): string {
    if (!checkPageCache()) return getSiteToken($scope);
    $mark = getDynamicMark('token', $scope);
    return ($mark !== '') ? $mark : getSiteToken($scope);
}

# Return the captcha block for page markup, or a signed dynamic-region marker when the build is cacheable; a rejected marker falls back to the live captcha
function getPageCaptcha(string $act): string {
    if (!checkPageCache()) return getCaptcha($act);
    $mark = getDynamicMark('captcha', $act);
    return ($mark !== '') ? $mark : getCaptcha($act);
}

# Render one known dynamic region fresh for the current visitor; the contract is revalidated at serve time so forged or stale markers stay inert
function getDynamicRegion(string $type, string $par): string {
    if (!checkDynamicMark($type, $par)) return '';
    if ($type === 'token') return htmlspecialchars(getSiteToken($par), ENT_QUOTES, 'UTF-8');
    if ($type === 'captcha') return getCaptcha($par);
    return getVotingView((int)$par, 'blockvoting');
}

# Replace signed dynamic-region markers with freshly rendered visitor-bound content; unsigned or forged markers stay literal text
function setDynamicRegions(string $html): string {
    if (!str_contains($html, '[[sldyn:')) return $html;
    return preg_replace_callback('#\[\[sldyn:([a-z]+):([a-z0-9_-]*):([a-f0-9]{16})\]\]#', static function(array $m): string {
        if (!hash_equals(substr(hash_hmac('sha256', $m[1].':'.$m[2], getSecret('dynreg')), 0, 16), $m[3])) return $m[0];
        return getDynamicRegion($m[1], $m[2]);
    }, $html) ?? $html;
}

# Validate the current request against the per-route page-cache contract: canonical homeurl host plus known, single, well-formed query keys; null means render live without caching
function getCacheRouteVars(): ?array {
    global $conf;
    static $memo = false;
    if ($memo !== false) return $memo;
    $canon = strtolower((string)parse_url((string)($conf['homeurl'] ?? ''), PHP_URL_HOST));
    $port = parse_url((string)($conf['homeurl'] ?? ''), PHP_URL_PORT);
    if ($canon === '' || strtolower(getHost()) !== $canon.($port ? ':'.$port : '')) return $memo = null;
    $url = $_SERVER['REQUEST_URI'] ?? getenv('REQUEST_URI') ?: '';
    $allow = ['name' => '#^news$#', 'op' => '#^$#', 'cat' => '#^[1-9][0-9]{0,8}$#', 'num' => '#^[1-9][0-9]{0,8}$#'];
    if (Cache::getQueryVars($url, $allow) === null) return $memo = null;
    $vars = ['name' => getVar('get', 'name', 'var')];
    $cat = getVar('get', 'cat', 'num');
    if ($cat) $vars['cat'] = (string)$cat;
    $num = getVar('get', 'num', 'num');
    if ($num > 1) $vars['num'] = (string)$num;
    return $memo = $vars;
}

# Decide whether the request may be served from or stored into the page cache; routes are default-deny per module and op and must satisfy the parameter contract
function checkPageCache(): bool {
    global $conf, $home, $name, $op;
    if (defined('ADMIN_FILE')) return false;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return false;
    if (empty($conf['cache'])) return false;
    if ($conf['cache'] == 2 && !$home) return false;
    if (is_user() || isAdmin()) return false;
    if (!empty($_SESSION[$conf['user_c'].'-flash'])) return false;
    $ops = ['news' => ['']];
    if (!in_array((string)($op ?? ''), $ops[$name ?? ''] ?? [], true)) return false;
    return getCacheRouteVars() !== null;
}

# Build the pc3 page cache identity from version, epoch, canonical host, scheme, theme, locale, and validated route parameters; old cache files stay unreachable until GC
# The prefix is the version field of the key: an entry written under earlier rules must not be served now, and bumping the literal retires all of them at once
function getPageHash(): string {
    global $theme, $locale, $conf;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $canon = strtolower((string)parse_url((string)($conf['homeurl'] ?? ''), PHP_URL_HOST));
    $vars = getCacheRouteVars() ?? [];
    ksort($vars);
    return Cache::getHash(['pc3', $conf['version'] ?? '', Cache::getEpoch(), $canon, $scheme, $theme, $locale, http_build_query($vars)]);
}

# Sweep stale page-cache files older than the retention window as a scheduler job and report the removed count
# Asset bundles are swept too: a bundle in use has its file rewritten on every rebuild, so only the one no other page points at can reach the retention window, which is a full day of page TTLs
function addCacheGcTask(): array {
    global $conf;
    $ttl = max((int)$conf['cache_t'] * 24, 86400);
    $num = Cache::deleteStale('html', $ttl) + Cache::deleteStale('locks', $ttl) + Cache::deleteStale('data', $ttl)
        + Cache::deleteStale('assets', $ttl) + Cache::deleteStaleTree(CACHE_DIR.'/templates', $ttl);
    return ['status' => 'success', 'message' => 'Removed '.$num.' cache files'];
}

# Format head
# A stored page may be handed to the browser cache too: that needs an entry with no dynamic region and a cache_b in days, and such a response drops the generation-time
# marker, because a copy the browser answers from its own store would keep showing the moment the first visitor was served
function setHead(array $seo = []): void {
    global $home, $conf, $user, $name, $theme, $op, $tpl, $adminpage, $adminvars, $sitepage, $sitevars;
    $name = $name ?? '';
    $ctime = time();
    $request = $_SERVER['REQUEST_URI'] ?? getenv('REQUEST_URI') ?: '';
    if ($conf['session']) {
        $track = updateSessionTrack($ctime, $request, $name);
        $uname = $track['uname'];
        $guest = $track['guest'];
    } else {
        $uname = '';
        $guest = 0;
    }
    if ($conf['referers']['refer']) addDeferredTask(static fn() => updateRefererTrack($ctime, $request, $uname));
    if ($conf['statistic']['stat']) {
        $stats = updateStatsCookie($guest);
        addDeferredTask(static fn() => updateStatsTrack($request, $guest, $stats));
    }
    if (checkPageCache()) {
        $hash = getPageHash();
        $file = Cache::getPath('html', $hash, 'html');
        if (Cache::isFresh($file, $conf['cache_t'])) {
            $body = Cache::getBody($file);
            if ($body !== '') {
                $meta = Cache::getMeta($file, $body);
                $days = (int)$conf['cache_b'];
                if (!$meta['dyn'] && $days > 0) {
                    $mtime = filemtime($file);
                    Cache::setHeaders(true, $days, 'text/html', $mtime);
                    if (Cache::checkNotModified($mtime)) {
                        setDeferredTasks();
                        exit;
                    }
                    $body = str_replace(GEN_MARK, '', $body);
                } elseif ($meta['dyn']) {
                    Cache::setHeaders(false);
                }
                echo getTimedHtml(setDynamicRegions($body));
                setDeferredTasks();
                exit;
            }
        }
        if (!empty($conf['cache_l']) && is_file($file) && !Cache::getRebuildLock($hash)) {
            $body = Cache::getBody($file);
            if ($body !== '') {
                $meta = Cache::getMeta($file, $body);
                if ($meta['dyn']) Cache::setHeaders(false);
                echo getTimedHtml(setDynamicRegions($body));
                setDeferredTasks();
                exit;
            }
        }
        ob_start();
    }
    $licens = getLicenseHtml();
    $strmeta = '<meta charset="'._CHARSET.'">'."\n";
    $strlink = $stscript = '';
    $sep = urldecode($conf['defis']);
    if (!defined('ADMIN_FILE')) {
        $seomap = getSeoRoute($seo);
        $site = filterSeoText((string)($conf['sitename'] ?? ''));
        $slogan = filterSeoText((string)($conf['slogan'] ?? ''));
        $headline = filterSeoText((string)($seo['title'] ?? $site));
        $ctitle = filterSeoText((string)($seo['ctitle'] ?? ''));
        $desc = array_key_exists('desc', $seo) ? filterSeoText((string)$seo['desc']) : ($home ? $slogan : '');
        $author = filterSeoText((string)($seo['author'] ?? ''));
        $time = trim((string)($seo['time'] ?? ''));
        $mtime = trim((string)($seo['mtime'] ?? ''));
        $tstamp = $time !== '' ? strtotime($time) : false;
        $mstamp = $mtime !== '' ? strtotime($mtime) : false;
        $tiso = $tstamp !== false ? date('c', $tstamp) : '';
        $miso = $mstamp !== false ? date('c', $mstamp) : '';
        $logo = rtrim((string)$conf['homeurl'], '/').'/templates/'.$theme.'/images/logos/'.$conf['site_logo'];
        $simg = trim((string)($seo['img'] ?? ''));
        $img = $simg !== '' ? $simg : $logo;
        $kind = $home ? 'website' : trim((string)($seo['kind'] ?? ($seomap['kind'] ?: 'website')));
        $ogmap = ['article' => 'article', 'news' => 'article', 'forum' => 'article', 'product' => 'product', 'profile' => 'profile'];
        $type = $ogmap[$kind] ?? 'website';
        $purl = $seomap['iscanon'] ? $seomap['canon'] : $seomap['siteurl'];
        $title = $headline;
        if ($home) {
            $title = getSeoTitle(mb_strlen($slogan, 'UTF-8') <= 60 ? [$site, $slogan] : [$site], $sep);
        } else {
            if ($conf['ltitle']) {
                $mod = getModuleName($conf['name']);
                $parts = $headline !== $site ? [$headline, $ctitle] : [$ctitle];
                $word = getVar('get', 'word', 'word');
                if ($word !== '') $parts[] = $word;
                $let = getVar('get', 'let', 'let');
                if ($let !== '') $parts[] = $let;
                $num = getVar('get', 'num', 'num');
                if ($num) $parts[] = _PAGE.' '.$num;
                $com = getVar('get', 'com', 'num');
                if ($com) $parts[] = _COMMENTS.' '.$com;
                if ($op == 'best') {
                    $parts[] = _BEST;
                } elseif ($op == 'pop') {
                    $parts[] = _POP;
                } elseif ($op == 'liste') {
                    $parts[] = _LIST;
                } elseif ($op == 'add') {
                    $parts[] = _ADD;
                }
                $parts[] = $mod;
                $parts[] = $site;
                $title = getSeoTitle($parts, $sep);
            }
        }
        $strmeta .= $tpl->getHtmlFrag('head-title', ['title' => $title])."\n";
        if ($author !== '') $strmeta .= $tpl->getHtmlFrag('head-meta', ['name' => 'author', 'content' => $author])."\n";
        if ($desc !== '') $strmeta .= $tpl->getHtmlFrag('head-meta', ['name' => 'description', 'content' => $desc])."\n";
        $strmeta .= $tpl->getHtmlFrag('head-meta', ['name' => 'robots', 'content' => $seomap['robot']])."\n";
        $from = ['[homeurl]', '[site]', '[logo]', '[loc]', '[time]', '[mtime]', '[title]', '[desc]', '[img]', '[ctitle]', '[type]', '[url]', '[headline]', '[author]'];
        $raw = [$conf['homeurl'], $site, $logo, _LOCALE, $tiso, $miso, $title, $desc, $img, $ctitle, $type, $purl, $headline, $author];
        $gvars = array_combine($from, array_map('strval', $raw)) ?: [];
        $jvars = $gvars;
        if (!empty($conf['agraph'])) {
            $graph = ['og:site_name' => $site, 'og:locale' => _LOCALE, 'og:title' => $title, 'og:image' => $img, 'og:type' => $type, 'og:url' => $purl];
            if ($desc !== '') $graph['og:description'] = $desc;
            if (!empty($conf['graph'])) {
                $gset = $conf['derived']['graph'] ?? getSeoGraph((string)$conf['graph'], []);
                foreach ($gset as $gkey => $gval) $gset[$gkey] = str_replace(array_keys($gvars), array_values($gvars), $gval);
                $graph = array_replace($graph, $gset);
            }
            foreach ($graph as $prop => $value) {
                $strmeta .= $tpl->getHtmlFrag('head-meta', [
                    'is_property' => true,
                    'property' => $prop,
                    'content' => $value,
                ])."\n";
            }
        }
        if ($seomap['iscanon']) $strlink .= $tpl->getHtmlFrag('head-link', ['rel' => 'canonical', 'href' => $seomap['canon'], 'type' => '', 'title' => ''])."\n";
        if ($conf['rss']['act']) {
            $fieldc = explode('||', $conf['rss']['rss']);
            foreach ($fieldc as $val) {
                if ($val != '') {
                    $out = explode('|', $val);
                    if ($out[0] != '0' && $out[1] != '0' && $out[2] == '1') $strlink .= $tpl->getHtmlFrag('head-link', ['rel' => 'alternate', 'href' => $out[1], 'type' => 'application/rss+xml', 'title' => $out[0]])."\n";
                }
            }
        }
        $strlink .= $tpl->getHtmlFrag('head-link', ['rel' => 'search', 'href' => $conf['homeurl'].'/index.php?go=search', 'type' => 'application/opensearchdescription+xml', 'title' => $conf['sitename'].' - '._SEARCH])."\n";
        if (!empty($conf['aschema'])) {
            $sdata = [
                'title' => $home ? $site : $headline, 'desc' => $desc, 'url' => $purl, 'img' => $simg, 'author' => $author,
                'time' => $tiso, 'mtime' => $miso, 'site' => $site, 'home' => rtrim((string)$conf['homeurl'], '/'), 'logo' => $logo,
            ];
            $items = [getSeoSchema($kind, $sdata, (bool)$home)];
            if ($home) $items[] = getSeoSchema('website', $sdata);
            $bcid = (int)($seo['cid'] ?? getVar('get', 'cat', 'num'));
            $bread = getSeoBreadcrumbSchema($name, $bcid, $headline, $purl);
            if ($bread) $items[] = $bread;
            if (!empty($conf['schema'])) {
                try {
                    $custom = $conf['derived']['schema'] ?? getSeoJsonItems((string)$conf['schema']);
                    foreach ($custom as $item) $items[] = setSeoJsonVars($item, $jvars);
                } catch (Throwable $e) {
                    static $bad = [];
                    $hash = sha1((string)$conf['schema']);
                    if (!isset($bad[$hash]) && class_exists('Logger')) Logger::addSite('error', 'Invalid Schema.org template', ['error' => $e->getMessage()]);
                    $bad[$hash] = true;
                }
            }
            $extra = $seo['jsonld'] ?? [];
            if (is_array($extra) && isset($extra['@type'])) $extra = [$extra];
            if (is_array($extra)) {
                foreach ($extra as $item) if (is_array($item)) $items[] = $item;
            }
            foreach ($items as $item) {
                $stscript .= $tpl->getHtmlFrag('head-script-inline', [
                    'is_jsonld' => true,
                    'json_html' => getSeoJson($item),
                ])."\n";
            }
        }
    } else {
        $strmeta .= $tpl->getHtmlFrag('head-title', ['title' => $conf['sitename'].' '.$sep.' '._ADMIN])."\n";
    }
    $favicon = 'templates/'.(defined('ADMIN_FILE') ? 'admin' : $theme).'/images/favicon.svg';
    if (is_file(BASE_DIR.'/'.$favicon)) {
        $strlink .= $tpl->getHtmlFrag('head-link', ['rel' => 'shortcut icon', 'href' => $favicon, 'type' => 'image/svg+xml', 'title' => ''])."\n";
    }
    $strlink .= doCss();
    $script = (defined('ADMIN_FILE') || empty($conf['script_b'])) ? doScript()."\n".$stscript : $stscript;
    if (defined('ADMIN_FILE')) {
        $adlogo = basename((string)($conf['admin_logo'] ?? 'slaed_logo_256x73.png'));
        $adpath = getThemeImagePath('logos/'.$adlogo);
        if (!is_file($adpath)) $adpath = getThemeImagePath('logos/slaed_logo_256x73.png');
        $adminvars = [
            'theme' => getTheme(),
            'lang' => substr(_LOCALE, 0, 2),
            'sitename' => $conf['sitename'] ?? '',
            'homeurl' => $conf['homeurl'] ?? '',
            'slogan' => $conf['slogan'] ?? '',
            'meta' => $strmeta,
            'links' => $strlink,
            'scripts' => $script,
            'license' => $licens,
            'head_html' => '',
            'menu' => '',
            'admin_langs' => '',
            'admin_blocks' => '',
            'login' => '',
            'content' => '',
            'foot_html' => '',
            'blocks_left' => '',
            'blocks_right' => '',
            'blocks_down' => '',
            'debug_html' => '',
            'adlogo' => $adpath,
            'adalt' => $conf['sitename'] ?? 'SLAED CMS',
            'adtitle' => $conf['sitename'] ?? 'SLAED CMS',
        ];
        $adminvars = array_replace($adminvars, getAdminLayoutVars(), getThemeHookVars('getAdminHeadVars'));
        $adminpage = isAdmin() ? 'admin' : 'login';
        ob_start();
        return;
    }
    $strig = addSchedulerTrigger();
    if ($strig) {
        $body = 'body:"trigger=pseudo&token='.$strig['token'].'"';
        $init = '{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded"},'.$body.'}';
        $call = 'window.addEventListener("load",function(){window.setTimeout(function(){fetch("'.$strig['url'].'",'.$init.');},1);});';
        $script .= $tpl->getHtmlFrag('head-script-inline', ['js' => $call]);
    }
    $login = '';
    if (is_user()) {
        $uname = htmlspecialchars(substr((string)$user[1], 0, 25), ENT_QUOTES, 'UTF-8');
        $userinfo = getUserInfo();
        $avatar = getUserAvatarUrl($userinfo);
        $items = [
            $tpl->getHtmlFrag('link', ['href' => 'index.php?name=account', 'title' => _ACCOUNT, 'img_src' => $avatar, 'img_alt' => _ACCOUNT, 'label' => $uname, 'is_login_profile' => true, 'is_login_avatar' => true, 'is_bold_label' => true]),
            $tpl->getHtmlFrag('link', ['href' => 'index.php?name=account&op=logout&refer=1', 'title' => _LOGOUT, 'label' => _LOGOUT]),
        ];
        $html = '';
        foreach ($items as $item) {
            $html .= $tpl->getHtmlFrag('list-item', ['content_html' => $item]);
        }
        $login = $tpl->getHtmlFrag('list', ['is_unordered' => true, 'is_login_top' => true, 'is_logged' => true, 'items_html' => $html]);
    } elseif ($conf['users']['enter']) {
        $captcha = getPageCaptcha('login');
        $atok = htmlspecialchars(getPageToken('account'), ENT_QUOTES, 'UTF-8');
        $login = $tpl->getHtmlPart('login-nav', [
            'login'    => _LOGIN,
            'nickname' => _NICKNAME,
            'password' => _PASSWORD,
            'captcha'  => $captcha,
            'token'    => $atok,
            'lost'     => _PASSFOR,
            'register' => _REG,
            'name_field' => ['itype' => 'text', 'name_attr' => 'user_name', 'value_attr' => '', 'maxlength_num' => 25, 'placeholder_text' => _NICKNAME, 'is_required' => true],
            'password_field' => ['itype' => 'password', 'name_attr' => 'user_password', 'value_attr' => '', 'maxlength_num' => 25, 'placeholder_text' => _PASSWORD, 'is_required' => true],
            'submit_button' => ['button_type' => 'submit', 'label' => _LOGIN, 'title' => _LOGIN],
            'lost_link' => ['href' => 'index.php?name=account&op=passlost', 'title' => _PASSFOR, 'label' => _PASSFOR],
            'register_link' => ['href' => 'index.php?name=account&op=newuser', 'title' => _REG, 'label' => _REG, 'is_account_button' => true],
            'token_field' => ['name_attr' => 'token', 'value_attr' => $atok],
            'refer_field' => ['name_attr' => 'refer', 'value_attr' => '1'],
            'op_field' => ['name_attr' => 'op', 'value_attr' => 'login'],
            'oauth_html' => Oauth::getButtons(),
        ]);
    } else {
        $item = $tpl->getHtmlFrag('link', ['href' => 'index.php?name=account', 'title' => _BREG, 'label' => _BREG, 'is_login_button' => true, 'is_bold_label' => true]);
        $login = $tpl->getHtmlFrag('list', ['is_unordered' => true, 'is_login_top' => true, 'items_html' => $tpl->getHtmlFrag('list-item', ['content_html' => $item])]);
    }
    [$logo_w, $logo_h] = $conf['derived']['logo'][$theme] ?? getImageBox(BASE_DIR.'/templates/'.$theme.'/images/logos/'.($conf['site_logo'] ?? ''));
    $sitevars = [
        'theme' => getTheme(),
        'lang' => substr(_LOCALE, 0, 2),
        'sitename' => $conf['sitename'] ?? '',
        'logo' => $conf['site_logo'] ?? '',
        'logo_w' => $logo_w ?: '',
        'logo_h' => $logo_h ?: '',
        'homeurl' => $conf['homeurl'] ?? '',
        'slogan' => $conf['slogan'] ?? '',
        'license' => $licens,
        'meta' => $strmeta,
        'links' => $strlink,
        'scripts' => $script,
        'content' => '',
        'head_html' => $login,
        'head_cid' => (int)($seo['cid'] ?? 0),
        'head_item' => (string)($seo['title'] ?? ''),
        'foot_html' => '',
        'blocks_left' => '',
        'blocks_right' => '',
        'blocks_down' => '',
        'login' => $login,
        'home' => _HOME,
        'account' => _ACCOUNT,
        'album' => _ALBUM,
        'alinks' => _A_LINKS,
        'feedback' => _FEEDBACK,
        'content_label' => _CONTENT,
        'faq' => _FAQ,
        'files' => _FILES,
        'forum' => _FORUM,
        'help' => _HELP,
        'radio' => _RADIO,
        'jokes' => _JOKES,
        'links_label' => _LINKS,
        'media' => _MEDIA,
        'users' => _USERS,
        'news' => _NEWS,
        'order' => _ORDER,
        'pages' => _PAGES,
        'recommend' => _RECOMMEND,
        'rss' => _RSS,
        'search' => _SEARCH,
        'shop' => _SHOP,
        'topusers' => _TOPUSERS,
        'voting' => _VOTING,
        'homepage' => _S_STARTSEITE,
    ];
    $sitevars = array_replace($sitevars, getThemeHookVars('getThemeHeadVars'));
    $sitepage = $home ? 'home' : 'module';
    ob_start();
    updatePoints(1);
    return;
}

# Format foot
# What is stored is what is served: the entry holds the same HTML the first visitor received, with the serve-time markers still in it, so no later visitor is given a different page
# The response that is also handed to the browser cache drops the generation-time marker rather than filling it, because a frozen copy would report the timing of a foreign request
function setFoot(): void {
    global $home, $name, $conf, $tpl, $adminpage, $adminvars, $sitepage, $sitevars, $blocks, $blocks_c, $foot;
    if (defined('ADMIN_FILE')) {
        $vars = is_array($adminvars ?? null) ? $adminvars : [];
        $vars['content'] = getFlashHtml().((ob_get_level() > 0) ? (string)ob_get_clean() : '');
        $time = ($conf['db_t'] == '1') ? GEN_MARK : '';
        $debug = checkDebugView() ? getVariables() : '';
        $vars = array_replace($vars, [
            'time_html' => $time,
            'foot_html' => getFootControls(_PAGETOP, _PAGETOP, '', '', '', true, $debug !== ''),
            'debug_html' => $debug,
        ]);
        $page = (is_string($adminpage ?? '') && $adminpage !== '') ? $adminpage : 'admin';
        echo getTimedHtml($tpl->getHtmlPage($page, $vars, $page === 'login' ? 'bare' : 'admin'));
        unset($adminpage, $adminvars);
        return;
    }
    $vars = is_array($sitevars ?? null) ? $sitevars : [];
    $body = (ob_get_level() > 0) ? (string)ob_get_clean() : '';
    $docache = checkPageCache();
    $time = ($conf['db_t'] == '1') ? GEN_MARK : '';
    $license = !empty($vars['license']) ? (string)$vars['license'] : '';
    getBlocks('f');
    if ($blocks == '' || $blocks == '0' || $blocks == '1') {
        ob_start(); getBlocks('l'); $left = ob_get_clean();
    } else {
        $left = '';
    }
    if ($blocks == '' || $blocks == '0' || $blocks == '2') {
        ob_start(); getBlocks('r'); $right = ob_get_clean();
    } else {
        $right = '';
    }
    if ($blocks_c == '' || $blocks_c == '0' || $blocks_c == '1') {
        ob_start(); getBlocks('c'); $center = ob_get_clean();
    } else {
        $center = '';
    }
    if ($blocks_c == '' || $blocks_c == '0' || $blocks_c == '2') {
        ob_start(); getBlocks('d'); $down = ob_get_clean();
    } else {
        $down = '';
    }
    $msg = ($home == 1) ? setMessageShow() : '';
    $vars = array_replace($vars, [
        'content' => $msg.$center.$body,
        'blocks_left' => $left,
        'blocks_right' => $right,
        'blocks_down' => $down,
    ]);
    $vars = array_replace($vars, getThemeHookVars('getThemeFootVars'));
    $debug = (!$docache && checkDebugView()) ? getVariables() : '';
    $foot = getFootControls(_PAGETOP, _PAGETOP, $time, $license, '', false, $debug !== '');
    $vars = array_replace($vars, [
        'foot_html' => $foot,
        'debug_html' => $debug,
    ]);
    $page = (is_string($sitepage ?? '') && $sitepage !== '') ? $sitepage : ($home ? 'home' : 'module');
    $html = getOutputHtml($tpl->getHtmlPage($page, $vars, $page === 'home' ? 'home' : 'app'));
    unset($sitepage, $sitevars);
    if ($docache && $html !== '' && !checkCachePoison()) {
        $dyn = str_contains($html, '[[sldyn:');
        $file = Cache::getPath('html', getPageHash(), 'html');
        $done = Cache::setBody($file, $html) && Cache::setMeta($file, $html, $dyn);
        $days = (int)$conf['cache_b'];
        if ($done && !$dyn && $days > 0) {
            clearstatcache(true, $file);
            Cache::setHeaders(true, $days, 'text/html', filemtime($file));
            $html = str_replace(GEN_MARK, '', $html);
        }
        if ($dyn && !headers_sent()) Cache::setHeaders(false);
    }
    Cache::setRebuildFree();
    echo getTimedHtml(setDynamicRegions($html));
    while (ob_get_level() > 0) ob_end_flush();
    flush();
    setDeferredTasks();
    exit;
}

# Store one-time flash message in session
function setFlash(string $text, bool $warn = false): void {
    global $conf;
    if ($text === '' || session_status() !== PHP_SESSION_ACTIVE) return;
    $_SESSION[$conf['user_c'].'-flash'] = ['text' => $text, 'warn' => $warn ? 1 : 0];
}

# Render and clear one-time flash message
function getFlashHtml(): string {
    global $conf, $tpl;
    if (session_status() !== PHP_SESSION_ACTIVE) return '';
    $data = $_SESSION[$conf['user_c'].'-flash'] ?? null;
    if (!is_array($data)) return '';
    unset($_SESSION[$conf['user_c'].'-flash']);
    $text = (string)($data['text'] ?? '');
    if ($text === '') return '';
    return $tpl->getHtmlFrag('alert', [
        'is_warn' => !empty($data['warn']),
        'is_flash' => true,
        'alert_attr' => 'data-sl-autohide="5000"',
        'text' => $text,
    ]);
}

# Safe redirect with optional referer fallback
function setRedirect(string $url, bool $refer = false, int $code = 302, string $text = '', bool $warn = false): never {
    if (!in_array($code, [301, 302, 303, 307, 308], true)) $code = 302;
    if ($code === 302 && strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST') $code = 303;
    if ($text !== '') setFlash($text, $warn);
    $target = trim(str_replace(["\r", "\n"], '', $url));
    if ($refer && (isset($_GET['refer']) || isset($_POST['refer']))) {
        $ref = trim(str_replace(["\r", "\n"], '', (string)($_SERVER['HTTP_REFERER'] ?? getenv('HTTP_REFERER') ?? '')));
        $valid = $ref !== '' && !preg_match('#^unknown#i', $ref) && !preg_match('#^bookmark#i', $ref);
        if ($valid) {
            $is_rel = str_starts_with($ref, '/') && !str_starts_with($ref, '//');
            if ($is_rel) {
                $target = $ref;
            } else {
                $rschm = strtolower((string)(parse_url($ref, PHP_URL_SCHEME) ?? ''));
                $rhost = (string)(parse_url($ref, PHP_URL_HOST) ?? '');
                $chost = (string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
                $is_same = in_array($rschm, ['http', 'https'], true) && $rhost !== '' && $chost !== '' && strcasecmp($rhost, $chost) === 0;
                if ($is_same) $target = $ref;
            }
        }
    }
    if ($target === '') $target = '/';
    header('Location: '.$target, true, $code);
    exit;
}

# Highlights text terms inside HTML content
function filterTextHighlight(string $sourse, string $word): string {
    global $tpl;
    $word = filterWord(urldecode($word));
    if (!$word) return $sourse;
    $parts = array_values(array_unique(array_filter(array_map('trim', explode(' ', preg_replace('/\s+/', ' ', trim($word)))))));
    if (!$parts) return $sourse;
    usort($parts, static fn(string $a, string $b): int => strlen($b) - strlen($a));
    preg_match_all('#<[^>]*>#', $sourse, $tags);
    $from = $tags[0];
    $to   = array_map(static fn(int $k): string => "\x00TAG{$k}\x00", array_keys($from));
    $sourse = str_replace($from, $to, $sourse);
    $pattern = '/('.implode('|', array_map(static fn(string $p): string => preg_quote($p, '/'), $parts)).')/iu';
    $sourse = preg_replace_callback($pattern, static function(array $m) use ($tpl): string {
        return $tpl->getHtmlFrag('span', ['is_highlight' => true, 'text' => $m[0]]);
    }, $sourse);
    return str_replace($to, $from, $sourse);
}

# Write, append, or compress file; a short write counts as a failure and an incomplete append is rolled back so callers never see a partial record as success
function addFile(string $file, string $src, string $comp = 'none', bool $del = false, string $mode = 'w', int $max = 10485760): int {
    if (is_file($src)) {
        $data = file_get_contents($src);
        if ($data === false) {
            addErrorFile(_ERR_READ.': '.$src);
            return 1;
        }
    } else {
        $data = $src;
    }
    $done = false;
    if ($mode === 'a') {
        $hand = fopen($file, 'ab');
        if ($hand !== false) {
            if (flock($hand, LOCK_EX)) {
                fseek($hand, 0, SEEK_END);
                $pos = (int)ftell($hand);
                $put = fwrite($hand, $data);
                $done = ($put === strlen($data) && fflush($hand));
                if (!$done) ftruncate($hand, $pos);
                flock($hand, LOCK_UN);
            }
            fclose($hand);
        }
    } else {
        $done = (file_put_contents($file, $data, LOCK_EX) === strlen($data));
    }
    if (!$done) {
        addErrorFile(_ERR_WRITE.': '.$file);
        return 2;
    }
    if ($comp !== 'none') return addCompress(dirname($file), $file, basename($file), $comp, filesize($file) > $max || $del) ? 0 : 3;
    return 0;
}

# Secure recursive directory deletion
function deleteDir(string $dir): bool {
    if (!file_exists($dir)) return false;
    if (!is_dir($dir)) return unlink($dir);
    $files = scandir($dir);
    if ($files === false) return false;
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = realpath($dir.DIRECTORY_SEPARATOR.$file);
        if ($path === false || !deleteDir($path)) return false;
    }
    return rmdir($dir);
}

# Check which compression methods are available
function checkCompress(): array {
    return ['zip' => class_exists('ZipArchive'), 'gz' => function_exists('gzopen'), 'bz2' => function_exists('bzopen')];
}

# Compress a file, folder or string (zip, gz, bz2)
function addCompress(string $dir, string $src, string $name, string $mode = 'auto', bool $del = false, bool $bak = false): bool {
    if (!is_dir($dir) || !is_writable($dir)) {
        addErrorFile(_ERR_DIR.': '.$dir);
        return false;
    }
    if (empty($src) || empty($name)) {
        addErrorFile(_ERR_PARAM);
        return false;
    }
    $name = basename($name);
    $avail = checkCompress();
    $algo = match (strtolower($mode)) {
        'auto' => $avail['zip'] ? 'zip' : ($avail['gz'] ? 'gz' : ($avail['bz2'] ? 'bz2' : 'none')),
        'zip' => 'zip',
        'gz', 'gzip' => 'gz',
        'bz2', 'bzip2' => 'bz2',
        default => 'invalid'
    };
    if ($algo === 'none') {
        if ($bak && is_file($src)) return rename($src, rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name.'.bak');
        addErrorFile(_ERR_NOCOMP);
        return false;
    }
    if ($algo === 'invalid') {
        addErrorFile(_ERR_INVMODE.': '.$mode);
        return false;
    }
    if (!$avail[$algo]) {
        $errmsg = match($algo) { 'zip' => _ERR_ZIPNA, 'gz' => _ERR_GZNA, 'bz2' => _ERR_BZ2NA };
        addErrorFile($errmsg);
        return false;
    }
    $exts = match($algo) {'zip' => '.zip', 'gz' => '.gz', 'bz2' => '.bz2' };
    $nbase = preg_replace('/\.(zip|gz|bz2)$/i', '', $name);
    $file = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$nbase.$exts;

    if ($algo === 'zip') {
        $zip = new ZipArchive();
        $res = $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($res !== true) {
            addErrorFile(_ERR_ZOPEN.': '.$file);
            return false;
        }

        // Handle directory
        if (is_dir($src)) {
            $rit = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            $base = strlen(rtrim($src, DIRECTORY_SEPARATOR)) + 1;

            foreach ($rit as $info) {
                $path = $info->getRealPath();
                $local = substr($path, $base);

                if (!$zip->addFile($path, $local)) {
                    $zip->close();
                    addErrorFile(_ERR_ZADD.': '.$path);
                    return false;
                }
            }
        }
        // Handle file
        elseif (is_file($src)) {
            if (!$zip->addFile($src, basename($src))) {
                $zip->close();
                addErrorFile(_ERR_ZADD.': '.$src);
                return false;
            }
        }
        // Handle string content
        else {
            $iname = $nbase.'.txt';
            if (!$zip->addFromString($iname, $src)) {
                $zip->close();
                addErrorFile(_ERR_ZADD.': '.$iname);
                return false;
            }
        }

        $zip->close();

        // Delete source if requested
        if ($del) {
            if (is_file($src)) {
                if (!unlink($src)) addErrorFile(_ERR_DELETE.': '.$src);
            } elseif (is_dir($src)) {
                if (!deleteDir($src)) {
                    addErrorFile(_ERR_DELETE.': '.$src);
                    return false;
                }
            }
        }

        return true;
    }
    
    // GZ and BZ2 only support single files
    if (!is_file($src)) {
        addErrorFile(_ERR_FILE.': '.$src);
        return false;
    }

    $srcf = fopen($src, 'rb');
    if (!$srcf) {
        addErrorFile(_ERR_OPEN.': '.$src);
        return false;
    }

    if ($algo === 'gz') {
        $zipf = gzopen($file, 'wb');
        if (!$zipf) {
            fclose($srcf);
            addErrorFile(_ERR_GZIP.': '.$file);
            return false;
        }

        while (!feof($srcf)) {
            $chunk = fread($srcf, 65536);
            if ($chunk === false) {
                gzclose($zipf);
                fclose($srcf);
                addErrorFile(_ERR_READ.': '.$src);
                return false;
            }
            if (gzwrite($zipf, $chunk) === false) {
                gzclose($zipf);
                fclose($srcf);
                addErrorFile(_ERR_GZIP.': Write failed');
                return false;
            }
        }

        gzclose($zipf);
        fclose($srcf);
    }
    elseif ($algo === 'bz2') {
        $zipf = bzopen($file, 'w');
        if (!$zipf) {
            fclose($srcf);
            addErrorFile(_ERR_BZIP.': '.$file);
            return false;
        }

        while (!feof($srcf)) {
            $chunk = fread($srcf, 65536);
            if ($chunk === false) {
                bzclose($zipf);
                fclose($srcf);
                addErrorFile(_ERR_READ.': '.$src);
                return false;
            }
            if (bzwrite($zipf, $chunk) === false) {
                bzclose($zipf);
                fclose($srcf);
                addErrorFile(_ERR_BZIP.': Write failed');
                return false;
            }
        }

        bzclose($zipf);
        fclose($srcf);
    }
    else {
        fclose($srcf);
        addErrorFile(_ERR_TYPE.': '.$algo);
        return false;
    }

    // Delete source if requested
    if ($del) {
        if (!unlink($src)) addErrorFile(_ERR_DELETE.': '.$src);
    }

    return true;
}

# Add file errors to error_file.log
function addErrorFile(string $msg): bool {
    return class_exists('Logger') ? Logger::addFile('error', $msg) : false;
}

# Verify a captcha submission for the given action; true means the request must be blocked
function checkCaptcha(string $act): bool {
    return Captcha::check($act);
}

# Build the module categories block: fluid tiles with tinted icon, aggregated per-category count and subcategory chips
function setCategories(string $mod, int $sub, bool $desc, string $id = ''): string {
 global $db, $conf, $locale, $tpl;
    if (!filterVar($mod)) return '';
    $id = intval($id) ?: 0;
    $params = ['mod' => $mod];
    if ($id) {
        $where = 'WHERE modul = :mod AND parent = :pid';
        $params['pid'] = $id;
    } elseif ($conf['multilingual']) {
        $where = "WHERE modul = :mod AND (lang = :loc OR lang = '')";
        $params['loc'] = $locale;
    } else {
        $where = 'WHERE modul = :mod';
    }
    $massiv = [];
    $result = $db->getSqlQuery('SELECT id, title, intro, img, parent, pview, pread, ordern FROM '.PREFIX_DB.'_categories '.$where.' ORDER BY ordern, title', $params);
    while (list($cid, $title, $intro, $img, $parentid, $pview, $pread, $ordern) = $db->getSqlRow($result)) {
        $massiv[] = [$cid, $title, $intro, $img, $parentid, $pview, $pread, $ordern];
    }
    if (!$massiv) return '';
    $catid = [];
    foreach ($massiv as $val) {
        if ($val[4] == $id && is_acess($val[5])) {
            $catid[] = (int)$val[0];
            foreach ($massiv as $sval) {
                if ($val[0] == $sval[4] && is_acess($sval[5])) $catid[] = (int)$sval[0];
            }
        }
    }
    $catid = array_values(array_filter(array_unique($catid), static fn($v) => $v > 0));
    if (!$catid) return '';
    [$counts, $total, $in] = getCategoryCounts($mod, $catid);
    $cont = '';
    foreach ($massiv as $val) {
        if ($val[4] == $id && is_acess($val[5])) {
            $name = getConst($val[1]);
            $hidden = !is_acess($val[6]);
            $num = $counts[(int)$val[0]] ?? 0;
            $subs = [];
            foreach ($massiv as $sval) {
                if ($val[0] == $sval[4] && is_acess($sval[5])) {
                    $num += $counts[(int)$sval[0]] ?? 0;
                    if ($sub == 1 && is_acess($sval[6])) {
                        $sname = getConst($sval[1]);
                        $subs[] = ['href' => getSeoUrl(['name' => $mod, 'cat' => $sval[0]]), 'title' => $sname, 'name' => $sname];
                    }
                }
            }
            $cont .= $tpl->getHtmlFrag('category-row', [
                'is_hidden' => $hidden,
                'tone' => (int)$val[7] % 6,
                'href' => $hidden ? '' : getSeoUrl(['name' => $mod, 'cat' => $val[0]]),
                'title' => $hidden ? $name.' - '._CCLOSED : $name,
                'name' => $name,
                'icon_name' => preg_match('/^[a-z0-9-]+$/', (string)$val[3]) ? $val[3] : 'folder',
                'count' => $num ? (string)$num : '',
                'description' => $desc ? getConst($val[2]) : '',
                'subs' => $subs,
            ]);
        }
    }
    if (!$cont) return '';
    return $tpl->getHtmlPart('categories', ['categories' => _CATEGORIES, 'content' => $cont, 'total' => _ALLIN, 'pages' => $total, 'in' => $in, 'cat' => count($massiv), 'category' => _ALLINC, 'mod' => $mod]);
}

# Per-category published-material counts for a module (grouped by cid) plus the summed total and the unit label
function getCategoryCounts(string $mod, array $catid): array {
 global $db, $user;
    switch ($mod) {
        case 'faq':   $table = 'faq';      $cond = "time <= NOW() AND status != '0'"; $in = _INFA; break;
        case 'files': $table = 'files';    $cond = "time <= NOW() AND status != '0'"; $in = _INF;  break;
        case 'help':  $table = 'help';     $cond = "time <= NOW() AND pid = '0' AND uid = :uid"; $in = _INH; break;
        case 'jokes': $table = 'jokes';    $cond = "time <= NOW() AND status != '0'"; $in = _INJ;  break;
        case 'links': $table = 'links';    $cond = "time <= NOW() AND status != '0'"; $in = _INL;  break;
        case 'media': $table = 'media';    $cond = "time <= NOW() AND status != '0'"; $in = _INM;  break;
        case 'news':  $table = 'news';     $cond = "time <= NOW() AND status != '0'"; $in = _INN;  break;
        case 'pages': $table = 'pages';    $cond = "time <= NOW() AND status != '0'"; $in = _INP;  break;
        case 'shop':  $table = 'products'; $cond = "time <= NOW() AND status != '0'"; $in = _INS;  break;
        default: return [[], 0, ''];
    }
    $ph = [];
    $pm = [];
    foreach ($catid as $k => $v) {
        $ph[] = ':c'.$k;
        $pm['c'.$k] = (int)$v;
    }
    if ($mod === 'help') $pm['uid'] = is_user() ? intval($user[0]) : 0;
    $res = $db->getSqlQuery('SELECT cid, COUNT(id) FROM '.PREFIX_DB.'_'.$table.' WHERE cid IN ('.implode(', ', $ph).') AND '.$cond.' GROUP BY cid', $pm);
    $counts = [];
    $total = 0;
    while ([$ccid, $cn] = $db->getSqlRow($res)) {
        $counts[(int)$ccid] = (int)$cn;
        $total += (int)$cn;
    }
    return [$counts, $total, $in];
}

# Load configuration file or directory and return chmod warning if needed
function checkPerms(string $fp): string {
    global $tpl;
    $perm = is_dir($fp) ? 777 : 666;
    $info = checkFileChmod($fp, $perm);
    return ($info !== '') ? $tpl->getHtmlFrag('alert', ['text' => $info, 'meta' => '', 'type' => 'warn', 'is_warn' => true]) : '';
}

# Check file chmod permission and try to fix it (Linux only)
function checkFileChmod(string $dir, int $chm): string {
    $out = '';
    if (file_exists($dir) && $chm > 0) {
        $per=substr(decoct(fileperms($dir)), -3);
        if (php_uname('s') === 'Linux' && $per != $chm) {
            $tdir = CONFIG_DIR.'/chmod.php';
            $mode = octdec((string)$chm);
            $uid = function_exists('posix_geteuid') ? (int)posix_geteuid() : -1;
            if (file_put_contents($tdir, '') !== false) {
                $own = (int)fileowner($tdir);
                $can = ($uid > -1) ? ($own === $uid) : is_writable($tdir);
                if ($can && is_writable($tdir)) chmod($tdir, $mode);
                $tper = substr(decoct(fileperms($tdir)), -3);
                if ($tper == $chm) {
                    $down = (int)fileowner($dir);
                    $cdir = ($uid > -1) ? ($down === $uid) : is_writable($dir);
                    if ($cdir && is_writable($dir)) chmod($dir, $mode);
                    $per = substr(decoct(fileperms($dir)),-3);
                }
                unlink($tdir);
            }
        }
        $out = ($per != $chm) ? $dir.' '._ERRORPERM.' CHMOD - '.$chm : '';
    }
    return $out;
}

# Saving configurations to a file
# One value is one line however long it is: this file is stored data, not code anybody reads, and the line limit of .rules/global.md governs what a person writes
# Line endings are normalized on the way in, because a textarea posts CRLF and a stored configuration is a source file of this repository, which is LF only
# The output is deterministic, so a save of an unchanged configuration reproduces the file byte for byte and the round trip the settings tabs rely on still holds
function setConfigFile(string $fp, array $arr, array $act = []): void {
    static $reserved = ['system.php', 'header.php', 'chmod.php', 'local.php'];
    if (in_array($fp, $reserved)) return;
    $fp = CONFIG_DIR.'/'.$fp;
    if (!empty($act)) $arr = array_replace_recursive($act, $arr);
    ksort($arr);
    $norm = function ($val) use (&$norm) {
        if (is_array($val)) {
            foreach ($val as $k => $vv) $val[$k] = $norm($vv);
            return $val;
        }
        if (is_bool($val)) return (string)(int)$val;
        return str_replace(["\r\n", "\r"], "\n", (string)$val);
    };
    foreach ($arr as $key => $val) $arr[$key] = $norm($val);
    $key  = pathinfo(basename($fp), PATHINFO_FILENAME);
    $data = ($key === 'global') ? $arr : [$key => $arr];
    $export = function (array $arr, int $dep = 0) use (&$export): string {
        $pad = str_repeat('    ', $dep);
        $ind = $pad.'    ';
        $out = '['."\n";
        foreach ($arr as $key => $val) {
            $body = is_array($val) ? $export($val, $dep + 1) : var_export($val, true);
            $out .= $ind.var_export($key, true).' => '.$body.','."\n";
        }
        return $out.$pad.']';
    };
    $cnt = '<?php'."\n"
    .'# Author: Eduard Laas'."\n"
    .'# 2005 - '.date('Y').' SLAED'."\n"
    .'# License: MIT'."\n"
    .'# Website: slaed.net'."\n\n"
    .'return '.$export($data).';'."\n";
    file_put_contents($fp, $cnt, LOCK_EX);
    if (is_file(CONFIG_DIR.'/local.php')) unlink(CONFIG_DIR.'/local.php');
    getConfig();
}

# Returns list of asset files found in standard theme subdirectories
function getThemeAssets(string $theme, string $ext): array {
    $base = 'templates/'.$theme.'/';
    $out = [];
    foreach (glob($base.'*.'.$ext) ?: [] as $file) $out[] = $file;
    foreach (glob($base.'assets/vendor/*/', GLOB_ONLYDIR) ?: [] as $sub) {
        foreach (glob($sub.'*.'.$ext) ?: [] as $file) $out[] = $file;
        foreach (glob($sub.'*/', GLOB_ONLYDIR) ?: [] as $subsub) {
            foreach (glob($subsub.'*.'.$ext) ?: [] as $file) $out[] = $file;
        }
    }
    foreach (glob($base.'assets/'.$ext.'/*.'.$ext) ?: [] as $file) $out[] = $file;
    if ($ext === 'js') {
        foreach (glob($base.'js/*.'.$ext) ?: [] as $file) $out[] = $file;
    }
    return array_values(array_unique($out));
}

# Resolves asset config entries that may point to files or directories
function getAssetFiles(array $entries, string $ext): array {
    $out = [];
    foreach ($entries as $entry) {
        $entry = trim((string)$entry);
        if ($entry === '') continue;
        if (is_file($entry) && strtolower(pathinfo($entry, PATHINFO_EXTENSION)) === $ext) {
            $out[] = $entry;
            continue;
        }
        if (is_dir($entry)) {
            foreach (glob(rtrim($entry, '/\\').'/*.'.$ext) ?: [] as $file) {
                if (is_file($file)) $out[] = $file;
            }
        }
    }
    return array_values(array_unique($out));
}

# Definition and processing of header scripts files
# Concatenated sources are separated by a semicolon and a line break, never by a space: a file ending in a line comment without a break would swallow the next one,
# and a statement left without its own semicolon would join the first line of the following file instead of ending where its author ended it
function doScript(): string {
    global $theme, $conf, $tpl;
    $async = ($conf['script_a']) ? 'async ' : '';
    $drv = $conf['derived']['assets'][$theme] ?? null;
    if ($drv !== null) {
        $array = $drv['js'];
    } else {
        $entries = explode(',', $conf['script_f']);
        $array = array_values(array_unique(array_merge(getAssetFiles($entries, 'js'), getThemeAssets($theme, 'js'))));
    }
    $arr = [];
    $cont = '';
    if (!defined('ADMIN_FILE')) {
        $sfile = '';
        $route = '';
        if ($conf['cache_script']) {
            $fp = $drv['jsfp'] ?? sha1(serialize(array_map(static fn($file) => is_file($file) ? filemtime($file).':'.filesize($file) : '0:0', array_combine($array, $array) ?: [])));
            $hash = Cache::getHash(['assets-v'.ASSETS_VER, $theme, 'js', $fp, $conf['script_h'], $conf['script_a']]);
            $sfile = Cache::getPath('assets', $hash, 'js');
            $route = 'index.php?go=asset&file='.$hash.'&type=js';
        }
        if ($conf['cache_script'] && Cache::isFresh($sfile, $conf['cache_t'])) {
            $cont = ($conf['script_h']) ? Cache::getBody($sfile) : $tpl->getHtmlFrag('head-script-src', ['src' => $route, 'attr' => trim($async)]);
        } else {
            foreach ($array as $file) {
                if (file_exists($file)) {
                    if ($conf['cache_script'] || $conf['script_h']) {
                        $arr[] = file_get_contents($file);
                    } else {
                        $arr[] = $tpl->getHtmlFrag('head-script-src', ['src' => $file, 'attr' => trim($async)]);
                    }
                }
            }
            $bond = ($conf['cache_script'] || $conf['script_h']) ? implode(";\n", $arr) : implode("\n", $arr);
            $cont = ($conf['script_h']) ? $tpl->getHtmlFrag('head-script-inline', ['js' => $bond]) : $bond;
            if ($conf['cache_script']) {
                Cache::setBody($sfile, $cont);
                $cont = (is_file($sfile) && !$conf['script_h']) ? $tpl->getHtmlFrag('head-script-src', ['src' => $route, 'attr' => trim($async)]) : $cont;
            }
        }
        if (file_exists(CONFIG_DIR.'/header.php')) {
            ob_start();
            include CONFIG_DIR.'/header.php';
            $cont .= ob_get_clean();
        }
    } else {
        foreach ($array as $file) {
            if (file_exists($file)) {
                $arr[] = $tpl->getHtmlFrag('head-script-src', ['src' => $file, 'attr' => trim($async)]);
            }
        }
        $cont = implode("\n", $arr);
    }
    return $cont;
}

# Definition and processing of CSS files
function doCss(): string {
    global $theme, $conf, $tpl;
    $drv = $conf['derived']['assets'][$theme] ?? null;
    if ($drv !== null) {
        $array = $drv['css'];
    } else {
        $entries = explode(',', str_replace('[theme]', $theme, $conf['css_f']));
        $array = array_values(array_unique(array_merge(getAssetFiles($entries, 'css'), getThemeAssets($theme, 'css'))));
    }
    $arr = [];
    $cont = '';
    if (!defined('ADMIN_FILE')) {
        $bundle = !empty($conf['cache_css']) || !empty($conf['css_h']);
        $cfile = '';
        $route = '';
        if ($bundle) {
            $fp = $drv['cssfp'] ?? sha1(serialize(array_map(static fn($file) => is_file($file) ? filemtime($file).':'.filesize($file) : '0:0', array_combine($array, $array) ?: [])));
            $hash = Cache::getHash(['assets-v'.ASSETS_VER, $theme, 'css', $fp, $conf['css_c'], $conf['css_h'], $conf['css_e']]);
            $cfile = Cache::getPath('assets', $hash, 'css');
            $route = 'index.php?go=asset&file='.$hash.'&type=css';
        }
        if ($bundle && Cache::isFresh($cfile, $conf['cache_t'])) {
            $cont = $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => $route, 'type' => '', 'title' => '']);
        } else {
            foreach ($array as $file) {
                if (file_exists($file)) {
                    if ($bundle) {
                        $dir = rtrim(str_replace('\\', '/', dirname($file)), '/').'/';
                        $cont = file_get_contents($file);
                        $cont = preg_replace_callback(
                            '#url\((\'|"|)((?!data:|https?:|//|/).*?)(\'|"|)\)#i',
                            function(array $m) use ($dir): string {
                                $parts = explode('/', $dir.$m[2]);
                                $out = [];
                                foreach ($parts as $part) {
                                    if ($part === '..') array_pop($out);
                                    elseif ($part !== '' && $part !== '.') $out[] = $part;
                                }
                                return 'url('.$m[1].implode('/', $out).$m[3].')';
                            },
                            $cont
                        );
                        if ($conf['css_e']) $cont = preg_replace_callback('#url\((.*?\.(png|jpg|jpeg|gif|svg|bmp))\)#i', 'getImgEncode', $cont);
                        $arr[] = ($conf['css_c'] && !str_contains(basename($file), '.min.')) ? getCompressCss($cont) : $cont;
                    } else {
                        $arr[] = $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => $file, 'type' => '', 'title' => '']);
                    }
                }
            }
            $cont = $bundle ? implode(' ', $arr) : implode("\n", $arr);
            if ($bundle) {
                Cache::setBody($cfile, $cont);
                $cont = is_file($cfile) ? $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => $route, 'type' => '', 'title' => '']) : '';
            }
        }
    } else {
        foreach ($array as $file) {
            if (file_exists($file)) {
                $arr[] = $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => $file, 'type' => '', 'title' => '']);
            }
        }
        $cont = implode("\n", $arr);
    }
    return $cont;
}

# Create a sitemap
function addSitemapTask(bool $force = false): array {
 global $db, $conf, $tpl;
    if ($force || defined('ADMIN_FILE') || !empty($conf['sitemap']['auto'])) {
        $sess_f = BASE_DIR.'/sitemap.xml';
        $sess_b = (file_exists($sess_f) && filesize($sess_f) != 0) ? filemtime($sess_f) : 0;
        $past = time() - intval($conf['sitemap']['auto_t'] ?? 0);
        if ($force || defined('ADMIN_FILE') || $sess_b < $past) {
            $date = date('Y-m-d');
            $info = $htm = $cd = [];
            $modules_raw = (string)($conf['sitemap']['mod'] ?? '');
            $mod = array_values(array_filter(array_map('trim', explode(',', $modules_raw)), static fn(string $one): bool => $one !== '' && $one !== '0'));
            if (!$mod) return ['status' => 'disabled', 'message' => 'Sitemap has no modules selected, the existing map was left untouched'];
            for ($i = 0; $i < count($mod); $i++) {
                if ($mod[$i] == 'account' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery('SELECT id, name, lastvis FROM '.PREFIX_DB.'_users');
                    while (list($id, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, '', $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'content' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB.'_content WHERE time <= NOW()');
                    while (list($id, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, '', $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'faq' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery('SELECT id, cid, title, time FROM '.PREFIX_DB."_faq WHERE time <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'files' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery('SELECT id, cid, title, time FROM '.PREFIX_DB."_files WHERE time <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'forum' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery('SELECT id, cid, title, time FROM '.PREFIX_DB."_forum WHERE pid = '0' AND time <= NOW() AND status > '1'");
                    while (list($id, $cat, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'jokes' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery('SELECT id, time, title, cid FROM '.PREFIX_DB."_jokes WHERE time <= NOW() AND status != '0'");
                    while (list($id, $time, $title, $cat) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'links' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery('SELECT id, cid, title, time FROM '.PREFIX_DB."_links WHERE time <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'media' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery('SELECT id, cid, title, subtitle, time FROM '.PREFIX_DB."_media WHERE time <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $subtitle, $time) = $db->getSqlRow($result)) {
                        $title = ($subtitle) ? $title.' - '.$subtitle : $title;
                        $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                    }
                } elseif ($mod[$i] == 'news' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery('SELECT id, cid, title, time FROM '.PREFIX_DB."_news WHERE time <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'pages' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery('SELECT id, cid, title, time FROM '.PREFIX_DB."_pages WHERE time <= NOW() AND status != '0'");
                    while (list($id, $cat, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'shop' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery('SELECT id, cid, time, title FROM '.PREFIX_DB."_products WHERE time <= NOW() AND status != '0'");
                    while (list($id, $cat, $time, $title) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, $cat, $title, $time, $mod[$i]];
                } elseif ($mod[$i] == 'voting' && is_active($mod[$i], '0')) {
                    $result = $db->getSqlQuery('SELECT id, title, time FROM '.PREFIX_DB."_voting WHERE modul = '' AND time <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')");
                    while (list($id, $title, $time) = $db->getSqlRow($result)) $info[$mod[$i]][] = [$id, '', $title, $time, $mod[$i]];
                } elseif (is_active($mod[$i], '0')) {
                    $info[$mod[$i]][] = ['', '', '', '', $mod[$i]];
                }
            }
            $map_h = $map_m = $map_c = $map_p = '';
            if (count($info) > 0) {
                foreach ($info as $key => $val) {
                    if ($conf['sitemap']['gen_m']) {
                        $map_m .= '<url><loc>'.getPublicUrl(['name' => $key]).'</loc>';
                        $map_m .= $conf['sitemap']['dat_m'] ? '<lastmod>'.$date.'</lastmod>' : '';
                        $map_m .= $conf['sitemap']['fr_m'] ? '<changefreq>'.$conf['sitemap']['fr_m'].'</changefreq>' : '';
                        $map_m .= $conf['sitemap']['pr_m'] ? '<priority>'.$conf['sitemap']['pr_m'].'</priority>' : '';
                        $map_m .= '</url>'."\n";
                    }
                    foreach ($info[$key] as $key2 => $val2) {
                        if ($conf['sitemap']['gen_p'] && $info[$key][$key2][0]) {
                            $map_p .= '<url><loc>'.getPublicUrl([
                                'name' => $info[$key][$key2][4],
                                'op' => 'view',
                                'id' => $info[$key][$key2][0],
                            ]).'</loc>';
                            $map_p .= $conf['sitemap']['dat_p'] ? '<lastmod>'.format_time($info[$key][$key2][3], 'Y-m-d').'</lastmod>' : '';
                            $map_p .= $conf['sitemap']['fr_p'] ? '<changefreq>'.$conf['sitemap']['fr_p'].'</changefreq>' : '';
                            $map_p .= $conf['sitemap']['pr_p'] ? '<priority>'.$conf['sitemap']['pr_p'].'</priority>' : '';
                            $map_p .= '</url>'."\n";
                        }
                        $htm[$key][$info[$key][$key2][1]][] = [$info[$key][$key2][0],$info[$key][$key2][2]];
                    }
                    $result = $db->getSqlQuery('SELECT id, modul, title, parent FROM '.PREFIX_DB.'_categories WHERE modul = :mod', ['mod' => $key]);
                    while (list($cid, $cmodul, $title, $parentid) = $db->getSqlRow($result)) {
                        $cd[$cid] = [$cid, $parentid, $title, $cmodul];
                        if ($conf['sitemap']['gen_c']) {
                            $map_c .= '<url><loc>'.getPublicUrl(['name' => $cmodul, 'cat' => $cid]).'</loc>';
                            $map_c .= $conf['sitemap']['dat_c'] ? '<lastmod>'.$date.'</lastmod>' : '';
                            $map_c .= $conf['sitemap']['fr_c'] ? '<changefreq>'.$conf['sitemap']['fr_c'].'</changefreq>' : '';
                            $map_c .= $conf['sitemap']['pr_c'] ? '<priority>'.$conf['sitemap']['pr_c'].'</priority>' : '';
                            $map_c .= '</url>'."\n";
                        }
                    }
                }
            }
            if ($conf['sitemap']['txt']) {
                $bufferItems = '';
                foreach ($htm as $key => $val) {
                    $moduleLink = $tpl->getHtmlFrag('link', [
                        'href' => getSeoUrl(['name' => $key]),
                        'title' => getModuleName($key),
                        'label' => getModuleName($key),
                    ]);
                    $moduleChildren = '';
                    if (count($htm[$key]) > 0) {
                        $categoryItems = '';
                        $publicLists = '';
                        foreach ($htm[$key] as $key2 => $val2) {
                            $categoryLink = isset($cd[$key2][2]) ? $tpl->getHtmlFrag('link', [
                                'href' => getSeoUrl(['name' => $key, 'cat' => $key2]),
                                'title' => $cd[$key2][2],
                                'label' => $cd[$key2][2],
                            ]) : '';
                            $viewItems = '';
                            if (count($htm[$key][$key2]) > 0) {
                                foreach ($htm[$key][$key2] as $key3 => $val3) {
                                    if ($htm[$key][$key2][$key3][0]) {
                                        $viewLink = $tpl->getHtmlFrag('link', [
                                            'href' => getSeoUrl(['name' => $key, 'op' => 'view', 'id' => $htm[$key][$key2][$key3][0]]),
                                            'title' => $htm[$key][$key2][$key3][1],
                                            'label' => $htm[$key][$key2][$key3][1],
                                        ]);
                                        $viewItems .= $tpl->getHtmlFrag('list-item', ['content_html' => $viewLink]);
                                    }
                                }
                            }
                            $viewList = $viewItems ? $tpl->getHtmlFrag('list', ['items_html' => $viewItems, 'is_sub_two' => true]) : '';
                            if ($categoryLink) {
                                $categoryItems .= $tpl->getHtmlFrag('list-item', ['content_html' => $categoryLink, 'children_html' => $viewList]);
                            } else {
                                $publicLists .= $viewList;
                            }
                        }
                        $moduleChildren = $categoryItems
                            ? $tpl->getHtmlFrag('list', ['items_html' => $categoryItems, 'is_sub' => true])
                            : $publicLists;
                    }
                    $bufferItems .= $tpl->getHtmlFrag('list-item', ['content_html' => $moduleLink, 'children_html' => $moduleChildren]);
                }
                $buffer = $tpl->getHtmlFrag('list', ['items_html' => $bufferItems]);
                $sdir = dirname(SITEMAP_DIR.'/sitemap.txt');
                if (!is_dir($sdir) && !mkdir($sdir, 0777, true) && !is_dir($sdir)) {
                    return [
                        'status' => 'failed',
                        'message' => 'Sitemap storage directory is unavailable',
                    ];
                }
                file_put_contents(SITEMAP_DIR.'/sitemap.txt', $buffer);
            }
            if ($conf['sitemap']['gen_h']) {
                $map_h = '<url><loc>'.getPublicUrl().'</loc>';
                $map_h .= ($conf['sitemap']['dat_h']) ? '<lastmod>'.$date.'</lastmod>' : '';
                $map_h .= ($conf['sitemap']['fr_h']) ? '<changefreq>'.$conf['sitemap']['fr_h'].'</changefreq>' : '';
                $map_h .= ($conf['sitemap']['pr_h']) ? '<priority>'.$conf['sitemap']['pr_h'].'</priority>' : '';
                $map_h .= '</url>'."\n";
            }
            $map = $map_h.$map_m.$map_c.$map_p;
            $array = explode("\n", $map);
            # Maximum number of links
            $max = 50000;
            # Maximum size in bytes
            $size = 10485760;
            if (count($array) > $max) {
                $i = 1;
                $links = '';
                foreach (array_chunk($array, $max, true) as $sitemap) {
                    $urls = '';
                    foreach ($sitemap as $val) $urls .= empty($val) ? '' : $val."\n";
                    $cont = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
                    $cont .= ($conf['sitemap']['xsl'] && file_exists(SITEMAP_DIR.'/sitemap.xsl')) ? '<?xml-stylesheet type="text/xsl" href="'.$conf['homeurl'].'/index.php?go=xsl"?>'."\n" : '';
                    $cont .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".$urls.'</urlset>';
                    $file = BASE_DIR.'/sitemap-'.$i.'.xml';
                    file_put_contents($file, $cont);
                    $i++;
                    if (strlen($cont) >= $size && checkCompress()['gz'] && file_exists($file)) {
                        if (addCompress(dirname($file), $file, basename($file), 'gz', true)) {
                            $file = $file.'.gz';
                        }
                    }
                    $links .= '<sitemap><loc>'.$conf['homeurl'].'/'.basename($file).'</loc><lastmod>'.$date.'</lastmod></sitemap>'."\n";
                }
                $set = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".$links.'</sitemapindex>';
            } else {
                $set = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".$map.'</urlset>';
            }
            $cont = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            $cont .= ($conf['sitemap']['xsl'] && file_exists(SITEMAP_DIR.'/sitemap.xsl')) ? '<?xml-stylesheet type="text/xsl" href="'.$conf['homeurl'].'/index.php?go=xsl"?>'."\n".$set : $set;
            if ($conf['rewrite']) {
                $cont = str_replace($conf['homeurl'].'/', '', $cont);
                $cont = preg_replace('#<loc>(.*?)</loc>#is', '<loc>'.$conf['homeurl'].'/\\1</loc>', $cont);
            }
            file_put_contents(BASE_DIR.'/sitemap.xml', $cont);
            return [
                'status' => 'success',
                'message' => 'Sitemap generation completed',
                'extra' => [
                    'last_map_size' => file_exists(BASE_DIR.'/sitemap.xml') ? (int)filesize(BASE_DIR.'/sitemap.xml') : 0,
                    'last_url_count' => count(array_filter($array, 'strlen')),
                    'last_output' => 'sitemap.xml',
                ],
            ];
        }
    }
    return ['status' => 'idle', 'message' => 'Sitemap generation was skipped'];
}

# Navigation tabs (compact, synchronized & sequential IDs)
function getNaviTabs(int $id = 0, string $pref = '', array $tabs = [], array $conts = []): string {
    global $tpl;
    $tabs = is_array($tabs) ? $tabs : [];
    $conts = is_array($conts) ? $conts : [];
    $cnt = 0;
    $pairs = array_filter(array_map(
        function($k, $t, $c) use (&$cnt) {
            if (!empty($t) && !empty($c)) {
                $p = ['id' => $cnt, 'tab' => $t, 'cont' => $c];
                $cnt++;
                return $p;
            }
            return null;
        },
        array_keys($tabs),
        $tabs,
        $conts
    ));
    if (!$pairs) return '';
    static $seq = 0;
    $gid = 'sl-tabs-'.preg_replace('/[^a-z0-9_-]/i', '', $pref.$id).'-'.(++$seq);
    $tlinks = implode('', array_map(static function($p) use ($tpl, $gid): string {
        $args = [
            'href' => '#',
            'is_active' => $p['id'] === 0,
            'rel' => $gid.'-'.$p['id'],
            'title' => strip_tags((string)$p['tab']),
        ];
        $key = preg_match('/<[^>]+>/', (string)$p['tab']) ? 'label_html' : 'label';
        $args[$key] = (string)$p['tab'];
        return $tpl->getHtmlFrag('tabs-link', $args);
    }, $pairs));
    $cdivs = implode('', array_map(static fn($p): string => $tpl->getHtmlFrag('tabs-panel', ['panel_id' => $gid.'-'.$p['id'], 'content_html' => $p['cont']]), $pairs));
    return $tpl->getHtmlPart('tabs', ['id' => $gid, 'is_runtime' => true, 'tabs_html' => $tlinks, 'content_html' => $cdivs]);
}

# Transliteration
function getTranslit(string $st, string $lo = ''): string {
    $st = strtr($st, ['а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'g', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'ы' => 'i', 'э' => 'e', 'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ж' => 'G', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Ы' => 'I', 'Э' => 'E', 'ё' => 'yo', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ь' => '', 'ъ' => '', 'ю' => 'yu', 'я' => 'ya', 'Ё' => 'Yo', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch', 'Ь' => '', 'Ъ' => '', 'Ю' => 'Yu', 'Я' => 'Ya']);
    $st = empty($lo) ? $st : mb_strtolower($st);
    $st = preg_replace('#[^a-zA-Z0-9]#', '', $st);
    $st = trim($st);
    return $st;
}

# Render the captcha block for a form action (empty when not required); a live captcha inside a cacheable build poisons the page cache
function getCaptcha(string $act): string {
    if (!defined('ADMIN_FILE') && checkPageCache()) checkCachePoison(true);
    return Captcha::html($act);
}

# Serve a captcha challenge as JSON for the given action
function getCaptchaChallenge(string $act): void {
    Captcha::challenge($act);
}

# Convert image to base64
function getImgEncode(array $img): string {
    if (file_exists($img[1]) && filesize($img[1]) <= 10240) {
        $type = pathinfo($img[1], PATHINFO_EXTENSION);
        static $argc, $cach;
        if ($argc != $img[1] || !isset($cach)) {
            $argc = $img[1];
            $cach = base64_encode(file_get_contents($argc));
        }
        $cont = 'url(data:image/'.$type.';base64,'.$cach.')';
    } else {
        $cont = 'url('.$img[1].')';
    }
    return $cont;
}

# Resolve intrinsic [width, height] for CLS-safe image attributes; [0, 0] when unknown
function getImageBox(string $file): array {
    if (!is_file($file)) return [0, 0];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext === 'svg') {
        $svg = (string)file_get_contents($file);
        if (preg_match('/viewBox\s*=\s*"\s*[\d.eE+-]+\s+[\d.eE+-]+\s+([\d.eE+-]+)\s+([\d.eE+-]+)/', $svg, $m)) {
            $w = (int)round((float)$m[1]);
            $h = (int)round((float)$m[2]);
            if ($w > 0 && $h > 0) return [$w, $h];
        }
        return [0, 0];
    }
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif'], true)) return [0, 0];
    $info = getimagesize($file);
    return (is_array($info) && $info[0] > 0 && $info[1] > 0) ? [(int)$info[0], (int)$info[1]] : [0, 0];
}

# Compress CSS
# A file that is already minified is left alone: it carries no comments and no indentation to win back, and running a regex over it only spends time on the build
# There is no counterpart for scripts on purpose: a regex cannot tell code from the inside of a string, so stripping spaces around braces and operators breaks valid JavaScript
function getCompressCss(string $css): string {
    # Remove multiline comment
    $css = preg_replace('#\/\*(?!-)[\x00-\xff]*?\*\/#', '', $css);
    # Remove tabs, spaces, newlines
    $css = str_replace(["\n", "\r", "\t"], ' ', $css);
    # Remove extra spaces
    $css = preg_replace('#\s+#', ' ', $css);
    # Remove spaces that can be removed
    $css = preg_replace('#\s?([\{\}\:\;\,])\s?#', '\\1', $css);
    return $css;
}

# Normalize final HTML output while keeping the template's own indentation, doing heavy work only on the few multiline matches
# Raw bodies of script/style/pre/code/textarea and comments are protected first
# Then multiline class lists and multiline tags are squeezed to a single line
# Blank lines are dropped, the leading indent of every line is kept, and all other template whitespace is left untouched
# There is no stronger mode: the page that is served and the page that is stored are the same string, so a second pass would only make the two differ
function getOutputHtml(string $html): string {
    if ($html === '') return '';
    $keep = [];
    $html = (string)preg_replace_callback('#<(script|style|pre|code|textarea)\b[^>]*>.*?</\1>|<!--.*?-->#si', static function(array $dat) use (&$keep): string {
        if ($dat[0][1] === '!') {
            $key = "\x01".count($keep)."\x01";
            $keep[$key] = $dat[0];
            return $key;
        }
        $gt = strpos($dat[0], '>');
        $key = "\x01".count($keep)."\x01";
        $keep[$key] = substr($dat[0], $gt + 1);
        return substr($dat[0], 0, $gt + 1).$key;
    }, $html);
    $html = (string)preg_replace_callback('#\sclass\s*=\s*(["\'])([^"\']*[\r\n][^"\']*)\1#', static function(array $cls): string {
        return ' class='.$cls[1].trim((string)preg_replace('#\s+#', ' ', $cls[2])).$cls[1];
    }, $html);
    $html = (string)preg_replace_callback('#<[a-zA-Z][^>]*[\r\n][^>]*>#s', static function(array $dat): string {
        $tag = $dat[0];
        $out = '';
        $quot = '';
        $space = false;
        $len = strlen($tag);
        for ($pos = 0; $pos < $len; $pos++) {
            $chr = $tag[$pos];
            if ($quot !== '') {
                $out .= $chr;
                if ($chr === $quot) $quot = '';
            } elseif ($chr === '"' || $chr === "'") {
                $quot = $chr;
                $out .= $chr;
            } elseif (ctype_space($chr)) {
                $space = $out !== '' && substr($out, -1) !== '<';
            } else {
                if ($space && $chr !== '>' && substr($out, -1) !== '<') $out .= ' ';
                $space = false;
                $out .= $chr;
            }
        }
        return $out;
    }, $html);
    $html = (string)preg_replace('#\n[ \t\r]*(?=\n)#', '', $html);
    return strtr($html, $keep);
}

# Voting view
function getVotingView(int $id = 0, string $votid = '', bool $force = false): string {
    global $db, $afile, $user, $locale, $conf, $tpl;
    if (!$id) $id = getVar('get', 'id', 'num', 0);
    if (!$votid) $votid = filterVar(getVar('get', 'votid', 'text'));
    if (!$votid) $votid = filterVar(getVar('post', 'votid', 'text', 'voting')) ?: 'voting';
    $ispage = !$force && $votid === 'voting';
    $iswidget = !$force && $votid === 'blockvoting';
    $issection = !$ispage && !$iswidget;

    if ($force) {
        $qwhere = '1 = 1';
        $qpars = [];
    } else {
        $qwhere = "time <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')";
        $qpars = [];
        if ($conf['multilingual'] == 1) {
            $qwhere = "(lang = :locale OR lang = '') AND ".$qwhere;
            $qpars = ['locale' => $locale];
        }
    }

    $result = $db->getSqlQuery('SELECT modul, title, body, answer, enddate, multi, comments, acomm, typ FROM '.PREFIX_DB.'_voting WHERE id = :id AND '.$qwhere, array_merge(['id' => $id], $qpars));
    if ($db->getSqlRowCount($result) < 1) {
        return $tpl->getHtmlFrag('alert', ['text' => _NO_INFO, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
    }

    [$modul, $title, $body, $answer, $end, $multi, $comm, $acomm, $typ] = $db->getSqlRow($result);
    $cmod = substr('voting', 0, 2).'-'.$id;
    $cook = isset($_COOKIE[$cmod]) ? intval($_COOKIE[$cmod]) : 0;
    $uid = is_user() ? intval(substr($user[0], 0, 11)) : 0;

    $db->getSqlQuery('DELETE FROM '.PREFIX_DB."_rating WHERE time < :past AND modul = 'voting'", ['past' => time() - intval($conf['voting']['voting_t'])]);
    [$num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB."_rating WHERE (mid = :id AND modul = 'voting' AND ip = :ip) OR (mid = :id2 AND modul = 'voting' AND uid = :uid AND uid != '0')", ['id' => $id, 'ip' => getIp(), 'id2' => $id, 'uid' => $uid]));
    $rate = $force || $cook == $id || $num > 0 || strtotime($end) <= time();
    if (!$force && !$typ && $rate) {
        return $tpl->getHtmlFrag('alert', ['text' => _VCLINFO, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
    }

    $body = explode('|', $body);
    $answer = explode('|', $answer);
    $vote = array_sum($answer);
    $ints = array_map('intval', $answer);
    $top = ($rate && $vote > 0) ? array_search(max($ints), $ints) : -1;
    $items = '';
    foreach ($body as $idx => $text) {
        $ord = $idx + 1;
        $pn = $idx % 5 + 1;
        $cnt = intval($answer[$idx] ?? 0);
        $perc = ($vote > 0) ? number_format(100 * $cnt / $vote, 2) : '0.00';

        if ($rate) {
            $items .= $tpl->getHtmlFrag('voting-view', ['text' => $text, 'text_safe' => filterText($text), 'n' => $ord, 'pn' => $pn, 'percent' => $perc, 'votes_label' => _VOTES, 'votes' => $cnt, 'is_lead' => ($idx === $top)]);
            continue;
        }

        $field = $multi
            ? $tpl->getHtmlFrag('checkbox', ['name_attr' => 'body[]', 'value_attr' => (string)$ord, 'label_html' => $text, 'is_plain' => true])
            : $tpl->getHtmlFrag('radio', ['name_attr' => 'body[]', 'value_attr' => (string)$ord, 'label_html' => $text]);
        $items .= $tpl->getHtmlFrag('list-item', ['content_html' => $field]);
    }

    [$vnum] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_voting WHERE '.$qwhere, $qpars));
    $admin = '';
    if (!$force && is_moder('voting')) {
        $admin = $tpl->getHtmlFrag('dial', getTplEditMenu($afile.'.php?name=voting&op=add&id='.$id, $afile.'.php?name=voting&op=delete&id='.$id.'&refer=1&token='.getSiteToken(), $title));
    }

    $post = (!$force && !$rate) ? $tpl->getHtmlFrag('link', [
        'href' => 'index.php?go=1&op=updateVotingResult&votid='.$votid,
        'is_post' => true,
        'hx_target' => '#rep'.$votid,
        'hx_include' => '#form'.$votid,
        'title' => _VOTE,
        'label' => _VOTE,
        'is_button_blue' => true,
    ]) : '';
    $polls = (!$force && $vnum > 1) ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name=voting', 'title' => _POLLS, 'label' => _POLLS, 'icon_name' => 'card-checklist', 'chip_tone' => 'accent']) : '';
    $votes = $force ? '' : $tpl->getHtmlFrag('span', ['is_votes' => true, 'text' => _VOTES.': '.$vote]);
    if (!$force && !$modul && $votid != 'voting') {
        $votes = $tpl->getHtmlFrag('link', ['href' => 'index.php?name=voting&op=view&id='.$id, 'title' => _VOTES, 'label' => _VOTES.': '.$vote, 'is_votes' => true]);
    }
    $com = (!$force && !$modul && $acomm) ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name=voting&op=view&id='.$id.'#comm', 'title' => _COMMENTS, 'label' => _COMMENTS.': '.$comm, 'is_comments' => true, 'chip_tone' => 'info']) : '';

    return $tpl->getHtmlPart('voting-widget', [
        'has_form'   => !$rate,
        'is_view'    => $votid === 'voting',
        'is_page'    => $ispage,
        'is_section' => $issection,
        'is_widget'  => $iswidget,
        'form_id'    => 'form'.$votid,
        'poll_id'    => $id,
        'token'      => getSiteToken(),
        'title'      => $title,
        'items_html' => $items,
        'admin_html' => $admin,
        'post_html'  => $post,
        'polls_html' => $polls,
        'votes_html' => $votes,
        'comm_html'  => $com,
        'live_every' => (!$force && $rate && strtotime($end) > time()) ? intval($conf['live_u'] ?? 0) : 0,
        'live_query' => 'go=1&op=getVotingView&id='.$id.'&votid='.$votid.'&token='.getSiteToken(),
        'is_closed' => (!$force && $rate && strtotime($end) <= time()),
        'end_label' => _VOTING_END,
    ]);
}

# Converts PHP memory_limit to bytes
function getMemoryLimitBytes(bool $safe = false): int {
    $limit = ini_get('memory_limit');
    if ($limit === false || $limit === '' || $limit === '-1') return ($safe) ? max(memory_get_usage(true) * 2, 134217728) : 0;
    $limit = trim($limit);
    if (!preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([KMG])?$/i', $limit, $matches)) return max(0, (int)$limit);
    $value = (float)$matches[1];
    $unit = strtoupper($matches[2] ?? '');
    if ($unit === 'G') return (int)($value * 1024 * 1024 * 1024);
    if ($unit === 'M') return (int)($value * 1024 * 1024);
    if ($unit === 'K') return (int)($value * 1024);
    return (int)$value;
}

# Return one final request metrics snapshot for footer and debug panel formatting
function getLoadStats(): array {
    global $db, $sgtime;
    $qnum = (int)$db->qnum;
    $sql = (float)$db->sqltime;
    return [
        'mem' => memory_get_peak_usage(),
        'gen' => microtime(true) - $sgtime,
        'qnum' => $qnum,
        'sql' => $sql,
        'avg' => ($qnum > 0) ? ($sql / $qnum) : 0.0,
    ];
}

# The one ladder that turns a per cent into a tone, so a quota ring, a server gauge and the debug panel never disagree about what «almost full» looks like
# The names are the ones both themes give their tone classes, and a value outside nought to a hundred answers the tone of the end it passed
function getPercentTone(float $part): string {
    if ($part > 95) return 'danger';
    if ($part > 75) return 'warn';
    if ($part > 50) return 'info';
    return 'ok';
}

# Returns rendered system debug information
function getDebugSystemInfo(array $stats = []): string {
    global $tpl;
    if ($stats === []) $stats = getLoadStats();
    $max = [
        'mem' => getMemoryLimitBytes(),
        'gen' => 2.0,
        'qnum' => 50,
        'qtime' => 0.010,
    ];
    $metric = static function (float $value, float $max): array {
        $percent = ($max > 0) ? ($value * 100 / $max) : 0.0;
        $percent = min(100.0, max(0.0, $percent));
        $state = getPercentTone($percent);
        return [
            'percent' => number_format($percent, 1, '.', ''),
            'progress' => ['ok' => '2', 'info' => '1', 'warn' => '3', 'danger' => '4'][$state],
            'is_success' => $state === 'ok',
            'is_info' => $state === 'info',
            'is_warn' => $state === 'warn',
            'is_danger' => $state === 'danger',
        ];
    };
    $memuse = (int)($stats['mem'] ?? memory_get_peak_usage());
    $gentime = (float)($stats['gen'] ?? 0.0);
    $sqltime = (float)($stats['sql'] ?? 0.0);
    $qnum = (int)($stats['qnum'] ?? 0);
    $avg = (float)($stats['avg'] ?? (($qnum > 0) ? ($sqltime / $qnum) : 0.0));
    $mem = $metric($memuse, $max['mem']);
    $gen = $metric($gentime, $max['gen']);
    $queries = $metric($qnum, $max['qnum']);
    $sql = $metric($avg, $max['qtime']);
    $data = [
        'lbl_meml' => _MEMUSAGE,
        'mem_title' => ($max['mem'] > 0) ? $mem['percent'].'%' : _NO_INFO,
        'mem_value' => filterSize($memuse),
        'mem_limit' => ($max['mem'] > 0) ? filterSize($max['mem']) : _NO_INFO,
        'generation_label' => _PAGETIME,
        'generation_text' => sprintf('%.3f', $gentime).' '._SEC.'.',
        'generation_title' => $gen['percent'].'%',
        'db_queries_label' => _DBQUERY,
        'db_queries_text' => $qnum,
        'db_queries_title' => $qnum.' / '.$max['qnum'],
        'db_time_label' => _DBQTIME,
        'db_text' => sprintf('%.3f', $sqltime).' '._SEC.'. / Ø '.sprintf('%.4f', $avg).' '._SEC.'.',
        'db_title' => 'Ø '.sprintf('%.4f', $avg).' '._SEC.'.',
    ];
    foreach (['mem' => $mem, 'generation' => $gen, 'db_queries' => $queries, 'db' => $sql] as $name => $item) {
        $data[$name.'_is_success'] = $item['is_success'];
        $data[$name.'_is_info'] = $item['is_info'];
        $data[$name.'_is_warn'] = $item['is_warn'];
        $data[$name.'_is_danger'] = $item['is_danger'];
        $data[$name.'_percent'] = $item['percent'];
        $data[$name.'_progress'] = $item['progress'];
    }
    return $tpl->getHtmlFrag('debug-stats', $data);
}

# Returns recent PHP and SQL debug log entries
function getDebugErrors(): string {
    global $tpl;
    $logs = [
        'PHP' => LOGS_DIR.'/error_php.log',
        'SQL' => LOGS_DIR.'/error_sql.log',
    ];
    $rows = [];
    foreach ($logs as $chan => $file) {
        if (!is_file($file) || !is_readable($file)) continue;
        $size = filesize($file);
        if (!$size) continue;
        $open = fopen($file, 'rb');
        if (!$open) continue;
        $text = '';
        $pos = $size;
        while ($pos > 0 && substr_count($text, "\n") <= 4) {
            $step = min(8192, $pos);
            $pos -= $step;
            if (fseek($open, $pos) !== 0) break;
            $read = fread($open, $step);
            if ($read === false) break;
            $text = $read.$text;
        }
        fclose($open);
        $list = array_values(array_filter(array_map('trim', explode("\n", $text)), static fn($line) => $line !== ''));
        if (!$list) continue;
        foreach (array_slice($list, -4) as $line) {
            $data = json_decode($line, true);
            $time = is_array($data) ? (string)($data['ts'] ?? '') : '';
            $msg = is_array($data) ? (string)($data['msg'] ?? $line) : $line;
            $url = is_array($data) ? (string)($data['url'] ?? '') : '';
            $rows[] = ['time' => $time, 'chan' => $chan, 'msg' => cutstr($msg, 180), 'url' => cutstr($url, 80)];
        }
    }
    if (!$rows) return '';
    usort($rows, static fn($one, $two) => strcmp($two['time'], $one['time']));
    $html = '';
    foreach (array_slice($rows, 0, 4) as $row) {
        $dt = ($row['time'] !== '') ? date_create($row['time']) : false;
        $time = $dt ? $dt->format(_TIMESTRING) : '';
        $text = (($time !== '') ? $time.' ' : '').$row['chan'].': '.$row['msg'].(($row['url'] !== '') ? ' - '.$row['url'] : '');
        $html .= $tpl->getHtmlFrag('list-item', ['content_html' => $tpl->getHtmlFrag('span', ['text' => $text])]);
    }
    return $tpl->getHtmlFrag('list', ['is_unordered' => true, 'items_html' => $html]);
}

# Variable analyzer; self-guarded so direct calls follow the configured debug visibility
function getVariables(): string {
    global $db, $conf, $tpl;
    if (!checkDebugView()) return '';
    $cont = '';
    $cvar = explode(',', $conf['variables']);
    $rows = [];
    if ($cvar[1]) {
        $rows[] = ['legend' => _SYSTEM_INFO, 'tone' => 'info', 'content' => DBG_MARK];
        if (isAdmin()) {
            $errors = getDebugErrors();
            if ($errors !== '') $rows[] = ['legend' => _ERRLOG, 'tone' => 'danger', 'content' => $errors];
        }
    }
    $vars = [
        2 => ['POST', 'success', $_POST, true],
        3 => ['GET', 'info', $_GET, true],
        4 => ['COOKIE', 'warn', $_COOKIE, false],
        5 => ['FILES', 'accent', $_FILES, false],
        6 => ['SESSION', 'accent', $_SESSION, false],
        7 => ['SERVER', 'danger', $_SERVER, false],
    ];
    foreach ($vars as $key => $var) {
        if (!$cvar[$key] || !$var[2]) continue;
        $text = print_r($var[2], true);
        $rows[] = [
            'legend' => _AVARIABLES.': '.$var[0],
            'tone' => $var[1],
            'content' => ($var[3]) ? htmlspecialchars($text) : $text,
        ];
    }
    if ($cvar[8]) $rows[] = ['legend' => _AQUERY_DB, 'tone' => 'success', 'content' => $db->getSqlTraceHtml()];
    foreach ($rows as $row) $cont .= $tpl->getHtmlFrag('debug-section', $row);
    return $cont;
}

# Number of user news
function getUserNews(int $num): int {
    global $user, $conf;
    $unum = (int)($user[3] ?? 0);
    return ($unum > 0 && $unum <= $num && $conf['users']['news'] == 1) ? $unum : $num;
}

# Random password generation
function getRandomString(int $m): string {
    $m = intval($m);
    $pass = '';
    for ($i = 0; $i < $m; $i++) {
        $te = random_int(48, 122);
        if (($te > 57 && $te < 65) || ($te > 90 && $te < 97)) $te = $te - 9;
        $pass .= chr($te);
    }
    return $pass;
}

# Defining the server connection protocol
function getProtocol(): string {
    if ($_SERVER['SERVER_PORT'] == 443) {
        $proto = 'https';
    } elseif (isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) {
        $proto = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
        $proto = 'https';
    } elseif (strtolower(substr($_SERVER['SERVER_PROTOCOL'], 0, 5)) == 'https') {
        $proto = 'https';
    } else {
        $proto = 'http';
    }
    return $proto;
}

# Get the image from the text; inline data URIs are never returned because meta image tags must point to a real fetchable resource
# An attachment is resolved against the upload directory of its own module, and the module is named explicitly by a caller that renders the content of another one
# Without that name the request module would decide the path, which is right for a module rendering itself and wrong for a page that lists the newest rows of several
# The name becomes a path segment, so it passes the same filter the request boundary applies to it; anything else answers an empty name, and the path it builds simply resolves to no file
function getImgText(string $text, string $type = '', bool $check = true, string $mod = ''): string|false {
 global $conf;
    $mod = filterVar(($mod !== '') ? $mod : (string)($conf['name'] ?? ''));
    if (preg_match('#\[attach=(.*?)\s(.*?)\]#i', $text, $match)) {
        $fname = basename(trim($match[1]));
        $img = (!$type) ? 'uploads/'.$mod.'/thumb/'.$fname : 'uploads/'.$mod.'/'.$fname;
    } elseif (preg_match('#\[img=[a-zA-Z]+\](.*?)\[/img\]#i', $text, $match)) {
        $img = trim($match[1]);
    } elseif (preg_match('#\[img\](.*?)\[/img\]#i', $text, $match)) {
        $img = trim($match[1]);
    } else {
        $img = '';
    }
    if ($img !== '' && stripos($img, 'data:') === 0) $img = '';
    $path = empty($img) ? '' : BASE_DIR.'/'.ltrim(str_replace('\\', '/', $img), '/');
    $img = empty($img) ? false : ($check ? (file_exists($path) ? $img : false) : $img);
    return $img;
}

# Format SEO url
function getSeoUrl(array $params): string {
 global $conf;
    $sep   = $conf['sep'] ?? '-';
    $tsep  = $conf['tsep'] ?? '-';
    $slugs = ['title', 'ctitle'];
    $segments = [];
    $query = [];
    foreach ($params as $key => $val) {
        if (in_array($key, $slugs, true)) continue;
        $segments[] = $val;
        $query[] = $key.'='.$val;
    }
    if ($conf['rewrite'] ?? false) {
        foreach ($slugs as $key) {
            if (!empty($conf[$key]) && !empty($params[$key])) {
                $segments[] = filterSlug($params[$key], $tsep);
            }
        }
        return implode($sep, $segments);
    }
    return 'index.php?'.implode('&', $query);
}

function filterSlug(string $text, string $sep = '-'): string {
    $text = trim($text);
    static $rus = [
        'А' => 'A',  'Б' => 'B',  'В' => 'V',  'Г' => 'G',  'Д' => 'D',  'Е' => 'E',  'Ё' => 'E',  'Ж' => 'Zh',
        'З' => 'Z',  'И' => 'I',  'Й' => 'I',  'К' => 'K',  'Л' => 'L',  'М' => 'M',  'Н' => 'N',  'О' => 'O',
        'П' => 'P',  'Р' => 'R',  'С' => 'S',  'Т' => 'T',  'У' => 'U',  'Ф' => 'F',  'Х' => 'Kh', 'Ц' => 'Ts',
        'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch', 'Ы' => 'Y', 'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        'Ъ' => '',   'Ь' => '',
        'а' => 'a',  'б' => 'b',  'в' => 'v',  'г' => 'g',  'д' => 'd',  'е' => 'e',  'ё' => 'e',  'ж' => 'zh',
        'з' => 'z',  'и' => 'i',  'й' => 'i',  'к' => 'k',  'л' => 'l',  'м' => 'm',  'н' => 'n',  'о' => 'o',
        'п' => 'p',  'р' => 'r',  'с' => 's',  'т' => 't',  'у' => 'u',  'ф' => 'f',  'х' => 'kh', 'ц' => 'ts',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'ъ' => '',   'ь' => '',
    ];
    $text = strtr($text, $rus);
    $text = preg_replace('~[^a-zA-Z0-9]+~', $sep, $text);
    $text = trim($text, $sep);
    return strtolower($text);
}

# Format theme
function getTheme(): string {
    static $cached = null;
    if ($cached !== null) return $cached;
    global $user, $conf;
    $default = $conf['theme'] ?? 'default';
    if (is_user()) {
        $utheme = $user[5] ?? '';
        if ($utheme !== '' && checkThemeAssets($utheme)) return $cached = $utheme;
    }
    if (!checkThemeAssets($default)) Logger::addSite('error', 'Active theme incomplete: '.$default, ['theme' => $default]);
    return $cached = $default;
}

# Validate that a theme directory contains the canonical structure: base/theme CSS, icon library, system avatars, presets, and theme assets declared by editor manifests
function checkThemeAssets(string $name): bool {
    static $cache = [];
    if (isset($cache[$name])) return $cache[$name];
    $base = BASE_DIR.'/templates/'.$name.'/';
    $need = [
        'assets/css/base.css',
        'assets/css/theme.css',
        'assets/vendor/bootstrap-icons/css/bootstrap-icons.min.css',
        'assets/vendor/bootstrap-icons/css/fonts/bootstrap-icons.woff2',
        'images/avatars/system/user.svg',
        'images/avatars/system/guest.svg',
        'images/avatars/system/deleted.svg',
    ];
    foreach ($need as $file) {
        if (!is_file($base.$file)) return $cache[$name] = false;
    }
    if (!is_dir($base.'images/avatars/presets')) return $cache[$name] = false;
    foreach (glob(BASE_DIR.'/plugins/editors/*/manifest.json') ?: [] as $file) {
        $man = Editor::getManifest(basename(dirname($file)));
        $dec = (array)($man['theme'] ?? []);
        if (!$dec) continue;
        if (!empty($dec['skin']) && !is_file($base.'assets/editors/'.$man['id'].'/skin.css')) return $cache[$name] = false;
        foreach ((array)($dec['partials'] ?? []) as $part) {
            if (!is_file($base.'partials/'.$part.'.html')) return $cache[$name] = false;
        }
    }
    return $cache[$name] = true;
}

# Format theme file
# Determining the load time
function getTimeLoads(array $stats = []): string {
    if ($stats === []) $stats = getLoadStats();
    $ttime = sprintf('%.3f', (float)($stats['gen'] ?? 0.0));
    $qnums = (int)($stats['qnum'] ?? 0);
    $sqltime = sprintf('%.3f', (float)($stats['sql'] ?? 0.0));
    $cont = _GENERATION.': '.$ttime.' '._SEC.'. '._AND.' '.$qnums.' '._GENERATION_DB.' '.$sqltime.' '._SEC.'.';
    return $cont;
}

# Replace footer and debug markers with one final request metrics snapshot
function getTimedHtml(string $html): string {
    global $conf;
    $hgen = str_contains($html, GEN_MARK);
    $hdbg = str_contains($html, DBG_MARK);
    if (!$hgen && !$hdbg) return $html;
    $stats = getLoadStats();
    $maps = [];
    if ($hgen) $maps[GEN_MARK] = ($conf['db_t'] == '1') ? getTimeLoads($stats) : '';
    if ($hdbg) $maps[DBG_MARK] = checkDebugView() ? getDebugSystemInfo($stats) : '';
    return strtr($html, $maps);
}

# Notify subscribed admins by email on new content or comment submission
# The stored module list is only read here; normalising it is a write and belongs to the admin screen that owns those records, which already writes the normalised form
function addAdminMail(bool $enab, string $mod, string $username = '', string $title = '', bool $iscmt = false, string $text = ''): void {
    global $db, $conf, $locale, $mailer;
    $mod = filterVar($mod);
    if ($enab && $mod) {
        $kind = $iscmt ? 'comment' : 'content';
        $subject = $iscmt ? $conf['sitename'].' - '.$title.' - '._COMMENT : $conf['sitename'].' - '.$title;
        $puname  = $username ? filterText(substr($username, 0, 25)) : _ANONYM;
        $message = $iscmt
            ? str_replace('[text]', sprintf(_ADDMAILC, $puname, $title, $text), $conf['mtemp'])
            : str_replace('[text]', sprintf(_ADDMAIL, $puname, $title), $conf['mtemp']);
        $params = [];
        $where = " WHERE smail = '1'";
        if ($conf['multilingual']) {
            $where .= " AND (lang = :lang OR lang = '')";
            $params['lang'] = $locale;
        }
        $result = $db->getSqlQuery('SELECT email, super, modules FROM '.PREFIX_DB.'_admins'.$where.' ORDER BY id', $params);
        while ($row = $db->getSqlRow($result)) {
            [$email, $super, $modules] = $row;
            if ($super) {
                $mailer->addQueue(['kind' => $kind, 'email' => $email, 'title' => $subject, 'body' => $message, 'sender' => $conf['adminmail'], 'prio' => 1, 'client' => true]);
            } else {
                foreach (getAdminModuleNames($modules) as $val) {
                    if ($val !== '' && $val === $mod) {
                        $mailer->addQueue(['kind' => $kind, 'email' => $email, 'title' => $subject, 'body' => $message, 'sender' => $conf['adminmail'], 'prio' => 1, 'client' => true]);
                        break;
                    }
                }
            }
        }
    }
}

# Size value filter (bytes to human-readable unit)
function filterSize(mixed $size): string {
    $val = (float)$size;
    if ($val <= 0) return intval($size).' Bytes';
    $unit = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    for ($idx = 0, $max = count($unit) - 1; $val >= 1024 && $idx < $max; $idx++) $val /= 1024;
    return round($val, 2).' '.$unit[$idx];
}

# Drain the outgoing mail queue as a scheduler job and report what the run delivered, what it refused and what is still waiting
# A run that delivered nothing while refusing something is reported as failed, because that is a transport the administrator has to be told about rather than a quiet retry
# An empty queue is a successful run and never idle: this job runs every five minutes, and idle would count as a failure and leave the last success behind on a healthy installation
function addMailTask(): array {
    global $mailer;
    if (!$mailer instanceof Mail) return ['status' => 'failed', 'message' => 'Mail service is unavailable'];
    $data = $mailer->updateQueue();
    if ($data['stop'] === 'database') return ['status' => 'failed', 'message' => 'The mail queue is unreachable'];
    if ($data['stop'] === 'transport') return ['status' => 'failed', 'message' => 'The transport refused before any message left: '.$mailer->getError()];
    if ($data['sent'] === 0 && $data['fail'] === 0) return ['status' => 'success', 'message' => 'Nothing to send, pending '.$data['left']];
    return [
        'status' => ($data['sent'] === 0 && $data['fail'] > 0) ? 'failed' : 'success',
        'message' => 'Sent '.$data['sent'].', failed '.$data['fail'].', pending '.$data['left'],
        'extra' => [
            'last_mail_sent' => $data['sent'],
            'last_mail_failed' => $data['fail'],
            'last_mail_pending' => $data['left'],
            'last_mail_pruned' => $data['kept'],
            'last_mail_stop' => $data['stop'],
        ],
    ];
}

# Resolve one audience criterion into the paged query its rows are expanded by and the count query the selector shows beside it
# The client sets are grouped by address, because one person may hold several orders and the mailing is addressed to the person
# Every source is ordered by its own primary key, which is what makes a cursor over it resumable after a run is killed
function getMailAudience(string $audit, string $apar): array {
    global $db;
    $tabl = PREFIX_DB.'_users';
    $cond = '';
    $pars = [];
    $uniq = false;
    switch ($audit) {
        case 'all': $cond = '1'; break;
        case 'subs': $cond = 'newslet = 1'; break;
        case 'group':
            $grup = $db->getSqlRow($db->getSqlQuery('SELECT points, extra FROM '.PREFIX_DB.'_groups WHERE id = :id', ['id' => intval($apar)]));
            if (!$grup) return [];
            if (intval($grup['extra']) === 1) {
                $cond = 'grp = :grp';
                $pars['grp'] = intval($apar);
            } else {
                $cond = 'points >= :pts';
                $pars['pts'] = intval($grup['points']);
            }
            break;
        case 'active':
            $cond = 'lastvis >= DATE_SUB(NOW(), INTERVAL :day DAY)';
            $pars['day'] = max(1, intval($apar));
            break;
        case 'money': $tabl = PREFIX_DB.'_money'; $cond = 'status = 1'; $uniq = true; break;
        case 'order': $tabl = PREFIX_DB.'_order'; $cond = 'status = 1'; $uniq = true; break;
        case 'shop':
            $tabl = PREFIX_DB.'_clients';
            $cond = ($apar === 'on') ? 'status = 1' : (($apar === 'off') ? 'status = 0' : '1');
            $uniq = true;
            break;
        default: return [];
    }
    $cond .= ' AND email != \'\'';
    $list = $uniq
        ? 'SELECT MIN(id) AS id, email FROM '.$tabl.' WHERE '.$cond.' GROUP BY email HAVING id > :cur ORDER BY id ASC LIMIT '
        : 'SELECT id, email FROM '.$tabl.' WHERE '.$cond.' AND id > :cur ORDER BY id ASC LIMIT ';
    $nums = $uniq
        ? 'SELECT COUNT(DISTINCT email) AS num FROM '.$tabl.' WHERE '.$cond
        : 'SELECT COUNT(id) AS num FROM '.$tabl.' WHERE '.$cond;
    return ['list' => $list, 'nums' => $nums, 'pars' => $pars];
}

# Count the recipients one audience criterion resolves to right now, which is the number every option of the selector carries
function getMailAudienceNum(string $audit, string $apar): int {
    global $db;
    $data = getMailAudience($audit, $apar);
    if (!$data) return 0;
    $row = $db->getSqlRow($db->getSqlQuery($data['nums'], $data['pars']));
    return intval($row['num'] ?? 0);
}

# Expand the audience of the mailing that is preparing into queue rows, one resumable slice at a time, and close the expansion when its source is exhausted
# Writing one row per recipient inside the request that pressed save is what this replaces: at a hundred thousand accounts that request cannot finish and cannot be resumed
# Every address is checked before a row is written, so an invalid or suppressed one never enters a bulk audience and never costs a delivery attempt
# The run is time-boxed like the drain, and the cursor is written per slice, so a killed run resumes where it stopped instead of restarting or duplicating
function updateNewsletter(): array {
    global $db, $conf, $mailer;
    $sql = 'SELECT id, title, audit, apar, `cursor`, expect FROM '.PREFIX_DB.'_newsletter WHERE status = 1 ORDER BY id ASC LIMIT 1';
    $camp = $db->getSqlRow($db->getSqlQuery($sql));
    if (!$camp) return ['status' => 'success', 'message' => 'No mailing is being prepared'];
    $id = intval($camp['id']);
    $data = getMailAudience((string)$camp['audit'], (string)$camp['apar']);
    if (!$data) {
        $note = 'the audience of this mailing can no longer be resolved';
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_newsletter SET status = 7, note = :note WHERE id = :id', ['note' => $note, 'id' => $id]);
        return ['status' => 'failed', 'message' => 'Newsletter '.$id.': '.$note];
    }
    $cli = PHP_SAPI === 'cli';
    if ($cli && function_exists('set_time_limit')) set_time_limit(0);
    $lock = max(60, intval($conf['scheduler']['jobs']['newsletter']['lock_timeout'] ?? 0) ?: 900);
    $exec = intval(ini_get('max_execution_time'));
    $tend = microtime(true) + ($cli ? $lock - 60 : (($exec > 0) ? max(10, intval($exec * 0.6)) : 120));
    $slice = 500;
    $cur = intval($camp['cursor']);
    $num = 0;
    $skip = 0;
    $over = false;
    while (microtime(true) < $tend) {
        $rows = $db->getSqlRows($db->getSqlQuery($data['list'].$slice, $data['pars'] + ['cur' => $cur])) ?: [];
        if (!$rows) {
            $over = true;
            break;
        }
        foreach ($rows as $one) {
            $cur = intval($one['id']);
            $mail = trim((string)$one['email']);
            if (!$mailer->checkAddress($mail)) {
                $skip++;
                continue;
            }
            $mesg = ['kind' => 'newsletter', 'email' => $mail, 'title' => (string)$camp['title'], 'sender' => (string)$conf['adminmail']];
            if ($mailer->addQueue($mesg + ['ref' => $id, 'prio' => 3, 'camp' => true, 'hold' => true])) $num++;
            else $skip++;
        }
        $sql = 'UPDATE '.PREFIX_DB.'_newsletter SET `cursor` = :cur, total = total + :num WHERE id = :id';
        $db->getSqlQuery($sql, ['cur' => $cur, 'num' => $num, 'id' => $id]);
        $num = 0;
    }
    $stat = $over ? $mailer->setCampReady($id, intval($camp['expect'])) : 1;
    return [
        'status' => 'success',
        'message' => 'Newsletter '.$id.': expanded to cursor '.$cur.', skipped '.$skip.($over ? ', audience complete' : ', more to come'),
        'extra' => [
            'last_newsletter_id' => $id,
            'last_newsletter_cursor' => $cur,
            'last_newsletter_skipped' => $skip,
            'last_newsletter_state' => $stat,
        ],
    ];
}

# Resolve a PHP constant by name; return the name itself if undefined
function getConst(string $con): string {
    return defined($con) ? constant($con) : $con;
}

# Resolve a module key to its localised name constant
function getModuleName(string $con): string {
    $map = ['account' => _ACCOUNT, 'album' => _ALBUM, 'all' => _ALL, 'auto_links' => _A_LINKS, 'changelog' => _CHANGELOG, 'clients' => _CLIENTS, 'contact' => _FEEDBACK, 'content' => _CONTENT, 'faq' => _FAQ, 'files' => _FILES, 'forum' => _FORUM, 'gallery' => _ALBUM, 'help' => _HELP, 'info' => _INFO, 'jokes' => _JOKES, 'links' => _LINKS, 'main' => _MAIN, 'media' => _MEDIA, 'members' => _USERS, 'money' => _MONEY, 'news' => _NEWS, 'order' => _ORDER, 'pages' => _PAGES, 'radio' => _RADIO, 'recommend' => _RECOMMEND, 'rss' => _RSS, 'rss_info' => _RSS, 'search' => _SEARCH, 'shop' => _SHOP, 'sitemap' => _SITEMAP, 'users' => _TOPUSERS, 'voting' => _VOTING, 'whois' => _WHOIS];
    return $map[$con] ?? $con;
}

# Resolve a language code to its localised name constant
function getLangName(string $con): string {
    $map = ['en' => _ENGLISH, 'fr' => _FRENCH, 'de' => _GERMAN, 'pl' => _POLISH, 'ru' => _RUSSIAN, 'uk' => _UKRAINIAN];
    return $map[$con] ?? $con;
}

# Resolve a language code to an existing SVG flag in the current theme
function getLanguageFlagSrc(string $lang): string {
    $map = ['en' => 'gb', 'uk' => 'ua'];
    $code = $map[$lang] ?? $lang;
    $path = 'flags/'.$code.'.svg';
    return file_exists(getThemeImagePath($path)) ? getThemeImagePath($path) : getThemeImagePath('flags/unknown.svg');
}

# Hash a user password with bcrypt
function getPassHash(string $pass): string {
    return password_hash($pass, PASSWORD_BCRYPT);
}

# Verify a user password; supports current bcrypt and legacy md5 hashes transparently.
# Legacy branch will be removed once all stored hashes have been upgraded via transparent rehashing.
function checkPassHash(string $pass, string $hash): bool {
    if (password_verify($pass, $hash)) return true;
    if (strlen($hash) === 32 && ctype_xdigit($hash)) return md5(md5(PASS_SALT).md5($pass)) === $hash;
    return false;
}

####
# OLD FUNCTIONS (for backward compatibility, not recommended for use in new code)
####

# Format Time filter
function format_time(string $time, string $string = ''): string {
    $string = ($string) ? $string : _DATESTRING;
    $cont = date($string, strtotime($time));
    return $cont;
}

# Replace break
function getEditorKey(): string {
 global $admin, $conf;
    $role = defined('ADMIN_FILE') ? 'admin' : 'user';
    if ($role === 'admin') {
        $key = (string)($admin[3] ?? '');
        if ($key !== '' && Editor::isValidEditor($key, 'admin')) return $key;
        $key = (string)($conf['editor']['admin'] ?? 'plain');
        if (Editor::isValidEditor($key, 'admin')) return $key;
        return 'plain';
    }
    $key = (string)($conf['editor']['user'] ?? 'plain');
    if (Editor::isValidEditor($key, 'user')) return $key;
    return 'plain';
}

# Check whether the active content editor stores trusted HTML
function checkHtmlEditor(?string $key = null): bool {
    return getEditorMode($key) === 'html';
}

# Resolve content storage format for the selected editor
function getEditorMode(?string $key = null): string {
    $key ??= getEditorKey();
    return Editor::getFormat($key);
}

# Replace break
function replace_break(string $text): string {
    if ($text) {
        $out = !checkHtmlEditor() ? preg_replace('#<br\s*/?>#i', '', $text) : $text;
        return $out;
    }
    return '';
}

# User information for user
function getUserSessionInfo(string $id = ''): string {
 global $db, $conf, $tpl;
    if ($conf['session']) {
        [$mem, $bots, $all] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(CASE WHEN guest = 2 THEN 1 END), COUNT(CASE WHEN guest = 1 THEN 1 END), COUNT(*) FROM '.PREFIX_DB.'_session'));
        $mem = intval($mem);
        $bot = ($conf['botsact']) ? intval($bots) : 0;
        $all = intval($all);
        $gst = $all - $mem - $bot;
        $mper = ($all > 0) ? intval(round($mem / $all * 100)) : 0;
        $bend = ($all > 0) ? intval(round(($mem + $bot) / $all * 100)) : 0;
        $gper = ($all > 0) ? intval(round($gst / $all * 100)) : 0;
        $content = $tpl->getHtmlPart('session-summary', [
            'members_label' => _BMEM,
            'members_count' => $mem,
            'members_pct' => $mper,
            'show_bots' => $conf['botsact'],
            'bots_label' => _BOTS,
            'bots_count' => $bot,
            'bots_end_pct' => $bend,
            'visitors_label' => _BVIS,
            'visitors_count' => $gst,
            'visitors_pct' => $gper,
            'overall_label' => _OVERALL,
            'overall_count' => $all,
            'online_label' => _ONLINE,
            'show_online_chip' => !is_user(),
            'update_title' => _UPDATE,
            'update_label' => _UPDATE,
            'toggle_title' => _READMORE,
            'toggle_label' => _READMORE,
            'has_rows' => ($mem > 0 || $bot > 0),
            'rows_query' => 'go=1&op=getUserSessionRows',
            'toggle_id' => 'u-block',
            'live_every' => intval($conf['live_u'] ?? 0),
            'update_target' => 'sinfo',
            'update_query' => 'go=1&op=getUserSessionInfo',
        ]);
        if ($id) { return $content; } else { echo $content; }
    }
    return '';
}

# Output the detailed online visitor rows (users and bots) for the sidebar session block; requested lazily via htmx on first expand
function getUserSessionRows(): void {
 global $db, $conf, $tpl;
    if (!$conf['session']) return;
    $where = ($conf['botsact']) ? 'guest IN (1, 2)' : 'guest = 2';
    $rows = '';
    $result = $db->getSqlQuery('SELECT uname, time, ip, guest, modul FROM '.PREFIX_DB.'_session WHERE '.$where.' ORDER BY uname');
    while ([$uname, $time, $host, $guest, $module] = $db->getSqlRow($result)) {
        $time = time() - $time;
        $strip = cutstr($uname, 15);
        $module = getModuleName($module);
        $lstrip = cutstr($module, 15);
        if ($guest == 2) {
            $rows .= $tpl->getHtmlFrag('session-row', [
                'geo_html' => Geoip::getFlagHtml($host),
                'name_link' => ['href' => 'index.php?name=account&op=view&uname='.urlencode($uname), 'title' => getDuration($time), 'label' => $strip],
                'module_title' => $module,
                'module_text' => $lstrip,
            ]);
        } else {
            $rows .= $tpl->getHtmlFrag('session-row', [
                'geo_html' => Geoip::getFlagHtml($host),
                'name_title' => getDuration($time),
                'name_text' => $strip,
                'is_name_note' => true,
                'module_title' => $module,
                'module_text' => $lstrip,
            ]);
        }
    }
    echo $rows;
}

# User information for admin
function getUserSessionAdminInfo(string $id = ''): string {
 global $db, $conf, $tpl;
    if ($conf['session'] && isAdmin()) {
        $a = $b = $m = $u = $i = 0;
        $who_online = ['0' => '', '1' => '', '2' => '', '3' => ''];
        $content_who = '';
        $result = $db->getSqlQuery('SELECT uname, time, ip, guest, modul, url FROM '.PREFIX_DB.'_session ORDER BY uname');
        while (list($uname, $time, $host, $guest, $module, $url) = $db->getSqlRow($result)) {
            $time = time() - $time;
            $namestrip = cutstr($uname, 15);
            $lstrip = cutstr($module, 15);
            $alink = urldecode($url);
            $alstrip = cutstr($alink, 15);
            $guest = intval($guest);
            if ($guest == 3) {
                $title_who = $tpl->getHtmlFrag('session-row', [
                    'geo_html'    => Geoip::getFlagHtml($host),
                    'name_link'   => ['href' => $conf['ip_link'].$host, 'title' => getDuration($time).' - '._IP.': '.$host, 'label' => $namestrip, 'is_blank' => true],
                    'module_link' => ['href' => $alink, 'title' => $alink, 'label' => $alstrip, 'is_blank' => true],
                    'is_module_right' => true,
                ]);
                $a++;
            } elseif ($guest == 2) {
                $title_who = $tpl->getHtmlFrag('session-row', [
                    'geo_html'    => Geoip::getFlagHtml($host),
                    'name_link'   => ['href' => 'index.php?name=account&op=view&uname='.urlencode($uname), 'title' => getDuration($time).' - '._IP.': '.$host, 'label' => $namestrip, 'is_blank' => true],
                    'module_link' => ['href' => $alink, 'title' => $alink, 'label' => ($lstrip !== '' ? $lstrip : $alstrip), 'is_blank' => true],
                    'is_module_right' => true,
                ]);
                $m++;
            } elseif ($guest == 1) {
                $title_who = $tpl->getHtmlFrag('session-row', [
                    'geo_html'    => Geoip::getFlagHtml($host),
                    'name_link'   => ['href' => $conf['ip_link'].$host, 'title' => getDuration($time).' - '._IP.': '.$host, 'label' => $namestrip, 'is_blank' => true],
                    'module_link' => ['href' => $alink, 'title' => $alink, 'label' => $lstrip, 'is_blank' => true],
                    'is_module_right' => true,
                ]);
                $b++;
            } else {
                $title_who = ($u < 250) ? $tpl->getHtmlFrag('session-row', [
                    'geo_html'    => Geoip::getFlagHtml($host),
                    'name_link'   => ['href' => $conf['ip_link'].$host, 'title' => getDuration($time), 'label' => $uname, 'is_blank' => true],
                    'module_link' => ['href' => $alink, 'title' => $alink, 'label' => $lstrip, 'is_blank' => true],
                    'is_module_right' => true,
                ]) : '';
                $u++;
            }
            $who_online[$guest] .= $title_who;
            $i++;
        }
        $content_who .= $tpl->getHtmlPart('session-summary', [
            'show_admins' => isAdmin(true),
            'admins_icon_name'   => 'person-gear',
            'members_icon_name'  => 'person',
            'bots_icon_name'     => 'robot',
            'visitors_icon_name' => 'person-slash',
            'overall_icon_name'  => 'people',
            'admins_label' => _ADMINS,
            'admins_count' => $a,
            'admins_rows_html' => $who_online[3],
            'admins_toggle_id' => 'ad-block',
            'members_label' => _BMEM,
            'members_count' => $m,
            'members_rows_html' => $who_online[2],
            'members_toggle_id' => 'us-block',
            'bots_label' => _BOTS,
            'bots_count' => $b,
            'bots_rows_html' => $who_online[1],
            'bots_toggle_id' => 'bo-block',
            'visitors_label' => _BVIS,
            'visitors_count' => $u,
            'visitors_rows_html' => $who_online[0],
            'visitors_toggle_id' => 'an-block',
            'overall_label' => _OVERALL,
            'overall_count' => $i,
            'readmore_title' => _READMORE,
            'update_title' => _UPDATE,
            'update_label' => _UPDATE,
            'update_target' => 'sainfo',
            'update_query' => 'go=5&op=getUserSessionAdminInfo',
        ]);
        if ($id) { return $content_who; } else { echo $content_who; }
    }
    return '';
}

# Format admin block
function adminblock(): string {
 global $db, $afile, $tpl;
    if (isAdmin()) {
        $cltit = defined('_OPCL') ? _OPCL : ((defined('_CLOSE') ? _CLOSE : 'Close').' / '.(defined('_WHO') ? _WHO : 'Open'));
        $title = '';
        $content = '';
        $block = '';
        if (isAdmin(true)) {
            list($title, $content) = $db->getSqlRow($db->getSqlQuery('SELECT title, content FROM '.PREFIX_DB."_blocks WHERE bkey = 'admin'"));
            $block = (string)$content;
        }
        $cont = $tpl->getHtmlFrag('admin-block-links', [
            'admin_link'  => ['href' => $afile.'.php', 'title' => (string)_ADMINMENU, 'label' => (string)_ADMINMENU, 'icon_name' => 'house-door'],
            'logout_link' => ['href' => $afile.'.php?op=logout', 'title' => (string)_LOGOUT, 'label' => (string)_LOGOUT, 'icon_name' => 'box-arrow-right'],
            'block_html'  => $block,
        ]);
        $a_title = ($title) ? $title : _ADMINS;
        return $tpl->getHtmlPart('block-sidebar', ['title' => $a_title, 'icon_name' => 'person-gear', 'content_html' => $cont, 'id' => '7', 'close' => $cltit])
            .$tpl->getHtmlPart('block-sidebar', ['title' => _WHO, 'icon_name' => 'eye', 'content_html' => getUserSessionAdminInfo(1), 'content_id' => 'repsainfo', 'id' => '8', 'close' => $cltit]);
    }
    return '';
}

# User info link
# $icon is off wherever the name stands next to an info tip, because the "i" already marks the user block and a second person icon would only repeat it
function user_info(string $name, bool $icon = true): string {
    global $conf, $tpl;
    if (!$name) return '';
    if ($conf['users']['prof'] != 1 || ($conf['users']['prof'] == 1 && is_user()) || isAdmin()) {
        return $tpl->getHtmlFrag('link', [
            'href' => 'index.php?name=account&op=view&uname='.urlencode($name),
            'title' => (string)_PERSONALINFO,
            'is_author' => $icon,
            'label' => $name,
        ]);
    }
    return $name;
}

# Write the live reply count into one forum topic, which is the one statement every forum counter path goes through
# The number is counted inside the statement rather than moved by a delta, so a topic that had drifted comes back correct instead of drifting further
function setForumCount(int $tid): bool {
    global $db;
    if ($tid < 1) return false;
    $live = 'SELECT COUNT(*) FROM '.PREFIX_DB.'_forum AS r WHERE r.pid = :pid';
    return $db->getSqlQuery('UPDATE '.PREFIX_DB.'_forum SET comments = ('.$live.') WHERE id = :tid', ['pid' => $tid, 'tid' => $tid]) !== false;
}

# Queue the reply count of one topic to be rewritten once the request is over
# The count runs after the response because the subquery it needs is a locking read of the topic table, and two people answering one topic at once would wait on each other
function addForumCount(int $tid): void {
    if ($tid < 1) return;
    addDeferredTask(static function() use ($tid): void {
        setForumCount($tid);
    });
}

# Answer the forum topics whose stored reply count disagrees with the replies actually under them
# A topic with no counter and no reply is never examined, so the scan costs the topics that carry a discussion rather than every row
function getForumDrift(): array {
    global $db;
    $live = 'SELECT COUNT(*) FROM '.PREFIX_DB.'_forum AS r WHERE r.pid = t.id';
    $sql = 'SELECT x.tid, x.live FROM (SELECT t.id AS tid, t.comments AS col, ('.$live.') AS live FROM '.PREFIX_DB.'_forum AS t'
        .' WHERE t.pid = 0 AND (t.comments <> 0 OR EXISTS (SELECT 1 FROM '.PREFIX_DB.'_forum AS d WHERE d.pid = t.id))) AS x'
        .' WHERE x.col <> x.live ORDER BY x.tid ASC';
    $out = [];
    foreach ($db->getSqlRows($db->getSqlQuery($sql)) ?: [] as $row) $out[] = intval($row['tid']);
    return $out;
}

# Answer the topic carrying the newest visible activity across the given categories, or 0 when they hold none
# A reply answers as the topic it belongs to, because a category advertises a topic rather than a message
# This is the one definition of "last message" in the forum: the repair below and the synchronisation tab both ask it
function getForumLast(array $cats): int {
    global $db;
    $ids = array_values(array_unique(array_filter(array_map('intval', $cats), static fn(int $one): bool => $one > 0)));
    if (!$ids) return 0;
    $keys = [];
    $pars = [];
    foreach ($ids as $num => $one) {
        $keys[] = ':c'.$num;
        $pars['c'.$num] = $one;
    }
    $sql = 'SELECT id, pid FROM '.PREFIX_DB.'_forum WHERE cid IN ('.implode(', ', $keys).')'
        .' AND ((pid != 0 AND status = 1) OR (pid = 0 AND status > 1)) ORDER BY id DESC LIMIT 1';
    [$mid, $pid] = $db->getSqlRow($db->getSqlQuery($sql, $pars));
    return intval($pid) ?: intval($mid);
}

# Repair the last message the forum categories advertise, after a removal took one out of a branch
# Two sets are repaired: whoever pointed at the removed topic, wherever it sat, and the chain above the category it was removed from
# Matching on the stored value rather than walking from the category the request named is what keeps a parent from being decided by one child
function setForumLast(int $cat, int $gone = 0): void {
    global $db;
    $rows = $db->getSqlRows($db->getSqlQuery('SELECT id, parent, lpost FROM '.PREFIX_DB.'_categories WHERE modul = \'forum\'')) ?: [];
    $up = [];
    $kids = [];
    $had = [];
    $hit = [];
    foreach ($rows as $one) {
        $id = intval($one['id']);
        $up[$id] = intval($one['parent']);
        $had[$id] = intval($one['lpost']);
        $kids[intval($one['parent'])][] = $id;
        if ($gone > 0 && $had[$id] === $gone) $hit[$id] = $id;
    }
    $walk = $cat;
    while ($walk > 0 && isset($up[$walk])) {
        $hit[$walk] = $walk;
        $walk = $up[$walk];
    }
    foreach ($hit as $id) {
        $sub = [$id];
        for ($num = 0; $num < count($sub); $num++) {
            foreach ($kids[$sub[$num]] ?? [] as $one) $sub[] = $one;
        }
        $want = getForumLast($sub);
        if ($want === $had[$id]) continue;
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET lpost = :lid WHERE id = :id AND modul = \'forum\'', ['lid' => $want, 'id' => $id]);
    }
}

# Resolve the topic status badge flag shared by the forum list and the home forum block
function getForumTopicState(int $status, string $time, string $ltime, int $comments, int $pop, int $ulast, bool $ismod): string {
    $now = date('Y-m-d H:i:s');
    if (!$status && $ismod) return 'is_topic_moderated';
    if ($status == 1) return 'is_topic_admin';
    if ($status == 2) return 'is_topic_closed';
    if ($status == 3 && $time <= $now) {
        $ispop = $comments > $pop;
        if ($ltime > $ulast) return $ispop ? 'is_topic_popular_new' : 'is_topic_new';
        return $ispop ? 'is_topic_popular_old' : 'is_topic_old';
    }
    if ($status == 3 && $time > $now && $ismod) return 'is_topic_pending';
    if ($status == 4 || $status == 5) return ($status == 4) ? 'is_topic_hot' : 'is_topic_announcement';
    return '';
}

# Query recent root forum topics shared by the forum blocks and theme teasers
function getForumTopics(string $cols, string $bclos, int $limit): mixed {
    global $db;
    $where = $bclos ? 'cid NOT IN ('.$bclos.') AND' : '';
    $vis = is_moder('forum') ? '' : "AND time <= now() AND status > '1'";
    return $db->getSqlQuery('SELECT '.$cols.' FROM '.PREFIX_DB.'_forum WHERE '.$where." pid = '0' ".$vis.' ORDER BY ltime DESC LIMIT 0, '.$limit);
}

# Show cart
function getCartSummary(string $info = ''): string {
 global $db, $conf, $tpl;
    $shop = base64_decode((string)($_COOKIE[$conf['user_c'].'-shop'] ?? $_COOKIE['shop'] ?? ''));
    $info = (empty($info)) ? $shop : base64_decode($info);
    $cookies = (preg_match('#[^0-9,]#', $info)) ? '' : $info;
    if ($cookies) {
        $massiv = explode(',', $cookies);
        $ids = array_values(array_unique(array_map('intval', $massiv)));
        $ids = array_values(array_filter($ids, static fn($v) => $v > 0));
        if (!$ids) return '';
        $pp = [];
        $pm = [];
        foreach ($ids as $k => $pid) {
            $ph = 'id'.$k;
            $pp[] = ':'.$ph;
            $pm[$ph] = $pid;
        }
        $result = $db->getSqlQuery('SELECT id, time, title, price FROM '.PREFIX_DB.'_products WHERE id IN ('.implode(', ', $pp).')', $pm);
        $rows = '';
        $ptotal = 0;
        while (list($id, $time, $title, $price) = $db->getSqlRow($result)) {
            $i = 0;
            foreach ($massiv as $val) {
                if ($val == $id) $i++;
            }
            $price = $price * $i;
            $ptotal += $price;
            $titlink = $tpl->getHtmlFrag('link', ['href' => 'index.php?name=shop&op=view&id='.$id, 'title' => $title, 'label' => $title]);
            $actions = $tpl->getHtmlFrag('link', [
                'href' => 'index.php?go=2&op=addCartItem&id='.$id.'&token='.getSiteToken(),
                'is_htmx' => true, 'hx_target' => '#repkasse', 'title' => _PPLUS, 'is_cart_plus' => true,
            ])
                .$tpl->getHtmlFrag('link', [
                    'href' => 'index.php?go=2&op=deleteCartItem&id='.$id.'&token='.getSiteToken(),
                    'is_htmx' => true, 'hx_target' => '#repkasse', 'title' => ($i > 1) ? _PMINUS : _DELETE, 'is_cart_minus' => true,
                ]);
            $rows .= $tpl->getHtmlFrag('table-row', [
                'id' => 'kasse-'.$id,
                'is_cart_row' => true,
                'cells' => [
                    ['href' => '#kasse-'.$id, 'title' => (string)$id, 'text' => (string)$id, 'is_cart_col_num' => true, 'is_cart_id' => true],
                    ['is_cart_col_content' => true, 'heading_html' => $titlink.' '.getTplNewGraphic($time)],
                    ['is_cart_col_num' => true, 'text' => (string)$i],
                    ['is_cart_col_stat' => true, 'text' => $price.' '.$conf['shop']['valute']],
                    ['is_cart_col_stat' => true, 'content_html' => $actions],
                ],
            ]);
        }
        $footer = $tpl->getHtmlFrag('table-row', [
            'is_cart_foot' => true,
            'cells' => [
                ['colspan' => 2, 'content_html' => $tpl->getHtmlFrag('link', ['href' => 'index.php?name=shop&op=kasse', 'title' => _SCACH, 'label' => _SCACH, 'is_cart_checkout' => true])],
                ['colspan' => 3, 'content_html' => $tpl->getHtmlFrag('span', ['title' => _PARTNERGES, 'text' => _PARTNERGES.': '.$ptotal.' '.$conf['shop']['valute'], 'is_cart_total' => true])],
            ],
        ]);
        return $tpl->getHtmlFrag('table', [
            'title' => _PBASKET,
            'is_cart' => true,
            'rows_html' => $rows.$footer,
            'headers' => [
                ['text' => _ID, 'is_cart_col_num' => true],
                ['text' => _PRODUCT],
                ['text' => cutstr(_QUANTITY, 3, 1), 'is_cart_col_num' => true],
                ['text' => _PREIS, 'is_cart_col_stat' => true],
                ['text' => _FUNCTIONS, 'is_cart_col_stat' => true],
            ],
        ]);
    }
    return '';
}

# Add cart item
function addCartItem(): void {
    global $conf;
    $id = getVar('get', 'id', 'num', 0);
    $carts = base64_decode((string)($_COOKIE[$conf['user_c'].'-shop'] ?? $_COOKIE['shop'] ?? ''));
    $cookies = (preg_match('#[^0-9,]#', $carts)) ? '' : $carts;
    $info = '';
    if ($id) {
        $info = ($cookies) ? base64_encode($cookies.','.$id) : base64_encode($id);
        setCookies('shop', time() + $conf['shop']['shop_t'], $info);
    }
    echo getCartSummary($info);
}

# Delete cart item
function deleteCartItem(): void {
    global $conf;
    $id = getVar('get', 'id', 'num', 0);
    $carts = base64_decode((string)($_COOKIE[$conf['user_c'].'-shop'] ?? $_COOKIE['shop'] ?? ''));
    $cookies = (preg_match('#[^0-9,]#', $carts)) ? '' : $carts;
    $info = '';
    if ($id && $cookies) {
        $massiv = explode(',', $cookies);
        $a = 0;
        foreach ($massiv as $val) {
            if ($val == $id && $a == 0) {
                $a++;
                continue;
            }
            $info .= ($info === '') ? $val : ','.$val;
        }
        if ($info === '') {
            setCookiesDelete('shop');
            unset($_COOKIE[$conf['user_c'].'-shop'], $_COOKIE['shop']);
        } else {
            $info = base64_encode($info);
            setCookies('shop', time() + $conf['shop']['shop_t'], $info);
        }
    }
    echo getCartSummary($info);
}

# Format user warnings
function warnings(string $warnings): string {
    global $tpl;
    if ($warnings) {
        $warns = explode('|', $warnings);
        $items = implode('', array_map(fn($v) => $v !== '' ? $tpl->getHtmlFrag('list-item', ['content_html' => $v]) : '', $warns));
        return $tpl->getHtmlFrag('list', ['items_html' => $items]);
    }
    return (string)_NO;
}

# Return JSON response for Toast UI editor endpoints
function getEditorJson(array $dat): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

# Split the pipe-separated upload configuration of one directory into named rule keys; all twelve are returned even when ok is false, so a caller needing one limit can read it
# The order is the stored one and the stored strings carry exactly these twelve fields; a rule written short by hand keeps its guest limit at the user one, because zero there means no limit at all
function getUploadRuleData(string $mod): array {
    global $conf;
    $con = isset($conf['uploads'][$mod]) ? explode('|', (string)$conf['uploads'][$mod]) : [];
    $err = '';
    $path = UPLOADS_DIR.'/'.$mod;
    if ($mod === '' || $con === []) $err = 'Upload configuration is missing';
    elseif (!is_dir($path)) $err = 'Upload directory is missing';
    return [
        'ok' => $err === '',
        'error' => $err,
        'dir' => 'uploads/'.$mod,
        'path' => $path,
        'extensions' => (string)($con[0] ?? ''),
        'maxquota' => (int)($con[1] ?? 0),
        'maxbytes' => (int)($con[2] ?? 0),
        'maxwidth' => (int)($con[3] ?? 0),
        'maxheight' => (int)($con[4] ?? 0),
        'maxfiles' => (int)($con[5] ?? 0),
        'thumbwidth' => (int)($con[6] ?? 0),
        'moderfiles' => (int)($con[7] ?? 0),
        'userfiles' => (int)($con[8] ?? 0),
        'userupload' => (int)($con[9] ?? 0),
        'guestupload' => (int)($con[10] ?? 0),
        'guestfiles' => (int)($con[11] ?? $con[8] ?? 0),
    ];
}

# Assemble the pipe-separated upload configuration of one directory from named rule keys, in the field order getUploadRuleData() reads
function setUploadRuleData(array $rule): string {
    $keys = [
        'extensions', 'maxquota', 'maxbytes', 'maxwidth', 'maxheight', 'maxfiles', 'thumbwidth',
        'moderfiles', 'userfiles', 'userupload', 'guestupload', 'guestfiles',
    ];
    return implode('|', array_map(static fn($v) => (string)($rule[$v] ?? ''), $keys));
}

# Return what one refused upload is told to the visitor: the service answers a code and the settings of the module answer the number the refusal names, so both screens speak alike
# The mapping lives here and not beside a screen, because the editor window and the administrative catalogue would otherwise explain the same refusal in two different words
# A capability this build does not have is told apart from a file this build refuses: the first is nothing the visitor can correct by choosing another file
function getUploadFailText(string $code, array $rule): string {
    return match ($code) {
        'size' => _ERROR_BIG,
        'count' => _FILEUP.': '.(int)($rule['maxfiles'] ?? 0),
        'unsupported' => _ERROR_SERV,
        'extension', 'mime', 'image' => _ERROR_FILE,
        'dimensions' => _ERROR_SIZE,
        'quota' => _FSIZEALL.': '.filterSize((int)($rule['maxquota'] ?? 0)),
        'exists' => _ERROR_EXIST,
        'destination', 'write' => _ERROR_UP,
        default => _ERROR_DOWN,
    };
}

# Return the single upload service; core/classes has no runtime autoload, so the class file is required on first use instead of on every request
# The upload root is named here and nowhere else, which is why no adapter ever calls new Upload(); where the destination locks live is decided by FileManager alone
function getUploadService(): Upload {
    static $upl = null;
    if ($upl === null) {
        require_once BASE_DIR.'/core/classes/upload.php';
        $upl = new Upload(UPLOADS_DIR);
    }
    return $upl;
}

# Check whether the current visitor may use the module editor upload
function checkEditorUploadAccess(string $mod, array $rule): bool {
    if (is_moder($mod)) return true;
    if (is_user() && (int)($rule['userupload'] ?? 0) === 1) return true;
    return !is_user() && (int)($rule['guestupload'] ?? 0) === 1;
}

# Resolve the owner token the stored file name of the current visitor carries: the site user id for a member, none for a moderator, and a per-session token for a guest
# A moderator is answered null because is_moder() reads the admin session: an administrator need not be a site user, so there is no id to own the file with and no segment
# The guest token is derived from the session and is never the session id, because the segment ends up in a public file name and must authenticate nothing when it is read off a URL
# A guest without a session is answered null and not a token derived from an empty id, because that one derivation is the same for every guest and is the defect this token removes
function getEditorFileOwner(string $mod): ?string {
    global $user;
    if (is_user()) return (string)(int)($user[0] ?? 0);
    if (is_moder($mod)) return null;
    $sid = (string)session_id();
    return ($sid === '') ? null : substr(hash_hmac('sha256', 'upload|'.$sid, getSecret('upload')), 0, 16);
}

# Return the file context of one editor module; core/classes has no runtime autoload, so the file layer is required on first use and the module directory is named the root here alone
# The client passes a name inside that directory and never a root of its own, and what may be done there is the answer of the upload rule and not a role the window worked out again
# Listing and uploading are one decision, checkEditorUploadAccess(), and the two operations a module moderator additionally holds ride on is_moder(), the one role rule of this area
function getEditorFileArea(string $mod, array $rule): FileManager {
    require_once BASE_DIR.'/core/classes/filemanager.php';
    $able = checkEditorUploadAccess($mod, $rule);
    return new FileManager('editor', (string)$rule['path'], ['upload' => $able, 'list' => $able, 'moder' => is_moder($mod)]);
}

# Answer the upload rule of one editor route once the three questions every one of them asks have been answered: the settings of the module, the right of the visitor and the token
# A refusal answers the JSON here and never returns, so no route restates the guards and none of them can ship with one of the three quietly missing from its own opening lines
function getEditorRouteRule(string $src = 'post'): array {
    $mod = strtolower(getVar('get', 'mod', 'var', ''));
    $rul = getUploadRuleData($mod);
    if (!$rul['ok']) getEditorJson(['ok' => false, 'error' => $rul['error']]);
    if (!checkEditorUploadAccess($mod, $rul)) getEditorJson(['ok' => false, 'error' => _ACCESSDENIED]);
    if (!checkSiteToken(getVar($src, 'token', 'raw', ''), 'upload')) getEditorJson(['ok' => false, 'error' => _TOKENMISS]);
    return $rul + ['mod' => $mod];
}

# Return one stored editor file row for JSON output: the descriptor of the file layer plus the two strings the window prints, so the client never formats a size or a date of its own
# Which actions a row offers is the capability set of its own descriptor and never a role the window derives again, which is what keeps the interface from computing a permission
# The absolute server path is absent because the file layer gives an editor context none, and the thumbnail falls back to the file itself so a listing always has one to draw
function getEditorFileData(array $one): array {
    return [
        'file' => $one['name'],
        'path' => $one['path'],
        'url' => $one['url'],
        'thumb' => ($one['thumbnail'] !== '') ? $one['thumbnail'] : $one['url'],
        'kind' => $one['kind'],
        'type' => $one['extension'],
        'size' => $one['size'],
        'sizetext' => filterSize($one['size']),
        'time' => $one['mtime'],
        'timetext' => date(_TIMESTRING, $one['mtime']),
        'image' => $one['kind'] === 'image',
        'width' => (int)$one['width'],
        'height' => (int)$one['height'],
        'able' => $one['capabilities'],
    ];
}

# Publish one editor submission through the upload service and answer with the editor JSON; rules, naming and quota belong to the service, the adapter only maps its codes
# Who the stored file belongs to is answered by getEditorFileOwner() alone, so the upload route and the listing route can never disagree about the segment the name carries
# Every submission is journalled on its own, as the other two routes of the window are: a file that reaches the disk without a record is a file nobody can account for later
function addEditorUpload(): void {
    global $admin, $user;
    $rul = getEditorRouteRule();
    $mod = (string)$rul['mod'];
    $area = getEditorFileArea($mod, $rul);
    $own = getEditorFileOwner($mod);
    $who = (string)($user[1] ?? '');
    if ($who === '') $who = (string)($admin[1] ?? '');
    $out = [];
    $bad = [];
    foreach (getUploadService()->addUploadedFiles($_FILES['file'] ?? [], $rul, $mod, $mod, $own) as $res) {
        $one = $res['ok'] ? $area->getFileData((string)$res['file']) : [];
        Logger::addFile($res['ok'] ? 'notice' : 'warning', 'Editor file operation', [
            'user' => substr($who, 0, 25),
            'ctx' => 'editor',
            'op' => 'editorUpload',
            'path' => $mod,
            'target' => (string)($res['file'] ?? ''),
            'result' => $res['ok'] ? 'ok' : (string)$res['error'],
        ]);
        if ($one !== []) {
            $out[] = getEditorFileData($one);
            continue;
        }
        $bad[] = getUploadFailText($res['ok'] ? 'write' : (string)$res['error'], $rul);
    }
    getEditorJson(['ok' => $out !== [], 'files' => $out, 'errors' => $bad, 'error' => $out ? '' : ($bad[0] ?? _ERROR_DOWN)]);
}

# Return stored files for the Toast UI editor file panel; a moderator lists the whole directory and every other visitor only the files carrying the own owner token
# Whether a list is answered at all is decided by checkEditorUploadAccess() and by nothing else, so a guest the settings allow sees the own uploads of the own session
# Which of the three limits applies is the one role question left on this route, and what each of them is worth is a setting: moderfiles, userfiles and guestfiles
# The owner segment is alphanumeric and not numeric, so the comparison is a string one: an integer cast turns every guest token into zero and matches one guest against another
# Who a stored name belongs to is read by FileManager::getFileOwner(), the one place that knows the format, so this route carries no pattern and no salt length of its own
function getEditorFileJson(): void {
    $rul = getEditorRouteRule('req');
    $mod = (string)$rul['mod'];
    $area = getEditorFileArea($mod, $rul);
    $all = is_moder($mod);
    $tok = getEditorFileOwner($mod);
    $lim = $all ? $rul['moderfiles'] : (is_user() ? $rul['userfiles'] : $rul['guestfiles']);
    $row = [];
    $used = 0;
    foreach ($area->getFileList('') as $one) {
        if ($one['kind'] === 'dir' || in_array($one['name'], ['index.html', '.htaccess'], true)) continue;
        $used += $one['size'];
        if (!$all && ($tok === null || FileManager::getFileOwner($one['name']) !== $tok)) continue;
        $row[] = getEditorFileData($one);
    }
    usort($row, static fn(array $one, array $two): int => $two['time'] <=> $one['time']);
    if ($lim > 0) $row = array_slice($row, 0, $lim);
    getEditorJson([
        'ok' => true,
        'files' => $row,
        'able' => $area->getCapabilities(),
        'used' => $used,
        'quota' => $rul['maxquota'],
        'usedtext' => filterSize($used),
        'quotatext' => filterSize($rul['maxquota']),
    ]);
}

# Run one changing operation of the editor window over one path or over the marked set; deletion and packing are the two it offers and a module moderator alone is answered them
# Every path is asked of its own descriptor before it is touched, so a capability the context withholds refuses here and not after the object was already opened for the work
# Each path is journalled on its own and the answer names how many of how many ran, because a marked set is the same action over several names and never a second operation
function setEditorFileRun(string $op): void {
    global $admin, $user;
    $rul = getEditorRouteRule();
    $who = (string)($user[1] ?? '');
    if ($who === '') $who = (string)($admin[1] ?? '');
    $area = getEditorFileArea((string)$rul['mod'], $rul);
    $mark = getVar('post', 'mark[]', 'array', []);
    $file = getVar('post', 'file', 'raw', '');
    if ($mark === [] && is_string($file) && $file !== '') $mark = [$file];
    $need = ($op === 'editorDelete') ? 'delete' : 'compress';
    $done = 0;
    $note = '';
    foreach ($mark as $path) {
        $one = is_string($path) ? $area->getFileData(mb_substr(trim($path), 0, 512)) : [];
        $shut = $one === [] || empty($one['capabilities'][$need]);
        if ($shut) $res = ['ok' => false, 'error' => 'closed', 'path' => ''];
        elseif ($need === 'delete') $res = $area->deleteFileEntry($one['path']);
        else $res = $area->addFileArchive($one['path']);
        if ($res['ok']) $done++;
        elseif ($note === '') $note = ($res['error'] === 'closed') ? _ACCESSDENIED : _ERROR_UP;
        Logger::addFile($res['ok'] ? 'notice' : 'warning', 'Editor file operation', [
            'user' => substr($who, 0, 25),
            'ctx' => 'editor',
            'op' => $op,
            'path' => is_string($path) ? $path : '',
            'target' => (string)($res['path'] ?? ''),
            'result' => $res['ok'] ? 'ok' : (string)$res['error'],
        ]);
    }
    getEditorJson(['ok' => $done > 0, 'done' => $done, 'total' => count($mark), 'error' => ($done > 0) ? '' : ($note ?: _ERROR)]);
}

# Hand one stored file to the client as a download and end the request there, which is the single download path of the project and the one place its headers are decided
# The type is always the opaque one and never guessed from the extension: an active type answered from the origin of the site is executed instead of being saved
# The name is reduced to its own last segment and encoded, so a name assembled out of a request carries no separator of its own and can append no header line
function getFileStream(string $path, string $name): void {
    $name = rawurlencode(basename($name));
    if ($name === '' || !is_file($path) || !is_readable($path)) {
        http_response_code(404);
        exit;
    }
    while (ob_get_level() > 0) ob_end_clean();
    Cache::setHeaders(false, 0, 'application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$name.'"; filename*=UTF-8\'\''.$name);
    header('Content-Length: '.filesize($path));
    readfile($path);
    exit;
}

# Format letter
function letter(string $mod): string {
 global $db, $user, $tpl;
    if ($mod == 'faq') {
        $result = $db->getSqlQuery('SELECT title FROM '.PREFIX_DB."_faq WHERE time <= NOW() AND status != '0'");
    } elseif ($mod == 'files') {
        $result = $db->getSqlQuery('SELECT title FROM '.PREFIX_DB."_files WHERE time <= NOW() AND status != '0'");
    } elseif ($mod == 'help') {
        $uid = intval($user[0]);
        $result = $db->getSqlQuery('SELECT title FROM '.PREFIX_DB."_help WHERE time <= NOW() AND pid = '0' AND uid = :uid", ['uid' => $uid]);
    } elseif ($mod == 'links') {
        $result = $db->getSqlQuery('SELECT title FROM '.PREFIX_DB."_links WHERE time <= NOW() AND status != '0'");
    } elseif ($mod == 'media') {
        $result = $db->getSqlQuery('SELECT title FROM '.PREFIX_DB."_media WHERE time <= NOW() AND status != '0'");
    } elseif ($mod == 'news') {
        $result = $db->getSqlQuery('SELECT title FROM '.PREFIX_DB."_news WHERE time <= NOW() AND status != '0'");
    } elseif ($mod == 'pages') {
        $result = $db->getSqlQuery('SELECT title FROM '.PREFIX_DB."_pages WHERE time <= NOW() AND status != '0'");
    } elseif ($mod == 'shop') {
        $result = $db->getSqlQuery('SELECT title FROM '.PREFIX_DB."_products WHERE time <= NOW() AND status != '0'");
    } else {
        $result = '';
    }
    if ($result) {
        while(list($title) = $db->getSqlRow($result)) $letdb[] = ucfirst(mb_substr(trim($title), 0, 1, 'utf-8'));
        $alpha = array_unique($letdb);
    } else {
        $alpha = [];
    }
    $rows = [];
    $digits = '';
    foreach (range(0, 9) as $num) {
        $label = $tpl->getHtmlFrag('span', ['text' => (string)$num, 'is_alpha_letter' => true]);
        $digits .= in_array((string)$num, $alpha)
            ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$mod.'&op=liste&let='.$num, 'title' => (string)$num, 'label_html' => $label])
            : $label;
    }
    $rows[] = $digits;
    $locale = '';
    foreach (preg_split('//u', _ALPHABET, -1, PREG_SPLIT_NO_EMPTY) as $char) {
        $label = $tpl->getHtmlFrag('span', ['text' => $char, 'is_alpha_letter' => true]);
        $locale .= in_array($char, $alpha)
            ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$mod.'&op=liste&let='.urlencode($char), 'title' => $char, 'label_html' => $label])
            : $label;
    }
    $rows[] = $locale;
    if (substr(_LOCALE, 0, 2) != 'fr') {
        $latin = '';
        foreach (range('A', 'Z') as $eng) {
            $label = $tpl->getHtmlFrag('span', ['text' => $eng, 'is_alpha_letter' => true]);
            $latin .= in_array($eng, $alpha)
                ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$mod.'&op=liste&let='.$eng, 'title' => $eng, 'label_html' => $label])
                : $label;
        }
        $rows[] = $latin;
    }
    $items = '';
    foreach ($rows as $row) {
        $items .= $tpl->getHtmlFrag('span', ['is_line_stack_item' => true, 'content_html' => $row, 'is_line_break' => true]);
    }
    return $items;
}

# Build a speed-dial action menu from structured dial items; editor gear by default, user menu with three dots when $user is true
function getActionMenu(array $items, bool $user = false): string {
    global $tpl;
    $items = array_values(array_filter($items));
    if (!$items) return '';
    return $tpl->getHtmlFrag('dial', ['is_user_menu' => $user, 'dial_title' => $user ? (string)_USER : (string)_EDITOR, 'dial' => $items]);
}

# Admin status
# The private-message variant is gone with the shared status column it rendered: a message now carries four independent states and its own labelled badge
function ad_status(mixed $link, mixed $id, string $text = ''): string {
    global $tpl;
    if ($link) {
        $deact = ($text) ? _DEACTIVATE.': '.$text : _DEACTIVATE;
        $act = ($text) ? _ACTIVATE.': '.$text : _ACTIVATE;
        return ($id == 1)
            ? $tpl->getHtmlFrag('link', ['href' => $link, 'title' => $deact, 'label' => $deact])
            : $tpl->getHtmlFrag('link', ['href' => $link, 'title' => $act, 'label' => $act]);
    }
    return ($id == 1)
        ? $tpl->getHtmlFrag('inline-badge', ['title_text' => _ACT, 'is_status_active' => true, 'label' => ''])
        : $tpl->getHtmlFrag('inline-badge', ['title_text' => _DEACT, 'is_status_inactive' => true, 'label' => '']);
}

# Returns the path of an image inside the active theme images directory
function getThemeImagePath(string $img): string {
    static $base;
    if (!$base) $base = 'templates/'.getTheme().'/images/';
    return $base.$img;
}

# Render a category icon from its stored Bootstrap Icons name, empty string when no icon is set
function getCategoryIcon(string $img): string {
    global $tpl;
    return ($img !== '' && preg_match('/^[a-z0-9-]+$/', $img)) ? $tpl->getHtmlFrag('bootstrap-icon', ['icon_name' => $img]) : '';
}

# Format select RSS
function rss_select(): string {
    global $conf, $tpl;
    $fieldc = explode('||', $conf['rss']['rss']);
    $url = getVar('post', 'url', 'url', '');
    $cont = '';
    foreach ($fieldc as $val) {
        if ($val != '') {
            preg_match("#(.*)\|(.*)\|(.*)#i", $val, $out);
            if ($out[1] != '0' && $out[2] != '0') {
                $link = (!preg_match("#http\:\/\/#i", $out[2])) ? $conf['homeurl'].'/'.$out[2] : $out[2];
                $cont .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => $link,
                    'label_text' => $out[1],
                    'is_selected' => $url == $out[2],
                ]);
            }
        }
    }
    return $cont;
}

# Read RSS
function rss_read(mixed $url, mixed $id): string {
    global $conf, $tpl;
    if ($url) {
        $url = trim(html_entity_decode(str_replace(['&#038;', '&amp;'], '&', $url), ENT_QUOTES, 'UTF-8'));
        $url = (!preg_match('#^https?://#i', $url)) ? 'http://'.$url : $url;
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'follow_location' => 1,
                'user_agent' => 'SLAED RSS Reader',
            ],
        ]);
        set_error_handler(static function (): bool { return true; });
        $content = file_get_contents($url, false, $context);
        restore_error_handler();
        if ($content) {
            if (preg_match('#encoding=["\']([^"\']+)#i', $content, $val) && !empty($val[1])) {
                $encoding = strtolower($val[1]);
                if ($encoding != 'utf-8') {
                    $converted = iconv($val[1], 'utf-8//IGNORE', $content);
                    if ($converted !== false) $content = $converted;
                }
            }
            $title = parse_url($url, PHP_URL_HOST);
            if (!$title) $title = $url;
            preg_match_all('#<item>(.*)</item>#Uism', $content, $items, PREG_PATTERN_ORDER);
            if (!empty($items[1])) {
                $number = ($conf['rss']['max'] > count($items[1])) ? count($items[1]) : $conf['rss']['max'];
                $cont = '';
                for ($i = 0; $i < $number; $i++) {
                    preg_match('#<title>(.*)</title>#Uism', $items[1][$i], $rss_title);
                    preg_match('#<pubDate>(.*)</pubDate>#Uism', $items[1][$i], $rss_date);
                    preg_match('#<guid>(.*)</guid>(.*)#Uism', $items[1][$i], $rss_guid);
                    preg_match('#<description>(.*)</description>#Uism', $items[1][$i], $rss_desc);
                    $temp = html_entity_decode($conf['rss']['temp'], ENT_QUOTES, 'UTF-8');
                    $rss_title = $rss_title[1] ?? '';
                    $rss_date = $rss_date[1] ?? '';
                    $rss_guid = $rss_guid[1] ?? '';
                    $rss_desc = $rss_desc[1] ?? '';
                    $rss_date = ($rss_date && strtotime($rss_date) !== false) ? date(_DATESTRING, strtotime($rss_date)) : '';
                    $temp = str_replace('[title]', $rss_title, $temp);
                    $temp = str_replace('[date]', $rss_date, $temp);
                    $temp = str_replace('[guid]', $rss_guid, $temp);
                    $temp = str_replace('[description]', filterText(html_entity_decode(str_replace(']]>', '', $rss_desc))), $temp);
                    $cont .= $temp;
                }
                if (!$id) {
                    $sourceLink = $tpl->getHtmlFrag('link', ['href' => $url, 'title' => _RSS_FROM.': '.$title, 'label' => $title, 'is_blank' => true]);
                    $cont = $tpl->getHtmlFrag('title', ['is_level_two' => true, 'title_html' => _RSS_FROM.': '.$sourceLink]).$cont;
                }
            } else {
                $cont = ($id) ? '' : $tpl->getHtmlFrag('alert', ['text' => _RSS_PROBLEM, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
            }
        } else {
            $cont = ($id) ? '' : $tpl->getHtmlFrag('alert', ['text' => _RSS_PROBLEM, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
        }
        return $cont;
    }
    return '';
}

# Load RSS
function rss_load(mixed $bid): void {
    global $db, $tpl;
    $bid = intval($bid);
    list($title, $content, $url, $refresh, $otime) = $db->getSqlRow($db->getSqlQuery('SELECT title, content, url, refresh, time FROM '.PREFIX_DB.'_blocks WHERE id = :bid', ['bid' => $bid]));
    $past = time() - $refresh;
    if ($otime < $past) {
        $btime = time();
        $content = rss_read($url, 1);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET content = :content, time = :time WHERE id = :bid', ['content' => $content, 'time' => $btime, 'bid' => $bid]);
    }
    echo $tpl->getHtmlFrag('block-all', ['title' => $title, 'content' => $content]);
}

# Build the project copyright and license line shown in page footers
function getLicenseHtml(): string {
    return '<a href="https://slaed.net" target="_blank" title="SLAED CMS">SLAED CMS</a> © 2005-'.date('Y').' Eduard Laas. Released under MIT License.';
}

# Get shared footer controls through a fragment
function getFootControls(string $title, string $label, string $time = '', string $lic = '', string $debug = '', bool $link = false, bool $dbgtog = false): string {
    global $tpl;
    return $tpl->getHtmlPart('foot-controls', [
        'top_title' => $title,
        'top_label' => $label,
        'brand_link' => $link ? [
            'href' => '//slaed.net',
            'title' => 'SLAED CMS',
            'label' => 'SLAED CMS',
            'isslaed' => true,
            'is_top_hidden' => true,
            'is_blank' => true,
        ] : [],
        'dbglink' => $dbgtog ? [
            'href' => '#',
            'title' => _DEBUGPANEL,
            'label' => _DEBUGPANEL,
            'is_top_hidden' => true,
            'isdebug' => true,
            'icon_name' => 'bug',
        ] : [],
        'top_link' => ['href' => '#', 'title' => $title, 'label' => $label, 'is_top_hidden' => true, 'is_upper' => true],
        'time_html' => $time,
        'license_html' => $lic,
        'debug_html' => $debug,
    ]);
}

# Format domain
function domain(string $url, string $str = ''): string {
    global $tpl;
    $massiv = explode(',', $url);
    $str = intval($str);
    $dom = [];
    foreach ($massiv as $val) {
        $val = html_entity_decode($val, ENT_QUOTES, 'UTF-8');
        $label = ($str) ? cutstr(preg_replace("/http\:\/\/|www./", '', $val), $str) : preg_replace("/http\:\/\/|www./", '', $val);
        $dom[] = $tpl->getHtmlFrag('link', [
            'href' => $val,
            'is_blank' => true,
            'label' => $label,
            'title' => _DOWNLLINK,
        ]);
    }
    return implode(', ', $dom);
}

# Check the user agent against the configured bot list with literal case-insensitive matching, result cached per request
function is_bot(): int|string {
    global $conf;
    static $found = null;
    if ($found !== null) return $found;
    $agent = getAgent();
    $found = 0;
    foreach (explode(',', $conf['bots']) as $item) {
        [$mask, $bname] = array_pad(explode('=', $item, 2), 2, '');
        if ($mask !== '' && stripos($agent, $mask) !== false) {
            $found = filterText(substr($bname, 0, 25), 1);
            break;
        }
    }
    return $found;
}
# Check the referer against the configured bot referer list with literal case-insensitive matching, result cached per request
function from_bot(): int|string {
    global $conf;
    static $found = null;
    if ($found !== null) return $found;
    $refer = getReferer();
    $found = 0;
    foreach (explode(',', $conf['fbots']) as $mask) {
        if ($mask !== '' && stripos($refer, $mask) !== false) {
            $found = filterText(substr($mask, 0, 25), 1);
            break;
        }
    }
    return $found;
}

# Check referer from Search Engines
function engines_word(string $refer): string {
    $engines = ['images.google.' => ['q', 'prev'], 'bing.com' => 'q', '.alot.' => 'q', 'a993.com' => 'q1', 'abcsok.' => 'q', 'alltheweb.' => 'q', 'altavista.' => 'q', 'aol.' => ['q', 'query', 'encquery'], 'aolsvc.' => 'query', 'avantfind.com' => 'keywords', 'bonvote.com' => 'search', 'bonweb.com' => 'search', 'comcast.net' => 'q', 'conduit.' => 'q', 'eniro.se' => 'search_word', 'excite.' => 'search', 'google.' => ['q', 'as_q'], 'gogo.ru' => 'q', 'yandex.' => ['text', 'query'], 'ya.ru' => 'text', 'hotbot.' => 'query', 'icerocket.com' => 'q', 'icq.com' => 'q', 'isheyka.com' => 'q', 'midco.net' => 'q', 'live.com' => 'q', 'msn.' => 'q', 'yahoo.' => ['p', 'k'], 'search.' => 'q', 'kvasir.no' => 'q', 'myway.com' => 'searchfor', 'netscape.' => ['q', 'query'], 'oceanfree.net' => 'as_q', 'qip.ru' => 'query', 'sweetim.com' => 'q', 'tut.by' => 'query', 'ukr.net' => 'search_query', 'search.oboz.ua' => 'k', 'search.www.infoseek.co.jp' => 'qt', '.setooz.com' => 'query', 'toile.com' => 'q', 'vinden.nl' => 'q', '.i.ua' => 'q', '.mail.ru' => ['q', 'tag'], '.onru.ru' => 'q', 'aport.ru' => 'r', 'find.ru' => 'text', 'gde.ru' => ['keywords', 'query', 't', 'search_query', 'id'], 'go.km.ru' => 'sq', 'meta.ua' => 'q', 'metabot.ru' => 'st', 'nerus.ru' => 'query', 'nigma.ru' => ['s', 'pq'], 'nova.rambler.ru' => 'query', 'poisk.ru' => 'text', 'protonet.ru' => 'q', 'rambler.ru' => 'query', 'tyndex.ru' => 'pnam', 'webalta.ru' => 'q', 'exactseek.com' => ['q', 'query'], 'lycos.' => 'query', 'ask.' => 'q', 'cnn.' => 'query', 'looksmart.' => 'qt', 'about.' => 'terms', 'mamma.' => 'query', 'gigablast.' => 'q', 'voila.' => 'rdata', 'virgilio.' => 'qs', 'baidu.' => 'wd', 'alice.' => 'qs', 'najdi.' => 'q', 'club-internet.' => 'q', 'mama.' => 'query', 'seznam.' => 'q', 'netsprint.' => 'q', 'szukacz.' => 'q', 'yam.' => 'k', 'pchome.' => 'q'];

    $refer= str_replace(['&#038;', '&amp;'], '&', $refer);
    $tmp = parse_url(urldecode(trim($refer)));
    $site = $tmp['host'];
    $str = $tmp['query'] ?? '';
    parse_str($str, $arr);

    foreach ($engines as $key => $value) {
        if (substr_count($site, $key)) {
            foreach ($arr as $k => $v) {
                if (is_array($value)) {
                    if (in_array($k, $value)) {
                        return $v;
                        break;
                    }
                } elseif ($k == $value) {
                    return $v;
                    break;
                } else {
                    return _NO;
                    break;
                }
            }
            break;
        }
    }
    return '';
}

# Check user
function is_user(string $usr = ''): int {
 global $db, $conf, $user;
    static $usertrue;
    if (!isset($usertrue) && $user) {
        $uid = intval(substr($user[0], 0, 11));
        $una = htmlspecialchars(substr($user[1], 0, 25));
        $pwd = $user[2];
        $ip = getIp();
        if ($uid != '' && $pwd != '') {
            if ($conf['users']['check'] == '0') {
                list($pass) = $db->getSqlRow($db->getSqlQuery('SELECT password FROM '.PREFIX_DB.'_users WHERE id = :uid AND name = :name', ['uid' => $uid, 'name' => $una]));
                if ($pass != '' && hash_equals($pass, $pwd)) {
                    $usertrue = 1;
                    return 1;
                }
            } else {
                list($pass, $userip) = $db->getSqlRow($db->getSqlQuery('SELECT password, ip FROM '.PREFIX_DB.'_users WHERE id = :uid AND name = :name', ['uid' => $uid, 'name' => $una]));
                if ($pass != '' && hash_equals($pass, $pwd) && $userip != '' && $userip == $ip) {
                    $usertrue = 1;
                    return 1;
                }
            }
        }
        $usertrue = 0;
        return 0;
    }
    if ($usertrue == 1) {
        return 1;
    } else {
        return 0;
    }
}

# Get user id
function is_user_id(string $name): int {
 global $db;
    $name = filterText(substr($name, 0, 25));
    list($uid) = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_users WHERE name = :name', ['name' => $name]));
    return intval($uid);
}

# Check modul admin
function is_admin_modul(string $modul): int {
 global $db, $admin;
    $aid = intval(substr($admin[0], 0, 11));
    $modul = addslashes(trim(substr($modul, 0, 25)));
    if ($modul == '') return 0;
    if (isAdmin(true)) return 1;
    static $amodules = [];
    if (!isset($amodules[$aid])) {
        list($modules) = $db->getSqlRow($db->getSqlQuery('SELECT modules FROM '.PREFIX_DB.'_admins WHERE id = :id', ['id' => $aid]));
        $modules = $modules ?? '';
        $names = getAdminModuleNames($modules);
        $new_modules = implode(',', $names);
        if ($new_modules !== $modules) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_admins SET modules = :modules WHERE id = :id', ['modules' => $new_modules, 'id' => $aid]);
        }
        $amodules[$aid] = $names ? array_fill_keys($names, 1) : [];
    }
    return isset($amodules[$aid][$modul]) ? 1 : 0;
}

# Check moderator
function is_moder(string $modul = ''): int {
    $modul = ($modul) ? addslashes(trim(substr($modul, 0, 25))) : 0;
    if ((isAdmin() && isAdmin(true)) || ($modul && isAdmin() && is_admin_modul($modul))) {
        return 1;
    } else {
        return 0;
    }
}

# Search user name: the flat array of names twelve forms read, and the richer answer only the compose field of the private message page asks for
# Both shapes are bounded, the richer one tighter: a suggestion list nobody can read to the end is a page of a database and not an answer to a reader
# The card belongs to the name that resolved exactly and never to a suggestion, so one keystroke costs one lookup of one account instead of one for every row offered
function getUserList(): void {
    global $db;
    $let = analyze_name(getVar('get', 'term', 'text', ''));
    $rich = getVar('get', 'rich', 'num', 0) == 1;
    $name = [];
    if ($let) {
        $sql = 'SELECT name FROM '.PREFIX_DB.'_users WHERE name LIKE :name ORDER BY name ASC LIMIT '.($rich ? 10 : 50);
        $result = $db->getSqlQuery($sql, ['name' => $let.'%']);
        while (list($uname) = $db->getSqlRow($result)) $name[] = $uname;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rich ? ['items' => $name, 'card' => getUserCardData($let) ?: null] : $name, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

# The recipient card of the private message form: who a typed name resolves to, and how much room their mailbox still has, in one lookup for one account
# Only an exact name is answered: a card drawn for every suggestion would cost one mailbox count per offered row, which is the N+1 this form exists without
# The fill is read through the subsystem that owns the private message table, and it answers a grade and not the numbers: how full another member's mailbox is belongs to them
# The grade folds the ladder that colours the shelves into the three steps a sender can act on: there is room, it is nearly full, and it will refuse the message
# The inbox is the only mailbox read, because it is the only one an arriving message lands in and the only one a send is refused on
# A mailbox no setting bounds is graded none rather than well: the shelves answer an unmeasured box the same way, and nothing measured is not the same as nothing wrong
# A card is answered to a member only, and never before the module itself is on: a name that resolves to nobody answers nothing at all and the form sends exactly as it did
function getUserCardData(string $name): array {
    global $db, $conf, $prv;
    $name = filterText(mb_substr($name, 0, 25));
    if ($name === '' || !is_user() || empty($conf['privat']['act'])) return [];
    $sql = 'SELECT u.id, u.name, u.avatar, u.lastvis, g.name AS gname FROM '.PREFIX_DB.'_users AS u'
        .' LEFT JOIN '.PREFIX_DB.'_groups AS g ON ((g.extra = 1 AND u.grp = g.id) OR (g.extra != 1 AND u.points >= g.points))'
        .' WHERE u.name = :name ORDER BY g.extra DESC, g.points DESC LIMIT 1';
    $mate = $db->getSqlRow($db->getSqlQuery($sql, ['name' => $name]));
    if (!$mate) return [];
    ['has' => $has, 'max' => $max, 'part' => $part] = $prv->getBoxFill(intval($mate['id']), PrivatBox::Inbox);
    $tone = ($max > 0) ? getPercentTone($part) : '';
    $step = ($max < 1) ? 'none' : (($has >= $max) ? 'danger' : (in_array($tone, ['warn', 'danger'], true) ? 'warn' : 'ok'));
    return [
        'name' => (string)$mate['name'],
        'avatar' => getUserAvatarUrl(['avatar' => (string)($mate['avatar'] ?? '')]),
        'group' => ($mate['gname'] ?? '') ? _GROUP.': '.$mate['gname'] : (string)_RANK,
        'seen' => ($mate['lastvis'] ?? '') ? _LAST_VISIT.': '.format_time((string)$mate['lastvis'], _TIMESTRING) : '',
        'tone' => $step,
        'fill' => match ($step) {'danger' => (string)_PRBOXFULL, 'warn' => (string)_PRBOXNEAR, 'ok' => (string)_PRBOXROOM, default => ''},
    ];
}

# Analyze name
function analyze_name(mixed $name): string {
    $name = ($name) ? ((preg_match("#\"|\'|\.|\:|\;|\/|\*#", $name)) ? '' : $name) : '';
    return $name;
}

# URL types
function url_types(string $urls): string {
    $url = explode(',', $urls);
    $con = [];
    foreach ($url as $v) {
        $var    = parse_url($v);
        $scheme = !empty($var['scheme']) ? $var['scheme'] : '';
        if ($scheme == 'ed2k') {
            $con[] = 'eMule';
        } elseif ($scheme == 'http') {
            $con[] = ucfirst(current(explode('.', str_replace('www.', '', $var['host']))));
        }
    }
    return $con ? implode(', ', array_unique($con)) : '';
}

# Log files report
function create_dump(string $dir, array &$log, array $skip = []): void {
    if (is_dir($dir)) {
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if ($file == '.' || $file == '..') continue;
                $location = $dir.$file;
                $relative = ltrim(str_replace('\\', '/', $location), './');
                $ignore = false;
                foreach ($skip as $path) {
                    $path = trim(str_replace('\\', '/', (string)$path), '/');
                    if ($path === '') continue;
                    if ($relative === $path || str_starts_with($relative, $path.'/')) {
                        $ignore = true;
                        break;
                    }
                }
                if ($ignore) continue;
                if (filetype($location) == 'dir') {
                    create_dump($location.'/', $log, $skip);
                } else {
                    if (is_readable($location)) $log[$location] = md5_file($location);
                }
            }
            closedir($dh);
        }
    }
}

function write_dump(array $dump, string $file): bool {
    if ($fp = fopen($file, 'wb')) {
        $new = '';
        foreach ($dump as $location => $md5) $new .= $location.'||'.$md5."\n";
        flock($fp, 2);
        fwrite($fp, $new);
        flock($fp, 3);
        fclose($fp);
    }
    return ($fp) ? true : false;
}

function write_log(mixed $log, string $file): bool {
    global $conf;
    if ($fp = fopen($file, 'ab')) {
        $log = ($log) ? implode("\n", $log) : _NO;
        flock($fp, 2);
        fwrite($fp, $log."\n"._DATE.': '.date(_TIMESTRING)."\n---\n");
        flock($fp, 3);
        fclose($fp);
        if (file_exists($file) && filesize($file) > $conf['security']['log_size']) {
            addCompress(LOGS_DIR, $file, 'dump_log_'.date('Y-m-d_H-i').'.log', 'auto', true);
        }
    }
    return ($fp) ? true : false;
}

function diff_dump(array $dump, array $old, array $skip = []): array|false {
    $log = [];
    $skip = array_map(static fn($path): string => trim(str_replace('\\', '/', (string)$path), '/'), $skip);
    foreach ($old as $string) {
        list($location, $md5) = explode('||', trim($string));
        $relative = ltrim(str_replace('\\', '/', $location), './');
        $ignore = false;
        foreach ($skip as $path) {
            if ($path === '') continue;
            if ($relative === $path || str_starts_with($relative, $path.'/')) {
                $ignore = true;
                break;
            }
        }
        if ($ignore) continue;
        $new[$location] = $md5;
    }
    foreach ($new as $location => $md5) {
        if (!isset($dump[$location])) $log[] = _D_DEL.': '.$location;
    }
    foreach ($dump as $location => $md5) {
        $relative = ltrim(str_replace('\\', '/', $location), './');
        $ignore = false;
        foreach ($skip as $path) {
            if ($path === '') continue;
            if ($relative === $path || str_starts_with($relative, $path.'/')) {
                $ignore = true;
                break;
            }
        }
        if ($ignore) continue;
        if (!isset($new[$location])) {
            $log[] = _D_NEW.': '.$location;
        } elseif ($new[$location] != $dump[$location]) {
            $log[] = _D_EDIT.': '.$location;
        }
    }
    return (count($log) > 0) ? $log : false;
}

# Executes a file scan task and returns scheduler metadata
# storage and node_modules are excluded by default: the first is runtime the site rewrites by itself, the second a development dependency absent from a delivered site
# Both only produce noise an integrity report cannot act on
function addFilescanTask(): array {
 global $conf, $tpl, $mailer;
    if (empty($conf['security']['log_d'])) return ['status' => 'failed', 'message' => 'File scan is disabled'];
    $sess_f = LOGS_DIR.'/dump_map.json';
    $state = [];
    if (file_exists($sess_f) && filesize($sess_f) != 0) {
        $json = file_get_contents($sess_f);
        $state = $json ? json_decode($json, true) : [];
        if (!is_array($state)) $state = [];
    }
    $now = time();
    $state['running'] = 1;
    $state['started_at'] = $now;
    if (!isset($state['last_run'])) $state['last_run'] = 0;
    file_put_contents($sess_f, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    $safe = ini_get('safe_mode') == '1' ? 1 : 0;
    if (!$safe && function_exists('set_time_limit')) set_time_limit(600);

    $dump = [];
    $skip = ['storage', 'node_modules'];
    foreach ([LOGS_DIR.'/dump.log', LOGS_DIR.'/dump_log.log'] as $path) {
        $skip[] = ltrim(str_replace('\\', '/', str_replace(BASE_DIR, '', $path)), '/');
    }
    $rawskip = str_replace(["\r\n", "\r"], "\n", (string)($conf['security']['dump_skip'] ?? ''));
    foreach (explode("\n", $rawskip) as $line) {
        $line = trim(str_replace('\\', '/', (string)$line));
        $line = preg_replace('#/+#', '/', $line);
        $line = preg_replace('#^\./#', '', (string)$line);
        $line = trim((string)$line, " \t\n\r\0\x0B");
        if ($line === '' || $line === '.' || $line === './') continue;
        if (str_contains($line, '..')) continue;
        $skip[] = $line;
    }
    $skip = array_values(array_unique($skip));
    create_dump('./', $dump, $skip);
    $dumpp = LOGS_DIR.'/dump.log';
    $logpp = LOGS_DIR.'/dump_log.log';
    if (file_exists($dumpp) && filesize($dumpp) != 0) {
        if ($log = diff_dump($dump, file($dumpp), $skip)) sort($log);
    } else {
        $log = false;
    }
    write_log($log, $logpp);
    write_dump($dump, $dumpp);
    $state['last_run'] = time();
    $state['running'] = 0;
    $state['started_at'] = 0;
    $state['last_count'] = count($dump);
    $state['last_size'] = file_exists($dumpp) ? (int)filesize($dumpp) : 0;
    $state['last_changes'] = is_array($log) ? count($log) : 0;
    file_put_contents($sess_f, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    if ($conf['security']['mail_d']) {
        $mail = '';
        foreach (($log ?: [_NO]) as $line) {
            $mail .= $tpl->getHtmlFrag('span', ['is_line_stack_item' => true, 'text' => $line, 'is_line_break' => true]);
        }
        $subj = $conf['sitename'].' - '._SECURITY;
        $mmsg = $tpl->getHtmlPart('message-block', [
            'title' => $conf['sitename'].' - '._SECURITY,
            'content_html' => $mail,
            'lines' => [
                ['label' => _DATE, 'value' => date(_TIMESTRING)],
            ],
        ]);
        $mailer->addQueue(['kind' => 'security', 'email' => $conf['adminmail'], 'title' => $subj, 'body' => $mmsg, 'sender' => $conf['adminmail'], 'prio' => 1]);
    }
    return [
        'status' => 'success',
        'message' => 'File scan completed',
        'extra' => [
            'last_count' => count($dump),
            'last_size' => file_exists($dumpp) ? (int)filesize($dumpp) : 0,
            'last_changes' => is_array($log) ? count($log) : 0,
        ],
    ];
}

# User and admin login report
function login_report(mixed $id, mixed $typ, mixed $login, mixed $pass): void {
 global $admin, $conf, $user;
    $id = ($id) ? 'admin' : 'user';
    if (($conf['security']['log_a'] && $id) || ($conf['security']['log_u'] && !$id)) {
        $typ = ($typ) ? _YES : _NO;
        $ip = getIp();
        $login = ($login) ? "\n"._NICKNAME.': '.substr($login, 0, 25) : '';
        $lpass = ($pass) ? "\n"._PASSWORD.': '.substr($pass, 0, 25) : '';
        $agent = getAgent();
        $url = filterText(getenv('REQUEST_URI'));
        $ladmin = ($admin) ? "\n"._ADMIN.': '.substr($admin[1], 0, 25) : '';
        $luser = ($user) ? "\n"._USER.': '.substr($user[1], 0, 25) : '';
        $path = LOGS_DIR.'/log_'.$id.'.log';
        if ($fhandle = fopen($path, 'ab')) {
            if (filesize($path) > $conf['security']['log_size']) {
                addCompress(LOGS_DIR, $path, 'log_'.$id.'_'.date('Y-m-d_H-i').'.log', 'auto', true);
            }
            fwrite($fhandle, _INPUT.': '.$typ."\n"._IP.': '.$ip.$login.$lpass.$ladmin.$luser."\n"._URL.': '.$url."\n"._BROWSER.': '.$agent."\n"._DATE.': '.date(_TIMESTRING)."\n---\n");
            fclose($fhandle);
        }
    }
}

# Check user acess
# The moderator check takes the module name itself, not whether it is set: isset() asked about a module literally named "1" and dropped its administrator into the visitor branch
function is_acess(string $ids): bool {
 global $db, $user, $conf;
    if ($ids) {
        $id = explode('|', $ids);
        if (is_moder((string)($conf['name'] ?? ''))) {
            $isa = true;
        } elseif (is_user() && $id[1]) {
            $uid = intval($user[0]);
            $mid = array_values(array_filter(array_map('intval', explode(',', (string)$id[1])), static fn($v) => $v > 0));
            if ($mid) {
                $pp = [];
                $pm = ['uid' => $uid];
                foreach ($mid as $k => $gid) {
                    $ph = 'g'.$k;
                    $pp[] = ':'.$ph;
                    $pm[$ph] = $gid;
                }
                $sql = 'SELECT COUNT(u.id) FROM '.PREFIX_DB.'_users AS u LEFT JOIN '.PREFIX_DB.'_groups AS g ON ((g.extra = 1 AND u.grp = g.id) OR (g.extra != 1 AND u.points >= g.points)) WHERE u.id = :uid AND g.id IN ('.implode(', ', $pp).')';
                list($uid) = $db->getSqlRow($db->getSqlQuery($sql, $pm));
            } else {
                $uid = 0;
            }
            $isa = ($uid) ? true : false;
        } elseif (is_user() && !$id[1]) {
            $isa = (1 >= $id[0]) ? true : false;
        } else {
            $isa = (0 >= $id[0] && !$id[1]) ? true : false;
        }
    } else {
        $isa = false;
    }
    return $isa;
}

# Format categories IDs
function catids(string $mod = '', int $id = 0): string {
 global $db;
    $mod     = filterVar($mod);
    $content = '';
    if ($mod) {
        $where  = 'WHERE modul = :modul';
        $params = ['modul' => $mod];
    } else {
        $where  = '';
        $params = [];
    }
    $result = $db->getSqlQuery('SELECT id, parent FROM '.PREFIX_DB.'_categories '.$where, $params);
    if ($db->getSqlRowCount($result) > 0) {
        while (list($cid, $parentid) = $db->getSqlRow($result)) $massiv[$cid] = [$parentid];
        foreach ($massiv as $key => $val) {
            $cont[$key] = $key;
            $flag = $val[0];
            while ($flag != 0) {
                $cont[$key] = $flag.', '.$cont[$key];
                $flag = intval($massiv[$flag][0]);
            }
            if ($id == $key) $content .= $cont[$key];
        }
        return $content;
    }
    return '';
}

# Format categories IDs from module
function catmids(string $modul, string $field): string {
 global $db, $conf, $locale;
    if ($conf['multilingual']) {
        $where  = 'WHERE modul = :modul AND (lang = :locale OR lang = \'\')';
        $params = ['modul' => $modul, 'locale' => $locale];
    } else {
        $where  = 'WHERE modul = :modul';
        $params = ['modul' => $modul];
    }
    $result = $db->getSqlQuery('SELECT id, pread FROM '.PREFIX_DB.'_categories '.$where.' ORDER BY id', $params);
    while (list($cid, $pread) = $db->getSqlRow($result)) if (is_acess($pread)) $catid[] = $cid;
    return isset($catid) ? 'AND '.$field.' IN ('.implode(', ', $catid).')' : '';
}

# Decode stored HTML entities to plain UTF-8 text for safe re-escaping at the output boundary
function getDecodedText(string $text): string {
    return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

# Length end filter
function cutstr(mixed $strip, int $size, string $type = ''): string {
    $strip = (string)$strip;
    $size = (int)$size;
    $end = match ($type) {
        '1' => '.',
        '2' => '',
        default => '...',
    };
    if (mb_strlen($strip, 'utf-8') > $size) $strip = mb_substr($strip, 0, $size, 'utf-8').$end;
    return $strip;
}

# Check module
function is_active(string $mod, string $view = ''): int {
    global $conf;
    static $list = null;
    if ($list === null) {
        $list = [];
        foreach ($conf['modules'] as $name => $item) {
            if (empty($item['active'])) continue;
            $mview = intval($item['view'] ?? 0);
            if (!isset($list[$mview])) $list[$mview] = [];
            $list[$mview][$name] = 1;
        }
    }
    $vnum = intval($view);
    return isset($list[$vnum][$mod]) ? 1 : 0;
}

# Format PHP code
function encode_php(array $text): string {
 global $conf, $tpl;
    static $sname;

    $replace = isset($text[2]) ? trim($text[2]) : trim($text[1]);
    $cname = isset($text[2]) ? filterVar($text[1]) : 'php';

    $from = ['bash', 'cpp', 'csharp', 'css', 'delphi', 'diff', 'groovy', 'java', 'jscript', 'php', 'plain', 'python', 'ruby', 'scala', 'sql', 'vb', 'xml'];
    $to = ['Bash', 'Cpp', 'CSharp', 'Css', 'Delphi', 'Diff', 'Groovy', 'Java', 'JScript', 'Php', 'Plain', 'Python', 'Ruby', 'Scala', 'Sql', 'Vb', 'Xml'];
    $cname = str_ireplace($from, $to, $cname);
    $ucname = strtolower($cname);

    $in = ['&#034;', '&quot;', '&#036;', '&dollar;', '&#038;', '&amp;', '&#039;', '&apos;', '&#060;', '&lt;', '&#062;', '&gt;', '&#092;', '&bsol;'];
    $out = ['"', '"', '$', '$', '&', '&', "'", "'", '<', '<', '>', '>', '\\', '\\'];
    $replace = ($conf['syntax'] <= 1) ? str_replace($in, $out, $replace) : $replace;
    $replace = preg_replace('#<br.*>#i', '', $replace);

    if (!$conf['syntax']) {
        if (preg_match("#<\?(php)?[^[:graph:]]#", $replace)) {
            $replace = highlight_string($replace, true);
        } else {
            $replace = preg_replace("#&lt;\?php&nbsp;#", '', highlight_string('<?php '.$replace, true));
        }
        $format = str_replace('&nbsp;&nbsp;', '&nbsp; ', $replace);
    } elseif ($conf['syntax'] == 1) {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $replace));
        $count = 1;
        $rows = '';
        foreach ($lines as $code) {
            $odd = (bool)($count % 2);
            if (preg_match("#<\?(php)?[^[:graph:]]#", $code)) {
                $chtml = highlight_string($code, true);
            } else {
                $chtml = preg_replace("#&lt;\?php&nbsp;#", '', highlight_string('<?php '.$code, true));
            }
            $rows .= $tpl->getHtmlFrag('code-row', ['is_odd' => $odd, 'row_num' => $count, 'code_html' => $chtml]);
            $count++;
        }
        $format = $tpl->getHtmlFrag('table', ['is_form' => true, 'rows_html' => str_replace('&nbsp;&nbsp;', '&nbsp; ', $rows)]);
    } elseif ($conf['syntax'] == 2) {
        if ($sname !== 'hljs') {
            $scripts = $tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/highlightjs/highlight.min.js', 'attr' => '']);
            $scripts .= $tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/highlightjs/highlight-line-numbers.min.js', 'attr' => '']);
            $scripts .= $tpl->getHtmlFrag('head-script-inline', ['js' => 'hljs.highlightAll();hljs.initLineNumbersOnLoad();']);
            $sname = 'hljs';
        } else {
            $scripts = '';
        }
        $hmap = ['jscript' => 'javascript', 'vb' => 'vbnet', 'plain' => 'plaintext'];
        $hlang = $hmap[$ucname] ?? $ucname;
        $format = $tpl->getHtmlFrag('code-highlight', ['scripts_html' => $scripts, 'lang' => $hlang, 'code_html' => $replace]);
    }
    return $tpl->getHtmlPart('div', ['is_code' => true, 'title' => htmlspecialchars($cname.' - '._CODE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'content_html' => $format ?? '']);
}

# Mail check
function checkemail(string $mail): array {
 global $stop;
    $mail = strtolower(filterText($mail, 1));
    if ((!$mail) || ($mail=='') || (!preg_match("#^[_\.a-z0-9-]+@([a-z0-9_-]+\.)+[a-z]{2,6}$#", $mail))) $stop[] = _ERROR1.' '._ERROR2.' (email@domain.com)';
    if ((strlen($mail) >= 4) && (substr($mail, 0, 4) == 'www.')) $stop[] = _ERROR1.' '._ERROR3.' (www.)';
    if (strrpos($mail, ' ') > 0) $stop[] = _ERROR1.' '._ERROR4.'.';
    return $stop ?? [];
}

# Format add block
# Format block
function render_blocks(string $side, string $bfile, string $blocktitle, string $content, mixed $bid, string $url): string {
    global $showbanners, $foot, $tpl;
    if ($url == '') {
        $blocktitle = getConst($blocktitle);
        $bicon = '';
        $bhref = '';
        if ($bfile != '') {
            $path = BASE_DIR.'/blocks/'.$bfile;
            if (file_exists($path)) {
                include($path);
            } else {
                $content = $tpl->getHtmlFrag('block-content', ['is_center' => true, 'content' => (string)_BLOCKPROBLEM]);
            }
        }
        if (!isset($content) || empty($content)) $content = $tpl->getHtmlFrag('block-content', ['is_center' => true, 'content' => (string)_BLOCKPROBLEM2]);
        switch($side) {
            case 'b':
            $showbanners = $content;
            break;
            case 'f':
            $foot = $content;
            break;
            case 'n':
            echo $content;
            break;
            case 'p':
            return $content;
            break;
            case 'o':
            return $tpl->getHtmlFrag('block-all', ['title' => $blocktitle, 'content' => $content, 'icon_name' => $bicon, 'href' => $bhref]);
            break;
            default:
            echo $tpl->getHtmlFrag('block-all', [
                'title' => $blocktitle,
                'content' => $content,
                'icon_name' => $bicon,
                'href' => $bhref,
                'is_before_content' => $side === 'c',
            ]);
            break;
        }
    } else {
        rss_load($bid);
    }
    return '';
}

# Handle an ajax rating request: enforce one vote per user or ip, persist the vote, and echo the refreshed rating block
function getRatingView(): void {
    global $db, $conf, $user;
    $id = getVar('get', 'id', 'num', 0);
    $typ = filterVar(getVar('get', 'typ', 'text', ''));
    $mod = filterVar(getVar('get', 'mod', 'text', ''));
    $rate = min(5, getVar('get', 'rate', 'num', 0));
    $stl = getVar('get', 'stl', 'num', 0);
    $con = explode('|', $conf['ratings'][strtolower($mod)] ?? '');
    $map = [
        'account' => ['_users', 'votes', 'tvotes', 0],
        'faq' => ['_faq', 'ratings', 'score', 8],
        'files' => ['_files', 'votes', 'tvotes', 12],
        'forum' => ['_forum', 'ratings', 'score', 15],
        'help' => ['_help', 'ratings', 'score', 0],
        'jokes' => ['_jokes', 'ratetot', 'rating', 20],
        'links' => ['_links', 'votes', 'tvotes', 24],
        'media' => ['_media', 'votes', 'tvotes', 27],
        'news' => ['_news', 'ratings', 'score', 33],
        'pages' => ['_pages', 'ratings', 'score', 37],
        'shop' => ['_products', 'votes', 'tvotes', 41],
    ];
    if (!$id || !$mod || !isset($map[$mod])) return;
    [$tab, $cnt, $scr, $pts] = $map[$mod];
    $ip = getIp();
    $cmod = substr($mod, 0, 2).'-'.$id;
    $cook = isset($_COOKIE[$cmod]) ? intval($_COOKIE[$cmod]) : 0;
    $uid = is_user() ? intval(substr($user[0], 0, 11)) : 0;
    $self = $mod === 'account' && $uid && $uid === $id;
    $where = $mod === 'account' ? 'id = :id' : "id = :id AND status != '0'";
    [$exists] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.$tab.' WHERE '.$where, ['id' => $id]));
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_rating WHERE time < :past AND modul = :mod', ['past' => time() - intval($con[0]), 'mod' => $mod]);
    $cq = 'SELECT COUNT(id) FROM '.PREFIX_DB."_rating WHERE (mid = :id AND modul = :mod AND ip = :ip) OR (mid = :id2 AND modul = :mod2 AND uid = :uid AND uid != '0')";
    [$num] = $db->getSqlRow($db->getSqlQuery($cq, ['id' => $id, 'mod' => $mod, 'ip' => $ip, 'id2' => $id, 'mod2' => $mod, 'uid' => $uid]));
    $voted = $cook == $id || $num > 0 || $self;
    if (!$voted && $rate && $exists) {
        setcookie($cmod, $id, time() + intval($con[0]));
        $pdo = $db->sqlconnid instanceof PDO ? $db->sqlconnid : null;
        if ($pdo) $pdo->beginTransaction();
        $ins = $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_rating (mid, modul, time, uid, ip) VALUES (:mid, :modul, :time, :uid, :ip)', ['mid' => $id, 'modul' => $mod, 'time' => time(), 'uid' => $uid, 'ip' => $ip]);
        if ($ins) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.$tab.' SET '.$cnt.' = '.$cnt.' + 1, '.$scr.' = '.$scr.' + :rate WHERE id = :id', ['rate' => $rate, 'id' => $id]);
            if ($pts && $uid) {
                [$spent] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_rating WHERE uid = :uid AND time > :since', ['uid' => $uid, 'since' => time() - 86400]));
                if ($spent <= 30) updatePoints($pts);
            }
        }
        if ($pdo) { $ins ? $pdo->commit() : $pdo->rollBack(); }
        $voted = true;
    }
    if (!$voted) return;
    [$votes, $total] = $db->getSqlRow($db->getSqlQuery('SELECT '.$cnt.', '.$scr.' FROM '.PREFIX_DB.$tab.' WHERE id = :id', ['id' => $id]));
    echo getRatingAsync(2, $id, $mod, $votes, $total, $typ, $stl);
}

# Format nummer page for Ajax
function getAsyncPager(string $frag, int $count, int $pages, int $page, int $mnum = 8, int $num = 1, string $ld = '', int $go = 0, string $op = '', string $id = '', int $cid = 0, string $typ = '', string $mod = ''): string {
    $target = static fn(int $i): array => [
        'query' => 'index.php?'.getQueryString(['go' => $go, 'op' => $op, 'id' => $cid, 'cid' => $i, 'typ' => $typ, 'dir' => $mod]),
        'target_id' => $id,
    ];
    return getTplPagerView($num, $pages, $mnum, $target, ['count' => $count, 'limit' => $page]);
}

# Answer the body region of one comment: the editor when the form is fetched, the rendered body when a save arrives, and the refusal that stopped either
# The editor is loaded with GET and the save arrives as POST, so a body is only ever read from a request that carries one and a refusal is written rather than returned
function updateComment(): void {
    global $conf, $tpl, $prs, $com;
    $id = getVar('req', 'id', 'num', 0);
    $typ = getVar('req', 'typ', 'num', 0);
    $text = trim(getVar('post', 'text', 'raw', ''));
    $edit = $com->updateComment($id, $text);
    if (!$edit['allow']) {
        echo $tpl->getHtmlFrag('alert', ['text' => sprintf(_PEDEND, intval($conf['comments']['edit'] / 60)), 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
        return;
    }
    if ($edit['error']) {
        echo $tpl->getHtmlFrag('alert', ['messages' => $edit['error'], 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
        return;
    }
    if (!$id || $edit['mod'] === '') return;
    if (!$text && $typ) {
        echo getTplAjaxTextarea([
            'obj' => 'com'.$id, 'go' => '1', 'op' => 'updateComment', 'id' => $id,
            'cid' => '0', 'typ' => '0', 'mod' => $edit['mod'], 'store' => 'comment.body', 'text' => $edit['body'], 'rows' => 10,
        ]);
        return;
    }
    echo $prs->filterContent($edit['body'], true, $edit['mod'], 2, 'breaks');
}

# Publish or hide one comment as a moderator and answer the comment itself, so the reader keeps the slice and the scroll position the action was taken from
# The swap is named by the response rather than by the link, so a refused transition leaves the element the request named exactly as it was
function updateCommentStatus(): void {
    global $tpl, $com;
    $id = getVar('req', 'id', 'num', 0);
    $typ = getVar('req', 'typ', 'num', 0);
    $numb = getVar('req', 'numb', 'num', 0);
    $row = $com->setStatus($id, (bool)$typ) ? $com->getComment($id) : [];
    if (!$row) {
        header('HX-Retarget: #repcstat');
        header('HX-Reswap: innerHTML');
        echo $tpl->getHtmlFrag('alert', ['text' => _ERROR, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
        return;
    }
    header('HX-Reswap: outerHTML');
    echo getCommentView($row, $numb, getPageToken());
}

# Remove one comment as a moderator and answer nothing, because the reader's slice loses exactly the element the request named
# The removal is named by the response, so a refused delete reports the refusal in the status zone and takes no element out of the page
function deleteComment(): void {
    global $tpl, $com;
    if (!$com->deleteComment(getVar('req', 'id', 'num', 0))) {
        header('HX-Retarget: #repcstat');
        header('HX-Reswap: innerHTML');
        echo $tpl->getHtmlFrag('alert', ['text' => _ERROR, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
        return;
    }
    header('HX-Reswap: delete');
}

# Voting result save
function updateVotingResult(): void {
 global $db, $conf, $user, $locale, $tpl;
    $id = getVar('post', 'id', 'num', 0);
    $body = isset($_POST['body']) && is_array($_POST['body']) ? $_POST['body'] : [];
    if ($conf['multilingual'] == 1) {
        $querylang = "(lang = :locale OR lang = '') AND time <= NOW() AND enddate >= NOW()";
        $qlang_params = ['locale' => $locale];
    } else {
        $querylang = 'time <= NOW() AND enddate >= NOW()';
        $qlang_params = [];
    }
    $result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_voting WHERE id = :id AND '.$querylang, array_merge(['id' => $id], $qlang_params));
    if ($db->getSqlRowCount($result) > 0) {
        if (!$body) {
            $meta = $tpl->getHtmlFrag('meta-refresh', ['secs' => '3', 'url' => 'index.php?name=voting&op=view&id='.$id]);
            $cont = $tpl->getHtmlFrag('alert', ['text' => _SEROR1, 'meta' => $meta, 'type' => 'warn', 'is_warn' => true]);
        } else {
            $ip = getIp();
            $past = time() - intval($conf['voting']['voting_t']);
            $cmod = substr('voting', 0, 2).'-'.$id;
            $cookies = (isset($_COOKIE[$cmod])) ? intval($_COOKIE[$cmod]) : '';
            $uid = (is_user()) ? intval(substr($user[0], 0, 11)) : 0;
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB."_rating WHERE time < :past AND modul = 'voting'", ['past' => $past]);
            list($num) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB."_rating WHERE (mid = :id AND modul = 'voting' AND ip = :ip) OR (mid = :id2 AND modul = 'voting' AND uid = :uid AND uid != '0')", ['id' => $id, 'ip' => $ip, 'id2' => $id, 'uid' => $uid]));
            if ($cookies == $id || $num > 0) {
                $meta = $tpl->getHtmlFrag('meta-refresh', ['secs' => '3', 'url' => 'index.php?name=voting&op=view&id='.$id]);
                $cont = $tpl->getHtmlFrag('alert', ['text' => _SEROR2, 'meta' => $meta, 'type' => 'warn', 'is_warn' => true]);
            } else {
                setcookie(substr('voting', 0, 2).'-'.$id, $id, time() + intval($conf['voting']['voting_t']));
                $new = time();
                $inserted = $db->getSqlQuery('INSERT INTO '.PREFIX_DB."_rating (mid, modul, time, uid, ip) VALUES (:mid, 'voting', :time, :uid, :ip)", ['mid' => $id, 'time' => $new, 'uid' => $uid, 'ip' => $ip]);
                if ($inserted) {
                    list($answer) = $db->getSqlRow($db->getSqlQuery('SELECT answer FROM '.PREFIX_DB.'_voting WHERE id = :id', ['id' => $id]));
                    $answer = explode('|', $answer);
                    for ($q = 0; $q < count($answer); $q++) {
                        if ($answer[$q] != '') {
                            foreach ($body as $val) {
                                if ($val != '' && $val == $q + 1) {
                                    $isansw = 1;
                                    break;
                                } else {
                                    $isansw = 0;
                                }
                            }
                            $answ[] = ($isansw) ? $answer[$q] + 1 : $answer[$q];
                        }
                    }
                    $answ = implode('|', $answ);
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_voting SET answer = :answer WHERE id = :id', ['answer' => $answ, 'id' => $id]);
                    updatePoints(42);
                }
                $votid = filterVar(getVar('get', 'votid', 'text', 'voting')) ?: 'voting';
                $cont = getVotingView($id, $votid);
            }
        }
    } else {
        $meta = $tpl->getHtmlFrag('meta-refresh', ['secs' => '3', 'url' => 'index.php?name=voting']);
        $cont = $tpl->getHtmlFrag('alert', ['text' => _ERROR, 'meta' => $meta, 'type' => 'warn', 'is_warn' => true]);
    }
    echo $cont;
}

# Update points
function updatePoints(int $id, int $uid = 0, bool $del = false): void {
 global $db, $conf, $user;
    $uid = $uid ?: (is_user() ? intval($user[0]) : 0);
    if ($id && $uid && $conf['users']['point'] == 1) {
        $upoints = explode(',', $conf['users']['points']);
        $a       = $id - 1;
        $delta   = isset($upoints[$a]) ? intval($upoints[$a]) : 0;
        $delta   = $del ? -$delta : $delta;
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET points = points + :delta WHERE id = :uid', ['delta' => $delta, 'uid' => $uid]);
    }
}

# Add action points once per (event, item, user/ip) within a retention window; reuses the _rating dedup table
function addPointsAction(string $event, int $mid, int $pts, int $ttl = 2592000): void {
    global $db, $user;
    if (!$mid || !$pts || !is_user()) return;
    $uid = intval($user[0]);
    $ip  = getIp();
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_rating WHERE time < :past AND modul = :event', ['past' => time() - $ttl, 'event' => $event]);
    [$num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB."_rating WHERE (mid = :mid AND modul = :event AND ip = :ip) OR (mid = :mid2 AND modul = :event2 AND uid = :uid AND uid != '0')", ['mid' => $mid, 'event' => $event, 'ip' => $ip, 'mid2' => $mid, 'event2' => $event, 'uid' => $uid]));
    if ($num > 0) return;
    $ins = $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_rating (mid, modul, time, uid, ip) VALUES (:mid, :event, :time, :uid, :ip)', ['mid' => $mid, 'event' => $event, 'time' => time(), 'uid' => $uid, 'ip' => $ip]);
    if ($ins) updatePoints($pts);
}

# Promote pending items to active (status 0 -> 1) and credit submission points to their authors in one step
function setContentActive(string $tab, array $ids, int $pts): void {
    global $db;
    $ids = array_values(array_filter(array_map('intval', $ids), static fn($val): bool => $val > 0));
    if (!$ids) return;
    $keys = [];
    $pars = [];
    foreach ($ids as $pos => $val) {
        $keys[] = ':id'.$pos;
        $pars['id'.$pos] = $val;
    }
    $in = implode(', ', $keys);
    if ($pts) {
        $res = $db->getSqlQuery('SELECT uid FROM '.PREFIX_DB.$tab.' WHERE id IN ('.$in.") AND status = '0' AND uid > 0", $pars);
        while ([$uid] = $db->getSqlRow($res)) updatePoints($pts, (int)$uid);
    }
    $db->getSqlQuery('UPDATE '.PREFIX_DB.$tab." SET status = '1' WHERE id IN (".$in.')', $pars);
}

# Resample an image down to the requested width through GD and write it to the thumb path; returns the source path unchanged when GD, the format or the write is unavailable
function getImageThumb(string $file, string $dest, int $width): string {
    if (!function_exists('imagecreatetruecolor') || $width < 1) return $file;
    $info = getimagesize($file);
    if (!is_array($info) || $info[0] < 1 || $info[1] < 1) return $file;
    $type = $info[2];
    $simg = match ($type) {
        IMAGETYPE_GIF => function_exists('imagecreatefromgif') ? imagecreatefromgif($file) : false,
        IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? imagecreatefromjpeg($file) : false,
        IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? imagecreatefrompng($file) : false,
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($file) : false,
        IMAGETYPE_AVIF => function_exists('imagecreatefromavif') ? imagecreatefromavif($file) : false,
        default => false,
    };
    if (!$simg) return $file;
    $swid = $info[0];
    $shei = $info[1];
    $dhei = max(1, (int)round($shei * $width / $swid));
    $dimg = imagecreatetruecolor($width, $dhei);
    imagesavealpha($dimg, true);
    $back = imagecolorallocatealpha($dimg, 255, 255, 255, 127);
    imagefill($dimg, 0, 0, $back);
    imagecopyresampled($dimg, $simg, 0, 0, 0, 0, $width, $dhei, $swid, $shei);
    $done = match ($type) {
        IMAGETYPE_GIF => imagegif($dimg, $dest),
        IMAGETYPE_JPEG => imagejpeg($dimg, $dest),
        IMAGETYPE_PNG => imagepng($dimg, $dest),
        IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($dimg, $dest) : false,
        IMAGETYPE_AVIF => function_exists('imageavif') ? imageavif($dimg, $dest) : false,
        default => false,
    };
    return $done ? $dest : $file;
}
