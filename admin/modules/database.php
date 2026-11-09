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
        $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['content_html' => (string)(int)$row['num'], 'is_col_id' => true],
                ['has_content_text' => true, 'content_text' => (string)$row['type']],
                ['has_content_text' => true, 'content_text' => $tab],
                ['has_content_text' => true, 'content_text' => $sql],
                ['is_col_status' => true, 'stat' => ['text' => $row['ok'] ? _OK : _ERROR.' - '.$row['error'], 'is_on' => (bool)$row['ok'], 'is_fail' => !$row['ok']]],
            ],
        ])]);
    }
    return $tpl->getHtmlFrag('table', [
        'is_fixed' => true,
        'head' => [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _TYPE],
            ['content' => _TABLE],
            ['content' => _DB_SQL, 'nosort' => true],
            ['content' => _STATUS, 'is_col_status' => true, 'is_head_status' => true],
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

# List every table of the database with its size and free space; a maintenance run waits in the admin session and shows its summary and marks once
function database(): void {
    global $db, $conf, $tpl;
    $dbname = preg_replace('#[^a-zA-Z0-9_]#', '', (string)($conf['db']['name'] ?? ''));
    $keep = $conf['admin_c'].'-dbrun';
    $run = $_SESSION[$keep] ?? null;
    unset($_SESSION[$keep]);
    $isrun = is_array($run);
    $marks = (array)($run['marks'] ?? []);
    $words = ['sound' => _DB_SOUND, 'fixed' => _DB_FIXED, 'broken' => _DB_BROKEN, 'done' => _OPTIMIZED, 'same' => _ALREADYOPTIMIZED, 'fail' => _ERROR];
    $sum = '';
    if ($isrun) {
        $count = array_count_values([...array_column($marks, 'fix'), ...array_column($marks, 'opt')]);
        $acts = (array)($run['acts'] ?? []);
        $lines = [_DB_MODE.': '.implode(' + ', array_map(fn($v) => ($v === 'check') ? _DB_CHECK : _OPTIMIZE, $acts)), _DB_TABLES.': '.count($marks)];
        if (in_array('optimize', $acts, true)) {
            $was = array_sum(array_column($marks, 'was'));
            $now = array_sum(array_column($marks, 'now'));
            $lines[] = _DB_FREED.': '.filterSize(max(0, $was - $now));
            $lines[] = _SIZE.': '.filterSize($was).' → '.filterSize($now);
        }
        foreach ($words as $key => $word) if (!empty($count[$key])) $lines[] = $word.': '.$count[$key];
        $sum = $tpl->getHtmlFrag('alert', ['type' => (empty($count['fail']) && empty($count['broken'])) ? 'success' : 'error', 'messages' => $lines]);
    }
    $ops = ['name=database', 'name=database&op=service', 'name=database&op=dump', 'name=database&op=info'];
    $head = getTplAdminTabs(['ops' => $ops, 'tabs' => [_HOME, _DB_SERVICE, _DB_SQL, _MANUAL], 'tab' => 0]);
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
            ? $tpl->getHtmlFrag('inline-badge', ['is_muted' => !$tabfree, 'label' => filterSize($tabfree)])
            : $tpl->getHtmlFrag('inline-badge', ['is_danger' => (bool)$tabfree, 'is_success' => !$tabfree, 'label' => filterSize($tabfree)]);
        $tail = [['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _EDITOR, 'dial' => [
            getTplPostAction(['name' => 'database', 'op' => 'delete', 'tb' => $name, 'id' => 1], 'eraser', _CLEAN, _CLEAN.' "'.$name.'"?'),
            getTplPostAction(['name' => 'database', 'op' => 'delete', 'tb' => $name, 'id' => 2], 'trash', _ONDELETE, _DELETE.' "'.$name.'"?'),
        ]])]];
        if ($isrun) {
            $one = (array)($marks[$name] ?? []);
            $fix = (string)($one['fix'] ?? '');
            $opt = (string)($one['opt'] ?? '');
            $freed = max(0, intval($one['was'] ?? 0) - intval($one['now'] ?? 0));
            $said = array_values(array_filter([
                ($fix === 'sound' && $opt !== '') ? '' : ($words[$fix] ?? ''),
                ($opt === 'done') ? '−'.filterSize($freed) : ($words[$opt] ?? ''),
            ]));
            $text = implode(' · ', $said).((($one['msg'] ?? '') !== '') ? ': '.$one['msg'] : '');
            $tone = (in_array($fix, ['broken', 'fail'], true) || $opt === 'fail') ? 'is_fail' : (($fix === 'fixed' || $opt === 'done') ? 'is_on' : 'is_off');
            $tail = [
                ['is_col_status' => true, 'stat' => ['text' => $said ? implode(' · ', $said) : _NO, $tone => (bool)$said]],
                ['is_truncate' => true, 'title_text' => $text, 'has_content_text' => true, 'content_text' => $text, 'attr' => 'data-sort="'.$freed.'"'],
            ];
        }
        $rows[] = ['name' => $name, 'cells' => [
            ['is_col_id' => true, 'has_content_text' => true, 'content_text' => count($rows) + 1],
            ['has_content_text' => true, 'content_text' => $name],
            ['has_content_text' => true, 'content_text' => $tabeng],
            ['title_text' => $tabloc, 'has_content_text' => true, 'content_text' => $tabloc],
            ['is_col_count' => true, 'has_content_text' => true, 'content_text' => $num],
            ['is_col_date' => true, 'moment' => $info['Create_time']],
            ['is_col_count' => true, 'has_content_text' => true, 'content_text' => filterSize($tabsize)],
            ['is_col_count' => true, 'content_html' => $freetag],
            ...$tail,
        ]];
    }
    $tiles = $tpl->getHtmlFrag('metric-list', ['items' => [
        ['label' => _DB_TABLES, 'value' => filterDecimal(count($rows), 0), 'icon' => 'table'],
        ['label' => _ROWS, 'value' => filterDecimal($allrows, 0), 'icon' => 'list-ol'],
        ['label' => _DB_TOTAL, 'value' => filterSize($total), 'icon' => 'database'],
        ['label' => _DBFREE, 'value' => filterSize($sumfree), 'icon' => 'recycle'],
    ]]);
    setHead();
    echo getTplAdminList([
        'head_html' => $head,
        'note_html' => $sum.$tiles,
        'head' => [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _TABLE],
            ['content' => _TYPE],
            ['content' => _DBCOLL],
            ['content' => _ROWS, 'is_col_count' => true],
            ['content' => _DATE, 'is_col_date' => true],
            ['content' => _SIZE, 'is_col_count' => true],
            ['content' => _DBFREE, 'is_col_count' => true, 'nosort' => true],
            ...($isrun ? [['content' => _STATUS, 'is_col_status' => true, 'is_head_status' => true], ['content' => _DB_RESULT]]
                : [['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true]]),
        ],
        'rows' => $rows,
        'count' => count($rows),
    ]);
    setFoot();
}

function service(): void {
    global $afile, $tpl;
    setHead();
    $ops = ['name=database', 'name=database&op=service', 'name=database&op=dump', 'name=database&op=info'];
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => [_HOME, _DB_SERVICE, _DB_SQL, _MANUAL], 'tab' => 1]);
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _REPTEXT.' '._OPTTEXT]);
    $rows = [];
    foreach (['check' => _DB_CHECK, 'optimize' => _OPTIMIZE] as $key => $label) {
        $rows[] = [
            'label_html' => $label,
            'label_id' => $labid = getFieldIds('', $key)['label'],
            'field_html' => getTplRadioGroup(['labelledby' => $labid,
                'name' => $key,
                'value' => '0',
                'options' => [
                    ['value' => '1', 'label' => _YES],
                    ['value' => '0', 'label' => _NO],
                ],
            ]),
        ];
    }
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'database'],
            ['nameattr' => 'op', 'valueattr' => 'maintain'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('database')],
        ],
        'rows' => $rows,
        'submit_label' => _EXECUTE,
    ])]);
    echo $cont;
    setFoot();
}

