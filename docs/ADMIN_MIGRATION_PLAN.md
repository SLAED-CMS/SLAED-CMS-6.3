# Admin-Template-Migration: Master-Plan

## Ziel

Kein Helper-Funktions-ZOO. Kein Template-ZOO.
Stabile mittlere Schicht aus fachlichen Admin-Templates mit klaren PHP-Template-Grenzen.

Dieses Dokument ist ein Ausfuehrungsplan, kein reines Mengenziel.
Strukturqualitaet ist wichtiger als maximale Dateireduktion.

---

## Zielprinzip

### PHP ist zustaendig fuer

- Daten lesen und vorbereiten
- Bedingungen und Ablaufsteuerung
- Flags fuer Templates
- Auswahl des passenden Templates
- bewusst gelieferte Raw-Slots (`{{{ }}}`) nur wenn fachlich noetig

### Templates sind zustaendig fuer

- sichtbares Markup
- Wrapping und Struktur
- Tabellen, Listen, Panels, Karten
- wiederkehrende fachliche UI-Bloecke

### Nicht gewuenscht

- PHP-Helfer die hauptsaechlich HTML-Strings zusammenbauen
- atomare Templates wie `open.html`, `close.html`, `span-x.html`
- generische Allzweck-Templates mit gemischten Escaping-Kontexten
- neue Daten- oder Wrapper-Helfer nur als Uebergangsschicht ohne echten Architekturgewinn

---

## Verbindliche Grundregeln

**1. Kein neues HTML-in-PHP als Standard**
Wenn eine Funktion sichtbares HTML mit mehr als einem trivialen Literal rendert, ist das ein Template-Kandidat.

**2. Kein Mikro-Template-ZOO**
Keine Datei fuer ein einzelnes `<span>`, `<a>`, oeffnende oder schliessende Tags oder minimale Varianten ohne fachlichen Mehrwert.

**3. Fachlicher Schnitt statt technischer Schnitt**
Gut: `session-row`, `comment`, `rating-bar`, `editor-upload-panel`
Schlecht: `row-open`, `red-text`, `link-with-icon`

**4. Escaping bewusst pro Variable**
- `{{ var }}` fuer escaped Text oder Attributwert
- `{{{ var }}}` nur fuer bewusstes Raw-HTML
- Kontext immer benennen: HTML-Text / Attribut / URL / Inline-JavaScript
- nie Escaping angleichen nur um einen Merge zu vereinfachen

**5. Template-Logik klein halten**
`{% if %}` kompiliert zu `!empty()`.
Keine Wertvergleiche, keine komplexen Modus-Switches.
Varianten nur ueber klare Flags oder getrennte fachliche Templates.

**6. Direkte Renames vor Wrappern**
Wenn eine Legacy-Helfer-Aenderung nur ein Namenswechsel ohne Verhaltensaenderung ist, bestehende Funktion direkt umbenennen und Aufrufer migrieren.
Keine parallelen Alt/Neu-Wrapper ohne klaren Mehrwert.

**7. Root-Cause statt Call-Site-Flickwerk**
Wenn derselbe HTML- oder Helper-Schnitt in mehreren Modulen auftritt, an der Quelle loesen und nicht in jeder Funktion neu.

---

## Plan-Stand gegen Repo

Vor jeder Phase muss der Plan gegen den aktuellen Repository-Stand synchronisiert werden.

Pflicht:

- Existenz aller betroffenen Fragmente und Zieltemplates pruefen
- bereits entfernte oder schon zusammengefuehrte Fragmente aus dem Batch streichen
- bereits rueckmigrierte Bereiche nicht erneut planen
- Batch-Dokumentation nach Abschluss aktualisieren

Konsequenz:

- Der Plan arbeitet immer gegen den Ist-Stand
- keine Arbeit gegen Phantom-Fragmente oder alte Zwischenstaende

---

## Entscheidungsmodell pro Baustein

Vor jeder Aenderung diese Fragen stellen:

1. Ist das ein sichtbarer fachlicher UI-Baustein
2. Ist das als Template lesbarer als als PHP-String
3. Hat eine eigene Datei echten fachlichen Mehrwert
4. Bleibt die Escaping-Semantik dabei klar

Ergebnis:

- `ja / ja / ja / ja` -> als Template behalten oder dorthin zurueckfuehren
- `nein / nein / nein / ja` -> kein eigenes Template
- `unklar` -> nicht zusammenziehen, separat pruefen

