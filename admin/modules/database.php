<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function addDblog(string $text): void {
    $path = LOGS_DIR.'/database.log';
    $line = '['.date('Y-m-d H:i:s').'] '.$text.PHP_EOL;
    file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

function getSqlbatch(string $sql): array {
    $sql = str_replace("\r\n", "\n", str_replace("\r", "\n", $sql));
    $len = strlen($sql);
    $dlim = ';';
    $buff = '';
    $list = [];
    $quot = '';
    $lcom = false;
    $bcom = false;
    $sol = true;

    for ($num = 0; $num < $len; $num++) {
        $char = $sql[$num];
        $next = ($num + 1 < $len) ? $sql[$num + 1] : '';

        if ($sol && !$quot && !$lcom && !$bcom) {
            $lend = strpos($sql, "\n", $num);
            $lend = ($lend === false) ? $len : $lend;
            $line = substr($sql, $num, $lend - $num);
            if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $mass)) {
                $dlim = $mass[1];
                $num = $lend;
                $sol = true;
                continue;
            }
        }

        if ($lcom) {
            $buff .= $char;
            $sol = ($char === "\n");
            if ($char === "\n") $lcom = false;
            continue;
        }

        if ($bcom) {
            $buff .= $char;
            if ($char === '*' && $next === '/') {
                $buff .= '/';
                $num++;
                $bcom = false;
                $sol = false;
                continue;
            }
            $sol = ($char === "\n");
            continue;
        }

        if ($quot !== '') {
            $buff .= $char;
            if ($char === '\\' && $quot !== '`' && $next !== '') {
                $buff .= $next;
                $num++;
                $sol = false;
                continue;
            }
            if ($char === $quot) {
                if ($quot !== '`' && $next === $quot) {
                    $buff .= $next;
                    $num++;
                } else {
                    $quot = '';
                }
            }
            $sol = ($char === "\n");
            continue;
        }

        if ($char === '-' && $next === '-' && (($num + 2 >= $len) || preg_match('/\s/', $sql[$num + 2]))) {
            $buff .= $char.$next;
            $num++;
            $lcom = true;
            $sol = false;
            continue;
        }
        if ($char === '#') {
            $buff .= $char;
            $lcom = true;
            $sol = false;
            continue;
        }
        if ($char === '/' && $next === '*') {
            $buff .= $char.$next;
            $num++;
            $bcom = true;
            $sol = false;
            continue;
        }
        if ($char === '\'' || $char === '"' || $char === '`') {
            $buff .= $char;
            $quot = $char;
            $sol = false;
            continue;
        }

        if ($dlim !== '' && substr($sql, $num, strlen($dlim)) === $dlim) {
            $stmt = trim($buff);
            if ($stmt !== '') $list[] = $stmt;
            $buff = '';
            $num += strlen($dlim) - 1;
            $sol = false;
            continue;
        }

        $buff .= $char;
        $sol = ($char === "\n");
    }

    if ($quot !== '') return ['statements' => $list, 'error' => 'Unclosed quoted string in SQL input'];
    if ($bcom) return ['statements' => $list, 'error' => 'Unclosed block comment in SQL input'];

    $stmt = trim($buff);
    if ($stmt !== '') $list[] = $stmt;

    return ['statements' => $list, 'error' => ''];
}

function getSqlinfo(string $sql): array {
    $type = 'SQL';
    $table = '';
    if (preg_match('/^\s*([A-Z]+)/i', $sql, $mass)) $type = strtoupper($mass[1]);
    if (preg_match('/`([^`]+)`/', $sql, $mass)) $table = $mass[1];
    return ['type' => $type, 'table' => $table];
}

function checkDblock(): bool {
    global $db;
    $result = $db->getSqlQuery("SELECT GET_LOCK('slaed_db_migration_runner', 0)");
    if (!$result) return false;
    $row = $db->getSqlRow($result);
    return (int)($row[0] ?? 0) === 1;
}

function deleteDblock(): void {
    global $db;
    $db->getSqlQuery("SELECT RELEASE_LOCK('slaed_db_migration_runner')");
}

function getSqltable(array $items): string {
    if (!$items) return '';
    $head = getTplAdminTableHead([_ID, _TYPE, _TABLE, [_DB_SQL, 'nosort'], [_STATUS, 'nosort']]);
    $rows = '';
    foreach ($items as $row) {
        $sql = htmlspecialchars(cutstr(preg_replace('/\s+/', ' ', trim($row['sql'])), 160));
        $tab = ($row['table'] !== '') ? htmlspecialchars($row['table']) : _NO;
        $status = getTplAdminFlagBox((bool)$row['ok'], _OK, _ERROR.' - '.$row['error']);
        $rows .= getTplAdminTableRow(getTplAdminTableCells([
            (string)(int)$row['num'],
            htmlspecialchars($row['type']),
            $tab,
            $sql,
            $status,
        ]));
    }
    return getTplAdminTable($head, $rows);
}

