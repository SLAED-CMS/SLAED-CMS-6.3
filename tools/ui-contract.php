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
        'font-size' => [
            'steps' => [10, 12, 14, 16, 18, 20, 24, 32, 48],
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
        'col-status', 'com', 'com-arrow', 'com-ava', 'com-item', 'count', 'crumb', 'crumb-bar', 'demo', 'dial', 'donut', 'drift',
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
        'rail', 'rank', 'ratings', 'ring', 'row', 'scroll', 'search', 'search-filter', 'search-order', 'search-sort', 'select', 'sep',
        'session', 'shot', 'shot-side', 'site', 'site-img', 'skel',
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
        '.bx-pager-item a:hover:after, .sl-forum-last:hover, .sl-rate-sites:hover, .sl-session-note',
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
        '.sl-fieldset-form-legend-danger, .sl-text-danger, .sl-hide:before, .sl-hide:after, .sl-profile-proof.sl-is-warn i, '
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
        '.sl-gallery > li > b, .sl-login-top--head > li > a:hover, .sl-login-top--head > li > a:focus-visible, '
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
    ],

    # PHP the markup scan skips, each with the reason it is not a leftover
    'markup' => [
        'exclude' => [
            '/lang/' => 'a language file defines translated sentences, one file per locale; markup inside a sentence is part of that text and moves '
                .'with the translation, not with a fragment. This is the general case of the RSS feed document the plan already excluded',
            'config/filetype.php' => 'attachment templates edited by the administrator through admin/modules/uploads.php: user data that happens to be markup',
        ],
    ],
];
