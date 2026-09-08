/* The deck: the shared behaviour of the presentation series. A variant is markup and a palette; every chart,
   table, stream, switch and scene it shows is drawn from here, from one body of illustrative data, so eighteen
   faces of the main page cannot disagree about a figure. Nothing here ships: this is the stand.

   Every figure below is illustrative - a plausible shape for a chart, not monitoring and not a promise. The
   facts that are real are marked as such where they are declared. */

/* Deterministic noise, so a chart looks the same on every load and two variants compared side by side show the
   same curve rather than two rolls of a die */
function getDeckRandom(seed) {
  let s = seed >>> 0;
  return () => {
    s = (s + 0x6D2B79F5) >>> 0;
    let t = s;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

/* A series with a slow wave and fast noise on top: the shape of a traffic curve */
function getDeckSeries(seed, n, min, max, wave) {
  const rnd = getDeckRandom(seed);
  const out = [];
  for (let i = 0; i < n; i++) {
    const ph = (i / n) * Math.PI * 2 * (wave || 1.4);
    const base = 0.5 + 0.28 * Math.sin(ph) + 0.12 * Math.sin(ph * 2.7 + 1);
    const v = min + (max - min) * Math.max(0, Math.min(1, base + (rnd() - 0.5) * 0.28));
    out.push(Math.round(v));
  }
  return out;
}

const DECK_DAYS = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
const DECK_HOURS = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));

/* The traffic of a period: labels and four fields. The totals are what the leader shows (10 693 visits over the
   snapshot); the per-day distribution is illustrative. */
function getDeckTraffic(key, n, seed) {
  const labels = key === '7d'
    ? DECK_DAYS
    : key === '30d'
      ? Array.from({ length: n }, (_, i) => String(i + 1).padStart(2, '0'))
      : Array.from({ length: n }, (_, i) => 'W' + String(i + 1).padStart(2, '0'));
  const visits = getDeckSeries(seed, n, key === '90d' ? 2100 : 260, key === '90d' ? 3900 : 560, 1.6);
  const humans = visits.map((v, i) => Math.round(v * (0.78 + 0.08 * Math.sin(i))));
  const bots = visits.map((v, i) => Math.round(v * (0.14 + 0.05 * Math.cos(i * 1.3))));
  const attacks = visits.map((v, i) => Math.round(v * (0.03 + 0.03 * Math.abs(Math.sin(i * 2.1)))));
  const pages = visits.map((v) => Math.round(v * 3.4));
  return { labels, visits, humans, bots, attacks, pages };
}

const DECK_DATA = {
  /* Real facts of the product, the ones the leader states */
  facts: { version: '8.0', code: 'Phoenix', addons: 685, since: 2005, years: 21, license: 'MIT', gen: 0.027, sql: 12, screens: 71, online: 76 },

  traffic: {
    '7d': getDeckTraffic('7d', 7, 11),
    '30d': getDeckTraffic('30d', 30, 23),
    '90d': getDeckTraffic('90d', 13, 37),
  },

  /* Response time over a day, milliseconds, and the SQL count beside it */
  response: {
    labels: DECK_HOURS,
    ms: getDeckSeries(41, 24, 19, 41, 1.1),
    sql: getDeckSeries(43, 24, 9, 15, 2.2),
    cache: getDeckSeries(47, 24, 78, 97, 0.9),
  },

  /* Hits by hour from the leader's snapshot, as a share of the busiest hour */
  hourly: [46, 9, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 2, 1, 22, 49, 35, 31, 54, 82, 44, 52],

  /* Traffic classes of the request guard */
  classes: [
    { key: 'human', label: 'Человек', share: 61, color: 'accent', icon: 'bi-person-fill', rule: 'ALLOW' },
    { key: 'search', label: 'Поисковик', share: 18, color: 'info', icon: 'bi-search', rule: 'ALLOW / INDEX' },
    { key: 'ai', label: 'AI-агент', share: 9, color: 'good', icon: 'bi-stars', rule: 'CLASSIFY' },
    { key: 'bot', label: 'Сервисный бот', share: 7, color: 'warn', icon: 'bi-robot', rule: 'ALLOW' },
    { key: 'attack', label: 'Атака', share: 5, color: 'bad', icon: 'bi-bug-fill', rule: 'BLOCK' },
  ],

  /* Runtime shares for donuts and rings */
  runtime: { labels: ['Генерация', 'SQL', 'Шаблон', 'Кэш'], ms: [11, 9, 5, 2] },

  /* The module registry: names are real modules of the system, the figures beside them are illustrative */
  modules: [
    { name: 'News', cat: 'Content', ver: '8.0.1', status: 'on', hits: 4210, time: 3.1, share: 92, icon: 'bi-newspaper' },
    { name: 'Pages', cat: 'Content', ver: '8.0.1', status: 'on', hits: 2870, time: 2.4, share: 78, icon: 'bi-file-earmark-text' },
    { name: 'Files', cat: 'Content', ver: '8.0.0', status: 'on', hits: 3160, time: 4.2, share: 84, icon: 'bi-folder2-open' },
    { name: 'Forum', cat: 'Community', ver: '8.0.0', status: 'on', hits: 1980, time: 5.6, share: 66, icon: 'bi-chat-square-text' },
    { name: 'Users', cat: 'Control', ver: '8.0.1', status: 'on', hits: 1420, time: 2.9, share: 58, icon: 'bi-people' },
    { name: 'Account', cat: 'Control', ver: '8.0.1', status: 'on', hits: 960, time: 3.3, share: 44, icon: 'bi-person-circle' },
    { name: 'Search', cat: 'Core', ver: '8.0.0', status: 'on', hits: 1730, time: 6.8, share: 61, icon: 'bi-search' },
    { name: 'SEO', cat: 'Core', ver: '8.0.0', status: 'on', hits: 540, time: 1.2, share: 30, icon: 'bi-globe2' },
    { name: 'Security', cat: 'Core', ver: '8.0.2', status: 'on', hits: 5120, time: 0.8, share: 100, icon: 'bi-shield-lock' },
    { name: 'Statistics', cat: 'Core', ver: '8.0.0', status: 'on', hits: 610, time: 1.9, share: 33, icon: 'bi-bar-chart' },
    { name: 'Comments', cat: 'Community', ver: '8.0.0', status: 'on', hits: 1240, time: 2.7, share: 52, icon: 'bi-chat-dots' },
    { name: 'Gallery', cat: 'Content', ver: '7.9.4', status: 'off', hits: 0, time: 0, share: 0, icon: 'bi-images' },
    { name: 'Shop', cat: 'Commerce', ver: '7.9.2', status: 'off', hits: 0, time: 0, share: 0, icon: 'bi-bag' },
    { name: 'Vote', cat: 'Community', ver: '8.0.0', status: 'on', hits: 380, time: 1.4, share: 24, icon: 'bi-hand-thumbs-up' },
    { name: 'Sitemap', cat: 'Core', ver: '8.0.0', status: 'on', hits: 210, time: 1.1, share: 16, icon: 'bi-diagram-3' },
    { name: 'Blocks', cat: 'Core', ver: '8.0.1', status: 'on', hits: 6020, time: 0.6, share: 100, icon: 'bi-grid-1x2' },
  ],

  /* Real commits of master, as the leader lists them */
  commits: [
    { sha: 'f360f6a', date: '26.08.2026', time: '15:43', title: 'One window frame for the whole system; file manager grows up', tags: ['UI', 'file manager', 'refactor'], text: 'Один канонический window-frame вместо ручных копий; файловые менеджеры получают сортировку, полные листинги и свойства файлов.' },
    { sha: '1a200a8', date: '25.08.2026', time: '22:39', title: 'Module block setting works again; content gets its width back', tags: ['layout', 'modules', 'fix'], text: 'Восстановлено чтение side/top настройки модулей; grid отдаёт контенту ширину колонок, которых нет.' },
    { sha: '687d958', date: '25.08.2026', time: '15:32', title: 'Presentation stand gets 23 designs; 71 site screenshots restored', tags: ['demo', 'showcase', 'design'], text: 'Презентационная страница сравнивается на реальных вариантах дизайна; каталог внедрений снова показывает настоящие сайты.' },
    { sha: '7cd6144', date: '25.08.2026', time: '13:03', title: 'Photo bands gain a light beam; season art ships as WebP', tags: ['theme', 'WebP', 'motion'], text: 'Фото-полосы получают контролируемое движение, а сезонные изображения переведены в WebP.' },
    { sha: '12e1c35', date: '24.08.2026', time: '23:12', title: 'Settings window reopens after POST; demo carries 16 band treatments', tags: ['admin', 'UX', 'prototype'], text: 'Настройки сохраняют контекст окна после навигации, а стенд получает отдельные варианты оформления.' },
  ],

  /* Pools the streams draw from */
  pools: {
    guard: [
      ['HUMAN · GET /index.php?name=news', 'allow', ''],
      ['SEARCH · GET /sitemap.xml', 'index', 'info'],
      ['AI · GET /content', 'classify', 'info'],
      ['BOT · GET /rss', 'allow', ''],
      ['SQLi · id=1 UNION SELECT', 'block', 'bad'],
      ['HUMAN · GET /files', 'allow', ''],
      ['XSS · q=<script>', 'block', 'bad'],
      ['TRAVERSAL · ../../etc/passwd', 'block', 'bad'],
      ['HUMAN · POST /account', 'allow', ''],
      ['BRUTE · POST /account × 27', 'block', 'bad'],
      ['PROBE · GET /.env', 'block', 'bad'],
      ['BOT · GET /robots.txt', 'allow', ''],
    ],
    runtime: [
      ['request.filter', 'allowed', ''],
      ['session.verify', 'verified', ''],
      ['query.analyze', 'review', 'warn'],
      ['cache.refresh', 'complete', ''],
      ['template.render', '12 ms', ''],
      ['injection.scan', 'blocked', 'bad'],
      ['files.download', '+1', ''],
      ['sitemap.build', 'ok', ''],
      ['parser.cache', 'warm', 'info'],
      ['pdo.prepare', 'native', ''],
    ],
    core: [
      ['kernel.boot', 'ok', ''],
      ['config.load', 'ok', ''],
      ['module.resolve', '3 ms', ''],
      ['blocks.render', '6', ''],
      ['users.online', '76', 'info'],
      ['cache.store', 'ok', ''],
      ['response.send', '200', ''],
    ],
  },

  /* The PDO cases of the leader: the shape of Database::getSqlQuery() */
  pdo: [
    { verb: 'SELECT', query: 'SELECT id, title FROM slaed_news WHERE cat = :cat ORDER BY time DESC LIMIT ?', params: ':cat=2 · ?=10', prepared: true, result: '10 rows · FETCH_BOTH', elapsed: 0.00083, write: false },
    { verb: 'SELECT', query: 'SELECT id, user_name FROM slaed_users WHERE user_id = :id', params: ':id=42', prepared: true, result: '1 row · FETCH_BOTH', elapsed: 0.00046, write: false },
    { verb: 'SELECT', query: 'SELECT COUNT(*) AS total FROM slaed_comments WHERE status = ?', params: '?=1', prepared: true, result: '1 field · FETCH_BOTH', elapsed: 0.00061, write: false },
    { verb: 'UPDATE', query: 'UPDATE slaed_news SET counter = counter + 1 WHERE id = :id', params: ':id=125', prepared: true, result: '1 affected row', elapsed: 0.00074, write: true },
    { verb: 'SHOW', query: 'SHOW TABLE STATUS', params: '—', prepared: false, result: '34 rows · PDOStatement', elapsed: 0.00112, write: false },
  ],

  /* The page cache scenarios of the leader */
  cache: [
    { mode: 'miss', badge: 'MISS · BUILD', route: 'GET /index.php?name=news&cat=1 · guest', seq: ['request', 'guard', 'cache', 'kernel', 'module', 'template', 'response'], state: 'rebuild → body + sidecar' },
    { mode: 'hit', badge: 'HIT · READY PAGE', route: 'GET /index.php?name=news&cat=1 · guest', seq: ['request', 'guard', 'cache', 'response'], state: 'body → sidecar → dynamic' },
    { mode: 'hit', badge: 'HIT · DYNAMIC LIVE', route: 'GET / · guest', seq: ['request', 'guard', 'cache', 'response'], state: 'body → sidecar → dynamic' },
    { mode: 'bypass', badge: 'BYPASS · LIVE', route: 'POST /account · logged-in visitor', seq: ['request', 'guard', 'kernel', 'module', 'template', 'response'], state: 'full live render · no store' },
  ],
};

