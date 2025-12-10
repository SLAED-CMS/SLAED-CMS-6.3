<?php
/**
 * SLAED Kompatibilitäts-Check 5.4 → 8.4 / MySQL 8+
 * Vollständig: alle Unterordner inkl. public/
 * Mit mehrfarbigen Balken (rot/orange/gelb)
 * check_compat.php wird übersprungen
 */

$rootDir = __DIR__;

$patterns = [
    // SQL Problemfälle
    'GROUP BY ohne alle Spalten' => ['regex'=>'/\bGROUP\s+BY\b/i','level'=>'orange'],
    '0000-00-00 als Datum'       => ['regex'=>"/'0000-00-00(?: 00:00:00)?'/",'level'=>'red'],
    'TYPE= (veraltet in SQL)'    => ['regex'=>'/CREATE\s+TABLE.*TYPE\s*=/i','level'=>'orange'],
    'MyISAM Engine'              => ['regex'=>'/\bMyISAM\b/i','level'=>'orange'],
    'DEFAULT auf TEXT'           => ['regex'=>'/TEXT\s+[^,]*DEFAULT/i','level'=>'red'],
    'DEFAULT auf BLOB'           => ['regex'=>'/BLOB\s+[^,]*DEFAULT/i','level'=>'red'],
    'LIMIT offset, count'        => ['regex'=>'/\bLIMIT\s+\d+\s*,\s*\d+/i','level'=>'orange'],

    // PHP Problemfälle
    'each() (entfernt in PHP 8)'               => ['regex'=>'/\beach\s*\(/i','level'=>'red'],
    'create_function() (entfernt in PHP 7.2)'  => ['regex'=>'/\bcreate_function\s*\(/i','level'=>'red'],
    'split() (entfernt in PHP 7)'              => ['regex'=>'/\bsplit\s*\(/i','level'=>'red'],
    'ereg() Familie (entfernt in PHP 7)'       => ['regex'=>'/\bereg(_replace|_i)?\s*\(/i','level'=>'red'],
    'mysql_* Funktionen (entfernt in PHP 7)'   => ['regex'=>'/\bmysql_(query|connect|fetch|num_rows|select_db)\s*\(/i','level'=>'red'],
    'get_magic_quotes_gpc() (entfernt)'        => ['regex'=>'/\bget_magic_quotes_gpc\s*\(/i','level'=>'orange'],
    'get_magic_quotes_runtime() (entfernt)'    => ['regex'=>'/\bget_magic_quotes_runtime\s*\(/i','level'=>'orange'],
    'set_magic_quotes_runtime() (entfernt)'    => ['regex'=>'/\bset_magic_quotes_runtime\s*\(/i','level'=>'orange'],
    'call_user_method() (entfernt)'            => ['regex'=>'/\bcall_user_method\s*\(/i','level'=>'red'],
    'mbstring.func_overload (entfernt)'        => ['regex'=>'/mbstring\.func_overload/i','level'=>'orange'],
    '__autoload() (deprecated PHP 7.2)'       => ['regex'=>'/\b__autoload\s*\(/i','level'=>'orange'],
    'preg_replace /e (deprecated)'             => ['regex'=>'/preg_replace\s*\(.+,\s*.+,\s*.+\s*\)/i','level'=>'orange'],
    'urldecode(null) möglich'                  => ['regex'=>'/urldecode\s*\(\s*\$[a-z_][a-z0-9_]*\s*\)/i','level'=>'orange'],
    'mt_rand max < min möglich'                => ['regex'=>'/mt_rand\s*\(\s*\d*\s*,\s*\d*\s*\)/i','level'=>'orange'],

    // PHP 7/8 Stolperfallen
    'count(null) möglich'                       => ['regex'=>'/\bcount\s*\(\s*null\s*\)/i','level'=>'yellow'],
    'empty(undefined) möglich'                  => ['regex'=>'/\bempty\s*\(\s*\$[a-z_][a-z0-9_]*\s*\)/i','level'=>'yellow'],
    'Reserved Keyword Variable'                => ['regex'=>'/\$?(match|string|static)\b/i','level'=>'yellow'],
];

$checkedFiles = [];
$folderCount = [];

function scanAllDirsIncludePublic($dir, $patterns, &$checkedFiles, &$folderCount) {
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    $results = [];

    foreach ($rii as $file) {
        if ($file->isDir()) continue;

        $filePath = $file->getRealPath();
        if (!$filePath) continue;

        $filePath = str_replace('\\','/',$filePath);

        // Only check PHP files, except check_compat.php
        if (substr(strtolower($filePath), -4) !== '.php') continue;
        if (basename($filePath) === 'check_compat.php') continue;

        $checkedFiles[] = $filePath;

        $folder = dirname($filePath);
        if (!isset($folderCount[$folder])) $folderCount[$folder] = 0;
        $folderCount[$folder]++;

        $lines = @file($filePath);
        if (!$lines) continue;

        foreach ($lines as $lineNumber => $lineContent) {
            foreach ($patterns as $label => $info) {
                if (preg_match($info['regex'], $lineContent)) {
                    $results[] = [
                        'file' => $filePath,
                        'line' => $lineNumber + 1,
                        'issue' => $label,
                        'level' => $info['level'],
                        'code' => htmlspecialchars(trim($lineContent))
                    ];
                }
            }
        }
    }
    return $results;
}

