<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=database', 'name=database&amp;type=optimize', 'name=database&amp;type=repair', 'name=database&amp;op=dump', 'name=database&amp;op=info'];
    $lang = [_HOME, _OPTIMIZE, _REPAIR, _INQUIRY, _INFO];
    return getAdminTabs('', $ops, $lang, [], [], $tab, $subtab);
}

function database(): void {
    global $db, $conf, $afile;
    $type = getVar('get', 'type', 'var');
    $ftitleth = ($type === 'optimize' || $type === 'repair') ? _STATUS : _FUNCTIONS;
    $dbname = preg_replace('#[^a-zA-Z0-9_]#', '', (string)($conf['db']['name'] ?? ''));
    if ($dbname === '') {
        setHead();
        echo navi(0, 0, 0, 0).setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _ERROR]);
        setFoot();
        return;
    }
    $result = $db->getSqlQuery('SHOW TABLE STATUS FROM `'.$dbname.'`');
    $tables = [];
    while ($info = $db->getSqlRow($result)) {
        $tables[] = $info;
    }

    $total = 0;
    $totalfree = 0;
    $total_rows = 0;
    $i = 0;

    $content = '<table class="sl_table_list_sort">';
    $content .= '<thead><tr>'
              .'<th>'._ID.'</th>'
              .'<th>'._TABLE.'</th>'
              .'<th>'._TYPE.'</th>'
              .'<th>'._DBCOLL.'</th>'
              .'<th>'._ROWS.'</th>'
              .'<th>'._DATE.'</th>'
              .'<th>'._SIZE.'</th>'
              .'<th>'._DBFREE.'</th>'
              .'<th class="{sorter: false}">'.$ftitleth.'</th>'
              .'</tr></thead><tbody>';

    foreach ($tables as $info) {
        $name = $info['Name'];
        $tabeng = $info['Engine'];
        $tabloc = $info['Collation'];
        $crtime = $info['Create_time'];
        $rows = (int) $info['Rows'];
        $res = $db->getSqlQuery('SELECT COUNT(*) AS cnt FROM `'.$conf['db']['name'].'`.`'.$name.'`');
        if ($res && $row = $db->getSqlRow($res)) $rows = (int) $row['cnt'];
        $total_rows += $rows;

        // --- Table  und free space size ---
        $tabsize   = (int) $info['Data_length'] + (int) $info['Index_length'];
        $tabsizefr = (int) ($info['Data_free'] ?: 0);

        $total     += $tabsize;
        $totalfree += $tabsizefr;

        // Darstellung Data_free
        if ($tabeng === 'InnoDB') {
            $tabsizefrc = '<div class="sl_hidden">'.filterSize($tabsizefr).'</div>';
        } else {
            $tabsizefrc = $tabsizefr
                ? '<div class="sl_red">'.filterSize($tabsizefr).'</div>'
                : '<div class="sl_green">'.filterSize($tabsizefr).'</div>';
        }

        // --- Status / Actions depending on mode ---
        if (!preg_match('#^[a-zA-Z0-9_]+$#', (string)$name)) {
            continue;
        }
        if ($type === 'optimize') {
            $db->getSqlQuery('ANALYZE TABLE `'.$dbname.'`.`'.$name.'`');
            $oresult = $db->getSqlQuery('OPTIMIZE TABLE `'.$dbname.'`.`'.$name.'`');

            if (!$oresult) {
                $ftitletd = '<div class="sl_red">'._ERROR.'</div>';
            } elseif ($tabeng === 'InnoDB') {
                $ftitletd = '<div class="sl_green">'._OPTIMIZED.'</div>';
            } elseif ($tabeng === 'MyISAM' && !$info['Data_free']) {
                $ftitletd = '<div class="sl_red">'._ALREADYOPTIMIZED.'</div>';
            } else {
                $ftitletd = '<div class="sl_green">'._OPTIMIZED.'</div>';
            }

        } elseif ($type === 'repair') {
            if ($tabeng === 'InnoDB') {
                $ftitletd = '<div class="sl_hidden">'._NO.'</div>';
            } else {
                $rresult  = $db->getSqlQuery('REPAIR TABLE `'.$dbname.'`.`'.$name.'`');
                $ftitletd = $rresult
                    ? '<div class="sl_green">'._OK.'</div>'
                    : '<div class="sl_red">'._ERROR.'</div>';
            }

        } else {
            // Standardansicht mit Aktionen
            $ftitletd = add_menu(
                '<a href="'.$afile.'.php?name=database&amp;op=del&amp;tb='.$name.'&amp;id=1" '
                .'OnClick="return DelCheck(this, \''._CLEAN.' &quot;'.$name.'&quot;?\');" '
                .'title="'._CLEAN.'">'._CLEAN.'</a>'
                .'||'
                .'<a href="'.$afile.'.php?name=database&amp;op=del&amp;tb='.$name.'&amp;id=2" '
                .'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$name.'&quot;?\');" '
                .'title="'._ONDELETE.'">'._ONDELETE.'</a>'
            );
        }

        $i++;

        $content .= '<tr>'
                  .'<td>'.$i.'</td>'
                  .'<td>'.$name.'</td>'
                  .'<td>'.$tabeng.'</td>'
                  .'<td>'.$tabloc.'</td>'
                  .'<td>'.$rows.'</td>'
                  .'<td>'.format_time($crtime, _TIMESTRING).'</td>'
                  .'<td>'.filterSize($tabsize).'</td>'
                  .'<td>'.$tabsizefrc.'</td>'
                  .'<td>'.$ftitletd.'</td>'
                  .'</tr>';
    }

    // --- Gesamtzeile wie in phpMyAdmin ---
    $content .= '<tr>'
              .'<td><strong>'.$i.'</strong></td>'
              .'<td>&nbsp;</td>'
              .'<td>&nbsp;</td>'
              .'<td>&nbsp;</td>'
              .'<td><strong>'.$total_rows.'</strong></td>'
              .'<td>&nbsp;</td>'
              .'<td><strong>'.filterSize($total).'</strong></td>'
              .'<td><strong>'.filterSize($totalfree).'</strong></td>'
              .'<td>&nbsp;</td>'
              .'</tr>';

    $content .= '</tbody></table>';

    // After OPTIMIZE: Totals to recalculate info box
    if ($type === 'optimize') {
        $result    = $db->getSqlQuery('SHOW TABLE STATUS FROM `'.$dbname.'`');
        $total     = 0;
        $totalfree = 0;

        while ($info = $db->getSqlRow($result)) {
            $tabsize  = (int) $info['Data_length'] + (int) $info['Index_length'];
            $tabfree  = (int) ($info['Data_free'] ?: 0);

            $total     += $tabsize;
            $totalfree += $tabfree;
        }
    }

    setHead();

    // Navigation + Info-Boxen
    if (empty($type)) {
        $cont  = navi(0, 0, 0, 0);
        $cont .= setTemplateWarning('warn', [
            'time' => '',
            'url'  => '',
            'id'   => 'warn',
            'text' => _OPTTEXT
        ]);
        $cont .= setTemplateWarning('warn', [
            'time' => '',
            'url'  => '',
            'id'   => 'info',
            'text' => _REPTEXT
        ]);

    } elseif ($type === 'optimize') {
        $db->getSqlQuery('FLUSH TABLES');
        $cont = navi(0, 1, 0, 0);

        $info = _OPTIMIZE.': '.$conf['db']['name']
                  .'<br>'._TOTALSPACE.': '.filterSize($total)
                  .'<br>'._TOTALFREE.': '.filterSize($totalfree);

        $cont .= setTemplateWarning('warn', [
            'time' => '',
            'url'  => '',
            'id'   => 'info',
            'text' => $info
        ]);

    } elseif ($type === 'repair') {
        $cont = navi(0, 2, 0, 0);

        $info = _REPAIR.': '.$conf['db']['name']
                  .'<br>'._TOTALSPACE.': '.filterSize($total)
                  .'<br>'._TOTALFREE.': '.filterSize($totalfree);

        $cont .= setTemplateWarning('warn', [
            'time' => '',
            'url'  => '',
            'id'   => 'info',
            'text' => $info
        ]);
    }

    echo $cont
       .setTemplateBasic('open')
       .$content
       .setTemplateBasic('close');

    setFoot();
}

