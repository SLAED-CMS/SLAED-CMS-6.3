// Author: Eduard Laas
// 2005 - 2026 SLAED
// License: MIT
// Website: slaed.net
//
// The label crawl. It walks the panel, the site as a member and the site as a guest - three sets of form rows, and
// the third is the only one shown the login, registration and lost-password forms - and asks of every rendered page
// the questions no
// count in tools/ui-audit.php can ask, because they are questions about a document and not about a file: does a
// `for` reach a control, is an id unique, does an aria IDREF resolve - and, the half that actually proves a
// migration, does the radio group, the editor and the hint carry the name they are supposed to carry.
//
//   node tools/label-audit.mjs            crawl, and fail on anything outside tools/label-audit-baseline.json
//   node tools/label-audit.mjs --store    rewrite that baseline from this run, refusing to store a count that grew
//   node tools/label-audit.mjs --census   also print, per route, how many rows, groups, editors and hints were seen
//   node tools/label-audit.mjs --only=x   audit only routes whose signature contains x; the walk itself is unchanged
//
// Not part of `npm run ui:gates`, and that is deliberate: the gates run from tools/hooks/pre-commit on every commit
// and this needs an HTTPS stand, a browser and administrator credentials. The hook prints a reminder instead, the
// same answer the tree already gives the screenshot rig. Moving it into the gates means changing the hook with it.
//
// The session is the trap tools/ui-shots.json records in its own _base note: the cookie is `secure` when homeurl is
// https, so a run over http is handed a cookie the browser drops - no session and no error. An unauthenticated crawl
// walks the admin routes, is served the login form on every one of them, and reports a clean panel it never saw. So
// the login is proved before a page is counted, and a run that checked nothing exits non-zero rather than green.
//
// Coverage is stored beside the violations and judged apart from them. Record-bound forms are reached by following
// the links a list page carries, so an empty list or a renamed link makes violations vanish - and a baseline holding
// violations alone reads that as a fix. Violations down is a pass; coverage down is its own failure and offsets nothing.

import { chromium } from 'playwright';
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { setSeededState, deleteSeededState } from './ui-probe.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const conf = JSON.parse(readFileSync(join(root, 'tools/ui-shots.json'), 'utf8'));
const args = new Map(process.argv.slice(2).map((a) => {
  const cut = a.indexOf('=');
  return cut === -1 ? [a.replace(/^--/, ''), true] : [a.slice(2, cut), a.slice(cut + 1)];
}));

const baseFile = join(root, 'tools/label-audit-baseline.json');
const user = process.env[conf.env.user] || '';
const pass = process.env[conf.env.pass] || '';
const only = typeof args.get('only') === 'string' ? args.get('only') : '';
const store = args.has('store');
const census = args.has('census');
const limit = Number(args.get('limit')) > 0 ? Number(args.get('limit')) : 1500;
const adminPath = new URL(conf.auth.admin.url, conf.base).pathname;
const origin = new URL(conf.base).origin;

// The ops a crawler is allowed to open. An allowlist and not a denylist: this panel drives delete, activate, save and
// backup from plain links, so one op forgotten in a denylist is a stand wiped by its own audit. Everything here either
// renders a form or renders a view, and what is left out is reported as a skipped op rather than passed over silently
const OPEN = new Set(['', 'view', 'edit', 'add', 'new', 'newuser', 'passlost', 'config', 'info', 'list', 'search',
    'profil', 'profile', 'privat', 'favorites', 'settings', 'docs', 'stat', 'preview', 'reply', 'newtopic', 'quote', 'contact',
    'edithome', 'oauthlist', 'banlist', 'liste', 'rules', 'stats', 'partners', 'clients', 'fileadd', 'fileedit', 'subadd', 'addedit']);
