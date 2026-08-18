# Window Canon 2026

Work plan for replacing every dialog of the system with one window.

Status: done. Every batch landed and was verified in both themes, and this document is
now the description of what ships rather than a plan of what to do.
`demo/window-canon.html` stays as the reference page: it carries no styles and no script
of its own, so it can only show what the theme and the shared component actually do.
Batches 3 and 4 were run together: both windows are driven by the same five functions
in `editor-upload.js`, so splitting them would have meant writing a dual-mode version of
each and deleting it one batch later. Two items of batch 5 were closed by the batch that
made them dead: `.sl-modal-close` and `.sl-modal-wide` went with the gallery in batch 2,
and the three Escape handlers became one in batch 3.

No line numbers anywhere in this document on purpose: every reference names the
file, the selector, the attribute or the function it points at, and that name is
what to search for.

## Goal

One window, one mechanism, one set of class names. Today the system carries nine
dialog purposes built from five different chromes and two different mechanisms.
After this program there is one structure with four axes, and every theme sets
its own values for it.

The look is not designed here. It is taken from the editor File Manager window,
value for value, because that window is the one that already works.

## Decisions already taken

Recorded so they are not re-litigated.

- **The File Manager is the reference.** Every value of the canon is copied from
  `.sl-toastui-upload`, `.sl-toastui-window-head`, `.sl-fm-foot` and `.sl-fm-btn`
  in `skin.css`. Head plate 42px on `--sl-color-bg-soft`, ghost action cluster
  right, body with its own scroll, foot with a status line.
- **"Unchanged" is measured per theme, not once.** `skin.css` is byte-identical in
  both themes but its tokens are not, so there are two File Managers today and each
  is compared against itself. See *The two File Managers* below for the numbers.
- **Values stay per theme.** The admin radius is **not** unified to the lite one.
  That follows the rule already accepted in THEME-ETALON-2026 — one canon, many
  skins, values per theme — and unifying one token while leaving the others
  divergent would be arbitrary.
- **Size `lg` is 880px** so the File Manager keeps its current width.
- **One mechanism: the native `<dialog>`.** `showModal()` for decisions,
  `show()` for tools worked alongside. The focus trap, the top layer and the
  return of the focus become the browser's job.
- **The non-modal presentation stays non-modal.** The File Manager and the emoji
  panel are dragged aside to read the text under them. This is a feature and must
  survive.
- **Every name is standardised**, not only the class names. Classes, data
  attributes, element ids, JavaScript functions and template file names all follow
  one rule each; the rules are in *Naming standard* below and are part of the
  contract, not a matter of taste at implementation time.
- **`lite` is migrated first**, `admin` mirrors it afterwards. The reference File
  Manager lives in `lite`, so drift is visible immediately.
- **Separate task, ahead of THEME-ETALON-2026 batch 0.** Windows are inside the
  canon scope of that program, so its rules apply here: one canon, many skins;
  class names, semantics and rule structure single and shared; values per theme;
  themes stay independent and their duplication is on purpose.
- **No temporary aliases.** The canon reuses the `sl-modal*` names, so the CSS
  cannot be laid down beside the old one and switched over gradually — the moment
  it lands, every existing window is restyled. Batch 1 therefore lands the CSS and
  all five markup sites together. A transitional `.sl-win` name is forbidden by
  `.rules/global.md`.

## What exists today

### Nine purposes, thirteen declarations

| Purpose | Where | Theme |
|---|---|---|
| Confirmation | `fragments/confirm-modal.html`, included by `partials/site-footer.html` | lite |
| Confirmation | `fragments/confirm-modal.html`, included by `layouts/admin.html` | admin |
| Share sheet | `fragments/share-dialogs.html` (`#sl-share-sheet`) | lite |
| QR code | `fragments/share-dialogs.html` (`#sl-share-qr`) | lite |
| Lightbox | built as a string in `plugins/system/slaed.js` | both |
| Editor gallery | `partials/editor-toastui-files.html` (`.sl-toastui-shot`) | lite, admin |
| Browser gallery | `partials/file-browser.html` (`.sl-fm-modal`) | admin |
| Icon picker | `partials/icon-picker-modal.html` | admin |
| File Manager | `partials/editor-toastui-files.html` (`.sl-toastui-upload`) | lite, admin |
| Emoji panel | `partials/editor-toastui-templates.html` (`.sl-editor-emoji-panel`) | lite, admin |

### Who renders them

- `plugins/editors/toastui/driver.php` — `getHtmlPart('editor-toastui-files')`
  and `getHtmlPart('editor-toastui-templates')`
