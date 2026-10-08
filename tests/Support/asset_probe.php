<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for docs/PERFORMANCE.md, section "Static Assets": the asset tags the real core prints, with the derived configuration of a request
# It boots the core like index.php, so doCss(), doScript() and the editor loader answer from the same local.php a page reads
# The ghost run hands the printers a list and a version map naming a file that does not exist, which proves the page prints from the map and never looks at the disk
$probework = (string)($argv[1] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';

# The head tags of one theme as a page of that theme prints them
function getProbeHead(string $name): array {
    global $theme;
    $theme = $name;
    return ['css' => doCss(), 'js' => doScript()];
}

# The tags of one editor engine on a page load, the list the same engine names to the client loader on an htmx fragment, and the init of an instance
function getProbeEditor(): array {
    $css = ['plugins/editors/codemirror/assets/cm6.css'];
    $js = ['plugins/system/editor.js'];
    unset($_SERVER['HTTP_HX_REQUEST']);
    $page = Editor::getAssetTags($css, $js);
    $_SERVER['HTTP_HX_REQUEST'] = 'true';
    $frag = Editor::getAssetTags($css, $js);
    unset($_SERVER['HTTP_HX_REQUEST']);
    return ['page' => $page, 'htmx' => $frag, 'init' => Editor::getInitScript('probe-editor', 'run();', 'kill();')];
}

# The head of the site theme printed from a list and a map that name one missing file
function getProbeGhost(): array {
    global $conf, $theme;
    $theme = 'lite';
    $css = 'templates/lite/assets/css/ghost-probe.css';
    $js = 'plugins/system/ghost-probe.js';
    $conf['derived']['assets']['lite'] = ['css' => [$css], 'js' => [$js]];
    $conf['derived']['version'][$css] = 'abcdef0123';
    $conf['derived']['version'][$js] = '0123abcdef';
    return ['css' => doCss(), 'js' => doScript()];
}

$out = [];
try {
    $conf['dev_mode'] = false;
    $out = [
        'map' => is_array($conf['derived']['version'] ?? null),
        'keys' => array_values(array_intersect(['script_a', 'script_b'], array_keys($conf))),
        'lite' => getProbeHead('lite'),
        'admin' => getProbeHead('admin'),
        'editor' => getProbeEditor(),
        'ghost' => getProbeGhost(),
    ];
} catch (Throwable $error) {
    $out = ['error' => $error->getMessage().' @ '.$error->getFile().':'.$error->getLine()];
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($out, JSON_UNESCAPED_SLASHES);
