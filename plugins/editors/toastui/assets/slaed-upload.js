(function(win, doc) {
    'use strict';

    var api = win.SlaedToastUi || {};

    function getOpt(id) {
        var ed = api.options || {};
        return ed[String(id)] || {};
    }

    function getLab(id, key, val) {
        var opt = getOpt(id);
        var lab = opt.labels || {};
        return lab[key] || val;
    }

    function setMsg(id, text, warn) {
        var opt = getOpt(id);
        var el = opt.msg ? doc.getElementById(opt.msg) : null;
        if (!el) return;
        el.className = warn ? 'sl-alert sl-warn' : 'sl-alert';
        el.textContent = text || '';
    }

    function getEsc(text) {
        return String(text || '').replace(/[&<>"']/g, function(chr) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[chr];
        });
    }

    function getReq(url, data) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            body: data
        }).then(function(res) {
            return res.json();
        });
    }

    function addImage(id, file, done) {
        var opt = getOpt(id);
        var data = new FormData();
        if (!opt.upload) return;
        data.append('token', opt.token || '');
        data.append('file[]', file);
        getReq(opt.upload, data).then(function(json) {
            var row = json.files && json.files[0] ? json.files[0] : null;
            if (!json.ok || !row) {
                setMsg(id, json.error || getLab(id, 'upload', 'Upload failed'), true);
                return;
            }
            setMsg(id, row.file || getLab(id, 'uploaded', 'Uploaded'), false);
            if (done) done(row.url, row.file || 'image');
            getFiles(id);
        }).catch(function() {
            setMsg(id, getLab(id, 'upload', 'Upload failed'), true);
        });
    }

    function addAttach(id, file) {
        var txt = '[attach=' + file + ' align=left title=title]';
        if (api.insertText) api.insertText(id, txt);
    }

    function addExisting(id, url, text) {
        var ed = api.getEditor ? api.getEditor(id) : null;
        if (!ed) return;
        ed.focus();
        ed.exec('addImage', { imageUrl: url, altText: text || 'image' });
    }

    function getRows(id, rows) {
        if (!rows || !rows.length) return '<div class="sl-info">' + getEsc(getLab(id, 'nofiles', 'No files')) + '</div>';
        return '<table class="sl-table"><tbody>' + rows.map(function(row) {
            var dat = ' data-editor="' + getEsc(id) + '" data-file="' + getEsc(row.file) + '"';
            var img = row.image ? '<img src="' + getEsc(row.url) + '" alt="" style="max-width:80px;max-height:50px">' : '';
            var lab = getEsc(getLab(id, 'image', 'Image'));
            var ins = row.image
                ? '<button type="button" class="sl-but-blue js-slaed-file-image"' + dat + ' data-url="' + getEsc(row.url) + '">' + lab + '</button> '
                : '';
            var att = '<button type="button" class="sl-but-green js-slaed-file-attach"' + dat + '>' + getEsc(getLab(id, 'attach', 'Attach')) + '</button>';
            return '<tr><td>' + img + '</td><td>' + getEsc(row.file) + '</td><td>' + getEsc(row.size) + '</td><td>' + ins + att + '</td></tr>';
        }).join('') + '</tbody></table>';
    }

    function getFiles(id) {
        var opt = getOpt(id);
        var el = opt.list ? doc.getElementById(opt.list) : null;
        if (!el || !opt.files) return;
        fetch(opt.files, { credentials: 'same-origin' }).then(function(res) {
            return res.json();
        }).then(function(json) {
            el.innerHTML = json.ok ? getRows(id, json.files || []) : '<div class="sl-alert sl-warn">' + getEsc(json.error || getLab(id, 'load', 'Load failed')) + '</div>';
        }).catch(function() {
            el.innerHTML = '<div class="sl-alert sl-warn">' + getEsc(getLab(id, 'load', 'Load failed')) + '</div>';
        });
    }

    function addPanel(id) {
        var opt = getOpt(id);
        var el = opt.panel ? doc.getElementById(opt.panel) : null;
        if (!el) return;
        el.classList.toggle('sl-none');
        if (!el.classList.contains('sl-none')) getFiles(id);
    }

    function addHook(id, ed) {
        if (!ed || typeof ed.addHook !== 'function') return;
        ed.addHook('addImageBlobHook', function(blob, done) {
            addImage(id, blob, done);
            return false;
        });
    }

    function addBtn(id, ed) {
        if (!ed || typeof ed.addCommand !== 'function' || typeof ed.insertToolbarItem !== 'function') return;
        ed.addCommand('markdown', 'slaedFiles', function() {
            addPanel(id);
        });
        ed.addCommand('wysiwyg', 'slaedFiles', function() {
            addPanel(id);
        });
        ed.insertToolbarItem({ groupIndex: 6, itemIndex: 4 }, {
            name: 'slaedFiles',
            text: '',
            className: 'toastui-editor-toolbar-icons slaed-bi slaed-bi-files',
            tooltip: getLab(id, 'files', 'SLAED files'),
            command: 'slaedFiles'
        });
    }

    doc.addEventListener('change', function(ev) {
        var el = ev.target;
        if (!el.classList || !el.classList.contains('js-slaed-upload-file')) return;
        Array.prototype.forEach.call(el.files || [], function(file) {
            addImage(el.getAttribute('data-editor'), file);
        });
        el.value = '';
    });

    doc.addEventListener('click', function(ev) {
        var el = ev.target;
        if (!el.classList) return;
        if (el.classList.contains('js-slaed-file-image')) addExisting(el.getAttribute('data-editor'), el.getAttribute('data-url'), el.getAttribute('data-file'));
        if (el.classList.contains('js-slaed-file-attach')) addAttach(el.getAttribute('data-editor'), el.getAttribute('data-file'));
        if (el.classList.contains('js-slaed-file-refresh')) getFiles(el.getAttribute('data-editor'));
    });

    api.options = api.options || {};
    api.addUpload = function(id, ed, opt) {
        if (!opt || !opt.upload) return;
        api.options[String(id)] = opt;
        addHook(id, ed);
        addBtn(id, ed);
    };
    win.SlaedToastUi = api;
})(window, document);
