# Private Messages 2026

Work plan for the two-column redesign of the private messages page.

Status: every batch is implemented. Batch 7 was measured and closed without
touching the schema, so nothing in this document is waiting on a decision and
nothing is left to run. What remains is a record: the decisions below are why the
code reads as it does, and the Progress table is the evidence each batch left.

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

Written down so the next reader does not re-derive it. This section is the state
the work started from, kept in the present tense it was written in: the tabs, the
route that read only `typ`, the autocomplete without a debounce. What each batch
did to it is in Decisions and in the Progress table, and where the two disagree
the code is the answer.

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
  interval, the quota and the deadlock retry sit behind the account lock
  inside the subsystem; no view may re-check them and pretend the answer still
  holds. `quota` refused on the recipient's inbox **or** their saved box when
  this was written; the second half was a regression and Decisions records why it
  is gone.
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
is exactly the one that still needs attention. `getUnreadBoxCount()` is the shelf
one and `getUnreadCount()` the cabinet one.

**The rich autocomplete is opt-in and bounded.** `getUserList()` keeps answering
a flat array of names for its twelve other call sites; the compose field asks
for the richer answer through its own parameter, and the JS renders the card
only when the answer carries the richer shape. The rich branch gets a `LIMIT`,
and the field gets a debounce before either shape is asked for. Migrating the
other consumers to a new shape is not part of this plan.

The parameter is `rich=1` and the answer is one object for one round trip:
`{"items": [names], "card": {…}|null}`. The names are what the datalist reads and
the card is filled only when the typed term resolved to an account exactly, which
is why one keystroke costs one bounded name query and, at most, one count. The
`LIMIT` is 10 and the debounce 250 ms. The flat branch keeps its shape and its
lack of a card, but not its lack of a bound: it answers 50. On this stand a
one-letter term matched 1246 accounts and a three-letter one 193, and a
suggestion list nobody can read to the end is a page of a database rather than an
answer — the twelve forms lose nothing they could use. The field declares its
opt-in by naming the container the card lives in — the markup and the class names
stay in the theme, and the answer carries data only.

**The quota of a mailbox is read from `Privat` and nowhere else.**
`getBoxLimit()` answers which setting bounds which box and that the outbox is
bounded by none; `getBoxFill()` answers what a mailbox holds, what it may hold
and the per cent between them, clamped. The rings, the full-mailbox alert, the
saved-quota refusal of a bulk save and the recipient card of batch 5 all read
that one pair, so no view restates `messin` or `messsav` of its own. What the
class still never answers is a tone, a class name or a text: the ladder and the
strings stay in the adapter.

**A limit of zero is the absence of a bound, everywhere and without exception.**
It is what `getBoxLimit()` answers for the outbox, which has no quota at all;
what `getBoxFill()` reads as nothing to measure; what the shelf draws as a solid
ring under «без ограничения»; and what the card grades as `none`. The write path
used to read it as a bound of zero instead, and `count >= 0` is always true: with
`messin` at zero every send was refused, and with `messsav` at zero every save
was. Both now test the bound before they compare against it, and both readings
are pinned by a test that fails without the guard. Seven places read a mailbox
limit and all seven agree; an eighth must ask `> 0` before it compares.

**The recipient card is drawn for the resolved recipient only.** The card asks
for one account — the one the name resolves to exactly — and never for every
suggestion, which is what keeps the answer to one query instead of N+1. The fill
of a stranger's mailbox is read through a method of `Privat`, because nothing
outside the class touches that table. The binding check stays inside
`addMessage()`, behind the lock, and a missing card never blocks a send.

**A send is refused by the inbox alone, and the card watches the same one.** The
stored row carries no saved flag of its own, so an arriving message can only land
in the inbox; a full saved folder has nothing to do with whether it can be
received. `checkQuota()` used to refuse on the inbox **or** the saved folder, and
that turned the card into a liar: an account holding 10 of 250 received and 250
of 250 saved read green and the send was refused anyway. The second condition is
gone. It was never a decision — the code this module grew out of counted only the
inbox against `messin` — and the saved quota is enforced where it belongs, inside
`setMessageSaved()`, over what a batch really adds, at the moment the reader
moves a message into that folder. `_PRSENDOVER` names the inbox and is now the
only thing that can be true when it is shown.

