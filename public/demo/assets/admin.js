/* The engine of the admin dashboard series. The four faces of `admin.php` carry one corpus - the thirty-eight modules
   of the live panel, its moderation queues, its session block and the figures of its debug footer - so no face can
   drift from another by retyping a list. A face is markup and `v-*` rules; whatever repeats is written here from a
   <template> the face itself owns, which keeps the theme classes in the face and the data in one place.

   A placeholder stands only in text or in an attribute value - the parser of a <template> would split a bare one into
   attributes - so a part that may be absent carries `data-adm-on="{{ field }}"` and the stylesheet takes a zero off
   the screen.

   Loaded after demo.js and before DOMContentLoaded, so the rows exist by the time the stand wires its filter. */

const ADM_GROUPS = [
  { key: 'content', title: 'Контент', icon: 'bi-layers', tone: 'primary', note: 'Что видит посетитель: блоки, категории, обсуждения' },
  { key: 'people', title: 'Люди', icon: 'bi-people', tone: 'success', note: 'Учётные записи, группы и переписка' },
  { key: 'site', title: 'Сайт', icon: 'bi-globe2', tone: 'accent', note: 'Оформление, языки, поиск и посещаемость' },
  { key: 'system', title: 'Система', icon: 'bi-cpu', tone: 'warning', note: 'Ядро, база данных, защита и расписание' },
  { key: 'trade', title: 'Коммерция', icon: 'bi-bag', tone: 'danger', note: 'Магазин, клиенты и оплата' },
];

/* key, title, icon, group, access (0 all / 1 users / 2 admins), pinned, badge, description, config op, public view */
const ADM_MODS = [
  ['blocks', 'Блоки и баннеры', 'bi-grid-3x3-gap', 'content', 0, 1, 0, 'Расстановка блоков по позициям и показ баннеров', 0, 0],
  ['categories', 'Категории', 'bi-folder', 'content', 0, 0, 0, 'Дерево разделов для всех модулей', 0, 0],
  ['comments', 'Комментарии', 'bi-chat-dots', 'content', 0, 1, 3, 'Обсуждения и очередь на проверку', 1, 0],
  ['editor', 'Редактор', 'bi-pencil-square', 'content', 0, 0, 0, 'Визуальный редактор и его панели', 0, 0],
  ['fields', 'Дополнительные поля', 'bi-input-cursor-text', 'content', 0, 0, 0, 'Свои поля в формах модулей', 0, 0],
  ['favorites', 'Фавориты', 'bi-star', 'content', 0, 0, 0, 'Закладки посетителей и их квота', 0, 0],
  ['ratings', 'Рейтинги', 'bi-star-half', 'content', 0, 0, 0, 'Оценки материалов и защита от накрутки', 0, 0],
  ['replace', 'Замена слов', 'bi-arrow-left-right', 'content', 0, 0, 0, 'Автозамена и фильтр выражений', 0, 0],
  ['uploads', 'Загрузки', 'bi-cloud-arrow-up', 'content', 0, 0, 0, 'Правила файлов: форматы, вес, квоты', 0, 0],
  ['voting', 'Опросы', 'bi-bar-chart', 'content', 0, 0, 0, 'Голосования и живые результаты', 1, 1],
  ['forum', 'Форум', 'bi-chat-square-text', 'content', 0, 0, 0, 'Разделы, темы и модерация форума', 1, 1],
  ['changelog', 'Журнал изменений', 'bi-journal-text', 'content', 0, 0, 0, 'История коммитов проекта', 1, 1],

  ['account', 'Пользователи', 'bi-people', 'people', 0, 1, 30, 'Учётные записи, регистрация и вход', 1, 1],
  ['admins', 'Администрация', 'bi-person-gear', 'people', 2, 0, 0, 'Администраторы и их права', 0, 0],
  ['groups', 'Группы', 'bi-people-fill', 'people', 0, 0, 0, 'Группы, очки и переходы между ними', 0, 0],
  ['messages', 'Сообщения', 'bi-envelope', 'people', 1, 0, 0, 'Объявления для посетителей', 0, 0],
  ['privat', 'Личные сообщения', 'bi-incognito', 'people', 1, 0, 0, 'Переписка между пользователями', 0, 0],
  ['newsletter', 'Рассылка', 'bi-send', 'people', 0, 0, 0, 'Письма подписчикам', 0, 0],
  ['contact', 'Обратная связь', 'bi-envelope-open', 'people', 1, 0, 0, 'Форма обращений с сайта', 1, 1],
  ['whois', 'Жалобы', 'bi-geo-alt', 'people', 1, 0, 0, 'Проверка адресов и обращения', 1, 1],

  ['template', 'Шаблон', 'bi-palette', 'site', 0, 1, 0, 'Тема оформления и её файлы', 0, 0],
  ['lang', 'Редактор языков', 'bi-translate', 'site', 0, 0, 0, 'Языковые константы шести локалей', 0, 0],
  ['search', 'Поиск', 'bi-search', 'site', 0, 0, 0, 'Поиск по материалам сайта', 1, 1],
  ['sitemap', 'Карта сайта', 'bi-diagram-3', 'site', 0, 0, 0, 'Карта для людей и поисковиков', 1, 1],
  ['rss', 'RSS каналы', 'bi-rss', 'site', 0, 0, 0, 'Ленты экспорта и импорта', 1, 1],
  ['auto_links', 'Обмен ссылками', 'bi-link', 'site', 0, 0, 0, 'Каталог партнёрских ссылок', 1, 1],
  ['referers', 'Переходы', 'bi-box-arrow-in-right', 'site', 0, 0, 0, 'Откуда приходят посетители', 0, 0],
  ['statistic', 'Статистика', 'bi-graph-up', 'site', 0, 1, 0, 'Посещаемость по дням и часам', 0, 0],

  ['config', 'Конфигурации', 'bi-gear', 'system', 0, 1, 0, 'Основные настройки системы', 0, 0],
  ['modules', 'Модули', 'bi-puzzle', 'system', 0, 1, 0, 'Включение, доступ и порядок модулей', 0, 0],
  ['database', 'База данных', 'bi-database', 'system', 0, 0, 0, 'Оптимизация, копии и запросы', 0, 0],
  ['security', 'Безопасность', 'bi-shield-lock', 'system', 0, 1, 0, 'Защита, блокировки и журнал атак', 0, 0],
  ['monitor', 'Мониторинг', 'bi-display', 'system', 0, 0, 0, 'Нагрузка сервера в реальном времени', 0, 0],
  ['scheduler', 'Планировщик', 'bi-clock-history', 'system', 0, 0, 0, 'Задачи по расписанию', 0, 0],

  ['shop', 'Магазин', 'bi-cart3', 'trade', 0, 0, 11, 'Товары, заказы, клиенты и партнёры', 1, 1],
  ['clients', 'Клиенты', 'bi-briefcase', 'trade', 1, 0, 0, 'Кабинет клиента и его покупки', 1, 1],
  ['order', 'Форма заказа', 'bi-receipt', 'trade', 0, 0, 0, 'Заявки с сайта', 1, 1],
  ['money', 'Обмен WebMoney', 'bi-currency-exchange', 'trade', 0, 0, 0, 'Курсы и заявки на обмен', 1, 1],
].map((r) => ({
  key: r[0], title: r[1], icon: r[2], group: r[3], access: r[4], pin: r[5], badge: r[6], desc: r[7], conf: r[8], view: r[9],
}));

