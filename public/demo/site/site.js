/* The site etalon of the stand (demo/site/): the live news feed of the lite theme seen by a signed-in administrator, with
   the block "Администрация" (variant 08) first in its column and the floating menu (variant 09) over it. The panel etalon
   (../admin/) holds the same menu and block; the two stand on one address, so the panel's Options and the menu's shared
   choices (its position, its pins, the growth of its icons) act here at once. The stand never calls the server */
(() => {
  const doc = document;
  const root = doc.documentElement;

  function getKept(key) {
    try { return localStorage.getItem(key); } catch (e) { return null; }
  }

  function setKept(key, value) {
    try {
      if (value === null) localStorage.removeItem(key);
      else localStorage.setItem(key, value);
    } catch (e) { /* storage may be off */ }
  }

  function getEsc(text) {
    const box = doc.createElement('span');
    box.textContent = text;
    return box.innerHTML;
  }

  function setWindow(box, open) {
    if (!box) return;
    if (open) (window.setWindowOpen || ((d) => d.showModal()))(box);
    else (window.setWindowClose || ((d) => d.close()))(box);
  }

  const ICO = (name) => '<i class="bi bi-' + name + '" aria-hidden="true"></i>';

  /* ---------- С12 The pill of the site (setToast of slaed.js), with "Отменить" for ten seconds ---------- */

  let toastTimer = 0;
  function setToast(text, undo, warn) {
    let toast = doc.querySelector('.sl-toast');
    if (!toast) {
      toast = doc.createElement('div');
      toast.className = 'sl-toast';
      toast.setAttribute('role', 'status');
      toast.innerHTML = '<i class="bi" aria-hidden="true"></i> <span></span><button type="button" class="sl-toast-undo" hidden>Отменить</button>';
      doc.body.appendChild(toast);
    }
    toast.classList.toggle('sl-toast-warn', !!warn);
    toast.querySelector('.bi').className = 'bi ' + (warn ? 'bi-exclamation-triangle' : 'bi-check-lg');
    toast.querySelector('span').textContent = text;
    const btn = toast.querySelector('.sl-toast-undo');
    btn.hidden = !undo;
    btn.onclick = undo ? () => { undo(); toast.classList.remove('sl-is-visible'); } : null;
    toast.classList.add('sl-is-visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('sl-is-visible'), undo ? 10000 : 2600);
  }

  /* ---------- What the stand knows: the sections of the panel, its queue, the owner's links ---------- */

  const MENU = {
    admin: [['admins', 'Администрация', 'person-gear'], ['blocks', 'Блоки и баннеры', 'grid-3x3-gap'], ['categories', 'Категории', 'folder'],
      ['comments', 'Комментарии', 'chat-dots'], ['config', 'Конфигурации', 'gear'], ['database', 'База данных', 'database'],
      ['editor', 'Редактор', 'pencil-square'], ['favorites', 'Фавориты', 'star'], ['fields', 'Дополнительные поля', 'input-cursor-text'],
      ['groups', 'Группы', 'people-fill'], ['lang', 'Редактор языков', 'translate'], ['messages', 'Сообщения', 'envelope'],
      ['modules', 'Модули', 'puzzle'], ['monitor', 'Мониторинг', 'display'], ['newsletter', 'Рассылка', 'send'],
      ['privat', 'Личные сообщения', 'incognito'], ['ratings', 'Рейтинги', 'star-half'], ['referers', 'Переходы', 'box-arrow-in-right'],
      ['replace', 'Замена слов', 'arrow-left-right'], ['scheduler', 'Планировщик', 'clock-history'], ['security', 'Безопасность', 'shield-lock'],
      ['statistic', 'Статистика', 'graph-up'], ['template', 'Шаблон', 'palette'], ['uploads', 'Загрузки', 'cloud-arrow-up']],
    modules: [['account', 'Пользователи', 'people'], ['changelog', 'Журнал изменений', 'journal-text'], ['contact', 'Обратная связь', 'envelope-open'],
      ['forum', 'Форум', 'chat-square-text'], ['node', 'Материалы', 'collection'], ['rss', 'RSS каналы', 'rss'], ['search', 'Поиск', 'search'],
      ['sitemap', 'Карта сайта', 'diagram-3'], ['voting', 'Опросы', 'bar-chart']],
  };
  // С8 The queue the panel home calls "Ждут внимания" (Г8 of the panel etalon): the four with a figure
  const QUEUE = [
    ['users', 'Новые пользователи', 'person-plus', '/admin.php?name=account&op=newuser', 18, 'var(--sl-success)'],
    ['comments', 'Комментарии ждут проверки', 'chat-dots', '/admin.php?name=comments', 3, 'var(--sl-primary)'],
    ['help', 'Помощь', 'life-preserver', '/admin.php?name=node&type=help', 3, 'var(--sl-accent)'],
    ['news', 'Новости ждут проверки', 'newspaper', '/admin.php?name=node&type=news&status=1', 2, 'var(--sl-warning)'],
  ];
  const WAITING = QUEUE.reduce((s, q) => s + q[4], 0);
  // С13 The owner's links of block id 2, the same five as in the panel (А6)
  const OWN = [
    ['/plugins/sxd/index.php', 'DB Backup - Sypex Dumper', 'database-down', 'var(--sl-primary)', true],
    ['https://github.com/SLAED-CMS/SLAED-CMS-6.3', 'SLAED CMS на GitHub', 'github', 'var(--sl-accent)', false],
    ['https://pagespeed.web.dev/', 'PageSpeed Insights', 'speedometer', 'var(--sl-success)', false],
    ['https://search.google.com/search-console', 'Google Search Console', 'google', 'var(--sl-primary)', false],
    ['https://webmaster.yandex.ru/', 'Яндекс Вебмастер', 'bar-chart-line', 'var(--sl-warning)', false],
  ];

  /* ---------- С6–С11 The floating menu on the site ---------- */

  const DOCK_PINS = ['blocks', 'comments', 'config', 'database', 'node', 'account'];
  const DOCK_MAX = 8;
  const DOCK_EDGE = 12;
  const DOCK_TONES = ['var(--sl-primary)', 'var(--sl-success)', 'var(--sl-accent)', 'var(--sl-warning)'];
  // С11 The place dragged on the site and its axis are the site's own; the edge, the pins and the growth are shared with the panel
  const KEY = { on: 'sl.dock.site', at: 'sl.dock.site.at', dir: 'sl.dock.site.dir', fold: 'sl.dock.site.fold', side: 'sl.dock.side', pins: 'sl.dock.pins', mag: 'sl.dock.mag' };

  function getDockPins() {
    try {
      const p = JSON.parse(getKept(KEY.pins) || 'null');
      return Array.isArray(p) ? p : DOCK_PINS.slice();
    } catch (e) { return DOCK_PINS.slice(); }
  }

  function getDockAt() {
    try {
      const a = JSON.parse(getKept(KEY.at) || 'null');
      return a && typeof a.x === 'number' ? a : null;
    } catch (e) { return null; }
  }

  function getDockSide() {
    const side = getKept(KEY.side);
    return ['top', 'right', 'left'].includes(side) ? side : 'bottom';
  }

  // The axis the site was turned to, or the one of the edge from the panel's Options
  function getDockDir() {
    const dir = getKept(KEY.dir);
    if (dir === 'row' || dir === 'col') return dir;
    return ['right', 'left'].includes(getDockSide()) ? 'col' : 'row';
  }

  function getDockSections() {
    const map = {};
    MENU.admin.concat(MENU.modules).forEach(([key, name, icon], i) => {
      map[key] = { key, name, icon, tone: DOCK_TONES[i % DOCK_TONES.length], href: '/admin.php?name=' + key };
    });
    return map;
  }

  // С6 С7 С8 С9 The inside of the pill on the site: the mark into the panel, this page in the panel, the pins, the queue,
  // all the sections, the watch, the owner's links, the admin, the logout; then the three tools
  function getDockNav() {
    const map = getDockSections();
    const fold = getKept(KEY.fold) === '1';
    const app = (cls, href, tip, inner, extra = '') => '<a class="sl-dock-app ' + cls + '" href="' + href + '" data-tip="' + getEsc(tip) + '" aria-label="' + getEsc(tip) + '"'
      + extra + '>' + inner + '</a>';
    const btn = (cls, tip, inner, extra = '') => '<button type="button" class="sl-dock-app ' + cls + '" data-tip="' + getEsc(tip) + '" aria-label="' + getEsc(tip) + '"' + extra
      + '>' + inner + '</button>';
    const pins = getDockPins().filter((k) => map[k]).map((k) => app('sl-dock-pin', map[k].href, map[k].name, ICO(map[k].icon), ' style="--sl-dock-c: ' + map[k].tone
      + '"')).join('');
    const queue = QUEUE.map(([key, title, icon, href, n, tone]) => app('sl-dock-qapp', href, title + ' · ' + n, ICO(icon) + '<b class="sl-dock-badge sl-dock-count">' + n + '</b>',
      ' style="--sl-dock-c: ' + tone + '"')).join('');
    const own = OWN.some((o) => o[4]);
    return app('sl-dock-plain', '/admin.php', 'Панель администратора',
      '<img src="/templates/lite/images/logos/slaed-logo-mark-gradient-blue.svg" alt=""><b class="sl-dock-badge sl-dock-count sl-dock-home-badge" title="Ждут внимания: ' + WAITING
        + '">' + WAITING + '</b>')
      + '<div class="sl-dock-body" id="sl-dock-body" data-sl-dock-body' + (fold ? ' hidden style="display: none"' : '') + '>'
      + app('sl-dock-page sl-dock-here', '/admin.php?name=node&amp;type=news', 'Эта страница в панели · вы на ней', ICO('newspaper'),
      ' style="--sl-dock-c: var(--sl-primary)" aria-current="page" data-mark="С7"')
      // С15 The site builder: this page in the editor of sections, menus and theme parts, beside this page in the panel
      + app('sl-dock-page', '../sb-01-builder.html?page=news', 'Изменить страницу', ICO('pencil-square'), ' style="--sl-dock-c: var(--sl-success)" data-mark="С15"')
      + (pins ? '<span class="sl-dock-sep sl-dock-pin"></span><span class="sl-dock-pins">' + pins + '</span>' : '')
      + '<span class="sl-dock-sep"></span>'
      + '<span class="sl-dock-queue" data-mark="С8">' + queue
      + btn('sl-dock-qone', 'Ждут внимания · ' + WAITING, ICO('bell-fill') + '<b class="sl-dock-badge sl-dock-count">' + WAITING + '</b>',
      ' style="--sl-dock-c: var(--sl-danger)" data-sl-dock-pop="queue" aria-controls="sl-dock-pop-queue" aria-expanded="false"') + '</span>'
      + btn('sl-dock-launch', 'Все разделы панели', ICO('grid-3x3-gap-fill'), ' data-sl-dock-pop="launch" aria-controls="sl-dock-pop-launch" aria-expanded="false" data-mark="С9"')
      + btn('sl-dock-watch', 'Кто на сайте: 2 — подробная статистика', '<span>2</span>', ' data-sl-open="sl-stats" style="--sl-d-adm: 50%; --sl-d-people: 50%; --sl-d-bots: 50%"')
      + btn('sl-dock-stack', 'Свои ссылки: ' + OWN.length, ICO('folder2-open') + (own ? '<b class="sl-dock-badge" title="Одна ссылка не работает">!</b>' : ''),
      ' data-sl-dock-pop="stack" aria-controls="sl-dock-pop-stack" aria-expanded="false"')
      + btn('sl-dock-me', 'SLAED CMS', '<img src="/templates/lite/images/avatars/presets/038.svg" alt="">',
      ' data-sl-dock-pop="me" aria-controls="sl-dock-pop-me" aria-expanded="false"')
      + '<span class="sl-dock-sep"></span>'
      + app('sl-dock-plain sl-dock-out', '/admin.php?op=logout', 'Выход', ICO('box-arrow-right'))
      + '</div>'
      + '<span class="sl-dock-sep sl-dock-sep-end"></span>'
      + '<span class="sl-modal-acts sl-dock-tools" data-mark="С11">'
      + '<button type="button" class="sl-modal-act sl-dock-tool sl-dock-turn" data-sl-dock-turn title="Повернуть меню: горизонтально или вертикально" aria-label="Повернуть меню">'
      + ICO('distribute-vertical sl-dock-ax-row') + ICO('distribute-horizontal sl-dock-ax-col') + '</button>'
      + '<button type="button" class="sl-modal-act sl-dock-tool sl-dock-fold" data-sl-dock-fold aria-controls="sl-dock-body" aria-expanded="' + !fold
      + '" title="Свернуть или развернуть меню">'
      + ICO('arrows-collapse-vertical sl-dock-ax-row sl-dock-open') + ICO('arrows-expand-vertical sl-dock-ax-row sl-dock-shut') + ICO('arrows-collapse sl-dock-ax-col sl-dock-open')
      + ICO('arrows-expand sl-dock-ax-col sl-dock-shut') + '</button>'
      + '<button type="button" class="sl-modal-act sl-modal-act-move sl-dock-tool sl-dock-grip" data-sl-dock-grip title="Перетащить меню" aria-label="Переместить меню: '
      + 'перетащите мышью или двигайте стрелками; Home — вернуть на место">' + ICO('grip-vertical sl-dock-ax-row') + ICO('grip-horizontal sl-dock-ax-col') + '</button>'
      + '</span>';
  }

  // С9 All the sections of the panel as one grid with a search; the star pins a section into the menu
  function getDockLaunch() {
    const map = getDockSections();
    const pins = getDockPins();
    const tile = ([key]) => {
      const s = map[key];
      const on = pins.includes(key);
      return '<div class="sl-dock-lp-item" data-sl-dock-lp="' + getEsc(s.name.toLowerCase()) + '"><a href="' + s.href + '" style="--sl-dock-c: ' + s.tone + '"><span>' + ICO(s.icon)
        + '</span><span>' + getEsc(s.name) + '</span></a>'
        + '<button type="button" class="sl-dock-star" data-sl-dock-star="' + key + '" aria-pressed="' + on + '" title="' + (on
        ? 'Открепить от плавающего меню' : 'Закрепить в плавающем меню') + '" aria-label="' + (on ? 'Открепить' : 'Закрепить') + ': ' + getEsc(s.name) + '">' + ICO(on
        ? 'star-fill' : 'star') + '</button></div>';
    };
    return '<p class="sl-dock-cap">' + ICO('grid-3x3-gap') + ' Все разделы панели<small>Звезда закрепляет раздел в плавающем меню — до ' + DOCK_MAX
      + ', список общий с панелью</small></p>'
      + '<input type="search" class="sl-field sl-dock-find" placeholder="Найти раздел" aria-label="Найти раздел" data-sl-dock-find>'
      + '<p class="sl-dock-lp-group">Администратор</p><div class="sl-dock-lp">' + MENU.admin.map(tile).join('') + '</div>'
      + '<p class="sl-dock-lp-group">Модули</p><div class="sl-dock-lp">' + MENU.modules.map(tile).join('') + '</div>'
      + '<p class="sl-dock-lp-empty" data-sl-dock-lp-empty hidden>Раздел не найден</p>';
  }

  function setDockFind(field) {
    const q = field.value.trim().toLowerCase();
    const pop = field.closest('.sl-dock-pop');
    let seen = 0;
    pop.querySelectorAll('[data-sl-dock-lp]').forEach((it) => {
      it.hidden = !!q && !it.dataset.slDockLp.includes(q);
      if (!it.hidden) seen += 1;
    });
    pop.querySelectorAll('.sl-dock-lp-group').forEach((g) => { g.hidden = !!q && !g.nextElementSibling.querySelector('[data-sl-dock-lp]:not([hidden])'); });
    pop.querySelector('[data-sl-dock-lp-empty]').hidden = seen > 0;
  }

  // С6 Build the layer once; it stands on its own point over the page and is put back there on every load
  function setDock() {
    const dir = getDockDir();
    const mag = getKept(KEY.mag) !== '0';
    const box = doc.createElement('div');
    box.className = 'sl-dock';
    box.setAttribute('data-sl-dock', '');
    box.dataset.dir = dir;
    box.dataset.to = 'up';
    box.toggleAttribute('data-min', getKept(KEY.fold) === '1');
    const pop = (id, html, wide) => '<div class="sl-dock-pop' + (wide ? ' sl-dock-pop-wide' : '') + '" id="sl-dock-pop-' + id + '" data-sl-dock-pop-id="' + id + '" hidden>' + html
      + '</div>';
    const grid = '<div class="sl-dock-grid">' + OWN.map(([href, name, icon, tone, dead]) => '<a href="' + href + '" target="_blank" rel="noopener noreferrer" title="' + (dead
      ? 'Не работает: файла plugins/sxd/index.php нет на сервере' : getEsc(name)) + '"><span style="--sl-dock-c: ' + tone + '">' + ICO(icon) + '</span><span>' + getEsc(name)
      + '</span>' + (dead ? '<i class="sl-dock-dead" title="Нет файла"></i>' : '') + '</a>').join('') + '</div>';
    const qlist = '<div class="sl-dock-qlist">' + QUEUE.map(([key, title, icon, href, n]) => '<a href="' + href + '">' + ICO(icon) + ' ' + title + ' <b>' + n + '</b></a>').join('')
      + '</div>';
    box.innerHTML = pop('launch', getDockLaunch(), true)
      + pop('queue', '<p class="sl-dock-cap">' + ICO('bell') + ' Ждут внимания<small>Всего ' + WAITING + ' · в панели это колокольчик и «Ждут внимания»</small></p>' + qlist)
      + pop('stack', '<p class="sl-dock-cap">' + ICO('folder2-open') + ' Свои ссылки<small>Добавляются в настройках блока «Администрация»</small></p>' + grid)
      + pop('me', '<div class="sl-dock-me-head"><img src="/templates/lite/images/avatars/presets/038.svg" alt=""><b>SLAED CMS</b><small>Суперадминистратор</small></div>'
        + '<dl class="sl-dock-facts"><dt>Вошёл</dt><dd>сегодня, 20:31</dd><dt>Прошлый вход</dt><dd>вчера, 21:14</dd><dt>IP-адрес</dt><dd>127.0.0.1</dd><dt>До конца '
        + 'сеанса</dt><dd>47 мин</dd></dl>'
        + '<div class="sl-dock-row">Положение меню <span class="sl-dock-pair"><button type="button" data-sl-dock-dir="row" aria-pressed="' + (dir === 'row')
        + '" title="Горизонтально" aria-label="Горизонтально">' + ICO('three-dots') + '</button><button type="button" data-sl-dock-dir="col" aria-pressed="' + (dir === 'col')
        + '" title="Вертикально" aria-label="Вертикально">' + ICO('three-dots-vertical') + '</button></span></div>'
        + '<button type="button" class="sl-dock-row" data-sl-dock-home>Вернуть на место ' + ICO('arrow-counterclockwise') + '</button>'
        + '<label class="sl-dock-row">Увеличивать иконки под курсором <input type="checkbox" class="sl-check" data-sl-dock-mag' + (mag ? ' checked' : '') + '></label>'
        + '<button type="button" class="sl-dock-row" data-sl-dock-fold>Свернуть меню ' + ICO('arrows-collapse-vertical') + '</button>'
        + '<p class="sl-dock-note">Меню можно перетащить за ручку в любое место экрана — сайт запомнит своё место. С клавиатуры: стрелки двигают меню, Home возвращает его на '
        + 'место. Позиция и отключение меню на сайте — в «Опциях» панели.</p>')
      + '<nav class="sl-dock-bar" aria-label="Плавающее меню администратора" data-mark="С6">' + getDockNav() + '</nav>';
    doc.body.append(box);
    setDockPlace();
    doc.addEventListener('pointermove', setDockGrow);
    setDockDrag(box);
    window.addEventListener('resize', setDockPlace);
  }

  // Stand the layer on its point, keep it whole in the window, turn it to the room; upright on a short screen the tiles
  // give way just enough for the pill to stand whole, never under 32 px
  function setDockPlace() {
    const box = doc.querySelector('[data-sl-dock]');
    if (!box) return;
    const phone = window.matchMedia('(max-width: 560px)').matches;
    box.dataset.dir = phone ? 'row' : getDockDir();
    const bar = box.querySelector('.sl-dock-bar');
    bar.style.removeProperty('--sl-dock-tile');
    if (box.dataset.dir === 'col') {
      const room = window.innerHeight - DOCK_EDGE * 2;
      for (let tile = 44; tile >= 32 && bar.getBoundingClientRect().height > room; tile -= 2) bar.style.setProperty('--sl-dock-tile', (tile - 2) + 'px');
    }
    // С11 With no point of its own the pill stands on the edge from the panel's Options; upright on the top or the bottom
    // edge it stands at the middle of the right one, not across the content
    const side = getDockSide();
    const col = box.dataset.dir === 'col';
    const edge = { top: { x: 0.5, y: 0 }, right: { x: 1, y: 0.5 }, left: { x: 0, y: 0.5 } }[col && (side === 'top' || side === 'bottom') ? 'right' : side];
    const at = getDockAt() || edge || null;
    if (at && !phone && !box.hasAttribute('data-drag')) {
      box.setAttribute('data-placed', '');
      const r = box.getBoundingClientRect();
      box.style.setProperty('--sl-dock-x', Math.min(Math.max(at.x * window.innerWidth, r.width / 2 + DOCK_EDGE), window.innerWidth - r.width / 2 - DOCK_EDGE) + 'px');
      box.style.setProperty('--sl-dock-y', Math.min(Math.max(at.y * window.innerHeight, r.height / 2 + DOCK_EDGE), window.innerHeight - r.height / 2 - DOCK_EDGE) + 'px');
    } else if (!box.hasAttribute('data-drag')) {
      box.removeAttribute('data-placed');
      box.style.removeProperty('--sl-dock-x');
      box.style.removeProperty('--sl-dock-y');
    }
    setDockTurn(box);
    setDockLift(box);
  }

  function setDockTurn(box) {
    const r = box.getBoundingClientRect();
    const cx = r.left + r.width / 2;
    const cy = r.top + r.height / 2;
    box.dataset.to = box.dataset.dir === 'col' ? (cx < window.innerWidth / 2 ? 'right' : 'left') : (cy > window.innerHeight * 0.45 ? 'up' : 'down');
  }

  // С12 The pill of the site keeps its home at the bottom centre and steps over the menu lying there; a menu standing tall
  // through the lower part is passed beside
  function setDockLift(box) {
    const r = box.getBoundingClientRect();
    const hit = !root.hasAttribute('data-sl-dock-off') && r.bottom > window.innerHeight - 110 && r.top > window.innerHeight * 0.6 && r.right > window.innerWidth / 2 - 320
      && r.left < window.innerWidth / 2 + 320;
    if (hit) root.style.setProperty('--sl-dock-lift', (window.innerHeight - r.top + 14) + 'px');
    else root.style.removeProperty('--sl-dock-lift');
  }

  // Only the icon under the hand grows; the scale grows from the middle of the axis, so the centre of the icon stays put
  function setDockGrow(e) {
    const box = doc.querySelector('[data-sl-dock]');
    if (!box || box.hasAttribute('data-drag') || root.hasAttribute('data-sl-dock-off')) return;
    const bar = box.querySelector('.sl-dock-bar');
    const r = bar.getBoundingClientRect();
    const col = box.dataset.dir === 'col';
    const near = e.clientY > r.top - 50 && e.clientY < r.bottom + 50 && e.clientX > r.left - 50 && e.clientX < r.right + 50;
    const flat = getKept(KEY.mag) === '0' || root.hasAttribute('data-sl-still') || e.pointerType === 'touch';
    bar.querySelectorAll('.sl-dock-app').forEach((a) => {
      if (!near || flat) { a.style.setProperty('--sl-dock-s', 1); a.style.removeProperty('--sl-dock-z'); return; }
      const ar = a.getBoundingClientRect();
      if (!ar.width) return;
      const c = col ? ar.top + ar.height / 2 : ar.left + ar.width / 2;
      const grow = Math.max(0, Math.cos(Math.min(Math.abs((col ? e.clientY : e.clientX) - c), 70) / 70 * Math.PI / 2));
      a.style.setProperty('--sl-dock-s', (1 + 0.5 * grow).toFixed(3));
      a.style.setProperty('--sl-dock-z', Math.round(grow * 10));
    });
  }

  // Dragging by the grip: anywhere, and remembered for the site
  function setDockDrag(box) {
    let drag = null;
    box.addEventListener('pointerdown', (e) => {
      const grip = e.target.closest('[data-sl-dock-grip]');
      if (!grip || e.button !== 0) return;
      const r = box.getBoundingClientRect();
      drag = { x: e.clientX, y: e.clientY, dx: e.clientX - (r.left + r.width / 2), dy: e.clientY - (r.top + r.height / 2), moved: false, id: e.pointerId };
      // The page would start its own drag and cancel the pointer after the first step; the grip still takes the focus
      e.preventDefault();
      grip.focus({ preventScroll: true });
      grip.setPointerCapture(e.pointerId);
    });
    box.addEventListener('pointermove', (e) => {
      if (!drag || e.pointerId !== drag.id) return;
      if (!drag.moved && Math.hypot(e.clientX - drag.x, e.clientY - drag.y) < 5) return;
      if (!drag.moved) {
        drag.moved = true;
        setDockClose();
        box.setAttribute('data-drag', '');
        box.setAttribute('data-placed', '');
      }
      const r = box.getBoundingClientRect();
      const x = Math.min(Math.max(e.clientX - drag.dx, r.width / 2 + DOCK_EDGE), window.innerWidth - r.width / 2 - DOCK_EDGE);
      const y = Math.min(Math.max(e.clientY - drag.dy, r.height / 2 + DOCK_EDGE), window.innerHeight - r.height / 2 - DOCK_EDGE);
      box.style.setProperty('--sl-dock-x', x + 'px');
      box.style.setProperty('--sl-dock-y', y + 'px');
      drag.at = [x, y];
      setDockTurn(box);
    });
    // A cancelled pointer ends the drag where it stands rather than leaving the pill held
    const setDrop = (e) => {
      if (!drag || e.pointerId !== drag.id) return;
      const done = drag;
      drag = null;
      if (!done.moved) return;
      box.removeAttribute('data-drag');
      setDockAt(done.at[0], done.at[1]);
    };
    box.addEventListener('pointerup', setDrop);
    box.addEventListener('pointercancel', setDrop);
  }

  // Keep the point as shares of the window; the one the fold holds is not an own place
  function setDockAt(x, y, own = true) {
    const at = { x: +(x / window.innerWidth).toFixed(4), y: +(y / window.innerHeight).toFixed(4) };
    const was = getDockAt();
    if (!own && !(was && was.own !== false)) at.own = false;
    setKept(KEY.at, JSON.stringify(at));
    setDockPlace();
  }

  // С11 Home and "Вернуть на место": back to the edge from the panel's Options, on its axis
  function setDockHome() {
    setKept(KEY.at, null);
    setKept(KEY.dir, null);
    const dir = getDockDir();
    doc.querySelectorAll('[data-sl-dock-dir]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.slDockDir === dir)));
    setDockClose();
    setDockPlace();
  }

  function setDockClose() {
    const box = doc.querySelector('[data-sl-dock]');
    if (!box) return;
    box.removeAttribute('data-open');
    box.querySelectorAll('.sl-dock-pop').forEach((p) => { p.hidden = true; });
    box.querySelectorAll('[data-sl-dock-pop]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
  }

  // The pop over (beside) its icon, held inside the window
  function setDockPop(btn) {
    const box = doc.querySelector('[data-sl-dock]');
    const pop = box.querySelector('[data-sl-dock-pop-id="' + btn.dataset.slDockPop + '"]');
    const open = pop.hidden;
    setDockClose();
    if (!open) return;
    const wb = box.getBoundingClientRect();
    const bb = btn.getBoundingClientRect();
    pop.hidden = false;
    if (box.dataset.to === 'up' || box.dataset.to === 'down') {
      const half = pop.offsetWidth / 2;
      pop.style.setProperty('--sl-dock-px', (Math.min(Math.max(bb.left + bb.width / 2, half + 16), window.innerWidth - half - 16) - wb.left) + 'px');
    } else {
      const half = pop.offsetHeight / 2;
      pop.style.setProperty('--sl-dock-py', (Math.min(Math.max(bb.top + bb.height / 2, half + 16), window.innerHeight - half - 16) - wb.top) + 'px');
    }
    box.setAttribute('data-open', '');
    btn.setAttribute('aria-expanded', 'true');
    const find = pop.querySelector('[data-sl-dock-find]');
    if (find) find.focus();
  }

  // The motion of the column blocks (setSlideMotion, slaed.js) along the axis of the pill, the far end held under the hand
  function setDockFold(shut) {
    const box = doc.querySelector('[data-sl-dock]');
    const body = box.querySelector('[data-sl-dock-body]');
    const col = box.dataset.dir === 'col';
    const prop = col ? 'height' : 'width';
    const size = () => (col ? body.offsetHeight : body.offsetWidth);
    setDockClose();
    box.toggleAttribute('data-min', shut);
    setKept(KEY.fold, shut ? '1' : '0');
    box.querySelector('.sl-dock-bar [data-sl-dock-fold]').setAttribute('aria-expanded', String(!shut));
    body.getAnimations().forEach((a) => a.cancel());
    const r0 = box.getBoundingClientRect();
    const end0 = col ? r0.bottom : r0.right;
    box.setAttribute('data-placed', '');
    box.style.setProperty('--sl-dock-x', (r0.left + r0.width / 2) + 'px');
    box.style.setProperty('--sl-dock-y', (r0.top + r0.height / 2) + 'px');
    if (!shut) {
      body.hidden = false;
      body.style.display = 'flex';
      body.style[prop] = '0px';
      body.style.overflow = 'hidden';
      void body.offsetWidth;
    } else {
      body.style.overflow = 'hidden';
      body.style[prop] = size() + 'px';
    }
    const from = shut ? size() : 0;
    const to = shut ? 0 : (col ? body.scrollHeight : body.scrollWidth);
    let raf = 0;
    const hold = () => {
      const r = box.getBoundingClientRect();
      if (col) box.style.setProperty('--sl-dock-y', (end0 - r.height / 2) + 'px');
      else box.style.setProperty('--sl-dock-x', (end0 - r.width / 2) + 'px');
      raf = requestAnimationFrame(hold);
    };
    const run = body.animate([{ [prop]: from + 'px' }, { [prop]: to + 'px' }], { duration: root.hasAttribute('data-sl-still') ? 1 : 500, easing: 'linear' });
    hold();
    run.onfinish = () => {
      cancelAnimationFrame(raf);
      body.style.overflow = '';
      body.style[prop] = '';
      if (shut) {
        body.style.display = 'none';
        body.hidden = true;
      }
      const r = box.getBoundingClientRect();
      setDockAt(col ? r.left + r.width / 2 : end0 - r.width / 2, col ? end0 - r.height / 2 : r.top + r.height / 2, false);
    };
  }

  function setDockDir(dir) {
    setKept(KEY.dir, dir);
    doc.querySelectorAll('[data-sl-dock-dir]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.slDockDir === dir)));
    setDockClose();
    setDockPlace();
  }

  // С10 The row "Меню на сайте" of the panel's Options, mirrored by the stand
  function setDockOn(on, keep = true) {
    root.toggleAttribute('data-sl-dock-off', !on);
    if (keep) setKept(KEY.on, on ? '1' : '0');
    setDockClose();
    setDockPlace();
    if (setDockOn.onset) setDockOn.onset(on);
  }

  // С9 С12 A star pins or unpins a section; the pill says so and can undo it for ten seconds
  function setDockStar(key) {
    const map = getDockSections();
    const was = getDockPins();
    let pins = was.slice();
    if (pins.includes(key)) pins = pins.filter((k) => k !== key);
    else if (pins.length < DOCK_MAX) pins.push(key);
    else { setToast('В плавающем меню уже ' + DOCK_MAX + ' разделов. Чтобы добавить новый, открепите один из них.', null, true); return; }
    const draw = (list) => {
      setKept(KEY.pins, JSON.stringify(list));
      const box = doc.querySelector('[data-sl-dock]');
      box.querySelector('.sl-dock-bar').innerHTML = getDockNav();
      const lp = box.querySelector('#sl-dock-pop-launch');
      const q = lp.querySelector('[data-sl-dock-find]').value;
      lp.innerHTML = getDockLaunch();
      const field = lp.querySelector('[data-sl-dock-find]');
      field.value = q;
      setDockFind(field);
      setDockPlace();
    };
    draw(pins);
    setToast('Раздел «' + map[key].name + '» ' + (pins.includes(key) ? 'закреплён в плавающем меню' : 'откреплён от плавающего меню') + '.', () => draw(was));
  }

  function setDockClicks(e) {
    const t = e.target;
    if (!t.closest) return;
    const box = doc.querySelector('[data-sl-dock]');
    if (!box) return;
    if (t.closest('[data-sl-dock-home]')) { setDockHome(); return; }
    const star = t.closest('[data-sl-dock-star]');
    if (star) { setDockStar(star.dataset.slDockStar); return; }
    if (t.closest('[data-sl-dock-turn]')) { setDockDir(box.dataset.dir === 'col' ? 'row' : 'col'); box.querySelector('[data-sl-dock-turn]').focus(); return; }
    if (t.closest('[data-sl-dock-fold]')) { setDockFold(!box.hasAttribute('data-min')); box.querySelector('.sl-dock-bar [data-sl-dock-fold]').focus(); return; }
    const dir = t.closest('[data-sl-dock-dir]');
    if (dir) { setDockDir(dir.dataset.slDockDir); return; }
    const mag = t.closest('[data-sl-dock-mag]');
    if (mag) { setKept(KEY.mag, mag.checked ? '1' : '0'); return; }
    const popBtn = t.closest('[data-sl-dock-pop]');
    if (popBtn) { setDockPop(popBtn); return; }
    if (!t.closest('.sl-dock-pop')) setDockClose();
  }

  function setDockKeys(e) {
    if (e.key === 'Escape') { setDockClose(); return; }
    if (!e.target.matches || !e.target.matches('[data-sl-dock-grip]')) return;
    const step = e.shiftKey ? 96 : 24;
    const mv = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key];
    if (e.key === 'Home') { e.preventDefault(); setDockHome(); e.target.focus(); return; }
    if (!mv) return;
    e.preventDefault();
    const r = doc.querySelector('[data-sl-dock]').getBoundingClientRect();
    setDockAt(r.left + r.width / 2 + mv[0], r.top + r.height / 2 + mv[1]);
    e.target.focus();
  }

  /* ---------- С5 The window "Наблюдение": the filter by kind, the pause, the age of the last refresh ---------- */

  function setStats() {
    const win = doc.getElementById('sl-stats');
    if (!win) return;
    let stamp = Date.now();
    let paused = false;
    const ago = () => {
      const el = win.querySelector('[data-sl-stats-ago]');
      const sec = Math.round((Date.now() - stamp) / 1000);
      el.textContent = paused ? 'на паузе' : sec < 2 ? 'только что' : sec + ' сек назад';
    };
    setInterval(() => {
      if (!paused && !doc.hidden && Date.now() - stamp >= 10000) stamp = Date.now();
      ago();
    }, 1000);
    win.addEventListener('click', (e) => {
      const kind = e.target.closest('[data-sl-stats-kind]');
      if (kind) {
        const k = kind.dataset.slStatsKind;
        win.querySelectorAll('[data-sl-stats-kind]').forEach((b) => b.setAttribute('aria-pressed', String(b === kind)));
        let seen = 0;
        win.querySelectorAll('[data-sl-stats-rows] > li').forEach((li) => {
          li.hidden = !(k === 'all' || li.dataset.kind === k);
          if (!li.hidden) seen += 1;
        });
        win.querySelector('[data-sl-stats-empty]').hidden = seen > 0;
        return;
      }
      const pause = e.target.closest('[data-sl-stats-pause]');
      if (pause) {
        paused = !paused;
        stamp = Date.now();
        pause.setAttribute('aria-pressed', String(paused));
        pause.querySelector('[data-sl-stats-state]').textContent = paused ? 'пауза' : 'в эфире';
        ago();
      }
    });
  }

  /* ---------- С1 The live page on a stand: its colour switch works here, its forms and its server calls stay home ---------- */

  function setModeTo(mode) {
    root.dataset.theme = mode;
    setKept('sl.mode', mode === 'auto' ? null : mode);
    doc.querySelectorAll('.sl-mode .sl-seg-cell').forEach((b) => b.setAttribute('aria-pressed', String(b.value === mode)));
    if (setModeTo.onset) setModeTo.onset(mode);
  }

  function setLivePage() {
    doc.addEventListener('submit', (e) => {
      e.preventDefault();
      const mode = e.submitter && e.submitter.name === 'mode' ? e.submitter.value : '';
      if (mode) setModeTo(mode);
      else setToast('Стенд не отправляет формы: это снимок страницы сайта.', null, true);
    }, true);
    doc.addEventListener('click', (e) => {
      const hx = e.target.closest && e.target.closest('[hx-get], [hx-post]');
      if (!hx || hx.closest('[data-sl-dock]')) return;
      e.preventDefault();
      setToast('Стенд не ходит на сервер: это снимок страницы сайта.', null, true);
    }, true);
    setModeTo(root.dataset.theme || 'auto');
  }

  // С2 "Обновить счётчики" of the block: the summary blinks as the hx-get of the live chip does
  function setBlockKeys(e) {
    const fresh = e.target.closest && e.target.closest('[data-sl-sess-refresh]');
    if (!fresh) return;
    const box = doc.querySelector('[data-sl-sess-box]');
    box.setAttribute('aria-busy', 'true');
    setTimeout(() => box.setAttribute('aria-busy', 'false'), 320);
  }

  /* ---------- The stand: marks, their description, the helper window ---------- */

  function setDemoStand() {
    const layer = doc.querySelector('[data-demo-layer]');
    const legend = doc.querySelector('[data-demo-legend]');
    const panel = doc.getElementById('demo-panel');
    if (!layer || !legend || !panel) return;
    const off = new Set(JSON.parse(getKept('demo.site.marks.off') || '[]'));
    const items = [...legend.querySelectorAll('li[data-code]')];
    items.forEach((li) => {
      const code = li.dataset.code;
      const show = li.dataset.show ? ' <button type="button" class="sl-linkbtn" data-demo-show="' + li.dataset.show + '">Показать</button>' : '';
      li.innerHTML = '<input type="checkbox" class="sl-check" data-demo-code="' + code + '" aria-label="Пометка ' + code + '"' + (off.has(code) ? '' : ' checked') + '>'
        + '<span class="sl-chip sl-chip-accent demo-legend-code">' + code + '</span>'
        + '<span class="demo-legend-text">' + li.innerHTML + show + '</span>';
    });
    let wait = 0;
    const draw = () => {
      wait = 0;
      if (!root.hasAttribute('data-demo-marks')) return;
      let html = '';
      const tags = [];
      doc.querySelectorAll('[data-mark]').forEach((el) => {
        const codes = el.dataset.mark.split(' ').filter((c) => !off.has(c));
        const r = el.getBoundingClientRect();
        if (!codes.length || r.width < 4 || r.height < 4 || el.closest('dialog, [hidden]') || r.bottom < 0 || r.top > window.innerHeight) return;
        html += '<span class="demo-mark-box" data-codes="' + codes.join(' ') + '" style="left:' + Math.round(r.left) + 'px;top:' + Math.round(r.top) + 'px;width:'
          + Math.round(r.width) + 'px;height:' + Math.round(r.height) + 'px"></span>';
        const text = codes.join(' · ');
        const tag = { x: Math.min(Math.round(r.left), window.innerWidth - text.length * 7 - 16), y: Math.round(r.top) - 17, w: text.length * 7 + 12 };
        if (tag.y < 0) tag.y = Math.round(r.top) + 1;
        let hit = null;
        let tries = 0;
        while (tries++ < 24 && (hit = tags.find((o) => tag.x < o.x + o.w && o.x < tag.x + tag.w && Math.abs(o.y - tag.y) < 18))) {
          tag.x = hit.x + hit.w + 4;
          if (tag.x + tag.w > window.innerWidth - 4) Object.assign(tag, { x: window.innerWidth - tag.w - 4, y: tag.y > 18 ? tag.y - 18 : tag.y + 36 });
        }
        tags.push(tag);
        html += '<button type="button" class="demo-mark-tag" data-demo-tag="' + codes[0] + '" style="left:' + tag.x + 'px;top:' + tag.y + 'px" tabindex="-1">' + text + '</button>';
      });
      layer.innerHTML = html;
    };
    const later = () => { if (!wait) wait = requestAnimationFrame(draw); };
    const setSeg = (key, val) => panel.querySelectorAll('[data-demo-seg="' + key + '"] [data-demo-val]').forEach((b) => {
      const on = b.dataset.demoVal === val;
      b.classList.toggle('sl-is-active', on);
      b.setAttribute('aria-pressed', String(on));
    });
    const setOn = (on) => {
      root.toggleAttribute('data-demo-marks', on);
      setKept('demo.marks', on ? '1' : '0');
      setSeg('marks', on ? '1' : '0');
      later();
    };
    const setMotion = (on) => {
      root.toggleAttribute('data-sl-still', !on);
      setKept('sl.motion', on ? null : '0');
      setSeg('motion', on ? '1' : '0');
    };
    const setLegend = (open) => setWindow(legend, open);
    setModeTo.onset = (mode) => setSeg('mode', mode);
    setDockOn.onset = (on) => setSeg('dock', on ? '1' : '0');
    const segs = {
      mode: (v) => setModeTo(v),
      marks: (v) => setOn(v === '1'),
      motion: (v) => setMotion(v === '1'),
      dock: (v) => setDockOn(v === '1'),
      legend: (v) => setLegend(v === '1'),
    };
    const fold = panel.querySelector('[data-demo-fold]');
    const phone = window.matchMedia('(max-width: 560px)');
    const setFold = (shut, keep) => {
      const was = panel.offsetHeight;
      panel.querySelectorAll('.sl-modal-body, .sl-modal-foot').forEach((n) => { n.hidden = shut; });
      fold.title = shut ? 'Развернуть' : 'Свернуть';
      fold.setAttribute('aria-label', fold.title);
      fold.setAttribute('aria-expanded', String(!shut));
      fold.firstElementChild.className = 'bi ' + (shut ? 'bi-window' : 'bi-dash-lg');
      if (panel.style.top) panel.style.top = Math.max(0, panel.offsetTop + was - panel.offsetHeight) + 'px';
      if (keep) setKept('demo.site.panel.fold', shut ? '1' : '0');
    };
    const setPanelPlace = () => {
      if (phone.matches) return;
      let at = null;
      try { at = JSON.parse(getKept('demo.site.panel.at') || 'null'); } catch (e) { at = null; }
      // On the site the stand stands top left: the bottom is the menu, wider here than in the panel, and the right is the
      // column with the block it is about
      const left = at ? at[0] : 16;
      const top = at ? at[1] : 16;
      panel.style.left = Math.max(0, Math.min(left, window.innerWidth - panel.offsetWidth)) + 'px';
      panel.style.top = Math.max(0, Math.min(top, window.innerHeight - panel.offsetHeight)) + 'px';
    };
    const setAllBox = () => {
      const all = legend.querySelector('[data-demo-all]');
      all.checked = off.size === 0;
      all.indeterminate = off.size > 0 && off.size < items.length;
    };
    const setFlash = (code) => {
      const el = [...doc.querySelectorAll('[data-mark]')].find((n) => n.dataset.mark.split(' ').includes(code) && n.getClientRects().length && !n.closest('[hidden]'));
      if (!el) return false;
      el.scrollIntoView({ block: 'center', behavior: 'smooth' });
      setTimeout(() => {
        draw();
        layer.querySelectorAll('.demo-mark-box').forEach((b) => b.toggleAttribute('data-flash', b.dataset.codes.split(' ').includes(code)));
        setTimeout(() => layer.querySelectorAll('[data-flash]').forEach((b) => b.removeAttribute('data-flash')), 1600);
      }, 400);
      return true;
    };
    const dockUp = () => { if (root.hasAttribute('data-sl-dock-off')) setDockOn(true); };
    const shows = {
      adm: () => {
        setLegend(false);
        doc.querySelector('.sl-adm').scrollIntoView({ block: 'center', behavior: 'instant' });
        setTimeout(() => setFlash('С2'), 200);
      },
      stats: () => { setLegend(false); setWindow(doc.getElementById('sl-stats'), true); },
      dockmove: () => {
        setLegend(false);
        dockUp();
        setTimeout(() => doc.querySelector('[data-sl-dock-grip]').focus(), 300);
        setToast('Ручка меню выбрана: стрелки двигают меню, с Shift — быстрее, Home возвращает его на место.', null);
      },
      dockturn: () => {
        setLegend(false);
        dockUp();
        const box = doc.querySelector('[data-sl-dock]');
        setDockDir(box.dataset.dir === 'col' ? 'row' : 'col');
      },
      queue: () => {
        setLegend(false);
        dockUp();
        const box = doc.querySelector('[data-sl-dock]');
        if (box.dataset.dir !== 'col') setDockDir('col');
        setTimeout(() => setDockPop(box.querySelector('.sl-dock-qone')), 250);
      },
      launch: () => {
        setLegend(false);
        dockUp();
        setTimeout(() => setDockPop(doc.querySelector('.sl-dock-launch')), 250);
      },
      pin: () => {
        setLegend(false);
        dockUp();
        setDockStar('monitor');
      },
      dockoff: () => {
        setLegend(false);
        const on = root.hasAttribute('data-sl-dock-off');
        setDockOn(on);
        setToast(on ? 'Плавающее меню на сайте включено.' : 'Плавающее меню на сайте выключено — так же, как строкой «Меню на сайте» в «Опциях» панели.', () => setDockOn(!on));
      },
    };
    legend.addEventListener('change', (e) => {
      const c = e.target;
      if (c.matches('[data-demo-all]')) {
        items.forEach((li) => {
          li.querySelector('[data-demo-code]').checked = c.checked;
          if (c.checked) off.delete(li.dataset.code);
          else off.add(li.dataset.code);
        });
      } else if (c.matches('[data-demo-code]')) {
        if (c.checked) off.delete(c.dataset.demoCode); else off.add(c.dataset.demoCode);
      }
      setKept('demo.site.marks.off', JSON.stringify([...off]));
      setAllBox();
      later();
    });
    doc.addEventListener('click', (e) => {
      const t = e.target;
      if (!t.closest) return;
      const cell = t.closest('[data-demo-seg] [data-demo-val]');
      if (cell) segs[cell.closest('[data-demo-seg]').dataset.demoSeg](cell.dataset.demoVal);
      else if (t.closest('[data-demo-fold]')) setFold(fold.getAttribute('aria-expanded') === 'true', true);
      else if (t.closest('[data-demo-reset]')) {
        ['sl.mode', 'demo.marks', 'demo.site.marks.off', 'sl.motion', 'demo.site.panel.at', 'demo.site.panel.fold', KEY.on, KEY.at, KEY.dir, KEY.fold]
          .forEach((k) => setKept(k, null));
        location.href = location.pathname;
      } else if (t.closest('[data-demo-show]')) shows[t.closest('[data-demo-show]').dataset.demoShow]();
      else if (t.closest('.demo-legend-code')) {
        const li = t.closest('li');
        if (!setFlash(li.dataset.code) && li.dataset.show) shows[li.dataset.show]();
      } else if (t.closest('[data-demo-tag]')) {
        const li = legend.querySelector('li[data-code="' + t.closest('[data-demo-tag]').dataset.demoTag + '"]');
        setLegend(true);
        li.scrollIntoView({ block: 'center' });
        li.setAttribute('data-flash', '');
        setTimeout(() => li.removeAttribute('data-flash'), 1600);
      }
    });
    const setTagsLive = (e) => root.toggleAttribute('data-demo-tags-live', e.altKey === true);
    doc.addEventListener('keydown', setTagsLive);
    doc.addEventListener('keyup', setTagsLive);
    window.addEventListener('blur', () => root.removeAttribute('data-demo-tags-live'));
    window.addEventListener('scroll', later, { passive: true, capture: true });
    window.addEventListener('resize', later);
    new MutationObserver((list) => { if (list.some((m) => !layer.contains(m.target))) later(); })
      .observe(doc.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['hidden', 'class', 'style', 'open'] });
    new ResizeObserver(later).observe(doc.body);
    root.addEventListener('transitionend', later);
    doc.addEventListener('pointerup', () => { if (panel.hasAttribute('data-moving')) setKept('demo.site.panel.at', JSON.stringify([panel.offsetLeft, panel.offsetTop])); }, true);
    new MutationObserver(() => setSeg('legend', legend.open ? '1' : '0')).observe(legend, { attributes: true, attributeFilter: ['open'] });
    setAllBox();
    setOn(root.hasAttribute('data-demo-marks'));
    setSeg('motion', root.hasAttribute('data-sl-still') ? '0' : '1');
    setSeg('mode', root.dataset.theme || 'auto');
    setSeg('dock', root.hasAttribute('data-sl-dock-off') ? '0' : '1');
    setSeg('legend', '0');
    panel.inert = true;
    panel.show();
    panel.inert = false;
    if (window.setWindowFront) window.setWindowFront(panel);
    setFold(getKept('demo.site.panel.fold') === '1' || phone.matches, false);
    setPanelPlace();
  }

  function setSite() {
    setLivePage();
    setDock();
    setDockOn(!root.hasAttribute('data-sl-dock-off'), false);
    setStats();
    doc.addEventListener('click', setDockClicks);
    doc.addEventListener('click', setBlockKeys);
    doc.addEventListener('keydown', setDockKeys);
    doc.addEventListener('input', (e) => { if (e.target.matches && e.target.matches('[data-sl-dock-find]')) setDockFind(e.target); });
    // С10 The panel's Options act here at once: a change of the shared keys in the other tab redraws the menu
    window.addEventListener('storage', (e) => {
      if (e.key === KEY.on) setDockOn(e.newValue !== '0', false);
      else if ([KEY.side, KEY.pins, KEY.mag].includes(e.key)) {
        const box = doc.querySelector('[data-sl-dock]');
        box.querySelector('.sl-dock-bar').innerHTML = getDockNav();
        box.querySelector('#sl-dock-pop-launch').innerHTML = getDockLaunch();
        setDockPlace();
      }
    });
    setDemoStand();
  }

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', setSite);
  else setSite();
})();
