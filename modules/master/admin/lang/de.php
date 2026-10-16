<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

define('_MASTER_RELEASES', 'Releases');
define('_MASTER_SITES', 'Websites');
define('_MASTER_NOTES', 'Hinweise');
define('_MASTER_KEYS', 'Schlüssel');
define('_MASTER_INBOX', 'Wartet in inbox/');
define('_MASTER_ACCEPT', 'Annehmen');
define('_MASTER_ACCEPTED', 'Angenommen');
define('_MASTER_CHANGED', 'Geändert');
define('_MASTER_EXPIRES', 'Manifest läuft ab');
define('_MASTER_EXPWARN', 'Das Manifest von %1$s läuft am %2$s ab: neu signieren mit php tools/sign.php --refresh %1$s, veröffentlichen und erneut annehmen.');
define('_MASTER_WAIT', 'Wartet');
define('_MASTER_BYOWNER', 'vom Inhaber gesetzt');
define('_MASTER_BYAUTO', 'automatisch: %d fehlgeschlagen, %d erfolgreich');
define('_MASTER_TOBETA', 'Nach beta');
define('_MASTER_TOSTABLE', 'Nach stable');
define('_MASTER_SHARE', 'Anteil der Auslieferung');
define('_MASTER_SHAREDESC', 'Der Anteil der Websites, denen Release %s angeboten wird, von 0 bis 100. Eine Website fällt nach ihrer Kennung und der Version hinein, so hat jedes Release eigene erste Websites.');
define('_MASTER_DONE', 'Angenommen: %s');
define('_MASTER_REFUSED', 'Abgelehnt: %s');
define('_MASTER_RSIGN', 'die Signatur passt nicht zum gültigen Schlüssel');
define('_MASTER_REXP', 'das Manifest ist abgelaufen');
define('_MASTER_RZIP', 'ein Archiv weicht von seiner signierten Größe oder SHA-256 ab');
define('_MASTER_RMISS', 'die Dateien fehlen');
define('_MASTER_RWRITE', 'die Dateien konnten nicht geschrieben werden');
define('_MASTER_RFORM', 'ein Manifest ist unvollständig oder hat ein anderes Format, eine andere Version oder einen anderen Kanal');
define('_MASTER_SID', 'Kennung');
define('_MASTER_DOMAIN', 'Domain');
define('_MASTER_NOHOST', 'nicht gemeldet');
define('_MASTER_CHAN', 'Kanal');
define('_MASTER_EXT', 'Erweiterungen');
define('_MASTER_ERRS', 'PHP- / DB-Fehler');
define('_MASTER_FIRST', 'Erste Prüfung');
define('_MASTER_LAST', 'Letzte Prüfung');
define('_MASTER_SILENT', 'Seit über %d Tagen still');
define('_MASTER_FAILS', 'Fehlgeschlagene Installationen');
define('_MASTER_STEP', 'Schritt %d');
define('_MASTER_STEPS', 'Schritt');
define('_MASTER_COUNT', 'Websites');
define('_MASTER_NOSOD', 'Ohne sodium');
define('_MASTER_NOZIP', 'Ohne zip');
define('_MASTER_LEVEL', 'Stufe');
define('_MASTER_TO', 'Empfänger');
define('_MASTER_TOALL', 'alle Websites');
define('_MASTER_RANGE', 'Versionen');
define('_MASTER_UNTIL', 'Bis');
define('_MASTER_NOEND', 'ohne Frist');
define('_MASTER_PRIMARY', 'Hauptschlüssel');
define('_MASTER_RECOVERY', 'Wiederherstellungsschlüssel');
define('_MASTER_FACTORY', 'werkseitig');
define('_MASTER_INFORCE', 'gültig');
define('_MASTER_PRINT', 'Fingerabdruck');
define('_MASTER_KEYERR', 'Eine Schlüsselerklärung in inbox/keys/ besteht die Prüfung nicht (%s); gültig sind die Schlüssel davor.');
define('_MASTER_KEEPSITE', 'Stille Websites aufbewahren, Tage');
define('_MASTER_KEEPSTAT', 'Tagessummen aufbewahren, Tage');
define('_MASTER_LIMIT', 'Schwelle der automatischen Pause (Anteil fehlgeschlagener Installationen in Prozent)');
define('_MASTER_JOBS', 'Beim Speichern werden einmalig die Planer-Aufgaben masterclean (Tagessummen und Bereinigung) und masterwatch (automatische Pause, stündlich) angelegt.');
