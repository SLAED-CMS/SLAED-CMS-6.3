# LITE Theme CSS Simplification Prompt

Du bist ein Senior CSS-Architekt für SLAED CMS und analysierst ausschließlich die lokale Lite-Theme-Codebasis.

## Kontext

Ziel ist nicht ein Redesign, sondern die **gleiche visuelle Wirkung mit deutlich weniger CSS**.

Relevante Dateien:

- `templates/lite/assets/css/base.css`
- `templates/lite/assets/css/theme.css`
- `templates/lite/layouts/app.html`
- `templates/lite/layouts/home.html`
- `templates/lite/partials/menu.html`
- optional zusätzlich prüfen:
  - `templates/lite/pages/*.html`
  - `templates/lite/fragments/*.html`

Aktueller Zustand:

- `theme.css` ist mit ca. **5564 Zeilen** sehr groß.
- `base.css` ist bereits die Token-Schicht mit ca. **549 Zeilen**.
- Viele Bereiche sind bereits semantisch verbessert, aber CSS ist noch stark fragmentiert.
- Das Layout nutzt weiterhin viele `#id`-Selektoren, Footer-/Menü-Wrapper, Tabellen-, Karten- und Dropdown-Komponenten.

## Ziel

Analysiere sehr präzise, wie man die CSS-Menge mit **dem gleichen Design-Effekt** deutlich kompakter machen kann.

Wichtig:

- Kein Redesign.
- Keine sichtbaren Layoutänderungen.
- Keine neuen Klassen nur aus Bequemlichkeit.
- Bestehende Tokens aus `base.css` bevorzugen.
- Wenn HTML-Anpassungen nötig sind, dann nur minimal und mit klarer Begründung.

## Analyseauftrag

Untersuche die CSS-Basis in folgenden Dimensionen:

1. **Duplikate**
   - Welche Selektoren oder Deklarationsblöcke wiederholen sich fast identisch?
   - Welche Blöcke können zu gemeinsamen Komponenten zusammengezogen werden?

2. **Tokenisierung**
   - Welche harten Farbwerte, Shadows, Border-Werte und Abstände sollten auf Tokens aus `base.css` umgestellt werden?
   - Welche Werte sind bewusst dekorativ und sollten bleiben?

3. **Layout-Risiko**
   - Welche Regeln sind stark gekoppelt an DOM-Struktur oder `#id`-Selektoren?
   - Wo besteht das größte Risiko, dass ein kleiner HTML-Fix drei CSS-Bereiche beeinflusst?
   - Welche `@media`-Blöcke verstärken diese Kopplung oder überschreiben sie nur für bestimmte Breakpoints?

4. **Komponenten-Zuschnitt**
   - Welche UI-Bereiche sind eigentlich dieselbe Komponente mit nur kleinen Varianten?
   - Beispiele: Buttons, Tabellenköpfe, Karten, Dropdowns, Footer-Menü, Preview-/Changelog-Flächen, Login-UI, Pager, Panels.
   - Berücksichtige dabei auch responsive `@media`-Overrides als Teil derselben Komponente, wenn sie dieselben Elemente nur anders ausprägen.

5. **Dead Weight**
   - Welche Regeln wirken veraltet, doppelt oder nur als Fallback?
   - Welche Selektoren sind vermutlich nur historisch vorhanden und nicht mehr notwendig?

## Konkrete Fragen, die du beantworten musst

1. Welche 10 bis 20 CSS-Regeln verursachen den größten Wartungsaufwand?
2. Welche 5 bis 10 Selektoren-Ketten sollten zuerst zusammengeführt werden?
3. Welche Farben, Borders und Shadows können ohne visuelle Änderung vereinheitlicht werden?
4. Welche Bereiche sollten in `base.css` bleiben und welche in `theme.css`?
5. Welche Teile sind aktuell die größten Risiko-Zonen für CSS-Breakage?
6. Wie stark kann die CSS-Datei realistisch schrumpfen?
   - nenne eine konservative Spanne in Prozent
   - nenne eine aggressive Spanne in Prozent
7. Was sollte **nicht** angefasst werden, weil es zu viel Risiko für zu wenig Nutzen bringt?

## Erwartetes Output-Format

Antworte in genau dieser Struktur:

### 1. Executive Summary
- kurze Gesamtaussage
- wichtigste Ursache für die CSS-Größe
- grobe Schrumpf-Potenziale

### 2. Top Duplication Clusters
- Liste der größten Wiederholungsgruppen
- jeweils mit Dateipfad und ungefähren Selektoren

### 3. Safe Consolidation Candidates
- was kann mit geringem Risiko zusammengelegt werden
- was kann auf vorhandene Tokens umgestellt werden

### 4. High-Risk Areas
- welche Bereiche nur mit Vorsicht ändern
- warum genau dort das Risiko hoch ist

### 5. Concrete Refactor Plan
- 5 bis 10 konkrete Schritte
- in sinnvoller Reihenfolge
- mit Priorität und Risikoangabe

### 6. CSS Reduction Estimate
- konservative Schätzung
- realistische Schätzung
- aggressive Schätzung

### 7. Non-goals
- was du bewusst nicht anfassen würdest

## Zusätzliche Regeln

- Keine allgemeinen Ratschläge ohne Bezug auf die konkreten Dateien.
- Keine vagen Aussagen wie „CSS ist groß“ oder „man könnte optimieren“.
- Jede Aussage sollte möglichst an einem echten Selektor, Block oder Muster festgemacht werden.
- Wenn du etwas nur vermutest, markiere es als Vermutung.
- Wenn du HTML-/Template-Anpassungen empfiehlst, erkläre genau, warum sie CSS vereinfachen.
- Wenn du eine Umstellung auf Tokens vorschlägst, nenne die passenden vorhandenen Token-Namen.
- Trenne bei der Analyse klar zwischen Basisregeln und responsive `@media`-Overrides.

## Qualitätsziel

Die Antwort soll so präzise sein, dass man daraus direkt einen sicheren Refactor-Plan ableiten kann.
