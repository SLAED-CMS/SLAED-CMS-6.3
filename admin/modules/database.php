<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function addDblog(string $text): void {
    $path = LOGS_DIR.'/database.log';
    $line = '['.date('Y-m-d H:i:s').'] '.$text.PHP_EOL;
    file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
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
    global $tpl;
    if (!$items) return '';
    $rows = [];
    foreach ($items as $row) {
        $sql = cutstr(preg_replace('/\s+/', ' ', trim($row['sql'])), 160);
        $tab = ($row['table'] !== '') ? (string)$row['table'] : _NO;
        $status = $tpl->getHtmlFrag('inline-badge', [
            'is_success' => $row['ok'],
            'is_danger' => !$row['ok'],
            'label' => $row['ok'] ? _OK : _ERROR.' - '.$row['error'],
        ]);
        $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['content_html' => (string)(int)$row['num']],
                ['has_content_text' => true, 'content_text' => (string)$row['type']],
                ['has_content_text' => true, 'content_text' => $tab],
                ['has_content_text' => true, 'content_text' => $sql],
                ['content_html' => $status],
            ],
        ])]);
    }
    return $tpl->getHtmlFrag('table', [
        'is_fixed' => true,
        'head' => [
            ['content' => _ID],
            ['content' => _TYPE],
            ['content' => _TABLE],
            ['content' => _DB_SQL, 'nosort' => true],
            ['content' => _STATUS, 'nosort' => true],
        ],
        'rows_html' => implode('', $rows),
        'is_wrapless' => true,
    ]);
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
    $mval = ($mode === 'dump') ? _DB_RUNMODE : _DB_PARSEMODE;
    $lines = [
        _INQUIRY.': '.$name,
        _DB_MODE.': '.$mval,
        _DB_BLOCKS.': '.$all,
        'OK: '.$good,
        _DB_ERRORS.': '.$bad,
        _STATUS.': '.($bad === 0 ? _OK : _ERROR),
    ];
    if ($stop) {
        $lines[] = _DB_STOP.': '.$stop;
    }
    return $tpl->getHtmlFrag('alert', ['messages' => $lines]);
}


