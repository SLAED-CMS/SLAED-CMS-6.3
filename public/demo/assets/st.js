/* Statistic series: admin.php?name=statistic redrawn. Every face shows the same corpus — one month of the counter as
   storage/counter/statistic.log and days.log write it, field by field: the date, unique hosts (1), hits (2), the hits
   of all time (3), crawlers (4), referers (5), the home page (6), users (7), browsers (8), systems (9), devices (10),
   countries (11), sources (12), hits by hour (13), new and returning (14), depth (15), duration (16), and the load of
   the pages by hour: milliseconds (17), queries (18) and kilobytes (19). The archive months are the file names of
   storage/counter/statistic on the stand, with their three gaps; the figures are an invented corpus of that shape.

   A face is a <template data-st-face> of markup and its own <style>; this file draws the panel around it (the module
   named by data-st-module, -title, -icon and -tabs of the template, the statistic by default) and fills every host by
   its attribute:
   - data-st-t="<key>" — a fact of the scope: u h home ref bot aud usr all gen sql mem, avg-u, avg-h, per (pages a
     host), avg-work, avg-end (a weekday and a weekend day), days, period, period-short, peak-day, peak-u, peak-hour, slow-hour, fast-hour, date, weekday, run
   - data-st-delta="<metric>" — the change against the same days of the month before (a day: the day before;
     data-st-vs="year" — the same days a year before),
     with data-dir up, down or flat
   - data-st-spark="<metric>" — a sparkline of the days
   - data-st-chart="<metric>[,<metric>]" — the chart: data-st-x days|hours, data-st-kind bars|area|line, the second
     series is a line on its own scale; "@" takes the picked metric; data-st-pick makes a day column pick the day,
     data-st-cmp-on draws the month before dashed while compare is on
   - data-st-bars="<dimension>" — a bar list (data-st-top, a <template> of the row with data-st-f name|num|pct|flag)
   - data-st-stack="<dimension>" — one bar split into its parts, with a legend
   - data-st-arc — an arc of another series, its value in el._stShare, filled the same way as a share
   - data-st-share="human|mobile|ret|search" — a share: data-st-v the number, data-st-pct with its sign, .sl-knob-val and .sl-statx-rval arcs
   - data-st-table — the table of days (data-st-table="bars groups pick" adds cell bars, group heads, row picking)
   - data-st-cal — the month as a calendar; data-st-week — weekdays by hours, every cell with its step data-lv 0–5;
     data-st-clock — the day as a dial
   - data-st-archive — every month of the archive as one strip; data-st-months — the select of the panel
   - data-st-rhythm — the rhythm chart of the presentation page, drawn by its own plugin: 24 h the hits, the generation
     and the queries of a page by hour of the day of the scope, 7 d and 30 d the hits, hosts and crawlers of the days up to it
   - data-st-say="visits|audience|sources|tech|load|hours|day" — the sentence a report opens with
   - controls: data-st-go prev|next, data-st-metric, data-st-range 7|14|all, data-st-cmp, data-st-day, data-st-day-clear
   - data-st-scope="day" — the hosts inside read the picked day (the last whole day until one is picked; with
     data-st-now the running day while the month runs), data-st-now-set="on|off" switches it
   - data-sl-live-box with the live chip of the theme — the box the counter ticks in; slaed.js pauses it and counts the
     seconds since the last tick, the tick stamps it as a swap of the box would
   - data-st-if="day|noday|now|last|load|noload|run|arc|cmp" — a part shown only in that state
   - the hints of the parts are the floats of the system (.sl-float with its .sl-float-panel, opened and placed by slaed.js):
     a share of a dimension, an hour of the week, and data-st-share-rows — the panel of a gauge, filled as the cockpit of
     the home page fills its own; a column of a chart and a point of the rhythm chart are floats too; a part drawn
     again is handed to slaed.js as a swap
   The motion layer, gone with prefers-reduced-motion and with the motion switch of the stand: every child of the root
   is a card (data-st-rise passes the turn to its own children) that rises in its turn when it enters the view, and only
   then its columns grow, its lines are drawn, its figures run up and its arcs fill; a redraw plays them again, a step of
   the month slides the cards the way it went. data-st-tilt — a tile that leans to the hand; data-st-clock-say — the
   centre of a dial, its own part shown while the hand is on a spoke; the running day of the current month ticks every few seconds */

const ST_NOW = { key: '2026-10', day: 27, hour: 11, start: 11 * 3600 + 40 * 60, born: Date.now() };
const ST_ARCHIVE = ('2008-10 2008-12 2009-01 2009-02 2009-03 2009-04 2009-05 2009-06 2009-07 2009-08 2009-09 2009-10 2009-11 2009-12 ' +
  '2010-01 2010-02 2010-03 2010-04 2010-05 2010-06 2010-07 2010-08 2010-09 2010-10 2010-11 2010-12 2011-01 2011-02 2011-03 2011-04 ' +
  '2011-05 2011-06 2011-07 2011-08 2011-09 2011-10 2011-11 2011-12 2012-01 2012-02 2012-03 2012-04 2012-05 2012-06 2012-07 2012-08 ' +
  '2012-09 2012-10 2012-11 2012-12 2013-01 2013-02 2013-03 2013-04 2013-05 2013-06 2013-07 2013-08 2013-09 2013-10 2013-11 2013-12 ' +
  '2014-01 2014-02 2014-03 2014-04 2014-05 2014-06 2014-07 2014-08 2014-09 2014-10 2014-11 2014-12 2015-01 2015-02 2015-03 2015-04 ' +
  '2015-05 2015-06 2015-07 2015-08 2015-09 2015-10 2015-11 2015-12 2016-01 2016-02 2016-03 2016-04 2016-05 2016-06 2016-07 2016-08 ' +
  '2016-09 2016-10 2016-11 2016-12 2017-01 2017-02 2017-03 2017-04 2017-05 2017-06 2017-07 2017-08 2017-09 2017-10 2017-11 2017-12 ' +
  '2018-01 2018-02 2018-03 2018-04 2018-05 2018-06 2018-07 2018-08 2018-09 2018-10 2018-11 2018-12 2019-01 2019-02 2019-03 2019-04 ' +
  '2019-05 2019-06 2019-07 2019-08 2019-09 2019-10 2019-11 2019-12 2020-01 2020-02 2020-03 2020-04 2020-05 2020-06 2020-07 2020-08 ' +
  '2020-09 2020-10 2020-11 2020-12 2021-01 2021-02 2021-03 2021-04 2021-05 2021-06 2021-07 2021-08 2021-09 2021-10 2021-11 2021-12 ' +
  '2022-01 2022-02 2022-03 2022-04 2022-05 2022-06 2022-07 2022-08 2022-09 2022-10 2022-11 2022-12 2023-01 2023-02 2023-03 2023-04 ' +
  '2023-05 2023-06 2023-07 2023-08 2023-09 2023-10 2023-11 2023-12 2024-01 2024-02 2024-03 2024-04 2024-05 2024-06 2024-07 2024-08 ' +
  '2024-09 2024-10 2024-11 2024-12 2025-01 2025-02 2025-03 2025-04 2025-05 2025-06 2025-07 2025-08 2025-09 2025-10 2025-11 2026-02 ' +
  '2026-03 2026-04 2026-05 2026-06 2026-07 2026-08 2026-09').split(' ');
const ST_ALL = 161502713;

function getStClockNow() {
  const sec = Math.min(86399, ST_NOW.start + Math.floor((Date.now() - ST_NOW.born) / 1000));
  return [Math.floor(sec / 3600), Math.floor(sec / 60) % 60, sec % 60].map((n) => String(n).padStart(2, '0')).join(':');
}

const ST_METRICS = {
  u: { name: 'Уникальные', icon: 'person', hint: 'уникальных хостов' },
  h: { name: 'Входы', icon: 'eye', hint: 'просмотров страниц' },
  home: { name: 'Главная', icon: 'house-door', hint: 'входов на главную' },
  ref: { name: 'Переходы', icon: 'box-arrow-in-right', hint: 'пришли по ссылке' },
  bot: { name: 'Краулеры', icon: 'robot', hint: 'поисковых роботов' },
  aud: { name: 'Аудитория', icon: 'people', hint: 'пришли сами' },
  usr: { name: 'Пользователи', icon: 'person-check', hint: 'вошли под именем' },
  gen: { name: 'Генерация', icon: 'stopwatch', hint: 'средняя страница', unit: 'мс' },
  sql: { name: 'SQL-запросы', icon: 'database', hint: 'на страницу' },
  mem: { name: 'Память PHP', icon: 'memory', hint: 'на страницу', unit: 'МБ' },
  hits: { name: 'Входы', icon: 'eye', hint: 'просмотров за час' },
};
const ST_DIMS = {
  browsers: { name: 'Браузеры', icon: 'browser-chrome', w: { 'Chrome 156': 34, 'Chrome 155': 12, 'YaBrowser 26': 11, 'Bot': 20, 'Firefox 158': 8, 'Edge 156': 7, 'Safari 19': 6, 'Opera 122': 2, 'Other': 3 } },
  os: { name: 'Системы', icon: 'windows', w: { Windows: 44, Android: 21, Bot: 20, iOS: 8, macOS: 4, Linux: 3 } },
  devices: { name: 'Устройства', icon: 'laptop', ord: ['desktop', 'mobile', 'tablet', 'bot'], w: { desktop: 55, mobile: 22, tablet: 3, bot: 20 } },
  countries: { name: 'Страны', icon: 'globe2', w: { ru: 42, ua: 10, de: 8, kz: 7, by: 6, us: 5, pl: 3, lv: 2, Other: 17 } },
  refcat: { name: 'Источники', icon: 'signpost-split', ord: ['direct', 'search', 'social', 'referrer'], w: { direct: 52, search: 33, social: 4, referrer: 11 } },
  newret: { name: 'Новые и вернувшиеся', icon: 'arrow-repeat', ord: ['new', 'returning'], w: { new: 58, returning: 42 } },
  depth: { name: 'Глубина', icon: 'layers', ord: ['1', '2-3', '4-7', '8+'], w: { '1': 46, '2-3': 27, '4-7': 16, '8+': 11 } },
  duration: { name: 'Длительность', icon: 'hourglass-split', ord: ['<30s', '30s-3m', '3m-15m', '15m+'], w: { '<30s': 50, '30s-3m': 24, '3m-15m': 18, '15m+': 8 } },
};
const ST_KEYS = {
  desktop: 'Компьютер', mobile: 'Телефон', tablet: 'Планшет', bot: 'Бот', Bot: 'Бот', Other: 'Другие',
  direct: 'Прямые', search: 'Поиск', social: 'Соцсети', referrer: 'Сайты', new: 'Новые', returning: 'Вернувшиеся',
  '1': '1 страница', '2-3': '2–3 страницы', '4-7': '4–7 страниц', '8+': '8 и больше', '<30s': 'до 30 с', '30s-3m': '30 с – 3 мин', '3m-15m': '3–15 мин', '15m+': 'дольше 15 мин',
  ru: 'Россия', ua: 'Украина', de: 'Германия', kz: 'Казахстан', by: 'Беларусь', us: 'США', pl: 'Польша', lv: 'Латвия',
};
const ST_SHARES = {
  human: { name: 'Люди', hint: 'без роботов, от всех входов' },
  mobile: { name: 'С телефона', hint: 'от входов людей' },
  ret: { name: 'Вернулись', hint: 'от всех сеансов' },
  search: { name: 'Из поиска', hint: 'от всех источников' },
};
const ST_MONTHS = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
const ST_MONTHS_OF = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
const ST_WEEK = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
const ST_WEEK_FULL = ['понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота', 'воскресенье'];
const ST_HOURS = [0.9, 0.6, 0.45, 0.35, 0.3, 0.32, 0.5, 1, 1.8, 2.6, 3, 3.1, 3, 2.9, 2.8, 2.7, 2.6, 2.6, 2.7, 2.9, 3, 2.6, 1.9, 1.3];
const ST_TONES = { u: 'primary', h: 'accent', home: 'success', ref: 'warning', bot: 'muted', aud: 'success', usr: 'danger', gen: 'warning', sql: 'accent', mem: 'primary', hits: 'primary' };

