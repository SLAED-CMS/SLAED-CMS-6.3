<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the handlers behind the foot of a panel list: each takes the rows of id[] and refuses the whole post without its token
# It lifts one handler out of its module and runs it over stubs and scripted rows, so no scenario boots the core or touches a database
# The scenario comes base64 encoded, since the shell of Windows strips the quotes of a JSON argument
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
$pwork = str_replace('\\', '/', (string)($argv[1] ?? ''));
$pcase = json_decode((string)base64_decode((string)($argv[2] ?? '')), true);
$psite = str_replace('\\', '/', dirname(__DIR__, 2));
if ($pwork === '' || !is_array($pcase) || !isset($pcase['file'], $pcase['func'])) {
    echo json_encode(['error' => 'usage']);
    exit;
}
foreach (['logs', 'config'] as $dir) {
    if (!is_dir($pwork.'/'.$dir)) mkdir($pwork.'/'.$dir, 0777, true);
}
define('PREFIX_DB', 'probe');
define('LOGS_DIR', $pwork.'/logs');
define('CONFIG_DIR', $pwork.'/config');
foreach (['_TOKENMISS', '_SUCCDELETE', '_SUCCSTATUS', '_SUCCSAVE', '_ERROR', '_NLBUSY', '_BANN_INFO'] as $name) define($name, strtolower($name));
set_time_limit(20);
$pdone = ['error' => '', 'data' => ['write' => [], 'call' => [], 'cfg' => []]];

# Cut one top-level function out of a shipped source and load it into this process
function setProbeLift(string $file, string $func, string $into): void {
    $code = (string)file_get_contents($file);
    $from = strpos($code, "\nfunction ".$func.'(');
    $end = ($from === false) ? false : strpos($code, "\n}\n", $from);
    if ($end === false) throw new RuntimeException('Function '.$func.' was not found');
    file_put_contents($into, "<?php\n".substr($code, $from, $end - $from + 3));
    require $into;
}

# The token of the scenario: present or missing
function checkAdminPost(string $scope): bool {
    return !empty($GLOBALS['pcase']['token']);
}

# Answer a posted field as the core does: key[] is the filtered list without empty items, a plain key never reads a list
function getVar(string $typ, string $name, string $filt = '', mixed $def = ''): mixed {
    $many = str_ends_with($name, '[]');
    $val = $GLOBALS['pcase']['post'][$many ? substr($name, 0, -2) : $name] ?? null;
    $cast = static fn(mixed $v): mixed => match ($filt) {'num' => filterNum($v), 'var' => is_string($v) ? filterVar($v) : $v, default => $v};
    if ($many) return is_array($val) ? array_values(array_filter(array_map($cast, $val), static fn(mixed $v): bool => $v !== '' && $v !== null && $v !== false)) : [];
    return ($val === null || is_array($val)) ? $def : $cast($val);
}

# Record the answer of the handler and leave it the way a redirect does
function setRedirect(string $url, bool $refer = false, int $code = 302, string $text = '', bool $warn = false): never {
    $GLOBALS['pdone']['data'] += ['url' => $url, 'text' => $text, 'warn' => $warn];
    throw new LogicException('redirect');
}

# Record a written configuration instead of writing it
function setConfigFile(string $name, array $cont): void {
    $GLOBALS['pdone']['data']['cfg'][$name] = $cont;
}

# Record the result a sweep sets on the journal record of its post
function setAdminLog(array $data): void {
    $GLOBALS['pdone']['data']['log'] = $data;
}

# No Node type is installed for the search filter of a scenario
function getNodeTypeMap(): array {
    return [];
}

final class ProbeRes {
    # Hold the scripted answer of a read and the rows of a sweep portion
    public function __construct(private mixed $col = false, public array $rows = []) {}

    # Every write changed one row
    public function rowCount(): int {
        return 1;
    }

    # The scripted column of a read, false when the scenario has none
    public function fetchColumn(): mixed {
        return $this->col;
    }

    # The scripted row of a read, false when the scenario has none
    public function fetch(): array|false {
        return is_array($this->col) ? $this->col : false;
    }
}

