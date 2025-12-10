<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');
require_once CONFIG_DIR.'/changelog.php';

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    global $conflog;
    if ($conflog['export_enabled'] ?? true) {
        $ops = ['name=changelog', 'name=changelog&amp;op=conf', 'name=changelog&amp;op=export&amp;id=txt', 'name=changelog&amp;op=export&amp;id=md', 'name=changelog&amp;op=info'];
        $lang = [_HOME, _PREFERENCES, 'Export TXT', 'Export Markdown', _INFO];
    } else {
        $ops = ['name=changelog', 'name=changelog&amp;op=conf', 'name=changelog&amp;op=info'];
        $lang = [_HOME, _PREFERENCES, _INFO];
    }
    return getAdminTabs('Changelog', 'editor.png', '', $ops, $lang, [], [], $tab, $subtab);
}

function commits(string $owner, string $repo, int $limit = 50, string $token = '', array $filters = []): array {
    global $github_error;
    $url = "https://api.github.com/repos/$owner/$repo/commits?per_page=$limit";

    // Add filter
    if (!empty($filters['author'])) $url .= '&author='.urlencode($filters['author']);
    if (!empty($filters['since'])) $url .= '&since='.urlencode($filters['since'].'T00:00:00Z');
    if (!empty($filters['until'])) $url .= '&until='.urlencode($filters['until'].'T23:59:59Z');

    $headers = [
        'User-Agent: SLAED-CMS-Changelog',
        'Accept: application/vnd.github.v3+json'
    ];

    if ($token) $headers[] = 'Authorization: token '.$token;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        $error_data = json_decode($response, true);
        $github_error = '<strong>GitHub API Fehler:</strong><br>';
        $github_error .= '• <strong>HTTP Status:</strong> '.$http_code.'<br>';
        $github_error .= '• <strong>URL:</strong> '.$url.'<br>';
        if (isset($error_data['message'])) {
            $github_error .= '• <strong>Nachricht:</strong> '.htmlspecialchars($error_data['message']).'<br>';
        }
        if (isset($error_data['documentation_url'])) {
            $github_error .= '• <strong>Doku:</strong> <a href="'.$error_data['documentation_url'].'" target="_blank">'.$error_data['documentation_url'].'</a><br>';
        }
        $github_error .= '• <strong>Token verwendet:</strong> '.($token ? 'Ja' : 'Nein');
        return [];
    }

    $commits_data = json_decode($response, true);
    if (!is_array($commits_data)) return [];

    $commits = [];
    foreach ($commits_data as $c) {
        // Filter nach Suchbegriff (in Commit-Message)
        if (!empty($filters['search']) && stripos($c['commit']['message'], $filters['search']) === false) {
            continue;
        }

        $commits[] = [
            'full_hash' => $c['sha'],
            'hash' => substr($c['sha'], 0, 7),
            'date' => date('Y-m-d H:i', strtotime($c['commit']['author']['date'])),
            'author' => $c['commit']['author']['name'],
            'email' => $c['commit']['author']['email'],
            'subject' => explode("\n", $c['commit']['message'])[0],
            'body' => implode("\n", array_slice(explode("\n", $c['commit']['message']), 1)),
            'files' => []
        ];
    }

    return $commits;
}

