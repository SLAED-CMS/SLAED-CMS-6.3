<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the render half of batch 9 in docs/UPLOAD-2026.md
# boots the real core like index.php so Parser::filterAttach() reads the shipped config/filetype.php and the shipped upload rules
# The image family is the only one that needs a real file, because filterAttach() renders a placeholder instead of the template when the source is missing
# The fixture module lives below UPLOADS_DIR because filterAttach() resolves attachments there by construction, and the whole directory is removed again before the probe answers
$probework = (string)($argv[2] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';
if (!class_exists('Parser')) require_once BASE_DIR.'/core/classes/parser.php';

# The upload directory this probe owns; it has no record in config/uploads.php, which is what makes the thumbnail width fall back to the general setting
const RENDMOD = 'slaedrender';

# One attachment per render family, each with the file name the tag points at
function getProbeCases(): array {
    return [
        'image' => 'render.png',
        'audio' => 'render.mp3',
        'video' => 'render.mp4',
        'document' => 'render.pdf',
        'archive' => 'render.zip',
    ];
}

# Create the fixture directory and write the one decodable image the image template needs
function addProbeFixture(): bool {
    $dir = UPLOADS_DIR.'/'.RENDMOD;
    if (!is_dir($dir) && !mkdir($dir, 0777, true)) return false;
    $img = imagecreatetruecolor(40, 30);
    if (!$img) return false;
    $done = imagepng($img, $dir.'/render.png');
    imagedestroy($img);
    return $done;
}

# Render one attachment through the real pipeline; the rel and width form is used so every token of every shipped template is exercised
function getProbeRender(Parser $par, string $file): string {
    $tag = '[attach='.$file.' align=left title=Render width=100 height=80 rel=group]';
    return $par->filterDoc($tag, true, RENDMOD);
}

$out = ['ok' => addProbeFixture(), 'runs' => []];
if ($out['ok']) {
    $par = new Parser();
    foreach (getProbeCases() as $name => $file) $out['runs'][$name] = getProbeRender($par, $file);
}
deleteDir(UPLOADS_DIR.'/'.RENDMOD);
$out['left'] = is_dir(UPLOADS_DIR.'/'.RENDMOD);
echo json_encode($out);
