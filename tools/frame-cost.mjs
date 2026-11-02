// Author: Eduard Laas
// 2005 - 2026 SLAED
// License: MIT
// Website: slaed.net
//
// The rig of the frame cost: what a page costs while nobody touches it, and what a moving pointer or a scroll adds. It
// measures in interleaved pairs, because the absolute level moves between sessions and only a pair is a fact.
//
//   node tools/frame-cost.mjs                            every state, three rounds, the medians
//   node tools/frame-cost.mjs --before=HEAD              each state as a pair: the public files of that commit against the tree
//   node tools/frame-cost.mjs --patch=<address>=<file>   each state as a pair: the tree against that file served over the address
//   node tools/frame-cost.mjs --trace=<state>            restyles, forced layouts and the animations the compositor refuses
//   node tools/frame-cost.mjs --profile=<state>          the functions that hold the main thread
//   node tools/frame-cost.mjs --pixels=<part>            crops at seven moments of the cycle in both modes, one variant against the other;
//                                                        --tones=ok,info,warn,danger takes the cockpit through its states, --crops=<dir> keeps the PNGs
//   node tools/frame-cost.mjs --phoenix                  every layer of one round of the scene on a frozen clock, one variant against the other
//
// `--states=a,b` limits a measuring run, `--rounds=N` sets the pairs, `--tone=danger` holds the cockpit in another state
// than warn, the one of the table of the plan, and `--out=<file>` writes the samples as JSON. A job
// that compares takes its second variant from --before or --patch and without either compares the tree with itself,
// which is how the rig proves its own noise. The account comes from the environment, as for the screenshot rig.
//
// The HTTP cache is off for the whole context: a file served over an address stays in the cache under the same ?v= and
// would come back into the run of the original. A variant that served none of its files stops the run.

import { chromium } from 'playwright';
import { PNG } from 'pngjs';
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { setSession } from './ui-probe.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const conf = JSON.parse(readFileSync(join(root, 'tools/ui-shots.json'), 'utf8'));
const args = process.argv.slice(2).map((one) => {
  const cut = one.indexOf('=');
  return cut === -1 ? [one.replace(/^--/, ''), true] : [one.slice(2, cut), one.slice(cut + 1)];
});
const opts = new Map(args);

const view = { width: 1440, height: 900 };
const span = 4000;
const settle = 3000;
const tolerance = 16;
const moments = 7;
const frozen = new Date('2026-01-01T00:00:00Z').getTime();

// One state of the table: who opens what, where the page stands and what the hand does during the window
const states = {
  'home-idle': { auth: 'admin', url: '/admin.php', run: 'idle' },
  'home-pointer': { auth: 'admin', url: '/admin.php', run: 'pointer' },
  'home-nudge': { auth: 'admin', url: '/admin.php', run: 'nudge' },
  'home-scene': { auth: 'admin', url: '/admin.php', run: 'scene' },
  'pres-top': { auth: '', url: '/presentation', run: 'idle' },
  'pres-quiet': { auth: '', url: '/presentation', run: 'idle', top: 7200 },
  'pres-pipeline': { auth: '', url: '/presentation', run: 'idle', at: '#pipeline' },
  'pres-guard': { auth: '', url: '/presentation', run: 'idle', at: '#security' },
  'pres-pointer': { auth: '', url: '/presentation', run: 'pointer' },
  'pres-nudge': { auth: '', url: '/presentation', run: 'nudge' },
  'pres-scroll': { auth: '', url: '/presentation', run: 'scroll' },
};

