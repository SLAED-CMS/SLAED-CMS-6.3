# Private Messages 2026

Work plan for the two-column redesign of the private messages page.

Status: groundwork implemented, batches 1–7 not implemented. Run the batches in
order; batch 7 exists only if the measurement of batch 4 asks for it, and it is
a schema change that needs its own approval. Update this line as batches land.

The approved layout is `demo/private-messages-final-2026.html` — it is the
specification for markup, class names and states, and its section 7 is the
contract with the backend. `demo/private-messages-index.html` records the eight
labs and why each of them lost; read it only when a decision looks arbitrary.

No line numbers anywhere in this document on purpose: every reference names the
function, the file or the constant it points at, and that name is what to search
for.

## Problem

`op=privat` renders four htmx tabs — `prmessin`, `prmessou`, `prmesssa`,
`prmessfo` — built by `privat()` in `modules/account/index.php` and filled by
`getPrivateMessageView()`. Every action redraws a whole tab: opening a message
replaces the list with the detail view, so the reader loses the list, the scroll
position and the checkbox selection, and the Toast UI editor is created again on
every message. The list itself is a `content-list` table of five columns with no
search, no filter and no sort, and the mailbox quota is announced by two text
alerts, one of which fires from half capacity.

## What already holds

Written down so the next reader does not re-derive it.

- **All table access goes through `Privat`.** `getMessageCount()`,
  `getUnreadCount()`, `getUnreadOutCount()`, `getMessageList()`,
  `getRecentList()`, `getMessageView()`, `getAdminList()`, `addMessage()`,
  `setMessageRead()`, `setMessageSaved()`, `deleteMessage()`, `deleteUser()`,
  `deleteAdminMessage()`. Mailboxes are the `PrivatBox` enum, and the box
  predicate lives in `getBoxWhere()`/`getSideWhere()`. No route builds SQL of
  its own, and none may start.
- **The list query does not carry the snippet.** `FIELDS` is
  `id, uidin, uidout, title, time, viewed, saved, delin, delout`, so
  `getMessageList()` answers everything the row needs except the preview text:
  `body` is `MEDIUMTEXT` and is read only by `getMessageView()` and
  `getAdminList()`. The dense row of the demo shows a snippet, and that column
  has to be added to the list query as a cut, never as a whole body per row.
- **Four routes, not more.** `getPrivateMessageView()` renders a mailbox
  (`typ` 1–3) or the compose form (`typ` 4); `setPrivateMessageRead()` opens one
  message and is a POST because opening is what marks it read;
  `updatePrivatBox()` applies one action to one or many ids and already limits
  the action set per mailbox; `addPrivateMessage()` validates and sends.
- **The POST gate is central and closed.** `index.php` refuses
  `setPrivateMessageRead`, `updatePrivatBox` and `addPrivateMessage` unless the
  method is POST, and checks `checkSiteToken()` before the router runs. Nothing
  in this plan may open a state-changing route to GET.
- **`updatePrivatBox()` answers a mailbox, not a view state.** It reads `typ`
  and nothing else, so the page number, the search, the filters and the sort of
  the request that triggered it are lost and the reader lands on page one — the
  page is read from `cid` in the query string, which a bulk POST does not carry.
  Every batch that adds view state has to carry it through this route as well.
- **`cid` means two different things.** In a mailbox view (`typ` 1–3) and in the
  pager it is the page number; in `setPrivateMessageRead()` it is the side —
  1 for the inbox, 2 for the outbox — and the compose branch (`typ = 4`) reads
  the same name again to decide whether the reply form is rendered at all, which
  is how a sent message ends up without one. A batch that threads view state
  through these routes must not add a third meaning to that name; the page
  travels under its own parameter or the overload gets resolved first.
- **The send contract is finished.** `addMessage()` answers a machine code —
  `not_logged`, `no_recipient`, `unknown_recipient`, `self`, `no_title`,
  `no_body`, `word_long`, `flood`, `quota`, `no_room`, `sql` — and every code is
  already mapped to its constant, `sql` through the default branch. The send
  interval, both quotas and the deadlock retry sit behind the account lock
  inside the subsystem; no view may re-check them and pretend the answer still
  holds. `quota` refuses on the recipient's inbox **or** their saved box.
