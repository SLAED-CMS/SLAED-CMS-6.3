# Files 2026

Work plan for one way of delivering uploaded files across the site. Today Node closes its upload folders and
serves files through a route that checks the rights of the material; every other module links its files directly
in open folders. The plan gives the forum, private messages and comments the same controlled delivery, brings the
migrated Node texts onto that route, and retires `uploads/archive/`.

Status: planned, nothing implemented. Batches run in order; update this line as they land. The last batch moves
what lasts into the permanent reference and deletes this file.

No line numbers for this tree anywhere in this document on purpose: every reference names the function, the file or
the constant it points at. The counts come from the production database migrated on 2026-10-01.

## What exists today

| Area | Folder | Delivery | Who may read |
| --- | --- | --- | --- |
| Node materials | `uploads/<type>/`, closed | `op=attach` for `[attach]`, `op=asset` for resources | the reader of the material |
| Comments of Node | the folder of the type | `op=attach` with the id of the material | the reader of the material: published comments, all for a moderator |
| Forum posts and signatures | `uploads/forum/`, open | direct link | anyone with the address |
| Private messages, profile comments, own block | `uploads/account/`, open | direct link | anyone with the address |
| Site messages and newsletters | `uploads/all/`, open | direct link | anyone with the address |
| Polls and their comments | `uploads/voting/`, open | direct link | anyone with the address |
| Avatars, presentation | `uploads/avatars/`, `uploads/presentation/`, open | direct link | public by nature |
| Migrated direct links | `uploads/archive/<module>/`, open | direct link | anyone with the address |

How it works for Node:

- `Parser::filterAttach()` turns `[attach=name …]` into `index.php?name=<type>&op=attach&id=<nid>&key=<name>` when
  it is given a material id (`int $nid` of `Parser::filterContent()`); without one it writes `uploads/<mod>/<name>`.
- `NodeService::getNodeFile()` grants a name only when it is a managed name (`FileManager::checkFileName()`) with an
  allowed extension, and only when the text of the readable material or one of its published comments carries it.
- A closed folder holds the guard files of `FileManager::getGuardFiles()`. Apache and LiteSpeed follow `.htaccess`;
  nginx needs the shared rule in UPGRADING.md, "Web Server Rule for Node Upload Directories".

### Numbers

- `uploads/archive/`: 279 files, 34 MB. Addresses into it:
  - 521 in 122 Node materials: 264 `[img]`, 69 `[url]`, 184 inside `[usehtml]` (178 of them in news), 4 in
    other forms;
  - 4 in comments of Node materials;
  - 2 in forum posts, 1 in a private message, 2 in messages, 1 in a newsletter.
- `uploads/forum/`: 1 025 files, 65 MB; posts carry 362 `[attach]` and 150 direct addresses into the folder.
- `uploads/account/`: 13 files; private messages carry 16 `[attach]`.
- `uploads/all/`: 622 files. `uploads/avatars/`: 2 457 files.
- Managed names carry a random part of `FileManager::SALTLEN` (10) characters. Some files do not: 5 of 1 025 in
  `forum`, 1 of 13 in `account`, 36 of 622 in `all`. Their addresses can be guessed.

### Defects the plan closes

1. **Private files are public.** An attachment of a private message, or of a post in a forum category closed to
   the reader, is served to anyone who has its address. Only the random part of the name protects it, and some
   names have none.
2. **Two concepts for one job.** `uploads/archive/` exists only because migrated texts addressed files directly.
   It is a second, open place beside the closed type folder.
3. **Raw HTML cannot use the route.** The route grants only names that `Parser::getAttachList()` finds in
   `[attach]` tags, so 184 addresses inside `[usehtml]` cannot leave the archive as they are.
4. **An attachment is not an inline image.** The image templates of `config/filetype.php` render `[attach]` as a
   thumbnail (`[tsrc]`, `max-width:[twidth]px`) that links to the full image. A screenshot a text showed at full
   width would become a thumbnail.
5. **Comments of other targets link directly.** A comment of a poll or a profile renders `[attach]` as a direct
   link into the folder of that module.
6. **Nothing reports unused files.** The migration left 414 files nothing references. No screen tells an
   administrator which files of a folder are no longer used.
7. **A signature resolves against two folders.** The forum renders a signature with the module `forum`, a
   private message with the module of its page, `account`; an `[attach]` of one signature exists in only one of the
   two folders and is broken on the other page.
8. **The shipped tree carries old folders.** `uploads/jokes/`, `uploads/media/` and `uploads/pages/` are tracked,
   though no shipped type or module uses them.

## Design

### One route, one owner per file

A new class `FileAccess` (`core/classes/file_access.php`) owns the delivery decision and nothing else. It receives
one adapter per owner as closures, the way `getRatingService()` builds `Rating`. Its factory is `getFileService()` in
`core/system.php`. An owner is named by a closed value:

- `node` — the existing check, `NodeService::getNodeFile()`, unchanged;
- `forum` — a post: the reader of its category and topic, from the stored row;
- `privat` — a private message: its sender or its recipient;
- `comment` — a comment of a target that is not a Node type: the reader of that target, published comments only;
- `public` — avatars, presentation and the other folders that stay open; no route, a direct link as today.

Each adapter answers two questions from stored rows only, never from the request:

- **grant**: may the reader receive this name of this target — the name must be a managed name the text of the
  target carries;
- **folder**: which folder holds the files of this owner.

The route is one endpoint for every non-Node owner, `index.php?go=file&own=<owner>&id=<target>&key=<name>` with an
optional `thumb=1`. It answers through `getFileStream()` like `setNodeAttach()`, and every refusal is the same 404.
Node keeps `op=attach`: every Node text already renders it, and nothing is gained by moving it.

