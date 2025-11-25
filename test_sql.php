<?php
define('FUNC_FILE', true);
define("_SEC", "сек");
define("_ERROR", "Ошибка");
define("_SQLERRORCON","Проблема установки соединения с базой данных!");

$conf['variables'] = '0,1,1,1,1,1,1,0,1';
$confs['error_log'] = '1';

require 'core/classes/pdo.php'; // Pfad anpassen

$host = 'MariaDB-10.9';
$user = 'root';
$pass = '';
$db   = 'slaed_new';
$table = 'benchmark_table';

$selectQueries = 1000;
$dmlQueries    = 200;
$mixedQueries  = 500;

$sql = new sql_db($host, $user, $pass, $db, 'utf8mb4');
$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// Tabelle vorbereiten
$sql->sql_query("DROP TABLE IF EXISTS $table");
$sql->sql_query("
    CREATE TABLE $table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Initial Rows
for ($i=0;$i<100;$i++){
    $sql->sql_query("INSERT INTO $table (name) VALUES (:name)", [':name'=>"Init_$i"]);
}

// ---------------------------
// Funktionen
function getColor($time){
    if($time<0.05) return 'green';
    if($time<0.2) return 'orange';
    return 'red';
}

function runBenchmark(&$results,$label,$callbackWrapper,$callbackPDO){
    $start=microtime(true);
    $rowsWrapper=$callbackWrapper();
    $end=microtime(true);
    $results[$label]['Wrapper']=['time'=>$end-$start,'rows'=>$rowsWrapper];

    $start=microtime(true);
    $rowsPDO=$callbackPDO();
    $end=microtime(true);
    $results[$label]['PDO']=['time'=>$end-$start,'rows'=>$rowsPDO];
}

// ---------------------------
// Benchmarks
$benchmarksList = [
    'SELECT Row'=>[
        function() use($sql,$selectQueries){$c=0;for($i=0;$i<$selectQueries;$i++){if($sql->sql_fetchrow($sql->sql_query("SELECT * FROM benchmark_table LIMIT 1"))) $c++;}return $c;},
        function() use($pdo,$selectQueries){$c=0;for($i=0;$i<$selectQueries;$i++){if($pdo->query("SELECT * FROM benchmark_table LIMIT 1")->fetch())$c++;}return $c;}
    ],
    'SELECT Set'=>[
        function() use($sql,$selectQueries){$c=0;for($i=0;$i<$selectQueries;$i++){$r=$sql->sql_fetchrowset($sql->sql_query("SELECT * FROM benchmark_table LIMIT 10"));if(is_array($r)) $c+=count($r);}return $c;},
        function() use($pdo,$selectQueries){$c=0;for($i=0;$i<$selectQueries;$i++){$r=$pdo->query("SELECT * FROM benchmark_table LIMIT 10")->fetchAll();if(is_array($r)) $c+=count($r);}return $c;}
    ],
    'INSERT'=>[
        function() use($sql,$dmlQueries){$c=0;for($i=0;$i<$dmlQueries;$i++){$sql->sql_query("INSERT INTO benchmark_table (name) VALUES (:name)",[':name'=>"Wrapper_$i"]);$c+=$sql->sql_affectedrows();}return $c;},
        function() use($pdo,$dmlQueries){$c=0;$stmt=$pdo->prepare("INSERT INTO benchmark_table (name) VALUES (:name)");for($i=0;$i<$dmlQueries;$i++){$stmt->execute([':name'=>"PDO_$i"]);$c+=$stmt->rowCount();}return $c;}
    ],
    'UPDATE'=>[
        function() use($sql,$dmlQueries){$c=0;for($i=1;$i<=$dmlQueries;$i++){$sql->sql_query("UPDATE benchmark_table SET name=:name WHERE id=:id",[':name'=>"Wrapper_Updated_$i",':id'=>$i]);$c+=$sql->sql_affectedrows();}return $c;},
        function() use($pdo,$dmlQueries){$c=0;$stmt=$pdo->prepare("UPDATE benchmark_table SET name=:name WHERE id=:id");for($i=1;$i<=$dmlQueries;$i++){$stmt->execute([':name'=>"PDO_Updated_$i",':id'=>$i]);$c+=$stmt->rowCount();}return $c;}
    ],
    'DELETE'=>[
        function() use($sql,$dmlQueries){$c=0;for($i=1;$i<=$dmlQueries;$i++){$sql->sql_query("DELETE FROM benchmark_table WHERE id=:id",[':id'=>$i]);$c+=$sql->sql_affectedrows();}return $c;},
        function() use($pdo,$dmlQueries){$c=0;$ids=$pdo->query("SELECT id FROM benchmark_table ORDER BY id ASC LIMIT $dmlQueries")->fetchAll(PDO::FETCH_COLUMN);$stmt=$pdo->prepare("DELETE FROM benchmark_table WHERE id=:id");foreach($ids as $id){$stmt->execute([':id'=>$id]);$c+=$stmt->rowCount();}return $c;}
    ],
    'Mixed'=>[
        function() use($sql,$mixedQueries){$c=0;for($i=0;$i<$mixedQueries;$i++){$r=rand(1,4);switch($r){case 1: if($sql->sql_fetchrow($sql->sql_query("SELECT * FROM benchmark_table LIMIT 1"))) $c++; break; case 2: $sql->sql_query("INSERT INTO benchmark_table (name) VALUES (:name)",[':name'=>"Wrapper_Mixed_$i"]);$c+=$sql->sql_affectedrows(); break; case 3: $sql->sql_query("UPDATE benchmark_table SET name=:name WHERE id=:id",[':name'=>"Wrapper_MixedU_$i",':id'=>rand(1,$mixedQueries)]);$c+=$sql->sql_affectedrows(); break; case 4: $sql->sql_query("DELETE FROM benchmark_table WHERE id=:id",[':id'=>rand(1,$mixedQueries)]);$c+=$sql->sql_affectedrows(); break;}}return $c;},
        function() use($pdo,$mixedQueries){$c=0;$stmtInsert=$pdo->prepare("INSERT INTO benchmark_table (name) VALUES (:name)");$stmtUpdate=$pdo->prepare("UPDATE benchmark_table SET name=:name WHERE id=:id");$stmtDelete=$pdo->prepare("DELETE FROM benchmark_table WHERE id=:id");for($i=0;$i<$mixedQueries;$i++){$r=rand(1,4);switch($r){case 1: if($pdo->query("SELECT * FROM benchmark_table LIMIT 1")->fetch())$c++; break; case 2:$stmtInsert->execute([':name'=>"PDO_Mixed_$i"]);$c+=$stmtInsert->rowCount(); break; case 3:$stmtUpdate->execute([':name'=>"PDO_MixedU_$i",':id'=>rand(1,$mixedQueries)]);$c+=$stmtUpdate->rowCount(); break; case 4: $ids=$pdo->query("SELECT id FROM benchmark_table ORDER BY id ASC LIMIT 1")->fetchAll(PDO::FETCH_COLUMN); foreach($ids as $id){$stmtDelete->execute([':id'=>$id]); $c+=$stmtDelete->rowCount();} break;}} return $c;}
    ]
];

// Benchmarks ausführen
$benchmarks=[];
foreach($benchmarksList as $label=>$callbacks){
    runBenchmark($benchmarks,$label,$callbacks[0],$callbacks[1]);
}

// ---------------------------
// Dashboard mit Legende
echo "<h2>Kompaktes Benchmark Dashboard mit Diff Overlay</h2>";

// Legende
echo "<div style='margin-bottom:10px; font-family:Arial,sans-serif; display:flex; gap:15px; flex-wrap:wrap;'>";
echo "<div><span style='display:inline-block;width:15px;height:15px;background-color:green;margin-right:5px;'></span> Zeit Ampel grün (schnell)</div>";
echo "<div><span style='display:inline-block;width:15px;height:15px;background-color:orange;margin-right:5px;'></span> Zeit Ampel orange (mittel)</div>";
echo "<div><span style='display:inline-block;width:15px;height:15px;background-color:red;margin-right:5px;'></span> Zeit Ampel rot (langsam)</div>";
echo "<div><span style='display:inline-block;width:15px;height:15px;background-color:#007BFF;margin-right:5px;'></span> Wrapper Balken</div>";
echo "<div><span style='display:inline-block;width:15px;height:15px;background-color:#28A745;margin-right:5px;'></span> PDO Balken</div>";
echo "<div><span style='display:inline-block;width:15px;height:15px;background-color:rgba(0,200,0,0.5);margin-right:5px;'></span> Diff % Wrapper schneller</div>";
echo "<div><span style='display:inline-block;width:15px;height:15px;background-color:rgba(200,0,0,0.5);margin-right:5px;'></span> Diff % Wrapper langsamer</div>";
echo "</div><br><br>";

// Dashboard Container
echo "<div style='font-family:Arial,sans-serif; display:flex; flex-direction:column; gap:100px;'>";

$maxTime = max(array_map(function($d){return max($d['Wrapper']['time'],$d['PDO']['time']);}, $benchmarks));
$scale = 400/$maxTime; // max Balkenbreite

foreach($benchmarks as $label=>$data){
    $wrapperTime=$data['Wrapper']['time'];
    $pdoTime=$data['PDO']['time'];
    $diffPercent=round(($wrapperTime-$pdoTime)/$pdoTime*100,2);

    $wrapperWidth=$wrapperTime*$scale;
    $pdoWidth=$pdoTime*$scale;

    $wrapperColor='#007BFF'; // Blau für Wrapper
    $pdoColor='#28A745';     // Grün für PDO

    // Diff-Farbe
    $diffColor = ($diffPercent<0) ? 'rgba(0,200,0,0.5)' : 'rgba(200,0,0,0.5)';

    echo "<div style='display:flex; align-items:center; gap:8px; position:relative;'>";

    // Benchmark Name
    echo "<div style='width:120px;'><b>$label</b></div>";

    // Balken Container
    echo "<div style='position:relative; width:500px; height:25px; background:#eee;'>";

    // Wrapper Balken
    echo "<div title='Wrapper: ".round($wrapperTime,4)." s | Rows: ".$data['Wrapper']['rows']."' style='background-color:$wrapperColor; width:{$wrapperWidth}px; height:25px; text-align:right; color:white; padding-right:3px;'>W ".round($wrapperTime,4)."s</div>";

    // PDO Balken
    echo "<div title='PDO: ".round($pdoTime,4)." s | Rows: ".$data['PDO']['rows']."' style='background-color:$pdoColor; width:{$pdoWidth}px; height:25px; text-align:right; color:white; padding-right:3px; margin-left:-2px;'>P ".round($pdoTime,4)."s</div>";

    // Prozentdifferenz Overlay über Balken
    echo "<div style='position:absolute; top:-20px; right:0; width:60px; text-align:center; font-weight:bold; line-height:20px; background-color:$diffColor; border-radius:3px;'>$diffPercent%</div>";

    echo "</div>"; // container
    echo "</div>"; // row
}

echo "</div>";
