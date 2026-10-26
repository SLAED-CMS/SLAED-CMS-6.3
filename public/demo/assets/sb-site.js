/* The canvas of the site builder: a page of the site in the theme lite, drawn from the layout of sb-data.js. The header
   and the footer are the live ones, fetched from the site and then filled from the theme parts and the menus, so
   what the editor changes lands in the very markup a visitor gets. The content between them is the list of sections.

   Two ways in. Inside the editor (?edit=1 in its frame) every part answers the hand: a ring and a label follow the
   pointer, a click picks the part and tells the editor, a text of the picked part is typed in place, and the picked
   section carries its toolbar. Opened on its own it is the published site an administrator sees, with one pill
   "Изменить страницу" that opens the editor on this page.

   Messages from the editor: {sb: 'render', layout, page, sel} and {sb: 'pick', sel}, {sb: 'mode', mode}.
   Messages to the editor: {sb: 'ready'}, {sb: 'pick', sel}, {sb: 'text', path, value, done}, {sb: 'act', act, id}.
   A selection is a string: sec:<id>, part:<key>, menu:<key> or menu:<key>:<item id> */
(function () {
  const params = new URLSearchParams(location.search);
  const edit = params.has('edit') && window.parent !== window;
  const root = document.documentElement;
  const head = document.querySelector('[data-sb-head]');
  const foot = document.querySelector('[data-sb-foot]');
  const main = document.querySelector('[data-sb-page]');
  const st = { layout: null, page: params.get('page') || 'home', sel: '', shell: null, typing: '' };
  if (edit) root.dataset.sbEdit = '';

  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const lines = (v) => esc(v).replace(/\n/g, '<br>');
  const href = (v) => !v ? '#' : /^(https?:|mailto:|#)/.test(v) ? v : v === '/' ? '/' : '/' + v.replace(/^\//, '');
  const blank = (v) => /^https?:/.test(v || '') ? ' target="_blank" rel="noopener"' : '';
  const photo = (key) => (SB_PHOTOS.find((p) => p[0] === key) || SB_PHOTOS[0])[1];
  const text = (path, tag, cls, value, multi) => `<${tag} class="${cls}" data-sb-text="${path}"${multi ? ' data-sb-multi' : ''}>${multi ? lines(value) : esc(value)}</${tag}>`;
  const but = (path, cls, label, link) => label ? `<a class="${cls}" href="${esc(href(link))}"${blank(link)} data-sb-text="${path}">${esc(label)}</a>` : '';

  /* The stub rows of a feed: the news of the pager series where the stand has them, short lists for the rest */
  const FEED = {
    news: (typeof DEMO_PG_NEWS !== 'undefined' ? DEMO_PG_NEWS : [['SLAED CMS возвращается', 'Наши новости']]).map(([t, c]) => [t, c]),
    content: [['О проекте', 'Содержание'], ['Политика конфиденциальности', 'Содержание'], ['Реквизиты', 'Содержание'], ['Как нас найти', 'Содержание']],
    docs: [['Установка на хостинг', 'Документация'], ['Типы Node', 'Документация'], ['Темы и токены', 'Документация'], ['Обновление 8.0', 'Документация']],
    faq: [['Как перенести сайт?', 'Вопросы'], ['Где взять тему?', 'Вопросы'], ['Как включить HTTPS?', 'Вопросы'], ['Что такое Node?', 'Вопросы']],
    links: [['HostMind', 'Хостинг'], ['Молодёжный портал Коми', 'Портал'], ['Feldsher', 'Форум'], ['Start-Drive', 'Автосервис']],
    help: [['Не приходит письмо', 'Помощь'], ['Сброс пароля', 'Помощь'], ['Ошибка 403', 'Помощь'], ['Права на папки', 'Помощь']],
    files: [['SLAED CMS 8.0 Phoenix', 'Система'], ['Тема Lite для 8.0', 'Темы'], ['Редактор CodeMirror 6', 'Редакторы'], ['Модуль «Магазин»', 'Модули'], ['Языковой пакет DE', 'Языки']],
    forum: [['Пожелания к версии 8.1', 'Общий форум'], ['Дата выхода новой версии', 'Новости'], ['Перенос сайта с 6.2', 'Помощь'], ['Своя секция в теме', 'Темы'], ['Ошибка в модуле файлов', 'Помощь']],
  };

  /* One renderer per type of section: the markup the theme fragment sections/<type>.html would carry */
  const DRAW = {
    hero: (s, p) => `
      <div class="sc-hero-photo" style="--sc-photo: url('${esc(photo(s.photo))}')"></div>
      <div class="sl-wrp sc-hero-body">
        ${text(p + 'title', 'h2', 'sl-font sc-hero-title', s.title)}
        ${s.text ? text(p + 'text', 'p', 'sc-hero-text', s.text, true) : ''}
        <p class="sc-acts">${but(p + 'btn', 'sl-but', s.btn, s.href)}${but(p + 'btn2', 'sl-but sc-but-quiet', s.btn2, s.href2)}</p>
      </div>`,
    features: (s, p) => `
      <div class="sl-wrp">
        ${getHeadHtml(s, p, 'intro')}
        <div class="sc-cards">${s.items.map((it, i) => `
          <article class="sc-card">
            <span class="sc-card-ico"><i class="bi bi-${esc(it.icon)}" aria-hidden="true"></i></span>
            <div class="sc-card-body">
              ${text(p + 'items.' + i + '.title', 'h3', 'sl-font sc-card-title', it.title)}
              ${text(p + 'items.' + i + '.text', 'p', 'sc-card-text', it.text, true)}
            </div>
          </article>`).join('')}
        </div>
      </div>`,
    // One module or several: several are dealt in turn, each row then names its module instead of its category
    feed: (s, p) => {
      const srcs = [].concat(s.source);
      const label = (k) => (SB_NODE_TYPES.find((x) => x[0] === k) || SB_NODE_TYPES[0])[1];
      const lists = srcs.map((k) => (FEED[k] || FEED.news).map(([t, c]) => [t, srcs.length > 1 ? label(k) : c, k]));
      const rows = [];
      for (let i = 0; rows.length < Number(s.count) && i < 20; i++) lists.forEach((l) => { if (l[i] && rows.length < Number(s.count)) rows.push(l[i]); });
      const name = label(srcs[0]);
      return `
      <div class="sl-wrp">
        <div class="sc-head sc-head-row">
          ${text(p + 'title', 'h2', 'sl-font sc-title', s.title)}
          ${s.more && srcs.length === 1 ? `<a class="sc-more" href="${esc(href(srcs[0]))}">Все: ${esc(name)}<i class="bi bi-arrow-right" aria-hidden="true"></i></a>` : ''}
        </div>
        <ul class="sc-feed-list">${rows.map(([t, c, k], i) => `
          <li class="sc-feed-item">
            <span class="sc-feed-cat">${esc(c)}</span>
            <a class="sc-feed-title" href="${esc(href(k))}">${esc(t)}</a>
            ${s.date ? `<span class="sc-feed-date"><i class="bi bi-calendar3" aria-hidden="true"></i>${26 - i * 3}.10.2026</span>` : ''}
          </li>`).join('')}
        </ul>
      </div>`;
    },
    media: (s, p) => `
      <div class="sl-wrp sc-media-grid">
        <figure class="sc-media-pic"><img src="${esc(photo(s.photo))}" alt="" loading="lazy" width="2560" height="1440"></figure>
        <div class="sc-media-body">
          ${text(p + 'title', 'h2', 'sl-font sc-title', s.title)}
          ${text(p + 'text', 'p', 'sc-media-text', s.text, true)}
          <p class="sc-acts">${but(p + 'btn', 'sl-but', s.btn, s.href)}</p>
        </div>
      </div>`,
    stats: (s, p) => `
      <div class="sl-wrp">
        ${getHeadHtml(s, p)}
        <dl class="sc-stat-list">${s.items.map((it, i) => `
          <div class="sc-stat">
            ${text(p + 'items.' + i + '.num', 'dt', 'sl-font sc-stat-num', it.num)}
            ${text(p + 'items.' + i + '.label', 'dd', 'sc-stat-label', it.label)}
          </div>`).join('')}
        </dl>
      </div>`,
    sites: (s, p) => {
      const list = (typeof DEMO_SITES !== 'undefined' ? DEMO_SITES : []).slice(0, Number(s.count));
      return `
      <div class="sl-wrp">
        ${getHeadHtml(s, p)}
        <div class="sc-site-list">${list.map((site) => `
          <a class="sc-site" href="/links" title="${esc(site.name)}">
            <img src="../uploads/presentation/sites/thumb/${esc(site.file)}" alt="${esc(site.name)}" width="260" height="${site.height}" loading="lazy">
            <span class="sc-site-name">${esc(site.name)}</span><span class="sc-site-cat">${esc(site.cat)}</span>
          </a>`).join('')}
        </div>
      </div>`;
    },
    faq: (s, p) => `
      <div class="sl-wrp sc-narrow">
        ${getHeadHtml(s, p)}
        ${s.items.map((it, i) => `
          <details class="sl-node-faq"${s.open && i === 0 ? ' open' : ''}>
            <summary><i class="bi bi-chevron-right" aria-hidden="true"></i>${text(p + 'items.' + i + '.q', 'span', 'sl-title', it.q)}</summary>
            ${text(p + 'items.' + i + '.a', 'div', 'sl-node-faq-body', it.a, true)}
          </details>`).join('')}
      </div>`,
    cta: (s, p) => `
      <div class="sl-wrp">
        <div class="sc-cta-box">
          <div class="sc-cta-copy">
            ${text(p + 'title', 'h2', 'sl-font sc-title', s.title)}
            ${text(p + 'text', 'p', 'sc-cta-text', s.text, true)}
          </div>
          ${but(p + 'btn', 'sl-but', s.btn, s.href)}
        </div>
      </div>`,
    // A block of the site by its number, as [block=N] prints it: a fixed free block keeps its where, any other shows anywhere
    block: (s) => {
      const b = (st.layout.blocks || []).find((one) => one.id === s.block);
      if (!b) return edit ? '<div class="sl-wrp"><p class="sc-module-note"><i class="bi bi-bricks" aria-hidden="true"></i>Выберите блок в настройках секции</p></div>' : '';
      const fixed = (b.which || []).includes('flyfix');
      if (!edit && (!b.status || !isBlockSeen(b) || (fixed && !getSbWhereShow(b.which, st.page, st.layout.pages[st.page])))) return '';
      return `<div class="sl-wrp sc-narrow">${getBlockHtml(b)}</div>`;
    },
    html: (s) => s.code,
    // The slot of the module: what its own code prints today, here a stub of its list; it is edited in its section of the panel
    module: (s) => {
      const [, name, icon] = SB_MODULES.find((m) => m[0] === s.module) || SB_MODULES[0];
      const rows = (FEED[s.module] || [1, 2, 3, 4].map((n) => ['Запись модуля «' + name + '» № ' + n, name])).slice(0, 4);
      const body = s.module === 'contact' ? `
          <form class="sc-module-form" onsubmit="return false">
            <input type="text" class="sl-field" placeholder="Ваше имя" aria-label="Ваше имя">
            <input type="email" class="sl-field" placeholder="Почта" aria-label="Почта">
            <textarea class="sl-field" rows="3" placeholder="Сообщение" aria-label="Сообщение"></textarea>
            <button type="button" class="sl-but"><i class="bi bi-send" aria-hidden="true"></i>Отправить</button>
          </form>` : `<ul class="sc-module-list">${rows.map(([t, c], i) => `
            <li><a href="${esc(href(s.module))}">${esc(t)}</a><span class="sc-feed-date"><i class="bi bi-calendar3" aria-hidden="true"></i>${26 - i * 3}.10.2026 · ${esc(c)}</span></li>`).join('')}
          </ul>`;
      return `
      <div class="sl-wrp">
        <div class="sc-head sc-head-row"><h1 class="sl-font sc-title"><i class="bi bi-${icon}" aria-hidden="true"></i> ${esc(name)}</h1>
          ${edit ? '<span class="sc-module-note"><i class="bi bi-lock" aria-hidden="true"></i>Содержимое модуля — правится в его разделе панели</span>' : ''}</div>
        ${body}
      </div>`;
    },
  };

  function getHeadHtml(s, p, intro) {
    return `<div class="sc-head">${text(p + 'title', 'h2', 'sl-font sc-title', s.title)}${intro && s[intro] ? text(p + intro, 'p', 'sc-intro', s[intro], true) : ''}</div>`;
  }

  /* Who sees a section and when, read as a block of the site reads view and expire; the visitor of the published page
     is a guest. The editor sees every section and the flags of what holds it back */
  const today = new Date().toISOString().slice(0, 10);
  const isDated = (s) => (s.from && s.from > today) || (s.till && s.till < today);
  const isShown = (s) => s.on && !isDated(s) && ['0', '3', undefined].includes(s.who);
  const isBlockSeen = (b) => ['0', '3', undefined].includes(b.who) && (!b.lang || b.lang === 'ru');
  const VIEW = { 1: 'Только пользователям', 2: 'Только модераторам', 3: 'Только гостям' };
  const day = (v) => v.split('-').reverse().join('.');

  function getFlagsHtml(s) {
    const flags = [];
    if (VIEW[s.who]) flags.push(['person-lock', VIEW[s.who]]);
    if (s.from || s.till) flags.push(['calendar-range', (s.from ? 'с ' + day(s.from) : '') + (s.from && s.till ? ' ' : '') + (s.till ? 'до ' + day(s.till) : '')]);
    if (s.phone === false) flags.push(['phone', 'Скрыта на телефоне']);
    if (s.desk === false) flags.push(['display', 'Скрыта на компьютере']);
    if (s.anchor) flags.push(['hash', s.anchor]);
    return flags.length ? '<span class="sb-flags">' + flags.map(([i, t]) => `<span class="sl-chip"><i class="bi bi-${i}" aria-hidden="true"></i>${esc(t)}</span>`).join('') + '</span>' : '';
  }

  /* A block of the site in the markup of fragments/block-all.html; its body here is a stub of what its file prints */
  const BLOCK = {
    voting: () => [['Конструктор секций', 62], ['Редактор меню', 24], ['Настройки темы', 14]].map(([t, v]) =>
      `<li class="sc-vote"><span>${t}</span><b>${v}%</b><span class="sc-vote-bar" style="--sc-share: ${v}%"></span></li>`).join(''),
    login: () => '<li><input type="text" class="sl-field" placeholder="Ник" aria-label="Ник"></li><li><input type="password" class="sl-field" placeholder="Пароль" aria-label="Пароль"></li><li><button type="button" class="sl-but">Войти</button></li>',
    stat: () => '<li>Сегодня: 1 204</li><li>Вчера: 1 873</li><li>Всего: 2 418 650</li>',
    search: () => '<li><input type="search" class="sl-field" placeholder="Поиск" aria-label="Поиск"></li>',
    languages: () => '<li>Русский</li><li>English</li><li>Deutsch</li>',
    user_info: () => '<li>Пользователей: 3</li><li>Гостей: 12</li><li>Поисковых ботов: 4</li>',
  };

  /* A block of the site in the markup of fragments/block-all.html, by its kind: the HTML of its field, the headlines of
     its feed or a stub of what its file prints; the centre zone above the content takes the "before content" form */
  function getBlockHtml(b, before) {
    const [, , icon] = b.type === 'html' ? [0, 0, 'code-square'] : b.type === 'rss' ? [0, 0, 'rss'] : (SB_BLOCKS.find((x) => x[0] === b.kind) || SB_BLOCKS[0]);
    const body = b.type === 'html' ? b.content
      : b.type === 'rss' ? '<ul class="sc-block-body">' + ['Заголовок ленты 1', 'Заголовок ленты 2', 'Заголовок ленты 3'].map((t) => `<li><a href="${esc(b.url)}">${t}</a></li>`).join('') + '</ul>'
      : `<ul class="sc-block-body">${(BLOCK[b.kind] || BLOCK.stat)()}</ul>`;
    const head = `<i class="bi bi-${icon}" aria-hidden="true"></i> <span data-sb-text="blk:${b.id}:title">${esc(b.title)}</span>`;
    const off = edit && !b.status ? ' sb-off' : '';
    return before
      ? `<div class="sl-block${off}" data-sb-pick="blk:${b.id}" data-sb-label="Блок «${esc(b.title)}»"><div class="sl-title">${head}</div><div class="sl-block-content">${body}</div></div>`
      : `<section class="sl-block${off}" data-sb-pick="blk:${b.id}" data-sb-label="Блок «${esc(b.title)}»"><h2 class="sl-title">${head}</h2><div class="sl-block-content">${body}</div></section>`;
  }

  /* The zones of a page as setFoot() lays them: side opens the left and the right column, top the centre above and
     under the content; a zone takes the blocks of its position whose where holds this page, in their order */
  function getColumnsHtml(inner, blocks, page) {
    const on = (set, one) => ['', '0', one].includes(String(set ?? '3'));
    const zone = (z) => blocks.filter((b) => b.pos === z).map((b) => getBlockHtml(b, z === 'c')).join('');
    const left = on(page.side, '1') ? zone('l') : '';
    const right = on(page.side, '2') ? zone('r') : '';
    const up = on(page.top, '1') ? zone('c') : '';
    const down = on(page.top, '2') ? zone('d') : '';
    if (!left && !right && !up && !down) return inner;
    const cls = [left ? 'sl-has-left' : '', right ? 'sl-has-right' : ''].filter(Boolean).join(' ');
    return `<div class="sl-wrp"><div id="container"${cls ? ` class="${cls}"` : ''}>${left ? `<aside id="leftbar" data-sb-zone="l">${left}</aside>` : ''}<div id="content" class="sb-main">${up ? `<div class="sb-zone-up" data-sb-zone="c">${up}</div>` : ''}${inner}</div>${right ? `<aside id="sidebar" data-sb-zone="r">${right}</aside>` : ''}</div></div>${down ? `<div class="sl-wrp sb-zone-down" data-sb-zone="d">${down}</div>` : ''}`;
  }

  function getSectionHtml(s) {
    const type = SB_TYPES[s.type];
    const p = 'sec:' + s.id + ':';
    const cls = ['sc', 'sc-' + s.type];
    if (s.phone === false) cls.push('sc-no-phone');
    if (s.desk === false) cls.push('sc-no-desk');
    if (edit && isDated(s)) cls.push('sb-off');
    if (s.type === 'hero') cls.push('sc-hero-' + s.size, 'sc-align-' + s.align, s.veil ? 'sc-veil' : 'sc-clear');
    if (s.type === 'features') cls.push('sc-cols-' + s.cols, 'sc-look-' + s.look);
    if (s.type === 'feed') cls.push('sc-view-' + s.view);
    if (s.type === 'media') cls.push('sc-side-' + s.side);
    if (s.tone) cls.push('sc-tone-' + s.tone);
    if (!s.on) cls.push('sb-off');
    const anchor = s.anchor ? ` id="${esc(s.anchor)}"` : '';
    return `<section class="${cls.join(' ')}"${anchor} data-sb-pick="sec:${s.id}" data-sb-label="${esc(type.title)}">${DRAW[s.type](s, p)}${edit ? getFlagsHtml(s) : ''}</section>`;
  }

  /* ---- The live header and footer, filled from the theme parts and the menus ---- */

  function getMenuHtml(list) {
    const link = (it) => `<a href="${esc(it.href ? href(it.href) : '#')}"${it.blank ? ' target="_blank" rel="noopener"' : ''} title="${esc(it.title)}" data-sb-text="menu:main:${it.id}:title">${esc(it.title)}</a>`;
    return '<li><a href="/" class="sl-home-link sl-circle-action" title="Главная"><i class="bi bi-house-door-fill" aria-hidden="true"></i><span class="sl-hidden">Главная</span></a></li>' +
      list.map((it) => `<li data-sb-pick="menu:main:${it.id}" data-sb-label="Пункт меню">${link(it)}${it.kids.length ? `<ul>${it.kids.map((k) =>
        `<li data-sb-pick="menu:main:${k.id}" data-sb-label="Пункт меню">${link(k)}</li>`).join('')}</ul>` : ''}</li>`).join('');
  }

  function getPaneHtml(t) {
    return `<li class="sl-d-info sl-d-version sl-font" data-sb-text="theme:paneCap">${esc(t.paneCap)}</li>
      <li class="sl-d-num sl-font" data-sb-text="theme:paneNum">${esc(t.paneNum)}</li>
      <li class="sl-d-btns"><a class="sl-but" href="${esc(href(t.paneHref))}" data-sb-text="theme:paneBtn">${esc(t.paneBtn)}</a></li>`;
  }

  function setShell(layout) {
    const sh = st.shell;
    if (!sh) return;
    const t = layout.theme;
    const mark = (node, sel, label) => { if (node) { node.dataset.sbPick = sel; node.dataset.sbLabel = label; } };
    const logo = sh.querySelector('#header .sl-logo');
    if (logo) {
      const img = logo.querySelector('img');
      img.src = new URL('../templates/lite/images/logos/' + t.logo, location.href).href;
      img.removeAttribute('width');
      img.removeAttribute('height');
      [logo, img].forEach((n) => { n.title = t.sitename + ' - ' + t.slogan.replace(/\n/g, ' '); });
      img.alt = logo.title;
      mark(logo, 'part:brand', 'Логотип');
    }
    const slogan = sh.querySelector('#header .sl-slogan');
    if (slogan) {
      slogan.innerHTML = lines(t.slogan);
      slogan.dataset.sbText = 'theme:slogan';
      slogan.dataset.sbMulti = '';
      mark(slogan, 'part:brand', 'Слоган');
    }
    sh.querySelectorAll('.sl-d-pane').forEach((pane) => {
      pane.innerHTML = getPaneHtml(t);
      pane.hidden = !t.paneOn;
      mark(pane, 'part:pane', 'Плашка версии');
    });
    const social = sh.querySelector('#topbar .sl-top-social');
    if (social) {
      social.innerHTML = t.social.map((n, i) => `<a class="sl-thd sl-circle-action sl-cat-tone-${i % 6}" rel="me noopener" target="_blank" href="${esc(n.url)}" title="${esc(n.title)}" aria-label="${esc(n.title)}"><i class="bi bi-${esc(n.icon)}" aria-hidden="true"></i>${esc(n.title)}</a>`).join('');
      mark(social, 'part:social', 'Соцсети');
    }
    mark(sh.querySelector('#topbar .sl-mode'), 'part:look', 'Цвет и сезон');
    const menu = sh.querySelector('#topmenu > ul');
    if (menu) {
      menu.innerHTML = getMenuHtml(layout.menus.main);
      mark(sh.querySelector('#topmenu'), 'menu:main', 'Главное меню');
    }
    const line = sh.querySelector('#demo-line');
    if (line) {
      line.hidden = !t.lineOn;
      const title = line.querySelector('.sl-demo-line-title');
      title.textContent = t.lineTitle;
      title.dataset.sbText = 'theme:lineTitle';
      mark(line, 'part:demoline', 'Полоса перед подвалом');
    }
    const contact = sh.querySelector('#footbox .sl-block-contact');
    if (contact) {
      const row = (icon, html) => `<li><i class="bi bi-${icon} sl-contact-icon" aria-hidden="true"></i>${html}</li>`;
      contact.innerHTML = row('geo-alt', `<span data-sb-text="theme:address" data-sb-multi>${lines(t.address)}</span>`) +
        row('telephone', `<span data-sb-text="theme:phone">${esc(t.phone)}</span>`) +
        row('envelope', `<a href="mailto:${esc(t.email)}" data-sb-text="theme:email">${esc(t.email)}</a>`) +
        row('globe', `<a href="${esc(t.web)}" data-sb-text="theme:web">${esc(t.web)}</a>`);
      mark(contact.closest('section'), 'part:contacts', 'Контакты');
    }
    const tag = sh.querySelector('#footbox .sl-upper-tagline');
    if (tag) {
      tag.textContent = t.tagline;
      tag.dataset.sbText = 'theme:tagline';
      mark(tag.closest('.sl-upper-wordmark'), 'part:contacts', 'Подпись под логотипом');
    }
    const fmenu = sh.querySelector('#footbox .sl-fmenu');
    if (fmenu) {
      fmenu.querySelector('ul').innerHTML = layout.menus.foot.map((it) => `<li data-sb-pick="menu:foot:${it.id}" data-sb-label="Пункт меню"><a href="${esc(href(it.href))}" title="${esc(it.title)}">${it.icon ? `<i class="bi bi-${esc(it.icon)}" aria-hidden="true"></i>` : ''}<span data-sb-text="menu:foot:${it.id}:title">${esc(it.title)}</span></a></li>`).join('');
      mark(fmenu, 'menu:foot', 'Нижнее меню');
    }
  }

  /* The live chrome: header, menu, the band and the footer of the real site, scripts and form tokens dropped */
  function getShell() {
    const url = new URL('../index.php?name=presentation', location.href);
    return fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(async (reply) => {
      if (!reply.ok) throw new Error('HTTP ' + reply.status);
      const doc = new DOMParser().parseFromString(await reply.text(), 'text/html');
      const top = ['topbar', 'header', 'hmenu'].map((id) => doc.getElementById(id));
      const end = ['demo-line', 'footbox'].map((id) => doc.getElementById(id));
      if ([...top, ...end].some((n) => !n)) throw new Error('Missing site header or footer');
      [...top, ...end].forEach((node) => {
        node.querySelectorAll('script, input[name="token"]').forEach((el) => el.remove());
        node.querySelectorAll('[href], [src], [action]').forEach((el) => ['href', 'src', 'action'].forEach((attr) => {
          const v = el.getAttribute(attr);
          if (v && !v.startsWith('#') && !v.startsWith('mailto:')) el.setAttribute(attr, new URL(v, url).href);
        }));
      });
      head.replaceChildren(...top);
      foot.replaceChildren(...end);
      st.shell = document.body;
    }).catch((err) => {
      head.innerHTML = '<p class="sl-wrp sc-shell-fail">Шапка и подвал сайта не загрузились: ' + esc(err.message) + '</p>';
    });
  }

  /* ---- Drawing ---- */

  function setSeason(t) {
    const month = new Date().getMonth();
    const byDate = month === 11 || month === 0 ? 'sl-newyear' : month < 2 ? 'sl-winter' : month < 5 ? 'sl-spring' : month < 8 ? 'sl-summer' : 'sl-autumn';
    const season = t.season === 'date' ? byDate : t.season;
    ['sl-winter', 'sl-spring', 'sl-summer', 'sl-autumn', 'sl-newyear'].forEach((c) => document.body.classList.toggle(c, c === season));
    // "Системная" is the visitor's own system; the stand's switch plays that system
    root.dataset.theme = t.mode === 'auto' ? (st.mode || root.dataset.theme || 'auto') : t.mode;
  }

  function render() {
    const layout = st.layout;
    if (!layout) return;
    const page = layout.pages[st.page] || layout.pages.home;
    const list = page.sections.filter((s) => edit || isShown(s));
    const inner = list.length ? list.map(getSectionHtml).join('') : `
      <div class="sl-wrp sc-empty">
        <i class="bi bi-layout-wtf" aria-hidden="true"></i>
        <p>На странице пока нет ни одной секции.</p>
        ${edit ? '<button type="button" class="sl-but" data-sb-act="add"><i class="bi bi-plus-lg" aria-hidden="true"></i>Добавить секцию</button>' : ''}
      </div>`;
    const blocks = (layout.blocks || []).filter((b) => getSbWhereShow(b.which, st.page, page) && (edit || (b.status && isBlockSeen(b))));
    main.innerHTML = getColumnsHtml(inner, blocks, page);
    setShell(layout);
    setSeason(layout.theme);
    document.title = page.title + ' — ' + layout.theme.sitename;
    if (edit) setRings();
  }

  /* ---- Editing on the page ---- */

  const ring = { hover: null, sel: null, chip: null, bar: null };

  function getRingBox(cls) {
    const box = document.createElement('div');
    box.className = cls;
    box.setAttribute('aria-hidden', 'true');
    document.body.appendChild(box);
    return box;
  }

  function setRingAt(box, node) {
    if (!node || !node.isConnected || node.closest('[hidden]')) {
      box.hidden = true;
      return;
    }
    const r = node.getBoundingClientRect();
    box.hidden = false;
    box.style.setProperty('--sb-x', (r.left + scrollX) + 'px');
    box.style.setProperty('--sb-y', (r.top + scrollY) + 'px');
    box.style.setProperty('--sb-w', r.width + 'px');
    box.style.setProperty('--sb-h', r.height + 'px');
  }

  function getPicked(sel) {
    return sel ? document.querySelector(`[data-sb-pick="${CSS.escape(sel)}"]`) : null;
  }

  function setRings() {
    const node = getPicked(st.sel);
    setRingAt(ring.sel, node);
    ring.sel.querySelector('.sb-ring-name').textContent = node ? node.dataset.sbLabel : '';
    const isSec = st.sel.startsWith('sec:');
    const isBlk = st.sel.startsWith('blk:');
    ring.bar.hidden = !node || !(isSec || isBlk);
    // A block carries the grip and its order in the position; the rest of the toolbar is the section's
    ring.bar.querySelectorAll('[data-sb-act="dup"], [data-sb-act="toggle"], [data-sb-act="del"], .sb-bar-add').forEach((n) => { n.hidden = isBlk; });
    if (node && isBlk) {
      const b = st.layout.blocks.find((one) => 'blk:' + one.id === st.sel);
      const same = st.layout.blocks.filter((one) => one.pos === b.pos && !(one.which || []).includes('infly'));
      const idx = same.indexOf(b);
      ring.bar.querySelector('[data-sb-act="up"]').disabled = idx <= 0;
      ring.bar.querySelector('[data-sb-act="down"]').disabled = idx >= same.length - 1;
    }
    if (node && isSec) {
      const list = st.layout.pages[st.page].sections;
      const idx = list.findIndex((s) => 'sec:' + s.id === st.sel);
      ring.bar.querySelector('[data-sb-act="up"]').disabled = idx <= 0;
      ring.bar.querySelector('[data-sb-act="down"]').disabled = idx >= list.length - 1;
      const on = list[idx] && list[idx].on;
      const eye = ring.bar.querySelector('[data-sb-act="toggle"]');
      eye.title = on ? 'Скрыть' : 'Показать';
      eye.querySelector('i').className = 'bi bi-' + (on ? 'eye-slash' : 'eye');
    }
    document.querySelectorAll('[data-sb-picked]').forEach((n) => n.removeAttribute('data-sb-picked'));
    if (node) node.dataset.sbPicked = '';
  }

  function setEditing() {
    ring.hover = getRingBox('sb-ring sb-ring-hover');
    ring.hover.innerHTML = '<span class="sb-ring-name"></span>';
    ring.sel = getRingBox('sb-ring sb-ring-sel');
    ring.sel.innerHTML = '<span class="sb-ring-name"></span>';
    ring.bar = document.createElement('div');
    ring.bar.className = 'sb-tools';
    ring.bar.innerHTML = '<div class="sb-bar"><button type="button" class="sl-but-mini sb-grip" data-sb-grip title="Перетащить" aria-label="Перетащить"><i class="bi bi-arrows-move" aria-hidden="true"></i></button>' + [['up', 'arrow-up', 'Переместить выше'], ['down', 'arrow-down', 'Переместить ниже'], ['dup', 'copy', 'Дублировать'], ['toggle', 'eye-slash', 'Скрыть'], ['del', 'trash3', 'Удалить']]
      .map(([act, icon, title]) => `<button type="button" class="sl-but-mini" data-sb-act="${act}" title="${title}" aria-label="${title}"><i class="bi bi-${icon}" aria-hidden="true"></i></button>`).join('') +
      '</div><button type="button" class="sl-but sb-bar-add" data-sb-act="add" title="Добавить секцию ниже"><i class="bi bi-plus-lg" aria-hidden="true"></i>Добавить ниже</button>';
    ring.sel.appendChild(ring.bar);
    ring.hover.hidden = true;
    ring.sel.hidden = true;

    document.addEventListener('pointerover', (e) => {
      const node = e.target.closest('[data-sb-pick]');
      if (node && node.dataset.sbPick !== st.sel) {
        setRingAt(ring.hover, node);
        ring.hover.querySelector('.sb-ring-name').textContent = node.dataset.sbLabel;
      } else {
        ring.hover.hidden = true;
      }
    });
    document.addEventListener('pointerleave', () => { ring.hover.hidden = true; });

    document.addEventListener('click', (e) => {
      const act = e.target.closest('[data-sb-act]');
      if (act) {
        e.preventDefault();
        post({ sb: 'act', act: act.dataset.sbAct, kind: st.sel.slice(0, 3), id: /^(sec|blk):/.test(st.sel) ? st.sel.slice(4) : '' });
        return;
      }
      if (e.target.closest('[contenteditable]')) return;
      const node = e.target.closest('[data-sb-pick]');
      const words = e.target.closest('[data-sb-text]');
      // A link, a button, a form never leaves the page being edited; a summary still opens its answer
      if (!e.target.closest('summary')) e.preventDefault();
      if (!node) return;
      const sel = node.dataset.sbPick;
      // The first click picks the part, a click on a text of the picked part types it in place
      if (sel === st.sel && words && getOwner(words) === sel) {
        setTyping(words);
        return;
      }
      st.sel = sel;
      ring.hover.hidden = true;
      setRings();
      post({ sb: 'pick', sel: sel });
    }, true);

    document.addEventListener('dblclick', (e) => {
      const words = e.target.closest('[data-sb-text]');
      if (words && !words.isContentEditable) {
        e.preventDefault();
        const owner = getOwner(words);
        if (owner && owner !== st.sel) {
          st.sel = owner;
          setRings();
          post({ sb: 'pick', sel: owner });
        }
        setTyping(words);
      }
    });

    document.addEventListener('submit', (e) => e.preventDefault(), true);
    setDragging();
    addEventListener('resize', () => requestAnimationFrame(setRings));
    new ResizeObserver(() => requestAnimationFrame(setRings)).observe(document.body);
  }

  /* Drag with the cursor: the grip of the picked section or block follows the pointer; a line shows where it lands - between
     sections, or beside a block in any open zone. The drop is told to the editor, which moves it as the tree does */
  function setDragging() {
    const line = getRingBox('sb-ring sb-drop-line');
    const ghost = document.createElement('div');
    ghost.className = 'sb-ghost';
    ghost.hidden = true;
    document.body.appendChild(ghost);
    line.hidden = true;
    let drag = null;
    const zoneOf = (n) => (n.closest('[data-sb-zone]') || {}).dataset?.sbZone || '';
    const getSpot = (x, y) => {
      ghost.hidden = true;
      const at = document.elementFromPoint(x, y);
      ghost.hidden = false;
      if (!at) return null;
      if (drag.kind === 'sec') {
        const one = at.closest('main .sc[data-sb-pick^="sec:"]');
        if (!one) return null;
        const r = one.getBoundingClientRect();
        return { node: one, to: one.dataset.sbPick.slice(4), after: y > r.top + r.height / 2, zone: '' };
      }
      const one = at.closest('.sl-block[data-sb-pick^="blk:"]');
      if (one && zoneOf(one)) {
        const r = one.getBoundingClientRect();
        return { node: one, to: one.dataset.sbPick.slice(4), after: y > r.top + r.height / 2, zone: zoneOf(one) };
      }
      const box = at.closest('[data-sb-zone]');
      return box ? { node: box, to: '', after: true, zone: box.dataset.sbZone } : null;
    };
    document.addEventListener('pointerdown', (e) => {
      const grip = e.target.closest('[data-sb-grip]');
      if (!grip || !/^(sec|blk):/.test(st.sel)) return;
      e.preventDefault();
      drag = { kind: st.sel.slice(0, 3), id: st.sel.slice(4), spot: null };
      ghost.textContent = getPicked(st.sel).dataset.sbLabel;
      ghost.hidden = false;
      grip.setPointerCapture(e.pointerId);
      root.dataset.sbDragging = '';
    });
    document.addEventListener('pointermove', (e) => {
      if (!drag) return;
      // zoom scales the coordinates of the label too: they are written in its own, zoomed units
      const z = parseFloat(getComputedStyle(root).getPropertyValue('--sb-zoom')) || 1;
      ghost.style.setProperty('--sb-gx', (e.clientX / z) + 'px');
      ghost.style.setProperty('--sb-gy', (e.clientY / z) + 'px');
      const spot = getSpot(e.clientX, e.clientY);
      drag.spot = spot && spot.to !== drag.id ? spot : null;
      if (!drag.spot) {
        line.hidden = true;
        return;
      }
      const r = drag.spot.node.getBoundingClientRect();
      const y = drag.spot.to ? (drag.spot.after ? r.bottom : r.top) : r.bottom;
      line.hidden = false;
      line.style.setProperty('--sb-x', (r.left + scrollX) + 'px');
      line.style.setProperty('--sb-y', (y + scrollY - 2) + 'px');
      line.style.setProperty('--sb-w', r.width + 'px');
      line.style.setProperty('--sb-h', '4px');
      // Near the edges of the window the page scrolls under the hand
      if (e.clientY < 60) scrollBy(0, -12);
      if (e.clientY > innerHeight - 60) scrollBy(0, 12);
    });
    document.addEventListener('pointerup', () => {
      if (!drag) return;
      const { kind, id, spot } = drag;
      drag = null;
      ghost.hidden = true;
      line.hidden = true;
      delete root.dataset.sbDragging;
      if (spot) post({ sb: 'move', kind: kind, id: id, to: spot.to, after: spot.after, zone: spot.zone });
    });
  }

  function getOwner(words) {
    const pick = words.closest('[data-sb-pick]');
    return pick ? pick.dataset.sbPick : '';
  }

  function setTyping(words) {
    const path = words.dataset.sbText;
    const multi = words.dataset.sbMulti !== undefined;
    st.typing = path;
    words.contentEditable = 'plaintext-only';
    words.spellcheck = true;
    words.focus();
    const range = document.createRange();
    range.selectNodeContents(words);
    getSelection().removeAllRanges();
    getSelection().addRange(range);
    const read = () => words.innerText.replace(/\n$/, '');
    const send = (done) => post({ sb: 'text', path: path, value: read(), done: done });
    const onInput = () => { send(false); setRings(); };
    const onKey = (e) => {
      if (e.key === 'Escape' || (e.key === 'Enter' && (!multi || e.ctrlKey))) {
        e.preventDefault();
        words.blur();
      }
    };
    const onBlur = () => {
      words.removeAttribute('contenteditable');
      words.removeEventListener('input', onInput);
      words.removeEventListener('keydown', onKey);
      st.typing = '';
      send(true);
    };
    words.addEventListener('input', onInput);
    words.addEventListener('keydown', onKey);
    words.addEventListener('blur', onBlur, { once: true });
  }

  function post(msg) {
    if (edit) window.parent.postMessage(msg, location.origin);
  }

  /* ---- The two ways in ---- */

  function getLive() {
    try {
      const raw = localStorage.getItem('sb.live');
      if (raw) return setSbLayoutFit(JSON.parse(raw));
    } catch (e) { /* storage may be off */ }
    return getSbStart();
  }

  const ready = getShell();

  if (edit) {
    setEditing();
    addEventListener('message', (e) => {
      if (e.origin !== location.origin || !e.data || !e.data.sb) return;
      const m = e.data;
      if (m.sb === 'render') {
        st.layout = m.layout;
        st.page = m.page;
        st.sel = m.sel || '';
        // The text being typed is the source of this render; drawing over it would drop the caret
        if (!st.typing) ready.then(render);
      } else if (m.sb === 'pick') {
        st.sel = m.sel;
        setRings();
        const node = getPicked(m.sel);
        if (node && m.scroll) node.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      } else if (m.sb === 'scale') {
        // The editor shows this page scaled down; the editing chrome is zoomed back to its own size, never past double
        root.style.setProperty('--sb-zoom', String(Math.min(2, 1 / (m.scale || 1))));
        setRings();
      } else if (m.sb === 'mode') {
        st.mode = m.mode;
        if (st.layout) setSeason(st.layout.theme);
      }
    });
    ready.then(() => post({ sb: 'ready' }));
  } else {
    st.layout = getLive();
    ready.then(render);
    addEventListener('storage', (e) => {
      if (e.key === 'sb.live') {
        st.layout = getLive();
        render();
      }
    });
    const pill = document.createElement('a');
    pill.className = 'sl-but sb-pill';
    pill.href = 'sb-01-builder.html?page=' + encodeURIComponent(st.page);
    pill.innerHTML = '<i class="bi bi-pencil-square" aria-hidden="true"></i>Изменить страницу';
    document.body.appendChild(pill);
  }
})();
