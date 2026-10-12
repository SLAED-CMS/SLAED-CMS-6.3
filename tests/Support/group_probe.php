<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the group form of the panel: the save of a group and the next step of the ladder a new group is offered
# It lifts the shipped functions out of their sources and runs them over stubs and scripted rows, so no scenario boots the core or touches a database
# The scenario comes base64 encoded, since the shell of Windows strips the quotes of a JSON argument
# Modes: save <json> posts the fields of the scenario against one taken threshold or none; next <json> answers the step after the top group of the scenario
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
$pmode = (string)($argv[1] ?? '');
$pwork = str_replace('\\', '/', (string)($argv[2] ?? ''));
$pcase = json_decode((string)base64_decode((string)($argv[3] ?? '')), true);
$psite = str_replace('\\', '/', dirname(__DIR__, 2));
if ($pwork === '' || !is_array($pcase) || !in_array($pmode, ['save', 'next'], true)) {
    echo json_encode(['error' => 'usage']);
    exit;
}
if (!is_dir($pwork)) mkdir($pwork, 0777, true);
define('PREFIX_DB', 'probe');
foreach (['_CERROR', '_NONUMVALUE', '_TOKENMISS', '_SUCCSAVE', '_GROUPS_BADRANK', '_GROUPS_BADCOLOR'] as $name) define($name, strtolower($name));
define('_GROUPS_TAKEN', 'taken %s %s');
foreach (['_GROUPS_GRAY', '_GROUPS_BROWN', '_GROUPS_RED', '_GROUPS_PINK', '_GROUPS_ORANGE', '_GROUPS_AMBER', '_GROUPS_OLIVE', '_GROUPS_LIME', '_GROUPS_GREEN',
    '_GROUPS_TEAL', '_GROUPS_BLUE', '_GROUPS_NAVY', '_GROUPS_NIGHT', '_GROUPS_VIOLET'] as $name) define($name, $name);
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

# The token check passed: only the fields are under test
function checkAdminPost(string $scope): bool {
    return true;
}

# Answer the posted field of the scenario through the filter the handler names, as the core does for numbers
function getVar(string $typ, string $name, string $filt = '', mixed $def = ''): mixed {
    $val = $GLOBALS['pcase']['post'][$name] ?? $def;
    return ($filt === 'num') ? filterNum($val) : $val;
}

# Record the answer of the handler and leave it the way a redirect does
function setRedirect(string $url, bool $refer = false, int $code = 302, string $text = '', bool $warn = false): never {
    $GLOBALS['pdone']['data'] += ['url' => $url, 'text' => $text, 'warn' => $warn];
    throw new LogicException('redirect');
}

# Record the refusal the handler hands back to its form instead of rendering it
function add(): void {
    $GLOBALS['pdone']['data']['stop'] = $GLOBALS['stop'];
}

final class ProbeDb {
    # Answer the scripted row of a read, and record a write instead of running it
    public function getSqlQuery(string $sql, array $pars = []): array {
        if (str_starts_with($sql, 'INSERT') || str_starts_with($sql, 'UPDATE')) {
            $GLOBALS['pdone']['data']['write'] = $pars;
            return [];
        }
        $GLOBALS['pdone']['data']['read'][] = $pars;
        return $GLOBALS['pcase']['row'] ?? [];
    }

    # Answer the row of the statement, or none
    public function getSqlRow(array $res): array|false {
        return $res ?: false;
    }
}

try {
    setProbeLift($psite.'/core/security.php', 'filterNum', $pwork.'/lift_num.php');
    setProbeLift($psite.'/core/security.php', 'filterCutText', $pwork.'/lift_cut.php');
    setProbeLift($psite.'/core/system.php', 'getRankData', $pwork.'/lift_rank.php');
    $db = new ProbeDb();
    $afile = 'admin';
    $stop = '';
    if ($pmode === 'save') {
        setProbeLift($psite.'/admin/modules/groups.php', 'save', $pwork.'/lift_save.php');
        try {
            save();
        } catch (LogicException) {
        }
    } else {
        setProbeLift($psite.'/admin/modules/groups.php', 'getGroupTones', $pwork.'/lift_tones.php');
        setProbeLift($psite.'/admin/modules/groups.php', 'getGroupNext', $pwork.'/lift_next.php');
        $pdone['data']['next'] = getGroupNext();
    }
} catch (Throwable $err) {
    $pdone['error'] = $err->getMessage();
}
echo json_encode($pdone);