// One part of a screen for the pixels: where it lies; a live figure cannot move, since the document and the polls answer both variants alike
// A ring drawn outside its element is cropped with the margin `pad` round the box, or the crop would hold the fill alone
// The mark of the state beats by a transform on the compositor, whose raster of the glyph differs by up to 64 of 255 between two loads of the same file, so it stands still
const beat = '.sl-cockpit-state .bi';
const parts = {
  cockpit: { auth: 'admin', url: '/admin.php', sel: '.sl-cockpit', still: beat },
  'cockpit-stat': { auth: 'admin', url: '/admin.php?name=statistic', sel: '.sl-cockpit', still: beat },
  'cockpit-mon': { auth: 'admin', url: '/admin.php?name=monitor', sel: '.sl-cockpit', still: beat },
  led: { auth: 'admin', url: '/admin.php', sel: '.sl-led-ok', pad: 8 },
  live: { auth: 'admin', url: '/admin.php', sel: '.sl-live-icon', pad: 8 },
  hero: { auth: '', url: '/presentation', sel: '.sl-pres-hero' },
  pipeline: { auth: '', url: '/presentation', sel: '#pipeline' },
  guard: { auth: '', url: '/presentation', sel: '#security' },
  runtime: { auth: '', url: '/presentation', sel: '#runtime' },
};

// The median of a list of numbers
function getMedian(list) {
  const sorted = [...list].sort((one, two) => one - two);
  const mid = sorted.length >> 1;
  return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
}

// Wait in real time: a frame loop of the page goes on meanwhile
function setPause(ms) {
  return new Promise((done) => setTimeout(done, ms));
}

// The files of one side of a pair by their public address; the name says which side is the original
function getVariants() {
  const out = [{ name: 'tree', files: new Map() }];
  const rev = opts.get('before');
  if (typeof rev === 'string') {
    const list = execFileSync('git', ['diff', '--name-only', '--diff-filter=M', rev, '--', 'public/'], { cwd: root, encoding: 'utf8' }).split('\n').filter(Boolean);
    const files = new Map();
    for (const path of list) {
      if (!/\.(css|js)$/.test(path)) continue;
      files.set('/' + path.replace(/^public\//, ''), execFileSync('git', ['show', rev + ':' + path], { cwd: root, maxBuffer: 1 << 28 }));
    }
    if (!files.size) throw new Error('nothing under public/ differs from ' + rev + ' in a .css or .js');
    out.unshift({ name: rev, files });
    return out;
  }
  const patch = args.filter(([key]) => key === 'patch').map(([, val]) => val);
  if (patch.length) {
    const files = new Map();
    for (const one of patch) {
      const cut = one.indexOf('=');
      files.set('/' + one.slice(0, cut).replace(/^\/+/, ''), readFileSync(one.slice(cut + 1)));
    }
    out.push({ name: 'patch', files });
  }
  return out;
}

// A context signed in when the state needs it, with the HTTP cache off for good
async function getContext(browser, kind, keep) {
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true, viewport: view, storageState: keep[kind] });
  await ctx.route('**/*', (route) => route.continue());
  if (kind && !keep[kind]) {
    const page = await ctx.newPage();
    await setSession(page, conf, kind);
    keep[kind] = await ctx.storageState();
    await page.close();
  }
  return ctx;
}

// A page with the files of its variant laid over their addresses
async function getPage(ctx, side, init) {
  const page = await ctx.newPage();
  const hits = new Set();
  if (init) await page.addInitScript(init);
  for (const [path, body] of side.files) {
    const type = path.endsWith('.css') ? 'text/css; charset=utf-8' : 'text/javascript; charset=utf-8';
    await page.route((url) => url.pathname === path, (route) => {
      hits.add(path);
      return route.fulfill({ status: 200, contentType: type, body });
    });
  }
  page.setProof = () => {
    if (!hits.size && side.files.size) throw new Error('variant ' + side.name + ' reached this page with none of its files');
  };
  return page;
}

