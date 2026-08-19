<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The machine-readable theme contract. `.rules/theme.md` is the prose; this file is the authority.
# Every axis, ladder step, allowlist entry, categorical set, declared component and contrast pair
# a tool reads lives here, because `.rules/`, `.agents/` and `.claude/` are not tracked by git.

return [
    'frozen' => false,
    'marker' => '/* --- end tokens --- */',
    'prefix' => '--sl-',

    # Theme packages the audit walks. `api` is the file holding the :root block above the marker
    'themes' => [
        'admin' => [
            'root' => 'templates/admin',
            'api' => 'templates/admin/assets/css/base.css',
            'css' => ['templates/admin/assets/css/base.css', 'templates/admin/assets/css/theme.css', 'templates/admin/assets/editors/toastui/skin.css'],
            'kind' => 'admin',
        ],
        'lite' => [
            'root' => 'templates/lite',
            'api' => 'templates/lite/assets/css/base.css',
            'css' => ['templates/lite/assets/css/base.css', 'templates/lite/assets/css/theme.css', 'templates/lite/assets/editors/toastui/skin.css'],
            'kind' => 'frontend',
        ],
    ],

    # Semantic axes. Closed list: a new axis is a contract change, never a local decision
    'axes' => [
        'color' => [
            'prefix' => '',
            'roles' => ['bg', 'surface', 'border', 'text', 'primary', 'success', 'warning', 'danger', 'accent', 'info', 'on-solid', 'scrim'],
            'steps' => ['subtle', 'muted', '', 'strong', 'inverse', 'sunken', 'raised'],
        ],
        'space' => ['prefix' => 'space', 'roles' => ['1', '2', '3', '4', '5', '6', '7', '8']],
        'radius' => ['prefix' => 'radius', 'roles' => ['1', '2', '3', 'pill', 'circle']],
        'font' => ['prefix' => 'font', 'roles' => ['display', 'h1', 'h2', 'h3', 'h4', 'body', 'small', 'micro']],
        'face' => ['prefix' => 'face', 'roles' => ['body', 'display', 'mono', 'quote']],
        'line' => ['prefix' => 'line', 'roles' => ['tight', 'normal', 'loose']],
        'weight' => ['prefix' => 'weight', 'roles' => ['normal', 'medium', 'semibold', 'bold']],
        'track' => ['prefix' => 'track', 'roles' => ['tight', 'normal', 'wide']],
        'shadow' => ['prefix' => 'shadow', 'roles' => ['xs', 'raised', 'float', 'overlay', 'inset', 'focus', 'color']],
        'grad' => ['prefix' => 'grad', 'roles' => ['line', 'gloss', 'stripe', 'progress-1', 'progress-2', 'progress-3', 'progress-4', 'progress-5']],
        'time' => ['prefix' => 'time', 'roles' => ['fast', 'base', 'slow']],
        'ease' => ['prefix' => 'ease', 'roles' => ['out', 'in-out']],
        # `raised` is the layer measurement asked for: a component whose decorative floor opens a local stack needs
        # a name for what sits on that floor, and every other role is a layer that leaves the flow
        'z' => ['prefix' => 'z', 'roles' => ['base', 'raised', 'dropdown', 'sticky', 'overlay', 'modal', 'popover', 'toast']],
        'size' => ['prefix' => 'size', 'roles' => ['control', 'chip', 'tile', 'avatar', 'icon-xs', 'icon-sm', 'icon-md', 'icon-lg']],
        'fade' => ['prefix' => 'fade', 'roles' => ['subtle', 'muted', 'disabled']],
        'layout' => ['prefix' => 'layout', 'roles' => ['container', 'sidebar', 'gutter', 'grid']],
        'bp' => ['prefix' => 'bp', 'roles' => ['sm', 'md', 'lg', 'xl']],
    ],

    # Ladders. A value either sits on a step or carries an allowlist entry with a written reason
    'ladders' => [
        'space' => [
            'steps' => [2, 4, 8, 10, 12, 16, 20, 24],
            'unit' => 'px',
            'tokens' => ['--sl-space-1', '--sl-space-2', '--sl-space-3', '--sl-space-4', '--sl-space-5', '--sl-space-6', '--sl-space-7', '--sl-space-8'],
        ],
        'font-size' => [
            'steps' => [10, 12, 14, 16, 18, 20, 24, 32],
            'unit' => 'px',
            'tokens' => ['--sl-font-micro', '--sl-font-small', '--sl-font-body', '--sl-font-h4', '--sl-font-h3', '--sl-font-h2', '--sl-font-h1', '--sl-font-display'],
        ],
        'line-height' => ['steps' => [1.2, 1.45, 1.6], 'unit' => '', 'tokens' => ['--sl-line-tight', '--sl-line-normal', '--sl-line-loose']],
        'font-weight' => ['steps' => [400, 500, 600, 700], 'unit' => '', 'tokens' => ['--sl-weight-normal', '--sl-weight-medium', '--sl-weight-semibold', '--sl-weight-bold']],
        'border-radius' => ['steps' => [4, 8, 12], 'unit' => 'px', 'tokens' => ['--sl-radius-1', '--sl-radius-2', '--sl-radius-3']],
        'transition' => ['steps' => [0.15, 0.2, 0.35], 'unit' => 's', 'tokens' => ['--sl-time-fast', '--sl-time-base', '--sl-time-slow']],
        'opacity' => ['steps' => [0.8, 0.55, 0.45], 'unit' => '', 'tokens' => ['--sl-fade-subtle', '--sl-fade-muted', '--sl-fade-disabled']],
        'breakpoint' => ['steps' => [560, 768, 900, 1200], 'unit' => 'px', 'tokens' => ['--sl-bp-sm', '--sl-bp-md', '--sl-bp-lg', '--sl-bp-xl']],
    ],

    # Easing keeps two spellings only: the keyword and one curve
    'easing' => ['ease', 'cubic-bezier(0.4, 0, 0.2, 1)'],

    # Values a ladder never covers. Growing this list requires the reason written beside the entry
    'allowlist' => [
        'properties' => [
            'grid-template' => 'a track list is structure, not a visual decision',
            'grid-template-columns' => 'a track list is structure, not a visual decision',
            'grid-template-rows' => 'a track list is structure, not a visual decision',
            'grid-template-areas' => 'a named area map is structure',
            'grid-area' => 'names a cell, carries no visual value',
            'grid-column' => 'names a track span, carries no visual value',
            'grid-row' => 'names a track span, carries no visual value',
            'flex' => 'grow, shrink and basis are layout arithmetic',
            'flex-basis' => 'layout arithmetic',
            'flex-grow' => 'layout arithmetic',
            'flex-shrink' => 'layout arithmetic',
            'order' => 'source order override, carries no visual value',
            'aspect-ratio' => 'a proportion, not a size a theme repaints',
            'content' => 'a string or counter, owned by the rule that shows it',
            'counter-reset' => 'a counter name and seed',
            'counter-increment' => 'a counter name and step',
            'top' => 'placement is structure entirely',
            'right' => 'placement is structure entirely',
            'bottom' => 'placement is structure entirely',
            'left' => 'placement is structure entirely',
            'inset' => 'placement is structure entirely',
            'background-position' => 'places an image inside its box',
            'background-size' => 'scales an image inside its box',
            'transform' => 'a geometric operation, not a repaintable value',
            'transform-origin' => 'a geometric anchor',
            'stroke-dasharray' => 'traces one path, measured against that path',
            'stroke-dashoffset' => 'traces one path, measured against that path',
            'will-change' => 'names a property, holds no value',
            'clip-path' => 'a geometric mask',
        ],
        'values' => [
            '0' => 'the absence of a value is not a decision',
            '1' => 'neutral in opacity and line-height',
            '1px' => 'a hairline is structural; anything thicker is a component decision',
            'solid' => 'a border style keyword',
            '100%' => 'fills its container',
            '100vh' => 'fills the viewport',
            '100vw' => 'fills the viewport',
            'auto' => 'hands the value to layout',
            'none' => 'the absence of a value is not a decision',
            'inherit' => 'defers to the ancestor',
            'transparent' => 'the absence of a colour is not a colour decision',
            'currentcolor' => 'defers to the colour already decided',
            '0.01ms' => 'motion off, not a duration',
        ],
        'shapes' => [
            'circle-radius' => '50% on border-radius makes a circle and is geometry',
            'triangle-border' => 'border: <n>px solid transparent draws a CSS triangle; the width is geometry',
        ],
        'sites' => [],
    ],

    # Sets with no order: a ladder cannot apply, so members are checked for mutual distinguishability
    'categorical' => [
        'chart' => ['members' => ['up', 'down', 'cpu', 'ram'], 'mindiff' => 60],
        'season' => ['members' => ['winter', 'spring', 'summer', 'autumn', 'newyear'], 'mindiff' => 40],
        # The five tones of the level meter: a poll paints its options with them by option number, so no option
        # is more than another and no ladder applies. The gradient of each tone lives on the grad axis
        'progress' => ['members' => ['1', '2', '3', '4', '5'], 'mindiff' => 60],
    ],

    # Component tokens: the prop is closed, the component is open but declared here
    'props' => ['bg', 'border', 'text', 'radius', 'height', 'width', 'pad-x', 'pad-y', 'gap', 'shadow', 'ring', 'dur'],
    'components' => [
        'alert', 'avatar', 'badge', 'btn', 'card', 'check', 'chip', 'code', 'crumb', 'dial',
        'drop', 'editor', 'field', 'forum', 'header', 'knob', 'login', 'menu', 'meter', 'modal',
        'nav', 'pager', 'panel', 'placeholder', 'popover', 'progress', 'pulse', 'quote', 'spin',
        'switch', 'table', 'tab', 'thumb', 'toast', 'tooltip', 'vote',
    ],

    # Written from outside CSS by a template or a script, read only by CSS. Never API
    'data' => [
        '--sl-d-arrow' => 'popover arrow offset, plugins/system/slaed.js',
        '--sl-d-bots' => 'session donut bot share, templates/lite/partials/session-summary.html',
        '--sl-d-count' => 'speed dial item count, plugins/editors/toastui/assets/editor-upload.js',
        '--sl-d-depth' => 'comment nesting depth, templates/lite/fragments/comment.html',
        '--sl-d-distance' => 'profile feed scroll distance, plugins/system/slaed.js',
        '--sl-d-duration' => 'profile feed scroll duration, plugins/system/slaed.js',
        '--sl-d-level' => 'profile completion percentage, templates/lite/partials/account-home.html and account-profile.html',
        '--sl-d-members' => 'session donut member share, templates/lite/partials/session-summary.html',
        '--sl-d-ring' => 'profile ring colour, templates/lite/partials/account-home.html and account-profile.html',
        '--sl-d-user' => 'user group colour, templates/lite/partials/block-user-info.html',
        '--sl-d-usrlevel' => 'user group progress, templates/lite/partials/block-user-info.html',
    ],

    # Names read by JavaScript through getComputedStyle. Renaming one edits the script in the same commit
    'js' => [
        '--sl-size-chip' => 'plugins/system/slaed.js, drives speed dial geometry',
    ],

    # Files outside the theme packages that declare or read theme token names
    'places' => [
        'templates/lite/fragments/comment.html',
        'templates/lite/partials/account-home.html',
        'templates/lite/partials/account-profile.html',
        'templates/lite/partials/block-user-info.html',
        'templates/lite/partials/session-summary.html',
        'admin/modules/monitor.php',
        'admin/modules/admins.php',
        'plugins/system/slaed.js',
        'plugins/editors/toastui/assets/editor-upload.js',
        'tests/Unit/EditorWindowTest.php',
        'error.html',
    ],

    # Colour ramp, derived by `--ramp` from the real distribution and never invented.
    # Each step carries a role, which is what lets the ramp reverse for dark while the roles hold
    'ramp' => [
        'saturation' => 30,
        # HSL saturation is chroma divided by what the lightness still allows, so a near-white carrying
        # three points of chroma reads 100 and would be filed as a colour it is not. Chroma decides beside the ratio
        'chroma' => 12,
        'roles' => [
            '50' => 'background',
            '100' => 'background',
            '200' => 'hovered background',
            '300' => 'hovered background',
            '400' => 'border',
            '500' => 'border',
            '600' => 'solid fill',
            '700' => 'solid fill',
            '800' => 'text',
            '900' => 'text',
        ],
        # Lightness per step as `--ramp` found it in each theme today, rank-mapped onto the ten roles.
        # A family with gaps has fewer colours than steps and gains them as the semantic tokens land;
        # a family whose steps sit at one lightness is where two values serve one role and collapse
        'families' => [
            'admin' => [
                'blue' => ['50' => 61.6, '100' => 56.5, '200' => 49.3, '300' => 42.0, '400' => 42.0, '500' => 42.0, '600' => 42.0, '700' => 39.0, '800' => 26.9, '900' => 26.3],
                'gray' => ['50' => 100.0, '100' => 98.7, '200' => 96.5, '300' => 95.9, '400' => 95.0, '500' => 92.9, '600' => 91.3, '700' => 70.3, '800' => 33.2, '900' => 11.0],
                'green' => ['50' => 56.3, '200' => 46.7, '300' => 46.5, '500' => 46.5, '600' => 46.5, '800' => 36.7, '900' => 36.3],
                'orange' => ['50' => 54.1, '200' => 54.1, '500' => 54.1, '700' => 43.7, '900' => 40.4],
                'red' => ['50' => 62.0, '200' => 59.2, '300' => 59.2, '500' => 59.2, '600' => 52.4, '800' => 49.4, '900' => 41.8],
                'teal' => ['50' => 70.6, '300' => 70.6, '600' => 46.7, '900' => 32.4],
                'violet' => ['50' => 62.0, '300' => 47.3, '600' => 46.9, '900' => 41.8],
            ],
            'lite' => [
                'blue' => ['50' => 72.7, '100' => 67.4, '200' => 63.7, '300' => 61.0, '400' => 57.6, '500' => 56.0, '600' => 53.4, '700' => 50.6, '800' => 44.5, '900' => 20.2],
                'gray' => ['50' => 100.0, '100' => 98.2, '200' => 96.3, '300' => 92.4, '400' => 88.4, '500' => 72.7, '600' => 39.8, '700' => 20.6, '800' => 10.1, '900' => 0.0],
                'green' => ['50' => 65.9, '100' => 65.9, '200' => 64.3, '300' => 62.5, '400' => 56.3, '500' => 46.7, '600' => 36.3, '700' => 36.3, '800' => 36.3, '900' => 36.3],
                'orange' => ['50' => 57.1, '200' => 57.1, '400' => 54.1, '500' => 54.1, '700' => 54.1, '900' => 54.1],
                'red' => ['50' => 72.7, '100' => 62.0, '200' => 61.6, '300' => 60.2, '500' => 52.4, '600' => 41.8, '700' => 41.8, '800' => 41.8, '900' => 41.8],
                'teal' => ['50' => 46.7, '500' => 39.4, '900' => 32.4],
                'violet' => ['50' => 62.0, '200' => 46.9, '500' => 41.8, '700' => 41.8, '900' => 41.8],
            ],
        ],
    ],

    # Generated by tools/ui-shots.mjs from pairs that actually meet on screen, checked offline here
    'contrast' => [
        'aa' => 4.5,
        'aalarge' => 3.0,
        'large' => ['size' => 18.66, 'boldsize' => 14.0],
        'pairs' => [],
    ],

    # `unmet` is the other half of `dead`: a name a theme reads and declares nowhere, which CSS answers by
    # dropping the declaration with no error and no warning. A read carrying a fallback is met by that fallback,
    # and a name in `data` is met by whatever writes it from outside CSS.
    # Counts the ratchet holds: none of these may grow against the stored baseline.
    # `tokens` and `single` are recorded but not ratcheted, because extracting an axis into the API block
    # raises both on purpose, and a component token that one component reads is correct at one use
    'ratchet' => ['count', 'bare', 'dup', 'names', 'dead', 'alias', 'unsat', 'unmet', 'scoped', 'classes', 'important', 'contrast', 'clash'],

    # Rule bodies repeated under selectors that do not belong together. Batch 8 fills this
    'duplicates' => [],

    # PHP the markup scan skips, each with the reason it is not a leftover
    'markup' => [
        'exclude' => [
            '/lang/' => 'a language file defines translated sentences, one file per locale; markup inside a sentence is part of that text and moves '
                .'with the translation, not with a fragment. This is the general case of the RSS feed document the plan already excluded',
            'config/filetype.php' => 'attachment templates edited by the administrator through admin/modules/uploads.php: user data that happens to be markup',
        ],
    ],
];
