<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function groups(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=groups', 'name=groups&op=add', 'name=groups&op=points', 'name=groups&op=info'], 'tabs' => [_HOME, _ADD, _POINTS, _DOCS]]);
    $result = $db->getSqlQuery('SELECT id, name, intro, points, extra, rank, color FROM '.PREFIX_DB.'_groups ORDER BY points, extra');
    if ($db->getSqlRowCount($result) > 0) {
        $head = [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _RANK, 'nosort' => 1],
            ['content' => _GROUP, 'is_truncate' => true],
            ['content' => _POINTS, 'is_col_count' => true],
            ['content' => cutstr(_USERSCOUNT, 5, 1), 'is_col_count' => true],
            ['content' => cutstr(_SPEC, 4, 1), 'is_col_status' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => 1],
        ];
        $rows = '';
        while ([$grid, $grname, $description, $points, $extra, $rank, $color] = $db->getSqlRow($result)) {
            if (intval($extra)) {
                $extra = _YES;
                [$users_num] = $db->getSqlRow($db->getSqlQuery('SELECT Count(*) FROM '.PREFIX_DB.'_users WHERE grp = :grid', ['grid' => $grid]));
                $userlink = $afile.'.php?op=users_show&search=6&chng_user='.$grid;
            } else {
                $extra = _NO;
                [$users_num] = $db->getSqlRow($db->getSqlQuery('SELECT Count(*) FROM '.PREFIX_DB.'_users WHERE points >= :points', ['points' => $points]));
                $userlink = $afile.'.php?op=users_show&search=7&chng_user='.$points;
            }
            $acts = $tpl->getHtmlFrag('dial', [
                'dial_title' => _FUNCTIONS,
                'dial' => [
                    [
                        'href' => $userlink,
                        'icon_name' => 'eye',
                        'title' => _MVIEW,
                    ],
                    [
                        'href' => $afile.'.php?name=groups&op=add&id='.$grid,
                        'icon_name' => 'pencil',
                        'title' => _FULLEDIT,
                    ],
                    getTplPostAction(['name' => 'groups', 'op' => 'delete', 'id' => $grid], 'trash', _ONDELETE, _DELETE.' "'.$grname.'"?'),
                ],
            ]);
            $rows .= $tpl->getHtmlFrag('table-row', [
                'cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['is_col_id' => true, 'content_html' => (string)$grid],
                    ['content_html' => $tpl->getHtmlFrag('span', ['img_src' => 'templates/'.$conf['theme'].'/images/ranks/'.$rank, 'img_alt' => _RANK])],
                    ['is_truncate' => true, 'title_text' => $grname, 'prefix_html' => $tpl->getHtmlFrag('popover', ['content_html' => _DESCRIPTION.': '.$description]), 'content_html' => $tpl->getHtmlFrag('inline-badge', ['label' => $grname, 'color_attr' => $color])],
                    ['is_col_count' => true, 'content_html' => (string)$points],
                    ['is_col_count' => true, 'content_html' => (string)$users_num],
                    ['is_col_status' => true, 'content_html' => $extra],
                    ['is_col_actions' => true, 'content_html' => $acts],
                ]]),
            ]);
        }
        $cont .= $tpl->getHtmlFrag('table', [
            'is_fixed' => true,
            'head' => $head,
            'rows_html' => $rows,
        ]);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop, $tpl;
    $id = getVar('req', 'id', 'num');
    if ($id) {
        $result = $db->getSqlQuery('SELECT id, name, intro, points, extra, rank, color FROM '.PREFIX_DB.'_groups WHERE id = :id', ['id' => $id]);
        [$gid, $grname, $description, $points, $extra, $rank, $color] = $db->getSqlRow($result);
        $check = ($extra) ? ' checked' : '';
    } else {
        $gid = getVar('post', 'gid', 'num');
        $grname = getVar('post', 'grname', 'title');
        $description = getVar('post', 'description', 'text');
        $grextra = getVar('post', 'grextra', 'num');
        $points = getVar('post', 'points', 'num');
        $rank = getVar('post', 'rank', 'title');
        $rank = str_replace('templates/'.$conf['theme'].'/images/ranks/', '', $rank);
        $color = getVar('post', 'color', 'title');
        $check = ($grextra) ? ' checked' : '';
    }
    $rank = empty($rank) ? 'rank_1.png' : $rank;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=groups', 'name=groups&op=add', 'name=groups&op=points', 'name=groups&op=info'], 'tabs' => [_HOME, _ADD, _POINTS, _DOCS], 'tab' => 1]);
    $cont .= $tpl->getHtmlFrag('alert', ['text' => _GROUPSI]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
    $rows = [];
    $rows[] = [
        'label_for' => 'f-grname',
        'label_html' => _NAME,
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'name_attr' => 'grname',
            'input_id' => 'f-grname',
            'value_attr' => $grname,
            'placeholder_text' => _NAME,
            'input_attr' => 'maxlength="255"',
            'is_required' => true,
        ]),
    ];
    $rows[] = [
        'label_for' => 'f-description',
        'label_html' => _DESCRIPTION,
        'field_html' => $tpl->getHtmlFrag('textarea', [
            'name_attr' => 'description',
            'input_id' => 'f-description',
            'value_text' => $description,
            'placeholder_text' => _DESCRIPTION,
        ]),
    ];
    $path = 'templates/'.$conf['theme'].'/images/ranks/';
    $pickopts = '';
    foreach (scandir($path) as $entry) {
        if (preg_match('#(\.gif|\.png|\.jpg|\.jpeg)$#is', $entry)) {
            $pickopts .= $tpl->getHtmlFrag('select-option', [
                'value_attr' => $path.$entry,
                'label_text' => $entry,
                'is_selected' => $rank == $entry,
            ]);
        }
    }
    $rows[] = [
        'label_for' => 'f-rank',
        'label_html' => _IMG,
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'rank',
            'selectid' => 'f-rank',
            'options_html' => $pickopts,
            'select_attr' => 'id="img_replace"',
        ]),
    ];
    $rows[] = [
        'label_html' => _RANK,
        'field_html' => $tpl->getHtmlFrag('image-preview', ['src_attr' => $path.$rank, 'image_id' => 'picture', 'alt_text' => _RANK]),
    ];
    $rows[] = [
        'label_for' => 'f-color',
        'label_html' => _COLOR,
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'color',
            'name_attr' => 'color',
            'input_id' => 'f-color',
            'value_attr' => $color,
        ]),
    ];
    $rows[] = [
        'label_for' => 'f-points',
        'label_html' => _POINTSNEEDED,
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'number',
            'name_attr' => 'points',
            'input_id' => 'f-points',
            'value_attr' => (string)$points,
            'placeholder_text' => _POINTSNEEDED,
        ]),
    ];
    $rows[] = [
        'label_for' => 'f-grextra',
        'label_html' => _SPEC_GROUP, 'hint_html' => _GRSINFO, 'hint_id' => $hntid = getFieldIds('f-grextra')['hint'],
        'field_html' => $tpl->getHtmlFrag('checkbox', ['describedby' => $hntid,
            'name_attr' => 'grextra',
            'input_id' => 'f-grextra',
            'value_attr' => '1',
            'is_checked' => !empty($check),
        ]),
    ];
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'gid', 'valueattr' => (string)$gid],
            ['nameattr' => 'name', 'valueattr' => 'groups'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('groups')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVE,
    ]);
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $form]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $conf, $stop;
    $gid = getVar('post', 'gid', 'num');
    $stop = [];
    $warn = !checkAdminPost('groups');
    if (!$warn) {
        $grname = getVar('post', 'grname', 'title');
        $description = getVar('post', 'description', 'text');
        $points = getVar('post', 'points', 'num');
        $grextra = getVar('post', 'grextra', 'num');
        $rank = getVar('post', 'rank', 'title');
        $color = getVar('post', 'color', 'title');
        if (!$grname) $stop[] = _CERROR;
        if (!is_numeric($points) && $grextra != '1') $stop[] = _NONUMVALUE;
    }
    if ($warn || !$stop) {
        if (!$warn) {
            $points = ($grextra == '1') ? '0' : $points;
            $rank = str_replace('templates/'.$conf['theme'].'/images/ranks/', '', $rank);
            if ($gid) {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_groups SET name = :name, intro = :intro, points = :points, extra = :extra, rank = :rank, color = :color WHERE id = :id', ['name' => $grname, 'intro' => $description, 'points' => $points, 'extra' => $grextra, 'rank' => $rank, 'color' => $color, 'id' => $gid]);
            } else {
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_groups (name, intro, points, extra, rank, color) VALUES (:name, :intro, :points, :extra, :rank, :color)', ['name' => $grname, 'intro' => $description, 'points' => $points, 'extra' => $grextra, 'rank' => $rank, 'color' => $color]);
            }
        }
        setRedirect($afile.'.php?name=groups', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
    } else {
        $stop = implode("\n", $stop);
        add();
    }
}

