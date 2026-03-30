# Frontend-Plan

## Ziel

`modules/*/index.php` auf feste PHP-Datenschnitte bringen.
Danach Frontend-Templates reduzieren.

Reihenfolge ist verbindlich:

1. Repo-Stand pruefen
2. PHP normalisieren
3. Templates reduzieren
4. Theme-Duplizierung reduzieren

Keine neue Template-Arbeit gegen uneinheitliches PHP.

---

## Feste Regeln

### Regel 0: Neue Hilfsfunktionen zentralisieren

Pflicht:

- neue Funktionsnamen nur in `camelCase`, nur Buchstaben, 6-24 Zeichen, mit Pflicht-Praefix `get`, `set`, `add`, `update`, `delete`, `is`, `check` oder `filter`
- neue Variablennamen nur in Kleinbuchstaben, nur Buchstaben, 2-8 Zeichen, ohne `_`, ohne Ziffern, ohne `camelCase`
- neue Funktionen nur in `core/helpers.php` anlegen
- bestehende neue Hilfsfunktionen nur in `core/helpers.php` erweitern oder aendern
- Details und Ausnahmen folgen weiter den Projektregeln in `.rules/`

### Regel 1: Keine Mikro-Fragmente erweitern

Diese historischen Muster werden nicht weiter ausgebaut:

- `open.html`
- `close.html`
- `*-open.html`
- `*-close.html`
- technische Grid-Open-/Close-Fragmente

Die alten `open`/`close`-Dateien sind im aktuellen Repo-Stand bereits entfernt.
Gemeint bleibt das historische Wrapper-Muster, nicht ein neuer Dateityp.

### Regel 2: `basic` ist kein Endzustand

`basic` wird nicht erweitert.

Verboten:

- neue Flags wie `is_view`, `has_back`, `variant`
- neue modulbezogene Sondervariablen

Zielschnitte:

- `content-card`
- `content-view`

### Regel 3: Gemeinsamer Content-Kartenschnitt

Fuer Karten-/Teaser-Ansichten sind diese Schluessel verbindlich:

- `id`
- `title_href`
- `title_attr`
- `title_text`
- `category_href`
- `category_attr`
- `category_text`
- `text`
- `post_text`
- `date_text`
- `date_iso`
- `reads_text`
- `comm_href`
- `comm_text`
- `rating`
- `favorites`

Nur diese Erweiterungen sind zulaessig:

- `hits`
- `read_href`
- `read_text`
- `editor`
- `edit_href`
- `delete_href`
- `delete_text`
- `delete_ask`
- `is_moder`

### Regel 4: Gemeinsamer Listen-Schnitt

Fuer `liste()`-Ansichten sind diese Schluessel verbindlich:

- `id`
- `title_href`
- `title_attr`
- `title_text`
- `title_new`
- `category_href`
- `category_attr`
- `category_text`
- `post_text`
- `time_text`
- `time_iso`

### Regel 5: `basic-download-view` ist eigener Fachschnitt

Dieser Schnitt wird nicht an Regel 3 angepasst.

Verbindliche Schluessel:

- `id`
- `title`
- `post`
- `date`
- `date_iso`
- `reads`
- `reads_label`
- `ctitle`
- `cimg`
- `text`
- `hits`
- `rating`
- `favorites`
- `goback`
- `admin`
- `download`
- `broken`

`ctitle` und `cimg` duerfen als vorbereitete HTML-Fragmente geliefert werden.

### Regel 6: Gemeinsamer Formular-Schnitt

Fuer `form-add` sind diese Schluessel verbindlich:

- `name`
- `postname`
- `emailval`
- `titleval`
- `catselect`
- `hometext`
- `bodytext`
- `siteval`
- `site_attr`
- `fields`
- `extrafields`
- `captcha`
- `submit`

Festlegung:

- `site_attr` Standard ist `site`
- `fields` nur fuer admin-konfigurierte Zusatzfelder
- `extrafields` nur fuer modul-spezifische Zusatzzeilen

`auto_links` ist Ausnahme:

- kein User-/Poster-Konzept
- reduzierte Form-Variante ist erlaubt

### Regel 7: Raw-Slots begrenzen

Neue Raw-Slots sind verboten, wenn kein dokumentierter Grund vorliegt.

Bestehende Raw-Slots muessen spaeter entweder:

- dokumentiert
- ersetzt

werden.

### Regel 8: Theme-Duplizierung nicht verfestigen

Neue Fachfragmente werden nicht parallel in mehreren Themes vervielfaeltigt.

Master-Richtung:

- fachliche Fragments logisch zentralisieren
- Themes nur fuer Layout-/Asset-Abweichungen nutzen

---

## Repo-Stand

### Repo-Sync Stand 2026-03-30

Ist-Stand:

- Frontend ist weiter normalisiert als Admin
- die alten Theme-Fragmente `open.html` / `close.html` sind entfernt
- der alte Allzweck-Schnitt `basic` ist aus den Frontend-Content-Pfaden entfernt; uebrig bleiben nur eigene Fachschnitte wie `basic-download-view` und `basic-media-view`
- `liste-wrap` und `liste-basic` sind fuer `news`, `files`, `faq`, `links`, `pages` weitgehend angeglichen
- `media` nutzt fuer Karten-, Listen- und relevante Detailpfade `getSeoUrl()`
- `links/view()` ist innerhalb von `basic-download-view` an `files/view()` angeglichen
- `content-card` ist produktiv in `news`, `auto_links`, `pages`, `files`, `faq`, `links`, `help`, `jokes`, `media`
- `content-view` ist produktiv in `news`, `pages`, `faq`
- `form-add` ist in den Zielmodulen produktiv, `site_attr` ist dort vereinheitlicht und die auditierte Trennung `fields` / `extrafields` ist stabil
- representative GET-/Smoke-Pruefungen fuer die migrierten Frontend-Pfade laufen grün; der fruehere Warning-Hinweis in `help/view()` ist behoben
- die identischen Shared-Fragmente `content-card`, `content-view`, `content-list-basic` und `content-list-open` bleiben theme-lokal, sind aber jetzt per Test gegen Drift abgesichert
- Mikro-Fragmente und Theme-Duplizierung bestehen weiter

---

## Schnitte und Gruppen

### Gruppe A: Content-Karten

- `news`
- `auto_links`
- `files`
- `faq`
- `links`
- `media`
- `pages`
- `jokes`
- `help`

### Gruppe B: Listen

- `news`
- `files`
- `faq`
- `links`
- `pages`
- `media`

### Gruppe C: Submit-Formulare

- `faq`
- `files`
- `auto_links`
- `links`
- `help`
- `jokes`
- `pages`

### Gruppe D: Eigene Fachgruppen

- `account`
- `users`
- `forum`

### Gruppe E: Sonderfaelle

- `search`
- `sitemap`
- `recommend`
- `contact`
- `voting`
- `whois`
- `money`
- `shop`

---

## Verbindliche Datenschnitte

### Content-Karten

Standardschnitte:

- `content-card`
- `content-view`

Verbindliche Feldnamen:

- `id`
- `title_href`
- `title_attr`
- `title_text`
- `category_href`
- `category_attr`
- `category_text`
- `text`
- `post_text`
- `date_text`
- `date_iso`
- `reads_text`
- `comm_href`
- `comm_text`
- `rating`
- `favorites`

### Listen

Bestehende Schnitte:

- `liste-wrap`
- `liste-basic`

Verbindliche Feldnamen:

- `id`
- `title_href`
- `title_attr`
- `title_text`
- `title_new`
- `category_href`
- `category_attr`
- `category_text`
- `post_text`
- `time_text`
- `time_iso`

### Detailschnitt fuer Download-Module

