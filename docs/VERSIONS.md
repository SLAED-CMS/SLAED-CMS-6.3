# Versions

## 2026-07-10
- Added the route-aware SEO head contract with safe HTML/JSON serialization, canonical and robots policy, Open Graph, and typed JSON-LD.
- Normalized frontend `H1-H3` ownership, card and voting contexts, comments, related content, tables, landmarks, and category breadcrumbs.
- Added scoped Markdown heading offsets for article, card, comment, block, and forum contexts.
- Added `SeoSemanticsValidationTest` and the executable `tools/seo-audit.php` HTTP contract audit.
- Corrected the English and Ukrainian Open Graph locale identifiers. `hreflang` remains disabled until languages have stable public URLs.

## 2026-05-20
- Responsive baseline closed for `lite` and `admin`.
- `lite` mobile/tablet layout fixed without changing the desktop visual style.
- Top menu, dropdowns, table wrappers, forum posts, comments, cards, media, and admin forms were verified after the CSS updates.
- Browser verification was completed with Playwright and Chromium.
- Authenticated admin pages were verified after login.
- Remaining component-specific backlog items are non-blocking: CodeMirror editor surface on the admin template page, statistic chart fixed-width images, and monitor widget internal widths.