**The card shows a grade and never another member's numbers.** How full one
member's mailbox is belongs to them, so the card carries three steps a sender can
act on — there is room, it is nearly full, it will refuse — and no per cent, no
count and no arc. The grade folds `getPercentTone()`, the same ladder the shelves
read: its `ok` and `info` become «there is room», its `warn` and `danger` become
«nearly full», and a mailbox at or over its limit becomes «full». A mailbox no
setting bounds is graded `none` rather than well, because nothing measured is not
the same as nothing wrong. The ring is drawn solid in the grade's tone rather
than as an arc — an arc would put the per cent back into the markup — and the
chip beside it takes that same tone, so one grade is never told in two colours.
This replaces the demo card, which printed «входящих 214 из 250 · свободно 36».

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

**The schema is not touched, and batch 7 is closed.** The measurement said the
search never scans the table: it takes `type=ref` on `in_box` and filters inside
the reader's own mailbox, which the quota bounds at 250. `FULLTEXT` would buy
nothing there and would cost the substring matching the reader expects, so it is
refused rather than deferred. The covering `(uidin, delin, saved, viewed)` is
refused with it: the five shelf counts are five round trips of about a
millisecond that grow with one reader's mail and not with the table, and an index
added to make one aggregate row possible would be paid for by every write.
Reopen both only against a new measurement, not against a hunch.

**The focus deck stands over the inbox and nowhere else.** The demo draws it in
the inbox and in the mobile inbox, and in no other state: the outbox has no
unread of its own to answer for, a saved message is not going anywhere, and
«Написать» is an action whose left column happens to be the inbox rather than a
mailbox of its own. Section 3 of the demo says it in words — «колода фокуса
остаётся во входящих» — and the three other frames say it by not drawing one.

**Every action of a deck slot names the inbox, and the inbox action set gains
`unsave` for it.** The deck stands over the inbox, so an answer that redraws
`#prlist` has to redraw the inbox; a slot that named its own mailbox would swap
the saved folder under a reader who is looking at the received one, while the
shelf strip and the toolbar still said inbox. Every action except one already
works that way, because `setMessageRead()`, `setMessageSaved()` and
`deleteMessage()` all authorize by the reader's own side and not by the folder.
The exception is `unsave`, which the inbox never offered — and it has to, because
the deck shows saved messages that were never read and the demo gives them that
button. Widening the set grants nothing: the write still authorizes itself inside
the transaction, and the reader can already unsave from the saved folder. Each
action also carries the page, the search, the filters and the sort, exactly as a
list row does, so acting from the deck lands back on the selection the reader was
holding.

**The focus deck summary carries one count and not two.** The demo prints «8
непрочитанных · 3 старше суток» on the wide layout and only the first of them at
390 px. The second matches no row of the predicate table, and a count that exists
nowhere in it is either a bug or a missing row; adding the row is an amendment
this plan did not need. The deck therefore says how many are unread, read from
`getUnreadCount()`, which is already counted for the badge — the deck costs no
query of its own beyond the rows it shows.

## Predicate table

Every list query must match a row here; a query that does not is either a bug or
a missing row. The parity rule applies to the selection group only: list,
filtered count and pager come from one predicate set, inside one response.