const deckState = { period: {}, guard: true, cache: true, charts: [] };

/* Colours are read through a probe, because a custom property holding light-dark() computes to its own text and
   the canvas needs the resolved colour of the mode the page is in */
function getDeckColor(el, name) {
  const probe = document.createElement('i');
  probe.style.color = 'var(--p-' + name + ')';
  probe.style.display = 'none';
  el.appendChild(probe);
  const out = getComputedStyle(probe).color;
  probe.remove();
  return out || '#888';
}

/* The same colour at another alpha; anything the browser did not hand back as rgb() is returned as it came */
function getDeckAlpha(color, a) {
  const m = color.match(/rgba?\(([^)]+)\)/);
  if (!m) return color;
  const p = m[1].split(/[\s,\/]+/).filter(Boolean);
  return 'rgba(' + p[0] + ',' + p[1] + ',' + p[2] + ',' + a + ')';
}

/* Which figures a chart draws: a key into the data above (with the period the page chose), or inline values */
function getDeckData(el) {
  const key = el.dataset.key;
  if (key) {
    let src = DECK_DATA[key];
    if (src && !src.labels) src = src[deckState.period[key] || el.dataset.period || Object.keys(src)[0]];
    if (!src) return { labels: [], series: [] };
    const fields = (el.dataset.fields || Object.keys(src).filter((k) => k !== 'labels')).toString().split(',');
    return { labels: src.labels, series: fields.map((f) => src[f.trim()] || []), names: fields };
  }
  const raw = (el.dataset.values || '').split('|').map((s) => s.split(',').map(Number));
  const labels = (el.dataset.labels || '').split(',').filter(Boolean);
  return { labels, series: raw, names: (el.dataset.names || '').split(',') };
}

/* The canvas is sized to its box at device resolution, once per draw */
function getDeckCanvas(box) {
  let cv = box.querySelector('canvas');
  if (!cv) {
    cv = document.createElement('canvas');
    box.appendChild(cv);
  }
  const dpr = Math.min(devicePixelRatio || 1, 2);
  const w = box.clientWidth;
  const h = box.clientHeight;
  const rw = Math.max(1, Math.round(w * dpr));
  const rh = Math.max(1, Math.round(h * dpr));
  if (cv.width !== rw || cv.height !== rh) {
    cv.width = rw;
    cv.height = rh;
  }
  const ctx = cv.getContext('2d');
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  ctx.clearRect(0, 0, w, h);
  return { cv, ctx, w, h };
}

function getDeckPalette(el, n) {
  const names = (el.dataset.colors || 'accent,good,info,warn,bad,accent2').split(',');
  const out = [];
  for (let i = 0; i < Math.max(n, 1); i++) out.push(getDeckColor(el, names[i % names.length].trim()));
  return out;
}

function getDeckRange(series, el) {
  let lo = Infinity;
  let hi = -Infinity;
  series.forEach((s) => s.forEach((v) => { lo = Math.min(lo, v); hi = Math.max(hi, v); }));
  if (!isFinite(lo)) { lo = 0; hi = 1; }
  if (el.dataset.min !== undefined) lo = Number(el.dataset.min);
  else lo = el.dataset.zero !== undefined ? 0 : Math.max(0, lo - (hi - lo) * 0.25);
  if (el.dataset.max !== undefined) hi = Number(el.dataset.max);
  else hi = hi + (hi - lo) * 0.12;
  if (hi === lo) hi = lo + 1;
  return { lo, hi };
}

