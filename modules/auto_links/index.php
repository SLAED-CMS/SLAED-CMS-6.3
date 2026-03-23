<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

const AUTO_LINKS_NAVI = ['htitle' => _A_LINKS, 'liste_href' => ''];


function autolink(): void {
    global $db, $afile, $user, $conf, $home, $op, $tpl;
    $unum = intval(getUserNews($conf['auto_links']['num']));
    if ($unum < 1) $unum = 1;
    $word = getVar('get', 'word', 'word');
    if ($op) {
        $field = 'op='.$op.'&';
        if ($op == 'new') {
            $order = 'added';
            $ntitle = _NEW;
        } else {
            $order = 'outs';
            $ntitle = _POP;
        }
    } else {
        $field = '';
        $order = 'hits';
        $ntitle = _A_LINKS;
    }
    $order = in_array($order, ['added', 'outs', 'hits'], true) ? $order : 'hits';
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $unum;
    $offset = intval($offset);
    $result = $db->getSqlQuery('SELECT id, title, intro, hits, outs, added FROM '.PREFIX_DB.'_auto_links WHERE hits != \'0\' ORDER BY '.$order.' DESC LIMIT '.$offset.', '.$unum);
    setHead(['title' => $ntitle]);
    $cont = '';
    if (!$home) $cont .= setModuleNavi(['title' => $ntitle, 'best_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'new']), 'btitle' => _NEW, 'pop_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'pop']), 'add_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'add'])] + AUTO_LINKS_NAVI);
    if ($db->getSqlRowCount($result) > 0) {
        while ([$id, $sitename, $intro, $hits, $outs, $time] = $db->getSqlRow($result)) {
            $thref = 'index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id;
            $date = format_time($time);
            $hits = $tpl->getHtmlFrag('hit-badge', ['title' => _HITS, 'text' => $hits, 'cls' => 'sl_hits']);
            $ask = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$sitename.'&quot;?');
            $cont .= $tpl->getHtmlFrag('basic', [
                'id' => $id,
                'title_href' => $thref,
                'title_attr' => $sitename,
                'title_text' => filterTextHighlight($sitename, $word),
                'title_new' => new_graphic($time),
                'category_href' => '',
                'category_attr' => '',
                'category_text' => '',
                'category_img' => '',
                'text' => filterTextHighlight(filterReplaceText(filterMarkdown($intro, $conf['name'], false), $conf['name']), $word),
                'read_href' => $thref,
                'read_text' => _DOWNLLINK,
                'post_text' => '',
                'post_label' => '',
                'date_text' => $date,
                'date_iso' => date('c', strtotime($time)),
                'date_label' => _CHNGSTORY,
                'reads_text' => $outs,
                'reads_label' => _OUTS,
                'hits' => $hits,
                'comm_href' => '',
                'comm_text' => '',
                'comm_label' => _COMMENTS,
                'rating' => '',
                'favorites' => '',
                'voting' => '',
                'editor' => _EDITOR,
                'edit_href' => $afile.'.php?op=auto_links_add&amp;id='.$id,
                'edit_text' => _FULLEDIT,
                'delete_href' => $afile.'.php?op=auto_links_delete&amp;id='.$id.'&amp;refer=1',
                'delete_text' => _ONDELETE,
                'delete_ask' => $ask,
                'back_title' => '',
                'back_text' => '',
                'is_moder' => is_moder($conf['name']),
            ]);
        }
        $cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'id', '_auto_links', '', 'hits != \'0\'', $conf['auto_links']['nump']);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $conf;
    $id = getVar('get', 'id', 'num');
    if ($id) {
        [$url] = $db->getSqlRow($db->getSqlQuery('SELECT url FROM '.PREFIX_DB.'_auto_links WHERE id = :id', ['id' => $id]));
        if (!$url) setRedirect('index.php?name='.$conf['name']);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_auto_links SET outs = outs+1 WHERE id = :id', ['id' => $id]);
        update_points(4);
        setRedirect($url);
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function add(): void {
    global $stop, $conf, $tpl;
    if (is_user()) {
        $userinfo = getUserInfo();
        $email = getVar('post', 'mail', 'var', $userinfo['email']);
        $site = getVar('post', 'site', 'url', $userinfo['website']);
    } else {
        $email = getVar('post', 'mail', 'var', '');
        $site = getVar('post', 'site', 'url', 'http://');
    }
    $name = getVar('post', 'name', 'title');
    $desc = getVar('post', 'desc', 'text');
    
    setHead(['title' => _ADD]);
    $cont = setModuleNavi(['title' => _ADD, 'best_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'new']), 'btitle' => _NEW, 'pop_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'pop']), 'add_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'add'])] + AUTO_LINKS_NAVI);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
    if ($desc) $cont .= preview($name, $desc, '', '', $conf['name']);
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _A_LINKS_I]);
    $cont .= $tpl->getHtmlFrag('form-add', [
        'name' => $conf['name'],
        'token' => htmlspecialchars(getSiteToken('auto_links'), ENT_QUOTES, 'UTF-8'),
        'style' => $conf['style'],
        'lbl_email' => _A_LINKS_E,
        'lbl_title' => _SITENAME,
        'lbl_text' => _A_LINKS_TEXT,
        'lbl_site' => _A_LINKS_L,
        'emailval' => $email,
        'titleval' => $name,
        'hometext' => textarea('1', 'desc', $desc, $conf['name'], '5', _A_LINKS_TEXT, '1'),
        'site_attr' => 'site',
        'siteval' => $site,
        'captcha' => getCaptcha(1),
        'submit' => ad_save('', '', 'send'),
    ]);
    echo $cont;
    setFoot();
}

