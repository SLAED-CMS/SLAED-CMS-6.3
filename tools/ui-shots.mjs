// Author: Eduard Laas
// 2005 - 2026 SLAED
// License: MIT
// Website: slaed.net
//
// The screenshot and contrast runner for the theme etalon work. It walks tools/ui-shots.json, drives the
// interactions each page names - hover, focus, open - and does one of three jobs:
//
//   node tools/ui-shots.mjs --capture    write or refresh the PNG baselines under tools/ui-baseline
//   node tools/ui-shots.mjs --check      compare the tree against those baselines and fail on any drift
//   node tools/ui-shots.mjs --contrast   emit tools/ui-contrast.json, the pairs that really meet on screen
//   node tools/ui-shots.mjs --newtheme   build a scratch theme from an etalon and render every page of the manifest in it
//
// `--only=settings,profile` limits any job to the named pages. Every page of every mode is one piece of work, and
// `workers` of the manifest walk them side by side; a mode keeps contexts of its own, because a context is what holds
// the mode cookie. `--workers=N` overrides the manifest for one run.
//
// Beside every PNG a capture writes the DOM of the same state: the box, the paint and the text of every rendered
// element, and the files the state loaded. A check takes that snapshot first and shoots only a state whose snapshot
// moved, which loaded a public file the tree changed, or which a changed rule of a changed stylesheet still matches;
// every other state is reported as unchanged without a picture. `--pixels` shoots every state regardless.
//
// The last of the four is the HTTP half of the theme-creation gate. ThemeCreationTest asks the static half of the
// same question - does a copy of an etalon with only its API block repainted audit clean - and this asks the half a
// file cannot answer: does the CMS actually serve pages in it. Both build and remove the copy through one lifecycle,
// tests/Support/theme_scratch.php, reached here through the make, pick and gone jobs of tests/Support/theme_probe.php.
//
// A page may also name a `probe` of its own, for a state no address carries - the OAuth card renders only for a browser
// already holding a pending flow. That seeding is shared with the label crawl and lives in tools/ui-probe.mjs.
//
// A contrast pair existing only on hover is invisible to a crawler that never hovers, which is why the
// states live in the manifest and not in the runner. Credentials come from the environment, never the file.
//
// Before capturing, empty storage/cache/data and storage/cache/templates: a warm-cache comparison compares
// caches instead of renders.

import { chromium } from 'playwright';
import { PNG } from 'pngjs';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { gzipSync, gunzipSync } from 'node:zlib';
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync, mkdirSync, existsSync, readdirSync, statSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { getProbeAnswer, setSeededState, deleteSeededState } from './ui-probe.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const conf = JSON.parse(readFileSync(join(root, 'tools/ui-shots.json'), 'utf8'));
const args = new Map(process.argv.slice(2).map((a) => {
  const cut = a.indexOf('=');
  return cut === -1 ? [a.replace(/^--/, ''), true] : [a.slice(2, cut), a.slice(cut + 1)];
}));

// The two-word guard. A committed baseline cannot be the "before" on a live stand: its own content moves the page height
// between runs, so a check against it reports the week rather than the change. `--before` and `--after` capture the pair
// minutes apart into one fixed directory outside the repository, and `--after` does every step around the comparison
// that is otherwise a command to remember - the caches, the contrast registry when a palette moved, and the audit
const guard = args.has('before') ? 'before' : args.has('after') ? 'after' : '';
const guardDir = join(tmpdir(), 'slaed-ui-guard');
const job = (guard === 'before' || args.has('capture')) ? 'capture' : args.has('contrast') ? 'contrast' : args.has('newtheme') ? 'newtheme' : 'check';
// `--only=a,b` walks the named pages and nothing else, one login for all of them
const only = typeof args.get('only') === 'string' ? new Set(args.get('only').split(',').map((s) => s.trim()).filter(Boolean)) : null;
// A batch cannot trust the committed baseline as its "before": the stand's own data moves between runs, so a
// check against it reports the week rather than the change. --out= sends a capture somewhere outside the
// repository, which is what lets a batch compare its own two captures instead
const outRel = guard ? guardDir : (typeof args.get('out') === 'string' ? args.get('out') : conf.out);
const outDir = resolve(root, outRel);
const user = process.env[conf.env.user] || '';
const pass = process.env[conf.env.pass] || '';
const workers = Math.max(1, parseInt(args.get('workers'), 10) || conf.workers || 1);
const pixels = args.has('pixels');
// The floors are measured by the method that shoots; a file written by another method is measured again whole
const floorMethod = 2;

// Run one command from the repository root and let its output through; the exit code is the caller's to read
function runStep(cmd, list) {
  try {
    execFileSync(cmd, list, { cwd: root, stdio: 'inherit' });
    return 0;
  } catch (err) {
    return typeof err.status === 'number' ? err.status : 1;
  }
}

// Whatever git says has changed against HEAD, staged or not, so a step can be skipped when nothing it guards was touched
function getChangedFiles() {
  try {
    const out = execFileSync('git', ['diff', 'HEAD', '--name-only'], { cwd: root, encoding: 'utf8' });
    const add = execFileSync('git', ['ls-files', '--others', '--exclude-standard'], { cwd: root, encoding: 'utf8' });
    return (out + add).split('\n').map((l) => l.trim()).filter(Boolean);
  } catch {
    return [];
  }
}

// The rendered tree has to be the tree on disk: a stored parser output or compiled template may predate the edit,
// so a comparison of caches is not a comparison of themes
function setCachesEmpty() {
  for (const dir of ['storage/cache/data', 'storage/cache/templates']) {
    const full = join(root, dir);
    if (!existsSync(full)) continue;
    for (const item of readdirSync(full)) rmSync(join(full, item), { recursive: true, force: true });
  }
}

// The files of one state in a capture: its picture and the snapshot of its DOM
function getStateFiles(name) {
  return [name + '.png', name + '.dom.gz'];
}

