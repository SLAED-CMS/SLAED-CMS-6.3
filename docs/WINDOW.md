# Window System Documentation

This document describes the window canon: the one structure every dialog of the project is built from.

## Architecture Overview

Every window in SLAED is a native `<dialog class="sl-modal">`. There is no second mechanism and no wrapper element
inside the dialog — the dialog is the flex column itself.

The platform does the hard parts. `showModal()` gives the focus trap, the top layer and the return of the focus;
`show()` gives a panel that stands beside the page and is worked alongside it. The canon engine in
`plugins/system/slaed.js` writes only what the platform does not give: the scroll lock, the animated exit, the
placing, the stacking, the drag, and the key a non-modal dialog does not answer.

The component is declared once and byte-identically in `templates/lite/assets/css/theme.css` and
`templates/admin/assets/css/theme.css`. Both copies consume theme tokens from `base.css`, so the same block
produces a different window in each theme by design — the corner, the elevation and the head plate of the two
themes differ because their tokens differ.

There is no reference page. The canon is checked against the shipped windows themselves, listed under
*Where the windows are*, with the Verification list at the end of this document.

## Structure

```
dialog.sl-modal
  .sl-modal-title      head plate, 42px
    .bi                icon, takes --sl-modal-tone
    .sl-modal-cap      name and optional subtitle, both clipped
      :first-child     the name, any tag, styled by position and not by tag
      .sl-modal-sub    the subtitle
    .sl-modal-acts     ghost action cluster
      .sl-modal-act    28px square; -move and -close carry their own hover tone
  .sl-modal-bar        optional command panel
  .sl-modal-body       the only scrolling part; -flush drops the padding
  .sl-modal-foot       status line and buttons
    .sl-modal-info     always aria-live, collapses when empty
    .sl-modal-btn      32px, min-width 116px; -main takes the tone
```

A caption with a subtitle fits the same plate height as one without: the height of the plate is part of the look
and must not float with the content.

## Axes

Four, and nothing else.

**Size.** `sl-modal-sm` 420px, no class 560px, `sl-modal-lg` 880px, `sl-modal-xl` 1120px, `sl-modal-full`.
There is deliberately no class for the default: a window that wants 560px writes no size at all.

**Presentation.** Modal by default. `data-sl-window` opens the dialog through `show()`, drops the backdrop and
turns the head plate into a drag handle. Use it only for a tool that must be worked alongside the text under it.

**Tone.** `sl-modal-danger`, `sl-modal-warn`, `sl-modal-success` set `--sl-modal-tone` and `--sl-modal-line`; the
head icon, the accent stripe and the primary button find them by themselves.

**Foot.** Present or simply not written. No class and no PHP flag is needed for the empty case.

## Tokens

`--sl-modal-gap`, `--sl-modal-pad-x`, `--sl-modal-height` and the default `--sl-modal-width` are theme values and
live in the API block of `templates/<theme>/assets/css/base.css`, above the marker.

`--sl-modal-tone`, `--sl-modal-line` and `--sl-modal-act-tone` are internal to the component: they sit on the
`.sl-modal` root, the axis classes set them, and a theme author never does. `--sl-modal-width` is both — the
theme owns the default, `sl-modal-sm`, `-lg` and `-xl` override it on the window itself.

## Data attributes

| Attribute | Meaning |
|---|---|
| `data-sl-open="id"` | the control that opens the window |
| `data-sl-close` | the control that closes it |
| `data-sl-full` | expand and collapse toggle |
| `data-sl-static` | window refuses Escape and the backdrop click |
| `data-sl-focus` | where the keyboard lands on open |
| `data-sl-window` | present means the non-modal presentation |

`data-sl-open` opens a window that needs no data. Every window that must be filled before it is shown — and today
that is all of them — is opened from JavaScript with `window.setWindowOpen()` after its content is written. The
attribute stays because a window whose content is already in the markup needs no script at all to open.

The engine writes `data-sl-moved`, `data-sl-left` and `data-sl-top` on a windowed dialog to remember where the
user put it. These are internal state and are never authored in a template.

### The gallery family

The gallery is the one window whose inside has a layout of its own, so it owns a namespaced family, as
`data-sl-fm-*` does. It is authored in `partials/window-gallery.html` only.

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

`data-sl-shot` defaults to `view` when PHP names no owner, which is how a page of the site gets its own image
viewer from the same fragment without a flag of its own.

## JavaScript API

`plugins/system/slaed.js` exports four functions on `window`:

| Function | Use |
|---|---|
| `setWindowOpen(box)` | open, place, stack and put the keyboard in. Calling it on an already open window brings that window to the front and focuses it |
| `setWindowClose(box)` | play the exit and then close |
| `setWindowFront(box)` | raise a windowed dialog and make it the top one for Escape |
| `setWindowExpand(box, button)` | toggle `sl-is-full`, stashing and restoring the pre-expand coordinates |