### A-B-C-D Klassifikation

| Klasse | Kriterium | Massnahme |
|---|---|---|
| **A** | sichtbare UI-Struktur, mehrere Ebenen, wiederverwendet | als Template fuehren |
| **B** | rein technischer Mini-Rueckgabewert ohne fachliche Darstellung | als Literal belassen |
| **C** | als Template zu klein und atomar | nicht als Mikro-Template anlegen |
| **D** | gemischte Escaping-Semantik oder HTML plus JS in einem String | separat pruefen |

---

## Entscheidungsmodell fuer Helper

Nicht jeder alte HTML-Helfer braucht automatisch einen neuen `get*`-Datenhelfer.

Fuer jeden Helfer zuerst genau eine dieser Entscheidungen treffen:

### Typ 1: Beibehalten

Der bestehende Helper bleibt, wenn er:

- fachlich klein ist
- keine problematische Markup-Menge erzeugt
- keinen zweiten Ersatz-Helper rechtfertigt

Beispiele:

- `title_tip()`
- `mailto()`
- `ad_status()`
- `add_menu()`
- `get_user_search()`

### Typ 2: Direkter Rename

Nur wenn wirklich ein Projektstandard erzwungen werden soll und das Verhalten gleich bleibt.
Dann keine Alt/Neu-Doppelpflege.

### Typ 3: Echter Daten-Helper

Nur wenn:

- mehrere Module denselben komplexen Datensatz fuer ein Template brauchen
- der neue Schnitt deutlich sauberer ist
- damit mehrere HTML-String-Aufbauten entfallen

### Typ 4: Nicht migrieren

Wenn der Umbau nur neuen Glue-Code erzeugt, ohne die Template-Grenze messbar zu verbessern.

---

## Spur 1: Fragment- und Template-Konsolidierung

Diese Spur arbeitet nur noch Template gegen Template.
Nicht mehr: Fragment gegen PHP-Inline.

### Pflichtpruefung vor jedem Merge: Escaping-Matrix

```text
Datei A -> Datei B -> Zieldatei
Variable  | Datei A     | Datei B     | Kontext           | Entscheidung
----------|-------------|-------------|-------------------|-------------
href      | {{{ }}} raw | {{ }} esc   | URL in href-Attr  | NICHT mergen
label     | {{ }} esc   | {{ }} esc   | HTML-Text         | mergen ok
query     | {{{ }}} raw | {{{ }}} raw | Inline-JavaScript | mergen ok
```

Wenn ein Merge die Escaping-Semantik aendert: abbrechen.

### Batch-Regel

Vor jedem Fragment-Batch:

1. aktuelle Existenz pruefen
2. Nutzungen pruefen
3. Escaping-Matrix schreiben
4. nur gleichartige Templates zusammenfuehren

### Konsolidierungsregel

Erlaubt:

- `row` plus `table` innerhalb derselben Fachgruppe
- Varianten mit denselben Kontexten
- fachliche Zusammenfuehrung in ein groesseres Zieltemplate

Nicht erlaubt:

- Zusammenfuehrung ueber unterschiedliche URL- und JS-Kontexte
- Zusammenfuehrung nur ueber kuenstliche `mode`-Parameter
- Rueckverlagerung groesserer Strukturen nach PHP

### Umgang mit frueheren Mikro-Fragmenten

Wenn ein Mikro-Fragment fachlich ueberfluessig ist, gibt es nur zwei erlaubte Ziele:

1. in ein groesseres fachliches Template integrieren
2. als triviales Literal belassen, wenn wirklich kein fachlicher Mehrwert entsteht

Nicht mehr als Standard:

- neue PHP-Helfer nur als Ersatz fuer alte Mikro-Fragmente

### Konkrete Fachgruppen fuer Spur 1

Die eigentliche Abarbeitung von Spur 1 beginnt erst nach den Modul-Batches aus Spur 2.
Damit die spaetere Konsolidierung trotzdem schon planbar ist, werden die verbleibenden Fragmente vorab in Fachgruppen einsortiert.

### Phase 1: Actions

Ziel:

- verbleibende Aktionsfragmente auf gleiche Escaping- und Interaktionsmuster pruefen
- nur gleichartige Link- oder Ajax-Varianten zusammenfuehren

Typische Kandidaten:

- `comment-action-link`
- `comment-action-ajax`
- `action-menu`
- `action-delete`

Regel:

