<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function clients(): void {
    global $db, $conf, $afile, $user, $stop, $info, $tpl, $prs;
    getLang('clients');
    setHead(['title' => _PRODUCTSINFO]);
    $cont = $tpl->getHtmlFrag('title', ['title' => _PRODUCTSINFO, 'is_level_one' => true]);
    $cont .= getUserNav();
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
    if ($info) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => $info]);
    $result = $db->getSqlQuery('SELECT id, title, body, url, num, hits, pid FROM '.PREFIX_DB.'_clients_down WHERE status != \'0\'');
    if ($db->getSqlRowCount($result) > 0) {
        $uid = (int)($user[0] ?? 0);
        $conts = '';
        $rows = [];
        $i = 0;
        $a = 1;
        while ($row = $db->getSqlRow($result)) {
            [$id, $title, $body, $url, $num, $hits, $prod] = $row;
            $tpath = UPLOADS_DIR.'/clients/thumb/'.$id.'_'.$uid;
            if (file_exists($tpath.'.zip')) $tpath .= '.zip';
            elseif (file_exists($tpath.'.gz')) $tpath .= '.gz';
            elseif (file_exists($tpath.'.bz2')) $tpath .= '.bz2';
            else $tpath = '';
            $dtitle = $tpath ? _CDOWN : _GZIPGEN;
            $moder = (is_moder($conf['name'])) ? $tpl->getHtmlFrag('link', ['href' => $afile.'.php?op=clients_add&id='.$id, 'title' => _FULLEDIT, 'label' => _FULLEDIT]).'||' : '';
            $acont = getActionMenu(explode('||',
                $moder
                .$tpl->getHtmlFrag('link', ['href' => '#', 'title' => _CINFO, 'label' => _CINFO, 'onclick_attr' => 'data-sl-toggle-control="cl'.$i.'" data-sl-toggle-effect="slide" data-sl-toggle-duration="500"']).'||'
                .$tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$conf['name'].'&op=download&id='.$id.'&pid='.$prod, 'title' => $dtitle, 'label' => $dtitle]).'||'
                .$tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$conf['name'].'&op=generator&id='.$id.'&pid='.$prod, 'title' => _CLIZENS, 'label' => _CLIZENS])
            ));
            $time = (file_exists(UPLOADS_DIR.'/clients/'.$url)) ? date(_TIMESTRING, filemtime(UPLOADS_DIR.'/clients/'.$url)) : _NO_INFO;
            $rows[] = [
                'id' => (string)$a,
                'cells' => [
                    ['text' => (string)$a, 'href' => '#'.$a, 'title' => (string)$a, 'is_num' => true],
                    ['prefix_html' => getTplTitleTip(_CDATE.': '.$time), 'text' => $title],
                    ['text' => (string)$num],
                    ['text' => (string)$hits],
                    ['content_html' => $acont],
                ],
            ];
            $conts .= $tpl->getHtmlFrag('block-content', ['id' => 'cl'.$i, 'is_hidden' => true, 'content' => $prs->filterContent($body, false, $conf['name'])]);
            $i++;
            $a++;
        }
        $cont .= $tpl->getHtmlPart('content-list', [
            'rows' => $rows,
            'table_open' => [
                'open' => true,
                'sortable' => true,
                'headers' => [
                    ['text' => _ID, 'is_num' => true],
                    ['text' => _CTITLE],
                    ['text' => _CVERSION],
                    ['text' => _CLOADS],
                    ['text' => _FUNCTIONS, 'no_sort' => true],
                ],
            ],
            'table_close' => [],
        ]).$conts;
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function download(): void {
    global $db, $user, $stop, $info;
    $uid = (int)($user[0] ?? 0);
    $result = $db->getSqlQuery('SELECT website FROM '.PREFIX_DB.'_clients WHERE status = 1 AND uid = :user_id', ['user_id' => $uid]);
    if (is_user() && $db->getSqlRowCount($result) > 0) {
        $id = getVar('get', 'id', 'num');
        [$pid, $url, $num] = $db->getSqlRow($db->getSqlQuery('SELECT id, url, num FROM '.PREFIX_DB.'_clients_down WHERE status != 0 AND id = :id', ['id' => $id]));
        $tpath = UPLOADS_DIR.'/clients/thumb/'.$pid.'_'.$uid;
        if (file_exists($tpath.'.zip')) $tpath .= '.zip';
        elseif (file_exists($tpath.'.gz')) $tpath .= '.gz';
        elseif (file_exists($tpath.'.bz2')) $tpath .= '.bz2';
        else $tpath = '';
        if (!$tpath) {
            $ipath = UPLOADS_DIR.'/clients/images';
            $path = UPLOADS_DIR.'/clients/'.$url;
            $code = base64_encode($uid.'-'.getip().'-'.getagent());

            # Шифруем файлы
            $input = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S' ,'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '='];
            $output = ['{', '©', '"', '§', '$', 'Ц', '&', '/', '(', '', '№', 'Ў', '<', '%', '‹', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'е', 'B', 'ш', 'D', 'E', 'я', 'G', 'д', 'I', 'J', 'K', 'L', '‡', 'Ш', 'O', 'Ж', 'Q', '·', 'В' ,'!', 'U', '†', '¶', 'X', 'Y', 'Z', 'ї'];
            $sourse = str_replace($input, $output, $code);
            if (file_exists($path.'/html/templates/admin/images/admin/admins.png')) hidden($path.'/html/templates/admin/images/admin/admins.png', $ipath.'/admins.png', $sourse.'IEND®B`‚');
            if (file_exists($path.'/html/templates/admin/images/admin/forum.png')) hidden($path.'/html/templates/admin/images/admin/forum.png', $ipath.'/forum.png', $code);
            if (file_exists($path.'/html/templates/admin/images/flags/de.svg')) hidden($path.'/html/templates/admin/images/flags/de.svg', $ipath.'/de.svg', $code);
            if (file_exists($path.'/html/templates/admin/images/admin/menu.png')) hidden($path.'/html/templates/admin/images/admin/menu.png', $ipath.'/menu.png', $sourse.'IEND®B`‚'.$code);

            if (file_exists($path.'/html/config/license.txt')) generator($path.'/html/config');
            if (file_exists($path.'/setup/config/license.txt')) generator($path.'/setup/config');
            if (file_exists($path.'/update/config/license.txt')) generator($path.'/update/config');
            if (!addCompress(UPLOADS_DIR.'/clients/thumb', $path, $pid.'_'.$uid, 'auto')) {
                $stop = _CLERROR2;
                clients();
            } else {
                $tpath = UPLOADS_DIR.'/clients/thumb/'.$pid.'_'.$uid;
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
    # Читаем и перезаписываем файл
    $content = file_get_contents($ipath);
    if ($content === false) return;
    $code = $content.$code;
    $fp = fopen($path, 'wb');
    if ($fp === false) return;
    fwrite($fp, $code);
    fclose($fp);
    # Меняем время файла
    $atime = filemtime($ipath);
    if ($atime !== false) {
        touch($path, $atime, $atime);
    }
}

function generator(string $path = ''): void {
    global $db, $user, $stop;
    $uid = (int)($user[0] ?? 0);
    $result = $db->getSqlQuery('SELECT website FROM '.PREFIX_DB.'_clients WHERE status = 1 AND uid = :user_id', ['user_id' => $uid]);
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
        $dir = ($path) ? (preg_match('#^(?:[A-Za-z]:/|//|/)#', str_replace('\\', '/', $path)) ? str_replace('\\', '/', $path) : BASE_DIR.'/'.ltrim(str_replace('\\', '/', $path), '/')) : UPLOADS_DIR.'/clients/thumb/';
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

switch ($op) {
    default: clients(); break;
    case 'download': download(); break;
    case 'generator': generator(); break;
}
