<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function groups(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=groups', 'name=groups&amp;op=add', 'name=groups&amp;op=points', 'name=groups&amp;op=info'], 'tabs' => [_HOME, _ADD, _POINTS, _INFO]]);
    $result = $db->getSqlQuery('SELECT id, name, intro, points, extra, rank, color FROM '.PREFIX_DB.'_groups ORDER BY points, extra');
    if ($db->getSqlRowCount($result) > 0) {
        $head = '<th>'._ID.'</th><th class="{sorter: false}">'._RANK.'</th><th>'._GROUP.'</th><th>'._POINTS.'</th><th>'.cutstr(_USERSCOUNT, 5, 1).'</th><th>'.cutstr(_SPEC, 4, 1).'</th><th class="{sorter: false}">'._FUNCTIONS.'</th>';
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
            $acts = adminMenuItems([
                '<a href="'.$userlink.'" title="'._MVIEW.'">'._MVIEW.'</a>',
                '<a href="'.$afile.'.php?name=groups&amp;op=add&amp;id='.$grid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>',
                '<a href="'.$afile.'.php?name=groups&amp;op=delete&amp;id='.$grid.'" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$grname.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>',
            ]);
            $cols = '<td>'.$grid.'</td>'
               .'<td><img src="templates/'.$conf['theme'].'/images/ranks/'.$rank.'" alt="'._RANK.'" title="'._RANK.'"></td>'
               .'<td>'.title_tip(_DESCRIPTION.': '.$description).'<span style="color: '.$color.'">'.$grname.'</span></td>'
               .'<td>'.$points.'</td>'
               .'<td>'.$users_num.'</td>'
               .'<td>'.$extra.'</td>'
               .'<td>'.$acts.'</td>';
            $rows .= getAdminTableRow($cols);
        }
        $cont .= getAdminTable($head, $rows);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _NO_INFO]);
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
    $cont = setAdminNavi(['ops' => ['name=groups', 'name=groups&amp;op=add', 'name=groups&amp;op=points', 'name=groups&amp;op=info'], 'tabs' => [_HOME, _ADD, _POINTS, _INFO], 'tab' => 1]);
    $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _GROUPSI]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => $stop]);
    $hide = '<input type="hidden" name="gid" value="'.$gid.'"><input type="hidden" name="name" value="groups"><input type="hidden" name="op" value="save">';
    $rows = '';
    $rows .= getAdminFormRow(_NAME.':', '<input type="text" name="grname" value="'.$grname.'" maxlength="255" class="sl_form" placeholder="'._NAME.'" required>');
    $rows .= getAdminFormRow(_DESCRIPTION.':', '<textarea name="description" cols="65" rows="5" class="sl_form" placeholder="'._DESCRIPTION.'">'.$description.'</textarea>');
    $path = 'templates/'.$conf['theme'].'/images/ranks/';
    $pickopts = '';
    foreach (scandir($path) as $entry) {
        if (preg_match('#(\.gif|\.png|\.jpg|\.jpeg)$#is', $entry)) {
            $pickopts .= getAdminOption($path.$entry, $entry, $rank == $entry);
        }
    }
    $pick = getAdminSelect('rank', $pickopts, 'sl_form', 'id="img_replace"');
    $rows .= getAdminFormRow(_IMG.':', $pick);
    $rows .= getAdminFormRow(_RANK.':', '<img src="'.$path.$rank.'" id="picture" alt="'._RANK.'">');
    $rows .= getAdminFormRow(_COLOR.':', '<input type="color" name="color" value="'.$color.'" class="sl_form">');
    $rows .= getAdminFormRow(_POINTSNEEDED.':', '<input type="number" name="points" value="'.$points.'" class="sl_form" placeholder="'._POINTSNEEDED.'">');
    $rows .= getAdminFormRow(_SPEC_GROUP.':<div class="sl_small">'._GRSINFO.'</div>', '<input type="checkbox" name="grextra" value="1"'.$check.'>');
    $rows .= getAdminFormWide('<input type="submit" value="'._SAVE.'" class="sl_but_blue">', '', 'sl_center');
    $cont .= getAdminForm($afile.'.php', $rows, $hide);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $conf, $stop;
    $gid = getVar('post', 'gid', 'num');
    $grname = getVar('post', 'grname', 'title');
    $description = getVar('post', 'description', 'text');
    $points = getVar('post', 'points', 'num');
    $grextra = getVar('post', 'grextra', 'num');
    $rank = getVar('post', 'rank', 'title');
    $color = getVar('post', 'color', 'title');
    if (!$grname) $stop[] = _CERROR;
    if (!is_numeric($points) && $grextra != '1') $stop = _NONUMVALUE;
    if (!$stop) {
        $points = ($grextra == '1') ? '0' : $points;
        $rank = str_replace('templates/'.$conf['theme'].'/images/ranks/', '', $rank);
        if ($gid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_groups SET name = :name, intro = :intro, points = :points, extra = :extra, rank = :rank, color = :color WHERE id = :id', ['name' => $grname, 'intro' => $description, 'points' => $points, 'extra' => $grextra, 'rank' => $rank, 'color' => $color, 'id' => $gid]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_groups (name, intro, points, extra, rank, color) VALUES (:name, :intro, :points, :extra, :rank, :color)', ['name' => $grname, 'intro' => $description, 'points' => $points, 'extra' => $grextra, 'rank' => $rank, 'color' => $color]);
        }
        setRedirect($afile.'.php?name=groups');
    } else {
        add();
    }
}

