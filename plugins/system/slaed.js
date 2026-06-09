(function () {
    function setLightbox() {
        if (!document.getElementById('sl-lightbox-styles')) {
            var style = document.createElement('style');
            style.id = 'sl-lightbox-styles';
            style.textContent = [
                '.sl-lightbox{position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;padding:24px;background:rgba(17,24,39,.72);opacity:0;visibility:hidden;transition:opacity .18s ease,visibility .18s ease;}',
                '.sl-lightbox.is-open{opacity:1;visibility:visible;}',
                '.sl-lightbox__dialog{position:relative;max-width:min(92vw,1280px);max-height:92vh;display:flex;align-items:center;justify-content:center;}',
                '.sl-lightbox__surface{background:#fff;border-radius:8px;box-shadow:0 18px 50px rgba(0,0,0,.32);overflow:hidden;}',
                '.sl-lightbox__image{display:block;max-width:min(92vw,1280px);max-height:calc(92vh - 48px);width:auto;height:auto;background:#fff;}',
                '.sl-lightbox__caption{padding:10px 14px;font:14px/1.4 Arial,sans-serif;color:#44515f;background:#f7f9fb;border-top:1px solid #dbe3eb;}',
                '.sl-lightbox__close{position:absolute;top:-14px;right:-14px;width:36px;height:36px;border:0;border-radius:18px;background:#1f2937;color:#fff;font:700 22px/36px Arial,sans-serif;cursor:pointer;box-shadow:0 6px 18px rgba(0,0,0,.28);}',
                '.sl-lightbox__close:hover{background:#111827;}'
            ].join('');
            document.head.appendChild(style);
        }

        var root = null;
        var image = null;
        var caption = null;
        var closeButton = null;
        var previousOverflow = '';

        function getLightbox() {
            if (root) return;
            root = document.createElement('div');
            root.className = 'sl-lightbox';
            root.setAttribute('hidden', 'hidden');
            root.innerHTML = '<div class="sl-lightbox__dialog" role="dialog" aria-modal="true" aria-label="Image preview"><div class="sl-lightbox__surface"><img class="sl-lightbox__image" alt=""><div class="sl-lightbox__caption" hidden></div></div><button type="button" class="sl-lightbox__close" aria-label="Close">×</button></div>';
            document.body.appendChild(root);
            image = root.querySelector('.sl-lightbox__image');
            caption = root.querySelector('.sl-lightbox__caption');
            closeButton = root.querySelector('.sl-lightbox__close');

            closeButton.addEventListener('click', setLightboxClose);
            root.addEventListener('click', function (event) {
                if (event.target === root) setLightboxClose();
            });
        }

        function setLightboxClose() {
            if (!root || root.hidden) return;
            root.classList.remove('is-open');
            window.setTimeout(function () {
                if (!root.classList.contains('is-open')) {
                    root.hidden = true;
                    image.removeAttribute('src');
                    image.removeAttribute('width');
                    image.removeAttribute('height');
                    caption.textContent = '';
                    caption.hidden = true;
                    document.documentElement.style.overflow = previousOverflow;
                }
            }, 180);
        }

        function setLightboxOpen(src, title) {
            getLightbox();
            previousOverflow = document.documentElement.style.overflow;
            image.src = src;
            image.alt = title || '';
            if (title) {
                caption.textContent = title;
                caption.hidden = false;
            } else {
                caption.textContent = '';
                caption.hidden = true;
            }
            root.hidden = false;
            document.documentElement.style.overflow = 'hidden';
            window.requestAnimationFrame(function () {
                root.classList.add('is-open');
            });
            closeButton.focus();
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('a.screens, a.site-link');
            if (!trigger) return;
            var href = trigger.getAttribute('href') || '';
            if (!/\.(?:bmp|gif|jpe?g|png|webp|svg)(?:[?#].*)?$/i.test(href)) return;
            event.preventDefault();
            setLightboxOpen(href, trigger.getAttribute('title') || '');
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') setLightboxClose();
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

    function setToggleBlockState(element, id, isOpen, effect, duration) {
        element.classList.toggle('sl-is-open', isOpen);
        element.classList.toggle('sl-is-closed', !isOpen);
        setToggleControls(id, isOpen);
        if (element.classList.contains('sl-float-panel')) {
            // Float dropdowns share the sl-tip visibility model: opacity/visibility
            // via CSS, viewport-fixed placement via placeFloat. Skip the legacy
            // display/slide/puff path so there is no positioning jump on open/close.
            var floatHost = element.closest('.sl-float');
            if (floatHost) {
                if (isOpen) placeFloat(floatHost); else clearFloatState(floatHost);
            }
            return;
        }
        var display = element.getAttribute('data-sl-toggle-display') || ((element.classList.contains('sl-div-item') || element.classList.contains('sl-div-grid')) ? 'grid' : 'block');
        if (effect === 'slide') {
            setSlideMotion(element, isOpen, duration || 400);
            return;
        }
        if (effect === 'puff') {
            setFadeScale(element, isOpen, duration || 400);
            return;
        }
        element.hidden = !isOpen;
        element.style.display = isOpen ? display : 'none';
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
            element.style.display = 'block';
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
            element.style.display = 'block';
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
        var legacy = document.getElementById('img_replace');
        if (!selects.length && legacy) {
            legacy.setAttribute('data-sl-image-replace', 'picture');
            selects = [legacy];
        }
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

    function clearFloatState(node) {
        // Only drop the state classes. The panel keeps its inline fixed position
        // so it fades out in place on close (hover and click) with no jump back
        // to the static CSS spot.
        for (var i = 0; i < floatStates.length; i++) node.classList.remove(floatStates[i]);
    }

    function getFloatPanel(node) {
        return node.querySelector(':scope > .sl-float-panel');
    }

    function placeFloat(node) {
        var panel = getFloatPanel(node);
        if (!panel) return;

        clearFloatState(node);
        node.classList.add('sl-is-open');

        // Position is written inline on the panel (both hover and click) so it
        // overrides any tuned/id-scoped theme CSS and, crucially, survives the
        // close transition (sl-is-open is removed, but the panel keeps these
        // styles and just fades out in place).
        panel.style.position = 'fixed';
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';
        panel.style.margin = '0';
        panel.style.transform = 'none';
        panel.style.zIndex = '3000';
        panel.style.left = '-9999px';
        panel.style.top = '-9999px';

        window.requestAnimationFrame(function () {
            var rect = panel.getBoundingClientRect();
            var host = node.getBoundingClientRect();
            var width = rect.width;
            var height = rect.height;
            var left = host.left + (host.width / 2) - (width / 2);
            var top = host.bottom + floatGap;
            var center = host.left + (host.width / 2);

            if (left + width > window.innerWidth - floatEdge) {
                left = Math.max(floatEdge, window.innerWidth - width - floatEdge);
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

    function setFloating(node) {
        var root = node && node.nodeType ? node : document;
        var list = root.querySelectorAll ? root.querySelectorAll('.sl-float:not([data-sl-float-event="click"])') : [];
        for (var i = 0; i < list.length; i++) {
            (function (item) {
                if (item.getAttribute('data-sl-float-ready') === '1') return;
                item.setAttribute('data-sl-float-ready', '1');
                item.addEventListener('mouseenter', function () { placeFloat(item); });
                item.addEventListener('focusin', function () { placeFloat(item); });
                item.addEventListener('mouseleave', function () { clearFloatState(item); });
                item.addEventListener('focusout', function () { clearFloatState(item); });
            }(list[i]));
        }
    }

    function setSlaedUi() {
        setTableSort(document);
        setLightbox();
        setImageReplace();
        setTableCheckAll();
        setToggleBlocks();
        setFloating(document);
        setEditorInsertHandler();
    }

    document.addEventListener('htmx:afterSwap', function (event) {
        setTableSort(event.target);
        setToggleBlocks();
        setFloating(event.target);
    });

    window.addEventListener('resize', refitFloating);
    window.addEventListener('scroll', refitFloating, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setSlaedUi);
    } else {
        setSlaedUi();
    }
})();