- `admin/modules/categories.php` (three call sites) and `admin/modules/modules.php`
  — `getHtmlPart('icon-picker-modal')`
- `core/admin.php` — `getHtmlPart('file-browser')` and its row, tile and props
  fragments
- confirmation and sharing are included by the template engine, not by PHP

### Who drives them

- `plugins/system/slaed.js` — lightbox creation, confirmation, share sheet, QR,
  `data-sl-close`, `data-sl-confirm-ok`, and its own Escape for the speed dial
- `plugins/editors/toastui/assets/editor-upload.js` — `setPanel`,
  `setWindowFront`, `setWindowExpand`, `setWindowClose`, `setWindowDrag`, its own
  Escape, and the gallery `showModal()`
- `templates/admin/assets/js/admin-ui.js` — icon picker and browser gallery

### Five chromes

`dialog.sl-modal` in `lite/theme.css`; `dialog.sl-modal` in `admin/theme.css`
(different border, shadow, backdrop, head padding, and a 16px bold title against
a 28px normal one); `.sl-icon-modal` on top of the admin one; `.sl-toastui-upload`
and `.sl-editor-emoji-panel` in `skin.css`, which is byte-identical in both themes.

Closing is solved twice: a filled round `.sl-modal-close` floating over the corner
of the content, and a ghost square inside the head. The head keeps the ghost one.

### The two File Managers

One stylesheet, two looks. `skin.css` is byte-identical in both themes and spends
its values through tokens that are not. Measured in `base.css`:

| Token | lite | admin |
|---|---|---|
| `--sl-radius-card` | 10px | **8px** |
| `--sl-shadow-panel` | `0 4px 16px rgba(0, 0, 0, 0.2)` | **`0 1px 3px rgba(0, 0, 0, 0.05)`** |
| `--sl-color-bg-soft` | `#f8f9fb` | **`#f7fbfd`** |
| `--sl-color-border-strong` | `#cad2da` | `#cad2da` |
| `--sl-radius-control` | 4px | 4px |

So the corner, the elevation and the head plate of the File Manager already differ
between the two panels. The canon inherits that and keeps it: each theme is
compared against its own screenshot.

**Open question for the admin theme.** `0 1px 3px rgba(0, 0, 0, 0.05)` is a resting
shadow for a card lying on a page, not for a window floating over one. It is very
likely a value chosen for panels and inherited by the window by accident rather
than a skin decision. This program does not change it — that would be a redesign
in disguise — but it should be confirmed or corrected as its own task.

## The canon

### Structure

The `<dialog>` is the flex column itself; there is no wrapper element inside it.

```
dialog.sl-modal
  .sl-modal-title      head plate, 42px
    .bi                icon, takes --sl-modal-tone
    .sl-modal-cap      name and optional subtitle, both clipped
      :first-child     the name, any tag, styled by position not by tag
      .sl-modal-sub    the subtitle
    .sl-modal-acts     ghost action cluster
      .sl-modal-act    28px square; -move and -close carry their own hover tone
  .sl-modal-bar        optional command panel
  .sl-modal-body       the only scrolling part; -flush drops the padding
  .sl-modal-foot       status line and buttons
    .sl-modal-info     always aria-live, collapses when empty
    .sl-modal-btn      32px, min-width 116px; -main takes the tone
```

### Four axes and nothing else

- **Size** — `sl-modal-sm` 420, default 560, `sl-modal-lg` 880, `sl-modal-xl` 1120,
  `sl-modal-full`. Replaces the current 520 / `.sl-modal-wide` pair, which is
  implemented differently in the two themes.
- **Presentation** — modal by default; `data-sl-window` opens through `show()`,
  drops the backdrop and turns the head into a drag handle.
- **Tone** — `sl-modal-danger`, `-warn`, `-success` set `--sl-modal-tone` and
  `--sl-modal-line`; the icon, the accent stripe and the primary button follow.
  The system has no notion of tone today: deleting twelve files looks like sharing.
- **Foot** — present or simply not written. No class and no PHP flag is needed for
  the empty case.

### Tokens

`--sl-modal-gutter`, `--sl-modal-pad` and `--sl-modal-head` are theme values and
belong in `tokens.css` when THEME-ETALON-2026 creates it; until then they sit at
the top of the component block.

`--sl-modal-w`, `--sl-modal-tone`, `--sl-modal-line` and `--sl-modal-act-tone` are
internal. The axis classes set them, a theme author never does, and they do not go
to `tokens.css`.

## Naming standard

One rule per kind of name. A name that does not fit its rule is a defect of the
batch that introduced it.

### CSS classes