/* Lines and areas: smooth curves through the points, a soft grid, labels along the floor, a crosshair tooltip */
function setDeckLine(box, el, data) {
  const { ctx, w, h } = getDeckCanvas(box);
  const axis = el.dataset.axis !== '0';
  const pad = { l: axis ? 36 : 6, r: 8, t: 10, b: axis ? 22 : 6 };
  const colors = getDeckPalette(el, data.series.length);
  const muted = getDeckColor(el, 'muted');
  const line = getDeckColor(el, 'line');
  const { lo, hi } = getDeckRange(data.series, el);
  const n = Math.max(...data.series.map((s) => s.length), 2);
  const px = (i) => pad.l + (i / (n - 1)) * (w - pad.l - pad.r);
  const py = (v) => pad.t + (1 - (v - lo) / (hi - lo)) * (h - pad.t - pad.b);
  const smooth = el.dataset.smooth !== '0';
  const fill = el.dataset.fill !== '0' && el.dataset.chart === 'area';
  if (el.dataset.grid !== '0') {
    ctx.strokeStyle = getDeckAlpha(line, 0.9);
    ctx.lineWidth = 1;
    ctx.setLineDash([3, 5]);
    for (let g = 0; g <= 4; g++) {
      const y = pad.t + (g / 4) * (h - pad.t - pad.b);
      ctx.beginPath();
      ctx.moveTo(pad.l, y);
      ctx.lineTo(w - pad.r, y);
      ctx.stroke();
    }
    ctx.setLineDash([]);
  }
  if (axis) {
    ctx.fillStyle = muted;
    ctx.font = '10px ui-monospace, Consolas, monospace';
    ctx.textAlign = 'right';
    for (let g = 0; g <= 4; g++) {
      const v = hi - (g / 4) * (hi - lo);
      ctx.fillText(v >= 1000 ? (v / 1000).toFixed(1) + 'k' : String(Math.round(v)), pad.l - 6, pad.t + (g / 4) * (h - pad.t - pad.b) + 3);
    }
    ctx.textAlign = 'center';
    const every = Math.max(1, Math.ceil(n / Math.max(2, Math.floor((w - pad.l) / 46))));
    data.labels.forEach((lab, i) => { if (i % every === 0) ctx.fillText(lab, px(i), h - 6); });
  }
  data.series.forEach((s, si) => {
    if (!s.length) return;
    ctx.beginPath();
    ctx.moveTo(px(0), py(s[0]));
    for (let i = 1; i < s.length; i++) {
      const x0 = px(i - 1);
      const y0 = py(s[i - 1]);
      const x1 = px(i);
      const y1 = py(s[i]);
      if (smooth) ctx.bezierCurveTo((x0 + x1) / 2, y0, (x0 + x1) / 2, y1, x1, y1);
      else ctx.lineTo(x1, y1);
    }
    if (fill) {
      const g = ctx.createLinearGradient(0, pad.t, 0, h - pad.b);
      g.addColorStop(0, getDeckAlpha(colors[si], si === 0 ? 0.3 : 0.14));
      g.addColorStop(1, getDeckAlpha(colors[si], 0));
      ctx.save();
      ctx.lineTo(px(s.length - 1), h - pad.b);
      ctx.lineTo(px(0), h - pad.b);
      ctx.closePath();
      ctx.fillStyle = g;
      ctx.fill();
      ctx.restore();
      ctx.beginPath();
      ctx.moveTo(px(0), py(s[0]));
      for (let i = 1; i < s.length; i++) {
        const x0 = px(i - 1);
        const y0 = py(s[i - 1]);
        const x1 = px(i);
        const y1 = py(s[i]);
        if (smooth) ctx.bezierCurveTo((x0 + x1) / 2, y0, (x0 + x1) / 2, y1, x1, y1);
        else ctx.lineTo(x1, y1);
      }
    }
    ctx.strokeStyle = colors[si];
    ctx.lineWidth = Number(el.dataset.thick || (si === 0 ? 2 : 1.5));
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
    if (si > 0 && el.dataset.dash !== undefined) ctx.setLineDash([4, 4]);
    ctx.stroke();
    ctx.setLineDash([]);
    if (el.dataset.dots !== undefined) {
      s.forEach((v, i) => {
        ctx.beginPath();
        ctx.arc(px(i), py(v), 3, 0, Math.PI * 2);
        ctx.fillStyle = getDeckColor(el, 'panel');
        ctx.fill();
        ctx.strokeStyle = colors[si];
        ctx.lineWidth = 2;
        ctx.stroke();
      });
    }
  });
  const hov = box.deckHover;
  if (hov !== undefined && hov >= 0 && hov < n) {
    ctx.strokeStyle = getDeckAlpha(muted, 0.6);
    ctx.setLineDash([3, 3]);
    ctx.beginPath();
    ctx.moveTo(px(hov), pad.t);
    ctx.lineTo(px(hov), h - pad.b);
    ctx.stroke();
    ctx.setLineDash([]);
    data.series.forEach((s, si) => {
      if (s[hov] === undefined) return;
      ctx.beginPath();
      ctx.arc(px(hov), py(s[hov]), 4, 0, Math.PI * 2);
      ctx.fillStyle = colors[si];
      ctx.fill();
    });
  }
  box.deckGeo = { px, n, pad, colors };
}

/* Bars: grouped side by side, or stacked when the chart says so; rounded on the top */
function setDeckBars(box, el, data) {
  const { ctx, w, h } = getDeckCanvas(box);
  const axis = el.dataset.axis !== '0';
  const pad = { l: axis ? 36 : 4, r: 4, t: 8, b: axis ? 22 : 4 };
  const stack = el.dataset.stack !== undefined;
  const colors = getDeckPalette(el, data.series.length);
  const muted = getDeckColor(el, 'muted');
  const line = getDeckColor(el, 'line');
  const n = Math.max(...data.series.map((s) => s.length), 1);
  const sums = stack ? Array.from({ length: n }, (_, i) => data.series.reduce((a, s) => a + (s[i] || 0), 0)) : [];
  const { hi } = getDeckRange(stack ? [sums] : data.series, { dataset: { zero: '', max: el.dataset.max } });
  const lo = 0;
  const gw = (w - pad.l - pad.r) / n;
  const gap = Math.max(2, gw * Number(el.dataset.gap || 0.28));
  const bw = stack ? gw - gap : (gw - gap) / data.series.length;
  const py = (v) => pad.t + (1 - (v - lo) / (hi - lo)) * (h - pad.t - pad.b);
  if (el.dataset.grid !== '0') {
    ctx.strokeStyle = getDeckAlpha(line, 0.9);
    ctx.setLineDash([3, 5]);
    for (let g = 0; g <= 4; g++) {
      const y = pad.t + (g / 4) * (h - pad.t - pad.b);
      ctx.beginPath();
      ctx.moveTo(pad.l, y);
      ctx.lineTo(w - pad.r, y);
      ctx.stroke();
    }
    ctx.setLineDash([]);
  }
  if (axis) {
    ctx.fillStyle = muted;
    ctx.font = '10px ui-monospace, Consolas, monospace';
    ctx.textAlign = 'right';
    for (let g = 0; g <= 4; g++) {
      const v = hi - (g / 4) * (hi - lo);
      ctx.fillText(v >= 1000 ? (v / 1000).toFixed(1) + 'k' : String(Math.round(v)), pad.l - 6, pad.t + (g / 4) * (h - pad.t - pad.b) + 3);
    }
    ctx.textAlign = 'center';
    const every = Math.max(1, Math.ceil(n / Math.max(2, Math.floor((w - pad.l) / 40))));
    data.labels.forEach((lab, i) => { if (i % every === 0) ctx.fillText(lab, pad.l + gw * i + gw / 2, h - 6); });
  }
  const hov = box.deckHover;
  for (let i = 0; i < n; i++) {
    let base = h - pad.b;
    data.series.forEach((s, si) => {
      const v = s[i] || 0;
      const x = stack ? pad.l + gw * i + gap / 2 : pad.l + gw * i + gap / 2 + bw * si;
      const top = stack ? base - (h - pad.b - py(v)) : py(v);
      const bh = stack ? base - top : h - pad.b - top;
      ctx.fillStyle = hov === i ? colors[si] : getDeckAlpha(colors[si], 0.82);
      const r = Math.min(4, bw / 2);
      ctx.beginPath();
      ctx.roundRect(x, top, bw, Math.max(bh, 1), stack && si < data.series.length - 1 ? 0 : [r, r, 0, 0]);
      ctx.fill();
      if (stack) base = top;
    });
  }
  box.deckGeo = { px: (i) => pad.l + gw * i + gw / 2, n, pad, colors };
}

/* Horizontal bars: one per label, value at the end */
function setDeckHbars(box, el, data) {
  const { ctx, w, h } = getDeckCanvas(box);
  const colors = getDeckPalette(el, data.labels.length);
  const muted = getDeckColor(el, 'muted');
  const ink = getDeckColor(el, 'ink');
  const line = getDeckColor(el, 'line');
  const s = data.series[0] || [];
  const n = s.length;
  const lw = Number(el.dataset.labelw || 90);
  const rh = h / Math.max(n, 1);
  const bh = Math.min(rh * 0.56, 18);
  const max = Math.max(...s, 1);
  ctx.font = '11px ui-monospace, Consolas, monospace';
  s.forEach((v, i) => {
    const y = rh * i + rh / 2;
    ctx.fillStyle = muted;
    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    ctx.fillText(data.labels[i] || '', 0, y);
    ctx.fillStyle = getDeckAlpha(line, 0.9);
    ctx.beginPath();
    ctx.roundRect(lw, y - bh / 2, w - lw - 40, bh, bh / 2);
    ctx.fill();
    ctx.fillStyle = el.dataset.mono !== undefined ? colors[0] : colors[i % colors.length];
    ctx.beginPath();
    ctx.roundRect(lw, y - bh / 2, Math.max(bh, (w - lw - 40) * (v / max)), bh, bh / 2);
    ctx.fill();
    ctx.fillStyle = ink;
    ctx.textAlign = 'right';
    ctx.fillText(String(v) + (el.dataset.unit || ''), w, y);
  });
  ctx.textBaseline = 'alphabetic';
}

