<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE') && !defined('ADMIN_FILE')) die('Illegal file access');

define('BLOCK_FILE', true);
define('FUNC_FILE', true);

# Configuration directory
define('CONFIG_DIR', BASE_DIR.'/config');

# Storage directories for internal data
define('BACKUP_DIR', BASE_DIR.'/storage/backup');
define('CACHE_DIR', BASE_DIR.'/storage/cache');
define('COUNTER_DIR', BASE_DIR.'/storage/counter');
define('LOGS_DIR', BASE_DIR.'/storage/logs');
define('SITEMAP_DIR', BASE_DIR.'/storage/sitemap');

# Uploads directory for user content
define('UPLOADS_DIR', BASE_DIR.'/uploads');

# Load all /config/*.php into a unified $conf array; apply local.php overrides
function getConfig(): array {
    $conf = [];
    $default_files = [];
    $files = glob(CONFIG_DIR.'/*.php');
    if ($files === false) $files = [];
    sort($files);
    $skip = ['local.php', 'system.php', 'header.php', 'chmod.php'];
    foreach ($files as $file) {
        if (in_array(basename($file), $skip)) continue;
        $data = require $file;
        if (is_array($data)) {
            $conf = array_merge($conf, $data);
            $default_files[] = $file;
        }
    }
    $conf['dev_mode'] ??= false;
    $local_file = CONFIG_DIR.'/local.php';
    $local = [];
    if (file_exists($local_file)) {
        $data = include $local_file;
        if (is_array($data)) $local = $data;
    }
    $stored_finger = $local['_meta']['base_fingerprint'] ?? '';
    unset($local['_meta']);
    if ($local !== []) $conf = filterConfigMerge($conf, $local);
    $finger = getConfigFingerprint($default_files);
    if ($conf['dev_mode'] && $finger !== $stored_finger) {
        setConfigFingerprint($local_file, $finger);
    }
    return $conf;
}

# Safe recursive merge: override only existing keys with matching type; ignore unknown keys
function filterConfigMerge(array $base, array $override): array {
    foreach ($override as $key => $value) {
        if (!array_key_exists($key, $base)) continue;
        if (is_array($base[$key]) && is_array($value)) {
            $base[$key] = filterConfigMerge($base[$key], $value);
        } elseif (gettype($base[$key]) === gettype($value)) {
            $base[$key] = $value;
        }
    }
    return $base;
}

# Compute sha1 fingerprint over config files; includes filename to detect additions/removals
function getConfigFingerprint(array $files): string {
    $hash = '';
    foreach ($files as $file) {
        if (!is_file($file)) continue;
        $file_hash = sha1_file($file);
        if ($file_hash !== false) $hash .= basename($file).$file_hash;
    }
    return sha1($hash);
}

# Read local.php as array, update only _meta.base_fingerprint, write atomically
function setConfigFingerprint(string $local_file, string $fingerprint): void {
    $data = [];
    if (file_exists($local_file)) {
        $existing = include $local_file;
        if (is_array($existing)) $data = $existing;
    }
    $data['_meta']['base_fingerprint'] = $fingerprint;
    $exported = var_export($data, true);
    $exported = preg_replace('/array \(/', '[', $exported);
    $exported = preg_replace('/^(\s*)\)(,?)$/m', '$1]$2', $exported);
    $content = "<?php\nreturn ".$exported.";\n";
    $tmp = $local_file.'.tmp';
    $is_new = !file_exists($local_file);
    if (file_put_contents($tmp, $content, LOCK_EX) !== false) {
        if (!rename($tmp, $local_file)) {
            unlink($tmp);
        } elseif ($is_new) {
            chmod($local_file, 0640);
        }
    }
}

# Returns the scheduler configuration with guaranteed top-level defaults
function getSchedulerConfig(): array {
    global $conf;
    $cfg = $conf['scheduler'] ?? [];
    if (!is_array($cfg)) $cfg = [];
    $cfg['active'] = (string)($cfg['active'] ?? '1');
    $cfg['pseudo'] = (string)($cfg['pseudo'] ?? '1');
    $cfg['token'] = (string)($cfg['token'] ?? '');
    $cfg['cron_timeout'] = (string)($cfg['cron_timeout'] ?? '600');
    $cfg['trigger_cooldown'] = (string)($cfg['trigger_cooldown'] ?? '60');
    $cfg['lock_timeout'] = (string)($cfg['lock_timeout'] ?? '1800');
    $cfg['jobs'] = (isset($cfg['jobs']) && is_array($cfg['jobs'])) ? $cfg['jobs'] : [];
    return $cfg;
}

# Returns the scheduler runtime directory and creates it on demand
function getSchedulerDir(): string {
    $path = LOGS_DIR.'/scheduler';
    if (!is_dir($path)) mkdir($path, 0750, true);
    return $path;
}

# Returns the scheduler state file path for a named job
function getSchedulerFile(string $name): string {
    return getSchedulerDir().'/'.preg_replace('#[^a-z]#', '', strtolower($name)).'.json';
}

# Returns the scheduler heartbeat file path
function getSchedulerBeat(): string {
    return getSchedulerDir().'/heartbeat.json';
}

# Returns the default runtime state structure for any scheduler job
function getSchedulerBase(): array {
    return [
        'running' => 0,
        'started_at' => 0,
        'last_run' => 0,
        'last_success' => 0,
        'last_status' => 'idle',
        'last_message' => '',
        'last_error' => '',
        'last_duration' => 0,
        'last_trigger' => '',
        'fail_count' => 0,
    ];
}

# Returns a normalized scheduler job config
function getSchedulerJob(string $name): array {
    $cfg = getSchedulerConfig();
    $job = $cfg['jobs'][$name] ?? [];
    if (!is_array($job)) $job = [];
    $job['name'] = $name;
    $job['title'] = (string)($job['title'] ?? $name);
    $job['type'] = (string)($job['type'] ?? 'system');
    $job['active'] = (string)($job['active'] ?? '0');
    $job['handler'] = (string)($job['handler'] ?? '');
    $job['schedule'] = trim((string)($job['schedule'] ?? ''));
    $job['priority'] = (string)($job['priority'] ?? '100');
    $job['lock_timeout'] = (string)($job['lock_timeout'] ?? $cfg['lock_timeout']);
    $job['manual'] = (string)($job['manual'] ?? '1');
    $job['settings'] = (isset($job['settings']) && is_array($job['settings'])) ? $job['settings'] : [];
    return $job;
}

