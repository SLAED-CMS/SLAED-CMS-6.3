# SLAED Coding Rules

**Autor:** Eduard Laas
**Projekt:** SLAED CMS
**Projektstart:** 2005
**Zielplattform:** PHP ^8.4
**AI-Kommunikation:** Russisch oder Deutsch

---

## 1. Allgemeine Prinzipien

1. **schnell**: kurze Ladezeiten, optimierte Abfragen, Cache nutzen
2. **stabil**: Fehlervermeidung, konsistente API, Ausfallsicherheit
3. **effektiv**: klare Strukturen, wiederverwendbarer Code, keine Redundanzen
4. **produktiv**: einfache Erweiterbarkeit, klare Coding-Richtlinien, schnelle Entwicklung möglich
5. **sicher**: Schutz vor XSS, CSRF, SQL-Injection, sichere Speicherung von Daten

---

## 2. Regel-Prioritäten für KI

1. SLAED-Projektregeln > allgemeine Einstellungen
2. AI-Kommunikation: Russisch oder Deutsch (Pflicht!)
3. Performance > übermäßiges Logging

---

## 3. Kern-Verben

1. **Verbindlich:** `get`, `set`, `add`, `update`, `delete`, `is`, `check`, `filter`
2. Jede Funktion MUSS mit einem dieser Verben beginnen.

---

## 4. Funktionsnamen

1. Format: **Verb + Nomen**, CamelCase, max. 2–3 Wörter im Nomen

**Beispiele:**

```php
function getUserById(int $id): array {}
function setConfig(string $file, array $cfg): bool {}
function isUserActive(int $id): bool {}
```

---

## 5. Rückgabeverhalten

| Verb                  | Typische Rückgabe   | Pflicht?            |
| --------------------- | ------------------- | ------------------- |
| get                   | Array/Object/String | ja                  |
| is                    | bool                | ja                  |
| check                 | bool/Array (Fehler) | ja                  |
| filter                | bereinigte Daten    | ja                  |
| set/add/update/delete | bool/ID             | nein (void erlaubt) |

---

## 6. Variablen

1. Keine CamelCase, 4–8 Zeichen bevorzugt
2. Kurz-Ausnahmen: `$id`, `$db`, `$cfg`, `$tmp`
3. Arrays: `$arr`, `$list`, `$rows`

---

## 7. Dateinamen / Klassen / Konstanten

1. **Dateien:** snake_case.php
2. **Klassen:** PascalCase
3. **Konstanten:** UPPER_CASE

---

## 8. Dateikopf & Kommentare

1. **Dateikopf (bei neuen Dateien):** Englisch

**Beispiel:**

```php
<?php
# Author: Eduard Laas
# Copyright © 2005 - present SLAED
# License: GNU GPL 3
# Website: slaed.net
```

2. **Code-Kommentare:** Englisch
3. **Kommunikation mit AI:** Russisch oder Deutsch

---

## 9. Formatierung & Type-Hints

1. UTF-8, 4 Leerzeichen, max. 120 Zeichen pro Zeile
2. Neue Module: `declare(strict_types=1);`
3. Type-Hints und Rückgabetypen nutzen, PHP 8.4 Features erlaubt

---

## 10. Linting / Static Analysis

1. `phpcs` (PSR-12), `phpstan` Level ≥5, `php-cs-fixer`
2. CI prüft: phpcs, phpstan, phpunit

---

## 11. Sicherheitsregeln

1. **DB:** PDO + Prepared Statements
2. **Output:** Escaping vor HTML-Ausgabe
3. **Input:** Validieren und filtern
4. **CSRF:** Token bei state-ändernden Formularen
5. **Uploads:** MIME-Type, Extension, Größe prüfen, sicher ablegen
6. **Secrets:** Nie im VCS, Umgebungsvariablen nutzen

---

## 12. Tests & Coverage

1. Unit-Tests mit phpunit
2. Coverage ≥60% für Kernmodule
3. CI führt Tests vor jedem Merge aus

---

## 13. Refactoring

1. Kleine Batches, CSV-Mapping: `old_name,new_name,verb,noun,return_type,notes`
2. Tests + CI nach jedem Batch ausführen

---

## 14. Editor Setup

1. UTF-8, 4 Leerzeichen, max. 120 Zeichen
2. phpcs und phpstan aktivieren
3. Snippets für Kern-Verben nutzen
4. Cursor springt automatisch zu `$0` in Snippets für schnelles Ausfüllen

---

## 15. Häufige KI-Fehler (vermeiden)

❌ Falsch: function sanitizePath()
✅ Richtig: function filterPath()

❌ Falsch: übermäßiges Logging
✅ Richtig: minimale, schnelle Meldungen

❌ Falsch: func_get_args() verwenden
✅ Richtig: Spread Operator ... (seit PHP 5.6+)

**Beispiel Spread Operator:**
```php
// ❌ Veraltet
function doSomething() {
    $args = func_get_args();
}

// ✅ Modern (explizite Parameter - bevorzugt)
function doSomething(string $param1 = '', int $param2 = 0): void {}

// ✅ Modern (variadic - wenn wirklich nötig)
function doSomething(string ...$params): void {}
```

---

## 16. Funktionsschreibweise

1. Klammer auf derselben Zeile wie der Name
2. Verb + Nomen als Name, CamelCase, max. 2–3 Wörter im Nomen
3. `{ }` auf separaten Zeilen, Code eingerückt mit 4 Leerzeichen
4. Type-Hints und Rückgabewert verwenden, wenn möglich

