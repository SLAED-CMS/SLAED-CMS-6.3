# Versions

## 2026-10-31

### Small repairs found by the audit of the panel sections

- **Site message.** The edit link of a message on the home page opens `name=messages&op=add`, where it led nowhere before,
  and an expired message is switched off without being shown once more.
- **"Редактор".** `save()` takes the path of the file from its tab; a path sent in the request is ignored.
- **"Блоки".** A bad file name in `filecode()` returns to `op=fileedit` instead of an operation that does not exist.
- **"Конструктор сайта".** A new block is created with `refresh` 3600, as the form of "Блоки" offers it: at 0 an RSS block
  fetched its feed on every view.
- **Help.** "Рейтинги" is rewritten by the code; "Категории", "Конфигурации", "Опросы", "Рассылка", "Группы", "Загрузки" and
  "Языки" no longer name modules that are gone, and "Сообщения" says where a message shows.

### The panel takes the parts of its series: "Статистика" and "Мониторинг" are cockpits, the other screens take the window

The engine of the series lives in `admin-ui.js`, and the two screens are built on it by their data. The help of each
screen is in `admin/info/` and `modules/*/admin/info/`.

- **"Статистика".** `getStatData()` gives the cockpit: the dial of the day, the archive strip kept in
  `storage/counter/archive.json`, the windows, the log of days and `op=live`. The GD chart and the settings `bet` and `shi`
  are gone.
- **"Мониторинг".** The pulse and thirteen signals of `getServerSignalList()`, the tiles over the live chart and the
  findings of the audit; `op=live` takes the place of `op=status`, `op=traffic` and `op=sync`.
- **The screens of the panel.** "Рассылка", "База данных", "Безопасность", "Конфигурации", "Обновления", "Загрузки",
  "Поиск", "Карта сайта" and "Мастер" take the window, the tiles and the meters on top. The key `pane` of
  `getTplAdminList()` puts a list inside a window.
- **The mark "i".** `getTplLongCell()` opens a value of `CELL_LONG` (16) characters or more whole behind the mark, a shorter
  one keeps its title. A cut cell that ends a row stays inside the table, and a status wraps instead of covering its
  neighbour.
- **The meters.** `getTplMeter()` draws a share of a real limit: a campaign with nothing expected shows its figures alone.

### Visits are counted in one place, the files of the statistics, and robots are named by type

The counter of the statistics is the only record of visits; the page `name=referers` and the table `_referer` are gone.
The help of the panel is `admin/info/statistic/ru.md`, the upgrade notes are in `UPGRADING.md`.

- **The fields.** A line of `statistic.log` gains the hosts, the landing pages, the names and the types of robots. A full
  field lets a frequent key in by evicting the rarest, so a burst of spam no longer holds the top.
- **The sources.** A source counts the start of a session only, the new source `ai` is checked first, and the own site is
  told by its domain, so a search with the name of the site in its query is a search.
- **The robots.** `getBotInfo()` reads the masks of five types, and `getVisitBot()` adds a visit without `Accept-Language`
  as a robot; `is_bot()`, `from_bot()`, `engines_word()` and `updateRefererTrack()` are gone. A robot leaves "Аудитория"
  once and no longer writes search words.
- **The settings.** The lists and the masks moved to the settings of the statistics, the tab "Краулеры" of the
  configuration is gone and its tabs after it moved up by one.
- **The cache.** `Cache::setBody()` retries a `rename` on a busy file, as the writer of the configuration does.

## 2026-10-30

### The panel moves by one set of rules and behaviours, and a still panel stops all of it

The effects settled on the demo series "Статистика" became the motion of the admin theme. The reference is
`docs/TEMPLATES.md`, "Panel Shell", "Motion"; the help of the panel is `admin/info/panel/ru.md`.

- **The light.** A light runs round the frame of the cockpit, a home tile and a card of "Мониторинг" under the hand; the
  scan and the comet of the cockpit moved into the block "Motion over the page", and the circles of the cockpit glow at
  the share of the gauges of the presentation.
- **The figures.** The rings, the strip and the queues run to a new value and light once; the arcs of the rings fill
  after them.
- **The home.** The tiles rise in their turn when the grid enters the view and lean to the hand under a glare; a press on
  a tile, a row of the menu or of its counters and a control of the cockpit spreads from the pointer.
- **The charts.** A chart of "Мониторинг" is drawn left to right once when it enters the view, and a poll does not
  draw it again.

## 2026-10-29

### The site builder edits the pages, the menus and the parts of the theme on the page itself, and publishes them with their blocks in one step

The start page was one module of the list in `config/global.php`, and the header, the menus and the footer of lite were
partials that carried the data of slaed.net into every site. A site now has a layout, `config/layout.php`: pages of
sections at addresses of their own, two menus and the parts of the theme, edited by "Конструктор сайта" of the panel
in a draft and published with the rows of `_blocks` they touch. The references are `docs/ARCHITECTURE.md`, "Layout
Boundary", "Address Boundary" and "Block Boundary", `docs/TEMPLATES.md`, "Layout Sections", and `docs/UPDATE.md`,
"Settings, Languages and Themes"; the help of the panel is `admin/info/layout/ru.md`.

- **Pages.** The page `home` of the layout is the start page, its module slot holding the module of the home route
  (the first entry of the list, no longer one drawn at random). A page of sections answers at the root as the kind
  `page` of `_routes`, with 301 from a former address and 410 once deleted, its own title, description, picture and
  `robots`, an entry in the sitemap and the audience "users only"; the page 404 of the layout answers a missing address
  with the code kept. `which` of a block takes `page-<number>`.
- **Sections.** 21 types in six groups: the module slot of the core, 17 of lite, "Свежие материалы" and "Материал" of
  the module Node, "Внедрения" of the module presentation. A type is a description, a fragment and, in a module, a provider; Markdown renders
  through the parser, a video, a map and an outside page load only on a click.
- **The chrome of lite.** The header, both menus, the contacts, the band before the footer, the marks of the footer
  and the side tabs come from the layout by `places.json`; the delivered layout is neutral, and slaed.net takes its own
  by the import of the builder. The marks moved to `uploads/presentation/brand/tech/`, `blocks/menu.php` prints the main
  menu.
- **Blocks.** `setBlockOrder()` is the one writer of the zone and the order; the positions b and f leave, their rows
  moving to d by a statement of `table_update6_3.sql`; an expired block no longer cuts its column, a block without its
  file is hidden from visitors, a Node type carries columns of its own (`view.side`, `view.top`), and `[block=N]` renders
  in a trusted rendering.
- **The screen.** The structure, the page in a frame and the inspector; 50 steps of Undo kept in the draft; the checks
  of links, anchors, descriptions and weights before a publication, with its note; a history of ten with one section
  back; the guest view, a signed link of the draft, ready pages, own sections, the copy of a page and a page from a
  text; the export and the import of the layout as JSON. One draft serves every administrator, and a write that saw
  another revision is refused.
- **Beside its demo.** The screen was set beside the demo series "Конструктор сайта" and built of the canon of the
  panel where the two differed. The page draws at the width of its device, 1366, 820 or 390 px, scaled to the stage;
  the library sets its cards under the heading of their group by the key `order` of a type, each card a schema of its
  type; a zone of blocks carries its icon from `places.json`, the blocks their order in the zone, a menu item the name
  of its page or module; Alt+↑/↓ and Delete move and remove the picked section; the choice of a page lists the modules
  without a page and ends in "+ Новая страница". A button of lite is closed on both sides: no default text without its
  address, and the check names a filled text whose address is empty.
- **The publication.** The file, the addresses and the rows of `_blocks` go in one transaction inside the shared writer
  of `config/`, its proof a witness row; a row changed meanwhile in "Блоки и баннеры" stops it.
- **On the page.** "Конструктор сайта" of the floating menu, one entry in the panel and on the site, opens the builder
  on the page where the administrator stands; in the frame `plugins/system/builder.js` rings a part, types a text in
  place, drags a section and shows its toolbar.
- **One drag of the panel.** Rows of a table or a list drag by their handle; "Блоки и баннеры" post the order, and each
  administrator keeps an order of the panel menu of their own in the cookie `menu`.
- **Tests.** `LayoutTest`, `LayoutDraftTest`, `LayoutPublishTest`, `BlocksTest` and the route tests.

## 2026-10-26

### Every stored text is Markdown by CommonMark and GFM, and BB is left with the six SLAED tags

The parser read Markdown by its own habits and BB beside it. It now reads CommonMark 0.31.2 with the GFM extensions,
every example of both specifications passing in the trusted and in the safe mode; the update turns the BB of every
stored text into Markdown once, and the bracket layer reads `[hide]`, `[tabs]`, `[attach]`, `[usehtml]`, `[usephp]` and
`[block]` alone. The references are `docs/PARSER.md`, "The standard", "SLAED tags" and "Texts of the BB era", and
`docs/EDITORS.md`, "Stored Text on Mount and Save"; the help of the panel is `admin/info/config/ru.md`.

- **What goes.** The smilies `*01`–`*18` become emoji, with `images/smilies/` of both themes and `_SMILIE`; `[u]`,
  `[color]`, `[size]`, `[family]`, the alignment tags, the alignment of an image and ed2k keep their text and lose the
  formatting; the Toast UI quote button and `_EQUOTE` go. `filterClickable()` goes with the settings `clickable` and
  `alink`: a bare address is a link at render.
- **One storage, one rendering.** Every Markdown text is stored as typed through `filterSourceText()`, line ends as LF,
  and mounted in the editor unchanged, so a save without a change keeps every byte; the forum, the signatures, the own
  blocks, the blocks, the admin messages, the newsletters and the contact text render safe with trust, as a Node
  material does.
- **Safe addresses.** A safe rendering keeps an address without a scheme and one of `http`, `https` or `mailto`;
  `data:`, `javascript:` and `vbscript:` die in every mode, but a Markdown image of `data:` passes as an embedded raster.
- **Tests.** `ParserFixturesTest` runs the examples of `tests/Fixtures/spec/`, `UpdateTextTest` the conversion,
  `EditorBreakTest` the storage of every writer.

### The update brings a site of early October forward, and the tables of the gone and the carried modules leave

`slaed.net` runs a development build of early October with its content already in Node, so it is moved by bringing its
own database forward, not by a new installation. The first stage of `update.php` now does that for any such site, and
the whole move was rehearsed on the production dump of 2026-10-25. The references are `docs/NODE.md`, "A site of an
early 6.3 build", and `docs/UPDATE.md`, "Moving slaed.net to 8.0".

- **The catch-up.** The addresses of `uploads/archive/<module>/` and of the old module folders in the Node texts, the
  comments and the other texts become attachments or `go=file` addresses; the options of the release a site lacks are
  merged from `storage/backup/release/config/` and the retired ones leave; eight time columns take their forms with
  every moment kept; a 6.3 `db.php` is written in the collation of the release, so the schema file converts the tables.
- **Tables.** The tables of `auto_links`, `clients`, `clients_down`, `money`, `order`, `partners`, `products` and
  `whois` are dropped with no archive, and the source table of a carried module as soon as every row of it is a
  material, in the first stage for a site already in Node and right after the data step in the second. `jokes` and `media` join the
  6.2 path, the media columns going to the extra fields of its profile and its web links to `download` resources.
- **A unit that ran before.** Points, ratings and fields with their mark set and their manifest missing answer done; the
  units used to stop the whole run on a site brought from its dump, and the tables stayed.
