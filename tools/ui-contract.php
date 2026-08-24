<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The machine-readable theme contract. `.rules/theme.md` is the prose; this file is the authority.
# Every axis, ladder step, allowlist entry, categorical set, declared component and contrast pair
# a tool reads lives here, because `.rules/`, `.agents/` and `.claude/` are not tracked by git.

return [
    # The API is frozen: batch 8 settled the last of canon, so from here a theme package may gain a role but may never lose
    # or rename one. What holds it is the roster under `api` in tools/ui-audit-baseline.json, which the tool compares every
    # run and which --store refuses to write while a name has gone missing - a freeze nothing checks is a sentence in a file
    'frozen' => true,
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
            # `tint` is a translucent wash of the brand colour over whatever is behind it - a selection, a drop target,
            # a pointed row. It is not `primary-subtle`, which is opaque and hides what it covers, and it inverts by itself
            'roles' => ['bg', 'surface', 'border', 'text', 'primary', 'success', 'warning', 'danger', 'accent', 'info', 'on-solid', 'scrim', 'tint'],
            'steps' => ['subtle', 'muted', '', 'strong', 'inverse', 'sunken', 'raised'],
        ],
        # Steps 9 to 11 are the second rhythm a page carries: the gap between its sections, which sits above every
        # component gap. Only a frontend theme reaches that far, so admin declares none of the three
        'space' => ['prefix' => 'space', 'roles' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11']],
        'radius' => ['prefix' => 'radius', 'roles' => ['1', '2', '3', 'pill', 'circle']],
        # `hero` is the step above every heading: the largest type a page carries - a slider headline, a dashboard number -
        # and it exists because folding those onto `display` would size a hero like an h1 and lose the rank
        'font' => ['prefix' => 'font', 'roles' => ['hero', 'display', 'h1', 'h2', 'h3', 'h4', 'body', 'small', 'micro']],
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
        'size' => ['prefix' => 'size', 'roles' => ['control', 'chip', 'tile', 'avatar', 'icon-xs', 'icon-sm', 'icon-md', 'icon-lg', 'icon-xl']],
        'fade' => ['prefix' => 'fade', 'roles' => ['subtle', 'muted', 'disabled']],
        'layout' => ['prefix' => 'layout', 'roles' => ['container', 'sidebar', 'gutter', 'grid']],
        'bp' => ['prefix' => 'bp', 'roles' => ['sm', 'md', 'lg', 'xl']],
    ],

    # Ladders. A value either sits on a step or carries an allowlist entry with a written reason
    'ladders' => [
        'space' => [
            'steps' => [2, 4, 8, 10, 12, 16, 20, 24, 32, 40, 48],
            'unit' => 'px',
            'tokens' => [
                '--sl-space-1', '--sl-space-2', '--sl-space-3', '--sl-space-4', '--sl-space-5', '--sl-space-6',
                '--sl-space-7', '--sl-space-8', '--sl-space-9', '--sl-space-10', '--sl-space-11',
            ],
        ],
        # The hero step reads 38 and not 48 on purpose: it is the version number of the dashboard and the slider headline,
        # and at 48 the number crowded the pane it shares with its label. Step count and role names are unchanged, which is
        # what the ladder law asks of a theme that needs a different value
        'font-size' => [
            'steps' => [10, 12, 14, 16, 18, 20, 24, 32, 38],
            'unit' => 'px',
            'tokens' => [
                '--sl-font-micro', '--sl-font-small', '--sl-font-body', '--sl-font-h4', '--sl-font-h3',
                '--sl-font-h2', '--sl-font-h1', '--sl-font-display', '--sl-font-hero',
            ],
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
            # The figure aligns a glyph against its own baseline. It is measured against the font, not against the page, and a
            # theme that repaints the palette never moves it; a ladder step here would fight the typeface instead of the design
            'vertical-align' => 'an optical offset measured against the font, not a rhythm step',
            # A size hint for content-visibility: it estimates the box of what the browser has not rendered yet and paints nothing.
            # Wrong, it costs a scroll jump; repainted by a theme, it changes no pixel
            'contain-intrinsic-size' => 'a rendering hint for skipped content, never a painted value',
        ],
        'values' => [
            '0' => 'the absence of a value is not a decision',
            '1' => 'neutral in opacity and line-height',
            '1px' => 'a hairline is structural; anything thicker is a component decision',
            '-1px' => 'a hairline pulled back by its own width, so two borders share one line; the optical counterpart of 1px and never a rhythm step',
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
            # A constant rate is the definition of a continuous animation: a spinner or a marquee that eases stutters at every
            # cycle boundary, because the curve restarts where it ended. It is the absence of a curve, not one curve among many
            'linear' => 'a constant rate, which is what a looping animation needs instead of a curve',
            '-9999px' => 'text pushed off the canvas so an icon can stand where it was: a hiding technique, not a typographic decision',
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
    # `min-*` and `max-*` are the same decision as the size beside them: a component that names its height and not the
    # floor it may not fall below leaves half its geometry outside the API. `mix` is how much of a tone a tint carries,
    # which cannot be hoisted into a root token when the tone itself is scoped. `ease` is the curve half of `dur`
    'props' => [
        'bg', 'border', 'text', 'radius', 'height', 'width', 'min-width', 'max-width', 'min-height', 'max-height',
        'pad-x', 'pad-y', 'gap', 'shadow', 'ring', 'dur', 'ease', 'mix',
    ],
    # The eleven `col-*` entries are the column widths of a fixed admin list table: the track list of `table-layout: fixed`,
    # which CSS spells as one width per cell instead of one `grid-template-columns`, and each is a figure an author retunes
    'components' => [
        'alert', 'arrow', 'aside', 'ava', 'avatar', 'badge', 'band', 'bar', 'brand', 'btn', 'btn-mini', 'bullet', 'cab-act', 'cab-msg', 'cab-ring',
        'calc', 'card', 'changelog', 'changelog-body', 'changelog-date',
        'changelog-files', 'changelog-stat', 'check', 'check-tick', 'chip', 'chip-tint', 'code', 'col-actions', 'col-amount', 'col-author',
        'col-check', 'col-count', 'col-date', 'col-form', 'col-id', 'col-ip', 'col-lang', 'col-last', 'col-module', 'col-num', 'col-sent',
        'col-status', 'com', 'com-arrow', 'com-ava', 'com-item', 'count', 'crumb', 'crumb-bar', 'demo', 'dial', 'donut', 'drift', 'drift-dot',
        'drop', 'edge', 'editor', 'editor-mode', 'editor-tab', 'emoji', 'emoji-full', 'emoji-grid', 'entry', 'f-title', 'fav', 'feedback',
        'field', 'flash', 'flash-bar', 'fm-as',
        'fm-bar', 'fm-body', 'fm-busy', 'fm-drop', 'fm-edit', 'fm-empty', 'fm-field', 'fm-filter', 'fm-kind', 'fm-mode',
        'fm-panel', 'fm-pick', 'fm-preview', 'fm-props', 'fm-quota', 'fm-row', 'fm-search', 'fm-sep', 'fm-split', 'fm-thumb',
        'fm-tile', 'footer', 'form', 'form-label', 'forum', 'fp', 'fp-ava', 'fresh', 'fresh-day', 'fresh-days', 'fresh-month',
        'fresh-now', 'fresh-week', 'graph', 'header', 'hub-head', 'hub-row',
        'ico', 'idea', 'info-row', 'invoice', 'invoice-logo', 'item', 'knob', 'lang', 'led', 'letter', 'live-dot', 'loading',
        'loading-dot', 'login', 'login-drop', 'login-footer', 'login-header', 'logo', 'madein', 'marquee', 'menu', 'meta',
        'meter', 'modal', 'modal-act', 'modal-btn', 'mode', 'module-head', 'monitor-table', 'move', 'msg', 'msg-brand', 'nav',
        'pager', 'pager-dot', 'pager-item', 'panel', 'panel-feed', 'placeholder', 'pmf-ava', 'pmf-blank', 'pmf-chip', 'pmf-day', 'pmf-filter',
        'pmf-head', 'pmf-mate', 'pmf-meta', 'pmf-pane', 'pmf-slot', 'pmf-text', 'pmf-who',
        'pnum', 'pnum-arrow', 'popover', 'preview', 'profile-ava', 'profile-dot', 'progress', 'proof', 'pulse', 'qr', 'quote', 'radio',
        'rail', 'rank', 'ratings', 'ring', 'row', 'scroll', 'search', 'search-filter', 'search-order', 'search-sort', 'select', 'set', 'sep',
        'session', 'shot', 'shot-side', 'site', 'site-img', 'skel', 'statx',
        'skel-row', 'skel-tile', 'slide', 'slide-cont', 'sort', 'spark', 'spin', 'sublist', 'sublist-two', 'switch', 'switch-knob',
        't-icon', 'table', 'tab', 'thumb', 'tile', 'toast',
        'toolbar', 'toolbar-row', 'tooltip', 'topbar', 'touch', 'tree', 'veil', 'view', 'vote', 'wordmark', 'wordmark-img', 'wrap',
    ],

    # Written from outside CSS by a template or a script, read only by CSS. Never API
    'data' => [
        '--sl-d-arrow' => 'popover arrow offset, plugins/system/slaed.js',
        '--sl-d-bots' => 'session donut bot share, templates/lite/partials/session-summary.html',
        '--sl-d-count' => 'speed dial item count, plugins/editors/toastui/assets/editor-upload.js',
        '--sl-d-depth' => 'comment nesting depth, templates/lite/fragments/comment.html',
        '--sl-d-distance' => 'profile feed scroll distance, plugins/system/slaed.js',
        '--sl-d-duration' => 'profile feed scroll duration, plugins/system/slaed.js',
        '--sl-d-float-left' => 'floating panel viewport left, plugins/system/slaed.js',
        '--sl-d-float-top' => 'floating panel viewport top, plugins/system/slaed.js',
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

    # Rule bodies repeated under selectors that do not belong together, each with the reason it is not merged.
    # The key is the group's selectors sorted and joined by ', ', which is what checkDupBlocks() compares
    'duplicates' => [
        # A utility that puts a gap after any inline element, beside the input of one form field: merging would
        # move the field's definition into the utility block, where nobody editing the field would look
        '.sl-div-field label input, .sl-inline-gap',
        # The head of the monitor and the count badge of a sidebar block: two components that share three
        # declarations by coincidence and nothing else
        '.sl-monitor-head-left, .sl-wrapper.sl-admin-shell .sl-admin-sidebar .sl-block-sidebar-count-label',
        # The tab strip of the icon picker and the filter row of the upload panel, in two different files
        '.sl-icon-modal-tabs, .sl-toastui-upload .sl-fm-filters',
        # The name cell of the file manager and a field row of the language editor
        '.sl-fm-name, .sl-lang-edit-row .sl-div-field',
        # The paragraph reset of base.css and the list item of the markdown block: one is the element default
        # every page inherits, the other a rule scoped to rendered markdown, and they meet at one step by chance
        '.sl-markdown li, p',
        # Two site blocks and two cells of the profile hub that lite hides on a narrow screen, beside the rail parts
        # the editor window hides at the same width. They met when the skin took the shared breakpoint ladder and the
        # hub's own 760 moved onto 768; one set is page furniture, the other a dialog the page never sees
        '#block-idea, #block-feedback, .sl-profile-hub-row > span:nth-of-type(1), .sl-profile-hub-row-head, '
            .'.sl-toastui-upload .sl-fm-rail-cap, .sl-toastui-upload .sl-fm-rail-sep, .sl-toastui-upload .sl-fm-rail-foot, '
            .'.sl-toastui-upload .sl-fm-rail-item small, .sl-toastui-upload .sl-fm-rows-head span:nth-child(4), '
            .'.sl-toastui-upload .sl-fm-row span:nth-child(4), .sl-toastui-upload .sl-fm-rows-head span:nth-child(5), '
            .'.sl-toastui-upload .sl-fm-row span:nth-child(5)',
        # Three hover states and one static note that all fade to the same step. The note is not a hover, so merging
        # would put a resting style inside a list of pointer states and hide it from whoever edits either one
        '.sl-block-sidebar h3:hover, .sl-but:hover, .sl-but-blue:hover, .sl-but-red:hover, .sl-but-green:hover, .sl-but-foot:hover, .sl-but-back:hover, .sl-dashboard-panel-head:hover, .sl-session-note',
        # A body that is one declaration reaching one ladder step is need and not repetition, the same way `display: flex`
        # under 122 flex containers is. These three met when lite's type sizes landed on the ladder: an element default
        # beside three small labels, three unrelated places reaching the smallest step, and four glyphs at one icon step.
        # Merging any of them would file an element reset or one component's glyph inside a list that names nothing
        '.sl-chip.sl-topic-post .bi, .sl-chip.sl-topic-edit .bi, .sl-toastui-upload .sl-fm-drop small, .sl-toastui-upload .sl-fm-view .sl-pager-info, small',
        '.sl-block-pm a > .bi:last-child, .sl-cab-nav .sl-cab-act b, .sl-pmf-mate small',
        '.bi-stars, .sl-cab-nav .sl-cab-act .sl-cat-ico .bi, .sl-msg-search .sl-home-link .bi, .sl-pmf-slot-more i',
        # The same shape in lite: three hovers in three unrelated components and one resting note, which met when
        # 0.9, 0.8 and 0.75 folded onto one step. The note is not a hover, and the three hovers share nothing else
        '.bx-pager-item a:hover::after, .sl-forum-last:hover, .sl-rate-sites:hover, .sl-session-note',
        # The element default every page inherits, a utility that bolds any inline run, and one component label. They met
        # when `bold` and `700` became one name; merging would move the element reset into a utility block
        '.sl-label, .sl-text-bold, strong, b',
        # The hovered compact button and the page the pager is standing on: both are painted in the brand colour and both
        # take the text that reads on it, which is one role and not one component. Merging would file the pager's current
        # page inside a button's hover block, where nobody editing either would look for it
        '.sl-but-mini:hover, .sl-but-mini:focus-visible, .sl-pager-pages .sl-pnum-cur',
        # A chip that carries a checked radio and a filter button that is pressed: two controls of the upload panel that both
        # say "on" in the same tint. Merging would put the chip's definition inside the filter's block, where nobody looking
        # at either one would find it, and the two drift apart the moment one of them stops being a tint
        '.sl-toastui-upload .sl-fm-as label:has(input:checked), .sl-toastui-upload .sl-fm-filter[aria-pressed="true"]',
        # A status tone has one colour and many marks, and the moment every mark stopped reading a tint step and took the
        # base tone of its family, the marks of one status met each other. A hidden-text frame, a hot topic, a moderated
        # topic and a category tone are four components, not one: a selector list holding them would name nothing, and each
        # would lose the block a reader opens to find it. Two of the three also reach across a file the list cannot cross
        '.sl-fieldset-form-legend-success, .sl-text-success, .sl-profile-proof:nth-child(2) i, '
            .'.sl-radio-group.sl-radio-switch:has(input[value="1"]:checked) .sl-radio:has(input:checked), '
            .'.sl-session-line[data-sl-audience="users"] > .bi, .sl-toastui-upload .sl-fm-job.sl-is-done > .bi, .sl-topic-moderated .bi',
        '.sl-fieldset-form-legend-danger, .sl-text-danger, .sl-hide::before, .sl-hide::after, .sl-profile-proof.sl-is-warn i, '
            .'.sl-profile-wide.sl-is-warn h3 i, .sl-toastui-upload .sl-fm-empty.sl-is-fail .bi, .sl-toastui-upload .sl-fm-job.sl-is-fail > .bi',
        '.sl-pmf-focus > summary .bi-lightning-charge, .sl-profile-row-private > i, .sl-session-line[data-sl-audience="bots"] > .bi, '
            .'.sl-topic-hot .bi, .sl-topic-admin .bi',
        # A category tone and the tone a kept message wears are one colour under two names on purpose: the first is a
        # category of the catalogue, the second a state of a message, and they are free to part without touching each other
        '.sl-cat-tone-2, .sl-pmf-slot-keep',
        # Five groups the colour fold closed over: the changelog island, the two service tones of the social icons and the
        # colour a heading link carried all reached a role that other places already read, and a page ground meeting an editor
        # ground is a coincidence of one role and not one component. Four of the five cross a file boundary that no selector
        # list can cross at all, and the fifth would file a changelog row inside a code table
        '.sl-changelog-commit:hover, .sl-code-row-odd, .toastui-editor-contents pre, .toastui-editor-context-menu .menu-item:hover, '
            .'.toastui-editor-popup-add-heading ul li:hover',
        '.sl-changelog-commit-alt, .toastui-editor-popup-add-table .toastui-editor-table-cell.header, a.sl-profile-hub-row:hover',
        '.sl-i-vk:hover, .toastui-editor-defaultUI .toastui-editor-ok-button:hover',
        '.sl-urating a.sl-star:has(~ a.sl-star:hover) .bi, .sl-urating a.sl-star:hover .bi, .toastui-editor-contents a:hover',
        '.sl-login-top--head > li > a:hover, .sl-login-top--head > li > a:focus-visible, '
            .'.sl-login-top--head > li > .sl-login-toggle:hover, .sl-login-top--head > li > .sl-login-toggle:focus-visible, '
            .'.sl-profile-feed .sl-tabs-link:hover, .sl-profile-hub-row b, .sl-table-head th a, .toastui-editor-contents h1, '
            .'.toastui-editor-contents h2, .toastui-editor-contents h3, .toastui-editor-contents h4, .toastui-editor-contents h5, '
            .'.toastui-editor-contents h6',
        # Three chips meeting three freshness and liveness marks, which is what one wash of one's own colour costs: once every
        # tinted pill in the theme reads the same ground, a tone class and a state chip carrying the same tone say the same two
        # things. They are not one component - a chip is a label the page prints, a freshness mark is a reading of the clock -
        # and merging either pair would file one component's tone inside the other's block
        '.sl-chip-info, a.sl-chip-info, .sl-fresh-week',
        '.sl-chip-success, a.sl-chip-success, .sl-live-chip.sl-is-paused .sl-live-act',
        '.sl-chip-warn, a.sl-chip-warn, .sl-fresh-days',
        # The faint text of a rail item nobody may click, beside the syntax marks of the markdown source. One is a state of a
        # control, the other is how a language is written down; they share a tone and nothing else
        '.sl-toastui-upload .sl-fm-rail-item[disabled] b, .sl-toastui-upload .sl-fm-rail-item[disabled] .bi, '
            .'.toastui-editor-md-delimiter, .toastui-editor-md-thematic-break, .toastui-editor-md-link, '
            .'.toastui-editor-md-table, .toastui-editor-md-block-quote',
        # The same meeting in lite, where the faint tone is already shared by a footer menu, the glyphs of a read forum row
        # and a nested list. None of them is the markdown source, and a selector list holding all four would name nothing
        '.sl-forum-old .bi, .sl-topic-old .bi, .sl-topic-popular-old .bi, .sl-topic-closed .bi, '
            .'.sl-forum-closed .bi, .sl-list-item > li ul, .sl-list-item > li ul a, '
            .'.sl-toastui-upload .sl-fm-rail-item[disabled] b, .sl-toastui-upload .sl-fm-rail-item[disabled] .bi, '
            .'.toastui-editor-md-delimiter, .toastui-editor-md-thematic-break, .toastui-editor-md-link, '
            .'.toastui-editor-md-table, .toastui-editor-md-block-quote',
        # the item of a tab strip beside the definition list inside a hint: two components that both open as a block with no margin and
        # share nothing else
        '.sl-tabs-item, .sl-tip dl',
        # the link list of an admin block beside the count list of a sidebar block: two lists of a sidebar that happen to take the same
        # track and gap
        '.sl-admin-block-links, .sl-block-sidebar-count-list',
        # the same pair one level down, on the link each list holds
        '.sl-admin-block-link a, .sl-block-sidebar-count-label a',
        # the control of a live chip beside the thumbnail of a file row: two buttons stripped of their browser chrome, which is need and not
        # one component
        '.sl-live-act, button.sl-fm-thumb',
        # the wordmark of the login card beside the flag of a session line: two boxes that centre one child, in two screens that never meet
        '.sl-admin-login-card .sl-admin-brand, .sl-session-icon .sl-geo-flag',
        # the primary action of the file manager beside a pressed filter of its bar: one is a rank, the other a state, and they part the
        # moment either stops being solid
        '.sl-but-mini.sl-fm-main, .sl-fm-bar .sl-but-mini[aria-pressed="true"]',
        # the open node of the file tree beside the picture kind of a thumbnail: a place the window is standing in and a kind of file, which
        # is a coincidence of one tint
        '.sl-fm-node[aria-current="true"], .sl-fm-thumb-img',
        # a selected tile beside a drop target under a dragged file: two states of the same panel that mean different things and are free to
        # part
        '.sl-fm-cell[aria-selected="true"] .sl-fm-tile, .sl-fm-drop.sl-drag-over',
        # the caption of the drop zone beside the caption of an empty list: two states of the panel, one inviting and one reporting
        '.sl-toastui-upload .sl-fm-drop b, .sl-toastui-upload .sl-fm-empty b',
        # the note beside a queued file and the term of a property list: two faint labels in two panels of the window
        '.sl-toastui-upload .sl-fm-job-name small, .sl-toastui-upload .sl-fm-props dt, .sl-shot-side .sl-fm-props dt',
        # the caption over the queue beside the room the module has left: two readings of the same panel, and the second is a measurement
        # rather than a heading
        '.sl-toastui-upload .sl-fm-queue-cap, .sl-toastui-upload .sl-fm-quota',
        # the tab standing open in a profile feed beside a letter of the alphabet index under the pointer: a state and a rank, both painted
        # in the brand fill
        '.sl-profile-feed .sl-tabs-link.sl-is-active, a .sl-letter:hover',
        # the byline of an entry, the action row of its meta line and a provider button: three inline rows that carry one icon beside one
        # label
        '.sl-author, .sl-meta-actions, .sl-oauth-but',
        # a link in a sidebar block beside a link in a table cell: two places one title is cut off with an ellipsis, and they live in two
        # layouts
        '.sl-block-content > li > a, .sl-cell-ellipsis > a:last-child',
        # the page a breadcrumb is standing on beside the note under a focused message: one is a position, the other a reading of the clock
        '.sl-crumb-cur, .sl-pmf-focus > summary small',
        # the buttons under a notice beside the chips of a message filter: two wrapping rows in two components
        '.sl-alert-actions, .sl-pmf-chips',
        # the foot of a reply box beside a group of radios: a toolbar and a form control that both wrap their children on one line
        '.sl-pmf-reply-foot, .sl-radio-group',
        # three pictures that fill the box they are given: a preview, the lead image of a list row and the thumbnail of a related entry.
        # Merging would file three components under one selector list that names none of them
        '.sl-image-preview-thumb, .sl-main-img img, .sl-related-img-inner',
        # the two footer lines beside the nested list of a main row: an element default of the footer meeting a list, at the small step in
        # the muted tone
        '.sl-generates, .sl-license, .sl-main-list > li ul',
        # the two parts of a speed dial beside the control of a live chip: three buttons stripped of their browser chrome, which is need
        '.sl-dial-toggle, .sl-dial-item, .sl-live-act',
        # a quotation in running text beside the two cells of a session line: both have to break an unbreakable run, and nothing else joins
        # a quote to a session
        '#content blockquote, .sl-session-name, .sl-session-module',
        # the number column of a cart beside the number column of a file list: two fixed tracks of two tables, each retuned by whoever owns
        # that table
        'td.sl-cart-col-num, th.sl-fl-col-num, td.sl-fl-col-num',
        # the title cell of a cart row beside the heading of a file row: one is inline emphasis, the other a heading, and they are the same
        # size by chance
        '.sl-cart-col-content strong, .sl-fl-col-content h4, .sl-fl-col-content h3',
        # the body of a preview panel beside the info block of a profile: two grids that close their gaps
        '.sl-preview-body, .sl-profile-info',
        # the subject of a commit beside the count of a result set: a title and a figure, in one screen but not in one component
        '.sl-changelog-commit-header strong, .sl-changelog-results-info strong',
        # the name under a user avatar beside the points beside it: a name and a number, which read at one size today and need not tomorrow
        '.sl-block-user-ava p > a, .sl-block-user-ava p > b, .sl-user-points b',
        # the timestamp of a message slot, the note in a profile hub row and the unit beside a points figure: three smallest-step labels in
        # three components
        '.sl-pmf-slot-top time, .sl-profile-hub-row span, .sl-user-points small',
        # the comment a reply link points at beside the focused mode switch: one is a place the page jumped to, the other a control under
        # the keyboard, and both are drawn with the theme's one ring
        '.sl-com-reply-at .sl-com-cont, .sl-mode-but:focus-visible',
        # the round action's own gloss beside the same gloss restated for the search button. The second is there because the search button
        # sets its own background-image at rest, and dropping it would leave that rest value in place under the pointer
        '.sl-circle-action:hover, .sl-circle-action:focus-visible, .sl-search-form button.sl-circle-action:hover, '
            .'.sl-search-form button.sl-circle-action:focus-visible',
        # the work area of the cabinet beside the main column of a profile: two page regions that take one rhythm step of padding
        '.sl-cab-main, .sl-profile-split-main',
        # the label of a cabinet action beside the figure of a profile score: a name and a number
        '.sl-cab-act b, .sl-profile-score > b',
        # the glyph of a cabinet row, of a profile entry and of a profile info line: three components whose icon is centred in the brand
        # tone
        '.sl-cab-row > i, .sl-profile-entry > i, .sl-profile-info-row i',
        # the title of a cabinet row beside the correspondent of a message slot: two titles cut off at the same step
        '.sl-cab-row b, .sl-pmf-slot-top b',
        # the rail of the cabinet beside the rail of a profile, both turning their side border into a bottom one when the two columns stack.
        # Two page regions that stack the same way at one breakpoint
        '.sl-cab-rail, .sl-profile-split-rail',
    ],

    # A shared selector whose two themes hold a different set of properties, each with the reason the difference is not a
    # bug. A selector holding the same properties with different values needs no entry: that is one canon carrying many
    # skins, which is what the theme packages are for. The key is the `@media context` and the selector joined by two
    # spaces, which is what `--cross` prints
    'divergent' => [
        # The shorthand and the three longhands are not two spellings of one intent: every longhand a shorthand leaves out
        # is reset to its initial value, so `list-style: disc outside` fixes the type where the longhands leave it alone
        'ul' =>
            'admin leaves list-style-type to the browser so a nested list changes its mark - disc, then circle, then square - '
            .'and lite fixes it at a disc. The shorthand cannot express the first: writing it flattened 82 nested marks in admin',
        'body' =>
            'the page shell, which canon does not cover: the panel fills the viewport it owns and the site sets a floor of nothing on a document whose '
            .'width the content decides. layouts and pages are outside canon for the same reason',
        'thead tr th[data-sort-method="none"]' =>
            'admin resets the padding its own sort arrow reserved; lite reserves a wider one and never resets it, because no lite table carries a column '
            .'the sorter is told to skip',
        '.sl-but' =>
            'two different controls under one name: the panel button is a bordered rectangle that inherits the form font, the site button is a pill with '
            .'a bevel, its own height and its own type. Every property lite adds is that pill; giving the panel the same twenty declarations would make '
            .'the admin look like the site, which is the one thing this batch may not do',
        '.sl-dimmed' =>
            'what each theme dims is a different thing: the panel dims a menu row that is off and pulls its text to the border tone, because an inactive '
            .'row is chrome the reader is not meant to read; the site dims content a reader may still read - a comment awaiting approval, a message '
            .'already opened, an action the reader may not take - and only lowers it, because pulling body text to the border tone would take it under AA',
        '.sl-tip' =>
            'the panel hint is a glyph the admin draws itself - its own box, tone, size and transition - while the site puts the hint on whatever element '
            .'carries it and only pushes the next one away. One is a control, the other a modifier',
        '.sl-tip > .sl-float-panel' =>
            'the whole floating panel is defined here in lite and on .sl-float-panel in admin; the two carry the same declarations under two selectors, '
            .'so the tool sees one side as empty. Moving either would change which rule wins over the placement rule',
        '.sl-float-panel::after' =>
            'the arrow of the panel is drawn against a border in admin, which needs a drop-shadow to sit on it; lite gives the panel a muted border and '
            .'paints the shadow on the two contexts that show one, so the shared rule carries none',
        '.sl-float.sl-float-up > .sl-float-panel::after' =>
            'the flipped half of the same arrow, and the same reason',
        '.sl-chip' =>
            'the panel chip is a count badge with a minimum width and a bold micro type; the site chip is a label with an icon, its own gap and a pointer '
            .'transition. They share a name and a shape and nothing else',
        '.sl-dial' =>
            'the speed dial is two mechanisms: in admin an absolutely placed row that grows its items in place, in lite an inline toggle with an '
            .'absolutely placed fan. Everything below follows from that, and the two were built that way on purpose',
        '.sl-dial.sl-open, .sl-dial:hover' =>
            'the open dial: admin lifts a whole row onto a surface, lite raises one fan above the page',
        '.sl-dial-toggle, .sl-dial-item' =>
            'lite\'s dial opens on a click and says so with a pointer; admin\'s opens on hover',
        '.sl-dial.sl-open .sl-dial-toggle, .sl-dial:hover .sl-dial-toggle' =>
            'the same, and the site\'s toggle carries the text that reads on its fill',
        '.sl-dial .sl-dial-item' =>
            'the resting item: admin collapses its width to nothing, lite parks it behind the toggle',
        '.sl-dial.sl-open .sl-dial-item, .sl-dial:hover .sl-dial-item' =>
            'the opened item, the other half of the same two mechanisms',
        '.sl-bulk-bar' =>
            'the panel bar is a strip inside a list, drawn with one rule above it; the site bar is a card of its own with a border and a radius, standing '
            .'away from what it acts on',
        '.sl-image-preview-mini:hover' =>
            'the panel thumbnail grows into a circle under the pointer and the site thumbnail is already round, so only one of them has a radius left to '
            .'change',
        '.sl-alert' =>
            'the panel notice is one line high with its text justified in the theme\'s text tone; the site notice is a block that lifts off the page. The '
            .'tint law and the tone are shared, the geometry is each theme\'s own',
        '.sl-alert::before' =>
            'the glyph of the notice: the panel spells the weight its icon font needs beside a reset that would otherwise bolden it, the site nudges it '
            .'down to sit on the first line of a block',
        '.sl-alert-flash' =>
            'the flash of the panel is placed and faded as a whole; the site flash only collapses its height, because it sits in a column that already '
            .'holds its place',
        '.sl-alert-flash-bar' =>
            'the countdown: the panel draws it in the tone\'s own colour on a transparent ground and pins it to the top edge, the site gives it a height '
            .'and fills it',
        '.sl-pager' =>
            'the panel pager is a row inside a list foot that has already laid it out; the site pager is a centred band with a rule above it, standing '
            .'between the list and the page',
        '.sl-pager-main' =>
            'the same band: the site pager wraps onto a second line on a narrow screen and reads at heading size, the panel one never wraps',
        '.sl-but-mini' =>
            'the site\'s mini button is a real button element and resets the appearance the browser gives one; in admin the same chip is a link',
        '.sl-but-mini.sl-is-muted' =>
            'the muted chip is inert on the site and says so with the pointer; in admin it is not clickable to begin with',
        '.sl-geo-flag img' =>
            'the panel prints a flag as a block at icon size with its ratio free; the site prints it inline beside text at the smaller step',
        '.sl-progress-line div' =>
            'the site fills its bar in front of the reader, because a poll is answered on the page it is drawn on; the panel bar is a reading of '
            .'something already counted',
        '.sl-tabs-nav' =>
            'the panel tab strip separates its tabs, the site\'s lets them touch and carries the gap on the item',
        '.sl-tabs-item' =>
            'the same, from the item\'s side: the site tab is a list item that has to lose its bullet and hold its own gap',
        '.sl-tabs-link' =>
            'two tabs: the panel tab is a bevelled folder with a minimum height, the site tab a bold block with its own type and a pointer transition. '
            .'The strip is shared, the tab is not',
        '.sl-tabs-content' =>
            'the panel drops its panel straight under the strip with one gap; the site draws it as a surface with a border, a ground and its own padding',
        '.sl-form-row' =>
            'the panel form row is a horizontal pair, the site row a stacked one, which is what a narrow column asks for and a wide panel does not',
        '.sl-form-label' =>
            'the same pair from the label\'s side: a fixed column that may not shrink in the panel, a line above the field on the site',
        '.sl-session-line, .sl-session-row' =>
            'the site lays the session line out as a grid of two tracks so the count keeps its column; the panel lets it flow, because the sidebar it '
            .'sits in is already narrow enough to hold the pair',
        '.sl-session-icon' =>
            'the panel tints the session glyph and holds its width; the site lets it take the line\'s own colour',
        '.sl-session-name, .sl-session-module' =>
            'the panel lets a long module name shrink to nothing rather than push the count off the row; the site breaks it inside the word',
        '.sl-session-action' =>
            'the site gives the action its own vertical room inside the grid cell; in the panel the row already has it',
        '.sl-changelog-filter-actions' =>
            'the site pushes the filter buttons to the end of a four-track grid; the panel grid already ends there',
        '.sl-changelog-commit-header code' =>
            'the panel prints a commit hash in a monospace face at the small step, the site in the face the surrounding text already uses',
        '.sl-changelog-commit-files' =>
            'the same face, on the file list of the same commit',
        '.sl-knob' =>
            'the panel gauge is a dashboard figure with room under it; the site gauge is a fixed track in a flex row. The size, the stroke and the track '
            .'are values, the flex and the margin are the place each one stands in',
        '.sl-code' =>
            'the site sets a code fragment in italic because it quotes the source inside running text; the panel prints it as a block',
        '.sl-mode' =>
            'the panel puts the mode switch after the last toolbar item and needs the gap; the site puts it in a row that already spaces its children',
        '.sl-msg-foot' =>
            'the site holds the message foot to the card\'s own width, because the message page is a card centred on a wide page; the panel message fills '
            .'the shell it is in',
        '.sl-dial.sl-dial-point' =>
            'the pointed dial: in admin a row that stops wrapping and closes its gap, on the site the switch from an inline dial to an absolutely placed '
            .'one. The two mechanisms again',
    ],

    # The skeleton of a theme package: what a directory has to hold before the runtime and the tests accept it as a theme.
    # It is the union of two lists that are not the same list, and each entry names the gate that demands it, because a
    # skeleton nobody can trace back to a gate grows entries nobody dares delete
    'skeleton' => [
        # Demanded of every theme by checkThemeAssets() in core/system.php, which the runtime calls before it selects one
        'any' => [
            'assets/css/base.css' => 'checkThemeAssets: the API block, the marker and the element styles - the one file a new theme edits',
            'assets/css/theme.css' => 'checkThemeAssets: the components, holding no literal visual value',
            'assets/vendor/bootstrap-icons/css/bootstrap-icons.min.css' => 'checkThemeAssets: the icon face every component draws its glyph from',
            'assets/vendor/bootstrap-icons/css/fonts/bootstrap-icons.woff2' => 'checkThemeAssets: the font file that stylesheet points at',
            'images/avatars/system/user.svg' => 'checkThemeAssets: the avatar a member falls back to',
            'images/avatars/system/guest.svg' => 'checkThemeAssets: the avatar a visitor falls back to',
            'images/avatars/system/deleted.svg' => 'checkThemeAssets: the avatar a removed account falls back to',
            'images/avatars/presets/' => 'checkThemeAssets: the directory the avatar picker offers, which may be empty but must exist',
        ],
        # Demanded of a frontend theme by TemplateValidationTest, which is why the admin package passes without them
        'frontend' => [
            'fragments/title.html' => 'TemplateValidationTest: the title of a module page',
            'partials/content-list.html' => 'TemplateValidationTest: the list a module renders its rows into',
            'partials/view.html' => 'TemplateValidationTest: the single record a module opens',
            'pages/module.html' => 'TemplateValidationTest: the page shell a module is drawn in',
            'layouts/app.html' => 'TemplateValidationTest: the document every ordinary page opens',
        ],
        # Demanded per editor manifest that declares a `theme` block, which is a list the plugin owns rather than the theme
        'editor' => [
            'assets/editors/<id>/skin.css' => 'checkThemeAssets: required when the manifest declares `skin`, and held to the same zero as the theme CSS',
            'partials/<name>.html' => 'checkThemeAssets: one file per name the manifest lists under `partials`',
        ],
    ],

    # The directories a shared template name is canon in. layouts and pages are outside it because the page shells of a
    # panel and a site differ by nature, so --cross reports them and demands nothing
    'canon' => ['fragments', 'partials'],

    # A template name both themes carry whose two files differ, each with the reason canon does not want one file. The
    # question a shared name asks is not "do the two files match" but "is this one contract with two spellings, or two
    # contracts under one name". Unifying without reconciling the key sets first silently drops data or changes what is
    # escaped, so every entry here is the answer to a call-site audit and not an excuse for one
    'templates' => [
        'fragments/admin-block-links.html' =>
            'one producer hands both themes the same two links and the editable block under them. The panel draws them as two rows of its sidebar block and rules '
            .'the editable text off with a line; the site draws them as the logged-in half of its login list, which is the same ul the header already fills when '
            .'nobody is logged in, so the two cannot be one element',
        'fragments/alert.html' =>
            'the flash notice is the panel alone: getFlashHtml() and the admin tabs are its only producers, and is_flash with alert_attr are what place it, fade it '
            .'and start its countdown. Under that the panel prints the text as handed and the site wraps it in a paragraph, because the site notice is a block of '
            .'running text and the panel notice is one line - the same split the .sl-alert rules already carry',
        'fragments/block-content.html' =>
            'a class map, and the map is each theme own vocabulary: three named containers in the panel against fifteen on the site, with the four they share '
            .'spelled the same way. Unifying means one theme emitting class names nothing in it styles, which is how a dead class enters a package hundreds of '
            .'themes are copied from',
        'fragments/button.html' =>
            'the panel needs a real button element whenever a value is posted under a name, because an input carries its value and not its label; the site has no '
            .'such caller and stays on the input it has always drawn. On top of that the panel picks a tone class per button and the site has one button, so the '
            .'ladder that reads the tone flags has nothing to map to there',
        'fragments/mode-switch.html' =>
            'one producer hands both themes the same three modes with the one in force marked, and each spends the answer where its user already is. The site '
            .'stands the three open on the top bar as a rail with a knob, because a visitor changes the mode from the page and has room for it there; the panel '
            .'spends it as one row of the settings window beside the editor and the language, because a toolbar carrying seven menu items has no room for a third '
            .'open control and the answer belongs with the other two an administrator changes from any screen',
        'fragments/changelog-commit.html' =>
            'one producer hands both themes the commit hash and the author e-mail address; the panel prints them, because a moderator reads a changelog to identify '
            .'a commit, and the public page drops both, because an address published on an open page is harvested. Guarding them on a key would move that decision '
            .'into the producer, which feeds one call site and cannot know which theme renders',
        'fragments/checkbox.html' =>
            'the same box under two form systems: the panel wraps it in the label class its radio uses and can set a code sample beside the text, the site names '
            .'its own label class per call and carries a plain shape that takes no class at all. The check-all hook is the one thing both spell, and they spell it '
            .'the same way',
        'fragments/dial.html' =>
            'two mechanisms under one name, which the .sl-dial rules already answer for: the panel dial drives the file manager through data-sl-fm-act, -file, -arg '
            .'and -run, and the site dial swaps a fragment over htmx with its own target, swap and CSRF header. Neither set is inert in the other theme - each is '
            .'the half of the dial that theme is built on',
        'fragments/inline-badge.html' =>
            'two badge vocabularies: fourteen names the panel maps and twenty the site does, with no name in both. The site badge tells a reader what a topic is - '
            .'new, hot, closed, moderated - and carries the glyph for it; the panel badge tells a moderator what a record is. A union would be thirty-four branches '
            .'of which each theme reaches its own half',
        'fragments/input.html' =>
            'the field look is where the two themes part: the site gives every input one sl-field class and modifies it, the panel names the context the input sits '
            .'in instead - a config row, a ratings day box, a search filter, a translation target. The keys do not collide and no producer sets one the other theme '
            .'knows, so this is two class maps and not one contract spelled twice',
        'fragments/label.html' =>
            'the panel label is a real label element with a for, a title and a prefix, because a panel form associates its control by id; the site label defaults '
            .'to one class and takes text or markup and nothing else. One caller feeds the site file and it passes neither for nor title',
        'fragments/link.html' =>
            'the largest of the two vocabularies: twelve names the panel maps against thirty-three the site does, and not one is in both. The site link is the '
            .'whole card, cart, category and login language of a public page; the panel link is a row action. This is the pair the plan measured at over a hundred '
            .'lines, and it is two contracts rather than one contract with two spellings',
        'fragments/pager-link.html' =>
            'the panel page number is its own control, sl-pnum, with a current and a nav modifier; the site reuses the mini chip every other small control on it is '
            .'drawn as, and puts a glyph in place of the previous and next labels. One producer feeds both and hands the label and the icon name either way, so what '
            .'differs is which of the two each theme draws',
        'fragments/pager.html' =>
            'the site opens its pager with the tally it paginates - how many records over how many pages at what page size - because a public list is the whole '
            .'page; the panel pager sits under a module that already carries its own head and toolbar. The one producer passes the four labels to both themes, so '
            .'the divergence is which theme draws them and not what either is given',
        'fragments/popover.html' =>
            'the hint is a control in the panel and a modifier on the site, which the .sl-tip rules already answer for: the panel draws the glyph itself and gives '
            .'the whole thing a nav element, the site wraps the glyph in its own span so the hint can sit on whatever element carries it. The note under the panel '
            .'hint has no producer on the site',
        'fragments/select.html' =>
            'the panel select renders its options from an array as well as from ready markup, and names the context it sits in - config, search sort, search order, '
            .'save action; the site takes ready markup only and gives every select the one sl-field class. Two class maps and two option contracts, and no producer '
            .'crosses them',
        'fragments/span.html' =>
            'one flag in the panel against twenty-two on the site. The site span is the chip language of a public page - price, reads, votes, cart total, the four '
            .'message markers - each carrying its own glyph; the panel span is a drag handle and a few text tones. The hidden and dimmed names are the one thing '
            .'both spell, and after this batch they spell it the same way',
        'fragments/table-row.html' =>
            'two row contracts: the panel row takes cells already rendered and adds the sort and grouping hooks its tables need, the site row builds its own cells '
            .'from a list and knows what a title, a category and a moderator column are. The site file is over a hundred lines because the row is where a public '
            .'list decides what a record looks like; the panel decides that in the module',
        'fragments/table.html' =>
            'the panel table is one shape - a sortable list inside a card, optionally unwrapped - and the site table is nine, from a cart to an FAQ to an avatar '
            .'grid. The site also splits on an open flag so a looped row lands inside the tbody, which the panel has no producer for. The head is the same split '
            .'again: ready markup in the panel, a column list on the site',
        'fragments/textarea.html' =>
            'the same field split as the input: the site gives every textarea sl-field and marks the one the editor mounts on, the panel names the config row '
            .'instead. The site also lets the name be absent, because an editor area posts through the control it is mounted in',
        'fragments/voting-view.html' =>
            'the result row of a poll: the panel prints the answer and its share as a header over the bar, the site draws the leading answer with a trophy and '
            .'hangs the raw counts on the element as data attributes for the live swap to read. One producer feeds both and passes is_lead either way',
        'partials/block-sidebar.html' =>
            'the panel sidebar block is a div with an h3 that collapses on a slide and carries an icon and the two collapse glyphs; the site block is a section '
            .'with an h2 and no icon, because a heading rank follows the document it sits in and the two documents are the page shells canon does not cover. The '
            .'collapse hook is the same name in both',
        'partials/div.html' =>
            'the panel container knows what it is wrapping - a search box, a menu grid, a radio group, a collapsible, a rate box - and the site container is a div '
            .'with an id, a title and at most one of two classes. The row form differs the same way: the panel includes its own row fragment, the site writes the '
            .'row inline',
        'partials/foot-controls.html' =>
            'the panel foot carries the two links the site has no producer for - the brand link and the debug switch - and prints the generation time before the '
            .'licence, where the site prints it after. One producer feeds both and hands every key to each, so the order and the two extra links are what each theme '
            .'chose to draw',
        'partials/preview.html' =>
            'the panel draws its preview inside the sl-box card every panel block sits in, and the site has no such card at all - sl-box appears nowhere in its '
            .'CSS. Everything inside the wrapper is already byte-identical, so this entry is the wrapper and nothing else',
        'partials/session-summary.html' =>
            'two readings of the same numbers: the panel lists who is online and lets a moderator open each audience, so it needs a row list, a toggle id and a '
            .'label per audience; the site draws one donut of the three shares and refreshes it on a timer. The panel has no producer for the percentages and the '
            .'site none for the row lists',
        'partials/voting-widget.html' =>
            'the site widget is live - it re-fetches itself on a timer, closes itself when the poll ends and picks its heading rank from where it stands; the panel '
            .'widget is a still section with a fixed h4. The one producer hands both themes poll_id and token and the panel file draws neither, which is safe only '
            .'while has_form is false there: a poll form posted from the panel theme would carry no id and no CSRF token',
    ],

    # PHP the markup scan skips, each with the reason it is not a leftover
    'markup' => [
        # The scan reached zero, so it gets a gate under it: --markup exits non-zero on the next hardcoded class, inline
        # style or HTML tag instead of printing a figure nobody reads. A count with no limit beside it is a report, not a check
        'limit' => 0,
        'exclude' => [
            '/lang/' => 'a language file defines translated sentences, one file per locale; markup inside a sentence is part of that text and moves '
                .'with the translation, not with a fragment. This is the general case of the RSS feed document the plan already excluded',
            'config/filetype.php' => 'attachment templates edited by the administrator through admin/modules/uploads.php: user data that happens to be markup',
        ],
    ],
];
