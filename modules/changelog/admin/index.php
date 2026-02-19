<?php
# Author: Eduard Laas
# Copyright Â© 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');
$conflog = $conf['changelog'] ?? [];

// ============================================================================
// CONSTANTS
// ============================================================================

const GH_API_TIMEOUT = 10;
const GH_API_CONNECT_TIMEOUT = 5;
const GIT_LOG_DELIM = '||';
const COMMIT_START = 'COMMIT_START';
const COMMIT_END = 'COMMIT_END';
const DEFAULT_CACHE_TTL = 900; // 15 minutes

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function esc(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function clamp(int $value, int $min, int $max): int {
    return max($min, min($value, $max));
}

function valdate(string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date;
}

// ============================================================================
// CACHE FUNCTIONS
// ============================================================================

/**
 * Get cache data with metadata
 * @return array|null ['data' => mixed, 'etag' => string, 'lastmod' => string] or null
 */
function chgetcache(string $key): ?array {
    $file = CACHE_DIR.'/'.sha1($key).'.json';
    if (!file_exists($file)) return null;

    $json = @file_get_contents($file);
    if (!$json) return null;

    $cache = json_decode($json, true);
    if (!$cache || !isset($cache['meta'], $cache['data'])) {
        @unlink($file);
        return null;
    }

    // Check expiration
    $now = time();
    if ($now > $cache['meta']['expires_at']) {
        @unlink($file);
        return null;
    }

    return [
        'data' => $cache['data'],
        'etag' => $cache['meta']['etag'] ?? '',
        'lastmod' => $cache['meta']['last_modified'] ?? ''
    ];
}

/**
 * Set cache with metadata (atomic write)
 * @param string $key Cache key
 * @param mixed $data Data to cache
 * @param string $url Original URL (for debugging)
 * @param string $etag ETag from response
 * @param string $lastmod Last-Modified from response
 */
function chsetcache(string $key, $data, string $url = '', string $etag = '', string $lastmod = ''): void {
    if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);

    $file = CACHE_DIR.'/'.sha1($key).'.json';
    $tmpfile = $file.'.tmp';

    $ttl = DEFAULT_CACHE_TTL;
    global $conflog;
    if (isset($conflog['cachettl'])) {
        $ttl = (int)$conflog['cachettl'];
    }

    $cache = [
        'meta' => [
            'created_at' => time(),
            'expires_at' => time() + $ttl,
            'etag' => $etag,
            'last_modified' => $lastmod,
            'url' => $url
        ],
        'data' => $data
    ];

    // Atomic write
    $json = json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (file_put_contents($tmpfile, $json, LOCK_EX) !== false) {
        @rename($tmpfile, $file);
    }
}

// ============================================================================
// GITHUB API FUNCTIONS
// ============================================================================

function ghtotal(string $owner, string $repo, string $token = ''): int {
    global $gherror;

    $cachekey = "ghtotal_$owner/$repo";
    $cached = chgetcache($cachekey);
    if ($cached !== null) return $cached['data'];

    $query = <<<GQL
query {
  repository(owner: "$owner", name: "$repo") {
    defaultBranchRef {
      target {
        ... on Commit {
          history {
            totalCount
          }
        }
      }
    }
  }
}
GQL;

    $payload = json_encode(['query' => $query]);
    $headers = [
        'User-Agent: SLAED-CMS-Changelog',
        'Content-Type: application/json',
    ];

    if ($token) {
        $headers[] = 'Authorization: Bearer '.$token;
    }

    $ch = curl_init('https://api.github.com/graphql');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, GH_API_TIMEOUT);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, GH_API_CONNECT_TIMEOUT);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpcode !== 200) {
        $gherror = 'GraphQL API Fehler: HTTP '.$httpcode;
        return 0;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $gherror = 'JSON Decode Fehler';
        return 0;
    }

    $count = $data['data']['repository']['defaultBranchRef']['target']['history']['totalCount'] ?? 0;
    chsetcache($cachekey, $count, 'https://api.github.com/graphql');

    return $count;
}

