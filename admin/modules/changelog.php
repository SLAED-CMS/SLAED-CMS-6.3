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
	head();
	$cont = changelog_navi(0, 0, 0, 0);

	// Git-Log abrufen
	$gitlog = [];
	$git_dir = realpath(__DIR__.'/../../');
	$git_exe = 'C:\\Program Files\\Git\\cmd\\git.exe';

	// Fallback wenn Git nicht im Standard-Pfad
	if (!file_exists($git_exe)) $git_exe = 'git';

	$old_dir = getcwd();
	chdir($git_dir);
	$cmd = '"'.$git_exe.'" log --pretty=format:"%h||%ad||%an||%s" --date=format:"%Y-%m-%d %H:%M" -50 2>&1';
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
		$cont .= '<table class="sl_table_list_sort"><thead><tr><th>Commit</th><th>Datum</th><th>Autor</th><th>Änderung</th></tr></thead><tbody>';

		foreach ($gitlog as $line) {
			$parts = explode('||', $line);
			if (count($parts) === 4) {
				list($hash, $date, $author, $message) = $parts;
				$cont .= '<tr>';
				$cont .= '<td><code>'.$hash.'</code></td>';
				$cont .= '<td>'.$date.'</td>';
				$cont .= '<td>'.htmlspecialchars($author).'</td>';
				$cont .= '<td>'.htmlspecialchars($message).'</td>';
				$cont .= '</tr>';
			}
		}

		$cont .= '</tbody></table>';
		$cont .= setTemplateBasic('close');
	}

	echo $cont;
	foot();
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
