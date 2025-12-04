<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');

function databaseNavi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    panel();
    $ops = ['name=database&amp;op=show', 'name=database&amp;op=show&amp;type=optimize', 'name=database&amp;op=show&amp;type=repair', 'name=database&amp;op=dump', 'name=database&amp;op=info'];
    $lang = [_HOME, _OPTIMIZE, _REPAIR, _INQUIRY, _INFO];
    return getAdminTabs(_DATABASE, 'database.png', '', $ops, $lang, [], [], $tab, $subtab);
}

function database(): void {
    global $db, $confdb, $admin_file;

    $type     = getVar('get', 'type', 'var'); // '', 'optimize', 'repair'
    $ftitleth = ($type === 'optimize' || $type === 'repair') ? _STATUS : _FUNCTIONS;

    // Tabelleninfos einmal einlesen
    $result = $db->sql_query('SHOW TABLE STATUS FROM `'.$confdb['name'].'`');
    $tables = [];
    while ($info = $db->sql_fetchrow($result)) {
        $tables[] = $info;
    }

    $total       = 0; // Summe Data + Index
    $totalfree   = 0; // Summe Data_free
    $total_rows  = 0; // Summe aller COUNT(*)
    $i           = 0;

    $content  = '<table class="sl_table_list_sort">';
    $content .= '<thead><tr>'
              . '<th>'._ID.'</th>'
              . '<th>'._TABLE.'</th>'
              . '<th>'._TYPE.'</th>'
              . '<th>'._DBCOLL.'</th>'
              . '<th>'._ROWS.'</th>'
              . '<th>'._DATE.'</th>'
              . '<th>'._SIZE.'</th>'
              . '<th>'._DBFREE.'</th>'
              . '<th class="{sorter: false}">'.$ftitleth.'</th>'
              . '</tr></thead><tbody>';

    foreach ($tables as $info) {
        $name    = $info['Name'];
        $tabeng  = $info['Engine'];
        $tabloc  = $info['Collation'];
        $crtime  = $info['Create_time'];

        // --- Exakte Zeilenzahl per COUNT(*) (MyISAM & InnoDB) ---
        $rows = (int) $info['Rows']; // Fallback

        $rowResult = $db->sql_query(
            'SELECT COUNT(*) AS cnt FROM `'.$confdb['name'].'`.`'.$name.'`'
        );
        if ($rowResult && $rowData = $db->sql_fetchrow($rowResult)) {
            $rows = (int) $rowData['cnt'];
        }
        $total_rows += $rows;

        // --- Tabellen- und Freispeichergröße ---
        $tabsize   = (int) $info['Data_length'] + (int) $info['Index_length'];
        $tabsizefr = (int) ($info['Data_free'] ?: 0);

        $total     += $tabsize;
        $totalfree += $tabsizefr;

        // Darstellung Data_free
        if ($tabeng === 'InnoDB') {
            $tabsizefrc = '<div class="sl_hidden">'.files_size($tabsizefr).'</div>';
        } else {
            $tabsizefrc = $tabsizefr
                ? '<div class="sl_red">'.files_size($tabsizefr).'</div>'
                : '<div class="sl_green">'.files_size($tabsizefr).'</div>';
        }

        // --- Status / Aktionen abhängig vom Modus ---
        if ($type === 'optimize') {
            $db->sql_query('ANALYZE TABLE `'.$confdb['name'].'`.`'.$name.'`');
            $oresult = $db->sql_query('OPTIMIZE TABLE `'.$confdb['name'].'`.`'.$name.'`');

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
                $rresult  = $db->sql_query('REPAIR TABLE `'.$confdb['name'].'`.`'.$name.'`');
                $ftitletd = $rresult
                    ? '<div class="sl_green">'._OK.'</div>'
                    : '<div class="sl_red">'._ERROR.'</div>';
            }

        } else {
            // Standardansicht mit Aktionen
            $ftitletd = add_menu(
                '<a href="'.$admin_file.'.php?name=database&amp;op=del&amp;tb='.$name.'&amp;id=1" '
                .'OnClick="return DelCheck(this, \''._CLEAN.' &quot;'.$name.'&quot;?\');" '
                .'title="'._CLEAN.'">'._CLEAN.'</a>'
                .'||'
                .'<a href="'.$admin_file.'.php?name=database&amp;op=del&amp;tb='.$name.'&amp;id=2" '
                .'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$name.'&quot;?\');" '
                .'title="'._ONDELETE.'">'._ONDELETE.'</a>'
            );
        }

        $i++;

        $content .= '<tr>'
                  . '<td>'.$i.'</td>'
                  . '<td>'.$name.'</td>'
                  . '<td>'.$tabeng.'</td>'
                  . '<td>'.$tabloc.'</td>'
                  . '<td>'.$rows.'</td>'
                  . '<td>'.format_time($crtime, _TIMESTRING).'</td>'
                  . '<td>'.files_size($tabsize).'</td>'
                  . '<td>'.$tabsizefrc.'</td>'
                  . '<td>'.$ftitletd.'</td>'
                  . '</tr>';
    }

    // --- Gesamtzeile wie in phpMyAdmin ---
    $content .= '<tr>'
              . '<td><strong>'.$i.'</strong></td>'
              . '<td>&nbsp;</td>'
              . '<td>&nbsp;</td>'
              . '<td>&nbsp;</td>'
              . '<td><strong>'.$total_rows.'</strong></td>'
              . '<td>&nbsp;</td>'
              . '<td><strong>'.files_size($total).'</strong></td>'
              . '<td><strong>'.files_size($totalfree).'</strong></td>'
              . '<td>&nbsp;</td>'
              . '</tr>';

    $content .= '</tbody></table>';

    // Nach OPTIMIZE: Totals für Info-Box neu berechnen
    if ($type === 'optimize') {
        $result    = $db->sql_query('SHOW TABLE STATUS FROM `'.$confdb['name'].'`');
        $total     = 0;
        $totalfree = 0;

        while ($info = $db->sql_fetchrow($result)) {
            $tabsize  = (int) $info['Data_length'] + (int) $info['Index_length'];
            $tabfree  = (int) ($info['Data_free'] ?: 0);

            $total     += $tabsize;
            $totalfree += $tabfree;
        }
    }

    head();

    // Navigation + Info-Boxen
    if (empty($type)) {
        $cont  = databaseNavi(0, 0, 0, 0);
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
        $db->sql_query('FLUSH TABLES');
        $cont = databaseNavi(0, 1, 0, 0);

        $infoText = _OPTIMIZE.': '.$confdb['name']
                  . '<br>'._TOTALSPACE.': '.files_size($total)
                  . '<br>'._TOTALFREE.': '.files_size($totalfree);

        $cont .= setTemplateWarning('warn', [
            'time' => '',
            'url'  => '',
            'id'   => 'info',
            'text' => $infoText
        ]);

    } elseif ($type === 'repair') {
        $cont = databaseNavi(0, 2, 0, 0);

        $infoText = _REPAIR.': '.$confdb['name']
                  . '<br>'._TOTALSPACE.': '.files_size($total)
                  . '<br>'._TOTALFREE.': '.files_size($totalfree);

        $cont .= setTemplateWarning('warn', [
            'time' => '',
            'url'  => '',
            'id'   => 'info',
            'text' => $infoText
        ]);
    }

    echo $cont
       . setTemplateBasic('open')
       . $content
       . setTemplateBasic('close');

    foot();
}