function ghfetch(string $owner, string $repo, array $filters, int $limit, string $token = ''): array {
    global $gherror;

    $cachekey = "ghfetch_$owner/$repo/$limit/".md5(json_encode($filters));

    $cached = chgetcache($cachekey);
    if ($cached !== null) return $cached['data'];

    $allcom = [];
    $wanted = clamp($limit, 1, 500);
    $page = 1;

    while (count($allcom) < $wanted && $page <= 10) {
        $perpage = min(100, $wanted - count($allcom));
        $commits = ghpage($owner, $repo, $filters, $perpage, $page, $token);

        if (empty($commits)) break;

        $allcom = array_merge($allcom, $commits);
        $page++;
    }

    $url = "https://api.github.com/repos/$owner/$repo/commits";
    chsetcache($cachekey, $allcom, $url);
    return $allcom;
}

function ghpage(string $owner, string $repo, array $filters, int $perpage, int $page, string $token): array {
    global $gherror;

    $url = "https://api.github.com/repos/$owner/$repo/commits?per_page=$perpage&page=$page";

    if (!empty($filters['author'])) {
        $url .= '&author='.urlencode($filters['author']);
    }
    if (!empty($filters['since']) && valdate($filters['since'])) {
        $url .= '&since='.urlencode($filters['since'].'T00:00:00Z');
    }
    if (!empty($filters['until']) && valdate($filters['until'])) {
        $url .= '&until='.urlencode($filters['until'].'T23:59:59Z');
    }

    $headers = [
        'User-Agent: SLAED-CMS-Changelog',
        'Accept: application/vnd.github.v3+json'
    ];

    if ($token) {
        $headers[] = 'Authorization: token '.$token;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, GH_API_TIMEOUT);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, GH_API_CONNECT_TIMEOUT);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerz = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($response === false) {
        $gherror = 'CURL Fehler: Verbindung fehlgeschlagen';
        return [];
    }

    $header = substr($response, 0, $headerz);
    $body = substr($response, $headerz);

    if ($httpcode !== 200) {
        $errdata = json_decode($body, true);
        $gherror = '<strong>GitHub API Fehler:</strong><br>';
        $gherror .= 'â€¢ HTTP Status: '.$httpcode.'<br>';

        if ($httpcode === 403) {
            if (preg_match('/X-RateLimit-Remaining: (\d+)/i', $header, $m)) {
                $gherror .= 'â€¢ Rate Limit verbleibend: '.$m[1].'<br>';
            }
            if (preg_match('/X-RateLimit-Reset: (\d+)/i', $header, $m)) {
                $gherror .= 'â€¢ Reset um: '.date('H:i:s', intval($m[1])).'<br>';
            }
        }

        if (isset($errdata['message'])) {
            $gherror .= 'â€¢ Nachricht: '.esc($errdata['message']);
        }

        return [];
    }

    $comdata = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($comdata)) {
        $gherror = 'JSON Decode Fehler: '.json_last_error_msg();
        return [];
    }

    return ghparse($comdata, $filters);
}

function ghparse(array $data, array $filters): array {
    $commits = [];
    $search = $filters['search'] ?? '';

    foreach ($data as $c) {
        if (!isset($c['commit']['message'])) continue;

        $msg = $c['commit']['message'];

        if ($search && stripos($msg, $search) === false) {
            continue;
        }

        $parts = explode("\n", $msg, 2);
        $subject = $parts[0];
        $body = isset($parts[1]) ? trim($parts[1]) : '';

        $commits[] = [
            'fullhash' => $c['sha'] ?? '',
            'hash' => substr($c['sha'] ?? '', 0, 7),
            'date' => date('Y-m-d H:i', strtotime($c['commit']['author']['date'] ?? 'now')),
            'author' => $c['commit']['author']['name'] ?? 'Unknown',
            'email' => $c['commit']['author']['email'] ?? '',
            'subject' => $subject,
            'body' => $body,
            'files' => []
        ];
    }

    return $commits;
}

// ============================================================================
// LOCAL GIT FUNCTIONS
// ============================================================================

