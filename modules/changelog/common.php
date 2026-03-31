<?php
# Shared Changelog data engine for public and admin modules.

const CHLOG_GH_API_TIMEOUT = 10;
const CHLOG_GH_API_CONNECT_TIMEOUT = 5;
const CHLOG_GIT_LOG_DELIM = '||';
const CHLOG_COMMIT_START = 'COMMIT_START';
const CHLOG_COMMIT_END = 'COMMIT_END';
const CHLOG_DEFAULT_CACHE_TTL = 900;

function chlogEsc(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function chlogClamp(int $value, int $min, int $max): int {
    return max($min, min($value, $max));
}

function chlogValdate(string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date;
}

function chlogSource(string $source): string {
    return $source === 'github' ? 'github' : 'local';
}

function chlogNormalizeFilters(array $filters): array {
    $author = trim((string)($filters['author'] ?? ''));
    $file = trim((string)($filters['file'] ?? ''));
    $search = trim((string)($filters['search'] ?? ''));
    $since = trim((string)($filters['since'] ?? ''));
    $until = trim((string)($filters['until'] ?? ''));

    if ($since !== '' && !chlogValdate($since)) $since = '';
    if ($until !== '' && !chlogValdate($until)) $until = '';

    return [
        'author' => $author,
        'file' => $file,
        'search' => $search,
        'since' => $since,
        'until' => $until
    ];
}

function chlogReadFilters(string $scope = 'get'): array {
    return chlogNormalizeFilters([
        'author' => strip_tags(getVar($scope, 'author', 'var', '')),
        'file' => strip_tags(getVar($scope, 'file', 'var', '')),
        'search' => strip_tags(getVar($scope, 'search', 'var', '')),
        'since' => getVar($scope, 'datefrom', 'var', ''),
        'until' => getVar($scope, 'dateto', 'var', '')
    ]);
}

function chlogGetConfig(array $conf): array {
    $cfg = $conf['changelog'] ?? [];
    return [
        'source' => chlogSource((string)($cfg['source'] ?? 'local')),
        'owner' => trim((string)($cfg['ghowner'] ?? '')),
        'repo' => trim((string)($cfg['ghrepo'] ?? '')),
        'token' => trim((string)($cfg['ghtoken'] ?? '')),
        'limit' => chlogClamp((int)($cfg['limit'] ?? 50), 10, 500),
        'perpage' => chlogClamp((int)($cfg['perpage'] ?? 10), 1, 50),
        'grpdate' => !empty($cfg['grpdate']),
        'showfile' => !empty($cfg['showfile']),
        'showstat' => !empty($cfg['showstat']),
        'exporten' => !empty($cfg['exporten'])
    ];
}

function chlogLoadCommits(array $conf, array $filters, string $gitdir = ''): array {
    $config = chlogGetConfig($conf);
    $filters = chlogNormalizeFilters($filters);
    $error = '';
    $commits = [];

    if ($config['source'] === 'github') {
        if ($config['owner'] === '' || $config['repo'] === '') {
            return [
                'source' => $config['source'],
                'commits' => [],
                'total' => 0,
                'error' => _CHLOG_ERR_GH_CONF
            ];
        }

        $commits = chlogGhFetch(
            $config['owner'],
            $config['repo'],
            $filters,
            $config['limit'],
            $config['token'],
            $error
        );
    } else {
        $repoRoot = $gitdir !== '' ? $gitdir : ((defined('BASE_DIR') && is_string(BASE_DIR)) ? BASE_DIR : getcwd());
        $repoRoot = realpath($repoRoot) ?: $repoRoot;
        $commits = chlogGitFetch((string)$repoRoot, $filters, $config['limit'], $error);
    }

    return [
        'source' => $config['source'],
        'commits' => $commits,
        'total' => count($commits),
        'error' => $error
    ];
}

function chlogPaginate(array $commits, int $page, int $perpage): array {
    $total = count($commits);
    $pages = max(1, (int)ceil($total / max($perpage, 1)));
    $page = chlogClamp($page, 1, $pages);
    $offset = ($page - 1) * $perpage;

    return [
        'items' => array_slice($commits, $offset, $perpage),
        'total' => $total,
        'pages' => $pages,
        'page' => $page,
        'offset' => $offset
    ];
}

function chlogFormatDate(string $date): string {
    $dt = DateTime::createFromFormat('Y-m-d H:i', $date);
    if ($dt === false) {
        $ts = strtotime($date);
        if ($ts !== false) $dt = (new DateTime())->setTimestamp($ts);
    }
    return $dt ? $dt->format('H:i d.m.Y') : chlogEsc($date);
}

function chlogFormatDay(string $date): string {
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if ($dt === false) {
        $ts = strtotime($date);
        if ($ts !== false) $dt = (new DateTime())->setTimestamp($ts);
    }
    return $dt ? $dt->format('d.m.Y') : chlogEsc($date);
}

function chlogGroupCommitsByDate(array $commits): array {
    $grouped = [];
    $lastdate = '';
    $today = date('Y-m-d');
    $yester = date('Y-m-d', strtotime('-1 day'));

    foreach ($commits as $commit) {
        $comdate = substr((string)($commit['date'] ?? ''), 0, 10);
        if ($comdate !== $lastdate) {
            $lastdate = $comdate;
            $labelDate = chlogFormatDay($comdate);
            $label = $labelDate;
            if ($comdate === $today) {
                $label = _CHLOG_TODAY.' ('.$labelDate.')';
            } elseif ($comdate === $yester) {
                $label = _CHLOG_YESTERDAY.' ('.$labelDate.')';
            } elseif (strtotime($comdate) > strtotime('-7 days')) {
                $label = _CHLOG_THIS_WEEK.' ('.$labelDate.')';
            }
            $grouped[] = ['datehdr' => $label];
        }
        $grouped[] = $commit;
    }

    return $grouped;
}

function chlogRenderCommitStats(array $commit, bool $showStats, bool $showFiles): string {
    global $tpl;
    if (!$showStats || empty($commit['files']) || !is_array($commit['files'])) {
        return '';
    }

    $totadd = 0;
    $totdel = 0;
    foreach ($commit['files'] as $file) {
        $totadd += (int)($file['added'] ?? 0);
        $totdel += (int)($file['deleted'] ?? 0);
    }

    $filesHtml = '';
    if ($showFiles) {
        $rows = '';
        foreach ($commit['files'] as $file) {
            $rows .= $tpl->getHtmlFrag('changelog-file-row', [
                'added'   => str_pad((string)((int)($file['added'] ?? 0)), 3, ' ', STR_PAD_LEFT),
                'deleted' => str_pad((string)((int)($file['deleted'] ?? 0)), 3, ' ', STR_PAD_LEFT),
                'file'    => chlogEsc((string)($file['file'] ?? '')),
            ]);
        }
        $filesHtml = $tpl->getHtmlFrag('changelog-file-list', ['rows_html' => $rows]);
    }

    $statsHtml = $tpl->getHtmlFrag('changelog-stats', [
        'changes_label' => _CHLOG_CHANGES,
        'added'         => $totadd,
        'deleted'       => $totdel,
        'count'         => count($commit['files']),
        'files_label'   => _CHLOG_FILES,
    ]);

    return $statsHtml.$filesHtml;
}

function chlogRenderCommits(array $commits, array $options = []): string {
    global $tpl;
    $showStats = !empty($options['showstat']);
    $showFiles = !empty($options['showfile']);
    $html = '';
    $i = 0;

    foreach ($commits as $commit) {
        if (isset($commit['datehdr'])) {
            $html .= $tpl->getHtmlFrag('changelog-date-header', ['label' => chlogEsc((string)$commit['datehdr'])]);
            continue;
        }

        $bodyHtml = '';
        if (!empty($commit['body']) && $commit['body'] !== CHLOG_COMMIT_END) {
            $bodyHtml = $tpl->getHtmlFrag('changelog-commit-body', ['content' => filterMarkdown((string)$commit['body'], 'changelog', true)]);
        }

        $html .= $tpl->getHtmlFrag('basic-changelog-commit', [
            'background' => $i % 2 ? '#f9f9f9' : '#fff',
            'subject' => chlogEsc((string)($commit['subject'] ?? '')),
            'hash' => chlogEsc((string)($commit['hash'] ?? '')),
            'author' => chlogEsc((string)($commit['author'] ?? '')),
            'email' => chlogEsc((string)($commit['email'] ?? '')),
            'date' => chlogFormatDate((string)($commit['date'] ?? '')),
            'label_author' => _CHLOG_AUTHOR,
            'label_date' => _CHLOG_DATE,
            'body' => $bodyHtml,
            'stats' => chlogRenderCommitStats($commit, $showStats, $showFiles)
        ]);
        $i++;
    }

    return $html;
}

function chlogBuildExport(array $commits, string $format): string {
    $format = $format === 'md' ? 'md' : 'txt';

    if ($format === 'md') {
        $out = "# SLAED CMS Changelog\n\n";
        $out .= _CHLOG_GENERATED.': '.date('Y-m-d H:i:s')."\n\n---\n\n";
        foreach ($commits as $commit) {
            $out .= '## '.((string)($commit['subject'] ?? ''))."\n\n";
            $out .= '**'._CHLOG_COMMIT.':** `'.((string)($commit['hash'] ?? ''))."`  \n";
            $out .= '**'._CHLOG_AUTHOR.':** '.((string)($commit['author'] ?? ''))."  \n";
            $out .= '**'._CHLOG_DATE.':** '.((string)($commit['date'] ?? ''))."  \n\n";
            if (!empty($commit['body']) && $commit['body'] !== CHLOG_COMMIT_END) {
                $out .= ((string)$commit['body'])."\n\n";
            }
            $out .= "---\n\n";
        }
        return $out;
    }

    $out = "SLAED CMS Changelog\n===================\n\n";
    $out .= _CHLOG_GENERATED.': '.date('Y-m-d H:i:s')."\n\n";
    foreach ($commits as $commit) {
        $out .= ((string)($commit['subject'] ?? ''))."\n";
        $out .= _CHLOG_COMMIT.': '.((string)($commit['hash'] ?? '')).' | '
            ._CHLOG_AUTHOR.': '.((string)($commit['author'] ?? '')).' | '
            ._CHLOG_DATE.': '.((string)($commit['date'] ?? ''))."\n";
        if (!empty($commit['body']) && $commit['body'] !== CHLOG_COMMIT_END) {
            $out .= ((string)$commit['body'])."\n";
        }
        $out .= "\n".str_repeat('-', 80)."\n\n";
    }
    return $out;
}

function chlogGetCache(string $key): ?array {
    $file = CACHE_DIR.'/'.sha1($key).'.json';
    if (!is_file($file)) return null;

    $json = file_get_contents($file);
    if ($json === false) return null;

    $cache = json_decode($json, true);
    if (!$cache || !isset($cache['meta'], $cache['data'])) return null;
    if (time() > ($cache['meta']['expires_at'] ?? 0)) return null;

    return [
        'data' => $cache['data'],
        'etag' => $cache['meta']['etag'] ?? '',
        'lastmod' => $cache['meta']['last_modified'] ?? ''
    ];
}

function chlogSetCache(string $key, mixed $data, string $url = '', string $etag = '', string $lastmod = ''): void {
    if (!is_dir(CACHE_DIR) && !mkdir(CACHE_DIR, 0755, true) && !is_dir(CACHE_DIR)) return;

    $file = CACHE_DIR.'/'.sha1($key).'.json';
    $tmpfile = $file.'.tmp';
    $ttl = CHLOG_DEFAULT_CACHE_TTL;

    global $conf;
    if (isset($conf['changelog']['cachettl'])) {
        $ttl = (int)$conf['changelog']['cachettl'];
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

    $json = json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return;

    if (file_put_contents($tmpfile, $json, LOCK_EX) !== false && is_file($tmpfile)) {
        if (!@rename($tmpfile, $file)) {
            if (@copy($tmpfile, $file)) {
                @unlink($tmpfile);
            } elseif (file_exists($tmpfile)) {
                @unlink($tmpfile);
            }
        }
    }
}

function chlogGhFetch(string $owner, string $repo, array $filters, int $limit, string $token, string &$error): array {
    $cachekey = "ghfetch_$owner/$repo/$limit/".md5(json_encode($filters));
    $cached = chlogGetCache($cachekey);
    if ($cached !== null) return $cached['data'];

    $allcom = [];
    $wanted = chlogClamp($limit, 1, 500);
    $page = 1;

    while (count($allcom) < $wanted && $page <= 10) {
        $perpage = min(100, $wanted - count($allcom));
        $commits = chlogGhPage($owner, $repo, $filters, $perpage, $page, $token, $error);
        if (empty($commits)) break;
        $allcom = array_merge($allcom, $commits);
        $page++;
    }

    chlogSetCache($cachekey, $allcom, "https://api.github.com/repos/$owner/$repo/commits");
    return $allcom;
}

function chlogGhPage(string $owner, string $repo, array $filters, int $perpage, int $page, string $token, string &$error): array {
    $url = "https://api.github.com/repos/$owner/$repo/commits?per_page=$perpage&page=$page";
    if (!empty($filters['author'])) $url .= '&author='.urlencode($filters['author']);
    if (!empty($filters['since']) && chlogValdate($filters['since'])) $url .= '&since='.urlencode($filters['since'].'T00:00:00Z');
    if (!empty($filters['until']) && chlogValdate($filters['until'])) $url .= '&until='.urlencode($filters['until'].'T23:59:59Z');

    $headers = [
        'User-Agent: SLAED-CMS-Changelog',
        'Accept: application/vnd.github.v3+json'
    ];
    if ($token !== '') $headers[] = 'Authorization: token '.$token;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, CHLOG_GH_API_TIMEOUT);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, CHLOG_GH_API_CONNECT_TIMEOUT);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerz = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($response === false) {
        $error = defined('_CHLOG_ERR_GH_CONNECT') ? _CHLOG_ERR_GH_CONNECT : 'CURL Fehler: Verbindung fehlgeschlagen';
        return [];
    }

    $header = substr($response, 0, $headerz);
    $body = substr($response, $headerz);

    if ($httpcode !== 200) {
        $errdata = json_decode($body, true);
        if (defined('_CHLOG_ERR_GH_API')) {
            $msg = $errdata['message'] ?? '';
            $error = trim(sprintf(_CHLOG_ERR_GH_API, $httpcode).' '.chlogEsc((string)$msg));
        } else {
            $error = 'GitHub API Fehler: HTTP Status '.$httpcode.'.';
            if ($httpcode === 403) {
                if (preg_match('/X-RateLimit-Remaining: (\d+)/i', $header, $m)) {
                    $error .= ' Rate Limit verbleibend: '.$m[1].'.';
                }
                if (preg_match('/X-RateLimit-Reset: (\d+)/i', $header, $m)) {
                    $error .= ' Reset um: '.date('H:i:s', intval($m[1])).'.';
                }
            }
            if (isset($errdata['message'])) {
                $error .= ' Nachricht: '.chlogEsc((string)$errdata['message']);
            }
        }
        return [];
    }

    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        $error = defined('_CHLOG_ERR_GH_API_JSON')
            ? _CHLOG_ERR_GH_API_JSON
            : 'JSON Decode Fehler: '.json_last_error_msg();
        return [];
    }

    return chlogGhParse($data, $filters);
}