function changelog(): void {
    global $aroute, $conflog;
    head();
    checkConfigFile('changelog.php');
    $cont = navi(0, 0, 0, 0);

    //  Filter-Parameter
    $page = getVar('get', 'page', 'num', 1);
    $author = getVar('get', 'author', 'var', '');
    $file = getVar('get', 'file', 'var', '');
    $search = getVar('get', 'search', 'var', '');
    $date_from = getVar('get', 'date_from', 'var', '');
    $date_to = getVar('get', 'date_to', 'var', '');
    $export = getVar('get', 'export', 'var', '');

    // Export-Handling
    if ($export && $conflog['export_enabled']) {
        export($export);
        return;
    }

    // Filter-Formular
    $cont .= '<form action="'.$aroute.'.php" method="get" class="sl_filter_form">';
    $cont .= '<input type="hidden" name="name" value="changelog">';
    $cont .= '<div style="background: #f9f9f9; padding: 15px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px;">';
    $cont .= '<strong>Filter & Suche:</strong><br><br>';
    $cont .= '<table class="sl_table_conf"><tr>';
    $cont .= '<td><input type="text" name="search" value="'.htmlspecialchars($search).'" placeholder="Suche in Commits..." class="sl_conf" style="width: 200px;"></td>';
    $cont .= '<td><input type="text" name="author" value="'.htmlspecialchars($author).'" placeholder="Autor..." class="sl_conf" style="width: 180px;"></td>';
    $cont .= '<td><input type="text" name="file" value="'.htmlspecialchars($file).'" placeholder="Datei..." class="sl_conf" style="width: 180px;"></td>';
    $cont .= '</tr><tr>';
    $cont .= '<td><input type="date" name="date_from" value="'.htmlspecialchars($date_from).'" placeholder="Von Datum" class="sl_conf" style="width: 150px;"></td>';
    $cont .= '<td><input type="date" name="date_to" value="'.htmlspecialchars($date_to).'" placeholder="Bis Datum" class="sl_conf" style="width: 150px;"></td>';
    $cont .= '<td><button type="submit" class="sl_but_blue">Filtern</button> ';
    $cont .= '<a href="'.$aroute.'.php?name=changelog" class="sl_but_gray">Zurücksetzen</a></td>';
    $cont .= '</tr></table>';
    $cont .= '</div></form>';

    // Commits abrufen (GitHub oder Lokal)
    $source = $conflog['source'] ?? 'local';
    $commits = [];

    if ($source === 'github') {
        // GitHub API
        $github_owner = $conflog['github_owner'] ?? '';
        $github_repo = $conflog['github_repo'] ?? '';
        $github_token = $conflog['github_token'] ?? '';

        if (empty($github_owner) || empty($github_repo)) {
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => 'GitHub Owner/Repo nicht konfiguriert. Bitte in den Einstellungen angeben.']);
        } else {
            $filters = [
                'author' => $author,
                'search' => $search,
                'since' => $date_from,
                'until' => $date_to
            ];

            $limit = $conflog['limit'] ?? 50;
            $commits = commits($github_owner, $github_repo, $limit, $github_token, $filters);

            if (empty($commits)) {
                global $github_error;
                $error_msg = $github_error ?: 'Keine Commits von GitHub geladen. Prüfen Sie Owner/Repo oder API-Zugriff.';
                $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $error_msg]);
            }
        }
    } else {
        // Lokales Git
        $gitlog = [];
        $git_dir = realpath(__DIR__.'/../../');
        $git_exe = 'C:\\Program Files\\Git\\cmd\\git.exe';

        if (!file_exists($git_exe)) $git_exe = 'git';

        $old_dir = getcwd();
        chdir($git_dir);

        // Filter-Command bauen
        $git_filters = '';
        if ($author) $git_filters .= ' --author="'.escapeshellarg($author).'"';
        if ($search) $git_filters .= ' --grep="'.escapeshellarg($search).'"';
        if ($date_from) $git_filters .= ' --since="'.escapeshellarg($date_from).'"';
        if ($date_to) $git_filters .= ' --until="'.escapeshellarg($date_to).'"';
        if ($file) $git_filters .= ' -- '.escapeshellarg($file);

        $limit = $conflog['limit'] ?? 50;
        $cmd = '"'.$git_exe.'" log --pretty=format:"COMMIT_START||%H||%h||%ad||%an||%ae||%s||%b||COMMIT_END" --date=format:"%Y-%m-%d %H:%M" --numstat '.$git_filters.' -'.$limit.' 2>&1';
        exec($cmd, $gitlog, $return_code);
        chdir($old_dir);

        if ($return_code !== 0 || empty($gitlog)) {
            $error_msg = 'Git-Historie konnte nicht geladen werden.<br>';
            $error_msg .= 'Git-Verzeichnis: '.$git_dir.'<br>';
            $error_msg .= 'Git-Executable: '.$git_exe.'<br>';
            $error_msg .= 'Return Code: '.$return_code;
            if (!empty($gitlog)) $error_msg .= '<br>Output: '.implode('<br>', $gitlog);
            $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $error_msg]);
        } else {
            // Commits parsen
            $current_commit = null;
            $files = [];

            foreach ($gitlog as $line) {
                if (strpos($line, 'COMMIT_START||') === 0) {
                    if ($current_commit) {
                        $current_commit['files'] = $files;
                        $commits[] = $current_commit;
                    }
                    $parts = explode('||', $line);
                    if (count($parts) >= 8) {
                        $current_commit = [
                            'full_hash' => $parts[1],
                            'hash' => $parts[2],
                            'date' => $parts[3],
                            'author' => $parts[4],
                            'email' => $parts[5],
                            'subject' => $parts[6],
                            'body' => trim($parts[7])
                        ];
                        $files = [];
                    }
                } elseif (strpos($line, 'COMMIT_END') === 0) {
                    continue;
                } elseif ($current_commit && preg_match('/^(\d+|-)\s+(\d+|-)\s+(.+)$/', $line, $matches)) {
                    $files[] = [
                        'added' => $matches[1] === '-' ? 0 : intval($matches[1]),
                        'deleted' => $matches[2] === '-' ? 0 : intval($matches[2]),
                        'file' => $matches[3]
                    ];
                }
            }

            if ($current_commit) {
                $current_commit['files'] = $files;
                $commits[] = $current_commit;
            }
        }
    }

    // Nur fortfahren wenn Commits vorhanden
    if (!empty($commits)) {

        // Pagination
        $per_page = $conflog['per_page'] ?? 10;
        $total_commits = count($commits);
        $total_pages = ceil($total_commits / $per_page);
        $page = max(1, min($page, $total_pages));
        $offset = ($page - 1) * $per_page;
        $commits_page = array_slice($commits, $offset, $per_page);

        // Anzeige
        $cont .= setTemplateBasic('open');
        $cont .= '<div class="sl_changelog">';
        $cont .= '<div style="margin-bottom: 15px;"><strong>Gesamt: '.$total_commits.' Commits | Seite '.$page.' von '.$total_pages.'</strong></div>';

        // Datum-Gruppierung
        if ($conflog['group_by_date']) {
            $commits_page = groupbydate($commits_page);
        }

        $i = 0;
        foreach ($commits_page as $commit) {
            if (isset($commit['date_header'])) {
                $cont .= '<div style="background: #4CAF50; color: white; padding: 8px; margin: 20px 0 10px 0; font-weight: bold; border-radius: 4px;">';
                $cont .= $commit['date_header'];
                $cont .= '</div>';
                continue;
            }
            $cont .= render($commit, $i, $conflog);
            $i++;
        }

        $cont .= '</div>';

        // Pagination via setPageNumbers()
        $query = http_build_query(array_filter([
            'name' => 'changelog',
            'author' => $author,
            'file' => $file,
            'search' => $search,
            'date_from' => $date_from,
            'date_to' => $date_to
        ]));
        $url = $query ? $query.'&' : 'name=changelog&';
        $cont .= setPageNumbers(
            'pagenum',
            'changelog',
            $total_commits,
            $total_pages,
            $per_page,
            $url,
            10,
            $page,
            '',
            'page'
        );

        $cont .= setTemplateBasic('close');
    }

    echo $cont;
    foot();
}

