<?php
# Author: Eduard Laas
# Copyright � 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('auto_links')) die('Illegal file access');


function auto_links(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi([
        'ops'  => ['name=auto_links', 'name=auto_links&amp;op=add', 'name=auto_links&amp;op=hitreset', 'name=auto_links&amp;op=zerodel', 'name=auto_links&amp;op=config', 'name=auto_links&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NULLHITS, _NOINDEL, _PREFERENCES, _INFO],
    ]);
    if (!$conf['referers']['refer']) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _A_NOTE]);
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $conf['auto_links']['anum'];
    $result = $db->getSqlQuery('SELECT id, title, url, hits, outs, added FROM '.PREFIX_DB.'_auto_links ORDER BY hits ASC LIMIT '.$offset.', '.$conf['auto_links']['anum']);
    if ($db->getSqlRowCount($result) > 0) {
        $head = $tpl->getHtmlFrag('admin-auto-links-list-head', [
            'functions_label' => _FUNCTIONS,
            'hits_label' => _HITS,
            'id_label' => _ID,
            'outs_label' => _OUTS,
            'sitename_label' => _SITENAME,
            'siteurl_label' => _SITEURL,
        ]);
        $rows = '';
        while ([$id, $name, $url, $hits, $outs, $added] = $db->getSqlRow($result)) {
            $acts = adminMenuItems([
                $hits ? adminLinkAction($afile.'.php?name=auto_links&amp;op=stats&amp;id='.$id, _MVIEW, _MVIEW) : '',
                adminLinkAction($afile.'.php?name=auto_links&amp;op=add&amp;id='.$id, _FULLEDIT, _FULLEDIT),
                adminDeleteAction($afile.'.php?name=auto_links&amp;op=delete&amp;id='.$id.'&amp;refer=1', _DELETE.' "'.$name.'"?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-auto-links-list-row', [
                'actions_html' => $acts,
                'hits_text' => (string)$hits,
                'id_text' => (string)$id,
                'outs_text' => (string)$outs,
                'sitename_html' => title_tip(_REG.': '.format_time($added, _TIMESTRING)).'<span title="'.$name.'" class="sl_note">'.cutstr($name, 40).'</span>',
                'siteurl_text' => domain($url),
            ]));
        }
        $cont .= getAdminTable($head, $rows);
        $cont .= setArticleNumbers('pagenum', '', $conf['auto_links']['anum'], 'name=auto_links&amp;', 'id', '_auto_links', '', '', $conf['auto_links']['anump']);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function stats(): void {
    global $db, $afile, $conf, $tpl;
    $id = getVar('req', 'id', 'num');
    $sort = getVar('req', 'sort', 'num');
    $order = getVar('req', 'order', 'num');
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $conf['auto_links']['anum'];
    $tnum = ($offset) ? $conf['auto_links']['anum'] + $offset : $conf['auto_links']['anum'];
    if ($sort == 1) { $count = 'referer'; $ordby = 'hits'; }
    elseif ($sort == 2) { $count = 'referer'; $ordby = 'referer'; }
    elseif ($sort == 3) { $count = 'url'; $ordby = 'hits'; }
    elseif ($sort == 4) { $count = 'url'; $ordby = 'url'; }
    elseif ($sort == 5) { $count = 'name'; $ordby = 'hits'; }
    elseif ($sort == 6) { $count = 'name'; $ordby = 'name'; }
    elseif ($sort == 7) { $count = 'ip'; $ordby = 'hits'; }
    elseif ($sort == 8) { $count = 'ip'; $ordby = 'ip'; }
    elseif ($sort == 9) { $count = 'time'; $ordby = 'hits'; }
    else { $count = 'time'; $ordby = 'time'; }
    $ordsc = ($order == 1) ? 'ASC' : 'DESC';
    $result = $db->getSqlQuery('SELECT Count('.$count.') AS hits, uid, name, ip, referer, url, time FROM '.PREFIX_DB.'_referer WHERE lid = :lid GROUP BY '.$count.' ORDER BY '.$ordby.' '.$ordsc, ['lid' => $id]);
    setHead();
    $_id = getVar('req', 'id', 'num');
    $box = '';
    if ($_id) {
        $opts = '';
        foreach ([_REF_ID, _REF_URL, _IN_ID, _IN_URL, _NAME_ID, _NAME_REF, _IP_ID, _IP_REF, _TIME_ID, _TIME_REF] as $_k => $_v) {
            $_sort = $_k + 1;
            $opts .= getAdminOption((string)$_sort, $_v, getVar('post', 'sort', 'num') == $_sort);
        }
        $sortSelect = getAdminSelect('sort', $opts);
        $opts = '';
        foreach ([_ASC, _DESC] as $_k => $_v) {
            $_sort = $_k + 1;
            $opts .= getAdminOption((string)$_sort, $_v, getVar('post', 'order', 'num') == $_sort);
        }
        $orderSelect = getAdminSelect('order', $opts);
        $box = getAdminSearchBox($tpl->getHtmlFrag('admin-auto-links-stats-search', [
            'id_value' => (string)$_id,
            'ok_label' => _OK,
            'order_html' => $orderSelect,
            'route' => $afile,
            'sort_html' => $sortSelect,
            'sort_label' => _SORTE.':',
        ]));
    }
    $cont = setAdminNavi([
        'ops'  => ['name=auto_links', 'name=auto_links&amp;op=add', 'name=auto_links&amp;op=hitreset', 'name=auto_links&amp;op=zerodel', 'name=auto_links&amp;op=config', 'name=auto_links&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NULLHITS, _NOINDEL, _PREFERENCES, _INFO],
        'sub'  => $box,
    ]);
    if (!$conf['referers']['refer']) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _A_NOTE]);
    $list = [];
    $a = 0;
    while ([$hits, $uid, $name, $ip, $referer, $url, $date] = $db->getSqlRow($result)) {
        $list[] = [$hits, $uid, $name, $ip, $referer, $url, $date];
        $a++;
    }
    if (isArray($list)) {
        $head = $tpl->getHtmlFrag('admin-auto-links-stats-head', [
            'id_label' => _ID,
            'in_url_label' => _IN_URL,
            'ip_label' => _IP,
            'nickname_label' => _NICKNAME,
            'ref_url_label' => _REF_URL,
        ]);
        $rows = '';
        for ($i = $offset; $i < $tnum; $i++) {
            if (isset($list[$i])) {
                $name = ($list[$i][1]) ? user_info($list[$i][2]) : $list[$i][2];
                $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-auto-links-stats-row', [
                    'hits_text' => (string)$list[$i][0],
                    'in_url_text' => domain($list[$i][5], 15),
                    'ip_html' => user_geo_ip($list[$i][3], 4),
                    'nickname_html' => title_tip(_DATE.': '.date(_TIMESTRING, $list[$i][6])).$name,
                    'ref_url_text' => domain($list[$i][4], 35),
                ]));
            }
        }
        $cont .= getAdminTable($head, $rows);
        $pages = ceil($a / $conf['auto_links']['anum']);
        $cont .= setPageNumbers('pagenum', '', $a, $pages, $conf['auto_links']['anum'], 'name=auto_links&amp;op=stats&amp;id='.$id.'&amp;sort='.$sort.'&amp;order='.$order.'&amp;', $conf['auto_links']['anump']);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop, $tpl;
    $stop = $stop ?? [];
    $id = getVar('req', 'id', 'num');
    if ($id) {
        $result = $db->getSqlQuery('SELECT id, title, intro, url, email, hits, outs FROM '.PREFIX_DB.'_auto_links WHERE id = :id', ['id' => $id]);
        [$id, $name, $desc, $site, $email, $hits, $outs] = $db->getSqlRow($result);
    } else {
        $id = getVar('post', 'id', 'num');
        $name = getVar('post', 'name', 'title', '');
        $email = getVar('post', 'mail', 'var', '');
        $desc = getVar('post', 'desc', 'text', '');
        $site = getVar('post', 'site', 'url', 'https://');
        $hits = getVar('post', 'hits', 'num', 0);
        $outs = getVar('post', 'outs', 'num', 0);
    }
    setHead();
    $cont = setAdminNavi([
        'ops'  => ['name=auto_links', 'name=auto_links&amp;op=add', 'name=auto_links&amp;op=hitreset', 'name=auto_links&amp;op=zerodel', 'name=auto_links&amp;op=config', 'name=auto_links&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NULLHITS, _NOINDEL, _PREFERENCES, _INFO],
        'tab'  => 1,
    ]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => implode('<br>', $stop)]);
    if ($desc) $cont .= preview($name, $desc, '', '', 'auto_links');
    $rows = $tpl->getHtmlFrag('admin-auto-links-add-rows', [
        'desc_html' => textarea('1', 'desc', $desc, 'auto_links', '5', _A_LINKS_TEXT, '1'),
        'desc_label' => _A_LINKS_TEXT.':',
        'email_label' => _A_LINKS_E.':',
        'email_value' => $email,
        'hits_label' => _HITS.':',
        'hits_value' => (string)$hits,
        'name_label' => _SITENAME.':',
        'name_value' => $name,
        'outs_label' => _OUTS.':',
        'outs_value' => (string)$outs,
        'save_html' => ad_save('id', $id, 'save'),
        'site_label' => _A_LINKS_L.':',
        'site_value' => $site,
    ]);
    $cont .= getAdminForm($afile.'.php?name=auto_links', $rows);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $id = getVar('post', 'id', 'num');
    $name = getVar('post', 'name', 'title', '');
    $desc = getVar('post', 'desc', 'text', '');
    $site = getVar('post', 'site', 'url', 'https://');
    $email = getVar('post', 'mail', 'var', '');
    $hits = getVar('post', 'hits', 'num', 0);
    $outs = getVar('post', 'outs', 'num', 0);
    $stop = [];
    if (!$name) $stop[] = _CERROR10;
    if (!$desc) $stop[] = _CERROR11;
    if (!$site) $stop[] = _CERROR4;
    $posttype = getVar('post', 'posttype', 'var', '');
    if (!$stop && $posttype === 'save') {
        if ($id) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_auto_links SET title = :name, intro = :desc, url = :url, email = :email, hits = :hits, outs = :outs WHERE id = :id', ['name' => $name, 'desc' => $desc, 'url' => $site, 'email' => $email, 'hits' => $hits, 'outs' => $outs, 'id' => $id]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_auto_links (title, intro, url, email, hits, outs, added) VALUES (:name, :desc, :url, :email, :hits, :outs, now())', ['name' => $name, 'desc' => $desc, 'url' => $site, 'email' => $email, 'hits' => $hits, 'outs' => $outs]);
        }
        setRedirect($afile.'.php?name=auto_links');
    } elseif ($posttype === 'delete') {
        delete($id);
    } else {
        add();
    }
}

