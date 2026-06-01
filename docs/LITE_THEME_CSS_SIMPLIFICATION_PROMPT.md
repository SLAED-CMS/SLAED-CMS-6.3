# SLAED Lite Theme CSS Optimierung

Du arbeitest als Senior CSS-Architekt + Frontend-Refactoring-Agent für SLAED CMS.
Die Aufgabe ist komplex. Du darfst bei Bedarf mehrere Subagenten einsetzen, z. B. für:

- CSS-Duplizierung
- Tokenisierung
- Responsive-Regeln
- DOM-/HTML-Kopplung
- Dead-Code-Prüfung
- visuelle Regression
- Risikoanalyse

Wichtig: Nutze Subagenten nur sinnvoll. Am Ende zählt ein sauberer, konsolidierter Patch ohne widersprüchliche Änderungen.


## Ziel

Optimiere die Lite-Theme-CSS-Struktur direkt.

Kernaufgabe ist CSS-Konsolidierung: gleiche Deklarationsblöcke, ähnliche Komponentenregeln und doppelte Media-Query-Overrides aktiv zusammenführen, nicht nur dokumentieren.

Ziele:

- gleiche visuelle Wirkung
- deutlich weniger CSS
- bessere Wartbarkeit
- keine sichtbaren Layoutänderungen
- keine neuen Klassen ohne echten Grund
- keine Redesign-Entscheidungen
- keine unnötigen HTML-Umbauten

## Relevante Dateien

Primär:

- `templates/lite/assets/css/base.css`
- `templates/lite/assets/css/theme.css`

Für Prüfung und minimale Anpassungen:

- `templates/lite/fragments/*.html`
- `templates/lite/layouts/*.html`
- `templates/lite/pages/*.html`
- `templates/lite/partials/*.html`

## Ausgangslage

- `theme.css` ist sehr groß und fragmentiert.
- `base.css` ist bereits die Token-Schicht.
- Viele Regeln sind historisch gewachsen.
- Viele Bereiche nutzen noch `#id`-Selektoren, tiefe Selektor-Ketten, Footer-/Menü-Wrapper, Tabellen-, Karten-, Panel- und Dropdown-Regeln.
- Ziel ist keine kosmetische Neugestaltung, sondern CSS-Vereinfachung bei gleicher Darstellung.

## Arbeitsauftrag

Arbeite direkt am Code.
Nicht nur analysieren. Nicht nur planen. Direkt optimieren.

Vorgehen:

1. Bestand kurz erfassen.
2. Duplikate und harte Werte finden.
3. Sichere Konsolidierungen direkt umsetzen.
4. Vorhandene Tokens aus `base.css` verwenden.
5. Wiederholte Deklarationen zusammenführen.
6. Responsive-Regeln komponentenbezogen bereinigen.
7. Offensichtlich tote oder doppelte Regeln entfernen, aber nur nach Nutzungssuche.
8. HTML nur minimal ändern, wenn es CSS klar vereinfacht und keine sichtbare Änderung verursacht.
9. Ergebnis testen.
10. Finalen Bericht mit Änderungen, Risiken und Verifikation liefern.

## Strenge Regeln

- Kein Redesign.
- Keine sichtbaren Layoutänderungen.
- Keine neuen Utility-Klassen nur aus Bequemlichkeit.
- Keine Klassen-ZOO-Erweiterung.
- Bestehende Klassen, IDs und Tokens bevorzugen.
- Keine CSS-Regeln löschen, wenn ihre Nutzung nicht geprüft wurde.
- Keine risky Layout-Umbauten in Header, Menü oder Footer ohne klare Notwendigkeit.
- Keine Inline-Styles einführen.
- Keine unnötigen Template-Änderungen.
- Keine Änderung an Routing, PHP-Logik oder Modulverhalten.
- Keine Commits, außer ich fordere es ausdrücklich.
- Keine AI-Attribution in Dateien oder Commit-Messages.

## Optimierungspriorität

Priorität 1: sichere Reduktion

- identische Deklarationsblöcke zusammenführen
- gleiche Farben/Borders/Shadows auf Tokens umstellen
- doppelte Button-, Card-, Table-, Panel- und Dropdown-Regeln konsolidieren
- Media-Query-Duplikate bereinigen
- gleichartige Footer-/Menu-Regeln zusammenführen

Priorität 2: mittleres Risiko

