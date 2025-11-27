# SLAED CMS - Modernisierungs-Regeln

## 1. getVar() Array-Handling

### FILTER_REQUIRE_ARRAY ersetzen

**ALT (nicht mehr verwenden):**
```php
$amodules = filter_input(INPUT_POST, 'amodules', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
$modules = is_array($amodules) ? implode(',', array_map('intval', $amodules)) : '';
```

**NEU (korrekt):**
```php
$amodules = getVar('post', 'amodules[]', 'num') ?: [];
$modules = $amodules ? implode(',', $amodules) : '';
```

**Erklärung:**
- `getVar('post', 'field[]', 'type')` für Arrays
- Mit `'num'` type werden alle Array-Elemente automatisch gefiltert
- `array_map('intval', ...)` ist überflüssig
- `?: []` als Elvis-Operator für Default-Wert

---

## 2. Config-Save Funktionen kompakt halten

### Pattern: Inline getVar() in $cont Array

**ALT (verbose):**
```php
function users_save(): void {
    global $admin_file, $confu;

    // 25+ separate Variable-Zuweisungen
    $anonym = getVar('post', 'anonym', 'title');
    $adirectory = getVar('post', 'adirectory', 'title');
    $atypefile = getVar('post', 'atypefile', 'title');

    // 10+ Zeilen Validierung
    $xatypefile = (!$atypefile) ? 'gif,jpg,jpeg,png' : strtolower(strtr($atypefile, $protect));
    $xamaxsize = (!intval($amaxsize)) ? 51200 : $amaxsize;

    // Array-Zusammenstellung
    $cont = [
        'anonym' => $anonym,
        'adirectory' => $adirectory,
        'atypefile' => $xatypefile,
        // ...
    ];

    setConfigFile('users.php', 'confu', $cont);
}
```

**NEU (kompakt):**
```php
function users_save(): void {
    global $admin_file, $confu;
    $protect = ["\n" => '', "\t" => '', "\r" => '', ' ' => ''];
    $cont = [
        'anonym' => getVar('post', 'anonym', 'title'),
        'adirectory' => getVar('post', 'adirectory', 'title'),
        'atypefile' => strtolower(strtr(getVar('post', 'atypefile', 'title') ?: 'gif,jpg,jpeg,png', $protect)),
        'amaxsize' => getVar('post', 'amaxsize', 'num') ?: 51200,
        'awidth' => getVar('post', 'awidth', 'num') ?: 100,
        'user_t' => ($t = getVar('post', 'user_t', 'num')) ? intval($t * 86400) : 2592000,
        'network_c' => "<<<HTML\n".getVar('post', 'network_c', 'text')."\nHTML",
        'name_b' => strtolower(strtr(getVar('post', 'name_b', 'text'), $protect)),
        'points' => $confu['points']
    ];
    setConfigFile('users.php', 'confu', $cont);
    header('Location: '.$admin_file.'.php?op=users_conf');
}
```

**Vorteile:**
- 60+ Zeilen → 15 Zeilen
- Keine redundanten intval() Checks
- Elvis-Operator `?:` für Defaults
- Inline-Verarbeitung (strtolower, strtr)
- Keine intermediate Variablen

---

## 3. Redundante intval() Checks entfernen

**Problem:**
```php
$xamaxsize = (!intval($amaxsize)) ? 51200 : $amaxsize;
```

**Warum redundant?**
- `getVar('post', 'amaxsize', 'num')` gibt **bereits Integer** zurück
- `!intval($var)` ist identisch mit `!$var` bei Integer-Typen

**Lösung:**
```php
$amaxsize = getVar('post', 'amaxsize', 'num') ?: 51200;
```

---

## 4. setConfigFile() Parameter

**NEU (seit Modernisierung):**
```php
setConfigFile('users.php', 'confu', $cont, $confu);
```

**Wichtig:**
- 4. Parameter `$confu` wird jetzt benötigt
- Alte Aufrufe ohne 4. Parameter aktualisieren

**Beispiel:**
```php
// ALT
setConfigFile('users.php', 'confu', $cont);

// NEU
setConfigFile('users.php', 'confu', $cont, $confu);
```

---

## 5. Typed Function Parameters

### setArticleNumbers() Modernisierung

**ALT:**
```php
function setArticleNumbers() {
    global $prefix, $db, $conf, $currentlang;
    $arg = func_get_args();
    // ...
}
```

**NEU:**
```php
function setArticleNumbers(
    string $name,
    string $mod,
    int $limit,
    string $url,
    string $cntfld,
    string $tbl,
    string $catfld = '',
    string $where = '',
    int $maxpg = 10,
    array $params = []
): string {
    global $prefix, $db, $conf, $currentlang;
    // ...
}
```

