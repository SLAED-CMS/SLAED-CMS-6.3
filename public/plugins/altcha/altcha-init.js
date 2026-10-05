// ALTCHA widget bootstrap, worker and localization.
// Targets ALTCHA widget v3.x (verified against 3.0.11). Uses the *external* build so the
// proof-of-work runs in a self-hosted same-origin Worker instead of a blob: Worker. This keeps
// the captcha working under a strict Content-Security-Policy (default-src 'self') without any
// per-site CSP change — important for a CMS shipped to many installations.
// Version-sensitive contract:
//   - widget challenge source is the `challenge` attribute (was `challengeurl` in v2)
//   - footer/logo are hidden via the `configuration` attribute, not `hidefooter`/`hidelogo`
//   - the PoW worker is registered through `$altcha.algorithms.set(algorithm, factory)`
//   - custom strings are registered through `$altcha.i18n` (this build ships English only)
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
