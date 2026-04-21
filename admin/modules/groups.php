<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function groups(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=groups', 'name=groups&amp;op=add', 'name=groups&amp;op=points', 'name=groups&amp;op=info'], 'tabs' => [_HOME, _ADD, _POINTS, _INFO]]);
    $result = $db->getSqlQuery('SELECT id, name, intro, points, extra, rank, color FROM '.PREFIX_DB.'_groups ORDER BY points, extra');
    if ($db->getSqlRowCount($result) > 0) {
        $head = [
            ['content' => _ID],
            ['content' => _RANK, 'nosort' => 1],
            ['content' => _GROUP],
            ['content' => _POINTS],
            ['content' => cutstr(_USERSCOUNT, 5, 1)],
            ['content' => cutstr(_SPEC, 4, 1)],
            ['content' => _FUNCTIONS, 'nosort' => 1],
        ];
        $rows = '';
        while ([$grid, $grname, $description, $points, $extra, $rank, $color] = $db->getSqlRow($result)) {
            if (intval($extra)) {
                $extra = _YES;
                [$users_num] = $db->getSqlRow($db->getSqlQuery('SELECT Count(*) FROM '.PREFIX_DB.'_users WHERE grp = :grid', ['grid' => $grid]));
                $userlink = $afile.'.php?op=users_show&amp;search=6&amp;chng_user='.$grid;
            } else {
                $extra = _NO;
                [$users_num] = $db->getSqlRow($db->getSqlQuery('SELECT Count(*) FROM '.PREFIX_DB.'_users WHERE points >= :points', ['points' => $points]));
                $userlink = $afile.'.php?op=users_show&amp;search=7&amp;chng_user='.$points;
            }
            $acts = $tpl->getHtmlFrag('row-actions', [
                'trigger_label' => _FUNCTIONS,
                'items' => [
                    [
                        'href' => $userlink,
                        'label' => _MVIEW,
                        'title' => _MVIEW,
                    ],
                    [
                        'href' => $afile.'.php?name=groups&amp;op=add&amp;id='.$grid,
                        'label' => _FULLEDIT,
                        'title' => _FULLEDIT,
                    ],
                    [
                        'href' => $afile.'.php?name=groups&amp;op=delete&amp;id='.$grid.'&amp;token='.getSiteToken(),
                        'label' => _ONDELETE,
                        'title' => _ONDELETE,
                        'onclick_attr' => "return DelCheck(this, '"._DELETE." &quot;".addslashes($grname)."&quot;?')",
                    ],
                ],
            ]);
            $rows .= $tpl->getHtmlFrag('table-row', [
                'cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                    ['content_html' => (string)$grid],
                    ['content_html' => '<img src="templates/'.$conf['theme'].'/images/ranks/'.$rank.'" alt="'._RANK.'" title="'._RANK.'">'],
                    ['content_html' => $tpl->getHtmlFrag('info-tooltip', ['content_html' => _DESCRIPTION.': '.$description]).$tpl->getHtmlFrag('inline-badge', ['label' => $grname, 'badge_attr' => ' style="color: '.htmlspecialchars($color, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"'])],
                    ['content_html' => (string)$points],
                    ['content_html' => (string)$users_num],
                    ['content_html' => $extra],
                    ['content_html' => $acts],
                ]]),
            ]);
        }
        $cont .= $tpl->getHtmlFrag('table', [
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
    $cont = getTplAdminTabs(['ops' => ['name=groups', 'name=groups&amp;op=add', 'name=groups&amp;op=points', 'name=groups&amp;op=info'], 'tabs' => [_HOME, _ADD, _POINTS, _INFO], 'tab' => 1]);
    $cont .= $tpl->getHtmlFrag('alert', ['text' => _GROUPSI]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
    $rows = [];
    $rows[] = [
        'label_html' => _NAME.':',
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'name_attr' => 'grname',
            'value_attr' => $grname,
            'placeholder_text' => _NAME,
            'input_attr' => 'maxlength="255"',
            'is_required' => true,
        ]),
    ];
    $rows[] = [
        'label_html' => _DESCRIPTION.':',
        'field_html' => $tpl->getHtmlFrag('textarea', [
            'name_attr' => 'description',
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
        'label_html' => _IMG.':',
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'rank',
            'options_html' => $pickopts,
            'select_attr' => 'id="img_replace"',
        ]),
    ];
    $rows[] = [
        'label_html' => _RANK.':',
        'field_html' => '<img src="'.$path.$rank.'" id="picture" alt="'._RANK.'">',
    ];
    $rows[] = [
        'label_html' => _COLOR.':',
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'color',
            'name_attr' => 'color',
            'value_attr' => $color,
        ]),
    ];
    $rows[] = [
        'label_html' => _POINTSNEEDED.':',
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'number',
            'name_attr' => 'points',
            'value_attr' => (string)$points,
            'placeholder_text' => _POINTSNEEDED,
        ]),
    ];
    $rows[] = [
        'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _SPEC_GROUP.':', 'hint' => _GRSINFO]),
        'field_html' => $tpl->getHtmlFrag('checkbox', [
            'name_attr' => 'grextra',
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
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
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
    $warn = !checkSiteToken();
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
        $stop = implode(PHP_EOL, $stop);
        add();
    }
}

function points(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=groups', 'name=groups&amp;op=add', 'name=groups&amp;op=points', 'name=groups&amp;op=info'], 'tabs' => [_HOME, _ADD, _POINTS, _INFO], 'tab' => 2]);
    $p = [_POINTS01, _POINTS02, _POINTS03, _POINTS04, _POINTS05, _POINTS06, _POINTS07, _POINTS08, _POINTS09, _POINTS10, _POINTS11, _POINTS12, _POINTS13, _POINTS14, _POINTS15, _POINTS16, _POINTS17, _POINTS18, _POINTS19, _POINTS20, _POINTS21, _POINTS22, _POINTS23, _POINTS24, _POINTS25, _POINTS26, _POINTS27, _POINTS28, _POINTS29, _POINTS30, _POINTS31, _POINTS32, _POINTS33, _POINTS34, _POINTS35, _POINTS36, _POINTS37, _POINTS38, _POINTS39, _POINTS40, _POINTS41, _POINTS42, _POINTS43, _POINTS44, _POINTS45];
    $d = [_DESC01, _DESC02, _DESC03, _DESC04, _DESC05, _DESC06, _DESC07, _DESC08, _DESC09, _DESC10, _DESC11, _DESC12, _DESC13, _DESC14, _DESC15, _DESC16, _DESC17, _DESC18, _DESC19, _DESC20, _DESC21, _DESC22, _DESC23, _DESC24, _DESC25, _DESC26, _DESC27, _DESC28, _DESC29, _DESC30, _DESC31, _DESC32, _DESC33, _DESC34, _DESC35, _DESC36, _DESC37, _DESC38, _DESC39, _DESC40, _DESC41, _DESC42, _DESC43, _DESC44, _DESC45];
    $pts = explode(',', $conf['users']['points']);
    $phead = [
        ['content' => _ID],
        ['content' => _NAME],
        ['content' => _DESCRIPTION],
        ['content' => _POINTS, 'nosort' => 1],
    ];
    $prows = '';
    $count = count($p);
    for ($i = 0; $i < $count; $i++) {
        $prows .= $tpl->getHtmlFrag('table-row', [
            'cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
                ['content_html' => (string)($i + 1)],
                ['content_html' => $p[$i]],
                ['content_html' => $d[$i]],
                ['content_html' => $tpl->getHtmlFrag('input', [
                    'itype' => 'number',
                    'name_attr' => 'spoints[]',
                    'value_attr' => (string)$pts[$i],
                    'placeholder_text' => _POINTS,
                    'is_required' => true,
                ])],
            ]]),
        ]);
    }
    $pointv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'groups'],
            ['nameattr' => 'op', 'valueattr' => 'pointssave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'content_html' => $tpl->getHtmlFrag('table', [
            'head' => $phead,
            'rows_html' => $prows,
            'is_wrapless' => true,
        ]),
        'submit_label' => _SAVE,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $pointv]);
    setFoot();
}

function pointssave(): void {
    global $afile, $conf;
    $warn = !checkSiteToken();
    if (!$warn) {
        $spoints = getVar('post', 'spoints[]', 'num');
        if ($spoints) {
            $npoints = implode(',', $spoints);
            $cont = ['points' => $npoints];
            setConfigFile('users.php', $cont, $conf['users']);
        }
    }
    setRedirect($afile.'.php?name=groups&op=points', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function delete(): void {
    global $db, $afile, $conf;
    $warn = !checkSiteToken();
    $id = getVar('req', 'id', 'num');
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
        'ops' => ['name=groups', 'name=groups&amp;op=add', 'name=groups&amp;op=points', 'name=groups&amp;op=info'],
        'tabs' => [_HOME, _ADD, _POINTS, _INFO],
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
