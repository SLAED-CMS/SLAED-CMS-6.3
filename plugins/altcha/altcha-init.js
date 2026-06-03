// ALTCHA widget bootstrap and localization.
// Targets ALTCHA widget v3.x (verified against 3.0.11). Version-sensitive contract:
//   - widget challenge source is the `challenge` attribute (was `challengeurl` in v2)
//   - footer/logo are hidden via the `configuration` attribute, not `hidefooter`/`hidelogo`
//   - custom strings are registered through the internal `globalThis.$altcha.i18n` global,
//     because this build ships English only and ignores a `strings` attribute
// Strings arrive as an inert JSON island (no inline executable script, CSP-safe). The import
// runs before the widget's first render so the translation is applied immediately.
// Best-effort: any failure degrades to the built-in English strings; the widget keeps working.
import './altcha.min.js';

const island = document.querySelector('script.sl-altcha-i18n');
if (island && globalThis.$altcha && globalThis.$altcha.i18n) {
    try {
        globalThis.$altcha.i18n.set(island.getAttribute('data-lang'), JSON.parse(island.textContent));
    } catch (e) {
        // Malformed payload or changed i18n API: fall back to the built-in English strings
    }
}
