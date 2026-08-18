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

`demo/window-canon.html` is the reference page. It loads the shipped `theme.css` and the shipped `slaed.js` and
carries no stylesheet and no script of its own, so it cannot drift from what ships.

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

`--sl-modal-gutter`, `--sl-modal-pad` and `--sl-modal-head` are theme values.

`--sl-modal-w`, `--sl-modal-tone`, `--sl-modal-line` and `--sl-modal-act-tone` are internal to the component.
The axis classes set them and a theme author never does.

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
attribute is what lets `demo/window-canon.html` open every window without a script of its own.

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
| File Manager | `partials/editor-toastui-files.html` | lite, admin |
| Emoji panel | `partials/editor-toastui-templates.html` | lite, admin |

The File Manager and the emoji panel keep their editor partial names: those files carry an editor subsystem and
not only a window. Both are `data-sl-window`.

`core/admin.php` and `plugins/editors/toastui/driver.php` render `window-gallery` with their own data;
`partials/site-footer.html` includes it with none, which produces the site image viewer.

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