- **Prepared statements are native.** `PDO::ATTR_EMULATE_PREPARES` is false, so
  a named placeholder may appear exactly once per statement. Every new predicate
  that compares the same value in several columns needs one placeholder per
  column.
- **The autocomplete answers a flat array of names.** `getUserList()` selects
  names with no `LIMIT`, and `fetchUserSuggestions()` in
  `plugins/system/global-func.js` assigns each item straight to `option.value`.
  Thirteen call sites reach it through `getTplUserSearchInput()`, most of them
  in admin forms; the compose field becomes the fourteenth and the only rich
  one, leaving twelve on the flat contract. It fires on every `input` event
  without a debounce; a stale answer is dropped by comparing the field value,
  but the request itself is not cancelled.
- **The editor has an API.** `window.SlaedToastUi.getEditor(id)` answers the
  Toast UI instance and `setMarkdown()` replaces its content; the hidden
  textarea is only the transport to the form. Writing the textarea alone does
  not change what the reader sees.
- **Settings**, all in `config/privat.php`. `act` enables the feature, `messin`
  and `messsav` are the two quotas, `num` is the page size, `nump` the pager
  width, `send` the interval in seconds, `letter` the longest unbroken word a
  body may carry, `himself` **refuses** a message addressed to the sender's own
  account when it is on — it is off on this stand, so writing to yourself works
  — `profil`, `web` and `newmail` the profile link, the site link and the mail
  notification. `anum` and `anump` belong to the administrator list.
- **The template boundary.** PHP passes data, text, URLs and semantic flags;
  fragments own the markup and the class names. This plan adds no exception.
- **Already in the theme.** `sl-chip-icon` makes a label-less state flag round
  and is emitted by `templates/lite/fragments/span.html` when a message flag
  carries no text. `sl-cab-badge` and `sl-pm-new` are border-box centred
  counters. The unread badge is capped at `99+` in `getUserNavItems()`.

## Decisions

Taken and not to be reopened without a reason written down here.

**Layout.** Two columns: the list keeps the screen, the message opens beside it.
The shelves, the focus deck, the filter bar, the dense row, the reading
typography and the bulk bar are the parts the labs contributed; the demo is the
reference for all of them.

**The quota is a ring, not an alert.** Every shelf carries a ring around its
icon: tone by the same ladder that colours the site footer, `rest` = 100 − per
cent as `stroke-dashoffset`, a solid ring where nothing can be measured
(outgoing and «Написать»). The text alerts `_PRINEXIT` and `_PRSAVEEXIT` stay,
because at the limit the reader needs to be told what to do. The half-capacity
branch goes with both its call sites — the mailbox view and the `save` answer of
`updatePrivatBox()` — and `_PRINMAX` and `_PRSAVEMAX` leave all six locale files
in the same change, because nothing else uses them.

**One main swap and a set of addressable out-of-band targets.** Opening a
message swaps the right column; the row, the shelf badge and the cabinet badge
are separate out-of-band targets, each with a stable id. Redrawing both columns
is forbidden: it throws away the scroll position and the selection, which is the
whole reason the layout has two columns. Out-of-band swapping needs an
addressable row, so the id contract, the layout and the first swap ship in one
batch and are never split.

**The rendered page is a snapshot; only navigation counters follow a mutation.**
Opening a message under the «непрочитанные» filter updates that row in place and
the two badges beside the shelves, and changes nothing else: the filtered total,
the chip numbers, the pager and the row set stay as they were rendered. Parity
is a property of one list response — `rows = min(limit, total − offset)` holds
for the answer that produced them — and it is restored on the next list request,
which is when the row leaves. The alternative, deleting the row and recomputing
the total, the pager and every chip inside a mutation answer, reshuffles the
list under the reader's cursor and is exactly what the two columns exist to
prevent.