function points(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=groups', 'name=groups&op=add', 'name=groups&op=points', 'name=groups&op=info'], 'tabs' => [_HOME, _ADD, _POINTS, _DOCS], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/points.php');
    $mark = ($conf['update']['points'] ?? '') === '6.3.0';
    if (!$mark) $cont .= $tpl->getHtmlFrag('alert', ['text' => _POINTS_NOMARK, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
    $phead = [
        ['content' => _NAME],
        ['content' => _POINTS, 'nosort' => 1],
        ['content' => _POINTS_PERIOD, 'nosort' => 1],
        ['content' => _POINTS_LIMIT, 'nosort' => 1],
    ];
    $heads = ['points' => _POINTS, 'period' => _POINTS_PERIOD, 'limit' => _POINTS_LIMIT];
    $prows = '';
    foreach ($conf['points']['actions'] as $name => $rule) {
        if ($name === 'adjust') continue;
        $cells = [['has_content_text' => true, 'content_text' => constant('_POINTS_'.strtoupper($name))]];
        foreach ($heads as $key => $head) {
            $cells[] = ['content_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'rule['.$name.']['.$key.']',
                'value_attr' => (string)$rule[$key],
                'placeholder_text' => $head,
                'is_required' => true,
            ])];
        }
        $prows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => $cells])]);
    }
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $pointv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'groups'],
            ['nameattr' => 'op', 'valueattr' => 'pointssave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('groups')],
        ],
        'content_html' => $tpl->getHtmlPart('div', ['rows' => [[
            'label_html' => _UPDATE_POINTS,
            'label_id' => $labid = getFieldIds('', 'active')['label'],
            'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'active', 'value' => (string)$conf['points']['active'], 'options' => $yesno]),
        ]]]).$tpl->getHtmlFrag('table', [
        'is_fixed' => true,
            'head' => $phead,
            'rows_html' => $prows,
            'is_wrapless' => true,
        ]),
        'submit_label' => _SAVE,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $pointv]).($mark ? getPointsJournal() : '');
    setFoot();
}