// The cockpit held in one state, whatever the signals of the stand say and whatever a poll answers; the table of the plan is the state warn
async function setCockpitTone(page, tone) {
  await page.evaluate((tone) => {
    const box = document.querySelector('.sl-cockpit');
    if (!box) return;
    const hold = () => {
      const made = box.className.replace(/sl-knob-\w+/, 'sl-knob-' + box.slFrameTone);
      if (made !== box.className) box.className = made;
    };
    const first = !box.slFrameTone;
    box.slFrameTone = tone;
    hold();
    if (first) new MutationObserver(hold).observe(box, { attributes: true, attributeFilter: ['class'] });
  }, tone);
}

// Open a state and stand where it stands; the arrival of the page settles before anything is read
async function setOpened(page, item) {
  await page.goto(conf.base + item.url, { waitUntil: 'load' });
  page.setProof();
  await setCockpitTone(page, typeof opts.get('tone') === 'string' ? opts.get('tone') : 'warn');
  if (item.top) await page.evaluate((top) => window.scrollTo({ top, behavior: 'instant' }), item.top);
  if (item.at) await page.evaluate((sel) => document.querySelector(sel).scrollIntoView({ behavior: 'instant', block: 'start' }), item.at);
  await page.waitForTimeout(settle);
}

// Every number of the window that only ever grows, read at one moment
async function getCounters(page, cdp, hub) {
  const [{ metrics }, { processInfo }] = await Promise.all([cdp.send('Performance.getMetrics'), hub.send('SystemInfo.getProcessInfo')]);
  const out = { cpu: { renderer: 0, gpu: 0, browser: 0, other: 0 }, at: Date.now() };
  for (const one of metrics) out[one.name] = one.value;
  for (const one of processInfo) {
    const type = one.type.toLowerCase();
    out.cpu[type in out.cpu ? type : 'other'] += one.cpuTime;
  }
  out.frames = await page.evaluate(() => window.slFrameCost ? window.slFrameCost.frames : 0);
  return out;
}

// What one window cost, per second of it
function getWindow(was, now) {
  const secs = (now.at - was.at) / 1000;
  const rate = (name) => (now[name] - was[name]) / secs;
  return {
    renderer: (now.cpu.renderer - was.cpu.renderer) / secs * 100,
    gpu: (now.cpu.gpu - was.cpu.gpu) / secs * 100,
    browser: (now.cpu.browser - was.cpu.browser) / secs * 100,
    task: rate('TaskDuration') * 1000,
    script: rate('ScriptDuration') * 1000,
    style: rate('RecalcStyleDuration') * 1000,
    layout: rate('LayoutDuration') * 1000,
    styles: rate('RecalcStyleCount'),
    layouts: rate('LayoutCount'),
    frames: rate('frames'),
  };
}

// The frame counter starts with the window and is the only frame loop the rig adds
async function setFrameCount(page) {
  await page.evaluate(() => {
    if (window.slFrameCost) return;
    window.slFrameCost = { frames: 0 };
    const tick = () => {
      window.slFrameCost.frames++;
      window.requestAnimationFrame(tick);
    };
    window.requestAnimationFrame(tick);
  });
}

// The hand: 60 moves a second for the whole window, across the middle of the page or, nudging, in a circle at its edge where it enters no element
async function setPointerRun(page, ms, nudge) {
  const from = Date.now();
  let num = 0;
  while (Date.now() - from < ms) {
    const part = (Date.now() - from) / ms * Math.PI * 4;
    if (nudge) await page.mouse.move(view.width - 8 + Math.cos(part * 8) * 4, view.height / 2 + Math.sin(part * 8) * 4);
    else await page.mouse.move(view.width / 2 + Math.cos(part) * view.width * 0.4, view.height / 2 + Math.sin(part * 1.5) * view.height * 0.4);
    num++;
    const rest = from + num * 1000 / 60 - Date.now();
    if (rest > 0) await setPause(rest);
  }
}