| Selection | Predicate | Owner |
|---|---|---|
| Inbox | `uidin = :uid AND delin = 0 AND saved = 0` | `getBoxWhere(Inbox)`, exists |
| Outbox | `uidout = :uid AND delout = 0` | `getBoxWhere(Outbox)`, exists |
| Saved | `uidin = :uid AND delin = 0 AND saved = 1` | `getBoxWhere(Saved)`, exists |
| Cabinet badge | `uidin = :uid AND delin = 0 AND viewed = 0` | `getNewWhere()` through `getUnreadCount()`, exists |
| Shelf badge, inbox and saved only | box predicate `AND viewed = 0` | `getUnreadBoxCount()`, exists |
| Focus deck | the cabinet badge predicate, newest first | `getNewWhere()` through `getRecentList()`, exists |
| Chip facets | one row over the box predicate: total, `viewed = 0`, `viewed = 1`, `time >= :fnew`, `time < :fold` | `getBoxFacets()`, exists |
| Filter: unread / read | `AND viewed = 0` / `AND viewed = 1` | `getPickWhere()`, exists |
| Filter: period, newer / older | `AND p.time >= :fnew` / `AND p.time < :fold`, one name per direction so both can stand in one statement | `getPickWhere()`, exists |
| Search | `AND (p.title LIKE :ftit ESCAPE '!' OR p.body LIKE :fbody ESCAPE '!' OR u.name LIKE :fusr ESCAPE '!')` | `getPickWhere()`, exists |
| Filtered count | the selection predicate, `COUNT(*)`, join only when the search reads `u.name` | `getTotal()`, exists |
| Recipient fill | `getMessageCount()` of one account, one call, the inbox only | `getBoxFill()`, exists |

Every row is owned by a symbol that exists. A query that matches none of them is
still a bug or a missing row, and adding a row is an amendment to this plan. The
badge and the deck read one predicate through `getNewWhere()` rather than two
spellings of it, so a counter and the cards under it can no longer drift apart.

## Files this touches

- `core/classes/privat.php` — snippet cut, per-box unread, the quota pair
  `getBoxLimit()`/`getBoxFill()`, chip facets, selection arguments, the unread
  branch of `getRecentList()`
- `core/user.php` — `getPrivateMessageView()`, the four routes, day grouping,
  view state carried through mutations, `getPrivatShelves()`,
  `getPrivatFocus()`, `getPrivatBadges()`, `getPrivatRowData()`,
  `getPrivatPick()`, the forward branch of the compose view
- `modules/account/index.php` — `privat()` loses the tabs and honours `id`
- `core/system.php` — the opt-in rich branch and the `LIMIT` of `getUserList()`,
  and `getUserCardData()` beside it
- `core/helpers.php` — the card container a field opts in with, on
  `getTplUserSearchInput()`
- `plugins/system/slaed.js` — the pane switch, the editor fill contract, the
  compose eraser and the reply focus
- `plugins/system/global-func.js` — debounce, the rich answer shape and the card
  the answer fills
- `templates/lite/fragments/privat-shelves.html` — the shelf strip, batch 2
- `templates/lite/fragments/privat-focus.html` — the focus deck, batch 6
- `templates/lite/fragments/{input,span}.html` — the card hook of a field and the
  generic tone and icon of a chip
- `templates/lite/partials/privat-{page,list,view}.html` and
  `templates/lite/fragments/privat-row.html` — the layout of batch 3, the table
  below says what each of them owns
- `templates/lite/assets/css/theme.css` — the shared `sl-knob-*`, the layout
  classes, `sl-pmf-mate` and the `sl-pmf-focus`/`sl-pmf-deck`/`sl-pmf-slot*` family
- `lang/{de,en,fr,pl,ru,uk}.php` — in every batch that adds or retires a string
- `tests/Support/privat_class_probe.php` and `tests/Unit/PrivatClassTest.php` —
  the predicates of the class, the deck among them
- `setup/sql/*` — never: batch 7 is closed and the schema is untouched

The four template files of batch 3 are confirmed, one per swap boundary, so that
no answer ever redraws more than the region it owns:

