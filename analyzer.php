// Datei: analyzer.php
// Ausgabe: output/deps.json und output/graph.dot
// OSPanel cmd modules\PHP-8.4\PHP\ home\slaed.loc\public\analyzer.php

/*
Pseudocode / Plan (Schritt-für-Schritt)

1. Verzeichnis rekursiv durchsuchen (ab Startpfad) und alle .php Dateien sammeln.
2. Für jede PHP-Datei:
   a. Inhalt als Text einlesen.
   b. include/require Anweisungen mit statischen Strings extrahieren.
      - Muster: include 'datei.php', include_once "..."
      - Dynamische Includes (z. B. Variablen oder Verkettungen) können nicht immer aufgelöst werden.
   c. Pfade normalisieren: relative Pfade in Bezug auf die aktuelle Datei auflösen.
   d. Abhängigkeiten speichern: Datei_A -> Datei_B.
   e. Funktionsdeklarationen erfassen: function name(...)
   f. Funktionsaufrufe heuristisch suchen: Aufrufmuster `name(` in allen Dateien.
3. Nach dem Scan erzeugen:
   - dependencies: Liste {from, to, type: include}
   - functions: Liste {name, defined_in}
   - function_calls: Liste {caller_file, function_name, defined_in(optional)}
4. Ergebnisse ausgeben:
   - JSON-Datei mit den drei Bereichen (output/deps.json)
   - GraphViz DOT-Datei (output/graph.dot), die Include-Beziehungen darstellt.
5. Zusammenfassung in STDOUT: Anzahl Dateien, Anzahl Edges, Top 10 Dateien nach out-degree (inkludieren viele Dateien), Top 10 nach in-degree (werden oft inkludiert).

Hinweise und Einschränkungen:
- Statische, heuristische Analyse: dynamische Includes werden nicht vollständig erkannt.
- Funktionsaufruf-Erkennung nur Name-basiert, keine echte Laufzeitauflösung.
- Das Skript ist konservativ: nur klare Literal-Strings und einfache dirname(__FILE__) Muster werden aufgelöst.
*/

<?php
if (php_sapi_name() !== 'cli') {
    echo "Dieses Skript muss über die Kommandozeile ausgeführt werden.\n";
    exit(1);
}

$start = $argv[1] ?? '.';
$start = rtrim($start, DIRECTORY_SEPARATOR);
if (!is_dir($start)) {
    fwrite(STDERR, "Pfad ist kein Verzeichnis: $start\n");
    exit(2);
}

// Alle PHP-Dateien sammeln
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($start));
$phpFiles = [];
foreach ($iter as $f) {
    if (!$f->isFile()) continue;
    $name = $f->getFilename();
    if (substr($name, -4) === '.php') {
        $phpFiles[] = $f->getPathname();
    }
}

// Hilfsfunktionen
function read_file($path) {
    $content = @file_get_contents($path);
    return $content === false ? '' : $content;
}

function resolve_include_path($includingFile, $rawPath) {
    // Anführungszeichen entfernen
    $p = preg_replace('#^["\']|["\']$#', '', $rawPath);
    // Absoluter Pfad
    if (preg_match('#^(/|[A-Za-z]:\\\\)#', $p)) {
        return $p;
    }
    // dirname(__FILE__) + '/foo.php'
    if (preg_match("#dirname\\s*\\(\\s*__FILE__\\s*\\)\\s*\\.\\s*['\"](/?[^'\"]+)['\"]#", $rawPath, $m)) {
        $base = dirname($includingFile);
        $candidate = $base . DIRECTORY_SEPARATOR . ltrim($m[1], '/\\');
        return realpath($candidate) ?: $candidate;
    }
    // Relativer Pfad
    $base = dirname($includingFile);
    $candidate = $base . DIRECTORY_SEPARATOR . $p;
    return realpath($candidate) ?: $candidate;
}

$includePattern = '/\b(include|require)(?:_once)?\s*\(?\s*["\']([^"\']+)["\']\s*\)?\s*;?/i';
$includePatternAlt = '/\b(include|require)(?:_once)?\s+["\']([^"\']+)["\']\s*;?/i';

$dependencies = [];
$declaredFunctions = [];
$functionDefsPerFile = [];

