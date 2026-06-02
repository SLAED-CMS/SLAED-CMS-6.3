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
    if (!$home) $cont .= getModuleNavi(['title' => $ntitle, 'best_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'new']), 'btitle' => _NEW, 'pop_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'pop']), 'add_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'add'])] + AUTO_LINKS_NAVI);
    if ($db->getSqlRowCount($result) > 0) {
        $ismoder = is_moder($conf['name']);
        $token   = getSiteToken();
        $cont .= $tpl->getHtmlFrag('grid', ['open' => true]);
        while ([$id, $sitename, $intro, $hits, $outs, $time] = $db->getSqlRow($result)) {
            $thref  = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id]);
            $date   = format_time($time);
            $iso    = date('c', strtotime($time));
            $hbadge = $tpl->getHtmlFrag('inline-badge', ['title_text' => _HITS, 'label' => $hits, 'is_hits' => true]);
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
    $cont = getModuleNavi(['title' => _ADD, 'best_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'new']), 'btitle' => _NEW, 'pop_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'pop']), 'add_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'add'])] + AUTO_LINKS_NAVI);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
    if ($desc) $cont .= getTplPreviewContent(['title' => $name, 'texta' => $desc, 'textb' => '', 'mod' => $conf['name']]);
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _A_LINKS_I]);
    $fields = $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('auto_links')]);
    $fields .= $tpl->getHtmlFrag('form-field-row', [
        'label' => _A_LINKS_E,
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'mail', 'value_attr' => $email, 'maxlength_num' => 100, 'placeholder_text' => _A_LINKS_E, 'is_required' => true]),
    ]);
    $fields .= $tpl->getHtmlFrag('form-field-row', [
        'label' => _SITENAME,
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'name', 'value_attr' => $name, 'maxlength_num' => 100, 'placeholder_text' => _SITENAME, 'is_required' => true]),
    ]);
    $fields .= $tpl->getHtmlFrag('form-field-row', ['label' => _A_LINKS_TEXT, 'field_html' => getTplTextarea(['id' => '1', 'name' => 'desc', 'value' => $desc, 'mod' => $conf['name'], 'rows' => '5', 'placeholder' => _A_LINKS_TEXT, 'required' => '1'])]);
    $fields .= $tpl->getHtmlFrag('form-field-row', [
        'label' => _A_LINKS_L,
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'site', 'value_attr' => $site, 'maxlength_num' => 100, 'placeholder_text' => _A_LINKS_L]),
    ]);
    $cont .= $tpl->getHtmlPart('form-add', [
        'name'      => $conf['name'],
        'fields'    => $fields,
        'captcha'   => getCaptcha('comment'),
        'submit'    => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'send', 'extra' => '', 'name' => '', 'val' => '', 'select' => true, 'show_preview' => true, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _OK]),
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
    if (checkCaptcha('comment')) $stop[] = _SECCODEINCOR;
    if ($db->getSqlRowCount($db->getSqlQuery('SELECT url FROM '.PREFIX_DB.'_auto_links WHERE url = :url', ['url' => $site])) > 0) $stop[] = _LINKEXIST;
    if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
        setHead(['title' => _ADD]);
        $cont = getModuleNavi(['title' => _ADD, 'best_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'new']), 'btitle' => _NEW, 'pop_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'pop']), 'add_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'add'])] + AUTO_LINKS_NAVI);
        $db->getSqlQuery(
            'INSERT INTO '.PREFIX_DB.'_auto_links (title, intro, url, email, hits, outs, added) VALUES (:title, :intro, :url, :email, 0, 0, NOW())',
            ['title' => $name, 'intro' => $desc, 'url' => $site, 'email' => $email]
        );
        $puname = (is_user()) ? $user[1] : '';
        addAdminMail($conf['auto_links']['addmail'], $conf['name'], $puname, _A_LINKS);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _A_LINKS_OK]);
        $embedHome = htmlspecialchars($conf['homeurl'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $embedSlogan = htmlspecialchars($conf['slogan'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $embedSite = htmlspecialchars($conf['sitename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $code = '&lt;a href=&quot;'.$embedHome.'&quot; target=&quot;_blank&quot; title=&quot;'.$embedSlogan.'&quot;&gt;'.$embedSite.'&lt;/a&gt;';
        $rows = [[
            'cells' => [
            ['text' => _A_LINKS_M],
            ['content_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'description', 'rows_num' => 5, 'value_text' => $code])],
            ],
        ]];
        if ($conf['auto_links']['img']) {
            $banner = img_find('banners/'.$conf['auto_links']['img']);
            if ($banner && file_exists($banner)) {
                [$imgwidth, $imgheight] = getimagesize($banner);
                $embedTitle = htmlspecialchars($conf['sitename'].' - '.$conf['slogan'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $embedSrc = htmlspecialchars($conf['homeurl'].'/'.$banner, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $code  = '&lt;a href=&quot;'.$embedHome.'&quot; target=&quot;_blank&quot; title=&quot;'.$embedTitle.'&quot;&gt;&lt;img src=&quot;'.$embedSrc.'&quot; alt=&quot;'.$embedTitle.'&quot; class=&quot;sl-embed-img&quot; width=&quot;'.$imgwidth.'&quot; height=&quot;'.$imgheight.'&quot;&gt;&lt;/a&gt;';
                $rows[] = [
                    'cells' => [
                    ['text' => _A_LINKS_IMG],
                    ['content_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'description', 'rows_num' => 5, 'value_text' => $code])],
                    ],
                ];
            }
        }
        $cont .= $tpl->getHtmlPart('content-list', [
            'rows' => $rows,
            'table_open' => ['open' => true, 'is_form' => true],
            'table_close' => [],
        ]);
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
