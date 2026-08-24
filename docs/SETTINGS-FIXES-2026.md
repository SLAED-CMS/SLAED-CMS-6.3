# Settings Controls Fixes 2026

Follow-up plan for the colour mode rail and the panel settings window, landed in
commit `7e864723`. Everything here was found by auditing that commit, not by
running into a failure.

Status: planned, nothing implemented. Batch 1 is the only one that closes a
security hole; the rest are consolidation and polish and may be run in any order
after it. Update this line as batches land.

No line numbers anywhere in this document on purpose: every reference names the
function, the file, the class or the token it points at, and that name is what to
search for.

## What already holds

Written down so the next reader does not re-derive it, and does not undo it.

- **The site rail.** `getThemeModeSwitch()` hands the three modes with the one in
  force marked; `fragments/mode-switch.html` of the site draws them as a groove
  with a knob riding over the active cell. The knob is placed by `:has()` on that
  cell, so no state attribute is written from PHP. Do not reintroduce one.
- **The panel window.** `getAdminSettingsWindow()` builds three rows, each
  posting to a handler that already existed: the mode writes its cookie, the
  editor writes `_admins.editor` through `changeeditor()`, the language writes the
  session through the `newlang` reader in `setLang()`. No new storage was added
  and none is needed.
- **The frozen API.** `--sl-mode-width` and `--sl-mode-height` must stay declared
  in both themes. `ThemeContractTest::testAFrozenApiHasNotLostAName` fails if
  either disappears, and the reason is not the test: themes are copied from these
  two packages, and a lost name breaks every copy that reads it.
- **The divergence on record.** `fragments/mode-switch.html` differs between the
  themes on purpose, and the reason sits in `tools/ui-contract.php`. Unifying the
  two files means reconciling the key sets first.
- **Contrast measured.** Resting icons over the rail groove read 3.73:1 at the
  blue end and 5.59:1 at the dark end; the knob icon reads 12.9:1. These are the
  figures the current gradient was chosen for. A repaint has to re-measure.

## Batch 1 — the editor row posts without a token

**The finding.** `changeeditor()` in `admin/index.php` writes the session and
updates `_admins.editor` from a POST body, and checks no token at all. The mode
handler beside it does: `admin/index.php` guards it with `checkAdminPost('mode')`.

This predates the settings window — the sidebar editor block posted the same way
long before. The window did not create the hole, but it gave the handler a second
entry point, and `.rules/global.md` asks for a token on every state-changing POST.

**What to change.**

1. `changeeditor()` refuses the request unless `checkAdminPost('changeeditor')`
   answers true, the same shape the mode handler uses.
2. `getAdminEditorPick()` and the sidebar block in `getAdminInfo()` both carry the
   token in their form; the fragment that draws the row has to accept one.
3. `getPageToken()` already answers for a named scope — check whether
   `checkAdminPost()` needs the scope added to its allowlist in `core/system.php`,
   the way `mode` is listed there.

**Open question for the language row.** `newlang` is read by `setLang()` from
`req`, so it answers to a GET link as well as to the POST the window sends. The
flag strip of the plain panel still links to it. Adding a token would mean either
dropping those links or giving them one; decide before touching it, and do not
half-guard a handler that has two callers with different shapes.

**Verify.** `php vendor/bin/phpunit --filter SecurityValidationTest`; a manual
POST to `admin.php` with `op=changeeditor` and no token must change nothing, and
the same POST from the window must still work. Check `storage/logs/error_site.log`
after the run.

## Batch 2 — one row, one builder, one template

**The finding.** Three rows of one window are built three ways.

- `getAdminLanguagePick()` and `getAdminEditorPick()` have the same shape: build a
  select, wrap it in `fragments/settings-row.html`. They differ in the `op`, the
  icon, the caption and where the options come from.
- The mode row does not use that fragment at all. `fragments/mode-switch.html` of
  the panel repeats the whole construction — the form, `.sl-set-name` with its
  icon, `.sl-set-pick` with a select — and builds the options with a `{% for %}`
  of its own.
- Hidden fields arrive two ways in the same window: the mode row gets them as
  `{{{ hidden }}}` assembled by PHP through `fragments/hidden.html`, while
  `settings-row.html` writes `<input type="hidden">` inline.

**What to change.**

1. One builder takes the axis and returns the row: the caption, the icon, the
   `op`, and the options already rendered through `fragments/select.html` and
   `fragments/select-option.html`. `getAdminLanguagePick()` and
   `getAdminEditorPick()` collapse into it.
2. `getThemeModeSwitch()` keeps handing `modes` for the site rail, and the panel
   fragment stops drawing a row of its own. Two ways to reach that: hand the
   panel a rendered `pick_html` beside `modes`, or let the panel fragment include
   `settings-row.html`. The first costs the site a select it never draws; the
   second needs the template engine to pass a rendered list into an include.
   Measure both before choosing.
