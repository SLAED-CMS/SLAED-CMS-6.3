# Private Messages 2026

Work plan for the two-column redesign of the private messages page.

Status: groundwork and batches 1–4 implemented, batches 5–7 not. Run the batches in
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

**The shelf strip stands below the account navigation, not in place of it.** The
two strips are two axes: `getUserNav()` is the cabinet, present on every inner
page, and the shelves switch the mailbox. Dropping the cabinet strip on this one
page would leave the reader of a mailbox without a way back to the settings or
the exit, and would make the private message page the only cabinet page without
its own navigation. The tabs die in batch 3, so what is left above the layout is
two strips and not three.

**The quota is a ring, not an alert.** Every shelf carries a ring around its
icon: tone by the same ladder that colours the site footer, the per cent itself
as the drawn half of `stroke-dasharray`, and a solid ring wherever there is
nothing to measure (outgoing and «Написать») or nothing left to fill. The demo
drew the arc as an offset of a whole dash instead; that leaves a hairline at 12
o'clock on a mailbox at zero per cent, which is what a new account sees, so the
dash carries the arc and no view subtracts anything from a hundred. At a full
hundred the two butt ends of the arc meet at that same point, so a full ring
takes the tone into its track and the join has nothing to show through it.

**The ring is one component of the platform, not of this page.** It is
`sl-knob`, the name the admin theme already gave it, and both themes now carry
the same class family, the way the unified alert is carried in both. Geometry
belongs to the markup — each consumer draws its own viewBox and radius — and
appearance to the theme, through `--sl-knob-size`, `--sl-knob-width`,
`--sl-knob-tone` and `--sl-knob-track`. The tone itself comes from
`getPercentTone()` in `core/system.php`, which is the one ladder the shelves,
the server gauges and the debug panel all read. The text alerts `_PRINEXIT` and `_PRSAVEEXIT` stay,
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

**The shelf counters are five separate counts and stay that way.** Three mailbox
counts and two per-box unread counts, each a `ref` on `in_box` or `in_new` and
three of them index-only. One aggregate row replacing all five was measured
against them on a mailbox of 240 messages: 1.0 ms against 5.0 ms — but `EXPLAIN`
answers `type=ALL` for the aggregate, because no index carries `saved` and
`viewed` together, so it wins only while the table is small and scans the whole
of it on every visit once it is not. The five counts are five round trips of
about a millisecond and grow with one reader's mail rather than with the table.
A covering `(uidin, delin, saved, viewed)` would change that answer; it is a
schema change and belongs to the same approval as the index of batch 7.

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

**The quota of a mailbox is read from `Privat` and nowhere else.**
`getBoxLimit()` answers which setting bounds which box and that the outbox is
bounded by none; `getBoxFill()` answers what a mailbox holds, what it may hold
and the per cent between them, clamped. The rings, the full-mailbox alert, the
saved-quota refusal of a bulk save and the recipient card of batch 5 all read
that one pair, so no view restates `messin` or `messsav` of its own. What the
class still never answers is a tone, a class name or a text: the ladder and the
strings stay in the adapter.

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
| Shelf badge, inbox and saved only | box predicate `AND viewed = 0` | `getUnreadBoxCount()`, exists |
| Focus deck | the cabinet badge predicate, newest first | new argument to `getRecentList()`, batch 6 |
| Chip facets | one row over the box predicate: total, `viewed = 0`, `viewed = 1`, `time >= :fnew`, `time < :fold` | new, batch 4 |
| Filter: unread / read | `AND viewed = 0` / `AND viewed = 1` | new, batch 4 |
| Filter: period, newer / older | `AND p.time >= :fnew` / `AND p.time < :fold`, one name per direction so both can stand in one statement | new, batch 4 |
| Search | `AND (p.title LIKE :ftit ESCAPE '!' OR p.body LIKE :fbody ESCAPE '!' OR u.name LIKE :fusr ESCAPE '!')` | new, batch 4 |
| Filtered count | the selection predicate, `COUNT(*)`, join only when the search reads `u.name` | new, batch 4 |
| Recipient fill | `getMessageCount()` of one account, one call | `getBoxFill()`, exists |

## Files this touches

