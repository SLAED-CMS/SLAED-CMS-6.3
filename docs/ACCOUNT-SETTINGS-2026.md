# Account Settings 2026

Work plan for the redesign of the account settings page,
`index.php?name=account&op=edithome`, after the stand variant
`demo/set-10-bridge.html` — "the bridge". Four tabs, each hiding an independent
POST endpoint, become one page of six sections that is read top to bottom and
saved with one button. The point is not the tiles. The point is that settings
stop being four forms pretending to be one page.

Status: planned, nothing implemented.

No line numbers anywhere in this document on purpose: every reference names the
file, the function, the fragment or the config key it points at, and that name
is what to search for. By the time a batch runs, a line number would have
drifted; a name will not.

## Where this sits in the queue

Three plans run back to back, in a fixed order:

1. `docs/FORM-FIELDS-2026.md` — the form row standard and its accessibility;
2. **this one**;
3. `docs/UPLOAD-FIELD-2026.md` — the file manager as a form field.

Each plan is committed when it is finished, not batch by batch. So this work
starts from a clean tree, accumulates eight batches of uncommitted changes
across eight sessions, and hands a clean tree to the third plan.

**What the first one hands over, finished.** By the time this work starts the
tree already has: `getFieldIds()` in `core/helpers.php` returning the triple
`input` / `label` / `hint` from one per-request counter; the `hint_html` row key
and the hint that has moved out of the caption; `role="group"` with
`aria-labelledby` on `.sl-radio-group`; the `.sl-value-row` / `.sl-value-label`
/ `.sl-value-text` vocabulary in place of a `<label>` with no control; the row
fold rewritten as a `@container` query; and `npm run ui:label` with the tracked
baseline `tools/label-audit-baseline.json`.

None of that is rebuilt here and none of it is worked around. **New markup is
born on that contract**: every caption id comes from `getFieldIds()`, every
radio group carries a name, no `<label>` is emitted without a control, and no
new fold is written against the viewport.

**What is left to the third one.** The avatar upload row keeps
`fragments/file-input.html` exactly as it is. The avatar tile is built so that
batch 6 of that plan replaces one row inside it — the input becomes a button
with a chip — and touches nothing around it.

**What this work invalidates in the third plan, and must be corrected there.**
Its batch 6 states that the preset gallery "keeps its own row and its own form".
After this work the gallery has no form of its own: 127 micro-forms collapse
into one radio field inside the shared form. And its batch 0 fixes
`'input_id' => 'f-userfile'`, which currently sits in the `form-field-row`
arguments instead of the `file-input` arguments — that call is rewritten here in
full, the finding closes by itself, and only the `sl-field-auto` class miss is
left for that batch.

## Read these before the first edit

In this order, and all of them:

- `demo/set-10-bridge.html` — the leader. The `<style>` block is a design
  document: read the comments, they carry the reasoning behind each part.
- `demo/README.md`, sections about the settings series and the six `data-demo-*`
  behaviours — three of them become real behaviours here.
- `templates/lite/partials/account-profile.html` — the structural precedent this
  page copies: PHP hands over nested data, the template owns markup and classes.
- `docs/FORM-FIELDS-2026.md`, the sections "Who owns the id" and batches 5 and 6.
- `docs/TEMPLATES.md` for the fragment contract, `.rules/theme.md` before
  touching CSS, `.rules/constants.md` before adding a single caption.

## The baseline this starts from

Half of the leader is already written. It has to be reassembled, not invented.

| Layer | Where it lives | State |
|---|---|---|
| Progress ring | `.sl-knob` + `.sl-cab-ring`, drawn by `partials/account-home.html` | complete, including `sl-knob-full` and the arc length passed from the template |
| Data-carrying custom properties | `--sl-d-level`, `--sl-d-ring`, set inline by the template | the naming precedent for `--sl-d-*` |
| Cabinet menu | `getUserNav()` → `fragments/account-nav.html`, `.sl-cab-nav` | complete, the leader uses it unchanged |
| Yes/no switch | `getTplRadioGroup()`, `.sl-radio-group.sl-radio-switch` | complete |
| OAuth list | `partials/account-oauth-links.html`, `.sl-oauth-*` | complete, the leader uses it unchanged |
| A page built from nested structures | `partials/account-profile.html` — a loop of panels, a loop of rows inside | the form this page follows |
| A form carrying a file | `partials/form-add.html` — `enctype="multipart/form-data"` is already the default | complete, no change needed |
| Avatar upload | `getUploadService()->addUploadedFile()`, `getUploadFailText()` | complete |
| Tile tones | `sl-cat-tone-0…5`, `--sl-cat-tone` | complete |