**Navigation counters ignore the toolbar.** The shelf badges and the shelf
quotas answer the mailbox, not the current selection: a filter that hides
everything must not empty the shelves. A shelf badge exists for the inbox and
for the saved box only — the outgoing counter `getUnreadOutCount()` means «the
recipient has not read it», which is a different fact and gets no badge, so the
two badges always add up to the cabinet one.

**The filter chips are facets of the mailbox, not of each other.** Each chip
shows what its own condition answers inside the current mailbox, independent of
the other chips and of the search; only the toolbar counter and the pager read
the full selection. All chip numbers come from one aggregate row — a `COUNT`
with conditional sums over the mailbox predicate — so a chip never costs a query
of its own. The two period chips carry their own boundaries — «за неделю» in the
inbox, «старше месяца» among the saved — so that row binds `:fnew` and `:fold`
separately; one placeholder for both would be the repeat native prepares refuse.
Only when a mailbox shows both chips against one boundary may the older facet be
derived as total minus the newer one instead of being counted.

**The editor never enters a swap, and it is filled through its own API.** The
reply form lives below the swapped region and is created once per visit. The
detail answer carries the source in a hidden `<textarea>` inside the swapped
region — HTML-escaped text, which is the only transport that cannot terminate
its own context and is practical for a `MEDIUMTEXT` body. The handler reads its
`value`, calls `SlaedToastUi.getEditor(id).setMarkdown(source)`, fills the
recipient and title fields, and removes the carrier node. The hidden textarea of
the form is synchronised from the editor, never instead of it. This handler is a
new, small module in `plugins/system/slaed.js`, not demo code.

**The mobile pane switch is production JavaScript.** At 390 px the container
query hides one panel and `data-sl-pane` decides which; today that attribute is
driven by the demo's own inline script and `plugins/system/slaed.js` has no
handler for it at all. Without the handler a mobile reader opens a message and
stays on the hidden list. The switch, the back button and the focus move ship
with the layout batch.

**The deep link opens in two steps.** `op=privat&id=…` is a GET, and opening a
message writes `viewed`, so the GET only builds the layout with that message
targeted; the layout then issues the authenticated POST to
`setPrivateMessageRead()` that opens it, marks it read and fills the right
column. The mailbox is resolved by the reader's own copies — the inbox first,
the outbox as the fallback — because an id alone does not say which side the
reader is on, and both reads are already authorised by `getSideWhere()`. A
message the reader has no copy of answers the ordinary empty state.

**«Написать» is an action, not a mailbox.** The compose form opens in the right
column and the left column keeps the inbox, including the entry from a profile
link carrying `uname`. There is no fourth list.

**Sorting is one `ORDER BY` with four shapes, each ending in a unique
tie-breaker on `p.id` in the direction of its primary sort.** Newest first is
`p.time DESC, p.id DESC` and stays the default; oldest first is
`p.time ASC, p.id ASC`; unread first is `p.viewed ASC, p.time DESC, p.id DESC`;
by counterpart is `u.name ASC, p.time DESC, p.id DESC`. The tie-breaker is not
decoration: without it a page boundary can repeat or drop a row between two
requests. The parity `COUNT` needs the join only when the **search** reads
`u.name`; a sort never enters a count. Sort and filter values arrive from a
fixed whitelist, never as SQL fragments from the request.

**Search uses one placeholder per column and one declared escape character.**
The predicate is `p.title LIKE :ftit ESCAPE '!' OR p.body LIKE :fbody ESCAPE '!'
OR u.name LIKE :fusr ESCAPE '!'`, because native prepares refuse a repeated
name. `%`, `_` and `!` itself are escaped inside the term before the wildcards
are added, and the escape character is declared in the statement so the
behaviour does not depend on the SQL mode.

**A saved message leaves the inbox, and the counters follow it.** The inbox
predicate carries `saved = 0`, so no message is ever in two lists at once. Two
consequences the demo states and the implementation must keep: the shelf badges
split the unread between them — 7 in the inbox and 1 in saved on the stand —
while the cabinet badge keeps counting all of them as one number, and the focus
deck reads unread across both boxes, because a message saved without being read
is exactly the one that still needs attention. The per-box unread count does not
exist yet; `getUnreadCount()` is the cabinet one.

