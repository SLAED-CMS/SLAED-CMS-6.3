# Node Head 2026

Work plan for the list of a Node type: its header — the approved face of the stand page `public/demo/nh-13-calm.html`
carried into the lite theme — the views a type gives its list, and the RSS channel, the second face of the same list.

Status: planned, nothing implemented. The order of the batches is kept in `docs/ROADMAP-2026.md`. Update this line
as batches land.

No line numbers anywhere in this document on purpose: every reference names the function, the file or the constant
it points at, and that name is what to search for.

## Decisions

Taken by the owner on 2026-10-07; settled, not to be reopened by a batch.

- **The face is `public/demo/nh-13-calm.html` as it stands on 2026-10-07,** two rows and no third. Row one: the title
  and the count chip on the left; the view switch, the search field and the RSS button on the right. Row two: the
  pills "Категории", "Алфавит" and the sort pill, the direction pill right after it, the reset chip; "Добавить" at
  the right edge.
- **One of each.** One number of materials (the count chip; it reads "19 из 97 материалов" when the list is
  narrowed), one way to reset ("Сбросить"; a pill has no cross of its own), one place to flip the direction (the
  direction pill).
- **The windows open under the bar, one at a time,** and are not remembered across a reload.
- **The sort window is all chips.** Group "Сортировка": the sorts; a click picks one and closes the window, a click on
  the chosen one keeps its direction. Group "Показать": "С прошлого визита" and "В избранном", each a toggle that
  keeps the window open, drawn only for a signed-in visitor and only when its number is not zero; the group is not
  drawn when it has no chip. While one is on, the sort pill is tinted and carries its icon after the name.
- **The direction pill** is a pill with text like the others ("Убывание" / "Возрастание", the full wording in its
  hint); the sort pill wears a neutral icon, so the direction is shown once.
- **The bar sticks** when the list scrolls: the title, the pills, the search field and "Наверх" on a wide screen; on a
  narrow one the pills, a lens that opens the search field over the bar, and "Наверх". Closing the lens drops what
  was typed. The stuck bar hides the count chip and the view switch; the direction pill shows its icon only.
- **Views by type.** A type sets which views its list offers, its own card first: news cards, compact and tiles;
  documentation list and rail; FAQ accordion and list. The visitor sees only the views of the type, and the switch
  only when there are two or more; one click switches.
- **Month heads.** A list ordered by date is headed month by month with the count of the month, in the cards and the
  compact view.
- **The feed stays** and is the source readers, bots and Search Console use; the RSS button stays in row one.
- **The feed is fixed in five points:** the channel title names the site, the type and the category; the channel link
  points at the list it mirrors; `lastBuildDate` is the newest item; the route answers 304 to a reader that already
  holds the current channel; the copyright names the site, not the engine.
- **The list changes without a reload.** The links of the header and the pager swap the header and the list through
  htmx and push the address; without a script they stay plain links. The page stays at the header.
- **"Показать ещё"** under the list appends the next page of cards; the page numbers stay.
- **Search hints:** while typing, up to five titles of the type under the field, each a link straight to the
  material, served by a light route of its own.
- **Existing parts first:** no new function, class or template where one in the tree does the job.

Rejected, with the reason, so it is not proposed again: Atom or JSON Feed in place of RSS 2.0 (every reader reads RSS,
a switch buys nothing); a direction pill inside the sort window and a flip by a second click on the chosen sort (two
places for one action); a cross on every pill beside "Сбросить" (two ways to reset); a third row of quick chips and
the period chips "За неделю · За месяц · За год" (the header grows by a row, and on a site that publishes once a
month they mostly read zero); the shown range "Показано 1–N" and "Всего" under the list (the count chip already says
it); actions of a moderator in the list — pick boxes, a bulk bar, a menu on the card (the panel does that); a select
or a window for the view (two clicks).

## What exists today

- `setNodeList()` in `modules/node/index.php` renders `partials/node/list.html` (the media type
  `partials/node/media/list.html`, items in `sl-node-tiles`) with `navi_html`, `intro`, `cats_html`, `letters_html`,
  `items_html`, `pager_html`, `empty_alert`. Every item is the card of its type: `fragments/node/card.html`, or
  `docs/card.html` (`sl-node-toc`), `faq/card.html` (`<details class="sl-node-faq">`), `files/card.html`,
  `media/card.html` (`sl-node-tile`, a play mark on the cover), `support/card.html`.