const ADM_ACCESS = ['Все посетители', 'Только пользователи', 'Только администраторы'];

/* The "New" and "Awaiting review" blocks of the live sidebar, row for row, with the numbers of 2026-09-20 */
const ADM_QUEUE = [
  ['Пользователи', 'bi-person-plus', 30, 'account&op=newuser', 'ждут активации'],
  ['Клиенты', 'bi-bag-plus', 10, 'shop&op=clients', 'новые в магазине'],
  ['Комментарии', 'bi-chat-dots', 3, 'comments&status=1', 'ждут проверки'],
  ['Новости', 'bi-newspaper', 2, 'news&status=1', 'предложены авторами'],
  ['Партнёры', 'bi-shop', 1, 'shop&op=partners', 'заявка на участие'],
  ['Вопросы и ответы', 'bi-question-circle', 0, 'faq&status=1', ''],
  ['Каталог файлов', 'bi-file-earmark-plus', 0, 'files&status=1', ''],
  ['Недоступные файлы', 'bi-file-earmark-x', 0, 'files&status=2', ''],
  ['Анекдоты', 'bi-emoji-laughing', 0, 'jokes&status=1', ''],
  ['Каталог сайтов', 'bi-link-45deg', 0, 'links&status=1', ''],
  ['Недоступные сайты', 'bi-slash-circle', 0, 'links&status=2', ''],
  ['Медиа каталог', 'bi-camera', 0, 'media&status=1', ''],
  ['Недоступные медиа файлы', 'bi-camera-video-off', 0, 'media&status=2', ''],
  ['Статьи', 'bi-file-richtext', 0, 'pages&status=1', ''],
].map((r) => ({ title: r[0], icon: r[1], num: r[2], href: '../admin.php?name=' + r[3], note: r[4] }));