- Link-, Ajax- und Delete-Kontexte nicht kuenstlich in ein Allzweck-Template pressen

### Phase 2: Navigation und Pager

Ziel:

- fachlich zusammenhaengende Navigationsbausteine zusammenziehen
- keine Vermischung von URL- und JS-Pager-Varianten

Typische Kandidaten:

- `pager-link`
- `pagenum`
- `list-bottom`
- `navi-tabs-wrap`

Regel:

- `pagenum` und `list-bottom` sind Strukturbausteine, keine simplen Pager-Items

### Phase 3: Files und Preview

Ziel:

- File- und Preview-Bausteine innerhalb derselben Fachgruppe stabilisieren

Typische Kandidaten:

- `editor-file-preview`
- `admin-files-row`
- `admin-files-table`

Regel:

- Tabellenstruktur und Row-Struktur nur dann weiter zusammenziehen, wenn Spaltenmodell und Raw-Slots gleich bleiben

### Phase 4: Kategorien, Listen, Tabellen

Ziel:

- gleichartige Admin-Listen und Tabellenfragmente innerhalb derselben Fachgruppe angleichen

Typische Kandidaten:

- `admin-category-row`
- `admin-category-table`
- `admin-block-row`
- `admin-block-table`
- `admin-favorites-row`
- `admin-favorites-table`
- `admin-private-row`
- `admin-private-table`
- `category-row`
- `category-select`
- `category-title`
- `category-icon`

Regel:

- fachliche Tabellen duerfen gleich gebaut sein, muessen aber nicht zwangsweise in ein einziges Meta-Template verschmolzen werden

### Phase 5: Layout, Panels und Restgruppen

Ziel:

- stabile Shell- und Layoutbausteine nur noch gezielt pruefen
- keine aggressive Reduktion um der Zahl willen

Typische Kandidaten:

- `panel-admin`
- `title`
- `block-left`
- `alert`
- `form-conf`
- `basic`
- `basic-changelog-commit`
- `basic-monitor`
- `comment`
- `comment-bulk-actions`
- `session-summary`
- `session-admin-summary`
- `session-row`
- `rating-bar`
- `rating-like`
- `rating-stars-live`
- `rating-like-live`
- `voting-post`
- `voting-view`
- `categories`
- `foot-controls`
- `spoiler`

Regel:

- diese Gruppe ist primaer fuer Stabilisierung und nicht fuer aggressive Verdichtung gedacht

---

## Spur 2: Modul-HTML-Migration

Ziel:

- HTML aus den Admin-Modulen in fachliche Templates verlagern
- aber ohne einen neuen Helper-ZOO aufzubauen
- und ohne jede kleine HTML-Zeile als Datei auszulagern

### Template-Ablage

Template-Ablage folgt nicht pauschal der Modulstruktur.
Der Default ist nicht automatisch eine Datei pro Modul und Funktion.

Moegliche Ablagen:

- `templates/admin/partials/<fachlicher-schnitt>.html`

Voraussetzung:

- der Name muss mit `getHtmlPart()` kompatibel sein
- der Name beschreibt den fachlichen Schnitt, nicht das Modul

Regel:

- zuerst globalen fachlichen Schnitt pruefen
- kein Modulpfad-Schema als Architekturvorgabe
- triviales Markup bleibt in PHP

### Migrationsregel pro Modul

1. Funktion lesen und HTML-Bloecke identifizieren
2. Bloecke als A/B/C/D klassifizieren
3. fachliches Zieltemplate definieren
4. nur die sichtbare Struktur ins Template ziehen
5. Daten, Bedingungen, Schleifensteuerung und Escaping-Entscheidungen in PHP belassen
6. kein Rest-HTML-String in der migrierten Hauptstruktur
7. wenn zu viele Raw-Slots noetig sind: Batch stoppen und Schnitt neu bewerten

### Raw-Slot-Regel

`{{{ rows_html }}}`, `{{{ editor_html }}}` oder aehnliche Slots sind nur als Uebergang erlaubt, wenn:

- ein kompletter Block bereits fachlich sauber abgegrenzt ist
- die Schleifen- oder Helper-Migration noch in einer spaeteren Phase folgt

Sie sind nicht das Endziel fuer ganze Module.

Endzustand:

- ein Modul-Template darf einzelne bewusst definierte Raw-Slots fuer komplexe Widgets behalten
- ein Modul-Template darf nicht dauerhaft im Wesentlichen nur ein Wrapper um grosse zusammengesetzte `*_html`-Strings sein
- Listen, Tabellen, Form-Layouts und Panels sollen am Ende als echte Template-Struktur vorliegen