The measurement the whole composition rests on: `--sl-layout-container` is
1320px and `--sl-layout-sidebar` is 400px, so the content column on a page with
one side column is about 896px. That is why the leader lays its rail across the
top instead of down the left — a 300px rail would leave the form 556px.

## What the stand does not show honestly

Five things. Each of them is a place where the implementation will not look like
the picture, and knowing that in advance is cheaper than discovering it.

**Two of those textareas are editors.** `sig` and `block` go through
`getTplTextarea()`, which calls `Editor::getContent()` and mounts the configured
editor. **Four drivers, not five**: `plain`, `toastui`, `ckeditor` and `tinymce`
implement `ContentDriver` and are the only ones this path can reach; the fifth
directory, `codemirror`, is a different interface and does not appear here —
the first plan makes the same distinction and it is easy to get wrong. The stand draws two
plain `<textarea>` elements. In the real page two full editors mount on one
page, with `rows` 5 and 10, their own toolbars and their own upload rule from
`getUploadRuleData()`. Plan the tiles that hold them for an editor's height, not
a textarea's, and check the page weight with two instances mounted.

**Both of those editors carry a popover hint today.** `getTplTitleTip(_SIGNATURE_TEXT)`
and `getTplTitleTip(_MENUINFO)` are prepended to the field. On the stand they are
plain `<small>` under the question. Move both onto `hint_html` from the first
plan. This is not a licence to convert the other eighteen `getTplTitleTip()`
call sites in the tree — `sl-tip` is recorded as unfixed there, and it stays
recorded.

**Half the rows are conditional.** `story` renders only when
`$conf['users']['news'] == 1`, `fsmail` only when `is_active('forum')`, `psmail`
only when `$conf['privat']['act']`, the theme row only when more than one theme
passes `checkThemeAssets()`, the points fact only when `$conf['users']['point']`,
the avatar upload row only when `$conf['users']['aupload']`, extra fields number
between zero and ten from `config/fields.php`, and the whole password section
disappears when the password starts with `!` — an OAuth-only account, which gets
a "recover the password" link instead. A section with nothing in it must not
render at all, and the rail must count real sections rather than six.

**The log will be shorter than the stand's.** Six entries there, three or four
here. See the decisions below.

**The preset gallery is five times bigger than the stand's.** The stand draws
twenty-four thumbnails, because `data-demo-presets="24"` is a stand number.
`templates/lite/images/avatars/presets` holds **127 files** — counted, not
estimated — and `edithome()` renders every one of them, six per table row, each
inside its own `<form>`. So the page today carries 128 tokens and 127 images,
and the field this work turns them into is a radio group of 127 options. That
changes three things at once: the tile cannot be a plain grid of everything at
full size, a group of 127 radios needs a name and a sane reading order for a
screen reader, and 127 images want `loading="lazy"`. Decide the tile's shape
against 127, never against the picture.

## Six decisions taken before the first edit

**One form for everything except two things.** Profile, mail, privacy, extra
fields, avatar upload and preset pick all travel in one POST to `savehome`. The
form is already `multipart/form-data` by default, so it carries the file itself.
The `saveavatar` route disappears.

**The password keeps its own form.** The single exception, and it is taken for
the error contract: a member who has filled fifteen fields and mistyped the old
password must neither lose the input nor be told "saved" about half the page.
The password form lives inside its own tile in the keys section, with its own
button and its own `savepass`. OAuth unlink stays a `post-button` for the same
reason — it is an action, not a setting.

**The preset gallery is a field, not 127 forms.** Today every thumbnail is its
own `<form class="sl-avatar-link">` carrying hidden `op`, `avatar` and a token,
rendered through `content-list` with `is_avatar_grid`, six to a table row.
Inside a shared form that is impossible, and it is also unnecessary: the preset
becomes a radio field named `avatar` whose label is the image. The page carries
one token instead of 128.

**The log is assembled from what exists.** There is no history, and the columns
are not the ones they look like. `_users` has `regdate` and **`lastvis`** — and
`lastvis` is last *activity*, not last login: `core/system.php` refreshes it on
any request, throttled to once per 60 seconds. There is no `lastlog` in
`_users`; `lastlog` is a column of **`_user_oauth`**, and it means the last sign-in
through that provider. So the entries are: registration from `regdate`, last
visit from `lastvis` — captioned as a visit, never as a login — and per linked
provider both `linked` and `lastlog` from `Oauth::getLinks()`. This work adds no
table and no column.

**The password change no longer logs the member out.** Today `savepass()`
redirects to the login page on success, and that is not a courtesy but a
consequence: the `account` cookie carries the password hash, after the change it
no longer matches and the session is dead. From now on `savepass()` rewrites the
cookie itself with the new hash and returns to the settings page.