function getSchedulerSettings(string $name): array {
    $job = getSchedulerJob($name);
    return (isset($job['settings']) && is_array($job['settings'])) ? $job['settings'] : [];
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

# Returns whether a unix timestamp matches a scheduler cron expression
function checkSchedulerCronMatch(string $schedule, int $time): bool {
    $schedule = getSchedulerSchedule($schedule);
    if ($schedule === '') return false;
    [$min, $hour, $mday, $mon, $wday] = explode(' ', $schedule);
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

# Returns the next runtime timestamp for a scheduler job from its cron schedule
function getSchedulerNextTime(array $job, array $state = [], ?int $from = null): int {
    $schedule = getSchedulerSchedule($job);
    if ($schedule === '') return 0;
    $from = $from ?? time();
    $next = $from - ($from % 60) + 60;
    $max = $next + (60 * 60 * 24 * 366 * 5);
    while ($next <= $max) {
        if (checkSchedulerCronMatch($schedule, $next)) return $next;
        $next += 60;
    }
    return 0;
}

# Returns the current planned run timestamp for a scheduler job based on last execution or current time
function getSchedulerPlannedTime(array $job, array $state = []): int {
    $last = (int)($state['last_run'] ?? 0);
    $from = ($last > 0) ? $last : time();
    return getSchedulerNextTime($job, $state, $from);
}

# Returns all scheduler jobs normalized and sorted by priority and key
function getSchedulerJobs(): array {
    $cfg = getSchedulerConfig();
    $arr = [];
    foreach ($cfg['jobs'] as $key => $val) {
        if (!is_string($key) || $key === '') continue;
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

# Returns the runtime state for a scheduler job merged with defaults
function getSchedulerState(string $name): array {
    $file = getSchedulerFile($name);
    $state = getSchedulerBase();
    if (!is_file($file) || filesize($file) === 0) return $state;
    $json = file_get_contents($file);
    if ($json === false || $json === '') return $state;
    $data = json_decode($json, true);
    if (!is_array($data)) return $state;
    return array_replace($state, $data);
}

# Writes the runtime state for a scheduler job atomically
function setSchedulerState(string $name, array $state): bool {
    $file = getSchedulerFile($name);
    $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) return false;
    return file_put_contents($file, $json, LOCK_EX) !== false;
}

# Returns whether the scheduler job lock is still valid
function checkSchedulerLock(string $name, array $job = [], array $state = []): bool {
    if ($job === []) $job = getSchedulerJob($name);
    if ($state === []) $state = getSchedulerState($name);
    if (empty($state['running']) || empty($state['started_at'])) return false;
    $time = max(60, (int)($job['lock_timeout'] ?? 0));
    return (time() - (int)$state['started_at']) < $time;
}

# Returns whether the scheduler job is due for execution
function checkSchedulerDue(string $name, array $job = [], array $state = []): bool {
    if ($job === []) $job = getSchedulerJob($name);
    if ($state === []) $state = getSchedulerState($name);
    if ((int)($job['active'] ?? 0) !== 1) return false;
    if (checkSchedulerLock($name, $job, $state)) return false;
    $next = getSchedulerPlannedTime($job, $state);
    return $next > 0 && $next <= time();
}

# Acquires the scheduler lock for a named job and persists trigger metadata
function addSchedulerLock(string $name, string $type): bool {
    $job = getSchedulerJob($name);
    $state = getSchedulerState($name);
    if (checkSchedulerLock($name, $job, $state)) return false;
    $state['running'] = 1;
    $state['started_at'] = time();
    $state['last_run'] = time();
    $state['last_trigger'] = $type;
    $state['last_status'] = 'running';
    $state['last_message'] = '';
    $state['last_error'] = '';
    return setSchedulerState($name, $state);
}

# Releases the scheduler lock and persists final status plus any extra runtime data
function deleteSchedulerLock(string $name, string $stat, string $mess = '', array $extra = []): bool {
    $job = getSchedulerJob($name);
    $state = array_replace(getSchedulerState($name), $extra);
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
    } else {
        $state['fail_count'] = (int)($state['fail_count'] ?? 0) + 1;
        if ($mess !== '') $state['last_error'] = $mess;
    }
    return setSchedulerState($name, $state);
}

# Writes a scheduler heartbeat marker for cron, pseudo-cron, or manual triggers
function addSchedulerHeartbeat(string $type): void {
    $data = [
        'trigger' => $type,
        'time' => time(),
    ];
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($json)) file_put_contents(getSchedulerBeat(), $json, LOCK_EX);
}

# Returns whether a recent cron heartbeat exists within the configured timeout
function checkSchedulerCronAlive(): bool {
    $cfg = getSchedulerConfig();
    $file = getSchedulerBeat();
    if (!is_file($file) || filesize($file) === 0) return false;
    $json = file_get_contents($file);
    if ($json === false || $json === '') return false;
    $data = json_decode($json, true);
    if (!is_array($data) || (($data['trigger'] ?? '') !== 'cron')) return false;
    return (time() - (int)($data['time'] ?? 0)) < max(60, (int)$cfg['cron_timeout']);
}

# Returns whether the current request may execute the scheduler runner
function checkSchedulerAccess(string $type, string $stok): bool {
    global $conf;
    if (isAdmin(true)) return true;
    $scfg = getSchedulerConfig();
    $stkn = (string)($scfg['token'] ?? '');
    $psok = ($type === 'pseudo' && checkSiteToken($stok, 'scheduler'));
    $tkok = ($stkn !== '' && hash_equals($stkn, $stok));
    return $psok || $tkok;
}

# Returns the pseudo-trigger throttle file path
function getSchedulerTrig(): string {
    return getSchedulerDir().'/trigger.json';
}

# Returns a signed pseudo-trigger URL when the next due job should be started asynchronously
function addSchedulerTrigger(): string {
    global $conf;
    $cfg = getSchedulerConfig();
    if ((int)$cfg['active'] !== 1 || (int)$cfg['pseudo'] !== 1) return '';
    if (checkSchedulerCronAlive()) return '';
    $job = getSchedulerNextJob();
    if (!$job) return '';
    $file = getSchedulerTrig();
    $last = 0;
    if (is_file($file) && filesize($file) !== 0) {
        $json = file_get_contents($file);
        $data = $json ? json_decode($json, true) : [];
        if (is_array($data)) $last = (int)($data['time'] ?? 0);
    }
    $cool = max(15, (int)$cfg['trigger_cooldown']);
    if ($last > 0 && (time() - $last) < $cool) return '';
    $json = json_encode(['time' => time(), 'job' => (string)($job['name'] ?? '')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($json)) file_put_contents($file, $json, LOCK_EX);
    return 'index.php?go=3&op=scheduler&trigger=pseudo&token='.rawurlencode(getSiteToken('scheduler'));
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
        if ($job['handler'] === '') return null;
        return checkSchedulerDue($name, $job) ? $job : null;
    }
    foreach (getSchedulerJobs() as $job) {
        $name = (string)($job['name'] ?? '');
        if ($name === '' || $job['handler'] === '') continue;
        if (checkSchedulerDue($name, $job)) return $job;
    }
    return null;
}

# Executes the next due scheduler job or a named job and returns a structured result
function addSchedulerRun(?string $name = null, string $type = 'manual'): array {
    $cfg = getSchedulerConfig();
    if ((int)$cfg['active'] !== 1) return ['status' => 'disabled', 'message' => 'Scheduler is disabled'];
    if ($name !== null && $name !== '' && $type === 'manual') {
        $job = getSchedulerJob($name);
        if (($job['handler'] ?? '') === '' && ($job['type'] ?? '') !== 'custom') $job = null;
    } else {
        $job = getSchedulerNextJob($name);
    }
    if (!$job) return ['status' => 'idle', 'message' => 'No due jobs'];
    $name = (string)$job['name'];
    if (!addSchedulerLock($name, $type)) return ['status' => 'locked', 'message' => 'Job is already running', 'job' => $name];
    addSchedulerHeartbeat($type);
    $func = (string)$job['handler'];
    try {
        if (($job['type'] ?? '') === 'custom') {
            $data = addSchedulerCustom($job);
        } else {
            $data = function_exists($func) ? $func() : ['status' => 'failed', 'message' => 'Handler not found'];
        }
        if (!is_array($data)) $data = ['status' => 'failed', 'message' => 'Invalid handler result'];
    } catch (Throwable $error) {
        $data = ['status' => 'failed', 'message' => $error->getMessage()];
    }
    $stat = (string)($data['status'] ?? 'failed');
    $mess = (string)($data['message'] ?? '');
    $extra = (isset($data['extra']) && is_array($data['extra'])) ? $data['extra'] : [];
    deleteSchedulerLock($name, $stat, $mess, $extra);
    $data['job'] = $name;
    return $data;
}

# Executes the scheduler database backup task and returns runtime metadata
function addSchedulerBackup(): array {
    return addBackupTask();
}

# Executes the scheduler file scan task and returns runtime metadata
function addSchedulerFilescan(): array {
    return addFilescanTask();
}

# Executes the scheduler sitemap task and returns runtime metadata
function addSchedulerSitemap(): array {
    return doSitemap(true);
}

# Executes the scheduler newsletter task and returns runtime metadata
function addSchedulerNewsletter(): array {
    return updateNewsletter(true);
}

# System file include
require_once BASE_DIR.'/core/security.php';

if (defined('MODULE_FILE')) {
    require_once BASE_DIR.'/core/user.php';
} elseif (defined('ADMIN_FILE')) {
    require_once BASE_DIR.'/core/admin.php';
}

$theme = getTheme();
if (is_file(BASE_DIR.'/templates/'.$theme.'/index.php')) require_once BASE_DIR.'/templates/'.$theme.'/index.php';
require_once BASE_DIR.'/core/template.php';

# Format block
function getBlocks(string $side, string $fly = ''): void {
    global $db, $conf, $locale, $name, $home, $pos, $b_id, $bfile;
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
        $result = $db->getSqlQuery('SELECT id, bkey, title, content, url, bfile, view, expire, action, bpos, which FROM '.PREFIX_DB."_blocks WHERE status = '1' ".$querylang.' ORDER BY weight ASC', $qlang_params);
        while(list($bid, $bkey, $title, $content, $url, $bfile, $view, $expire, $action, $bpos, $which) = $db->getSqlRow($result)) {
            $bid = intval($bid);
            $content = filterReplaceText(filterMarkdown($content, 'all', false), 'all');
            $view = intval($view);
            $where_mas = explode(',', $which);
            $barr[] = [$bid, $bkey, $title, $content, $url, $bfile, $view, $expire, $action, $bpos, $where_mas];
        }
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

# Convert Markdown+BB source to safe HTML.
# Safe mode (true): escapes HTML, URL allowlist - for user content.
# Safe mode (false): allows raw HTML blocks + admin BB tags.
function filterMarkdown(string $src, string $mod = '', bool $safe = true): string {
    global $conf;
    static $md = null;
    $md ??= new class {

        private array  $stash = [];
        private string $salt  = '';
        private array  $hids  = [];
        private string $mod   = 'all';

        public function filterHtml(string $src, bool $safe, string $mod): string {
            $this->stash = [];
            $this->hids  = [];
            $this->salt  = bin2hex(random_bytes(4));
            $this->mod   = $mod !== '' ? strtolower($mod) : 'all';
            $out = $this->filterMain($src, $safe);
            $sentinel = "\x02{$this->salt}:";
            while (str_contains($out, $sentinel)) {
                $prev = $out;
                $out  = strtr($out, $this->stash);
                if ($out === $prev) break;
            }
            return trim($out);
        }

        // Add a comma before each next VALUES row (except first row and after split markers)
        private function filterNest(string $src, bool $safe): string {
            return $this->filterMain($src, $safe);
        }

        private function filterMain(string $src, bool $safe): string {
            $src = str_replace(["\r\n", "\r"], "\n", $src);
            $src = $this->filterBbBlocks($src, $safe);
            $src = $this->filterFencedCode($src);
            if ($safe) $src = $this->filterIndentedCode($src);
            $src = $this->filterInlineCode($src);
            $src = $this->filterBlocks($src, $safe);
            return $src;
        }

        // Helpers

        private function addStash(string $html): string {
            $key = "\x02{$this->salt}:".count($this->stash)."\x03";
            $this->stash[$key] = $html;
            return $key;
        }

        private function filterEsc(string $s): string {
            return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        private function filterDec(string $s): string {
            return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        private function filterText(string $s): string {
            $pat   = '/(\x02'.preg_quote($this->salt, '/').':\d+\x03)/';
            $parts = preg_split($pat, $s, -1, PREG_SPLIT_DELIM_CAPTURE) ?? [$s];
            return implode('', array_map(fn($p) => preg_match($pat, $p) ? $p : $this->filterEsc($p), $parts));
        }

        private function filterInline(string $txt, bool $safe): string {
            return $this->filterInlines($safe ? $this->filterText($txt) : $txt, $safe);
        }

        private function filterUrl(string $url): string {
            $url = trim($url);
            return preg_match('/^(?:https?:\/\/|mailto:|[\/\.#?])/i', $url) ? $url : '#';
        }

        // BB blocks (stash before Markdown parsing)

        private function filterBbBlocks(string $src, bool $safe): string {
            // Add a comma before each next VALUES row (except first row and after split markers)
            $src = preg_replace('/\[hr\]/si', $this->addStash('<hr>'), $src) ?? $src;

            // Add a comma before each next VALUES row (except first row and after split markers)
            $src = preg_replace('/\[li\]/si', $this->addStash('&bull; '), $src) ?? $src;

            // *01 smilies
            if (preg_match('/\*(\d{2})/', $src)) {
                $src = preg_replace_callback(
                    '/\*(\d{2})/',
                    function(array $m): string {
                        $num = $this->filterEsc($m[1]);
                        $img = img_find('smilies/'.$num.'.gif');
                        return $this->addStash('<img src="'.$this->filterEsc($img).'" alt="'._SMILIE.' - '.$num.'" title="'._SMILIE.' - '.$num.'">');
                    },
                    $src
                ) ?? $src;
            }

            // Add a comma before each next VALUES row (except first row and after split markers)
            $src = preg_replace_callback(
                '/\[usehtml\](.*?)\[\/usehtml\]/si',
                function(array $m) use ($safe): string {
                    if ($safe) return $m[0];
                    $html = htmlspecialchars_decode(replace_break($m[1]), ENT_QUOTES);
                    return $this->addStash($html);
                },
                $src
            ) ?? $src;

            // Add a comma before each next VALUES row (except first row and after split markers)
            $src = preg_replace_callback(
                '/\[usephp\](.*?)\[\/usephp\]/si',
                function(array $m) use ($safe): string {
                    if ($safe) return $m[0];
                    $rep = str_replace(['&#036;', '&#092;'], ['$', '\\'], $m[1]);
                    ob_start();
                    try {
                        eval(htmlspecialchars_decode(replace_break($rep), ENT_QUOTES));
                        $out = ob_get_clean();
                    } catch (Throwable $ex) {
                        ob_end_clean();
                        $out = '';
                    }
                    return $this->addStash((string)$out);
                },
                $src
            ) ?? $src;

            // [tabs=n]...[tab=title]...[/tab]...[/tabs]
            $src = preg_replace_callback(
                '/\[tabs=(.*?)\](.*?)\[\/tabs\]/si',
                function(array $m) use ($safe): string {
                    $num = (int)trim($m[1]);
                    $rep = (string)$m[2];
                    $cnt = preg_match_all('/\[tab=([\pL0-9_\-\.\"\s]+)\](.*?)\[\/tab\]/siu', $rep, $mm);
                    if (!$cnt) return $m[0];
                    $ttl = [];
                    $txt = [];
                    for ($i = 0; $i < $cnt; $i++) {
                        $ttl[] = $mm[1][$i];
                        $txt[] = $this->filterNest($mm[2][$i], $safe);
                    }
                    return $this->addStash((string)getNaviTabs($num, 'tab', $ttl, $txt));
                },
                $src
            ) ?? $src;

            // [code]...[/code]
            $src = preg_replace_callback(
                '/\[code\](.*?)\[\/code\]/si',
                function(array $m): string {
                    $txt  = str_replace('?', '&#063;', (string)$m[1]);
                    $html = setTemplateBasic('code', ['{%title%}' => _CODE, '{%content%}' => $this->filterEsc($txt)]);
                    return $this->addStash((string)$html);
                },
                $src
            ) ?? $src;

            // [code=lang]...[/code]
            $src = preg_replace_callback(
                '/\[code=(.*?)\](.*?)\[\/code\]/si',
                function(array $m): string {
                    return $this->addStash((string)encode_php([0 => $m[0], 1 => $m[1], 2 => $m[2]]));
                },
                $src
            ) ?? $src;

            // [php]...[/php]
            $src = preg_replace_callback(
                '/\[php\](.*?)\[\/php\]/si',
                function(array $m): string {
                    return $this->addStash((string)encode_php([0 => $m[0], 1 => $m[1]]));
                },
                $src
            ) ?? $src;

            // Add a comma before each next VALUES row (except first row and after split markers)
            while (preg_match('/\[quote\](.*?)\[\/quote\]/si', $src)) {
                $src = preg_replace_callback(
                    '/\[quote\](.*?)\[\/quote\]/si',
                    function(array $m) use ($safe): string {
                        $txt  = $this->filterNest($m[1], $safe);
                        $html = setTemplateBasic('quote', ['{%title%}' => _QUOTE, '{%text%}' => $txt]);
                        return $this->addStash((string)$html);
                    },
                    $src
                ) ?? $src;
            }

            // Add a comma before each next VALUES row (except first row and after split markers)
            while (preg_match('/\[hide\](.*?)\[\/hide\]/si', $src)) {
                $src = preg_replace_callback(
                    '/\[hide\](.*?)\[\/hide\]/si',
                    function(array $m) use ($safe): string {
                        $show = (defined('ADMIN_FILE') || is_user());
                        $txt  = $show ? $this->filterNest($m[1], $safe) : (string)_HIDETEXT;
                        $html = setTemplateBasic('hide', ['{%title%}' => _HIDE, '{%text%}' => $txt]);
                        return $this->addStash((string)$html);
                    },
                    $src
                ) ?? $src;
            }

            // [attach=...]
            if (stripos($src, '[attach=') !== false) {
                $src = $this->filterAttach($src);
            }

            return $src;
        }

        private function filterAttach(string $src): string {
            global $conf;
            $mod = $this->mod !== '' ? $this->mod : 'all';

            if (stripos($src, 'rel=') !== false && stripos($src, 'width=') !== false) {
                $re = '/\[attach=([a-zA-Z0-9_\-\. ]+) align=([a-zA-Z]+) title=([\pL0-9_\-\.\"\s]+) width=([0-5]?[0-9]?[0-9]+) height=([0-5]?[0-9]?[0-9]+) rel=([a-zA-Z0-9_\-]+)\]/siu';
            } elseif (stripos($src, 'width=') !== false) {
                $re = '/\[attach=([a-zA-Z0-9_\-\. ]+) align=([a-zA-Z]+) title=([\pL0-9_\-\.\"\s]+) width=([0-5]?[0-9]?[0-9]+) height=([0-5]?[0-9]?[0-9]+)\]/siu';
            } else {
                $re = '/\[attach=([a-zA-Z0-9_\-\. ]+) align=([a-zA-Z]+) title=([\pL0-9_\-\.\"\s]+)\]/siu';
            }

            if (!preg_match_all($re, $src, $mm, PREG_SET_ORDER)) return $src;

            $con = explode('|', (string)($conf['uploads'][$mod] ?? ''));
            $twd = $con[6] ?? ($conf['uploads']['width'] ?? '250');
            $img = ['png', 'jpg', 'jpeg', 'gif', 'bmp'];

            foreach ($mm as $m) {
                $fn   = (string)$m[1];
                $al   = (string)$m[2];
                $tl   = (string)$m[3];
                $wd   = $m[4] ?? '';
                $hg   = $m[5] ?? '';
                $rl   = $m[6] ?? '';
                $ext  = strtolower((string)substr((string)strrchr($fn, '.'), 1));
                $file = 'uploads/'.$mod.'/'.$fn;
                $timg = $file;
                if ($tl === '' || strtolower($tl) === 'title') $tl = $fn;

                if (in_array($ext, $img, true)) {
                    $tfile = 'uploads/'.$mod.'/thumb/'.$fn;
                    $tdir  = 'uploads/'.$mod.'/thumb';
                    if ($mod !== '' && file_exists($file) && !file_exists($tfile)) {
                        if (!file_exists($tdir)) mkdir($tdir);
                        $ok   = create_img_gd($file, $tfile, $twd);
                        $timg = $ok ? $tfile : $file;
                    } else {
                        $timg = $tfile;
                    }
                    if (file_exists($file)) {
                        [$wd, $hg] = getimagesize($file);
                    } else {
                        $file = img_find('misc/no-image.png');
                        $timg = $file;
                    }
                }

                $tmp = $conf['filetype'][$ext] ?? '<a href="[src]" target="_blank" title="[title]">[title]</a>';
                $tmp = str_replace('[src]',    $file, $tmp);
                $tmp = str_replace('[tsrc]',   (string)$timg, $tmp);
                $tmp = (!empty($wd) && (int)$wd)
                     ? str_replace('[width]',  (string)$wd, $tmp)
                     : str_replace('[width]',  (string)($conf['uploads']['width'] ?? '500'), $tmp);
                $tmp = str_replace('[twidth]', (string)$twd, $tmp);
                $tmp = (!empty($hg) && (int)$hg)
                     ? str_replace('[height]', (string)$hg, $tmp)
                     : str_replace('[height]', (string)($conf['uploads']['height'] ?? '500'), $tmp);
                $tmp = str_replace('[align]',  $al, $tmp);
                $tmp = str_replace('[title]',  $tl, $tmp);
                $tmp = str_replace('[quot]',   '&quot;', $tmp);
                $tmp = str_replace('[rel]',    $rl !== '' ? $rl : 'alternate', $tmp);

                $src = str_replace($m[0], $this->addStash($tmp), $src);
            }

            return $src;
        }

        // Code protection

        private function filterFencedCode(string $src): string {
            return preg_replace_callback(
                '/(^(`{3,}|~{3,})[ \t]*([\w\-]*)[^\n]*\n(.*?)\n^\2[ \t]*$)/ms',
                function($m) {
                    $cls = $m[3] ? ' class="language-'.$this->filterEsc($m[3]).'"' : '';
                    return $this->addStash('<pre><code'.$cls.'>'.$this->filterEsc($m[4]).'</code></pre>');
                },
                $src
            ) ?? $src;
        }

        private function filterIndentedCode(string $src): string {
            return preg_replace_callback(
                '/(?:^(?:    |\t).+\n?)+/m',
                fn($m) => $this->addStash(
                    '<pre><code>'.$this->filterEsc(preg_replace('/^(?:    |\t)/m', '', rtrim($m[0]))).'</code></pre>'
                )."\n",
                $src
            ) ?? $src;
        }

        private function filterInlineCode(string $src): string {
            return preg_replace_callback(
                '/``(.+?)``|`([^`\n]+)`/s',
                function($m) {
                    $txt = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');
                    return $this->addStash('<code>'.$this->filterEsc($txt).'</code>');
                },
                $src
            ) ?? $src;
        }

        // Blocks

        private function filterBlocks(string $src, bool $safe): string {
            $lines = explode("\n", $src);
            $n     = count($lines);
            $pat   = '/^\x02'.preg_quote($this->salt, '/').':\d+\x03$/';
            $out   = '';
            $i     = 0;

            while ($i < $n) {
                $line = $lines[$i];
                $trim = ltrim($line);

                if (preg_match($pat, trim($line))) { $out .= $line."\n"; $i++; continue; }
                if ($trim === '') { $out .= "\n"; $i++; continue; }

                if (preg_match('/^(#{1,6})\s+(.*?)(?:\s+#+)?$/', $trim, $m)) {
                    $lvl = strlen($m[1]);
                    $id  = $this->getHeadingId($m[2], $lvl);
                    $out .= '<h'.$lvl.' id="'.$id.'">'.$this->filterInline($m[2], $safe).'</h'.$lvl.'>'."\n";
                    $i++; continue;
                }

                if (preg_match('/^(?:\*{3,}|-{3,}|_{3,})\s*$/', $trim)) {
                    $out .= "<hr>\n"; $i++; continue;
                }

                if (str_starts_with($trim, '>')) {
                    [$bq, $i] = $this->getBlockquote($lines, $i, $n);
                    $map  = ['note' => 'sl_callout_note', 'tip' => 'sl_callout_tip', 'important' => 'sl_callout_important', 'warning' => 'sl_callout_warning', 'caution' => 'sl_callout_caution'];
                    $segs = [[]];
                    foreach ($bq as $ln) {
                        if ($ln === '' && end($segs) !== []) $segs[] = [];
                        elseif ($ln !== '') $segs[count($segs) - 1][] = $ln;
                    }
                    foreach ($segs as $seg) {
                        if ($seg === []) continue;
                        $hd = trim($seg[0]);
                        if (preg_match('/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]$/i', $hd, $cm)) {
                            $cls = $map[strtolower($cm[1])];
                            array_shift($seg);
                            $out .= '<div class="'.$cls.'">'."\n".$this->filterBlocks(implode("\n", $seg), $safe)."</div>\n";
                        } else {
                            $out .= "<blockquote>\n".$this->filterBlocks(implode("\n", $seg), $safe)."</blockquote>\n";
                        }
                    }
                    continue;
                }

                if (preg_match('/^([ \t]*)([*+\-]|\d+\.)\s+/', $line, $m)) {
                    [$html, $i] = $this->filterList($lines, $i, strlen($m[1]), $safe);
                    $out .= $html; continue;
                }

                if (isset($lines[$i + 1]) && str_contains($trim, '|')
                    && preg_match('/^\|?[ \t]*:?-{2,}:?[ \t]*(?:\|[ \t]*:?-{2,}:?[ \t]*)+\|?$/', $lines[$i + 1])
                ) {
                    [$html, $i] = $this->filterTable($lines, $i, $safe);
                    $out .= $html; continue;
                }

                if (isset($lines[$i + 1]) && $trim !== '') {
                    if (preg_match('/^=+\s*$/', $lines[$i + 1])) {
                        $id = $this->getHeadingId($trim, 1);
                        $out .= '<h1 id="'.$id.'">'.$this->filterInline($trim, $safe)."</h1>\n";
                        $i += 2; continue;
                    }
                    if (preg_match('/^-+\s*$/', $lines[$i + 1]) && !preg_match('/^[*+\-]\s/', $trim)) {
                        $id = $this->getHeadingId($trim, 2);
                        $out .= '<h2 id="'.$id.'">'.$this->filterInline($trim, $safe)."</h2>\n";
                        $i += 2; continue;
                    }
                }

                if (!$safe && preg_match('/^<\/?[a-zA-Z]/', $trim)) {
                    $raw = '';
                    while ($i < $n && trim($lines[$i]) !== '') { $raw .= $lines[$i++]."\n"; }
                    $raw = strtr($raw, $this->stash);
                    $out .= $this->addStash(str_replace(['&#034;', '&#039;'], ['"', "'"], $raw));
                    continue;
                }

                $para = [];
                while ($i < $n && trim($lines[$i]) !== ''
                    && !preg_match('/^#{1,6}\s|^(?:\*{3,}|-{3,}|_{3,})\s*$/', ltrim($lines[$i]))
                ) {
                    $para[] = $lines[$i++];
                }
                $out .= '<p>'.$this->filterInline(implode("\n", $para), $safe)."</p>\n";
            }

            return $out;
        }

        private function getBlockquote(array $lines, int $i, int $n): array {
            $bq = [];
            while ($i < $n) {
                $t = ltrim($lines[$i]);
                if (str_starts_with($t, '>')) {
                    $bq[] = preg_replace('/^[ \t]*>[ \t]?/', '', $lines[$i++]);
                } elseif (trim($lines[$i]) === '') {
                    $j = $i + 1;
                    while ($j < $n && trim($lines[$j]) === '') $j++;
                    if ($j < $n && str_starts_with(ltrim($lines[$j]), '>')) { $bq[] = ''; $i++; }
                    else break;
                } else break;
            }
            return [$bq, $i];
        }

        private function getHeadingId(string $raw, int $lvl): string {
            $txt  = preg_replace('/\x02'.preg_quote($this->salt, '/').':\d+\x03/', '', $raw);
            $id   = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', strip_tags($txt)), '-'));
            if ($id === '') $id = 'h'.$lvl;
            $base = $id;
            if (isset($this->hids[$base])) $id = $base.'-'.(++$this->hids[$base]);
            else $this->hids[$base] = 0;
            return $id;
        }

        private function filterList(array $lines, int $i, int $ind, bool $safe): array {
            $n   = count($lines);
            $ord = (bool)preg_match('/^\s*\d+\./', $lines[$i]);
            $tag = $ord ? 'ol' : 'ul';
            $it  = [];
            $cur = null;

            while ($i < $n) {
                $line = $lines[$i];
                if (trim($line) === '') { if ($cur !== null) $cur .= "\n"; $i++; continue; }
                $sp = strlen($line) - strlen(ltrim($line));
                if ($sp === $ind && preg_match('/^[ \t]*(?:[*+\-]|\d+\.)\s+(.*)$/', $line, $m)) {
                    if ($cur !== null) $it[] = $cur;
                    $cur = $m[1]; $i++;
                } elseif ($sp > $ind) {
                    $cur .= "\n".$line; $i++;
                } else break;
            }
            if ($cur !== null) $it[] = $cur;

            $html = '<'.$tag.">\n";
            foreach ($it as $item) {
                $item = trim($item);
                if (preg_match('/^\[(x| )\]\s+(.*)/si', $item, $tm)) {
                    $chk = $tm[1] === 'x' ? ' checked' : '';
                    $lbl = trim($tm[2]);
                    $lbl = str_contains($lbl, "\n") ? $this->filterBlocks($lbl, $safe) : $this->filterInline($lbl, $safe);
                    $html .= '<li><input type="checkbox" disabled'.$chk.'> '.$lbl."</li>\n";
                } elseif (str_contains($item, "\n")) {
                    $html .= '<li>'.$this->filterBlocks($item, $safe)."</li>\n";
                } else {
                    $html .= '<li>'.$this->filterInline($item, $safe)."</li>\n";
                }
            }
            return [$html.'</'.$tag.">\n", $i];
        }

        private function filterTable(array $lines, int $i, bool $safe): array {
            $heads = array_map('trim', explode('|', trim($lines[$i],   " |\t")));
            $seps  = array_map('trim', explode('|', trim($lines[$i+1], " |\t")));
            $cols  = max(count($heads), count($seps));
            $al    = array_map(fn($a) =>
                preg_match('/^:-+:$/', $a) ? ' style="text-align:center"' :
               (preg_match('/^-+:$/', $a)  ? ' style="text-align:right"'  :
               (preg_match('/^:-+$/', $a)  ? ' style="text-align:left"'   : '')),
                $seps
            );
            $i += 2;
            $html = "<table>\n<thead>\n<tr>";
            foreach (array_pad($heads, $cols, '') as $j => $h) {
                $html .= '<th'.($al[$j] ?? '').'>'.$this->filterInline($h, $safe).'</th>';
            }
            $html .= "</tr>\n</thead>\n<tbody>\n";
            while (isset($lines[$i]) && str_contains($lines[$i], '|') && trim($lines[$i]) !== '') {
                $cells = array_map('trim', explode('|', trim($lines[$i], " |\t")));
                $html .= '<tr>';
                foreach (array_pad($cells, $cols, '') as $j => $c) {
                    $html .= '<td'.($al[$j] ?? '').'>'.$this->filterInline($c, $safe).'</td>';
                }
                $html .= "</tr>\n"; $i++;
            }
            return [$html."</tbody>\n</table>\n", $i];
        }

        // Inlines: Markdown + BB

        private function filterInlines(string $src, bool $safe): string {
            // BB inline

            // ed2k links - must come BEFORE generic [url] patterns
            $src = preg_replace_callback(
                '/\[url\](ed2k:\/\/\|file\|(.*?)\|\d+\|\w+\|(h=\w+\|)?\/?)\[\/url\]/si',
                function(array $m): string {
                    $url = $this->filterEsc($this->filterDec($m[1]));
                    $ttl = $this->filterEsc($this->filterDec($m[2]));
                    return $this->addStash('eMule/eDonkey: <a href="'.$url.'" target="_blank" title="'.$ttl.'">'.$ttl.'</a>');
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[url=(ed2k:\/\/\|file\|(.*?)\|\d+\|\w+\|(h=\w+\|)?\/?)\](.*?)\[\/url\]/si',
                function(array $m): string {
                    $url = $this->filterEsc($this->filterDec($m[1]));
                    $ttl = $this->filterEsc($this->filterDec($m[2]));
                    return $this->addStash('<a href="'.$url.'" target="_blank" title="'.$ttl.'">'.(string)$m[4].'</a>');
                },
                $src
            ) ?? $src;

            for ($i = 0; $i < 3; $i++) {
                $src = preg_replace('/\[b\](.*?)\[\/b\]/si', '<strong>$1</strong>', $src) ?? $src;
                $src = preg_replace('/\[i\](.*?)\[\/i\]/si', '<em>$1</em>', $src) ?? $src;
                $src = preg_replace('/\[u\](.*?)\[\/u\]/si', '<u>$1</u>', $src) ?? $src;
                $src = preg_replace('/\[s\](.*?)\[\/s\]/si', '<del>$1</del>', $src) ?? $src;
            }

            $src = preg_replace_callback(
                '/\[color=([^\]]+)\](.*?)\[\/color\]/si',
                function(array $m): string {
                    $color = strtolower(trim($m[1]));
                    if (!preg_match('/^#[0-9a-f]{6}$/', $color) && !preg_match('/^[a-z]+$/', $color)) return $m[2];
                    return '<span style="color:'.$this->filterEsc($color).'">'.$m[2].'</span>';
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[family=([A-Za-z ]+)\](.*?)\[\/family\]/si',
                function(array $m): string {
                    return '<span style="font-family:'.$this->filterEsc(trim($m[1])).'">'.$m[2].'</span>';
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[size=([0-9]{1,2})\](.*?)\[\/size\]/si',
                function(array $m): string {
                    $size = max(8, min(48, (int)$m[1]));
                    return '<span style="font-size:'.$size.'px">'.$m[2].'</span>';
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[(left|right|center|justify)\](.*?)\[\/\1\]/si',
                function(array $m): string {
                    $align = strtolower(trim($m[1]));
                    if (!in_array($align, ['left', 'right', 'center', 'justify'], true)) return $m[2];
                    return '<div style="text-align:'.$align.';">'.$m[2].'</div>';
                },
                $src
            ) ?? $src;

            // [mail] / [mail=]
            $src = preg_replace_callback(
                '/\[mail\](.*?)\[\/mail\]/si',
                function(array $m): string {
                    $mail = trim($this->filterDec($m[1]));
                    if (!preg_match('/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $mail)) return $m[1];
                    $mail = $this->filterEsc($mail);
                    return $this->addStash('<a href="mailto:'.$mail.'">'.$mail.'</a>');
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[mail\s*=\s*([^\]]+)\](.*?)\[\/mail\]/si',
                function(array $m): string {
                    $mail = trim($this->filterDec($m[1]));
                    if (!preg_match('/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $mail)) return $m[2];
                    $mail = $this->filterEsc($mail);
                    return $this->addStash('<a href="mailto:'.$mail.'">'.$m[2].'</a>');
                },
                $src
            ) ?? $src;

            // [url] / [url=]
            $src = preg_replace_callback(
                '/\[url\](.*?)\[\/url\]/si',
                function(array $m) use ($safe): string {
                    $url = trim($this->filterDec($m[1]));
                    if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                    $href = $this->filterEsc($safe ? $this->filterUrl($url) : $url);
                    return $this->addStash('<a href="'.$href.'">'.$this->filterEsc($url).'</a>');
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[url=([^\]]+)\](.*?)\[\/url\]/si',
                function(array $m) use ($safe): string {
                    $url = trim($this->filterDec($m[1]));
                    if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                    $href = $this->filterEsc($safe ? $this->filterUrl($url) : $url);
                    return $this->addStash('<a href="'.$href.'">'.$m[2].'</a>');
                },
                $src
            ) ?? $src;

            // [img] / [img=align] / [img alt=] / [img=align alt=]
            $src = preg_replace_callback(
                '/\[img\](.*?)\[\/img\]/si',
                function(array $m) use ($safe): string {
                    $url  = trim($this->filterDec($m[1]));
                    if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                    $src2 = $this->filterEsc($safe ? $this->filterUrl($url) : $url);
                    $path = parse_url($url, PHP_URL_PATH);
                    $path = is_string($path) && $path !== '' ? $path : $url;
                    $file = $this->filterEsc(basename(rawurldecode($path)) ?: 'image');
                    $alt  = $file;
                    $err  = ' onerror="this.onerror=null;this.src=\''.img_find('misc/no-image.png').'\';this.alt=\''.$file.'\';this.title=\''.$file.'\'"';
                    return $this->addStash('<img src="'.$src2.'" alt="'.$alt.'" title="'.$alt.'" class="sl_img"'.$err.'>');
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[img=([a-zA-Z]+)\](.*?)\[\/img\]/si',
                function(array $m) use ($safe): string {
                    $align = strtolower(trim($m[1]));
                    if (!in_array($align, ['left', 'right'], true)) $align = 'left';
                    $url   = trim($this->filterDec($m[2]));
                    if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                    $src2  = $this->filterEsc($safe ? $this->filterUrl($url) : $url);
                    $path = parse_url($url, PHP_URL_PATH);
                    $path = is_string($path) && $path !== '' ? $path : $url;
                    $file = $this->filterEsc(basename(rawurldecode($path)) ?: 'image');
                    $alt   = $file;
                    $err  = ' onerror="this.onerror=null;this.src=\''.img_find('misc/no-image.png').'\';this.alt=\''.$file.'\';this.title=\''.$file.'\'"';
                    return $this->addStash('<img src="'.$src2.'" style="float:'.$align.';" alt="'.$alt.'" title="'.$alt.'" class="sl_img"'.$err.'>');
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[img\s+alt=([\pL0-9_\-\.\"\s]+)\](.*?)\[\/img\]/siu',
                function(array $m) use ($safe): string {
                    $alt  = trim($this->filterDec($m[1]));
                    $url  = trim($this->filterDec($m[2]));
                    if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                    $src2 = $this->filterEsc($safe ? $this->filterUrl($url) : $url);
                    $path = parse_url($url, PHP_URL_PATH);
                    $path = is_string($path) && $path !== '' ? $path : $url;
                    $file = $this->filterEsc(basename(rawurldecode($path)) ?: 'image');
                    $alt  = ($alt === '' || strtolower($alt) === 'title' || strtolower($alt) === 'alt') ? $file : $this->filterEsc($alt);
                    $err  = ' onerror="this.onerror=null;this.src=\''.img_find('misc/no-image.png').'\';this.alt=\''.$file.'\';this.title=\''.$file.'\'"';
                    return $this->addStash('<img src="'.$src2.'" alt="'.$alt.'" title="'.$alt.'" class="sl_img"'.$err.'>');
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[img=([a-zA-Z]+)\s+alt=([\pL0-9_\-\.\"\s]+)\](.*?)\[\/img\]/siu',
                function(array $m) use ($safe): string {
                    $align = strtolower(trim($m[1]));
                    if (!in_array($align, ['left', 'right'], true)) $align = 'left';
                    $alt   = trim($this->filterDec($m[2]));
                    $url   = trim($this->filterDec($m[3]));
                    if (preg_match('/^www\./i', $url)) $url = 'https://'.$url;
                    $src2  = $this->filterEsc($safe ? $this->filterUrl($url) : $url);
                    $path = parse_url($url, PHP_URL_PATH);
                    $path = is_string($path) && $path !== '' ? $path : $url;
                    $file = $this->filterEsc(basename(rawurldecode($path)) ?: 'image');
                    $alt   = ($alt === '' || strtolower($alt) === 'title' || strtolower($alt) === 'alt') ? $file : $this->filterEsc($alt);
                    $err  = ' onerror="this.onerror=null;this.src=\''.img_find('misc/no-image.png').'\';this.alt=\''.$file.'\';this.title=\''.$file.'\'"';
                    return $this->addStash('<img src="'.$src2.'" style="float:'.$align.';" alt="'.$alt.'" title="'.$alt.'" class="sl_img"'.$err.'>');
                },
                $src
            ) ?? $src;

            // Markdown inline

            $src = preg_replace_callback(
                '/!\[([^\]]*)\]\(([^\s)]+)(?:\s+(?:"|&quot;)(.*?)(?:"|&quot;))?\)/',
                function($m) use ($safe) {
                    $raw = $this->filterDec($m[2]);
                    $url = $this->filterEsc($safe ? $this->filterUrl($raw) : $raw);
                    $path = parse_url($raw, PHP_URL_PATH);
                    $path = is_string($path) && $path !== '' ? $path : $raw;
                    $file = $this->filterEsc(basename(rawurldecode($path)) ?: 'image');
                    $alt = trim($this->filterDec($m[1]));
                    $alt = ($alt === '' || strtolower($alt) === 'title' || strtolower($alt) === 'alt') ? $file : $this->filterEsc($alt);
                    $ttl = isset($m[3]) ? trim($this->filterDec($m[3])) : '';
                    $ttl = ($ttl === '' || strtolower($ttl) === 'title' || strtolower($ttl) === 'alt') ? ' title="'.$file.'"' : ' title="'.$this->filterEsc($ttl).'"';
                    $err  = ' onerror="this.onerror=null;this.src=\''.img_find('misc/no-image.png').'\';this.alt=\''.$file.'\';this.title=\''.$file.'\'"';
                    return $this->addStash('<img src="'.$url.'" alt="'.$alt.'"'.$ttl.$err.'>');
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/\[([^\]]+)\]\(([^\s)]+)(?:\s+(?:"|&quot;)(.*?)(?:"|&quot;))?\)/',
                function($m) use ($safe) {
                    $href = $this->filterEsc($safe ? $this->filterUrl($this->filterDec($m[2])) : $this->filterDec($m[2]));
                    $ttl  = isset($m[3]) ? ' title="'.$this->filterEsc($this->filterDec($m[3])).'"' : '';
                    return $this->addStash('<a href="'.$href.'"'.$ttl.'>'.$m[1].'</a>');
                },
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/<(https?:\/\/[^\s>]+)>/',
                fn($m) => $this->addStash('<a href="'.$this->filterEsc($m[1]).'">'.$this->filterEsc($m[1]).'</a>'),
                $src
            ) ?? $src;

            $src = preg_replace_callback(
                '/<([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})>/',
                fn($m) => $this->addStash('<a href="mailto:'.$this->filterEsc($m[1]).'">'.$this->filterEsc($m[1]).'</a>'),
                $src
            ) ?? $src;

            if ($safe) {
                $src = preg_replace_callback('/<[^>]+>/', fn($m) => $this->filterEsc($m[0]), $src) ?? $src;
            }

            $src = preg_replace(['/\*{3}(.+?)\*{3}/s', '/_{3}(.+?)_{3}/s'], '<strong><em>$1</em></strong>', $src);
            $src = preg_replace(['/\*{2}(.+?)\*{2}/s', '/_{2}(.+?)_{2}/s'], '<strong>$1</strong>', $src);
            $src = preg_replace(['/\*([^*\n]+)\*/', '/(?<![_\w])_([^_\n]+)_(?![_\w])/'], '<em>$1</em>', $src);
            $src = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $src);
            $src = preg_replace('/==(.+?)==/s', '<mark>$1</mark>', $src);
            $src = preg_replace(['/  \n/', '/\\\\\n/'], "<br>\n", $src);

            return $src;
        }
    };

    return $md->filterHtml($src, $safe, $mod);
}

# Search and replace
function filterReplaceText(string $sourse, string $mod): string {
    global $conf;
    $mod = ($mod && isset($conf['replace'][$mod])) ? $conf['replace'][$mod] : '';
    if ($mod) {
        $mod = explode('||', $mod);
        foreach ($mod as $word) {
            if ($word != '') {
                $warray = explode('|', $word);
                if ($warray[0]) {
                    preg_match_all('#<[^>]*>#', $sourse, $tags);
                    array_unique($tags);
                    $taglist = [];
                    $k = 0;
                    foreach($tags[0] as $i) {
                        $k++;
                        $taglist[$k] = $i;
                        $sourse = str_replace($i, '<'.$k.'>', $sourse);
                    }
                    $sourse = preg_replace('#'.$warray[0].'#i', $warray[1], $sourse);
                    foreach($taglist as $k => $i) $sourse = str_replace('<'.$k.'>', $i, $sourse);
                }
            }
        }
    }
    return $sourse;
}

# Executes a database backup task and returns scheduler metadata
function addBackupTask(): array {
    global $db, $conf;
    if (empty($conf['security']['log_b'])) return ['status' => 'failed', 'message' => 'Database backup is disabled'];
    $backup_start = microtime(true);

    $sess_f = COUNTER_DIR.'/backup.log';
    if (file_exists($sess_f)) unlink($sess_f);
    $fp_time = fopen($sess_f, 'wb');
    if ($fp_time) {
        fwrite($fp_time, time());
        fclose($fp_time);
    }

    // FIX: Memory-Management
    ini_set('memory_limit', '512M');

    // safe_mode ist entfernt; defensiv behandeln
    $safe = 0;
    if (function_exists('ini_get')) {
        $sm = ini_get('safe_mode');
        $safe = ($sm && $sm != '0') ? 1 : 0;
    }
    if (!$safe && function_exists('set_time_limit')) set_time_limit(600);

    # MySQL connection charset
    # auto - automatic (uses table charset), latin1, cp1251, utf8, etc.
    $ccharset = 'auto';
    $charset = preg_replace('#[^a-zA-Z0-9_\\-]#', '', (string)$ccharset);

    # Table types where only structure is saved (no data), comma-separated
    $conlycreate = 'MRG_MyISAM,MERGE,HEAP,MEMORY';

    # Table filter uses wildcard patterns. Supported special characters:
    # * - any number of characters;
    # ? - one arbitrary character;
    # ^ - excludes table(s) from the list.

    # Examples:
    # slaed_*           - all tables starting with "slaed_" (all Invision Board forum tables)
    # slaed_*, ^slaed_session  - all tables starting with "slaed_", except "slaed_session"
    # slaed_s*s, ^slaed_session - all tables starting with "slaed_s" and ending with "s", except "slaed_session"
    # ^*s               - all tables except those ending with "s"
    # ^slaed_????       - all tables except those starting with "slaed_" with 4 chars after the underscore
    $ctables = '^ipb_*';

    $bsize = 0;

    // Server-Version via PDO
    try {
        $vres = $db->getSqlQuery('SELECT VERSION() AS v');
        $vrow = $vres ? $vres->fetch(PDO::FETCH_ASSOC) : null;
        $ver = $vrow && isset($vrow['v']) ? $vrow['v'] : '0.0.0';
        preg_match("#^(\d+)\.(\d+)\.(\d+)#", $ver, $m);
        $bmysql_ver = isset($m[1]) ? sprintf('%d%02d%02d', $m[1], $m[2], $m[3]) : 0;
    } catch (Exception $e) {
        error_log('Backup failed: Cannot get MySQL version - '.$e->getMessage());
        return ['status' => 'failed', 'message' => 'Cannot get MySQL version'];
    }

    $bonly_create = explode(',', $conlycreate);

    $btables_exclude = !empty($ctables) && $ctables[0] == '^' ? 1 : 0;
    $btables = (!empty($ctables)) ? $ctables : '';
    $btables = explode(',', $btables);
    $tbls = [];

    if (!empty($ctables)) {
        foreach($btables as $table) {
            $table = preg_replace("/[^\w*?^]/", '', $table);
            $pattern = ["/\?/", "/\*/"];
            $replace = ['.', '.*?'];
            $tbls[] = preg_replace($pattern, $replace, $table);
        }
    }

    // Zeichenkodierung setzen, wenn nicht auto
    if ($bmysql_ver > 40101 && $charset !== '' && $charset != 'auto') {
        $db->getSqlQuery("SET NAMES '".$charset."'");
        $last_charset = $charset;
    } else {
        $last_charset = '';
    }

    // FIX: Korrigierte Filter-Logik
    $tables = [];
    $res = $db->getSqlQuery('SHOW TABLES');

    while ($row = $res->fetch(PDO::FETCH_NUM)) {
        $status = 0;

        if (!empty($tbls)) {
            foreach ($tbls as $table) {
                $exclude = preg_match("#^\^#", $table) ? true : false;

                if (!$exclude) {
                    if (preg_match("#^{$table}$#i", $row[0])) {
                        $status = 1; // Include
                    }
                }

                if ($exclude && preg_match("#{$table}$#i", $row[0])) {
                    $status = -1; // Exclude
                    break; // Sofort abbrechen wenn excluded
                }
            }

            // FIX: Korrekte Include/Exclude Logik
            if ($btables_exclude) {
                // Exclude mode: Take everything except status == -1
                if ($status != -1) {
                    $tables[] = $row[0];
                }
            } else {
                // Include-Modus: Nimm nur status == 1
                if ($status == 1) {
                    $tables[] = $row[0];
                }
            }
        } else {
            // Keine Filter = alle Tabellen
            $tables[] = $row[0];
        }
    }

    if (empty($tables)) {
        error_log('Backup failed: No tables found to backup');
        return ['status' => 'failed', 'message' => 'No tables found to backup'];
    }

    $tabs = count($tables);
    $res = $db->getSqlQuery('SHOW TABLE STATUS');
    $tabinfo = [];
    $tab_charset = [];
    $tab_type = [];
    $tabsize = [];
    $tabinfo[0] = 0;

    while ($item = $res->fetch(PDO::FETCH_ASSOC)) {
        if (in_array($item['Name'], $tables)) {
            $item['Rows'] = empty($item['Rows']) ? 0 : $item['Rows'];
            $tabinfo[0] += $item['Rows'];
            $tabinfo[$item['Name']] = $item['Rows'];
            $bsize += $item['Data_length'];
            $tabsize[$item['Name']] = 1 + round(1048576 / ($item['Avg_row_length'] + 1));

            if (!empty($item['Collation']) && preg_match('#^([a-z0-9]+)_#i', $item['Collation'], $m)) {
                $tab_charset[$item['Name']] = $m[1];
            }

            $tab_type[$item['Name']] = isset($item['Engine']) ? $item['Engine'] : $item['Type'];
        }
    }

    // FIX: Path Traversal security vulnerability
    $safe_dbname = preg_replace('/[^a-zA-Z0-9_-]/', '_', $conf['db']['name']);
    $name = $safe_dbname.'_'.date('Y-m-d_H-i-s');

    // FIX: Verzeichnis-Check
    $backup_dir = BACKUP_DIR.'/';
    if (!is_dir($backup_dir)) {
        if (!mkdir($backup_dir, 0750, true)) {
            error_log('Backup failed: Cannot create backup directory');
            return ['status' => 'failed', 'message' => 'Cannot create backup directory'];
        }
    }

    $filepath = $backup_dir.$name.'.sql';

    // FIX: Error handling for fopen
    $fp = fopen($filepath, 'wb');
    if (!$fp) {
        error_log('Backup failed: Cannot create file '.$filepath);
        return ['status' => 'failed', 'message' => 'Cannot create backup file'];
    }

    // Header schreiben
    fwrite($fp, '# DB: '.$conf['db']['name']."\n");
    fwrite($fp, '# Tables: '.$tabs."\n");
    fwrite($fp, '# Size: '.round($bsize / 1048576, 2)." MB\n");
    fwrite($fp, '# Lines: '.number_format($tabinfo[0], 0, ',', ' ')."\n");
    fwrite($fp, '# Date: '.date('Y.m.d H:i:s')."\n\n");

    $db->getSqlQuery('SET SQL_QUOTE_SHOW_CREATE = 1');

    foreach ($tables as $table) {
        if (!preg_match('#^[a-zA-Z0-9_]+$#', (string)$table)) {
            continue;
        }
        // Add a comma before each next VALUES row (except first row and after split markers) Check
        if ($bmysql_ver > 40101 && isset($tab_charset[$table]) && $tab_charset[$table] != $last_charset) {
            if ($ccharset == 'auto' && !empty($tab_charset[$table])) {
                $tcharset = preg_replace('#[^a-zA-Z0-9_\\-]#', '', (string)$tab_charset[$table]);
                if ($tcharset !== '') {
                    $db->getSqlQuery("SET NAMES '".$tcharset."'");
                    $last_charset = $tcharset;
                }
            }
        }

        $res = $db->getSqlQuery("SHOW CREATE TABLE `{$table}`");
        $tab = $res->fetch(PDO::FETCH_NUM);

        // For MariaDB 10+ do NOT use conditional comments
        if (isset($tab[1])) {
            fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n{$tab[1]};\n\n");
        }

        if (in_array($tab_type[$table], $bonly_create)) continue;

        $NumericColumn = [];
        $res = $db->getSqlQuery("SHOW COLUMNS FROM `{$table}`");
        $field = 0;
        while ($col = $res->fetch(PDO::FETCH_NUM)) {
            $NumericColumn[$field++] = preg_match("#^(\w*int|year)#", $col[1]) ? 1 : 0;
        }
        $fields = $field;

        $from = 0;
        $limit = $tabsize[$table];

        if ($tabinfo[$table] > 0) {
            $i = 0;
            fwrite($fp, "INSERT INTO `{$table}` VALUES");

            while ($res = $db->getSqlQuery("SELECT * FROM `{$table}` LIMIT ".intval($from).', '.intval($limit))) {
                $batch = 0;

                while ($row = $res->fetch(PDO::FETCH_NUM)) {
                    $batch++;
                    $i++;

                    // CRITICAL LIMIT: flush INSERT every 10000 rows to avoid memory pressure
                    if ($i > 1 && ($i - 1) % 10000 == 0) {
                        // Close previous INSERT and start a new one
                        fwrite($fp, ";\n\nINSERT INTO `{$table}` VALUES");
                    }

                    for ($k = 0; $k < $fields; $k++) {
                        if ($NumericColumn[$k]) {
                            $row[$k] = isset($row[$k]) ? $row[$k] : 'NULL';
                        } else {
                            $row[$k] = isset($row[$k]) ? $db->getSqlValue($row[$k]) : 'NULL';
                        }
                    }

                    // Add a comma before each next VALUES row (except first row and after split markers)
                    $is_first_in_block = ($i == 1) || (($i - 1) % 10000 == 0);
                    fwrite($fp, ($is_first_in_block ? "\n" : ",\n").'('.implode(',', $row).')');
                }

                if ($batch < $limit) break;
                $from += $limit;
            }

            fwrite($fp, ";\n\n");
        }
    }

    fclose($fp);
    if (!addCompress($backup_dir, $filepath, $name, 'auto', true)) {
        return ['status' => 'failed', 'message' => 'Cannot compress backup file'];
    }

    // Performance-Logging
    $duration = round(microtime(true) - $backup_start, 2);
    error_log("Backup completed: {$tabs} tables, ".round($bsize/1048576, 2)."MB in {$duration}s");
    $archive = $backup_dir.$name.'.sql.gz';
    return [
        'status' => 'success',
        'message' => 'Database backup completed',
        'extra' => [
            'last_backup_file' => basename(file_exists($archive) ? $archive : $filepath),
            'last_backup_size' => file_exists($archive) ? (int)filesize($archive) : (file_exists($filepath) ? (int)filesize($filepath) : 0),
            'last_table_count' => $tabs,
        ],
    ];
}

# Get admin module names (stored as names)
function getAdminModuleNames(string $modules): array {
    $list = array_filter(array_map('trim', explode(',', $modules)), 'strlen');
    return array_values(array_unique($list));
}

# Format head
function setHead(array $seo = []): void {
    global $db, $home, $index, $conf, $user, $admin, $name, $theme, $op;
    $name = $name ?? '';
    $ctime = time();
    $request = getenv('REQUEST_URI');
    if ($conf['session']) {
        $ip = getIp();
        $url = urlencode($request);
        $guest = 0;
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
        $sess_f = 'config/counter/sess.txt';
        $sess_t = (file_exists($sess_f) && filesize($sess_f) != 0) ? file_get_contents($sess_f) : 0;
        $past = $ctime - intval($conf['sess_t']);
        if ($sess_t < $past) {
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_session WHERE time < :past', ['past' => $past]);
            if (file_exists($sess_f)) unlink($sess_f);
            $fp = fopen($sess_f, 'wb');
            fwrite($fp, $ctime);
            fclose($fp);
        }
        if (!empty($uname)) {
            if (!defined('ADMIN_FILE') && is_user()) {
                $uagent = getAgent();
                $uid= intval($user[0]);
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET ip = :ip, lastvis = NOW(), agent = :agent WHERE id = :uid', ['ip' => $ip, 'agent' => $uagent, 'uid' => $uid]);
            }
            $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_session WHERE uname = :uname', ['uname' => $uname]));
            if ($num >= 1) {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_session SET time = :time, ip = :ip, guest = :guest, modul = :modul, url = :url WHERE uname = :uname', [':time' => $ctime, ':ip' => $ip, ':guest' => $guest, ':modul' => $name, ':url' => $url, ':uname' => $uname]);
            } else {
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_session (uname, time, ip, guest, modul, url) VALUES (:uname, :time, :ip, :guest, :modul, :url)', ['uname' => $uname, 'time' => $ctime, 'ip' => $ip, 'guest' => $guest, 'modul' => $name, 'url' => $url]);
            }
        }
    }
    if ($conf['referers']['refer']) {
        $referer = getReferer();
        if ($referer) {
            $refer_f = 'config/counter/refer.txt';
            $refer_t = (file_exists($refer_f) && filesize($refer_f) != 0) ? file_get_contents($refer_f) : 0;
            $past = $ctime - intval($conf['referers']['refer_t']);
            if ($refer_t < $past) {
                $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_referer WHERE lid = :lid', ['lid' => 0]);
                unlink($refer_f);
                $fp = fopen($refer_f, 'wb');
                fwrite($fp, $ctime);
                fclose($fp);
            }
            $ip = getIp();
            $uid = is_user() ? intval($user[0]) : 0;
            $link = filterText($request);
            if (is_active('auto_links')) {
                list($exist) = $db->getSqlRow($db->getSqlQuery('SELECT ip FROM '.PREFIX_DB.'_referer WHERE ip = :ip AND lid != :lid', ['ip' => $ip, 'lid' => 0]));
                if ($exist) {
                    if ($conf['referers']['referb'] != 1 || ($conf['referers']['referb'] == 1 && from_bot())) $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_referer (uid, name, ip, referer, url, time, lid) VALUES (:uid, :name, :ip, :referer, :url, NOW(), :lid)', ['uid' => $uid, 'name' => $uname, 'ip' => $ip, 'referer' => $referer, 'url' => $link, 'lid' => 0]);
                } else {
                    $result = $db->getSqlQuery('SELECT url FROM '.PREFIX_DB.'_auto_links');
                    while(list($slink) = $db->getSqlRow($result)) {
                        if (preg_match('#'.$slink.'#i', $referer)) {
                            $islink = 1;
                            break;
                        } else {
                            $islink = 0;
                        }
                    }
                    if ($islink) {
                        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_auto_links SET hits = hits + 1 WHERE url = :url', ['url' => $slink]);
                        list($lid) = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_auto_links WHERE url = :url', ['url' => $slink]));
                        $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_referer (uid, name, ip, referer, url, time, lid) VALUES (:uid, :name, :ip, :referer, :url, NOW(), :lid)', ['uid' => $uid, 'name' => $uname, 'ip' => $ip, 'referer' => $referer, 'url' => $link, 'lid' => $lid]);
                    } else {
                        if ($conf['referers']['referb'] != 1 || ($conf['referers']['referb'] == 1 && from_bot())) $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_referer (uid, name, ip, referer, url, time, lid) VALUES (:uid, :name, :ip, :referer, :url, NOW(), :lid)', ['uid' => $uid, 'name' => $uname, 'ip' => $ip, 'referer' => $referer, 'url' => $link, 'lid' => 0]);
                    }
                }
            } else {
                if ($conf['referers']['referb'] != 1 || ($conf['referers']['referb'] == 1 && from_bot())) $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_referer (uid, name, ip, referer, url, time, lid) VALUES (:uid, :name, :ip, :referer, :url, NOW(), :lid)', ['uid' => $uid, 'name' => $uname, 'ip' => $ip, 'referer' => $referer, 'url' => $link, 'lid' => 0]);
            }
        }
    }
    if ($conf['statistic']['stat']) {
        $sreferer = getReferer();
        $sreqhom = filterText($request);
        $spath = COUNTER_DIR.'/';
        $slog = $spath.'statistic.log';
        $safeReadLines = static function(string $file) {
            if (!is_file($file) || !is_readable($file)) return false;
            set_error_handler(static function(): bool {
                return true;
            });
            try {
                $lines = file($file);
            } finally {
                restore_error_handler();
            }
            return $lines ?: false;
        };
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
        $sdate = $safeReadLines($slog);
        if ($sdate) {
            $con = explode('|', trim($sdate[0]));
            if (date('d.m.Y') != $con[0]) {
                $fpd = $safeOpen($spath.'days.log', 'ab');
                if ($fpd && flock($fpd, LOCK_EX)) {
                    fwrite($fpd, $sdate[0].PHP_EOL);
                    fflush($fpd);
                    flock($fpd, LOCK_UN);
                }
                if ($fpd) fclose($fpd);
                if (file_exists($spath.'statistic.log')) unlink($spath.'statistic.log');
                if (file_exists($spath.'ips.log')) unlink($spath.'ips.log');
                if (file_exists($spath.'user.log')) unlink($spath.'user.log');
                if (substr($con[0], 3) != date('m.Y')) {
                    $month = date('Y-m', strtotime('-1 month'));
                    $sdir = $spath.'statistic';
                    if (!is_dir($sdir)) mkdir($sdir, 0755, true);
                    rename($spath.'days.log', $sdir.'/statistic_'.$month.'.log');
                    if (file_exists($spath.'days.log')) unlink($spath.'days.log');
                }
                $ahits = ($con[3] ?? 0) ? (($con[3] ?? 0) + 1) : '1';
                $sengine = ($conf['session'] && $guest == 1) ? '1' : '0';
                $srefer = ($sreferer) ? '1' : '0';
                $reqhom = ($sreqhom == '/' || $sreqhom == '/index.html' || $sreqhom == '/index.php') ? '1' : '0';
                $wc = date('d.m.Y').'|0|1|'.$ahits.'|'.$sengine.'|'.$srefer.'|'.$reqhom.'|0';
            } else {
                $check = checkUniqueIp();
                $checku = check_user();
                $shost = ($check) ? intval(($con[1] ?? 0) + 1) : ($con[1] ?? 0);
                $sengine = ($check && $conf['session'] && $guest == 1) ? intval(($con[4] ?? 0) + 1) : ($con[4] ?? 0);
                $srefer = ($check && $sreferer) ? intval(($con[5] ?? 0) + 1) : ($con[5] ?? 0);
                $reqhom = ($sreqhom == '/' || $sreqhom == '/index.html' || $sreqhom == '/index.php') ? intval(($con[6] ?? 0) + 1) : ($con[6] ?? 0);
                $suser = ($checku && $conf['session'] && $guest == 2) ? intval(($con[7] ?? 0) + 1) : ($con[7] ?? 0);
                $wc = $con[0].'|'.$shost.'|'.intval(($con[2] ?? 0) + 1).'|'.intval(($con[3] ?? 0) + 1).'|'.$sengine.'|'.$srefer.'|'.$reqhom.'|'.$suser;
            }
            $fps = $safeOpen($spath.'statistic.log', 'wb');
            if ($fps && flock($fps, LOCK_EX)) {
                ftruncate($fps, 0);
                fwrite($fps, $wc);
                fflush($fps);
                flock($fps, LOCK_UN);
            }
            if ($fps) fclose($fps);
        } elseif (!file_exists($slog) || filemtime($slog) < strtotime('today midnight')) {
            if (file_exists($spath.'ips.log')) unlink($spath.'ips.log');
            if (file_exists($spath.'user.log')) unlink($spath.'user.log');
            $sengine = ($conf['session'] && $guest == 1) ? '1' : '0';
            $srefer = ($sreferer) ? '1' : '0';
            $reqhom = ($sreqhom == '/' || $sreqhom == '/index.html' || $sreqhom == '/index.php') ? '1' : '0';
            $wc = date('d.m.Y').'|0|1|1|'.$sengine.'|'.$srefer.'|'.$reqhom.'|0';
            $fps = $safeOpen($slog, 'wb');
            if ($fps && flock($fps, LOCK_EX)) {
                fwrite($fps, $wc);
                fflush($fps);
                flock($fps, LOCK_UN);
            }
            if ($fps) fclose($fps);
        }
    }
    if ((!defined('ADMIN_FILE') && $conf['cache'] == 1) || (!defined('ADMIN_FILE') && $conf['cache'] == 2 && $home)) {
        ob_start();
        $url = str_replace('/', '', $request);
        $url = (!$url) ? 'index.php' : $url;
        if ($conf['cache'] == 2) {
            if ($conf['rewrite']) {
                $match = ($url == 'index.php' || $url == 'index.html') ? 1 : 0;
            } else {
                $match = ($url == 'index.php') ? 1 : 0;
            }
        } else {
            if ($conf['rewrite']) {
                $match = ($url == 'index.php' || $url == 'index.html' || strstr($url, 'index.php?name='.$name) || strstr($url, $name)) ? 1 : 0;
            } else {
                $match = ($url == 'index.php' || strstr($url, 'index.php?name='.$name)) ? 1 : 0;
            }
        }
        if ($match && !is_user() && !isAdmin()) {
            $cacheurl = 'config/cache/'.md5($url).'.txt';
            if (file_exists($cacheurl) && filesize($cacheurl) != 0 && ($ctime - $conf['cache_t']) < filemtime($cacheurl)) {
                readfile($cacheurl);
                exit;
            }
        }
    }
    $index = file_get_contents(getThemeFile('index'));
    if (defined('ADMIN_FILE') && ($conf['lic_h'] != 'UG93ZXJlZCBieSA8YSBocmVmPSJodHRwczovL3NsYWVkLm5ldCIgdGFyZ2V0PSJfYmxhbmsiIHRpdGxlPSJTTEFFRCBDTVMiPlNMQUVEIENNUzwvYT4gJmNvcHk7IDIwMDUt' || $conf['lic_f'] != 'IFNMQUVELiBBbGwgcmlnaHRzIHJlc2VydmVkLg==' || !preg_match('#{%LICENSE%}#', $index))) setExit(_NO_LICENSE);
    $licens = base64_decode($conf['lic_h']).date('Y').base64_decode($conf['lic_f']);
    $index = str_replace('{%LICENSE%}', $licens, $index);
    preg_match('#^(.*){%MODULE%}#iUs', $index, $head);
    $head = (isset($head[1])) ? $head[1] : die('Error in Head!');
    preg_match('#{%MODULE%}(.*)$#iUs', $index, $index);
    $index = (isset($index[1])) ? $index[1] : die('Error in Foot!');
    $strmeta = '<meta charset="'._CHARSET.'">'."\n"
        .'<meta name="htmx-config" content=\'{"defaultHXHeaders":{"X-CSRF-Token":"'.getSiteToken('ajax').'"}}\'>'."\n";
    $strlink = $stscript = '';
    $sep = urldecode($conf['defis']);
    if (!defined('ADMIN_FILE')) {
        $atime  = date('Y-m-d H:i:s');
        $time   = $seo['time']   ?? $atime;
        $mtime  = $time;
        $title    = $seo['title']  ?? $conf['sitename'];
        $headline = $title;
        $desc   = $seo['desc']   ?? $conf['slogan'];
        $img    = ($seo['img'] ?? '') ?: $conf['homeurl'].'/templates/'.$theme.'/images/logos/'.$conf['site_logo'];
        $ctitle = $seo['ctitle'] ?? '';
        $author = $seo['author'] ?? $conf['sitename'];
        $url = ($conf['rewrite']) ? urldecode(substr($request, 1)) : urldecode(str_replace('index.php?', '', substr($request, 1)));
        $purl = ($conf['rewrite']) ? $conf['homeurl'].'/'.htmlspecialchars($url) : (($home) ? $conf['homeurl'] : $conf['homeurl'].'/index.php?'.htmlspecialchars($url));
        $type = 'article';
        if ($home) {
            $title = $conf['sitename'].' '.$sep.' '.$conf['slogan'];
        } else {
            if ($conf['ltitle']) {
                $mod = getModuleName($conf['name']);
                $title = ($title == $conf['sitename']) ? [] : [$title];
                $title = empty($ctitle) ? $title : array_merge($title, [$ctitle]);
                $word = getVar('get', 'word', 'word');
                $title = empty($word) ? $title : array_merge($title, [$word]);
                $let = getVar('get', 'let', 'let');
                $title = empty($let) ? $title : array_merge($title, [$let]);
                $num = getVar('get', 'num', 'num');
                $title = empty($num) ? $title : array_merge($title, [_PAGE.' '.$num]);
                $com = getVar('get', 'com', 'num');
                $title = empty($com) ? $title : array_merge($title, [_COMMENTS.' '.$com]);
                if ($op == 'best') {
                    $title = array_merge($title, [_BEST]);
                } elseif ($op == 'pop') {
                    $title = array_merge($title, [_POP]);
                } elseif ($op == 'liste') {
                    $title = array_merge($title, [_LIST]);
                } elseif ($op == 'add') {
                    $title = array_merge($title, [_ADD]);
                }
                $title = array_merge($title, [$mod]);
                $title = array_merge($title, [$conf['sitename']]);
                $title = implode(' '.$sep.' ', array_map('trim', $title));
            }
        }
        $strmeta .= '<title>'.$title.'</title>'."\n"
        .'<meta name="author" content="'.$conf['sitename'].'">'."\n"
        .'<meta name="description" content="'.$desc.'">'."\n"
        .'<meta name="robots" content="index, follow">'."\n"
        .'<meta name="revisit-after" content="1 days">'."\n"
        .'<meta name="rating" content="general">'."\n"
        .'<meta name="generator" content="SLAED CMS">'."\n";
        $seofrom = ['[homeurl]', '[site]', '[logo]', '[loc]', '[time]', '[mtime]', '[title]', '[desc]', '[img]', '[ctitle]', '[type]', '[url]', '[headline]', '[author]'];
        $seoto   = [$conf['homeurl'], $conf['sitename'], $conf['homeurl'].'/templates/'.$theme.'/images/logos/'.$conf['site_logo'], _LOCALE, date('c', strtotime($time)), date('c', strtotime($mtime)), $title, $desc, $img, $ctitle, $type, $purl, $headline, $author];
        if (!empty($conf['agraph']) && !empty($conf['graph'])) {
            $strmeta .= str_replace($seofrom, $seoto, $conf['graph']);
        }
        $strlink .= '<link rel="shortcut icon" href="templates/'.$theme.'/favicon.png">'."\n";
        if (strpos($conf['homeurl'], getHost()) !== false) $strlink .= '<link rel="canonical" href="'.$purl.'">'."\n";
        if ($conf['rss']['act']) {
            $fieldc = explode('||', $conf['rss']['rss']);
            foreach ($fieldc as $val) {
                if ($val != '') {
                    $out = explode('|', $val);
                    if ($out[0] != '0' && $out[1] != '0' && $out[2] == '1') $strlink .= '<link rel="alternate" type="application/rss+xml" href="'.$out[1].'" title="'.$out[0].'">'."\n";
                }
            }
        }
        $strlink .= '<link rel="search" type="application/opensearchdescription+xml" href="'.$conf['homeurl'].'/index.php?go=search" title="'.$conf['sitename'].' - '._SEARCH.'">'."\n";
    } else {
        $strmeta .= '<title>'.$conf['sitename'].' '.$sep.' '._ADMIN.'</title>'."\n";
    }
    $strlink .= doCss();
    if (!defined('ADMIN_FILE') && !empty($conf['aschema']) && !empty($conf['schema'])) {
        $stscript = str_replace($seofrom, $seoto, $conf['schema']);
    }
    $script = (defined('ADMIN_FILE') || empty($conf['script_b'])) ? doScript()."\n".$stscript : $stscript;
    $head = str_replace(['{%META%}', '{%LINK%}', '{%SCRIPT%}'], [$strmeta, $strlink, $script], addblocks($head));
    $surl = (!defined('ADMIN_FILE')) ? addSchedulerTrigger() : '';
    if ($surl !== '') {
        $js = '<script>window.addEventListener("load",function(){window.setTimeout(function(){fetch("'.$surl.'",{credentials:"same-origin"});},1);});</script>';
        $head = preg_replace('#<body(.*?)>#si', '<body$1>'.$js, $head, 1);
    }
    echo setTemplateHead($head);
    unset($head);
    if (!defined('ADMIN_FILE')) update_points(1);
}

# Format foot
function setFoot(): void {
 global $home, $name, $index, $conf, $do_gzip_compress;
    $index = addblocks($index);
    $index = (!defined('ADMIN_FILE') && !empty($conf['script_b'])) ? str_replace('{%SCRIPT%}', doScript(), $index) : str_replace('{%SCRIPT%}', '', $index);
    echo setTemplateFoot($index);
    unset($index);
    if ((!defined('ADMIN_FILE') && $conf['cache'] == 1) || (!defined('ADMIN_FILE') && $conf['cache'] == 2 && $home)) {
        $dir = 'config/cache/';
        $url = str_replace('/', '', getenv('REQUEST_URI'));
        $url = (!$url) ? 'index.php' : $url;
        if ($conf['cache'] == 2) {
            if ($conf['rewrite']) {
                $match = ($url == 'index.php' || $url == 'index.html') ? 1 : 0;
            } else {
                $match = ($url == 'index.php') ? 1 : 0;
            }
        } else {
            if ($conf['rewrite']) {
                $match = ($url == 'index.php' || $url == 'index.html' || strstr($url, 'index.php?name='.$name) || strstr($url, $name)) ? 1 : 0;
            } else {
                $match = ($url == 'index.php' || strstr($url, 'index.php?name='.$name)) ? 1 : 0;
            }
        }
        $cont = ob_get_contents();
        if ($cont && $match && !is_user() && !isAdmin()) {
            $cont = ($conf['cache_c']) ? getCompressHtml($cont) : $cont;
            $fp = fopen($dir.md5($url).'.txt', 'wb');
            fwrite($fp, $cont);
            fclose($fp);
        }
        if (!empty($conf['cache_d'])) {
            $time = time();
            $expire = $conf['cache_d'] * 86400;
            if (is_dir($dir)) {
                if ($dh = opendir($dir)) {
                    while (($file = readdir($dh)) !== false) {
                        if ($file != '.' && $file != '..' && $file != '.htaccess' && $file != 'index.html') {
                            $ftime = $time - filemtime($dir.$file);
                            if ($ftime >= $expire) unlink($dir.$file);
                        }
                    }
                    closedir($dh);
                }
            }
        }
    }
    while (ob_get_level() > 0) ob_end_flush();
    exit;
}

# Safe redirect with optional referer fallback
function setRedirect(string $url, bool $refer = false, int $code = 302): never {
    if (!in_array($code, [301, 302, 303, 307, 308], true)) $code = 302;
    if ($code === 302 && strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST') $code = 303;
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
    $sourse = preg_replace($pattern, '<span class="sl_word">$0</span>', $sourse);
    return str_replace($to, $from, $sourse);
}

# Write, append, or compress file
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
    $flags = ($mode === 'a' ? FILE_APPEND : 0) | LOCK_EX;
    if (file_put_contents($file, $data, $flags) === false) {
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

# Check if IP exists in log, add once if missing
function checkUniqueIp(): bool {
    $file = COUNTER_DIR.'/ips.log';
    $ip = getIp();
    if (file_exists($file)) {
        $cont = file_get_contents($file);
        if ($cont === false) {
            addErrorFile(_ERR_READ.': '.$file);
            return false;
        }
        if ($cont !== '' && str_contains(','.$cont, ','.$ip.',')) return false;
    }
    addFile($file, $ip.',', 'none', false, 'a');
    return true;
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
        $zipf = bzopen($file, 'wb');
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

# Error logging with rotation and compression
function addErrorFile(string $msg): bool {
 global $conf;
    static $running = false;
    if ($running) {
        error_log('[LOG] Recursive call prevented: '.$msg);
        return false;
    }
    $running = true;
    $log = LOGS_DIR.'/error_file.log';
    $cfg = $conf['security'] ?? [];
    $max = $cfg['log_size'] ?? 10485760;
    $line = '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL;
    if (file_put_contents($log, $line, FILE_APPEND | LOCK_EX) === false) {
        error_log('[LOG] Write failed: '.$log.' | '.$msg);
        $running = false;
        return false;
    }
    if (filesize($log) >= $max) {
        $safe = pathinfo($log, PATHINFO_FILENAME).'_'.date('Y-m-d_H-i-s');
        addCompress(dirname($log), $log, $safe, 'auto', true, true);
    }
    $running = false;
    return true;
}

# Captcha check
function checkCaptcha(int $id): bool {
 global $conf;
    if ($conf['gfx_chk'] >= '1' && ($id == 2 || ($id == 1 && !is_user()))) {
        $recaptcha = getVar('post', 'recaptcha', 'text');
        if ($recaptcha) {
            $url = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$conf['capsec'].'&response='.$recaptcha.'&remoteip='.getIp());
            $ret = json_decode($url, true);
            $cont = ($ret['success'] == 1 && substr($ret['score'], 2) >= $conf['quality']) ? false : true;
        } else {
            $cont = true;
        }
    } else {
        $cont = false;
    }
    return $cont;
}

# Generating categories for modules
function setCategories(string $mod, int $sub, bool $desc, string $id = ''): string {
 global $db, $user, $conf, $locale;
    if (filterVar($mod)) {
        $id = (intval($id)) ? $id : 0;
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
        $cnum = 0;
        $result = $db->getSqlQuery('SELECT id, title, intro, img, parent, pview, pread FROM '.PREFIX_DB.'_categories '.$where.' ORDER BY ordern, title', $params);
        while (list($cid, $title, $intro, $img, $parentid, $pview, $pread) = $db->getSqlRow($result)) {
            $massiv[] = [$cid, $title, $intro, $img, $parentid, $pview, $pread];
            unset($cid, $title, $intro, $img, $parentid, $pview, $pread);
            $cnum++;
        }
        if ($massiv) {
            $cont = '';
            foreach ($massiv as $val) {
                if ($val[4] == $id && is_acess($val[5])) {
                    $catid[] = $val[0];
                    $val[1] = getConst($val[1]);
                    $val[2] = getConst($val[2]);
                    if (is_acess($val[6])) {
                        $style = '';
                        $href = getSeoUrl(['name' => $mod, 'cat' => $val[0]]);
                        $ilink = ($val[3]) ? '<a href="'.$href.'" title="'.$val[1].'"><img src="'.img_find('categories/'.$val[3]).'" alt="'.$val[1].'" title="'.$val[1].'"></a>' : '<a href="'.$href.'" title="'.$val[1].'" class="sl_cat"></a>';
                        $alink = '<a href="'.$href.'" title="'.$val[1].'"><b>'.$val[1].'</b></a>';
                    } else {
                        $style = ' sl_hidden';
                        $htitle = $val[1].' - '._CCLOSED;
                        $ilink = ($val[3]) ? '<img src="'.img_find('categories/'.$val[3]).'" alt="'.$htitle.'" title="'.$htitle.'">' : '<span title="'.$htitle.'" class="sl_cat"></span>';
                        $alink = '<b>'.$val[1].'</b>';
                    }
                    $subcat = '';
                    foreach ($massiv as $sval) {
                        if ($val[0] == $sval[4] && is_acess($sval[5])) {
                            $catid[] = $sval[0];
                            if ($sub == 1) {
                                $sval[1] = getConst($sval[1]);
                                $shref = getSeoUrl(['name' => $mod, 'cat' => $sval[0]]);
                                $sublink = (is_acess($sval[6])) ? ' <a href="'.$shref.'" title="'.$sval[1].'" class="sl_cat">'.$sval[1].'</a>' : '';
                                $subcat .= '<div>'.$sublink.'</div>';
                            }
                        }
                    }
                    $description = ($desc) ? '<br><i>'.$val[2].'</i>' : '';
                    $cont .= '<div class="sl_catflex-box'.$style.'"><div class="sl_catflex-inbox"><div>'.$ilink.'</div><div>'.$alink.$description.'</div></div>'.$subcat.'</div>';
                }
            }
        }
        if ($cont) {
            $cat_ids = array_values(array_unique(array_map('intval', $catid)));
            $cat_ids = array_values(array_filter($cat_ids, static fn($v) => $v > 0));
            if (!$cat_ids) return '';
            $pp = [];
            $pm = [];
            foreach ($cat_ids as $k => $v) {
                $ph = 'c'.$k;
                $pp[] = ':'.$ph;
                $pm[$ph] = $v;
            }
            $cin = implode(', ', $pp);
            if ($mod == 'faq') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_faq WHERE cid IN ('.$cin.") AND time <= NOW() AND status != '0'", $pm));
                $in = _INFA;
            } elseif ($mod == 'files') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_files WHERE cid IN ('.$cin.") AND time <= NOW() AND status != '0'", $pm));
                $in = _INF;
            } elseif ($mod == 'help') {
                $uid = is_user() ? intval($user[0]) : 0;
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_help WHERE cid IN ('.$cin.") AND time <= NOW() AND pid = '0' AND uid = :uid", array_merge($pm, ['uid' => $uid])));
                $in = _INH;
            } elseif ($mod == 'jokes') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_jokes WHERE cid IN ('.$cin.") AND time <= NOW() AND status != '0'", $pm));
                $in = _INJ;
            } elseif ($mod == 'links') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_links WHERE cid IN ('.$cin.") AND time <= NOW() AND status != '0'", $pm));
                $in = _INL;
            } elseif ($mod == 'media') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_media WHERE cid IN ('.$cin.") AND time <= NOW() AND status != '0'", $pm));
                $in = _INM;
            } elseif ($mod == 'news') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_news WHERE cid IN ('.$cin.") AND time <= NOW() AND status != '0'", $pm));
                $in = _INN;
            } elseif ($mod == 'pages') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_pages WHERE cid IN ('.$cin.") AND time <= NOW() AND status != '0'", $pm));
                $in = _INP;
            } elseif ($mod == 'shop') {
                list($pnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_products WHERE cid IN ('.$cin.") AND time <= NOW() AND status != '0'", $pm));
                $in = _INS;
            }
            return setTemplateBasic('categories', ['{%categories%}' => _CATEGORIES, '{%content%}' => $cont, '{%total%}' => _ALLIN, '{%pages%}' => $pnum, '{%in%}' => $in, '{%cat%}' => $cnum, '{%category%}' => _ALLINC, '{%mod%}' => $mod]);
        }
    }
    return '';
}

# Generation of article numbers
function setArticleNumbers(string $name, string $mod, int $limit, string $url, string $cntfld, string $tbl, string $catfld = '', string $where = '', int $maxpg = 10, array $params = []): string {
    global $db, $conf, $locale;
    if (!defined('ADMIN_FILE') && $catfld && $where) {
        if ($conf['multilingual']) {
            $lng_where = 'WHERE modul = :mod AND (lang = :loc OR lang = \'\')';
            $lng_params = ['mod' => $mod, 'loc' => $locale];
        } else {
            $lng_where = 'WHERE modul = :mod';
            $lng_params = ['mod' => $mod];
        }
        $res = $db->getSqlQuery('SELECT id, pread FROM '.PREFIX_DB.'_categories '.$lng_where.' ORDER BY id', $lng_params);
        $catid = [];
        while (list($cid, $auth) = $db->getSqlRow($res)) {
            if (is_acess($auth)) $catid[] = (int)$cid;
        }
        $where = (!empty($catid)) ? ' WHERE '.$catfld.' IN ('.implode(', ',$catid).') AND '.$where : ' WHERE '.$where;
    } else {
        $where = $where ? ' WHERE '.$where : '';
    }
    $sql = 'SELECT COUNT('.$cntfld.') FROM '.PREFIX_DB.$tbl.$where;
    list($cnt) = $db->getSqlRow($db->getSqlQuery($sql,$params));
    $cnt = (int)$cnt;
    $pages = $cnt > 0 ? (int)ceil($cnt / $limit) : 1;
    return setPageNumbers($name, $mod, $cnt, $pages, $limit, $url, $maxpg);
}

# Generation of page numbers
function setPageNumbers(string $tpl, string $mod, int $count, int $pages, int $limit, string $url = '', int $maxpg = 8, int $num = 0, string $anchor = '', string $n = 'num'): string {
    global $afile;
    $num  = $num ?: getVar('get', $n, 'num', 1);
    $nnum = $maxpg + 1;
    if ($pages > 1) {
        $cont = '';
        if ($num > 1) {
            $prev  = $num - 1;
            $cprev = (!defined('ADMIN_FILE')) ? '<a href="'.getSeoUrl(['name' => $mod, $url.$n => $prev]).$anchor.'" class="sl_num" title="'._BACK.'">'._BACK.'</a>' : '<a href="'.$afile.'.php?'.$url.$n.'='.$prev.$anchor.'" class="sl_num" title="'._BACK.'">'._BACK.'</a>';
        } else {
            $cprev = '<span class="sl_num" title="'._BACK.'">'._BACK.'</span>';
        }
        for ($i = 1; $i < $pages+1; $i++) {
            if ($i == $num) {
                $cont .= '<span title="'.$i.'">'.$i.'</span>';
            } else {
                if ((($i > ($num - $maxpg)) && ($i < ($num + $maxpg))) || ($i == $pages) || ($i == 1)) $cont .= (!defined('ADMIN_FILE')) ? '<a href="'.getSeoUrl(['name' => $mod, $url.$n => $i]).$anchor.'" title="'.$i.'">'.$i.'</a>' : '<a href="'.$afile.'.php?'.$url.$n.'='.$i.$anchor.'" title="'.$i.'">'.$i.'</a>';
            }
            if ($i < $pages) {
                if (($i > ($num - $nnum)) && ($i < ($num + $maxpg))) $cont .= ' ';
                if (($num > $nnum) && ($i == 1)) $cont .= '<span class="sl_num_exit" title="&hellip;">&hellip;</span>';
                if (($num < ($pages - $maxpg)) && ($i == ($pages - 1))) $cont .= '<span class="sl_num_exit" title="&hellip;">&hellip;</span>';
            }
        }
        if ($num < $pages) {
            $next  = $num + 1;
            $cnext = (!defined('ADMIN_FILE')) ? '<a href="'.getSeoUrl(['name' => $mod, $url.$n => $next]).$anchor.'" class="sl_num" title="'._NEXT.'">'._NEXT.'</a>' : '<a href="'.$afile.'.php?'.$url.$n.'='.$next.$anchor.'" class="sl_num" title="'._NEXT.'">'._NEXT.'</a>';
        } else {
            $cnext = '<span class="sl_num" title="'._NEXT.'">'._NEXT.'</span>';
        }
        return setTemplateBasic($tpl, ['{%overall%}' => _OVERALL, '{%count%}' => $count, '{%by%}' => _BY, '{%pages%}' => $pages, '{%page_s%}' => _PAGE_S, '{%page%}' => $limit, '{%perpage%}' => _PERPAGE, '{%pager%}' => $cont, '{%prev%}' => $cprev, '{%next%}' => $cnext]);
    }
    return '';
}

# Browser caching
function setCache($id=''): void {
    header('Content-Type: text/html; charset='._CHARSET);
    if ($id === '1') {
 global $conf;
        $cached = (int) ($conf['cache_d'] ?? 7);
        $max = $cached * 86400;
        $expires = time() + $max;
        header('Cache-Control: public, max-age='.$max);
        header('Expires: '.gmdate('D, d M Y H:i:s', $expires).' GMT');
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
    } else {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: '.gmdate('D, d M Y H:i:s', time() - 3600).' GMT');
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
    }
    header('X-Powered-By: SLAED CMS');
    header('X-Powered-CMS: SLAED CMS');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

# Set cached script file
function setScript(): void {
    header('Content-type: text/javascript');
    readfile('config/cache/'.md5(getTheme().'script').'.txt');
}

# Set cached CSS file
function setCss(): void {
    header('Content-type: text/css');
    readfile('config/cache/'.md5(getTheme().'style').'.txt');
}

# Set bottom navigation
function setNaviLower(string $mod): string {
    return setTemplateBasic('open').'<span class="sl_pos_center"><a href="javascript:window.history.go(-1);" title="'._BACK.'" class="sl_but_foot">'._BACK.'</a><a href="index.php?name='.$mod.'" title="'._PAGEHOME.'" class="sl_but_foot">'._PAGEHOME.'</a><a OnClick="Upper(\'html, body\', 600);" title="'._PAGETOP.'" class="sl_but_foot">'._PAGETOP.'</a></span>'.setTemplateBasic('close');
}

# Load configuration file or directory and return chmod warning if needed
function checkPerms(string $fp): string {
    $perm = is_dir($fp) ? 777 : 666;
    $info = checkFileChmod($fp, $perm);
    return ($info !== '') ? setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $info]) : '';
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
        return is_bool($val) ? (string)(int)$val : (string)$val;
    };
    foreach ($arr as $key => $val) $arr[$key] = $norm($val);
    $key  = pathinfo(basename($fp), PATHINFO_FILENAME);
    $data = ($key === 'global') ? $arr : [$key => $arr];
    $exp  = function (array $arr, int $dep = 0) use (&$exp): string {
        $pad = str_repeat('    ', $dep);
        $ind = $pad.'    ';
        $out = '['.PHP_EOL;
        foreach ($arr as $key => $val) {
            $out .= $ind.var_export($key, true).' => ';
            $out .= is_array($val) ? $exp($val, $dep + 1) : var_export($val, true);
            $out .= ','.PHP_EOL;
        }
        return $out.$pad.']';
    };
    $cnt = '<?php'.PHP_EOL
    .'# Author: Eduard Laas'.PHP_EOL
    .'# Copyright (c) 2005 - '.date('Y').' SLAED'.PHP_EOL
    .'# License: GNU GPL 3'.PHP_EOL
    .'# Website: slaed.net'.PHP_EOL.PHP_EOL
    .'return '.$exp($data).';'.PHP_EOL;
    file_put_contents($fp, $cnt, LOCK_EX);
}

# Definition and processing of header scripts files
function doScript(): string {
 global $theme, $conf;
    $async = ($conf['script_a']) ? 'async ' : '';
    $sfile = 'config/cache/'.md5($theme.'script').'.txt';
    $array = explode(',', $conf['script_f']);
    $array = is_array($array) ? $array : [];
    $array = (!$conf['security']['error_java']) ? array_merge($array, ['plugins/system/block-error.js']) : $array;
    if (!defined('ADMIN_FILE')) {
        if ($conf['cache_script'] && file_exists($sfile) && filesize($sfile) != 0 && (time() - $conf['cache_t']) < filemtime($sfile)) {
            $cont = ($conf['script_h']) ? file_get_contents($sfile) : '<script '.$async.'src="index.php?go=script"></script>';
        } else {
            foreach ($array as $file) {
                if (file_exists($file)) {
                    if ($conf['cache_script'] || $conf['script_h']) {
                        $cont = file_get_contents($file);
                        $arr[] = ($conf['script_c']) ? getCompressCode($cont) : $cont;
                    } else {
                        $arr[] = '<script '.$async.'src="'.$file.'"></script>';
                    }
                }
            }
            $cont = ($conf['script_h']) ? '<script>'.implode(' ', $arr).'</script>' : (($conf['cache_script']) ? implode(' ', $arr) : implode("\n", $arr));
            if ($conf['cache_script']) {
                file_put_contents($sfile, $cont);
                $cont = (file_exists($sfile) && !$conf['script_h']) ? '<script '.$async.'src="index.php?go=script"></script>' : $cont;
            }
        }
        if (file_exists('config/header.php')) {
            ob_start();
            include('config/header.php');
            $cont .= ob_get_clean();
        }
    } else {
        foreach ($array as $file) {
            if (file_exists($file)) {
                $arr[] = '<script '.$async.'src="'.$file.'"></script>';
            }
        }
        $cont = implode("\n", $arr);
    }
    return $cont;
}

# Definition and processing of CSS files
function doCss(): string {
 global $theme, $conf;
    $array = explode(',', str_replace('[theme]', $theme, $conf['css_f']));
    if (is_array($array)) {
        if (!defined('ADMIN_FILE')) {
            $cfile = 'config/cache/'.md5($theme.'style').'.txt';
            if ($conf['cache_css'] && file_exists($cfile) && filesize($cfile) != 0 && (time() - $conf['cache_t']) < filemtime($cfile)) {
                $cont = ($conf['css_h']) ? file_get_contents($cfile) : '<link rel="stylesheet" href="index.php?go=css">';
            } else {
                foreach ($array as $dir) {
                    foreach (glob($dir.'*.css') as $file) {
                        if (file_exists($file)) {
                            if ($conf['cache_css'] || $conf['css_h']) {
                                $cont = str_replace('../', '', file_get_contents($file));
                                $cont = preg_replace('#url\((\'|"|)(.*?)(\'|"|)\)#i', 'url('.$dir.'\\2)', $cont);
                                if ($conf['css_e']) $cont = preg_replace_callback('#url\((.*?\.(png|jpg|jpeg|gif|svg|bmp))\)#i', 'getImgEncode', $cont);
                                $arr[] = ($conf['css_c']) ? getCompressCss($cont) : $cont;
                            } else {
                                $arr[] = '<link rel="stylesheet" href="'.$file.'">';
                            }
                        }
                    }
                }
                $cont = ($conf['css_h']) ? '<style type="text/css">'.implode(' ', $arr).'</style>' : (($conf['cache_css']) ? implode(' ', $arr) : implode("\n", $arr));
                if ($conf['cache_css']) {
                    file_put_contents($cfile, $cont);
                    $cont = (file_exists($cfile) && !$conf['css_h']) ? '<link rel="stylesheet" href="index.php?go=css">' : $cont;
                }
            }
        } else {
            foreach ($array as $dir) {
                foreach (glob($dir.'*.css') as $file) {
                    if (file_exists($file)) {
                        $arr[] = '<link rel="stylesheet" href="'.$file.'">';
                    }
                }
            }
            $cont = implode("\n", $arr);
        }
    } else {
        $cont = '';
    }
    return $cont;
}

# Create a sitemap
function doSitemap(bool $force = false): array {
 global $db, $conf;
    if ($force || defined('ADMIN_FILE') || !empty($conf['sitemap']['auto'])) {
        $sess_f = 'sitemap.xml';
        $sess_b = (file_exists($sess_f) && filesize($sess_f) != 0) ? filemtime($sess_f) : 0;
        $past = time() - intval($conf['sitemap']['auto_t'] ?? 0);
        if ($force || defined('ADMIN_FILE') || $sess_b < $past) {
            $date = date('Y-m-d');
            $modules_raw = (string)($conf['sitemap']['mod'] ?? '');
            $mod = ($modules_raw === '') ? ['0'] : explode(',', $modules_raw);
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
                        $map_m .= '<url><loc>'.$conf['homeurl'].'/index.php?name='.$key.'</loc>';
                        $map_m .= $conf['sitemap']['dat_m'] ? '<lastmod>'.$date.'</lastmod>' : '';
                        $map_m .= $conf['sitemap']['fr_m'] ? '<changefreq>'.$conf['sitemap']['fr_m'].'</changefreq>' : '';
                        $map_m .= $conf['sitemap']['pr_m'] ? '<priority>'.$conf['sitemap']['pr_m'].'</priority>' : '';
                        $map_m .= '</url>'."\n";
                    }
                    foreach ($info[$key] as $key2 => $val2) {
                        if ($conf['sitemap']['gen_p'] && $info[$key][$key2][0]) {
                            $map_p .= '<url><loc>'.$conf['homeurl'].'/index.php?name='.$info[$key][$key2][4].'&amp;op=view&amp;id='.$info[$key][$key2][0].'</loc>';
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
                            $map_c .= '<url><loc>'.$conf['homeurl'].'/index.php?name='.$cmodul.'&amp;cat='.$cid.'</loc>';
                            $map_c .= $conf['sitemap']['dat_c'] ? '<lastmod>'.$date.'</lastmod>' : '';
                            $map_c .= $conf['sitemap']['fr_c'] ? '<changefreq>'.$conf['sitemap']['fr_c'].'</changefreq>' : '';
                            $map_c .= $conf['sitemap']['pr_c'] ? '<priority>'.$conf['sitemap']['pr_c'].'</priority>' : '';
                            $map_c .= '</url>'."\n";
                        }
                    }
                }
            }
            if ($conf['sitemap']['txt']) {
                $buffer = '<ol class="sl_list">';
                foreach ($htm as $key => $val) {
                    $buffer .= '<li><a href="index.php?name='.$key.'" title="'.getModuleName($key).'">'.getModuleName($key).'</a>';
                    if (count($htm[$key]) > 0) {
                        $cat = '';
                        foreach ($htm[$key] as $key2 => $val2) {
                            $cat .= (isset($cd[$key2][2])) ? '<li><a href="index.php?name='.$key.'&amp;cat='.$key2.'" title="'.$cd[$key2][2].'">'.$cd[$key2][2].'</a>' : '';
                            if (count($htm[$key][$key2]) > 0) {
                                $view = $pub = '';
                                foreach ($htm[$key][$key2] as $key3 => $val3) {
                                    $view .= $htm[$key][$key2][$key3][0] ? '<li><a href="index.php?name='.$key.'&amp;op=view&amp;id='.$htm[$key][$key2][$key3][0].'" title="'.$htm[$key][$key2][$key3][1].'">'.$htm[$key][$key2][$key3][1].'</a></li>' : '';
                                }
                                $pub .= $view ? '<ol class="sl_sublist_two">'.$view.'</ol>' : '';
                            }
                            $cat .= isset($cd[$key2][2]) ? $pub.'</li>' : '';
                        }
                        $buffer .= $cat ? '<ol class="sl_sublist">'.$cat.'</ol>' : $pub;
                    }
                    $buffer .= '</li>';
                }
                $buffer .= '</ol>';
                file_put_contents(SITEMAP_DIR.'/sitemap.txt', $buffer);
            }
            if ($conf['sitemap']['gen_h']) {
                $map_h = '<url><loc>'.$conf['homeurl'].'/index.php</loc>';
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
                    if ($conf['rewrite']) {
                        $cont = str_replace($conf['homeurl'].'/', '', $cont);
                        $cont = preg_replace('#<loc>(.*?)</loc>#is','<loc>'.$conf['homeurl'].'/\\1</loc>', $cont);
                    }
                    $file = 'sitemap-'.$i.'.xml';
                    file_put_contents($file, $cont);
                    $i++;
                    if (strlen($cont) >= $size && checkCompress()['gz'] && file_exists($file)) {
                        if (addCompress('.', $file, basename($file), 'gz', true)) {
                            $file = $file.'.gz';
                        }
                    }
                    $links .= '<sitemap><loc>'.$conf['homeurl'].'/'.$file.'</loc><lastmod>'.$date.'</lastmod></sitemap>'."\n";
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
            file_put_contents('sitemap.xml', $cont);
            return [
                'status' => 'success',
                'message' => 'Sitemap generation completed',
                'extra' => [
                    'last_map_size' => file_exists('sitemap.xml') ? (int)filesize('sitemap.xml') : 0,
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
    $tlinks = implode('', array_map(fn($p) => '<li><a href="#'.$pref.'_'.$id.'_'.$p['id'].'">'.$p['tab'].'</a></li>', $pairs));
    $cdivs = implode('', array_map(fn($p) => '<div id="'.$pref.'_'.$id.'_'.$p['id'].'">'.$p['cont'].'</div>', $pairs));
    return '<div id="sl_tabs_'.$id.'"><ul>'.$tlinks.'</ul>'.$cdivs.'</div>';
}

# Transliteration
function getTranslit(string $st, string $lo = ''): string {
    $st = strtr($st, ['ÃÂ°' => 'a', 'ÃÂ±' => 'b', 'ÃÂ²' => 'v', 'ÃÂ³' => 'g', 'ÃÂ´' => 'd', 'ÃÂµ' => 'e', 'ÃÂ¶' => 'g', 'ÃÂ·' => 'z', 'ÃÂ¸' => 'i', 'ÃÂ¹' => 'y', 'ÃÂº' => 'k', 'ÃÂ»' => 'l', 'ÃÂ¼' => 'm', 'ÃÂ½' => 'n', 'ÃÂ¾' => 'o', 'ÃÂ¿' => 'p', 'Ã‘â‚¬' => 'r', 'Ã‘Â' => 's', 'Ã‘â€š' => 't', 'Ã‘Æ’' => 'u', 'Ã‘â€ž' => 'f', 'Ã‘â€¹' => 'i', 'Ã‘Â' => 'e', 'ÃÂ' => 'A', 'Ãâ€˜' => 'B', 'Ãâ€™' => 'V', 'Ãâ€œ' => 'G', 'Ãâ€' => 'D', 'Ãâ€¢' => 'E', 'Ãâ€“' => 'G', 'Ãâ€”' => 'Z', 'ÃËœ' => 'I', 'Ãâ„¢' => 'Y', 'ÃÅ¡' => 'K', 'Ãâ€º' => 'L', 'ÃÅ“' => 'M', 'ÃÂ' => 'N', 'ÃÅ¾' => 'O', 'ÃÅ¸' => 'P', 'ÃÂ ' => 'R', 'ÃÂ¡' => 'S', 'ÃÂ¢' => 'T', 'ÃÂ£' => 'U', 'ÃÂ¤' => 'F', 'ÃÂ«' => 'I', 'ÃÂ­' => 'E', 'Ã‘â€˜' => 'yo', 'Ã‘â€¦' => 'h', 'Ã‘â€ ' => 'ts', 'Ã‘â€¡' => 'ch', 'Ã‘Ë†' => 'sh', 'Ã‘â€°' => 'shch', 'Ã‘Å ' => '', 'Ã‘Å’' => '', 'Ã‘Å½' => 'yu', 'Ã‘Â' => 'ya', 'ÃÂ' => 'Yo', 'ÃÂ¥' => 'H', 'ÃÂ¦' => 'Ts', 'ÃÂ§' => 'Ch', 'ÃÂ¨' => 'Sh', 'ÃÂ©' => 'Shch', 'ÃÂª' => '', 'ÃÂ¬' => '', 'ÃÂ®' => 'Yu', 'ÃÂ¯' => 'Ya']);
    $st = empty($lo) ? $st : mb_strtolower($st);
    $st = preg_replace('#[^a-zA-Z0-9]#', '', $st);
    $st = trim($st);
    return $st;
}

# Social networks code
function getNetworks(): string {
 global $conf;
    if ($conf['users']['network_c']) {
        $url = urlencode($conf['homeurl'].'/index.php?name=account&op=network');
        $st = ['[url]' => $url];
        $cont = strtr($conf['users']['network_c'], $st);
    } else {
        $cont = '';
    }
    return $cont;
}

# Get captcha
function getCaptcha(int $id): string {
 global $conf;
    if ($conf['gfx_chk'] >= '1' && ($id == 2 || ($id == 1 && !is_user()))) {
        $cont = '<script src="https://www.google.com/recaptcha/api.js?render='.$conf['capkey'].'"></script>
        <script>grecaptcha.ready(function() { grecaptcha.execute("'.$conf['capkey'].'", { action: "homepage" }) .then(function(token) { document.getElementById("recaptcha").value = token; }); });</script>';
        $cont .= '<input type="hidden" id="recaptcha" name="recaptcha">';
    } else {
        $cont = '';
    }
    return $cont;
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

# Compress CSS
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

# Compress Code
function getCompressCode(string $code): string {
    # Remove multiline comment
    $code = preg_replace('#\/\*(?!-)[\x00-\xff]*?\*\/#', '', $code);
    # Remove tabs and extra spaces
    $code = str_replace(["\t", '  ', '   ', '    '], ' ', $code);
    # Remove other spaces before/after )
    $code = preg_replace(['#( )+\]#', '#\)( )+#'], ')', $code);
    # Remove spaces that can be removed
    $code = preg_replace('#\s?([\{\=-])\s?#', '\\1', $code);
    return $code;
}

# Compress HTML
function getCompressHtml(string $html): string {
    preg_match_all('#(<(?:code|pre|textarea|script|style)[^>]+>.*?</(?:code|pre|textarea|script|style)>)#si', $html, $pre);
    $html = preg_replace('#<(?:code|pre|textarea|script|style)[^>]+>.*?</(?:code|pre|textarea|script|style)>#si', '%pre%', $html);
    $html = preg_replace('#<!--[^\[].+-->#', '', $html);
    $html = preg_replace('#[\r\n\t]+#', ' ', $html);
    $html = preg_replace('#>[\s]+<#', '><', $html);
    $html = preg_replace('#[\s]+#', ' ', $html);
    if (!empty($pre[0])) {
        foreach ($pre[0] as $tag) {
            $html = preg_replace('#%pre%#', $tag, $html, 1);
        }
    }
    return $html;
}

# Voting view
function getVoting(int $id = 0, string $votid = ''): string {
 global $db, $afile, $user, $locale, $conf;
    if ($conf['multilingual'] == 1) {
        $querylang = "(lang = :locale OR lang = '') AND time <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')";
        $qlang_params = ['locale' => $locale];
    } else {
        $querylang = "time <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')";
        $qlang_params = [];
    }
    if (!$id)    $id    = getVar('get', 'id', 'num', 0);
    if (!$votid) $votid = filterVar(getVar('post', 'votid', 'text', 'voting')) ?: 'voting';
    $result = $db->getSqlQuery('SELECT modul, title, body, answer, enddate, multi, comments, acomm, typ, status FROM '.PREFIX_DB.'_voting WHERE id = :id AND '.$querylang, array_merge(['id' => $id], $qlang_params));
    if ($db->getSqlRowCount($result) > 0) {
        $ip = getIp();
        $past = time() - intval($conf['voting']['voting_t']);
        $cmod = substr('voting', 0, 2).'-'.$id;
        $cookies = (isset($_COOKIE[$cmod])) ? intval($_COOKIE[$cmod]) : '';
        $uid = (is_user()) ? intval(substr($user[0], 0, 11)) : 0;
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB."_rating WHERE time < :past AND modul = 'voting'", ['past' => $past]);
        list($num) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB."_rating WHERE (mid = :id AND modul = 'voting' AND ip = :ip) OR (mid = :id2 AND modul = 'voting' AND uid = :uid AND uid != '0')", ['id' => $id, 'ip' => $ip, 'id2' => $id, 'uid' => $uid]));
        list($modul, $title, $body, $answer, $enddate, $multi, $comments, $acomm, $typ, $status) = $db->getSqlRow($result);
        $rate = ($cookies == $id || $num > 0 || strtotime($enddate) <= time()) ? 1 : 0;
        if ($typ || !$typ && !$rate) {
            $body = explode('|', $body);
            $answer = explode('|', $answer);
            $vote = array_sum($answer);
            $form = (!$rate) ? '<form name="voting" id="form'.$votid.'" method="post">' : '';
            $cont = setTemplateBasic('voting-open', ['{%form%}' => $form, '{%title%}' => $title]);
            $pn = 0;
            for ($i = 0; $i < count($body); $i++) {
                $pn++;
                if ($pn > 5) $pn = 1;
                $n = $i + 1;
                if ($vote > 0) {
                    $proc = 100 * $answer[$i] / $vote;
                    $procent = number_format($proc, 2);
                } else {
                    $procent = '0.00';
                }
                if (!$rate) {
                    $itype = ($multi) ? 'checkbox' : 'radio';
                    $cont .= setTemplateBasic('voting-post', ['{%id%}' => $id, '{%n%}' => $n, '{%itype%}' => $itype, '{%name%}' => 'body[]', '{%text%}' => $body[$i]]);
                } else {
                    $cont .= setTemplateBasic('voting-view', ['{%text%}' => $body[$i], '{%text_safe%}' => filterText($body[$i]), '{%n%}' => $n, '{%pn%}' => $pn, '{%percent%}' => $procent, '{%votes_label%}' => _VOTES, '{%votes%}' => $answer[$i]]);
                }
            }
            list($vnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_voting WHERE '.$querylang, $qlang_params));
            $admin = (is_moder('voting') && $votid == 'voting') ? add_menu('<a href="'.$afile.'.php?name=voting&amp;op=add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?name=voting&amp;op=delete&amp;id='.$id."&amp;refer=1\" OnClick=\"return DelCheck(this, '"._DELETE.' &quot;'.$title."&quot;?');\" title=\""._ONDELETE.'">'._ONDELETE.'</a>') : '';
            $post = (!$rate) ? "<span OnClick=\"AjaxLoad('POST', '1', '".$votid."', 'go=1&amp;op=avoting_save&amp;id=".$id.'&amp;votid='.$votid."', { 'body%5B%5D':'"._SEROR1."' }); return false;\" title=\""._VOTE.'" class="sl_but_blue">'._VOTE.'</span>' : '';
            $polls = ($vnum > 1) ? '<a href="index.php?name=voting" title="'._POLLS.'" class="sl_but">'._POLLS.'</a>' : '';
            $votes = (!$modul && $votid != 'voting') ? '<a href="index.php?name=voting&amp;op=view&amp;id='.$id.'" title="'._VOTES.'" class="sl_votes">'._VOTES.': '.$vote.'</a>' : '<span class="sl_votes">'._VOTES.': '.$vote.'</span>';
            $comm = (!$modul && $acomm) ? '<a href="index.php?name=voting&amp;op=view&amp;id='.$id.'#'.$id.'" title="'._COMMENTS.'" class="sl_coms">'._COMMENTS.': '.$comments.'</a>' : '';
            $formend = (!$rate) ? '</form>' : '';
            $cont .= setTemplateBasic('voting-close', ['{%admin%}' => $admin, '{%post%}' => $post, '{%polls%}' => $polls, '{%votes%}' => $votes, '{%comm%}' => $comm, '{%formend%}' => $formend]);
        } else {
            $cont = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _VCLINFO]);
        }
    } else {
        $cont = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    return $cont;
}

# CPU load analyzer with cache in seconds (Windows 10/11, Linux/macOS)
function getCpuLoad(int $tcache = 2): array {
    static $cache = ['time' => 0, 'cpu' => _NO_INFO, 'info' => _NO_INFO];
    if (time() - $cache['time'] < $tcache) return [$cache['cpu'], $cache['info']];
    $percent = null;
    $allow = static function (string $path): bool {
        $obase = ini_get('open_basedir');
        if ($obase === false || $obase === '') return true;

        $npath = str_replace('\\', '/', $path);
        foreach (explode(PATH_SEPARATOR, $obase) as $base) {
            $base = trim((string)$base);
            if ($base === '' || $base === '.') continue;

            $cbase = rtrim(str_replace('\\', '/', $base), '/');
            if ($cbase === '') continue;

            if ($npath === $cbase || str_starts_with($npath, $cbase.'/')) {
                return true;
            }
        }
        return false;
    };
    $rfile = static function (string $path) use ($allow): string|false {
        if (!$allow($path)) return false;
        if (!is_file($path) || !is_readable($path)) return false;

        $content = file_get_contents($path);
        return ($content === false) ? false : $content;
    };
    if (stristr(PHP_OS, 'WIN')) {
        $out = [];
        $cmd = 'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "(Get-CimInstance Win32_Processor -ErrorAction SilentlyContinue | Measure-Object -Property LoadPercentage -Average).Average"';
        if (function_exists('exec')) exec($cmd, $out);
        if (!empty($out)) {
            $val = str_replace(',', '.', trim($out[0]));
            if (is_numeric($val)) $percent = (float)$val;
        }
        if ($percent === null) {
            $out = [];
            $cmd = 'wmic cpu get loadpercentage /all';
            if (function_exists('exec')) exec($cmd, $out);
            if ($out) {
                foreach ($out as $line) {
                    if ($line && preg_match('#^[0-9]+$#', $line)) {
                        $percent = (float)$line;
                        break;
                    }
                }
            }
        }
    } else {
        if (function_exists('sys_getloadavg')) {
            $tmp = sys_getloadavg();
            if (isset($tmp[0]) && is_numeric($tmp[0])) $raw = (float)$tmp[0];
        }
        $loadavg = $rfile('/proc/loadavg');
        if (!isset($raw) && $loadavg !== false) {
            $tmp = explode(' ', $loadavg);
            if (isset($tmp[0]) && is_numeric($tmp[0])) $raw = (float)$tmp[0];
        }
        $nproc = 0;
        $info = $rfile('/proc/cpuinfo');
        if ($info !== false) {
            preg_match_all('/^processor\s*:/m', $info, $matches);
            if (!empty($matches[0])) $nproc = count($matches[0]);
        }
        if ($nproc <= 0) $nproc = 1;
        if (isset($raw) && is_numeric($raw)) $percent = ($raw / $nproc) * 10.0;
    }
    if (is_numeric($percent)) {
        $cpu = round((float)$percent, 2);
        if ($cpu < 0) $cpu = 0.0;
        if ($cpu > 100) $cpu = 100.0;
        $info = _PLOAD1;
    } else {
        $cpu = $info = _NO_INFO;
    }
    $cache = ['time' => time(), 'cpu' => $cpu, 'info' => $info];
    return [$cpu, $info];
}

# Variable analyzer
function getVariables(): string {
 global $db, $conf;
    $cont = '';
    $cvar = explode(',', $conf['variables']);
    if ($cvar[1]) {
        list($cpu, $info) = getCpuLoad(4);
        $cpucls = 'sl_red sl_note';
        $cputtl = _RATE1.(($info) ? ' - '.$info : '');
        if ($cpu <= 50) {
            $cpucls = 'sl_green sl_note';
            $cputtl = _RATE5.(($info) ? ' - '.$info : '');
        } elseif ($cpu <= 80) {
            $cpucls = 'sl_orange sl_note';
            $cputtl = _RATE3.(($info) ? ' - '.$info : '');
        }
        $cpucont = _PLOAD.': <span title="'.$cputtl.'" class="'.$cpucls.'">'.$cpu.'</span> % <progress max="100" value="'.$cpu.'">'.$cpu.' %</progress>';
        $memuse = memory_get_usage();
        $memtxt = filterSize((string)$memuse);
        $memcls = 'sl_red sl_note';
        $memttl = _RATE1;
        if ($memuse <= 10485760) {
            $memcls = 'sl_green sl_note';
            $memttl = _RATE5;
        } elseif ($memuse <= 20971520) {
            $memcls = 'sl_orange sl_note';
            $memttl = _RATE3;
        }
        $memcont = _MEML.': <span title="'.$memttl.'" class="'.$memcls.'">'.$memtxt.'</span> <progress max="'.(str_replace('M', '', ini_get('memory_limit')) * 1024 * 1024).'" value="'.$memuse.'">'.$memtxt.'</progress>';
        $cont .= '<fieldset class="sl_sys_var"><legend style="color: darkgreen;">'._SYSTEM_INFO.'</legend>'.$cpucont.'<br>'.$memcont.'<br>'.getTimeLoads().'</fieldset>';
    }
    if ($cvar[2] && $_POST) $cont .= '<fieldset class="sl_sys_var"><legend style="color: green;">'._AVARIABLES.': POST</legend>'.htmlspecialchars(print_r($_POST, true)).'</fieldset>';
    if ($cvar[3] && $_GET) $cont .= '<fieldset class="sl_sys_var"><legend style="color: blue;">'._AVARIABLES.': GET</legend>'.htmlspecialchars(print_r($_GET, true)).'</fieldset>';
    if ($cvar[4] && $_COOKIE) $cont .= '<fieldset class="sl_sys_var"><legend style="color: orangered;">'._AVARIABLES.': COOKIE</legend>'.print_r($_COOKIE, true).'</fieldset>';
    if ($cvar[5] && $_FILES) $cont .= '<fieldset class="sl_sys_var"><legend style="color: purple;">'._AVARIABLES.': FILES</legend>'.print_r($_FILES, true).'</fieldset>';
    if ($cvar[6] && $_SESSION) $cont .= '<fieldset class="sl_sys_var"><legend style="color: fuchsia;">'._AVARIABLES.': SESSION</legend>'.print_r($_SESSION, true).'</fieldset>';
    if ($cvar[7] && $_SERVER) $cont .= '<fieldset class="sl_sys_var"><legend style="color: red;">'._AVARIABLES.': SERVER</legend>'.print_r($_SERVER, true).'</fieldset>';
    if ($cvar[8]) $cont .= '<fieldset class="sl_sys_var"><legend style="color: green;">'._AQUERY_DB.': MySQL</legend>'.$db->qtime.'</fieldset>';
    return $cont;
}

# Number of user news
function getUserNews(int $num): int {
    global $user, $conf;
    $unum = (int)($user[3] ?? 0);
    return ($unum > 0 && $unum <= $num && $conf['users']['news'] == 1) ? $unum : $num;
}

# Random password generation
function getPass(int $m): string {
    $m = intval($m);
    $pass = '';
    for ($i = 0; $i < $m; $i++) {
        $te = mt_rand(48, 122);
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

# Get the image from the text
function getImgText(string $text, string $type = '', bool $check = true): string|false {
 global $conf;
    if (preg_match('#\[attach=(.*?)\s(.*?)\]#i', $text, $match)) {
        $fname = basename(trim($match[1]));
        $img = (!$type) ? 'uploads/'.$conf['name'].'/thumb/'.$fname : 'uploads/'.$conf['name'].'/'.$fname;
    } elseif (preg_match('#\[img=[a-zA-Z]+\](.*?)\[/img\]#i', $text, $match)) {
        $img = trim($match[1]);
    } elseif (preg_match('#\[img\](.*?)\[/img\]#i', $text, $match)) {
        $img = trim($match[1]);
    } else {
        $img = '';
    }
    $img = empty($img) ? false : ($check ? (file_exists($img) ? $img : false) : $img);
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
    return 'index.php?'.implode('&amp;', $query);
}

function filterSlug(string $text, string $sep = '-'): string {
    $text = trim($text);
    static $rus = [
        'ÃÂ' => 'A',  'Ãâ€˜' => 'B',  'Ãâ€™' => 'V',  'Ãâ€œ' => 'G',  'Ãâ€' => 'D',  'Ãâ€¢' => 'E',  'ÃÂ' => 'E',  'Ãâ€“' => 'Zh',
        'Ãâ€”' => 'Z',  'ÃËœ' => 'I',  'Ãâ„¢' => 'I',  'ÃÅ¡' => 'K',  'Ãâ€º' => 'L',  'ÃÅ“' => 'M',  'ÃÂ' => 'N',  'ÃÅ¾' => 'O',
        'ÃÅ¸' => 'P',  'ÃÂ ' => 'R',  'ÃÂ¡' => 'S',  'ÃÂ¢' => 'T',  'ÃÂ£' => 'U',  'ÃÂ¤' => 'F',  'ÃÂ¥' => 'Kh', 'ÃÂ¦' => 'Ts',
        'ÃÂ§' => 'Ch', 'ÃÂ¨' => 'Sh', 'ÃÂ©' => 'Shch', 'ÃÂ«' => 'Y', 'ÃÂ­' => 'E', 'ÃÂ®' => 'Yu', 'ÃÂ¯' => 'Ya',
        'ÃÂª' => '',   'ÃÂ¬' => '',
        'ÃÂ°' => 'a',  'ÃÂ±' => 'b',  'ÃÂ²' => 'v',  'ÃÂ³' => 'g',  'ÃÂ´' => 'd',  'ÃÂµ' => 'e',  'Ã‘â€˜' => 'e',  'ÃÂ¶' => 'zh',
        'ÃÂ·' => 'z',  'ÃÂ¸' => 'i',  'ÃÂ¹' => 'i',  'ÃÂº' => 'k',  'ÃÂ»' => 'l',  'ÃÂ¼' => 'm',  'ÃÂ½' => 'n',  'ÃÂ¾' => 'o',
        'ÃÂ¿' => 'p',  'Ã‘â‚¬' => 'r',  'Ã‘Â' => 's',  'Ã‘â€š' => 't',  'Ã‘Æ’' => 'u',  'Ã‘â€ž' => 'f',  'Ã‘â€¦' => 'kh', 'Ã‘â€ ' => 'ts',
        'Ã‘â€¡' => 'ch', 'Ã‘Ë†' => 'sh', 'Ã‘â€°' => 'shch', 'Ã‘â€¹' => 'y', 'Ã‘Â' => 'e', 'Ã‘Å½' => 'yu', 'Ã‘Â' => 'ya',
        'Ã‘Å ' => '',   'Ã‘Å’' => '',
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
    if (defined('ADMIN_FILE')) return $cached = 'admin';
    $default = $conf['theme'] ?? 'default';
    if (!is_user()) return $cached = $default;
    $utheme = $user[5] ?? '';
    if ($utheme !== '' && is_dir(BASE_DIR.'/templates/'.$utheme)) return $cached = $utheme;
    return $cached = $default;
}

# Format theme file
function getThemeFile(string $name): string|false {
 global $home, $conf, $op;
    static $cache = [];
    static $files = null;
    static $dir = null;
    if ($files === null) {
        $dir = BASE_DIR.'/templates/'.getTheme().'/';
        $files = array_flip(scandir($dir, SCANDIR_SORT_NONE) ?: []);
    }
    $tpl = $conf['template'] ?? '';
    $mod = $conf['name'] ?? '';
    $opv = $op ?? '';
    $cat = getVar('get', 'cat', 'num', 0);
    $key = $name.'|'.(int)$home.'|'.$tpl.'|'.$mod.'|'.$opv.'|'.$cat;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $candidates = [];
    if ($home) {
        $candidates[] = $name.'-home';
    } elseif ($tpl !== '') {
        $candidates[] = $name.'-'.$tpl;
    } elseif ($mod !== '' && $opv !== '') {
        $candidates[] = $name.'-'.$mod.'-'.$opv;
        $candidates[] = $name.'-'.$mod;
    } elseif ($mod !== '' && $cat > 0) {
        $candidates[] = $name.'-'.$mod.'-cat-'.$cat;
        $candidates[] = $name.'-'.$mod;
    } elseif ($mod !== '') {
        $candidates[] = $name.'-'.$mod;
    }
    $candidates[] = $name;
    foreach ($candidates as $fname) {
        $file = $fname.'.html';
        if (isset($files[$file])) return $cache[$key] = $dir.$file;
    }
    return $cache[$key] = false;
}

# Get theme load
function getThemeLoad(string $tpl): string {
    $path = getThemeFile($tpl);
    if (!$path) return '';
    static $cache = [];
    if (array_key_exists($path, $cache)) return $cache[$path];
    $raw = file_get_contents($path);
    return $cache[$path] = $raw !== false ? $raw : '';
}

# Determining the load time
function getTimeLoads(): string {
 global $db, $sgtime;
    $ttime = sprintf('%.3f', microtime(true) - $sgtime);
    $qnums = $db->qnum;
    $sqltime = sprintf('%.3f', $db->sqltime);
    $cont = _GENERATION.': '.$ttime.' '._SEC.'. '._AND.' '.$qnums.' '._GENERATION_DB.' '.$sqltime.' '._SEC.'.';
    return $cont;
}

# Notify subscribed admins by email on new content or comment submission
function addAdminMail(bool $enab, string $mod, string $username = '', string $title = '', bool $iscmt = false, string $text = ''): void {
    global $db, $conf, $locale;
    $mod = filterVar($mod);
    if ($enab && $mod) {
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
        $result = $db->getSqlQuery('SELECT id, email, super, modules FROM '.PREFIX_DB.'_admins'.$where.' ORDER BY id', $params);
        while ($row = $db->getSqlRow($result)) {
            [$id, $email, $super, $modules] = $row;
            if ($super) {
                addMail($email, $conf['adminmail'], $subject, $message, 1, 1);
            } else {
                $amid = getAdminModuleNames($modules);
                $nmods = implode(',', $amid);
                if ($nmods !== $modules) {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_admins SET modules = :modules WHERE id = :id', ['modules' => $nmods, 'id' => $id]);
                }
                foreach ($amid as $val) {
                    if ($val !== '' && $val === $mod) {
                        addMail($email, $conf['adminmail'], $subject, $message, 1, 1);
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

# Newsletter send
function updateNewsletter(bool $force = false): array {
 global $db, $conf;
    if ($force || $conf['newsletter']['active']) {
        $result = $db->getSqlQuery('SELECT id, title, body, mails FROM '.PREFIX_DB."_newsletter WHERE mails != ''");
        if ($db->getSqlRowCount($result) > 0) {
            list($id, $title, $body, $mails) = $db->getSqlRow($result);
            $ncount = intval($conf['newsletter']['count']);
            $id = intval($id);
            $mails = explode(',', $mails);
            $outmail = array_values(array_filter(array_slice($mails, 0, $ncount), 'strlen'));
            $inmail = implode(',', array_slice($mails, $ncount));
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_newsletter SET mails = :mails, send = send + :cnt, endtime = NOW() WHERE id = :id', ['mails' => $inmail, 'cnt' => $ncount, 'id' => $id]);
            foreach ($outmail as $val) addMail($val, $conf['adminmail'], $title, filterReplaceText(filterMarkdown($body, 'all', false), 'all'), 0, 3);
            if (!$inmail) {
                $cont = ['active' => '0'];
                setConfigFile('newsletter.php', $cont, $conf['newsletter']);
            }
            return [
                'status' => 'success',
                'message' => 'Newsletter batch completed',
                'extra' => [
                    'last_newsletter_id' => $id,
                    'last_batch_count' => count($outmail),
                    'remaining_count' => $inmail === '' ? 0 : count(array_filter(explode(',', $inmail), 'strlen')),
                ],
            ];
        }
    }
    return ['status' => 'idle', 'message' => 'No pending newsletter batches'];
}

# Resolve a PHP constant by name; return the name itself if undefined
function getConst(string $con): string {
    return defined($con) ? constant($con) : $con;
}

# Resolve a module key to its localised name constant
function getModuleName(string $con): string {
    $map = ['account' => _ACCOUNT, 'album' => _ALBUM, 'all' => _ALL, 'auto_links' => _A_LINKS, 'changelog' => _CHANGELOG, 'clients' => _CLIENTS, 'contact' => _FEEDBACK, 'content' => _CONTENT, 'faq' => _FAQ, 'files' => _FILES, 'forum' => _FORUM, 'gallery' => _ALBUM, 'help' => _HELP, 'info' => _INFO, 'jokes' => _JOKES, 'links' => _LINKS, 'main' => _MAIN, 'media' => _MEDIA, 'members' => _USERS, 'money' => _MONEY, 'news' => _NEWS, 'order' => _ORDER, 'pages' => _PAGES, 'radio' => _RADIO, 'recommend' => _RECOMMEND, 'rss_info' => _RSS, 'search' => _SEARCH, 'shop' => _SHOP, 'sitemap' => _SITEMAP, 'users' => _TOPUSERS, 'voting' => _VOTING, 'whois' => _WHOIS];
    return $map[$con] ?? $con;
}

# Resolve a language code to its localised name constant
function getLangName(string $con): string {
    $map = ['en' => _ENGLISH, 'fr' => _FRENCH, 'de' => _GERMAN, 'pl' => _POLISH, 'ru' => _RUSSIAN, 'uk' => _UKRAINIAN];
    return $map[$con] ?? $con;
}

# Hash a user password with bcrypt
function getPassHash(string $pass): string {
    return password_hash($pass, PASSWORD_BCRYPT);
}

# Verify a user password; supports current bcrypt and legacy md5 hashes transparently.
# Legacy branch will be removed once all stored hashes have been upgraded via transparent rehashing.
function checkPassHash(string $pass, string $hash): bool {
    global $conf;
    if (password_verify($pass, $hash)) return true;
    if (strlen($hash) === 32 && ctype_xdigit($hash)) return md5(md5((string)($conf['lic_f'] ?? '')).md5($pass)) === $hash;
    return false;
}

####
# OLD FUNCTIONS (for backward compatibility, not recommended for use in new code)
####

# Format Time
function datetime(int $id, string $name, string $time, int $max, string $class): string {
    static $jscript;
    $time = ($time) ? substr($time, 0, $max) : (($id == 1) ? date('Y-m-d H:i') : date('Y-m-d'));
    $class = ($class) ? 'sl_field '.$class : 'sl_field';
    if ($id == 1) {
        $format = "dateFormat: 'yy-mm-dd', timeFormat: 'HH:mm'";
        $typ = 'datetimepicker';
    } else {
        $format = "dateFormat: 'yy-mm-dd', yearRange: '".(date('Y') - 100).':'.date('Y')."'";
        $typ = 'datepicker';
    }
    if (!isset($jscript)) {
        $cont = '<script src="plugins/jquery/ui/jquery-ui-timepicker.js"></script>'
        .'<script src="plugins/jquery/ui/langs/'.substr(_LOCALE, 0, 2).'.js"></script>';
        $jscript = 1;
    } else {
        $cont = '';
    }
    $cont .= "<script>$(function() { $('#".$name."').".$typ.'({ changeMonth: true, changeYear: true, '.$format."}, $.timepicker.regional['".substr(_LOCALE, 0, 2)."']); });</script>"
    .'<input type="text" id="'.$name.'" name="'.$name.'" value="'.$time.'" maxlength="'.$max.'" class="'.$class.'">';
    return $cont;
}

# Format Time filter
function format_time(string $time, string $string = ''): string {
    $string = ($string) ? $string : _DATESTRING;
    $cont = date($string, strtotime($time));
    return $cont;
}

# Format new graphic
function new_graphic(string $time): string {
    $data = time() - strtotime($time);
    $img = '';
    if ($data < 86400) $img = '<span title="'._NEWTODAY.'" class="sl_n_day"></span>';
    if (($data > 86400) && ($data < 259200)) $img = '<span title="'._NEWLAST3DAYS.'" class="sl_n_days"></span>';
    if (($data > 259200) && ($data < 604800)) $img = '<span title="'._NEWTHISWEEK.'" class="sl_n_week"></span>';
    return $img;
}

# Format radio form
function radio_form(mixed $var, string $name, string $id = ''): string {
    if ($id == 1) {
        $sel1 = (!$var) ? 'checked' : '';
        $sel2 = ($var) ? 'checked' : '';
        $content = '<label><input type="radio" name="'.$name.'" value="0" '.$sel1.'> '._YES.' </label><label><input type="radio" name="'.$name.'" value="1" '.$sel2.'> '._NO.'</label>';
    } else {
        $sel1 = ($var || !$var) ? 'checked' : '';
        $sel2 = ($var == '0') ? 'checked' : '';
        $content = '<label><input type="radio" name="'.$name.'" value="1" '.$sel1.'> '._YES.' </label><label><input type="radio" name="'.$name.'" value="0" '.$sel2.'> '._NO.'</label>';
    }
    return $content;
}

# Get gender
function get_gender(string $name, int $typ, string $class): string {
    $gender = [_NO_INFO, _MAN, _WOMAN];
    $cont = '<select name="'.$name.'" class="sl_field '.$class.'">';
    foreach ($gender as $key => $val) {
        $select = ($key == $typ) ? ' selected' : '';
        $cont .= '<option value="'.$key.'"'.$select.'>'.$val.'</option>';
    }
    $cont .= '</select>';
    return $cont;
}

# Format gender
function gender(int $gender): string {
    if ($gender == 2) {
        $gen = _WOMAN;
    } elseif ($gender == 1) {
        $gen = _MAN;
    } else {
        $gen = _NO_INFO;
    }
    return $gen;
}


# Replace break
function replace_break(string $text): string {
 global $admin, $conf;
    if ($text) {
        $flag = is_array($admin) ? ($admin[3] ?? '') : '';
        $editor = (int)substr($flag, 0, 1);
        $out = ((defined('ADMIN_FILE') && $editor == 1) || (!defined('ADMIN_FILE') && $conf['redaktor'] == 1)) ? preg_replace('#<br.*>#i', '', $text) : $text;
        return $out;
    }
    return '';
}

# DELETE OR MODIFY
# User country information
function user_geo_ip(string $ip, int $id = 4): string {
 global $conf;
    if ((PHP_VERSION >= '5') && $conf['geo_ip'] && preg_match('#([0-9]{1,3}).([0-9]{1,3}).([0-9]{1,3}).([0-9]{1,3})#', $ip)) {
        if ($id == 1) {
            $cont = $ip;
        } elseif ($id == 2) {
            $cont = $ip;
        } elseif ($id == 3) {
            $name = $ip;
            $img = str_replace(' ', '_', strtolower($name));
            $imgl = (file_exists(img_find('lang/'.$img.'.png'))) ? img_find('lang/'.$img.'.png') : (($img == '?') ? img_find('lang/white.png') : img_find('lang/white.png'));
            $cont = '<img src="'.$imgl.'" alt="'.$name.'" title="'.$name.'" class="sl_flag">';
        } elseif ($id == 4) {
            $name = $ip;
            $img = str_replace(' ', '_', strtolower($name));
            $imgl = (file_exists(img_find('lang/'.$img.'.png'))) ? img_find('lang/'.$img.'.png') : (($img == '?') ? img_find('lang/white.png') : img_find('lang/white.png'));
            $cont = '<img src="'.$imgl.'" alt="'.$name.'" title="'.$name.'" class="sl_flag"><a href="'.$conf['ip_link'].$ip.'" target="_blank" title="'._IP.': '.$ip.'">'.$ip.'</a>';
        }
    } else {
        $cont = ($id == 4) ? '<a href="'.$conf['ip_link'].$ip.'" target="_blank" title="'._IP.': '.$ip.'">'.$ip.'</a>' : '';
    }
    return $cont;
}

# User information for user
function user_sinfo(string $id = ''): string {
 global $db, $conf;
    if ($conf['session']) {
        $who_online = ''; $m = 0; $b = 0; $u = 0; $i = 0;
        $result = $db->getSqlQuery('SELECT uname, time, ip, guest, modul FROM '.PREFIX_DB.'_session ORDER BY uname');
        while (list($uname, $time, $host, $guest, $module) = $db->getSqlRow($result)) {
            $time = time() - $time;
            $strip = cutstr($uname, 15);
            $module = getModuleName($module);
            $linkstrip = cutstr($module, 15);
            if ($guest == 2) {
                $who_online .= '<tr><td>'.user_geo_ip($host, 3).'<a href="index.php?name=account&amp;op=view&amp;uname='.urlencode($uname).'" title="'.getDuration($time).'">'.$strip.'</a></td><td title="'.$module.'" class="sl_right sl_note">'.$linkstrip.'</td></tr>';
                $m++;
            } elseif ($guest == 1 && $conf['botsact']) {
                $who_online .= '<tr><td>'.user_geo_ip($host, 3).'<span title="'.getDuration($time).'" class="sl_note">'.$strip.'</span></td><td title="'.$module.'" class="sl_right sl_note">'.$linkstrip.'</td></tr>';
                $b++;
            } else {
                $who_online .= '';
                $u++;
            }
            $i++;
        }
        $content = '<hr><table class="sl_table_block"><tr><td>'._BMEM.':</td><td class="sl_right">'.$m.'</td></tr>';
        if ($conf['botsact']) $content .= '<tr><td>'._BOTS.':</td><td class="sl_right">'.$b.'</td></tr>';
        $content .= '<tr><td>'._BVIS.':</td><td class="sl_right">'.$u.'</td></tr><tr><td>'._OVERALL.':</td><td class="sl_right">'.$i."</td></tr></table><hr><table class=\"sl_table_block\"><tr><td class=\"sl_center\"><a OnClick=\"AjaxLoad('GET', '0', 'sinfo', 'go=1&amp;op=user_sinfo', ''); return false;\" title=\""._UPDATE.'" class="sl_but_green">'._UPDATE.'</a>';
        $content .= ($who_online) ? "<a OnClick=\"HideShow('u-block', 'slide', 'up', 500);\" title=\""._READMORE.'" class="sl_but_blue">'._READMORE.'</a></td></tr></table><table id="u-block" class="sl_table_block sl_none">'.$who_online.'</table>' : '</td></tr></table>';
        if ($id) { return $content; } else { echo $content; }
    }
    return '';
}

# User information for admin
function user_sainfo(string $id = ''): string {
 global $db, $conf;
    if ($conf['session'] && isAdmin()) {
        $a = $b = $m = $u = $i = 0;
        $who_online = ['0' => '', '1' => '', '2' => '', '3' => ''];
        $content_who = '';
        $result = $db->getSqlQuery('SELECT uname, time, ip, guest, modul, url FROM '.PREFIX_DB.'_session ORDER BY uname');
        while (list($uname, $time, $host, $guest, $module, $url) = $db->getSqlRow($result)) {
            $time = time() - $time;
            $namestrip = cutstr($uname, 15);
            $lstrip = cutstr($module, 15);
            $alink = htmlspecialchars(urldecode($url));
            $alstrip = cutstr($alink, 15);
            $guest = intval($guest);
            if ($guest == 3) {
                $title_who = '<tr><td>'.user_geo_ip($host, 3).'<a href="'.$conf['ip_link'].$host.'" title="'.getDuration($time).' - '._IP.': '.$host.'" target="_blank">'.$namestrip.'</a></td><td class="sl_right"><a href="'.$alink.'" title="'.$alink.'" target="_blank">'.$alstrip.'</a></td></tr>';
                $a++;
            } elseif ($guest == 2) {
                if ($lstrip != '') {
                    $title_who = '<tr><td>'.user_geo_ip($host, 3).'<a href="index.php?name=account&amp;op=view&amp;uname='.urlencode($uname).'" title="'.getDuration($time).' - '._IP.': '.$host.'" target="_blank">'.$namestrip.'</a></td><td class="sl_right"><a href="'.$alink.'" title="'.$alink.'" target="_blank">'.$lstrip.'</a></td></tr>';
                    $m++;
                } else {
                    $title_who = '<tr><td>'.user_geo_ip($host, 3).'<a href="index.php?name=account&amp;op=view&amp;uname='.urlencode($uname).'" title="'.getDuration($time).' - '._IP.': '.$host.'" target="_blank">'.$namestrip.'</a></td><td class="sl_right"><a href="'.$alink.'" title="'.$alink.'" target="_blank">'.$alstrip.'</a></td></tr>';
                }
            } elseif ($guest == 1) {
                $title_who = '<tr><td>'.user_geo_ip($host, 3).'<a href="'.$conf['ip_link'].$host.'" title="'.getDuration($time).' - '._IP.': '.$host.'" target="_blank">'.$namestrip.'</a></td><td class="sl_right"><a href="'.$alink.'" title="'.$alink.'" target="_blank">'.$lstrip.'</a></td></tr>';
                $b++;
            } else {
                $title_who = ($u < 250) ? '<tr><td>'.user_geo_ip($host, 3).'<a href="'.$conf['ip_link'].$host.'" title="'.getDuration($time).'" target="_blank">'.$uname.'</a></td><td class="sl_right"><a href="'.$alink.'" title="'.$alink.'" target="_blank">'.$lstrip.'</a></td></tr>' : '';
                $u++;
            }
            $who_online[$guest] .= $title_who;
            $i++;
        }
        $content_who .= (isAdmin(true)) ? "<table class=\"sl_table_block\"><tr><td><a OnClick=\"HideShow('ad-block', 'slide', 'up', 500);\" title=\""._READMORE.'" class="sl_plus">'._ADMINS.':</a></td><td class="sl_right">'.$a.'</td></tr></table><table id="ad-block" class="sl_table_block sl_none">'.$who_online[3].'</table>' : '';
        $content_who .= "<table class=\"sl_table_block\"><tr><td><a OnClick=\"HideShow('us-block', 'slide', 'up', 500);\" title=\""._READMORE.'" class="sl_plus">'._BMEM.':</a></td><td class="sl_right">'.$m.'</td></tr></table><table id="us-block" class="sl_table_block sl_none">'.$who_online[2].'</table>'
        ."<table class=\"sl_table_block\"><tr><td><a OnClick=\"HideShow('bo-block', 'slide', 'up', 500);\" title=\""._READMORE.'" class="sl_plus">'._BOTS.':</a></td><td class="sl_right">'.$b.'</td></tr></table><table id="bo-block" class="sl_table_block sl_none">'.$who_online[1].'</table>'
        ."<table class=\"sl_table_block\"><tr><td><a OnClick=\"HideShow('an-block', 'slide', 'up', 500);\" title=\""._READMORE.'" class="sl_plus">'._BVIS.':</a></td><td class="sl_right">'.$u.'</td></tr></table><table id="an-block" class="sl_table_block sl_none">'.$who_online[0].'</table>'
        .'<table class="sl_table_block"><tr><td>'._OVERALL.':</td><td class="sl_right">'.$i."</td></tr></table><hr><table class=\"sl_table_block\"><tr><td class=\"sl_center\"><a OnClick=\"AjaxLoad('GET', '0', 'sainfo', 'go=1&amp;op=user_sainfo', ''); return false;\" title=\""._UPDATE.'" class="sl_but_green">'._UPDATE.'</a></td></tr></table>';
        if ($id) { return $content_who; } else { echo $content_who; }
    }
    return '';
}

# Format admin block
function adminblock(): string {
 global $db, $afile;
    if (isAdmin()) {
        $title = '';
        $content = '';
        $cont = '<table class="sl_table_block"><tr><td><a href="'.$afile.'.php" title="'._ADMINMENU.'">'._ADMINMENU.'</a></td></tr>'
        .'<tr><td><a href="'.$afile.'.php?op=logout" title="'._LOGOUT.'">'._LOGOUT.'</a></td></tr></table>';
        if (isAdmin(true)) {
            list($title, $content) = $db->getSqlRow($db->getSqlQuery('SELECT title, content FROM '.PREFIX_DB."_blocks WHERE bkey = 'admin'"));
            $cont .= '<hr>'.$content;
        }
        $a_title = ($title) ? $title : _ADMINS;
        return setTemplateBlock('block-left', ['{%title%}' => $a_title, '{%content%}' => $cont, '{%id%}' => '7']).setTemplateBlock('block-left', ['{%title%}' => _WHO, '{%content%}' => '<div id="repsainfo">'.user_sainfo(1).'</div>', '{%id%}' => '8']);
    }
    return '';
}

# User info link
function user_info(string $name): string {
    global $conf;
    if ($name) {
        $link = ($conf['users']['prof'] != 1 || ($conf['users']['prof'] == 1 && is_user()) || isAdmin()) ? '<a href="index.php?name=account&amp;op=view&amp;uname='.urlencode($name).'" title="'._PERSONALINFO.'">'.$name.'</a>' : $name;
    } else {
        $link = '';
    }
    return $link;
}

# Show kasse
function show_kasse(string $info = ''): string {
 global $db, $conf;
    $shop = (isset($_COOKIE['shop'])) ? base64_decode($_COOKIE['shop']) : '';
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
        $cont = '';
        $ptotal = 0;
        while (list($id, $time, $title, $price) = $db->getSqlRow($result)) {
            $i = 0;
            foreach ($massiv as $val) {
                if ($val == $id) $i++;
            }
            $price = $price * $i;
            $ptotal += $price;
            $ptitle = '<a href="index.php?name=shop&amp;op=view&amp;id='.$id.'" title="'.$title.'">'.$title.'</a> '.new_graphic($time);
            $mtitle = ($i > 1) ? _PMINUS : _DELETE;
            $plus = "<a OnClick=\"AjaxLoad('GET', '0', 'kasse', 'go=2&amp;op=add_kasse&amp;id=".$id."', ''); return false;\" title=\""._PPLUS.'" class="sl_shop_plus"></a>';
            $minus = "<a OnClick=\"AjaxLoad('GET', '0', 'kasse', 'go=2&amp;op=del_kasse&amp;id=".$id."', ''); return false;\" title=\"".$mtitle.'" class="sl_shop_minus"></a>';
            $cont .= setTemplateBasic('kasse-basic', ['{%id%}' => $id, '{%title%}' => $ptitle, '{%qty%}' => $i, '{%price%}' => $price.' '.$conf['shop']['valute'], '{%plus%}' => $plus, '{%minus%}' => $minus]);
        }
        $cart = '<a href="index.php?name=shop&amp;op=kasse" title="'._SCACH.'" class="sl_shop_kasse">'._SCACH.'</a>';
        $total = '<span title="'._PARTNERGES.'" class="sl_shop_total">'._PARTNERGES.': '.$ptotal.' '.$conf['shop']['valute'].'</span>';
        return setTemplateBasic('kasse-open', ['{%title%}' => _PBASKET, '{%col_id%}' => _ID, '{%col_product%}' => _PRODUCT, '{%col_qty%}' => cutstr(_QUANTITY, 3, 1), '{%col_price%}' => _PREIS, '{%col_fn%}' => _FUNCTIONS]).$cont.setTemplateBasic('kasse-close', ['{%cart%}' => $cart, '{%total%}' => $total]);
    }
    return '';
}

# Add kasse
function add_kasse(): void {
    global $conf;
    $id = getVar('get', 'id', 'num', 0);
    $cookies = (preg_match('#[^0-9,]#', base64_decode($_COOKIE['shop']))) ? '' : base64_decode($_COOKIE['shop']);
    if ($id) {
        setcookie('shop', false);
        if ($cookies) {
            $info = base64_encode($cookies.','.$id);
            setcookie('shop', $info, time() + $conf['shop']['shop_t']);
        } else {
            $info = base64_encode($id);
            setcookie('shop', $info, time() + $conf['shop']['shop_t']);
        }
    }
    echo show_kasse($info);
}

# Delete kasse
function del_kasse(): void {
    global $conf;
    $id = getVar('get', 'id', 'num', 0);
    $cookies = (preg_match('#[^0-9,]#', base64_decode($_COOKIE['shop']))) ? '' : base64_decode($_COOKIE['shop']);
    if ($id && $cookies) {
        $massiv = explode(',', $cookies);
        setcookie('shop', false);
        $i = 0;
        $a = 0;
        $b = 0;
        foreach ($massiv as $val) {
            if ($val == $id && $a == 0) {
                $i++;
                $a++;
                $val = '';
            } else {
                if ($b == 0) {
                    $info = $val;
                    $b++;
                } else {
                    $info .= ','.$val;
                }
            }
        }
        $info = base64_encode($info);
        setcookie('shop', $info, time() + $conf['shop']['shop_t']);
    }
    echo show_kasse($info);
}

# Format user warnings
function warnings(string $warnings): string {
    if ($warnings) {
        $warns = explode('|', $warnings);
        $cont = '<ol>';
        foreach ($warns as $val) $cont .= ($val != '') ? '<li>'.$val.'</li>' : '';
        $cont .= '</ol>';
    } else {
        $cont = _NO;
    }
    return $cont;
}

# Format ajax rating
function ajax_rating(mixed $typ, mixed $id, mixed $mod, mixed $rat, mixed $scor, string $obj = '', string $stl = ''): string {
    global $conf;
    if (intval($rat)) {
        $votnum = $rat;
        $votes = $rat;
    } else {
        $votnum = 0;
        $votes = 1;
    }
    $width = number_format($scor / $votes, 2) * 20;
    $result = substr($scor / $votes, 0, 4);
    if (intval($votes) && intval($scor)) {
        $title = _RATING.': '.$result.'/'.$votes.' '._AVERAGESCORE.': '.$result;
        $nrate = 'sl_rate-num sl_rate-is';
    } else {
        $title = _RATING.': 0/0 '._AVERAGESCORE.': 0';
        $nrate = 'sl_rate-num';
    }
    if ($stl == 1) {
        $img = '<span class="sl_none">'.$result.'</span><div class="sl_rate-like"><p title="'._RATE1.'" class="sl_rate-minus"><p title="'._RATE5.'" class="sl_rate-plus"></div><span title="'.$title.'" class="'.$nrate.'">'.$result.'</span>';
        $imgr = '<span class="sl_none">'.$result."</span><div OnMouseOver=\"AjaxLoad('GET', '0', '".$id.$obj."', 'go=1&amp;op=rating&amp;id=".$id.'&amp;typ='.$obj.'&amp;mod='.$mod."&amp;stl=1', ''); return false;\" class=\"sl_rate-like\"><p title=\""._RATE1.'" class="sl_rate-minus"><p title="'._RATE5.'" class="sl_rate-plus"></div><span class="'.$nrate.'" title="'.$title.'">'.$result.'</span>';
        $crate = 'sl_rate-like';
    } else {
        $img = '<span class="sl_none">'.$result.'</span><ul title="'.$title.'" class="sl_urating"><li class="sl_crating" style="width: '.$width.'%;"></li></ul><span title="'._VOTES.'" class="'.$nrate.'">'.$votnum.'</span>';
        $imgr = '<span class="sl_none">'.$result."</span><ul OnMouseOver=\"AjaxLoad('GET', '0', '".$id.$obj."', 'go=1&amp;op=rating&amp;id=".$id.'&amp;typ='.$obj.'&amp;mod='.$mod."', ''); return false;\" title=\"".$title.'" class="sl_urating"><li class="sl_crating" style="width: '.$width.'%;"></li></ul><span title="'._VOTES.'" class="'.$nrate.'">'.$votnum.'</span>';
        $crate = 'sl_rate';
    }
    if ($typ == 2) {
        $content = '<div class="'.$crate.'">'.$img.'</div>';
    } else {
        $con = explode('|', $conf['ratings'][strtolower($mod)]);
        if (($con[1] && $id && $mod) || ($rat && $scor)) {
            $content = (($con[1] && $typ) || ($con[1] && !$con[2] && !$typ)) ? '<div id="rep'.$id.$obj.'" class="'.$crate.'">'.$imgr.'</div>' : '<div class="'.$crate.'">'.$img.'</div>';
        } else {
            $content = '';
        }
    }
    return $content;
}

# Show editor files
function show_files(): void {
    global $conf, $user;
    $id   = filterVar(getVar('get', 'id',   'text', '')) ?: 0;
    $dir  = strtolower(getVar('get', 'dir',  'text', ''));
    $cid = getVar('get', 'cid', 'num', 0);
    $con = explode('|', (string)($conf['uploads'][$dir] ?? ''));
    $connum = (isset($con[7]) && intval($con[7])) ? $con[7] : '50';
    $eallf = (is_moder()) ? intval($con[8] ?? 0) : intval($con[9] ?? 0);
    $file = filterText(getVar('get', 'file', 'raw', ''));
    $num = ($cid) ? $cid : '1';
    $uname = (is_user()) ? intval($user[0]) : 0;
    $path = 'uploads/'.$dir.'/';
    $files = [];
    $contents = [];
    $a = 0;
    if (is_moder($dir) && $file && $dir) {
        if (!$cid) {
            unlink($path.$file);
        } else {
            addCompress($path, $path.$file, $file);
        }
    }
    $dh = opendir($path);
    while ($entry = readdir($dh)) {
        if ($entry != '.' && $entry != '..' && $entry != 'index.html' && !is_dir($path.$entry)) $files[] = [filemtime($path.$entry), $entry];
    }
    closedir($dh);
    if ($files) {
        rsort($files);
        foreach ($files as $entry) {
            preg_match("#([a-zA-Z0-9]+)\-([a-zA-Z0-9]+)\-([0-9]+)\.([a-zA-Z0-9]+)#", $entry[1], $date);
            if (($uname == $date[3] && $date[2] && $date[1]) || is_moder($dir)) {
                $filesize = filesize($path.$entry[1]);
                list($imgwidth, $imgheight) = getimagesize($path.$entry[1]);
                $type = strtolower(substr(strrchr($entry[1], '.'), 1));
                $ftype = ['png', 'jpg', 'jpeg', 'gif', 'bmp'];
                if (in_array($type, $ftype) && $imgwidth && $imgheight) {
                    $img = "<div OnClick=\"HideShow('sf-form-".$a."', 'fold', 'up', 500);\" class=\"sl_drop sl_preview_mini\" style=\"background-image: url(".$path.$entry[1].');" title="'._IMG.'"><span id="sf-form-'.$a.'" class="sl_drop-form"><img src="'.$path.$entry[1].'" alt="'._IMG.'" title="'._IMG.'"></span></div>';
                    $show = "<a OnClick=\"InsertCode('attach', '".$entry[1]."', '', '', '".$id."')\" title=\""._INSERT.' '.$imgwidth.' x '.$imgheight.'">'._INSERT."</a>||<a OnClick=\"InsertCode('img', '".$path.$entry[1]."', '', '', '".$id."')\" return false;\" title=\""._EIMG.' '.$imgwidth.' x '.$imgheight.'">'._EIMG.'</a>';
                } else {
                    $img = '<div class="sl_preview_mini" style="background-image: url(templates/'.$conf['theme'].'/images/categories/no.png);" title="'._NO.'"></div>';
                    $show = "<a OnClick=\"InsertCode('attach', '".$entry[1]."', '', '', '".$id."')\" title=\""._INSERT.'">'._INSERT.'</a>';
                }
                if (is_moder($dir)) {
                    $show .= (in_array(true, checkCompress(), true)) ? "||<a OnClick=\"AjaxLoad('GET', '0', 'f".$id."', 'go=1&amp;op=show_files&amp;id=".$id.'&amp;dir='.$dir.'&amp;cid=1&amp;file='.$entry[1]."', ''); return false;\" title=\""._ZIP.'">'._ZIP.'</a>' : '';
                    $show .= "||<a OnClick=\"AjaxLoad('GET', '0', 'f".$id."', 'go=1&amp;op=show_files&amp;id=".$id.'&amp;dir='.$dir.'&amp;cid=0&amp;file='.$entry[1]."', ''); return false;\" title=\""._ONDELETE.'">'._ONDELETE.'</a>';
                }
                $contents[] = '<tr><td>'.$img.'</td><td>'.$entry[1].'</td><td>'.filterSize($filesize).'</td><td>'.add_menu($show).'</td></tr>';
                $a++;
            }
            if ($eallf && $a == $eallf) break;
        }
    }
    $numpages = ($a > 0) ? ceil($a / $connum) : 0;
    $offset = ($num - 1) * $connum;
    $tnum = ($offset) ? $connum + $offset : $connum;
    $cont = '';
    for ($i = $offset; $i < $tnum; $i++) {
        if (!empty($contents[$i])) $cont .= $contents[$i];
    }
    $contnum = ($a > $connum) ? num_ajax('pagenum', $a, $numpages, $connum, 8, $num, '0', 1, 'show_files', 'f'.$id, $id, '', $dir) : '';
    $content = ($cont) ? '<table class="sl_table_ajax"><thead class="sl_table_ajax_head"><tr><th>'.cutstr(_IMG, 4, 1).'</th><th>'._FILE.'</th><th>'._SIZE.'</th><th>'._FUNCTIONS.'</th></tr></thead><tbody class="sl_table_ajax_body">'.$cont.'</tbody></table>'.$contnum : '';
    echo $content;
}

# Add downloads
function stream(string $url, string $name): void {
    header('Content-Type: application/force-download');
    header('Content-Range: bytes');
    header('Content-Length: '.filesize($url));
    header('Content-Disposition: attachment; filename='.$name);
    readfile($url);

    /* https://secure.php.net/manual/ru/function.readfile.php
    if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }*/
}

# Anti spam
function anti_spam(string $mail): string {
    preg_match('#^(.*?)(@)(.*?)$#', $mail, $info);
    $cont = "<script>\"mysi\".AddMail('".$info[1]."', '".$info[3]."');</script><noscript>".$info[1].'<!-- slaed --><span>&#64;</span><!-- slaed -->'.$info[3].'</noscript>';
    return $cont;
}

# Format letter
function letter(string $mod): string {
 global $db, $user;
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
    $cont = '';
    foreach(range(0, 9) as $num) $cont .= (in_array("$num", $alpha)) ? '<a href="index.php?name='.$mod.'&amp;op=liste&amp;let='.$num.'" title="'.$num.'"><span class="sl_letter">'.$num.'</span></a>' : '<span class="sl_letter">'.$num.'</span>';
    $cont .= '<br>';
    foreach (preg_split('//u', _ALPHABET, -1, PREG_SPLIT_NO_EMPTY) as $char) $cont .= (in_array($char, $alpha)) ? '<a href="index.php?name='.$mod.'&amp;op=liste&amp;let='.urlencode($char).'" title="'.$char.'"><span class="sl_letter">'.$char.'</span></a>' : '<span class="sl_letter">'.$char.'</span>';
    if (substr(_LOCALE, 0, 2) != 'fr') {
        $cont .= '<br>';
        foreach(range('A', 'Z') as $eng) $cont .= (in_array($eng, $alpha)) ? '<a href="index.php?name='.$mod.'&amp;op=liste&amp;let='.$eng.'" title="'.$eng.'"><span class="sl_letter">'.$eng.'</span></a>' : '<span class="sl_letter">'.$eng.'</span>';
    }
    return $cont;
}

# Format admin menu
function add_menu(string $links): string {
    if ($links) {
        $links = explode('||', $links);
        $cont = '<nav class="sl_menu"><ul><li><span class="sl_but_red">'._EDITOR.'</span><ul>';
        foreach ($links as $val) if ($val != '') $cont .= '<li>'.$val.'</li>';
        $cont .= '</ul></li></ul></nav>';
        return $cont;
    }
    return '';
}

# Format title tips
function title_tip(mixed $data): string {
    $data = is_array($data) ? implode('<br>', $data) : $data;
    $tip = '<nav class="sl_tip"><div>'.$data.'</div></nav>';
    return $tip;
}

# Admin status
function ad_status(mixed $link, mixed $id, string $typ = '', string $text = ''): string {
    if ($typ) {
        $cont = ($id) ? '<span title="'._PROLD.'" class="sl_n_act"></span>' : '<span title="'._PROUTNEW.'" class="sl_n_deact"></span>';
    } elseif ($link) {
        $deact = ($text) ? _DEACTIVATE.': '.$text : _DEACTIVATE;
        $act = ($text) ? _ACTIVATE.': '.$text : _ACTIVATE;
        $cont = ($id == 1) ? '<a href="'.$link.'" title="'.$deact.'">'.$deact.'</a>' : '<a href="'.$link.'" title="'.$act.'">'.$act.'</a>';
    } else {
        $cont = ($id == 1) ? '<span title="'._ACT.'" class="sl_n_act"></span>' : '<span title="'._DEACT.'" class="sl_n_deact"></span>';
    }
    return $cont;
}

# Add mailto
function mailto(string $mail): string {
 global $conf;
    return '<a href="mailto:'.$mail.'?subject='.$conf['sitename'].'" target="_blank">'.$mail.'</a>';
}

# Add save button
function ad_save(string $name = '', string $val = '', string $op = '', string $noPreview = ''): string {
    $cont = '<select name="posttype" class="sl_field">';
    if (!$noPreview) $cont .= '<option value="preview">'._PREVIEW.'</option>';
    $cont .= '<option value="save">'._SEND.'</option>';
    $cont .= ($val) ? '<option value="delete">'._DELETE.'</option></select>' : '</select>';
    $cont .= ($name && $val) ? '<input type="hidden" name="'.$name.'" value="'.$val.'">' : '';
    $cont .= '<input type="hidden" name="op" value="'.$op.'">'
    .' <input type="submit" value="'._OK.'" class="sl_but_blue">';
    return $cont;
}

# Find img
function img_find(string $img): string {
    static $base;
    if (!$base) $base = 'templates/'.getTheme().'/images/';
    return $base.$img;
}

# Format select RSS
function rss_select(): string {
    global $conf;
    $fieldc = explode('||', $conf['rss']['rss']);
    $url = getVar('post', 'url', 'url', '');
    $cont = '';
    foreach ($fieldc as $val) {
        if ($val != '') {
            preg_match("#(.*)\|(.*)\|(.*)#i", $val, $out);
            if ($out[1] != '0' && $out[2] != '0') {
                $sel = ($url == $out[2]) ? ' selected' : '';
                $link = (!preg_match("#http\:\/\/#i", $out[2])) ? $conf['homeurl'].'/'.$out[2] : $out[2];
                $cont .= '<option value="'.$link.'"'.$sel.'>'.$out[1].'</option>';
            }
        }
    }
    return $cont;
}

# Read RSS
function rss_read(mixed $url, mixed $id): string {
    global $conf;
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
                    $temp = $conf['rss']['temp'];
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
                $cont = ($id) ? $cont : '<h2>'._RSS_FROM.': <a href="'.htmlspecialchars($url).'" target="_blank" title="'._RSS_FROM.': '.$title.'">'.$title.'</a></h2>'.$cont;
            } else {
                $cont = ($id) ? '' : setTemplateWarning('warn', ['text' => _RSS_PROBLEM, 'url' => '', 'time' => 0, 'id' => 'warn']);
            }
        } else {
            $cont = ($id) ? '' : setTemplateWarning('warn', ['text' => _RSS_PROBLEM, 'url' => '', 'time' => 0, 'id' => 'warn']);
        }
        return $cont;
    }
    return '';
}

# Load RSS
function rss_load(mixed $bid): void {
 global $db;
    $bid = intval($bid);
    list($title, $content, $url, $refresh, $otime) = $db->getSqlRow($db->getSqlQuery('SELECT title, content, url, refresh, time FROM '.PREFIX_DB.'_blocks WHERE id = :bid', ['bid' => $bid]));
    $past = time() - $refresh;
    if ($otime < $past) {
        $btime = time();
        $content = rss_read($url, 1);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET content = :content, time = :time WHERE id = :bid', ['content' => $content, 'time' => $btime, 'bid' => $bid]);
    }
    echo setTemplateBlock('', ['{%title%}' => $title, '{%content%}' => $content]);
}

# Preview
function preview(string $title = '', string $text1 = '', string $text2 = '', string $text3 = '', string $mod = ''): string {
    $fields  = $title ? '<b>'.$title.'</b>' : '';
    $fields1 = $text1 ? (($fields) ? '<br><br>'.filterReplaceText(filterMarkdown($text1, $mod, false), $mod) : filterReplaceText(filterMarkdown($text1, $mod, false), $mod)) : '';
    $fields2 = $text2 ? '<br><br>'.filterReplaceText(filterMarkdown($text2, $mod, false), $mod) : '';
    $fields3 = $text3 ? '<br><br>'.fields_out(filterReplaceText(filterMarkdown($text3, $mod, false), $mod), $mod) : '';
    return setTemplateBasic('preview', ['{%title%}' => _PREVIEW, '{%fields%}' => $fields, '{%fields1%}' => $fields1, '{%fields2%}' => $fields2, '{%fields3%}' => $fields3]);
}

# Fields in
function fields_in(mixed $fieldb, string $mod): string {
 global $conf;
    $mod = strtolower($mod);
    $style = (defined('ADMIN_FILE')) ? 'sl_field sl_form' : 'sl_field '.$conf['style'];
    $fieldc = $conf['fields'][$mod];
    $field = getVar('post', 'field', 'raw', '');
    if ($field !== '') {
        $fieldb = filterFields($field);
    }
    $fieldb = explode('|', $fieldb ?? '');
    $fieldc = explode('||', $fieldc);
    $i = 0;
    $fields = '';
    foreach ($fieldc as $val) {
        if ($val != '') {
            preg_match("#(.*)\|(.*)\|(.*)\|(.*)#i", $val, $out);
            if ($out[1] != '0') {
                $fieldin = (!empty($fieldb[$i])) ? $fieldb[$i] : $out[2];
                $requir = ($out[4] == 1) ? ' required' : '';
                if ($out[3] == 1) {
                    $dvalue = ($fieldin) ? getConst($fieldin) : '';
                    $field = '<input type="text" name="field[]" value="'.$dvalue.'" class="'.$style.'" placeholder="'.$dvalue.'"'.$requir.'>';
                } elseif ($out[3] == 2) {
                    $field = '<textarea name="field[]" cols="15" rows="5" class="'.$style.'"'.$requir.'>'.$fieldin.'</textarea>';
                } elseif ($out[3] == 3) {
                    $field = '<select name="field[]" class="'.$style.'"'.$requir.'>';
                    $field .= '<option value="">'._NO.'</option>';
                    $fieldcs = explode(',', $out[2]);
                    foreach ($fieldcs as $val) {
                        if ($val != '') {
                            $sel = ($val == $fieldin) ? ' selected' : '';
                            $field .= '<option value="'.$val.'"'.$sel.'>'.$val."</option>\n";
                        }
                    }
                    $field .= '</select>';
                } elseif ($out[3] == 4) {
                    $field = datetime(1, 'field[]', $fieldin, 16, $conf['style']);
                } elseif ($out[3] == 5) {
                    $field = datetime(2, 'field[]', $fieldin, 10, $conf['style']);
                }
                $fields .= '<tr><td>'.getConst($out[1]).':</td><td>'.$field.'</td></tr>';
            }
        }
        $i++;
    }
    return $fields;
}

# Fields out
function fields_out(mixed $fieldb, string $mod): string {
    global $conf;
    $mod = strtolower($mod);
    if ($fieldb && $mod) {
        $fieldc = $conf['fields'][$mod];
        $fieldb = explode('|', $fieldb);
        $fieldc = explode('||', $fieldc);
        $i = 0;
        $fields = '';
        foreach ($fieldc as $val) {
            if ($val != '' && !empty($fieldb[$i])) {
                preg_match("#(.*)\|(.*)\|(.*)\|(.*)#i", $val, $out);
                $fields .= getConst($out[1]).': '.$fieldb[$i].'<br>';
            }
            $i++;
        }
        return $fields;
    }
    return '';
}

# Format domain
function domain(string $url, string $str = ''): string {
    $massiv = explode(',', $url);
    $str = intval($str);
    foreach ($massiv as $val) $dom[] = '<a href="'.$val.'" target="_blank" title="'._DOWNLLINK.'">'.(($str) ? cutstr(preg_replace("/http\:\/\/|www./", '', $val), $str) : preg_replace("/http\:\/\/|www./", '', $val)).'</a>';
    return implode(', ', $dom);
}

# Check bot
function is_bot(): int|string {
 global $conf;
    $bots = explode(',', $conf['bots']);
    for ($i = 0; $i < count($bots); $i++) {
        list($uagent, $bname) = explode('=', $bots[$i]);
        if (preg_match('#'.$uagent.'#i', getAgent())) {
            $name = filterText(substr($bname, 0, 25), 1);
            break;
        } else {
            $name = 0;
        }
    }
    return $name;
}
# Check referer from bot
function from_bot(): int|string {
 global $conf;
    $bots = explode(',', $conf['fbots']);
    for ($i = 0; $i < count($bots); $i++) {
        if (preg_match('#'.$bots[$i].'#i', getReferer())) {
            $name = filterText(substr($bots[$i], 0, 25), 1);
            break;
        } else {
            $name = 0;
        }
    }
    return $name;
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

# Search user name
function get_user(): void {
 global $db;
    $let = analyze_name(getVar('get', 'term', 'text', ''));
    if ($let) {
        $result = $db->getSqlQuery('SELECT name FROM '.PREFIX_DB.'_users WHERE name LIKE :name ORDER BY name ASC', ['name' => $let.'%']);
        while(list($user_name) = $db->getSqlRow($result)) $name[]= '"'.$user_name.'"';
        echo '['.implode(', ', $name).']';
    }
}

# Autocomplete user name
function get_user_search(string $id, string $val, int $maxlength, string $extraClass = '', string $required = ''): string {
 global $conf;
    $class = $extraClass ? 'sl_field '.$extraClass : 'sl_field';
    $req   = $required ? ' required' : '';
    $cont = '<script>
    $(function() {
        $("#'.$id.'").autocomplete({
            source: "index.php?go=1&op=get_user",
            minLength: '.$conf['search']['slet'].'
        });
    });
    </script>'
    .'<input type="text" id="'.$id.'" name="'.$id.'" value="'.$val.'" maxlength="'.$maxlength.'" class="'.$class.'" placeholder="'._NICKNAME.'"'.$req.'>';
    return $cont;
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

# Check user
function check_user(): ?bool {
 global $user;
    if (is_user()) {
        $f = COUNTER_DIR.'/user.log';
        $un = filterText(substr($user[1], 0, 25), 1);
        if (file_exists($f)) {
            $fun = file_get_contents($f);
            $fun = explode(',', $fun);
            foreach ($fun as $val) {
                if ($val != '' && $val == $un) {
                    return false;
                    break;
                }
            }
        }
        $fp = fopen($f, 'ab');
        flock($fp, 2);
        fwrite($fp, $un.',');
        flock($fp, 3);
        fclose($fp);
        return true;
    } else {
        return false;
    }
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
function addFilescanTask(): array {
 global $conf;
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
    $skip = [
        ltrim(str_replace('\\', '/', str_replace(BASE_DIR, '', LOGS_DIR.'/dump.log')), '/'),
        ltrim(str_replace('\\', '/', str_replace(BASE_DIR, '', LOGS_DIR.'/dump_log.log')), '/')
    ];
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
        $mail = ($log) ? implode('<br>', $log) : _NO;
        $subj = $conf['sitename'].' - '._SECURITY;
        $mmsg = $conf['sitename'].' - '._SECURITY.'<br><br>'.$mail.'<br>'._DATE.': '.date(_TIMESTRING);
        addMail($conf['adminmail'], $conf['adminmail'], $subj, $mmsg, 0, 1);
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
function is_acess(string $ids): bool {
 global $db, $user, $conf;
    if ($ids) {
        $id = explode('|', $ids);
        if (is_moder(isset($conf['name']))) {
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

# Format categories select
function getcat(string $modul = '', int $id = 0, string $selectName = '', string $extraClass = '', string $emptyOption = '', string $noSelect = ''): string {
 global $db, $conf;
    $modul = filterVar($modul);
    $conf['name'] = $conf['name'] ?? $modul;
    $class  = $extraClass ? 'sl_field '.$extraClass : 'sl_field';
    if ($modul) {
        $where  = 'WHERE modul = :modul ORDER BY ordern';
        $params = ['modul' => $modul];
    } else {
        $where  = 'ORDER BY ordern';
        $params = [];
    }
    $result = $db->getSqlQuery('SELECT id, title, parent, pview FROM '.PREFIX_DB.'_categories '.$where, $params);
    if ($db->getSqlRowCount($result) > 0) {
        $content = (!$noSelect) ? '<select name="'.$selectName.'" title="'._CATEGORIES.'" class="'.$class.'">' : '';
        while (list($cid, $title, $parentid, $pview) = $db->getSqlRow($result)) if (is_acess($pview)) $massiv[$cid] = [getConst($title), $parentid];
        foreach ($massiv as $key => $val) {
            $cont[$key] = $val[0];
            $flag = $val[1];
            while ($flag != 0) {
                $cont[$key] = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'.$cont[$key];
                $flag = intval($massiv[$flag][1]);
            }
            $sel     = ($id == $key) ? ' selected' : '';
            $content .= '<option value="'.$key.'"'.$sel.'>'.$cont[$key].'</option>';
        }
        return (!$noSelect) ? $content.'</select>' : $content;
    } elseif ($emptyOption) {
        return '<select name="'.$selectName.'" title="'._CATEGORIES.'" class="'.$class.'">'.$emptyOption.'</select>';
    }
    return '';
}

# Format categories links
function catlink(string $mod = '', int $id = 0, string $sep = '', string $home = ''): string {
 global $db, $conf;
    $mod     = filterVar($mod);
    $sep     = $sep ? ' '.urldecode($sep).' ' : ' '.urldecode($conf['defis']).' ';
    $content = $home ? '<a href="index.php?name='.$conf['name'].'" title="'.$home.'">'.$home.'</a>'.$sep : '';
    if ($mod) {
        $where  = 'WHERE modul = :modul';
        $params = ['modul' => $mod];
    } else {
        $where  = '';
        $params = [];
    }
    $result = $db->getSqlQuery('SELECT id, title, parent FROM '.PREFIX_DB.'_categories '.$where, $params);
    if ($db->getSqlRowCount($result) > 0) {
        while (list($cid, $title, $parentid) = $db->getSqlRow($result)) $massiv[$cid] = [getConst($title), $parentid];
        foreach ($massiv as $key => $val) {
            $flag = $val[1];
            $cont[$key] = ($flag != 0) ? $val[0] : '<a href="index.php?name='.$conf['name'].'&amp;cat='.$key.'" title="'.$val[0].'">'.$val[0].'</a>';
            while ($flag != 0) {
                $cont[$key] = '<a href="index.php?name='.$conf['name'].'&amp;cat='.$flag.'" title="'.$massiv[$flag][0].'">'.$massiv[$flag][0].'</a>'.$sep.'<a href="index.php?name='.$conf['name'].'&amp;cat='.$key.'" title="'.$val[0].'">'.$cont[$key].'</a>';
                $flag = intval($massiv[$flag][1]);
            }
            if ($id == $key) $content .= $cont[$key];
        }
        return $content;
    }
    return '';
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

# Length end filter
function cutstr(mixed $strip, int $size, string $type = ''): string {
    $strip = (string)$strip;
    $size = (int)$size;
    if (!$type) {
        $end = '&hellip;';
    } elseif ($type == '1') {
        $end = '.';
    } elseif ($type == '2') {
        $end = '';
    }
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
 global $conf;
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
        $replace = explode("\n", str_replace(["\r\n", "\r"], "\n", $replace));
        $count = 1;
        $format = '';
        foreach ($replace as $code) {
            $bgcolor = ($count % 2) ? 'background-color: #fafafa;' : 'background-color: #fff;';
            $format .= '<tr style="'.$bgcolor.'"><td style="vertical-align: top;">'.$count.'</td>';
            $count++;
            if (preg_match("#<\?(php)?[^[:graph:]]#", $code)) {
                $format .= '<td style="width: 100%;">'.highlight_string($code, true).'</td></tr>';
            } else {
                $format .= '<td style="width: 100%;">'.preg_replace("#&lt;\?php&nbsp;#", '', highlight_string('<?php '.$code, true)).'</td></tr>';
            }
        }
        $replace = str_replace('&nbsp;&nbsp;', '&nbsp; ', $format);
        $format = '<table class="sl_table_form">'.$replace.'</table>';
    } elseif ($conf['syntax'] == 2) {
        if ($sname != $cname) {
            $scripts = '<script src="plugins/syntaxhighlighter/scripts/shCore.js"></script>';
            $scripts .= (file_exists('plugins/syntaxhighlighter/scripts/shBrush'.$cname.'.js')) ? '<script src="plugins/syntaxhighlighter/scripts/shBrush'.$cname.'.js"></script>' : '<script src="plugins/syntaxhighlighter/scripts/shBrushPhp.js"></script>';
            $scripts .= "<script>
                SyntaxHighlighter.config.clipboardSwf = 'plugins/syntaxhighlighter/scripts/clipboard.swf';
                SyntaxHighlighter.all();
            </script>";
            $sname = $cname;
        } else {
            $scripts = '';
        }
        $format = $scripts.'<pre class="brush: '.$ucname.';">'.$replace.'</pre>';
    }
    return setTemplateBasic('code', ['{%title%}' => $cname.' - '._CODE, '{%content%}' => $format]);
}

# Mail check
function checkemail(string $mail): array {
 global $stop;
    $mail = strtolower(filterText($mail, 1));
    if ((!$mail) || ($mail=='') || (!preg_match("#^[_\.a-z0-9-]+@([a-z0-9_-]+\.)+[a-z]{2,6}$#", $mail))) $stop[] = _ERROR1.'<br>'._ERROR2.' (<b>email@domain.com</b>)';
    if ((strlen($mail) >= 4) && (substr($mail, 0, 4) == 'www.')) $stop[] = _ERROR1.'<br>'._ERROR3.' (<b>www.</b>)';
    if (strrpos($mail, ' ') > 0) $stop[] = _ERROR1.'<br>'._ERROR4.'.';
    return $stop ?? [];
}

# Format add block
function addblocks(string $str): string {
 global $blocks, $blocks_c, $home, $showbanners, $foot, $db, $conf, $foot;
    preg_match_all('#{%BLOCKS([^%]+)%}#iUs', $str, $blk);
    $ci = count($blk[1]);
    for ($i = 0; $i < $ci; $i++) {
        $blk[0][$i] = '#'.$blk[0][$i].'#';
        $telo = trim($blk[1][$i]);
        $pos = strtolower($telo[0]);
        switch($pos) {
            case 'l':
            if ($blocks == '' || $blocks == '0'|| $blocks == '1') {
                ob_start();
                getBlocks('l');
                $blk[1][$i] = ob_get_clean();
            } else {
                $blk[1][$i] = '';
            }
            break;
            case 'r':
            if ($blocks == '' || $blocks == '0'|| $blocks == '2') {
                ob_start();
                getBlocks('r');
                $blk[1][$i] = ob_get_clean();
            } else {
                $blk[1][$i] = '';
            }
            break;
            case 'c':
            if ($blocks_c == '' || $blocks_c == '0' || $blocks_c == '1') {
                ob_start();
                getBlocks('c');
                $blk[1][$i] = ob_get_clean();
            } else {
                $blk[1][$i] = '';
            }
            break;
            case 'd':
            if ($blocks_c == '' || $blocks_c == '0'|| $blocks_c == '2') {
                ob_start();
                getBlocks('d');
                $blk[1][$i] = ob_get_clean();
            } else {
                $blk[1][$i] = '';
            }
            break;
            case 'b':
            getBlocks('b');
            $blk[1][$i] = $showbanners;
            break;
            case 'f':
            getBlocks('f');
            $blk[1][$i] = $foot;
            break;
            case 'm':
            $blk[1][$i] = ($home == 1) ? setMessageShow() : '';
            break;
            case 't':
            $blk[1][$i] = ($conf['db_t'] == '1') ? getTimeLoads() : '';
            break;
            case 'v':
            $cvar = explode(',', $conf['variables']);
            $blk[1][$i] = (!$cvar[0] && ($conf['var_view'] || (isAdmin() && !$conf['var_view']))) ? '<div>'.getVariables().'</div>' : '';
            break;
            default:
            $telo = explode(',', $telo);
            ob_start();
            getBlocks($telo[0], $telo[1]);
            $blk[1][$i] = ob_get_clean();
            break;
        }
    }
    return preg_replace($blk[0], $blk[1], $str);
}

# Format block
function render_blocks(string $side, string $bfile, string $blocktitle, string $content, mixed $bid, string $url): string {
 global $showbanners, $foot;
    if ($url == '') {
        $blocktitle = getConst($blocktitle);
        if ($bfile != '') {
            if (file_exists('blocks/'.$bfile)) {
                include('blocks/'.$bfile);
            } else {
                $content = '<div class="sl_center">'._BLOCKPROBLEM.'</div>';
            }
        }
        if (!isset($content) || empty($content)) $content = '<div class="sl_center">'._BLOCKPROBLEM2.'</div>';
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
            return setTemplateBlock('', ['{%title%}' => $blocktitle, '{%content%}' => $content]);
            break;
            default:
            echo setTemplateBlock('', ['{%title%}' => $blocktitle, '{%content%}' => $content]);
            break;
        }
    } else {
        rss_load($bid);
    }
    return '';
}

# Format rating
function rating(): void {
 global $db, $conf, $user;
    $id   = getVar('get', 'id',   'num',  0);
    $typ  = filterVar(getVar('get', 'typ',  'text', ''));
    $mod  = filterVar(getVar('get', 'mod',  'text', ''));
    $rate = min(5, getVar('get', 'rate', 'num', 0));
    $stl  = getVar('get', 'stl',  'num',  0);
    $con = explode('|', $conf['ratings'][strtolower($mod)]);
    if ($id && $mod) {
        $query = '';
        if ($mod == 'account') {
            $query = 'SELECT votes, tvotes FROM '.PREFIX_DB.'_users WHERE id = :id';
        } elseif ($mod == 'faq') {
            $query = 'SELECT ratings, score FROM '.PREFIX_DB.'_faq WHERE id = :id';
        } elseif ($mod == 'files') {
            $query = 'SELECT votes, tvotes FROM '.PREFIX_DB.'_files WHERE id = :id';
        } elseif ($mod == 'forum') {
            $query = 'SELECT ratings, score FROM '.PREFIX_DB.'_forum WHERE id = :id';
        } elseif ($mod == 'help') {
            $query = 'SELECT ratings, score FROM '.PREFIX_DB.'_help WHERE id = :id';
        } elseif ($mod == 'jokes') {
            $query = 'SELECT ratetot, rating FROM '.PREFIX_DB.'_jokes WHERE id = :id';
        } elseif ($mod == 'links') {
            $query = 'SELECT votes, tvotes FROM '.PREFIX_DB.'_links WHERE id = :id';
        } elseif ($mod == 'media') {
            $query = 'SELECT votes, tvotes FROM '.PREFIX_DB.'_media WHERE id = :id';
        } elseif ($mod == 'news') {
            $query = 'SELECT ratings, score FROM '.PREFIX_DB.'_news WHERE id = :id';
        } elseif ($mod == 'pages') {
            $query = 'SELECT ratings, score FROM '.PREFIX_DB.'_pages WHERE id = :id';
        } elseif ($mod == 'shop') {
            $query = 'SELECT votes, tvotes FROM '.PREFIX_DB.'_products WHERE id = :id';
        }
        if ($query == '') {
            return;
        }
        $ip = getIp();
        $past = time() - intval($con[0]);
        $cmod = substr($mod, 0, 2).'-'.$id;
        $cookies = isset($_COOKIE[$cmod]) ? intval($_COOKIE[$cmod]) : '';
        $uid = (is_user()) ? intval(substr($user[0], 0, 11)) : 0;
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_rating WHERE time < :past AND modul = :mod', ['past' => $past, 'mod' => $mod]);
        list($num) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB."_rating WHERE (mid = :id AND modul = :mod AND ip = :ip) OR (mid = :id2 AND modul = :mod2 AND uid = :uid AND uid != '0')", ['id' => $id, 'mod' => $mod, 'ip' => $ip, 'id2' => $id, 'mod2' => $mod, 'uid' => $uid]));
        if ($cookies == $id || $num > 0) {
            list($votes, $totalvotes) = $db->getSqlRow($db->getSqlQuery($query, ['id' => $id]));
            echo ajax_rating(2, '', '', $votes, $totalvotes, '', $stl);
        } elseif (!$cookies && !$num && !$rate) {
            list($votes, $totalvotes) = $db->getSqlRow($db->getSqlQuery($query, ['id' => $id]));
            if (intval($votes)) {
                $votnum = $votes;
                $votes = $votes;
            } else {
                $votnum = 0;
                $votes = 1;
            }
            $width = number_format($totalvotes / $votes, 2) * 20;
            $result = substr($totalvotes / $votes, 0, 4);
            if (intval($votes) && intval($totalvotes)) {
                $title = _RATING.': '.$result.'/'.$votes.' '._AVERAGESCORE.': '.$result;
                $nrate = 'sl_rate-num sl_rate-is';
            } else {
                $title = _RATING.': 0/0 '._AVERAGESCORE.': 0';
                $nrate = 'sl_rate-num';
            }
            if ($stl == 1) {
                echo '<span class="sl_none">'.$result."</span>
                <div class=\"sl_rate-like\">
                    <p OnClick=\"AjaxLoad('GET', '1', '".$id.$typ."', 'go=1&amp;op=rating&amp;id=".$id.'&amp;typ='.$typ.'&amp;mod='.$mod."&amp;rate=1&amp;stl=1', ''); return false;\" title=\""._RATE1."\" class=\"sl_rate-minus sl_out\">
                    <p OnClick=\"AjaxLoad('GET', '1', '".$id.$typ."', 'go=1&amp;op=rating&amp;id=".$id.'&amp;typ='.$typ.'&amp;mod='.$mod."&amp;rate=5&amp;stl=1', ''); return false;\" title=\""._RATE5.'" class="sl_rate-plus sl_out">
                </div><span class="'.$nrate.'" title="'.$title.'">'.$result.'</span>';
            } else {
                echo '<ul class="sl_urating">
                    <li class="sl_crating" style="width:'.$width."%;\"></li>
                    <li><div OnMouseOver=\"this.className='sl_over1';\" OnMouseOut=\"this.className='sl_out1';\" OnClick=\"AjaxLoad('GET', '1', '".$id.$typ."', 'go=1&amp;op=rating&amp;id=".$id.'&amp;typ='.$typ.'&amp;mod='.$mod."&amp;rate=1', ''); return false;\" title=\""._RATE1."\" class=\"sl_out1\"></div></li>
                    <li><div OnMouseOver=\"this.className='sl_over2';\" OnMouseOut=\"this.className='sl_out2';\" OnClick=\"AjaxLoad('GET', '1', '".$id.$typ."', 'go=1&amp;op=rating&amp;id=".$id.'&amp;typ='.$typ.'&amp;mod='.$mod."&amp;rate=2', ''); return false;\" title=\""._RATE2."\" class=\"sl_out2\"></div></li>
                    <li><div OnMouseOver=\"this.className='sl_over3';\" OnMouseOut=\"this.className='sl_out3';\" OnClick=\"AjaxLoad('GET', '1', '".$id.$typ."', 'go=1&amp;op=rating&amp;id=".$id.'&amp;typ='.$typ.'&amp;mod='.$mod."&amp;rate=3', ''); return false;\" title=\""._RATE3."\" class=\"sl_out3\"></div></li>
                    <li><div OnMouseOver=\"this.className='sl_over4';\" OnMouseOut=\"this.className='sl_out4';\" OnClick=\"AjaxLoad('GET', '1', '".$id.$typ."', 'go=1&amp;op=rating&amp;id=".$id.'&amp;typ='.$typ.'&amp;mod='.$mod."&amp;rate=4', ''); return false;\" title=\""._RATE4."\" class=\"sl_out4\"></div></li>
                    <li><div OnMouseOver=\"this.className='sl_over5';\" OnMouseOut=\"this.className='sl_out5';\" OnClick=\"AjaxLoad('GET', '1', '".$id.$typ."', 'go=1&amp;op=rating&amp;id=".$id.'&amp;typ='.$typ.'&amp;mod='.$mod."&amp;rate=5', ''); return false;\" title=\""._RATE5.'" class="sl_out5"></div></li>
                </ul><span class="'.$nrate.'" title="'._VOTES.'">'.$votnum.'</span>';
            }
        } elseif (!$cookies && !$num && $rate) {
            setcookie(substr($mod, 0, 2).'-'.$id, $id, time() + intval($con[0]));
            $new = time();
            $inserted = $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_rating (mid, modul, time, uid, ip) VALUES (:mid, :modul, :time, :uid, :ip)', ['mid' => $id, 'modul' => $mod, 'time' => $new, 'uid' => $uid, 'ip' => $ip]);
            if ($inserted) {
                if ($mod == 'account' || $mod == 'members') {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET votes = votes + 1, tvotes = tvotes + :rate WHERE id = :id', ['rate' => $rate, 'id' => $id]);
                    update_points(2);
                } elseif ($mod == 'faq') {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_faq SET score = score + :rate, ratings = ratings + 1 WHERE id = :id', ['rate' => $rate, 'id' => $id]);
                    update_points(8);
                } elseif ($mod == 'files') {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_files SET votes = votes + 1, tvotes = tvotes + :rate WHERE id = :id', ['rate' => $rate, 'id' => $id]);
                    update_points(12);
                } elseif ($mod == 'forum') {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_forum SET score = score + :rate, ratings = ratings + 1 WHERE id = :id', ['rate' => $rate, 'id' => $id]);
                    update_points(15);
                } elseif ($mod == 'help') {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_help SET score = score + :rate, ratings = ratings + 1 WHERE id = :id', ['rate' => $rate, 'id' => $id]);
                } elseif ($mod == 'gallery') {
                    #$db->getSqlQuery("UPDATE ".PREFIX_DB."_gallery SET votes=votes+1, totalvotes=totalvotes+".$rate." WHERE lid = '".$id."'");
                    update_points(18);
                } elseif ($mod == 'jokes') {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_jokes SET rating = rating + :rate, ratetot = ratetot + 1 WHERE id = :id', ['rate' => $rate, 'id' => $id]);
                    update_points(20);
                } elseif ($mod == 'links') {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_links SET votes = votes + 1, tvotes = tvotes + :rate WHERE id = :id', ['rate' => $rate, 'id' => $id]);
                    update_points(24);
                } elseif ($mod == 'media') {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_media SET votes = votes + 1, tvotes = tvotes + :rate WHERE id = :id', ['rate' => $rate, 'id' => $id]);
                    update_points(27);
                } elseif ($mod == 'multimedia') {
                    #$db->getSqlQuery("UPDATE ".PREFIX_DB."_multimedia SET votes=votes+1, totalvotes=totalvotes+".$rate." WHERE id = '".$id."'");
                    update_points(30);
                } elseif ($mod == 'news') {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET score = score + :rate, ratings = ratings + 1 WHERE id = :id', ['rate' => $rate, 'id' => $id]);
                    update_points(33);
                } elseif ($mod == 'pages') {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_pages SET score = score + :rate, ratings = ratings + 1 WHERE id = :id', ['rate' => $rate, 'id' => $id]);
                    update_points(37);
                } elseif ($mod == 'shop') {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET votes = votes + 1, tvotes = tvotes + :rate WHERE id = :id', ['rate' => $rate, 'id' => $id]);
                    update_points(41);
                }
            }
            list($votes, $totalvotes) = $db->getSqlRow($db->getSqlQuery($query, ['id' => $id]));
            echo ajax_rating(2, '', '', $votes, $totalvotes, '', $stl);
        }
    }
}

# Format BB Code and Smilies
function textarea(string $id, string $name, string $var, string $mod, int $rows, string $placeholder = '', string $required = ''): string {
 global $admin, $op, $user, $conf;
    $placeholder = $placeholder ? ' placeholder="'.$placeholder.'"' : '';
    $required    = $required ? ' required' : '';
    $stloc = substr(_LOCALE, 0, 2);
    $desc = $var ?: filterHtml(getVar('post', $name, 'raw', ''));
    $con = explode('|', (string)($conf['uploads'][strtolower($mod)] ?? ''));
    $style = (defined('ADMIN_FILE')) ? ' sl_form' : ' '.$conf['style'];
    $editor = (isset($admin[3])) ? intval(substr($admin[3], 0, 1)) : 0;
    if ((defined('ADMIN_FILE') && $editor == 1) || (!defined('ADMIN_FILE') && $conf['redaktor'] == 1)) {
        $code = ($id == 1) ? '<script src="plugins/system/insert-code.js"></script>' : '';
        $code .= "<table class=\"sl_table_form\"><tr><td><div class=\"sl_bb-editor\">
        <div class=\"sl_bb-panel\">
            <div class=\"sl_pos_right\">
                <span OnClick=\"RowsTextarea(1, '".$id."')\" class=\"sl_bb_plus\" title=\""._EPLUS."\"></span>
                <span OnClick=\"RowsTextarea(0, '".$id."')\" class=\"sl_bb_minus\" title=\""._EMINUS."\"></span>
            </div>
            <span OnClick=\"InsertCode('b', '', '', '', '".$id."')\" class=\"sl_bb_b\" title=\""._EBOLD."\"></span>
            <span OnClick=\"InsertCode('i', '', '', '', '".$id."')\" class=\"sl_bb_i\" title=\""._EITALIC."\"></span>
            <span OnClick=\"InsertCode('u', '', '', '', '".$id."')\" class=\"sl_bb_u\" title=\""._EUNDERLINE."\"></span>
            <span OnClick=\"InsertCode('s', '', '', '', '".$id."')\" class=\"sl_bb_s\" title=\""._ESTRIKET."\"></span>
            <span OnClick=\"InsertCode('li', '', '', '', '".$id."')\" class=\"sl_bb_li\" title=\""._ELI."\"></span>
            <span OnClick=\"InsertCode('hr', '', '', '', '".$id."')\" class=\"sl_bb_hr\" title=\""._EHR."\"></span>
            <div class=\"sl_bb_sep\"></div>
            <span OnClick=\"InsertCode('left', '', '', '', '".$id."')\" class=\"sl_bb_left\" title=\""._ELEFT."\"></span>
            <span OnClick=\"InsertCode('center', '', '', '', '".$id."')\" class=\"sl_bb_center\" title=\""._ECENTER."\"></span>
            <span OnClick=\"InsertCode('right', '', '', '', '".$id."')\" class=\"sl_bb_right\" title=\""._ERIGHT."\"></span>
            <span OnClick=\"InsertCode('justify', '', '', '', '".$id."')\" class=\"sl_bb_justify\" title=\""._EYUSTIFY."\"></span>
            <div class=\"sl_bb_sep\"></div>
            <span OnClick=\"InsertCode('hide', '', '', '', '".$id."')\" class=\"sl_bb_hide\" title=\""._HIDE."\"></span>
            <span OnClick=\"InsertCode('url', '"._JINFO."', '"._JTYPE."', '"._JERROR."', '".$id."')\" class=\"sl_bb_link\" title=\""._EURL."\"></span>
            <span OnClick=\"InsertCode('mail', '"._JINFO."', '"._JTYPE."', '"._JERROR."', '".$id."')\" class=\"sl_bb_mail\" title=\""._EEMAIL."\"></span>
            <span OnClick=\"InsertCode('img', '"._JINFO."', '"._JTYPE."', '"._JERROR."', '".$id."')\" class=\"sl_bb_img\" title=\""._EIMG."\"></span>
            <!-- <span OnClick=\"InsertCode('media', '"._EMEDIA."', '', '', '".$id."')\" class=\"sl_bb_media\" title=\""._EMEDIA."\"></span> -->
            <span OnMouseOver=\"CopyText();\" OnClick=\"InsertCode('quote', '"._JQUOTE."', '', '', '".$id."')\" class=\"sl_bb_quote\" title=\""._EQUOTE."\"></span>
            <!-- <span OnClick=\"InsertCode('spoiler', '"._ESPOIL."', '', '', '".$id."')\" class=\"sl_bb_spoiler\" title=\""._ESPOIL.'"></span> -->
        </div>';
        $code .= '<textarea id="'.$id.'" name="'.$name.'" cols="65" rows="'.$rows."\" OnKeyPress=\"TransliteFeld(this, event)\" OnSelect=\"FieldName(this, '".$id."')\" OnClick=\"FieldName(this, '".$id."')\" OnKeyUp=\"FieldName(this, '".$id."')\" class=\"sl_field".$style.'"'.$placeholder.$required.'>'.replace_break($desc)."</textarea>
        <div class=\"sl_bb-panel\">
            <div class=\"sl_pos_right\">
                <div class=\"sl_drop\">
                    <span OnClick=\"HideShow('i-form-".$id."', 'blind', 'up', 500);\" class=\"sl_bb_info\" title=\""._INFO.'"></span>
                    <div id="i-form-'.$id.'" class="sl_drop-form">'._INFO_BB.' '.$conf['version'].'</div>
                </div>
            </div>';
            if ((defined('ADMIN_FILE') && ($con[10] ?? 0) == 1) || (is_user() && ($con[10] ?? 0) == 1) || (!is_user() && ($con[11] ?? 0) == 1)) $code .= "<span OnClick=\"HideShow('af-form-".$id."', 'slide', 'up', 500); AjaxLoad('GET', '1', 'f".$id."', 'go=1&amp;op=show_files&amp;id=".$id.'&amp;dir='.$mod."', ''); return false;\" class=\"sl_bb_file\" title=\""._EUPLOAD.'"></span>';
            $code .= "<div class=\"sl_drop\">
                <span OnClick=\"HideShow('s-form-".$id."', 'blind', 'up', 500);\" class=\"sl_bb_smile\" title=\""._ESMILIE.'"></span>
                <div id="s-form-'.$id.'" class="sl_drop-form">';
                $i = 1;
                $dir = opendir(img_find('smilies'));
                while (false !== ($entry = readdir($dir))) {
                    if (preg_match("#(\.gif)$#i", $entry) && $entry != '.' && $entry != '..') {
                        $i = ($i < 10) ? '0'.$i : $i;
                        $code .= ' <img src="'.img_find('smilies/'.$i.'.gif')."\" OnClick=\"InsertCode('smilies', ' *".$i."', '', '', '".$id."');\" style=\"cursor: pointer; margin: 3px 2px 0px 0px;\" alt=\""._SMILIE.' - '.$i.'" title="'._SMILIE.' - '.$i.'">';
                        $i++;
                    }
                }
                closedir($dir);
            $code .= '</div></div>';
        if ($stloc == 'ru') {
            $code .= "<div class=\"sl_drop\"><span OnClick=\"HideShow('l-form-".$id."', 'blind', 'up', 500); changelanguage();\" class=\"sl_bb_translate\" title=\""._EAUTOTR.'"></span>
            <div id="l-form-'.$id."\" class=\"sl_drop-form\">
                <table class=\"sl_bb_trans\"><tr>
                <td>ÃÂ</td><td>Ãâ€˜</td><td>Ãâ€™</td><td>Ãâ€œ</td><td>Ãâ€</td><td>Ãâ€¢</td><td>ÃÂ</td><td>Ãâ€“</td><td>Ãâ€”</td><td>ÃËœ</td><td>Ãâ„¢</td>
                <td>ÃÅ¡</td><td>Ãâ€º</td><td>ÃÅ“</td><td>ÃÂ</td><td>ÃÅ¾</td><td>ÃÅ¸</td><td>ÃÂ </td><td>ÃÂ¡</td><td>ÃÂ¢</td><td>ÃÂ£</td><td>ÃÂ¤</td>
                <td>ÃÂ¥</td><td>ÃÂ¦</td><td>ÃÂ§</td><td>ÃÂ¨</td><td>ÃÂ©</td><td>ÃÂª</td><td>ÃÂ«</td><td>ÃÂ¬</td><td>ÃÂ­</td><td>ÃÂ®</td><td>ÃÂ¯</td>
                </tr><tr>
                <td>A</td><td>B</td><td>V</td><td>G</td><td>D</td><td>E</td><td>JO</td><td>ZH</td><td>Z</td><td>I</td><td>J</td>
                <td>K</td><td>L</td><td>M</td><td>N</td><td>O</td><td>P</td><td>R</td><td>S</td><td>T</td><td>U</td><td>F</td>
                <td>X</td><td>C</td><td>CH</td><td>SH</td><td>W</td><td>'</td><td>Y</td><td>#</td><td>JE</td><td>JU</td><td>JA</td>
                </tr></table>
            </div></div>
            <span OnClick=\"translateAlltoCyrillic()\" class=\"sl_bb_translit\" title=\""._ERUS.'"></span>
            <span OnClick="translateAlltoLatin()" class="sl_bb_trans" title="'._ELAT.'"></span>';
        }
        $fonts = '<option value="">'._FONT.'</option>';
        $font = ['Arial', 'Courier', 'Mistral', 'Impact', 'Sans Serif', 'Tahoma', 'Helvetica', 'Verdana'];
        foreach ($font as $val) if ($val != '') $fonts .= '<option style="font-family: '.$val.';" value="'.$val.'">'.$val.'</option>';

        $colors = '<option value="">'._ECOLOR.'</option>';
        $color = ['black', 'gray', 'silver', 'white', 'maroon', 'red', 'orangered', 'orange', 'yellow', 'purple', 'fuchsia', 'violet', 'darkgreen', 'green', 'lime', 'navy', 'blue', 'teal', 'aqua'];
        foreach ($color as $val) if ($val != '') $colors .= '<option style="background: '.$val.';" value="'.$val.'">'.$val.'</option>';

        $fsizes = '<option value="">'._ESIZE.'</option>';
        $fsize = ['8', '10', '12', '14', '16', '18', '20', '22', '24', '26', '28', '30', '32'];
        foreach ($fsize as $val) if ($val != '') $fsizes .= '<option value="'.$val.'">'.$val.'</option>';

        $fcodes = '<option value="">'._CODE.'</option>';
        $fcode = ['Bash', 'Cpp', 'CSharp', 'Css', 'Delphi', 'Diff', 'Groovy', 'Java', 'JScript', 'Php', 'Plain', 'Python', 'Ruby', 'Scala', 'Sql', 'Vb', 'Xml'];
        foreach ($fcode as $val) if ($val != '') $fcodes .= '<option value="'.strtolower($val).'">'.$val.'</option>';

        $code .= "<div class=\"sl_drop\">
            <span OnClick=\"HideShow('t-form-".$id."', 'blind', 'up', 500);\" class=\"sl_bb_text\" title=\""._TEXT.'"></span>
            <div id="t-form-'.$id."\" class=\"sl_drop-form\">
                <ul>
                    <li><select name=\"family\" OnChange=\"InsertCode('family', this.options[this.selectedIndex].value, '', '', '".$id."'); this.selectedIndex=0;\" class=\"sl_field\" multiple>".$fonts."</select></li>
                    <li><select name=\"color\" OnChange=\"InsertCode('color', this.options[this.selectedIndex].value, '', '', '".$id."'); this.selectedIndex=0;\" class=\"sl_field\" multiple>".$colors."</select></li>
                    <li><select name=\"size\" OnChange=\"InsertCode('size', this.options[this.selectedIndex].value, '', '', '".$id."'); this.selectedIndex=0;\" class=\"sl_field\" multiple>".$fsizes."</select></li>
                </ul>
            </div>
        </div>
        <div class=\"sl_drop\">
            <span OnClick=\"HideShow('c-form-".$id."', 'blind', 'up', 500);\" class=\"sl_bb_code\" title=\""._CODE.'"></span>
            <div id="c-form-'.$id."\" class=\"sl_drop-form\"><ul><li><select name=\"code\" OnChange=\"InsertCode('code', this.options[this.selectedIndex].value, '', '', '".$id."'); this.selectedIndex=0;\" class=\"sl_field\" multiple>".$fcodes.'</select></li></ul></div>
        </div>';
        if (isAdmin()) {
            $code .= '<div class="sl_bb_sep"></div>'
            ."<span OnClick=\"InsertCode('usehtml', '', '', '', '".$id."')\" class=\"sl_bb_html\" title=\""._EUSEHTML.'"></span>'
            ."<span OnClick=\"InsertCode('usephp', '', '', '', '".$id."')\" class=\"sl_bb_php\" title=\""._EUSEPHP.'"></span>';
            $conf['name'] = (!empty($conf['name'])) ? $conf['name'] : '';
            if ($op == 'faq_add' || $op == 'news_add' || $op == 'page_add' || $conf['name'] == 'faq' || $conf['name'] == 'news' || $conf['name'] == 'page') $code .= "<span OnClick=\"InsertCode('pagebreak', '', '', '', '".$id."')\" class=\"sl_bb_break\" title=\""._EBREAK.'"></span>';
        }
        $code .= '</div>';
        if ((defined('ADMIN_FILE') && ($con[10] ?? 0) == 1) || (is_user() && ($con[10] ?? 0) == 1) || (!is_user() && ($con[11] ?? 0) == 1)) {
            $code .= '<div id="af-form-'.$id.'" class="sl_bbup-panel sl_none">';
            if ($id == 1) {
                $uinfo = '<div class="ico sl_info sl_left"><b>'._UPLOADINFO.'</b><br>'._FTYPE.': '.str_replace(',', ', ', $con[0]).'<br>'._FSIZEALL.': '.filterSize($con[1]).'<br>'._FSIZE.': '.filterSize($con[2]).'<br>'._AWIDTH.': '.$con[3].' px<br>'._AHEIGHT.': '.$con[4].' px<br>'._FILEUP.': '.$con[5].'<br>'.'</div>';
                $code .= "<script>
                $(document).ready(function(e) {
                    $('#msg').html('".$uinfo."');
                    $('#file_upload').on('change', function () {
                        var form_data = new FormData();
                        var ins = document.getElementById('file_upload').files.length;
                        for (var x = 0; x < ins; x++) {
                            form_data.append('file[]', document.getElementById('file_upload').files[x]);
                        }
                        form_data.append('token', '".getSiteToken('upload')."');
                        $.ajax({
                            url: 'index.php?go=4&mod=".$mod.'&userid='.intval($user[0] ?? 0)."',
                            type: 'POST',
                            dataType: 'text',
                            data: form_data,
                            cache: false,
                            contentType: false,
                            processData: false,
                            beforeSend: function() {
                                $('#msg').html('<div class=\"sl_loading\"></div><br>');
                            },
                            success: function (response) {
                                console.log('Success: ', response);
                                $('#msg').html(response);
                                AjaxLoad('GET', '1', 'f".$id."', 'go=1&op=show_files&id=".$id.'&dir='.$mod."', '');
                            },
                            error: function (response) {
                                console.log('Error: ', response);
                                $('#msg').html(response);
                                alert('File upload error!');
                            }
                        });
                    });
                });
                </script>
                <div id=\"msg\"></div>
                <div class=\"sl_pos_center\">
                <input type=\"file\" id=\"file_upload\" name=\"file[]\" multiple=\"multiple\" class=\"sl_field\">
                <input type=\"button\" value=\""._UPDATE."\" OnClick=\"AjaxLoad('GET', '1', 'f".$id."', 'go=1&amp;op=show_files&amp;id=".$id.'&amp;dir='.$mod."', ''); return false;\" class=\"sl_but_green\"></div>";
            } else {
                $code .= '<div class="sl_pos_center"><input type="button" value="'._UPDATE."\" OnClick=\"AjaxLoad('GET', '1', 'f".$id."', 'go=1&amp;op=show_files&amp;id=".$id.'&amp;dir='.$mod."', ''); return false;\" class=\"sl_but_green\"></div>";
            }
            $code .= '<div id="repf'.$id.'" style="margin: 5px;"></div></div>';
        }
        $code .= '</div></td></tr></table>';
    } elseif ((defined('ADMIN_FILE') && $editor == 2) || (!defined('ADMIN_FILE') && $conf['redaktor'] == 2)) {
        static $jscript;
        if (defined('ADMIN_FILE') && $editor == 2) {
            if (!isset($jscript)) {
                $code = '<script src="plugins/tinymce/tinymce.min.js"></script>
                <script>
                tinymce.init({
                    selector: "textarea",
                    theme: "modern",
                    plugins: [
                        "advlist autolink lists link image charmap print preview hr anchor pagebreak",
                        "searchreplace wordcount visualblocks visualchars code fullscreen",
                        "insertdatetime media nonbreaking save table contextmenu directionality",
                        "emoticons template paste textcolor responsivefilemanager"
                    ],
                    toolbar1: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image",
                    toolbar2: "responsivefilemanager print preview media | forecolor backcolor emoticons",
                    image_advtab: true,
                    templates: [
                        { title: "Test template 1", content: "Test 1" },
                        { title: "Test template 2", content: "Test 2" }
                    ],
                    language: "'.$stloc.'",
                    external_filemanager_path: "../plugins/filemanager/",
                    filemanager_title: "'._EUPLOAD.'" ,
                    external_plugins: { "filemanager" : "../filemanager/plugin.min.js" }
                });
                </script>';
                $jscript = 1;
            } else {
                $code = '';
            }
        } elseif (!defined('ADMIN_FILE') && $conf['redaktor'] == 2) {
            if (!isset($jscript)) {
                $code = '<script src="plugins/tinymce/tinymce.min.js"></script>
                <script>
                tinymce.init({
                    selector: "textarea",
                    plugins: [
                        "advlist autolink lists link image charmap print preview anchor",
                        "searchreplace visualblocks code fullscreen",
                        "insertdatetime media table contextmenu paste"
                    ],
                    toolbar: "insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image",
                    language: "'.$stloc.'"
                });
                </script>';
                $jscript = 1;
            } else {
                $code = '';
            }
        }
        $code .= '<textarea id="'.$id.'" name="'.$name.'" cols="65" rows="'.$rows.'" class="'.$style.'"'.$placeholder.'>'.$desc.'</textarea>';
    } elseif ((defined('ADMIN_FILE') && $editor == 3) || (!defined('ADMIN_FILE') && $conf['redaktor'] == 3)) {
        if (defined('ADMIN_FILE') && $editor == 3) {
            if (!isset($jscript)) {
                $code = '<script src="plugins/ckeditor/ckeditor.js"></script><script src="plugins/ckeditor/adapters/jquery.js"></script>';
                $jscript = 1;
            } else {
                $code = '';
            }
            $code .= "<script>
            $(document).ready( function() {
                $('textarea#".$id."').ckeditor({
                    language: '".$stloc."',
                    filebrowserBrowseUrl: '../plugins/filemanager/dialog.php?type=2&editor=ckeditor&fldr=',
                    filebrowserUploadUrl: '../plugins/filemanager/dialog.php?type=2&editor=ckeditor&fldr=',
                    filebrowserImageBrowseUrl: '../plugins/filemanager/dialog.php?type=1&editor=ckeditor&fldr='
                });
            });
            </script>";
        } elseif (!defined('ADMIN_FILE') && $conf['redaktor'] == 3) {
            if (!isset($jscript)) {
                $code = '<script src="plugins/ckeditor/ckeditor.js"></script><script src="plugins/ckeditor/adapters/jquery.js"></script>';
                $jscript = 1;
            } else {
                $code = '';
            }
            $code .= "<script>
            $(document).ready( function() {
                $('textarea#".$id."').ckeditor({
                    language: '".$stloc."'
                });
            });
            </script>";
        }
        $code .= '<textarea id="'.$id.'" name="'.$name.'" cols="65" rows="'.$rows.'" class="'.$style.'"'.$placeholder.'>'.$desc.'</textarea>';
    } elseif (defined('ADMIN_FILE') && $editor == 4) {
        if (!isset($jscript)) {
            $code = '<script src="plugins/codemirror/lib/codemirror.js"></script>
            <script src="plugins/codemirror/addon/edit/matchbrackets.js"></script>

            <script src="plugins/codemirror/addon/hint/show-hint.js"></script>
            <script src="plugins/codemirror/addon/hint/xml-hint.js"></script>
            <script src="plugins/codemirror/addon/hint/html-hint.js"></script>

            <script src="plugins/codemirror/mode/htmlmixed/htmlmixed.js"></script>
            <script src="plugins/codemirror/mode/xml/xml.js"></script>
            <script src="plugins/codemirror/mode/javascript/javascript.js"></script>
            <script src="plugins/codemirror/mode/css/css.js"></script>';
            $jscript = 1;
        } else {
            $code = '';
        }
        $code .= '<textarea id="'.$id.'" name="'.$name.'" class="'.$style.'"'.$placeholder.'>'.str_replace('&amp;', '&amp;amp;', $desc).'</textarea>
        <script>
        var editor = CodeMirror.fromTextArea(document.getElementById("'.$id.'"), {
            lineNumbers: true,
            matchBrackets: true,
            mode: "text/html",
            extraKeys: {"Ctrl": "autocomplete"},
            value: document.documentElement.innerHTML,
            indentUnit: 4,
            indentWithTabs: true
        });
        </script>';
    } else {
        $code = '<textarea id="'.$id.'" name="'.$name.'" cols="65" rows="'.$rows.'" class="'.$style.'"'.$placeholder.$required.'>'.str_replace('&amp;', '&amp;amp;', $desc).'</textarea>';
    }
    return $code;
}

# Format ajax edit
function textareae(mixed $obj, mixed $go, mixed $op, mixed $id, mixed $cid, mixed $typ, mixed $mod, mixed $text, int $rows): string {
 global $conf, $admin;
    $editor = (isset($admin[3])) ? intval(substr($admin[3], 0, 1)) : 0;
    $desc = ((defined('ADMIN_FILE') && $editor == 1) || (!defined('ADMIN_FILE') && $conf['redaktor'] == 1)) ? replace_break($text) : $text;
    $code = '<form name="textareae" id="form'.$obj.'" method="post">
    <textarea id="text" name="text" cols="65" rows="'.$rows.'" class="sl_earea">'.$desc."</textarea>
    <input type=\"submit\" OnClick=\"AjaxLoad('POST', '1', '".$obj."', 'go=".$go.'&amp;op='.$op.'&amp;id='.$id.'&amp;cid='.$cid.'&amp;typ='.$typ.'&amp;mod='.$mod."', { 'text':'"._CERROR1."' }); return false;\" value=\""._SAVE.'" title="'._SAVE."\" class=\"sl_but_green\">
    <input type=\"submit\" OnClick=\"AjaxLoad('GET', '1', '".$obj."', 'go=".$go.'&amp;op='.$op.'&amp;id='.$id.'&amp;cid='.$cid.'&amp;typ='.$typ.'&amp;mod='.$mod."', ''); return false;\" value=\""._BACK.'" title="'._BACK.'" class="sl_but_blue">
    </form>';
    return $code;
}

# Format code edit
function textarea_code(string $id, string $name, string $style, string $mode, string $text): string {
    static $jscript;
    if (!isset($jscript)) {
        $code = '<script src="plugins/codemirror/lib/codemirror.js"></script>
        <script src="plugins/codemirror/addon/edit/matchbrackets.js"></script>

        <script src="plugins/codemirror/addon/hint/show-hint.js"></script>
        <script src="plugins/codemirror/addon/hint/xml-hint.js"></script>
        <script src="plugins/codemirror/addon/hint/html-hint.js"></script>
        <script src="plugins/codemirror/addon/hint/css-hint.js"></script>
        <script src="plugins/codemirror/addon/hint/sql-hint.js"></script>

        <script src="plugins/codemirror/mode/htmlmixed/htmlmixed.js"></script>
        <script src="plugins/codemirror/mode/xml/xml.js"></script>
        <script src="plugins/codemirror/mode/javascript/javascript.js"></script>
        <script src="plugins/codemirror/mode/css/css.js"></script>
        <script src="plugins/codemirror/mode/clike/clike.js"></script>
        <script src="plugins/codemirror/mode/php/php.js"></script>
        <script src="plugins/codemirror/mode/sql/sql.js"></script>
        <script src="plugins/codemirror/mode/http/http.js"></script>';
        $jscript = 1;
    } else {
        $code = '';
    }
    $style = ($style) ? ' '.$style : '';
    $code .= '<textarea id="'.$id.'" name="'.$name.'" class="sl_field'.$style.'">'.$text.'</textarea>
    <script>
        var editor = CodeMirror.fromTextArea(document.getElementById("'.$id.'"), {
            lineNumbers: true,
            matchBrackets: true,
            mode: "'.$mode.'",
            extraKeys: {"Ctrl": "autocomplete"},
            value: document.documentElement.innerHTML,
            indentUnit: 4,
            indentWithTabs: true
        });
    </script>';
    return $code;
}

# Format nummer page for Ajax
function num_ajax(string $tpl, int $count, int $pages, int $page, int $mnum = 8, int $num = 1, string $ld = '', int $go = 0, string $op = '', string $id = '', int $cid = 0, string $typ = '', string $mod = ''): string {
 global $afile;
    $nnum = $mnum + 1;
    if ($pages > 1) {
        $cont = '';
        if ($num > 1) {
            $prev = $num - 1;
            $cprev = "<a href=\"#\" OnClick=\"AjaxLoad('GET', '".$ld."', '".$id."', 'go=".$go.'&amp;op='.$op.'&amp;id='.$cid.'&amp;cid='.$prev.'&amp;typ='.$typ.'&amp;dir='.$mod."', ''); return false;\" class=\"sl_num\" title=\""._BACK.'">'._BACK.'</a>';
        } else {
            $cprev = '<span class="sl_num" title="'._BACK.'">'._BACK.'</span>';
        }
        for ($i = 1; $i < $pages+1; $i++) {
            if ($i == $num) {
                $cont .= '<span title="'.$i.'">'.$i.'</span>';
            } else {
                if ((($i > ($num - $mnum)) && ($i < ($num + $mnum))) || ($i == $pages) || ($i == 1)) $cont .= "<a href=\"#\" OnClick=\"AjaxLoad('GET', '".$ld."', '".$id."', 'go=".$go.'&amp;op='.$op.'&amp;id='.$cid.'&amp;cid='.$i.'&amp;typ='.$typ.'&amp;dir='.$mod."', ''); return false;\" title=\"".$i.'">'.$i.'</a>';
            }
            if ($i < $pages) {
                if (($i > ($num - $nnum)) && ($i < ($num + $mnum))) $cont .= ' ';
                if (($num > $nnum) && ($i == 1)) $cont .= '<span class="sl_num_exit" title="&hellip;">&hellip;</span>';
                if (($num < ($pages - $mnum)) && ($i == ($pages - 1))) $cont .= '<span class="sl_num_exit" title="&hellip;">&hellip;</span>';
            }
        }
        if ($num < $pages) {
            $next = $num + 1;
            $cnext = " <a href=\"#\" OnClick=\"AjaxLoad('GET', '".$ld."', '".$id."', 'go=".$go.'&amp;op='.$op.'&amp;id='.$cid.'&amp;cid='.$next.'&amp;typ='.$typ.'&amp;dir='.$mod."', ''); return false;\" class=\"sl_num\" title=\""._NEXT.'">'._NEXT.'</a>';
        } else {
            $cnext = '<span class="sl_num" title="'._NEXT.'">'._NEXT.'</span>';
        }
        return setTemplateBasic($tpl, ['{%overall%}' => _OVERALL, '{%count%}' => $count, '{%by%}' => _BY, '{%pages%}' => $pages, '{%page_s%}' => _PAGE_S, '{%page%}' => $page, '{%perpage%}' => _PERPAGE, '{%pager%}' => $cont, '{%prev%}' => $cprev, '{%next%}' => $cnext]);
    }
    return '';
}

# Check type upload file
function check_file(string $type, string $typefile): string {
    $strtypefile = str_replace(',', '|', $typefile);
    if (!preg_match('#'.$strtypefile.'#i', $type) || preg_match('#php.*|js|htm|html|phtml|cgi|pl|perl|asp#i', $type)) return _ERROR_FILE;
    return '';
}

# Check size upload file
function check_size(string $file, int $width, int $height): string {
    list($imgwidth, $imgheight) = getimagesize($file);
    if ($imgwidth > $width || $imgheight > $height) return _ERROR_SIZE;
    return '';
}


# Upload file
function upload(int $typ, string $directory, string $typefile, int $maxsize, string $namefile, int $width, int $height, string $userid = '', string $url = ''): mixed {
 global $user, $conf, $stop;
    if ($typ == 1 && !empty($_FILES['userfile']['size'])) {
        if (is_uploaded_file($_FILES['userfile']['tmp_name'])) {
            if ($_FILES['userfile']['size'] > $maxsize) {
                $stop = _ERROR_BIG;
                return 0;
            } else {
                $type = strtolower(substr(strrchr($_FILES['userfile']['name'], '.'), 1));
                if (!check_file($type, $typefile) && !check_size($_FILES['userfile']['tmp_name'], $width, $height)) {
                    if (isAdmin() && !is_user()) {
                        $newname = ($namefile) ? $namefile.'-'.getPass(10).'.'.$type : getPass(15).'.'.$type;
                    } else {
                        $uname = (is_user()) ? intval($user[0]) : (($userid) ? intval($userid) : '0');
                        $newname = ($namefile) ? $namefile.'-'.getPass(10).'-'.$uname.'.'.$type : getPass(15).'.'.$type;
                    }
                    if (file_exists($directory.'/'.$newname)) {
                        $stop = _ERROR_EXIST;
                        return 0;
                    } else {
                        $res = copy($_FILES['userfile']['tmp_name'], $directory.'/'.$newname);
                        if (!$res) {
                            $stop = _ERROR_UP;
                            return 0;
                        } else {
                            return $newname;
                        }
                    }
                } else {
                    $stop = (!check_file($type, $typefile)) ? check_size($_FILES['userfile']['tmp_name'], $width, $height) : check_file($type, $typefile);
                    return 0;
                }
            }
        } else {
            $stop = _ERROR_DOWN;
            return 0;
        }
    } elseif ($typ == 2) {
        if (isset($_FILES['file']) && !empty($_FILES['file']) && checkSiteToken(getVar('post', 'token', 'raw', ''), 'upload')) {
            $files = count($_FILES['file']['name']);
            for ($i = 0; $i < $files; $i++) {
                if ($_FILES['file']['size'][$i] > $maxsize) {
                    echo '<div class="ico sl_warn">'._ERROR_BIG.'</div>';
                } else {
                    $type = strtolower(substr(strrchr($_FILES['file']['name'][$i], '.'), 1));
                    if (!check_file($type, $typefile) && !check_size($_FILES['file']['tmp_name'][$i], $width, $height)) {
                        if (isAdmin() && !is_user()) {
                            $newname = ($namefile) ? $namefile.'-'.getPass(10).'.'.$type : getPass(15).'.'.$type;
                        } else {
                            $uname = (is_user()) ? intval($user[0]) : (($userid) ? intval($userid) : '0');
                            $newname = ($namefile) ? $namefile.'-'.getPass(10).'-'.$uname.'.'.$type : getPass(15).'.'.$type;
                        }
                        if (file_exists($directory.'/'.$newname)) {
                            echo '<div class=" ico sl_warn">'._ERROR_EXIST.'</div>';
                        } else {
                            $res = copy($_FILES['file']['tmp_name'][$i], $directory.'/'.$newname);
                            if (!$res) {
                                echo '<div class="ico sl_warn">'._ERROR_UP.'</div>';
                            } else {
                                echo '<div class="ico sl_info">'._FILE_RENAMED.': '.$newname.'</div>';
                            }
                        }
                    } else {
                        $info = (!check_file($type, $typefile)) ? check_size($_FILES['file']['tmp_name'][$i], $width, $height) : check_file($type, $typefile);
                        echo '<div class="ico sl_warn">'.$info.'</div>';
                    }
                }
            }
        } else {
            echo '<div class="ico sl_warn">'._ERROR_DOWN.'</div>';
        }
    } elseif ($typ == 3 && !empty(getVar('post', 'sitefile', 'raw', ''))) {
        $sitefile = getVar('post', 'sitefile', 'raw', '');
        $afile = str_replace(['&', '?', '#'], '', $sitefile);
        $type = strtolower(substr(strrchr($afile, '.'), 1));
        if (!check_file($type, $typefile) && !check_size($sitefile, $width, $height)) {
            $fn = $sitefile;
            $path_sitefile = fopen($fn, 'rb');
            if (!$path_sitefile) {
                $stop = _ERROR_DOWN;
                return 0;
            } else {
                if (isAdmin() && !is_user()) {
                    $newname = ($namefile) ? $namefile.'-'.getPass(10).'.'.$type : getPass(15).'.'.$type;
                } else {
                    $uname = (is_user()) ? intval($user[0]) : (($userid) ? intval($userid) : '0');
                    $newname = ($namefile) ? $namefile.'-'.getPass(10).'-'.$uname.'.'.$type : getPass(15).'.'.$type;
                }
                $dir = $directory.'/'.$newname;
                if (file_exists($dir)) {
                    $stop = _ERROR_EXIST;
                    return 0;
                } else {
                    while (!feof($path_sitefile)) $data .= fread($path_sitefile, 1024);
                    fclose($path_sitefile);
                    $path_sitefile = fopen($directory.'/'.$newname, 'wb');
                    if (!$path_sitefile) {
                        $stop = _ERROR_UP;
                        return 0;
                    } else {
                        fwrite($path_sitefile, $data);
                        fclose($path_sitefile);
                        if (file_exists($dir)) {
                            if (filesize($dir) > $maxsize) {
                                unlink($dir);
                                $stop = _ERROR_BIG;
                                return 0;
                            } else {
                                return $newname;
                            }
                        }
                    }
                }
            }
        } else {
            $stop = (!check_file($type, $typefile)) ? check_size($sitefile, $width, $height) : check_file($type, $typefile);
            return 0;
        }
    } elseif ($typ == 4 && $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        $result = curl_exec($ch);
        if (!$result) return 0;
        preg_match('#Content-Type: \w+(\/)(?<value>\w+)#', $result, $value);
        $type = ($value['value'] == 'jpeg') ? 'jpg' : $value['value'];
        if (isAdmin() && !is_user()) {
            $newname = ($namefile) ? $namefile.'-'.getPass(10).'.'.$type : getPass(15).'.'.$type;
        } else {
            $uname = (is_user()) ? intval($user[0]) : (($userid) ? intval($userid) : '0');
            $newname = ($namefile) ? $namefile.'-'.getPass(10).'-'.$uname.'.'.$type : getPass(15).'.'.$type;
        }
        $dir = $directory.'/'.$newname;
        $from = file_get_contents($url);
        file_put_contents($dir, $from);
        return $newname;
    }
    return null;
}

# Format language
function language(string $lang = '', string $typ = ''): string {
    $dir = opendir('lang');
    $cont = (!$typ) ? '<option value="">'._ALL.'</option>' : '';
    while (false !== ($file = readdir($dir))) {
        if (preg_match("#^(.+)\.php#", $file, $matches)) {
            $langf = $matches[1];
            $title = getLangName($langf);
            $sel = ($lang == $langf) ? ' selected' : '';
            $cont .= '<option value="'.$langf.'"'.$sel.'>'.$title.'</option>';
        }
    }
    closedir($dir);
    return $cont;
}

# Builds a multiple-select list of module directories; $allow limits options to a whitelist when non-empty
function modul(string $name, string $class, string $modul, string $no = '', array $allow = []): string {
    $class = ($class) ? ' class="'.$class.'"' : '';
    $content = '<select name="'.$name.'[]"'.$class.' multiple>';
    if (!empty($no)) {
        $sel = empty($modul) ? ' selected' : '';
        $content .= '<option value="0"'.$sel.'>'._NO.'</option>';
    }
    $modul = explode(',', $modul);
    foreach (scandir('modules') as $file) {
        if (str_contains($file, '.')) continue;
        if ($allow && !in_array($file, $allow, true)) continue;
        $sel = '';
        foreach ($modul as $val) {
            if ($val !== '' && $val === $file) { $sel = ' selected'; break; }
        }
        $content .= '<option value="'.$file.'"'.$sel.'>'.getModuleName($file).'</option>';
    }
    $content .= '</select>';
    return $content;
}

# Format categorie module
function cat_modul(string $selectName, string $extraClass = '', string $selected = '', bool $autoSubmit = false): string {
    $submit  = $autoSubmit ? ' OnChange="submit()"' : '';
    $class   = $extraClass ? ' class="'.$extraClass.'"' : '';
    $content = '<select name="'.$selectName.'"'.$class.$submit.'>';
    $mods = ['faq', 'files', 'forum', 'help', 'jokes', 'links', 'media', 'news', 'pages', 'shop'];
    foreach ($mods as $m) {
        $sel     = ($selected == $m) ? ' selected' : '';
        $content .= '<option value="'.$m.'"'.$sel.'>'.getModuleName($m).' - '.$m.'</option>';
    }
    $content .= '</select>';
    return $content;
}

# Format editor
function redaktor(int $id, string $name, string $class, int $editor, mixed $submit): string {
 global $conf;
    $submit = ($submit) ? ' OnChange="submit()"' : '';
    $class = ($class) ? ' class="'.$class.'"' : '';
    $content = '<select name="'.$name.'"'.$submit.$class.'>';
    $ename = ($id == 1) ? [0 => _NO, 1 => 'SLAED BB '.substr($conf['version'], 0, strrpos($conf['version'], '.')), 2 => 'TinyMCE 4.5.6', 3 => 'CKEditor 4.6.2', 4 => 'CodeMirror 5.25.0'] : [0 => _NO, 1 => 'SLAED BB '.substr($conf['version'], 0, strrpos($conf['version'], '.')), 2 => 'TinyMCE 4.5.6', 3 => 'CKEditor 4.6.2'];
    foreach ($ename as $key => $value) {
        $sel = ($editor == $key) ? ' selected' : '';
        if ($key <= 1) {
            $content .= '<option value="'.$key.'"'.$sel.'>'.$value.'</option>';
        } elseif ($key == 2) {
            if (file_exists('plugins/tinymce/')) $content .= '<option value="'.$key.'"'.$sel.'>'.$value.'</option>';
        } elseif ($key == 3) {
            if (file_exists('plugins/ckeditor/')) $content .= '<option value="'.$key.'"'.$sel.'>'.$value.'</option>';
        } elseif ($key == 4) {
            $content .= '<option value="'.$key.'"'.$sel.'>'.$value.'</option>';
        }
    }
    $content .= '</select>';
    return $content;
}

# Show comments
function ashowcom(int $cid = 0, string $mod = ''): string {
 global $db, $conf, $afile, $user;
    $mod = filterVar($mod);
    $params = [];
    if (defined('ADMIN_FILE')) {
        if (getVar('get', 'status', 'num', 0) == 1) {
            $ordern = 'WHERE status = :status';
            $params = ['status' => 0];
        } else {
            $ordern = 'WHERE status != :status';
            $params = ['status' => 0];
        }
        $ccnum = $conf['comments']['anum'];
        $plnum = $conf['comments']['anump'];
    } else {
        if (is_moder($mod)) {
            $ordern = 'WHERE cid = :cid AND modul = :mod';
            $params = ['cid' => $cid, 'mod' => $mod];
        } else {
            $ordern = 'WHERE cid = :cid AND modul = :mod AND status != :status';
            $params = ['cid' => $cid, 'mod' => $mod, 'status' => 0];
        }
        $ccnum = $conf['comments']['num'];
        $plnum = $conf['comments']['nump'];
    }
    list($numstories) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(cid) FROM '.PREFIX_DB.'_comment '.$ordern, $params));
    if ($numstories > 0) {
        $com = getVar('get', 'com', 'num', '1');
        $offset = ($com - 1) * $ccnum;
        $numpages = ceil($numstories / $ccnum);
        if ($conf['comments']['sort']) {
            $sort = 'ASC';
            $a = ($com) ? $offset+1 : 1;
        } else {
            $sort = 'DESC';
            $a = $numstories;
            if ($numstories > $offset) $a -= $offset;
        }
        $where = [];
        $result = $db->getSqlQuery('SELECT id, cid, modul, time, uid, name, ip, body, status FROM '.PREFIX_DB.'_comment '.$ordern.' ORDER BY time '.$sort.' LIMIT '.intval($offset).', '.intval($ccnum), $params);
        while (list($com_id, $com_cid, $com_modul, $com_date, $com_uid, $com_name, $com_host, $com_text, $com_status) = $db->getSqlRow($result)) {
            $cmassiv[] = [$com_id, $com_cid, $com_modul, $com_date, $com_uid, $com_name, $com_host, $com_text, $com_status];
            if ($com_uid) $where[] = $com_uid;
            unset($com_id, $com_cid, $com_modul, $com_date, $com_uid, $com_name, $com_host, $com_text, $com_status);
        }
        if ($where) {
            $uids = array_values(array_unique(array_map('intval', $where)));
            $uids = array_values(array_filter($uids, static fn($v) => $v > 0));
            if ($uids) {
                $up = [];
                $um = [];
                foreach ($uids as $k => $v) {
                    $ph = 'u'.$k;
                    $up[] = ':'.$ph;
                    $um[$ph] = $v;
                }
                $result2 = $db->getSqlQuery('SELECT u.id, u.name, u.rank, u.email, u.website, u.avatar, u.regdate, u.origin, u.sig, u.viewmail, u.points, u.warnings, u.gender, u.votes, u.tvotes, g.name, g.rank, g.color FROM '.PREFIX_DB.'_users AS u LEFT JOIN '.PREFIX_DB.'_groups AS g ON ((g.extra = 1 AND u.grp = g.id) OR (g.extra != 1 AND u.points >= g.points)) WHERE u.id IN ('.implode(', ', $up).') ORDER BY g.extra ASC, g.points ASC', $um);
                while (list($user_id, $user_name, $user_rank, $user_email, $user_website, $user_avatar, $user_regdate, $user_from, $user_sig, $user_viewemail, $user_points, $user_warnings, $user_gender, $user_votes, $user_totalvotes, $user_gname, $user_grank, $user_gcolor) = $db->getSqlRow($result2)) {
                    $umassiv[] = [$user_id, $user_name, $user_rank, $user_email, $user_website, $user_avatar, $user_regdate, $user_from, $user_sig, $user_viewemail, $user_points, $user_warnings, $user_gender, $user_votes, $user_totalvotes, $user_gname, $user_grank, $user_gcolor];
                    unset($user_id, $user_name, $user_rank, $user_email, $user_website, $user_avatar, $user_regdate, $user_from, $user_sig, $user_viewemail, $user_points, $user_warnings, $user_gender, $user_votes, $user_totalvotes, $user_gname, $user_grank, $user_gcolor);
                }
            }
        }
        $cont = '';
        if (defined('ADMIN_FILE')) {
            $cont .= '<form name="comm" action="'.$afile.'.php" method="post">';
            $b = 0;
        }
        foreach ($cmassiv as $val) {
            $com_id = $val[0];
            $com_cid = $val[1];
            $com_modul = $val[2];
            $com_date = $val[3];
            $com_uid = $val[4];
            $com_name = $val[5];
            $com_host = $val[6];
            $com_text = $val[7];
            $com_status = $val[8];
            unset($user_id, $user_name, $user_rank, $user_email, $user_website, $user_avatar, $user_regdate, $user_from, $user_sig, $user_viewemail, $user_points, $user_warnings, $user_gender, $user_votes, $user_totalvotes, $user_gname, $user_grank, $user_gcolor);
            if (isset($umassiv)) {
                foreach ($umassiv as $val2) {
                    if (strtolower($com_uid) == strtolower($val2[0])) {
                        $user_id = $val2[0];
                        $user_name = $val2[1];
                        $user_rank = $val2[2];
                        $user_email = $val2[3];
                        $user_website = $val2[4];
                        $user_avatar = $val2[5];
                        $user_regdate = $val2[6];
                        $user_from = $val2[7];
                        $user_sig = $val2[8];
                        $user_viewemail = $val2[9];
                        $user_points = $val2[10];
                        $user_warnings = $val2[11];
                        $user_gender = $val2[12];
                        $user_votes = $val2[13];
                        $user_totalvotes = $val2[14];
                        $user_gname = $val2[15];
                        $user_grank = $val2[16];
                        $user_gcolor = $val2[17];
                    }
                }
            }
            $avname = (!empty($user_name)) ? $user_name : $com_name.' ('._ANONYM.')';
            $date = '<span title="'._PADD.'" class="sl_t_post">'.format_time($com_date, _TIMESTRING).'</span>';
            $ip = (is_moder($com_modul)) ? user_geo_ip($com_host, 4) : '';
            $amess = '<a href="#'.$com_id.'" title="'._COMMENT.': '.$a.'" class="sl_pnum">'.$a.'</a>';
            $avatar = (!empty($user_name)) ? (($user_avatar && file_exists($conf['users']['adirectory'].'/'.$user_avatar)) ? $conf['users']['adirectory'].'/'.$user_avatar : $conf['users']['adirectory'].'/default/00.gif') : $conf['users']['adirectory'].'/default/0.gif';
            $rank = (!empty($user_rank)) ? $user_rank : '';
            $trank = (!empty($user_gname)) ? _GROUP.': '.$user_gname : _RANK;
            $rlink = (!empty($user_grank) && file_exists(img_find('ranks/'.$user_grank))) ? '<img src="'.img_find('ranks/'.$user_grank).'" alt="'.$trank.'" title="'.$trank.'">' : '';
            $rate = (!empty($user_id)) ? ajax_rating(0, $user_id, 'account', $user_votes, $user_totalvotes, $com_id, 1) : '';
            $rwarn = (!empty($user_warnings)) ? _UWARNS.': '.warnings($user_warnings) : '';
            $group = (!empty($user_gname)) ? _GROUP.': <span style="color: '.$user_gcolor.'">'.$user_gname.'</span>' : '';
            $point = ($conf['users']['point'] && !empty($user_points)) ? _POINTS.': '.$user_points : '';
            $regdate = (!empty($user_regdate)) ? _REG.': '.format_time($user_regdate) : _NO_INFO;
            $gender = (!empty($user_gender)) ? _GENDER.': '.gender($user_gender) : '';
            $from = (!empty($user_from)) ? _FROM.': '.$user_from : '';
            $sig = (!empty($user_sig)) ? '<hr>'.$user_sig : '';
            $personal = (is_moder($com_modul) || is_user() || $conf['comments']['anonpost'] != 0) ? "<a href=\"javascript: InsertCode('name', '".$avname."', '', '', '1');\" title=\""._PERSONAL.'" class="sl_but_blue">'._PERS.'</a>' : '';
            $privat = ($conf['comments']['privat'] && $conf['privat']['act'] && !empty($user_name)) ? '<a href="index.php?name=account&amp;op=privat&amp;uname='.urlencode($user_name).'" title="'._SENDMES.'" class="sl_but_green">'._MESSAGE.'</a>' : '';
            $profil = ($conf['comments']['profil'] && !empty($user_name)) ? '<a href="index.php?name=account&amp;op=view&amp;uname='.urlencode($user_name).'" title="'._PERSONALINFO.'" class="sl_but">'._ACCOUNT.'</a>' : '';
            $web = ($conf['comments']['web'] && !empty($user_website)) ? '<a href="'.$user_website.'" target="_blank" title="'._DOWNLLINK.'" class="sl_but">'._SITE.'</a>' : '';

            # Future functions
            #$warn = "<a href=\"javascript: scroll(0, 0);\" title=\""._WARNM."\">"._WARNM."</a>";
            #$thank = "<a href=\"javascript: scroll(0, 0);\" title=\""._THANK."\">"._THANK."</a>";
            $warn = '';
            $thank = '';

            if (is_moder($com_modul)) {
                if (defined('ADMIN_FILE')) {
                    $edit = add_menu('<a href="index.php?name='.$com_modul.'&amp;op=view&amp;id='.$com_cid.'#'.$com_id.'" title="'._MVIEW.'">'._MVIEW.'</a>||<a href="'.$afile.'.php?op=comm_edit&amp;id='.$com_id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=comm_act&amp;id='.$com_id.'&amp;refer=1" title="'._ACTIVATE.'">'._ACTIVATE.'</a>||<a href="'.$afile.'.php?op=comm_del&amp;id='.$com_id."&amp;refer=1\" OnClick=\"return DelCheck(this, '"._DELETE.' &quot;'.cutstr(filterText(filterReplaceText(filterMarkdown($com_text, $com_modul, false), $com_modul)), 10)."&quot;?');\" title=\""._ONDELETE.'">'._ONDELETE.'</a>');
                } else {
                    $edit = add_menu("<a href=\"#\" OnClick=\"AjaxLoad('GET', '1', 'com".$com_id."', 'go=1&amp;op=editcom&amp;id=".$com_id.'&amp;typ=1&amp;mod='.$com_modul."', ''); return false;\" title=\""._ONEDIT.'">'._ONEDIT."</a>||<a href=\"#\" OnClick=\"AjaxLoad('GET', '1', 'com".$com_id."', 'go=1&amp;op=closecom&amp;id=".$com_id.'&amp;typ=0&amp;mod='.$com_modul."', ''); return false;\" title=\""._FMODC.'">'._FMODC."</a>||<a href=\"#\" OnClick=\"AjaxLoad('GET', '1', 'com".$com_id."', 'go=1&amp;op=closecom&amp;id=".$com_id.'&amp;typ=1&amp;mod='.$com_modul."', ''); return false;\" title=\""._ACTIVATE.'">'._ACTIVATE.'</a>');
                }
            } else {
                $stime = strtotime($com_date) + $conf['comments']['edit'];
                $edit = (is_user() && isset($user_id) == intval($user[0]) && time() < $stime) ? add_menu("<a href=\"#\" OnClick=\"AjaxLoad('GET', '1', 'com".$com_id."', 'go=1&amp;op=editcom&amp;id=".$com_id.'&amp;typ=1&amp;mod='.$com_modul."', ''); return false;\" title=\""._ONEDIT.'">'._ONEDIT.'</a>') : '';
            }
            $hclass = (!defined('ADMIN_FILE') && !$com_status) ? 'title="'._PCLOSED.'" class="sl_hidden"' : '';
            $text = '<div id="repcom'.$com_id.'">'.filterReplaceText(filterMarkdown($com_text, $com_modul, false), $com_modul).'</div>';
            if (defined('ADMIN_FILE')) {
                $checkb = (!$b) ? ' '._CHECKALL." <input type=\"checkbox\" name=\"markcheck\" id=\"markcheck\" OnClick=\"CheckBox('#markcheck', '.sl_check')\"> | <input type=\"checkbox\" name=\"id[]\" class=\"sl_check\" value=\"".$com_id.'">' : ' <input type="checkbox" name="id[]" class="sl_check" value="'.$com_id.'">';
                $b++;
            } else {
                $checkb = '';
            }
            $cont .= setTemplateBasic('comment', ['{%id%}' => $com_id, '{%username%}' => $avname, '{%date%}' => $date, '{%ip%}' => $ip, '{%post_count%}' => $amess, '{%avatar%}' => $avatar, '{%rank%}' => $rank, '{%rank_link%}' => $rlink, '{%user_rate%}' => $rate, '{%warn%}' => $rwarn, '{%group%}' => $group, '{%points%}' => $point, '{%regdate%}' => $regdate, '{%gender%}' => $gender, '{%from%}' => $from, '{%text%}' => $text, '{%sig%}' => filterReplaceText(filterMarkdown($sig, $com_modul, false), $com_modul), '{%btn_personal%}' => $personal, '{%btn_pm%}' => $privat, '{%btn_profile%}' => $profil, '{%btn_web%}' => $web, '{%btn_warn%}' => $warn, '{%btn_thank%}' => $thank, '{%btn_edit%}' => $edit, '{%hclass%}' => $hclass, '{%checkb%}' => $checkb]);
            if ($conf['comments']['sort']) { $a++; } else { $a--; }
        }
        if (defined('ADMIN_FILE')) {
            $selms = _CHECKOP.': <select name="op"><option value="comm_act">'._ACTIVATE.'</option><option value="comm_del">'._DELETE.'</option></select> <input type="hidden" name="refer" value="1"><input type="submit" value="'._OK.'" class="sl_but_blue">';
            $pag = (getVar('get', 'status', 'num', 0) == 1) ? 'op=comm_show&amp;status=1' : 'op=comm_show';
            $numpt = setPageNumbers('pagenum', $com_modul, $numstories, $numpages, $ccnum, $pag.'&amp;', $plnum, 0, '', 'com');
            $cont .= setTemplateBasic('list-bottom', ['{%pager%}' => $numpt, '{%select%}' => $selms]);
            $out = setTemplateBasic('open', []).$cont.setTemplateBasic('close', []);
        } else {
            $num = getVar('get', 'num', 'num');
            $pag = empty($num) ? 'op=view&id='.$cid : 'op=view&id='.$cid.'&num='.$num;
            $cont .= setPageNumbers('pagenum', $com_modul, $numstories, $numpages, $ccnum, $pag.'&', $plnum, 0, '#comm', 'com');
            $out = setTemplateBasic('title', ['{%title%}' => _COMMENTS]).setTemplateBasic('open').$cont.setTemplateBasic('close');
        }
    } else {
        $winfo = (defined('ADMIN_FILE')) ? _NO_INFO : _NOCOMMENTS;
        $out = setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $winfo]);
    }
    return $out;
}

# Save edit comments
function editcom(): string {
 global $db, $conf, $user;
    $id   = getVar('post', 'id',   'num',  0) ?: getVar('get', 'id',   'num',  0);
    $typ  = getVar('post', 'typ',  'num',  0) ?: getVar('get', 'typ',  'num',  0);
    $mod  = filterVar(getVar('post', 'mod',  'text', '') ?: getVar('get', 'mod',  'text', ''));
    $text = trim(getVar('post', 'text', 'raw',  '') ?: getVar('get', 'text', 'raw',  ''));
    list($uid, $date, $comment) = $db->getSqlRow($db->getSqlQuery('SELECT uid, time, body FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $id]));
    $stime = strtotime($date) + $conf['comments']['edit'];
    if (is_moder($mod) || (is_user() && $uid == intval($user[0]) && time() < $stime)) {
        if ($id && $mod && !$text) {
            $content = ($typ) ? textareae('com'.$id, '1', 'editcom', $id, '0', '0', $mod, $comment, '10') : filterReplaceText(filterMarkdown($comment, $mod, false), $mod);
            echo $content;
        } elseif ($id && $mod && $text) {
            $checks = str_replace(["\n", "\r", "\t"], ' ', $text);
            $e = explode(' ', $checks);
            for ($a = 0; $a < count($e); $a++) $o = strlen($e[$a]);
            $stop = [];
            if ($text == '') $stop[] = _CERROR1;
            if ($o > $conf['comments']['letter']) $stop[] = _CERROR2;
            if (!is_moder($mod) && (($conf['comments']['link'] == 1 && !is_user()) || ($conf['comments']['link'] == 2)) && stripos($text, 'http://') !== false) $stop[] = _CERROR9;
            $urlclick = (!is_moder($mod) && (($conf['comments']['alink'] == 1 && !is_user()) || ($conf['comments']['alink'] == 2))) ? 1 : 0;
            if (!$stop) {
                $comm = filterHtml($text, $urlclick);
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET body = :body WHERE id = :id', ['body' => $comm, 'id' => $id]);
                echo filterReplaceText(filterMarkdown($comm, $mod, false), $mod);
            } else {
                return setTemplateWarning('warn', ['text' => $stop, 'url' => '', 'time' => 0, 'id' => 'warn']);
            }
        }
    } else {
        $info = sprintf(_PEDEND, intval($conf['comments']['edit'] / 60));
        return setTemplateWarning('warn', ['text' => $info, 'url' => '', 'time' => 0, 'id' => 'warn']);
    }
    return '';
}

# Close comments
function closecom(): void {
 global $db;
    $id  = getVar('post', 'id',  'num',  0) ?: getVar('get', 'id',  'num',  0);
    $typ = getVar('post', 'typ', 'num',  0) ?: getVar('get', 'typ', 'num',  0);
    $mod = filterVar(getVar('post', 'mod', 'text', '') ?: getVar('get', 'mod', 'text', ''));
    if ($id && $mod && is_moder($mod)) {
        $status = ($typ) ? 1 : 0;
        $info = ($typ) ? _PCOPEN : _PCLOSED;
        $numcom = ($typ) ? 0 : 1;
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET status = :status WHERE id = :id', ['status' => $status, 'id' => $id]);
        list($cid, $uid) = $db->getSqlRow($db->getSqlQuery('SELECT cid, uid FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $id]));
        numcom($cid, $mod, $numcom, $uid);
        echo setTemplateWarning('warn', ['text' => $info, 'url' => '', 'time' => 0, 'id' => 'warn']);
    }
}

# Number comments
function numcom(int $id = 0, string $mod = '', bool $del = false, int $uid = 0): void {
 global $db;
    $mod   = $mod ? filterVar($mod) : '';
    $delta = $del ? -1 : 1;
    $point = $del ? 1 : 0;
    if ($id && $mod) {
        if ($mod == 'account' || $mod == 'members') {
            #$db->getSqlQuery("UPDATE ".PREFIX_DB."_users SET totalcomments=totalcomments".$typ." WHERE lid = '".$id."'");
            update_points(3, $uid, $point);
        } elseif ($mod == 'faq') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_faq SET comments = comments + :delta WHERE id = :id', ['delta' => $delta, 'id' => $id]);
            update_points(7, $uid, $point);
        } elseif ($mod == 'files') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_files SET comments = comments + :delta WHERE id = :id', ['delta' => $delta, 'id' => $id]);
            update_points(10, $uid, $point);
        } elseif ($mod == 'gallery') {
            #$db->getSqlQuery("UPDATE ".PREFIX_DB."_gallery SET totalcomments=totalcomments".$typ." WHERE lid = '".$id."'");
            update_points(17, $uid, $point);
        } elseif ($mod == 'links') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_links SET comments = comments + :delta WHERE id = :id', ['delta' => $delta, 'id' => $id]);
            update_points(22, $uid, $point);
        } elseif ($mod == 'media') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_media SET comments = comments + :delta WHERE id = :id', ['delta' => $delta, 'id' => $id]);
            update_points(26, $uid, $point);
        } elseif ($mod == 'multimedia') {
            #$db->getSqlQuery("UPDATE ".PREFIX_DB."_multimedia SET totalcom=totalcom".$typ." WHERE id = '".$id."'");
            update_points(29, $uid, $point);
        } elseif ($mod == 'news') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET comments = comments + :delta WHERE id = :id', ['delta' => $delta, 'id' => $id]);
            update_points(32, $uid, $point);
        } elseif ($mod == 'pages') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_pages SET comments = comments + :delta WHERE id = :id', ['delta' => $delta, 'id' => $id]);
            update_points(36, $uid, $point);
        } elseif ($mod == 'shop') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_products SET comments = comments + :delta WHERE id = :id', ['delta' => $delta, 'id' => $id]);
            update_points(40, $uid, $point);
        } elseif ($mod == 'voting') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_voting SET comments = comments + :delta WHERE id = :id', ['delta' => $delta, 'id' => $id]);
            update_points(43, $uid, $point);
        }
    }
}

# Voting result save
function avoting_save(): void {
 global $db, $conf, $user, $locale;
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
            $cont = setTemplateWarning('warn', ['text' => _SEROR1, 'url' => '?name=voting&amp;op=view&amp;id='.$id, 'time' => 3, 'id' => 'warn']);
        } else {
            $ip = getIp();
            $past = time() - intval($conf['voting']['voting_t']);
            $cmod = substr('voting', 0, 2).'-'.$id;
            $cookies = (isset($_COOKIE[$cmod])) ? intval($_COOKIE[$cmod]) : '';
            $uid = (is_user()) ? intval(substr($user[0], 0, 11)) : 0;
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB."_rating WHERE time < :past AND modul = 'voting'", ['past' => $past]);
            list($num) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB."_rating WHERE (mid = :id AND modul = 'voting' AND ip = :ip) OR (mid = :id2 AND modul = 'voting' AND uid = :uid AND uid != '0')", ['id' => $id, 'ip' => $ip, 'id2' => $id, 'uid' => $uid]));
            if ($cookies == $id || $num > 0) {
                $cont = setTemplateWarning('warn', ['text' => _SEROR2, 'url' => '?name=voting&amp;op=view&amp;id='.$id, 'time' => 3, 'id' => 'warn']);
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
                    update_points(42);
                }
                $cont = getVoting($id);
            }
        }
    } else {
        $cont = setTemplateWarning('warn', ['time' => '3', 'url' => '?name=voting', 'id' => 'warn', 'text' => _ERROR]);
    }
    echo $cont;
}

# Update points
function update_points(int $id, int $uid = 0, bool $del = false): void {
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

# Format image preview PHP GD
function create_img_gd(string $imgfile, string $imgthumb, int $newwidth): string {
    if (function_exists('imagecreate')) {
        $imginfo = getimagesize($imgfile);
        switch($imginfo[2]) {
            default: return $imgfile; break;
            case 1: $type = IMG_GIF; break;
            case 2: $type = IMG_JPG; break;
            case 3: $type = IMG_PNG; break;
            case 4: $type = IMG_WBMP; break;
        }
        switch($type) {
            case IMG_GIF:
            if (!function_exists('imagecreatefromgif')) return $imgfile;
            $srcImage = imagecreatefromgif($imgfile);
            break;
            case IMG_JPG:
            if (!function_exists('imagecreatefromjpeg')) return $imgfile;
            $srcImage = imagecreatefromjpeg($imgfile);
            break;
            case IMG_PNG:
            if(!function_exists('imagecreatefrompng')) return $imgfile;
            $srcImage = imagecreatefrompng($imgfile);
            break;
            case IMG_WBMP:
            if (!function_exists('imagecreatefromwbmp')) return $imgfile;
            $srcImage = imagecreatefromwbmp($imgfile);
            break;
            default:
            return $imgfile;
        }
        if ($srcImage) {
            $srcWidth = $imginfo[0];
            $srcHeight = $imginfo[1];
            $ratioWidth = $srcWidth / $newwidth;
            $destWidth = $newwidth;
            $destHeight = $srcHeight / $ratioWidth;
            $destImage = imagecreatetruecolor($destWidth, $destHeight);

            imagesavealpha($destImage, true);
            $iccalpha = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
            imagefill($destImage, 0, 0, $iccalpha);
            imagecopyresampled($destImage, $srcImage, 0, 0, 0, 0, $destWidth, $destHeight, $srcWidth, $srcHeight);

            switch($type) {
                case IMG_GIF:
                imagegif($destImage, $imgthumb);
                break;
                case IMG_JPG:
                imagejpeg($destImage, $imgthumb);
                break;
                case IMG_PNG:
                imagepng($destImage, $imgthumb);
                break;
                case IMG_WBMP:
                imagewbmp($destImage, $imgthumb);
                break;
            }
            return $imgthumb;
        } else {
            return $imgfile;
        }
    } else {
        return $imgfile;
    }
}