function gitfetch(string $gitdir, array $filters, int $limit): array {
    global $giterror;

    $cachekey = "gitfetch_$gitdir/$limit/".md5(json_encode($filters));

    $cached = chgetcache($cachekey);
    if ($cached !== null) return $cached['data'];

    $gitexe = 'git';
    if (file_exists('C:\\Program Files\\Git\\cmd\\git.exe')) {
        $gitexe = 'C:\\Program Files\\Git\\cmd\\git.exe';
    }

    if (!is_dir($gitdir.'/.git')) {
        $giterror = 'Git-Repository nicht gefunden: '.$gitdir;
        return [];
    }

    $olddir = getcwd();
    if (!chdir($gitdir)) {
        $giterror = 'Konnte nicht in Verzeichnis wechseln: '.$gitdir;
        return [];
    }

    $gitfilt = '';
    if (!empty($filters['author'])) {
        $gitfilt .= ' --author='.escapeshellarg($filters['author']);
    }
    if (!empty($filters['search'])) {
        $gitfilt .= ' --grep='.escapeshellarg($filters['search']);
    }
    if (!empty($filters['since']) && valdate($filters['since'])) {
        $gitfilt .= ' --since='.escapeshellarg($filters['since']);
    }
    if (!empty($filters['until']) && valdate($filters['until'])) {
        $gitfilt .= ' --until='.escapeshellarg($filters['until']);
    }
    if (!empty($filters['file'])) {
        $gitfilt .= ' -- '.escapeshellarg($filters['file']);
    }

    $limit = clamp($limit, 1, 500);
    $format = COMMIT_START.GIT_LOG_DELIM.'%H'.GIT_LOG_DELIM.'%h'.GIT_LOG_DELIM;
    $format .= '%ad'.GIT_LOG_DELIM.'%an'.GIT_LOG_DELIM.'%ae'.GIT_LOG_DELIM;
    $format .= '%s'.GIT_LOG_DELIM.'%b'.GIT_LOG_DELIM.COMMIT_END;

    $dateformat = '%Y-%m-%d %H:%M';

    $cmd = escapeshellarg($gitexe).' log --pretty="format:'.$format.'" --date="format:'.$dateformat.'" --numstat'.$gitfilt.' -'.$limit.' 2>&1';

    $gitlog = [];
    exec($cmd, $gitlog, $retcode);
    chdir($olddir);

    if ($retcode !== 0) {
        $giterror = 'Git-Befehl fehlgeschlagen (Code: '.$retcode.')';
        return [];
    }

    $commits = gitparse($gitlog);
    chsetcache($cachekey, $commits, "git:$gitdir");

    return $commits;
}

function gitparse(array $lines): array {
    $commits = [];
    $curcom = null;
    $files = [];
    $delim = GIT_LOG_DELIM;

    foreach ($lines as $line) {
        if (strpos($line, COMMIT_START.$delim) === 0) {
            if ($curcom) {
                $curcom['files'] = $files;
                $commits[] = $curcom;
            }

            $parts = explode($delim, $line, 9);
            if (count($parts) >= 8) {
                $body = trim($parts[7] ?? '');
                $body = str_replace(COMMIT_END, '', $body);
                $curcom = [
                    'fullhash' => $parts[1] ?? '',
                    'hash' => $parts[2] ?? '',
                    'date' => $parts[3] ?? '',
                    'author' => $parts[4] ?? '',
                    'email' => $parts[5] ?? '',
                    'subject' => $parts[6] ?? '',
                    'body' => $body
                ];
                $files = [];
            }
        } elseif (strpos($line, COMMIT_END) === 0) {
            continue;
        } elseif ($curcom && preg_match('/^(\d+|-)\s+(\d+|-)\s+(.+)$/', $line, $m)) {
            if (count($m) >= 4) {
                $files[] = [
                    'added' => $m[1] === '-' ? 0 : intval($m[1]),
                    'deleted' => $m[2] === '-' ? 0 : intval($m[2]),
                    'file' => $m[3]
                ];
            }
        }
    }

    if ($curcom) {
        $curcom['files'] = $files;
        $commits[] = $curcom;
    }

    return $commits;
}

// ============================================================================
// MAIN FUNCTIONS
// ============================================================================