**The new class prefix is `sl-opt-*`.** The obvious choice, `sl-cab-*`, does not
work: `sl-cab-rail` is the left column of the cabinet card, and `sl-cab-row`,
`sl-cab-main` and `sl-cab-deck` belong to the same card. `sl-set-*` is taken too,
by `templates/admin` under a different meaning. `sl-opt-*` is free in both themes
— verified by search.

### What follows from them

The shared form carries **two mutually exclusive avatar outcomes**, and the
handler has to arbitrate:

| What arrived | What the handler does |
|---|---|
| `avatar`, a preset filename | validates it against the theme preset directory, stores `presets/<file>` |
| a really uploaded `$_FILES['userfile']` | hands it to `getUploadService()->addUploadedFile()`, stores the filename |
| both | the preset wins: it was picked by a click, while the file may be left over from an earlier attempt |
| neither | **the avatar is not touched and the upload service is not called** |

The rule is assembled from `$conf['users']` — `atypefile`, `amaxsize`, `awidth`,
`aheight`, `adirectory`, `maxfiles` fixed at 1 — exactly as `saveavatar()`
assembles it today.

**The fourth row is not what the current code does, and this is the trap that
would break every save.** `saveavatar()` branches `if ($avatar) … elseif
($conf['users']['aupload'])`, so an empty preset falls straight into the upload
branch with an empty `$_FILES`. Today that is harmless — the route is only
reached by the avatar form. Merged into `savehome()` it means every ordinary
profile save with no file attached goes to `addUploadedFile([])`, where
`checkUploadInput()` returns `missing`, and the member is told a file was not
selected while saving their e-mail. Three explicit branches, and the third one
does nothing: preset / a file that was offered / neither.

**"Offered" means `error !== UPLOAD_ERR_NO_FILE`, not `error === UPLOAD_ERR_OK`.**
Testing for `OK` looks safer and silently swallows every real failure:
`UPLOAD_ERR_INI_SIZE`, `UPLOAD_ERR_FORM_SIZE` and `UPLOAD_ERR_PARTIAL` would all
land in the do-nothing branch, and a member whose 5 MB avatar was cut off by
`upload_max_filesize` would be told the profile was saved and never learn the
picture was dropped. `checkUploadInput()` already turns each of those into a
`size` or `transfer` code that `getUploadFailText()` puts into words — so hand
them to the service and let it speak. Only `UPLOAD_ERR_NO_FILE` means nothing
was offered, and a missing `$_FILES['userfile']` key defaults to exactly that.

**An avatar failure does not roll back the profile.** The profile is one
`UPDATE`, the avatar is a second one. If the upload is rejected by size or
dimensions, the profile is still saved and the rejection message is addressed to
the avatar section. Losing fifteen fields over a 60 KB image is the worse
outcome.

**The cookie rewrite has a shape to copy.** `savehome()` already does it:
`setCookies('account', time() + (int)$conf['user_c_t'], [$uid, $name, $pass, $story, $blockon, $theme])`.
`setCookies()` keeps at most six elements — `array_slice($value, 0, 6)` — so the
password branch must read `storynum`, `blockon` and `theme` for the current user
before writing, not pass a short array.

## Three findings fixed on the way

**The new password is mailed in clear text.** `savepass()` interpolates the
plaintext password into `_PASSESEND` through `sprintf()` before hashing it, as
the fourth of five arguments. The constant has exactly one call site. Batch 0
rewrites it into a "your password was changed" notice with no password in it,
and drops the argument.

**`_PASSTEXT` promises something that will stop being true.** It tells the
member the session will be dropped and they must sign in again. After the cookie
decision that is false. It is rewritten in the same six locales.

**`is_avatar_link` becomes dead.** The branch in `fragments/table-row.html`, the
`.sl-avatar-link` and `.sl-avatar-grid` rules in the lite theme and the
`is_avatar_grid` flag in `fragments/table.html` have exactly one caller — the
preset gallery, verified by a tree-wide search. After batch 1 they have none,
and batch 7 removes them.

## The vocabulary this work introduces

Everything new is listed here, because `.rules/global.md` requires a new class,
fragment, template or function to be agreed before it is written. This document
is that agreement; anything **not** on this list is a new decision and needs one
of its own.

**Template.** One: `templates/lite/partials/account-settings.html`. No new
fragment — the page composes existing ones.

**CSS classes**, all in `templates/lite/assets/css/theme.css`:
`sl-opt-sec`, `sl-opt-grid`, `sl-opt-tile`, `sl-opt-tile-head`, `sl-opt-fields`,
`sl-opt-lines`, `sl-opt-line`, `sl-opt-line-wide`, `sl-opt-face`, `sl-opt-facts`,
`sl-opt-rail`, `sl-opt-mark`, `sl-opt-lamps`, `sl-opt-lamp`, `sl-opt-log`,
`sl-opt-save`, plus four tile widths. Tile tones reuse `sl-cat-tone-0…5`; a
read-only value reuses `.sl-value-row` from the first plan.