| File | What it owns | Who swaps it |
|---|---|---|
| `partials/privat-page.html` | The two-column shell, `data-sl-pane`, the «К списку» button, the toolbar and chip bar of batch 4, the send form and the recipient card of batch 5 | Nobody: rendered once per visit |
| `partials/privat-list.html` | The left column: day groups, the row loop, the pager, the bulk bar | The pager, the filters and the bulk route |
| `partials/privat-view.html` | The right column: the message, its actions, the hidden carrier textarea, the compose state with its chips and eraser | The main swap of `setPrivateMessageRead()`, the answer of `addPrivateMessage()`, and the forward branch of `getPrivateMessageView()` |
| `fragments/privat-row.html` | One dense row under its stable id | The out-of-band swap beside a detail answer |
| `fragments/privat-shelves.html` | The shelf strip with its rings and badges | Nobody: rendered once per visit |
| `fragments/privat-focus.html` | The focus deck over the inbox | Nobody: rendered once per visit |

Reused unchanged: `swap-oob`, `alert`, `pager`, `checkbox`, `dial`, `link`,
`block-content` and the textarea helper. Two shared fragments were extended
rather than reused as they stood, and both extensions are additive: `span.html`
gained the generic `chip_tone` and `icon_name` that `link.html` already carried,
so a chip with a tone and an icon needs no markup from PHP; and `input.html`
gained the card container a user-search field opts into, which is the whole of
the rich autocomplete's opt-in. Neither changes what an existing caller renders.
The day group header lives inside the list partial; it became a file of its own
for nobody, because the focus deck draws its own card and not that header.

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

Nothing. The three questions this section carried are answered in Decisions
above: the recipient card shows a grade and not another member's numbers, the
rich autocomplete is `rich=1` answering `{items, card}` in one round trip, and
batch 7 is closed without a schema change — neither `FULLTEXT` nor the covering
index is taken. Reopen any of them only against a new measurement, and write the
question back here before the batch that needs it, not during it.

## Progress