- **The rehearsal.** On a copy of the dump, both stages and a repeat that changed nothing: 36 tables equal to
  `table.sql`, 15385 addresses, 121877 rows in a dump of the core restored and compared table by table, the folders of
  `uploads/` laid out and checked file by file, and the site and the panel checked live in two passes.

## 2026-10-25

### Two writers of one address no longer end a request in a 500 on MariaDB 11.6.2 and newer

With `innodb_snapshot_isolation`, the MariaDB default from 11.6.2, two writers that moved one entity at once, and on
some versions two that took one free path, left the second with error 1020 "Record has changed since last read". The
server rolls the whole transaction back on 1020, and `Route` answered it with `RuntimeException`, a 500 for a form
that only needed its next suffix. A write of `Route` in its own transaction now runs whole again with a fresh snapshot,
at most three times; a write inside the transaction of its writer still throws, as `Point` does, because only the
writer can repeat its operation. The reference is `docs/ARCHITECTURE.md`, "Address Boundary"; `RouteRegistryTest`
runs both races on MariaDB 11.8 and MySQL 8.4.

## 2026-10-24

### The panel gets one shell: the options in place, Ctrl+K, a cockpit, a floating menu, voices and a list canon

The etalons of the panel and of the site under an administrator moved into the themes `admin` and `lite`, so the first
8.0 release brings them to every site. The references are `docs/ARCHITECTURE.md`, "Admin Shell Boundary",
`docs/TEMPLATES.md`, "Panel Shell", "Admin List Contract", "Admin Form Contract" and "Alert Action Slot", and
`docs/WINDOW.md`, "Where the windows are"; the help of the panel is `admin/info/panel/ru.md`, opened from "Опции" and
from Ctrl+K.

- **The shell.** One `h1` on a page, a skip link, the tab title by section, the menu as a drawer on a phone, a filter
  and the current section in the menu, the dial "…" one Tab stop. "Опции" apply in place through op `option` — the
  colour mode, "Движение", "Звук", "Заставка", the floating menu in the panel and on the site, its position, the
  editor; only the language reloads. Ctrl+K opens the sections, the actions and the settings of the page; `[` folds the
  menu into a rail.
- **Queues and the session.** One source of the queues feeds the bell, "Ждут внимания", the chips of the menu and the
  cards and the floating menu, polled once a minute. The panel session ends after the key `idle` of
  `config/security.php` (60 minutes) without an action; a window warns before the end and keeps the session, and no
  poll counts as an action.
- **The home.** The cockpit of a super administrator: four rings, a lamp of fourteen signals with the age of a backup
  against `dump_age`, the strip of the request and a live chip; the warnings about `setup.php`, the self-check and the
  PHP version are signals of the lamp.
- **The block and the menu.** One block "Администрация" in both themes — in the panel on the home only, on the site
  first in its column; the window "Наблюдение" with the present of the visitors; a floating menu in the panel and on
  the site with the pins, the queue, "Все разделы", "Эта страница в панели", the card of the administrator, the own
  links and the window tools.
- **Lists and forms.** Every list of the panel is drawn by one canon: the filter bar, the address with the page, four
  views, a kept sort, relative dates, cards on a phone, deferred actions with Undo, the keys J, K, X, E and "/", and a
  personal number of rows per page; every POST form asks before its changes are left behind, and "Конфигурации" check
  their fields with a summary and find a setting. An alert carries its action in one slot.
- **Voices and the screensaver.** Short voices in `sound/` of each theme answer actions and events after the first
  touch and are switched in "Опции" and by zones in the profile; the screensaver "Возрождение" starts after the chosen
  idle minutes.

Breaking for code outside the tree: `getThemeImageUrl()` and `getThemeImagePath()` became `getThemeFileUrl()` and
`getThemeFilePath()` from the root of the theme (`images/…` in the argument); `isThemeStill()` became
`isThemeFlag($key)`; `setToast(text, warn)` became `setToast(text, opts)` with `{warn, undo, time}`;
`getTplPostButton()` takes the look as its fifth parameter; `Rating::getRatingList($scope, $id, $page, $limit)` pages
by number; `getSelfCheckAlert()` takes no argument; the ops `mode` and `changeeditor` of the panel, the function
`toplist()` of "Поиск" (its op stays on `search(true)`), `getAdminFavoriteList()`, `getAdminPrivateList()`, `getAdminCountRow()`, `_EDITOR_UNDO`
(now `_UNDO`), `_GROUPS_LEAVE` (now `_ADM_LEAVE`) and `_PHPSETUP` are gone; `fragments/admin-block-links.html` is
`fragments/admin-block.html`; "Оптимизация" and "Ремонт" of "База данных" run only by POST, the ban lists are
`op=banlist&kind=ip|user`; `public/sound/` is gone and its address answers 404.

### Every panel list does what "Комментарии" do, and takes it from the canon instead of its module

Until now only "Комментарии" had a filter bar, a relative date, the "i", the key E, row picks with a bulk foot and
"Отметить все N"; every other list glued its date and its tip into its cells and deleted one row at a time. The list
canon `getTplAdminList()` now builds all of it from the meaning a module hands it. The reference is `docs/TEMPLATES.md`,
"Admin List Contract"; the help of each section in `admin/info/` and `modules/*/admin/info/` says what its list does.

- **The row by meaning.** A row hands `name`, a cell `moment`, `details`, the dial items with `is_edit` and `is_find`,
  `drop` and `stat`; the canon draws `<time datetime>` with its relative reading, the "i" in the first cell past the id,
  `data-sl-edit` for the key E and the dial with the delete last. No list function writes a `popover`, a `stamp`, a
  `data-sl-edit` or an `hx-boost`. "Избранное" date by `_favorites.time`, "Голосования" by `enddate`; the bans read as
  the moment they end.
- **Picks and the bulk foot.** Seventeen lists with a deferred delete carry a box per row, "Отмечено: N", the choice of
  the action and OK, with Undo for ten seconds; the lamp of "Сообщения" turns in place, "Вернуть" of the undelivered is
  the action `move`. A delete under a question, "Аннулировать" of a rating vote and a campaign with mail in its queue
  stay single.
- **"Отметить все N".** The twelve paged lists of the foot act on the whole list under its filter: the foot handler is
  posted with `upto` and walks the list in portions of 100 by `setListPortion()`, with a progress line and a pause;
  rows added after the page was drawn are not touched.
- **Filters.** "Избранное", the private messages, "Сообщения", "Голосования", the campaigns and the undeliverable
  addresses of "Рассылка" got the filter bar; the list, its count, the pager, the foot and "Отметить все N" read one condition. A
  language picks its own rows and the rows for every language.
- **Empty states.** An empty list under its filter says "ничего не найдено", without one "нет информации"; the four
  queues of the bell say "всё проверено" in the calm tone. The OAuth provider column is `_OAUTH_PROVIDER`, no longer
  `_MODUL`.

Breaking for code outside the tree: a delete or status handler of the panel lists takes `id[]` (one id is a list of
one), and the delete of a user from the profile and the poll menu of the site post `id[]` too; `getTplEditMenu()`
writes a list of its address back as `key[]`; a ban removal names its list by `kind` (`ip`, `user`) instead of `id` 1
and 3; op `actions` and op `sweep` of "Комментарии", `Comment::setAdminPortion()` and `_COMMENTS_PICK` are gone;
`Privat::getAdminList()` and `Mail::getDeadList()` take the filter.

## 2026-10-18

### A site updates itself from the panel under the signature of the SLAED key, and every failure rolls it back

A site learns of an update only by code it already has, so the updater enters 8.0: without it every 8.0 site would stay
blind to the next security fix. The reference is `docs/UPDATE.md`; the help of the panel is `admin/info/update/ru.md`,
that of the master `modules/master/admin/info/ru.md`.

- **The check.** The scheduler job `updatecheck` asks `https://slaed.net/api/v1/master/check` once a day at a minute of
  its own, HTTPS only and without redirects. The site sends its identifier, channel, version, PHP and database versions,
  three extension bits, two error counts and its domain unless switched off; no text and no path. A banner names the
  release, red for a security fix; notes of the master arrive signed, as plain text.
- **The signature.** A manifest, a note and a key statement verify under Ed25519 keys signed offline on the owner's
  machine; the factory keys `Update::PRIMARY` and `Update::RECOVERY` ship in the core and their fingerprints in
  `README.md`. Without `sodium` the class `Signature` verifies, without `zip` `PharData` unpacks under the site's own
  reading of the central directory.
- **The installation.** "Обновления" downloads with resume or takes an archive by hand only under the manifest the master
  serves now, unpacks into `stage/`, shows what "Проверить установку" would change and installs in steps, each its own
  request, while `apply.lock` closes all three entries: the database in portions of the site's own tables, the files by
  renaming through the executor of the new package, the database steps in the light mode `UPDATE_FILE`, the merge of
  `config/`, the self-check. A failure rolls back files, `config/` and database with the caches; a rollback code and the
  file `rescue.now` reach the installed `core/rescue.php` when the core no longer boots; "Вернуть предыдущую версию"
  restores the last installation. The format of `apply.lock`, the backup and the portion dump is frozen as contract
  version 1.
- **Settings, languages and themes.** An update adds the options a site lacks at any depth and keeps every saved value;
  the language files and the themes `admin` and `lite` are replaced whole, other themes are not touched.
- **The manual path.** While `SL_VERSION` is above the last database step done, every entry answers 503 and only the
  light mode of the panel opens, with "Применить шаги базы" after a backup of the site's tables; after the steps it
  clears `config/local.php`, the caches of pages and assets and OPcache, as an installation does.
- **The release archive.** It carries only what a site runs. The modules `master` and `changelog` of slaed.net with the
  changelog templates, `public/update.php`, `table_update6_3.sql`, `tests/`, `tools/`, `docs/`, the stands
  `public/demo/`, `public/phoenix/` and `public/sound/`, `composer.json` and the other files of development and documents
  of the root stay in the repository; the build cuts the rows of the modules it leaves out from `config/modules.php`.
  Such a module keeps its name in its own languages, read by `getModuleLabel()`, and declares its scheduler jobs in its
  `scheduler.php`. The GitHub release goes to the repository `Update::PAGE` names, with the two signed archives.
- **The master.** The module `master` of slaed.net accepts releases and notes from `inbox/`, serves them by channel,
  share and pause, keeps a minimal statistic without an IP, pauses a release by itself on failed installations and
  mails the owner. It is in the repository and never in a release archive.
- **The tools.** `tools/release.php` builds the full archive and the delta from a tag and publishes, `tools/sign.php`
  signs and runs the key ceremony, `tools/rehearse.php` installs the previous release clean and updates it by both
  archives; `publish` refuses without that green rehearsal and with test factory keys. Before the first release the
  whole chain went through a real master on two OSPanel projects, a key change included.
- **The API.** The branch `/api/` of `public/index.php` is the one entry: the light core, the guards of `.rules/api.md`,
  the envelope, the reply mode `file`, `system/ping` and `system/schema`.

Breaking for code outside the tree: `$conf['version']` is built from `SL_VERSION` and `SL_CODENAME` of
`core/version.php` and is no key of `config/global.php`; every entry that loads the core calls `getSiteGate()` of
`core/gate.php` first, before the core; the language files of the shipped modules and the themes `admin` and `lite` are
overwritten by an update; `public/sitemap.xml` left git, `public/robots.txt` is a site file; `_CHANGELOG` and `_MASTER`
left `lang/*.php` for the languages of their modules; `addMasterTask()` is gone, a module job runs through
`addSchedulerModuleJob()`; `getModuleName()` answers the name of a site module its map lacks, `node` included, instead
of its key. The shipped `config/` carries no value of the stand: no test ban, no row `master`, error level 0.

