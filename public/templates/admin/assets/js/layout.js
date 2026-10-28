/* The screen of the site builder (admin/modules/layout.php): picks fetch the inspector, every act posts to the draft and the answer swaps the tree,
   the inspector and the state out of band, then raises sl-layout-saved, on which the frame of the page reloads where it stood */
(function () {
    var app = document.querySelector('[data-sl-lay-app]');
    if (!app || !window.htmx) return;
    var frame = app.querySelector('[data-sl-lay-frame] iframe');
    var lib = document.getElementById('sl-lay-lib');
    var sets = document.getElementById('sl-lay-sets');
    var pal = document.getElementById('sl-lay-pal');
    var story = document.getElementById('sl-lay-story');
    // The widths of the devices of the top bar, written as --sl-d-device; the computer takes the whole stage
    var devices = { desk: '', tablet: '768px', phone: '390px' };
    var state = { page: app.getAttribute('data-sl-lay-page'), sel: app.getAttribute('data-sl-lay-sel'), tab: app.getAttribute('data-sl-lay-tab') };

    // The place of the work in this tab of the browser: the pick, the tab of the tree and the device come back after a reload of the same page
    function setPlace() {
        try { window.sessionStorage.setItem('sl-lay-place', JSON.stringify(state)); } catch (e) { return; }
    }

    function getPlace() {
        try { return JSON.parse(window.sessionStorage.getItem('sl-lay-place') || 'null'); } catch (e) { return null; }
    }

    // Post one act of the screen with the pick it acts on; the answer swaps out of band and raises its events on the screen
    function setAct(act, id, arg, sel) {
        window.htmx.ajax('POST', app.getAttribute('data-sl-lay-post'), {
            source: app,
            swap: 'none',
            values: {
                token: app.getAttribute('data-sl-lay-token'), page: state.page, sel: sel === undefined ? state.sel : sel, tab: state.tab, act: act, id: id || '', arg: arg || ''
            }
        });
    }

    // Pick a part: the tree marks its row, the inspector comes from the server
    function setPick(sel) {
        state.sel = sel;
        app.querySelectorAll('#sl-lay-tree a[aria-current]').forEach(function (one) { one.removeAttribute('aria-current'); });
        var row = sel ? app.querySelector('#sl-lay-tree a[data-sl-lay-pick="' + sel + '"]') : null;
        if (row) row.setAttribute('aria-current', 'page');
        var url = app.getAttribute('data-sl-lay-get-insp') + '&page=' + encodeURIComponent(state.page) + '&sel=' + encodeURIComponent(sel);
        window.htmx.ajax('GET', url, { target: '#sl-lay-insp', swap: 'outerHTML' });
        setPlace();
    }

    // Show the tree of a tab
    function setTab(tab) {
        state.tab = tab;
        var url = app.getAttribute('data-sl-lay-get-tree') + '&page=' + encodeURIComponent(state.page) + '&tab=' + tab + '&sel=' + encodeURIComponent(state.sel);
        window.htmx.ajax('GET', url, { target: '#sl-lay-tree', swap: 'outerHTML' });
        setPlace();
    }

    // The device of the frame: its width, the pressed cell of the top bar
    function setDevice(dev) {
        if (!(dev in devices)) dev = 'desk';
        state.dev = dev;
        app.querySelector('[data-sl-lay-frame]').style.setProperty('--sl-d-device', devices[dev] || '100%');
        app.querySelectorAll('[data-sl-lay-device]').forEach(function (one) {
            var on = one.getAttribute('data-sl-lay-device') === dev;
            one.classList.toggle('sl-is-active', on);
            one.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        setPlace();
    }

    // Reload the page of the draft in its frame and put it back where it was scrolled
    function setFrame() {
        var top = 0;
        try { top = frame.contentWindow.scrollY; } catch (e) { top = 0; }
        frame.addEventListener('load', function back() {
            frame.removeEventListener('load', back);
            try { frame.contentWindow.scrollTo(0, top); } catch (e) { return; }
        });
        try { frame.contentWindow.location.reload(); } catch (e) { frame.src = frame.src; }
    }

    // The library: its groups and its search narrow the cards together, an empty result says so
    function setLibraryList() {
        var key = lib.querySelector('[data-sl-lay-group][aria-pressed="true"]');
        var group = key ? key.getAttribute('data-sl-lay-group') : '';
        var find = lib.querySelector('[data-sl-lay-find]').value.trim().toLowerCase();
        var seen = 0;
        lib.querySelectorAll('[data-sl-lay-card]').forEach(function (card) {
            var show = (group === '' || card.getAttribute('data-sl-lay-card') === group) && (find === '' || card.getAttribute('data-sl-lay-words').indexOf(find) !== -1);
            card.hidden = !show;
            if (show) seen++;
        });
        lib.querySelector('[data-sl-lay-none]').hidden = seen > 0;
    }

    function setLibrary() {
        lib.querySelector('[data-sl-lay-find]').value = '';
        setLibraryList();
        window.setWindowOpen(lib);
    }

    // Run the command the palette chose once it has closed
    function setPaletteRun(row) {
        var cmd = row.getAttribute('data-sl-lay-cmd') || '';
        if (row.hasAttribute('data-sl-lay-goto')) return setPick(row.getAttribute('data-sl-lay-goto'));
        if (row.hasAttribute('data-sl-lay-href')) return window.location.assign(row.getAttribute('data-sl-lay-href'));
        if (cmd === 'lib') return setLibrary();
        if (cmd === 'sets') return window.setWindowOpen(sets);
        if (cmd === 'story') return setStory();
        if (cmd.indexOf('tab-') === 0) return setTab(cmd.slice(4));
        if (cmd.indexOf('dev-') === 0) return setDevice(cmd.slice(4));
        if (cmd === 'site' || cmd === 'help') {
            var link = app.querySelector(cmd === 'site' ? '.sl-lay-tools a[href][target]' : '.sl-lay-tools a[href*="op=info"]');
            if (link) window.open(link.href, '_blank', 'noopener');
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
        var link = node ? node.closest('[data-sl-lay-pick]') : null;
        var act = node ? node.closest('[data-sl-lay-act]') : null;
        if (link && link.closest('#sl-lay-tree')) {
            event.preventDefault();
            return setPick(link.getAttribute('data-sl-lay-pick'));
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
        if (node && node.closest('[data-sl-lay-unpick]')) return setPick('');
        var dev = node ? node.closest('[data-sl-lay-device]') : null;
        if (dev) return setDevice(dev.getAttribute('data-sl-lay-device'));
        var open = node ? node.closest('[data-sl-lay-open]') : null;
        if (open && open.getAttribute('data-sl-lay-open') === 'story') return setStory();
        if (open) return open.getAttribute('data-sl-lay-open') === 'palette' ? setPalette() : window.setWindowOpen(sets);
    });

    // The page of the screen is its address: another page reloads the screen
    app.querySelector('[data-sl-lay-page-pick]').addEventListener('change', function (event) {
        window.location.assign(event.target.value);
    });

    // A range shows its number as it moves; the icon window writes its field by an input event, which the form takes as the change it saves on
    app.addEventListener('input', function (event) {
        var range = event.target.closest ? event.target.closest('[data-sl-lay-range]') : null;
        if (range && range.nextElementSibling) range.nextElementSibling.textContent = range.value;
        var icon = !event.isTrusted && event.target.matches && event.target.matches('#sl-lay-insp [data-sl-icon-input]');
        if (icon) event.target.dispatchEvent(new Event('change', { bubbles: true }));
    });

    lib.addEventListener('click', function (event) {
        var group = event.target.closest ? event.target.closest('[data-sl-lay-group]') : null;
        var add = event.target.closest ? event.target.closest('[data-sl-lay-add]') : null;
        if (group) {
            lib.querySelectorAll('[data-sl-lay-group]').forEach(function (one) {
                one.classList.toggle('sl-is-active', one === group);
                one.setAttribute('aria-pressed', one === group ? 'true' : 'false');
            });
            return setLibraryList();
        }
        if (!add) return;
        event.preventDefault();
        if (add.getAttribute('aria-disabled') === 'true') return window.setToast(add.getAttribute('title') || '', { warn: true });
        window.setWindowClose(lib);
        setAct('sec-add', '', add.getAttribute('data-sl-lay-add'));
    });

    lib.querySelector('[data-sl-lay-find]').addEventListener('input', setLibraryList);

    // The history of publications: its list comes from the server on each opening, a version goes back into the draft as an act of the screen
    function setStory() {
        window.htmx.ajax('GET', app.getAttribute('data-sl-lay-get-story'), { target: '#sl-lay-story-list', swap: 'innerHTML' }).then(function () {
            window.setWindowOpen(story);
        });
    }

    story.addEventListener('click', function (event) {
        var back = event.target.closest ? event.target.closest('[data-sl-lay-act]') : null;
        if (!back) return;
        window.setWindowClose(story);
        setAct(back.getAttribute('data-sl-lay-act'), back.getAttribute('data-sl-lay-id'));
    });

    /* Every write carries this editor and the draft and the site it saw, so a write of another editor in between is refused instead of overwritten;
       a request of the tree asks for the pick and the page the screen holds now, not the ones its buttons were drawn with */
    document.addEventListener('htmx:configRequest', function (event) {
        var elt = event.detail.elt;
        var mark = document.getElementById('sl-lay-state');
        if (event.detail.verb === 'post' && elt && (elt === app || (elt.closest && elt.closest('[data-sl-lay-app]')))) {
            event.detail.parameters.ed = app.getAttribute('data-sl-lay-ed') || '';
            event.detail.parameters.rev = mark ? mark.getAttribute('data-sl-lay-rev') || '' : '';
            event.detail.parameters.live = mark ? mark.getAttribute('data-sl-lay-live') || '' : '';
        }
        if (!elt || !elt.closest || !elt.closest('#sl-lay-tree')) return;
        event.detail.parameters.page = state.page;
        if (!/sel=menu/.test(event.detail.path)) event.detail.parameters.sel = state.sel;
    });

    // A part swapped out of band is set up as after any swap: the tabs, the rows shown for a value, the toggled items of the panel
    document.addEventListener('htmx:oobAfterSwap', function (event) {
        var box = event.target;
        if (!box || !box.isConnected || ['sl-lay-insp', 'sl-lay-tree'].indexOf(box.id) < 0) return;
        box.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: { target: box, elt: box } }));
    });

    // A write of the draft came back: the pick, the tab and the page follow it, the notice tells what happened, the frame shows the new draft
    document.addEventListener('sl-layout-saved', function (event) {
        var data = event.detail || {};
        if (String(data.page) !== String(state.page)) {
            state.sel = data.sel || '';
            state.tab = data.tab || 'page';
            state.page = String(data.page);
            setPlace();
            var url = app.getAttribute('data-sl-lay-get-tree').replace('&op=tree', '');
            return window.location.assign(url + '&page=' + encodeURIComponent(data.page) + '&sel=' + encodeURIComponent(state.sel));
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

    // Keys of the screen, taken before those of the panel: Ctrl+K its palette, Ctrl+Z and Ctrl+Shift+Z or Ctrl+Y the steps, Esc lets the pick go
    window.addEventListener('keydown', function (event) {
        var ctrl = event.ctrlKey || event.metaKey;
        if (event.isComposing) return;
        if (ctrl && !event.altKey && !event.shiftKey && event.code === 'KeyK') {
            if (document.querySelector('dialog[open]')) return;
            event.preventDefault();
            event.stopPropagation();
            return setPalette();
        }
        if (isKeyOwned(event)) return;
        if (ctrl && !event.altKey && (event.code === 'KeyZ' || event.code === 'KeyY')) {
            event.preventDefault();
            return setAct(event.code === 'KeyY' || event.shiftKey ? 'redo' : 'undo');
        }
        if (event.key === 'Escape' && state.sel) setPick('');
    }, true);

    var place = getPlace();
    if (place && String(place.page) === String(state.page)) {
        setDevice(place.dev || 'desk');
        if (!state.sel && place.sel) setPick(place.sel);
    }
    setPlace();
})();