**Two more, and they are not `sl-opt-`.** The lamps need semantic tones, and the
`sl-is-*` family has exactly one — `sl-is-warn`. `sl-is-ok` and `sl-is-info` do
not exist, verified by search. They are added beside `sl-is-warn` as **general
state modifiers**, not under the `sl-opt-` prefix, because the family they join
is general and a second tone vocabulary for one page would be the zoo. Do not
reach for `sl-chip-success` or `sl-knob-ok` instead: those are the tone scales
of a chip and of a ring, and a lamp is neither.

**Custom properties carrying data**, set inline by the template or by JS:
`--sl-d-meter`, `--sl-d-step`. Named after the existing `--sl-d-level`.

**JavaScript behaviours**, all in `plugins/system/slaed.js`, no new file:
`data-sl-spy` with `data-sl-spy-mark`; `data-sl-meter` with `data-sl-meter-fill`,
`data-sl-meter-num`, `data-sl-meter-left`; `data-sl-dirty` with `data-sl-clean`.
Verified absent from the current `data-sl-*` inventory.

**PHP helpers.** Prefer keeping the assembly inside `modules/account/index.php`;
`edithome()` is already long and will get longer, so extract only where a name
earns its place, and follow `.rules/global.md` naming — verb prefix, camelCase,
6–24 characters. Candidates the batches below assume:
`getAccountLamps()`, `getProfileFillRate()`, `getAccountLog()`. Nothing goes into
`core/helpers.php`: none of this is used outside this module.

**Language constants.** Six section titles, four lamp captions, the meter, the
log and the save bar are roughly fifteen captions, and half of them do not
exist. Reused as they are: `_SECURITY`, `_SAVECHANGES`, `_AVATARSETUP`,
`_PASSSETUP`, `_OAUTHTAB`, `_PERSONALINFO`, `_CHANGE`. Missing entirely:
publications and mail, privacy and appearance, profile completeness, fields
left, change log, unsaved changes, cancel. New ones go into
`modules/account/lang/en.php` first and into the five other locales **in the
same commit**, per `.rules/constants.md` — and search for an existing global
before inventing one: a scoped duplicate of `_SECURITY` is forbidden.
`LanguageValidationTest` guards this and is **not** part of `ui:gates`; it needs
a full `phpunit` run.

## The shape of the work

Eight batches, in order. Each ends green on its own. The order is not negotiable
in two places: batch 1 must land before batch 2, or markup is built against a
handler that does not exist yet; batches 2 and 3 must land before batches 4 and
5, or the JavaScript looks for nodes that are not there.

**Nothing is committed until the last batch is done.** The commit happens once,
at the end of this plan, and the three plans are committed the same way — the
first plan is already in history by the time this one starts, and the third one
starts from a clean tree this one leaves behind.

| # | What | Moves pixels |
|---|---|---|
| 0 | The password: fields, order, mail, session, plus the rig route | slightly |
| 1 | One form, one handler, three sibling forms, tabs gone | yes |
| 2 | Six sections and tiles: the new partial | yes |
| 3 | The section rail and the scroll spy | yes |
| 4 | The state lamps and the completeness meter | yes |
| 5 | The save bar | yes |
| 6 | The change log | yes |
| 7 | Cleanup: the dead gallery, the reference, this document | no |

### The verification matrix

Inherited from `docs/FORM-FIELDS-2026.md` and not softened. Every batch that
stages a file under `templates/*/fragments/`, `templates/*/partials/` or a theme
CSS file takes its own screenshot pair, and `npm run ui:before` runs **before
the first edit of that batch** — a baseline cannot be reconstructed afterwards.

| Batch | `php -l` | phpstan | phpunit | cs-fixer | `ui:gates` | `ui:before`/`after` | `ui:label` |
|---|---|---|---|---|---|---|---|
| 0 | yes | yes | yes | yes | yes | yes | yes |
| 1 | yes | yes | yes | yes | yes | yes | yes |
| 2 | yes | yes | yes | yes | yes | yes | yes |
| 3 | yes | yes | yes | yes | yes | yes | yes |
| 4 | yes | yes | yes | yes | yes | yes | yes |
| 5 | yes | yes | yes | yes | yes | yes | yes |
| 6 | yes | yes | yes | yes | yes | yes | yes |
| 7 | yes | yes | yes | yes | yes | — | yes |

`tools/label-audit-baseline.json` must not grow in any batch. phpstan runs over
`core` and `modules` only, which does cover this module — unlike an admin change,
a clean run here means something.

