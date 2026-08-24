(function () {
    // Demo stand for the eight replacements of the admin home page. On a real page the module set is written by
    // getAdminPanel() and every choice is a request answered by PHP; a static file has no server, so the rows are
    // rendered once by the generator and this script only decides which of them the page is showing right now
    var PINS = 'sl-demo-home-pins';

    // Read the query the way a person types it: case and the space around it never decide a match
    function getNeedle(node) {
        return (node && node.value ? node.value : '').trim().toLowerCase();
    }

    // Paint the part of a name the query matched, so a long list says why each row survived the filter
    function setRowMark(row, need) {
        var name = row.getAttribute('data-sl-name') || '';
        var hold = row.querySelector('b');
        var at;
        if (!hold) return;
        at = need ? name.toLowerCase().indexOf(need) : -1;
        if (at < 0) {
            hold.textContent = name;
            return;
        }
        hold.textContent = '';
        hold.appendChild(document.createTextNode(name.slice(0, at)));
        var hit = document.createElement('span');
        hit.className = 'sl-cmd-mark';
        hit.textContent = name.slice(at, at + need.length);
        hold.appendChild(hit);
        hold.appendChild(document.createTextNode(name.slice(at + need.length)));
    }

    // The command list: with an empty field it shows the handful of modules the panel was last in, and nothing else
    function setCmdShow(root) {
        var need = getNeedle(root.querySelector('[data-sl-cmd-field]'));
        var rows = root.querySelectorAll('[data-sl-cmd-res] .sl-cmd-row');
        var none = root.querySelector('[data-sl-cmd-empty]');
        var cap = root.querySelector('[data-sl-cmd-cap]');
        var seen = 0;
        var i;
        for (i = 0; i < rows.length; i++) {
            var row = rows[i];
            var find = row.getAttribute('data-sl-find') || '';
            var show = need ? find.indexOf(need) > -1 : row.hasAttribute('data-sl-recent');
            row.hidden = !show;
            row.classList.remove('sl-is-active');
            if (show) {
                setRowMark(row, need);
                seen++;
            }
        }
        if (none) none.hidden = seen > 0;
        if (cap) cap.textContent = need ? 'Найдено: ' + seen : 'Недавние';
        var first = root.querySelector('[data-sl-cmd-res] .sl-cmd-row:not([hidden])');
        if (first) first.classList.add('sl-is-active');
    }

    // Up and down walk the visible rows only, and Enter opens the one the walk is standing on
    function setCmdKey(root, event) {
        var rows = Array.prototype.filter.call(root.querySelectorAll('[data-sl-cmd-res] .sl-cmd-row'), function (row) {
            return !row.hidden;
        });
        var at = rows.findIndex(function (row) {
            return row.classList.contains('sl-is-active');
        });
        if (!rows.length) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            var next = event.key === 'ArrowDown' ? at + 1 : at - 1;
            if (next < 0) next = rows.length - 1;
            if (next >= rows.length) next = 0;
            rows.forEach(function (row) { row.classList.remove('sl-is-active'); });
            rows[next].classList.add('sl-is-active');
            rows[next].scrollIntoView({ block: 'nearest' });
            return;
        }
        if (event.key === 'Enter' && at > -1) {
            event.preventDefault();
            rows[at].click();
        }
    }

    // The table of the third variant: one field and one select decide which of the fifty rows stay in the body
    function setListShow(root) {
        var need = getNeedle(root.querySelector('[data-sl-lst-field]'));
        var pick = root.querySelector('[data-sl-lst-sec]');
        var sec = pick ? pick.value : '';
        var rows = root.querySelectorAll('tbody tr');
        var out = root.querySelector('[data-sl-lst-count]');
        var seen = 0;
        for (var i = 0; i < rows.length; i++) {
            var find = rows[i].getAttribute('data-sl-find') || '';
            var mine = rows[i].getAttribute('data-sl-sec') || '';
            var show = (!need || find.indexOf(need) > -1) && (!sec || mine === sec);
            rows[i].hidden = !show;
            if (show) seen++;
        }
        if (out) out.textContent = seen + ' из ' + rows.length;
    }

    // The pinned set the sixth variant remembers between reloads; on a real panel this is a column on _admins
    function getPins() {
        var raw = null;
        try {
            raw = window.localStorage.getItem(PINS);
        } catch (err) {
            raw = null;
        }
        if (raw === null) return ['news', 'pages', 'account', 'comments', 'monitor', 'config'];
        return raw ? raw.split(',') : [];
    }

    function setPins(list) {
        try {
            window.localStorage.setItem(PINS, list.join(','));
        } catch (err) {
            void err;
        }
    }

    // Both lists carry every module from the start, so pinning one is a change of state and never a rebuild of markup
    function setPinShow(root) {
        var list = getPins();
        var tiles = root.querySelectorAll('[data-sl-pin-key]');
        var none = root.querySelector('[data-sl-pin-empty]');
        for (var i = 0; i < tiles.length; i++) {
            var on = list.indexOf(tiles[i].getAttribute('data-sl-pin-key')) > -1;
            var want = tiles[i].hasAttribute('data-sl-pin-tile') ? on : !on;
            tiles[i].hidden = !want;
            var star = tiles[i].querySelector('.sl-pin-star');
            if (star) star.classList.toggle('sl-is-on', on);
        }
        if (none) none.hidden = list.length > 0;
    }

    function setPinTake(root, key) {
        var list = getPins();
        var at = list.indexOf(key);
        if (at > -1) list.splice(at, 1); else list.push(key);
        setPins(list);
        setPinShow(root);
    }

    document.addEventListener('input', function (event) {
        var node = event.target;
        if (!node || !node.closest) return;
        var cmd = node.hasAttribute('data-sl-cmd-field') ? node.closest('[data-sl-cmd]') : null;
        if (cmd) setCmdShow(cmd);
        var lst = node.hasAttribute('data-sl-lst-field') ? node.closest('[data-sl-lst]') : null;
        if (lst) setListShow(lst);
    });

    document.addEventListener('change', function (event) {
        var node = event.target;
        if (!node || !node.closest || !node.hasAttribute('data-sl-lst-sec')) return;
        var lst = node.closest('[data-sl-lst]');
        if (lst) setListShow(lst);
    });

    document.addEventListener('keydown', function (event) {
        var node = event.target;
        var cmd = node && node.closest ? node.closest('[data-sl-cmd]') : null;
        // The shortcut of the panel comes first: it has to answer from inside the field of the page as well, or the
        // one place where a person is already typing a module name is the one place the chord goes silent
        if ((event.ctrlKey || event.metaKey) && (event.key === 'k' || event.key === 'K')) {
            var win = document.getElementById('sl-cmd-win');
            if (!win || !window.setWindowOpen) return;
            event.preventDefault();
            window.setWindowOpen(win);
            var field = win.querySelector('[data-sl-cmd-field]');
            if (field) field.select();
            return;
        }
        if (cmd && node.hasAttribute('data-sl-cmd-field')) setCmdKey(cmd, event);
    });

    document.addEventListener('click', function (event) {
        var star = event.target && event.target.closest ? event.target.closest('[data-sl-pin-act]') : null;
        if (!star) return;
        event.preventDefault();
        var host = star.closest('[data-sl-pin-key]');
        var root = star.closest('[data-sl-pin]');
        if (host && root) setPinTake(root, host.getAttribute('data-sl-pin-key'));
    });

    document.querySelectorAll('[data-sl-cmd]').forEach(function (root) { setCmdShow(root); });
    document.querySelectorAll('[data-sl-lst]').forEach(function (root) { setListShow(root); });
    document.querySelectorAll('[data-sl-pin]').forEach(function (root) { setPinShow(root); });
})();
