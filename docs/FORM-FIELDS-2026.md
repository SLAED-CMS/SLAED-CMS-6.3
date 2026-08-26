# Form Fields 2026

Work plan for what the field standard of 2026-08-26 left open. The standard
itself has landed: on both sides of the system a form row now puts its caption
in the left column and its control in the right one, an editor takes the whole
row, and the colon that used to separate the two is gone. What follows is the
part that was found while building it and deliberately not built.

Status: planned, nothing implemented.

No line numbers anywhere in this document on purpose: every reference names the
file, the fragment or the helper it points at, and that name is what to search
for.

## The baseline this starts from

The caption is tied to its control by explicit `for` and `id` — never by
wrapping the row in a `<label>`, because the field cell is a `<div>` and a
`<div>` inside a `<label>` breaks the content model. Ids read `f-<name>`;
`label_for` sits on the row, `input_id` on the control, `selectid` on an admin
select. 527 rows declare that link today — ten of them conditionally, because
the author row shows a plain value to a member and an input to a guest, and only
the guest has something to point at.

A crawl of 24 site pages and 252 admin pages confirmed that every `for` resolves
to a labelable element, that no id repeats on a page and that no `<label>` nests
inside another. Know what that crawl did not reach: it walked the admin menu and
the tab strips, so every form that opens only against an existing record —
`op=edit&id=…` and its relatives — was never rendered. Those rows were wired by
the same scanner and are almost certainly fine, but they are unproven.

Rows whose field is a radio group, a date pair, an editor or a button carry no
`for`, and that is correct rather than lazy: `for` names exactly one labelable
control and none of those rows has one. It is also why they have no accessible
name at all right now, which is what items 1 to 3 are about.

## The prerequisite all three accessibility items share

Items 1, 2 and 3 are not three separate jobs. Each of them needs the same thing
first, and once it exists each is a short step.

**The label cell needs a stable id of its own.** Today it has none.
`form-field-row.html` renders the caption through `fragments/label.html`, and
`div-row.html` renders `<div class="sl-div-label">` whose contents it wraps in a
`<label for>` when the row named a control. Both can carry a `for`; neither can
be pointed **at**, which is what `aria-labelledby` needs.

The shape to add is one new key, `label_id`, on both row fragments, rendered as
an `id` on the label cell. Derive it the way `label_for` is already derived — the
control name with a suffix, so a row that has both reads `f-title` on the input
and `f-title-label` on the caption. Rows that have no control name — a radio
group, an editor — need the name passed in explicitly; the helper that builds
them (`getTplRadioGroup()`, `getTplTextarea()`) already receives one.

Do this once, verify it with the same crawl that verified `for`, and then take
the three items in any order.

## Item 1 — a radio group has no accessible name

### Problem

257 call sites of `getTplRadioGroup()`, of which the row scan counted 252 sitting
in a form row. The caption lives in the left cell and the options live in the
right one, with nothing joining them. A screen reader reaching such a row reads
"Yes", "No" and never says what question those answer. This is the most serious
of the three: it is WCAG 1.3.1 and 3.3.2, and it affects the single most common
row shape in the admin panel.

`fieldset` appears in this tree three times: `fragments/debug-section.html` in
each theme, and `partials/fieldset-panel.html` in lite. All three are panels — a
bordered box whose `legend` names it — which is the shape `legend` is
comfortable in, and the whois forms in fact sit inside one. No form **row** uses
it, and that distinction is the whole of the argument below.

### Fix

Do **not** reach for `fieldset` and `legend` at row level. They are the textbook
answer and they are the wrong one here: `legend` is laid out by the fieldset's
own algorithm rather than as an ordinary box, so it does not behave as a grid
item, and making it sit in the left track of `.sl-div-item` means fighting that.
The row geometry is shared by 890 admin cells and is not worth destabilising for
this. Note that this is an objection to `legend` as a grid cell and not to
`fieldset` as such — `fieldset-panel.html` shows the idiom working where it
belongs, around a whole panel.

