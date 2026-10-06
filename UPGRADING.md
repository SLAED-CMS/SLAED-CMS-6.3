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

### Node Upload Directories Under `uploads/node/`

Every Node type keeps its files in `uploads/node/<type>/`; the folders of the modules (`account`, `all`, `avatars`,
`forum`, `presentation`, `voting`) stay in `uploads/`. A site whose type folders still sit at `uploads/<type>/`
moves each of them with its contents before the new files answer a request:

| From | To |
| --- | --- |
| `uploads/content/` | `uploads/node/content/` |
| `uploads/docs/` | `uploads/node/docs/` |
| `uploads/faq/` | `uploads/node/faq/` |
| `uploads/files/` | `uploads/node/files/` |
| `uploads/help/` | `uploads/node/help/` |
| `uploads/links/` | `uploads/node/links/` |
| `uploads/news/` | `uploads/node/news/` |
| `uploads/pages/` of the 6.2 module `pages` | `uploads/node/docs/` |
| `uploads/<type>/` of any other type | `uploads/node/<type>/` |

On a site updated from 6.2 these moves come after the migration of the removed modules in `update.php` and before
the site opens: the migration creates each type with an empty folder and refuses one that already holds a file. It
changes the database alone: it moves, copies and renames no file. It turns a direct address of a file of the 6.2
folder into an `[attach]` of the type with the name unchanged, and where a resource of `files` or `links` names a path
Node refuses it stores a safe spelling and its report names the file to rename to it.

No folder of `uploads/` carries a guard file any more, and the upload service creates a missing folder of its owner
on the first write. No stored text and no setting carries the folder: texts reach their files through `go=file`
and `op=asset` by name, and the default folder of the uploads screen (`dir` in `config/uploads.php`) names a type,
not a path.

### The Document Root Is `public/`

The browser reaches `public/` and nothing else: the entries `index.php`, `admin.php`, `setup.php` and
`update.php`, `.htaccess`, `robots.txt`, `favicon.ico`, `error.html`, the sitemap files, `templates/`, `plugins/`,
`sound/` and `demo/`. `core/`, `modules/`, `admin/` with the body of the panel, `config/`, `storage/` and the whole
`uploads/` stay at the project level. Point the document root of the server at `public/`; where a host cannot move
it, the `.htaccess` of the project rewrites every request into `public/` (Apache and LiteSpeed with `mod_rewrite`
only, and without the module it refuses everything). The server settings and the variants for shared hosting are in
README.md, "Document Root and Web Server". The installer records the mode it found as `webroot` in
`config/global.php`.

No upload lies in the document root. An address `uploads/<folder>/<name>` reaches `index.php`, whose light path
serves a file of a public folder (avatars, `presentation` and `all`) before the core boots and
answers 410 for every other folder and every missing file; the files of a Node type leave only through `go=file` and
`op=asset`, the files of the forum only through `go=file` to a reader of the post that names them, the files of
`uploads/account/` only to the two sides of the private message that names them and to a moderator of `account`, and
the files of a comment on a poll (`uploads/voting/`) or on a profile (`uploads/profile/`) only to a reader of its
poll or profile while the comment is published, and to a moderator of `voting` or `account`. A new `[attach]` in a
comment, Node included, names an own upload or a file the same target already serves; any other is refused.
`update.php` turns a direct address of a file `uploads/forum/` holds in a post, and of a file `uploads/account/` holds
in a private message, into an `[attach]` of the same name; an address the folder does not hold, and an image inside a
link to another site, stay as written and answer 410. Once the old modules are carried into Node, `update.php` points a
direct address any text keeps into the 6.2 folder of a type it does not belong to (`uploads/news/`, `uploads/files/`
and the others) at the `go=file` address of a published material of that type whose text or published comment names
the file, so the reader of that material receives it, and a direct address of `uploads/forum/` outside the forum at
the `go=file` address of a published post that names the file; a name nothing carries keeps its address and answers
410, and a quoted example inside `[code]` stays as written.

`uploads/account/` holds the files of the private messages alone. The signature, the own block and the comments on a
profile upload into `uploads/profile/`; a signature is served from there to whoever may open the profile, an own
block to its owner alone; the mail texts of the panel take their pictures from the public `uploads/all/`. The release
added `profile` to `config/uploads.php`. A signature, an own block, a comment on a profile or a mail text of a 6.2
site that carries an `[attach]` of a file in `uploads/account/` needs that file copied into `uploads/profile/` (a
signature, a block or a comment) or `uploads/all/` (a mail text); those of `slaed.net` carry none. nginx reads no `.htaccess`: the release ships `nginx.conf.example`, whose
`server` block carries the root on `public/`, the prefix `/uploads/` for the front controller, a path after a script
sent to that script and the refusals of `public/.htaccess`.

The site answers only at its folder and its scripts. A path after a script (`/index.php/…`) answers 301 to that
script with the same query, and any other path, an old `*.html` address included, gets the 404 page.

Every `.htaccess` outside `public/` but the one of the project, and every guard `index.html` outside it, are gone:
a folder outside the document root needs none.

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

`config/global.php` holds the cache field `cache`: it switches the parser cache on (`1`) or off (`0`). The `cachegc` job removes cache files not rewritten for a day (`Cache::KEEP`). Styles and scripts are linked one file at a time from `css_f`, `script_f` and the theme package. Keys the settings form does not write are ignored and disappear on its next save.

`cache_version` `4` rebuilds `config/local.php` on the first request.

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
