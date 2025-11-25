<?php
# Author: Eduard Laas
# Copyright © 2005 - 2025 SLAED
# License: GNU GPL 3
# Website: slaed.net

define('FUNC_FILE', true);

// Benchmark-Funktion wie vorher
function benchmark($db, $label, $iterations = 1000) {
    $times = [];

    echo "<h3>$label Benchmark ($iterations Iterationen)</h3>";

    // SELECT
    $start = microtime(true);
    for ($i = 1; $i <= $iterations; $i++) {
        $result = $db->sql_query("SELECT id, username FROM users WHERE id=1");
        $row = $db->sql_fetchrow($result);
    }
    $times['SELECT'] = microtime(true) - $start;

    // INSERT
    $start = microtime(true);
    for ($i = 1; $i <= $iterations; $i++) {
        $db->sql_query("INSERT INTO users (username,email) VALUES ('BenchmarkUser','benchmark@example.com')");
        $lastId = $db->sql_nextid();
    }
    $times['INSERT'] = microtime(true) - $start;

    // UPDATE
    $start = microtime(true);
    for ($i = 1; $i <= $iterations; $i++) {
        $db->sql_query("UPDATE users SET email='updated@example.com' WHERE username='BenchmarkUser'");
    }
    $times['UPDATE'] = microtime(true) - $start;

    // DELETE
    $start = microtime(true);
    for ($i = 1; $i <= $iterations; $i++) {
        $db->sql_query("DELETE FROM users WHERE username='BenchmarkUser'");
    }
    $times['DELETE'] = microtime(true) - $start;

    return $times;
}

include('config/db.php');
include_once('language/lang-russian.php');

// --- Alte mysqli-Klasse ---
require 'core/classes/mysqli.php';
$db_mysqli = new sql_db($confdb['host'], $confdb['uname'], $confdb['pass'], $confdb['name'], $confdb['charset']);
#$db_mysqli = new sql_db('localhost', 'user', 'pass', 'slaed');
$times_mysqli = benchmark($db_mysqli, "Alte mysqli-Klasse");

// --- Neue PDO-Klasse ---
require 'core/classes/pdo.php';
$db_pdo = new sql_db($confdb['host'], $confdb['uname'], $confdb['pass'], $confdb['name'], $confdb['charset']);
#$db_pdo = new sql_db('localhost', 'user', 'pass', 'slaed');
$times_pdo = benchmark($db_pdo, "Neue PDO-Klasse");

// --- Grafische Ausgabe ---
echo "<h2>Benchmark Vergleich</h2>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Operation</th><th>Alte mysqli-Klasse (s)</th><th>Neue PDO-Klasse (s)</th><th>Balken</th></tr>";

$maxTime = max(array_merge($times_mysqli, $times_pdo));

foreach (['SELECT','INSERT','UPDATE','DELETE'] as $op) {
    $width_mysqli = intval(($times_mysqli[$op] / $maxTime) * 200);
    $width_pdo = intval(($times_pdo[$op] / $maxTime) * 200);

    echo "<tr>";
    echo "<td>$op</td>";
    echo "<td>".$times_mysqli[$op]."</td>";
    echo "<td>".$times_pdo[$op]."</td>";
    echo "<td>";
    echo "<div style='display:inline-block; background-color:blue; width:{$width_mysqli}px; height:20px;'></div>";
    echo "<div style='display:inline-block; background-color:red; width:{$width_pdo}px; height:20px; margin-left:5px;'></div>";
    echo "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p style='color:blue;'>Blaue Balken = alte mysqli-Klasse</p>";
echo "<p style='color:red;'>Rote Balken = neue PDO-Klasse</p>";
