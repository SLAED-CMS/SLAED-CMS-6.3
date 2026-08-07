(function(win, doc) {
    'use strict';

    var api = win.SlaedToastUi || {};
    var drag = null;
    var layer = 10100;

    function getOpt(id) {
        var ed = api.options || {};
        return ed[String(id)] || {};
    }

    function getLab(id, key, val) {
        var opt = getOpt(id);
        var lab = opt.labels || {};
        return lab[key] || val;
    }

    function getWindow(id, type) {
        var opt = getOpt(id);
        var emoji;
        if (type === 'emoji') {
            emoji = doc.querySelector('.sl-editor-emoji-panel');
            return emoji && emoji.getAttribute('data-editor') === String(id) ? emoji : null;
        }
        return opt.panel ? doc.getElementById(opt.panel) : null;
    }

    function setWindowFront(id, type) {
        var el = getWindow(id, type);
        if (!el || el.classList.contains('sl-none') || win.getComputedStyle(el).display === 'none') return;
        layer += 2;
        el.style.zIndex = String(layer);
    }

    function setPanel(id, show) {
        var opt = getOpt(id);
        var el = opt.panel ? doc.getElementById(opt.panel) : null;
        var box = doc.getElementById(String(id) + '_toast');
        var root = box ? box.querySelector('.toastui-editor-defaultUI') : null;
        if (!el) return;
        if (root && el.parentNode !== root && !el.classList.contains('sl-toastui-window-expanded')) root.appendChild(el);
        el.classList.toggle('sl-none', !show);
        el.setAttribute('aria-hidden', show ? 'false' : 'true');
        if (!show) return;
        if (opt.canlist) getFiles(id);
        setWindowFront(id, 'files');
    }

    function setWindowExpand(id, type, button) {
        var el = getWindow(id, type);
        var icon = button ? button.querySelector('.sl-toastui-head-icon') : null;
        var box = doc.getElementById(String(id) + '_toast');
        var root = box ? box.querySelector('.toastui-editor-defaultUI') : null;
        var open;
        if (!el || !button) return;
        open = !el.classList.contains('sl-toastui-window-expanded');
        if (open) {
            el.setAttribute('data-slaed-left', el.style.left || '');
            el.setAttribute('data-slaed-top', el.style.top || '');
            el.setAttribute('data-slaed-transform', el.style.transform || '');
            el.setAttribute('data-slaed-bottom', el.style.bottom || '');
            el.setAttribute('data-slaed-height', el.style.height || '');
            el.setAttribute('data-slaed-position', el.style.position || '');
            el.setAttribute('data-slaed-right', el.style.right || '');
            el.setAttribute('data-slaed-width', el.style.width || '');
            if (el.parentNode !== doc.body) doc.body.appendChild(el);
            el.classList.add('sl-toastui-window-expanded');
            el.style.bottom = '24px';
            el.style.height = 'auto';
            el.style.left = '24px';
            el.style.position = 'fixed';
            el.style.right = '24px';
            el.style.top = '24px';
            el.style.transform = 'none';
            el.style.width = 'auto';
        } else {
            el.classList.remove('sl-toastui-window-expanded');
            if (type === 'files' && root && el.parentNode !== root) root.appendChild(el);
            el.style.bottom = el.getAttribute('data-slaed-bottom') || '';
            el.style.height = el.getAttribute('data-slaed-height') || '';
            el.style.left = el.getAttribute('data-slaed-left') || '';
            el.style.position = el.getAttribute('data-slaed-position') || '';
            el.style.right = el.getAttribute('data-slaed-right') || '';
            el.style.top = el.getAttribute('data-slaed-top') || '';
            el.style.transform = el.getAttribute('data-slaed-transform') || '';
            el.style.width = el.getAttribute('data-slaed-width') || '';
        }
        button.title = button.getAttribute(open ? 'data-restore' : 'data-expand') || '';
        button.setAttribute('aria-label', button.title);
        if (icon) {
            icon.classList.toggle('sl-toastui-head-icon-expand', !open);
            icon.classList.toggle('sl-toastui-head-icon-collapse', open);
        }
        setWindowFront(id, type);
    }

    function setWindowClose(id, type) {
        var el = getWindow(id, type);
        var expand = el ? el.querySelector('.js-slaed-window-expand') : null;
        if (el && el.classList.contains('sl-toastui-window-expanded') && expand) setWindowExpand(id, type, expand);
        if (type === 'emoji') {
            if (el) el.classList.add('sl-none');
            return;
        }
        setPanel(id, false);
    }

    function setWindowDrag(event) {
        var handle = event.target.closest ? event.target.closest('.js-slaed-window-drag') : null;
        var id;
        var type;
        var el;
        var rect;
        if (!handle) return;
        id = handle.getAttribute('data-editor');
        type = handle.getAttribute('data-window');
        el = getWindow(id, type);
        if (!el || el.classList.contains('sl-toastui-window-expanded')) return;
        setWindowFront(id, type);
        rect = el.getBoundingClientRect();
        el.style.bottom = 'auto';
        el.style.height = rect.height + 'px';
        el.style.left = rect.left + 'px';
        el.style.position = 'fixed';
        el.style.right = 'auto';
        el.style.top = rect.top + 'px';
        el.style.transform = 'none';
        el.style.width = rect.width + 'px';
        el.setAttribute('data-slaed-moved', '1');
        drag = {
            id: id,
            type: type,
            el: el,
            left: parseFloat(el.style.left) || 0,
            top: parseFloat(el.style.top) || 0,
            x: event.clientX,
            y: event.clientY
        };
        event.preventDefault();
        event.stopPropagation();
    }

    function updateWindowDrag(event) {
        var left;
        var top;
        var maxleft;
        var maxtop;
        if (!drag) return;
        maxleft = Math.max(0, win.innerWidth - drag.el.offsetWidth);
        maxtop = Math.max(0, win.innerHeight - 36);
        left = Math.max(0, Math.min(maxleft, drag.left + event.clientX - drag.x));
        top = Math.max(0, Math.min(maxtop, drag.top + event.clientY - drag.y));
        drag.el.style.left = left + 'px';
        drag.el.style.top = top + 'px';
        event.preventDefault();
    }

    function deleteWindowDrag() {
        drag = null;
    }

    function getMsg(id, text, warn) {
        var el = api.getTpl(id, warn ? 'msg-warn' : 'msg-info');
        var slot = el ? el.querySelector('[data-sl-text]') : null;
        if (slot) slot.textContent = text;
        else if (el) el.textContent = text;
        return el;
    }

    function setMsg(id, text, warn) {
        var opt = getOpt(id);
        var el = opt.msg ? doc.getElementById(opt.msg) : null;
        var node;
        if (!el) return;
        el.replaceChildren();
        if (text) {
            node = getMsg(id, text, warn);
            if (node) el.appendChild(node);
        }
        if (warn) setPanel(id, true);
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

    function getImageMode(id) {
        var opt = getOpt(id);
        var mode = opt.object ? doc.getElementById(opt.object) : null;
        var sel = mode ? mode.querySelector('input[type="radio"]:checked') : null;
        if (sel) return sel.value;
        return opt.canupload ? 'upload' : 'embed';
    }

    function addSource(id, url, text) {
        var ed = api.getEditor ? api.getEditor(id) : null;
        if (!ed || !url) return;
        ed.focus();
        ed.exec('addImage', { imageUrl: url, altText: text || 'image' });
    }

    function checkEmbedType(id, file) {
        var list = getOpt(id).embedimg || [];
        var name = String(file.name || '');
        var type = String(file.type || '').toLowerCase();
        var sub = type.indexOf('image/') === 0 ? type.slice(6) : '';
        var ext = name.indexOf('.') > 0 ? name.split('.').pop().toLowerCase() : '';
        if (type !== '') return sub !== '' && list.indexOf(sub) >= 0;
        return ext !== '' && list.indexOf(ext) >= 0;
    }

    function addEmbed(id, file, done) {
        var opt = getOpt(id);
        var max = parseInt(opt.embedmax || 0, 10);
        var rd = new FileReader();
        if (!opt.canembed) {
            setMsg(id, getLab(id, 'noembed', 'This field cannot hold an embedded image'), true);
            return;
        }
        if (!checkEmbedType(id, file)) {
            setMsg(id, getLab(id, 'badtype', 'Unsupported file type'), true);
            return;
        }
        if (max > 0 && file.size > max) {
            setMsg(id, getLab(id, 'toobig', 'File is too big'), true);
            return;
        }
        rd.onload = function() {
            if (done) done(String(rd.result), file.name || 'image');
            setPanel(id, false);
        };
        rd.readAsDataURL(file);
    }

    function addImage(id, file, done) {
        var opt = getOpt(id);
        var data = new FormData();
        var put = done || function(url, text) {
            addSource(id, url, text);
        };
        if (getImageMode(id) === 'embed') {
            addEmbed(id, file, put);
            return Promise.resolve();
        }
        if (!opt.canupload || !opt.upload) return Promise.resolve();
        data.append('token', opt.token || '');
        data.append('file[]', file);
        return getReq(opt.upload, data).then(function(json) {
            var row = json.files && json.files[0] ? json.files[0] : null;
            if (!json.ok || !row) {
                setMsg(id, json.error || getLab(id, 'upload', 'Upload failed'), true);
                return;
            }
            if (getImageMode(id) === 'attach') {
                addAttach(id, row.file);
                getFiles(id);
                return;
            }
            setMsg(id, row.file || getLab(id, 'uploaded', 'Uploaded'), false);
            put(row.url, row.file || 'image');
            getFiles(id);
        }).catch(function() {
            setMsg(id, getLab(id, 'upload', 'Upload failed'), true);
        });
    }

    function addAttach(id, file) {
        var txt = '[attach=' + file + ' align=left title=title]';
        if (api.insertText) api.insertText(id, txt);
        setPanel(id, false);
    }

    function addExisting(id, url, text) {
        addSource(id, url, text);
        setPanel(id, false);
    }

    function addUrl(id) {
        var opt = getOpt(id);
        var url = opt.url ? doc.getElementById(opt.url) : null;
        var alt = opt.alt ? doc.getElementById(opt.alt) : null;
        var src = url ? String(url.value || '').trim() : '';
        if (src === '') return;
        addSource(id, src, alt ? String(alt.value || '').trim() : '');
        if (url) url.value = '';
        if (alt) alt.value = '';
        setPanel(id, false);
    }

    function getRows(id, rows) {
        var table;
        var body;
        if (!rows || !rows.length) return getMsg(id, getLab(id, 'nofiles', 'No files'), false);
        table = api.getTpl(id, 'file-table');
        body = table ? table.querySelector('tbody') : null;
        if (!body) return null;
        rows.forEach(function(row) {
            var tr = api.getTpl(id, 'file-row');
            var img = tr ? tr.querySelector('.js-slaed-file-thumb') : null;
            var name = tr ? tr.querySelector('.js-slaed-file-name') : null;
            var size = tr ? tr.querySelector('.js-slaed-file-size') : null;
            var ins = tr ? tr.querySelector('.js-slaed-file-image') : null;
            var att = tr ? tr.querySelector('.js-slaed-file-attach') : null;
            if (!tr) return;
            if (img) {
                if (row.image) img.src = row.url;
                else img.remove();
            }
            if (name) name.textContent = row.file;
            if (size) size.textContent = row.size;
            if (ins) {
                if (row.image) {
                    ins.textContent = getLab(id, 'image', 'Image');
                    ins.setAttribute('data-editor', id);
                    ins.setAttribute('data-file', row.file);
                    ins.setAttribute('data-url', row.url);
                } else {
                    ins.remove();
                }
            }
            if (att) {
                att.textContent = getLab(id, 'attach', 'Attachment');
                att.setAttribute('data-editor', id);
                att.setAttribute('data-file', row.file);
            }
            body.appendChild(tr);
        });
        return table;
    }

    function getFiles(id) {
        var opt = getOpt(id);
        var el = opt.list ? doc.getElementById(opt.list) : null;
        if (!el || !opt.files) return;
        fetch(opt.files, { credentials: 'same-origin' }).then(function(res) {
            return res.json();
        }).then(function(json) {
            var node = json.ok ? getRows(id, json.files || []) : getMsg(id, json.error || getLab(id, 'load', 'Load failed'), true);
            el.replaceChildren();
            if (node) el.appendChild(node);
        }).catch(function() {
            var node = getMsg(id, getLab(id, 'load', 'Load failed'), true);
            el.replaceChildren();
            if (node) el.appendChild(node);
        });
    }

    function setFileName(id, files) {
        var opt = getOpt(id);
        var panel = opt.panel ? doc.getElementById(opt.panel) : null;
        var name = panel ? panel.querySelector('.js-slaed-upload-name') : null;
        var rows = Array.prototype.slice.call(files || []);
        if (!name) return;
        name.textContent = rows.length ? rows.map(function(file) { return file.name; }).join(', ') : getLab(id, 'nofile', 'No file');
        name.title = name.textContent;
    }

    function addFileList(id, list, input) {
        var opt = getOpt(id);
        var files = Array.prototype.slice.call(list || []);
        var max = parseInt(opt.maxfiles || 0, 10);
        if (!files.length) return;
        setFileName(id, files);
        if (getImageMode(id) !== 'embed' && max > 0 && files.length > max) {
            setMsg(id, getLab(id, 'fileup', 'Files') + ': ' + max, true);
            if (input) input.value = '';
            setFileName(id, []);
            return;
        }
        files.reduce(function(req, file) {
            return req.then(function() {
                return addImage(id, file);
            });
        }, Promise.resolve()).then(function() {
            if (opt.canlist) getFiles(id);
            if (input) input.value = '';
            setFileName(id, []);
        });
    }

    function addPanel(id) {
        var opt = getOpt(id);
        var el = opt.panel ? doc.getElementById(opt.panel) : null;
        if (!el) return;
        setPanel(id, el.classList.contains('sl-none'));
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
        if (typeof ed.removeToolbarItem === 'function') ed.removeToolbarItem('image');
        ed.addCommand('markdown', 'slaedFiles', function() {
            addPanel(id);
        });
        ed.addCommand('wysiwyg', 'slaedFiles', function() {
            addPanel(id);
        });
        ed.insertToolbarItem({ groupIndex: 3, itemIndex: 1 }, {
            name: 'slaedFiles',
            text: '',
            className: 'toastui-editor-toolbar-icons sl-editor-icon sl-editor-icon-files',
            tooltip: getLab(id, 'insert', 'Insert image'),
            command: 'slaedFiles'
        });
    }

    doc.addEventListener('change', function(ev) {
        var el = ev.target;
        if (!el.classList || !el.classList.contains('js-slaed-upload-file')) return;
        addFileList(el.getAttribute('data-editor'), el.files, el);
    });

    doc.addEventListener('dragover', function(ev) {
        var zone = ev.target.closest ? ev.target.closest('.js-slaed-upload-drop') : null;
        if (!zone) return;
        ev.preventDefault();
        if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'copy';
        zone.classList.add('sl-drag-over');
    });

    doc.addEventListener('dragleave', function(ev) {
        var zone = ev.target.closest ? ev.target.closest('.js-slaed-upload-drop') : null;
        if (!zone || (ev.relatedTarget && zone.contains(ev.relatedTarget))) return;
        zone.classList.remove('sl-drag-over');
    });

    doc.addEventListener('drop', function(ev) {
        var zone = ev.target.closest ? ev.target.closest('.js-slaed-upload-drop') : null;
        if (!zone) return;
        ev.preventDefault();
        zone.classList.remove('sl-drag-over');
        addFileList(zone.getAttribute('data-editor'), ev.dataTransfer ? ev.dataTransfer.files : []);
    });

    doc.addEventListener('keydown', function(ev) {
        var zone = ev.target.closest ? ev.target.closest('.js-slaed-upload-drop') : null;
        var file;
        if (!zone || (ev.key !== 'Enter' && ev.key !== ' ')) return;
        ev.preventDefault();
        file = zone.querySelector('.js-slaed-upload-file');
        if (file) file.click();
    });

    doc.addEventListener('pointerdown', setWindowDrag);
    doc.addEventListener('pointermove', updateWindowDrag);
    doc.addEventListener('pointerup', deleteWindowDrag);
    doc.addEventListener('pointercancel', deleteWindowDrag);

    doc.addEventListener('pointerdown', function(ev) {
        var el = ev.target.closest ? ev.target.closest('.sl-toastui-upload, .sl-editor-emoji-panel') : null;
        if (!el) return;
        if (el.classList.contains('sl-editor-emoji-panel')) setWindowFront(el.getAttribute('data-editor'), 'emoji');
        else setWindowFront(el.getAttribute('data-editor-id'), 'files');
    }, true);

    doc.addEventListener('click', function(ev) {
        var el = ev.target;
        var button = el.closest ? el.closest('button, input[type="button"]') : el;
        var zone = el.closest ? el.closest('.js-slaed-upload-drop') : null;
        var id;
        var opt;
        var panel;
        var file;
        if (zone && !el.classList.contains('js-slaed-upload-file')) {
            id = zone.getAttribute('data-editor');
            opt = getOpt(id);
            panel = opt.panel ? doc.getElementById(opt.panel) : null;
            file = panel ? panel.querySelector('.js-slaed-upload-file') : null;
            if (file) file.click();
            return;
        }
        if (!button || !button.classList) return;
        id = button.getAttribute('data-editor');
        if (button.classList.contains('js-slaed-image-insert')) addUrl(id);
        if (button.classList.contains('js-slaed-file-image')) addExisting(id, button.getAttribute('data-url'), button.getAttribute('data-file'));
        if (button.classList.contains('js-slaed-file-attach')) addAttach(id, button.getAttribute('data-file'));
        if (button.classList.contains('js-slaed-file-refresh')) getFiles(id);
        if (button.classList.contains('js-slaed-window-expand')) setWindowExpand(id, button.getAttribute('data-window'), button);
        if (button.classList.contains('js-slaed-window-close')) setWindowClose(id, button.getAttribute('data-window'));
    });

    api.options = api.options || {};
    api.getTpl = api.getTpl || function(id, name) {
        var opt = (api.options || {})[String(id)] || {};
        var root = doc.querySelector('.' + (opt.tpl || 'js-slaed-editor-tpl'));
        var tpl = root ? root.querySelector('template[data-tpl="' + name + '"]') : null;
        return tpl && tpl.content && tpl.content.firstElementChild ? tpl.content.firstElementChild.cloneNode(true) : null;
    };
    api.syncWindow = function(id, type) {
        setWindowFront(id, type);
        return getWindow(id, type);
    };
    api.addUpload = function(id, ed, opt) {
        if (!opt) return;
        api.options[String(id)] = opt;
        addHook(id, ed);
        addBtn(id, ed);
    };
    win.SlaedToastUi = api;
})(window, document);