function send(): void {
    global $db, $user, $stop, $conf, $tpl;
    $name = getVar('post', 'name', 'title');
    $desc = getVar('post', 'desc', 'text');
    $site = getVar('post', 'site', 'url');
    $email = getVar('post', 'mail', 'var');
    $stop = [];
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'auto_links')) $stop[] = _ERROR;
    if (!$name) $stop[] = _CERROR10;
    if (!$desc) $stop[] = _CERROR11;
    if (!$site) $stop[] = _CERROR4;
    checkemail($email);
    if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT url FROM '.PREFIX_DB.'_auto_links WHERE url = :url', ['url' => $site])) > 0) $stop[] = _LINKEXIST;
    if (!$stop && getVar('post', 'posttype', 'text') == 'save') {
        setHead(['title' => _ADD]);
        $cont = setModuleNavi(['title' => _ADD, 'best_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'new']), 'btitle' => _NEW, 'pop_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'pop']), 'add_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'add'])] + AUTO_LINKS_NAVI);
        $db->getSqlQuery(
            'INSERT INTO '.PREFIX_DB.'_auto_links (title, intro, url, email, hits, outs, added) VALUES (:title, :intro, :url, :email, 0, 0, NOW())',
            ['title' => $name, 'intro' => $desc, 'url' => $site, 'email' => $email]
        );
        $puname = (is_user()) ? $user[1] : '';
        addAdminMail($conf['auto_links']['addmail'], $conf['name'], $puname, _A_LINKS);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _A_LINKS_OK]);
        $code = $tpl->getHtmlFrag('auto-links-embed-link', ['href' => $conf['homeurl'], 'title' => $conf['slogan'], 'label' => $conf['sitename']]);
        $rows = $tpl->getHtmlFrag('auto-links-code-row', ['label' => _A_LINKS_M, 'style' => $conf['style'], 'code' => $code]);
        if ($conf['auto_links']['img']) {
            $banner = img_find('banners/'.$conf['auto_links']['img']);
            if ($banner && file_exists($banner)) {
                [$imgwidth, $imgheight] = getimagesize($banner);
                $code = $tpl->getHtmlFrag('auto-links-embed-image', ['href' => $conf['homeurl'], 'title' => $conf['sitename'].' - '.$conf['slogan'], 'src' => $conf['homeurl'].'/'.$banner, 'alt' => $conf['sitename'].' - '.$conf['slogan'], 'width' => $imgwidth, 'height' => $imgheight]);
                $rows .= $tpl->getHtmlFrag('auto-links-code-row', ['label' => _A_LINKS_IMG, 'style' => $conf['style'], 'code' => $code]);
            }
        }
        $cont .= $tpl->getHtmlFrag('auto-links-code-table', ['rows' => $rows]);
        echo $cont;
        setFoot();
    } else {
        add();
    }
}

switch ($op) {
    default: autolink(); break;
    case 'view': view(); break;
    case 'add': add(); break;
    case 'send': send(); break;
}