// The parameters that name one record rather than one route. Every one of them is dropped from the signature, and the
// four that identify a record leave the `#rec` mark behind, so a hundred categories are one route that is known to be
// record-bound. Measured, without this the panel alone offered more than five hundred signatures - eighty six category
// ids, forty nine banned addresses - and the walk hit its own limit with the queue still full.
// Everything not named here keeps its value in the signature, which is what holds the tab strips of the panel apart:
// name=help&status=1 is a different screen from the tab beside it, name=categories&modul=faq is the same screen twice
// Addresses the walk asks for outright instead of waiting to be linked to them. The registration and lost-password
// forms are linked only from the page a guest is shown, so whether the member side ever opened them depended on the
// walk - measured, `site:account:newuser` was in one baseline and not in the next, for no change anybody made. They
// are also the lite form rows batch 1 rewrites, so they are the last thing that should be reached by luck
const SEED = ['/index.php?name=account&op=newuser', '/index.php?name=account&op=passlost'];
const REC = new Set(['id', 'cid', 'pid', 'qid']);
const LOOSE = new Set([...REC, 'name', 'op', 'num', 'cat', 'token', 'sid', 'page', 'uname', 'redirect', 'go', 'prov',
    'mod', 'modul', 'new_ip', 'search', 'last', 'pnum', 'com', 'typ', 'let']);

// One route, named by what it renders rather than by which record it happened to open, and by who was looking. The
// side is told rather than read off the address because one address is two screens: signed in, index.php?name=account
// is a profile, and the registration and lost-password forms redirect away from it entirely. Walking the site only as
// a member never opens the three forms a guest is shown, and those are lite form rows like any other
function getRouteSig(href, side) {
  const url = new URL(href);
  const name = (url.searchParams.get('name') || 'home').toLowerCase();
  const op = (url.searchParams.get('op') || '').toLowerCase();
  const rest = [];
  let rec = false;
  for (const [raw, val] of url.searchParams) {
    // An href carrying a double-escaped ampersand arrives as a parameter literally named `amp;page`, which is a
    // different name from `page` and would walk the pager as if it were a screen of its own
    const key = raw.startsWith('amp;') ? raw.slice(4) : raw;
    if (REC.has(key)) rec = true;
    if (!LOOSE.has(key)) rest.push(key + '=' + val);
  }
  rest.sort();
  return side + ':' + name + ':' + op + (rec ? '#rec' : '') + (rest.length ? '?' + rest.join('&') : '');
}

// Sign one context in through the form the manifest names and prove the session took two ways, the way the screenshot
// rig proves it: something only a session shows must appear, and the password field must be gone. Either alone passes
// for a login that never happened, and a crawl that believes it is signed in reports the login page as a clean panel.
// The wait is on the password field leaving rather than on a load event: the altcha widget of the panel solves its
// proof of work on submit, so the navigation arrives seconds after the click and a load state that already fired
// resolves at once - which read as a finished login and handed the crawl the login form to audit
async function setSession(page, kind) {
  const form = conf.auth[kind];
  await page.goto(conf.base + form.url, { waitUntil: 'domcontentloaded' });
  await page.fill(form.user, user);
  await page.fill(form.pass, pass);
  await page.locator(form.submit).first().click();
  try {
    await page.locator(form.gone).first().waitFor({ state: 'detached', timeout: 60000 });
  } catch {
    throw new Error('login as ' + kind + ' did not take: ' + form.gone + ' is still on the page after 60s');
  }
  await page.waitForLoadState('load');
  if (!(await page.locator(form.proof).first().count())) throw new Error('login as ' + kind + ' did not take: ' + form.proof + ' is absent');
}