Use the ARIA equivalent, which is fully valid and costs no layout at all: leave
the row markup as it is, put `role="group"` on the field cell and
`aria-labelledby` beside it, naming the label cell. The accessible outcome is
the same as `fieldset` with a `legend`, and nothing moves by a pixel.

That is a change inside `div-row.html` and `form-field-row.html` plus a flag
from the rows that hold a group — the same scanner used for `label_for` already
identifies them exactly, by looking for `getTplRadioGroup` in the field.

### Verification

Extend the crawl that checked `for` and `id`: assert that every `role="group"`
has an `aria-labelledby` that resolves, and that every `.sl-radio-group` on the
page sits inside one. The crawl already walks 252 admin pages, which is where
nearly all of these live.

### Open

- Whether the radio **switch** variant (`sl-radio-switch`, the two-state Yes/No
  control) should be a group at all, or whether it reads better as a single
  labelled control. It is drawn from the same helper but answers one question,
  not a choice among several.

## Item 2 — an editor has no accessible name

### Problem

55 call sites of `getTplTextarea()`, 53 of them in a form row. The caption sits
above a full-width editor that has no name: `fragments/editor-mount.html` is a
`div` carrying an id and, for the code editor, one class — nothing that names
it — and the widget the driver builds inside it ties back to the caption in no
way at all.

### Fix

`aria-labelledby` on whatever element ends up carrying `role="textbox"`, naming
the label cell. That element is different per driver, and this is the part that
makes the item bigger than it looks:

- **toastui** — the contenteditable the vendor script creates; the attribute has
  to be set after the editor mounts, in `plugins/editors/toastui/assets/`.
- **ckeditor** — `.ck-editor__editable`, same timing problem.
- **tinymce** — the editable body lives inside an iframe; the vendor exposes an
  init hook for this.
- **plain** — a real `textarea`, so it only needs `input_id` and an ordinary
  `for`, which the existing wiring already knows how to do.

Take `plain` first: it is the one case that needs no JavaScript at all and it
proves the `label_id` plumbing end to end.

### Open

- Whether the label of an editor row should also become a real `label` when the
  driver is `plain`. It would give click-to-focus for free on that driver and
  nothing on the others, which is an inconsistency worth deciding on rather than
  falling into.

## Item 3 — a hint is not tied to the field it explains

### Problem

98 call sites of `fragments/label-hint.html`, all in the admin panel. The hint
renders under the caption and reads correctly to anyone looking at the screen,
but there is no `aria-describedby` anywhere in this tree — the fragments carry
none, and neither does any call site. A screen reader announces the caption and
the control and never the sentence that explains what to type.

### Fix

Give the hint span an id — the label cell's id with a `-hint` suffix follows the
scheme — and point the control at it with `aria-describedby`. Both ends are
inside `label-hint.html` and the control fragments, so the per-call-site work is
one more key, wired by the same scanner.

The hint is already a `span` rather than a `div`: it was changed during the
field standard work so the caption above it could be wrapped in a `label`
without putting block content inside one. Nothing further is needed there.

### Open

- Whether `sl-tip` — the separate glyph-with-a-popover hint, a different
  component — should be described the same way. It is easier than it looks: the
  text lives in a real `.sl-float-panel`, not in a `title`, so it can be given
  an id and pointed at exactly like `label-hint`. What it does lack is a name
  and a role of its own — it is a `div` with `tabindex="0"` and an
  `aria-hidden` glyph, so a keyboard user reaches it and hears nothing.

## Item 4 — the row folds on the viewport, not on its container

### Problem

Both themes collapse a form row to a single column with
`@media (max-width: 900px)`. That reads the width of the window, and the width
that actually matters is the width of the box the row is standing in. Three
places already disagree with the viewport:

- the two OAuth cards, which are `minmax(280px, 1fr)` in a grid and can be very
  narrow on a wide screen;
- the private-message composer, which lives in the right pane of a two-pane
  split;
