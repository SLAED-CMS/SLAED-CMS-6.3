<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for isModGroup(): the function is lifted out of core/user.php and run over a scripted account, module and group, so no scenario boots the core
# The rows carry native integers as PDO answers them with emulated prepares off, which is what a string comparison of the group flag never matched
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
if ($pwork === '' || !is_array($pcase)) {
    echo json_encode(['error' => 'usage']);
    exit;
}
if (!is_dir($pwork)) mkdir($pwork, 0777, true);
define('PREFIX_DB', 'probe');
set_time_limit(20);
$pdone = ['error' => '', 'data' => []];

# Cut one top-level function out of a shipped source and load it into this process
function setProbeLift(string $file, string $func, string $into): void {
    $code = (string)file_get_contents($file);
    $from = strpos($code, "\nfunction ".$func.'(');
    $end = ($from === false) ? false : strpos($code, "\n}\n", $from);
    if ($end === false) throw new RuntimeException('Function '.$func.' was not found');
    file_put_contents($into, "<?php\n".substr($code, $from, $end - $from + 3));
    require $into;
}

# Answer whether the scenario is signed in
function is_user(): bool {
    return !empty($GLOBALS['pcase']['user']);
}

final class ProbeDb {
    # Answer the scripted row of the table a read names
    public function getSqlQuery(string $sql, array $pars = []): array {
        $GLOBALS['pdone']['data']['read'][] = $pars;
        return str_contains($sql, '_groups') ? ($GLOBALS['pcase']['group'] ?? []) : ($GLOBALS['pcase']['account'] ?? []);
    }

    # Answer the row of the statement, or none
    public function getSqlRow(array $res): ?array {
        return $res ?: null;
    }
}

try {
    setProbeLift($psite.'/core/user.php', 'isModGroup', $pwork.'/lift_modgroup.php');
    $db = new ProbeDb();
    $user = [7, 'probe'];
    $conf = ['modules' => ['forum' => ['group' => $pcase['limit'] ?? 0]]];
    $pdone['data']['open'] = isModGroup('forum');
} catch (Throwable $err) {
    $pdone['error'] = $err->getMessage();
}
echo json_encode($pdone);