/* Donuts: arcs with a hairline gap; the legend, if the chart names one, is written beside it */
function setDeckDonut(box, el, data) {
  const { ctx, w, h } = getDeckCanvas(box);
  const vals = data.series[0] || [];
  const colors = getDeckPalette(el, vals.length);
  const sum = vals.reduce((a, b) => a + b, 0) || 1;
  const r = Math.min(w, h) / 2 - 4;
  const thick = Number(el.dataset.thick || Math.max(10, r * 0.28));
  const cx = w / 2;
  const cy = h / 2;
  let a = -Math.PI / 2;
  const hov = box.deckHover;
  vals.forEach((v, i) => {
    const b = a + (v / sum) * Math.PI * 2;
    ctx.beginPath();
    ctx.arc(cx, cy, r - (hov === i ? 0 : 2), a + 0.02, b - 0.02);
    ctx.strokeStyle = hov !== undefined && hov !== i ? getDeckAlpha(colors[i], 0.45) : colors[i];
    ctx.lineWidth = thick;
    ctx.lineCap = 'butt';
    ctx.stroke();
    a = b;
  });
  box.deckGeo = { donut: true, cx, cy, r, thick, vals, sum, colors };
  const leg = el.dataset.legend ? document.getElementById(el.dataset.legend) : null;
  if (leg && !leg.dataset.written) {
    leg.dataset.written = '1';
    leg.innerHTML = vals.map((v, i) => '<span><i style="--c:' + colors[i] + '"></i>' + (data.labels[i] || '') + ' <b>' + (el.dataset.percent !== undefined ? Math.round((v / sum) * 100) + '%' : v) + '</b></span>').join('');
  }
}

/* A gauge: a 240-degree arc, the value on it, ticks around */
function setDeckGauge(box, el) {
  const { ctx, w, h } = getDeckCanvas(box);
  const v = Number(el.dataset.value || 0);
  const max = Number(el.dataset.max || 100);
  const color = getDeckColor(el, el.dataset.color || 'accent');
  const line = getDeckColor(el, 'line');
  const muted = getDeckColor(el, 'muted');
  const r = Math.min(w / 2, h / 1.25) - 8;
  const cx = w / 2;
  const cy = h / 2 + r * 0.2;
  const a0 = Math.PI * 0.75;
  const a1 = Math.PI * 2.25;
  const thick = Number(el.dataset.thick || Math.max(8, r * 0.16));
  ctx.lineCap = 'round';
  ctx.beginPath();
  ctx.arc(cx, cy, r, a0, a1);
  ctx.strokeStyle = getDeckAlpha(line, 0.9);
  ctx.lineWidth = thick;
  ctx.stroke();
  ctx.beginPath();
  ctx.arc(cx, cy, r, a0, a0 + (a1 - a0) * Math.max(0, Math.min(1, v / max)));
  ctx.strokeStyle = color;
  ctx.stroke();
  if (el.dataset.ticks !== undefined) {
    ctx.strokeStyle = muted;
    ctx.lineWidth = 1;
    for (let i = 0; i <= 12; i++) {
      const a = a0 + (a1 - a0) * (i / 12);
      ctx.beginPath();
      ctx.moveTo(cx + Math.cos(a) * (r - thick), cy + Math.sin(a) * (r - thick));
      ctx.lineTo(cx + Math.cos(a) * (r - thick - (i % 3 === 0 ? 7 : 4)), cy + Math.sin(a) * (r - thick - (i % 3 === 0 ? 7 : 4)));
      ctx.stroke();
    }
  }
}