const st = { month: ST_NOW.key, day: -1, metric: 'u', range: 'all', cmp: false, cache: {}, all: ST_ALL };

function getStRand(seed) {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6D2B79F5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

function getStSeed(str) {
  let h = 2166136261;
  for (const ch of str) h = Math.imul(h ^ ch.charCodeAt(0), 16777619);
  return h >>> 0;
}

function getStNum(n, digits = 0) {
  if (n === null || n === undefined || Number.isNaN(n)) return '—';
  return Number(n).toLocaleString('ru-RU', { minimumFractionDigits: digits, maximumFractionDigits: digits });
}

function getStEsc(s) {
  return String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
}

/* The base of a month: the audience of an old CMS site — growth to 2013, a slow decline, summer dips and the release of 8.0 */
function getStBase(key) {
  const [y, m] = key.split('-').map(Number);
  const yr = y + (m - 1) / 12;
  const curve = yr < 2013 ? 300 + (yr - 2008.75) * 560 : 2680 - (yr - 2013) * 175;
  const season = [0.93, 0.98, 1.02, 1.03, 0.97, 0.88, 0.8, 0.82, 0.98, 1.04, 1.05, 0.9][m - 1];
  const boost = key === '2026-10' ? 1.42 : key === '2026-09' ? 1.08 : 1;
  return Math.max(260, curve) * season * boost;
}

function getStSplit(total, weights, rnd) {
  const out = {};
  let sum = 0;
  const keys = Object.keys(weights);
  const w = keys.map((k) => weights[k] * (0.85 + rnd() * 0.3));
  const all = w.reduce((a, b) => a + b, 0);
  keys.forEach((k, i) => {
    out[k] = i === keys.length - 1 ? Math.max(0, total - sum) : Math.round(total * w[i] / all);
    sum += out[k];
  });
  return out;
}

/* One month of the counter: a row per day with the fields of statistic.log, load fields only from October 2026 */
function getStMonth(key) {
  if (st.cache[key]) return st.cache[key];
  const [y, m] = key.split('-').map(Number);
  const run = key === ST_NOW.key;
  const len = new Date(y, m, 0).getDate();
  const upto = run ? ST_NOW.day : len;
  const rnd = getStRand(getStSeed(key));
  const base = getStBase(key);
  const load = key >= '2026-10';
  const days = [];
  for (let n = 1; n <= upto; n++) {
    const date = new Date(y, m - 1, n);
    const wd = (date.getDay() + 6) % 7;
    const live = run && n === upto;
    const part = live ? ST_HOURS.slice(0, ST_NOW.hour + 1).reduce((a, b) => a + b, 0) / ST_HOURS.reduce((a, b) => a + b, 0) : 1;
    const peak = key === '2026-10' && n === 14 ? 1.9 : key === '2026-10' && n === 15 ? 1.35 : 1;
    const u = Math.round(base * (wd > 4 ? 0.72 : 1) * (0.86 + rnd() * 0.28) * peak * part);
    const h = Math.round(u * (4.2 + rnd() * 1.6));
    const bot = Math.round(u * (0.16 + rnd() * 0.05));
    const ref = Math.round(u * (0.22 + rnd() * 0.06));
    const home = Math.round(h * (0.05 + rnd() * 0.02));
    const usr = Math.max(1, Math.round(u * (0.012 + rnd() * 0.008)));
    const hw = ST_HOURS.map((v, i) => (live && i > ST_NOW.hour ? 0 : v * (0.8 + rnd() * 0.4)));
    const hsum = hw.reduce((a, b) => a + b, 0);
    const hrs = hw.map((v) => Math.round(h * v / hsum));
    const num = load ? hrs.slice() : hrs.map(() => 0);
    const gen = num.map((p, i) => Math.round(p * (64 + (i === 3 || i === 4 ? 120 : 0) + (i >= 10 && i <= 14 ? 18 : 0) + rnd() * 22)));
    const sql = num.map((p, i) => Math.round(p * (19 + (i === 3 || i === 4 ? 9 : 0) + rnd() * 5)));
    const mem = num.map((p) => Math.round(p * (1720 + rnd() * 380)));
    const sess = Math.round(u * 1.3);
    const dims = {};
    Object.keys(ST_DIMS).forEach((dk) => {
      const total = ['newret', 'depth', 'duration'].includes(dk) ? sess : dk === 'refcat' ? Math.round(h * 0.8) : h;
      dims[dk] = getStSplit(total, ST_DIMS[dk].w, rnd);
    });
    days.push({ n, date, wd, live, u, h, home, ref, bot, aud: Math.max(0, u - (bot + ref)), usr, hrs, num, gen, sql, mem, dims, load,
      d: String(n).padStart(2, '0') + '.' + String(m).padStart(2, '0') + '.' + y });
  }
  st.cache[key] = { key, y, m, len, run, load, days, title: ST_MONTHS[m - 1] + ' ' + y };
  return st.cache[key];
}

/* The sums of a run of days, as the panel adds them up: counts summed, the load averaged over the counted pages */
function getStAgg(days, label) {
  const a = { days, label, u: 0, h: 0, home: 0, ref: 0, bot: 0, aud: 0, usr: 0, hrs: Array(24).fill(0), num: Array(24).fill(0),
    gen: Array(24).fill(0), sql: Array(24).fill(0), mem: Array(24).fill(0), dims: {}, load: days.some((d) => d.load) };
  Object.keys(ST_DIMS).forEach((k) => { a.dims[k] = {}; });
  days.forEach((d) => {
    ['u', 'h', 'home', 'ref', 'bot', 'aud', 'usr'].forEach((k) => { a[k] += d[k]; });
    for (let i = 0; i < 24; i++) {
      a.hrs[i] += d.hrs[i];
      a.num[i] += d.num[i];
      a.gen[i] += d.gen[i];
      a.sql[i] += d.sql[i];
      a.mem[i] += d.mem[i];
    }
    Object.keys(d.dims).forEach((k) => Object.entries(d.dims[k]).forEach(([n, v]) => { a.dims[k][n] = (a.dims[k][n] || 0) + v; }));
  });
  const pages = a.num.reduce((x, y) => x + y, 0);
  a.pages = pages;
  a.avg = {
    gen: pages ? a.gen.reduce((x, y) => x + y, 0) / pages : null,
    sql: pages ? a.sql.reduce((x, y) => x + y, 0) / pages : null,
    mem: pages ? a.mem.reduce((x, y) => x + y, 0) / pages / 1024 : null,
  };
  a.genHour = a.gen.map((g, i) => (a.num[i] ? g / a.num[i] : 0));
  return a;
}

function getStDayVal(d, key) {
  if (key === 'gen' || key === 'sql' || key === 'mem') {
    const p = d.num.reduce((x, y) => x + y, 0);
    if (!p) return null;
    const s = d[key].reduce((x, y) => x + y, 0) / p;
    return key === 'mem' ? s / 1024 : s;
  }
  return d[key];
}

function getStPeriod() {
  const mon = getStMonth(st.month);
  const days = st.range === 'all' ? mon.days : mon.days.slice(-Number(st.range));
  return { mon, agg: getStAgg(days, mon.title) };
}

function getStPrevKey(key) {
  const [y, m] = key.split('-').map(Number);
  const d = new Date(y, m - 2, 1);
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
}

function getStHas(key) {
  return key === ST_NOW.key || ST_ARCHIVE.includes(key);
}

function getStScope(el) {
  const { mon, agg } = getStPeriod();
  const box = el.closest('[data-st-scope="day"]');
  if (box) {
    const runs = mon.days.length > 1 && mon.days[mon.days.length - 1].live;
    const last = mon.days.length - (runs && !box.hasAttribute('data-st-now') ? 2 : 1);
    const day = mon.days[st.day >= 0 ? st.day : last];
    return { mon, agg: getStAgg([day], day.d), day, isDay: true };
  }
  return { mon, agg, isDay: false };
}

/* The scope to compare with: the same first days of the month before, or the day before a picked day */
function getStBefore(scope, vs) {
  if (vs === 'year' && !scope.isDay) {
    const yk = (scope.mon.y - 1) + '-' + String(scope.mon.m).padStart(2, '0');
    if (!getStHas(yk)) return null;
    const last = scope.agg.days[scope.agg.days.length - 1].n;
    return getStAgg(getStMonth(yk).days.filter((d) => d.n >= scope.agg.days[0].n && d.n <= last));
  }
  if (scope.isDay) {
    const i = scope.mon.days.indexOf(scope.day);
    if (i > 0) return getStAgg([scope.mon.days[i - 1]]);
    const pk = getStPrevKey(scope.mon.key);
    if (!getStHas(pk)) return null;
    const pd = getStMonth(pk).days;
    return getStAgg([pd[pd.length - 1]]);
  }
  const pk = getStPrevKey(scope.mon.key);
  if (!getStHas(pk)) return null;
  const pm = getStMonth(pk);
  const first = scope.agg.days[0].n;
  const last = scope.agg.days[scope.agg.days.length - 1].n;
  return getStAgg(pm.days.filter((d) => d.n >= first && d.n <= last));
}

function getStValue(agg, key) {
  if (key === 'gen' || key === 'sql' || key === 'mem') return agg.avg[key];
  if (key === 'all') return st.all;
  return agg[key];
}

function getStShare(agg, key) {
  const d = agg.dims;
  const sum = (o) => Object.values(o).reduce((x, y) => x + y, 0);
  const pct = (a, b) => (b > 0 ? Math.round(a / b * 100) : 0);
  const human = sum(d.devices) - (d.devices.bot || 0);
  if (key === 'human') return pct(human, sum(d.devices));
  if (key === 'mobile') return pct(d.devices.mobile || 0, human);
  if (key === 'ret') return pct(d.newret.returning || 0, sum(d.newret));
  return pct(d.refcat.search || 0, sum(d.refcat));
}

/* The rows of a dimension as getStatExtPanel() orders them: by its fixed order or by count, the rest at the end */
function getStRows(agg, key) {
  const map = Object.assign({}, agg.dims[key]);
  const oth = map.Other || 0;
  delete map.Other;
  const sum = Object.values(map).reduce((x, y) => x + y, 0) + oth;
  const ord = ST_DIMS[key].ord;
  let keys = Object.keys(map);
  if (ord) keys = ord.filter((k) => k in map).concat(keys.filter((k) => !ord.includes(k)));
  else keys.sort((a, b) => map[b] - map[a]);
  const rows = keys.filter((k) => map[k] > 0).map((k) => ({ key: k, name: ST_KEYS[k] || k, num: map[k], pct: sum ? Math.round(map[k] / sum * 100) : 0 }));
  if (oth > 0) rows.push({ key: 'Other', name: ST_KEYS.Other, num: oth, pct: sum ? Math.round(oth / sum * 100) : 0 });
  return rows;
}

function getStPeakHour(arr) {
  let best = 0;
  arr.forEach((v, i) => { if (v > arr[best]) best = i; });
  return best;
}

function getStHourName(i) {
  return String(i).padStart(2, '0') + ':00';
}

function getStText(scope, key) {
  const a = scope.agg;
  const days = a.days;
  const fmt = (v, dg) => getStNum(v, dg);
  switch (key) {
    case 'period': return scope.isDay ? scope.day.d : scope.mon.title;
    case 'period-short': return ST_MONTHS[scope.mon.m - 1].slice(0, 3) + ' ' + scope.mon.y;
    case 'month': return scope.mon.title;
    case 'days': return String(days.length);
    case 'avg-u': return fmt(a.u / days.length);
    case 'avg-h': return fmt(a.h / days.length);
    case 'avg-work':
    case 'avg-end': {
      const set = days.filter((d) => !d.live && (key === 'avg-end' ? d.wd > 4 : d.wd < 5));
      return set.length ? fmt(set.reduce((x, d) => x + d.u, 0) / set.length) : '—';
    }
    case 'per': return fmt(a.u ? a.h / a.u : 0, 1);
    case 'gen': return a.avg.gen === null ? '—' : fmt(a.avg.gen);
    case 'sql': return a.avg.sql === null ? '—' : fmt(a.avg.sql, 1);
    case 'mem': return a.avg.mem === null ? '—' : fmt(a.avg.mem, 1);
    case 'all': return fmt(st.all);
    case 'peak-day': {
      const top = days.reduce((b, d) => (d.u > b.u ? d : b), days[0]);
      return top.n + ' ' + ST_MONTHS_OF[scope.mon.m - 1];
    }
    case 'peak-u': return fmt(Math.max(...days.map((d) => d.u)));
    case 'peak-hour': return getStHourName(getStPeakHour(a.hrs));
    case 'slow-hour': return a.load ? getStHourName(getStPeakHour(a.genHour)) : '—';
    case 'fast-hour': {
      if (!a.load) return '—';
      let best = -1;
      a.genHour.forEach((v, i) => { if (v > 0 && (best < 0 || v < a.genHour[best])) best = i; });
      return getStHourName(best);
    }
    case 'date': return scope.isDay ? scope.day.n + ' ' + ST_MONTHS_OF[scope.mon.m - 1] : scope.mon.title;
    case 'weekday': return scope.isDay ? ST_WEEK_FULL[scope.day.wd] : '';
    case 'run': return scope.mon.run ? 'идёт ' + ST_NOW.day + '-й день · ' + getStClockNow() : 'архив, ' + days.length + ' дн.';
    default:
      if (key.startsWith('share-')) return String(getStShare(a, key.slice(6)));
      if (key.startsWith('name-')) return (ST_METRICS[key.slice(5)] || ST_DIMS[key.slice(5)] || {}).name || '';
      return a[key] === undefined ? '' : fmt(a[key]);
  }
}

function getStDelta(scope, key, vs) {
  const was = getStBefore(scope, vs);
  if (!was) return null;
  const now = getStValue(scope.agg, key);
  const then = getStValue(was, key);
  if (!then || now === null) return null;
  return Math.round((now - then) / then * 100);
}

/* The sentences a report opens a section with; every number in them is a field of the scope */
function getStSay(scope, key) {
  const a = scope.agg;
  const t = (k) => `<b>${getStText(scope, k)}</b>`;
  const dlt = getStDelta(scope, 'u');
  const cmp = dlt === null ? '' : dlt === 0 ? ', столько же, сколько месяцем раньше' : `, на <b>${Math.abs(dlt)} %</b> ${dlt > 0 ? 'больше' : 'меньше'}, чем в те же дни месяцем раньше`;
  const top = (k) => getStRows(a, k).filter((r) => !['Bot', 'bot', 'Other'].includes(r.key))[0] || { name: '—', pct: 0 };
  switch (key) {
    case 'visits': return scope.isDay
      ? `${t('date')}, ${getStText(scope, 'weekday')}: ${t('u')} уникальных хостов и ${t('h')} просмотров${cmp.replace('месяцем раньше', 'днём раньше').replace('в те же дни ', '')}.`
      : `За ${scope.agg.days.length < scope.mon.len ? scope.agg.days.length + ' дн. ' + ST_MONTHS_OF[scope.mon.m - 1] : ST_MONTHS[scope.mon.m - 1].toLowerCase()} сайт открыли ${t('u')} уникальных хостов${cmp}. В среднем ${t('avg-u')} в день, пик — ${t('peak-day')}: ${t('peak-u')}.`;
    case 'audience': return `Из всех входов ${t('share-human')}&nbsp;% — люди, остальное — роботы. Вернулись ${t('share-ret')}&nbsp;% сеансов, с телефона пришли ${t('share-mobile')}&nbsp;% людей.`;
    case 'sources': return `Сами пришли ${t('aud')}, по ссылкам — ${t('ref')}, роботов — ${t('bot')}. Из поиска — ${t('share-search')}&nbsp;% всех источников.`;
    case 'tech': return `Чаще всего открывают в <b>${top('browsers').name}</b> (${top('browsers').pct}&nbsp;%), на <b>${top('os').name}</b>; первая страна — <b>${top('countries').name}</b>.`;
    case 'load': return a.load
      ? `Средняя страница собирается за ${t('gen')}&nbsp;мс, делает ${t('sql')} запросов к базе и берёт ${t('mem')}&nbsp;МБ памяти. Медленнее всего — в ${t('slow-hour')}, когда работает планировщик.`
      : 'Нагрузку счётчик пишет с октября 2026 года: у этого месяца её колонок нет, вместо цифр — прочерк.';
    case 'hours': return `Больше всего просмотров приходится на ${t('peak-hour')}; ночью, с 02:00 до 06:00, сайт почти пуст.`;
    default: return '';
  }
}

/* The chart: columns of the first series, a line of the second on its own scale, the month before dashed, a tip under the hand */
function setStChart(el, scope) {
  const x = el.dataset.stX || 'days';
  const keys = el.dataset.stChart.split(',').map((k) => (k === '@' ? st.metric : k));
  const kind = el.dataset.stKind || 'bars';
  const a = scope.agg;
  const days = a.days;
  const series = keys.map((k) => (x === 'hours'
    ? (k === 'gen' ? a.genHour : k === 'sql' ? a.sql.map((s, i) => (a.num[i] ? s / a.num[i] : 0)) : a.hrs)
    : days.map((d) => getStDayVal(d, k))));
  const n = series[0].length;
  const labels = x === 'hours' ? Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0')) : days.map((d) => String(d.n));
  let cmp = null;
  if (st.cmp && el.hasAttribute('data-st-cmp-on') && x === 'days' && !scope.isDay) {
    const pk = getStPrevKey(scope.mon.key);
    if (getStHas(pk)) {
      const pd = getStMonth(pk).days;
      cmp = days.map((d) => { const o = pd.find((p) => p.n === d.n); return o ? getStDayVal(o, keys[0]) : null; });
    }
  }
  const top0 = Math.max(1, ...series[0].map((v) => v || 0), ...(cmp || []).map((v) => v || 0));
  const nice = (v) => { const p = Math.pow(10, Math.floor(Math.log10(v))); return Math.ceil(v / p / (v / p > 5 ? 2 : 1)) * p * (v / p > 5 ? 2 : 1); };
  const top = nice(top0);
  const unit = (ST_METRICS[keys[0]] || {}).unit || '';
  const grid = [1, 0.75, 0.5, 0.25, 0].map((f) => `<span class="st-grid-line" style="--sl-d-y:${f}"><i>${getStNum(top * f, keys[0] === 'sql' || keys[0] === 'mem' ? 1 : 0)}</i></span>`).join('');
  const every = n > 16 ? (x === 'hours' ? 3 : 2) : 1;
  const tops = [top].concat(series.slice(1).map((s) => Math.max(1, ...s.map((v) => v || 0)) * 1.08));
  const info = { keys, series, cmp, labels, x, days, scope, tops, kind };
  const cols = series[0].map((v, i) => {
    const d = x === 'days' ? days[i] : null;
    const cls = ['st-col', 'sl-float', d && d.wd > 4 ? 'st-col-end' : '', d && d.live ? 'st-col-run' : '', d && st.day >= 0 && scope.mon.days[st.day] === d ? 'st-is-pick' : ''].filter(Boolean).join(' ');
    return `<span class="${cls}" data-st-i="${i}" style="--sl-d-v:${v === null ? 0 : v / top};--sl-d-i:${i}">${kind === 'bars' ? `<i class="st-bar st-s-${keys[0]}"></i>` : ''}<em${i % every ? ' class="st-col-skip"' : ''}>${labels[i]}</em><span class="sl-float-panel st-float" role="tooltip">${getStHintHtml(getStColRows(info, i))}</span></span>`;
  }).join('');
  const path = (vals, max, area) => {
    const pts = vals.map((v, i) => [(i + 0.5) / n * 100, 100 - (v === null ? 0 : v / max * 100)]);
    const line = pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(2) + ' ' + p[1].toFixed(2)).join(' ');
    return area ? line + ` L${pts[pts.length - 1][0].toFixed(2)} 100 L${pts[0][0].toFixed(2)} 100 Z` : line;
  };
  let svg = '';
  const gid = 'st-g-' + Math.random().toString(36).slice(2, 8);
  if (kind !== 'bars') {
    svg += `<path class="st-area st-s-${keys[0]}" d="${path(series[0], top, true)}" fill="url(#${gid})"${kind === 'line' ? ' hidden' : ''}></path><path class="st-line st-s-${keys[0]}" d="${path(series[0], top)}"></path>`;
  }
  if (cmp) svg += `<path class="st-line st-line-cmp st-s-${keys[0]}" d="${path(cmp, top)}"></path>`;
  series.slice(1).forEach((s, j) => {
    svg += `<path class="st-line st-line-2 st-s-${keys[j + 1]}" d="${path(s, tops[j + 1])}"></path>`;
  });
  el.innerHTML = `<div class="st-plot" style="--sl-d-n:${n}">
    <div class="st-grid">${grid}</div>
    <svg class="st-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="${gid}" class="st-s-${keys[0]}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity="0.42"></stop><stop offset="1" stop-color="currentColor" stop-opacity="0.02"></stop></linearGradient></defs>${svg}</svg>
    <div class="st-cols${el.hasAttribute('data-st-pick') && x === 'days' && !scope.isDay ? ' st-cols-pick' : ''}">${cols}</div>
    <div class="st-marks"></div>
  </div>`;
  el.dataset.stUnit = unit;
  el._st = info;
}