Every batch that changes state through the browser ends by reading
`storage/logs/error_php.log`, `storage/logs/error_sql.log` and
`storage/logs/error_site.log`, per `.rules/global.md`.

### One batch, one window

Every batch runs in a fresh session with no memory of the previous one. Four
consequences, all of them practical:

- **`git status` is not a progress report.** With no commit between batches, the
  working tree carries everything done so far, and a diff cannot tell batch 4's
  work from batch 2's. The log below is the only record of where the work
  stands — **update it as the last action of the batch**, before reporting.
- **The commit gate never fires on its own.** `tools/hooks/pre-commit` runs on
  commit, and there is no commit until the end. Run `npm run ui:gates` by hand
  at the end of every batch; nothing else will.
- **The screenshot pair opens and closes inside one batch.** `npm run ui:before`
  before the first edit, `npm run ui:after` after the last one, same session. A
  baseline left dangling into the next window is a baseline for the wrong thing.
- **Read the plan, then read the tree.** The first batch is starting from a
  clean tree; every later one starts from a tree already carrying the earlier
  batches. Confirm the previous batch actually landed — the log says what it was
  and the section below says what it should have produced — before adding to it.

### Progress log

Filled in as the work goes. `—` means not started.

| # | Batch | State | Note |
|---|---|---|---|
| 0 | Password: fields, order, mail, session, rig route | — | |
| 1 | One form, one handler, tabs gone | — | |
| 2 | Six sections and tiles | — | |
| 3 | Rail and spy | — | |
| 4 | Lamps and meter | — | |
| 5 | Save bar | — | |
| 6 | Log | — | |
| 7 | Cleanup | — | |

### The commit at the end

One logical change set, but not necessarily one commit: `.rules/git.md` asks for
a split when changes span separate topics, and batch 0 is a security fix that
happens to sit in the same file as a redesign. Two commits, in order — `Fix` for
batch 0, then `Feature` for batches 1 to 7 — say the truth better than one does.
Build both messages from `.gitmessage`, stage the whole tree, and commit only
when the user asks for it.

---

## Batch 0 — the password, and the route the rig cannot see

Not a redesign batch. It is first because it is security, because it is worth
having even if everything after it stalls, and because the screenshot rig cannot
photograph this page until it ends.

### Fix

**The password fields are `type="text"` today.** All three calls —
`newpass`, `newpass2`, `oldpass` — pass no `itype` to `fragments/input.html`,
which defaults to `text`, so every character is on screen and every password
manager is guessing. Add `'itype' => 'password'` to all three, and the
`autocomplete_attr` the fragment already supports: `new-password` on the first
two, `current-password` on the third.

**`savepass()` does its work in the wrong order.** Today it queues the mail,
then hashes, then writes, and never looks at the result of the `UPDATE`. A
failed write therefore still sends "your password was changed".

The `UPDATE` is the only gate. Nothing happens before it succeeds; **everything
happens after it succeeds**:

- write fails → no mail, no cookie, an error, back to the page;
- write succeeds → queue the mail, rewrite the cookie, redirect.

**The cookie does not depend on the mail.** `addQueue()` returns `bool` and
refuses on a rejected recipient, a rejected sender, an over-long subject, a
missing database connection or a failed insert. Chaining the cookie behind it —
"each step only after its predecessor" — means a rejected queue row logs the
member out of a password that has *already changed* in the database. The queue
failure is its own outcome: report it, let `Mailer` log it, and carry on with
the cookie and the redirect regardless.

**The mail carries the password.** `_PASSESEND` becomes a notice that the
password was changed, carrying the nickname, the site name and the login link,
and **not** the password. The `sprintf()` call loses its fourth argument and the
placeholders renumber — check every locale for `%4$s`, they are positional.

**The session dies on success.** Instead of
`setRedirect('index.php?name='.$conf['name'])`: read `storynum`, `blockon` and
`theme` for the current user, call `setCookies()` with the same six-element
shape `savehome()` uses and the **new** hash, then redirect to `op=edithome`
with a success message. `_PASSTEXT` is rewritten to describe that.

Both constants exist only in `modules/account/lang/`; `en.php` first, then the
other five in the same commit.

**The rig has never seen this page.** `tools/ui-shots.json` lists
`name=account`, `op=profile` and `op=privat` under `auth: "site"`, and no
`op=edithome`. Every screenshot pair in every batch below is worthless until the
route is in the manifest, so it goes in here, before the first `ui:before` of
the whole plan: one entry, `auth: "site"`, following the shape of the `profile`
line beside it. Check the `mask` list while there — the page shows an IP and a
registration date, and `.sl-cab-ring b` and `time` are already masked for
exactly that reason.

### Traps