**Beispiel:**

```php
function getImgEncode(string $img): string {
   return base64_encode(file_get_contents($img));
}
```

---

## 17. String-Konkatenation / Quotes

1. Immer `'...'` verwenden, außer zwingend `"..."` notwendig
2. Variablen mit `.` konkatinieren
3. Keine direkte Interpolation von Variablen in `"..."`

**Beispiele:**

```php
echo '<span title="'.$ttl.'" class="'.$cls.'">'.$acon.'</span>';
$sql = 'SELECT * FROM users WHERE id='.$id;
```

---

## 18. String-Konkatenation: keine Leerzeichen um Punkt

1. Keine Leerzeichen um den `.`-Operator
2. Immer konsistent `'...'` für Strings verwenden

**Beispiele:**

```php
return '<span title="'.htmlspecialchars($tit, ENT_QUOTES, 'UTF-8').'" class="sl_blue sl_note">'.$acon.'</span>';
$ttl = _RATE3.$info;
```

---

## 19. Konstanten-Regeln

1. **Präfix `_`**

- Alle Konstanten müssen mit `_` beginnen.
- Beispiel: `_ERR_COPY`, `_USR_NAME`, `_PRSAVEMAX`.

2. **Schreibweise**

- Nur **UPPER_CASE** verwenden.
- Wörter durch `_` trennen.
- Länge 4–16 Zeichen empfohlen, möglichst kurz und eindeutig.
- Beispiele: `_MOD_ON`, `_DB_FAIL`, `_ZIP_MISS`.

3. **Quotes**

- Immer `'...'` (einfache Quotes) statt `"..."` verwenden.
- Keine unnötigen Leerzeichen nach `,`.
- Beispiel: 

```php
define('_ERR_FILE', 'File not found: %1$s');
```

4. **Platzhalter**

- Verwende `printf`-kompatible Platzhalter: `%1$s`, `%2$d` usw.
- Keine unnötigen Backslashes (`%1\$s` → `%1$s`).
- Beispiel: 

```php
define('_PRSAVEMAX', 'Die maximale Anzahl der gespeicherten Nachrichten %1$s, Abteilung: %2$s, verbleibend: %3$s.');
```

5. **Mehrsprachigkeit (Pflicht!)**

- Jede Konstante **muss in allen 6 Sprachen** definiert werden:
- Englisch (EN)
- Französisch (FR)
- Deutsch (DE)
- Polnisch (PL)
- Russisch (RU)
- Ukrainisch (UA)
- Der **Konstantenname bleibt identisch**, nur der Textwert wird übersetzt.
- Beispiel:

```php
// German
define('_ERR_FILE', 'Datei nicht gefunden: %1$s');
// English
define('_ERR_FILE', 'File not found: %1$s');
// French
define('_ERR_FILE', 'Fichier introuvable : %1$s');
// Polish
define('_ERR_FILE', 'Nie znaleziono pliku: %1$s');
// Russian
define('_ERR_FILE', 'Файл не найден: %1$s');
// Ukrainian
define('_ERR_FILE', 'Файл не знайдено: %1$s');
```

6. **Kategorien**

- Fehler → `_ERR_*`
- System → `_SYS_*`
- Benutzer → `_USR_*`
- Module → `_MOD_*`
- Andere Gruppen möglich, aber konsistent halten.

7. **Keine doppelten Konstanten**

- Jede Konstante darf nur einmal definiert werden.
- Prüfen mit `defined('_NAME')` vor `define()`.

8. **Kommentarpflicht**

- Jede neue Konstantengruppe erhält einen kurzen Kommentarblock mit Zweck.
- Beispiel: 

```php
# Error messages
define('_ERR_COPY', 'Kopieren fehlgeschlagen: %1$s → %2$s');
```

---

## 20. Git Commit Messages

1. **Sprache:** Immer Englisch
2. **Format:** Detailliert und strukturiert

**Struktur:**

```
Brief summary (50-72 chars)

Detailed description of what changed and why:
- First change with explanation
- Second change with context
- Third change with reasoning
- Impact on system/performance/security

Technical details:
- Files modified and their purpose
- Functions/methods affected
- Database changes (if any)
- Dependencies updated (if any)

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

**Beispiel:**

```
Refactor user authentication system for PHP 8.4

Modernize authentication to leverage PHP 8.4 features:
- Replace procedural auth functions with AuthService class
- Add strict type declarations and return types
- Implement password hashing with Argon2id algorithm
- Add session token validation with CSRF protection

Technical changes:
- New file: core/classes/AuthService.php
- Modified: core/user.php, core/security.php
- Updated: config/config_security.php

Security improvements:
- Stronger password hashing (bcrypt → Argon2id)
- Session fixation prevention
- CSRF token validation

Performance impact: ~15% faster auth checks

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

3. **Pflicht:** Immer detailliert, nie kurz wie "fix bug" oder "update code"
4. **Was beschreiben:**
   - Was wurde geändert (What)
   - Warum wurde es geändert (Why)
   - Welche Dateien betroffen (Where)
   - Welche Auswirkungen (Impact)

---

## 21. KI-Checkliste

1. [ ] Funktion beginnt mit einem der 8 Verben?
2. [ ] Kommunikation auf Russisch/Deutsch?
3. [ ] Code schnell und effizient?
4. [ ] Type-Hints hinzugefügt?
5. [ ] Git Commit auf Englisch und detailliert?