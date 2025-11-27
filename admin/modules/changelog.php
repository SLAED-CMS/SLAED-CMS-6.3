<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');
require_once CONFIG_DIR.'/changelog.php';

function changelog_navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    panel();
    $ops = ['changelog', 'changelog_conf', 'changelog_info'];
    $lang = [_HOME, _PREFERENCES, _INFO];
    return getAdminTabs('Changelog', 'editor.png', '', $ops, $lang, [], [], $tab, $subtab);
}

function changelog(): void {
    global $admin_file, $conflog;
    head();
    checkConfigFile('changelog.php');
    $cont = changelog_navi(0, 0, 0, 0);

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
        changelog_export($export);
        return;
    }

    // Filter-Formular
    $cont .= '<form action="'.$admin_file.'.php" method="get" class="sl_filter_form">';
    $cont .= '<input type="hidden" name="op" value="changelog">';
    $cont .= '<div style="background: #f9f9f9; padding: 15px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px;">';
    $cont .= '<strong>Filter & Suche:</strong><br><br>';
    $cont .= '<table class="sl_table_conf"><tr>';
    $cont .= '<td><input type="text" name="search" value="'.htmlspecialchars($search).'" placeholder="Suche in Commits..." class="sl_conf"></td>';
    $cont .= '<td><input type="text" name="author" value="'.htmlspecialchars($author).'" placeholder="Autor..." class="sl_conf"></td>';
    $cont .= '<td><input type="text" name="file" value="'.htmlspecialchars($file).'" placeholder="Datei..." class="sl_conf"></td>';
    $cont .= '</tr><tr>';
    $cont .= '<td><input type="date" name="date_from" value="'.htmlspecialchars($date_from).'" placeholder="Von Datum" class="sl_conf"></td>';
    $cont .= '<td><input type="date" name="date_to" value="'.htmlspecialchars($date_to).'" placeholder="Bis Datum" class="sl_conf"></td>';
    $cont .= '<td><button type="submit" class="sl_but_blue">Filtern</button> ';
    $cont .= '<a href="'.$admin_file.'.php?op=changelog" class="sl_but_gray">Zurücksetzen</a></td>';
    $cont .= '</tr></table>';
    if ($conflog['export_enabled']) {
        $cont .= '<div style="margin-top: 10px;">';
        $cont .= '<strong>Export:</strong> ';
        $cont .= '<a href="'.$admin_file.'.php?op=changelog&export=txt" class="sl_but_gray">TXT</a> ';
        $cont .= '<a href="'.$admin_file.'.php?op=changelog&export=md" class="sl_but_gray">Markdown</a>';
        $cont .= '</div>';
    }
    $cont .= '</div></form>';

    // Git-Log abrufen
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
        $commits = [];
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
            $commits_page = group_commits_by_date($commits_page);
        }

        $i = 0;
        foreach ($commits_page as $commit) {
            if (isset($commit['date_header'])) {
                $cont .= '<div style="background: #4CAF50; color: white; padding: 8px; margin: 20px 0 10px 0; font-weight: bold; border-radius: 4px;">';
                $cont .= $commit['date_header'];
                $cont .= '</div>';
                continue;
            }
            $cont .= render_commit($commit, $i, $conflog);
            $i++;
        }

        $cont .= '</div>';

        // Pagination Links
        if ($total_pages > 1) {
            $cont .= '<div style="margin: 20px 0; text-align: center;">';
            $query = http_build_query(array_filter([
                'op' => 'changelog',
                'author' => $author,
                'file' => $file,
                'search' => $search,
                'date_from' => $date_from,
                'date_to' => $date_to
            ]));

            if ($page > 1) {
                $cont .= '<a href="'.$admin_file.'.php?'.$query.'&page='.($page-1).'" class="sl_but_blue">« Vorherige</a> ';
            }

            for ($p = max(1, $page - 5); $p <= min($total_pages, $page + 5); $p++) {
                if ($p == $page) {
                    $cont .= '<strong style="padding: 5px 10px; background: #4CAF50; color: white; margin: 0 2px;">'.$p.'</strong> ';
                } else {
                    $cont .= '<a href="'.$admin_file.'.php?'.$query.'&page='.$p.'" class="sl_but_gray">'.$p.'</a> ';
                }
            }

            if ($page < $total_pages) {
                $cont .= '<a href="'.$admin_file.'.php?'.$query.'&page='.($page+1).'" class="sl_but_blue">Nächste »</a>';
            }
            $cont .= '</div>';
        }

        $cont .= setTemplateBasic('close');
    }

    echo $cont;
    foot();
}

