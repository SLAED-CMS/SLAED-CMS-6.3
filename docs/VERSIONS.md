# Versions

## 8.0.0

The first release of SLAED CMS 8. `setup.php` installs a new site; later releases reach the site through the page
"Обновления" of the panel (`docs/UPDATE.md`).

- **Node** — typed content in one module. A new site gets sixteen types from the shipped profiles, and the panel
  creates more from a profile or from scratch (`docs/NODE.md`).
- **Readable addresses** — every page has one address without an id, and every other form of it answers one 301
  (`docs/ARCHITECTURE.md`, "Address Boundary").
- **Markdown** — texts are written in CommonMark with GFM, beside the SLAED tags (`docs/PARSER.md`).
- **Points, ratings and extra fields** — a points journal, ratings with their rules and fields of a Node type
  (`docs/POINTS.md`, `docs/RATINGS.md`).
- **Signed updates** — a site checks the signature of a release, installs it in steps and can go back
  (`docs/UPDATE.md`).
- **Themes** — `aurora` for the site and `admin` for the panel (`docs/TEMPLATES.md`).
- **Requirements** — PHP 8.4 and newer, MariaDB 10.5.2 or MySQL 8.0.16 and newer.