// Erster Durchlauf: Includes & Funktionsdeklarationen
foreach ($phpFiles as $file) {
    $content = read_file($file);
    $dependencies[$file] = [];

    // Includes mit Klammern
    if (preg_match_all($includePattern, $content, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
            $raw = $mm[2];
            $resolved = resolve_include_path($file, $raw);
            $dependencies[$file][] = ['raw' => $raw, 'resolved' => $resolved];
        }
    }
    // Includes ohne Klammern
    if (preg_match_all($includePatternAlt, $content, $m2, PREG_SET_ORDER)) {
        foreach ($m2 as $mm) {
            $raw = $mm[2];
            $resolved = resolve_include_path($file, $raw);
            // Duplikate vermeiden
            $found = false;
            foreach ($dependencies[$file] as $ex) {
                if ($ex['raw'] === $raw) { $found = true; break; }
            }
            if (!$found) $dependencies[$file][] = ['raw' => $raw, 'resolved' => $resolved];
        }
    }

    // Funktionsdeklarationen
    if (preg_match_all('/\\bfunction\\s+([a-zA-Z0-9_]+)\\s*\\(/', $content, $mf)) {
        foreach ($mf[1] as $fname) {
            $declaredFunctions[strtolower($fname)] = $file;
            $functionDefsPerFile[$file][] = $fname;
        }
    }
}

// Zweiter Durchlauf: Funktionsaufrufe
$functionCalls = [];
$funcNames = array_keys($declaredFunctions);
usort($funcNames, function($a,$b){ return strlen($b)-strlen($a); });

foreach ($phpFiles as $file) {
    $content = read_file($file);
    foreach ($funcNames as $fname) {
        if (preg_match_all('/\\b' . preg_quote($fname, '/') . '\\s*\\(/i', $content, $mc)) {
            $definedIn = $declaredFunctions[$fname] ?? null;
            $functionCalls[] = ['caller' => $file, 'function' => $fname, 'defined_in' => $definedIn];
        }
    }
}

// Graph-Metriken
$outDegree = [];
$inDegree = [];
$edges = [];
foreach ($dependencies as $from => $arr) {
    foreach ($arr as $d) {
        $to = $d['resolved'];
        $edges[] = ['from' => $from, 'to' => $to, 'raw' => $d['raw']];
        $outDegree[$from] = ($outDegree[$from] ?? 0) + 1;
        $inDegree[$to] = ($inDegree[$to] ?? 0) + 1;
    }
}

// Zusammenfassung
$filesCount = count($phpFiles);
$edgesCount = count($edges);

// Ausgabeordner
$outDir = __DIR__ . '/../output';
if (!is_dir($outDir)) mkdir($outDir, 0755, true);

// JSON speichern
$json = [
    'generated_at' => date(DATE_ATOM),
    'root' => realpath($start),
    'files_count' => $filesCount,
    'edges_count' => $edgesCount,
    'edges' => $edges,
    'functions' => $declaredFunctions,
    'function_calls' => $functionCalls,
];
file_put_contents($outDir . '/deps.json', json_encode($json, JSON_PRETTY_PRINT));

// Graphviz DOT erstellen
$dot = "digraph slaed {\n  rankdir=LR;\n  node [shape=box];\n";
function shortn($p, $root) {
    $rp = realpath($p) ?: $p;
    $rroot = realpath($root) ?: $root;
    if (strpos($rp, $rroot) === 0) return substr($rp, strlen($rroot)+1);
    return $rp;
}

foreach ($edges as $e) {
    $from = addslashes(shortn($e['from'], $start));
    $to = addslashes(shortn($e['to'], $start));
    $dot .= "  \"$from\" -> \"$to\";\n";
}
$dot .= "}\n";
file_put_contents($outDir . '/graph.dot', $dot);

// Zusammenfassung ausgeben
arsort($outDegree);
arsort($inDegree);

echo "Gesamtanzahl PHP-Dateien: $filesCount\n";
echo "Gefundene Include-Kanten: $edgesCount\n\n";
echo "Top 10 Dateien nach Out-Degree (inkludieren viele Dateien):\n";
$cnt = 0;
foreach ($outDegree as $f => $c) {
    echo "  $c -> " . shortn($f, $start) . "\n";
    if (++$cnt >= 10) break;
}

echo "\nTop 10 Dateien nach In-Degree (werden oft inkludiert):\n";
$cnt = 0;
foreach ($inDegree as $f => $c) {
    echo "  $c <- " . shortn($f, $start) . "\n";
    if (++$cnt >= 10) break;
}

echo "\nErgebnisse erzeugt: output/deps.json und output/graph.dot\n";

// Ende Skript
?>