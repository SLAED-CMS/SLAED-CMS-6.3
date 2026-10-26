<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the stored text of docs/EDITORS.md: a stored value mounted by getTplTextarea(), submitted as a browser submits it and saved as typed, per format
# It boots the real core like index.php, so the mount, the save and the render are the shipped functions and nothing is read from or written to the database
# A browser normalizes every line end of a textarea to CRLF on submit, and Toast UI hands its markdown back byte for byte in the markdown mode the site starts it in
$probework = (string)($argv[1] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';
if (!class_exists('Parser')) require_once BASE_DIR.'/core/classes/parser.php';

# Stored Markdown as the columns hold it: both hard breaks, an empty line, entities and a bare ampersand, tags a member typed, quotes and a fence with markup inside
function getProbeValues(): array {
    return [
        'breaks' => "alpha  \nbeta\\\ngamma",
        'blank' => "para\n\n\nnext",
        'entity' => 'a &amp; b & c &lt;x&gt;',
        'tags' => '<h4>Head</h4> and <b>x</b><br>y',
        'quote' => "> quote\n> > nested \\- kept\n\n```php\n<?php echo '<b>'; ?>\n```",
    ];
}

# Mount one value the way the account settings mount the signature and read back the text the editor receives
function getProbeMount(string $value): string {
    $html = getTplTextarea(['id' => '1', 'name' => 'sig', 'value' => $value, 'mod' => 'profile', 'store' => 'users.sig']);
    if (!preg_match('#<textarea[^>]*>(.*?)</textarea>#s', $html, $hit)) return '';
    return html_entity_decode($hit[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

# Submit the mounted text as a browser does and save it as the signature writer does, which filter_input() keeps out of reach of a CLI process
function getProbeSave(string $text): string {
    return filterSourceText(str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $text), isAdmin(true));
}

# One value through mount and save twice, with the signature render of the stored value
function getProbeTrip(string $value): array {
    $mount = getProbeMount($value);
    $saved = getProbeSave($mount);
    return ['mount' => $mount, 'saved' => $saved, 'again' => getProbeSave(getProbeMount($saved)), 'sign' => getUserSign($value, 0, 2)];
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
