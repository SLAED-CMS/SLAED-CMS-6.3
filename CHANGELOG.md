# SLAED CMS - Changelog

Alle Änderungen am SLAED CMS System werden hier dokumentiert.

## Format
Dieses Changelog folgt dem [Keep a Changelog](https://keepachangelog.com/de/1.0.0/) Format.

---

## [Unreleased]

### Added (Neu)
- Keine neuen Features

---

## [1.0.1] - 2025-11-25

### Added
- Git-Versionskontrolle initialisiert (Commit: 842eff4)
- Changelog-Modul für Admin-Panel: admin/modules/changelog.php
- Automatische Changelog-Generierung: generate_changelog.sh
- CHANGELOG.md Dokumentation im Keep-a-Changelog Format

---

## [1.0.0] - 2025-11-24

### Changed (Modernisierungen vom 24.11.2025)

**admin/modules/favorites.php** - Vollständige Modernisierung:
- Copyright aktualisiert: 2005-2017 → 2005-2026
- Type Hints hinzugefügt (int, string, void)
- navi_gen() → getAdminTabs()
- tpl_eval()/tpl_warn() → setTemplateBasic()/setTemplateWarning()
- array() → [] Kurzschreibweise
- Direkte $_POST → getVar() mit Type-Checking
- include()/end_chmod() → checkConfigFile()
- doConfig() → setConfigFile()
- favor_conf_save() optimiert ohne Zwischenvariablen

**admin/modules/comments.php** - Vollständige Modernisierung:
- Copyright aktualisiert: 2005-2017 → 2005-2026
- Type Hints hinzugefügt (int, string, void)
- navi_gen() → getAdminTabs()
- Template-Funktionen modernisiert
- checkConfigFile() Pattern implementiert
- comm_save() optimiert: Direkte Wertzuweisung in Array
- Alle getVar() Aufrufe mit Default-Parametern

**admin/modules/sitemap.php** - Vollständige Modernisierung:
- Copyright aktualisiert: 2005-2022 → 2005-2026
- require_once CONFIG_DIR.'/sitemap.php' Pattern
- Type Hints für alle Funktionen
- navi_gen() → getAdminTabs()
- Template-System modernisiert
- checkConfigFile() für Permission-Checks
- setConfigFile() für Konfiguration
- sitemap_save(): Array-Input mit filter_input() + getVar()
- 20+ Konfigurationsparameter optimiert

### Added
- Initial Release des SLAED CMS
- Admin-Panel mit Modulen:
  - Database Management
  - Categories
  - Comments
  - Favorites
  - Sitemap
  - Editor
  - Newsletter
  - Admins & Blocks
- 4.039 Dateien committet (Commit: d328a9a)
- Mehrsprachige Unterstützung (DE, EN, FR, PL, RU, UA)

---

## [0.9.x] - vor 2025-11-24

### Added
- Alte Version des SLAED CMS (vor Modernisierung)
- Admin-Panel mit Modulen (alte Code-Basis)
  - Database Management
  - Categories
  - Comments
  - Favorites
  - Sitemap
  - Editor
  - Newsletter
  - Admins & Blocks
- 4.039 Dateien committet
- Mehrsprachige Unterstützung (DE, EN, FR, PL, RU, UA)

---

## Legende

- **Added** - Neue Features
- **Changed** - Änderungen an bestehenden Features
- **Deprecated** - Features die bald entfernt werden
- **Removed** - Entfernte Features
- **Fixed** - Bugfixes
- **Security** - Sicherheitsupdates

---

*Generiert am: 2025-11-25*
*Lizenz: GNU GPL 3*
*Autor: Eduard Laas*
*Website: slaed.net*