- `core/classes/privat.php` — snippet cut, per-box unread, the quota pair
  `getBoxLimit()`/`getBoxFill()`, chip facets, selection arguments
- `core/user.php` — `getPrivateMessageView()`, the four routes, day grouping,
  view state carried through mutations
- `modules/account/index.php` — `privat()` loses the tabs and honours `id`
- `core/system.php` — the opt-in rich branch and the `LIMIT` of `getUserList()`
- `plugins/system/slaed.js` — the pane switch and the editor fill contract
- `plugins/system/global-func.js` — debounce and the rich answer shape
- `templates/lite/fragments/privat-shelves.html` — the shelf strip, batch 2
- `templates/lite/partials/privat-{page,list,view}.html` and
  `templates/lite/fragments/privat-row.html` — the layout of batch 3, the table
  below says what each of them owns
- `templates/lite/assets/css/theme.css` — the shared `sl-knob-*` and the layout classes
- `lang/{de,en,fr,pl,ru,uk}.php` — in every batch that adds or retires a string
- `setup/sql/table.sql` and a new `setup/sql/table_update*.sql` — batch 7 only,
  and only after its own approval

The four template files of batch 3 are confirmed, one per swap boundary, so that
no answer ever redraws more than the region it owns:

| File | What it owns | Who swaps it |
|---|---|---|
| `partials/privat-page.html` | The two-column shell, `data-sl-pane`, the «К списку» button | Nobody: rendered once per visit |
| `partials/privat-list.html` | The left column: day groups, the row loop, the pager, the bulk bar | The pager, the filters and the bulk route |
| `partials/privat-view.html` | The right column: the message, its actions, the hidden carrier textarea | The main swap of `setPrivateMessageRead()` |
| `fragments/privat-row.html` | One dense row under its stable id | The out-of-band swap beside a detail answer |

Reused unchanged: `swap-oob`, `alert`, `pager`, `checkbox`, `dial`, `span`,
`link`, `block-content` and the textarea helper. The day group header lives
inside the list partial; it becomes a file of its own only if the focus deck of
batch 6 asks for the same header.

## Batch 1 — the data batches 2 and 3 consume

No visible change. `Privat` gains the per-box unread count for the inbox and the
saved box, and the snippet cut in the list query. Sort and filter arguments are
not part of this batch; they arrive with the toolbar that uses them. Unit tests
cover both counts and the cut. Verify: the existing page renders exactly as
before; the snippet is cut by the database, not a body pulled per row; the cut
is multibyte-safe, carries no half-open markup into the interface and is escaped
at the template boundary; `composer test` covers the new predicates.

## Batch 2 — the quota ring and the shelves

Put the ring into `templates/lite/assets/css/theme.css` as the shared `sl-knob`,
add the shelf strip as a fragment, and give the view four values per shelf —
`tone`, `part`, `full`, the screen-reader `label` — plus the unread badge for
the inbox and the saved box. The tabs stay; the shelves render above them. Remove the
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

- **What the recipient card may show.** The demo prints another account's exact
  fill — «входящих 214 из 250 · свободно 36». That is one member's mailbox state
  shown to another, and a coarse tone (room / almost full / full) carries the
  same warning without the numbers. If the coarse variant is chosen, the demo
  card changes with it. Needed before batch 5.
- **The parameter name and shape of the rich autocomplete answer.** Needed
  before batch 5.
- Whether the index of batch 7 is wanted at all, once batch 4 has measured, and
  whether its changed search semantics are acceptable — and, in the same
  decision, whether a covering `(uidin, delin, saved, viewed)` is worth it for
  the shelf counters.

## Progress

