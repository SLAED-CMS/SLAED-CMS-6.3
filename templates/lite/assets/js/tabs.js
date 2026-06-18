(function () {
    function setSiteTabs(group) {
        var root = document.getElementById(group);
        if (!root) return;

        var list = Array.prototype.slice.call(root.querySelectorAll('ul > li > a[href^="#"]'));
        if (!list.length) return;
        var tablist = root.querySelector('ul');

        if (tablist) {
            tablist.classList.add('sl-tabs-nav');
            tablist.setAttribute('role', 'tablist');
        }

        var save = 'slaed-site-tabs:' + window.location.pathname + window.location.search + ':' + group;

        function pick(link) {
            var hash = link.getAttribute('href');
            if (!hash || hash.charAt(0) !== '#') return;

            for (var i = 0; i < list.length; i++) {
                var item = list[i];
                var show = item === link;
                var paneId = item.getAttribute('href').slice(1);
                var pane = document.getElementById(paneId);
                var tabItem = item.parentNode;

                item.classList.toggle('selected', show);
                item.setAttribute('aria-selected', show ? 'true' : 'false');
                item.setAttribute('tabindex', show ? '0' : '-1');

                if (tabItem && tabItem.classList) {
                    tabItem.classList.add('sl-tabs-item');
                    tabItem.classList.toggle('sl-tabs-active', show);
                }

                if (pane) {
                    pane.classList.add('sl-tabs-panel');
                    pane.style.display = show ? 'block' : 'none';
                    pane.hidden = !show;
                    pane.setAttribute('aria-hidden', show ? 'false' : 'true');
                }
            }

            try {
                window.sessionStorage.setItem(save, String(list.indexOf(link)));
            } catch (err) {
            }
        }

        for (var i = 0; i < list.length; i++) {
            if (list[i].parentNode) list[i].parentNode.setAttribute('role', 'presentation');
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

            var pane = document.getElementById(list[i].getAttribute('href').slice(1));
            if (pane) pane.setAttribute('role', 'tabpanel');
        }

        var idx = -1;
        try {
            idx = parseInt(window.sessionStorage.getItem(save), 10);
        } catch (err) {
        }
        if (isNaN(idx) || !list[idx]) {
            idx = list.findIndex(function (item) {
                return item.classList.contains('selected') || item.getAttribute('aria-selected') === 'true';
            });
        }
        if (idx < 0 && window.location.hash) {
            idx = list.findIndex(function (item) {
                return item.getAttribute('href') === window.location.hash;
            });
        }
        if (idx < 0) idx = 0;

        pick(list[idx]);
    }

    function initSiteTabs() {
        var roots = document.querySelectorAll('[data-sl-tabs]');
        for (var i = 0; i < roots.length; i++) {
            setSiteTabs(roots[i].id);
        }
        var lists = document.querySelectorAll('[data-sl-tabs]');
        for (var j = 0; j < lists.length; j++) {
            lists[j].addEventListener('wheel', function (event) {
                if (this.scrollWidth <= this.clientWidth) return;
                if (Math.abs(event.deltaY) <= Math.abs(event.deltaX) && event.deltaX === 0) return;
                event.preventDefault();
                this.scrollLeft += event.deltaX || event.deltaY;
            }, { passive: false });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSiteTabs);
    } else {
        initSiteTabs();
    }
})();
