/* The admin block series (ab-NN-*.html): adminblock() in core/system.php, the block with bkey 'admin' (id 2 on the
   stand). It shows to an administrator only, in the panel on every screen under the menu and on the site in its column,
   and it carries two links of its own - the panel and the logout - then a line, then whatever the owner wrote into the
   block's content. adminblock() also prints the second block, "Наблюдение", from getUserSessionAdminInfo().

   One page per face, two rooms per page: ?ctx=admin stands on the admin theme with the panel shell and the block form,
   ?ctx=site stands on the lite theme with the news column. The face is window.AB_FACE, declared by the page before this
   script runs; it answers blocks(d) with the HTML of its sidebar blocks and may answer dock(d) and after(host, d).

   Loaded in <head>: it writes the theme of the room, then builds the body before slaed.js and demo.js boot */
(() => {
  const root = document.documentElement;
  const params = new URLSearchParams(location.search);
  const ctx = params.get('ctx') === 'site' ? 'site' : 'admin';
  const theme = ctx === 'site' ? 'lite' : 'admin';
  const stamp = Date.now();
  root.dataset.abCtx = ctx;
  document.write(
    `<link rel="stylesheet" href="../templates/${theme}/assets/vendor/bootstrap-icons/css/bootstrap-icons.min.css">` +
    `<link rel="stylesheet" href="../templates/${theme}/assets/css/base.css">` +
    `<link rel="stylesheet" href="../templates/${theme}/assets/css/theme.css">` +
    `<link rel="stylesheet" href="assets/demo.css?v=${stamp}">` +
    `<link rel="stylesheet" href="assets/ablock.css?v=${stamp}">` +
    '<script src="../plugins/system/global-func.js"><\/script>' +
    '<script src="../plugins/system/slaed.js"><\/script>' +
    (ctx === 'admin' ? '<script src="../templates/admin/assets/js/admin-ui.js"><\/script>' : ''));

  /* ---------- Corpus: what the stand knows, from the stand database and the etalon ---------- */

  // The content column of sport_blocks id 2, word for word; plugins/sxd is not in the tree, so the link is dead
  const PRESETS = {
    db: `<a href="javascript:OpenWindow('plugins/sxd/index.php', 'DB Backup - Sypex Dumper', '600', '500')" title="DB Backup - Sypex Dumper">DB Backup - Sypex Dumper</a>`,
    links: '<a href="admin.php?name=database" title="Резервная копия базы">Резервная копия</a>\n' +
      '<a href="admin.php?name=security&amp;op=logview" title="Журналы безопасности">Журналы</a>\n' +
      '<a href="https://slaed.net" title="Сайт проекта SLAED CMS" target="_blank">slaed.net</a>',
    many: '<a href="admin.php?name=database" title="Резервная копия базы">Резервная копия</a>\n' +
      '<a href="admin.php?name=security&amp;op=logview" title="Журналы безопасности">Журналы</a>\n' +
      '<a href="admin.php?name=monitor" title="Мониторинг сервера">Мониторинг</a>\n' +
      '<a href="https://mailpit" title="Почта стенда в Mailpit" target="_blank"><i class="bi bi-mailbox"></i> Почта стенда</a>\n' +
      '<a href="https://slaed.net" title="Сайт проекта SLAED CMS" target="_blank">slaed.net</a>',
    free: '<p>Резервная копия снимается по воскресеньям в 03:00 — перед выходными проверьте место на диске.</p>\n' +
      '<a href="admin.php?name=database" title="Резервная копия базы">Резервная копия</a>',
    none: '',
  };
  const PRESET_NAMES = [['db', 'Как в базе'], ['links', 'Три ссылки'], ['many', 'Пять ссылок'], ['free', 'Текст и ссылка'], ['none', 'Пусто']];
  const DEAD = ['plugins/sxd'];

  // The pending queue of the panel home ("Новые" and "Ждут проверки", Г8 of the etalon), same names, order and figures
  const QUEUE = [
    { key: 'users', label: 'Пользователи', title: 'Новые пользователи', icon: 'person-plus', href: '../admin.php?name=account&op=newuser', n: 18 },
    { key: 'comments', label: 'Комментарии', title: 'Комментарии ждут проверки', icon: 'chat-dots', href: '../admin.php?name=comments', n: 3 },
    { key: 'help', label: 'Помощь', title: 'Помощь', icon: 'life-preserver', href: '../admin.php?name=node&type=help', n: 3 },
    { key: 'news', label: 'Новости', title: 'Новости', icon: 'newspaper', href: '../admin.php?name=node&type=news&status=1', n: 2 },
    { key: 'faq', label: 'Вопросы и ответы', title: 'Вопросы и ответы', icon: 'question-circle', href: '../admin.php?name=node&type=faq&status=1', n: 0 },
    { key: 'content', label: 'Содержание', title: 'Содержание', icon: 'puzzle', href: '../admin.php?name=node&type=content&status=1', n: 0 },
    { key: 'links', label: 'Каталог сайтов', title: 'Каталог сайтов', icon: 'link-45deg', href: '../admin.php?name=node&type=links&status=1', n: 0 },
    { key: 'files', label: 'Каталог файлов', title: 'Каталог файлов', icon: 'file-earmark-arrow-down', href: '../admin.php?name=node&type=files&status=1', n: 0 },
  ];

  // Who is on the site. "Стенд" is what the stand really shows - the administrator himself and one guest. "Оживлённо"
  // is a busy evening of the live site: the people are the commenters of the etalon corpus with their countries and
  // their stored avatars (presets of templates/lite), the bots are the two crawlers the session table knows
  const AVA = '../templates/lite/images/avatars/';
  const FLAG = '../templates/lite/images/flags/';
  const SESSIONS = {
    stand: [
      { kind: 'admin', name: 'SLAED CMS', ava: 'presets/038.svg', cc: '', ip: '127.0.0.1', page: '/admin.php', ago: '1 сек' },
      { kind: 'guest', name: 'Гость', ava: 'system/guest.svg', cc: '', ip: '127.0.0.1', page: '/news', ago: '12 сек' },
    ],
    busy: [
      { kind: 'admin', name: 'SLAED CMS', ava: 'presets/038.svg', cc: '', ip: '127.0.0.1', page: '/admin.php', ago: '1 сек' },
      { kind: 'user', name: 'olevpa', ava: 'presets/033.svg', cc: 'de', ip: '195.181.175.177', page: '/news/slaed-cms-8', ago: '20 сек' },
      { kind: 'user', name: 'Aquamarine', ava: 'presets/012.svg', cc: 'ru', ip: '188.68.83.202', page: '/forum', ago: '1 мин' },
      { kind: 'user', name: 'rvg', ava: 'presets/007.svg', cc: 'ua', ip: '46.175.253.108', page: '/files/view/5056', ago: '2 мин' },
      { kind: 'user', name: 'o5Tolik', ava: 'presets/028.svg', cc: 'ru', ip: '92.255.10.41', page: '/voting', ago: '3 мин' },
      { kind: 'user', name: 'potapchik', ava: 'presets/017.svg', cc: 'ua', ip: '176.104.48.144', page: '/account', ago: '4 мин' },
      { kind: 'user', name: 'Badboy', ava: 'presets/029.svg', cc: 'no', ip: '77.111.247.157', page: '/links', ago: '6 мин' },
      { kind: 'bot', name: 'Googlebot', ava: 'system/user.svg', cc: 'us', ip: '66.249.66.1', page: '/sitemap.xml', ago: '30 сек' },
      { kind: 'bot', name: 'YandexBot', ava: 'system/user.svg', cc: 'ru', ip: '5.255.231.12', page: '/news', ago: '2 мин' },
      { kind: 'bot', name: 'bingbot', ava: 'system/user.svg', cc: 'us', ip: '40.77.167.30', page: '/files', ago: '5 мин' },
      { kind: 'guest', name: 'Гость', ava: 'system/guest.svg', cc: 'ru', ip: '91.204.15.7', page: '/news/slaed-cms-8', ago: '8 сек' },
      { kind: 'guest', name: 'Гость', ava: 'system/guest.svg', cc: 'de', ip: '84.143.20.9', page: '/help', ago: '40 сек' },
      { kind: 'guest', name: 'Гость', ava: 'system/guest.svg', cc: 'ua', ip: '46.211.80.14', page: '/files', ago: '1 мин' },
      { kind: 'guest', name: 'Гость', ava: 'system/guest.svg', cc: 'by', ip: '178.70.12.33', page: '/voting', ago: '1 мин' },
      { kind: 'guest', name: 'Гость', ava: 'system/guest.svg', cc: 'kz', ip: '2.134.40.18', page: '/', ago: '2 мин' },
    ],
  };
  // Guests beyond the ones listed: the live block lists up to 250 and counts the rest
  const GUESTS_MORE = { stand: 0, busy: 22 };

  // What arrives while the admin reads: the incoming comments of the etalon (Д8), in the order they come
  const INCOMING = [
    { name: 'Гость', cc: 'ru', module: 'Новости', text: 'Когда выйдет 8.0? Жду с нетерпением, хочу обновить свой сайт.' },
    { name: 'kostya_dev', cc: 'ua', module: 'Каталог файлов', text: 'Подскажите, тема из каталога файлов подойдёт к версии 8.0 без доработки?' },
    { name: 'Гость', cc: 'de', module: 'Помощь', text: 'После переноса на новый хостинг не открывается панель, белый экран.' },
    { name: 'Marta', cc: 'by', module: 'Опросы', text: 'Голосую за каталог сайтов, давно его не хватало.' },
  ];

  const MENU = [
    ['admins', 'Администрация', 'person-gear'], ['blocks', 'Блоки и баннеры', 'grid-3x3-gap'], ['categories', 'Категории', 'folder'],
    ['comments', 'Комментарии', 'chat-dots'], ['config', 'Конфигурации', 'gear'], ['database', 'База данных', 'database'],
    ['editor', 'Редактор', 'pencil-square'], ['favorites', 'Фавориты', 'star'], ['fields', 'Дополнительные поля', 'input-cursor-text'],
    ['groups', 'Группы', 'people-fill'], ['lang', 'Редактор языков', 'translate'], ['messages', 'Сообщения', 'envelope'],
    ['modules', 'Модули', 'puzzle'], ['monitor', 'Мониторинг', 'display'], ['newsletter', 'Рассылка', 'send'],
    ['privat', 'Личные сообщения', 'incognito'], ['ratings', 'Рейтинги', 'star-half'], ['referers', 'Переходы', 'box-arrow-in-right'],
    ['replace', 'Замена слов', 'arrow-left-right'], ['scheduler', 'Планировщик', 'clock-history'], ['security', 'Безопасность', 'shield-lock'],
    ['statistic', 'Статистика', 'graph-up'], ['template', 'Шаблон', 'palette'], ['update', 'Обновления', 'cloud-arrow-down'],
    ['uploads', 'Загрузки', 'cloud-arrow-up'],
  ];
  const MODULES = [
    ['account', 'Пользователи', 'people'], ['changelog', 'Журнал изменений', 'journal-text'], ['contact', 'Обратная связь', 'envelope-open'],
    ['forum', 'Форум', 'chat-square-text'], ['node', 'Материалы', 'collection'], ['rss', 'RSS каналы', 'rss'], ['search', 'Поиск', 'search'],
    ['sitemap', 'Карта сайта', 'diagram-3'], ['voting', 'Опросы', 'bar-chart'],
  ];

  /* ---------- State of the desk ---------- */

  const KEEP = 'ab.state';
  const state = { role: 'super', preset: 'db', own: PRESETS.db, title: 'Администрация', queue: 'busy', col: 'on', crowd: 'busy', toast: 'dock' };
  try {
    Object.assign(state, JSON.parse(localStorage.getItem(KEEP) || '{}'));
  } catch (e) { /* storage may be off */ }
  // A named preset is its text; only "Своё" keeps what was typed
  if (state.preset !== 'own') state.own = PRESETS[state.preset] ?? PRESETS.db;
  const setKept = () => {
    try { localStorage.setItem(KEEP, JSON.stringify(state)); } catch (e) { /* storage may be off */ }
  };

  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

  /* ---------- The content after the line: links come out as items, anything else stays as it was written ---------- */

  function getItemIcon(href, label) {
    const s = (href + ' ' + label).toLowerCase();
    if (/sxd|dump|backup|database|резерв|копи|баз/.test(s)) return 'database-down';
    if (/security|logview|журнал|лог/.test(s)) return 'shield-lock';
    if (/mail|почт/.test(s)) return 'envelope';
    if (/docs|справ|help|faq/.test(s)) return 'book';
    if (/slaed\.net/.test(s)) return 'house-heart';
    if (/monitor|монитор/.test(s)) return 'display';
    if (/^https?:/.test(href)) return 'globe2';
    return 'link-45deg';
  }

  // A link written in the block content: a script popup becomes its address in a new tab, an address relative to the
  // site root is read from the root (the stand lives one folder down), and an address the tree does not have is flagged
  function getItem(a) {
    let href = a.getAttribute('href') || '';
    const label = (a.textContent || '').trim() || href;
    const title = a.getAttribute('title') || label;
    let popup = false;
    const js = href.match(/^javascript:\s*OpenWindow\(\s*'([^']+)'/i);
    if (js) {
      href = js[1];
      popup = true;
    }
    const ext = /^(https?:)?\/\//.test(href);
    const dead = DEAD.some((p) => href.includes(p));
    const icon = (a.querySelector('i.bi') && [...a.querySelector('i.bi').classList].find((c) => c.startsWith('bi-'))?.slice(3)) || getItemIcon(href, label);
    if (!ext && !href.startsWith('/') && !href.startsWith('#') && !href.startsWith('../')) href = '../' + href;
    return { href, label, title, icon, ext, popup, dead, blank: ext || popup || a.target === '_blank' };
  }

  function getExtras(html) {
    const tpl = document.createElement('template');
    tpl.innerHTML = html || '';
    const items = [];
    let rest = '';
    tpl.content.childNodes.forEach((node) => {
      if (node.nodeType === 3) {
        if (node.textContent.trim()) rest += esc(node.textContent.trim());
        return;
      }
      if (node.nodeType !== 1 || node.tagName === 'BR' || node.tagName === 'HR') return;
      if (node.tagName === 'A') {
        items.push(getItem(node));
        return;
      }
      const links = node.querySelectorAll('a');
      const bare = (node.tagName === 'UL' || node.tagName === 'OL') && [...node.children].every((li) => li.querySelectorAll('a').length === 1);
      if (bare) {
        links.forEach((a) => items.push(getItem(a)));
        return;
      }
      rest += node.outerHTML;
    });
    return { items, rest };
  }

  /* ---------- The live part: what changes while the page stands ---------- */

  // drift moves the guest count as a refresh would; trend is the online figure of the last hour, a point per 5 minutes;
  // add counts what arrived through "Новый комментарий" on the desk
  const live = { drift: 0, trend: [21, 24, 22, 27, 31, 29, 33, 30, 35, 38, 34, 37], add: {}, next: 0, paused: false, stamp: Date.now() };

  /* ---------- The data a face renders ---------- */

  function getData() {
    const sup = state.role === 'super';
    const queue = QUEUE.map((q) => ({ ...q, n: state.queue === 'busy' ? q.n + (live.add[q.key] || 0) : (live.add[q.key] || 0) }));
    const crowd = state.crowd === 'stand' ? 'stand' : 'busy';
    const sessions = SESSIONS[crowd].filter((x) => sup || x.kind !== 'admin');
    const count = (k) => SESSIONS[crowd].filter((x) => x.kind === k).length;
    const who = { admins: count('admin'), users: count('user'), bots: count('bot'), guests: count('guest') + GUESTS_MORE[crowd] + live.drift };
    who.all = who.admins + who.users + who.bots + who.guests;
    return {
      ctx,
      sup,
      // adminblock(): the title from the block row and the content after the line are a super-administrator's only
      title: sup ? (state.title || 'Администрация') : 'Администрация',
      sessions,
      more: GUESTS_MORE[crowd] + live.drift,
      trend: crowd === 'stand' ? [1, 2, 1, 1, 2, 2, 1, 2, 2, 1, 2, 2] : live.trend,
      ava: (f) => AVA + f,
      flagSrc: (cc) => (cc ? FLAG + cc + '.svg' : ''),
      me: { nick: 'SLAED CMS', initials: 'SC', ava: AVA + 'presets/038.svg', role: sup ? 'Суперадминистратор' : 'Администратор модулей', since: '20:31', last: 'вчера, 21:14',
        ip: '127.0.0.1', left: '47 мин' },
      home: { href: '../admin.php', label: 'Панель администратора', short: 'Панель', title: 'Панель администратора', icon: 'house-door' },
      logout: { href: '../admin.php?op=logout', label: 'Выход', short: 'Выход', title: 'Выход', icon: 'box-arrow-right' },
      extras: sup ? getExtras(state.own) : { items: [], rest: '' },
      queue,
      waiting: queue.reduce((s, q) => s + q.n, 0),
      who,
      page: ctx === 'site'
        ? { name: 'news', label: 'Новости', kind: 'Лента раздела', icon: 'newspaper',
          admin: { href: '../admin.php?name=node&type=news', label: 'Новости в панели', icon: 'newspaper' },
          add: { href: '../admin.php?name=node&op=add&type=news', label: 'Добавить новость', icon: 'plus-circle' } }
        : { name: 'blocks', label: 'Блоки и баннеры', kind: 'Редактировать блок', icon: 'grid-3x3-gap',
          site: { href: '../index.php?name=news', label: 'Блок на сайте', icon: 'globe2' },
          help: { href: '../admin.php?name=blocks&op=info', label: 'Справка раздела', icon: 'info-circle' } },
    };
  }

  /* ---------- Helpers a face composes with ---------- */

  const ico = (name, cls) => `<i class="bi bi-${name}${cls ? ' ' + cls : ''}" aria-hidden="true"></i>`;

  const attrs = (it) => `href="${esc(it.href)}" title="${esc(it.title || it.label)}"` +
    (it.blank ? ' target="_blank" rel="noopener noreferrer"' : '');

  // The marks of an item: a dead address in warning tone, a former popup and an outside address as a quiet arrow
  const flag = (it) => (it.dead ? `<span class="ab-flag" title="Файла ${esc(it.href.replace('../', ''))} нет на сервере">${ico('exclamation-triangle')}нет файла</span>` : '') +
    (!it.dead && (it.ext || it.popup) ? ` ${ico('box-arrow-up-right', 'ab-ext')}` : '');

  const free = (d) => (d.extras.rest ? `<div class="ab-free">${d.extras.rest}</div>` : '');

  // Rows of links in the room's own list: the panel's sl-admin-block-link, the site's sl-block-pm of the user block;
  // an item may carry cls, and on the site a chevron closes a plain row while a flag or an arrow closes a marked one
  function rows(d, list) {
    if (!list.length) return '';
    if (d.ctx === 'admin') {
      return `<div class="sl-admin-block-links">${list.map((it) => `<div class="sl-admin-block-link${it.cls ? ' '
        + it.cls : ''}"><a ${attrs(it)}>${ico(it.icon)} <span>${esc(it.label)}</span>${it.tail || flag(it)}</a></div>`).join('')}</div>`;
    }
    return `<ul class="sl-block-pm ab-pm">${list.map((it) => `<li${it.cls ? ` class="${it.cls}"` : ''}><a ${attrs(it)}>${ico(it.icon)}<span>${esc(it.label)}</span>${it.tail
      || (it.dead || it.blank ? flag(it) : ico('chevron-right'))}</a></li>`).join('')}</ul>`;
  }

  // The line before the owner's part, captioned; nothing after it means no line either
  const line = (d, label) => (d.extras.items.length || d.extras.rest ? `<div class="ab-line"><span>${label || 'Свои ссылки'}</span></div>` : '');

  // One sidebar block in the room's own shell: the panel's solid head, the site's titled section; ids keep the live
  // ones (7 and 8), so the panel paints the heads in the colours it gives those blocks
  function block(d, opt, inner) {
    const id = 'sl-block-' + opt.id;
    if (d.ctx === 'admin') {
      return `<div class="sl-block-sidebar${opt.cls ? ' ' + opt.cls : ''}">
  <h3 title="Свёртывание / Развёртывание" data-sl-toggle-control="${id}" data-sl-toggle-effect="slide" data-sl-toggle-duration="500"><b>${ico(opt.icon
  || 'person-gear')}${esc(opt.title)}</b>${opt.head
  || ''}<i class="bi bi-arrows-collapse sl-panel-toggle sl-panel-toggle-open" aria-hidden="true"></i><i class="bi bi-arrows-expand sl-panel-toggle sl-panel-toggle-closed" `
  + `aria-hidden="true"></i></h3>
  <div id="${id}" class="sl-block-sidebar-content" data-sl-toggle="${id}">${inner}</div>
</div>`;
    }
    return `<section class="sl-block${opt.cls ? ' ' + opt.cls : ''}">
  <h2 class="sl-title" title="Свёртывание / Развёртывание" data-sl-toggle-control="${id}">${esc(opt.title)}${opt.head || ''}</h2>
  <div id="${id}" class="sl-block-content" data-sl-toggle="${id}">${inner}</div>
</section>`;
  }

  // "Наблюдение" as the room prints it; on the site the ring is the lite one, fed the shares it waits for - the live
  // block feeds it the admin keys and the ring reads "%" with nothing in it
  function who(d) {
    const w = d.who;
    if (d.ctx === 'admin') {
      const line = (icon, label, n, btn) => `<div class="sl-session-line sl-session-line--icon"><div class="sl-session-icon">${ico(icon)}</div><div>${btn
        ? `<a href="#" title="Подробнее">${label}:</a>` : label + ':'}</div><div class="sl-session-right${n ? '' : ' ab-zero'}">${n}</div></div>`;
      return block(d, { id: 8, title: 'Наблюдение', icon: 'eye' },
        (d.sup ? line('person-gear', 'Администрация', w.admins, true) : '') +
        line('person', 'Пользователей', w.users) + line('person-slash', 'Гостей', w.guests) + line('people', 'Всего', w.all) +
        '<hr><div class="sl-session-action"><button type="button" class="sl-but">Обновить</button></div>');
    }
    const pct = (n) => (w.all ? Math.round(n / w.all * 100) : 0);
    return block(d, { id: 8, title: 'Наблюдение' }, `<div class="sl-session-summary">
  <div class="sl-session-donut" style="--sl-d-members: ${pct(w.users + w.admins)}%; --sl-d-bots: ${pct(w.users + w.admins)}%;">
    <span><b>${pct(w.guests)}%</b><small>Гостей</small></span>
  </div>
  <div>
    ${d.sup ? `<div class="sl-session-line"><i class="bi bi-person-gear" aria-hidden="true"></i><span>Администрация</span><b>${w.admins}</b></div>` : ''}
    <div class="sl-session-line" data-sl-audience="users"><i class="bi bi-person-fill" aria-hidden="true"></i><span>Пользователей</span><b>${w.users}</b></div>
    <div class="sl-session-line" data-sl-audience="guests"><i class="bi bi-people-fill" aria-hidden="true"></i><span>Гостей</span><b>${w.guests}</b></div>
    <div class="sl-session-line sl-session-total"><span>Всего</span><b>${w.all}</b></div>
  </div>
</div>
<div class="sl-session-action"><a href="#" class="sl-chip sl-chip-info sl-session-button" title="Обновить">${ico('arrow-repeat')} Обновить</a></div>`);
  }

  /* ---------- The watch: "Администрация" and "Наблюдение" as one piece ---------- */

  const KINDS = [['admins', 'admin', 'Администрация', 'Админы'], ['users', 'user', 'Пользователи', 'Польз.'], ['bots', 'bot', 'Поисковые боты', 'Боты'], ['guests', 'guest',
    'Гости', 'Гости']];

  // The hour as an area under a line; the last point is now and carries a dot
  function getSpark(trend) {
    const w = 120;
    const h = 36;
    const max = Math.max(...trend) * 1.15 || 1;
    const pts = trend.map((v, i) => [(i / (trend.length - 1)) * w, h - (v / max) * (h - 4) - 2]);
    const line = pts.map(([x, y]) => x.toFixed(1) + ',' + y.toFixed(1)).join(' ');
    const [lx, ly] = pts[pts.length - 1];
    return `<polygon points="0,${h} ${line} ${w},${h}" class="ab-w-area"/><polyline points="${line}" class="ab-w-line"/><circle cx="${lx.toFixed(1)}" cy="${ly.toFixed(1)}" `
      + `r="3" class="ab-w-dot"/>`;
  }

  const getBar = (who) => KINDS.map(([k]) => `<i class="ab-w-${k}" style="--ab-w: ${who.all ? (who[k] / who.all * 100).toFixed(1) : 0}%"></i>`).join('');

  // opt.list: open the "who is where" list; opt.me: false leaves the identity line to the face
  function watch(d, opt = {}) {
    const w = d.who;
    const kinds = KINDS.filter(([k]) => d.sup || k !== 'admins');
    const me = opt.me === false ? '' : `<div class="ab-w-me"><span class="ab-w-ava"><img src="${d.me.ava}" alt=""><i class="ab-w-on" title="В сети"></i></span>` +
      `<p><b>${esc(d.me.nick)}</b><small>${d.me.role} · вход в ${d.me.since}</small></p>` +
      `<span class="ab-w-live" title="Обновляется каждые 10 секунд"><i></i>в эфире</span></div>`;
    const now = `<div class="ab-w-now"><p class="ab-w-big"><b data-ab-n="all">${w.all}</b><small>на сайте сейчас</small></p>` +
      `<svg class="ab-w-spark" viewBox="0 0 120 36" preserveAspectRatio="none" role="img" aria-label="Посещаемость за последний час" data-ab-spark>${getSpark(d.trend)}</svg>`
        + `</div>`;
    const bar = `<div class="ab-w-bar" data-ab-bar>${getBar(w)}</div>`;
    const legend = `<ul class="ab-w-legend">${kinds.map(([k, , label, short]) => `<li class="ab-w-${k}${w[k]
      ? '' : ' ab-w-zero'}" title="${label}"><i></i>${short}<b data-ab-n="${k}">${w[k]}</b></li>`).join('')}</ul>`;
    const people = d.sessions.filter((x) => x.kind !== 'guest' && x.kind !== 'bot');
    const pile = `<div class="ab-w-pile">${people.slice(0, 6).map((x) => `<img src="${d.ava(x.ava)}" alt="" title="${esc(x.name)} · ${x.page}">`).join('')}` +
      `<span title="Ещё гостей и ботов">+${w.all - Math.min(6, people.length)}</span></div>`;
    const row = (x) => `<li class="ab-w-row ab-w-${x.kind}"><img src="${d.ava(x.ava)}" alt=""><span><b>${esc(x.name)}</b><a href="../index.php" `
      + `title="${x.page}">${x.page}</a></span>` +
      `<span class="ab-w-where">${x.cc
        ? `<img src="${d.flagSrc(x.cc)}" alt="${x.cc.toUpperCase()}" title="${x.cc.toUpperCase()} · ${x.ip}">` : `<small `
        + `title="${x.ip}">${x.ip}</small>`}<small>${x.ago}</small></span></li>`;
    const list = `<details class="ab-w-list"${opt.list ? ' open' : ''}><summary>${ico('people')} Кто где <b>${d.sessions.length}${d.more ? ' + '
      + d.more : ''}</b>${ico('chevron-down', 'ab-w-chev')}</summary><ul>${d.sessions.map(row).join('')}</ul>` +
      (d.more ? `<p class="ab-w-more">и ещё ${d.more} гостей — список держит 250 строк</p>` : '') + '</details>';
    return `<div class="ab-w">${me}${now}${bar}${legend}${opt.pile === false ? '' : pile}${list}</div>`;
  }

  /* ---------- The summary: the lower half of the site's user block (partials/session-summary.html of lite) ---------- */

  // The ring of shares and the lines beside it, the chips "Подробнее" and "Обновить" under them. "Подробнее" opens
  // the window of the live statistics (statsWin) instead of the list the site folds out over the card
  function summary(d, opt = {}) {
    const w = d.who;
    const pct = (n) => (w.all ? Math.round(n / w.all * 100) : 0);
    const people = w.users + (d.sup ? w.admins : 0);
    const line = (k, icon, label,
      n) => `<div class="ab-sum-line" data-kind="${k}"><i class="bi bi-${icon}" aria-hidden="true"></i><span>${label}</span><b data-ab-n="${k === 'admin' ? 'admins' : k
        + 's'}">${n}</b></div>`;
    const ring = `<button type="button" class="ab-sum-ring" data-sl-open="ab-stats" title="Подробная статистика посещаемости" aria-label="Наблюдение: на сайте ${w.all}, `
      + `подробная статистика" style="--ab-a: ${pct(d.sup ? w.admins : 0)}%; --ab-m: ${pct(people)}%; --ab-b: ${pct(people
      + w.bots)}%;" data-ab-ring><span><b data-ab-n="all">${w.all}</b><small>на сайте</small></span></button>`;
    const lines = `<div class="ab-sum-lines">${d.sup ? line('admin', 'person-gear', 'Администрация', w.admins) : ''}${line('user', 'person-fill', 'Пользователей',
      w.users)}${line('bot', 'robot', 'Поисковых ботов', w.bots)}${line('guest', 'people-fill', 'Гостей', w.guests)}` +
      `<div class="ab-sum-line ab-sum-total"><i class="bi bi-people" aria-hidden="true"></i><span>Всего</span><b data-ab-n="all">${w.all}</b></div></div>`;
    const chips = opt.chips === false
      ? '' : `<div class="ab-sum-acts"><button type="button" class="sl-chip ab-chip-accent" data-sl-open="ab-stats" title="Подробная статистика посещаемости">${ico('eye')} `
      + `Подробнее</button><button type="button" class="sl-chip ab-chip-info" data-ab-refresh title="Обновить">${ico('arrow-repeat')} Обновить</button></div>`;
    return `<div class="ab-sum${opt.cls ? ' ' + opt.cls : ''}">${opt.ringless ? '' : ring}${opt.lineless ? '' : lines}</div>${chips}`;
  }

  /* ---------- The window of the live statistics: fragments/window.html and the window mechanics of slaed.js ---------- */

  const KIND_NAMES = { admin: 'Админ', user: 'Пользователь', bot: 'Бот', guest: 'Гость' };

  function getStatsRows(d) {
    return d.sessions.map((x) => `<li class="ab-w-row ab-w-${x.kind}" data-kind="${x.kind}"><img src="${d.ava(x.ava)}" alt=""><span><b>${esc(x.name)}</b><a `
      + `href="../index.php" title="${x.page}">${x.page}</a></span>` +
      `<span class="ab-sw-kind">${KIND_NAMES[x.kind]}</span><span class="ab-w-where">${x.cc
        ? `<img src="${d.flagSrc(x.cc)}" alt="${x.cc.toUpperCase()}" title="${x.cc.toUpperCase()} · ${x.ip}">` : `<small>${x.ip}</small>`}<small>${x.ago}</small></span>`
        + `</li>`).join('');
  }

  function getStatsTops(d) {
    const pages = {};
    const lands = {};
    d.sessions.forEach((x) => {
      pages[x.page] = (pages[x.page] || 0) + 1;
      if (x.cc) lands[x.cc] = (lands[x.cc] || 0) + 1;
    });
    const top = (map) => Object.entries(map).sort((a, b) => b[1] - a[1]).slice(0, 6);
    const most = (list) => Math.max(1, ...list.map(([, n]) => n));
    const pl = top(pages);
    const ll = top(lands);
    return `<div class="ab-sw-box"><p class="ab-sw-cap">${ico('file-earmark-text')} Где сейчас</p><ol class="ab-sw-top">${pl.map(([p,
      n]) => `<li><span title="${p}">${p}</span><i style="--ab-p: ${(n / most(pl) * 100).toFixed(0)}%"></i><b>${n}</b></li>`).join('')}</ol></div>` +
      `<div class="ab-sw-box"><p class="ab-sw-cap">${ico('globe2')} Страны</p><ol class="ab-sw-top">${ll.map(([cc,
        n]) => `<li><span><img src="${d.flagSrc(cc)}" alt=""> ${cc.toUpperCase()}</span><i style="--ab-p: ${(n / most(ll) * 100).toFixed(0)}%"></i><b>${n}</b>`
          + `</li>`).join('')}</ol>` +
      (d.more ? `<p class="ab-w-more">и ещё ${d.more} гостей без страны в списке</p>` : '') + '</div>';
  }

  function statsWin(d) {
    const w = d.who;
    const peak = Math.max(...d.trend);
    const mean = Math.round(d.trend.reduce((s, v) => s + v, 0) / d.trend.length);
    const lands = new Set(d.sessions.map((x) => x.cc).filter(Boolean)).size;
    const kinds = [['all', 'Все', w.all], ...(d.sup ? [['admin', 'Админы', w.admins]] : []), ['user', 'Пользователи', w.users], ['bot', 'Боты', w.bots], ['guest', 'Гости',
      w.guests]];
    const seg = kinds.map(([k, label, n]) => `<button type="button" class="ab-sw-seg" data-ab-sw-kind="${k}" aria-pressed="${k === 'all'}">${label} <b data-ab-n="${k === 'all'
      ? 'all' : k === 'admin' ? 'admins' : k + 's'}">${n}</b></button>`).join('');
    return `<dialog class="sl-modal sl-modal-lg ab-sw" id="ab-stats" data-sl-window aria-labelledby="ab-sw-cap">
  <div class="sl-modal-title">
    <i class="bi bi-activity" aria-hidden="true"></i>
    <span class="sl-modal-cap"><strong id="ab-sw-cap">Наблюдение</strong><span class="sl-modal-sub">Посещаемость в реальном времени</span></span>
    <span class="sl-modal-acts">
      <span class="sl-modal-act sl-modal-act-move" title="Переместить" aria-hidden="true"><i class="bi bi-grip-vertical"></i></span>
      <button type="button" class="sl-modal-act" data-sl-full data-expand="Развернуть" data-restore="Свернуть" title="Развернуть" aria-label="Развернуть"><i class="bi `
      + `bi-arrows-angle-expand" aria-hidden="true"></i></button>
      <button type="button" class="sl-modal-act sl-modal-act-close" data-sl-close title="Закрыть" aria-label="Закрыть"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </span>
  </div>
  <div class="sl-modal-bar ab-sw-bar"><div class="ab-sw-segs" role="group" aria-label="Кого показать">${seg}</div>
    <button type="button" class="ab-sw-live" data-ab-sw-pause aria-pressed="false" title="Пауза / продолжить"><i class="ab-sw-dot" aria-hidden="true"></i><span `
    + `data-ab-sw-state>в эфире</span><small>· каждые 10 сек</small></button></div>
  <div class="sl-modal-body">
    <div class="ab-sw-kpis">
      <div><small>На сайте сейчас</small><b data-ab-n="all">${w.all}</b></div>
      <div><small>Пик за час</small><b data-ab-sw="peak">${peak}</b></div>
      <div><small>В среднем за час</small><b data-ab-sw="mean">${mean}</b></div>
      <div><small>Стран</small><b>${lands}</b></div>
    </div>
    <div class="ab-sw-chart"><svg class="ab-w-spark" viewBox="0 0 120 36" preserveAspectRatio="none" role="img" aria-label="Посещаемость за последний час" `
    + `data-ab-spark>${getSpark(d.trend)}</svg><div class="ab-sw-axis"><span>60 мин</span><span>45</span><span>30</span><span>15</span><span>сейчас</span></div></div>
    <div class="ab-w-bar" data-ab-bar>${getBar(w)}</div>
    <div class="ab-sw-cols">
      <div class="ab-sw-box"><p class="ab-sw-cap">${ico('people')} Кто где <small>${d.sessions.length}${d.more ? ' + ' + d.more
      + ' гостей' : ''}</small></p><ul class="ab-sw-rows" data-ab-sw-rows>${getStatsRows(d)}</ul><p class="ab-w-more" data-ab-sw-empty hidden>Сейчас таких на сайте нет</p></div>
      <div class="ab-sw-side">${getStatsTops(d)}</div>
    </div>
  </div>
  <div class="sl-modal-foot ab-sw-foot"><span>Обновлено: <b data-ab-sw-ago>только что</b></span><a class="sl-but" href="../admin.php?name=statistic" title="Статистика `
  + `сайта">${ico('graph-up')} Статистика сайта</a></div>
</dialog>`;
  }

  // The parts of the watch that follow a tick in place
  document.addEventListener('ab:tick', (e) => {
    const d = e.detail;
    const w = d.who;
    document.querySelectorAll('[data-ab-spark]').forEach((svg) => { svg.innerHTML = getSpark(d.trend); });
    document.querySelectorAll('[data-ab-bar]').forEach((bar) => { bar.innerHTML = getBar(w); });
    // The ring of the summary carries its shares in two properties, as the site's donut does
    const people = w.users + (d.sup ? w.admins : 0);
    document.querySelectorAll('[data-ab-ring]').forEach((r) => {
      r.style.setProperty('--ab-a', (w.all && d.sup ? Math.round(w.admins / w.all * 100) : 0) + '%');
      r.style.setProperty('--ab-m', (w.all ? Math.round(people / w.all * 100) : 0) + '%');
      r.style.setProperty('--ab-b', (w.all ? Math.round((people + w.bots) / w.all * 100) : 0) + '%');
    });
    const peak = document.querySelector('[data-ab-sw="peak"]');
    if (peak) peak.textContent = Math.max(...d.trend);
    const mean = document.querySelector('[data-ab-sw="mean"]');
    if (mean) mean.textContent = Math.round(d.trend.reduce((s, v) => s + v, 0) / d.trend.length);
    live.stamp = Date.now();
  });

  // The theme's own toast (slaed.js builds the same node and keeps its function inside), raised by a face
  function toast(text, warn) {
    let el = document.querySelector('.sl-toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'sl-toast';
      el.setAttribute('role', 'status');
      el.innerHTML = '<i class="bi" aria-hidden="true"></i> <span></span>';
      document.body.appendChild(el);
    }
    el.classList.toggle('sl-toast-warn', !!warn);
    el.querySelector('.bi').className = 'bi ' + (warn ? 'bi-exclamation-triangle' : 'bi-chat-dots');
    el.querySelector('span').textContent = text;
    el.classList.add('sl-is-visible');
    clearTimeout(el.abTimer);
    el.abTimer = setTimeout(() => el.classList.remove('sl-is-visible'), 4200);
  }

  /* ---------- The rooms ---------- */

  // The menu blocks of the panel; the screen of the stand is "Блоки и баннеры", so that row is the open one. A face that
  // makes the column its navigation (face.menuOpen) gets the blocks open, the others get them folded out of the way
  function getMenu(id, title, icon, list, open) {
    const rows = list.map(([key, label, ic]) => `<div class="sl-menu-list-item${key === 'blocks'
      ? ' v-menu-on' : ''}"><a href="../admin.php?name=${key}" title="${label}"${key === 'blocks'
      ? ' aria-current="page"' : ''}>${ico(ic)} <span class="ab-name">${label}</span></a></div>`).join('');
    return `<div class="sl-block-sidebar">
  <h3 title="Свёртывание / Развёртывание" data-sl-toggle-control="sl-block-${id}" data-sl-toggle-effect="slide" data-sl-toggle-duration="500"><b>${ico(icon)}<span `
  + `class="ab-name">${title}</span></b><i class="bi bi-arrows-collapse sl-panel-toggle sl-panel-toggle-open" aria-hidden="true"></i><i class="bi bi-arrows-expand `
  + `sl-panel-toggle sl-panel-toggle-closed" aria-hidden="true"></i></h3>
  <div id="sl-block-${id}" class="sl-block-sidebar-content" data-sl-toggle="sl-block-${id}"${open ? '' : ' data-sl-toggle-default="closed"'}>${rows}</div>
</div>`;
  }

  function getStage() {
    const seg = (key, list) => list.map(([v,
      label]) => `<button type="button" data-ab-set="${key}" data-ab-val="${v}" aria-pressed="${state[key] === v}">${label}</button>`).join('');
    const room = [['admin', 'Панель', 'layout-sidebar'], ['site', 'Сайт', 'globe2']].map(([v, label, ic]) =>
      `<a href="?ctx=${v}"${ctx === v ? ' aria-current="page"' : ''}>${ico(ic)} ${label}</a>`).join('');
    return `<div class="ab-stage" data-ab-stage>
  <p class="ab-stage-cap">${ico('sliders')} Пульт стенда <small>блок «Администрация», id 2, bkey admin</small></p>
  <div class="ab-stage-rows">
    <span>Где</span><div class="ab-seg">${room}</div>
    <span>Кто вошёл</span><div class="ab-seg">${seg('role', [['super', 'Суперадминистратор'], ['mod', 'Администратор модулей']])}</div>
    <span>После черты</span><div class="ab-seg">${seg('preset', PRESET_NAMES.concat(state.preset === 'own' ? [['own', 'Своё']] : []))}</div>
    <span>Очередь</span><div class="ab-seg">${seg('queue', [['busy', 'Ждут 26'], ['empty',
    'Всё проверено']])}<button type="button" data-ab-incoming title="Комментарий из корпуса эталона (Д8) приходит на проверку">${ico('chat-dots')} Новый комментарий</button></div>
    <span>На сайте</span><div class="ab-seg">${seg('crowd', [['stand', 'Стенд · 2'], ['busy', 'Оживлённо · 37']])}</div>
    ${ctx === 'site' ? `<span>Колонка</span><div class="ab-seg">${seg('col', [['on', 'Есть'], ['off', 'Страница без колонки']])}</div>` : ''}
    ${window.AB_FACE && window.AB_FACE.stage ? window.AB_FACE.stage() : ''}
  </div>
  <p class="ab-stage-note">${window.AB_FACE && window.AB_FACE.stageNote ? window.AB_FACE.stageNote(ctx) : ctx === 'admin'
    ? 'Колонка — как на любом экране панели: меню «Администратор» и «Модули» свёрнуты, ниже блок и «Наблюдение». Поле «Содержание» справа — то, что владелец пишет после '
    + 'черты; правка видна в блоке сразу.'
    : 'Лента «Новости» глазами вошедшего администратора: колонка справа — как на сайте, блок стоит первым. «Страница без колонки» — главная-презентация и другие страницы, '
      + 'где блоку негде встать.'}</p>
</div>`;
  }

  function getAdminRoom(d, face) {
    const tabs = [['', 'Главная'], ['add', 'Добавить блок'], ['fileadd', 'Добавить файловый блок'], ['edit', 'Редактировать блок'], ['info', 'Справка']]
      .map(([op, label]) => `<li class="sl-tabs-item"><a href="../admin.php?name=blocks${op ? '&amp;op=' + op : ''}" title="${label}" class="sl-tabs-link${op === 'edit'
        ? ' sl-is-active' : ''}">${label}</a></li>`).join('');
    const bar = [['admin.php', 'Главная', 'house-door'], ['admin.php?name=blocks', 'Блоки и баннеры', 'grid-3x3-gap'], ['admin.php?name=modules', 'Модули', 'gpu-card'],
      ['admin.php?name=categories', 'Категории', 'folder'], ['index.php', 'Сайт', 'globe2', 'sl-split-item'], ['index.php?name=account', 'Профиль', 'person'],
      ['admin.php?op=logout', 'Выход', 'box-arrow-right', 'sl-split-item']]
      .map(([href, label, ic, cls]) => `<li${cls ? ` class="${cls}"` : ''}><a href="../${href}" title="${label}">${ico(ic)} <b>${label}</b></a></li>`).join('');
    return `<div class="sl-wrapper sl-admin-shell">
  <div class="sl-wraptop"></div>
  <div class="sl-wrapmid">
    <div class="sl-header sl-admin-header">
      <div class="sl-admin-brand"><h1><a href="../admin.php" title="Название сайта"><img src="../templates/admin/images/logos/slaed-logo-wordmark-gradient-blue.svg" `
      + `alt="Название сайта" title="Название сайта"></a></h1></div>
      <div class="sl-admin-toolbar"><div class="sl-toolbar"><div><ul class="sl-reset">${bar}<li class="sl-set-item"><button type="button" data-sl-open="sl-settings" `
      + `title="Опции">${ico('sliders')} <b>Опции</b></button></li></ul></div></div></div>
    </div>
    <div class="sl-admin-main">
      <aside class="sl-admin-sidebar">${face.sideTop ? face.sideTop(d) : ''}${getMenu(1, 'Администратор', 'person-gear', MENU, face.menuOpen)}${getMenu(2, 'Модули', 'people',
      MODULES, face.menuOpen)}<div class="ab-host" data-ab-host style="display: contents">${face.blocks(d)}</div></aside>
      <main class="sl-admin-content"><div class="sl-admin-content-inner">
        <div class="sl-box">
          <div class="sl-module-head"><div class="sl-module-head-icon">${ico('grid-3x3-gap')}</div><div class="sl-module-head-body"><h1 class="sl-module-head-title">Блоки и `
          + `баннеры</h1></div></div>
          <ul class="sl-tabs-nav sl-tabs-nav-main">${tabs}</ul>
        </div>
        <div class="sl-alert sl-alert-info"><div class="sl-alert-body">${face.formAlert || 'Блок: Администрация (HTML блок)'}</div></div>
        ${getStage()}
        <div class="sl-box ab-form">
          <div class="sl-div-form"><div class="sl-div-grid">
            <div class="sl-div-item"><div class="sl-div-label"><label for="ab-title">Заголовок</label><span class="sl-small">Предусмотрено использование языковых `
            + `констант.</span></div>
              <div class="sl-div-field"><input type="text" id="ab-title" maxlength="50" value="${esc(state.title)}" data-ab-title></div></div>
            <div class="sl-div-item sl-div-item-full"><div class="sl-div-label"><label for="ab-content">Содержание</label></div>
              <div class="sl-div-field"><textarea id="ab-content" rows="7" data-ab-own>${esc(state.own)}</textarea><span class="ab-form-hint">${face.formHint
              || 'Блок сам выводит «Панель администратора» и «Выход»; написанное здесь встаёт после черты и видно только суперадминистратору.'}</span></div></div>
            ${face.formTail ? face.formTail(d) : ''}
          </div></div>
        </div>
        <div class="sl-box" data-demo-about></div>
      </div></main>
    </div>
    <div class="sl-footer sl-admin-footer"><div class="sl-copyright">Генерация: 0.233 сек. и 5 запросов к базе данных за 0.002 сек.<br><a href="https://slaed.net" `
    + `title="SLAED CMS" target="_blank" rel="noopener noreferrer">SLAED CMS</a> © 2005-2026 Eduard Laas. Released under MIT License.</div></div>
  </div>
  <div class="sl-wrapfoot"></div>
</div>`;
  }

  const NEWS = [
    ['SLAED CMS возвращается — мощнее, быстрее, безопаснее', '14.08.2026', 'Наши новости',
      'Версия 8.0 Phoenix переписана под PHP 8.4: подготовленные запросы, токены во всех формах, htmx вместо перезагрузок и две темы на одном контракте токенов.'],
    ['Sitemap, XML Карта сайта — SEO инструмент новой версии системы', '02.04.2017', 'Наши новости',
      'Генератор карты сайта входит в стандартную поставку: XML-версия для поисковиков и HTML-версия для посетителей, разбивка по 50 000 адресов.'],
    ['Mozilla Firefox прекращает поддержку плагинов', '09.03.2017', 'Программы',
      'Firefox 52 закончил поддержку NPAPI-плагинов, кроме Adobe Flash; следом от них отказались и другие браузеры.'],
  ];

  function getSiteRoom(d) {
    const news = NEWS.map(([t, date, cat, text]) => `<article class="ab-news"><h2><a href="#">${t}</a></h2>
  <div class="ab-news-meta"><span class="sl-chip sl-chip-info">${ico('calendar3')} ${date}</span><span class="sl-chip sl-chip-success">${ico('folder')} ${cat}</span></div>
  <p>${text}</p></article>`).join('');
    return `<main class="d-page" data-demo-page data-demo-band="Новости">${getStage()}<h1 class="sl-title">Новости</h1>${news}</main>
<template data-demo-side><div class="ab-host" data-ab-host style="display: contents">${window.AB_FACE.blocks(d)}</div></template>`;
  }

  /* ---------- Boot and redraw ---------- */

  let face = null;

  function setRoomSync() {
    root.toggleAttribute('data-ab-nocol', ctx === 'site' && state.col === 'off');
    document.querySelectorAll('[data-ab-set]').forEach((b) => b.setAttribute('aria-pressed', String(state[b.dataset.abSet] === b.dataset.abVal)));
  }

  function setDock(d) {
    let dock = document.querySelector('[data-ab-dock]');
    const html = face.dock ? face.dock(d) : '';
    if (!html) {
      if (dock) dock.remove();
      return;
    }
    if (!dock) {
      dock = document.createElement('div');
      dock.setAttribute('data-ab-dock', '');
      document.body.appendChild(dock);
    }
    dock.innerHTML = html;
  }

  function setRedraw() {
    const d = getData();
    document.querySelectorAll('[data-ab-host]').forEach((host) => {
      host.innerHTML = face.blocks(d);
      host.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true }));
      if (face.after) face.after(host, d);
    });
    setDock(d);
    setRoomSync();
  }

  // Figures marked data-ab-n follow the data without a redraw, so an open pop or a running animation survives a tick
  function setFigures(d) {
    document.querySelectorAll('[data-ab-n]').forEach((el) => {
      const k = el.dataset.abN;
      const v = k === 'waiting' ? d.waiting : k.startsWith('q-') ? (d.queue.find((q) => q.key === k.slice(2)) || {}).n : d.who[k];
      if (v === undefined || el.textContent === String(v)) return;
      el.textContent = v;
      el.classList.remove('ab-bump');
      void el.offsetWidth;
      el.classList.add('ab-bump');
    });
  }

  // A refresh every ten seconds while the site is busy: a guest comes or goes and the hour gets its newest point
  function setTick() {
    setInterval(() => {
      if (state.crowd === 'stand' || document.hidden || live.paused) return;
      live.drift = Math.max(-3, Math.min(4, live.drift + [-1, 0, 1, 1, -1, 2, -2][Math.floor(Math.random() * 7)]));
      const d = getData();
      live.trend = live.trend.slice(1).concat(d.who.all);
      const next = getData();
      setFigures(next);
      document.dispatchEvent(new CustomEvent('ab:tick', { detail: next }));
    }, 10000);
  }

  function setIncoming() {
    const item = INCOMING[live.next % INCOMING.length];
    live.next += 1;
    live.add.comments = (live.add.comments || 0) + 1;
    const d = getData();
    setFigures(d);
    document.dispatchEvent(new CustomEvent('ab:incoming', { detail: { item, d } }));
  }

  // The window of the statistics: the filter by kind, the pause of the live refresh, the age of the last one; and the
  // "Обновить" chip of every summary, which refreshes at once as the hx-get of the site's chip does
  function setStatsLive() {
    const ago = () => {
      const el = document.querySelector('[data-ab-sw-ago]');
      if (!el) return;
      const sec = Math.round((Date.now() - live.stamp) / 1000);
      el.textContent = live.paused ? 'на паузе' : sec < 2 ? 'только что' : sec + ' сек назад';
    };
    setInterval(ago, 1000);
    document.addEventListener('click', (e) => {
      const kind = e.target.closest('[data-ab-sw-kind]');
      if (kind) {
        const k = kind.dataset.abSwKind;
        document.querySelectorAll('[data-ab-sw-kind]').forEach((b) => b.setAttribute('aria-pressed', String(b === kind)));
        let seen = 0;
        document.querySelectorAll('[data-ab-sw-rows] > li').forEach((li) => {
          const on = k === 'all' || li.dataset.kind === k;
          li.hidden = !on;
          if (on) seen += 1;
        });
        document.querySelector('[data-ab-sw-empty]').hidden = seen > 0;
        return;
      }
      const pause = e.target.closest('[data-ab-sw-pause]');
      if (pause) {
        live.paused = !live.paused;
        pause.setAttribute('aria-pressed', String(live.paused));
        pause.querySelector('[data-ab-sw-state]').textContent = live.paused ? 'пауза' : 'в эфире';
        ago();
        return;
      }
      const refresh = e.target.closest('[data-ab-refresh]');
      if (refresh) {
        refresh.classList.remove('ab-spin');
        void refresh.offsetWidth;
        refresh.classList.add('ab-spin');
        live.stamp = Date.now();
        setFigures(getData());
      }
    });
  }

  function setStage() {
    document.addEventListener('click', (e) => {
      if (e.target.closest('[data-ab-incoming]')) {
        setIncoming();
        return;
      }
      const btn = e.target.closest('[data-ab-set]');
      if (!btn) return;
      state[btn.dataset.abSet] = btn.dataset.abVal;
      if (btn.dataset.abSet === 'preset' && btn.dataset.abVal !== 'own') {
        state.own = PRESETS[btn.dataset.abVal];
        const area = document.querySelector('[data-ab-own]');
        if (area) area.value = state.own;
      }
      setKept();
      setRedraw();
    });
    document.addEventListener('input', (e) => {
      if (e.target.matches('[data-ab-own]')) {
        state.own = e.target.value;
        if (state.preset !== 'own') {
          state.preset = 'own';
          const seg = e.target.closest('main').querySelector('[data-ab-set="preset"]').parentNode;
          if (!seg.querySelector('[data-ab-val="own"]')) seg.insertAdjacentHTML('beforeend',
            '<button type="button" data-ab-set="preset" data-ab-val="own" aria-pressed="true">Своё</button>');
        }
      } else if (e.target.matches('[data-ab-title]')) {
        state.title = e.target.value;
      } else {
        return;
      }
      setKept();
      setRedraw();
    });
    // The room switch of the stand panel: the two rooms stand on different themes, so the switch is a reload
    const kinds = document.querySelector('[data-demo-kinds]');
    if (kinds) kinds.addEventListener('demo-kind', (e) => { location.search = '?ctx=' + e.detail; });
  }

  document.addEventListener('DOMContentLoaded', () => {
    face = window.AB_FACE;
    const d = getData();
    const host = document.createElement('div');
    host.innerHTML = (ctx === 'admin' ? getAdminRoom(d, face) : getSiteRoom(d)) +
      `<span hidden data-demo-kinds="admin:Панель:admin|site:Сайт:site" data-kind="${ctx}"></span>`;
    document.body.prepend(...host.childNodes);
    if (face.bodyEnd) document.body.insertAdjacentHTML('beforeend', face.bodyEnd(d));
    document.body.insertAdjacentHTML('beforeend', statsWin(d));
    setStatsLive();
    if (ctx === 'site') document.body.classList.add('sl-summer');
    setRoomSync();
    setStage();
    setTick();
    // After demo.js has stood the site column up and slaed.js has armed what was there: arm the column too
    setTimeout(() => {
      document.querySelectorAll('[data-ab-host]').forEach((h) => {
        h.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true }));
        if (face.after) face.after(h, d);
      });
      setDock(d);
    }, 0);
  });

  window.AB = { esc, ico, attrs, flag, free, rows, line, block, who, watch, summary, toast, data: () => getData(), state: () => state, sections: () => ({ admin: MENU,
    modules: MODULES }), redraw: () => setRedraw() };
})();
