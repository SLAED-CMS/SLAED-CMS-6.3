/* The screen of the site builder (admin/modules/layout.php): picks fetch the inspector, every act posts to the draft and the answer swaps the tree,
   the inspector and the state out of band, then raises sl-layout-saved, on which the frame of the page reloads where it stood; the page in the frame
   runs plugins/system/builder.js, whose messages pick, type, move and act as the screen does */
(function () {
    var app = document.querySelector('[data-sl-lay-app]');
    if (!app || !window.htmx) return;
    var frame = app.querySelector('[data-sl-lay-frame] iframe');
    var lib = document.getElementById('sl-lay-lib');
    var sets = document.getElementById('sl-lay-sets');
    var pal = document.getElementById('sl-lay-pal');
    var story = document.getElementById('sl-lay-story');
    var ready = document.getElementById('sl-lay-ready');
    var checks = document.getElementById('sl-lay-checks');
    var dest = document.getElementById('sl-lay-dest');
    var edit = document.getElementById('sl-lay-edit');
    var link = document.getElementById('sl-lay-link');
    var help = document.getElementById('sl-lay-help');
    var bar = app.querySelector('[data-sl-lay-preview]');
    // The widths of the devices of the top bar: the page is drawn at that width and scaled down to the stage, so a computer shows the desktop layout of the theme
    var devices = { desk: 1366, tablet: 820, phone: 390 };
    var state = { page: app.getAttribute('data-sl-lay-page'), sel: app.getAttribute('data-sl-lay-sel'), tab: app.getAttribute('data-sl-lay-tab'), dev: 'desk', guest: false };
    var scale = 1;

    // One key of the storage of this tab of the browser, or of the browser for the recent types; a storage that is off reads as empty
    function getKept(key, local) {
        try { return JSON.parse((local ? window.localStorage : window.sessionStorage).getItem(key) || 'null'); } catch (e) { return null; }
    }

    function setKept(key, value, local) {
        try { (local ? window.localStorage : window.sessionStorage).setItem(key, JSON.stringify(value)); } catch (e) { return; }
    }

    // The place of the work in this tab of the browser: the pick, the tab of the tree, the device and the guest view come back after a reload of the same page
    function setPlace() {
        setKept('sl-lay-place', state);
    }

    // Post one act of the screen with the pick it acts on and any values of its window; the answer swaps out of band and raises its events on the screen
    function setAct(act, id, arg, sel, more) {
        var values = {
            token: app.getAttribute('data-sl-lay-token'), page: state.page, sel: sel === undefined ? state.sel : sel, tab: state.tab, act: act, id: id || '', arg: arg || ''
        };
        Object.keys(more || {}).forEach(function (key) { values[key] = more[key]; });
        window.htmx.ajax('POST', app.getAttribute('data-sl-lay-post'), { source: app, swap: 'none', values: values });
    }

    // Pick a part: the tree marks its row, the inspector comes from the server, the page in the frame rings it unless the pick came from there
    function setPick(sel, still) {
        state.sel = sel;
        app.querySelectorAll('#sl-lay-tree a[aria-current]').forEach(function (one) { one.removeAttribute('aria-current'); });
        var row = sel ? app.querySelector('#sl-lay-tree a[data-sl-lay-pick="' + sel + '"]') : null;
        if (row) row.setAttribute('aria-current', 'page');
        var url = app.getAttribute('data-sl-lay-get-insp') + '&page=' + encodeURIComponent(state.page) + '&sel=' + encodeURIComponent(sel);
        window.htmx.ajax('GET', url, { target: '#sl-lay-insp', swap: 'outerHTML' });
        if (!still) setFramePost({ sl: 'pick', sel: sel, scroll: true });
        setPlace();
    }

    // Tell the page in the frame, whose builder.js listens for the screen of its own site only
    function setFramePost(msg) {
        try { frame.contentWindow.postMessage(msg, window.location.origin); } catch (e) { return; }
    }

    // The tab of the tree that lists a pick: a part of the theme, a menu, or the page with its sections and blocks
    function getPickTab(sel) {
        if (sel.indexOf('part:') === 0) return 'theme';
        return sel.indexOf('menu:') === 0 ? 'menu' : 'page';
    }

    // Show the tree of a tab
    function setTab(tab) {
        state.tab = tab;
        var url = app.getAttribute('data-sl-lay-get-tree') + '&page=' + encodeURIComponent(state.page) + '&tab=' + tab + '&sel=' + encodeURIComponent(state.sel);
        window.htmx.ajax('GET', url, { target: '#sl-lay-tree', swap: 'outerHTML' });
        setPlace();
    }

    // The address of the screen on a page with its pick: another page of the draft is another load of the screen
    function getPageUrl(page, sel) {
        return app.getAttribute('data-sl-lay-get-tree').replace('&op=tree', '') + '&page=' + encodeURIComponent(page) + '&sel=' + encodeURIComponent(sel);
    }

    // Lead to a part named by a row of a window: on its page, in the tab of the tree that lists it
    function setGo(sel, page) {
        if (!sel) return;
        var tab = sel.indexOf('menu:') === 0 ? 'menu' : 'page';
        if (page && page !== '0' && page !== String(state.page)) {
            setKept('sl-lay-place', { page: page, sel: sel, tab: tab, dev: state.dev, guest: state.guest });
            return window.location.assign(getPageUrl(page, sel));
        }
        setPick(sel);
        if (tab !== state.tab) setTab(tab);
    }

    // The device of the frame: its width, the pressed cell of the top bar
    function setDevice(dev) {
        if (!(dev in devices)) dev = 'desk';
        state.dev = dev;
        app.querySelectorAll('[data-sl-lay-device]').forEach(function (one) {
            var on = one.getAttribute('data-sl-lay-device') === dev;
            one.classList.toggle('sl-is-active', on);
            one.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        setFit();
        setPlace();
    }

    /* The page at the width of its device, scaled down to the stage and as tall as the stage at that scale; a narrow window stacks the columns,
       and there the page takes the width of the stage up to its device. The page is told the scale, so its rings and toolbar keep their size */
    function setFit() {
        var box = app.querySelector('[data-sl-lay-frame]');
        var css = window.getComputedStyle(box.parentElement);
        var wide = box.parentElement.clientWidth - parseFloat(css.paddingLeft) - parseFloat(css.paddingRight);
        var high = box.parentElement.clientHeight - parseFloat(css.paddingTop) - parseFloat(css.paddingBottom);
        var width = devices[state.dev];
        if (window.matchMedia('(max-width: 900px)').matches) width = Math.min(wide, width);
        var fit = Math.min(1, wide / width);
        box.style.setProperty('--sl-d-device', width + 'px');
        box.style.setProperty('--sl-d-device-h', (high / fit) + 'px');
        box.style.setProperty('--sl-d-scale', String(fit));
        if (fit === scale) return;
        scale = fit;
        setFramePost({ sl: 'scale', scale: scale });
    }

    // Reload the page of the draft in its frame and put it back where it was scrolled; the guest view asks the page as a visitor without a session sees it
    function setFrame() {
        var top = 0;
        var url = new URL(frame.getAttribute('src'), window.location.href);
        var guest = state.guest || app.hasAttribute('data-sl-lay-full');
        try { top = frame.contentWindow.scrollY; } catch (e) { top = 0; }
        frame.addEventListener('load', function back() {
            frame.removeEventListener('load', back);
            try { frame.contentWindow.scrollTo(0, top); } catch (e) { return; }
        });
        if ((url.searchParams.get('guest') === '1') !== guest) {
            if (guest) url.searchParams.set('guest', '1');
            else url.searchParams.delete('guest');
            frame.setAttribute('src', url.pathname + url.search);
            return;
        }
        try { frame.contentWindow.location.reload(); } catch (e) { frame.setAttribute('src', frame.getAttribute('src')); }
    }

    // The guest view of the frame: the sections and menus a visitor without a session sees; the blocks keep the session of the editor
    function setGuest(on) {
        state.guest = on;
        app.querySelectorAll('[data-sl-lay-guest]').forEach(function (one) { one.setAttribute('aria-pressed', on ? 'true' : 'false'); });
        setPlace();
        setFrame();
    }

    /* The full preview: the panels step aside under a thin bar of the way back and the page shows as a guest gets it, nothing is picked; Esc on the
       page or on the screen, or the button of the bar, returns */
    function setPreview(on) {
        if (on === app.hasAttribute('data-sl-lay-full')) return;
        app.toggleAttribute('data-sl-lay-full', on);
        bar.hidden = !on;
        if (on) {
            if (state.sel) setPick('');
            bar.querySelector('button').focus();
        }
        setFrame();
    }

    // The keys pressed in the page of the frame are the keys of the screen; the frame is of this site, so its window is reachable
    frame.addEventListener('load', function () {
        try { frame.contentWindow.addEventListener('keydown', setKey, true); } catch (e) { return; }
    });

    // The names of the rings and the words of the toolbar for the page in the frame, with the pick it shows and the scale it is drawn at; the names come with every write in the state
    function setFrameInit() {
        var mark = document.getElementById('sl-lay-state');
        var names = {};
        var words = {};
        try { names = JSON.parse(mark ? mark.getAttribute('data-sl-lay-names') || '{}' : '{}'); } catch (e) { names = {}; }
        try { words = JSON.parse(app.getAttribute('data-sl-lay-words') || '{}'); } catch (e) { words = {}; }
        setFramePost({ sl: 'init', sel: state.sel, names: names, words: words });
        setFramePost({ sl: 'scale', scale: scale });
    }

    // A pick made on the page: the inspector comes, and the tree turns to the tab that lists it
    function setPickFrom(sel) {
        var tab = getPickTab(sel);
        setPick(sel, true);
        if (sel && tab !== state.tab) setTab(tab);
    }

    // The field a mark of the page names: the key of a section or a part, or an item by its place in the list, items.01.title the first one
    function getTextPath(path) {
        var part = String(path || '').split('.');
        return part.length === 3 ? { key: part[2], num: String(parseInt(part[1], 10) - 1) } : { key: part[0], num: '' };
    }

    // A text typed on the page is saved as the inspector saves its field, and the inspector is drawn again with it
    function setText(sel, path, value) {
        var one = getTextPath(path);
        var values = { token: app.getAttribute('data-sl-lay-token'), page: state.page, sel: sel, tab: getPickTab(sel), redraw: 1 };
        values[one.num === '' ? 'field[' + one.key + ']' : 'item[' + one.num + '][' + one.key + ']'] = value;
        window.htmx.ajax('POST', app.getAttribute('data-sl-lay-save'), { source: app, swap: 'none', values: values });
    }

    // An act of the toolbar of a section on the page: the acts of the foot of its inspector, the library after it, the link to its anchor
    function setFrameAct(act, sel) {
        var acts = { up: 'sec-up', down: 'sec-down', dup: 'sec-dup', hide: 'sec-toggle', del: 'sec-del' };
        if (sel.indexOf('sec:') !== 0) return;
        if (act === 'add') return setLibrary();
        if (act === 'link') {
            var copy = app.querySelector('#sl-lay-insp [data-sl-lay-copy]');
            if (copy && window.setCopyText) window.setCopyText(copy, new URL(copy.getAttribute('data-sl-lay-copy'), window.location.href).href);
            return;
        }
        if (acts[act]) setAct(acts[act], sel.slice(4), '', sel);
    }

    // A message of the page in the frame: it is ready, a pick, a typed text, an act of its toolbar, a dragged section, a text for the editor window
    window.addEventListener('message', function (event) {
        var msg = event.data;
        if (event.origin !== window.location.origin || event.source !== frame.contentWindow || !msg || !msg.sl) return;
        var sel = String(msg.sel || '');
        if (msg.sl === 'ready') return setFrameInit();
        if (msg.sl === 'pick') return setPickFrom(sel);
        if (msg.sl === 'text') return setText(sel, msg.path, String(msg.value));
        if (msg.sl === 'act') return setFrameAct(String(msg.act || ''), sel);
        if (msg.sl === 'move' && sel.indexOf('sec:') === 0) {
            return setAct('sec-move', sel.slice(4), String(parseInt(msg.to, 10) || 0) + '-' + (msg.after ? 1 : 0), sel);
        }
        if (msg.sl === 'open') {
            var one = getTextPath(msg.path);
            if (sel !== state.sel) setPickFrom(sel);
            setEdit(one.key, one.num);
        }
    });

    // The recent types lead the library, three of them, the last put first: a convenience of this browser
    function getRecent() {
        var list = getKept('sl-lay-recent', true);
        return Array.isArray(list) ? list.filter(function (one) { return lib.querySelector('[data-sl-lay-type="' + one + '"]'); }).slice(0, 3) : [];
    }

    function addRecent(type) {
        setKept('sl-lay-recent', [type].concat(getRecent().filter(function (one) { return one !== type; })).slice(0, 3), true);
    }

    // The library: its groups and its search narrow the cards together; the recent types lead it while nothing is searched, my sections stand before the types
    function setLibraryList() {
        var find = lib.querySelector('[data-sl-lay-find]').value.trim().toLowerCase();
        var recent = getRecent();
        var mine = lib.querySelectorAll('#sl-lay-mine [data-sl-lay-card]').length;
        var seen = 0;
        var box = lib.querySelector('[data-sl-lay-recent]');
        lib.querySelector('[data-sl-lay-group="recent"]').hidden = !recent.length;
        lib.querySelector('[data-sl-lay-group="mine"]').hidden = !mine;
        var key = lib.querySelector('[data-sl-lay-group][aria-pressed="true"]');
        if (!key || key.hidden) key = setLibraryGroup(lib.querySelector('[data-sl-lay-group=""]'));
        var group = key.getAttribute('data-sl-lay-group');
        lib.querySelector('[data-sl-lay-sect="types"]').hidden = group === 'mine' || group === 'recent';
        box.textContent = '';
        recent.forEach(function (type) { box.appendChild(lib.querySelector('[data-sl-lay-type="' + type + '"]').cloneNode(true)); });
        box.closest('[data-sl-lay-sect]').hidden = !recent.length || find !== '' || (group !== '' && group !== 'recent');
        if (!box.closest('[data-sl-lay-sect]').hidden) seen += recent.length;
        var own = lib.querySelector('#sl-lay-mine');
        if (own) own.hidden = !mine || (group !== '' && group !== 'mine');
        lib.querySelectorAll('[data-sl-lay-sect="types"] [data-sl-lay-card], #sl-lay-mine [data-sl-lay-card]').forEach(function (card) {
            var kind = card.getAttribute('data-sl-lay-card');
            var show = (kind === 'mine') ? (group === '' || group === 'mine') : (group === '' || kind === group);
            show = show && (find === '' || card.getAttribute('data-sl-lay-words').indexOf(find) !== -1);
            card.hidden = !show;
            if (show && !card.closest('[hidden]')) seen++;
        });
        lib.querySelector('[data-sl-lay-none]').hidden = seen > 0;
    }

    // Press one group of the library, its cell alone; the answer is that cell
    function setLibraryGroup(cell) {
        lib.querySelectorAll('[data-sl-lay-group]').forEach(function (one) {
            one.classList.toggle('sl-is-active', one === cell);
            one.setAttribute('aria-pressed', one === cell ? 'true' : 'false');
        });
        return cell;
    }

    function setLibrary() {
        lib.querySelector('[data-sl-lay-find]').value = '';
        setLibraryList();
        window.setWindowOpen(lib);
    }

    // A window whose body the server writes on each opening, whole or into its list: the checks before a publication, the pages a section is copied to, the history
    function setWindowFrom(box, url, into) {
        window.htmx.ajax('GET', url, { target: into || box, swap: 'innerHTML' }).then(function () { window.setWindowOpen(box); });
    }

    // The changes against the site and the remarks; opened by the publish button it holds the note and the publication itself
    function setChecks(pub) {
        setWindowFrom(checks, app.getAttribute('data-sl-lay-get-checks') + '&page=' + encodeURIComponent(state.page) + (pub ? '&pub=1' : ''));
    }

    function setStory() {
        setWindowFrom(story, app.getAttribute('data-sl-lay-get-story'), '#sl-lay-story-list');
    }

    // The help of the screen: the text of the tab "Справка" of the panel without its frame, fetched on the first opening
    function setHelp() {
        var body = document.getElementById('sl-lay-help-body');
        if (body.childElementCount) return window.setWindowOpen(help);
        window.htmx.ajax('GET', app.getAttribute('data-sl-lay-get-help'), { target: body, swap: 'innerHTML', select: '.sl-markdown' })
            .then(function () { window.setWindowOpen(help); });
    }

    // The editor window of a text of the pick, a field of an item by its place; the frame of the editor comes from the server on each opening
    function setEdit(key, num) {
        var url = app.getAttribute('data-sl-lay-get-edit') + '&page=' + encodeURIComponent(state.page) + '&sel=' + encodeURIComponent(state.sel);
        setWindowFrom(edit, url + '&fld=' + encodeURIComponent(key) + (num !== '' && num !== null ? '&num=' + encodeURIComponent(num) : ''));
    }

    // Open a window of the screen by its name
    function setOpen(name, id) {
        if (name === 'story') return setStory();
        if (name === 'help') return setHelp();
        if (name === 'from') return setWindowFrom(edit, app.getAttribute('data-sl-lay-get-edit') + '&page=' + encodeURIComponent(state.page) + '&from=1');
        if (name === 'link') {
            link.querySelector('[data-sl-lay-link-box]').hidden = true;
            return window.setWindowOpen(link);
        }
        if (name === 'sets') return setWindowFrom(sets, app.getAttribute('data-sl-lay-get-source'), '#sl-lay-file');
        if (name === 'palette') return setPalette();
        if (name === 'checks' || name === 'publish') return setChecks(name === 'publish');
        if (name === 'ready') return window.setWindowOpen(ready);
        if (name === 'dest') return setWindowFrom(dest, app.getAttribute('data-sl-lay-get-dest') + '&sel=' + encodeURIComponent('sec:' + id));
        return window.setWindowOpen(sets);
    }

    // The look of a section kept in this tab: the paste of a section of the same type takes it, its own section never
    function setPasteState() {
        var look = getKept('sl-lay-look');
        app.querySelectorAll('#sl-lay-insp [data-sl-lay-paste]').forEach(function (btn) {
            var on = !!look && look.type === btn.getAttribute('data-sl-lay-paste') && String(look.id) !== btn.getAttribute('data-sl-lay-id');
            btn.disabled = !on;
            btn.setAttribute('data-sl-lay-arg', on ? String(look.id) : '');
            if (on) btn.setAttribute('title', btn.getAttribute('data-sl-lay-ok') || '');
            if (on) btn.setAttribute('aria-label', btn.getAttribute('data-sl-lay-ok') || '');
        });
    }

    // Run the command the palette chose once it has closed
    function setPaletteRun(row) {
        var cmd = row.getAttribute('data-sl-lay-cmd') || '';
        if (row.hasAttribute('data-sl-lay-goto')) return setPick(row.getAttribute('data-sl-lay-goto'));
        if (row.hasAttribute('data-sl-lay-href')) return window.location.assign(row.getAttribute('data-sl-lay-href'));
        if (cmd === 'lib') return setLibrary();
        if (['sets', 'story', 'checks', 'publish', 'link', 'help'].indexOf(cmd) >= 0) return setOpen(cmd);
        if (cmd === 'page-add') return setOpen('ready');
        if (cmd === 'guest') return setGuest(!state.guest);
        if (cmd === 'preview') return setPreview(true);
        if (cmd.indexOf('tab-') === 0) return setTab(cmd.slice(4));
        if (cmd.indexOf('dev-') === 0) return setDevice(cmd.slice(4));
        if (cmd === 'site') {
            var site = app.querySelector('.sl-lay-tools a[href][target]');
            if (site) window.open(site.href, '_blank', 'noopener');
            return;
        }
        setAct(cmd);
    }

    // Open the palette; its sections are those the tree lists now, each with the icon, the name and the words of its row
    function setPalette() {
        var box = pal ? pal.querySelector('[data-sl-lay-pal-secs]') : null;
        if (!box || !window.setPaletteOpen) return;
        box.querySelectorAll('[role="option"]').forEach(function (one) { one.remove(); });
        app.querySelectorAll('#sl-lay-tree a[data-sl-lay-pick^="sec:"]').forEach(function (link) {
            var row = box.querySelector('template').content.firstElementChild.cloneNode(true);
            var sel = link.getAttribute('data-sl-lay-pick');
            var sub = link.querySelector('small');
            row.id = 'sl-lay-pal-' + sel.replace(':', '-');
            row.setAttribute('data-sl-lay-goto', sel);
            row.querySelector('.bi').className = (link.querySelector('.bi') || {}).className || 'bi';
            row.querySelector('.sl-editor-pal-name').textContent = link.title + (sub && sub.textContent ? ' · ' + sub.textContent : '');
            box.appendChild(row);
        });
        window.setPaletteOpen(pal, setPaletteRun, '');
    }

    // Whether a key belongs to a field or a window rather than to the screen
    function isKeyOwned(event) {
        return !!document.querySelector('dialog[open]') || !!(event.target.closest && event.target.closest('input, select, textarea, [contenteditable]'));
    }

    app.addEventListener('click', function (event) {
        var node = event.target.closest ? event.target : null;
        if (!node) return;
        var link = node.closest('[data-sl-lay-pick]');
        var act = node.closest('[data-sl-lay-act]');
        var step = node.closest('[data-sl-lay-step]');
        if (link && link.closest('#sl-lay-tree')) {
            event.preventDefault();
            return setPick(link.getAttribute('data-sl-lay-pick'));
        }
        if (step && !step.disabled) return setPick(step.getAttribute('data-sl-lay-step'));
        var text = node.closest('#sl-lay-insp [data-sl-lay-edit]');
        if (text) return setEdit(text.getAttribute('data-sl-lay-edit'), text.getAttribute('data-sl-lay-num'));
        var copy = node.closest('[data-sl-lay-copy]');
        if (copy && window.setCopyText) return window.setCopyText(copy, new URL(copy.getAttribute('data-sl-lay-copy'), window.location.href).href);
        var look = node.closest('[data-sl-lay-look]');
        if (look) {
            setKept('sl-lay-look', { type: look.getAttribute('data-sl-lay-look'), id: look.getAttribute('data-sl-lay-id') });
            window.setToast(look.getAttribute('data-sl-done') || '');
            return setPasteState();
        }
        if (act && !act.disabled) {
            var name = act.getAttribute('data-sl-lay-act');
            event.preventDefault();
            if (name === 'lib') return setLibrary();
            var sel;
            var row = act.closest('.sl-menu-list-item');
            var own = row ? row.querySelector('[data-sl-lay-pick]') : null;
            if (name.indexOf('menu-') === 0 && name !== 'menu-add' && own) {
                var menu = own.getAttribute('data-sl-lay-pick').split(':').slice(0, 2).join(':');
                sel = state.sel.indexOf(menu) === 0 ? state.sel : menu;
            }
            return setAct(name, act.getAttribute('data-sl-lay-id'), act.getAttribute('data-sl-lay-arg'), sel);
        }
        if (node.closest('[data-sl-lay-unpick]')) return setPick('');
        if (node.closest('[data-sl-lay-unpreview]')) return setPreview(false);
        if (node.closest('[data-sl-lay-guest]')) return setGuest(!state.guest);
        var dev = node.closest('[data-sl-lay-device]');
        if (dev) return setDevice(dev.getAttribute('data-sl-lay-device'));
        var open = node.closest('[data-sl-lay-open]');
        if (open && !open.disabled) return setOpen(open.getAttribute('data-sl-lay-open'), open.getAttribute('data-sl-lay-id'));
    });

    // A double click on the guest view opens the full preview, as Ctrl+Alt+P and the palette do
    app.addEventListener('dblclick', function (event) {
        if (event.target.closest && event.target.closest('[data-sl-lay-guest]')) setPreview(true);
    });

    // The page of the screen is its address: another page reloads the screen
    app.querySelector('[data-sl-lay-page-pick]').addEventListener('change', function (event) {
        window.location.assign(event.target.value);
    });

    // A changed field of the inspector shows its way back to the site at once; the inspector is not drawn again on a save, so the focus stays where it went
    app.addEventListener('change', function (event) {
        var row = event.target.closest ? event.target.closest('#sl-lay-insp .sl-div-item') : null;
        var back = row ? row.querySelector('[data-sl-lay-act="field-back"]') : null;
        if (back) back.hidden = false;
    });

    // An address picked from the list is written into the own field, which the form posts, before the change that saves it; the own address keeps it and takes the focus
    app.addEventListener('input', function (event) {
        var pick = event.target.closest ? event.target.closest('[data-sl-lay-addr]') : null;
        var own = pick ? pick.parentNode.querySelector('[data-sl-lay-own]') : null;
        if (own && pick.value !== '__own') own.value = pick.value;
        if (own && pick.value === '__own') window.setTimeout(function () { own.focus(); }, 0);
    });

    // Enter in the search of a record keeps the inspector from posting: the search asks for its list as the words are typed
    app.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && event.target.matches && event.target.matches('[data-sl-lay-seek]')) event.preventDefault();
    });

    // A range shows its number as it moves; the icon window writes its field by an input event, which the form takes as the change it saves on
    app.addEventListener('input', function (event) {
        var range = event.target.closest ? event.target.closest('[data-sl-lay-range]') : null;
        if (range && range.nextElementSibling) range.nextElementSibling.textContent = range.value;
        var icon = !event.isTrusted && event.target.matches && event.target.matches('#sl-lay-insp [data-sl-icon-input]');
        if (icon) event.target.dispatchEvent(new Event('change', { bubbles: true }));
    });

    lib.addEventListener('click', function (event) {
        var node = event.target.closest ? event.target : null;
        if (!node) return;
        var group = node.closest('[data-sl-lay-group]');
        var add = node.closest('[data-sl-lay-add]');
        var mine = node.closest('[data-sl-lay-mine]');
        var drop = node.closest('[data-sl-lay-mine-del]');
        if (group) {
            setLibraryGroup(group);
            return setLibraryList();
        }
        if (drop) {
            event.preventDefault();
            return setAct('mine-del', drop.getAttribute('data-sl-lay-mine-del'));
        }
        var card = add || mine;
        if (!card) return;
        event.preventDefault();
        if (card.getAttribute('aria-disabled') === 'true') return window.setToast(card.getAttribute('title') || '', { warn: true });
        window.setWindowClose(lib);
        if (mine) return setAct('mine-add', '', mine.getAttribute('data-sl-lay-mine'));
        addRecent(add.getAttribute('data-sl-lay-add'));
        setAct('sec-add', '', add.getAttribute('data-sl-lay-add'));
    });

    lib.querySelector('[data-sl-lay-find]').addEventListener('input', setLibraryList);

    // A ready page is made by its set, the empty one as well, and opened; a page from a text opens the editor window for its document
    ready.addEventListener('click', function (event) {
        var from = event.target.closest ? event.target.closest('[data-sl-lay-from]') : null;
        var card = event.target.closest ? event.target.closest('[data-sl-lay-ready]') : null;
        if (from) {
            event.preventDefault();
            window.setWindowClose(ready);
            return setOpen('from');
        }
        if (!card) return;
        event.preventDefault();
        window.setWindowClose(ready);
        setAct('page-add', '', card.getAttribute('data-sl-lay-ready'));
    });

    // The rows of the windows lead to their places; their acts post through the screen, the publication with its note
    [checks, dest, story].forEach(function (box) {
        box.addEventListener('click', function (event) {
            var node = event.target.closest ? event.target : null;
            var go = node ? node.closest('[data-sl-lay-goto]') : null;
            var act = node ? node.closest('[data-sl-lay-act]') : null;
            if (go && !go.disabled) {
                window.setWindowClose(box);
                return setGo(go.getAttribute('data-sl-lay-goto'), go.getAttribute('data-sl-lay-page'));
            }
            if (!act || act.disabled) return;
            var note = box.querySelector('[data-sl-lay-note]');
            window.setWindowClose(box);
            setAct(act.getAttribute('data-sl-lay-act'), act.getAttribute('data-sl-lay-id'), act.getAttribute('data-sl-lay-arg'), undefined, note ? { note: note.value } : {});
        });
    });

    // The link window makes a link for the term it shows; the answer brings the address and signs the frame anew
    link.addEventListener('click', function (event) {
        if (!event.target.closest || !event.target.closest('[data-sl-lay-link-make]')) return;
        setAct('link', '', link.querySelector('[data-sl-lay-term]').value);
    });

    document.addEventListener('sl-layout-link', function (event) {
        var data = event.detail || {};
        var url = new URL(data.frame || '', window.location.href);
        link.querySelector('[data-sl-lay-link-url]').textContent = data.url || '';
        link.querySelector('[data-sl-lay-link-till]').textContent = data.till || '';
        link.querySelector('[data-sl-lay-link-box]').hidden = false;
        if (state.guest || app.hasAttribute('data-sl-lay-full')) url.searchParams.set('guest', '1');
        frame.setAttribute('src', url.pathname + url.search);
        if (data.text) window.setToast(data.text);
    });

    // The editor window closes once its text was taken; a refusal keeps it open with the text, Ctrl+Enter saves as its button does
    edit.addEventListener('htmx:afterRequest', function (event) {
        var head = event.detail.xhr ? event.detail.xhr.getResponseHeader('HX-Trigger') || '' : '';
        if (event.detail.successful && head.indexOf('sl-quick-note') < 0) window.setWindowClose(edit);
    });

    // "Загрузить файл" opens the choice of a JSON file, whose change posts the foot of the settings; the window closes once the draft took it, a refusal keeps it
    sets.addEventListener('click', function (event) {
        var pick = event.target.closest ? event.target.closest('[data-sl-lay-import]') : null;
        if (pick) sets.querySelector('[data-sl-lay-import-file]').click();
    });

    sets.addEventListener('htmx:afterRequest', function (event) {
        var file = sets.querySelector('[data-sl-lay-import-file]');
        var head = event.detail.xhr ? event.detail.xhr.getResponseHeader('HX-Trigger') || '' : '';
        if (!file || event.detail.elt !== file.form) return;
        file.value = '';
        if (event.detail.successful && head.indexOf('sl-quick-note') < 0) window.setWindowClose(sets);
    });

    edit.addEventListener('keydown', function (event) {
        var form = edit.querySelector('#sl-lay-edit-form');
        if (!form || event.key !== 'Enter' || !(event.ctrlKey || event.metaKey)) return;
        event.preventDefault();
        form.requestSubmit();
    });

    // Enter in the note publishes, as the button beside it would
    checks.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' || !event.target.matches || !event.target.matches('[data-sl-lay-note]')) return;
        event.preventDefault();
        var go = checks.querySelector('[data-sl-lay-act="publish"]');
        if (go) go.click();
    });

    /* Every write carries this editor and the draft and the site it saw, so a write of another editor in between is refused instead of overwritten;
       every request names the module of a page made in memory until it is written, and a request of the tree or of the settings asks for the pick
       and the page the screen holds now, not the ones its buttons were drawn with */
    document.addEventListener('htmx:configRequest', function (event) {
        var elt = event.detail.elt;
        var mark = document.getElementById('sl-lay-state');
        if (event.detail.verb === 'post' && elt && (elt === app || (elt.closest && elt.closest('[data-sl-lay-app], #sl-lay-edit, #sl-lay-sets')))) {
            event.detail.parameters.ed = app.getAttribute('data-sl-lay-ed') || '';
            event.detail.parameters.rev = mark ? mark.getAttribute('data-sl-lay-rev') || '' : '';
            event.detail.parameters.live = mark ? mark.getAttribute('data-sl-lay-live') || '' : '';
        }
        if (app.getAttribute('data-sl-lay-mod') && /[?&]name=layout(?:&|$)/.test(event.detail.path)) event.detail.parameters.mod = app.getAttribute('data-sl-lay-mod');
        if (!elt || !elt.closest || !elt.closest('#sl-lay-tree, #sl-lay-sets')) return;
        event.detail.parameters.page = state.page;
        if (!/sel=menu/.test(event.detail.path)) event.detail.parameters.sel = state.sel;
    });

    // A part swapped out of band is set up as after any swap: the tabs, the rows shown for a value, the toggled items of the panel, the paste of a look
    document.addEventListener('htmx:oobAfterSwap', function (event) {
        var box = event.target;
        if (box && box.id === 'sl-lay-mine' && lib.open) setLibraryList();
        if (!box || !box.isConnected || ['sl-lay-insp', 'sl-lay-tree'].indexOf(box.id) < 0) return;
        box.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: { target: box, elt: box } }));
    });

    // After any swap the tab of the tree is the one it came with, also when its own tabs fetched it, so a reload comes back to it
    document.addEventListener('htmx:afterSwap', function () {
        var tree = document.getElementById('sl-lay-tree');
        var tab = tree ? tree.getAttribute('data-sl-lay-tab') : '';
        if (tab && tab !== state.tab) {
            state.tab = tab;
            setPlace();
        }
        setPasteState();
    });

    // A write of the draft came back: the pick, the tab and the page follow it, the notice tells what happened, the frame shows the new draft
    document.addEventListener('sl-layout-saved', function (event) {
        var data = event.detail || {};
        if (String(data.page) !== String(state.page)) {
            state.sel = data.sel || '';
            state.tab = data.tab || 'page';
            state.page = String(data.page);
            setPlace();
            if (data.text) setKept('sl-lay-note', { text: data.text, undo: data.undo });
            return window.location.assign(getPageUrl(data.page, state.sel));
        }
        state.sel = data.sel || '';
        state.tab = data.tab || state.tab;
        setPlace();
        if (data.text) window.setToast(data.text, data.undo ? { undo: function () { setAct('undo'); } } : {});
        setFrame();
    });

    // The rows of a list were dragged: the new order goes to the draft, a level of a menu by its parent
    document.addEventListener('sl:lay-order', function (event) {
        var list = event.target.getAttribute('data-sl-lay-list') || '';
        var ids = event.detail.ids.join('-');
        var row = event.target.querySelector('[data-sl-drag-id="' + event.detail.id + '"]');
        if (list === 'sec') return setAct('sec-order', '', ids);
        if (list.indexOf('blk-') === 0) return setAct('blk-order', list.slice(4), ids);
        if (list.indexOf('menu-') === 0) {
            var menu = 'menu:' + list.slice(5);
            setAct('menu-order', row ? row.getAttribute('data-sl-drag-parent') || '0' : '0', ids, state.sel.indexOf(menu) === 0 ? state.sel : menu);
        }
    });

    // Keys of the screen and of its page, before those of the panel: Ctrl+K the palette, Ctrl+Alt+P the preview, Ctrl+Z, Ctrl+Shift+Z or Ctrl+Y the steps, Ctrl+D a copy, Esc
    function setKey(event) {
        var ctrl = event.ctrlKey || event.metaKey;
        if (event.isComposing) return;
        if (ctrl && !event.altKey && !event.shiftKey && event.code === 'KeyK') {
            if (document.querySelector('dialog[open]')) return;
            event.preventDefault();
            event.stopPropagation();
            return setPalette();
        }
        if (ctrl && event.altKey && !event.shiftKey && event.code === 'KeyP') {
            if (document.querySelector('dialog[open]')) return;
            event.preventDefault();
            return setPreview(!app.hasAttribute('data-sl-lay-full'));
        }
        if (event.key === 'Escape' && app.hasAttribute('data-sl-lay-full') && !document.querySelector('dialog[open]')) return setPreview(false);
        if (isKeyOwned(event)) return;
        if (ctrl && !event.altKey && (event.code === 'KeyZ' || event.code === 'KeyY')) {
            event.preventDefault();
            return setAct(event.code === 'KeyY' || event.shiftKey ? 'redo' : 'undo');
        }
        if (ctrl && !event.altKey && !event.shiftKey && event.code === 'KeyD' && state.sel.indexOf('sec:') === 0) {
            event.preventDefault();
            return setAct('sec-dup', state.sel.slice(4));
        }
        if (event.key === 'Escape' && state.sel) setPick('');
    }

    window.addEventListener('keydown', setKey, true);
    new window.ResizeObserver(function () { setFit(); }).observe(app.querySelector('[data-sl-lay-frame]').parentElement);

    var place = getKept('sl-lay-place');
    if (place && String(place.page) === String(state.page)) {
        setDevice(place.dev || 'desk');
        if (place.guest) setGuest(true);
        if (!state.sel && place.sel) setPick(place.sel);
        if (place.tab && place.tab !== state.tab) setTab(place.tab);
    }
    var note = getKept('sl-lay-note');
    if (note && note.text) {
        setKept('sl-lay-note', null);
        window.setToast(note.text, note.undo ? { undo: function () { setAct('undo'); } } : {});
    }
    setPasteState();
    setPlace();
})();
