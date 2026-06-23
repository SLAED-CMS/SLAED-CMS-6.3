# CSS Modernization — Remaining Work (Self-Prompt)

> Continue the `templates/lite` CSS modernization. Read this, then `.rules/global.md`
> and `.agents/skills/slaed/templates/manage-slaed-templates/SKILL.md` before editing.
> Work in **verifiable batches** — one component per batch, before/after check on
> **4 widths (1280 / 900 / 768 / 560)**. Never mass-convert.

## Goal (3 approved tasks)
1. **Convert legacy `float` layout → flex/grid.** 7 floats left (was ~29), all intentional/text-flow: `.sl-pull-right` utility (right-align + text wraps around it in `.sl-search-line`), `.sl-card-image`/`.sl-icat`, `.sl-card-aside`, `.sl-img-left`, `.sl-img-right` (text-wrap), and `.sl-progress-info` label/value pair (flex shrinks/wraps the label — float is better here). Batches 1–5 done.
2. **Refactor load-bearing `!important` via specificity** (column selectors → `td.sl-*-col-*`), ~12 removable that way.
3. **Deep-scan for repeated identical declaration blocks** across different selectors (real consolidation).

## Already done — do NOT redo
- Removed 12 redundant `text-decoration: none !important` (base.css enforces none globally; no `underline` rule exists). `!important` 33 → 21.
- Merged the split `#content` rule.
- **Batch 1 — page shell (committed):** `#container` → CSS grid (`minmax(0,1fr) var(--sl-sidebar)`, `column-gap: var(--sl-gutter)`); dropped clearfix hack `height:1%` + sidebar `padding-right` reservation; `#content`/`#sidebar` lost their floats/width/negative-margin and gained `min-width:0`; mobile `#container{grid-template-columns:1fr}`; removed `class="sl-clrfix"` from `#container` in home.html + app.html.
- **`.sl-grid` consolidation:** removed the legacy duplicate `.sl-grid{float:left}` (the canonical rule at ~line 1165 is `display:flex;flex-wrap:wrap`, vestigial float was overridden/no-op everywhere — verified pixel-identical on 4 widths for news listing + forum). Simplified `.sl-grid-1-X > .sl-grid` reset to just `margin:0` (cancels the canonical negative margins); dropped 4 now-dead `float:none` resets (`.sl-forum-cat-info .sl-grid`, `.sl-forum-list-info .sl-grid`, 2× footbox mobile). Fixed dup-class `class="sl-grid sl-grid"`→`sl-grid` in forum-list-info.html.
- **Batch 2 — footer:** `.sl-fmenu`→flex (`flex-wrap;align-items:center;row-gap`), `.sl-fmenu ul`→flex (`min-width:0`), removed `.sl-fmenu ul li{float}` rule, `.sl-partners`→flex, removed `.sl-partners a{float}` + dead mobile `float:none`. Reordered footer HTML so `<ul>` precedes the ad-link `<a class="sl-pull-right">` (menu-first); ad-link held right by footer-scoped `.sl-fmenu .sl-pull-right{margin-left:auto}` (pull-right utility itself still deferred to Batch 5). Verified: li + partners geometry preserved on 4 widths; intended change — ad-link now stacks **below** the menu (right-aligned) instead of above when the bar wraps (≤900px).
- **Batch 3 — comments / forum posts (media-object):** `.sl-comment`→flex (`gap:33px`, was `padding-left:113`+neg-margin float), `.sl-com-left{flex:none;width:80px}`, `.sl-com-right{flex:1 1 auto;min-width:0}`. `.sl-forum-post-in`→flex (`gap:15px`), `.sl-fp-left{order:-1;flex:none;width:160px}` (source is body-first, `order:-1` puts meta column left), `.sl-fp-right{flex:1 1 auto;min-width:0}`. Mobile block switched float-resets → `flex-direction:column;gap:0` + `.sl-fp-left{order:0}` (preserves current mobile order: comment meta-first, forum-post body-first). Removed `.sl-comment:after`+`.sl-partners:after` from the clearfix group and `sl-clrfix` class from `.sl-forum-post-in` (would become phantom flex items). Verified pixel-identical x/y/w on 4 widths (news#642 + forum#16304); only invisible box-height deltas (flex stretch / no margin-collapse), wrapper totals unchanged.

## CRITICAL GOTCHAS (learned the hard way)
- **clearfix `::before/::after` become flex/grid items.** Turning a `.sl-clrfix` element into grid/flex injects its clearfix pseudo-elements as items and scrambles the layout. → Remove `sl-clrfix` from that element (grid/flex self-clears). Util at `.sl-clrfix{}` (~line 2826).
- **Add `min-width:0`** to grid/flex items holding long text / `pre` / tables, or they overflow the track.
- **Use `column-gap`, not `gap`,** when a stacked mobile state relies on existing `margin-top` (avoids double spacing).
- **Breakpoints are intentional tiers: 560 / 768 / 900(+901). DO NOT change the values.**
- ~~**The 21 `!important` …**~~ — **Task #2 DONE: 21 → 7.** Removed 14 table-column `!important` by bumping selectors to `td.…`/`th.…` (specificity 0,1,1) winning by source order over `.sl-cart-row td`/`.sl-forum-line td`/`.sl-table-head th`. **Gotcha corrections for next time:** (a) `.sl-fl-col-num`/`.sl-fl-col-stat` are also on `<th>` (table.html head builder + forum-category-table), so they need `th.…, td.…` — `td.` alone breaks the header cells. (b) The `.sl-forum-line td { padding: --sl-space-sm 5px }` rule lives in `@media (min-width:901px)` (desktop), and its horizontal 5px would re-clobber the columns once `!important` is gone → changed it to vertical-only `padding-top/bottom`. Verified forum table pixel-identical @1280 and column padding/align preserved at ≤900. The remaining **7 stay**: visually-hidden (`left:-9999px`), hover/active background + `opacity` states (`2000`, `2607`, `2859`, `3488`, `3489`).

## REMAINING float work — batches
Re-inventory first: `grep -nE 'float:\s*(left|right)' templates/lite/assets/css/theme.css`

- ~~**Batch 2 — footer**~~ — DONE (see "Already done" above).
- ~~**Batch 3 — comments / forum posts**~~ — DONE (see "Already done" above).
- ~~**Batch 4 — cards / rating / media utils**~~ — DONE:
  - Vestigial removed (float on `position:absolute`, zero effect): `.sl-tip > .sl-float-panel`, `.sl-dropdown-form`, `.sl-login-top--head .sl-login-dropdown-form`.
  - Rating cluster (already `inline-flex` pills): removed `float` from `.sl-rate .sl-rate-num/.sl-urating`, `.sl-rate-box .sl-rate`, `.sl-rate-like` + dead `.sl-min-rate` `float:none` resets. Verified pixel-identical across card-listing / rating-bar / comment / forum-post / min-rate.
  - `.sl-card-id`/`.sl-cart-id`: removed `float`, `inline-flex` keeps inline placement (verified).
  - Slider feature row `.sl-big-sl-icons`→flex (`flex-wrap`), `li{flex:none}` — icons pixel-identical, `ul` now self-contains height (no longer relies on parent clearfix). `.bx-pager`→flex, removed `float` from `.bx-pager-item`+`a`, **added `display:block` to `.bx-pager-item a`** (it relied on float to be block-level — `width`/`padding` were being dropped). Verified on `index.php?name=main`.
  - **Kept as intentional text-wrap floats** (flex worse): `.sl-card-image`/`.sl-icat`, `.sl-card-aside` (170px aside, article text wraps around it), `.sl-img-left`/`.sl-img-right`.
  - **Deferred to Batch 5:** `.sl-progress-info span` + `.sl-progress-info .sl-pull-right` — reverted a space-between attempt; the percent value is a `.sl-pull-right`, so do it together with the pull-right utility conversion.
  - Moderation menu `.sl-card-menu` → `display:inline-block` (mirrors the existing `.sl-editor-menu` precedent); dropped the vestigial `.sl-menu` selectors from the float group (`.sl-menu` in lite is always `.sl-editor-menu`, already `float:none`; standalone `.sl-menu` is admin-only, separate CSS). Verified with admin login on news listing: card-menu / trigger / meta-foot / rate-box / dropdown panel all identical; only the adjacent `.sl-card-read` nudges +4px at 1280 (inline-block whitespace gap — resolves when `.sl-meta-foot` goes flex in Batch 5).
- ~~**Batch 5 — nav / meta**~~ — DONE (header + meta):
  - Header `#hmenu`: `#topmenu > ul`→flex (`flex-wrap`), `#topmenu > ul > li > a` got explicit `display:block` (was block only via float); `#hmenu > .sl-wrp`→flex `space-between` **scoped to `@media (min-width:901px)`** (≤900 stacks via normal flow — flexing it at all widths collapsed the vertical mobile menu width); `.sl-search-form` float removed; dropped now-dead ≤900 `float:none` resets; removed `sl-clrfix` from the `#hmenu` wrapper in app/home.html. Verified pixel-identical on 4 widths incl. dropdowns.
  - `.sl-meta > ul`→flex (removed `.sl-meta > ul > li{float:left}`); ≤900 mobile override generalized to `.sl-meta > ul{display:block}` (was `.sl-pull-right` only). Verified: li pixel-identical desktop, mobile identical.
  - **Intentionally NOT converted (float is the right tool):**
    - `.sl-pull-right` global utility — kept `float:right`. It is consumed in many parents; the flex ones (`.sl-fmenu`, `.sl-fp-meta`, `.sl-forum-foot`) already neutralize it locally (margin-left:auto / order / float:none), but `.sl-search-line` relies on **text wrapping around** the right float (title+meta flow beside it), and `.sl-meta`/`.sl-meta-foot`/`.sl-forum-top` use it for simple right-alignment in block flow. A global flip to `margin-left:auto` would need every parent flex AND would break the search-line text-flow. Treat like the text-wrap image floats.
    - `.sl-progress-info span`(label) + `.sl-progress-info .sl-pull-right`(percent) — flex (both `space-between` and `margin-left:auto`) shrinks/wraps the label to 2 lines; the float keeps it on one line. Kept as-is.

~~Then **Task #2** (`!important` specificity) and **Task #3** (repeated-block dedup).~~

- **Task #2 — DONE** (see the gotchas note above): 21 → 7 `!important`.
- **Task #3 — DONE (selective):** scripted scan found 23 groups of selectors sharing an identical declaration body. Consolidated the clear win: the **list reset** `list-style:none; margin:0; padding:0`, which existed as 11 separate rules, → one shared rule near the top (14 selectors: `.sl-meta ul`, `.sl-main-list`(+ul), `.sl-menu ul`/`.sl-card-menu ul`, `.sl-related-list`, `.sl-block-menu ul`, `.sl-bx-wrapper ul`, `.sl-list-item`(+ul), `.sl-block-contact`, `#topbar ul`, `#topmenu ul`, `.sl-forum-list-info ul`). Cascade-safe (resets are order-independent; no intervening margin/padding rule for these selectors). Verified all compute to `none/0/0` on home/forum/view, no errors. The other 22 groups are **coincidental shared values across unrelated components** (e.g. `flex:1 1 auto;min-width:0` on `.sl-com-right`/`.sl-fp-right`, image-fill `object-fit:cover` trios, dropdown-panel mechanics) — merging would couple unrelated widgets and lose per-component locality, so left as-is.

## Regression fix — legacy `height:1%` clearfix vs CSS grid
Batch 1 made `#container` a CSS grid, so `#sidebar` became a grid item stretched to a **definite** (tall) height matching `#content`. The legacy IE clearfix hack `height: 1%` then resolved to 1% of that huge height (~68px), **collapsing** `.sl-block` (sidebar blocks) and `.sl-post-vote` — their bodies overflowed and overlapped the next block (login block over poll, forum block over news). Replaced `height: 1%` → `display: flow-root` on `.sl-block`, `.sl-post-vote`, `.sl-main-list > li` (modern float-containment, sizes to content, no definite-height side-effect). Verified on home (sidebar + central forum block) and main page: blocks size to content, `OVERLAPS: []`, no page errors.

## ALL PLAN ITEMS DONE
Float work (Batches 1–5): 29 → 7 (all remaining are text-wrap/text-flow). `!important` (Task #2): 21 → 7 (all remaining are visually-hidden + hover/active state). Dedup (Task #3): list-reset consolidated. theme.css net ~ −130 lines. Everything verified on 4 widths via Playwright, no page errors. Not committed — awaiting explicit instruction.

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
