(function () {
    'use strict';
    var picker = null;
    var names = null;
    var icontags = null;
    var iconterms = null;
    function getIconWords() {
        if (icontags !== null) return;
        icontags = {};
        iconterms = {};
        var base = 'templates/admin/assets/js/i18n/';
        fetch(base + 'icon-tags.json').then(function (res) { return res.json(); }).then(function (json) { icontags = json; }).catch(function () {});
        var lang = (document.documentElement.lang || 'en').slice(0, 2);
        if (lang !== 'en') {
            fetch(base + 'icon-terms-' + lang + '.json').then(function (res) { return res.json(); }).then(function (json) { iconterms = json; }).catch(function () {});
        }
    }
    function getIconNames() {
        if (names) return names;
        names = [];
        var sheets = document.styleSheets;
        for (var i = 0; i < sheets.length; i++) {
            var rules = null;
            try { rules = sheets[i].cssRules; } catch (e) { continue; }
            if (!rules) continue;
            for (var j = 0; j < rules.length; j++) {
                var sel = rules[j].selectorText || '';
                var m = sel.match(/^\.bi-([a-z0-9-]+)::before$/);
                if (m) names.push(m[1]);
            }
        }
        names.sort();
        return names;
    }
    function setIconGrid(modal) {
        var grid = modal.querySelector('[data-sl-icon-grid]');
        if (!grid || grid.childElementCount) return;
        var frag = document.createDocumentFragment();
        getIconNames().forEach(function (name) {
            var cell = document.createElement('button');
            cell.type = 'button';
            cell.className = 'sl-icon-cell';
            cell.title = name;
            cell.setAttribute('data-sl-icon-name', name);
            cell.innerHTML = '<i class="bi bi-' + name + '"></i><span>' + name + '</span>';
            frag.appendChild(cell);
        });
        grid.appendChild(frag);
    }
    function setIconCurrent(modal) {
        var value = picker ? picker.querySelector('[data-sl-icon-input]').value.trim() : '';
        modal.querySelectorAll('.sl-icon-cell').forEach(function (cell) {
            cell.classList.toggle('sl-is-active', cell.getAttribute('data-sl-icon-name') === value);
        });
        var active = modal.querySelector('.sl-icon-cell.sl-is-active');
        if (active) active.scrollIntoView({ block: 'center' });
    }
    function setIconValue(name) {
        if (!picker) return;
        var input = picker.querySelector('[data-sl-icon-input]');
        var preview = picker.querySelector('[data-sl-icon-preview]');
        if (input) input.value = name;
        if (preview) preview.className = 'bi bi-' + name;
    }
    document.addEventListener('click', function (event) {
        var node = event.target;
        if (!node || !node.closest) return;
        var opener = node.closest('[data-sl-icon-open]');
        if (opener) {
            var modal = document.getElementById('sl_icon_modal');
            if (!modal) return;
            picker = opener.closest('.sl-icon-picker');
            setIconGrid(modal);
            getIconWords();
            var search = modal.querySelector('[data-sl-icon-search]');
            if (search) search.value = '';
            modal.querySelectorAll('.sl-icon-cell').forEach(function (cell) { cell.hidden = false; });
            modal.showModal();
            setIconCurrent(modal);
            if (search) search.focus();
            return;
        }
        var cell = node.closest('.sl-icon-cell');
        if (cell) {
            setIconValue(cell.getAttribute('data-sl-icon-name'));
            cell.closest('dialog').close();
            return;
        }
        if (node.closest('[data-sl-icon-close]')) {
            node.closest('dialog').close();
            return;
        }
        var dialog = node.closest('dialog.sl-icon-modal');
        if (dialog && node === dialog) dialog.close();
    });
    document.addEventListener('input', function (event) {
        var node = event.target;
        if (!node || !node.closest) return;
        if (node.matches('[data-sl-icon-search]')) {
            var term = node.value.trim().toLowerCase();
            var list = [];
            if (term !== '') {
                Object.keys(iconterms || {}).forEach(function (word) {
                    if (word.indexOf(term) === 0 && word.length - term.length <= 3) list = list.concat(iconterms[word].split(' '));
                });
            }
            node.closest('dialog').querySelectorAll('.sl-icon-cell').forEach(function (cell) {
                var name = cell.getAttribute('data-sl-icon-name');
                var words = name + ' ' + ((icontags || {})[name] || '');
                var flat = ' ' + words.replace(/-/g, ' ') + ' ';
                var show = words.indexOf(term) !== -1 || list.some(function (w) { return w && flat.indexOf(' ' + w + ' ') !== -1; });
                cell.hidden = term !== '' && !show;
            });
            return;
        }
        if (node.matches('[data-sl-icon-input]')) {
            var wrap = node.closest('.sl-icon-picker');
            var preview = wrap ? wrap.querySelector('[data-sl-icon-preview]') : null;
            if (preview) preview.className = 'bi bi-' + node.value.trim();
        }
    });
    var drag = null;
    function getDragTable(node) {
        return node && node.closest ? node.closest('table[data-sl-drag-url]') : null;
    }
    function getDragRow(node) {
        return node && node.closest ? node.closest('tr[data-sl-drag-id]') : null;
    }
    function getDragRows(row) {
        var rows = [row];
        var ids = [row.getAttribute('data-sl-drag-id')];
        var next = row.nextElementSibling;
        while (next && next.hasAttribute && next.hasAttribute('data-sl-drag-id')) {
            if (next.hasAttribute('data-sl-drag-parent') && ids.indexOf(next.getAttribute('data-sl-drag-parent')) !== -1) {
                rows.push(next);
                ids.push(next.getAttribute('data-sl-drag-id'));
                next = next.nextElementSibling;
            } else break;
        }
        return rows;
    }
    function setDropMarksOff(table) {
        table.querySelectorAll('.sl-drop-above, .sl-drop-below, .sl-drop-deny').forEach(function (row) {
            row.classList.remove('sl-drop-above', 'sl-drop-below', 'sl-drop-deny');
        });
    }
    document.addEventListener('mousedown', function (event) {
        var handle = event.target && event.target.closest ? event.target.closest('.sl-drag-handle') : null;
        if (!handle) return;
        var row = getDragRow(handle);
        if (row) row.draggable = true;
    });
    document.addEventListener('dragstart', function (event) {
        var row = getDragRow(event.target);
        if (!row || !row.draggable || !getDragTable(row)) return;
        drag = row;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', row.getAttribute('data-sl-drag-id'));
        setTimeout(function () { getDragRows(row).forEach(function (item) { item.classList.add('sl-dragging'); }); });
    });
    document.addEventListener('dragover', function (event) {
        if (!drag) return;
        var row = getDragRow(event.target);
        var table = getDragTable(row);
        if (!row || row === drag || !table) return;
        setDropMarksOff(table);
        if (row.getAttribute('data-sl-drag-group') !== drag.getAttribute('data-sl-drag-group')) {
            event.dataTransfer.dropEffect = 'none';
            row.classList.add('sl-drop-deny');
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        var before = event.clientY < row.getBoundingClientRect().top + row.offsetHeight / 2;
        row.classList.add(before ? 'sl-drop-above' : 'sl-drop-below');
    });
    document.addEventListener('drop', function (event) {
        if (!drag) return;
        var row = getDragRow(event.target);
        var table = getDragTable(row);
        if (!row || row === drag || !table) return;
        if (row.getAttribute('data-sl-drag-group') !== drag.getAttribute('data-sl-drag-group')) return;
        event.preventDefault();
        var moving = getDragRows(drag);
        if (moving.indexOf(row) !== -1) return;
        var before = event.clientY < row.getBoundingClientRect().top + row.offsetHeight / 2;
        var family = getDragRows(row);
        var anchor = before ? row : family[family.length - 1].nextSibling;
        var parent = row.parentNode;
        moving.forEach(function (item) { parent.insertBefore(item, anchor); });
        var ids = [];
        table.querySelectorAll('tr[data-sl-drag-scope="' + drag.getAttribute('data-sl-drag-scope') + '"]').forEach(function (item) {
            ids.push(item.getAttribute('data-sl-drag-id'));
        });
        var saved = drag.getAttribute('data-sl-drag-id');
        var target = table.getAttribute('data-sl-drag-target');
        fetch(table.getAttribute('data-sl-drag-url'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'ids=' + ids.join('-') + '&token=' + encodeURIComponent(table.getAttribute('data-sl-drag-token') || '')
        }).then(function (reply) { return reply.text(); }).then(function (html) {
            var box = document.getElementById(target);
            if (!box) return;
            box.innerHTML = html;
            setGroupStates(box);
            var flash = box.querySelector('tr[data-sl-drag-id="' + saved + '"]');
            if (!flash) return;
            flash.classList.add('sl-row-saved');
            setTimeout(function () { flash.classList.remove('sl-row-saved'); }, 900);
        });
    });
    document.addEventListener('dragend', function () {
        if (!drag) return;
        getDragRows(drag).forEach(function (item) { item.classList.remove('sl-dragging'); });
        drag.draggable = false;
        var table = getDragTable(drag);
        if (table) setDropMarksOff(table);
        drag = null;
    });
    function setGroupRows(head, closed) {
        head.classList.toggle('sl-is-closed', closed);
        var next = head.nextElementSibling;
        while (next && next.hasAttribute && !next.hasAttribute('data-sl-group')) {
            next.classList.toggle('sl-row-collapsed', closed);
            next = next.nextElementSibling;
        }
    }
    function setGroupStates(root) {
        (root || document).querySelectorAll('tr[data-sl-group]').forEach(function (head) {
            var closed = false;
            try { closed = window.localStorage.getItem('sl-group-' + head.getAttribute('data-sl-group')) === '0'; } catch (err) {}
            if (closed) setGroupRows(head, true);
        });
    }
    document.addEventListener('click', function (event) {
        var head = event.target && event.target.closest ? event.target.closest('tr[data-sl-group]') : null;
        if (!head) return;
        var closed = !head.classList.contains('sl-is-closed');
        setGroupRows(head, closed);
        try { window.localStorage.setItem('sl-group-' + head.getAttribute('data-sl-group'), closed ? '0' : '1'); } catch (err) {}
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { setGroupStates(document); });
    } else {
        setGroupStates(document);
    }
    /* File browser: the view, the current object and the way back are local state of the screen, and where back, up and refresh lead is read off the body the server just sent */
    var fmback = [];
    /* The object a range of marks starts from, and the wait before the properties of a walked-to object are asked for */
    var fmfrom = -1;
    var fmside = null;
    var fmshown = false;
    function setFileView(mode) {
        document.querySelectorAll('[data-sl-fm-view]').forEach(function (node) {
            var own = node.getAttribute('data-sl-fm-view');
            if (node.tagName === 'BUTTON') node.setAttribute('aria-pressed', own === mode ? 'true' : 'false');
            else node.hidden = own !== mode;
        });
    }
    function setFilePick(node) {
        document.querySelectorAll('[data-sl-fm-pick]').forEach(function (item) {
            var row = item.closest('tr') || item.closest('.sl-fm-cell');
            if (row) row.removeAttribute('aria-selected');
        });
        var own = node.closest('tr') || node.closest('.sl-fm-cell');
        if (own) own.setAttribute('aria-selected', 'true');
    }
    /* The keys and the modifiers walk the objects of the view that is on, and one object is one entry however the two views of the list draw it */
    function getFileWalk() {
        var view = document.querySelector('#slfmbody div[data-sl-fm-view]:not([hidden])');
        return view ? Array.prototype.slice.call(view.querySelectorAll('[data-sl-fm-pick]')) : [];
    }
    function getFileHost(node) {
        return (node && node.closest) ? node.closest('tr, .sl-fm-cell') : null;
    }
    /* A mark belongs to the object and not to the box drawing it, so it is set through the one handler that keeps the row, the tile and the panel of marks in step */
    function setFileMark(item, on) {
        var host = getFileHost(item);
        var box = host ? host.querySelector('[data-sl-fm-mark]') : null;
        if (!box) return;
        box.checked = (on === null) ? !box.checked : on;
        setFileMarks(box);
    }
    function setFileSpan(from, to) {
        var walk = getFileWalk();
        var at = Math.min(from, to);
        var end = Math.max(from, to);
        for (; at <= end; at++) setFileMark(walk[at], true);
    }
    /* Walking the list changes the current object, and the properties beside it belong to that object; the request waits a moment, because a held key would send one per step */
    function setFileSide(item) {
        var url = item.getAttribute('hx-get') || '';
        if (fmside) window.clearTimeout(fmside);
        if (!window.htmx || url === '' || item.getAttribute('hx-target') !== '#slfmside') return;
        fmside = window.setTimeout(function () {
            window.htmx.ajax('GET', url, { target: '#slfmside', swap: 'innerHTML' });
        }, 250);
    }
    /* How far one key carries depends on the view: a list walks by rows, tiles walk by one across and by a whole row of them down, counted off the drawing itself */
    function getFileCols(walk) {
        var top = walk.length ? walk[0].getBoundingClientRect().top : 0;
        var num = 0;
        walk.forEach(function (item) {
            if (Math.abs(item.getBoundingClientRect().top - top) < 4) num++;
        });
        return Math.max(1, num);
    }
    function getFileTurn(walk, at, key) {
        var view = document.querySelector('#slfmbody div[data-sl-fm-view]:not([hidden])');
        var grid = !!view && view.classList.contains('sl-fm-grid');
        if (key === 'Home') return 0;
        if (key === 'End') return walk.length - 1;
        if (key === 'ArrowLeft') return grid ? at - 1 : at;
        if (key === 'ArrowRight') return grid ? at + 1 : at;
        if (key === 'ArrowUp') return at - (grid ? getFileCols(walk) : 1);
        return at + (grid ? getFileCols(walk) : 1);
    }
    /* One form serves every operation needing a word from the administrator: which operation, what it runs on and what it starts from are read off the item that opened it */
    /* The item names the operation exactly as the route does, so the value travels into the form untouched and no second table of names has to agree with the module */
    function setFileOps(node) {
        var form = document.querySelector('.sl-fm-ops');
        var arg = form ? form.querySelector('[name="arg"]') : null;
        var act = node.getAttribute('data-sl-fm-act');
        if (act === 'close') {
            var own = node.closest('form');
            if (own) own.hidden = true;
            return;
        }
        if (!form || !arg) return;
        form.querySelector('[data-sl-fm-marks]').textContent = '';
        arg.required = true;
        form.querySelector('[name="op"]').value = act;
        form.querySelector('[name="file"]').value = node.getAttribute('data-sl-fm-file') || '';
        form.querySelector('[data-sl-fm-title]').textContent = node.getAttribute('title') || '';
        arg.setAttribute('aria-label', node.getAttribute('title') || '');
        arg.value = node.getAttribute('data-sl-fm-arg') || '';
        form.hidden = false;
        arg.focus();
        arg.select();
    }
    document.addEventListener('keydown', function (event) {
        var form = (event.key === 'Escape' && event.target && event.target.closest) ? event.target.closest('.sl-fm-ops') : null;
        if (form) form.hidden = true;
    });
    function getFileUrl(step) {
        var split = document.querySelector('[data-sl-fm-url]');
        if (!split) return '';
        if (step === 'up') return split.getAttribute('data-sl-fm-up');
        if (step === 'self') return split.getAttribute('data-sl-fm-url');
        if (fmback.length < 2) return '';
        fmback.pop();
        return fmback.pop();
    }
    function addFileStep() {
        var split = document.querySelector('[data-sl-fm-url]');
        var back = document.querySelector('[data-sl-fm-go="back"]');
        var mode = document.querySelector('button[data-sl-fm-view][aria-pressed="true"]');
        var find = document.querySelector('.sl-fm-bar input[name="find"]');
        var load = document.querySelector('[data-sl-fm-act="upload"]');
        if (load) load.disabled = !document.querySelector('.sl-fm-load');
        /* The answer brings another directory: the object a range started from is gone with the previous one, and a request asked for by a key of the previous one is stale */
        if (fmside) window.clearTimeout(fmside);
        fmfrom = -1;
        fmshown = false;
        setFileMarks(null);
        if (split) {
            var url = split.getAttribute('data-sl-fm-url');
            if (fmback[fmback.length - 1] !== url) fmback.push(url);
            if (fmback.length > 30) fmback.shift();
            /* The filter belongs to the directory the answer is about, and the field is left alone while it is the one being typed in */
            if (find && document.activeElement !== find && find.value !== split.getAttribute('data-sl-fm-find')) find.value = split.getAttribute('data-sl-fm-find');
        }
        if (back) back.disabled = fmback.length < 2;
        if (mode) setFileView(mode.getAttribute('data-sl-fm-view'));
    }
    /* Opening a panel of the browser is a local matter of the screen, and closing it happens by its own button or by the next answer of the server replacing the body */
    function setFileShow(name) {
        var box = document.querySelector(name);
        if (box) box.hidden = !box.hidden;
    }
    /* Dropping every mark of the screen is one action of its own, because the panel it belongs to disappears with the last of them and would take its own button with it */
    function setFileNone() {
        document.querySelectorAll('[data-sl-fm-mark]').forEach(function (box) { box.checked = false; });
        setFileMarks(null);
    }
    /* One object carries a mark and not one of its two drawings: the row and the tile of a file hold the same value, so marking either of them marks the object once */
    function setFileMarks(node) {
        var list = document.querySelectorAll('[data-sl-fm-mark]');
        var bar = document.querySelector('.sl-bulk-bar');
        var all = document.querySelector('[data-sl-fm-all]');
        var count = bar ? bar.querySelector('[data-sl-fm-count]') : null;
        var seen = [];
        var uniq = [];
        if (node && node.hasAttribute('data-sl-fm-mark')) {
            list.forEach(function (box) { if (box.value === node.value) box.checked = node.checked; });
        }
        if (node && node.hasAttribute('data-sl-fm-all')) {
            list.forEach(function (box) { box.checked = node.checked; });
        }
        list.forEach(function (box) {
            if (uniq.indexOf(box.value) < 0) uniq.push(box.value);
            if (box.checked && seen.indexOf(box.value) < 0) seen.push(box.value);
        });
        if (all) {
            all.checked = uniq.length > 0 && seen.length === uniq.length;
            all.indeterminate = seen.length > 0 && seen.length < uniq.length;
        }
        if (bar) bar.hidden = seen.length < 1;
        if (count) count.textContent = count.getAttribute('data-sl-fm-count') + ': ' + seen.length;
        return seen;
    }
    /* A marked set travels in the form one object travels in: the operation is named the same way and only the number of paths in the body differs */
    /* The panel of marks and the pointer dragging a set onto a directory fill the same form, so what a set is written into the form with is said once and in one place */
    function setFileSet(act, name, paths, val) {
        var form = document.querySelector('.sl-fm-ops');
        var box = form ? form.querySelector('[data-sl-fm-marks]') : null;
        var arg = form ? form.querySelector('[name="arg"]') : null;
        if (!form || !box || !arg || paths.length < 1) return null;
        box.textContent = '';
        paths.forEach(function (one) {
            var line = document.createElement('input');
            line.type = 'hidden';
            line.name = 'mark[]';
            line.value = one;
            box.appendChild(line);
        });
        form.querySelector('[name="op"]').value = act;
        form.querySelector('[name="file"]').value = '';
        form.querySelector('[data-sl-fm-title]').textContent = name;
        /* The root of the context is a target with no name of its own, and a field demanding a word would refuse the one directory that has none */
        arg.required = val !== '';
        arg.setAttribute('aria-label', name);
        arg.value = val;
        return form;
    }
    function setFileWord(form) {
        var arg = form.querySelector('[name="arg"]');
        form.hidden = false;
        arg.focus();
        arg.select();
    }
    /* An operation asking for a word opens the field with the value it starts from, and one asking for nothing goes straight out, through the system dialog where it must */
    function setFileMany(node) {
        var need = node.hasAttribute('data-sl-fm-arg');
        var ask = node.getAttribute('data-sl-fm-ask');
        var form = setFileSet(node.getAttribute('data-sl-fm-act'), (node.textContent || '').trim(), setFileMarks(null), need ? node.getAttribute('data-sl-fm-arg') : '');
        if (!form) return;
        form.hidden = !need;
        if (need) return setFileWord(form);
        var run = function () { form.requestSubmit ? form.requestSubmit() : form.submit(); };
        if (ask && window.setConfirmTask) return window.setConfirmTask(ask, run);
        run();
    }
    /* Files go up one after another: each carries its own bar and its own reason, a refused one does not stop the rest, and the reason is the one the server answered */
    /* The body of every job is taken from the form before the first byte leaves, because the answer of the last job replaces the list and the form standing in it */
    var fmsend = null;
    var fmstop = false;
    function setFileLoad(form) {
        var pick = form.querySelector('input[type="file"]');
        var link = form.querySelector('[name="sitefile"]');
        var box = document.querySelector('.sl-fm-queue');
        var base = [];
        var jobs = [];
        var i;
        if (!box || !window.FormData || !window.XMLHttpRequest) return false;
        if (pick && pick.files) for (i = 0; i < pick.files.length; i++) jobs.push(pick.files[i]);
        if (link && link.value.trim() !== '') jobs.push(link.value.trim());
        if (jobs.length < 1) return false;
        form.querySelectorAll('input[type="hidden"]').forEach(function (one) { base.push([one.name, one.value]); });
        form.hidden = true;
        fmstop = false;
        box.hidden = false;
        box.querySelector('[data-sl-fm-jobs]').textContent = '';
        addFileJob(form.getAttribute('action'), base, jobs, 0);
        return true;
    }
    function addFileJob(url, base, jobs, at) {
        var box = document.querySelector('.sl-fm-queue');
        var split = document.querySelector('[data-sl-fm-url]');
        var data = new FormData();
        var line = document.createElement('div');
        var xhr = new XMLHttpRequest();
        var bar;
        if (fmstop || at >= jobs.length) {
            fmsend = null;
            box.querySelector('[data-sl-fm-turn]').textContent = '';
            if (split && window.htmx) window.htmx.ajax('GET', split.getAttribute('data-sl-fm-url'), { target: '#slfmbody', swap: 'innerHTML' });
            return;
        }
        box.querySelector('[data-sl-fm-turn]').textContent = (at + 1) + ' / ' + jobs.length;
        line.className = 'sl-fm-job';
        line.innerHTML = '<b></b><div class="sl-progress-line sl-progress-2"><div style="width: 0%;">0%</div></div><small></small>';
        line.querySelector('b').textContent = (typeof jobs[at] === 'string') ? jobs[at] : jobs[at].name;
        box.querySelector('[data-sl-fm-jobs]').appendChild(line);
        bar = line.querySelector('.sl-progress-line div');
        base.forEach(function (one) { data.append(one[0], one[1]); });
        data.append('ajax', '1');
        if (typeof jobs[at] === 'string') data.append('sitefile', jobs[at]);
        else data.append('userfile[]', jobs[at], jobs[at].name);
        xhr.open('POST', url);
        xhr.upload.onprogress = function (event) {
            var pct = event.lengthComputable ? Math.round(event.loaded * 100 / event.total) : 0;
            bar.style.width = pct + '%';
            bar.textContent = pct + '%';
        };
        xhr.onload = function () {
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch (err) { res = null; }
            setFileDone(line, bar, (res && res.ok) ? '' : ((res && res.error) ? res.error : String(xhr.status)));
            addFileJob(url, base, jobs, at + 1);
        };
        xhr.onerror = function () {
            setFileDone(line, bar, String(xhr.status));
            addFileJob(url, base, jobs, at + 1);
        };
        fmsend = xhr;
        xhr.send(data);
    }
    function setFileDone(line, bar, why) {
        bar.style.width = '100%';
        bar.textContent = '100%';
        line.querySelector('small').textContent = why;
        if (why !== '') line.setAttribute('data-sl-fm-fail', '1');
    }
    function setFileStop() {
        var box = document.querySelector('.sl-fm-queue');
        fmstop = true;
        if (fmsend) fmsend.abort();
        fmsend = null;
        if (box) box.hidden = true;
    }
    /* The target really takes a drop: a label around a file field opens the picker on a click, but a dropped file reaches nobody unless the page takes it off the event itself */
    /* The whole browser is that target and not the label alone: a file dragged onto the list belongs to the directory the list shows, and the panel opens with the drag */
    /* so its limits and its second field are read before the pointer is let go; a panel opened by the drag closes with it, one opened by the administrator stays */
    function checkFileDrag(event) {
        var kind = event.dataTransfer ? event.dataTransfer.types : null;
        return !drag && fmdrag.length < 1 && !!kind && Array.prototype.indexOf.call(kind, 'Files') >= 0;
    }
    function setFileZone(on) {
        var form = document.querySelector('.sl-fm-load');
        var zone = form ? form.querySelector('.sl-fm-drop') : null;
        if (!form || !zone) return;
        zone.classList.toggle('sl-drag-over', on);
        if (on && form.hidden) {
            form.hidden = false;
            fmshown = true;
        }
        if (!on && fmshown) {
            form.hidden = true;
            fmshown = false;
        }
    }
    document.addEventListener('dragover', function (event) {
        var body = event.target && event.target.closest ? event.target.closest('#slfmbody') : null;
        if (!body || !document.querySelector('.sl-fm-load') || !checkFileDrag(event)) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
        setFileZone(true);
    });
    document.addEventListener('dragleave', function (event) {
        var body = event.target && event.target.closest ? event.target.closest('#slfmbody') : null;
        if (!body || (event.relatedTarget && body.contains(event.relatedTarget))) return;
        setFileZone(false);
    });
    document.addEventListener('drop', function (event) {
        var body = event.target && event.target.closest ? event.target.closest('#slfmbody') : null;
        var form = body ? document.querySelector('.sl-fm-load') : null;
        var pick = form ? form.querySelector('input[type="file"]') : null;
        if (!pick || !event.dataTransfer || event.dataTransfer.files.length < 1) return;
        event.preventDefault();
        setFileZone(false);
        fmshown = false;
        pick.files = event.dataTransfer.files;
        form.requestSubmit ? form.requestSubmit() : form.submit();
    });
    /* A modifier turns a press on an object into a mark: alone it marks and unmarks one, with the shift it marks everything between the object it started from and this one */
    /* The press is taken in the capture phase, because the address of the object carries a request of its own and it must not leave while the press means marking */
    document.addEventListener('click', function (event) {
        var node = (event.shiftKey || event.ctrlKey || event.metaKey) && event.target.closest ? event.target.closest('[data-sl-fm-pick],[data-sl-fm-mark]') : null;
        var host = getFileHost(node);
        var walk;
        var at;
        if (!node || !host) return;
        walk = getFileWalk();
        at = walk.indexOf(host.querySelector('[data-sl-fm-pick]'));
        if (at < 0) return;
        event.preventDefault();
        event.stopPropagation();
        /* The mark is not the current object: the current one is the object whose properties stand on the right, and a marking press asks the server for nothing (§9.1) */
        if (event.shiftKey && fmfrom >= 0) return setFileSpan(fmfrom, at);
        setFileMark(walk[at], null);
        fmfrom = at;
    }, true);
    /* A desk drags: an object taken by the pointer and let go on a directory moves there, and the whole marked set travels with it when the object dragged is one of them */
    /* The move is never run behind the administrator: the form opens with the target already in it, because a moved file leaves its published address behind (§8) */
    var fmdrag = [];
    function setFileOver(box) {
        document.querySelectorAll('.sl-drag-over[data-sl-fm-dir]').forEach(function (one) {
            if (one !== box) one.classList.remove('sl-drag-over');
        });
        if (box) box.classList.add('sl-drag-over');
    }
    /* A directory takes the set unless it is in the set itself: a directory dropped into its own name is the one move that has nowhere to go */
    function getFileDir(node) {
        var box = (node && node.closest) ? node.closest('[data-sl-fm-dir]') : null;
        if (!box || fmdrag.length < 1) return null;
        return (fmdrag.indexOf(box.getAttribute('data-sl-fm-dir')) < 0) ? box : null;
    }
    document.addEventListener('dragstart', function (event) {
        var host = getFileHost(event.target);
        var own = host ? host.getAttribute('data-sl-fm-file') : '';
        var seen;
        if (!host || !own || !host.hasAttribute('draggable')) return;
        seen = setFileMarks(null);
        fmdrag = (seen.indexOf(own) >= 0) ? seen : [own];
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', fmdrag.join('\n'));
    });
    document.addEventListener('dragover', function (event) {
        var box = getFileDir(event.target);
        if (!box) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        setFileOver(box);
    });
    document.addEventListener('drop', function (event) {
        var box = getFileDir(event.target);
        var many = document.querySelector('[data-sl-fm-act="fmmove"][data-sl-fm-many]');
        var form;
        if (!box || !many) return;
        event.preventDefault();
        setFileOver(null);
        form = setFileSet('fmmove', (many.textContent || '').trim(), fmdrag, box.getAttribute('data-sl-fm-dir'));
        fmdrag = [];
        if (form) setFileWord(form);
    });
    document.addEventListener('dragend', function () {
        setFileOver(null);
        fmdrag = [];
    });
    /* The context menu is the fan of the object and the component opens it for every fan of the project; what belongs to the browser is that the object becomes current */
    document.addEventListener('contextmenu', function (event) {
        var host = (event.target && event.target.closest && event.target.closest('#slfmbody')) ? getFileHost(event.target) : null;
        var node = host ? host.querySelector('[data-sl-fm-pick]') : null;
        if (!node) return;
        setFilePick(node);
        setFileSide(node);
    });
    /* The keys walk the list the pointer walks: an arrow moves the current object, the shift with it drags the marks along, a space marks one and Enter does what a press does */
    /* Only an object of the list answers to these keys: a fan item and the field of an operation stand inside the body too and keep the keys they came with */
    document.addEventListener('keydown', function (event) {
        var box = document.querySelector('.sl-fm-modal');
        var node = document.activeElement;
        var keys = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Home', 'End'];
        var host = getFileHost(node);
        var walk;
        var at;
        var to;
        if (box && box.open) return;
        if (!node || !node.matches || !host || !node.matches('[data-sl-fm-pick],[data-sl-fm-mark]')) return;
        walk = getFileWalk();
        at = walk.indexOf(host.querySelector('[data-sl-fm-pick]'));
        if (at < 0) return;
        if (event.key === 'Enter' && node.hasAttribute('data-sl-fm-pick')) {
            event.preventDefault();
            return walk[at].click();
        }
        /* The mark of a box is set by the browser itself on this key, and setting it a second time here would take it straight back off */
        if (event.key === ' ' && node.hasAttribute('data-sl-fm-pick')) {
            event.preventDefault();
            fmfrom = at;
            return setFileMark(walk[at], null);
        }
        if (keys.indexOf(event.key) < 0) return;
        to = Math.min(Math.max(getFileTurn(walk, at, event.key), 0), walk.length - 1);
        event.preventDefault();
        if (event.shiftKey && fmfrom < 0) fmfrom = at;
        setFilePick(walk[to]);
        walk[to].focus();
        setFileSide(walk[to]);
        if (event.shiftKey) setFileSpan(fmfrom, to);
    });
    /* The gallery walks the objects that can be shown and nothing else, and one object is one entry however many times the two views of the list draw it */
    function getFileShots() {
        var out = [];
        var seen = {};
        document.querySelectorAll('[data-sl-fm-show]').forEach(function (node) {
            var own = node.getAttribute('data-sl-fm-file');
            if (seen[own]) return;
            seen[own] = 1;
            out.push(node);
        });
        return out;
    }
    /* The properties beside the picture are the panel of the same object, asked of the same route the list asks, so the gallery repeats no descriptor of its own */
    var fmshot = 0;
    function setFileShot(step, from) {
        var box = document.querySelector('.sl-fm-modal');
        var list = getFileShots();
        var own = from ? from.getAttribute('data-sl-fm-file') : '';
        var at = from ? -1 : ((fmshot + step) % list.length + list.length) % list.length;
        /* The entry is found by the object and not by the element: the row and the tile of one file are two elements, and only one of them is in the walk */
        if (from) list.forEach(function (node, i) { if (node.getAttribute('data-sl-fm-file') === own) at = i; });
        if (!box || list.length < 1 || at < 0) return;
        var node = list[at];
        var down = box.querySelector('[data-sl-fm-down]');
        var img = box.querySelector('[data-sl-fm-img]');
        fmshot = at;
        box.querySelector('[data-sl-fm-name]').textContent = node.getAttribute('data-sl-fm-name');
        box.querySelector('[data-sl-fm-num]').textContent = (at + 1) + ' / ' + list.length;
        img.src = node.getAttribute('data-sl-fm-img');
        img.alt = node.getAttribute('data-sl-fm-name');
        down.href = node.getAttribute('data-sl-fm-down') || '#';
        down.hidden = !node.getAttribute('data-sl-fm-down');
        if (window.htmx) window.htmx.ajax('GET', node.getAttribute('data-sl-fm-info'), { target: '#slfmshot', swap: 'innerHTML' });
        if (!box.open) box.showModal();
    }
    /* An action of the gallery is the action of the object it shows: the fan of the row already carries it with its form and its question, so the picture only presses it */
    function setFileRun(name) {
        var box = document.querySelector('.sl-fm-modal');
        var list = getFileShots();
        var own = list[fmshot] ? list[fmshot].closest('tr, .sl-fm-cell') : null;
        var item = own ? own.querySelector('[data-sl-fm-run="' + name + '"], [data-sl-fm-act="' + name + '"]') : null;
        if (box && box.open) box.close();
        if (item) item.click();
    }
    document.addEventListener('change', function (event) {
        var node = event.target && event.target.closest ? event.target.closest('[data-sl-fm-mark],[data-sl-fm-all]') : null;
        if (node) setFileMarks(node);
    });
    document.addEventListener('keydown', function (event) {
        var box = document.querySelector('.sl-fm-modal');
        if (!box || !box.open) return;
        if (event.key === 'ArrowLeft') setFileShot(-1, null);
        if (event.key === 'ArrowRight') setFileShot(1, null);
    });
    var fmhit = '[data-sl-fm-view],[data-sl-fm-pick],[data-sl-fm-go],[data-sl-fm-act],[data-sl-fm-show],[data-sl-fm-step],[data-sl-fm-run]';
    document.addEventListener('click', function (event) {
        var node = event.target && event.target.closest ? event.target.closest(fmhit) : null;
        if (!node) return;
        if (node.hasAttribute('data-sl-fm-show')) {
            event.preventDefault();
            return setFileShot(0, node);
        }
        if (node.hasAttribute('data-sl-fm-step')) return setFileShot(parseInt(node.getAttribute('data-sl-fm-step'), 10), null);
        /* The fan of a row opens the gallery on the object the row is about; every other action of the fan is a link or a form of its own and is left alone */
        if (node.getAttribute('data-sl-fm-run') === 'preview') {
            var own = node.closest('tr, .sl-fm-cell');
            var shot = own ? own.querySelector('[data-sl-fm-show]') : null;
            event.preventDefault();
            if (shot) setFileShot(0, shot);
            return;
        }
        if (node.hasAttribute('data-sl-fm-run') && node.closest('.sl-fm-modal')) return setFileRun(node.getAttribute('data-sl-fm-run'));
        if (node.hasAttribute('data-sl-fm-act')) {
            event.preventDefault();
            if (node.hasAttribute('data-sl-fm-many')) return setFileMany(node);
            if (node.getAttribute('data-sl-fm-act') === 'upload') return setFileShow('.sl-fm-load');
            if (node.getAttribute('data-sl-fm-act') === 'stop') return setFileStop();
            if (node.getAttribute('data-sl-fm-act') === 'unmark') return setFileNone();
            return setFileOps(node);
        }
        if (node.hasAttribute('data-sl-fm-pick')) return setFilePick(node);
        if (node.hasAttribute('data-sl-fm-view')) return setFileView(node.getAttribute('data-sl-fm-view'));
        /* Only a step of the navigation reaches the walk below: a node matched by any other mark of the browser keeps the action it came with */
        if (!node.hasAttribute('data-sl-fm-go')) return;
        var url = window.htmx ? getFileUrl(node.getAttribute('data-sl-fm-go')) : '';
        if (!url) return;
        var go = function () { window.htmx.ajax('GET', url, { target: '#slfmbody', swap: 'innerHTML' }); };
        /* These three ask here and not in the request guard below, because a call of the htmx api names the body as its source and not the button that made it */
        if (checkFileEdit()) return setFileLeave(go);
        go();
    });
    /* An open source editor holds work the server has not seen: the widget copies the document into its field on submit alone, so a document still differing from it is unsaved */
    var fmfree = false;
    function checkFileEdit() {
        var box = document.querySelector('[data-sl-fm-ask]');
        var area = box ? document.getElementById(box.getAttribute('data-sl-fm-code')) : null;
        var view = (area && window.CM6 && window.CM6.editors) ? window.CM6.editors[area.id] : null;
        return !fmfree && !!view && view.state.doc.toString() !== area.value;
    }
    /* Once the administrator answered yes, the way out is released: without that the browser asks a second time with its own dialog on the navigation the answer just allowed */
    function setFileLeave(run) {
        window.setConfirmTask(document.querySelector('[data-sl-fm-ask]').getAttribute('data-sl-fm-ask'), function () {
            fmfree = true;
            run();
        });
    }
    /* The three states a list can be in besides being there: it is on its way, it did not arrive, or it arrived and takes the place of both (§31) */
    /* The answer replaces the whole body, so the marks below belong to the body that is leaving and never have to be taken back on a swap that succeeded */
    function setFileWait(mode) {
        /* The three states belong to the list and are looked for inside it: a refused file of the queue carries the same mark, and the queue stands above the body */
        var skel = document.querySelector('#slfmbody .sl-skel');
        var fail = document.querySelector('#slfmbody [data-sl-fm-fail]');
        var real = document.querySelector('#slfmbody [data-sl-fm-real]');
        if (!skel || !fail || !real) return;
        skel.hidden = mode !== 'wait';
        fail.hidden = mode !== 'fail';
        real.hidden = mode !== 'done';
    }
    function checkFileTarget(event) {
        var to = event.detail ? event.detail.target : null;
        return !!to && (to.id === 'slfmbody' || (to.closest && to.closest('#slfmbody')));
    }
    document.addEventListener('htmx:beforeRequest', function (event) {
        if (checkFileTarget(event) && event.detail.target.id === 'slfmbody') setFileWait('wait');
    });
    document.addEventListener('htmx:responseError', function (event) {
        if (checkFileTarget(event)) setFileWait('fail');
    });
    document.addEventListener('htmx:sendError', function (event) {
        if (checkFileTarget(event)) setFileWait('fail');
    });
    document.addEventListener('htmx:afterSwap', function (event) {
        if (event.target && event.target.id === 'slfmbody') {
            fmfree = false;
            addFileStep();
        }
    });
    /* A swap of the browser body replaces the editor as silently as a navigation drops the page, so both ways out ask first; a save is not a way out and never asks */
    /* Only a request of the browser itself is guarded: every admin page carries panels of its own, and their refresh does not touch the editor and must not be asked about */
    document.addEventListener('htmx:confirm', function (event) {
        var from = (event.detail && event.detail.elt && event.detail.elt.closest) ? event.detail.elt : null;
        if (!from || !from.closest('#slfmbody, .sl-fm-bar') || !checkFileEdit()) return;
        event.preventDefault();
        setFileLeave(function () { event.detail.issueRequest(true); });
    });
    document.addEventListener('click', function (event) {
        var link = event.target && event.target.closest ? event.target.closest('.sl-fm-edit a[href]') : null;
        if (!link || !checkFileEdit()) return;
        event.preventDefault();
        setFileLeave(function () { window.location.href = link.href; });
    });
    /* The save of the editor is not a way out and never asks; the form of an operation leaves the page like any other navigation and therefore asks with the same dialog */
    document.addEventListener('submit', function (event) {
        var form = (event.target && event.target.closest) ? event.target : null;
        if (!form) return;
        if (form.closest('.sl-fm-edit')) {
            fmfree = true;
            return;
        }
        if (form.classList.contains('sl-fm-load')) {
            if (setFileLoad(form)) event.preventDefault();
            return;
        }
        if (!form.classList.contains('sl-fm-ops') || !checkFileEdit()) return;
        event.preventDefault();
        setFileLeave(function () { form.requestSubmit ? form.requestSubmit() : form.submit(); });
    });
    /* The last net catches what no handler of the page sees: a reload, the address bar and the back button of the browser; the older engines need the value beside the cancel */
    window.addEventListener('beforeunload', function (event) {
        if (!checkFileEdit()) return;
        event.preventDefault();
        event.returnValue = '';
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addFileStep);
    } else {
        addFileStep();
    }
})();
