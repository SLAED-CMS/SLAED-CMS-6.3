<?php
// Link checker script for SLAED CMS documentation
header('Content-Type: application/json');

// List of all documentation pages
$pages = [
    'index.html',
    'installation.html',
    'configuration.html',
    'architecture.html',
    'modules.html',
    'templates.html',
    'database.html',
    'core-functions.html',
    'database-api.html',
    'template-api.html',
    'security-api.html',
    'creating-modules.html',
    'custom-themes.html',
    'performance.html',
    'security.html',
    'upgrade.html',
    'admin-api.html',
    'admin-guide.html',
    'eduard-laas.html',
    'history.html',
    'privacy-policy.html'
];

$results = [];
$workingLinks = 0;
$brokenLinks = 0;

foreach ($pages as $page) {
    // Check if file exists
    $filePath = __DIR__ . '/' . $page;
    if (file_exists($filePath) && is_readable($filePath)) {
        // Get file size and last modified time for additional info
        $fileSize = filesize($filePath);
        $lastModified = date('Y-m-d H:i:s', filemtime($filePath));
        
        $results[] = [
            'url' => $page,
            'status' => 'OK',
            'http_code' => 200,
            'file_size' => $fileSize,
            'last_modified' => $lastModified
        ];
        $workingLinks++;
    } else {
        $results[] = [
            'url' => $page,
            'status' => 'BROKEN',
            'http_code' => file_exists($filePath) ? 403 : 404, // 403 if exists but not readable, 404 if doesn't exist
            'error' => file_exists($filePath) ? 'File not readable' : 'File not found'
        ];
        $brokenLinks++;
    }
}

// Return JSON response
echo json_encode([
    'total_links' => count($pages),
    'working_links' => $workingLinks,
    'broken_links' => $brokenLinks,
    'timestamp' => date('Y-m-d H:i:s'),
    'results' => $results
]);
?>