`setWindowPlace`, `setWindowBounds`, `setWindowRelease`, `setFirstFocus`, `setPageLock` and `isWindowModal` are
internal to the engine.

`window.setConfirmTask(text, run)` asks a question through `#sl-confirm` and runs `run` on yes. It falls back to
`window.confirm()` for a page that renders no confirmation window.

## Naming

**CSS classes.** Hyphenated, inside the `sl-modal` nest, element first and modifier after it: `sl-modal-btn`,
`sl-modal-btn-main`, `sl-modal-act`, `sl-modal-act-close`. Never `sl_*`. The gallery block uses `sl-shot-*`.

**State classes.** A state is `sl-is-<state>`, never a name that reads like an element of the component. The canon
uses `sl-is-full`, `sl-is-shut` and `sl-is-locked`. `sl-is-locked` sits on `<html>` while a modal window is open.

**Element ids.** Hyphenated `sl-*`, like the classes: `#sl-confirm`, `#sl-share-sheet`, `#sl-share-qr`,
`#sl-icon-window`.

**JavaScript functions.** `.rules/global.md` applies: camelCase, verb + noun, 6-24 characters. Short
verb-plus-adjective names such as `setOpen`, `setShut` or `setFull` do not satisfy the rule.

**Template files.** `window-<purpose>.html`. The folder follows the API split in `docs/TEMPLATES.md`: a window PHP
renders with data through `getHtmlPart()` lives in `partials/`, a window a layout includes unconditionally lives
in `fragments/`.

## Where the windows are

| Window | File | Theme |
|---|---|---|
| Confirmation | `fragments/window-confirm.html` | lite, admin |
| Share sheet | `fragments/window-share.html` | lite |
| QR code | `fragments/window-qr.html` | lite |
| Gallery and lightbox | `partials/window-gallery.html` | lite, admin |
| Icon picker | `partials/window-icons.html` | admin |
| File Manager | `partials/file-manager.html` | lite, admin |
| Emoji panel | `partials/editor-toastui-templates.html` | lite, admin |

The emoji panel keeps its editor partial name: that file carries an editor subsystem and not only a window, and it
is `data-sl-window`. The File Manager left the editor for the theme and is built by `getFileManagerWindow()` from the
rule of one upload place; it is `data-sl-window` inside the editor, where the text under it has to stay reachable, and
modal beside a form field, where nothing else is worked on at once.

`core/admin.php` and `plugins/editors/toastui/driver.php` render `window-gallery` with their own data;
`partials/site-footer.html` includes it with none, which produces the site image viewer.

## The file manager as a form field

The File Manager window has two modes, and `is_field` is the flag that tells them apart.
`getFileManagerWindow(['is_field' => true, …])` draws no queue, no progress bar and no
"insert as" switch: in field mode nothing is being read, so there is nothing to report the
progress of. The quota line still draws. What was picked is drawn as a chip in the row that
opened the window.

**Outside the editor the window only picks — the form uploads.** Inside the editor the window
keeps uploading over AJAX because there is no form there to carry the file. Beside a form row
the file rides an ordinary multipart POST on submit, which is why field mode needs no progress
bar of its own.

The window can hand back exactly one of three things, and the three are exclusive by
construction — the rail offers one tab at a time and `setFieldPick()` clears the other two
carriers whenever it writes one:

| Tab | What reaches the form | Uploads |
|---|---|---|
| Upload to server | the `File`, through `DataTransfer` into the hidden `<input type="file">` | yes, on submit |
| My files | the path of an already stored file | no |
| Link to file | the address | no |

**The handler still reads them in a fixed defensive order** — `$_FILES`, then the storage path,
then the address — because the exclusivity is a property of the client and a handler must not
depend on one. The storage path arrives from the client and is **never taken on trust**: it is
resolved by `getUploadTakenFile()`, which reads it through the place context and refuses it unless
`FileManager::getFileOwner()` matches `getEditorFileOwner()` — a module moderator is excused that
last test and nothing before it. Both form handlers call that one resolver and keep only the wording
of their own refusal: the guard is written once, because a guard written twice is one that drifts.

**`canlink` decides whether the link tab exists at all.** It is a key of the place rule, not a
choice of the caller. `users.avatar` stores a file name resolved against `adirectory`, so an
external address could never be resolved there: its rule answers `canlink` false, the window
draws no link tab, and `getFileManagerField()` emits no address field for it to be posted into.
An interface that simply does not draw a tab is not a guard — the route refuses it too.