// Everything one document has to answer for. Run per frame and not per page: TinyMCE puts its editable body in a
// separate document, an IDREF does not cross that boundary, and a main-frame query sees the iframe and stops - so an
// editor with no name at all reads as clean. Ids are unique per document, which is the same reason
function getFrameFindings() {
  const LABELABLE = 'button, input:not([type="hidden"]), meter, output, progress, select, textarea';
  // `.sl-opt-line` is the third row shape: the settings page owns its own markup, so a caption, its hint and the
  // control they belong to sit in a line of a tile and not in a form row, and a hint there has a row like any other
  const ROW = '.sl-div-item, .sl-form-row, .sl-value-row, .sl-opt-line';
  const out = [];
  // A selector is only how a violation is named, but the name is the key the baseline is stored under, so a class the
  // page puts on and takes off would file the same element under two keys - and a run where the editor happened not to
  // hold focus would report a violation that had not moved as new. State is dropped; identity is kept
  const STATE = /(^is-|-(focused|active|open|selected|hidden|disabled|hover|dragging)$)/;
  const sel = (el) => {
    const list = typeof el.className === 'string' ? el.className.trim().split(/\s+/).filter((one) => one && !STATE.test(one)) : [];
    return el.tagName.toLowerCase() + (list.length ? '.' + list.join('.') : '');
  };
  // An id that is nothing but digits is a record number, not a field name: the record view of this CMS prints the row
  // id on two elements at once, so the duplicate is real and its text is whichever record the walk happened to open.
  // Left as it stands it files the same defect under a new key every run and the baseline never settles
  const add = (kind, el, note) => out.push({ kind, sel: el ? sel(el) : '', note: /^\d+$/.test(note || '') ? '<record>' : (note || '') });
  const text = (el) => (el ? (el.textContent || '').replace(/\s+/g, ' ').trim() : '');
  const refs = (el, attr) => (el.getAttribute(attr) || '').split(/\s+/).filter(Boolean);
  // Named by a reference that resolves to something with text in it. An empty target is a worse answer than no
  // attribute: the name computes to the empty string and the reader announces nothing, with the attribute in place
  const namedby = (el) => refs(el, 'aria-labelledby').some((id) => text(document.getElementById(id)) !== '');
  const named = (el) => {
    if (namedby(el)) return true;
    if ((el.getAttribute('aria-label') || '').trim() !== '') return true;
    if (el.id) {
      for (const lab of document.querySelectorAll('label[for]')) if (lab.getAttribute('for') === el.id && text(lab) !== '') return true;
    }
    if (el.closest('label') && text(el.closest('label')) !== '') return true;
    return (el.getAttribute('title') || '').trim() !== '';
  };
  const seen = new Map();
  for (const el of document.querySelectorAll('[id]')) if (el.id) seen.set(el.id, (seen.get(el.id) || 0) + 1);
  for (const [id, num] of seen) for (let i = 1; i < num; i++) add('dup-id', null, id);
  for (const el of document.querySelectorAll('label[for]')) {
    const id = el.getAttribute('for');
    const hit = id ? document.getElementById(id) : null;
    if (!hit) add('for-missing', el, id || '');
    else if (!hit.matches(LABELABLE)) add('for-not-labelable', el, id + ' -> ' + hit.tagName.toLowerCase());
  }
  for (const el of document.querySelectorAll('label label')) add('label-nested', el, '');
  for (const el of document.querySelectorAll('label')) {
    if (el.hasAttribute('for') || el.querySelector(LABELABLE)) continue;
    add('label-empty', el, '');
  }
  for (const attr of ['aria-labelledby', 'aria-describedby']) {
    for (const el of document.querySelectorAll('[' + attr + ']')) {
      const list = refs(el, attr);
      if (!list.length) {
        add('idref-blank-attr', el, attr);
        continue;
      }
      for (const id of list) {
        const hit = document.getElementById(id);
        if (!hit) add('idref-missing', el, attr + ' ' + id);
        else if (text(hit) === '') add('idref-empty', el, attr + ' ' + id);
      }
    }
  }
  for (const el of document.querySelectorAll('[aria-labelledby][aria-describedby]')) {
    const both = refs(el, 'aria-labelledby').filter((id) => refs(el, 'aria-describedby').includes(id));
    if (both.length) add('name-is-hint', el, both.join(' '));
  }
  for (const el of document.querySelectorAll('.sl-radio-group')) {
    if (el.getAttribute('role') !== 'group') add('group-no-role', el, '');
    if (!namedby(el)) add('group-no-name', el, '');
  }
  // An editable nobody can reach needs no name, and asking for one files a permanent entry against a vendor. Three
  // shapes are dropped: the source textarea a rich driver hides behind its own mount, the second ProseMirror the
  // editor keeps at zero size for the mode it is not in, and the clipboard shim toastui parks at minus a thousand
  // pixels with zero opacity - measured, 1280 by 180, so a floor on the box alone does not reach it.
  // Every test here is a rendered fact and none is a class name, so they hold for whatever the next driver leaves lying about
  const shown = (el) => {
    if (el.hasAttribute('hidden') || el.closest('[aria-hidden="true"]')) return false;
    for (let one = el; one; one = one.parentElement) {
      const own = getComputedStyle(one);
      if (own.display === 'none' || own.visibility === 'hidden' || Number(own.opacity) === 0) return false;
    }
    const box = el.getBoundingClientRect();
    if (box.right < 0 || box.bottom < 0) return false;
    return box.width >= 24 && box.height >= 24;
  };
  const box = new Set();
  for (const el of document.querySelectorAll('[role="textbox"], textarea')) box.add(el);
  for (const el of document.querySelectorAll('[contenteditable]')) if (el.isContentEditable) box.add(el);
  for (const el of box) if (!shown(el)) box.delete(el);
  for (const el of box) if (!named(el)) add('editor-unnamed', el, el.id || '');
  const hint = new Set();
  for (const el of document.querySelectorAll('.sl-div-label .sl-small')) hint.add(el);
  for (const el of document.querySelectorAll('[id$="-hint"]')) hint.add(el);
  for (const el of hint) {
    if (!el.id) {
      add('hint-unnamed', el, '');
      continue;
    }
    const row = el.closest(ROW);
    if (!row) {
      add('hint-no-row', el, el.id);
      continue;
    }
    const tied = Array.from(row.querySelectorAll('[aria-describedby]')).some((one) => refs(one, 'aria-describedby').includes(el.id));
    if (!tied) add('hint-unreferenced', el, el.id);
  }
  return {
    out,
    census: {
      rows: document.querySelectorAll('.sl-div-item, .sl-form-row').length,
      groups: document.querySelectorAll('.sl-radio-group').length,
      editors: box.size,
      hints: hint.size,
      values: document.querySelectorAll('.sl-form-value, .sl-value-text').length,
      labels: document.querySelectorAll('label').length,
    },
  };
}

