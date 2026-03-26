# Admin-Plan

## Ziel

`admin/modules/*.php` und `modules/*/admin/index.php` auf feste PHP-Datenschnitte bringen.
Danach Admin-Fragmente reduzieren.

Reihenfolge ist verbindlich:

1. Repo-Stand pruefen
2. PHP normalisieren
3. Templates reduzieren
4. Sonderfaelle separat behandeln

Keine neue Template-Arbeit gegen uneinheitliches PHP.

---

## Feste Regeln

### Regel 0: Neue Hilfsfunktionen zentralisieren

- neue Funktionsnamen nur in `camelCase`, nur Buchstaben, 6-24 Zeichen, mit Pflicht-Praefix `get`, `set`, `add`, `update`, `delete`, `is`, `check` oder `filter`
- neue Variablennamen nur in Kleinbuchstaben, nur Buchstaben, 2-8 Zeichen, ohne `_`, ohne Ziffern, ohne `camelCase`
- neue Funktionen nur in `core/helpers.php` anlegen
- bestehende neue Hilfsfunktionen nur in `core/helpers.php` erweitern oder aendern
- Details und Ausnahmen bleiben in `.rules/` verbindlich

### Regel 1: Erst PHP, dann Template

- gleiche Datenstruktur zuerst
- gleiche Template-Variable danach
- kein Template als Ausrede fuer uneinheitliches PHP

### Regel 2: `open` und `close` sind deprecated

`open` und `close` sind Uebergangstechnik.

Verboten:

- neue Aufrufer
- neue Open-/Close-Varianten

Pflicht:

- bestehende Aufrufer bei jeder betroffenen Migration abbauen

### Regel 3: Fragment nur bei stabilem Mehrfachschnitt

Ein neues gemeinsames Fragment wird nur angelegt, wenn:

- der Block in mindestens zwei Modulen identisch vorkommt
- der Schnitt stabil ist

Kein Fragment auf Verdacht.

### Regel 4: Kein Flag-Monster

Wenn ein Schnitt nur ueber viele Schalter wie

- `mode`
- `variant`
- `show_sort`
- `show_mail`
- `show_status`

funktioniert, ist der Schnitt falsch.

Ist-Stand:

- `form-conf` arbeitet noch mit Modul-Flags wie `comments`, `favorites`, `privat`

Pflicht:

- keine neuen Modul-Flags in gemeinsamen Templates
- bestehende Modul-Flags schrittweise durch feste Feldschnitte oder fachliche Unterfragmente abbauen

### Regel 5: Raw-Slots dokumentieren

Diese Admin-Raw-Slots sind erlaubt, aber dokumentationspflichtig.
Massgeblich ist der reale Registry-Stand in `docs/RAW_SLOTS_ADMIN.md`.

Aktuell produktiv verdrahtete Shared-Slots:

- `head_html`
- `rows_html`
- `cells_html`
- `hidden_html`
- `form_attr`
- `label_html`
- `field_html`
- `content_html`

Zusatz-Slots in bestehenden aelteren Strukturen:

- `status_html`
- `actions_html`
- `rows_html`
- `fields`
- `searchbox_html`
- `token`
- `category_select`
- `body_editor`
- `extra_fields`

Fuer jeden Raw-Slot gilt:

- Herkunft muss im PHP-Aufrufer klar sein
- Escaping passiert vor dem Template
- neue Raw-Slots nur mit bewusster Begruendung
- jeder Raw-Slot steht in `docs/RAW_SLOTS_ADMIN.md`
- maximal 5 Raw-Slots pro neuem Admin-Template

### Regel 6: Sonderlogik bleibt in PHP

In PHP bleiben:

- Datenlesen
- Rechtepruefung
- Verzweigungen
- Schleifenaufbereitung
- Escaping-Entscheidungen

---

## Repo-Stand

### Repo-Sync Stand 2026-03-26

Ist-Stand Template-Verzeichnis:

- unter `templates/admin/fragments/` existieren **63** Fragmente
- darunter weiter technische Fragmente wie `alert.html`, `form-conf.html`, dazu produktive Shared-Cuts wie `admin-box`, `admin-form`, `admin-table`
- unter `templates/admin/partials/` existieren **9** Dateien:
- `admin-config-base.html`
- `admin-config-communication.html`
- `blocks/add.html`
- `blocks/edit.html`
- `changelog.html`
- `login.html`
- `preview.html`
- `registration.html`
- `searchbox.html`

Ist-Stand Modulmigration:

- produktiv verwendet wird vor allem `searchbox`
- `admin-config-base` und `admin-config-communication` sind produktiv als `form-conf`-Teilbloecke verdrahtet
- `blocks/add.html` und `blocks/edit.html` sind vorbereitet, aber nicht verdrahtet
- ausser `searchbox` gibt es keine bestaetigte produktive Nutzung fachlicher Admin-Partials fuer Modul-Hauptstrukturen
- Config-Module nutzen weiter `form-conf`
- produktive PHP-Aufrufer von `open` und `close` sind entfernt; der Rest ist jetzt Shared-Helper-Layer plus fachliche Sonderlayouts

---

## Schnitte

### Behalten

- `form-conf`
- `searchbox`
- `alert`

### Deprecated

- `open`
- `close`

Die frueheren Fragment-Dateien `open.html` und `close.html` sind entfernt.
Deprecated bleibt das historische Wrapper-Muster, nicht ein aktiver Dateibestand.

### Eingefuehrt als `form-conf`-Includes

- `admin-config-base`
- `admin-config-communication`

Sie sind aktuell keine eigenstaendigen Standard-Einstiege.
Sie gelten als interne Teilblaecke von `form-conf`.

### Bestaetigte Standardschnitte

- `admin-table`
- `admin-table-row`
- `admin-form`
- `admin-form-row`
- `admin-form-wide`
- `admin-box`

### Verbindliche Felder

`admin-config-base`:

- `num`
- `anum`
- `nump`
- `anump`

`admin-config-communication`:

- `letter`
- `send`
- `r_profil`
- `r_web`

---

## Verbindliche Datenschnitte

### Config-Schnitt

Basis-Felder:

- `num`
- `anum`
- `nump`
- `anump`
- `letter`
- `send`
- `status`
- `addmail`
- `r_profil`
- `r_web`
- `r_mail`
- `acomm`
- `ahome`

Slot-Namen:

- `fields`
- `searchbox_html`
- reale Shared-Raw-Slots siehe `docs/RAW_SLOTS_ADMIN.md`

### Listen-Schnitt

Standard-Templates:

- `admin-table`
- `admin-table-row`

Basis-Felder:

- `id`
- `title`
- `title_href`
- `status`
- `status_text`
- `date`
- `user`
- `actions_html`
- `is_checked`
- `class`

Optionale Felder:

- `category`
- `category_href`
- `reads`
- `comments`
- `rating`

Slot-Namen:

- `rows_html`
- `status_html`
- `actions_html`
- reale Shared-Raw-Slots siehe `docs/RAW_SLOTS_ADMIN.md`

### Formular-Schnitt

Standard-Template:

- `admin-form`

Basis-Felder:

- `title`
- `date`
- `status`
- `cid`
- `uid`
- `intro`
- `body`
- `acomm`
- `ihome`

Raw-Slots:

- `token`
- `category_select`
- `body_editor`
- `extra_fields`
- reale Shared-Raw-Slots siehe `docs/RAW_SLOTS_ADMIN.md`

Stop-Bedingung:

- wenn ein Formular mehr als 15 Basis-Felder braucht, ist Architektur-Review Pflicht

---

## Status

### Batch 1: Config

Status:

- code-seitig abgeschlossen auf Wrapper-Ebene
- `form-conf`- und Config-Faelle wurden repo-weit auf `admin-box` oder bestehende stabile Config-Struktur gezogen
- produktive `open`/`close`-Aufrufer in `admin/modules/*.php`, `modules/*/admin/index.php`, `core/admin.php` und `core/system.php` sind entfernt
- gemischte Config- und Tab-Screens bleiben fachlich gemischt, aber nicht mehr auf `open`/`close`
- Batch-Status fachlich noch in Verifikation, weil nicht alle migrierten Save-Pfade manuell Kernpfad-geprueft wurden

