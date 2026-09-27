<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the cart, the checkout, the admin lists and the point compensations of the shop, order and forum handlers
# It lifts the shipped functions out of their sources and runs them over stubs of the database, the points journal and the request, so no scenario boots the core
# Modes cart, pager and import: the cart cookie in several shapes, a pager counted by the caller or by the table, a product CSV read into an update and two inserts
# Mode import escape posts the same CSV as a path out of shop/temp, which the import must not read
# Mode ops <typ> runs the mass action of the product list; mode points <owner>|<case> runs one owner of a compensation with the journal lost, refused or kept
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
if ($pwork === '' || !in_array($pmode, ['cart', 'pager', 'ops', 'import', 'points'], true)) {
    echo json_encode(['error' => 'usage']);
    exit;
}
if (!is_dir($pwork.'/shop/temp')) mkdir($pwork.'/shop/temp', 0777, true);
define('PREFIX_DB', 'sl');
define('UPLOADS_DIR', $pwork);
define('ADMIN_FILE', true);
define('_TOKENMISS', 'tokenmiss');
define('_SUCCSAVE', 'saved');
define('_SUCCDELETE', 'deleted');
define('_SUCCSTATUS', 'status');
define('_OR_8', 'sent');
define('_ERROR', 'error');
$pdone = ['error' => '', 'data' => []];
$conf = ['user_c' => 'sl', 'name' => 'forum', 'forum' => ['add' => 1, 'recycle' => 0]];
$afile = 'admin';
$admin = ['1'];
$user = [5];
$ppost = [];

# Moderation mode of a comment target, as core/classes/comment.php declares it
enum CommentMode: int {
    case Disabled = 0;
    case Moderated = 1;
    case Open = 2;
}

# Database that answers rows by a fragment of their statement and its parameters, and records every statement and transaction step
final class ProbeDb {
    public array $log = [];
    public array $rows = [];

    # Record the statement and hand back its text with the parameters as the result
    public function getSqlQuery(string $sql, array $par = []): string {
        $this->log[] = trim(preg_replace('/\s+/', ' ', $sql));
        return $sql.' '.json_encode($par);
    }

    # Answer the first row whose mark the statement carries, or false
    public function getSqlRow(mixed $res): array|false {
        foreach ($this->rows as $mark => $row) {
            if (str_contains((string)$res, $mark)) return $row;
        }
        return false;
    }

    # Count one row when a mark matches the statement
    public function getSqlRowCount(mixed $res): int {
        return $this->getSqlRow($res) ? 1 : 0;
    }

    # Quote a value the way the import builds its literals
    public function getSqlValue(mixed $val): string {
        return "'".addslashes((string)$val)."'";
    }

    # Record the start of a transaction
    public function setSqlBegin(): bool {
        $this->log[] = 'BEGIN';
        return true;
    }

    # Record a commit that succeeds
    public function setSqlCommit(): bool {
        $this->log[] = 'COMMIT';
        return true;
    }

    # Record a rollback that succeeds
    public function setSqlRollback(): bool {
        $this->log[] = 'ROLLBACK';
        return true;
    }

    # No insert of a scenario needs its id
    public function getSqlLastId(): int {
        return 0;
    }
}

# Points journal whose origin lookup and compensation answer what the scenario asks for
final class ProbePnt {
    public array $log = [];

    # Take the origin the lookup answers and whether a compensation is accepted
    public function __construct(private int|false $rid, private bool $back) {}

    # Record the lookup and answer the origin of the scenario
    public function getEventId(string $act, string $scope, string $src, int $uid): int|false {
        $this->log[] = 'get '.$src;
        return $this->rid;
    }

    # Record the event; an award always passes, a compensation answers what the scenario asks for
    public function addEvent(string $act, string $scope, string $src, int $uid, array $data = []): bool {
        $this->log[] = 'add '.$src;
        return str_starts_with($src, 'reverse:') ? $this->back : true;
    }

    # The account locks always pass
    public function setUserLocks(array $ids): bool {
        return true;
    }
}

# Cut one top-level function out of a shipped source and load it into this process
function setProbeLift(string $file, string $func, string $into): void {
    $code = (string)file_get_contents($file);
    $from = strpos($code, "\nfunction ".$func.'(');
    $end = ($from === false) ? false : strpos($code, "\n}\n", $from);
    if ($end === false) throw new RuntimeException('Function '.$func.' was not found');
    file_put_contents($into, "<?php\n".substr($code, $from, $end - $from + 3));
    require $into;
}

# Every token check passed: only the handler under it is tested
function checkAdminPost(string $scope): bool {
    return true;
}

# Every token check passed: only the handler under it is tested
function checkSiteToken(): bool {
    return true;
}

# Answer the posted field of the scenario, or the default of the call
function getVar(string $typ, string $name, string $filt = '', mixed $def = null): mixed {
    return $GLOBALS['ppost'][$name] ?? $def;
}

# The shipped filter keeps letters and digits, which is all a mass action carries
function filterVar(string $val): string {
    return preg_replace('/[^a-zA-Z0-9_-]/', '', $val);
}

# Record the answer of the handler and leave it the way a redirect does
function setRedirect(string $url, bool $refer = false, int $code = 302, string $text = '', bool $warn = false): never {
    $GLOBALS['pdone']['data'] += ['url' => $url, 'text' => $text, 'warn' => $warn];
    throw new LogicException('redirect');
}

# Record the flash a handler leaves without a redirect of its own
function setFlash(string $text, bool $warn = false): void {
    $GLOBALS['pdone']['data']['flash'] = [$text, $warn];
}