if (!user || !pass) {
  console.error('Set ' + conf.env.user + ' and ' + conf.env.pass + ' first: without a session every admin route answers with the login form');
  process.exit(2);
}

// A narrowed run still walks every route, so it would store full coverage beside the violations of a handful of pages,
// and the next full run would report everything it skipped as new. The two flags cannot be combined
if (store && only) {
  console.error('--store and --only cannot be combined: a narrowed run would store its coverage as if it had audited all of it');
  process.exit(2);
}

// A crawl that dies without saying so is the failure this tool exists to prevent, one level up: the caller sees a run
// that stopped and no reason, and the temptation is to store whatever it managed. Both of these end the run loudly
process.on('unhandledRejection', (err) => {
  console.error('the crawl died on an unhandled rejection: ' + ((err && err.stack) || err));
  process.exit(2);
});
process.on('uncaughtException', (err) => {
  console.error('the crawl died on an uncaught exception: ' + ((err && err.stack) || err));
  process.exit(2);
});

let browser;
try {
  browser = await chromium.launch();
} catch (err) {
  console.error('chromium did not start: ' + err.message);
  console.error('Run: npx playwright install chromium');
  process.exit(2);
}

const hurt = [];
const quiet = [];
const sess = new Map();
const found = new Map();
const routes = new Set();
const record = new Set();
const skipped = new Map();
const lines = [];
let seed = { cookies: [], sown: [] };
let walked = 0;