function getSqlsum(array $items, string $mode, string $name): string {
    global $tpl;
    $all = count($items);
    $good = 0;
    $bad = 0;
    $stop = 0;
    foreach ($items as $row) {
        if (!empty($row['ok'])) {
            $good++;
        } else {
            $bad++;
            if (!$stop) $stop = (int)$row['num'];
        }
    }
    $stat = getTplAdminFlagBox($bad === 0, _OK, _ERROR);
    $mval = ($mode === 'dump') ? _DB_RUNMODE : _DB_PARSEMODE;
    $text = getTplAdminInfoLine(_INQUIRY, $name)
        .getTplAdminInfoLine(_DB_MODE, $mval)
        .getTplAdminInfoLine(_DB_BLOCKS, (string)$all)
        .getTplAdminInfoLine('OK', (string)$good)
        .getTplAdminInfoLine(_DB_ERRORS, (string)$bad)
        .getTplAdminInfoLine(_STATUS, $stat);
    if ($stop) {
        $text .= getTplAdminInfoLine(_DB_STOP, (string)$stop);
    }
    return $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => $text]);
}


function database(): void {
    global $db, $conf, $afile, $tpl;
    $type = getVar('get', 'type', 'var');
    $headtag = ($type === 'optimize' || $type === 'repair') ? _STATUS : _FUNCTIONS;
    $dbname = preg_replace('#[^a-zA-Z0-9_]#', '', (string)($conf['db']['name'] ?? ''));
    if ($dbname === '') {
        setHead();
        $cont = getTplAdminNavi(['ops' => ['name=database', 'name=database&amp;type=optimize', 'name=database&amp;type=repair', 'name=database&amp;op=dump', 'name=database&amp;op=info'], 'tabs' => [_HOME, _OPTIMIZE, _REPAIR, _INQUIRY, _INFO]]);
        echo $cont.$tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _ERROR]);
        setFoot();
        return;
    }
    $result = $db->getSqlQuery('SHOW TABLE STATUS FROM `'.$dbname.'`');
    $tables = [];
    while ($info = $db->getSqlRow($result)) {
        $tables[] = $info;
    }

    $total = 0;
    $sumfree = 0;
    $allrows = 0;
    $item = 0;

    $dbhead = getTplAdminTableHead([_ID, _TABLE, _TYPE, _DBCOLL, _ROWS, _DATE, _SIZE, [_DBFREE, 'nosort'], [$headtag, 'nosort']]);
    $dbrows = '';

    foreach ($tables as $info) {
        $name = $info['Name'];
        $tabeng = $info['Engine'];
        $tabloc = $info['Collation'];
        $crtime = $info['Create_time'];
        $rows = (int) $info['Rows'];
        $res = $db->getSqlQuery('SELECT COUNT(*) AS cnt FROM `'.$dbname.'`.`'.$name.'`');
        if ($res && $row = $db->getSqlRow($res)) $rows = (int) $row['cnt'];
        $allrows += $rows;

        // --- Table and free space size ---
        $tabsize = (int) $info['Data_length'] + (int) $info['Index_length'];
        $tabfree = (int) ($info['Data_free'] ?: 0);

        $total += $tabsize;
        $sumfree += $tabfree;

        // Free space display
        $freetag = $tabeng === 'InnoDB'
            ? $tpl->getHtmlFrag('admin-flag-box', ['css_class' => 'sl_hidden', 'label_text' => filterSize($tabfree)])
            : $tpl->getHtmlFrag('admin-flag-box', ['css_class' => $tabfree ? 'sl_red' : 'sl_green', 'label_text' => filterSize($tabfree)]);

        // --- Status / actions depending on mode ---
        if (!preg_match('#^[a-zA-Z0-9_]+$#', (string)$name)) {
            continue;
        }
        if ($type === 'optimize') {
            $db->getSqlQuery('ANALYZE TABLE `'.$dbname.'`.`'.$name.'`');
            $oresult = $db->getSqlQuery('OPTIMIZE TABLE `'.$dbname.'`.`'.$name.'`');

            if (!$oresult) {
                $stattag = $tpl->getHtmlFrag('admin-flag-box', ['css_class' => 'sl_red', 'label_text' => _ERROR]);
            } elseif ($tabeng === 'InnoDB') {
                $stattag = $tpl->getHtmlFrag('admin-flag-box', ['css_class' => 'sl_green', 'label_text' => _OPTIMIZED]);
            } elseif ($tabeng === 'MyISAM' && !$info['Data_free']) {
                $stattag = $tpl->getHtmlFrag('admin-flag-box', ['css_class' => 'sl_red', 'label_text' => _ALREADYOPTIMIZED]);
            } else {
                $stattag = $tpl->getHtmlFrag('admin-flag-box', ['css_class' => 'sl_green', 'label_text' => _OPTIMIZED]);
            }

        } elseif ($type === 'repair') {
            if ($tabeng === 'InnoDB') {
                $stattag = $tpl->getHtmlFrag('admin-flag-box', ['css_class' => 'sl_hidden', 'label_text' => _NO]);
            } else {
                $rresult = $db->getSqlQuery('REPAIR TABLE `'.$dbname.'`.`'.$name.'`');
                $stattag = $tpl->getHtmlFrag('admin-flag-box', ['css_class' => $rresult ? 'sl_green' : 'sl_red', 'label_text' => $rresult ? _OK : _ERROR]);
            }

        } else {
            // Default view with actions
            $stattag = getTplAdminActionMenu([
                getTplAdminDeleteAction($afile.'.php?name=database&amp;op=delete&amp;tb='.$name.'&amp;id=1', _CLEAN.' "'.$name.'"?', _CLEAN, _CLEAN),
                getTplAdminDeleteAction($afile.'.php?name=database&amp;op=delete&amp;tb='.$name.'&amp;id=2', _DELETE.' "'.$name.'"?', _ONDELETE, _ONDELETE),
            ]);
        }

        $item++;

        $dbrows .= getTplAdminTableRow(getTplAdminTableCells([
            (string)$item,
            $name,
            $tabeng,
            $tabloc,
            (string)$rows,
            format_time($crtime, _TIMESTRING),
            filterSize($tabsize),
            $freetag,
            $stattag,
        ]));
    }

    $dbrows .= getTplAdminTableRow(getTplAdminTableCells([
        '<strong>'.(string)$item.'</strong>',
        '&nbsp;',
        '&nbsp;',
        '&nbsp;',
        '<strong>'.(string)$allrows.'</strong>',
        '&nbsp;',
        '<strong>'.filterSize($total).'</strong>',
        '<strong>'.filterSize($sumfree).'</strong>',
        '&nbsp;',
    ]), '', 'data-sort-method="none"');
    $content = getTplAdminTable($dbhead, $dbrows);

    // After OPTIMIZE: Totals to recalculate info box
    if ($type === 'optimize') {
        $result = $db->getSqlQuery('SHOW TABLE STATUS FROM `'.$dbname.'`');
        $total = 0;
        $sumfree = 0;

        while ($info = $db->getSqlRow($result)) {
            $tabsize = (int) $info['Data_length'] + (int) $info['Index_length'];
            $tabfree = (int) ($info['Data_free'] ?: 0);

            $total += $tabsize;
            $sumfree += $tabfree;
        }
    }

    setHead();

    // Navigation + Info-Boxen
    if (empty($type)) {
        $cont = getTplAdminNavi(['ops' => ['name=database', 'name=database&amp;type=optimize', 'name=database&amp;type=repair', 'name=database&amp;op=dump', 'name=database&amp;op=info'], 'tabs' => [_HOME, _OPTIMIZE, _REPAIR, _INQUIRY, _INFO]]);
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _OPTTEXT]);
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _REPTEXT]);

    } elseif ($type === 'optimize') {
        $db->getSqlQuery('FLUSH TABLES');
        $cont = getTplAdminNavi(['ops' => ['name=database', 'name=database&amp;type=optimize', 'name=database&amp;type=repair', 'name=database&amp;op=dump', 'name=database&amp;op=info'], 'tabs' => [_HOME, _OPTIMIZE, _REPAIR, _INQUIRY, _INFO], 'tab' => 1]);

        $info = _OPTIMIZE.': '.$conf['db']['name'].getTplAdminTipLine(_TOTALSPACE, filterSize($total)).getTplAdminTipLine(_TOTALFREE, filterSize($sumfree));

        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => $info]);

    } elseif ($type === 'repair') {
        $cont = getTplAdminNavi(['ops' => ['name=database', 'name=database&amp;type=optimize', 'name=database&amp;type=repair', 'name=database&amp;op=dump', 'name=database&amp;op=info'], 'tabs' => [_HOME, _OPTIMIZE, _REPAIR, _INQUIRY, _INFO], 'tab' => 2]);

        $info = _REPAIR.': '.$conf['db']['name'].getTplAdminTipLine(_TOTALSPACE, filterSize($total)).getTplAdminTipLine(_TOTALFREE, filterSize($sumfree));

        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => $info]);
    }

    echo $cont.getTplBox($content);

    setFoot();
}