/* Radar: one polygon per series over the labels */
function setDeckRadar(box, el, data) {
  const { ctx, w, h } = getDeckCanvas(box);
  const labs = data.labels;
  const n = labs.length || 1;
  const colors = getDeckPalette(el, data.series.length);
  const line = getDeckColor(el, 'line2');
  const muted = getDeckColor(el, 'muted');
  const cx = w / 2;
  const cy = h / 2;
  const r = Math.min(w, h) / 2 - 26;
  const max = Number(el.dataset.max || 100);
  const pt = (i, v) => [cx + Math.cos(-Math.PI / 2 + (i / n) * Math.PI * 2) * r * (v / max), cy + Math.sin(-Math.PI / 2 + (i / n) * Math.PI * 2) * r * (v / max)];
  ctx.strokeStyle = getDeckAlpha(line, 0.7);
  ctx.lineWidth = 1;
  for (let g = 1; g <= 4; g++) {
    ctx.beginPath();
    for (let i = 0; i <= n; i++) {
      const [x, y] = pt(i % n, max * (g / 4));
      if (i === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    }
    ctx.stroke();
  }
  ctx.font = '10px ui-monospace, Consolas, monospace';
  ctx.fillStyle = muted;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  labs.forEach((lab, i) => {
    const [x, y] = pt(i, max * 1.18);
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    const [ex, ey] = pt(i, max);
    ctx.lineTo(ex, ey);
    ctx.stroke();
    ctx.fillText(lab, x, y);
  });
  data.series.forEach((s, si) => {
    ctx.beginPath();
    s.forEach((v, i) => {
      const [x, y] = pt(i, v);
      if (i === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });
    ctx.closePath();
    ctx.fillStyle = getDeckAlpha(colors[si], 0.18);
    ctx.fill();
    ctx.strokeStyle = colors[si];
    ctx.lineWidth = 1.5;
    ctx.stroke();
  });
  ctx.textBaseline = 'alphabetic';
}

/* Candles, for the faces that speak the language of an exchange: open, high, low, close built from a series */
function setDeckCandles(box, el, data) {
  const { ctx, w, h } = getDeckCanvas(box);
  const s = data.series[0] || [];
  const rnd = getDeckRandom(77);
  const rows = s.map((v, i) => {
    const o = i ? s[i - 1] : v;
    const hi = Math.max(o, v) + rnd() * (Math.abs(v - o) + 2);
    const lo = Math.min(o, v) - rnd() * (Math.abs(v - o) + 2);
    return { o, c: v, hi, lo };
  });
  const good = getDeckColor(el, 'good');
  const bad = getDeckColor(el, 'bad');
  const muted = getDeckColor(el, 'muted');
  const line = getDeckColor(el, 'line');
  const pad = { l: 36, r: 6, t: 8, b: 20 };
  const lo = Math.min(...rows.map((r) => r.lo));
  const hi = Math.max(...rows.map((r) => r.hi));
  const n = rows.length;
  const gw = (w - pad.l - pad.r) / n;
  const py = (v) => pad.t + (1 - (v - lo) / (hi - lo || 1)) * (h - pad.t - pad.b);
  ctx.strokeStyle = getDeckAlpha(line, 0.9);
  ctx.setLineDash([3, 5]);
  for (let g = 0; g <= 4; g++) {
    const y = pad.t + (g / 4) * (h - pad.t - pad.b);
    ctx.beginPath();
    ctx.moveTo(pad.l, y);
    ctx.lineTo(w - pad.r, y);
    ctx.stroke();
  }
  ctx.setLineDash([]);
  ctx.fillStyle = muted;
  ctx.font = '10px ui-monospace, Consolas, monospace';
  ctx.textAlign = 'right';
  for (let g = 0; g <= 4; g++) ctx.fillText(String(Math.round(hi - (g / 4) * (hi - lo))), pad.l - 6, pad.t + (g / 4) * (h - pad.t - pad.b) + 3);
  ctx.textAlign = 'center';
  const every = Math.max(1, Math.ceil(n / 8));
  data.labels.forEach((lab, i) => { if (i % every === 0) ctx.fillText(lab, pad.l + gw * i + gw / 2, h - 6); });
  rows.forEach((r, i) => {
    const x = pad.l + gw * i + gw / 2;
    const up = r.c >= r.o;
    ctx.strokeStyle = up ? good : bad;
    ctx.fillStyle = up ? good : bad;
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(x, py(r.hi));
    ctx.lineTo(x, py(r.lo));
    ctx.stroke();
    const bw = Math.max(3, gw * 0.55);
    ctx.fillRect(x - bw / 2, py(Math.max(r.o, r.c)), bw, Math.max(1, Math.abs(py(r.o) - py(r.c))));
  });
  box.deckGeo = { px: (i) => pad.l + gw * i + gw / 2, n, pad, colors: [good] };
}

/* Heatmap on canvas: rows by columns, a value per cell */
function setDeckHeat(box, el) {
  const { ctx, w, h } = getDeckCanvas(box);
  const rows = Number(el.dataset.rows || 7);
  const cols = Number(el.dataset.cols || 24);
  const rnd = getDeckRandom(Number(el.dataset.seed || 5));
  const color = getDeckColor(el, el.dataset.color || 'accent');
  const line = getDeckColor(el, 'line');
  const gap = 3;
  const cw = (w - gap * (cols - 1)) / cols;
  const ch = (h - gap * (rows - 1)) / rows;
  for (let r = 0; r < rows; r++) {
    for (let c = 0; c < cols; c++) {
      const day = r >= 5 ? 0.55 : 1;
      const hour = c < 7 ? 0.08 : c < 17 ? 0.55 + 0.35 * Math.sin(((c - 7) / 10) * Math.PI) : 0.9 - (c - 17) * 0.09;
      const v = Math.max(0, Math.min(1, hour * day + (rnd() - 0.5) * 0.25));
      ctx.fillStyle = v < 0.08 ? getDeckAlpha(line, 0.6) : getDeckAlpha(color, 0.15 + v * 0.85);
      ctx.beginPath();
      ctx.roundRect(c * (cw + gap), r * (ch + gap), cw, ch, 2);
      ctx.fill();
    }
  }
}

/* One chart: reads its type, draws, and remembers itself for the period switch, the theme switch and the resize */
function setDeckChart(box) {
  const el = box;
  const type = el.dataset.chart;
  const data = type === 'gauge' || type === 'heat' ? null : getDeckData(el);
  if (type === 'line' || type === 'area' || type === 'spark') setDeckLine(box, el, data);
  else if (type === 'bars') setDeckBars(box, el, data);
  else if (type === 'hbars') setDeckHbars(box, el, data);
  else if (type === 'donut') setDeckDonut(box, el, data);
  else if (type === 'gauge') setDeckGauge(box, el);
  else if (type === 'radar') setDeckRadar(box, el, data);
  else if (type === 'candles') setDeckCandles(box, el, data);
  else if (type === 'heat') setDeckHeat(box, el);
}

/* The tooltip of a chart: the nearest column, every series at it */
function setDeckChartTip(box) {
  const el = box;
  const type = el.dataset.chart;
  if (type === 'gauge' || type === 'heat' || type === 'hbars' || type === 'spark' || el.dataset.tip === '0') return;
  const tip = document.createElement('div');
  tip.className = 'p-chart-tip';
  box.appendChild(tip);
  box.addEventListener('pointermove', (e) => {
    const geo = box.deckGeo;
    if (!geo) return;
    const r = box.getBoundingClientRect();
    const x = e.clientX - r.left;
    const y = e.clientY - r.top;
    const data = getDeckData(el);
    let at = -1;
    if (geo.donut) {
      const d = Math.hypot(x - geo.cx, y - geo.cy);
      if (d > geo.r - geo.thick / 2 - 4 && d < geo.r + geo.thick / 2 + 4) {
        let a = Math.atan2(y - geo.cy, x - geo.cx) + Math.PI / 2;
        if (a < 0) a += Math.PI * 2;
        let acc = 0;
        geo.vals.forEach((v, i) => {
          const b = acc + (v / geo.sum) * Math.PI * 2;
          if (a >= acc && a < b) at = i;
          acc = b;
        });
      }
    } else {
      let best = Infinity;
      for (let i = 0; i < geo.n; i++) {
        const d = Math.abs(geo.px(i) - x);
        if (d < best) { best = d; at = i; }
      }
      if (best > 40) at = -1;
    }
    if (at !== box.deckHover) {
      box.deckHover = at < 0 ? undefined : at;
      setDeckChart(box);
    }
    if (at < 0) { tip.classList.remove('on'); return; }
    const names = (el.dataset.names || (data.names || []).join(',')).split(',');
    const rows = geo.donut
      ? ['<span><i style="background:' + geo.colors[at] + '"></i>' + Math.round((geo.vals[at] / geo.sum) * 100) + '% · ' + geo.vals[at] + '</span>']
      : data.series.map((s, si) => '<span><i style="background:' + geo.colors[si] + '"></i>' + (names[si] || '').trim() + ' ' + (s[at] === undefined ? '' : s[at]) + (el.dataset.unit || '') + '</span>');
    tip.innerHTML = '<b>' + (data.labels[at] || '') + '</b>' + rows.join('');
    tip.style.left = (geo.donut ? geo.cx : geo.px(at)) + 'px';
    tip.style.top = (geo.donut ? geo.cy : Math.max(30, y)) + 'px';
    tip.classList.add('on');
  });
  box.addEventListener('pointerleave', () => {
    tip.classList.remove('on');
    box.deckHover = undefined;
    setDeckChart(box);
  });
}

/* Live charts breathe: the targets shift every 900 ms and the drawn values ease toward them, only while in view */
function setDeckLive(box) {
  const el = box;
  const data = getDeckData(el);
  const src = data.series[0];
  if (!src || !src.length) return;
  const lo = Math.min(...src);
  const hi = Math.max(...src);
  const rnd = getDeckRandom(Date.now() & 0xffff);
  let target = src.slice();
  const cur = src.slice();
  let last = performance.now();
  let seen = false;
  const io = new IntersectionObserver((rows) => { seen = rows.some((r) => r.isIntersecting); });
  io.observe(box);
  function frame(now) {
    if (seen && document.documentElement.dataset.demoMotion !== 'off') {
      if (now - last > 900) {
        target = target.slice(1).concat(Math.round(lo + rnd() * (hi - lo)));
        last = now;
      }
      for (let i = 0; i < cur.length; i++) cur[i] += (target[i] - cur[i]) * 0.1;
      el.dataset.values = cur.map((v) => v.toFixed(1)).join(',');
      delete el.dataset.key;
      setDeckChart(box);
    }
    requestAnimationFrame(frame);
  }
  el.dataset.labels = data.labels.join(',');
  requestAnimationFrame(frame);
}

function setDeckCharts() {
  const boxes = [...document.querySelectorAll('[data-chart]')];
  boxes.forEach((box) => {
    box.classList.add('p-chart');
    setDeckChart(box);
    setDeckChartTip(box);
    if (box.dataset.live !== undefined) setDeckLive(box);
  });
  deckState.charts = boxes;
  const ro = new ResizeObserver(() => boxes.forEach(setDeckChart));
  boxes.forEach((b) => ro.observe(b));
  new MutationObserver(() => setTimeout(() => boxes.forEach(setDeckChart), 30)).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
}

/* The period switch: buttons naming a key and a period; the charts on that key redraw, and the sums beside them
   (`data-sum="traffic:visits"`, `data-avg`, `data-max`) are written again */
function setDeckPeriods() {
  function apply(key) {
    deckState.charts.filter((c) => c.dataset.key === key).forEach(setDeckChart);
    const root = DECK_DATA[key];
    const src = root && (root.labels ? root : root[deckState.period[key]]);
    if (!src) return;
    const words = { '7d': '7 дней', '30d': '30 дней', '90d': '90 дней' };
    if (!root.labels) document.querySelectorAll('[data-period-label="' + key + '"], [data-period-label=""]').forEach((n) => { n.textContent = words[deckState.period[key]] || deckState.period[key]; });
    document.querySelectorAll('[data-sum], [data-avg], [data-peak]').forEach((n) => {
      const spec = n.dataset.sum || n.dataset.avg || n.dataset.peak;
      const [k, f] = spec.split(':');
      if (k !== key || !src[f]) return;
      const arr = src[f];
      let v = arr.reduce((a, b) => a + b, 0);
      if (n.dataset.avg !== undefined) v = v / arr.length;
      if (n.dataset.peak !== undefined) v = Math.max(...arr);
      n.textContent = Math.round(v).toLocaleString('ru-RU');
    });
  }
  document.querySelectorAll('[data-period-for]').forEach((btn) => {
    const key = btn.dataset.periodFor;
    if (!deckState.period[key]) deckState.period[key] = btn.closest('[data-periods]')?.dataset.periods || btn.dataset.period;
    btn.setAttribute('aria-pressed', String(deckState.period[key] === btn.dataset.period));
  });
  const keys = new Set(Object.keys(deckState.period));
  document.querySelectorAll('[data-sum], [data-avg], [data-peak]').forEach((n) => keys.add((n.dataset.sum || n.dataset.avg || n.dataset.peak).split(':')[0]));
  keys.forEach(apply);
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-period-for]');
    if (!btn) return;
    e.preventDefault();
    const key = btn.dataset.periodFor;
    deckState.period[key] = btn.dataset.period;
    document.querySelectorAll('[data-period-for="' + key + '"]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.period === deckState.period[key])));
    apply(key);
  });
}