| # | Batch | Status | Evidence |
|---|---|---|---|
| 0 | Layout approved, decisions recorded | done | `demo/private-messages-final-2026.html`, section 7 |
| 0 | Theme groundwork: `sl-chip-icon`, centred counters, badge cap | done | `templates/lite/assets/css/theme.css`, `templates/lite/fragments/span.html`, `getUserNavItems()` |
| 1 | Data for batches 2 and 3 | done | `getUnreadBoxCount()`, `filterSnippet()` and the `LEFT(p.body, …)` cut in `core/classes/privat.php`; three scenarios in `PrivatClassTest`. Gates green; the four tabs render byte for byte as before; `EXPLAIN` unchanged |
| 2 | Quota ring and shelves | done | `getPrivatShelves()`, `fragments/privat-shelves.html`, the shared `sl-knob-*` and `sl-pmf-fold*` in `theme.css`; five strings into and `_PRINMAX`/`_PRSAVEMAX` out of six locales. Gates green; the ladder holds at 25/60/86/96 per cent and the limit alert at 100; a real POST made both badges agree; four widths clean. Tiles are not links and the `@container` rule waits for the layout of batch 3 |
| 3 | The working layout | done | `privat()` without tabs and with `id`/`typ`/`uname`, `getPrivatRowData()`, `getPrivatBadges()` and the rewritten `getPrivateMessageView()`/`setPrivateMessageRead()`/`updatePrivatBox()` in `core/user.php`; `snip` on `getMessageView()`; `partials/privat-{page,list,view}.html`, `fragments/privat-row.html`, links and badge slots in `fragments/privat-shelves.html`, `is_message_read`/`is_cab_badge`/`is_oob` in `fragments/span.html`, the badge id in `fragments/account-nav.html`; the layout classes in `theme.css`; `no_params` in `fragments/dial.html`; the pane switch, the carrier fill and the open mark in `plugins/system/slaed.js`; seven strings into six locales. Gates green; a real POST opened a message, wrote `viewed = 1`, redrew its row in place and moved both badges and the cabinet one, while the page scroll, the selection and the row set stayed as rendered; one editor instance survived three opens and the carrier was gone after each; a real bulk `unread` moved the badges from 4/5 to 6/7; `op=privat&id=…` opened a permitted message by POST and answered the empty pane for a foreign one; the three logs stayed clean. Two columns at container widths 1240 and 1040, the panel swap and the back button at 800 and 390 — on a stand with side blocks the account column is under 800 px, so the reader gets the swap layout there as well. A self check caught two defects and both are fixed and re-verified: a shelf link drew the mailbox into both columns at once, ids and all, because the empty pane was asked for with the same zero the request falls back on; and a row action sent the selection form with itself, so the bulk action ran on the checked rows instead — every action of a row and of the reading pane now names everything in its address and sends no body |
| 4 | Toolbar and selection | done | `getPickWhere()`, `filterFindText()`, `getSortOrder()`, `getBoxFacets()`, the selection argument of `getMessageList()` and the join-aware `getTotal()` in `core/classes/privat.php`; `getPrivatPick()` and the selection carried through the list, the pager, the row actions and the bulk route in `core/user.php`; the toolbar and the chip bar in `partials/privat-page.html`, the selection counter in `partials/privat-list.html`, the chip logic in `plugins/system/slaed.js`, the bar and chip styles in `theme.css`; the page parameter renamed to `pnum` so `cid` keeps its two meanings and gains no third; fifteen strings into six locales. Gates green. Parity holds inside every answer measured: 240/25 page 10 gives 15 rows and «показано 226–240», `pnum=99` clamps to the last page instead of breaking the pager. The chips keep the facets of the mailbox under every selection and a filter that hides everything leaves the shelves and the chip numbers untouched and renders the empty state. A literal `%` finds 11 messages where a wildcard would find 240, and `100%` finds the same 3 rows the raw statement does. Search with the by-counterpart sort answers 39 of 240. The pager, a bulk action and a row action all return to the same page, filter, search and sort. A self check ran the paths the first pass had missed — `:fold` in the saved box, both period placeholders beside the three of the search in one statement, the unread-first and by-counterpart sorts, a search over the outbox counterpart, and rejected sort and filter values — and found no defect; the empty selection now also says how many the filter hid |
| 5 | Compose form and recipient card | done, revised | A revision found the card promising room where the send was refused: it read the inbox while `checkQuota()` refused on the inbox **or** the saved folder. Reproduced on a recipient holding 10 of 250 received and 250 of 250 saved — green card, refused send. The first answer widened the card to both folders; the right answer was the other way round, because a stored row carries no saved flag and an arriving message can only land in the inbox, and because the code this module grew out of counted only `messin`. So `checkQuota()` lost its second condition, the card stayed on the inbox alone, the predicate row stayed at one call, and `aFullMailboxRefusesTheSend()` now asserts that a full saved folder accepts the message instead of refusing it. `_PRSENDOVER` needed no change: it names the inbox, and the inbox is now the only thing that can be true when it is shown. The rest of batch 5 as first shipped: The two decisions of Open closed first: the card shows a **grade** and not another member's numbers, and the rich answer is `rich=1` answering `{items, card}` in one round trip. `getUserCardData()` and the bounded rich branch of `getUserList()` in `core/system.php`; the compose chips, the eraser and `is_wipe` in `getPrivateMessageView()`; `card` on `getTplUserSearchInput()` and `card_attr` in `fragments/input.html`; generic `chip_tone`/`icon_name` in `fragments/span.html`; the card skeleton in `partials/privat-page.html` and `is_wipe` in `partials/privat-view.html`; `sl-pmf-mate` in `theme.css`; `setUserCard()` plus the 250 ms debounce in `global-func.js` and `setPrivatWipe()` plus the input event of the carrier in `slaed.js`; seven strings into six locales. Gates green: PHPStan clean, 818 tests, PHP-CS-Fixer 0 of 503. Real authenticated requests on fixtures of 10, 220 and 250 messages: the three grades answer `ok`/`warn`/`danger` with the chip in the ring's own colour, no per cent and no arc anywhere in the markup. The flat answer stays an unbounded array of 12 for the admin field that asks for it, whose markup carries no card hook; the rich answer of the same term is bounded at 10 and carries `card: null` until the name resolves exactly, and a form with no card sends. Every send outcome met its constant through the real route — sent, flood, quota of a full recipient, unknown recipient, empty title — refusals stored nothing and kept the typed fields while a success cleared the form and the card. The eraser emptied all three fields through the editor API. Four widths clean with no horizontal overflow. `error_php.log` and `error_sql.log` untouched by the runs; `error_site.log` only carries the stand's mail transport, which has no SMTP. A self check walked the paths the first pass had missed — the entry by `uname`, the reply state, three consecutive opens, the outbox, and a mailbox no setting bounds — and found one defect and one gap, both fixed and re-verified: `--sl-knob-tone` and `--sl-knob-size` were declared on the card instead of on the ring, and `sl-knob` declares both on its own element, so the ring stayed success-green at every grade and 46 px wide while only the chip changed colour; and an unmeasured mailbox was graded well rather than `none`. Ring, icon and chip now answer one tone in all three grades, the ring is the 36 px of the demo, and the unmeasured grade is the subtle tone with no chip. The card follows the reply recipient and the profile link, and one open costs one bounded name lookup and one count |
| 6 | Focus deck and forwarding | done | The unread argument and the snippet on `getRecentList()` in `core/classes/privat.php`; `getPrivatFocus()`, the forward branch of the compose view and the forward action of the reading pane in `core/user.php`; the new `fragments/privat-focus.html`; the deck slot in `partials/privat-page.html` and the forward button in `partials/privat-view.html`; the `sl-pmf-focus`/`sl-pmf-deck`/`sl-pmf-slot*` family in `theme.css`; `setPrivatReply()` in `slaed.js`; four strings into six locales; a deck scenario in the probe and `theDeckReadsTheUnreadOfBothReceivedBoxes()` in `PrivatClassTest`, which pins the predicate on the fixture message that is saved and unread at once — under the inbox predicate that test answers the wrong two ids. Two decisions taken first: the deck names the cabinet badge predicate and «Ещё N» sends the reader to `typ=1&stat=unread`, which is the closest existing selection and leaves the saved unread out by exactly the shelf badge that counts them. The summary carries one count and not the demo's two: «старше суток» matches no row of the predicate table and adding one is an amendment. Gates green: PHPStan clean, 818 tests, PHP-CS-Fixer 0 of 503. Real authenticated requests on a mailbox of 40 with nine unread, one of them saved: six slots, the saved one toned apart, «Ещё 3» to the unread inbox, and the two shelf badges 8 and 1 adding to the cabinet 9. Opening from the deck hits `setPrivateMessageRead` and the three other actions hit `updatePrivatBox`, the same routes the list row uses; a mark-read from the deck moved 7/1/8 to 6/1/7 while the deck stayed as drawn. Forwarding answered an empty recipient, «Fwd: » before the title and the body with no quote wrapper, and the carrier was gone after. Nothing unread renders no deck at all. Two defects the verification caught are fixed and re-verified: the folded content of the `details` was not clipped by the element that folds it, so the deck scrolled the whole page 380 px sideways at every width — `overflow: clip` does not stop it and `contain: paint` does; and an out-of-band row swap answered a page whose list does not hold that row, which logged `htmx:oobErrorNoTarget` — the row now travels back only where the caller stands on it, which also closes the same hazard on the deep link. A second self check against the demo found three more, all fixed and re-verified: the deck rendered in all four states where the demo draws it only in the inbox; an action taken in it carried no view state, so from page two under a search the reader landed on page one unfiltered; and a saved slot named its own mailbox, which would have swapped the saved folder under a reader looking at the inbox — every slot now names the inbox and the inbox action set gains `unsave` for it. Measured after: the deck renders 1/0/0/0 across inbox, outbox, saved and compose; a deck action keeps «показано 26–39», the sort and the pager on page two; unsave from a deck slot moved the shelf badges 9/1 to 10/0 while the cabinet badge stayed at 10 and the list did not move. Four widths clean with no horizontal scroll, the summary folds and keeps its focus ring under the containment; the three logs carry nothing from the runs but the stand's own mail transport |
| 8 | Cheap follow-ups after the revision | done | Five small things the revision turned up, all measured after the change and not only before it: the dead `sl-pmf-compose` left the send form, which carried a class no theme defines; the carrier announces a recipient only when it really changed, so three opens from one sender cost one card lookup instead of three; the send form says «интервал между отправками 60 секунд» in its footer, which is the only place an answering reader could learn it — the chips that carried it render in the compose state alone; the flat autocomplete answers at most 50 where it answered 1246 for a single letter, keeping its array shape; and the unread predicate moved into `getNewWhere()`, which the cabinet badge and the deck now both read instead of spelling it twice. Gates green; the badge, the deck and the tail still agree at 6 + 1 = 7 with «Ещё 1» |
| 9 | Zero quota blocked everything | done | Reported against the code: with `messin` at zero `checkQuota()` compared `count >= 0` and refused every send, and with `messsav` at zero `setMessageSaved()` refused every save, while `getBoxFill()`, both alerts, the shelf ring and the card all read zero as «no bound». `checkQuota()` and `setMessageSaved()` now test the bound before comparing, and the unbounded saved folder no longer pays for the two reads behind that comparison. Two scenarios added to the probe and asserted in `aFullMailboxRefusesTheSend()` and `theSavedQuotaBoundsWhatIsAdded()`; both were run against the unfixed code first and both failed there — `'quota'` where `'ok'` was due, and a message left unsaved — so they pin the defect and not just the fix. A sweep found seven readers of a mailbox limit and no third offender |
| 10 | Wording of the strings | done | Ten constants restyled across all six locales: they start with a capital and say the shortest true thing, because every one of them renders as a chip, a shelf note or a counter and never inside a sentence. `_PRBOXFULL`, `_PRBOXNEAR` and `_PRBOXROOM` drop «получателя», which the name above the card already says; `_PRLIMSUB`, `_PRLIMTO` and `_PRWAIT` lose the words a chip cannot afford; `_PRFREE`, `_PRSHOWN`, `_PRNOLIMIT` and the Polish `_PRFOCUSN` gain their capital. `_PRFWD` stops being the untranslated `Fwd` and becomes a noun each locale really uses — «Пересылка», `Forwarded`, `Weitergeleitet`, `Transfert`, `Przekazane», «Пересилання» — so a forwarded subject reads «Пересылка: …» the way a reply reads «Ответ: …». Rendered and read back off the page: the outbox shelf says «Без ограничения» standing alone, which is what the count being «0» leaves it doing; the counter says «Показано 1–25» with no search and «Найдено 39 из 39 · Показано 1–25» with one. A second pass shortened the three chips of the compose header to what a chip can carry — «Интервал 60 сек.», «Имя, до 25 символов», «Заголовок, до 100 символов» — and they now stand on one line at 1240 and 800 and on two at 390, where they wrapped before |
| 7 | Search index, only if measured | closed, no schema change | `EXPLAIN` of batch 4 on 1472 stored messages: the search reads `type=ref` on `in_box` and filters inside it, so it never scans the table but only the reader's own mailbox, which the quota bounds at 250. The count of the search takes the same access path with `eq_ref` on the users primary key. The by-counterpart sort adds `Using temporary; Using filesort` over that same mailbox. Nothing here asks for `FULLTEXT`, and its changed semantics would cost more than the scan it removes. Closed on that measurement, and the covering `(uidin, delin, saved, viewed)` is refused with it: the five shelf counts grow with one reader's mail and not with the table, while an index exists to be paid for by every write. `setup/sql/*` and `docs/VERSIONS.md` are untouched |