function dump(): void {
    global $db, $conf, $afile;
    $type = getVar('post', 'type', 'var', '');
    $pstring = filter_input(INPUT_POST, 'string', FILTER_UNSAFE_RAW) ?? '';
    setHead();
    $cont = navi(0, 3, 0, 0);
    if ($type === 'dump' && !empty($pstring)) {
        $replacements = ['{prefix}' => $conf['db']['prefix'], '{engine}' => $conf['db']['engine'], '{charset}' => $conf['db']['charset'], '{collate}' => $conf['db']['collate']];
        $info = '';
        $queries = array_filter(array_map('trim', explode(';', $pstring)));
        foreach ($queries as $query) {
            $stringdb = str_replace(array_keys($replacements), array_values($replacements), $query);
            $stringdb = stripslashes($stringdb);
            $result = $db->getSqlQuery($stringdb);
            if (preg_match('#^\s*(ALTER|ANALYZE|CREATE|DELETE|DROP|INSERT|OPTIMIZE|RENAME|REPAIR|REPLACE|SET|TRUNCATE|UPDATE)\s#i', $stringdb, $matches)) {
                $tablename = '';
                if (preg_match('#`([^`]+)`#', $stringdb, $tablematch)) $tablename = $tablematch[1];
                if ($result) {
                    $status = '<span class="sl_green">'._OK.'</span>';
                } else {
                    $error = $db->getSqlError();
                    $errmsg = htmlspecialchars($error['message']);
                    $errinfo = $error['sqlstate'].' / '.$error['code'];
                    $status = '<span class="sl_red">'._ERROR.' - '.$errinfo.' - '.$errmsg.'</span>';
                }
                $info .= _TABLE.': '.$tablename.'<br>'._STATUS.': '.$status.'<br>';
            }
        }
        $cont .= !empty($info) ? setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _INQUIRY.': '.$conf['db']['name'].'<br>'.$info]) : setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _DBERROR]);
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _DBINFO]);
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _DBWARN]);
    }
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post">
        <table class="sl_table_edit">
            <tr>
                <td>'.textarea_code('code', 'string', 'sl_form', 'text/x-mysql', stripslashes($pstring)).'</td>
            </tr>
            <tr>
                <td class="sl_center">
                    <input type="hidden" name="name" value="database">
                    <input type="hidden" name="op" value="dump">
                    <input type="hidden" name="type" value="dump">
                    <input type="submit" value="'._EXECUTE.'" class="sl_but_blue">
                </td>
            </tr>
        </table>
    </form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function info(): void {
    setHead();
    echo navi(0, 4, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

function del(): void {
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
    case 'del': del(); break;
    case 'info': info(); break;
}
