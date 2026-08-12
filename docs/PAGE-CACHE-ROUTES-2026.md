# Page Cache Routes 2026

Work plan for widening the ready-page cache beyond the news list.

Status: planned, nothing implemented. Run the batches in order 1 → 2 → 3 → 4 → 5;
batch 1 changes no behaviour and every later batch depends on it. Update this
line as batches land.

No line numbers anywhere in this document on purpose: every reference names the
function, the file or the constant it points at, and that name is what to search
for.

## Problem

`checkPageCache()` answers true for one route: the news list, `op` empty, with
`cat` and `num` as the only extra keys. Everything else a guest opens is built
from scratch on every request — the news article itself, every page of the
`pages` module, the FAQ, the help section, the link and media catalogues. Those
are the expensive ones: an article renders its body through the parser, its
category, its related items and its whole comment thread, and the list page that
is cached today is the cheap half of the module.

The layer itself is finished and safe. What is missing is a per-route contract
for the rest of the site, and one decision about view counters.

## What already holds

Written down so the next reader does not re-derive it.

- **Entry conditions.** `checkPageCache()` refuses admin requests, anything that
  is not GET, logged-in users and admins, a request carrying a flash message, and
  a disabled `cache` setting. Option 2 of that setting narrows the cache to the
  front page. None of this needs to change.
- **Guests only.** Because logged-in visitors bypass the layer completely, the
  eight to fourteen `is_user()` branches inside every content module cannot leak
  into a stored page: what is stored is always the guest variant, and the only
  reader of that entry is another guest.
- **Fail-closed identity.** `getCacheRouteVars()` validates the entire query
  string against a per-route key => regex allowlist through
  `Cache::getQueryVars()`. An unknown key, a duplicate key or a malformed value
  returns null, and the request renders live. A route can therefore never be
  served a wrong entry; the worst outcome of an incomplete contract is a page
  that is not cached. Tracking keys (`utm_*`, `gclid`, `fbclid`, …) are dropped
  centrally before the check.
- **Key contents.** `getPageHash()` mixes the identity version (`pc3`), the CMS
  version, the cache epoch, the canonical `homeurl` host, the scheme, the theme,
  the locale and the validated parameters. Bumping the literal retires every
  stored page at once.
- **Visitor-bound content.** CSRF tokens, the captcha and the voting widget are
  stored as signed markers (`[[sldyn:type:par:hmac]]`) and rendered per serve.
  `checkDynamicMark()` is the allowlist of what may be signed. Any live
  `getSiteToken()` or `getCaptcha()` call during a cacheable build poisons the
  build (`checkCachePoison()`) and the page is never stored — an unregistered
  emitter disables caching instead of leaking.
- **Invalidation.** Every DB write bumps the epoch, which retires the whole
  layer; `cachegc` removes the files afterwards. No route needs its own
  invalidation.
- **Statistics survive a hit.** `setHead()` registers the referer and stats
  trackers through `addDeferredTask()` before it looks into the cache, and
  `setDeferredTasks()` runs on the hit path as well. Visit statistics therefore
  keep counting on cached pages today.
- **Browser caching is not part of this.** `cache_b` can only apply to an entry
  whose sidecar says `dyn = false`, and every page carrying a CSRF token holds a
  marker, so HTML is served `no-store` in practice. Widening routes changes
  nothing there; do not size the work by it.

## Decisions

**One contract table, not one literal per route.** The allowlist currently lives
as an inline array inside `checkPageCache()` and an inline regex map inside
`getCacheRouteVars()`. Two literals in two functions that must agree is the
shape that breaks when a third route appears. Both become one map keyed by
module name, holding the cacheable ops and the key => regex contract, and both
functions read it. This is batch 1 and it changes no behaviour.

**`word` never enters the identity.** Every module of the content family accepts
a free-text `word` filter. A filtered list must render live: the value space is
unbounded, the directory would grow with every crawler, and the hit rate of a
one-off search term is zero. The fail-closed contract gives this for free —
leaving `word` out of the map means those URLs are never stored.

**The canonical list URL is the empty op.** Those modules answer their list under
both an empty `op` and `op=liste`. Only the empty op enters the contract, since
that is the form the site links to. Confirm at implementation time that nothing
links the `liste` form; if something does, make it redirect rather than adding a
second identity for one page.

**View counters keep counting, through the sidecar.** `op=view` ends with
`UPDATE … SET counter = counter+1`, and a cache hit exits before the module
runs, so caching an article as it stands freezes both the counter and the number
printed on the page. Of the three ways out:

- Not caching `view` leaves the expensive half of every module uncached, which
  is the reason for this work.
- Letting the counter freeze for the TTL is cheap but silently wrong: the number
  on the page is the number at build time, and with a long `cache_t` a popular
  article stops counting for hours.
- Recording the count on the hit path is the one that keeps both. The build
  writes a small descriptor into the existing `.json` sidecar — the module and
  the record id — and the hit path validates it against an allowlist, exactly as
  `checkDynamicMark()` validates a marker, then runs the update through
  `addDeferredTask()` after the body is flushed.