/* The panel of a column: the day or the hour, every series of the chart, the month before while it is drawn */
function getStColRows(s, i) {
  const d = s.x === 'days' ? s.days[i] : null;
  const head = d ? `${d.n} ${ST_MONTHS_OF[s.scope.mon.m - 1]}, ${ST_WEEK[d.wd]}${d.live ? ' · идёт' : ''}` : getStHourName(i) + '–' + getStHourName((i + 1) % 24);
  const row = (k, v, before) => {
    const m = ST_METRICS[k] || { name: k };
    return [m.name + (before ? ' · месяц назад' : ''), v === null ? '—' : getStNum(v, k === 'sql' || k === 'mem' ? 1 : 0) + (m.unit ? ' ' + m.unit : ''), 's-' + k];
  };
  const rows = s.keys.map((k, j) => row(k, s.series[j][i]));
  if (s.cmp) rows.push(row(s.keys[0], s.cmp[i], true));
  return { c: head, r: rows };
}

function setStMarks(el, i) {
  const s = el._st;
  el.querySelectorAll('.st-col').forEach((c) => c.classList.toggle('st-is-on', Number(c.dataset.stI) === i));
  const marks = el.querySelector('.st-marks');
  if (i < 0) { marks.innerHTML = ''; return; }
  const pos = (i + 0.5) / s.series[0].length;
  const dots = [];
  if (s.kind !== 'bars') dots.push([s.keys[0], s.series[0][i], s.tops[0]]);
  if (s.cmp) dots.push([s.keys[0] + ' st-mark-cmp', s.cmp[i], s.tops[0]]);
  s.series.slice(1).forEach((v, j) => dots.push([s.keys[j + 1], v[i], s.tops[j + 1]]));
  marks.style.setProperty('--sl-d-x', pos);
  marks.innerHTML = dots.filter((m) => m[1] !== null).map(([k, v, t]) => `<i class="st-mark st-s-${k}" style="--sl-d-y:${Math.min(1, v / t).toFixed(3)}"></i>`).join('');
}

