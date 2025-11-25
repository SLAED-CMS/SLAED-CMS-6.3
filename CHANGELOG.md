# SLAED CMS - Changelog

Alle Änderungen am SLAED CMS System werden hier dokumentiert.

## Format
Dieses Changelog folgt dem [Keep a Changelog](https://keepachangelog.com/de/1.0.0/) Format.

---

## [Unreleased]

### Added (Neu)
- Git-Versionskontrolle initialisiert
- Changelog-Modul für Admin-Panel
- Automatische Changelog-Generierung

### Changed (Geändert)
- Modernisierung: admin/modules/favorites.php
  - Type Hints hinzugefügt
  - getAdminTabs() statt navi_gen()
  - checkConfigFile() für Permissions
  - Array-Kurzschreibweise []
  - getVar() statt $_POST

- Modernisierung: admin/modules/comments.php
  - Optimierte comm_save() Funktion
  - Direkte Wertzuweisung ohne Zwischenvariablen
  - setConfigFile() statt doConfig()

- Modernisierung: admin/modules/sitemap.php
  - Vollständige Code-Modernisierung
  - require_once CONFIG_DIR Pattern
  - Copyright aktualisiert: 2005-2026

### Fixed (Behoben)
- Keine Fehler in dieser Version

---

## [1.0.0] - 2025-11-25

### Added
- Initial Release des modernisierten SLAED CMS
- Admin-Panel mit Modulen:
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