function changelog(): void {
    global $aroute, $conflog, $gherror, $giterror;

    head();
    

    $cont = navi(0, 0);
    $cont .= checkPerms('changelog.php');

    // Get filters
    $page = max(1, getVar('get', 'page', 'num', 1));
    $author = trim(strip_tags(getVar('get', 'author', 'var', '')));
    $file = trim(strip_tags(getVar('get', 'file', 'var', '')));
    $search = trim(strip_tags(getVar('get', 'search', 'var', '')));
    $datefrom = getVar('get', 'datefrom', 'var', '');
    $dateto = getVar('get', 'dateto', 'var', '');

    if ($datefrom && !valdate($datefrom)) $datefrom = '';
    if ($dateto && !valdate($dateto)) $dateto = '';

    $filters = [
        'author' => $author,
        'file' => $file,
        'search' => $search,
        'since' => $datefrom,
        'until' => $dateto
    ];

    // Get commits
    $source = $conflog['source'] ?? 'local';
    $limit = clamp($conflog['limit'] ?? 50, 10, 500);
    $commits = [];
    $totcount = 0;

    if ($source === 'github') {
        $ghowner = trim($conflog['ghowner'] ?? '');
        $ghrepo = trim($conflog['ghrepo'] ?? '');
        $ghtoken = trim($conflog['ghtoken'] ?? '');

        if (empty($ghowner) || empty($ghrepo)) {
            $cont .= setTemplateWarning('warn', [
                'time' => '', 'url' => '', 'id' => 'warn',
                'text' => 'GitHub Owner/Repo nicht konfiguriert.'
            ]);
            echo $cont;
            foot();
            return;
        }

        $totcount = ghtotal($ghowner, $ghrepo, $ghtoken);
        $commits = ghfetch($ghowner, $ghrepo, $filters, $limit, $ghtoken);

        if (empty($commits) && $gherror) {
            $cont .= setTemplateWarning('warn', [
                'time' => '', 'url' => '', 'id' => 'warn',
                'text' => $gherror
            ]);
        }
    } else {
        $gitdir = realpath(__DIR__.'/../../');
        $commits = gitfetch($gitdir, $filters, $limit);
        $totcount = count($commits);

        if (empty($commits) && $giterror) {
            $cont .= setTemplateWarning('warn', [
                'time' => '', 'url' => '', 'id' => 'warn',
                'text' => $giterror
            ]);
        }
    }

    if (empty($commits)) {
        if (!$gherror && !$giterror) {
            $cont .= setTemplateWarning('info', [
                'time' => '', 'url' => '', 'id' => 'info',
                'text' => 'Keine Commits gefunden.'
            ]);
        }
        echo $cont;
        foot();
        return;
    }

    // Pagination
    $perpage = clamp($conflog['perpage'] ?? 10, 1, 50);
    $totcom = count($commits);
    $totpage = max(1, ceil($totcom / $perpage));
    $page = clamp($page, 1, $totpage);
    $offset = ($page - 1) * $perpage;
    $compg = array_slice($commits, $offset, $perpage);

    // Group by date
    if ($conflog['grpdate'] ?? false) {
        $compg = grpdate($compg);
    }

    // Render template
    $cont .= setTemplateBasic('basic-changelog', [
        '{%aroute%}' => $aroute,
        '{%search%}' => esc($search),
        '{%author%}' => esc($author),
        '{%file%}' => esc($file),
        '{%datefrom%}' => esc($datefrom),
        '{%dateto%}' => esc($dateto),
        '{%totcount%}' => $totcount,
        '{%totcom%}' => $totcom,
        '{%page%}' => $page,
        '{%totpage%}' => $totpage,
        '{%commits%}' => rencom($compg, $conflog),
        '{%paging%}' => rendpage($totcom, $totpage, $perpage, $page, $filters)
    ]);

    echo $cont;
    foot();
}