- the editor's insert window, a modal capped at `--sl-modal-width`, which is
  880px for the `sl-modal-lg` size it uses.

In all three the row stays two-column on a wide screen because the window is
wide, even though the row itself has nowhere near the room.

### Fix

`container-type: inline-size` on the boxes that own form rows — `.sl-form` and
`.sl-div-grid` at least, plus the composer and the OAuth card — and rewrite the
fold as a `@container` query. Container queries have been Baseline since 2023,
and the admin panel and the site can move together.

Two traps worth knowing before starting:

- `container-type: inline-size` brings layout containment with it, and layout
  containment makes the box a containing block for every absolutely and fixed
  positioned descendant. Anything that escapes its parent today will start
  resolving against the new container instead. The editor toolbars, the popover
  panel of `sl-tip` and the absolutely placed dial fan are what to look at
  first.
- A query cannot read a custom property, so the threshold stays a literal. The
  `bp` axis is declared in `tools/ui-contract.php` but no `--sl-bp-*` token
  exists in either `base.css`; do not add one for this.

### Verification

The screenshot rig, at all four widths and both modes. This is the one item in
this document that moves pixels, so `npm run ui:before` has to run before the
first edit.

## Smaller notes

- **The id scheme is static.** `f-<name>` is derived at the call site, so a page
  that renders the same form twice collides. That already happened twice and was
  fixed by hand — the money module draws three calculators with the same field
  names, and the rss page carries two forms that both post `url`. A counter
  resolved at render time would be structurally safer than the next person
  remembering. The crawl catches it either way.
- **The placeholder repeats the caption** on 90 of the 143 site rows. This was
  decided deliberately, to match the admin panel, and it is not an accessibility
  failure now that a real label exists — the label wins the accessible name. It
  is redundancy, not a defect: some screen readers read the text twice. Revisit
  only if that redundancy is felt.
- **Nothing marks a required field** until it is submitted. `required` is set on
  the control and the theme styles `:user-invalid`, but there is no visible mark
  and no error tied to the field: messages are rendered as a list above the
  form, with no `aria-invalid` on the control and no `aria-describedby` to the
  message. This is the natural sequel to items 1 to 3 and shares their
  prerequisite.
- **A duplicate id on the news page.** `index.php?name=news&op=view&id=<id>`
  renders both an `article` and a `span` carrying the record id. Pre-existing,
  found by the label crawl, not touched: the id is probably load-bearing for
  anchors or scripts and deserves its own look.
- **Two raw attribute interpolations remain.** `modules/money/index.php` and
  `getTplFieldsIn()` in `core/helpers.php` build an `input_attr` string with a
  placeholder value concatenated into it, and the fragments print `input_attr`
  unescaped. The values come from admin configuration, so this is admin-only,
  but it is an unescaped path. There are 98 `input_attr` call sites across the
  tree and the root cause is the fragment contract, not any one caller — which
  is why it was left alone rather than patched in two places.
- **`StatsContractTest` fails across midnight.** Five failures, all comparing a
  date string or a host counter, when a run straddles the day boundary. Re-run
  once the boundary is past and the whole suite is green — that was confirmed
  here, 915 tests with nothing failing. Recorded only so the next person who
  meets it does not read it as fallout from this work.

## Out of scope

- **The choice of caption position.** Left-aligned captions are the
  settings-panel convention and were chosen deliberately for consistency with
  the admin panel. Top-aligned captions measure faster for one-pass form
  filling, which is why most public web forms use them; left-aligned reads
  better when the user is meant to scan and compare fields. Both are defensible.
  This is settled, and reopening it is a product decision, not a defect report.
- **Colons in running text.** The separator between a caption and its control is
  gone everywhere. Colons inside sentences — the pager count, changelog
  metadata, the shop invoice, mail bodies, log lines — stay, because they are
  punctuation and not a field separator.
- **Inline widgets.** The sidebar login block, the search form, the changelog
  filter and the cabinet RSS row are single-line controls, not labelled field
  rows, and they keep their own shape.