// The wheel: 60 notches a second down the page for the whole window
async function setScrollRun(page, ms) {
  await page.mouse.move(view.width / 2, view.height / 2);
  const from = Date.now();
  let num = 0;
  while (Date.now() - from < ms) {
    await page.mouse.wheel(0, 40);
    num++;
    const rest = from + num * 1000 / 60 - Date.now();
    if (rest > 0) await setPause(rest);
  }
}

// What the hand does during the window of a state
async function setHandRun(page, item) {
  if (item.run === 'pointer' || item.run === 'nudge') await setPointerRun(page, span, item.run === 'nudge');
  else if (item.run === 'scroll') await setScrollRun(page, span);
  else await page.waitForTimeout(span);
}

// One round of the scene, from the class that opens it to the class leaving it, in windows of 1.5 s named by the act
async function getSceneRun(page, cdp, hub) {
  const acts = [
    ['code', 1.5], ['clear', 4.5], ['rise', 9], ['hold', 16.5], ['fall', 18.5], ['form', 22],
    ['big', 28], ['melt', 30.5], ['fly', 34], ['small', 39], ['dust', 41], ['land', 43.5],
  ];
  const over = () => page.evaluate(() => {
    const one = document.querySelector('.sl-phx-ground');
    return one ? one.classList.contains('sl-phx-over') : null;
  });
  await page.waitForFunction(() => {
    const one = document.querySelector('.sl-phx-ground');
    return one && !one.classList.contains('sl-phx-over');
  }, null, { timeout: 30000 });
  await page.waitForFunction(() => document.querySelector('.sl-phx-ground').classList.contains('sl-phx-over'), null, { timeout: 120000 });
  const from = Date.now();
  const start = await getCounters(page, cdp, hub);
  const list = [];
  let was = start;
  while (await over()) {
    await page.waitForTimeout(1500);
    const now = await getCounters(page, cdp, hub);
    const clock = 1.5 + (now.at - from) / 1000 - 0.75;
    const act = acts.filter(([, at]) => at <= clock).pop();
    list.push({ act: act ? act[0] : 'code', ...getWindow(was, now) });
    was = now;
  }
  const whole = getWindow(start, was);
  whole.acts = {};
  for (const [name] of acts) {
    const some = list.filter((one) => one.act === name);
    if (some.length) whole.acts[name] = Math.round(getMedian(some.map((one) => one.script)));
  }
  return whole;
}

// One measurement of one state on one side of a pair, on a page of its own
async function getSample(browser, keep, hub, side, name) {
  const item = states[name];
  const ctx = await getContext(browser, item.auth, keep);
  const init = item.run === 'scene' ? () => localStorage.setItem('slaed-toggle:saver', '0.2') : null;
  const page = await getPage(ctx, side, init);
  const cdp = await ctx.newCDPSession(page);
  await cdp.send('Performance.enable');
  await setOpened(page, item);
  await setFrameCount(page);
  let out;
  if (item.run === 'scene') {
    out = await getSceneRun(page, cdp, hub);
  } else {
    const was = await getCounters(page, cdp, hub);
    await setHandRun(page, item);
    out = getWindow(was, await getCounters(page, cdp, hub));
  }
  out.tone = await page.evaluate(() => {
    const one = document.querySelector('.sl-cockpit');
    return one ? (one.className.match(/sl-knob-(\w+)/) || [])[1] || '' : '';
  });
  await ctx.close();
  return out;
}