- `$user[2]` is the hash the session compares against. If the cookie is written
  with the old hash, or with fewer than six elements, the member is silently
  logged out on the next request and it will look like an unrelated bug.
- `getCookies()` filters to `[a-zA-Z0-9_-]` and the payload is base64, so it is
  read directly from `$_COOKIE` with the `user_c` prefix elsewhere in the tree.
  Do not "fix" that while passing through.

### Verification

The full matrix. `npm run ui:before` **after** the manifest entry lands and
before the field-type edit, so the pair covers the page this plan is about — and
that first baseline is the reference every later batch is read against.

Live, over real HTTP: the three fields render as dots; change the password and
confirm the settings page opens as the same member; read the queued mail and
confirm the body carries no password; submit a wrong old password and get
`_ERROROLD`; submit mismatched new passwords and get `_ERROR_PASS`; submit a
password shorter than `$conf['users']['minpass']` — and in each failing case
confirm **no mail was queued and the password is unchanged**. Then force a queue
refusal on an otherwise valid change — an unreachable queue is enough — and
confirm the member stays signed in with the new password: that is the case the
naive ordering breaks. Then the three logs.

---

## Batch 1 — one form and one handler

### Fix

`edithome()` stops building three forms.

**The tabs go in this batch, not in batch 2.** The earlier draft of this plan
kept them and wrapped one `form-add` around the tab component. That produces
nested forms and it cannot work: the password form and the OAuth unlink
`post-button` live inside tab panels, so wrapping the tabs puts a `<form>`
inside a `<form>`, which the HTML parser silently drops — the inner form
disappears and its buttons start submitting the outer one. Whatever the layout
looks like at the end of this batch, the three forms must already be siblings:
the shared form, the password form, the unlink buttons. So `getNaviTabs()` leaves
`edithome()` here — plain stacked sections are enough for now, and batch 2 gives
them their shape.

The preset gallery stops being a `content-list` of 127 forms and becomes a radio
field named `avatar`, the image acting as each option's label — 127 options, and
the tile has to survive that count from the first commit, not from batch 2. The
upload row keeps `fragments/file-input.html` untouched, for the third plan.

`savehome()` takes over the avatar, with the **three explicit branches** from
the decisions above — preset, a file that was offered at all, or neither, where
neither is `UPLOAD_ERR_NO_FILE` alone and is the only case in which the upload
service is not called. This is the one place where
`saveavatar()` is rewritten rather than moved; everything else about it moves as
it is, including the failure path that deletes the stored file through
`deleteStoredFile()` when the DB write fails and logs through
`Logger::addFile()`. `case 'saveavatar'` leaves `switch ($op)` together with the
function.

Errors stop being one list at the top: each `$stop` entry gains the id of the
section it belongs to, so batch 2 can turn it into a link. Until batch 2 this is
invisible and is therefore verified by reading the code, not the screen.

### Traps

- `savehome()` re-checks the session against the database — id, name and
  password hash — before writing anything, and silently falls through to
  `edithome()` when they disagree. Keep that guard; it is the only thing
  standing between a stale cookie and a write.
- The profile `UPDATE` writes seventeen columns in one statement. Adding the
  avatar to it is tempting and wrong — it belongs to the second `UPDATE`, so a
  rejected upload cannot poison the profile write.
- `getVar()` types are not decoration: `mail` is `text` and goes through
  `checkemail()`, `site` is `url`, the numeric switches are `num`, `user_birthday`
  is `date` and is read from `req` rather than `post`, and `field` has its own
  `field` type. Copy them exactly.
- `checkEditorTextRoom($sig, 'users.sig')` and the same for `users.block` guard
  the editor payload size. They stay.

### Verification

The full matrix plus its own screenshot pair. Live and exhaustively, because
this is the batch that can quietly drop a field: save every profile field and
re-read the page; **save the profile with no file and no preset and confirm no
upload error appears**; pick a preset; upload an avatar; upload a rejected
avatar and read `getUploadFailText()`; **upload a file larger than
`upload_max_filesize` and confirm the member is told so** rather than being
shown a clean save — that is the case an `UPLOAD_ERR_OK` test would swallow; change the theme and confirm the cookie
was rewritten; submit with `mail` empty. Then read the rendered DOM and confirm
there is not one `<form>` inside another. Then the three logs, `error_sql.log`
first.

---

## Batch 2 — six sections and the tiles

### Fix

`templates/lite/partials/account-settings.html` appears — the whole page, built
the way `partials/account-profile.html` is built: PHP hands over a structure,
the template owns the markup and every class name. The stacked sections batch 1
left behind get their structure, their tiles and their CSS here.

The structure PHP passes:

