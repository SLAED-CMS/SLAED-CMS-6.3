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
//
// A contrast pair existing only on hover is invisible to a crawler that never hovers, which is why the
// states live in the manifest and not in the runner. Credentials come from the environment, never the file.
//
// Before capturing, empty storage/cache/pages and storage/cache/templates and keep cache_css and css_h
// off: doCss() bundles when either is set, the bundle fingerprint sits in $conf['derived']['assets'], and
// a warm-cache comparison compares caches instead of renders.

import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync, existsSync, readdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const conf = JSON.parse(readFileSync(join(root, 'tools/ui-shots.json'), 'utf8'));
const args = new Map(process.argv.slice(2).map((a) => {
  const cut = a.indexOf('=');
  return cut === -1 ? [a.replace(/^--/, ''), true] : [a.slice(2, cut), a.slice(cut + 1)];
}));

const job = args.has('capture') ? 'capture' : args.has('contrast') ? 'contrast' : 'check';
const only = args.get('only');
const outDir = join(root, conf.out);
const user = process.env[conf.env.user] || '';
const pass = process.env[conf.env.pass] || '';

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
    // A gradient has no single background colour, so the stop that reads worst against the text is recorded.
    // A mostly transparent stop is a texture laid over the real background, not the background, so the walk
    // passes through it to the ancestor that actually paints - a 0.08 white stripe is not a white surface
    const worst = (css, fg) => {
      const stop = (css.backgroundImage || '').match(/rgba?\([^)]+\)/g);
      if (!stop) return null;
      let out = null;
      for (const item of stop) {
        const part = /rgba?\(([^)]+)\)/.exec(item)[1].split(/[\s,\/]+/).filter(Boolean).map(Number);
        if (part.length > 3 && part[3] < 0.5) continue;
        const rgb = solid(item);
        if (!rgb) continue;
        if (out === null || ratio(fg, rgb) < ratio(fg, out)) out = rgb;
      }
      return out;
    };
    const under = (node, fg) => {
      for (let el = node; el; el = el.parentElement) {
        const css = getComputedStyle(el);
        const bg = solid(css.backgroundColor) || worst(css, fg);
        if (bg) return { rgb: bg, sel: el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).join('.') : '') };
      }
      return { rgb: [255, 255, 255], sel: 'html' };
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

const floorFile = join(root, conf.out, 'noise-floor.json');
const floor = existsSync(floorFile) ? JSON.parse(readFileSync(floorFile, 'utf8')) : {};

const browser = await chromium.launch();
const report = [];
const pairs = [];
const need = new Set((conf.pages || []).filter((p) => p.auth).map((p) => p.auth));
const sess = new Map();

mkdirSync(outDir, { recursive: true });

for (const kind of need) {
  if (!user || !pass) {
    report.push('  skipped every ' + kind + ' page: set ' + conf.env.user + ' and ' + conf.env.pass + ' in the environment');
    continue;
  }
  const ctx = await browser.newContext();
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

const open = await browser.newContext();

for (const mode of conf.modes) {
  for (const item of conf.pages) {
    if (only && item.name !== only) continue;
    const ctx = item.auth ? sess.get(item.auth) : open;
    if (!ctx) continue;
    const page = await ctx.newPage();
    if (mode !== 'auto') await ctx.addCookies([{ name: 'mode', value: mode, url: conf.base }]);
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
  for (const pair of pairs) seen.set([pair.theme, pair.mode, pair.sel, pair.fg, pair.bg, pair.size, pair.weight].join('|'), pair);
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
console.log(job + ': ' + shots + ' baseline images under ' + conf.out);
for (const line of report) console.log(line);
process.exit(report.some((l) => l.includes('DIFF') || l.includes('failed')) ? 1 : 0);
