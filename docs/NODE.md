# Node

Node is the one content module of SLAED. A single `node` module serves any number of content types side by
side and replaces the nine separate content modules `news`, `pages`, `faq`, `help`, `jokes`, `content`,
`links`, `files` and `media`. A type is configuration, not code: its row in `_node_types`, its section in
`config/node.php`, its field definitions, its upload rule and its rating rule. A simple type is created in the
admin panel without PHP; special behaviour comes from a registered extension. Node does not own the shared
subsystems it uses: points (`Point`, [POINTS.md](POINTS.md)), rating (`Rating`, [RATINGS.md](RATINGS.md)),
extra fields (`Field`) and RSS/Atom loading (`Feed`) are independent global classes that serve Node and the
remaining modules alike.

## Goals and boundaries

### What Node provides

- One module instance serves every registered type at once. Types have their own fields, settings, routes
  and templates; there is no separate ACL per type.
- Categories, comments, rating, favorites, users and templates are the existing global subsystems.
- Public reads follow the existing category rights. `NodeQuery` checks read rights and `NodeService` checks
  write rights, so a controller is never the only security boundary.
- Every compound write is one transaction: it completes as a whole or rolls back.
- List pages do not issue queries per row.
- Every material has one global id (`_nodes.id`) and every type one canonical route by its public name. The
  public contract does not depend on ids, URLs, tables or APIs of the replaced modules.
- Themes own all HTML and visual variants; PHP passes facts, data, URLs and semantic flags.

### Replaced modules