Hyphenated, inside the `sl-modal` nest, element first and modifier after it:
`sl-modal-btn`, `sl-modal-btn-main`, `sl-modal-act`, `sl-modal-act-close`. Never
`sl_*`. Approved set: `sl-modal-cap`, `sl-modal-sub`, `sl-modal-acts`,
`sl-modal-act`, `sl-modal-info`, `sl-modal-btn`, `sl-modal-bar`,
`sl-modal-bar-gap`, `sl-modal-body-flush`. Everything else reuses names that
already exist.

The gallery is the one window whose inside has a layout of its own, and its old
names carried the chrome it no longer wears, so they were renamed with it:
`sl-shot-grid`, `sl-shot-stage`, `sl-shot-img`, `sl-shot-nav`, `sl-shot-nav-prev`,
`sl-shot-nav-next`, `sl-shot-side`. The word is the one the project already used
for a preview, and the block lives beside the canon in `theme.css` of each theme.

### State classes

A state is `sl-is-<state>`, never a name that reads like an element of the
component. The themes already carry fifteen of them — `sl-is-active`,
`sl-is-open`, `sl-is-current`, `sl-is-hiding` and the rest — and the canon joins
that set with `sl-is-full`, `sl-is-shut` and `sl-is-locked`.

`sl-is-locked` sits on `<html>` while a modal window is open. Nothing else in the
system puts a class on `<html>` or `<body>` today, so this is the first one and it
follows the state rule rather than inventing a document-level convention.

### Data attributes

House style is `data-sl-<thing>`: short and unnamespaced. A namespaced family is
used only where one subsystem owns many of them, as `data-sl-fm-*` does. The
canon is small, so it stays short and does not invent a `data-sl-modal-*` family.

| Attribute | Meaning |
|---|---|
| `data-sl-open="id"` | the control that opens the window |
| `data-sl-close` | the control that closes it |
| `data-sl-full` | expand and collapse toggle |
| `data-sl-static` | window refuses Escape and the backdrop click |
| `data-sl-focus` | where the keyboard lands on open |
| `data-sl-window` | present means the non-modal presentation |

The gallery is the exception the rule allows: one subsystem owning many, as
`data-sl-fm-*` does. Its family replaced the three sets that drove the four
galleries, and is authored in `partials/window-gallery.html` only.

| Attribute | Meaning |
|---|---|
| `data-sl-shot="owner"` | the gallery window and who drives it: `editor`, `files` or `view` |
| `data-sl-shot-name` | the name of the object on show |
| `data-sl-shot-img` | the picture |
| `data-sl-shot-props` | the container the properties are swapped into |
| `data-sl-shot-rows` | the list the properties are written into |
| `data-sl-shot-num` | the counter of the walk |
| `data-sl-shot-down` | the download link |
| `data-sl-shot-step` | one step of the walk, `-1` or `1` |
| `data-sl-shot-act` | an action of the object, named by the subsystem that drives it |

`data-sl-shot` defaults to `view` when PHP names no owner, which is how the page
of the site gets its own viewer from the same fragment without a flag of its own.

The engine also writes `data-sl-moved`, `data-sl-left` and `data-sl-top` on a
windowed dialog to remember where the user put it. These are internal state and
are never authored in a template.

`data-sl-close` already exists with eleven call sites and its handler in
`slaed.js`; it is reused unchanged, and `data-sl-icon-close` in the admin theme
folds into it. `data-sl-open` was left without a handler while no markup wrote it,
and landed with `demo/window-canon.html`, the first page to use it. The presentation is a boolean attribute rather than
`data-sl-mode="window"` because `data-sl-mode` is already taken by the File
Manager drop zones and by its list/grid toggle. `data-sl-open`, `data-sl-full`,
`data-sl-static`, `data-sl-focus` and `data-sl-window` were checked and are free.

### Element ids

Hyphenated `sl-*`, like the classes. `#sl-confirm`, `#sl-share-sheet` and
`#sl-share-qr` already comply. `#sl_icon_modal` does not and becomes
`#sl-icon-window` in batch 1. `#sl_debugger` and `#sl_nav_cats` carry the same
defect but are outside this program.

### JavaScript functions

`.rules/global.md` applies: camelCase, verb + noun, 6-24 characters, two or three
words in the noun part. Where `editor-upload.js` already has a good name for the
same action, the canon takes that name instead of inventing a synonym.

`setWindowOpen`, `setWindowClose`, `setWindowExpand`, `setWindowFront`,
`setWindowPlace`, `setWindowBounds`, `setWindowRelease`, `setFirstFocus`,
`setPageLock`, `isWindowModal`.

`setWindowFront` and `setWindowExpand` keep the names `editor-upload.js` already
gave those actions.