if (guard) {
  // Without credentials the rig skips every state that needs a session and captures the rest logged out, which is a
  // different set from the one a full run writes. A guard that quietly guards two thirds of the manifest is worse than none
  if (!user || !pass) {
    console.error('Set ' + conf.env.user + ' and ' + conf.env.pass + ' first: without them the walk skips every page that needs a session');
    process.exit(1);
  }
  if (guard === 'before') {
    // `--only` recaptures the named pages into a pair that keeps every other page: wiping the directory left the pages it
    // did not name without a "before", so a full `--after` reported them missing. The names are built the way setOneShot() builds them.
    // The noise floors and the saved sessions stay: a floor is a property of the page and not of the tree, and a session saves a login
    if (only && existsSync(guardDir)) {
      const tails = conf.viewports.flatMap((view) => conf.modes.map((mode) => '-' + view.name + (mode === 'auto' ? '' : '-' + mode)));
      const heads = conf.pages.filter((item) => only.has(item.name)).flatMap((item) => [item.name, ...(item.steps || []).filter((step) => step.shot).map((step) => item.name + '-' + step.shot)]);
      for (const head of heads) for (const tail of tails) for (const file of getStateFiles(head + tail)) rmSync(join(guardDir, file), { force: true });
    } else if (existsSync(guardDir)) {
      for (const file of readdirSync(guardDir)) if (file.endsWith('.png') || file.endsWith('.dom.gz')) rmSync(join(guardDir, file), { force: true });
    }
    console.log('before: capturing the tree you are about to change into ' + guardDir);
  } else {
    const shot = existsSync(guardDir) ? readdirSync(guardDir).filter((f) => f.endsWith('.png')).length : 0;
    if (!shot) {
      console.error('Nothing to compare against: run `npm run ui:before` before you start editing, not after');
      process.exit(1);
    }
    console.log('after: comparing against the ' + shot + ' images captured in ' + guardDir);
  }
  setCachesEmpty();
}

// Count the pixels two PNGs differ in, by the sum of the three channel differences past 12; equal bytes are equal pictures
function getPixelDiff(one, two) {
  if (Buffer.compare(one, two) === 0) return { ratio: 0, note: '' };
  const imgA = PNG.sync.read(one);
  const imgB = PNG.sync.read(two);
  if (imgA.width !== imgB.width || imgA.height !== imgB.height) return { ratio: 1, note: 'size changed' };
  const a = imgA.data;
  const b = imgB.data;
  let bad = 0;
  for (let i = 0; i < a.length; i += 4) {
    if (Math.abs(a[i] - b[i]) + Math.abs(a[i + 1] - b[i + 1]) + Math.abs(a[i + 2] - b[i + 2]) > 12) bad++;
  }
  return { ratio: bad / (a.length / 4), note: '' };
}

// Stretch the viewport to the document once, so the whole page is one plain screenshot. A full-page screenshot stretches
// the viewport by itself for every shot, and the resize it fires sets the scripts of the page moving their floating panels
// and scroll marks each time: measured, two shots in a row of the front page at 1200 points never agreed in ten tries.
// Stretched once and given a moment, the page agrees at the second shot, and the picture is the one a full-page shot takes
// A box sized by the viewport grows with the stretch and the document with it, so the stretch is repeated while it grows
async function setStretched(page, view) {
  let high = view.height;
  for (let i = 0; i < 4; i++) {
    const tall = await page.evaluate(() => Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0));
    if (tall <= high && i) break;
    high = Math.max(high, tall);
    await page.setViewportSize({ width: view.width, height: high });
    await page.waitForTimeout(conf.stretch ?? 150);
  }
  await setListsStill(page);
}

// Scroll every list box to its first chosen option, or to its top. The browser scrolls a chosen option into view at a moment
// of the layout of its own, and two runs of the settings page drew the same list ten points apart
async function setListsStill(page) {
  await page.evaluate(() => {
    for (const list of document.querySelectorAll('select[multiple], select[size]')) {
      const one = list.querySelector('option:checked');
      list.scrollTop = one ? one.offsetTop - list.firstElementChild.offsetTop : 0;
    }
  });
}

// Shoot the stretched page until `need` consecutive shots agree: two for a picture that becomes a reference or confirms a
// difference, one for a check, whose difference is confirmed before it is reported. The count of tries is reported, so a
// page that never settles cannot pass for one that did
async function getStableShot(page, report, name, need) {
  let last = null;
  for (let i = 0; i < (conf.tries || 8); i++) {
    const now = await page.screenshot();
    if (need < 2 || (last !== null && Buffer.compare(last, now) === 0)) return now;
    last = now;
    await page.waitForTimeout(conf.settle);
  }
  report.push('  never settled after ' + (conf.tries || 8) + ' tries: ' + name);
  return last;
}

// The DOM of one state as lines: the box, the paint and the text of every rendered element in document order, and the
// paths of every file the state loaded. A masked element is hidden and a dropped one has no box, so neither is in it.
// Digits of a text are read as zeros: the stand prints what it measured of itself in many places - milliseconds, shares,
// counts - and a figure that changes without moving a box is the request and not the tree; the box and the paint still count.
// The text is kept whole as a hash, so a change at the end of a long paragraph counts, and a picture by its address and a
// field by its value and its placeholder, which no text node and no box carries
async function getDomShot(page) {
  return page.evaluate((home) => {
    const hash = (text) => {
      let out = 2166136261;
      for (let i = 0; i < text.length; i++) out = Math.imul(out ^ text.charCodeAt(i), 16777619);
      return (out >>> 0).toString(36);
    };
    const path = (url) => {
      try {
        return new URL(url, location.href).pathname;
      } catch {
        return String(url);
      }
    };
    const props = ['display', 'visibility', 'opacity', 'color', 'background-color', 'background-image', 'border-top-color', 'border-right-color',
      'border-bottom-color', 'border-left-color', 'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width', 'border-radius',
      'box-shadow', 'outline-color', 'outline-width', 'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing',
      'text-decoration-line', 'text-transform', 'transform', 'filter', 'z-index'];
    const rows = [];
    for (const el of document.body.querySelectorAll('*')) {
      const box = el.getBoundingClientRect();
      if (!box.width && !box.height) continue;
      const css = getComputedStyle(el);
      if (css.visibility === 'hidden') continue;
      let text = '';
      for (const node of el.childNodes) if (node.nodeType === 3) text += node.textContent;
      const name = el.tagName.toLowerCase() + (typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\s+/).join('.') : '');
      text = text.replace(/\s+/g, ' ').replace(/\d/g, '0').trim();
      if (el.tagName === 'IMG') text += ' src=' + path(el.currentSrc || el.src);
      if (/^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName) && el.type !== 'password' && el.type !== 'hidden') {
        text += ' value=' + String(el.value || '').replace(/\d/g, '0') + ' hint=' + (el.placeholder || '');
      }
      rows.push(name + '|' + [box.x, box.y, box.width, box.height].map(Math.round).join(',') + '|' + props.map((one) => css.getPropertyValue(one)).join(';')
        + '|' + text.slice(0, 40) + (text.length > 40 ? '~' + hash(text) : ''));
    }
    const res = new Set();
    const add = (url) => {
      try {
        const one = new URL(url, location.href);
        if (one.origin === location.origin) res.add(decodeURIComponent(one.pathname).replace(home, ''));
      } catch {
        return;
      }
    };
    for (const one of performance.getEntriesByType('resource')) add(one.name);
    for (const one of document.querySelectorAll('link[href], script[src]')) add(one.href || one.src);
    return { rows, res: Array.from(res).sort() };
  }, new URL(conf.base + '/').pathname);
}

