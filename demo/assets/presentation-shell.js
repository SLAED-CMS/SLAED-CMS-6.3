(() => {
    const params = new URLSearchParams(location.search);
    const root = document.documentElement;
    const head = document.querySelector('[data-site-head]');
    const foot = document.querySelector('[data-site-foot]');
    if (params.has('bare')) {
        document.addEventListener('DOMContentLoaded', () => { root.dataset.demoMotion = 'off'; }, { once: true });
        return;
    }
    const page = new URL('../index.php?name=main', location.href);
    fetch(page, { credentials: 'same-origin', cache: 'no-store' }).then(async (reply) => {
        if (!reply.ok) throw new Error('HTTP ' + reply.status);
        const doc = new DOMParser().parseFromString(await reply.text(), 'text/html');
        const top = ['topbar', 'header', 'hmenu'].map((id) => doc.getElementById(id));
        const end = ['demo-line', 'footbox'].map((id) => doc.getElementById(id));
        if ([...top, ...end].some((node) => !node)) throw new Error('Missing project header or footer');
        [...top, ...end].forEach((node) => {
            node.querySelectorAll('[href], [src], [action]').forEach((el) => {
                ['href', 'src', 'action'].forEach((attr) => {
                    const value = el.getAttribute(attr);
                    if (value && !value.startsWith('#')) el.setAttribute(attr, new URL(value, page).href);
                });
            });
            node.querySelectorAll('script').forEach((el) => el.remove());
        });
        head.replaceChildren(...top);
        foot.replaceChildren(...end);
        head.querySelectorAll('.sl-mode button[name="mode"]').forEach((btn) => {
            btn.type = 'button';
            btn.dataset.demoSet = 'mode';
            btn.dataset.demoValue = btn.value;
        });
        const watch = new MutationObserver(() => {
            head.querySelectorAll('.sl-mode-cell').forEach((btn) => {
                btn.classList.toggle('sl-is-active', btn.value === root.dataset.theme);
            });
        });
        watch.observe(root, { attributes: true, attributeFilter: ['data-theme'] });
        head.querySelectorAll('.sl-mode-cell').forEach((btn) => {
            btn.classList.toggle('sl-is-active', btn.value === root.dataset.theme);
        });
        document.querySelectorAll('[data-demo-set]').forEach((btn) => {
            if (btn.dataset.demoSet === 'mode') btn.setAttribute('aria-pressed', String(btn.dataset.demoValue === root.dataset.theme));
        });
        root.dataset.siteShell = 'ready';
    }).catch((err) => {
        head.className = 'sl-pres-shell-status';
        head.innerHTML = 'Не удалось загрузить шапку и подвал проекта. <a href="">Повторить</a>';
        root.dataset.siteShell = 'error';
        console.error('Project shell:', err.message);
    });
})();
