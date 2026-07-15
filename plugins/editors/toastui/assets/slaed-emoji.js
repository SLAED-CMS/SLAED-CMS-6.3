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
                ['😀', 'grin smile happy'],
                ['😃', 'smile happy'],
                ['😄', 'laugh smile'],
                ['😁', 'grin teeth'],
                ['😆', 'laugh'],
                ['😂', 'joy laugh'],
                ['🤣', 'rofl laugh'],
                ['😊', 'smile blush'],
                ['🙂', 'smile'],
                ['😉', 'wink'],
                ['😍', 'heart love'],
                ['😘', 'kiss love'],
                ['😎', 'cool sunglasses'],
                ['🤔', 'think question'],
                ['😐', 'neutral'],
                ['😮', 'surprise'],
                ['😢', 'sad cry'],
                ['😭', 'cry'],
                ['😡', 'angry'],
                ['🥳', 'party'],
                ['😇', 'angel'],
                ['🙃', 'upside down'],
                ['😋', 'yum tasty'],
                ['😛', 'tongue'],
                ['😜', 'wink tongue'],
                ['🤪', 'crazy'],
                ['🤨', 'doubt'],
                ['🧐', 'monocle'],
                ['🤓', 'nerd'],
                ['🥸', 'disguise'],
                ['🤩', 'star eyes'],
                ['🥰', 'love hearts'],
                ['🤗', 'hug'],
                ['🤭', 'oops'],
                ['🤫', 'quiet'],
                ['🤥', 'lie'],
                ['😶', 'silent'],
                ['😏', 'smirk'],
                ['😬', 'grimace'],
                ['🙄', 'eyeroll'],
                ['😴', 'sleep'],
                ['🤤', 'drool'],
                ['🤐', 'zip mouth'],
                ['🤢', 'sick'],
                ['🤮', 'vomit'],
                ['🤧', 'sneeze'],
                ['🥶', 'cold'],
                ['🥵', 'hot'],
                ['😵', 'dizzy'],
                ['🤯', 'mind blown'],
                ['😱', 'shock'],
                ['😳', 'blush'],
                ['🥺', 'please'],
                ['😈', 'devil'],
                ['💀', 'skull'],
                ['👻', 'ghost'],
                ['🤖', 'robot']
            ]
        },
        reactions: {
            label: 'Reactions',
            rows: [
                ['👍', 'like thumbs up'],
                ['👎', 'dislike thumbs down'],
                ['👏', 'clap applause'],
                ['🙌', 'raise hands'],
                ['🙏', 'thanks pray'],
                ['💪', 'strong'],
                ['🤝', 'handshake'],
                ['👌', 'ok'],
                ['✌️', 'peace'],
                ['🤞', 'luck'],
                ['👀', 'eyes look'],
                ['💯', 'hundred'],
                ['🔥', 'fire hot'],
                ['✨', 'sparkles'],
                ['⭐', 'star'],
                ['🎉', 'party celebration'],
                ['✅', 'check done'],
                ['❌', 'cross no'],
                ['⚠️', 'warning'],
                ['❗', 'important'],
                ['❓', 'question'],
                ['❔', 'question light'],
                ['‼️', 'double important'],
                ['⁉️', 'question important'],
                ['🔴', 'red circle'],
                ['🟠', 'orange circle'],
                ['🟡', 'yellow circle'],
                ['🟢', 'green circle'],
                ['🔵', 'blue circle'],
                ['🟣', 'purple circle'],
                ['⚫', 'black circle'],
                ['⚪', 'white circle'],
                ['🟩', 'green square'],
                ['🟥', 'red square'],
                ['🟨', 'yellow square'],
                ['🟦', 'blue square'],
                ['🏆', 'trophy'],
                ['🥇', 'gold medal'],
                ['🥈', 'silver medal'],
                ['🥉', 'bronze medal'],
                ['🎯', 'target'],
                ['📈', 'chart up'],
                ['📉', 'chart down'],
                ['💥', 'boom'],
                ['💫', 'dizzy sparkle'],
                ['🌟', 'glowing star'],
                ['☀️', 'sun'],
                ['🌙', 'moon'],
                ['🌈', 'rainbow'],
                ['☕', 'coffee'],
                ['🍕', 'pizza'],
                ['🍺', 'beer'],
                ['🎁', 'gift'],
                ['💎', 'diamond']
            ]
        },
        notices: {
            label: 'Notices',
            rows: [
                ['📌', 'pin'],
                ['📎', 'clip attachment'],
                ['📝', 'note edit'],
                ['📣', 'announce'],
                ['🔔', 'bell notice'],
                ['🔒', 'lock private'],
                ['🔓', 'unlock'],
                ['🛠️', 'tools'],
                ['💡', 'idea'],
                ['📅', 'calendar date'],
                ['⏰', 'time alarm'],
                ['📍', 'location'],
                ['📁', 'folder'],
                ['📄', 'file document'],
                ['🖼️', 'image'],
                ['🎧', 'audio'],
                ['🎬', 'video'],
                ['🧩', 'plugin'],
                ['🚀', 'launch'],
                ['🧪', 'test'],
                ['📊', 'statistics'],
                ['📋', 'clipboard'],
                ['✅', 'task done'],
                ['☑️', 'checkbox'],
                ['📦', 'package'],
                ['🗂️', 'index tabs'],
                ['🗃️', 'archive'],
                ['🧾', 'receipt'],
                ['📰', 'news'],
                ['🏷️', 'tag label'],
                ['🔖', 'bookmark'],
                ['🔎', 'inspect'],
                ['🔧', 'wrench'],
                ['⚙️', 'settings'],
                ['🧰', 'toolbox'],
                ['🗑️', 'trash'],
                ['💾', 'save'],
                ['💿', 'disk'],
                ['🖨️', 'print'],
                ['💻', 'computer'],
                ['🖥️', 'desktop'],
                ['📱', 'mobile'],
                ['🌐', 'web'],
                ['🔐', 'secure'],
                ['🛡️', 'shield'],
                ['👤', 'user'],
                ['👥', 'users'],
                ['🏠', 'home'],
                ['🏢', 'office'],
                ['🚧', 'work'],
                ['⛔', 'blocked'],
                ['📤', 'upload'],
                ['📥', 'download'],
                ['🧭', 'navigation']
            ]
        },
        symbols: {
            label: 'Symbols',
            rows: [
                ['❤️', 'heart love'],
                ['🧡', 'orange heart'],
                ['💛', 'yellow heart'],
                ['💚', 'green heart'],
                ['💙', 'blue heart'],
                ['💜', 'purple heart'],
                ['🖤', 'black heart'],
                ['🤍', 'white heart'],
                ['➕', 'plus'],
                ['➖', 'minus'],
                ['➡️', 'right arrow'],
                ['⬅️', 'left arrow'],
                ['⬆️', 'up arrow'],
                ['⬇️', 'down arrow'],
                ['↩️', 'return back'],
                ['🔗', 'link'],
                ['🔍', 'search'],
                ['💬', 'comment'],
                ['📞', 'phone'],
                ['✉️', 'mail'],
                ['☑️', 'checked'],
                ['✔️', 'check'],
                ['✖️', 'x'],
                ['➰', 'loop'],
                ['➿', 'double loop'],
                ['♻️', 'recycle'],
                ['©️', 'copyright'],
                ['®️', 'registered'],
                ['™️', 'trademark'],
                ['ℹ️', 'info'],
                ['🔢', 'numbers'],
                ['#️⃣', 'hash'],
                ['*️⃣', 'asterisk'],
                ['0️⃣', 'zero'],
                ['1️⃣', 'one'],
                ['2️⃣', 'two'],
                ['3️⃣', 'three'],
                ['4️⃣', 'four'],
                ['5️⃣', 'five'],
                ['6️⃣', 'six'],
                ['7️⃣', 'seven'],
                ['8️⃣', 'eight'],
                ['9️⃣', 'nine'],
                ['🔼', 'up button'],
                ['🔽', 'down button'],
                ['◀️', 'left button'],
                ['▶️', 'right button'],
                ['⏪', 'rewind'],
                ['⏩', 'forward'],
                ['⏫', 'fast up'],
                ['⏬', 'fast down'],
                ['🔄', 'reload'],
                ['🔁', 'repeat'],
                ['🔀', 'shuffle']
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

    function esc(text) {
        return String(text || '').replace(/[&<>"']/g, function(chr) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[chr];
        });
    }

    function addButton(row) {
        return '<button type="button" class="slaed-emoji-item" data-emoji="' + esc(row[0]) + '" title="' + esc(row[1]) + '">' + row[0] + '</button>';
    }

    function findRows(term) {
        var needle = String(term || '').toLowerCase();
        if (!needle) return cat === 'recent' ? getRecent().map(function(row) { return [row, 'recent']; }) : data[cat].rows;
        return getRows().filter(function(row) {
            return row[0] === needle || row[1].toLowerCase().indexOf(needle) !== -1;
        });
    }

    function renderTabs(id) {
        var rec = getRecent();
        var html = rec.length
            ? '<button type="button" class="slaed-emoji-tab" data-cat="recent">' + esc(getLab(id, 'emoji_recent', 'Recent')) + '</button>'
            : '';
        Object.keys(data).forEach(function(name) {
            html += '<button type="button" class="slaed-emoji-tab" data-cat="' + name + '">' + esc(getCatLabel(id, name)) + '</button>';
        });
        return html.replace('data-cat="' + cat + '"', 'data-cat="' + cat + '" aria-current="true"');
    }

    function sizePanel() {
        var tabs = panel.querySelector('.slaed-emoji-tabs');
        var min = 360;
        var max = Math.max(280, win.innerWidth - 24);
        var width = Math.max(min, tabs.scrollWidth + 18);
        panel.style.width = Math.min(width, max) + 'px';
    }

    function render(id) {
        var search = panel.querySelector('.slaed-emoji-search');
        var tabs = panel.querySelector('.slaed-emoji-tabs');
        var grid = panel.querySelector('.slaed-emoji-grid');
        var rows = findRows(search ? search.value : '');
        tabs.innerHTML = renderTabs(id);
        grid.innerHTML = rows.length ? rows.map(addButton).join('') : '<div class="slaed-emoji-empty">' + esc(getLab(id, 'emoji_empty', 'No emoji')) + '</div>';
        Array.prototype.forEach.call(tabs.querySelectorAll('.slaed-emoji-tab'), function(btn) {
            btn.classList.toggle('active', btn.getAttribute('data-cat') === cat);
        });
        sizePanel();
    }

    function makePanel(id) {
        if (panel) return panel;
        panel = doc.createElement('div');
        panel.className = 'slaed-emoji-panel sl-none';
        panel.innerHTML = '<input type="search" class="slaed-emoji-search" aria-label="' + esc(getLab(id, 'emoji', 'Emoji')) + '">'
            + '<div class="slaed-emoji-tabs"></div><div class="slaed-emoji-grid"></div>';
        doc.body.appendChild(panel);
        return panel;
    }

    function place(id) {
        var root = doc.getElementById(id + '_toast');
        var btn = root ? root.querySelector('.toastui-editor-toolbar-icons.slaed-bi-emoji') : null;
        var left;
        var box;
        if (!btn) btn = doc.querySelector('.toastui-editor-toolbar-icons.slaed-bi-emoji');
        if (!panel || !btn) return;
        box = btn.getBoundingClientRect();
        left = Math.max(8, box.left + win.scrollX - 160);
        left = Math.min(left, win.scrollX + win.innerWidth - panel.offsetWidth - 8);
        panel.style.left = Math.max(8, left) + 'px';
        panel.style.top = (box.bottom + win.scrollY + 6) + 'px';
        panel.setAttribute('data-editor', id);
    }

    function toggle(id) {
        makePanel(id);
        active = String(id);
        if (cat === 'recent' && !getRecent().length) cat = 'smileys';
        panel.classList.toggle('sl-none', panel.getAttribute('data-editor') === active && !panel.classList.contains('sl-none'));
        if (panel.classList.contains('sl-none')) return;
        render(id);
        place(id);
        if (api.syncWindow) api.syncWindow(id, 'emoji');
        panel.querySelector('.slaed-emoji-search').focus();
    }

    function hide() {
        if (panel) panel.classList.add('sl-none');
        if (active && api.syncWindow) api.syncWindow(active, 'emoji');
    }

    doc.addEventListener('input', function(ev) {
        if (!panel || ev.target !== panel.querySelector('.slaed-emoji-search')) return;
        render(active);
    });

    doc.addEventListener('click', function(ev) {
        var el = ev.target;
        var head;
        if (!panel || panel.classList.contains('sl-none')) return;
        head = el.closest ? el.closest('[data-window-head="emoji"]') : null;
        if (el.classList && el.classList.contains('slaed-emoji-tab')) {
            cat = el.getAttribute('data-cat') || 'smileys';
            panel.querySelector('.slaed-emoji-search').value = '';
            render(active);
            return;
        }
        if (el.classList && el.classList.contains('slaed-emoji-item')) {
            api.insertText(active, el.getAttribute('data-emoji') || '');
            setRecent(el.getAttribute('data-emoji') || '');
            render(active);
            return;
        }
        if (!panel.contains(el) && !head && !el.classList.contains('slaed-bi-emoji')) hide();
    });

    doc.addEventListener('keydown', function(ev) {
        if (ev.key === 'Escape') hide();
    });

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
            className: 'toastui-editor-toolbar-icons slaed-bi slaed-bi-emoji',
            tooltip: getLab(id, 'emoji', 'Emoji'),
            command: 'slaedEmoji'
        });
    };
    win.SlaedToastUi = api;
})(window, document);
