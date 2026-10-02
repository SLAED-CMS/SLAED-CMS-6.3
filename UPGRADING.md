# Upgrading SLAED CMS

> **Migration Guide for SLAED CMS**

This document says what this release does with an existing site and what changed for code written against the
previous release.

## Table of Contents

- [Existing Sites](#existing-sites)
- [Breaking and Important Changes](#breaking-and-important-changes)
- [Migration Checklist for Custom Code](#migration-checklist-for-custom-code)
- [Troubleshooting](#troubleshooting)

---

## Existing Sites

> [!IMPORTANT]
> This release installs a new site only. It does not update a site of SLAED CMS 6.2 or of an earlier release:
> no file of the release reads or converts an existing database or configuration, and `setup.php` refuses a site
> that is already installed.

Install the release as a new site, as `README.md` describes, into an empty database or under a table prefix that no
table of the database carries yet. Keep the old site and its backups until the new one is ready.

### Minimum Runtime

- **PHP:** 8.4+
- **Database:** PDO MySQL-compatible server: MariaDB 10.5.2+ or MySQL 8.0.16+, InnoDB tables

### Writable Directories

Typical writable paths:

- `config/`
- `storage/`
- `uploads/`

The installer never changes file permissions. It does not install while `config/db.php`, `config/global.php`,
`config/security.php` or `config/update.php` is not writable by PHP (for one of them that does not exist yet:
`config/` itself).

---

## Breaking and Important Changes

The current codebase confirms these project-level changes in 6.3:

### Node Replaces Nine Content Modules

The modules `news`, `pages`, `faq`, `help`, `jokes`, `content`, `links`, `files` and `media` are gone, with
their blocks, configuration files and tables. Their content is served by Node
(`modules/node`, `core/classes/node/`): one module that runs any number of content types side by side.

- A type keeps the public list address of the module it replaces, `index.php?name=<type>`; a material opens
  at `index.php?name=<type>&op=view&id=<id>` with one global id across all types.
- The installer creates ten active types from `modules/node/profiles/*.json` — the nine replacements
  and `docs` — with the first administrator, and one welcome news item.
- Custom code that read these tables or called helpers of the removed modules must move to `NodeQuery`
  (reads) and `NodeService` (writes).

### Shop, Order and Service Modules Are Not Shipped

The modules `shop`, `clients`, `order`, `money`, `auto_links` and `whois` are not part of the package, nor
their block `blocks/auto_links.php`, configuration files, upload directories and tables.

- The shared subsystems have no scope for them: the shop cart, the shop comments, ratings and favorites, the
  points action `order`, the extra-field area `order`, and the RSS feed, sitemap section, search source and
  newsletter audiences of these modules do not exist.
- `{prefix}_referer` has no `lid`, which tied a referer to a link of `auto_links`, and `config/global.php` has
  no `amod`.
- A site that needs these modules stays on its current release.

### Web Server Rule for Node Upload Directories

Node serves every file of a type through a controlled route, and a type is switched on only when the web
server refuses direct access to `uploads/<type>/` (answer `403` or `404`). Apache and LiteSpeed follow the
`.htaccess` guard Node writes into the directory. nginx ignores `.htaccess` and needs one shared rule:

```nginx
location ~ ^/uploads/([^/]+)/ {
    if (-f $document_root/uploads/$1/.htaccess) { return 403; }
}
```

`storage/` holds logs, backups, the schema files and, while an installation runs, its token, and must never be
served. Apache and LiteSpeed follow its `.htaccess`; nginx needs:

```nginx
location ^~ /storage/ {
    deny all;
}
```

### Points, Ratings and Extra Fields

- Points run on the journal `{prefix}_points`; `{prefix}_users.points` is the balance.
- Ratings live in `{prefix}_rating_targets`, `{prefix}_rating_actors` and `{prefix}_rating_votes`; a vote is a
  POST request. The rules of every target are in `config/ratings.php`.
- Extra fields of accounts and forum posts are named definitions in `config/fields.php` with JSON
  values, handled by the `Field` class; the positional `||` strings of 6.2 are no longer read.

### OAuth2/OIDC Login Added

Built-in OAuth2 Authorization Code Flow with PKCE (Google and Microsoft in V1, no Composer dependencies) replaces the legacy third-party social login integration:

- The tables `{prefix}_user_oauth` (permanent provider links) and `{prefix}_oauth_temp` (one-time state/pending records) hold the provider data; `{prefix}_users` has no `network` column.
- Providers are configured in the admin panel under Users settings (Client ID / Client Secret per provider) or in `config/oauth.php`. The redirect URI to register at the provider console is `https://your-site/index.php?name=account&op=oauth`.
- Accounts created through OAuth have no password (an invalid `!`-prefixed marker is stored); a password can be added later via password recovery.

### Cache And Asset Settings

`config/global.php` holds four page-cache fields: `cache`, `cache_t`, `cache_b`, `cache_l`. Keys not in that set are ignored and disappear on the next save of the settings form.

`cache_b` is the number of days a browser may keep a page, `0` for off. The asset key changes with `ASSETS_VER`, so a rebuilt JS bundle needs nothing cleared by hand. `cache_version` `4` rebuilds `config/local.php` on the first request, and stored pages live under the `pc3` key, which the `cachegc` job cleans up.

`config/local.php` is the merged configuration cache. It is accepted on its version marker alone and is never compared against the source files it was built from. The installer removes it with every configuration file it writes; a hand edit of a file in `config/` needs `rm -f config/local.php`.

### Database Layer

The active database class is `Database` in `core/classes/pdo.php`.

Current method family:

- `getSqlQuery()`
- `getSqlRow()`
- `getSqlRows()`
- `getSqlField()`
- `getSqlRowCount()`

Custom code should use the current `Database` API.

### Input Handling

The current project pattern is to use `getVar()` instead of direct request access:

```php
$id = getVar('post', 'id', 'num');
```

### Password Hashing

Current helpers in the runtime:

- `getPassHash()`
- `checkPassHash()`

### CSRF Tokens

Current helpers:

- `getSiteToken()`
- `checkSiteToken()`

### Content Editors

The pluggable editor layer is active via the `Editor` class (`core/classes/editor.php`). Forms and textareas should output via `Editor::getContent()` or `Editor::getCode()` rather than hardcoded editor initializers. The available editor drivers are bundled under `plugins/editors/`.

A code editor driver of your own must accept a sixth argument: `CodeDriver::getWidget(string $id, string $name, string $value, string $lang, string $profile, string $label)`. The label is the accessible name of the editable area and is never empty; a driver with the old five-argument signature no longer loads. Pass `'label'` to `Editor::getCode()` to name the field.

### Content Parsing

User and administrative content formatting should be passed through the unified `Parser` class (`core/classes/parser.php`), typically accessed via its `filterContent()` method.

### Template Layer

The active file-backed template runtime is `core/classes/template.php`.

New template work should target the modern runtime and theme HTML files under `templates/`.
The modern engine supports automatic CSS and JS injection for components placed in `partials/`: `{% component '<name>' %}` also loads `partials/<name>.css` and `partials/<name>.js` at compile time when those files exist.

When upgrading custom modules:
- Remove subdirectories from your module's `fragments/` logic (e.g. `new/`). The fragment namespace has been strictly flattened. Update `$tpl->getHtmlFrag(...)` calls accordingly.

### Themes

Themes currently present in the repository:

- `admin`
- `lite`

A custom theme directory of an older release has to be reviewed against the structure of these two before it is used.

---

## Migration Checklist for Custom Code

Use this checklist when reviewing custom modules, custom admin code, or local patches.

### Security

- [ ] Replace direct request access with `getVar()` where possible
- [ ] Replace string-built SQL with prepared statements
- [ ] Review state-changing actions for CSRF protection

### Database

- [ ] Update custom DB calls to current `Database` method names
- [ ] Re-test custom queries against the current schema

### Templates

- [ ] Review custom templates against the current theme structure
- [ ] For new template work, prefer the modern `Template` runtime
- [ ] Keep HTML in theme files, never in PHP - `php tools/ui-audit.php --markup` fails on a hardcoded class, inline style or tag

### Configuration

- [ ] Review local config overrides
- [ ] Re-check file permissions after deployment

### Runtime Validation

- [ ] Check `storage/logs/`
- [ ] Test admin login
- [ ] Test frontend entry page
- [ ] Test the modules critical to your site

---

## Troubleshooting

### White Screen / HTTP 500

Check:

- PHP error log
- `storage/logs/`
- web server logs

### Database Connection Errors

Verify:

- database credentials
- database server status
- charset and prefix settings

### Cache Issues

Clear runtime cache:

```bash
rm -rf storage/cache/*
```

### Missing Constants or Language Errors

Review:

- `lang/*.php`
- `admin/lang/*.php`
- module-specific language files

---

## Support

- **Documentation EN:** [slaed.info](https://slaed.info)
- **Documentation DE:** [slaed.de](https://slaed.de)
- **Forum:** [slaed.net/forum](https://slaed.net/index.php?name=forum)
- **Email:** info@slaed.net

---

*SLAED CMS © 2005 - 2026 Eduard Laas. Released under MIT License.*