function group_commits_by_date(array $commits): array {
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

function render_commit(array $commit, int $index, array $conflog): string {
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

function changelog_export(string $format): void {
    global $conflog;

    $gitlog = [];
    $git_dir = realpath(__DIR__.'/../../');
    $git_exe = 'C:\\Program Files\\Git\\cmd\\git.exe';
    if (!file_exists($git_exe)) $git_exe = 'git';

    $old_dir = getcwd();
    chdir($git_dir);
    $limit = $conflog['limit'] ?? 50;
    $cmd = '"'.$git_exe.'" log --pretty=format:"COMMIT_START||%H||%h||%ad||%an||%ae||%s||%b||COMMIT_END" --date=format:"%Y-%m-%d %H:%M" -'.$limit.' 2>&1';
    exec($cmd, $gitlog, $return_code);
    chdir($old_dir);

    $commits = [];
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

function changelog_conf(): void {
    global $admin_file, $conflog;
    head();
    checkConfigFile('changelog.php');
    $cont = changelog_navi(0, 1, 0, 0);

    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$admin_file.'.php" method="post">';
    $cont .= '<table class="sl_table_conf">';
    $cont .= '<tr><td><strong>Anzahl Commits (gesamt):</strong></td><td><input type="number" name="limit" value="'.($conflog['limit'] ?? 50).'" class="sl_conf" min="10" max="500"></td></tr>';
    $cont .= '<tr><td><strong>Commits pro Seite:</strong></td><td><input type="number" name="per_page" value="'.($conflog['per_page'] ?? 10).'" class="sl_conf" min="5" max="50"></td></tr>';
    $cont .= '<tr><td><strong>Nach Datum gruppieren:</strong></td><td><input type="checkbox" name="group_by_date" value="1" '.($conflog['group_by_date'] ? 'checked' : '').'></td></tr>';
    $cont .= '<tr><td><strong>Dateien anzeigen:</strong></td><td><input type="checkbox" name="show_files" value="1" '.($conflog['show_files'] ? 'checked' : '').'></td></tr>';
    $cont .= '<tr><td><strong>Statistiken anzeigen:</strong></td><td><input type="checkbox" name="show_stats" value="1" '.($conflog['show_stats'] ? 'checked' : '').'></td></tr>';
    $cont .= '<tr><td><strong>Export aktivieren:</strong></td><td><input type="checkbox" name="export_enabled" value="1" '.($conflog['export_enabled'] ? 'checked' : '').'></td></tr>';
    $cont .= '<tr><td colspan="2" class="sl_center"><input type="hidden" name="op" value="changelog_save_conf"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr>';
    $cont .= '</table></form>';
    $cont .= setTemplateBasic('close');

    echo $cont;
    foot();
}

function changelog_save_conf(): void {
    global $admin_file;

    $cont = [
        'limit' => getVar('post', 'limit', 'num', 50),
        'per_page' => getVar('post', 'per_page', 'num', 10),
        'group_by_date' => getVar('post', 'group_by_date', 'num', 0),
        'show_files' => getVar('post', 'show_files', 'num', 0),
        'show_stats' => getVar('post', 'show_stats', 'num', 0),
        'export_enabled' => getVar('post', 'export_enabled', 'num', 0)
    ];

    setConfigFile('changelog.php', 'conflog', $cont);
    header('Location: '.$admin_file.'.php?op=changelog_conf');
}

function changelog_info(): void {
    head();
    $info = '<h3>Changelog Modul</h3>
    <p>Dieses Modul zeigt die Git-Historie des SLAED CMS an.</p>
    <h4>Features:</h4>
    <ul>
    <li><strong>Filter:</strong> Nach Autor, Datei, Datum und Suchbegriff filtern</li>
    <li><strong>Pagination:</strong> Blättern durch alle Commits</li>
    <li><strong>Export:</strong> Als TXT oder Markdown exportieren</li>
    <li><strong>Datum-Gruppierung:</strong> Commits nach Datum gruppieren (Heute, Gestern, etc.)</li>
    <li><strong>Konfigurierbar:</strong> Alle Einstellungen im Preferences-Tab anpassbar</li>
    <li><strong>Statistiken:</strong> Zeigt +/- Zeilen und geänderte Dateien</li>
    </ul>';
    echo changelog_navi(0, 0, 2, 0).'<div id="repadm_info">'.$info.'</div>';
    foot();
}

switch($op) {
    case 'changelog':
        changelog();
        break;

    case 'changelog_conf':
        changelog_conf();
        break;

    case 'changelog_save_conf':
        changelog_save_conf();
        break;

    case 'changelog_info':
        changelog_info();
        break;

    default:
        changelog();
        break;
}