function chlogGhParse(array $data, array $filters): array {
    $commits = [];
    $search = $filters['search'] ?? '';

    foreach ($data as $c) {
        if (!isset($c['commit']['message'])) continue;

        $msg = $c['commit']['message'];
        if ($search && stripos($msg, $search) === false) continue;

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

function chlogGitFetch(string $gitdir, array $filters, int $limit, string &$error): array {
    $cachekey = "gitfetch_$gitdir/$limit/".md5(json_encode($filters));
    $cached = chlogGetCache($cachekey);
    if ($cached !== null) return $cached['data'];

    $gitexe = 'git';
    if (is_file('C:\\Program Files\\Git\\cmd\\git.exe')) $gitexe = 'C:\\Program Files\\Git\\cmd\\git.exe';

    if (!is_dir($gitdir.'/.git')) {
        $error = defined('_CHLOG_ERR_GIT_MISSING')
            ? sprintf(_CHLOG_ERR_GIT_MISSING, $gitdir)
            : 'Git-Repository nicht gefunden: '.$gitdir;
        return [];
    }

    $olddir = getcwd();
    if (!chdir($gitdir)) {
        $error = defined('_CHLOG_ERR_GIT_CHDIR')
            ? sprintf(_CHLOG_ERR_GIT_CHDIR, $gitdir)
            : 'Konnte nicht in Verzeichnis wechseln: '.$gitdir;
        return [];
    }

    $gitfilt = '';
    if (!empty($filters['author'])) $gitfilt .= ' --author='.escapeshellarg($filters['author']);
    if (!empty($filters['search'])) $gitfilt .= ' --grep='.escapeshellarg($filters['search']);
    if (!empty($filters['since']) && chlogValdate($filters['since'])) $gitfilt .= ' --since='.escapeshellarg($filters['since']);
    if (!empty($filters['until']) && chlogValdate($filters['until'])) $gitfilt .= ' --until='.escapeshellarg($filters['until']);
    if (!empty($filters['file'])) $gitfilt .= ' -- '.escapeshellarg($filters['file']);

    $limit = chlogClamp($limit, 1, 500);
    $format = CHLOG_COMMIT_START.CHLOG_GIT_LOG_DELIM.'%H'.CHLOG_GIT_LOG_DELIM.'%h'.CHLOG_GIT_LOG_DELIM;
    $format .= '%ad'.CHLOG_GIT_LOG_DELIM.'%an'.CHLOG_GIT_LOG_DELIM.'%ae'.CHLOG_GIT_LOG_DELIM;
    $format .= '%s'.CHLOG_GIT_LOG_DELIM.'%b'.CHLOG_GIT_LOG_DELIM.CHLOG_COMMIT_END;
    $dateformat = '%Y-%m-%d %H:%M';
    $cmd = escapeshellarg($gitexe).' log --pretty="format:'.$format.'" --date="format:'.$dateformat.'" --numstat'.$gitfilt.' -'.$limit.' 2>&1';

    $gitlog = [];
    $retcode = 1;
    chlogExecGit($cmd, $gitlog, $retcode);
    chdir($olddir);

    if ($retcode !== 0) {
        $error = defined('_CHLOG_ERR_GIT_CMD')
            ? sprintf(_CHLOG_ERR_GIT_CMD, $retcode)
            : 'Git-Befehl fehlgeschlagen (Code: '.$retcode.')';
        return [];
    }

    $commits = chlogGitParse($gitlog);
    chlogSetCache($cachekey, $commits, 'git:'.$gitdir);

    return $commits;
}

function chlogExecGit(string $cmd, array &$out, int &$code): void {
    $out = [];
    $code = 1;
    if (!function_exists('exec')) return;
    if (preg_match('/[\x00\r\n]/', $cmd)) return;
    if (!preg_match('#(?:^git|[\'"][^\'"]*git(?:\.exe)?[\'"])\s+log\s+#i', $cmd)) return;
    exec($cmd, $out, $code);
}

function chlogGitParse(array $lines): array {
    $commits = [];
    $curcom = null;
    $files = [];
    $delim = CHLOG_GIT_LOG_DELIM;

    foreach ($lines as $line) {
        if (strpos($line, CHLOG_COMMIT_START.$delim) === 0) {
            if ($curcom) {
                $curcom['files'] = $files;
                $commits[] = $curcom;
            }

            $parts = explode($delim, $line, 9);
            if (count($parts) >= 8) {
                $body = trim($parts[7] ?? '');
                $body = str_replace(CHLOG_COMMIT_END, '', $body);
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
        } elseif (strpos($line, CHLOG_COMMIT_END) === 0) {
            continue;
        } elseif ($curcom && preg_match('/^(\d+|-)\s+(\d+|-)\s+(.+)$/', $line, $m)) {
            $files[] = [
                'added' => $m[1] === '-' ? 0 : (int)$m[1],
                'deleted' => $m[2] === '-' ? 0 : (int)$m[2],
                'file' => $m[3]
            ];
        }
    }

    if ($curcom) {
        $curcom['files'] = $files;
        $commits[] = $curcom;
    }

    return $commits;
}