## 2026-10-14

### Every page has one readable address without an id, and every other form of it answers one 301

The links of 6.2 were `index.php?name=files&op=view&id=5061`, and its switch of readable addresses could not work:
switched on, it printed `faq%2Fview%2F4048`, which Apache refuses before PHP, and nothing parsed a path. The addresses
change once, before the release, so the index of a search engine moves from the 6.2 query addresses straight to the
final form. The reference is `docs/ARCHITECTURE.md`, "Address Boundary"; the addresses of a type are in
`docs/NODE.md`, "Addresses", the links of templates in `docs/TEMPLATES.md`, "Links In Templates".

- **The addresses.** `/files`, `/files/adaptivnyi-shablon-pure`, `/files/moduli`, `/files/moduli?page=2`,
  `/forum/modul-poiska`, `/users/eduard`, `/voting/kak-vam-novyi-dizain`, `/files/add`, `/files/asset/2`. The prefix
  of the type is part of the address, the separator a fixed `/`; materials and categories of a type share one
  namespace; a private type keeps `/<type>/view/<id>`. AJAX, feeds, the panel and `/uploads/` keep their addresses.
- **One registry.** `_routes` maps a path to its entity — the current path and every former one; one class, `Route`,
  owns it. An address is never reused: a former path answers 301 to the current one, the path of a deleted entity
  410, and both stay taken. `filterSlug()` transliterates the letters of all six locales and cuts to 80 characters.
  Every writer of a material, type, category, topic, account and poll gives, moves and marks gone its paths inside
  its own transaction; the address follows the title until the first publication and then changes only by the field
  "Адрес". The name of a Node type is permanent, and the name of a deleted type is never given again.
- **Migration.** `addRouteBacklog()` gives every entity due an address its first path, categories before materials
  and the oldest first, from the second stage of `update.php` and from `setup.php`. No 6.2 path form is parsed: 6.2
  printed query addresses, and those answer one 301.
- **Parsing.** `core/security.php` cuts a path of up to five words before the database, `public/index.php` resolves
  it by one grammar against the configuration and the registry; `getVar()` reads scalars from `$_GET` and `$_POST`,
  so the keys of a path reach every reader. One indexed query per page view.
- **Building.** `getSeoUrl()` builds the address from the path the reader joined, operations and a numeric `id` in the
  path, the rest of the query sorted; every address the site prints starts at the root of the site, `HOME_PATH`, and
  templates take `{{ root }}`. The query counts of every list page stayed as they were.
- **One redirect.** `setSeoMove()` answers a former path, upper case, a trailing slash, `/index.php/`, a query address
  of 6.2 or 8.0, `?num=`, `page=1` and the wrong scheme or host in one absolute 301, after the entity is read and its
  access checked, for `GET` and `HEAD` only. `robots.txt` names the sitemap absolutely, written by the sitemap task,
  and lost the `Disallow` lines of folders no longer under `public/`.
- **The servers.** Checked live on Apache with the root on `public/` and on the project and on nginx: the same answer
  for every address of the route matrix and every link the pages print. The `.htaccess` of the project turns off the
  slash redirect of `mod_dir`, which sent `/docs` to the folder `docs/` of the project and back in a loop;
  `public/.htaccess` and `nginx.conf.example` refuse every dot file but `.well-known/`, which nginx served.
- **Pages.** A public list counts its pages by `page`; the panel keeps `num`. A profile of a user that does not exist
  answers 404, a profile closed to guests 403.

Breaking for code outside the tree: `rewrite`, `sep`, `tsep`, `title` and `ctitle` are no settings and no keys of
`getSeoUrl()`; `getSeoUrl()`, `Template::getAssetUrl()`, `getUploadUrl()` and `FileAccess::getFileUrl()` answer
addresses from the root; an entity link passes its `route` (`null` for none), and a link of `op=view` without the key
is logged; `getVar()` no longer reads through `filter_input()`; `getPublicUrl()` takes an address as a string too and
`setRedirect()` roots a relative target; `filterSlug()` lost its separator; `user_info()` takes the path of the user
as its second argument; `getPageNumbers()` writes `page` on the site and takes the keys of its page as an array;
`NodeService::addNodeCategory()` and `updateNodeCategory()` take the field "Адрес", `Node` and `NodeInput` end in a
path; a page link in a template or a script starts at `{{ root }}` or `HOME_PATH`. The server must send every path
that is no file to `index.php`, as `public/.htaccess` and `nginx.conf.example` do.

## 2026-10-13

### The schema takes one set of column domains, one connection contract and keys that serve the queries that run

Every change of a type, a key or a collation after 8.0 would be a migration step on every installed site, so the whole
schema was brought to one set of conventions before the release. The reference is `docs/NODE.md`, "Schema
conventions"; `tests/SchemaConventionTest.php` holds them for every later table.

- **The connection.** `Database::__construct()` takes the collation and sets it with `utf8mb4`, the time zone of PHP
  (by name where the server refuses the offset) and one strict `sql_mode` with `ONLY_FULL_GROUP_BY` on every path —
  the site, the panel, `setup.php`, `update.php`, the tools and the probes that load the code — so `NOW()` and
  `date()` write the same moment and MariaDB refuses what MySQL would. The zone line of `core/system.php`,
  `$conf['db']['sync']` and the panel switch `dbsync` are gone; the referer list groups by its newest row.
- **Text.** `utf8mb4_unicode_520_ci`, the newest collation both MySQL and MariaDB know, comes from `{collate}` of
  `config/db.php` alone. `ip`, `modul`, `scope` and `lang` are binary ASCII of one width each, a `lang` column holds a
  code. `filterCutText()` cuts a stored, escaped value to its column without splitting an entity, and every writer of
  an outside value uses it; a fixed choice is checked against its values.
- **Time.** A moment is `DATETIME`, a machine timer `BIGINT` unix seconds, and no column holds a time as text:
  `_mail.ntime` and `locked`, `_node_publish.due`, `_blocks.time` and `_rating.time` are timers, `_blocks.expire`,
  `_message.expire`, the stamps of `_rating_*` and `_user_oauth` are moments, `_users.lastvis` is NULL for never.
  `_users_temp.time` leaves. The forum marks a topic new only when it changed after the last visit, not every topic.
- **NULL.** No flag, counter or text is NULL. `_users.viewmail` is 0 where it was NULL, so an address stays hidden as
  the profile showed it, and saving the settings untouched no longer publishes it. An empty birthday is NULL; the
  false ones a date filter wrote into empty fields are gone.
- **Keys.** `_users.email`, `_admins.email` and `_users_temp.email` are unique with NULL for "no address";
  `Database::getSqlTwin()` names the key a duplicate hit, and every writer answers it as "address taken". Covered keys
  and `(191)` prefixes leave, unused keys leave, the lists of the forum, comments, blocks, categories, search and
  support get the keys they sort by, and `_node_categories` keys on `(nid, cid)`.
- **The 6.2 path.** `table_update6_3.sql` converts column by column, reaches every form through `runiftype()` and sets
  the keys through `setidx()`, both doing nothing on a second run; `update.php` stops before any change while two
  accounts share an address, and maps language names to codes. Any clean 6.2 site migrates: the rehearsal ran on
  MariaDB 11.8, MariaDB 10.11 and MySQL 8.4. `setup.php` starts its session only when none is open.

Breaking for code outside the tree: `Database::__construct()` requires the collation; `$conf['db']['sync']`, `dbsync`
and `_users_temp.time` no longer exist; the time columns named above changed type; an address column is written NULL,
never `''`; a `GROUP BY` must name every column it selects. The recommended servers are MySQL 8.4 and MariaDB 10.11 or
newer; the floor stays MariaDB 10.5.2 and MySQL 8.0.16.

## 2026-10-12

### Every journal of the site takes one format, the panel records each action, and every login lands in a table

No change made in the panel was recorded anywhere. A login was a block of text lines in `admin.log` or `user.log`, an
OAuth login was written three times in two formats, `oauth.log` and `database.log` kept forms of their own, and a user
could see none of their logins. The reference is `docs/ARCHITECTURE.md`, "Journal Boundary"; the help of the screen is
`admin/info/security/ru.md`.

- **One format.** `Logger` gains the channels `admin`, `user`, `oauth`, `database` and `filescan` with a method each,
  and `addRequest()` for the request journal; `addEntry()` is protected. The five carry the address, the method, the
  URL and the browser of the request, never its body. `Oauth::setLog()`, `addDblog()` of the database tool and
  `write_log()` of the file scan are gone; every journal rotates through `addCompress()`, `oauth.log` and
  `database.log` included.
- **The actions of the panel.** `checkAdminPost()` notes a passed token check through `setAdminLog()`, `setRedirect()`
  gives it its result, and `addAdminLog()` writes one line of `admin.log` when the request ends: the administrator,
  the module, the operation, the object and the number of a bulk action, `done`, `refused` or `open`. Every module is
  covered by that one point, one written later too; the drag-and-drop order of categories and blocks moves onto
  `checkAdminPost()`. The switch `log_a` covers actions and logins of the panel.
- **Logins.** `addLoginReport()` becomes `addLoginLog(int $uid, bool $adm, string $res, string $login, string $way)`,
  the one writer of a login: a row of the new table `_login_log` always, an event of `admin.log` or `user.log` while its
  switch is on. `oauth.log` keeps the events of the protocol and no longer a second record of the login. The monitor
  counts the refused logins of the panel from the table, and `cachegc` removes rows older than 90 days.
- **Reading.** A user reads their own logins in the profile, «Журнал входов»; the panel reads them all on the tab of
  the same name of «Безопасность» with filters. Every journal opens there as a table with filters of level, period,
  address and, for `admin.log`, event, module and result, and the pager; `filescan_tree.log` keeps the raw view. Every
  module ends its tab strip with «Журнал действий», that table filtered by the module, for an administrator who may
  open «Безопасность».

Breaking for code outside the tree: `addLoginReport()`, `Oauth::setLog()` and `write_log()` no longer exist, and
`Logger::addEntry()` is not public. The 6.2 update creates `_login_log`; the old text blocks of `admin.log`,
`user.log` and `oauth.log` stay in their files and the table view skips them.

### The rank of a group is a row of icons in a colour of its own, and the group list becomes a ladder with a rank composer

A rank was one of fourteen 93×17 pictures in `templates/lite/images/ranks/`, which seven readers found each their own
way, and the panel listed the groups as one table and picked a rank from a `select` of file names. The rank is now drawn
with Bootstrap Icons from one stored string, and the groups screen wears the face of the stand page
`public/demo/grp-10-unified.html`. The reference is `docs/TEMPLATES.md`, "Rank" and "Group Ladder And Rank Composer";
the help of the screen is `admin/info/groups/ru.md`.

- **The rank as icons.** `_groups.rank` holds `icon:lit:total:#rrggbb`: M marks of one icon, the first N lit in the
  colour of the rank, the rest in a muted tone. `getRankData()` and `getRankView()` in `core/system.php` parse and draw
  it through `fragments/rank.html`, the same in both themes; the comment, the forum post, the profile, the user block,
  the users statistics and the panel list pass the raw column. The schema does not change.
- **The migration.** `table_update6_3.sql` maps the fourteen pictures onto icon strings in tones darkened to hold 3:1 on
  the light and the dark surface — steel `#8297a2`, gold `#b0904d`, bronze `#c68649`, sky `#629abf`; a site's own
  picture becomes no lit star of five in its group colour. Only values ending in a picture extension change, so a second
  run changes nothing.