/* Tables written from the registry. `data-cols` names the columns as `field:Title:type`, the types being text,
   mono, num, chip, state, bar, ms; the header sorts, and a search field aimed at the table filters its rows */
function setDeckTables() {
  document.querySelectorAll('[data-table]').forEach((box) => {
    const key = box.dataset.table;
    const rows = (DECK_DATA[key] || []).slice();
    const cols = (box.dataset.cols || 'name:Модуль:text,cat:Раздел:chip,ver:Версия:mono,hits:Просмотры:num,time:Ответ:ms,status:Статус:state').split(',').map((c) => {
      const [field, title, type] = c.split(':');
      return { field, title, type: type || 'text' };
    });
    let sort = (box.dataset.sort || '').split(':');
    let q = '';
    const many = Number(box.dataset.rows) || rows.length;
    function cell(r, c) {
      const v = r[c.field];
      if (c.type === 'num') return '<td class="p-num p-mono">' + (typeof v === 'number' ? v.toLocaleString('ru-RU') : v) + '</td>';
      if (c.type === 'ms') return '<td class="p-num p-mono">' + (v ? v.toFixed(1) + ' ms' : '—') + '</td>';
      if (c.type === 'mono') return '<td class="p-mono p-muted">' + v + '</td>';
      if (c.type === 'chip') return '<td><span class="p-chip">' + v + '</span></td>';
      if (c.type === 'state') return '<td><span class="p-chip ' + (v === 'on' ? 'p-chip-good' : 'p-chip-warn') + '"><i class="p-dot ' + (v === 'on' ? '' : 'p-dot-warn') + '"></i>' + (v === 'on' ? 'включён' : 'выключен') + '</span></td>';
      if (c.type === 'bar') return '<td><div class="p-bar"><i style="--w:' + v + '%"></i></div></td>';
      if (c.type === 'icon') return '<td><span class="p-icon"><i class="bi ' + r.icon + '"></i></span></td>';
      if (c.type === 'name') return '<td><b>' + r.name + '</b><br><small class="p-muted p-mono">' + r.cat.toLowerCase() + ' · ' + r.ver + '</small></td>';
      return '<td><b>' + v + '</b></td>';
    }
    function draw() {
      let list = rows.filter((r) => !q || Object.values(r).join(' ').toLowerCase().includes(q));
      if (sort[0]) list.sort((a, b) => (typeof a[sort[0]] === 'number' ? a[sort[0]] - b[sort[0]] : String(a[sort[0]]).localeCompare(String(b[sort[0]]))) * (sort[1] === 'desc' ? -1 : 1));
      list = list.slice(0, many);
      box.innerHTML = '<table class="p-table ' + (box.dataset.tableClass || '') + '"><thead><tr>'
        + cols.map((c) => '<th data-sort="' + c.field + '"' + (sort[0] === c.field ? ' aria-sort="' + (sort[1] === 'desc' ? 'descending' : 'ascending') + '"' : '') + (c.type === 'num' || c.type === 'ms' ? ' class="p-num"' : '') + '>' + c.title + '</th>').join('')
        + '</tr></thead><tbody>'
        + (list.length ? list.map((r) => '<tr>' + cols.map((c) => cell(r, c)).join('') + '</tr>').join('') : '<tr><td colspan="' + cols.length + '" class="p-table-empty">Ничего не найдено</td></tr>')
        + '</tbody></table>';
      document.querySelectorAll('[data-table-count="' + key + '"]').forEach((n) => { n.textContent = String(list.length); });
    }
    box.addEventListener('click', (e) => {
      const th = e.target.closest('th[data-sort]');
      if (!th) return;
      sort = [th.dataset.sort, sort[0] === th.dataset.sort && sort[1] !== 'desc' ? 'desc' : 'asc'];
      draw();
    });
    document.querySelectorAll('[data-table-search="' + key + '"]').forEach((f) => f.addEventListener('input', () => { q = f.value.trim().toLowerCase(); draw(); }));
    document.querySelectorAll('[data-table-filter="' + key + '"]').forEach((b) => b.addEventListener('click', (e) => {
      e.preventDefault();
      q = (b.dataset.value || '').toLowerCase();
      document.querySelectorAll('[data-table-filter="' + key + '"]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
      draw();
    }));
    draw();
  });
}

/* Streams: a pool of rows, one more every few seconds, the oldest dropped past the cap. The guard stream answers
   the guard switch: with the guard off, an attack is not blocked but missed. */
function setDeckLogs() {
  document.querySelectorAll('[data-log]').forEach((box) => {
    const pool = DECK_DATA.pools[box.dataset.log] || DECK_DATA.pools.runtime;
    const max = Number(box.dataset.logMax) || 6;
    const every = Number(box.dataset.logEvery) || 2300;
    let n = Number(box.dataset.logSkip) || 0;
    box.classList.add('p-log');
    function push() {
      const [op, res, tone] = pool[n++ % pool.length];
      const d = new Date();
      const row = document.createElement('div');
      row.className = 'p-logrow';
      let out = res;
      let cls = tone;
      if (box.dataset.log === 'guard' && !deckState.guard && tone === 'bad') { out = 'missed'; cls = 'warn'; }
      row.innerHTML = '<time>' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0') + ':' + String(d.getSeconds()).padStart(2, '0') + '</time><span>' + op + '</span><em class="' + cls + '">' + out + '</em>';
      if (box.dataset.logAppend !== undefined) box.appendChild(row);
      else box.prepend(row);
      while (box.children.length > max) (box.dataset.logAppend !== undefined ? box.firstElementChild : box.lastElementChild).remove();
    }
    for (let i = 0; i < Math.min(max, 4); i++) push();
    setInterval(() => { if (document.documentElement.dataset.demoMotion !== 'off') push(); }, every);
  });
}

/* Figures that flicker: the online count, the request rate */
function setDeckTicks() {
  const rnd = getDeckRandom(9);
  document.querySelectorAll('[data-tick]').forEach((n) => {
    const [lo, hi] = (n.dataset.tick || '72,91').split(',').map(Number);
    setInterval(() => { if (document.documentElement.dataset.demoMotion !== 'off') n.textContent = String(Math.round(lo + rnd() * (hi - lo))); }, Number(n.dataset.tickEvery) || 2700);
  });
}

/* CSS bars and heat cells written from data, so the markup does not carry seventy numbers */
function setDeckBarsCss() {
  document.querySelectorAll('[data-bars]').forEach((box) => {
    const src = box.dataset.bars === 'hourly' ? DECK_DATA.hourly : getDeckSeries(Number(box.dataset.seed || 3), Number(box.dataset.count || 24), 8, 100, 1.3);
    const max = Math.max(...src);
    box.classList.add('p-bars');
    box.innerHTML = src.map((v, i) => '<i style="--v:' + Math.max(2, Math.round((v / max) * 100)) + '%" title="' + (box.dataset.bars === 'hourly' ? DECK_HOURS[i] + ':00' : i + 1) + '"></i>').join('');
    if (box.dataset.barsLive !== undefined) {
      const rnd = getDeckRandom(17);
      setInterval(() => { if (document.documentElement.dataset.demoMotion !== 'off') [...box.children].forEach((i) => i.style.setProperty('--v', Math.round(15 + rnd() * 80) + '%')); }, 850);
    }
  });
  document.querySelectorAll('[data-heat-css]').forEach((box) => {
    const [rows, cols] = (box.dataset.heatCss || '7x24').split('x').map(Number);
    const rnd = getDeckRandom(Number(box.dataset.seed || 5));
    box.classList.add('p-heat');
    box.style.setProperty('--cols', String(cols));
    let out = '';
    for (let r = 0; r < rows; r++) {
      for (let c = 0; c < cols; c++) {
        const day = r >= 5 ? 0.55 : 1;
        const hour = c < 7 ? 0.08 : c < 17 ? 0.55 + 0.35 * Math.sin(((c - 7) / 10) * Math.PI) : 0.9 - (c - 17) * 0.09;
        out += '<i style="--v:' + Math.max(0, Math.min(1, hour * day + (rnd() - 0.5) * 0.25)).toFixed(2) + '"></i>';
      }
    }
    box.innerHTML = out;
  });
}

/* A month grid with the marked days of `data-cal-marks` and today at `data-cal-now` */
function setDeckCal() {
  document.querySelectorAll('[data-cal]').forEach((box) => {
    const [y, m] = box.dataset.cal.split('-').map(Number);
    const first = new Date(y, m - 1, 1);
    const days = new Date(y, m, 0).getDate();
    const start = (first.getDay() + 6) % 7;
    const marks = (box.dataset.calMarks || '').split(',').map(Number);
    const now = Number(box.dataset.calNow || 0);
    box.classList.add('p-cal');
    let out = DECK_DAYS.map((d) => '<span>' + d + '</span>').join('');
    for (let i = 0; i < start; i++) out += '<i class="dim"></i>';
    for (let d = 1; d <= days; d++) out += '<i class="' + (d === now ? 'now' : marks.includes(d) ? 'mark' : '') + '">' + d + '</i>';
    box.innerHTML = out;
  });
}

/* Lists written from the data where a variant asked for them: commits, traffic classes, module tiles. The
   markup is the deck's; the variant paints it. */
function setDeckLists() {
  document.querySelectorAll('[data-list="commits"]').forEach((box) => {
    box.innerHTML = DECK_DATA.commits.slice(0, Number(box.dataset.rows) || 5).map((c) => '<div class="p-commit"><span class="sha">' + c.sha + '</span><div><b>' + c.title + '</b><span class="tags">' + c.tags.map((t) => '<i>' + t + '</i>').join('') + '</span></div><time>' + c.date + '<br>' + c.time + '</time></div>').join('');
  });
  document.querySelectorAll('[data-list="classes"]').forEach((box) => {
    box.innerHTML = DECK_DATA.classes.map((c) => '<div class="p-kv"><span><i class="bi ' + c.icon + '" style="color:var(--p-' + c.color + ');margin-right:6px"></i>' + c.label + '</span><span class="p-mono">' + c.share + '%</span><b class="p-mono">' + c.rule + '</b></div>').join('');
  });
  document.querySelectorAll('[data-list="modules"]').forEach((box) => {
    const many = Number(box.dataset.rows) || 8;
    box.innerHTML = DECK_DATA.modules.slice(0, many).map((m) => '<button type="button" class="p-mod" data-mod="' + m.name + '" aria-pressed="' + (m.status === 'on') + '"><span class="p-icon"><i class="bi ' + m.icon + '"></i></span><b>' + m.name + '</b><small>' + m.cat.toLowerCase() + ' · ' + m.hits.toLocaleString('ru-RU') + '</small></button>').join('');
  });
}

/* Module tiles are switches; the count beside them says how many of the registry are on */
function setDeckMods() {
  function count() {
    const all = [...document.querySelectorAll('.p-mod[data-mod]')];
    const on = all.length ? all.filter((m) => m.getAttribute('aria-pressed') !== 'false').length : DECK_DATA.modules.filter((m) => m.status === 'on').length;
    document.querySelectorAll('[data-mods-count]').forEach((n) => { n.textContent = 'включено ' + on + ' из ' + DECK_DATA.facts.addons; });
  }
  document.addEventListener('click', (e) => {
    const m = e.target.closest('.p-mod[data-mod]');
    if (!m) return;
    m.setAttribute('aria-pressed', String(m.getAttribute('aria-pressed') === 'false'));
    count();
  });
  count();
}

/* Switches: guard and cache. The state lands on the page root as data-guard / data-cache, so CSS paints it, and
   the label beside the switch takes its on/off wording from its own attributes */
function setDeckToggles() {
  const page = document.querySelector('.p-page');
  function paint(name) {
    const on = deckState[name];
    if (page) page.dataset[name] = on ? 'on' : 'off';
    document.querySelectorAll('[data-toggle="' + name + '"]').forEach((b) => b.setAttribute('aria-pressed', String(on)));
    document.querySelectorAll('[data-toggle-label="' + name + '"]').forEach((l) => { l.textContent = on ? l.dataset.on : l.dataset.off; });
  }
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-toggle]');
    if (!b) return;
    e.preventDefault();
    deckState[b.dataset.toggle] = !deckState[b.dataset.toggle];
    paint(b.dataset.toggle);
  });
  paint('guard');
  paint('cache');
}

