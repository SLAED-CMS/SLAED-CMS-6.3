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

# Load the runtime config from cache, rebuilding it from source if needed
function getConfig(): array {
    $local_file = CONFIG_DIR.'/local.php';
    if (is_file($local_file) && is_readable($local_file)) {
        $cache = require $local_file;
        if (is_array($cache) && isset($cache['_meta'], $cache['_config']) && is_array($cache['_meta']) && is_array($cache['_config']) && (($cache['_meta']['cache_version'] ?? 0) === 1)) {
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
    $export = function (array $arr, int $dep = 0) use (&$export): string {
        $pad = str_repeat('    ', $dep);
        $ind = $pad.'    ';
        $out = '['.PHP_EOL;
        foreach ($arr as $key => $val) {
            $out .= $ind.var_export($key, true).' => ';
            $out .= is_array($val) ? $export($val, $dep + 1) : var_export($val, true);
            $out .= ','.PHP_EOL;
        }
        return $out.$pad.']';
    };
    $data = [
        '_meta' => [
            'base_fingerprint' => sha1($hash),
            'cache_version' => 1,
            'generated_at' => time(),
        ],
        '_config' => $conf,
    ];
    $tmp = $local_file.'.tmp';
    $is_new = !file_exists($local_file);
    $cnt = '<?php'.PHP_EOL
    .'# Author: Eduard Laas'.PHP_EOL
    .'# Copyright © 2005 - '.date('Y').' SLAED'.PHP_EOL
    .'# License: GNU GPL 3'.PHP_EOL
    .'# Website: slaed.net'.PHP_EOL.PHP_EOL
    .'return '.$export($data).';'.PHP_EOL;
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

# System file include
require_once BASE_DIR.'/core/security.php';

$theme = getTheme();
if (is_file(BASE_DIR.'/templates/'.$theme.'/index.php')) require_once BASE_DIR.'/templates/'.$theme.'/index.php';
require_once BASE_DIR.'/core/classes/template.php';
require_once BASE_DIR.'/core/classes/parser.php';
require_once BASE_DIR.'/core/classes/geoip.php';
$tpl = new Template($theme);
$prs = new Parser();

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

# Returns all scheduler jobs normalized and sorted by priority and key
function getSchedulerJobs(): array {
    global $conf;
    $arr = [];
    foreach ($conf['scheduler']['jobs'] ?? [] as $key => $val) {
        if (!is_string($key) || $key === '') continue;
        $arr[$key] = $val + ['name' => $key];
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
    $file = LOGS_DIR.'/scheduler/'.$name.'.json';
    $state = ['running' => 0, 'started_at' => 0, 'last_run' => 0, 'last_success' => 0, 'last_status' => 'idle', 'last_message' => '', 'last_error' => '', 'last_duration' => 0, 'last_trigger' => '', 'fail_count' => 0];
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

# Returns whether the scheduler job lock is still valid
function checkSchedulerLock(string $name, array $job = [], array $state = []): bool {
    global $conf;
    if ($job === []) $job = ($conf['scheduler']['jobs'][$name] ?? []) + ['name' => $name];
    if ($state === []) $state = getSchedulerState($name);
    if (empty($state['running']) || empty($state['started_at'])) return false;
    $time = max(60, (int)($job['lock_timeout'] ?? 0));
    return (time() - (int)$state['started_at']) < $time;
}

# Returns whether the scheduler job is due for execution
function checkSchedulerDue(string $name, array $job = [], array $state = []): bool {
    global $conf;
    if ($job === []) $job = ($conf['scheduler']['jobs'][$name] ?? []) + ['name' => $name];
    if ($state === []) $state = getSchedulerState($name);
    if ((int)($job['active'] ?? 0) !== 1) return false;
    if (checkSchedulerLock($name, $job, $state)) return false;
    $next = getSchedulerPlannedTime($job, $state);
    return $next > 0 && $next <= time();
}

# Acquires the scheduler lock for a named job and persists trigger metadata
function addSchedulerLock(string $name, string $type): bool {
    global $conf;
    $job = ($conf['scheduler']['jobs'][$name] ?? []) + ['name' => $name];
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
function checkSchedulerAccess(string $type, string $stok): bool {
    global $conf;
    if (isAdmin(true)) return true;
    $stkn = (string)($conf['scheduler']['token'] ?? '');
    $psok = ($type === 'pseudo' && checkSiteToken($stok, 'scheduler'));
    $tkok = ($stkn !== '' && hash_equals($stkn, $stok));
    return $psok || $tkok;
}


# Returns a signed pseudo-trigger URL when the next due job should be started asynchronously
function addSchedulerTrigger(): string {
    global $conf;
    if ((int)($conf['scheduler']['active'] ?? 0) !== 1 || (int)($conf['scheduler']['pseudo'] ?? 0) !== 1) return '';
    if (checkSchedulerCronAlive()) return '';
    $job = getSchedulerNextJob();
    if (!$job) return '';
    $file = LOGS_DIR.'/scheduler/trigger.json';
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) return '';
    $last = 0;
    if (is_file($file) && filesize($file) !== 0) {
        $json = file_get_contents($file);
        $data = $json ? json_decode($json, true) : [];
        if (is_array($data)) $last = (int)($data['time'] ?? 0);
    }
    $cool = max(15, (int)($conf['scheduler']['trigger_cooldown'] ?? 15));
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
    global $conf;
    if ($name !== null && $name !== '') {
        $job = ($conf['scheduler']['jobs'][$name] ?? []) + ['name' => $name];
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

# Dispatches a named system scheduler job by key and returns runtime metadata
function addSchedulerSystemJob(string $name): array {
    return match ($name) {
        'backup' => addBackupTask(),
        'filescan' => addFilescanTask(),
        'sitemap' => addSitemapTask(),
        'newsletter' => updateNewsletter(true),
        default => ['status' => 'failed', 'message' => 'Unknown system job: '.$name],
    };
}

# Executes the next due scheduler job or a named job and returns a structured result
function addSchedulerRun(?string $name = null, string $type = 'manual'): array {
    global $conf;
    if ((int)($conf['scheduler']['active'] ?? 0) !== 1) return ['status' => 'disabled', 'message' => 'Scheduler is disabled'];
    if ($name !== null && $name !== '' && $type === 'manual') {
        $job = ($conf['scheduler']['jobs'][$name] ?? []) + ['name' => $name];
        if (($job['system'] ?? '') === '' && ($job['type'] ?? '') !== 'custom') $job = null;
    } else {
        $job = getSchedulerNextJob($name);
    }
    if (!$job) return ['status' => 'idle', 'message' => 'No due jobs'];
    $name = (string)$job['name'];
    if (!addSchedulerLock($name, $type)) return ['status' => 'locked', 'message' => 'Job is already running', 'job' => $name];
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
    deleteSchedulerLock($name, $stat, $mess, $extra);
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
        $result = $db->getSqlQuery('SELECT id, bkey, title, content, url, bfile, view, expire, action, bpos, which FROM '.PREFIX_DB."_blocks WHERE status = '1' ".$querylang.' ORDER BY weight ASC', $qlang_params);
        while(list($bid, $bkey, $title, $content, $url, $bfile, $view, $expire, $action, $bpos, $which) = $db->getSqlRow($result)) {
            $bid = intval($bid);
            $content = $prs->filterContent($content, false, 'all');
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

# Executes a database backup task and returns scheduler metadata
function addBackupTask(): array {
    global $db, $conf;
    if (empty($conf['security']['log_b'])) return ['status' => 'failed', 'message' => 'Database backup is disabled'];
    $backup_start = microtime(true);

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
        if (class_exists('Logger')) Logger::addSql('error', 'Backup failed: Cannot get MySQL version', ['error' => $e->getMessage()]);
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
        if (class_exists('Logger')) Logger::addSql('error', 'Backup failed: No tables found to backup');
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
            if (class_exists('Logger')) Logger::addFile('error', 'Backup failed: Cannot create backup directory', ['path' => $backup_dir]);
            return ['status' => 'failed', 'message' => 'Cannot create backup directory'];
        }
    }

    $filepath = $backup_dir.$name.'.sql';

    // FIX: Error handling for fopen
    $fp = fopen($filepath, 'wb');
    if (!$fp) {
        if (class_exists('Logger')) Logger::addFile('error', 'Backup failed: Cannot create file', ['path' => $filepath]);
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

# Track the current visitor session and return derived tracking context
function updateSessionTrack(int $ctime, string $request, string $name): array {
    global $db, $conf, $user, $admin;
    $ip = getIp();
    $url = urlencode($request);
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
        if (!defined('ADMIN_FILE') && is_user()) {
            $uagent = getAgent();
            $uid = intval($user[0]);
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET ip = :ip, lastvis = NOW(), agent = :agent WHERE id = :uid', ['ip' => $ip, 'agent' => $uagent, 'uid' => $uid]);
        }
        $num = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_session WHERE uname = :uname', ['uname' => $uname]));
        if ($num >= 1) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_session SET time = :time, ip = :ip, guest = :guest, modul = :modul, url = :url WHERE uname = :uname', ['time' => $ctime, 'ip' => $ip, 'guest' => $guest, 'modul' => $name, 'url' => $url, 'uname' => $uname]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_session (uname, time, ip, guest, modul, url) VALUES (:uname, :time, :ip, :guest, :modul, :url)', ['uname' => $uname, 'time' => $ctime, 'ip' => $ip, 'guest' => $guest, 'modul' => $name, 'url' => $url]);
        }
    }
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
        $islink = 0;
        $slink = '';
        $result = $db->getSqlQuery('SELECT url FROM '.PREFIX_DB.'_auto_links');
        while ([$slink] = $db->getSqlRow($result)) {
            if (preg_match('#'.$slink.'#i', $referer)) {
                $islink = 1;
                break;
            }
        }
        if ($islink) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_auto_links SET hits = hits + 1 WHERE url = :url', ['url' => $slink]);
            [$lid] = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_auto_links WHERE url = :url', ['url' => $slink]));
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

# Update the active session state in the sliding session log
function updateSessionState(string $sid, int $ctime): array {
    $file = COUNTER_DIR.'/sessions.log';
    $ret = ['is_new' => false, 'depth' => 1, 'duration' => 0];
    $fp = fopen($file, 'c+');
    if ($fp === false) return $ret;
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return $ret;
    }
    try {
        rewind($fp);
        $data = stream_get_contents($fp);
        $lim = $ctime - 1800;
        $rows = [];
        $found = false;
        $lines = ($data !== false && $data !== '') ? explode("\n", trim($data)) : [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $pts = array_pad(explode('|', $line, 4), 4, '');
            $csid = (string)$pts[0];
            $fst = (int)$pts[1];
            $lst = (int)$pts[2];
            $hits = (int)$pts[3];
            if ($lst < $lim) continue;
            if ($csid === $sid) {
                if ($found) continue;
                $found = true;
                $hits = ($hits > 0) ? $hits + 1 : 1;
                $lst = $ctime;
                $ret['depth'] = $hits;
                $ret['duration'] = max(0, $lst - $fst);
                $rows[] = $csid.'|'.$fst.'|'.$lst.'|'.$hits;
                continue;
            }
            $rows[] = $csid.'|'.$fst.'|'.$lst.'|'.$hits;
        }
        if (!$found) {
            $ret['is_new'] = true;
            $rows[] = $sid.'|'.$ctime.'|'.$ctime.'|1';
        }
        $txt = $rows !== [] ? implode(PHP_EOL, $rows).PHP_EOL : '';
        rewind($fp);
        ftruncate($fp, 0);
        if ($txt !== '') fwrite($fp, $txt);
        fflush($fp);
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    return $ret;
}

# Track daily statistics and rotate counter files when periods change
function updateStatsTrack(string $request, int $guest): void {
    global $conf;
    $sreferer = getReferer();
    $sreqhom = filterText($request);
    $spath = COUNTER_DIR.'/';
    $slog = $spath.'statistic.log';
    $info = getAgentInfo(getAgent(), $guest);
    $rcat = ($guest !== 1) ? getRefCategory($sreferer) : '';
    $ip = getIp();
    $cc = class_exists('Geoip') ? Geoip::getCountry($ip) : '';
    $sid = '';
    if ($guest !== 1 && $guest !== 3) {
        if (isset($_COOKIE['stats_id']) && $_COOKIE['stats_id'] !== '') {
            $sid = (string)$_COOKIE['stats_id'];
        } elseif (!headers_sent()) {
            try {
                $sid = bin2hex(random_bytes(8));
            } catch (Exception) {
                $sid = '';
            }
            if ($sid !== '') {
                setcookie('stats_id', $sid, [
                    'expires' => time() + 31536000,
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }
    }
    $sess = null;
    if ($guest !== 1 && $guest !== 3 && $sid !== '' && is_dir(COUNTER_DIR) && is_writable(COUNTER_DIR)) {
        $sess = updateSessionState($sid, time());
    }
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
            $prev = (isset($con) && ($con[0] ?? '') === date('d.m.Y')) ? $con : [];
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
        } else {
            $check = checkUniqueIp();
            $checku = check_user();
            $shost = ($check) ? intval(($con[1] ?? 0) + 1) : ($con[1] ?? 0);
            $sengine = ($check && $conf['session'] && $guest == 1) ? intval(($con[4] ?? 0) + 1) : ($con[4] ?? 0);
            $srefer = ($check && $sreferer) ? intval(($con[5] ?? 0) + 1) : ($con[5] ?? 0);
            $reqhom = ($sreqhom == '/' || $sreqhom == '/index.html' || $sreqhom == '/index.php') ? intval(($con[6] ?? 0) + 1) : ($con[6] ?? 0);
            $suser = ($checku && $conf['session'] && $guest == 2) ? intval(($con[7] ?? 0) + 1) : ($con[7] ?? 0);
            $wc = $con[0].'|'.$shost.'|'.intval(($con[2] ?? 0) + 1).'|'.intval(($con[3] ?? 0) + 1).'|'.$sengine.'|'.$srefer.'|'.$reqhom.'|'.$suser;
            $prev = (isset($con) && ($con[0] ?? '') === date('d.m.Y')) ? $con : [];
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
        }
        $fps = $safeOpen($spath.'statistic.log', 'wb');
        if ($fps && flock($fps, LOCK_EX)) {
            ftruncate($fps, 0);
            fwrite($fps, $wc);
            fflush($fps);
            flock($fps, LOCK_UN);
        }
        if ($fps) fclose($fps);
        return;
    }
    if (!file_exists($slog) || filemtime($slog) < strtotime('today midnight')) {
        if (file_exists($spath.'ips.log')) unlink($spath.'ips.log');
        if (file_exists($spath.'user.log')) unlink($spath.'user.log');
        $sengine = ($conf['session'] && $guest == 1) ? '1' : '0';
        $srefer = ($sreferer) ? '1' : '0';
        $reqhom = ($sreqhom == '/' || $sreqhom == '/index.html' || $sreqhom == '/index.php') ? '1' : '0';
        $wc = date('d.m.Y').'|0|1|1|'.$sengine.'|'.$srefer.'|'.$reqhom.'|0';
        $prev = [];
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
        $fps = $safeOpen($slog, 'wb');
        if ($fps && flock($fps, LOCK_EX)) {
            fwrite($fps, $wc);
            fflush($fps);
            flock($fps, LOCK_UN);
        }
        if ($fps) fclose($fps);
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
    return $vars;
}

# Build one public site URL from normalized route parameters
function getPublicUrl(array $vars = []): string {
 global $conf;
    $base = rtrim((string)($conf['homeurl'] ?? ''), '/');
    if ($vars === []) return $base;
    $path = ltrim(getSeoUrl($vars), '/');
    return $base.'/'.$path;
}

# Resolve canonical URL and robots defaults for the current frontend request
function getSeoRoute(array $seo = []): array {
    $vars = filterCanonicalParams();
    $name = $vars['name'] ?? '';
    $robot = trim((string)($seo['robots'] ?? ''));
    $canon = trim((string)($seo['canon'] ?? ''));
    $iscanon = $name !== 'search';
    if ($robot === '') {
        $robot = ($name === 'search') ? 'noindex, follow' : 'index, follow';
    }
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
    ];
}

# Format head
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
    if ($conf['referers']['refer']) updateRefererTrack($ctime, $request, $uname);
    if ($conf['statistic']['stat']) updateStatsTrack($request, $guest);
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
            $cacheurl = CACHE_DIR.'/'.md5($url).'.txt';
            if (file_exists($cacheurl) && filesize($cacheurl) != 0 && ($ctime - $conf['cache_t']) < filemtime($cacheurl)) {
                readfile($cacheurl);
                exit;
            }
        }
    }
    if (defined('ADMIN_FILE') && ($conf['lic_h'] != 'UG93ZXJlZCBieSA8YSBocmVmPSJodHRwczovL3NsYWVkLm5ldCIgdGFyZ2V0PSJfYmxhbmsiIHRpdGxlPSJTTEFFRCBDTVMiPlNMQUVEIENNUzwvYT4gJmNvcHk7IDIwMDUt' || $conf['lic_f'] != 'IFNMQUVELiBBbGwgcmlnaHRzIHJlc2VydmVkLg==')) setExit(_NO_LICENSE);
    $licens = base64_decode($conf['lic_h']).date('Y').base64_decode($conf['lic_f']);
    $strmeta = '<meta charset="'._CHARSET.'">'."\n";
    $strlink = $stscript = '';
    $sep = urldecode($conf['defis']);
    if (!defined('ADMIN_FILE')) {
        $seomap = getSeoRoute($seo);
        $atime  = date('Y-m-d H:i:s');
        $time   = $seo['time']   ?? $atime;
        $mtime  = $time;
        $title    = $seo['title']  ?? $conf['sitename'];
        $headline = $title;
        $desc   = $seo['desc']   ?? $conf['slogan'];
        $img    = ($seo['img'] ?? '') ?: $conf['homeurl'].'/templates/'.$theme.'/images/logos/'.$conf['site_logo'];
        $ctitle = $seo['ctitle'] ?? '';
        $author = $seo['author'] ?? $conf['sitename'];
        $purl = $seomap['siteurl'];
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
        .'<meta name="robots" content="'.$seomap['robot'].'">'."\n"
        .'<meta name="revisit-after" content="1 days">'."\n"
        .'<meta name="rating" content="general">'."\n"
        .'<meta name="generator" content="SLAED CMS">'."\n";
        $from = ['[homeurl]', '[site]', '[logo]', '[loc]', '[time]', '[mtime]', '[title]', '[desc]', '[img]', '[ctitle]', '[type]', '[url]', '[headline]', '[author]'];
        $into = [$conf['homeurl'], $conf['sitename'], $conf['homeurl'].'/templates/'.$theme.'/images/logos/'.$conf['site_logo'], _LOCALE, date('c', strtotime($time)), date('c', strtotime($mtime)), $title, $desc, $img, $ctitle, $type, $purl, $headline, $author];
        if (!empty($conf['agraph']) && !empty($conf['graph'])) {
            $strmeta .= str_replace($from, $into, $conf['graph']);
        }
        $favicon = 'templates/'.$theme.'/images/favicon.svg';
        if (is_file(BASE_DIR.'/'.$favicon)) {
            $strlink .= $tpl->getHtmlFrag('head-link', ['rel' => 'shortcut icon', 'href' => $favicon, 'type' => 'image/svg+xml', 'title' => ''])."\n";
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
    } else {
        $strmeta .= '<title>'.$conf['sitename'].' '.$sep.' '._ADMIN.'</title>'."\n";
    }
    $strlink .= doCss();
    if (!defined('ADMIN_FILE') && !empty($conf['aschema']) && !empty($conf['schema'])) {
        $stscript = str_replace($from, $into, $conf['schema']);
    }
    $script = (defined('ADMIN_FILE') || empty($conf['script_b'])) ? doScript()."\n".$stscript : $stscript;
    if (defined('ADMIN_FILE')) {
        $adlogo = basename((string)($conf['admin_logo'] ?? 'slaed_logo_256x73.png'));
        $adpath = img_find('logos/'.$adlogo);
        if (!is_file($adpath)) $adpath = img_find('logos/slaed_logo_256x73.png');
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
    $surl = addSchedulerTrigger();
    if ($surl !== '') {
        $script .= $tpl->getHtmlFrag('head-script-inline', ['js' => 'window.addEventListener("load",function(){window.setTimeout(function(){fetch("'.$surl.'",{credentials:"same-origin"});},1);});']);
    }
    $login = '';
    if (is_user()) {
        $uname = htmlspecialchars(substr((string)$user[1], 0, 25), ENT_QUOTES, 'UTF-8');
        $userinfo = getUserInfo();
        $avpath = BASE_DIR.'/'.$conf['users']['adirectory'].'/'.($userinfo['avatar'] ?? '');
        $avatar = (!empty($userinfo['avatar']) && is_file($avpath)) ? $userinfo['avatar'] : 'default/00.gif';
        $items = [
            $tpl->getHtmlFrag('link', ['href' => 'index.php?name=account', 'title' => _ACCOUNT, 'img_src' => $conf['users']['adirectory'].'/'.$avatar, 'img_alt' => _ACCOUNT, 'label' => $uname, 'is_login_profile' => true, 'is_login_avatar' => true, 'is_bold_label' => true]),
            $tpl->getHtmlFrag('link', ['href' => 'index.php?name=account&amp;op=logout&amp;refer=1', 'title' => _LOGOUT, 'label' => _LOGOUT]),
        ];
        $html = '';
        foreach ($items as $item) {
            $html .= $tpl->getHtmlFrag('list-item', ['content_html' => $item]);
        }
        $login = $tpl->getHtmlFrag('list', ['is_unordered' => true, 'is_login_top' => true, 'is_logged' => true, 'items_html' => $html]);
    } elseif ($conf['users']['enter']) {
        $gfx = (int)($conf['gfx_chk'] ?? 0);
        $captcha = in_array($gfx, [2, 4, 5, 7], true) ? getCaptcha(2) : '';
        $atok = htmlspecialchars(getSiteToken('account'), ENT_QUOTES, 'UTF-8');
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
            'submit_button' => ['button_type' => 'submit', 'label' => _LOGIN, 'title' => _LOGIN, 'is_login_submit' => true],
            'lost_link' => ['href' => 'index.php?name=account&amp;op=passlost', 'title' => _PASSFOR, 'label' => _PASSFOR],
            'register_link' => ['href' => 'index.php?name=account&amp;op=newuser', 'title' => _REG, 'label' => _REG, 'is_account_button' => true],
            'token_field' => ['name_attr' => 'token', 'value_attr' => $atok],
            'refer_field' => ['name_attr' => 'refer', 'value_attr' => '1'],
            'op_field' => ['name_attr' => 'op', 'value_attr' => 'login'],
        ]);
    } else {
        $item = $tpl->getHtmlFrag('link', ['href' => 'index.php?name=account', 'title' => _BREG, 'label' => _BREG, 'is_login_button' => true, 'is_bold_label' => true]);
        $login = $tpl->getHtmlFrag('list', ['is_unordered' => true, 'is_login_top' => true, 'items_html' => $tpl->getHtmlFrag('list-item', ['content_html' => $item])]);
    }
    $sitevars = [
        'theme' => getTheme(),
        'lang' => substr(_LOCALE, 0, 2),
        'sitename' => $conf['sitename'] ?? '',
        'logo' => $conf['site_logo'] ?? '',
        'homeurl' => $conf['homeurl'] ?? '',
        'slogan' => $conf['slogan'] ?? '',
        'license' => $licens,
        'meta' => $strmeta,
        'links' => $strlink,
        'scripts' => $script,
        'content' => '',
        'head_html' => $login,
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
    update_points(1);
    return;
}

# Format foot
function setFoot(): void {
    global $home, $name, $conf, $tpl, $adminpage, $adminvars, $sitepage, $sitevars, $blocks, $blocks_c, $foot;
    if (defined('ADMIN_FILE')) {
        $vars = is_array($adminvars ?? null) ? $adminvars : [];
        $vars['content'] = getFlashHtml().((ob_get_level() > 0) ? (string)ob_get_clean() : '');
        $time = ($conf['db_t'] == '1') ? getTimeLoads() : '';
        $cvar = explode(',', $conf['variables']);
        $debug = (!$cvar[0] && ($conf['var_view'] || (isAdmin() && !$conf['var_view']))) ? getVariables() : '';
        $vars = array_replace($vars, [
            'time_html' => $time,
            'foot_html' => getFootControls(_PAGETOP, _PAGETOP, '', '', '', true, $debug !== ''),
            'debug_html' => $debug,
        ]);
        $page = (is_string($adminpage ?? '') && $adminpage !== '') ? $adminpage : 'admin';
        echo $tpl->getHtmlPage($page, $vars, $page === 'login' ? 'bare' : 'admin');
        unset($adminpage, $adminvars);
        return;
    }
    $vars = is_array($sitevars ?? null) ? $sitevars : [];
    $body = (ob_get_level() > 0) ? (string)ob_get_clean() : '';
    $time = ($conf['db_t'] == '1') ? getTimeLoads() : '';
    $cvar = explode(',', $conf['variables']);
    $debug = (!$cvar[0] && ($conf['var_view'] || (isAdmin() && !$conf['var_view']))) ? getVariables() : '';
    $license = !empty($vars['license']) ? (string)$vars['license'] : '';
    getBlocks('f');
    $foot = getFootControls(_PAGETOP, _PAGETOP, $time, $license);
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
        'foot_html' => $foot,
        'debug_html' => $debug,
    ]);
    $vars = array_replace($vars, getThemeHookVars('getThemeFootVars'));
    $page = (is_string($sitepage ?? '') && $sitepage !== '') ? $sitepage : ($home ? 'home' : 'module');
    echo $tpl->getHtmlPage($page, $vars, $page === 'home' ? 'home' : 'app');
    unset($sitepage, $sitevars);
    if ((!defined('ADMIN_FILE') && $conf['cache'] == 1) || (!defined('ADMIN_FILE') && $conf['cache'] == 2 && $home)) {
        $dir = CACHE_DIR;
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

# Store one-time flash message in session
function setFlash(string $text, bool $warn = false): void {
    if ($text === '' || session_status() !== PHP_SESSION_ACTIVE) return;
    $_SESSION['slaed_flash'] = ['text' => $text, 'warn' => $warn ? 1 : 0];
}

# Render and clear one-time flash message
function getFlashHtml(): string {
    global $tpl;
    if (session_status() !== PHP_SESSION_ACTIVE) return '';
    $data = $_SESSION['slaed_flash'] ?? null;
    if (!is_array($data)) return '';
    unset($_SESSION['slaed_flash']);
    $text = (string)($data['text'] ?? '');
    if ($text === '') return '';
    return $tpl->getHtmlFrag('alert', [
        'is_warn' => !empty($data['warn']),
        'is_flash' => true,
        'alert_attr' => 'data-sl-autohide="15000"',
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

# Add file errors to error_file.log
function addErrorFile(string $msg): bool {
    return class_exists('Logger') ? Logger::addFile('error', $msg) : false;
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
 global $db, $user, $conf, $locale, $tpl;
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
                        $is_hidden = false;
                        $href = getSeoUrl(['name' => $mod, 'cat' => $val[0]]);
                        $isrc = $val[3] ? img_find('categories/'.$val[3]) : '';
                        $ilink = $tpl->getHtmlFrag('link', ['href' => $href, 'title' => $val[1], 'is_cat_image' => true, 'img_src' => $isrc, 'img_alt' => $val[1]]);
                        $alink = $tpl->getHtmlFrag('link', ['href' => $href, 'title' => $val[1], 'label' => $val[1], 'is_cat_name' => true]);
                    } else {
                        $is_hidden = true;
                        $htitle = $val[1].' - '._CCLOSED;
                        $isrc = $val[3] ? img_find('categories/'.$val[3]) : '';
                        $ilink = $tpl->getHtmlFrag('span', ['title' => $htitle, 'is_cat_image' => true, 'img_src' => $isrc, 'img_alt' => $htitle]);
                        $alink = $tpl->getHtmlFrag('span', ['title' => $val[1], 'text' => $val[1], 'is_cat_name' => true]);
                    }
                    $subcat = '';
                    foreach ($massiv as $sval) {
                        if ($val[0] == $sval[4] && is_acess($sval[5])) {
                            $catid[] = $sval[0];
                            if ($sub == 1) {
                                $sval[1] = getConst($sval[1]);
                                $shref = getSeoUrl(['name' => $mod, 'cat' => $sval[0]]);
                                $sublink = is_acess($sval[6]) ? $tpl->getHtmlFrag('link', ['href' => $shref, 'title' => $sval[1], 'label' => $sval[1], 'is_category' => true]) : '';
                                $subcat .= $tpl->getHtmlFrag('block-content', ['content' => $sublink]);
                            }
                        }
                    }
                    $description = $desc ? $val[2] : '';
                    $cont .= $tpl->getHtmlFrag('category-row', [
                        'description_text' => $description,
                        'image_html' => $ilink,
                        'is_hidden' => $is_hidden,
                        'subitems_html' => $subcat,
                        'title_html' => $alink,
                    ]);
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
            return $tpl->getHtmlPart('categories', ['categories' => _CATEGORIES, 'content' => $cont, 'total' => _ALLIN, 'pages' => $pnum, 'in' => $in, 'cat' => $cnum, 'category' => _ALLINC, 'mod' => $mod]);
        }
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
    readfile(CACHE_DIR.'/'.md5(getTheme().'script').'.txt');
}

# Set cached CSS file
function setCss(): void {
    header('Content-type: text/css');
    readfile(CACHE_DIR.'/'.md5(getTheme().'style').'.txt');
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
    $export = function (array $arr, int $dep = 0) use (&$export): string {
        $pad = str_repeat('    ', $dep);
        $ind = $pad.'    ';
        $out = '['.PHP_EOL;
        foreach ($arr as $key => $val) {
            $out .= $ind.var_export($key, true).' => ';
            $out .= is_array($val) ? $export($val, $dep + 1) : var_export($val, true);
            $out .= ','.PHP_EOL;
        }
        return $out.$pad.']';
    };
    $cnt = '<?php'.PHP_EOL
    .'# Author: Eduard Laas'.PHP_EOL
    .'# Copyright © 2005 - '.date('Y').' SLAED'.PHP_EOL
    .'# License: GNU GPL 3'.PHP_EOL
    .'# Website: slaed.net'.PHP_EOL.PHP_EOL
    .'return '.$export($data).';'.PHP_EOL;
    file_put_contents($fp, $cnt, LOCK_EX);
    @unlink(CONFIG_DIR.'/local.php');
    getConfig();
}

# Returns ordered list of base stylesheet paths for a theme, alphabetical by filename
function getThemeCssFiles(string $theme): array {
    $dir = 'templates/'.$theme.'/assets/css/';
    $out = glob($dir.'*.css') ?: [];
    sort($out);
    return $out;
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
function doScript(): string {
 global $theme, $conf, $tpl;
    $async = ($conf['script_a']) ? 'async ' : '';
    $sfile = CACHE_DIR.'/'.md5($theme.'script').'.txt';
    $entries = explode(',', $conf['script_f']);
    $entries = is_array($entries) ? $entries : [];
    $array = array_merge(getAssetFiles($entries, 'js'), getThemeAssets($theme, 'js'));
    $array = array_values(array_unique($array));
    if (!defined('ADMIN_FILE')) {
        if ($conf['cache_script'] && file_exists($sfile) && filesize($sfile) != 0 && (time() - $conf['cache_t']) < filemtime($sfile)) {
            $cont = ($conf['script_h']) ? file_get_contents($sfile) : $tpl->getHtmlFrag('head-script-src', ['src' => 'index.php?go=script', 'attr' => trim($async)]);
        } else {
            foreach ($array as $file) {
                if (file_exists($file)) {
                    if ($conf['cache_script'] || $conf['script_h']) {
                        $cont = file_get_contents($file);
                        $arr[] = ($conf['script_c']) ? getCompressCode($cont) : $cont;
                    } else {
                        $arr[] = $tpl->getHtmlFrag('head-script-src', ['src' => $file, 'attr' => trim($async)]);
                    }
                }
            }
            $cont = ($conf['script_h']) ? $tpl->getHtmlFrag('head-script-inline', ['js' => implode(' ', $arr)]) : (($conf['cache_script']) ? implode(' ', $arr) : implode("\n", $arr));
            if ($conf['cache_script']) {
                file_put_contents($sfile, $cont);
                $cont = (file_exists($sfile) && !$conf['script_h']) ? $tpl->getHtmlFrag('head-script-src', ['src' => 'index.php?go=script', 'attr' => trim($async)]) : $cont;
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
    $entries = explode(',', str_replace('[theme]', $theme, $conf['css_f']));
    $array = array_merge(
        getAssetFiles(is_array($entries) ? $entries : [], 'css'),
        getThemeAssets($theme, 'css')
    );
    $array = array_values(array_unique($array));
    if (is_array($array)) {
        if (!defined('ADMIN_FILE')) {
            $cfile = CACHE_DIR.'/'.md5($theme.'style').'.txt';
            $bundle = !empty($conf['cache_css']) || !empty($conf['css_h']);
            if ($bundle && file_exists($cfile) && filesize($cfile) != 0 && (time() - $conf['cache_t']) < filemtime($cfile)) {
                $cont = $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => 'index.php?go=css', 'type' => '', 'title' => '']);
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
                            $arr[] = ($conf['css_c']) ? getCompressCss($cont) : $cont;
                        } else {
                            $arr[] = $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => $file, 'type' => '', 'title' => '']);
                        }
                    }
                }
                $cont = $bundle ? implode(' ', $arr) : implode("\n", $arr);
                if ($bundle) {
                    file_put_contents($cfile, $cont);
                    $cont = file_exists($cfile) ? $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => 'index.php?go=css', 'type' => '', 'title' => '']) : '';
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
    } else {
        $cont = '';
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
    $tlinks = implode('', array_map(static function($p) use ($tpl, $pref, $id): string {
        $label = preg_match('/<[^>]+>/', $p['tab']) ? $p['tab'] : htmlspecialchars($p['tab'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $link = $tpl->getHtmlFrag('link', [
            'href' => '#'.$pref.'_'.$id.'_'.$p['id'],
            'title' => strip_tags((string)$p['tab']),
            'label_html' => $label,
        ]);
        return $tpl->getHtmlFrag('list-item', ['content_html' => $link]);
    }, $pairs));
    $cdivs = implode('', array_map(static fn($p): string => $tpl->getHtmlFrag('block-content', ['id' => $pref.'_'.$id.'_'.$p['id'], 'content' => $p['cont']]), $pairs));
    return $tpl->getHtmlFrag('navi-tabs-wrap', ['tabs_html' => $tlinks, 'content_html' => $cdivs, 'id' => $id]);
}

# Transliteration
function getTranslit(string $st, string $lo = ''): string {
    $st = strtr($st, ['а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'g', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'ы' => 'i', 'э' => 'e', 'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ж' => 'G', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Ы' => 'I', 'Э' => 'E', 'ё' => 'yo', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ь' => '', 'ъ' => '', 'ю' => 'yu', 'я' => 'ya', 'Ё' => 'Yo', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch', 'Ь' => '', 'Ъ' => '', 'Ю' => 'Yu', 'Я' => 'Ya']);
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
 global $conf, $tpl;
    if ($conf['gfx_chk'] >= '1' && ($id == 2 || ($id == 1 && !is_user()))) {
        $cont = $tpl->getHtmlFrag('head-script-src', ['src' => 'https://www.google.com/recaptcha/api.js?render='.$conf['capkey'], 'attr' => ''])
            ."\n        ".$tpl->getHtmlFrag('head-script-inline', ['js' => 'grecaptcha.ready(function() { grecaptcha.execute("'.$conf['capkey'].'", { action: "homepage" }) .then(function(token) { document.getElementById("recaptcha").value = token; }); });']);
        $cont .= $tpl->getHtmlFrag('hidden', ['name_attr' => 'recaptcha', 'value_attr' => '', 'input_attr' => 'id="recaptcha"']);
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
function getVotingView(int $id = 0, string $votid = '', bool $forceResult = false): string {
 global $db, $afile, $user, $locale, $conf, $tpl;
    if ($forceResult) {
        $querylang = '1 = 1';
        $qlang_params = [];
    } elseif ($conf['multilingual'] == 1) {
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
        $rate = ($forceResult || $cookies == $id || $num > 0 || strtotime($enddate) <= time()) ? 1 : 0;
        if ($forceResult || $typ || !$typ && !$rate) {
            $body = explode('|', $body);
            $answer = explode('|', $answer);
            $vote = array_sum($answer);
            $items = '';
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
                    $voteField = ($itype === 'checkbox')
                        ? $tpl->getHtmlFrag('checkbox', ['name_attr' => 'body[]', 'value_attr' => (string)$n, 'label_html' => $body[$i], 'is_plain' => true])
                        : $tpl->getHtmlFrag('radio', ['name_attr' => 'body[]', 'value_attr' => (string)$n, 'label_html' => $body[$i]]);
                    $items .= $tpl->getHtmlFrag('list-item', ['content_html' => $voteField]);
                } else {
                    $items .= $tpl->getHtmlFrag('voting-view', ['text' => $body[$i], 'text_safe' => filterText($body[$i]), 'n' => $n, 'pn' => $pn, 'percent' => $procent, 'votes_label' => _VOTES, 'votes' => $answer[$i]]);
                }
            }
            list($vnum) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_voting WHERE '.$querylang, $qlang_params));
            if (!$forceResult && is_moder('voting') && $votid == 'voting') {
                $items = [
                    $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=voting&amp;op=add&amp;id='.$id, 'title' => _FULLEDIT, 'label' => _FULLEDIT]),
                    $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=voting&amp;op=delete&amp;id='.$id.'&amp;refer=1', 'confirm_text' => _DELETE.' "'.$title.'"?', 'title' => _ONDELETE, 'label' => _ONDELETE, 'is_delete' => true]),
                ];
                $admin = $tpl->getHtmlFrag('editor-action-menu', ['editor_label' => _EDITOR, 'items_html' => implode('', array_map(fn($item) => $tpl->getHtmlFrag('list-item', ['content_html' => $item]), $items))]);
            } else {
                $admin = '';
            }
            $post = (!$forceResult && !$rate) ? $tpl->getHtmlFrag('comment-action-ajax', ['target' => $votid, 'query' => 'go=1&amp;op=updateVotingResult&amp;id='.$id.'&amp;votid='.$votid, 'title' => _VOTE, 'label' => _VOTE, 'is_button_blue' => true]) : '';
            $polls = (!$forceResult && $vnum > 1) ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name=voting', 'title' => _POLLS, 'label' => _POLLS, 'is_account_button' => true]) : '';
            $votes = $forceResult ? '' : ((!$modul && $votid != 'voting') ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name=voting&amp;op=view&amp;id='.$id, 'title' => _VOTES, 'label' => _VOTES.': '.$vote, 'is_votes' => true]) : $tpl->getHtmlFrag('span', ['is_votes' => true, 'text' => _VOTES.': '.$vote]));
            $comm = (!$forceResult && !$modul && $acomm) ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name=voting&amp;op=view&amp;id='.$id.'#'.$id, 'title' => _COMMENTS, 'label' => _COMMENTS.': '.$comments, 'is_comments' => true]) : '';
            $cont = $tpl->getHtmlPart('voting-widget', [
                'has_form'   => !$rate,
                'form_id'    => 'form'.$votid,
                'title'      => $title,
                'items_html' => $items,
                'admin_html' => $admin,
                'post_html'  => $post,
                'polls_html' => $polls,
                'votes_html' => $votes,
                'comm_html'  => $comm,
            ]);
        } else {
            $cont = $tpl->getHtmlFrag('alert', ['text' => _VCLINFO, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
        }
    } else {
        $cont = $tpl->getHtmlFrag('alert', ['text' => _NO_INFO, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
    }
    return $cont;
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

# Returns rendered system debug information
function getDebugSystemInfo(): string {
    global $tpl, $db, $sgtime;
    $max = [
        'mem' => getMemoryLimitBytes(),
        'gen' => 2.0,
        'qnum' => 50,
        'qtime' => 0.010,
    ];
    $metric = static function (float $value, float $max): array {
        $percent = ($max > 0) ? ($value * 100 / $max) : 0.0;
        $percent = min(100.0, max(0.0, $percent));
        $state = 'info';
        $prog = '1';
        if ($percent <= 50) {
            $state = 'success';
            $prog = '2';
        } elseif ($percent > 75 && $percent <= 95) {
            $state = 'warn';
            $prog = '3';
        } elseif ($percent > 95) {
            $state = 'danger';
            $prog = '4';
        }
        return [
            'percent' => number_format($percent, 1, '.', ''),
            'progress' => $prog,
            'is_success' => $state === 'success',
            'is_info' => $state === 'info',
            'is_warn' => $state === 'warn',
            'is_danger' => $state === 'danger',
        ];
    };
    $memuse = memory_get_usage();
    $gentime = microtime(true) - $sgtime;
    $sqltime = (float)$db->sqltime;
    $qnum = (int)$db->qnum;
    $avg = ($qnum > 0) ? ($sqltime / $qnum) : 0.0;
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
        $text = $row['time'].' '.$row['chan'].': '.$row['msg'].(($row['url'] !== '') ? ' - '.$row['url'] : '');
        $html .= $tpl->getHtmlFrag('list-item', ['content_html' => $tpl->getHtmlFrag('span', ['text' => $text])]);
    }
    return $tpl->getHtmlFrag('list', ['is_unordered' => true, 'items_html' => $html]);
}

# Variable analyzer
function getVariables(): string {
    global $db, $conf, $tpl;
    $cont = '';
    $cvar = explode(',', $conf['variables']);
    $rows = [];
    if ($cvar[1]) {
        $rows[] = ['legend' => _SYSTEM_INFO, 'tone' => 'info', 'content' => getDebugSystemInfo()];
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
    if ($cvar[8]) $rows[] = ['legend' => _AQUERY_DB, 'tone' => 'success', 'content' => $db->qtime];
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
    if (!is_user()) return $cached = $default;
    $utheme = $user[5] ?? '';
    if ($utheme !== '' && is_dir(BASE_DIR.'/templates/'.$utheme)) return $cached = $utheme;
    return $cached = $default;
}

# Format theme file
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
 global $db, $conf, $prs;
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
            foreach ($outmail as $val) addMail($val, $conf['adminmail'], $title, $prs->filterContent($body, false, 'all'), 0, 3);
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
    return file_exists(img_find($path)) ? img_find($path) : img_find('flags/unknown.svg');
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
    $key ??= getEditorKey();
    return in_array($key, ['ckeditor', 'tinymce'], true);
}

# Resolve content storage format for the selected editor
function getEditorMode(?string $key = null): string {
    $key ??= getEditorKey();
    return match ($key) {
        'ckeditor', 'tinymce' => 'html',
        'toastui' => 'markdown',
        default => 'plain',
    };
}

# Replace break
function replace_break(string $text): string {
 global $conf;
    if ($text) {
        $out = !checkHtmlEditor() ? preg_replace('#<br.*>#i', '', $text) : $text;
        return $out;
    }
    return '';
}

# User information for user
function getUserSessionInfo(string $id = ''): string {
 global $db, $conf, $tpl;
    if ($conf['session']) {
        $who_online = ''; $m = 0; $b = 0; $u = 0; $i = 0;
        $result = $db->getSqlQuery('SELECT uname, time, ip, guest, modul FROM '.PREFIX_DB.'_session ORDER BY uname');
        while (list($uname, $time, $host, $guest, $module) = $db->getSqlRow($result)) {
            $time = time() - $time;
            $strip = cutstr($uname, 15);
            $module = getModuleName($module);
            $linkstrip = cutstr($module, 15);
            if ($guest == 2) {
                $who_online .= $tpl->getHtmlFrag('session-row', [
                    'geo_html' => Geoip::getFlagHtml($host),
                    'name_href' => 'index.php?name=account&amp;op=view&amp;uname='.urlencode($uname),
                    'name_title' => getDuration($time),
                    'name_text' => $strip,
                    'name_link' => ['href' => 'index.php?name=account&amp;op=view&amp;uname='.urlencode($uname), 'title' => getDuration($time), 'label' => $strip],
                    'module_title' => $module,
                    'module_text' => $linkstrip,
                ]);
                $m++;
            } elseif ($guest == 1 && $conf['botsact']) {
                $who_online .= $tpl->getHtmlFrag('session-row', [
                    'geo_html' => Geoip::getFlagHtml($host),
                    'name_title' => getDuration($time),
                    'name_text' => $strip,
                    'is_name_note' => true,
                    'module_title' => $module,
                    'module_text' => $linkstrip,
                ]);
                $b++;
            } else {
                $who_online .= '';
                $u++;
            }
            $i++;
        }
        $content = $tpl->getHtmlPart('session-summary', [
            'members_label' => _BMEM,
            'members_count' => $m,
            'show_bots' => $conf['botsact'],
            'bots_label' => _BOTS,
            'bots_count' => $b,
            'visitors_label' => _BVIS,
            'visitors_count' => $u,
            'overall_label' => _OVERALL,
            'overall_count' => $i,
            'update_title' => _UPDATE,
            'update_label' => _UPDATE,
            'toggle_title' => _READMORE,
            'toggle_label' => _READMORE,
            'rows_html' => $who_online,
            'has_rows' => $who_online !== '',
            'toggle_id' => 'u-block',
            'update_target' => 'sinfo',
            'update_query' => 'go=1&amp;op=getUserSessionInfo&amp;token='.getSiteToken(),
        ]);
        if ($id) { return $content; } else { echo $content; }
    }
    return '';
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
            $alink = htmlspecialchars(urldecode($url));
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
                    'name_link'   => ['href' => 'index.php?name=account&amp;op=view&amp;uname='.urlencode($uname), 'title' => getDuration($time).' - '._IP.': '.$host, 'label' => $namestrip, 'is_blank' => true],
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
            'admins_icon_name'   => 'shield-check',
            'members_icon_name'  => 'person-check',
            'bots_icon_name'     => 'robot',
            'visitors_icon_name' => 'eye',
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
            'update_query' => 'go=5&amp;op=getUserSessionAdminInfo&amp;token='.getSiteToken(),
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
        return $tpl->getHtmlPart('block-sidebar', ['title' => $a_title, 'icon_name' => 'shield-lock', 'content_html' => $cont, 'id' => '7', 'close' => $cltit])
            .$tpl->getHtmlPart('block-sidebar', ['title' => _WHO, 'icon_name' => 'eye', 'content_html' => getUserSessionAdminInfo(1), 'content_id' => 'repsainfo', 'id' => '8', 'close' => $cltit]);
    }
    return '';
}

# User info link
function user_info(string $name): string {
    global $conf, $tpl;
    if (!$name) return '';
    if ($conf['users']['prof'] != 1 || ($conf['users']['prof'] == 1 && is_user()) || isAdmin()) {
        return $tpl->getHtmlFrag('link', [
            'href' => 'index.php?name=account&amp;op=view&amp;uname='.urlencode($name),
            'title' => (string)_PERSONALINFO,
            'label' => $name,
        ]);
    }
    return $name;
}

# Show cart
function getCartSummary(string $info = ''): string {
 global $db, $conf, $tpl;
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
        $rows = '';
        $ptotal = 0;
        while (list($id, $time, $title, $price) = $db->getSqlRow($result)) {
            $i = 0;
            foreach ($massiv as $val) {
                if ($val == $id) $i++;
            }
            $price = $price * $i;
            $ptotal += $price;
            $titlink = $tpl->getHtmlFrag('link', ['href' => 'index.php?name=shop&amp;op=view&amp;id='.$id, 'title' => $title, 'label' => $title]);
            $actions = $tpl->getHtmlFrag('comment-action-ajax', ['target' => 'kasse', 'query' => 'go=2&amp;op=addCartItem&amp;id='.$id, 'title' => _PPLUS, 'label' => '', 'is_cart_plus' => true])
                .$tpl->getHtmlFrag('comment-action-ajax', ['target' => 'kasse', 'query' => 'go=2&amp;op=deleteCartItem&amp;id='.$id, 'title' => ($i > 1) ? _PMINUS : _DELETE, 'label' => '', 'is_cart_minus' => true]);
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
                ['colspan' => 2, 'content_html' => $tpl->getHtmlFrag('link', ['href' => 'index.php?name=shop&amp;op=kasse', 'title' => _SCACH, 'label' => _SCACH, 'is_cart_checkout' => true])],
                ['colspan' => 3, 'content_html' => $tpl->getHtmlFrag('span', ['title' => _PARTNERGES, 'text' => _PARTNERGES.': '.$ptotal.' '.$conf['shop']['valute'], 'is_cart_total' => true])],
            ],
        ]);
        return $tpl->getHtmlFrag('table', [
            'open' => true,
            'title' => _PBASKET,
            'is_cart' => true,
            'headers' => [
                ['text' => _ID, 'is_cart_col_num' => true],
                ['text' => _PRODUCT],
                ['text' => cutstr(_QUANTITY, 3, 1), 'is_cart_col_num' => true],
                ['text' => _PREIS, 'is_cart_col_stat' => true],
                ['text' => _FUNCTIONS, 'is_cart_col_stat' => true],
            ],
        ]).$rows.$footer.$tpl->getHtmlFrag('table', []);
    }
    return '';
}

# Add cart item
function addCartItem(): void {
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
    echo getCartSummary($info);
}

# Delete cart item
function deleteCartItem(): void {
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

# Return upload configuration for a module editor endpoint
function getEditorUploadData(string $mod): array {
    global $conf;
    if ($mod === '' || !isset($conf['uploads'][$mod])) return ['ok' => false, 'error' => 'Upload configuration is missing'];
    $con = explode('|', (string)$conf['uploads'][$mod]);
    $dir = 'uploads/'.$mod;
    $path = UPLOADS_DIR.'/'.$mod;
    if (!is_dir($path)) return ['ok' => false, 'error' => 'Upload directory is missing'];
    return ['ok' => true, 'con' => $con, 'dir' => $dir, 'path' => $path];
}

# Check whether the current visitor may use the module editor upload
function checkEditorUploadAccess(string $mod, array $con): bool {
    if (is_moder($mod)) return true;
    if (is_user() && (int)($con[10] ?? 0) === 1) return true;
    return !is_user() && (int)($con[11] ?? 0) === 1;
}

# Return image metadata for an uploaded or stored editor file
function getEditorImageData(string $file, string $ext, int $wid, int $hei): array {
    $img = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp'], true);
    if (!$img) return ['ok' => true, 'image' => false, 'width' => 0, 'height' => 0, 'error' => ''];
    $inf = @getimagesize($file);
    if (!is_array($inf)) return ['ok' => false, 'image' => true, 'width' => 0, 'height' => 0, 'error' => _ERROR_FILE];
    $one = (int)($inf[0] ?? 0);
    $two = (int)($inf[1] ?? 0);
    if (($wid > 0 && $one > $wid) || ($hei > 0 && $two > $hei)) return ['ok' => false, 'image' => true, 'width' => $one, 'height' => $two, 'error' => _ERROR_SIZE];
    return ['ok' => true, 'image' => true, 'width' => $one, 'height' => $two, 'error' => ''];
}

# Return one stored editor file row for JSON output
function getEditorFileData(string $dir, string $file): array {
    $base = str_replace('\\', '/', BASE_DIR);
    $rel = '';
    $dir = str_replace('\\', '/', $dir);
    if (str_starts_with($dir, $base.'/')) {
        $rel = substr($dir, strlen($base) + 1);
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $img = getEditorImageData($dir.'/'.$file, $ext, 0, 0);
    return [
        'file' => $file,
        'url' => (($rel !== '') ? $rel : basename($dir)).'/'.$file,
        'type' => $ext,
        'size' => filterSize(filesize($dir.'/'.$file)),
        'image' => (bool)($img['ok'] ?? false) && (bool)($img['image'] ?? false),
        'width' => (int)($img['width'] ?? 0),
        'height' => (int)($img['height'] ?? 0),
        'time' => filemtime($dir.'/'.$file),
    ];
}

# Upload files for the Toast UI editor and return JSON
function addEditorUpload(): void {
    global $user;
    $mod = strtolower(getVar('get', 'mod', 'var', ''));
    $dat = getEditorUploadData($mod);
    if (!$dat['ok']) getEditorJson(['ok' => false, 'error' => $dat['error']]);
    $con = (array)$dat['con'];
    $dir = (string)($dat['path'] ?? (BASE_DIR.'/'.ltrim(str_replace('\\', '/', (string)$dat['dir']), '/')));
    if (!checkEditorUploadAccess($mod, $con)) getEditorJson(['ok' => false, 'error' => 'Access denied']);
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'upload')) getEditorJson(['ok' => false, 'error' => _TOKENMISS]);
    $upl = $_FILES['file'] ?? [];
    if (!$upl || empty($upl['name'])) getEditorJson(['ok' => false, 'error' => _ERROR_DOWN]);
    $nam = is_array($upl['name']) ? $upl['name'] : [$upl['name']];
    $tmp = is_array($upl['tmp_name']) ? $upl['tmp_name'] : [$upl['tmp_name']];
    $siz = is_array($upl['size']) ? $upl['size'] : [$upl['size']];
    $err = is_array($upl['error']) ? $upl['error'] : [$upl['error']];
    $typ = (string)($con[0] ?? '');
    $max = (int)($con[2] ?? 0);
    $wid = (int)($con[3] ?? 0);
    $hei = (int)($con[4] ?? 0);
    $uid = is_user() ? (int)($user[0] ?? 0) : (int)getVar('get', 'userid', 'num', 0);
    $out = [];
    $bad = [];
    foreach ($nam as $key => $old) {
        if (($err[$key] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($tmp[$key] ?? ''))) {
            $bad[] = _ERROR_DOWN;
            continue;
        }
        if ($max > 0 && (int)($siz[$key] ?? 0) > $max) {
            $bad[] = _ERROR_BIG;
            continue;
        }
        $ext = strtolower(pathinfo((string)$old, PATHINFO_EXTENSION));
        $msg = check_file($ext, $typ);
        if ($msg !== '') {
            $bad[] = $msg;
            continue;
        }
        $img = getEditorImageData((string)$tmp[$key], $ext, $wid, $hei);
        if (!$img['ok']) {
            $bad[] = (string)$img['error'];
            continue;
        }
        $new = $mod.'-'.getPass(10).'-'.$uid.'.'.$ext;
        while (is_file($dir.'/'.$new)) $new = $mod.'-'.getPass(10).'-'.$uid.'.'.$ext;
        if (!move_uploaded_file((string)$tmp[$key], $dir.'/'.$new)) {
            $bad[] = _ERROR_UP;
            continue;
        }
        $out[] = getEditorFileData($dir, $new);
    }
    getEditorJson(['ok' => $out !== [], 'files' => $out, 'errors' => $bad, 'error' => $out ? '' : ($bad[0] ?? _ERROR_DOWN)]);
}

# Return stored files for the Toast UI editor file panel
function getEditorFileJson(): void {
    global $user;
    $mod = strtolower(getVar('get', 'mod', 'var', ''));
    $dat = getEditorUploadData($mod);
    if (!$dat['ok']) getEditorJson(['ok' => false, 'error' => $dat['error']]);
    $con = (array)$dat['con'];
    $dir = (string)($dat['path'] ?? (BASE_DIR.'/'.ltrim(str_replace('\\', '/', (string)$dat['dir']), '/')));
    if (!checkEditorUploadAccess($mod, $con)) getEditorJson(['ok' => false, 'error' => 'Access denied']);
    if (!checkSiteToken(getVar('req', 'token', 'raw', ''), 'upload')) getEditorJson(['ok' => false, 'error' => _TOKENMISS]);
    $uid = is_user() ? (int)($user[0] ?? 0) : 0;
    $all = is_moder($mod);
    $lim = (int)($all ? ($con[8] ?? 0) : ($con[9] ?? 0));
    $row = [];
    foreach (scandir($dir) ?: [] as $file) {
        if ($file === '.' || $file === '..' || $file === 'index.html' || !is_file($dir.'/'.$file)) continue;
        $own = preg_match("#^[a-zA-Z0-9_]+-[a-zA-Z0-9]+-([0-9]+)\\.[a-zA-Z0-9]+$#", $file, $mat) && (int)$mat[1] === $uid;
        if (!$all && !$own) continue;
        $row[] = getEditorFileData($dir, $file);
    }
    usort($row, static fn(array $one, array $two): int => ($two['time'] ?? 0) <=> ($one['time'] ?? 0));
    if ($lim > 0) $row = array_slice($row, 0, $lim);
    getEditorJson(['ok' => true, 'files' => $row]);
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
            ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$mod.'&amp;op=liste&amp;let='.$num, 'title' => (string)$num, 'label_html' => $label])
            : $label;
    }
    $rows[] = $digits;
    $locale = '';
    foreach (preg_split('//u', _ALPHABET, -1, PREG_SPLIT_NO_EMPTY) as $char) {
        $label = $tpl->getHtmlFrag('span', ['text' => $char, 'is_alpha_letter' => true]);
        $locale .= in_array($char, $alpha)
            ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$mod.'&amp;op=liste&amp;let='.urlencode($char), 'title' => $char, 'label_html' => $label])
            : $label;
    }
    $rows[] = $locale;
    if (substr(_LOCALE, 0, 2) != 'fr') {
        $latin = '';
        foreach (range('A', 'Z') as $eng) {
            $label = $tpl->getHtmlFrag('span', ['text' => $eng, 'is_alpha_letter' => true]);
            $latin .= in_array($eng, $alpha)
                ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$mod.'&amp;op=liste&amp;let='.$eng, 'title' => $eng, 'label_html' => $label])
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

# Format admin menu
function add_menu(string $links): string {
    global $tpl;
    if ($links) {
        $items = explode('||', $links);
        $html = implode('', array_map(fn($v) => $v !== '' ? $tpl->getHtmlFrag('list-item', ['content_html' => $v]) : '', $items));
        return $tpl->getHtmlFrag('editor-action-menu', ['editor_label' => (string)_EDITOR, 'items_html' => $html]);
    }
    return '';
}

# Admin status
function ad_status(mixed $link, mixed $id, string $typ = '', string $text = ''): string {
    global $tpl;
    if ($typ) {
        return ($id)
            ? $tpl->getHtmlFrag('inline-badge', ['title_text' => _PROLD, 'is_status_active' => true, 'label' => ''])
            : $tpl->getHtmlFrag('inline-badge', ['title_text' => _PROUTNEW, 'is_status_inactive' => true, 'label' => '']);
    } elseif ($link) {
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

# Find img
function img_find(string $img): string {
    static $base;
    if (!$base) $base = 'templates/'.getTheme().'/images/';
    return $base.$img;
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
                if (!$id) {
                    $sourceLink = $tpl->getHtmlFrag('link', ['href' => htmlspecialchars($url), 'title' => _RSS_FROM.': '.$title, 'label' => $title, 'is_blank' => true]);
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
function getUserList(): void {
 global $db;
    $let = analyze_name(getVar('get', 'term', 'text', ''));
    $name = [];
    if ($let) {
        $result = $db->getSqlQuery('SELECT name FROM '.PREFIX_DB.'_users WHERE name LIKE :name ORDER BY name ASC', ['name' => $let.'%']);
        while (list($user_name) = $db->getSqlRow($result)) $name[] = $user_name;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($name, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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
 global $conf, $tpl;
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
        $format = $tpl->getHtmlFrag('table', ['open' => true, 'is_form' => true]).str_replace('&nbsp;&nbsp;', '&nbsp; ', $rows).$tpl->getHtmlFrag('table', []);
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
            return $tpl->getHtmlFrag('block-all', ['title' => $blocktitle, 'content' => $content]);
            break;
            default:
            echo $tpl->getHtmlFrag('block-all', ['title' => $blocktitle, 'content' => $content]);
            break;
        }
    } else {
        rss_load($bid);
    }
    return '';
}

# Format rating
function getRatingView(): void {
 global $db, $conf, $user, $tpl;
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
            echo getRatingAsync(2, '', '', $votes, $totalvotes, '', $stl);
        } elseif (!$cookies && !$num && !$rate) {
            list($votes, $totalvotes) = $db->getSqlRow($db->getSqlQuery($query, ['id' => $id]));
            if (intval($votes)) {
                $votnum = $votes;
                $votes = $votes;
            } else {
                $votnum = 0;
                $votes = 1;
            }
            $width = (int) max(0, min(100, round(($totalvotes / $votes) * 20)));
            $result = substr($totalvotes / $votes, 0, 4);
            if (intval($votes) && intval($totalvotes)) {
                $title = _RATING.': '.$result.'/'.$votes.' '._AVERAGESCORE.': '.$result;
                $has_score = true;
            } else {
                $title = _RATING.': 0/0 '._AVERAGESCORE.': 0';
                $has_score = false;
            }
            if ($stl == 1) {
                echo $tpl->getHtmlFrag('rating-like', [
                    'result' => $result,
                    'title' => $title,
                    'has_score' => $has_score,
                    'target_id' => $id.$typ,
                    'rate1_query' => 'go=1&amp;op=getRatingView&amp;id='.$id.'&amp;typ='.$typ.'&amp;mod='.$mod.'&amp;rate=1&amp;stl=1',
                    'rate5_query' => 'go=1&amp;op=getRatingView&amp;id='.$id.'&amp;typ='.$typ.'&amp;mod='.$mod.'&amp;rate=5&amp;stl=1',
                    'rate1_title' => _RATE1,
                    'rate5_title' => _RATE5,
                    'is_live' => true,
                ]);
            } else {
                echo $tpl->getHtmlFrag('rating-bar', [
                    'title' => $title,
                    'width' => (string) $width,
                    'target_id' => $id.$typ,
                    'hover_query' => 'go=1&amp;op=getRatingView&amp;id='.$id.'&amp;typ='.$typ.'&amp;mod='.$mod,
                    'has_score' => $has_score,
                    'votes' => (string)$votnum,
                    'votes_title' => _VOTES,
                    'is_live' => true,
                ]);
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
            echo getRatingAsync(2, '', '', $votes, $totalvotes, '', $stl);
        }
    }
}

# Format nummer page for Ajax
function getAsyncPager(string $frag, int $count, int $pages, int $page, int $mnum = 8, int $num = 1, string $ld = '', int $go = 0, string $op = '', string $id = '', int $cid = 0, string $typ = '', string $mod = ''): string {
    global $tpl;
    $nnum = $mnum + 1;
    $pagerLink = static function(string $query, string $target, string $title, string $label, bool $isNav = false) use ($tpl): string {
        return $tpl->getHtmlFrag('pager-link', [
            'query' => $query ? 'index.php?'.$query : '',
            'target_id' => $target,
            'title' => $title,
            'label' => $label,
            'is_nav' => $isNav,
        ]);
    };
    $pagerCurrent = static fn(string $title, string $label, bool $isNav = false): string => $tpl->getHtmlFrag('pager-link', [
        'title' => $title,
        'label' => $label,
        'is_cur' => true,
        'is_nav' => $isNav,
    ]);
    $pagerDots = static fn(): string => $tpl->getHtmlFrag('inline-badge', ['is_pager_dots' => true]);
    if ($pages > 1) {
        $cont = '';
        if ($num > 1) {
            $prev = $num - 1;
            $cprev = $pagerLink(getQueryString(['go' => $go, 'op' => $op, 'id' => $cid, 'cid' => $prev, 'typ' => $typ, 'dir' => $mod]), $id, _BACK, _BACK, true);
        } else {
            $cprev = $pagerCurrent(_BACK, _BACK, true);
        }
        for ($i = 1; $i < $pages+1; $i++) {
            if ($i == $num) {
                $cont .= $pagerCurrent((string)$i, (string)$i);
            } else {
                if ((($i > ($num - $mnum)) && ($i < ($num + $mnum))) || ($i == $pages) || ($i == 1)) $cont .= $pagerLink(getQueryString(['go' => $go, 'op' => $op, 'id' => $cid, 'cid' => $i, 'typ' => $typ, 'dir' => $mod]), $id, (string)$i, (string)$i);
            }
            if ($i < $pages) {
                if (($i > ($num - $nnum)) && ($i < ($num + $mnum))) $cont .= ' ';
                if (($num > $nnum) && ($i == 1)) $cont .= $pagerDots();
                if (($num < ($pages - $mnum)) && ($i == ($pages - 1))) $cont .= $pagerDots();
            }
        }
        if ($num < $pages) {
            $next = $num + 1;
            $cnext = $pagerLink(getQueryString(['go' => $go, 'op' => $op, 'id' => $cid, 'cid' => $next, 'typ' => $typ, 'dir' => $mod]), $id, _NEXT, _NEXT, true);
        } else {
            $cnext = $pagerCurrent(_NEXT, _NEXT, true);
        }
        $data = ['overall' => _OVERALL, 'count' => $count, 'by' => _BY, 'pages' => $pages, 'page_s' => _PAGE_S, 'limit' => $page, 'perpage' => _PERPAGE, 'items' => $cont, 'prev' => $cprev, 'next' => $cnext];
        return $tpl->getHtmlFrag('pager', $data);
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
 global $user, $conf, $stop, $tpl;
    $directory = str_replace('\\', '/', trim($directory));
    if ($directory === '') {
        $directory = BASE_DIR;
    } elseif (!preg_match('#^(?:[A-Za-z]:/|//|/)#', $directory)) {
        $directory = BASE_DIR.'/'.ltrim($directory, '/');
    }
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
                    echo $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _ERROR_BIG]);
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
                            echo $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _ERROR_EXIST]);
                        } else {
                            $res = copy($_FILES['file']['tmp_name'][$i], $directory.'/'.$newname);
                            if (!$res) {
                                echo $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _ERROR_UP]);
                            } else {
                                echo $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _FILE_RENAMED.': '.$newname]);
                            }
                        }
                    } else {
                        $info = (!check_file($type, $typefile)) ? check_size($_FILES['file']['tmp_name'][$i], $width, $height) : check_file($type, $typefile);
                        echo $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $info]);
                    }
                }
            }
        } else {
            echo $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _ERROR_DOWN]);
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