- tiefe Selektoren vereinfachen
- Varianten von Komponenten sauber zusammenführen
- veraltete Fallbacks entfernen, wenn Nutzung geprüft wurde
- redundante `#id`-Ketten reduzieren, ohne HTML-Struktur sichtbar zu ändern

Priorität 3: nur wenn sicher

- minimale HTML-Anpassungen
- Entfernung historischer Selektoren
- Umstrukturierung größerer CSS-Blöcke

## Konkrete CSS-Ziele

Prüfe und optimiere besonders:

- Buttons
- Tabellen
- Karten
- Panels
- Dropdowns
- Footer
- Footer-Menü
- Hauptmenü
- Login-UI
- Pager
- Preview-/Changelog-Flächen
- Formulare
- Avatare/Icon-Flächen
- responsive Layoutbereiche

Responsive `@media`-Overrides gehören zur jeweiligen Komponente und sollen nicht getrennt als Müllhalde am Dateiende behandelt werden, wenn eine sichere Zusammenführung möglich ist.

## Token-Regel

Wenn `base.css` passende Tokens enthält, verwende sie.

Besonders prüfen:

- Farben
- Backgrounds
- Borders
- Border-Radius
- Shadows
- Spacing
- Transitions
- Textfarben
- Linkfarben

Keine neuen Tokens anlegen, wenn vorhandene reichen.

Neue Tokens nur anlegen, wenn:

- derselbe Wert mehrfach vorkommt
- der Wert semantisch stabil ist
- der Token wirklich themeweit sinnvoll ist

## Dead-Code-Regel

Bevor du CSS entfernst:

- Suche nach Selektor-Nutzung in Lite-Templates.
- Prüfe Layouts, Pages, Fragments und Partials.
- Berücksichtige dynamisch erzeugte Klassen, falls im Projekt erkennbar.
- Wenn Nutzung unklar ist: nicht löschen, sondern im Bericht als unsicher markieren.

## HTML-Regel

HTML darf nur geändert werden, wenn dadurch CSS eindeutig einfacher wird.

Erlaubt:

- vorhandene Klassen konsistenter einsetzen
- unnötige Wrapper nur entfernen, wenn sicher
- gleiche Komponentenstruktur vereinheitlichen

Nicht erlaubt:

- neues Markup aus Design-Laune
- große Strukturänderungen
- semantische Änderungen ohne Notwendigkeit
- Klassen erfinden, obwohl vorhandene reichen

## Tests / Verifikation

Nach Änderungen prüfen:

- CSS-Syntax
- relevante Templates auf kaputtes Markup
- Desktop-Ansicht
- Tablet-Ansicht
- Mobile-Ansicht
- Header
- Hauptmenü
- Footer
- Footer-Menü
- Tabellen
- Karten
- Dropdowns
- Formulare
- Login
- Pager
- Preview-/Changelog-Flächen

Wenn Browser-/Screenshot-Tools vorhanden sind:

- Vorher/Nachher visuell vergleichen.
- Keine sichtbaren Layoutänderungen akzeptieren.
- Kleinste Abweichungen im Bericht nennen.

## Erwartetes Ergebnis

Am Ende liefere:

### 1. Summary

- Was wurde optimiert?
- Wie stark wurde CSS reduziert?
- Welche Bereiche wurden bewusst nicht angefasst?

### 2. Changed Files

Liste aller geänderten Dateien.

### 3. Main Refactors

Für jede größere Änderung:

- betroffener Bereich
- vorheriges Problem
- neue Lösung
- Risiko

### 4. Token Changes

- welche harten Werte ersetzt wurden
- welche Tokens verwendet oder ergänzt wurden

### 5. Removed / Consolidated CSS

- welche Blöcke entfernt wurden
- welche Blöcke zusammengeführt wurden
- warum das sicher ist

### 6. HTML Changes

Nur falls HTML geändert wurde:

- Datei
- Änderung
- Grund
- warum keine sichtbare Änderung erwartet wird

### 7. Verification

- welche Prüfungen durchgeführt wurden
- Ergebnis
- offene Risiken

### 8. Remaining Risks

- unsichere Selektoren
- Bereiche, die bewusst nicht gelöscht wurden
- Empfehlungen für nächsten Schritt

## Wichtig

Nicht endlos analysieren.
Erst verstehen, dann direkt verbessern.
Arbeite konservativ, aber wirksam.
Ziel ist ein echter Patch, nicht nur ein Bericht.

Beginne sofort mit der Optimierung. Stelle Rückfragen, wenn vorhanden.