try {
  // Three lookers, because the tree renders three different sets of form rows: `guest` signs in nowhere and is the
  // only one shown the login, registration and lost-password forms; `site` is a member; `admin` is the panel
  for (const kind of ['guest', 'site', 'admin']) {
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
    sess.set(kind, ctx);
    if (kind === 'guest') continue;
    const page = await ctx.newPage();
    await page.setViewportSize({ width: 1280, height: 1000 });
    await setSession(page, kind);
    await page.close();
  }
  // The manifest carries pages a link never leads to, and one of them - the OAuth card - renders only for a browser
  // already holding a pending flow. The same seeding the screenshot rig uses hands every context that state, so the
  // crawl audits the card rather than the account page the handler serves a browser arriving without it
  seed = setSeededState(conf);
  for (const ctx of sess.values()) if (seed.cookies.length) await ctx.addCookies(seed.cookies);
  const queue = [{ url: conf.base + '/', kind: 'guest' }, { url: conf.base + '/', kind: 'site' },
    { url: conf.base + conf.auth.admin.url, kind: 'admin' }];
  for (const item of conf.pages || []) {
    if (item.auth === 'admin') {
      queue.push({ url: conf.base + item.url, kind: 'admin' });
      continue;
    }
    queue.push({ url: conf.base + item.url, kind: 'guest' });
    queue.push({ url: conf.base + item.url, kind: 'site' });
  }
  for (const one of SEED) {
    queue.push({ url: conf.base + one, kind: 'guest' });
    queue.push({ url: conf.base + one, kind: 'site' });
  }
  const asked = new Set(queue.map((one) => getRouteSig(one.url, one.kind)));
  while (queue.length && walked < limit) {
    const step = queue.shift();
    const page = await sess.get(step.kind).newPage();
    await page.setViewportSize({ width: 1280, height: 1000 });
    try {
      const res = await page.goto(step.url, { waitUntil: 'load', timeout: 30000 });
      // Two answers this CMS gives on purpose. A 404 is an alphabetical index with no records behind the letter, and a
      // 403 to a guest is a module that members only may read - measured, four of them, and every one of them renders
      // for the member walk. Both are reported and not counted rather than failing the run. A 403 to a member or to
      // the panel is not in that class and stays a failure: it means access was lost, and a run that walked the refusal
      // page instead would report the whole area as clean. Anything else past 400 is a route that should have rendered
      if (res && (res.status() === 404 || (res.status() === 403 && step.kind === 'guest'))) {
        quiet.push('  ' + getRouteSig(step.url, step.kind) + ' answered ' + res.status() + ', so nothing was audited there');
        await page.close();
        continue;
      }
      if (!res || res.status() >= 400) {
        hurt.push('  ' + getRouteSig(step.url, step.kind) + ' answered ' + (res ? res.status() : 'nothing'));
        await page.close();
        continue;
      }
      await page.waitForTimeout(conf.settle || 500);
      // The signature of what was served, not of what was asked for: a route that redirects rendered something else,
      // and recording the address would put a page nobody can reach into the coverage the next run is measured against
      const sig = getRouteSig(page.url(), step.kind);
      if (routes.has(sig)) {
        await page.close();
        continue;
      }
      routes.add(sig);
      if (sig.includes('#rec')) record.add(sig);
      walked++;
      // A walk of several hundred routes takes long enough that silence is indistinguishable from a hang. The line
      // goes to stderr so the report on stdout stays the only thing a caller has to read
      process.stderr.write(String(walked).padStart(4) + '  ' + sig + '\n');
      if (!only || sig.includes(only)) {
        for (const frame of page.frames()) {
          let said;
          try {
            said = await frame.evaluate(getFrameFindings);
          } catch {
            continue;
          }
          const tag = frame === page.mainFrame() ? '' : 'frame ';
          for (const one of said.out) {
            const key = one.kind + '|' + sig + '|' + tag + (one.note || one.sel);
            found.set(key, (found.get(key) || 0) + 1);
          }
          if (census && frame === page.mainFrame()) {
            const num = said.census;
            lines.push('  ' + sig + '  rows ' + num.rows + '  groups ' + num.groups + '  editors ' + num.editors
              + '  hints ' + num.hints + '  values ' + num.values + '  labels ' + num.labels);
          }
        }
      }
      for (const href of await page.$$eval('a[href]', (list) => list.map((one) => one.href))) {
        let url;
        try {
          url = new URL(href);
        } catch {
          continue;
        }
        if (url.origin !== origin) continue;
        if (url.pathname !== adminPath && url.pathname !== '/index.php' && url.pathname !== '/') continue;
        const op = (url.searchParams.get('op') || '').toLowerCase();
        if (!OPEN.has(op)) {
          skipped.set(op, (skipped.get(op) || 0) + 1);
          continue;
        }
        // A link keeps the eyes that found it: a guest following a link stays a guest, and only an address inside the
        // panel changes that, because the panel has one kind of visitor
        const kind = url.pathname === adminPath ? 'admin' : (step.kind === 'admin' ? 'site' : step.kind);
        const next = getRouteSig(url.href, kind);
        if (asked.has(next) || routes.has(next)) continue;
        asked.add(next);
        queue.push({ url: url.href, kind });
      }
    } catch (err) {
      hurt.push('  ' + getRouteSig(step.url, step.kind) + ' failed: ' + err.message);
    }
    await page.close();
  }
  // A walk that stopped because it ran out of allowance did not finish, and the routes it never opened are routes the
  // next run may open instead - coverage that moves between runs for no reason anybody changed. Say so and refuse
  if (queue.length) hurt.push('  the walk stopped at its ' + limit + '-route limit with ' + queue.length + ' still queued');
} catch (err) {
  console.error('the crawl could not start: ' + err.message);
  await browser.close();
  deleteSeededState(seed);
  process.exit(2);
}
await browser.close();
deleteSeededState(seed);