// The table: rounds of every state on every side, interleaved, so whatever the machine does falls on both alike
async function setTableRun(browser) {
  const sides = getVariants();
  const names = typeof opts.get('states') === 'string' ? opts.get('states').split(',') : Object.keys(states);
  const rounds = Number(opts.get('rounds')) || 3;
  const keep = {};
  const hub = await browser.newBrowserCDPSession();
  const data = {};
  for (const name of names) {
    if (!states[name]) throw new Error('no state ' + name + '; there are ' + Object.keys(states).join(', '));
    data[name] = Object.fromEntries(sides.map((side) => [side.name, []]));
  }
  for (let i = 0; i < rounds; i++) {
    for (const name of names) {
      const order = i % 2 ? [...sides].reverse() : sides;
      for (const side of order) {
        const one = await getSample(browser, keep, hub, side, name);
        data[name][side.name].push(one);
        const said = ['renderer', 'gpu', 'style', 'frames'].map((key) => key + ' ' + one[key].toFixed(0)).join(' ');
        console.log('  ' + (i + 1) + '/' + rounds + ' ' + name + ' [' + side.name + '] ' + said);
      }
    }
  }
  const rows = ['State | Side | Renderer % | GPU % | Script ms/s | Style ms/s | Layout ms/s | Frames/s | Tone'];
  for (const name of names) {
    for (const side of sides) {
      const list = data[name][side.name];
      const mid = (key) => getMedian(list.map((one) => one[key])).toFixed(0);
      const tones = [...new Set(list.map((one) => one.tone))].join('/');
      rows.push([name, side.name, mid('renderer'), mid('gpu'), mid('script'), mid('style'), mid('layout'), mid('frames'), tones].join(' | '));
      const acts = list.filter((one) => one.acts);
      for (const act of acts.length ? Object.keys(acts[0].acts) : []) {
        rows.push(['  ' + act, side.name, '', '', getMedian(acts.map((one) => one.acts[act])).toFixed(0), '', '', '', ''].join(' | '));
      }
    }
  }
  console.log(rows.join('\n'));
  if (typeof opts.get('out') === 'string') writeFileSync(opts.get('out'), JSON.stringify(data, null, 1));
}

// What a window of the trace holds: the restyles and their size, and the stacks that forced a layout
function getTraceReport(events) {
  const style = events.filter((one) => one.name === 'UpdateLayoutTree' && one.ph === 'X' && one.args && one.args.elementCount !== undefined);
  const sizes = style.map((one) => one.args.elementCount).sort((one, two) => one - two);
  const forced = new Map();
  for (const one of events) {
    const stack = one.args && one.args.beginData && one.args.beginData.stackTrace;
    if (!['Layout', 'UpdateLayoutTree'].includes(one.name) || !stack || !stack.length) continue;
    const key = one.name + ' ' + (stack[0].functionName || '(anonymous)') + ' ' + String(stack[0].url).split('/').pop().split('?')[0] + ':' + stack[0].lineNumber;
    forced.set(key, (forced.get(key) || 0) + 1);
  }
  return {
    restyles: style.length,
    size: sizes.length ? {
      median: getMedian(sizes),
      p90: sizes[Math.floor(sizes.length * 0.9)],
      max: sizes[sizes.length - 1],
      total: sizes.reduce((one, two) => one + two, 0),
    } : null,
    forced: [...forced].sort((one, two) => two[1] - one[1]).slice(0, 15),
  };
}

// The reason of every animation the compositor refused; an animation names itself once, when it starts, so this trace opens before the page does
function getRefusals(events) {
  const names = new Map();
  const refused = new Map();
  for (const one of events) {
    const data = one.name === 'Animation' && one.args && one.args.data;
    if (!data || !one.id2) continue;
    if (data.displayName) names.set(one.id2.local, data.displayName);
    if (!data.compositeFailed) continue;
    refused.set(one.id2.local, (data.unsupportedProperties || []).join(',') || 'code ' + data.compositeFailed);
  }
  const out = new Map();
  for (const [id, why] of refused) {
    const key = (names.get(id) || '(a transition)') + ' on ' + why;
    out.set(key, (out.get(key) || 0) + 1);
  }
  return [...out].sort((one, two) => two[1] - one[1]);
}