Standardschnitt:

- `basic-download-view`

Verbindliche Feldnamen:

- `id`
- `title`
- `post`
- `date`
- `date_iso`
- `reads`
- `reads_label`
- `ctitle`
- `cimg`
- `text`
- `hits`
- `rating`
- `favorites`
- `goback`
- `admin`
- `download`
- `broken`

### Formulare

Standardschnitt:

- `form-add`

Verbindliche Feldnamen:

- `name`
- `postname`
- `emailval`
- `titleval`
- `catselect`
- `hometext`
- `bodytext`
- `siteval`
- `site_attr`
- `fields`
- `extrafields`
- `captcha`
- `submit`

---

## Status

### Batch 1: Content-Karten und Listen

Status:

- `news`   - `news()` auf `content-card`, `liste()` auf `liste-wrap` / `liste-basic`, `view()` auf `content-view`
- `auto_links` - `autolink()` auf `content-card`, `view()` bleibt Redirect-Fachpfad
- `files`  - `files()` auf `content-card`, `liste()` stabil, `view()` bleibt `basic-download-view`
- `faq`    - `faq()` auf `content-card`, `liste()` stabil, `view()` Hauptartikel auf `content-view`
- `links`  - `links()` auf `content-card`, `liste()` stabil, `view()` bleibt `basic-download-view`
- `pages`  - `pages()` auf `content-card`, `liste()` stabil, `view()` auf `content-view`
- `media`  - `media()` auf `content-card`, `liste()` stabil, `view()` bleibt `basic-media-view`
- `jokes`  - `jokes()` auf `content-card`
- `help`   - `help()` auf `content-card`, `view()` bleibt eigener Thread-Detailpfad

Rueckstaende:

- `basic` ist in den Frontend-Content-Pfaden entfernt
- `help/addview()` ist als Reply-Form jetzt auf `form-add` gezogen, bleibt aber fachlich ein eigener Pfad
- `media/view()` bleibt absichtlich auf `basic-media-view`

### Batch 2: Detailansichten

Status:

- weitgehend abgeschlossen

Stand:

- `news/view()` auf `content-view`
- `pages/view()` auf `content-view`
- `faq/view()` Hauptartikel auf `content-view`, Restblocke bleiben angehaengt
- `files/view()` bleibt `basic-download-view`
- `links/view()` bleibt `basic-download-view` und ist in den betroffenen Fachbloecken an `files/view()` angeglichen
- `help/view()` bleibt eigener Thread-/Reply-Detailpfad, nutzt aber keinen generischen `basic`-Schnitt mehr
- `media/view()` bleibt eigener Fachschnitt `basic-media-view`

Zielmodule:

- `files`
- `links`
- `media`

### Batch 3: Formulare

Status:

- weitgehend abgeschlossen

Rueckstaende:

- `help/addview()` nutzt jetzt ebenfalls `form-add`, bleibt aber als eigener Reply-Form-Pfad fachlich getrennt
- `media` nutzt weiter `media-form-add` als eigenen Fachschnitt
- `form-add` hat weiter vorhandene Roh-Sammelslots, bekommt aber keine neuen

Stand:

- `order()` nutzt `form-add` mit shared row-/submit-fragments
- `money()` nutzt `form-add` fuer den Antragsblock; die Rechnerformulare bleiben vorerst fachlich separat
- `help/addview()` nutzt `form-add` fuer den Reply-Pfad ohne neuen allgemeinen Sondermodus

Zielmodule:

- `faq`
- `files`
- `auto_links`
- `links`
- `help`
- `jokes`
- `pages`

### Batch 4: Account / Users / Forum

Status:

- audit abgeschlossen, bleibt als eigene Fachgruppe getrennt

Stand:

- `account` bleibt ein eigener Satz aus Login-, Register-, Profil-, PM- und Setup-Schnitten
- `users` bleibt ein eigener Satz aus Ranking-/Rules-/Stats-Tabellen
- `forum` bleibt ein eigener Satz aus Kategorie-, Themenlisten-, Thread- und Reply-Schnitten
- in `account/last()` und in forumeigenen self-links laufen lokale URL-Bereinigungen auf `getSeoUrl()`

Festlegung:

- keine erzwungene Migration auf `content-card`
- keine erzwungene Migration auf `content-view`
- keine erzwungene Migration auf `form-add`
- nur lokale Aufraeumarbeiten und URL-/Fragment-Hygiene innerhalb der eigenen Fachschnitte

### Batch 5: Fragment-Bestand und Themes

Status:

- teilweise abgeschlossen

Stand:

- `default`, `lite` und `simple` teilen aktuell `193` gemeinsame Fragment-Dateien; davon sind `185` byte-identisch und nur `8` bewusst theme-spezifisch
- physische Zusammenlegung wurde bewusst nicht ueber den Loader erzwungen, weil der aktuelle Runtime-Contract theme-lokal ist
- ein automatischer Test schuetzt jetzt den gesamten identischen Shared-Layer gegen weiteres Auseinanderlaufen und bewacht zugleich die `8` erlaubten Unterschiede

---

## Ausfuehrungsreihenfolge

1. Repo-Sync aktualisieren
2. Restfaelle klar als eigene Fachschnitte markieren
3. Account/Users/Forum separat normalisieren
4. Mikro-Fragmente erst nach stabiler PHP-Normalisierung entfernen
5. Theme-Duplizierung reduzieren

### Ausfuehrung pro Modul

1. Funktion lesen
2. sichtbare Hauptstruktur bestimmen
3. auf bestehenden Standardschnitt mappen
4. Daten und Escaping in PHP belassen
5. Markup ins Zieltemplate ziehen
6. Mikro-Fragmente am migrierten Pfad nicht weiter benutzen
7. Restreferenzen auf alte Struktur entfernen
8. Batch verifizieren

Stop-Bedingung:

- mehr als 2 Raw-Slots fuer die Zielstruktur
- neuer Modus-Flag waere noetig
- Standardschnitt passt fachlich nicht
- Escaping-Kontext wird unklar

Dann:

- Migration stoppen
- als Sonderfall markieren
- nicht in ein Allzweck-Template pressen

---

## Erfolg

### Batch 1: Content-Karten und Listen

- `media` nutzt `getSeoUrl()` fuer `thref` und `chref`
- `links/view()` ist in den betroffenen Fachbloecken an `files/view()` angeglichen
- `basic` bekommt keine neuen Variablen und keine neuen Flags
- alle migrierten `liste()`-Nutzer liefern den festen Listenschnitt

### Batch 2: Detailansichten

- `basic-download-view` ist fuer die migrierten Ziele der feste Fachschnitt
- keine entsprechenden Raw-HTML-Reste mehr in `links/view()`

### Batch 3: Formulare

- `site_attr` ist in den Zielmodulen vereinheitlicht und `files/add()` nutzt jetzt ebenfalls `site`
- `fields` und `extrafields` sind fachlich sauber getrennt
- `form-add` bekommt keine neuen dauerhaften Roh-Sammelslots

### Batch 4: Account / Users / Forum

- diese Gruppe bleibt fachlich getrennt von Gruppe A bis C
- keine erzwungene Verschmelzung mit allgemeinen Content-Schnitten
- neue Fragmente folgen denselben Raw-Slot- und Flag-Regeln

### Batch 5: Fragment-Bestand und Themes

- Mikro-Fragmente werden nicht mehr erweitert
- Loeschungen passieren erst nach stabiler PHP-Normalisierung
- Theme-Duplizierung wird nicht weiter verfestigt
- der identische Shared-Layer ueber `default`, `lite` und `simple` bleibt bis zu einer expliziten Loader-Strategie synchron und testgesichert