// Scan starten
$issues = scanAllDirsIncludePublic($rootDir,$patterns,$checkedFiles,$folderCount);
$checkedFiles = array_unique($checkedFiles);
ksort($folderCount);

// Fehler zählen für Balkendiagramm
$errorCounts = [];
foreach($issues as $i){
    $label = $i['issue'];
    if(!isset($errorCounts[$label])){
        $errorCounts[$label] = ['count'=>0, 'level'=>$i['level']];
    }
    $errorCounts[$label]['count']++;
}
arsort($errorCounts);

?>

<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>SLAED PHP 5.4 → 8.4 Kompatibilitäts-Check</title>
<style>
body{font-family:Arial,sans-serif;margin:20px;background:#f9f9f9;}
h1,h2{color:#333;}
table{border-collapse:collapse;width:100%;background:#fff;margin-bottom:20px;}
th,td{border:1px solid #ccc;padding:6px 10px;text-align:left;}
th{background:#eee;}
tr:nth-child(even){background:#f2f2f2;}
.code{font-family:monospace;color:#222;}
.issue{font-weight:bold;}
.issue.red{color:#b00;}
.issue.orange{color:#e68a00;}
.issue.yellow{color:#f1c40f;}
.filter{margin-bottom:15px;}
.checked-files li{margin:2px 0;}
.bar-container{margin:5px 0;}
.bar{height:20px;text-align:right;color:#fff;padding-right:5px;}
</style>
</head>
<body>
<h1>SLAED PHP 5.4 → 8.4 & MySQL 8 Kompatibilitäts-Check</h1>

<h2>Geprüfte Dateien</h2>
<?php if($checkedFiles): ?>
<ul class="checked-files">
<?php foreach($checkedFiles as $f): ?>
<li><?= htmlspecialchars($f)?></li>
<?php endforeach; ?>
</ul>
<?php else: ?>
<p>⚠️ Keine Dateien gefunden.</p>
<?php endif; ?>

<h2>Geprüfte Dateien pro Ordner</h2>
<?php if($folderCount): ?>
<table>
<tr><th>Ordner</th><th>Anzahl geprüfter Dateien</th></tr>
<?php foreach($folderCount as $folder=>$count): ?>
<tr>
<td><?= htmlspecialchars($folder)?></td>
<td><?= $count ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?>
<p>⚠️ Keine Ordner gefunden.</p>
<?php endif; ?>

<p>Gefundene mögliche Problemstellen: <strong><?= count($issues)?></strong></p>

<div class="filter">
<label>Filter: </label>
<select id="levelFilter" onchange="filterTable()">
<option value="all">Alle</option>
<option value="red">Kritisch (Rot)</option>
<option value="orange">Warnung (Orange)</option>
<option value="yellow">Hinweis (Gelb)</option>
</select>
</div>

<?php if(!$issues): ?>
<p>✅ Keine typischen Kompatibilitätsprobleme gefunden.</p>
<?php else: ?>
<table id="issueTable">
<tr><th>Datei</th><th>Zeile</th><th>Problem</th><th>Code</th></tr>
<?php foreach($issues as $i): ?>
<tr class="<?= $i['level'] ?>">
<td><?= htmlspecialchars($i['file']) ?></td>
<td><?= $i['line'] ?></td>
<td class="issue <?= $i['level'] ?>"><?= htmlspecialchars($i['issue']) ?></td>
<td class="code"><pre><?= $i['code'] ?></pre></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Fehler-Übersicht</h2>
<?php if($errorCounts): ?>
<div style="width:100%; max-width:800px;">
<?php
$max = max(array_column($errorCounts,'count'));
foreach($errorCounts as $label => $data):
    $width = ($data['count']/$max)*100;
    $color = match($data['level']){
        'red'=>'#b00',
        'orange'=>'#e68a00',
        'yellow'=>'#f1c40f',
        default=>'#ccc'
    };
?>
<div class="bar-container">
<strong><?= htmlspecialchars($label) ?> (<?= $data['count'] ?>)</strong>
<div class="bar" style="width:<?= $width ?>%; background:<?= $color ?>;"></div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<p>✅ Keine Fehler gefunden.</p>
<?php endif; ?>

<script>
function filterTable(){
    var filter=document.getElementById('levelFilter').value;
    var rows=document.getElementById('issueTable').rows;
    for(var i=1;i<rows.length;i++){
        if(filter==='all'){rows[i].style.display='';continue;}
        if(rows[i].classList.contains(filter)){rows[i].style.display='';}else{rows[i].style.display='none';}
    }
}
</script>

</body>
</html>