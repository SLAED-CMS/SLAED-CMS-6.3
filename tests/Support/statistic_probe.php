<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the one reading of the counter behind the statistic page: a sample counter in scratch, a clock of its own, and the facts the test compares with the fields
$probework = (string)($argv[1] ?? '');
require_once __DIR__.'/probe_boot.php';
if (!is_dir($probework.'/statistic')) mkdir($probework.'/statistic', 0777, true);
require_once BASE_DIR.'/core/system.php';

# One line of the counter: the eight fields of every build, and with hours the session maps and the load of the later ones
function getProbeLine(string $date, int $base, array $hours = []): string {
    $line = $date.'|'.$base.'|'.($base * 5).'|'.($base * 50).'|'.($base / 10).'|'.($base / 5).'|'.($base * 2).'|'.($base / 100);
    if ($hours === []) return $line;
    $list = array_replace(array_fill(0, 24, 0), $hours);
    $load = static fn(int $by): string => implode(',', array_map(static fn(int $num): int => $num * $by, $list));
    return $line.'|Chrome 1:30,Other:10|Windows:40|desktop:20,mobile:15,bot:5|ru:8,de:2|direct:6,search:3,referrer:1|'.implode(',', $list)
        .'|new:6,returning:2|1:4,8+:4|<30s:7,15m+:1|'.$load(70).'|'.$load(20).'|'.$load(2048);
}

# Write a file of the counter from its lines
function setProbeFile(string $name, array $lines): void {
    file_put_contents(COUNTER_DIR.'/'.$name, $lines === [] ? '' : implode("\n", $lines)."\n");
}

# Four Sundays of September with their own traffic at three o'clock, a Saturday that must not count, and the month before it left as a gap
setProbeFile('statistic/statistic_2026-07.log', [getProbeLine('30.07.2026', 300), getProbeLine('31.07.2026', 500)]);
setProbeFile('statistic/statistic_2026-09.log', [
    getProbeLine('03.09.2026', 100),
    getProbeLine('05.09.2026', 100, [15 => 900]),
    getProbeLine('06.09.2026', 100, [15 => 40]),
    getProbeLine('13.09.2026', 100, [15 => 50]),
    getProbeLine('20.09.2026', 200, [15 => 60]),
    getProbeLine('27.09.2026', 200, [15 => 70]),
]);
setProbeFile('days.log', [getProbeLine('02.10.2026', 200, [9 => 10, 15 => 5]), getProbeLine('03.10.2026', 300, [9 => 20, 15 => 5])]);
setProbeFile('statistic.log', [getProbeLine('04.10.2026', 100, [14 => 60, 15 => 80])]);
$pclock = mktime(15, 30, 0, 10, 4, 2026);
$pmonth = getStatData('', -1, true, $pclock);
$pout = [
    'month' => $pmonth,
    'yesterday' => getStatData('', -1, false, $pclock),
    'picked' => getStatData('', 0, true, $pclock),
    'closed' => getStatData('2026-09', -1, true, $pclock),
    'old' => getStatData('2026-07', -1, true, $pclock),
    'wrong' => getStatData('../../config/db', -1, true, $pclock),
    'archive' => getStatArchive(),
];
$pfile = COUNTER_DIR.'/statistic/statistic_2026-07.log';
$pstamp = (int)filemtime($pfile);
$psame = str_replace('|300|1500|', '|900|4500|', (string)file_get_contents($pfile));
file_put_contents($pfile, $psame);
touch($pfile, $pstamp);
$pout['kept'] = getStatArchive()['2026-07'];
touch($pfile, $pstamp + 60);
$pout['fresh'] = getStatArchive()['2026-07'];
$pout['rate'] = [
    getStatsHourRate(array_replace(array_fill(0, 24, 0), [14 => 60, 15 => 80]), $pclock),
    getStatsHourRate(array_replace(array_fill(0, 24, 0), [0 => 10]), mktime(0, 30, 0, 10, 4, 2026)),
];
$pace = static function (int $rate, array $norm) use ($pclock): array {
    $live = getStatRow(getProbeLine('04.10.2026', 100, [15 => $rate]));
    $days = ['06.09.2026', '13.09.2026', '20.09.2026', '27.09.2026'];
    $past = array_map(static fn(string $date, int $num): array => getStatRow(getProbeLine($date, 100, [15 => $num])), $days, $norm);
    return getStatPace($live, $past, $pclock);
};
$pout['pace'] = [
    'lull' => $pace(10, [100, 100, 100, 100]),
    'calm' => $pace(30, [100, 100, 100, 100]),
    'day' => $pace(100, [100, 100, 100, 100]),
    'rise' => $pace(150, [100, 100, 100, 100]),
    'rush' => $pace(300, [100, 100, 100, 100]),
    'noise' => $pace(300, [5, 5, 5, 5]),
];
setProbeFile('statistic.log', [getProbeLine('30.09.2026', 400, [3 => 7])]);
setProbeFile('days.log', []);
$pout['rollover'] = getStatData('', -1, true, mktime(0, 5, 0, 10, 1, 2026));
$pout['before'] = getStatData('2026-09', -1, true, mktime(0, 5, 0, 10, 1, 2026));
setProbeFile('statistic.log', [getProbeLine('15.08.2026', 400)]);
$pout['stale'] = getStatData('2026-08', -1, true, $pclock);
$pout['staleset'] = array_keys(getStatArchive($pclock));
$pout['stalenow'] = array_keys(getStatArchive(mktime(0, 0, 0, 8, 20, 2026)));
# The rules of the view live in the module of the panel, which ends in its routing: lifted from it without its guard and its switch
require_once BASE_DIR.'/lang/en.php';
require_once BASE_DIR.'/admin/lang/en.php';
$pcode = (string)file_get_contents(BASE_DIR.'/admin/modules/statistic.php');
$pcode = substr($pcode, (int)strpos($pcode, '# The name of a month'));
eval(substr($pcode, 0, (int)strpos($pcode, 'switch ($op)')));
$pmap = array_combine(['a', 'b', 'c', 'd', 'e', 'f', 'g'], [70, 60, 50, 40, 30, 20, 10]);
$pout['six'] = array_column(getStatRowList(array_slice($pmap, 0, 6), [], [], 6), 'key');
$pout['seven'] = array_column(getStatRowList($pmap, [], [], 6), 'num');
$pout['spare'] = array_column(getStatRowList(array_slice($pmap, 0, 5) + ['Other' => 9], [], [], 6), 'key');
$prun = static fn(int $num): array => array_map(static fn(int $i): array => getStatRow(getProbeLine(sprintf('%02d.09.2026', $i), 100)), range(1, $num));
$pspan = static fn(int $num): array => getStatRhythm(['day' => null, 'past' => null, 'run' => $prun($num)])['periods'][1];
$pout['week10'] = $pspan(10)['delta'];
$pout['week14'] = $pspan(14)['delta'];
echo json_encode($pout, JSON_UNESCAPED_UNICODE);
