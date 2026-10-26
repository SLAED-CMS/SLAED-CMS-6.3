/* The canvas of the site builder: a page of the site in the theme lite, drawn from the layout of sb-data.js. The header,
   the footer and the answer of a module are the live ones, fetched from the site; the header and the footer are then
   filled from the theme parts and the menus, so what the editor changes lands in the very markup a visitor gets. The
   content between them is the list of sections, each drawn on the classes the site already has (presentation.css).

   Two ways in. Inside the editor (?edit=1 in its frame) every part answers the hand: a ring and a label follow the
   pointer, a click picks the part and tells the editor, a text of the picked part is typed in place, and the picked
   section carries its toolbar. Opened on its own it is the published site an administrator sees, the floating menu of
   the session carrying "Изменить страницу" that opens the editor on this page (a pill of the stand without a session).

   Messages from the editor: {sb: 'render', layout, page, sel, guest, preview} and {sb: 'pick', sel}, {sb: 'mode', mode}.
   Messages to the editor: {sb: 'ready'}, {sb: 'pick', sel}, {sb: 'text', path, value, done}, {sb: 'act', act, id},
   {sb: 'leave'} - Esc in the full-screen preview.
   A selection is a string: sec:<id>, part:<key>, menu:<key> or menu:<key>:<item id> */