/* The panel of a float of the system: its caption and its rows, the markup of the panel in one place */
function getStFloat(cap, rows) {
  return `<span class="sl-float-panel st-float" role="tooltip">${getStHintHtml({ c: cap, r: rows })}</span>`;
}

function getStHintHtml(data) {
  return `<span class="sl-bell-cap">${getStEsc(data.c)}</span>` + data.r.map(([name, num, tone]) =>
    `<span class="st-tip-row">${tone ? `<i class="st-dot st-${tone}"></i>` : '<i></i>'}${getStEsc(name)}<b class="sl-bell-num">${getStEsc(num)}</b></span>`).join('');
}

/* The rhythm chart is a canvas of the plugin, so the stand lays a zone over every point of it: a float of the system with
   the figures of that point. The zones stand where the plugin puts its points - the axis takes the width of its top
   figure in the font of the chart and 8 px, the plot ends 10 px before the edge - and they hand the hand on to the
   canvas, so its guide and its dots answer as before */
function setStPresZones(art, set, names) {
  const plot = art.querySelector('.sl-pres-plot');
  const face = plot.querySelector('canvas');
  const rows = [set.visits, set.cache, set.db].filter(Boolean);
  const count = set.visits.length;
  const tones = ['s-u', 's-h', 's-home'];
  const label = (i) => (count === 24 ? String(set.labels[i]).padStart(2, '0') + ':00' : String(set.labels[i]).slice(0, 5));
  const box = document.createElement('div');
  box.className = 'st-zones';
  box.innerHTML = set.visits.map((_, i) => `<span class="st-zone sl-float"><span class="sl-float-panel st-float" role="tooltip">${getStHintHtml({ c: label(i), r: rows.map((r, j) => [names[j], getStNum(r[i] || 0), tones[j]]) })}</span></span>`).join('');
  plot.appendChild(box);
  const place = () => {
    const pen = document.createElement('canvas').getContext('2d');
    const style = getComputedStyle(art);
    pen.font = style.getPropertyValue('--sl-font-micro').trim() + ' ' + style.fontFamily;
    const top = Math.max(1, ...rows.flat());
    const left = Math.ceil(pen.measureText(String(Math.round(top)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ')).width) + 8;
    const right = plot.clientWidth - 10;
    const step = count > 1 ? (right - left) / (count - 1) : right - left;
    box.querySelectorAll('.st-zone').forEach((zone, i) => {
      zone.style.left = (left + (i - 0.5) * step) + 'px';
      zone.style.width = step + 'px';
    });
  };
  place();
  new ResizeObserver(place).observe(plot);
  box.addEventListener('pointermove', (e) => face.dispatchEvent(new PointerEvent('pointermove', { clientX: e.clientX, clientY: e.clientY })));
  box.addEventListener('pointerleave', () => face.dispatchEvent(new PointerEvent('pointerleave')));
}

function setStSpark(el, scope) {
  const key = el.dataset.stSpark;
  const vals = scope.agg.days.map((d) => getStDayVal(d, key) || 0);
  const max = Math.max(1, ...vals);
  const n = vals.length;
  const pts = vals.map((v, i) => `${(n > 1 ? i / (n - 1) : 0.5) * 100},${(100 - v / max * 90).toFixed(1)}`).join(' ');
  el.innerHTML = `<svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><polyline class="st-s-${key}" points="${pts}"></polyline></svg>`;
}

function setStBars(el, scope) {
  const key = el.dataset.stBars;
  let rows = getStRows(scope.agg, key);
  const top = Number(el.dataset.stTop || 0);
  if (top && rows.length > top) {
    const rest = rows.slice(top - 1);
    rows = rows.slice(0, top - 1).concat([{ key: 'Other', name: 'Остальные', num: rest.reduce((x, r) => x + r.num, 0), pct: rest.reduce((x, r) => x + r.pct, 0) }]);
  }
  const max = Math.max(1, ...rows.map((r) => r.pct));
  const tpl = el._stTpl || (el._stTpl = el.querySelector('template'));
  const flag = (r) => (key === 'countries' && r.key.length === 2 ? `<img src="../templates/admin/images/flags/${r.key}.svg" alt="" width="18" height="13">` : '');
  if (!tpl) {
    el.innerHTML = rows.map((r, i) => `<div class="sl-statx-bar" style="--sl-d-i:${i}"><span class="sl-statx-bl" title="${getStEsc(r.name)}">${flag(r)}${getStEsc(r.name)}</span><span class="sl-statx-bg"><span class="sl-statx-bv" style="width: ${r.pct}%"></span></span><span class="sl-statx-bc">${getStNum(r.num)}</span><span class="sl-statx-bp">${r.pct}%</span></div>`).join('');
    return;
  }
  el.replaceChildren(tpl);
  rows.forEach((r, i) => {
    const node = tpl.content.firstElementChild.cloneNode(true);
    node.style.setProperty('--sl-d-pct', r.pct + '%');
    node.style.setProperty('--sl-d-rel', (r.pct / max * 100) + '%');
    node.style.setProperty('--sl-d-i', i);
    node.classList.add('st-k-' + (i % 5 + 1));
    node.querySelectorAll('[data-st-f]').forEach((f) => {
      const k = f.dataset.stF;
      if (k === 'name') f.textContent = r.name;
      else if (k === 'num') f.textContent = getStNum(r.num);
      else if (k === 'pct') f.textContent = r.pct + ' %';
      else if (k === 'flag') f.innerHTML = flag(r);
    });
    el.appendChild(node);
  });
}

function setStStack(el, scope) {
  const key = el.dataset.stStack;
  const rows = getStRows(scope.agg, key);
  el.innerHTML = `<span class="st-stack-bar" role="img" aria-label="${ST_DIMS[key].name}">${rows.map((r, i) => `<span class="st-stack-part sl-float st-k-${i % 5 + 1}" style="--sl-d-pct:${r.pct}%">${getStFloat(ST_DIMS[key].name, [[r.name, r.pct + ' %', 'k-' + (i % 5 + 1)], ['Число', getStNum(r.num)]])}</span>`).join('')}</span>` +
    `<span class="st-stack-keys">${rows.map((r, i) => `<span class="st-stack-key st-k-${i % 5 + 1}"><i></i>${getStEsc(r.name)} <b>${r.pct} %</b></span>`).join('')}</span>`;
}

function setStShare(el, scope) {
  const p = getStShare(scope.agg, el.dataset.stShare);
  el.style.setProperty('--sl-d-share', p / 100);
  el.querySelectorAll('[data-st-v]').forEach((v) => setStNum(v, String(p)));
  el.querySelectorAll('[data-st-pct]').forEach((v) => setStNum(v, p + ' %'));
  el.querySelectorAll('[data-st-share-name]').forEach((v) => { v.textContent = ST_SHARES[el.dataset.stShare].name; });
  const list = el.parentElement.querySelector('[data-st-share-rows]');
  if (list) {
    const cap = el.parentElement.querySelector('[data-st-share-cap]');
    if (cap) cap.textContent = ST_SHARES[el.dataset.stShare].name;
    list.innerHTML = getStShareRows(scope.agg, el.dataset.stShare).map(([icon, name, num]) =>
      `<a><i class="bi bi-${icon}" aria-hidden="true"></i> <span>${name}</span> <span class="sl-bell-num">${getStNum(num)}</span></a>`).join('');
  }
  el.querySelectorAll('[data-st-share-hint]').forEach((v) => { v.textContent = ST_SHARES[el.dataset.stShare].hint; });
  el._stShare = p;
  setStArc(el);
  if (el.hasAttribute('role')) el.setAttribute('aria-valuenow', p);
}

/* What a share is made of, as the rows of a gauge of the cockpit: hits of people and robots, devices of people,
   sessions, sources */
function getStShareRows(agg, key) {
  const d = agg.dims;
  const sum = (o) => Object.values(o).reduce((x, y) => x + y, 0);
  if (key === 'human') return [['person', 'Люди', sum(d.devices) - (d.devices.bot || 0)], ['robot', 'Роботы', d.devices.bot || 0], ['eye', 'Всего входов', sum(d.devices)]];
  if (key === 'mobile') return [['phone', 'Телефон', d.devices.mobile || 0], ['laptop', 'Компьютер', d.devices.desktop || 0], ['tablet', 'Планшет', d.devices.tablet || 0]];
  if (key === 'ret') return [['arrow-repeat', 'Вернувшиеся', d.newret.returning || 0], ['person-plus', 'Новые', d.newret.new || 0], ['layers', 'Всего сеансов', sum(d.newret)]];
  return [['search', 'Поиск', d.refcat.search || 0], ['cursor', 'Прямые', d.refcat.direct || 0], ['link-45deg', 'Сайты', d.refcat.referrer || 0], ['people', 'Соцсети', d.refcat.social || 0]];
}

function setStDelta(el, scope) {
  const v = getStDelta(scope, el.dataset.stDelta, el.dataset.stVs);
  el.dataset.dir = v === null ? 'none' : v > 1 ? 'up' : v < -1 ? 'down' : 'flat';
  const ic = v === null ? 'dash' : v > 1 ? 'arrow-up-right' : v < -1 ? 'arrow-down-right' : 'arrow-right';
  el.innerHTML = `<i class="bi bi-${ic}" aria-hidden="true"></i>${v === null ? 'нет данных' : (v > 0 ? '+' : v < 0 ? '−' : '') + Math.abs(v) + ' %'}`;
  el.title = scope.isDay ? 'К предыдущему дню' : el.dataset.stVs === 'year' ? 'К тем же дням год назад' : 'К тем же дням месяцем раньше';
}

/* The table of days: the columns of the live page, the total row, and on request a bar in every cell */
function setStTable(el, scope) {
  const opt = (el.dataset.stTable || '').split(' ');
  const a = scope.agg;
  const cols = [['u', 'Уникальные', 'v'], ['h', 'Входы', 'p'], ['home', 'Главная', 'p'], ['ref', 'Переходы', 's'], ['bot', 'Краулеры', 's'], ['aud', 'Аудитория', 'v'], ['usr', 'Пользователи', 'v'], ['gen', 'Генерация', 'l'], ['sql', 'SQL-запросы', 'l'], ['mem', 'Память PHP', 'l']];
  const order = opt.includes('groups') ? ['u', 'aud', 'usr', 'h', 'home', 'ref', 'bot', 'gen', 'sql', 'mem'].map((k) => cols.find((c) => c[0] === k)) : cols;
  const max = {};
  order.forEach(([k]) => { max[k] = Math.max(1, ...a.days.map((d) => getStDayVal(d, k) || 0)); });
  const fmt = (k, v) => (v === null ? '—' : k === 'gen' ? getStNum(v) + ' мс' : k === 'mem' ? getStNum(v, 1) + ' МБ' : k === 'sql' ? getStNum(v, 1) : getStNum(v));
  const cell = (k, v, isTop) => `<td class="st-td-${k}${isTop ? ' st-td-top' : ''}">${opt.includes('bars') && v !== null ? `<span class="st-cell-bar st-s-${k}" style="--sl-d-pct:${(v / max[k] * 100).toFixed(1)}%"></span>` : ''}<span class="st-cell-num">${fmt(k, v)}</span></td>`;
  const groups = opt.includes('groups') ? `<tr class="st-thead-groups"><th></th><th colspan="3">Посетители</th><th colspan="2">Страницы</th><th colspan="2">Источники</th><th colspan="3">Нагрузка средней страницы</th></tr>` : '';
  const head = `<tr><th class="st-th-date">Дата</th>${order.map(([k, n]) => `<th class="st-th-${k}">${n}</th>`).join('')}</tr>`;
  const rows = a.days.map((d, i) => {
    const pick = st.day >= 0 && scope.mon.days[st.day] === d;
    const vals = order.map(([k]) => getStDayVal(d, k));
    return `<tr style="--sl-d-i:${i}" class="${[d.wd > 4 ? 'st-tr-end' : '', d.live ? 'st-tr-run' : '', pick ? 'st-is-pick' : ''].filter(Boolean).join(' ')}"${opt.includes('pick') ? ` data-st-day="${scope.mon.days.indexOf(d)}" tabindex="0"` : ''}><td class="st-td-date"><span class="st-date-n">${d.d}</span><span class="st-date-wd">${ST_WEEK[d.wd]}</span>${d.live ? '<span class="sl-chip sl-chip-success st-run-chip">идёт</span>' : ''}</td>${order.map(([k], j) => cell(k, vals[j], vals[j] !== null && vals[j] === max[k] && a.days.length > 1)).join('')}</tr>`;
  }).join('');
  const total = `<tr class="st-tr-total"><td>Всего</td>${order.map(([k]) => `<td class="st-td-${k}">${fmt(k, getStValue(a, k))}</td>`).join('')}</tr>`;
  el.innerHTML = `<table class="sl-table-list-sort sl-table-fixed st-table">${opt.includes('nohead') ? '' : `<thead>${groups}${head}</thead>`}<tbody>${opt.includes('totaltop') ? total + rows : rows + total}</tbody></table>`;
}

function setStCal(el, scope) {
  const mon = scope.mon;
  const key = el.dataset.stCal || st.metric;
  const vals = mon.days.map((d) => getStDayVal(d, key) || 0);
  const full = vals.filter((v, i) => !mon.days[i].live);
  const max = Math.max(1, ...full);
  const min = Math.min(...full);
  const heat = (v) => Math.max(0.06, Math.min(1, 0.12 + 0.88 * (v - min) / Math.max(1, max - min)));
  const lead = (new Date(mon.y, mon.m - 1, 1).getDay() + 6) % 7;
  let html = ST_WEEK.map((w, i) => `<span class="st-cal-wd${i > 4 ? ' st-cal-end' : ''}">${w}</span>`).join('');
  for (let i = 0; i < lead; i++) html += '<span class="st-cal-pad"></span>';
  for (let n = 1; n <= mon.len; n++) {
    const d = mon.days[n - 1];
    if (!d) { html += `<span class="st-cal-day st-cal-future"><span class="st-cal-n">${n}</span></span>`; continue; }
    const pick = st.day === n - 1;
    const v = getStDayVal(d, key);
    html += `<button type="button" class="st-cal-day${d.wd > 4 ? ' st-cal-end' : ''}${d.live ? ' st-cal-run' : ''}${pick ? ' st-is-pick' : ''}" data-st-day="${n - 1}" aria-pressed="${pick}" style="--sl-d-heat:${heat(vals[n - 1]).toFixed(3)};--sl-d-i:${n}" title="${d.d}">` +
      `<span class="st-cal-n">${n}</span><b class="st-cal-v">${getStNum(v, key === 'sql' || key === 'mem' ? 1 : 0)}</b><span class="st-cal-sub">${getStNum(d.h)} вх.</span><span class="st-cal-meter" style="--sl-d-pct:${(d.h / Math.max(...mon.days.map((x) => x.h)) * 100).toFixed(1)}%"></span></button>`;
  }
  el.innerHTML = html;
}

function setStWeek(el, scope) {
  const grid = Array.from({ length: 7 }, () => Array(24).fill(0));
  const cnt = Array(7).fill(0);
  scope.agg.days.forEach((d) => { if (d.live) return; cnt[d.wd]++; d.hrs.forEach((v, i) => { grid[d.wd][i] += v; }); });
  const avg = grid.map((r, w) => r.map((v) => (cnt[w] ? v / cnt[w] : 0)));
  const max = Math.max(1, ...avg.flat());
  let html = '<span class="st-week-corner"></span>' + Array.from({ length: 24 }, (_, i) => `<span class="st-week-h">${i % 3 ? '' : String(i).padStart(2, '0')}</span>`).join('');
  avg.forEach((r, w) => {
    html += `<span class="st-week-wd${w > 4 ? ' st-cal-end' : ''}">${ST_WEEK[w]}</span>` + r.map((v, i) => `<span class="st-week-cell sl-float" data-lv="${Math.min(5, Math.round(v / max * 5))}" style="--sl-d-heat:${(v / max).toFixed(3)};--st-wx:${i};--st-wy:${w}">${getStFloat(ST_WEEK_FULL[w] + ', ' + getStHourName(i) + '–' + getStHourName((i + 1) % 24), [['Входов в среднем', getStNum(v), 's-hits']])}</span>`).join('');
  });
  el.innerHTML = html;
}

/* The day as a dial: a spoke per hour for the hits, a dot outside it for the average generation */
function setStClock(el, scope) {
  const a = scope.agg;
  const max = Math.max(1, ...a.hrs);
  const gmax = Math.max(1, ...a.genHour);
  const peak = getStPeakHour(a.hrs);
  let svg = '<circle class="st-clock-ring" cx="100" cy="100" r="44"></circle><circle class="st-clock-ring" cx="100" cy="100" r="84"></circle>';
  for (let i = 0; i < 24; i++) {
    const ang = (i / 24) * Math.PI * 2 - Math.PI / 2 + Math.PI / 24;
    const r0 = 48;
    const r1 = 48 + 32 * a.hrs[i] / max;
    const pt = (r) => [(100 + Math.cos(ang) * r).toFixed(2), (100 + Math.sin(ang) * r).toFixed(2)];
    const [x0, y0] = pt(r0);
    const [x1, y1] = pt(r1);
    const when = scope.day && scope.day.live ? (i === ST_NOW.hour ? ' st-is-now' : i > ST_NOW.hour ? ' st-is-later' : '') : '';
    svg += `<line class="st-clock-spoke${i === peak ? ' st-is-on' : ''}${when}" data-st-h="${i}" x1="${x0}" y1="${y0}" x2="${x1}" y2="${y1}" style="--sl-d-i:${i}"></line>`;
    if (a.load && a.genHour[i]) {
      const [gx, gy] = pt(89);
      svg += `<circle class="st-clock-gen" cx="${gx}" cy="${gy}" r="${(1.2 + 3.4 * a.genHour[i] / gmax).toFixed(2)}"></circle>`;
    }
    if (i % 3 === 0) {
      const ta = (i / 24) * Math.PI * 2 - Math.PI / 2;
      svg += `<text class="st-clock-h" x="${(100 + Math.cos(ta) * 99).toFixed(1)}" y="${(100 + Math.sin(ta) * 99 + 3).toFixed(1)}">${String(i).padStart(2, '0')}</text>`;
    }
  }
  el.innerHTML = `<svg viewBox="-10 -10 220 220" role="img" aria-label="Входы по часам суток">${svg}</svg>`;
  el._stClock = { hrs: a.hrs.slice(), gen: a.genHour.slice(), load: a.load };
}

/* Every month of the archive in one strip, the gaps where the file is missing, the bar the average day of the month */
function setStArchive(el) {
  const all = ST_ARCHIVE.concat([ST_NOW.key]);
  const first = all[0].split('-').map(Number);
  const keys = [];
  for (let y = first[0], m = first[1]; y < 2026 || (y === 2026 && m <= 10); m++) {
    if (m > 12) { m = 1; y++; }
    keys.push(y + '-' + String(m).padStart(2, '0'));
  }
  const avg = (k) => getStBase(k);
  const max = Math.max(...keys.filter(getStHas).map(avg));
  let year = '';
  el.innerHTML = keys.map((k, idx) => {
    const [y, m] = k.split('-');
    const has = getStHas(k);
    const yr = y !== year && Number(m) <= 6 ? (year = y, `<span class="st-arc-year">${y}</span>`) : '';
    const name = ST_MONTHS[Number(m) - 1] + ' ' + y;
    return has
      ? `<button type="button" class="st-arc-m${k === st.month ? ' st-is-pick' : ''}${k === ST_NOW.key ? ' st-arc-run' : ''}" data-st-month="${k}" aria-pressed="${k === st.month}" style="--sl-d-v:${(avg(k) / max).toFixed(3)};--sl-d-i:${idx}" title="${name}${k === ST_NOW.key ? ' — идёт' : ''}">${yr}<i></i></button>`
      : `<span class="st-arc-m st-arc-gap" title="${name} — нет файла statistic_${k}.log">${yr}<i></i></span>`;
  }).join('');
}

function setStMonths(el) {
  const opts = [`<option value="${ST_NOW.key}">Текущий месяц</option>`].concat(ST_ARCHIVE.slice().reverse().map((k) => {
    const [y, m] = k.split('-');
    return `<option value="${k}">${ST_MONTHS[Number(m) - 1]} ${y}</option>`;
  }));
  if (!el.options.length) el.innerHTML = opts.join('');
  el.value = st.month;
}

/* The rhythm chart of plugins/presentation/presentation.js on the figures of the counter, in the shape the presentation
   module hands it: 24 h is the hours of the day (the picked one, else the last whole day) — hits, the milliseconds and
   the queries of its average page, which stand on one scale with the hits of an hour; 7 d and 30 d are the days up to
   it, reaching into the months before — hits, hosts and crawlers. The plugin reads its series and the names of its
   legend once, so a new scope or a new period gets a new chart and the plugin is run once more; the period and the
   view the reader chose are pressed again on the new one. data-sl-pres-fill="all" asks the plugin for a wash and a
   shade under every line, as on the monitor of the panel */
const ST_RHYTHM_NAMES = { day: ['Входы', 'Генерация, мс', 'SQL на страницу'], week: ['Входы', 'Уникальные', 'Краулеры'] };
function getStRhythm(scope) {
  const mon = scope.mon;
  const at = st.day >= 0 ? st.day : mon.days.length - (mon.days.length > 1 && mon.days[mon.days.length - 1].live ? 2 : 1);
  const day = mon.days[at];
  const run = [];
  let key = mon.key;
  let list = mon.days.slice(0, at + 1);
  while (list && run.length < 30) {
    run.unshift(...list.slice(-(30 - run.length)));
    key = getStPrevKey(key);
    list = run.length < 30 && getStHas(key) ? getStMonth(key).days : null;
  }
  const days = (part) => ({ labels: part.map((d) => d.d), visits: part.map((d) => d.h), cache: part.map((d) => d.u), db: part.map((d) => d.bot) });
  const prev = run.length > 1 ? run[run.length - 2].h : 0;
  const hours = { labels: Array.from({ length: 24 }, (_, i) => i), visits: day.hrs.slice() };
  if (day.load) {
    hours.cache = day.gen.map((g, i) => (day.num[i] ? Math.round(g / day.num[i]) : 0));
    hours.db = day.sql.map((q, i) => (day.num[i] ? Math.round(q / day.num[i]) : 0));
  }
  return { day, prev, sets: { day: hours, week: days(run.slice(-7)), month: days(run) } };
}

function setStRhythm(el, scope) {
  const r = getStRhythm(scope);
  const span = el._stSpan || 'day';
  const json = JSON.stringify(r.sets);
  if (el._stJson === json + span) return;
  el._stJson = json + span;
  const old = el.querySelector('.sl-pres-chart');
  const keep = ['[data-sl-pres-period="' + span + '"]'].concat(old ? [...old.querySelectorAll('[data-sl-pres-view][aria-pressed="true"]')].map((b) => '[data-sl-pres-view="' + b.dataset.slPresView + '"]') : []);
  const list = span === 'day' ? ST_RHYTHM_NAMES.day.slice(0, r.sets.day.cache ? 3 : 1) : ST_RHYTHM_NAMES.week;
  const names = list.map((n) => `<span><i></i>${n}</span>`).join('');
  const diff = r.prev ? Math.round((r.day.h - r.prev) / r.prev * 100) : null;
  const seg = (attr, list, label) => `<div class="sl-pres-segment" role="group" aria-label="${label}">${list.map(([k, t, ic], i) => `<button type="button" data-sl-pres-${attr}="${k}" aria-pressed="${!i}"${ic ? ` aria-label="${t}"` : ''}>${ic ? `<i class="bi bi-${ic}" aria-hidden="true"></i>` : t}</button>`).join('')}</div>`;
  el.innerHTML = `<article class="sl-pres-chart" data-sl-pres="chart" data-sl-pres-fill="all">
    <div class="sl-pres-chart-meta">
      <div><strong>${getStNum(r.day.h)}</strong><span>входов за период${diff === null ? '' : ` <b>${diff >= 0 ? '+' : ''}${diff}%</b>`}</span></div>
      ${seg('period', [['day', '24ч'], ['week', '7д'], ['month', '30д']], 'Период графика')}
    </div>
    <div class="sl-pres-plot"><canvas role="img" aria-label="Ритм сайта: входы по часам и по дням"></canvas><output class="sl-pres-tip" hidden></output></div>
    <div class="sl-pres-chart-foot">
      <div class="sl-pres-legend">${names}</div>
      ${seg('view', [['area', 'Области', 'graph-up'], ['bar', 'Столбцы', 'bar-chart']], 'Вид графика')}
    </div>
  </article>`;
  const art = el.firstElementChild;
  art.setAttribute('data-sl-pres-series', json);
  setStPresZones(art, r.sets[span], list);
  el.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: {} }));
  const run = document.createElement('script');
  run.src = '../plugins/presentation/presentation.js?v=' + ST_NOW.born;
  run.addEventListener('load', () => {
    el._stPress = true;
    keep.forEach((sel) => { const btn = art.querySelector(sel); if (btn && btn.getAttribute('aria-pressed') !== 'true') btn.click(); });
    el._stPress = false;
    run.remove();
  });
  document.body.appendChild(run);
}

