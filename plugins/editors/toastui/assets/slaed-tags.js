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

    function addItem(ed, idx, name, icon, tip) {
        ed.insertToolbarItem({ groupIndex: 6, itemIndex: idx }, {
            name: name,
            text: '',
            className: 'toastui-editor-toolbar-icons ' + icon,
            tooltip: tip,
            command: name
        });
    }

    function addTags(id, ed, opt) {
        var admin = !!(opt && opt.admin);
        var txt = opt && opt.labels ? opt.labels : {};
        if (!ed || typeof ed.addCommand !== 'function' || typeof ed.insertToolbarItem !== 'function') return;
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
    function refit(id) {
        var box = doc.getElementById(String(id) + '_toast');
        var bar = box && box.querySelector('.toastui-editor-defaultUI-toolbar');
        if (!bar || !bar.parentElement) return;
        // Toast UI folds surplus toolbar icons into its "..." dropdown only
        // when the toolbar has a determinate width: at width:auto it shares
        // the row with the md tab switcher and classifies the icons against
        // a bogus width, so they spill past the editor. Pin the toolbar to
        // the real remaining row width; its ResizeObserver then reclassifies
        // correctly on every change.
        var tabs = box.querySelector('.toastui-editor-md-tab-container');
        var cs = win.getComputedStyle(bar);
        var pad = (cs.boxSizing === 'border-box') ? 0 : parseFloat(cs.paddingLeft) + parseFloat(cs.paddingRight);
        // -1: the pinned width must differ from the auto width, or the box
        // does not resize and the observer never re-runs the stale classifier
        var w = bar.parentElement.clientWidth - (tabs ? tabs.offsetWidth : 0) - pad - 1;
        if (w > 0) bar.style.width = w + 'px';
    }
    function refitAll() {
        map.forEach(function(ed, id) { refit(id); });
    }
    win.addEventListener('load', refitAll);
    win.addEventListener('resize', refitAll);
    api.register = function(id, ed, opt) {
        map.set(String(id), ed);
        api.options[String(id)] = opt || {};
        addTags(id, ed, opt || {});
        if (api.addEmoji) api.addEmoji(id, ed, opt || {});
        if (api.addUpload) api.addUpload(id, ed, opt || {});
        if (typeof ed.on === 'function') ed.on('changeMode', function() { refit(id); });
        if (doc.readyState === 'complete') refit(id);
    };
    win.SlaedToastUi = api;
})(window, document);