// Whether two snapshots of a state show the same DOM
function checkDomSame(one, two) {
  return one.rows.length === two.rows.length && one.rows.every((row, i) => row === two.rows[i]);
}

// The DOM of a stretched page once two snapshots a pause apart agree: a code editor draws the lines the stretch brought into
// view a frame or more later, and a fixed pause caught it half drawn in one run and done in the next
async function getSettledDom(page) {
  let dom = await getDomShot(page);
  for (let i = 0; i < (conf.tries || 8); i++) {
    await page.waitForTimeout(conf.stretch ?? 150);
    const again = await getDomShot(page);
    if (checkDomSame(dom, again)) return again;
    dom = again;
  }
  return dom;
}

// Read the snapshot a capture stored beside a picture, or null where the capture predates snapshots
function getStoredDom(file) {
  if (!existsSync(file)) return null;
  try {
    return JSON.parse(gunzipSync(readFileSync(file)).toString('utf8'));
  } catch {
    return null;
  }
}

// Name the first elements a snapshot gained or lost against another, so a difference says where it is
function getDomMoves(was, now) {
  const left = new Map();
  for (const row of was.rows) left.set(row, (left.get(row) || 0) + 1);
  const out = [];
  for (const row of now.rows) {
    const one = left.get(row) || 0;
    if (one) {
      left.set(row, one - 1);
      continue;
    }
    const part = row.split('|');
    out.push(part[0].slice(0, 80) + ' at ' + part[1]);
    if (out.length === 3) break;
  }
  return out;
}

// Split a selector list at its own commas, never at a comma inside :is(), :not() or :has()
function getSelectorParts(list) {
  const out = [];
  let deep = 0;
  let cur = '';
  for (const ch of list) {
    if (ch === '(') deep++;
    if (ch === ')') deep--;
    if (ch === ',' && deep === 0) {
      out.push(cur.trim());
      cur = '';
      continue;
    }
    cur += ch;
  }
  if (cur.trim()) out.push(cur.trim());
  return out;
}