function groupbydate(array $commits): array {
    $grouped = [];
    $last_date = '';
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    foreach ($commits as $commit) {
        $commit_date = substr($commit['date'], 0, 10);

        if ($commit_date !== $last_date) {
            $last_date = $commit_date;
            $date_label = $commit_date;

            if ($commit_date === $today) {
                $date_label = 'Heute ('.$commit_date.')';
            } elseif ($commit_date === $yesterday) {
                $date_label = 'Gestern ('.$commit_date.')';
            } elseif (strtotime($commit_date) > strtotime('-7 days')) {
                $date_label = 'Diese Woche ('.$commit_date.')';
            }

            $grouped[] = ['date_header' => $date_label];
        }

        $grouped[] = $commit;
    }

    return $grouped;
}

function render(array $commit, int $index, array $conflog): string {
    $cont = '<div class="sl_commit" style="border: 1px solid #ddd; margin: 10px 0; padding: 15px; background: '.($index % 2 ? '#f9f9f9' : '#fff').'">';

    // Header
    $cont .= '<div style="border-bottom: 2px solid #4CAF50; padding-bottom: 10px; margin-bottom: 10px;">';
    $cont .= '<span style="font-weight: bold; font-size: 16px;">'.htmlspecialchars($commit['subject']).'</span>';
    $cont .= ' <code style="background: #f0f0f0; padding: 2px 6px; margin-left: 10px;">'.$commit['hash'].'</code>';
    $cont .= '</div>';

    // Meta-Info
    $cont .= '<div style="color: #666; font-size: 13px; margin-bottom: 10px;">';
    $cont .= '<strong>Autor:</strong> '.htmlspecialchars($commit['author']).' &lt;'.htmlspecialchars($commit['email']).'&gt; ';
    $cont .= '| <strong>Datum:</strong> '.$commit['date'];
    $cont .= '</div>';

    // Body
    if (!empty($commit['body']) && $commit['body'] !== 'COMMIT_END') {
        $body = htmlspecialchars($commit['body']);
        $body = str_replace("\n", '<br>', $body);
        $cont .= '<div style="background: #f5f5f5; padding: 10px; margin: 10px 0; border-left: 3px solid #2196F3;">';
        $cont .= $body;
        $cont .= '</div>';
    }

    // Datei-Statistiken
    if ($conflog['show_stats'] && !empty($commit['files'])) {
        $total_add = $total_del = $file_count = 0;
        foreach ($commit['files'] as $f) {
            $total_add += $f['added'];
            $total_del += $f['deleted'];
            $file_count++;
        }

        $cont .= '<div style="margin: 10px 0;">';
        $cont .= '<strong>Änderungen:</strong> ';
        $cont .= '<span style="color: green;">+'.$total_add.'</span> / ';
        $cont .= '<span style="color: red;">-'.$total_del.'</span> | ';
        $cont .= '<strong>'.$file_count.' '.($file_count === 1 ? 'Datei' : 'Dateien').'</strong>';
        $cont .= '</div>';

        // Dateiliste
        if ($conflog['show_files']) {
            if ($file_count <= 5) {
                $cont .= '<div style="font-size: 12px; color: #555;">';
                foreach ($commit['files'] as $f) {
                    $cont .= '<div style="padding: 2px 0;">';
                    $cont .= '<span style="color: green;">+'.str_pad($f['added'], 3, ' ', STR_PAD_LEFT).'</span> ';
                    $cont .= '<span style="color: red;">-'.str_pad($f['deleted'], 3, ' ', STR_PAD_LEFT).'</span> ';
                    $cont .= htmlspecialchars($f['file']);
                    $cont .= '</div>';
                }
                $cont .= '</div>';
            } else {
                $cont .= '<details style="font-size: 12px; color: #555; margin-top: 5px;">';
                $cont .= '<summary style="cursor: pointer;">Zeige alle '.$file_count.' Dateien</summary>';
                $cont .= '<div style="margin-top: 5px;">';
                foreach ($commit['files'] as $f) {
                    $cont .= '<div style="padding: 2px 0;">';
                    $cont .= '<span style="color: green;">+'.str_pad($f['added'], 3, ' ', STR_PAD_LEFT).'</span> ';
                    $cont .= '<span style="color: red;">-'.str_pad($f['deleted'], 3, ' ', STR_PAD_LEFT).'</span> ';
                    $cont .= htmlspecialchars($f['file']);
                    $cont .= '</div>';
                }
                $cont .= '</div>';
                $cont .= '</details>';
            }
        }
    }

    $cont .= '</div>';
    return $cont;
}