function conf(): void {
    global $aroute, $conflog;
    head();
    $cont = navi(0, 1);
    $cont .= checkPerms('changelog.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.esc($aroute).'.php" method="post">';
    $cont .= '<table class="sl_table_conf">';

    $source = $conflog['source'] ?? 'local';

    $cont .= '<tr><td colspan="2" style="background: #4CAF50; color: white; padding: 8px; font-weight: bold;">Changelog-Quelle</td></tr>';
    $cont .= '<tr><td><strong>Quelle:</strong></td><td>';
    $cont .= '<select name="source" class="sl_conf" onchange="toggleGithubFields(this.value)">';
    $cont .= '<option value="local"'.($source === 'local' ? ' selected' : '').'>Lokales Git</option>';
    $cont .= '<option value="github"'.($source === 'github' ? ' selected' : '').'>GitHub API</option>';
    $cont .= '</select></td></tr>';

    $display = $source === 'github' ? 'table-row-group' : 'none';
    $cont .= '<tbody id="github_fields" style="display: '.$display.'">';
    $cont .= '<tr><td><strong>GitHub Owner:</strong></td><td>';
    $cont .= '<input type="text" name="ghowner" value="'.esc($conflog['ghowner'] ?? '').'" class="sl_conf"></td></tr>';
    $cont .= '<tr><td><strong>GitHub Repository:</strong></td><td>';
    $cont .= '<input type="text" name="ghrepo" value="'.esc($conflog['ghrepo'] ?? '').'" class="sl_conf"></td></tr>';
    $cont .= '<tr><td><strong>GitHub Token:</strong></td><td>';
    $cont .= '<input type="text" name="ghtoken" value="'.esc($conflog['ghtoken'] ?? '').'" class="sl_conf"></td></tr>';
    $cont .= '</tbody>';

    $cont .= '<tr><td colspan="2" style="background: #2196F3; color: white; padding: 8px; font-weight: bold;">Anzeige-Optionen</td></tr>';
    $cont .= '<tr><td><strong>Anzahl Commits:</strong></td><td>';
    $cont .= '<input type="number" name="limit" value="'.($conflog['limit'] ?? 50).'" class="sl_conf" min="10" max="500"></td></tr>';
    $cont .= '<tr><td><strong>Pro Seite:</strong></td><td>';
    $cont .= '<input type="number" name="perpage" value="'.($conflog['perpage'] ?? 10).'" class="sl_conf" min="5" max="50"></td></tr>';
    $cont .= '<tr><td><strong>Cache TTL (Sekunden):</strong></td><td>';
    $cont .= '<input type="number" name="cachettl" value="'.($conflog['cachettl'] ?? 900).'" class="sl_conf" min="0" max="3600"></td></tr>';
    $cont .= '<tr><td><strong>Datum gruppieren:</strong></td><td>';
    $cont .= '<input type="checkbox" name="grpdate" value="1" '.($conflog['grpdate'] ?? false ? 'checked' : '').'></td></tr>';
    $cont .= '<tr><td><strong>Dateien anzeigen:</strong></td><td>';
    $cont .= '<input type="checkbox" name="showfile" value="1" '.($conflog['showfile'] ?? false ? 'checked' : '').'></td></tr>';
    $cont .= '<tr><td><strong>Statistiken:</strong></td><td>';
    $cont .= '<input type="checkbox" name="showstat" value="1" '.($conflog['showstat'] ?? false ? 'checked' : '').'></td></tr>';
    $cont .= '<tr><td><strong>Export:</strong></td><td>';
    $cont .= '<input type="checkbox" name="exporten" value="1" '.($conflog['exporten'] ?? false ? 'checked' : '').'></td></tr>';

    $cont .= '<tr><td colspan="2" class="sl_center">';
    $cont .= '<input type="hidden" name="name" value="changelog">';
    $cont .= '<input type="hidden" name="op" value="save">';
    $cont .= '<input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr>';
    $cont .= '</table>';

    $cont .= '<script>
    function toggleGithubFields(source) {
        document.getElementById("github_fields").style.display = (source === "github") ? "table-row-group" : "none";
    }
    </script>';

    $cont .= '</form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function save(): void {
    global $aroute;

    $confdata = [
        'source' => getVar('post', 'source', 'var', 'local'),
        'ghowner' => getVar('post', 'ghowner', 'text', ''),
        'ghrepo' => getVar('post', 'ghrepo', 'text', ''),
        'ghtoken' => getVar('post', 'ghtoken', 'text', ''),
        'limit' => clamp(getVar('post', 'limit', 'num', 50), 10, 500),
        'perpage' => clamp(getVar('post', 'perpage', 'num', 10), 5, 50),
        'cachettl' => clamp(getVar('post', 'cachettl', 'num', 900), 0, 3600),
        'grpdate' => getVar('post', 'grpdate', 'num', 0),
        'showfile' => getVar('post', 'showfile', 'num', 0),
        'showstat' => getVar('post', 'showstat', 'num', 0),
        'exporten' => getVar('post', 'exporten', 'num', 0)
    ];

    setConfigFile('changelog.php', $confdata);
    header('Location: '.$aroute.'.php?name=changelog&op=conf');
    exit;
}

function export(): void {
    global $conflog;

    $format = getVar('get', 'id', 'var');
    $source = $conflog['source'] ?? 'local';
    $limit = clamp($conflog['limit'] ?? 50, 10, 500);

    if ($source === 'github') {
        $ghowner = trim($conflog['ghowner'] ?? '');
        $ghrepo = trim($conflog['ghrepo'] ?? '');
        $ghtoken = trim($conflog['ghtoken'] ?? '');
        $commits = ghfetch($ghowner, $ghrepo, [], $limit, $ghtoken);
    } else {
        $gitdir = realpath(__DIR__.'/../../');
        $commits = gitfetch($gitdir, [], $limit);
    }

    $filename = 'changelog_'.date('Y-m-d').'.'.($format === 'md' ? 'md' : 'txt');

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    if ($format === 'md') {
        echo "# SLAED CMS Changelog\n\n";
        echo "Generiert am: ".date('Y-m-d H:i:s')."\n\n---\n\n";
        foreach ($commits as $c) {
            echo "## ".$c['subject']."\n\n";
            echo "**Commit:** `".$c['hash']."`  \n";
            echo "**Autor:** ".$c['author']."  \n";
            echo "**Datum:** ".$c['date']."  \n\n";
            if (!empty($c['body']) && $c['body'] !== COMMIT_END) {
                echo $c['body']."\n\n";
            }
            echo "---\n\n";
        }
    } else {
        echo "SLAED CMS Changelog\n===================\n\n";
        echo "Generiert am: ".date('Y-m-d H:i:s')."\n\n";
        foreach ($commits as $c) {
            echo $c['subject']."\n";
            echo "Commit: ".$c['hash']." | Autor: ".$c['author']." | Datum: ".$c['date']."\n";
            if (!empty($c['body']) && $c['body'] !== COMMIT_END) {
                echo $c['body']."\n";
            }
            echo "\n".str_repeat('-', 80)."\n\n";
        }
    }
    exit;
}

function info(): void {
    head();
    echo navi(0, 4, 0, 0, 'changelog').'<div id="repadm_info">'.adm_info(1, 'changelog', 0).'</div>';
    foot();
}

function navi(int $opt, int $tab): string {
    global $conflog;

    $exporten = $conflog['exporten'] ?? true;

    if ($exporten) {
        $ops = [
            'name=changelog',
            'name=changelog&amp;op=conf',
            'name=changelog&amp;op=export&amp;id=txt',
            'name=changelog&amp;op=export&amp;id=md',
            'name=changelog&amp;op=info'
        ];
        $lang = [_HOME, _PREFERENCES, 'Export TXT', 'Export Markdown', _INFO];
    } else {
        $ops = ['name=changelog', 'name=changelog&amp;op=conf', 'name=changelog&amp;op=info'];
        $lang = [_HOME, _PREFERENCES, _INFO];
    }

    return getAdminTabs('Changelog', 'editor.png', '', $ops, $lang, [], [], $tab, 0);
}

// ============================================================================
// RENDER FUNCTIONS
// ============================================================================

function grpdate(array $commits): array {
    $grouped = [];
    $lastdate = '';
    $today = date('Y-m-d');
    $yester = date('Y-m-d', strtotime('-1 day'));

    foreach ($commits as $commit) {
        $comdate = substr($commit['date'], 0, 10);

        if ($comdate !== $lastdate) {
            $lastdate = $comdate;
            $label = $comdate;

            if ($comdate === $today) {
                $label = 'Heute ('.$comdate.')';
            } elseif ($comdate === $yester) {
                $label = 'Gestern ('.$comdate.')';
            } elseif (strtotime($comdate) > strtotime('-7 days')) {
                $label = 'Diese Woche ('.$comdate.')';
            }

            $grouped[] = ['datehdr' => $label];
        }

        $grouped[] = $commit;
    }

    return $grouped;
}

function rencom(array $commits, array $conf): string {
    $html = '';
    $i = 0;

    foreach ($commits as $commit) {
        if (isset($commit['datehdr'])) {
            $html .= '<div class="date-header">'.esc($commit['datehdr']).'</div>';
            continue;
        }

        $bodyHtml = '';
        if (!empty($commit['body']) && $commit['body'] !== COMMIT_END) {
            $body = esc($commit['body']);
            $body = preg_replace('/\*\*([^\*]+)\*\*/', '<strong>$1</strong>', $body);
            $body = preg_replace('/^[\-\*] (.+)$/m', '&bull; $1', $body);
            $body = preg_replace('/`([^`]+)`/', '<code>$1</code>', $body);
            $bodyHtml = '<div class="commit-body">'.nl2br($body).'</div>';
        }

        $statsHtml = '';
        if (!empty($conf['showstat']) && !empty($commit['files'])) {
            $totadd = $totdel = 0;
            foreach ($commit['files'] as $f) {
                $totadd += $f['added'];
                $totdel += $f['deleted'];
            }

            $filesHtml = '';
            if (!empty($conf['showfile'])) {
                $rows = [];
                foreach ($commit['files'] as $f) {
                    $rows[] = '<div><span class="add">+'.str_pad($f['added'], 3, ' ', STR_PAD_LEFT).'</span> '
                        .'<span class="del">-'.str_pad($f['deleted'], 3, ' ', STR_PAD_LEFT).'</span> '
                        .esc($f['file']).'</div>';
                }
                $filesHtml = '<div class="commit-files">'.implode('', $rows).'</div>';
            }

            $statsHtml = '<div class="commit-stats">';
            $statsHtml .= '<strong>Änderungen:</strong> ';
            $statsHtml .= '<span class="add">+'.$totadd.'</span> / ';
            $statsHtml .= '<span class="del">-'.$totdel.'</span> | ';
            $statsHtml .= '<strong>'.count($commit['files']).' Datei(en)</strong>';
            $statsHtml .= '</div>';
            $statsHtml .= $filesHtml;
        }

        $html .= setTemplateBasic('basic-changelog-commit', [
            '{%background%}' => $i % 2 ? '#f9f9f9' : '#fff',
            '{%subject%}' => esc($commit['subject']),
            '{%hash%}' => esc($commit['hash']),
            '{%author%}' => esc($commit['author']),
            '{%email%}' => esc($commit['email']),
            '{%date%}' => esc($commit['date']),
            '{%body%}' => $bodyHtml,
            '{%stats%}' => $statsHtml
        ]);
        $i++;
    }

    return $html;
}function rendpage(int $totcom, int $totpage, int $perpage, int $page, array $filters): string {
    $query = http_build_query(array_filter([
        'name' => 'changelog',
        'author' => $filters['author'],
        'file' => $filters['file'],
        'search' => $filters['search'],
        'datefrom' => $filters['since'],
        'dateto' => $filters['until']
    ]));
    $url = $query ? $query.'&' : 'name=changelog&';

    return setPageNumbers(
        'pagenum', 'changelog', $totcom, $totpage, $perpage,
        $url, 10, $page, '', 'page'
    );
}

// ============================================================================
// ROUTING
// ============================================================================

switch ($op) {
    default: changelog(); break;
    case 'conf': conf(); break;
    case 'save': save(); break;
    case 'export': export(); break;
    case 'info': info(); break;
}