// The rules of one stylesheet keyed by the at-rules that hold them and their selector, apart by a control character, each with its body
function getCssRules(text) {
  const src = text.replace(/\/\*[\s\S]*?\*\//g, '');
  const out = new Map();
  const at = [];
  let head = '';
  for (let i = 0; i < src.length; i++) {
    const ch = src[i];
    if (ch === '}') {
      at.pop();
      head = '';
      continue;
    }
    if (ch === ';' && head.trim().startsWith('@')) {
      out.set(at.join(' ') + '\u0001' + head.trim(), '');
      head = '';
      continue;
    }
    if (ch !== '{') {
      head += ch;
      continue;
    }
    const name = head.trim().replace(/\s+/g, ' ');
    head = '';
    if (/^@(media|supports|container|layer|scope)\b/.test(name)) {
      at.push(name);
      continue;
    }
    let deep = 1;
    let body = '';
    for (i++; i < src.length && deep; i++) {
      if (src[i] === '{') deep++;
      if (src[i] === '}') deep--;
      if (deep) body += src[i];
    }
    i--;
    out.set(at.join(' ') + '\u0001' + name, body.replace(/\s+/g, ' ').trim());
  }
  return out;
}

// What one stylesheet changed between two of its texts: the selectors of every rule added, removed or rewritten, and whether an
// at-rule such as a face or a keyframes block changed, which no selector can place, so every page the sheet serves counts.
// A sheet with no earlier text is new as a whole
function getCssChanges(was, now) {
  if (was === null) return { global: true, sels: [] };
  const one = getCssRules(was);
  const two = getCssRules(now === null ? '' : now);
  const sels = new Set();
  let global = false;
  for (const key of new Set([...one.keys(), ...two.keys()])) {
    if (one.get(key) === two.get(key)) continue;
    const name = key.split('\u0001')[1];
    if (name.startsWith('@')) {
      global = true;
      continue;
    }
    for (const part of getSelectorParts(name)) sels.add(part);
  }
  return { global, sels: Array.from(sels) };
}

// The files of the document root a page may load, each by the hash of its content, and the text of every stylesheet, as they
// stand now. A PHP entry is no file a page loads but the server that answers it, and what it changes reaches the DOM snapshot
function getPublicState() {
  const base = join(root, 'public');
  const out = { hashes: {}, css: {} };
  const walk = (dir) => {
    for (const one of readdirSync(dir, { withFileTypes: true })) {
      const full = join(dir, one.name);
      if (one.isSymbolicLink()) continue;
      if (one.isDirectory()) {
        walk(full);
        continue;
      }
      if (one.name.endsWith('.php')) continue;
      const rel = full.slice(base.length + 1).replace(/\\/g, '/');
      const data = readFileSync(full);
      out.hashes[rel] = createHash('sha1').update(data).digest('hex');
      if (rel.endsWith('.css')) out.css[rel] = data.toString('utf8');
    }
  };
  walk(base);
  return out;
}

// The public files that changed since the capture, as the paths a page loads them by, each stylesheet with what it changed.
// A capture records the files it shot, so the comparison is with the tree of the "before" and not with HEAD: against HEAD a
// change already in the tree before the capture counted again, and one committed between the two runs did not count at all.
// A capture made before the record existed falls back to HEAD
function getTouched() {
  const out = new Map();
  if (job !== 'check' || pixels) return out;
  const was = existsSync(stateFile) ? JSON.parse(gunzipSync(readFileSync(stateFile)).toString('utf8')) : null;
  if (was) {
    const now = getPublicState();
    for (const rel of new Set([...Object.keys(was.hashes), ...Object.keys(now.hashes)])) {
      if (was.hashes[rel] === now.hashes[rel]) continue;
      out.set(rel, rel.endsWith('.css') ? getCssChanges(was.css[rel] ?? null, now.css[rel] ?? null) : null);
    }
    return out;
  }
  for (const file of getChangedFiles().filter((f) => f.startsWith('public/') && !f.endsWith('.php'))) {
    let head = null;
    try {
      head = execFileSync('git', ['show', 'HEAD:' + file], { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
    } catch {
      head = null;
    }
    const now = existsSync(join(root, file)) ? readFileSync(join(root, file), 'utf8') : null;
    out.set(file.slice('public/'.length), file.endsWith('.css') ? getCssChanges(head, now) : null);
  }
  return out;
}

const stateFile = join(outDir, 'files.json.gz');
const touch = getTouched();

// Whether a check has to shoot one state: its snapshot moved, it loaded a changed public file other than a stylesheet,
// a changed stylesheet changed an at-rule, or a changed rule of it still matches an element of the state. A selector is
// tried without its pseudo-elements and its pointer and focus states, and one the page cannot parse counts as a match
async function getShotReason(page, was, now) {
  if (pixels) return 'every state asked for';
  if (!was) return 'no snapshot before';
  if (!checkDomSame(was, now)) return 'DOM moved';
  const hits = now.res.filter((path) => touch.has(path));
  if (!hits.length) return '';
  const sels = [];
  for (const path of hits) {
    const css = touch.get(path);
    if (!css || css.global) return 'loaded ' + path;
    sels.push(...css.sels);
  }
  const match = await page.evaluate((list) => list.find((sel) => {
    const base = sel.replace(/::?(before|after|first-line|first-letter|placeholder|marker|selection|backdrop|file-selector-button|-webkit-[\w-]+|-moz-[\w-]+)(\([^)]*\))?/g, '')
      .replace(/:(hover|focus-visible|focus-within|focus|active|visited|target)(?![\w-])/g, '').trim();
    try {
      return !!document.querySelector(base || '*');
    } catch {
      return true;
    }
  }) || '', sels);
  return match ? 'a changed rule matches ' + match : '';
}

// Sign one context in through the form the manifest names, and prove the session took two ways: something
// only a session shows must appear, and the password field must be gone. One alone is not proof - the first
// version of this asserted `.sl-user-card`, which the anonymous page also carries, so a login that never
// happened passed and three states were baselined as the logged-out page nobody noticed they were
async function setSession(page, kind) {
  const form = conf.auth[kind];
  await page.goto(conf.base + form.url, { waitUntil: 'domcontentloaded' });
  await page.fill(form.user, user);
  await page.fill(form.pass, pass);
  await page.locator(form.submit).first().click();
  await page.waitForLoadState('load');
  await checkSession(page, kind);
}

// Throw unless the page shows the session of one kind by both of the proofs the manifest names
async function checkSession(page, kind) {
  const form = conf.auth[kind];
  if (await page.locator(form.gone).count()) throw new Error('login as ' + kind + ' did not take: ' + form.gone + ' is still on the page');
  if (!(await page.locator(form.proof).first().count())) throw new Error('login as ' + kind + ' did not take: ' + form.proof + ' is absent');
}

// Hide what moves on its own, so a diff reports a change in the theme and not the hour of the day.
// Motion is switched off with `animation: none`, not with a duration near zero: a duration near zero on an
// infinite animation does not stop it, it makes it cycle as fast as the compositor can draw, and the frame
// a screenshot catches is then chosen by the scheduler. That produced a page whose DOM was identical over
// four seconds and whose pixels were not, and the caret does the same on a focused field. A script that draws by itself -
// the pulse strip and the breathing figure of the presentation cockpit - is outside the reach of a stylesheet, so every
// context asks for reduced motion and the scripts that honour the media query hold still
async function setMasks(page) {
  const hide = (conf.mask || []).length ? (conf.mask || []).join(', ') + ' { visibility: hidden !important; }\n' : '';
  const drop = (conf.drop || []).length ? (conf.drop || []).join(', ') + ' { display: none !important; }\n' : '';
  const still = '*, *::before, *::after { animation: none !important; transition: none !important; }';
  const caret = '* { caret-color: transparent !important; }';
  await page.addStyleTag({ content: hide + drop + still + caret });
  // A path animated by SMIL - the dot walking the pipeline of the presentation page - is not a CSS animation, so the
  // rule above leaves it moving and the page never renders the same twice; the SVG API holds it in place
  await page.evaluate(() => {
    for (const svg of document.querySelectorAll('svg')) if (svg.pauseAnimations) svg.pauseAnimations();
    document.documentElement.setAttribute('data-sl-rig', '');
  });
}

// Walk the page to the bottom and back so every lazy image has been asked for and decoded, and wait for
// the fonts. A full-page screenshot scrolls by itself, so without the walk the first capture triggers the
// loading and the second finds it done. And `font-display: swap` paints the fallback until the face
// arrives: a shot taken before that lands differs from every later one on every line of text at once,
// with the page height unchanged - which reads as a theme change and is not one.
// The image wait is bounded: a lazy image the walk never brings into view - a tile a script hid, a card past the
// edge of a horizontal strip - fires neither load nor error, and the presentation page holds ninety of them, so an
// unbounded wait held the whole run for good. `walk` false only waits, for a page already walked at another width
async function setScrolled(page, walk = true) {
  await page.evaluate(async (walk) => {
    const step = window.innerHeight;
    const far = document.body.scrollHeight;
    for (let y = 0, n = 0; walk && y < far && n < 60; y += step, n++) {
      window.scrollTo(0, y);
      await new Promise((ok) => setTimeout(ok, 60));
    }
    window.scrollTo(0, 0);
    const shown = Promise.all(Array.from(document.images).filter((i) => !i.complete).map((i) => new Promise((ok) => {
      i.addEventListener('load', ok, { once: true });
      i.addEventListener('error', ok, { once: true });
    })));
    await Promise.race([shown, new Promise((ok) => setTimeout(ok, 3000))]);
    await Promise.all(Array.from(document.fonts).map((f) => (f.status === 'loaded' ? null : f.load().catch(() => {}))));
    await document.fonts.ready;
  }, walk);
}

// Every text node against the background it really sits on, resolved through its ancestors
async function getContrastPairs(page, name, mode) {
  return page.evaluate(([page, mode]) => {
    const seen = new Map();
    const solid = (col) => {
      const hit = /rgba?\(([^)]+)\)/.exec(col || '');
      if (!hit) return null;
      const part = hit[1].split(/[\s,\/]+/).filter(Boolean).map(Number);
      if (part.length > 3 && part[3] === 0) return null;
      return part.slice(0, 3);
    };
    const lum = (rgb) => {
      let out = 0;
      [0.2126, 0.7152, 0.0722].forEach((part, i) => {
        const val = rgb[i] / 255;
        out += part * (val <= 0.03928 ? val / 12.92 : Math.pow((val + 0.055) / 1.055, 2.4));
      });
      return out;
    };
    const ratio = (one, two) => (Math.max(lum(one), lum(two)) + 0.05) / (Math.min(lum(one), lum(two)) + 0.05);
    // The alpha of one fill, 1 when it carries none and 0 when it is not a colour at all
    const alpha = (col) => {
      const hit = /rgba?\(([^)]+)\)/.exec(col || '');
      if (!hit) return 0;
      const part = hit[1].split(/[\s,\/]+/).filter(Boolean).map(Number);
      return part.length > 3 ? part[3] : 1;
    };
    // One layer laid over what is already behind it. Nine per cent of orange over a white page is a definite colour and
    // text standing on it is standing on that colour, so a sheer fill is composited rather than passed through: passing
    // through reported the page ground for a tint nobody could see, and skipping it left the honest readings unmeasured
    const over = (top, back, part) => top.map((one, i) => Math.round(one * part + back[i] * (1 - part)));
    // Split one background-image into its layers: a comma at depth zero separates two layers, every comma inside a
    // gradient's own argument list is deeper than that. The list is painted front to back, first layer on top
    const layers = (val) => {
      const out = [];
      let deep = 0;
      let cur = '';
      for (const ch of val || '') {
        if (ch === '(') deep++;
        if (ch === ')') deep--;
        if (ch === ',' && deep === 0) {
          out.push(cur);
          cur = '';
          continue;
        }
        cur += ch;
      }
      if (cur.trim()) out.push(cur);
      return out;
    };
    // A gradient has no single background colour, so the stop that reads worst against the text is recorded. Layers are
    // walked back to front and each one is composited onto what the walk has gathered under it: an eight per cent white
    // stripe laid over a brand gradient is a stripe on that gradient, never a stripe on the page two boxes further out
    const worst = (css, fg, back) => {
      const list = layers(css.backgroundImage || '').reverse();
      let out = null;
      for (const layer of list) {
        const stop = layer.match(/rgba?\([^)]+\)/g);
        if (!stop) continue;
        const under = out === null ? back : out;
        let low = null;
        for (const item of stop) {
          const rgb = solid(item);
          if (!rgb) continue;
          const mix = over(rgb, under, alpha(item));
          if (low === null || ratio(fg, mix) < ratio(fg, low)) low = mix;
        }
        if (low !== null) out = low;
      }
      return out;
    };
    // Walk out to the first ancestor that paints something opaque, compositing every sheer layer met on the way back down
    const under = (node, fg) => {
      const stack = [];
      let hit = null;
      for (let el = node; el; el = el.parentElement) {
        const css = getComputedStyle(el);
        const sel = el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).join('.') : '');
        stack.push({ css, sel });
        if (alpha(css.backgroundColor) >= 1 && solid(css.backgroundColor)) { hit = stack.length - 1; break; }
      }
      let rgb = [255, 255, 255];
      let sel = 'html';
      const from = hit === null ? stack.length - 1 : hit;
      for (let i = from; i >= 0; i--) {
        const css = stack[i].css;
        const fill = solid(css.backgroundColor);
        const part = alpha(css.backgroundColor);
        let painted = false;
        if (fill && part > 0) {
          rgb = over(fill, rgb, part);
          painted = true;
        }
        const grad = worst(css, fg, rgb);
        if (grad) {
          rgb = grad;
          painted = true;
        }
        if (painted) sel = stack[i].sel;
      }
      return { rgb, sel };
    };
    for (const el of document.querySelectorAll('body *')) {
      const text = Array.from(el.childNodes).filter((n) => n.nodeType === 3 && n.textContent.trim()).length;
      if (!text) continue;
      const box = el.getBoundingClientRect();
      if (!box.width || !box.height) continue;
      const css = getComputedStyle(el);
      if (css.visibility === 'hidden' || css.display === 'none' || Number(css.opacity) === 0) continue;
      if (box.width <= 1 || box.height <= 1) continue;
      if (parseFloat(css.fontSize) < 1 || css.textIndent.startsWith('-')) continue;
      const fg = solid(css.color);
      if (!fg) continue;
      const bg = under(el, fg);
      // A colour measured against itself is not a reading anyone can act on: it means an element between the text and
      // the ground the walk could not follow - a knob drawn as a ::after, a pseudo-element, an image. The switch label
      // sits on exactly such a knob, and reporting the track behind it as its ground would file a permanent false alarm
      if (fg.join(',') === bg.rgb.join(',')) continue;
      const key = el.tagName.toLowerCase() + '|' + (typeof el.className === 'string' ? el.className.trim() : '') + '|' + fg.join(',') + '|' + bg.rgb.join(',');
      if (seen.has(key)) continue;
      seen.set(key, {
        page,
        mode,
        sel: el.tagName.toLowerCase() + (typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\s+/).join('.') : ''),
        bgsel: bg.sel,
        fg: 'rgb(' + fg.join(', ') + ')',
        bg: 'rgb(' + bg.rgb.join(', ') + ')',
        size: parseFloat(css.fontSize) || 0,
        weight: parseInt(css.fontWeight, 10) || 400,
      });
    }
    return Array.from(seen.values());
  }, [name, mode]);
}

