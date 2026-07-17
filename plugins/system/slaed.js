(function () {
    // Lightbox: image links open in the shared project modal frame; the dialog is created once on first use
    function setLightbox() {
        var root = null;
        var image = null;
        var label = null;

        function getLightbox() {
            if (root) return;
            root = document.createElement('dialog');
            root.className = 'sl-modal sl-modal-wide';
            root.innerHTML = '<div class="sl-modal-body">'
                + '<div class="sl-font sl-modal-title"><i class="bi bi-image" aria-hidden="true"></i> <span></span></div>'
                + '<button type="button" class="sl-but-mini sl-modal-close" data-sl-close><i class="bi bi-x-lg" aria-hidden="true"></i></button>'
                + '<img class="sl-modal-image" alt=""></div>';
            document.body.appendChild(root);
            image = root.querySelector('.sl-modal-image');
            label = root.querySelector('.sl-modal-title > span');
            root.addEventListener('close', function () {
                image.removeAttribute('src');
            });
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('a.screens, a.site-link');
            if (!trigger) return;
            var href = trigger.getAttribute('href') || '';
            if (!/\.(?:bmp|gif|jpe?g|png|webp|svg)(?:[?#].*)?$/i.test(href)) return;
            event.preventDefault();
            getLightbox();
            image.src = href;
            image.alt = trigger.getAttribute('title') || '';
            label.textContent = trigger.getAttribute('title') || href.split('/').pop();
            root.showModal();
        });
    }

    function getTableNumber(text) {
        var item = (text || '').replace(/<[^>]*>/g, '').replace(/\s+/g, '').replace(/,/g, '.');
        var val = item.match(/[-+]?\d+(?:\.\d+)?/);
        return val ? parseFloat(val[0]) : 0;
    }

    function getTableDate(text) {
        var item = (text || '').trim();
        var val = item.match(/^(\d{2})\.(\d{2})\.(\d{4})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$/);
        if (!val) return 0;
        var sec = val[6] ? parseInt(val[6], 10) : 0;
        return new Date(
            parseInt(val[3], 10),
            parseInt(val[2], 10) - 1,
            parseInt(val[1], 10),
            parseInt(val[4] || '0', 10),
            parseInt(val[5] || '0', 10),
            sec
        ).getTime();
    }

    function setTableSortState(table) {
        if (!table || !table.tHead) return;
        var head = table.tHead.rows;
        for (var i = 0; i < head.length; i++) {
            for (var j = 0; j < head[i].cells.length; j++) {
                var cell = head[i].cells[j];
                cell.classList.remove('sl-sort', 'sl-sort-asc', 'sl-sort-desc');
                if (cell.getAttribute('data-sort-method') === 'none') continue;
                cell.classList.add('sl-sort');
                if (cell.getAttribute('aria-sort') === 'ascending') {
                    cell.classList.remove('sl-sort');
                    cell.classList.add('sl-sort-asc');
                } else if (cell.getAttribute('aria-sort') === 'descending') {
                    cell.classList.remove('sl-sort');
                    cell.classList.add('sl-sort-desc');
                }
            }
        }
    }

    function setTableSort(node) {
        if (typeof window.Tablesort === 'undefined') return;
        if (typeof window.Tablesort.extend === 'function' && !window.Tablesort.__slaedExtensionsReady) {
            window.Tablesort.extend('slaedNumber', function (item) {
                return /^[-+]?[\d\s.,]+(?:\s*[%A-Za-zА-Яа-я]+)?$/.test(item.trim()) && /\d/.test(item);
            }, function (a, b) {
                return getTableNumber(b) - getTableNumber(a);
            });

            window.Tablesort.extend('slaedDate', function (item) {
                return /^\d{2}\.\d{2}\.\d{4}(?:\s+\d{2}:\d{2}(?::\d{2})?)?$/.test(item.trim());
            }, function (a, b) {
                return getTableDate(b) - getTableDate(a);
            });

            window.Tablesort.__slaedExtensionsReady = true;
        }
        var list = [];
        var root = node && node.nodeType ? node : document;
        var tables = root.querySelectorAll ? root.querySelectorAll('[data-sl-table-sort]') : [];
        if (root.nodeType === 1 && root.hasAttribute && root.hasAttribute('data-sl-table-sort')) {
            list.push(root);
        }
        for (var i = 0; i < tables.length; i++) {
            list.push(tables[i]);
        }
        for (var k = 0; k < list.length; k++) {
            var table = list[k];
            if (!table.tHead || !table.tBodies.length) continue;
            if (table.getAttribute('data-sort-ready') !== '1') {
                new window.Tablesort(table);
                table.addEventListener('afterSort', function (event) {
                    setTableSortState(event.currentTarget);
                });
                table.setAttribute('data-sort-ready', '1');
            }
            setTableSortState(table);
        }
    }

    function getStorageKey(name, scoped) {
        return scoped ? 'slaed-toggle:' + window.location.pathname + window.location.search + ':' + name : 'slaed-toggle:' + name;
    }

    function getToggleState(name, scoped) {
        try {
            return window.localStorage.getItem(getStorageKey(name, scoped));
        } catch (err) {
            return null;
        }
    }

    function setToggleState(name, scoped, value) {
        try {
            window.localStorage.setItem(getStorageKey(name, scoped), value);
        } catch (err) {
        }
    }

    function getToggleControls(id) {
        var controls = document.querySelectorAll('[data-sl-toggle-control]');
        var matches = [];
        for (var i = 0; i < controls.length; i++) {
            if (controls[i].getAttribute('data-sl-toggle-control') === id) matches.push(controls[i]);
        }
        return matches;
    }

    function setToggleControls(id, isOpen) {
        var controls = getToggleControls(id);
        for (var i = 0; i < controls.length; i++) {
            controls[i].setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            controls[i].classList.toggle('sl-is-open', isOpen);
            controls[i].classList.toggle('sl-is-closed', !isOpen);
            if (controls[i].type === 'checkbox') controls[i].checked = isOpen;
        }
    }

    // One source of truth for the display a toggled block opens with: explicit data-sl-toggle-display wins, grid containers open as grid, everything else as block
    function getToggleDisplay(element) {
        return element.getAttribute('data-sl-toggle-display') || ((element.classList.contains('sl-div-item') || element.classList.contains('sl-div-grid')) ? 'grid' : 'block');
    }

    // Float panels bypass the legacy display/slide/puff path: their visibility lives in CSS and placement in placeFloat, so toggling them never jumps
    function setToggleBlockState(element, id, isOpen, effect, duration) {
        element.classList.toggle('sl-is-open', isOpen);
        element.classList.toggle('sl-is-closed', !isOpen);
        setToggleControls(id, isOpen);
        if (element.classList.contains('sl-float-panel')) {
            var floatHost = element.closest('.sl-float');
            if (floatHost) {
                if (isOpen) placeFloat(floatHost); else clearFloatState(floatHost);
            }
            return;
        }
        if (effect === 'slide') {
            setSlideMotion(element, isOpen, duration || 400);
            return;
        }
        if (effect === 'puff') {
            setFadeScale(element, isOpen, duration || 400);
            return;
        }
        element.hidden = !isOpen;
        element.style.display = isOpen ? getToggleDisplay(element) : 'none';
    }

    function setToggleBlock(id, scoped, isOpen, effect, duration) {
        var element = document.getElementById(id);
        if (!element) return;
        var isHidden;
        if (element.classList.contains('sl-float-panel')) {
            var fh = element.closest('.sl-float');
            isHidden = !(fh && fh.classList.contains('sl-is-open'));
        } else {
            isHidden = window.getComputedStyle(element).display === 'none' || element.hidden;
        }
        var nextOpen = typeof isOpen === 'boolean' ? isOpen : isHidden;
        setToggleBlockState(element, id, nextOpen, effect, duration);
        setToggleState(id, scoped, nextOpen ? '1' : '0');
        if (nextOpen) {
            var items = getToggleControls(id);
            for (var num = 0; num < items.length; num++) items[num].dispatchEvent(new Event('sl-toggle-open'));
        }
    }

    function setToggleControl(control) {
        if (control.getAttribute('data-sl-toggle-ready') === '1') return;
        var id = control.getAttribute('data-sl-toggle-control');
        if (!id) return;
        var scoped = control.getAttribute('data-sl-toggle-scope') === 'path';
        var isCheckbox = control.type === 'checkbox';
        control.setAttribute('data-sl-toggle-ready', '1');
        control.setAttribute('aria-controls', id);
        if (!isCheckbox) {
            control.setAttribute('role', 'button');
            control.setAttribute('tabindex', '0');
        }
        var eventName = isCheckbox ? 'change' : 'click';
        control.addEventListener(eventName, function (event) {
            if (!isCheckbox) event.preventDefault();
            var effect = control.getAttribute('data-sl-toggle-effect');
            var duration = parseInt(control.getAttribute('data-sl-toggle-duration') || '400', 10);
            setToggleBlock(id, scoped, isCheckbox ? control.checked : undefined, effect, duration);
        });
        control.addEventListener('keydown', function (event) {
            if (isCheckbox) return;
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            var effect = control.getAttribute('data-sl-toggle-effect');
            var duration = parseInt(control.getAttribute('data-sl-toggle-duration') || '400', 10);
            setToggleBlock(id, scoped, undefined, effect, duration);
        });
    }

    function setSlideMotion(element, show, duration) {
        var anims = element.getAnimations ? element.getAnimations() : [];
        for (var i = 0; i < anims.length; i++) anims[i].cancel();
        if (show) {
            element.hidden = false;
            element.style.display = getToggleDisplay(element);
            element.style.height = '0px';
            element.style.overflow = 'hidden';
            element.offsetHeight;
        } else {
            element.style.overflow = 'hidden';
            element.style.height = element.offsetHeight + 'px';
        }
        var startHeight = show ? 0 : element.offsetHeight;
        var endHeight = show ? element.scrollHeight : 0;
        element.animate([
            { height: startHeight + 'px' },
            { height: endHeight + 'px' }
        ], {
            duration: duration,
            easing: 'linear'
        }).onfinish = function () {
            element.style.overflow = '';
            element.style.height = '';
            if (!show) {
                element.style.display = 'none';
                element.hidden = true;
            }
        };
    }

    function setFadeScale(element, show, duration) {
        if (show) {
            element.hidden = false;
            element.style.display = getToggleDisplay(element);
        }
        element.animate([
            { opacity: show ? 0 : 1, transform: show ? 'scale(0.96)' : 'scale(1)' },
            { opacity: show ? 1 : 0, transform: show ? 'scale(1)' : 'scale(0.96)' }
        ], {
            duration: duration,
            easing: 'ease'
        }).onfinish = function () {
            element.style.opacity = '';
            element.style.transform = '';
            if (!show) {
                element.style.display = 'none';
                element.hidden = true;
            }
        };
    }

    function getJsonData(url) {
        return window.fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            if (!response.ok) throw new Error('Request failed');
            return response.json();
        });
    }

    function setInputValueByClass(className, value) {
        var field = document.querySelector('input.' + className);
        if (field) field.value = value;
    }

    function setImageReplace() {
        var selects = document.querySelectorAll('[data-sl-image-replace]');
        selects.forEach(function (select) {
            var picture = document.getElementById(select.getAttribute('data-sl-image-replace') || '');
            if (!picture) return;
            select.addEventListener('change', function () {
                picture.setAttribute('src', this.value || '');
            });
        });
    }

    function setTableCheckAll() {
        document.addEventListener('click', function (event) {
            var cell = event.target.closest('th[data-sl-check-all]');
            if (!cell || event.target.matches('input[type="checkbox"]')) return;
            var checkbox = cell.querySelector('input[type="checkbox"]');
            if (checkbox) checkbox.click();
        });
        document.addEventListener('change', function (event) {
            var master = event.target;
            if (!master || master.type !== 'checkbox' || !master.closest('[data-sl-check-all]')) return;
            var scope = master.closest('form') || document;
            var items = scope.querySelectorAll('[data-sl-check-item]');
            for (var i = 0; i < items.length; i++) {
                if (items[i] !== master) items[i].checked = !!master.checked;
            }
        });
    }

    function setToggleBlocks() {
        var controls = document.querySelectorAll('[data-sl-toggle-control]');
        for (var i = 0; i < controls.length; i++) setToggleControl(controls[i]);

        var blocks = document.querySelectorAll('[data-sl-toggle]');
        for (var j = 0; j < blocks.length; j++) {
            var id = blocks[j].getAttribute('data-sl-toggle');
            var scoped = blocks[j].getAttribute('data-sl-toggle-scope') === 'path';
            if (!id) continue;
            var state = getToggleState(id, scoped);
            var isOpen = state !== '0';
            if (state === null) {
                var defaultState = blocks[j].getAttribute('data-sl-toggle-default');
                if (defaultState === 'closed') {
                    isOpen = false;
                } else if (defaultState === 'open') {
                    isOpen = true;
                }
                var blockControls = getToggleControls(id);
                for (var k = 0; k < blockControls.length; k++) {
                    if (blockControls[k].type === 'checkbox') {
                        isOpen = blockControls[k].checked;
                        break;
                    }
                }
            }
            setToggleBlockState(blocks[j], id, isOpen);
        }
    }

    function getEditorInsertText(command, value) {
        if (command === 'name') return '[b]' + value + '[/b], ';
        if (command === 'attach') return '[attach=' + value + ' align=left title=title width=500 height=500 rel=rel]\n';
        if (command === 'img') return '[img=left alt=title]' + value + '[/img]\n';
        return value;
    }

    function syncEditorValue(id, editor) {
        var field = document.getElementById(id);
        if (!field || !editor || typeof editor.getMarkdown !== 'function') return;
        field.value = editor.getMarkdown();
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function insertTextareaText(field, text) {
        var start = typeof field.selectionStart === 'number' ? field.selectionStart : field.value.length;
        var end = typeof field.selectionEnd === 'number' ? field.selectionEnd : start;
        field.focus();
        if (typeof field.setRangeText === 'function') {
            field.setRangeText(text, start, end, 'end');
        } else {
            field.value = field.value.slice(0, start) + text + field.value.slice(end);
            field.selectionStart = field.selectionEnd = start + text.length;
        }
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function insertEditorContent(id, command, value, title) {
        var api = window.SlaedToastUi;
        var editor = api && typeof api.getEditor === 'function' ? api.getEditor(id) : null;
        var text = getEditorInsertText(command, value);
        if (editor) {
            editor.focus();
            if (command === 'img' && typeof editor.exec === 'function') {
                editor.exec('addImage', { imageUrl: value, altText: title || 'image' });
            } else if (typeof editor.insertText === 'function') {
                editor.insertText(text);
            } else if (api && typeof api.insertText === 'function') {
                api.insertText(id, text);
            }
            syncEditorValue(id, editor);
            return true;
        }
        var field = document.getElementById(id);
        if (!field || field.tagName !== 'TEXTAREA') return false;
        insertTextareaText(field, text);
        return true;
    }

    function setEditorInsertHandler() {
        if (document.documentElement.getAttribute('data-sl-editor-insert-ready') === '1') return;
        document.documentElement.setAttribute('data-sl-editor-insert-ready', '1');
        document.addEventListener('click', function (event) {
            var control = event.target.closest('[data-sl-editor-insert]');
            if (!control) return;
            var command = control.getAttribute('data-sl-editor-insert') || '';
            var id = control.getAttribute('data-sl-editor-id') || '1';
            var value = control.getAttribute('data-sl-editor-value') || '';
            var title = control.getAttribute('data-sl-editor-title') || control.getAttribute('title') || '';
            if (!command || !id) return;
            event.preventDefault();
            insertEditorContent(id, command, value, title);
        });
    }

    // Speed dial: click on the toggle pins the fan open, any other click closes every open dial;
    // links carrying data-sl-confirm (plain text, escaped by the template) must pass a confirm dialog first
    function setDialToggle() {
        document.addEventListener('click', function (event) {
            var node = event.target;
            if (!node || !node.closest) return;
            var ask = node.closest('[data-sl-confirm]');
            if (ask) {
                var dlg = document.getElementById('sl-confirm');
                if (dlg) {
                    event.preventDefault();
                    dlg.querySelector('[data-sl-confirm-text]').textContent = ask.getAttribute('data-sl-confirm');
                    dlg.slask = ask;
                    dlg.showModal();
                    return;
                }
                if (!window.confirm(ask.getAttribute('data-sl-confirm'))) {
                    event.preventDefault();
                    return;
                }
            }
            var toggle = node.closest('.sl-dial-toggle');
            document.querySelectorAll('.sl-dial.sl-open').forEach(function (dial) {
                if (!toggle || dial !== toggle.parentNode) dial.classList.remove('sl-open');
            });
            if (toggle) toggle.parentNode.classList.toggle('sl-open');
        });
    }

    window.Upper = function (obj, dur) {
        var duration = dur || 200;
        var target = document.scrollingElement || document.documentElement;
        if (obj && obj !== 'html, body') {
            var node = document.querySelector(obj);
            if (node) target = node;
        }
        if (target === document.documentElement || target === document.body || target === document.scrollingElement) {
            window.scrollTo({ top: 0, behavior: duration > 0 ? 'smooth' : 'auto' });
        } else {
            target.scrollTo({ top: 0, behavior: duration > 0 ? 'smooth' : 'auto' });
        }
        return false;
    };

    window.TranslateLang = function (input, output, lang, info, key) {
        var source = document.querySelector('input.' + input);
        var txt = source ? source.value.trim() : '';
        if (!txt) {
            window.alert(info);
            return;
        }

        lang = lang.toLowerCase().trim();
        var parts = lang.split(/[-_|]/);
        if (parts.length !== 2) {
            window.alert('Wrong language format: ' + lang);
            return;
        }

        var from = parts[0].substring(0, 2);
        var to = parts[1].substring(0, 2);
        var url = 'https://api.mymemory.translated.net/get';
        var hasHTML = /<[^>]+>/.test(txt);

        if (!hasHTML) {
            getJsonData(url + '?q=' + encodeURIComponent(txt) + '&langpair=' + encodeURIComponent(from + '|' + to))
                .then(function (res) {
                    var translated = ((res && res.responseData && res.responseData.translatedText) || txt)
                        .replace(/&nbsp;/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();
                    setInputValueByClass(output, translated);
                })
                .catch(function () {
                    window.alert('Translation request failed');
                });
            return;
        }

        var div = document.createElement('div');
        div.innerHTML = txt;
        var nodes = [];

        (function checkScan(node) {
            if (node.nodeType === 3 && node.nodeValue.trim() !== '') {
                nodes.push(node);
                return;
            }
            for (var i = 0; i < node.childNodes.length; i++) {
                checkScan(node.childNodes[i]);
            }
        })(div);

        var index = 0;
        function setProcessNext() {
            if (index >= nodes.length) {
                setInputValueByClass(output, div.innerHTML);
                return;
            }
            var original = nodes[index].nodeValue.trim();
            getJsonData(url + '?q=' + encodeURIComponent(original) + '&langpair=' + encodeURIComponent(from + '|' + to))
                .then(function (res) {
                    if (res && res.responseData && res.responseData.translatedText) {
                        nodes[index].nodeValue = res.responseData.translatedText;
                    }
                })
                .catch(function () {
                })
                .finally(function () {
                    index++;
                    window.setTimeout(setProcessNext, 100);
                });
        }

        setProcessNext();
    };

    var floatEdge = 12;
    var floatGap = 8;
    var floatStates = ['sl-float-left', 'sl-float-right', 'sl-float-up', 'sl-is-open'];

    // Drop only the state classes: the panel keeps its inline fixed position and fades out in place with no jump back to the static CSS spot
    function clearFloatState(node) {
        for (var i = 0; i < floatStates.length; i++) node.classList.remove(floatStates[i]);
    }

    function getFloatPanel(node) {
        return node.querySelector(':scope > .sl-float-panel');
    }

    // Open a float: inline fixed styles beat theme CSS and survive the close fade; park off-screen only on first measure so re-placing never yanks the panel from under the cursor
    function placeFloat(node) {
        var panel = getFloatPanel(node);
        if (!panel) return;

        if (node.slFloatClose) {
            window.clearTimeout(node.slFloatClose);
            node.slFloatClose = null;
        }
        clearFloatState(node);
        node.classList.add('sl-is-open');

        var placed = panel.style.position === 'fixed';
        panel.style.position = 'fixed';
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';
        panel.style.margin = '0';
        panel.style.transform = 'none';
        panel.style.zIndex = '3000';
        if (!placed) {
            panel.style.left = '-9999px';
            panel.style.top = '-9999px';
        }

        window.requestAnimationFrame(function () {
            var rect = panel.getBoundingClientRect();
            var host = node.getBoundingClientRect();
            var width = rect.width;
            var height = rect.height;
            var anchor = node.closest('[data-sl-float-align]');
            var align = anchor ? anchor.getAttribute('data-sl-float-align') : '';
            var left = (align === 'end') ? host.right - width : host.left + (host.width / 2) - (width / 2);
            var top = host.bottom + floatGap;
            var center = host.left + (host.width / 2);

            if (left + width > window.innerWidth - floatEdge) {
                left = Math.max(floatEdge, Math.min(host.right, window.innerWidth - floatEdge) - width);
                node.classList.add('sl-float-right');
            } else if (left < floatEdge) {
                left = floatEdge;
                node.classList.add('sl-float-left');
            }

            if (top + height > window.innerHeight - floatEdge && host.top > height + floatEdge) {
                top = host.top - height - floatGap;
                node.classList.add('sl-float-up');
            }

            var arrow = Math.max(14, Math.min(width - 14, center - left));
            panel.style.left = left + 'px';
            panel.style.top = top + 'px';
            panel.style.setProperty('--sl-float-arrow', arrow + 'px');
        });
    }

    function refitFloating() {
        var list = document.querySelectorAll('.sl-float.sl-is-open');
        for (var i = 0; i < list.length; i++) placeFloat(list[i]);
    }

    // Wire hover/focus float opening; closing waits for a grace delay and for focus to leave, so the cursor can cross the gap into the panel and typing inside never closes it
    function setFloating(node) {
        var root = node && node.nodeType ? node : document;
        var list = root.querySelectorAll ? root.querySelectorAll('.sl-float:not([data-sl-float-event="click"])') : [];
        for (var i = 0; i < list.length; i++) {
            (function (item) {
                if (item.getAttribute('data-sl-float-ready') === '1') return;
                item.setAttribute('data-sl-float-ready', '1');
                item.addEventListener('mouseenter', function () { placeFloat(item); });
                item.addEventListener('focusin', function () { placeFloat(item); });
                item.addEventListener('mouseleave', function () {
                    if (item.contains(document.activeElement)) return;
                    if (item.slFloatClose) window.clearTimeout(item.slFloatClose);
                    item.slFloatClose = window.setTimeout(function () {
                        item.slFloatClose = null;
                        clearFloatState(item);
                    }, 300);
                });
                item.addEventListener('focusout', function (e) {
                    if (e.relatedTarget && item.contains(e.relatedTarget)) return;
                    clearFloatState(item);
                });
            }(list[i]));
        }
    }

    // Close hover-model floats on a tap or click outside: touch has no mouseleave, so this is the only reliable close path on coarse pointers
    function setFloatOutsideClose() {
        if (document.documentElement.getAttribute('data-sl-float-outside-ready') === '1') return;
        document.documentElement.setAttribute('data-sl-float-outside-ready', '1');
        document.addEventListener('pointerdown', function (e) {
            var list = document.querySelectorAll('.sl-float.sl-is-open:not([data-sl-float-event="click"])');
            for (var i = 0; i < list.length; i++) {
                if (!list[i].contains(e.target)) clearFloatState(list[i]);
            }
        });
    }

    function initTabGroup(group) {
        var list = Array.prototype.slice.call(group.querySelectorAll('[data-sl-tab-link][data-sl-tab-target]'));
        if (!list.length) return;
        var saveGroup = group.getAttribute('data-sl-tabs-init') || group.id || 'tabs';
        var save = 'slaed-tabs:' + saveGroup;
        var syncSelector = group.getAttribute('data-sl-tabs-sync');

        function pick(link) {
            var rel = link.getAttribute('data-sl-tab-target');
            if (!rel) return;
            var index = list.indexOf(link);
            for (var i = 0; i < list.length; i++) {
                var item = list[i];
                var show = item === link;
                var target = item.getAttribute('data-sl-tab-target');
                var pane = group.querySelector('[data-sl-tab-panel="' + target + '"]') || document.getElementById(target);
                item.classList.toggle('sl-is-active', show);
                item.setAttribute('aria-selected', show ? 'true' : 'false');
                item.setAttribute('tabindex', show ? '0' : '-1');
                if (pane) {
                    pane.style.display = show ? 'block' : 'none';
                    pane.hidden = !show;
                    pane.setAttribute('aria-hidden', show ? 'false' : 'true');
                }
            }
            var shows = document.querySelectorAll('[data-sl-tab-show][data-sl-tab-group="' + saveGroup + '"]');
            for (var s = 0; s < shows.length; s++) {
                shows[s].style.display = (shows[s].getAttribute('data-sl-tab-show') === String(index)) ? '' : 'none';
            }
            try {
                window.sessionStorage.setItem(save, String(index));
            } catch (err) {
            }
            if (syncSelector) {
                var sync = document.querySelectorAll(syncSelector);
                for (var k = 0; k < sync.length; k++) {
                    if ('value' in sync[k]) sync[k].value = String(index);
                }
            }
            var infos = document.querySelectorAll('[data-sl-tab-info-link="' + saveGroup + '"]');
            for (var j = 0; j < infos.length; j++) {
                try {
                    var url = new URL(infos[j].href, window.location.href);
                    url.searchParams.set('tab', String(index));
                    infos[j].href = url.toString();
                } catch (err) {
                }
            }
        }

        for (var i = 0; i < list.length; i++) {
            list[i].setAttribute('role', 'tab');
            list[i].onclick = function () {
                pick(this);
                return false;
            };
            list[i].onkeydown = function (event) {
                var pos = list.indexOf(this);
                if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    pick(list[(pos + 1) % list.length]);
                } else if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    pick(list[(pos + list.length - 1) % list.length]);
                }
            };
            var target = list[i].getAttribute('data-sl-tab-target');
            var pane = group.querySelector('[data-sl-tab-panel="' + target + '"]') || document.getElementById(target);
            if (pane) pane.setAttribute('role', 'tabpanel');
        }

        var idx = -1;
        var attr = parseInt(group.getAttribute('data-sl-tabs-index'), 10);
        if (!isNaN(attr) && list[attr]) idx = attr;
        if (idx < 0) {
            try {
                idx = parseInt(window.sessionStorage.getItem(save), 10);
            } catch (err) {
            }
        }
        if (isNaN(idx) || !list[idx]) {
            idx = list.findIndex(function (item) {
                return item.classList.contains('sl-is-active');
            });
        }
        if (idx < 0) idx = 0;
        pick(list[idx]);

        var nav = group.querySelector('.sl-tabs-nav');
        if (nav) {
            nav.setAttribute('role', 'tablist');
            nav.addEventListener('wheel', function (event) {
                if (this.scrollWidth <= this.clientWidth) return;
                if (Math.abs(event.deltaY) <= Math.abs(event.deltaX) && event.deltaX === 0) return;
                event.preventDefault();
                this.scrollLeft += event.deltaX || event.deltaY;
            }, { passive: false });
        }
    }

    function setTabs(node) {
        var root = node && node.querySelectorAll ? node : document;
        var groups = root.querySelectorAll('[data-sl-tabs-init]');
        for (var i = 0; i < groups.length; i++) {
            if (groups[i].getAttribute('data-sl-tabs-ready') === '1') continue;
            groups[i].setAttribute('data-sl-tabs-ready', '1');
            initTabGroup(groups[i]);
        }
    }

    // Profile activity feeds: clone entries once and auto-scroll the visible list; hidden tab panels are measured after their tab becomes active
    function initProfileScroll(view) {
        if (view.getAttribute('data-sl-profile-ready') === '1' || view.offsetParent === null) return;
        var feed = view.querySelector('.sl-profile-scroll-feed');
        if (!feed) return;
        view.setAttribute('data-sl-profile-ready', '1');
        var items = Array.prototype.slice.call(feed.children);
        if (items.length <= 4 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        items.forEach(function (item) {
            var clone = item.cloneNode(true);
            clone.setAttribute('aria-hidden', 'true');
            feed.appendChild(clone);
        });
        window.requestAnimationFrame(function () {
            view.style.setProperty('--sl-profile-scroll-distance', feed.scrollHeight / 2 + 'px');
            view.style.setProperty('--sl-profile-scroll-duration', Math.max(15, items.length * 3) + 's');
            view.classList.add('sl-is-scrolling');
        });
    }

    function setProfileScrolls(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var views = scope.querySelectorAll('[data-sl-profile-scroll]');
        for (var i = 0; i < views.length; i++) initProfileScroll(views[i]);
    }

    function setAlerts(node) {
        var root = node && node.querySelectorAll ? node : document;
        var list = root.querySelectorAll('[data-sl-autohide]');
        for (var i = 0; i < list.length; i++) {
            var item = list[i];
            if (item.getAttribute('data-sl-alert-ready') === '1') continue;
            item.setAttribute('data-sl-alert-ready', '1');
            var time = parseInt(item.getAttribute('data-sl-autohide'), 10);
            if (isNaN(time) || time < 1) time = 5000;
            window.setTimeout((function (el) {
                return function () {
                    if (!el || !el.parentNode) return;
                    el.classList.add('sl-is-hiding');
                    window.setTimeout(function () {
                        if (el && el.parentNode) el.parentNode.removeChild(el);
                    }, 400);
                };
            })(item), time);
        }
    }

    // Voting widgets: ease one numeric label from a start value to a target over 800ms
    function setVoteNumber(el, from, to, unit) {
        var start = performance.now();
        function tick(now) {
            var p = Math.min((now - start) / 800, 1);
            var val = from + (to - from) * (1 - Math.pow(1 - p, 3));
            el.textContent = unit ? val.toFixed(2) + unit : String(Math.round(val));
            if (p < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    }

    // Voting widgets: apply data-sl-percent/votes targets to bars and counters; first run counts up from zero
    function setVoteValues(vote, first) {
        var rows = vote.querySelectorAll('li[data-sl-percent]');
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        for (var i = 0; i < rows.length; i++) {
            (function (row) {
                var pct = parseFloat(row.getAttribute('data-sl-percent')) || 0;
                var num = parseInt(row.getAttribute('data-sl-votes'), 10) || 0;
                var pel = row.querySelector('.sl-vote-pct');
                var vel = row.querySelector('.sl-vote-num');
                var bar = row.querySelector('.sl-progress-line div');
                if (bar) bar.style.width = pct.toFixed(2) + '%';
                if (reduce) {
                    if (pel) pel.textContent = pct.toFixed(2) + '%';
                    if (vel) vel.textContent = String(num);
                    return;
                }
                if (pel) setVoteNumber(pel, first ? 0 : parseFloat(pel.textContent) || 0, pct, '%');
                if (vel) setVoteNumber(vel, first ? 0 : parseInt(vel.textContent, 10) || 0, num, '');
            })(rows[i]);
        }
    }

    // Voting widgets: cold-start intro — bars and counters rise from zero after the first painted frame
    function setVoteIntro(vote) {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        var rows = vote.querySelectorAll('li[data-sl-percent]');
        if (!rows.length) return;
        for (var i = 0; i < rows.length; i++) {
            var bar = rows[i].querySelector('.sl-progress-line div');
            if (bar) bar.style.width = '0%';
        }
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { setVoteValues(vote, true); });
        });
    }

    // Voting widgets: poll the widget endpoint every data-sl-vote-live seconds and tween rows to fresh values
    function setVoteLive(vote) {
        var wait = parseInt(vote.getAttribute('data-sl-vote-live'), 10) || 0;
        var url = vote.getAttribute('data-sl-vote-url') || '';
        if (wait < 1 || url === '' || vote.getAttribute('data-sl-vote-ready') === '1') return;
        vote.setAttribute('data-sl-vote-ready', '1');
        if (!vote.hasAttribute('data-sl-live-stamp')) vote.setAttribute('data-sl-live-stamp', Date.now());
        var timer = window.setInterval(function () {
            if (!document.body.contains(vote)) { window.clearInterval(timer); return; }
            if (vote.hasAttribute('data-sl-live-paused')) return;
            window.fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (response) {
                if (!response.ok) throw new Error('Request failed');
                return response.text();
            }).then(function (html) {
                var hold = document.createElement('template');
                hold.innerHTML = html;
                var fresh = hold.content.querySelector('.sl-vote');
                if (!fresh) return;
                var frows = fresh.querySelectorAll('li[data-sl-percent]');
                var rows = vote.querySelectorAll('li[data-sl-percent]');
                if (!frows.length || frows.length !== rows.length) {
                    window.clearInterval(timer);
                    vote.replaceWith(fresh);
                    setVoteBlocks(document);
                    return;
                }
                for (var i = 0; i < rows.length; i++) {
                    rows[i].setAttribute('data-sl-percent', frows[i].getAttribute('data-sl-percent'));
                    rows[i].setAttribute('data-sl-votes', frows[i].getAttribute('data-sl-votes'));
                    rows[i].classList.toggle('sl-lead', frows[i].classList.contains('sl-lead'));
                }
                setVoteValues(vote, false);
                vote.setAttribute('data-sl-live-stamp', Date.now());
                var votes = vote.querySelector('.sl-votes');
                var fvotes = fresh.querySelector('.sl-votes');
                if (votes && fvotes) votes.innerHTML = fvotes.innerHTML;
                var coms = vote.querySelector('.sl-coms');
                var fcoms = fresh.querySelector('.sl-coms');
                if (coms && fcoms) coms.innerHTML = fcoms.innerHTML;
            }).catch(function () {});
        }, wait * 1000);
    }

    // Voting widgets: entry point for initial load and htmx swaps
    function setVoteBlocks(root) {
        var votes = (root || document).querySelectorAll('.sl-vote');
        for (var i = 0; i < votes.length; i++) {
            if (votes[i].getAttribute('data-sl-vote-intro') !== '1') {
                votes[i].setAttribute('data-sl-vote-intro', '1');
                setVoteIntro(votes[i]);
            }
            setVoteLive(votes[i]);
        }
    }

    // QR generator: byte mode, ECC level L, penalty-based mask choice - compact port of Nayuki's qrcodegen (public domain)
    function getQrSvg(text) {
        var ECC = [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30];
        var BLK = [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25];
        function raw(v) { var r = (16 * v + 128) * v + 64; if (v >= 2) { var a = Math.floor(v / 7) + 2; r -= (25 * a - 10) * a - 55; if (v >= 7) r -= 36; } return r; }
        function cap(v) { return Math.floor(raw(v) / 8) - ECC[v] * BLK[v]; }
        function gmul(x, y) { var z = 0; for (var i = 7; i >= 0; i--) { z = (z << 1) ^ ((z >>> 7) * 0x11D); z ^= ((y >>> i) & 1) * x; } return z & 255; }
        var bytes = Array.prototype.slice.call(new TextEncoder().encode(text));
        var ver;
        for (ver = 1; ver <= 40; ver++) if (4 + (ver < 10 ? 8 : 16) + bytes.length * 8 <= cap(ver) * 8) break;
        if (ver > 40) return '';
        var bits = [];
        function put(val, n) { for (var i = n - 1; i >= 0; i--) bits.push((val >>> i) & 1); }
        put(4, 4);
        put(bytes.length, ver < 10 ? 8 : 16);
        bytes.forEach(function (b) { put(b, 8); });
        var capbits = cap(ver) * 8;
        put(0, Math.min(4, capbits - bits.length));
        put(0, (8 - bits.length % 8) % 8);
        for (var pad = 0xEC; bits.length < capbits; pad ^= 0xEC ^ 0x11) put(pad, 8);
        var data = [];
        for (var i = 0; i < bits.length; i += 8) { var b = 0; for (var j = 0; j < 8; j++) b = (b << 1) | bits[i + j]; data.push(b); }
        var nb = BLK[ver], ecl = ECC[ver], rawcw = Math.floor(raw(ver) / 8);
        var nshort = nb - rawcw % nb, blocklen = Math.floor(rawcw / nb);
        var div = [];
        for (i = 0; i < ecl - 1; i++) div.push(0);
        div.push(1);
        var root = 1;
        for (i = 0; i < ecl; i++) {
            for (j = 0; j < div.length; j++) {
                div[j] = gmul(div[j], root);
                if (j + 1 < div.length) div[j] ^= div[j + 1];
            }
            root = gmul(root, 2);
        }
        var blocks = [], k = 0;
        for (i = 0; i < nb; i++) {
            var dat = data.slice(k, k + blocklen - ecl + (i < nshort ? 0 : 1));
            k += dat.length;
            var rem = div.map(function () { return 0; });
            dat.forEach(function (bb) {
                var factor = bb ^ rem.shift();
                rem.push(0);
                div.forEach(function (c, ci) { rem[ci] ^= gmul(c, factor); });
            });
            if (i < nshort) dat.push(0);
            blocks.push(dat.concat(rem));
        }
        var cw = [];
        for (i = 0; i < blocks[0].length; i++) for (j = 0; j < blocks.length; j++) if (i != blocklen - ecl || j >= nshort) cw.push(blocks[j][i]);
        var size = ver * 4 + 17, mods = [], fun = [];
        for (i = 0; i < size; i++) { mods.push(new Array(size).fill(false)); fun.push(new Array(size).fill(false)); }
        function setf(x, y, d) { mods[y][x] = d; fun[y][x] = true; }
        function bit(x, i) { return ((x >>> i) & 1) != 0; }
        for (i = 0; i < size; i++) { setf(6, i, i % 2 == 0); setf(i, 6, i % 2 == 0); }
        function finder(cx, cy) {
            for (var dy = -4; dy <= 4; dy++) for (var dx = -4; dx <= 4; dx++) {
                var xx = cx + dx, yy = cy + dy, dd = Math.max(Math.abs(dx), Math.abs(dy));
                if (xx >= 0 && xx < size && yy >= 0 && yy < size) setf(xx, yy, dd != 2 && dd != 4);
            }
        }
        finder(3, 3); finder(size - 4, 3); finder(3, size - 4);
        var ap = [];
        if (ver > 1) {
            var na = Math.floor(ver / 7) + 2;
            var step = (ver == 32) ? 26 : Math.ceil((ver * 4 + 4) / (na * 2 - 2)) * 2;
            ap = [6];
            for (var pos = size - 7; ap.length < na; pos -= step) ap.splice(1, 0, pos);
        }
        for (i = 0; i < ap.length; i++) for (j = 0; j < ap.length; j++) {
            if ((i == 0 && j == 0) || (i == 0 && j == ap.length - 1) || (i == ap.length - 1 && j == 0)) continue;
            for (var dy = -2; dy <= 2; dy++) for (var dx = -2; dx <= 2; dx++) setf(ap[i] + dx, ap[j] + dy, Math.max(Math.abs(dx), Math.abs(dy)) != 1);
        }
        function fmt(mask) {
            var d = (1 << 3) | mask, r = d;
            for (var i = 0; i < 10; i++) r = (r << 1) ^ ((r >>> 9) * 0x537);
            var f = ((d << 10) | r) ^ 0x5412;
            for (i = 0; i <= 5; i++) setf(8, i, bit(f, i));
            setf(8, 7, bit(f, 6)); setf(8, 8, bit(f, 7)); setf(7, 8, bit(f, 8));
            for (i = 9; i < 15; i++) setf(14 - i, 8, bit(f, i));
            for (i = 0; i < 8; i++) setf(size - 1 - i, 8, bit(f, i));
            for (i = 8; i < 15; i++) setf(8, size - 15 + i, bit(f, i));
            setf(8, size - 8, true);
        }
        fmt(0);
        if (ver >= 7) {
            var vr = ver;
            for (i = 0; i < 12; i++) vr = (vr << 1) ^ ((vr >>> 11) * 0x1F25);
            var vb = (ver << 12) | vr;
            for (i = 0; i < 18; i++) {
                var c = bit(vb, i), a = size - 11 + i % 3, d2 = Math.floor(i / 3);
                setf(a, d2, c); setf(d2, a, c);
            }
        }
        var idx = 0;
        for (var right = size - 1; right >= 1; right -= 2) {
            if (right == 6) right = 5;
            for (var vert = 0; vert < size; vert++) {
                for (j = 0; j < 2; j++) {
                    var x = right - j, up = ((right + 1) & 2) == 0;
                    var y = up ? size - 1 - vert : vert;
                    if (!fun[y][x] && idx < cw.length * 8) {
                        mods[y][x] = ((cw[idx >>> 3] >>> (7 - (idx & 7))) & 1) != 0;
                        idx++;
                    }
                }
            }
        }
        function maskbit(m, x, y) {
            switch (m) {
                case 0: return (x + y) % 2 == 0;
                case 1: return y % 2 == 0;
                case 2: return x % 3 == 0;
                case 3: return (x + y) % 3 == 0;
                case 4: return (Math.floor(x / 3) + Math.floor(y / 2)) % 2 == 0;
                case 5: return x * y % 2 + x * y % 3 == 0;
                case 6: return (x * y % 2 + x * y % 3) % 2 == 0;
                default: return ((x + y) % 2 + x * y % 3) % 2 == 0;
            }
        }
        function applymask(m) {
            for (var y = 0; y < size; y++) for (var x = 0; x < size; x++)
                if (!fun[y][x] && maskbit(m, x, y)) mods[y][x] = !mods[y][x];
        }
        function penalty() {
            var res = 0, x, y, dark = 0;
            function line(get) {
                var s = 0, run = 1, i, j, p;
                for (i = 1; i <= size; i++) {
                    if (i < size && get(i) == get(i - 1)) run++;
                    else { if (run >= 5) s += 3 + run - 5; run = 1; }
                }
                for (i = 0; i + 11 <= size; i++) {
                    p = 0;
                    for (j = 0; j < 11; j++) p = (p << 1) | (get(i + j) ? 1 : 0);
                    if (p == 0x5D0 || p == 0x05D) s += 40;
                }
                return s;
            }
            for (y = 0; y < size; y++) (function (yy) { res += line(function (i) { return mods[yy][i]; }); })(y);
            for (x = 0; x < size; x++) (function (xx) { res += line(function (i) { return mods[i][xx]; }); })(x);
            for (y = 0; y < size - 1; y++) for (x = 0; x < size - 1; x++)
                if (mods[y][x] == mods[y][x + 1] && mods[y][x] == mods[y + 1][x] && mods[y][x] == mods[y + 1][x + 1]) res += 3;
            for (y = 0; y < size; y++) for (x = 0; x < size; x++) if (mods[y][x]) dark++;
            res += (Math.ceil(Math.abs(dark * 20 - size * size * 10) / (size * size)) - 1) * 10;
            return res;
        }
        var best = 0, bestscore = Infinity;
        for (var m = 0; m < 8; m++) {
            applymask(m); fmt(m);
            var s = penalty();
            if (s < bestscore) { bestscore = s; best = m; }
            applymask(m);
        }
        applymask(best); fmt(best);
        var dim = size + 6, path = '';
        for (var yy = 0; yy < size; yy++) for (var xx = 0; xx < size; xx++)
            if (mods[yy][xx]) path += 'M' + (xx + 3) + ' ' + (yy + 3) + 'h1v1h-1z';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + dim + ' ' + dim + '" shape-rendering="crispEdges" role="img" aria-label="QR"><rect width="100%" height="100%" fill="#ffffff"/><path d="' + path + '" fill="#111827"/></svg>';
    }

    // Toast: singleton confirmation pill, created on first use and reused
    function setToast(text) {
        if (!text) return;
        var toast = document.querySelector('.sl-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'sl-toast';
            toast.setAttribute('role', 'status');
            toast.innerHTML = '<i class="bi bi-check-lg" aria-hidden="true"></i> <span></span>';
            document.body.appendChild(toast);
        }
        toast.querySelector('span').textContent = text;
        toast.classList.add('sl-is-visible');
        window.clearTimeout(toast.sltimer);
        toast.sltimer = window.setTimeout(function () { toast.classList.remove('sl-is-visible'); }, 2600);
    }

    // Share endpoints: {u} = encoded canonical url, {t} = encoded title
    var sharenets = {
        telegram: 'https://t.me/share/url?url={u}&text={t}',
        whatsapp: 'https://wa.me/?text={t}%20{u}',
        viber: 'viber://forward?text={t}%20{u}',
        vk: 'https://vk.com/share.php?url={u}&title={t}',
        ok: 'https://connect.ok.ru/offer?url={u}&title={t}',
        facebook: 'https://www.facebook.com/sharer/sharer.php?u={u}',
        x: 'https://x.com/intent/post?url={u}&text={t}',
        pinterest: 'https://pinterest.com/pin/create/button/?url={u}&description={t}',
        reddit: 'https://www.reddit.com/submit?url={u}&title={t}',
        linkedin: 'https://www.linkedin.com/sharing/share-offsite/?url={u}',
        mail: 'mailto:?subject={t}&body={u}'
    };

    // Share helpers: canonical url and title come from the closest annotated host (dial or dialog)
    function getShareData(node) {
        var host = node.closest('[data-sl-share-url]');
        if (!host) return null;
        var canon = document.querySelector('link[rel="canonical"]');
        var base = canon ? canon.href : window.location.href.split('#')[0];
        var url = null;
        try {
            url = new URL(host.getAttribute('data-sl-share-url') || base, base);
        } catch (error) {
            return null;
        }
        if (url.protocol !== 'http:' && url.protocol !== 'https:') return null;
        return { url: url.href, title: host.getAttribute('data-sl-share-title') || document.title };
    }

    // Copy the canonical url with a clipboard fallback, flash a check icon and confirm with a toast
    function setShareCopy(node, url) {
        var done = function () {
            setToast(node.getAttribute('data-sl-done') || '');
            var icon = node.querySelector('.bi');
            if (!icon) return;
            var was = icon.className;
            icon.className = 'bi bi-check-lg';
            node.classList.add('sl-is-copied');
            window.setTimeout(function () { icon.className = was; node.classList.remove('sl-is-copied'); }, 1400);
        };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(url).then(done, done); return; }
        var area = document.createElement('textarea');
        area.value = url;
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        try { document.execCommand('copy'); } catch (err) {}
        area.remove();
        done();
    }

    // Favorites: remember the clicked star so the freshly swapped on-state can burst and toast
    var favpending = null;

    function setFavoriteBurst() {
        if (!favpending) return;
        var host = document.getElementById(favpending.id);
        var done = favpending.done;
        favpending = null;
        if (!host) return;
        var star = host.querySelector('.sl-fav-on');
        if (!star) return;
        star.classList.add('sl-is-burst');
        window.setTimeout(function () { star.classList.remove('sl-is-burst'); }, 700);
        setToast(done);
    }

    // Live boxes: containers that own auto-refresh state (voting widget root or a marked htmx container)
    function getLiveBox(node) {
        return node.closest('[data-sl-vote-live], [data-sl-live-box]');
    }

    function setLiveState(box) {
        var chip = box.querySelector('.sl-live-chip');
        if (chip) chip.classList.toggle('sl-is-paused', box.hasAttribute('data-sl-live-paused'));
    }

    // Live chips: init refresh stamps and sync chip visuals after load and htmx swaps
    function setLiveChips(root) {
        var scope = root || document;
        var boxes = Array.prototype.slice.call(scope.querySelectorAll('[data-sl-vote-live], [data-sl-live-box]'));
        if (scope !== document && scope.matches && scope.matches('[data-sl-vote-live], [data-sl-live-box]')) boxes.push(scope);
        for (var i = 0; i < boxes.length; i++) {
            if (!boxes[i].hasAttribute('data-sl-live-stamp')) boxes[i].setAttribute('data-sl-live-stamp', Date.now());
            setLiveState(boxes[i]);
        }
    }

    // Live chips: tick the "updated N sec ago" counters once per second
    function setLiveTick() {
        var boxes = document.querySelectorAll('[data-sl-live-stamp]');
        for (var i = 0; i < boxes.length; i++) {
            if (boxes[i].hasAttribute('data-sl-live-paused')) continue;
            var ago = boxes[i].querySelector('[data-sl-live-ago]');
            if (!ago) continue;
            var sec = Math.max(0, Math.round((Date.now() - parseInt(boxes[i].getAttribute('data-sl-live-stamp'), 10)) / 1000));
            ago.textContent = sec + ' ' + (ago.getAttribute('data-sl-live-unit') || 's');
        }
    }

    // Delegated UI actions: share networks and dialogs, copy, favorite star capture, live pause toggle
    function setUiActions() {
        document.addEventListener('click', function (event) {
            var node = event.target;
            if (!node || !node.closest) return;
            var fav = node.closest('[data-sl-fav]');
            if (fav) favpending = { id: fav.getAttribute('data-sl-fav'), done: fav.getAttribute('data-sl-done') || '' };
            var close = node.closest('[data-sl-close]');
            if (close) {
                var dlg = close.closest('dialog');
                if (dlg) dlg.close();
                return;
            }
            if (node.tagName === 'DIALOG' && node.classList.contains('sl-modal')) { node.close(); return; }
            var okay = node.closest('[data-sl-confirm-ok]');
            if (okay) {
                var box = okay.closest('dialog');
                var ask = box ? box.slask : null;
                if (box) box.close();
                if (ask && ask.href) window.location.href = ask.href;
                return;
            }
            var toggle = node.closest('[data-sl-live-toggle]');
            if (toggle) {
                var live = getLiveBox(toggle);
                if (!live) return;
                if (live.hasAttribute('data-sl-live-paused')) live.removeAttribute('data-sl-live-paused');
                else live.setAttribute('data-sl-live-paused', '1');
                setLiveState(live);
                return;
            }
            var net = node.closest('[data-sl-net]');
            if (net) {
                var data = getShareData(net);
                var key = net.getAttribute('data-sl-net');
                var mask = sharenets[key] || '';
                if (!data || !mask) return;
                var target = mask.replace('{u}', encodeURIComponent(data.url)).replace('{t}', encodeURIComponent(data.title));
                var sheet = net.closest('dialog');
                if (sheet) sheet.close();
                if (key === 'mail' || key === 'viber') { window.location.href = target; return; }
                window.open(target, 'slshare', 'width=640,height=520,noopener');
                return;
            }
            var act = node.closest('[data-sl-share-act]');
            if (!act) return;
            var kind = act.getAttribute('data-sl-share-act');
            var share = getShareData(act);
            if (kind === 'copy' && share) setShareCopy(act, share.url);
            if (kind === 'qr' && share) {
                var qr = document.getElementById('sl-share-qr');
                if (!qr) return;
                qr.setAttribute('data-sl-share-url', share.url);
                var note = qr.querySelector('.sl-modal-note');
                if (note) note.textContent = share.url;
                var slot = qr.querySelector('[data-sl-qr]');
                if (slot && slot.getAttribute('data-sl-qr') !== share.url) {
                    slot.innerHTML = getQrSvg(share.url);
                    slot.setAttribute('data-sl-qr', share.url);
                }
                qr.showModal();
            }
            if (kind === 'more' && share) {
                var more = document.getElementById('sl-share-sheet');
                if (!more) return;
                more.setAttribute('data-sl-share-url', share.url);
                more.setAttribute('data-sl-share-title', share.title);
                more.showModal();
            }
        });
        // Paused live boxes swallow their htmx polls; direct clicks keep working
        document.addEventListener('htmx:beforeRequest', function (event) {
            var trig = event.detail && event.detail.requestConfig ? event.detail.requestConfig.triggeringEvent : null;
            if (trig && trig.type === 'click') return;
            var elt = event.detail ? event.detail.elt : null;
            if (elt && elt.closest && elt.closest('[data-sl-live-paused]')) event.preventDefault();
        });
        window.setInterval(function () {
            if (document.hidden || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) return;
            var lists = document.querySelectorAll('[data-sl-live-scroll].sl-is-open');
            for (var i = 0; i < lists.length; i++) {
                var list = lists[i];
                if (list.closest('[data-sl-live-paused]') || list.matches(':hover') || list.contains(document.activeElement)) continue;
                var limit = list.scrollHeight - list.clientHeight;
                if (limit <= 1) continue;
                var wait = parseInt(list.getAttribute('data-sl-scroll-wait') || '0', 10);
                if (list.scrollTop >= limit - 1) {
                    if (!wait) list.setAttribute('data-sl-scroll-wait', Date.now() + 1000);
                    else if (Date.now() >= wait) {
                        list.scrollTop = 0;
                        list.removeAttribute('data-sl-scroll-wait');
                    }
                    continue;
                }
                list.scrollTop += 1;
            }
        }, 50);
        window.setInterval(setLiveTick, 1000);
    }

    function setSlaedUi() {
        setTableSort(document);
        setLightbox();
        setImageReplace();
        setTableCheckAll();
        setToggleBlocks();
        setFloating(document);
        setFloatOutsideClose();
        setEditorInsertHandler();
        setDialToggle();
        setTabs(document);
        setAlerts(document);
        setVoteBlocks(document);
        setUiActions();
        setLiveChips(document);
        setProfileScrolls(document);
    }

    // Feed lists inside inactive tabs have zero height: re-measure pending ones right after any tab switch
    document.addEventListener('click', function (event) {
        if (!event.target || !event.target.closest || !event.target.closest('[data-sl-tab-link]')) return;
        window.requestAnimationFrame(function () { setProfileScrolls(document); });
    });

    document.addEventListener('htmx:afterSwap', function (event) {
        setTableSort(event.target);
        setToggleBlocks();
        setFloating(event.target);
        setTabs(event.target);
        setAlerts(event.target);
        setVoteBlocks(event.target);
        var live = event.target && event.target.closest ? event.target.closest('[data-sl-live-box]') : null;
        if (live) live.setAttribute('data-sl-live-stamp', Date.now());
        setLiveChips(event.target);
        setProfileScrolls(document);
        if (live === event.target && window.htmx) {
            var detail = live.querySelector('[data-sl-toggle-control][aria-expanded="true"]');
            var rows = live.querySelector('.sl-session-list [id$="-rows"]');
            var query = detail ? detail.getAttribute('hx-get') : '';
            if (detail && rows && query) window.htmx.ajax('GET', query, { source: detail, target: rows, swap: 'innerHTML' });
        }
        setFavoriteBurst();
    });

    window.addEventListener('resize', refitFloating);
    window.addEventListener('scroll', refitFloating, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setSlaedUi);
    } else {
        setSlaedUi();
    }
})();