// The refusals from the load on, then the restyles and forced layouts of one state over one window
async function setTraceRun(browser, name) {
  const cats = ['devtools.timeline', 'disabled-by-default-devtools.timeline', 'disabled-by-default-devtools.timeline.stack', 'blink.animations'];
  const item = states[name];
  if (!item) throw new Error('no state ' + name + '; there are ' + Object.keys(states).join(', '));
  const keep = {};
  for (const side of getVariants()) {
    const ctx = await getContext(browser, item.auth, keep);
    const page = await getPage(ctx, side);
    await browser.startTracing(page, { categories: ['blink.animations'] });
    await setOpened(page, item);
    const refused = getRefusals(JSON.parse((await browser.stopTracing()).toString()).traceEvents);
    await browser.startTracing(page, { categories: cats });
    await setHandRun(page, item);
    const running = await page.evaluate(() => document.getAnimations().filter((one) => one.playState === 'running').length);
    const report = getTraceReport(JSON.parse((await browser.stopTracing()).toString()).traceEvents);
    await ctx.close();
    const said = running + ' animations running, ' + report.restyles + ' restyles in ' + span / 1000 + ' s, elements of one ' + JSON.stringify(report.size);
    console.log('[' + side.name + '] ' + name + ': ' + said);
    for (const [key, num] of report.forced) console.log('  forced ' + num + ' x ' + key);
    for (const [key, num] of refused) console.log('  refused ' + num + ' x ' + key);
  }
}

// The functions that hold the main thread over one window, by their own time
async function setProfileRun(browser, name) {
  const item = states[name];
  if (!item) throw new Error('no state ' + name + '; there are ' + Object.keys(states).join(', '));
  const keep = {};
  for (const side of getVariants()) {
    const ctx = await getContext(browser, item.auth, keep);
    const page = await getPage(ctx, side);
    const cdp = await ctx.newCDPSession(page);
    await setOpened(page, item);
    await cdp.send('Profiler.enable');
    await cdp.send('Profiler.start');
    await setHandRun(page, item);
    const { profile } = await cdp.send('Profiler.stop');
    await ctx.close();
    const nodes = new Map(profile.nodes.map((one) => [one.id, one]));
    const self = new Map();
    profile.samples.forEach((id, i) => {
      const one = nodes.get(id).callFrame;
      const key = (one.functionName || '(anonymous)') + ' ' + one.url.split('/').pop().split('?')[0] + ':' + one.lineNumber;
      self.set(key, (self.get(key) || 0) + profile.timeDeltas[i] / 1000);
    });
    console.log('[' + side.name + '] ' + name + ', ms of own time in ' + span / 1000 + ' s');
    for (const [key, ms] of [...self].sort((one, two) => two[1] - one[1]).slice(0, 15)) console.log('  ' + ms.toFixed(0).padStart(6) + ' ' + key);
  }
}

// The largest channel difference of two crops of one size, and how many pixels leave the tolerance
function getCropDiff(one, two) {
  const was = PNG.sync.read(one);
  const now = PNG.sync.read(two);
  if (was.width !== now.width || was.height !== now.height) {
    return { size: was.width + 'x' + was.height + ' / ' + now.width + 'x' + now.height, max: 255, over: was.width * was.height };
  }
  let max = 0;
  let over = 0;
  for (let i = 0; i < was.data.length; i += 4) {
    let big = 0;
    for (let j = 0; j < 4; j++) big = Math.max(big, Math.abs(was.data[i + j] - now.data[i + j]));
    if (big > max) max = big;
    if (big > tolerance) over++;
  }
  return { max, over };
}

// The page loads on a stopped clock with a chance of a seed: real time passes only outside it, so two loads run the same script in the same frames
async function setFrozenClock(page) {
  await page.addInitScript(() => {
    let seed = 2005;
    Math.random = () => {
      seed = (Math.imul(seed, 1664525) + 1013904223) >>> 0;
      return seed / 4294967296;
    };
  });
  await page.clock.install({ time: frozen });
  await page.clock.pauseAt(frozen + 1000);
}