/* The debug footer of the live page: real figures of one request */
const ADM_LOG = [
  ['20:12:44', 'sql', 'Unknown column \'time\' in SELECT', 'account · favorites', 'danger'],
  ['20:12:42', 'sql', 'Unknown column \'time\' in SELECT', 'account · favorites', 'danger'],
  ['20:12:41', 'sql', 'Unknown column \'time\' in SELECT', 'account · favorites', 'danger'],
  ['20:11:08', 'guard', 'setup.php лежит в корне сайта', 'security', 'warning'],
  ['20:10:57', 'auth', 'Вход администратора SLAED CMS', 'admins', 'success'],
  ['20:10:02', 'cron', 'Планировщик: 3 задачи, 0 ошибок', 'scheduler', 'success'],
].map((r) => ({ time: r[0], chan: r[1], text: r[2], src: r[3], tone: r[4] }));

const ADM_ONLINE = [
  ['Администрация', 'bi-person-gear', 1],
  ['Пользователей', 'bi-person', 0],
  ['Гостей', 'bi-person-dash', 0],
].map((r) => ({ title: r[0], icon: r[1], num: r[2] }));

/* Hits by day: illustrative, and every face says so in its closing line */
const ADM_SERIES = {
  hits: [212, 240, 198, 260, 310, 284, 190, 176, 228, 265, 301, 342, 298, 321],
  gen: [310, 290, 335, 301, 280, 322, 296, 288, 340, 306, 299, 312, 284, 306],
};

function getAdmEsc(v) {
  return String(v).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
}

function getAdmRows(box) {
  const kind = box.dataset.admList;
  const groups = Object.fromEntries(ADM_GROUPS.map((g) => [g.key, g]));
  if (kind === 'mods') {
    return ADM_MODS
      .filter((m) => !box.dataset.admGroup || m.group === box.dataset.admGroup)
      .filter((m) => box.dataset.admPin === undefined || m.pin)
      .map((m) => ({
        ...m,
        href: '../admin.php?name=' + m.key,
        gtitle: groups[m.group].title,
        tone: groups[m.group].tone,
        accessname: ADM_ACCESS[m.access],
      }));
  }
  if (kind === 'queue') {
    const only = box.dataset.admOnly;
    const top = Math.max(...ADM_QUEUE.map((q) => q.num));
    return ADM_QUEUE
      .filter((q) => !only || (only === 'hot' ? q.num > 0 : q.num === 0))
      .map((q) => ({ ...q, part: Math.round((q.num / top) * 100) }));
  }
  if (kind === 'groups') return ADM_GROUPS.map((g) => ({ ...g, num: ADM_MODS.filter((m) => m.group === g.key).length }));
  if (kind === 'log') return ADM_LOG.slice(0, Number(box.dataset.admRows) || ADM_LOG.length);
  if (kind === 'online') return ADM_ONLINE;
  return [];
}

function setAdmLists() {
  document.querySelectorAll('[data-adm-list]').forEach((box) => {
    /* A face that repeats one row under several groups names the template once, by id */
    const tpl = box.querySelector('template') || document.getElementById(box.dataset.admTpl || '');
    if (!tpl) return;
    const html = tpl.innerHTML;
    box.insertAdjacentHTML('beforeend', getAdmRows(box).map((row) =>
      html.replace(/\{\{\s*(\w+)\s*\}\}/g, (m, k) => getAdmEsc(row[k] ?? ''))).join(''));
  });
}

function setAdmCounts() {
  const hot = ADM_QUEUE.filter((q) => q.num > 0);
  const facts = {
    mods: ADM_MODS.length,
    hot: hot.length,
    zero: ADM_QUEUE.length - hot.length,
    sum: hot.reduce((a, q) => a + q.num, 0),
    zeronames: ADM_QUEUE.filter((q) => !q.num).map((q) => q.title.toLowerCase()).join(', '),
  };
  document.querySelectorAll('[data-adm-count]').forEach((node) => {
    const [key, arg] = node.dataset.admCount.split(':');
    node.textContent = arg ? ADM_MODS.filter((m) => m.group === arg).length : facts[key];
  });
}

/* A sparkline is an inline SVG so the theme paints it: the stroke and the fill are classes, not colours */
function setAdmSparks() {
  document.querySelectorAll('[data-adm-spark]').forEach((box) => {
    const vals = ADM_SERIES[box.dataset.admSpark] || [];
    const w = 300;
    const h = 80;
    const lo = Math.min(...vals) * 0.9;
    const hi = Math.max(...vals);
    const pts = vals.map((v, i) => [(i / (vals.length - 1)) * w, h - ((v - lo) / (hi - lo)) * (h - 8) - 4]);
    const line = pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' ');
    const last = pts[pts.length - 1];
    box.innerHTML = '<svg viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none" aria-hidden="true">'
      + '<path class="a-spark-fill" d="' + line + ' L' + w + ' ' + h + ' L0 ' + h + ' Z"/>'
      + '<path class="a-spark-line" d="' + line + '"/></svg>'
      + '<i class="a-spark-dot" style="left:' + ((last[0] / w) * 100).toFixed(1) + '%;top:' + ((last[1] / h) * 100).toFixed(1) + '%"></i>';
  });
}