# The server answers a table command in rows, so a failed table is an error row of a query that succeeded; REPAIR does nothing on InnoDB, which only CHECK can judge
function maintain(): void {
    global $db, $conf, $afile;
    $opt = getVar('post', 'optimize', 'num') === 1;
    $chk = getVar('post', 'check', 'num') === 1;
    $dbname = preg_replace('#[^a-zA-Z0-9_]#', '', (string)($conf['db']['name'] ?? ''));
    $warn = !checkAdminPost('database') || $dbname === '';
    if (!$warn && !$opt && !$chk) setRedirect($afile.'.php?name=database&op=service', false, 302, _DB_NOPICK, true);
    $ask = static function (string $sql) use ($db): array {
        $res = $db->getSqlQuery($sql);
        if (!$res) return [false, (string)($db->getSqlError()['message'] ?? _ERROR)];
        $isok = false;
        $text = '';
        while ($row = $db->getSqlRow($res)) {
            $type = strtolower((string)($row['Msg_type'] ?? ''));
            $msg = (string)($row['Msg_text'] ?? '');
            if ($type === 'status') $isok = in_array($msg, ['OK', 'Table is already up to date'], true);
            if ($type === 'error' || ($type === 'status' && !$isok)) $text = $msg;
        }
        return [$isok && $text === '', $text];
    };
    $size = static function (string $name) use ($db, $dbname): int {
        $res = $db->getSqlQuery('SELECT DATA_LENGTH + INDEX_LENGTH + DATA_FREE AS used FROM information_schema.TABLES WHERE TABLE_SCHEMA = :base AND TABLE_NAME = :name',
            ['base' => $dbname, 'name' => $name]);
        $row = $res ? $db->getSqlRow($res) : null;
        return intval($row['used'] ?? 0);
    };
    $tabs = [];
    $result = $warn ? false : $db->getSqlQuery('SHOW TABLE STATUS FROM `'.$dbname.'`');
    while ($result && $info = $db->getSqlRow($result)) {
        if (preg_match('#^[a-zA-Z0-9_]+$#', (string)$info['Name'])) $tabs[(string)$info['Name']] = (string)$info['Engine'];
    }
    $marks = [];
    foreach ($tabs as $name => $engine) {
        $path = '`'.$dbname.'`.`'.$name.'`';
        $one = ['fix' => '', 'opt' => '', 'was' => 0, 'now' => 0, 'msg' => ''];
        if ($chk) {
            [$isok, $text] = $ask('CHECK TABLE '.$path);
            if ($isok) {
                $one['fix'] = 'sound';
            } elseif ($engine === 'InnoDB') {
                $one = ['fix' => 'broken', 'msg' => $text] + $one;
            } else {
                [$isok, $back] = $ask('REPAIR TABLE '.$path);
                $one = ['fix' => $isok ? 'fixed' : 'fail', 'msg' => $isok ? $text : $back] + $one;
            }
        }
        if ($opt && !in_array($one['fix'], ['broken', 'fail'], true)) {
            $db->getSqlQuery('ANALYZE TABLE '.$path);
            $one['was'] = $size($name);
            [$isok, $text] = $ask('OPTIMIZE TABLE '.$path);
            $one['now'] = $size($name);
            $one['opt'] = !$isok ? 'fail' : (($one['was'] > $one['now']) ? 'done' : 'same');
            if (!$isok) $one['msg'] = $text;
        }
        $marks[$name] = $one;
    }
    if ($opt && $marks) $db->getSqlQuery('FLUSH TABLES');
    if (!$warn) $_SESSION[$conf['admin_c'].'-dbrun'] = ['acts' => array_keys(array_filter(['check' => $chk, 'optimize' => $opt])), 'marks' => $marks];
    setRedirect($afile.'.php?name=database', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function dump(): void {
    global $db, $conf, $afile, $tpl;
    $type = getVar('post', 'type', 'var', '');
    $string = getVar('post', 'string', 'raw', '');
    $action = getVar('post', 'action', 'var', '');
    $ops = ['name=database', 'name=database&op=service', 'name=database&op=dump', 'name=database&op=info'];
    $tabs = [_HOME, _DB_SERVICE, _DB_SQL, _MANUAL];
    $run = $type === 'dump' && !empty($string) && ($action === 'parse' || $action === 'exec');
    if ($run && !checkAdminPost('database')) setRedirect($afile.'.php?name=database&op=dump', false, 302, _TOKENMISS, true);
    setHead();
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => 2]);
    if ($run) {
        $parsed = getSqlbatch(getSqlFilled($string));
        if ($parsed['error'] !== '') {
            $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $parsed['error']]);
        } else {
            $items = $parsed['statements'];
            $reslist = [];
            $isdump = ($action === 'exec');
            if ($isdump) {
                if (!checkDblock()) {
                    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _DB_RUNBUSY]);
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
        if ($type === 'dump' && empty($string)) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _CERROR1]);
        $cont .= $tpl->getHtmlFrag('alert', ['text' => _DBINFO]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _DBWARN]);
    }
    $buttons =
        $tpl->getHtmlFrag('button', ['button_type' => 'submit', 'name_attr' => 'action', 'value_attr' => 'parse', 'submit_label' => _DB_PARSE, 'icon_name' => 'search',
            'is_green' => true])
        .$tpl->getHtmlFrag('button', ['button_type' => 'submit', 'name_attr' => 'action', 'value_attr' => 'exec', 'submit_label' => _EXECUTE,
            'icon_name' => 'play-fill', 'is_blue' => true]);
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
        'ops' => ['name=database', 'name=database&op=service', 'name=database&op=dump', 'name=database&op=info'],
        'tabs' => [_HOME, _DB_SERVICE, _DB_SQL, _MANUAL],
    ]);
}

function delete(): void {
    global $db, $afile;
    $tb = getVar('post', 'tb', 'var');
    $id = getVar('post', 'id', 'num');
    $warn = !checkAdminPost('database');
    $tb = preg_match('#^[a-zA-Z0-9_]+$#', (string)$tb) ? $tb : '';
    $done = !$warn && in_array($id, [1, 2], true) && checkSqlTable($tb)
        && $db->getSqlQuery((($id === 1) ? 'TRUNCATE' : 'DROP').' TABLE `'.$tb.'`') !== false;
    $mess = $warn ? _TOKENMISS : ($done ? (($id === 1) ? _SUCCCLEAR : _SUCCDELETE) : _ERROR);
    setRedirect($afile.'.php?name=database', false, 302, $mess, !$done);
}

switch ($op) {
    default: database(); break;
    case 'service': service(); break;
    case 'maintain': maintain(); break;
    case 'dump': dump(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
