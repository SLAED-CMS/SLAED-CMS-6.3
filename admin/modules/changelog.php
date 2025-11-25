<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');

function changelog_navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
	panel();
	$ops = ['changelog', 'changelog_info'];
	$lang = [_HOME, _INFO];
	return getAdminTabs('Changelog', 'editor.png', '', $ops, $lang, [], [], $tab, $subtab);
}

function changelog(): void {
	global $admin_file;
	head();
	$cont = changelog_navi(0, 0, 0, 0);

	// Git-Log abrufen (ausführlich mit Body und Stats)
	$gitlog = [];
	$git_dir = realpath(__DIR__.'/../../');
	$git_exe = 'C:\\Program Files\\Git\\cmd\\git.exe';

	// Fallback wenn Git nicht im Standard-Pfad
	if (!file_exists($git_exe)) $git_exe = 'git';

	$old_dir = getcwd();
	chdir($git_dir);
	$cmd = '"'.$git_exe.'" log --pretty=format:"COMMIT_START||%H||%h||%ad||%an||%ae||%s||%b||COMMIT_END" --date=format:"%Y-%m-%d %H:%M" --numstat -50 2>&1';
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
		$cont .= setTemplateBasic('open');
		$cont .= '<div class="sl_changelog">';

		$i = 0;
		$current_commit = null;
		$files = [];

		foreach ($gitlog as $line) {
			if (strpos($line, 'COMMIT_START||') === 0) {
				// Vorherigen Commit ausgeben
				if ($current_commit) {
					$cont .= render_commit($current_commit, $files, $i);
					$i++;
				}
				// Neuen Commit parsen
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
				// Marker für Ende des Commits
				continue;
			} elseif ($current_commit && preg_match('/^(\d+|-)\s+(\d+|-)\s+(.+)$/', $line, $matches)) {
				// Datei-Statistik
				$files[] = [
					'added' => $matches[1] === '-' ? 0 : intval($matches[1]),
					'deleted' => $matches[2] === '-' ? 0 : intval($matches[2]),
					'file' => $matches[3]
				];
			}
		}

		// Letzten Commit ausgeben
		if ($current_commit) {
			$cont .= render_commit($current_commit, $files, $i);
		}

		$cont .= '</div>';
		$cont .= setTemplateBasic('close');
	}

	echo $cont;
	foot();
}

function render_commit(array $commit, array $files, int $index): string {
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

	// Body (wenn vorhanden)
	if (!empty($commit['body']) && $commit['body'] !== 'COMMIT_END') {
		$body = htmlspecialchars($commit['body']);
		$body = str_replace("\n", '<br>', $body);
		$cont .= '<div style="background: #f5f5f5; padding: 10px; margin: 10px 0; border-left: 3px solid #2196F3;">';
		$cont .= $body;
		$cont .= '</div>';
	}

	// Datei-Statistiken
	if (!empty($files)) {
		$total_add = $total_del = $file_count = 0;
		foreach ($files as $f) {
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

		// Dateiliste (kollabierbar wenn mehr als 5)
		if ($file_count <= 5) {
			$cont .= '<div style="font-size: 12px; color: #555;">';
			foreach ($files as $f) {
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
			foreach ($files as $f) {
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

	$cont .= '</div>';
	return $cont;
}

function changelog_info(): void {
	head();
	$info = '<h3>Changelog Modul</h3>
	<p>Dieses Modul zeigt die Git-Historie des SLAED CMS an.</p>
	<ul>
	<li>Alle Commits werden chronologisch aufgelistet</li>
	<li>Du kannst die Historie für User öffentlich machen</li>
	<li>Zeigt die letzten 50 Änderungen</li>
	</ul>';
	echo changelog_navi(0, 1, 0, 0).'<div id="repadm_info">'.$info.'</div>';
	foot();
}

switch($op) {
	case 'changelog':
	changelog();
	break;

	case 'changelog_info':
	changelog_info();
	break;
}