// Bring a page to the address of one item: masked, still, with every lazy image asked for and with no focus. A field the page
// focuses by itself keeps it only while the window it stands in has the focus, which pages walked side by side do not agree on:
// measured, the code editor of a system file was focused in one run and not in the next, its caret, line and capsule along.
// A focused state the manifest wants is a step of its own
async function setPageReady(page, item) {
  await page.goto(conf.base + item.url, { waitUntil: 'load' });
  await setMasks(page);
  await setScrolled(page);
  await page.evaluate(() => { if (document.activeElement && document.activeElement !== document.body) document.activeElement.blur(); });
  await page.waitForTimeout(conf.settle);
}

// Run one page in every viewport: capture, compare or crawl, including every state the manifest drives.
// A page without states loads once and is shown at each width by a resize, which is what a reader does by turning a tablet;
// a page with states loads again for each width, because a hover, a focus or a submit leaves the page in a state the next width must not start from
async function checkPageViews(page, item, mode, pairs, report) {
  const steps = item.steps || [];
  const due = [];
  for (const [n, view] of conf.viewports.entries()) {
    await page.setViewportSize({ width: view.width, height: view.height });
    if (steps.length || n === 0) await setPageReady(page, item);
    else {
      await setScrolled(page, false);
      await page.waitForTimeout(conf.settle);
    }
    if (await setOneShot(page, item, view, mode, '', pairs, report)) due.push(view);
    for (const step of steps) {
      const node = page.locator(step.sel.split(',').map((s) => s.trim() + ':visible').join(', ')).first();
      if (!(await node.count())) {
        if (!step.optional) report.push('  missing state: ' + item.name + ' ' + step.do + ' ' + step.sel);
        continue;
      }
      try {
        if (step.do === 'hover') await node.hover({ timeout: 3000 });
        if (step.do === 'focus') await node.focus({ timeout: 3000 });
        if (step.do === 'click') await node.click({ timeout: 3000 });
        if (step.do === 'wait') await page.waitForTimeout(step.ms || 200);
      } catch (err) {
        report.push('  state not reachable: ' + item.name + ' ' + step.do + ' ' + step.sel);
        continue;
      }
      await page.waitForLoadState('load');
      // A step that submits a form leaves for another document, which carries none of the masks: the code state of a file block
      // was shot with the debug sections of its request in view, and every run reported them as a change of the theme
      if (!(await page.evaluate(() => document.documentElement.hasAttribute('data-sl-rig')))) {
        await setMasks(page);
        await setScrolled(page);
      }
      await page.waitForTimeout(conf.settle);
      await setOneShot(page, item, view, mode, step.shot ? '-' + step.shot : '', pairs, report);
    }
  }
  for (const view of due) await setFloor(page, item, view, mode, report);
}

