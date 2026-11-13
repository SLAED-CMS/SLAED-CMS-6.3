// ALTCHA widget v3 bootstrap: the external build runs the proof of work in a same-origin worker, so the captcha works under a strict CSP with no change per site
import './altcha.min.js';

if (globalThis.$altcha) {
    // Register the self-hosted SHA-256 worker; the external build creates no blob: worker itself
    if (globalThis.$altcha.algorithms) {
        globalThis.$altcha.algorithms.set('SHA-256', () => new Worker(new URL('./altcha-sha.js', import.meta.url)));
    }
    // The external build does not inject CSS; load the widget stylesheet once (light DOM)
    if (!document.querySelector('link[data-altcha-css]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = new URL('./altcha.css', import.meta.url).href;
        link.dataset.altchaCss = '';
        document.head.appendChild(link);
    }
    // Localized strings via the inert JSON island (CSP-safe, applied before first render)
    const island = document.querySelector('script.sl-altcha-i18n');
    if (island && globalThis.$altcha.i18n) {
        try {
            globalThis.$altcha.i18n.set(island.getAttribute('data-lang'), JSON.parse(island.textContent));
        } catch (e) {
            // Malformed payload or changed i18n API: fall back to the built-in English strings
        }
    }
    // htmx sends on the click of a submit button, before the submit event the widget proves on: such a request waits until the proof stands in the form
    document.addEventListener('htmx:confirm', (event) => {
        const elt = event.detail && event.detail.elt;
        const form = elt && elt.closest ? elt.closest('form') : null;
        const widget = form ? form.querySelector('altcha-widget') : null;
        if (!widget || !widget.verify || (elt !== form && !elt.matches('button:not([type="button"]):not([type="reset"]), input[type="submit"]'))) return;
        const state = widget.getState();
        if (state === 'verified') return;
        event.preventDefault();
        if (state === 'verifying') return;
        const done = (change) => {
            const now = change.detail && change.detail.state;
            if (now === 'verifying') return;
            widget.removeEventListener('statechange', done);
            if (now === 'verified') event.detail.issueRequest(true);
        };
        widget.addEventListener('statechange', done);
        // The click that raised the request still bubbles, and the widget hides on any click outside itself: it is shown once that click is over
        setTimeout(() => widget.show(), 0);
        widget.verify();
    });
    // A proof is accepted once, and the form htmx answered stays on the page: the widget goes back to unsolved for the next send
    document.addEventListener('htmx:afterRequest', (event) => {
        const elt = event.detail && event.detail.elt;
        const form = elt && elt.closest ? elt.closest('form') : null;
        const widget = form ? form.querySelector('altcha-widget') : null;
        if (!widget || !widget.reset || widget.getState() !== 'verified') return;
        widget.reset();
        if (widget.getConfiguration().display === 'floating') widget.hide();
    });
}