/* Master and detail: a row chosen in the list writes itself into the inspector, and the actions the speed dial hides
   behind three dots stand there opened out with their names */
function setAdmInspect() {
  const pane = document.querySelector('[data-adm-inspector]');
  if (!pane) return;
  const tpl = pane.querySelector(':scope > template');
  const idle = pane.querySelector('[data-adm-idle]');
  const slot = pane.querySelector('[data-adm-slot]');

  function show(key) {
    const m = ADM_MODS.find((v) => v.key === key);
    document.querySelectorAll('[data-adm-key]').forEach((r) => r.setAttribute('aria-current', String(r.dataset.admKey === key)));
    if (!m) { slot.innerHTML = ''; idle.hidden = false; return; }
    const g = ADM_GROUPS.find((v) => v.key === m.group);
    const row = {
      ...m, gtitle: g.title, tone: g.tone, accessname: ADM_ACCESS[m.access], href: '../admin.php?name=' + m.key,
    };
    idle.hidden = true;
    slot.innerHTML = tpl.innerHTML.replace(/\{\{\s*(\w+)\s*\}\}/g, (x, k) => getAdmEsc(row[k] ?? ''));
  }

  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-adm-close]')) { show(''); return; }
    const row = e.target.closest('[data-adm-key]');
    if (!row || e.target.closest('a, label, input')) return;
    show(row.dataset.admKey);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.matches('[data-adm-key]')) show(e.target.dataset.admKey);
  });
}

/* The hint line: what the pointer or the focus rests on says its one sentence at one address */
function setAdmHint() {
  const line = document.querySelector('[data-adm-hint]');
  if (!line) return;
  const idle = line.textContent;
  const say = (e) => {
    const node = e.target.closest ? e.target.closest('[data-adm-say]') : null;
    line.textContent = node ? node.dataset.admSay : idle;
    line.toggleAttribute('data-live', Boolean(node));
  };
  document.addEventListener('pointerover', say);
  document.addEventListener('focusin', say);
}

/* Keys: "/" and Ctrl+K reach the search of the face, Enter opens the first match, Escape clears */
function setAdmKeys() {
  const field = document.querySelector('[data-demo-filter]');
  if (!field) return;
  document.addEventListener('keydown', (e) => {
    const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName);
    if ((e.key === '/' && !typing) || (e.key.toLowerCase() === 'k' && (e.ctrlKey || e.metaKey))) {
      e.preventDefault();
      field.focus();
      field.select();
    }
    if (document.activeElement !== field) return;
    if (e.key === 'Escape') { field.value = ''; field.dispatchEvent(new Event('input')); }
    if (e.key === 'Enter') {
      const hit = [...document.querySelectorAll('[data-demo-find]')].find((r) => !r.hidden && r.offsetParent);
      const link = hit ? (hit.matches('a') ? hit : hit.querySelector('a')) : null;
      if (link) location.href = link.href;
    }
  });
}

function setAdmFacts() {
  const hour = new Date().getHours();
  const greet = hour < 5 ? 'Доброй ночи' : hour < 12 ? 'Доброе утро' : hour < 18 ? 'Добрый день' : 'Добрый вечер';
  document.querySelectorAll('[data-adm-greet]').forEach((n) => { n.textContent = greet; });
  const clock = document.querySelectorAll('[data-adm-clock]');
  const tick = () => {
    const now = new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    clock.forEach((n) => { n.textContent = now; });
  };
  tick();
  if (clock.length) setInterval(tick, 30000);
}

/* The closing card of a face, read from the manifest of the stand so the text lives at one address */
function setAdmNote() {
  const box = document.querySelector('[data-adm-note]');
  if (!box || new URLSearchParams(location.search).has('bare')) return;
  const place = getDemoPlace(location.pathname.split('/').pop() || '');
  if (!place.item) return;
  box.innerHTML = '<div class="demo-note"><h2>' + String(place.idx + 1).padStart(2, '0') + ' &middot; ' + place.item.title
    + '</h2><p>' + place.item.note + '</p><p>Модули, очереди, сеансы и цифры генерации сняты с живой панели 2026-09-20; '
    + 'кривые посещаемости — иллюстративные. Ссылки модулей ведут в настоящий <code>admin.php</code>.</p></div>';
}

setAdmLists();
setAdmCounts();
setAdmSparks();
setAdmInspect();
setAdmHint();
setAdmKeys();
setAdmFacts();
setAdmNote();
