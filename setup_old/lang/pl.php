<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

define('_ADMIN_SE','Instalacja admin');
define('_CONFIG','Podstawowe konfiguracje');
define('_CONF_1','Serwer bazy danych');
define('_CONF_2','Nazwa użytkownika bazy danych');
define('_CONF_3','Hasło użytkownika bazy danych');
define('_CONF_4','Nazwa bazy danych');
define('_CONF_9','Prefiks tabel bazy danych');
define('_CONF_10','Nazwa pliku, aby wejść w dział administracji');
define('_CONF_10_INFO','W celu zapewnienia bezpieczeństwa należy zmienić standardowe nazwa pliku admin.php. Zmień jego nazwę w to, że Można wymyślić, na przykład: %1$s.php i wpisz jej nową nazwę, ale bez zakończenia «.php» w naszym przypadku: %1$s');
define('_CONF_11','Kod z config/setup.unlock');
define('_CONF_3_INFO','Pozostaw pole puste, aby zachować obecne hasło strony.');
define('_LANG','Wybierz język');
define('_NEXT_SE','Kontynuować');
define('_EXTSETUP','Wymagane rozszerzenie PHP nie jest zainstalowane na Twoim serwerze');
define('_PHPSETUP','Wersja PHP zainstalowany na Twoim serwerze nie spełnia minimalne wymagania systemu, powinna być nie niższa PHP 8.4!');
define('_SAVE_NEW','Instalacja i konfiguracja');
define('_SAVE_UPDATE','Aktualizacja i konfiguracja');
define('_SERRORPERM','nie ma odpowiednich uprawnień do zapisu na serwerze.<br>Zaznacz właściwe atrybuty');
define('_SETUPLOCK','Strona jest już zainstalowana, dlatego instalator jest zablokowany. Aby uruchomić aktualizację, prześlij plik config/setup.unlock z własnym kodem o długości co najmniej 8 znaków, ponownie otwórz setup.php i wpisz ten kod; udane uruchomienie usuwa ten plik.');
define('_SETUPCODE','Kod nie zgadza się z kodem w config/setup.unlock. Nic nie zostało zmienione.');
define('_SETUPKEYGONE','Pięć błędnych kodów z rzędu: plik config/setup.unlock został usunięty, a instalator jest ponownie zablokowany. Aby kontynuować, prześlij nowy klucz z własnym kodem o długości co najmniej 8 znaków.');
define('_SETUPJOUR','Niedokończona operacja konfiguracji strony czeka w storage/backup/config. Dokończ ją na ekranie przywracania konfiguracji w panelu administracyjnym, a następnie uruchom instalator ponownie. Nic nie zostało zmienione.');
define('_SETUPAFILE','Nazwa pliku panelu administracyjnego może zawierać tylko litery łacińskie, cyfry, _ i -, nie może brzmieć index ani setup i nie może wskazywać innego pliku w katalogu głównym witryny niż admin.php lub bieżący panel. Nic nie zostało zmienione.');
define('_SETUPPREFIX','Prefiks tabel może zawierać tylko litery łacińskie, cyfry i _, maksymalnie 32 znaki. Nic nie zostało zmienione.');
define('_SETUPINNODB','Te tabele nie są InnoDB, przekonwertuj je i uruchom aktualizację ponownie:');
define('_SETUPTABLES','W bazie nie ma obu tabel %1$s_users i %1$s_admins, sprawdź prefiks tabel witryny.');
define('_SETUPTAKEN','Baza zawiera już tabele z prefiksem %s_, nowa instalacja wymaga pustej bazy lub innego prefiksu.');
define('_SETUPTYPE','Wybierz nową instalację lub jedną z proponowanych aktualizacji. Nic nie zostało zmienione.');
define('_SETUPVER','Serwer bazy danych %1$s jest starszy niż %2$s.');
define('_SETUP_NEW','Nowa instalacja systemu');
define('_SETUP_SLAED','Instalacja SLAED CMS');
define('_SUPDATE','Aktualizacja systemu');
define('_TABLE','The table');
