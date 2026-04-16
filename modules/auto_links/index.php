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
    global $db, $afile, $conf, $home, $op, $tpl, $prs;
    $unum = (int)getUserNews($conf['auto_links']['num']);
    if ($unum < 1) $unum = 1;
    $word = getVar('get', 'word', 'word');
    if ($op) {
        if ($op == 'new') {
            $order  = 'added';
            $ntitle = _NEW;
        } else {
            $order  = 'outs';
            $ntitle = _POP;
        }
    } else {
        $order  = 'hits';
        $ntitle = _A_LINKS;
    }
    $order = in_array($order, ['added', 'outs', 'hits'], true) ? $order : 'hits';
    $num    = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $unum);
    $onum   = "hits != '0'";
    $sql    = 'SELECT id, title, intro, hits, outs, added FROM '.PREFIX_DB.'_auto_links WHERE '.$onum.' ORDER BY '.$order.' DESC LIMIT '.$offset.', '.$unum;
    $result = $db->getSqlQuery($sql);
    setHead(['title' => $ntitle]);
    $cont = '';
    if (!$home) $cont .= setModuleNavi(['title' => $ntitle, 'best_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'new']), 'btitle' => _NEW, 'pop_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'pop']), 'add_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'add'])] + AUTO_LINKS_NAVI);
    if ($db->getSqlRowCount($result) > 0) {
        $ismoder = is_moder($conf['name']);
        $token   = getSiteToken();
        $cont .= $tpl->getHtmlFrag('grid', ['open' => true]);
        while ([$id, $sitename, $intro, $hits, $outs, $time] = $db->getSqlRow($result)) {
            $thref  = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id]);
            $date   = format_time($time);
            $iso    = date('c', strtotime($time));
            $hbadge = $tpl->getHtmlFrag('hit-badge', ['title' => _HITS, 'text' => $hits, 'cls' => 'sl_hits']);
            $ask    = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$sitename.'&quot;?');
            $cont .= $tpl->getHtmlFrag('card', [
                'id'            => $id,
                'width'         => 100,
                'title_href'    => $thref,
                'title_attr'    => $sitename,
                'title_text'    => filterTextHighlight($sitename, $word),
                'title_new'     => getTplNewGraphic($time),
                'category_href' => '',
                'category_attr' => '',
                'category_text' => '',
                'category_img'  => '',
                'text'          => filterTextHighlight($prs->filterContent($intro, false, $conf['name']), $word),
                'read_href'     => $thref,
                'read_text'     => _DOWNLLINK,
                'post_text'     => '',
                'post_label'    => '',
                'date_text'     => $date,
                'date_iso'      => $iso,
                'date_label'    => _CHNGSTORY,
                'reads_text'    => $outs,
                'reads_label'   => _OUTS,
                'hits'          => $hbadge,
                'comm_href'     => '',
                'comm_text'     => '',
                'comm_label'    => _COMMENTS,
                'rating'        => '',
                'favorites'     => '',
                'voting'        => '',
                'editor'        => _EDITOR,
                'edit_href'     => $afile.'.php?name=auto_links&amp;op=auto_links_add&amp;id='.$id,
                'edit_text'     => _FULLEDIT,
                'delete_href'   => $afile.'.php?name=auto_links&amp;op=auto_links_delete&amp;id='.$id.'&amp;refer=1&amp;token='.$token,
                'delete_text'   => _ONDELETE,
                'delete_ask'    => $ask,
                'is_moder'      => $ismoder,
            ]);
        }
        $cont .= $tpl->getHtmlFrag('grid', []);
        $url_extra = [];
        if ($op) $url_extra['op'] = $op;
        $cont .= getTplPager([
            'limit'     => $unum,
            'maxpg'     => $conf['auto_links']['nump'],
            'table'     => '_auto_links',
            'field'     => 'id',
            'mod'       => $conf['name'],
            'where'     => $onum,
            'url_extra' => $url_extra,
            'prefix'    => 'new/',
        ]);
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
        $site  = getVar('post', 'site', 'url', $userinfo['website']);
    } else {
        $email = getVar('post', 'mail', 'var', '');
        $site  = getVar('post', 'site', 'url', 'http://');
    }
    $name = getVar('post', 'name', 'title');
    $desc = getVar('post', 'desc', 'raw');
    setHead(['title' => _ADD]);
    $cont = setModuleNavi(['title' => _ADD, 'best_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'new']), 'btitle' => _NEW, 'pop_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'pop']), 'add_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'add'])] + AUTO_LINKS_NAVI);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => getStopText((array)$stop)]);
    if ($desc) $cont .= getTplPreviewContent(['title' => $name, 'texta' => $desc, 'textb' => '', 'mod' => $conf['name']]);
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _A_LINKS_I]);
    $cont .= $tpl->getHtmlFrag('form-add', [
        'name'      => $conf['name'],
        'token'     => htmlspecialchars(getSiteToken('auto_links'), ENT_QUOTES, 'UTF-8'),
        'lbl_email' => _A_LINKS_E,
        'lbl_title' => _SITENAME,
        'lbl_text'  => _A_LINKS_TEXT,
        'lbl_site'  => _A_LINKS_L,
        'emailval'  => $email,
        'titleval'  => $name,
        'hometext'  => getTplTextarea(['id' => '1', 'name' => 'desc', 'value' => $desc, 'mod' => $conf['name'], 'rows' => '5', 'placeholder' => _A_LINKS_TEXT, 'required' => '1']),
        'site_attr' => 'site',
        'siteval'   => $site,
        'captcha'   => getCaptcha(1),
        'submit'    => getTplFormSubmit(['op' => 'send', 'select' => true]),
    ]);
    echo $cont;
    setFoot();
}

function send(): void {
    global $db, $user, $stop, $conf, $tpl;
    $name  = getVar('post', 'name', 'title');
    $desc  = getVar('post', 'desc', 'text');
    $site  = getVar('post', 'site', 'url');
    $email = getVar('post', 'mail', 'var');
    $stop = [];
    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'auto_links')) $stop[] = _ERROR;
    if (!$name) $stop[] = _CERROR10;
    if (!$desc) $stop[] = _CERROR11;
    if (!$site) $stop[] = _CERROR4;
    checkemail($email);
    if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT url FROM '.PREFIX_DB.'_auto_links WHERE url = :url', ['url' => $site])) > 0) $stop[] = _LINKEXIST;
    if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
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
        $rows = $tpl->getHtmlFrag('auto-links-code-row', ['label' => _A_LINKS_M, 'code' => $code]);
        if ($conf['auto_links']['img']) {
            $banner = img_find('banners/'.$conf['auto_links']['img']);
            if ($banner && file_exists($banner)) {
                [$imgwidth, $imgheight] = getimagesize($banner);
                $code  = $tpl->getHtmlFrag('auto-links-embed-image', ['href' => $conf['homeurl'], 'title' => $conf['sitename'].' - '.$conf['slogan'], 'src' => $conf['homeurl'].'/'.$banner, 'alt' => $conf['sitename'].' - '.$conf['slogan'], 'width' => $imgwidth, 'height' => $imgheight]);
                $rows .= $tpl->getHtmlFrag('auto-links-code-row', ['label' => _A_LINKS_IMG, 'code' => $code]);
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