Bestaetigte Config-/Box-Faelle:

- `admin/modules/comments.php`
- `admin/modules/favorites.php`
- `admin/modules/privat.php`
- `admin/modules/referers.php`
- `admin/modules/newsletter.php`
- `admin/modules/ratings.php`
- `admin/modules/statistic.php`
- `admin/modules/lang.php`
- `admin/modules/security.php`
- `admin/modules/config.php`
- `admin/modules/uploads.php`
- `admin/modules/database.php`
- `modules/news/admin/index.php`
- `modules/order/admin/index.php`
- `modules/rss/admin/index.php`
- `modules/whois/admin/index.php`
- `modules/faq/admin/index.php`
- `modules/pages/admin/index.php`
- `modules/search/admin/index.php`
- `modules/shop/admin/index.php`

### Batch 2: Listen

Status:

- code-seitig abgeschlossen fuer stabile Listen-Schnitte
- `admin-table` und `admin-table-row` sind bestaetigte Mehrfachschnitte
- gemischte Listen mit eigenem Bulk-/Tab-/Audit-Layout wurden nur wrapper-seitig bereinigt und nicht in einen falschen Standardschnitt gepresst
- Batch-Status fachlich noch in Verifikation, weil nicht alle migrierten Listen-Pfade manuell kernpfadgeprueft wurden

Zielmodule:

- `admins`
- `modules`
- `referers`
- `modules/news/admin/index.php`
- `modules/files/admin/index.php`
- `modules/account/admin/index.php`
- `modules/forum/admin/index.php`

Scope:

- nur Module mit strukturierter Listenansicht
- Module ohne echte Listenansicht sind aus Batch 2 ausgenommen
- bestaetigte Listen-Faelle wurden ausserdem erfolgreich auf weitere Module ausgerollt:
- `groups`, `messages`, `newsletter`, `lang`, `scheduler`, `security`
- `news`, `files`, `account`, `forum`
- `faq`, `help`, `content`, `pages`, `links`, `clients`, `order`, `money`, `voting`, `whois`, `jokes`, `media`, `auto_links`

### Batch 3: Formulare

Status:

- code-seitig abgeschlossen fuer stabile Add/Edit-Formulare
- `admin-form`, `admin-form-row`, `admin-form-wide` sind bestaetigte Mehrfachschnitte
- verbleibende gemischte Editor-Screens bleiben bewusst lokal oder special und wurden nicht in einen falschen Standardschnitt gepresst
- Batch-Status fachlich noch in Verifikation, weil kein vollstaendiger manueller Submit-Durchlauf fuer alle migrierten Form-Pfade dokumentiert ist

Zielmodule:

- `admins`
- `messages`
- `groups`
- `categories`
- `modules/news/admin/index.php`
- `modules/files/admin/index.php`
- `modules/account/admin/index.php`

Bestaetigte Form-Faelle:

- `admin/modules/admins.php`
- `admin/modules/groups.php`
- `admin/modules/messages.php`
- `admin/modules/newsletter.php`
- `admin/modules/comments.php`
- `admin/modules/modules.php`
- `admin/modules/scheduler.php`
- `admin/modules/security.php` (`passwd()`)
- `modules/news/admin/index.php`
- `modules/content/admin/index.php`
- `modules/order/admin/index.php`
- `modules/money/admin/index.php`
- `modules/voting/admin/index.php`
- `modules/files/admin/index.php`
- `modules/media/admin/index.php`
- `modules/contact/admin/index.php`
- `modules/clients/admin/index.php`
- `modules/faq/admin/index.php`
- `modules/pages/admin/index.php`
- `modules/help/admin/index.php`
- `modules/search/admin/index.php`

### Batch 4: Sonderfaelle

Status:

- weiter offen als fachliche Restklasse
- Sonderfaelle sind jetzt echte Fachsonderfaelle und nicht mehr `open`/`close`-Restmuell
- Wrapper-Cleanup fuer diese Klasse ist erledigt

Sonderfaelle:

- `blocks`
- `config`
- `database`
- `editor`
- `fields`
- `uploads`
- `security`
- `scheduler`
- `template`
- `replace`
- `monitor`
- `lang/fileedit()`
- `categories` innere Tab-/Multi-Form-Struktur
- `account/add()` als schwerer Mixed-Editor
- `shop` innere Shop-Layouts

Repo-Stand 2026-03-26:

- `open`/`close` sind im aktiven PHP-Admin-Layer entfernt
- `admin-box`, `admin-table`, `admin-table-row`, `admin-form`, `admin-form-row`, `admin-form-wide` sind produktiv verdrahtet
- representative GET-/Smoke-Pruefungen fuer migrierte Admin-Routen laufen grün; fuer `comments`, `newsletter`, `files`, `search` und `shop` wurden no-op Config-Saves ohne Dateiaenderung und ohne neue Logeintraege bestaetigt; `groups/save` und `modules/edit(news)` liefen als representative gekoppelte Edit-Submits mit grünem Redirect und ohne neue Logeintraege; verbleibende schwere Mehrfach-Editoren wie `categories/edit()`, `lang/fileedit()` und `account/add()` werden wegen Datenrisiko und hoher Formkomplexitaet als browser-only Kernpfade behandelt, laden nach den aktuellen Runtime-Fixes aber ohne neue PHP-Logeintraege
- Restarbeit ist keine Wrapper-Bereinigung mehr, sondern manuelle Kernpfad-Verifikation, fachliche Sonderfaelle und spaetere Template-Reduktion

Neubewertung nur wenn:

- Batch 1 bis 3 stabile gemeinsame Schnitte haben
- der Sonderfall denselben Datenschnitt erkennbar teilen kann
- dabei kein neues Flag-Monster entsteht

---

## Ausfuehrungsreihenfolge

1. Repo-Sync aktualisieren
2. Config-Module auf feste Feldnamen bringen
3. `open`/`close` bei betroffenen Config-Modulen abbauen
4. gemeinsamen Listen-Schnitt einfuehren
5. `admin-table` / `admin-table-row` als Standardschnitt bauen
6. Formular-Basisfelder vereinheitlichen
7. `admin-form` als Standardschnitt bauen
8. Sonderfaelle einzeln neu bewerten

### Ausfuehrung pro Modul

1. Funktion lesen
2. sichtbare Hauptstruktur bestimmen
3. auf bestehenden Standardschnitt mappen
4. Daten und Escaping in PHP belassen
5. Markup ins Zieltemplate ziehen
6. `open`/`close` am migrierten Pfad abbauen
7. Restreferenzen auf alte Struktur entfernen
8. Batch verifizieren

Stop-Bedingung:

- mehr als 2 Raw-Slots fuer die Zielstruktur
- neuer Modul-Flag waere noetig
- Standardschnitt passt fachlich nicht
- Escaping-Kontext wird unklar

Dann:

- Migration stoppen
- als Sonderfall markieren
- nicht in ein Allzweck-Template pressen

---

## Erfolg

### Fuer Config

- gleiche Feldnamen in allen migrierten Config-Modulen
- `form-conf` ohne neue Modul-Flags fuer dieselbe Bedeutung
- `admin-config-base` und `admin-config-communication` nur fuer stabile Mehrfachschnitte

### Fuer Listen

- gemeinsamer Datenschnitt ist eingefuehrt
- `rows_html`, `actions_html`, `status_html` sind einheitlich belegt
- migrierte Listen bauen keine unstrukturierten Freiform-Zeilen mehr
- `open`/`close` in migrierten Listen entfallen

### Fuer Formulare

- gemeinsame Standardfelder haben identische Namen
- `admin-form` ist Standardschnitt fuer neue Add/Edit-Formulare
- Sonderfelder sind als benannte Fachbloecke getrennt

