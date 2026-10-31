/* Monitor series: admin.php?name=monitor redrawn on the engine of the statistic series (st.js draws the panel, the
   motion, the floats and the live chip; this file brings the monitor). The figures are the stand on 2026-10-27 as the
   live page showed them, every one from its function: getMonitorPanelSnapshot() (CPU, RAM, disk, the network and its
   history), getMonitorServerStats(), getMonitorRuntimeStats(), getMonitorDbStats(), getMonitorRequestStats(); the
   signals are those of the server and its programs, not the checks of SLAED that the lamp of the cockpit of the home
   keeps (owner, 2026-10-27). Three findings of the live page are drawn mended:
   the cookies are named and never shown, the time zones are zones and not the time, the security event is read into
   its time, level and message instead of the raw line of the log.

   A face places blocks and lists; every host is filled by its attribute:
   - data-mon-block="cockpit" — the plate: the four gauges, the lamp of the signals, the strip, the live chip, the state
   - data-mon-block="traffic" — a tile a series over the live chart of the network, CPU and RAM of the last minutes, a
     float and a guide on every point
   - data-mon-kv="<group>" — the list name · value of a group, one line a row: server, disk, cms, storage, folders,
     security, php, opcache, limits, db, request, software; data-mon-slice="from,to" or "from" shows a part of it
   - data-mon-live — the live chip of the theme; the window around it carries data-sl-live-box and pauses on its own
   - data-mon-signals — the checks of the lamp, the loud ones first, the passed ones folded into one line;
     data-mon-signals="all" lists the passed ones unfolded
   - data-mon-meter="<key>" — a meter used of limit: disk, opmem, pool, phpmem, upload
   - data-mon-store — the folders of storage as one split bar
   - data-mon-block="ext" — the extensions by their weight for SLAED: the required and the suggested ones framed in the
     tone of their state, then the other ones on and the ones off
   - data-mon-t="<key>" — one figure: cpu, ram, disk, hit, up, down, uptime, gen, sql, mem, online, loud, passed, state
   - data-demo-kinds on the root — the row "Тип" of the stand panel switches the signals: ok, warn (the stand), danger,
     and on the face with the pulse night, a calm server without visitors */

const MON = {
  cpu: 20, cores: '16 / 22', freq: '1,4 GHz',
  ram: 64, ramUsed: '20,14 GB', ramTotal: '31,47 GB', ramFree: '11,33 GB',
  disk: 31.5, diskUsed: '290,13 GB', diskTotal: '920,41 GB', diskFree: '630,28 GB',
  hit: 96.6, opUsed: 20.39, opTotal: 32, scripts: 385,
  up: 41.11, down: 37.66, upTotal: '3,46 GB', downTotal: '2,71 GB',
  uptime: '11 д 21 ч', gen: 74, sql: 38, mem: 2, online: 1, news: 97, files: 701, tables: 36,
};