if (!walked) {
  console.error('nothing was checked, so nothing is proved: no route rendered');
  process.exit(2);
}

const first = !existsSync(baseFile);
const base = first ? { coverage: { pages: 0, routes: [], records: [] }, violations: {} } : JSON.parse(readFileSync(baseFile, 'utf8'));
const was = base.violations || {};
const cov = base.coverage || { pages: 0, routes: [], records: [] };
const grew = [];
const gone = [];
for (const [key, num] of Array.from(found.entries()).sort()) {
  const old = was[key] || 0;
  if (num > old) grew.push('  ' + (old ? 'up from ' + old + ' to ' + num : 'new x' + num) + '  ' + key);
}
// What left the baseline is the only thing a batch below is trying to produce, and reading it out of a JSON diff is
// how it gets skipped. A drop never fails a run; it is printed so the proof is on screen beside the failures
const fell = [];
for (const [key, num] of Object.entries(was)) {
  const now = found.get(key) || 0;
  if (now < num) fell.push('  ' + (now ? 'down from ' + num + ' to ' + now : 'gone, was x' + num) + '  ' + key);
}
for (const sig of cov.routes || []) if (!routes.has(sig)) gone.push('  route no longer reached: ' + sig);
for (const sig of cov.records || []) if (!record.has(sig)) gone.push('  record-bound form no longer reached: ' + sig);
if (walked < (cov.pages || 0)) gone.push('  pages checked fell from ' + cov.pages + ' to ' + walked);

let total = 0;
for (const num of found.values()) total += num;
console.log('label audit: ' + walked + ' routes rendered, ' + record.size + ' of them record-bound, ' + found.size
  + ' distinct violations over ' + total + ' sightings');
if (census) {
  console.log('\ncensus, per route:');
  for (const line of lines.sort()) console.log(line);
  console.log('\nops the walk did not open, and the number of links offering them:');
  for (const [op, num] of Array.from(skipped.entries()).sort((a, b) => b[1] - a[1])) console.log('  ' + (op || '(none)') + '  x' + num);
}
if (hurt.length) {
  console.log('\nroutes that did not render:');
  for (const line of hurt) console.log(line);
}
if (quiet.length) {
  console.log('\nroutes with nothing behind them, reported and not counted:');
  for (const line of quiet) console.log(line);
}
if (fell.length) {
  console.log('\nbelow the baseline, which is what a batch is for:');
  for (const line of fell) console.log(line);
}
if (gone.length) {
  console.log('\ncoverage fell, which no drop in violations offsets:');
  for (const line of gone) console.log(line);
}
if (grew.length) {
  console.log('\noutside the baseline:');
  for (const line of grew) console.log(line);
}

if (store) {
  // Everything is above nothing, so the very first store cannot be held to the ratchet it is creating. Every store
  // after it can: a count above the stored one is a regression whatever else the run did, and so is a route that
  // stopped rendering - a baseline written over either would file the regression as the new floor
  if (!first && (grew.length || gone.length || hurt.length)) {
    console.error('\nRefusing to store: a count is above the stored one, coverage fell, or a route did not render.');
    process.exit(1);
  }
  if (first && hurt.length) {
    console.error('\nRefusing to store a first baseline while a route did not render: it would file the gap as normal.');
    process.exit(1);
  }
  const note = 'What the label crawl finds today, so a run fails on what it finds tomorrow. Each key is kind|route|id-or-selector'
    + ' and each value how often it was seen. Every entry is a defect a plan names; the file shrinks and never grows. Coverage is'
    + ' judged apart from it: a route that stopped rendering hides violations rather than fixing them.';
  const out = {
    generated: new Date().toISOString().replace(/\.\d+Z$/, 'Z'),
    _: note,
    coverage: { pages: walked, routes: Array.from(routes).sort(), records: Array.from(record).sort() },
    violations: Object.fromEntries(Array.from(found.entries()).sort()),
  };
  writeFileSync(baseFile, JSON.stringify(out, null, 2) + '\n');
  console.log('\nstored tools/label-audit-baseline.json: ' + found.size + ' entries, ' + routes.size + ' routes');
  process.exit(0);
}

const bad = grew.length || gone.length || hurt.length;
console.log(bad ? '\nFAIL' : '\nPASS - nothing outside the baseline, and coverage held');
process.exit(bad ? 1 : 0);