```
sections[] → { id, icon, title, tiles[] }
tiles[]    → { icon, title, width, tone, text, rows_html | lines[] | fields[] }
lines[]    → { label, hint, control_html, is_wide }
fields[]   → { label_html, hint_html, control_html, is_span }
```

`width` is the number 2, 3, 4 or 6 and the template maps it to a class — the
precedent for interpolating a class suffix from data is `sl-chip-{{ grp.tone }}`
in `account-profile.html`. `icon` is a bootstrap-icons name, as `panel.icon`
already is there. **No CSS class name arrives from PHP.**

Six sections: personal data, avatar, publications and mail, privacy and
appearance, keys, external services. An empty section does not render at all —
see the conditional list above, it is longer than it looks.

Every caption id comes from `getFieldIds()`. Every radio group is passed a
`labelledby`. The two editor rows carry their hint through `hint_html` instead
of `getTplTitleTip()`.

The fold inside a tile is a `@container` query and nothing else. A two-column
tile inside 896px is about 290px wide, and a caption-left row does not live
there; the viewport width knows nothing about it. Take the idiom from batch 6 of
the first plan rather than inventing a second one.

### Traps

- `container-type: inline-size` brings layout containment, which makes the box a
  containing block for absolutely positioned descendants. The two editors mount
  toolbars and floating panels inside these tiles. Check an open editor toolbar,
  the `sl-tip` popover and the `sl-dial` fan before declaring the batch done —
  the dial has known specificity fragility.
- Two editors on one page means two mounts, two upload rules and DOM ids `1` and
  `2` — `id="1"` is valid HTML5 and unreachable by `#1` in CSS or
  `querySelector`. The first plan records this and works around it; do the same,
  do not try to fix it here.
- The three forms became siblings in batch 1, and the new partial must keep them
  that way. A tile is a box inside a section, and the shared form has to close
  before the keys section opens — which means the partial cannot simply wrap
  everything it renders. Verify in the rendered DOM, not in the template source:
  nested forms fail silently, the inner one is dropped by the parser and its
  button starts submitting the outer one.

### Verification

The full matrix, its own pair at all four widths and both colour modes.
`ui:label`: the baseline has not grown — the new markup must not introduce a
single `<label>` without a control or a group without a name. Live: a member
with no forum, no private messages, one theme and no extra fields — the page
must stay coherent rather than full of holes. And an OAuth-only member, whose
keys section is a recovery link instead of a form.

---

## Batch 3 — the rail and the spy

### Fix

The section rail goes across the top, above the tiles: `sl-opt-rail`,
`sl-opt-mark`, sticky, hiding nothing. The number of marks equals the number of
sections that actually rendered, and the progress line is computed from that
number rather than from a literal — the stand hardcodes five.

One new behaviour in `plugins/system/slaed.js`: `data-sl-spy` on the rail,
`data-sl-spy-mark` on each button carrying the target section id. An
`IntersectionObserver` with the band in the upper fifth of the window, as on the
stand; a click scrolls to the section. The current position is written to
`--sl-d-step` on the rail, after `--sl-d-level`.

### Traps

- The rail is sticky and the site header exists on the real page, which the
  stand does not reproduce faithfully. Pick the `z` token deliberately and check
  the overlap at every width.
- The marks are `<button type="button">` inside a form. Without the explicit
  type they submit it.
- At 768 the labels are hidden and six marks share the width. Verify they stay
  large enough to tap; this is the first thing that breaks.

### Verification

The full matrix and its own pair. Live: scrolling from top to bottom marks the
sections in turn; clicking a mark moves to its section; a member with three
sections gets three marks and a progress line that reaches the end.

---

## Batch 4 — the lamps and the meter

### Fix

Lamps: four state tiles above the rail, `sl-opt-lamps` and `sl-opt-lamp`.
Everything they show is computed on the server from `getUserInfo()` and
`Oauth::getLinks()` — whether a real password exists (the `!` prefix says it
does not), whether the address is hidden, how many of the three mail
notifications are on, and how complete the profile is. Tone through `sl-is-warn`,
which exists, and `sl-is-ok` / `sl-is-info`, which this batch adds to that family
— see the vocabulary section.

Meter: the same `.sl-knob.sl-cab-ring` that `name=account` draws, with the arc
length passed from PHP exactly as `account-home.html` passes `level`.
Completeness is the share of non-empty profile fields; **the list of fields that
count is declared once, on the server**, and is not inferred from the markup.

The second new behaviour in `slaed.js` recomputes it on input:
`data-sl-meter` on the receiving node, `data-sl-meter-fill` on the counted
fields, `data-sl-meter-num` and `data-sl-meter-left` on the numbers. It writes
`--sl-d-meter` and toggles `sl-knob-full` at one hundred.

