<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the scheduler lock protocol: boots the real core with LOGS_DIR in scratch, so every lock file and every state file this writes lives outside the site
# One scenario per process, and the scenarios that need a second party start a real second process, because an operating system lock can only be contended across processes
$probework = (string)($argv[2] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';

# The job every scenario drives, declared in memory so no site configuration is written
const PROBEJOB = 'probejob';
const OTHERJOB = 'otherjob';

# Declare two jobs in the running configuration: one the scenarios drive and one that proves unrelated jobs stay independent
function addProbeJobs(): void {
    $one = ['title' => 'Probe job', 'type' => 'custom', 'active' => '1', 'system' => '', 'schedule' => '* * * * *', 'priority' => '900', 'lock_timeout' => '600', 'manual' => '1',
        'settings' => ['url' => 'robots.txt']];
    $GLOBALS['conf']['scheduler']['active'] = '1';
    $GLOBALS['conf']['scheduler']['jobs'] = [PROBEJOB => $one, OTHERJOB => array_replace($one, ['title' => 'Other job', 'priority' => '901'])];
    foreach ([PROBEJOB, OTHERJOB] as $name) {
        $file = LOGS_DIR.'/scheduler/'.$name.'.json';
        if (is_file($file)) unlink($file);
    }
}

# Write one job state directly, which is how a scenario starts from a state a crashed process would have left
function setProbeState(string $name, array $state): void {
    setSchedulerState($name, array_replace(getSchedulerState($name), $state));
}

# Read the fields of one job state that the protocol is defined in terms of
function getProbeState(string $name): array {
    $state = getSchedulerState($name);
    return [
        'running' => (int)$state['running'],
        'started' => (int)$state['started_at'],
        'status' => (string)$state['last_status'],
        'lastrun' => (int)$state['last_run'],
        'fails' => (int)$state['fail_count'],
        'error' => (string)$state['last_error'],
    ];
}

# Start a second process that holds the operating system lock of one job for the given milliseconds, and wait until it reports that it has it
function addProbeHolder(string $name, int $msec): mixed {
    $file = LOGS_DIR.'/holder.php';
    $lock = getSchedulerLockPath($name);
    $flag = LOGS_DIR.'/holder.flag';
    if (is_file($flag)) unlink($flag);
    $code = '<?php $fh = fopen('.var_export($lock, true).', \'cb\'); flock($fh, LOCK_EX); file_put_contents('.var_export($flag,
        true).', \'1\'); usleep('.($msec * 1000).'); flock($fh, LOCK_UN); fclose($fh);';
    file_put_contents($file, $code);
    $proc = proc_open(escapeshellarg(PHP_BINARY).' '.escapeshellarg($file), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipe);
    for ($i = 0; $i < 200; $i++) {
        if (is_file($flag)) break;
        usleep(20000);
    }
    return ['proc' => $proc, 'pipe' => $pipe, 'held' => is_file($flag)];
}

# Wait for the second process to finish and release its lock
function deleteProbeHolder(array $hold): void {
    if (!is_resource($hold['proc'])) return;
    foreach ($hold['pipe'] as $one) fclose($one);
    proc_close($hold['proc']);
}

# The four rows of the lock state table: what the operating system lock and the JSON say together, and what each combination allows
function getProbeTable(): array {
    $out = [];
    setProbeState(PROBEJOB, ['running' => 0, 'started_at' => 0, 'last_status' => 'idle', 'last_run' => 0]);
    $out['idle'] = ['locked' => checkSchedulerLock(PROBEJOB), 'due' => checkSchedulerDue(PROBEJOB)];
    $hold = addProbeHolder(PROBEJOB, 1500);
    setProbeState(PROBEJOB, ['running' => 1, 'started_at' => time(), 'last_status' => 'running']);
    $out['live'] = ['held' => $hold['held'], 'locked' => checkSchedulerLock(PROBEJOB), 'due' => checkSchedulerDue(PROBEJOB),
        'run' => addSchedulerRun(PROBEJOB, 'manual')['status'], 'state' => getProbeState(PROBEJOB)];
    deleteProbeHolder($hold);
    setProbeState(PROBEJOB, ['running' => 1, 'started_at' => time() - 10, 'last_status' => 'running', 'last_run' => 1700000000, 'fail_count' => 2]);
    $out['crashed'] = ['locked' => checkSchedulerLock(PROBEJOB), 'due' => checkSchedulerDue(PROBEJOB), 'read' => getProbeState(PROBEJOB)];
    $out['crashed']['after'] = updateProbeProtocol(PROBEJOB);
    $out['crashed']['twice'] = updateProbeProtocol(PROBEJOB);
    return $out;
}

# One full pass of the transition protocol: take the lock, re-read the state while holding it, reconcile a crashed run, release
# Running it twice is how idempotence is asserted, because the second pass finds running at zero and must change nothing
function updateProbeProtocol(string $name): array {
    $lock = getSchedulerLockHandle($name);
    if ($lock === false) return ['refused' => true];
    $state = getSchedulerState($name);
    if (!empty($state['running'])) updateSchedulerCrash($name, $state);
    deleteSchedulerHandle($lock);
    return getProbeState($name);
}

# Two runs of the same job cannot overlap, while a different job stays startable at the same moment
function getProbeConcurrent(): array {
    setProbeState(PROBEJOB, ['running' => 0, 'started_at' => 0, 'last_status' => 'idle']);
    setProbeState(OTHERJOB, ['running' => 0, 'started_at' => 0, 'last_status' => 'idle']);
    $hold = addProbeHolder(PROBEJOB, 1500);
    $out = [
        'held' => $hold['held'],
        'same' => addSchedulerRun(PROBEJOB, 'manual')['status'],
        'other' => getSchedulerLockHandle(OTHERJOB) !== false,
        'samehandle' => getSchedulerLockHandle(PROBEJOB) === false,
    ];
    deleteProbeHolder($hold);
    $out['free'] = getSchedulerLockHandle(PROBEJOB) !== false;
    return $out;
}

# A read path must never repair anything: looking at a crashed job leaves its state exactly as it found it
function getProbeReadOnly(): array {
    setProbeState(PROBEJOB, ['running' => 1, 'started_at' => time() - 5, 'last_status' => 'running', 'last_run' => 1700000000, 'fail_count' => 0]);
    $before = getProbeState(PROBEJOB);
    checkSchedulerLock(PROBEJOB);
    getSchedulerState(PROBEJOB);
    checkSchedulerDue(PROBEJOB);
    getSchedulerJobs();
    return ['before' => $before, 'after' => getProbeState(PROBEJOB)];
}

# A crashed job is reconciled by the next run that takes the lock, and that run keeps last_run so the job resumes at its next slot instead of retrying in a loop
function getProbeRecover(): array {
    setProbeState(PROBEJOB, ['running' => 1, 'started_at' => time() - 20, 'last_status' => 'running', 'last_run' => 1700000000, 'fail_count' => 1]);
    $due = checkSchedulerDue(PROBEJOB);
    $res = addSchedulerRun(PROBEJOB, 'manual');
    $state = getProbeState(PROBEJOB);
    return ['due' => $due, 'status' => $res['status'], 'ranagain' => $state['lastrun'] !== 1700000000, 'state' => $state, 'locked' => checkSchedulerLock(PROBEJOB)];
}

# Two scheduled runs that both saw the job as due must not both execute it: the second one asks again while holding the lock and finds the slot already covered
function getProbeSlot(): array {
    setProbeState(PROBEJOB, ['running' => 0, 'started_at' => 0, 'last_status' => 'idle', 'last_run' => 1700000000, 'next_run' => 0, 'next_schedule' => '', 'next_last_run' => 0]);
    $out = ['duebefore' => checkSchedulerDue(PROBEJOB)];
    $first = addSchedulerRun(PROBEJOB, 'cron');
    $second = addSchedulerRun(PROBEJOB, 'cron');
    $out['first'] = $first['status'];
    $out['second'] = $second['status'];
    $out['secondmsg'] = (string)($second['message'] ?? '');
    $out['ranonce'] = getProbeState(PROBEJOB)['lastrun'] !== 1700000000;
    # The barrier itself: a process that decided the job was due before the lock existed asks again while holding it, and the answer must have flipped
    $out['slotafter'] = checkSchedulerSlot(PROBEJOB, getSchedulerJob(PROBEJOB), getSchedulerState(PROBEJOB));
    $out['slotstale'] = checkSchedulerSlot(PROBEJOB, getSchedulerJob(PROBEJOB),
        array_replace(getSchedulerState(PROBEJOB), ['last_run' => 1700000000, 'next_run' => 0, 'next_schedule' => '', 'next_last_run' => 0]));
    setProbeState(PROBEJOB, ['running' => 0, 'started_at' => 0, 'last_status' => 'idle', 'last_run' => 1700000000, 'next_run' => 0, 'next_schedule' => '', 'next_last_run' => 0]);
    $out['manual'] = addSchedulerRun(PROBEJOB, 'manual')['status'];
    $out['manualagain'] = addSchedulerRun(PROBEJOB, 'manual')['status'];
    return $out;
}

# A run whose state cannot be stored must say so instead of reporting success on a job that still looks due
# The state file is made read-only, so it still reads as due while every write to it fails, which is exactly the disk-full or read-only-directory case
function getProbeUnwritable(): array {
    setProbeState(PROBEJOB, ['running' => 0, 'started_at' => 0, 'last_status' => 'idle', 'last_run' => 1700000000, 'next_run' => 0, 'next_schedule' => '', 'next_last_run' => 0]);
    $file = LOGS_DIR.'/scheduler/'.PROBEJOB.'.json';
    $out = ['due' => checkSchedulerDue(PROBEJOB)];
    chmod($file, 0444);
    $out['writable'] = is_writable($file);
    $res = addSchedulerRun(PROBEJOB, 'cron');
    chmod($file, 0666);
    $out['status'] = $res['status'];
    $out['message'] = (string)($res['message'] ?? '');
    $out['state'] = getProbeState(PROBEJOB);
    return $out;
}

# The access matrix of the direct endpoint: each trigger carries its own credential and nothing else opens it
function getProbeAccess(): array {
    $GLOBALS['conf']['scheduler']['token'] = 'cron-secret-value';
    $good = getSiteToken('scheduler');
    return [
        'cronok' => checkSchedulerAccess('cron', 'cron-secret-value'),
        'cronbad' => checkSchedulerAccess('cron', 'wrong'),
        'cronempty' => checkSchedulerAccess('cron', ''),
        'pseudook' => checkSchedulerAccess('pseudo', $good),
        'pseudobad' => checkSchedulerAccess('pseudo', 'wrong'),
        'pseudocron' => checkSchedulerAccess('pseudo', 'cron-secret-value'),
        'cronsite' => checkSchedulerAccess('cron', $good),
        'manual' => checkSchedulerAccess('manual', $good),
        'unknown' => checkSchedulerAccess('anything', 'cron-secret-value'),
        'empty' => checkSchedulerAccess('', ''),
    ];
}

# The unlock action follows the same protocol: it refuses while the lock is held and reconciles only when it owns the lock, and it never deletes the lock file
function getProbeUnlock(): array {
    setProbeState(PROBEJOB, ['running' => 1, 'started_at' => time(), 'last_status' => 'running', 'last_run' => 1700000000]);
    $hold = addProbeHolder(PROBEJOB, 1200);
    $lock = getSchedulerLockHandle(PROBEJOB);
    $out = ['held' => $hold['held'], 'refused' => $lock === false, 'kept' => getProbeState(PROBEJOB)];
    deleteProbeHolder($hold);
    $lock = getSchedulerLockHandle(PROBEJOB);
    $out['granted'] = $lock !== false;
    if ($lock !== false) {
        updateSchedulerCrash(PROBEJOB, getSchedulerState(PROBEJOB));
        deleteSchedulerHandle($lock);
    }
    $out['after'] = getProbeState(PROBEJOB);
    $out['lockfile'] = is_file(getSchedulerLockPath(PROBEJOB));
    return $out;
}

# Drive the direct endpoint over real HTTP: what each method, trigger and credential actually gets back from the running site
function getProbeRoute(): array {
    $base = rtrim((string)($GLOBALS['conf']['homeurl'] ?? ''), '/');
    if ($base === '') return ['error' => 'No homeurl configured'];
    $url = $base.'/index.php?go=3&op=scheduler';
    $mark = BASE_DIR.'/storage/logs/scheduler/trigger.json';
    if (is_file($mark)) unlink($mark);
    $home = getProbeHttp($base.'/', 'GET');
    if ($home['body'] === '') return ['error' => 'The site did not answer at '.$base];
    $tok = preg_match('/trigger=pseudo&token=([a-f0-9]{16,})/', $home['body'], $mat) ? $mat[1] : '';
    $out = [
        'getquery' => getProbeHttp($url.'&trigger=cron&token=probe', 'GET')['body'],
        'postnotoken' => getProbeHttp($url, 'POST', 'trigger=cron')['body'],
        'postmanual' => getProbeHttp($url, 'POST', 'trigger=manual&token=probe')['body'],
        'postnotrigger' => getProbeHttp($url, 'POST', 'token=probe')['body'],
        'postwrongcron' => getProbeHttp($url, 'POST', 'trigger=cron&token=probe')['body'],
        'postwrongpseudo' => getProbeHttp($url, 'POST', 'trigger=pseudo&token=probe')['body'],
        'token' => $tok !== '',
    ];
    if ($tok === '') return $out;
    $out['session'] = getProbeHttp($url, 'POST', 'trigger=pseudo&token='.$tok, $home['cookie'])['body'];
    $out['nosession'] = getProbeHttp($url, 'POST', 'trigger=pseudo&token='.$tok)['body'];
    $out['getvalid'] = getProbeHttp($url.'&trigger=pseudo&token='.$tok, 'GET', '', $home['cookie'])['body'];
    return $out;
}

# One HTTP call against the running site, carrying an optional body and session cookie, returning the body and the cookie the site set
function getProbeHttp(string $url, string $verb, string $body = '', string $cookie = ''): array {
    $head = 'Content-Type: application/x-www-form-urlencoded'."\r\n";
    if ($cookie !== '') $head .= 'Cookie: '.$cookie."\r\n";
    $ctx = stream_context_create(['http' => ['method' => $verb, 'header' => $head, 'content' => $body, 'timeout' => 20, 'ignore_errors' => true]]);
    $link = @fopen($url, 'rb', false, $ctx);
    if ($link === false) return ['body' => '', 'cookie' => ''];
    $text = (string)stream_get_contents($link);
    $meta = stream_get_meta_data($link);
    fclose($link);
    $jar = [];
    foreach (($meta['wrapper_data'] ?? []) as $line) {
        if (preg_match('/^Set-Cookie:\s*([^;]+)/i', (string)$line, $mat)) $jar[] = trim($mat[1]);
    }
    return ['body' => $text, 'cookie' => implode('; ', $jar)];
}

# The pseudo trigger must carry its credential in a body rather than in an address
function getProbeTrigger(): array {
    $GLOBALS['conf']['scheduler']['pseudo'] = '1';
    $GLOBALS['conf']['scheduler']['token'] = '';
    setProbeState(PROBEJOB, ['running' => 0, 'started_at' => 0, 'last_status' => 'idle', 'last_run' => 1700000000]);
    $file = LOGS_DIR.'/scheduler/trigger.json';
    if (is_file($file)) unlink($file);
    $data = addSchedulerTrigger();
    $out = ['url' => (string)($data['url'] ?? ''), 'token' => (string)($data['token'] ?? ''), 'query' => str_contains((string)($data['url'] ?? ''), 'token=')];
    # A site whose cron is alive must simply get no trigger, and asking for one must not be an error on a page render
    file_put_contents(LOGS_DIR.'/scheduler/heartbeat.json', json_encode(['trigger' => 'cron', 'time' => time()]));
    try {
        $out['withcron'] = addSchedulerTrigger();
    } catch (Throwable $error) {
        $out['withcron'] = 'threw: '.$error->getMessage();
    }
    unlink(LOGS_DIR.'/scheduler/heartbeat.json');
    return $out;
}

addProbeJobs();
$mode = (string)($argv[1] ?? '');
$out = [];
try {
    $out = match ($mode) {
        'table' => getProbeTable(),
        'concurrent' => getProbeConcurrent(),
        'readonly' => getProbeReadOnly(),
        'recover' => getProbeRecover(),
        'access' => getProbeAccess(),
        'slot' => getProbeSlot(),
        'unwritable' => getProbeUnwritable(),
        'unlock' => getProbeUnlock(),
        'trigger' => getProbeTrigger(),
        'route' => getProbeRoute(),
        default => ['error' => 'unknown scenario '.$mode],
    };
} catch (Throwable $error) {
    $out = ['error' => $error->getMessage()];
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($out, JSON_UNESCAPED_SLASHES);