// Measure the noise floor of the base state of one width after the walk, from a fresh load at that width: what the walk drew
// after a resize and what a load draws are both a render of the tree, so the floor holds the difference of the two as well.
// Measured inside the walk, the reload gave the widths after it a history the check never had, and a code editor laid out
// the lines it had wrapped at the widths before differently in the two runs
async function setFloor(page, item, view, mode, report) {
  const name = item.name + '-' + view.name + (mode === 'auto' ? '' : '-' + mode) + '.png';
  await page.setViewportSize({ width: view.width, height: view.height });
  await setPageReady(page, item);
  await setStretched(page, view);
  try {
    await getSettledDom(page);
    floor[name] = getPixelDiff(readFileSync(join(outDir, name)), await getStableShot(page, report, name, 2)).ratio;
    if (floor[name] > conf.threshold) report.push('  noise floor ' + (floor[name] * 100).toFixed(2) + '% of ' + name + ', not guarded below that');
  } finally {
    await page.setViewportSize({ width: view.width, height: view.height });
  }
}

// Record one state: a screenshot and its DOM against its baseline, or the contrast pairs the state puts on screen.
// On capture it answers whether the floor of the state is due: one the floor file does not hold yet is measured by setFloor(); the site
// rotates content per request - a random FAQ line, a random poll, a related-article list - and no amount of masking makes
// such a page the same twice, so the floor says how much of the page is not the same twice and --check only reports past it.
// A page with a high floor is not guarded, and the runner says so rather than letting a green run imply a guard that is not there.
// On check a state is shot only when getShotReason() gives a reason, and a difference past the floor is confirmed by a
// settled shot before it is reported, with the first elements its DOM moved
async function setOneShot(page, item, view, mode, tag, pairs, report) {
  if (job === 'contrast') {
    for (const pair of await getContrastPairs(page, item.name + tag, mode)) pairs.push({ theme: item.theme || 'lite', ...pair });
    return;
  }
  const tail = '-' + view.name + (mode === 'auto' ? '' : '-' + mode);
  const name = item.name + tag + tail + '.png';
  const base = item.name + tail + '.png';
  const file = join(outDir, name);
  const domFile = join(outDir, item.name + tag + tail + '.dom.gz');
  // A check whose baseline is missing must say so: writing it instead re-baselines the gate against the very tree it was
  // asked to judge, and the run exits green having compared nothing. Only a capture is allowed to create an image
  if (job === 'check' && !existsSync(file)) {
    report.push('  MISSING baseline ' + name + ' - capture it before checking against it');
    return;
  }
  await setStretched(page, view);
  try {
    const dom = await getSettledDom(page);
    if (job === 'capture') {
      const now = await getStableShot(page, report, name, 2);
      writeFileSync(file, now);
      writeFileSync(domFile, gzipSync(JSON.stringify(dom)));
      report.push('  wrote ' + name);
      return !tag && (floor[base] === undefined || args.has('floor'));
    }
    const was = getStoredDom(domFile);
    const why = await getShotReason(page, was, dom);
    if (!why) {
      skipped++;
      return;
    }
    shot++;
    whys[why.split(' ').slice(0, 2).join(' ')] = (whys[why.split(' ').slice(0, 2).join(' ')] || 0) + 1;
    const bar = Math.max(conf.threshold, (floor[base] || 0) * 1.5);
    const old = readFileSync(file);
    let diff = getPixelDiff(old, await getStableShot(page, report, name, 1));
    if (diff.ratio > bar) diff = getPixelDiff(old, await getStableShot(page, report, name, 2));
    const past = floor[base] ? '   past its ' + (bar * 100).toFixed(2) + '% floor' : '';
    if (diff.ratio <= bar) return;
    const moves = was ? getDomMoves(was, dom) : [];
    report.push('  DIFF ' + (diff.ratio * 100).toFixed(3) + '% ' + diff.note + ' ' + name + past + '   (' + why + ')'
      + (moves.length ? '\n      moved: ' + moves.join('; ') : ''));
  } finally {
    await page.setViewportSize({ width: view.width, height: view.height });
  }
}