function setStIf(el, scope) {
  const want = el.dataset.stIf;
  const live = scope.isDay && scope.day.live && st.day < 0;
  const map = { day: st.day >= 0, noday: st.day < 0, now: live, last: scope.isDay && !scope.day.live && st.day < 0, load: scope.agg.load, noload: !scope.agg.load, run: scope.mon.run, arc: !scope.mon.run, cmp: st.cmp };
  el.hidden = !map[want];
}

function setStDraw() {
  const doc = document;
  const each = (sel, fn) => doc.querySelectorAll(sel).forEach((el) => fn(el, getStScope(el)));
  each('[data-st-if]', setStIf);
  each('[data-st-t]', (el, sc) => setStNum(el, getStText(sc, el.dataset.stT)));
  each('[data-st-say]', (el, sc) => { el.innerHTML = getStSay(sc, el.dataset.stSay); });
  each('[data-st-delta]', setStDelta);
  each('[data-st-spark]', setStSpark);
  each('[data-st-chart]', setStChart);
  each('[data-st-bars]', setStBars);
  each('[data-st-stack]', setStStack);
  each('[data-st-share]', setStShare);
  each('[data-st-table]', setStTable);
  each('[data-st-cal]', setStCal);
  each('[data-st-week]', setStWeek);
  each('[data-st-clock]', setStClock);
  each('[data-st-rhythm]', setStRhythm);
  doc.querySelectorAll('[data-st-stack], [data-st-week], [data-st-chart]').forEach((host) => host.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: {} })));
  doc.querySelectorAll('[data-st-archive]').forEach(setStArchive);
  doc.querySelectorAll('[data-st-months]').forEach(setStMonths);
  doc.querySelectorAll('.st-still').forEach((host) => host.classList.remove('st-still'));
  const press = (sel, on) => doc.querySelectorAll(sel).forEach((b) => { b.setAttribute('aria-pressed', String(on(b))); b.classList.toggle('sl-is-active', on(b)); });
  press('[data-st-metric]', (b) => b.dataset.stMetric === st.metric);
  press('[data-st-range]', (b) => b.dataset.stRange === st.range);
  press('[data-st-cmp]', () => st.cmp);
  press('[data-st-now-set]', (b) => b.closest('[data-st-scope="day"]').hasAttribute('data-st-now') === (b.dataset.stNowSet === 'on'));
  doc.querySelectorAll('[data-st-go]').forEach((b) => { b.disabled = !getStStep(b.dataset.stGo); });
  setStReplay();
}

