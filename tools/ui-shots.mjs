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
// The last of the four is the HTTP half of the theme-creation gate. ThemeCreationTest asks the static half of the
// same question - does a copy of an etalon with only its API block repainted audit clean - and this asks the half a
// file cannot answer: does the CMS actually serve pages in it. Both build and remove the copy through one lifecycle,
// tests/Support/theme_scratch.php, reached here through the make, pick and gone jobs of tests/Support/theme_probe.php.
//
// A contrast pair existing only on hover is invisible to a crawler that never hovers, which is why the
// states live in the manifest and not in the runner. Credentials come from the environment, never the file.
//
// Before capturing, empty storage/cache/pages and storage/cache/templates and keep cache_css and css_h
// off: doCss() bundles when either is set, the bundle fingerprint sits in $conf['derived']['assets'], and
// a warm-cache comparison compares caches instead of renders.

import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { readFileSync, writeFileSync, mkdirSync, existsSync, readdirSync, statSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

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
const only = args.get('only');
// A batch cannot trust the committed baseline as its "before": the stand's own data moves between runs, so a
// check against it reports the week rather than the change. --out= sends a capture somewhere outside the
// repository, which is what lets a batch compare its own two captures instead
const outRel = guard ? guardDir : (typeof args.get('out') === 'string' ? args.get('out') : conf.out);
const outDir = resolve(root, outRel);
const user = process.env[conf.env.user] || '';
const pass = process.env[conf.env.pass] || '';

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

// The rendered tree has to be the tree on disk: doCss() bundles when cache_css or css_h is set, and a page cache
// serves a render made before the edit, so a comparison of caches is not a comparison of themes
function setCachesEmpty() {
  for (const dir of ['storage/cache/pages', 'storage/cache/templates']) {
    const full = join(root, dir);
    if (!existsSync(full)) continue;
    for (const item of readdirSync(full)) rmSync(join(full, item), { recursive: true, force: true });
  }
}

if (guard) {
  // Without credentials the rig skips every state that needs a session and captures the rest logged out, which is a
  // different set from the one a full run writes. A guard that quietly guards two thirds of the manifest is worse than none
  if (!user || !pass) {
    console.error('Set ' + conf.env.user + ' and ' + conf.env.pass + ' first: without them the walk skips every page that needs a session');
    process.exit(1);
  }
  if (guard === 'before') {
    rmSync(guardDir, { recursive: true, force: true });
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

// Count the pixels two PNGs differ in, drawn on a canvas in the page so the rig needs no image dependency
async function getPixelDiff(page, one, two) {
  return page.evaluate(async ([a, b]) => {
    const load = (src) => new Promise((ok, no) => {
      const img = new Image();
      img.onload = () => ok(img);
      img.onerror = no;
      img.src = 'data:image/png;base64,' + src;
    });
    const [imgA, imgB] = await Promise.all([load(a), load(b)]);
    if (imgA.width !== imgB.width || imgA.height !== imgB.height) return { ratio: 1, note: 'size changed' };
    const draw = (img) => {
      const cvs = document.createElement('canvas');
      cvs.width = img.width;
      cvs.height = img.height;
      cvs.getContext('2d').drawImage(img, 0, 0);
      return cvs.getContext('2d').getImageData(0, 0, img.width, img.height).data;
    };
    const one = draw(imgA);
    const two = draw(imgB);
    let bad = 0;
    for (let i = 0; i < one.length; i += 4) {
      if (Math.abs(one[i] - two[i]) + Math.abs(one[i + 1] - two[i + 1]) + Math.abs(one[i + 2] - two[i + 2]) > 12) bad++;
    }
    return { ratio: bad / (one.length / 4), note: '' };
  }, [one, two]);
}

// Shoot the page once it has stopped changing, not once a timer has run out. Measured, this site keeps
// repainting for about two seconds after load, so a fixed settle produced two different renders at random
// and a baseline that disagreed with itself between runs. Two identical consecutive shots is the signal;
// the count of tries is reported so a page that never settles cannot pass for one that did
async function getStableShot(page, report, name) {
  let last = null;
  for (let i = 0; i < (conf.tries || 8); i++) {
    const now = await page.screenshot({ fullPage: true });
    if (last !== null && Buffer.compare(last, now) === 0) return now;
    last = now;
    await page.waitForTimeout(conf.settle);
  }
  report.push('  never settled after ' + (conf.tries || 8) + ' tries: ' + name);
  return last;
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
  if (await page.locator(form.gone).count()) throw new Error('login as ' + kind + ' did not take: ' + form.gone + ' is still on the page');
  if (!(await page.locator(form.proof).first().count())) throw new Error('login as ' + kind + ' did not take: ' + form.proof + ' is absent');
}

// Hide what moves on its own, so a diff reports a change in the theme and not the hour of the day.
// Motion is switched off with `animation: none`, not with a duration near zero: a duration near zero on an
// infinite animation does not stop it, it makes it cycle as fast as the compositor can draw, and the frame
// a screenshot catches is then chosen by the scheduler. That produced a page whose DOM was identical over
// four seconds and whose pixels were not, and the caret does the same on a focused field
async function setMasks(page) {
  const hide = (conf.mask || []).length ? (conf.mask || []).join(', ') + ' { visibility: hidden !important; }\n' : '';
  const drop = (conf.drop || []).length ? (conf.drop || []).join(', ') + ' { display: none !important; }\n' : '';
  const still = '*, *::before, *::after { animation: none !important; transition: none !important; }';
  const caret = '* { caret-color: transparent !important; }';
  await page.addStyleTag({ content: hide + drop + still + caret });
}

// Walk the page to the bottom and back so every lazy image has been asked for and decoded, and wait for
// the fonts. A full-page screenshot scrolls by itself, so without the walk the first capture triggers the
// loading and the second finds it done. And `font-display: swap` paints the fallback until the face
// arrives: a shot taken before that lands differs from every later one on every line of text at once,
// with the page height unchanged - which reads as a theme change and is not one
async function setScrolled(page) {
  await page.evaluate(async () => {
    const step = window.innerHeight;
    const far = document.body.scrollHeight;
    for (let y = 0, n = 0; y < far && n < 60; y += step, n++) {
      window.scrollTo(0, y);
      await new Promise((ok) => setTimeout(ok, 60));
    }
    window.scrollTo(0, 0);
    await Promise.all(Array.from(document.images).filter((i) => !i.complete).map((i) => new Promise((ok) => {
      i.addEventListener('load', ok, { once: true });
      i.addEventListener('error', ok, { once: true });
    })));
    await Promise.all(Array.from(document.fonts).map((f) => (f.status === 'loaded' ? null : f.load().catch(() => {}))));
    await document.fonts.ready;
  });
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

// Run one page in one viewport: capture, compare or crawl, including every state the manifest drives
async function checkOnePage(page, item, view, mode, pairs, report) {
  await page.setViewportSize({ width: view.width, height: view.height });
  await page.goto(conf.base + item.url, { waitUntil: 'load' });
  await setMasks(page);
  await setScrolled(page);
  await page.waitForTimeout(conf.settle);
  await setOneShot(page, item, view, mode, '', pairs, report);
  for (const step of item.steps || []) {
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
    await page.waitForTimeout(conf.settle);
    await setOneShot(page, item, view, mode, step.shot ? '-' + step.shot : '', pairs, report);
  }
}

// Record one state: a screenshot against its baseline, or the contrast pairs the state puts on screen.
// On capture the same state is rendered a second time and the two are compared, and that figure is stored
// as the state's noise floor. The site rotates content per request - a random FAQ line, a random poll, a
// related-article list - and no amount of masking makes such a page the same twice, so the floor says how
// much of the page is not the same twice and --check only reports past it. A page with a high floor is not
// guarded, and the runner says so rather than letting a green run imply a guard that is not there
async function setOneShot(page, item, view, mode, tag, pairs, report) {
  if (job === 'contrast') {
    for (const pair of await getContrastPairs(page, item.name + tag, mode)) pairs.push({ theme: item.theme || 'lite', ...pair });
    return;
  }
  const tail = '-' + view.name + (mode === 'auto' ? '' : '-' + mode) + '.png';
  const name = item.name + tag + tail;
  const base = item.name + tail;
  const file = join(outDir, name);
  const now = await getStableShot(page, report, name);
  // A check whose baseline is missing must say so: writing it instead re-baselines the gate against the very tree it was
  // asked to judge, and the run exits green having compared nothing. Only a capture is allowed to create an image
  if (job === 'check' && !existsSync(file)) {
    report.push('  MISSING baseline ' + name + ' - capture it before checking against it');
    return;
  }
  if (job === 'capture' || !existsSync(file)) {
    writeFileSync(file, now);
    if (!tag) {
      await page.reload({ waitUntil: 'load' });
      await setMasks(page);
      await setScrolled(page);
      const twice = await getStableShot(page, report, name);
      floor[name] = (await getPixelDiff(page, now.toString('base64'), twice.toString('base64'))).ratio;
    }
    report.push('  wrote ' + name + (floor[base] > conf.threshold ? '   noise floor ' + (floor[base] * 100).toFixed(2) + '%, not guarded below that' : ''));
    return;
  }
  const bar = Math.max(conf.threshold, (floor[base] || 0) * 1.5);
  const diff = await getPixelDiff(page, readFileSync(file).toString('base64'), now.toString('base64'));
  const past = floor[base] ? '   past its ' + (bar * 100).toFixed(2) + '% floor' : '';
  if (diff.ratio > bar) report.push('  DIFF ' + (diff.ratio * 100).toFixed(3) + '% ' + diff.note + ' ' + name + past);
}

// One job of the PHP lifecycle, answered as JSON. The scratch tree and the account row are the only things this file
// changes on the stand, and every one of them is put back by the finally that wraps the walk. The probe writes its own
// logs somewhere, and that somewhere is outside the repository: a directory left inside storage/cache is one the next
// reader has to work out the provenance of
function getProbeAnswer(name, ...rest) {
  const work = join(tmpdir(), 'slaed_newtheme');
  const out = execFileSync('php', [join(root, 'tests/Support/theme_probe.php'), name, work, ...rest], { encoding: 'utf8' });
  const said = JSON.parse(out);
  if (said.error) throw new Error('theme probe: ' + said.error);
  return said;
}

// Render every page of the manifest that a frontend theme owns, in a scratch copy of an etalon, and report what a
// visitor would have been served. A theme that audits clean and cannot render is still not a theme, and only a real
// request can tell the two apart: the copy is selected through the `theme` column of the account the rig signs in as,
// which is the lever getTheme() reads before it falls back to the site default, so no configuration of a running
// stand is touched. getTheme() caches its answer in a static, so the switch has to be in place before the request
// rather than during it - which is why it is a database write and not a header the runner could send
async function checkNewTheme(browser, report) {
  if (!user || !pass) throw new Error('the HTTP half needs a session: set ' + conf.env.user + ' and ' + conf.env.pass);
  const made = getProbeAnswer('make', args.get('etalon') === undefined || args.get('etalon') === true ? 'lite' : args.get('etalon'));
  let back = null;
  try {
    back = getProbeAnswer('pick', user, made.name).was;
    const logs = (conf.logs || []).map((one) => [one, existsSync(join(root, one)) ? statSync(join(root, one)).size : 0]);
    const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
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
    if (back !== null) getProbeAnswer('pick', user, back);
    getProbeAnswer('gone', made.path);
  }
}

const floorFile = join(outDir, 'noise-floor.json');
const floor = existsSync(floorFile) ? JSON.parse(readFileSync(floorFile, 'utf8')) : {};

const browser = await chromium.launch();
const report = [];
const pairs = [];
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

const need = new Set((conf.pages || []).filter((p) => p.auth).map((p) => p.auth));
const sess = new Map();

mkdirSync(outDir, { recursive: true });

for (const kind of need) {
  if (!user || !pass) {
    report.push('  skipped every ' + kind + ' page: set ' + conf.env.user + ' and ' + conf.env.pass + ' in the environment');
    continue;
  }
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  try {
    await setSession(page, kind);
    sess.set(kind, ctx);
  } catch (err) {
    report.push('  ' + kind + ' login failed: ' + err.message);
    await ctx.close();
  }
  await page.close();
}

// A development stand serves its own certificate, and the manifest names https because the session cookie needs it
const open = await browser.newContext({ ignoreHTTPSErrors: true });

for (const mode of (job === 'contrast' ? conf.contrastmodes || conf.modes : conf.modes)) {
  for (const item of conf.pages) {
    if (only && item.name !== only) continue;
    const ctx = item.auth ? sess.get(item.auth) : open;
    if (!ctx) continue;
    const page = await ctx.newPage();
    if (mode !== 'auto') await ctx.addCookies([{ name: conf.cookie, value: mode, url: conf.base }]);
    try {
      for (const view of conf.viewports) await checkOnePage(page, item, view, mode, pairs, report);
    } catch (err) {
      report.push('  ' + item.name + ' failed: ' + err.message);
    }
    await page.close();
  }
}

await browser.close();

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

const noisy = Object.entries(floor).filter(([, v]) => v > conf.threshold);
if (noisy.length) {
  console.log(noisy.length + ' of ' + Object.keys(floor).length + ' states are not the same twice and are guarded only past their own floor:');
  for (const [k, v] of noisy.sort((a, b) => b[1] - a[1])) console.log('  ' + (v * 100).toFixed(2) + '%  ' + k);
}

const shots = existsSync(outDir) ? readdirSync(outDir).filter((f) => f.endsWith('.png')).length : 0;
console.log(job + ': ' + shots + ' baseline images under ' + outRel);
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
