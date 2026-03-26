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

### Regel 1: Keine Mikro-Fragmente erweitern

Diese Muster werden nicht weiter ausgebaut:

- `open.html`
- `close.html`
- `*-open.html`
- `*-close.html`
- technische Grid-Open-/Close-Fragmente

Sie werden spaeter entfernt, sobald der umgebende PHP-Schnitt normalisiert ist.

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

### Repo-Sync Stand 2026-03-26

Ist-Stand:

- Frontend ist weiter normalisiert als Admin
- `basic` wird in mehreren Content-Modulen weiter als Allzweck-Schnitt genutzt
- `liste-wrap` und `liste-basic` sind fuer `news`, `files`, `faq`, `links`, `pages` weitgehend angeglichen
- `media` weicht noch bei URL-Aufbau von `getSeoUrl()` ab
- `links/view()` ist noch nicht an `files/view()` angeglichen
- `form-add` wird in mehreren Modulen produktiv genutzt, aber `fields` und `extrafields` sind noch nicht sauber getrennt
- Mikro-Fragmente und Theme-Duplizierung bestehen weiter

---

## Schnitte und Gruppen

### Gruppe A: Content-Karten

- `news`
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

- `news`   - Karten/Listen weitgehend normalisiert
- `files`  - Karten/Listen weitgehend normalisiert
- `faq`    - Karten/Listen weitgehend normalisiert
- `links`  - Listen weitgehend normalisiert | `view()` offen
- `pages`  - Listen weitgehend normalisiert
- `media`  - offen
- `jokes`  - Karten separat pruefen
- `help`   - Karten separat pruefen

Rueckstaende:

- `media` nutzt teils rohe Frontend-URLs statt `getSeoUrl()`
- `basic` ist noch Allzweck-Template
- `links/view()` ist noch nicht an `files/view()` angeglichen

### Batch 2: Detailansichten

Status:

- offen

Zielmodule:

- `files`
- `links`
- `media`

### Batch 3: Formulare

Status:

- offen

Rueckstaende:

- `site_attr` noch nicht ueberall auf `site`
- `fields` und `extrafields` noch nicht sauber getrennt
- `form-add` noch mit dauerhaften Roh-Sammelslots

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

- offen

### Batch 5: Fragment-Bestand und Themes

Status:

- offen

---

## Ausfuehrungsreihenfolge

1. Repo-Sync aktualisieren
2. `media` bereinigen
3. `links/view()` an `files/view()` angleichen
4. `site_attr` vereinheitlichen
5. `form-add`-Nutzer auf festen Schnitt bringen
6. `basic` aufspalten
7. Account/Users/Forum separat normalisieren
8. Mikro-Fragmente entfernen
9. Theme-Duplizierung reduzieren

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

- `site_attr` ist ueberall `site`, ausser dokumentierte Ausnahme
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