function getStStep(dir) {
  const all = ST_ARCHIVE.concat([ST_NOW.key]);
  const i = all.indexOf(st.month);
  return all[i + (dir === 'prev' ? -1 : 1)] || null;
}

function setStMonth(key, dir) {
  const way = dir || (key < st.month ? 'prev' : 'next');
  st.month = key;
  st.day = -1;
  setStDraw();
  setStSwap(way);
}

/* The panel around the face: the header, the two menu blocks, the module head with its tabs, the footer */
const ST_MENU = [
  ['admins', 'Администрация', 'person-gear'], ['blocks', 'Блоки и баннеры', 'grid-3x3-gap'], ['categories', 'Категории', 'folder'],
  ['comments', 'Комментарии', 'chat-dots'], ['config', 'Конфигурации', 'gear'], ['database', 'База данных', 'database'],
  ['editor', 'Редактор', 'pencil-square'], ['favorites', 'Фавориты', 'star'], ['fields', 'Дополнительные поля', 'input-cursor-text'],
  ['groups', 'Группы', 'people-fill'], ['lang', 'Редактор языков', 'translate'], ['messages', 'Сообщения', 'envelope'],
  ['modules', 'Модули', 'puzzle'], ['monitor', 'Мониторинг', 'display'], ['newsletter', 'Рассылка', 'send'],
  ['privat', 'Личные сообщения', 'incognito'], ['ratings', 'Рейтинги', 'star-half'], ['referers', 'Переходы', 'box-arrow-in-right'],
  ['replace', 'Замена слов', 'arrow-left-right'], ['scheduler', 'Планировщик', 'clock-history'], ['security', 'Безопасность', 'shield-lock'],
  ['statistic', 'Статистика', 'graph-up'], ['template', 'Шаблон', 'palette'], ['uploads', 'Загрузки', 'cloud-arrow-up'],
];
const ST_MENU_MODS = [
  ['account', 'Пользователи', 'people'], ['changelog', 'Журнал изменений', 'journal-text'], ['contact', 'Обратная связь', 'envelope-open'],
  ['forum', 'Форум', 'chat-square-text'], ['node', 'Материалы', 'collection'], ['rss', 'RSS каналы', 'rss'], ['search', 'Поиск', 'search'],
  ['sitemap', 'Карта сайта', 'diagram-3'], ['voting', 'Опросы', 'bar-chart'],
];