(function () {
  const params = new URLSearchParams(location.search);
  const edit = params.has('edit') && window.parent !== window;
  const root = document.documentElement;
  const head = document.querySelector('[data-sb-head]');
  const foot = document.querySelector('[data-sb-foot]');
  const main = document.querySelector('[data-sb-page]');
  const st = { layout: null, page: params.get('page') || 'home', sel: '', shell: null, typing: '', mods: {} };
  if (edit) root.dataset.sbEdit = '';

  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const lines = (v) => esc(v).replace(/\n/g, '<br>');
  const href = (v) => !v ? '#' : /^(https?:|mailto:|#)/.test(v) ? v : v === '/' ? '/' : '/' + v.replace(/^\//, '');
  const blank = (v) => /^https?:/.test(v || '') ? ' target="_blank" rel="noopener"' : '';
  // A picture is a file of the site by its path from the root, as a file field keeps it
  const photo = (path) => new URL('../' + path, location.href).href;
  // The point a cropped picture keeps in view, as the field of focus stores it
  const focus = (v) => ` style="object-position: ${esc(v || 'center')}"`;

  // A Markdown text of the page: the canvas opens the editor window on it, it is never typed in place
  const mdText = (path, cls, label, value) => `<div class="${cls}" data-sb-md="${path}" data-sb-md-label="${esc(label)}">${getSbMd(value)}</div>`;
  const tone = (v) => v && v !== 'text' ? ` data-sl-tone="${esc(v)}"` : '';
  const day = (i) => String(26 - i * 3).padStart(2, '0') + '.10.2026';
  const text = (path, tag, cls, value, multi) => `<${tag}${cls ? ` class="${cls}"` : ''} data-sb-text="${path}"${multi ? ' data-sb-multi' : ''}>${multi ? lines(value) : esc(value)}</${tag}>`;
  const but = (path, cls, label, link) => label ? `<a class="${cls}" href="${esc(href(link))}"${blank(link)} data-sb-text="${path}">${esc(label)}</a>` : '';

  /* The stub rows of a feed: title, category and a line of text, the news of the pager series where the stand has them */
  const FEED = {
    news: (typeof DEMO_PG_NEWS !== 'undefined' ? DEMO_PG_NEWS : [['SLAED CMS возвращается', 'Наши новости']]).map(([t, c], i) => [t, c,
      i ? '' : 'Официальная новость проекта остаётся в контентном канале, а главная показывает только одну актуальную точку входа.']),
    forum: [['Пожелания к версии SLAED CMS 6.3', 'Общий форум', 'Форум остаётся местом обсуждений, вопросов и истории сообщества.'], ['Дата выхода новой версии', 'Новости'],
      ['Перенос сайта с 6.2', 'Помощь'], ['Своя секция в теме', 'Темы'], ['Ошибка в модуле файлов', 'Помощь']],
    docs: [['Устройство системы', 'Документация', 'Темы, модули, блоки и информация для администратора — отдельная база знаний.'], ['Типы Node', 'Документация'],
      ['Темы и токены', 'Документация'], ['Обновление 8.0', 'Документация']],
    files: [['SLAED CMS 6.3 Phoenix', 'Система', 'Система, модули, блоки, темы, языки и полезные инструменты — экосистема.'], ['Тема Lite', 'Темы'],
      ['Редактор CodeMirror 6', 'Редакторы'], ['Модуль «Магазин»', 'Модули'], ['Языковой пакет DE', 'Языки']],
    content: [['О проекте', 'Содержание'], ['Политика конфиденциальности', 'Содержание'], ['Реквизиты', 'Содержание'], ['Как нас найти', 'Содержание']],
    faq: [['Как перенести сайт?', 'Вопросы'], ['Где взять тему?', 'Вопросы'], ['Как включить HTTPS?', 'Вопросы'], ['Что такое Node?', 'Вопросы']],
    links: [['HostMind', 'Хостинг'], ['Молодёжный портал Коми', 'Портал'], ['Feldsher', 'Форум'], ['Start-Drive', 'Автосервис']],
    help: [['Не приходит письмо', 'Помощь'], ['Сброс пароля', 'Помощь'], ['Ошибка 403', 'Помощь'], ['Права на папки', 'Помощь']],
  };

  /* The sections drawn inside the frame of a presentation section: a head with the number on the page, then the body */
  const FRAMED = ['features', 'feed', 'item', 'media', 'text', 'gallery', 'video', 'steps', 'stats', 'sites', 'team', 'voices', 'prices', 'faq', 'map', 'embed'];

  /* One renderer per type of section: the markup the theme fragment sections/<type>.html would carry */
  const DRAW = {
    // The hero of the presentation with a picture where its cockpit stands; the plain words are bold, the accent a span
    hero: (s, p) => `
      <div class="sl-pres-hero">
        <div class="sl-pres-hero-grid${s.pic ? '' : ' sc-hero-solo'}">
          <div class="sl-pres-copy">
            ${s.over ? text(p + 'over', 'div', 'sl-pres-eyebrow', s.over) : ''}
            <h1>${text(p + 'title', 'b', '', s.title)}${s.accent ? ' ' + text(p + 'accent', 'span', '', s.accent) : ''}${s.title2 ? '<br>' + text(p + 'title2', 'b', '', s.title2) : ''}</h1>
            ${s.text ? text(p + 'text', 'p', 'sl-pres-lead', s.text, true) : ''}
            <p class="sc-acts">${but(p + 'btn', 'sl-but', s.btn, s.href)}${but(p + 'btn2', 'sl-but', s.btn2, s.href2)}</p>
          </div>
          ${s.pic ? `<figure class="sc-hero-pic"><img src="${esc(photo(s.photo))}" alt="${esc(s.alt || '')}"${focus(s.focus)} loading="lazy" width="2560" height="1440"></figure>` : ''}
        </div>
      </div>`,
    // DNA cards with their band picture, or the technology cards of the hero
    features: (s, p) => s.look === 'line' ? `
      <div class="sl-pres-tech">${s.items.map((it, i) => `
        <div class="sl-pres-tech-card"${tone(s.tone)}><i class="bi bi-${esc(it.icon)}" aria-hidden="true"></i>
          <div>${text(p + 'items.' + i + '.title', 'b', '', it.title)}${text(p + 'items.' + i + '.text', 'span', '', it.text)}</div>
        </div>`).join('')}
      </div>` : `
      <div class="sl-pres-dna">${s.items.map((it, i) => `
        <article class="sl-pres-principle">
          <img src="${esc(photo(it.photo))}" width="1920" height="440" alt=""${focus(it.focus)} loading="lazy" decoding="async">
          <div class="sl-pres-principle-copy">
            <header${tone(s.tone)}><i class="bi bi-${esc(it.icon)}" aria-hidden="true"></i>${text(p + 'items.' + i + '.title', 'h3', '', it.title)}</header>
            ${text(p + 'items.' + i + '.text', 'p', '', it.text, true)}
          </div>
        </article>`).join('')}
      </div>`,
    // The pulse cards, or the commit rows of "Живая разработка"; several types are dealt in turn
    feed: (s) => {
      const type = (k) => SB_NODE_TYPES.find((x) => x[0] === k) || SB_NODE_TYPES[0];
      const lists = [].concat(s.source).map((k) => (FEED[k] || FEED.news).map((row) => [row, k]));
      const rows = [];
      for (let i = 0; rows.length < Number(s.count) && i < 20; i++) lists.forEach((l) => { if (l[i] && rows.length < Number(s.count)) rows.push(l[i]); });
      if (s.view === 'list') {
        return `
      <div class="sl-pres-commits">${rows.map(([[t, c], k], i) => `
        <a class="sl-pres-commit" href="${esc(href(k))}"><span class="sl-pres-sha">${esc(type(k)[1])}</span>
          <div><b>${esc(t)}</b><span class="sl-pres-tags"><span>${esc(c)}</span></span></div>
          ${s.date ? `<div class="sl-pres-when"><time>${day(i)}</time><small>${esc(c)}</small></div>` : '<span></span>'}
        </a>`).join('')}
      </div>`;
      }
      return `
      <div class="sl-pres-pulse">${rows.map(([[t, c, x], k], i) => {
        const [, label, icon, tn] = type(k);
        return `
        <article class="sl-pres-pulse-card" data-sl-tone="${tn}">
          <div class="sl-pres-pulse-head"><span><i class="bi bi-${icon}" aria-hidden="true"></i></span><div><b>${esc(label)}</b><span>${esc(c.toUpperCase())}</span></div></div>
          <div class="sl-pres-pulse-body">${s.date ? `<time>${day(i)}</time>` : ''}<h3>${esc(t)}</h3>${x ? `<p>${esc(x)}</p>` : ''}</div>
          <div class="sl-pres-pulse-foot"><i class="sl-pres-dot sl-is-live"></i>${s.more ? `<a href="${esc(href(k))}"><span>Все: ${esc(label.toLowerCase())}</span> <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>` : ''}</div>
        </article>`;
      }).join('')}
      </div>`;
    },
    // The picture as the showcase shows a site, the words as a principle card writes them
    media: (s, p) => `
      <div class="sc-media-grid">
        <figure class="sl-pres-shot sc-media-pic"><img src="${esc(photo(s.photo))}" alt="${esc(s.alt || '')}"${focus(s.focus)} loading="lazy" width="2560" height="1440"></figure>
        <div class="sl-pres-principle-copy sc-media-copy">
          ${mdText(p + 'text', 'sl-entry-content sc-md', 'Текст', s.text)}
          <p class="sc-acts">${but(p + 'btn', 'sl-but', s.btn, s.href)}</p>
        </div>
      </div>`,
    // The fact tiles under the headline of the presentation
    stats: (s, p) => `
      <div class="sl-pres-facts">${s.items.map((it, i) => `
        <div class="sl-pres-stat"${tone(s.tone)}>${text(p + 'items.' + i + '.label', 'span', '', it.label)}<b>${text(p + 'items.' + i + '.num', 'span', '', it.num)}${it.unit ? ` <small>${esc(it.unit)}</small>` : ''}</b></div>`).join('')}
      </div>`,
    // The showcase of the presentation: the catalogue of sites, its snapshots and ratings
    sites: (s, p) => {
      const all = typeof DEMO_SITES !== 'undefined' ? DEMO_SITES : [];
      return `
      <div class="sl-pres-gallery" data-sl-pres="sites">
        <div class="sl-pres-gallery-head">${s.over ? text(p + 'over', 'span', '', s.over) : '<span></span>'}<b>сайтов: ${all.length}</b></div>
        <div class="sl-pres-gallery-track" tabindex="0">${all.slice(0, Number(s.count)).map((site) => `
          <article class="sl-pres-tile">
            <figure class="sl-pres-shot"><img src="../uploads/presentation/sites/thumb/${esc(site.file)}" width="260" height="${site.height}" alt="${esc(site.name)}" loading="lazy" decoding="async"><b class="sl-pres-rate"><i class="bi bi-star-fill" aria-hidden="true"></i>${esc(site.rate)}</b></figure>
            <div class="sl-pres-tile-copy"><small>${esc(site.cat)}</small><h3>${esc(site.name)}</h3></div>
          </article>`).join('')}
        </div>
      </div>`;
    },
    // The voices of the presentation: the words, the hairline of the tone, the person and the year
    voices: (s, p) => `
      <div class="sl-pres-voices">
        <div class="sl-pres-panel-head">${s.over ? text(p + 'over', 'span', '', s.over) : '<span></span>'}<b class="sl-pres-pill" data-sl-tone="success">Отзывы · ${s.items.length}</b></div>
        <div class="sl-pres-voices-track">${s.items.map((it, i) => `
          <article class="sl-pres-voice" data-sl-tone="${['primary', 'success', 'accent'][i % 3]}">
            ${text(p + 'items.' + i + '.text', 'p', '', it.text, true)}
            <i class="sl-pres-voice-sep"></i>
            <div class="sl-pres-voice-person">
              <span class="sl-pres-voice-ava">${esc(String(it.name).split(/\s+/).map((w) => w[0] || '').join('').slice(0, 2).toUpperCase())}</span>
              <div>${text(p + 'items.' + i + '.name', 'strong', '', it.name)}<small>${esc(it.site)}${it.role ? ' · ' + esc(it.role) : ''}</small></div>
              ${it.since ? `<div class="sl-pres-since"><span>с</span><b>${esc(it.since)}</b></div>` : ''}
            </div>
          </article>`).join('')}
        </div>
      </div>`,
    text: (s, p) => mdText(p + 'body', 'sl-entry-content sc-md', 'Текст', s.body),
    faq: (s, p) => s.items.map((it, i) => `
      <details class="sl-node-faq"${s.open && i === 0 ? ' open' : ''}>
        <summary><i class="bi bi-chevron-right" aria-hidden="true"></i>${text(p + 'items.' + i + '.q', 'span', 'sl-title', it.q)}</summary>
        ${mdText(p + 'items.' + i + '.a', 'sl-node-faq-body sc-md', 'Ответ', it.a)}
      </details>`).join(''),
    // Pictures with their captions on the track of the showcase
    gallery: (s, p) => `
      <div class="sl-pres-gallery">
        <div class="sl-pres-gallery-head">${s.over ? text(p + 'over', 'span', '', s.over) : '<span></span>'}<b>картинок: ${s.items.length}</b></div>
        <div class="sl-pres-gallery-track" tabindex="0">${s.items.map((it, i) => `
          <article class="sl-pres-tile">
            <figure class="sl-pres-shot"><img src="${esc(photo(it.photo))}" alt="${esc(it.alt || '')}"${focus(it.focus)} loading="lazy" decoding="async"></figure>
            ${it.caption ? `<div class="sl-pres-tile-copy">${text(p + 'items.' + i + '.caption', 'h3', '', it.caption)}</div>` : ''}
          </article>`).join('')}
        </div>
      </div>`,
    // A film of YouTube in its privacy mode, or a file of the site played by the browser; nothing on the site while empty
    video: (s, p) => {
      const url = String(s.url || '').trim();
      const tube = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([\w-]{6,})/);
      const media = tube ? `<iframe src="https://www.youtube-nocookie.com/embed/${esc(tube[1])}" title="${esc(s.caption || s.title || 'Видео')}" allow="encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe>`
        : /\.(mp4|webm)$/i.test(url) ? `<video src="${esc(/^https?:/.test(url) ? url : photo(url))}" controls preload="metadata"></video>` : '';
      if (!media) return edit ? '<p class="sc-module-wait"><i class="bi bi-play-btn" aria-hidden="true"></i>Вставьте в настройках секции ссылку на ролик YouTube или путь к файлу видео.</p>' : '';
      return `<figure class="sc-video">${media}</figure>${s.caption ? text(p + 'caption', 'p', 'sl-pres-lead', s.caption) : ''}`;
    },
    // The steps as the commit rows of "Живая разработка", their number where a commit keeps its hash
    steps: (s, p) => `
      <div class="sl-pres-commits">${s.items.map((it, i) => `
        <div class="sl-pres-commit"><span class="sl-pres-sha">${String(i + 1).padStart(2, '0')}</span>
          <div>${text(p + 'items.' + i + '.title', 'b', '', it.title)}<span class="sl-pres-tags">${text(p + 'items.' + i + '.text', 'span', '', it.text)}</span></div><span></span>
        </div>`).join('')}
      </div>`,
    // The people as the principle cards: the photo on the band, the name, the role over the words
    team: (s, p) => `
      <div class="sl-pres-dna">${s.items.map((it, i) => `
        <article class="sl-pres-principle">
          <img src="${esc(photo(it.photo))}" width="1920" height="440" alt="${esc(it.name)}"${focus(it.focus)} loading="lazy" decoding="async">
          <div class="sl-pres-principle-copy">
            <header data-sl-tone="primary"><i class="bi bi-person" aria-hidden="true"></i>${text(p + 'items.' + i + '.name', 'h3', '', it.name)}</header>
            ${it.role ? text(p + 'items.' + i + '.role', 'div', 'sl-pres-eyebrow', it.role) : ''}
            ${text(p + 'items.' + i + '.text', 'p', '', it.text, true)}
          </div>
        </article>`).join('')}
      </div>`,
    // The plans as the pulse cards: the name and its term in the head, the price, what it holds, the way to take it
    prices: (s, p) => `
      <div class="sl-pres-pulse">${s.items.map((it, i) => `
        <article class="sl-pres-pulse-card" data-sl-tone="${it.hot ? 'accent' : 'primary'}">
          <div class="sl-pres-pulse-head"><span><i class="bi bi-${it.hot ? 'star-fill' : 'tag'}" aria-hidden="true"></i></span><div>${text(p + 'items.' + i + '.name', 'b', '', it.name)}<span>${esc(String(it.unit || '').toUpperCase())}</span></div></div>
          <div class="sl-pres-pulse-body"><h3>${text(p + 'items.' + i + '.price', 'span', '', it.price)}</h3>${it.text ? text(p + 'items.' + i + '.text', 'p', '', it.text, true) : ''}</div>
          <div class="sl-pres-pulse-foot"><i class="sl-pres-dot${it.hot ? ' sl-is-live' : ''}"></i>${it.btn ? `<a href="${esc(href(it.href))}"${blank(it.href)}><span>${esc(it.btn)}</span> <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>` : ''}</div>
        </article>`).join('')}
      </div>`,
    // The map of OpenStreetMap at the point of the section beside the contacts of the theme, as technology cards
    map: (s) => {
      const t = st.layout.theme;
      const lat = Number(s.lat) || 0;
      const lon = Number(s.lon) || 0;
      const box = [lon - 0.016, lat - 0.01, lon + 0.016, lat + 0.01].map((n) => n.toFixed(4)).join(',');
      const card = (icon, html) => `<div class="sl-pres-tech-card" data-sl-tone="primary"><i class="bi bi-${icon}" aria-hidden="true"></i><div>${html}</div></div>`;
      return `
      <div class="sc-media-grid">
        <figure class="sc-map-pic"><iframe src="https://www.openstreetmap.org/export/embed.html?bbox=${box}&amp;layer=mapnik&amp;marker=${lat},${lon}" title="Карта" loading="lazy"></iframe></figure>
        <div class="sl-pres-tech sc-map-cards">
          ${t.address ? card('geo-alt', `<b>Адрес</b><span data-sb-text="theme:address" data-sb-multi>${lines(t.address)}</span>`) : ''}
          ${t.phone ? card('telephone', `<b>Телефон</b><span><a href="tel:${esc(t.phone.replace(/[^\d+]/g, ''))}">${esc(t.phone)}</a></span>`) : ''}
          ${t.email ? card('envelope', `<b>Почта</b><span><a href="mailto:${esc(t.email)}">${esc(t.email)}</a></span>`) : ''}
        </div>
      </div>`;
    },
    // A page of another service in a frame, held by the sandbox; only an address of https is shown
    embed: (s) => {
      const url = String(s.url || '').trim();
      if (!/^https:\/\//.test(url)) return edit ? '<p class="sc-module-wait"><i class="bi bi-window-stack" aria-hidden="true"></i>Вставьте в настройках секции адрес страницы, начинающийся с https://.</p>' : '';
      return `<iframe class="sc-embed" src="${esc(url)}" title="${esc(s.title || 'Встраивание')}" style="height: ${Number(s.height) || 420}px" loading="lazy" sandbox="allow-scripts allow-forms allow-same-origin allow-popups" referrerpolicy="no-referrer"></iframe>`;
    },
    // The claim card of the presentation, its words beside the button
    cta: (s, p) => `
      <div class="sl-pres-claim sc-cta-box">
        ${s.over ? text(p + 'over', 'span', 'sl-pres-over', s.over) : ''}
        ${text(p + 'title', 'h3', '', s.title)}
        ${s.text ? text(p + 'text', 'p', '', s.text, true) : ''}
        ${but(p + 'btn', 'sl-but sc-cta-but', s.btn, s.href)}
      </div>`,
    // A block of the site by its number, as [block=N] prints it: a fixed free block keeps its where, any other shows anywhere
    block: (s) => {
      const b = (st.layout.blocks || []).find((one) => one.id === s.block);
      if (!b) return edit ? '<p class="sc-module-wait"><i class="bi bi-bricks" aria-hidden="true"></i>Выберите блок в настройках секции.</p>' : '';
      const fixed = (b.which || []).includes('flyfix');
      if (!edit && (!b.status || !isBlockSeen(b) || (fixed && !getSbWhereShow(b.which, st.page, st.layout.pages[st.page])))) return '';
      return getBlockHtml(b);
    },
    html: (s) => s.code,
    // The slot of the module: the module's own answer, fetched from the site; it is edited in its section of the panel
    module: (s) => {
      if (st.mods[s.module] === undefined) getRemote(s.module, 'index.php?name=' + encodeURIComponent(s.module), (box) => {
        box.querySelectorAll(':scope > .sl-block').forEach((el) => el.remove());
        return box.innerHTML;
      });
      return st.mods[s.module];
    },
    // A record of Node: the text its own page prints, its introduction and its body; the record is written in its module
    item: (s) => {
      const rec = getSbItem(s.item);
      if (!rec) return edit ? '<p class="sc-module-wait"><i class="bi bi-file-earmark-richtext" aria-hidden="true"></i>Выберите материал в настройках секции.</p>' : '';
      const key = 'node:' + rec[0];
      if (st.mods[key] === undefined) getRemote(key, rec[2], (box) => {
        const part = (end) => (box.querySelector(`.sl-post [id^="repnode"][id$="${end}"]`) || { innerHTML: '' }).innerHTML;
        if (!box.querySelector('.sl-post')) throw new Error('Материала нет');
        // A record written without an introduction gives the first paragraph of its body instead
        const lead = box.querySelector('.sl-post [id^="repnode"][id$="body"] p');
        return { intro: part('intro'), body: part('body'), lead: part('intro').trim() || (lead ? lead.outerHTML : '') };
      });
      const got = st.mods[key];
      if (typeof got === 'string') return got;
      const intro = s.part === 'intro' && got.body.trim() !== '';
      return `<div class="sl-entry-content">${intro ? got.lead : got.intro + got.body}</div>${intro ? `<p class="sc-acts"><a class="sl-but" href="${esc(href(rec[2]))}">Читать полностью</a></p>` : ''}`;
    },
  };

  /* A part of the site as the site prints it into #content - the answer of a module, the text of a record: its scripts
     and form tokens dropped, every address made absolute, then take() keeps what the section shows; one fetch per key,
     then the page is drawn again */
  function getRemote(key, path, take) {
    // While the site answers the section shows the skeleton lines of the theme
    st.mods[key] = '<div class="sl-skel sc-wait" aria-busy="true"><span class="sl-skel-line"></span><span class="sl-skel-line"></span><span class="sl-skel-line sl-skel-line-half"></span></div>';
    const url = new URL('../' + path, location.href);
    fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(async (reply) => {
      if (!reply.ok) throw new Error('HTTP ' + reply.status);
      const doc = new DOMParser().parseFromString(await reply.text(), 'text/html');
      const box = doc.getElementById('content');
      if (!box) throw new Error('No #content');
      box.querySelectorAll('script, input[name="token"]').forEach((el) => el.remove());
      setAbsolute(box, url);
      st.mods[key] = take(box);
    }).catch((err) => {
      st.mods[key] = '<p class="sc-module-wait"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i>Сайт не ответил: ' + esc(err.message) + '.</p>';
    }).then(() => { if (!st.typing) render(); });
  }

  function setAbsolute(node, base) {
    node.querySelectorAll('[href], [src], [action]').forEach((el) => ['href', 'src', 'action'].forEach((attr) => {
      const v = el.getAttribute(attr);
      if (v && !v.startsWith('#') && !v.startsWith('mailto:')) el.setAttribute(attr, new URL(v, base).href);
    }));
  }

  function getFrameHtml(s, p, num, body) {
    // A record shown by "Материал" lends its title while the section has none of its own
    const title = s.title || (s.type === 'item' ? (getSbItem(s.item) || [])[3] || '' : '');
    return `<div class="sl-pres-head"><span class="sl-pres-num">${String(num).padStart(2, '0')}</span><i class="bi bi-${esc(s.icon || SB_TYPES[s.type].icon)} sl-pres-icon" aria-hidden="true"></i>${text(p + 'title', 'h2', '', title)}${s.lead ? text(p + 'lead', 'p', '', s.lead) : ''}</div>
      <div class="sl-pres-body">${body}</div>`;
  }

  /* Who sees a section and when, read as a block of the site reads view and expire; the visitor of the published page
     is a guest. The editor sees every section and the flags of what holds it back */
  const today = new Date().toISOString().slice(0, 10);
  const isDated = (s) => (s.from && s.from > today) || (s.till && s.till < today);
  const isShown = (s) => s.on && !isDated(s) && ['0', '3', undefined].includes(s.who) && (!s.lang || s.lang === 'ru');
  const isBlockSeen = (b) => ['0', '3', undefined].includes(b.who) && (!b.lang || b.lang === 'ru');
  const VIEW = { 1: 'Только пользователям', 2: 'Только модераторам', 3: 'Только гостям' };
  const date = (v) => v.split('-').reverse().join('.');

  function getFlagsHtml(s) {
    const flags = [];
    if (VIEW[s.who]) flags.push(['person-lock', VIEW[s.who]]);
    if (s.lang) flags.push(['translate', 'Только язык: ' + ((SB_LANGS.find((l) => l[0] === s.lang) || [0, s.lang])[1])]);
    if (s.from || s.till) flags.push(['calendar-range', (s.from ? 'с ' + date(s.from) : '') + (s.from && s.till ? ' ' : '') + (s.till ? 'до ' + date(s.till) : '')]);
    if (s.phone === false) flags.push(['phone', 'Скрыта на телефоне']);
    if (s.desk === false) flags.push(['display', 'Скрыта на компьютере']);
    if (s.anchor) flags.push(['hash', s.anchor]);
    return flags.length ? '<span class="sb-flags">' + flags.map(([i, t]) => `<span class="sl-chip"><i class="bi bi-${i}" aria-hidden="true"></i>${esc(t)}</span>`).join('') + '</span>' : '';
  }

  /* A block of the site in the markup of fragments/block-all.html; its body here is a stub of what its file prints. The
     poll is the theme's own: partials/voting-widget.html around the rows of fragments/voting-view.html */
  const getVoteHtml = () => '<div class="sl-vote"><ul class="sl-vote-list">' + [['Конструктор секций', 62, 31], ['Редактор меню', 24, 12], ['Настройки темы', 14, 7]].map(([t, v, n], i) => `
    <li${i ? '' : ' class="sl-lead"'} data-sl-votes="${n}" data-sl-percent="${v}">
      <div class="sl-progress-info"><span title="${t}"><i class="bi bi-trophy-fill" aria-hidden="true"></i>${t}</span><span class="sl-pull-right"><b class="sl-vote-pct">${v}%</b> <b>(голосов: <span class="sl-vote-num">${n}</span>)</b></span></div>
      <div class="sl-progress-line sl-progress-${i + 1}"><div style="width: ${v}%;">${v}%</div></div>
    </li>`).join('') + '</ul></div>';
  const BLOCK = {
    login: () => '<li><input type="text" class="sl-field" placeholder="Ник" aria-label="Ник"></li><li><input type="password" class="sl-field" placeholder="Пароль" aria-label="Пароль"></li><li><button type="button" class="sl-but">Войти</button></li>',
    stat: () => '<li>Сегодня: 1 204</li><li>Вчера: 1 873</li><li>Всего: 2 418 650</li>',
    search: () => '<li><input type="search" class="sl-field" placeholder="Поиск" aria-label="Поиск"></li>',
    languages: () => '<li>Русский</li><li>English</li><li>Deutsch</li>',
    user_info: () => '<li>Пользователей: 3</li><li>Гостей: 12</li><li>Поисковых ботов: 4</li>',
  };

  /* A block of the site in the markup of fragments/block-all.html, by its kind: the HTML of its field, the headlines of
     its feed or a stub of what its file prints; the centre zone above the content takes the "before content" form */
  function getBlockHtml(b, before) {
    const icon = getSbBlockKind(b)[2];
    const body = b.type === 'html' ? b.content
      : b.type === 'rss' ? '<ul class="sc-block-body">' + ['Заголовок ленты 1', 'Заголовок ленты 2', 'Заголовок ленты 3'].map((t) => `<li><a href="${esc(b.url)}">${t}</a></li>`).join('') + '</ul>'
      : b.kind === 'voting' ? getVoteHtml()
      : `<ul class="sc-block-body">${(BLOCK[b.kind] || BLOCK.stat)()}</ul>`;
    const head = `<i class="bi bi-${icon}" aria-hidden="true"></i> <span data-sb-text="blk:${b.id}:title">${esc(b.title)}</span>`;
    const off = edit && !b.status ? ' sb-off' : '';
    return before
      ? `<div class="sl-block${off}" data-sb-pick="blk:${b.id}" data-sb-label="Блок «${esc(b.title)}»"><div class="sl-title">${head}</div><div class="sl-block-content">${body}</div></div>`
      : `<section class="sl-block${off}" data-sb-pick="blk:${b.id}" data-sb-label="Блок «${esc(b.title)}»"><h2 class="sl-title">${head}</h2><div class="sl-block-content">${body}</div></section>`;
  }

  /* The page as layouts/app.html of the theme lays it: side opens the left and the right column, top the centre above
     and under the content, both inside #content; a zone takes the blocks of its position whose where holds this page */
  function getColumnsHtml(inner, blocks, page) {
    const on = (set, one) => ['', '0', one].includes(String(set ?? '3'));
    const zone = (z) => blocks.filter((b) => b.pos === z).map((b) => getBlockHtml(b, z === 'c')).join('');
    const left = on(page.side, '1') ? zone('l') : '';
    const right = on(page.side, '2') ? zone('r') : '';
    const up = on(page.top, '1') ? zone('c') : '';
    const down = on(page.top, '2') ? zone('d') : '';
    const cls = [left ? 'sl-has-left' : '', right ? 'sl-has-right' : ''].filter(Boolean).join(' ');
    return `<div class="sl-wrp"><div id="container"${cls ? ` class="${cls}"` : ''}>${left ? `<aside id="leftbar" data-sb-zone="l">${left}</aside>` : ''}<main id="content">${up ? `<div data-sb-zone="c">${up}</div>` : ''}${inner}${down ? `<div class="sb-zone-down" data-sb-zone="d">${down}</div>` : ''}</main>${right ? `<aside id="sidebar" data-sb-zone="r">${right}</aside>` : ''}</div></div>`;
  }

  function getSectionHtml(s, num) {
    const type = SB_TYPES[s.type];
    const p = 'sec:' + s.id + ':';
    const framed = FRAMED.includes(s.type);
    const cls = ['sc', 'sc-' + s.type];
    // The module brings its own root; every other section stands under the root of the presentation
    if (!['module', 'block', 'html'].includes(s.type)) cls.unshift('sl-pres');
    if (framed) cls.push('sl-pres-section');
    if (s.phone === false) cls.push('sc-no-phone');
    if (s.desk === false) cls.push('sc-no-desk');
    if (edit && isDated(s)) cls.push('sb-off');
    if (s.type === 'features' || s.type === 'team') cls.push('sc-cols-' + s.cols);
    if (s.type === 'media' || s.type === 'map') cls.push('sc-side-' + s.side);
    if (!s.on) cls.push('sb-off');
    const anchor = s.anchor ? ` id="${esc(s.anchor)}"` : '';
    const body = DRAW[s.type](s, p);
    return `<section class="${cls.join(' ')}"${anchor} data-sb-pick="sec:${s.id}" data-sb-label="${esc(type.title)}">${framed ? getFrameHtml(s, p, num, body) : body}${edit && !st.guest ? getFlagsHtml(s) : ''}</section>`;
  }

  /* ---- The live header and footer, filled from the theme parts and the menus ---- */

  // Who a menu item is for, read as a block reads view: the visitor of the published page is a guest
  const isItemSeen = (it) => (edit && !st.guest) || ['0', '3', undefined].includes(it.who);

  function getMenuHtml(list) {
    const link = (it) => `<a href="${esc(it.href ? href(it.href) : '#')}"${it.blank ? ' target="_blank" rel="noopener"' : ''} title="${esc(it.title)}" data-sb-text="menu:main:${it.id}:title">${esc(it.title)}</a>`;
    return '<li><a href="/" class="sl-home-link sl-circle-action" title="Главная"><i class="bi bi-house-door-fill" aria-hidden="true"></i><span class="sl-hidden">Главная</span></a></li>' +
      list.filter(isItemSeen).map((it) => `<li data-sb-pick="menu:main:${it.id}" data-sb-label="Пункт меню">${link(it)}${it.kids.length ? `<ul>${it.kids.filter(isItemSeen).map((k) =>
        `<li data-sb-pick="menu:main:${k.id}" data-sb-label="Пункт меню">${link(k)}</li>`).join('')}</ul>` : ''}</li>`).join('');
  }

  function getPaneHtml(t) {
    return `<li class="sl-d-info sl-d-version sl-font" data-sb-text="theme:paneCap">${esc(t.paneCap)}</li>
      <li class="sl-d-num sl-font" data-sb-text="theme:paneNum">${esc(t.paneNum)}</li>
      <li class="sl-d-btns"><a class="sl-but" href="${esc(href(t.paneHref))}" data-sb-text="theme:paneBtn">${esc(t.paneBtn)}</a></li>`;
  }

  /* The title band of lite (#head-content): the page's own title, or the name of the module whose slot it holds */
  function setBand(key, page) {
    const sh = st.shell;
    let band = sh.querySelector('#head-content');
    if (!band) {
      band = document.createElement('div');
      band.id = 'head-content';
      band.innerHTML = '<div class="sl-wrp"><p class="sl-font"></p></div>';
      sh.querySelector('#hmenu').after(band);
    }
    const slot = page.sections.find((s) => s.type === 'module');
    const words = band.querySelector('.sl-font');
    words.textContent = slot ? (SB_MODULES.find((m) => m[0] === slot.module) || SB_MODULES[0])[1] : page.title;
    if (slot) delete words.dataset.sbText;
    else words.dataset.sbText = 'page:' + key + ':title';
    band.hidden = !page.band;
    band.dataset.sbPick = 'page:' + key;
    band.dataset.sbLabel = slot ? 'Полоса заголовка — имя модуля' : 'Полоса заголовка';
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
      [logo, img].forEach((n) => { n.title = t.sitename; });
      img.alt = t.sitename;
      mark(logo, 'part:brand', 'Логотип');
    }
    const tagline = sh.querySelector('#header .sl-slogan');
    if (tagline) {
      tagline.innerHTML = lines(t.tagline);
      tagline.dataset.sbText = 'theme:tagline';
      tagline.dataset.sbMulti = '';
      mark(tagline, 'part:brand', 'Строка под логотипом');
    }
    sh.querySelectorAll('.sl-d-pane').forEach((pane) => {
      pane.innerHTML = getPaneHtml(t);
      pane.hidden = !t.paneOn;
      mark(pane, 'part:pane', 'Плашка версии');
    });
    const social = sh.querySelector('#topbar .sl-top-social');
    if (social) {
      social.innerHTML = t.social.map((n, i) => `<a class="sl-thd sl-circle-action sl-cat-tone-${i % 6}" href="${esc(href(n.url))}"${/^https?:/.test(n.url) ? ' rel="me noopener" target="_blank"' : ''} title="${esc(n.title)}" aria-label="${esc(n.title)}"><i class="bi bi-${esc(n.icon)}" aria-hidden="true"></i>${esc(n.title)}</a>`).join('');
      mark(social, 'part:social', 'Ссылки верхней строки');
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
    const word = sh.querySelector('#footbox .sl-upper-wordmark');
    if (word) {
      word.title = t.sitename;
      word.querySelector('.sl-upper-tagline').textContent = t.tagline.replace(/\s*\n\s*/g, ' ');
      mark(word, 'part:brand', 'Строка под логотипом');
    }
    const made = sh.querySelector('#footbox .sl-madein');
    if (made) {
      made.hidden = !t.madeOn;
      mark(made, 'part:marks', 'Знак «Made in Germany»');
    }
    const partners = sh.querySelector('#footbox .sl-partners');
    if (partners) {
      const col = partners.closest('section');
      col.hidden = !t.techOn;
      const cap = col.querySelector('.sl-f-title');
      cap.textContent = t.techTitle;
      cap.dataset.sbText = 'theme:techTitle';
      partners.innerHTML = t.tech.map((m) => `<span title="${esc(m.title)}"><img src="${esc(photo(m.img))}" alt="${esc(m.title)}" width="74" height="74"></span>`).join('');
      mark(col, 'part:marks', 'Знаки в подвале');
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
    const fmenu = sh.querySelector('#footbox .sl-fmenu');
    if (fmenu) {
      fmenu.querySelector('ul').innerHTML = layout.menus.foot.filter(isItemSeen).map((it) => `<li data-sb-pick="menu:foot:${it.id}" data-sb-label="Пункт меню"><a href="${esc(href(it.href))}" title="${esc(it.title)}">${it.icon ? `<i class="bi bi-${esc(it.icon)}" aria-hidden="true"></i>` : ''}<span data-sb-text="menu:foot:${it.id}:title">${esc(it.title)}</span></a></li>`).join('');
      mark(fmenu, 'menu:foot', 'Нижнее меню');
    }
    // The two side tabs of the theme, the green one and the blue one, in the order of the part
    ['block-idea', 'block-feedback'].forEach((id, i) => {
      const box = sh.querySelector('#' + id);
      if (!box) return;
      const one = t.tabs[i];
      box.hidden = !t.tabsOn || !one;
      if (!one) return;
      const a = box.querySelector('a');
      a.href = href(one.href);
      a.title = one.title;
      const span = a.querySelector('span');
      span.textContent = one.title;
      span.dataset.sbText = 'theme:tabs.' + i + '.title';
      mark(box, 'part:tabs', 'Боковые ярлыки');
    });
  }

  /* The live chrome: header, menu, the band, the footer and the side tabs of the real site, scripts and form tokens dropped */
  function getShell() {
    const url = new URL('../index.php?name=presentation', location.href);
    return fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(async (reply) => {
      if (!reply.ok) throw new Error('HTTP ' + reply.status);
      const doc = new DOMParser().parseFromString(await reply.text(), 'text/html');
      const top = ['topbar', 'header', 'hmenu'].map((id) => doc.getElementById(id));
      const end = ['demo-line', 'footbox', 'block-idea', 'block-feedback'].map((id) => doc.getElementById(id));
      if ([...top, ...end].some((n) => !n)) throw new Error('Missing site header or footer');
      [...top, ...end].forEach((node) => {
        node.querySelectorAll('script, input[name="token"]').forEach((el) => el.remove());
        setAbsolute(node, url);
      });
      head.replaceChildren(...top);
      foot.replaceChildren(...end);
      // The floating menu of an administrator comes with the page while the session is one; the published view takes it
      const dock = edit ? null : doc.querySelector('[data-sl-dock]');
      if (dock) {
        dock.querySelectorAll('script').forEach((el) => el.remove());
        setAbsolute(dock, url);
        st.dock = dock;
      }
      st.shell = document.body;
    }).catch((err) => {
      head.innerHTML = '<div class="sl-wrp"><div class="sl-alert sl-alert-error"><div class="sl-alert-body">Шапка и подвал сайта не загрузились: ' + esc(err.message) + '.</div></div></div>';
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

  // A mark of the head the page gives search engines and networks, as setHead() writes it
  function setMeta(name, value) {
    const attr = name.startsWith('og:') ? 'property' : 'name';
    let node = document.head.querySelector(`meta[${attr}="${name}"]`);
    if (!value) {
      if (node) node.remove();
      return;
    }
    if (!node) {
      node = document.createElement('meta');
      node.setAttribute(attr, name);
      document.head.appendChild(node);
    }
    node.content = value;
  }

  function render() {
    const layout = st.layout;
    if (!layout) return;
    const key = layout.pages[st.page] ? st.page : 'home';
    const page = layout.pages[key];
    // "Смотреть как гость" draws the sections a guest gets; the blocks stay as the session shows them, as getBlocks() reads it
    const list = page.sections.filter((s) => (edit && !st.guest) || isShown(s));
    let num = 0;
    // A page for users only gives a guest the login, as a hidden module answers
    const closed = page.who === '1' && (!edit || st.guest);
    const inner = closed ? `<div class="sl-alert sl-alert-info"><div class="sl-alert-body">Эта страница — только для пользователей сайта. Войдите или зарегистрируйтесь, чтобы её открыть.</div></div>` : list.length ? `<div class="sl-pres-stage sc-stage">${list.map((s) => getSectionHtml(s, FRAMED.includes(s.type) ? ++num : 0)).join('')}</div>` : `
      <div class="sc-empty">
        <i class="bi bi-layout-wtf" aria-hidden="true"></i>
        <b>На странице пока нет ни одной секции.</b>
        ${edit ? '<p>Соберите её из готовых секций: обложка, текст, материал, лента, призыв.</p><button type="button" class="sl-but" data-sb-act="add"><i class="bi bi-plus-lg" aria-hidden="true"></i>Добавить секцию</button>' : ''}
      </div>`;
    const blocks = (layout.blocks || []).filter((b) => getSbWhereShow(b.which, key, page) && (edit || (b.status && isBlockSeen(b))));
    main.innerHTML = getColumnsHtml(inner, blocks, page);
    if (st.shell) {
      setShell(layout);
      setBand(key, page);
    }
    setSeason(layout.theme);
    document.title = page.title + ' — ' + layout.theme.sitename;
    setMeta('robots', page.noindex || key === 'notfound' ? 'noindex, follow' : 'index, follow');
    setMeta('og:image', page.og ? photo(page.og) : '');
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
    // A part pinned to the window (the side tabs) is ringed in the window, so the ring stays on it while the page scrolls;
    // a ring never starts left of the page, where its label would be cut
    let pinned = false;
    for (let n = node; n && n !== document.body && !pinned; n = n.parentElement) pinned = getComputedStyle(n).position === 'fixed';
    const left = Math.max(r.left, 0);
    box.hidden = false;
    box.classList.toggle('sb-ring-pinned', pinned);
    box.style.setProperty('--sb-x', (left + (pinned ? 0 : scrollX)) + 'px');
    box.style.setProperty('--sb-y', (r.top + (pinned ? 0 : scrollY)) + 'px');
    box.style.setProperty('--sb-w', (r.right - left) + 'px');
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
    // A section a page holds once - the slot of its module - has no twin
    const picked = isSec ? (st.layout.pages[st.page] || st.layout.pages.home).sections.find((s) => 'sec:' + s.id === st.sel) : null;
    if (picked && SB_TYPES[picked.type].one) ring.bar.querySelector('[data-sb-act="dup"]').hidden = true;
    // A link leads to a section only by its anchor
    ring.bar.querySelector('[data-sb-act="link"]').hidden = !(picked && picked.anchor);
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
    // The capsule of the editors (sl-editor-pill): the grip, the order, the copies, the showing and the removal
    const act = ([key, icon, title, cls]) => `<button type="button" class="sl-modal-act${cls ? ' ' + cls : ''}" data-sb-act="${key}" title="${title}" aria-label="${title}"><i class="bi bi-${icon}" aria-hidden="true"></i></button>`;
    const groups = [[['up', 'arrow-up', 'Переместить выше'], ['down', 'arrow-down', 'Переместить ниже']], [['dup', 'copy', 'Дублировать'], ['link', 'link-45deg', 'Скопировать ссылку на секцию']],
      [['toggle', 'eye-slash', 'Скрыть'], ['del', 'trash3', 'Удалить', 'sb-act-danger']]];
    ring.bar.innerHTML = '<span class="sl-editor-pill sb-pill sb-bar" role="toolbar" aria-label="Секция"><span class="sl-editor-grp"><button type="button" class="sl-modal-act sb-grip" data-sb-grip title="Перетащить" aria-label="Перетащить"><i class="bi bi-arrows-move" aria-hidden="true"></i></button></span>'
      + groups.map((g) => '<span class="sl-editor-grp">' + g.map(act).join('') + '</span>').join('') + '</span>'
      + '<button type="button" class="sl-but sb-bar-add" data-sb-act="add" title="Добавить секцию ниже"><i class="bi bi-plus-lg" aria-hidden="true"></i>Добавить ниже</button>';
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
      // A click in a text being typed places the caret; inside a link it must not follow the link
      if (e.target.closest('[contenteditable]')) {
        if (e.target.closest('a')) e.preventDefault();
        return;
      }
      const node = e.target.closest('[data-sb-pick]');
      const words = getWords(e.target);
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
      // "Свой HTML" opens the code editor of the panel
      const own = e.target.closest('.sc-html[data-sb-pick]');
      if (own) {
        e.preventDefault();
        post({ sb: 'code', sel: own.dataset.sbPick, path: own.dataset.sbPick + ':code' });
        return;
      }
      const md = e.target.closest('[data-sb-md]');
      if (md) {
        e.preventDefault();
        post({ sb: 'md', sel: getOwner(md), path: md.dataset.sbMd, label: md.dataset.sbMdLabel });
        return;
      }
      const words = getWords(e.target);
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

  /* The text a click types: the one under the pointer, or the only text of the part clicked beside it - a side tab, a
     button - so a click on its padding still reaches its words */
  function getWords(node) {
    const words = node.closest('[data-sb-text]');
    if (words) return words;
    const part = node.closest('[data-sb-pick]');
    const list = part ? part.querySelectorAll('[data-sb-text]') : [];
    return list.length === 1 ? list[0] : null;
  }

  /* The words as typed, not as shown: innerText hands back the capitals a theme draws with text-transform; a line break
     is a <br> or a new line, a block a typed Enter leaves is a line of its own */
  function getPlain(node) {
    let out = '';
    node.childNodes.forEach((one) => {
      if (one.nodeType === Node.TEXT_NODE) {
        out += one.nodeValue;
      } else if (one.nodeName === 'BR') {
        out += '\n';
      } else {
        if (/^(DIV|P)$/.test(one.nodeName) && out && !out.endsWith('\n')) out += '\n';
        out += getPlain(one);
      }
    });
    return out;
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
    const read = () => getPlain(words).replace(/\n$/, '');
    const was = read();
    const send = (done) => post({ sb: 'text', path: path, value: read(), done: done });
    const onInput = () => { send(false); setRings(); };
    // The keys of the quick edit of the site (setQuickEdit): Esc gives the text back, Ctrl+Enter keeps it, Enter in one line
    const onKey = (e) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        words.innerText = was;
        words.blur();
      } else if (e.key === 'Enter' && (!multi || e.ctrlKey || e.metaKey)) {
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
      const live = JSON.parse(localStorage.getItem('sb.live') || 'null');
      if (isSbLayout(live)) return live;
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
        st.guest = !!m.guest;
        root.toggleAttribute('data-sb-guest', st.guest);
        // The full-screen preview: the guest's page with no ring and no toolbar over it; Esc on it goes back to the editor
        root.toggleAttribute('data-sb-preview', !!m.preview);
        if (m.preview && !st.leave) {
          st.leave = true;
          document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && root.hasAttribute('data-sb-preview')) post({ sb: 'leave' }); });
        }
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
  } else if (params.has('preview')) {
    let pass = null;
    try { pass = (JSON.parse(localStorage.getItem('sb.previews') || '[]') || []).find((one) => one.token === params.get('preview') && one.until > Date.now()); } catch (e) { pass = null; }
    let draft = null;
    try { draft = JSON.parse(localStorage.getItem('sb.draft') || 'null'); } catch (e) { draft = null; }
    if (pass && isSbLayout(draft)) {
      st.layout = draft;
      ready.then(render);
      const tag = document.createElement('span');
      tag.className = 'sl-chip sl-chip-warn sb-float';
      tag.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i>Черновик — ещё не опубликован';
      document.body.appendChild(tag);
    } else {
      main.innerHTML = '<div class="sl-wrp"><div class="sl-alert sl-alert-warn"><div class="sl-alert-body">Ссылка для просмотра устарела или неверна. Попросите новую у администратора сайта.</div></div></div>';
    }
  } else {
    st.layout = getLive();
    ready.then(render);
    addEventListener('storage', (e) => {
      if (e.key === 'sb.live') {
        st.layout = getLive();
        render();
      }
    });
    // The way into the editor: "Изменить страницу" in the floating menu of an administrator, right after "Эта страница
    // в панели", as getAdminDock() prints it with the transfer; the menu runs on dock.js of the system. Without the
    // session of an administrator the stand keeps a pill of its own
    const into = 'sb-01-builder.html?page=' + encodeURIComponent(st.page);
    ready.then(() => {
      if (st.dock) {
        const app = document.createElement('a');
        app.className = 'sl-dock-app';
        app.href = into;
        app.dataset.tip = 'Изменить страницу';
        app.setAttribute('aria-label', 'Изменить страницу');
        app.innerHTML = '<i class="bi bi-pencil-square" aria-hidden="true"></i>';
        const here = st.dock.querySelector('.sl-dock-page');
        if (here) here.after(app);
        else st.dock.querySelector('[data-sl-dock-body]').prepend(app);
        document.body.appendChild(st.dock);
        const run = document.createElement('script');
        run.src = '../plugins/system/dock.js';
        document.body.appendChild(run);
        return;
      }
      const pill = document.createElement('a');
      pill.className = 'sl-but sb-float';
      pill.href = into;
      pill.innerHTML = '<i class="bi bi-pencil-square" aria-hidden="true"></i>Изменить страницу';
      document.body.appendChild(pill);
    });
  }
})();