**The rich autocomplete is opt-in and bounded.** `getUserList()` keeps answering
a flat array of names for its twelve other call sites; the compose field asks
for the richer answer through its own parameter, and the JS renders the card
only when the answer carries the richer shape. The rich branch gets a `LIMIT`,
and the field gets a debounce before either shape is asked for. Migrating the
other consumers to a new shape is not part of this plan.

**The recipient card is drawn for the resolved recipient only.** The card asks
for one account — the one the name resolves to exactly — and never for every
suggestion, which is what keeps the answer to one query instead of N+1. The fill
of a stranger's mailbox is read through a method of `Privat`, because nothing
outside the class touches that table. What the card shows is the open question
below; the binding check stays inside `addMessage()`, behind the lock, and a
missing card never blocks a send.

**Forwarding is a route parameter.** The compose view —
`getPrivateMessageView()` with `typ = 4` — reads `cid` and `mod` today and gets
two new parameters, the source id and the forward flag; it loads the source
through `Privat::getMessageView()` under the reader's own predicate and prefills
a localised «Fwd: » prefix, the body without the quote wrapper and an empty
recipient.

**Every batch ships its own strings in all six locales.** A batch that renders a
new label — the day groups, the toolbar, the periods, the sort options, the
focus deck, the forward prefix — adds its constants to `de`, `en`, `fr`, `pl`,
`ru` and `uk` in the same change, and a batch that retires one removes it from
all six. Localisation is never a later step.

**A schema change is never part of a feature batch.** If the measurement of
batch 4 shows the body search needs `FULLTEXT`, that is its own batch with
install and update SQL, a version note and an explicit approval — and it changes
the search semantics, not only the storage. A BTREE index cannot serve
`LIKE '%term%'`, so «add an index» is not an option inside batch 4; the
alternatives there are dropping `body` from the search or keeping the scan.

## Predicate table

Every list query must match a row here; a query that does not is either a bug or
a missing row. The parity rule applies to the selection group only: list,
filtered count and pager come from one predicate set, inside one response.

| Selection | Predicate | Owner |
|---|---|---|
| Inbox | `uidin = :uid AND delin = 0 AND saved = 0` | `getBoxWhere(Inbox)`, exists |
| Outbox | `uidout = :uid AND delout = 0` | `getBoxWhere(Outbox)`, exists |
| Saved | `uidin = :uid AND delin = 0 AND saved = 1` | `getBoxWhere(Saved)`, exists |
| Cabinet badge | `uidin = :uid AND delin = 0 AND viewed = 0` | `getUnreadCount()`, exists |
| Shelf badge, inbox and saved only | box predicate `AND viewed = 0` | new, batch 1 |
| Focus deck | the cabinet badge predicate, newest first | new argument to `getRecentList()`, batch 6 |
| Chip facets | one row over the box predicate: total, `viewed = 0`, `viewed = 1`, `time >= :fnew`, `time < :fold` | new, batch 4 |
| Filter: unread / read | `AND viewed = 0` / `AND viewed = 1` | new, batch 4 |
| Filter: period, newer / older | `AND p.time >= :fnew` / `AND p.time < :fold`, one name per direction so both can stand in one statement | new, batch 4 |
| Search | `AND (p.title LIKE :ftit ESCAPE '!' OR p.body LIKE :fbody ESCAPE '!' OR u.name LIKE :fusr ESCAPE '!')` | new, batch 4 |
| Filtered count | the selection predicate, `COUNT(*)`, join only when the search reads `u.name` | new, batch 4 |
| Recipient fill | `getMessageCount()` of one account, one call | new, batch 5 |

## Files this touches

- `core/classes/privat.php` — snippet cut, per-box unread, chip facets,
  selection arguments, the recipient fill method
- `core/user.php` — `getPrivateMessageView()`, the four routes, day grouping,
  view state carried through mutations