- A card already carries `fresh_html` — `getTplNewGraphic()` in `core/helpers.php`, a mark by the age of the material
  up to thirty days, not by the visitor's last visit — and `fav_html`, the favourite button `getFavoriteButton()`.
- `navi_html` is `getNodeNavi()` → `getModuleNavi()` in `core/helpers.php` → `partials/navi.html`: `title`,
  `nav_label`, `is_heading`, `home_link`, `best_link`, `pop_link`, `list_link` (empty for Node), `add_link` (by
  `checkNodeFlow()`), `cat_link`. `cat_link` is `fragments/link.html` with `is_category_toggle`, a toggle of
  `sl_nav_cats` remembered per address.
- Not in the header today: the count, the sorts "Обновлённые" and "По названию", the direction, the search field, the
  RSS link, any view. The count reaches only the pager (`getTplPagerView()`).
- The list reads `cat`, `num`, `let`, `order` (whitelist `published, updated, title, views, rating`, each beyond
  `published` also in `settings['list']['orders']`) and `dir` (`asc`/`desc`; `title` defaults to `asc`); an order and
  direction equal to the defaults answer 301 to the clean address. `word` is not read by the list;
  `NodeQuery::setNodeSearch()` serves only `modules/search/index.php`.
- `getLetterNavi()` in `core/system.php` draws digits, `_ALPHABET` and A–Z, every letter a link, no count and no empty
  state. `setCategories()` draws `fragments/category-row.html` tiles inside `partials/categories.html`; `count` is
  always empty. `NodeQuery::getNodeCategoryCount()` answers only a manager or moderator.
- `{prefix}_users.lastvis` is moved to now by `updateSessionTrack()` in `core/system.php` once a minute while the
  visitor is active, so the previous visit is gone by the second request. `{prefix}_favorites` holds `uid`, `fid`,
  `modul`.
- Parts the views can stand on: `fragments/related-item.html` and `partials/related.html` (`sl-related-*`, no caller
  today, the layout never finished); the mode rail `fragments/mode-switch.html` (`sl-mode-rail`, three cells, the
  face of the dark top band); the split of the private messages (`sl-pmf-split`, `sl-pmf-aside`, `sl-pmf-view`); the
  tabs of `slaed.js` (`data-sl-tabs-init`, `data-sl-tab-link`, `data-sl-tab-panel`); the day head `sl-pmf-day`.
- `public/plugins/system/slaed.js`: `setToggleBlocks()` and its helpers; `data-sl-toggle-scope` takes `path`, `none`
  (not remembered, added with the stand on 2026-10-07) or nothing (remembered for the site). No grouping of toggles,
  no sticky header, no lens.
- `data-nh-*` lives only in `public/demo/nh-*.html` and `public/demo/assets/demo.js`; the stand filters the cards of
  one page in the browser, which a real list does not do.
- The feed: `public/index.php` answers `go=rss` with `Cache::setHeaders()` in no-store mode and
  `getRssChannel()` from `core/user.php`. The channel title is `$conf['sitename']`, the link `$conf['homeurl']`,
  `lastBuildDate` the time of the request, the copyright "SLAED CMS"; `cat` is passed to `setNodeCategory()`
  without `NodeQuery::checkNodeCategory()`, so a hidden category gives an empty channel. `getRssFeeds()` in
  `core/system.php` lists the types with `integrations.rss`.
- `Cache::setHeaders()` and `Cache::checkNotModified()` in `core/classes/cache.php` send the validators and answer
  304 by `If-None-Match`, else `If-Modified-Since`; their one caller is `getFileStream()` in `core/stream.php`.
- htmx is `public/plugins/htmx/htmx.min.js`, loaded through `core/system.php`. A request with `HX-Request` is
  answered with the part alone by the help screen in `core/helpers.php`, by `core/user.php` and by `Editor`; no list
  swaps through htmx today.
- Tests: `NodeRouteTest::aFeedExistsOnlyWhereATypeHasOne()` with `getRouteSeo()`, `getRouteSeoData()` and
  `getRouteInteg()` in `tests/Support/route_probe.php`.

## Design

### Links first

Every control of the header is a link or a form the server answers; the script only opens windows, sticks the bar,
opens the lens and swaps the list. Nothing of the stand's in-page state (`data-nh-*`, `setNodeHeadDraw()`) is carried.