/*
function database(): void {
    global $db, $confdb, $admin_file;
    $type = getVar('get', 'type', 'var');
    $ftitleth = ($type === 'optimize' || $type === 'repair') ? _STATUS : _FUNCTIONS;
    $content ='<table class="sl_table_list_sort"><thead><tr><th>'._ID.'</th><th>'._TABLE.'</th><th>'._TYPE.'</th><th>'._DBCOLL.'</th><th>'._ROWS.'</th><th>'._DATE.'</th><th>'._SIZE.'</th><th>'._DBFREE.'</th><th class="{sorter: false}">'.$ftitleth.'</th></tr></thead><tbody>';
    $total = 0;
    $totalfree = 0;
    $i = 0;
    $result = $db->sql_query('SHOW TABLE STATUS FROM `'.$confdb['name'].'`');
    while ($info = $db->sql_fetchrow($result)) {
        $name = $info['Name'];
        $tabeng = $info['Engine'];
        $tabloc = $info['Collation'];
        $tabsize = $info['Data_length'] + $info['Index_length'];
        $total += $tabsize;
        $tabsizefr = $info['Data_free'] ?: 0;
        if ($tabeng === 'InnoDB') {
            $tabsizefrc = '<div class="sl_hidden">'.files_size($tabsizefr).'</div>';
        } else {
            $tabsizefrc = ($tabsizefr) ? '<div class="sl_red">'.files_size($tabsizefr).'</div>' : '<div class="sl_green">'.files_size($tabsizefr).'</div>';
        }
        $totalfree += $tabsizefr;
        $crtime = $info['Create_time'];
        $rows = $info['Rows'];
        if ($type === 'optimize') {
            $db->sql_query('ANALYZE TABLE `'.$name.'`');
            $oresult = $db->sql_query('OPTIMIZE TABLE `'.$name.'`');
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
                $rresult = $db->sql_query('REPAIR TABLE `'.$name.'`');
                $ftitletd = (!$rresult) ? '<div class="sl_red">'._ERROR.'</div>' : '<div class="sl_green">'._OK.'</div>';
            }
        } else {
            $ftitletd = add_menu('<a href="'.$admin_file.'.php?op=database_del&amp;tb='.$name.'&amp;id=1" OnClick="return DelCheck(this, \''._CLEAN.' &quot;'.$name.'&quot;?\');" title="'._CLEAN.'">'._CLEAN.'</a>||<a href="'.$admin_file.'.php?op=database_del&amp;tb='.$name.'&amp;id=2" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$name.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>');
        }
        $i++;
        $content .= '<tr><td>'.$i.'</td><td>'.$name.'</td><td>'.$tabeng.'</td><td>'.$tabloc.'</td><td>'.$rows.'</td><td>'.format_time($crtime, _TIMESTRING).'</td><td>'.files_size($tabsize).'</td><td>'.$tabsizefrc.'</td><td>'.$ftitletd.'</td></tr>';
    }
    $content .= '</tbody></table>';
    head();
    if (empty($type)) {
        $cont = databaseNavi(0, 0, 0, 0);
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _OPTTEXT]);
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _REPTEXT]);
    } elseif ($type === 'optimize') {
        $db->sql_query('FLUSH TABLES');
        $cont = databaseNavi(0, 1, 0, 0);
        $totalspace = $total - $totalfree;
        $info = _OPTIMIZE.': '.$confdb['name'].'<br>'._TOTALSPACE.': '.files_size($totalspace).'<br>'._TOTALFREE.': '.files_size($totalfree);
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $info]);
    } elseif ($type === 'repair') {
        $cont = databaseNavi(0, 2, 0, 0);
        $info = _REPAIR.': '.$confdb['name'].'<br>'._TOTALSPACE.': '.files_size($total);
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $info]);
    }
    echo $cont.setTemplateBasic('open').$content.setTemplateBasic('close');
    foot();
}
*/