# Hand the pager view the numbers it would draw
function getTplPagerView(int $num, int $pages, int $maxpg, callable $target, array $meta = []): string {
    return json_encode(['pages' => $pages, 'count' => $meta['count'] ?? null]);
}

# The forum helpers around the deletion keep their own tests; here they only have to exist
function is_acess(mixed $val): bool {
    return true;
}

# Category list of one branch, a single id here
function catids(string $mod, int|string $id): string {
    return (string)intval($id);
}

# Recount of one topic, not under test
function addForumCount(int|string $id): void {}

# Advertised last topic of a branch, not under test
function setForumLast(int $cid, int $gone = 0): void {}

# Run the scenario of one owner of a compensation
function getProbeOwner(string $name, ProbeDb $db): void {
    global $psite, $pwork, $ppost;
    [$owner, $case] = explode('|', $name) + ['', ''];
    $GLOBALS['pnt'] = new ProbePnt($case === 'lost' ? false : 9, $case === 'kept');
    $map = [
        'shop.clientdel' => ['modules/shop/admin/index.php', 'clientdel', ['id' => 3], ['FROM sl_clients WHERE id' => [7, 1]]],
        'shop.clientset' => ['modules/shop/admin/index.php', 'clientset', ['id' => 3], ['FROM sl_clients WHERE id' => [7, 1]]],
        'order.delete' => ['modules/order/admin/index.php', 'delete', ['id' => 3], ['FROM sl_order WHERE id' => [7, 1]]],
        'order.activate' => ['modules/order/admin/index.php', 'activate', ['id' => 3, 'act' => '0'], ['FROM sl_order WHERE id' => [7, 1]]],
        'forum.delete' => ['modules/forum/index.php', 'delete', [], [
            'pdelete, pmod' => ['x', 'x'], 'SELECT pid, uid FROM sl_forum' => [0, 7], 'COUNT(id)' => [0], '_favorites' => [0],
        ]],
    ];
    if (!isset($map[$owner])) throw new RuntimeException('Unknown owner '.$owner);
    [$file, $func, $post, $rows] = $map[$owner];
    $ppost = $post;
    $db->rows = $rows;
    setProbeLift($psite.'/'.$file, $func, $pwork.'/lift.php');
    try {
        ($owner === 'forum.delete') ? $func(4, 3) : $func();
    } catch (LogicException) {
    }
    $GLOBALS['pdone']['data']['journal'] = $GLOBALS['pnt']->log;
}

try {
    $db = new ProbeDb();
    $GLOBALS['db'] = $db;
    if ($pmode === 'cart') {
        setProbeLift($psite.'/core/system.php', 'getCartCookie', $pwork.'/lift.php');
        $cases = [
            'ids' => ['sl-shop' => base64_encode('1,2,2')],
            'raw' => ['shop' => base64_encode('1,2')],
            'empty' => ['sl-shop' => base64_encode('1,,2')],
            'letter' => ['sl-shop' => base64_encode('1,a')],
            'zero' => ['sl-shop' => base64_encode('0,1')],
            'trail' => ['sl-shop' => base64_encode('1,')],
            'array' => ['sl-shop' => ['x']],
            'broken' => ['sl-shop' => '%%%'],
        ];
        foreach ($cases as $key => $val) {
            $_COOKIE = $val;
            $pdone['data'][$key] = getCartCookie();
        }
    } elseif ($pmode === 'pager') {
        setProbeLift($psite.'/core/helpers.php', 'getTplPager', $pwork.'/lift.php');
        $db->rows = ['COUNT(id)' => [23]];
        $pdone['data']['given'] = json_decode(getTplPager(['count' => '45', 'limit' => 10, 'url' => 'name=shop&op=clients&']), true);
        $pdone['data']['given_sql'] = $db->log;
        $db->log = [];
        $pdone['data']['table'] = json_decode(getTplPager(['limit' => 10, 'table' => '_products', 'where' => 'status=1']), true);
        $pdone['data']['table_sql'] = $db->log;
    } elseif ($pmode === 'ops') {
        setProbeLift($psite.'/modules/shop/admin/index.php', 'productops', $pwork.'/lift.php');
        $ppost = ['id[]' => ['5', '6'], 'typ' => $pname];
        try {
            productops();
        } catch (LogicException) {
        }
        $pdone['data']['sql'] = $db->log;
    } elseif ($pmode === 'import') {
        setProbeLift($psite.'/modules/shop/admin/index.php', 'export', $pwork.'/lift.php');
        $line = static fn(int $id, string $title): array => [$id, 2, '2026-01-01 10:00:00', $title, 'intro', 'body', 10, 0, '', 44, 12, 7, 31, 0, 1];
        $fp = fopen($pwork.($pname === 'escape' ? '' : '/shop/temp').'/t_products.csv', 'wb');
        foreach ([$line(5, 'Kept'), $line(8, 'New'), $line(0, 'Auto')] as $row) fputcsv($fp, $row);
        fclose($fp);
        $db->rows = ['sl_products WHERE id = :id {"id":5}' => [5]];
        $ppost = ['id' => 2, 'bd' => ($pname === 'escape' ? '../../' : '').'t_products.csv'];
        try {
            export();
        } catch (LogicException) {
        }
        $pdone['data']['sql'] = array_values(array_filter($db->log, static fn(string $v): bool => !str_starts_with($v, 'SELECT')));
    } else {
        getProbeOwner($pname, $db);
        $pdone['data']['sql'] = $db->log;
    }
} catch (Throwable $e) {
    $pdone['error'] = get_class($e).': '.$e->getMessage();
}
echo json_encode($pdone);