- A sort is a link with `order` and its default `dir`; the chosen sort links to itself with its current `dir`. The
  direction pill links to the chosen order with the other `dir`. The defaults keep the 301 to the clean address.
- The sorts offered are `published` and `settings['list']['orders']`, in the order of `ORDERS`.
- "С прошлого визита" and "В избранном" are links with a parameter of their own, read by `setNodeList()` like `let`;
  the reset chip is the clean list.
- The count chip is the total the pager already counts; a narrowed list adds the total of the type.
- The RSS button is drawn when the type is in `getRssFeeds()` and carries the category of the list.
- "Добавить" keeps the condition of `add_link`.

### Personal filters

- **The previous visit** is the `lastvis` the session finds when it starts, kept in the session by
  `updateSessionTrack()` before it moves the column; "С прошлого визита" lists the materials published after it.
- **The favourites** are the rows of `{prefix}_favorites` of the visitor for the type.
- Both numbers are counted only for a signed-in visitor, in the query of the list or one grouped query beside it.

### Views

- The view is a list setting of the type: the views it offers, the first its default. A view is a card fragment the
  tree has: cards and compact `fragments/node/card.html`; list the `sl-node-toc` row with the thumbnail and the date
  of `fragments/related-item.html`; accordion `faq/card.html`; tiles `media/card.html` in `sl-node-tiles`, the play
  mark only for media; rail the `sl-node-toc` rows in the split of the private messages with the tabs of `slaed.js`,
  the whole type and no pages.
- The view switch is `fragments/mode-switch.html` generalised to a count of cells and a face for the page; the server
  draws only the cells of the type. The choice of the visitor rides in the address by decision 7.
- The month heads are drawn by the list for an order by date, in the cards and the compact view.

### Script

`slaed.js` takes what the stand did in `demo.js`, as attributes on the markup and no Node knowledge, each one first
looked for in what `slaed.js` already has — the toggles of `setToggleBlocks()`, the observer of `setSectionSpy()` —
and added only where nothing there carries it:

- a group of toggles in which opening one closes the others;
- a sticky mark that sets an attribute on the bar while it is stuck;
- a lens that opens a field over a stuck bar, closes on Esc and on the lens, and empties the field on closing.

### Live list

- One template part holds the header and the list. A request with `HX-Request` gets that part alone, any other the
  whole page, at the same address, so the address bar, a reload and a shared link agree.
- The links of the header, the view switch and the pager carry `hx-get` to their own `href`, the part as target and
  `hx-push-url`; the part carries the `<title>` of the list, which htmx applies. The open windows close with the swap,
  as they would with a reload.
- After a swap the bar is scrolled into view when it is above the screen; back and forward restore the list.
- "Показать ещё" is the link to the next page under the list: `hx-get`, the cards of the answer appended to the card
  container, the button replaced by the next one, the pager redrawn; by decision 5 the address follows or not.

### Search hints

- A read-only GET route answers the titles of one type that match a word: `NodeQuery::setNodeSearch()` with the read
  rights of the visitor, at most five rows, no count; a word shorter than the minimum of decision 6 gets nothing.
- The field asks it through `hx-get` on input with a delay; the hints stand under the field, arrows move through them,
  Enter opens the chosen one, Esc closes them. Without a script the field submits to the search module as before.

### Theme

The stand stands on parts of the tree, and the header keeps them:

| Part of the header | What it reuses |
| --- | --- |
| windows | `data-sl-toggle` of `slaed.js`, `data-sl-toggle-scope="none"` |
| category window | `setCategories()`, `sl-cat-grid`, `sl-cat-tile` |
| letter window | `getLetterNavi()`, `sl-letter` |
| pills, chosen state | `sl-but`, `sl-is-active` |
| count, reset, the chips of the sort window | `sl-chip`, `sl-chip-neutral`, `sl-chip-info` |
| search | `sl-search-form` |
| direction | the `bi-sort-down` / `bi-sort-up` icons |
| view switch | `sl-mode-rail` |
| month heads | `sl-pmf-day` |
| views | the cards of the types, `sl-related-*`, `sl-pmf-split` |

What the stand added only for layout — the two rows of the bar, the stuck state, the list row and the rail — becomes
the few `sl-*` rules that layout needs, named with the owner in batch 0. Classes taken from the private messages
(`sl-pmf-day`, `sl-pmf-split`) are renamed to a common name once they serve two places, not copied. Every visible
string is a constant in the six locales of `lang/` or `modules/node/lang/`.

### Feed

