(function () {
    'use strict';
    var dockEdge = 12;
    var dockPhone = window.matchMedia('(max-width: 560px)');
    var dockGone = {};
    // С11 The menu on the site keeps its own point, axis and fold; the edge and the growth are the ones of the panel
    function getDockKey(name) {
        var box = document.querySelector('[data-sl-dock]');
        var own = box && box.hasAttribute('data-sl-dock-site') && ['at', 'dir', 'fold'].indexOf(name) !== -1;
        return (own ? 'dock-site-' : 'dock-') + name;
    }
    // The floating menu keeps its point, axis, edge, fold and growth in the browser, one entry each
    function getDockKept(name) {
        return window.getToggleState(getDockKey(name), '');
    }
    // Store one answer of the floating menu beside the others of the browser
    function setDockKept(name, value) {
        window.setToggleState(getDockKey(name), '', value);
    }
    // Store one answer through op option with the token the menu carries, in the panel and on the site alike; a refusal rejects
    function setOptionSend(key, value) {
        var box = document.querySelector('[data-sl-dock]');
        var body = new FormData();
        if (!box) return Promise.reject(new Error('no menu'));
        body.set('op', 'option');
        body.set('token', box.getAttribute('data-sl-option-token'));
        body.set('key', key);
        body.set('value', value);
        return fetch(box.getAttribute('data-sl-option'), { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) { if (!res.ok) throw new Error('refused'); });
    }
    // М5 The point of the menu as shares of the window, or null while it stands on its edge
    function getDockAt() {
        try {
            var at = JSON.parse(getDockKept('at') || 'null');
            return at && typeof at.x === 'number' ? at : null;
        } catch (err) {
            return null;
        }
    }
    // М10 The edge chosen in Options; the bottom is the first state
    function getDockSide() {
        var side = getDockKept('side');
        return ['top', 'right', 'left'].indexOf(side) !== -1 ? side : 'bottom';
    }
    // М5 М6 М10 Stand the layer on its point or its edge and keep it whole; upright on a short screen the tiles give way, never under 32 px
    function setDockPlace() {
        var box = document.querySelector('[data-sl-dock]');
        if (!box) return;
        var bar = box.querySelector('.sl-dock-bar');
        var col, side, edge, at, rect, tile;
        if (document.documentElement.hasAttribute('data-sl-dock-off')) setDockClose();
        box.setAttribute('data-dir', dockPhone.matches ? 'row' : (getDockKept('dir') === 'col' ? 'col' : 'row'));
        col = box.getAttribute('data-dir') === 'col';
        box.querySelectorAll('[data-sl-dock-dir]').forEach(function (btn) { btn.setAttribute('aria-pressed', String((btn.getAttribute('data-sl-dock-dir') === 'col') === col)); });
        bar.style.removeProperty('--sl-d-dock-tile');
        tile = parseFloat(window.getComputedStyle(bar.querySelector('.sl-dock-app')).width) || 44;
        while (col && tile > 32 && bar.getBoundingClientRect().height > window.innerHeight - dockEdge * 2) {
            tile -= 2;
            bar.style.setProperty('--sl-d-dock-tile', tile + 'px');
        }
        side = getDockSide();
        edge = { top: { x: 0.5, y: 0 }, right: { x: 1, y: 0.5 }, left: { x: 0, y: 0.5 } }[col && (side === 'top' || side === 'bottom') ? 'right' : side];
        at = getDockAt() || edge || null;
        if (!box.hasAttribute('data-drag')) {
            if (at && !dockPhone.matches) {
                box.setAttribute('data-placed', '');
                rect = box.getBoundingClientRect();
                box.style.setProperty('--sl-d-dock-x', Math.min(Math.max(at.x * window.innerWidth, rect.width / 2 + dockEdge),
                    window.innerWidth - rect.width / 2 - dockEdge) + 'px');
                box.style.setProperty('--sl-d-dock-y', Math.min(Math.max(at.y * window.innerHeight, rect.height / 2 + dockEdge),
                    window.innerHeight - rect.height / 2 - dockEdge) + 'px');
            } else {
                box.removeAttribute('data-placed');
                box.style.removeProperty('--sl-d-dock-x');
                box.style.removeProperty('--sl-d-dock-y');
            }
        }
        setDockTurn(box);
        setDockLift(box);
    }
    // М4 Tips, pops and the growth of the icons turn to where the room is
    function setDockTurn(box) {
        var rect = box.getBoundingClientRect();
        var cx = rect.left + rect.width / 2;
        var cy = rect.top + rect.height / 2;
        box.setAttribute('data-to', box.getAttribute('data-dir') === 'col' ? (cx < window.innerWidth / 2 ? 'right' : 'left') : (cy > window.innerHeight * 0.45 ? 'up' : 'down'));
    }
    // М12 С12 The notice keeps its home at the bottom centre and steps over a pill lying there; a tall upright pill is passed beside
    function setDockLift(box) {
        var root = document.documentElement;
        var rect = box.getBoundingClientRect();
        var hit = rect.height > 0 && rect.bottom > window.innerHeight - 110 && rect.top > window.innerHeight * 0.6 && rect.right > window.innerWidth / 2 - 320
            && rect.left < window.innerWidth / 2 + 320;
        if (hit) root.style.setProperty('--sl-d-dock-lift', (window.innerHeight - rect.top + 14) + 'px');
        else root.style.removeProperty('--sl-d-dock-lift');
    }
    // М3 Only the icons near the hand grow along the axis, the one under it most; the pill and the cells never move
    function setDockGrow(event) {
        var box = document.querySelector('[data-sl-dock]');
        if (!box || box.hasAttribute('data-drag') || document.documentElement.hasAttribute('data-sl-dock-off')) return;
        var bar = box.querySelector('.sl-dock-bar');
        var rect = bar.getBoundingClientRect();
        var col = box.getAttribute('data-dir') === 'col';
        var near = event.clientY > rect.top - 50 && event.clientY < rect.bottom + 50 && event.clientX > rect.left - 50 && event.clientX < rect.right + 50;
        var flat = !near || getDockKept('mag') === '0' || window.isMotionStill() || event.pointerType === 'touch' || dockPhone.matches;
        if (flat && !box.hasAttribute('data-grow')) return;
        box.toggleAttribute('data-grow', !flat);
        bar.querySelectorAll('.sl-dock-app').forEach(function (app) {
            if (flat) return app.style.removeProperty('--sl-d-dock-s');
            var cell = app.getBoundingClientRect();
            var mid = col ? cell.top + cell.height / 2 : cell.left + cell.width / 2;
            var grow = Math.max(0, Math.cos(Math.min(Math.abs((col ? event.clientY : event.clientX) - mid), 70) / 70 * Math.PI / 2));
            app.style.setProperty('--sl-d-dock-s', (1 + 0.5 * grow).toFixed(3));
        });
    }
    // М5 Drag by the grip: anywhere in the window, the point kept when the pointer lets go or is cancelled
    function setDockDrag(box) {
        var drag = null;
        box.addEventListener('pointerdown', function (event) {
            var grip = event.target.closest('[data-sl-dock-grip]');
            if (!grip || event.button !== 0) return;
            var rect = box.getBoundingClientRect();
            drag = { x: event.clientX, y: event.clientY, dx: event.clientX - (rect.left + rect.width / 2), dy: event.clientY - (rect.top + rect.height / 2),
                moved: false, id: event.pointerId };
            event.preventDefault();
            grip.focus({ preventScroll: true });
            grip.setPointerCapture(event.pointerId);
        });
        box.addEventListener('pointermove', function (event) {
            if (!drag || event.pointerId !== drag.id) return;
            if (!drag.moved && Math.hypot(event.clientX - drag.x, event.clientY - drag.y) < 5) return;
            if (!drag.moved) {
                drag.moved = true;
                setDockClose();
                box.setAttribute('data-drag', '');
                box.setAttribute('data-placed', '');
            }
            var rect = box.getBoundingClientRect();
            var x = Math.min(Math.max(event.clientX - drag.dx, rect.width / 2 + dockEdge), window.innerWidth - rect.width / 2 - dockEdge);
            var y = Math.min(Math.max(event.clientY - drag.dy, rect.height / 2 + dockEdge), window.innerHeight - rect.height / 2 - dockEdge);
            box.style.setProperty('--sl-d-dock-x', x + 'px');
            box.style.setProperty('--sl-d-dock-y', y + 'px');
            drag.at = [x, y];
            setDockTurn(box);
        });
        function setDrop(event) {
            if (!drag || event.pointerId !== drag.id) return;
            var done = drag;
            drag = null;
            if (!done.moved) return;
            box.removeAttribute('data-drag');
            setDockAt(done.at[0], done.at[1], true);
        }
        box.addEventListener('pointerup', setDrop);
        box.addEventListener('pointercancel', setDrop);
    }
    // М5 Keep the point as shares of the window; a point of the grip is the own place Options call custom, the one a fold holds is not
    function setDockAt(x, y, own) {
        var at = { x: +(x / window.innerWidth).toFixed(4), y: +(y / window.innerHeight).toFixed(4) };
        var was = getDockAt();
        if (!own && !(was && was.own !== false)) at.own = false;
        setDockKept('at', JSON.stringify(at));
        setDockSideMark();
        setDockPlace();
    }
    // М10 С11 An edge drops the own point and lays the pill along it: across on the top and the bottom, upright on the sides
    function setDockSide(side) {
        if (['top', 'right', 'bottom', 'left'].indexOf(side) === -1) return;
        setDockKept('side', side);
        setDockKept('dir', side === 'right' || side === 'left' ? 'col' : 'row');
        setDockKept('at', '');
        setDockSideMark();
        setDockClose();
        setDockPlace();
    }
    // М10 The row of Options shows the edge, or "custom" once the grip has set a point of its own
    function setDockSideMark() {
        var at = getDockAt();
        document.querySelectorAll('#sl-set-dock-side').forEach(function (pick) { pick.value = at && at.own !== false ? '' : getDockSide(); });
    }
    // Shut the open pop of the menu; a focus inside it goes back to the icon that opened it
    function setDockClose() {
        var box = document.querySelector('[data-sl-dock]');
        if (!box || !box.hasAttribute('data-open')) return;
        box.removeAttribute('data-open');
        box.querySelectorAll('.sl-dock-pop').forEach(function (pop) {
            var btn = box.querySelector('[data-sl-dock-pop="' + pop.getAttribute('data-sl-dock-pop-id') + '"]');
            if (!pop.hidden && pop.contains(document.activeElement) && btn) btn.focus();
            pop.hidden = true;
        });
        box.querySelectorAll('[data-sl-dock-pop]').forEach(function (btn) { btn.setAttribute('aria-expanded', 'false'); });
    }
    // Open the pop of an icon on the side of the room, centred on the icon and held inside the window; a search takes the focus, a key press the first item
    function setDockPop(btn, keyed) {
        var box = document.querySelector('[data-sl-dock]');
        var pop = box.querySelector('[data-sl-dock-pop-id="' + btn.getAttribute('data-sl-dock-pop') + '"]');
        var open = pop.hidden;
        var wide, cell, half, find;
        setDockClose();
        if (!open) return;
        setDockSections(box, pop);
        wide = box.getBoundingClientRect();
        cell = btn.getBoundingClientRect();
        pop.hidden = false;
        if (box.getAttribute('data-dir') === 'row') {
            half = pop.offsetWidth / 2;
            pop.style.setProperty('--sl-d-dock-px', (Math.min(Math.max(cell.left + cell.width / 2, half + 16), window.innerWidth - half - 16) - wide.left) + 'px');
        } else {
            half = pop.offsetHeight / 2;
            pop.style.setProperty('--sl-d-dock-py', (Math.min(Math.max(cell.top + cell.height / 2, half + 16), window.innerHeight - half - 16) - wide.top) + 'px');
        }
        box.setAttribute('data-open', '');
        btn.setAttribute('aria-expanded', 'true');
        find = pop.querySelector('[data-sl-dock-find]');
        if (find) find.focus({ preventScroll: true });
        else if (keyed && pop.querySelector('a, button')) pop.querySelector('a, button').focus({ preventScroll: true });
    }
    // М7 The fold button names what a press will do
    function setDockFoldMark(btn, shut) {
        var name = btn.getAttribute(shut ? 'data-sl-unfold' : 'data-sl-fold');
        btn.setAttribute('aria-expanded', shut ? 'false' : 'true');
        btn.setAttribute('aria-label', name);
        btn.title = name;
    }
    // М7 Fold the body along the axis of the pill by setSlideMotion, the far end held under the hand, then keep the point it ends on
    function setDockFold(shut) {
        var box = document.querySelector('[data-sl-dock]');
        var body = box.querySelector('[data-sl-dock-body]');
        var col = box.getAttribute('data-dir') === 'col';
        var rect = box.getBoundingClientRect();
        var end = col ? rect.bottom : rect.right;
        var raf = 0;
        var run;
        setDockClose();
        box.toggleAttribute('data-min', shut);
        setDockKept('fold', shut ? '1' : '0');
        setDockFoldMark(box.querySelector('.sl-dock-tools [data-sl-dock-fold]'), shut);
        box.setAttribute('data-placed', '');
        box.style.setProperty('--sl-d-dock-x', (rect.left + rect.width / 2) + 'px');
        box.style.setProperty('--sl-d-dock-y', (rect.top + rect.height / 2) + 'px');
        function setHold() {
            var now = box.getBoundingClientRect();
            if (col) box.style.setProperty('--sl-d-dock-y', (end - now.height / 2) + 'px');
            else box.style.setProperty('--sl-d-dock-x', (end - now.width / 2) + 'px');
            raf = window.requestAnimationFrame(setHold);
        }
        run = window.setSlideMotion(body, !shut, 500, col ? 'y' : 'x');
        setHold();
        run.addEventListener('finish', function () {
            window.cancelAnimationFrame(raf);
            var now = box.getBoundingClientRect();
            setDockAt(col ? now.left + now.width / 2 : end - now.width / 2, col ? end - now.height / 2 : now.top + now.height / 2, false);
        });
        run.addEventListener('cancel', function () { window.cancelAnimationFrame(raf); });
    }
    // М6 Across by default, upright on a press, and back; the point the pill stands on stays
    function setDockDir(dir) {
        setDockKept('dir', dir);
        setDockClose();
        setDockPlace();
    }
    // М9 The keys of the pins in the order the pill shows them
    function getDockPins(box) {
        return Array.prototype.map.call(box.querySelectorAll('[data-sl-dock-pins] [data-sl-pin-key]'), function (app) { return app.getAttribute('data-sl-pin-key'); });
    }
    // М9 A pin the pill lacks: the one it just let go, or a new icon cloned from the menu row, card or section tile of its section
    function getDockPinApp(box, key) {
        var item = document.querySelector('[data-sl-menu-item="' + key + '"]');
        var link = item ? item.querySelector('a[href]') : null;
        var here = box.querySelector('[data-sl-dock-pins]').getAttribute('data-sl-here');
        var app, name, icon;
        if (dockGone[key]) return dockGone[key];
        if (!link) return null;
        app = box.querySelector('template[data-sl-dock-pin]').content.firstElementChild.cloneNode(true);
        name = item.querySelector('.sl-menu-name').textContent.trim();
        icon = link.querySelector('.bi');
        app.href = link.href;
        app.setAttribute('data-sl-pin-key', key);
        app.setAttribute('aria-label', name);
        app.setAttribute('data-tip', link.getAttribute('aria-current') === 'page' ? name + ' · ' + here : name);
        if (link.getAttribute('aria-current') === 'page') {
            app.setAttribute('aria-current', 'page');
            app.classList.add('sl-dock-here');
        }
        if (icon) app.querySelector('.bi').className = icon.className;
        return app;
    }
    // М9 Lay the pins out in the order given, keep the nodes of those that stay, and let every fan and star say what a press on its pin will do
    function setDockPins(box, list) {
        var group = box.querySelector('[data-sl-dock-pins]');
        var have = {};
        group.querySelectorAll('[data-sl-pin-key]').forEach(function (app) { have[app.getAttribute('data-sl-pin-key')] = app; });
        list.forEach(function (key) {
            var app = have[key] || getDockPinApp(box, key);
            delete have[key];
            delete dockGone[key];
            if (app) group.appendChild(app);
        });
        Object.keys(have).forEach(function (key) {
            dockGone[key] = have[key];
            have[key].remove();
        });
        document.querySelectorAll('[data-sl-pin]').forEach(function (btn) {
            var on = list.indexOf(btn.getAttribute('data-sl-pin')) > -1;
            btn.setAttribute('aria-pressed', String(on));
            btn.title = box.getAttribute(on ? 'data-sl-pin-drop' : 'data-sl-pin-add');
            btn.querySelector('.bi').className = 'bi bi-' + (btn.getAttribute('data-sl-pin-icon') || 'pin-angle') + (on ? '-fill' : '');
        });
        setDockPlace();
    }
    // М9 С9 Pin or unpin a section from a fan or a star, at most eight, stored by op option; Undo lays and stores the list it had
    function setDockPin(btn) {
        var box = document.querySelector('[data-sl-dock]');
        var key = btn.getAttribute('data-sl-pin');
        var item = btn.closest('[data-sl-menu-item]');
        var name = item ? item.querySelector('.sl-menu-name').textContent.trim() : key;
        var was = getDockPins(box);
        var list = was.filter(function (one) { return one !== key; });
        if (list.length === was.length) {
            if (was.length >= 8) return window.setToast(box.getAttribute('data-sl-pin-max'), { warn: true });
            list.push(key);
        }
        setDockPins(box, list);
        setOptionSend('pins', list.join('-')).catch(function () { setDockPins(box, was); });
        window.setToast(box.getAttribute(list.indexOf(key) > -1 ? 'data-sl-pin-on' : 'data-sl-pin-off').replace('%s', name), { undo: function () {
            setDockPins(box, was);
            setOptionSend('pins', was.join('-')).catch(function () {});
        } });
    }
    // С9 All the sections of the panel, asked once on the first opening and laid out from the templates of the pop, each with its star
    function setDockSections(box, pop) {
        var url = box.getAttribute('data-sl-dock-sections');
        var host = pop.querySelector('[data-sl-dock-lp-host]');
        if (!url || !host || pop.hasAttribute('data-sl-done')) return;
        pop.setAttribute('data-sl-done', '');
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                var pins = getDockPins(box);
                if (!Array.isArray(data)) throw new Error('refused');
                data.forEach(function (grp) {
                    var part = pop.querySelector('template[data-sl-dock-group]').content.cloneNode(true);
                    var list = part.querySelector('[data-sl-dock-lp-list]');
                    part.querySelector('[data-sl-dock-lp-cap]').textContent = grp.title;
                    grp.rows.forEach(function (row) {
                        var tile = pop.querySelector('template[data-sl-dock-tile]').content.firstElementChild.cloneNode(true);
                        var link = tile.querySelector('a');
                        var star = tile.querySelector('[data-sl-pin]');
                        tile.setAttribute('data-sl-menu-item', row.key);
                        tile.setAttribute('data-sl-dock-lp', row.label.toLowerCase());
                        link.href = row.href;
                        link.querySelector('.bi').classList.add('bi-' + row.icon);
                        tile.querySelector('.sl-menu-name').textContent = row.label;
                        star.setAttribute('data-sl-pin', row.key);
                        list.appendChild(tile);
                    });
                    host.appendChild(part);
                });
                setDockPins(box, pins);
                setDockFind(pop.querySelector('[data-sl-dock-find]'));
            })
            .catch(function () { pop.removeAttribute('data-sl-done'); });
    }
    // С9 The search of all the sections keeps the tiles whose name holds the words, a group with none steps away, nothing found says so
    function setDockFind(field) {
        var pop = field.closest('.sl-dock-pop');
        var term = field.value.trim().toLowerCase();
        var seen = 0;
        pop.querySelectorAll('[data-sl-dock-lp]').forEach(function (tile) {
            tile.hidden = term !== '' && tile.getAttribute('data-sl-dock-lp').indexOf(term) === -1;
            if (!tile.hidden) seen += 1;
        });
        pop.querySelectorAll('[data-sl-dock-lp-list]').forEach(function (list) {
            var gone = !list.querySelector('[data-sl-dock-lp]:not([hidden])');
            list.hidden = gone;
            list.previousElementSibling.hidden = gone;
        });
        pop.querySelector('[data-sl-dock-lp-none]').hidden = seen > 0 || !pop.hasAttribute('data-sl-done');
    }
    // С8 Д8 One answer of the queue: the figures on the four apps, on the bell and on the folded mark, a row at nought steps away; a grown sum is heard
    function setDockQueue(box, rows) {
        var bar = box.querySelector('[data-sl-dock-queue]');
        var bell = bar.querySelector('[data-sl-dock-pop="queue"]');
        var list = box.querySelector('[data-sl-dock-qlist]');
        var shown = 0;
        var sum = 0;
        rows.forEach(function (row) {
            var key = CSS.escape(row.key);
            var app = bar.querySelector('[data-sl-dock-q="' + key + '"]');
            var line = list.querySelector('[data-sl-dock-q="' + key + '"]');
            sum += row.num;
            [app, line].forEach(function (node) {
                if (!node) return;
                node.querySelector('[data-sl-dock-qn]').textContent = row.num;
                node.hidden = !row.num;
            });
            if (app) {
                app.hidden = !row.num || shown >= 4;
                if (!app.hidden) shown += 1;
                app.setAttribute('data-tip', app.getAttribute('data-sl-dock-qcap') + ' · ' + row.num);
                app.setAttribute('aria-label', app.getAttribute('data-tip'));
                bar.insertBefore(app, bell);
            }
            if (line) list.appendChild(line);
        });
        if (sum > (Number(box.getAttribute('data-sl-dock-sum')) || 0)) window.setSoundPlay('queue');
        box.setAttribute('data-sl-dock-sum', sum);
        bell.hidden = !sum;
        bell.setAttribute('data-tip', bell.getAttribute('data-sl-dock-qcap') + ' · ' + sum);
        bell.setAttribute('aria-label', bell.getAttribute('data-tip'));
        box.querySelectorAll('[data-sl-dock-qsum]').forEach(function (node) {
            node.textContent = sum;
            node.hidden = !sum;
        });
        setDockPlace();
    }
    // С8 Ask the server for the queue of the site now; the admin card takes the time left
    function setDockFetch(box) {
        fetch(box.getAttribute('data-sl-dock-poll'), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-SL-Poll': '1' } })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                var left;
                if (!data || !Array.isArray(data.rows)) return;
                setDockQueue(box, data.rows);
                left = Math.max(0, Number(data.left) || 0);
                box.querySelectorAll('[data-sl-sess-left]').forEach(function (node) {
                    node.textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');
                });
            })
            .catch(function () {});
    }
    // С8 The queue on the site asks once a minute while the tab is seen, as the bell of the panel does, and at once after a won queue
    function setDockPoll(box) {
        if (!box.getAttribute('data-sl-dock-poll')) return;
        window.setInterval(function () { if (document.visibilityState === 'visible') setDockFetch(box); }, 60000);
        document.addEventListener('sl-sound', function (event) {
            if (event.detail && event.detail.value === 'win') setDockFetch(box);
        });
    }
    // М1 Wake the layer the page brought: its fold, the growth switch of the admin card, the row of Options, its place, the drag, the growth and the queue
    function setDock() {
        var box = document.querySelector('[data-sl-dock]');
        if (!box) return;
        var body = box.querySelector('[data-sl-dock-body]');
        box.querySelectorAll('[data-sl-dock-mag]').forEach(function (mag) { mag.checked = getDockKept('mag') !== '0'; });
        if (getDockKept('fold') === '1') {
            body.hidden = true;
            body.style.display = 'none';
            box.setAttribute('data-min', '');
            setDockFoldMark(box.querySelector('.sl-dock-tools [data-sl-dock-fold]'), true);
        }
        setDockSideMark();
        setDockPlace();
        setDockDrag(box);
        setDockPoll(box);
        document.addEventListener('pointermove', setDockGrow);
        document.documentElement.addEventListener('mouseleave', function () { setDockGrow({ clientX: -1000, clientY: -1000 }); });
        window.addEventListener('resize', setDockPlace);
    }
    // The presses of the menu: a pin of a fan or a star, turn, axis, way back, fold, a pop; a press anywhere else shuts the open pop
    document.addEventListener('click', function (event) {
        var box = document.querySelector('[data-sl-dock]');
        var target = event.target;
        var btn;
        if (!box || !target.closest) return;
        if (target.closest('[data-sl-pin]')) return setDockPin(target.closest('[data-sl-pin]'));
        if (target.closest('[data-sl-dock-turn]')) return setDockDir(box.getAttribute('data-dir') === 'col' ? 'row' : 'col');
        if (target.closest('[data-sl-dock-dir]')) return setDockDir(target.closest('[data-sl-dock-dir]').getAttribute('data-sl-dock-dir'));
        if (target.closest('[data-sl-dock-home]')) return setDockSide(getDockSide());
        if (target.closest('.sl-dock-pop [data-sl-dock-fold]')) {
            setDockFold(true);
            return box.querySelector('.sl-dock-tools [data-sl-dock-fold]').focus({ preventScroll: true });
        }
        if (target.closest('[data-sl-dock-fold]')) return setDockFold(!box.hasAttribute('data-min'));
        btn = target.closest('[data-sl-dock-pop]');
        if (btn) return setDockPop(btn, event.detail === 0);
        if (!target.closest('.sl-dock-pop')) setDockClose();
    });
    // М14 The growth of the icons is switched in the admin card and kept in the browser
    document.addEventListener('change', function (event) {
        if (event.target.matches && event.target.matches('[data-sl-dock-mag]')) setDockKept('mag', event.target.checked ? '1' : '0');
    });
    // С9 The search of all the sections filters as it is typed
    document.addEventListener('input', function (event) {
        if (event.target.matches && event.target.matches('[data-sl-dock-find]')) setDockFind(event.target);
    });
    // М5 WCAG 2.5.7: the arrows on the grip move the layer, with Shift the long step, Home sends it back to its edge; Escape shuts a pop
    document.addEventListener('keydown', function (event) {
        var box = document.querySelector('[data-sl-dock]');
        var step = event.shiftKey ? 96 : 24;
        var move, rect;
        if (!box) return;
        if (event.key === 'Escape' && box.hasAttribute('data-open')) {
            event.preventDefault();
            return setDockClose();
        }
        if (!event.target.matches || !event.target.matches('[data-sl-dock-grip]')) return;
        if (event.key === 'Home') {
            event.preventDefault();
            return setDockSide(getDockSide());
        }
        move = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[event.key];
        if (!move) return;
        event.preventDefault();
        rect = box.getBoundingClientRect();
        setDockAt(rect.left + rect.width / 2 + move[0], rect.top + rect.height / 2 + move[1], true);
    });
    window.setOptionSend = setOptionSend;
    window.setDockSide = setDockSide;
    window.setDockPlace = setDockPlace;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setDock);
    else setDock();
})();
