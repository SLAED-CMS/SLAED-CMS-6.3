# Admin Module Modernization Rules

Diese Regeln werden beim Modernisieren von Admin-Modulen angewendet.

## 1. Navigation-Funktion

- **Immer umbenennen:** `{modul}Navi()` → `navi()`
- **Beispiel:** `blocksNavi()`, `categoriesNavi()`, `editorNavi()` → alle zu `navi()`
- **Signatur beibehalten:** `function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string`

## 2. URL-Parameter bereinigen

- **Entfernen:** `&op=show` aus allen Navigation-URLs
- **Begründung:** Default-Case im Switch behandelt dies bereits
- **Beispiel:**
  - Vorher: `'name=categories&amp;op=show'.$modlink`
  - Nachher: `'name=categories'.$modlink`
- **Auch entfernen:** Versteckte `<input type="hidden" name="op" value="show">` Felder in Formularen

## 3. Exit nach Header-Redirects

- **Immer hinzufügen:** `exit;` nach jedem `header('Location: ...');` Aufruf
- **Sicherheit:** Verhindert weitere Code-Ausführung nach Redirect
- **Beispiel:**
  ```php
  header('Location: '.$aroute.'.php?name=categories&modul='.$modul);
  exit;
  ```

## 4. Switch-Case Formatierung

- **Mit Leerzeichen:** `switch ($op)` statt `switch($op)`
- **Konsistenz:** Alle Module verwenden diese Formatierung

## 5. Funktionen extrahieren

- **Inline Cases extrahieren:** Switch-Cases mit Code-Blöcken in separate Funktionen auslagern
- **Beispiel:**
  - Vorher: `case 'del': { /* code */ } break;`
  - Nachher:
    ```php
    function del(): void { /* code */ }
    case 'del': del(); break;
    ```

## 6. Funktionsnamen und Switch-Cases

- **Konsistenz:** Switch-Case-Name muss mit URL-Parameter übereinstimmen
- **Beispiel:**
  - URL: `op=del` → Switch: `case 'del':` → Funktion: `del()`
  - URL: `op=editheader` → Switch: `case 'editheader':` → Funktion: `editheader()`
- **Bei Konflikten:** Funktion umbenennen (z.B. `header()` → `editheader()` wegen PHP built-in)

## 7. Variablen-Namen

- **Global Variable:** `$admin_file` → `$aroute`
- **In allen Funktionen ersetzen:** global-Deklarationen und Verwendungen
- **Beispiel:**
  ```php
  // Vorher
  global $prefix, $db, $admin_file;
  header('Location: '.$admin_file.'.php?name=categories');

  // Nachher
  global $prefix, $db, $aroute;
  header('Location: '.$aroute.'.php?name=categories');
  ```

## 8. Datei-Operationen

- **Verwenden:** `scandir()` statt `opendir()`/`readdir()`/`closedir()`
- **Moderner und sauberer Code**
- **Beispiel:**
  ```php
  // Vorher
  $handle = opendir($path);
  while ($entry = readdir($handle)) { ... }
  closedir($handle);

  // Nachher
  $files = scandir($path);
  foreach ($files as $entry) { ... }
  ```

## 9. PHP Built-in Konflikte vermeiden

- **Niemals verwenden:** Funktionsnamen die mit PHP built-in Funktionen kollidieren
- **Bekannte Konflikte:**
  - `header()` → umbenennen zu z.B. `editheader()`
  - `list()`, `echo()`, `print()`, etc.
- **Lösung:** Prefix hinzufügen (z.B. `edit`, `show`, `get`)

## 10. Funktionsnamen-Konvention

- **Alle Modulfunktionen:** Komplett kleingeschrieben, keine Unterstriche, so kurz wie möglich
- **Standard-Funktionen:** `navi()`, `add()`, `save()`, `del()`, `info()`
- **Interne Helper:** So kurz wie sinnvoll ohne Konflikte
- **Beispiele:**
  ```php
  // ✗ Falsch (camelCase oder Unterstriche)
  getGitHubCommits()
  group_commits_by_date()
  renderCommit()

  // ✓ Richtig (komplett lowercase, kurz)
  commits()
  groupbydate()
  render()
  ```
- **Wichtig:** Keine camelCase, keine Unterstriche (_), nur lowercase
- **Referenz:** Siehe `admin/modules/admins.php` als Beispiel

## 11. Checkliste für Modernisierung

Beim Modernisieren eines Admin-Moduls:

- [ ] `{modul}Navi()` → `navi()` umbenennen
- [ ] Alle `navi()` Aufrufe aktualisieren
- [ ] `&op=show` aus URLs entfernen
- [ ] `exit;` nach allen `header()` Aufrufen hinzufügen
- [ ] Switch-Formatierung: `switch ($op)` mit Leerzeichen
- [ ] Inline switch-cases in Funktionen extrahieren
- [ ] Switch-Case-Namen mit Funktionsnamen abgleichen
- [ ] `$admin_file` → `$aroute` global ersetzen
- [ ] `scandir()` statt `opendir()/readdir()` verwenden
- [ ] PHP built-in Funktionsnamen-Konflikte prüfen
- [ ] **Funktionsnamen:** Alle komplett kleingeschrieben, keine Unterstriche, so kurz wie möglich
- [ ] Alte kommentierte Code-Blöcke entfernen (wenn vorhanden)

## Modernisierte Module

- ✓ `admin/modules/admins.php`
- ✓ `admin/modules/blocks.php`
- ✓ `admin/modules/categories.php`
- ✓ `admin/modules/changelog.php`
- ✓ `admin/modules/comments.php`
- ✓ `admin/modules/database.php`
- ✓ `admin/modules/editor.php`

## Besondere Fälle

### comments.php
- Funktionen umbenannt: `comm_act()` → `act()`, `comm_del()` → `del()`
- Switch-Cases angepasst: `case 'comm_act':` → `case 'act':`
- Externe Referenzen in `core/core.php` aktualisiert (Zeile 4923)

### editor.php
- Funktion `header()` → `editheader()` wegen PHP built-in Konflikt
- URL bleibt konsistent: `op=editheader`

### categories.php
- Modul-Filter in Navigation mit `$modlink` Parameter
- Switch-Case: `case 'delete':` → `case 'del':` für Konsistenz