// Render every page of the manifest that a frontend theme owns, in a scratch copy of an etalon, and report what a
// visitor would have been served. A theme that audits clean and cannot render is still not a theme, and only a real
// request can tell the two apart: the copy is selected through the `theme` column of the account the rig signs in as,
// which is the lever getTheme() reads before it falls back to the site default, so no configuration of a running
// stand is touched. getTheme() caches its answer in a static, so the switch has to be in place before the request
// rather than during it - which is why it is a database write and not a header the runner could send
async function checkNewTheme(browser, report) {
  if (!user || !pass) throw new Error('the HTTP half needs a session: set ' + conf.env.user + ' and ' + conf.env.pass);
  const made = getProbeAnswer('theme', 'make', args.get('etalon') === undefined || args.get('etalon') === true ? 'lite' : args.get('etalon'));
  let back = null;
  try {
    back = getProbeAnswer('theme', 'pick', user, made.name).was;
    const logs = (conf.logs || []).map((one) => [one, existsSync(join(root, one)) ? statSync(join(root, one)).size : 0]);
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, reducedMotion: 'reduce' });
    const page = await ctx.newPage();
    await page.setViewportSize({ width: 1200, height: 1000 });
    await setSession(page, 'site');
    for (const item of conf.pages) {
      if (item.theme === 'admin' || item.auth === 'admin') continue;
      const res = await page.goto(conf.base + item.url, { waitUntil: 'load' });
      const html = await page.content();
      if (!res || res.status() !== 200) report.push('  ' + item.name + ': the new theme answered ' + (res ? res.status() : 'nothing'));
      if (!html.includes('templates/' + made.name + '/')) report.push('  ' + item.name + ': served by another theme, so this page proves nothing about the copy');
      // No test for a surviving placeholder here, and it is not an oversight: this page carries whatever a member
      // typed, and the private messages of this stand quote template syntax at each other. A scan of a rendered page
      // cannot tell a tag the engine failed to fill from a tag somebody wrote in a message, so it reported both. The
      // static half asks that question of every fragment with input it controls, which is where it can be answered
    }
    await ctx.close();
    // What the pages themselves cannot be asked: the server writes a notice nobody sees on the page. A log that grew
    // over the walk is the answer, and unlike a scan of the markup no member can type their way into it
    for (const [one, was] of logs) {
      const now = existsSync(join(root, one)) ? statSync(join(root, one)).size : 0;
      if (now <= was) continue;
      const said = readFileSync(join(root, one), 'utf8').slice(was).trim().slice(0, 300);
      report.push('  ' + one + ' grew by ' + (now - was) + ' bytes while the copy was serving: ' + said);
    }
    console.log('newtheme: ' + made.name + ' rendered ' + conf.pages.filter((p) => p.theme !== 'admin' && p.auth !== 'admin').length + ' pages of the manifest');
  } finally {
    if (back !== null) getProbeAnswer('theme', 'pick', user, back);
    getProbeAnswer('theme', 'gone', made.path);
  }
}

const floorFile = join(outDir, 'noise-floor.json');
const floorRead = existsSync(floorFile) ? JSON.parse(readFileSync(floorFile, 'utf8')) : {};
// A floor file of the shooting method this run uses is kept; one written by the full-page method of before is measured again
const floor = floorRead._method === floorMethod ? floorRead : { _method: floorMethod };
let skipped = 0;
let shot = 0;
const whys = {};

if (job === 'newtheme') {
  const browser = await chromium.launch();
  const report = [];
  try {
    await checkNewTheme(browser, report);
  } finally {
    await browser.close();
  }
  for (const line of report) console.log(line);
  process.exit(report.length ? 1 : 0);
}

const browser = await chromium.launch();
const report = [];
const pairs = [];
const need = new Set((conf.pages || []).filter((p) => p.auth).map((p) => p.auth));

mkdirSync(outDir, { recursive: true });

// A capture records the public files it shoots, taken before the walk so the record is the tree the pictures show. A capture of
// some pages keeps the record it finds: the pages it did not name were shot against that one, and a newer one would hide from
// them what changed in between
if (job === 'capture' && (!only || !existsSync(stateFile))) writeFileSync(stateFile, gzipSync(JSON.stringify(getPublicState())));

const seed = setSeededState(conf);

// One line of the report per fact, however many walks meet it: two modes sign in twice and would otherwise say twice
// that the credentials are missing
function addReportOnce(line) {
  if (!report.includes(line)) report.push(line);
}

// A session of one kind for one slot of the pool: the one saved by an earlier run while it still proves itself, else a fresh
// login through the form, tried once more on a fresh page with the cookies of the first attempt cleared, and saved for the next
// run. The saved state lives in the guard directory outside the repository; the admin form solves a proof of work on every
// submit, which is seconds a run no longer pays. Each slot keeps a session of its own because PHP serves the requests of one
// session one after another: the pool shared one, and a heavy page of four pages at once waited past the navigation timeout
async function getSessionContext(kind, slot) {
  const keep = join(guardDir, 'session-' + kind + '-' + slot + '.json');
  if (existsSync(keep)) {
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, reducedMotion: 'reduce', storageState: keep });
    const page = await ctx.newPage();
    try {
      await page.goto(conf.base + conf.auth[kind].url, { waitUntil: 'domcontentloaded' });
      await checkSession(page, kind);
      await page.close();
      return ctx;
    } catch {
      await ctx.close();
    }
  }
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true, reducedMotion: 'reduce' });
  for (let i = 1; i <= 2; i++) {
    if (i === 2) await ctx.clearCookies();
    const page = await ctx.newPage();
    try {
      await setSession(page, kind);
      await page.close();
      mkdirSync(guardDir, { recursive: true });
      await ctx.storageState({ path: keep });
      return ctx;
    } catch (err) {
      await page.close();
      if (i === 2) addReportOnce('  ' + kind + ' login failed: ' + err.message);
    }
  }
  await ctx.close();
  return null;
}