Short verb-plus-adjective names such as `setOpen`, `setShut`, `setFull` or
`setClamp` do not satisfy the rule and must not appear.

### Template files

`window-<purpose>.html`. The folder follows the documented API split in
`docs/TEMPLATES.md`: a window that PHP renders with data through `getHtmlPart()`
lives in `partials/`, a window that a layout includes unconditionally lives in
`fragments/`.

| New | Replaces |
|---|---|
| `fragments/window-confirm.html` | `fragments/confirm-modal.html` |
| `fragments/window-share.html` | half of `fragments/share-dialogs.html` |
| `fragments/window-qr.html` | the other half of `fragments/share-dialogs.html` |
| `partials/window-gallery.html` | the gallery inside three partials plus the string in `slaed.js` |
| `partials/window-icons.html` | `partials/icon-picker-modal.html` |

The File Manager and the emoji panel keep their current partial names: those files
carry an editor subsystem, not only a window.

## Batches

Every batch ends green on the verification baseline below before the next starts.

### Batch 1 — the canon and the five native dialogs

Lands the CSS and all markup that depends on it in one step, because the names are
shared and there is no intermediate state.

- Replace the `dialog.sl-modal` block in `lite/theme.css` with the canon.
- Rename `fragments/confirm-modal.html` to `fragments/window-confirm.html` and
  split `fragments/share-dialogs.html` into `fragments/window-share.html` and
  `fragments/window-qr.html`; migrate the `{% include %}` in `partials/site-footer.html`.
- Rewrite all three onto the new structure, keeping `data-sl-close` and
  `data-sl-confirm-ok`.
- Rewrite the lightbox markup inside `plugins/system/slaed.js`.
- Add the canon engine: scroll lock, animated exit, `data-sl-static`, first focus,
  and the `cancel`/`close` listeners in the capture phase. Function names come from
  *Naming standard*.
- Mirror into `admin/theme.css` and `admin/fragments/window-confirm.html`; migrate
  the `{% include %}` in `layouts/admin.html`.
- Rename `admin/partials/icon-picker-modal.html` to `partials/window-icons.html`,
  change its id from `sl_icon_modal` to `sl-icon-window`, move the search out of
  the head into `.sl-modal-bar`, fold `data-sl-icon-close` into `data-sl-close`, and
  migrate the four `getHtmlPart('icon-picker-modal')` call sites in
  `admin/modules/categories.php` and `admin/modules/modules.php`.

Done when: no window in either theme still carries `.sl-modal-close`, no id in the
canon uses an underscore, and the icon picker no longer keeps controls inside its
title.

### Batch 2 — one gallery instead of four

- One `partials/window-gallery.html` per theme, used by the editor and by the admin
  file browser.
- One set of data attributes. Today there are three: `data-sl-slot` in the editor,
  `data-sl-fm-*` in the browser, and none in the `slaed.js` lightbox.
- Delete the three duplicate declarations and the string-built one.
- `core/admin.php` and `plugins/editors/toastui/driver.php` pass data to the same
  fragment.

Done when: `grep` for `sl-toastui-shot` and `sl-fm-modal` returns nothing.

### Batch 3 — the File Manager on `<dialog>`

The riskiest batch: uploads, the queue, the quota, drag-and-drop, the keyboard walk
and htmx all live next to the window code in `editor-upload.js`.

- The panel root becomes `<dialog class="sl-modal sl-modal-lg" data-sl-window>`.
- Remove the manual `aria-hidden` toggle, the manual focus return and the fixed
  z-index ladder 31 / 10020 / 10050 in `skin.css`.
- **Keep the stacking mechanic.** A non-modal `<dialog>` does not enter the browser
  top layer, so the order of two open windows is the canon's job. `setWindowFront`
  in `editor-upload.js` goes away as a function; the same behaviour lives in the
  canon engine under the same name and does two things at once: it raises the
  z-index of the window, and it moves that window to the end of the Escape stack.
  Without the second half, Escape closes whatever was opened last instead of what
  the user is looking at.
- The front is taken on open, on a pointer press anywhere inside the window
  (captured, so it runs before the drag), and on `focusin` — a window reached by
  keyboard must come forward too.
- Keep `setWindowDrag` and `setWindowExpand`; the expand must stash the pre-expand
  coordinates and restore them, or a moved window is lost on collapse.
- Keep the Escape handler: a non-modal `<dialog>` does not answer that key by
  itself, and the existing handler also has to let the open speed dial win.
- The head keeps its three actions and their hover tones.

Done when: the File Manager is pixel-identical to the pre-batch screenshot, still
drags, still expands, and the page behind it still scrolls.