- `modules/account/index.php` — `privat()` loses the tabs and honours `id`
- `core/system.php` — the opt-in rich branch and the `LIMIT` of `getUserList()`
- `plugins/system/slaed.js` — the pane switch and the editor fill contract
- `plugins/system/global-func.js` — debounce and the rich answer shape
- `templates/lite/fragments/*`, `templates/lite/partials/*` — the new layout
- `templates/lite/assets/css/theme.css` — `sl-pmh-*` and the layout classes
- `lang/{de,en,fr,pl,ru,uk}.php` — in every batch that adds or retires a string
- `setup/sql/table.sql` and a new `setup/sql/table_update*.sql` — batch 7 only,
  and only after its own approval

The concrete list of new template files is proposed in batch 3 and must be
confirmed before it is created: `.rules/global.md` forbids adding a template or
a class without asking.

## Batch 1 — the data batches 2 and 3 consume

No visible change. `Privat` gains the per-box unread count for the inbox and the
saved box, and the snippet cut in the list query. Sort and filter arguments are
not part of this batch; they arrive with the toolbar that uses them. Unit tests
cover both counts and the cut. Verify: the existing page renders exactly as
before; the snippet is cut by the database, not a body pulled per row; the cut
is multibyte-safe, carries no half-open markup into the interface and is escaped
at the template boundary; `composer test` covers the new predicates.

## Batch 2 — the quota ring and the shelves

Move `sl-pmh-*` into `templates/lite/assets/css/theme.css`, add the shelf strip
as a fragment, and give the view four values per shelf — `tone`, `rest`,
`no_limit`, the screen-reader `label` — plus the unread badge for the inbox and
the saved box. The tabs stay; the shelves render above them. Remove the
half-capacity alert in both call sites and `_PRINMAX`/`_PRSAVEMAX` from all six
locales. Verify: the ring tone matches the ladder at 25, 60, 86 and 96 per cent,
the two badges add up to the cabinet badge, the limit alert still fires when a
mailbox is full, no constant is left behind in any locale, and the strip is
readable at 390 px.

## Batch 3 — the working layout

The tabs die and the layout arrives as one working contract: the split, the
two-level row with its stable id, the day groups with their labels in six
locales, the row actions, the bulk bar, the main swap into the right column, the
out-of-band swaps for the row and both badges, the editor fill through the
hidden carrier textarea, the mobile pane switch in `plugins/system/slaed.js`,
and the two-step deep link. These do not split: an out-of-band swap needs an
addressable row, a hidden panel needs a handler that brings it back, and a
detail swap without the editor contract leaves the reply form showing the
previous message. Verify with real requests: opening keeps the scroll position
and the selection, the row loses its unread mark in place, both badges count
down, `viewed` is 1 in the database, the editor instance survives three
consecutive opens and shows the body it was given, the carrier node is gone
afterwards, at 390 px the list opens a message and the back button returns focus
where the reader can continue, `op=privat&id=…` opens a permitted message
through the POST and refuses a foreign one, and the three logs stay clean.

## Batch 4 — the toolbar and the selection

Search, the state and period filters in both directions, the sort select, the
chip facets, «найдено N из M», and the view state carried through the pager, the
row actions and the bulk route — with the new strings in six locales. Produce
`EXPLAIN` for the search and for the by-counterpart sort. Verify the parity
formula inside one response, that the shelves and the chips keep their own
semantics while the toolbar counts the selection, that a filter hiding
everything renders the empty state and not a broken pager, that a search for
`100%` matches what it should, and that a bulk action returns to the same page,
filter, search and sort.

## Batch 5 — the compose form and the recipient card

The compose form in the right column, the opt-in rich branch of `getUserList()`
with its `LIMIT` and debounce, and the card for the resolved recipient. Verify
that the flat contract still serves the other twelve call sites unchanged, that
an answer without card data still sends, that the rich branch cannot be asked
for an unbounded list, and that every send outcome matches its constant,
including the flood interval and the full recipient.

## Batch 6 — the focus deck and forwarding