function delete(int $id = 0): void {
    global $db, $afile;
    if (!$id) $id = getVar('req', 'id', 'num');
    if ($id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_auto_links WHERE id = :id', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_referer WHERE lid = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=auto_links');
}

function hitreset(): void {
    global $db, $afile;
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_auto_links SET hits = 0, outs = 0');
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_referer WHERE lid != 0');
    setRedirect($afile.'.php?name=auto_links');
}

function zerodel(): void {
    global $db, $afile;
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_auto_links WHERE hits = 0');
    setRedirect($afile.'.php?name=auto_links');
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi([
        'ops'  => ['name=auto_links', 'name=auto_links&amp;op=add', 'name=auto_links&amp;op=hitreset', 'name=auto_links&amp;op=zerodel', 'name=auto_links&amp;op=config', 'name=auto_links&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NULLHITS, _NOINDEL, _PREFERENCES, _INFO],
        'tab'  => 4,
    ]);
    if (!$conf['referers']['refer']) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _A_NOTE]);
    $cont .= checkPerms(CONFIG_DIR.'/auto_links.php');
    $path = 'templates/'.$conf['theme'].'/images/banners/';
    $opts = '';
    foreach (scandir($path) as $entry) {
        if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg)$/is', $entry)) {
            $opts .= getAdminOption($path.$entry, $entry, $conf['auto_links']['img'] == $entry);
        }
    }
    $hide = getAdminHidden('op', 'configsave');
    $rows = $tpl->getHtmlFrag('admin-auto-links-config-rows', [
        'addmail_html' => radio_form($conf['auto_links']['addmail'], 'addmail'),
        'addmail_label' => _ADDAMAIL,
        'anum_label' => _C_34.':',
        'anum_value' => (string)$conf['auto_links']['anum'],
        'anump_label' => _C_36.':',
        'anump_value' => (string)$conf['auto_links']['anump'],
        'img_html' => getAdminSelect('img', $opts, 'sl_conf', 'id="img_replace"'),
        'img_label' => _A_1.':',
        'limit_label' => _A_5.':',
        'limit_value' => (string)$conf['auto_links']['limit'],
        'num_label' => _C_33.':',
        'num_value' => (string)$conf['auto_links']['num'],
        'nump_label' => _C_35.':',
        'nump_value' => (string)$conf['auto_links']['nump'],
        'preview_html' => '<img src="'.$path.$conf['auto_links']['img'].'" id="picture" alt="'._SITELOGO.'">',
        'preview_label' => _A_2.':',
        'save_label' => _SAVECHANGES,
        'strip_label' => _A_4.':',
        'strip_value' => (string)$conf['auto_links']['strip'],
    ]);
    $cont .= getAdminForm($afile.'.php?name=auto_links', $rows, $hide, 'sl_table_conf', 'post', 'post');
    echo getAdminBox($cont);
    setFoot();
}