Praktische Obergrenze fuer den Endzustand:

- maximal 1-2 fachlich begruendete Raw-Slots pro Zieltemplate
- keine komplette Seite, die fast nur aus `rows_html`, `form_html`, `content_html`, `panel_html` zusammengesetzt ist

### Stop-Bedingungen pro Modul

Sofort stoppen und neu planen, wenn:

- mehr als 3 Raw-Slots fuer die Zielstruktur noetig sind
- ein Template mehrere unterschiedliche Escaping-Kontexte mischt
- fuer die Migration ein neuer Wrapper-Helper ohne echten Mehrwert noetig waere
- die SLAED-Template-Engine fuer die benoetigte Logik nicht ausreicht

---

## Risikobasierte Modulreihenfolge

Keine starre lineare Abarbeitung aller 24 Module.
Stattdessen Batches nach Risiko und Wiederverwendbarkeit.

### Batch A: Niedriges Risiko

Ziel:

- schnelle, saubere Vorlagen
- Template-Schnitt stabilisieren
- Verifikation billig halten
- zuerst gemeinsamen globalen Schnitt finden, nicht Moduldateien vermehren

Module:

- `comments.php`
- `favorites.php`
- `privat.php`
- `ratings.php`
- `statistic.php`
- `editor.php`
- `referers.php`
- `replace.php`

### Batch B: Mittleres Risiko

Ziel:

- wiederkehrende Listen und Form-Schnitte
- begrenzte Helper-Abhaengigkeiten

Module:

- `admins.php`
- `groups.php`
- `lang.php`
- `messages.php`
- `newsletter.php`
- `modules.php`
- `uploads.php`
- `scheduler.php`
- `fields.php`

### Batch C: Hoeheres Risiko

Ziel:

- fachlich groessere Admin-Bereiche erst nach stabilen Mustern migrieren

Module:

- `blocks.php`
- `categories.php`
- `security.php`
- `database.php`

### Batch D: Sonderfall

Module:

- `config.php`

Regel:

- erst eigene Voranalyse der Tab-Struktur
- erst danach Teilmigration pro Tab

### Batch E: Vorerst nicht priorisieren

Module:

- `monitor.php`

Regel:

- nur anfassen wenn sich aus einem gemeinsamen Schnitt echter Mehrwert ergibt

---

## Vorgeschlagene Modul-Zielschnitte

Das sind Zielbilder, keine harten Vorbedingungen.
Wenn der reale Code einen besseren fachlichen Schnitt zeigt, gilt der reale Schnitt.

### Batch A: globale Erst-Schnitte

| Bereich | Bevorzugter Schnitt | Hinweis |
|---|---|---|
| `comments.php`, `ratings.php`, `favorites.php`, `privat.php` Konfiguration | `partials/admin-config-form.html` | zuerst auf gemeinsamen fachlichen Config-Schnitt pruefen |
| `referers.php`, `statistic.php` Suchbereich | bestehendes `searchbox` beibehalten | bereits als globaler Partial-Schnitt vorhanden |
| `referers.php`, `statistic.php` Listen | vorerst separat pruefen | nur globalisieren wenn Tabellenstruktur und Escaping wirklich gleich sind |
| `editor.php` | vorerst kein neues globales Template | vorhandene Editor-Helper weiterverwenden |
| `replace.php` | vorerst kein neues globales Template | wegen Tab- und Wiederholstruktur separat schneiden |

### Weitere fachliche Zielbilder

Diese Ziele sind bewusst fachlich benannt.
Wenn dafuer spaeter einzelne Module aus dem globalen Schnitt herausfallen, ist das eine Ausnahme und kein Default.

| Bereich | Bevorzugter Schnitt | Hinweis |
|---|---|---|
| wiederkehrende Admin-Listen | `partials/admin-list-table.html` | nur wenn Tabellenstruktur und Escaping tragfaehig gleich sind |
| wiederkehrende Admin-Formulare | `partials/admin-form-panel.html` | nur wenn Feld- und Layoutschnitt wirklich gemeinsam ist |
| Dateiformulare | `partials/admin-file-form.html` | fuer kleine Datei- und Auswahlformulare |
| Editor-Bloecke | `partials/admin-editor-panel.html` | fuer Code- oder Texteditor-Bereiche |
| Kategorien-Schnitte | `partials/admin-category-list.html` | nur wenn Kategorien ueber mehrere Module fachlich gleich gebaut sind |
| Sicherheits-Schnitte | `partials/admin-security-ban-list.html` | nur wenn nicht mit Config oder Mail-Kontext vermischt |
| Config-Tabs | `partials/admin-config-section.html` | Tab-Dateien nicht automatisch pro Bereich anlegen |

