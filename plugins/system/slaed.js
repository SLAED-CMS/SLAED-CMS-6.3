(function () {
    function ensureLightboxStyles() {
        if (document.getElementById('sl-lightbox-styles')) return;
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

    function createLightbox() {
        var root = document.createElement('div');
        root.className = 'sl-lightbox';
        root.setAttribute('hidden', 'hidden');
        root.innerHTML = '<div class="sl-lightbox__dialog" role="dialog" aria-modal="true" aria-label="Image preview"><div class="sl-lightbox__surface"><img class="sl-lightbox__image" alt=""><div class="sl-lightbox__caption" hidden></div></div><button type="button" class="sl-lightbox__close" aria-label="Close">×</button></div>';
        document.body.appendChild(root);
        return root;
    }

    function initLightbox() {
        ensureLightboxStyles();

        var root = null;
        var image = null;
        var caption = null;
        var closeButton = null;
        var previousOverflow = '';

        function ensureLightbox() {
            if (root) return;
            root = createLightbox();
            image = root.querySelector('.sl-lightbox__image');
            caption = root.querySelector('.sl-lightbox__caption');
            closeButton = root.querySelector('.sl-lightbox__close');

            closeButton.addEventListener('click', close);
            root.addEventListener('click', function (event) {
                if (event.target === root) close();
            });
        }

        function close() {
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

        function open(src, title) {
            ensureLightbox();
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
            open(href, trigger.getAttribute('title') || '');
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') close();
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
                cell.classList.remove('sl_sort', 'sl_sort_asc', 'sl_sort_desc');
                if (cell.getAttribute('data-sort-method') === 'none') continue;
                cell.classList.add('sl_sort');
                if (cell.getAttribute('aria-sort') === 'ascending') {
                    cell.classList.remove('sl_sort');
                    cell.classList.add('sl_sort_asc');
                } else if (cell.getAttribute('aria-sort') === 'descending') {
                    cell.classList.remove('sl_sort');
                    cell.classList.add('sl_sort_desc');
                }
            }
        }
    }

    function setTableSort(node) {
        if (typeof window.Tablesort === 'undefined') return;
        var list = [];
        var root = node && node.nodeType ? node : document;
        var tables = root.querySelectorAll ? root.querySelectorAll('.sl_table_list_sort') : [];
        if (root.nodeType === 1 && root.classList && root.classList.contains('sl_table_list_sort')) {
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

    function initTableSortExtensions() {
        if (typeof window.Tablesort === 'undefined' || typeof window.Tablesort.extend !== 'function') return;
        if (!window.Tablesort.__slaedExtensionsReady) {
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
    }

    function storageKey(name, scoped) {
        return scoped ? 'slaed-toggle:' + window.location.pathname + window.location.search + ':' + name : 'slaed-toggle:' + name;
    }

    function readToggleState(name, scoped) {
        try {
            return window.localStorage.getItem(storageKey(name, scoped));
        } catch (err) {
            return null;
        }
    }

    function writeToggleState(name, scoped, value) {
        try {
            window.localStorage.setItem(storageKey(name, scoped), value);
        } catch (err) {
        }
    }

    function animateSlide(element, show, duration) {
        var startHeight = show ? 0 : element.offsetHeight;
        var endHeight = show ? element.scrollHeight : 0;
        if (show) {
            element.hidden = false;
            element.style.display = 'block';
        }
        element.style.overflow = 'hidden';
        element.style.height = startHeight + 'px';
        element.animate([
            { height: startHeight + 'px', opacity: show ? 0 : 1 },
            { height: endHeight + 'px', opacity: show ? 1 : 0 }
        ], {
            duration: duration,
            easing: 'ease'
        }).onfinish = function () {
            element.style.overflow = '';
            element.style.height = '';
            if (!show) {
                element.style.display = 'none';
                element.hidden = true;
            }
        };
    }

    function animateFadeScale(element, show, duration) {
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

    function fetchJson(url) {
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

    function initImageReplace() {
        var select = document.getElementById('img_replace');
        var picture = document.getElementById('picture');
        if (!select || !picture) return;
        select.addEventListener('change', function () {
            picture.setAttribute('src', this.value || '');
        });
    }

    function initCloseOpenBlocks() {
        var blocks = document.querySelectorAll('.data[data-all]');
        for (var i = 0; i < blocks.length; i++) {
            var raw = blocks[i].getAttribute('data-all');
            if (!raw) continue;
            try {
                var data = JSON.parse(raw);
                if (!data || !data.id) continue;
                blocks[i].style.display = readToggleState(data.id, false) === '0' ? 'none' : 'block';
            } catch (err) {
            }
        }
    }

    window.CloseOpen = function (obj, path) {
        var element = document.getElementById(obj);
        if (!element) return;
        var isHidden = window.getComputedStyle(element).display === 'none';
        element.style.display = isHidden ? 'block' : 'none';
        element.hidden = !isHidden ? true : false;
        writeToggleState(obj, !!path, isHidden ? '1' : '0');
    };

    window.HideShow = function (obj, eff, opt, dur) {
        var element = document.getElementById(obj);
        if (!element) return false;
        var duration = dur || 400;
        var isHidden = window.getComputedStyle(element).display === 'none' || element.hidden;
        if (eff === 'puff') {
            animateFadeScale(element, isHidden, duration);
        } else {
            animateSlide(element, isHidden, duration);
        }
        return false;
    };

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

    window.CheckBox = function (id, clas) {
        var master = typeof id === 'string' ? document.querySelector(id) : id;
        if (!master) return;
        var items = document.querySelectorAll(clas);
        for (var i = 0; i < items.length; i++) {
            items[i].checked = !!master.checked;
        }
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
            fetchJson(url + '?q=' + encodeURIComponent(txt) + '&langpair=' + encodeURIComponent(from + '|' + to))
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

        (function scan(node) {
            if (node.nodeType === 3 && node.nodeValue.trim() !== '') {
                nodes.push(node);
                return;
            }
            for (var i = 0; i < node.childNodes.length; i++) {
                scan(node.childNodes[i]);
            }
        })(div);

        var index = 0;
        function processNext() {
            if (index >= nodes.length) {
                setInputValueByClass(output, div.innerHTML);
                return;
            }
            var original = nodes[index].nodeValue.trim();
            fetchJson(url + '?q=' + encodeURIComponent(original) + '&langpair=' + encodeURIComponent(from + '|' + to))
                .then(function (res) {
                    if (res && res.responseData && res.responseData.translatedText) {
                        nodes[index].nodeValue = res.responseData.translatedText;
                    }
                })
                .catch(function () {
                })
                .finally(function () {
                    index++;
                    window.setTimeout(processNext, 100);
                });
        }

        processNext();
    };

    function initSlaedUi() {
        initTableSortExtensions();
        setTableSort(document);
        initLightbox();
        initImageReplace();
        initCloseOpenBlocks();
    }

    document.addEventListener('htmx:afterSwap', function (event) {
        setTableSort(event.target);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSlaedUi);
    } else {
        initSlaedUi();
    }
})();
