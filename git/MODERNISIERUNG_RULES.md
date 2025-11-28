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

## 11. Modern PHP Array Syntax

**ALT (PHP 5.x):**
```php
$ops = array('admins_show', 'admins_add', 'admins_info');
$lang = array(_HOME, _ADD, _INFO);
$stop = array();
```

**NEU (PHP 7+):**
```php
$ops = ['admins_show', 'admins_add', 'admins_info'];
$lang = [_HOME, _ADD, _INFO];
$stop = [];
```

**Regel:** Verwende immer `[]` statt `array()` für moderne Syntax

---

## 12. Function Return Types

### void für Funktionen ohne Rückgabewert

**ALT:**
```php
function admins_show() {
    // ...
    echo $cont;
    foot();
}
```

**NEU:**
```php
function admins_show(): void {
    // ...
    echo $cont;
    foot();
}
```

**Regel:** Alle Funktionen sollten Return-Types haben (void, string, int, array, bool)

---

## 13. getVar() mit 'req' Source

**Verwendung:** Holt Wert aus GET ODER POST (in dieser Reihenfolge)

```php
$id = getVar('req', 'id', 'num');
// Prüft zuerst $_GET['id'], dann $_POST['id']
```

**Anwendungsfall:** Wenn Parameter sowohl per GET als auch POST kommen können (z.B. bei Edit-Formularen)

**Beispiel aus admins.php:**
```php
function admins_add(): void {
    $id = getVar('req', 'id', 'num');  // Edit-ID aus GET
    if ($id) {
        // Lade existierenden Admin aus DB
    } else {
        // Hole Form-Daten aus POST
        $name = getVar('post', 'name', 'name', '');
    }
}
```

---

## 14. PDO Prepared Statements - Parameter-Formatierung

### Kurze Queries: Inline Parameters

```php
$db->sql_query('DELETE FROM '.$prefix.'_admins WHERE id = :id', ['id' => $id]);
$db->sql_query('SELECT * FROM '.$prefix.'_users WHERE name = :name', ['name' => $name]);
```

### Lange Queries: Multi-line Parameters

**Bei vielen Parametern:**
```php
$db->sql_query('UPDATE '.$prefix.'_admins SET name = :name, title = :title, url = :url, email = :email, pwd = :pwd, super = :super, editor = :editor, smail = :smail, modules = :modules, lang = :lang WHERE id = :id', [
    'name' => $name,
    'title' => $title,
    'url' => $url,
    'email' => $email,
    'pwd' => $newpass,
    'super' => $super,
    'editor' => $editor,
    'smail' => $smail,
    'modules' => $modules,
    'lang' => $lang,
    'id' => $aid
]);
```

**Regel:**
- Bis 3 Parameter → Inline: `['id' => $id, 'name' => $name]`
- Ab 4 Parametern → Multi-line mit Einrückung für Lesbarkeit

---

## 15. Boolean zu Integer Konvertierung für DB

**Problem:** Datenbank erwartet TINYINT(1) für 0/1, aber getVar('bool') gibt true/false

**Lösung:**
```php
$super = getVar('post', 'super', 'bool', 0) ? 1 : 0;
$smail = getVar('post', 'smail', 'bool', 0) ? 1 : 0;
```

**Anwendung:**
- Bei Checkboxen in Formularen
- DB-Felder vom Typ TINYINT(1)
- Explizite 0/1 Werte für Kompatibilität

---

## 16. Template-Funktionen (Modern)

### setTemplateWarning()

**Verwendung:** Warnungen/Infos anzeigen

```php
if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
if (getVar('get', 'send', 'num')) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MAIL_SEND]);
```

**Parameter:**
- `'warn'` - Type (warn, info, error)
- Array mit: time, url, id, text

### setTemplateBasic()

**Verwendung:** Content-Container öffnen/schließen

```php
$cont .= setTemplateBasic('open');
// ... Content hier ...
$cont .= setTemplateBasic('close');
```

**Ersetzt:** `tpl_eval('open')` / `tpl_eval('close')`

---

## 17. getAdminTabs() statt navi_gen()

**ALT:**
```php
return navi_gen(_EDITADMINS, 'admins.png', '', $ops, $lang, '', '', $opt, $tab, $subtab, $legacy);
```