/* The page cache scene: nodes lit one after another along the route of the scenario, the badge naming the
   decision, the case list marking the branch taken. With the cache off every request renders live. */
function setDeckCache() {
  document.querySelectorAll('[data-cache-scene]').forEach((scene) => {
    const nodes = {};
    scene.querySelectorAll('[data-node]').forEach((n) => { nodes[n.dataset.node] = n; });
    let at = 0;
    let step = null;
    function run() {
      clearInterval(step);
      const sc = deckState.cache ? DECK_DATA.cache[at++ % DECK_DATA.cache.length] : { mode: 'off', badge: 'CACHE OFF · LIVE', route: 'GET /index.php?name=news&cat=1 · live', seq: ['request', 'guard', 'kernel', 'module', 'template', 'response'], state: 'full live render every request' };
      scene.dataset.mode = sc.mode;
      scene.querySelectorAll('[data-cache-badge]').forEach((n) => { n.textContent = sc.badge; n.dataset.mode = sc.mode; });
      scene.querySelectorAll('[data-cache-route]').forEach((n) => { n.textContent = sc.route; });
      scene.querySelectorAll('[data-cache-state]').forEach((n) => { n.textContent = sc.state; });
      scene.querySelectorAll('[data-cache-case]').forEach((n) => n.setAttribute('aria-current', String(n.dataset.cacheCase === (sc.mode === 'off' ? 'bypass' : sc.mode))));
      Object.entries(nodes).forEach(([k, n]) => { n.dataset.skip = String(!sc.seq.includes(k)); n.setAttribute('aria-current', String(k === sc.seq[0])); });
      let i = 0;
      step = setInterval(() => {
        i = (i + 1) % sc.seq.length;
        Object.entries(nodes).forEach(([k, n]) => n.setAttribute('aria-current', String(k === sc.seq[i])));
      }, 700);
    }
    run();
    setInterval(run, 5600);
    document.addEventListener('click', (e) => { if (e.target.closest('[data-toggle="cache"]')) { at = 0; run(); } });
  });
}

/* The PDO scene: a case at a time, the packet walking module → database → PDO → SQL → statement */
function setDeckPdo() {
  document.querySelectorAll('[data-pdo-scene]').forEach((scene) => {
    const nodes = ['module', 'database', 'pdo', 'sql', 'statement'];
    let at = 0;
    let step = null;
    let total = 0.011;
    let count = 12;
    function write(sel, text) { scene.querySelectorAll(sel).forEach((n) => { n.textContent = text; }); }
    function run() {
      clearInterval(step);
      const c = DECK_DATA.pdo[at++ % DECK_DATA.pdo.length];
      scene.dataset.write = String(c.write);
      write('[data-pdo-verb]', c.verb);
      write('[data-pdo-query]', c.query);
      write('[data-pdo-params]', c.params);
      write('[data-pdo-result]', c.result);
      write('[data-pdo-elapsed]', c.elapsed.toFixed(5) + ' s');
      write('[data-pdo-mode]', c.prepared ? 'PREPARED' : 'DIRECT QUERY');
      write('[data-pdo-status]', c.write ? 'WRITE · TRANSACTIONAL' : 'READ · READY');
      let i = 0;
      scene.querySelectorAll('[data-pdo]').forEach((n) => n.setAttribute('aria-current', String(n.dataset.pdo === nodes[0])));
      scene.style.setProperty('--pdo-at', '0');
      step = setInterval(() => {
        i++;
        if (i >= nodes.length) {
          clearInterval(step);
          count++;
          total += c.elapsed;
          write('[data-pdo-count]', String(count));
          write('[data-pdo-total]', total.toFixed(4) + ' s');
          scene.querySelectorAll('[data-pdo]').forEach((n) => n.setAttribute('aria-current', 'false'));
          return;
        }
        scene.querySelectorAll('[data-pdo]').forEach((n) => n.setAttribute('aria-current', String(n.dataset.pdo === nodes[i])));
        scene.style.setProperty('--pdo-at', String(i));
      }, 590);
    }
    run();
    setInterval(run, 4300);
  });
}

/* The guard scene: chips of traffic fly from the left toward the gate; a clean request passes to the site, an
   attack is thrown down into quarantine - unless the guard is off, when it walks straight in */
