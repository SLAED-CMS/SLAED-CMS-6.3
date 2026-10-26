/* The editor of the site builder, on the theme admin: one screen for the pages, the menus and the theme parts of a
   site. Left - the structure (Страница: header, sections, footer; Меню; Тема), middle - the page itself in the canvas
   frame (sb-site.html?edit=1), right - the settings of what is picked. Whatever is picked in one place is picked in
   all three; a text typed on the canvas runs into its field and back.

   The layout is one object (sb-data.js): a draft, kept in the key sb.draft, and the published copy in sb.live, which
   the canvas opened on its own shows. Every change is a step that Ctrl+Z takes back. "Данные" shows the draft as the
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
  const read = (key) => { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch (e) { return null; } };
  const keep = (key, v) => { try { localStorage.setItem(key, JSON.stringify(v)); } catch (e) { /* storage may be off */ } };

  const st = {
    layout: read('sb.draft') || getSbStart(),
    page: params.get('page') || 'home',
    tab: 'page',
    menu: 'main',
    sel: '',
    look: 'content',
    past: [],
    future: [],
    burst: 0,
    ready: false,
    insertAt: -1,
  };
  // A draft from before the columns of blocks takes the blocks of the site from the delivery
  if (!st.layout.blocks) st.layout.blocks = getSbStart().blocks;
  setSbLayoutFit(st.layout);
  if (!read('sb.live')) keep('sb.live', st.layout);
  if (!st.layout.pages[st.page]) st.page = 'home';

  /* ---- The model ---- */

  const page = () => st.layout.pages[st.page];
  const sections = () => page().sections;
  const getSec = (id) => sections().find((s) => s.id === id);
  const newId = (p) => p + (++st.layout.next);

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

  /* A path to the object that holds the value and the key of it */
  function getSlot(path) {
    const part = path.split(':');
    let obj = null;
    let rest = '';
    if (part[0] === 'sec') { obj = getSec(part[1]); rest = part[2]; }
    if (part[0] === 'theme') { obj = st.layout.theme; rest = part[1]; }
    if (part[0] === 'menu') { obj = getMenuItem(part[1], part[2]); rest = part[3]; }
    if (part[0] === 'page') { obj = st.layout.pages[part[1]]; rest = part[2]; }
    if (part[0] === 'blk') { obj = st.layout.blocks.find((b) => b.id === part[1]); rest = part[2]; }
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
    setToast(back ? 'Шаг отменён' : 'Шаг возвращён');
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
    const live = JSON.stringify(read('sb.live'));
    const dirty = live !== JSON.stringify(st.layout);
    const state = app.querySelector('[data-sb-state]');
    state.dataset.dirty = dirty ? '1' : '0';
    state.innerHTML = dirty ? ico('pencil') + 'Черновик' : ico('check2-circle') + 'Опубликовано';
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
      frame.contentWindow.postMessage({ sb: 'render', layout: st.layout, page: st.page, sel: st.sel }, location.origin);
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
      setPick(m.sel, false, true);
    } else if (m.sb === 'text') {
      setValue(m.path, m.value, !m.done);
      const field = insp.querySelector(`[data-sb-field="${CSS.escape(m.path)}"]`);
      if (field && field.value !== m.value) field.value = m.value;
      setTree();
      if (m.done) setCanvas();
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
    if (st.sel && matchMedia('(max-width: 999px)').matches && fromCanvas) setView('edit');
    if (st.sel) app.dataset.sbOpen = '1';
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
      const copy = clone(sec);
      copy.id = newId('s');
      setChange(() => list.splice(idx + 1, 0, copy));
      setPick('sec:' + copy.id, true);
      setToast('Секция «' + name + '» продублирована');
    } else if (act === 'toggle') {
      setChange(() => { sec.on = !sec.on; });
      setToast(sec.on ? 'Секция «' + name + '» снова видна' : 'Секция «' + name + '» скрыта от посетителей', !sec.on);
    } else if (act === 'del') {
      // The pick lets go first: the redraw that follows must not read a section that is gone
      if (st.sel === 'sec:' + id) st.sel = '';
      setChange(() => list.splice(idx, 1));
      setToast('Секция «' + name + '» удалена', true);
    }
  }

  function setAdd(type, at) {
    if (SB_TYPES[type].one && sections().some((one) => one.type === type)) {
      setToast('«' + SB_TYPES[type].title + '» уже есть на этой странице');
      return;
    }
    const sec = Object.assign({ id: newId('s'), type: type, on: true }, SB_TYPES[type].make(), getSbShow());
    const list = sections();
    const to = at < 0 || at > list.length ? list.length : at;
    setChange(() => list.splice(to, 0, sec));
    setLibrary(false);
    setPick('sec:' + sec.id, true);
    setToast('Добавлена секция «' + SB_TYPES[type].title + '»');
  }

  /* ---- The left column ---- */

  function getRow(sel, icon, name, sub, opts) {
    const o = opts || {};
    const cur = st.sel === sel;
    return `<li class="sb-row${o.off ? ' sb-row-off' : ''}"${o.drag ? ` draggable="true" data-sb-drag="${esc(o.drag)}"` : ''}>
      ${o.drag ? '<span class="sl-drag-handle" aria-hidden="true"></span>' : ''}
      <button type="button" class="sb-row-main" data-sb-pick="${esc(sel)}"${cur ? ' aria-current="true"' : ''}>
        <span class="sb-row-ico">${ico(icon)}</span>
        <span class="sb-row-text"><span class="sb-row-name">${esc(name)}</span>${sub ? `<span class="sb-row-sub">${esc(sub)}</span>` : ''}</span>
      </button>
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
        <ul class="sb-rows">${getRow('page:' + st.page, 'file-earmark-richtext', pg.title, 'Адрес: /' + (pg.addr === '/' ? '' : pg.addr))}</ul>
      </div>
      <div class="sb-group">
        <p class="sb-group-head">${ico('window')}Шапка <span class="sb-badge" title="Одна на весь сайт">весь сайт</span></p>
        <ul class="sb-rows">${parts(['social', 'brand', 'pane'])}${getRow('menu:main', 'menu-button-wide', 'Главное меню', countItems('main'))}</ul>
      </div>
      <div class="sb-group sb-group-page">
        <p class="sb-group-head">${ico('layout-text-window')}Содержимое страницы <span class="sb-badge">${pg.sections.length}</span></p>
        <ul class="sb-rows" data-sb-drop="sec">${secs || '<li class="sb-rows-empty">Секций пока нет</li>'}</ul>
        <button type="button" class="sb-add" data-sb-lib-open>${ico('plus-lg')}Добавить секцию</button>
      </div>
      <div class="sb-group">
        <p class="sb-group-head">${ico('layout-sidebar')}Блоки <span class="sb-badge" title="Блоки одни на весь сайт">весь сайт</span></p>
        ${getZonesHtml(pg)}
      </div>
      <div class="sb-group">
        <p class="sb-group-head">${ico('window-dock')}Подвал <span class="sb-badge" title="Один на весь сайт">весь сайт</span></p>
        <ul class="sb-rows">${parts(['demoline', 'contacts'])}${getRow('menu:foot', 'list-ul', 'Нижнее меню', countItems('foot'))}</ul>
      </div>`;
  }

  /* The four zones of the blocks, each a list a block is dragged into, then the free blocks a section or a tag prints. A zone
     the page switches off says so; a block whose where leaves this page is listed faded, with the reason */
  function getZonesHtml(pg) {
    const on = (set, one) => ['', '0', one].includes(String(set ?? '3'));
    const open = { l: on(pg.side, '1'), r: on(pg.side, '2'), c: on(pg.top, '1'), d: on(pg.top, '2') };
    const row = (b, n) => {
      const kind = b.type === 'html' ? ['', 'HTML', 'code-square'] : b.type === 'rss' ? ['', 'RSS', 'rss'] : (SB_BLOCKS.find((x) => x[0] === b.kind) || SB_BLOCKS[0]);
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
      return `<p class="sb-zone-head">${ico(icon)}${title}${open[z] ? '' : '<span class="sb-badge" title="Зона выключена в настройках страницы">выключена</span>'}</p>
        <ul class="sb-rows sb-zone" data-sb-drop="blk" data-sb-zone="${z}">${rows || '<li class="sb-rows-empty">Пусто — перетащите блок сюда</li>'}</ul>`;
    }).join('') + (free ? `<p class="sb-zone-head">${ico('pin-angle')}Свободные — секцией «Блок сайта» или тегом [block=N]</p><ul class="sb-rows">${free}</ul>` : '')
      + `<button type="button" class="sb-add" data-sb-row="badd">${ico('plus-lg')}Добавить блок</button>`;
  }

  function getSecSub(s) {
    const mod = s.type === 'module' ? (SB_MODULES.find((m) => m[0] === s.module) || SB_MODULES[0])[1]
      : s.type === 'block' ? ((st.layout.blocks.find((b) => b.id === s.block) || {}).title || 'блок не выбран') : '';
    const words = s.title || mod || (s.type === 'html' ? 'разметка' : '');
    return words.length > 34 ? words.slice(0, 33) + '…' : words;
  }

  function countItems(menu) {
    let n = 0;
    st.layout.menus[menu].forEach((it) => { n += 1 + it.kids.length; });
    return n + ' ' + (n % 10 === 1 && n % 100 !== 11 ? 'пункт' : n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20) ? 'пункта' : 'пунктов');
  }

  function getMenuTree() {
    const def = SB_MENUS[st.menu];
    const label = (it) => it.href ? (SB_LINKS.find((l) => l[0] === it.href) || [0, it.href])[1] : 'Раскрывающийся пункт';
    const row = (it, depth, last, first) => getRow('menu:' + st.menu + ':' + it.id, it.icon || (depth ? 'arrow-return-right' : 'link-45deg'), it.title, label(it), {
      drag: 'menu:' + st.menu + ':' + it.id,
      tools: (def.depth > 1 ? (depth ? getTool('out', 'arrow-bar-left', 'На уровень выше', it.id) : first ? '' : getTool('in', 'arrow-bar-right', 'Вложить в пункт выше', it.id)) : '') +
        getTool('mdel', 'x-lg', 'Удалить пункт', it.id),
    }).replace('class="sb-row', `class="sb-row sb-depth-${depth}`);
    const list = st.layout.menus[st.menu];
    return `
      <div class="sl-seg sb-menu-pick" role="group" aria-label="Меню">${Object.entries(SB_MENUS).map(([k, m]) =>
        `<button type="button" class="sl-seg-cell${k === st.menu ? ' sl-is-active' : ''}" aria-pressed="${k === st.menu}" data-sb-menu="${k}">${esc(m.title)}</button>`).join('')}</div>
      <div class="sb-group">
        <p class="sb-group-head">${ico('geo')}${esc(def.where)} <span class="sb-badge">${def.depth > 1 ? 'два уровня' : 'один уровень'}</span></p>
        <ul class="sb-rows" data-sb-drop="menu">${list.map((it, i) => row(it, 0, i === list.length - 1, i === 0) +
          it.kids.map((k, j) => row(k, 1, j === it.kids.length - 1, j === 0)).join('')).join('')}</ul>
        <button type="button" class="sb-add" data-sb-row="madd">${ico('plus-lg')}Добавить пункт</button>
        <button type="button" class="sb-add" data-sb-row="mmods">${ico('puzzle')}Пункты из модулей</button>
      </div>
      <p class="sb-hint">${ico('info-circle')}Перетащите пункт, чтобы сменить порядок. Стрелка вправо вкладывает пункт в пункт над ним.</p>`;
  }

  function getThemeTree() {
    const groups = {};
    Object.entries(SB_PARTS).forEach(([k, p]) => { (groups[p.where] = groups[p.where] || []).push(k); });
    return Object.entries(groups).map(([where, keys]) => `
      <div class="sb-group">
        <p class="sb-group-head">${ico('geo')}${esc(where)}</p>
        <ul class="sb-rows">${keys.map((k) => getRow('part:' + k, SB_PARTS[k].icon, SB_PARTS[k].title, '')).join('')}</ul>
      </div>`).join('') + `
      <p class="sb-hint">${ico('palette')}Цвета, шрифты и отступы — токены темы. Здесь меняется то, что сайт говорит, а не то, как тема рисует.</p>`;
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
    const cur = body.querySelector('[aria-current="true"]');
    if (cur) cur.scrollIntoView({ block: 'nearest' });
  }

  /* ---- The library of sections ---- */

  /* The library is a window of the canon: slaed.js opens it, keeps the focus in it and closes it on Esc */
  function setLibrary(open, at) {
    if (!open) {
      if (lib.open) window.setWindowClose(lib);
      return;
    }
    st.insertAt = at === undefined ? (st.sel.startsWith('sec:') ? sections().findIndex((s) => 'sec:' + s.id === st.sel) + 1 : sections().length) : at;
    const after = sections()[st.insertAt - 1];
    lib.querySelector('[data-sb-lib-where]').textContent = after ? 'После «' + SB_TYPES[after.type].title + '»' : 'В начало страницы';
    lib.querySelector('[data-sb-lib-find]').value = '';
    setLibraryList('');
    window.setWindowOpen(lib);
  }

  function setLibraryList(q) {
    const words = q.trim().toLowerCase();
    const groups = {};
    Object.entries(SB_TYPES).forEach(([k, t]) => {
      if (words && !(t.title + ' ' + t.note + ' ' + t.group).toLowerCase().includes(words)) return;
      (groups[t.group] = groups[t.group] || []).push(k);
    });
    const list = lib.querySelector('[data-sb-lib-list]');
    list.innerHTML = Object.keys(groups).length ? Object.entries(groups).map(([g, keys]) => `
      <p class="sb-group-head">${esc(g)}</p>
      <div class="sb-cards">${keys.map((k) => `
        <button type="button" class="sb-card" data-sb-new="${k}"${SB_TYPES[k].one && sections().some((one) => one.type === k) ? ' disabled title="Уже есть на этой странице"' : ''}>
          <span class="sb-thumb sb-thumb-${k}" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
          <span class="sb-card-name">${ico(SB_TYPES[k].icon)}${esc(SB_TYPES[k].title)}</span>
          <span class="sb-card-note">${esc(SB_TYPES[k].note)}</span>
        </button>`).join('')}
      </div>`).join('') : '<p class="sb-hint">Ничего не нашлось.</p>';
  }

  /* ---- The inspector ---- */

  function getField(f, path, value) {
    const id = 'f-' + path.replace(/[^a-z0-9]/gi, '-');
    const lab = `<span class="sb-label" id="${id}-l">${esc(f.label)}</span>`;
    const when = f.when ? ` data-sl-show-when="${f.when[0]}" data-sl-show-value="${f.when[1]}"` : '';
    const row = (inner, labelFor, wide) => `<div class="sb-field${wide ? ' sb-field-wide' : ''}"${when}>${labelFor ? `<label class="sb-label" for="${id}">${esc(f.label)}</label>` : lab}<div class="sb-control">${inner}</div></div>`;
    const data = `data-sb-field="${esc(path)}"`;
    switch (f.kind) {
      case 'text':
        return row(`<input type="text" id="${id}" ${data} value="${esc(value ?? '')}">`, true);
      case 'date':
        return row(`<input type="date" id="${id}" ${data} value="${esc(value ?? '')}">`, true);
      case 'area':
        return row(`<textarea id="${id}" rows="3" ${data}>${esc(value)}</textarea>`, true);
      case 'code':
        return row(`<textarea id="${id}" rows="10" class="sb-code" spellcheck="false" ${data}>${esc(value)}</textarea><p class="sb-note">${ico('shield-exclamation')}Выводится как есть: правят только администраторы.</p>`, true, true);
      case 'number':
        return row(`<input type="number" id="${id}" min="0" ${data} value="${esc(value ?? 0)}">`, true);
      case 'checks': {
        // Where a block shows, as the checkboxes of the panel: modules and types, the pages of the builder, the marks
        const on = [].concat(value || []);
        const box = (v, t) => `<div><label class="sl-radio"><input type="checkbox" value="${esc(v)}" data-sb-check="${esc(path)}"${on.includes(v) ? ' checked' : ''}> ${esc(t)}</label></div>`;
        const pages = Object.entries(st.layout.pages).filter(([, pg]) => !pg.sections.some((s) => s.type === 'module')).map(([k, pg]) => box('page:' + k, 'Страница «' + pg.title + '»'));
        return row(`<p class="sb-check-cap">Модули и типы</p><div class="sl-check-list sb-checks" role="group" aria-labelledby="${id}-l">${SB_MODULES.map(([k, t]) => box(k, t)).join('')}</div>
          <p class="sb-check-cap">Страницы конструктора</p><div class="sl-check-list sb-checks" role="group">${pages.join('')}</div>
          <p class="sb-check-cap">Особые</p><div class="sl-check-list sb-checks" role="group">${SB_WHERE.map(([k, t]) => box(k, t)).join('')}</div>`, false, true);
      }
      case 'blockref': {
        const list = st.layout.blocks.map((b) => [b.id, '№' + b.id.slice(1) + ' — ' + b.title + ((b.which || []).includes('infly') ? ' (свободный)' : '')]);
        return row(`<select id="${id}" class="sl-select" ${data}><option value="">— выберите блок —</option>${list.map(([v, t]) => `<option value="${esc(v)}"${v === value ? ' selected' : ''}>${esc(t)}</option>`).join('')}</select>
          <p class="sb-note">${ico('pin-angle')}Свободный блок выводится только так — секцией или тегом [block=N]; обычный блок можно поставить и сюда.</p>`, true);
      }
      case 'select':
        return row(`<select id="${id}" name="${id}" class="sl-select" ${data}>${f.opts.map(([v, t]) => `<option value="${esc(v)}"${v === value ? ' selected' : ''}>${esc(t)}</option>`).join('')}</select>`, true);
      case 'link': {
        // The own address is a conditional row of slaed.js (data-sl-show-when): shown while the list says "Свой адрес…"
        // The anchors of this page come first: a menu of a one-page site leads to its sections
        const marks = sections().filter((one) => one.anchor).map((one) => ['#' + one.anchor, SB_TYPES[one.type].title + (one.title ? ' · ' + one.title : '')]);
        const own = !!value && !SB_LINKS.some((l) => l[0] === value) && !marks.some((l) => l[0] === value);
        return row(`<select id="${id}" name="${id}" class="sl-select" data-sb-link="${esc(path)}">
          <option value=""${!value ? ' selected' : ''}>— никуда —</option>
          ${marks.length ? `<optgroup label="На этой странице">${marks.map(([v, t]) => `<option value="${esc(v)}"${v === value ? ' selected' : ''}>${esc(v + ' — ' + t)}</option>`).join('')}</optgroup>` : ''}
          <optgroup label="Разделы сайта">${SB_LINKS.map(([v, t]) => `<option value="${esc(v)}"${v === value ? ' selected' : ''}>${esc(t)}</option>`).join('')}</optgroup>
          <option value="__own"${own ? ' selected' : ''}>Свой адрес…</option></select>
          <span class="sb-own" data-sl-show-when="${id}" data-sl-show-value="__own"><input type="text" ${data} value="${own ? esc(value) : ''}" aria-label="Свой адрес" placeholder="https://"></span>`, true);
      }
      case 'range':
        return row(`<span class="sb-range"><input type="range" id="${id}" min="${f.min}" max="${f.max}" ${data} value="${esc(value)}"><output for="${id}">${esc(value)}</output></span>`, true);
      case 'switch':
        return row(`<div class="sl-radio-switch sl-radio-group" role="group" aria-labelledby="${id}-l">
          <label class="sl-radio"><input type="radio" name="${id}" value="1" data-sb-bool="${esc(path)}"${value ? ' checked' : ''}> Да</label><label class="sl-radio"><input type="radio" name="${id}" value="0" data-sb-bool="${esc(path)}"${value ? '' : ' checked'}> Нет</label></div>`);
      case 'multi': {
        // Several values at once: each cell is pressed on its own, at least one stays pressed
        const on = [].concat(value);
        return row(`<span class="sl-seg sb-seg" role="group" aria-labelledby="${id}-l">${f.opts.map(([v, t]) =>
          `<button type="button" class="sl-seg-cell${on.includes(v) ? ' sl-is-active' : ''}" aria-pressed="${on.includes(v)}" data-sb-multi="${esc(path)}" data-sb-val="${esc(v)}">${esc(t)}</button>`).join('')}</span>`);
      }
      case 'seg':
        return row(`<span class="sl-seg sb-seg" role="group" aria-labelledby="${id}-l">${f.opts.map(([v, t, i]) =>
          `<button type="button" class="sl-seg-cell${String(v) === String(value) ? ' sl-is-active' : ''}" aria-pressed="${String(v) === String(value)}" data-sb-set="${esc(path)}" data-sb-val="${esc(v)}" title="${esc(t)}">${i ? ico(i) : ''}${i && f.opts.length > 2 && f.key === 'mode' ? '' : esc(t)}</button>`).join('')}</span>`);
      case 'tone':
        return row(`<span class="sb-tones" role="group" aria-labelledby="${id}-l">${SB_TONES.map(([v, t]) =>
          `<button type="button" class="sb-tone sb-tone-${v}" aria-pressed="${v === value}" data-sb-set="${esc(path)}" data-sb-val="${v}" title="${esc(t)}"><span class="sl-hidden">${esc(t)}</span></button>`).join('')}</span>`);
      case 'icon':
        // The icon picker of the panel: its window of all Bootstrap Icons with the Russian search, as the ranks of groups use it
        return row(`<span class="sl-icon-picker"><span class="sl-ico"><i class="bi bi-${esc(value || 'star')}" data-sl-icon-preview></i></span><input type="text" id="${id}" class="sl-select-config" maxlength="64" ${data} value="${esc(value)}" data-sl-icon-input><button type="button" class="sl-but-green" title="Выбрать иконку" data-sl-icon-open><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>Выбрать</button></span>`, true);
      case 'photo':
        return row(`<span class="sb-photos" role="group" aria-labelledby="${id}-l">${SB_PHOTOS.map(([v, src, t]) =>
          `<button type="button" class="sb-photo" aria-pressed="${v === value}" data-sb-set="${esc(path)}" data-sb-val="${v}" title="${esc(t)}"><img src="${esc(src)}" alt="${esc(t)}" loading="lazy" width="2560" height="1440"></button>`).join('')}</span>`, false, true);
      case 'logo':
        return row(`<span class="sb-logos" role="group" aria-labelledby="${id}-l">${SB_LOGOS.map(([v, t]) =>
          `<button type="button" class="sb-logo" aria-pressed="${v === value}" data-sb-set="${esc(path)}" data-sb-val="${v}" title="${esc(t)}"><img src="../templates/lite/images/logos/${esc(v)}" alt="${esc(t)}"></button>`).join('')}</span>`, false, true);
      default:
        return '';
    }
  }

  function getItems(def, base, list) {
    const it = def;
    return `
      <div class="sb-items">
        <p class="sb-items-head">${esc(it.label)} <span class="sb-badge">${list.length}</span></p>
        <ol class="sb-item-list" data-sb-drop="items" data-sb-base="${esc(base)}">${list.map((one, i) => `
          <li class="sb-item" draggable="true" data-sb-drag="item:${i}">
            <details${st.openItem === base + i ? ' open' : ''} data-sb-item="${esc(base + i)}">
              <summary>
                <span class="sl-drag-handle" aria-hidden="true"></span>
                ${one.icon ? `<span class="sb-item-ico">${ico(one.icon)}</span>` : ''}
                <span class="sb-item-name">${esc(one[it.name] || '—')}</span>
                <button type="button" class="sl-but-mini sl-is-muted sb-row-tool" data-sb-item-del="${esc(base)}" data-sb-n="${i}" title="Удалить" aria-label="Удалить">${ico('x-lg')}</button>
              </summary>
              <div class="sb-form">${it.fields.map((f) => getField(f, base + i + '.' + f.key, one[f.key])).join('')}</div>
            </details>
          </li>`).join('')}
        </ol>
        <button type="button" class="sb-add" data-sb-item-add="${esc(base)}"${list.length >= it.max ? ' disabled' : ''}>${ico('plus-lg')}Добавить ${esc(it.one)}</button>
      </div>`;
  }

  function getInspector() {
    const sel = st.sel;
    const part = sel.split(':');
    if (!sel) {
      return `
        <div class="sb-empty">
          <span class="sb-empty-ico">${ico('cursor')}</span>
          <p class="sb-empty-title">Щёлкните по любой части страницы</p>
          <p>Секция, меню, логотип, контакты — всё, что видит посетитель, выбирается щелчком на странице посередине или в списке слева.
            Двойной щелчок по тексту правит его на месте.</p>
          <ul class="sb-keys">
            <li><kbd>Ctrl</kbd> <kbd>Z</kbd> отменить шаг</li>
            <li><kbd>Alt</kbd> <kbd>↑</kbd> <kbd>↓</kbd> секцию выше и ниже</li>
            <li><kbd>Del</kbd> удалить выбранную секцию</li>
            <li><kbd>Esc</kbd> снять выбор</li>
          </ul>
        </div>`;
    }
    if (part[0] === 'sec') {
      const s = getSec(part[1]);
      const t = SB_TYPES[s.type];
      const base = 'sec:' + s.id + ':';
      const form = (tab) => `<div class="sb-form">${t.fields.filter((f) => (f.tab || 'content') === tab).map((f) => getField(f, base + f.key, s[f.key])).join('')}</div>`;
      const items = t.items ? getItems(t.items, base + 'items.', s.items) : '';
      // "Содержимое", "Вид" and "Показ" are the tabs of the panel (setTabs of slaed.js), which keep the chosen tab for the next pick
      const show = Object.assign(getSbShow(), s);
      const tabs = [['content', 'type', 'Содержимое', form('content') + items]];
      if (t.fields.some((f) => f.tab === 'look')) tabs.push(['view', 'sliders', 'Вид', form('look')]);
      tabs.push(['show', 'eye', 'Показ', `<div class="sb-form">${SB_SHOW.map((f) => getField(f, base + f.key, show[f.key])).join('')}</div>
            <p class="sb-hint">${ico('info-circle')}«Кому» и даты — те же, что у блоков сайта. Якорь даёт секции адрес на странице: пункт меню «#${esc(show.anchor || 'якорь')}» ведёт к ней.</p>`]);
      const look = `
          <ul class="sl-tabs-nav sb-look">${tabs.map(([k, i, title], n) => `
            <li class="sl-tabs-item"><a href="#" class="sl-tabs-link${n ? '' : ' sl-is-active'}" data-sl-tab-link data-sl-tab-target="sb-look-${k}">${ico(i)}${title}</a></li>`).join('')}
          </ul>${tabs.map(([k, , , html], n) => `
          <div class="sl-tab-panel" data-sl-tab-panel="sb-look-${k}"${n ? ' hidden' : ''}>${html}</div>`).join('')}`;
      return getHead(t.icon, t.title, 'Секция страницы «' + page().title + '»') + `
        <div class="sb-scroll" data-sl-tabs-init="sb-look">${look}</div>
        <div class="sb-foot">
          <button type="button" class="sl-but" data-sb-sec="dup">${ico('copy')}Дублировать</button>
          <button type="button" class="sl-but" data-sb-sec="toggle">${ico(s.on ? 'eye-slash' : 'eye')}${s.on ? 'Скрыть' : 'Показать'}</button>
          <button type="button" class="sl-but-red" data-sb-sec="del">${ico('trash3')}Удалить</button>
        </div>`;
    }
    if (part[0] === 'part') {
      const p = SB_PARTS[part[1]];
      return getHead(p.icon, p.title, p.where + ' · весь сайт') + `
        <div class="sb-scroll">
          <div class="sb-form">${p.fields.map((f) => getField(f, 'theme:' + f.key, st.layout.theme[f.key])).join('')}</div>
          ${p.items ? getItems(p.items, 'theme:' + p.items.key + '.', st.layout.theme[p.items.key]) : ''}
        </div>`;
    }
    if (part[0] === 'menu' && !part[2]) {
      const m = SB_MENUS[part[1]];
      return getHead(part[1] === 'main' ? 'menu-button-wide' : 'list-ul', m.title, m.where + ' · весь сайт') + `
        <div class="sb-scroll"><p class="sb-hint">${ico('arrow-left')}Пункты меню — слева, во вкладке «Меню». Щелчок по пункту на странице открывает его здесь.</p>
        <button type="button" class="sb-add" data-sb-row="madd">${ico('plus-lg')}Добавить пункт</button></div>`;
    }
    if (part[0] === 'menu') {
      const it = getMenuItem(part[1], part[2]);
      const m = SB_MENUS[part[1]];
      const base = 'menu:' + part[1] + ':' + it.id + ':';
      const fields = [{ key: 'title', label: 'Название', kind: 'text' }, { key: 'href', label: 'Ведёт на', kind: 'link' }];
      if (m.icons) fields.push({ key: 'icon', label: 'Иконка', kind: 'icon' });
      fields.push({ key: 'blank', label: 'Новое окно', kind: 'switch' });
      return getHead('link-45deg', 'Пункт меню', m.title) + `
        <div class="sb-scroll"><div class="sb-form">${fields.map((f) => getField(f, base + f.key, it[f.key])).join('')}</div>
        ${it.kids && it.kids.length ? `<p class="sb-hint">${ico('diagram-2')}Вложенных пунктов: ${it.kids.length}. Без адреса пункт только раскрывает их.</p>` : ''}</div>
        <div class="sb-foot"><button type="button" class="sl-but-red" data-sb-row="mdel" data-sb-id="${esc(it.id)}">${ico('trash3')}Удалить пункт</button></div>`;
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
      const icon = b.type === 'html' ? 'code-square' : b.type === 'rss' ? 'rss' : (SB_BLOCKS.find((one) => one[0] === b.kind) || SB_BLOCKS[0])[2];
      return getHead(icon, 'Блок сайта', (b.which || []).includes('infly') ? 'Свободный блок №' + b.id.slice(1) : 'Блоки · весь сайт') + `
        <div class="sb-scroll"><div class="sb-form">${fields.map((f) => getField(f, base + f.key, b[f.key])).join('')}</div>
        <p class="sb-hint">${ico('info-circle')}Это строка «Блоков и баннеров»: позиция, порядок, «Где показывать», срок, язык и «Кому» — те же поля, что в панели. «Срок, дней» — 0 без ограничения.</p></div>
        <div class="sb-foot"><button type="button" class="sl-but-red" data-sb-row="bdel" data-sb-id="${esc(b.id)}">${ico('trash3')}Удалить блок</button></div>`;
    }
    if (part[0] === 'page') {
      const pg = st.layout.pages[part[1]];
      return getHead('file-earmark-richtext', 'Страница', 'Секций: ' + pg.sections.length) + `
        <div class="sb-scroll"><div class="sb-form">
          ${getField({ key: 'title', label: 'Заголовок', kind: 'text' }, 'page:' + part[1] + ':title', pg.title)}
          ${getField({ key: 'addr', label: 'Адрес', kind: 'text' }, 'page:' + part[1] + ':addr', pg.addr)}
          ${getField({ key: 'side', label: 'Боковые блоки', kind: 'select', opts: SB_SIDE }, 'page:' + part[1] + ':side', pg.side)}
          ${getField({ key: 'top', label: 'Центральные блоки', kind: 'select', opts: SB_TOP }, 'page:' + part[1] + ':top', pg.top)}
        </div>
        <p class="sb-hint">${ico('signpost-2')}По этому адресу страница открывается на сайте, как любая другая.</p>
        <p class="sb-hint">${ico('layout-sidebar')}${pg.sections.some((s) => s.type === 'module') ? 'На странице модуля боковые и центральные блоки — это его «Отображение блоков» в «Модулях»: поле одно на все его страницы.' : 'Боковые и центральные блоки — те же два поля, что «Отображение блоков» у модуля в «Модулях».'}</p></div>`;
    }
    return '';
  }

  function getHead(icon, title, sub) {
    return `<div class="sb-insp-head"><span class="sb-insp-ico">${ico(icon)}</span><span class="sb-insp-text"><span class="sb-insp-title">${esc(title)}</span><span class="sb-insp-sub">${esc(sub)}</span></span>
      <button type="button" class="sl-but-mini sl-is-muted" data-sb-unpick title="Закрыть" aria-label="Закрыть">${ico('x-lg')}</button></div>`;
  }

  /* The inspector is redrawn as a swap: the event slaed.js answers wires its tabs and conditional rows, exactly as
     after an htmx answer, so the transfer can render it on the server and swap it with htmx unchanged */
  function setInspector() {
    const scroll = insp.querySelector('.sb-scroll');
    const top = scroll ? scroll.scrollTop : 0;
    insp.innerHTML = getInspector();
    insp.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: { target: insp } }));
    const next = insp.querySelector('.sb-scroll');
    if (next) next.scrollTop = top;
    app.dataset.sbPicked = st.sel ? '1' : '0';
  }

  function setAll() {
    setTree();
    setInspector();
    setCanvas();
  }

  /* The one notice of the panel; a step that removes or hides offers Undo */
  function setToast(text, undo) {
    window.setToast(text, undo ? { undo: () => setUndo(true) } : {});
  }

  /* ---- The views of a narrow screen and the device of the canvas ---- */

  function setView(view) {
    app.dataset.view = view;
    app.querySelectorAll('[data-sb-view]').forEach((b) => {
      const on = b.dataset.sbView === view;
      b.classList.toggle('sl-is-active', on);
      b.setAttribute('aria-pressed', String(on));
    });
  }

  function setDevice(dev) {
    app.querySelector('[data-sb-frame]').dataset.device = dev;
    app.querySelectorAll('[data-sb-device]').forEach((b) => {
      const on = b.dataset.sbDevice === dev;
      b.classList.toggle('sl-is-active', on);
      b.setAttribute('aria-pressed', String(on));
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
    const narrow = matchMedia('(max-width: 999px)').matches;
    const vw = narrow ? w : DEVICE[box.dataset.device];
    const scale = Math.min(1, w / vw);
    box.style.setProperty('--sb-vw', vw + 'px');
    box.style.setProperty('--sb-vh', (h / scale) + 'px');
    box.style.setProperty('--sb-scale', String(scale));
    box.dataset.scaled = scale < 1 ? '1' : '0';
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

  function setData(open) {
    if (!open) {
      if (dialog.open) window.setWindowClose(dialog);
      return;
    }
    const file = Object.assign({}, st.layout);
    delete file.next;
    const php = "<?php\n# Site layout: theme parts, menus and pages, written by the site builder\n\nreturn " + getPhp(file) + ';\n';
    dialog.querySelector('[data-sb-php]').textContent = php;
    const bytes = new Blob([php]).size;
    const secs = Object.values(st.layout.pages).reduce((n, pg) => n + pg.sections.length, 0);
    dialog.querySelector('[data-sb-php-meta]').textContent = (bytes / 1024).toFixed(1).replace('.', ',') + ' КБ · страниц: ' +
      Object.keys(st.layout.pages).length + ' · секций: ' + secs + ' · запросов к базе: 0';
    window.setWindowOpen(dialog);
  }

  /* ---- Events ---- */

  app.addEventListener('click', (e) => {
    const t = e.target;
    const pick = t.closest('[data-sb-pick]');
    if (pick) { setPick(pick.dataset.sbPick, true); return; }
    const tab = t.closest('[data-sb-tab]');
    if (tab) { st.tab = tab.dataset.sbTab; setLibrary(false); setTree(); return; }
    const menu = t.closest('[data-sb-menu]');
    if (menu) { st.menu = menu.dataset.sbMenu; setPick('menu:' + st.menu, true); return; }
    const multi = t.closest('[data-sb-multi]');
    if (multi) {
      const now = [].concat(getValue(multi.dataset.sbMulti));
      const val = multi.dataset.sbVal;
      const next = now.includes(val) ? now.filter((v) => v !== val) : now.concat(val);
      if (!next.length) {
        window.setToast('Нужен хотя бы один модуль', { warn: true });
        return;
      }
      setValue(multi.dataset.sbMulti, next, false);
      setAll();
      return;
    }
    const set = t.closest('[data-sb-set]');
    if (set) {
      setValue(set.dataset.sbSet, set.dataset.sbVal, false);
      setAll();
      return;
    }
    if (t.closest('[data-sb-unpick]')) { setPick(''); app.dataset.sbOpen = '0'; return; }
    if (t.closest('[data-sb-lib-open]')) { setLibrary(true); return; }
    const card = t.closest('[data-sb-new]');
    if (card) { setAdd(card.dataset.sbNew, st.insertAt); return; }
    const sec = t.closest('[data-sb-sec]');
    if (sec) { setAct(sec.dataset.sbSec, st.sel.slice(4)); return; }
    const del = t.closest('[data-sb-item-del]');
    if (del) {
      e.preventDefault();
      const slot = getSlot(del.dataset.sbItemDel.replace(/\.$/, ''));
      setChange(() => slot.obj[slot.key].splice(Number(del.dataset.sbN), 1));
      setToast('Удалено', true);
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
      st.openItem = base + (slot.obj[slot.key].length - 1);
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
    setToast('Блок «' + b.title + '» удалён', true);
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
        window.setToast('Все модули уже есть в меню', { warn: true });
        return;
      }
      setChange(() => add.forEach(([key, title, icon]) => list.push({ id: newId('m'), title: title, href: key, icon: SB_MENUS[menu].icons ? icon : '', blank: false, kids: [] })));
      setToast('Добавлено пунктов: ' + add.length + ' — ' + add.map((m) => m[1]).join(', '), true);
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
      setToast('Пункт «' + it.title + '» удалён' + (it.kids.length ? ' вместе с вложенными' : ''), true);
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
      const name = item.querySelector('.sb-item-name');
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
    if (path.startsWith('theme:social')) return SB_PARTS.social.items.name;
    return '';
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
        const own = t.closest('.sb-control').querySelector('.sb-own input');
        if (own) own.focus();
        return;
      }
      setValue(t.dataset.sbLink, t.value, false);
      setTree();
      setCanvas();
    } else if (t.dataset.sbCheck) {
      const list = [...t.closest('.sb-control').querySelectorAll('[data-sb-check]:checked')].map((c) => c.value);
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

  app.addEventListener('toggle', (e) => {
    const d = e.target;
    if (d.matches && d.matches('[data-sb-item]')) st.openItem = d.open ? d.dataset.sbItem : (st.openItem === d.dataset.sbItem ? '' : st.openItem);
  }, true);

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
    if ((e.ctrlKey || e.metaKey) && e.code === 'KeyK' && !pal.open) {
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
    } else if (e.key === 'Escape') {
      // An open window answers its own Esc (slaed.js); only a bare editor lets go of the pick
      if (dialog.open || lib.open || document.querySelector('dialog[open]')) return;
      if (st.sel) setPick('');
    } else if (!typing && st.sel.startsWith('sec:') && e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) {
      e.preventDefault();
      setAct(e.key === 'ArrowUp' ? 'up' : 'down', st.sel.slice(4));
    } else if (!typing && st.sel.startsWith('sec:') && (e.key === 'Delete')) {
      setAct('del', st.sel.slice(4));
    }
  });

  /* ---- Checks before publishing: what a visitor would trip over, each with the way to its place ---- */

  const today = new Date().toISOString().slice(0, 10);
  const isLink = (v) => /^(https?:\/\/|mailto:|#)/.test(v) || SB_LINKS.some((l) => l[0] === v);

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
      });
      const twin = Object.entries(st.layout.pages).find(([k, other]) => k !== key && other.addr === pg.addr);
      if (twin && key < twin[0]) add(key, 'page:' + key, 'signpost-2', 'Адрес /' + pg.addr + ' занят страницей «' + twin[1].title + '»', pg.title);
    });
    st.layout.blocks.forEach((b) => {
      if (b.type === 'html' && !String(b.content).trim()) add('', 'blk:' + b.id, 'code-square', 'Блок «' + b.title + '» без содержания', 'Блоки');
      if (b.type === 'rss' && !/^https?:\/\//.test(b.url)) add('', 'blk:' + b.id, 'rss', 'У блока «' + b.title + '» нет адреса ленты', 'Блоки');
    });
    const th = st.layout.theme;
    if (th.slogan.replace(/\n/g, ' ').length > 40) add('', 'part:brand', 'badge-tm', 'Слоган длиннее 40 знаков — в шапке он перенесётся', 'Шапка');
    th.social.forEach((n) => { if (!/^https?:\/\//.test(n.url)) add('', 'part:social', 'share', 'У «' + n.title + '» нет адреса', 'Верхняя строка'); });
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

  /* The publish button opens the checks first; nothing to say - it publishes at once */
  function setChecks(publishing) {
    const list = getIssues();
    if (publishing && !list.length) {
      setPublish();
      return;
    }
    checks.slIssues = list;
    checks.querySelector('[data-sb-checks-sub]').textContent = list.length ? 'Замечаний: ' + list.length : 'Замечаний нет';
    checks.querySelector('[data-sb-checks-list]').innerHTML = list.length ? list.map((one, i) => `
      <li><button type="button" class="sb-issue" data-sb-issue="${i}">${ico(one.icon)}<span class="sb-issue-text">${esc(one.text)}</span><span class="sb-issue-where">${esc(one.where)}</span>${ico('chevron-right')}</button></li>`).join('')
      : `<li class="sb-hint">${ico('check2-circle')}Всё в порядке: ссылки ведут куда нужно, заголовки на месте.</li>`;
    checks.querySelector('[data-sb-publish-now]').hidden = !publishing;
    window.setWindowOpen(checks);
  }

  function setPublish() {
    keep('sb.live', st.layout);
    const story = read('sb.history') || [];
    const secs = Object.values(st.layout.pages).reduce((n, pg) => n + pg.sections.length, 0);
    story.unshift({ time: Date.now(), note: 'Страниц: ' + Object.keys(st.layout.pages).length + ' · секций: ' + secs, layout: clone(st.layout) });
    keep('sb.history', story.slice(0, 10));
    setSaved();
    setToast('Опубликовано. Прежняя версия сохранена в истории');
  }

  checks.addEventListener('click', (e) => {
    const one = e.target.closest('[data-sb-issue]');
    if (one) {
      const it = checks.slIssues[Number(one.dataset.sbIssue)];
      window.setWindowClose(checks);
      if (it.page && it.page !== st.page) setPageOpen(it.page);
      setPick(it.sel, true);
      return;
    }
    if (e.target.closest('[data-sb-publish-now]')) {
      window.setWindowClose(checks);
      setPublish();
    }
  });

  /* ---- History of publications: the last ten copies of the file, each can come back as the draft ---- */

  function setStory() {
    const list = read('sb.history') || [];
    const live = JSON.stringify(read('sb.live'));
    story.slList = list;
    story.querySelector('[data-sb-story-list]').innerHTML = list.length ? list.map((one, i) => `
      <li class="sb-story-row">
        <span class="sb-story-ico">${ico(i ? 'clock-history' : 'cloud-check')}</span>
        <span class="sb-story-text"><b>${esc(new Date(one.time).toLocaleString('ru-RU', { dateStyle: 'medium', timeStyle: 'short' }))}</b>
          <span>${esc(one.note)}${JSON.stringify(one.layout) === live ? ' · сейчас на сайте' : ''}</span></span>
        <button type="button" class="sl-but" data-sb-restore="${i}">${ico('arrow-counterclockwise')}Вернуть в черновик</button>
      </li>`).join('')
      : `<li class="sb-hint">${ico('info-circle')}Публикаций пока нет. Здесь хранятся десять последних.</li>`;
    window.setWindowOpen(story);
  }

  story.addEventListener('click', (e) => {
    const back = e.target.closest('[data-sb-restore]');
    if (!back) return;
    const one = story.slList[Number(back.dataset.sbRestore)];
    setStep(false);
    st.layout = setSbLayoutFit(clone(one.layout));
    if (!st.layout.pages[st.page]) st.page = 'home';
    st.sel = '';
    window.setWindowClose(story);
    setPagePick();
    setSaved();
    setAll();
    setToast('Версия от ' + new Date(one.time).toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'short' }) + ' — в черновике; опубликуйте, чтобы вернуть её на сайт', true);
  });

  /* ---- A new page from a ready set ---- */

  function setPresets() {
    presets.querySelector('[data-sb-presets-list]').innerHTML = SB_PRESETS.map((p) => `
      <button type="button" class="sb-card" data-sb-preset="${p.key}">
        <span class="sb-thumb sb-thumb-page" aria-hidden="true">${(p.list.length ? p.list : [['empty']]).map(([type]) => `<i class="sb-mini-${type}"></i>`).join('')}</span>
        <span class="sb-card-name">${ico(p.icon)}${esc(p.title)}</span>
        <span class="sb-card-note">${esc(p.note)}</span>
      </button>`).join('');
    window.setWindowOpen(presets);
  }

  presets.addEventListener('click', (e) => {
    const card = e.target.closest('[data-sb-preset]');
    if (!card) return;
    const p = SB_PRESETS.find((one) => one.key === card.dataset.sbPreset);
    const key = 'page' + (++st.layout.next);
    const list = p.list.map(([type, over]) => Object.assign({ id: newId('s'), type: type, on: true }, SB_TYPES[type].make(), getSbShow(), over || {}));
    const title = p.key === 'empty' ? 'Новая страница' : p.title;
    setChange(() => { st.layout.pages[key] = { title: title, addr: getSlug(title), sections: list }; });
    window.setWindowClose(presets);
    setPageOpen(key);
    setPick('page:' + key, false);
    setToast('Страница «' + st.layout.pages[key].title + '» создана' + (list.length ? ', секций: ' + list.length : ': добавьте первую секцию'));
  });

  /* The address of a new page from its title, in Latin letters as the readable addresses of the site, and free */
  function getSlug(title) {
    const map = { а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'i', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p',
      р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'kh', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'shch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'iu', я: 'ia' };
    const base = title.toLowerCase().split('').map((c) => map[c] ?? c).join('').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'page';
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
        row('mods', '', 'puzzle', 'Пункты из модулей в меню') + row('data', '', 'filetype-php', 'Данные: config/layout.php') +
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
      case 'undo': setUndo(true); break;
      case 'add': setAdd(arg, st.sel.startsWith('sec:') ? sections().findIndex((one) => 'sec:' + one.id === st.sel) + 1 : sections().length); break;
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
    setToast('Файл ' + a.download + ' сохранён');
  }

  dialog.addEventListener('change', (e) => {
    const input = e.target.closest('[data-sb-import-file]');
    if (!input || !input.files[0]) return;
    input.files[0].text().then((text) => {
      const next = JSON.parse(text);
      const ok = next && next.theme && next.menus && next.pages && Object.values(next.pages).every((pg) => Array.isArray(pg.sections) && pg.sections.every((one) => SB_TYPES[one.type]));
      if (!ok) throw new Error('shape');
      const ids = JSON.stringify(next).match(/"id":"[a-z](\d+)"/g) || [];
      next.next = Math.max(next.next || 0, ...ids.map((m) => Number(m.replace(/\D/g, ''))));
      setStep(false);
      st.layout = setSbLayoutFit(next);
      if (!st.layout.pages[st.page]) st.page = 'home';
      st.sel = '';
      setData(false);
      setPagePick();
      setSaved();
      setAll();
      setToast('Файл ' + input.files[0].name + ' загружен в черновик', true);
    }).catch(() => {
      window.setToast('Это не файл конструктора сайта', { warn: true });
    }).finally(() => { input.value = ''; });
  });


  dialog.addEventListener('click', (e) => {
    if (e.target.closest('[data-sb-export]')) { setExport(); return; }
    if (e.target.closest('[data-sb-import]')) { dialog.querySelector('[data-sb-import-file]').click(); return; }
    if (e.target.closest('[data-sb-copy]')) {
      navigator.clipboard.writeText(dialog.querySelector('[data-sb-php]').textContent).then(() => setToast('Скопировано'));
    }
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
      setToast('Стенд сброшен к поставке');
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
  setSaved();
  setTree();
  setInspector();
})();