function export(): void {
    global $conflog;
    $format = getVar('get', 'id', 'var');
    $source = $conflog['source'] ?? 'local';
    $limit = $conflog['limit'] ?? 50;
    $commits = [];

    if ($source === 'github') {
        // GitHub API Export
        $github_owner = $conflog['github_owner'] ?? '';
        $github_repo = $conflog['github_repo'] ?? '';
        $github_token = $conflog['github_token'] ?? '';

        if ($github_owner && $github_repo) {
            $commits = commits($github_owner, $github_repo, $limit, $github_token);
        }
    } else {
        // Lokales Git Export
        $gitlog = [];
        $git_dir = realpath(__DIR__.'/../../');
        $git_exe = 'C:\\Program Files\\Git\\cmd\\git.exe';
        if (!file_exists($git_exe)) $git_exe = 'git';

        $old_dir = getcwd();
        chdir($git_dir);
        $cmd = '"'.$git_exe.'" log --pretty=format:"COMMIT_START||%H||%h||%ad||%an||%ae||%s||%b||COMMIT_END" --date=format:"%Y-%m-%d %H:%M" -'.$limit.' 2>&1';
        exec($cmd, $gitlog, $return_code);
        chdir($old_dir);

        $current_commit = null;

        foreach ($gitlog as $line) {
            if (strpos($line, 'COMMIT_START||') === 0) {
                if ($current_commit) $commits[] = $current_commit;
                $parts = explode('||', $line);
                if (count($parts) >= 8) {
                    $current_commit = [
                        'hash' => $parts[2],
                        'date' => $parts[3],
                        'author' => $parts[4],
                        'subject' => $parts[6],
                        'body' => trim($parts[7])
                    ];
                }
            } elseif (strpos($line, 'COMMIT_END') === 0) {
                continue;
            }
        }
        if ($current_commit) $commits[] = $current_commit;
    }

    $filename = 'changelog_'.date('Y-m-d').'.'.$format;

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    if ($format === 'md') {
        echo "# SLAED CMS Changelog\n\n";
        echo "Generiert am: ".date('Y-m-d H:i:s')."\n\n";
        echo "---\n\n";
        foreach ($commits as $c) {
            echo "## ".$c['subject']."\n\n";
            echo "**Commit:** `".$c['hash']."`  \n";
            echo "**Autor:** ".$c['author']."  \n";
            echo "**Datum:** ".$c['date']."  \n\n";
            if ($c['body'] && $c['body'] !== 'COMMIT_END') {
                echo $c['body']."\n\n";
            }
            echo "---\n\n";
        }
    } else {
        echo "SLAED CMS Changelog\n";
        echo "===================\n\n";
        echo "Generiert am: ".date('Y-m-d H:i:s')."\n\n";
        foreach ($commits as $c) {
            echo $c['subject']."\n";
            echo "Commit: ".$c['hash']." | Autor: ".$c['author']." | Datum: ".$c['date']."\n";
            if ($c['body'] && $c['body'] !== 'COMMIT_END') {
                echo $c['body']."\n";
            }
            echo "\n".str_repeat('-', 80)."\n\n";
        }
    }
    exit;
}