`modules/node/profiles/` holds ten type profiles in the `slaed.node` export format. A clean installation
creates ten active types from them; an updated site gets none and creates types in the admin panel, from a
profile or from scratch. The 6.3 update imports no content of the removed modules
(see [The 6.3 update](#the-63-update)); the separate script `update.php` carries it, see
[Migration of the removed modules](#migration-of-the-removed-modules).
Settings of each profile are in [Shipped profiles](#shipped-profiles).

| Profile | Replaces | Extension | `view.mode` | Special behaviour |
|---|---|---|---|---|
| `news` | news | - | `article` | pinning, relations, polls |
| `pages` | pages | - | `article` | the plain material |
| `faq` | faq | - | `faq` | question-and-answer display |
| `help` | help | `support` | `support` | private request, replies, closing |
| `jokes` | jokes | - | `default` | short material, simple form |
| `content` | content | `sync` | `article` | material loaded and refreshed from a feed |
| `links` | links | - | `default` | external addresses and visit statistics |
| `files` | files | - | `files` | several resources, downloads, reports |
| `media` | media | - | `media` | sources, player, metadata |
| `docs` | - | - | `docs` | hierarchical documentation (`parent` relations) |

### What Node does not cover

Accounts, private messages and the forum stay separate subsystems. Node has no own categories or
comments, runs no PHP, SQL or class named in type settings, offers no logic builder or public headless API,
and reads no table of the removed modules at runtime; only the one-off `update.php` does.

### Responsibilities

SLAED owns the site, users, security, top-level routing and themes; Node owns types, materials and their
states, categories, relations, resources and reads; an extension owns only the special behaviour of its
type and never replaces core rights, transactions, writes or reads. A simple type needs no core change and
no table; an extension needs no change of `NodeService` or `NodeQuery`.

## Naming

### Vocabulary

- **Material** (`Node`) - one publication with a global id.
- **Type** - fields, features, routes and views of a group of materials; read rights belong to categories.
- **Field** - a typed extra value of a material, defined in `config/fields.php`.
- **Feature** - standard behaviour the core can switch on for a type (`features.*`).
- **Extension** - a PHP component with special behaviour that no feature covers.
- **Display mode** - `view.mode` of a type: which template set renders it.
- **Public type name** - the unique key of a registered type, used as the `name` route parameter.

One term per concept: `Node` is never also called `Entry`, a type is never called a module inside the core,
and no general API carries the name of a concrete type. The ticket system uses distinct names by role: public
type `help`, extension key `support`, class `NodeSupport`, file `core/classes/node/ext/support.php`, table
`_node_support`, display mode `support`.

### Code naming rules

The general SLAED naming rules apply (PascalCase classes, camelCase verb-noun functions with a fixed verb
set, short lowercase variables). Node adds:

- Class files in `core/classes/node/` are one lowercase word without the `node` prefix (`typeinput.php`).
- Language constants specific to Node use the prefix `_NODE_` (at most 18 characters with the underscore):
  public ones in `modules/node/lang/`, admin ones in `modules/node/admin/lang/`, those of shared admin
  screens in `admin/lang/`. Field type labels are `_FIELDS_BOOL`, `_FIELDS_INT`, `_FIELDS_DECIMAL`.
- No temporary aliases of classes or methods; no `_at` suffix on date columns.

### Public type name

A type name matches `^[a-z][a-z0-9]{0,19}$` and is fixed at creation. `NodeService` refuses:

- the reserved names `node`, `admin`, `index`, `setup`, `core`, `config`, `storage`, `templates`, `plugins`,
  `uploads`, `tools`, `tests`, `vendor` and the Windows device names `con`, `prn`, `aux`, `nul`,
  `com1`-`com9`, `lpt1`-`lpt9`;
- the name of an existing module (in `$conf['modules']` or `modules/<name>/`), except the nine replaced
  names, which a type may take; a registered name always routes to Node, never to an old module;
- a name that already has a section in any of the four configuration areas of a type (see below);
- a name whose directory exists under `uploads/` in a different letter case;
- a name that categories, comments or favorites of an earlier owner still carry (`modul = <name>`);
- an existing `uploads/<name>` directory that holds anything but guard files.

Concurrent creation of the same name is resolved by `UNIQUE KEY name` and the configuration lock. Field
names and `select` option keys use a different grammar, `^[a-z][a-z0-9_]{0,31}$`; once values are stored
under a field name it only changes together with an explicit data conversion. A field may not take the name
of a `_nodes` column.

### Names derived from a type

| Place | Form |
|---|---|
| Route parameter, registry | `name=<name>`, `_node_types.name` |
| Type settings | `$conf['node']['types'][<name>]` in `config/node.php` |
| Field definitions | `$conf['fields']['node'][<name>]` in `config/fields.php` |
| Uploads | rule `$conf['uploads'][<name>]`, place `<name>.attach`, directory `uploads/<name>` |
| Rating | rule `$conf['ratings']['node.<name>']`, scope `node.<name>` |
| Point scope | `node.<name>` |
| Categories, comments, favorites | `modul = <name>` |
| Admin moderation right | key `node-<name>` in `_admins.modules` |
| Editor storage of texts | `nodes.intro`, `nodes.body` with `mod = <name>` |

The admin key `node` is the right to manage Node (types and their settings). The four configuration areas of
a type are `node`, `fields`, `uploads` and `ratings`; creating, importing and deleting a type writes all four
together.

### Database names

Tables are written without `PREFIX_DB` in prose; DDL uses `{prefix}`, runtime code `PREFIX_DB`. Constraints
are named `{prefix}_fk_<table without prefix>_<column or role>` and `{prefix}_chk_<table>_<column>`, so two
installations with different prefixes can share one database.

### Theme names

Large views live in `templates/<theme>/partials/node/`, repeated elements in
`templates/<theme>/fragments/node/`. The base set is `partials/node/list.html`, `partials/node/view.html`,
`fragments/node/card.html`, `block.html`, `image.html`, `gallery.html`, `download.html`, `player.html`,
`link.html` and `tree.html`. A display mode other than `default` may override any base file with
`node/<mode>/<file>`; `getNodeTplName()` picks the mode file when the theme has it and the base file
otherwise. The `lite` theme ships the modes `docs`, `faq`, `files`, `media` and `support`.

## Code layout

```text
core/classes/node/          core: models, contracts, services
core/classes/node/ext/      extensions and their closed factory
modules/node/               public and admin HTTP adapters, language files, profiles
blocks/node.php             the one file block of Node
config/node.php             Node settings and type settings
storage/update/sql/table.sql  the only DDL of the Node tables
```

### Core files

| File in `core/classes/node/` | Component |
|---|---|
| `load.php` | closed class map, `spl_autoload_register()` |
| `entity.php`, `type.php`, `typeinput.php`, `input.php` | `Node`, `NodeType`, `NodeTypeInput`, `NodeInput` |
| `relation.php`, `asset.php`, `target.php`, `context.php` | `NodeRelation`, `NodeAsset`, `NodeTarget`, `NodeContext` |
| `status.php`, `exception.php` | `enum NodeStatus: int`, `NodeException extends RuntimeException` |
| `extension.php` | `interface NodeExtension`, nine methods |
| `query.php`, `service.php`, `view.php` | `NodeQuery` (all reads), `NodeService` (all writes), `NodeView` (template data) |
| `ext/load.php` | `getNodeExtension()`, the closed extension factory |
| `ext/support.php`, `ext/sync.php` | `NodeSupport`, `NodeSync`, both `implements NodeExtension` |

All value objects are `final readonly` with one constructor and public typed properties; the other classes
are `final`. The directory is flat: no `Registry`, `Provider`, `Repository`, `Manager` or `Factory`, no
getters or setters, no subdirectory beside `ext/`.

### The value objects

- `Node` holds one read material in `_nodes` column order - `fields` is the decoded `field`, `pubdate` the
  `published` column - then the requested sets `cids` (int list), `rels` (`NodeRelation[]`), `assets`
  (`NodeAsset[]`) and the joined `uname` and `ctitle`. `null` marks a value not loaded or hidden (`ip` for
  anyone who does not moderate the type). It runs no lazy query.
- `NodeInput` carries full sets: `cid`, `cids`, `aname`, `title`, `intro`, `body`, `fields`, `poll`, `home`,
  `comon`, `pinned`, `pubdate`, `expires`, `rels`, `assets`, `ext` (extension data, not a column). The state
  travels separately.
- `NodeTypeInput` carries `title`, `intro`, `ext`, `sort`, `settings`, `fields`, `uploads`, `rating`; name
  and expected version travel separately. `NodeType` is the registry row followed by the effective
  `settings`, `fields`, `uploads` and `rating`.
- `NodeTarget` is the light target of comments, rating and favorites: `type`, `id`, `uid`, `title`, `comon`,
  `comnum`, `score`, `ratings`; no text, no URL, no query.
- `NodeContext` holds `uid`, `groups`, `aid`, `mods` (moderated type names), `manage`, `super`, `ip`,
  `lang`, `task` (background) and `polls`. Its constructor refuses admin rights without an administrator
  and a background context with any identity. `getNodeContext()` builds it once per request; only the
  scheduler adapters build one with `task = true`. Core classes never read user or admin globals.
- `NodeException` is the only error of the public API. Codes:

| Constant | Value | Meaning |
|---|---:|---|
| `NOTFOUND` | 1 | the material or a related entity does not exist |
| `DENIED` | 2 | the context does not allow the action |
| `INVALID` | 3 | the input, the state or the requested move is not acceptable |
| `CONFLICT` | 4 | the expected version is older than the stored one |
| `STORAGE` | 5 | the storage operation did not complete |
| `LIMITED` | 6 | the public write window `limits.send` of the same address has not passed |

The message is for the log and never reaches a visitor.

### Shared classes Node uses

`Field` (`core/classes/field.php`, stateless; its `InvalidArgumentException` becomes `NodeException::INVALID`),
`Point` (sole owner of `_points` and `_users.points`), `Rating` (sole owner of the rating tables, Node is the
target kind `node.<name>`), `Feed` (safe RSS/Atom loading, limits `rss.bytes`, `rss.timeout`,
`rss.redirects`), `CommentMode` (`core/classes/comment.php`, the modes in `_nodes.comon`), `Cache`,
`FileManager` and `Upload`.

### Loading

Composer is not a runtime dependency. `core/system.php` requires `field.php`, `point.php`, `rating.php` and
`comment.php`, then `core/classes/node/load.php`, whose closed class map loads a Node class only when a
request uses it. It creates `$fld = new Field()` and `$pnt = new Point(...)`; `Point` gets `$conf['points']`
only once `$conf['update']['points']` is `'6.3.0'`, otherwise an empty scope that switches it off. `NodeView`
is created on demand from `Parser` and `Field`; `Feed` is loaded only by its consumers.

The glue in `core/system.php` builds the request objects once: `getNodeContext()`, `getNodeTypeMap()`
(visible types as `name => NodeType`, no query when `config/node.php` lists no type), `getNodeReader()` and
`getNodeWriter()` (bound to the type's extension via `getNodeHandler()`), `getRatingService()`. Around them:
`getNodeTitleMap()`, `getNodeModeType()`, `getNodeTplName()`, `getNodeBlockParam()`, `addNodeMail()`,
`checkUploadModer()` (a Node upload place is moderated by `node-<name>` only), `getAdminNames()` and the
scheduler adapters. `core/admin.php` holds `updateNodeTypePart()`, which saves one part of a type
(`fields`, `uploads`, `rating`, `integrations`) through `NodeService` with the form version.

### Extension loading

`core/classes/node/ext/load.php` holds the closed factory `getNodeExtension()` described in
[Extensions](#extensions); the stored `_node_types.ext` is looked up, never turned into a path, and no shared
subsystem (rating, comments, scheduler) includes files from `modules/node/`.

The scheduler adapters `addNodePublishTask()` and `addNodeSyncTask()` in `core/system.php` run the jobs
`nodepublish` (every minute, `limit` 50, bounded 1-500) and `nodesync` (every five minutes, `limit` 10,
bounded 1-50) of `config/scheduler.php`. A public page view never triggers a sync.

### Module

```text
modules/node/
├── index.php            public entry of every type
├── lang/                de, en, fr, pl, ru, uk
├── profiles/<name>.json the ten shipped profiles, format slaed.node, no PHP
└── admin/
    ├── index.php        admin entry: materials, types, settings
    ├── lang/            de, en, fr, pl, ru, uk
    └── info/ru.md
```

- `modules/node/index.php` routes the public ops `view`, `add`, `asset`, `report` and `support`; an attachment leaves through the file route `go=file` of the core.
- `modules/node/admin/index.php` routes the admin ops for materials (`show`, `add`, `edit`, `status`,
  `delete`, `report`, `support`, `sync`), types (`types`, `type`, `typestatus`, `typedelete`, `clone`,
  `export`, `import`, `remains`), `config` and `info`.
- `addNodeProfiles()` in `core/admin.php` creates the ten profile types for the first main administrator of a clean
  installation, called by `setup.php` and by the recovery form of `admin/index.php`; the new-type form loads a profile by `op=type&profile=<name>`.
- The module holds no `controllers`, `repositories`, `managers`, `factories`, `src` or `sql` directory.
  Controllers hold no SQL or business rules; the core holds no HTML, language strings or type names.
- `blocks/node.php` is the only block of Node; its instance settings are the canonical JSON
  `{"type":…,"mode":…,"limit":…}` in `_blocks.param` (`VARCHAR(255)`), with `mode` `last` or `home` and
  `limit` 1-50.

### Editor, uploads and file delivery

Node has no own editor, uploader or file window. Texts use `getTplTextarea()`; texts and resources use the
place `<name>.attach`, `Upload`, `FileManager` and `uploads/node/<name>`; each `getFileManagerField()` picks one
resource. Files are delivered through `getFileStream()` and `Cache`, authorized by `NodeService::getNodeFile()` as the
`node` adapter of `FileAccess` (docs/ARCHITECTURE.md, "File Delivery Boundary").

### Configuration

`$conf` is read-only for Node; a type write builds the checked package of its four areas from fresh
configuration inside the locked Closure mode of `setConfigFile()` and never writes `config/local.php`.
`config/node.php` has the keys `version` (format), `limits` (`maxassets`, `maxlist`, `syncbatch`, `send`, `edit`),
`support` (the maps `state` and `prio`), `defaults` and `types`; its sections are described with the types.

## Database

### Tables

| Table | Holds |
|---|---|
| `_node_types` | registered types: identity, state, version |
| `_nodes` | every material: main category, author, texts, fields, state, counters |
| `_node_categories` | extra categories of a material |
| `_node_legacy` | old address of a migrated material: removed module and old id |
| `_node_relations` | directed relations between materials |
| `_node_assets` | structured resources of a material |
| `_node_publish` | pending reward of a scheduled publication |
| `_node_support` | queue state of a `support` request |
| `_node_sync` | source and schedule of a `sync` material |

All Node tables are InnoDB; `ascii_bin` below stands for `CHARACTER SET ascii COLLATE ascii_bin`.
`storage/update/sql/table.sql` is the only DDL; `storage/update/sql/table_update6_3.sql` brings a 6.2 site to
the same schema with `CREATE TABLE IF NOT EXISTS`. Both order the Node tables by dependency, not
alphabetically: `_node_types`, `_nodes`, then the other `_node_*` tables; `FOREIGN_KEY_CHECKS` stays on.
There is no `modules/node/sql/`. The rating tables are described in [RATINGS.md](RATINGS.md), the points
journal `_points` in [POINTS.md](POINTS.md).

User, categories and poll are references the application checks, without foreign keys: the global SLAED
tables use `0` for "none".

### `_node_types`

| Column | Type | Meaning |
|---|---|---|
| `id` | `INT UNSIGNED NOT NULL AUTO_INCREMENT` | type id |
| `name` | `VARCHAR(50) ascii_bin NOT NULL` | public name and technical key |
| `title` | `VARCHAR(100) NOT NULL` | display title |
| `intro` | `TEXT NOT NULL` | admin description |
| `ext` | `VARCHAR(50) ascii_bin NOT NULL DEFAULT ''` | extension key, `''` for a standard type |
| `active` | `BOOLEAN NOT NULL DEFAULT 0` | type is available |
| `sort` | `INT UNSIGNED NOT NULL DEFAULT 0` | order in admin lists |
| `version` | `INT UNSIGNED NOT NULL DEFAULT 1` | optimistic version of the type |
| `created` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | creation date |
| `updated` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | last change |

| Key | Columns | Serves |
|---|---|---|
| `PRIMARY` | `id` | lookup by `_nodes.tid` |
| `UNIQUE name` | `name` | the route and technical key |
| `active` | `active, sort, id` | stable list of active types |

- `name` never changes after creation; there is no separate route column.
- `ext` holds only a key of the closed extension map, never a class, file or path. It can be set, replaced
  or cleared only while the type is disabled and has no material.
- A new type is created disabled with version 1 and becomes public only by explicit activation. Activation
  re-checks everything stored and needs a writable `uploads/<name>` that the web server refuses to serve.
- Every change of data, settings, fields, upload or rating rule or activity checks the expected version and
  raises it by one together with `updated`; `config/node.php` mirrors it as `types.<name>.version`. A stale
  form changes neither the row nor any of the four configuration areas. Setting the current activity again
  succeeds without a write.
- Settings live in `config/node.php`, not here. No material counter is cached.
- A type is deleted only when it has no material in any state (the foreign key enforces the same), no
  category and no user file in `uploads/<name>`; the deletion also removes its sections from the four
  configuration areas and the key `node-<name>` from every administrator.

### `_nodes`

| Column | Type | Meaning |
|---|---|---|
| `id` | `INT UNSIGNED NOT NULL AUTO_INCREMENT` | global material id |
| `tid` | `INT UNSIGNED NOT NULL` | type |
| `cid` | `INT UNSIGNED NOT NULL DEFAULT 0` | main category, `0` for none |
| `uid` | `INT UNSIGNED NOT NULL DEFAULT 0` | registered author, `0` for a guest |
| `aname` | `VARCHAR(25) NOT NULL DEFAULT ''` | guest author name |
| `ip` | `VARCHAR(45) ascii_bin NOT NULL DEFAULT ''` | normalized IP of creation |
| `title` | `VARCHAR(100) NOT NULL` | title |
| `intro` | `TEXT NOT NULL` | summary |
| `body` | `MEDIUMTEXT NOT NULL` | full text |
| `field` | `MEDIUMTEXT NOT NULL` | extra field values as a JSON object |
| `poll` | `INT UNSIGNED NOT NULL DEFAULT 0` | attached poll |
| `home` | `BOOLEAN NOT NULL DEFAULT 0` | shown on the home page |
| `comon` | `TINYINT UNSIGNED NOT NULL DEFAULT 0` | `CommentMode`: 0 disabled, 1 moderated, 2 open |
| `pinned` | `BOOLEAN NOT NULL DEFAULT 0` | pinned first |
| `comnum` | `INT UNSIGNED NOT NULL DEFAULT 0` | cached comment count |
| `views` | `INT UNSIGNED NOT NULL DEFAULT 0` | view count |
| `score` | `INT UNSIGNED NOT NULL DEFAULT 0` | sum of rating values |
| `ratings` | `INT UNSIGNED NOT NULL DEFAULT 0` | number of ratings |
| `status` | `TINYINT UNSIGNED NOT NULL DEFAULT 0` | `NodeStatus` |
| `version` | `INT UNSIGNED NOT NULL DEFAULT 1` | optimistic version |
| `created` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | creation date |
| `updated` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | last content change |
| `published` | `DATETIME DEFAULT NULL` | publication date |
| `expires` | `DATETIME DEFAULT NULL` | end of public visibility |

Foreign key: `{prefix}_fk_nodes_type` - `tid` references `_node_types.id`, `ON UPDATE RESTRICT ON DELETE
RESTRICT`.

| Key | Columns | Serves |
|---|---|---|
| `PRIMARY` | `id` | material by global id |
| `pub` | `tid, status, pinned, published, id` | public list of a type, publication order |
| `cat` | `tid, cid, status, pinned, published, id` | list of a main category |
| `home` | `tid, home, status, pinned, published, id` | home page materials |
| `updated` | `tid, status, pinned, updated, id` | order by last change |
| `views` | `tid, status, pinned, views, published, id` | order by views |
| `title` | `tid, status, pinned, title, id` | letter filter and order by title |
| `tree` | `tid, status, id` | `getNodeTree()` batches by id cursor |
| `queue` | `status, created, id` | moderation queue and state lists |
| `author` | `uid, status, published, id` | materials of an author |
| `ip` | `ip, created, id` | source lookup, spam cleanup, the `limits.send` window |
| `poll` | `poll` | materials using a poll |

Rules the indexes rely on:

- There are no single-column indexes on `tid`, `cid` or `status`; the composite keys cover those prefixes.
  There is no index per category-and-sort combination and none for the average rating, which is computed
  from `score` and `ratings`.
- `id` ends every sortable key, so equal dates page stably.
- An ordered read splits each type branch into the disjoint parts `pinned <> 0` and `pinned = 0` and joins
  them with `UNION ALL`, each limited to the end of the page. `pinned` is constant inside a part, so a key
  with `pinned` after `tid, status` delivers rows in the requested order in both directions, and pinning
  first never sorts the whole type. Mixed-type reads join their type branches the same way.
- Public reads compare `status = 2` exactly and add
  `published <= NOW() AND (expires IS NULL OR expires > NOW())`. A moderator of the type reads any state.

Column rules:

- `aname` stays empty when `uid > 0`; the current name comes from `_users.name` as the projection `uname`.
  For a guest it holds the given name.
- `ip` is written at creation, never replaced by a later editor, stored for guests and users alike, and
  returned only to moderators of the type.
- `comnum` is recounted from the global comment table; `score` and `ratings` are aggregates owned by
  `Rating`.
- A material never overrides settings of its type.
- There is no language column: visibility by language follows the language of the main category, and a
  material without category is shown in every language. There are no translation groups.
- Indexed special data of a type is never a new `_nodes` column; it belongs to an extension table.

Version and dates:

- `version` starts at 1. Every content change of the material, its categories, relations or resources
  raises it by one and sets `updated`. The update runs `WHERE id = :id AND version = :ver`; a stale version
  is `NodeException::CONFLICT`, nothing is overwritten, and the editor shows the current row beside the
  submitted values. No form copy is stored in the database.
- Views, comments, ratings, recounts, hits and reports change neither `version` nor `updated`. No column
  uses `ON UPDATE CURRENT_TIMESTAMP`; dates come from the database clock read under the type lock.
- Reaching `expires` ends public visibility without touching `status`, `version` or `updated`; an editor
  changing `expires` is a content change. Moving into `Pending` or `Published` with an `expires` not after
  the publication date is refused.
- `Published` with a future `published` is a scheduled publication; there is no separate state.

### Material states

`enum NodeStatus: int` in `core/classes/node/status.php` owns the stored numbers; application code compares
cases, never numbers.

| Value | Case | Meaning |
|---:|---|---|
| 0 | `Draft` | saved, not submitted |
| 1 | `Pending` | waiting for moderation |
| 2 | `Published` | public, subject to `published` and `expires` |
| 3 | `Disabled` | taken off publication |
| 4 | `Deleted` | in the trash |

Allowed moves (`NodeStatus::checkStatusMove(self $to): bool`):

| From | To |
|---|---|
| `Draft` | `Pending`, `Published`, `Deleted` |
| `Pending` | `Draft`, `Published`, `Deleted` |
| `Published` | `Pending`, `Disabled`, `Deleted` |
| `Disabled` | `Pending`, `Published`, `Deleted` |
| `Deleted` | `Disabled` |

- `NodeContext` and the type's `workflow` narrow the matrix further for a user.
- `checkStatusMove()` answers `false` for the current state. `NodeService::updateNodeStatus()` treats a
  request for the state the material already has as success without a write or version change.
- Restoring from the trash always goes to `Disabled`, so nothing is republished automatically. A disabled
  material is edited in place and then sent to `Pending` or `Published`; there is no `Disabled → Draft`.
- Moving into `Pending` or `Published` requires the required fields and the minimum resources of each role;
  `Draft` and `Disabled` may stay incomplete.
- A move from `Pending` to `Published` rewards the approving moderator with the Point action `moderate`,
  unless the material is the moderator's own.

Trash and deletion:

- The trash is the `Deleted` state: no trash table, no deletion date. Trashed materials leave every public
  read; their relations stay.
- Physical deletion is the explicit admin operation `NodeService::deleteNode()` at the expected version:
  one transaction removes extension rows, comments (compensating their awards), the publication award,
  favorites and the row; the other `_node_*` rows follow by cascade. Files stay.

### Extra fields: `_nodes.field`

Node uses the global field system; there are no field tables. Definitions live in `config/fields.php` under
`node/<name>` (at most 256 per set). `_nodes.field` stores one JSON object of named values, keys sorted by
name, at most 1048576 bytes, checked before SQL: strings for `text`, `textarea`, `select`, `email`, `url`;
`true`/`false` for `bool`; an integer for `int`; an exact decimal string for `decimal`; `YYYY-MM-DD` for
`date`; `YYYY-MM-DD HH:MM:SS` for `datetime`; an array for a multiple `select`. Empty string and empty array
are not stored for an optional field. An inactive field keeps its value; a key without a current definition
is dropped at the next content change. A value that must be filtered, sorted, aggregated, unique or searched
often is a column or indexed extension data, not a field.

### `_node_categories`

| Column | Type | Meaning |
|---|---|---|
| `id` | `INT UNSIGNED NOT NULL AUTO_INCREMENT` | link id |
| `nid` | `INT UNSIGNED NOT NULL` | material |
| `cid` | `INT UNSIGNED NOT NULL` | extra category |

| Key | Columns | Serves |
|---|---|---|
| `PRIMARY` | `id` | |
| `UNIQUE node` | `nid, cid` | extra categories of a material, logical uniqueness |
| `cat` | `cid, nid` | materials of an extra category |

Foreign key `{prefix}_fk_node_categories_node`: `nid` references `_nodes.id`, `ON UPDATE RESTRICT ON DELETE
CASCADE`. There is no foreign key to `_categories`.

- `_nodes.cid` is the only source of the main category, which decides language and main route. The table
  holds extra categories only: the service drops the main category and `0` from the input list.
- The service checks that every category exists, belongs to the type (`_categories.modul = <name>`) and, for
  a writer who does not moderate the type, grants view and post rights and is shared or of the context language.
- The main category and the full set of extra ones are saved in the material's transaction.
- A category read is two disjoint branches joined by `UNION ALL`: `cid = X` through `_nodes.cat` in sort
  order, and `cid <> X` joined through `_node_categories.cat`. Neither branch can return a material twice,
  so page and count stay correct even if a main category appears among the extra ones.
- A category is not deleted while any material in any state, the trash included, has it or a category of
  its subtree as main category; materials must first move to another main category. Deleting a category
  subtree removes its extra links and raises the version of each affected material.

### `_node_relations`

| Column | Type | Meaning |
|---|---|---|
| `id` | `INT UNSIGNED NOT NULL AUTO_INCREMENT` | relation id |
| `nid` | `INT UNSIGNED NOT NULL` | source material |
| `rid` | `INT UNSIGNED NOT NULL` | target or parent material |
| `type` | `VARCHAR(50) ascii_bin NOT NULL` | relation kind |
| `sort` | `INT UNSIGNED NOT NULL DEFAULT 0` | order |
| `created` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | creation date |

| Key | Columns | Serves |
|---|---|---|
| `PRIMARY` | `id` | |
| `UNIQUE edge` | `nid, type, rid` | one edge per kind |
| `source` | `nid, type, sort, rid` | outgoing relations in order |
| `target` | `rid, type, sort, nid` | incoming relations, children of a parent |

Constraints: `{prefix}_fk_node_relations_node` (`nid`) and `{prefix}_fk_node_relations_related` (`rid`) both
reference `_nodes.id` `ON DELETE CASCADE`; `{prefix}_chk_node_relations_self` is `CHECK (nid <> rid)`.

- Relations are directed; a reverse edge is a separate row written in the same transaction when needed.
- The kinds are `related` (needs the feature `related`) and `parent` (needs the feature `tree`). An input
  relation carries only `rid`, `type` and `sort`.
- `parent` allows one parent, within the same type, without cycles; no parent means a root. Relations across
  types are refused.
- Similar materials and previous/next are computed by query, never stored.

Tree changes serialize on the type row. Every material write takes `SELECT ... FOR UPDATE` on its
`_node_types` row before it reads or locks any material, then re-checks the type, the current relations,
the rights and the expected version. The ancestor walk from the new parent uses locking reads: reaching the
material itself is a cycle (`INVALID` on `rels.parent`), meeting a node twice is a damaged tree (`STORAGE`,
logged). A chain loaded before the lock is never proof that no cycle exists: two concurrent moves may each
look valid on an old snapshot, and the second fails once it re-reads the first. Plain tree reads take no lock.

### `_node_assets`

| Column | Type | Meaning |
|---|---|---|
| `id` | `INT UNSIGNED NOT NULL AUTO_INCREMENT` | resource id |
| `nid` | `INT UNSIGNED NOT NULL` | material |
| `kind` | `VARCHAR(16) ascii_bin NOT NULL` | `file`, `image`, `audio` or `video` |
| `role` | `VARCHAR(50) ascii_bin NOT NULL` | role registered in the type |
| `src` | `VARCHAR(2048) NOT NULL` | normalized relative path or allowed external URL |
| `name` | `VARCHAR(255) NOT NULL DEFAULT ''` | download file name |
| `title` | `VARCHAR(100) NOT NULL DEFAULT ''` | public title |
| `intro` | `TEXT NOT NULL` | caption |
| `mime` | `VARCHAR(100) ascii_bin NULL DEFAULT NULL` | checked MIME type |
| `size` | `BIGINT UNSIGNED NULL DEFAULT NULL` | bytes |
| `width` | `INT UNSIGNED NULL DEFAULT NULL` | pixels |
| `height` | `INT UNSIGNED NULL DEFAULT NULL` | pixels |
| `duration` | `INT UNSIGNED NULL DEFAULT NULL` | seconds |
| `hits` | `INT UNSIGNED NOT NULL DEFAULT 0` | allowed download starts or `link` visits |
| `reported` | `DATETIME NULL DEFAULT NULL` | date of the first open report |
| `ruid` | `INT UNSIGNED NOT NULL DEFAULT 0` | registered author of that report, `0` for a guest |
| `sort` | `INT UNSIGNED NOT NULL DEFAULT 0` | order within the role |
| `created` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | added to the material |
| `updated` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | last content change of the resource |

| Key | Columns | Serves |
|---|---|---|
| `PRIMARY` | `id` | read, counter, delete |
| `node` | `nid, role, sort, id` | resources of a material or of one role in order |
| `report` | `reported, id` | admin queue of open reports |
| `src` | `src(191)` | narrows candidates for the `link` duplicate check |

Constraints: `{prefix}_fk_node_assets_node` (`nid` → `_nodes.id`, `ON UPDATE RESTRICT ON DELETE CASCADE`),
`{prefix}_chk_node_assets_kind` (`kind IN ('file','image','audio','video')`),
`{prefix}_chk_node_assets_role` (`role <> ''`), `{prefix}_chk_node_assets_src` (`src <> ''`).

- A material may hold many resources; `limits.maxassets` and the per-role `min`/`max` limit them, not the
  schema. `role` is checked against the type's roles, whose display mode (`image`, `gallery`, `download`,
  `player`, `none`, `link`) decides the allowed kinds.
- A checked `src` is either a relative local path or an absolute URL; there is no "external" flag.
  Metadata is read once when a local file is added or replaced; public output never touches the file system
  per row or queries an external server. Unknown metadata stays `NULL`.
- `NodeInput::$assets` is a full set of items with exactly `id`, `kind`, `role`, `src`, `name`, `title`,
  `intro`, `sort`; `id = null` is new, a positive id an existing row of the same material. A resource
  missing from the set loses its row. A row never owns its file: no cascade or set change deletes files,
  and an editor `[attach=...]` creates no row.
- Resource changes go through `NodeService` at the current `_nodes.version` and raise it; `hits` moves by
  one atomic statement without version or date change.
- The same `src` may appear in different materials and roles, except that an external address of a `link`
  role is unique within a type. There is no `UNIQUE` on `src`; the service checks under the type lock by an
  exact binary comparison, using the `src` prefix key only to narrow candidates.
- Reports: the first report sets `reported` and `ruid`, a repeat changes nothing. A moderator decides it as
  useful or rejected on the locked row; a useful report of a registered author other than the moderator
  creates the Point event `report`, the moderator gets `moderate` unless the report is his own, and both
  columns are cleared in the same transaction. A report changes neither the material's state, `version` and
  `updated` nor `_node_assets.updated`; replacing the file clears the report and resets `hits`.

### `_node_legacy`

| Column | Type | Meaning |
|---|---|---|
| `modul` | `VARCHAR(50) ascii_bin NOT NULL` | name of the removed module the material came from |
| `oid` | `INT UNSIGNED NOT NULL` | id the material had in that module |
| `nid` | `INT UNSIGNED NOT NULL` | the migrated material |

Keys: `PRIMARY (modul, oid)`, `node (nid)`. Foreign key `{prefix}_fk_node_legacy_node` (`nid` → `_nodes.id`,
`ON UPDATE RESTRICT ON DELETE CASCADE`).

- Only `update.php` writes rows, in the transaction of the module it carries, for every material its module had
  published; a submission it had not approved had no public page and gets none. No Node operation writes here, and a
  physically deleted material takes its old address with it, so that address answers 404.
- `NodeQuery::getNodeLegacy()` reads it, and only on the way to a 404: see [Response codes](#response-codes).
- A material with a row here earns no Point award `publish`, neither at once nor from a publication job: its removed
  module rewarded the publication and the 6.3 update carried that balance. `NodeService` asks the table only on the way
  to that award.

### `_node_publish`

| Column | Type | Meaning |
|---|---|---|
| `nid` | `INT UNSIGNED NOT NULL` | material, primary key |
| `published` | `DATETIME NOT NULL` | publication date the job was created for |
| `due` | `DATETIME NOT NULL` | next attempt |

Keys: `PRIMARY (nid)`, `queue (due, nid)`. Foreign key `{prefix}_fk_node_publish_node` (`nid` →
`_nodes.id`, `ON UPDATE RESTRICT ON DELETE CASCADE`).

The table delivers the Point reward `publish` of a scheduled publication; at most one row per material.

- A move into `Published` with a future date adds or replaces the job in the material's transaction; moving
  the future date updates both dates; leaving `Published` or deleting cancels it. An immediate publication
  removes any job and rewards in the owner's transaction. Editing an already published material does not
  reward again; moving a pending job's date into the past is handled as a publication that has come.
- `nodepublish` runs `NodeService::updateNodePublishList(int $limit = 50): array` (background context
  only) over jobs with `due <= NOW()` of active types, one transaction per job, locking type, material and
  job in that order and re-reading them. A job whose material left `Published` or whose date differs is
  deleted without reward; one whose date has not come gets `due = published`; an author without account
  gets nothing. `Point::addEvent()` and the job deletion commit together, so a retry never doubles a
  balance. A recoverable Point refusal moves `due` to `NOW() + 60 seconds`; a closed or invalid points
  configuration delivers without reward and logs it.

### `_node_support`

Used only by a type with the extension `support`. The request itself - author, category, text - stays in
`_nodes`, replies are comments in `_comment`; this table holds only the queue state.

| Column | Type | Meaning |
|---|---|---|
| `id` | `INT UNSIGNED NOT NULL AUTO_INCREMENT` | row id |
| `nid` | `INT UNSIGNED NOT NULL` | request |
| `aid` | `INT UNSIGNED NOT NULL DEFAULT 0` | assigned administrator, `0` for none |
| `state` | `TINYINT UNSIGNED NOT NULL DEFAULT 0` | 0 waits for staff, 1 waits for author, 2 closed |
| `prio` | `TINYINT UNSIGNED NOT NULL DEFAULT 1` | 0 low, 1 normal, 2 high, 3 urgent |
| `version` | `INT UNSIGNED NOT NULL DEFAULT 1` | version of the working card |
| `activity` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | last significant activity |

| Key | Columns | Serves |
|---|---|---|
| `PRIMARY` | `id` | |
| `UNIQUE node` | `nid` | one row per request |
| `queue` | `state, prio, activity, id` | the shared queue |
| `admin` | `aid, state, activity, id` | the queue of one assignee |

Constraints: `{prefix}_fk_node_support_node` (`nid` → `_nodes.id`, `ON UPDATE RESTRICT ON DELETE CASCADE`),
`{prefix}_chk_node_support_state` (`state <= 2`), `{prefix}_chk_node_support_prio` (`prio <= 3`).

- The value maps live only in `config/node.php` as `support.state` (`staff`, `author`, `closed`) and
  `support.prio` (`low`, `normal`, `high`, `urgent`); they must match the check constraints.
- A new request needs a registered owner and open comments; its row is created in the material's
  transaction as unassigned, normal priority, waiting for staff.
- The first publication of a reply, inside the comment transaction: a reply of the owner sets "waits for
  staff", any other reply "waits for author"; `activity` and `version` move and the other side is notified.
  A closed request accepts no replies but stays readable to its owner and moderators.
- `activity` moves on creation, reply, closing and reopening, not on a new assignee or priority. `version`
  moves on every change of `aid`, `state` or `prio`; queue changes never touch `_nodes.version` or `updated`.
- There is no foreign key to `_admins`: deleting an administrator never blocks on a request; a missing
  `aid` reads as unassigned.

### `_node_sync`

Used only by a type with the extension `sync`. Material, title, text, state and version stay in `_nodes`;
the table holds one external source per material and the state of its background check.

| Column | Type | Meaning |
|---|---|---|
| `id` | `INT UNSIGNED NOT NULL AUTO_INCREMENT` | row id |
| `nid` | `INT UNSIGNED NOT NULL` | material |
| `url` | `VARCHAR(2048) NOT NULL` | normalized RSS or Atom address |
| `refresh` | `INT UNSIGNED NOT NULL DEFAULT 3600` | seconds between checks, `0` for manual only |
| `due` | `DATETIME NULL DEFAULT NULL` | next automatic check, `NULL` when `refresh = 0` |
| `checked` | `DATETIME NULL DEFAULT NULL` | last finished attempt |
| `synced` | `DATETIME NULL DEFAULT NULL` | last real change of the text |
| `etag` | `VARCHAR(255) NOT NULL DEFAULT ''` | last accepted `ETag` |
| `modified` | `VARCHAR(100) ascii_bin NOT NULL DEFAULT ''` | last accepted `Last-Modified` |
| `fails` | `SMALLINT UNSIGNED NOT NULL DEFAULT 0` | consecutive failures |
| `error` | `VARCHAR(255) NOT NULL DEFAULT ''` | last short safe error |

| Key | Columns | Serves |
|---|---|---|
| `PRIMARY` | `id` | |
| `UNIQUE node` | `nid` | one source per material |
| `due` | `due, id` | the automatic queue |

Constraints: `{prefix}_fk_node_sync_node` (`nid` → `_nodes.id`, `ON UPDATE RESTRICT ON DELETE CASCADE`),
`{prefix}_chk_node_sync_refresh` (`refresh = 0 OR refresh BETWEEN 300 AND 31536000`).

- The automatic queue takes rows with `due <= NOW()` of an active type with extension `sync` whose material
  is not `Deleted`; a disabled material keeps updating, a disabled type does not. `refresh = 0` leaves only
  the manual admin run (`op=sync`).
- `etag` and `modified` go out as conditional headers; `304` is a successful check without change.
- The network request runs before the transaction; row, URL and material version are re-checked before
  writing, so a stale download never overwrites a concurrent change.
- An unchanged answer updates validators, `checked`, `due`, clears `fails` and `error`, and leaves the
  material alone. A changed text is stored in `_nodes.body` with a new version and `updated`, and sets `synced`.
- A failure keeps the text, raises `fails`, stores a safe message and, when `refresh > 0`, sets `due` to
  `checked + 300 × 2^(fails−1)` seconds, at most one day.
- `NodeSync` hooks write the row in the material's transaction; the cascade is only a safety net.

### Write discipline

Every Node write follows [the single lock order](#the-single-lock-order). A type row that changed version since
the request read it is a `CONFLICT`, because the input was checked against the older settings.

### Administrator rights column

`_admins.modules` is `TEXT NOT NULL`, a comma-separated list of module names, `node` and `node-<name>`
keys; there is no rights table. The admin form accepts only keys of real modules and registered types.

## Core API

`NodeQuery` is the only reader of Node and `NodeService` the only writer. Modules, blocks, feeds, the sitemap,
comments, rating, favorites and search never run SQL against the Node tables. Rules a maintainer must keep:

- A read never exposes SQL, rows or the reason of a refusal. A write always runs rights, validation and one
  transaction.
- No public method branches on a type name; behaviour comes from type settings and the type's extension.
- Input is typed (`NodeInput`, `NodeTypeInput`), never a free array. IDs, counters, audit dates and computed values
  are assigned by the service.
- The request context is an explicit `NodeContext`; the core never reads `$user`, `$locale` or the client address.
- Settings come from the loaded `$conf` (`node`, `fields.node`, `uploads`, `ratings`), never copied or changed.
- Types are never copied into the registries of global subsystems; those pass the type name and the global
  material ID and resolve them through `NodeQuery`.

### Classes

The classes live in `core/classes/node/` (`Node` in `entity.php`, the others in the lower-case file of their
suffix: `query.php`, `service.php`, `typeinput.php` …) and load through the closed autoload map of `load.php`:
an exact class name leads to a known file, no path is built from the name. `getNodeExtension()`, `NodeSupport` and
`NodeSync` live in `ext/`.

### Models

All models are `final readonly` with public constructor properties: no getters, setters, factories or lazy
loading, and no change after construction.

```php
final readonly class Node {
    public function __construct(
        public int $id, public int $tid, public int $cid, public int $uid, public string $aname, public ?string $ip,
        public string $title, public string $intro, public ?string $body, public ?array $fields, public int $poll,
        public bool $home, public CommentMode $comon, public bool $pinned, public int $comnum, public int $views,
        public int $score, public int $ratings, public NodeStatus $status, public int $version,
        public string $created, public string $updated, public ?string $pubdate, public ?string $expires,
        public ?array $cids, public ?array $rels, public ?array $assets, public ?string $uname, public ?string $ctitle
    ) {}
}
```

- Properties follow `_nodes`; `pubdate` is `_nodes.published`, `fields` the decoded `field` JSON. Dates are
  canonical database strings `YYYY-MM-DD HH:MM:SS`.
- `null` in `body`, `fields`, `cids`, `rels`, `assets` means "not loaded by this projection"; `''` or `[]` means
  loaded and empty. `cids` is `int[]` of extra categories (the main one excluded), `rels` `NodeRelation[]`,
  `assets` `NodeAsset[]`.
- `ip` is `null` unless the context moderates the type. `uname` is the current `_users.name` of a registered
  author, `ctitle` the raw main category title; both come from joins of the main statement or are `null`. `aname`
  is the stored name of a guest author only.
- A row with an unknown `status` or `comon` is logged with its `nid` and skipped by the reader (`STORAGE` for the
  writer); stored field values that are not a JSON object read as `[]` and are logged.

```php
final readonly class NodeType {
    public function __construct(
        public int $id, public string $name, public string $title, public string $intro, public string $ext,
        public bool $active, public int $sort, public int $version, public string $created, public string $updated,
        public array $settings, public array $fields, public array $uploads, public array $rating
    ) {}
}
```

- `id` … `updated` come from `_node_types`; `settings` is the effective configuration (without `version`),
  `fields` the checked `fields.node.<name>`. `uploads` is `$conf['uploads'][<name>]` as the twelve pieces of
  `NodeQuery::UPLOAD`; `rating` is `$conf['ratings']['node.<name>']` (string keys `active`, `period`, `detail`,
  `guests`). A missing or broken rule gives `[]`, blocks only that subsystem and is logged.

```php
final readonly class NodeTarget {
    public function __construct(
        public NodeType $type, public int $id, public int $uid, public string $title,
        public CommentMode $comon, public int $comnum, public int $score, public int $ratings
    ) {}
}

final readonly class NodeRelation {
    public function __construct(public int $id, public int $nid, public int $rid, public string $type, public int $sort, public string $created) {}
}

final readonly class NodeAsset {
    public function __construct(
        public int $id, public int $nid, public string $kind, public string $role, public string $src, public string $name,
        public string $title, public string $intro, public ?string $mime, public ?int $size, public ?int $width,
        public ?int $height, public ?int $duration, public int $hits, public ?string $reported, public int $ruid,
        public int $sort, public string $created, public string $updated
    ) {}
}
```

- `NodeTarget` has no text, sets or URL. `uid` is the stored author (Rating refuses a vote on one's own material
  by it); `0` never proves guest ownership.
- `NodeRelation` and `NodeAsset` mirror their rows. In a resource, unknown metadata and an absent report are `null`;
  `src` is a path under `uploads/node/<type>` or an external URL; `ruid` is the author of the open report or `0`.

```php
final readonly class NodeInput {
    public function __construct(
        public int $cid, public array $cids, public string $aname, public string $title, public string $intro,
        public string $body, public array $fields, public int $poll, public bool $home, public CommentMode $comon,
        public bool $pinned, public ?string $pubdate, public ?string $expires, public array $rels,
        public array $assets, public array $ext
    ) {}
}

final readonly class NodeTypeInput {
    public function __construct(
        public string $title, public string $intro, public string $ext, public int $sort,
        public array $settings, public array $fields, public array $uploads, public array $rating
    ) {}
}
```

Constructors fix only top-level types; `NodeService` checks the values. `cids`, `rels`, `assets`, `fields` are full
desired sets (`[]` clears, no partial update); `ext` is the extension input, never stored in `_nodes`. A resource
element has exactly `id` (`null` creates, a positive ID names an own resource), `kind`, `role`, `src`, `name`,
`title`, `intro`, `sort`; a relation element exactly `rid`, `type` (`related` or `parent`), `sort`.

### NodeStatus

```php
enum NodeStatus: int { case Draft = 0; case Pending = 1; case Published = 2; case Disabled = 3; case Deleted = 4; }
public function checkStatusMove(self $to): bool
```

Application code never compares stored states with numbers. The move matrix is private and listed in
[Material states](#material-states); `checkStatusMove()` is its only check and answers `false` for the current
state. What a context may do is decided by `NodeService`.

### NodeContext

```php
final readonly class NodeContext {
    public function __construct(
        public int $uid, public array $groups, public int $aid, public array $mods, public bool $manage,
        public bool $super, public string $ip, public string $lang, public bool $task = false, public bool $polls = false
    ) {}
}
```

`uid` is the site user (`0` guest), `groups` the unique IDs of all effective groups, `aid` the separate
administrator (`0` without one; never replaced by `uid`), `mods` the public type names the administrator moderates
(without `node-`), `manage` the right to manage Node and its types, `super` the main administrator, `ip` the client
address, `lang` the category language filter (`''` turns it off), `task` a trusted background context without
identity, `polls` the right to delete shared polls.

The constructor transforms nothing and throws `NodeException::INVALID` for a negative `uid`/`aid`, `groups` that is
not a list of unique positive ints, `mods` that is not a list of unique names `^[a-z][a-z0-9]{0,19}$`, `manage`,
`super`, `polls` or `mods` without `aid`, and `task` together with any identity or right.

A type is moderated by `super` or by its name in `mods`. Moderation, not `manage`, gives rights on the materials,
resources and reports of a type; category read rights still use `uid` and `groups`.

`getNodeContext()` (`core/system.php`) builds the one snapshot per request: `groups` by one SQL over `_users` and
`_groups`; `super` from `isAdmin(true)` (also sets `manage`, `polls`); otherwise one read of `_admins.modules`:
`node` sets `manage`, `voting` sets `polls`, `node-<name>` adds to `mods`. `lang` is `$locale` when
`multilingual` is on. Nothing comes from the request, `task` stays `false`, a failed SQL is `STORAGE`. Only the
scheduler adapters build `task = true`; it permits the publication queue and batch sync and lifts no read rule.

### NodeView

```php
public function __construct(Parser $prs, Field $field)
public function getNodeView(NodeType $type, Node|NodeTarget $node, string $mode): array
```

Prepares one accessible object for one closed mode without SQL, request reads, HTML building or template calls;
only `intro_html` and `body_html` carry HTML, rendered by `Parser::filterContent()` in safe mode with the material
ID, so only `[usehtml]`/`[usephp]` content is trusted and attachments use the controlled file route `go=file`.
Assets never expose `src`, `reported` or `ruid`. The modes and the exact keys are in
[Rendering: NodeView](#nodeview-keys).

### Extensions in the core

The interface (`filterNodeConfig`, `filterNodeData`, `getNodeScope`, `checkNodeAction`, `updateNodeAction`,
`addNodeData`, `updateNodeData`, `deleteNodeData`, `getNodeData`) and the factory
`getNodeExtension(string $key, Database $db, NodeContext $context): ?NodeExtension` (closed map `support`, `sync`;
`''` gives `null`, an unknown key `INVALID`) are described with the extensions. Core guarantees: a reader or writer
refuses an extension that is not exactly the factory class for `NodeType::$ext` (`INVALID extension`);
`getNodeScope()` must answer exactly `join`, `where`, `params`, and each bound value is renamed to a placeholder
unique per branch and occurrence (`<branch>x<name>o<n>`); the scope narrows every read before counting and paging;
write hooks run inside the `NodeService` transaction and roll back with it.

### NodeQuery

```php
public function __construct(Database $db, NodeContext $context, Field $field)
```

Every read is decided by the instance context. Assembled types and category maps live for the instance (one
request); nothing goes to a persistent or process cache.

#### Type reads

```php
public function getNodeType(string $name): ?NodeType
public function getNodeTypeList(): array
public function filterNodeSettings(string $ext, array $settings, array $fields): array
public function getNodeTypeExport(string $name): string
```

- `getNodeType()` answers `null` for a bad name, a missing, invalid or held type, and a disabled type for a context
  that is not `super`, `manage` or its moderator. `getNodeTypeList()` reads all types with one statement and
  returns those the context may receive, ordered by `sort`, `id`. Repeated calls use the instance memory.
- A type is valid only when row and configuration carry the same `name` and `version` and everything passes. On a
  mismatch the reader takes one fresh `getConfig()` snapshot (without touching `$conf`), which is how a type written
  earlier in the request is read back; a lasting mismatch invalidates the type. A type named in the marker of an
  unfinished configuration operation (`BACKUP_DIR/config/marker.json`) is held (`null`) until restored. Failures
  are logged as `Node: …` with the name and the path of the first error.
- `filterNodeSettings()` is the one settings validator, for stored types and before every type write: it merges the
  stored differences over `node.defaults` (maps by key, lists whole), checks the closed sections (`list`, `view`,
  `form`, `workflow`, `admin`, `features`, `assets`, `integrations`, `ext` via the extension) and refuses a field
  named after a `_nodes` column. It returns the effective settings or throws `INVALID <path>`. The read of a stored
  type runs the same check and narrows two switches a later rule closed (see Effective settings).
- `getNodeTypeExport()` needs `manage` or `super` (`NOTFOUND`, `INVALID export` for a broken upload or rating rule)
  and returns pretty-printed JSON `{"format": "slaed.node", "version": 1, "type": {…}}` with exactly the keys of
  `EXPORT`: `name`, `title`, `intro`, `ext`, `sort`, `settings`, `fields`, `uploads`, `rating`.

Public constants shared with the writer: `TREEPART = 500`, `KINDS`, `RMODES` (kinds each role mode shows),
`ROLEDEF` (role keys with defaults), `UPLOAD`, `FORMAT = 'slaed.node'`, `EXPORT`, `MODES` (display modes of `view.mode`).

#### Read rights

| Read | Methods | State and dates | Category right |
|---|---|---|---|
| item | `getNode`, `getNodeContent`, `getNodeAsset`, targets with `any` | moderator any state and date; others published in window | PHP check of the joined row |
| target | `getNodeTarget`, `getNodeTargetList` | published in window | PHP check of the joined row |
| list | `getNodeList`, `getNodeCount`, `getNodeTree`, `getNodeAuthorStat` | per `setNodeStatus()` | SQL predicate with language |
| site | `getNodeSitemap` | published in window | SQL predicate with language |
| legacy | `getNodeLegacy` | none: it answers an address, the route it leads to reads the material | none; only a type the context receives |

- "Published in window": `status = Published`, `published <= NOW()`, `expires` empty or later, database clock. A
  disabled type yields nothing to a non-moderator. The extension scope always applies; a moderator always passes
  category rights.
- Category rights come from one pre-read per type per instance. A right string `<level>|<group ids>`: empty grants
  nobody, a group list grants by intersection with `groups`, else level `0` grants everyone and `1` users only.
  `cid = 0` is open; a type without `features.categories` reads only `cid = 0`; a main category of another module
  or a deleted one closes the material to non-moderators.
- The language filter (context language or `lang = ''`) applies to lists, tree, deadline, search and the sitemap,
  never to reads by ID, targets or resources.
- Missing and closed answer the same `null`.

#### Single reads and targets

```php
public function setNodeExtension(?NodeExtension $ext): self
public function getNode(int $id, NodeType $type): ?Node
public function getNodeContent(int $id, NodeType $type): ?Node
public function getNodeAsset(int $id, NodeType $type): ?NodeAsset
public function getNodeTarget(string $type, int $id, bool $any = false): ?NodeTarget
public function getNodeTargetList(array $refs, bool $any = false): array
public function getTargetSize(): int
```

- The extension is assigned before the first single-type read (`null` for a standard type). Materials are found by
  the global `_nodes.id` and only within the passed route type.
- `getNode()`: main statement with author and category, then extra categories, relations and resources, one
  statement each. `getNodeContent()`: the main statement alone, `body` loaded, the four sets `null` (the `[attach]`
  route). `getNodeAsset()`: resource, material, route type and main category in one statement, plus an active role.
- `getNodeTargetList()` takes `global ID => type name`, at most `getTargetSize()` entries, and returns
  `array<int, NodeTarget>` in input order without missing, closed, unpublished, disabled-type or wrong-type
  entries. Bad IDs, names, key or value types or an oversized batch are `INVALID`; `[]` returns `[]`. It costs the
  type rows unknown to the instance plus one `UNION ALL` of one branch per type, and never loads text or sets.
  `getNodeTarget()` is the batch of one.
- `any = true` reads a type the context moderates by the item rule. Public readers never pass it;
  `NodeService::getLockedTarget()`, resource actions of an extension type and the Rating adapter of the main
  administrator do.

#### Lists and filters

```php
public function setNodeType(NodeType $type): self
public function setNodeTypes(array $types): self
public function setNodeCategory(int $cid): self
public function setNodeLetter(string $letter): self
public function setNodePage(int $page, int $limit): self
public function setNodeSets(bool $load): self
public function setNodeAuthor(int $uid): self
public function setNodeStatus(NodeStatus $status): self
public function setNodeOrder(string $order, string $dir = 'desc'): self
public function setNodePublished(?string $from, ?string $until): self
public function setNodeSearch(string $text): self
public function setNodeHome(bool $home = true): self
public function getNodeList(): array
public function getNodeCount(): int
```

Setters return the instance and check the shape of their value at once; whether the type settings allow a filter is
checked when the read runs. Every refusal is `INVALID` with the filter as path. There is no free filter array,
column name or SQL.

| Setter | Contract |
|---|---|
| `setNodeType` | one type; a disabled one only for its moderator |
| `setNodeTypes` | non-empty `NodeType[]`, no repeated ID, each readable; each branch uses the factory extension; an assigned `setNodeExtension()` is refused for a mixed set |
| `setNodeCategory` | `cid >= 1`, main or extra category without duplicates; needs `features.categories`; an unreadable category yields nothing |
| `setNodeLetter` | `''` or one Unicode letter or digit; needs `list.alpha`; escaped `LIKE`, table collation |
| `setNodePage` | page and size from 1; the size may not exceed `limits.maxlist` or any selected `list.limit`; without it the smallest of them |
| `setNodeSets` | `false` drops fields and the page batches of categories, relations and resources (`null` in models) |
| `setNodeAuthor` | `uid >= 1` on `_nodes.uid` |
| `setNodeStatus` | moderator: exactly that state, no date window; others: intersection with published; without it everyone lists published only |
| `setNodeOrder` | `published`, `updated`, `title`, `views`, `rating`; `asc`/`desc`; a key other than `published` must be in `list.orders` of every selected type |
| `setNodePublished` | canonical bounds on `published`, `from` inclusive, `until` exclusive, one may be `null`, `from < until` |
| `setNodeSearch` | UTF-8 up to 255 characters, `''` lifts it; literal escaped `LIKE` on `title`, `intro`, `body`; field JSON not scanned |
| `setNodeHome` | `true` requires `home = 1` and `features.home` of every selected type |

- `getNodeList()` returns `Node[]` with `body = null`. Fields are read when a selected type has an active field and
  sets are on; `cids` for `features.categories`, `rels` for `features.related` or `features.tree`, `assets` for an
  active role, each with at most one batch statement per page. `getNodeCount()` applies the same conditions without
  order and paging.
- A mixed selection compiles one branch per type with its own extension, joined by `UNION ALL`; one extension never
  weakens the rights of another type.
- Order: pinned rows first where `features.pinned` is on; `published`, `updated`, `title` then `id`; `views` then
  `published DESC, id DESC`; `rating` puts unrated rows last, then `score / ratings`, `ratings`, `published DESC`,
  `id DESC`. The default is `list.order`/`list.dir` of a single type, `published desc` for a mixed one.
  `published` is open to every type (the feed order, indexed by `pub`); `list.orders` limits the visitor's choice.
- Each branch splits into disjoint index ranges (pinned and unpinned; main `cid = X` and extra links of X). Parts
  carry only ID and sort keys, are cut to the page end, unioned and paged again; only the final page reads full rows.

#### Integration reads

```php
public function getNodeTree(int $after = 0, int $limit = self::TREEPART): array
public function getNodeSitemap(int $after = 0, int $limit = 500): array
public function getNodeAuthorStat(): array
public function getNodeCategoryCount(NodeType $type): array
public function checkNodeCategory(NodeType $type, int $cid): bool
public function getNodePostCats(NodeType $type): array
public function getNodeLegacy(string $mod, int $id = 0): ?array
```

- `getNodeTree()`: one selected type with `features.tree`, `limit` 1…500; rows `id`, `title`, `parent`, `sort` for
  `id > after` by ID under the list rules. `parent` is set only when the parent passes the same predicate, so no
  ACL is inherited. Callers read batches until one is not full.
- `getNodeSitemap()`: active types with `integrations.sitemap`, `limit` 1…500; rows `id`, `name`, `title`, `cid`,
  `ctitle`, `published`, `updated` for `id > after`; an empty batch ends the walk (the task uses a guest context).
- `getNodeAuthorStat()`: needs `setNodeAuthor()`; `type id => ['num', 'score', 'ratings', 'favs']` in one
  statement under the list rules.
- `getNodeCategoryCount()`: `cid => count` in any state per main category; `DENIED` without `manage` or moderation.
- `checkNodeCategory()`: the category is readable in the type by the list rule (a public list outside it is 404).
  `getNodePostCats()`: categories the form offers (`pview` and `ppost` both grant and the category is shared or of
  the context language; all for a moderator).
- `getNodeLegacy()`: the row of `_node_legacy` for a removed module name and its old id as `['type', 'nid']`, or
  with `id` 0 the type its materials went to (`nid` 0); `null` for a name outside the grammar, a missing row or a
  type the context does not receive.

#### Read budgets per method

Counted by `$db->qnum` around the Node handler; `getNodeContext()`, Point and global subsystems are counted apart.

| Method | Statements |
|---|---|
| `getNodeType()` / `getNodeTypeList()` | 1 per new name / 1 for all, 0 from memory (a version mismatch adds a re-read) |
| category pre-read | 1 per type per instance |
| `getNodeList()` | 1 page + at most one batch each for `cids`, `rels`, `assets` |
| `getNodeCount()`, `getNodeAuthorStat()`, `getNodeCategoryCount()`, `getNodeContent()`, `getNodeAsset()` | 1 |
| `getNode()` | 4 |
| `getNodeTargetList()` | type rows not yet known + 1 |
| `getNodeTree()`, `getNodeSitemap()` | 1 per batch |
| `getNodeLegacy()` | 1, plus the type read of `getNodeType()` |

Lists never run N+1 for authors, fields, categories, relations, resources, rating or favorites. The budgets of
whole routes are in [Statement budgets](#statement-budgets).

### NodeService

```php
public function __construct(Database $db, NodeContext $context, Field $field, ?Point $point = null, ?NodeExtension $ext = null)
```

The writer builds its own `NodeQuery` with the same context, fields and extension. `Point` is required before the
first statement (`INVALID point`) by `addNode()`, `updateNode()`, `updateNodeStatus()`, `deleteNode()`,
`updateNodeViews()`, `updateNodeAssetHits()`, `deleteNodeAssetReport()`, `updateNodePublishList()`. Callers outside
the Node classes take a writer from `getNodeWriter()`.

#### Lock order

Every writer follows [the single lock order](#the-single-lock-order); a type operation runs the sequence of
[the configuration protocol](#lock-order-of-a-type-operation). Inside a material write the type row is locked
`FOR UPDATE` first: another version than the read `NodeType` is `CONFLICT`, a disabled type is `NOTFOUND` for
non-moderators; then categories, materials by ascending ID, the `parent` chain by locking reads, resources and
Point. The proof of a type operation (`kind` `add|update|status|delete`, `name`, `id`, `old`, `new`; `0` for the
missing side) goes to the site log at `info` with the `aid`.

#### Type writes

```php
public function addNodeType(string $name, NodeTypeInput $input): NodeType
public function updateNodeType(string $name, NodeTypeInput $input, int $version): NodeType
public function updateNodeTypeStatus(string $name, bool $active, int $version): NodeType
public function deleteNodeType(string $name, int $version): void
public function addNodeTypeImport(string $json, string $name = ''): NodeType
```

All need `manage` or `super` (`DENIED` before any read) and return the type read back by a fresh reader. A new type
has `version = 1`; each change of data, configuration or activity adds one. A stale version is `CONFLICT` without a
partial write; a configuration that does not carry the row's version is `STORAGE`.

- `addNodeType()` creates a disabled type. The name matches `^[a-z][a-z0-9]{0,19}$`, is not in the private
  `RESERVED` list (`node`, system directories, Windows device names), is no module key or `modules/<name>` unless one
  of the nine replacements (`news`, `pages`, `faq`, `help`, `jokes`, `content`, `links`, `files`, `media`), is free
  in the four areas and in `uploads/` in any case, and has no categories (`INVALID categories`) or comments and
  favorites of an earlier owner (`INVALID remains`). Empty `uploads` copies `uploads.all`; empty `rating` becomes
  `active '1'`, `period '2592000'`, `detail '1'`, `guests '1'`.
- `updateNodeType()` needs complete rules. `ext` changes only while the type is disabled and has no material.
  Switching off `features.categories` with categories, `features.tree` with `parent` links or `features.related`
  with `related` links is `INVALID features.<key>`. A role with resource rows cannot be removed
  (`INVALID assets.<role>`); a `link` role needs all its sources to be distinct `http(s)` URLs compared byte for
  byte (`CAST(src AS BINARY)`), else `INVALID assets.<role>.mode`.
- `updateNodeTypeStatus()` is the only switch; the current state succeeds without write or version. Switching on
  first, before every lock, requires the self-check `checkPrivateRoots()` to find the upload root `closed`: the marker
  `uploads/check.txt` it asks for at `<homeurl>` lies in no public folder, so the light path of `index.php` refuses it;
  under the lock it repeats the full check of what is stored and needs a writable, non-link directory.
- `deleteNodeType()` needs no material in any state, no category and no user file. It removes the sections from the
  four areas and the key `node-<name>` from every administrator in the same transaction, so a later type of that
  name inherits no rights; the directory and guards stay.
- `addNodeTypeImport()` takes a `slaed.node` export up to 1 MiB: exactly `format`, `type`, `version = 1`, and the
  nine export keys with native types. A non-empty `$name` replaces the file's name; an existing type is never
  overwritten. Cloning is export plus import under a new name; a profile is `modules/node/profiles/<name>.json`.

Beyond `filterNodeSettings()` a write checks: labels (type title ≤ 100, role title ≤ 255, intros ≤ 1000) as plain
text or defined constants; `sort` 0…4294967295; fields by `Field::filterFieldList()`; existing `workflow` groups;
the twelve upload pieces (extensions of `Upload::getSupportedTypes()`, limits ≤ 18 digits, switches 0/1); the four
rating keys. The section is stored as differences from `node.defaults` (a role keeps `title`, `mode`, `max` and the
keys differing from `ROLEDEF`), and they must reproduce the checked settings exactly, else `STORAGE`. A new type
takes an existing `uploads/<name>` only when it holds nothing but the guard files of `FileManager::getGuardFiles()`;
a missing directory is created with its guards before the row.

#### Material writes

```php
public function getNodePreview(NodeType $type, NodeInput $input, NodeStatus $status): Node
public function addNode(NodeType $type, NodeInput $input, NodeStatus $status): Node
public function updateNode(int $id, NodeInput $input, int $version): Node
public function updateNodeStatus(int $id, NodeStatus $status, int $version): Node
public function getTextSource(int $id): ?array
public function updateNodeText(int $id, string $field, string $text, int $version): Node
public function deleteNode(int $id, int $version, Comment $com): void
```

Rights:

- A moderator creates in `Draft`, `Pending` or `Published`, also in a disabled type. Others need an active type with
  `features.submit` and a workflow that admits them (`workflow.access` `all`, `user`, or `group` with an
  intersection of `workflow.groups`); `Draft` is refused, `Pending` needs `features.moderation`, `Published` under
  moderation needs a group of `workflow.publish`. A background context writes no material.
- Only a moderator of the type changes, moves or deletes: a context moderating no type is refused before any
  statement, a moderator of another type after reading the head. `manage` alone gives no right on materials.
  The one exception is the quick edit of one text below, which the signed-in author holds too.
- A non-moderator sets no `poll`, `home`, `pinned`, `pubdate`, `expires`, posts only into categories whose `pview`
  and `ppost` grant him (`DENIED`), relates only to targets the reader returns, and gets `LIMITED` while the last
  material of the same address is younger than `limits.send` seconds (database clock, all types; `0` off; best
  effort, not asked by preview).

Input rules (`INVALID <path>`):

| Part | Rule |
|---|---|
| trusted tags | `filterTrustedTags()` strips `[usephp]`/`[usehtml]` from `intro`, `body`, resource intros and string field values of everyone but the main administrator, before any other check |
| `title`, `aname` | trimmed 1…100 characters without control characters; `aname` up to 25 and empty for a registered author |
| `intro`, `body` | UTF-8 within `checkEditorTextRoom()` of `nodes.intro` / `nodes.body` |
| `cid`, `cids` | empty without `features.categories`; `cids` unique, at most `limits.syncbatch`; each category of this type |
| `poll`, `home`, `pinned` | feature and moderator; an unchanged stored poll passes even with the feature off; a new poll must be active in `_voting` |
| `comon` | `CommentMode::Disabled` without `features.comments` |
| `pubdate`, `expires` | moderator only; a future date and any `expires` need `features.schedule`; `expires` later than the publication moment; `Published` without a date gets `NOW()` |
| `fields` | `checkFieldValues()`, `required` only for `Pending`/`Published`; on create empty active fields get `default`; inactive stored values survive; undefined names go; JSON at most 1 MiB |
| `rels` | at most `limits.syncbatch`; `related` needs `features.related`, `parent` `features.tree`; one parent, no repeat, no self; same type; a new parent is walked on the locked chain (cycle `INVALID rels.parent`, a stored loop is logged and `STORAGE`) |
| `assets` | at most `limits.maxassets` and each role `max`; each active role `min` for `Pending`/`Published`; a registered active role and a kind its mode shows; `src` a safe relative path outside `thumb/` or, for `canlink` roles, an `http(s)` URL without credentials up to 2048 bytes (a `link` role takes URLs only); `name` ≤ 255 and `title` ≤ 100 characters, `intro` ≤ 65535 bytes |
| external URL | a new or replaced one from a non-moderator only for `Pending`; a `link` role address is unique in the type byte for byte |
| `ext` | `[]` for a standard type, else `filterNodeData()` of the extension |

A resource unchanged in `kind`, `role`, `src` keeps its metadata unchecked. A new local source (and a new `[attach]`
name in the text) must exist in `uploads/node/<type>` under the directory lock, carry an allowed extension of rule and
role, fit the smaller `maxbytes`, match its kind and belong to the visitor (account ID or the guest token of
`getEditorFileOwner()`) unless he moderates; `mime` comes from `finfo`. A replaced `src`, `kind` or `role` resets
`hits` and the report. Removing a resource deletes the row, never the file.

- `getNodePreview()` runs every check of `addNode()` with reading statements only and no transaction, and returns
  an unsaved `Node` with `id = 0`, `version = 0`.
- `addNode()` stores `version = 1` with its sets in one transaction; `uid` and `ip` come from the context;
  `addNodeData()` of the extension runs before the publication job and Point.
- `updateNode()` changes content only, keeps `uid`, `ip` and the state, checks the version on the head and on the
  locked row and writes `… WHERE version = :ver`. It returns the stored material with its sets.
- `updateNodeStatus()` follows the matrix; the current state returns the stored row without a write. `Pending` and
  `Published` need the required fields and role minimums by what is stored and an `expires` later than the
  publication moment. The answer has `cids`, `rels`, `assets` `null`. `Pending` → `Published` awards the moderator
  `moderate` unless the material is the moderator's own.
- `deleteNode()` in one transaction: type lock → material `FOR UPDATE` → its `_node_assets` rows (before any Point
  account, the order of a report decision) → `deleteNodeData()` → `Comment::deleteTarget($type, [$id], [$uid])` →
  compensation of the author's `publish` (`reverse:<rid>`) → `_favorites` → `DELETE`. Sets, relations of both
  directions, the job and extension rows go by `ON DELETE CASCADE`; files stay. A failed compensation is `STORAGE`;
  only `Point::$valid = false` deletes without it and logs a warning.
- `getTextSource()` answers the stored material in every state with its type and `allow`, or `null`: the right of
  the quick edit decides who sees it, not the public read, so an author sees the text an edit sent back to
  moderation.
- `updateNodeText()` changes `intro` or `body` of a type without an extension (`DENIED` otherwise, `INVALID field`
  for any other field). The moderator of the type edits in every state; the signed-in author of a `Pending` or
  `Published` material edits while `limits.edit` seconds from `created` last on the database clock; a material
  written without an account has no author. The text passes the checks of `intro` / `body` above and a new
  `[attach]` name the ownership rule, under the directory lock. Under the type lock and the material `FOR UPDATE`
  the right is decided again, then a text equal to the stored one answers the stored material without a write (no
  version, no `updated`, no state), and only then a stale version is `CONFLICT`. An author edit
  of a `Published` material under `features.moderation` without a group of `workflow.publish` moves it to `Pending`
  in the same write and version step; it needs the readiness of a move to pending, and a job of a future
  publication goes with it. Categories, fields, relations, resources and the poll stay as they are.
- After `CONFLICT` nothing is changed; the controller keeps the input and offers the current version as the new
  base. There is no merge and no version-less write.

#### Resources and reports

```php
public function getNodeFile(NodeType $type, int $id, string $key, bool $thumb, Comment $com): string
public function getTypeFiles(NodeType $type): array
public function updateNodeAssetHits(int $id, NodeType $type): void
public function updateNodeAssetReport(int $id, NodeType $type): void
public function deleteNodeAssetReport(int $id, NodeType $type, bool $useful): void
```

- `getNodeFile()` resolves an attachment to its canonical path for `getFileStream()`, `''` for any refusal. `key`
  is a bare basename (≤ 255 bytes) of a type `Upload::getSupportedTypes()` knows. With `id > 0` it must occur in the
  text of a material the reader returns — as an `[attach]` or, inside `[usehtml]`, as the `go=file` address of the
  same material — or in a published comment of that material (any comment not deleted for a moderator), read by
  `Comment::getAttachTexts()`; its form does not matter, and the extensions of the upload rule today do not either.
  With `id = 0` (preview) it must be a managed name (`FileManager::checkFileName()`) with an extension of the rule
  that belongs to the visitor unless he moderates, the visitor may write the type or upload into its area, and no
  SQL runs.
- `getTypeFiles()` answers every name the materials of a type, whatever their state, and their local resources
  reference; the unused filter of the uploads screen measures the type folder against it.
- `updateNodeAssetHits()` re-reads through `getNodeAsset()`, accepts mode `download`, or `link` with an external
  URL, and adds one hit atomically right before an allowed `GET` download or visit (`HEAD`, display and later ranges
  do not count).
- `updateNodeAssetReport()` needs a role with `report` (only modes `download` and `link` may take reports) and sets
  `reported = NOW()` and `ruid` (guest `0`) only while no report is open. CSRF and rate limits are the controller's.
- For an extension type, hits and report run as the actions `asset`/`report`: the target is read with `any`,
  `checkNodeAction()` may forbid (`DENIED`), `updateNodeAction()` follows a changed row in the same transaction.
- `deleteNodeAssetReport()` is for a moderator; it locks the resource row and re-reads the report, so a second
  decision is an empty success. A useful report of another registered user earns him `report`
  (`report:<id>:<16 hex>`); each decision earns the moderator `moderate` unless the report is his own. The report is
  cleared in the same transaction.
- `hits`, `reported`, `ruid` never touch `_nodes.version`, `_nodes.updated` or `_node_assets.updated`.

#### Counters and global owners

```php
public function updateNodeViews(int $id, NodeType $type): void
public function setTargetLock(int $id, NodeType $type): void
public function getLockedTarget(int $id, NodeType $type, bool $any = false): ?NodeTarget
public function updateNodeComments(int $id, NodeType $type, int $count): void
public function updateNodeRating(int $id, NodeType $type, int $score, int $ratings): void
public function deleteNodePoll(int $id): void
```

- `updateNodeViews()` repeats the read right through `getNodeTarget()` and adds one view atomically, once per
  successful view (never preview, `HEAD` or error); refused for a background context.
- `setTargetLock()` locks the type row, then the material row, by locking reads alone inside the owner's open
  transaction (`INVALID transaction` outside, `NOTFOUND` when gone), so no snapshot predates the wait.
  `getLockedTarget()` takes that lock and then reads the target by the reader's predicate (`null` when missing,
  closed or foreign); Rating calls it first in a vote, favorites after locking the user row.
- `updateNodeComments()` and `updateNodeRating()` store trusted aggregates inside the owner's transaction after
  `setTargetLock()`, change neither `version` nor `updated`, and never commit. `count` is
  0…4294967295; ratings need `ratings <= score <= 5 × ratings`.
- `deleteNodePoll()` needs `polls` and the open transaction of the poll owner, who holds `GET_LOCK('node.poll.<id>')`.
  It locks all type rows by ascending ID (the reached types are known only from materials), then the poll's
  materials, and clears `poll` with `version + 1` and `updated`; the owner commits.

`Comment` treats a module key naming a registered type as a Node target (own reader and writer without Point).
Each comment write calls `setTargetLock()` before touching `_comment`, re-reads the discussion mode under the lock,
counts published comments with `LOCK IN SHARE MODE` and stores the count via `updateNodeComments()`; the
extension's `updateNodeAction('comment')` runs only on the first publication (`_comment.shown IS NULL`).

#### Categories and remains

```php
public function addNodeCategory(array $row): int
public function updateNodeCategory(int $id, array $row): void
public function deleteNodeCategory(int $id): void
public function checkTypeRegistry(array $names): bool
public function getNodeRemains(): array
public function deleteNodeRemains(string $name): void
```

- Categories of a Node type change only here; `row` is the full category form (the columns `modul` … `pmod`). The
  context needs `super`, `manage` or moderation of each touched type; writes lock those type rows. The parent is `0`
  or a category of the same module whose chain (locked) neither reaches the category nor loops
  (`INVALID category.parent`).
- `updateNodeCategory()` refuses to move a used category to another module (`INVALID category.used`): main or extra
  category of any material, direct subcategories, or items of the fixed module `forum` in `_forum`, counted
  without a lock). `deleteNodeCategory()` deletes the whole subtree; a main category of any
  material in it refuses (`category.used`), extra links go and the affected materials get a new version.
- `checkTypeRegistry()` answers whether a name is in `_node_types` whatever its configuration says; the category
  screen chooses the Node path by it, so a broken type is refused instead of falling back to plain SQL.
- `getNodeRemains()` (`manage`/`super`) lists `modul` keys of `_comment` and `_favorites` that are neither a
  registered type nor a live module beyond the nine replacements, as `key => ['comments', 'favorites']`.
  `deleteNodeRemains()` deletes one listed key's rows in one transaction; awards are not compensated.

#### Publication queue

```php
public function updateNodePublishList(int $limit = 50): array
```

A published material with a future date has a row in `_node_publish` (`published = due = date`) kept in line by
every write. `updateNodePublishList()` needs `task`, `limit` 1…500, takes due jobs of active types by `due, nid`,
one transaction each: stale jobs go without award, a refused award moves the job 60 seconds on, a closed Point
configuration delivers without award and logs. It never changes `version` or `updated` and returns exactly
`status` (`success`/`failed`), `message` and `extra` (`processed`, `failed`, `skipped`). The job `nodepublish`
(`* * * * *`, `limit 50`) calls it via `addNodePublishTask()` and must be registered in `getSchedulerJob()`,
`addSchedulerSystemJob()`, `config/scheduler.php` and `setUpdateRun()` of `update.php`.

Node awards use scope `node.<name>`: `publish` (`node:<id>`, compensation `reverse:<rid>`), `moderate`
(`approve:<id>`, `report:<key>`, with `aid`), `view` (`node:<id>`), `download`/`visit` (`asset:<id>`), `report`.
A refusal logged by Point never blocks the action; an exception of Point is `STORAGE`.

### Exceptions

`NodeException extends RuntimeException` is the only exception of the API: no subclasses, no second error object.
The cause is `getCode()`; the message (`Invalid node input: <path>` for input) is for the log only. Storage failures
carry the original as `previous`. `Field` throws `InvalidArgumentException`, which the writer turns into `INVALID`.

| Code | Value | Cause | HTTP |
|---|---:|---|---:|
| `NOTFOUND` | 1 | the material or related entity to change does not exist | 404 |
| `DENIED` | 2 | the context does not allow the action | 403 |
| `INVALID` | 3 | input, state or move not acceptable | 422 |
| `CONFLICT` | 4 | stale expected version or a lock not obtained | 409 |
| `STORAGE` | 5 | the storage operation did not complete | 500 |
| `LIMITED` | 6 | the write window `limits.send` has not passed | 429 |

The values are a stable internal contract; the module picks HTTP codes (`getNodeStatus()` in
`modules/node/index.php`) and texts by code (`getNodeFault()`: `_NODE_GONE`, `_ACCESSDENIED`, `_NODE_INVALID`,
`_NODE_BUSY`, `_CERROR5`, else `_NODE_FAILED`). Reads keep answering `null` for missing and closed alike.

### Site helpers

All in `core/system.php` unless noted.

| Function | Contract |
|---|---|
| `getNodeContext(): NodeContext` | the request snapshot |
| `getNodeReader(?NodeType $type = null): NodeQuery` | reader with the shared `$fld`, bound to the type's extension |
| `getNodeWriter(?NodeType $type = null): NodeService` | writer with `$fld` and the shared `$pnt`, bound to the type's extension |
| `getNodeHandler(NodeType $type): ?NodeExtension` | the type's extension with the request context |
| `getNodeTypeMap(): array` | `name => NodeType` the request may receive, once per request; no SQL without registered types |
| `getNodeTitleMap(array $refs): array` | `id => title` of readable targets, batches of `NodeQuery::getTargetSize()` |
| `getNodeModeType(string $mode): ?NodeType` | first active type without extension with that `view.mode` |
| `getNodeTplName(string $kind, string $name, NodeType $type): string` | `node/<mode>/<name>` when the theme has it, else `node/<name>` |
| `getNodeBlockParam(string $param): ?array` | canonical JSON of `_blocks.param`: exactly `type`, `mode` (`last`/`home`), `limit` 1…50; a named type needs `integrations.blocks`, `home` its `features.home` |
| `addNodeMail(array $list, string $title, string $text): bool` | one queued mail per address: `mtemp` with escaped `[text]`, subject `<sitename> - <title>`, kind `node`, priority 3 |
| `addNodePublishTask(): array`, `addNodeSyncTask(): array` | scheduler adapters; a `NodeException` becomes `status = failed` |
| `getRssFeeds(): array` | active types with `integrations.rss` |
| `checkUploadModer(string $mod): bool` | upload moderator; a type only through `node-<name>` |
| `getUserMail(int $uid): string`, `getAdminNames(string $key): array` | stored account address; `id => name` of main administrators and holders of the key |
| `getConst(string $con): string` | a defined `_NAME` answers its text, anything else as is |
| `updateNodeTypePart(string $name, string $part, array $value, int $version): string` (`core/admin.php`) | replaces `fields`, `uploads`, `rating` or `integrations` of a type via `updateNodeType()`; `''` or a safe text by code |
| `getCategoryModules(): array` (`core/helpers.php`) | `forum` and types with `features.categories` |

```php
function getFileStream(string $path, string $name, string $mime = 'application/octet-stream', bool $inline = false, bool $cached = false, ?callable $start = null): void
```

Sends one file and ends the request. A MIME outside the inline registry (common `image/*`, `audio/*`, `video/*`
types listed in the function) goes as an `application/octet-stream` attachment. ETag `"<32 hex>"` of path, size
and mtime; `cached` sends private revalidated headers, otherwise `no-store`. `GET` honours one range; a bad one is
416, several give the whole file; `If-Range` needs the strong ETag or exact date. `$start` runs once before a body
from byte zero, never for `HEAD`, 304 or 416. The body streams in 64 KiB blocks until the client leaves.

## Field

`final class Field` (`core/classes/field.php`) serves the extra fields of every area: accounts, forum and
each Node type. `core/system.php` creates the shared `$fld = new Field()`, passed explicitly to `NodeQuery`,
`NodeService` and `NodeView`. The class has no constructor or state, reads neither configuration nor database and
writes nothing.

```php
public function getFieldTypeList(): array
public function filterFieldList(array $fields): array
public function checkFieldValues(array $fields, array $values, bool $required = true): array
public function filterFieldValues(array $fields, array $values): array
public function getFieldForm(Template $tpl, array $fields, array $values = [], array $errors = []): array
public function getFieldView(Parser $prs, array $fields, array $values, string $module): array
```

### Registry

Each entry has exactly `title` (language constant), `control` (semantic control key), `multi` (may allow several
values) and `options`. No classes, callbacks, SQL, template paths or CSS. A new type is added in code with tests.

| Type | `title` | `control` | `multi` | `options` |
|---|---|---|---|---|
| `text` | `_FIELDINPUT` | `text` | no | `min`, `max` |
| `textarea` | `_FIELDAREA` | `textarea` | no | `min`, `max` |
| `select` | `_FIELDSELECT` | `select` | yes | `items`, `min`, `max` |
| `bool` | `_FIELDS_BOOL` | `checkbox` | no | — |
| `int` | `_FIELDS_INT` | `number` | no | `min`, `max` |
| `decimal` | `_FIELDS_DECIMAL` | `number` | no | `min`, `max`, `scale` |
| `date` / `datetime` | `_FIELDDATE` / `_FIELDTIME` | `date` / `datetime` | no | `min`, `max` |
| `email` / `url` | `_EMAIL` / `_URL` | `email` / `url` | no | `min`, `max` |

### Definitions

`filterFieldList()` is atomic: the whole set canonical, ordered by `sort` then name, or `InvalidArgumentException`
whose message is the path of the first error (`price.options.scale`). Nothing is completed or repaired.

- Field names and option keys share `^[a-z][a-z0-9_]{0,31}$`, so a stored key never becomes a PHP integer.
- A definition has exactly `title`, `intro`, `type`, `default`, `options`, `req`, `multi`, `active`, `sort` with
  native types. `title` (1…255 characters) and `intro` (up to 1000) are plain text without markup, control
  characters, `<`, `>` or edge spaces, or a language constant: a leading `_` always means a constant, and an unknown
  one is an error. In the administration and the first stage of `update.php` the constant must be defined in the site dictionary
  `lang/<language>.php` the request loaded, so a saved set also passes on the site, where `defined()` decides.
- Only `select` supports `multi`. `req` with `active = false` is refused. Inactive fields leave form and view but
  keep their stored values.
- `options.items` of a `select`: non-empty, at most 256, each exactly `title`, `active`, `sort`, ordered by `sort`
  then key. The key is the stored value and is not renamed once used; an inactive option is not offered but keeps
  its label for stored values.
- `min`/`max` are inclusive: characters for `text`, `textarea`, `email`, `url`; whole numbers for `int`; canonical
  strings for `decimal`, `date`, `datetime`; a count for a multiple `select`. A single `select` forbids them,
  `min > max` is refused, and `max` can only lower a hard limit. `decimal` requires `scale` 1…18.
- `default` is the empty value (`''`, `[]` for multiple `select`, `null` for `bool` and `int`) or a valid canonical
  value, never a disabled option; it applies only when an entity is created without a value.

Hard limits: 256 definitions per set and options per `select`; 64 chosen values; `text` 4096, `textarea` 262144,
`email` 254, `url` 2048 characters; 65 digits per `decimal`; 1048576 bytes of canonical JSON per entity.

### Values

One internal normalization serves check and filter. `null`, `''` and `[]` are absence; `false`, `0`, `'0.00'` are
values.

| Type | Canonical value |
|---|---|
| `text` | trimmed, no control characters |
| `textarea` | CRLF and CR become LF, whitespace kept |
| `select` | an allowed key; `multi` a list without repeats in definition order |
| `bool` | native bool from `true`, `false`, `1`, `0`, `'1'`, `'0'` only |
| `int` | signed 64-bit int from an int or a decimal string without `+` or leading zeros |
| `decimal` | string with point and exactly `scale` digits, compared without float |
| `date` / `datetime` | real `YYYY-MM-DD` / `YYYY-MM-DD HH:MM:SS` from `YYYY-MM-DDTHH:MM[:SS]` or the canonical form |
| `email` | valid address, domain lower-cased, local part kept |
| `url` | absolute `http(s)` without credentials or a root path `/...`; `//host`, other schemes, spaces and backslashes refused; case kept, nothing completed (`filterWebUrl()` is not used) |

- `checkFieldValues()` returns `name => code` for the active fields, `[]` on success, the first error per field.
  Unknown names are no error. `$required = false` lifts only the demand to fill required fields. Canonical JSON over
  1 MiB gives `max` to the longest value. Codes: `required`, `type`, `format`, `choice`, `min`, `max`; messages
  `_FIELDS_REQ`, `_FIELDS_TYPE`, `_FIELDS_FORMAT`, `_FIELDS_CHOICE`, `_FIELDS_MIN`, `_FIELDS_MAX`.
- `filterFieldValues()` returns canonical values of active fields without unknown or absent names and throws
  `InvalidArgumentException` for a value that still fails.
- Owners merge on change: active values from the form, stored values of inactive fields kept, undefined names
  dropped.
- `getFieldForm()` returns rows by name (`name`, `type`, `label_for`, `label_text`, `hint_id`, `hint_text`,
  `error_text`, `is_required`, `field_html`) and never reads POST. Controls are `field[<name>]` from theme fragments;
  a checkbox is preceded by a hidden `0` so an unchecked box posts `false`.
- `getFieldView()` returns active fields with a value, by name in canonical order: `name` (repeated because the
  template engine does not pass keys), `type`, `label_text`, `hint_text`, `value`, `value_text`, `value_html` (only
  `textarea`, `Parser::filterContent()` in safe mode), `value_href` (the URL, `mailto:<address>`, else `''`),
  `items` (chosen options as `value`, `label_text`). Stored values meet only the hard limits; a disabled option
  keeps its label, removed options and undefined names are left out.

### Storage

Definitions live in `config/fields.php` under `fields.<area>`: `account`, `forum` and
`fields.node.<type>`. Definition CRUD and the file write stay outside `Field`.

- `getFieldRules(string $mod)` (`core/helpers.php`) answers the checked set of an area once per request; every area
  is empty until the 6.3 data update leaves `$conf['update']['fields'] === '6.3.0'`, and a refused set is logged and
  counts as empty.
- `getFieldsPost(string $mod, string $old = '')` reads `field[]` for the fixed areas (trusted tags stripped unless
  the main administrator posts) and answers `json`, `errors` and `stop`; without the mark or with a broken set the
  stored text comes back unchanged. `getFieldsInRows()` and `getTplViewFieldRows()` build form and view rows. Node
  materials pass values through `NodeInput::$fields`.
- `admin/modules/fields.php` edits the areas of `getFieldAreas()` (fixed areas and `node.<name>`). A saved field
  keeps its name, type and option keys; a changed name, a lost option key, a vanished stored block or a missing
  `done[<area>]` marker (truncation by `max_input_vars`) refuses the whole save with the path. Fixed areas go
  through `setConfigFile()`, a Node area through `updateNodeTypePart($name, 'fields', …)` with the form's version.

## Feed

`final class Feed` (`core/classes/feed.php`) is the one RSS and Atom reader. It runs no SQL, knows nothing about
Node, renders no HTML and writes no cache.

```php
public function __construct(array $conf, ?Closure $send = null)
public function getFeedContent(string $url, string $etag = '', string $modified = ''): array
public static function getFeedUrl(string $url): array
```

- The constructor takes the loaded `$conf['rss']` and an optional trusted transport; `null` selects the built-in
  cURL transport. A transport is never read from configuration or the request.
- `getFeedContent()` returns exactly `ok`, `changed`, `code`, `body`, `etag`, `modified`, `error`. 304 gives
  `ok = true`, `changed = false`, no body; validators are returned only in checked form. A refusal has `ok` and
  `changed` `false`, empty body and validators, the last response code (`0` if none) and one safe `error` code:
  `config`, `support`, `url`, `address`, `transport`, `timeout`, `bytes`, `redirect`, `status`, `type`, `xml`.
- `getFeedUrl()` normalizes without network: `['url' => canonical, 'host' => host]` or `[]`; normalizing the result
  again changes nothing. `NodeSync` stores exactly this form.

Four bounds of `config/rss.php` are whole decimal numbers checked before any request (a value out of shape is
`config`): `bytes` (body limit while streaming and after, shipped `2097152`), `timeout` (seconds for the whole
operation including lookups, `10`), `redirects` (`3`), `max` (entries written, `50`).

### Address policy

- `http(s)`, no credentials, default port only, ≤ 2048 bytes; the host an IP literal or a lower-case DNS name of two
  or more labels (Unicode through `idn_to_ascii()`); the fragment is dropped.
- Public addresses are judged only by `Upload::checkPublicAddress()`. A name is resolved and one non-public address
  refuses the host; the request is pinned to the smallest IPv4, else IPv6, and the built-in `get` checks
  `CURLINFO_PRIMARY_IP` against it (no proxy, no automatic redirects, TLS verification on).
- Each redirect (301, 302, 303, 307, 308, one `Location`) passes the same checks; cookies and authorization are
  never forwarded.
- `If-None-Match` and `If-Modified-Since` go on the first hop only, in checked form. A 304 after a redirect or
  without a sent validator is `status`; after a redirect no validators are returned.
- A lookup starts only while time is left; one that exhausted the bound is `timeout`.

```php
Closure(string $op, array $request): array
// resolve: ['host' => string] -> ['addresses' => string[]]
// get: ['url', 'headers', 'timeout', 'bytes', 'ip'] -> ['code' => int, 'headers' => array<string, string[]>, 'body' => string]
```

`get` performs one HTTP exchange, not the redirect loop. A `RuntimeException` or an answer of another shape is
`transport`; an unknown operation is refused. Tests replace both operations, so DNS rebinding and private-IP
redirects are reproduced without network.

### Canonical Markdown

- Accepted: status 200 with no `Content-Type` or an XML feed type (`*+xml`, `application/xml`, `text/xml`).
  `<!DOCTYPE`/`<!ENTITY` are refused before parsing, `DOMDocument` loads with `LIBXML_NONET`, and a parsed doctype
  refuses too. Roots: RSS 2.0/0.9x, RSS 1.0, Atom; broken XML is `xml`, an empty feed an empty body.
- Entries in feed order up to `max`: title, link (RSS `link` or permanent `guid`, Atom first alternate link), date
  (`pubDate`/`dc:date`, `updated`/`published`) and description (`description`, Atom `summary`/`content`).
- HTML becomes plain paragraphs through `Dom\HTMLDocument`; executable and embedded elements (`script`, `style`,
  `iframe`, `object`, `svg`, form controls and the like) are dropped with their content.
- Dates in RFC 822 or RFC 3339 shape become `YYYY-MM-DD HH:MM UTC`, others are dropped. An entry without title is
  headed by its link host; one without both is skipped.

The body is UTF-8 with LF and one final newline: per entry `## <title>`, the optional date, `[<host>](<url>)` and
the description paragraphs, all separated by blank lines. Received text is data: CR/CRLF become LF, control
characters other than LF and TAB go, and every ASCII punctuation character is prefixed with a backslash. Headings
and links are added after escaping; in the URL `(`, `)`, `[`, `]`, `*`, `` ` ``, `\` and anything outside the safe
set are percent-encoded. `Parser` turns each backslash + ASCII punctuation pair into a literal token before BB,
Markdown and free blocks, left to right, and emits it HTML-escaped; raw regions keep their backslashes (`[code]`,
`[php]`, `[usephp]`, `[usehtml]`, HTML tags, `script`/`style`, Markdown code). `getAttachList()` applies the same
rule, so an escaped bracket names no attachment. Safe mode alone does not stop BB commands; every consumer renders
the body through Parser in safe mode.

### Consumers

| Function | Use |
|---|---|
| `getRssBody(string $url): ?string` | Markdown for `_blocks.content`; `null` on failure or above the `TEXT` column (65535 bytes) |
| `getRssBlock(int $bid): void` | a stale block fetches and stores its body, a failure keeps the old one, both move `time`; rendered by `filterContent()` in safe mode |
| `getRssView(string $url): string` | feed page of the account and rss modules; each outcome (refusals too) is kept 900 seconds in the data cache keyed by the canonical URL and `rss.max`; an address outside the Feed form is neither fetched nor kept; rendered by `filterDoc()` without the parser cache |
| `NodeSync` | fetches outside transactions, keeps the previous body and validators on failure |

The three functions live in `core/system.php`.

## Types

A type is one row of `_node_types` (identity and state) plus its section in `config/node.php` (settings), its field
set in `config/fields.php`, its upload rule in `config/uploads.php` and its rating rule in `config/ratings.php`. Code
never assembles a type itself: `NodeQuery::getNodeType()` and `getNodeTypeList()` join the row and the four areas into
one immutable `NodeType`, and `getNodeTypeMap()` in `core/system.php` holds the types of the request by name.

```php
final readonly class NodeType {
    public function __construct(
        public int $id, public string $name, public string $title, public string $intro, public string $ext,
        public bool $active, public int $sort, public int $version, public string $created, public string $updated,
        public array $settings, public array $fields, public array $uploads, public array $rating
    ) {}
}

final readonly class NodeTypeInput {
    public function __construct(
        public string $title, public string $intro, public string $ext, public int $sort,
        public array $settings, public array $fields, public array $uploads, public array $rating
    ) {}
}
```

- `settings` holds the effective settings (defaults merged, every key present, roles expanded), `fields` the canonical
  field set, `uploads` the twelve named pieces of the upload rule, `rating` the four rating keys. A broken upload rule
  gives `[]` and blocks uploads of the type; a broken rating rule gives `[]` and blocks its rating; both are logged.
- The public name travels beside `NodeTypeInput` to the create call; the expected version travels beside it to every
  change. `id`, `active`, `version`, `created` and `updated` are set by the system only.

### Identity rules

- `name` matches `^[a-z][a-z0-9]{0,19}$` and is immutable: public route, key of every area, suffix of `node-<name>`.
  Refused: `node`, `admin`, `index`, `setup`, `core`, `config`, `storage`, `templates`, `plugins`, `uploads`, `tools`,
  `tests`, `vendor`, Windows device names; a module name (`$conf['modules']` or `modules/<name>`) other than the nine
  replacements `news`, `pages`, `faq`, `help`, `jokes`, `content`, `links`, `files`, `media`; a name any of the four
  areas carries or `uploads/` holds in another case; a name with rows in `_categories` (`INVALID categories`) or in
  `_comment`/`_favorites` (`INVALID remains`, cleared with admin `op=remains`).
- One area is no refusal for the nine replacements: the upload rule a removed module left under its name in
  `config/uploads.php`. A new type of that name with an empty upload rule takes the old rule over; a rule the check
  refuses is logged (`Node: the upload rule a removed module left is invalid, …`) and the new type gets the copy of
  `all` as every other new type does.
- `title` (1-100) and `intro` (0-1000) are plain text or a defined language constant; `ext` is empty or a key of the
  closed extension map (`support`, `sync`); `sort` is 0-4294967295.

### Where settings live

`config/node.php` is the only store of Node settings; the database has no settings table and reading a type runs no
settings query.

```php
'node' => [
    'version' => '1',
    'limits' => ['maxassets' => 100, 'maxlist' => 100, 'syncbatch' => 500, 'send' => 60, 'edit' => 600],
    'support' => [
        'state' => ['staff' => 0, 'author' => 1, 'closed' => 2],
        'prio' => ['low' => 0, 'normal' => 1, 'high' => 2, 'urgent' => 3],
    ],
    'defaults' => [ /* list, view, form, workflow, admin, features, assets, integrations */ ],
    'types' => [
        '<name>' => ['version' => 1, /* only the differences from defaults */],
    ],
],
```

| Key | Type | Shipped value | Rule |
|---|---|---|---|
| `version` | string | `'1'` | format of the file, including extension settings; any other value makes every type invalid |
| `limits.maxassets` | int | 100 | at least 1; resource rows of one material, all roles together; ceiling of every role `max` |
| `limits.maxlist` | int | 100 | at least 1; ceiling of `list.limit` and of a block limit |
| `limits.syncbatch` | int | 500 | at least 1; ids per pass towards a shared subsystem, ceiling of relations and extra categories per input |
| `limits.send` | int | 60 | at least 0; seconds between two public submissions from one IP for a visitor who does not moderate the type, `0` switches the window off |
| `limits.edit` | int | 600 | at least 0; seconds from `created` in which the signed-in author may quick edit the intro and body of an own pending or published material of a type without extension, `0` switches the author edit off |
| `support.state`, `support.prio` | map name => int | see above | the only source of ticket states and priorities |
| `defaults` | map | see sections | shared values; only the eight sections below, never `ext` |
| `types.<name>.version` | int | - | equals `_node_types.version`; consistency metadata |
| `types.<name>.<section>` | map | - | differences from `defaults`, including `ext` |

A type has no `limits` section. It narrows its own materials through `list.limit` and `assets.<role>.max`.

Merge rule (`NodeQuery::getMergedArray()`): an associative map merges key by key into the default map; a list
replaces the default whole; an empty array over a non-empty default map is no difference; an empty array over a list
clears it. The writer stores only differences (`NodeService::getStoredSettings()`): a role keeps `title`, `mode` and
`max` plus the keys that differ from the role defaults; a section whose default is `[]` (`features`, `assets`) is
stored whole. After storing, the writer re-reads the stored form and refuses (`STORAGE`) when it does not reproduce
the same effective settings.

### Effective settings

`NodeQuery::filterNodeSettings(string $ext, array $settings, array $fields): array` is the single check for every
write, and the read of a stored type runs it too. It merges `$settings` into `defaults`, validates each section, runs the extension
filter and returns the canonical settings in the order `list`, `view`, `form`, `workflow`, `admin`, `features`,
`assets`, `integrations`, `ext`. A failure throws `NodeException::INVALID` with the message
`Invalid node input: <path>` (for example `list.limit`, `assets.cover.mode`). A field named like a column of
`_nodes` is refused as `fields.<name>`.

A stored type may predate a rule that closed a switch: `features.submit` of a `sync` type, or `report` of a role
outside the modes `download` and `link`. When the only refusal is such a switch standing at `true`, the read takes it
as `false`, logs `Node: a type carries a switch a later rule closed, it is read switched off` with the name and the
path, and checks again; both switches only narrow what the type grants. A malformed value of them, and every other
refusal, still fails the type. A write is never narrowed: saving the type form stores the switches off.

#### Settings: list

| Key | Type | Default | Rule |
|---|---|---|---|
| `orders` | list | `['published']` | non-empty, unique subset of `published`, `updated`, `title`, `views`, `rating` |
| `order` | string | `'published'` | member of `orders` |
| `dir` | string | `'desc'` | `asc` or `desc` |
| `limit` | int | 10 | 1 to `limits.maxlist`; page size of the list, the category and the admin preview |
| `alpha` | bool | false | enables the letter filter `let` |
| `show` | list | `['category', 'author', 'date', 'views']` | unique subset of these four; ordered; hides metadata in the standard templates only |

The section must carry exactly these six keys after the merge. `features.pinned` puts pinned materials first in every
sort; there is no sort key for it.

#### Settings: view

| Key | Type | Default | Rule |
|---|---|---|---|
| `mode` | string | `'default'` | one of `NodeQuery::MODES`: `default`, `article`, `docs`, `faq`, `files`, `media`, `support`; a type of the extension `support` takes `support` and no other type does |

`view` carries no rights. See Rendering for how the mode selects templates.

#### Settings: form and admin

Reserved and always empty. A non-empty `form` or `admin` in `defaults` or in a type fails as `INVALID form`/`admin`.
The form is built from the fields and roles of the type, the admin screens from the shared admin templates.

#### Settings: workflow

| Key | Type | Default | Rule |
|---|---|---|---|
| `access` | string | `'user'` | `all` (every visitor), `user` (any registered user), `group` (a user of one of `groups`) |
| `groups` | list of int | `[]` | unique positive group ids; must be non-empty when `access = group` |
| `publish` | list of int | `[]` | unique positive group ids whose members publish directly; subset of `groups` when `access = group` |
| `notify.pending` | bool | true | admin notice (`addAdminMail`) for a new `Pending` material |
| `notify.result` | bool | true | mail to the registered author when a moderator decides a `Pending` material |

The writer also checks that every id of `groups` and `publish` exists in `_groups`.

Public submission (`features.submit` must be on, otherwise the form answers 404):

- The form is open to a visitor the `access` rule admits, and always to a moderator of the type; the type must be
  active.
- The created state: `Published` without `features.moderation`, for a user in a `publish` group, or for a moderator of
  the type; `Pending` otherwise. The public form never creates a `Draft`.
- `access = all` with `submit` on and `moderation` off lets guests publish directly; the type form requires the
  explicit confirmation box `guestok` for it and answers 422 (`_NODE_GUESTNO`) without it.
- Moderation belongs to the main administrator and to an administrator holding `node-<name>`. The right `node` manages
  the subsystem and the types but does not moderate materials. User groups never moderate.

#### Settings: features

Exactly twelve native booleans; `defaults.features` is `[]`, so every type stores all twelve.

| Key | Enables | Enforced by the writer when off |
|---|---|---|
| `categories` | primary category `cid` and extra categories `cids`; the primary category decides language and read right | `cid = 0`, `cids = []` |
| `comments` | the shared comment subsystem; a material picks `CommentMode` `Disabled`, `Moderated` or `Open` | only `CommentMode::Disabled` |
| `rating` | the shared rating of scope `node.<name>` | - |
| `favorites` | the shared favorites | - |
| `poll` | a linked poll `_nodes.poll` | no new poll (a stored one may stay) |
| `home` | the start-page mark `_nodes.home` | `home = false` |
| `pinned` | the pin `_nodes.pinned` | `pinned = false` |
| `submit` | the public form | - |
| `moderation` | public submissions go to `Pending` | - |
| `schedule` | a future `published` and an `expires` date | no future publication date, no `expires` |
| `related` | relations of kind `related` | no `related` relation |
| `tree` | the parent relation `parent` (document tree) | no `parent` relation |

Only a moderator sets poll, home, pin and dates; for anyone else these values are refused when non-empty. `views` is
always counted and has no switch. `categories`, `related` and `tree` cannot be switched off while the type still has
categories, `related` rows or `parent` rows (`INVALID features.<key>`).

#### Settings: assets

A map of role name to role definition; `defaults.assets` is `[]`. Role names match `^[a-z][a-z0-9_]{0,49}$`; the key
is stored in `_node_assets.role`. Roles are ordered by `sort`, then by name.

| Key | Type | Default | Rule |
|---|---|---|---|
| `title` | string | required | plain text or a defined language constant, 1-255 characters |
| `intro` | string | `''` | plain text or a defined language constant, up to 1000 characters |
| `kinds` | list | `[]` | unique subset of `file`, `image`, `audio`, `video`; `[]` means every kind the mode accepts |
| `extensions` | list | `[]` | unique `^[a-z0-9]{1,10}$`; narrows the upload rule of the type for local files |
| `maxbytes` | int or null | `null` | at least 1; narrows the upload rule for local files; `null` uses the rule |
| `min` | int | 0 | at least 0; checked before `Pending` and `Published`, not for `Draft` or `Disabled` |
| `max` | int | required | 1 to `limits.maxassets`, not below `min`; checked on every save |
| `canlink` | bool | false | allows an external `http`/`https` source |
| `report` | bool | false | offers the broken-resource report; only for modes `download` and `link`; the type form shows the switch only while the mode select of the role holds one of them and drops it otherwise |
| `mode` | string | required | a key of the closed mode registry below |
| `active` | bool | true | an inactive role keeps its rows but takes no new resource and leaves the standard output |
| `sort` | int | 0 | at least 0 |

Values are native PHP types; `'0'`/`'1'` strings are refused. Unknown keys are refused.

| `mode` | Accepted kinds | Output |
|---|---|---|
| `image` | `image` | `fragments/node/image.html` |
| `gallery` | `image` | `fragments/node/gallery.html` |
| `download` | `file`, `image`, `audio`, `video` | `fragments/node/download.html`; counted, reportable |
| `player` | `audio`, `video` | `fragments/node/player.html` |
| `none` | `file`, `image`, `audio`, `video` | no output of its own (a role named `poster` feeds the player and the card cover) |
| `link` | `file` | `fragments/node/link.html`; external visit, counted, reportable |

- The effective kinds are the kinds of the mode intersected with `kinds`; an empty intersection is `INVALID`.
- A `link` role requires `canlink = true`, `max = 1`, no `extensions`, `maxbytes = null`; a type has at most one
  `link` role. Its source is only an absolute `http`/`https` address, unique within the type among all materials in
  every state (compared byte for byte). The writer checks uniqueness inside the save transaction after locking the
  type row; a configuration change that would make a `link` role hold local files or duplicate addresses is refused.
- A new external source from someone who does not moderate the type is accepted only in a material that goes to
  `Pending`.
- `limits.maxassets` counts every resource row of a material, whatever the role or its state; a role limit and the
  material limit apply together.
- A role cannot be removed while resources of it exist (`INVALID assets.<role>`). Roles carry no rights of their own:
  a resource inherits the state of its material, the read right of the primary category and the extension scope.

#### Settings: integrations

Exactly five keys after the merge; a type stores only the differences.

| Key | Type | Default | Meaning |
|---|---|---|---|
| `search` | bool | false | materials enter the search module |
| `rss` | bool | false | the type has an RSS feed (`getRssFeeds()`) |
| `sitemap` | bool | false | materials enter the sitemap |
| `blocks` | bool | false | the type may feed the file block `blocks/node.php` |
| `seo` | string | `'website'` | `website`, `article` or `news`; the `kind` passed to `setHead()` for the full material |

Comments, rating, favorites, polls, home and points are not repeated here. The `search` and `sitemap` switches are
also edited from the admin screens of those modules, which save through `updateNodeTypePart()` (below).

#### Settings: ext

The settings of the registered extension, filtered by its `filterNodeConfig()`; an empty `ext` key requires an empty
section. The key itself lives only in `_node_types.ext`.

| Extension | Section | Constraints on the rest of the settings |
|---|---|---|
| `support` | exactly `['mail' => bool]` | features `categories`, `comments`, `submit` on and all other nine off; `search`, `rss`, `sitemap`, `blocks` off; `workflow.access` `user` or `group`; no active role; `view.mode = support`; `support.state`/`support.prio` intact |
| `sync` | `[]` | `features.submit` off |

`ext` can change only while the type is disabled and has no material.

### Fields

Field definitions of a type live at `$conf['fields']['node'][<name>]` and are checked by the global
`Field::filterFieldList()` (`core/classes/field.php`), which returns the canonical set ordered by `sort`, then name,
or throws `InvalidArgumentException` with the path of the first error; Node turns it into `INVALID fields.<path>`.
Values are stored in `_nodes.field` as a JSON object keyed by field name. Fields never take part in SQL filtering or
sorting.

| Key | Type | Rule |
|---|---|---|
| `title` | string | plain text or a language constant defined in the site dictionary, up to 255 characters |
| `intro` | string | same contract, up to 1000 characters, `''` when empty |
| `type` | string | key of the closed type registry |
| `default` | typed | matches the base type and `multi`; applied only when a new material leaves an active field empty |
| `options` | array | only the options the type accepts |
| `req` | bool | checked for `Pending` and `Published` only; incompatible with `active = false` |
| `multi` | bool | only `select` supports `true` |
| `active` | bool | an inactive field is neither edited nor shown; its stored value is kept |
| `sort` | int | order in the form and the standard output |

All nine keys are required; strings for booleans or numbers are refused. Field names and select keys match
`^[a-z][a-z0-9_]{0,31}$`.

| Type | Options | Stored value |
|---|---|---|
| `text` | `min`, `max` (ceiling 4096 characters) | trimmed string without line breaks |
| `textarea` | `min`, `max` (ceiling 262144) | string with LF line endings |
| `select` | `items`, `min`, `max` | option key, or a list of keys with `multi` (at most 64 picked, 256 items) |
| `bool` | none | boolean |
| `int` | `min`, `max` | signed 64-bit integer |
| `decimal` | `min`, `max`, `scale` (1-18) | normalized string, at most 65 digits |
| `date`, `datetime` | `min`, `max` | `YYYY-MM-DD`, `YYYY-MM-DD HH:MM:SS` |
| `email` | `min`, `max` (ceiling 254) | domain normalized |
| `url` | `min`, `max` (ceiling 2048) | `http`, `https` or a root-relative path |

A set holds at most 256 definitions; the JSON of one material's values is at most 1048576 bytes. A select item holds
exactly `title`, `active`, `sort`. The writer reports the first refused value as `INVALID fields.<name>.<code>` with
the code `required`, `type`, `format`, `choice`, `min` or `max`. Stored values of inactive fields survive an edit;
names without a definition are dropped on the next change.

### Upload and rating rules

| Area | Key | Form | On create when the input brings none |
|---|---|---|---|
| `config/uploads.php` | `$conf['uploads'][<name>]` | one string of twelve `|`-separated pieces: `extensions`, `maxquota`, `maxbytes`, `maxwidth`, `maxheight`, `maxfiles`, `thumbwidth`, `moderfiles`, `userfiles`, `userupload`, `guestupload`, `guestfiles` | the valid rule a removed module left under one of the nine replaced names, else a copy of `$conf['uploads']['all']` |
| `config/ratings.php` | `$conf['ratings']['node.<name>']` | exactly `active`, `period`, `detail`, `guests`, all strings; `active`/`detail`/`guests` `'0'` or `'1'`, `period` whole seconds | `['active' => '1', 'period' => '2592000', 'detail' => '1', 'guests' => '1']` |

Upload extensions must be supported by the upload service and unique; `userupload` and `guestupload` are 0 or 1; the
other limits are whole numbers of at most 18 digits. The rule is the upload place `<name>.attach` and is edited on the
shared screen `admin.php?name=uploads&op=config`. The directory of a type is `uploads/<name>` with `thumb/` created on
demand by Parser; direct web access to it is refused and files are served through `op=asset` and `go=file`.

### Changing a type

Types change only through `NodeService`:

| Method | Effect |
|---|---|
| `addNodeType(string $name, NodeTypeInput $input): NodeType` | disabled type of version 1, the four areas, `uploads/<name>` with guard files; an existing directory holding anything but guard files is `INVALID directory` |
| `updateNodeType(string $name, NodeTypeInput $input, int $version): NodeType` | replaces title, intro, ext, sort and all four areas; version + 1 |
| `updateNodeTypeStatus(string $name, bool $active, int $version): NodeType` | switching on re-checks everything stored, needs a writable directory and the self-check of the upload root answering `closed`; the current state is a no-op |
| `deleteNodeType(string $name, int $version): void` | refused while materials, categories or user files exist; removes the four sections and `node-<name>` from every administrator; the directory stays |
| `addNodeTypeImport(string $json, string $name = ''): NodeType` | decodes an export and calls `addNodeType()`; import, clone and profiles |

A stale version is `CONFLICT`; a missing type is `NOTFOUND`; a context without `manage` or `super` is `DENIED`.

Shared screens (`admin/modules/fields.php`, `uploads.php`, `ratings.php`, the search and sitemap admin) save one part
of a type through `updateNodeTypePart(string $name, string $part, array $value, int $version): string` in
`core/admin.php` (`$part`: `fields`, `uploads`, `rating`, `integrations`), which rebuilds the whole `NodeTypeInput`
and calls `updateNodeType()` with the version the form carries.

### Shipped profiles

`modules/node/profiles/<name>.json` holds ten type exports. A profile is copied into a new disabled type through
`addNodeTypeImport()`; nothing reads it at runtime. Every profile carries full `settings` and `fields` and empty
`uploads` and `rating`, so a type made from it takes the site's `uploads.all` and the default rating rule. A clean
installation (`addNodeProfiles()` in `core/admin.php`, opened by the mark `node => new` of `config/update.php`)
imports and activates all ten and adds a starter material to `news`; the 6.3 update creates no type.

All ten profiles share `workflow` = `access user`, no groups, both notices on, except `help` (both notices off).

| Profile | Title | Sort | `view.mode` | `ext` | `list` (orders / order dir / limit / alpha / show) |
|---|---|---|---|---|---|
| `news` | `_NEWS` | 10 | `article` | - | all five / published desc / 10 / no / all four |
| `pages` | `_PAGES` | 20 | `article` | - | all five / published desc / 10 / no / all four |
| `faq` | `_FAQ` | 30 | `faq` | - | all five / published desc / 10 / no / all four |
| `help` | `_HELP` | 40 | `support` | `support` (`mail = true`) | published / published desc / 20 / no / category, date |
| `jokes` | `_JOKES` | 50 | `default` | - | all five / published desc / 10 / no / all four |
| `content` | `_CONTENT` | 60 | `article` | `sync` | all five / published desc / 10 / no / all four |
| `links` | `_LINKS` | 70 | `default` | - | all five / published desc / 25 / yes / all four |
| `files` | `_FILES` | 80 | `files` | - | all five / published desc / 25 / yes / all four |
| `media` | `_MEDIA` | 90 | `media` | - | all five / published desc / 25 / yes / all four |
| `docs` | `_DOCS` | 100 | `docs` | - | title, updated, views / title asc / 50 / yes / category, date, views |

| Profile | Features on | Integrations |
|---|---|---|
| `news` | categories, comments, rating, favorites, poll, home, pinned, submit, moderation, schedule, related | all four, `seo = news` |
| `pages`, `faq`, `jokes`, `files`, `media` | as `news` without poll | all four, `seo = article` |
| `links` | as `news` without poll | all four, `seo = website` |
| `content` | categories, comments, rating, favorites, pinned, schedule, related | all four, `seo = article` |
| `docs` | categories, favorites, related, tree | search, sitemap, blocks; `seo = article` |
| `help` | categories, comments, submit | none, `seo = website` |

| Profile | Roles (mode, kinds, min/max, canlink, report, sort) |
|---|---|
| `news` | `cover` (image, image, 0/1, no, no, 10); `gallery` `_ALBUM` (gallery, image, 0/20, no, no, 20) |
| `pages`, `faq` | `download` `_DOWNLOAD` (download, all kinds, 0/10, yes, yes, 10) |
| `files` | `cover` (image, image, 0/1, no, no, 10); `download` (download, all kinds, 1/10, yes, yes, 20) |
| `links` | `cover` (image, image, 0/1, no, no, 10); `link` `_URL` (link, file, 1/1, yes, yes, 20) |
| `media` | `poster` `_NODE_POSTER` (none, image, 0/1, no, no, 10); `source` `_NODE_PLAY` (player, audio+video, 1/10, yes, no, 20); `gallery` (gallery, image, 0/30, no, no, 30); `download` (download, all kinds, 0/10, yes, yes, 40) |
| `jokes`, `content`, `docs`, `help` | none (editor attachments only) |

`cover` is titled `_NODE_COVER`. Fields: `files` has `release` (`_VERSION`, text, max 100, sort 10) and `site`
(`_URL`, url, sort 20). `media` has twelve optional single fields, sort 10 to 120 in this order: `subtitle`
(`_NODE_SUBTITLE`, text 100), `year` (`_NODE_YEAR`, int 1-9999), `director` (`_NODE_DIRECTOR`, text 100), `cast`
(`_NODE_CAST`, text 255), `creator` (`_NODE_CREATOR`, text 100), `runtime` (`_NODE_RUNTIME`, text 100), `language`
(`_LANGUAGE`, text 100), `notes` (`_NOTE`, textarea 262144), `format` (`_NODE_MFORMAT`, text 100), `quality`
(`_NODE_QUALITY`, text 100), `filesize` (`_SIZE`, text 100), `release` (`_NODE_RELEASE`, text 100). The other
profiles have no fields.

In `help` a request is created `Published` and `NodeSupport` narrows every read to the owner and the moderators; its
files are editor attachments. In `content` the body belongs to the source (`_node_sync.url`, `refresh`): the admin
form takes address and period instead of a body. In `links` the site address lives only in the `link` resource.

### Export format

One UTF-8 JSON file `node-<name>.json`, at most 1 MiB:

```json
{
  "format": "slaed.node",
  "version": 1,
  "type": {"name": "", "title": "", "intro": "", "ext": "", "sort": 0,
           "settings": {}, "fields": {}, "uploads": {}, "rating": {}}
}
```

- `NodeQuery::getNodeTypeExport(string $name): string` writes exactly these nine type keys with the effective
  settings and both rules in full; it refuses (`INVALID export`) a type whose upload or rating rule is broken.
- The import checks the exact top keys, `format`, `version === 1`, the exact nine type keys and their native types,
  then runs the normal create path, so every setting, field, role, rule and extension is checked before any write.
  A non-empty name from the form replaces the name of the file. An import never overwrites an existing type.
- No ids, state, record version, dates, materials, files, secrets or paths are exported.

### Configuration protocol

All functions live in `core/system.php`.

```php
function getConfig(bool $fresh = false): array
function setConfigFile(string|Closure $fp, array $arr = [], array $act = []): bool
function getConfigCode(array $data): string
function setConfigSource(string $file, string $code): bool
function getConfigJournal(): array
function setConfigRestore(string $force = ''): bool
```

| Function | Contract |
|---|---|
| `getConfig()` | Returns the `config/local.php` snapshot; on a miss rebuilds it under the lock `FileManager::getPathLock(CONFIG_DIR)`. While a marker exists it assembles the snapshot in memory (touched files from the journal side, or from disk when the journal is unreadable or a snapshot fails its hash) and publishes nothing. `$fresh = true` is the publication step; it returns `[]` on failure and leaves the previous `local.php`. |
| `setConfigFile()`, string form | One independent source; scalars become strings, LF line endings. Refused for `system.php`, `header.php`, `chmod.php`, `local.php` and for `node`, `fields`, `uploads`, `ratings`. |
| `setConfigFile()`, Closure form | The only writer of the four shared areas. The closure gets `array $base` (fresh areas, no file wrappers) and `Closure $save`, and returns `'committed'`, `'aborted'` or `'uncertain'`. `$arr`/`$act` must be empty; a nested call is refused. |
| `$save(array $package, array $proof = []): bool` | Callable once; exactly the four areas, only string, int, bool, null and arrays. Only areas that differ from `$base` are written; an unchanged package succeeds without a journal. |
| `getConfigCode()` | The one exporter: standard header plus a deterministic `return [...]`. |
| `setConfigSource()` | Temporary neighbour, PHP parse check, atomic rename, OPcache invalidation; an empty code removes the file. |
| `getConfigJournal()` | `[]` without a marker; else `op`, `phase`, `time`, `types`, `proof`, `files` (`old`/`new`/`now` hashes, `state`), `verdict` (`old`/`new`/empty), `why` (`journal`, `backup`, `source`, `proof` or empty). |
| `setConfigRestore()` | Finishes an operation under the lock: without an argument by the verdict, with a Node proof by a locking read of the type row (`proof.new` version means new, `proof.old` means old). Republishes `local.php`, clears the cache (admin entry), re-checks hashes, removes marker and operation directories. Repeatable. |

Pipeline of both forms: take the config lock; refuse when `storage/backup/config/marker.json` exists; write both
snapshots and `journal.json` under `storage/backup/config/<operation>/`; write the marker (operation, touched files,
touched Node type from the proof); replace the sources; mark the journal `committed`; publish through
`getConfig(true)` and `setConfigRestore('new')`. Outcomes: `committed` finishes the new snapshot; `aborted`, any other
answer or an exception without proof returns the old snapshot in the same call; `uncertain` or an exception after a
proof was saved keeps marker and journal for the restore. `true` means a finished, consistent result.

#### Lock order of a type operation

The sequence of `NodeService::setTypeWrite()`, which every Node writer of the shared areas follows:

1. `setConfigFile()` takes the config lock and re-reads the four areas.
2. `checkBaseNode()` refuses (`CONFLICT`) when the global Node settings loaded by the request differ from the fresh
   ones, because the settings check reads the loaded `$conf['node']`.
3. The directory lock of `uploads/<name>`.
4. `BEGIN`, the type row by `SELECT ... FOR UPDATE`, the version and data checks, the SQL.
5. `$save($package, ['kind' => add|update|status|delete, 'name', 'id', 'old', 'new'])`, then `COMMIT`.

Afterwards the directory lock is released, and a published change is logged with its proof and administrator id.
A writer never calls `setConfigFile()` inside a closure and never takes the config lock after locking a type row.

On read, `types.<name>.version` must equal `_node_types.version`; a missing section or a different version re-reads
the configuration and the row once, and a version that still differs makes the type unavailable (logged). A type
named by the marker, or every type when the marker cannot be read, is unavailable until the restore
(`admin.php?name=config&op=restore`: GET shows the journal, POST with token runs `setConfigRestore()`); public
requests for it answer 503. New configuration writes are refused while the marker exists.

The limits screen `admin.php?name=node&op=config` writes only `limits`, after re-checking every stored type and the
base of a new type against the new values; the first type that fails is named and nothing is written.

## Routing

### Dispatch

`index.php` reads `name`, `op` and `file` from the request. When the effective name (explicit `name`, or the module
drawn from `$conf['module']` for the start page) is a key of `$conf['node']['types']`, the request goes to the fixed
`modules/node/index.php`; no SQL runs before that decision and the name never builds a path.

- `name=node` and any `file` other than `index` answer 404; the technical name has no public route.
- `$conf['name']` keeps the public type name; layout and block positions come from `$conf['modules']['node']`; the
  language file is `modules/node/lang/<language>.php` through `getLang('node')`.
- A registered name never falls back to a module directory of the same name. A name outside the registry goes the
  ordinary module route.
- `modules/node/index.php` first checks `getConfigJournal()` (503, see above), then the closed operation table
  `getNodeOps()`, then the method, then dispatches.
- The type comes from `getNodeRoute()`: `getNodeTypeMap()[$conf['name']]`; a missing, broken or (for this visitor)
  disabled type answers 404. A disabled type is visible to the main administrator, the Node manager and its
  moderators.

### Public operations

| `op` | Methods | Parameters | Purpose |
|---|---|---|---|
| (empty) | GET, HEAD | `cat`, `num`, `let`, `order`, `dir` | list of the type or of one category |
| `view` | GET, HEAD | `id` (global `_nodes.id`) | full material |
| `add` | GET, HEAD, POST | POST: `action` (`preview`/`submit`), `token`, form fields | public form, preview, submission |
| `asset` | GET, HEAD | `id` (`_node_assets.id`) | one structured resource |
| `report` | POST | `id` (resource), `token` | broken-resource report |
| `support` | POST | `id`, `token`, `state`, `version` | owner closes or reopens a request; only for a type with extension `support` |

An unknown operation, or `support` on another type, answers 404. A known operation with another method answers 405
with an `Allow` header. Comments, rating and favorites use their own global routes.

### Response codes

| Code | Cause |
|---|---|
| 301 | explicit `order`/`dir` equal to the type default: redirect to the clean list URL (keeps `cat`, `let`, `num > 1`) |
| 301 | an address of a removed module in `_node_legacy`: `view`/`broken` with an old `id` to the migrated material; the list, `liste`, `best`, `pop`, `closed` to the list of its type (keeps `cat`). Asked by `getNodeLegacyUrl()` in `core/system.php` only where the answer would be 404: an unknown `id` of `view`, an unknown operation, and in `index.php` a name that is neither module nor type (`pages` → `docs`) |
| 302 / 303 | redirect after a write (`setRedirect()` sends 303 for POST); 302 for an external `download`/`link` source |
| 400 | malformed `cat`/`num`; bad or disabled `let`; `order` unknown, or other than `published` and outside `list.orders`; `dir` not `asc`/`desc`; form `action` not `preview`/`submit` |
| 403 | guest non-moderator on a `support` list or material; form not open to the visitor; bad token on `add`, `report`, `support`; `support` by a non-owner or to another state; writer `DENIED` |
| 404 | unknown type or op; `cat` without categories or unreadable; page past the end; missing or malformed `id`; material or resource missing, closed, foreign or unreadable; `add` without `submit`; report refused |
| 405 | method not in the operation table, with `Allow` |
| 409 | writer `CONFLICT` (stale ticket version) |
| 422 | form or field error on `add`; writer `INVALID`; non-numeric `support` state |
| 429 | a second report within 60 seconds in one session (`Retry-After`); writer `LIMITED` (`limits.send`) |
| 503 | type held by an unfinished configuration operation (`Retry-After: 60`) |

Reader refusals map through `getNodeStatus()` (`NOTFOUND` 404, `DENIED` 403, `INVALID` 422, `CONFLICT` 409,
`LIMITED` 429); a storage failure goes to the shared error handler and is logged.

### List and material

- `cat` and `num` accept `^[1-9][0-9]{0,9}$` up to 4294967295; `num` defaults to 1. The page count is computed first,
  so `num > pages` answers 404 before the reader sets the page.
- Without `order`/`dir` the list uses `list.order`/`list.dir`. With only `order`, the direction is the type default
  for the default key, `asc` for `title`, `desc` otherwise. `let` goes to `NodeQuery::setNodeLetter()`; the letter
  navigation keeps the category and links only the letters with materials (`getLetterNavi($mod, $cat, $nums)`).
- The type intro is shown only on the first page without category or letter. A `support` list holds only the
  visitor's own requests.
- `view` reads through `NodeQuery::getNode()`; the id must belong to the route type. Related materials (at most 500)
  become cards; poll, rating (`node.<name>`), favorites, tree and comments appear only with their feature, comments
  only when the material's mode is not `Disabled`. A GET counts the view after the answer
  (`NodeService::updateNodeViews()` as a deferred task); HEAD counts nothing.

### Public form

- POST requires `token` and the captcha of the action `comment`; a failed captcha stores no upload and answers 422
  with the form filled again.
- `preview` checks the whole input through `NodeService::getNodePreview()` and renders the view template, writing
  nothing. `submit` calls `NodeService::addNode()` with the workflow state: `Published` redirects to the material
  (`_NODE_ADDED`), `Pending` to the list (`_NODE_PENDING`) and queues the admin notice with `notify.pending`.
- A non-moderator sends no poll, home, pin or dates and gets `CommentMode::Open` where the type has comments. There
  is no public editing of an existing material.

### Resources and attachments

- `asset`: `NodeQuery::getNodeAsset()` checks type, role, state, dates, primary category read right and extension
  scope. An external source of a `download` or `link` role redirects; of other modes answers 404 (their templates show
  the address). A local file is served by `getFileStream()` from `uploads/<name>` outside `thumb/`, inline except for
  `download`, with a private revalidated HTTP cache (one range 206, bad range 416, several ranges the whole file).
- Only a GET of a `download` role or an external `link` visit calls `updateNodeAssetHits()`, right before the 200
  body, a 206 from byte zero or the 302; HEAD, 304, 416 and later ranges count nothing. Point action `download` or
  `visit`.
- An attachment is served by the file route of the core, `go=file` (`setFileRoute()`): the query must be exactly `go`,
  `own=node`, `key`, optional `thumb=1` plus `id`, or `go`, `own=node`, `name=<type>`, `key`, optional `thumb=1` and
  `preview=1` without `id`; anything else is 404, a method other than GET or HEAD 405. The type of a saved material is
  read from its row. `key` (at most 255 bytes, a bare name not starting with a dot or a space) must carry an extension
  the upload rule of the type still allows and the upload service supports. The saved form grants a name that the
  `intro` or `body` of the readable material carries (`Parser::getAttachList()`); the preview form grants only the
  visitor's own new upload (account, or session token of a guest) unless the visitor moderates the type. `thumb=1`
  serves `thumb/<key>` after the original is granted. A type held by a configuration journal grants nothing. Every
  refusal is the same 404; nothing is counted; the preview answer is not cached.
- `report`: after the token and the session window, `updateNodeAssetReport()` stores the first report once; a role
  without `report` answers 404. The answer returns to the referring page or the material (`_NODE_REPORTED`).

### Support request

`POST op=support` belongs to the owner of a request of a `support` type. `state` must be the value of
`support.state.staff` (reopen) or `support.state.closed` (close); the stored assignment and priority are sent back
from the current card, never from the request. A stale `version` answers 409; success redirects to the request with
`_NODE_SSAVED`.

### Canonical and robots

Node calls the existing `setHead()` and builds no canonical, Open Graph or schema of its own.

| Page | `kind` | `robots` | Canonical |
|---|---|---|---|
| list, category, page | `collection` (`website` on the start page) | default `index, follow` | `index.php?name=<type>` with `cat` and `num > 1`; the site root for the start page without `cat`/`num` |
| list with `let` or a non-default sort | `collection` | `noindex, follow` | none (`setHead()` drops it for noindex) |
| full material | `integrations.seo` | default | the view URL of the material (`getSeoUrl()` with the title slug) |
| any page of a `support` type | as above | `noindex, nofollow` | none |
| form, error (status 400+) | - | `noindex, follow` by `getSeoRoute()` | none |

The list passes title, category title, `cid` and, outside the start page, the type intro as description (a category
page gets none). The material passes title, category title, `cid`, the first 160 characters of the plain intro,
author, publication and update time and the absolute cover URL. The breadcrumb category is the `cid` the page names;
without it, `cat` of the query counts only when `NodeQuery::checkNodeCategory()` grants it.

### Admin operations

Entry `admin.php?name=node` (the file name comes from `$afile`) is open to the main administrator and to an
administrator whose `_admins.modules` holds `node` or any `node-<type>` (`is_admin_modul('node')`). Inside,
`checkNodeManage()` (main administrator or `node`) guards type screens, and `checkNodeModer()` (main administrator or
`node-<name>`) guards materials. POST forms carry the token of scope `node` (`checkAdminPost('node')`).

| `op` | Methods | Parameters | Right | Purpose |
|---|---|---|---|---|
| `show` (default) | GET, HEAD | `type`, `status` (NodeStatus value, default `Published`), `num` | moderator | materials of the moderated types; for a `support` type the ticket queue with `state` (default the `staff` value, `all` for any), `aid`, `prio`, `num` |
| `add` | GET, HEAD, POST | `type`; POST `status` (default `Draft`) | moderator | type picker without `type`; create; redirect to `edit` |
| `edit` | GET, HEAD, POST | `id`, `type`; POST `version`, `action` (`save`/`keep`) | moderator | edit; `keep` reloads the current version with the posted values without writing |
| `status` | POST | `id`, `type`, `status`, `version` | moderator | change state; sends the result mail |
| `delete` | POST | `id`, `type`, `version` | moderator | physical delete |
| `report` | POST | `id` (resource), `type`, `useful` (`0`/`1`) | moderator | decide a report (`deleteNodeAssetReport()`) |
| `support` | GET, HEAD, POST | `id`; POST `aid`, `state`, `prio`, `version` | moderator | working card of a ticket; 404 for other types |
| `sync` | POST | `id`, `type` | moderator | fetch the source immediately (`updateNodeSync()`); 404 for types without `sync` |
| `types` | GET, HEAD | - | manage | types and remains of removed modules |
| `type` | GET, HEAD, POST | `type` (edit), `profile` (create from profile); POST `version`, `action` | manage | type form, create, update |
| `typestatus` | POST | `type`, `active` (`0`/`1`), `version` | manage | enable or disable |
| `typedelete` | POST | `type`, `version` | manage | delete an empty type |
| `remains` | POST | `modul` | manage | delete comments and favorites of a removed module key |
| `clone` | GET, HEAD, POST | `type`; POST `tname` | manage | export and import under a new name |
| `export` | GET, HEAD | `type` | manage | download `node-<name>.json` |
| `import` | GET, HEAD, POST | POST `file` (`.json`, at most 1 MiB), `tname` | manage | import as a new disabled type; a file over 1 MiB, a failed upload and another extension answer 422 with their own text before the writer runs |
| `config` | GET, HEAD, POST | POST `maxassets`, `maxlist`, `syncbatch`, `send` | manage | edit `limits`; the other Node settings are shown only |
| `info` | GET, HEAD | - | entry | module documentation |

Answers: a successful write redirects (Post/Redirect/Get). Codes: 400 bad filter value, 403 bad token or missing
right, 404 unknown op, type, material or profile, 405 with `Allow`, 409 stale version (`CONFLICT`; `config` also when
a configuration operation is pending), 422 invalid input, 502 a failed source fetch of `sync`, 500 storage. A stale
material edit shows the differing sections and a link to the current record; a stale state change or delete asks to
open the record and confirm again. GET never changes data.

The type form edits identity, `list`, `view.mode` (a select of `NodeQuery::MODES`), `features`, `workflow` (access through the category access widget:
`0|0` all, `1|0` users, `2|<ids>` groups), roles and `integrations`. Fields, upload and rating rules and extension
settings stay those of the stored type, the profile, or the defaults of a new type. A new type starts from `defaults`
with every feature off and no role; a profile replaces each section of that base shallowly with its own.

## Rendering

The core returns data, the theme owns HTML. `NodeView` prepares arrays and never renders, queries or checks rights;
the controller (`modules/node/index.php`) and the file block choose templates; templates contain no SQL and no access
decisions.

### Template files and fallback

Every public theme carries the Node templates inside its own tree:

```text
templates/<theme>/
├── partials/node/list.html, view.html
└── fragments/node/card.html, block.html, image.html, gallery.html, download.html, player.html, link.html, tree.html
```

`getNodeTplName(string $kind, string $name, NodeType $type): string` in `core/system.php` answers
`node/<view.mode>/<name>` when the mode is not `default` and `Template::checkTemplateFile($kind, ...)` finds that file
in the current theme, otherwise the base `node/<name>`, memoized per request. The module and `blocks/node.php` both
use it, so a mode overrides `list`, `view`, `card`, `block`, `tree` and each resource fragment (`image`, `gallery`,
`download`, `player`, `link`) independently, for example `fragments/node/faq/card.html` before
`fragments/node/card.html`.

- A mode overrides only the files that differ. There is no fallback between themes, no per-material template, and no
  path from the request or from settings; a missing base file is a theme error.
- File names inside `node/` carry no `node-` prefix; shared elements (`alert`, `link`, `button`, form fields,
  `field-value`, `list`, pager) stay in the theme root folders. Resource mode `none` has no fragment.
- The admin side uses the shared `templates/admin` templates and has no Node templates or modes. A search hit of Node
  is shown by the shared row of the search module.

### Display modes of the lite theme

| `view.mode` | Files | Output |
|---|---|---|
| `default`, `article` | base files | the base set is the article |
| `docs` | `fragments/node/docs/card.html`, `partials/node/docs/view.html` | table-of-contents row: title, category chip, one-line plain intro; view without author and views chips |
| `faq` | `fragments/node/faq/card.html`, `partials/node/faq/view.html` | native `<details>` accordion: question in `<summary>`, intro answer and a read-more link; view without author and views chips |
| `files` | `fragments/node/files/card.html`, `partials/node/files/view.html` | card with direct download button, size and hits of the first `download`-mode resource; view shows resources above the text |
| `media` | `partials/node/media/list.html`, `fragments/node/media/card.html`, `partials/node/media/view.html` | tile grid; tile with cover, play icon, title and the `year` field (or the date); view shows player and gallery, then fields, then text; related as tiles |
| `support` | `fragments/node/support/card.html`, `partials/node/support/view.html` | state chip; view adds state and priority chips, the owner's close/reopen form and the staff card link |

The cover of a card is the first image of the role `poster`, then of roles in mode `image` or `gallery`
(`getNodeCover()`); the same cover becomes `og:image` of the full material.

### NodeView keys

A `Node` accepts modes `list`, `view`, `card`; a `NodeTarget` (light related target) accepts only `card`. Another mode
or a material of another type throws `NodeException::INVALID`. Every result has exactly these keys:

| Key | Content |
|---|---|
| `id`, `type`, `mode` | global id, type name, prepared mode |
| `href` | view URL (`getSeoUrl()` with title slug), `''` for an unsaved preview |
| `title` | plain title |
| `intro` | plain text of the rendered intro (scripts and styles dropped) |
| `intro_html` | intro rendered by `Parser::filterContent()` with trusted tags honoured (heading offset 1 in `view`, 2 otherwise) |
| `body_html` | rendered body, only in mode `view` |
| `author`, `ahref` | user name with profile URL, or the guest name without URL |
| `ctitle`, `chref` | primary category title (language constant resolved) and category list URL |
| `date`, `date_iso`, `mtime_iso` | formatted publication date, ISO publication and update time |
| `views`, `comnum`, `rating`, `ratings` | counters; `rating` is `Rating::getAverage()`; a target has `views = null` |
| `fields` | `Field::getFieldView()` result, keyed by field name |
| `assets` | resources grouped by active role in role order |

A light target leaves texts, author, category, dates, fields and assets empty. Only `*_html` values may be printed
unescaped; URLs are passed in `href`, `ahref`, `chref` and the resource `href`/`rhref`.

Resource item (inside `assets[<role>]`): `id`, `kind`, `role`, `href`, `rhref`, `name`, `title`, `intro`, `mime`,
`size`, `stext` (formatted size), `width`, `height`, `duration`, `hits`, `islink`. `href` is the `op=asset` URL of a
saved resource; for an external source of mode `image`, `gallery`, `player` or `none` it is the source itself; for an
unsaved local file it is the `go=file` preview URL. `rhref` is the `op=report` URL when the role reports. The
stored `src`, the report state and its author never leave the reader.

Field view entry: `name`, `type`, `label_text`, `hint_text`, `value`, `value_text`, `value_html` (only `textarea`,
Parser output), `value_href` (`url`, or `mailto:` for `email`), `items` (select options, each `value` and
`label_text`). Inactive, empty and orphaned values produce no entry; `false` and `0` do.

### Template keys

Shared view labels (`getNodeViewVars()`): `is_date`, `is_author`, `is_category`, `is_views` (from `list.show`),
`date_label`, `views_label`, `author_label`, `read_label`, `download_label`, `hits_label`.

Material facts (`getNodeMetaVars()`), read from the row and from caches the request already holds, so a list adds no
statement per material: `is_live` (a stored material, not a preview or a light related target), `is_comm` and
`comm_label` (the `comments` feature on and the material's mode not `Disabled`), `fresh_html` (the shared "new" badge
of a publication already reached), `cicon_html` and `ctone` (the icon name of the main category's `img` and its tone
from `ordern`), `fav_html`, `rating_html`, and `moder_html` (for a moderator of the type the speed dial of
`getNodeModerDial()`: the editor and every allowed move of the state, posted to the panel with `refer` so the page comes
back).

| Template | Called by | Keys |
|---|---|---|
| `partials/node/list.html` | `setNodeList()` | `head_html`, `intro`, `cats_html`, `letters_html`, `items_html`, `pager_html`, `empty_alert` |
| `fragments/node/card.html` in a list | `setNodeList()` | NodeView (`list`) + view labels + material facts + `cover`, `download` (first resource of a `download`- or `link`-mode role or `[]`), `ext` |
| `fragments/node/card.html` as related card | `setNodeView()` | NodeView (`card` of a target) + view labels, `is_views = false`, `cover = ''` |
| `partials/node/view.html` | `getNodeViewHtml()` | NodeView (`view`) + view labels + material facts + `fields_html`, `assets_html`, `rels_html`, `rels_label`, `poll_html`, `tree_html`, `share_url`, `share_title`, `user_html`, `ext` |
| resource fragment of a role | `getNodeAssetView()` | `items` (resource items + `is_video`, `is_audio`), `role_title`, `poster` (first `poster` href), `token`, `report_label`, `download_label`, `visit_label`, `hits_label` |
| `fragments/node/tree.html` | `getNodeTreeData()` | `title`, `trail` (`href`, `title`), `items` (`href`, `title`, `is_current`, `kids`, `is_more`), `first`, `is_before`, `is_after`, `prev_href`, `prev_title`, `next_href`, `next_title`, `toc_label`, `prev_label`, `next_label` |
| `fragments/node/block.html` | `blocks/node.php` | NodeView (`card` of a full material); the items go into the shared `fragments/list.html` |
| `fragments/field-value.html` | `getNodeViewHtml()` | `label`, `value_html`, `value_text`, one per field view entry |

- `ext` is the extension data of the material (`NodeExtension::getNodeData()`), `[]` for a standard type. For
  `support` it holds `state`, `prio`, `version`, `activity`, `aid`, `aname` (moderators only) plus `state_label`,
  `prio_label`, `is_closed`, `is_author`, `state_title`, `prio_title`, `is_owner`, `action`, `token`, `next`,
  `switch_label`, `card_href`. `sync` gives nothing to public views.
- The view carries the three speed dials of a comment in `sl-meta-actions`: share (`share_url`, a stored material of a
  type without `support`), the author menu `user_html` (private message and profile of a registered author) and the
  moderator menu `moder_html`, then the anchor `#node-<id>`. Poll, rating, favorites and tree are rendered by their own
  subsystems and handed in as HTML; a preview has none of them and no related cards.
- The special layouts `docs/card.html` (table of contents), `faq/card.html` (question and answer) and
  `media/card.html` (tile) keep their compact form without the material facts.
- The preview wraps the view template in the shared `partials/preview.html` (`title`, `body_a`).
- The tree branch shows the root trail, up to ten siblings on each side of the current document and its first twenty
  children, plus previous and next document in tree order; it is omitted for a single document or a material outside
  the reader's tree.
- The block file `blocks/node.php` reads `_blocks.param` as canonical JSON `{"type","mode","limit"}` (`mode` `last`
  or `home`, `limit` 1-50, capped by `limits.maxlist` and each type's `list.limit`); only active types with
  `integrations.blocks` are fed.

### Forms and editor

`intro` and `body` use the shared `getTplTextarea()` with `mod = <type name>` and `store = nodes.intro` (TEXT) or
`nodes.body` (MEDIUMTEXT); the editor is `$conf['editor']`, the upload place `<name>.attach`. Form rows are neutral
data (`label`, `for`, `hint`, `hint_id`, `field`, `full`) laid out by `fragments/form-field-row.html` on the site and
by the admin form templates. Resource rows use `getFileManagerField()` inside the repeatable `fragments/repeat.html`
(`plugins/system/slaed.js`; without JavaScript up to four empty rows per role, never above its `max`). Fields come
from `Field::getFieldForm()`.

Messages after a write (`_NODE_ADDED`, `_NODE_PENDING`, `_NODE_REPORTED`, `_NODE_SSAVED`) are one-time flashes of
`setRedirect()`, printed as the shared `alert` at the top of the content by `setFoot()`; a page with a pending flash is
not cached.

### Theme hooks

Lite classes in `templates/lite/assets/css/theme.css`: `sl-node-toc`, `sl-node-toc-intro`, `sl-node-faq`,
`sl-node-faq-body`, `sl-node-file`, `sl-node-tiles`, `sl-node-tile`, `sl-node-tile-cover`, `sl-node-tree`,
`sl-node-tree-cur`, `sl-node-tree-more`, `sl-node-tree-pager`. Articles carry `id="node-<id>"`. A theme or showcase
that needs one type of a kind asks `getNodeModeType(string $mode): ?NodeType` (the first active type without an
extension whose `view.mode` matches) instead of naming a type.

## Integrations

Node joins the global subsystems of SLAED without copying them and without any list of type names in shared
code. The only registry is `_node_types` with `config/node.php`; subsystems resolve types and materials through
`NodeQuery` and write through `NodeService`, never through the Node tables directly.

### Shared rules

- A global table stores the public type name in `modul` and the global `_nodes.id` as its target id (`cid` of
  `_comment`, `fid` of `_favorites`, `mid` of the rating tables). A type name is immutable, so stored references
  never need a cascading rename.
- Targets are resolved with `NodeQuery::getNodeTarget(string $type, int $id, bool $any = false): ?NodeTarget`
  or `NodeQuery::getNodeTargetList(array $refs, bool $any = false): array` (`id => type name` in,
  `id => NodeTarget` out, input order kept, at most two statements). A missing, closed, unpublished,
  out-of-window or foreign-type material and an unknown or disabled type all answer as absent. `$any = true`
  (never passed by public readers) lets a type moderator read every state.
- A global owner that writes against a material locks it inside its own transaction first:
  `NodeService::setTargetLock(int $id, NodeType $type): void` takes the `_node_types` row, then the `_nodes`
  row, as locking reads; `NodeService::getLockedTarget(int $id, NodeType $type, bool $any = false): ?NodeTarget`
  then reads the target with the reader's predicate. No plain read precedes these locks in the transaction.
- Counters are stored with `NodeService::updateNodeComments(int $id, NodeType $type, int $count): void` and
  `NodeService::updateNodeRating(int $id, NodeType $type, int $score, int $ratings): void`; neither moves
  `_nodes.version` or `_nodes.updated`.
- Before a `comment`, `rate`, `favorite`, `asset` or `report` action the type's extension is asked
  `checkNodeAction()` (it can only narrow); after the action really happened the same transaction calls
  `updateNodeAction()`, whose failure rolls both back.
- Moderating a type is the right `node-<name>` (`NodeContext::$mods`, `is_moder()`, `checkUploadModer()`),
  never the `modul` value read as the right of an old module.

Switches: `config/node.php -> types -> <name> -> settings -> integrations` has the booleans `search`, `rss`,
`sitemap`, `blocks` (default `false`) and `seo` = `website` | `article` | `news` (default `website`). A subsystem
takes part only for an active type whose switch is on.

### Request helpers (`core/system.php`)

| Function | Purpose |
|---|---|
| `getNodeContext(): NodeContext` | Request snapshot built once from trusted state; `task` is always `false` |
| `getNodeTypeMap(): array` | `name => NodeType` the request may receive, read once; no query without registered types |
| `getNodeReader(?NodeType $type = null): NodeQuery` | Reader of the request context, bound to the type's extension when given |
| `getNodeWriter(?NodeType $type = null): NodeService` | Writer with the shared `Point`, bound to the type's extension when given |
| `getNodeHandler(NodeType $type): ?NodeExtension` | Extension through the closed factory, `null` for a standard type |
| `getNodeTitleMap(array $refs): array` | `id => title` of readable targets, chunked by `NodeQuery::getTargetSize()` |
| `getNodeModeType(string $mode): ?NodeType` | First active type without extension whose `view.mode` is `$mode` |
| `getNodeTplName(string $kind, string $name, NodeType $type): string` | `node/<mode>/<name>` when the theme has it, else `node/<name>` |
| `addNodeMail(array $list, string $title, string $text): bool` | Queues one Node notice per address through `Mail` |

### Batch size

`limits.syncbatch` (shipped `500`) bounds the material ids Node hands to a global subsystem per pass. Ids are
cast to positive integers and de-duplicated, then split before the boundary (1200 ids: 500 + 500 + 200).
`NodeQuery::getTargetSize()` answers `min(500, syncbatch)`, and 500 for a missing or broken configuration;
`getNodeTargetList()` refuses more refs than that. Every caller chunks by the same method: `getNodeTitleMap()`,
`Comment::getUserList()`, `Comment::getUserCount()`, the related cards of a material, the relation check of a
write and the sitemap walk, so lowering `syncbatch` below stored relations never refuses a read. Long walks use the cursor
`id > <last id> ORDER BY id LIMIT n`, never `OFFSET`, and resume from the last confirmed cursor.

The document tree is an internal read: `NodeQuery::getNodeTree()` uses batches of `NodeQuery::TREEPART = 500`
regardless of `syncbatch`. Search keeps `search.slimit`, RSS `rss.max`, mail `mail.batch`; the sync queue has its
own job limit.

### Categories

Types use the shared `_categories` table with `modul = <type>`, its tree, languages and rights. `_nodes.cid` is
the one main category and decides language, read right (`pread`) and main route; `_node_categories` holds only
extra links; a category list unions both sources and returns each material once.
`NodeQuery::checkNodeCategory(NodeType $type, int $cid): bool` is the category read right of the context: a
public list outside it is `404`, and `setHead()` uses it so a closed category never shows its title.

### Comments

`Comment` (`core/classes/comment.php`) keeps its single constructor. `Comment::MODULES` holds only `account`
and `voting`; any other `modul` naming a registered type is a Node target, resolved lazily by a
`NodeQuery`/`NodeService` pair built from the class's `Database` and `getNodeContext()`.

- A discussion, `Comment::getTargetMode()` and the form need a readable target, the `comments` feature and no
  refusal by `checkNodeAction(..., 'comment')`; otherwise the mode is `Disabled` and the discussion empty.
- Lists outside the target page (`getUserList()`, `getAdminList()`, the profile feed of `core/user.php`,
  `getUserCount()`) drop comments whose target the viewer cannot read, with batch reads of
  `NodeQuery::getTargetSize()` per slice.
  `getModuleList()` hides a type the administrator does not moderate.
- A write on a material locks it (`setTargetLock()`) before the comment row, re-reads the mode under the lock,
  writes the counter from a locking count, then calls the extension, then `Point`.
- The action counts at the first visibility of the comment (at once in open mode, or on approval). Only then
  `updateNodeAction($type, $target, 'comment', $uid)` runs, `$uid` being the author, never the approving
  moderator; later edits, hides and deletes do not call it again.
- A write owning its transaction runs `BEGIN`, its work and `COMMIT` (private `setWriteBegin()`, `setWriteUndo()`,
  `setWriteDone()`); a joined write runs inside the open transaction of its owner (Node, account deletion, poll
  deletion), which commits or rolls back.
- `Comment::deleteTarget(string $mod, array $ids, array $uids = []): bool` locks the comment rows by ascending id,
  then all authors plus `$uids` in one `Point::setUserLocks()`, then compensates each award.
  `NodeService::deleteNode()` passes the material author so his publication award is compensated afterwards.
- A `support` type sends no administrative new-comment mail; its extension notifies instead.

### Rating

The scope of a material is `node.<name>`. `getRatingService()` builds `Rating` with a read adapter that resolves
the scope through `getNodeReader()->getNodeTarget()` or, as the first statement of a vote,
`getNodeWriter()->getLockedTarget()`, requires the `rating` feature and asks `checkNodeAction(..., 'rate')`. The
write adapter stores the aggregate with `updateNodeRating()` and, when the vote count grew, calls
`updateNodeAction(..., 'rate', $uid)`. Rating creates no `Point` events. Contract: docs/RATINGS.md.

### Favorites

`addFavorite()` (`core/user.php`) runs one transaction for a Node type: `setTargetLock()`, the member's
`_users` row `FOR UPDATE` (so parallel requests never pass `favorites.favorites` together), `getLockedTarget()`,
the `favorites` feature and `checkNodeAction(..., 'favorite')`, the `_favorites` row,
`updateNodeAction(..., 'favorite', $uid)`, the point event `favorite` with source `<type>:<id>`.
`getFavoriteList()` takes Node titles from `getNodeTitleMap()`, so unreadable materials drop out.
`NodeService::deleteNode()` removes the material's favorites in its own transaction.

### Polls

`_nodes.poll` is the id of a shared `voting` poll, `0` for none. Only a moderator of a type with the `poll`
feature assigns or changes it, and only to an existing poll; an unchanged stored link survives when the feature
is switched off. The view shows the poll through `getVotingView()` while the feature is on; voting keeps its own
handler, rights, CSRF and vote limit (legacy `_rating` rows with `modul = 'voting'`).

Assigning, changing or clearing a link takes the named SQL lock `node.poll.<id>` (`GET_LOCK`, 5 s wait, ascending
ids, `0` skipped) before `BEGIN`; a timeout is `CONFLICT`; `RELEASE_LOCK` follows the
transaction. Deleting a poll (`modules/voting/admin/index.php`) takes the same lock, then calls
`NodeService::deleteNodePoll(int $id): void` inside its transaction: requires `NodeContext::$polls`, locks every
`_node_types` row and then the poll's materials by ascending id, and sets their `poll` to `0` (version and
`updated` move). Poll and material deletions never delete each other.

### Search

`getSearchMods()` (`modules/search/index.php`) lists the modules of `search.mods` (a Node type name there is
ignored) and then every active type with `integrations.search`. `getSearchNode()` reads all selected types
through one reader: `NodeQuery::setNodeSearch(string $text)` (literal substring of title, intro or body, up to
255 characters, escaped for `LIKE`), `setNodeType()` with the extension for one type or `setNodeTypes()` for
several. The total is `min(getNodeCount(), search.slimit)`; only rows up to the requested page end are read, in
pages of the smallest `list.limit` (at most `limits.maxlist`). There is no index or rebuild job.

### RSS

`getRssFeeds(): array` is the one list of feeds: every active type with `integrations.rss`. The feed route `index.php?go=rss` (`getRssChannel()` in `core/user.php`), the alternate link of the head
and the picker of `modules/rss` read only this list. No name selects the start module when it has a feed, else
the first feed; an unknown name is `404`. A Node feed reads one page of `min(num, list.limit, limits.maxlist)`
items (`num` bounded by `rss.min`/`rss.max`), `published desc` with pinned first where the type has `pinned`;
`cat` applies only to a type with categories. Items come from `NodeView::getNodeView(..., 'card')`; relative
`href`/`src` in descriptions become absolute and all addresses are XML-escaped.

### Sitemap

`addSitemapTask(bool $force = false): array` streams XML into `sitemap-<n>.xml`, opens a new file after 50000 URLs
and joins several files with an index. For each active type with `integrations.sitemap` it writes the module URL,
the open categories (`pread` level `0` without groups; on a multilingual site only `lang = ''` or the site
language) and the materials read by `NodeQuery::getNodeSitemap(int $after = 0, int $limit = 500): array` - rows
`id`, type name, `title`, `cid`, `ctitle`, `published`, `updated` of every sitemap type after a global id cursor,
as a guest context of the site language (none on a site with one language), in batches of
`NodeQuery::getTargetSize()`. A failure removes the files of the run.
The HTML map lists a type with its open categories and leaves materials to the XML.

### Blocks

One file block, `blocks/node.php`, serves every Node feed. Instance parameters are canonical JSON in
`_blocks.param` (`VARCHAR(255) NOT NULL DEFAULT ''`) with exactly `type`, `mode`, `limit` in that order: `type`
is `''` (mixed feed of all active `blocks` types) or one such type; `mode` is `last` or `home` (a named type in
`home` needs its `home` feature); `limit` is `1..50`, further bounded by `limits.maxlist` and each type's
`list.limit`. `getNodeBlockParam()` answers `null` for anything else, including a stale type.

`admin/modules/blocks.php` edits and validates these fields and flags an invalid instance with `_BLOCKPROBLEM`;
at render time such an instance shows `_BLOCKPROBLEM` to a moderator only, without a log line. A single-type block
orders `published desc` whatever `list.orders` says; pinned come first where a selected type has `pinned`. An
empty result sets the content to `null`, so `setBlockView()` prints nothing (no title, no `_BLOCKPROBLEM2`).

### Home page and display-mode lookups

The home page is the ordinary list of the type the start module names. `NodeQuery::setNodeHome(bool $home =
true)` filters the `home` flag for the block's `home` mode; it is not a route. Code that needs "the type showing
a kind of content" asks `getNodeModeType($mode)`, never a type name:

| Caller | Modes | Use |
|---|---|---|
| `modules/presentation/index.php` | `article`, `docs`, `files` | count, category count, latest material |
| `admin/modules/monitor.php` `getMonitorDbStats()` | `article`, `files` | dashboard counts |
| `templates/lite/index.php` `getTemplateFaq()` | `faq` | header marquee |

The profile (`core/user.php`) counts an author's materials with `NodeQuery::getNodeAuthorStat()` and lists the
latest per type; `support` types appear only in the account menu, never in profiles.

### Editor attachments and the file manager

Each type owns the upload area `uploads/node/<type>`: editor `[attach]` files and local `_node_assets` sources in its
root, editor thumbnails in `thumb/`. The root of the types is the constant `NODE_DIR`, and `getUploadFolder()` in
`core/system.php` names the folder of an owner relative to `UPLOADS_DIR` for every builder of an upload path: a
registered type, or a type named through its flag, answers `node/<type>`, a module the folder of its own name. The editor passes the type name as `mod`, so `getTplTextarea()` picks
`$conf['uploads'][<type>]` and the place `<type>.attach`; the only structured-resource picker is
`getFileManagerField()` (docs/WINDOW.md). Node never renames or copies files; `Upload` names them once.

- The moderator exception of the upload helpers (`checkUploadModer()`, `checkEditorUploadAccess()`,
  `getEditorFileOwner()`) is the stored right `node-<type>`, never a form value.
- On create and content change `NodeService` takes the names from `Parser::getAttachList(string $src): array` of
  the new `intro` and `body`. A name the stored text of the same material already carried may stay under another
  authorized editor. A newly added name must be an existing managed file with an allowed extension in the type
  root (or `thumb/`) and must belong to the writer (member: own upload; guest: upload of the current session)
  unless he moderates the type. A new local resource `src` is checked the same way, also when the resource id is
  kept. Checked on preview and again on save; a refusal writes nothing.
- The parser renders `[attach]` of a stored material as `index.php?go=file&own=node&id=<nid>&key=<name>`
  (plus `thumb=1` for an existing thumbnail) when it receives the file context `['node', $nid]` in
  `Parser::filterContent()` or `Parser::filterDoc()`; the address comes from `FileAccess::getFileUrl()` and the
  cache key includes the context. Calls without a context are not Node. A comment of a Node type renders with the id
  of its material, so its attachments take the same route and the rights of the material. The route `go=file`
  decides through `FileAccess::getFilePath()`, whose `node` adapter is `NodeService::getNodeFile()`.


## Extensions

An extension adds behaviour that features, fields, resources and view modes cannot express. A type has at most
one, named by `_node_types.ext`. Two exist: `support` (`NodeSupport`) and `sync` (`NodeSync`).

### The contract

`core/classes/node/extension.php`:

```php
interface NodeExtension {
    public function filterNodeConfig(array $config, array $settings, array $fields): array;
    public function filterNodeData(NodeType $type, array $data, ?Node $node = null): array;
    public function getNodeScope(NodeType $type): array;
    public function checkNodeAction(NodeType $type, Node|NodeTarget $node, string $action): bool;
    public function updateNodeAction(NodeType $type, NodeTarget $node, string $action, int $uid): void;
    public function addNodeData(Node $node, array $data): void;
    public function updateNodeData(Node $before, Node $after, ?array $data): void;
    public function deleteNodeData(Node $node): void;
    public function getNodeData(NodeType $type, array $nodes, string $mode): array;
}
```

| Method | Contract |
|---|---|
| `filterNodeConfig()` | Checks and canonicalizes `settings.ext` against the effective standard settings and checked fields; runs inside `NodeQuery::filterNodeSettings()` on every read and write of a type |
| `filterNodeData()` | Checks and canonicalizes `NodeInput::$ext` before the transaction; `$node` is the stored material on change, `null` on create |
| `getNodeScope()` | Exactly `['join' => string, 'where' => string, 'params' => array]` narrowing every read of the type (alias `n`), values only as parameters, applied before count and paging; other shapes are `INVALID scope` |
| `checkNodeAction()` | Only `comment`, `rate`, `favorite`, `asset`, `report` (others throw `INVALID`); may forbid, never allow what the core refused |
| `updateNodeAction()` | Same names, after the real action, inside the owner's transaction; changes only extension rows; `$uid` is the acting user, never an approving moderator |
| `addNodeData()`, `updateNodeData()`, `deleteNodeData()` | Change only extension rows inside the `NodeService` transaction; `$data = null` means a pure state change; delete runs before the `_nodes` row goes |
| `getNodeData()` | One batch read for a page of accessible `Node`/`NodeTarget`, `id => array`; the template gets it as `ext`, `NodeView` is unchanged |

Write order: extension input is normalized before the transaction; inside it the stored material is read,
standard data changes, one extension method runs, then `COMMIT`. Any failure rolls back both parts. Network and
other irreversible work never runs inside these methods.

### The closed factory

```php
function getNodeExtension(string $key, Database $db, NodeContext $context): ?NodeExtension;
```

`core/classes/node/ext/load.php` holds the fixed map `'support' => ['support.php', 'NodeSupport']`,
`'sync' => ['sync.php', 'NodeSync']`; a stored or requested key is looked up, never turned into a path or class.
`''` returns `null`; an unknown key throws `NodeException::INVALID`. Constructors:
`NodeSupport::__construct(Database $db, NodeContext $context)` and
`NodeSync::__construct(Database $db, NodeContext $context, Feed $feed)` with `new Feed($conf['rss'] ?? [])`.

`NodeQuery::setNodeExtension(?NodeExtension $ext): self` and the fifth argument of the `NodeService` constructor
bind an extension; both refuse (`INVALID extension`) an extension on a standard type and any class other than the
one the factory makes for the type's key. Mixed reads (`setNodeTypes()`, `getNodeTargetList()`,
`getNodeSitemap()`) ask the factory per type. For a standard type `NodeInput::$ext` must be `[]`; `_nodes` never
stores it.

### Lifecycle

- No extension version, no `_node_extensions` table, no per-extension installer: extension tables live in
  `storage/update/sql/table.sql` and change with the regular schema update; the settings shape is versioned by
  `config/node.php` and re-checked before activation.
- `_node_types.ext` changes only on a disabled type without rows in `_nodes` (`updateNodeType()` answers
  `INVALID ext` otherwise). Extension rows go with their material (`ON DELETE CASCADE` and `deleteNodeData()`).
- Disabling keeps key, settings, materials and extension data; enabling re-runs the full check.
- An extension never bypasses `NodeService` for ordinary create, update, state change or delete, never takes a
  class, table or file name from the request and never builds theme HTML. The one exception is the background
  write of `NodeSync` below.

### Support

`NodeSupport` (`core/classes/node/ext/support.php`) makes its type a private Ticket System: a material is a
request, global comments are the correspondence, categories are departments, and `_node_support` holds the
working card: `nid` (unique, FK `ON DELETE CASCADE`), `aid` (`0` = unassigned), `state` (`<= 2`), `prio`
(`<= 3`), `version`, `activity`; indexes `queue (state, prio, activity, id)`, `admin (aid, state, activity, id)`.

The state and priority maps live only in `config/node.php -> support`: `state = [staff => 0, author => 1,
closed => 2]`, `prio = [low => 0, normal => 1, high => 2, urgent => 3]`. The class refuses (`INVALID support.*`)
a map with other names, non-integers, values other than `0..n-1` or priorities not rising from `low` to `urgent`.

- `filterNodeConfig()`: `settings.ext` is exactly `['mail' => bool]`; the type needs `categories`, `comments`,
  `submit` on and `moderation`, `rating`, `favorites`, `poll`, `home`, `pinned`, `schedule`, `related`, `tree`
  off, all integrations off, `workflow.access` `user` or `group`, no active resource role, `view.mode = support`.
- `filterNodeData()` accepts only `[]`; `getNodeScope()` gives a moderator everything, a member `n.uid = :owner`,
  a guest nothing; `checkNodeAction()` forbids `comment` on a closed request or one without card.
- `updateNodeAction()` (`comment` only) locks the card; unless closed, an owner reply sets `staff`, any other
  `author`, with `activity = NOW()`, `version + 1` and the other side's notice.
- `addNodeData()` needs a registered owner and open comments, inserts an unassigned `staff`/`normal` card and
  queues the new-request notice; `updateNodeData()` accepts only `null`/`[]` and keeps comments open;
  `deleteNodeData()` deletes the card; `getNodeData()` returns the card, `aname` for moderators only.

```php
public function updateNodeSupport(int $id, int $aid, int $state, int $prio, int $version): void;
public function getNodeSupportList(NodeType $type, int $page, int $limit, ?int $state = null, ?int $aid = null, ?int $prio = null): array;
```

- `updateNodeSupport()` changes the card at the expected version in one statement. A moderator sets assignment
  (`0`, or an administrator who is super or holds `node-<type>`), state and priority; the owner may only move his
  own request between `staff` and `closed` and must send the stored `aid` and `prio`. Background context
  `DENIED`, stale version `CONFLICT`, unreadable request `NOTFOUND`. Closing and reopening move `activity`;
  repeating stored values writes nothing; card changes send no mail.
- `getNodeSupportList()` is the administrative queue, moderators only: published requests, `null` filter lifted,
  `aid = 0` for unassigned, `limit` up to `limits.maxlist`, order `s.prio DESC, s.activity ASC, s.id ASC`;
  returns `['nodes' => NodeTarget[], 'ext' => [id => card], 'count' => int]`.
- Routes: public `POST op=support` (owner, CSRF, card version, state `staff` or `closed`; `aid`/`prio` come from
  the stored card); administrative `GET|POST op=support` for the full card, whose discussion is read whole in one
  pass by `Comment::getThread()`. The ordinary administrative list of the type is the queue.

Mail (`ext.mail = true`, default of the `help` profile) goes through `addNodeMail()`: a new request to every
subscribed administrator (`_admins.smail = '1'`, request language on a multilingual site) who is super or holds
`node-<type>`; an owner reply to the assigned one while still subscribed and entitled, else to all of them; a staff
reply to the owner. Nobody is told of his own action; texts carry site, title, side and link, never the reply. A
failed read or queue row is logged and costs only the notice.

### Sync

`NodeSync` (`core/classes/node/ext/sync.php`) keeps one RSS/Atom source per material in `_node_sync` and stores
the canonical Markdown of `Feed` in `_nodes.body`. Table: `nid` (unique, FK `ON DELETE CASCADE`), `url` (2048),
`refresh` (`0` or `300..31536000`, CHECK), `due`, `checked`, `synced`, `etag` (255), `modified` (100, ASCII),
`fails`, `error` (255); index `due (due, id)`.

- `filterNodeConfig()`: `settings.ext` must be `[]` (transport is `config/rss.php`, the period is per row) and
  `features.submit` must be `false`, because only a moderator sets a source.
- `filterNodeData()`: moderators only (`DENIED`); exactly `url` and `refresh`; `url` normalized by the static
  `Feed::getFeedUrl()` (external `http`/`https`, no credentials, default port, no DNS), at most 2048 bytes;
  `refresh` `0` (manual only) or `300..31536000`.
- `getNodeScope()` narrows nothing, `checkNodeAction()` forbids nothing, `updateNodeAction()` does nothing.
- `addNodeData()` inserts the row with `due = NOW()` for a period, `NULL` for manual; the body starts empty.
- `updateNodeData()`: `null` leaves the row; a form never changes the body. A new URL clears `etag`, `modified`,
  `checked`, `synced`, `fails`, `error` and makes the row due now; a new period alone keeps them and recomputes
  `due` from `checked`. No network.
- `getNodeData()` answers only mode `admin` for a moderator (`url`, `refresh`, `due`, `checked`, `synced`,
  `fails`, `error`); public views never see the URL or the error.

```php
public function updateNodeSync(int $id): array;
public function updateNodeSyncList(int $limit): array;
```

- `updateNodeSync()`: manual check by a moderator of the type, refused for a background context; answers
  `['id' => int, 'status' => 'updated'|'unchanged'|'failed'|'skipped', 'error' => string]`.
- `updateNodeSyncList()`: background context only, `$limit` `1..50`; one statement selects due rows of active
  `sync` types outside the trash, `ORDER BY s.due, s.id`; answers the scheduler shape `status`, `message`,
  `extra` (checked, updated, unchanged, failed, skipped).
- One check: snapshot read without lock; `Feed::getFeedContent($url, $etag, $modified)` outside any
  transaction; then a transaction locks `_nodes` and `_node_sync` and writes only if URL and `_nodes.version`
  match the snapshot and the material is not deleted. A new body that fits `checkEditorTextRoom(..., 'nodes.body')`
  is written by one conditional `UPDATE` of `body`, `updated`, `version + 1` with the source row. `304` or an
  identical body touches only the source row. A failure keeps text and validators, increments `fails` and retries
  after 300 s doubled per failure, capped at 86400 s; `error` holds a short code only.

Scheduler job `nodesync` (`*/5 * * * *`, `lock_timeout = 180`, `manual = 1`, `settings.limit = 10`) runs
`addNodeSyncTask()`, which builds `new NodeContext(0, [], 0, [], false, false, '', '', true)` and calls only
`updateNodeSyncList()`. Like `nodepublish` it is registered in `config/scheduler.php`, the system map of
`getSchedulerJob()`, `addSchedulerSystemJob()` and `setUpdateRun()` of `update.php`;
`admin/modules/scheduler.php` bounds `limit` by `SCHED_LIMITS = ['nodepublish' => 500, 'nodesync' => 50]`, and the
scheduler lock prevents parallel runs. A manual check is the administrative `POST op=sync` (CSRF, moderator,
extension `sync`, no URL from the request): success redirects to the edit form, failure is `502` with the safe
code. Nothing syncs during a public request.


## Security

### Trust boundary

- Input is read once with `getVar()`; core classes receive normalized typed values.
- `getNodeContext()` builds `NodeContext` from trusted state: `$mods` from stored `_admins.modules` keys
  `node-<name>`, `node` as `$manage`, `voting` as `$polls`. The request `name` never adds a right. The constructor
  refuses negative ids, malformed or repeated groups and names, administrative rights without an administrator,
  and a background context (`task`) carrying any identity; only the scheduler adapters build `task = true`.
- The administrator form builds `_admins.modules` from the allowed registry only, without repeats.
- Readable configuration is `node`, `fields.node`, `uploads.<type>` and `ratings.node.<name>` of a resolved type.
  Ordinary operations never write configuration and never use configuration or request values as file, class,
  table or column names; dynamic names come from closed maps in code. All SQL uses named parameters.

### Material input

- `final readonly NodeInput` has sixteen values: `cid`, `cids`, `aname`, `title`, `intro`, `body`, `fields`,
  `poll`, `home`, `comon`, `pinned`, `pubdate`, `expires`, `rels`, `assets`, `ext`; system columns cannot be
  assigned. State is a separate `NodeStatus`; `addNode()` allows `Draft`, `Pending`, `Published` by context and
  workflow.
- `cids`, `rels`, `assets` are full sets (ids at most `limits.syncbatch` each, resources at most
  `limits.maxassets = 100` plus each role's `max`); `fields` is the full set of active editable fields. Unknown
  keys, repeats, foreign ids, objects and excess depth are refused before the transaction; an unknown field name
  is dropped by `Field`.
- A relation is only `rid`, a registered `type`, a non-negative `sort`; existence, readability, type and absence
  of a `parent` cycle are checked first (the tree walk uses locking reads under the type lock).
- A resource is exactly `id`, `kind`, `role`, `src`, `name`, `title`, `intro`, `sort`; a positive `id` must
  belong to the edited material, `null` creates one. Metadata, counters, report and dates never come from a form;
  role `kinds`, `extensions`, `maxbytes`, `min`, `max`, `canlink`, `report` are enforced by the server.
- Extra fields (full rules in [Field](#field)): at most 256 definitions, `select` 256 options
  and 64 picks, canonical JSON 1048576 bytes, `decimal` 65 digits with scale up to 18; field URLs `http`, `https`
  or root paths only. A type setting may only tighten global limits.

### Writes and concurrency

- Changing routes require `POST` and CSRF (`checkSiteToken()` publicly, `checkAdminPost('node')` in the panel);
  administrative `status`, `delete`, `report`, `sync` accept only `POST`, and every successful administrative
  `POST` redirects to a canonical `GET`.
- `updateNode()`, `updateNodeStatus()`, `deleteNode()` take the expected `_nodes.version` from the `POST` body and
  put it into the SQL condition; `updateNodeType()`, `updateNodeTypeStatus()`, `deleteNodeType()` do the same with
  `_node_types.version`, raised by one on success.
- A stale version is `409`. There is no merge, no silent version swap, no force flag and no server draft: the
  submitted values live only in the returned form. State change and delete need the record reopened.
- Type operations require `NodeContext::$manage` or `super` through the four `NodeService` methods; a type
  moderator cannot, and controllers never write `_node_types`. Published type changes are logged with the
  administrator id.
- `Node::$ip` is `null` outside an authorized administrative context; the report author `ruid` reaches only the
  service and the moderator review.

### Public routes

`modules/node/index.php` answers the closed map of `getNodeOps()`: `''` and `view` (`GET`, `HEAD`), `add` (`GET`,
`HEAD`, `POST`), `asset` and `attach` (`GET`, `HEAD`), `report` (`POST`), plus `support` (`POST`) for a `support`
type. A pending configuration journal holding the type answers `503` (`Retry-After: 60`) before `op`, method or
type are examined. Unknown `op` is `404`, wrong method `405` with `Allow`, `cat`/`num`/`id` must be positive up
to 4294967295, an unknown or (for this visitor) disabled type is `404`. `NodeException` codes map to `404`,
`403`, `422`, `409`, `429` (`NOTFOUND`, `DENIED`, `INVALID`, `CONFLICT`, `LIMITED`), else `500`; pages show a safe
text, never the exception message.

- `add`: needs the `submit` feature and the workflow's access; `POST` with `action=preview|submit` and CSRF.
  Every guest `POST` passes the `comment` captcha (only for guests, only when the comment captcha is on); a
  failure is `422` and stores nothing. Preview validates fully without a write transaction. Submission gives
  `Pending` or `Published` from `features.moderation` and the user's groups in `workflow.publish`, never from the
  request; `Pending` mails the `node-<type>` administrators when `workflow.notify.pending` is on.
- Write window: `addNode()` for a non-moderator throws `LIMITED` (`429`, `_CERROR5`) while a material from the
  same `_nodes.ip` is younger than `limits.send` seconds (shipped `60`, `0` off), read through the index
  `ip (ip, created, id)` with the database clock; preview skips it; it is best effort under parallel requests.
- A form resource file is stored only when `checkEditorUploadAccess()` of `<type>.attach` allows it (moderator,
  `userupload`, `guestupload`), for active non-`link` roles, while the role has fewer than `max` sources and the
  request stored fewer than `maxfiles` (`0` = unlimited).
- A non-moderator posts only into categories whose `pview` shows them, whose `ppost` admits him and whose language
  is shared or that of the context (`NodeQuery::getNodePostCats()`) and relates only published readable targets. Editing is for the main
  administrator and type moderators, so a later-closed category or unreadable relation never blocks an edit.
- A new external resource URL from a non-moderator is accepted only in a material going to moderation (else
  `INVALID assets.<n>.src`, which `getNodeFault()` names as resource `n + 1`). The form offers the link input of a
  resource only to a moderator of the type or to a writer whose material goes to `Pending` (`getNodeFlowState()`). `op=asset` redirects externally only for roles in mode `download` or `link`; other
  modes show the address and their `op=asset` is `404`. The server never fetches an external resource URL.
- A `support` list answers `403` to a guest who does not moderate the type.

### Controlled files

Direct access to `uploads/node/<type>/` (root, editor files, `thumb/`) is impossible for every type: the whole
`uploads/` lies at the project level, outside the document root `public/`, so no server executes or lists an upload.
An address under `uploads/` reaches the light path of `index.php` (`core/stream.php`), which serves the folders of
`getUploadPublic()` alone, and `node` is not among them.

- No folder carries a guard file. `tests/Support/route_web.php` and `web_probe.php` run the real light path; the file
  `open` of `web_probe.php` stands for a server that still serves the upload root.
- Enabling a type asks the self-check for the upload root: `checkPrivateRoots(['uploads' => UPLOADS_DIR])` requests
  `<homeurl>/uploads/check.txt` and judges the body. Only `closed` passes; `open` (the marker served, whatever the
  status) or `unknown` (no answer, no http(s) site address) is `INVALID directory` and the type stays off.
- A type is created only over an empty directory or one holding empty directories alone (hidden files, previews and
  thumbnails are user files). Deleting a type re-checks all material states, categories and files and never
  removes the directory.
- `op=asset&id=<id>`: the id is resolved by `NodeQuery::getNodeAsset()` (resource, material, route type, category
  right, extension scope, active role, one statement) into an immutable `NodeAsset`; no path, name, MIME or URL
  comes from the request. A local `src` is canonical and must resolve by `realpath()` inside `uploads/node/<type>`,
  outside `thumb/`.
- `go=file` with `own=node`: the query is exactly `go`, `own`, `key`, optional `thumb=1` and either `id=<nid>` or `name=<type>` with `preview=1`.
  `NodeService::getNodeFile(NodeType $type, int $id, string $key, bool $thumb, Comment $com): string` accepts only a bare
  name of the attachment grammar (`[A-Za-z0-9_\-. ]`), an allowed extension and a `realpath()` inside the root; a
  stored material grants only names its own `intro`/`body` carry, in an `[attach]` or, inside a `[usehtml]` block, as
  the file address of that same material (read by `getNodeContent()`, no `LIKE`, no other materials), and names its
  published comments carry in an `[attach]` (all comments not deleted for a moderator), whatever the form of the
  name; the preview (`id` 0) grants only a whole managed name (`FileManager::checkFileName()`) of the visitor's own
  upload unless they moderate the type. A writer binding a new name, by tag or by such an address of any material of
  the type, passes the owner check of `checkNodeFiles()`. Every refusal is the same `404`.
- Resources have no rights of their own: they inherit material state, main-category read right and extension
  limits.
- `getFileStream(string $path, string $name, string $mime = 'application/octet-stream', bool $inline = false,
  bool $cached = false, ?callable $start = null): void` gets an authorized canonical path only and runs no SQL.
  `inline` works only for a closed list of raster image, audio and video types; everything else is an
  `application/octet-stream` attachment with `nosniff`. Access is re-checked before every `304`. `Range` only on
  `GET`: one satisfiable range `206`, bad `416`, several ranges or a mismatching `If-Range` a full `200`.
- The `$start` callback runs before headers for a full body or a range from byte 0 and calls
  `NodeService::updateNodeAssetHits()` (one atomic `UPDATE`); `HEAD`, `304`, `416`, later ranges and refusals
  count nothing. `hits`, `reported`, `ruid` never touch versions or `updated` dates.
- `POST op=report` needs CSRF and is limited to one per minute per session (`429` with `Retry-After`);
  `updateNodeAssetReport()` stores the first `reported`/`ruid` once. Only the main administrator or a type
  moderator clears it with `deleteNodeAssetReport(int $id, NodeType $type, bool $useful)`, which locks and
  re-reads the row in the same transaction as the award, so two moderators never reward twice. All resource
  methods re-authorize through the reader.

### External feeds

`Feed` accepts only external `http`/`https` without credentials. Before every request and redirect it resolves
the host again, refuses loopback, private, link-local, multicast, reserved and unspecified addresses (the policy
of `Upload`) and pins the connection to the checked address, defeating DNS rebinding. `config/rss.php` bounds a
fetch: `bytes = 2097152`, `timeout = 10` for all steps, `redirects = 3`; a malformed bound (including `max`)
refuses the fetch with `config`. XML is parsed without network or external entities; received text is data with
every ASCII punctuation mark escaped, so no BB, Markdown or HTML survives and raw XML never reaches `_nodes.body`.
Errors are short codes (`url`, `timeout`, `config`, `support`, ...) without body, path, credentials or query
string; diagnostics go through `Logger`.

### Configuration writes

Changes of `config/node.php`, the `node` area of `config/fields.php` and a type's rules in `config/uploads.php`
and `config/ratings.php` run as the Closure mode of `setConfigFile()`: the whole cycle under lock, temporary
files, PHP syntax check, atomic replacement with rollback of already replaced files, only native scalars, `null`
and arrays. An unknown `COMMIT` keeps the journal and holds the affected type (`503` publicly) until
`setConfigRestore()` finishes it; the administrative `POST name=config&op=restore` decides a Node operation's side
by a locking read of the type row. `config/local.php` is replaced whole and atomically. A type is activated only
after the single check of settings, fields, resources, directory and extension; guest access with moderation off
needs explicit confirmation in the type form.


## Performance

### Statement budgets

The budget is the difference of `$db->qnum` (and `$db->sqltime`) right before and after the Node handler; boot,
theme, blocks and independent global subsystems are outside it, and the `NodeContext::$groups` query runs in
`getNodeContext()` before it. The numbers are upper bounds of the successful path.

| Scenario | Usual | Ceiling |
|---|---:|---:|
| List | 3, with categories 5 | 7 |
| Full material | 5 | 6 with related cards; a tree type adds `floor(N/500) + 1` tree batches and a category-right prefetch |
| Controlled `[attach]` | 2 | 2 |
| Resource read or `HEAD` | 2 | 2 |
| Start of a counted download | 4 | 4 |
| Resource report | 4 | 4 |
| Administrative list (`setNodeSets(false)`) | 3 | 3 |
| Edit form | 5 | 5 |
| State change | 6 | 7 when a `_node_publish` job is created or removed |
| Physical delete | 5 statements on Node tables | Point and global cleanup measured apart |

- A list reads type, page and count (`uname`, `ctitle` joined); extra categories, relations, related cards and
  resources add one batch each; categories add the category-right prefetch (once per type and `NodeQuery`) and
  the page's extra-category batch.
- A view reads type, main row, extra categories, relations, resources, and related cards as the sixth; tree
  batches read the index `tree (tid, status, id)`, independent of page size and depth.
- `[attach]` uses `getNodeContent()`, never `getNode()`. `getNodeAsset()` is one joined statement; a write
  re-authorizes with one primary-key read before its counter or report update.
- A delete reads head and type, locks type, material (version check) and resources, then runs one `DELETE`. A zero
  point reward runs no SQL; a non-zero one is measured apart.

`NodeQueryTest` pins the list budgets per probe type, `NodeServiceTest` the state change (`<= 6`,
`<= 7` with a job) and the delete trace, `NodeRouteTest` the view with tree batches; `tools/node-profile.php`
enforces the ceilings on a large database.

Timing targets, as `p95` with Xdebug off and a warm MariaDB: model read and preparation 150 ms, file access check
without transfer 50 ms, administrative write without upload 300 ms, average statement 10 ms (the debug panel
threshold). The profile reports timing; only statement counts are hard gates.

### Read performance rules

- Type metadata is cached inside the current `NodeQuery` only; settings and fields come from the loaded `$conf`.
  No other runtime cache: no file cache of materials or SQL results, no Redis, no cache table.
- Lists do not select or decode `_nodes.field` unless the view uses fields; `body`, `fields`, `cids`, `rels`,
  `assets` stay `null` until requested and then load in one batch per page; models never query lazily. No N+1 for
  authors, fields, categories, rating or favorites.
- A page holds `1..list.limit` rows, `list.limit <= limits.maxlist = 100`. The letter filter uses the `title`
  index with one checked letter or digit and a parameterized `LIKE` prefix. SQL never filters or sorts on field
  JSON. `views` and `hits` move by one atomic statement; the report queue uses `(reported, id)`.

### The single lock order

Operations take these in this order and never take an earlier one while holding a later one:

1. the configuration lock of `setConfigFile()` (type operations);
2. `FileManager::getPathLock(NODE_DIR.'/<type>')` (type operations, material writes with files);
3. named locks `node.poll.<id>`, ascending (poll links and poll deletion);
4. `BEGIN`;
5. `_node_types` rows, ascending id;
6. `_categories` rows, ascending id;
7. `_nodes` rows, ascending id;
8. specialised rows: `_node_assets`, `_node_publish`, extension rows, comment rows;
9. Point `_users` rows, ascending id, the operation's whole set at once via `Point::setUserLocks(array $uids): bool`;
10. the Point journal.

Rating locks its target, actor and vote rows after the owner's locks and never the participating user. Network
access and upload bodies are handled before any lock.

Why: every writer of a material queues on the type row, then the material row, so no two operations cross (a
comment write and a delete, say) and no transaction reads a snapshot older than its wait - which is also why
`deleteNode()` and `Comment` do their plain reads before `BEGIN` and validate them by the locked row's version.
Tree-ancestor walks and the uniqueness check of an external `link` URL run under the type lock. Category moves
and deletions lock type, categories, materials (busy or stale `409`, rights `403`, bad assignment `422`).

`FileManager` locks belong to the request: a map of canonical key (private `getLockKey()`: `realpath()` of the
deepest existing part plus the rest, lower case on Windows) to handle with a nesting count, so re-entry never
self-blocks. A directory below an area takes the area root first, so `Upload` and `FileManager` wait for the holder
of a type root. An area is a folder of `UPLOADS_DIR`, and below `NODE_DIR` the folder of one type, so the writers of
two types never wait for each other and the root of the types is never locked. Filesystem work is not transactional: an unbound upload stays with its owner,
sources are never deleted before the links commit, and guards are never removed.

`Point` runs before the owner's `COMMIT`, inside a `SAVEPOINT` when a transaction is open (owning
`BEGIN`/`COMMIT` otherwise). A lost outer transaction surfaces as `RuntimeException`, which Node turns into
`STORAGE` (docs/POINTS.md).

`nodepublish` (`* * * * *`, `lock_timeout = 180`, `settings.limit = 50`, bounds `1..500`) runs
`NodeService::updateNodePublishList(int $limit = 50): array` in a background context; its queue work is measured
apart from the read and state-change budgets.


## Testing

### Suites and layout

`phpunit.xml` defines the suites `Unit` (`tests/Unit`) and `Validation` (the rest of `tests`), bootstrap
`tests/bootstrap.php`. Node tests are `#[Test]` classes in `tests/Unit`. Most start a CLI probe from
`tests/Support` as a child process and assert on its JSON; there is no base test class, no `tests/Fixtures/node`,
no SQL dump and no copy of the schema - probes execute the shipped `storage/update/sql/table.sql` and update SQL. Most
files also have a static half that reads the sources.

| Test | Tests | Driver | Covers |
|---|---:|---|---|
| `NodeModelTest` | 20 | `node_probe.php` | models, state matrix, context, class maps, schema install and update |
| `NodeQueryTest` | 25 | `node_probe.php query` | reader API, access, paging, targets, tree, sitemap, budgets, context |
| `NodeConfigTest` | 17 | `node_probe.php service` | type writes, versions, import, commit crash, restore, race |
| `NodeServiceTest` | 29 | `node_probe.php material` | material writes, moves, sets, locks, counters, reports, delayed publication |
| `NodeRouteTest` | 41 | `route_probe.php` + `secure`, `tree`, `modes`, `seo`, `head` | public and admin HTTP, cache, files, form rules, tree, view modes, SEO |
| `NodeSupportTest` | 13 | `route_probe.php support` | comments on Node, private requests, card, mail |
| `NodeSyncTest` | 10 | `route_probe.php sync` | source form, `op=sync`, `nodesync`, scripted transport |
| `NodeIntegTest` | 14 | `route_probe.php integ` | rating, favorites, poll, home, search, RSS, sitemap, blocks |
| `NodeGuardTest` | 10 | `route_probe.php guard` | output escaping, favorites with points, poll right |
| `NodeIntegrityTest` | 7 | `route_probe.php intact` | rows left by real requests, checked by SQL |
| `NodeProfileTest` | 14 | `install_probe.php` | `setup.php` stop by stop and part by part, its refusals, the ten profiles; panel and public walk |
| `UpdateSiteTest` | 9 | `install_probe.php update` | 6.2 -> 6.3 update of `tests/Fixtures/update62` and `update62early` by `update.php` over HTTP |
| `Update{Config,Mails,Setup}Test` | 6, 4, 10 | `update_probe.php config`/`mails`/`setup` | settings carry-over, newsletter, registry, both preflights |
| `Update{Points,Ratings,Fields}Test` | 6, 8, 9 | `update_probe.php` (`points`)/`ratings`/`fields` | data update units |
| `PointTest`, `RatingTest` | 20, 18 | `point_probe.php`, `rating_probe.php` | the classes |
| `PointOwnersTest`, `RatingOwnersTest` | 7, 5 | static | owner wiring, labels in six locales |
| `FieldTest`, `FieldViewTest` | 10, 6 | static; `contract_probe.php fieldpost` | `Field`, its form and view outputs |
| `FeedTest` | 21 | static; `contract_probe.php rssview` | RSS/Atom to Markdown, scripted DNS/HTTP rules |

`FieldIdsTest` covers the form-field helper `getFieldIds()`, not Node. Related: `ConfigFileTest` and
`FileManagerLockTest` (`config_probe.php`), `CacheContractTest` (static), `FileStreamTest` (`web_probe.php`),
`SchedulerLockTest` (`scheduler_probe.php`).

### Probes

Each probe is CLI-only (`tests/Support/probe_boot.php` refuses a web request, because the directory is served by
the stand), boots the real core like `index.php`, redirects `CONFIG_DIR`, `CACHE_DIR`, `BACKUP_DIR`,
`UPLOADS_DIR`, `LOGS_DIR`, `COUNTER_DIR` into a scratch root as needed, and prints
`{"error": "", "clean": bool, "runs": {...}}`; `clean` confirms that disposable databases and files are gone. The
stand's database, `config/`, `storage/` and `uploads/` are never written.

- `node_probe.php <scratch> [mode]`: none - class loading, then a fresh install and an install without Node
  brought up by the update, twice; `query` - probe types in scratch configuration, one filled database, the
  shipped reader beside a factory with a test extension, child `context` per visitor; `service` - scratch
  sources, backup, cache and uploads, children `crash`, `restore`, `race`; `material` - reader and writer copies
  with a recording extension, children `mtree`, `mcrash`, `mlink`, `mpub`, `mpubcrash`, `msched`, `mupload`,
  `mcomm`, `mdelete` for races, crashes, upload rights and comments.
- `route_probe.php <scratch> [mode]`: one database from `table.sql`, scratch configuration with the probe types,
  scratch uploads with release guards, the real `index.php` and `admin.php` behind `php -S` with
  `tests/Support/route_web.php` as router (visitor from the header `X-Probe-Who`, honoured only there). Modes:
  none (lists, view, attach, admin), `support`, `sync`, `integ`, `guard`, `intact`, `secure`, `tree`, `modes`,
  `seo`, `head`; children `view`, `comments`, `ext`, `syncext`, `integext`, `guardext`, `treeext`, `seoext` call
  classes directly on the same database; `serve [modes]` keeps a server up until a file
  `stop` appears in the scratch root.
- `install_probe.php <scratch> [keep | fail | update <dump> <config-dir-or-revision> <prefix> [<snapshot>]]`
  serves a copy of the tracked tree (without `docs`, `tests`, `tools`) with two `php -S` instances, so the
  directory check of type activation reaches a free server. `keep` stays up until `stop`; `fail` plants a user
  file in `uploads/jokes`; `update` loads a 6.2 dump and runs `update.php` over HTTP. The installation walks
  `setup.php` as a browser does: the token from the hidden field of the first page, every POST with `token`, `stop`
  and `go`, the parts with `go=part` until `more` is false.
  `SLAED_PROBE_DB=host|user|password` points it at another server.
- `update_probe.php <scratch> [points|ratings|fields|config|mails|setup]` lifts the functions of `update.php`, and
  for the installer preflight of `setup.php`, by name (both act on load) and runs one unit on a disposable schema and
  scratch site.
- `point_probe.php <scratch>`, `rating_probe.php <scratch>`: the classes on disposable schemas from the shipped
  DDL, concurrency through real processes. `config_probe.php <mode> <scratch>` takes the mode first.

### Disposable databases

A probe creates each database under a random name on the server of `config/db.php`, refuses
`$conf['db']['name']` as a target, and drops it in `finally` and a shutdown handler. DDL comes only from the
shipped SQL files. Seed data covers the probe types, open, closed, child and language categories, guests, members,
a group, a type moderator, the Node manager and the main administrator; boundary sets live only in their
scenario. N+1 is proven by one read with a single material and with a maximal page while `$db->qnum` stays within
budget.

### Requirements and running

- Probe-driven tests need the MariaDB server of `config/db.php`, an account allowed to `CREATE`/`DROP DATABASE`
  and, for `route_probe.php` and `install_probe.php`, free local ports. Only `install_probe.php` can be moved by
  `SLAED_PROBE_DB` (a clean install passes on MySQL 8.0). The snapshot-isolation test of `NodeServiceTest` is
  skipped without `innodb_snapshot_isolation`. A Node probe that cannot create its database or server fails.
- No test reaches the network: `Feed` gets a scripted transport (`new Feed($conf, $send)`), so a successful manual
  `op=sync` over HTTP is not tested, only its failure.
- Run `php vendor/bin/phpunit` (`composer test`), one file (`php vendor/bin/phpunit tests/Unit/NodeRouteTest.php`)
  or `--filter Node`; a probe alone (`php tests/Support/route_probe.php <scratch> integ`) prints its report. The
  pre-commit hook and `npm run ui:gates` run only theme and markup tests.
- A Node change is checked with `php -l`, `php vendor/bin/phpunit`, `php vendor/bin/phpstan`,
  `php vendor/bin/php-cs-fixer check`, `npm run ui:gates` when core, modules, templates, tests or UI tools change,
  and a real HTTP request per changing scenario with database and logs checked; route probes assert empty
  `error_php.log` and `error_sql.log`.

### Large profile

`php tools/node-profile.php [--rows=N] [--runs=N] [--out=<file>] [--keep]` runs by hand only. It creates its own
database on the server of `config/db.php` (prefix `prof`), executes `table.sql`, boots the core on a scratch copy of
the release configuration, imports and enables the ten shipped types through `NodeService::addNodeTypeImport()`
(its built-in server answers the directory check with `403`), and fills by batch SQL: `--rows` materials
(`1000..1000000`, default `100000`), 20 categories per type, two extra categories and two relations per material,
up to three resources where the type has roles. `--runs` (`5..500`, default `30`) repeats each warmed scenario;
`--keep` leaves database and scratch.

The report gives per scenario the Node statements against `PBUDGET` (the budget ceilings, with `view 5`,
`viewrel 6` plus tree batches, `status 6`; for `status` and `delete` only `_node*` statements count), p50, p95,
average statement time and `EXPLAIN` plans with rows read (`Handler_read*`). It exits `1` when a scenario exceeds its budget, a Node
table over 1000 rows is fully scanned, a list page reads more than `2 x page end x branches + 4 x page size +
2 x pinned rows + 3 x extra category links + 50` rows (a category count `3 x (materials + links) + 50`, the deadline
`3 x timed and pinned rows + 50`), a category page or count misses `_nodes.cat` or `_node_categories.cat`, or the
largest page (`media` at `limit = 100`) costs other statements than a page of one. Results with Xdebug loaded are
not acceptance results.

## The 6.3 update

`update.php` in the root brings a 6.2 site to 6.3 and then carries the content of the removed modules into Node
(see the next section). It is an internal tool of the maintainer: the release does not ship it, `setup.php` installs a
new site only, and the public `UPGRADING.md` says that a 6.2 site is not updated by the release. This section is the
maintainer view of its first stage and of `storage/update/sql/table_update6_3.sql`.

The first stage is the only entry of the data update. There is no CLI script, no conversion at runtime and no
detection of a 6.2 format by the running system: a subsystem whose mark is missing stays closed for writing and
says so, it never reads the old format. The conversion version string is `6.3.0`.

### Two stages in one file

A 6.2 site cannot boot the core: its `config/config_*.php` and `db.php` assign variables instead of returning
arrays, so `getConfig()` finds no database. `update.php` therefore decides before the core:

- **First stage**, while the marks `points`, `ratings` and `fields` of `config/update.php` are not all set, and for
  every request with `op=update`, which a repeat uses. It runs under `SETUP_FILE`, without a key and without a login,
  at the risk of the maintainer. `setUpdatePage()` shows what the run does on the login card of the admin theme
  (`pages/login.html` with the fragments `alert`, `table`, `table-row`, `table-cells`, `inline-badge`,
  `post-button`); a POST `op=update` runs `setUpdateRun()` and shows its report. The texts are English literals.
- **Second stage**, once the three marks stand: the core boots and `isAdmin(true)` guards the migration of the
  removed modules.

The connection comes from `config/db.php` in either shape (area `db` of 6.3, `$confdb` of 6.2), so the first stage
has no form. The panel file keeps the name of the site: the `afile` of `config/config_security.php` while a 6.2 site
still has it, otherwise the one of `security.php`; a name outside `[a-zA-Z0-9_-]` or one of `index`, `setup`,
`update` falls back to `admin`.

### Boundary of the first stage

The first stage does not include `core/system.php`. It loads only self-contained code: `core/admin.php` (the SQL
splitter `getSqlbatch()` and `getSqlinfo()`, which honour the `DELIMITER` directive), `core/classes/filemanager.php`,
`core/classes/logger.php`, `core/classes/pdo.php` (`Database`, which throws a `RuntimeException` on a refused
connection under `SETUP_FILE`), `core/classes/template.php`, `lang/en.php` and, inside the fields unit,
`core/classes/field.php`. A class the update needs must not depend on functions of `core/system.php`. The first stage
defines the path constants itself, `CONFIG_DIR`, `BACKUP_DIR`, `CACHE_DIR`, `LOGS_DIR` and `UPLOADS_DIR`, which the
attachment units of the forum and the private messages read. The update
runs on a closed site with one writer, so the runtime configuration protocol of the running system is not used.
No function of `update.php` carries a name the core, `core/admin.php` or a module declares, because the second stage
boots the core in the same file.

```php
function setUpdateFile(string $fp, array $arr, array $act = [], bool $raw = false): bool
```

- Writes `config/<fp>` under `FileManager::getPathLock(CONFIG_DIR)`, the same lock the runtime rebuilds
  `config/local.php` under, and deletes `config/local.php` on every call, so a rebuild from half-updated files
  never publishes `close = 0`. `CACHE_DIR` is defined by the first stage for that lock.
- Answers whether the whole file was written; it never touches a file or a `config/` that PHP cannot write, and it
  never changes file permissions. Every caller turns `false` into a failed report row.
- Scalars are stored as strings; `$raw = true` keeps native `bool`, `int` and `null`, which the Field definitions
  need. A file published with `$raw` equals byte for byte what the runtime `setConfigFile()` writes.
- `getUpdateSource(string $file): array` includes a source in an isolated scope without output and answers the
  array a 6.3 file returns or the first array a 6.2 file assigns. `getUpdateCred(): array` reads the gitignored
  `config/db.php` in both shapes.

A unit answers its report rows as data, `[['text' => …, 'done' => bool]]`, built by `getUpdateRow()`;
`checkUpdateFail()` finds a failed row, and every stop of the run is decided by it.

### Checks before the first write

In this order, each a failed row that changes nothing:

| Check | Row |
|---|---|
| `config/db.php`, `global.php`, `security.php` writable by PHP (for a missing one: `config/`) | `config/<name> is not writable by PHP` |
| `storage/backup/config/marker.json` is absent (no unfinished runtime configuration operation) | an unfinished configuration operation waits |
| `config/db.php` names a database and a prefix matching `[A-Za-z0-9_]{1,32}` | no database or no valid prefix |
| the connection | the reason of the server |
| `checkUpdateBase()` | see below |

### Preflight

```php
function checkUpdateBase(Database $db, string $prefix): string
```

Answers the refusal text or `''`, after connecting and before any file is written.

- Server version: MariaDB 10.5.2+ (`RENAME COLUMN`, `RENAME INDEX` of the schema file, enforced `CHECK`) or
  MySQL 8.0.16+.
- `<prefix>_users` and `<prefix>_admins` must exist; every existing table of the transaction list (`users`,
  `admins`, `comment`, `forum`, `favorites`, `user_oauth`, `points`, `rating_targets`, `rating_actors`,
  `rating_votes`, `categories`, `voting`, `newsletter`, `privat`) must be InnoDB, else the refusal lists one
  `ALTER TABLE … ENGINE=InnoDB;` per table. Nothing is converted automatically. A table that does not exist yet is
  skipped.

A table that takes part in a Point, Rating, Field, Node, private message or newsletter transaction belongs in that list.
The preflight of a new installation (a free prefix, the same server versions) is `getSetupProbe()` of `setup.php`.

### Run order

`setUpdateRun()` runs these steps. "Stop before the schema" means: every step up to the negative balances still
runs and reports, then neither the schema file nor any data unit runs.

1. The checks above; a refusal returns at once.
2. Close the site (`global.php`, `close = 1`). The shipped `global.php` carries `close = 0` and lands before a
   6.3 panel exists, so the run closes the site itself. It stays closed after the update until the maintainer
   opens it in the settings.
3. `setUpdateConfig()` carries the 6.2 settings (see "6.2 configuration" below).
4. The panel file: the rename source is the shipped `admin.php` when it exists, otherwise the panel file of the site
   (a repeat after the rename); the rename replaces a 6.2 loader under the name of the site, and a panel file of
   the site under another name is removed. Then `security.php` (`afile`) and `db.php` in the 6.3 format. The
   language and the address of 6.2 stay; nothing is taken from the request host.
5. `setUpdateModules($db, $prefix, $first)` reconciles `config/modules.php`.
6. `deleteUpdateTypes($keep)` removes every type of the shipped `config/node.php` that has no row in
   `<prefix>_node_types` (all of them on the first run, because the table does not exist yet).
7. `config/uploads.php` loses the rules `news`, `pages`, `faq`, `help`, `jokes`, `content`, `links`, `files`,
   `media` unless `config/node.php` has a type of that name.
8. `config/scheduler.php`: adds `maildrain` (priority `8`), `nodepublish` (`6`), `nodesync` (`7`) when missing;
   removes `commentsync`; on the first run switches `newsletter` on with `*/5 * * * *`; fills missing `dbbackup`
   settings; moves `maildrain` to the lowest free priority when another job holds its priority. Written only
   when something changed.
9. On the first run with no failed row so far: the mark `modules`.
10. `config/newsletter.php` gains missing keys (`abort`, `bouncemax`, `breakwin`, `canary`, `canarymin`), and
    `config/node.php` gains `limits.edit` (`600`) when its `limits` lack it.
11. `setUpdateMails(..., false)` snapshots the pending newsletter recipients while the column `mails` exists.
12. A negative balance in `_users.points` (`user_points` on 6.2) becomes 0, since the schema makes the column
    unsigned; the row counts the accounts.
13. Any failed row so far: stop before the schema.
14. `setUpdateSql($db, 'table_update6_3.sql', $prefix)`. An empty answer or a failed row stops here: no data unit
    runs, no data mark is written.
15. Data units, each independent of the result of the previous one: `setUpdatePoints()`,
    `setUpdateRatings()`, `setUpdateFields()`, then `setUpdateAttach()` over the forum posts and the private
    messages: a direct address of a file `uploads/forum/` or `uploads/account/` holds becomes an `[attach]` of the
    same name, because both folders are closed; an address the folder does not hold, and an image inside a link to
    another site, stay as written and answer 410.
16. `config/rss.php`: `temp` removed, missing `bytes` (`2097152`), `redirects` (`3`), `timeout` (`10`) added.
    Without these keys Feed refuses with code `config` before any request.
17. RSS blocks: `UPDATE _blocks SET content = '', time = '0' WHERE url != ''` drops the cached HTML; the next
    display fetches the feed and stores Markdown. A repeat empties the bodies again, which is harmless.
18. `_blocks` rows with `status = 1` and `bfile` `news`, `pages`, `faq`, `files`, `jokes`, `jokes_random`,
    `links`, `center`, `center_media`, `center_plus` (`.php`) get `status = 0`; the report names the files.
19. `setUpdateMails(..., true)` queues the kept recipients, each address once per campaign however often the run
    repeats.
20. Avatar check: a failed row while any `_users.avatar LIKE 'default/%'` remains (the schema file maps them).
21. `_nodes` counter: `AUTO_INCREMENT` is raised to the highest id of the nine old tables that still exist + 1,
    only when the current value is lower. The key column comes from `information_schema.COLUMNS` by
    `auto_increment` (6.2 uses `sid`, `pid`, `fid`, `lid`); the current value comes from `SHOW CREATE TABLE`,
    because MySQL 8 caches `information_schema.TABLES`. An old `name=<type>&op=view&id=N` answers 404 instead of a
    different material; there is no map of old ids.
22. Clean finish (no failed row in the whole report): every file under `storage/backup/update/*/` except
    `manifest.json` is deleted, including the moved configuration sources and the newsletter snapshot, because
    they hold guest addresses, balances and field values.

A rollback restores code, schema, configuration and data together; restoring only some files is none, and a
restore after the site was reopened loses what was written in between.

### Marks in `config/update.php`

The file holds the area `update`; it is gitignored and travels with the site. Marks are read without SQL.

| Key | Value | Written by | Read by |
|---|---|---|---|
| `points` | `6.3.0` | `setUpdatePoints()`; `setup.php` | `core/system.php` builds `Point` with the rules only when set; `admin/modules/groups.php` warns `_POINTS_NOMARK` |
| `ratings` | `6.3.0` | `setUpdateRatings()`; `setup.php` | `Rating` construction in `core/system.php`, `getRatingAsync()` in `core/helpers.php`, `admin/modules/ratings.php` (`_RATINGS_NOMARK`, no form) |
| `fields` | `6.3.0` | `setUpdateFields()`; `setup.php` | `getFieldRules()` (empty set without it), `getFieldsPost()` (keeps the stored text, logs), `admin/modules/fields.php` (`_FIELDS_NOMARK`) |
| `modules` | `6.3.0` | step 9 of the run | the run only: set means "not the first run" |
| `node` | `new` | `setup.php` only | `addNodeProfiles()` in `core/admin.php`: imports the shipped profiles for the first administrator, then removes the key |

`setup.php` writes `points`, `ratings`, `fields` and `node` together, after the last statement of `insert.sql`, so a
failed schema statement leaves no mark. Each data unit merges its key into the marks it reads at its start, so no
earlier mark is lost. The update never writes `node`, so `addNodeProfiles()` creates no type on an updated site.

### Units, manifests and snapshots

Every resumable unit keeps `storage/backup/update/<unit>/manifest.json` and its snapshots beside it, written by
`setUpdateBackup()` through a temporary file and `rename()`. Directories get mode `0750`; `storage/backup/` lies
outside the document root.

| Unit | Snapshots | Manifest beyond `version`, `state`, `source`, `target` |
|---|---|---|
| `points` | `balances.json` (uid -> `_users.points`) | `cursor` 0, `count` |
| `ratings` | `targets.json`, `terms.json`, `rules.json` | `cursor` per `targets`/`terms`, `moment` (server time of the preflight), `count` with `targets`, `terms`, `voting`, `foreign`, `orphan`, `dropped` |
| `fields` | `definitions.json`, `account.json`, `forum.json` | `cursor` and `count` per area |
| `newsletter` | `recipients.json` | `count` (`campaigns`, `recipients`), `sent`; states `prepared` -> `verified` |
| `config` | the moved `config_<name>.php` sources | none |

`source` holds the SHA-256 of every snapshot, `target` the SHA-256 of what the unit published (configuration files
and, for ratings and fields, the stored rows read back).

### Resume and repeat

- No manifest: the unit prepares, but refuses when its mark is set or target data exists (`_points` rows; rows in
  any rating table). The current state is never taken for the source state, so a repeat never snapshots a
  balance twice.
- `prepared` or `applying`: every snapshot is checked against its `source` hash, the state becomes `applying`,
  and application continues from the cursor. The cursor is saved after the `COMMIT` of each batch of 500, so a
  break leaves it at most one batch behind, and a row is compared by value: equal to its target is skipped,
  equal to its source is written, anything else stops the unit.
- `verified`: the unit only (re)writes its mark. It does not look at snapshots, which is why the snapshots can be
  deleted at the clean finish.
- DDL is not transactional: a rerun meets whatever stage the schema reached, so the schema file is re-runnable.

A repeat after a clean finish skips every unit by mark and manifest, reconciles the registry only with the tree,
re-runs the schema file idempotently and never lowers the `_nodes` counter.

### Points unit

`setUpdatePoints(Database $db, string $prefix): array` snapshots `uid -> points` of `_users` inside a
transaction, moves `users.point` of the site configuration into `points.active` of `config/points.php` and drops
`point` and `points` from `config/users.php`. It requires the points scope to carry 15 actions and an `active` of
`'0'` or `'1'`. `_users` is not modified: `_users.points` is the starting balance, rating rewards of 6.2 included, no
journal row is invented and the positional `users.points` string of 6.2 is not carried; the reward rules start from
the release.

### Ratings unit

`setUpdateRatings(Database $db, string $prefix): array` handles the remaining targets `account` (`_users`
`votes`/`tvotes`) and `forum` (`_forum` `ratings`/`score`, topics `pid = 0`).

- Preflight, nothing written on failure (first 50 entries and the total reported): an aggregate outside
  `votes <= total <= 5 × votes`; a kept `_rating` row without an actor or with a time that is not a positive
  integer up to the server time; an invalid rule; a missing rule for `account` or `forum`.
- Rules: `period|active|detail` of 6.2 becomes the four keys with `guests = '1'`; `period` must be a multiple of
  86400, `0` stays "no wait". Only `account`, `forum` and `node.<name>` are kept.
- Aggregates go to `rating_targets` (`base` = total, `created` = `moment`); the latest `_rating.time` per actor
  (`u:<uid>` or `g:<normalized address>`) and target goes to `rating_actors.last`. No vote row is created.
- The unit never modifies `_rating`; poll rows, other events and rows of missing targets are counted and left.
  The schema file before it has already removed the later rows of one address, target and module (see below).
- `config/ratings.php` is published only after targets, terms, owner aggregates, an empty `rating_votes` and the
  poll row count match the manifest. The rating model is described in `docs/RATINGS.md`.

### Fields unit

`setUpdateFields(Database $db, string $prefix): array` converts `account` (`_users.field`) and `forum`
(`_forum.field`).

```php
function getUpdateRules(string $area, mixed $text, Field $fld, array &$bad): array
function getUpdateValue(array $rules, array $slots, int $size, string $text, Field $fld, string $plan = ''): array
```

- `getUpdateRules()` splits the definitions by `||`, each position by `|` into `caption|content|type|duty`, and
  trims every slot and option caption. An empty position is skipped; type `1`-`5` maps to `text`, `textarea`,
  `select`, `datetime`, `date`; another slot count or type of an active position is refused. Keys are `fieldN` by
  original position with gaps kept, options `optionN` in original order, both with `sort = N × 10`; a repeated
  option caption is refused. Content `0` means no default; `req` only for exactly `1`. The set passes
  `Field::filterFieldList()`. A position with caption `0` and a known type stays in the slot map as switched off,
  with the inactive definition it becomes (`title` = its key, no default, not required) once a row needs it.
- `getUpdateValue()` splits a value row by `|` and tries two layouts: full (value i -> position i) and short
  (values -> active positions only). A layout that fits the definitions as they are wins; only when none does,
  a layout that fits once they grow: a select caption no option carries, or data at a switched off position.
  It answers `['json' => …, 'plan' => layout, 'grow' => field => [captions]]` when exactly one distinct result
  passes `Field::checkFieldValues()`, otherwise `['why' => …]` without stored data in the text. Empty is absence;
  `0` is a value of `text`/`textarea` and the placeholder of an empty choice in `select` (without such an option),
  `date`, `datetime` and in a switched off position. Data beyond every position is refused. Values are not decoded:
  6.2 stored `htmlspecialchars` text, which is carried byte for byte.
- A row the unit cannot map without guessing stops it before any write, named by table, row id and reason, for
  example `sport_users 261 (value 10 holds data and has no definition)`; the row is corrected in the database and
  the run repeated, finished units skipped and the fields unit started over.
- The unit grants the needs of every row in id order: a caption becomes one disabled option (`active = false`,
  next `optionN`) of its field, a switched off position its inactive field; then each growing row runs its layout
  (`$plan`) again against the grown set. The form offers no disabled option and hides an inactive field, the page
  shows a stored disabled option, and a stored value of an inactive field survives the form (`getFieldsPost()`).
  The manifest keeps the counts in `grown`, and the report names them, at most 50 rows plus the total.
- Definitions that are already named (arrays) with empty value columns are a valid no-op: the unit seals an
  empty manifest and sets the mark. Named definitions while positional rows exist and no manifest does stop the
  unit with the instruction to put the 6.2 `config/fields.php` back.
- The preflight read transaction is committed before the checks: an open one holds a metadata lock.
- Batches of 500 rows under `SELECT … FOR UPDATE`. After all batches every non-empty value of the column is
  compared with the snapshot; only then is `config/fields.php` published with `$raw = true`.

The columns `_users.field` and `_forum.field` are `MEDIUMTEXT NOT NULL` for the 1 MiB JSON limit of
Field, declared so in `table.sql` and in the final alignment of the schema file.

### Module registry

```php
function setUpdateModules(Database $db, string $prefix, bool $first = true): array
function deleteUpdateTypes(array $keep): array|false
```

- `setUpdateModules()` builds the default records of the modules screen (`admin/modules`: `active 1`, `type 0`;
  `modules`: `active 0`, `type 1`; `icon` `puzzle`) and the clean-installation record of `node`. Existing records
  of `config/modules.php` win; records of modules missing from the tree are dropped and named.
- First run only: when the 6.2 table `<prefix>_modules` exists, its `active`, `view`, `inmenu`,
  `mod_group`, `blocks`, `blocks_c` override the records, and the numeric module ids in `_admins.modules` are
  rewritten as module names. A failed read of `information_schema`, `_modules`, `_admins` or a failed
  `UPDATE _admins` is a failed row and `config/modules.php` is not written.
- Repeat (mark `modules` set): the table is not read, the switches the owner set in between stay, the report says
  so.
- `deleteUpdateTypes()` removes each type of `config/node.php` not in `$keep` from `node.types`, `fields.node`,
  `uploads` and `ratings` `node.<name>`, as the panel does when it deletes a type, and publishes the four files
  with `node.php` last, so a repeat after a failure still finds the types. It answers the removed names or
  `false`. The run passes the names of `<prefix>_node_types`; `deleteSetupTypes()` of `setup.php` does the same for
  a new installation and takes every type.

### 6.2 configuration

`setUpdateConfig(): array` reads every `config/config_<name>.php` and lays the site values over the shipped
`config/<name>.php` (`stat` -> `statistic`, `seo` -> over `global`; `fields` is taken as the site wrote it).

- Unshipped keys stay for the data units (`users.point`, `users.points`, positional `fields` and `ratings`); the
  settings form drops the rest on its next save.
- `global`: `close = '1'`; language names become codes (also `lang.lang`); `module` keeps only modules of the
  tree and `amod` is dropped; `version`, `css_f`, `script_f` come from the release; a missing theme or logo falls back.
  A key that names a configuration area (`forum`, `newsletter` and `search` of 6.2) is dropped: `getConfig()` merges
  the files in name order, so `global.php` would replace the whole `$conf['forum']` of `forum.php` with its string.
- `uploads`: a 12-field rule loses field 8 (`adminlist`) and gains `guestfiles` = `userfiles` as field 12.
- `security.blocker_ip`: `ip|octets|hash|time|reason` becomes `CIDR|hash|time|reason`; other entries are dropped.
- Not carried: the nine removed modules, `templ`, `header`, `chmod`, `core`, `rewrite`, `rules`, `db`, a file
  without an array or without a shipped counterpart.
- Each target is read back before its sources move to `storage/backup/update/config/`. The move is required:
  `getConfig()` includes every `config/*.php`, and `config_global.php` would overwrite `$conf`, `config_header.php`
  print HTML. A moved source is not applied twice; on a failed write or move the sources stay and the row fails.

### Newsletter

`setUpdateMails(Database $db, string $prefix, string $from, bool $move): array`: before the schema, while
`<prefix>_newsletter.mails` exists and no manifest does, the valid unique addresses per campaign go to
`recipients.json`. After it, each address is queued in `_mail` (`kind = 'newsletter'`, `ref` = campaign) unless
that `ref` and `email` pair exists, and the campaign gets `status = 5`, `audit = 'list'`, `expect` = `total` =
valid addresses, one transaction per campaign. Without emulated prepares a named placeholder may appear only once
per statement, and `SHOW … LIKE ?` fails, hence `information_schema`.

### The schema file

`storage/update/sql/table_update6_3.sql` runs through `setUpdateSql()`, which splits it with `getSqlbatch()`,
substitutes `{prefix}`, `{engine}`, `{charset}`, `{collate}` and answers a row per table statement and per failed
one; a `DELETE` names its removed rows. It runs outside any data transaction; a failed statement leaves the earlier
ones applied, so every statement must be safe to run again on a 6.2 schema, on a partly migrated one and on a
finished one. The file neither creates nor alters the tables of the nine removed modules nor those of `shop`,
`clients`, `order`, `money`, `auto_links` and `whois`; on an updated site they stay in 6.2 format. It drops
`_referer.lid`, which tied a referer to a link of `auto_links`.

It runs without `NO_ZERO_DATE` and `NO_ZERO_IN_DATE` for its session, which MySQL 8 sets by default and which refuse
every `ALTER` of a 6.2 table whose datetime defaults to a zero date, and restores the mode of the session at its end.
Before a statement makes a column `NOT NULL` or narrower, a NULL takes the default of its column and a longer value is
cut where the row is transient (`_session.modul`). The final alignment covers the two 6.2 schemas the tests carry:
`tests/Fixtures/update62` and the earlier `update62early` (signed integers, addresses of 15 characters, other defaults).
It stops with a message while `_privat.time` or `_comment.time` holds `NULL` or `_comment.reqkey` is still hex text.

Its one `DELETE` removes, before the unique key `mid_modul_ip`, every `_rating` row that has an earlier row of the
same `mid`, `modul` and `ip`: 6.2 kept one row per account, so two accounts of one address could both have voted.
`setUpdateSql()` names the removed rows of a `DELETE` in its report row.

Helper procedures are dropped and created at the top inside `DELIMITER $$` and dropped again in the cleanup
section; `movenet` is created and dropped around the OAuth block at the end.

| Procedure | Effect |
|---|---|
| `rencol(tab, old, new)` | `RENAME COLUMN` when `old` exists and `new` does not |
| `addcol(tab, col, def)` | `ADD COLUMN` when missing |
| `poscol(tab, col, def, after)` | `MODIFY … AFTER` when an existing column is not right after `after` |
| `modcol(tab, col, def)` | `MODIFY` only when the stored definition differs from `def` (integer display widths and implicit `NULL` normalized) |
| `setcoll(tab, col, def, coll)` | `MODIFY` when the collation differs |
| `delcol(tab, col)` | `DROP COLUMN` when present |
| `runifcol(tab, col, sql)` | runs `sql` only while `col` exists (backfills that must read a column before a rename consumes it) |
| `renidx`, `delidx`, `addidx(tab, idx, exp, unique)` | index rename, drop, add when absent |
| `stopcol(tab, col, type, msg)`, `stopnull(tab, col, msg)` | `SIGNAL SQLSTATE '45000'` with `msg` when the column has that type or holds `NULL` rows |
| `finalize_user_names(tab)` | renames duplicate user names to `<name>_<id>` (cut to 25), signals on a conflict |
| `mkuseruniq(tab)`, `mksessuniq(tab)` | turn `name` into a `UNIQUE KEY` when no duplicates remain; dedupe `_session` and make `uname` unique |
| `fixgrppk(tab)` | restores the primary key of `_groups` |
| `movenet(users, links)` | archives `_users.network` as provider `ulogin` rows of `_user_oauth` and drops the column |

Rules, enforced by `tests/SchemaUpdateValidationTest.php` for the file:

- Every `{prefix}_<table>` it names must be a table of `storage/update/sql/table.sql` (named `CONSTRAINT`s aside; the
  6.2 table `modules` is the only exception). A table it drops with `DROP TABLE IF EXISTS` must not be in `table.sql`.
- A column declaration is a `MODIFY` line of an `ALTER TABLE`, or the definition passed to `addcol()` or
  `modcol()`. Every declaration must equal the definition `table.sql` gives the column (whitespace, case and
  `BOOLEAN` = `TINYINT(1)` normalized).
- A column is not declared twice with different definitions: the last one would silently win. A type change is
  therefore one declaration of the final type. Data that only the old type accepts is fixed before the
  `MODIFY`, data that only the final type accepts is written after it. Example: `_admins.editor` of 6.2 is a
  `TINYINT`; `NULL -> 0` runs before the widening, `'plain'` for `''`, `0`, `1` after it.
- Most type alignment of existing columns lives in the section "Final type alignment to
  storage/update/sql/table.sql"; a rename a `MODIFY` depends on comes before that `MODIFY`.
- A statement that would fail on a repeat is not an unconditional `ALTER` (the `_users` alignment leaves out
  `network`, which the OAuth block drops). A plain `MODIFY` to the final definition re-runs to the same result.
- Tables 6.2 lacks are `CREATE TABLE IF NOT EXISTS` with the definitions of `table.sql` verbatim, ordered by their
  foreign keys, so the file runs with foreign key checks on.

The header names MariaDB 10.5.2+ and MySQL 8.0.16+, as do `table.sql` and `insert.sql`. `rank` is a reserved
word of MySQL 8.0.2+: unqualified `_users.rank` and `_groups.rank` are written in backticks in every query.

### Tests

| Test | Driver | Covers |
|---|---|---|
| `tests/SchemaUpdateValidationTest.php` | static | tables and column declarations of the schema file against `table.sql` |
| `tests/Unit/UpdateSiteTest.php` | `tests/Support/install_probe.php` mode `update` over HTTP | the whole first stage on a real 6.2 schema |
| `UpdateSetupTest` | `tests/Support/update_probe.php … setup` | the update preflight, registry, `deleteUpdateTypes()`; the installer preflight `getSetupProbe()` and the order of `checkSetupRun()` in `setup.php` |
| `UpdatePointsTest`, `UpdateRatingsTest`, `UpdateFieldsTest`, `UpdateConfigTest`, `UpdateMailsTest` | `update_probe.php` with unit `points` (default), `ratings`, `fields`, `config`, `mails` | one unit each: resume from cursors, stops, marks |

`update_probe.php` lifts the functions out of `update.php` and `setup.php` by name (both act on load), with a
scratch site and a disposable schema. A failing probe is a test failure, not a skip.

`tests/Fixtures/update62` is the 6.2 site of `UpdateSiteTest`:

- `site.sql`: the `CREATE TABLE` statements of all 33 tables of a real 6.2 dump (prefix `old`, counters dropped)
  and a small invented seed of what the update has to meet: boolean and `NULL` `editor` with numeric rights,
  `_modules`, balances, old sections up to id 300, a `news` category, blocks of removed modules, a cached RSS
  block, two poll votes of one address, newsletter campaigns, a legacy `_whois` row.
- `config/`: six 6.2 sources in variable form without secrets (`global` with language `russian`, `security`
  with `afile`, `ratings`, `users`, `voting`, `news`).

`tests/Fixtures/update62early/site.sql` is an earlier 6.2 schema of the same 33 tables (signed integers, addresses
of 15 characters, nullable columns, zero dates as defaults), the same seed loaded in the relaxed mode of 6.2, and a
negative balance, an account without an address, a comment without an author and an online row with a long module
name; it uses the configuration of `update62`.

`UpdateSiteTest` loads each into a disposable MariaDB database, serves a copy of the release and drives
`update.php` over HTTP: the page of the first stage offers the run without a login and writes nothing, the
preflight refuses a MyISAM table without touching a file, an unreadable `_modules` gives a failed row before the
mark (`config/modules.php` untouched), a broken schema file may fail on that statement only (the mark `modules`, no
manifest, the later poll vote of one address removed), then three runs, each without a failed statement, with all
four marks, all manifests `verified` and `_nodes` continuing at 301. After them the schema equals a clean
installation in `information_schema` (columns, indexes, foreign keys, checks, engine, collation). It also checks two
guest votes in one poll, the kept panel name `myadm` now holding the shipped loader, language and address of 6.2,
the switched-off blocks, type creation in the panel, the `_nodes` counter after a deleted material, and empty PHP and
SQL logs. A change to the first stage or the schema file is done only when this test passes.

## Migration of the removed modules

`update.php` in the root carries the content of `news`, `pages`, `faq`, `help`, `links`, `files` and `content` into
Node on a site that ran the 6.3 update. Only the main administrator opens it; the page shows the plan per module and
a POST with the token scope `update` runs every step that is not done yet. The old tables stay as they are. It reads
the module tables in the 6.3 shape (`id`, `cid`, `intro`, `body`, `ip`), which the production tables of `slaed.net`
carry; `table_update6_3.sql` no longer renames the tables of the removed modules, so a clean 6.2 site with `sid`,
`hometext` and `bodytext` stops at the counter step.

| Module | Type | Notes |
|---|---|---|
| `news`, `faq`, `links`, `files` | same name, from its profile | `fix` → `pinned`, `vote` → `poll`, `assoc` → extra categories; the file or address of `files` and `links` becomes the resource of the `download` or `link` role |
| `pages` | `docs` | the type gains the features its data needs, such as comments and rating |
| `help` | `help` (`support`) | every request becomes a published material, each reply a comment of its author; open or closed goes to the queue row, which follows the last reply |
| `content` | `content` without extension | static pages; a feed address is noted, not carried |

The run changes the database and nothing else: it moves, copies and renames no file. The operator runs it with the
upload folders of the modules where 6.2 left them, and after it, before the site opens, moves each with its contents
into the empty folder of its type:

| From | To |
| --- | --- |
| `uploads/<module>/` of `news`, `faq`, `help`, `links`, `files`, `content` | `uploads/node/<module>/` |
| `uploads/pages/` | `uploads/node/docs/` |

A new type refuses a folder that already holds a file, so a folder moved before the run stops it at the types step;
the run finds a file in `uploads/<module>/` or, moved already for a type that exists, in the folder of the type.
`uploads/account/` keeps the files of the private messages alone: a signature, an own block or a comment on a profile
of a 6.2 site that carries an `[attach]` of a file there needs it copied into `uploads/profile/`, and a mail text of
the panel into `uploads/all/`; those of `slaed.net` carry none.

Steps, in this order; the manifest `storage/backup/update/node/manifest.json` records each, so a stopped run
continues where it stopped:

1. Stash: categories, comments and favorites of a module move to the key `~<module>`, so the name check of a new
   type passes.
2. Types: a missing type is created from its shipped profile and takes over the upload rule the module left
   (see [Identity rules](#identity-rules)); an existing one only gains the features the data needs.
3. Counter: `_nodes` continues above the highest old id of every module, so no old address names a new material.
4. Data, one transaction per module: materials with dates, views, states, rating balances, resources and the
   `_node_legacy` row, then comments and favorites rebound to the new ids (rows of a gone material take the key
   `old<module>`, which `op=remains` cleans up), the terms of `_rating`, the comment counters and `node-<type>`
   rights instead of the module name. Texts are converted from the trusted HTML the old modules rendered into BB
   and Markdown; blocks the conversion does not know become `[usehtml]` blocks, and a bare relative address of
   `[img]` or `[url]` gains `./`, the local form the safe parser accepts. A local resource path Node refuses gets a
   safe spelling, and a note names the file the operator renames to it.
5. Foreign addresses, on every run (`setMigrateForeign()`): a direct address any text keeps into the 6.2 folder of
   a type it does not belong to becomes the `go=file&own=node` address of the first published material of that type
   whose text or published comment carries the name, and an address of `uploads/forum/` in a text that is no forum
   post the `go=file&own=forum` address of the first published post that carries it. A name nothing carries keeps
   its address and answers 410; `[code]` and `[php]` keep their examples.
6. Activation of every type the run created or switched off.

The data step turns a direct address of a file of `uploads/<module>/` in a material, a comment or a help reply into
an `[attach]` of the type with the name unchanged (`getMigrateAttach()`), whatever the form of the name, when the file
exists and its extension is one the type allows; the attach route grants it because the text carries it. An `[img]`
becomes the full-size form with its alignment (`none` without one) and its alternative text as the title, a link
around the thumb of a file the thumbnail form of that file, a link around another image the full-size form of the
image alone, and `[url]` an attachment titled by its label. Inside a `[usehtml]` block of a material the raw HTML
stays and a `src` or `href` becomes the file address of that material
(`index.php?go=file&own=node&id=<id>&key=<name>`, with `&thumb=1` for a thumb), which
`NodeService::getNodeFile()` grants as well. An image inside a link to anything else stays, because an attachment is
a link of its own, and so does every address inside `[code]`, `[php]` and `[usephp]`. Every other address stays as
written.
