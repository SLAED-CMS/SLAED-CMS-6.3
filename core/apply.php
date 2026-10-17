<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The executor of an installation, contract v1: the panel copies it from the package to client/run/apply.php, and the gate or the panel requires it with no argument
# All it needs lies in client/apply.lock and the plan of the backup; each request does one portion of one phase, writes the lock and answers its own page
# It loads the core only in the phase that runs the database steps, so the files move while no code of the mixed tree runs; the rollback is the installed core/rescue.php
if (!defined('BASE_DIR') || !defined('PUBLIC_DIR')) die('Illegal file access');

# The folder of the client state, named by the core or by the default layout when no core has run
function getApplyDir(): string {
    return (defined('UPDATE_DIR') ? UPDATE_DIR : BASE_DIR.'/storage/update').'/client';
}

# Hold the one turn of the installation, so a second tab and a rollback started by the rescue code never move files at once
function getApplyHold(): mixed {
    $link = fopen(getApplyDir().'/apply.run', 'c');
    if ($link !== false) flock($link, LOCK_EX);
    return $link;
}

# The lock of the installation as written, or null when it is missing or no JSON object
function getApplyLock(): ?array {
    $file = getApplyDir().'/apply.lock';
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    return is_array($data) ? $data : null;
}

# Write one JSON file by one rename, so a reader never sees half of it
function setApplyJson(string $file, array $data): bool {
    $temp = $file.'.'.bin2hex(random_bytes(6));
    if (file_put_contents($temp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n", LOCK_EX) === false) return false;
    if (rename($temp, $file)) return true;
    unlink($temp);
    return false;
}

# Write the lock after a portion; a lock that cannot be written stops the request before anything else moves
function setApplyLock(array $lock): array {
    if (setApplyJson(getApplyDir().'/apply.lock', $lock)) return $lock;
    $text = 'The lock storage/update/client/apply.lock cannot be written. Give PHP the right to write into storage/update/client/ and continue, '
        .'or roll back with the rescue code.';
    setApplyPage('Installation paused', $text, [], '', 503);
}

# The plan of the backup: the moves in their order and the folders the installation creates, or an empty plan when it cannot be read
function getApplyPlan(array $lock): array {
    $file = BASE_DIR.'/'.$lock['back'].'/plan.json';
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    return (is_array($data) && ($data['ver'] ?? null) === 1 && is_array($data['moves'] ?? null)) ? $data : ['ver' => 1, 'moves' => [], 'dirs' => []];
}

# Merge fields into client/state.json and keep every other field of it
function setApplyState(array $data): bool {
    $file = getApplyDir().'/state.json';
    $old = is_file($file) ? json_decode((string)file_get_contents($file), true) : [];
    return setApplyJson($file, array_replace(is_array($old) ? $old : [], $data));
}

# A connection to the site database from config/db.php alone, throwing on every failed statement as the portion mode of Backup requires
function getApplyLink(): PDO {
    $conf = (require (defined('CONFIG_DIR') ? CONFIG_DIR : BASE_DIR.'/config').'/db.php')['db'];
    $dsn = 'mysql:host='.$conf['host'].';dbname='.$conf['name'].';charset=utf8mb4';
    return new PDO($dsn, $conf['uname'], $conf['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# Create a folder with each missing parent at 0755
function addApplyPath(string $dir): bool {
    if (is_dir($dir)) return true;
    if (!addApplyPath(dirname($dir)) || (!mkdir($dir) && !is_dir($dir))) return false;
    return chmod($dir, 0755);
}

# Remove a file or a folder with all below it, never following a link out of it
function deleteApplyTree(string $path): bool {
    if (is_link($path) || is_file($path)) return unlink($path);
    if (!is_dir($path)) return true;
    foreach (scandir($path) ?: [] as $name) {
        if ($name !== '.' && $name !== '..' && !deleteApplyTree($path.'/'.$name)) return false;
    }
    return rmdir($path);
}

# Answer one page of the installation on the shell of public/error.html, which needs no core, and end the request; $act is the address of the form, empty for none
function setApplyPage(string $title, string $text, array $hide, string $act, int $code = 200, bool $auto = false, string $verb = 'post'): never {
    while (ob_get_level() > 0) ob_end_clean();
    $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    $form = '';
    if ($act !== '' && !str_contains($act, '/')) $act = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/x'))), '/').'/'.$act;
    if ($act !== '') {
        $form = '<form id="sl-apply-form" class="sl-search-form sl-msg-search" method="'.$verb.'" action="'.$esc($act).'">';
        foreach ($hide as $key => $val) $form .= '<input type="hidden" name="'.$esc((string)$key).'" value="'.$esc((string)$val).'">';
        $form .= '<span class="sl-field">'.$esc($auto ? 'Continues by itself' : 'Continue').'</span>'
            .'<button type="submit" class="sl-circle-action" aria-label="Continue" title="Continue"><svg class="bi" viewBox="0 0 16 16" aria-hidden="true">'
            .'<path d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/>'
            .'</svg></button></form>';
    }
    $body = '<h1 class="sl-msg-title">'.$esc($title).'</h1><div class="sl-msg-body"><div class="sl-alert sl-alert-warn"><div class="sl-alert-body"><p class="sl-alert-text">'
        .$esc($text).'</p></div></div></div>'.$form;
    $html = is_file(PUBLIC_DIR.'/error.html') ? (string)file_get_contents(PUBLIC_DIR.'/error.html') : '';
    $from = strpos($html, '<h1 class="sl-msg-title">');
    $to = ($from === false) ? false : strpos($html, '</form>', $from);
    $html = ($to === false) ? '<!doctype html><html lang="en"><head><meta charset="utf-8"><title></title></head><body>'.$body.'</body></html>'
        : substr($html, 0, $from).$body.substr($html, $to + 7);
    $html = (string)preg_replace('#<title>.*?</title>#s', '<title>'.$esc($title).' - SLAED CMS</title>', $html, 1);
    if ($auto && $form !== '') $html = str_replace('</body>', '<script>setTimeout(function(){document.getElementById(\'sl-apply-form\').submit();},300);</script></body>', $html);
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    echo $html;
    exit;
}

# The page that carries the token on to the next portion
function setApplyNext(array $lock, string $tok, string $text): never {
    setApplyPage('Installing SLAED CMS '.$lock['to'], $text, ['apply' => $tok], $lock['entry'], 200, true);
}

# Stop on steps 3 and 4, where neither files nor database were touched: the backup and the run copy go, the lock goes and the site opens again
function setApplyStop(array $lock, string $error): never {
    deleteApplyTree(BASE_DIR.'/'.$lock['back']);
    deleteApplyTree(getApplyDir().'/run');
    $why = substr($error, 0, 300);
    setApplyState(['fail' => ['ver' => $lock['to'], 'step' => 4], 'undo' => ['from' => $lock['to'], 'to' => $lock['from'], 'time' => time(), 'why' => 'dump', 'step' => 4,
        'error' => $why]]);
    unlink(getApplyDir().'/apply.lock');
    setApplyPage('Installation stopped', 'The backup of the database failed, so nothing was changed and the site is open again: '.$why, ['name' => 'update'], $lock['entry'],
        200, false, 'get');
}

# Hand a failed step from 5 on to the installed rescuer: the lock names the rollback, and the next request with the token reaches core/rescue.php through the gate
function setApplyRoll(array $lock, string $tok, string $why, int $step, string $error): never {
    $lock['roll'] = ['why' => $why, 'step' => $step, 'error' => substr($error, 0, 300), 'part' => 'base', 'rest' => null, 'at' => max($lock['at'], $lock['upto'])];
    $lock['phase'] = 'roll';
    setApplyLock($lock);
    $text = 'Step '.$step.' failed ('.$lock['roll']['error'].'). The previous version is being restored.';
    setApplyPage('Installation failed', $text, ['apply' => $tok], $lock['entry'], 200, true);
}

# Note a fatal error of the phase with the new core in the lock, so the next request with the token rolls back instead of repeating the crash
function setApplyFatal(): void {
    $err = error_get_last();
    if ($err === null || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
    $lock = getApplyLock();
    if (!is_array($lock) || ($lock['phase'] ?? '') !== 'core') return;
    $lock['roll'] = ['why' => 'fatal', 'step' => 6, 'error' => substr($err['message'], 0, 300), 'part' => 'base', 'rest' => null, 'at' => max($lock['at'], $lock['upto'])];
    $lock['phase'] = 'roll';
    setApplyJson(getApplyDir().'/apply.lock', $lock);
}

# Step 4: the next portions of the database dump through the portion mode of the installed Backup, until it is done or the time of the request is spent
function addApplyDump(array $lock, float $end): array {
    if (!defined('FUNC_FILE')) define('FUNC_FILE', true);
    require_once BASE_DIR.'/core/classes/backup.php';
    try {
        $pdo = getApplyLink();
        $state = is_array($lock['dump']) ? $lock['dump'] : Backup::getDumpState(BASE_DIR.'/'.$lock['back'].'/dump', $lock['prefix'], $lock['tabs']);
        do {
            $state = Backup::addDumpPart($pdo, $state);
        } while (!$state['done'] && microtime(true) < $end);
    } catch (Throwable $error) {
        setApplyStop($lock, $error->getMessage());
    }
    $lock['dump'] = $state;
    if ($state['done']) $lock['phase'] = 'files';
    return setApplyLock($lock);
}

# Move one file of the plan: the old one into the backup, the new one out of stage/; a move repeated after a cut request finishes what it had begun
function setApplyMove(array $one, string $stage, string $back): bool {
    $dest = BASE_DIR.'/'.$one['path'];
    $keep = $back.'/files/'.$one['path'];
    $src = ($one['op'] === 'put') ? $stage.'/'.$one['src'] : '';
    if ($one['op'] === 'put' && !is_file($src)) return is_file($dest);
    if (is_file($dest)) {
        if (is_file($keep) || !addApplyPath(dirname($keep)) || !rename($dest, $keep)) return false;
    }
    return $one['op'] === 'drop' || (addApplyPath(dirname($dest)) && rename($src, $dest));
}

# Whether the free space holds $need bytes and a reserve of 1 MB, true where the host hides it, since then only a failed write can tell
function checkApplySpace(int $need): bool {
    $free = function_exists('disk_free_space') ? disk_free_space(BASE_DIR) : false;
    return !is_float($free) || $free >= $need + 1048576;
}

# Step 5: the next batches of moves, each marked in the lock before it runs, so a rollback knows how far a cut batch may have gone; the entries come last
function addApplyFiles(array $lock, string $tok, float $end): array {
    $moves = getApplyPlan($lock)['moves'];
    $stage = BASE_DIR.'/'.$lock['stage'];
    $back = BASE_DIR.'/'.$lock['back'];
    $copy = stat($stage)['dev'] !== stat(BASE_DIR)['dev'] || stat($back)['dev'] !== stat(BASE_DIR)['dev'];
    set_error_handler(static fn(): bool => true);
    while ($lock['at'] < count($moves) && microtime(true) < $end) {
        $upto = min(count($moves), $lock['at'] + 100);
        $need = 0;
        for ($i = $lock['at']; $copy && $i < $upto; $i++) {
            if (is_file(BASE_DIR.'/'.$moves[$i]['path'])) $need += (int)filesize(BASE_DIR.'/'.$moves[$i]['path']);
            if ($moves[$i]['op'] === 'put' && is_file($stage.'/'.$moves[$i]['src'])) $need += (int)filesize($stage.'/'.$moves[$i]['src']);
        }
        if (!checkApplySpace($need)) setApplyRoll($lock, $tok, 'space', 5, 'no space left for the next batch of files');
        $lock['upto'] = $upto;
        setApplyLock($lock);
        for ($i = $lock['at']; $i < $upto; $i++) {
            if (!setApplyMove($moves[$i], $stage, $back)) setApplyRoll($lock, $tok, 'files', 5, 'cannot move '.$moves[$i]['path']);
        }
        $lock['at'] = $upto;
        setApplyLock($lock);
    }
    if ($lock['at'] < count($moves)) {
        restore_error_handler();
        return $lock;
    }
    foreach ($moves as $one) {
        $dir = dirname(BASE_DIR.'/'.$one['path']);
        while ($one['op'] === 'drop' && strlen($dir) > strlen(BASE_DIR) && is_dir($dir) && count(scandir($dir) ?: []) === 2 && rmdir($dir)) $dir = dirname($dir);
    }
    $live = function_exists('opcache_get_status') && is_array(opcache_get_status(false));
    if (function_exists('opcache_reset')) opcache_reset();
    restore_error_handler();
    if ($live && (bool)ini_get('opcache.validate_timestamps')) sleep(min(max(0, intval(ini_get('opcache.revalidate_freq'))), 30));
    $lock['phase'] = 'core';
    return setApplyLock($lock);
}

# Step 6 before the new core boots: config/ without local.php into the backup once, the cached configuration gone, and the database marked as touched from here on
function setApplyConf(array $lock, string $tok): array {
    $cdir = defined('CONFIG_DIR') ? CONFIG_DIR : BASE_DIR.'/config';
    $back = BASE_DIR.'/'.$lock['back'].'/config';
    if (!$lock['conf']) {
        deleteApplyTree($back);
        if (!addApplyPath($back)) setApplyRoll($lock, $tok, 'config', 6, 'cannot create the backup of config/');
        foreach (glob($cdir.'/*.php') ?: [] as $file) {
            if (basename($file) !== 'local.php' && !copy($file, $back.'/'.basename($file))) setApplyRoll($lock, $tok, 'config', 6, 'cannot back up config/'.basename($file));
        }
        $lock['conf'] = true;
    }
    if (is_file($cdir.'/local.php')) unlink($cdir.'/local.php');
    $lock['base'] = true;
    return setApplyLock($lock);
}

# Step 6 and 7 with the new core in its light mode: the database steps in order, then the merge of config/ from the package, then the caches and the cached configuration
function addApplyCore(array $lock, string $tok): array {
    global $conf, $db;
    try {
        $res = Update::addSteps($db, $conf);
        if (!$res['ok']) setApplyRoll($lock, $tok, 'steps', 6, $res['ver'].': '.$res['error']);
        $res = Update::addConfigMerge(BASE_DIR.'/'.$lock['stage']);
        if (!$res['ok']) setApplyRoll($lock, $tok, 'merge', 6, $res['error'].' '.$res['name']);
    } catch (Throwable $error) {
        setApplyRoll($lock, $tok, 'steps', 6, $error->getMessage());
    }
    if (is_file(CONFIG_DIR.'/local.php')) unlink(CONFIG_DIR.'/local.php');
    Cache::deleteAll();
    if (function_exists('opcache_reset')) opcache_reset();
    Logger::addAdmin('info', 'Update installed', ['event' => 'update', 'result' => 'done', 'from' => $lock['from'], 'to' => $lock['to'], 'kind' => $lock['kind']]);
    $lock['phase'] = 'open';
    return setApplyLock($lock);
}

# Step 8: the rescuer of the package takes its place by the same move, stage/ and the run copy go, the lock goes and the site opens; the backup stays for going back
function addApplyOpen(array $lock, string $tok): never {
    $plan = getApplyPlan($lock);
    $stage = BASE_DIR.'/'.$lock['stage'];
    $back = BASE_DIR.'/'.$lock['back'];
    $src = $stage.'/core/rescue.php';
    $swap = ['op' => 'put', 'src' => 'core/rescue.php', 'path' => 'core/rescue.php', 'old' => is_file(BASE_DIR.'/core/rescue.php')];
    $has = in_array('core/rescue.php', array_column($plan['moves'], 'path'), true);
    $diff = is_file($src) && (!$swap['old'] || hash_file('sha256', $src) !== hash_file('sha256', BASE_DIR.'/core/rescue.php'));
    if ($has || $diff) {
        if (!$has) {
            $plan['moves'][] = $swap;
            if (!setApplyJson($back.'/plan.json', $plan)) setApplyRoll($lock, $tok, 'open', 8, 'cannot write the plan of the backup');
        }
        $lock['upto'] = count($plan['moves']);
        setApplyLock($lock);
        set_error_handler(static fn(): bool => true);
        $done = setApplyMove($swap, $stage, $back);
        restore_error_handler();
        if (!$done) setApplyRoll($lock, $tok, 'open', 8, 'cannot move core/rescue.php');
        $lock['at'] = count($plan['moves']);
        setApplyLock($lock);
    }
    $sets = (static fn(string $path): mixed => is_file($path) ? include $path : [])((defined('CONFIG_DIR') ? CONFIG_DIR : BASE_DIR.'/config').'/update.php');
    $keep = intval($sets['update']['check']['keepbak'] ?? 1) === 1;
    if (!$keep) deleteApplyTree($back);
    deleteApplyTree(getApplyDir().'/stage');
    setApplyState(['done' => ['from' => $lock['from'], 'to' => $lock['to'], 'kind' => $lock['kind'], 'time' => time(), 'back' => $keep ? $lock['back'] : '', 'check' => 'wait'],
        'stage' => [], 'found' => [], 'fault' => []]);
    unlink(getApplyDir().'/apply.lock');
    deleteApplyTree(getApplyDir().'/run');
    setApplyPage('SLAED CMS '.$lock['to'].' is installed', 'The site is open again. The panel now checks that the site answers.', ['name' => 'update'], $lock['entry'], 200,
        true, 'get');
}

$ahold = getApplyHold();
$alock = getApplyLock();
$atok = (is_string($_POST['apply'] ?? null) && preg_match('#^[0-9a-f]{64}$#D', $_POST['apply']) === 1) ? $_POST['apply'] : '';
if ($alock === null || ($alock['ver'] ?? null) !== 1) setApplyPage('Installation refused', 'This executor knows format 1 of storage/update/client/apply.lock only. Roll back '
    .'with the rescue code.', [], '', 503);
if ($atok === '' || !hash_equals((string)$alock['token'], hash('sha256', $atok))) {
    setApplyPage('Installation refused', 'The request carries no token of this installation.', [], '', 403);
}
if ($alock['phase'] === 'roll') setApplyNext($alock, $atok, 'The installation is being rolled back.');
$aspan = intval(ini_get('max_execution_time'));
$aend = microtime(true) + (($aspan > 0) ? min(20, max(5, intdiv($aspan, 3))) : 20);
if ($alock['phase'] === 'dump') {
    $alock = addApplyDump($alock, $aend);
    setApplyNext($alock, $atok, $alock['dump']['done'] ? 'Step 4 of 9: the database is backed up.' : 'Step 4 of 9: backing up the database, part '
        .count($alock['dump']['parts']).'.');
}
if ($alock['phase'] === 'files') {
    $alock = addApplyFiles($alock, $atok, $aend);
    setApplyNext($alock, $atok, 'Step 5 of 9: files, '.$alock['at'].' of '.count(getApplyPlan($alock)['moves']).'.');
}
if ($alock['phase'] === 'core') {
    if (!defined('ADMIN_FILE')) setApplyNext($alock, $atok, 'Step 6 of 9: the database steps run in the panel.');
    $alock = setApplyConf($alock, $atok);
    register_shutdown_function('setApplyFatal');
    define('UPDATE_FILE', true);
    require_once BASE_DIR.'/core/system.php';
    require_once BASE_DIR.'/core/classes/update.php';
    $alock = addApplyCore($alock, $atok);
    setApplyNext($alock, $atok, 'Step 7 of 9: the database steps ran, the settings and caches are renewed.');
}
if ($alock['phase'] === 'open') addApplyOpen($alock, $atok);
setApplyPage('Installation paused', 'The installation stands in phase '.$alock['phase'].'. Roll back with the rescue code.', [], '', 503);
