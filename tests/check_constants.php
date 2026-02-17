<?php
/**
 * Check unused language constants
 * Usage: php tests/check_constants.php [language_file]
 * Default: admin/language/ru.php
 */

$base = dirname(__DIR__);
$langFile = $argv[1] ?? 'admin/language/ru.php';
$langPath = $base.'/'.$langFile;

if (!file_exists($langPath)) {
    echo "File not found: $langPath\n";
    exit(1);
}

// Parse all define() constants from language file
$content = file_get_contents($langPath);
preg_match_all('/define\(\s*["\'](_[A-Z0-9_]+)["\']/', $content, $matches);
$constants = array_unique($matches[1]);
sort($constants);

echo "Language file: $langFile\n";
echo "Constants found: ".count($constants)."\n";
echo str_repeat('-', 60)."\n";

// Directories to search
$searchDirs = ['core', 'admin', 'modules', 'blocks', 'templates', 'setup'];
// Also search root PHP files
$searchFiles = glob($base.'/*.php');

// Collect all PHP files to search
$allFiles = [];
foreach ($searchDirs as $dir) {
    $path = $base.'/'.$dir;
    if (!is_dir($path)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach ($it as $file) {
        if ($file->getExtension() === 'php') {
            $allFiles[] = $file->getPathname();
        }
    }
}
$allFiles = array_merge($allFiles, $searchFiles);

// Exclude language files from search
$langDir = realpath($base.'/admin/language');
$allFiles = array_filter($allFiles, function($f) use ($langDir) {
    return strpos(realpath($f), $langDir) === false;
});

// Read all file contents into one big string for fast searching
$allContent = '';
foreach ($allFiles as $file) {
    $allContent .= file_get_contents($file)."\n";
}

// Check each constant
$unused = [];
$used = 0;
foreach ($constants as $const) {
    if (strpos($allContent, $const) === false) {
        $unused[] = $const;
    } else {
        $used++;
    }
}

echo "Used: $used\n";
echo "Unused: ".count($unused)."\n";
echo str_repeat('-', 60)."\n";

if ($unused) {
    echo "\nUnused constants:\n";
    // Find line numbers for unused constants
    $lines = explode("\n", $content);
    foreach ($unused as $const) {
        $lineNum = 0;
        foreach ($lines as $i => $line) {
            if (strpos($line, '"'.$const.'"') !== false || strpos($line, "'".$const."'") !== false) {
                $lineNum = $i + 1;
                break;
            }
        }
        // Get the value
        preg_match('/define\(\s*["\']'.preg_quote($const).'["\']\s*,\s*["\'](.{0,60})/s', $content, $vm);
        $val = isset($vm[1]) ? $vm[1].'...' : '';
        echo sprintf("  Line %4d: %-30s %s\n", $lineNum, $const, $val);
    }
}

echo "\nDone.\n";
