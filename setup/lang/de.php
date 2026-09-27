<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

define('_ADMIN_SE','Installation des Verwalters');
define('_CONFIG','Generelle Seitenangaben');
define('_CONF_1','Datenbank Hostname');
define('_CONF_2','Datenbank Username');
define('_CONF_3','Datenbank Kennwort');
define('_CONF_4','Datenbank Name');
define('_CONF_9','Prefix der Datenbanktabelle');
define('_CONF_10','Administration Panel Datei Name');
define('_CONF_10_INFO','Zwecks der Sicherheit ändern Sie die standardisierte Bezeichnung bei der Datei admin.php. Umbenennen Sie ihn darin, dass Sie, zum Beispiel erdacht haben: %1$s.php geben Sie ein seine neue Bezeichnung, aber ohne Abschluss «.php» für unseren Fall: %1$s');
define('_CONF_11','Code aus config/setup.unlock');
define('_CONF_3_INFO','Lassen Sie das Feld leer, um das bisherige Passwort der Website beizubehalten.');
define('_LANG','Sprache für das Interface auswählen');
define('_NEXT_SE','Weiter');
define('_EXTSETUP','Eine erforderliche PHP-Erweiterung ist auf Ihrem Server nicht installiert');
define('_PHPSETUP','Die eingerichtete PHP Version auf Ihren Server entspricht den minimalen Forderungen des Systems nicht, sie soll nicht niedriger als PHP 8.4 sein!');
define('_SAVE_NEW','Installation und Konfiguration');
define('_SAVE_UPDATE','Aktualisierung und Konfiguration');
define('_SERRORPERM','hat kein Erlaubnis für die Aufzeichnung auf dem Server.<br>Geben Sie die nötigen Attribute');
define('_SETUPLOCK','Die Website ist bereits installiert, daher ist das Installationsprogramm gesperrt. Um ein Update auszuführen, laden Sie die Datei config/setup.unlock mit einem eigenen Code aus mindestens 8 Zeichen hoch, öffnen Sie setup.php erneut und geben Sie diesen Code ein; ein erfolgreicher Lauf entfernt die Datei.');
define('_SETUPCODE','Der Code stimmt nicht mit dem in config/setup.unlock überein. Es wurde nichts geändert.');
define('_SETUPJOUR','Ein nicht abgeschlossener Konfigurationsvorgang der Website wartet in storage/backup/config. Schließen Sie ihn in der Wiederherstellung der Konfiguration im Administrationsbereich ab und starten Sie das Installationsprogramm dann erneut. Es wurde nichts geändert.');
define('_SETUPAFILE','Der Dateiname des Administrationsbereichs darf nur lateinische Buchstaben, Ziffern, _ und - enthalten und keine andere Datei im Stammverzeichnis der Website als admin.php oder den aktuellen Administrationsbereich benennen; löschen Sie zuerst die Datei des Administrationsbereichs der alten Version. Es wurde nichts geändert.');
define('_SETUPPREFIX','Das Tabellenpräfix darf nur lateinische Buchstaben, Ziffern und _ enthalten, höchstens 32 Zeichen. Es wurde nichts geändert.');
define('_SETUPINNODB','Diese Tabellen sind nicht InnoDB, konvertieren Sie sie und starten Sie das Update erneut:');
define('_SETUPTABLES','Die Tabellen %1$s_users und %1$s_admins sind nicht beide in der Datenbank, prüfen Sie das Tabellenpräfix der Website.');
define('_SETUPTAKEN','Die Datenbank enthält bereits Tabellen mit dem Präfix %s_, eine neue Installation braucht eine leere Datenbank oder ein anderes Präfix.');
define('_SETUPTYPE','Wählen Sie eine neue Installation oder eines der angebotenen Updates. Es wurde nichts geändert.');
define('_SETUPVER','Der Datenbankserver %1$s ist älter als %2$s.');
define('_SETUP_NEW','Neuinstallation des Systems');
define('_SETUP_SLAED','Installation SLAED CMS');
define('_SUPDATE','Systemupdate');
define('_TABLE','The table');