The unread-only variant of `getRecentList()` behind the collapsible deck, the
forward parameter on the compose view, and the strings both introduce in six
locales. Verify that the deck disappears when nothing is unread, that its
actions hit the same routes as the list, and that a forwarded message arrives
with an empty recipient, a localised prefix and no quote wrapper.

## Batch 7 — the search index, only if measured

Written only if the `EXPLAIN` of batch 4 says the body search cannot stay a
scan. `FULLTEXT` on `title`/`body` is a schema change and a change of search
semantics — word boundaries, stop words and minimum length instead of substring
matching. Install SQL, update SQL, a version note, a decision about the changed
semantics, and its own approval before a line is written.

## Verification baseline

Every batch, from the project root in PowerShell:

- `php -l` on every changed PHP file
- `composer analyse`
- `composer test`
- `php-cs-fixer fix --dry-run --diff`
- real authenticated requests for anything that renders or mutates, never a
  direct DB write presented as a UI check
- `storage/logs/error_php.log`, `error_sql.log`, `error_site.log` after every
  state-changing run
- the layout at 1240, 1040, 800 and 390 px
- every new or retired constant present in, or gone from, all six locales

Per flow, when the batch touches it:

| Flow | What to check |
|---|---|
| Mailbox, search, filter, sort | Parity by the formula inside one response; stable order across pages; the query surviving the pager, the row actions and the bulk route; whitelisted sort and filter values |
| Opening an unread message | `viewed = 1` in the database; the row updated in place; both badges; the snapshot counters unchanged until the next list request; scroll and selection kept; the editor not recreated and the carrier node removed |
| Bulk read, save, delete | Real POST with CSRF; `viewed`, `saved`, `delin`, `delout` in the database; page, filter and sort preserved; logs |
| Compose and send | Every machine code covered by a unit test; a real POST that stores; the row visible to both sides; nothing stored on refusal |
| Autocomplete and card | The flat answer unchanged for the other call sites; the rich answer bounded; a missing card still sends |
| Forward and deep link | Empty recipient, localised prefix, source body without the quote wrapper; `op=privat&id=…` builds the layout by GET and opens by POST; a foreign id refused |
| Responsive | 1240 / 1040 / 800 / 390 px; list, view and back transitions; keyboard and focus order |

## Out of scope

- The administrator mailbox in `admin/modules` and `getAdminList()`.
- Attachments, threading and a chat-style transcript. The demo is a mailbox.
- The mail notification format and the queue behind `privat.newmail`.
- Migrating the other twelve consumers of `getTplUserSearchInput()` to a rich
  answer.
- Retiring the lab demos `private-messages-v1` … `v8`; that is a separate
  clean-up with its own approval.

## Open

Close these before the batch that needs them, not during it.

- **The new template file set** of batch 3, and whether the shelf strip replaces
  the account navigation on this page or stands below it. Needed before batch 3.
- **What the recipient card may show.** The demo prints another account's exact
  fill — «входящих 214 из 250 · свободно 36». That is one member's mailbox state
  shown to another, and a coarse tone (room / almost full / full) carries the
  same warning without the numbers. If the coarse variant is chosen, the demo
  card changes with it. Needed before batch 5.
- **The parameter name and shape of the rich autocomplete answer.** Needed
  before batch 5.
- Whether the index of batch 7 is wanted at all, once batch 4 has measured, and
  whether its changed search semantics are acceptable.

## Progress

| # | Batch | Status | Evidence |
|---|---|---|---|
| 0 | Layout approved, decisions recorded | done | `demo/private-messages-final-2026.html`, section 7 |
| 0 | Theme groundwork: `sl-chip-icon`, centred counters, badge cap | done | `templates/lite/assets/css/theme.css`, `templates/lite/fragments/span.html`, `getUserNavItems()` |
| 1 | Data for batches 2 and 3 | planned | — |
| 2 | Quota ring and shelves | planned | — |
| 3 | The working layout | planned | — |
| 4 | Toolbar and selection | planned | — |
| 5 | Compose form and recipient card | planned | — |
| 6 | Focus deck and forwarding | planned | — |
| 7 | Search index, only if measured | conditional | — |
