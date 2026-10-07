<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the line breaks of docs/EDITORS.md: a stored value mounted by getTplTextarea(), submitted as a browser submits it and saved by the text filter, per format
# It boots the real core like index.php, so the mount, the save and the render are the shipped functions and nothing is read from or written to the database
# A browser normalizes every line end of a textarea to CRLF on submit, and Toast UI hands its markdown back byte for byte in the markdown mode the site starts it in
$probework = (string)($argv[1] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';
if (!class_exists('Parser')) require_once BASE_DIR.'/core/classes/parser.php';

# Stored values as the columns hold them: breaks written by nl2br() of the plain save, an inline one, an empty line, an entity and a tag a member typed as text
function getProbeValues(): array {
    return [
        'lines' => "alpha<br>\r\nbeta<br />\r\ngamma",
        'inline' => 'one<br>two',
        'blank' => "para<br>\r\n<br>\r\nnext",
        'entity' => "a &amp; b<br>\r\nc",
        'typed' => 'a &lt;br&gt; b',
    ];
}

# Mount one value the way the account settings mount the signature and read back the text the editor receives
function getProbeMount(string $value): string {
    $html = getTplTextarea(['id' => '1', 'name' => 'sig', 'value' => $value, 'mod' => 'profile', 'store' => 'users.sig']);
    if (!preg_match('#<textarea[^>]*>(.*?)</textarea>#s', $html, $hit)) return '';
    return html_entity_decode($hit[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

# Submit the mounted text as a browser does and save it through the text filter of getVar(), which filter_input() keeps out of reach of a CLI process
function getProbeSave(string $text): string {
    return filterHtml(trim(str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $text)));
}

# One value through mount and save twice, with the signature render and the escaped render of each stage
function getProbeTrip(string $value): array {
    global $prs;
    $mount = getProbeMount($value);
    $saved = getProbeSave($mount);
    $again = getProbeSave(getProbeMount($saved));
    return [
        'mount' => $mount,
        'saved' => $saved,
        'again' => $again,
        'sign' => [getUserSign($value, 0, 2), getUserSign($saved, 0, 2)],
        'safe' => [$prs->filterContent($value, true, 'news', 2, ''), $prs->filterContent($saved, true, 'news', 2, '')],
    ];
}

$out = [];
try {
    $prs ??= new Parser();
    foreach (['plain' => 'plain', 'markdown' => 'toastui'] as $fmt => $key) {
        $conf['editor']['user'] = $key;
        $out[$fmt]['format'] = getEditorMode();
        foreach (getProbeValues() as $name => $value) $out[$fmt]['trips'][$name] = getProbeTrip($value);
    }
    $out['values'] = getProbeValues();
} catch (Throwable $error) {
    $out = ['error' => $error->getMessage().' @ '.$error->getFile().':'.$error->getLine()];
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
