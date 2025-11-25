<?php
// Anzahl der Schreibvorgänge
$iterations = 1000;
$data = sha1("127.0.0.1");

// Testdateien
$file1 = __DIR__ . "/test_fwrite.txt";
$file2 = __DIR__ . "/test_put.txt";

// Alte Dateien löschen
@unlink($file1);
@unlink($file2);

// Benchmark fopen/fwrite
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $fp = fopen($file1, "ab");
    flock($fp, LOCK_EX);
    fwrite($fp, $data);
    flock($fp, LOCK_UN);
    fclose($fp);
}
$time1 = microtime(true) - $start;

// Benchmark file_put_contents
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    file_put_contents($file2, $data, FILE_APPEND | LOCK_EX);
}
$time2 = microtime(true) - $start;

// Ergebnis
echo "fwrite: {$time1} Sekunden\n<br>";
echo "file_put_contents: {$time2} Sekunden\n";
