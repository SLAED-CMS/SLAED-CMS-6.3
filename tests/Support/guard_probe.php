<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for guards of the panel: the escaping of the debug panel, the rename of the admin file, the parent of a category and the repair of the forum last message
# It lifts the shipped function out of its source and runs it over stubs and an in-memory table, so no scenario boots the core or touches a database
# BASE_DIR is the scratch root the caller passed, so every rename the scenarios ask for happens inside it and nothing below the site moves
# Modes: debug prints the panel over a hostile cookie; afile <name> renames; catparent <id:parent>; forumlast repairs a cycle; rows [value]; menu <keys> orders the menu
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
if ($pwork === '' || !in_array($pmode, ['debug', 'afile', 'catparent', 'forumlast', 'rows', 'menu'], true)) {
    echo json_encode(['error' => 'usage']);
    exit;
}
if (!is_dir($pwork.'/root')) mkdir($pwork.'/root', 0777, true);
define('BASE_DIR', $pwork.'/root');
define('PUBLIC_DIR', BASE_DIR.'/public');
if (!is_dir(PUBLIC_DIR)) mkdir(PUBLIC_DIR, 0777, true);
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

# No super-admin unless the scenario lists the sections of one, so the error log of the panel stays out of the output
function isAdmin(bool $sup = false): bool {
    return $GLOBALS['padmin'] ?? false;
}

# Every site module of the scenario is one its administrator may open
function is_admin_modul(string $modul): int {
    return 1;
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

# Answer the forum as the one module with categories, so the save meets a known module
function getCategoryModules(): array {
    return ['forum'];
}

# Answer the locales of the tree as the panel lists them
function getLanguageList(): array {
    return ['en' => 'English', 'ru' => 'Russian'];
}

# Answer the cookie of the scenario, which is what the panel would have stored
function getCookies(string $name): string {
    return $GLOBALS['pcook'][$name] ?? '';
}

# Record the cookie the option would store
function setCookies(string $name, int $time, string|array $value): void {
    $GLOBALS['pdone']['data']['set'] = [$name, $value];
}

# Record the cookie the option would drop
function setCookiesDelete(string $name): void {
    $GLOBALS['pdone']['data']['drop'] = $name;
}

# Record the verdict the option writes into the journal
function setAdminLog(array $data): void {
    $GLOBALS['pdone']['data']['log'] = $data['result'] ?? '';
}

# Record every module whose stored category map a write drops, instead of touching the data cache
function deleteCategoryMap(string $mod): void {
    $GLOBALS['pdone']['data']['reset'][] = $mod;
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

    # Open the transaction of a save, which the in-memory table does not need
    public function setSqlBegin(): bool {
        return true;
    }

    # Close the transaction of a save
    public function setSqlCommit(): bool {
        return true;
    }

    # Undo the transaction of a refused save, which recorded nothing it would have to take back
    public function setSqlRollback(): bool {
        return true;
    }
}

# The registry of addresses of a scenario: every name a save keeps a category on is recorded instead of written
final class Route {
    # Take the table of the scenario
    public function __construct(object $db) {
    }

    # Record the kind, the id, the prefix and the name of the path a save keeps an entity on, and answer the path
    public function setRouteName(string $kind, int $oid, string $prefix, string $name, string $title): string {
        $GLOBALS['pdone']['data']['route'][] = [$kind, $oid, $prefix, $name, $title];
        return $prefix.'/'.$title;
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
        setProbeLift($psite.'/core/security.php', 'filterCutText', $pwork.'/lift_cut.php');
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
    } elseif ($pmode === 'rows') {
        define('ADMIN_FILE', true);
        define('LIST_MAX', 1000);
        setProbeLift($psite.'/core/helpers.php', 'getListLimit', $pwork.'/lift_limit.php');
        $pcall = 0;
        $pcount = static function() use (&$pcall): int {
            $pcall++;
            return 640;
        };
        foreach (['', '10', '25', '250', '5', '1000', '4', '1001', '07', '-5', '1e3', 'abc', 'all'] as $one) {
            $pcook = ['rows' => $one];
            $pdone['data']['limit'][$one] = [getListLimit(25, 300), getListLimit(25, 1000), getListLimit(25, 1001), getListLimit(0, 300)];
        }
        foreach (['', '50', 'all'] as $one) {
            $pcook = ['rows' => $one];
            $pdone['data']['lazy'][$one] = [getListLimit(25, $pcount), $pcall];
        }
        if ($pname !== '') {
            setProbeLift($psite.'/admin/index.php', 'setAdminOption', $pwork.'/lift_option.php');
            $conf = ['admin_c' => 'probe', 'user_c' => 'probe', 'modules' => []];
            $ppost = ['key' => 'rows', 'value' => $pname];
            register_shutdown_function(static function(): void {
                echo json_encode($GLOBALS['pdone']);
            });
            setAdminOption();
        }
    } elseif ($pmode === 'menu') {
        define('HOME_PATH', '/');
        setProbeLift($psite.'/core/system.php', 'getAdminSectionList', $pwork.'/lift_menu.php');
        setProbeLift($psite.'/admin/index.php', 'setAdminOption', $pwork.'/lift_option.php');
        foreach (['account', 'forum'] as $one) {
            mkdir(BASE_DIR.'/modules/'.$one.'/admin', 0777, true);
            file_put_contents(BASE_DIR.'/modules/'.$one.'/admin/index.php', $one);
        }
        $padmin = true;
        $afile = 'admin';
        $conf = ['admin_c' => 'probe', 'user_c' => 'probe', 'modules' => [
            'account' => ['type' => '1'], 'admins' => ['type' => '0'], 'blocks' => ['type' => '0'], 'config' => ['type' => '0'], 'forum' => ['type' => '1'],
        ]];
        $pcook = ['menu' => $pname];
        $pdone['data']['list'] = array_keys(getAdminSectionList());
        $ppost = ['key' => 'menu', 'value' => $pname];
        register_shutdown_function(static function(): void {
            echo json_encode($GLOBALS['pdone']);
        });
        setAdminOption();
    } else {
        foreach (['admin', 'index'] as $file) file_put_contents(PUBLIC_DIR.'/'.$file.'.php', $file);
        setProbeLift($psite.'/admin/modules/security.php', 'configsave', $pwork.'/lift_afile.php');
        chdir(BASE_DIR);
        $conf = ['security' => ['afile' => 'admin', 'blocker_ip' => '', 'blocker_user' => '', 'admin_ip' => '', 'login' => '', 'password' => '', 'secret' => 'x']];
        $afile = 'admin';
        $ppost = ['afile' => $pname];
        try {
            configsave();
        } catch (LogicException) {
        }
        $pdone['data']['files'] = array_values(array_diff(scandir(PUBLIC_DIR) ?: [], ['.', '..']));
        $pdone['data']['outside'] = array_values(array_diff(scandir($pwork) ?: [], ['.', '..', 'root', 'lift_afile.php']));
    }
} catch (Throwable $err) {
    $pdone['error'] = $err->getMessage();
}
echo json_encode($pdone);