function getPointsJournal(): string {
    global $db, $afile, $conf, $tpl;
    $uid = getVar('get', 'uid', 'num', 0);
    $act = getVar('get', 'act', 'word', '');
    $day = getVar('get', 'day', 'text', '');
    $num = max(1, getVar('get', 'num', 'num', 1));
    $names = array_keys($conf['points']['actions']);
    $act = in_array($act, $names, true) ? $act : '';
    $day = (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day) && strtotime($day) !== false) ? $day : '';
    $where = [];
    $pars = [];
    if ($uid) [$where[], $pars['uid']] = ['p.uid = :uid', $uid];
    if ($act !== '') [$where[], $pars['act']] = ['p.action = :act', $act];
    if ($day !== '') [$where[], $pars['from'], $pars['to']] = ['p.created >= :from AND p.created < :to', $day.' 00:00:00', date('Y-m-d', strtotime($day.' +1 day')).' 00:00:00'];
    $cond = $where ? ' WHERE '.implode(' AND ', $where) : '';
    [$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) FROM '.PREFIX_DB.'_points AS p'.$cond, $pars));
    $sql = 'SELECT p.id, p.uid, u.name, p.aid, p.action, p.scope, p.mid, p.source, p.points, p.rid, p.note, p.created FROM '.PREFIX_DB.'_points AS p'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.id = p.uid)'.$cond.' ORDER BY p.created DESC, p.id DESC LIMIT :offset, :limit';
    $result = $db->getSqlQuery($sql, $pars + ['offset' => ($num - 1) * 50, 'limit' => 50]);
    $opts = [['value_attr' => '', 'label_text' => _ALL, 'is_selected' => $act === '']];
    foreach ($names as $name) $opts[] = ['value_attr' => $name, 'label_text' => constant('_POINTS_'.strtoupper($name)), 'is_selected' => $name === $act];
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'method' => 'get',
        'is_inline_filter' => true,
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'groups'],
            ['nameattr' => 'op', 'valueattr' => 'points'],
        ],
        'content_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'uid', 'value_attr' => $uid ? (string)$uid : '', 'placeholder_text' => _USER.' '._ID])
            .$tpl->getHtmlFrag('select', ['name_attr' => 'act', 'options' => $opts])
            .$tpl->getHtmlFrag('input', ['itype' => 'date', 'name_attr' => 'day', 'value_attr' => $day, 'placeholder_text' => _DATE])
            .$tpl->getHtmlFrag('button', ['button_type' => 'submit', 'submit_label' => _OK]),
    ]);
    $rows = '';
    while ($result && ($row = $db->getSqlRow($result))) {
        $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
            ['is_col_id' => true, 'content_html' => (string)intval($row['id'])],
            ['is_col_date' => true, 'has_content_text' => true, 'content_text' => (string)$row['created']],
            ['is_truncate' => true, 'has_content_text' => true, 'content_text' => (string)($row['name'] ?? '').' #'.intval($row['uid'])],
            ['has_content_text' => true, 'content_text' => (string)$row['action']],
            ['is_truncate' => true, 'has_content_text' => true, 'content_text' => (string)$row['scope'].' / '.(string)$row['source']],
            ['is_col_count' => true, 'content_html' => (string)intval($row['points'])],
            ['is_truncate' => true, 'has_content_text' => true, 'title_text' => (string)$row['note'], 'content_text' => (string)$row['note']],
        ]])]);
    }
    $none = $tpl->getHtmlFrag('alert', ['text' => _NO_INFO, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
    if ($rows === '') return $tpl->getHtmlPart('box', ['title' => _POINTS_JOURNAL, 'content_html' => $form.$none]);
    $head = [
        ['content' => _ID],
        ['content' => _DATE],
        ['content' => _USER],
        ['content' => _TYPE],
        ['content' => _POINTS_SCOPE.' / '._POINTS_SOURCE],
        ['content' => _POINTS],
        ['content' => _NOTE],
    ];
    $link = 'name=groups&op=points'.($uid ? '&uid='.$uid : '').($act !== '' ? '&act='.$act : '').($day !== '' ? '&day='.$day : '').'&';
    $body = $form.$tpl->getHtmlFrag('table', ['head' => $head, 'rows_html' => $rows]).getPageNumbers('', (int)$count, (int)ceil($count / 50), 50, $link, 10, $num, '', 'num');
    return $tpl->getHtmlPart('box', ['title' => _POINTS_JOURNAL, 'content_html' => $body]);
}

function pointssave(): void {
    global $afile, $conf;
    $warn = !checkAdminPost('groups');
    $text = $warn ? _TOKENMISS : _SUCCSAVE;
    if (!$warn) {
        $cont = ['active' => getVar('post', 'active', 'num') ? '1' : '0', 'actions' => $conf['points']['actions']];
        $tops = ['points' => 1000, 'period' => 31536000, 'limit' => 10000];
        foreach (array_keys($cont['actions']) as $name) {
            if ($name === 'adjust') continue;
            foreach ($tops as $key => $top) {
                $val = trim((string)getVar('post', 'rule['.$name.']['.$key.']', 'raw', ''));
                if (!preg_match('/^(?:0|[1-9][0-9]{0,8})$/D', $val) || intval($val) > $top) $warn = true;
                $cont['actions'][$name][$key] = $val;
            }
            $per = intval($cont['actions'][$name]['period']);
            if (($per === 0) !== ($cont['actions'][$name]['limit'] === '0') || ($per > 0 && $per < 60)) $warn = true;
        }
        $text = $warn ? _NONUMVALUE : _SUCCSAVE;
        if (!$warn && !setConfigFile('points.php', $cont)) [$warn, $text] = [true, getConfigJournal() ? _CONFIG_PENDING : _ERROR_UP];
    }
    setRedirect($afile.'.php?name=groups&op=points', false, 302, $text, $warn);
}

function delete(): void {
    global $db, $afile, $conf;
    $warn = !checkAdminPost('groups');
    $id = getVar('post', 'id', 'num');
    if (!$warn && $id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_groups WHERE id = :id', ['id' => $id]);
        $changed = false;
        foreach ($conf['modules'] as $name => $info) {
            if ((int)($info['group'] ?? 0) === $id) {
                $conf['modules'][$name]['group'] = 0;
                $changed = true;
            }
        }
        if ($changed) setConfigFile('modules.php', $conf['modules']);
    }
    setRedirect($afile.'.php?name=groups', false, 302, $warn ? _TOKENMISS : _SUCCDELETE, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=groups', 'name=groups&op=add', 'name=groups&op=points', 'name=groups&op=info'],
        'tabs' => [_HOME, _ADD, _POINTS, _DOCS],
    ]);
}

switch ($op) {
    default: groups(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'points': points(); break;
    case 'pointssave': pointssave(); break;
    case 'info': info(); break;
}
