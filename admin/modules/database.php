<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');



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

# List every table of the database with its size and free space; a post runs optimize or repair and redirects to its tab, which shows the result per table once
function database(string $run = ''): void {
    global $db, $conf, $tpl, $afile;
    $type = ($run !== '') ? $run : getVar('get', 'type', 'var');
    $type = in_array($type, ['optimize', 'repair'], true) ? $type : '';
    $dbname = preg_replace('#[^a-zA-Z0-9_]#', '', (string)($conf['db']['name'] ?? ''));
    $keep = $conf['admin_c'].'-dbrun';
    if ($run !== '') {
        $warn = !checkAdminPost('database') || $dbname === '';
        $marks = [];
        $result = $warn ? false : $db->getSqlQuery('SHOW TABLE STATUS FROM `'.$dbname.'`');
        while ($result && $info = $db->getSqlRow($result)) {
            $name = (string)$info['Name'];
            if (!preg_match('#^[a-zA-Z0-9_]+$#', $name)) continue;
            if ($run === 'repair' && $info['Engine'] === 'InnoDB') {
                $marks[$name] = 'none';
                continue;
            }
            if ($run === 'optimize') $db->getSqlQuery('ANALYZE TABLE `'.$dbname.'`.`'.$name.'`');
            $res = $db->getSqlQuery(strtoupper($run).' TABLE `'.$dbname.'`.`'.$name.'`');
            $marks[$name] = !$res ? 'fail' : (($run === 'optimize' && $info['Engine'] === 'MyISAM' && !$info['Data_free']) ? 'same' : 'done');
        }
        if ($run === 'optimize' && $marks) $db->getSqlQuery('FLUSH TABLES');
        if (!$warn) $_SESSION[$keep] = $marks;
        setRedirect($afile.'.php?name=database&type='.$run, false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
    }
    $marks = ($type !== '') ? (array)($_SESSION[$keep] ?? []) : [];
    if ($type !== '') unset($_SESSION[$keep]);
    $words = ['done' => [($type === 'repair') ? _OK : _OPTIMIZED, 'is_success'], 'same' => [_ALREADYOPTIMIZED, 'is_danger'], 'fail' => [_ERROR, 'is_danger'],
        'none' => [_NO, 'is_dimmed']];
    $ops = ['name=database', 'name=database&type=optimize', 'name=database&type=repair', 'name=database&op=dump', 'name=database&op=info'];
    $head = getTplAdminTabs(['ops' => $ops, 'tabs' => [_HOME, _OPTIMIZE, _REPAIR, _INQUIRY, _MANUAL], 'tab' => ['' => 0, 'optimize' => 1, 'repair' => 2][$type],
        'part' => [0, 1, 2]]);
    if ($dbname === '') {
        setHead();
        echo getTplAdminList(['head_html' => $head, 'none_text' => _ERROR]);
        setFoot();
        return;
    }
    $result = $db->getSqlQuery('SHOW TABLE STATUS FROM `'.$dbname.'`');
    $tables = [];
    while ($info = $db->getSqlRow($result)) $tables[] = $info;
    $total = 0;
    $sumfree = 0;
    $allrows = 0;
    $rows = [];
    foreach ($tables as $info) {
        $name = $info['Name'];
        if (!preg_match('#^[a-zA-Z0-9_]+$#', (string)$name)) continue;
        $tabeng = $info['Engine'];
        $tabloc = $info['Collation'];
        $num = (int)$info['Rows'];
        $res = $db->getSqlQuery('SELECT COUNT(*) AS cnt FROM `'.$dbname.'`.`'.$name.'`');
        if ($res && $row = $db->getSqlRow($res)) $num = (int)$row['cnt'];
        $allrows += $num;
        $tabsize = (int)$info['Data_length'] + (int)$info['Index_length'];
        $tabfree = (int)($info['Data_free'] ?: 0);
        $total += $tabsize;
        $sumfree += $tabfree;
        $freetag = $tabeng === 'InnoDB'
            ? $tpl->getHtmlFrag('inline-badge', ['is_dimmed' => true, 'label' => filterSize($tabfree)])
            : $tpl->getHtmlFrag('inline-badge', ['is_danger' => (bool)$tabfree, 'is_success' => !$tabfree, 'label' => filterSize($tabfree)]);
        $mark = $words[$marks[$name] ?? ''] ?? [];
        $stattag = $mark ? $tpl->getHtmlFrag('inline-badge', [$mark[1] => true, 'label' => $mark[0]]) : '';
        if ($type === '') {
            $stattag = $tpl->getHtmlFrag('dial', ['dial_title' => _EDITOR, 'dial' => [
                getTplPostAction(['name' => 'database', 'op' => 'delete', 'tb' => $name, 'id' => 1], 'eraser', _CLEAN, _CLEAN.' "'.$name.'"?'),
                getTplPostAction(['name' => 'database', 'op' => 'delete', 'tb' => $name, 'id' => 2], 'trash', _ONDELETE, _DELETE.' "'.$name.'"?'),
            ]]);
        }
        $rows[] = ['name' => $name, 'cells' => [
            ['is_col_id' => true, 'has_content_text' => true, 'content_text' => count($rows) + 1],
            ['title_text' => $name, 'has_content_text' => true, 'content_text' => $name],
            ['has_content_text' => true, 'content_text' => $tabeng],
            ['title_text' => $tabloc, 'has_content_text' => true, 'content_text' => $tabloc],
            ['is_col_count' => true, 'has_content_text' => true, 'content_text' => $num],
            ['is_col_date' => true, 'has_content_text' => true, 'content_text' => format_time($info['Create_time'], _TIMESTRING)],
            ['is_col_count' => true, 'has_content_text' => true, 'content_text' => filterSize($tabsize)],
            ['is_col_count' => true, 'content_html' => $freetag],
            ['is_col_actions' => true, 'content_html' => $stattag],
        ]];
    }
    $sums = $tpl->getHtmlFrag('alert', ['messages' => [
        (['' => _DATABASE, 'optimize' => _OPTIMIZE, 'repair' => _REPAIR][$type]).': '.$conf['db']['name'],
        _ROWS.': '.$allrows,
        _TOTALSPACE.': '.filterSize($total),
        _TOTALFREE.': '.filterSize($sumfree),
    ]]);
    $note = match ($type) {
        'optimize' => $tpl->getHtmlFrag('alert', ['is_warn' => true,
            'text' => _OPTTEXT.' '.getTplPostButton(['name' => 'database', 'op' => 'optimize'], 'speedometer2', _OPTIMIZE)]),
        'repair' => $tpl->getHtmlFrag('alert', ['text' => _REPTEXT.' '.getTplPostButton(['name' => 'database', 'op' => 'repair'], 'wrench', _REPAIR)]),
        default => $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _OPTTEXT]).$tpl->getHtmlFrag('alert', ['text' => _REPTEXT]),
    };
    setHead();
    echo getTplAdminList([
        'head_html' => $head,
        'note_html' => $note.$sums,
        'head' => [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _TABLE],
            ['content' => _TYPE],
            ['content' => _DBCOLL],
            ['content' => _ROWS, 'is_col_count' => true],
            ['content' => _DATE, 'is_col_date' => true],
            ['content' => _SIZE, 'is_col_count' => true],
            ['content' => _DBFREE, 'is_col_count' => true, 'nosort' => true],
            ['content' => ($type !== '') ? _STATUS : _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ],
        'rows' => $rows,
        'count' => count($rows),
    ]);
    setFoot();
}

function dump(): void {
    global $db, $conf, $afile, $tpl;
    $type = getVar('post', 'type', 'var', '');
    $string = getVar('post', 'string', 'raw', '');
    $action = getVar('post', 'action', 'var', '');
    $ops = ['name=database', 'name=database&type=optimize', 'name=database&type=repair', 'name=database&op=dump', 'name=database&op=info'];
    $tabs = [_HOME, _OPTIMIZE, _REPAIR, _INQUIRY, _MANUAL];
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
                    Logger::addDatabase('info', 'Run started', ['blocks' => count($items)]);
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
                            $note = ['block' => $index + 1, 'kind' => $info['type'], 'table' => $info['table'], 'error' => $error];
                            Logger::addDatabase($ok ? 'info' : 'error', $ok ? 'Block done' : 'Block failed', $note);
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
                'label' => _INQUIRY,
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
        'tabs' => [_HOME, _OPTIMIZE, _REPAIR, _INQUIRY, _MANUAL],
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
    case 'optimize': database('optimize'); break;
    case 'repair': database('repair'); break;
    case 'dump': dump(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