function configsave(): void {
    global $afile, $conf;
    $cont = [
        'img' => str_replace('templates/'.$conf['theme'].'/images/banners/', '', getVar('post', 'img', 'var', '')),
        'num' => getVar('post', 'num', 'num', 10),
        'anum' => getVar('post', 'anum', 'num', 10),
        'nump' => getVar('post', 'nump', 'num', 10),
        'anump' => getVar('post', 'anump', 'num', 10),
        'strip' => getVar('post', 'strip', 'num', 100),
        'limit' => getVar('post', 'limit', 'num', 1),
        'addmail' => getVar('post', 'addmail', 'num', 0),
    ];
    setConfigFile('auto_links.php', $cont);
    setRedirect($afile.'.php?name=auto_links&op=config');
}

function info(): void {
    setHead();
    $cont = setAdminNavi([
        'ops'  => ['name=auto_links', 'name=auto_links&amp;op=add', 'name=auto_links&amp;op=hitreset', 'name=auto_links&amp;op=zerodel', 'name=auto_links&amp;op=config', 'name=auto_links&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NULLHITS, _NOINDEL, _PREFERENCES, _INFO],
        'tab'  => 5,
    ]);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
}

switch ($op) {
    default: auto_links(); break;
    case 'stats': stats(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'hitreset': hitreset(); break;
    case 'zerodel': zerodel(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}

