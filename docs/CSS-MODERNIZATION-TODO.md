# CSS Modernization — Remaining Work (Self-Prompt)

> Continue the `templates/lite` CSS modernization. Read this, then `.rules/global.md`
> and `.agents/skills/slaed/templates/manage-slaed-templates/SKILL.md` before editing.
> Work in **verifiable batches** — one component per batch, before/after check on
> **4 widths (1280 / 900 / 768 / 560)**. Never mass-convert.

## Goal (3 approved tasks)
1. **Convert legacy `float` layout → flex/grid.** ~29 layout floats left.
2. **Refactor load-bearing `!important` via specificity** (column selectors → `td.sl-*-col-*`), ~12 removable that way.
3. **Deep-scan for repeated identical declaration blocks** across different selectors (real consolidation).

## Already done — do NOT redo
- Removed 12 redundant `text-decoration: none !important` (base.css enforces none globally; no `underline` rule exists). `!important` 33 → 21.
- Merged the split `#content` rule.
- **Batch 1 — page shell (committed):** `#container` → CSS grid (`minmax(0,1fr) var(--sl-sidebar)`, `column-gap: var(--sl-gutter)`); dropped clearfix hack `height:1%` + sidebar `padding-right` reservation; `#content`/`#sidebar` lost their floats/width/negative-margin and gained `min-width:0`; mobile `#container{grid-template-columns:1fr}`; removed `class="sl-clrfix"` from `#container` in home.html + app.html.

## CRITICAL GOTCHAS (learned the hard way)
- **clearfix `::before/::after` become flex/grid items.** Turning a `.sl-clrfix` element into grid/flex injects its clearfix pseudo-elements as items and scrambles the layout. → Remove `sl-clrfix` from that element (grid/flex self-clears). Util at `.sl-clrfix{}` (~line 2826).
- **Add `min-width:0`** to grid/flex items holding long text / `pre` / tables, or they overflow the track.
- **Use `column-gap`, not `gap`,** when a stacked mobile state relies on existing `margin-top` (avoids double spacing).
- **Breakpoints are intentional tiers: 560 / 768 / 900(+901). DO NOT change the values.**
- **The 21 remaining `!important` are load-bearing.** Table column classes (`.sl-cart-col-num`, `.sl-fl-col-num`, … specificity 0,1,0) override `.sl-cart-row td, .sl-forum-line td` (0,1,1). Removing `!important` only works if you bump the column selectors to `td.sl-…-col-…` (0,1,1) and keep source order winning. Verify alignment/padding after. visually-hidden (`left:-9999px`) and hover-state `!important` stay.

## REMAINING float work — batches
Re-inventory first: `grep -nE 'float:\s*(left|right)' templates/lite/assets/css/theme.css`

- **Batch 2 — footer:** `.sl-fmenu ul`, `.sl-fmenu ul li`, `.sl-partners a`. (Footbox already uses `.sl-grid-1-4` grid.) Parent → flex; drop matching mobile `float:none` resets.
- **Batch 3 — comments / forum posts:** `.sl-com-left`/`.sl-com-right`, `.sl-fp-left`/`.sl-fp-right` (media-object: meta column + body) → flex. Mobile already resets these to `float:none`+`padding-left:0`.
- **Batch 4 — cards / rating / media utils:** `.sl-card-image`, `.sl-card-menu`, `.sl-card-aside`, `.sl-rate*` (`.sl-urating`, `.sl-rate`, `.sl-rate-like`), `.sl-img-left`/`.sl-img-right`, `.bx-pager-item`(+`a`), `.sl-big-sl-icons li`, `.sl-progress-info span`+`.sl-pull-right`, `.sl-cart-id`, `.sl-tip > .sl-float-panel`, `.sl-dropdown-form`, `.sl-login-top--head .sl-login-dropdown-form`. Group per widget; verify each.
- **Batch 5 — nav / meta + the `.sl-pull-right` utility:** `#topmenu`, `.sl-search-form`, `.sl-meta > ul > li`. `.sl-pull-right` (float:right) is used in many places — convert to `margin-left:auto` only after each PARENT is flex. Do it LAST.

Then **Task #2** (`!important` specificity) and **Task #3** (repeated-block dedup) as their own batches.

## Verification recipe (Playwright, headless)
Node + Playwright are in the project `node_modules`. Run temp scripts from `c:\tmp\`, point NODE_PATH at the project:
```
NODE_PATH="E:/OSPanel/home/slaed.loc/public/node_modules" node c:/tmp/check.js
```
Per batch: capture a baseline (`getBoundingClientRect` + element screenshots) BEFORE editing at 1280/900/768/560, edit, re-capture, compare (geometry must match the float version or be an intended improvement), assert `page.on('pageerror')` empty. Admin pages need login (see `browser-debugging` skill: name `SLAED CMS`, pwd `e20011976l`, altcha auto-solves). CSS is served direct (no cache layer). **Delete all temp scripts/screenshots when done.**

## Machine note
- Home user = `slaed`; work user = `eduard.laas`. Use env-var paths, never hardcode the username.
- Python: `%LOCALAPPDATA%\Python\bin\python.exe` (`$env:LOCALAPPDATA` / `$LOCALAPPDATA`).
- The lint hook flags pre-existing `>180`-char lines in theme.css on every edit — legacy noise, ignore; it does not block.

## Definition of done
All layout `float:left/right` gone (only text-wrap image floats like `.sl-img-left/right` may stay if a flex/grid equivalent is worse), clearfix removed where containers became flex/grid, ~12 more `!important` gone via specificity, repeated blocks consolidated. Every batch verified on 4 widths with no page errors, committed atomically.