- **The ladder.** «Главная» shows «Лестница пунктов», the point groups by threshold, and «Специальные группы», by
  members, as steps on a rail: the threshold or the mark «Специальная», the rank and the name in the group colour, the
  description, «Участников: N» as a link to the user list over a bar of the share, the dial. Each list reverses in place
  by its sort button. One query counts the members of every group.
- **The composer.** «Добавить» holds «Готовый ранг» (eighteen presets), «Свой ранг» (forty icons and the icon window,
  the counts, the rank colour as swatches, an own colour or the group colour) beside a preview at three sizes and two
  contrast tiles for the light and the dark theme, and «Группа», where the threshold says where the group will stand. A
  new group opens filled as the next step of the ladder. The rows of choices are toolbars with one Tab stop, and leaving
  a changed form asks first.
- **A painted name stays readable.** The panel clamps the lightness of a group colour in both themes, so any stored
  colour reads at 4.5:1; the stored value is untouched.
- **`save()`** writes the joined string, clamps the counts, and refuses a bad icon, a bad colour and a threshold
  another point group has.
- **Strings.** The captions of both screens, the eighteen preset names and the colour names in six locales;
  `_POINTSNEEDED` reads «Порог пунктов»; `_GROUPSI` and `_COLOR` are gone.
- **Breaking:** an old picture name in `_groups.rank` is not drawn until `update.php` ran, and the folder
  `templates/lite/images/ranks/` is gone. The templates take `rank_html` in place of `rank_link`, `rank_src`, `rank_url`
  and `has_rank_image`, so a theme with its own copy of the comment, the forum post, the profile or the user block shows
  no rank. `.sl-profile-rank-img` and the site token `--sl-rank-height` are gone.

## 2026-10-11

### One segmented switch with a travelling plate, and one pager for both themes that pages with the arrow keys

The colour mode of the site header was a rail with a plate that copied the chosen icon and stepped by an `nth-child`
ladder, the site pager a band of mini buttons with its line above it, the panel pager a row of `sl-pnum` cells with
drawn arrows, and "Наверх" a script in the panel and a hidden link on the site. The switch and the pager now wear the
faces of the stand pages `public/demo/pg-09-final.html` and `pg-14-admin.html`, chosen by the owner. The reference is
`docs/TEMPLATES.md`, "Segmented Switch" and "Pager".

- **One segmented switch.** `sl-seg` with `sl-seg-cell`, the empty plate `sl-seg-knob` and `sl-seg-run`, in both
  themes, with the modifiers `sl-seg-mode` (site header only), `sl-seg-view` (the view of a Node list) and
  `sl-seg-pager`; eight `--sl-seg-*` tokens in the API block of each `base.css`.
- **The plate travels.** A rail names itself in `data-sl-knob`; `slaed.js` measures the chosen cell and the sheet moves
  the plate. A link, the mode form and an htmx swap hand the place to the next page through `sessionStorage`, so the
  plate moves from where it stood; nothing moves under `prefers-reduced-motion`, and without the script the chosen cell
  wears the plate.
- **One pager.** The same `pager.html` and `pager-link.html` in both themes: the arrows, the numbers on the rail with
  the current page in the brand colour, "Наверх" and an "i" beside them, and the line "Страница 3 из 10 · Показаны
  21–30 · Всего 97" under the row on the site and at the right of the row in the panel. The pager narrows by its own
  column — far numbers and the range go under 520px, the neighbours under 320px — and the panel list foot puts the bulk
  action under it on a phone.
- **The arrow keys page the list.** ← and → follow the back and forward link of the first pager on show, by a click so
  an htmx pager swaps its part; a field, an editable, a tab, an open window and the file desk keep their arrows. The "i"
  explains the keys in the system tip and is hidden on a touch screen.
- **One "Наверх".** Every link to the top points at `#top` and the page scrolls smoothly by CSS, switched off under
  `prefers-reduced-motion`; `Upper()` of `slaed.js` and the inline handler of the panel footer are gone.
- **Strings.** `_PAGENAV`, `_PAGERANGE`, `_PAGEKEYS`, `_PAGEPREV`, `_PAGENEXT`, `_PAGEKEYNOTE`, `_KEYLEFT`, `_KEYRIGHT`
  in six locales; "Всего" in the page line carries no colon; the French `_PAGETOP` reads "Haut de page".
- **Breaking:** a theme with its own `pager.html` or `pager-link.html` keeps its old face and loses the marks, the tip
  and the keys; the fragments take `is_far`, `is_near`, `is_dots`, `rel`, `key_name` and `range_*`, and
  `.sl-pager-nextprev`, `.sl-pager-prev`, `.sl-pager-next`, `.sl-pnum-nav`, `.sl-pnum-cur`, `.sl-pnum-dots`,
  `sl-mode-rail`, `sl-mode-cell` and `sl-mode-knob` are gone, as is the `is_pager_dots` branch of `inline-badge.html`.
  The panel API loses `--sl-pnum-width`, `--sl-pnum-height`, `--sl-pnum-border`, `--sl-pnum-arrow-width`,
  `--sl-pnum-arrow-height` and `--sl-card-shadow`. `getFootControls()` takes no `$label` and `$debug` any more, the
  `debug_html` branch of `foot-controls.html` and the flag `is_upper` of `link.html` with them, and a list of keys
  of `link.html`, `span.html` and `toggle-form-block.html` no caller passed is gone.

### A Node list gets a header of its own, offers the views of its type, lives in place and hints its search

The list of a Node type had the shared navigation of the modules: a title, three fixed links and a category toggle,
no count, no direction, no search and no feed link, while the RSS channel named the engine and was never cached. The
list now wears the face of the stand page `public/demo/nh-13-calm.html`, chosen by the owner among thirteen. The
reference is `docs/NODE.md`, "List header", "Views of a list", "Live list", "Search" and "RSS", and
`docs/TEMPLATES.md`, "Node List Contract".

- **The header.** `partials/node/head.html` from `getNodeHead()`: the title with the count "19 из 97 материалов" in the
  plural form of the locale (`getPluralForm()`), the view switch, the search field, the RSS button; the windows of the
  categories and the letters with their counts, the sort window with "С прошлого визита" and "В избранном" for a
  signed-in visitor, the direction pill, the reset, "Добавить". The bar sticks while the list scrolls; on a narrow bar
  a lens opens the search over it. Every control is a link or a form the server answers; a sort, the direction and a personal filter keep the other
  filters.
- **Counts that cost nothing on a warm request.** `getNodeCountMap()` keeps one file per type, an entry per set of
  readable categories, until a write, a new type version or the next scheduled publication or expiry.
- **Views by type.** `list.views` offers cards, compact, list, accordion, tiles and a rail; the shipped `news` offers
  cards, compact and tiles, `docs` list and rail, `faq` accordion and list. The visitor's choice is a cookie
  per type set by a POST with a token; a list by publication is headed month by month.
- **A live list.** The links of the header and the pager swap the part through htmx and push the address, an
  `HX-Request` gets the part alone with its title, back and forward ask the part again; "Показать ещё" appends the next
  page and the address takes its `num`.
- **Search hints.** Up to five titles of the type under the field after 300 ms, each a link to the material, from the
  light route `go=1&op=getNodeHint` under the read rights of the visitor; the arrows, Enter and Esc work the list.
- **The feed.** The channel names the site, the type and the category, links the list it mirrors, dates itself by the
  newest item, names the site in the copyright, answers 404 for a closed category and 304 to a reader that holds its
  tag, cached private with revalidation instead of no-store.
- **Breaking:** `config/node.php` needs `node.defaults.list.views` (`[]`), without it every type is refused at `list`.
  A theme's `partials/node/list.html` receives `head_html` instead of `navi_html`, `groups` and `rail` instead of
  `items_html`, and the categories and letters inside the header; `getNodeNavi()`, `partials/categories.html`,
  `partials/node/media/list.html` and the category toggle of `fragments/link.html` are gone. A POST to a Node list
  answers 303 instead of 405. `sl-pmf-day` is `sl-date-head` (the token keeps the name `--sl-pmf-day-bg`),
  `sl-pmf-split`, `sl-pmf-aside`, `sl-pmf-view` are `sl-duo`, `sl-duo-aside`, `sl-duo-main`. `_PRSORT` and the `_SORT`
  of `admin/lang/` are one `_SORT` of `lang/`, `_ASC` and `_DESC` moved there too, `_CATVORH` is gone. The title of a
  site page is `getHeadTitle()` in place of the code inside `setHead()`.

## 2026-10-09

### Plain Plus and CodeMirror share one editor shell, and CodeMirror is also a text editor to choose

CodeMirror served the code screens of the panel as one 240 KB bundle with One Dark painted over the theme, and Plain
Plus was a bare textarea. Both now stand in one shell with one runtime, and CodeMirror is a fifth text editor beside
Plain Plus, Toast UI, TinyMCE and CKEditor. The reference is `docs/EDITORS.md`; the fragments are in
`docs/TEMPLATES.md`, "Editor Fragments".

- **CodeMirror in two roles.** Its manifest declares `type` `["content", "code"]`, which a manifest may now give as one
  string or a list; it stores Markdown as a text, as Toast UI does, so a site moves between the two without touching a
  stored text. It is offered to members and administrators and given to nobody who did not choose it.
- **Painted by the theme.** `classHighlighter` writes `tok-*` classes that both `theme.css` colour, so code follows the
  colour scheme of the page; One Dark is gone. Code everywhere, the code blocks of the parser included, reads
  `--sl-face-mono` and the token tones; a `[code]`, `[php]` or fenced block carries its language, a copy button and a
  fold above 25 lines.
- **Loaded by need.** The build writes `core.js`, one `lang-*.js` per language and shared chunks; the runtime imports the
  core and one grammar when a frame comes near the screen: 133 KB gzip for the core, 134 to 226 KB with a language,
  against 240 KB for every code screen before.
- **One shell.** `editor-frame.html` and `editor-kit.html`, byte-identical in both themes, and `plugins/system/editor.js`:
  the format tab, the capsule, the status line with the caret, the size and what the column has left, the dirty mark,
  full screen, wrap, copy, reset, undo, a draft of the tab in `sessionStorage`, and a palette on Ctrl+K.
- **Text abilities.** The file window and the emoji panel serve Plain Plus and CodeMirror as text through an adapter;
  a pasted or dropped image goes to the upload; Markdown commands and lists continued by Enter on a Markdown field.
- **What the page tells the editor.** `vars` turns on the variable list, the hint and the marks; `lint` the check of a
  template with its badge, list and fixes, and "тег на строку" and "в одну строку"; `preview` a sandboxed preview of a
  template with bundled samples or of a text through the new route `getEditorPreview` (`go=1` and `go=5`, POST with a
  token). The upload templates of `tplconfig` pass all three.
- **Comparison.** "изменено" opens the loaded text against the present one, together or side by side, and "Вернуть было";
  a long text is aligned by lines first, so a text of eighty kilobytes with edits in three places opens in about a
  tenth of a second.
