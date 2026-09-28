# Upgrading SLAED CMS

> **Migration Guide for SLAED CMS**

This document describes the upgrade process of the installer and the repository structure it relies on.

## Table of Contents

- [Before You Upgrade](#before-you-upgrade)
- [Upgrade Files Present in the Repository](#upgrade-files-present-in-the-repository)
- [Recommended Upgrade Flow](#recommended-upgrade-flow)
- [Breaking and Important Changes](#breaking-and-important-changes)
- [Migration Checklist for Custom Code](#migration-checklist-for-custom-code)
- [Troubleshooting](#troubleshooting)

---

## Before You Upgrade

> [!CAUTION]
> Always create a database and file backup before upgrading. The update deletes its own snapshots and the old
> configuration sources once it finishes without an error, so your backup is the only copy of the 6.2 state.

### Database Backup

```bash
mysqldump -u root -p your_database > backup_$(date +%Y%m%d).sql
```

### File Backup

```bash
tar -czf slaed_backup_$(date +%Y%m%d).tar.gz /path/to/slaed/
```

### Minimum Runtime

Confirmed current baseline:

- **PHP:** 8.4+
- **Database:** PDO MySQL-compatible server: MariaDB 10.5.2+ or MySQL 8.0.16+, InnoDB tables

### Review Writable Directories

Typical writable paths:

- `config/`
- `storage/`
- `uploads/`

The installer never changes file permissions. It refuses to start while `config/db.php`, `config/global.php` or
`config/security.php` is not writable by PHP (for one of them that does not exist yet: `config/` itself). Any other
configuration file that cannot be written during a run is reported as a failed step: no mark is set for it, and the
run can be repeated once the permissions are fixed.

---

## Upgrade Files Present in the Repository

The repository currently contains these SQL files under `setup/sql/`:

- `table.sql`
- `insert.sql`
- `table_update4_1.sql`
- `table_update4_2.sql`
- `table_update4_3.sql`
- `table_update5_0.sql`
- `table_update5_1.sql`
- `table_update6_0.sql`
- `table_update6_2.sql`
- `table_update6_3.sql`

> [!NOTE]
> These files are run by the installer only. They carry the placeholders `{prefix}`, `{engine}`, `{charset}` and
> `{collate}`, which `setup.php` fills in, so none of them can be imported with the `mysql` client by hand.

---

## Recommended Upgrade Flow

### 1. Prepare a Clean Copy of the Target Version

```bash
git clone https://github.com/SLAED-CMS/SLAED-CMS-6.3.git slaed-new
```

### 2. Preserve Site-Specific Data

Copy the release over the site. Keep what belongs to the site:

- `config/` - the release ships its own `config/<name>.php`; the settings of a 6.2 site live in
  `config/config_<name>.php` and in `config/db.php`, which the release does not overwrite
- `uploads/`
- any locally maintained templates or theme customizations
- any site-specific generated files that are not part of the repository

A 6.2 site that renamed `admin.php` still holds the 6.2 code under that name. The installer form offers that
name; the installer puts the new `admin.php` in place of the 6.2 file, so the panel keeps its address. Entering
another name moves the new `admin.php` there and removes the 6.2 file. A site on the default name `admin` is
offered a random name instead. A name that belongs to another file of the site root, such as `index` or `setup`,
is refused.

### 3. Run the Installer

The database is created and updated by `setup.php` only. A fresh installation chooses
**New installation**; an existing site chooses the update of its version, see below. The installer fills the
placeholders of the SQL files, runs them and reports every statement.

The form never shows the stored database password: leave the field empty to keep the password of `config/db.php`,
or type a new one. The table prefix may hold Latin letters, digits and `_` (up to 32 characters), the
administration panel filename Latin letters, digits, `_` and `-`; any other value, and an installer choice the
form does not offer, is refused before a file is written. A new installation needs MariaDB 10.5.2+ or MySQL
8.0.16+, as the update does, and a database without a table of its prefix; it is refused otherwise, and it writes
`config/update.php` only when both SQL files ran without a failed statement. A refused run keeps
`config/setup.unlock`, so it can be repeated at once. A new installation that stopped at a failed statement has
already written `config/db.php`, so the installer is locked again: drop the tables of the prefix, put your code
into `config/setup.unlock` and install again.

The installer refuses every branch while `storage/backup/config/marker.json` exists: an unfinished configuration
operation of the site has to be finished on the restore screen of the configuration in the panel first.

### From 6.2 to 6.3: Run the Installer Update

A 6.2 site is updated by the installer, not by importing SQL by hand. An installed site keeps `setup.php` locked:
upload the file `config/setup.unlock` holding a code of your own of at least 8 characters first, then open
`setup.php` and enter that code. Only then does the installer show its form: keep the connection data of the site
and choose **SLAED CMS 6.2 Pro > 6.3 Phoenix**. The first request replaces the code in the file by its password hash,
so a server that hands `config/` out as plain files, as nginx does without rules of its own, reveals no usable code;
keep the code, or upload a new key with a new code. Every run checks the code again before it writes anything, so a
visitor who finds the key in place can neither see the connection data nor start a branch. Five wrong codes in a row
remove the key and lock the installer again: upload a new key. `config/db.php` is not part of the release; the
installer creates it when a site has none.

The update runs in this order and reports every step on its result page:

1. **Preflight**, before any file is written. The server must be MariaDB 10.5.2+ or MySQL 8.0.16+, the
   `users` and `admins` tables of the entered prefix must exist, and the
   tables that take part in transactions (`users`, `admins`, `comment`, `forum`, `order`, `clients`, `favorites`,
   `user_oauth`, `points`, `products`, `rating_targets`, `rating_actors`, `rating_votes`, `categories`,
   `voting`, `newsletter`, `privat`) must be InnoDB. Otherwise the update stops and prints the `ALTER TABLE … ENGINE=InnoDB`
   statements to run; nothing is converted automatically and no file of the site changes.
2. **The site is closed** (`close = 1`). Every configuration file the installer writes removes
   `config/local.php`, so the next request already sees the closed site. It stays closed after the update;
   open it in the settings once the result is checked.
3. **6.2 settings.** Every `config/config_<name>.php` is read and its values go over the new
   `config/<name>.php` (`config_stat.php` into `statistic.php`, `config_seo.php` over `global.php`). The version
   and the asset lists come from the release, a language name becomes its code, a start module, a theme or a
   site logo that no longer exists in the tree falls back to the release value. Upload rules lose their retired
   `adminlist` field, address bans turn their octet mask into CIDR. The sources of the removed modules and of `templ`, `header`,
   `chmod`, `core`, `rewrite` and `rules` have no successor and are not carried. Every old source moves to
   `storage/backup/update/config/`, because the runtime reads every file in `config/`. `config/db.php` of 6.2
   is read as it is and rewritten in the 6.3 format. The language and the site address of 6.2 stay; the
   installer does not take them from its own language choice or from the address it was opened at.
4. **Configuration.** `config/modules.php` is reconciled the way the modules screen does it: records of
   modules that are no longer in the tree are dropped, `node` gets the record of a clean installation.
   A site that still has the 6.2 table `modules` keeps what it stored there: a module switched off, shown to a
   group or placed in blocks stays so, whatever the shipped `config/modules.php` says. Only the first run reads
   that table and switches the `newsletter` job on; it leaves the mark `modules` in `config/update.php`, and a
   repeated run keeps the switches and the job as the owner set them in between.
   The Node types the shipped `config/node.php` carries are removed with their field, upload and rating rules,
   unless the database already registers a type of that name, and the result page names them.
   `config/uploads.php` loses the upload rules of the nine removed modules, `config/scheduler.php` gains
   the `nodepublish` and `nodesync` jobs, `config/newsletter.php` gets the keys it lacks, `config/rss.php`
   gets its three transport limits. Pending newsletter recipients are kept in `storage/backup/update/newsletter/`.
5. **`table_update6_3.sql`**. Any failed statement stops the update here: no data unit runs and no mark is
   written. Correct the cause and run the update again.
6. The data units **points**, **ratings** and **fields**. Each unit keeps a manifest and snapshots under
   `storage/backup/update/<unit>/` and writes its mark to `config/update.php`; a subsystem without its mark
   stays closed for writing.
7. RSS blocks are emptied so the next refresh stores Markdown; the blocks of the removed modules (`news`,
   `pages`, `faq`, `files`, `jokes`, `jokes_random`, `links`, `center`, `center_media`, `center_plus`) are
   switched off and named on the result page; the kept newsletter recipients move into the mail queue, each
   address once per campaign, however often the update runs.
8. When the whole run reports no error, the snapshots are deleted: they hold guest addresses, balances and
   field values. Only the `manifest.json` files stay, and a repeated run skips every finished unit by them.

The **fields** unit checks every stored value of account, forum and order fields before it writes anything.
A select value that no option of the definitions carries becomes a disabled option of its field, and data at a
switched off position becomes an inactive field of that position; the result page counts both. When it cannot
map a value without guessing, it writes nothing and names the table, the row id and the reason, for example
`sport_order 261 (value 10 holds data and has no definition)`. Correct that row in the database and run the
update again: finished units are skipped, the fields unit starts over, and a repeated run changes nothing.

The update creates no Node types and imports no content of the removed modules: their tables, categories
and `uploads/<name>` directories stay as they are. See [Node Replaces Nine Content Modules](#node-replaces-nine-content-modules).

What the update changes in the data, beyond the steps above:

- **Old material addresses answer 404.** The id counter of `{prefix}_nodes` starts above the highest id of the
  nine old tables, so `index.php?name=<type>&op=view&id=N` of 6.2 answers 404 instead of showing a different
  material. There is no map of old ids.
- **Ratings.** Totals stay as starting totals and the last vote time of each visitor stays as the waiting
  period; no individual vote is recreated, and `{prefix}_rating` stays because polls use it. Carried rating rules
  allow guests; an interval of 0 means no waiting. The unit writes nothing when it finds broken data and names
  it: a rating total outside one to five times its vote count (accounts, forum topics, products), a
  `{prefix}_rating` row without an account, a valid address or with a future time, a broken rule, or a missing
  rule for `account`, `forum` or `shop`. Correct it and run the update again. Poll and rating votes of 6.2 are
  kept once per address and target: of two accounts that voted from one address, the earliest vote stays, and the
  result page names the removed rows.
- **Points.** `{prefix}_users.points` stays the balance, rating rewards of 6.2 included; a negative balance becomes
  0, because 6.3 keeps balances unsigned, and the result page counts those accounts. The points switch of 6.2
  becomes the switch of `config/points.php`, whose reward rules start from the release.
- **Extra fields.** Values are carried byte for byte; a `0` stored for an empty select or date becomes empty; a
  field is required only when 6.2 stored exactly `1`; spaces around captions are dropped. A disabled option is
  shown on the page but no longer offered by the form, and an inactive field keeps its values hidden until it is
  switched on and named in the fields settings. The report lists at most 50 rows plus the total. If
  `config/fields.php` is already in the 6.3 format while the tables still hold 6.2 rows, put the 6.2 file back
  and run again.
- **Upload rules and bans.** A carried upload rule gets a guest file limit equal to the user limit; a ban entry
  that is not an IPv4 ban of 6.2 is dropped and named.
- **Administrator rights.** The first run rewrites numeric module rights of administrators as module names.
- **Scheduler.** `maildrain` is added (priority 8, or the lowest free one when 8 is taken), `commentsync` is
  removed, missing `dbbackup` settings are filled.
- **Accounts and comments.** Duplicate user names are renamed to `<name>_<id>`. The schema file stops with a
  message when `{prefix}_privat.time` or `{prefix}_comment.time` holds `NULL` or `{prefix}_comment.reqkey` is
  still hex text; repair those rows and run again.
- **Older 6.2 schemas.** Columns a 6.2 site still carries in an earlier shape (addresses of 15 characters, signed
  counters, empty values stored as `NULL`) are brought to the release; a `NULL` takes the default of its column. On
  MySQL 8 the schema file runs without `NO_ZERO_DATE` and `NO_ZERO_IN_DATE` for its own session, because 6.2 tables
  default dates to zero.
- **Custom SQL on MySQL 8.** `rank` is reserved from MySQL 8.0.2: quote `{prefix}_users.rank` and
  `{prefix}_groups.rank` in backticks in custom queries.
- **Rollback.** Restoring only some files is no rollback: code, schema, configuration and data are restored
  together, and after the site is reopened a restore loses what was written after it.

### 4. Review Configuration

Check the active files in `config/` and verify:

- database connection
- prefixes
- language and site settings
- module-related configuration
- local overrides in `config/local.php`, if used

### 5. Clear Runtime Cache

```bash
rm -rf storage/cache/*
```

`config/local.php` is the merged configuration cache. It is accepted on its version marker alone
and is never compared against the source files it was built from. The installer removes it with every
configuration file it writes; a hand edit of a file in `config/` needs `rm -f config/local.php`.

Additional runtime-generated locations present in the repository:

- `storage/cache/`
- `storage/captcha/`
- `storage/counter/`
- `storage/geoip/`
- `storage/logs/`
- `storage/sitemap/`
- `storage/backup/`

### 6. Verify Entry Points

Check at minimum:

- `index.php`
- `admin.php`
- login flow
- key modules used by your installation
- `storage/logs/` for runtime errors

---

## Breaking and Important Changes

The current codebase confirms these project-level changes in 6.3:

### Node Replaces Nine Content Modules

The modules `news`, `pages`, `faq`, `help`, `jokes`, `content`, `links`, `files` and `media` are gone, with
their blocks, configuration files and tables in `setup/sql/table.sql`. Their content is served by Node
(`modules/node`, `core/classes/node/`): one module that runs any number of content types side by side.

- A type keeps the public list address of the module it replaces, `index.php?name=<type>`; a material opens
  at `index.php?name=<type>&op=view&id=<id>` with one global id across all types.
- A clean installation creates ten active types from `modules/node/profiles/*.json` — the nine replacements
  and `docs` — when the first administrator is created, and one welcome news item.
- An updated site gets no types. Create them in the admin panel under **Node → Types → New type**, from a
  shipped profile or from scratch, then place the Node block `blocks/node.php` in **Blocks** where the switched-off
  blocks of the removed modules stood. A type name is refused while categories of the old module or user files
  in `uploads/<name>` exist; archive, move or delete them yourself first — the update never does.
- The old tables (`{prefix}_news` and the others) are neither read, imported nor dropped. Their
  `config/<name>.php` files are no longer read and may be deleted.
- Custom code that read these tables or called helpers of the removed modules must move to `NodeQuery`
  (reads) and `NodeService` (writes).

### Web Server Rule for Node Upload Directories

Node serves every file of a type through a controlled route, and a type is switched on only when the web
server refuses direct access to `uploads/<type>/` (answer `403` or `404`). Apache and LiteSpeed follow the
`.htaccess` guard Node writes into the directory. nginx ignores `.htaccess` and needs one shared rule:

```nginx
location ~ ^/uploads/([^/]+)/ {
    if (-f $document_root/uploads/$1/.htaccess) { return 403; }
}
```

`storage/` holds logs, backups and the manifests of the update and must never be served. Apache and
LiteSpeed follow its `.htaccess`; nginx needs:

```nginx
location ^~ /storage/ {
    deny all;
}
```

### Points, Ratings and Extra Fields

- Points run on the journal `{prefix}_points`; `{prefix}_users.points` stays the balance and is kept as the
  starting balance by the update.
- Ratings live in `{prefix}_rating_targets`, `{prefix}_rating_actors` and `{prefix}_rating_votes`; a vote is a
  POST request. The rules of every target are in `config/ratings.php`.
- Extra fields of accounts, forum posts and orders are named definitions in `config/fields.php` with JSON
  values, handled by the `Field` class; the positional `||` strings of 6.2 are no longer read.

### OAuth2/OIDC Login Added

Built-in OAuth2 Authorization Code Flow with PKCE (Google and Microsoft in V1, no Composer dependencies) replaces the legacy third-party social login integration:

- New tables `{prefix}_user_oauth` (permanent provider links) and `{prefix}_oauth_temp` (one-time state/pending records) are created by `table_update6_3.sql`; the obsolete `network` column of `{prefix}_users` is migrated and dropped by the same script. Accounts that previously used the legacy social login keep their data and regain access via the standard password recovery ("Forgot password").
- Providers are configured in the admin panel under Users settings (Client ID / Client Secret per provider) or in `config/oauth.php`. The redirect URI to register at the provider console is `https://your-site/index.php?name=account&op=oauth`.
- Accounts created through OAuth have no password (an invalid `!`-prefixed marker is stored); a password can be added later via password recovery.

### Cache And Asset Settings

`config/global.php` holds four page-cache fields: `cache`, `cache_t`, `cache_b`, `cache_l`. Keys not in that set are ignored and disappear on the next save of the settings form.

Action required on upgrade:

- Set `cache_b` to the number of days a browser may keep a page, `0` for off.
- Rebuild the JS bundle: the asset key changes with `ASSETS_VER`, so nothing has to be cleared by hand.

Everything else follows automatically: `cache_version` `4` rebuilds `config/local.php` on the first request, and stored pages live under the `pc3` key, which the `cachegc` job cleans up.

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

If your installation contains custom theme directories not present in the current repository, review them manually before upgrade.

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
- `setup/lang/*.php`
- module-specific language files

---

## Rollback

If the upgrade fails:

1. Restore the database backup.
2. Restore the file backup.
3. Clear `storage/cache/`.
4. Re-check logs before retrying.

---

## Support

- **Documentation EN:** [slaed.info](https://slaed.info)
- **Documentation DE:** [slaed.de](https://slaed.de)
- **Forum:** [slaed.net/forum](https://slaed.net/index.php?name=forum)
- **Email:** info@slaed.net

---

*SLAED CMS © 2005 - 2026 Eduard Laas. Released under MIT License.*
