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
    /* Write the picked icon into its field and announce it, so a form that follows its fields hears a pick as it hears a typed name */
    function setIconValue(name) {
        if (!picker) return;
        var input = picker.querySelector('[data-sl-icon-input]');
        var preview = picker.querySelector('[data-sl-icon-preview]');
        if (input) input.value = name;
        if (preview) preview.className = 'bi bi-' + name;
        if (input) input.dispatchEvent(new Event('input', { bubbles: true }));
    }
    document.addEventListener('click', function (event) {
        var node = event.target;
        if (!node || !node.closest) return;
        var opener = node.closest('[data-sl-icon-open]');
        if (opener) {
            var modal = document.getElementById('sl-icon-window');
            if (!modal) return;
            picker = opener.closest('.sl-icon-picker');
            setIconGrid(modal);
            getIconWords();
            var search = modal.querySelector('[data-sl-icon-search]');
            if (search) search.value = '';
            modal.querySelectorAll('.sl-icon-cell').forEach(function (cell) { cell.hidden = false; });
            window.setWindowOpen(modal);
            setIconCurrent(modal);
            return;
        }
        var cell = node.closest('.sl-icon-cell[data-sl-icon-name]');
        if (cell) {
            setIconValue(cell.getAttribute('data-sl-icon-name'));
            window.setWindowClose(cell.closest('dialog'));
        }
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
            if (window.getToggleState('group-' + head.getAttribute('data-sl-group'), '') === '0') setGroupRows(head, true);
        });
    }
    document.addEventListener('click', function (event) {
        var head = event.target && event.target.closest ? event.target.closest('tr[data-sl-group]') : null;
        if (!head) return;
        var closed = !head.classList.contains('sl-is-closed');
        setGroupRows(head, closed);
        window.setToggleState('group-' + head.getAttribute('data-sl-group'), '', closed ? '0' : '1');
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
    /* The item names the operation exactly as the route does, so the value travels into the form untouched and no second table of names has to agree */
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
    /* The source editor route is a page of its own, so the window is raised as soon as that page is drawn over the directory of the file */
    function setFileEditWin() {
        var box = document.getElementById('slfmedit');
        if (box && !box.open && window.setWindowOpen) window.setWindowOpen(box);
    }

    function addFileStep() {
        var split = document.querySelector('[data-sl-fm-url]');
        setFileEditWin();
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
    /* A marked set travels in the form one object travels in: only the number of paths in the body differs */
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
    /* The body of every job is taken before the first byte leaves, because the answer of the last job replaces the list and the form standing in it */
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
    /* A dropped file reaches nobody unless the page takes it off the event, so the whole browser is the target and the panel opens with the drag */
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
    /* The press is taken in the capture phase, because the address of the object carries a request of its own and must not leave while the press marks */
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
        /* The mark is not the current object: the current one is the object whose properties stand on the right, and a marking press asks the server for nothing */
        if (event.shiftKey && fmfrom >= 0) return setFileSpan(fmfrom, at);
        setFileMark(walk[at], null);
        fmfrom = at;
    }, true);
    /* A drag never moves behind the administrator: the form opens with the target in it, because a moved file leaves its published address behind */
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
    /* Only an object of the list answers these keys: a fan item and the field of an operation stand inside the body too and keep their own keys */
    document.addEventListener('keydown', function (event) {
        var box = document.querySelector('dialog[data-sl-shot="files"]');
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
        var box = document.querySelector('dialog[data-sl-shot="files"]');
        var list = getFileShots();
        var own = from ? from.getAttribute('data-sl-fm-file') : '';
        var at = from ? -1 : ((fmshot + step) % list.length + list.length) % list.length;
        /* The entry is found by the object and not by the element: the row and the tile of one file are two elements, and only one of them is in the walk */
        if (from) list.forEach(function (node, i) { if (node.getAttribute('data-sl-fm-file') === own) at = i; });
        if (!box || list.length < 1 || at < 0) return;
        var node = list[at];
        var down = box.querySelector('[data-sl-shot-down]');
        var img = box.querySelector('[data-sl-shot-img]');
        fmshot = at;
        box.querySelector('[data-sl-shot-name]').textContent = node.getAttribute('data-sl-fm-name');
        box.querySelector('[data-sl-shot-num]').textContent = (at + 1) + ' / ' + list.length;
        img.src = node.getAttribute('data-sl-fm-img');
        img.alt = node.getAttribute('data-sl-fm-name');
        down.href = node.getAttribute('data-sl-fm-down') || '#';
        down.hidden = !node.getAttribute('data-sl-fm-down');
        if (window.htmx) window.htmx.ajax('GET', node.getAttribute('data-sl-fm-info'), { target: 'dialog[data-sl-shot="files"] [data-sl-shot-props]', swap: 'innerHTML' });
        window.setWindowOpen(box);
    }
    /* An action of the gallery is the action of the object it shows: the fan of the row already carries it with its form and its question, so the picture only presses it */
    function setFileRun(name) {
        var box = document.querySelector('dialog[data-sl-shot="files"]');
        var list = getFileShots();
        var own = list[fmshot] ? list[fmshot].closest('tr, .sl-fm-cell') : null;
        var item = own ? own.querySelector('[data-sl-fm-run="' + name + '"], [data-sl-fm-act="' + name + '"]') : null;
        if (box && box.open) window.setWindowClose(box);
        if (item) item.click();
    }
    document.addEventListener('change', function (event) {
        var node = event.target && event.target.closest ? event.target.closest('[data-sl-fm-mark],[data-sl-fm-all]') : null;
        if (node) setFileMarks(node);
    });
    document.addEventListener('keydown', function (event) {
        var box = document.querySelector('dialog[data-sl-shot="files"]');
        if (!box || !box.open) return;
        if (event.key === 'ArrowLeft') setFileShot(-1, null);
        if (event.key === 'ArrowRight') setFileShot(1, null);
    });
    var fmhit = '[data-sl-fm-view],[data-sl-fm-pick],[data-sl-fm-go],[data-sl-fm-act],[data-sl-fm-show],[data-sl-fm-run],[data-sl-shot-step],[data-sl-shot-act]';
    document.addEventListener('click', function (event) {
        var node = event.target && event.target.closest ? event.target.closest(fmhit) : null;
        if (!node) return;
        if (node.hasAttribute('data-sl-fm-show')) {
            event.preventDefault();
            return setFileShot(0, node);
        }
        if (node.hasAttribute('data-sl-shot-step') && node.closest('dialog[data-sl-shot="files"]')) return setFileShot(parseInt(node.getAttribute('data-sl-shot-step'), 10), null);
        /* The fan of a row opens the gallery on the object the row is about; every other action of the fan is a link or a form of its own and is left alone */
        if (node.getAttribute('data-sl-fm-run') === 'preview') {
            var own = node.closest('tr, .sl-fm-cell');
            var shot = own ? own.querySelector('[data-sl-fm-show]') : null;
            event.preventDefault();
            if (shot) setFileShot(0, shot);
            return;
        }
        if (node.hasAttribute('data-sl-shot-act') && node.closest('dialog[data-sl-shot="files"]')) return setFileRun(node.getAttribute('data-sl-shot-act'));
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
    /* An open source editor holds work the server has not seen while its text differs from the one it was loaded or last submitted with, which the editor runtime keeps */
    var fmfree = false;
    function checkFileEdit() {
        var box = document.querySelector('[data-sl-fm-ask]');
        var id = box ? box.getAttribute('data-sl-fm-code') : '';
        return !fmfree && !!id && !!window.SlaedEditor && window.SlaedEditor.isDirty(id);
    }
    /* Once the administrator answered yes, the way out is released: without that the browser asks a second time with its own dialog on the navigation the answer just allowed */
    function setFileLeave(run) {
        window.setConfirmTask(document.querySelector('[data-sl-fm-ask]').getAttribute('data-sl-fm-ask'), function () {
            fmfree = true;
            run();
        });
    }
    /* The answer replaces the whole body, so the marks below belong to the body that is leaving and are never taken back on a swap that succeeded */
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
    /* Only a request of the browser itself is guarded: the panels of every admin page refresh without touching the editor and must not ask */
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
    /* The ladder of the groups: a sort button turns its list over by the key its steps name, in place, the id breaking a tie; nothing is stored */
    document.addEventListener('click', function (event) {
        var btn = event.target && event.target.closest ? event.target.closest('[data-sl-ladder-sort]') : null;
        var list = btn ? document.getElementById(btn.getAttribute('aria-controls')) : null;
        if (!list) return;
        var key = 'data-sl-sort-' + btn.getAttribute('data-sl-ladder-sort');
        var desc = btn.getAttribute('aria-pressed') !== 'true';
        var num = function (node, name) { return parseInt(node.getAttribute(name), 10) || 0; };
        Array.prototype.slice.call(list.children).sort(function (a, b) {
            var gap = num(a, key) - num(b, key);
            return gap ? (desc ? -gap : gap) : num(a, 'data-sl-sort-id') - num(b, 'data-sl-sort-id');
        }).forEach(function (step) { list.appendChild(step); });
        btn.setAttribute('aria-pressed', desc ? 'true' : 'false');
    });
    /* The group form: one rank string icon:lit:total:#rrggbb, by the rules getRankData() of the core checks */
    function getRankParts(rank) {
        var m = /^([a-z0-9-]+):(\d{1,2}):(\d{1,2}):(#[0-9a-f]{6})$/.exec(rank || '');
        if (!m) return null;
        var lit = parseInt(m[2], 10);
        var total = parseInt(m[3], 10);
        return (total < 1 || total > 10 || lit > total) ? null : { icon: m[1], lit: lit, total: total, color: m[4] };
    }
    var rankpen = null;
    /* Any CSS colour as the browser paints it, one pixel of a canvas: a relative colour, an oklch or a hex alike */
    function getColorRgb(css) {
        if (!rankpen) {
            var cv = document.createElement('canvas');
            cv.width = 1;
            cv.height = 1;
            rankpen = cv.getContext('2d', { willReadFrequently: true });
        }
        rankpen.clearRect(0, 0, 1, 1);
        rankpen.fillStyle = '#fff';
        rankpen.fillStyle = css;
        rankpen.fillRect(0, 0, 1, 1);
        return Array.prototype.slice.call(rankpen.getImageData(0, 0, 1, 1).data, 0, 3);
    }
    /* The relative luminance of a colour by WCAG */
    function getColorLight(rgb) {
        var lin = rgb.map(function (v) {
            v = v / 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2];
    }
    /* The contrast ratio of two CSS colours */
    function getColorRatio(fore, back) {
        var a = getColorLight(getColorRgb(fore));
        var b = getColorLight(getColorRgb(back));
        return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
    }
    /* A row of choices is one stop for Tab and is walked with the arrows; the chosen member, or the first, holds the stop */
    function getRankItems(row) {
        return Array.prototype.filter.call(row.querySelectorAll(':scope > button, :scope > label > input'), function (item) { return !item.hidden; });
    }
    /* Give the Tab stop of a row to its chosen member, or to the first */
    function setRankRoving(row) {
        var items = getRankItems(row);
        var cur = items.filter(function (item) { return item.getAttribute('aria-pressed') === 'true' || !!item.closest('.sl-rank-custom.sl-is-active'); })[0] || items[0];
        items.forEach(function (item) { item.tabIndex = item === cur ? 0 : -1; });
    }
    /* A threshold with its thousands apart, as the list prints it */
    function getRankNumber(num) {
        return String(num).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }
    /* Put the values into the %s of a sentence in their order */
    function getRankFill(text, args) {
        var at = 0;
        return String(text || '').replace(/%s/g, function () { return args[at++] || ''; });
    }
    /* Where a group with this threshold would stand among the other point groups, in words */
    function getRankPlace(form, pts) {
        var hint = form.querySelector('[data-sl-rank-place]');
        var list = [];
        try { list = JSON.parse(form.getAttribute('data-sl-rank-steps') || '[]'); } catch (e) { list = []; }
        var prev = null;
        var next = null;
        list.forEach(function (step) {
            if (step.points <= pts) prev = step;
            else if (!next) next = step;
        });
        var pair = function (step) { return [step.name, getRankNumber(step.points)]; };
        if (!hint) return '';
        if (prev && prev.points === pts) return getRankFill(hint.getAttribute('data-sl-rank-taken'), pair(prev));
        if (!prev && !next) return hint.getAttribute('data-sl-rank-only');
        if (!prev) return getRankFill(hint.getAttribute('data-sl-rank-first'), pair(next));
        if (!next) return getRankFill(hint.getAttribute('data-sl-rank-last'), pair(prev));
        return getRankFill(hint.getAttribute('data-sl-rank-between'), pair(prev).concat(pair(next)));
    }
    /* A printed rank takes the new marks by cloning its own first one, so the markup stays the one of the theme fragment */
    function setRankMarks(span, data, title) {
        var mark = span.querySelector('i');
        if (!mark) return;
        while (span.children.length > data.total) span.removeChild(span.lastElementChild);
        while (span.children.length < data.total) span.appendChild(mark.cloneNode(false));
        Array.prototype.forEach.call(span.children, function (item, n) { item.className = 'bi bi-' + data.icon + (n < data.lit ? ' sl-is-active' : ''); });
        span.style.setProperty('--sl-d-rank', data.color);
        span.setAttribute('aria-label', title);
        span.setAttribute('title', title);
    }
    /* Answer every change at once: the rank and its proof, each tile against its own surface with the name as painted, the swatches, the threshold */
    function setRankView(form) {
        var field = form.elements;
        var total = Math.min(10, Math.max(1, parseInt(field.rank_total.value, 10) || 1));
        field.rank_lit.max = String(total);
        if ((parseInt(field.rank_lit.value, 10) || 0) > total) field.rank_lit.value = String(total);
        var color = String(field.color.value || '').toLowerCase();
        var sync = field.rank_sync.value === '1';
        if (sync) field.rank_color.value = color;
        var raw = field.rank_icon.value.trim();
        var lit = Math.min(total, Math.max(0, parseInt(field.rank_lit.value, 10) || 0));
        var data = getRankParts((/^[a-z0-9-]+$/.test(raw) ? raw : 'x') + ':' + lit + ':' + total + ':'
            + String(field.rank_color.value || color).toLowerCase());
        var name = field.grname.value.trim();
        var title = name ? form.getAttribute('data-sl-rank-group') + name : form.getAttribute('data-sl-rank-none');
        var each = function (sel, run) { Array.prototype.forEach.call(form.querySelectorAll(sel), run); };
        form.style.setProperty('--sl-d-group', color);
        each('[data-sl-rank-out="total"]', function (node) { node.textContent = String(total); });
        each('[data-sl-rank-out="lit"]', function (node) { node.textContent = String(lit); });
        each('[data-sl-rank-name]', function (node) { node.textContent = name || form.getAttribute('data-sl-rank-blank'); });
        if (data && raw === data.icon) {
            each('[data-sl-rank-live] .sl-rank', function (span) { setRankMarks(span, data, title); });
            each('[data-sl-icon-preview]', function (node) { node.className = 'bi bi-' + data.icon; });
            var rank = data.icon + ':' + data.lit + ':' + data.total + ':' + data.color;
            each('[data-sl-rank-set]', function (node) { node.setAttribute('aria-pressed', String(node.getAttribute('data-sl-rank-set') === rank)); });
            each('[data-sl-rank-icon]', function (node) { node.setAttribute('aria-pressed', String(node.getAttribute('data-sl-rank-icon') === data.icon)); });
        }
        var tone = data ? data.color : String(field.rank_color.value).toLowerCase();
        var tones = { rank: [], group: [] };
        each('[data-sl-rank-tone]', function (node) {
            var val = node.getAttribute('data-sl-rank-tone');
            if (val !== 'group') tones.rank.push(val);
            node.setAttribute('aria-pressed', String(val === 'group' ? sync : (!sync && val === tone)));
        });
        each('[data-sl-group-tone]', function (node) {
            tones.group.push(node.getAttribute('data-sl-group-tone'));
            node.setAttribute('aria-pressed', String(node.getAttribute('data-sl-group-tone') === color));
        });
        each('[data-sl-tone-custom]', function (node) {
            var own = node.getAttribute('data-sl-tone-custom') === 'rank';
            var val = own ? tone : color;
            node.style.setProperty('--sl-d-swatch', val);
            node.classList.toggle('sl-is-active', !(own && sync) && tones[own ? 'rank' : 'group'].indexOf(val) < 0);
        });
        each('[data-sl-rank-tile]', function (tile) {
            var back = getComputedStyle(tile).backgroundColor;
            var named = tile.querySelector('[data-sl-rank-name]');
            var mark = getColorRatio(tone, back);
            var text = getColorRatio(named ? getComputedStyle(named).color : color, back);
            tile.querySelector('[data-sl-rank-ratio="mark"]').textContent = mark.toFixed(1) + ':1';
            tile.querySelector('[data-sl-rank-ratio="text"]').textContent = text.toFixed(1) + ':1';
            tile.toggleAttribute('data-sl-bad-mark', mark < 3);
            tile.toggleAttribute('data-sl-bad-text', text < 3);
        });
        each('[data-sl-rank-note]', function (node) { node.hidden = !form.querySelector('[data-sl-bad-mark], [data-sl-bad-text]'); });
        each('[data-sl-rank-points]', function (node) { node.classList.toggle('sl-hidden', field.grextra.checked); });
        each('[data-sl-rank-place-text]', function (node) { node.textContent = getRankPlace(form, Math.max(0, parseInt(field.points.value, 10) || 0)); });
        each('[data-sl-roving]', setRankRoving);
    }
    /* Wire the choices and the arrows of the rows (up and down by a measured line); a choice written into the hidden fields tells the leave question of slaed.js */
    function setRankForm(form) {
        var field = form.elements;
        var setDirty = function () {
            form.dispatchEvent(new Event('change'));
        };
        form.addEventListener('input', function (event) {
            if (event.target.name === 'rank_color') field.rank_sync.value = '0';
            setRankView(form);
        });
        form.addEventListener('change', function () { setRankView(form); });
        form.addEventListener('click', function (event) {
            var node = event.target && event.target.closest ? event.target : null;
            if (!node) return;
            var set = node.closest('[data-sl-rank-set]');
            var tone = node.closest('[data-sl-rank-tone]');
            var gtone = node.closest('[data-sl-group-tone]');
            var icon = node.closest('[data-sl-rank-icon]');
            var data = set ? getRankParts(set.getAttribute('data-sl-rank-set')) : null;
            if (data) {
                field.rank_icon.value = data.icon;
                field.rank_total.value = String(data.total);
                field.rank_lit.max = String(data.total);
                field.rank_lit.value = String(data.lit);
                field.rank_color.value = data.color;
                field.rank_sync.value = '0';
            } else if (tone) {
                field.rank_sync.value = tone.getAttribute('data-sl-rank-tone') === 'group' ? '1' : '0';
                if (field.rank_sync.value === '0') field.rank_color.value = tone.getAttribute('data-sl-rank-tone');
            } else if (gtone) {
                field.color.value = gtone.getAttribute('data-sl-group-tone');
            } else if (icon) {
                field.rank_icon.value = icon.getAttribute('data-sl-rank-icon');
            } else {
                return;
            }
            setDirty();
        });
        form.addEventListener('keydown', function (event) {
            var row = event.target.closest ? event.target.closest('[data-sl-roving]') : null;
            if (!row || ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].indexOf(event.key) < 0) return;
            var items = getRankItems(row);
            var at = items.indexOf(event.target);
            if (at < 0) return;
            var top = items[0].getBoundingClientRect().top;
            var across = items.filter(function (item) { return Math.abs(item.getBoundingClientRect().top - top) < 2; }).length || items.length;
            var move = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -across, ArrowDown: across };
            var to = event.key === 'Home' ? 0 : (event.key === 'End' ? items.length - 1 : at + move[event.key]);
            to = Math.max(0, Math.min(items.length - 1, to));
            event.preventDefault();
            items.forEach(function (item, n) { item.tabIndex = n === to ? 0 : -1; });
            items[to].focus();
        });
        setRankView(form);
    }
    /* Wire the group form when the page carries one */
    function addRankForm() {
        var form = document.querySelector('[data-sl-rank-form]');
        if (form) setRankForm(form);
    }
    /* The name a field carries in its row, without the hint under it; a field outside a row says its own */
    function getFieldName(field) {
        var row = field.closest('.sl-div-item');
        var cap = row ? row.querySelector('.sl-div-label label, .sl-div-label > span[id]') : null;
        return (cap ? cap.textContent : (field.getAttribute('aria-label') || field.name)).trim();
    }
    /* Whether the row of a field stands on its tab; a row another choice of the form hides holds nothing to go to or to judge */
    function isFieldShown(field) {
        var row = field.closest('.sl-div-item');
        return !row || window.getComputedStyle(row).display !== 'none';
    }
    /* The first field of every shown row of the changeable forms of the page, the ones a find or the palette can lead to */
    function getFormFields() {
        return Array.prototype.map.call(document.querySelectorAll('[data-sl-dirty] .sl-div-item'), function (row) {
            return row.querySelector('input:not([type="hidden"]), select, textarea');
        }).filter(function (field) { return field && isFieldShown(field); });
    }
    /* Open every tab around a field, light its row and put the focus in it: the summary, the settings find and the palette lead here */
    function setFormJump(field) {
        var panes = [];
        var row = field.closest('.sl-div-item');
        for (var pane = field.closest('[data-sl-tab-panel]'); pane; pane = pane.parentElement.closest('[data-sl-tab-panel]')) panes.unshift(pane);
        panes.forEach(function (one) {
            var tab = one.hidden ? document.querySelector('[data-sl-tab-target="' + one.id + '"]') : null;
            if (tab) tab.click();
        });
        if (row) {
            row.removeAttribute('data-sl-found');
            void row.offsetWidth;
            row.setAttribute('data-sl-found', '');
        }
        field.focus();
    }
    /* Judge one field by the rules its markup states, in the words of the summary: empty where required, or a value of the wrong kind */
    function getFieldCheck(sum, field) {
        if (!field.willValidate || field.validity.valid || !isFieldShown(field)) return '';
        return sum.getAttribute(field.validity.valueMissing ? 'data-sl-fill' : 'data-sl-bad').replace('%s', getFieldName(field));
    }
    /* Say under a field what is wrong with it and name the words in its description; an empty text takes them back */
    function setFieldError(sum, field, text) {
        var err = field.slErr;
        var ids;
        if (!err && !text) return;
        if (!err) {
            err = field.slErr = sum.querySelector('template').content.querySelector('span').cloneNode(true);
            err.id = (field.id || field.name) + '-err';
            (field.closest('.sl-div-field') || field.parentNode).appendChild(err);
        }
        err.textContent = text;
        err.hidden = !text;
        if (text) field.setAttribute('aria-invalid', 'true');
        else field.removeAttribute('aria-invalid');
        ids = (field.getAttribute('aria-describedby') || '').split(' ').filter(function (one) { return one && one !== err.id; });
        if (text) ids.push(err.id);
        if (ids.length) field.setAttribute('aria-describedby', ids.join(' '));
        else field.removeAttribute('aria-describedby');
    }
    /* The check of the form canon: judged on the first save and then on leaving each field, a summary above the form leading into hidden tabs */
    function setFormCheck(form) {
        var sum = form.querySelector('[data-sl-valid-sum]');
        var judged = false;
        if (!sum) return;
        form.addEventListener('submit', function (event) {
            var seen = {};
            var bad = [];
            var list = sum.querySelector('ul');
            judged = true;
            Array.prototype.forEach.call(form.elements, function (field) {
                var text;
                if (!field.name || (field.type === 'radio' && seen[field.name])) return;
                seen[field.name] = true;
                text = getFieldCheck(sum, field);
                setFieldError(sum, field, text);
                if (text) bad.push([field, text]);
            });
            list.textContent = '';
            sum.hidden = !bad.length;
            if (!bad.length) return;
            event.preventDefault();
            event.stopPropagation();
            bad.forEach(function (one) {
                var item = sum.querySelector('template').content.querySelector('li').cloneNode(true);
                var link = item.querySelector('a');
                link.textContent = one[1];
                link.slField = one[0];
                list.appendChild(item);
            });
            sum.querySelector('b').textContent = sum.getAttribute('data-sl-valid-sum').replace('%s', bad.length);
            sum.focus();
            window.setSoundPlay('error');
        });
        form.addEventListener('focusout', function (event) {
            var field = event.target;
            if (judged && field.name && field.type !== 'radio' && field.willValidate) setFieldError(sum, field, getFieldCheck(sum, field));
        });
        sum.addEventListener('click', function (event) {
            var link = event.target.closest ? event.target.closest('a') : null;
            if (!link || !link.slField) return;
            event.preventDefault();
            setFormJump(link.slField);
        });
    }
    /* The settings find in the module head: rows whose name or hint hold the typed letters, walked by the arrows, Enter or a click leading to the field */
    function setFormFind() {
        var find = document.getElementById('sl-form-find');
        var list = document.getElementById('sl-form-hits');
        var at = -1;
        var draw = function () {
            var term = find.value.trim().toLowerCase();
            var tpl = list.parentNode.querySelector('template').content;
            list.textContent = '';
            if (term.length > 1) {
                getFormFields().filter(function (field) {
                    return field.closest('.sl-div-item').querySelector('.sl-div-label').textContent.toLowerCase().indexOf(term) >= 0;
                }).slice(0, 8).forEach(function (field, n) {
                    var hit = tpl.querySelector('[role="option"]').cloneNode(true);
                    hit.id = 'sl-form-hit-' + n;
                    hit.textContent = getFieldName(field);
                    hit.slField = field;
                    list.appendChild(hit);
                });
                if (!list.children.length) list.appendChild(tpl.querySelector('li:not([role])').cloneNode(true));
            }
            list.hidden = !list.children.length;
            find.setAttribute('aria-expanded', list.hidden ? 'false' : 'true');
            find.removeAttribute('aria-activedescendant');
            at = -1;
        };
        var jump = function (hit) {
            find.value = '';
            draw();
            setFormJump(hit.slField);
        };
        if (!find || !list) return;
        find.addEventListener('input', draw);
        find.addEventListener('keydown', function (event) {
            var hits = Array.prototype.slice.call(list.querySelectorAll('[role="option"]'));
            if (event.key === 'Escape' && find.value !== '') {
                event.preventDefault();
                find.value = '';
                draw();
                return;
            }
            if (!hits.length) return;
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                at = (at + (event.key === 'ArrowDown' ? 1 : -1) + hits.length) % hits.length;
                hits.forEach(function (hit, n) { hit.setAttribute('aria-selected', n === at ? 'true' : 'false'); });
                find.setAttribute('aria-activedescendant', hits[at].id);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                jump(hits[Math.max(0, at)]);
            }
        });
        list.addEventListener('mousedown', function (event) { event.preventDefault(); });
        list.addEventListener('click', function (event) {
            var hit = event.target.closest ? event.target.closest('[role="option"]') : null;
            if (hit) jump(hit);
        });
        find.addEventListener('blur', function () {
            list.hidden = true;
            find.setAttribute('aria-expanded', 'false');
        });
    }
    /* Wire the form canon where the page carries it: the check of each form that asks for one and the settings find */
    function addFormCanon() {
        document.querySelectorAll('form[data-sl-valid]').forEach(setFormCheck);
        setFormFind();
    }
    /* Motion over the page: the place of every solid head in the order its beam crosses; the light that follows the pointer is slaed.js's */
    function setBeamTurns() {
        document.querySelectorAll('.sl-dashboard-panel-head, .sl-block-sidebar h3').forEach(function (head, i) {
            head.style.setProperty('--sl-d-turn', i);
        });
    }
    /* Every part of the installation is its own POST of the run form, so the bar shows how far the server really got */
    function setSetupRun() {
        var run = document.querySelector('[data-sl-setup-run]');
        var form = run ? run.closest('form') : null;
        if (!form) return;
        var bar = run.querySelector('[data-sl-setup-bar]');
        var line = run.querySelector('[role="progressbar"]');
        var task = run.querySelector('[data-sl-setup-task]');
        var num = run.querySelector('[data-sl-setup-num]');
        function setSetupEnd() {
            var go = document.createElement('input');
            go.type = 'hidden';
            go.name = 'go';
            go.value = 'next';
            form.appendChild(go);
            form.submit();
        }
        function addSetupPart() {
            var data = new FormData(form);
            data.set('go', 'part');
            fetch(form.getAttribute('action'), { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    if (typeof json.percent === 'number') {
                        bar.style.width = json.percent + '%';
                        line.setAttribute('aria-valuenow', json.percent);
                        num.textContent = json.percent;
                    }
                    if (json.task) task.textContent = json.task;
                    if (json.more) addSetupPart();
                    else window.setTimeout(setSetupEnd, window.isMotionStill() ? 0 : 400);
                })
                .catch(setSetupEnd);
        }
        addSetupPart();
    }
    var phone = window.matchMedia('(max-width: 900px)');
    // Whether the menu stands folded into the rail; a phone keeps its drawer and has none
    function isMenuRail() {
        return document.documentElement.hasAttribute('data-sl-rail') && !phone.matches;
    }
    // A block folded by hand shows its sections while a search runs or the rail stands, and takes its own state back after
    function setMenuOpen(box, force) {
        if (force && !box.hasAttribute('data-sl-keep')) {
            box.setAttribute('data-sl-keep', JSON.stringify([box.hidden, box.style.display]));
            box.hidden = false;
            box.style.display = '';
        } else if (!force && box.hasAttribute('data-sl-keep')) {
            var was = JSON.parse(box.getAttribute('data-sl-keep'));
            box.removeAttribute('data-sl-keep');
            box.hidden = was[0];
            box.style.display = was[1];
        }
    }
    // The find field narrows the sections where they are listed, the menu of a screen or the cards of the home; the other blocks of the menu step aside
    function setMenuFind() {
        var find = document.querySelector('[data-sl-menu-find]');
        var term = find ? find.value.trim().toLowerCase() : '';
        document.querySelectorAll('#sl-menu, .sl-admin-content').forEach(function (scope) {
            var groups = scope.querySelectorAll('[data-sl-find-group]');
            var seen = 0;
            groups.forEach(function (group) {
                var hits = 0;
                group.querySelectorAll('[data-sl-menu-item]').forEach(function (item) {
                    var hit = !term || (item.textContent + ' ' + item.getAttribute('data-sl-menu-item')).toLowerCase().indexOf(term) !== -1;
                    item.hidden = !hit;
                    if (hit) hits += 1;
                });
                group.hidden = term !== '' && hits === 0;
                seen += hits;
            });
            if (groups.length && scope.id === 'sl-menu') {
                scope.querySelectorAll('.sl-block-sidebar:not([data-sl-find-group])').forEach(function (block) { block.hidden = term !== ''; });
            }
            scope.querySelectorAll('[data-sl-menu-empty]').forEach(function (note) {
                note.textContent = (groups.length && term !== '' && seen === 0) ? note.getAttribute('data-sl-menu-empty') : '';
            });
        });
        document.querySelectorAll('#sl-menu .sl-block-sidebar-content[data-sl-toggle]').forEach(function (box) { setMenuOpen(box, term !== '' || isMenuRail()); });
        document.querySelectorAll('.sl-admin-content .sl-dashboard-panel-content[data-sl-toggle]').forEach(function (box) { setMenuOpen(box, term !== ''); });
    }
    document.addEventListener('input', function (event) {
        if (event.target && event.target.matches && event.target.matches('[data-sl-menu-find]')) setMenuFind();
    });
    // Fold the menu into the rail and back: the root attribute at once, the width moving only under a hand, the choice kept in its cookie
    function setMenuRail(on) {
        var root = document.documentElement;
        root.setAttribute('data-sl-rail-anim', '');
        window.setTimeout(function () { root.removeAttribute('data-sl-rail-anim'); }, 400);
        root.toggleAttribute('data-sl-rail', on);
        document.querySelectorAll('[data-sl-rail-toggle]').forEach(function (btn) {
            var name = btn.getAttribute(on ? 'data-sl-unfold' : 'data-sl-fold');
            btn.setAttribute('aria-expanded', on ? 'false' : 'true');
            btn.setAttribute('aria-label', name);
            btn.title = name + ' ( [ )';
            btn.querySelector('.bi').className = 'bi ' + (on ? 'bi-layout-sidebar' : 'bi-layout-sidebar-inset');
        });
        setMenuFind();
        window.setOptionSend('rail', on ? '1' : '0').catch(function () {});
    }
    // Arrows walk the icons of the rail as in a menu; Tab still passes through them in order
    function setRailStep(event) {
        var sel = '#sl-menu .sl-menu-tool, #sl-menu [data-sl-menu-item] > a, #sl-menu .sl-block-sidebar-count-label a';
        var items = Array.prototype.filter.call(document.querySelectorAll(sel), function (one) { return one.getClientRects().length > 0; });
        var at = items.indexOf(document.activeElement);
        if (at < 0) return;
        event.preventDefault();
        items[(at + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length].focus();
    }
    // One row of the palette for every section the page links, its icon and name taken from the link, each address once, and one for each setting a find serves
    function setPaletteParts(pal) {
        var group = pal.querySelector('[data-sl-pal-parts]');
        var tpl = group.querySelector('template[data-sl-pal-part]');
        var seen = {};
        document.querySelectorAll('#sl-menu [data-sl-menu-item] > a, .sl-admin-content [data-sl-menu-item] > a').forEach(function (link, i) {
            var row, icon, name;
            if (seen[link.href]) return;
            seen[link.href] = true;
            icon = link.querySelector('.bi');
            name = link.querySelector('.sl-menu-name');
            row = tpl.content.firstElementChild.cloneNode(true);
            row.id = 'sl-pal-part-' + i;
            row.setAttribute('data-sl-pal-href', link.href);
            if (icon) row.querySelector('.bi').className = icon.className;
            row.querySelector('.sl-editor-pal-name').textContent = (name || link).textContent.trim();
            group.appendChild(row);
        });
        if (!document.getElementById('sl-form-find')) return;
        getFormFields().forEach(function (field, i) {
            var row = tpl.content.firstElementChild.cloneNode(true);
            row.id = 'sl-pal-set-' + i;
            row.slField = field;
            row.querySelector('.bi').className = 'bi bi-gear';
            row.querySelector('.sl-editor-pal-name').textContent = getFieldName(field);
            pal.querySelector('[data-sl-pal-sets]').appendChild(row);
        });
    }
    // Open the palette of the panel: its sections gathered once, the fold named for what it will do and offered only where a rail can stand
    function setPanelPalette() {
        var pal = document.getElementById('sl-palette');
        var fold;
        if (!pal || !window.setPaletteOpen) return;
        if (!pal.slParts) {
            pal.slParts = true;
            setPaletteParts(pal);
        }
        fold = pal.querySelector('[data-sl-pal-act="rail"]');
        fold.slOff = phone.matches || !document.querySelector('[data-sl-rail-toggle]');
        fold.slName = fold.getAttribute(isMenuRail() ? 'data-sl-unfold' : 'data-sl-fold');
        window.setPaletteOpen(pal, setPaletteRun, '');
    }
    // Run the command the palette chose once it has closed: a section opens, a setting is gone to, an action goes through the control it stands for
    function setPaletteRun(row) {
        var act = row.getAttribute('data-sl-pal-act');
        var href = row.getAttribute('data-sl-pal-href');
        var pick;
        if (row.slField) return setFormJump(row.slField);
        if (href) return window.location.assign(href);
        if (act === 'rail') return setMenuRail(!isMenuRail());
        if (act === 'options') return window.setWindowOpen(document.getElementById('sl-settings'));
        pick = document.getElementById('sl-set-' + act);
        if (!pick) return;
        pick.value = act === 'mode' ? row.getAttribute('data-sl-pal-arg') : (pick.value === '1' ? '0' : '1');
        pick.dispatchEvent(new Event('change', { bubbles: true }));
    }
    // Keys of the whole panel: Ctrl+K the palette, [ the rail, / the find field, arrows in the rail; a field, a window or an editor keeps its own
    document.addEventListener('keydown', function (event) {
        var find;
        if (event.defaultPrevented || event.isComposing) return;
        if ((event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey && event.code === 'KeyK') {
            if (!document.getElementById('sl-palette') || document.querySelector('dialog[open]')) return;
            event.preventDefault();
            setPanelPalette();
            return;
        }
        if (event.ctrlKey || event.metaKey || event.altKey) return;
        if (isMenuRail() && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) return setRailStep(event);
        if (event.target.closest && event.target.closest('input, select, textarea, dialog, [contenteditable]')) return;
        if (event.code === 'BracketLeft' && !phone.matches && document.querySelector('[data-sl-rail-toggle]')) {
            event.preventDefault();
            setMenuRail(!isMenuRail());
            return;
        }
        if (setListKeys(event)) {
            event.preventDefault();
            return;
        }
        find = document.querySelector('[data-sl-list] .sl-filter input[type="search"]') || document.getElementById('sl-form-find') || document.querySelector('[data-sl-menu-find]');
        if (event.key === '/' && find && find.getClientRects().length && window.getComputedStyle(find).visibility === 'visible') {
            event.preventDefault();
            find.focus();
        }
    });
    phone.addEventListener('change', setMenuFind);
    var held = [];
    // The drawer of a phone: the rest of the page goes inert while it is open, the focus comes back to the menu button
    function setDrawer(open) {
        var menu = document.getElementById('sl-menu');
        var btn = document.querySelector('[data-sl-menu-open]');
        if (!menu || !btn) return;
        var on = open && phone.matches;
        document.body.toggleAttribute('data-sl-drawer', on);
        btn.setAttribute('aria-expanded', on ? 'true' : 'false');
        held.forEach(function (node) { node.inert = false; });
        held = [];
        if (on) {
            for (var node = menu; node.parentElement; node = node.parentElement) {
                Array.prototype.forEach.call(node.parentElement.children, function (item) {
                    if (item === node || item.inert || item.hasAttribute('data-sl-menu-close') || item.tagName === 'SCRIPT') return;
                    item.inert = true;
                    held.push(item);
                });
                if (node.parentElement === document.body) break;
            }
            menu.querySelector('input, button, a').focus();
        } else if (menu.contains(document.activeElement)) {
            btn.focus();
        }
    }
    document.addEventListener('click', function (event) {
        if (!event.target.closest) return;
        if (event.target.closest('[data-sl-menu-open]')) setDrawer(!document.body.hasAttribute('data-sl-drawer'));
        else if (event.target.closest('[data-sl-menu-close]')) setDrawer(false);
        else if (event.target.closest('[data-sl-pal-open]')) setPanelPalette();
        else if (event.target.closest('[data-sl-rail-toggle]')) setMenuRail(!isMenuRail());
    });
    // A window opened from the drawer needs the page back: the drawer shuts first, so the window is not left inert and returns the focus to the menu button
    document.addEventListener('click', function (event) {
        if (event.target.closest && event.target.closest('#sl-menu [data-sl-open]') && document.body.hasAttribute('data-sl-drawer')) setDrawer(false);
    }, true);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && document.body.hasAttribute('data-sl-drawer')) setDrawer(false);
    });
    phone.addEventListener('change', function () {
        if (!phone.matches && document.body.hasAttribute('data-sl-drawer')) setDrawer(false);
    });
    var queue = { sum: -1, unseen: 0, title: '', mods: {} };
    // The rows of one list in the order of their numbers, the fullest first; equal numbers keep the order the page gave them
    function setQueueOrder(box) {
        var rows = Array.prototype.filter.call(box.children, function (row) { return row.hasAttribute('data-sl-queue-row'); });
        if (rows.length < 2) return;
        var after = rows[rows.length - 1].nextSibling;
        rows.forEach(function (row, i) { if (!row.hasAttribute('data-sl-queue-at')) row.setAttribute('data-sl-queue-at', i); });
        rows.sort(function (one, two) {
            var diff = Number(two.querySelector('[data-sl-queue-n]').textContent) - Number(one.querySelector('[data-sl-queue-n]').textContent);
            return diff || one.getAttribute('data-sl-queue-at') - two.getAttribute('data-sl-queue-at');
        });
        rows.forEach(function (row) { box.insertBefore(row, after); });
    }
    // The title of the tab counts the arrivals of the queues while the tab is not seen, and gives the count up once it is
    function setQueueTitle() {
        if (!queue.title) queue.title = document.title;
        document.title = (queue.unseen ? '(' + queue.unseen + ') ' : '') + queue.title;
    }
    // The tab is in sight again: the count leaves its title
    function setQueueSeen() {
        if (document.hidden || !queue.unseen) return;
        queue.unseen = 0;
        setQueueTitle();
    }
    // Д8 Д9 One poll answer feeds the bell, the waiting block, the chips and the sum; a grown queue is heard, one emptied by an action of this page sounds the win
    function setQueueView(list, acted) {
        var bell = document.querySelector('[data-sl-bell]');
        var mods = {};
        var sum = 0;
        list.forEach(function (row) {
            sum += row.num;
            mods[row.mod] = (mods[row.mod] || 0) + row.num;
            document.querySelectorAll('[data-sl-queue-row="' + CSS.escape(row.key) + '"]').forEach(function (node) {
                var num = node.querySelector('[data-sl-queue-n]');
                num.textContent = row.num;
                if (num.classList.contains('sl-chip')) {
                    num.classList.toggle('sl-chip-warn', row.num > 0);
                    num.classList.toggle('sl-chip-success', row.num === 0);
                }
                if (node.tagName === 'A') node.hidden = row.num === 0;
            });
        });
        document.querySelectorAll('[data-sl-queue]').forEach(function (chip) {
            var num = mods[chip.getAttribute('data-sl-queue')] || 0;
            chip.querySelector('[data-sl-queue-n]').textContent = num;
            chip.hidden = num === 0;
        });
        var boxes = [];
        document.querySelectorAll('[data-sl-queue-row]').forEach(function (row) { if (boxes.indexOf(row.parentNode) === -1) boxes.push(row.parentNode); });
        boxes.forEach(setQueueOrder);
        document.querySelectorAll('[data-sl-bell-sum]').forEach(function (node) {
            node.textContent = sum;
            node.hidden = sum === 0;
        });
        document.querySelectorAll('[data-sl-bell-none]').forEach(function (node) { node.hidden = sum !== 0; });
        bell.setAttribute('aria-label', bell.getAttribute('data-sl-bell-label') + ': ' + sum);
        if (queue.sum >= 0 && sum > queue.sum) {
            bell.removeAttribute('data-sl-ping');
            void bell.offsetWidth;
            bell.setAttribute('data-sl-ping', '');
            if (document.hidden || !document.hasFocus()) {
                queue.unseen += sum - queue.sum;
                setQueueTitle();
            }
            window.setSoundPlay('queue');
        }
        if (acted && Object.keys(queue.mods).some(function (mod) { return queue.mods[mod] > 0 && !mods[mod]; })) window.setSoundPlay('win');
        queue.sum = sum;
        queue.mods = mods;
    }
    // Ask the server for the queues now; acted says an action of this page has just reached it
    function setQueueFetch(acted) {
        var bell = document.querySelector('[data-sl-bell]');
        if (!bell) return;
        fetch(bell.getAttribute('data-sl-bell'), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-SL-Poll': '1' } })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (!data || !Array.isArray(data.rows)) return;
                setQueueView(data.rows, acted);
                setSessionLeft(data.left);
            })
            .catch(function () {});
    }
    // The bell asks the server for the queues once a minute, on a hidden tab too, since that is where the count of the title is made
    function setQueuePoll() {
        var bell = document.querySelector('[data-sl-bell]');
        if (!bell) return;
        queue.sum = Number(bell.querySelector('[data-sl-bell-sum]').textContent) || 0;
        document.querySelectorAll('[data-sl-queue]').forEach(function (chip) {
            queue.mods[chip.getAttribute('data-sl-queue')] = Number(chip.querySelector('[data-sl-queue-n]').textContent) || 0;
        });
        window.setInterval(function () { setQueueFetch(false); }, 60000);
        document.addEventListener('visibilitychange', setQueueSeen);
        window.addEventListener('focus', setQueueSeen);
    }
    // Г9-Г14 One answer of the cockpit poll: the tone of the panel and its line of state, the signals of the lamp in the order of the server, each ring and the strip
    function setCockpitView(box, data) {
        var loud = data.signals.filter(function (sig) { return sig.level !== 'ok'; }).length;
        var list = box.querySelector('[data-sl-signals]');
        var cap = box.querySelector('[data-sl-signals-cap]');
        var sample = box.querySelector('template[data-sl-cockpit-row]').content.firstElementChild;
        box.classList.remove('sl-knob-ok', 'sl-knob-warn', 'sl-knob-danger');
        box.classList.add('sl-knob-' + data.tone);
        box.querySelectorAll('[data-sl-tone]').forEach(function (node) { node.hidden = node.getAttribute('data-sl-tone') !== data.tone; });
        box.querySelector('[data-sl-cockpit-loud]').hidden = loud === 0;
        box.querySelector('[data-sl-cockpit-n]').textContent = loud;
        cap.textContent = cap.getAttribute(loud ? 'data-sl-loud' : 'data-sl-calm');
        data.signals.forEach(function (sig) {
            var line = list.querySelector('[data-sl-signal="' + CSS.escape(sig.key) + '"]');
            if (!line) return;
            line.className = 'sl-knob-' + sig.level;
            line.hidden = sig.level === 'ok';
            line.querySelectorAll('[data-sl-level]').forEach(function (icon) { icon.hidden = icon.getAttribute('data-sl-level') !== sig.level; });
            line.querySelector('[data-sl-signal-fact]').textContent = sig.fact;
            list.appendChild(line);
        });
        Object.keys(data.gauges).forEach(function (key) {
            var ring = box.querySelector('[data-sl-gauge="' + CSS.escape(key) + '"]');
            var gauge = data.gauges[key];
            var num = Math.round(gauge.value);
            var rows;
            if (!ring) return;
            rows = ring.parentNode.querySelector('[data-sl-gauge-rows]');
            ring.classList.remove('sl-knob-ok', 'sl-knob-info', 'sl-knob-warn', 'sl-knob-danger');
            ring.classList.add('sl-knob-' + gauge.tone);
            ring.querySelector('[data-sl-gauge-val]').textContent = num;
            ring.querySelector('.sl-knob-val').setAttribute('stroke-dasharray', gauge.value + ' 100');
            ring.setAttribute('aria-valuenow', num);
            ring.setAttribute('aria-valuetext', num + '%');
            rows.replaceChildren.apply(rows, gauge.rows.map(function (one) {
                var link = sample.cloneNode(true);
                var parts = link.querySelectorAll('span');
                link.querySelector('.bi').classList.add('bi-' + one.icon);
                parts[0].textContent = one.label;
                parts[1].textContent = one.value;
                return link;
            }));
        });
        Object.keys(data.strip).forEach(function (key) {
            var item = box.querySelector('[data-sl-strip="' + CSS.escape(key) + '"] [data-sl-strip-val]');
            if (item) item.textContent = data.strip[key].value;
        });
        box.querySelectorAll('[aria-busy]').forEach(function (node) { node.setAttribute('aria-busy', 'false'); });
        box.setAttribute('data-sl-live-stamp', Date.now());
    }
    // Г9 Г15 The cockpit asks for its figures at once and then at its interval while the tab is in sight and the live chip runs; a poll renews no session
    function setCockpitPoll() {
        var box = document.querySelector('[data-sl-cockpit]');
        var busy = false;
        var ask;
        if (!box) return;
        ask = function () {
            if (busy || document.hidden || box.hasAttribute('data-sl-live-paused')) return;
            busy = true;
            fetch(box.getAttribute('data-sl-cockpit'), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-SL-Poll': '1' } })
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (data) { if (data && data.gauges && Array.isArray(data.signals)) setCockpitView(box, data); })
                .catch(function () {})
                .then(function () { busy = false; });
        };
        ask();
        window.setInterval(ask, (Number(box.getAttribute('data-sl-cockpit-every')) || 10) * 1000);
        document.addEventListener('visibilitychange', ask);
        box.addEventListener('click', function (event) {
            if (event.target.closest('[data-sl-live-toggle]')) window.setTimeout(ask);
        });
    }
    var listretry = null;
    // The list of the canon a request comes from or lands in
    function getListBox(event) {
        var elt = event.detail ? event.detail.elt : null;
        return elt && elt.closest ? elt.closest('[data-sl-list]') : null;
    }
    // Э28 While a list loads its rows dim under the loading mark of the theme and the reader hears aria-busy
    function setListBusy(box, busy) {
        box.setAttribute('aria-busy', busy ? 'true' : 'false');
        box.querySelector('[data-sl-list-busy]').hidden = !busy;
    }
    // Э15 A filter field that acts at once sends its form: htmx carries it inside a live list, the browser elsewhere
    document.addEventListener('change', function (event) {
        var pick = event.target;
        if (!pick || !pick.matches || !pick.matches('[data-sl-filter-now]') || !pick.form) return;
        if (pick.form.requestSubmit) pick.form.requestSubmit(); else pick.form.submit();
    });
    document.addEventListener('htmx:beforeRequest', function (event) {
        var box = getListBox(event);
        if (!box) return;
        setListBusy(box, true);
        box.querySelector('[data-sl-list-error]').hidden = true;
    });
    // Э28 A list that did not come keeps its rows, marks and filters and offers the same request again
    document.addEventListener('htmx:afterRequest', function (event) {
        var box = getListBox(event);
        if (!box || !box.isConnected || event.detail.successful) return;
        var note = box.querySelector('[data-sl-list-error]');
        quiet = '';
        setListBusy(box, false);
        listretry = event.detail.elt;
        note.hidden = false;
        note.focus();
        window.setSoundPlay('error');
    });
    document.addEventListener('click', function (event) {
        var btn = event.target.closest ? event.target.closest('[data-sl-list-retry]') : null;
        if (!btn || !listretry || listretry.isConnected === false) return;
        btn.closest('[data-sl-list-error]').hidden = true;
        if (typeof listretry === 'function') listretry();
        else if (listretry.tagName === 'FORM') listretry.requestSubmit();
        else listretry.click();
    });
    // Э15 A list drawn anew tells the reader what it shows, and the tab title, now the one of the new view, takes the count of the queues again
    document.addEventListener('htmx:afterSettle', function (event) {
        var box = event.target;
        var said = document.querySelector('[data-sl-said]');
        var line = box && box.matches && box.matches('[data-sl-list]') ? box.querySelector('[data-sl-list-said]') : null;
        var held = quiet;
        var row;
        if (!line) return;
        quiet = '';
        queue.title = '';
        setQueueTitle();
        setListPicked();
        if (held) {
            row = held !== '-' ? box.querySelector('tr[data-sl-row="' + CSS.escape(held) + '"] [data-sl-check-item]') : null;
            if (row) row.focus();
            return;
        }
        if (event.detail && event.detail.requestConfig) setRowsEnter(box);
        if (!said) return;
        said.textContent = '';
        window.setTimeout(function () { said.textContent = line.getAttribute('data-sl-list-said') || ''; }, 100);
    });
    // В07.1 The rows a filter, a tab or a page brings enter one after another; a list Back restores only stands
    function setRowsEnter(box) {
        box.querySelectorAll('table[data-sl-density] > tbody > tr').forEach(function (row, i) {
            row.style.setProperty('--sl-d-order', String(i));
            row.setAttribute('data-sl-enter', '');
        });
    }
    // В02.1 The view of a canon list changes the table at once and is stored through op option; a refusal puts the view back
    function setListDensity(btn) {
        var seg = btn.closest('[data-sl-knob="density"]');
        var box = btn.closest('[data-sl-list]');
        var table = box ? box.querySelector('table[data-sl-density]') : null;
        var key = btn.getAttribute('data-sl-density');
        if (!seg || !table) return;
        var was = table.getAttribute('data-sl-density');
        var draw = function (now) {
            table.setAttribute('data-sl-density', now);
            seg.querySelectorAll('[data-sl-density]').forEach(function (cell) {
                var on = cell.getAttribute('data-sl-density') === now;
                cell.classList.toggle('sl-is-active', on);
                cell.setAttribute('aria-pressed', on ? 'true' : 'false');
                cell.tabIndex = on ? 0 : -1;
            });
        };
        if (key === was) return;
        draw(key);
        window.setOptionSend('density', key).catch(function () { draw(was); });
    }
    document.addEventListener('click', function (event) {
        var btn = event.target.closest ? event.target.closest('.sl-seg-view [data-sl-density]') : null;
        if (btn) setListDensity(btn);
    });
    // В02.1 The views are one stop of Tab and the arrows walk them; caught first, so the arrows do not turn the page as well
    document.addEventListener('keydown', function (event) {
        var btn = event.target.closest ? event.target.closest('.sl-seg-view [data-sl-density]') : null;
        var step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];
        if (!btn || !step) return;
        event.preventDefault();
        var list = Array.prototype.slice.call(btn.parentNode.querySelectorAll('[data-sl-density]'));
        var next = list[(list.indexOf(btn) + step + list.length) % list.length];
        setListDensity(next);
        next.focus();
    }, true);
    // The form of a constant split by "|" that a number asks for, one, few or many, by the plural rule of the page language
    function getPluralWord(num, forms) {
        var lang = document.documentElement.lang || 'en';
        var list = forms.split('|');
        var kind = new Intl.PluralRules(lang).select(num);
        var at = kind === 'one' ? 0 : (kind === 'few' ? 1 : list.length - 1);
        return list[Math.min(at, list.length - 1)].replace('%s', num.toLocaleString(lang));
    }
    // The row the reader is on: the first one whose bottom is below the sticky head of the table, with its place in the window
    function getRowsAnchor(box) {
        var head = box.querySelector('table[data-sl-density] > thead');
        var edge = head ? Math.max(head.getBoundingClientRect().bottom, 0) : 0;
        var rows = Array.prototype.slice.call(box.querySelectorAll('table[data-sl-density] > tbody > tr'));
        var at = rows.findIndex(function (row) { return row.getBoundingClientRect().bottom > edge; });
        return at < 0 ? null : { at: at, top: rows[at].getBoundingClientRect().top };
    }
    // A stored number brings the list again on the page of the row the reader is on, floor(row / number) + 1, with that row where it stood in the window
    function setRowsPick(host, pick, back) {
        var box = host.closest('[data-sl-list]');
        var was = host.getAttribute('data-sl-rows');
        var base = parseInt(host.getAttribute('data-sl-rows-base'), 10);
        var limit = parseInt(host.getAttribute('data-sl-rows-limit'), 10);
        var mark = box ? getRowsAnchor(box) : null;
        var pos = (parseInt(host.getAttribute('data-sl-rows-page'), 10) - 1) * limit + (mark ? mark.at : 0);
        var size = (pick === 'all') ? Infinity : (parseInt(pick, 10) || base);
        var url = host.getAttribute('data-sl-rows-href') + 'num=' + (Math.floor(pos / size) + 1);
        var low = !!host.closest('.sl-list-bottom');
        var keyed = host.contains(document.activeElement) && !host.matches(':hover');
        var text = (pick === 'all') ? host.getAttribute('data-sl-rows-all')
            : getPluralWord(parseInt(pick, 10) || base, host.getAttribute(pick ? 'data-sl-rows-new' : 'data-sl-rows-def'));
        if (!box || pick === was) return;
        window.setOptionSend('rows', pick).then(function () {
            var settle = function (event) {
                var now = event.target;
                var pager, rows, first, row;
                if (!now || !now.matches || !now.matches('[data-sl-list]')) return;
                document.removeEventListener('htmx:afterSettle', settle);
                history.replaceState(history.state, '', url);
                pager = now.querySelector((low ? '.sl-list-bottom' : '.sl-list-head') + ' .sl-pager-rows') || now.querySelector('.sl-pager-rows');
                rows = now.querySelectorAll('table[data-sl-density] > tbody > tr');
                first = pager ? (parseInt(pager.getAttribute('data-sl-rows-page'), 10) - 1) * parseInt(pager.getAttribute('data-sl-rows-limit'), 10) : 0;
                row = mark ? rows[pos - first] : null;
                if (row) window.scrollBy({ top: row.getBoundingClientRect().top - mark.top, behavior: 'instant' });
                if (keyed && pager) pager.querySelector('.sl-pager-rows-btn').focus();
                if (!back) window.setToast(text, { undo: function () {
                    var held = document.querySelector('[data-sl-list] .sl-pager-rows');
                    if (held) setRowsPick(held, was, true);
                } });
            };
            var done = function (event) {
                if (!event.detail || event.detail.elt !== box) return;
                document.removeEventListener('htmx:afterRequest', done);
                if (event.detail.successful) return;
                document.removeEventListener('htmx:afterSettle', settle);
                listretry = function () { setRowsPick(host, pick, back); };
            };
            quiet = '-';
            document.addEventListener('htmx:afterSettle', settle);
            document.addEventListener('htmx:afterRequest', done);
            window.htmx.ajax('GET', url, { source: box, target: box, swap: 'outerHTML show:none', headers: { 'X-SL-Part': '1' } });
        }).catch(function () {});
    }
    // The own number is held to 5…LIST_MAX and applied by Enter or the tick; an empty or unreadable field changes nothing
    function setRowsOwn(field) {
        var num = parseInt(field.value, 10);
        var host = field.closest('.sl-pager-rows');
        if (!host || isNaN(num)) {
            field.value = field.defaultValue;
            return;
        }
        setRowsPick(host, String(Math.max(5, Math.min(parseInt(field.max, 10), num))));
    }
    document.addEventListener('click', function (event) {
        var btn = event.target.closest ? event.target.closest('.sl-pager-rows [data-sl-rows-set], .sl-pager-rows [data-sl-rows-go]') : null;
        if (!btn || btn.disabled) return;
        if (btn.hasAttribute('data-sl-rows-go')) setRowsOwn(btn.closest('.sl-pager-rows').querySelector('[data-sl-rows-own]'));
        else setRowsPick(btn.closest('.sl-pager-rows'), btn.getAttribute('data-sl-rows-set'));
    });
    // The tick applies without taking the focus from the field, so the field does not forget the number on its way to the tick
    document.addEventListener('mousedown', function (event) {
        if (event.target.closest && event.target.closest('[data-sl-rows-go]')) event.preventDefault();
    });
    // A field left without Enter or the tick returns to the number in force: a click beside it applies nothing
    document.addEventListener('focusout', function (event) {
        if (event.target.matches && event.target.matches('[data-sl-rows-own]')) event.target.value = event.target.defaultValue;
    });
    // The arrows walk the panel, but in the field left and right move the caret and on the chip they stay the keys of the pager; Escape is the float's own
    document.addEventListener('keydown', function (event) {
        var host = event.target.closest ? event.target.closest('.sl-pager-rows') : null;
        var step = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[event.key];
        var vert = event.key === 'ArrowDown' || event.key === 'ArrowUp';
        var list, at, here, next;
        if (!host) return;
        if (event.key === 'Enter' && event.target.matches('[data-sl-rows-own]')) {
            event.preventDefault();
            setRowsOwn(event.target);
            return;
        }
        if (!step && event.key !== 'Home' && event.key !== 'End') return;
        if (event.target.matches('input') && !vert) return;
        list = Array.prototype.slice.call(host.querySelectorAll('.sl-float-panel button:not(:disabled), .sl-float-panel input'));
        at = list.indexOf(document.activeElement);
        if (at < 0 && !vert) return;
        if (at < 0 && host.hasAttribute('data-sl-float-shut')) host.dispatchEvent(new MouseEvent('mouseenter'));
        here = Math.max(0, list.findIndex(function (one) { return one.closest('.sl-pager-rows-item').classList.contains('sl-is-active'); }));
        next = at < 0 ? here : event.key === 'Home' ? 0 : event.key === 'End' ? list.length - 1 : (at + step + list.length) % list.length;
        event.preventDefault();
        if (list[next]) list[next].focus();
    }, true);
    var later = [];
    var sweep = { all: false, on: false, hold: false, step: null };
    var quiet = '';
    // The toast host carries the words of the canon actions: what an action did, the name of a turn and the state of a lamp
    function getLaterWord(kind, key) {
        var host = document.querySelector('.sl-toast-host');
        return host ? host.getAttribute('data-sl-' + kind + '-' + key) || '' : '';
    }
    // The word of a deferred form: fixed on a row or a section, read from the chosen action on a bulk form ("gone on=on off=off del=del")
    function getLaterKey(form) {
        var pick = form.elements.pick ? form.elements.pick.value : '';
        var hit = form.getAttribute('data-sl-later').split(' ').slice(1).map(function (one) { return one.split('='); }).find(function (one) {
            return one.length === 1 || one[0] === pick;
        });
        return hit ? hit[hit.length - 1] : '';
    }
    // One row by its name, many by their number: "Комментарий 20679 — удалено", "Удалено: 3"
    function getLaterText(name, key, num) {
        var word = getLaterWord('did', key);
        return name ? name + ' — ' + word.toLocaleLowerCase() : word + ': ' + num;
    }
    // The rows a deferred form acts on: its own row, or the picked rows of a bulk form, which owns their boxes through the form attribute
    function getLaterRows(form) {
        var row = form.closest('tr');
        if (row) return [row];
        return Array.prototype.filter.call(form.elements, function (box) {
            return box.type === 'checkbox' && box.checked && !!box.closest('tbody');
        }).map(function (box) { return box.closest('tr'); });
    }
    // Э29 The focus a leaving row held goes to the next row still standing, the last one when none follows
    function setRowFocus(held) {
        var rows = Array.prototype.filter.call(held.parentNode.children, function (one) { return !one.hidden; });
        var next = rows.find(function (one) { return held.compareDocumentPosition(one) & Node.DOCUMENT_POSITION_FOLLOWING; }) || rows[rows.length - 1];
        var box = next ? next.querySelector('[data-sl-check-item]') || next.querySelector('a, button') : null;
        if (box) box.focus();
    }
    // Э23 Rows that leave go at once, their boxes unpicked and out of every form; the way back puts them where they stood
    function setRowsGone(rows, gone) {
        var held = document.activeElement && document.activeElement.closest ? document.activeElement.closest('tr') : null;
        rows.forEach(function (row) {
            var box = row.querySelector('[data-sl-check-item]');
            row.hidden = gone;
            row.removeAttribute('data-sl-cursor');
            if (!box) return;
            box.checked = false;
            box.disabled = gone;
        });
        setListPicked();
        if (gone && held && rows.indexOf(held) > -1) setRowFocus(held);
    }
    // Н6 Г3 A section turned off or on shows it at once: its lamp, the dimmed card or row, the titles, and its form set for the way back
    function setItemFlip(form) {
        var item = form.closest('[data-sl-menu-item]');
        var link = item.querySelector('a');
        var name = item.querySelector('.sl-menu-name').textContent.trim();
        var off = form.elements.act.value === '0';
        var btn = document.querySelector('[form="' + form.getAttribute('id') + '"]');
        form.elements.act.value = off ? '1' : '0';
        form.setAttribute('data-sl-later', 'flip ' + (off ? 'on' : 'off'));
        if (item.classList.contains('sl-mcard')) item.classList.toggle('sl-mcard-off', off);
        else item.classList.toggle('sl-dimmed', off);
        item.querySelectorAll('.sl-led').forEach(function (led) {
            led.classList.toggle('sl-led-ok', !off);
            led.classList.toggle('sl-led-off', off);
            led.title = getLaterWord('led', off ? 'off' : 'on');
        });
        if (btn) btn.title = getLaterWord('turn', off ? 'on' : 'off');
        link.title = off ? name + ' - ' + getLaterWord('led', 'off') : name;
    }
    // Э22 A lamp in a row that stays turns at once: its mark, its pressed state, its title and the value its form sends next ("lamp act")
    function setLampFlip(form) {
        var btn = document.querySelector('[form="' + form.getAttribute('id') + '"]');
        var field = form.elements[form.getAttribute('data-sl-later').split(' ')[1]];
        var on = btn.getAttribute('aria-pressed') !== 'true';
        var mark = btn.querySelector('span');
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.title = getLaterWord('turn', on ? 'off' : 'on');
        mark.classList.toggle('sl-status-active', on);
        mark.classList.toggle('sl-status-inactive', !on);
        field.value = field.value === '1' ? '0' : '1';
    }
    // Э22 The status the foot picked for rows whose lamp stays: the lamp forms of those rows still standing the other way, or null when a row has no lamp
    function getLampForms(rows, key) {
        var forms = rows.map(function (row) { return row.querySelector('form[data-sl-later^="lamp "]'); });
        if ((key !== 'on' && key !== 'off') || !rows.length || forms.indexOf(null) > -1) return null;
        return forms.filter(function (form) {
            return (document.querySelector('[form="' + form.getAttribute('id') + '"]').getAttribute('aria-pressed') === 'true') !== (key === 'on');
        });
    }
    // Send one deferred action now: by fetch while the page stays, by a beacon when it goes; a refusal brings its rows back and says so
    function setLaterSend(job, beacon) {
        var at = later.indexOf(job);
        if (at < 0) return Promise.resolve();
        window.clearTimeout(job.timer);
        later.splice(at, 1);
        job.body.set('later', '1');
        if (beacon) {
            navigator.sendBeacon(job.form.action, job.body);
            return Promise.resolve();
        }
        return fetch(job.form.action, { method: 'POST', body: job.body, credentials: 'same-origin' })
            .then(function (res) { if (res.status !== 204) throw new Error('refused'); })
            .catch(function () {
                job.back();
                window.setToast(getLaterText(job.name, 'fail', job.num), { warn: true });
            });
    }
    // Once nothing waits any more a canon list is drawn again in place and quietly, so its pager and counts say what the server holds now
    function setLaterDone() {
        var box = document.querySelector('[data-sl-list]');
        var held = document.activeElement && document.activeElement.closest ? document.activeElement.closest('tr[data-sl-row]') : null;
        if (later.length || sweep.on || !box || !window.htmx || box.querySelector('tbody [data-sl-check-item]:checked')) return;
        quiet = held ? held.getAttribute('data-sl-row') : '-';
        window.htmx.ajax('GET', window.location.href, { source: box, target: box, swap: 'outerHTML' });
    }
    // The action picked in the foot of a canon list sends its own group of fields alone: its op, its direction, the filter and the token
    function setPickGroup(form) {
        var pick = form.elements.pick;
        if (!pick) return;
        form.querySelectorAll('fieldset[data-sl-pick]').forEach(function (set) { set.disabled = set.getAttribute('data-sl-pick') !== pick.value; });
    }
    document.addEventListener('change', function (event) {
        if (event.target.name === 'pick' && event.target.form) setPickGroup(event.target.form);
    });
    // Э22 Э23 A form marked data-sl-later acts at once and leaves for the server after ten seconds, which Undo can still take back
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.matches || !form.matches('form[data-sl-later]') || !window.setToast) return;
        setPickGroup(form);
        var kind = form.getAttribute('data-sl-later').split(' ')[0];
        var key = getLaterKey(form);
        var rows = kind === 'gone' ? getLaterRows(form) : [];
        var lamps = (kind === 'gone' && !form.closest('tr')) ? getLampForms(rows, key) : null;
        var job = { form: form, body: new FormData(form), name: '', num: rows.length, timer: 0, back: null };
        var held = later.find(function (one) { return one.form === form; });
        event.preventDefault();
        if (kind === 'gone' && !rows.length) return;
        if (sweep.all && !form.closest('tr')) return setSweepLater(form, key, rows);
        if (kind === 'lamp' && held) {
            window.clearTimeout(held.timer);
            later.splice(later.indexOf(held), 1);
            held.back();
            return;
        }
        if (kind === 'flip') {
            job.name = form.closest('[data-sl-menu-item]').querySelector('.sl-menu-name').textContent.trim();
            setItemFlip(form);
            job.back = function () { setItemFlip(form); };
        } else if (kind === 'lamp') {
            key = document.querySelector('[form="' + form.getAttribute('id') + '"]').getAttribute('aria-pressed') === 'true' ? 'off' : 'on';
            job.name = form.closest('tr').getAttribute('data-sl-row') || '';
            setLampFlip(form);
            job.back = function () { setLampFlip(form); };
        } else if (lamps) {
            job.name = rows.length === 1 ? rows[0].getAttribute('data-sl-row') || '' : '';
            lamps.forEach(setLampFlip);
            job.back = function () { lamps.forEach(setLampFlip); };
        } else {
            job.name = rows.length === 1 ? rows[0].getAttribute('data-sl-row') || '' : '';
            setRowsGone(rows, true);
            job.back = function () { setRowsGone(rows, false); };
        }
        later.push(job);
        job.timer = window.setTimeout(function () {
            setLaterSend(job, false).then(function () {
                setLaterDone();
                setQueueFetch(true);
            });
        }, 10000);
        window.setToast(getLaterText(job.name, key, job.num), { undo: function () {
            if (later.indexOf(job) < 0) return;
            window.clearTimeout(job.timer);
            later.splice(later.indexOf(job), 1);
            job.back();
            setLaterDone();
        } });
    });
    // A list about to be drawn anew takes the waiting actions along first, so it never shows a row the server has not lost yet
    document.addEventListener('htmx:confirm', function (event) {
        if (!getListBox(event) || !later.length) return;
        event.preventDefault();
        document.querySelectorAll('.sl-toast-host [data-sl-undo]').forEach(function (btn) { btn.hidden = true; });
        Promise.all(later.slice().map(function (job) { return setLaterSend(job, false); })).then(function () { event.detail.issueRequest(true); });
    });
    window.addEventListener('pagehide', function () {
        later.slice().forEach(function (job) { setLaterSend(job, true); });
    });
    // Э24 Э25 The pick of a canon list: the head box whole or partial, the count beside the action, OK only with something picked
    function setListPicked() {
        var list = document.querySelector('[data-sl-list]');
        var items, num, head, count, go;
        if (!list) return;
        items = Array.prototype.filter.call(list.querySelectorAll('tbody [data-sl-check-item]'), function (one) { return !one.disabled; });
        num = items.filter(function (one) { return one.checked; }).length;
        head = list.querySelector('thead [data-sl-check-all] input');
        count = list.querySelector('[data-sl-picked]');
        go = list.querySelector('[data-sl-pick-go]');
        if (head) {
            head.checked = num > 0 && num === items.length;
            head.indeterminate = num > 0 && num < items.length;
        }
        if (num < items.length || !num) sweep.all = false;
        if (count) count.textContent = count.getAttribute('data-sl-picked').replace('%s', getSweepNum(sweep.all ? getSweepTotal(list) : num));
        if (go) go.disabled = num === 0;
        setAllPages(list, num > 0 && num === items.length, num);
    }
    // A number as the page writes it, with the grouping of its language
    function getSweepNum(num) {
        return Number(num).toLocaleString(document.documentElement.lang || undefined);
    }
    // The size of the whole list the offer of all pages stands for
    function getSweepTotal(list) {
        var note = list.querySelector('[data-sl-allpages]');
        return note ? Number(note.getAttribute('data-sl-allpages-num')) || 0 : 0;
    }
    // Э26 A whole page picked offers the whole list; taking it says so and offers to give it back
    function setAllPages(list, whole, num) {
        var note = list.querySelector('[data-sl-allpages]');
        var text;
        if (!note || sweep.on) return;
        text = note.querySelector('[data-sl-allpages-text]');
        note.hidden = !whole;
        text.textContent = sweep.all ? text.getAttribute('data-sl-all-on').replace('%s', getSweepNum(getSweepTotal(list)))
            : text.getAttribute('data-sl-page-all').replace('%s', getSweepNum(num));
        note.querySelector('[data-sl-allpages-go]').hidden = sweep.all;
        note.querySelector('[data-sl-allpages-off]').hidden = !sweep.all;
    }
    // Э26 The whole list goes like a page does: its rows leave at once, Undo stands for ten seconds, and only then the portions start
    function setSweepLater(form, key, rows) {
        var list = form.closest('[data-sl-list]') || document.querySelector('[data-sl-list]');
        var total = getSweepTotal(list);
        var timer;
        setRowsGone(rows, true);
        sweep.on = true;
        timer = window.setTimeout(function () { setSweepRun(list, form, key, total); }, 10000);
        window.setToast(getLaterText('', key, getSweepNum(total)), { undo: function () {
            if (!sweep.on || sweep.step) return;
            window.clearTimeout(timer);
            sweep.on = false;
            sweep.all = false;
            setRowsGone(rows, false);
        } });
    }
    // Э26 The portions of the picked foot action follow one another behind the cursor the server hands back, with a progress line and a pause; a closed tab simply stops them
    function setSweepRun(list, form, key, total) {
        var note = list.querySelector('[data-sl-allpages]');
        var text = note.querySelector('[data-sl-allpages-text]');
        var hold = note.querySelector('[data-sl-sweep-hold]');
        var meter = note.querySelector('[data-sl-sweep-meter]');
        var body = new FormData(form);
        var seen = 0;
        var end = function (fail) {
            sweep.on = false;
            sweep.all = false;
            sweep.step = null;
            sweep.hold = false;
            hold.hidden = true;
            meter.hidden = true;
            if (fail) window.setToast(getLaterText('', 'fail', getSweepNum(total - seen)), { warn: true });
            setLaterDone();
            setQueueFetch(true);
        };
        body.delete('id[]');
        body.set('later', '1');
        body.set('after', '0');
        body.set('upto', note.getAttribute('data-sl-allpages-upto') || '0');
        note.hidden = false;
        note.querySelector('[data-sl-allpages-go]').hidden = true;
        note.querySelector('[data-sl-allpages-off]').hidden = true;
        hold.hidden = false;
        setSweepHold(hold);
        meter.style.setProperty('--sl-d-run', '0');
        meter.hidden = false;
        sweep.step = function () {
            if (sweep.hold) return;
            fetch(form.action, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (res) {
                    if (!res.ok) throw new Error('refused');
                    return res.json();
                })
                .then(function (data) {
                    seen += data.done + data.fail;
                    body.set('after', String(data.after));
                    text.textContent = text.getAttribute('data-sl-swept').replace('%s', getSweepNum(seen)).replace('%s', getSweepNum(total));
                    meter.style.setProperty('--sl-d-run', String(total ? Math.min(1, seen / total) : 1));
                    if (data.more) sweep.step();
                    else end(data.fail > 0);
                })
                .catch(function () { end(true); });
        };
        sweep.step();
    }
    // Э26 The pause key says what a press does next: its word, its icon and its pressed state follow the hold of the run
    function setSweepHold(btn) {
        btn.querySelector('span').textContent = btn.getAttribute(sweep.hold ? 'data-sl-resume' : 'data-sl-pause');
        btn.setAttribute('aria-pressed', String(sweep.hold));
        btn.querySelectorAll('i').forEach(function (one, i) { one.hidden = (i === 1) !== sweep.hold; });
    }
    document.addEventListener('click', function (event) {
        var btn = event.target.closest ? event.target.closest('[data-sl-allpages-go], [data-sl-allpages-off], [data-sl-sweep-hold]') : null;
        var list = btn ? btn.closest('[data-sl-list]') : null;
        if (!list) return;
        if (btn.hasAttribute('data-sl-sweep-hold')) {
            sweep.hold = !sweep.hold;
            setSweepHold(btn);
            if (!sweep.hold && sweep.step) sweep.step();
            return;
        }
        sweep.all = btn.hasAttribute('data-sl-allpages-go');
        if (!sweep.all) list.querySelectorAll('tbody [data-sl-check-item]').forEach(function (box) { box.checked = false; });
        setListPicked();
    });
    // Counted once every listener of the change has run, the head box of slaed.js among them
    document.addEventListener('change', function (event) {
        if (event.target.type === 'checkbox' && event.target.closest && event.target.closest('[data-sl-list] table')) window.setTimeout(setListPicked, 0);
    });
    // Э27 J and K walk the rows of a canon list, X picks the row in hand and E opens its edit; the row in hand is outlined and holds the focus
    function setListKeys(event) {
        var list = document.querySelector('[data-sl-list]');
        var rows, at, row, hit;
        if (!list || ['KeyJ', 'KeyK', 'KeyX', 'KeyE'].indexOf(event.code) < 0) return false;
        rows = Array.prototype.filter.call(list.querySelectorAll('tbody > tr'), function (one) { return !one.hidden; });
        at = rows.findIndex(function (one) { return one.hasAttribute('data-sl-cursor'); });
        row = rows[at] || null;
        if (event.code === 'KeyJ' || event.code === 'KeyK') {
            if (!rows.length) return true;
            rows.forEach(function (one) { one.removeAttribute('data-sl-cursor'); });
            row = rows[Math.max(0, Math.min(rows.length - 1, at + (event.code === 'KeyJ' ? 1 : -1)))];
            row.setAttribute('data-sl-cursor', '');
            row.tabIndex = -1;
            row.focus();
            return true;
        }
        hit = row ? row.querySelector(event.code === 'KeyX' ? '[data-sl-check-item]' : '[data-sl-edit]') : null;
        if (hit) hit.click();
        return true;
    }
    var sess = { end: 0, max: 0, warn: 0 };
    // The server names the seconds the session has left: on the page, after "Extend" and in every answer of the poll, which an action in another tab moves
    function setSessionLeft(left) {
        if (!sess.max || typeof left !== 'number') return;
        sess.end = Date.now() + left * 1000;
        setSessionTick();
    }
    // Э14 М14 Every place of the rest counts down, the admin card of the menu too; the end window opens before the limit and at zero the page leaves for the login form
    function setSessionTick() {
        var box = document.getElementById('sl-session');
        var left = Math.max(0, Math.round((sess.end - Date.now()) / 1000));
        if (left === 0) {
            document.querySelectorAll('[data-sl-leave]').forEach(function (form) { form.removeAttribute('data-sl-leave'); });
            return window.location.assign(window.location.href.split('#')[0]);
        }
        document.querySelectorAll('[data-sl-sess-left]').forEach(function (node) { node.textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0'); });
        if (left > sess.warn) {
            if (box.open) window.setWindowClose(box);
            return;
        }
        if (box.open) return;
        window.setWindowOpen(box);
        window.setSoundPlay('warn');
    }
    // The count starts from the limit the page was rendered with, as rendering it was an action
    function setSessionWatch() {
        var box = document.getElementById('sl-session');
        sess.max = box ? Number(box.getAttribute('data-sl-sess')) || 0 : 0;
        if (!sess.max) return;
        sess.warn = Math.min(120, Math.floor(sess.max / 2));
        setSessionLeft(sess.max);
        window.setInterval(setSessionTick, 1000);
    }
    // "Extend" posts op keep without leaving the page; the answer 204 restarts the count from the full limit
    document.addEventListener('submit', function (event) {
        var form = event.target.closest ? event.target.closest('form[data-sl-sess-keep]') : null;
        if (!form) return;
        event.preventDefault();
        fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
            .then(function (res) { if (res.ok) setSessionLeft(sess.max); })
            .catch(function () {});
    });
    var saver = { kit: null, ctl: null, run: 0, last: Date.now(), asleep: false, grace: 0 };
    // Д12 The rest of the hand before the screensaver in ms, kept in minutes on this device; 0 keeps it off, one minute unless chosen
    function getSaverWait() {
        var kept = window.getToggleState('saver', '');
        return (kept === null ? 1 : Number(kept) || 0) * 60000;
    }
    // Д11 While the screensaver is on, the badge and the quiet ground stand and the scene waits for the rest of the hand; off — none, still — the badge alone
    function setSaver() {
        var root = document.documentElement;
        var run = ++saver.run;
        var kit;
        if (saver.kit) saver.kit.reset();
        saver.kit = null;
        saver.ctl = null;
        root.classList.remove('sl-phx-lit', 'sl-phx-crown', 'sl-phx-glint');
        root.toggleAttribute('data-sl-phx', getSaverWait() > 0);
        if (!getSaverWait()) return;
        root.classList.add('sl-phx-lit', 'sl-phx-crown');
        if (window.isMotionStill() || !window.getPhoenixKit) return;
        kit = window.getPhoenixKit();
        if (!kit) return;
        saver.kit = kit;
        window.getPhoenixScene(kit).then(function (ctl) {
            if (run === saver.run) saver.ctl = ctl;
        }, function () {
            if (run === saver.run) kit.reset();
        });
    }
    // Д11 A move of the hand starts the rest over and fades a running scene out; the quiet stays as the ground
    function setSaverTouch() {
        saver.last = Date.now();
        if (!saver.ctl || Date.now() <= saver.grace) return;
        saver.asleep = false;
        saver.ctl.wake();
    }
    // Д11 After the rest the scene starts and runs in a circle while the hand rests, never under a modal window or on a hidden tab
    function setSaverTick() {
        var ctl = saver.ctl;
        var wait = getSaverWait();
        if (!ctl || !wait || ctl.busy || document.hidden || document.querySelector('dialog:modal')) return;
        if (saver.asleep ? !ctl.ended : Date.now() - saver.last < wait) return;
        saver.asleep = true;
        saver.grace = Date.now() + 1000;
        ctl.start();
    }
    // Д12 The row of Options shows what this device keeps
    function setSaverView() {
        document.querySelectorAll('#sl-set-saver').forEach(function (pick) { pick.value = String(getSaverWait() / 60000); });
    }
    // Д11 The screensaver lives where the layout of the panel carries its badge; it starts once phoenix.js, deferred after this file, has run
    function setSaverWatch() {
        if (!document.querySelector('.sl-phx-badge')) return;
        ['pointermove', 'pointerdown', 'keydown', 'wheel'].forEach(function (name) {
            document.addEventListener(name, setSaverTouch, { passive: true });
        });
        window.addEventListener('scroll', setSaverTouch, { passive: true });
        window.setInterval(setSaverTick, 250);
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            if ((document.documentElement.getAttribute('data-theme') || 'auto') === 'auto') setSaver();
        });
        window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', setSaver);
        window.addEventListener('storage', function (event) {
            if (event.key !== null && event.key !== 'slaed-toggle:saver') return;
            setSaverView();
            setSaver();
        });
        setSaverView();
        if (document.readyState === 'complete') setSaver();
        else window.addEventListener('load', setSaver, { once: true });
    }
    // One answer of the settings window shows on the page itself: the colour mode and the motion are attributes of the root, the editor waits for the next screen
    function setOptionView(axis, value) {
        var root = document.documentElement;
        if (axis === 'mode') root.setAttribute('data-theme', value);
        if (axis === 'still') root.toggleAttribute('data-sl-still', value === '1');
        if (axis === 'mode' || axis === 'still') setSaver();
        if (axis !== 'dock-off') return;
        root.toggleAttribute('data-sl-dock-off', value === '1');
        window.setDockPlace();
    }
    // A settings row applies its answer at once and stores it silently through setOptionSend of dock.js, a refusal puts it back; edge, sound and saver stay in the browser
    document.addEventListener('change', function (event) {
        var pick = event.target;
        var form = pick && pick.tagName === 'SELECT' ? pick.closest('form[data-sl-option]') : null;
        if (!form) return;
        var axis = form.getAttribute('data-sl-option');
        if (axis === 'language') return form.requestSubmit ? form.requestSubmit() : form.submit();
        if (axis === 'dock-side') return window.setDockSide(pick.value);
        if (axis === 'sound') return window.setSoundOn(pick.value === '1');
        if (axis === 'saver') {
            window.setToggleState('saver', '', pick.value);
            return setSaver();
        }
        var was = pick.querySelector('option[selected]');
        setOptionView(axis, pick.value);
        window.setOptionSend(axis, pick.value)
            .then(function () {
                if (was) was.removeAttribute('selected');
                pick.options[pick.selectedIndex].setAttribute('selected', '');
            })
            .catch(function () {
                if (!was) return;
                pick.value = was.value;
                setOptionView(axis, was.value);
            });
    });
    // А8 While the rail stands, a block head only stands for its icon and opens its panel beside it; it never folds the block away
    function setRailGuard(event) {
        if (!isMenuRail() || !event.target.closest || !event.target.closest('#sl-menu [data-sl-toggle-control]')) return;
        if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        event.stopPropagation();
    }
    document.addEventListener('click', setRailGuard, true);
    document.addEventListener('keydown', setRailGuard, true);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            addFileStep();
            setBeamTurns();
            setSetupRun();
            addRankForm();
            addFormCanon();
            setQueuePoll();
            setCockpitPoll();
            setSessionWatch();
            setMenuFind();
            setListPicked();
            setSaverWatch();
        });
    } else {
        addFileStep();
        setBeamTurns();
        setSetupRun();
        addRankForm();
        addFormCanon();
        setQueuePoll();
        setCockpitPoll();
        setSessionWatch();
        setMenuFind();
        setListPicked();
        setSaverWatch();
    }
})();
