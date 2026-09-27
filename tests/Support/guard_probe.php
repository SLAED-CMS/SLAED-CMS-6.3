<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for guards of the panel: the escaping of the debug panel, the rename of the admin file, the parent of a category and the repair of the forum last message
# It lifts the shipped function out of its source and runs it over stubs and an in-memory table, so no scenario boots the core or touches a database
# BASE_DIR is the scratch root the caller passed, so every rename the scenarios ask for happens inside it and nothing below the site moves
# Modes: debug prints the panel over a hostile cookie; afile <name> posts that admin file name; catparent <id:parent> saves a forum category; forumlast repairs a cycle
# Mode epoch writes through the shipped Database of the panel over SQLite: online tracking, then two content rows; it reports the generation after each and after shutdown
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
$pmode = (string)($argv[1] ?? '');
$pwork = str_replace('\\', '/', (string)($argv[2] ?? ''));
$pname = (string)($argv[3] ?? '');
$psite = str_replace('\\', '/', dirname(__DIR__, 2));
if ($pwork === '' || !in_array($pmode, ['debug', 'afile', 'catparent', 'forumlast', 'epoch'], true)) {
    echo json_encode(['error' => 'usage']);
    exit;
}
if (!is_dir($pwork.'/root')) mkdir($pwork.'/root', 0777, true);
define('BASE_DIR', $pwork.'/root');
define('DBG_MARK', 'mark');
define('_SYSTEM_INFO', 'info');
define('_ERRLOG', 'errlog');
define('_AVARIABLES', 'variables');
define('_AQUERY_DB', 'queries');
define('_TOKENMISS', 'tokenmiss');
define('_SUCCSAVE', 'saved');
define('_ADMIN_FILE_ERR', 'badname');
define('_CATPARENT', 'badparent');
define('PREFIX_DB', 'probe');
set_time_limit(20);
ini_set('memory_limit', '128M');
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

# The debug panel is open in every scenario: the visibility rule has its own tests
function checkDebugView(): bool {
    return true;
}

# No super-admin, so the error log of the panel stays out of the output
function isAdmin(bool $sup = false): bool {
    return false;
}

# The token check passed: only the rename is under test
function checkAdminPost(string $scope): bool {
    return true;
}

# Answer the posted field of the scenario, or the default of the call
function getVar(string $typ, string $name, string $filt = '', mixed $def = null): mixed {
    return $GLOBALS['ppost'][$name] ?? $def;
}

# Record the configuration the handler would store
function setConfigFile(string $name, array $arr = []): bool {
    $GLOBALS['pdone']['data']['saved'] = $arr['afile'] ?? null;
    return true;
}

# Record the answer of the handler and leave it the way a redirect does
function setRedirect(string $url, bool $refer = false, int $code = 302, string $text = '', bool $warn = false): never {
    $GLOBALS['pdone']['data'] += ['url' => $url, 'text' => $text, 'warn' => $warn];
    throw new LogicException('redirect');
}

# Grant the rights of the category form their defaults: only the parent is under test
function getCategoryRights(): array {
    return ['pview' => '0|0'];
}

# Answer the Node registry that no forum is a Node type, so the save takes the plain branch
function getNodeWriter(): object {
    return new class {
        # Answer that none of the modules is a registered Node type
        public function checkTypeRegistry(array $mods): bool {
            return false;
        }
    };
}

# Record every category set the repair asks the last message of, instead of reading the forum table
function getForumLast(array $keys): int {
    $GLOBALS['pdone']['data']['subs'][] = $keys;
    return 0;
}

final class ProbeDb {
    # Hold the category rows of the scenario as id, parent and advertised last message
    public function __construct(private array $rows) {
    }

    # Answer the read of a scenario as a statement over the category rows, and record a write instead of running it
    public function getSqlQuery(string $sql, array $pars = []): object|bool {
        if (str_starts_with($sql, 'UPDATE')) {
            $GLOBALS['pdone']['data']['update'][] = $pars;
            return true;
        }
        return new class($this->rows) {
            # Hold the rows the statement answers
            public function __construct(private array $rows) {
            }

            # Answer every row of the scenario
            public function fetchAll(int $mode = 0): array {
                return $this->rows;
            }

            # Answer the stored module of the category the save reads before it decides the branch
            public function fetchColumn(): string {
                return 'forum';
            }
        };
    }

    # Answer every row of a statement
    public function getSqlRows(object $res): array {
        return $res->fetchAll();
    }
}