# Show comments
function ashowcom(int $cid = 0, string $mod = ''): string {
 global $db, $conf, $afile, $user, $tpl, $prs;
    $mod = filterVar($mod);
    $params = [];
    if (defined('ADMIN_FILE')) {
        $amod = getVar('get', 'modul', 'var');
        if (getVar('get', 'status', 'num', 0) == 1) {
            $ordern = 'WHERE status = :status';
            $params = ['status' => 0];
        } else {
            $ordern = 'WHERE status != :status';
            $params = ['status' => 0];
        }
        if ($amod) {
            $ordern .= ' AND modul = :modul';
            $params['modul'] = $amod;
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
        $b = 0;
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
            $date = $tpl->getHtmlFrag('inline-badge', ['title_text' => (string)_PADD, 'label' => format_time($com_date, _TIMESTRING), 'is_comment_date' => true]);
            $ip = (is_moder($com_modul)) ? Geoip::getIpHtml($com_host) : '';
            $amess = $tpl->getHtmlFrag('link', ['href' => '#'.$com_id, 'title' => (string)_COMMENT.': '.(string)$a, 'label' => (string)$a, 'is_num_anchor' => true]);
            $avatar = (!empty($user_name)) ? (($user_avatar && file_exists($conf['users']['adirectory'].'/'.$user_avatar)) ? $conf['users']['adirectory'].'/'.$user_avatar : $conf['users']['adirectory'].'/default/00.gif') : $conf['users']['adirectory'].'/default/0.gif';
            $rank = (!empty($user_rank)) ? $user_rank : '';
            $trank = (!empty($user_gname)) ? _GROUP.': '.$user_gname : _RANK;
            $rlink = (!empty($user_grank) && file_exists(img_find('ranks/'.$user_grank))) ? $tpl->getHtmlFrag('image', ['src' => img_find('ranks/'.$user_grank), 'alt' => $trank, 'title' => $trank]) : '';
            $rate = (!empty($user_id)) ? getRatingAsync(0, $user_id, 'account', $user_votes, $user_totalvotes, $com_id, 1) : '';
            $rwarn = (!empty($user_warnings)) ? htmlspecialchars(_UWARNS, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').': '.htmlspecialchars(warnings($user_warnings), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
            $group = (!empty($user_gname)) ? $tpl->getHtmlFrag('span', ['label' => _GROUP, 'text' => $user_gname]) : '';
            $point = ($conf['users']['point'] && !empty($user_points)) ? htmlspecialchars(_POINTS, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').': '.htmlspecialchars((string) $user_points, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
            $regdate = (!empty($user_regdate)) ? htmlspecialchars(_REG, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').': '.htmlspecialchars(format_time($user_regdate), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : _NO_INFO;
            $gender = (!empty($user_gender)) ? htmlspecialchars(_GENDER, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').': '.htmlspecialchars(getGenderText($user_gender), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
            $from = (!empty($user_from)) ? htmlspecialchars(_FROM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').': '.htmlspecialchars($user_from, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
            $sig = (!empty($user_sig)) ? $tpl->getHtmlFrag('block-content', ['is_signature' => true, 'content' => $user_sig]) : '';
            $personal = (is_moder($com_modul) || is_user() || $conf['comments']['anonpost'] != 0) ? $tpl->getHtmlFrag('link', ['href' => '#', 'title' => _PERSONAL, 'label' => _PERS, 'is_button_blue' => true, 'link_attr' => getTplEditorInsertAttr('name', $avname)]) : '';
            $privat = ($conf['comments']['privat'] && $conf['privat']['act'] && !empty($user_name)) ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name=account&amp;op=privat&amp;uname='.urlencode($user_name), 'title' => _SENDMES, 'label' => _MESSAGE, 'is_button_green' => true]) : '';
            $profil = ($conf['comments']['profil'] && !empty($user_name)) ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name=account&amp;op=view&amp;uname='.urlencode($user_name), 'title' => _PERSONALINFO, 'label' => _ACCOUNT, 'is_account_button' => true]) : '';
            $web = ($conf['comments']['web'] && !empty($user_website)) ? $tpl->getHtmlFrag('link', ['href' => $user_website, 'title' => _DOWNLLINK, 'label' => _SITE, 'is_account_button' => true, 'is_blank' => true]) : '';
            $warn = '';
            $thank = '';

            if (is_moder($com_modul)) {
                if (defined('ADMIN_FILE')) {
                    $acttyp = $com_status ? '0' : '1';
                    $acttxt = $com_status ? _DEACTIVATE : _ACTIVATE;
                    $items = [
                        ['href' => 'index.php?name='.$com_modul.'&amp;op=view&amp;id='.$com_cid.'#'.$com_id, 'title' => _MVIEW, 'label' => _MVIEW],
                        ['href' => $afile.'.php?name=comments&amp;op=edit&amp;id='.$com_id, 'title' => _FULLEDIT, 'label' => _FULLEDIT],
                        ['href' => $afile.'.php?name=comments&amp;op=approve&amp;id='.$com_id.'&amp;typ='.$acttyp.'&amp;refer=1&amp;token='.getSiteToken(), 'title' => $acttxt, 'label' => $acttxt],
                        ['href' => $afile.'.php?name=comments&amp;op=delete&amp;id='.$com_id.'&amp;refer=1&amp;token='.getSiteToken(), 'title' => _ONDELETE, 'label' => _ONDELETE, 'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.cutstr(filterText($prs->filterContent($com_text, false, $com_modul)), 10).'&quot;?\');"'],
                    ];
                    $edit = $tpl->getHtmlFrag('row-actions', ['editor_label' => _EDITOR, 'items' => $items]);
                } else {
                    $items = [
                        $tpl->getHtmlFrag('comment-action-ajax', ['target' => 'com'.$com_id, 'query' => 'go=1&amp;op=updateComment&amp;id='.$com_id.'&amp;typ=1&amp;mod='.$com_modul, 'title' => _ONEDIT, 'label' => _ONEDIT]),
                        $tpl->getHtmlFrag('comment-action-ajax', ['target' => 'com'.$com_id, 'query' => 'go=1&amp;op=updateCommentStatus&amp;id='.$com_id.'&amp;typ=0&amp;mod='.$com_modul, 'title' => _FMODC, 'label' => _FMODC]),
                        $tpl->getHtmlFrag('comment-action-ajax', ['target' => 'com'.$com_id, 'query' => 'go=1&amp;op=updateCommentStatus&amp;id='.$com_id.'&amp;typ=1&amp;mod='.$com_modul, 'title' => _ACTIVATE, 'label' => _ACTIVATE]),
                    ];
                    $items = array_values(array_filter($items, static fn($item) => $item !== ''));
                    $edit = $tpl->getHtmlFrag('editor-action-menu', ['editor_label' => _EDITOR, 'items_html' => implode('', array_map(fn($item) => $tpl->getHtmlFrag('list-item', ['content_html' => $item]), $items))]);
                }
            } else {
                $stime = strtotime($com_date) + $conf['comments']['edit'];
                if (is_user() && isset($user_id) == intval($user[0]) && time() < $stime) {
                    $items = [
                        $tpl->getHtmlFrag('comment-action-ajax', ['target' => 'com'.$com_id, 'query' => 'go=1&amp;op=updateComment&amp;id='.$com_id.'&amp;typ=1&amp;mod='.$com_modul, 'title' => _ONEDIT, 'label' => _ONEDIT]),
                    ];
                    $edit = $tpl->getHtmlFrag('editor-action-menu', ['editor_label' => _EDITOR, 'items_html' => implode('', array_map(fn($item) => $tpl->getHtmlFrag('list-item', ['content_html' => $item]), $items))]);
                } else {
                    $edit = '';
                }
            }
            $text = $tpl->getHtmlFrag('block-content', ['id' => 'repcom'.$com_id, 'content' => $prs->filterContent($com_text, false, $com_modul)]);
            if (defined('ADMIN_FILE')) {
                $markAll = $tpl->getHtmlFrag('checkbox', [
                    'name_attr' => 'markcheck',
                    'input_id' => 'markcheck',
                ]);
                $itemCheck = $tpl->getHtmlFrag('checkbox', [
                    'name_attr' => 'id[]',
                    'is_check' => true,
                    'value_attr' => (string)$com_id,
                ]);
                $checkb = (!$b) ? ' '._CHECKALL.' '.$markAll.' | '.$itemCheck : ' '.$itemCheck;
                $b++;
            } else {
                $checkb = '';
            }
            $metatip = (defined('ADMIN_FILE')) ? $tpl->getHtmlFrag('info-tooltip', [
                'items' => [
                    ['label' => _DATE, 'value' => $date, 'is_last' => false],
                    ['label' => _IP, 'value' => $ip, 'is_last' => true],
                ],
            ]) : '';
            $cont .= $tpl->getHtmlFrag('comment', ['id' => $com_id, 'username' => $avname, 'date' => $date, 'ip' => $ip, 'meta_tip' => $metatip, 'post_count' => $amess, 'avatar' => $avatar, 'avatar_html' => $tpl->getHtmlFrag('image', ['src' => $avatar, 'alt' => $avname, 'title' => $avname, 'is_avatar' => true]), 'rank' => $rank, 'rank_link' => $rlink, 'user_rate' => $rate, 'warn' => $rwarn, 'group' => $group, 'points' => $point, 'regdate' => $regdate, 'gender' => $gender, 'from' => $from, 'text' => $text, 'sig' => $sig, 'btn_personal' => $personal, 'btn_pm' => $privat, 'btn_profile' => $profil, 'btn_web' => $web, 'btn_warn' => $warn, 'btn_thank' => $thank, 'btn_edit' => $edit, 'is_closed' => !defined('ADMIN_FILE') && !$com_status, 'closed_title' => _PCLOSED, 'checkb' => $checkb]);
            if ($conf['comments']['sort']) { $a++; } else { $a--; }
        }
        if (defined('ADMIN_FILE')) {
            $out = $tpl->getHtmlPart('form-wrap', ['form_name' => 'comm', 'action' => $afile.'.php', 'content_html' => $cont]);
        } else {
            $num = getVar('get', 'num', 'num');
            $pag = empty($num) ? 'op=view&id='.$cid : 'op=view&id='.$cid.'&num='.$num;
            $cont .= getPageNumbers($com_modul, $numstories, $numpages, $ccnum, $pag.'&', $plnum, 0, '#comm', 'com');
            $out = $tpl->getHtmlFrag('title', ['title' => _COMMENTS]).$cont;
        }
    } else {
        $winfo = (defined('ADMIN_FILE')) ? _NO_INFO : _NOCOMMENTS;
        $out = $tpl->getHtmlFrag('alert', ['text' => $winfo, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
    }
    return $out;
}

# Save edit comments
function updateComment(): string {
 global $db, $conf, $user, $tpl, $prs;
    $id   = getVar('post', 'id',   'num',  0) ?: getVar('get', 'id',   'num',  0);
    $typ  = getVar('post', 'typ',  'num',  0) ?: getVar('get', 'typ',  'num',  0);
    $mod  = filterVar(getVar('post', 'mod',  'text', '') ?: getVar('get', 'mod',  'text', ''));
    $text = trim(getVar('post', 'text', 'raw',  '') ?: getVar('get', 'text', 'raw',  ''));
    list($uid, $date, $comment) = $db->getSqlRow($db->getSqlQuery('SELECT uid, time, body FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $id]));
    $stime = strtotime($date) + $conf['comments']['edit'];
    if (is_moder($mod) || (is_user() && $uid == intval($user[0]) && time() < $stime)) {
        if ($id && $mod && !$text) {
            $content = ($typ) ? getTplAjaxTextarea(['obj' => 'com'.$id, 'go' => '1', 'op' => 'updateComment', 'id' => $id, 'cid' => '0', 'typ' => '0', 'mod' => $mod, 'text' => $comment, 'rows' => 10]) : $prs->filterContent($comment, false, $mod);
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
                echo $prs->filterContent($comm, false, $mod);
            } else {
                return $tpl->getHtmlFrag('alert', ['text' => $stop, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
            }
        }
    } else {
        $info = sprintf(_PEDEND, intval($conf['comments']['edit'] / 60));
        return $tpl->getHtmlFrag('alert', ['text' => $info, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
    }
    return '';
}

# Close comments
function updateCommentStatus(): void {
 global $db, $tpl;
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
        echo $tpl->getHtmlFrag('alert', ['text' => $info, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
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
            $meta = $tpl->getHtmlFrag('meta-refresh', ['secs' => '3', 'url' => 'index.php?name=voting&amp;op=view&amp;id='.$id]);
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
                $meta = $tpl->getHtmlFrag('meta-refresh', ['secs' => '3', 'url' => 'index.php?name=voting&amp;op=view&amp;id='.$id]);
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
                    update_points(42);
                }
                $cont = getVotingView($id);
            }
        }
    } else {
        $meta = $tpl->getHtmlFrag('meta-refresh', ['secs' => '3', 'url' => 'index.php?name=voting']);
        $cont = $tpl->getHtmlFrag('alert', ['text' => _ERROR, 'meta' => $meta, 'type' => 'warn', 'is_warn' => true]);
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
