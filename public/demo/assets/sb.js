/* The editor of the site builder, on the theme admin: one screen for the pages, the menus and the theme parts of a
   site. Left - the structure (Страница: header, sections, footer; Меню; Тема), middle - the page itself in the canvas
   frame (sb-site.html?edit=1), right - the settings of what is picked. Whatever is picked in one place is picked in
   all three; a text typed on the canvas runs into its field and back.

   The layout is one object (sb-data.js): a draft, kept in the key sb.draft, and the published copy in sb.live, which
   the canvas opened on its own shows. Every change is a step that Ctrl+Z takes back. "Настройки" shows the draft as the
   one file of the site it would be written to - config/layout.php.

   A path names a value everywhere - in the inspector, on the canvas, in a message:
     sec:<id>:<key>            a setting of a section; sec:<id>:items.<n>.<key> - of one of its items
     theme:<key>               a theme part; theme:social.<n>.<key> - one social network
     menu:<menu>:<id>:<key>    an item of a menu
     page:<key>:<key>          the page itself
   A selection is the same without the last key: sec:<id>, part:<key>, menu:<menu>, menu:<menu>:<id>, page:<key> */
(function () {
  const app = document.querySelector('[data-sb-app]');
  if (!app) return;
  const frame = app.querySelector('[data-sb-frame] iframe');
  const left = app.querySelector('[data-sb-left]');
  const insp = app.querySelector('[data-sb-inspector]');
  const lib = app.querySelector('[data-sb-lib]');
  const dialog = document.querySelector('[data-sb-data]');
  const checks = document.querySelector('[data-sb-checks]');
  const story = document.querySelector('[data-sb-story]');
  const presets = document.querySelector('[data-sb-presets]');
  const pal = document.querySelector('[data-sb-palette]');
  const params = new URLSearchParams(location.search);

  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const ico = (name) => `<i class="bi bi-${esc(name)}" aria-hidden="true"></i>`;
  const clone = (v) => JSON.parse(JSON.stringify(v));
  // A note of the panel: the info alert (fragments/alert.html) the forms of the panel explain themselves with
  const note = (html) => `<div class="sl-alert sl-alert-info"><div class="sl-alert-body">${html}</div></div>`;
  // Numbers and dates in the language of the page, as the panel prints them
  const LANG = document.documentElement.lang || 'ru';
  const read = (key) => { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch (e) { return null; } };
  const keep = (key, v) => { try { localStorage.setItem(key, JSON.stringify(v)); } catch (e) { /* storage may be off */ } };

  // A draft, a publication and a history of another shape of the layout give way to the delivery all together
  if (!isSbLayout(read('sb.draft')) || !isSbLayout(read('sb.live'))) ['sb.draft', 'sb.live', 'sb.history'].forEach((k) => { try { localStorage.removeItem(k); } catch (e) { /* storage may be off */ } });

  const st = {
    layout: read('sb.draft') || getSbStart(),
    page: params.get('page') || 'home',
    tab: 'page',
    menu: 'main',
    sel: '',
    past: [],
    future: [],
    burst: 0,
    ready: false,
    insertAt: -1,
  };
  if (!read('sb.live')) keep('sb.live', st.layout);
  if (!st.layout.pages[st.page]) st.page = 'home';

  /* ---- The model ---- */

  const page = () => st.layout.pages[st.page];
  const sections = () => page().sections;
  const getSec = (id) => sections().find((s) => s.id === id);
  const newId = (p) => p + (++st.layout.next);
  // A new section of a type: its neutral values and the defaults of "Показ", then what the caller sets
  const getNewSec = (type, over) => Object.assign({ id: newId('s'), type: type, on: true }, SB_TYPES[type].make(), getSbShow(), over || {});
  // A type a page holds once - the slot of its module - is taken on a page that has it already
  const isOneTaken = (type, list) => !!SB_TYPES[type].one && (list || sections()).some((one) => one.type === type);
  // Where a new section goes: under the picked one, else at the end of the page
  const getInsertAt = () => (st.sel.startsWith('sec:') ? sections().findIndex((s) => 'sec:' + s.id === st.sel) + 1 : sections().length);
  // The pressed state of a button of a group: the class of the canon and the state a reader of the screen hears
  const setPressed = (el, on) => { el.classList.toggle('sl-is-active', on); el.setAttribute('aria-pressed', String(on)); };
  // A row of the lists of changes, remarks, pages and sections of the history: a button leading to its place
  const getIssueRow = (attr, icon, text, where, end, change) => `<li><button type="button" class="sb-issue${change ? ' sb-change' : ''}" ${attr}>${ico(icon)}<span class="sb-issue-text">${esc(text)}</span><span class="sb-issue-where">${esc(where)}</span>${end ? ico(end) : ''}</button></li>`;

  function getMenuItem(menu, id, list) {
    for (const it of list || st.layout.menus[menu]) {
      if (it.id === id) return it;
      const deep = getMenuItem(menu, id, it.kids);
      if (deep) return deep;
    }
    return null;
  }

  function getMenuParent(menu, id) {
    const top = st.layout.menus[menu];
    if (top.some((it) => it.id === id)) return top;
    for (const it of top) if (it.kids.some((k) => k.id === id)) return it.kids;
    return null;
  }

  /* A path to the object that holds the value and the key of it, in the draft or in another layout (the published one) */
  function getSlot(path, from) {
    const lay = from || st.layout;
    const part = path.split(':');
    let obj = null;
    let rest = '';
    if (part[0] === 'sec') { obj = from ? Object.values(lay.pages).flatMap((pg) => pg.sections).find((s) => s.id === part[1]) : getSec(part[1]); rest = part[2]; }
    if (part[0] === 'theme') { obj = lay.theme; rest = part[1]; }
    if (part[0] === 'menu') { obj = lay.menus[part[1]] ? getMenuItem(part[1], part[2], lay.menus[part[1]]) : null; rest = part[3]; }
    if (part[0] === 'page') { obj = lay.pages[part[1]]; rest = part[2]; }
    if (part[0] === 'blk') { obj = (lay.blocks || []).find((b) => b.id === part[1]); rest = part[2]; }
    if (!obj || !rest) return null;
    const keys = rest.split('.');
    while (keys.length > 1) {
      obj = obj[keys.shift()];
      if (obj == null) return null;
    }
    return { obj: obj, key: keys[0] };
  }

  function getValue(path) {
    const slot = getSlot(path);
    return slot ? slot.obj[slot.key] : '';
  }

  // The value the site has now, or undefined where the published layout has no such place (a new section, a new item)
  function getLiveValue(path) {
    const slot = st.live ? getSlot(path, st.live) : null;
    return slot ? slot.obj[slot.key] : undefined;
  }

  /* One step of history per gesture: a burst of typing is one step, a click is one step */
  function setStep(soft) {
    const now = Date.now();
    if (soft && now - st.burst < 900) {
      st.burst = now;
      return;
    }
    st.burst = soft ? now : 0;
    st.past.push(JSON.stringify(st.layout));
    if (st.past.length > 200) st.past.shift();
    st.future = [];
  }

  function setValue(path, value, soft) {
    const slot = getSlot(path);
    if (!slot) return;
    const old = slot.obj[slot.key];
    if (typeof old === 'number') value = Number(value);
    if (old === value) return;
    setStep(soft);
    slot.obj[slot.key] = value;
    setSaved();
  }

  function setChange(fn) {
    setStep(false);
    fn();
    setSaved();
    setAll();
  }

  function setUndo(back) {
    const from = back ? st.past : st.future;
    const to = back ? st.future : st.past;
    if (!from.length) return;
    to.push(JSON.stringify(st.layout));
    st.layout = JSON.parse(from.pop());
    st.burst = 0;
    if (!st.layout.pages[st.page]) st.page = 'home';
    if (st.sel && !getSelNode(st.sel)) st.sel = '';
    setSaved();
    setAll();
    setToast(back ? 'Шаг отменён.' : 'Шаг возвращён.');
  }

  function getSelNode(sel) {
    const part = sel.split(':');
    if (part[0] === 'sec') return getSec(part[1]);
    if (part[0] === 'menu') return part[2] ? getMenuItem(part[1], part[2]) : st.layout.menus[part[1]];
    if (part[0] === 'part') return SB_PARTS[part[1]];
    if (part[0] === 'page') return st.layout.pages[part[1]];
    if (part[0] === 'blk') return st.layout.blocks.find((b) => b.id === part[1]);
    return null;
  }

  function setSaved() {
    keep('sb.draft', st.layout);
    // The counter of ids is no content: a draft brought back to the site keeps its larger counter and is not a change
    const bare = (lay) => JSON.stringify(lay ? Object.assign({}, lay, { next: 0 }) : lay);
    const dirty = bare(read('sb.live')) !== bare(st.layout);
    const state = app.querySelector('[data-sb-state]');
    // The count is that of the list of changes the publication shows, so the two never disagree
    const waiting = dirty ? getChanges().length : 0;
    state.className = 'sl-chip sb-state ' + (dirty ? 'sl-chip-warn' : 'sl-chip-success');
    state.innerHTML = dirty ? ico('pencil') + '&nbsp;<span>Черновик' + (waiting ? ' · ' + waiting : '') + '</span>' : ico('check2-circle') + '&nbsp;<span>Опубликовано</span>';
    state.title = waiting ? 'Изменений ждёт публикации: ' + waiting : '';
    app.querySelector('[data-sb-publish]').disabled = !dirty;
    app.querySelector('[data-sb-undo]').disabled = !st.past.length;
    app.querySelector('[data-sb-redo]').disabled = !st.future.length;
  }

  /* ---- The canvas ---- */

  let drawQueued = false;
  function setCanvas() {
    if (!st.ready || drawQueued) return;
    drawQueued = true;
    requestAnimationFrame(() => {
      drawQueued = false;
      frame.contentWindow.postMessage({ sb: 'render', layout: st.layout, page: st.page, sel: st.sel, guest: !!st.guest, preview: !!st.preview }, location.origin);
    });
  }

  function setCanvasPick(scroll) {
    if (st.ready) frame.contentWindow.postMessage({ sb: 'pick', sel: st.sel, scroll: scroll }, location.origin);
  }

  function setCanvasMode() {
    if (st.ready) frame.contentWindow.postMessage({ sb: 'mode', mode: document.documentElement.dataset.theme }, location.origin);
  }

  addEventListener('message', (e) => {
    if (e.origin !== location.origin || !e.data || !e.data.sb) return;
    const m = e.data;
    if (m.sb === 'ready') {
      st.ready = true;
      setCanvasMode();
      setCanvasScale();
      setCanvas();
    } else if (m.sb === 'pick') {
      // The preview is for looking: a click on the page picks nothing
      if (!st.preview) setPick(m.sel, false, true);
    } else if (m.sb === 'leave') {
      setPreview(false);
    } else if (m.sb === 'text') {
      setValue(m.path, m.value, !m.done);
      const field = insp.querySelector(`[data-sb-field="${CSS.escape(m.path)}"]`);
      if (field && field.value !== m.value) field.value = m.value;
      setTree();
      if (m.done) setCanvas();
    } else if (m.sb === 'code') {
      setPick(m.sel, false, true);
      setEditor(m.path, 'Свой HTML', 'html');
    } else if (m.sb === 'md') {
      setPick(m.sel, false, true);
      setEditor(m.path, m.label || 'Текст', 'markdown');
    } else if (m.sb === 'act') {
      if (m.kind === 'blk') setBlockStep(m.act, m.id);
      else setAct(m.act, m.id);
    } else if (m.sb === 'move') {
      // A drop on the page moves exactly as a drop in the tree
      if (m.kind === 'sec' && m.to && m.to !== m.id) setMoveSec(m.id, m.to, m.after);
      if (m.kind === 'blk') setMoveBlock(m.id, m.zone, m.to, m.after);
      setCanvasPick(false);
    }
  });

  /* ---- Picking ---- */

  function setPick(sel, scroll, fromCanvas) {
    st.sel = sel || '';
    if (st.sel.startsWith('menu:')) {
      st.tab = 'menu';
      st.menu = st.sel.split(':')[1];
    } else if (st.sel.startsWith('part:') && st.tab === 'menu') {
      st.tab = 'theme';
    } else if (st.sel.startsWith('sec:') || st.sel.startsWith('page:') || st.sel.startsWith('blk:')) {
      st.tab = 'page';
    }
    setTree();
    setInspector();
    if (!fromCanvas) setCanvasPick(scroll);
    if (st.sel && matchMedia('(max-width: 900px)').matches && fromCanvas) setView('edit');
    setPlace();
  }

  /* The place of the work in this tab of the browser - the pick and the tab of the tree - comes back after a reload, as
     setTabs() of slaed.js keeps the tab of the inspector; the page is the address of the screen itself */
  function setPlace() {
    try { sessionStorage.setItem('sb.place', JSON.stringify({ page: st.page, sel: st.sel, tab: st.tab })); } catch (e) { /* storage may be off */ }
  }

  function getPlace() {
    try { return JSON.parse(sessionStorage.getItem('sb.place') || 'null'); } catch (e) { return null; }
  }

  /* ---- Acts on sections ---- */

  function setAct(act, id) {
    const list = sections();
    const idx = list.findIndex((s) => s.id === id);
    if (act === 'add') {
      setLibrary(true, idx >= 0 ? idx + 1 : list.length);
      return;
    }
    if (idx < 0) return;
    const sec = list[idx];
    const name = SB_TYPES[sec.type].title;
    if (act === 'up' || act === 'down') {
      const to = idx + (act === 'up' ? -1 : 1);
      if (to < 0 || to >= list.length) return;
      setChange(() => list.splice(to, 0, list.splice(idx, 1)[0]));
      setCanvasPick(true);
    } else if (act === 'dup') {
      // A section a page holds once - the slot of its module - has no twin
      if (SB_TYPES[sec.type].one) return;
      const copy = clone(sec);
      copy.id = newId('s');
      setChange(() => list.splice(idx + 1, 0, copy));
      setPick('sec:' + copy.id, true);
      setToast('Секция «' + name + '» продублирована.');
    } else if (act === 'copy') {
      setPagesWin(id);
    } else if (act === 'mine') {
      setMine(id);
    } else if (act === 'look-copy') {
      setLookCopy(sec);
    } else if (act === 'look-paste') {
      setLookPaste(sec);
    } else if (act === 'link') {
      setSecLink(sec);
    } else if (act === 'toggle') {
      setChange(() => { sec.on = !sec.on; });
      setToast(sec.on ? 'Секция «' + name + '» снова видна.' : 'Секция «' + name + '» скрыта от посетителей.', !sec.on);
    } else if (act === 'del') {
      // The pick lets go first: the redraw that follows must not read a section that is gone
      if (st.sel === 'sec:' + id) st.sel = '';
      setChange(() => list.splice(idx, 1));
      setToast('Секция «' + name + '» удалена.', true);
    }
  }

  /* The look of a section, as "Copy style" of other builders carries it: the settings of its tab "Вид" that shape it -
     columns, tone, cards, sides, switches - never a picture, its description, its point of focus or the icon, which are
     the section's own content */
  const getLookKeys = (type) => SB_TYPES[type].fields.filter((f) => f.tab === 'look' && ['seg', 'switch', 'tone', 'range', 'select'].includes(f.kind)).map((f) => f.key);

  function setLookCopy(sec) {
    const keys = getLookKeys(sec.type);
    st.style = { type: sec.type, from: sec.id, vals: Object.fromEntries(keys.map((k) => [k, clone(sec[k])])) };
    setInspector();
    setToast('Оформление секции ' + getSecName(sec) + ' скопировано: ' + keys.map((k) => SB_TYPES[sec.type].fields.find((f) => f.key === k).label.toLowerCase()).join(', ') + '.');
  }

  function setLookPaste(sec) {
    if (!st.style || st.style.type !== sec.type) return;
    setChange(() => Object.assign(sec, clone(st.style.vals)));
    setToast('Оформление вставлено в секцию ' + getSecName(sec) + '.', true);
  }

  // The address of a section by its anchor, to paste into a letter or a news item; the site answers it once published
  function setSecLink(sec) {
    if (!sec.anchor) return;
    const addr = String(page().addr || '').replace(/^\/+/, '');
    const url = location.origin + '/' + addr + '#' + sec.anchor;
    navigator.clipboard.writeText(url).then(() => setToast('Ссылка на секцию скопирована: ' + url + '.'),
      () => setToast('Не удалось скопировать. Скопируйте ссылку вручную: ' + url + '.', 'warn'));
  }

  // A new section of a type, or a copy of a kept one ("Мои секции"), which takes an id of its own
  function setAdd(type, at, from) {
    if (isOneTaken(type)) {
      setToast('Секция «' + SB_TYPES[type].title + '» уже есть на этой странице.', 'warn');
      return;
    }
    const sec = from ? Object.assign(clone(from), { id: newId('s') }) : getNewSec(type);
    const list = sections();
    const to = at < 0 || at > list.length ? list.length : at;
    setChange(() => list.splice(to, 0, sec));
    // The three types put last lead the library next time, one row; a kept section is not a type chosen
    if (!from) keep('sb.recent', [type].concat((read('sb.recent') || []).filter((k) => k !== type && SB_TYPES[k])).slice(0, 3));
    setLibrary(false);
    setPick('sec:' + sec.id, true);
    setToast('Добавлена секция «' + SB_TYPES[type].title + '».');
  }

  /* "Мои секции": a section kept with its settings to be put on any page again; in the system a file of storage/layout/ */
  const getMine = () => read('sb.mine') || [];

  function setMine(id) {
    const sec = getSec(id);
    if (!sec || SB_TYPES[sec.type].one) return;
    const copy = clone(sec);
    delete copy.id;
    keep('sb.mine', getMine().concat({ time: Date.now(), name: getSecName(sec), sec: copy }));
    setToast('Секция ' + getSecName(sec) + ' сохранена в «Моих секциях»: она в начале библиотеки.');
  }

  /* A copy of the picked section onto another page, at its end; the window lists the other pages of the site */
  function setPagesWin(id) {
    const box = document.querySelector('[data-sb-pages]');
    const sec = getSec(id);
    box.slSec = id;
    box.querySelector('[data-sb-pages-sub]').textContent = getSecName(sec);
    box.querySelector('[data-sb-pages-list]').innerHTML = Object.entries(st.layout.pages).filter(([k]) => k !== st.page).map(([k, pg]) =>
      getIssueRow(`data-sb-copy-to="${esc(k)}"`, 'file-earmark-richtext', pg.title, '/' + (pg.addr === '/' ? '' : pg.addr), 'chevron-right', true)).join('')
      || '<li class="sl-list-none">Других страниц нет.</li>';
    window.setWindowOpen(box);
  }

  document.querySelector('[data-sb-pages]').addEventListener('click', (e) => {
    const to = e.target.closest('[data-sb-copy-to]');
    if (!to) return;
    const box = e.currentTarget;
    const sec = getSec(box.slSec);
    const pg = st.layout.pages[to.dataset.sbCopyTo];
    if (isOneTaken(sec.type, pg.sections)) {
      setToast('Секция «' + SB_TYPES[sec.type].title + '» уже есть на странице «' + pg.title + '».', 'warn');
      return;
    }
    setChange(() => pg.sections.push(Object.assign(clone(sec), { id: newId('s') })));
    window.setWindowClose(box);
    setToast('Секция ' + getSecName(sec) + ' скопирована на страницу «' + pg.title + '».', true);
  });

  /* ---- The left column ---- */

  /* A row of the tree is a row of the panel menu (fragments/menu-list-item.html): its link picks, a nested row stands
     behind the tree pad of the panel lists, the tools of the row follow the link */
  function getRow(sel, icon, name, sub, opts) {
    const o = opts || {};
    const cur = st.sel === sel;
    return `<li class="sl-menu-list-item${o.off ? ' sl-dimmed' : ''}"${o.drag ? ` draggable="true" data-sb-drag="${esc(o.drag)}"` : ''}>
      ${o.drag ? '<span class="sl-drag-handle" aria-hidden="true"></span>' : ''}${o.depth ? '<span class="sl-tree-pad"></span>' : ''}
      <a href="#" data-sb-pick="${esc(sel)}"${cur ? ' aria-current="page"' : ''}>${ico(icon)} <span class="sl-menu-name">${esc(name)}${sub ? `<small>${esc(sub)}</small>` : ''}</span></a>
      ${o.tools || ''}
    </li>`;
  }

  function getTool(act, icon, title, data) {
    return `<button type="button" class="sl-but-mini sl-is-muted sb-row-tool" data-sb-row="${act}"${data ? ` data-sb-id="${esc(data)}"` : ''} title="${esc(title)}" aria-label="${esc(title)}">${ico(icon)}</button>`;
  }

  function getPageTree() {
    const pg = page();
    const parts = (keys) => keys.map((k) => getRow('part:' + k, SB_PARTS[k].icon, SB_PARTS[k].title, '')).join('');
    const secs = pg.sections.map((s) => getRow('sec:' + s.id, SB_TYPES[s.type].icon, SB_TYPES[s.type].title, getSecSub(s), {
      drag: 'sec:' + s.id,
      off: !s.on,
      tools: getTool('toggle', s.on ? 'eye' : 'eye-slash', s.on ? 'Скрыть' : 'Показать', s.id),
    })).join('');
    return `
      <div class="sb-group">
        <ul class="sb-rows">${getRow('page:' + st.page, st.page === 'notfound' ? 'sign-stop' : 'file-earmark-richtext', pg.title, st.page === 'notfound' ? 'Любой ненайденный адрес · 404' : 'Адрес: /' + (pg.addr === '/' ? '' : pg.addr))}</ul>
      </div>
      <div class="sb-group">
        <div class="sb-group-head">${ico('window')}Шапка <span class="sl-chip sl-chip-neutral" title="Одна на весь сайт">весь сайт</span></div>
        <ul class="sb-rows">${parts(['social', 'brand', 'pane'])}${getRow('menu:main', 'menu-button-wide', 'Главное меню', countItems('main'))}</ul>
      </div>
      <div class="sb-group">
        <div class="sb-group-head">${ico('layout-text-window')}Содержимое страницы <span class="sl-chip sl-chip-neutral">${pg.sections.length}</span></div>
        <ul class="sb-rows" data-sb-drop="sec">${secs || '<li class="sl-list-none">Секций пока нет.</li>'}</ul>
        <button type="button" class="sb-add" data-sb-lib-open>${ico('plus-lg')}Добавить секцию</button>
      </div>
      <div class="sb-group">
        <div class="sb-group-head">${ico('layout-sidebar')}Блоки <span class="sl-chip sl-chip-neutral" title="Блоки одни на весь сайт">весь сайт</span></div>
        ${getZonesHtml(pg)}
      </div>
      <div class="sb-group">
        <div class="sb-group-head">${ico('window-dock')}Подвал <span class="sl-chip sl-chip-neutral" title="Один на весь сайт">весь сайт</span></div>
        <ul class="sb-rows">${parts(['demoline', 'contacts', 'marks'])}${getRow('menu:foot', 'list-ul', 'Нижнее меню', countItems('foot'))}</ul>
      </div>`;
  }

  /* The four zones of the blocks, each a list a block is dragged into, then the free blocks a section or a tag prints. A zone
     the page switches off says so; a block whose where leaves this page is listed faded, with the reason */
  function getZonesHtml(pg) {
    const on = (set, one) => ['', '0', one].includes(String(set ?? '3'));
    const open = { l: on(pg.side, '1'), r: on(pg.side, '2'), c: on(pg.top, '1'), d: on(pg.top, '2') };
    const row = (b, n) => {
      const kind = getSbBlockKind(b);
      const here = getSbWhereShow(b.which, st.page, pg);
      const sub = (b.type === 'file' ? kind[3] : kind[1]) + (b.status ? '' : ' · выключен') + ((b.which || []).includes('infly') ? ' · №' + b.id.slice(1) : here ? '' : ' · не на этой странице');
      // The order in its position, the weight the panel shows as "⋮ 1"
      const num = n ? `<span class="sb-order" title="Порядок в позиции">${n}</span>` : '';
      return getRow('blk:' + b.id, kind[2], b.title, sub, { drag: 'blk:' + b.id, off: !b.status || (!here && !(b.which || []).includes('infly')), tools: num });
    };
    const fixed = st.layout.blocks.filter((b) => !(b.which || []).includes('infly'));
    const free = st.layout.blocks.filter((b) => (b.which || []).includes('infly')).map((b) => row(b, 0)).join('');
    return SB_ZONES.map(([z, title, icon]) => {
      const rows = fixed.filter((b) => b.pos === z).map((b, i) => row(b, i + 1)).join('');
      return `<div class="sb-zone-head">${ico(icon)}${title}${open[z] ? '' : '<span class="sl-chip sl-chip-neutral" title="Зона выключена в настройках страницы">выключена</span>'}</div>
        <ul class="sb-rows sb-zone" data-sb-drop="blk" data-sb-zone="${z}">${rows || '<li class="sl-list-none">Пусто. Перетащите блок сюда.</li>'}</ul>`;
    }).join('') + (free ? `<div class="sb-zone-head">${ico('pin-angle')}Свободные — секцией «Блок сайта» или тегом [block=N]</div><ul class="sb-rows">${free}</ul>` : '')
      + `<button type="button" class="sb-add" data-sb-row="badd">${ico('plus-lg')}Добавить блок</button>`;
  }

  function getSecSub(s) {
    const mod = s.type === 'module' ? (SB_MODULES.find((m) => m[0] === s.module) || SB_MODULES[0])[1]
      : s.type === 'block' ? ((st.layout.blocks.find((b) => b.id === s.block) || {}).title || 'блок не выбран')
      : s.type === 'item' ? (getSbItem(s.item) || [0, 0, 0, 'материал не выбран'])[3] : '';
    const words = s.title || mod || (s.type === 'html' ? 'разметка' : '');
    return words.length > 34 ? words.slice(0, 33) + '…' : words;
  }

  // The count in words as the panel writes it: a constant of forms split by "|", picked by the plural rule of the page (getPluralWord)
  function countItems(menu) {
    let n = 0;
    st.layout.menus[menu].forEach((it) => { n += 1 + it.kids.length; });
    const forms = '%s пункт|%s пункта|%s пунктов'.split('|');
    const kind = new Intl.PluralRules(document.documentElement.lang || 'ru').select(n);
    return forms[kind === 'one' ? 0 : kind === 'few' ? 1 : forms.length - 1].replace('%s', n.toLocaleString(LANG));
  }

  function getMenuTree() {
    const def = SB_MENUS[st.menu];
    const label = (it) => it.href ? (SB_LINKS.find((l) => l[0] === it.href) || [0, it.href])[1] : 'Раскрывающийся пункт';
    const row = (it, depth, first) => getRow('menu:' + st.menu + ':' + it.id, it.icon || (depth ? 'arrow-return-right' : 'link-45deg'), it.title, label(it), {
      drag: 'menu:' + st.menu + ':' + it.id,
      tools: (def.depth > 1 ? (depth ? getTool('out', 'arrow-bar-left', 'На уровень выше', it.id) : first ? '' : getTool('in', 'arrow-bar-right', 'Вложить в пункт выше', it.id)) : '') +
        getTool('mdel', 'x-lg', 'Удалить пункт', it.id),
      depth: depth,
    });
    const list = st.layout.menus[st.menu];
    return `
      <div class="sl-seg sb-fill sb-menu-pick" role="group" aria-label="Меню">${Object.entries(SB_MENUS).map(([k, m]) =>
        `<button type="button" class="sl-seg-cell${k === st.menu ? ' sl-is-active' : ''}" aria-pressed="${k === st.menu}" data-sb-menu="${k}">${esc(m.title)}</button>`).join('')}</div>
      <div class="sb-group">
        <div class="sb-group-head">${ico('geo')}${esc(def.where)} <span class="sl-chip sl-chip-neutral">${def.depth > 1 ? 'два уровня' : 'один уровень'}</span></div>
        <ul class="sb-rows" data-sb-drop="menu">${list.map((it, i) => row(it, 0, i === 0) +
          it.kids.map((k, j) => row(k, 1, j === 0)).join('')).join('')}</ul>
        <button type="button" class="sb-add" data-sb-row="madd">${ico('plus-lg')}Добавить пункт</button>
        <button type="button" class="sb-add" data-sb-row="mmods">${ico('puzzle')}Пункты из модулей</button>
      </div>
      ${note(`Перетащите пункт, чтобы сменить порядок. Стрелка вправо вкладывает пункт в пункт над ним.`)}`;
  }

  function getThemeTree() {
    const groups = {};
    Object.entries(SB_PARTS).forEach(([k, p]) => { (groups[p.where] = groups[p.where] || []).push(k); });
    return Object.entries(groups).map(([where, keys]) => `
      <div class="sb-group">
        <div class="sb-group-head">${ico('geo')}${esc(where)}</div>
        <ul class="sb-rows">${keys.map((k) => getRow('part:' + k, SB_PARTS[k].icon, SB_PARTS[k].title, '')).join('')}</ul>
      </div>`).join('') + `
      ${note(`Цвета, шрифты и отступы — токены темы. Здесь меняется то, что сайт говорит, а не то, как тема рисует.`)}`;
  }

  function setTree() {
    left.querySelectorAll('[data-sb-tab]').forEach((b) => {
      const on = b.dataset.sbTab === st.tab;
      b.classList.toggle('sl-is-active', on);
      b.setAttribute('aria-selected', String(on));
    });
    const body = left.querySelector('[data-sb-tree]');
    const top = body.scrollTop;
    setPagePick();
    body.innerHTML = st.tab === 'menu' ? getMenuTree() : st.tab === 'theme' ? getThemeTree() : getPageTree();
    body.scrollTop = top;
    const cur = body.querySelector('[aria-current="page"]');
    if (cur) cur.scrollIntoView({ block: 'nearest' });
  }

  /* ---- The library of sections ---- */

  /* The library is a window of the canon: slaed.js opens it, keeps the focus in it and closes it on Esc */
  function setLibrary(open, at) {
    if (!open) {
      if (lib.open) window.setWindowClose(lib);
      return;
    }
    st.insertAt = at === undefined ? getInsertAt() : at;
    const after = sections()[st.insertAt - 1];
    lib.querySelector('[data-sb-lib-where]').textContent = after ? 'После «' + SB_TYPES[after.type].title + '»' : 'В начало страницы';
    lib.querySelector('[data-sb-lib-find]').value = '';
    setLibraryList('');
    window.setWindowOpen(lib);
  }

  /* A card of the library and of the ready pages is a card of the panel menu (fragments/menu-grid-item.html); the schema
     of the section stands where a module card keeps its icon */
  function getCard(attr, thumb, bars, icon, title, text, off, extra) {
    return `<article class="sl-menu-grid-item sl-mcard${off ? ' sl-mcard-off' : ''}">
      <a class="sl-mcard-link" href="#" ${attr}${off ? ' aria-disabled="true" title="Уже есть на этой странице"' : ` title="${esc(text)}"`}>
        <span class="sb-thumb ${thumb}" aria-hidden="true">${bars}</span>
        <span class="sl-mbody"><strong>${ico(icon)} <span class="sl-menu-name">${esc(title)}</span></strong><span>${esc(text)}</span></span>
      </a>${extra || ''}
    </article>`;
  }

  function setLibraryList(q) {
    const words = q.trim().toLowerCase();
    const only = st.libGroup || '';
    const groups = Object.fromEntries(SB_GROUPS.map((g) => [g, []]));
    Object.entries(SB_TYPES).forEach(([k, t]) => {
      if (words && !(t.title + ' ' + t.note + ' ' + t.group).toLowerCase().includes(words)) return;
      (groups[t.group] = groups[t.group] || []).push(k);
    });
    // "Мои секции" stand first: the kept ones a search finds by their name
    const mine = getMine().map((one, i) => [one, i]).filter(([one]) => !words || one.name.toLowerCase().includes(words));
    // "Недавние" stand before the groups while nothing is searched, as the types put last
    const recent = words ? [] : (read('sb.recent') || []).filter((k) => SB_TYPES[k]).slice(0, 3);
    // The filter of the window: all, the kept, the recent, then the groups of the types, as the segmented control of the
    // panel; a group the search left empty stays in it, so the filter never jumps
    const names = [['', 'Все']].concat(getMine().length ? [['mine', 'Мои секции']] : [], (read('sb.recent') || []).length ? [['recent', 'Недавние']] : [],
      SB_GROUPS.map((g) => [g, g]));
    if (!names.some(([k]) => k === only)) st.libGroup = '';
    lib.querySelector('[data-sb-lib-groups]').innerHTML = names.map(([k, t]) =>
      `<button type="button" class="sl-seg-cell${k === (st.libGroup || '') ? ' sl-is-active' : ''}" aria-pressed="${k === (st.libGroup || '')}" data-sb-lib-group="${esc(k)}">${esc(t)}</button>`).join('');
    const show = (k) => !st.libGroup || st.libGroup === k;
    const list = lib.querySelector('[data-sb-lib-list]');
    const card = (k) => getCard('data-sb-new="' + k + '"', 'sb-thumb-' + k, '<i></i><i></i><i></i><i></i>', SB_TYPES[k].icon, SB_TYPES[k].title, SB_TYPES[k].note,
      isOneTaken(k));
    const last = recent.length && show('recent') ? `
      <div class="sb-group-head">Недавние</div>
      <div class="sl-menu-grid sb-cards">${recent.map(card).join('')}</div>` : '';
    const kept = mine.length && show('mine') ? `
      <div class="sb-group-head">Мои секции <span class="sl-chip sl-chip-neutral">${mine.length}</span></div>
      <div class="sl-menu-grid sb-cards">${mine.map(([one, i]) => getCard('data-sb-mine="' + i + '"', 'sb-thumb-' + one.sec.type, '<i></i><i></i><i></i><i></i>', SB_TYPES[one.sec.type].icon, one.name,
        'Сохранена ' + new Date(one.time).toLocaleDateString(LANG), isOneTaken(one.sec.type),
        `<button type="button" class="sl-but-mini sl-is-muted sb-mine-del" data-sb-mine-del="${i}" title="Убрать из моих секций" aria-label="Убрать из моих секций">${ico('x-lg')}</button>`)).join('')}
      </div>` : '';
    const rest = Object.entries(groups).filter(([g, keys]) => keys.length && show(g)).map(([g, keys]) => `
      <div class="sb-group-head">${esc(g)}</div>
      <div class="sl-menu-grid sb-cards">${keys.map(card).join('')}
      </div>`).join('');
    list.innerHTML = kept + last + rest || '<p class="sl-list-none">Ничего не нашлось.</p>';
  }

  /* ---- The inspector ---- */

  /* A field is a row of the panel's form canon (fragments/div-row.html): the label with its hint on the left, the control
     on the right, a wide control on a row of its own (sl-div-item-full) */
  function getField(f, path, value) {
    const id = 'f-' + path.replace(/[^a-z0-9]/gi, '-');
    const when = f.when ? ` data-sl-show-when="${f.when[0]}" data-sl-show-value="${f.when[1]}"` : '';
    // A field that differs from the site carries the way back to its published value, as "Reset" of a block editor
    const live = getLiveValue(path);
    const back = live !== undefined && JSON.stringify(live ?? '') !== JSON.stringify(value ?? '')
      ? `<button type="button" class="sl-but-mini sl-is-muted sb-revert" data-sb-revert="${esc(path)}" title="Вернуть как на сайте" aria-label="Вернуть как на сайте: ${esc(f.label)}">${ico('arrow-counterclockwise')}</button>` : '';
    const row = (inner, labelFor, wide, hint) => `<div class="sl-div-item${wide ? ' sl-div-item-full' : ''}"${when}>
      <div class="sl-div-label">${labelFor ? `<label for="${id}">${esc(f.label)}</label>` : `<span id="${id}-l">${esc(f.label)}</span>`}${back}${hint ? `<span class="sl-small">${hint}</span>` : ''}</div>
      <div class="sl-div-field">${inner}</div></div>`;
    const data = `data-sb-field="${esc(path)}"`;
    switch (f.kind) {
      case 'text':
        return row(`<input type="text" id="${id}" ${data} value="${esc(value ?? '')}">`, true);
      case 'date':
        return row(`<input type="date" id="${id}" ${data} value="${esc(value ?? '')}">`, true);
      case 'area':
        return row(`<textarea id="${id}" rows="3" ${data}>${esc(value)}</textarea>`, true);
      case 'markdown':
        // A text in Markdown is written in the editor window of the panel; the column shows its beginning, a click opens it
        return row(`<textarea id="${id}" rows="4" readonly data-sb-md="${esc(path)}" data-sb-md-label="${esc(f.label)}" title="Открыть в редакторе">${esc(value)}</textarea>
          <button type="button" class="sl-but-green sb-md-open" data-sb-md="${esc(path)}" data-sb-md-label="${esc(f.label)}">${ico('pencil-square')}Редактировать</button>`, true, true, 'Markdown: подзаголовки, списки, ссылки, картинки.');
      case 'focus': {
        // The point a cropped picture keeps in view, as object-position takes it: a grid of nine
        const at = value || 'center';
        const cells = ['left top', 'center top', 'right top', 'left center', 'center', 'right center', 'left bottom', 'center bottom', 'right bottom'];
        return row(`<span class="sb-focus" role="group" aria-labelledby="${id}-l">${cells.map((v) =>
          `<button type="button" class="sb-focus-cell" aria-pressed="${v === at}" data-sb-set="${esc(path)}" data-sb-val="${v}" title="${esc(v)}" aria-label="${esc(v)}"></button>`).join('')}</span>`);
      }
      case 'code':
        // The markup is written in the code editor of the panel; the column shows its beginning, a click opens it
        return row(`<textarea id="${id}" rows="6" class="sb-code" readonly spellcheck="false" data-sb-code="${esc(path)}" title="Открыть в редакторе кода">${esc(value)}</textarea>
          <button type="button" class="sl-but-green sb-md-open" data-sb-code="${esc(path)}">${ico('code-slash')}Редактировать</button>`, true, true, 'Выводится как есть: правят только суперадминистраторы.');
      case 'number':
        return row(`<input type="number" id="${id}" min="0" ${data} value="${esc(value ?? 0)}">`, true);
      case 'checks': {
        // Where a block shows, as the checkboxes of the panel: modules and types, the pages of the builder, the marks
        const on = [].concat(value || []);
        const box = (v, t) => `<div><label class="sl-radio"><input type="checkbox" value="${esc(v)}" data-sb-check="${esc(path)}"${on.includes(v) ? ' checked' : ''}> ${esc(t)}</label></div>`;
        const pages = Object.entries(st.layout.pages).filter(([, pg]) => !pg.sections.some((s) => s.type === 'module')).map(([k, pg]) => box(getSbPageName(k, pg), 'Страница «' + pg.title + '»'));
        return row(`<div class="sb-check-cap">Модули и типы</div><div class="sl-check-list" role="group" aria-labelledby="${id}-l">${SB_MODULES.map(([k, t]) => box(k, t)).join('')}</div>
          <div class="sb-check-cap">Страницы конструктора</div><div class="sl-check-list" role="group">${pages.join('')}</div>
          <div class="sb-check-cap">Особые</div><div class="sl-check-list" role="group">${SB_WHERE.map(([k, t]) => box(k, t)).join('')}</div>`, false, true);
      }
      case 'blockref': {
        const list = st.layout.blocks.map((b) => [b.id, '№' + b.id.slice(1) + ' — ' + b.title + ((b.which || []).includes('infly') ? ' (свободный)' : '')]);
        return row(`<select id="${id}" class="sl-select" ${data}><option value="">— выберите блок —</option>${list.map(([v, t]) => `<option value="${esc(v)}"${v === value ? ' selected' : ''}>${esc(t)}</option>`).join('')}</select>`,
          true, true, 'Свободный блок выводится только так — секцией или тегом [block=N]; обычный можно поставить и сюда.');
      }
      case 'noderef': {
        // The records grouped by their type, as the feed names the types
        const groups = SB_NODE_TYPES.map(([k, t]) => [t, SB_NODE_ITEMS.filter((r) => r[1] === k)]).filter(([, list]) => list.length);
        return row(`<select id="${id}" class="sl-select" ${data}><option value="">— выберите материал —</option>${groups.map(([t, list]) => `<optgroup label="${esc(t)}">${list.map(([v, , , title]) =>
          `<option value="${v}"${String(v) === String(value) ? ' selected' : ''}>${esc('№' + v + ' — ' + title)}</option>`).join('')}</optgroup>`).join('')}</select>`,
          true, true, 'Текст правится в модуле материала: поиск, комментарии и переводы остаются у него.');
      }
      case 'select':
        return row(`<select id="${id}" name="${id}" class="sl-select" ${data}>${f.opts.map(([v, t]) => `<option value="${esc(v)}"${v === value ? ' selected' : ''}>${esc(t)}</option>`).join('')}</select>`, true);
      case 'link': {
        // The own address is a conditional row of slaed.js (data-sl-show-when): shown while the list says "Свой адрес…"
        // The anchors of this page come first: a menu of a one-page site leads to its sections
        // The pages of the builder come next, by their addresses; a section of the site a page already answers for stays out of the second list
        const marks = sections().filter((one) => one.anchor).map((one) => ['#' + one.anchor, SB_TYPES[one.type].title + (one.title ? ' · ' + one.title : '')]);
        const pages = Object.values(st.layout.pages).filter((pg) => pg.addr).map((pg) => [pg.addr, pg.title]);
        const links = SB_LINKS.filter((l) => !pages.some((pg) => pg[0] === l[0]));
        const own = !!value && !links.some((l) => l[0] === value) && !pages.some((l) => l[0] === value) && !marks.some((l) => l[0] === value);
        return row(`<select id="${id}" name="${id}" class="sl-select" data-sb-link="${esc(path)}">
          <option value=""${!value ? ' selected' : ''}>— никуда —</option>
          ${marks.length ? `<optgroup label="На этой странице">${marks.map(([v, t]) => `<option value="${esc(v)}"${v === value ? ' selected' : ''}>${esc(v + ' — ' + t)}</option>`).join('')}</optgroup>` : ''}
          <optgroup label="Страницы сайта">${pages.map(([v, t]) => `<option value="${esc(v)}"${v === value ? ' selected' : ''}>${esc(t)}</option>`).join('')}</optgroup>
          <optgroup label="Разделы сайта">${links.map(([v, t]) => `<option value="${esc(v)}"${v === value ? ' selected' : ''}>${esc(t)}</option>`).join('')}</optgroup>
          <option value="__own"${own ? ' selected' : ''}>Свой адрес…</option></select>
          <span class="sb-own" data-sl-show-when="${id}" data-sl-show-value="__own"><input type="text" ${data} value="${own ? esc(value) : ''}" aria-label="Свой адрес" placeholder="https://"></span>`, true);
      }
      case 'range':
        return row(`<span class="sb-range"><input type="range" id="${id}" min="${f.min}" max="${f.max}" ${data} value="${esc(value)}"><output for="${id}">${esc(value)}</output></span>`, true);
      case 'switch': {
        // "Да" and "Нет" on the switch of the panel; a field that names its answers (words) takes the segmented control,
        // as its words are longer than the switch holds, and a field asked in the negative shows its positive answer
        // first and stores the opposite (flip), as "В поисковиках" stores noindex
        if (f.words) {
          return row(`<span class="sl-seg sb-fill" role="group" aria-labelledby="${id}-l">${[true, false].map((v, n) => {
            const stored = f.flip ? !v : v;
            const on = !!value === stored;
            return `<button type="button" class="sl-seg-cell${on ? ' sl-is-active' : ''}" aria-pressed="${on}" data-sb-flag="${esc(path)}" data-sb-val="${stored ? '1' : '0'}">${esc(f.words[n])}</button>`;
          }).join('')}</span>`);
        }
        return row(`<div class="sl-radio-switch sl-radio-group" role="group" aria-labelledby="${id}-l">
          <label class="sl-radio"><input type="radio" name="${id}" value="1" data-sb-bool="${esc(path)}"${value ? ' checked' : ''}> Да</label><label class="sl-radio"><input type="radio" name="${id}" value="0" data-sb-bool="${esc(path)}"${value ? '' : ' checked'}> Нет</label></div>`);
      }
      case 'multi': {
        // Several values at once: the checkbox list of the panel, at least one stays checked
        const on = [].concat(value);
        return row(`<div class="sl-check-list" role="group" aria-labelledby="${id}-l">${f.opts.map(([v, t]) =>
          `<div><label class="sl-radio"><input type="checkbox" data-sb-multi="${esc(path)}" data-sb-val="${esc(v)}"${on.includes(v) ? ' checked' : ''}> ${esc(t)}</label></div>`).join('')}</div>`, false, true);
      }
      case 'seg':
        return row(`<span class="sl-seg sb-fill" role="group" aria-labelledby="${id}-l">${f.opts.map(([v, t, i]) =>
          `<button type="button" class="sl-seg-cell${String(v) === String(value) ? ' sl-is-active' : ''}" aria-pressed="${String(v) === String(value)}" data-sb-set="${esc(path)}" data-sb-val="${esc(v)}" title="${esc(t)}">${i ? ico(i) : ''}${i && f.opts.length > 2 && f.key === 'mode' ? '' : esc(t)}</button>`).join('')}</span>`);
      case 'tone':
        // The swatches of the panel (partials/group-form.html, sl-rank-tones), each painted by its token
        return row(`<div class="sl-rank-tones" role="toolbar" aria-labelledby="${id}-l" data-sl-roving>${SB_TONES.map(([v, t]) =>
          `<button type="button" style="--sl-d-swatch: var(--sl-${v})" aria-pressed="${v === value}" data-sb-set="${esc(path)}" data-sb-val="${v}" title="${esc(t)}" aria-label="${esc(t)}"></button>`).join('')}</div>`);
      case 'icon':
        // The icon picker of the panel: its window of all Bootstrap Icons with the Russian search, as the ranks of groups use it
        return row(`<span class="sl-icon-picker"><span class="sl-ico"><i class="bi bi-${esc(value || 'star')}" data-sl-icon-preview></i></span><input type="text" id="${id}" class="sl-select-config" maxlength="64" ${data} value="${esc(value)}" data-sl-icon-input><button type="button" class="sl-but-green" title="Выбрать иконку" aria-label="Выбрать иконку" data-sl-icon-open><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i></button></span>`, true);
      case 'file':
        // The file field of the panel (fragments/file-field.html): the chip names the file, the button at the right end opens
        // the file manager, as every action of a field stands at its right
        return row(`<div class="sl-file-door"><span class="sl-chip sl-chip-neutral"${value ? '' : ' hidden'}>${ico('paperclip')}&nbsp;<span>${esc(String(value || '').split('/').pop())}</span></span>
          <button type="button" class="sl-but-green" id="${id}" data-sb-files="${esc(path)}" data-sb-pics="${esc(f.pics)}">${ico('folder2-open')}Выбрать</button></div>`, true, true);
      case 'logo':
        // As "Конфигурации" picks site_logo: the files of the theme's logos in a select, the picture under it follows the pick
        return row(`<select id="${id}" class="sl-select" ${data} data-sl-image-replace="${id}-img">${SB_LOGOS.map(([v]) => `<option value="${esc(v)}"${v === value ? ' selected' : ''}>${esc(v)}</option>`).join('')}</select>`, true) +
          `<div class="sl-div-item"><div class="sl-div-label"><span>Логотип сайта</span></div><div class="sl-div-field"><img src="../templates/lite/images/logos/${esc(value)}" id="${id}-img" alt="Логотип сайта" class="sb-logo-img"></div></div>`;
      default:
        return '';
    }
  }

  /* The items of a section, each a block of the panel's form (partials/toggle-form-block.html): the plus folds it out, its
     state kept by the toggles of slaed.js under its own id */
  const getItemToggle = (base, i) => 'sbi-' + base.replace(/[^a-z0-9]/gi, '-') + i;

  function getItems(def, base, list) {
    return `
      <div class="sb-items">
        <div class="sb-group-head">${esc(def.label)} <span class="sl-chip sl-chip-neutral">${list.length}</span></div>
        <ol class="sb-item-list" data-sb-drop="items" data-sb-base="${esc(base)}">${list.map((one, i) => `
          <li class="sl-toggle-form-block" draggable="true" data-sb-drag="item:${i}" data-sb-item="${esc(base + i)}">
            <span class="sl-toggle-form-block-title">
              <span class="sl-drag-handle" aria-hidden="true"></span>
              <span class="sl-plus sl-toggle-form-block-toggle" title="Развернуть" aria-label="Развернуть" data-sl-toggle-control="${getItemToggle(base, i)}"></span>
              ${one.icon ? ico(one.icon) : ''}
              <span class="sl-toggle-form-block-label">${esc(one[def.name] || '—')}</span>
              <button type="button" class="sl-but-mini sl-is-muted sb-row-tool" data-sb-item-del="${esc(base)}" data-sb-n="${i}" title="Удалить" aria-label="Удалить">${ico('x-lg')}</button>
            </span>
            <div class="sl-div-grid" id="${getItemToggle(base, i)}" data-sl-toggle="${getItemToggle(base, i)}" data-sl-toggle-default="closed">${def.fields.map((f) => getField(f, base + i + '.' + f.key, one[f.key])).join('')}</div>
          </li>`).join('')}
        </ol>
        <button type="button" class="sb-add" data-sb-item-add="${esc(base)}"${list.length >= def.max ? ' disabled' : ''}>${ico('plus-lg')}Добавить ${esc(def.one)}</button>
      </div>`;
  }

  function getInspector() {
    const sel = st.sel;
    const part = sel.split(':');
    if (!sel) {
      return getHead('cursor', 'Ничего не выбрано', 'Щёлкните по любой части страницы', true) + `
        <div class="sl-modal-body">
          ${st.layout.shipped ? note('Сайт ещё на раскладке из поставки. Начните с готовой страницы — лендинг, «О компании», контакты — и правьте её под себя.') : ''}
          ${note('Секция, меню, логотип, контакты — всё, что видит посетитель, выбирается щелчком на странице посередине или в списке слева. Двойной щелчок по тексту правит его на месте.')}
          <ul class="sb-keys">
            <li><kbd>Ctrl</kbd> <kbd>Z</kbd> отменить шаг</li>
            <li><kbd>Alt</kbd> <kbd>↑</kbd> <kbd>↓</kbd> секцию выше и ниже</li>
            <li><kbd>Ctrl</kbd> <kbd>D</kbd> копия выбранной секции</li>
            <li><kbd>Del</kbd> удалить выбранную секцию</li>
            <li><kbd>Esc</kbd> снять выбор</li>
          </ul>
        </div>
        <div class="sl-modal-foot"><button type="button" class="sl-but-blue" data-sb-open-presets>${ico('file-earmark-plus')}${st.layout.shipped ? 'Выбрать готовую страницу' : 'Новая страница'}</button></div>`;
    }
    if (part[0] === 'sec') {
      const s = getSec(part[1]);
      const t = SB_TYPES[s.type];
      const base = 'sec:' + s.id + ':';
      const form = (tab) => `<div class="sl-div-grid">${t.fields.filter((f) => (f.tab || 'content') === tab).map((f) => getField(f, base + f.key, s[f.key])).join('')}</div>`;
      const items = t.items ? getItems(t.items, base + 'items.', s.items) : '';
      // "Содержимое", "Вид" and "Показ" are the tabs of the panel (setTabs of slaed.js), which keep the chosen tab for the next pick
      const show = Object.assign(getSbShow(), s);
      // The slot of a module says here, not over the module's own answer on the page, where its records are edited
      const own = s.type === 'module' ? note('Здесь стоит ответ модуля «' + esc((SB_MODULES.find((m) => m[0] === s.module) || SB_MODULES[0])[1]) + '»: его записи правятся в его разделе панели, а здесь — место на странице и «Показ».') : '';
      const tabs = [['content', 'card-text', 'Содержимое', form('content') + items + own]];
      if (t.fields.some((f) => f.tab === 'look')) tabs.push(['view', 'sliders', 'Вид', form('look')]);
      tabs.push(['show', 'eye', 'Показ', `<div class="sl-div-grid">${SB_SHOW.map((f) => getField(f, base + f.key, show[f.key])).join('')}</div>
            ${note(`«Кому» и даты — те же, что у блоков сайта. Якорь даёт секции адрес на странице: пункт меню «#${esc(show.anchor || 'якорь')}» ведёт к ней.`)}`]);
      const look = `
          <ul class="sl-tabs-nav sb-look">${tabs.map(([k, i, title], n) => `
            <li class="sl-tabs-item"><a href="#" class="sl-tabs-link${n ? '' : ' sl-is-active'}" data-sl-tab-link data-sl-tab-target="sb-look-${k}">${ico(i)}${title}</a></li>`).join('')}
          </ul>${tabs.map(([k, , , html], n) => `
          <div class="sl-tab-panel" data-sl-tab-panel="sb-look-${k}"${n ? ' hidden' : ''}>${html}</div>`).join('')}`;
      return getHead(t.icon, t.title, 'Секция страницы «' + page().title + '»') + `
        <div class="sl-modal-body" data-sl-tabs-init="sb-look">${look}</div>
        <div class="sl-modal-foot">
          ${getFootPill([
            t.one ? '' : getFootAct('dup', 'copy', 'Дублировать (Ctrl+D)') + getFootAct('copy', 'files', 'Копировать на другую страницу') + getFootAct('mine', 'bookmark-plus', 'Сохранить в мои секции'),
            getLookKeys(s.type).length ? getFootAct('look-copy', 'paint-bucket', 'Копировать оформление')
              + getFootAct('look-paste', 'clipboard-check', st.style && st.style.type === s.type && st.style.from !== s.id ? 'Вставить оформление' : 'Вставить оформление: сначала скопируйте его у секции «' + t.title + '»', !(st.style && st.style.type === s.type && st.style.from !== s.id)) : '',
            getFootAct('toggle', s.on ? 'eye-slash' : 'eye', s.on ? 'Скрыть от посетителей' : 'Показать посетителям'),
          ])}
          <button type="button" class="sl-but-red" data-sb-sec="del">${ico('trash3')}Удалить</button>
        </div>`;
    }
    if (part[0] === 'part') {
      const p = SB_PARTS[part[1]];
      return getHead(p.icon, p.title, p.where === 'Весь сайт' ? 'Весь сайт' : p.where + ' · весь сайт') + `
        <div class="sl-modal-body">
          <div class="sl-div-grid">${p.fields.map((f) => getField(f, 'theme:' + f.key, st.layout.theme[f.key])).join('')}</div>
          ${p.items ? getItems(p.items, 'theme:' + p.items.key + '.', st.layout.theme[p.items.key]) : ''}
          ${p.hint ? note(esc(p.hint)) : ''}
        </div>`;
    }
    if (part[0] === 'menu' && !part[2]) {
      const m = SB_MENUS[part[1]];
      return getHead(part[1] === 'main' ? 'menu-button-wide' : 'list-ul', m.title, m.where + ' · весь сайт') + `
        <div class="sl-modal-body">${note(`Пункты меню — слева, во вкладке «Меню». Щелчок по пункту на странице открывает его здесь.`)}
        <button type="button" class="sb-add" data-sb-row="madd">${ico('plus-lg')}Добавить пункт</button></div>`;
    }
    if (part[0] === 'menu') {
      const it = getMenuItem(part[1], part[2]);
      const m = SB_MENUS[part[1]];
      const base = 'menu:' + part[1] + ':' + it.id + ':';
      const fields = [{ key: 'title', label: 'Название', kind: 'text' }, { key: 'href', label: 'Ведёт на', kind: 'link' }, { key: 'who', label: 'Кому', kind: 'select', opts: SB_WHO }];
      if (m.icons) fields.push({ key: 'icon', label: 'Иконка', kind: 'icon' });
      fields.push({ key: 'blank', label: 'Новое окно', kind: 'switch' });
      return getHead('link-45deg', 'Пункт меню', m.title) + `
        <div class="sl-modal-body"><div class="sl-div-grid">${fields.map((f) => getField(f, base + f.key, it[f.key])).join('')}</div>
        ${it.kids && it.kids.length ? note(`Вложенных пунктов: ${it.kids.length}. Без адреса пункт только раскрывает их.`) : ''}</div>
        <div class="sl-modal-foot"><button type="button" class="sl-but-red" data-sb-row="mdel" data-sb-id="${esc(it.id)}">${ico('trash3')}Удалить пункт</button></div>`;
    }
    if (part[0] === 'blk') {
      const b = st.layout.blocks.find((one) => one.id === part[1]);
      const base = 'blk:' + b.id + ':';
      // The form of the panel's block in the order of its own screen; the rows of a kind show only for that kind (slaed.js)
      const kind = 'f-' + (base + 'type').replace(/[^a-z0-9]/gi, '-');
      const fields = [
        { key: 'title', label: 'Заголовок', kind: 'text' },
        { key: 'type', label: 'Вид', kind: 'select', opts: [['file', 'Файл'], ['html', 'HTML'], ['rss', 'RSS']] },
        { key: 'kind', label: 'Файл', kind: 'select', opts: SB_BLOCKS.map(([k, , , f]) => [k, f]), when: [kind, 'file'] },
        { key: 'content', label: 'Содержание', kind: 'area', when: [kind, 'html'] },
        { key: 'url', label: 'Канал RSS', kind: 'text', when: [kind, 'rss'] },
        { key: 'pos', label: 'Позиция', kind: 'select', opts: SB_ZONES.map(([z, t]) => [z, t]) },
        { key: 'which', label: 'Где показывать', kind: 'checks' },
        { key: 'status', label: 'Включён', kind: 'switch' },
        { key: 'days', label: 'Срок, дней', kind: 'number' },
        { key: 'action', label: 'По истечении', kind: 'select', opts: [['d', 'Выключить'], ['r', 'Удалить']] },
        { key: 'lang', label: 'Язык', kind: 'select', opts: SB_LANGS },
        { key: 'who', label: 'Кому', kind: 'select', opts: SB_WHO },
      ];
      const icon = getSbBlockKind(b)[2];
      return getHead(icon, 'Блок сайта', (b.which || []).includes('infly') ? 'Свободный блок №' + b.id.slice(1) : 'Блоки · весь сайт') + `
        <div class="sl-modal-body"><div class="sl-div-grid">${fields.map((f) => getField(f, base + f.key, b[f.key])).join('')}</div>
        ${note(`Это строка «Блоков и баннеров»: позиция, порядок, «Где показывать», срок, язык и «Кому» — те же поля, что в панели. «Срок, дней»: 0 — без ограничения.`)}</div>
        <div class="sl-modal-foot"><button type="button" class="sl-but-red" data-sb-row="bdel" data-sb-id="${esc(b.id)}">${ico('trash3')}Удалить блок</button></div>`;
    }
    if (part[0] === 'page') {
      const pg = st.layout.pages[part[1]];
      const base = 'page:' + part[1] + ':';
      // A page holding the slot of a module answers at the module's address with the module's SEO; its own fields are the rest
      const slot = pg.sections.find((s) => s.type === 'module');
      const mod = slot ? (SB_MODULES.find((m) => m[0] === slot.module) || SB_MODULES[0]) : null;
      // The page of a missing address has no address of its own and is never indexed
      const lost = part[1] === 'notfound';
      const own = !slot && !lost;
      const say = lost ? 'Эту страницу показывает любой ненайденный адрес с кодом ответа 404: её секции заменяют стандартное сообщение об ошибке.'
        : slot ? 'Страница модуля «' + esc(mod[1]) + '»: адрес, описание и заголовок для поисковиков — его собственные, полоса показывает его имя.'
          : 'По этому адресу страница открывается на сайте, как любая другая; описание и картинка уходят в заголовок страницы для поисковиков и соцсетей.';
      return getHead(lost ? 'sign-stop' : 'file-earmark-richtext', lost ? 'Страница 404' : 'Страница', 'Секций: ' + pg.sections.length) + `
        <div class="sl-modal-body"><div class="sl-div-grid">
          ${getField({ key: 'title', label: 'Заголовок', kind: 'text' }, base + 'title', pg.title)}
          ${own ? getField({ key: 'addr', label: 'Адрес', kind: 'text' }, base + 'addr', pg.addr) : ''}
          ${own ? getField({ key: 'desc', label: 'Описание для поисковиков', kind: 'area' }, base + 'desc', pg.desc) : ''}
          ${own ? getField({ key: 'og', label: 'Картинка для соцсетей', kind: 'file', pics: 'photos' }, base + 'og', pg.og || '') : ''}
          ${own ? getField({ key: 'noindex', label: 'В поисковиках', kind: 'switch', words: ['Показывать', 'Скрывать'], flip: true }, base + 'noindex', !!pg.noindex) : ''}
          ${own ? getField({ key: 'who', label: 'Кому', kind: 'select', opts: [['0', 'Всем'], ['1', 'Только пользователям']] }, base + 'who', pg.who || '0') : ''}
          ${getField({ key: 'band', label: 'Полоса заголовка', kind: 'switch' }, base + 'band', pg.band)}
          ${getField({ key: 'side', label: 'Боковые блоки', kind: 'select', opts: SB_SIDE }, base + 'side', pg.side)}
          ${getField({ key: 'top', label: 'Центральные блоки', kind: 'select', opts: SB_TOP }, base + 'top', pg.top)}
        </div>
        ${note(say)}
        ${note(slot ? 'На странице модуля боковые и центральные блоки — это его «Отображение блоков» в «Модулях»: поле одно на все его страницы.' : 'Боковые и центральные блоки — те же два поля, что «Отображение блоков» у модуля в «Модулях».')}
        ${own && pg.who === '1' ? note('Гость видит форму входа, как у скрытого модуля, а после входа — саму страницу.') : ''}</div>
        ${lost ? '' : `<div class="sl-modal-foot">${getFootPill([`<button type="button" class="sl-modal-act" data-sb-page-copy="${esc(part[1])}" title="Копировать страницу" aria-label="Копировать страницу">${ico('files')}</button>`])}${part[1] === 'home' ? '' : `<button type="button" class="sl-but-red" data-sb-page-del="${esc(part[1])}">${ico('trash3')}Удалить страницу</button>`}</div>`}`;
    }
    return '';
  }

  // An act of the foot of a section: a button of the capsule with its words in the title, so the acts keep one line
  const getFootAct = (act, icon, title, off) => `<button type="button" class="sl-modal-act" data-sb-sec="${act}" title="${esc(title)}" aria-label="${esc(title)}"${off ? ' disabled' : ''}>${ico(icon)}</button>`;

  // The acts of the foot as the capsule of the editors, a group each - its empty groups left out
  const getFootPill = (groups) => `<span class="sl-editor-pill sb-pill" role="toolbar" aria-label="Действия">${groups.filter(Boolean).map((g) => `<span class="sl-editor-grp">${g}</span>`).join('')}</span>`;

  /* The head of the inspector is the head of a window (fragments/window.html): icon, caption with its line, the close */
  function getHead(icon, title, sub, still) {
    const list = getSelRow(st.sel);
    const at = list.indexOf(st.sel);
    const step = (by, icon2, word) => `<button type="button" class="sl-modal-act" data-sb-step="${by}"${list[at + by] ? '' : ' disabled'} title="${word}" aria-label="${word}">${ico(icon2)}</button>`;
    const nav = at < 0 || list.length < 2 ? '' : step(-1, 'chevron-left', 'Предыдущий') + step(1, 'chevron-right', 'Следующий');
    return `<div class="sl-modal-title">${ico(icon)} <span class="sl-modal-cap"><strong>${esc(title)}</strong><span class="sl-modal-sub">${esc(sub)}</span></span>
      ${still ? '' : `<span class="sl-modal-acts">${nav}<button type="button" class="sl-modal-act sl-modal-act-close" data-sb-unpick title="Снять выбор" aria-label="Снять выбор">${ico('x-lg')}</button></span>`}</div>`;
  }

  // The neighbours of a pick in the order of its tree: the sections of the page, the items of the menu, the blocks
  function getSelRow(sel) {
    const part = String(sel).split(':');
    if (part[0] === 'sec') return sections().map((s) => 'sec:' + s.id);
    if (part[0] === 'menu' && part[2]) return (st.layout.menus[part[1]] || []).flatMap((it) => [it].concat(it.kids)).map((it) => 'menu:' + part[1] + ':' + it.id);
    if (part[0] === 'blk') {
      // The blocks as the tree lists them: by their position, then the free ones
      const free = (b) => (b.which || []).includes('infly');
      return SB_ZONES.flatMap(([z]) => st.layout.blocks.filter((b) => b.pos === z && !free(b))).concat(st.layout.blocks.filter(free)).map((b) => 'blk:' + b.id);
    }
    return [];
  }

  /* The stand's file manager: the window of the panel's file manager with its tiles (fragments/file-browser-tile.html),
     showing the files a field may take, or the pictures a text inserts (put); in the system the field opens the file
     manager itself */
  function setFiles(path, pics, put) {
    const box = document.querySelector('[data-sb-files-win]');
    const now = path ? getValue(path) : '';
    box.querySelector('[data-sb-files-list]').innerHTML = (pics === 'marks' ? SB_MARKS : SB_PHOTOS).map(([v, src, name]) => `
      <div class="sl-fm-cell"><a class="sl-fm-tile${v === now ? ' sl-is-active' : ''}" href="#" data-sb-file="${esc(v)}" data-sb-file-name="${esc(name)}" title="${esc(v)}">
        <span class="sl-fm-tile-img"><img src="${esc(src)}" alt="${esc(name)}" loading="lazy"></span>
        <span class="sl-fm-tile-cap"><b>${esc(name)}</b><small>${esc(v.split('/').slice(0, -1).join('/'))}</small></span>
      </a></div>`).join('');
    box.slFor = path;
    box.slPut = put || null;
    window.setWindowOpen(box);
  }

  /* The inspector is redrawn as a swap: the event slaed.js answers wires its tabs and conditional rows, exactly as
     after an htmx answer, so the transfer can render it on the server and swap it with htmx unchanged */
  function setInspector() {
    const scroll = insp.querySelector('.sl-modal-body');
    const top = scroll ? scroll.scrollTop : 0;
    // The published layout once per drawing: the fields compare with it
    st.live = read('sb.live');
    insp.innerHTML = getInspector();
    insp.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: { target: insp } }));
    const next = insp.querySelector('.sl-modal-body');
    if (next) next.scrollTop = top;
    app.dataset.sbPicked = st.sel ? '1' : '0';
  }

  function setAll() {
    setTree();
    setInspector();
    setCanvas();
  }

  /* The one notice of the panel: a step that removes or hides offers Undo (true), a refusal warns ('warn') */
  function setToast(text, how) {
    window.setToast(text, how === 'warn' ? { warn: true } : how ? { undo: () => setUndo(true) } : {});
  }

  /* The full-screen preview of the draft: the panels step aside and the page shows as a guest gets it, at the width of
     its device; Esc or the button brings the editor back, and nothing is picked meanwhile */
  let previewBar = null;
  function setPreview(on) {
    if (on === !!st.preview) return;
    st.preview = on;
    if (on) {
      st.guestWas = !!st.guest;
      st.guest = true;
      if (!previewBar) {
        previewBar = document.createElement('div');
        previewBar.className = 'sb-preview-bar';
        previewBar.innerHTML = `<span class="sb-preview-note">${ico('eye')}Предпросмотр черновика — так страницу увидит гость · Esc</span>
          <button type="button" class="sl-but" data-sb-preview-off>${ico('x-lg')}Вернуться к правке</button>`;
        app.appendChild(previewBar);
      }
    } else {
      st.guest = st.guestWas;
    }
    previewBar.hidden = !on;
    app.toggleAttribute('data-sb-preview', on);
    const guest = app.querySelector('[data-sb-guest]');
    setPressed(guest, st.guest);
    setCanvas();
    if (on) previewBar.querySelector('button').focus();
  }

  /* ---- The views of a narrow screen and the device of the canvas ---- */

  function setView(view) {
    app.dataset.view = view;
    app.querySelectorAll('[data-sb-view]').forEach((b) => {
      const on = b.dataset.sbView === view;
      setPressed(b, on);
    });
  }

  function setDevice(dev) {
    app.querySelector('[data-sb-frame]').dataset.device = dev;
    app.querySelectorAll('[data-sb-device]').forEach((b) => {
      const on = b.dataset.sbDevice === dev;
      setPressed(b, on);
    });
    setFit();
  }

  /* The page is drawn at the width of its device and scaled down to the stage, so a computer sees the desktop layout
     of the theme and not its tablet one squeezed into the middle column */
  const DEVICE = { desktop: 1366, tablet: 820, phone: 390 };
  function setFit() {
    const box = app.querySelector('[data-sb-frame]');
    const stage = app.querySelector('.sb-stage');
    const cs = getComputedStyle(stage);
    const w = stage.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
    const h = stage.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom);
    const narrow = matchMedia('(max-width: 900px)').matches;
    const vw = narrow ? w : DEVICE[box.dataset.device];
    const scale = Math.min(1, w / vw);
    box.style.setProperty('--sb-vw', vw + 'px');
    box.style.setProperty('--sb-vh', (h / scale) + 'px');
    box.style.setProperty('--sb-scale', String(scale));
    st.scale = scale;
    setCanvasScale();
  }

  /* The canvas is drawn smaller than it is; its rings, labels and toolbar are given back their real size */
  function setCanvasScale() {
    if (st.ready) frame.contentWindow.postMessage({ sb: 'scale', scale: st.scale || 1 }, location.origin);
  }
  new ResizeObserver(() => setFit()).observe(app.querySelector('.sb-stage'));

  /* ---- The data: the draft as the one file it is written to ---- */

  function getPhp(v, pad) {
    const p = pad || '';
    // A value with a line break goes in double quotes with \n, the rest in single quotes, as var_export would not
    const q = (s) => /\n/.test(s)
      ? '"' + String(s).replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\$/g, '\\$').replace(/\n/g, '\\n') + '"'
      : "'" + String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
    if (Array.isArray(v)) return v.length ? '[\n' + v.map((x) => p + '    ' + getPhp(x, p + '    ')).join(',\n') + ',\n' + p + ']' : '[]';
    if (v && typeof v === 'object') {
      const keys = Object.keys(v);
      return keys.length ? '[\n' + keys.map((k) => p + '    ' + q(k) + ' => ' + getPhp(v[k], p + '    ')).join(',\n') + ',\n' + p + ']' : '[]';
    }
    if (typeof v === 'boolean') return v ? 'true' : 'false';
    if (typeof v === 'number') return String(v);
    return q(v);
  }

  /* The help of the screen, the tab "Руководство" of the panel shown over the builder, so the work stays where it is: in
     the system admin/info/layout/<locale>.md through the parser, on the stand the file beside the demo through getSbMd() */
  const helpWin = document.querySelector('[data-sb-help]');

  function setHelp() {
    const body = helpWin.querySelector('[data-sb-help-body]');
    if (!body.dataset.loaded) {
      body.dataset.loaded = '1';
      fetch('assets/sb-help.md', { cache: 'no-store' }).then((reply) => (reply.ok ? reply.text() : Promise.reject(new Error(String(reply.status)))))
        .then((text) => { body.innerHTML = getSbMd(text); })
        .catch(() => { body.dataset.loaded = ''; body.innerHTML = note('Руководство не загрузилось — откройте его ещё раз.'); });
    }
    window.setWindowOpen(helpWin);
  }

  // "Настройки": the file of the layout and the screens of the panel where the rest is set
  function setData(open) {
    if (!open) {
      if (dialog.open) window.setWindowClose(dialog);
      return;
    }
    const file = Object.assign({}, st.layout);
    delete file.next;
    const php = "<?php\n# Site layout: theme parts, menus and pages, written by the site builder\n\nreturn " + getPhp(file) + ';\n';
    // The file opens in the editor of the panel to read, as the screen "Шаблон" shows a file: search, folds, wrap, copy
    const box = dialog.querySelector('[data-sb-php]');
    box.innerHTML = getEdFrame('config/layout.php', 'php', 'sb-layout-php', '', true);
    box.querySelector('textarea').textContent = php;
    const bytes = new Blob([php]).size;
    const secs = Object.values(st.layout.pages).reduce((n, pg) => n + pg.sections.length, 0);
    dialog.querySelector('[data-sb-php-meta]').textContent = (bytes / 1024).toFixed(1).replace('.', ',') + ' КБ · страниц: ' +
      Object.keys(st.layout.pages).length + ' · секций: ' + secs + ' · запросов к базе: 0';
    window.setWindowOpen(dialog);
    document.dispatchEvent(new CustomEvent('htmx:load', { detail: { elt: box } }));
  }

  /* ---- Events ---- */

  app.addEventListener('click', (e) => {
    const t = e.target;
    const pick = t.closest('[data-sb-pick]');
    if (pick) { e.preventDefault(); setPick(pick.dataset.sbPick, true); return; }
    const tab = t.closest('[data-sb-tab]');
    if (tab) { st.tab = tab.dataset.sbTab; setLibrary(false); setTree(); setPlace(); return; }
    const menu = t.closest('[data-sb-menu]');
    if (menu) { st.menu = menu.dataset.sbMenu; setPick('menu:' + st.menu, true); return; }
    const multi = t.closest('[data-sb-multi]');
    if (multi) {
      const now = [].concat(getValue(multi.dataset.sbMulti));
      const val = multi.dataset.sbVal;
      const next = now.includes(val) ? now.filter((v) => v !== val) : now.concat(val);
      if (!next.length) {
        setToast('Нужен хотя бы один тип.', 'warn');
        setInspector();
        return;
      }
      setValue(multi.dataset.sbMulti, next, false);
      setAll();
      return;
    }
    const flag = t.closest('[data-sb-flag]');
    if (flag) {
      setValue(flag.dataset.sbFlag, flag.dataset.sbVal === '1', false);
      setAll();
      return;
    }
    const set = t.closest('[data-sb-set]');
    if (set) {
      setValue(set.dataset.sbSet, set.dataset.sbVal, false);
      setAll();
      return;
    }
    if (t.closest('[data-sb-unpick]')) { setPick(''); return; }
    const step = t.closest('[data-sb-step]');
    if (step) {
      const list = getSelRow(st.sel);
      const next = list[list.indexOf(st.sel) + Number(step.dataset.sbStep)];
      if (next) setPick(next, true);
      return;
    }
    const revert = t.closest('[data-sb-revert]');
    if (revert) {
      const path = revert.dataset.sbRevert;
      setValue(path, clone(getLiveValue(path)), false);
      setAll();
      setToast('Поле вернулось к значению на сайте.', true);
      return;
    }
    if (t.closest('[data-sb-lib-open]')) { setLibrary(true); return; }
    const group = t.closest('[data-sb-lib-group]');
    if (group) {
      st.libGroup = group.dataset.sbLibGroup;
      setLibraryList(lib.querySelector('[data-sb-lib-find]').value);
      return;
    }
    const card = t.closest('[data-sb-new]');
    if (card) {
      e.preventDefault();
      if (card.getAttribute('aria-disabled') !== 'true') setAdd(card.dataset.sbNew, st.insertAt);
      return;
    }
    const kept = t.closest('[data-sb-mine]');
    if (kept) {
      e.preventDefault();
      const one = getMine()[Number(kept.dataset.sbMine)];
      if (kept.getAttribute('aria-disabled') !== 'true') setAdd(one.sec.type, st.insertAt, one.sec);
      return;
    }
    const drop = t.closest('[data-sb-mine-del]');
    if (drop) {
      const list = getMine();
      const gone = list.splice(Number(drop.dataset.sbMineDel), 1)[0];
      keep('sb.mine', list);
      setLibraryList(lib.querySelector('[data-sb-lib-find]').value);
      setToast('Секция «' + gone.name + '» убрана из «Моих секций».');
      return;
    }
    if (t.closest('[data-sb-open-presets]')) { setPresets(); return; }
    const code = t.closest('[data-sb-code]');
    if (code) { setEditor(code.dataset.sbCode, 'Свой HTML', 'html'); return; }
    const md = t.closest('[data-sb-md]');
    if (md) { setEditor(md.dataset.sbMd, md.dataset.sbMdLabel, 'markdown'); return; }
    const pcopy = t.closest('[data-sb-page-copy]');
    if (pcopy) { setPageCopy(pcopy.dataset.sbPageCopy); return; }
    const pdel = t.closest('[data-sb-page-del]');
    if (pdel) { setPageDel(pdel.dataset.sbPageDel); return; }
    if (t.closest('[data-sb-open-link]')) { linkWin.querySelector('[data-sb-link-box]').hidden = true; window.setWindowOpen(linkWin); return; }
    if (t.closest('[data-sb-preview-off]')) { setPreview(false); return; }
    const guest = t.closest('[data-sb-guest]');
    // The second click of a double click is the preview's, not a second toggle
    if (guest && e.detail > 1) return;
    if (guest) {
      st.guest = !st.guest;
      setPressed(guest, st.guest);
      setCanvas();
      setToast(st.guest ? 'Страница показана так, как её видит гость. Блоки показаны как администратору.' : 'Снова видно всё, что правится.');
      return;
    }
    const files = t.closest('[data-sb-files]');
    if (files) { setFiles(files.dataset.sbFiles, files.dataset.sbPics); return; }
    const sec = t.closest('[data-sb-sec]');
    if (sec) { setAct(sec.dataset.sbSec, st.sel.slice(4)); return; }
    const del = t.closest('[data-sb-item-del]');
    if (del) {
      e.preventDefault();
      const slot = getSlot(del.dataset.sbItemDel.replace(/\.$/, ''));
      setChange(() => slot.obj[slot.key].splice(Number(del.dataset.sbN), 1));
      setToast('Удалено.', true);
      return;
    }
    const add = t.closest('[data-sb-item-add]');
    if (add) {
      const base = add.dataset.sbItemAdd;
      const slot = getSlot(base.replace(/\.$/, ''));
      const def = base.startsWith('theme:') ? Object.values(SB_PARTS).find((p) => p.items && base === 'theme:' + p.items.key + '.').items : SB_TYPES[getSec(base.split(':')[1]).type].items;
      const one = def.make();
      if (base.startsWith('theme:')) one.id = newId('n');
      setChange(() => slot.obj[slot.key].push(one));
      window.setToggleState(getItemToggle(base, slot.obj[slot.key].length - 1), '', '1');
      setInspector();
      return;
    }
    const row = t.closest('[data-sb-row]');
    if (row) {
      if (row.dataset.sbRow === 'toggle') setAct('toggle', row.dataset.sbId);
      else if (row.dataset.sbRow === 'badd' || row.dataset.sbRow === 'bdel') setBlockAct(row.dataset.sbRow, row.dataset.sbId);
      else setMenuAct(row.dataset.sbRow, row.dataset.sbId);
      return;
    }
    const view = t.closest('[data-sb-view]');
    if (view) { setView(view.dataset.sbView); return; }
    const dev = t.closest('[data-sb-device]');
    if (dev) { setDevice(dev.dataset.sbDevice); return; }
    if (t.closest('[data-sb-undo]')) { setUndo(true); return; }
    if (t.closest('[data-sb-redo]')) { setUndo(false); return; }
    if (t.closest('[data-sb-open-data]')) { setData(true); return; }
    if (t.closest('[data-sb-open-help]')) { setHelp(); return; }
    if (t.closest('[data-sb-publish]')) { setChecks(true); return; }
    if (t.closest('[data-sb-open-checks]')) { setChecks(false); return; }
    if (t.closest('[data-sb-open-story]')) { setStory(); return; }
    if (t.closest('[data-sb-open-palette]')) { setPalette(); return; }
  });

  /* A block: a new one lands in the right column, a deleted one goes with Undo */
  function setBlockAct(act, id) {
    if (act === 'badd') {
      const b = { id: newId('b'), title: 'Новый блок', type: 'html', kind: 'voting', content: '<p>Текст блока</p>', url: '', pos: 'r', which: ['all'],
        status: true, days: 0, action: 'd', lang: '', who: '0' };
      setChange(() => st.layout.blocks.push(b));
      setPick('blk:' + b.id, true);
      return;
    }
    const idx = st.layout.blocks.findIndex((b) => b.id === id);
    const b = st.layout.blocks[idx];
    if (st.sel === 'blk:' + id) st.sel = '';
    setChange(() => st.layout.blocks.splice(idx, 1));
    setToast('Блок «' + b.title + '» удалён.', true);
  }

  /* A block one step up or down among the blocks of its position, as the arrows of the canvas ask */
  function setBlockStep(act, id) {
    const list = st.layout.blocks;
    const b = list.find((one) => one.id === id);
    const same = list.filter((one) => one.pos === b.pos && !(one.which || []).includes('infly'));
    const near = same[same.indexOf(b) + (act === 'up' ? -1 : 1)];
    if (!near) return;
    setMoveBlock(id, b.pos, near.id, act === 'down');
  }

  /* A block dropped into a zone takes that position and stands beside the block it was dropped on, or at the end */
  function setMoveBlock(id, zone, to, after) {
    setChange(() => {
      const list = st.layout.blocks;
      const one = list.splice(list.findIndex((b) => b.id === id), 1)[0];
      one.pos = zone;
      const at = to ? list.findIndex((b) => b.id === to) + (after ? 1 : 0) : list.length;
      list.splice(at, 0, one);
    });
  }

  function setMenuAct(act, id) {
    const menu = st.menu;
    if (act === 'mmods') {
      const list = st.layout.menus[menu];
      const have = (key) => list.some((it) => it.href === key || it.kids.some((k) => k.href === key));
      const add = SB_MODULES.filter(([key]) => !have(key));
      if (!add.length) {
        setToast('Все модули уже есть в меню.', 'warn');
        return;
      }
      setChange(() => add.forEach(([key, title, icon]) => list.push({ id: newId('m'), title: title, href: key, icon: SB_MENUS[menu].icons ? icon : '', blank: false, kids: [] })));
      setToast('Добавлено пунктов: ' + add.length + ' — ' + add.map((m) => m[1]).join(', ') + '.', true);
      return;
    }
    if (act === 'madd') {
      const it = { id: newId('m'), title: 'Новый пункт', href: 'news', icon: SB_MENUS[menu].icons ? 'link-45deg' : '', blank: false, kids: [] };
      setChange(() => st.layout.menus[menu].push(it));
      setPick('menu:' + menu + ':' + it.id, true);
      return;
    }
    const list = getMenuParent(menu, id);
    const idx = list.findIndex((it) => it.id === id);
    const it = list[idx];
    if (act === 'mdel') {
      if (st.sel.endsWith(':' + id)) st.sel = 'menu:' + menu;
      setChange(() => list.splice(idx, 1));
      setToast('Пункт «' + it.title + '» удалён' + (it.kids.length ? ' вместе с вложенными' : '') + '.', true);
    } else if (act === 'in' && idx > 0) {
      setChange(() => {
        list.splice(idx, 1);
        list[idx - 1].kids.push(it, ...it.kids);
        it.kids = [];
      });
    } else if (act === 'out') {
      const top = st.layout.menus[menu];
      const parent = top.find((p) => p.kids.includes(it));
      setChange(() => {
        list.splice(idx, 1);
        top.splice(top.indexOf(parent) + 1, 0, it);
      });
    }
  }

  app.addEventListener('input', (e) => {
    const t = e.target;
    if (t.matches('[data-sb-lib-find]')) { setLibraryList(t.value); return; }
    const path = t.dataset.sbField;
    if (!path) return;
    setValue(path, t.value, true);
    const out = t.parentNode.querySelector('output');
    if (out) out.textContent = t.value;
    const item = t.closest('[data-sb-item]');
    if (item) {
      const name = item.querySelector('.sl-toggle-form-block-label');
      const def = item.closest('.sb-items');
      if (name && def && path.endsWith('.' + (getItemNameKey(path) || ''))) name.textContent = t.value || '—';
    }
    setTreeSoft();
    setCanvas();
    // A pick in the icon window writes the field from outside it; the summary of its item then shows the new icon
    if (t.matches('[data-sl-icon-input]') && document.activeElement !== t) setInspector();
  });

  function getItemNameKey(path) {
    const sec = path.startsWith('sec:') ? getSec(path.split(':')[1]) : null;
    if (sec && SB_TYPES[sec.type].items) return SB_TYPES[sec.type].items.name;
    const part = path.startsWith('theme:') ? Object.values(SB_PARTS).find((p) => p.items && path.startsWith('theme:' + p.items.key + '.')) : null;
    return part ? part.items.name : '';
  }

  /* The tree follows a typed title without being redrawn under the hand */
  let treeTimer = 0;
  function setTreeSoft() {
    clearTimeout(treeTimer);
    treeTimer = setTimeout(setTree, 250);
  }

  app.addEventListener('change', (e) => {
    const t = e.target;
    if (t.dataset.sbBool) {
      setValue(t.dataset.sbBool, t.value === '1', false);
      setAll();
    } else if (t.dataset.sbLink) {
      // "Свой адрес…" only opens its row (slaed.js); the address itself is the value typed there
      if (t.value === '__own') {
        const own = t.closest('.sl-div-field').querySelector('.sb-own input');
        if (own) own.focus();
        return;
      }
      setValue(t.dataset.sbLink, t.value, false);
      setTree();
      setCanvas();
    } else if (t.dataset.sbCheck) {
      const list = [...t.closest('.sl-div-field').querySelectorAll('[data-sb-check]:checked')].map((c) => c.value);
      // The panel rewrites the marks on save; the boxes show at once what it will keep
      setValue(t.dataset.sbCheck, getSbWhich(list), false);
      setAll();
    } else if (t.matches('select[data-sb-field]')) {
      setAll();
    } else if (t.matches('[data-sb-page-pick]')) {
      if (t.value === '__new') {
        setPagePick();
        setPresets();
      } else {
        setPageOpen(t.value);
      }
    }
  });

  function setPageOpen(key) {
    st.page = key;
    st.sel = '';
    setPagePick();
    setAll();
    history.replaceState(null, '', '?page=' + encodeURIComponent(key));
    app.querySelector('[data-sb-site]').href = 'sb-site.html?page=' + encodeURIComponent(key);
  }

  function setPagePick() {
    const pick = app.querySelector('[data-sb-page-pick]');
    pick.innerHTML = Object.entries(st.layout.pages).map(([k, pg]) => `<option value="${esc(k)}"${k === st.page ? ' selected' : ''}>${esc(pg.title)}</option>`).join('') +
      '<option value="__new">+ Новая страница</option>';
  }

  /* Dragging: sections and menu items in the tree, items in the inspector, a card of the library into the sections */
  let drag = null;
  app.addEventListener('dragstart', (e) => {
    const card = e.target.closest('[data-sb-new]');
    const row = e.target.closest('[data-sb-drag]');
    drag = card ? { kind: 'new', type: card.dataset.sbNew } : row ? { kind: row.dataset.sbDrag.split(':')[0], key: row.dataset.sbDrag, row: row } : null;
    if (!drag) return;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', drag.key || drag.type);
    if (row) row.classList.add('sl-dragging');
  });

  app.addEventListener('dragover', (e) => {
    if (!drag) return;
    const list = e.target.closest('[data-sb-drop]');
    const want = { sec: 'sec', new: 'sec', menu: 'menu', item: 'items', blk: 'blk' }[drag.kind];
    if (!list || list.dataset.sbDrop !== want) return;
    e.preventDefault();
    app.querySelectorAll('.sl-drop-above, .sl-drop-below').forEach((n) => n.classList.remove('sl-drop-above', 'sl-drop-below'));
    const over = e.target.closest('[data-sb-drag]');
    if (over && over !== drag.row) {
      const r = over.getBoundingClientRect();
      over.classList.add(e.clientY < r.top + r.height / 2 ? 'sl-drop-above' : 'sl-drop-below');
    }
  });

  app.addEventListener('drop', (e) => {
    if (!drag) return;
    const over = app.querySelector('.sl-drop-above, .sl-drop-below');
    const list = e.target.closest('[data-sb-drop]');
    e.preventDefault();
    if (list && drag.kind === 'blk') {
      setMoveBlock(drag.key.slice(4), list.dataset.sbZone, over ? over.dataset.sbDrag.slice(4) : '', over ? over.classList.contains('sl-drop-below') : false);
    } else if (list && drag.kind === 'new') {
      const at = over ? sections().findIndex((s) => 'sec:' + s.id === over.dataset.sbDrag) + (over.classList.contains('sl-drop-below') ? 1 : 0) : sections().length;
      setAdd(drag.type, at);
    } else if (over) {
      const after = over.classList.contains('sl-drop-below');
      if (drag.kind === 'sec') setMoveSec(drag.key.slice(4), over.dataset.sbDrag.slice(4), after);
      if (drag.kind === 'menu') setMoveMenu(drag.key.split(':')[2], over.dataset.sbDrag.split(':')[2], after);
      if (drag.kind === 'item') setMoveItem(list.dataset.sbBase, Number(drag.key.split(':')[1]), Number(over.dataset.sbDrag.split(':')[1]), after);
    }
    setDragEnd();
  });

  app.addEventListener('dragend', setDragEnd);

  function setDragEnd() {
    app.querySelectorAll('.sl-drop-above, .sl-drop-below, .sl-dragging').forEach((n) => n.classList.remove('sl-drop-above', 'sl-drop-below', 'sl-dragging'));
    drag = null;
  }

  function setMoveSec(id, to, after) {
    const list = sections();
    setChange(() => {
      const one = list.splice(list.findIndex((s) => s.id === id), 1)[0];
      list.splice(list.findIndex((s) => s.id === to) + (after ? 1 : 0), 0, one);
    });
    setCanvasPick(true);
  }

  /* A menu item lands beside the item it is dropped on, at its level */
  function setMoveMenu(id, to, after) {
    const menu = st.menu;
    setChange(() => {
      const from = getMenuParent(menu, id);
      const one = from.splice(from.findIndex((it) => it.id === id), 1)[0];
      const into = getMenuParent(menu, to);
      if (into !== st.layout.menus[menu] && one.kids.length) {
        into.splice(into.findIndex((it) => it.id === to) + (after ? 1 : 0), 0, one, ...one.kids);
        one.kids = [];
      } else {
        into.splice(into.findIndex((it) => it.id === to) + (after ? 1 : 0), 0, one);
      }
    });
  }

  function setMoveItem(base, from, to, after) {
    const slot = getSlot(base.replace(/\.$/, ''));
    const list = slot.obj[slot.key];
    setChange(() => {
      const one = list.splice(from, 1)[0];
      const at = to + (after ? 1 : 0) - (from < to ? 1 : 0);
      list.splice(at, 0, one);
    });
  }

  document.addEventListener('keydown', (e) => {
    const typing = e.target.matches('input, textarea, select, [contenteditable]');
    if ((e.ctrlKey || e.metaKey) && e.code === 'KeyK' && !pal.open && !e.defaultPrevented) {
      e.preventDefault();
      setPalette();
      return;
    }
    if ((e.ctrlKey || e.metaKey) && !typing && e.key.toLowerCase() === 'z') {
      e.preventDefault();
      setUndo(!e.shiftKey);
    } else if ((e.ctrlKey || e.metaKey) && !typing && e.key.toLowerCase() === 'y') {
      e.preventDefault();
      setUndo(false);
    } else if ((e.ctrlKey || e.metaKey) && e.altKey && !typing && e.code === 'KeyP') {
      e.preventDefault();
      setPreview(!st.preview);
    } else if (e.key === 'Escape') {
      // An open window answers its own Esc (slaed.js); the preview goes first, then a bare editor lets go of the pick
      if (document.querySelector('dialog[open]')) return;
      if (st.preview) {
        setPreview(false);
        return;
      }
      if (st.sel) setPick('');
    } else if (!typing && st.sel.startsWith('sec:') && e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) {
      e.preventDefault();
      setAct(e.key === 'ArrowUp' ? 'up' : 'down', st.sel.slice(4));
    } else if ((e.ctrlKey || e.metaKey) && !typing && st.sel.startsWith('sec:') && e.code === 'KeyD') {
      e.preventDefault();
      setAct('dup', st.sel.slice(4));
    } else if (!typing && st.sel.startsWith('sec:') && (e.key === 'Delete')) {
      setAct('del', st.sel.slice(4));
    }
  });

  /* ---- Checks before publishing: what a visitor would trip over, each with the way to its place ---- */

  const today = new Date().toISOString().slice(0, 10);
  // An address the site answers: an outer one, a section of the site, a page of the builder or a file of uploads/
  const isLink = (v) => {
    const bare = String(v).replace(/^\/(?=.)/, '');
    return /^(https?:\/\/|mailto:|#)/.test(v) || /^uploads\//.test(bare)
      || SB_LINKS.some((l) => l[0] === v || l[0] === bare) || Object.values(st.layout.pages).some((pg) => pg.addr === bare);
  };

  // The links and pictures of a Markdown text, as the parser reads [words](address) and ![description](address)
  const getMdLinks = (text) => Array.from(String(text || '').matchAll(/(!?)\[([^\]]*)\]\(([^)\s]+)\)/g), (m) => ({ pic: !!m[1], words: m[2], url: m[3] }));

  // Every Markdown text of a section: its own fields and those of its items
  function getMdTexts(t, one) {
    const keys = (fields) => fields.filter((f) => f.kind === 'markdown').map((f) => f.key);
    return keys(t.fields).map((k) => one[k]).concat(t.items ? (one.items || []).flatMap((it) => keys(t.items.fields).map((k) => it[k])) : []);
  }

  function getIssues() {
    const out = [];
    const add = (page, sel, icon, text, where) => out.push({ page: page, sel: sel, icon: icon, text: text, where: where });
    Object.entries(st.layout.pages).forEach(([key, pg]) => {
      const marks = pg.sections.map((one) => one.anchor).filter(Boolean);
      pg.sections.forEach((one) => {
        const t = SB_TYPES[one.type];
        const sel = 'sec:' + one.id;
        const where = pg.title + ' · ' + t.title;
        if (t.fields.some((f) => f.key === 'title') && !String(one.title || '').trim()) add(key, sel, 'type', 'Пустой заголовок', where);
        [['btn', 'href'], ['btn2', 'href2']].forEach(([b, h]) => {
          if (!one[b]) return;
          if (!one[h]) add(key, sel, 'link-45deg', 'Кнопка «' + one[b] + '» никуда не ведёт', where);
          else if (one[h].startsWith('#') && !marks.includes(one[h].slice(1))) add(key, sel, 'hash', 'Якоря ' + one[h] + ' на странице нет', where);
          else if (!isLink(one[h])) add(key, sel, 'link-45deg', 'Адрес «' + one[h] + '» не похож на ссылку', where);
        });
        if (t.items && !(one.items || []).length) add(key, sel, 'list-ul', 'Нет ни одного элемента', where);
        if (one.anchor && !/^[a-z0-9-]+$/.test(one.anchor)) add(key, sel, 'hash', 'Якорь: только латиница, цифры и дефис', where);
        if (one.anchor && marks.filter((m) => m === one.anchor).length > 1) add(key, sel, 'hash', 'Якорь #' + one.anchor + ' повторяется', where);
        if (one.from && one.till && one.till < one.from) add(key, sel, 'calendar-x', 'Дата «до» раньше даты «с»', where);
        else if (one.till && one.till < today) add(key, sel, 'calendar-x', 'Срок показа вышел — посетители её не видят', where);
        if (one.phone === false && one.desk === false) add(key, sel, 'eye-slash', 'Не видна ни на одном устройстве', where);
        if (!one.on) add(key, sel, 'eye-slash', 'Секция скрыта от посетителей', where);
        if (one.type === 'block' && !st.layout.blocks.some((b) => b.id === one.block)) add(key, sel, 'bricks', 'Блок не выбран', where);
        if (one.type === 'item' && !getSbItem(one.item)) add(key, sel, 'file-earmark-richtext', 'Материал не выбран', where);
        if ((one.type === 'media' || (one.type === 'hero' && one.pic)) && !String(one.alt || '').trim()) add(key, sel, 'image', 'У картинки нет описания — его читают поисковики и программы чтения с экрана', where);
        if (one.type === 'gallery') (one.items || []).forEach((it) => { if (!String(it.alt || '').trim()) add(key, sel, 'image', 'Картинка галереи без описания: ' + String(it.photo || '').split('/').pop(), where); });
        if (one.type === 'video' && !String(one.url || '').trim()) add(key, sel, 'play-btn', 'Не задано видео', where);
        if (one.type === 'embed' && !/^https:\/\//.test(String(one.url || ''))) add(key, sel, 'window-stack', 'Встраиванию нужен адрес, начинающийся с https://', where);
        // Inside the texts: the same remarks as for the fields, so a link written by hand is checked as one picked
        getMdTexts(t, one).forEach((text) => getMdLinks(text).forEach((l) => {
          if (l.pic && !l.words.trim()) add(key, sel, 'image', 'Картинка в тексте без описания: ' + l.url.split('/').pop(), where);
          else if (!l.pic && l.url.startsWith('#') && !marks.includes(l.url.slice(1))) add(key, sel, 'hash', 'Ссылка в тексте: якоря ' + l.url + ' на странице нет', where);
          else if (!l.pic && !isLink(l.url)) add(key, sel, 'link-45deg', 'Ссылка в тексте: адреса «' + l.url + '» на сайте нет', where);
        }));
      });
      const twin = Object.entries(st.layout.pages).find(([k, other]) => k !== key && other.addr === pg.addr);
      if (twin && key < twin[0]) add(key, 'page:' + key, 'signpost-2', 'Адрес /' + pg.addr + ' занят страницей «' + twin[1].title + '»', pg.title);
      if (key !== 'notfound' && !pg.sections.some((one) => one.type === 'module') && !String(pg.desc || '').trim()) add(key, 'page:' + key, 'search', 'Нет описания для поисковиков', pg.title);
    });
    st.layout.blocks.forEach((b) => {
      if (b.type === 'html' && !String(b.content).trim()) add('', 'blk:' + b.id, 'code-square', 'Блок «' + b.title + '» без содержания', 'Блоки');
      if (b.type === 'rss' && !/^https?:\/\//.test(b.url)) add('', 'blk:' + b.id, 'rss', 'У блока «' + b.title + '» нет адреса ленты', 'Блоки');
    });
    const th = st.layout.theme;
    if (th.tagline.split('\n').some((line) => line.length > 20)) add('', 'part:brand', 'badge-tm', 'Строка под логотипом длиннее 20 знаков — в шапке она перенесётся', 'Шапка');
    th.social.forEach((n) => { if (!isLink(n.url) || n.url === 'https://') add('', 'part:social', 'share', 'У «' + n.title + '» нет адреса', 'Верхняя строка'); });
    if (th.tabsOn) th.tabs.forEach((n) => { if (!n.href) add('', 'part:tabs', 'bookmark', 'Ярлык «' + n.title + '» никуда не ведёт', 'Весь сайт'); });
    Object.entries(st.layout.menus).forEach(([menu, list]) => {
      const walk = (it) => {
        if (!String(it.title).trim()) add('', 'menu:' + menu + ':' + it.id, 'menu-button-wide', 'Пункт без названия', SB_MENUS[menu].title);
        if (!it.href && !it.kids.length) add('', 'menu:' + menu + ':' + it.id, 'menu-button-wide', 'Пункт «' + it.title + '» никуда не ведёт', SB_MENUS[menu].title);
        it.kids.forEach(walk);
      };
      list.forEach(walk);
    });
    return out;
  }

  /* ---- Before publishing: what the publication changes against the site, then what a visitor would trip over ---- */

  // The name of a section in a list: its type and its title
  const getSecName = (s) => SB_TYPES[s.type].title + (s.title ? ' «' + s.title + '»' : '');
  // The administrator of the stand; in the system the name of the session publishing
  const author = 'SLAED CMS';

  /* The changes of the draft against the published layout, found by the ids every section, item and block keeps: what is
     new, what is gone, what changed and what moved; a row leads to its place while the place is still there */
  function getChanges() {
    const live = read('sb.live') || getSbStart();
    const now = st.layout;
    const out = [];
    const add = (icon, text, where, sel, page) => out.push({ icon: icon, text: text, where: where, sel: sel || '', page: page || '' });
    const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
    const order = (a, b) => same(a.filter((x) => b.includes(x)), b.filter((x) => a.includes(x)));
    Object.entries(now.pages).forEach(([key, pg]) => {
      const was = live.pages[key];
      if (!was) {
        add('file-earmark-plus', 'Новая страница «' + pg.title + '»', 'Страницы', 'page:' + key, key);
        return;
      }
      if (['title', 'addr', 'desc', 'band', 'side', 'top', 'og', 'noindex', 'who'].some((k) => !same(pg[k], was[k]))) add('file-earmark-richtext', 'Изменены настройки страницы', pg.title, 'page:' + key, key);
      pg.sections.forEach((s) => {
        const old = was.sections.find((one) => one.id === s.id);
        if (!old) add('plus-square', 'Новая секция ' + getSecName(s), pg.title, 'sec:' + s.id, key);
        else if (!same(s, old)) add('pencil-square', 'Изменена секция ' + getSecName(s), pg.title, 'sec:' + s.id, key);
      });
      was.sections.forEach((s) => { if (!pg.sections.some((one) => one.id === s.id)) add('dash-square', 'Удалена секция ' + getSecName(s), pg.title, '', key); });
      if (!order(was.sections.map((s) => s.id), pg.sections.map((s) => s.id))) add('arrow-down-up', 'Изменён порядок секций', pg.title, 'page:' + key, key);
    });
    Object.entries(live.pages).forEach(([key, pg]) => { if (!now.pages[key]) add('file-earmark-minus', 'Удалена страница «' + pg.title + '»', 'Страницы'); });
    // A value of the theme belongs to the part that lists its field or its items
    const touched = Object.keys(Object.assign({}, live.theme, now.theme)).filter((k) => !same(now.theme[k], live.theme[k]));
    Object.entries(SB_PARTS).forEach(([k, p]) => {
      if (touched.some((key) => p.fields.some((f) => f.key === key) || (p.items && p.items.key === key))) add(p.icon, 'Изменена часть «' + p.title + '»', p.where, 'part:' + k);
    });
    Object.entries(SB_MENUS).forEach(([menu, m]) => {
      const flat = (list) => (list || []).flatMap((it) => [it].concat(it.kids || []));
      const was = flat(live.menus[menu]);
      const is = flat(now.menus[menu]);
      const bare = (it) => Object.assign({}, it, { kids: (it.kids || []).map((k) => k.id) });
      is.forEach((it) => {
        const old = was.find((one) => one.id === it.id);
        if (!old) add('plus-lg', 'Новый пункт «' + it.title + '»', m.title, 'menu:' + menu + ':' + it.id);
        else if (!same(bare(it), bare(old))) add('pencil', 'Изменён пункт «' + it.title + '»', m.title, 'menu:' + menu + ':' + it.id);
      });
      was.forEach((it) => { if (!is.some((one) => one.id === it.id)) add('dash-lg', 'Удалён пункт «' + it.title + '»', m.title, 'menu:' + menu); });
      if (!order(was.map((it) => it.id), is.map((it) => it.id))) add('arrow-down-up', 'Изменён порядок пунктов', m.title, 'menu:' + menu);
    });
    // The blocks wait for the publication as the layout does; they are written into _blocks with it
    now.blocks.forEach((b) => {
      const old = (live.blocks || []).find((one) => one.id === b.id);
      if (!old) add('plus-square', 'Новый блок «' + b.title + '»', 'Блоки', 'blk:' + b.id);
      else if (!same(b, old)) add('pencil-square', 'Изменён блок «' + b.title + '»', 'Блоки', 'blk:' + b.id);
    });
    (live.blocks || []).forEach((b) => { if (!now.blocks.some((one) => one.id === b.id)) add('dash-square', 'Удалён блок «' + b.title + '»', 'Блоки'); });
    if (!order((live.blocks || []).map((b) => b.id), now.blocks.map((b) => b.id))) add('arrow-down-up', 'Изменён порядок блоков', 'Блоки');
    return out;
  }

  /* The publish button and the checks open one window: the changes against the site, then the remarks; publishing is
     asked for there, so nothing reaches the site unseen */
  function setChecks(publishing) {
    const changes = getChanges();
    const list = getIssues();
    checks.slIssues = list;
    checks.slChanges = changes;
    const row = (one, i, kind) => getIssueRow(`data-sb-${kind}="${i}"${one.sel ? '' : ' disabled'}`, one.icon, one.text, one.where, one.sel ? 'chevron-right' : '', kind === 'change');
    checks.querySelector('[data-sb-checks-sub]').textContent = (changes.length ? 'Изменений: ' + changes.length : 'Изменений нет') + ' · ' + (list.length ? 'замечаний: ' + list.length : 'замечаний нет');
    checks.querySelector('[data-sb-changes-num]').textContent = String(changes.length);
    checks.querySelector('[data-sb-issues-num]').textContent = String(list.length);
    checks.querySelector('[data-sb-changes-list]').innerHTML = changes.length ? changes.map((one, i) => row(one, i, 'change')).join('')
      : '<li class="sl-list-none">Черновик совпадает с сайтом.</li>';
    checks.querySelector('[data-sb-checks-list]').innerHTML = list.length ? list.map((one, i) => row(one, i, 'issue')).join('')
      : '<li class="sl-list-none">Всё в порядке: ссылки ведут туда, куда нужно, заголовки на месте.</li>';
    const go = checks.querySelector('[data-sb-publish-now]');
    go.hidden = !publishing;
    checks.querySelector('[data-sb-note-row]').hidden = !publishing || !changes.length;
    checks.querySelector('[data-sb-discard]').hidden = !changes.length;
    checks.querySelector('[data-sb-note]').value = '';
    go.disabled = !changes.length;
    checks.querySelector('[data-sb-publish-word]').textContent = list.length ? 'Опубликовать всё равно' : 'Опубликовать';
    window.setWindowOpen(checks);
  }

  // A publication carries the note of its author, if one was written, beside the counts the history always shows
  function setPublish(note) {
    // The first publication takes away the mark of the shipped layout
    delete st.layout.shipped;
    keep('sb.draft', st.layout);
    keep('sb.live', st.layout);
    const story = read('sb.history') || [];
    const secs = Object.values(st.layout.pages).reduce((n, pg) => n + pg.sections.length, 0);
    story.unshift({ time: Date.now(), author: author, text: String(note || '').trim().slice(0, 120), note: 'Страниц: ' + Object.keys(st.layout.pages).length + ' · секций: ' + secs, layout: clone(st.layout) });
    keep('sb.history', story.slice(0, 10));
    setSaved();
    setToast('Опубликовано. Прежняя версия сохранена в истории.');
  }

  checks.addEventListener('click', (e) => {
    const one = e.target.closest('[data-sb-issue], [data-sb-change]');
    if (one) {
      const it = one.dataset.sbIssue !== undefined ? checks.slIssues[Number(one.dataset.sbIssue)] : checks.slChanges[Number(one.dataset.sbChange)];
      window.setWindowClose(checks);
      if (it.page && it.page !== st.page) setPageOpen(it.page);
      setPick(it.sel, true);
      return;
    }
    if (e.target.closest('[data-sb-publish-now]')) {
      window.setWindowClose(checks);
      setPublish(checks.querySelector('[data-sb-note]').value);
    }
    if (e.target.closest('[data-sb-discard]')) {
      window.setWindowClose(checks);
      setDiscard();
    }
  });

  /* The whole draft replaced in one step with Undo - a version of the history, an imported file, the site again; the
     counter of ids keeps the larger value, so an id is never given twice, and the page and the pick stay while they exist */
  function setLayout(next) {
    const from = st.page;
    setChange(() => {
      st.layout = Object.assign(next, { next: Math.max(next.next || 0, st.layout.next || 0) });
      if (!st.layout.pages[st.page]) st.page = 'home';
      if (st.sel && !getSelNode(st.sel)) st.sel = '';
    });
    if (st.page !== from) setPageOpen(st.page);
    else setPagePick();
  }

  // The draft becomes the site again
  function setDiscard() {
    const count = getChanges().length;
    setLayout(clone(read('sb.live') || getSbStart()));
    setToast('Изменений отменено: ' + count + '. Черновик совпадает с сайтом.', true);
  }

  // Enter in the note publishes, as the button beside it would
  checks.querySelector('[data-sb-note]').addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    checks.querySelector('[data-sb-publish-now]').click();
  });

  /* ---- History of publications: the last ten copies of the file with their authors; a copy comes back as the draft,
     or one section of it comes back into the draft ---- */

  const getWhen = (time, style) => new Date(time).toLocaleString(LANG, { dateStyle: style || 'medium', timeStyle: 'short' });

  function setStory() {
    const list = read('sb.history') || [];
    const live = JSON.stringify(read('sb.live'));
    story.slList = list;
    story.querySelector('[data-sb-story-list]').innerHTML = list.length ? list.map((one, i) => {
      const secs = Object.entries(one.layout.pages).flatMap(([key, pg]) => pg.sections.map((s) => [key, pg.title, s]));
      const box = 'sb-story-' + one.time;
      return `
      <li class="sb-story-row">
        <span class="sb-story-ico">${ico(i ? 'clock-history' : 'cloud-check')}</span>
        <span class="sb-story-text"><b>${esc(getWhen(one.time))}${one.text ? ' — ' + esc(one.text) : ''}</b>
          <span>${esc(one.author || '—')} · ${esc(one.note)}${JSON.stringify(one.layout) === live ? ' · сейчас на сайте' : ''}</span></span>
        <button type="button" class="sl-but" data-sb-restore="${i}">${ico('arrow-counterclockwise')}Вернуть в черновик</button>
        <div class="sb-story-secs">
          <span class="sl-toggle-form-block-title"><span class="sl-plus sl-toggle-form-block-toggle" title="Секции этой версии" aria-label="Секции этой версии" data-sl-toggle-control="${box}"></span><span class="sl-toggle-form-block-label">Вернуть одну секцию · ${secs.length}</span></span>
          <ul class="sb-issues" id="${box}" data-sl-toggle="${box}" data-sl-toggle-default="closed">${secs.map(([key, title, s]) =>
            getIssueRow(`data-sb-back="${i}:${esc(key)}:${esc(s.id)}"`, SB_TYPES[s.type].icon, getSecName(s), title, 'arrow-counterclockwise', true)).join('')}
          </ul>
        </div>
      </li>`;
    }).join('')
      : `<li class="sl-list-none">Публикаций пока нет. Здесь хранятся десять последних.</li>`;
    // The folds of the list are the toggles of slaed.js, wired as after an htmx answer
    story.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: { target: story } }));
    window.setWindowOpen(story);
  }

  // A tile of the stand's file manager writes its path into the field that opened it, or inserts its picture into the text
  document.querySelector('[data-sb-files-win]').addEventListener('click', (e) => {
    const tile = e.target.closest('[data-sb-file]');
    if (!tile) return;
    e.preventDefault();
    const box = e.currentTarget;
    window.setWindowClose(box);
    if (box.slPut) {
      box.slPut(tile.dataset.sbFile, tile.dataset.sbFileName);
      return;
    }
    setValue(box.slFor, tile.dataset.sbFile, false);
    setAll();
  });

  story.addEventListener('click', (e) => {
    const sec = e.target.closest('[data-sb-back]');
    if (sec) {
      setSecBack(...sec.dataset.sbBack.split(':'));
      return;
    }
    const back = e.target.closest('[data-sb-restore]');
    if (!back) return;
    const one = story.slList[Number(back.dataset.sbRestore)];
    window.setWindowClose(story);
    setLayout(clone(one.layout));
    setToast('Версия от ' + getWhen(one.time, 'short') + ' возвращена в черновик. Опубликуйте её, чтобы она вернулась на сайт.', true);
  });

  /* One section of a publication back into the draft: in its place if the page still holds it, else at its old place in
     the order; the ids are never given twice, so the section is found by its own */
  function setSecBack(n, key, id) {
    const one = story.slList[Number(n)];
    const from = one.layout.pages[key];
    const sec = from.sections.find((s) => s.id === id);
    const pg = st.layout.pages[key];
    if (!pg) {
      setToast('Страницы «' + from.title + '» в черновике больше нет.', 'warn');
      return;
    }
    const at = pg.sections.findIndex((s) => s.id === id);
    if (at < 0 && isOneTaken(sec.type, pg.sections)) {
      setToast('Секция «' + SB_TYPES[sec.type].title + '» уже есть на этой странице.', 'warn');
      return;
    }
    setChange(() => {
      if (at >= 0) pg.sections[at] = clone(sec);
      else pg.sections.splice(Math.min(from.sections.indexOf(sec), pg.sections.length), 0, clone(sec));
    });
    window.setWindowClose(story);
    if (key !== st.page) setPageOpen(key);
    setPick('sec:' + id, true);
    setToast('Секция ' + getSecName(sec) + ' возвращена из версии от ' + getWhen(one.time, 'short') + '.', true);
  }

  /* ---- Own content: a text in the editor of the panel, a copy of a page, a page from a text, a link to the draft ---- */

  /* A text of a section in the editor of the panel: a frame of fragments/editor-frame.html made on each opening and taken
     by the runtime of editor.js with the kit of the page - the tools, the search, the wrap, the folds, "было → стало",
     the draft of the tab - CodeMirror with the grammar of its language: Markdown as a text, "Свой HTML" as code, as the
     screen "Шаблон" edits a template through Editor::getCode() */
  const edWin = document.querySelector('[data-sb-ed-win]');
  const edForm = edWin.querySelector('[data-sb-ed-form]');
  const ED_ROOT = '../plugins/editors/codemirror/assets/';
  const ED_LANG = {
    markdown: ['Markdown', 'markdown', 'Markdown: подзаголовки, списки, ссылки, картинки и таблицы'],
    html: ['HTML', 'filetype-html', 'Выводится как есть — правят только суперадминистраторы'],
    php: ['PHP', 'filetype-php', 'Файл раскладки — только для чтения'],
  };

  /* The file kit and the preview of a text, as Editor::getFrame() gives a frame with its 'mod': in the system the place
     layout.attach of the upload rules and the file manager of the panel, and the route getEditorPreview rendering the
     text through Parser. On the stand the stand's file window answers the two calls editor.js makes of
     SlaedFileManager - a tile inserts its picture - and getSbMd() answers the POST of the preview */
  const ED_FILES = { place: 'layout.attach' };
  const ED_VIEW = { url: 'sb:preview' };
  const edPorts = {};
  window.SlaedFileManager = window.SlaedFileManager || {
    addUpload: (id, port) => { edPorts[id] = port; },
    addPanel: (id) => setFiles('', 'photos', (path, alt) => {
      edPorts[id].exec('addImage', { imageUrl: '/' + path, altText: alt });
      edPorts[id].focus();
    }),
  };
  const fetchSite = window.fetch.bind(window);
  window.fetch = (url, init) => url === ED_VIEW.url
    ? Promise.resolve(new Response(`<div class="sl-preview sl-preview-admin"><div class="sl-preview-head">Предварительный просмотр</div><div class="sl-preview-body"><div class="sl-preview-section sl-preview-field-primary">${getSbMd(init.body.get('text'))}</div></div></div>`,
      { headers: { 'Content-Type': 'text/html; charset=utf-8' } }))
    : fetchSite(url, init);

  // The markup of a frame of an editor, as Editor::getFrame() prints it with the core and the grammar of CodeMirror; a text
  // gets the file kit and the preview, code gets neither, as getFrame() gives the kit only to a text; a frame to read
  // carries readonly, as getFrame() prints it from 'readonly', and the runtime keeps only the tools that read
  function getEdFrame(name, lang, id, hint, read) {
    const [word, icon] = ED_LANG[lang];
    const code = lang !== 'markdown';
    const kit = code ? '' : ` data-sl-editor-files="${esc(JSON.stringify(ED_FILES))}" data-sl-editor-preview="${esc(JSON.stringify(ED_VIEW))}"`;
    return `<div class="sl-editor" data-sl-editor data-sl-editor-name="${esc(name)}" data-sl-editor-lang="${lang}"${code ? ' data-sl-editor-code' : kit} data-sl-editor-core="${ED_ROOT}core.js" data-sl-editor-grammar="${ED_ROOT}lang-${lang}.js">
      <div class="sl-editor-row" data-sl-editor-row><span class="sl-editor-tab">${ico(icon)}<span title="${esc(name)}">${esc(name)}</span></span></div>
      <div class="sl-editor-card" data-sl-editor-card>
        <textarea class="sl-editor-text${code ? ' sl-editor-code' : ''}" data-sl-editor-area name="${esc(id)}" id="${esc(id)}" rows="18"${code ? ' spellcheck="false"' : ''}${read ? ' readonly' : ''} aria-label="${esc(name)}"${hint ? ` placeholder="${esc(hint)}"` : ''}></textarea>
        <div class="sl-editor-status" data-sl-editor-status><span data-sl-editor-pos></span><span data-sl-editor-size></span><span class="sl-editor-left" data-sl-editor-left></span><span data-sl-editor-used></span><button type="button" class="sl-editor-dirty" data-sl-editor-act="diff" aria-haspopup="dialog" title="Сверка: было → стало">изменено</button><span class="sl-editor-note" data-sl-editor-note aria-live="polite"></span><span class="sl-editor-lang">${word}</span></div>
      </div>
    </div>`;
  }

  function setEditor(path, label, lang) {
    const [word, icon, sub] = ED_LANG[lang];
    const name = label + ' · ' + word;
    edWin.slPath = path;
    edWin.querySelector('[data-sb-ed-cap]').textContent = label;
    edWin.querySelector('[data-sb-ed-sub]').textContent = sub;
    edWin.querySelector('[data-sb-ed-ico]').className = 'bi bi-' + icon;
    edForm.innerHTML = getEdFrame(name, lang, 'sb-ed-' + path.replace(/[^a-z0-9]/gi, '-'));
    // The loaded text is the default of the field, so "было → стало" and the dirty mark measure against it
    edForm.querySelector('textarea').textContent = String(getValue(path) || '');
    window.setWindowOpen(edWin);
    document.dispatchEvent(new CustomEvent('htmx:load', { detail: { elt: edForm } }));
  }

  // Saving submits the form of the window: the runtime keeps the text and drops the draft of the tab, the step is written here
  edForm.addEventListener('submit', (e) => {
    e.preventDefault();
    setValue(edWin.slPath, edForm.querySelector('textarea').value, false);
    window.setWindowClose(edWin);
    setAll();
  });
  edForm.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
      e.preventDefault();
      edForm.requestSubmit();
    }
  });

  /* A copy of a whole page: its settings and sections with ids of their own, a title and an address of its own; the slot
     of a module stays with its page, as a module answers at one address */
  /* A new page under a key of the counter, in the one shape of a page (getSbPage() of sb-data.js), opened and picked;
     a ready set, a text and a copy all end here */
  function setNewPage(title, over, list, word) {
    const addr = getSlug(title);
    const key = newId('page');
    setChange(() => { st.layout.pages[key] = getSbPage(title, addr, over, list); });
    setPageOpen(key);
    setPick('page:' + key, false);
    setToast(word, true);
  }

  /* A page leaves the layout in one step with Undo and the editor goes to the home page; on the site its address answers
     410 once published, as every deleted page does. The home page and the page 404 stay */
  function setPageDel(key) {
    const pg = st.layout.pages[key];
    if (!pg || key === 'home' || key === 'notfound') return;
    setChange(() => {
      delete st.layout.pages[key];
      st.page = 'home';
      st.sel = '';
    });
    setPageOpen('home');
    setToast('Страница «' + pg.title + '» удалена.', true);
  }

  function setPageCopy(key) {
    const from = st.layout.pages[key];
    const { title, sections, ...over } = clone(from);
    delete over.addr;
    const list = sections.filter((s) => s.type !== 'module').map((s) => Object.assign(s, { id: newId('s') }));
    setNewPage('Копия — ' + title, over, list, 'Страница «' + from.title + '» скопирована' + (from.sections.some((s) => s.type === 'module') ? '. Слот модуля остался у оригинала.' : '.'));
  }

  /* A page from a text: "# " is the hero with the paragraph under it, each "## " with its lines is a section "Текст" */
  const fromWin = document.querySelector('[data-sb-from-text]');
  const fromBox = fromWin.querySelector('[data-sb-from-box]');
  const FROM_HINT = ['# Название страницы', '', 'Пара предложений под заголовком.', '', '## Первый раздел', '', 'Текст раздела.', '',
    '## Второй раздел', '', '- пункт', '- пункт'].join('\n');

  function setFromOpen() {
    fromBox.innerHTML = getEdFrame('Текст страницы · Markdown', 'markdown', 'sb-from-text', FROM_HINT);
    window.setWindowOpen(fromWin);
    document.dispatchEvent(new CustomEvent('htmx:load', { detail: { elt: fromBox } }));
  }

  function getFromText(src) {
    const lines = src.replace(/\r/g, '').split('\n');
    const out = { title: '', lead: '', parts: [] };
    let part = null;
    lines.forEach((line) => {
      const h1 = line.match(/^#\s+(.+)/);
      const h2 = line.match(/^##\s+(.+)/);
      if (h1 && !out.title) { out.title = h1[1].trim(); return; }
      if (h2) { part = { title: h2[1].trim(), body: [] }; out.parts.push(part); return; }
      if (part) part.body.push(line);
      else if (out.title && line.trim() && !out.lead) out.lead = line.trim();
    });
    out.parts.forEach((one) => { one.body = one.body.join('\n').trim(); });
    return out;
  }

  fromWin.addEventListener('click', (e) => {
    if (!e.target.closest('[data-sb-from-make]')) return;
    const doc = getFromText(fromBox.querySelector('textarea').value);
    if (!doc.title) {
      setToast('Нужен заголовок страницы: строка «# Название».', 'warn');
      return;
    }
    const list = [getNewSec('hero', { over: '', title: doc.title, accent: '', text: doc.lead, btn: '', btn2: '', pic: false })]
      .concat(doc.parts.map((one) => getNewSec('text', { title: one.title, body: one.body })));
    window.setWindowClose(fromWin);
    setNewPage(doc.title, { desc: doc.lead, band: false }, list, 'Страница «' + doc.title + '» создана из текста: обложка и секций «Текст» — ' + doc.parts.length + '.');
  });

  /* A link to look at the draft: a token with a term, kept in the browser of the stand; in the system a token signed on
     the server, read-only, refused once expired; a new link takes the earlier ones back */
  const linkWin = document.querySelector('[data-sb-link-win]');

  linkWin.addEventListener('click', (e) => {
    if (!e.target.closest('[data-sb-link-make]')) return;
    const days = Number(linkWin.querySelector('[data-sb-link-term]').value);
    const until = Date.now() + days * 86400000;
    const token = Array.from(crypto.getRandomValues(new Uint8Array(12)), (b) => b.toString(16).padStart(2, '0')).join('');
    keep('sb.previews', [{ token: token, until: until }]);
    const url = new URL('sb-site.html?page=' + encodeURIComponent(st.page) + '&preview=' + token, location.href).href;
    linkWin.querySelector('[data-sb-link-url]').textContent = url;
    linkWin.querySelector('[data-sb-link-until]').textContent = 'Действует до ' + getWhen(until);
    linkWin.querySelector('[data-sb-link-box]').hidden = false;
  });

  /* ---- A new page from a ready set ---- */

  function setPresets() {
    presets.querySelector('[data-sb-presets-sub]').textContent = st.layout.shipped ? 'Сайт ещё на раскладке из поставки — начните с готовой страницы' : 'Начните с готового набора секций';
    presets.querySelector('[data-sb-presets-list]').innerHTML = getCard('data-sb-from-open', 'sb-thumb-page', '<i class="sb-mini-hero"></i><i></i><i></i><i></i>', 'file-earmark-text', 'Из текста', 'Markdown-документ: # — обложка, каждый ## — секция «Текст»', false) + SB_PRESETS.map((p) => getCard('data-sb-preset="' + p.key + '"', 'sb-thumb-page',
      (p.list.length ? p.list : [['empty']]).map(([type]) => `<i class="sb-mini-${type}"></i>`).join(''), p.icon, p.title, p.note, false)).join('');
    window.setWindowOpen(presets);
  }

  presets.addEventListener('click', (e) => {
    if (e.target.closest('[data-sb-from-open]')) {
      e.preventDefault();
      window.setWindowClose(presets);
      setFromOpen();
      return;
    }
    const card = e.target.closest('[data-sb-preset]');
    if (!card) return;
    e.preventDefault();
    const p = SB_PRESETS.find((one) => one.key === card.dataset.sbPreset);
    const list = p.list.map(([type, over]) => getNewSec(type, over));
    const title = p.key === 'empty' ? 'Новая страница' : p.title;
    window.setWindowClose(presets);
    // A page that opens with its cover needs no title band over it
    setNewPage(title, { band: !(list[0] && list[0].type === 'hero') }, list, 'Страница «' + title + '» создана' + (list.length ? ', секций: ' + list.length + '.' : '. Добавьте первую секцию.'));
  });

  /* The address of a new page from its title as filterSlug() of the system writes it - one Latin table for the six
     locales, hyphens, cut to 80 at a hyphen - and free; in the system the slug and its suffix come from the server */
  const SLUG = {
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'i', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p',
    р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'kh', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'shch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
    і: 'i', ї: 'yi', є: 'ye', ґ: 'g', ä: 'ae', ö: 'oe', ü: 'ue', ß: 'ss', ą: 'a', ć: 'c', ę: 'e', ł: 'l', ń: 'n', ó: 'o', ś: 's', ź: 'z', ż: 'z',
    à: 'a', â: 'a', æ: 'ae', ç: 'c', é: 'e', è: 'e', ê: 'e', ë: 'e', î: 'i', ï: 'i', ô: 'o', œ: 'oe', ù: 'u', û: 'u', ÿ: 'y', "'": '', '’': '', 'ʼ': '',
  };

  function getSlug(title) {
    let base = title.toLowerCase().split('').map((c) => SLUG[c] ?? c).join('').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    if (base.length > 80) {
      const cut = base.slice(0, 81).lastIndexOf('-');
      base = base.slice(0, cut < 0 ? 80 : cut);
    }
    base = base || 'page';
    const taken = (a) => Object.values(st.layout.pages).some((pg) => pg.addr === a);
    let addr = base;
    for (let n = 2; taken(addr); n++) addr = base + '-' + n;
    return addr;
  }


  /* ---- The command palette of the panel (setPaletteOpen): every action and every place by name, Ctrl+K ---- */

  function setPalette() {
    let n = 0;
    const row = (cmd, arg, icon, name, keys) => `<button type="button" role="option" tabindex="-1" class="sl-editor-pal-row" id="sb-pal-${++n}" data-sb-cmd="${cmd}" data-sb-arg="${esc(arg)}">${ico(icon)}<span class="sl-editor-pal-name">${esc(name)}</span><span class="sl-editor-pal-keys">${keys || ''}</span></button>`;
    const group = (id, cap, rows) => `<div class="sl-editor-pal-group" role="group" aria-labelledby="sb-pal-${id}"><div class="sl-editor-pal-cap" id="sb-pal-${id}">${cap}</div>${rows}</div>`;
    const secs = sections().map((one) => row('pick', 'sec:' + one.id, SB_TYPES[one.type].icon, 'Секция: ' + SB_TYPES[one.type].title + (one.title ? ' — ' + one.title : ''))).join('');
    const parts = Object.entries(SB_PARTS).map(([k, p]) => row('pick', 'part:' + k, p.icon, p.title)).join('') +
      Object.entries(SB_MENUS).map(([k, m]) => row('pick', 'menu:' + k, 'menu-button-wide', m.title)).join('');
    const pages = Object.entries(st.layout.pages).map(([k, pg]) => row('page', k, 'file-earmark-richtext', 'Страница: ' + pg.title)).join('') +
      st.layout.blocks.map((b) => row('pick', 'blk:' + b.id, 'bricks', 'Блок: ' + b.title)).join('');
    pal.querySelector('[data-sb-pal-list]').innerHTML =
      group('acts', 'Действия', row('publish', '', 'cloud-arrow-up', 'Опубликовать') + row('checks', '', 'clipboard-check', 'Проверить перед публикацией') +
        row('story', '', 'clock-history', 'История публикаций') + row('newpage', '', 'file-earmark-plus', 'Новая страница') +
        row('mods', '', 'puzzle', 'Пункты из модулей в меню') + row('data', '', 'gear', 'Настройки: файл раскладки и где ещё') + row('help', '', 'question-circle', 'Руководство') +
        row('preview', '', 'arrows-fullscreen', 'Предпросмотр на весь экран', '<kbd>Ctrl</kbd><kbd>Alt</kbd><kbd>P</kbd>') +
        row('undo', '', 'arrow-counterclockwise', 'Отменить шаг', '<kbd>Ctrl</kbd><kbd>Z</kbd>')) +
      group('add', 'Добавить секцию', Object.entries(SB_TYPES).map(([k, t]) => row('add', k, t.icon, 'Добавить: ' + t.title)).join('')) +
      group('here', 'На этой странице', secs || '') +
      group('site', 'Весь сайт', parts + pages);
    window.setPaletteOpen(pal, setPaletteRun);
  }

  function setPaletteRun(r) {
    const arg = r.dataset.sbArg;
    switch (r.dataset.sbCmd) {
      case 'publish': setChecks(true); break;
      case 'checks': setChecks(false); break;
      case 'story': setStory(); break;
      case 'newpage': setPresets(); break;
      case 'mods': st.tab = 'menu'; setMenuAct('mmods'); setTree(); break;
      case 'data': setData(true); break;
      case 'help': setHelp(); break;
      case 'undo': setUndo(true); break;
      case 'preview': setPreview(true); break;
      case 'add': setAdd(arg, getInsertAt()); break;
      case 'page': setPageOpen(arg); break;
      case 'pick': setPick(arg, true); break;
    }
  }

  /* ---- The layout as a file: JSON out and in, never PHP in - an uploaded PHP file would be code the site runs ---- */

  function setExport() {
    const file = new Blob([JSON.stringify(st.layout, null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(file);
    a.download = 'layout-' + today + '.json';
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    setToast('Файл ' + a.download + ' сохранён.');
  }

  dialog.addEventListener('change', (e) => {
    const input = e.target.closest('[data-sb-import-file]');
    if (!input || !input.files[0]) return;
    input.files[0].text().then((text) => {
      const next = JSON.parse(text);
      const ok = isSbLayout(next) && Object.values(next.pages).every((pg) => Array.isArray(pg.sections) && pg.sections.every((one) => SB_TYPES[one.type]));
      if (!ok) throw new Error('shape');
      const ids = JSON.stringify(next).match(/"id":"[a-z](\d+)"/g) || [];
      next.next = Math.max(next.next || 0, st.layout.next || 0, ...ids.map((m) => Number(m.replace(/\D/g, ''))));
      setLayout(next);
      setData(false);
      setToast('Файл ' + input.files[0].name + ' загружен в черновик.', true);
    }).catch(() => {
      setToast('Это не файл конструктора сайта.', 'warn');
    }).finally(() => { input.value = ''; });
  });


  dialog.addEventListener('click', (e) => {
    if (e.target.closest('[data-sb-export]')) { setExport(); return; }
    if (e.target.closest('[data-sb-import]')) { dialog.querySelector('[data-sb-import-file]').click(); return; }
    if (e.target.closest('[data-sb-reset]')) {
      st.layout = getSbStart();
      st.past = [];
      st.future = [];
      st.page = 'home';
      st.sel = '';
      keep('sb.live', st.layout);
      setPagePick();
      setSaved();
      setAll();
      setData(false);
      setToast('Стенд сброшен к поставке.');
    }
  });

  // The panel of the stand stands where the settings end; it starts folded here, its switches a click away
  addEventListener('DOMContentLoaded', () => {
    const desk = document.querySelector('.demo-panel');
    if (!desk) return;
    desk.dataset.collapsed = '1';
    desk.querySelector('.demo-panel-toggle').textContent = '+';
  });

  // The stand's own switch of the colour scheme plays the visitor's system on the canvas
  new MutationObserver(setCanvasMode).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

  setPagePick();
  app.querySelector('[data-sb-site]').href = 'sb-site.html?page=' + encodeURIComponent(st.page);
  frame.src = 'sb-site.html?edit=1&bare=1&page=' + encodeURIComponent(st.page);
  setView('canvas');
  setDevice('desktop');
  // A double click on "как гость" opens the same view on the whole screen; its first click is taken back, so leaving the
  // preview returns the view it started from
  app.querySelector('[data-sb-guest]').addEventListener('dblclick', () => {
    st.guest = !st.guest;
    setPreview(true);
  });
  const place = getPlace();
  if (place && place.page === st.page) {
    st.tab = place.tab || st.tab;
    if (place.sel && getSelNode(place.sel)) {
      st.sel = place.sel;
    }
  }
  setSaved();
  setTree();
  setInspector();
})();