function setDeckGuard() {
  document.querySelectorAll('[data-guard-scene]').forEach((scene) => {
    const kinds = [
      { cls: 'human', icon: 'bi-person-fill', text: 'GET /index.php?name=news', bad: false, tone: 'accent' },
      { cls: 'search', icon: 'bi-search', text: 'crawler · /sitemap.xml', bad: false, tone: 'info' },
      { cls: 'attack', icon: 'bi-database-exclamation', text: 'id=1 UNION SELECT', bad: true, tone: 'bad' },
      { cls: 'ai', icon: 'bi-stars', text: 'AI agent · /content', bad: false, tone: 'good' },
      { cls: 'bot', icon: 'bi-robot', text: 'service bot · /rss', bad: false, tone: 'warn' },
      { cls: 'human', icon: 'bi-person-fill', text: 'GET /files', bad: false, tone: 'accent' },
      { cls: 'attack', icon: 'bi-code-slash', text: 'q=<script>…', bad: true, tone: 'bad' },
      { cls: 'attack', icon: 'bi-folder-x', text: '../../etc/passwd', bad: true, tone: 'bad' },
    ];
    let n = 0;
    let allow = 0;
    let deny = 0;
    let miss = 0;
    const lanes = Number(scene.dataset.lanes) || 4;
    const top = Number(scene.dataset.laneTop) || 14;
    const span = Number(scene.dataset.laneSpan) || 62;
    function spawn() {
      if (document.documentElement.dataset.demoMotion === 'off') return;
      const k = kinds[n++ % kinds.length];
      const el = document.createElement('div');
      el.className = 'p-traveler ' + k.cls;
      el.style.setProperty('--tc', 'var(--p-' + k.tone + ')');
      el.style.top = (top + ((n % lanes) / lanes) * span) + '%';
      el.innerHTML = '<i class="bi ' + k.icon + '"></i><span>' + k.text + '</span>';
      scene.appendChild(el);
      el.getBoundingClientRect();
      el.style.left = '46%';
      setTimeout(() => {
        const gate = scene.querySelector('[data-gate]');
        if (k.bad && deckState.guard) {
          deny++;
          el.classList.add('caught');
          gate?.classList.add('hit');
          setTimeout(() => gate?.classList.remove('hit'), 450);
          el.style.top = '88%';
          el.style.left = '40%';
          el.style.opacity = '0';
          scene.querySelectorAll('[data-guard-deny]').forEach((x) => { x.textContent = String(deny); });
        } else {
          if (k.bad) { miss++; scene.classList.add('breach'); setTimeout(() => scene.classList.remove('breach'), 600); } else allow++;
          el.style.left = '92%';
          el.style.opacity = '0';
          scene.querySelectorAll('[data-guard-allow]').forEach((x) => { x.textContent = String(allow); });
          scene.querySelectorAll('[data-guard-miss]').forEach((x) => { x.textContent = String(miss); });
        }
        setTimeout(() => el.remove(), 900);
      }, 1500);
    }
    for (let i = 0; i < 2; i++) setTimeout(spawn, i * 700);
    setInterval(spawn, Number(scene.dataset.every) || 1500);
  });
}

/* A walker: children marked `data-step` lit one after another, for pipelines and stepped diagrams */
function setDeckWalkers() {
  document.querySelectorAll('[data-walk]').forEach((box) => {
    const steps = [...box.querySelectorAll('[data-step]')];
    if (!steps.length) return;
    let at = 0;
    setInterval(() => {
      if (document.documentElement.dataset.demoMotion === 'off') return;
      at = (at + 1) % steps.length;
      steps.forEach((s, i) => s.setAttribute('aria-current', String(i === at)));
    }, Number(box.dataset.walk) || 900);
    steps.forEach((s, i) => s.setAttribute('aria-current', String(i === 0)));
  });
}

/* Scroll spy for every nav that asks: the link of the section in view is current, the ones above are done, the
   road progress is written as a percentage, the step counter as `03 / 11` */
function setDeckSpy() {
  const navs = [...document.querySelectorAll('[data-spy]')];
  if (!navs.length) return;
  function tick() {
    const mark = scrollY + innerHeight * 0.35;
    navs.forEach((nav) => {
      const links = [...nav.querySelectorAll('a[href^="#"]')];
      const secs = links.map((a) => document.getElementById(a.getAttribute('href').slice(1)));
      let at = 0;
      secs.forEach((s, i) => { if (s && s.offsetTop <= mark) at = i; });
      links.forEach((a, i) => {
        a.setAttribute('aria-current', String(i === at));
        a.dataset.done = String(i < at);
      });
      const first = secs[0];
      const last = secs[secs.length - 1];
      if (first && last) nav.style.setProperty('--road', Math.max(0, Math.min(100, ((mark - first.offsetTop) / (last.offsetTop + last.offsetHeight - first.offsetTop)) * 100)).toFixed(1) + '%');
      document.querySelectorAll('[data-spy-step]').forEach((n) => { n.textContent = String(at + 1).padStart(2, '0') + ' / ' + String(links.length).padStart(2, '0'); });
    });
    const d = document.documentElement;
    const max = d.scrollHeight - innerHeight;
    d.style.setProperty('--p-scroll', (max ? (scrollY / max) * 100 : 0).toFixed(2));
  }
  addEventListener('scroll', tick, { passive: true });
  addEventListener('resize', tick, { passive: true });
  tick();
}

/* Reveal on scroll, from an observer: sections come up as they enter */
function setDeckReveal() {
  const nodes = [...document.querySelectorAll('.p-reveal')];
  if (!nodes.length) return;
  const io = new IntersectionObserver((rows) => rows.forEach((r) => { if (r.isIntersecting) { r.target.classList.add('in'); io.unobserve(r.target); } }), { threshold: 0.08 });
  nodes.forEach((n) => io.observe(n));
}

/* The chrome of the site around a variant: fetched from the real main page so the header and footer are the
   ones the visitor will see, and skipped in the gallery frame where the variant is shown bare */
function setDeckShell() {
  const params = new URLSearchParams(location.search);
  const head = document.querySelector('[data-site-head]');
  const foot = document.querySelector('[data-site-foot]');
  const page = document.querySelector('.p-page');
  if (params.has('bare') || !head || !foot) return;
  const url = new URL('../index.php?name=main', location.href);
  fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(async (reply) => {
    if (!reply.ok) throw new Error('HTTP ' + reply.status);
    const doc = new DOMParser().parseFromString(await reply.text(), 'text/html');
    const top = ['topbar', 'header', 'hmenu'].map((id) => doc.getElementById(id));
    const end = ['demo-line', 'footbox'].map((id) => doc.getElementById(id));
    if ([...top, ...end].some((n) => !n)) throw new Error('Missing project header or footer');
    [...top, ...end].forEach((node) => {
      node.querySelectorAll('[href], [src], [action]').forEach((el) => {
        ['href', 'src', 'action'].forEach((attr) => {
          const v = el.getAttribute(attr);
          if (v && !v.startsWith('#')) el.setAttribute(attr, new URL(v, url).href);
        });
      });
      node.querySelectorAll('script').forEach((el) => el.remove());
    });
    head.replaceChildren(...top);
    foot.replaceChildren(...end);
    head.querySelectorAll('.sl-mode button[name="mode"]').forEach((btn) => {
      btn.type = 'button';
      btn.dataset.demoSet = 'mode';
      btn.dataset.demoValue = btn.value;
    });
    const mark = () => head.querySelectorAll('.sl-mode-cell').forEach((btn) => btn.classList.toggle('sl-is-active', btn.value === document.documentElement.dataset.theme));
    new MutationObserver(mark).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    mark();
    document.documentElement.dataset.siteShell = 'ready';
  }).catch((err) => {
    head.className = 'sl-pres-shell-status';
    document.documentElement.dataset.siteShell = 'error';
    console.error('Project shell:', err.message);
  });
  if (page && typeof getDemoPlace === 'function' && typeof getDemoNote === 'function') {
    const file = location.pathname.split('/').pop() || '';
    page.insertAdjacentHTML('beforeend', getDemoNote(getDemoPlace(file)));
  }
}

/* The pointer light of the leader: two custom properties the page may read for a glow that follows the hand */
function setDeckPointer() {
  const page = document.querySelector('.p-page');
  if (!page || page.dataset.pointer === undefined) return;
  addEventListener('pointermove', (e) => {
    page.style.setProperty('--mx', e.clientX + 'px');
    page.style.setProperty('--my', e.clientY + 'px');
  }, { passive: true });
}

function setDeckBoot() {
  setDeckLists();
  setDeckCharts();
  setDeckPeriods();
  setDeckTables();
  setDeckLogs();
  setDeckTicks();
  setDeckBarsCss();
  setDeckCal();
  setDeckMods();
  setDeckToggles();
  setDeckCache();
  setDeckPdo();
  setDeckGuard();
  setDeckWalkers();
  setDeckSpy();
  setDeckReveal();
  setDeckPointer();
  setDeckShell();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setDeckBoot);
else setDeckBoot();
