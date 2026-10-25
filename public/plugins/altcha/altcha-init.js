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
}