**Vorteile:**
- Type Safety
- Selbstdokumentierend
- IDE-Unterstützung
- Keine func_get_args() mehr

---

## 6. Code-Formatierung

### Einzeiliges if für Warnungen

**Vorher:**
```php
if ($stop) {
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
}
```

**Nachher:**
```php
if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
```

### SQL Parameter Arrays inline

**Vorher:**
```php
$db->sql_query('UPDATE '.$prefix.'_groups SET name = :name WHERE id = :id', [
    'name' => $grname,
    'id' => $id
]);
```

**Nachher:**
```php
$db->sql_query('UPDATE '.$prefix.'_groups SET name = :name WHERE id = :id', ['name' => $grname, 'id' => $id]);
```

---

## 7. Veraltete PHP-Funktionen entfernen

### stripslashes()

**NICHT mehr verwenden:**
```php
$xnetwork_c = "<<<HTML\n".stripslashes($network_c)."\nHTML";
```

**Korrekt (PHP 8+):**
```php
$network_c = "<<<HTML\n".getVar('post', 'network_c', 'text')."\nHTML";
```

**Grund:** Magic Quotes seit PHP 5.4 entfernt, stripslashes() unnötig

---

## 8. Array-Parameter mit getVar()

### Beispiele für verschiedene Szenarien

**1. Ganzes Array:**
```php
$spoints = getVar('post', 'spoints[]', 'num');
// Ergebnis: [1, 2, 3, 4, 5] (gefilterte Integers)
```

**2. Array mit Fallback auf GET-Parameter:**
```php
$get_id = getVar('get', 'id', 'num');
$id = getVar('post', 'id[]', 'num') ?: ($get_id ? [$get_id] : []);
```

**3. Array-Index-Zugriff:**
```php
$field1 = getVar('post', 'field1'.$a.'['.$i.']', 'title', '0');
```

---

## 9. Zusammenfassung der Hauptregeln

### ✅ DO (Machen)

1. **getVar() mit Bracket-Notation** für Arrays: `getVar('post', 'field[]', 'num')`
2. **Elvis-Operator** für Defaults: `$value ?: 'default'`
3. **Inline-Verarbeitung** in Arrays
4. **Typed Parameters** in Funktionssignaturen
5. **Kompakte Config-Save** Funktionen
6. **setConfigFile()** mit 4 Parametern

### ❌ DON'T (Nicht machen)

1. Keine `filter_input()` mit `FILTER_REQUIRE_ARRAY`
2. Keine redundanten `intval()` Checks bei `getVar(..., 'num')`
3. Keine `func_get_args()` in neuen Funktionen
4. Kein `stripslashes()` (PHP 8+)
5. Keine intermediate Variablen in Config-Save Funktionen
6. Keine `array_map('intval', ...)` nach `getVar(..., 'num')`

---

## 10. Migrations-Checkliste

Bei Modernisierung einer Funktion:

- [ ] `filter_input(FILTER_REQUIRE_ARRAY)` → `getVar('post', 'field[]', 'num')`
- [ ] `array_map('intval', ...)` entfernen (wenn nach getVar 'num')
- [ ] `(!intval($var))` → `!$var` oder Elvis-Operator
- [ ] Intermediate Variablen in Config-Save eliminieren
- [ ] `func_get_args()` → Typed Parameters
- [ ] `stripslashes()` entfernen
- [ ] `setConfigFile()` 4. Parameter hinzufügen
- [ ] Code kompakter formatieren (einzeiliges if, inline SQL-Arrays)

---

## Beispiel-Commits

### Commit-Message Format:
```
Modernize: [Bereich] - [Kurzbeschreibung]

- Datei1: Änderung 1
- Datei2: Änderung 2

Pattern-Ersetzung:
  filter_input() → getVar('post', 'field[]', 'num')

Vorteile: [Liste der Verbesserungen]

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

### Beispiel:
```
Modernize: Replace FILTER_REQUIRE_ARRAY with getVar() bracket notation

- admins.php: 2 occurrences (admins_add, admins_save)
- blocks.php: 2 occurrences (blocks_add_save, blocks_change)

Changed pattern:
  filter_input(INPUT_POST, 'field', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? []
  → getVar('post', 'field[]', 'num') ?: []

Simplified array processing:
  is_array($arr) ? implode(',', array_map('intval', $arr)) : ''
  → $arr ? implode(',', $arr) : ''

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

---

**Erstellt:** 2025-11-27
**Version:** 1.0
**Projekt:** SLAED CMS 6.3.0 Phoenix Modernisierung