function conf(): void {
    global $aroute, $conflog;
    head();
    checkConfigFile('changelog.php');
    $cont = navi(0, 1, 0, 0);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$aroute.'.php" method="post">';
    $cont .= '<table class="sl_table_conf">';

    // Quelle
    $source = $conflog['source'] ?? 'local';
    $cont .= '<tr><td colspan="2" style="background: #4CAF50; color: white; padding: 8px; font-weight: bold;">Changelog-Quelle</td></tr>';
    $cont .= '<tr><td><strong>Quelle:</strong></td><td>';
    $cont .= '<select name="source" class="sl_conf" onchange="toggleGithubFields(this.value)">';
    $cont .= '<option value="local"'.($source === 'local' ? ' selected' : '').'>Lokales Git</option>';
    $cont .= '<option value="github"'.($source === 'github' ? ' selected' : '').'>GitHub API</option>';
    $cont .= '</select></td></tr>';

    // GitHub-Optionen
    $cont .= '<tbody id="github_fields" style="display: '.($source === 'github' ? 'table-row-group' : 'none').'">';
    $cont .= '<tr><td><strong>GitHub Owner:</strong><br><small>z.B. "anthropics"</small></td><td><input type="text" name="github_owner" value="'.htmlspecialchars($conflog['github_owner'] ?? '').'" class="sl_conf" placeholder="owner"></td></tr>';
    $cont .= '<tr><td><strong>GitHub Repository:</strong><br><small>z.B. "claude-code"</small></td><td><input type="text" name="github_repo" value="'.htmlspecialchars($conflog['github_repo'] ?? '').'" class="sl_conf" placeholder="repo"></td></tr>';
    $cont .= '<tr><td><strong>GitHub Token (optional):</strong><br><small>Für höhere Rate Limits</small></td><td><input type="text" name="github_token" value="'.htmlspecialchars($conflog['github_token'] ?? '').'" class="sl_conf" placeholder="ghp_..."></td></tr>';
    $cont .= '</tbody>';

    // Allgemeine Optionen
    $cont .= '<tr><td colspan="2" style="background: #2196F3; color: white; padding: 8px; font-weight: bold; margin-top: 10px;">Anzeige-Optionen</td></tr>';
    $cont .= '<tr><td><strong>Anzahl Commits (gesamt):</strong></td><td><input type="number" name="limit" value="'.($conflog['limit'] ?? 50).'" class="sl_conf" min="10" max="500"></td></tr>';
    $cont .= '<tr><td><strong>Commits pro Seite:</strong></td><td><input type="number" name="per_page" value="'.($conflog['per_page'] ?? 10).'" class="sl_conf" min="5" max="50"></td></tr>';
    $cont .= '<tr><td><strong>Nach Datum gruppieren:</strong></td><td><input type="checkbox" name="group_by_date" value="1" '.($conflog['group_by_date'] ? 'checked' : '').'></td></tr>';
    $cont .= '<tr><td><strong>Dateien anzeigen:</strong></td><td><input type="checkbox" name="show_files" value="1" '.($conflog['show_files'] ? 'checked' : '').'></td></tr>';
    $cont .= '<tr><td><strong>Statistiken anzeigen:</strong></td><td><input type="checkbox" name="show_stats" value="1" '.($conflog['show_stats'] ? 'checked' : '').'></td></tr>';
    $cont .= '<tr><td><strong>Export aktivieren:</strong></td><td><input type="checkbox" name="export_enabled" value="1" '.($conflog['export_enabled'] ? 'checked' : '').'></td></tr>';
    $cont .= '<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="changelog"><input type="hidden" name="op" value="saveconf"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr>';
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

function confsave(): void {
    global $aroute;
    $cont = [
        'source' => getVar('post', 'source', 'var', 'local'),
        'github_owner' => getVar('post', 'github_owner', 'text', ''),
        'github_repo' => getVar('post', 'github_repo', 'text', ''),
        'github_token' => getVar('post', 'github_token', 'text', ''),
        'limit' => getVar('post', 'limit', 'num', 50),
        'per_page' => getVar('post', 'per_page', 'num', 10),
        'group_by_date' => getVar('post', 'group_by_date', 'num', 0),
        'show_files' => getVar('post', 'show_files', 'num', 0),
        'show_stats' => getVar('post', 'show_stats', 'num', 0),
        'export_enabled' => getVar('post', 'export_enabled', 'num', 0)
    ];
    setConfigFile('changelog.php', 'conflog', $cont);
    header('Location: '.$aroute.'.php?name=changelog&op=conf');
    exit;
}

function info(): void {
    global $conflog;
    $tab = ($conflog['export_enabled'] ?? true) ? 4 : 2;
    head();
    $source = $conflog['source'] ?? 'local';
    $source_label = $source === 'github' ? 'GitHub API' : 'Lokales Git';

    $info = '<h3>Changelog Modul</h3>
    <p>Dieses Modul zeigt die Git-Historie des SLAED CMS an.</p>
    <p><strong>Aktuelle Quelle:</strong> '.$source_label.'</p>
    <h4>Features:</h4>
    <ul>
    <li><strong>Flexible Quellen:</strong> Lokales Git-Repository ODER GitHub API (konfigurierbar)</li>
    <li><strong>Filter:</strong> Nach Autor, Datei, Datum und Suchbegriff filtern</li>
    <li><strong>Pagination:</strong> Blättern durch alle Commits</li>
    <li><strong>Export:</strong> Als TXT oder Markdown exportieren</li>
    <li><strong>Datum-Gruppierung:</strong> Commits nach Datum gruppieren (Heute, Gestern, etc.)</li>
    <li><strong>Konfigurierbar:</strong> Alle Einstellungen im Preferences-Tab anpassbar</li>
    <li><strong>Statistiken:</strong> Zeigt +/- Zeilen und geänderte Dateien (nur bei lokalem Git)</li>
    </ul>
    <h4>GitHub API:</h4>
    <ul>
    <li>Funktioniert ohne lokales Git-Repository</li>
    <li>Kann Remote-Repositories abfragen (z.B. anthropics/claude-code)</li>
    <li>Optional: GitHub Token für höhere Rate Limits (60 → 5000 Requests/Stunde)</li>
    <li>Filter: Autor, Datum, Suchbegriff werden unterstützt</li>
    </ul>';
    echo navi(0, $tab, 0, 0).'<div id="repadm_info">'.$info.'</div>';
    foot();
}

switch ($op) {
    default: changelog(); break;
    case 'conf': conf(); break;
    case 'saveconf': confsave(); break;
    case 'export': export(); break;
    case 'info': info(); break;
}
