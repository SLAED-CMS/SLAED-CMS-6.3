<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The rescuer of an installation, contract v1: the installed copy, which an installation replaces only on its last step, so a broken package never breaks its own rollback
# The gate requires it with no argument for a rescue code, a rescue.now put by FTP or a rollback under way; the panel requires it to go back to the previous version
# It reads all it needs from client/apply.lock and the plan of the backup and restores the database, config/ and the files in portions, one request each, without the core
if (!defined('BASE_DIR') || !defined('PUBLIC_DIR')) die('Illegal file access');

# The folder of the client state, named by the core or by the default layout when no core has run
function getRescueDir(): string {
    return (defined('UPDATE_DIR') ? UPDATE_DIR : BASE_DIR.'/storage/update').'/client';
}

# The folder of the site settings, named by the core or by the default layout
function getRescueConf(): string {
    return defined('CONFIG_DIR') ? CONFIG_DIR : BASE_DIR.'/config';
}

# Hold the one turn of the installation, so an executor still running in another tab and this rollback never move files at once
function getRescueHold(): mixed {
    $link = fopen(getRescueDir().'/apply.run', 'c');
    if ($link !== false) flock($link, LOCK_EX);
    return $link;
}

# The lock of the installation as written, or null when it is missing or no JSON object
function getRescueLock(): ?array {
    $file = getRescueDir().'/apply.lock';
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    return is_array($data) ? $data : null;
}