`getFileManagerField()` in `core/helpers.php` builds the row: the button, the chip and the three
hidden carriers. The runtime reads these names and nothing else does:

| Where | Name | What it is |
|---|---|---|
| the row | `data-sl-act="open"` + `data-editor="<id>"` | the button that opens the window |
| the row | `data-sl-act="clear"` + `data-editor="<id>"` | the cross of the chip; clears all three |
| the box | `data-sl-field="file"` | the hidden `<input type="file">` the form uploads |
| the box | `data-sl-field="path"` | the hidden text field carrying a storage path |
| the box | `data-sl-field="url"` | the hidden text field carrying an address |
| the box | `data-sl-field="chip"` | the chip; hidden while nothing is picked |
| the box | `data-sl-field="name"` | the text inside it — name and weight |

**The rule is checked at the pick, not only at the submit.** `addFileList()` refuses on count
first — a drop carries as many files as the pointer held, whatever the picker was told to allow —
and then hands the one file to `checkFieldFile()`, which refuses on extension, weight and finally
image dimensions, the last only once the browser has decoded the picture. The words are the ones
`getUploadFailText()` would have answered with and arrive in `opt.labels`; no refusal phrase is
invented for the client.
`setFieldPick()` answers whether the write landed and the window closes on that answer alone: a
chip drawn over a field that stayed empty states a file the form is not carrying.

**The window keeps its own id scheme.** The ids of its nodes are minted from the instance id —
`<id>-fm`, `-fm-msg`, `-fm-url` and the rest — and the runtime finds every node of one instance
by exactly those. `getFieldIds()` governs the form row the button lives in and nothing inside
the window; two windows on one page stay apart because the instance id differs.

**Where the row is assembled matters.** `getFileManagerWindow()` emits
`plugins/system/filemanager.js` on its first call of the request under a `static $done`, and the
tag lands where that call's output is printed. A door assembled before an editor of the same page
would let that editor's inline `register()` run before the runtime exists, and the editor loses
its file button with no console error. Assemble the door after every editor of the page.

## Traps

Each of these cost a debugging round. They are properties of the platform and of this code, not history.

- A `*/` sequence inside a path in a CSS comment closes the comment early and the next rule is swallowed. Never
  write `templates/*/assets` in a comment.
- `cancel` and `close` fire at the `<dialog>` and do **not** bubble. A listener on `document` sees them only with
  `capture: true`.
- `position: static` destroys the geometry of `dialog:modal`: the user agent gives it `position: fixed; inset: 0`,
  and overriding that drops the dialog into the document flow.
- A longer selector scoped to one window beats the phone media query. Scope such an override with
  `:not([data-sl-window])` instead of restating the inset.
- `getBoundingClientRect()` returns the transformed box, and the entry animation holds a window at `scale(0.97)`
  for its first frames. Any geometry the engine reads to place, pin or clamp a window must come from
  `offsetWidth`, `offsetLeft` and `offsetTop`, which ignore transforms.
- `.sl-alert-text` carries `text-align: justify` in the theme; inside a narrow window it opens rivers. The canon
  resets it to `left`.
- Removing a wrapper element means auditing every selector that used it as a step.
- A handler that answers Escape outside the canon must stand down while a window is open, or one press does two
  things. `editor-tags.js` yields the key to `dialog.sl-modal[open]` for exactly this reason.

## Verification

Run in both themes, at 1440x900 and at 390x844.

- Head plate is 42px in every window, with and without a subtitle.
- Sizes measure 420 / 560 / 880 / 1120 / full exactly, and a windowed dialog centres on its layout width.
- Focus lands inside the window on open and returns to the opener on close.
- Escape closes everywhere except a window carrying `data-sl-static`; a click on the backdrop follows the same rule.
- Tab does not leave an open modal window.
- The page behind a modal window does not scroll and does not shift sideways; the page behind a windowed one does
  scroll.
- Nothing leaves the viewport; no horizontal page scroll appears.
- A windowed dialog drags without a jump when grabbed during its opening animation, keeps at least 120px of its
  head on screen, survives a viewport resize, and returns to its own place after expand and collapse.
- The status line collapses when empty and the buttons stay right-aligned.
- Two windowed dialogs open at once stack correctly. Pressing inside the lower one, focusing it from the keyboard
  or dragging it brings it to the front, and Escape then closes that one and not the one opened last.
- A modal opened from inside a windowed one draws above it, locks the page, and gives the focus back into the
  windowed one on close.
- Calling the opener of a window that is already open brings it to the front and puts the keyboard in it.
- Under `prefers-reduced-motion` a window closes at once.
- Console is clean.

## Out of scope

Toasts, popovers, the speed dial and `.sl-float` tooltips are not windows and are not part of this canon.
