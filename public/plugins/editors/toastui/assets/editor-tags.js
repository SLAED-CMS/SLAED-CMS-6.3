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
        var button = box ? box.querySelector('.sl-editor-icon-fullscreen') : null;
        var tooltip = button ? button.querySelector('.toastui-editor-tooltip') : null;
        var ed = getEditor(id);
        var active;
        var label;
        if (!box) return;
        active = typeof open === 'boolean' ? open : !box.classList.contains('sl-toastui-editor-fullscreen');
        box.classList.toggle('sl-toastui-editor-fullscreen', active);
        if (ed && typeof ed.setHeight === 'function') {
            if (active) {
                if (!box.getAttribute('data-slaed-height') && typeof ed.getHeight === 'function') box.setAttribute('data-slaed-height', ed.getHeight() || '');
                ed.setHeight('100%');
            } else {
                ed.setHeight(box.getAttribute('data-slaed-height') || '300px');
                box.removeAttribute('data-slaed-height');
            }
        }
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
        var issuper = !!(opt && opt.super);
        var txt = opt && opt.labels ? opt.labels : {};
        if (!ed || typeof ed.addCommand !== 'function' || typeof ed.insertToolbarItem !== 'function') return;
        addCmd(ed, 'slaedFullscreen', function() {
            setFullscreen(id);
        });
        addItem(ed, 0, 'slaedFullscreen', 'sl-editor-icon sl-editor-icon-fullscreen', txt.fullscreen || 'Full screen', 0);
        addCmd(ed, 'slaedQuote', function() {
            addWrap(ed, '[quote]', '[/quote]', 'Quote');
        });
        addCmd(ed, 'slaedHide', function() {
            addWrap(ed, '[hide]', '[/hide]', 'Hidden text');
        });
        addCmd(ed, 'slaedTabs', function() {
            addTabs(ed);
        });
        addItem(ed, 0, 'slaedQuote', 'sl-editor-icon sl-editor-icon-quote', txt.quote || 'SLAED quote');
        addItem(ed, 1, 'slaedHide', 'sl-editor-icon sl-editor-icon-hide', txt.hide || 'SLAED hidden block');
        addItem(ed, 2, 'slaedTabs', 'sl-editor-icon sl-editor-icon-tabs', txt.tabs || 'SLAED tabs');
        if (!issuper) return;
        addCmd(ed, 'slaedHtml', function() {
            addWrap(ed, '[usehtml]', '[/usehtml]', '<p>HTML</p>');
        });
        addCmd(ed, 'slaedPhp', function() {
            addWrap(ed, '[usephp]', '[/usephp]', 'echo "";');
        });
        addItem(ed, 5, 'slaedHtml', 'sl-editor-icon sl-editor-icon-html', txt.html || 'SLAED raw HTML');
        addItem(ed, 6, 'slaedPhp', 'sl-editor-icon sl-editor-icon-php', txt.php || 'SLAED PHP');
    }

    // The emoji panel is the shared runtime's: the toolbar opens it at its own button and hands over the editor it writes into
    function addEmoji(id, ed, opt) {
        var txt = opt && opt.labels ? opt.labels : {};
        if (!win.SlaedEmoji || !ed || typeof ed.addCommand !== 'function' || typeof ed.insertToolbarItem !== 'function') return;
        addCmd(ed, 'slaedEmoji', function() {
            var box = doc.getElementById(String(id) + '_toast');
            win.SlaedEmoji.setPanel(id, ed, box ? box.querySelector('.toastui-editor-toolbar-icons.sl-editor-icon-emoji') : null);
        });
        ed.insertToolbarItem({ groupIndex: 6, itemIndex: 3 }, {
            name: 'slaedEmoji',
            text: '',
            className: 'toastui-editor-toolbar-icons sl-editor-icon sl-editor-icon-emoji',
            tooltip: txt.emoji || 'Emoji',
            command: 'slaedEmoji'
        });
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
    // The key reaches the fullscreen only once nothing stands in front of it: an open window is what the press is about, and the canon closes that one first
    doc.addEventListener('keydown', function(event) {
        if (event.key !== 'Escape' || doc.querySelector('dialog.sl-modal[open]')) return;
        map.forEach(function(ed, id) {
            var box = doc.getElementById(String(id) + '_toast');
            if (box && box.classList.contains('sl-toastui-editor-fullscreen')) setFullscreen(id, false);
        });
    });
    api.register = function(id, ed, opt) {
        map.set(String(id), ed);
        api.options[String(id)] = opt || {};
        addTags(id, ed, opt || {});
        addEmoji(id, ed, opt || {});
        if (win.SlaedFileManager) win.SlaedFileManager.addUpload(id, ed, opt || {});
        setTabs(id);
        if (typeof ed.on === 'function') ed.on('changeMode', function() { setWidth(id); });
        if (doc.readyState === 'complete') setWidth(id);
    };
    // An editor whose region is swapped away is destroyed, not only forgotten: the vendor instance goes with its entry and its options; the window forgets it on its own
    api.unregister = function(id) {
        var ed = map.get(String(id));
        map.delete(String(id));
        delete api.options[String(id)];
        if (win.SlaedEmoji) win.SlaedEmoji.deletePanel(id);
        if (ed && typeof ed.destroy === 'function') ed.destroy();
    };
    win.SlaedToastUi = api;
})(window, document);
