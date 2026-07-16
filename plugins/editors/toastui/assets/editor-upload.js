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

    function setPanel(id, show) {
        var opt = getOpt(id);
        var el = opt.panel ? doc.getElementById(opt.panel) : null;
        var box = doc.getElementById(String(id) + '_toast');
        var root = box ? box.querySelector('.toastui-editor-defaultUI') : null;
        if (!el) return;
        if (root && el.parentNode !== root) root.appendChild(el);
        el.classList.toggle('sl-none', !show);
        el.setAttribute('aria-hidden', show ? 'false' : 'true');
        if (show) {
            getFiles(id);
            setWindowFront(id, 'files');
        }
    }

    function getWindow(id, type) {
        var opt = getOpt(id);
        var box = doc.getElementById(String(id) + '_toast');
        var emoji;
        if (type === 'image') return box ? box.querySelector('.toastui-editor-popup-add-image') : null;
        if (type === 'link') return box ? box.querySelector('.toastui-editor-popup-add-link') : null;
        if (type === 'emoji') {
            emoji = doc.querySelector('.sl-editor-emoji-panel');
            return emoji && emoji.getAttribute('data-editor') === String(id) ? emoji : null;
        }
        return opt.panel ? doc.getElementById(opt.panel) : null;
    }

    function getWindowHead(id, type) {
        var opt = getOpt(id);
        var key = type + 'head';
        return opt[key] ? doc.getElementById(opt[key]) : null;
    }

    function setWindowFront(id, type) {
        var opt = getOpt(id);
        var el = getWindow(id, type);
        var head = type === 'files' ? null : getWindowHead(id, type);
        var mode = type === 'image' && opt.object ? doc.getElementById(opt.object) : null;
        if (!el || el.classList.contains('sl-none') || win.getComputedStyle(el).display === 'none') return;
        layer += 3;
        el.style.zIndex = String(layer);
        if (head) head.style.zIndex = String(layer + 1);
        if (mode) mode.style.zIndex = String(layer + 2);
    }

    function setPopupChrome(id, type, show) {
        var box = doc.getElementById(String(id) + '_toast');
        var popup = getWindow(id, type);
        var head = getWindowHead(id, type);
        var boxrect;
        var poprect;
        var active;
        var fixed;
        if (!box || !head) return popup;
        if (head.parentNode !== box) box.appendChild(head);
        if (!head.hasAttribute('data-slaed-bound')) {
            head.addEventListener('mousedown', function(event) {
                event.stopPropagation();
            });
            head.setAttribute('data-slaed-bound', '1');
        }
        active = !!show && !!popup && win.getComputedStyle(popup).display !== 'none';
        head.classList.toggle('sl-none', !active);
        if (!active) {
            head.classList.remove('sl-toastui-window-expanded', 'sl-toastui-window-fixed');
            return popup;
        }
        popup.classList.add('sl-toastui-window-popup', 'sl-toastui-' + type + '-popup');
        popup.setAttribute('data-slaed-editor', String(id));
        popup.setAttribute('data-slaed-window', type);
        fixed = win.getComputedStyle(popup).position === 'fixed';
        head.classList.toggle('sl-toastui-window-expanded', popup.classList.contains('sl-toastui-window-expanded'));
        head.classList.toggle('sl-toastui-window-fixed', fixed);
        boxrect = box.getBoundingClientRect();
        poprect = popup.getBoundingClientRect();
        head.style.left = (fixed ? poprect.left : poprect.left - boxrect.left) + 'px';
        head.style.top = (fixed ? poprect.top : poprect.top - boxrect.top) + 'px';
        head.style.width = poprect.width + 'px';
        return popup;
    }

    function setImageChrome(id, show) {
        var opt = getOpt(id);
        var box = doc.getElementById(String(id) + '_toast');
        var mode = opt.object ? doc.getElementById(opt.object) : null;
        var popup = setPopupChrome(id, 'image', show);
        var buttons = popup ? popup.querySelector('.toastui-editor-button-container') : null;
        var file = popup ? popup.querySelector('#toastuiImageFileInput') : null;
        var filebox = file ? file.parentElement : null;
        var boxrect;
        var poprect;
        var butrect;
        var active;
        var fixed;
        var filemode;
        if (!box || !mode) return popup;
        if (mode.parentNode !== box) box.appendChild(mode);
        mode.setAttribute('data-slaed-editor', String(id));
        mode.setAttribute('data-slaed-window', 'image');
        active = !!show && !!popup && win.getComputedStyle(popup).display !== 'none';
        filemode = active && !!filebox && win.getComputedStyle(filebox).display !== 'none';
        mode.classList.toggle('sl-none', !filemode);
        if (!active) return popup;
        fixed = win.getComputedStyle(popup).position === 'fixed';
        mode.classList.toggle('sl-toastui-window-fixed', fixed);
        if (filemode) {
            filebox.classList.add('sl-toastui-file-row');
            filebox.classList.add('js-slaed-image-drop');
            filebox.setAttribute('data-editor', String(id));
            filebox.setAttribute('data-slaed-drop', getLab(id, 'dropfiles', 'Drop file here or click the field'));
            filebox.setAttribute('role', 'button');
            filebox.setAttribute('tabindex', '0');
            filebox.style.display = 'grid';
            if (file) file.removeAttribute('accept');
        }
        boxrect = box.getBoundingClientRect();
        poprect = popup.getBoundingClientRect();
        if (filemode && buttons) {
            butrect = buttons.getBoundingClientRect();
            mode.style.left = (fixed ? poprect.left : poprect.left - boxrect.left) + 20 + 'px';
            mode.style.top = (fixed ? butrect.top : butrect.top - boxrect.top) - mode.offsetHeight - 12 + 'px';
            mode.style.width = Math.max(0, poprect.width - 40) + 'px';
        }
        return popup;
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
            if (type === 'files' && el.parentNode !== doc.body) doc.body.appendChild(el);
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
        if (type === 'image') setImageChrome(id, true);
        else if (type !== 'files') setPopupChrome(id, type, true);
    }

    function setWindowClose(id, type) {
        var popup = getWindow(id, type);
        var head = type === 'files' ? popup : getWindowHead(id, type);
        var expand = head ? head.querySelector('.js-slaed-window-expand') : null;
        var close;
        if (popup && popup.classList.contains('sl-toastui-window-expanded') && expand) setWindowExpand(id, type, expand);
        if (type === 'files') {
            setPanel(id, false);
            return;
        }
        if (type === 'emoji') {
            if (popup) popup.classList.add('sl-none');
        } else {
            close = popup ? popup.querySelector('.toastui-editor-close-button') : null;
            if (close) close.click();
        }
        if (type === 'image') setImageChrome(id, false);
        else setPopupChrome(id, type, false);
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
        if (drag.type === 'image') setImageChrome(drag.id, true);
        else if (drag.type !== 'files') setPopupChrome(drag.id, drag.type, true);
        event.preventDefault();
    }

    function deleteWindowDrag() {
        drag = null;
    }

    function setImagePopup(id) {
        var opt = getOpt(id);
        var box = doc.getElementById(String(id) + '_toast');
        var panel = opt.panel ? doc.getElementById(opt.panel) : null;
        var popup = box ? box.querySelector('.toastui-editor-popup-add-image') : null;
        var source;
        var rows;
        var left;
        if (!popup) return null;
        source = panel ? panel.querySelector('.sl-toastui-upload-info') : null;
        popup.classList.add('sl-toastui-image-popup');
        if (source) {
            rows = source.children;
            popup.setAttribute('data-slaed-info', Array.prototype.map.call(rows, function(row) {
                return row.textContent.trim();
            }).join('\n'));
        }
        left = Math.max(5, popup.parentElement.clientWidth - popup.offsetWidth - 5) + 'px';
        if (!popup.hasAttribute('data-slaed-moved') && !popup.classList.contains('sl-toastui-window-expanded') && popup.style.left !== left) popup.style.left = left;
        setImageChrome(id, true);
        return popup;
    }

    function setImageMsg(id, text, warn) {
        var popup = setImagePopup(id);
        var body = popup ? popup.querySelector('.toastui-editor-popup-body') : null;
        if (!body) return false;
        body.classList.toggle('sl-toastui-image-warn', warn && !!text);
        body.setAttribute('data-slaed-error', text || '');
        setImageChrome(id, true);
        return true;
    }

    function getMsg(id, text, warn) {
        var el = api.getTpl(id, warn ? 'msg-warn' : 'msg-info');
        if (el) el.textContent = text;
        return el;
    }

    function setMsg(id, text, warn, image) {
        var opt = getOpt(id);
        var el = opt.msg ? doc.getElementById(opt.msg) : null;
        var node;
        if (image && setImageMsg(id, text, warn)) return;
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

    function addImage(id, file, done, image) {
        var opt = getOpt(id);
        var data = new FormData();
        if (!opt.upload) return Promise.resolve();
        data.append('token', opt.token || '');
        data.append('file[]', file);
        return getReq(opt.upload, data).then(function(json) {
            var row = json.files && json.files[0] ? json.files[0] : null;
            var mode = opt.object ? doc.getElementById(opt.object) : null;
            var check = mode ? mode.querySelector('input[type="checkbox"]') : null;
            var box = doc.getElementById(String(id) + '_toast');
            var popup = box ? box.querySelector('.toastui-editor-popup-add-image') : null;
            var close = popup ? popup.querySelector('.toastui-editor-close-button') : null;
            if (!json.ok || !row) {
                setMsg(id, json.error || getLab(id, 'upload', 'Upload failed'), true, image);
                return;
            }
            if (image && check && check.checked) {
                addAttach(id, row.file);
                if (close) close.click();
                setImageChrome(id, false);
                getFiles(id);
                return;
            }
            setMsg(id, row.file || getLab(id, 'uploaded', 'Uploaded'), false);
            if (done) done(row.url, row.file || 'image');
            if (image) setImageChrome(id, false);
            getFiles(id);
        }).catch(function() {
            setMsg(id, getLab(id, 'upload', 'Upload failed'), true, image);
        });
    }

    function addAttach(id, file) {
        var txt = '[attach=' + file + ' align=left title=title]';
        if (api.insertText) api.insertText(id, txt);
        setPanel(id, false);
    }

    function addExisting(id, url, text) {
        var ed = api.getEditor ? api.getEditor(id) : null;
        if (!ed) return;
        ed.focus();
        ed.exec('addImage', { imageUrl: url, altText: text || 'image' });
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
        if (max > 0 && files.length > max) {
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
            getFiles(id);
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
        var box = doc.getElementById(String(id) + '_toast');
        if (!ed) return;
        if (getOpt(id).upload && typeof ed.addHook === 'function') {
            ed.addHook('addImageBlobHook', function(blob, done, type) {
                addImage(id, blob, done, type === 'ui');
                return false;
            });
        }
        if (!box) return;
        box.addEventListener('click', function(ev) {
            var el = ev.target.closest
                ? ev.target.closest('.toastui-editor-toolbar-icons.image, .toastui-editor-toolbar-icons.link, .toastui-editor-tabs .tab-item')
                : null;
            var popup;
            var body;
            var mode;
            if (!el) return;
            setTimeout(function() {
                if (el.classList.contains('link')) {
                    setPopupChrome(id, 'link', true);
                    setWindowFront(id, 'link');
                    return;
                }
                popup = setImagePopup(id);
                setWindowFront(id, 'image');
                body = popup ? popup.querySelector('.toastui-editor-popup-body') : null;
                if (body && el.classList.contains('image')) {
                    body.classList.remove('sl-toastui-image-warn');
                    body.removeAttribute('data-slaed-error');
                    mode = getOpt(id).object ? doc.getElementById(getOpt(id).object) : null;
                    if (mode) mode.querySelector('input[type="checkbox"]').checked = false;
                }
            }, 0);
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
            className: 'toastui-editor-toolbar-icons sl-editor-icon sl-editor-icon-files',
            tooltip: getLab(id, 'files', 'SLAED files'),
            command: 'slaedFiles'
        });
    }

    doc.addEventListener('change', function(ev) {
        var el = ev.target;
        var id;
        if (!el.classList || !el.classList.contains('js-slaed-upload-file')) return;
        id = el.getAttribute('data-editor');
        addFileList(id, el.files, el);
    });

    doc.addEventListener('dragover', function(ev) {
        var zone = ev.target.closest ? ev.target.closest('.js-slaed-upload-drop, .js-slaed-image-drop') : null;
        if (!zone) return;
        ev.preventDefault();
        if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'copy';
        zone.classList.add('sl-drag-over');
    });

    doc.addEventListener('dragleave', function(ev) {
        var zone = ev.target.closest ? ev.target.closest('.js-slaed-upload-drop, .js-slaed-image-drop') : null;
        if (!zone || (ev.relatedTarget && zone.contains(ev.relatedTarget))) return;
        zone.classList.remove('sl-drag-over');
    });

    doc.addEventListener('drop', function(ev) {
        var zone = ev.target.closest ? ev.target.closest('.js-slaed-upload-drop, .js-slaed-image-drop') : null;
        var id;
        var file;
        var send;
        var rows;
        if (!zone) return;
        ev.preventDefault();
        zone.classList.remove('sl-drag-over');
        id = zone.getAttribute('data-editor');
        if (zone.classList.contains('js-slaed-image-drop')) {
            file = zone.querySelector('#toastuiImageFileInput');
            rows = Array.prototype.slice.call(ev.dataTransfer ? ev.dataTransfer.files : [], 0, 1);
            if (!file || !rows.length) return;
            try {
                send = new DataTransfer();
                send.items.add(rows[0]);
                file.files = send.files;
                file.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (err) {
                setMsg(id, getLab(id, 'upload', 'Upload failed'), true, true);
            }
            return;
        }
        addFileList(id, ev.dataTransfer ? ev.dataTransfer.files : []);
    });

    doc.addEventListener('keydown', function(ev) {
        var zone = ev.target.closest ? ev.target.closest('.js-slaed-upload-drop, .js-slaed-image-drop') : null;
        var file;
        if (!zone || (ev.key !== 'Enter' && ev.key !== ' ')) return;
        ev.preventDefault();
        file = zone.querySelector('.js-slaed-upload-file, #toastuiImageFileInput');
        if (file) file.click();
    });

    doc.addEventListener('pointerdown', setWindowDrag);
    doc.addEventListener('pointermove', updateWindowDrag);
    doc.addEventListener('pointerup', deleteWindowDrag);
    doc.addEventListener('pointercancel', deleteWindowDrag);

    doc.addEventListener('pointerdown', function(ev) {
        var el = ev.target.closest ? ev.target.closest('[data-slaed-window], .sl-toastui-upload, [data-window-head]') : null;
        var ctr;
        var id;
        var type;
        if (!el) return;
        ctr = el.matches('[data-window-head]') ? el.querySelector('[data-editor][data-window]') : el;
        if (!ctr) return;
        id = ctr.getAttribute('data-slaed-editor') || ctr.getAttribute('data-editor-id') || ctr.getAttribute('data-editor');
        type = ctr.getAttribute('data-slaed-window') || ctr.getAttribute('data-window') || 'files';
        if (id) setWindowFront(id, type);
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
        if (button.classList.contains('js-slaed-file-image')) addExisting(id, button.getAttribute('data-url'), button.getAttribute('data-file'));
        if (button.classList.contains('js-slaed-file-attach')) addAttach(id, button.getAttribute('data-file'));
        if (button.classList.contains('js-slaed-file-refresh')) getFiles(id);
        if (button.classList.contains('js-slaed-window-expand')) setWindowExpand(id, button.getAttribute('data-window'), button);
        if (button.classList.contains('js-slaed-window-close')) setWindowClose(id, button.getAttribute('data-window'));
        setTimeout(function() {
            Object.keys(api.options).forEach(function(key) {
                setImageChrome(key, true);
                setPopupChrome(key, 'link', true);
                setPopupChrome(key, 'emoji', true);
            });
        }, 0);
    });

    api.options = api.options || {};
    api.getTpl = api.getTpl || function(id, name) {
        var opt = (api.options || {})[String(id)] || {};
        var root = doc.querySelector('.' + (opt.tpl || 'js-slaed-editor-tpl'));
        var tpl = root ? root.querySelector('template[data-tpl="' + name + '"]') : null;
        return tpl && tpl.content && tpl.content.firstElementChild ? tpl.content.firstElementChild.cloneNode(true) : null;
    };
    api.syncWindow = function(id, type) {
        var popup = type === 'image' ? setImageChrome(id, true) : setPopupChrome(id, type, true);
        setWindowFront(id, type);
        return popup;
    };
    api.addUpload = function(id, ed, opt) {
        if (!opt) return;
        api.options[String(id)] = opt;
        addHook(id, ed);
        if (opt.upload) addBtn(id, ed);
    };
    win.SlaedToastUi = api;
})(window, document);