// The contexts of one mode for one slot: a session per auth kind the manifest needs and one open context, each carrying the
// seeded state and the mode cookie. A context is what holds a cookie, so two modes cannot share one; the second mode of a slot
// takes the session the first one saved, which the one page the slot walks at a time never asks for twice at once
async function getModeContexts(mode, slot) {
  const sess = new Map();
  for (const kind of need) {
    if (!user || !pass) {
      addReportOnce('  skipped every ' + kind + ' page: set ' + conf.env.user + ' and ' + conf.env.pass + ' in the environment');
      continue;
    }
    const ctx = await getSessionContext(kind, slot);
    if (ctx) sess.set(kind, ctx);
  }
  // A development stand serves its own certificate, and the manifest names https because the session cookie needs it
  const open = await browser.newContext({ ignoreHTTPSErrors: true, reducedMotion: 'reduce' });
  for (const ctx of [...sess.values(), open]) {
    if (seed.cookies.length) await ctx.addCookies(seed.cookies);
    if (mode !== 'auto') await ctx.addCookies([{ name: conf.cookie, value: mode, url: conf.base }]);
    // The browser keeps 250 entries of resource timing and drops the rest without a word, and a page past them would hide a
    // changed file it loaded late from the snapshot
    await ctx.addInitScript(() => performance.setResourceTimingBufferSize(10000));
  }
  return { sess, open };
}

// The slots sign in one after another and only then walk: two forms of one account submitted at the same moment used to
// leave one of them without its session pages. Every page of every mode is one piece of work, taken by the next free slot
// of the pool; the report keeps the order of the manifest whatever order the pieces finish in
const walks = job === 'contrast' ? conf.contrastmodes || conf.modes : conf.modes;
const work = [];
for (const mode of walks) {
  for (const item of conf.pages) if (!only || only.has(item.name)) work.push({ mode, item, lines: [] });
}
const slots = Math.min(workers, Math.max(1, work.length));
const held = [];
for (let slot = 0; slot < slots; slot++) {
  const one = new Map();
  for (const mode of walks) one.set(mode, await getModeContexts(mode, slot));
  held.push(one);
}
let next = 0;
await Promise.all(held.map(async (one) => {
  while (next < work.length) {
    const piece = work[next++];
    const { sess, open } = one.get(piece.mode);
    const ctx = piece.item.auth ? sess.get(piece.item.auth) : open;
    if (!ctx) continue;
    const page = await ctx.newPage();
    page.setDefaultNavigationTimeout(60000);
    try {
      await checkPageViews(page, piece.item, piece.mode, pairs, piece.lines);
    } catch (err) {
      piece.lines.push('  ' + piece.item.name + ' failed: ' + err.message);
    }
    await page.close();
  }
}));
for (const piece of work) report.push(...piece.lines);
for (const one of held) for (const { sess, open } of one.values()) for (const ctx of [...sess.values(), open]) await ctx.close();

await browser.close();
deleteSeededState(seed);

if (job === 'contrast') {
  const seen = new Map();
  // The mode is out of the key on purpose: a pair whose two colours are the same in both modes is one pair, and
  // keying on the mode would file it twice and double a count that must only fall
  for (const pair of pairs) seen.set([pair.theme, pair.sel, pair.fg, pair.bg, pair.size, pair.weight].join('|'), pair);
  const list = Array.from(seen.values()).sort((a, b) => (a.theme + a.sel).localeCompare(b.theme + b.sel));
  writeFileSync(join(root, 'tools/ui-contrast.json'), JSON.stringify({ generated: new Date().toISOString(), pairs: list }, null, 2) + '\n');
  console.log('wrote tools/ui-contrast.json with ' + list.length + ' pairs that really meet on screen, out of ' + pairs.length + ' sightings');
}

if (job === 'capture') writeFileSync(floorFile, JSON.stringify(floor, null, 2) + '\n');

const noisy = Object.entries(floor).filter(([k, v]) => k !== '_method' && v > conf.threshold);
if (noisy.length) {
  console.log(noisy.length + ' of ' + (Object.keys(floor).length - 1) + ' states are not the same twice and are guarded only past their own floor:');
  for (const [k, v] of noisy.sort((a, b) => b[1] - a[1])) console.log('  ' + (v * 100).toFixed(2) + '%  ' + k);
}

const shots = existsSync(outDir) ? readdirSync(outDir).filter((f) => f.endsWith('.png')).length : 0;
console.log(job + ': ' + shots + ' baseline images under ' + outRel);
if (job === 'check') {
  console.log('  ' + shot + ' states shot and compared' + (shot ? ' (' + Object.entries(whys).map(([k, v]) => k + ' ' + v).join(', ') + ')' : '') + ', ' + skipped
    + ' unchanged by their DOM and by the files they load'
    + (touch.size ? '; public files changed: ' + Array.from(touch.keys()).join(', ') : ''));
}
for (const line of report) console.log(line);
let code = report.some((l) => l.includes('DIFF') || l.includes('failed') || l.includes('MISSING')) ? 1 : 0;

if (guard === 'after') {
  // A palette that moved invalidates the pair registry, and the audit would go on measuring the colours it replaced.
  // Only a base.css can move it, so the walk it costs is paid only when one did
  const palette = getChangedFiles().some((f) => f.endsWith('assets/css/base.css'));
  if (palette) {
    console.log('\na base.css changed, so the contrast registry is regenerated before the counts are read');
    code = runStep(process.execPath, ['tools/ui-shots.mjs', '--contrast']) || code;
  }
  console.log('\ncounts:');
  code = runStep('php', ['tools/ui-audit.php']) || code;
  console.log('\nmarkup:');
  code = runStep('php', ['tools/ui-audit.php', '--markup']) || code;
  console.log(code ? '\nFAIL - read the lines above; nothing was stored' : '\nPASS - re-store the ratchet with `php tools/ui-audit.php --store` when the change is final');
}

if (guard === 'before' && !code) console.log('\nbefore is captured. Make the change, then run `npm run ui:after`');

process.exit(code);