The server value and the value after the first keystroke must agree. That is the
main assertion of this batch: two implementations of one rule, and they will
drift the moment the field list is duplicated.

### Verification

The full matrix and its own pair. Live: open the page and check the number
against the drawn arc; clear a field and watch the number, the ring and the lamp
change together; fill everything and get a solid ring with no join.

---

## Batch 5 — the save bar

### Fix

`sl-opt-save`, a sticky bar at the bottom of the shared form that rises out of
nothing on the first edit. The third behaviour in `slaed.js`: `data-sl-dirty` on
the form, `data-sl-clean` on the discard button, the state carried as an
attribute on the scope so CSS can react to it.

The bar belongs to the shared form only. The password form has its own button
inside its tile, and the bar does not claim it — it would be promising to save
something it does not carry.

### Verification

The full matrix and its own pair. Live: no bar before an edit; the bar rises on
the first keystroke; discard removes it; saving with the bar visible behaves
exactly as saving without it. Separately, scroll to the bottom where the bar
meets the footer.

### Trap

`prefers-reduced-motion` is honoured elsewhere in this tree; the bar animates
and must honour it too.

---

## Batch 6 — the log

### Fix

`sl-opt-log`, in the keys section beside the password. Entries, and read the
column note in the decisions before writing any of them:

- registration — `_users.regdate`;
- last visit — `_users.lastvis`. **Captioned as a visit, not as a login.** It is
  refreshed on any request with a 60-second throttle, so for the member reading
  the page it is almost always "now"; that is honest for "last seen" and a lie
  for "last sign-in". If the caption cannot be made to read truthfully, drop the
  entry rather than mislabel it.
- per linked provider — `linked` and `lastlog` from `Oauth::getLinks()`, both
  columns of `_user_oauth`. `lastlog` is the last sign-in through that provider
  and is the only real login timestamp on this page.

Sorted newest first, dates through `format_time()` like everywhere else.

The password tile and the log tile share the row. With three or four entries
instead of the stand's six, the proportion between them is assigned again rather
than copied from the stand.

### Verification

The full matrix and its own pair. Live: a member with no links at all — two
entries, and the tile must not collapse; a member with three links, whose
`lastlog` values differ from their `linked` values. Confirm against the database
that every rendered timestamp is the column it claims to be.

---

## Batch 7 — cleanup

### Fix

Remove what has lost its last caller: the `is_avatar_link` branch in
`fragments/table-row.html`, the `is_avatar_grid` flag in `fragments/table.html`,
and the `.sl-avatar-grid` and `.sl-avatar-link` rules in the lite theme. Search
the whole tree first, `templates/admin` included.

What outlives this document goes to permanent reference: the settings page
contract — the `sections` / `tiles` structure, who owns tile width, why the
password sits outside the shared form — into `docs/TEMPLATES.md` beside the rest
of the fragment contract; the three new `data-sl-*` behaviours wherever the
existing ones are documented.

This document is deleted in the same batch.

### Verification

The full run: `php -l`, phpstan, phpunit, php-cs-fixer, `npm run ui:gates`,
`npm run ui:label`. Then a tree-wide search for `avatar-link`, `avatar-grid`,
`saveavatar` and `getNaviTabs` inside `modules/account` — every hit must be
explainable.

## Recorded, not fixed

- **The `account` cookie carries the password hash.** Six values base64-joined
  in one string, the hash among them. Batch 0 walks through this code and leaves
  it exactly as it is.
- **`getTplFieldsIn()` prints `input_attr` unescaped.** Extra fields are a whole
  section of the new page and the values come from admin configuration rather
  than from the visitor. Already recorded in `docs/FORM-FIELDS-2026.md`; noted
  here only so it is not mistaken for a new finding.
- **`sl-field--account` has no rule anywhere.** The `is_account` flag in
  `fragments/select.html` emits a class that exists in neither `base.css` nor
  `theme.css` of either theme. Either the rule is missing or the flag is — and
  deciding that needs to know what it was for, which nobody remembers.
- **`checkemail()` writes into the global `$stop` and also returns it.** Two
  contracts for one function. Not this work.

## Out of scope

- **The cabinet itself.** `name=account` with no `op` is its own page with its
  own card and its own ring. It lends this page the menu and the ring shape; it
  does not change.
- **What extra fields are.** Up to ten slots from `config/fields.php` with their
  own types and required flags. This page renders them; the admin screen that
  declares them is not touched.
- **Marking a required field before submission.** The natural sequel to the
  first plan and recorded there. The new markup neither blocks it nor delivers it.
- **The admin theme.** Settings exist only on the site side; there is no such
  page in `templates/admin` and the `sl-opt-*` vocabulary does not travel there.

## When this document dies

The last batch deletes this file along with itself.
