(function(win, doc) {
    'use strict';

    var api = win.SlaedToastUi || {};
    var key = 'slaed_toastui_recent_emoji';
    var active = '';
    var panel = null;
    var cat = 'recent';
    var data = {
        smileys: {
            label: 'Smileys',
            rows: [
                '😀',
                '😃',
                '😄',
                '😁',
                '😆',
                '😂',
                '🤣',
                '😊',
                '🙂',
                '😉',
                '😍',
                '😘',
                '😎',
                '🤔',
                '😐',
                '😮',
                '😢',
                '😭',
                '😡',
                '🥳',
                '😇',
                '🙃',
                '😋',
                '😛',
                '😜',
                '🤪',
                '🤨',
                '🧐',
                '🤓',
                '🥸',
                '🤩',
                '🥰',
                '🤗',
                '🤭',
                '🤫',
                '🤥',
                '😶',
                '😏',
                '😬',
                '🙄',
                '😴',
                '🤤',
                '🤐',
                '🤢',
                '🤮',
                '🤧',
                '🥶',
                '🥵',
                '😵',
                '🤯',
                '😱',
                '😳',
                '🥺',
                '😈',
                '💀',
                '👻',
                '🤖'
            ]
        },
        reactions: {
            label: 'Reactions',
            rows: [
                '👍',
                '👎',
                '👏',
                '🙌',
                '🙏',
                '💪',
                '🤝',
                '👌',
                '✌️',
                '🤞',
                '👀',
                '💯',
                '🔥',
                '✨',
                '⭐',
                '🎉',
                '✅',
                '❌',
                '⚠️',
                '❗',
                '❓',
                '❔',
                '‼️',
                '⁉️',
                '🔴',
                '🟠',
                '🟡',
                '🟢',
                '🔵',
                '🟣',
                '⚫',
                '⚪',
                '🟩',
                '🟥',
                '🟨',
                '🟦',
                '🏆',
                '🥇',
                '🥈',
                '🥉',
                '🎯',
                '📈',
                '📉',
                '💥',
                '💫',
                '🌟',
                '☀️',
                '🌙',
                '🌈',
                '☕',
                '🍕',
                '🍺',
                '🎁',
                '💎'
            ]
        },
        notices: {
            label: 'Notices',
            rows: [
                '📌',
                '📎',
                '📝',
                '📣',
                '🔔',
                '🔒',
                '🔓',
                '🛠️',
                '💡',
                '📅',
                '⏰',
                '📍',
                '📁',
                '📄',
                '🖼️',
                '🎧',
                '🎬',
                '🧩',
                '🚀',
                '🧪',
                '📊',
                '📋',
                '✅',
                '☑️',
                '📦',
                '🗂️',
                '🗃️',
                '🧾',
                '📰',
                '🏷️',
                '🔖',
                '🔎',
                '🔧',
                '⚙️',
                '🧰',
                '🗑️',
                '💾',
                '💿',
                '🖨️',
                '💻',
                '🖥️',
                '📱',
                '🌐',
                '🔐',
                '🛡️',
                '👤',
                '👥',
                '🏠',
                '🏢',
                '🚧',
                '⛔',
                '📤',
                '📥',
                '🧭'
            ]
        },
        symbols: {
            label: 'Symbols',
            rows: [
                '❤️',
                '🧡',
                '💛',
                '💚',
                '💙',
                '💜',
                '🖤',
                '🤍',
                '➕',
                '➖',
                '➡️',
                '⬅️',
                '⬆️',
                '⬇️',
                '↩️',
                '🔗',
                '🔍',
                '💬',
                '📞',
                '✉️',
                '☑️',
                '✔️',
                '✖️',
                '➰',
                '➿',
                '♻️',
                '©️',
                '®️',
                '™️',
                'ℹ️',
                '🔢',
                '#️⃣',
                '*️⃣',
                '0️⃣',
                '1️⃣',
                '2️⃣',
                '3️⃣',
                '4️⃣',
                '5️⃣',
                '6️⃣',
                '7️⃣',
                '8️⃣',
                '9️⃣',
                '🔼',
                '🔽',
                '◀️',
                '▶️',
                '⏪',
                '⏩',
                '⏫',
                '⏬',
                '🔄',
                '🔁',
                '🔀'
            ]
        }
    };

    function getOpt(id) {
        var opt = api.options || {};
        return opt[String(id)] || {};
    }

    function getLab(id, name, val) {
        var opt = getOpt(id);
        var lab = opt.labels || {};
        return lab[name] || val;
    }

    function getCatLabel(id, name) {
        return getLab(id, 'emoji_' + name, data[name] ? data[name].label : name);
    }

    function getRecent() {
        try {
            return JSON.parse(win.localStorage.getItem(key) || '[]').filter(Boolean);
        } catch (err) {
            return [];
        }
    }

    function setRecent(emoji) {
        var rows = getRecent().filter(function(row) {
            return row !== emoji;
        });
        rows.unshift(emoji);
        try {
            win.localStorage.setItem(key, JSON.stringify(rows.slice(0, 24)));
        } catch (err) {}
    }

    function getRows() {
        var rows = [];
        Object.keys(data).forEach(function(name) {
            rows = rows.concat(data[name].rows);
        });
        return rows;
    }

    function getWords(emoji) {
        var map = api.emojiWords || {};
        return String(map[emoji] || '').toLowerCase();
    }

    function getName(emoji) {
        return getWords(emoji).split('|')[0];
    }

    function findRows(term) {
        var needle = String(term || '').toLowerCase();
        if (!needle) return cat === 'recent' ? getRecent() : data[cat].rows;
        return getRows().filter(function(row) {
            return row === needle || getWords(row).indexOf(needle) !== -1;
        });
    }

    function addTab(id, name, label) {
        var btn = api.getTpl(id, 'emoji-tab');
        if (!btn) return null;
        btn.setAttribute('data-cat', name);
        btn.textContent = label;
        return btn;
    }

    // The width follows the row of category tabs, so a long row is not cut off and a short one leaves no gap
    // On a phone no width is written at all: there the canon lays the window edge to edge, and an inline width would leave the sheet short of the screen
    function sizePanel() {
        var tabs = panel.querySelector('.sl-editor-emoji-tabs');
        var min = 360;
        var max = Math.max(280, win.innerWidth - 24);
        var width;
        if (win.matchMedia('(max-width: 600px)').matches) {
            panel.style.width = '';
            return;
        }
        width = Math.max(min, tabs.scrollWidth + 18);
        panel.style.width = Math.min(width, max) + 'px';
    }

    function render(id) {
        var search = panel.querySelector('.sl-editor-emoji-search');
        var tabs = panel.querySelector('.sl-editor-emoji-tabs');
        var grid = panel.querySelector('.sl-editor-emoji-grid');
        var rows = findRows(search ? search.value : '');
        var rec = getRecent();
        var tab;
        var empty;
        tabs.replaceChildren();
        if (rec.length) {
            tab = addTab(id, 'recent', getLab(id, 'emoji_recent', 'Recent'));
            if (tab) tabs.appendChild(tab);
        }
        Object.keys(data).forEach(function(name) {
            var btn = addTab(id, name, getCatLabel(id, name));
            if (btn) tabs.appendChild(btn);
        });
        grid.replaceChildren();
        rows.forEach(function(row) {
            var btn = api.getTpl(id, 'emoji-item');
            if (!btn) return;
            btn.setAttribute('data-emoji', row);
            btn.title = getName(row);
            btn.textContent = row;
            grid.appendChild(btn);
        });
        if (!rows.length) {
            empty = api.getTpl(id, 'emoji-empty');
            if (empty) {
                empty.textContent = getLab(id, 'emoji_empty', 'No emoji');
                grid.appendChild(empty);
            }
        }
        Array.prototype.forEach.call(tabs.querySelectorAll('.sl-editor-emoji-tab'), function(btn) {
            var on = btn.getAttribute('data-cat') === cat;
            btn.classList.toggle('active', on);
            if (on) btn.setAttribute('aria-current', 'true');
        });
        sizePanel();
    }

    function makePanel(id) {
        if (panel) return panel;
        panel = api.getTpl(id, 'emoji-panel');
        if (!panel) return null;
        var search = panel.querySelector('.sl-editor-emoji-search');
        if (search) search.setAttribute('aria-label', getLab(id, 'emoji', 'Emoji'));
        doc.body.appendChild(panel);
        return panel;
    }

    // The panel stands at the button that opened it, because it is read against the text it writes into
    // On a phone it takes no coordinates: the canon lays every window as a sheet along the bottom edge, and an inline place would beat that rule and push the panel past it
    // The owner is written before that turn is taken, because a second press on the button closes the panel only while the panel knows whose it is
    function place(id) {
        var root = doc.getElementById(id + '_toast');
        var btn = root ? root.querySelector('.toastui-editor-toolbar-icons.sl-editor-icon-emoji') : null;
        var left;
        var top;
        var box;
        if (!panel) return;
        panel.setAttribute('data-editor', id);
        if (win.matchMedia('(max-width: 600px)').matches) return;
        if (!btn) btn = doc.querySelector('.toastui-editor-toolbar-icons.sl-editor-icon-emoji');
        if (!btn) return;
        box = btn.getBoundingClientRect();
        left = Math.max(8, box.left - 160);
        left = Math.min(left, win.innerWidth - panel.offsetWidth - 8);
        top = box.bottom + 6;
        // A window beside the page is fixed to the viewport and no longer scrolls away with the document: with no room under the button the panel opens above it
        if (top + panel.offsetHeight > win.innerHeight - 8) top = Math.max(8, box.top - panel.offsetHeight - 6);
        panel.style.left = Math.max(8, left) + 'px';
        panel.style.top = top + 'px';
    }

    function toggle(id) {
        makePanel(id);
        if (!panel) return;
        active = String(id);
        if (cat === 'recent' && !getRecent().length) cat = 'smileys';
        if (panel.open && panel.getAttribute('data-editor') === active) {
            win.setWindowClose(panel);
            return;
        }
        render(id);
        win.setWindowOpen(panel);
        place(id);
    }

    function hide() {
        if (panel) win.setWindowClose(panel);
    }

    doc.addEventListener('input', function(ev) {
        if (!panel || ev.target !== panel.querySelector('.sl-editor-emoji-search')) return;
        render(active);
    });

    doc.addEventListener('click', function(ev) {
        var el = ev.target;
        if (!panel || !panel.open) return;
        if (el.classList && el.classList.contains('sl-editor-emoji-tab')) {
            cat = el.getAttribute('data-cat') || 'smileys';
            panel.querySelector('.sl-editor-emoji-search').value = '';
            render(active);
            return;
        }
        if (el.classList && el.classList.contains('sl-editor-emoji-item')) {
            api.insertText(active, el.getAttribute('data-emoji') || '');
            setRecent(el.getAttribute('data-emoji') || '');
            render(active);
            return;
        }
        if (!panel.contains(el) && !el.classList.contains('sl-editor-icon-emoji')) hide();
    });

    api.getTpl = api.getTpl || function(id, name) {
        var opt = (api.options || {})[String(id)] || {};
        var root = doc.querySelector('.' + (opt.tpl || 'js-slaed-editor-tpl'));
        var tpl = root ? root.querySelector('template[data-tpl="' + name + '"]') : null;
        return tpl && tpl.content && tpl.content.firstElementChild ? tpl.content.firstElementChild.cloneNode(true) : null;
    };

    api.addEmoji = function(id, ed, opt) {
        if (!ed || typeof ed.addCommand !== 'function' || typeof ed.insertToolbarItem !== 'function') return;
        api.options = api.options || {};
        api.options[String(id)] = opt || getOpt(id);
        ed.addCommand('markdown', 'slaedEmoji', function() {
            toggle(id);
        });
        ed.addCommand('wysiwyg', 'slaedEmoji', function() {
            toggle(id);
        });
        ed.insertToolbarItem({ groupIndex: 6, itemIndex: 3 }, {
            name: 'slaedEmoji',
            text: '',
            className: 'toastui-editor-toolbar-icons sl-editor-icon sl-editor-icon-emoji',
            tooltip: getLab(id, 'emoji', 'Emoji'),
            command: 'slaedEmoji'
        });
    };
    win.SlaedToastUi = api;
})(window, document);