/* The module the face stands in: the statistic by default, another by the data of its template (data-st-module, -title,
   -icon, -tabs as "Name:op|Name:op", an empty op for the home tab) */
function getStModule(face) {
  const d = face ? face.dataset : {};
  const key = d.stModule || 'statistic';
  const tabs = (d.stTabs || 'Главная:|Конфигурации:config|Справка:info|Журнал действий:log').split('|').map((one) => one.split(':'));
  return { key, title: d.stTitle || 'Статистика', icon: d.stIcon || 'graph-up', tabs };
}

function getStShell(face, sub, mod) {
  const item = ([key, name, icon]) => `<div class="sl-menu-list-item"><a href="../admin.php?name=${key}" title="${name}"${key === mod.key ? ' aria-current="page"' : ''}><i class="bi bi-${icon}"></i> ${name}</a></div>`;
  const block = (id, title, icon, items) => `<div class="sl-block-sidebar"><h3 title="Свёртывание / Развёртывание" data-sl-toggle-control="sl-block-${id}" data-sl-toggle-effect="slide" data-sl-toggle-duration="500"><b><i class="bi bi-${icon}"></i>${title}</b><i class="bi bi-arrows-collapse sl-panel-toggle sl-panel-toggle-open" aria-hidden="true"></i><i class="bi bi-arrows-expand sl-panel-toggle sl-panel-toggle-closed" aria-hidden="true"></i></h3>` +
    `<div id="sl-block-${id}" class="sl-block-sidebar-content" data-sl-toggle="sl-block-${id}">${items.map(item).join('')}</div></div>`;
  const tool = [['../admin.php', 'Главная', 'house-door'], ['../admin.php?name=blocks', 'Блоки и баннеры', 'grid-3x3-gap'], ['../admin.php?name=modules', 'Модули', 'gpu-card'],
    ['../admin.php?name=categories', 'Категории', 'folder'], ['/', 'Сайт', 'globe2', 1], ['../index.php?name=account', 'Профиль', 'person'], ['../admin.php?op=logout', 'Выход', 'box-arrow-right', 1]]
    .map(([href, name, icon, split]) => `<li${split ? ' class="sl-split-item"' : ''}><a href="${href}" title="${name}"><i class="bi bi-${icon}"></i> <b>${name}</b></a></li>`).join('');
  const tab = (name, href, on) => `<li class="sl-tabs-item"><a href="${href}" title="${name}" class="sl-tabs-link${on ? ' sl-is-active' : ''}"${on ? ' aria-current="page"' : ''}>${name}</a></li>`;
  const pick = sub === null && mod.key !== 'statistic' ? '' : sub === null
    ? `<form class="sl-searchbox sl-filter" action="#" aria-label="Период"><span class="sl-filter-field"><label for="st-file">Показать статистику за</label><select id="st-file" class="sl-select" data-st-months></select></span></form>`
    : sub;
  return `<div class="sl-wrapper sl-admin-shell" id="top"><div class="sl-wraptop"></div><div class="sl-wrapmid">
    <div class="sl-header sl-admin-header">
      <div class="sl-admin-brand"><h1><a href="//slaed.net" target="_blank" title="Название сайта"><img src="../templates/admin/images/logos/slaed-logo-wordmark-gradient-blue.svg" alt="Название сайта" title="Название сайта"></a></h1></div>
      <div class="sl-admin-toolbar"><div class="sl-toolbar"><div><ul class="sl-reset">${tool}</ul></div></div></div>
    </div>
    <div class="sl-admin-main">
      <aside class="sl-admin-sidebar">${block(1, 'Администратор', 'person-gear', ST_MENU)}${block(2, 'Модули', 'people', ST_MENU_MODS)}</aside>
      <main class="sl-admin-content"><div class="sl-admin-content-inner">
        <div class="sl-box">
          <div class="sl-module-head">
            <div class="sl-module-head-icon"><i class="bi bi-${mod.icon}" title="${mod.title}"></i></div>
            <div class="sl-module-head-body">
              <h1 class="sl-module-head-title">${mod.title}</h1>
              <div class="sl-module-head-subtitle">${pick}</div>
            </div>
          </div>
          <ul class="sl-tabs-nav sl-tabs-nav-main">${mod.tabs.map(([name, op], i) => tab(name, !op ? '#' : op === 'log' ? '../admin.php?name=security&amp;op=logview&amp;file=admin&amp;modul=' + mod.key : '../admin.php?name=' + mod.key + '&amp;op=' + op, !i)).join('')}</ul>
        </div>
        <div class="st-root" data-st-root>${face}</div>
        <div class="sl-box" data-demo-about></div>
      </div></main>
    </div>
    <div class="sl-footer sl-admin-footer"><div class="sl-copyright">Генерация: 0.226 сек. и 22 запросов к базе данных за 0.008 сек.<br><a href="https://slaed.net" title="SLAED CMS" target="_blank" rel="noopener noreferrer">SLAED CMS</a> © 2005-2026 Eduard Laas. Released under MIT License.</div></div>
  </div><div class="sl-wrapfoot"></div></div>`;
}

function initSt() {
  const face = document.querySelector('template[data-st-face]');
  const sub = document.querySelector('template[data-st-sub]');
  document.body.insertAdjacentHTML('afterbegin', getStShell(face ? face.innerHTML : '', sub ? sub.innerHTML : null, getStModule(face)));
  setStCards();
  const params = new URLSearchParams(location.search);
  if (params.get('month') && getStHas(params.get('month'))) st.month = params.get('month');
  if (document.querySelector('[data-st-root] [data-st-default-metric]')) st.metric = document.querySelector('[data-st-default-metric]').dataset.stDefaultMetric;
  setStDraw();
  initStFx();

  document.addEventListener('click', (e) => {
    const t = e.target;
    const go = t.closest('[data-st-go]');
    if (go) { const k = getStStep(go.dataset.stGo); if (k) setStMonth(k, go.dataset.stGo); return; }
    const mon = t.closest('[data-st-month]');
    if (mon) { setStMonth(mon.dataset.stMonth); return; }
    const met = t.closest('[data-st-metric]');
    if (met) { st.metric = met.dataset.stMetric; setStDraw(); return; }
    const rng = t.closest('[data-st-range]');
    if (rng) { st.range = rng.dataset.stRange; st.day = -1; setStDraw(); return; }
    const cmp = t.closest('[data-st-cmp]');
    if (cmp) { st.cmp = !st.cmp; setStDraw(); return; }
    if (t.closest('[data-st-day-clear]')) { st.day = -1; setStDraw(); return; }
    const now = t.closest('[data-st-now-set]');
    if (now) { now.closest('[data-st-scope="day"]').toggleAttribute('data-st-now', now.dataset.stNowSet === 'on'); st.day = -1; setStDraw(); return; }
    const day = t.closest('[data-st-day]');
    if (day) { st.day = st.day === Number(day.dataset.stDay) && !day.hasAttribute('data-st-keep') ? -1 : Number(day.dataset.stDay); setStDraw(); return; }
    const col = t.closest('.st-cols-pick .st-col');
    if (col) {
      const host = col.closest('[data-st-chart]');
      const d = host._st.days[Number(col.dataset.stI)];
      const i = getStMonth(st.month).days.indexOf(d);
      st.day = st.day === i ? -1 : i;
      setStDraw();
      return;
    }
    const tab = t.closest('[data-demo-tab]');
    if (tab) tab.parentElement.querySelectorAll('[data-demo-tab]').forEach((b) => b.classList.toggle('sl-is-active', b === tab));
    if (t.closest('[data-st-root] a[href="#"], .sl-tabs-link[href="#"]')) e.preventDefault();
  });
  // A period of the rhythm chart is a chart of its own: the legend names other series, so the chart is built again
  document.addEventListener('click', (e) => {
    const span = e.target.closest && e.target.closest('[data-st-rhythm] [data-sl-pres-period]');
    const host = span && span.closest('[data-st-rhythm]');
    if (!host || host._stPress) return;
    e.stopImmediatePropagation();
    host._stSpan = span.dataset.slPresPeriod;
    setStRhythm(host, getStScope(host));
  }, true);
  document.addEventListener('keydown', (e) => {
    const row = e.target.closest && e.target.closest('tr[data-st-day]');
    if (row && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); row.click(); }
  });
  document.addEventListener('change', (e) => {
    const sel = e.target.closest('[data-st-months]');
    if (sel) setStMonth(sel.value);
  });
  document.addEventListener('pointermove', (e) => {
    const cols = e.target.closest && e.target.closest('.st-cols');
    document.querySelectorAll('[data-st-chart]').forEach((host) => {
      if (!host._st) return;
      if (!cols || !host.contains(cols)) { if (host._stOn !== -1) { host._stOn = -1; setStMarks(host, -1); } return; }
      const box = cols.getBoundingClientRect();
      const i = Math.max(0, Math.min(host._st.series[0].length - 1, Math.floor((e.clientX - box.left) / box.width * host._st.series[0].length)));
      if (host._stOn !== i) { host._stOn = i; setStMarks(host, i); }
    });
  });
}

/* The motion layer. Nothing in it changes a figure: it times what the draw writes */
function isStStill() {
  return matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.dataset.demoMotion === 'off';
}

function getStParse(text) {
  const raw = String(text).replace(/[\s\u00a0\u202f%]/g, '').replace(',', '.');
  if (!/^-?\d+(\.\d+)?$/.test(raw)) return null;
  return { val: Number(raw), digits: (raw.split('.')[1] || '').length, unit: /%\s*$/.test(text) ? ' %' : '' };
}

/* A figure runs from the value it showed to the new one; a figure of a card not yet in view waits at zero */
function setStNum(el, text, tick) {
  const goal = getStParse(text);
  if (!goal || isStStill()) { el.textContent = text; el._stVal = goal ? goal.val : null; return; }
  const from = el._stVal ?? 0;
  el._stVal = goal.val;
  const card = el.closest('.st-card');
  if (card && !card.classList.contains('st-in')) { el.textContent = getStNum(0, goal.digits) + goal.unit; el._stWait = text; return; }
  if (tick && from !== goal.val) {
    el.classList.remove('st-tick');
    void el.offsetWidth;
    el.classList.add('st-tick');
  }
  setStCount(el, from, goal, text);
}