Beispiele fuer Sonderfaelle:

- `blocks` kann wegen bereits vorbereiteter Templates ein eigener Sonderfall bleiben
- `replace` bleibt bis auf Weiteres separat, wenn Tab- und Wiederholstruktur keinen sauberen globalen Schnitt ergibt
- `config.php` wird tabweise fachlich geschnitten und nicht ueber Modulpfade vorstrukturiert

---

## Umgang mit bestehenden Helfern

### Vorlaeufig beibehalten

Diese Helfer muessen nicht sofort auf neue Daten-Helfer umgebaut werden:

- `radio_form()`
- `language()`
- `rss_select()`
- `redaktor()`
- `get_gender()`
- `modul()`
- `cat_modul()`
- `getcat()`
- `textarea()`
- `textareae()`
- `textarea_code()`
- `title_tip()`
- `mailto()`
- `ad_status()`
- `add_menu()`
- `get_user_search()`

Grund:

- zuerst Template-Grenze stabilisieren
- danach pruefen, welche Helper wirklich ein Architekturproblem sind

### Spaeter pruefen

Nur fuer wiederkehrende komplexe Datensaetze:

- ob ein echter Daten-Helper mehrere Module vereinfacht
- ob ein direkter Rename statt Wrapper moeglich ist
- ob der bestehende Helper besser unveraendert bleibt

---

## Verifikationsmatrix pro Batch

Jeder Batch braucht diese vier Ebenen:

### 1. Statisch

- `php -l` auf allen geaenderten PHP-Dateien
- Referenzsuche auf entfernte Fragmente oder alte Pfade

### 2. Render-Pruefung

- betroffene Admin-Route oeffnen
- pruefen ob Seite ohne PHP-Warning oder leeres Template rendert
- relevante Listen, Formulare, Buttons und Labels sichtbar

### 3. Write-Flow

Nur wenn der Batch schreibende Admin-Formulare anfasst:

- Formular absenden
- Erfolg oder Fehlermeldung im UI pruefen

### 4. Persistenz und Logs

Bei state-changing Batches:

- Konfig, Datei oder Datenbank-Ergebnis pruefen
- `storage/logs/error_php.log` pruefen
- `storage/logs/error_sql.log` pruefen
- `storage/logs/error_site.log` pruefen

Ohne diese Ebene gilt ein schreibender Batch nicht als voll verifiziert.

---

## Batch-Reihenfolge

Grundsatz:

- Spur 2 hat Vorrang vor weiterer Spur-1-Konsolidierung
- zuerst Modul-HTML in fachliche Templates migrieren
- erst danach verbliebene Fragmente innerhalb stabiler Fachgruppen konsolidieren

Begruendung:

- sonst wuerde Fragment-Konsolidierung auf einem noch instabilen Modul-Unterbau stattfinden
- zuerst muss klar sein, welche Templates die Module tatsaechlich brauchen
- erst auf dieser stabilisierten Basis ist weitere Fragment-Reduktion sinnvoll und risikoarm

### Phase 0

- Plan gegen Repo-Stand synchronisieren
- offene Fragmente und bereits migrierte Bereiche aktualisieren
- Batch-Kandidaten festschreiben

#### Repo-Sync Stand 2026-03-26

Ist-Stand Template-Verzeichnis:

- unter `templates/admin/fragments/` existieren aktuell **59** Fragmente
- darunter weiterhin technische Mikro-Fragmente wie `open.html`, `close.html`, `alert.html`, `form-conf.html`
- unter `templates/admin/partials/` existieren aktuell nur **7** Dateien:
- `blocks/add.html`
- `blocks/edit.html`
- `changelog.html`
- `login.html`
- `preview.html`
- `registration.html`
- `searchbox.html`

Ist-Stand Modulmigration:

- echte modulbezogene `partials/<modul>/...` sind derzeit nur fuer `blocks` vorbereitet
- diese vorbereiteten `blocks`-Partials sind im Code aktuell noch **nicht verdrahtet**
- `admin/modules/blocks.php` rendert `add()` und `edit()` weiterhin als grosse HTML-Strings mit `getHtmlFrag('open')` und `getHtmlFrag('close')`
- ausserhalb des Sonderfalls `blocks` gibt es derzeit keine bestaetigte produktive Nutzung fachlich benannter Admin-Partials fuer Modul-Hauptstrukturen
- produktiv verwendet wird bislang im Wesentlichen nur `getHtmlPart('searchbox', ...)` als allgemeiner Partial-Schnitt in `categories.php`, `modules.php`, `uploads.php`, `template.php`, `statistic.php` und `referers.php`
- mehrere Module nutzen weiterhin Fragmente wie `open`, `close`, `alert` und `form-conf`, ohne dass ihre Hauptstruktur in modulbezogene Partials ueberfuehrt waere

Batch-A-Bestaetigung gegen Ist-Stand:

- `comments.php`: noch keine Modul-Partial-Migration; `config()` nutzt weiter `form-conf`, `edit()` baut Formular direkt in PHP
- `favorites.php`: noch keine Modul-Partial-Migration; Startseite per Fragment plus Ajax-HTML, `config()` weiter ueber `form-conf`
- `privat.php`: noch keine Modul-Partial-Migration; Startseite per Fragment plus Ajax-HTML, `config()` weiter ueber `form-conf`
- `ratings.php`: noch keine Modul-Partial-Migration; Konfiguration weiter ueber `form-conf`
- `statistic.php`: nur `searchbox` ausgelagert; Listen- und Config-Hauptstruktur weiter in PHP bzw. `form-conf`
- `editor.php`: noch keine Modul-Partial-Migration; Editor-Formular weiter direkt in PHP
- `referers.php`: nur `searchbox` ausgelagert; Liste weiter in PHP, `config()` weiter ueber `form-conf`
- `replace.php`: noch keine Modul-Partial-Migration; grosse Formular- und Tab-Struktur weiter komplett in PHP

Konsequenz fuer den naechsten Schritt:

- Batch A bleibt als niedriger Risiko-Batch sinnvoll
- er gilt aber noch **nicht** als teilweise vorweggenommen
- als bereits vorbereitet duerfen nur die allgemeinen `searchbox`-Faelle und die noch unverdrahteten `blocks`-Partials gelten
- Spur 1 darf nicht auf einer angenommenen Modul-Partial-Landschaft aufbauen, die im Repo noch nicht produktiv genutzt wird

### Phase 1

- Batch A: niedrige Risiken
- Muster fuer Listen, Formulare und Panels validieren

### Phase 2

- Batch B: mittlere Risiken
- wiederkehrende Strukturen vereinheitlichen

### Phase 3

- Batch C: hohe Risiken
- nur mit stabilen Mustern aus Phase 1 und 2

### Phase 4

- Batch D: `config.php`
- Tab fuer Tab

### Phase 5

- gezielte Fragment-Konsolidierung nur noch innerhalb stabiler Fachgruppen

Regel:

- keine Phase gegen veralteten Planstand
- kein Batch ohne Verifikationsmatrix
- kein neuer Wrapper-Helper ohne Architekturentscheidung

### Batch-Abschluss-Kriterium

Ein Batch gilt erst dann als abgeschlossen, wenn alle folgenden Punkte erfuellt sind:

1. alle fuer den Batch vorgesehenen Zieltemplates sind angelegt oder bewusst verworfen
2. die betroffenen Modul-Funktionen verwenden den neuen Template-Schnitt stabil
3. keine alten HTML-Hauptstrukturen der Batch-Ziele liegen mehr als grosse String-Bloecke in den migrierten Funktionen
4. es gibt keine Restreferenzen auf im Batch entfernte Fragmente oder alte Zwischenpfade
5. die Verifikationsmatrix des Batchs wurde vollstaendig abgearbeitet
6. notwendige Log-Pruefungen bei schreibenden Flows sind unauffaellig
7. der Plan wurde danach auf den neuen Repo-Stand aktualisiert

Wenn einer dieser Punkte offen ist, gilt der Batch als `in Arbeit`, nicht als abgeschlossen.

---

## Zielbild in einem Satz

Nicht moeglichst wenige Dateien und nicht moeglichst wenige Funktionen, sondern eine saubere mittlere Schicht aus fachlichen Admin-Templates mit klaren, testbaren PHP-Template-Grenzen.