function points(): void {
    global $afile, $conf;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=groups', 'name=groups&amp;op=add', 'name=groups&amp;op=points', 'name=groups&amp;op=info'], 'tabs' => [_HOME, _ADD, _POINTS, _INFO], 'tab' => 2]);
    $p = [_POINTS01, _POINTS02, _POINTS03, _POINTS04, _POINTS05, _POINTS06, _POINTS07, _POINTS08, _POINTS09, _POINTS10, _POINTS11, _POINTS12, _POINTS13, _POINTS14, _POINTS15, _POINTS16, _POINTS17, _POINTS18, _POINTS19, _POINTS20, _POINTS21, _POINTS22, _POINTS23, _POINTS24, _POINTS25, _POINTS26, _POINTS27, _POINTS28, _POINTS29, _POINTS30, _POINTS31, _POINTS32, _POINTS33, _POINTS34, _POINTS35, _POINTS36, _POINTS37, _POINTS38, _POINTS39, _POINTS40, _POINTS41, _POINTS42, _POINTS43, _POINTS44, _POINTS45];
    $d = [_DESC01, _DESC02, _DESC03, _DESC04, _DESC05, _DESC06, _DESC07, _DESC08, _DESC09, _DESC10, _DESC11, _DESC12, _DESC13, _DESC14, _DESC15, _DESC16, _DESC17, _DESC18, _DESC19, _DESC20, _DESC21, _DESC22, _DESC23, _DESC24, _DESC25, _DESC26, _DESC27, _DESC28, _DESC29, _DESC30, _DESC31, _DESC32, _DESC33, _DESC34, _DESC35, _DESC36, _DESC37, _DESC38, _DESC39, _DESC40, _DESC41, _DESC42, _DESC43, _DESC44, _DESC45];
    $pts = explode(',', $conf['users']['points']);
    $phead = '<th>'._ID.'</th><th>'._NAME.'</th><th>'._DESCRIPTION.'</th><th class="{sorter: false}">'._POINTS.'</th>';
    $prows = '';
    $count = count($p);
    for ($i = 0; $i < $count; $i++) {
        $cells = '<td>'.($i + 1).'</td><td>'.$p[$i].'</td><td>'.$d[$i].'</td><td><input type="number" value="'.$pts[$i].'" name="spoints[]" class="sl_field" placeholder="'._POINTS.'" required></td>';
        $prows .= getAdminTableRow($cells);
    }
    $pointv = getAdminConfSave(getAdminTable($phead, $prows), 'groups', 'pointssave', _SAVE);
    echo $cont.getAdminBox($pointv);
    setFoot();
}

function pointssave(): void {
    global $afile, $conf;
    $spoints = getVar('post', 'spoints', 'num');
    if ($spoints) {
        $npoints = implode(',', $spoints);
        $cont = ['points' => $npoints];
        setConfigFile('users.php', $cont, $conf['users']);
    }
    setRedirect($afile.'.php?name=groups&op=points');
}

function delete(): void {
    global $db, $afile, $conf;
    $id = getVar('get', 'id', 'num');
    if ($id) {
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
    setRedirect($afile.'.php?name=groups');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=groups', 'name=groups&amp;op=add', 'name=groups&amp;op=points', 'name=groups&amp;op=info'], 'tabs' => [_HOME, _ADD, _POINTS, _INFO], 'tab' => 3]);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
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