const MON_KV = {
  server: [['Аптайм', '11 д 21 ч 23 мин'], ['ОС', 'Windows NT'], ['Веб-сервер', 'nginx/1.28.0'], ['HTTPS', 'on'], ['Протокол', 'HTTP/2.0'],
    ['Имя', 'slaed.loc'], ['IP сервера', '127.127.126.33'], ['Порт', '443'], ['Корень сайта', 'C:/OSPanel/home/slaed.loc/public/public', 'long']],
  disk: [['Свободно', '630,28 GB'], ['Чтение', 'нет данных', 'mute'], ['Запись', 'нет данных', 'mute'], ['Состояние', 'Норма', 'good']],
  cms: [['Сайт', 'on'], ['Статистика', 'off'], ['Переходы', 'on'], ['Рассылка', 'off'], ['Кеш', 'on'], ['Новости', String(MON.news)], ['Файлы', String(MON.files)]],
  storage: [['Последняя копия', 'сегодня, 07:38'], ['Резервные копии', '131,54 MB'], ['Кеш', '1,72 MB'], ['Журналы', '12,31 MB'], ['Загрузки', '1,6 GB']],
  folders: [['Загрузки', '1,6 GB'], ['Резервные копии', '131,54 MB'], ['Журналы', '12,31 MB'], ['Кеш', '1,72 MB']],
  security: [['Неудачные входы за сутки', '0'], ['Последнее событие', '14:20 · ошибка · 404 Not Found', 'long'], ['Ошибки базы за сутки', 'нет', 'good'], ['Журнал ошибок', '50 записей']],
  php: [['SAPI', 'cgi-fcgi'], ['Zend Engine', '4.4.27'], ['Кодировка', 'UTF-8'], ['Часовой пояс', 'Europe/Berlin'], ['GD', 'bundled (2.1.0 compatible)', 'long'], ['GZip', 'on'], ['Zip', 'on'], ['BZip2', 'on']],
  opcache: [['OPcache', 'on'], ['Скриптов в кеше', '385'], ['Попадания', '96,6%']],
  limits: [['Лимит памяти', '1024M'], ['Память скрипта', '2 MB'], ['Время выполнения', '300 с'], ['POST', '256M'], ['Файл', '256M'], ['Загрузка файлов', 'on'], ['Файлов за раз', '20'], ['Полей ввода', '10000']],
  db: [['Сервер', 'MariaDB 11.7.2'], ['Кодировка', 'utf8mb4'], ['Часовой пояс', 'SYSTEM (+01:00)'], ['Макс. пакет', '16 MB'],
    ['Подключений', '2'], ['Размер базы', '51,66 MB'], ['Таблицы', String(MON.tables)], ['База', 'slaed'], ['Пользователь', 'root@%'],
    ['SQL mode', 'ONLY_FULL_GROUP_BY, STRICT_TRANS_TABLES, NO_ZERO_IN_DATE, NO_ZERO_DATE, ERROR_FOR_DIVISION_BY_ZERO, NO_ENGINE_SUBSTITUTION', 'long']],
  request: [['IP клиента', '127.0.0.1'], ['Адрес', '/admin.php?name=monitor', 'long'], ['Строка запроса', 'name=monitor'], ['Время', '74 ms'], ['Запросов к базе', '38'], ['Время базы', '19,5 ms'],
    ['Язык браузера', 'нет данных', 'mute'], ['Куки', '3: slaed-language, PHPSESSID, slaed-stats — значения скрыты', 'long'],
    ['Браузер', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0 Safari/537.36', 'long']],
  software: [['SLAED CMS', '6.3.0 Phoenix', 'window-stack'], ['PHP', '8.4.27', 'filetype-php'], ['MariaDB', '11.7.2', 'database'], ['Nginx', '1.28.0', 'hdd-rack']],
};

/* The extensions SLAED asks of PHP, by the code: required are those of composer.json and those it calls with no way
   round (ctype, filter_var(), session_start() of core/security.php); recommended are those whose absence turns a part
   off, each with what it costs - the suggest of composer.json, getImageThumb() for gd, the callers of curl_init(),
   feed.php for dom, idn_to_ascii() for intl, oauth.php and the POP3 of mail.php for openssl, getZipReader() for Phar,
   opcache_reset() of apply.php. APCu is not among them: no code calls it since the store of the monitor became a file
   on 2026-10-21. A one is missing when the stand has it not loaded or the scene takes it away: a failure takes gd and
   sodium */
const MON_NEED = {
  must: ['ctype', 'curl', 'filter', 'gd', 'json', 'mbstring', 'PDO', 'pdo_mysql', 'session'],
  tip: ['dom', 'fileinfo', 'intl', 'OPcache', 'openssl', 'Phar', 'sodium', 'zip', 'zlib'],
};
const MON_COST = {
  gd: 'картинки не уменьшаются: на месте миниатюры остаётся исходный файл',
  curl: 'не работают вход через OAuth, чтение RSS-лент и загрузка файла по ссылке',
  dom: 'не работает чтение RSS-лент',
  intl: 'адрес с кириллическим доменом отбрасывается в почте, лентах и обновлении',
  OPcache: 'PHP компилирует каждый файл на каждом запросе, панель и сайт заметно медленнее',
  openssl: 'не работают вход через OAuth и почта POP3 по TLS',
  Phar: 'без zip пакет обновления нечем открыть',
  fileinfo: 'тип загруженного файла определяют только собственные проверки, без базы сигнатур',
  sodium: 'подпись обновления проверяется на чистом PHP, около десятой доли секунды на проверку',
  zip: 'загруженный zip-архив проверяется только разбором его каталога',
  zlib: 'сжатое gzip содержимое загрузки не сверяется с его контрольной суммой',
};
const MON_LOST = { danger: ['gd', 'sodium'] };
const MON_EXT = {
  on: 'Core bcmath calendar ctype curl date dom exif fileinfo filter gd gettext hash iconv intl json libxml mbstring mysqli mysqlnd openssl pcre PDO pdo_mysql Phar random readline Reflection session SimpleXML sodium SPL standard tokenizer xml xmlreader xmlwriter zip zlib Zend OPcache'.split(' '),
  off: 'amqp apcu apfd ast brotli com_dotnet crypto dba dbase decimal dio ds event ffi geoip gmp http igbinary imagick imap ldap memcache memcached mongodb msgpack oauth pgsql redis snmp soap sockets sqlsrv ssh2 swoole tidy uuid xdebug yaml'.split(' '),
};

/* The thirteen signals of the server and its programs, by gravity, the source of each named as admin/modules/monitor.php
   read it on 2026-10-27; three need a reading the monitor has not yet: the limit max_connections, the end of support of a PHP
   version and the age of MariaDB and nginx. The thresholds: CPU and RAM 75/90%, disk 80/95%, OPcache under 80% of hits or
   off, the connections near max_connections, the InnoDB buffer smaller than the base, the peak of a script near
   memory_limit, a page over 0.5/2 s, PHP out of its support, an old MariaDB or nginx, no HTTP/2 or no HTTPS, a needed
   extension missing, a reboot within the hour. A scene of the stand sets the level of each */
const MON_SIGNALS = [
  ['cpu', 'cpu', 'Процессор', '20% загрузка', 'getMonitorPanelSnapshot()'],
  ['ram', 'memory', 'Оперативная память', '64% занято', 'getMonitorPanelSnapshot()'],
  ['disk', 'hdd', 'Место на диске', '31,5% занято', 'getMonitorPanelSnapshot()'],
  ['opcache', 'lightning-charge', 'OPcache', '96,6% попаданий, 20,39 / 32 MB', 'getMonitorRuntimeStats()'],
  ['conn', 'diagram-3', 'Подключения к базе', '2 из 151', 'getMonitorRuntimeStats(), dbhealth; предел max_connections — новое чтение'],
  ['innodb', 'database', 'Буфер InnoDB', '160 MB при базе 51,66 MB', 'getMonitorRuntimeStats(), dbhealth; getMonitorDbStats(), dbsize'],
  ['phpmem', 'speedometer2', 'Память PHP', '2 MB из 1024M', 'memory_get_peak_usage(), memory_limit'],
  ['gen', 'stopwatch', 'Генерация страницы', '74 ms', 'getMonitorRuntimeStats(), exectime'],
  ['php', 'filetype-php', 'Версия PHP', '8.4.27, поддержка до 2028', 'PHP_VERSION; сроки поддержки — новая таблица'],
  ['soft', 'hdd-rack', 'MariaDB и nginx', 'MariaDB 11.7.2 · nginx 1.28.0', 'getDbVersion(), getMonitorServerStats(); «устарел» — новая таблица'],
  ['proto', 'lock', 'HTTPS и HTTP/2', 'включены', 'getMonitorServerStats()'],
  ['ext', 'puzzle', 'Расширения PHP', 'все нужные включены', 'getMonitorServerStats(), extlist_on'],
  ['uptime', 'clock-history', 'Аптайм', '11 д 21 ч без перезагрузки', 'getMonitorRuntimeStats()'],
];
const MON_SCENES = {
  ok: {},
  warn: { disk: ['warn', '82% занято'], soft: ['warn', 'nginx 1.18.0 устарел'] },
  danger: { cpu: ['danger', '96% загрузка'], phpmem: ['danger', '1019 MB из 1024M'], opcache: ['danger', 'выключен'], disk: ['warn', '82% занято'],
    uptime: ['warn', 'перезагрузка 14 мин назад'], ext: ['danger', 'нет gd и sodium'] },
};
const mon = { scene: 'warn', hist: { up: [], down: [], cpu: [], ram: [] }, gone: {}, step: 20 };

function getMonPoints() {
  const r = (s) => { let a = s; return () => { a = (a * 9301 + 49297) % 233280; return a / 233280; }; };
  const rnd = r(7);
  for (let i = 0; i < 60; i++) {
    const wave = Math.sin(i / 6) * 0.5 + Math.sin(i / 2.3) * 0.3;
    mon.hist.cpu.push(Math.max(3, Math.min(96, 20 + wave * 14 + (rnd() - 0.5) * 18 + (i % 17 === 5 ? 38 : 0))));
    mon.hist.ram.push(Math.max(40, Math.min(90, 62 + Math.sin(i / 11) * 3 + (rnd() - 0.5) * 2)));
    mon.hist.up.push(Math.max(1, 40 + wave * 22 + (rnd() - 0.5) * 30 + (i % 23 === 9 ? 160 : 0)));
    mon.hist.down.push(Math.max(1, 36 + Math.cos(i / 5) * 16 + (rnd() - 0.5) * 24 + (i % 19 === 12 ? 120 : 0)));
  }
}

function getMonSignals() {
  const scene = MON_SCENES[mon.scene] || {};
  return MON_SIGNALS.map(([key, icon, label, fact, src]) => {
    const [level, said] = scene[key] || ['ok', fact];
    return { key, icon, label, fact: said, src, level };
  });
}

function getMonState() {
  const sig = getMonSignals();
  return sig.some((s) => s.level === 'danger') ? 'danger' : sig.some((s) => s.level === 'warn') ? 'warn' : 'ok';
}

function getMonTone(key, p) {
  if (key === 'opcache') return p < 80 ? 'warn' : 'ok';
  return p >= 90 ? 'danger' : p >= 75 ? 'warn' : key === 'disk' ? 'info' : 'ok';
}

function getMonValue(key) {
  return { cpu: MON.cpu, ram: MON.ram, disk: MON.disk, opcache: MON.hit }[key];
}

function getMonRows(key) {
  if (key === 'cpu') return [['cpu', 'Ядра: физические / логические', MON.cores], ['speedometer2', 'Частота', MON.freq], ['activity', 'Загрузка сейчас', Math.round(MON.cpu) + '%']];
  if (key === 'ram') return [['memory', 'Занято', MON.ramUsed], ['box', 'Всего', MON.ramTotal], ['check2-circle', 'Доступно', MON.ramFree]];
  if (key === 'disk') return [['hdd', 'Раздел', '/'], ['pie-chart', 'Занято', MON.diskUsed], ['box', 'Всего', MON.diskTotal], ['check2-circle', 'Свободно', MON.diskFree]];
  return [['bullseye', 'Попадания', getStNum(MON.hit, 1) + '%'], ['memory', 'Память', getStNum(MON.opUsed, 2) + ' / ' + MON.opTotal + ' MB'], ['file-earmark-code', 'Скриптов', String(MON.scripts)]];
}

const MON_GAUGES = [['cpu', 'CPU'], ['ram', 'RAM'], ['disk', 'Диск'], ['opcache', 'OPcache']];
const MON_ARC = '<svg class="sl-knob-svg" viewBox="0 0 44 44" aria-hidden="true"><path class="sl-knob-bg" d="M7.858 36.142 A20 20 0 1 1 36.142 36.142"></path><path class="sl-knob-val" d="M7.858 36.142 A20 20 0 1 1 36.142 36.142" pathLength="100" stroke-dasharray="0 100"></path><path d="M10.7 33.3 11.8 32.2 M7.2 28.1 8.7 27.5 M6 22 H7.6 M7.2 15.9 8.7 16.5 M10.7 10.7 11.8 11.8 M15.9 7.2 16.5 8.7 M22 6 V7.6 M28.1 7.2 27.5 8.7 M33.3 10.7 32.2 11.8 M36.8 15.9 35.3 16.5 M38 22 H36.4 M36.8 28.1 35.3 27.5 M33.3 33.3 32.2 32.2"></path></svg>';

/* Пульс сервера: the lamp of a cockpit whose host carries data-mon-pulse shows the heart of the server in place of the
   radar and the mark. The heart beats at the requests the server answered in the last minute and quickens with the load
   of the processor, a trace of a cardiograph runs under the rate and the mood stands under it, all in the tone of the
   state of the server. A beat of the trace is 50 units on the line at 24, a racing one 25; a sweep is 200 units, the trace
   holds two and slides by one in the time of its beats. A warning drops a beat and slips in an early one, a failure
   races with higher spikes, a night without visitors is a flat line. The system has no rate per minute yet: the load
   journal of the statistic keeps hours, so the rate needs a counter of the minute beside the sample of the one writer
   (storage/logs/monitor.json), brought by the same poll as the gauges */
const MON_BEAT = {
  calm: 'h14l3-3l3 3h4l2 3l3-19l3 22l2-6h5q4-6 8 0h3',
  early: 'h4l3-3l3 3h2l2 2l2-12l2 14l2-4h4q3-4 6 0h20',
  race: 'h4l2 2l2-26l2 30l2-6h3q3-5 6 0h4',
  flat: 'h50',
};

/* The word of a lamp by its tone: the words of the line of state, partials/cockpit.html of the theme */
const MON_MOOD = { ok: 'Стабильное состояние', info: 'Затишье', warn: 'Требует внимания', danger: 'Сбой' };

const MON_PULSE = {
  ok: { bpm: 72, mood: MON_MOOD.ok, beats: ['calm', 'calm', 'calm', 'calm'] },
  warn: { bpm: 96, mood: MON_MOOD.warn, beats: ['calm', 'flat', 'early', 'calm'] },
  danger: { bpm: 168, mood: MON_MOOD.danger, beats: ['race', 'race', 'race', 'race', 'race', 'race', 'race', 'race'] },
  night: { bpm: 0, mood: MON_MOOD.info, beats: ['flat', 'flat', 'flat', 'flat'] },
};
const MON_HEART = '<span class="sl-cockpit-head mn-bpm" aria-hidden="true"><i class="bi bi-heart-pulse-fill"></i><b data-mon-bpm></b><small>уд/мин</small></span>'
  + '<span class="mn-ecg" aria-hidden="true"><svg viewBox="0 0 400 40" preserveAspectRatio="none"><path data-mon-trace d="M0 24H400"></path></svg></span>'
  + '<span class="sl-cockpit-mood" aria-hidden="true" data-mon-mood></span>';

/* The rhythm of the scene: its trace, mood and sweep, set once per scene so a drifting rate never jerks the trace */
function setMonPulse(box) {
  const lamp = box.querySelector('.mn-pulse');
  const mode = MON_PULSE[mon.scene] ? mon.scene : getMonState();
  if (!lamp) return;
  const now = MON_PULSE[mode];
  const sweep = now.beats.map((b) => MON_BEAT[b]).join('');
  lamp.dataset.monMode = mode;
  lamp.querySelector('[data-mon-trace]').setAttribute('d', 'M0 24' + sweep + sweep);
  lamp.querySelector('[data-mon-mood]').textContent = now.mood;
  lamp.toggleAttribute('data-mon-flat', !now.bpm);
  if (now.bpm) lamp.style.setProperty('--sl-d-sweep', (now.beats.length * 60 / now.bpm).toFixed(3) + 's');
  setMonPulseRate(lamp, now.bpm);
}

/* The rate a poll brings: the figure, the beat of the heart and the name a screen reader hears with the state */
function setMonPulseRate(lamp, bpm) {
  const kind = new Intl.PluralRules('ru').select(bpm);
  const word = kind === 'one' ? 'запрос' : kind === 'few' ? 'запроса' : 'запросов';
  lamp.querySelector('[data-mon-bpm]').textContent = bpm;
  if (bpm) lamp.style.setProperty('--sl-d-beat', (60 / bpm).toFixed(3) + 's');
  lamp.setAttribute('aria-label', 'Пульс сервера: ' + bpm + ' ' + word + ' в минуту, ' + MON_PULSE[lamp.dataset.monMode].mood.toLowerCase() + '. ' + getMonText('state'));
}

/* Each poll the rate follows the processor round the rate of its scene, the stand's 20% giving the rate itself; a night stays at zero */
function setMonPulseTick(live) {
  document.querySelectorAll('.mn-pulse').forEach((lamp) => {
    if (live.some((box) => box.contains(lamp))) setMonPulseRate(lamp, Math.round(MON_PULSE[lamp.dataset.monMode].bpm * (0.8 + MON.cpu / 100)));
  });
}

/* The live chip of the theme (fragments/live-chip.html): the corner of every live box of the monitor */
function getMonLive() {
  return `<div class="sl-tip sl-float sl-live-chip" tabindex="0"><span class="sl-live-icon"><i class="bi bi-activity sl-live-s-on" aria-hidden="true"></i><i class="bi bi-pause-circle-fill sl-live-s-paused" aria-hidden="true"></i></span><div class="sl-float-panel sl-live-panel"><dl><dt>Статус</dt><dd><span class="sl-live-s-on">В эфире</span><span class="sl-live-s-paused">На паузе</span></dd><dt>Интервал</dt><dd>4 сек</dd><dt>Обновлено</dt><dd><span data-sl-live-ago data-sl-live-unit="сек">&mdash;</span></dd></dl><span class="sl-live-action"><button type="button" class="sl-chip sl-chip-warn sl-live-act" data-sl-live-toggle><span class="sl-live-s-on"><i class="bi bi-pause-fill" aria-hidden="true"></i> Пауза</span><span class="sl-live-s-paused"><i class="bi bi-play-fill" aria-hidden="true"></i> Продолжить</span></button></span></div></div>`;
}

/* The plate of the cockpit: the markup of partials/cockpit.html of the theme, the gauges and the lamp filled here */
function getMonCockpit(el) {
  const gauge = ([key, name]) => `<span class="sl-float sl-cockpit-float"><span class="sl-cockpit-ring sl-knob" tabindex="0" role="meter" aria-label="${name}" aria-valuemin="0" aria-valuemax="100" data-mon-gauge="${key}" data-st-arc>${MON_ARC}<span><b><span data-mon-v></span><small>%</small></b><span>${name}</span></span></span><span class="sl-float-panel sl-bell-tip" role="group"><span class="sl-bell-cap">${name}</span><span data-mon-rows></span></span></span>`;
  const heart = el.hasAttribute('data-mon-pulse');
  const core = heart ? MON_HEART : `<span class="sl-cockpit-head" aria-hidden="true"><span class="sl-cockpit-mark"></span><b data-mon-pass></b><small>/${MON_SIGNALS.length}</small></span>`
    + '<span class="sl-cockpit-bars" aria-hidden="true" data-mon-bars></span><span class="sl-cockpit-mood" aria-hidden="true" data-mon-mood></span>';
  const lamp = `<span class="sl-float sl-cockpit-float"><button type="button" class="sl-cockpit-plate${heart ? ' mn-pulse' : ''}" aria-label="Состояние сервера"><span class="sl-cockpit-glass" aria-hidden="true"></span>${core}</button><span class="sl-float-panel sl-bell-tip" role="group"><span class="sl-bell-cap" data-mon-t="state-cap"></span><span data-mon-loud></span></span></span>`;
  const strip = [['clock-history', 'uptime', '', 'аптайм'], ['stopwatch', 'gen', 'ms', 'генерация'], ['database', 'sql', 'sql', 'запросов'], ['memory', 'mem', 'MB', 'память PHP'], ['people', 'online', '', 'онлайн']]
    .map(([icon, key, unit, name]) => `<li><i class="bi bi-${icon}" aria-hidden="true"></i><b data-mon-t="${key}"></b>${unit ? ' ' + unit : ''} <span>${name}</span></li>`).join('');
  el.innerHTML = `<section class="sl-cockpit mn-cockpit" aria-labelledby="mn-cap" data-sl-live-box>
    <div class="sl-cockpit-top">
      <span id="mn-cap"><i class="bi bi-display" aria-hidden="true"></i>Состояние сервера</span>
      <span class="sl-cockpit-state" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true" data-mon-state-icon></i><span data-mon-t="state"></span></span>
      <div class="mn-nav">${el.dataset.monNav === 'off' ? '' : '<a class="mn-nav-btn" href="#mn-signals" title="Проверки" aria-label="Проверки"><i class="bi bi-list-check" aria-hidden="true"></i></a>'}${getMonLive()}</div>
    </div>
    <div class="sl-cockpit-gauges">${gauge(MON_GAUGES[0])}${gauge(MON_GAUGES[1])}${lamp}${gauge(MON_GAUGES[2])}${gauge(MON_GAUGES[3])}</div>
    <ul class="sl-cockpit-strip">${strip}</ul>
  </section>`;
}

function setMonGauges(on) {
  document.querySelectorAll('[data-mon-gauge]').forEach((ring) => {
    if (!on(ring)) return;
    const key = ring.dataset.monGauge;
    const p = getMonValue(key);
    ring.classList.remove('sl-knob-ok', 'sl-knob-info', 'sl-knob-warn', 'sl-knob-danger');
    ring.classList.add('sl-knob-' + getMonTone(key, p));
    ring._stShare = Math.round(p);
    setStArc(ring);
    ring.setAttribute('aria-valuenow', Math.round(p));
    ring.querySelectorAll('[data-mon-v]').forEach((v) => setStNum(v, String(Math.round(p)), true));
    const rows = ring.parentElement.querySelector('[data-mon-rows]');
    if (rows) rows.innerHTML = getMonRows(key).map(([icon, name, num]) => `<a><i class="bi bi-${icon}" aria-hidden="true"></i> <span>${name}</span> <span class="sl-bell-num">${num}</span></a>`).join('');
  });
}

/* The icon of the line of state by its tone, as partials/cockpit.html of the theme marks it */
const MON_STATE_ICON = { ok: 'check-circle-fill', info: 'dash-circle-fill', warn: 'exclamation-triangle-fill', danger: 'x-octagon-fill' };

/* The tone of the plate: the state of the signals, and a calm server with a flat pulse — no request in the minute, the
   site empty, at any hour — is the lull in the blue of the theme */
function getMonLampTone() {
  const state = getMonState();
  return state === 'ok' && MON_PULSE[mon.scene] && !MON_PULSE[mon.scene].bpm ? 'info' : state;
}

/* The tone, the line of state and the panel of the lamp; a lamp without the pulse shows the rows of the home lamp as the
   panel draws them: the bars in the order of the first draw, failures first, each named under the hand, a change of level
   and of the passed checks flaring once, the count in the name a screen reader hears */
function setMonLamp() {
  const state = getMonLampTone();
  const sig = getMonSignals();
  const loud = sig.filter((s) => s.level !== 'ok');
  const rank = { danger: 0, warn: 1, ok: 2 };
  const pass = String(sig.length - loud.length);
  const fresh = (node) => {
    node.classList.remove('sl-is-fresh');
    void node.offsetWidth;
    node.classList.add('sl-is-fresh');
  };
  document.querySelectorAll('.mn-cockpit').forEach((box) => {
    box.classList.remove('sl-knob-ok', 'sl-knob-info', 'sl-knob-warn', 'sl-knob-danger');
    box.classList.add('sl-knob-' + state);
    box.querySelector('[data-mon-state-icon]').className = 'bi bi-' + MON_STATE_ICON[state];
    setMonPulse(box);
    const bars = box.querySelector('[data-mon-bars]');
    if (bars) {
      if (!bars.children.length) bars.innerHTML = sig.slice().sort((x, y) => rank[x.level] - rank[y.level]).map((one, i) => `<i data-mon-bar="${one.key}" style="--sl-d-i: ${i}"></i>`).join('');
      sig.forEach((one) => {
        const bar = bars.querySelector(`[data-mon-bar="${one.key}"]`);
        bar.title = one.label + (one.fact ? ' · ' + one.fact : '');
        if (bar.classList.contains('sl-knob-' + one.level)) return;
        const was = bar.className;
        bar.className = 'sl-knob-' + one.level;
        if (was) fresh(bar);
      });
      const num = box.querySelector('[data-mon-pass]');
      if (num.textContent && num.textContent !== pass) fresh(num);
      num.textContent = pass;
      box.querySelector('[data-mon-mood]').textContent = MON_MOOD[state];
      box.querySelector('.sl-cockpit-plate').setAttribute('aria-label', 'Состояние сервера: ' + MON_MOOD[state].toLowerCase() + ', пройдено ' + pass + ' из ' + sig.length);
    }
    const list = box.querySelector('[data-mon-loud]');
    list.innerHTML = loud.length ? loud.sort((a, b) => (a.level === 'danger' ? -1 : 1) - (b.level === 'danger' ? -1 : 1)).map((s) => `<a class="sl-knob-${s.level}"><i class="bi bi-${s.level === 'danger' ? 'x-octagon-fill' : 'exclamation-triangle-fill'}" aria-hidden="true"></i> ${s.label} <span class="sl-bell-num">${s.fact}</span></a>`).join('') : '';
  });
}

function getMonText(key) {
  const sig = getMonSignals();
  const loud = sig.filter((s) => s.level !== 'ok').length;
  const map = {
    cpu: Math.round(MON.cpu), ram: Math.round(MON.ram), disk: MON.disk, hit: MON.hit, up: getStNum(MON.up, 1), down: getStNum(MON.down, 1),
    uptime: MON.uptime, gen: MON.gen, sql: MON.sql, mem: MON.mem, online: MON.online,
    loud, passed: sig.length - loud, total: sig.length,
    state: loud ? (getMonState() === 'danger' ? 'Сбой' : 'Требует внимания') + ' · ' + loud : (getMonLampTone() === 'info' ? 'Затишье' : 'Стабильное состояние'),
    'state-cap': loud ? 'Требует внимания' : 'Все ' + sig.length + ' проверок пройдены',
  };
  return map[key] === undefined ? '' : String(map[key]);
}

/* A list name · value: on and off are lamps with a word, a long value ends in an ellipsis and opens whole in a float */
function setMonKv(el) {
  const [from, to] = (el.dataset.monSlice || '0').split(',').map(Number);
  const rows = (MON_KV[el.dataset.monKv] || []).slice(from, to || undefined);
  el.style.flexGrow = rows.length;
  el.innerHTML = rows.map(([name, value, kind]) => {
    if (el.dataset.monKv === 'software') return `<div class="mn-kv mn-kv-soft"><span><i class="bi bi-${kind}" aria-hidden="true"></i>${name}</span><b>${value}</b></div>`;
    const v = value === 'on' ? '<span class="mn-flag mn-flag-on">Вкл</span>' : value === 'off' ? '<span class="mn-flag">Выкл</span>'
      : kind === 'long' ? `<span class="mn-long"><span class="sl-float sl-tip"><button type="button" aria-label="Подробности: ${name}"><i class="bi bi-info-circle-fill" aria-hidden="true"></i></button><span class="sl-float-panel mn-long-panel" role="tooltip">${getStEsc(value)}</span></span><span class="mn-long-text">${getStEsc(value)}</span></span>`
        : `<span class="${kind ? 'mn-' + kind : ''}">${getStEsc(value)}</span>`;
    return `<div class="mn-kv"><span>${name}</span><b>${v}</b></div>`;
  }).join('');
}

/* The checks of the lamp; a figure keeps its unit on its line when a long fact wraps */
function setMonSignals(el) {
  const sig = getMonSignals();
  const loud = sig.filter((s) => s.level !== 'ok').sort((a, b) => (a.level === 'danger' ? 0 : 1) - (b.level === 'danger' ? 0 : 1));
  const calm = sig.filter((s) => s.level === 'ok');
  const row = (s) => `<div class="mn-sig sl-float sl-knob-${s.level}" tabindex="0"><i class="mn-sig-lamp" aria-hidden="true"></i><i class="bi bi-${s.icon}" aria-hidden="true"></i><span>${s.label}</span><b>${s.fact.replace(/(\d) (?=\S)/g, '$1&nbsp;').replace(/\/ /g, '/&nbsp;')}</b><span class="sl-float-panel st-float" role="tooltip"><span class="sl-bell-cap">${s.label}</span><span class="st-tip-row"><i></i>Источник<b class="sl-bell-num">${s.src}</b></span></span></div>`;
  const fold = el.dataset.monSignals === 'all' ? calm.map(row).join('') : `<details class="mn-sig-calm"><summary><i class="bi bi-check2-circle" aria-hidden="true"></i>Пройдено проверок: ${calm.length}</summary>${calm.map(row).join('')}</details>`;
  el.innerHTML = loud.map(row).join('') + fold;
}

function setMonMeter(el) {
  const m = {
    disk: ['Диск', MON.disk, MON.diskUsed + ' из ' + MON.diskTotal], opmem: ['Память OPcache', MON.opUsed / MON.opTotal * 100, getStNum(MON.opUsed, 2) + ' из ' + MON.opTotal + ' MB'],
    pool: ['Буфер InnoDB', 51.66 / 160 * 100, 'база 51,66 MB в буфере 160 MB'], phpmem: ['Память PHP', 2 / 1024 * 100, 'пик 2 MB из 1024 MB'],
    upload: ['Загрузка файла', 256 / 256 * 100, 'POST и файл по 256 MB'],
  }[el.dataset.monMeter];
  const p = Math.max(1, Math.min(100, m[1]));
  el.innerHTML = `<div class="mn-meter-head"><span>${m[0]}</span><b>${getStNum(m[1], m[1] < 10 ? 1 : 0)}%</b></div><span class="mn-meter-bar sl-knob-${getMonTone('disk', p)}"><i style="--sl-d-pct:${p}%"></i></span><span class="mn-meter-note">${m[2]}</span>`;
}

function setMonStore(el) {
  const parts = [['Загрузки', 1638.4], ['Резервные копии', 131.54], ['Журналы', 12.31], ['Кеш', 1.72]];
  const sum = parts.reduce((x, p) => x + p[1], 0);
  const size = (mb) => (mb >= 1024 ? getStNum(mb / 1024, 1) + ' GB' : getStNum(mb, mb < 10 ? 2 : 0) + ' MB');
  el.innerHTML = `<span class="st-stack-bar" role="img" aria-label="Хранилище">${parts.map(([name, mb], i) => `<span class="st-stack-part sl-float st-k-${i + 1}" style="--sl-d-pct:${Math.max(1.5, mb / sum * 100)}%"><span class="sl-float-panel st-float" role="tooltip">${getStHintHtml({ c: 'Хранилище', r: [[name, size(mb), 'k-' + (i + 1)]] })}</span></span>`).join('')}</span>` +
    `<span class="st-stack-keys">${parts.map(([name, mb], i) => `<span class="st-stack-key st-k-${i + 1}"><i></i>${name} <b>${size(mb)}</b></span>`).join('')}</span>`;
}

function setMonExt(el) {
  const lost = MON_LOST[mon.scene] || [];
  const miss = (n) => lost.includes(n) || !MON_EXT.on.includes(n);
  const need = [...MON_NEED.must, ...MON_NEED.tip];
  const chip = (n, cls) => `<span class="mn-chip${cls}">${n}</span>`;
  const rest = (key, cls) => MON_EXT[key].filter((n) => !need.includes(n)).map((n) => chip(n, cls)).join('');
  const group = (cap, list, foot = '') => `<div><p class="mn-sub">${cap}</p><div class="mn-chips">${list}</div>${foot}</div>`;
  // A needed one says its state three ways: the frame, the mark in it and the count in the caption; a missing one adds what it costs
  const mark = (cap, list) => {
    const have = list.filter((n) => !miss(n)).length;
    const chips = list.map((n) => miss(n)
      ? `<span class="mn-chip mn-chip-lost"><i class="bi bi-x-circle-fill" aria-hidden="true"></i>${n}<span class="sl-visually-hidden"> — нет</span></span>`
      : `<span class="mn-chip mn-chip-ok"><i class="bi bi-check-circle-fill" aria-hidden="true"></i>${n}<span class="sl-visually-hidden"> — есть</span></span>`).join('');
    const foot = list.filter(miss).map((n) => `<p class="mn-ext-lost"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span><b>Нет ${n}:</b> ${MON_COST[n] || 'SLAED без него не запускается'}</span></p>`).join('');
    return group(`${cap} · <span class="${have < list.length ? 'mn-ext-short' : 'mn-good'}">есть ${have} из ${list.length}</span>`, chips, foot);
  };
  el.innerHTML = `<div class="mn-two">${mark('Обязательные для SLAED', MON_NEED.must)}${mark('Рекомендуемые', MON_NEED.tip)}</div>`
    + `<div class="mn-two">${group('Другие включённые', rest('on', ''))}${group('Выключены', rest('off', ' mn-chip-off'))}</div>`;
}

/* The series of the live chart: key, name, unit, decimals, and the icon the live page gives it */
const MON_SERIES = [['up', 'Исходящий', 'KB/s', 1, 'cloud-upload-fill'], ['down', 'Входящий', 'KB/s', 1, 'cloud-download-fill'],
  ['cpu', 'CPU', '%', 0, 'cpu-fill'], ['ram', 'RAM', '%', 0, 'motherboard-fill']];

/* The live chart: the theme's chart of the monitor (sl-chart-*), CPU and RAM in percent, the network on its own scale;
   over it a tile a series, the figure of now with the peak and the mean of the window, the dot of the series its key.
   A tick keeps the svg, so the wipe of the entry plays once: the lines get the new points with the dropped one
   left of the edge, and the group glides one step to the left */
function setMonChart(el, tick) {
  const H = mon.hist;
  const n = H.cpu.length;
  const W = 1000;
  const T = 200;
  const S = W / (n - 1);
  const netMax = Math.max(1, ...H.up, ...H.down) * 1.1;
  const pt = (vals, max, k) => (tick && mon.gone[k] !== undefined ? [mon.gone[k], ...vals] : vals)
    .map((v, i, all) => [(i - all.length + n) * S, T - v / max * (T - 10)]);
  const line = (pts) => pts.map((p, i) => {
    if (!i) return 'M' + p[0].toFixed(1) + ' ' + p[1].toFixed(1);
    const q = pts[i - 1];
    const mid = (q[0] + p[0]) / 2;
    return `C${mid.toFixed(1)} ${q[1].toFixed(1)} ${mid.toFixed(1)} ${p[1].toFixed(1)} ${p[0].toFixed(1)} ${p[1].toFixed(1)}`;
  }).join(' ');
  const area = (pts) => line(pts) + ` L${W} ${T} L${pts[0][0].toFixed(1)} ${T} Z`;
  const ser = [['up', H.up, netMax], ['down', H.down, netMax], ['cpu', H.cpu, 100], ['ram', H.ram, 100]];
  const ago = (i) => { const s = (n - 1 - i) * mon.step; return s ? (s >= 60 ? Math.round(s / 60) + ' мин назад' : s + ' с назад') : 'сейчас'; };
  const dots = (i) => ser.map(([k, v, m]) => `<i class="mn-chart-dot mn-${k}" style="--sl-d-y:${(v[i] / m * (T - 10) / T).toFixed(3)}"></i>`).join('');
  const zones = H.cpu.map((_, i) => `<span class="st-zone sl-float" style="left:${(i - 0.5) / (n - 1) * 100}%;width:${100 / (n - 1)}%">${dots(i)}<span class="sl-float-panel st-float" role="tooltip">${getStHintHtml({ c: ago(i),
    r: MON_SERIES.map(([k, name, unit, d]) => [name, getStNum(H[k][i], d) + ' ' + unit, 'mn-' + k]) })}</span></span>`).join('');
  const sum = () => el.querySelectorAll('[data-mon-sum]').forEach((tag) => {
    const [k, , , d] = MON_SERIES.find((s) => s[0] === tag.dataset.monSum);
    tag.textContent = 'пик ' + getStNum(Math.max(...H[k]), d) + ' · среднее ' + getStNum(H[k].reduce((a, b) => a + b, 0) / n, d);
  });
  const flow = el.querySelector('.mn-chart-flow');
  if (tick && flow) {
    ser.forEach(([k, v, m]) => {
      const pts = pt(v, m, k);
      flow.querySelector('.sl-chart-area-' + k).setAttribute('d', area(pts));
      flow.querySelector('.sl-chart-line-' + k).setAttribute('d', line(pts));
    });
    flow.classList.remove('mn-glide');
    flow.style.transform = `translateX(${S}px)`;
    void flow.getBoundingClientRect();
    flow.classList.add('mn-glide');
    flow.style.transform = '';
    const box = el.querySelector('.st-zones');
    box.innerHTML = zones;
    box.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: {} }));
    sum();
    return;
  }
  const grad = (key, a, b) => `<linearGradient id="mn-g-${key}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="var(--sl-chart-${key})" stop-opacity="${a}"/><stop offset="1" stop-color="var(--sl-chart-${key})" stop-opacity="${b}"/></linearGradient>`;
  const svg = `<svg class="sl-chart-svg mn-chart-svg" viewBox="0 0 ${W} ${T}" preserveAspectRatio="none" aria-hidden="true"><defs>${grad('up', 0.55, 0.06)}${grad('down', 0.5, 0.05)}${grad('cpu', 0.34, 0.04)}${grad('ram', 0.34, 0.04)}<filter id="slMonitorGlow" x="-10%" y="-10%" width="120%" height="120%"><feDropShadow dx="0" dy="1" stdDeviation="0.8" flood-color="var(--sl-text)" flood-opacity="0.1"/></filter></defs><g class="mn-chart-flow">` +
    ser.map(([k, v, m]) => `<path class="sl-chart-area sl-chart-area-${k}" d="${area(pt(v, m, k))}" fill="url(#mn-g-${k})"></path>`).join('') +
    ser.map(([k, v, m]) => `<path class="sl-chart-line sl-chart-line-${k}" d="${line(pt(v, m, k))}"></path>`).join('') + '</g></svg>';
  const marks = [0, 15, 30, 45, 59].map((i) => `<em style="left:${i / (n - 1) * 100}%">${ago(i)}</em>`).join('');
  const tiles = MON_SERIES.map(([k, name, unit, , icon]) => `<div data-st-tilt class="st-tile mn-${k}"><span><i class="st-dot"></i>${name}</span><b><span data-mon-t="${k}"></span> <small>${unit}</small></b><small data-mon-sum="${k}"></small><i class="bi bi-${icon} st-tile-icon" aria-hidden="true"></i></div>`).join('');
  el.innerHTML = `<div class="st-tiles">${tiles}</div><div class="mn-chart"><div class="mn-chart-grid">${[100, 75, 50, 25, 0].map((v) => `<span style="--sl-d-y:${v / 100}"><i>${v}%</i></span>`).join('')}</div>${svg}<div class="st-zones">${zones}</div><div class="mn-chart-axis">${marks}</div></div>`;
  sum();
  el.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: {} }));
}

