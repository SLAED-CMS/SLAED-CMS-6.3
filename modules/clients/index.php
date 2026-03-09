<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function clients(): void {
    global $db, $conf, $afile, $user, $stop, $info;
    setHead(['title' => _PRODUCTSINFO]);
    $cont = setTemplateBasic('title', ['{%title%}' => _PRODUCTSINFO]);
    $cont .= getUserNav();
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
    if ($info) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $info]);
    $result = $db->getSqlQuery('SELECT id, title, infotext, url, num, hits, prod_id FROM '.PREFIX_DB.'_clients_down WHERE status != \'0\'');
    if ($db->getSqlRowCount($result) > 0) {
        $uid = (int)($user[0] ?? 0);
        $conts = '';
        $cont .= setTemplateBasic('open');
        $cont .= '<table class="sl_table_list_sort"><thead class="sl_table_list_head"><tr><th>'._ID.'</th><th>'._CTITLE.'</th><th>'._CVERSION.'</th><th>'._CLOADS.'</th><th>'._FUNCTIONS.'</th></tr></thead><tbody class="sl_table_list_body">';
        $i = 0;
        $a = 1;
        while ([$id, $title, $infotext, $url, $num, $hits, $prod] = $db->getSqlRow($result)) {
            $tpath = 'uploads/clients/thumb/'.$id.'_'.$uid;
            if (file_exists($tpath.'.zip')) $tpath .= '.zip';
            elseif (file_exists($tpath.'.gz')) $tpath .= '.gz';
            elseif (file_exists($tpath.'.bz2')) $tpath .= '.bz2';
            else $tpath = '';
            $dtitle = $tpath ? _CDOWN : _GZIPGEN;
            $moder = (is_moder($conf['name'])) ? '<a href="'.$afile.'.php?op=clients_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||' : '';
            $acont = add_menu($moder.'<a OnClick="HideShow(\'cl'.$i.'\', \'blind\', \'up\', 500);" title="'._CINFO.'">'._CINFO.'</a>||<a href="index.php?name='.$conf['name'].'&amp;op=download&amp;id='.$id.'&amp;prod_id='.$prod.'" title="'.$dtitle.'">'.$dtitle.'</a>||<a href="index.php?name='.$conf['name'].'&amp;op=generator&amp;id='.$id.'&amp;prod_id='.$prod.'" title="'._CLIZENS.'">'._CLIZENS.'</a>');
            $time = (file_exists('uploads/clients/'.$url)) ? date(_TIMESTRING, filemtime('uploads/clients/'.$url)) : _NO_INFO;
            $cont .= '<tr id="'.$a.'">'
            .'<td><a href="#'.$a.'" title="'.$a.'" class="sl_pnum">'.$a.'</a></td>'
            .'<td>'.title_tip(_CDATE.': '.$time).$title.'</td>'
            .'<td>'.$num.'</td>'
            .'<td>'.$hits.'</td>'
            .'<td>'.$acont.'</td></tr>';
            $conts .= '<div id="cl'.$i.'" class="sl_none">'.filterReplaceText(filterMarkdown($infotext, $conf['name'], false), $conf['name']).'</div>';
            $i++;
            $a++;
        }
        $cont .= '</tbody></table>'.$conts;
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function download(): void {
    global $db, $user, $stop, $info;
    $uid = (int)($user[0] ?? 0);
    $result = $db->getSqlQuery('SELECT website FROM '.PREFIX_DB.'_clients WHERE active = 1 AND id_user = :user_id', ['user_id' => $uid]);
    if (is_user() && $db->getSqlRowCount($result) > 0) {
        $id = getVar('get', 'id', 'num');
        [$pid, $url, $num] = $db->getSqlRow($db->getSqlQuery('SELECT id, url, num FROM '.PREFIX_DB.'_clients_down WHERE status != 0 AND id = :id', ['id' => $id]));
        $tpath = 'uploads/clients/thumb/'.$pid.'_'.$uid;
        if (file_exists($tpath.'.zip')) $tpath .= '.zip';
        elseif (file_exists($tpath.'.gz')) $tpath .= '.gz';
        elseif (file_exists($tpath.'.bz2')) $tpath .= '.bz2';
        else $tpath = '';
        if (!$tpath) {
            $ipath = 'uploads/clients/images';
            $path = 'uploads/clients/'.$url;
            $code = base64_encode($uid.'-'.getip().'-'.getagent());

            # Ð¨Ð¸Ñ„Ñ€ÑƒÐµÐ¼ Ñ„Ð°Ð¹Ð»Ñ‹
            $input = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S' ,'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '='];
            $output = ['{', 'Â©', '"', 'Â§', '$', 'Ð¦', '&', '/', '(', '', 'â„–', 'ÐŽ', '<', '%', 'â€¹', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'Ðµ', 'B', 'Ñˆ', 'D', 'E', 'Ñ', 'G', 'Ð´', 'I', 'J', 'K', 'L', 'â€¡', 'Ð¨', 'O', 'Ð–', 'Q', 'Â·', 'Ð’' ,'!', 'U', 'â€ ', 'Â¶', 'X', 'Y', 'Z', 'Ñ—'];
            $sourse = str_replace($input, $output, $code);
            if (file_exists($path.'/html/templates/admin/images/admin/admins.png')) hidden($path.'/html/templates/admin/images/admin/admins.png', $ipath.'/admins.png', $sourse.'IENDÂ®B`â€š');
            if (file_exists($path.'/html/templates/admin/images/admin/forum.png')) hidden($path.'/html/templates/admin/images/admin/forum.png', $ipath.'/forum.png', $code);
            if (file_exists($path.'/html/templates/admin/images/language/german.png')) hidden($path.'/html/templates/admin/images/language/german.png', $ipath.'/german.png', $code);
            if (file_exists($path.'/html/templates/admin/images/admin/menu.png')) hidden($path.'/html/templates/admin/images/admin/menu.png', $ipath.'/menu.png', $sourse.'IENDÂ®B`â€š'.$code);

            if (file_exists($path.'/html/config/license.txt')) generator($path.'/html/config');
            if (file_exists($path.'/setup/config/license.txt')) generator($path.'/setup/config');
            if (file_exists($path.'/update/config/license.txt')) generator($path.'/update/config');
            if (!addCompress('uploads/clients/thumb', $path, $pid.'_'.$uid, 'auto')) {
                $stop = _CLERROR2;
                clients();
            } else {
                $tpath = 'uploads/clients/thumb/'.$pid.'_'.$uid;
                if (file_exists($tpath.'.zip')) $tpath .= '.zip';
                elseif (file_exists($tpath.'.gz')) $tpath .= '.gz';
                elseif (file_exists($tpath.'.bz2')) $tpath .= '.bz2';
                else $tpath = '';
                if (!$tpath) {
                    $stop = _CLERROR2;
                    clients();
                }
                $info = _GZIPOK;
                clients();
            }
        } else {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_clients_down SET hits = hits+1 WHERE id = :id', ['id' => $id]);
            stream($tpath, date('d.m.Y').'_'.str_replace(' ', '_', $num).strtolower((string)strrchr($tpath, '.')));
        }
    } else {
        $stop = _CLERROR;
        clients();
    }
}

