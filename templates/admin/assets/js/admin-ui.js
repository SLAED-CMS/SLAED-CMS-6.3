(function () {
    'use strict';
    var picker = null;
    var names = null;
    var icontags = null;
    var iconterms = null;
    function getIconWords() {
        if (icontags !== null) return;
        icontags = {};
        iconterms = {};
        var base = 'templates/admin/assets/js/i18n/';
        fetch(base + 'icon-tags.json').then(function (res) { return res.json(); }).then(function (json) { icontags = json; }).catch(function () {});
        var lang = (document.documentElement.lang || 'en').slice(0, 2);
        if (lang !== 'en') {
            fetch(base + 'icon-terms-' + lang + '.json').then(function (res) { return res.json(); }).then(function (json) { iconterms = json; }).catch(function () {});
        }
    }
    function getIconNames() {
        if (names) return names;
        names = [];
        var sheets = document.styleSheets;
        for (var i = 0; i < sheets.length; i++) {
            var rules = null;
            try { rules = sheets[i].cssRules; } catch (e) { continue; }
            if (!rules) continue;
            for (var j = 0; j < rules.length; j++) {
                var sel = rules[j].selectorText || '';
                var m = sel.match(/^\.bi-([a-z0-9-]+)::before$/);
                if (m) names.push(m[1]);
            }
        }
        names.sort();
        return names;
    }
    function setIconGrid(modal) {
        var grid = modal.querySelector('[data-sl-icon-grid]');
        if (!grid || grid.childElementCount) return;
        var frag = document.createDocumentFragment();
        getIconNames().forEach(function (name) {
            var cell = document.createElement('button');
            cell.type = 'button';
            cell.className = 'sl-icon-cell';
            cell.title = name;
            cell.setAttribute('data-sl-icon-name', name);
            cell.innerHTML = '<i class="bi bi-' + name + '"></i><span>' + name + '</span>';
            frag.appendChild(cell);
        });
        grid.appendChild(frag);
    }
    function setIconCurrent(modal) {
        var value = picker ? picker.querySelector('[data-sl-icon-input]').value.trim() : '';
        modal.querySelectorAll('.sl-icon-cell').forEach(function (cell) {
            cell.classList.toggle('sl-is-active', cell.getAttribute('data-sl-icon-name') === value);
        });
        var active = modal.querySelector('.sl-icon-cell.sl-is-active');
        if (active) active.scrollIntoView({ block: 'center' });
    }
    function setIconValue(name) {
        if (!picker) return;
        var input = picker.querySelector('[data-sl-icon-input]');
        var preview = picker.querySelector('[data-sl-icon-preview]');
        if (input) input.value = name;
        if (preview) preview.className = 'bi bi-' + name;
    }
    document.addEventListener('click', function (event) {
        var node = event.target;
        if (!node || !node.closest) return;
        var opener = node.closest('[data-sl-icon-open]');
        if (opener) {
            var modal = document.getElementById('sl_icon_modal');
            if (!modal) return;
            picker = opener.closest('.sl-icon-picker');
            setIconGrid(modal);
            getIconWords();
            var search = modal.querySelector('[data-sl-icon-search]');
            if (search) search.value = '';
            modal.querySelectorAll('.sl-icon-cell').forEach(function (cell) { cell.hidden = false; });
            modal.showModal();
            setIconCurrent(modal);
            if (search) search.focus();
            return;
        }
        var cell = node.closest('.sl-icon-cell');
        if (cell) {
            setIconValue(cell.getAttribute('data-sl-icon-name'));
            cell.closest('dialog').close();
            return;
        }
        if (node.closest('[data-sl-icon-close]')) {
            node.closest('dialog').close();
            return;
        }
        var dialog = node.closest('dialog.sl-icon-modal');
        if (dialog && node === dialog) dialog.close();
    });
    document.addEventListener('input', function (event) {
        var node = event.target;
        if (!node || !node.closest) return;
        if (node.matches('[data-sl-icon-search]')) {
            var term = node.value.trim().toLowerCase();
            var list = [];
            if (term !== '') {
                Object.keys(iconterms || {}).forEach(function (word) {
                    if (word.indexOf(term) === 0 && word.length - term.length <= 3) list = list.concat(iconterms[word].split(' '));
                });
            }
            node.closest('dialog').querySelectorAll('.sl-icon-cell').forEach(function (cell) {
                var name = cell.getAttribute('data-sl-icon-name');
                var words = name + ' ' + ((icontags || {})[name] || '');
                var flat = ' ' + words.replace(/-/g, ' ') + ' ';
                var show = words.indexOf(term) !== -1 || list.some(function (w) { return w && flat.indexOf(' ' + w + ' ') !== -1; });
                cell.hidden = term !== '' && !show;
            });
            return;
        }
        if (node.matches('[data-sl-icon-input]')) {
            var wrap = node.closest('.sl-icon-picker');
            var preview = wrap ? wrap.querySelector('[data-sl-icon-preview]') : null;
            if (preview) preview.className = 'bi bi-' + node.value.trim();
        }
    });
    var drag = null;
    function getDragTable(node) {
        return node && node.closest ? node.closest('table[data-sl-drag-url]') : null;
    }
    function getDragRow(node) {
        return node && node.closest ? node.closest('tr[data-sl-drag-id]') : null;
    }
    function getDragRows(row) {
        var rows = [row];
        var ids = [row.getAttribute('data-sl-drag-id')];
        var next = row.nextElementSibling;
        while (next && next.hasAttribute && next.hasAttribute('data-sl-drag-id')) {
            if (next.hasAttribute('data-sl-drag-parent') && ids.indexOf(next.getAttribute('data-sl-drag-parent')) !== -1) {
                rows.push(next);
                ids.push(next.getAttribute('data-sl-drag-id'));
                next = next.nextElementSibling;
            } else break;
        }
        return rows;
    }
    function setDropMarksOff(table) {
        table.querySelectorAll('.sl-drop-above, .sl-drop-below, .sl-drop-deny').forEach(function (row) {
            row.classList.remove('sl-drop-above', 'sl-drop-below', 'sl-drop-deny');
        });
    }
    document.addEventListener('mousedown', function (event) {
        var handle = event.target && event.target.closest ? event.target.closest('.sl-drag-handle') : null;
        if (!handle) return;
        var row = getDragRow(handle);
        if (row) row.draggable = true;
    });
    document.addEventListener('dragstart', function (event) {
        var row = getDragRow(event.target);
        if (!row || !row.draggable || !getDragTable(row)) return;
        drag = row;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', row.getAttribute('data-sl-drag-id'));
        setTimeout(function () { getDragRows(row).forEach(function (item) { item.classList.add('sl-dragging'); }); });
    });
    document.addEventListener('dragover', function (event) {
        if (!drag) return;
        var row = getDragRow(event.target);
        var table = getDragTable(row);
        if (!row || row === drag || !table) return;
        setDropMarksOff(table);
        if (row.getAttribute('data-sl-drag-group') !== drag.getAttribute('data-sl-drag-group')) {
            event.dataTransfer.dropEffect = 'none';
            row.classList.add('sl-drop-deny');
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        var before = event.clientY < row.getBoundingClientRect().top + row.offsetHeight / 2;
        row.classList.add(before ? 'sl-drop-above' : 'sl-drop-below');
    });
    document.addEventListener('drop', function (event) {
        if (!drag) return;
        var row = getDragRow(event.target);
        var table = getDragTable(row);
        if (!row || row === drag || !table) return;
        if (row.getAttribute('data-sl-drag-group') !== drag.getAttribute('data-sl-drag-group')) return;
        event.preventDefault();
        var moving = getDragRows(drag);
        if (moving.indexOf(row) !== -1) return;
        var before = event.clientY < row.getBoundingClientRect().top + row.offsetHeight / 2;
        var family = getDragRows(row);
        var anchor = before ? row : family[family.length - 1].nextSibling;
        var parent = row.parentNode;
        moving.forEach(function (item) { parent.insertBefore(item, anchor); });
        var ids = [];
        table.querySelectorAll('tr[data-sl-drag-scope="' + drag.getAttribute('data-sl-drag-scope') + '"]').forEach(function (item) {
            ids.push(item.getAttribute('data-sl-drag-id'));
        });
        var saved = drag.getAttribute('data-sl-drag-id');
        var target = table.getAttribute('data-sl-drag-target');
        fetch(table.getAttribute('data-sl-drag-url'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'ids=' + ids.join('-')
        }).then(function (reply) { return reply.text(); }).then(function (html) {
            var box = document.getElementById(target);
            if (!box) return;
            box.innerHTML = html;
            setGroupStates(box);
            var flash = box.querySelector('tr[data-sl-drag-id="' + saved + '"]');
            if (!flash) return;
            flash.classList.add('sl-row-saved');
            setTimeout(function () { flash.classList.remove('sl-row-saved'); }, 900);
        });
    });
    document.addEventListener('dragend', function () {
        if (!drag) return;
        getDragRows(drag).forEach(function (item) { item.classList.remove('sl-dragging'); });
        drag.draggable = false;
        var table = getDragTable(drag);
        if (table) setDropMarksOff(table);
        drag = null;
    });
    function setGroupRows(head, closed) {
        head.classList.toggle('sl-is-closed', closed);
        var next = head.nextElementSibling;
        while (next && next.hasAttribute && !next.hasAttribute('data-sl-group')) {
            next.classList.toggle('sl-row-collapsed', closed);
            next = next.nextElementSibling;
        }
    }
    function setGroupStates(root) {
        (root || document).querySelectorAll('tr[data-sl-group]').forEach(function (head) {
            var closed = false;
            try { closed = window.localStorage.getItem('sl-group-' + head.getAttribute('data-sl-group')) === '0'; } catch (err) {}
            if (closed) setGroupRows(head, true);
        });
    }
    document.addEventListener('click', function (event) {
        var head = event.target && event.target.closest ? event.target.closest('tr[data-sl-group]') : null;
        if (!head) return;
        var closed = !head.classList.contains('sl-is-closed');
        setGroupRows(head, closed);
        try { window.localStorage.setItem('sl-group-' + head.getAttribute('data-sl-group'), closed ? '0' : '1'); } catch (err) {}
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setGroupStates(document); });
    } else {
        setGroupStates(document);
    }
})();
