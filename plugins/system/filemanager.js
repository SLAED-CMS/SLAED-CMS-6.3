(function(win, doc) {
    'use strict';

    var api = win.SlaedFileManager || {};
    var edits = new Map();
    // The box of every form row the window was opened from, which is what tells the two modes apart: a window registered here has a form behind it and no editor at all
    var fields = new Map();
    var rooms = {};
    // The kind of an object decides the glyph it is drawn with; the type resolver itself stays on the server and this map only dresses its answer
    var icons = {
        dir: 'folder',
        image: 'image',
        audio: 'file-earmark-music',
        video: 'file-earmark-play',
        archive: 'file-earmark-zip',
        document: 'file-earmark-pdf',
        text: 'file-earmark-text',
        code: 'file-earmark-code'
    };

    function getOpt(id) {
        var ed = api.options || {};
        return ed[String(id)] || {};
    }

    function getLab(id, key, val) {
        var opt = getOpt(id);
        var lab = opt.labels || {};
        return lab[key] || val;
    }

    // The language constants arrive as printf patterns, because a number in the middle of a sentence stands in a different place in every one of the six locales
    function getText(text, one, two) {
        return String(text || '')
            .replace('%1$d', one).replace('%2$d', two)
            .replace('%1$s', one).replace('%2$s', two)
            .replace('%d', one).replace('%s', one);
    }

    // Every window keeps its own catalogue, its own marks and its own current object, because a page may carry more than one editor and they share nothing but the code
    function getRoom(id) {
        var key = String(id);
        if (!rooms[key]) {
            rooms[key] = {
                files: [],
                view: [],
                pick: [],
                sent: [],
                cur: 0,
                pane: '',
                full: false,
                mode: 'list',
                kind: '',
                find: '',
                state: 'skel',
                queue: null,
                want: null,
                sort: { key: 'date', dir: -1 },
                anch: -1,
                take: null
            };
        }
        return rooms[key];
    }

    function getPanel(id) {
        var opt = getOpt(id);
        return opt.panel ? doc.getElementById(opt.panel) : null;
    }

    function getSlot(id, name) {
        var el = getPanel(id);
        return el ? el.querySelector('[data-sl-slot="' + name + '"]') : null;
    }

    // The options window stands beside the catalogue and not inside it, so it carries a family of its own
    function getOpts(id) {
        var opt = getOpt(id);
        return opt.opts ? doc.getElementById(opt.opts) : null;
    }

    function getOptsPart(id, name) {
        var el = getOpts(id);
        return el ? el.querySelector('[data-sl-opts="' + name + '"]') : null;
    }

    function getShot(id) {
        var all = doc.querySelectorAll('dialog[data-sl-shot="editor"]');
        var out = null;
        Array.prototype.forEach.call(all, function(el) {
            if (el.getAttribute('data-editor') === String(id)) out = el;
        });
        return out;
    }

    // The window itself, its focus and its place are the canon; what belongs to the editor is which pane stands open and whether the catalogue needs reading again
    // The panel keeps living inside the editor root, because the editor has a fullscreen of its own and a window outside it would be left behind
    function setPanel(id, show) {
        var opt = getOpt(id);
        var el = getPanel(id);
        var box = doc.getElementById(String(id) + '_toast');
        var root = box ? box.querySelector('.toastui-editor-defaultUI') : null;
        var room = getRoom(id);
        if (!el) return;
        if (!show) {
            win.setWindowClose(el);
            return;
        }
        if (root && el.parentNode !== root && !el.classList.contains('sl-is-full')) root.appendChild(el);
        if (room.pane === '') setPane(id, '');
        if (opt.canlist) getFiles(id);
        setRoom(id);
        win.setWindowOpen(el);
    }

    // The expanded window is also the expanded catalogue: one button, because the room and what fills it are the same decision
    // The list is drawn again with it, because the compact view shows the last few files and the expanded one shows a page of them
    function setPanelView(id) {
        var el = getPanel(id);
        if (!el) return;
        setView(id, el.classList.contains('sl-is-full'));
        setList(id);
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
        // A refusal opens the window only when it was closed: a message raised while it is open must not restart the request that produced it
        if (warn && getPanel(id) && !getPanel(id).open) setPanel(id, true);
    }

    // The status line reports what happened last and is read out loud, so it never carries a word the visitor did not cause
    function setInfo(id, text) {
        var el = getSlot(id, 'info');
        if (el) el.textContent = text;
    }

    function getReq(id, url, data) {
        var opt = getOpt(id);
        data.append('token', opt.ajax || '');
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            body: data
        }).then(function(res) {
            return res.json();
        });
    }

    // The runtime holds the editor of every window opened from one, because it no longer lives beside the editor plugin and cannot ask it back
    // A window opened as a form field registers no editor, so this answers null there and every editor-only path disables itself on that answer
    function getEditor(id) {
        return edits.get(String(id)) || null;
    }

    // Writing into a window that has no editor behind it is the field mode, and it has to stay silent rather than fail
    function addText(ed, text) {
        if (!ed) return;
        ed.focus();
        ed.insertText(text);
    }

    function addSource(id, url, text) {
        var ed = getEditor(id);
        if (!ed || !url) return;
        ed.focus();
        ed.exec('addImage', { imageUrl: url, altText: text || 'image' });
    }

    function addAttach(id, file, title, align) {
        var txt = '[attach=' + file + ' align=' + (align || 'none') + ' title=' + getTagText(title || file) + ']';
        addText(getEditor(id), txt);
    }

    // A markdown picture carries no side, so one that takes a side is written as the tag the parser reads instead
    function addImage(id, url, title, align) {
        if (align === '') {
            addSource(id, url, title);
            return;
        }
        addText(getEditor(id), '[img=' + align + ' alt=' + getTagText(title) + ']' + url + '[/img]');
    }

    // The parser reads a caption out of a tag by a narrow alphabet, and a character outside it would cut the tag in half
    function getTagText(text) {
        var out = String(text || '').replace(/[^\p{L}0-9_\-. "]+/gu, ' ').replace(/\s+/g, ' ').trim();
        return out === '' ? 'title' : out;
    }

    function getAltText(id, pane) {
        var opt = getOpt(id);
        var el = doc.getElementById((pane === 'url') ? (opt.urlalt || '') : (opt.alt || ''));
        return el ? String(el.value || '').trim() : '';
    }

    // Which shape an uploaded file takes lives inside the upload section, because it belongs to the file and not to the window
    function getInsertMode(id) {
        var opt = getOpt(id);
        var mode = opt.object ? doc.getElementById(opt.object) : null;
        var sel = mode ? mode.querySelector('input[type="radio"]:checked') : null;
        return sel ? sel.value : 'image';
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
            else addSource(id, String(rd.result), file.name || 'image');
            setPanel(id, false);
        };
        rd.readAsDataURL(file);
    }

    function addUrl(id) {
        var opt = getOpt(id);
        var url = opt.url ? doc.getElementById(opt.url) : null;
        var src = url ? String(url.value || '').trim() : '';
        var alt = opt.urlalt ? doc.getElementById(opt.urlalt) : null;
        var text = getAltText(id, 'url');
        if (src === '') return;
        // A picture from another server takes a side like any other, so it goes through the same options window
        askInsert(id, 'image', [{ url: src, file: text || src, image: true, path: '' }], text);
        if (url) url.value = '';
        if (alt) alt.value = '';
    }

    // The queue runs the files one at a time: each carries its own bar and its own cancel, and a refusal names the reason the server gave and never stops the rest
    function getJobBox(id, file, num) {
        var box = api.getTpl(id, 'fm-job');
        var name = box ? box.querySelector('.sl-fm-job-name') : null;
        if (!box) return null;
        box.setAttribute('data-sl-job', String(num));
        if (name) {
            name.firstElementChild.textContent = file.name || '';
            name.lastElementChild.textContent = getSizeText(file.size || 0);
        }
        return box;
    }

    function setJobDone(id, box, why) {
        var stop = box.querySelector('[data-sl-act="jobstop"]');
        var line = box.querySelector('.sl-progress-line');
        var note;
        if (stop) stop.remove();
        box.classList.add(why ? 'sl-is-fail' : 'sl-is-done');
        box.querySelector('.bi').className = 'bi bi-' + (why ? 'exclamation-octagon-fill' : 'check-circle-fill');
        if (!why || !line) return;
        note = api.getTpl(id, 'fm-why');
        if (!note) return;
        note.textContent = why;
        line.replaceWith(note);
    }

    function setJobStep(box, at) {
        var line = box ? box.querySelector('.sl-progress-line div') : null;
        if (!line) return;
        line.style.width = at + '%';
        line.textContent = at + '%';
    }

    function addJobRun(id, file, box, done) {
        var opt = getOpt(id);
        var data = new FormData();
        var xhr = new XMLHttpRequest();
        data.append('token', opt.token || '');
        data.append('file[]', file);
        xhr.open('POST', opt.upload, true);
        xhr.upload.onprogress = function(ev) {
            if (ev.lengthComputable) setJobStep(box, Math.round((ev.loaded / ev.total) * 100));
        };
        xhr.onload = function() {
            var json = null;
            try {
                json = JSON.parse(xhr.responseText);
            } catch (err) {
                json = null;
            }
            setJobStep(box, 100);
            done(json);
        };
        xhr.onerror = function() {
            done(null);
        };
        xhr.send(data);
        return xhr;
    }

    function setQueueStep(id) {
        var room = getRoom(id);
        var job = room.queue;
        var cap = getSlot(id, 'queuecap');
        var stop = getPanel(id) ? getPanel(id).querySelector('[data-sl-act="stop"]') : null;
        var box;
        if (!job) return;
        if (job.at >= job.list.length) {
            if (cap) cap.textContent = getText(getLab(id, 'queueend', '%1$d / %2$d'), job.done, job.list.length);
            if (stop) stop.hidden = true;
            room.queue = null;
            if (getOpt(id).canlist) getFiles(id);
            return;
        }
        box = getPanel(id).querySelector('[data-sl-job="' + job.at + '"]');
        if (cap) cap.textContent = getText(getLab(id, 'queue', '%1$d / %2$d'), job.at + 1, job.list.length);
        job.run = addJobRun(id, job.list[job.at], box, function(json) {
            var row = json && json.files && json.files[0] ? json.files[0] : null;
            var why = row ? '' : ((json && json.error) || getLab(id, 'upload', 'Upload failed'));
            if (box) setJobDone(id, box, why);
            if (row) job.done++;
            if (row) addUploaded(id, row, box);
            job.at++;
            setQueueStep(id);
        });
    }

    function addQueue(id, files) {
        var room = getRoom(id);
        var jobs = getSlot(id, 'jobs');
        var box = getSlot(id, 'queue');
        var stop = getPanel(id) ? getPanel(id).querySelector('[data-sl-act="stop"]') : null;
        if (!jobs || !box || !files.length) return;
        jobs.replaceChildren();
        // A new run starts with a clean slate: the marks of the previous run leave with the cards that carried them
        room.pick = room.pick.filter(function(one) { return room.sent.indexOf(one) < 0; });
        room.sent = [];
        files.forEach(function(file, i) {
            var one = getJobBox(id, file, i);
            if (one) jobs.appendChild(one);
        });
        box.hidden = false;
        if (stop) stop.hidden = false;
        room.queue = { list: files, at: 0, done: 0, run: null };
        setQueueStep(id);
    }

    function deleteQueue(id) {
        var room = getRoom(id);
        var box = getSlot(id, 'queue');
        if (room.queue && room.queue.run) room.queue.run.abort();
        room.queue = null;
        if (box) box.hidden = true;
        if (getOpt(id).canlist) getFiles(id);
    }

    // One successful upload adds one object instead of reloading the whole catalogue, which is what keeps the marks and the current object where they were
    // An upload no longer inserts by itself: the queue marks what it brought in, the window inserts what stays marked,
    // because a batch of several files is a choice and a single one has to be confirmed the same way
    function addUploaded(id, row, box) {
        var room = getRoom(id);
        setInfo(id, row.file);
        room.files.unshift(row);
        room.sent.push(row.path);
        if (room.pick.indexOf(row.path) < 0) room.pick.push(row.path);
        addJobPick(id, box, row);
        setList(id);
        setPicks(id);
    }

    // The mark of an uploaded file rides its own queue card, so it survives the list being redrawn under it, and it
    // takes the cell the stop button has just left, which is why the card keeps its three columns
    function addJobPick(id, box, row) {
        var pick = box ? api.getTpl(id, 'fm-pick') : null;
        var one = pick ? pick.querySelector('input') : null;
        if (!one) return;
        one.setAttribute('data-sl-sent', row.path);
        one.setAttribute('data-editor', String(id));
        one.checked = true;
        box.appendChild(pick);
    }

    function addFileList(id, list, mode) {
        var opt = getOpt(id);
        var files = Array.prototype.slice.call(list || []);
        var max = parseInt(opt.maxfiles || 0, 10);
        if (!files.length) return;
        // A form row takes one file and reads none of it: the pick waits in the window until the insert is pressed and the submit is what carries it to the server
        if (checkField(id)) {
            // A drop carries as many files as the pointer held, whatever the picker was told to allow, so the count is refused here and not left to the markup
            if (max > 0 && files.length > max) {
                setMsg(id, getLab(id, 'fileup', 'Files') + ': ' + max, true);
                return;
            }
            checkFieldFile(id, files[0], function() {
                setMsg(id, '', false);
                setFieldTake(id, 'file', files[0]);
            });
            return;
        }
        if (mode === 'embed') {
            addEmbed(id, files[0]);
            return;
        }
        if (!opt.canupload || !opt.upload) return;
        if (max > 0 && files.length > max) {
            setMsg(id, getLab(id, 'fileup', 'Files') + ': ' + max, true);
            return;
        }
        setMsg(id, '', false);
        addQueue(id, files);
    }

    function getSizeText(size) {
        var unit = ['B', 'KB', 'MB', 'GB'];
        var num = Number(size) || 0;
        var at = 0;
        while (num >= 1024 && at < unit.length - 1) {
            num = num / 1024;
            at++;
        }
        return (at === 0 ? num : num.toFixed(1)) + ' ' + unit[at];
    }

    function getIcon(row) {
        return icons[row.kind] || 'file-earmark';
    }

    // The fan belongs to the object and offers exactly what the descriptor of that object allows, so an action the context withholds is not drawn instead of failing on the route
    function getDial(id, row, num) {
        var box = api.getTpl(id, 'fm-dial');
        var able = row.able || {};
        var acts = [];
        var seen = 0;
        if (!box) return null;
        if (row.image && able.insert) acts.push(['image', 'image', getLab(id, 'insert', 'Insert image')]);
        if (able.insert) acts.push(['attach', 'paperclip', getLab(id, 'insobj', 'Insert file')]);
        if (able.download) acts.push(['down', 'download', getLab(id, 'download', 'Download')]);
        if (able.compress) acts.push(['zip', 'file-zip', getLab(id, 'zip', 'ZIP')]);
        if (able.delete) acts.push(['delete', 'trash3', getLab(id, 'delete', 'Delete')]);
        acts.forEach(function(act) {
            var item = api.getTpl(id, 'fm-act');
            if (!item) return;
            // Downloading is an address and not an action: a real link hands the file over, while a script would only open it in a second tab
            if (act[0] === 'down') {
                item.href = row.url;
                item.setAttribute('download', row.file);
            } else {
                item.setAttribute('data-sl-act', act[0]);
                item.setAttribute('data-sl-num', String(num));
            }
            item.setAttribute('data-editor', id);
            item.title = act[2];
            item.setAttribute('aria-label', act[2]);
            if (act[0] === 'delete') item.setAttribute('data-sl-ask', getText(getLab(id, 'askdel', 'Delete %s?'), row.file));
            item.firstElementChild.className = 'bi bi-' + act[1];
            box.appendChild(item);
            seen++;
        });
        box.querySelector('.sl-dial-toggle').title = getLab(id, 'acts', '');
        box.querySelector('.sl-dial-toggle').setAttribute('aria-label', getLab(id, 'acts', ''));
        // The width of the fan plate is counted by the theme through this variable, so it is written down and never inferred from a child index
        box.style.setProperty('--sl-d-count', String(seen));
        return seen ? box : null;
    }

    function setShotCell(id, cell, row, num) {
        var icon = cell.querySelector('.bi');
        var img = cell.querySelector('img');
        var zoom = cell.querySelector('[data-sl-zoom]');
        icon.className = 'bi bi-' + getIcon(row);
        if (row.image && row.thumb) {
            img.src = row.thumb;
            img.alt = row.file;
            img.hidden = false;
            icon.hidden = true;
        }
        if (!row.able || !row.able.preview) return;
        zoom.hidden = false;
        zoom.setAttribute('data-sl-zoom', String(num));
        zoom.setAttribute('data-editor', id);
        zoom.title = getLab(id, 'preview', '');
    }

    function setPickBox(id, cell, row, num) {
        var pick = cell.querySelector('[data-sl-pick]');
        if (!pick) return;
        pick.setAttribute('data-sl-pick', String(num));
        pick.setAttribute('data-editor', id);
        pick.parentNode.title = getLab(id, 'mark', '') + ' ' + row.file;
    }

    function getTile(id, row, num) {
        var cell = api.getTpl(id, 'fm-tile');
        var dial;
        if (!cell) return null;
        cell.setAttribute('data-sl-num', String(num));
        cell.setAttribute('data-editor', id);
        setShotCell(id, cell.querySelector('.sl-fm-tile-img'), row, num);
        cell.querySelector('.sl-fm-tile-kind').textContent = String(row.type || '').toUpperCase();
        cell.querySelector('.sl-fm-tile-cap b').textContent = row.file;
        cell.querySelector('.sl-fm-tile-cap small').textContent = row.sizetext + ' · ' + row.timetext;
        setPickBox(id, cell, row, num);
        dial = getDial(id, row, num);
        if (dial) cell.appendChild(dial);
        return cell;
    }

    function getRow(id, row, num) {
        var line = api.getTpl(id, 'fm-row');
        var meta;
        var dial;
        if (!line) return null;
        line.setAttribute('data-sl-num', String(num));
        line.setAttribute('data-editor', id);
        setShotCell(id, line.querySelector('.sl-fm-row-thumb'), row, num);
        line.querySelector('.sl-fm-row-name').textContent = row.file;
        line.querySelector('.sl-fm-row-name').title = row.file;
        meta = line.querySelectorAll('.sl-fm-row-meta');
        meta[0].textContent = String(row.type || '').toUpperCase();
        meta[1].textContent = row.sizetext;
        meta[2].textContent = row.timetext;
        setPickBox(id, line, row, num);
        dial = getDial(id, row, num);
        if (dial) line.appendChild(dial);
        return line;
    }

    function getProps(id, row) {
        var out = [
            [getLab(id, 'name', ''), row.file],
            [getLab(id, 'type', ''), row.kind + (row.type ? ' · ' + row.type : '')],
            [getLab(id, 'size', ''), row.sizetext]
        ];
        if (row.width && row.height) out.push([getLab(id, 'dim', ''), row.width + ' × ' + row.height]);
        out.push([getLab(id, 'date', ''), row.timetext]);
        // The mode and the account arrive for a module moderator alone, and a host that cannot answer for the account sends none
        if (row.perms) out.push([getLab(id, 'perms', ''), row.perms]);
        if (row.owner) out.push([getLab(id, 'owner', ''), row.owner]);
        out.push([getLab(id, 'addr', ''), row.url || row.path]);
        return out;
    }

    function setProps(id, box, rows) {
        if (!box) return;
        box.replaceChildren();
        rows.forEach(function(row) {
            var line = api.getTpl(id, 'fm-prop');
            if (!line) return;
            line.firstElementChild.textContent = row[0];
            line.lastElementChild.textContent = row[1];
            line.lastElementChild.title = row[1];
            box.appendChild(line);
        });
    }

    // The current object is the one whose properties stand on the right; it follows a click on a row or a tile and never follows a mark
    function setCurrent(id, num) {
        var room = getRoom(id);
        var row = room.view[num];
        var img = getSlot(id, 'propsimg');
        var el = getPanel(id);
        if (!row || !el) return;
        room.cur = num;
        // The tile and the row alone carry the mark of the current object: the items of a fan carry the same number and would wear it too
        Array.prototype.forEach.call(el.querySelectorAll('.sl-fm-cell, .sl-fm-row'), function(one) {
            one.classList.toggle('sl-is-current', one.getAttribute('data-sl-num') === String(num));
        });
        if (img) {
            img.replaceChildren();
            img.appendChild((row.image && row.thumb) ? getShotImage(row) : getIconNode(row));
            // The picture beside the properties opens the same gallery a tile opens, on the same object and with the same walk
            if (row.able && row.able.preview) img.appendChild(getZoomNode(id, num));
        }
        setProps(id, getSlot(id, 'propslist'), getProps(id, row));
        setPropsActs(id, row, num);
        setInfo(id, row.file + ' · ' + row.sizetext);
    }

    // The panel of an object offers what the object offers, which is the fan its own row and tile carry: one builder,
    // so an action the descriptor withholds is missing from all three at once
    function setPropsActs(id, row, num) {
        var box = getSlot(id, 'propsacts');
        var dial = box ? getDial(id, row, num) : null;
        if (!box) return;
        box.replaceChildren();
        if (dial) box.appendChild(dial);
    }

    // The overlay of the properties picture is the overlay of a tile: one class, one attribute and the same route into the gallery
    function getZoomNode(id, num) {
        var el = doc.createElement('a');
        var icon = doc.createElement('i');
        icon.className = 'bi bi-arrows-fullscreen';
        icon.setAttribute('aria-hidden', 'true');
        el.className = 'sl-fm-zoom';
        el.href = '#';
        el.title = getLab(id, 'preview', '');
        el.setAttribute('data-sl-zoom', String(num));
        el.setAttribute('data-editor', String(id));
        el.appendChild(icon);
        return el;
    }

    function getIconNode(row) {
        var el = doc.createElement('i');
        el.className = 'bi bi-' + getIcon(row);
        el.setAttribute('aria-hidden', 'true');
        return el;
    }

    function getShotImage(row) {
        var el = doc.createElement('img');
        el.src = row.thumb || row.url;
        el.alt = row.file;
        return el;
    }

    // The marks live in one set of names, so the tile and the row of one file always show the same state
    function setPicks(id) {
        var room = getRoom(id);
        var el = getPanel(id);
        var bulk = getSlot(id, 'bulk');
        var count = getSlot(id, 'bulkcount');
        var all = getSlot(id, 'pickall');
        var okay = el ? el.querySelector('[data-sl-act="apply"]') : null;
        var num = room.pick.length;
        var one;
        if (!el) return;
        Array.prototype.forEach.call(el.querySelectorAll('[data-sl-pick]'), function(box) {
            var row = room.view[parseInt(box.getAttribute('data-sl-pick'), 10)];
            box.checked = !!row && room.pick.indexOf(row.path) >= 0;
        });
        Array.prototype.forEach.call(el.querySelectorAll('[data-sl-sent]'), function(box) {
            box.checked = room.pick.indexOf(box.getAttribute('data-sl-sent')) >= 0;
        });
        if (bulk) bulk.hidden = num === 0;
        if (count) count.textContent = getLab(id, 'marked', '') + ': ' + num;
        // One mark speaks in the singular, because "insert as images" about a single file reads as a defect
        one = el.querySelector('[data-sl-bulk="image"] span');
        if (one) one.textContent = (num === 1) ? getLab(id, 'insert', '') : getLab(id, 'insimgs', '');
        one = el.querySelector('[data-sl-bulk="attach"] span');
        if (one) one.textContent = (num === 1) ? getLab(id, 'insobj', '') : getLab(id, 'insobjs', '');
        one = el.querySelector('[data-sl-bulk="delete"]');
        if (one) one.setAttribute('data-sl-ask', getText(getLab(id, 'askdels', '%d'), num));
        if (all) {
            all.checked = num > 0 && num === room.view.length;
            all.indeterminate = num > 0 && num < room.view.length;
        }
        if (okay) okay.disabled = room.pane !== 'url' && num === 0 && !(room.pane === 'up' && room.take);
    }

    // A mark belongs to the file and not to the box drawing it, so it is set on the path of the object and the row and the tile read it back from the one set of names
    function setPickOne(id, num, on) {
        var room = getRoom(id);
        var row = room.view[num];
        var at;
        if (!row) return;
        at = room.pick.indexOf(row.path);
        if (on === null) on = at < 0;
        if (on && at < 0) room.pick.push(row.path);
        if (!on && at >= 0) room.pick.splice(at, 1);
        setPicks(id);
    }

    function setPickSpan(id, from, to) {
        var at = Math.min(from, to);
        var end = Math.max(from, to);
        for (; at <= end; at++) setPickOne(id, at, true);
    }

    // The keys walk what the eye sees: whichever of the three drawings of the catalogue is on screen is the one the arrows step through
    function getWalk(id) {
        var el = getPanel(id);
        var out = [];
        if (!el) return out;
        Array.prototype.forEach.call(el.querySelectorAll('.sl-fm-row[data-sl-num], .sl-fm-cell[data-sl-num]'), function(one) {
            if (one.offsetParent !== null) out.push(one);
        });
        return out;
    }

    // How far one key carries depends on the drawing: rows walk by one, tiles walk by one across and by a whole row of them down, counted off the drawing itself
    function getWalkCols(walk) {
        var top = walk.length ? walk[0].getBoundingClientRect().top : 0;
        var num = 0;
        walk.forEach(function(one) {
            if (Math.abs(one.getBoundingClientRect().top - top) < 4) num++;
        });
        return Math.max(1, num);
    }

    function getWalkTurn(walk, at, key) {
        var wide = walk.length && walk[0].classList.contains('sl-fm-cell') ? getWalkCols(walk) : 1;
        if (key === 'Home') return 0;
        if (key === 'End') return walk.length - 1;
        if (key === 'ArrowLeft') return wide > 1 ? at - 1 : at;
        if (key === 'ArrowRight') return wide > 1 ? at + 1 : at;
        if (key === 'ArrowUp') return at - wide;
        return at + wide;
    }

    // An arrow moves the current object, the shift with it drags the marks along, a space marks one and Enter does with it what the window exists for: it inserts it
    function setWalkKeys(id, ev) {
        var room = getRoom(id);
        var walk = getWalk(id);
        var keys = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Home', 'End'];
        var at = -1;
        var to;
        var num;
        var row;
        if (!walk.length) return false;
        // The object under the keys is the object holding the focus, and the current one only when the focus stands somewhere else in the catalogue
        walk.forEach(function(one, i) {
            if (parseInt(one.getAttribute('data-sl-num'), 10) === room.cur && at < 0) at = i;
            if (one === doc.activeElement) at = i;
        });
        if (at < 0) at = 0;
        num = parseInt(walk[at].getAttribute('data-sl-num'), 10);
        row = room.view[num] || {};
        if (ev.key === 'Enter') {
            if (!row.able || !row.able.insert) return false;
            setAct(id, (row.image ? 'image' : 'attach'), num);
            return true;
        }
        if (ev.key === ' ') {
            room.anch = num;
            setPickOne(id, num, null);
            return true;
        }
        if (keys.indexOf(ev.key) < 0) return false;
        to = Math.min(Math.max(getWalkTurn(walk, at, ev.key), 0), walk.length - 1);
        num = parseInt(walk[to].getAttribute('data-sl-num'), 10);
        if (ev.shiftKey && room.anch < 0) room.anch = parseInt(walk[at].getAttribute('data-sl-num'), 10);
        setCurrent(id, num);
        walk[to].focus();
        if (ev.shiftKey) setPickSpan(id, room.anch, num);
        return true;
    }

    // The expanded catalogue is drawn whole and scrolled, as the administrative one is: a directory is one answer and
    // a walk of pages over an answer already in hand buys nothing. The compact view still shows the last few files
    function getPageRows(id) {
        var room = getRoom(id);
        return room.full ? room.view : room.view.slice(0, parseInt(getOpt(id).last || 6, 10));
    }

    // The list is not a table, so Tablesort cannot drive it; what is reused is the contract the eye reads — the same
    // three head classes and the same order the printed column stands for, with the raw figure sorted and not its text
    function getSortKey(row, key) {
        if (key === 'name') return String(row.file || '').toLowerCase();
        if (key === 'type') return String(row.kind || '') + ' ' + String(row.type || '');
        if (key === 'size') return Number(row.size) || 0;
        return Number(row.time) || 0;
    }

    function setSortRows(id) {
        var sort = getRoom(id).sort;
        var rows = getRoom(id).view;
        rows.sort(function(one, two) {
            var a = getSortKey(one, sort.key);
            var b = getSortKey(two, sort.key);
            if (a === b) return String(one.file).localeCompare(String(two.file));
            return (a > b ? 1 : -1) * sort.dir;
        });
    }

    function setSortHead(id) {
        var el = getPanel(id);
        var sort = getRoom(id).sort;
        if (!el) return;
        Array.prototype.forEach.call(el.querySelectorAll('[data-sl-sort]'), function(one) {
            var on = one.getAttribute('data-sl-sort') === sort.key;
            one.className = on ? (sort.dir > 0 ? 'sl-sort-asc' : 'sl-sort-desc') : 'sl-sort';
            one.setAttribute('aria-sort', on ? (sort.dir > 0 ? 'ascending' : 'descending') : 'none');
        });
    }

    function setSortRun(id, key) {
        var sort = getRoom(id).sort;
        // A column asked for again turns over; a new one starts the way that column reads first — names up, figures down
        sort.dir = (sort.key === key) ? -sort.dir : (key === 'name' || key === 'type' ? 1 : -1);
        sort.key = key;
        setList(id);
    }

    function setList(id) {
        var room = getRoom(id);
        var opt = getOpt(id);
        var tiles = getSlot(id, 'tiles');
        var rows = getSlot(id, 'rows');
        var full = getSlot(id, 'fulltiles');
        var more = getSlot(id, 'more');
        var find = String(room.find || '').toLowerCase();
        var page;
        if (!tiles || !rows || !full) return;
        room.view = room.files.filter(function(row) {
            if (find !== '' && String(row.file).toLowerCase().indexOf(find) < 0) return false;
            if (room.kind === 'image') return !!row.image;
            if (room.kind === 'other') return !row.image;
            return true;
        });
        room.pick = room.pick.filter(function(path) {
            return room.view.some(function(row) {
                return row.path === path;
            });
        });
        setSortRows(id);
        // A drawing of the catalogue again means other places for the same files, so the object a range of marks would start from is no longer the object it was
        room.anch = -1;
        page = getPageRows(id);
        tiles.replaceChildren();
        rows.replaceChildren();
        full.replaceChildren();
        page.forEach(function(row) {
            var num = room.view.indexOf(row);
            var tile = getTile(id, row, num);
            var line = getRow(id, row, num);
            var wide = getTile(id, row, num);
            if (tile) tiles.appendChild(tile);
            if (line) rows.appendChild(line);
            if (wide) full.appendChild(wide);
        });
        if (more) {
            more.textContent = (room.view.length > parseInt(opt.last || 6, 10))
                ? getText(getLab(id, 'more', '%1$d / %2$d'), page.length, room.view.length)
                : '';
        }
        setPicks(id);
        setSortHead(id);
        if (room.view.length) setCurrent(id, Math.min(room.cur, room.view.length - 1));
        setState(id, getListState(id));
    }

    function getListState(id) {
        var room = getRoom(id);
        if (room.state === 'skel' || room.state === 'fail') return room.state;
        if (room.files.length === 0) return 'empty';
        if (room.view.length === 0) return 'filter';
        return 'ready';
    }

    // The catalogue has five states and the settled one is only one of them; an empty catalogue and an empty filter say different words on purpose (§31)
    function setState(id, name) {
        var room = getRoom(id);
        var rows = {
            empty: ['folder', getLab(id, 'empty', ''), getLab(id, 'emptywhy', ''), ''],
            filter: ['search', getLab(id, 'none', ''), getLab(id, 'nonewhy', ''), getLab(id, 'reset', '')],
            fail: ['exclamation-triangle', getLab(id, 'fail', ''), getLab(id, 'failwhy', ''), getLab(id, 'retry', '')]
        };
        var row = rows[name];
        var el = getPanel(id);
        var box;
        room.state = name;
        if (!el || !el.querySelector('[data-sl-view="empty"]')) return;
        if (row) {
            getSlot(id, 'emptyicon').className = 'bi bi-' + row[0];
            getSlot(id, 'emptytitle').textContent = row[1];
            getSlot(id, 'emptytext').textContent = row[2];
            box = getSlot(id, 'emptyact');
            box.textContent = row[3];
            box.hidden = row[3] === '';
            box.setAttribute('data-sl-act', (name === 'fail') ? 'retry' : 'reset');
            getSlot(id, 'emptybox').classList.toggle('sl-is-fail', name === 'fail');
        }
        setView(id, room.full);
    }

    // The expanded window is also the expanded catalogue: the tiles give way to the filter, the list and the properties, and the empty result keeps the toolbar it belongs to
    function setView(id, full) {
        var room = getRoom(id);
        var el = getPanel(id);
        var lib = el ? el.querySelector('[data-sl-pane="lib"].sl-fm-pane') : null;
        var open = room.state === 'ready';
        var none = room.state === 'filter';
        room.full = !!full;
        if (!lib) return;
        lib.querySelector('[data-sl-view="compact"]').hidden = !open || room.full;
        lib.querySelector('[data-sl-view="full"]').hidden = (!open && !none) || !room.full;
        lib.querySelector('[data-sl-view="skel"]').hidden = room.state !== 'skel';
        lib.querySelector('[data-sl-view="empty"]').hidden = open || room.state === 'skel';
    }

    function setPane(id, name) {
        var room = getRoom(id);
        var opt = getOpt(id);
        var el = getPanel(id);
        // A place storing a file name carries no address tab in the markup, so counting one here would open the window on a pane that was never drawn
        var able = { up: !!opt.canupload, url: !!opt.canlink, emb: !!opt.canembed, lib: !!opt.canlist };
        var panes = opt.panes || {};
        var okay;
        var meta;
        if (!el) return;
        // A closed section never takes the window: it opens on the first one the settings left open
        if (name === '' || !able[name]) {
            name = '';
            ['up', 'url', 'emb', 'lib'].forEach(function(key) {
                if (name === '' && able[key]) name = key;
            });
        }
        if (name === '') return;
        room.pane = name;
        Array.prototype.forEach.call(el.querySelectorAll('.sl-fm-rail-item'), function(one) {
            one.setAttribute('aria-selected', String(one.getAttribute('data-sl-pane') === name));
        });
        Array.prototype.forEach.call(el.querySelectorAll('.sl-fm-pane'), function(one) {
            one.classList.toggle('sl-is-open', one.getAttribute('data-sl-pane') === name);
        });
        meta = panes[name] || ['', ''];
        el.querySelector('[data-sl-slot="title"]').textContent = meta[0];
        el.querySelector('[data-sl-slot="lead"]').textContent = meta[1];
        okay = el.querySelector('[data-sl-act="apply"]');
        if (okay) okay.disabled = name !== 'url' && room.pick.length === 0 && !(name === 'up' && room.take);
        setRoom(id);
    }

    function setQuota(id, json) {
        var room = getRoom(id);
        var rail = getSlot(id, 'quota');
        var num = getSlot(id, 'quotanum');
        var fill = getSlot(id, 'quotafill');
        var note = getSlot(id, 'railnote');
        var text = getText(getLab(id, 'quota', '%1$s / %2$s'), json.usedtext, json.quotatext);
        var part = (json.quota > 0) ? Math.min(100, Math.round((json.used / json.quota) * 100)) : 0;
        if (rail) rail.textContent = text;
        if (num) num.textContent = text;
        if (fill) fill.style.width = part + '%';
        if (note) note.textContent = getText(getLab(id, 'mynote', '%d'), room.files.length);
    }

    // The record meter answers for the column the text is stored in, not for the upload quota of the module: it counts
    // what the editor holds right now against that capacity, and an empty field has to read as empty
    function setRoom(id) {
        var opt = getOpt(id);
        var fill = getSlot(id, 'roomfill');
        var num = getSlot(id, 'roomnum');
        var cap = parseInt(opt.room || 0, 10);
        var ed = getEditor(id);
        var text = (ed && ed.getMarkdown) ? String(ed.getMarkdown() || '') : '';
        var used = getByteSize(text);
        var part = (cap > 0) ? Math.min(100, Math.round((used / cap) * 100)) : 0;
        if (fill) fill.style.width = part + '%';
        if (num) num.textContent = getText(getLab(id, 'quota', '%1$s / %2$s'), getSizeText(used), getSizeText(cap));
    }

    // A character of the text is one byte only while it stays inside ASCII, and the column counts bytes
    function getByteSize(text) {
        return win.Blob ? new win.Blob([text]).size : text.length;
    }

    // Reading the catalogue again is the one action whose result can look exactly like doing nothing, so the button says it is
    // working and the status line says what came back: a listing that did not change is an answer and has to read as one
    function setBusyList(id, on) {
        var el = getPanel(id);
        var act = el ? el.querySelector('[data-sl-act="refresh"]') : null;
        if (!act) return;
        act.disabled = on;
    }

    function getFiles(id) {
        var opt = getOpt(id);
        var room = getRoom(id);
        if (!opt.files) return;
        room.state = 'skel';
        setView(id, room.full);
        setBusyList(id, true);
        getReq(id, opt.files, new FormData()).then(function(json) {
            setBusyList(id, false);
            if (!json || !json.ok) {
                room.state = 'fail';
                setState(id, 'fail');
                setInfo(id, (json && json.error) || getLab(id, 'fail', ''));
                return;
            }
            room.state = 'ready';
            room.files = json.files || [];
            setList(id);
            setQuota(id, json);
            setInfo(id, getText(getLab(id, 'mynote', '%d'), room.files.length));
        }).catch(function() {
            setBusyList(id, false);
            room.state = 'fail';
            setState(id, 'fail');
            setInfo(id, getLab(id, 'fail', ''));
        });
    }

    // No changing action runs without an answer: the object stays busy until the server has spoken and no row disappears on hope alone
    // Only the tile and the row of the object are covered: the items of its own fan carry the same number, and an overlay inside one of them would hide the action it names
    function setBusy(id, num, on) {
        var el = getPanel(id);
        var pick = '.sl-fm-cell[data-sl-num="' + num + '"], .sl-fm-row[data-sl-num="' + num + '"]';
        if (!el) return;
        Array.prototype.forEach.call(el.querySelectorAll(pick), function(one) {
            var box = one.querySelector('.sl-fm-busy');
            if (on && !box) {
                box = api.getTpl(id, 'fm-busy');
                if (box) one.appendChild(box);
                return;
            }
            if (!on && box) box.remove();
        });
    }

    function setFileRun(id, way, paths, num) {
        var opt = getOpt(id);
        var url = (way === 'delete') ? opt.remove : opt.archive;
        var data = new FormData();
        if (!url || !paths.length) return;
        paths.forEach(function(path) {
            data.append('mark[]', path);
        });
        if (num >= 0) setBusy(id, num, true);
        getReq(id, url, data).then(function(json) {
            if (num >= 0) setBusy(id, num, false);
            if (!json || !json.ok) {
                setMsg(id, (json && json.error) || getLab(id, 'load', ''), true);
                return;
            }
            setInfo(id, json.done + ' / ' + json.total);
            getFiles(id);
        }).catch(function() {
            if (num >= 0) setBusy(id, num, false);
            setMsg(id, getLab(id, 'load', ''), true);
        });
    }

    function setAct(id, way, num) {
        var room = getRoom(id);
        var row = room.view[num];
        var shot = getShot(id);
        if (!row) return;
        if (shot && shot.open && way !== 'down') shot.close();
        if (way === 'image' || way === 'attach') {
            askInsert(id, way, [row], row.file);
            return;
        }
        if (way === 'delete' || way === 'zip') setFileRun(id, way, [row.path], num);
    }

    // The side and the caption belong to the object and not to the pane it was picked in, so every route into the text
    // stops at the same small window: one answer covers the whole batch, and an empty caption falls back to the file name
    function askInsert(id, way, rows, title) {
        var el = getOpts(id);
        var one;
        if (!rows.length) return;
        // Every route into a text stops here, so a form row is answered here too: it takes no side and no caption, and the options window never opens for it
        if (checkField(id)) {
            setFieldTake(id, '', null);
            if (setFieldPick(id, rows[0].path ? 'path' : 'url', rows[0])) setPanel(id, false);
            return;
        }
        if (!el) {
            addInsertRows(id, way, rows, '', title || '');
            return;
        }
        getRoom(id).want = { way: way, rows: rows, at: 0, title: title || '' };
        one = el.querySelector('[data-sl-opts="align"] input[value=""]');
        if (one) one.checked = true;
        setInsertStep(id);
        win.setWindowOpen(el);
    }

    // A batch is answered file by file, because a side and a caption belong to the picture and not to the marking that
    // collected it; the side stays on what the previous answer chose, which is the half of the pair that usually repeats
    function setInsertStep(id) {
        var want = getRoom(id).want;
        var name = getOptsPart(id, 'name');
        var note = getOptsPart(id, 'info');
        var field = getOptsPart(id, 'title');
        var row = want ? want.rows[want.at] : null;
        if (!row) return;
        if (name) name.textContent = row.file;
        if (note) note.textContent = (want.rows.length > 1) ? getText(getLab(id, 'quota', '%1$s / %2$s'), want.at + 1, want.rows.length) : '';
        if (field) field.value = (want.at === 0) ? want.title : '';
        if (field) field.focus();
    }

    function addInsert(id) {
        var room = getRoom(id);
        var want = room.want;
        var field = getOptsPart(id, 'title');
        var mark = getOptsPart(id, 'align');
        var sel = mark ? mark.querySelector('input:checked') : null;
        if (!want) return;
        addInsertRows(id, want.way, [want.rows[want.at]], sel ? sel.value : '', field ? String(field.value || '').trim() : '');
        want.at++;
        if (want.at < want.rows.length) {
            setInsertStep(id);
            return;
        }
        room.want = null;
        room.pick = [];
        room.sent = [];
        setPicks(id);
        win.setWindowClose(getOpts(id));
        setPanel(id, false);
    }

    function addInsertRows(id, way, rows, align, title) {
        rows.forEach(function(row) {
            if (way === 'image' && row.image) addImage(id, row.url, title || row.file, align);
            else addAttach(id, row.file, title || row.file, align);
        });
    }

    function setBulk(id, way) {
        var room = getRoom(id);
        // The upload pane marks what it has just brought in, and those objects are in the catalogue and not yet on the drawn page
        var src = room.pane === 'up' ? room.files : room.view;
        var alt = room.pane === 'up' ? getAltText(id, 'up') : '';
        var rows = src.filter(function(row) {
            return room.pick.indexOf(row.path) >= 0;
        });
        if (way === 'clear') {
            room.pick = [];
            setPicks(id);
            return;
        }
        if (way === 'delete' || way === 'zip') {
            setFileRun(id, way, room.pick.slice(), -1);
            room.pick = [];
            setPicks(id);
            return;
        }
        askInsert(id, way, rows, alt);
    }

    // The gallery walks the objects that can be previewed at all, so the counter counts them and never the length of the list
    function getShotList(id) {
        return getRoom(id).view.filter(function(row) {
            return row.able && row.able.preview;
        });
    }

    function setModal(id, num) {
        var room = getRoom(id);
        var shot = getShot(id);
        var list = getShotList(id);
        var row = room.view[num];
        var at = list.indexOf(row);
        var img;
        var down;
        if (!shot || !row || at < 0) return;
        room.cur = num;
        shot.querySelector('[data-sl-shot-name]').textContent = row.file;
        img = shot.querySelector('[data-sl-shot-img]');
        img.src = row.url;
        img.alt = row.file;
        shot.querySelector('[data-sl-shot-num]').textContent = (at + 1) + ' / ' + list.length;
        setProps(id, shot.querySelector('[data-sl-shot-rows]'), getProps(id, row));
        down = shot.querySelector('[data-sl-shot-down]');
        if (down) down.href = row.url;
        Array.prototype.forEach.call(shot.querySelectorAll('[data-sl-shot-act]'), function(one) {
            one.setAttribute('data-sl-num', String(num));
            if (one.getAttribute('data-sl-shot-act') === 'delete') one.setAttribute('data-sl-ask', getText(getLab(id, 'askdel', '%s'), row.file));
        });
        if (win.setWindowOpen) win.setWindowOpen(shot);
    }

    function setStep(id, way) {
        var room = getRoom(id);
        var list = getShotList(id);
        var at = list.indexOf(room.view[room.cur]);
        var next;
        if (!list.length) return;
        next = list[(at + way + list.length) % list.length];
        setModal(id, room.view.indexOf(next));
    }

    function setAsk(id, node, run) {
        var text = node.getAttribute('data-sl-ask');
        if (!text) {
            run();
            return;
        }
        if (win.setConfirmTask) win.setConfirmTask(text, run);
        else if (win.confirm(text)) run();
    }

    // Outside the editor the window only picks and the form uploads, so nothing here reads a file: the pick is written into the row the button stands in and rides the ordinary submit
    function getField(id) {
        return fields.get(String(id)) || null;
    }

    function checkField(id) {
        return fields.has(String(id));
    }

    function getFieldPart(id, name) {
        var el = getField(id);
        return el ? el.querySelector('[data-sl-field="' + name + '"]') : null;
    }

    // Name and weight, because those are the two things a visitor checks against the limits the row spells out beside the button
    function getFieldText(take) {
        var one = (take && take.data) || {};
        if (take.mode === 'file') return String(one.name || '') + ' · ' + getSizeText(one.size);
        if (take.mode === 'path') return String(one.file || '') + (one.sizetext ? ' · ' + one.sizetext : '');
        return String(one.url || '');
    }

    // What the window holds is not yet what the form carries: the chip in the foot says what the insert would hand over, and the insert is what hands it over
    function setFieldTake(id, mode, data) {
        var room = getRoom(id);
        var chip = getSlot(id, 'pick');
        var el = getPanel(id);
        var okay = el ? el.querySelector('[data-sl-act="apply"]') : null;
        room.take = mode ? { mode: mode, data: data } : null;
        if (chip) {
            chip.textContent = room.take ? getFieldText(room.take) : '';
            chip.hidden = !room.take;
        }
        if (okay && room.pane === 'up') okay.disabled = !room.take;
    }

    // The three outcomes are exclusive by construction, so writing one clears the other two here rather than trusting the window to have handed back a single answer
    // It answers whether the write landed, because a chip drawn over a field that stayed empty states a file the form is not carrying, which is the one lie this row must not tell
    function setFieldPick(id, mode, data) {
        var file = getFieldPart(id, 'file');
        var path = getFieldPart(id, 'path');
        var link = getFieldPart(id, 'url');
        var chip = getFieldPart(id, 'chip');
        var name = getFieldPart(id, 'name');
        var one = data || {};
        var done = mode === '';
        var box;
        if (!getField(id)) return false;
        if (file) file.value = '';
        if (path) path.value = '';
        if (link) link.value = '';
        // A file object cannot be assigned to a file field, so it travels through the one carrier the browser accepts for it
        if (mode === 'file' && file && win.DataTransfer) {
            box = new win.DataTransfer();
            box.items.add(one);
            file.files = box.files;
            done = true;
        }
        if (mode === 'path' && path) {
            path.value = String(one.path || '');
            done = true;
        }
        if (mode === 'url' && link) {
            link.value = String(one.url || '');
            done = true;
        }
        if (!done) setMsg(id, getLab(id, 'upload', 'Upload failed'), true);
        if (name) name.textContent = (done && mode) ? getFieldText({ mode: mode, data: one }) : '';
        if (chip) chip.hidden = !(done && mode);
        return done;
    }

    // One cross clears all three, because a row has to be able to go back to carrying nothing and a leftover of any of them is a file the visitor did not choose
    function deleteFieldPick(id) {
        setFieldPick(id, '', null);
        setFieldTake(id, '', null);
        setMsg(id, '', false);
    }

    // The window closes on a pick that landed and stays open on one that did not, because the message saying why is drawn inside it
    function setFieldApply(id) {
        var take = getRoom(id).take;
        if (!take) return;
        if (setFieldPick(id, take.mode, take.data)) setPanel(id, false);
    }

    // Nothing is read until the form is submitted, so the rule is checked at the pick or the visitor learns of a refusal only after the page they filled in has gone
    // The words are the ones the server would answer with, so a refusal reads the same whichever side of the submit it was raised on
    function checkFieldFile(id, file, done) {
        var opt = getOpt(id);
        var list = opt.exts || [];
        var max = parseInt(opt.maxbytes || 0, 10);
        var wid = parseInt(opt.maxwidth || 0, 10);
        var hei = parseInt(opt.maxheight || 0, 10);
        var name = String(file.name || '');
        var ext = name.indexOf('.') > 0 ? name.split('.').pop().toLowerCase() : '';
        var img;
        if (list.length && list.indexOf(ext) < 0) {
            setMsg(id, getLab(id, 'badtype', 'Unsupported file type'), true);
            return;
        }
        if (max > 0 && file.size > max) {
            setMsg(id, getLab(id, 'big', 'File is too big'), true);
            return;
        }
        if (wid <= 0 || hei <= 0 || String(file.type || '').indexOf('image/') !== 0) {
            done();
            return;
        }
        // The bounds belong to the picture and not to the file, so they can only be answered once the browser has decoded it
        img = new win.Image();
        img.onload = function() {
            win.URL.revokeObjectURL(img.src);
            if (img.naturalWidth > wid || img.naturalHeight > hei) setMsg(id, getLab(id, 'toobig', 'Image is too large'), true);
            else done();
        };
        img.onerror = function() {
            win.URL.revokeObjectURL(img.src);
            setMsg(id, getLab(id, 'badtype', 'Unsupported file type'), true);
        };
        img.src = win.URL.createObjectURL(file);
    }

    function addPanel(id) {
        var el = getPanel(id);
        if (!el) return;
        setPanel(id, !el.open);
    }

    function addHook(id, ed) {
        if (!ed || typeof ed.addHook !== 'function') return;
        ed.addHook('addImageBlobHook', function(blob, done) {
            var opt = getOpt(id);
            if (opt.canupload) addFileList(id, [blob], 'upload');
            else addEmbed(id, blob, done);
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
        var zone;
        var id;
        var num;
        var room;
        var row;
        var path;
        if (!el.classList) return;
        if (el.classList.contains('js-slaed-upload-file')) {
            zone = el.closest('.js-slaed-upload-drop');
            addFileList(el.getAttribute('data-editor'), el.files, zone ? zone.getAttribute('data-sl-mode') : 'upload');
            el.value = '';
            return;
        }
        id = el.getAttribute('data-editor');
        if (!id) return;
        if (el.hasAttribute('data-sl-pick')) {
            room = getRoom(id);
            num = parseInt(el.getAttribute('data-sl-pick'), 10);
            row = room.view[num];
            if (!row) return;
            if (el.checked && room.pick.indexOf(row.path) < 0) room.pick.push(row.path);
            if (!el.checked) room.pick = room.pick.filter(function(one) { return one !== row.path; });
            setPicks(id);
            return;
        }
        if (el.hasAttribute('data-sl-sent')) {
            room = getRoom(id);
            path = el.getAttribute('data-sl-sent');
            if (el.checked && room.pick.indexOf(path) < 0) room.pick.push(path);
            if (!el.checked) room.pick = room.pick.filter(function(one) { return one !== path; });
            setPicks(id);
            return;
        }
        if (el.hasAttribute('data-sl-slot') && el.getAttribute('data-sl-slot') === 'pickall') {
            room = getRoom(id);
            room.pick = el.checked ? room.view.map(function(one) { return one.path; }) : [];
            setPicks(id);
        }
    });

    doc.addEventListener('input', function(ev) {
        var el = ev.target;
        var id;
        if (!el.hasAttribute || !el.hasAttribute('data-sl-slot') || el.getAttribute('data-sl-slot') !== 'find') return;
        id = el.getAttribute('data-editor');
        getRoom(id).find = String(el.value || '');
        getRoom(id).page = 0;
        setList(id);
    });

    function getPanelOwn(node) {
        var el = node && node.closest ? node.closest('.sl-fm-win') : null;
        return el ? el.getAttribute('data-editor-id') : '';
    }

    // The catalogue takes a drop of its own: a file dragged onto the storage belongs to the same module, and the window turns to the upload, where the queue and its reasons live
    function getDropZone(ev) {
        var zone = ev.target.closest ? ev.target.closest('.js-slaed-upload-drop') : null;
        var lib = ev.target.closest ? ev.target.closest('.sl-fm-pane[data-sl-pane="lib"]') : null;
        if (zone) return zone;
        return (lib && getOpt(getPanelOwn(lib)).canupload) ? lib : null;
    }

    doc.addEventListener('dragover', function(ev) {
        var zone = getDropZone(ev);
        if (!zone) return;
        ev.preventDefault();
        if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'copy';
        zone.classList.add('sl-drag-over');
    });

    doc.addEventListener('dragleave', function(ev) {
        var zone = getDropZone(ev);
        if (!zone || (ev.relatedTarget && zone.contains(ev.relatedTarget))) return;
        zone.classList.remove('sl-drag-over');
    });

    doc.addEventListener('drop', function(ev) {
        var zone = getDropZone(ev);
        var id;
        if (!zone) return;
        ev.preventDefault();
        zone.classList.remove('sl-drag-over');
        id = zone.getAttribute('data-editor') || getPanelOwn(zone);
        if (!zone.classList.contains('js-slaed-upload-drop')) setPane(id, 'up');
        addFileList(id, ev.dataTransfer ? ev.dataTransfer.files : [], zone.getAttribute('data-sl-mode') || 'upload');
    });

    doc.addEventListener('keydown', function(ev) {
        var zone = ev.target.closest ? ev.target.closest('.js-slaed-upload-drop') : null;
        var shot = ev.target.closest ? ev.target.closest('dialog[data-sl-shot="editor"]') : null;
        var el = ev.target.closest ? ev.target.closest('.sl-fm-win') : null;
        var file;
        var lib;
        var id;
        if (shot) {
            id = shot.getAttribute('data-editor');
            if (ev.key === 'ArrowLeft') setStep(id, -1);
            if (ev.key === 'ArrowRight') setStep(id, 1);
            return;
        }
        // Only the catalogue answers to these keys: the rail, the fields and the fan of an object stand in the same window and keep the keys they came with
        lib = ev.target.closest ? ev.target.closest('.sl-fm-pane[data-sl-pane="lib"]') : null;
        if (el && lib && !ev.target.matches('input, textarea, select, button, a')) {
            if (setWalkKeys(getPanelOwn(el), ev)) ev.preventDefault();
            return;
        }
        if (!zone || (ev.key !== 'Enter' && ev.key !== ' ')) return;
        ev.preventDefault();
        file = zone.querySelector('.js-slaed-upload-file');
        if (file) file.click();
    });

    // The context menu is the fan of the object and is opened for every fan of the project by the component; what belongs to the window is that the object becomes the current one
    doc.addEventListener('contextmenu', function(ev) {
        var el = ev.target.closest ? ev.target.closest('.sl-fm-row[data-sl-num], .sl-fm-cell[data-sl-num]') : null;
        if (!el) return;
        setCurrent(getPanelOwn(el), parseInt(el.getAttribute('data-sl-num'), 10));
    });

    // A modifier turns a press on an object into a mark: alone it marks and unmarks one, with the shift it marks everything between the object it started from and this one
    // The press is taken in the capture phase, because a plain press on the same object means the current object and a press on the box itself means the mark of that one
    doc.addEventListener('click', function(ev) {
        var el = (ev.shiftKey || ev.ctrlKey || ev.metaKey) && ev.target.closest ? ev.target.closest('.sl-fm-row[data-sl-num], .sl-fm-cell[data-sl-num], [data-sl-pick]') : null;
        var id = el ? getPanelOwn(el) : '';
        var num = el ? parseInt(el.getAttribute('data-sl-num') || el.getAttribute('data-sl-pick'), 10) : -1;
        var room;
        if (!el || !id || isNaN(num)) return;
        ev.preventDefault();
        ev.stopPropagation();
        room = getRoom(id);
        setCurrent(id, num);
        if (ev.shiftKey && room.anch >= 0) {
            setPickSpan(id, room.anch, num);
            return;
        }
        setPickOne(id, num, null);
        room.anch = num;
    }, true);

    doc.addEventListener('click', function(ev) {
        var el = ev.target;
        var zone = el.closest ? el.closest('.js-slaed-upload-drop') : null;
        var zoom = el.closest ? el.closest('[data-sl-zoom]') : null;
        var pane = el.closest ? el.closest('[data-sl-pane].sl-fm-rail-item') : null;
        var sort = el.closest ? el.closest('[data-sl-sort]') : null;
        var act = el.closest ? el.closest('[data-sl-act]') : null;
        var bulk = el.closest ? el.closest('[data-sl-bulk]') : null;
        var kind = el.closest ? el.closest('[data-sl-kind]') : null;
        var mode = el.closest ? el.closest('[data-sl-mode].sl-fm-mode') : null;
        var shot = el.closest ? el.closest('dialog[data-sl-shot="editor"]') : null;
        var wide;
        var walk = shot ? el.closest('[data-sl-shot-step]') : null;
        var pick = shot ? el.closest('[data-sl-shot-act]') : null;
        var item = el.closest ? el.closest('[data-sl-num]') : null;
        var button = el.closest ? el.closest('button, input[type="button"]') : null;
        var id;
        var room;
        if (zone && !el.classList.contains('js-slaed-upload-file')) {
            zone.querySelector('.js-slaed-upload-file').click();
            return;
        }
        if (zoom && zoom.hasAttribute('data-editor')) {
            ev.preventDefault();
            setModal(zoom.getAttribute('data-editor'), parseInt(zoom.getAttribute('data-sl-zoom'), 10));
            return;
        }
        if (pane) {
            setPane(pane.getAttribute('data-editor'), pane.getAttribute('data-sl-pane'));
            return;
        }
        if (sort && sort.hasAttribute('data-editor')) {
            setSortRun(sort.getAttribute('data-editor'), sort.getAttribute('data-sl-sort'));
            return;
        }
        if (walk) {
            setStep(shot.getAttribute('data-editor'), parseInt(walk.getAttribute('data-sl-shot-step'), 10));
            return;
        }
        if (pick) {
            ev.preventDefault();
            setActRun(shot.getAttribute('data-editor'), pick, pick.getAttribute('data-sl-shot-act'));
            return;
        }
        if (kind) {
            id = kind.getAttribute('data-editor');
            room = getRoom(id);
            room.kind = kind.getAttribute('data-sl-kind');
            Array.prototype.forEach.call(getPanel(id).querySelectorAll('[data-sl-kind]'), function(one) {
                one.setAttribute('aria-pressed', String(one === kind));
            });
            setList(id);
            return;
        }
        if (mode) {
            id = mode.getAttribute('data-editor');
            getRoom(id).mode = mode.getAttribute('data-sl-mode');
            Array.prototype.forEach.call(getPanel(id).querySelectorAll('[data-sl-mode].sl-fm-mode'), function(one) {
                one.setAttribute('aria-pressed', String(one === mode));
            });
            Array.prototype.forEach.call(getPanel(id).querySelectorAll('[data-sl-modeview]'), function(one) {
                one.hidden = one.getAttribute('data-sl-modeview') !== getRoom(id).mode;
            });
            return;
        }
        if (bulk) {
            id = bulk.getAttribute('data-editor');
            setAsk(id, bulk, function() {
                setBulk(id, bulk.getAttribute('data-sl-bulk'));
            });
            return;
        }
        if (act && act.hasAttribute('data-editor')) {
            ev.preventDefault();
            id = act.getAttribute('data-editor');
            setActRun(id, act, act.getAttribute('data-sl-act'));
            return;
        }
        if (item && item.hasAttribute('data-sl-num') && !el.closest('.sl-fm-pick')) {
            setCurrent(item.getAttribute('data-editor'), parseInt(item.getAttribute('data-sl-num'), 10));
            return;
        }
        // The canon toggles the window on this press; the catalogue inside it follows once the class is on, which is the next frame
        wide = el.closest ? el.closest('.sl-fm-win [data-sl-full]') : null;
        if (wide) {
            id = wide.closest('.sl-fm-win').getAttribute('data-editor-id');
            win.requestAnimationFrame(function() { setPanelView(id); });
        }
    });

    function setActRun(id, act, way) {
        var num = act.hasAttribute('data-sl-num') ? parseInt(act.getAttribute('data-sl-num'), 10) : getRoom(id).cur;
        var box;
        // The button and the cross of a form row stand outside the window and reach it by the same delegation everything inside it uses
        if (way === 'open') {
            addPanel(id);
            return;
        }
        if (way === 'clear') {
            deleteFieldPick(id);
            return;
        }
        if (way === 'refresh') {
            getFiles(id);
            return;
        }
        if (way === 'insopts') {
            addInsert(id);
            return;
        }
        if (way === 'apply') {
            // A picked file never became a row, so it is the one answer the shared insert path cannot carry and is handed over from what the window is holding
            if (checkField(id) && getRoom(id).pane === 'up') {
                setFieldApply(id);
                return;
            }
            if (getRoom(id).pane === 'url') addUrl(id);
            else setBulk(id, getRoom(id).pane === 'up' ? getInsertMode(id) : 'image');
            return;
        }
        if (way === 'full') {
            box = getPanel(id).querySelector('[data-sl-full]');
            setPane(id, 'lib');
            if (box && !getPanel(id).classList.contains('sl-is-full')) box.click();
            return;
        }
        if (way === 'retry') {
            getFiles(id);
            return;
        }
        if (way === 'reset') {
            getRoom(id).find = '';
            getRoom(id).kind = '';
            getRoom(id).page = 0;
            box = getSlot(id, 'find');
            if (box) box.value = '';
            Array.prototype.forEach.call(getPanel(id).querySelectorAll('[data-sl-kind]'), function(one) {
                one.setAttribute('aria-pressed', String(one.getAttribute('data-sl-kind') === ''));
            });
            setList(id);
            return;
        }
        if (way === 'stop' || way === 'jobstop') {
            deleteQueue(id);
            return;
        }
        setAsk(id, act, function() {
            setAct(id, way, num);
        });
    }

    api.options = api.options || {};
    api.getTpl = api.getTpl || function(id, name) {
        var opt = (api.options || {})[String(id)] || {};
        var root = doc.querySelector('.' + (opt.tpl || 'js-slaed-fm-tpl'));
        var tpl = root ? root.querySelector('template[data-tpl="' + name + '"]') : null;
        return tpl && tpl.content && tpl.content.firstElementChild ? tpl.content.firstElementChild.cloneNode(true) : null;
    };
    api.addUpload = function(id, ed, opt) {
        if (!opt) return;
        api.options[String(id)] = opt;
        edits.set(String(id), ed);
        addHook(id, ed);
        addBtn(id, ed);
        setPane(id, '');
    };
    // The second entry of the runtime: the window of a form row registers a box and no editor, so the four editor-only paths find null and disable themselves
    // The hook and the toolbar icon return on a missing editor by themselves, which is why a field installs neither and needs no guard of its own
    api.addField = function(id, node, opt) {
        if (!opt || !node) return;
        api.options[String(id)] = opt;
        fields.set(String(id), node);
        setPane(id, '');
    };
    win.SlaedFileManager = api;
})(window, document);
