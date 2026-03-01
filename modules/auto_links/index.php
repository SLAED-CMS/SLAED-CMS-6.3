<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function navigate(string $title): string {
    global $conf;
    $home = '<a href="'.getSeoUrl(['name' => $conf['name']]).'" title="'._A_LINKS.'" class="sl_but_navi">'._HOME.'</a>';
    $new = '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'new']).'" title="'._NEW.'" class="sl_but_navi">'._NEW.'</a>';
    $pop = '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'pop']).'" title="'._POP.'" class="sl_but_navi">'._POP.'</a>';
    $add = '<a href="'.getSeoUrl(['name' => $conf['name'], 'op' => 'add']).'" title="'._ADD.'" class="sl_but_navi">'._ADD.'</a>';
    return setTemplateBasic('navi', ['{%title%}' => $title, '{%name%}' => $conf['name'], '{%home%}' => $home, '{%best%}' => $new, '{%pop%}' => $pop, '{%liste%}' => '', '{%add%}' => $add, '{%catshow%}' => '']);
}

function autolink(): void {
    global $db, $afile, $user, $conf, $home, $op;
    $unum = intval(user_news($user[3] ?? 0, $conf['auto_links']['num']));
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
    $result = $db->sql_query('SELECT id, sitename, description, hits, outs, added FROM '.PREFIX_DB.'_auto_links WHERE hits != \'0\' ORDER BY '.$order.' DESC LIMIT '.$offset.', '.$unum);
    setHead(['title' => $ntitle]);
    $cont = '';
    if (!$home) $cont .= navigate($ntitle);
    if ($db->sql_numrows($result) > 0) {
        while ([$id, $sitename, $description, $hits, $outs, $time] = $db->sql_fetchrow($result)) {
            $title = search_color($sitename, $word).' '.new_graphic($time);
            $read = '<a href="index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$id.'" target="_blank" title="'.$sitename.'" class="sl_but_read">'._DOWNLLINK.'</a>';
            $date = '<time datetime="'.date('c', strtotime($time)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($time).'</time>';
            $reads = '<span title="'._OUTS.'" class="sl_outs">'.$outs.'</span>';
            $hits = '<span title="'._HITS.'" class="sl_hits">'.$hits.'</span>';
            $admin = (is_moder($conf['name'])) ? add_menu('<a href="'.$afile.'.php?op=auto_links_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=auto_links_delete&amp;id='.$id.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$sitename.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>') : '';
            $cont .= setTemplateBasic('basic', ['{%cid%}' => '', '{%cimg%}' => '', '{%ctitle%}' => '', '{%id%}' => $id, '{%title%}' => $title, '{%text%}' => search_color(bb_decode($description, $conf['name']), $word), '{%read%}' => $read, '{%post%}' => '', '{%date%}' => $date, '{%reads%}' => $reads, '{%hits%}' => $hits, '{%comm%}' => '', '{%rating%}' => '', '{%admin%}' => $admin, '{%favorites%}' => '', '{%goback%}' => '', '{%voting%}' => '']);
        }
        $cont .= setArticleNumbers('pagenum', $conf['name'], $unum, $field, 'id', '_auto_links', '', 'hits != \'0\'', $conf['auto_links']['nump']);
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $conf;
    $id = getVar('get', 'id', 'num');
    if ($id) {
        [$link] = $db->sql_fetchrow($db->sql_query('SELECT link FROM '.PREFIX_DB.'_auto_links WHERE id = :id', ['id' => $id]));
        if (!$link) {
            setRedirect('index.php?name='.$conf['name']);
        }
        $db->sql_query('UPDATE '.PREFIX_DB.'_auto_links SET outs = outs+1 WHERE id = :id', ['id' => $id]);
        update_points(4);
        setRedirect($link);
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function add(): void {
    global $stop, $conf;
    if (is_user()) {
        $userinfo = getusrinfo();
        $mail = getVar('post', 'mail', 'var');
        $mail = ($mail) ? $mail : $userinfo['user_email'];
        $site = getVar('post', 'site', 'url');
        $site = ($site) ? $site : $userinfo['user_website'];
    } else {
        $mail = getVar('post', 'mail', 'var');
        $mail = ($mail) ? $mail : '';
        $site = getVar('post', 'site', 'url', 'http://');
        $site = ($site) ? $site : 'http://';
    }
    $name = getVar('post', 'name', 'title');
    $desc = getVar('post', 'desc', 'text');
    
    setHead(['title' => _ADD]);
    $cont = navigate(_ADD);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
    if ($desc) $cont .= preview($name, $desc, '', '', $conf['name']);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _A_LINKS_I]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="index.php?name='.$conf['name'].'" method="post"><table class="sl_table_form">'
    .'<tr><td>'._SITENAME.':</td><td><input type="text" name="name" value="'.$name.'" maxlength="255" class="sl_field '.$conf['style'].'" placeholder="'._SITENAME.'" required></td></tr>'
    .'<tr><td>'._A_LINKS_E.':</td><td><input type="email" name="mail" value="'.$mail.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._A_LINKS_E.'" required></td></tr>'
    .'<tr><td>'._A_LINKS_TEXT.':</td><td>'.textarea('1', 'desc', $desc, $conf['name'], '5', _A_LINKS_TEXT, '1').'</td></tr>'
    .'<tr><td>'._A_LINKS_L.':</td><td><input type="url" name="site" value="'.$site.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._A_LINKS_L.'" required></td></tr>'
    .'<tr><td colspan="2" class="sl_center">'.getCaptcha(1).ad_save('', '', 'send').'</td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function send(): void {
    global $db, $user, $stop, $conf;
    $name = getVar('post', 'name', 'title');
    $desc = getVar('post', 'desc', 'text');
    $site = getVar('post', 'site', 'url');
    $mail = getVar('post', 'mail', 'var');
    $stop = [];
    if (!$name) $stop[] = _CERROR10;
    if (!$desc) $stop[] = _CERROR11;
    if (!$site) $stop[] = _CERROR4;
    checkemail($mail);
    if (checkCaptcha(1)) $stop[] = _SECCODEINCOR;
    if ($db->sql_numrows($db->sql_query('SELECT link FROM '.PREFIX_DB.'_auto_links WHERE link = :sitelink', ['sitelink' => $site])) > 0) $stop[] = _LINKEXIST;
    if (!$stop && getVar('post', 'posttype', 'text') == 'save') {
        setHead(['title' => _ADD]);
        $cont = navigate(_ADD);
        $db->sql_query('INSERT INTO '.PREFIX_DB.'_auto_links VALUES (NULL, :sitename, :description, :sitelink, :adminemail, 0, 0, NOW())', ['sitename' => $name, 'description' => $desc, 'sitelink' => $site, 'adminemail' => $mail]);
        $puname = (is_user()) ? $user[1] : '';
        addmail($conf['auto_links']['addmail'], $conf['name'], $puname, _A_LINKS);
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _A_LINKS_OK]);
        $cont .= setTemplateBasic('open');
        $code = '<a href=&quot;'.$conf['homeurl'].'&quot; target=&quot;_blank&quot; title=&quot;'.$conf['slogan'].'&quot;>'.$conf['sitename'].'</a>';
        $cont .= '<table class="sl_table_form">'
        .'<tr><td>'._A_LINKS_M.':</td><td><textarea name="description" cols="65" rows="5" class="sl_field '.$conf['style'].'">'.$code.'</textarea></td></tr>';
        if ($conf['auto_links']['img']) {
            $banner = img_find('banners/'.$conf['auto_links']['img']);
            if ($banner && file_exists($banner)) {
                [$imgwidth, $imgheight] = getimagesize($banner);
                $code = '<a href=&quot;'.$conf['homeurl'].'&quot; target=&quot;_blank&quot; title=&quot;'.$conf['sitename'].' - '.$conf['slogan'].'&quot;><img src=&quot;'.$conf['homeurl'].'/'.$banner.'&quot; alt=&quot;'.$conf['sitename'].' - '.$conf['slogan'].'&quot; style=&quot;border: 0; width: '.$imgwidth.'; height: '.$imgheight.';&quot;></a>';
                $cont .= '<tr><td>'._A_LINKS_IMG.':</td><td><textarea name="description" cols="65" rows="5" class="sl_field '.$conf['style'].'">'.$code.'</textarea></td></tr>';
            }
        }
        $cont .= '</table>';
        $cont .= setTemplateBasic('close');
        echo $cont;
        setFoot();
    } else {
        add();
    }
}

switch ($op) {
    default:
    autolink();
    break;

    case 'view':
    view();
    break;

    case 'add':
    add();
    break;

    case 'send':
    send();
    break;
}