- **Breaking:** `cm6.bundle.js`, `build/entry.js` and the global `CM6` with `CM6.editors` and `CM6.oneDark` are gone; a
  script of a screen reads and writes an editor through `window.SlaedEditor`. The mount class `sl-code-editor` is
  `sl-editor`. `isValidEditor()` of `admin/index.php` is gone, its callers use `Editor::isValidEditor()`. The emoji panel
  left the Toast UI plugin: `SlaedEmoji` in `plugins/system/emoji.js`, its words in `plugins/system/emoji/<locale>.js`,
  the partial `emoji-panel`; the plugin's `assets/i18n/emoji-*.js` are gone. `_TPINFO` left the language files for ten
  `_UPLOADS_*` constants. `plugins/highlightjs/slaed-theme.css` and its `css_f` default are gone; the theme styles
  highlighted code.

## 2026-10-07

### A saved text keeps its line breaks in every editor format

`getTplTextarea()` deleted every `<br>` of a stored value before an editor saw it, for every format but `html`. The
`plain` save put them back through `nl2br()`; the `markdown` save of Toast UI, the editor of the site, did not. Every
member who saved anything on the account settings lost every line break of the signature and the custom menu, which
both show on every page: one save took the 22 breaks of a 1475-byte menu.

- **The mount.** A stored `<br>`, with the line end after it, mounts as a line end for `plain` and as a Markdown hard
  break, two spaces and a line end, for `markdown`; `html` mounts the value as it is. Measured in the browser through
  one save of the account settings: the menu keeps its 22 breaks as hard breaks and a second save changes no byte.
- **Stored form.** A value saved in `markdown` holds two spaces where it held `<br>`, two bytes less per break; every
  render of the parser, the escaped one of comments and the forum included, draws them as `<br>`, where an escaped
  render used to print a stored `<br>` as text. A line of nothing but a `<br>` becomes an empty line, so the lines
  around it render as two paragraphs.
- **Test.** `EditorBreakTest` drives stored values through the shipped mount, the browser submit and the save of both
  formats. The reference is `docs/EDITORS.md`, "Stored Text on Mount and Save".

### The private part of an installation is out of web reach, proves it every hour, and no journal holds a secret

The whole project sat in the document root and kept its private part with 95 `.htaccess` files of `deny from all`,
which nginx never reads; the login journal held the first 25 characters of every refused password and the request
journal the whole request with cookies and session. One server misconfiguration was a credential leak, and the
installation had no way to notice. The current state is in `docs/ARCHITECTURE.md`, "Private Data Boundary".

- **No secret in a journal.** `addLoginReport()` records who tried, under which login, from where and when, and
  rotates before it opens the file. The request journal is the channel `request` of `Logger` with its masking and
  only the names of cookies and session keys; `addLog()` and `getVariablesInfo()` are gone.
- **The document root is `public/`.** The entries, `.htaccess`, `robots.txt`, `favicon.ico`, `error.html`, the
  sitemap, `templates/`, `plugins/`, `sound/` and `demo/` are in it; the code, `config/`, `storage/` and the one
  upload root `uploads/` stay at the project level. Every entry defines `BASE_DIR` and `PUBLIC_DIR`, and no path relies
  on the working directory. A request under `/uploads/` reaches the light path of `core/stream.php`, which serves a
  public folder before the core boots and answers 410 for everything else; `getFileStream()` moved there with it and
  learned the public mode.
- **Two modes.** A root on `public/`, or on the project with the `.htaccess` of the project rewriting into `public/`
  and refusing everything without `mod_rewrite`; the installer records `webroot`. `nginx.conf.example` is the server
  block of the first mode, and `NginxConfigTest` keeps it refusing what `public/.htaccess` refuses. The help of the
  security section names the variants of shared hosting.
- **Guards gone.** Every `.htaccess` outside `public/` but the one of the project and every guard `index.html` outside
  it left the tree with their writers (`NodeService::setTypeGuards()`, `FileManager::getGuardFiles()`, the guard
  writer of `CaptchaStore`); the upload service creates a missing folder of its owner on the first write.
  `setup_old/` left the tree.
- **The self-check.** A marker `check.txt` in the project, `storage/`, `config/`, `uploads/` and `admin/info/`, asked
  over the site address every hour by the job `selfcheck` and judged by the body: `open`, `closed` or `unknown`. The
  home of the panel warns about a problem or a missing verdict, the security section shows the all-clear too, and a
  Node type is switched on only while `uploads/` is closed.
- **Journal names.** `error_*.log` is what failed, `<meaning>.log` what happened: `admin.log`, `user.log`,
  `oauth.log`, `request.log`, `filescan.log`, `filescan_tree.log`, `filescan.json`, and the new `file.log` for file
  operations below `error`. `Logger` and `addCompress()` name an archive `<name>_<date>.log.<ext>`; the locks moved into
  `storage/cache/locks/`. The dashboard error counter reads the structured lines and counts the problem levels, so it
  no longer reports zero whatever the journals hold.
- **Breaking:** the document root moves to `public/`; `addLoginReport()` lost its password parameter; `addLog()`,
  `getVariablesInfo()`, `FileManager::getGuardFiles()` and `NodeService::setTypeGuards()` are gone; the journals carry
  the names above and the label key `log` of the security section is `request`; `$conf['users']['adirectory']` is
  the folder name `avatars`. The journals of an existing installation are not renamed: 8.0 installs a new site.

### Every asset address carries the version of its content, the browser keeps it a year, and the head scripts run deferred

No static file carried a version, so a long browser lifetime would have kept an old file after a release. nginx sent
no `Cache-Control` at all and the `mod_expires` block of `public/.htaccess` never applied on the stand; a browser
guessed a tenth of a file's age, so right after a release every returning visitor asked for every file again, and a
file untouched for half a year stayed about eighteen days after it changed.

- **Versions.** Every printer of a stylesheet or script address goes through `Template::getAssetUrl()`:
  `theme.css?v=0a5af6e4ae`, ten hex characters of the SHA-1 of the file, from the map
  `$conf['derived']['version']` that `Template::getAssetVersions()` builds of `templates/` and `plugins/` with
  `config/local.php` (`cache_version` 5). A request stats no asset file; `dev_mode`, `setup.php` and `update.php` hash
  per request. The highlight versions are part of the parser cache key. The robots screen lost its second tag of
  `editor-robots.js`.
- **Lifetimes.** A style or script with `v=` in the query is kept `public, max-age=31536000, immutable`, any other
  static file `public, max-age=604800`, revalidated by `ETag`; pages stay `no-store`, the public uploads keep the day
  of the light path. `public/.htaccess` sets this through `mod_headers`, `nginx.conf.example` through a server-level
  `$asset_cache` and turns `gzip` on for the types the `.htaccess` deflates.
- **Defer.** `doScript()` prints every head script `defer` in the order of `getAssetList()`.
  `Editor::getInitScript()` waits for `DOMContentLoaded` while `window.SlaedEditors` is missing, so an editor of a
  page load registers its teardown.
- **Measured** on the stand, median of three runs, guest, start page / list / view against the baseline of the same
  day: a warm page costs one network request (the page) as before, now for a stated year instead of a guess; cold
  376 576 / 353 477 / 643 884 → 377 584 / 354 380 / 645 434 bytes (the `?v=` of every address), warm 30 449 /
  16 483 / 21 047 → 30 593 / 16 536 / 21 309 bytes, first paint warm 536 / 300 / 364 → 540 / 352 / 348 ms, within
  the spread of the runs. The current table is in `docs/PERFORMANCE.md`.
- **Breaking:** the settings `script_a` (async scripts) and `script_b` (scripts at the end of the page) left
  `config/global.php` and the settings screen, and `update.php` drops both from a carried 6.2 configuration. The
  `mod_expires` block left `public/.htaccess`; an Apache without `mod_headers` sends no lifetime. A theme or plugin
  file replaced by hand needs `config/local.php` rebuilt to reach the browsers.

### Retired addresses answer 404, 410 or 301, and the statistics append only what they are given

Two findings of the production logs of 2026-08-18 to 2026-08-21, neither of them the cause of that day's outage
(PHP-FPM had stopped, nginx answered 502).

- **A path that is no address answers 404.** The router read only the query, so `/news.html` of the long-retired
  `.html` scheme rendered the start page with 200 — 7058 requests in 2.9 days, almost all of them machines. Any path
  that reaches `index.php` other than the folder and its scripts now gets the standard 404 page; a real `.html` file
  is served by the server first, and a status an error document brings in `?error=` is kept. No table of the old
  scheme is built.
- **A path after a script answers 301.** `/index.php/index.php?name=sitemap` (1049 requests, from links Google
  keeps), `/index.php/` and `/admin.php/x` answer 301 to the script with the same query, or to the folder for a bare
  `index.php`. The target is built from the script name, which closes the open redirect of the old rule
  (`//other.host/index.php` sent `Location: //other.host/`); `nginx.conf.example` sends such a path to the script.
- **A retired file answers 410.** A missing file under `uploads/` and the direct address of a file of a closed owner.
- **`addFile()` writes data only.** It took its second argument as a source file or as the data and told them apart
  with `is_file()`. The visit statistics append an address or a user name followed by a comma, so every new visitor
  probed a path, which `open_basedir` logged as a warning (`File(141.148.184.29,) is not within the allowed path(s)`),
  and data that named an existing file would have appended the content of that file. `addFile(string $file, string
  $data, string $mode = 'w'): bool` writes or appends exactly the data; the compression branch had no caller and went
  with `$comp`, `$del` and `$max`.
- **Breaking:** `addFile()` returns `bool` instead of the codes `0`–`3` and drops its parameters `$comp`, `$del` and
  `$max`; a caller that copied a file reads it first.

### Every uploaded file leaves through the owner of the text that names it