| # | Batch | Status | Evidence |
|---|---|---|---|
| 0 | Layout approved, decisions recorded | done | `demo/private-messages-final-2026.html`, section 7 |
| 0 | Theme groundwork: `sl-chip-icon`, centred counters, badge cap | done | `templates/lite/assets/css/theme.css`, `templates/lite/fragments/span.html`, `getUserNavItems()` |
| 1 | Data for batches 2 and 3 | done | `getUnreadBoxCount()`, `filterSnippet()` and the `LEFT(p.body, …)` cut in `core/classes/privat.php`; three scenarios in `PrivatClassTest`. Gates green; the four tabs render byte for byte as before; `EXPLAIN` unchanged |
| 2 | Quota ring and shelves | done | `getPrivatShelves()`, `fragments/privat-shelves.html`, the shared `sl-knob-*` and `sl-pmf-fold*` in `theme.css`; five strings into and `_PRINMAX`/`_PRSAVEMAX` out of six locales. Gates green; the ladder holds at 25/60/86/96 per cent and the limit alert at 100; a real POST made both badges agree; four widths clean. Tiles are not links and the `@container` rule waits for the layout of batch 3 |
| 3 | The working layout | done | `privat()` without tabs and with `id`/`typ`/`uname`, `getPrivatRowData()`, `getPrivatBadges()` and the rewritten `getPrivateMessageView()`/`setPrivateMessageRead()`/`updatePrivatBox()` in `core/user.php`; `snip` on `getMessageView()`; `partials/privat-{page,list,view}.html`, `fragments/privat-row.html`, links and badge slots in `fragments/privat-shelves.html`, `is_message_read`/`is_cab_badge`/`is_oob` in `fragments/span.html`, the badge id in `fragments/account-nav.html`; the layout classes in `theme.css`; `no_params` in `fragments/dial.html`; the pane switch, the carrier fill and the open mark in `plugins/system/slaed.js`; seven strings into six locales. Gates green; a real POST opened a message, wrote `viewed = 1`, redrew its row in place and moved both badges and the cabinet one, while the page scroll, the selection and the row set stayed as rendered; one editor instance survived three opens and the carrier was gone after each; a real bulk `unread` moved the badges from 4/5 to 6/7; `op=privat&id=…` opened a permitted message by POST and answered the empty pane for a foreign one; the three logs stayed clean. Two columns at container widths 1240 and 1040, the panel swap and the back button at 800 and 390 — on a stand with side blocks the account column is under 800 px, so the reader gets the swap layout there as well. A self check caught two defects and both are fixed and re-verified: a shelf link drew the mailbox into both columns at once, ids and all, because the empty pane was asked for with the same zero the request falls back on; and a row action sent the selection form with itself, so the bulk action ran on the checked rows instead — every action of a row and of the reading pane now names everything in its address and sends no body |
| 4 | Toolbar and selection | done | `getPickWhere()`, `filterFindText()`, `getSortOrder()`, `getBoxFacets()`, the selection argument of `getMessageList()` and the join-aware `getTotal()` in `core/classes/privat.php`; `getPrivatPick()` and the selection carried through the list, the pager, the row actions and the bulk route in `core/user.php`; the toolbar and the chip bar in `partials/privat-page.html`, the selection counter in `partials/privat-list.html`, the chip logic in `plugins/system/slaed.js`, the bar and chip styles in `theme.css`; the page parameter renamed to `pnum` so `cid` keeps its two meanings and gains no third; fifteen strings into six locales. Gates green. Parity holds inside every answer measured: 240/25 page 10 gives 15 rows and «показано 226–240», `pnum=99` clamps to the last page instead of breaking the pager. The chips keep the facets of the mailbox under every selection and a filter that hides everything leaves the shelves and the chip numbers untouched and renders the empty state. A literal `%` finds 11 messages where a wildcard would find 240, and `100%` finds the same 3 rows the raw statement does. Search with the by-counterpart sort answers 39 of 240. The pager, a bulk action and a row action all return to the same page, filter, search and sort. A self check ran the paths the first pass had missed — `:fold` in the saved box, both period placeholders beside the three of the search in one statement, the unread-first and by-counterpart sorts, a search over the outbox counterpart, and rejected sort and filter values — and found no defect; the empty selection now also says how many the filter hid |
| 5 | Compose form and recipient card | planned | — |
| 6 | Focus deck and forwarding | planned | — |
| 7 | Search index, only if measured | measured, not needed | `EXPLAIN` of batch 4 on 1472 stored messages: the search reads `type=ref` on `in_box` and filters inside it, so it never scans the table but only the reader's own mailbox, which the quota bounds at 250. The count of the search takes the same access path with `eq_ref` on the users primary key. The by-counterpart sort adds `Using temporary; Using filesort` over that same mailbox. Nothing here asks for `FULLTEXT`, and its changed semantics would cost more than the scan it removes. The decision to close this batch is yours |