### Batch 4 — the emoji panel

- Same treatment; its head comes from 36px to the canon 42px.
- `.sl-toastui-window-head`, `.sl-toastui-window-actions`, `.sl-toastui-window-action`,
  `.sl-toastui-head-icon*` and `.sl-toastui-window-expanded` are deleted from
  `skin.css` in both themes.

Done when: `skin.css` contains no `sl-toastui-window-*` rule.

### Batch 5 — cleanup

- Delete `.sl-modal-close` and `.sl-modal-wide` from both themes.
- Collapse the three Escape handlers into one.
- Declare `--sl-space-xl` in `base.css` of both themes: `.sl-toast` already uses it
  and it is not declared anywhere.
- `demo/window-canon.html` stays. Its own copies of the canon, `window-canon.css`
  and `window-canon.js`, are gone: the page loads `theme.css` and
  `plugins/system/slaed.js`, so it cannot drift from what ships. The page itself is
  built from the theme as well - `sl-wrp` for the container, `sl-chip` for an opener,
  `sl-form-row` for a labelled field, `sl-font` for a heading, and the emoji grid of
  the editor skin. What stays inline is what lite does not ship:
  the layout of the demo cards, and the props standing in for a picture, a stage and
  the icon grid of the admin theme. That remainder is a `<style>` block of the page
  itself, so the demo is one file and carries no stylesheet and no script of its own.

## Verification baseline

Run after every batch, in both themes, at 1440x900 and at 390x844.

- Head plate is 42px in every window, with and without a subtitle.
- Sizes measure 420 / 560 / 880 / 1120 / full exactly.
- Focus lands inside the window on open and returns to the opener on close.
- Escape closes everywhere except a window carrying `data-sl-static`; a click on
  the backdrop follows the same rule.
- Tab does not leave an open modal window.
- The page behind a modal window does not scroll and does not shift sideways; the
  page behind a windowed one does scroll. The rule that does it, `html.sl-is-locked`,
  belongs to the canon stylesheet and not to any page around it.
- Nothing leaves the viewport; no horizontal page scroll appears.
- A windowed dialog drags, keeps at least 120px of its head on screen, survives a
  viewport resize, and returns to its own place after expand and collapse.
- The status line collapses when empty and the buttons stay right-aligned.
- Two windowed dialogs open at once stack correctly. Pressing inside the lower one,
  focusing it from the keyboard, or dragging it brings it to the front, and Escape
  then closes **that** one and not the one opened last.
- A modal opened from inside a windowed one draws above it, locks the page, and
  gives the focus back into the windowed one on close.
- Calling the opener of a window that is already open brings that window to the
  front and puts the keyboard in it. Doing nothing reads as a broken button, which
  is exactly what happens when the window is open but buried under another one.
- Under `prefers-reduced-motion` a window closes at once. Waiting out a fallback
  timer for an animation that was switched off is the opposite of what the setting
  asked for.
- Console is clean.

For the File Manager: compare each theme against a screenshot of **that theme**
taken before batch 3. Shared across both: head 42px on `--sl-color-bg-soft` at
13px, border `--sl-color-border-strong`, foot padding `10px 20px`, buttons 32px
with a 116px minimum, width 880px. Theme-specific: corner radius 10px in lite and
8px in admin, and the panel shadow of each theme. A radius of 10px measured in the
admin panel is a defect of the batch, not a fix.

## Traps already paid for

Found the hard way while building the demo; each one cost a debugging round.

- A `*/` sequence inside a path in a CSS comment closes the comment early and the
  next rule is swallowed. Never write `templates/*/assets` in a comment.
- `cancel` and `close` fire at the `<dialog>` and do **not** bubble. A listener on
  `document` sees them only with `capture: true`.
- `position: static` destroys the geometry of `dialog:modal`: the user agent gives
  it `position: fixed; inset: 0`, and overriding that drops the dialog into the
  document flow.
- A longer selector scoped to the File Manager beats the phone media query. Scope
  such overrides with `:not([data-sl-window])` instead of restating the inset.
- `.sl-alert-text` carries `text-align: justify` in the theme; inside a narrow
  window it opens rivers. The canon resets it to `left`.
- Removing a wrapper element means auditing every selector that used it as a step;
  two `> .sl-modal-frame >` selectors survived a refactor and silently killed the
  drag cursor.

## Out of scope

- `layouts` and `pages` are untouched, as in THEME-ETALON-2026.
- Toasts, popovers, the speed dial and `.sl-float` tooltips are not windows and are
  not part of this canon.
- `window.confirm()` stays in `slaed.js` as the fallback for a page that has no
  confirmation dialog rendered.