**NEU:**
```php
return getAdminTabs(_EDITADMINS, 'admins.png', '', $ops, $lang, [], [], $tab, $subtab);
```

**Unterschiede:**
- Leere Strings `''` → Leere Arrays `[]`
- Kürzere Parameterliste (kein $opt, $legacy)
- Modernere API

---

## 18. Header Redirects mit dynamischen Query-Parametern

**Pattern:**
```php
if ($mail) {
    // Mail-Logik
    $send = '&send=1';
}
header('Location: '.$admin_file.'.php?op=admins_show'.$send);
```

**Besser mit Null Coalescing:**
```php
header('Location: '.$admin_file.'.php?op=admins_show'.($send ?? ''));
```

---

## 19. Validation Arrays

**Pattern für Fehlersammlung:**
```php
$stop = [];
if (!$aid && !$pwd && !$pwd2) $stop[] = _NOPASS;
if ($name) {
    list($adid, $adname) = $db->sql_fetchrow($db->sql_query('SELECT id, name FROM '.$prefix.'_admins WHERE name = :name', ['name' => $name]));
    if ($aid != $adid && $name == $adname) $stop[] = _USEREXIST;
}
if (!$stop) {
    // Save-Logik
} else {
    // Zeige Form mit Fehlern
}
```

**Vorteile:**
- Sammle alle Fehler
- Zeige alle auf einmal
- Bessere UX

---

## 20. Zusammenfassung erweiterte Regeln

### ✅ DO (Zusätzlich)

1. **Modern Array Syntax**: `[]` statt `array()`
2. **Return Types**: Alle Funktionen typisieren (void, string, int, array)
3. **getVar('req', ...)**: Für GET/POST-flexible Parameter
4. **PDO Multi-line**: Ab 4+ Parametern formatieren
5. **Boolean → Int**: `? 1 : 0` für DB-Kompatibilität
6. **setTemplateWarning/Basic**: Statt tpl_eval()
7. **getAdminTabs()**: Statt navi_gen()
8. **Validation Arrays**: $stop[] für Fehlersammlung

### ❌ DON'T (Zusätzlich)

1. Keine `array()` Syntax mehr
2. Keine Funktionen ohne Return Type
3. Kein direkter `$_GET/$_POST` Zugriff
4. Keine `tpl_eval()` für Basic-Templates
5. Keine Boolean direkt in DB speichern (immer 0/1)

---

## 21. Vollständiges Beispiel (admins.php Style)

```php
function admins_save(): void {
    global $prefix, $db, $admin_file, $conf, $stop;

    // Input mit getVar
    $aid = getVar('post', 'aid', 'num', 0);
    $name = getVar('post', 'name', 'name');
    $amodules = getVar('post', 'amodules[]', 'num') ?: [];
    $modules = $amodules ? implode(',', $amodules) : '';
    $super = getVar('post', 'super', 'bool', 0) ? 1 : 0;

    // Validation
    $stop = [];
    if (!$name) $stop[] = _ERROR_ALL;
    if (!analyze_name($name)) $stop[] = _ERRORINVNICK;

    if (!$stop) {
        if ($aid) {
            // Update mit Multi-line Parameters
            $db->sql_query('UPDATE '.$prefix.'_admins SET name = :name, title = :title, super = :super, modules = :modules WHERE id = :id', [
                'name' => $name,
                'title' => $title,
                'super' => $super,
                'modules' => $modules,
                'id' => $aid
            ]);
        } else {
            // Insert
            $db->sql_query('INSERT INTO '.$prefix.'_admins (name, title, super, modules) VALUES (:name, :title, :super, :modules)', [
                'name' => $name,
                'title' => $title,
                'super' => $super,
                'modules' => $modules
            ]);
        }
        header('Location: '.$admin_file.'.php?op=admins_show');
    } else {
        admins_add();  // Zeige Form mit Fehlern
    }
}
```

---

**Erstellt:** 2025-11-27
**Version:** 2.0
**Projekt:** SLAED CMS 6.3.0 Phoenix Modernisierung
**Ergänzt:** Modern Array Syntax, Return Types, PDO Formatting, Templates