### The parser asks, it does not build

`Parser::filterContent()` takes a file context instead of `int $nid`: the owner and the target id. `filterAttach()`
asks `FileAccess` for the address of a name. A Node context answers `op=attach`, a closed owner answers `go=file`, a
public owner answers the direct link. The cache key of the parser carries the context as it carries `nid` today.

### Closing a folder

A folder closes the way a Node type folder does: the guard files of `FileManager::getGuardFiles()`, then a request
for its `index.html` that must answer 403 or 404 before the owner is switched to the route. On nginx this needs the
shared rule; the plan adds the forum and account folders to it rather than a second rule.

### Existing texts

A text that addresses a file of a closed folder directly is rewritten to `[attach]` when the file is a managed name
in that folder. A name without a random part is renamed first, as `getMigrateNames()` does for the migration. A
direct address that names a file which does not exist stays as it is.

### Inline images

`[attach]` gains a form that renders the full image inline instead of a thumbnail. The grammar
(`Parser::ATTACH`, `ATTSIZE`, `ATTREL`) requires `align` and `title` and allows `width`, `height` and `rel`, which the
image templates of `config/filetype.php` do not use. Either an explicit width makes the image template render the full
image, or the grammar gains one optional segment such as `size=full`. The converted `[img]` addresses use the
chosen form, so the migrated texts look as they did. The form, and whether the editor offers it, is decided in
batch 1.

### Unused files

An administration report lists, per folder, the files that no text, comment, resource or avatar references. It
deletes nothing on its own: the administrator removes the files the report shows. The scan uses the same reference
readers the route uses, so the report and the route never disagree about what is used.

## Batches

Every batch that touches markup or theme CSS takes its own `npm run ui:before` / `ui:after` pair. Every batch that
changes a closed folder is checked on Apache and against the nginx rule emulated by `tests/Support/route_web.php`.

0. **Decisions and inventory.** Settle the open decisions below. Count, on the current production dump, the
   direct addresses per folder and owner, the names without a random part, and the names that differ only by case.
1. **Inline attachment.** Add the full-size form of `[attach]` to the parser and to the image templates of both
   themes, with parser fixtures. Nothing else changes yet.
2. **Node texts off the archive.** In `update.php`, convert the `[img]` and `[url]` addresses of the archive into
   `[attach]` under managed names in the type folder, using the full-size form for images. Handle `[usehtml]` as
   decided in batch 0. Re-run the migration rehearsal on the production dump, with the crawl and the comparison of
   `src` values before and after.
3. **Protocol.** `FileAccess`, `getFileService()`, the `go=file` route, the file context of the parser and the
   `public` owner. `FileAccessTest` covers closed forms, 404 for every refusal, managed names only, and no access
   by a name of another target.
4. **Forum.** The `forum` adapter (category read right, topic state, the post row), the closed `uploads/forum/`, the
   rewrite of the 150 direct addresses of posts, the signatures of the forum, and the nginx rule. Route probes:
   a closed category, a hidden topic, a guest, a moderator.
5. **Private messages and signatures.** A signature gets one owner and one folder on every page. The `privat` adapter (sender or recipient; a side that has deleted its copy reads nothing,
   as `Privat` already answers for the message itself), the closed
   `uploads/account/` if nothing public lives there after batch 0, and the 16 attachments. Probes for both sides and
   a third account.
6. **Comments of other targets.** The `comment` adapter for polls and profiles; a comment of a Node type keeps its
   route through the material.
7. **Retire the archive.** Move the files that only the forum, private messages or newsletters still address
   directly into the folders of their owners and rewrite those addresses, then remove `uploads/archive/` from the
   migration and the tree.
8. **Unused files and the tree.** The administration report of unused files. Remove `uploads/jokes/`,
   `uploads/media/` and `uploads/pages/` from the shipped tree.
9. **Reference.** Write the lasting part — owners, the route, closing a folder, the nginx rule, the unused files
   report — into `docs/ARCHITECTURE.md` and `docs/NODE.md`. Add an entry to `docs/VERSIONS.md`. Delete this file.

## Decisions before work starts

- **`[usehtml]` in migrated texts.** 184 addresses live in raw HTML blocks. The options are: rewrite those blocks
  into BB and Markdown, so their images become `[attach]`; let the route grant a name that a trusted `[usehtml]`
  block of the same material addresses; or keep these files in an open folder of the type for raw HTML only.
- **The account folder.** `uploads/account/` holds private messages, comments on profiles and the own block of
  an account (`getUserBlock()`). Private messages need a closed owner, profile comments are public; whether the
  folder closes or the private messages move to a folder of their own is decided here.
- **What stays public.** Avatars and presentation are public by nature. `uploads/all/` serves the site messages
  and newsletters written in the panel (`admin/modules/messages.php`, `admin/modules/newsletter.php`), which reach
  every visitor or every subscriber anyway; it most likely stays open, which batch 0 confirms.
- **The update of a 6.2 site.** `update.php` is to become the full update from 6.2. The conversions of batches 2,
  4, 5 and 7 belong in it, so a 6.2 site lands on the closed folders in one run.
- **Thumbnail or full size.** Whether the editor offers the full-size form of batch 1, or whether it is only for
  converted texts.

## Outside this plan

- The case-only name pairs of a Windows copy of a Linux site: the migration keeps the addresses as they are, and
  the production server serves both files. A copy for a rehearsal on Windows loses one of each pair; that is a
  property of the copy, not of the site.
- Delivery speed and caching of the route: `getFileStream()` already answers 304 and ranges. A CDN or a static
  offload is a separate decision.
