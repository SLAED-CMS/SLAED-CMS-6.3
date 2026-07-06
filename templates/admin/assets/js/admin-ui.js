(function () {
    'use strict';
    var picker = null;
    var names = null;
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
        var toggle = node.closest('.sl-dial-toggle');
        document.querySelectorAll('.sl-dial.sl-open').forEach(function (dial) {
            if (!toggle || dial !== toggle.parentNode) dial.classList.remove('sl-open');
        });
        if (toggle) toggle.parentNode.classList.toggle('sl-open');
        var opener = node.closest('[data-sl-icon-open]');
        if (opener) {
            var modal = document.getElementById('sl_icon_modal');
            if (!modal) return;
            picker = opener.closest('.sl-icon-picker');
            setIconGrid(modal);
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
            node.closest('dialog').querySelectorAll('.sl-icon-cell').forEach(function (cell) {
                cell.hidden = term !== '' && cell.getAttribute('data-sl-icon-name').indexOf(term) === -1;
            });
            return;
        }
        if (node.matches('[data-sl-icon-input]')) {
            var wrap = node.closest('.sl-icon-picker');
            var preview = wrap ? wrap.querySelector('[data-sl-icon-preview]') : null;
            if (preview) preview.className = 'bi bi-' + node.value.trim();
        }
    });
})();