The third one is the decision. The module keeps declaring the fact (it is the
module that knows what a view is); the cache layer only executes a shape it
recognises. Two consequences to accept: a hit costs one indexed UPDATE, so the
saving is the render and not the write, and the number printed inside the stored
page is still the build-time value while the stored count is exact. If the
printed number has to be exact too, it becomes a dynamic region, which is a
larger change and is not part of this plan.

## Route verdicts

The content family — `news`, `pages`, `faq`, `help`, `links`, `media`, `files` —
shares one router shape: `liste`, `view`, `add`, `send` with `cat`, `id`, `let`,
`num`, `word`. `add` and `send` are forms and stay live; `broken` and `loading`
in `links`, `media` and `files` are actions, not pages.

| module | cache | keys | notes |
|---|---|---|---|
| `news` | list, view | `cat`, `id`, `let`, `num` | counter on view; comment pager keys below |
| `pages` | list, view | `cat`, `id`, `let`, `num` | counter on view |
| `faq` | list, view | `cat`, `id`, `let`, `num` | counter on view |
| `help` | list, view | `cat`, `id`, `let`, `num` | counter on view |
| `links` | list, view | `cat`, `id`, `let`, `num` | counter on view; `broken`/`loading` stay live |
| `media` | list, view | `cat`, `id`, `let`, `num` | no counter |
| `files` | list, view | `cat`, `id`, `let`, `num` | counter on view; downloads stay live |
| `jokes` | list | `cat`, `num` | no view op |
| `content` | view | `id`, `num` | renders through `filterDoc()`, no replace rules |
| `changelog` | list | `page` | renders through `filterDoc()` |
| `main` | page | none | already reachable as the front page |
| `shop` | later | — | catalogue is cacheable, cart and checkout are not; own batch |
| `forum` | no | — | per-user read state, POST-heavy |
| `search`, `contact`, `order`, `recommend`, `whois` | no | — | forms and free-text input |
| `account`, `users` | no | — | never reached, the visitor is logged in |
| `rss`, `sitemap`, `voting`, `clients` | no | — | own transport, own headers, or actions |

**Comment pagination.** An article with comments is also reachable under `com`,
`all` and `at`. Leaving them out of the contract means page two of a thread
renders live, which is correct but wasteful on a busy article. Add them to the
`view` contract of the modules that carry comments once batch 4 works, as a
separate step with its own measurement — each key multiplies the number of
stored entries per article.

**Rotating content freezes.** A block that shows a random item — the FAQ tip in
the header is the one in this install — is baked into the stored page and stops
rotating for the lifetime of the entry. That is already true for the front page
today. Decide per block whether that is acceptable or whether the block becomes
a dynamic region; do not discover it after the fact.

## Batch 1 — one contract table

Move the route allowlist and the parameter contract into a single map read by
both `checkPageCache()` and `getCacheRouteVars()`. No route is added. The
existing page-cache tests must pass untouched, and an empirical check must show
the same set of URLs cached as before: front page and news list yes, article and
every other module no.

## Batch 2 — routes without a counter

`jokes` list, `changelog`, `content` view, `main`. These have no counter and no
comment thread, so they exercise the new table without needing the sidecar work.
Verify per route: an entry appears, a second request is served from it, a
logged-in visitor never receives it, an unknown query key renders live, and
editing the content in the admin panel retires the entry through the epoch.

## Batch 3 — the list pages of the content family

`news` already; add `pages`, `faq`, `help`, `links`, `media`, `files`. Lists
only, `view` still live. Same per-route verification, plus a check that `let` and
`cat` produce separate entries and that a `word` filter is never stored.

## Batch 4 — view pages and the counter descriptor

Extend `Cache::setMeta()`/`getMeta()` with an optional counter descriptor,
validate it on the hit path against an allowlist of module names, and run the
update through `addDeferredTask()`. Then add `view` to the contract of the
content family. Verify that the stored count rises on every hit while the page
is served from disk, that a corrupt or unknown descriptor is ignored rather than
executed, and that the sidecar contract still fails closed when body and hash
disagree.

## Batch 5 — shop catalogue

Only after its own audit: the module mixes a catalogue with a cart, a checkout
and partner pages in one router. Establish first that nothing in the catalogue
branch reads cart state or a per-visitor price, then cache the list and the item
page under the same contract as the rest.

## Out of scope

- Making the printed view count exact on a cached page. That turns the number
  into a dynamic region and belongs to a plan about the dynamic-region set, not
  to one about routes.
- Browser caching. `cache_b` cannot apply while every page carries a token
  marker; reviving it means changing what emits the markers.
- Per-route TTL. One `cache_t` for the whole layer is what the settings promise,
  and a second dial for the same decision is what the config screen just lost.
- Caching for logged-in users. It would require a per-group identity and a
  review of every `is_user()` branch in every module — a different project.
- The forum. Read state, moderation views and POST flows make it a separate
  audit with a different risk profile.

## Open

- The counter decision above needs a sign-off before batch 4 is written: it adds
  one write per hit and one shape to the sidecar contract.
- The module set of batches 2 and 3 is proposed, not confirmed.
- Whether the `liste` op form is linked anywhere, which decides between ignoring
  it and redirecting it.
