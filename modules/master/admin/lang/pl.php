<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

define('_MASTER_RELEASES', 'Wydania');
define('_MASTER_SITES', 'Strony');
define('_MASTER_NOTES', 'Powiadomienia');
define('_MASTER_KEYS', 'Klucze');
define('_MASTER_INBOX', 'Czeka w inbox/');
define('_MASTER_ACCEPT', 'Przyjmij');
define('_MASTER_ACCEPTED', 'Przyjęto');
define('_MASTER_CHANGED', 'Zmieniono');
define('_MASTER_EXPIRES', 'Manifest wygasa');
define('_MASTER_EXPWARN', 'Manifest %1$s wygasa %2$s: podpisz go ponownie (php tools/sign.php --refresh %1$s), opublikuj i przyjmij jeszcze raz.');
define('_MASTER_WAIT', 'Oczekuje');
define('_MASTER_BYOWNER', 'ustawiona przez właściciela');
define('_MASTER_BYAUTO', 'automatyczna: %d nieudanych, %d udanych');
define('_MASTER_TOBETA', 'Do beta');
define('_MASTER_TOSTABLE', 'Do stable');
define('_MASTER_SHARE', 'Udział wdrożenia');
define('_MASTER_SHAREDESC', 'Udział stron, którym oferowane jest wydanie %s, od 0 do 100. Strona trafia do niego według swojego identyfikatora i wersji, więc każde wydanie ma własne pierwsze strony.');
define('_MASTER_DONE', 'Przyjęto: %s');
define('_MASTER_REFUSED', 'Odrzucono: %s');
define('_MASTER_RSIGN', 'podpis nie pasuje do obowiązującego klucza');
define('_MASTER_REXP', 'manifest wygasł');
define('_MASTER_RZIP', 'archiwum różni się od podpisanego rozmiaru lub SHA-256');
define('_MASTER_RMISS', 'brak plików');
define('_MASTER_RWRITE', 'nie udało się zapisać plików');
define('_MASTER_RFORM', 'manifest jest niepełny albo ma inny format, wersję lub kanał');
define('_MASTER_SID', 'Identyfikator');
define('_MASTER_DOMAIN', 'Domena');
define('_MASTER_NOHOST', 'nie podano');
define('_MASTER_CHAN', 'Kanał');
define('_MASTER_EXT', 'Rozszerzenia');
define('_MASTER_ERRS', 'Błędy PHP / bazy');
define('_MASTER_FIRST', 'Pierwsze sprawdzenie');
define('_MASTER_LAST', 'Ostatnie sprawdzenie');
define('_MASTER_SILENT', 'Milczą ponad %d dni');
define('_MASTER_FAILS', 'Nieudane instalacje');
define('_MASTER_STEP', 'krok %d');
define('_MASTER_STEPS', 'Krok');
define('_MASTER_COUNT', 'Stron');
define('_MASTER_NOSOD', 'Bez sodium');
define('_MASTER_NOZIP', 'Bez zip');
define('_MASTER_LEVEL', 'Poziom');
define('_MASTER_TO', 'Adresat');
define('_MASTER_TOALL', 'wszystkie strony');
define('_MASTER_RANGE', 'Wersje');
define('_MASTER_UNTIL', 'Do');
define('_MASTER_NOEND', 'bez terminu');
define('_MASTER_PRIMARY', 'Klucz główny');
define('_MASTER_RECOVERY', 'Klucz odzyskiwania');
define('_MASTER_FACTORY', 'fabryczny');
define('_MASTER_INFORCE', 'obowiązujący');
define('_MASTER_PRINT', 'Odcisk');
define('_MASTER_KEYERR', 'Oświadczenie o kluczach w inbox/keys/ nie przeszło weryfikacji (%s); obowiązują klucze sprzed niego.');
define('_MASTER_KEEPSITE', 'Przechowuj milczące strony, dni');
define('_MASTER_KEEPSTAT', 'Przechowuj dzienne sumy, dni');
define('_MASTER_LIMIT', 'Próg automatycznej pauzy (udział nieudanych instalacji w procentach)');
define('_MASTER_JOBS', 'Zapisanie jednorazowo dodaje do harmonogramu zadania masterclean (dzienne sumy i czyszczenie) oraz masterwatch (automatyczna pauza, co godzinę).');
define('_MASTER', 'Master');