// The document and every poll answer both variants with their first answer, and a poll stops once the crops begin, so a figure of the server cannot move between them
async function setFrozenPolls(ctx, polls) {
  await ctx.route('**/*', async (route) => {
    const req = route.request();
    const doc = req.isNavigationRequest();
    if (!doc && !req.headers()['x-sl-poll'] && !/[?&]op=live\b/.test(req.url())) return route.fallback();
    if (polls.shut && !doc) return route.abort();
    if (!polls.has(req.url())) {
      const res = await route.fetch();
      polls.set(req.url(), { status: res.status(), headers: res.headers(), body: await res.body() });
    }
    return route.fulfill(polls.get(req.url()));
  });
}

// One part at seven moments of its cycle: every loop of the page paused at the same share of its own run and a run that ends at its end
async function getCrops(browser, keep, polls, side, item, mode, tone) {
  const ctx = await getContext(browser, item.auth, keep);
  const page = await getPage(ctx, side);
  polls.shut = false;
  await setFrozenPolls(ctx, polls);
  await setFrozenClock(page);
  await setOpened(page, item);
  const box = page.locator(item.sel).first();
  await box.scrollIntoViewIfNeeded();
  await page.waitForTimeout(settle / 3);
  await page.clock.runFor(settle * 2);
  await page.waitForTimeout(settle);
  polls.shut = true;
  if (item.still) await page.addStyleTag({ content: item.still + ' { animation: none !important; }' });
  if (tone) await setCockpitTone(page, tone);
  await page.evaluate((mode) => {
    document.documentElement.setAttribute('data-theme', mode);
    document.getAnimations().forEach((one) => one.pause());
    document.querySelectorAll('svg').forEach((one) => one.pauseAnimations());
  }, mode);
  const out = [];
  for (let i = 0; i < moments; i++) {
    await page.evaluate(({ i, moments }) => {
      document.getAnimations().forEach((one) => {
        if (!(one.timeline instanceof DocumentTimeline)) return;
        const time = one.effect.getComputedTiming();
        const each = Number(time.duration) || 0;
        if (time.iterations === Infinity) one.currentTime = (time.delay || 0) + each * i / moments;
        else one.finish();
      });
      document.querySelectorAll('svg').forEach((one) => one.setCurrentTime(i * 0.37));
    }, { i, moments });
    const rect = item.pad ? await box.boundingBox() : null;
    const clip = rect ? { x: rect.x - item.pad, y: rect.y - item.pad, width: rect.width + item.pad * 2, height: rect.height + item.pad * 2 } : null;
    out.push(await (clip ? page.screenshot({ clip, animations: 'allow' }) : box.screenshot({ animations: 'allow' })));
  }
  await ctx.close();
  return out;
}

// One part, one variant against the other, in both modes; a first load only records the polls, and a variant left alone is compared with itself
async function setPixelsRun(browser, name) {
  const item = parts[name];
  if (!item) throw new Error('no part ' + name + '; there are ' + Object.keys(parts).join(', '));
  const sides = getVariants();
  const keep = {};
  const polls = new Map();
  const tones = typeof opts.get('tones') === 'string' ? opts.get('tones').split(',') : [''];
  const dir = opts.get('crops');
  let worst = 0;
  await getCrops(browser, keep, polls, sides[0], item, 'light', tones[0]);
  if (typeof dir === 'string') mkdirSync(dir, { recursive: true });
  for (const tone of tones) {
    for (const mode of ['light', 'dark']) {
      const one = await getCrops(browser, keep, polls, sides[0], item, mode, tone);
      const two = await getCrops(browser, keep, polls, sides[sides.length - 1], item, mode, tone);
      const row = one.map((crop, i) => getCropDiff(crop, two[i]));
      if (typeof dir === 'string') {
        one.forEach((crop, i) => writeFileSync(join(dir, [name, tone, mode, i, 'one'].filter((cell) => cell !== '').join('-') + '.png'), crop));
        two.forEach((crop, i) => writeFileSync(join(dir, [name, tone, mode, i, 'two'].filter((cell) => cell !== '').join('-') + '.png'), crop));
      }
      const max = Math.max(...row.map((cell) => cell.max));
      if (max > worst) worst = max;
      const said = row.map((cell) => cell.size || cell.max).join(' ') + ' of 255, past ' + tolerance + ': ' + row.map((cell) => cell.over).join(' ');
      console.log(name + (tone ? ' ' + tone : '') + ' ' + mode + ': largest difference by moment ' + said);
    }
  }
  console.log(worst <= tolerance ? 'within ' + tolerance + ' of 255' : 'LEFT the tolerance: ' + worst + ' of 255');
  if (worst > tolerance) process.exitCode = 1;
}