final class ProbeDb {
    # Record a write instead of running it and answer a read from the rows of the scenario
    public function getSqlQuery(string $sql, array $pars = []): ProbeRes {
        if (!str_starts_with($sql, 'SELECT')) {
            $GLOBALS['pdone']['data']['write'][] = ['sql' => strtok($sql, ' '), 'pars' => $pars];
            return new ProbeRes();
        }
        if (str_contains($sql, ':upto')) {
            $GLOBALS['pdone']['data']['sweep'] = ['sql' => $sql, 'pars' => $pars];
            return new ProbeRes(false, array_map(static fn(int $v): array => ['id' => $v], (array)($GLOBALS['pcase']['keys'] ?? [])));
        }
        return new ProbeRes($GLOBALS['pcase']['read'][(string)reset($pars)] ?? false);
    }

    # The row of the statement, or none
    public function getSqlRow(ProbeRes $res): ?array {
        return $res->fetch() ?: null;
    }

    # The scripted keys of a sweep portion
    public function getSqlRows(ProbeRes $res): array {
        return $res->rows;
    }

    # A transaction always opens
    public function setSqlBegin(): bool {
        return true;
    }

    # A transaction always commits
    public function setSqlCommit(): bool {
        return true;
    }

    # A rollback always succeeds
    public function setSqlRollback(): bool {
        return true;
    }
}

final class ProbeCall {
    # Record a call of a class the handler leans on and answer the number of the rows it was handed
    public function __call(string $name, array $args): mixed {
        if (str_ends_with($name, 'Scope')) return [PREFIX_DB.'_'.$name, ' WHERE kind = :kind OR status = :stat', ['kind' => 'probe', 'stat' => 2]];
        $GLOBALS['pdone']['data']['call'][] = [$name, ...$args];
        if ($name === 'getCampLeft') return intval($GLOBALS['pcase']['left'][(string)$args[0]] ?? 0);
        return is_array($args[0] ?? null) ? count($args[0]) : true;
    }
}

final class Logger {
    # Record the journal line of an unlinked account
    public static function addOauth(string $level, string $event, array $data = []): void {
        $GLOBALS['pdone']['data']['call'][] = ['addOauth', $event, $data];
    }
}

ob_start();
register_shutdown_function(static function (): void {
    global $pdone;
    $pdone['data']['out'] = (string)ob_get_clean();
    $pdone['data']['logs'] = array_values(array_map(static fn(string $v): string => basename($v, '.log'), glob(LOGS_DIR.'/*.log') ?: []));
    sort($pdone['data']['logs']);
    echo json_encode($pdone);
});
try {
    setProbeLift($psite.'/core/security.php', 'filterNum', $pwork.'/lift_num.php');
    setProbeLift($psite.'/core/security.php', 'filterVar', $pwork.'/lift_var.php');
    setProbeLift($psite.'/core/helpers.php', 'setListPortion', $pwork.'/lift_portion.php');
    foreach (['getIpNorm', 'getIpFamily', 'getIpCidr'] as $func) setProbeLift($psite.'/core/security.php', $func, $pwork.'/lift_'.strtolower($func).'.php');
    foreach ((array)($pcase['logs'] ?? []) as $one) file_put_contents(LOGS_DIR.'/'.$one.'.log', 'x');
    if (isset($pcase['config'])) file_put_contents(CONFIG_DIR.'/'.$pcase['config']['file'], '<?php return '.var_export($pcase['config']['data'], true).";\n");
    $db = new ProbeDb();
    $prv = new ProbeCall();
    $mailer = new ProbeCall();
    $admin = [1];
    $afile = 'admin';
    $conf = (array)($pcase['conf'] ?? []);
    foreach ([...(array)($pcase['lift'] ?? []), $pcase['func']] as $i => $func) setProbeLift($psite.'/'.$pcase['file'], $func, $pwork.'/lift_'.$i.'.php');
    try {
        ($pcase['func'])();
    } catch (LogicException) {
    }
} catch (Throwable $err) {
    $pdone['error'] = $err->getMessage();
}