# Write one JSON file by one rename, so a reader never sees half of it
function setRescueJson(string $file, array $data): bool {
    $temp = $file.'.'.bin2hex(random_bytes(6));
    if (file_put_contents($temp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n", LOCK_EX) === false) return false;
    if (rename($temp, $file)) return true;
    unlink($temp);
    return false;
}

# Write the lock after a portion; a lock that cannot be written stops the request before anything else moves
function setRescueLock(array $lock): array {
    if (setRescueJson(getRescueDir().'/apply.lock', $lock)) return $lock;
    $text = 'The lock storage/update/client/apply.lock cannot be written. Give PHP the right to write into storage/update/client/ and reload.';
    setRescuePage('Rollback paused', $text, [], '', 503);
}

# The plan of the backup: the moves in their order and the folders the installation created, or an empty plan when it cannot be read
function getRescuePlan(array $lock): array {
    $file = BASE_DIR.'/'.$lock['back'].'/plan.json';
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    return (is_array($data) && ($data['ver'] ?? null) === 1 && is_array($data['moves'] ?? null)) ? $data : ['ver' => 1, 'moves' => [], 'dirs' => []];
}

# Merge fields into client/state.json and keep every other field of it
function setRescueState(array $data): bool {
    $file = getRescueDir().'/state.json';
    $old = is_file($file) ? json_decode((string)file_get_contents($file), true) : [];
    return setRescueJson($file, array_replace(is_array($old) ? $old : [], $data));
}

# A connection to the site database from config/db.php alone, throwing on every failed statement as the portion mode of Backup requires
function getRescueLink(): PDO {
    $conf = (require getRescueConf().'/db.php')['db'];
    $dsn = 'mysql:host='.$conf['host'].';dbname='.$conf['name'].';charset=utf8mb4';
    return new PDO($dsn, $conf['uname'], $conf['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# Create a folder with each missing parent at 0755
function addRescuePath(string $dir): bool {
    if (is_dir($dir)) return true;
    if (!addRescuePath(dirname($dir)) || (!mkdir($dir) && !is_dir($dir))) return false;
    return chmod($dir, 0755);
}

# Remove a file or a folder with all below it, never following a link out of it
function deleteRescueTree(string $path): bool {
    if (is_link($path) || is_file($path)) return unlink($path);
    if (!is_dir($path)) return true;
    foreach (scandir($path) ?: [] as $name) {
        if ($name !== '.' && $name !== '..' && !deleteRescueTree($path.'/'.$name)) return false;
    }
    return rmdir($path);
}

# The rescue code a form posted, its dashes and spaces dropped and lowercased, or an empty string for anything but 20 hex characters
function getRescueCode(): string {
    $code = is_string($_POST['rescue'] ?? null) ? strtolower((string)preg_replace('#[\s-]+#', '', $_POST['rescue'])) : '';
    return (preg_match('#^[0-9a-f]{20}$#D', $code) === 1) ? $code : '';
}

# Answer one page of the rollback on the shell of public/error.html, which needs no core, and end the request; $field asks for the rescue code
function setRescuePage(string $title, string $text, array $hide, string $act, int $code = 200, bool $auto = false, string $verb = 'post', bool $field = false): never {
    while (ob_get_level() > 0) ob_end_clean();
    $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    $form = '';
    if ($act !== '' && !str_contains($act, '/')) $act = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/x'))), '/').'/'.$act;
    if ($act !== '') {
        $form = '<form id="sl-rescue-form" class="sl-search-form sl-msg-search" method="'.$verb.'" action="'.$esc($act).'">';
        foreach ($hide as $key => $val) $form .= '<input type="hidden" name="'.$esc((string)$key).'" value="'.$esc((string)$val).'">';
        $form .= $field ? '<input type="text" name="rescue" maxlength="40" placeholder="Rescue code" class="sl-field" autocomplete="off" required>'
            : '<span class="sl-field">'.$esc($auto ? 'Continues by itself' : 'Continue').'</span>';
        $acts = $field ? [['go', 'Continue the installation', 'M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708'
            .'L13.293 8.5H1.5A.5.5 0 0 1 1 8'], ['back', 'Roll back to the previous version', 'M8 3a5 5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2zM8 4.466V.534'
            .'a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36 1.966A.25.25 0 0 0 8 4.466']]
            : [['', 'Continue', 'M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8']];
        foreach ($acts as [$act, $label, $path]) {
            $form .= '<button type="submit"'.(($act !== '') ? ' name="act" value="'.$act.'"' : '').' class="sl-circle-action" aria-label="'.$label.'" title="'.$label.'">'
                .'<svg class="bi" viewBox="0 0 16 16" aria-hidden="true"><path d="'.$path.'"/></svg></button>';
        }
        $form .= '</form>';
    }
    $body = '<h1 class="sl-msg-title">'.$esc($title).'</h1><div class="sl-msg-body"><div class="sl-alert sl-alert-warn"><div class="sl-alert-body"><p class="sl-alert-text">'
        .$esc($text).'</p></div></div></div>'.$form;
    $html = is_file(PUBLIC_DIR.'/error.html') ? (string)file_get_contents(PUBLIC_DIR.'/error.html') : '';
    $from = strpos($html, '<h1 class="sl-msg-title">');
    $to = ($from === false) ? false : strpos($html, '</form>', $from);
    $html = ($to === false) ? '<!doctype html><html lang="en"><head><meta charset="utf-8"><title></title></head><body>'.$body.'</body></html>'
        : substr($html, 0, $from).$body.substr($html, $to + 7);
    $html = (string)preg_replace('#<title>.*?</title>#s', '<title>'.$esc($title).' - SLAED CMS</title>', $html, 1);
    if ($auto && $form !== '') $html = str_replace('</body>', '<script>setTimeout(function(){document.getElementById(\'sl-rescue-form\').submit();},300);</script></body>', $html);
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    echo $html;
    exit;
}

# Stop the rollback on a failure it cannot pass by itself; the lock stays, the site stays closed, and the same request repeats the part once the cause is fixed
function setRescueFail(array $lock, array $hide, string $error): never {
    $text = 'The rollback stopped: '.substr($error, 0, 300).'. Fix the cause and continue; the site stays closed until the rollback ends.';
    setRescuePage('Rollback stopped', $text, $hide, $lock['entry'], 500);
}

# Undo one move of the plan: the kept old file returns to its place, a file the installation added goes; undoing a move never begun leaves the old file alone
function setRescueBack(array $one, string $back): bool {
    $dest = BASE_DIR.'/'.$one['path'];
    $keep = $back.'/files/'.$one['path'];
    if (is_file($keep)) {
        if (is_file($dest) && !unlink($dest)) return false;
        return addRescuePath(dirname($dest)) && rename($keep, $dest);
    }
    if ($one['op'] === 'put' && empty($one['old']) && is_file($dest)) return unlink($dest);
    return true;
}

# The database back from the dump of the installation, one part per call; a request cut inside a part restarts its table from the part that begins it
function addRescueBase(array $lock, array $hide, float $end): array {
    if (!defined('FUNC_FILE')) define('FUNC_FILE', true);
    require_once BASE_DIR.'/core/classes/backup.php';
    try {
        $pdo = getRescueLink();
        $state = is_array($lock['roll']['rest']) ? $lock['roll']['rest'] : Backup::getRestoreState(BASE_DIR.'/'.$lock['back'].'/dump');
        if (!empty($lock['roll']['busy'])) $state['part'] = $state['head'];
        while (!$state['done'] && microtime(true) < $end) {
            $lock['roll']['busy'] = true;
            setRescueLock($lock);
            $state = Backup::addRestorePart($pdo, $state);
            $lock['roll'] = array_replace($lock['roll'], ['rest' => $state, 'busy' => false]);
            setRescueLock($lock);
        }
    } catch (Throwable $error) {
        setRescueFail($lock, $hide, 'the database cannot be restored: '.$error->getMessage());
    }
    if ($state['done']) $lock['roll']['part'] = 'conf';
    return setRescueLock($lock);
}

# The settings back from the copy of config/ the installation took before its merge; a settings file created after that copy goes, and so does the cached configuration
function addRescueConf(array $lock, array $hide): array {
    $cdir = getRescueConf();
    $back = BASE_DIR.'/'.$lock['back'].'/config';
    if (!is_dir($back)) setRescueFail($lock, $hide, 'the backup of config/ is missing');
    foreach (glob($cdir.'/*.php') ?: [] as $file) {
        if ((basename($file) === 'local.php' || !is_file($back.'/'.basename($file))) && !unlink($file)) setRescueFail($lock, $hide, 'cannot remove config/'.basename($file));
    }
    foreach (glob($back.'/*.php') ?: [] as $file) {
        $dest = $cdir.'/'.basename($file);
        if (!copy($file, $dest.'.part') || !rename($dest.'.part', $dest)) setRescueFail($lock, $hide, 'cannot restore config/'.basename($file));
    }
    $lock['roll']['part'] = 'files';
    return setRescueLock($lock);
}

# The files back by the moves of the plan in reverse, in batches, then the folders the installation created when they are empty again
function addRescueFiles(array $lock, array $hide, float $end): array {
    $plan = getRescuePlan($lock);
    $back = BASE_DIR.'/'.$lock['back'];
    set_error_handler(static fn(): bool => true);
    while ($lock['roll']['at'] > 0 && microtime(true) < $end) {
        $from = max(0, $lock['roll']['at'] - 100);
        for ($i = $lock['roll']['at'] - 1; $i >= $from; $i--) {
            if (!setRescueBack($plan['moves'][$i], $back)) setRescueFail($lock, $hide, 'cannot restore '.$plan['moves'][$i]['path']);
        }
        $lock['roll']['at'] = $from;
        setRescueLock($lock);
    }
    if ($lock['roll']['at'] === 0) {
        foreach (array_reverse((array)($plan['dirs'] ?? [])) as $dir) {
            if (is_dir(BASE_DIR.'/'.$dir) && count(scandir(BASE_DIR.'/'.$dir) ?: []) === 2) rmdir(BASE_DIR.'/'.$dir);
        }
        $lock['roll']['part'] = 'clean';
    }
    restore_error_handler();
    return setRescueLock($lock);
}

# Every rollback ends with the caches gone: the cached configuration, the cached pages and assets and the opcode cache, so the old code meets nothing of the new one
function addRescueClean(array $lock): array {
    $cache = defined('CACHE_DIR') ? CACHE_DIR : BASE_DIR.'/storage/cache';
    set_error_handler(static fn(): bool => true);
    if (is_file(getRescueConf().'/local.php')) unlink(getRescueConf().'/local.php');
    if (is_dir($cache)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cache, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && !str_ends_with($file->getFilename(), '.lock')) unlink($file->getPathname());
        }
    }
    if (function_exists('opcache_reset')) opcache_reset();
    restore_error_handler();
    $lock['roll']['part'] = 'done';
    return setRescueLock($lock);
}

# The end of every rollback: the backup is spent, the package and the run copy go, the state notes the rollback and a failed step for the master, and the site opens
function setRescueDone(array $lock): never {
    $roll = $lock['roll'];
    $data = ['undo' => ['from' => $lock['to'], 'to' => $lock['from'], 'time' => time(), 'why' => $roll['why'], 'step' => $roll['step'], 'error' => $roll['error']],
        'stage' => [], 'done' => []];
    if ($roll['why'] !== 'revert') $data['fail'] = ['ver' => $lock['to'], 'step' => $roll['step']];
    deleteRescueTree(BASE_DIR.'/'.$lock['back']);
    deleteRescueTree(getRescueDir().'/stage');
    deleteRescueTree(getRescueDir().'/run');
    setRescueState($data);
    if (is_file(getRescueDir().'/rescue.now')) unlink(getRescueDir().'/rescue.now');
    unlink(getRescueDir().'/apply.lock');
    setRescuePage('SLAED CMS '.$lock['from'].' is back', 'The previous version is restored and the site is open again.', ['name' => 'update'], $lock['entry'], 200, true, 'get');
}

$rhold = getRescueHold();
$rlock = getRescueLock();
if ($rlock === null || ($rlock['ver'] ?? null) !== 1) {
    setRescuePage('Rollback refused', 'storage/update/client/apply.lock is missing, damaged or of a format this rescuer does not know. Restore the backup in '
        .'storage/backup/update/ by hand.', [], '', 503);
}
$rnow = is_file(getRescueDir().'/rescue.now');
$rtok = (is_string($_POST['apply'] ?? null) && preg_match('#^[0-9a-f]{64}$#D', $_POST['apply']) === 1) ? $_POST['apply'] : '';
$rhit = $rtok !== '' && hash_equals((string)$rlock['token'], hash('sha256', $rtok));
$rcode = getRescueCode();
if ($rcode !== '' && hash_equals((string)$rlock['code'], hash('sha256', $rcode))) {
    $rtok = bin2hex(random_bytes(32));
    $rlock['token'] = hash('sha256', $rtok);
    $rhit = true;
}
if (!$rnow && !$rhit) {
    $rtext = (isset($_POST['rescue']) ? 'This rescue code is wrong. ' : '').'Enter the rescue code shown before the installation started. The arrow continues the '
        .'installation from where it stopped, the circular arrow rolls back to the previous version. Without the code put an empty file storage/update/client/rescue.now '
        .'on the server by FTP and reload this page: that rolls back.';
    setRescuePage('The installation is not finished', $rtext, [], $rlock['entry'], isset($_POST['rescue']) ? 403 : 503, false, 'post', true);
}
if ($rcode !== '' && ($_POST['act'] ?? '') === 'go' && !$rnow && empty($rlock['roll']) && in_array($rlock['phase'], ['dump', 'files', 'core', 'open'], true)) {
    setRescueLock($rlock);
    setRescuePage('Installing SLAED CMS '.$rlock['to'], 'The installation continues from where it stopped.', ['apply' => $rtok], $rlock['entry'], 200, true);
}
if (empty($rlock['roll'])) {
    $rstep = ['dump' => 4, 'files' => 5, 'core' => 6, 'open' => 8][$rlock['phase']] ?? 0;
    $rlock['roll'] = ['why' => $rnow ? 'now' : 'code', 'step' => $rstep, 'error' => '', 'part' => ($rlock['phase'] === 'dump') ? 'stop' : 'base', 'rest' => null,
        'at' => max($rlock['at'], $rlock['upto'])];
    $rlock['phase'] = 'roll';
}
$rlock = setRescueLock($rlock);
$rhide = $rhit ? ['apply' => $rtok] : [];
$rspan = intval(ini_get('max_execution_time'));
$rend = microtime(true) + (($rspan > 0) ? min(20, max(5, intdiv($rspan, 3))) : 20);
if ($rlock['roll']['part'] === 'stop') {
    $rlock['roll']['part'] = 'done';
    $rlock['base'] = false;
}
if ($rlock['roll']['part'] === 'base') {
    $rlock = $rlock['base'] ? addRescueBase($rlock, $rhide, $rend) : setRescueLock(array_replace_recursive($rlock, ['roll' => ['part' => 'conf']]));
    if ($rlock['roll']['part'] === 'base') setRescuePage('Rolling back to SLAED CMS '.$rlock['from'], 'Restoring the database.', $rhide, $rlock['entry'], 200, true);
}
if ($rlock['roll']['part'] === 'conf') $rlock = $rlock['conf'] ? addRescueConf($rlock, $rhide) : setRescueLock(array_replace_recursive($rlock, ['roll' => ['part' => 'files']]));
if ($rlock['roll']['part'] === 'files') {
    $rlock = addRescueFiles($rlock, $rhide, $rend);
    if ($rlock['roll']['part'] === 'files') setRescuePage('Rolling back to SLAED CMS '.$rlock['from'], 'Restoring the files, '.$rlock['roll']['at'].' left.', $rhide,
        $rlock['entry'], 200, true);
}
if ($rlock['roll']['part'] === 'clean') $rlock = addRescueClean($rlock);
setRescueDone($rlock);
