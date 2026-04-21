<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Return the CSS season class based on the current date
function setTemplateSeason(): string {
    $zdate = date('z');
    if ($zdate > 355 || $zdate < 5) return 'newyear';
    $seas = [0 => 'winter', 1 => 'spring', 2 => 'summer', 3 => 'autumn'];
    return $seas[floor(date('n') / 3) % 4];
}

# Query recent forum topics and render via forum-teaser partial
function setTemplateForum(): string {
    global $db, $tpl;
    $blimit = 3;
    $bclos = '97, 98, 99, 100, 101';
    $bwhere = $bclos ? 'cid NOT IN ('.$bclos.') AND' : '';
    $ordern = is_moder('forum') ? '' : "AND time <= now() AND status > '1'";
    $items = '';
    $result = $db->getSqlQuery('SELECT id, title, ltime, luid, lname, lpost, status FROM '.PREFIX_DB.'_forum WHERE '.$bwhere." pid = '0' ".$ordern.' ORDER BY ltime DESC LIMIT 0, '.$blimit);
    while ([$id, $title, $time, $luid, $lname, $lpost, $status] = $db->getSqlRow($result)) {
        $poster = $luid ? user_info($lname) : htmlspecialchars((string)$lname, ENT_QUOTES, 'UTF-8');
        $items .= $tpl->getHtmlFrag('forum-teaser-item', [
            'hidden' => ($status <= 1 || $time > date('Y-m-d H:i:s')),
            'href' => 'index.php?name=forum&amp;op=view&amp;id='.(int)$id.'&amp;last#'.$lpost,
            'title' => (string)$title,
            'short' => cutstr((string)$title, 50),
            'by' => _POSTEDBY,
            'poster' => $poster,
            'when' => _DATE.': '.format_time($time, _TIMESTRING),
            'date' => format_time($time),
        ]);
    }
    return $tpl->getHtmlPart('forum-teaser', ['items' => $items, 'label' => _FORUM]);
}

# Provide head-time variables for the lite theme layout
function getThemeHeadVars(): array {
    global $db, $conf, $tpl;
    $mname = $conf['name'] ? getModuleName($conf['name']) : '';
    $fcat = (int)getVar('get', 'cat', 'num', 0);
    $cname = ($fcat && !empty($conf['files'])) ? getTplCategoryTrail($conf['name'], $fcat, $conf['files']['defis'], $mname) : '';
    [$count] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB."_faq WHERE time <= now() AND status != '0'"));
    $random = mt_rand(0, (int)$count);
    [$fid, $title] = $db->getSqlRow($db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_faq ORDER BY id DESC LIMIT '.$random.', 1'));
    $ftitle = htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8');
    $faq = $tpl->getHtmlFrag('lite-faq-random-link', ['url' => 'index.php?name=faq&amp;op=view&amp;id='.(int)$fid, 'title' => $ftitle, 'label' => $ftitle]);
    $head = '';
    if ($mname !== '' || $cname !== '') {
        $head = $tpl->getHtmlFrag('lite-head-banner', ['module' => $mname, 'category' => $cname]);
    }
    return [
        'season' => setTemplateSeason(),
        'modul' => $conf['name'] ?? '',
        'faqtitle' => $faq,
        'head_html' => $head,
    ];
}

# Provide foot-time variables for the lite theme layout
function getThemeFootVars(): array {
    global $tpl;
    $contact = $tpl->getHtmlPart('contact-block', [
        'feedback' => _FEEDBACK,
        'text' => _MESSAGE,
        'pname' => _YOURNAME,
        'email' => _YOUREMAIL,
        'captcha' => getCaptcha(1),
        'token' => getSiteToken('contact'),
        'send' => _SEND,
        'message_field' => ['name_attr' => 'message', 'rows_num' => 5, 'cols_num' => 65, 'placeholder_text' => _MESSAGE, 'is_required' => true],
        'name_field' => ['itype' => 'text', 'name_attr' => 'sname', 'value_attr' => '', 'placeholder_text' => _YOURNAME, 'is_required' => true],
        'email_field' => ['itype' => 'email', 'name_attr' => 'semail', 'value_attr' => '', 'placeholder_text' => _YOUREMAIL, 'is_required' => true],
        'token_field' => ['name_attr' => 'token', 'value_attr' => getSiteToken('contact')],
        'op_field' => ['name_attr' => 'op', 'value_attr' => 'contact'],
        'send_field' => ['name_attr' => 'send', 'value_attr' => '1'],
        'submit_button' => ['button_type' => 'submit', 'label' => _SEND],
    ]);
    return [
        'forumblock' => setTemplateForum(),
        'contactblock' => $contact,
    ];
}