function setStCount(el, from, goal, text) {
  if (from === goal.val) { el.textContent = text; return; }
  const run = (el._stRun || 0) + 1;
  el._stRun = run;
  const start = performance.now();
  const step = (now) => {
    if (el._stRun !== run) return;
    const part = Math.min(1, (now - start) / 900);
    const ease = 1 - Math.pow(1 - part, 3);
    el.textContent = part < 1 ? getStNum(from + (goal.val - from) * ease, goal.digits) + goal.unit : text;
    if (part < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}

function setStArc(el) {
  const card = el.closest('.st-card');
  const p = card && !card.classList.contains('st-in') && !isStStill() ? 0 : el._stShare;
  el.querySelectorAll('.sl-knob-val').forEach((v) => v.setAttribute('stroke-dasharray', p + ' 100'));
  el.querySelectorAll('.sl-statx-rval').forEach((v) => v.setAttribute('stroke-dashoffset', (238.76 * (1 - p / 100)).toFixed(1)));
}

/* The cards of the root: its children, and the children of every part marked data-st-rise, each with its turn */
function setStCards() {
  const walk = (node, list) => [...node.children].forEach((kid) => {
    if (kid.tagName === 'TEMPLATE') return;
    if (kid.hasAttribute('data-st-rise')) walk(kid, list);
    else list.push(kid);
  });
  document.querySelectorAll('[data-st-root]').forEach((root) => {
    const list = [];
    walk(root, list);
    list.forEach((card, i) => {
      card.classList.add('st-card');
      card.style.setProperty('--st-i', i);
    });
  });
}

function setStReplay() {
  document.querySelectorAll('.st-card.st-in').forEach((card) => {
    card.classList.remove('st-play');
    void card.offsetWidth;
    card.classList.add('st-play');
  });
}

function setStOnce(card, cls) {
  card.classList.remove(cls);
  void card.offsetWidth;
  card.classList.add(cls);
  card.addEventListener('animationend', function done(e) {
    if (e.target !== card) return;
    card.classList.remove(cls);
    card.removeEventListener('animationend', done);
    // A panel of slaed.js opened while the card moved is placed again where the card stopped
    if (card.querySelector('.sl-float.sl-is-open')) window.dispatchEvent(new Event('resize'));
  });
}

function setStSwap(way) {
  if (isStStill()) return;
  document.querySelectorAll('.st-card.st-in').forEach((card) => setStOnce(card, 'st-swap-' + way));
}

/* A card that enters the view rises, then plays its figures, its columns and its arcs */
function setStEnter(card) {
  card.classList.add('st-in', 'st-play');
  setStOnce(card, 'st-rise');
  card.querySelectorAll('*').forEach((el) => {
    if (el._stWait === undefined) return;
    const text = el._stWait;
    delete el._stWait;
    setStCount(el, 0, getStParse(text), text);
  });
  requestAnimationFrame(() => card.querySelectorAll('[data-st-share], [data-st-arc]').forEach(setStArc));
}

/* The running day ticks: a few hits of the current hour, now and then a host, and the parts that show them follow */
function setStTick() {
  if (!document.querySelector('[data-st-t], [data-st-chart], [data-st-share], [data-st-clock]')) return;
  if (isStStill() || document.hidden || st.month !== ST_NOW.key || document.querySelector('[data-sl-live-box][data-sl-live-paused]')) return;
  const mon = getStMonth(ST_NOW.key);
  const d = mon.days[mon.days.length - 1];
  if (!d.live) return;
  const hr = ST_NOW.hour;
  const add = 1 + Math.floor(Math.random() * 4);
  d.h += add;
  d.hrs[hr] += add;
  d.num[hr] += add;
  d.gen[hr] += add * Math.round(70 + Math.random() * 25);
  d.sql[hr] += add * 20;
  d.mem[hr] += add * 1900;
  ['browsers:Chrome 156', 'os:Windows', 'devices:desktop', 'countries:ru', 'refcat:direct'].forEach((pair) => {
    const [k, n] = pair.split(':');
    d.dims[k][n] += add;
  });
  if (Math.random() < 0.5) {
    d.u += 1;
    d.aud += 1;
  }
  if (Math.random() < 0.08) d.usr += 1;
  st.all += add;
  document.querySelectorAll('[data-st-t]').forEach((el) => setStNum(el, getStText(getStScope(el), el.dataset.stT), true));
  document.querySelectorAll('[data-st-chart]').forEach((host) => {
    const sc = host._st;
    if (!sc) return;
    const col = sc.x === 'days' ? host.querySelector('.st-col-run') : sc.scope.agg.days.includes(d) ? host.querySelector('.st-col[data-st-i="' + hr + '"]') : null;
    if (!col) return;
    const v = sc.x === 'days' ? getStDayVal(d, sc.keys[0]) : sc.keys[0] === 'gen' ? null : getStAgg(sc.scope.agg.days).hrs[hr];
    if (v !== null) col.style.setProperty('--sl-d-v', Math.min(1, v / sc.tops[0]));
    const at = Number(col.dataset.stI);
    const ag = sc.x === 'hours' ? getStAgg(sc.scope.agg.days) : null;
    sc.keys.forEach((k, j) => { sc.series[j][at] = ag ? (k === 'gen' ? ag.genHour[at] : k === 'sql' ? (ag.num[at] ? ag.sql[at] / ag.num[at] : 0) : ag.hrs[at]) : getStDayVal(d, k); });
    col.querySelector(':scope > .sl-float-panel').innerHTML = getStHintHtml(getStColRows(sc, at));
    col.classList.remove('st-tick');
    void col.offsetWidth;
    col.classList.add('st-tick');
  });
  document.querySelectorAll('[data-st-share]').forEach((el) => setStShare(el, getStScope(el)));
  document.querySelectorAll('[data-st-clock]').forEach((host) => {
    const sc = getStScope(host);
    if (!sc.day || !sc.day.live) return;
    host.classList.add('st-still');
    setStClock(host, sc);
  });
  document.querySelectorAll('[data-sl-live-box]').forEach((box) => box.setAttribute('data-sl-live-stamp', Date.now()));
}

/* The clock of the stand: the line of the running day shows it, and a new hour opens a new column of the counter */
function setStSecond() {
  const now = getStClockNow();
  const hr = Number(now.slice(0, 2));
  if (hr > ST_NOW.hour) ST_NOW.hour = hr;
  document.querySelectorAll('[data-st-t="run"]').forEach((el) => { el.textContent = getStText(getStScope(el), 'run'); });
}

function initStFx() {
  document.documentElement.classList.add('st-fx');
  const io = new IntersectionObserver((rows) => rows.forEach((row) => {
    if (!row.isIntersecting) return;
    io.unobserve(row.target);
    setStEnter(row.target);
  }), { threshold: 0.06 });
  document.querySelectorAll('.st-card').forEach((card) => io.observe(card));
  setInterval(setStTick, 3200);
  setInterval(setStSecond, 1000);

  // The tile under the hand leans to it and carries a glare where the hand is, written once a frame
  let last = null;
  let queued = false;
  let held = null;
  const lean = () => {
    queued = false;
    const tile = last && !isStStill() && last.target.closest ? last.target.closest('[data-st-tilt]') : null;
    if (held && held !== tile) ['--st-rx', '--st-ry'].forEach((k) => held.style.removeProperty(k));
    held = tile;
    if (!tile) return;
    const box = tile.getBoundingClientRect();
    const x = (last.clientX - box.left) / box.width;
    const y = (last.clientY - box.top) / box.height;
    tile.style.setProperty('--st-mx', (x * 100).toFixed(1) + '%');
    tile.style.setProperty('--st-my', (y * 100).toFixed(1) + '%');
    tile.style.setProperty('--st-ry', ((x - 0.5) * 10).toFixed(2) + 'deg');
    tile.style.setProperty('--st-rx', ((0.5 - y) * 12).toFixed(2) + 'deg');
  };
  document.addEventListener('pointermove', (e) => {
    last = e;
    if (!queued) { queued = true; requestAnimationFrame(lean); }
  });

  // The floats of the parts stand close to each other: the one under the hand shuts its neighbours at once, without the
  // grace slaed.js gives a lone float to let the hand cross into its panel, so no two panels lie over each other
  document.addEventListener('pointerover', (e) => {
    const host = e.target.closest && e.target.closest('.sl-float');
    if (!host || !host.querySelector(':scope > .st-float')) return;
    document.querySelectorAll('.sl-float.sl-is-open').forEach((other) => {
      if (other === host || !other.querySelector(':scope > .st-float')) return;
      if (other.slFloatClose) window.clearTimeout(other.slFloatClose);
      other.slFloatClose = null;
      other.classList.remove('sl-float-left', 'sl-float-right', 'sl-float-up', 'sl-is-open');
    });
  });

  // The ripple of a press stays with the pointer in every mode: it is a touch, not a motion of the page
  const touch = '.st-root :is(.sl-but, .sl-seg-cell, [data-st-metric], button[data-st-day], [data-demo-tab], [data-st-tilt])';
  document.addEventListener('pointerdown', (e) => {
    const node = e.target.closest(touch);
    if (!node) return;
    const box = node.getBoundingClientRect();
    const wave = document.createElement('span');
    wave.className = 'st-ripple';
    wave.style.setProperty('--st-d', (Math.hypot(box.width, box.height) * 2).toFixed(0) + 'px');
    wave.style.left = (e.clientX - box.left) + 'px';
    wave.style.top = (e.clientY - box.top) + 'px';
    wave.addEventListener('animationend', () => wave.remove());
    node.appendChild(wave);
  });

  // The centre of a dial names the hour under the hand and returns to its own words when the hand leaves
  document.addEventListener('pointerover', (e) => {
    const spoke = e.target.closest && e.target.closest('.st-clock-spoke');
    const host = e.target.closest && e.target.closest('[data-st-clock]');
    const say = host && host.parentElement.querySelector('[data-st-clock-say]');
    if (!say) return;
    host.querySelectorAll('.st-clock-spoke').forEach((one) => one.classList.toggle('st-is-hand', one === spoke));
    say.hidden = !spoke;
    if (!spoke) return;
    const h = Number(spoke.dataset.stH);
    const c = host._stClock;
    say.innerHTML = `<span>${getStHourName(h)}</span><b>${getStNum(c.hrs[h])}</b><span>${c.load && c.gen[h] ? getStNum(c.gen[h]) + ' мс' : 'входов'}</span>`;
  });
  document.addEventListener('pointerout', (e) => {
    const host = e.target.closest && e.target.closest('[data-st-clock]');
    if (!host || host.contains(e.relatedTarget)) return;
    const say = host.parentElement.querySelector('[data-st-clock-say]');
    host.querySelectorAll('.st-is-hand').forEach((one) => one.classList.remove('st-is-hand'));
    if (say) say.hidden = true;
  });
}

initSt();
