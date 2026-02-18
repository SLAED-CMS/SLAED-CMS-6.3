<?php
# Author: Eduard Laas
# Copyright 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

get_lang($conf['name']);
$conflog = $conf['changelog'] ?? [];

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

function chlogSetCache(string $key, $data, string $url = '', string $etag = '', string $lastmod = ''): void {
    if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);

    $file = CACHE_DIR.'/'.sha1($key).'.json';
    $ttl = CHLOG_DEFAULT_CACHE_TTL;
    global $conflog;
    if (isset($conflog['cachettl'])) {
        $ttl = (int) $conflog['cachettl'];
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
    $tmp = $file.'.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) !== false) {
        rename($tmp, $file);
    }
}

function chlogGhTotal(string $owner, string $repo, string $token, string &$error): int {
    $cachekey = "ghtotal_$owner/$repo";
    $cached = chlogGetCache($cachekey);
    if ($cached !== null) return (int) $cached['data'];

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
    if ($payload === false) {
        $error = _CHLOG_ERR_GH_REQ;
        return 0;
    }

    $headers = [
        'User-Agent: SLAED-CMS-Changelog',
        'Content-Type: application/json'
    ];
    if ($token) $headers[] = 'Authorization: Bearer '.$token;

    $ch = curl_init('https://api.github.com/graphql');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, CHLOG_GH_API_TIMEOUT);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, CHLOG_GH_API_CONNECT_TIMEOUT);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpcode !== 200) {
        $error = sprintf(_CHLOG_ERR_GH_API, $httpcode);
        return 0;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error = _CHLOG_ERR_GH_JSON;
        return 0;
    }

    $count = $data['data']['repository']['defaultBranchRef']['target']['history']['totalCount'] ?? 0;
    chlogSetCache($cachekey, $count, 'https://api.github.com/graphql');
    return (int) $count;
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
    if ($token) $headers[] = 'Authorization: token '.$token;

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
        $error = _CHLOG_ERR_GH_CONNECT;
        return [];
    }

    $header = substr($response, 0, $headerz);
    $body = substr($response, $headerz);

    if ($httpcode !== 200) {
        $errdata = json_decode($body, true);
        $msg = $errdata['message'] ?? '';
        $error = trim(sprintf(_CHLOG_ERR_GH_API, $httpcode).' '.chlogEsc($msg));
        return [];
    }

    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        $error = _CHLOG_ERR_GH_API_JSON;
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
        $error = sprintf(_CHLOG_ERR_GIT_MISSING, $gitdir);
        return [];
    }

    $olddir = getcwd();
    if (!chdir($gitdir)) {
        $error = sprintf(_CHLOG_ERR_GIT_CHDIR, $gitdir);
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
    exec($cmd, $gitlog, $retcode);
    chdir($olddir);

    if ($retcode !== 0) {
        $error = sprintf(_CHLOG_ERR_GIT_CMD, $retcode);
        return [];
    }

    $commits = chlogGitParse($gitlog);
    chlogSetCache($cachekey, $commits, 'git:'.$gitdir);
    return $commits;
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
                'added' => $m[1] === '-' ? 0 : (int) $m[1],
                'deleted' => $m[2] === '-' ? 0 : (int) $m[2],
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

function chlogGrpDate(array $commits): array {
    $grouped = [];
    $lastdate = '';
    $today = date('Y-m-d');
    $yester = date('Y-m-d', strtotime('-1 day'));

    foreach ($commits as $commit) {
        $comdate = substr($commit['date'], 0, 10);
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

function chlogRenderCommits(array $commits, array $conf): string {
    $html = '';
    $i = 0;

    foreach ($commits as $commit) {
        if (isset($commit['datehdr'])) {
            $html .= '<div class="date-header">'.chlogEsc($commit['datehdr']).'</div>';
            continue;
        }

        $bodyHtml = '';
        if (!empty($commit['body']) && $commit['body'] !== CHLOG_COMMIT_END) {
            $body = chlogEsc($commit['body']);
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
                        .chlogEsc($f['file']).'</div>';
                }
                $filesHtml = '<div class="commit-files">'.implode('', $rows).'</div>';
            }

            $statsHtml = '<div class="commit-stats">';
            $statsHtml .= '<strong>'._CHLOG_CHANGES.':</strong> ';
            $statsHtml .= '<span class="add">+'.$totadd.'</span> / ';
            $statsHtml .= '<span class="del">-'.$totdel.'</span> | ';
            $statsHtml .= '<strong>'.count($commit['files']).' '._CHLOG_FILES.'</strong>';
            $statsHtml .= '</div>';
            $statsHtml .= $filesHtml;
        }

        $html .= setTemplateBasic('basic-changelog-commit', [
            '{%background%}' => $i % 2 ? '#f9f9f9' : '#fff',
            '{%subject%}' => chlogEsc($commit['subject']),
            '{%author%}' => chlogEsc($commit['author']),
            '{%email%}' => chlogEsc($commit['email']),
            '{%date%}' => chlogFormatDate($commit['date']),
            '{%label_author%}' => _CHLOG_AUTHOR,
            '{%label_date%}' => _CHLOG_DATE,
            '{%body%}' => $bodyHtml,
            '{%stats%}' => $statsHtml
        ]);
        $i++;
    }

    return $html;
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

function chlogRenderPaging(string $modname, int $totcom, int $totpage, int $perpage, int $page, array $filters): string {
    $query = http_build_query(array_filter([
        'author' => $filters['author'] ?? '',
        'file' => $filters['file'] ?? '',
        'search' => $filters['search'] ?? '',
        'datefrom' => $filters['since'] ?? '',
        'dateto' => $filters['until'] ?? ''
    ]));
    $url = $query ? $query.'&' : '';

    $out = setPageNumbers('pagenum', $modname, $totcom, $totpage, $perpage, $url, 10, $page, '', 'page');
    return $out ?? '';
}

function changelog(): void {
    global $conf, $conflog;

    head();

    $page = max(1, getVar('get', 'page', 'num', 1));
    $author = trim(strip_tags(getVar('get', 'author', 'var', '')));
    $file = trim(strip_tags(getVar('get', 'file', 'var', '')));
    $search = trim(strip_tags(getVar('get', 'search', 'var', '')));
    $datefrom = getVar('get', 'datefrom', 'var', '');
    $dateto = getVar('get', 'dateto', 'var', '');

    if ($datefrom && !chlogValdate($datefrom)) $datefrom = '';
    if ($dateto && !chlogValdate($dateto)) $dateto = '';

    $filters = [
        'author' => $author,
        'file' => $file,
        'search' => $search,
        'since' => $datefrom,
        'until' => $dateto
    ];

    $source = $conflog['source'] ?? 'local';
    $limit = chlogClamp((int) ($conflog['limit'] ?? 50), 10, 500);
    $perpage = chlogClamp((int) ($conflog['perpage'] ?? 10), 1, 50);
    $commits = [];
    $totcount = 0;
    $error = '';

    if ($source === 'github') {
        $ghowner = trim($conflog['ghowner'] ?? '');
        $ghrepo = trim($conflog['ghrepo'] ?? '');
        $ghtoken = trim($conflog['ghtoken'] ?? '');

        if ($ghowner === '' || $ghrepo === '') {
            echo setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _CHLOG_ERR_GH_CONF]);
            foot();
            return;
        }

        $totcount = chlogGhTotal($ghowner, $ghrepo, $ghtoken, $error);
        $commits = chlogGhFetch($ghowner, $ghrepo, $filters, $limit, $ghtoken, $error);
        if (empty($commits) && $error !== '') {
            echo setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $error]);
            foot();
            return;
        }
    } else {
        $gitdir = realpath(BASE_DIR) ?: BASE_DIR;
        $commits = chlogGitFetch($gitdir, $filters, $limit, $error);
        $totcount = count($commits);
        if (empty($commits) && $error !== '') {
            echo setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $error]);
            foot();
            return;
        }
    }

    if (empty($commits)) {
        echo setTemplateWarning('info', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _CHLOG_ERR_NO_COMMITS]);
        foot();
        return;
    }

    $totcom = count($commits);
    $totpage = max(1, (int) ceil($totcom / $perpage));
    $page = chlogClamp($page, 1, $totpage);
    $offset = ($page - 1) * $perpage;
    $compg = array_slice($commits, $offset, $perpage);

    if (!empty($conflog['grpdate'])) {
        $compg = chlogGrpDate($compg);
    }

    $cont = setTemplateBasic('basic-changelog', [
        '{%action%}' => 'index',
        '{%name%}' => $conf['name'],
        '{%search%}' => chlogEsc($search),
        '{%author%}' => chlogEsc($author),
        '{%file%}' => chlogEsc($file),
        '{%datefrom%}' => chlogEsc($datefrom),
        '{%dateto%}' => chlogEsc($dateto),
        '{%totcount%}' => $totcount,
        '{%totcom%}' => $totcom,
        '{%page%}' => $page,
        '{%totpage%}' => $totpage,
        '{%commits%}' => chlogRenderCommits($compg, $conflog),
        '{%paging%}' => chlogRenderPaging($conf['name'], $totcom, $totpage, $perpage, $page, $filters),
        '{%txt_filter_heading%}' => _CHLOG_FILTER,
        '{%txt_search_label%}' => _CHLOG_SEARCH,
        '{%txt_search_placeholder%}' => _CHLOG_SEARCH_PH,
        '{%txt_author_label%}' => _CHLOG_AUTHOR,
        '{%txt_author_placeholder%}' => _CHLOG_AUTHOR_PH,
        '{%txt_file_label%}' => _CHLOG_FILE,
        '{%txt_file_placeholder%}' => _CHLOG_FILE_PH,
        '{%txt_datefrom_label%}' => _CHLOG_DATE_FROM,
        '{%txt_dateto_label%}' => _CHLOG_DATE_TO,
        '{%txt_filter_btn%}' => _CHLOG_FILTER_BTN,
        '{%txt_reset_btn%}' => _CHLOG_RESET_BTN,
        '{%txt_total%}' => _CHLOG_TOTAL,
        '{%txt_filtered%}' => _CHLOG_FILTERED,
        '{%txt_page%}' => _CHLOG_PAGE,
        '{%txt_commits%}' => _CHLOG_COMMITS,
        '{%txt_commits_repo%}' => _CHLOG_COMMITS_REPO
    ]);

    echo $cont;
    foot();
}

switch ($op) {
    default: changelog(); break;
}