function hidden(string $path, string $ipath, string $code): void {
    # Ð§Ð¸Ñ‚Ð°ÐµÐ¼ Ð¸ Ð¿ÐµÑ€ÐµÐ·Ð°Ð¿Ð¸ÑÑ‹Ð²Ð°ÐµÐ¼ Ñ„Ð°Ð¹Ð»
    $content = file_get_contents($ipath);
    if ($content === false) return;
    $code = $content.$code;
    $fp = fopen($path, 'wb');
    if ($fp === false) return;
    fwrite($fp, $code);
    fclose($fp);
    # ÐœÐµÐ½ÑÐµÐ¼ Ð²Ñ€ÐµÐ¼Ñ Ñ„Ð°Ð¹Ð»Ð°
    $atime = filemtime($ipath);
    if ($atime !== false) {
        touch($path, $atime, $atime);
    }
}

function generator(string $path = ''): void {
    global $db, $user, $stop;
    $uid = (int)($user[0] ?? 0);
    $result = $db->getSqlQuery('SELECT website FROM '.PREFIX_DB.'_clients WHERE active = 1 AND id_user = :user_id', ['user_id' => $uid]);
    if (is_user() && $db->getSqlRowCount($result) > 0) {
        $domains = [];
        $code = '';
        while ([$domain] = $db->getSqlRow($result)) $domains[] = $domain;
        $domains = preg_replace('#https?://|www\.#i', '', implode(',', $domains));
        $id = getVar('get', 'id', 'num');
        [$pass] = $db->getSqlRow($db->getSqlQuery('SELECT code FROM '.PREFIX_DB.'_clients_down WHERE status != 0 AND id = :id', ['id' => $id]));
        $massiv = explode(',', $domains);
        foreach ($massiv as $val) {
            if ($val != '') {
                $code .= md5($pass.$val.$pass)."\n";
                $code .= md5($pass.'www.'.$val.$pass)."\n";
            }
        }
        $code .= md5($pass.'localhost'.$pass)."\n";
        $code .= md5($pass.'127.0.0.1'.$pass);
        $dir = ($path) ? $path : 'uploads/clients/thumb/';
        $nfile = ($path) ? 'license' : $uid;
        $fp = fopen($dir.'/'.$nfile.'.txt', 'wb');
        if ($fp === false) {
            if (!$path) {
                $stop = _CLERROR2;
                clients();
            }
            return;
        }
        fwrite($fp, $code);
        fclose($fp);
        if (!$path) stream($dir.'/'.$uid.'.txt', 'license.txt');
    } else {
        $stop = _CLERROR;
        clients();
    }
}

switch($op) {
    default: clients(); break;
    case 'download': download(); break;
    case 'generator': generator(); break;
}