function database(): void {
    global $db, $conf, $afile, $tpl;
    $type = getVar('get', 'type', 'var');
    $ops = ['name=database', 'name=database&type=optimize', 'name=database&type=repair', 'name=database&op=dump', 'name=database&op=info'];
    $tabs = [_HOME, _OPTIMIZE, _REPAIR, _INQUIRY, _DOCS];
    $headtag = ($type === 'optimize' || $type === 'repair') ? _STATUS : _FUNCTIONS;
    $dbname = preg_replace('#[^a-zA-Z0-9_]#', '', (string)($conf['db']['name'] ?? ''));
    if ($dbname === '') {
        setHead();
        $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs]);
        echo $cont.$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _ERROR]);
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

    $dbrows = [];

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
            ? $tpl->getHtmlFrag('inline-badge', ['is_dimmed' => true, 'label' => filterSize($tabfree)])
            : $tpl->getHtmlFrag('inline-badge', ['is_danger' => (bool)$tabfree, 'is_success' => !$tabfree, 'label' => filterSize($tabfree)]);

        // --- Status / actions depending on mode ---
        if (!preg_match('#^[a-zA-Z0-9_]+$#', (string)$name)) {
            continue;
        }
        if ($type === 'optimize') {
            $db->getSqlQuery('ANALYZE TABLE `'.$dbname.'`.`'.$name.'`');
            $oresult = $db->getSqlQuery('OPTIMIZE TABLE `'.$dbname.'`.`'.$name.'`');

            if (!$oresult) {
                $stattag = $tpl->getHtmlFrag('inline-badge', ['is_danger' => true, 'label' => _ERROR]);
            } elseif ($tabeng === 'InnoDB') {
                $stattag = $tpl->getHtmlFrag('inline-badge', ['is_success' => true, 'label' => _OPTIMIZED]);
            } elseif ($tabeng === 'MyISAM' && !$info['Data_free']) {
                $stattag = $tpl->getHtmlFrag('inline-badge', ['is_danger' => true, 'label' => _ALREADYOPTIMIZED]);
            } else {
                $stattag = $tpl->getHtmlFrag('inline-badge', ['is_success' => true, 'label' => _OPTIMIZED]);
            }

        } elseif ($type === 'repair') {
            if ($tabeng === 'InnoDB') {
                $stattag = $tpl->getHtmlFrag('inline-badge', ['is_dimmed' => true, 'label' => _NO]);
            } else {
                $rresult = $db->getSqlQuery('REPAIR TABLE `'.$dbname.'`.`'.$name.'`');
                $stattag = $tpl->getHtmlFrag('inline-badge', ['is_success' => (bool)$rresult, 'is_danger' => !$rresult, 'label' => $rresult ? _OK : _ERROR]);
            }

        } else {
            // Default view with actions
            $stattag = $tpl->getHtmlFrag('dial', [
                'dial_title' => _EDITOR,
                'dial' => [
                    getTplPostAction(['name' => 'database', 'op' => 'delete', 'tb' => $name, 'id' => 1], 'eraser', _CLEAN, _CLEAN.' "'.$name.'"?'),
                    getTplPostAction(['name' => 'database', 'op' => 'delete', 'tb' => $name, 'id' => 2], 'trash', _ONDELETE, _DELETE.' "'.$name.'"?'),
                ],
            ]);
        }

        $item++;

        $dbrows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['is_col_id' => true, 'content_html' => (string)$item],
                ['title_text' => $name, 'has_content_text' => true, 'content_text' => $name],
                ['has_content_text' => true, 'content_text' => $tabeng],
                ['title_text' => $tabloc, 'has_content_text' => true, 'content_text' => $tabloc],
                ['is_col_count' => true, 'has_content_text' => true, 'content_text' => (string)$rows],
                ['is_col_date' => true, 'has_content_text' => true, 'content_text' => format_time($crtime, _TIMESTRING)],
                ['is_col_count' => true, 'has_content_text' => true, 'content_text' => filterSize($tabsize)],
                ['is_col_count' => true, 'content_html' => $freetag],
                ['is_col_actions' => true, 'content_html' => $stattag],
            ],
        ])]);
    }

    $dbrows[] = $tpl->getHtmlFrag('table-row', [
        'is_no_sort' => true,
        'cells_html' => $tpl->getHtmlFrag('table-cells', [
            'is_summary' => true,
            'cells' => [
                ['is_col_id' => true, 'content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => (string)$item])],
                ['has_content_text' => true, 'content_text' => ''],
                ['has_content_text' => true, 'content_text' => ''],
                ['has_content_text' => true, 'content_text' => ''],
                ['is_col_count' => true, 'content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => (string)$allrows])],
                ['is_col_date' => true, 'has_content_text' => true, 'content_text' => ''],
                ['is_col_count' => true, 'content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => filterSize($total)])],
                ['is_col_count' => true, 'content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => filterSize($sumfree)])],
                ['is_col_actions' => true, 'has_content_text' => true, 'content_text' => ''],
            ],
        ]),
    ]);
    $content = $tpl->getHtmlFrag('table', [
        'head' => [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _TABLE],
            ['content' => _TYPE],
            ['content' => _DBCOLL],
            ['content' => _ROWS, 'is_col_count' => true],
            ['content' => _DATE, 'is_col_date' => true],
            ['content' => _SIZE, 'is_col_count' => true],
            ['content' => _DBFREE, 'is_col_count' => true, 'nosort' => true],
            ['content' => $headtag, 'is_col_actions' => true, 'nosort' => true],
        ],
        'rows_html' => implode('', $dbrows),
        'is_wrapless' => true,
    ]);

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
        $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _OPTTEXT]);
        $cont .= $tpl->getHtmlFrag('alert', ['text' => _REPTEXT]);

    } elseif ($type === 'optimize') {
        $db->getSqlQuery('FLUSH TABLES');
        $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => 1]);

        $cont .= $tpl->getHtmlFrag('alert', ['messages' => [
            _OPTIMIZE.': '.$conf['db']['name'],
            _TOTALSPACE.': '.filterSize($total),
            _TOTALFREE.': '.filterSize($sumfree),
        ]]);

    } elseif ($type === 'repair') {
        $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => 2]);

        $cont .= $tpl->getHtmlFrag('alert', ['messages' => [
            _REPAIR.': '.$conf['db']['name'],
            _TOTALSPACE.': '.filterSize($total),
            _TOTALFREE.': '.filterSize($sumfree),
        ]]);
    } else {
        $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs]);
    }

    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $content]);

    setFoot();
}

