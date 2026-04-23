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
    api.register = function(id, ed, opt) {
        map.set(String(id), ed);
        api.options[String(id)] = opt || {};
        addTags(id, ed, opt || {});
        if (api.addEmoji) api.addEmoji(id, ed, opt || {});
        if (api.addUpload) api.addUpload(id, ed, opt || {});
    };
    win.SlaedToastUi = api;
})(window, document);