Node closed its upload folders and served a file to the reader of its material; every other module linked its files
directly in open folders, so an attachment of a private message or of a post in a closed forum category reached
anyone who had its address. Every closed owner now has one route and one rights check (`docs/ARCHITECTURE.md`, "File
Delivery Boundary").

- **One route for every owner.** `index.php?go=file&own=<owner>&id=<target>&key=<name>` serves Node, the forum, the
  private messages, the comments on polls and profiles and the texts of an account; `FileAccess` asks the adapter of
  the owner whether the stored text carries the name and the reader may read the text. Every refusal is 404.
- **Closed folders.** `uploads/forum/`, `uploads/account/` (private messages alone), `uploads/voting/` and the new
  `uploads/profile/` (signature, own block, comments on a profile) answer 410 at their direct addresses, as
  `uploads/archive/` does; `uploads/all/`, `uploads/avatars/` and `uploads/presentation/` stay public.
- **Node under one root.** A type keeps its files in `uploads/node/<type>/` (`NODE_DIR`, `getUploadFolder()`), so a
  type can meet only another type there.
- **A signature renders the same everywhere,** in the file context of its account, on the forum, the profile, the
  private message and the comments; the comments printed its stored source unparsed until now.
- **No foreign file through a new text.** A writer binds a new `[attach]` name only when it is an own upload, the
  writer moderates the folder, or the same target already serves the name; a refusal names the file.
- **The rule governs the upload.** The extensions of an upload rule decide what may be uploaded, previewed and newly
  bound; a name a stored text carries is served whatever the rule says today.
- **Full-size attachments.** `[attach … size=full]` shows an image at its own size through the template `full` of
  `config/filetype.php`; the insert window of the editor offers "Thumbnail" or "Full size".
- **Unused files.** The file browser of the uploads screen filters a Node type, the forum, the private messages, the
  profile texts, the poll comments and the avatars to the files no stored row names, older than a day; the marking
  and the deletion of the browser remove them.
- **The update of `slaed.net`.** `update.php` rewrites stored texts and moves, copies and renames no file: a direct
  address of the own folder becomes an `[attach]`, a source inside `[usehtml]` the `go=file` address of its material,
  an address into the folder of another type or of the forum the `go=file` address of a published material or post
  that names the file. On a copy of the production dump of 2026-09-30, of the 721 file addresses of the stored texts
  657 became attachments, 24 `go=file` addresses, 9 point into public folders, and 31 answer 410, 21 because
  production does not hold the file and 10 by decision; the 1049 file references of the 231 pages that show them all
  answered 200. The upload folders move after the run; the steps are in `docs/NODE.md`, "The 6.3 update".
  `UPGRADING.md` keeps saying that the release installs a new site only.
- **Breaking:** `op=attach` of Node is gone; `uploads/forum/`, `uploads/account/`, `uploads/voting/`,
  `uploads/profile/` and `uploads/archive/` answer 410; `Parser::filterContent()` takes the file context `array $own`
  in place of `int $nid`; `uploads/jokes/`, `uploads/media/`, `uploads/pages/` and `uploads/node/files/temp/` left the
  shipped tree.

## 2026-10-05

### Every page is rendered live, and the ready-page cache leaves the system

A load test on the stand (nginx in front of eight PHP 8.4 FastCGI workers with opcache, 1000 guest requests per
page) showed the ready-page cache paying only on the lists of a type: 142 against 60 requests per second at a
concurrency of 16, 100 against 37 at 64. The material view (40 req/s) and the forum (47 req/s) were never stored,
a stored hit still cost about 56 ms of worker time, and a block printing a live token kept a page out of the cache
at random. The template, parser and data caches carry the expensive part of a page.

- **Gone with it:** the stored pages and their route contract, the signed dynamic markers of tokens, captcha and
  polls, the write guard around every content write, the cache generation moved by every database write, the
  deadline of a Node list and the single-flight rebuild.
- **Settings:** `cache` is an on/off switch of the parser cache; `cache_b`, `cache_l` and `cache_t` are gone; the
  `cachegc` job, now titled "Cache cleanup", removes files not rewritten for a day (`Cache::KEEP`).
- **Styles and scripts are linked one file at a time.** Joining them into bundles (`cache_css`, `cache_script`),
  inlining them into the page (`css_h`, `script_h`), compressing CSS (`css_c`) and embedding images into it as data
  URIs (`css_e`) are gone with the route `go=asset`: a browser keeps every file on its own, and one changed file no
  longer invalidates the rest.
- **Folders:** the caches live in `storage/cache/data/`; `storage/cache/pages/`, the guard journal and
  `storage/counter/cache.log` are no longer written. The changelog entries and the OAuth key sets moved from
  `storage/cache/changelog/` and `storage/cache/jwks_*.json` into the same data cache, so the cleanup job reaches
  them too.
- **One cache API:** `Cache::getFile()` names a stored entry, `Cache::deleteStale()` sweeps a directory, and
  `Cache::setHeaders()` takes the mode of the answer, `none`, `private` or `public`; the duplicate header
  `X-Powered-CMS` is gone. The query allowlist of an editor attachment lives in Node and refuses a tracking
  parameter like any other unknown key. One `getAssetList()` builds the linked styles and scripts of a theme.
- **The category map** is dropped explicitly by every writer of a category title, parent, order or icon.
- **Presentation:** the request path shows the parser cache, the compiled templates, the category map and the page
  built live.
- **Breaking:** `NodeException::BLOCKED`, the quick-edit result `blocked`, `getPageToken()`, `getPageCaptcha()`,
  `NodeQuery::getNodeDeadline()` and `setHead()` with a closure are gone; `Cache::getPath()`, `Cache::getHash()`,
  `Cache::getQueryVars()`, `Cache::setPrivateHeaders()` and `getAssetFiles()` are replaced as above.

## 2026-10-04

### One quick edit for comments, forum posts and Node materials

The quick edit of a comment and of a forum post were two separate paths over one helper, and Node had none. They
are one protocol now (`QuickEdit`, `getQuickService()`, the routes `getQuickEdit` and `updateQuickEdit`; see
`docs/ARCHITECTURE.md`, "Quick edit").

- **A refusal keeps the typed text.** Every refusal answers its own status and is told on the warning toast; the
  editor stays. An expired session answers 403 instead of an alert swapped over the text.
- **No silent overwrite.** A save carries the stamp the editor was opened with; a text changed meanwhile answers a
  conflict and asks once whether to overwrite. The repetition of a save whose answer was lost is saved again
  without a second write.
- **Cancel needs no request** and asks before it drops unsaved changes; Escape cancels, Ctrl+Enter saves.
- **The edited mark follows the save** for comments and forum posts.
- **The forum** writes under the write guard, with the topic and the right read again under the lock, so a topic
  closed or a post moved after the editor opened refuses the author; the page cache moves only after a write. The
  route `op=updatePost`, the route `op=updateComment` and `getTplAjaxTextarea()` are gone.
- **Node** offers the quick edit of the intro and the body on the public page of a type without an extension: to
  its moderator before the full editor, and to the signed-in author of a pending or published material for
  `limits.edit` seconds after creation (new key, default `600`, `0` off, on the limits tab; `update.php` adds it to
  an existing `config/node.php`). An author edit of a published material sends it back to moderation in the same
  write, unless the author publishes directly; the matrix of states gains `Published → Pending`.
- **Editor assets load once per page.** A fragment no longer carries the engine of an editor again: the client
  loader adds only what the page lacks, the init waits for the engine, and an editor swapped away is destroyed. On a
  page without an editor of its own the quick editor used to stay hidden.
- **Breaking:** `Comment::updateComment()` takes the stamp and answers a result code; `NodeException` gains
  `BLOCKED` (7, HTTP 503) for a closed write guard, which `setNodeWrite()` used to report as `STORAGE`.

## 2026-10-01

### One file installs a new site and deletes itself, and the 6.2 update leaves the release

The installer is one file, `setup.php`, and installs a new site only. The `setup/` directory, its key
`config/setup.unlock` and its update branches are gone; the update files of 4.1 to 6.2 are dropped.

- **Seven stops on the login card of the admin theme:** language, server checks, database with a probe, site,
  administrator, the run and the closing stop. Markup, styles and motion belong to the admin theme, the texts to the
  `_SETUP_*` constants of `admin/lang/*.php`.
- **The run in parts.** Configuration, the tables in groups, every seed statement and the administrator are separate
  requests, so the progress line shows how far the server really got. A part that broke its request off fails the
  run instead of running twice.
- **The first administrator** is created by the installer itself, with the ten Node types and the starter news.
  The form of `admin.php` for an empty admins table stays as the way back into the panel.
- **CSRF** is a random token in the session of the browser that runs the installation, carried to the end.
- **An installed site** — its database holds an administrator or does not answer — is refused without a write or a
  delete. The installer deletes itself only at the end of its own installation; a file that stays keeps the warning
  of the panel.
- **The language** chosen on the first stop reaches the panel: the installer sets the core cookie
  `{user_c}-language`, not the old `{user_c}-lang`.
- **The schema** moves to `storage/update/sql/`: `table.sql`, `insert.sql` and `table_update6_3.sql`.
- **The 6.2 update** moves into `update.php`, an internal tool of the maintainer that the release does not ship. A
  6.2 site is not updated by the release.
- **An administrator password** is hashed as typed in the installer, the recovery form and the admins module; the
  login still opens with a hash made from the old cut and escaped form. The recovery form carries a token.

### The migration of the old modules leaves one copy of every file, and every migrated image shows

A rehearsal on a copy of the production site found four faults of `update.php`, each fixed:

- **A stopped run.** The empty `index.html` placeholders of the old upload folders stayed in place, and Node took them
  for user files and refused the new type. Only the guard files of the release stay now.
- **Images replaced by `#`.** The converter wrote images and links as bare relative addresses like `uploads/x.png`,
  which the safe parser of Node replaces with `#`, so no migrated image showed. They now gain `./`. The
  entity-quoted attributes of old HTML are decoded first.
- **Duplicate files.** Every file went back into the closed type folder, and directly linked ones were copied into
  `uploads/archive/` as well, which left 270 duplicates. A file now has one place: the type folder holds what the
  materials use, the archive what the site links to directly. A file nothing uses stays outside the site in the
  working directory.
- **Broken outside links.** The forum, comments, private messages, newsletters, blocks, signatures and polls kept
  links into the folders Node closes. They are pointed at the archive as well, written as `./uploads/archive/...`.

### A comment of a Node material shows its attachments

A file uploaded into a comment of a material landed in the closed folder of the type and was linked there
directly, so it answered 403. A comment of a Node type now renders with the id of its material, and
`op=attach` grants the names its published comments carry, every comment not deleted for a moderator, with the
rights of the material. The preview of a fresh upload is open to whoever may upload into the type. The migration
keeps the attachments of comments and help replies in the type folder for the same route, so a private help
request keeps its files private.

The menu and footer of the lite theme link to `docs`, not to the removed `pages`.

## 2026-09-30

### The content of the removed modules moves into Node, and their old addresses follow it

`update.php` in the root carries `news`, `pages`, `faq`, `help`, `links`, `files` and `content` of a site that
ran the 6.3 update into Node types - `pages` into `docs`, `content` into a type without extension - with
categories, comments, favorites, rating balances, resources and files; the old tables stay. Only the main
administrator runs it, a stopped run continues from its manifest, and texts are converted from the trusted HTML
the old modules rendered into BB and Markdown. The new table `_node_legacy` maps every old address to its
material; the counter of `_nodes` continues above every old id, so an old address never names a new material,
and it answers 301 to the migrated material or list where it would otherwise meet a 404. A migrated material earns
no second publication award when a moderator publishes it again, since its module rewarded it once already.

The views and cards of Node show what the old modules showed: the share, author and moderator speed dials and the
anchor of the material, the chips of comments, author and downloads, favorites, rating, the category icon and the
fresh mark. The moderator dial of the site and the one of the panel list come from one function.

A new type of one of the nine replaced names now takes over the upload rule its old module left in
`config/uploads.php`, where it used to be refused as a taken name.

The category tiles of a Node list show the description of every category, as the old modules did;
`setCategories()` lost the two switches its one caller always set the same way.

### A forum post is acted on under the rights of the category it is stored in

The quick edit, the full edit, the reply, the deletion and the moderator actions of the forum took the rights of
the category the request named, so the moderator of one category could edit, delete, close or move the posts of
every other one. `getForumPlace()` now reads the category, the topic and the author of a named post from its rows,
and `checkForumRight()` is the one right over it that the handlers and the buttons of the view share: the
moderator of its category, or its signed-in author with the right of the category while the topic is open. A
reply always answers the topic of the post it names and needs the reply right, a guest no longer matches a post
written without an account, and a refused quick edit shows its alert instead of an empty body.

### A thumb vote bursts the thumb it pressed

The thumbs of the shared rating - a forum post, a profile, the rating under an avatar in the forum and the
comments, the user list - answer a counted vote with the burst of the favourite star, in the green or red their
hover promised.

### Code blocks are escaped in every highlighter mode

With `syntax = 2`, the shipped default, `[php]` and `[code=language]` handed their content to the page as
markup, so any author of a comment, a forum post or a material could store a script. The renderer moved into
the parser as `Parser::getCodeHtml()`; it decodes the entities of stored legacy text once and escapes in every
mode, drops only the editor line breaks before a line end instead of everything between the first `<br` and the
last `>`, and the highlighter mode joined the fingerprint of the parser cache.

## 2026-09-29

### The shop, order and service modules leave the package

`shop`, `clients`, `order`, `money`, `auto_links` and `whois` are no longer shipped: their module directories,
the block `auto_links`, their configuration files, upload directories, templates, language constants and tables
in `setup/sql/table.sql` are gone, and so is every hook the core kept for them — the cart route `go=2`, the
shop scope of comments, ratings and favorites, the points action `order`, the extra-field area `order`, the shop
feed, sitemap section and search source, the newsletter audiences of the three client lists, and the
administration counters of new clients, partners and whois requests. The referer log no longer links a visitor to
a link of `auto_links`, so `_referer.lid` is dropped, and the unread setting `amod` is gone from the global
configuration.

The `update6_3` branch leaves the tables and upload directories of these modules on an updated site untouched:
`table_update6_3.sql` no longer normalizes them, the ratings unit counts 6.2 shop rules as dropped, and the fields
unit converts account and forum fields only.

## 2026-09-24

### Node replaces nine content modules, and the 6.3 update carries a real 6.2 site over

`news`, `pages`, `faq`, `help`, `jokes`, `content`, `links`, `files` and `media` left the tree; one module,
Node, serves typed content in their place. A type is a row of `_node_types` plus its settings in
`config/node.php`, its extra fields in `fields.node`, its upload rule and its rating rule; `NodeQuery`
reads with the category rights of the visitor, `NodeService` writes in one transaction with a version
check, and two extensions carry the special cases: `support` for private requests of `help`, `sync` for
the external sources of `content`. Files of a type are served only through its controlled route, so a
type is switched on only when the web server refuses its upload directory. Ten shipped profiles in
`modules/node/profiles/` — the nine replacements and the new hierarchical `docs` — become active types
when the first administrator of a clean installation is created. Points, ratings and extra fields became
the shared classes `Point`, `Rating` and `Field`, and `Feed` fetches RSS and Atom for blocks and `sync`.

The `update6_3` branch of the installer closes the site, checks server version and table engines, runs
`table_update6_3.sql` and the resumable units points, ratings and fields with manifests under
`storage/backup/update/`, and leaves the marks in `config/update.php`. It now also reconciles
`config/modules.php` the way the modules screen does — the records of the removed modules go, `node`
gets the record of a clean installation — and drops the upload rules of the removed modules, which used
to refuse a Node type of the same name. It creates no types and touches no table of the removed modules.
Run on a dump of the stand taken before the data units, the fields preflight stopped on one order row it
could not map, wrote nothing, and finished after that row was corrected; the upgraded schema matches a
clean installation table for table.

The installer no longer needs `config/db.php` to exist, since the file is not shipped. MySQL 8.0 runs the
release: the columns `_users.rank` and `_groups.rank` are quoted wherever a query names them bare, because
`rank` is reserved there. The administrative material list of Node reads without the category,
relation and resource sets it never showed (`NodeQuery::setNodeSets(false)`), three statements a page.

`php tools/node-profile.php` builds a disposable database of 100000 materials in ten types with 200
categories, relations and resources, measures every route budget against `docs/NODE.md` (Performance), the p50 and p95
time and the plans of the main statements, and fails on a budget overrun or a full scan.

## 2026-09-11

### The presentation module renders the etalon home page from live figures, and the start page listens to its module

The etalon `demo/22-dashboard-layout-etalon.html` became `modules/presentation`: twelve
sections in a fixed order, each a partial of the `lite` theme with its repeated card as a
fragment, dressed by one stylesheet `assets/css/presentation.css`, moved by one plugin
`plugins/presentation/presentation.js`, and fed by PHP that hands over data and `is_*`/`has_*`
flags only. Every figure on the page has a source in the system: the request timing and
the memory come from `getLoadStats()` against the limits of the new `getLoadLimits()`, the
online figures from `getSessionCounts()`, today's visits and the day rows from
`getStatsToday()` and `getStatsDays()`, the table counts from `getTableCount()`, the commits
from the changelog module, the cache state from the cache config. Sites, brand materials,
principles and testimonials come from `config/presentation.php`, whose dictionary fields
name `_PRES_*` constants in six locales while proper names and quotes stay raw text.
The seventy-one site cards carry no address and open no window; the brand tiles open the
site lightbox through `a[data-sl-shot-open]`, the hook that replaced the dead `a.site-link`.

The server metrics left the admin monitor for `core/monitor.php`, and the scheduler samples
them once a minute through the `monitor` job at the lowest priority, so the public page
never runs `exec()` on a visit: it reads `storage/logs/monitor.json` and hides the section
while the sample is missing or older than five minutes. The guard section shows counts of
the last twenty-four hours and six static legend lines; the tail of the security logs stays
in the admin panel.

The start page now reads the `side` and `top` positions of its module like every named
route, so the block columns of the home page are set in `admin.php?name=modules`. This is a
breaking change for a site whose start module is `news` with `side = 2`: the empty value
used to mean both columns, and the left one disappears until the setting says `0`. A module
that prints its own `h1` raises `has_own_title` and `layouts/home.html` drops the site name
heading, so the page keeps one `h1` on either route.

The micro font step reads 11 px in both themes and the contract ladder follows: the
monospace labels of the page were unreadable at 10, and every page reading `--sl-font-micro`
grew by the same pixel. `setSpyRail()` gained the pixel mode of the presentation rail
(`data-sl-spy="px"`, `--sl-d-rail`), and the screenshot rig captures the presentation page
on its own, masks the shuffled site strip, bounds its wait for lazy images and pauses SMIL
animations before a shot.

## 2026-08-07

### One window and one icon insert an image, and a denied upload no longer means an unbounded one

The image dialog and the file catalogue are one window behind one toolbar icon. The
vendor image popup is removed from the toolbar, and what replaces it is our own markup:
the image address with its description, the file picker with its drag target, the three
insert modes, the stored-file list and the limits block, all siblings in one window
instead of two windows held together by measured geometry. Nothing in the editor reaches
into a popup it does not own any more, and the link dialog and the emoji window are
untouched.

The icon is there for every visitor. Inserting an image by its address touches no file
and no server, so it is available to everyone, always — and a visitor allowed nothing
beyond that opens the same window and finds the address field in it, which is an honest
answer where a missing icon would not be one.

A visitor who may not upload no longer gets a *worse* path than one who may. Dragging an
image onto the editor, pasting one from the clipboard and picking one in the window all
go through one bounded path from this release on: at most `Parser::EMBEDMAX` and only a
type the parser will draw. Until now the editor left that event to the vendor default
whenever uploading was denied, and that default base64-encoded any picked file straight
into the body — no extension check, no size check, no dimension check — so a
three-megabyte photo became four megabytes of base64 in the row and then did not display
at all.

### Whether a field may hold an embedded image is a property of the field

Every editor call site now names where its text is stored, and one table turns that name
into the room the storage has. A field wide enough for a whole embedded image offers the
embed mode; a summary, a signature or a link description does not, and offers the
address field instead. That is not a restriction on graphics: a linked image is accepted
in all of them, and it is the better delivery there anyway, because a list page draws the
summary of twenty rows and the browser fetches a linked image once instead of re-sending
a base64 copy twenty times.

A call site that names no storage renders a working editor with the room of `TEXT` and no
embed mode, so a forgotten one costs its author a button and never costs them a post.

The five image types the parser will draw have one definition, `Parser::EMBEDIMG`. The
render bound, the upload adapter and the editor read it from there, and a test fails if
they disagree.

### No stored text is longer than the column that holds it

One guard measures a finished text against the room its field declares, before the query
runs, and refuses with a message instead of letting the database answer `ERROR 1406` and
the author lose the post. It measures bytes and not characters, because bytes are what a
column bounds — in utf8mb4 a Cyrillic letter costs two of them, so a `TEXT` column runs
out at about 32700 Russian characters rather than 65535. That holds for plain prose as
much as for an embedded image: 70 KB of text in a news article was refused by the
database before this release and is refused with a message now.

The same guard measures what a text embeds, so the field rule is enforced and not merely
offered. A field that may not hold a data URI refuses one at any size; a field that may
refuses one over `Parser::EMBEDMAX` or of a type the parser will not draw. The editor
still refuses first, so an author is told at the moment they act, and a request that
never rendered an editor is told the same thing.

The one writer into a guarded column that has no author to tell is the content module: an
item with a feed address rewrites its body from whatever the far end served. A feed that
does not fit leaves the stored body untouched and writes the reason to the site log, since
a stale item is a better answer to a visitor than a lost one.

### The editor file panel is answered by the settings, not by the role

A guest allowed to upload now sees a file list. Until this release the editor listing
route refused every visitor who was not logged in, whatever
`admin.php?name=uploads&op=config` said, so a guest could upload a file under
`guestupload` and never see it again — not even their own, not even once. That rule is
gone: whether a list is answered at all is decided by the module upload settings and by
the module moderator, which is the one role standing above them.

A guest is shown the files of their own session and no other guest's, which rests on the
owner token below. The next session is a new session, and the files of the old one are no
longer listed.

The listing limit is chosen from three values instead of two. A moderator is bounded by
`moderfiles`, a member by `userfiles` and a guest by `guestfiles` — the field the release
below adds, which this one gives something to bound. Which of the three applies is the
only role question left on the route; what each of them is worth is a setting.

### A stored file belongs to a token, so one guest is no longer every guest

The owner segment of a stored upload name — the `-42` in `news-a1b2c3d4e5-42.png` — is an
alphanumeric token instead of a number. A member still owns their files by their user id
and a privileged upload still carries no owner segment at all, but a guest now owns theirs
by a token derived from the session rather than by the shared `0` every guest carried. Two
guests are therefore two owners, which is what a per-guest file list has to rest on.

The token is derived from the session and is never the session id itself, because the
segment ends up in a public file name and must authenticate nothing when it is read off a
URL. It lasts as long as the session: the next session is a new one, and the files of the
old one are no longer listed.

Files stored under the old pattern keep resolving, because digits are still a valid token,
and nothing already in `uploads/` is renamed or moved.

The per-guest file list above rests on this token: without it every guest would carry the
same owner and would see the uploads of every other guest.

### A link description is no longer one sentence, and the guest file list gets its own limit

`{prefix}_auto_links.intro` moves from `VARCHAR(255)` to `TEXT`. Those 255 bytes are
about 127 Cyrillic characters in utf8mb4 — one sentence for a field the site asks a
visitor to describe a whole site in. It joins the summary class rather than the body
class and stays `TEXT`: a link description is drawn once per row of a link list, which
is exactly the shape that must not carry an embedded image. It is the last column of
the schema work below that had not moved yet, and with it every column a rich editor
writes into is `TEXT` or `MEDIUMTEXT` and no `VARCHAR` sits behind an editor.

**Deployment:** a 6.2 upgrade and a fresh install carry the column already. No data is
lost or rewritten — the column only gets wider, and no stored description changes.

The upload rule of a module gains a thirteenth field, `guestfiles`, with its own input
on `admin.php?name=uploads&op=config` beside the two upload switches. A rule stored
before this release is one field short and keeps working untouched: the missing position
answers the user limit rather than zero, because zero means *no limit* to the reader and
would hand an unbounded list to the one role that never had one. The first save
normalises the rule to the full field order without changing a value it already carried,
and every save after that reproduces it.

The limit bounds the guest file list described above.

## 2026-08-05

### Private messages store source, and both fields are rendered safe

A private message is the source its author wrote from this release on. The body is
rendered by the parser with `safe = true`, the title is plain text escaped where a
template prints it, and no stored value is trusted HTML any more. **This release follows the state-model release below it and
must not be deployed before that one is live**, because it reads the columns that
release adds.

> **Superseded on 2026-08-06 by the content contract.** This entry originally
> shipped a `format` column on `{prefix}_privat` and a mandatory
> `tools/privat-migrate.php` run that rewrote stored bodies in place. Neither
> exists any more: the column is gone from all three SQL channels, the tool is
> deleted, and no text migration is part of any release. What survives is the part
> below — a message is stored as source and rendered safe — and it needs no
> conversion pass to be true.

A body is read through one contract. `plain` and the editors are input interfaces,
not storage formats, and nothing in the rendering branches on the editor an author
happened to type in. That is why no column names a syntax and why there is nothing
to classify: the same stored bytes render the same way whichever editor wrote them.

No deployment step is needed for this beyond the runtime code itself. The schema
work belongs to the state-model entry below, which stays exactly as it was.

What changes for the people using the site:

- A message is stored as it was written and escaped when it is read. Markup a
  sender types is text on the recipient's screen instead of live HTML, while
  Markdown, the bracket tags and the smilies still render.
- The editor an author writes in is an input interface and nothing more. An HTML
  editor is not a trust grant either: its markup is stored as source and escaped
  like any other, and the reading side never asks which editor produced a message.
- The subject line is plain text now. It carries no markup, it is stored decoded,
  and the template escapes it where it prints it — in the mailbox list, in its
  attribute and in the administrator panel alike. The panel used to decode it back
  on read to compensate for a writer that no longer exists.
- An administrator reads a message body through the same safe renderer its
  recipient does. Access to private-message contents in the `privat` section is a
  deliberate system policy, super-administrator only as before, and no raw stored
  body reaches a template on that path.
- One limitation the state model cannot repair either: a message
  either side deleted while both sides still shared one row is gone from the
  database and cannot be reconstructed for the other participant. That is recorded
  rather than papered over with a placeholder message.

### Private messages: four independent states instead of one shared column

The private-message subsystem is now one class, `Privat`, and one message carries
four state columns instead of the single `status` it shared between both
participants. **The schema section and the runtime code of this release are
deployed together.** An installation that applies the section while still running
code that reads `status` answers an SQL error on every private-message page until
the code follows it, and code deployed before the section does the same in the
other direction.

Which file an installation needs depends on where it comes from, and it is
exactly one of the two:

| Coming from | File | How |
|---|---|---|
| a new installation | `setup/sql/table.sql` | the installer, nothing to do |
| 6.2 | `setup/sql/table_update6_3.sql` | the installer, per the section below |

What the upgrade does to `{prefix}_privat`: it adds `saved`, `delin` and `delout`,
carries the saved messages over from `status` before that column is renamed to
`viewed`, forces `viewed` onto `TINYINT UNSIGNED NOT NULL DEFAULT 0` — the old
declaration was `BOOLEAN` while the code stored `2` — and replaces the three
single-column keys `uidin`, `uidout` and `status` with the composites
`in_box`, `in_new`, `out_box`, `out_new` and `flood`. It deletes no row and no
message, and the row count it starts from is the row count it ends on. It is safe
to run twice, and safe to run again after a crash: a re-run reads the shape the
table is really in and finishes only what is missing. Both files end on the same
table definition, byte for byte.

Building five indexes rewrites the table, and InnoDB holds the rows while it
does. On a large private-message table that is a maintenance window rather than a
page reload — the same rule the comment upgrade below already carries.

One documented consequence of the conversion: a message that was saved under the
old model becomes `viewed = 1`, because `status = 2` carried no read bit of its
own. Saving required opening the message, so read is the correct assumption.

What changes for the people using the site:

- A recipient deleting a message no longer removes it from the sender's outbox,
  and the reverse. Until now one delete destroyed the single shared row.
- A message the recipient saved stays visible in the sender's outbox, where it
  used to vanish the moment it was saved.
- A sender may delete an outgoing message the recipient has already read. It used
  to stay in the outbox forever.
- Saving no longer discards the read state, and a saved message no longer becomes
  undeletable for the sender.
- Read and unread are two actions now, not one transition, and inbox, saved and
  outbox carry bulk read, unread, save and delete.
- A send is refused when the saved folder of the recipient is full and not only
  when their inbox is, so the mailbox a message cannot fit into is the whole
  mailbox. Both quotas and the send interval are rechecked inside the write
  transaction, under a lock on both accounts, so two simultaneous sends can no
  longer both take the last free place.
- The notification mail reads the `psmail` preference of the recipient, which is
  the setting the account page has always offered and which had no effect until
  now — the code read the forum preference `fsmail` instead. Its link carries the
  id of the message that was really stored.
- Deleting an account now cleans its mailboxes in the same transaction that
  deletes the account, on both paths that delete a user row. The counterpart keeps
  a readable copy with the gone account rendered as an anonymous sender.
- The second administrator delete route, a GET carrying its token in the address,
  is gone. One POST route remains, super-administrator only as before.

## 2026-07-29

### Upgrade 6.2 → 6.3 must go through the installer, not through the SQL page

`setup/sql/table_update6_3.sql` is **not the whole upgrade**. Five steps of the 6.2 → 6.3
migration live in PHP, in the `update6_3` branch of `setup/index.php`, and pasting the
SQL file into **Database → Inquiry** performs none of them. The schema will look
correct — an upgrade of a real 6.2 database was compared against a fresh
`setup/sql/table.sql` install, table by table: 38 tables, 494 columns, 171 indexes,
engine and collation, zero differences, and the file is idempotent — but the data
around it will not be.

What the SQL file alone does **not** do:

1. **Administrator module permissions stay numeric and stop matching.** In 6.2 the
   `modules` column of `{prefix}_admins` held numeric ids from the `{prefix}_modules`
   table; 6.3 stores module names. `getAdminModuleNames()` (`core/system.php:998`)
   splits the column and does not translate ids, so every administrator who is not a
   super administrator silently loses **all** module permissions, comment moderation
   included. Only the installer rewrites the column.
2. **`config/modules.php` is not rebuilt.** 6.3 moves the module registry out of the
   `{prefix}_modules` table into that config file. The installer scans
   `admin/modules/*.php` and `modules/*`, folds in the old table and the existing
   file, and writes it.
3. **Pending newsletter recipients are lost.** The upgrade drops
   `{prefix}_newsletter.mails` (line 1696 of the SQL file), and it is the installer
   that reads those addresses first and writes them into the new mail queue
   afterwards. The SQL file does not carry them anywhere. Check before a manual run:
   `SELECT id, title, mails FROM {prefix}_newsletter WHERE mails IS NOT NULL AND mails != ''`
   — if that is empty, nothing is lost.
4. **The mail queue is never drained.** 6.3 stores outgoing mail instead of sending it
   inside the request, and the `maildrain` scheduler job is what delivers it. The
   installer seeds that job into `config/scheduler.php`; without it **all outgoing
   mail stops**.
5. **`config/newsletter.php` is not created**, so the campaign limits fall back to
   nothing.

The upgrade runs through the installer and nowhere else. The SQL page parses and
executes what it is given, but it performs none of the five PHP steps above, so a
schema pasted into it leaves the data around it wrong.

### Upgrade notes for the comment subsystem

Read this before running `setup/sql/table_update6_3.sql` on an installation with a
large comment table. **Take a dump of `{prefix}_comment` first and rehearse the
restore.**

- The upgrade adds five columns to `{prefix}_comment` (`pid`, `shown`, `edited`,
  `deleted`, `reqkey`), places `pid` directly behind `id` and `shown` behind
  `status`, fills `shown` from `time` for every published comment, makes `time` required, stores `ip`
  under a binary ascii collation, creates the index set the real list, count and
  thread predicates are read through, and drops the keys those supersede (`cid`,
  `modul_status`). No column named `format`, `iphash` or `path` is created, no
  comment rate table is built, and no idempotency key is minted for an existing
  row: a comment written before this release was never replayed and needs none.
- Two guards stop the run instead of guessing, and both stop it before anything
  else has changed: a `reqkey` found as hex text, which means the table was carried
  through a transitional release, and a `NULL` in `time`.
- **This is not instant on a large table.** Every index build rewrites the table,
  and InnoDB holds the rows while it does. It took under a second against 7358
  rows; on a table with orders of magnitude more it is a maintenance window, not a
  page reload. Close the site for it rather than letting a visitor discover it
  during a lock.
- **No text migration is needed and none is shipped.** A comment body is the
  source its author wrote and is rendered through one contract; nothing branches on
  an editor and no column names a storage format.
- The upgrade is idempotent: a second run changes neither schema nor data.
- Deleting a user no longer orphans their comments. The rows stay and lose only
  the reference to the account, so discussions and reply branches survive.
- **Repair the comment counters after the upgrade.** The `comments` column of the
  eight target tables is denormalised, and until 6.3 the counter could be moved for
  the wrong target by a request-supplied module name. The write path is fixed, the
  residue is not: on the reference installation 23 of 885 targets disagreed with
  their live count. Two ways in, both writing the same numbers:
  - `php tools/comment-recount.php report` reads only and prints every target that
    disagrees, `fix` writes the live count back;
  - the first tab of the comments section, which reports what is left and repairs it
    on a click.
  Both are safe to repeat: only rows that disagree are written, so a second
  run reports zero affected rows. None of them touches the comment table, and no
  user points are recalculated. Beyond them the counter maintains itself: every
  comment write recomputes the target it touched instead of nudging it by one.
- New setting `comments.reps` (default 5): how many replies a page shows under one
  comment before it offers to load the rest. It bounds what one long discussion can
  put in front of a reader; the remaining replies stay reachable both through the
  control and, without JavaScript, through the `&all=` link it carries.

### Fixed in the upgrade file itself

- `{prefix}_users`: the file modified `network` and then dropped it further down,
  so a re-run failed and discarded the type normalisation of thirteen other
  columns with it. The upgrade is idempotent for that table again.
- `{prefix}_admins.editor` was declared `BOOLEAN` while `setup/sql/table.sql`
  defines `VARCHAR(32) NOT NULL DEFAULT 'plain'`. It failed on any installation
  whose administrators carry an editor name, and would have destroyed those names
  had it passed.
- `{prefix}_users.points` was declared twice with two different definitions, and
  the second one undid the first. `table.sql` now agrees at `NOT NULL DEFAULT 0`.
- `SchemaUpdateValidationTest` compares column definitions between the fresh
  schema and the upgrade, and rejects a column declared twice with two
  definitions, so this class of defect fails a test instead of an installation.

## 2026-07-10
- Added the route-aware SEO head contract with safe HTML/JSON serialization, canonical and robots policy, Open Graph, and typed JSON-LD.
- Normalized frontend `H1-H3` ownership, card and voting contexts, comments, related content, tables, landmarks, and category breadcrumbs.
- Added scoped Markdown heading offsets for article, card, comment, block, and forum contexts.
- Added `SeoSemanticsValidationTest` and the executable `tools/seo-audit.php` HTTP contract audit.
- Corrected the English and Ukrainian Open Graph locale identifiers. `hreflang` remains disabled until languages have stable public URLs.

## 2026-05-20
- Responsive baseline closed for `lite` and `admin`.
- `lite` mobile/tablet layout fixed without changing the desktop visual style.
- Top menu, dropdowns, table wrappers, forum posts, comments, cards, media, and admin forms were verified after the CSS updates.
- Browser verification was completed with Playwright and Chromium.
- Authenticated admin pages were verified after login.
- Remaining component-specific backlog items are non-blocking: CodeMirror editor surface on the admin template page, statistic chart fixed-width images, and monitor widget internal widths.