### Gesamt

1. neue Modul-Implementierungen nutzen die festen Schnitte direkt
2. keine neuen Mikro-Fragmente oder neuen `basic`-Varianten entstanden
3. die Batch-Verifikation ist vollstaendig durchlaufen
4. der Plan wurde danach auf den neuen Repo-Stand aktualisiert

---

## Hilfsabhaengigkeiten

Behalten:

- `getSeoUrl()`
- `ajax_rating()`
- `navigation()`, Pager- und Letter-Helfer
- Formular-Helfer fuer Captcha und Zusatzfelder

Regel:

- bestehende Helfer duerfen bleiben, wenn sie stabile Fachbloecke oder Daten liefern
- neue HTML-Helfer nur als Uebergang sind verboten
- Helper, die nur Mikro-Fragmente zusammensetzen, werden nicht erweitert

---

## Abgleich mit Admin-Plan

Gemeinsame Prinzipien:

- erst PHP normalisieren, dann Templates reduzieren
- `open`/`close` und vergleichbare Mikro-Muster werden nicht weiter ausgebaut
- Raw-Slot-Dokumentation ist Pflicht
- kein neuer Template-Schnitt mit Flag-Monster

Bewusste Unterschiede:

- Frontend behaelt `form-add` als Submit-Basis
- Admin behaelt `form-conf` als Config-Basis
- Frontend ist content-getrieben
- Admin ist status-/actions-getrieben

---

## Risiko-Management

Hauptrisiken:

- `basic` bleibt Allzweck-Template statt Uebergang
- `fields` und `extrafields` wachsen weiter unscharf zusammen
- Theme-Duplizierung wird bei neuen Fragmenten fortgeschrieben
- Account/Forum werden in zu allgemeine Schnitte gepresst

Gegenmassnahmen:

- keine neuen Variablen oder Flags in `basic`
- feste Trennung von Basis-Schnitt und Fach-Erweiterung
- neue Fachfragmente nicht parallel in mehreren Themes vervielfaeltigen
- eigene Fachgruppen getrennt behandeln

Fruehwarnzeichen:

- neuer Frontend-Schnitt braucht mehrere Modus-Flags
- neues Modul fuehrt wieder rohe `index.php?...`-URLs ein
- neue Raw-Slots ohne dokumentierten Grund
- neue Mikro-Fragmente oder neue `*-open`/`*-close`-Paare

---

## Test-Strategie

Pflicht pro migriertem Modul:

- Smoke-Test: Seite laedt ohne PHP-Warning
- Render-Test: neues oder angepasstes Template rendert ohne offene Platzhalter
- Klick-/Submit-Test fuer betroffene Nutzerpfade

Pflicht pro Batch:

- Batch 1: Kartenansicht rendern, Links und Meta pruefen
- Batch 2: Detailansicht rendern, Link-/Button-Pfade pruefen
- Batch 3: Formular oeffnen, absenden, Ruecklauf pruefen
- Batch 4: Fachpfade fuer Account/Users/Forum separat pruefen

Verifikation:

- `php -l` auf migrierte Dateien
- Referenzsuche auf entfernte Fragmente und alte Pfade
- betroffene Frontend-Seite im Browser oeffnen
- relevanten Link, Button oder Submit-Pfad einmal durchlaufen
- bei schreibenden Flows `storage/logs/error_php.log`, `storage/logs/error_sql.log`, `storage/logs/error_site.log` pruefen

Minimalziel:

- 100% Smoke-Tests fuer migrierte Module
- jede migrierte Phase hat mindestens einen erfolgreich geprueften Kernpfad
- keine Phase gilt ohne manuelle Sichtpruefung als abgeschlossen

---

## Zielbild

Wenige feste PHP-Datenschnitte.
Wenige fachliche Templates.
Keine neuen Mikro-Fragmente.
Kein weiterer Ausbau von `basic` als Allzweck-Template.