function dump(): void {
    global $db, $confdb, $admin_file;
    $type = getVar('post', 'type', 'var', '');
    $pstring = filter_input(INPUT_POST, 'string', FILTER_UNSAFE_RAW) ?? '';
    head();
    $cont = databaseNavi(0, 3, 0, 0);
    if ($type === 'dump' && !empty($pstring)) {
        $replacements = ['{prefix}' => $confdb['prefix'], '{engine}' => $confdb['engine'], '{charset}' => $confdb['charset'], '{collate}' => $confdb['collate']];
        $info = '';
        $queries = array_filter(array_map('trim', explode(';', $pstring)));
        foreach ($queries as $query) {
            $stringdb = str_replace(array_keys($replacements), array_values($replacements), $query);
            $stringdb = stripslashes($stringdb);
            $result = $db->sql_query($stringdb);
            if (preg_match('#^\s*(ALTER|ANALYZE|CREATE|DELETE|DROP|INSERT|OPTIMIZE|RENAME|REPAIR|REPLACE|SET|TRUNCATE|UPDATE)\s#i', $stringdb, $matches)) {
                $tablename = '';
                if (preg_match('#`([^`]+)`#', $stringdb, $tablematch)) $tablename = $tablematch[1];
                if ($result) {
                    $status = '<span class="sl_green">'._OK.'</span>';
                } else {
                    $error = $db->sql_error();
                    $errmsg = htmlspecialchars($error['message']);
                    $errinfo = $error['sqlstate'].' / '.$error['code'];
                    $status = '<span class="sl_red">'._ERROR.' - '.$errinfo.' - '.$errmsg.'</span>';
                }
                $info .= _TABLE.': '.$tablename.'<br>'._STATUS.': '.$status.'<br>';
            }
        }
        $cont .= !empty($info) ? setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _INQUIRY.': '.$confdb['name'].'<br>'.$info]) : setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _DBERROR]);
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _DBINFO]);
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _DBWARN]);
    }
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$admin_file.'.php" method="post">
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
    foot();
}

function info(): void {
    head();
    echo databaseNavi(0, 4, 0, 0).'<div id="repadm_info">'.adm_info(1, 0, 'database').'</div>';
    foot();
}

switch ($op) {
    default: database(); break;
    case 'dump': dump(); break;

    case 'del':
    $tb = getVar('get', 'tb', 'var');
    $delid = getVar('get', 'id', 'num');
    if ($tb && $delid == 1) {
        $db->sql_query('TRUNCATE TABLE `'.$tb.'`');
    } elseif ($tb && $delid == 2) {
        $db->sql_query('DROP TABLE `'.$tb.'`');
    }
    header('Location: '.$admin_file.'.php?name=database&op=show');
    break;

    case 'info': info(); break;
}