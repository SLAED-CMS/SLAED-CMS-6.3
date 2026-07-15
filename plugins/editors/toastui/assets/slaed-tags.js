(function(win, doc) {
    'use strict';

    var api = win.SlaedToastUi || {};
    var map = api.editors || new Map();

    function getEditor(id) {
        return map.get(String(id)) || null;
    }

    function addText(ed, text) {
        if (!ed) return;
        ed.focus();
        ed.insertText(text);
    }

    function addWrap(ed, open, close, text) {
        var sel = '';
        if (!ed) return;
        ed.focus();
        if (typeof ed.getSelectedText === 'function') sel = ed.getSelectedText() || '';
        ed.replaceSelection(open + (sel || text || '') + close);
    }

    function addTabs(ed) {
        addText(ed, '[tabs=1]\n[tab=Title]Content[/tab]\n[/tabs]');
    }

    function addCmd(ed, name, call) {
        ed.addCommand('markdown', name, function() {
            call();
        });
        ed.addCommand('wysiwyg', name, function() {
            call();
        });
    }

    function addItem(ed, idx, name, icon, tip, group) {
        ed.insertToolbarItem({ groupIndex: typeof group === 'number' ? group : 6, itemIndex: idx }, {
            name: name,
            text: '',
            className: 'toastui-editor-toolbar-icons ' + icon,
            tooltip: tip,
            command: name
        });
    }

    function setFullscreen(id, open) {
        var box = doc.getElementById(String(id) + '_toast');
        var opt = api.options && api.options[String(id)] ? api.options[String(id)] : {};
        var txt = opt.labels || {};
        var button = box ? box.querySelector('.slaed-bi-fullscreen') : null;
        var tooltip = button ? button.querySelector('.toastui-editor-tooltip') : null;
        var active;
        var label;
        if (!box) return;
        active = typeof open === 'boolean' ? open : !box.classList.contains('sl-toastui-editor-fullscreen');
        box.classList.toggle('sl-toastui-editor-fullscreen', active);
        label = active ? (txt.exitfull || 'Exit full screen') : (txt.fullscreen || 'Full screen');
        if (button) {
            button.classList.toggle('sl-toastui-fullscreen-active', active);
            button.setAttribute('aria-label', label);
            button.setAttribute('data-tooltip', label);
            button.title = label;
        }
        if (tooltip) tooltip.textContent = label;
        doc.documentElement.classList.toggle('sl-toastui-page-locked', !!doc.querySelector('.sl-toastui-editor-fullscreen'));
        if (doc.body) doc.body.classList.toggle('sl-toastui-page-locked', !!doc.querySelector('.sl-toastui-editor-fullscreen'));
        setTimeout(function() { setWidth(id); }, 0);
    }

    function addTags(id, ed, opt) {
        var admin = !!(opt && opt.admin);
        var txt = opt && opt.labels ? opt.labels : {};
        if (!ed || typeof ed.addCommand !== 'function' || typeof ed.insertToolbarItem !== 'function') return;
        addCmd(ed, 'slaedFullscreen', function() {
            setFullscreen(id);
        });
        addItem(ed, 0, 'slaedFullscreen', 'slaed-bi slaed-bi-fullscreen', txt.fullscreen || 'Full screen', 0);
        addCmd(ed, 'slaedQuote', function() {
            addWrap(ed, '[quote]', '[/quote]', 'Quote');
        });
        addCmd(ed, 'slaedHide', function() {
            addWrap(ed, '[hide]', '[/hide]', 'Hidden text');
        });
        addCmd(ed, 'slaedTabs', function() {
            addTabs(ed);
        });
        addItem(ed, 0, 'slaedQuote', 'slaed-bi slaed-bi-quote', txt.quote || 'SLAED quote');
        addItem(ed, 1, 'slaedHide', 'slaed-bi slaed-bi-hide', txt.hide || 'SLAED hidden block');
        addItem(ed, 2, 'slaedTabs', 'slaed-bi slaed-bi-tabs', txt.tabs || 'SLAED tabs');
        if (!admin) return;
        addCmd(ed, 'slaedHtml', function() {
            addWrap(ed, '[usehtml]', '[/usehtml]', '<p>HTML</p>');
        });
        addCmd(ed, 'slaedPhp', function() {
            addWrap(ed, '[usephp]', '[/usephp]', 'echo "";');
        });
        addItem(ed, 5, 'slaedHtml', 'slaed-bi slaed-bi-html', txt.html || 'SLAED raw HTML');
        addItem(ed, 6, 'slaedPhp', 'slaed-bi slaed-bi-php', txt.php || 'SLAED PHP');
    }

    api.editors = map;
    api.options = api.options || {};
    api.getEditor = getEditor;
    api.insertText = function(id, text) {
        addText(getEditor(id), text);
    };
    api.insertWrap = function(id, open, close, text) {
        addWrap(getEditor(id), open, close, text);
    };
    function setTabs(id) {
        var box = doc.getElementById(String(id) + '_toast');
        var tabs = box && box.querySelector('.toastui-editor-md-tab-container');
        var mode = box && box.querySelector('.toastui-editor-mode-switch');
        if (!tabs || !mode || tabs.parentElement === mode) return;
        mode.insertBefore(tabs, mode.firstChild);
    }
    function setWidth(id) {
        var box = doc.getElementById(String(id) + '_toast');
        var bar = box && box.querySelector('.toastui-editor-defaultUI-toolbar');
        if (!bar || !bar.parentElement) return;
        var cs = win.getComputedStyle(bar);
        var pad = (cs.boxSizing === 'border-box') ? 0 : parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight);
        var width = bar.parentElement.clientWidth - pad - 1;
        if (width > 0) bar.style.width = width + 'px';
    }
    function setWidths() {
        map.forEach(function(ed, id) { setWidth(id); });
    }
    win.addEventListener('load', setWidths);
    win.addEventListener('resize', setWidths);
    doc.addEventListener('keydown', function(event) {
        if (event.key !== 'Escape') return;
        map.forEach(function(ed, id) {
            var box = doc.getElementById(String(id) + '_toast');
            if (box && box.classList.contains('sl-toastui-editor-fullscreen')) setFullscreen(id, false);
        });
    });
    api.register = function(id, ed, opt) {
        map.set(String(id), ed);
        api.options[String(id)] = opt || {};
        addTags(id, ed, opt || {});
        if (api.addEmoji) api.addEmoji(id, ed, opt || {});
        if (api.addUpload) api.addUpload(id, ed, opt || {});
        setTabs(id);
        if (typeof ed.on === 'function') ed.on('changeMode', function() { setWidth(id); });
        if (doc.readyState === 'complete') setWidth(id);
    };
    win.SlaedToastUi = api;
})(window, document);