3. Hidden fields settle on one form. The fragment already exists; the inline
   inputs are the odd ones.

**Verify.** `npm run ui:gates`; the window still opens with three rows, the
selects still measure equal, and all three still apply. `--cross` must stay at
zero divergent canon templates without a reason.

## Batch 3 — the frozen tokens have no honest role

**The finding.** `--sl-mode-width` and `--sl-mode-height` cannot be deleted from
the panel, but the panel has no mode control any more. To keep them read, the
commit gave them one:

- `--sl-mode-width: var(--sl-set-width)` — a token pointing at a token with a
  single consumer, which `.rules/theme.md` names as forbidden.
- `.sl-mode` survives in the panel theme as two rules whose only purpose is to
  spend those tokens on the mode row.

**What to change.** Decide which reading of the freeze is right — the test asks
only that the name stays *declared*, not that it stays *spent*. If a declared and
unread token is acceptable, both the alias and the `.sl-mode` rules go, and the
two names keep their old values in the API block with a comment saying why they
are there. If it is not acceptable, the alias has to become a real value rather
than a pointer.

**Verify.** `php tools/ui-audit.php --theme=admin` — watch `dead`, `single` and
`alias`; `php vendor/bin/phpunit --filter ThemeContract`. The audit answer decides
this batch, so run it before writing anything.

## Batch 4 — the editor is offered twice

**The finding.** The sidebar block from `getAdminInfo()` and the settings window
both write `_admins.editor`. They stay in step because they write one field, and
this was left in place on purpose: the sidebar block is where the mechanism came
from and nobody asked for it to go.

**What to change.** One of two, and it is a product decision rather than a code
one: either the sidebar block goes and the window is the only place, or both stay
and the duplication is written down here as deliberate. Do not leave it undecided
a second time.

**Verify.** Change the editor in whichever place survives, reload, and confirm
`_admins.editor` and the session agree.

## Batch 5 — the panel loses two settings without JavaScript

**The finding.** Measured with scripting off: the site rail works completely — its
three cells are real submit buttons and the POST goes through. The panel does not:
`<dialog>` cannot open without a script, so the mode and the language become
unreachable. Before the window, the mode button was a plain submit and worked.

The editor was already in this state — the sidebar select has always submitted on
`change` — and the panel needs a script for its fans, its windows and its htmx
lists anyway. So this is a degradation inside an already scripted surface, not a
new dependency for the tree.

**What to change.** Decide whether the panel owes anything to a scriptless
client. If it does, the settings need a plain page or a `<details>` fallback
behind the same three forms. If it does not, write that decision here and close
the batch.

**Verify.** A pass with `javaScriptEnabled: false` over `admin.php`, listing what
is reachable.

## Batch 6 — two category tones are thin under a white icon

**The finding.** The social row now takes its hover ground from the category
palette. On four of the six tones a white icon reads 4.7:1 to 7.3:1. On two it
does not: `--sl-warning` gives about 2.6:1 and `--sl-info` about 2.9:1.

This is not a regression — the RSS link sat on `--sl-warning-subtle` and the X
link on `--sl-info` before the rotation — and it is a hover state. But it is below
the 3:1 a non-text control is held to.

**What to change.** Do not repaint the category palette: it is spent by category
tiles, crumbs and the account rail, and a change there reaches all of them.
Either the icon darkens on the light tones, or the two tones are skipped in the
rotation for this row.

**Verify.** Compute the ratio for all eight links against their tone, in both
colour modes; the registry in `tools/ui-contrast.json` covers declared pairs only
and will not see this one.

## Batch 7 — two loose ends from the landing

- **`overflow: hidden` on `.sl-toolbar`.** It clips anything dropped from the
  toolbar row. The window does not care — a dialog draws in the top layer — so it
  was left alone. Any later control that opens downwards in that row needs it
  lifted, and the corner survives without it because the gradient is painted on
  the bar and not on its content.
- **The screenshot pair.** `npm run ui:before` / `ui:after` was run around the
  main landing, but not around the three corrections that followed it, nor around
  the toolbar work. The current tree has no clean pair under it. Run one before
  the next batch so the comparison means something.

## Not doing, and why

- **Renaming `.sl-set-*`.** The component `set` is declared in
  `tools/ui-contract.php` and the names are spent by the window and the toolbar
  item. Renaming crosses the contract, the baseline and both themes for no gain.
- **Unifying `fragments/mode-switch.html` across the themes.** The two files
  answer two different questions — a rail on a band, a row in a window — and the
  reason is on record. Unifying them means one theme drawing markup it does not
  style.
- **Repainting the category palette.** See batch 6.