final class ProbeTpl {
    # Render a debug section as its bare content, which is what the fragment prints unescaped
    public function getHtmlFrag(string $name, array $row): string {
        return $row['content'];
    }
}

try {
    if ($pmode === 'debug') {
        setProbeLift($psite.'/core/system.php', 'getVariables', $pwork.'/lift_debug.php');
        $conf = ['variables' => '0,0,1,1,1,1,1,1,0'];
        $tpl = new ProbeTpl();
        $db = null;
        $_POST = ['post' => '<b>post</b>'];
        $_GET = ['get' => '<i>get</i>'];
        $_COOKIE = ['cookie' => '<script>cookie</script>'];
        $_FILES = ['file' => ['name' => '<img src=x onerror=files>']];
        $_SESSION = ['session' => '<svg onload=session>'];
        $_SERVER = ['HTTP_USER_AGENT' => '<iframe>server</iframe>'];
        $pdone['data']['html'] = getVariables();
    } elseif ($pmode === 'catparent') {
        setProbeLift($psite.'/admin/modules/categories.php', 'save', $pwork.'/lift_cat.php');
        [$pid, $ppar] = array_map('intval', explode(':', $pname));
        $db = new ProbeDb([['id' => 1, 'parent' => 0], ['id' => 2, 'parent' => 1], ['id' => 3, 'parent' => 2], ['id' => 4, 'parent' => 0]]);
        $afile = 'admin';
        $ppost = ['id' => $pid, 'modul' => 'forum', 'title' => 'probe', 'parent' => $ppar, 'status' => 1];
        try {
            save();
        } catch (LogicException) {
        }
    } elseif ($pmode === 'forumlast') {
        setProbeLift($psite.'/core/system.php', 'setForumLast', $pwork.'/lift_last.php');
        $db = new ProbeDb([
            ['id' => 1, 'parent' => 2, 'lpost' => 5],
            ['id' => 2, 'parent' => 1, 'lpost' => 5],
            ['id' => 3, 'parent' => 1, 'lpost' => 7],
            ['id' => 4, 'parent' => 0, 'lpost' => 9],
        ]);
        setForumLast(3, 9);
        $pdone['data']['done'] = true;
    } elseif ($pmode === 'epoch') {
        define('FUNC_FILE', true);
        define('ADMIN_FILE', true);
        define('COUNTER_DIR', $pwork.'/counter');
        define('CACHE_DIR', $pwork.'/cache');
        require $psite.'/core/classes/cache.php';
        require $psite.'/core/classes/pdo.php';
        $conf = ['security' => ['error_log' => 0]];
        $db = (new ReflectionClass('Database'))->newInstanceWithoutConstructor();
        $db->sqlconnid = new PDO('sqlite::memory:');
        $db->getSqlQuery('CREATE TABLE probe (id INTEGER)');
        $db->getSqlQuery('CREATE TABLE probe_session (id INTEGER)');
        foreach (['INSERT INTO probe_session VALUES (1)', 'UPDATE probe_session SET id = 2', 'DELETE FROM probe_session WHERE id = 3'] as $sql) $db->getSqlQuery($sql);
        $pdone['data']['session'] = Cache::getEpoch();
        foreach ([1, 2] as $i) $db->getSqlQuery('INSERT INTO probe VALUES (:id)', ['id' => $i]);
        $pdone['data']['early'] = Cache::getEpoch();
        register_shutdown_function(static function (): void {
            $GLOBALS['pdone']['data']['final'] = Cache::getEpoch();
            echo json_encode($GLOBALS['pdone']);
        });
        exit;
    } else {
        foreach (['admin', 'index'] as $file) file_put_contents(BASE_DIR.'/'.$file.'.php', $file);
        setProbeLift($psite.'/admin/modules/security.php', 'configsave', $pwork.'/lift_afile.php');
        chdir(BASE_DIR);
        $conf = ['security' => ['afile' => 'admin', 'blocker_ip' => '', 'blocker_user' => '', 'admin_ip' => '', 'login' => '', 'password' => '', 'secret' => 'x']];
        $afile = 'admin';
        $ppost = ['afile' => $pname];
        try {
            configsave();
        } catch (LogicException) {
        }
        $pdone['data']['files'] = array_values(array_diff(scandir(BASE_DIR) ?: [], ['.', '..']));
        $pdone['data']['outside'] = array_values(array_diff(scandir($pwork) ?: [], ['.', '..', 'root', 'lift_afile.php']));
    }
} catch (Throwable $err) {
    $pdone['error'] = $err->getMessage();
}
echo json_encode($pdone);