### Gesamt

Ein Batch gilt erst als abgeschlossen, wenn:

1. alle Zielmodule des Abschnitts migriert sind oder bewusst als Sonderfall markiert wurden
2. keine alten HTML-Hauptstrukturen mehr als grosse String-Bloecke im migrierten Zielpfad liegen
3. keine neuen `open`/`close`-Aufrufer entstanden sind
4. Raw-Slots der migrierten Templates dokumentiert sind
5. Render- und Submit-Pfade der migrierten Module funktionieren
6. der Plan danach auf den neuen Repo-Stand aktualisiert wurde

---

## Hilfsabhaengigkeiten

Behalten:

- `checkPerms()`
- `setAdminNavi()`
- `catselect()` bzw. bestehende Kategorien-Helfer
- `radio_form()`
- `textarea()`
- Editor-Helfer

Regel:

- HTML-Helfer duerfen bleiben, wenn sie stabile Fachblöcke liefern
- neue HTML-Helfer nur als Uebergang sind verboten

---

## Abgleich mit Frontend-Plan

Gemeinsame Prinzipien:

- Raw-Slot-Dokumentation ist in Admin und Frontend Pflicht
- `open`/`close` sind in Admin und Frontend deprecated
- kein neuer Template-Schnitt mit Flag-Monster
- erst PHP normalisieren, dann Templates reduzieren

Bewusste Unterschiede:

- Admin behaelt `form-conf` als Config-Basis
- Frontend behaelt `form-add` als Submit-Basis
- Admin-Listen sind status-/actions-getrieben
- Frontend-Listen sind content-/navigation-getrieben

---

## Risiko-Management

Hauptrisiken:

- Config-Felder werden zu modul-spezifisch
- Listen-Schnitt deckt Sonderfaelle nicht sauber ab
- `extra_fields` wird Dauerzustand statt Uebergang
- `open`/`close` bleiben aus Bequemlichkeit produktiv

Gegenmassnahmen:

- Basisfelder strikt von Erweiterungen trennen
- Sonderfaelle explizit ausserhalb des Standardschnitts halten
- jede neue Abweichung nur mit begruendeter Dokumentation
- pro Migration bestehende `open`/`close`-Aufrufer abbauen

Fruehwarnzeichen:

- mehr als 15 Basis-Felder im Formular
- neuer Listen-Schnitt braucht mehrere Modus-Flags
- neue Raw-Slots ohne Eintrag in `RAW_SLOTS_ADMIN.md`
- neue Admin-PR fuehrt `open`/`close` erneut ein

---

## Test-Strategie

Pflicht pro migriertem Modul:

- Smoke-Test: Seite laedt ohne PHP-Warning
- Render-Test: neues Template rendert ohne offene Platzhalter
- Submit-Test bei schreibenden Formularen

Pflicht pro Batch:

- Batch 1: alle migrierten Config-Formulare laden und speichern
- Batch 2: alle migrierten Listen rendern, Status und Actions pruefen
- Batch 3: alle migrierten Add/Edit-Formulare laden und speichern

Verifikation:

- `php -l` auf migrierte Dateien
- Referenzsuche auf entfernte Fragmente und alte Pfade
- betroffene Admin-Route im Browser oeffnen
- Phase 1: speichern und Redirect pruefen
- Phase 2: Status-/Action-Spalten pruefen
- Phase 3: Formular oeffnen, speichern, Redirect pruefen
- bei schreibenden Flows `storage/logs/error_php.log`, `storage/logs/error_sql.log`, `storage/logs/error_site.log` pruefen

Minimalziel:

- 100% Smoke-Tests fuer migrierte Module
- 100% Submit-Tests fuer migrierte Config-Formulare
- keine Phase gilt ohne erfolgreiche manuelle Kernpfad-Pruefung als abgeschlossen

---

## Zielbild

Wenige feste PHP-Datenschnitte.
Wenige fachliche Admin-Templates.
Keine neuen `open`/`close`-Aufrufer.