function dump(): void {
    global $db, $conf, $afile, $tpl;
    $type = getVar('post', 'type', 'var', '');
    $string = getVar('post', 'string', 'raw', '');
    $action = getVar('post', 'action', 'var', '');
    $ops = ['name=database', 'name=database&type=optimize', 'name=database&type=repair', 'name=database&op=dump', 'name=database&op=info'];
    $tabs = [_HOME, _OPTIMIZE, _REPAIR, _INQUIRY, _DOCS];
    setHead();
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => 3]);
    if ($type === 'dump' && !empty($string) && ($action === 'parse' || $action === 'exec')) {
        if (!checkAdminPost('database')) {
            echo $cont.$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _TOKENMISS]);
            setFoot();
            return;
        }
        $parsed = getSqlbatch(getSqlFilled($string));
        if ($parsed['error'] !== '') {
            $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $parsed['error']]);
        } else {
            $items = $parsed['statements'];
            $reslist = [];
            $isdump = ($action === 'exec');
            if ($isdump) {
                if (!checkDblock()) {
                    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => 'Another migration run is already active']);
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
                    $cont .= getSqlsum($reslist, 'dump', $conf['db']['name']);
                    $cont .= $tpl->getHtmlPart('box', ['content_html' => getSqltable($reslist)]);
                }
            } else {
                foreach ($items as $index => $sql) {
                    $info = getSqlinfo($sql);
                    $reslist[] = ['num' => $index + 1, 'type' => $info['type'], 'table' => $info['table'], 'ok' => true, 'error' => '', 'sql' => $sql];
                }
                $cont .= getSqlsum($reslist, 'parse', $conf['db']['name']);
                $cont .= $tpl->getHtmlPart('box', ['content_html' => getSqltable($reslist)]);
            }
        }
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['text' => _DBINFO]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _DBWARN]);
    }
    $buttons =
        $tpl->getHtmlFrag('button', ['button_type' => 'submit', 'name_attr' => 'action', 'value_attr' => 'parse', 'submit_label' => _DB_PARSE, 'is_green' => true])
        .$tpl->getHtmlFrag('button', ['button_type' => 'submit', 'name_attr' => 'action', 'value_attr' => 'exec', 'submit_label' => _EXECUTE, 'is_blue' => true]);
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=database&op=dump',
        'hidden' => [
            ['nameattr' => 'type', 'valueattr' => 'dump'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('database')],
        ],
        'rows' => [[
            'is_full' => true,
            'label_html' => '',
            'field_html' => Editor::getCode([
                'id' => 'code',
                'name' => 'string',
                'lang' => 'sql',
                'text' => $string,
            ]),
        ]],
        'actions_html' => $buttons,
        'submit_label' => '',
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $form]);
    setFoot();
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=database', 'name=database&type=optimize', 'name=database&type=repair', 'name=database&op=dump', 'name=database&op=info'],
        'tabs' => [_HOME, _OPTIMIZE, _REPAIR, _INQUIRY, _DOCS],
    ]);
}

function delete(): void {
    global $db, $afile;
    $tb = getVar('post', 'tb', 'var');
    $id = getVar('post', 'id', 'num');
    $warn = !checkAdminPost('database');
    $tb = preg_match('#^[a-zA-Z0-9_]+$#', (string)$tb) ? $tb : '';
    if (!$warn && $tb && $id == 1) {
        $db->getSqlQuery('TRUNCATE TABLE `'.$tb.'`');
        $mess = _SUCCCLEAR;
    } elseif (!$warn && $tb && $id == 2) {
        $db->getSqlQuery('DROP TABLE `'.$tb.'`');
        $mess = _SUCCDELETE;
    } else {
        $mess = $warn ? _TOKENMISS : _SUCCDELETE;
    }
    setRedirect($afile.'.php?name=database', false, 302, $mess, $warn);
}

switch ($op) {
    default: database(); break;
    case 'dump': dump(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
