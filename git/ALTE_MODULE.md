# SLAED CMS - Alte Module (vor 2024)

## Übersicht: Module die modernisiert werden müssen

Stand: 25.11.2025

### Sehr alte Module (2017 - 2018)

#### 1. admin/modules/privat.php
- **Letzte Änderung:** 07.03.2017 (8+ Jahre alt)
- **Status:** ⚠️ Benötigt Modernisierung
- **Priorität:** HOCH

#### 2. admin/modules/ratings.php
- **Letzte Änderung:** 07.03.2017 (8+ Jahre alt)
- **Status:** ⚠️ Benötigt Modernisierung
- **Priorität:** HOCH

#### 3. admin/modules/fields.php
- **Letzte Änderung:** 12.07.2017 (7+ Jahre alt)
- **Status:** ⚠️ Benötigt Modernisierung
- **Priorität:** HOCH

#### 4. admin/modules/rss.php
- **Letzte Änderung:** 12.07.2017 (7+ Jahre alt)
- **Status:** ⚠️ Benötigt Modernisierung
- **Priorität:** HOCH

#### 5. admin/modules/groups.php
- **Letzte Änderung:** 25.01.2018 (7 Jahre alt)
- **Status:** ⚠️ Benötigt Modernisierung
- **Priorität:** HOCH

#### 6. admin/modules/messages.php
- **Letzte Änderung:** 25.01.2018 (7 Jahre alt)
- **Status:** ⚠️ Benötigt Modernisierung
- **Priorität:** HOCH

#### 7. admin/modules/referers.php
- **Letzte Änderung:** 25.01.2018 (7 Jahre alt)
- **Status:** ⚠️ Benötigt Modernisierung
- **Priorität:** HOCH

#### 8. admin/modules/replace.php
- **Letzte Änderung:** 25.01.2018 (7 Jahre alt)
- **Status:** ⚠️ Benötigt Modernisierung
- **Priorität:** HOCH

#### 9. admin/modules/template.php
- **Letzte Änderung:** 25.01.2018 (7 Jahre alt)
- **Status:** ⚠️ Benötigt Modernisierung
- **Priorität:** HOCH

#### 10. admin/modules/uploads.php
- **Letzte Änderung:** 25.01.2018 (7 Jahre alt)
- **Status:** ⚠️ Benötigt Modernisierung
- **Priorität:** HOCH

---

### Mittelalte Module (2021)

#### 11. admin/modules/users.php
- **Letzte Änderung:** 20.10.2021 (4 Jahre alt)
- **Status:** ⚠️ Benötigt Modernisierung
- **Priorität:** MITTEL

#### 12. admin/modules/stat.php
- **Letzte Änderung:** 15.11.2021 (4 Jahre alt)
- **Status:** ⚠️ Benötigt Modernisierung
- **Priorität:** MITTEL

#### 13. admin/modules/modules.php
- **Letzte Änderung:** 17.11.2021 (4 Jahre alt)
- **Status:** ⚠️ Benötigt Modernisierung
- **Priorität:** MITTEL

#### 14. admin/modules/lang.php
- **Letzte Änderung:** 21.11.2021 (4 Jahre alt)
- **Status:** ⚠️ Benötigt Modernisierung
- **Priorität:** MITTEL

---

## Bereits modernisiert (2025)

### ✅ Modernisierte Module (24.-25.11.2025)

1. **admin/modules/favorites.php** - 25.11.2025
2. **admin/modules/comments.php** - 25.11.2025
3. **admin/modules/sitemap.php** - 25.11.2025
4. **admin/modules/changelog.php** - 25.11.2025 (NEU)

### ✅ Bereits aktuell (2025)

5. **admin/modules/security.php** - 25.09.2025
6. **admin/modules/configure.php** - 25.09.2025
7. **admin/modules/admins.php** - 24.11.2025
8. **admin/modules/editor.php** - 24.11.2025
9. **admin/modules/newsletter.php** - 24.11.2025
10. **admin/modules/categories.php** - 24.11.2025
11. **admin/modules/database.php** - 24.11.2025
12. **admin/modules/blocks.php** - 24.11.2025

---

## Modernisierungs-Reihenfolge (Empfohlen)

### Phase 1: Kritische Alte Module (2017-2018)
**Priorität: SEHR HOCH - 10 Module**

1. privat.php (2017)
2. ratings.php (2017)
3. fields.php (2017)
4. rss.php (2017)
5. groups.php (2018)
6. messages.php (2018)
7. referers.php (2018)
8. replace.php (2018)
9. template.php (2018)
10. uploads.php (2018)

**Geschätzter Aufwand:** 10-15 Stunden
**Code-Style:** Sehr alt (7-8 Jahre), func_get_args(), array(), double quotes

---

### Phase 2: Mittelalte Module (2021)
**Priorität: MITTEL - 4 Module**

1. users.php (2021)
2. stat.php (2021)
3. modules.php (2021)
4. lang.php (2021)

**Geschätzter Aufwand:** 4-6 Stunden
**Code-Style:** Alt (4 Jahre), teilweise modernisiert

---

## Erwartete Änderungen pro Modul

Basierend auf favorites.php, comments.php, sitemap.php:

### Code-Modernisierung
- Copyright: aktualisieren auf 2005-2026
- Type Hints: int, string, void für alle Funktionen
- func_get_args() → feste Parameter mit Defaults
- navi_gen() → getAdminTabs()
- tpl_eval() → setTemplateBasic()
- tpl_warn() → setTemplateWarning()
- array() → []
- "$string" → 'string'
- $_POST → getVar()
- include() + end_chmod() → checkConfigFile()
- doConfig() → setConfigFile()

### Sicherheit
- SQL: Prepared Statements prüfen
- Input: Alle getVar() mit Type-Checking
- XSS: Output-Escaping prüfen

---

## Statistik

**Gesamt Admin-Module:** 26
**Modernisiert:** 12 (46%)
**Benötigen Modernisierung:** 14 (54%)

### Nach Alter:
- 2017: 4 Module (15%)
- 2018: 6 Module (23%)
- 2021: 4 Module (15%)
- 2025: 12 Module (46%) ✅

### Nach Priorität:
- SEHR HOCH: 10 Module (38%)
- MITTEL: 4 Module (15%)
- AKTUELL: 12 Module (46%) ✅

---

**Autor:** Eduard Laas  
**Datum:** 25. November 2025  
**Lizenz:** GNU GPL 3  
**Website:** slaed.net