/* A full draw fills every host; a tick (live: the boxes on air) moves only what stands in a box on air */
function setMonDraw(full, live) {
  const on = (el) => !live || live.some((box) => box.contains(el));
  if (full) {
    document.querySelectorAll('[data-mon-kv]').forEach(setMonKv);
    document.querySelectorAll('[data-mon-meter]').forEach(setMonMeter);
    document.querySelectorAll('[data-mon-store]').forEach(setMonStore);
  }
  if (!live) {
    document.querySelectorAll('[data-mon-signals]').forEach(setMonSignals);
    document.querySelectorAll('[data-mon-block="ext"]').forEach(setMonExt);
    setMonLamp();
  }
  document.querySelectorAll('[data-mon-block="traffic"]').forEach((el) => { if (on(el)) setMonChart(el, !!live); });
  setMonGauges(on);
  document.querySelectorAll('[data-mon-t]').forEach((el) => { if (on(el)) setStNum(el, getMonText(el.dataset.monT), !full); });
  if (live) return;
  document.querySelectorAll('[data-mon-kv], [data-mon-signals], [data-mon-store]').forEach((host) => host.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: {} })));
}

/* Every 4 s the stand measures again, as the sync poll of the live page does; the live chip of a box pauses that box */
function setMonTick() {
  const live = [...document.querySelectorAll('[data-sl-live-box]')].filter((box) => !box.hasAttribute('data-sl-live-paused'));
  if (isStStill() || document.hidden || !live.length) return;
  const walk = (v, step, lo, hi) => Math.max(lo, Math.min(hi, v + (Math.random() - 0.5) * step));
  MON.cpu = walk(MON.cpu, 14, 4, 95);
  MON.ram = walk(MON.ram, 1.6, 50, 88);
  MON.up = walk(MON.up, 18, 2, 240);
  MON.down = walk(MON.down, 16, 2, 220);
  MON.gen = Math.round(walk(MON.gen, 18, 40, 160));
  ['cpu', 'ram', 'up', 'down'].forEach((k) => { mon.hist[k].push(MON[k]); mon.gone[k] = mon.hist[k].shift(); });
  setMonDraw(false, live);
  setMonPulseTick(live);
  live.forEach((box) => box.setAttribute('data-sl-live-stamp', Date.now()));
}

function initMon() {
  document.querySelectorAll('[data-mon-block="cockpit"]').forEach(getMonCockpit);
  document.querySelectorAll('[data-mon-live]').forEach((el) => { el.outerHTML = getMonLive(); });
  getMonPoints();
  setMonDraw(true);
  document.querySelectorAll('[data-mon-block="cockpit"], [data-sl-live-box]').forEach((el) => el.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: {} })));
  setInterval(setMonTick, 4000);
  document.addEventListener('animationend', (e) => { if (e.animationName === 'sl-cockpit-fresh') e.target.classList.remove('sl-is-fresh'); });
  const kinds = document.querySelector('[data-demo-kinds]');
  if (kinds) {
    mon.scene = kinds.dataset.kind || mon.scene;
    kinds.addEventListener('demo-kind', () => {
      mon.scene = kinds.dataset.kind;
      setMonDraw(false);
    });
  }
}

initMon();