// Every layer of one round on the frozen clock, hashed; the logo decodes in the real time left to the load, and a moment is one sample of 0.5 s from the class that opens the round
async function getRound(browser, keep, side) {
  const ctx = await getContext(browser, 'admin', keep);
  const page = await getPage(ctx, side, () => localStorage.removeItem('slaed-toggle:saver'));
  await setFrozenClock(page);
  await page.goto(conf.base + '/admin.php', { waitUntil: 'load' });
  page.setProof();
  await page.waitForTimeout(settle);
  await page.clock.runFor(61000);
  const hash = () => page.evaluate(async () => {
    const list = [...document.querySelectorAll('canvas.sl-phx-layer')];
    const out = [];
    for (const one of list) {
      const data = one.getContext('2d').getImageData(0, 0, one.width, one.height).data;
      const made = await crypto.subtle.digest('SHA-1', data);
      out.push([...new Uint8Array(made)].map((num) => num.toString(16).padStart(2, '0')).join(''));
    }
    return out;
  });
  const over = () => page.evaluate(() => document.querySelector('.sl-phx-ground').classList.contains('sl-phx-over'));
  for (let i = 0; i < 40 && !(await over()); i++) await page.clock.runFor(250);
  if (!(await over())) throw new Error('the round did not start on the frozen clock');
  const out = [];
  while (await over()) {
    out.push(await hash());
    await page.clock.runFor(500);
  }
  await ctx.close();
  return out;
}

// One round, one variant against the other; a variant left alone is compared with itself
async function setPhoenixRun(browser) {
  const sides = getVariants();
  const keep = {};
  const one = await getRound(browser, keep, sides[0]);
  const two = await getRound(browser, keep, sides[sides.length - 1]);
  const count = Math.min(one.length, two.length);
  const layers = one[0].length;
  const left = [];
  for (let i = 0; i < count; i++) {
    for (let j = 0; j < layers; j++) if (one[i][j] !== two[i][j]) left.push('moment ' + i + ' (' + (1.5 + i / 2) + ' s) layer ' + j);
  }
  console.log(count + ' moments of ' + one.length + ' / ' + two.length + ' x ' + layers + ' layers: ' + (count * layers - left.length) + ' of ' + count * layers + ' equal');
  for (const row of left) console.log('  differs: ' + row);
  if (left.length || one.length !== two.length) process.exitCode = 1;
}

if (!process.env[conf.env.user]) {
  console.log('set ' + conf.env.user + ' and ' + conf.env.pass + ': the home of the panel needs the account');
  process.exit(2);
}
const browser = await chromium.launch({ headless: true, channel: 'chromium' });
try {
  if (typeof opts.get('trace') === 'string') await setTraceRun(browser, opts.get('trace'));
  else if (typeof opts.get('profile') === 'string') await setProfileRun(browser, opts.get('profile'));
  else if (typeof opts.get('pixels') === 'string') await setPixelsRun(browser, opts.get('pixels'));
  else if (opts.has('phoenix')) await setPhoenixRun(browser);
  else await setTableRun(browser);
} finally {
  await browser.close();
}