function dump(): void {
    global $db, $conf, $afile, $tpl;
    $type = getVar('post', 'type', 'var', '');
    $string = getVar('post', 'string', 'raw', '');
    $action = getVar('post', 'action', 'var', '');
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=database', 'name=database&amp;type=optimize', 'name=database&amp;type=repair', 'name=database&amp;op=dump', 'name=database&amp;op=info'], 'tabs' => [_HOME, _OPTIMIZE, _REPAIR, _INQUIRY, _INFO], 'tab' => 3]);
    if ($type === 'dump' && !empty($string) && ($action === 'parse' || $action === 'dump')) {
        $subst = ['{prefix}' => $conf['db']['prefix'], '{engine}' => $conf['db']['engine'], '{charset}' => $conf['db']['charset'], '{collate}' => $conf['db']['collate']];
        $parsed = getSqlbatch(stripslashes($string));
        if ($parsed['error'] !== '') {
            $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => htmlspecialchars($parsed['error'])]);
        } else {
            $items = [];
            foreach ($parsed['statements'] as $query) {
                $items[] = str_replace(array_keys($subst), array_values($subst), $query);
            }
            $reslist = [];
            if ($action === 'dump') {
                if (!checkDblock()) {
                    $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => 'Another migration run is already active']);
                } else {
                    addDblog('START blocks='.count($items));
                    try {
                        foreach ($items as $index => $sql) {
                            $info = getSqlinfo($sql);
                            $result = $db->getSqlQuery($sql);
                            $ok = (bool)$result;
                            $error = '';
                            if (!$ok) {
                                $dberr = $db->getSqlError();
                                $error = trim(($dberr['sqlstate'] ?? '').' / '.($dberr['code'] ?? '').' / '.($dberr['message'] ?? 'SQL error'), ' /');
                            }
                            $reslist[] = ['num' => $index + 1, 'type' => $info['type'], 'table' => $info['table'], 'ok' => $ok, 'error' => $error, 'sql' => $sql];
                            addDblog(($ok ? 'OK ' : 'ERR ').'block='.($index + 1).' type='.$info['type'].' table='.$info['table'].($error ? ' error='.$error : ''));
                            if (!$ok) break;
                        }
                    } finally {
                        deleteDblock();
                    }
                }
            } else {
                foreach ($items as $index => $sql) {
                    $info = getSqlinfo($sql);
                    $reslist[] = ['num' => $index + 1, 'type' => $info['type'], 'table' => $info['table'], 'ok' => true, 'error' => '', 'sql' => $sql];
                }
            }
            $cont .= getSqlsum($reslist, $action, $conf['db']['name']);
            $cont .= getTplBox(getSqltable($reslist));
        }
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _DBINFO]);
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _DBWARN]);
    }
    $fhide = getTplHiddenInput('name', 'database').getTplHiddenInput('op', 'dump').getTplHiddenInput('type', 'dump');
    $frows = getTplAdminFormWide(textarea_code('code', 'string', 'sl_form', 'text/x-mysql', stripslashes($string)));
    $actions = $tpl->getHtmlFrag('admin-input', [
        'itype' => 'submit',
        'name_attr' => 'action',
        'value_attr' => 'parse',
        'input_class' => 'sl_but_blue',
        'input_attr' => '',
    ]);
    $actions .= ' ';
    $actions .= $tpl->getHtmlFrag('admin-input', [
        'itype' => 'submit',
        'name_attr' => 'action',
        'value_attr' => 'dump',
        'input_class' => 'sl_but_blue',
        'input_attr' => '',
    ]);
    $frows .= getTplAdminFormWide($actions, '', 'sl_center');
    echo $cont.getTplBox(getTplAdminForm($afile.'.php', $frows, $fhide, 'sl_table_edit'));
    setFoot();
}

function info(): void {
    $cont = getTplAdminNavi(['ops' => ['name=database', 'name=database&amp;type=optimize', 'name=database&amp;type=repair', 'name=database&amp;op=dump', 'name=database&amp;op=info'], 'tabs' => [_HOME, _OPTIMIZE, _REPAIR, _INQUIRY, _INFO], 'tab' => 4]);
    setAdminInfoPage($cont);
}

function delete(): void {
    global $db, $afile;
    $tb = getVar('get', 'tb', 'var');
    $id = getVar('get', 'id', 'num');
    $tb = preg_match('#^[a-zA-Z0-9_]+$#', (string)$tb) ? $tb : '';
    if ($tb && $id == 1) {
        $db->getSqlQuery('TRUNCATE TABLE `'.$tb.'`');
    } elseif ($tb && $id == 2) {
        $db->getSqlQuery('DROP TABLE `'.$tb.'`');
    }
    setRedirect($afile.'.php?name=database');
}

switch ($op) {
    default: database(); break;
    case 'dump': dump(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