- **Title** `sitename — module name [— category title]`, the category from `getCategoryMap()` through `getConst()`,
  named only after `NodeQuery::checkNodeCategory()` passes; a category the visitor may not read answers 404, as the
  list does.
- **Link** the address of the list the channel mirrors, built as the list builds its canonical address.
- **`lastBuildDate`** the newest `published` of the items; left out of an empty channel.
- **Conditional GET** `Cache::setHeaders()` with revalidation in place of no-store; the validator is computed from the
  item list — ids, `updated`, the category title, `num` — before `NodeView` renders a single item, and
  `Cache::checkNotModified()` ends the request with 304.
- **Copyright** the site name.

## Open decisions

Put to the owner in batch 0; the answers are written here before any code.

1. **Where the header lives.** `partials/navi.html` serves `getModuleNavi()` for every module: rework it for all, or
   give the Node list its own header partial and leave the navigation of the other modules as it is. Batch 0 lists
   the callers first.
2. **Search.** The field submits to the search module scoped to the type (the stand's form does) with the hints under
   it — and nothing more, or also filters the cards of the open page as the stand does.
3. **Counts.** The stand shows counts on the category tiles and per letter, and greys letters without materials:
   one grouped query each per list view, cached with the list, or no counts and every letter a link as today.
4. **The stand pages.** Which of `public/demo/nh-01…nh-13` stay after batch 8.
5. **"Показать ещё" and the address.** The address takes the `num` of the last page appended, so a reload shows that
   page, or keeps the page the visitor started from.
6. **The hint route.** Its place (an `op` of the search module or of the Node list), the minimum length of the word
   (two or three letters) and the delay of the input.
7. **The view of the visitor.** A parameter of the address (shareable, one more variant of every list address for
   the cache and the canonical link) or a cookie per type through `setCookies()` (one address, the server reads it).

## Batches

### Batch 0 — *decision*: questions and baseline

The open decisions to the owner, the answers into this file; the callers of `getModuleNavi()` listed;
`npm run ui:before`.

### Batch 1 — the feed

The five points of "Feed" in `getRssChannel()` and the `go=rss` branch of `public/index.php`. Tests through
`route_probe.php`: the title parts, the link, `lastBuildDate` equal to the newest item, 304 on a repeated request with
the validator, 404 for a hidden category. `NodeRouteTest` by `--filter`.

### Batch 2 — the script

Group, sticky mark and lens in `slaed.js`, as "Script" names them; the stand switched onto them so it keeps working.

### Batch 3 — the data of the header

`setNodeList()` and the header builder pass the count and, for a narrowed list, the total of the type, the sort and
direction links, the reset link, the RSS link, by decision 3 the counts, and "Personal filters": the previous visit
kept by `updateSessionTrack()`, the two parameters, the two numbers for a signed-in visitor. Route tests for the new
links, including the 301 of the defaults, and that a guest gets neither personal chip.

### Batch 4 — template, theme, strings

The header markup by decision 1, the sort window of chips, the month heads, the `sl-*` classes in `theme.css`, the
constants in six locales, the media list too. `npm run ui:gates`, `npm run ui:after`, a browser check of the windows,
the sticky bar and the lens at desktop and phone width.

### Batch 5 — views

"Views" in full: the list setting of the type with its validation and its field in the type editor of the panel, the
card fragment per view, `related-item` laid out, the mode rail generalised, the rail on the split and the tabs, the
choice by decision 7. Route tests that a type answers only its views and falls back to its first; a browser check of
every view of news, documentation and FAQ.

### Batch 6 — live list and "Показать ещё"

"Live list" in full: the part, the `HX-Request` answer, the htmx attributes, the title, "Показать ещё" by decision 5.
A route test that `HX-Request` gets the part without the page frame and a plain request the whole page; a browser
check of a swap, back and forward, a reload of a pushed address and the list with the script switched off.

### Batch 7 — search hints

"Search hints" by decision 6. Route tests: at most five rows, nothing under the minimum length, no title of a hidden
category or of an unpublished material; a browser check of the keyboard.

### Batch 8 — reference

The header, the views and the feed described in `docs/NODE.md` and `docs/TEMPLATES.md`; the stand pages by decision
4; this file deleted.

## Out of scope

Atom, JSON Feed, WebSub, a Telegram bridge, push and mail subscriptions; pretty feed addresses, which belong to the
work on addresses as a whole; actions of a moderator in the public list.
