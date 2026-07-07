<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function forumIcon(string $href, string $title, string $state, string $label = ''): string {
    global $tpl;

    $badge = ['title_text' => $label ?: $title, 'label' => ''];
    $badge[$state] = true;

    return $tpl->getHtmlFrag('link', [
        'href' => $href,
        'title' => $title,
        'label_html' => $tpl->getHtmlFrag('inline-badge', $badge),
    ]);
}

function forum(): void {
    global $db, $conf, $locale, $tpl;
    $rows = [];
    $mod = ($conf['name']) ? filterVar($conf['name']) : 0;
    $cat = getVar('req', 'cat', 'num');
    $id = $cat;
    $pars = ['mod' => $mod];
    if ($id) {
        $where = 'WHERE c.modul = :mod AND (c.parent = :parent OR c.id = :catid)';
        $pars['parent'] = $id;
        $pars['catid'] = $id;
        if ($conf['multilingual']) {
            $where .= ' AND (c.lang = :locale OR c.lang = \'\')';
            $pars['locale'] = $locale;
        }
    } elseif ($conf['multilingual']) {
        $where = 'WHERE c.modul = :mod AND (c.lang = :locale OR c.lang = \'\')';
        $pars['locale'] = $locale;
    } else {
        $where = 'WHERE c.modul = :mod';
    }
    $query = $db->getSqlQuery('SELECT c.id, c.title, c.intro, c.img, c.parent, c.status, c.ordern, c.topics, c.posts, c.lpost, c.pview, c.pread, c.ppost, c.preply, c.pedit, c.pdelete, c.pmod, f.title, f.luid, f.lname, f.lpost, f.ltime FROM '.PREFIX_DB.'_categories AS c LEFT JOIN '.PREFIX_DB.'_forum AS f ON (c.lpost = f.id) '.$where.' ORDER BY c.ordern', $pars);
    while ([$cid, $title, $intro, $img, $parent, $status, $order, $topics, $posts, $lastid, $authv, $authr, $authp, $authy, $authe, $authd, $authm, $ftitle, $fuid, $fname, $flid, $fltime] = $db->getSqlRow($query)) {
        $rows[] = [$cid, $title, $intro, $img, $parent, $status, $order, $topics, $posts, $lastid, $authv, $authr, $authp, $authy, $authe, $authd, $authm, $ftitle, $fuid, $fname, $flid, $fltime];
        unset($cid, $title, $intro, $img, $parent, $status, $order, $topics, $posts, $lastid, $authv, $authr, $authp, $authy, $authe, $authd, $authm, $ftitle, $fuid, $fname, $flid, $fltime);
    }
    if ($rows) {
        $isview = is_acess($rows[0][10]);
        $isread = is_acess($rows[0][11]);
        $istopic = is_acess($rows[0][12]);
        $isreply = is_acess($rows[0][13]);
        $isedit = is_acess($rows[0][14]);
        $isdelete = is_acess($rows[0][15]);
        $ismod = is_acess($rows[0][16]);
        $uinfo = getUserInfo();
        $ulast = (is_array($uinfo) && !empty($uinfo['lastvis'])) ? (int)$uinfo['lastvis'] : 0;
        $head = ($id) ? _FORUM.' '.$rows[0][1] : _FORUM;
        setHead(['title' => $head]);
        $cnt = 0;
        foreach ($rows as $val) {
            if ($val[4] == $id && is_acess($val[10])) {
                if ($id) {
                    if (!$cnt) {
                        $h1 = $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$conf['name'], 'title' => _FORUM, 'label' => _FORUM]);
                        $h2 = $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$mod.'&amp;cat='.$rows[0][0], 'title' => $rows[0][1], 'label' => $rows[0][1]]);
                        $heading = $h1.' '.urldecode($conf['forum']['defis']).' '.$h2;
                        $cont = $tpl->getHtmlFrag('forum-category-table', ['open' => true, 'heading' => $heading, 'col_forum' => _FORUM, 'col_topics' => _NEWTOPICS, 'col_messages' => cutstr(_MESSAGES, 5, 1), 'col_last' => _LASTMESSAGE]);
                    } else {
                        $cont = '';
                    }
                    $ttit = ($val[2]) ? $val[2] : $val[1];
                    $tlink = ($val[5] || is_moder($conf['name'])) ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$mod.'&amp;cat='.$val[0], 'title' => $ttit, 'label' => $val[1]]) : $val[1];
                    $cat_url = 'index.php?name='.$mod.'&amp;cat='.$val[0];
                    if (!$val[5]) {
                        $timg = (is_moder($conf['name'])) ? forumIcon($cat_url, _FCLOSED, 'is_forum_closed') : $tpl->getHtmlFrag('inline-badge', ['title_text' => _FCLOSED, 'label' => '', 'is_forum_closed' => true]);
                    } elseif ($val[21] > $ulast) {
                        $timg = forumIcon($cat_url, _ISNEWPOST, 'is_forum_new');
                    } else {
                        $timg = forumIcon($cat_url, _NONEWPOST, 'is_forum_old');
                    }
                    if ($val[9]) {
                        $data = _DATE.': '.format_time($val[21], _TIMESTRING);
                        $topic_href = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $val[9], 'title' => $val[17]]);
                        $topic_link = ($val[5]) ? $tpl->getHtmlFrag('link', ['href' => $topic_href, 'title' => $val[17], 'label' => cutstr($val[17], 14)]) : cutstr($val[17], 14);
                        $topic = _TOPIC.': '.$topic_link;
                        $post = ($val[18]) ? user_info($val[19]) : $val[19];
                        $post = _POSTER.': '.$post;
                        $lid = ($val[20]) ? $val[20] : $val[9];
                        $lpost = ($val[5]) ? forumIcon($topic_href.'&amp;last=1#'.$lid, _LASTMESSAGE, 'is_forum_last') : $tpl->getHtmlFrag('inline-badge', ['title_text' => _LASTMESSAGE, 'label' => '', 'is_forum_last' => true]);
                    } else {
                        $data = _NO_INFO;
                        $topic = $post = $lpost = '';
                    }
                    $cont .= $tpl->getHtmlFrag('forum-category-row', ['icon' => $timg, 'link' => getCategoryIcon($val[3]).' '.$tlink, 'desc' => $val[2], 'topics' => $val[7], 'posts' => $val[8], 'date' => $data, 'last_topic' => $topic, 'last_post' => $post, 'last_link' => $lpost]);
                    echo $cont;
                } else {
                    $heading = $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$mod.'&amp;cat='.$val[0], 'title' => $val[1], 'label' => $val[1]]);
                    $cont = $tpl->getHtmlFrag('forum-category-table', ['open' => true, 'heading' => $heading, 'col_forum' => _FORUM, 'col_topics' => _NEWTOPICS, 'col_messages' => cutstr(_MESSAGES, 5, 1), 'col_last' => _LASTMESSAGE]);
                    foreach ($rows as $valb) {
                        if ($val[0] == $valb[4] && is_acess($valb[10])) {
                            $ttit = ($valb[2]) ? $valb[2] : $valb[1];
                            $tlink = ($valb[5] || is_moder($conf['name'])) ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$mod.'&amp;cat='.$valb[0], 'title' => $ttit, 'label' => $valb[1]]) : $valb[1];
                            $cat_url = 'index.php?name='.$mod.'&amp;cat='.$valb[0];
                            if (!$valb[5]) {
                                $timg = (is_moder($conf['name'])) ? forumIcon($cat_url, _FCLOSED, 'is_forum_closed') : $tpl->getHtmlFrag('inline-badge', ['title_text' => _FCLOSED, 'label' => '', 'is_forum_closed' => true]);
                            } elseif ($valb[21] > $ulast) {
                                $timg = forumIcon($cat_url, _ISNEWPOST, 'is_forum_new');
                            } else {
                                $timg = forumIcon($cat_url, _NONEWPOST, 'is_forum_old');
                            }
                            if ($valb[9]) {
                                $data = _DATE.': '.format_time($valb[21], _TIMESTRING);
                                $topic_href = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $valb[9], 'title' => $valb[17]]);
                                $topic_link = ($valb[5]) ? $tpl->getHtmlFrag('link', ['href' => $topic_href, 'title' => $valb[17], 'label' => cutstr($valb[17], 14)]) : cutstr($valb[17], 14);
                                $topic = _TOPIC.': '.$topic_link;
                                $post = ($valb[18]) ? user_info($valb[19]) : $valb[19];
                                $post = _POSTER.': '.$post;
                                $lid = ($valb[20]) ? $valb[20] : $valb[9];
                                $lpost = ($valb[5]) ? forumIcon($topic_href.'&amp;last=1#'.$lid, _LASTMESSAGE, 'is_forum_last') : $tpl->getHtmlFrag('inline-badge', ['title_text' => _LASTMESSAGE, 'label' => '', 'is_forum_last' => true]);
                            } else {
                                $data = _NO_INFO;
                                $topic = $post = $lpost = '';
                            }
                            $cont .= $tpl->getHtmlFrag('forum-category-row', ['icon' => $timg, 'link' => getCategoryIcon($valb[3]).' '.$tlink, 'desc' => $valb[2], 'topics' => $valb[7], 'posts' => $valb[8], 'date' => $data, 'last_topic' => $topic, 'last_post' => $post, 'last_link' => $lpost]);
                        }
                    }
                    $cont .= $tpl->getHtmlFrag('forum-category-table', []);
                    echo $cont;
                }
                $cnt++;
            }
        }
        $show = true;
        unset($cont);
        if ($id) {
            if (!$cnt) {
                if ($isview) {
                    $catid = (int)$id;
                    $lang = ($conf['multilingual']) ? 'AND (c.lang = :locale OR c.lang = \'\') AND s.cid = :cat' : 'AND s.cid = :cat';
                    $lpars = ['cat' => $catid];
                    if ($conf['multilingual']) {
                        $lpars['locale'] = $locale;
                    }
                    $listnum = (int)$conf['forum']['listnum'];
                    if ($listnum < 1) $listnum = 1;
                    $ordern = (is_moder($conf['name'])) ? "WHERE s.pid = '0'" : "WHERE s.pid = '0' AND s.time <= NOW() AND s.status != '0'";
                    $num = getVar('req', 'num', 'num') ?: 1;
                    $offset = (int)(($num - 1) * $listnum);
                    $query = $db->getSqlQuery('SELECT s.id, s.cid, s.name, s.title, s.time, s.body, s.comments, s.counter, s.score, s.ratings, s.ip, s.luid, s.lname, s.lpost, s.ltime, s.status, c.id, c.title, c.intro, c.img, u.name FROM '.PREFIX_DB.'_forum AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.id) '.$ordern.' '.$lang.' ORDER BY s.status DESC, s.ltime DESC LIMIT '.$offset.', '.$listnum, $lpars);
                    $newbt = ($istopic)
                        ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$rows[0][0], 'title' => _NEWTOPIC, 'is_account_button' => true, 'label' => _OPEN])
                        : $tpl->getHtmlFrag('inline-badge', ['title_text' => sprintf(_ACINFOT, _NOTCAN), 'is_account_button' => true, 'is_hidden' => true, 'label' => _OPEN]);
                    $cat_link = $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$mod.'&amp;cat='.$rows[0][0], 'title' => $rows[0][1], 'label' => $rows[0][1]]);
                    $cont = $tpl->getHtmlFrag('forum-topic-view', ['open' => true, 'button' => $newbt, 'title_html' => $cat_link]);
                    if ($db->getSqlRowCount($query) > 0) {
                        $mark = 0;
                        $canmod = is_moder($conf['name']);
                        $pop = (int)$conf['forum']['pop'];
                        $slabels = [
                            'is_topic_moderated' => _TOPICM,
                            'is_topic_admin' => _TOPICA,
                            'is_topic_closed' => _TOPICN,
                            'is_topic_new' => _ISNEWPOST,
                            'is_topic_old' => _NONEWPOST,
                            'is_topic_popular_new' => _TPOPN,
                            'is_topic_popular_old' => _TPOP,
                            'is_topic_pending' => _TOPICP,
                            'is_topic_hot' => _THOT,
                            'is_topic_announcement' => _TANNOUN,
                        ];
                        $topicList = $tpl->getHtmlFrag('forum-category-table', ['open' => true, 'is_topic_list' => true, 'col_topics' => _NEWTOPICS, 'col_posts' => _POSTS, 'col_poster' => _POSTER, 'col_views' => cutstr(_TVIEWS, 5, 1), 'col_last' => _LASTMESSAGE]);
                        while ([$id, $cid, $uname, $title, $time, $hometext, $comments, $counter, $score, $ratings, $ipsend, $luid, $lname, $lid, $ltime, $status, $cat, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($query)) {
                            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]);
                            $title = getDecodedText($title);
                            $state = getForumTopicState((int)$status, $time, $ltime, (int)$comments, $pop, $ulast, $canmod);
                            $view = $state !== '' ? 1 : 0;
                            $canlink = !($status == 1 && !$canmod);
                            $slabel = $slabels[$state] ?? '';
                            $badge = $state ? $tpl->getHtmlFrag('inline-badge', ['title_text' => $slabel, 'label' => '', $state => true]) : '';
                            $tlink = $canlink ? $tpl->getHtmlFrag('link', ['href' => $thref, 'title' => $title, 'label_html' => $badge, 'label' => $title]) : $badge.' '.$title;
                            $lpost = $canlink ? forumIcon($thref.'&amp;last=1#'.$lid, _LASTMESSAGE, 'is_forum_last') : $tpl->getHtmlFrag('inline-badge', ['title_text' => _LASTMESSAGE, 'label' => '', 'is_forum_last' => true]);
                            $ldata = _DATE.': '.format_time($ltime, _TIMESTRING);
                            $post = ($nick) ? user_info($nick) : $uname.' ('._ANONYM.')';
                            $lposter = ($luid) ? _POSTER.': '.user_info($lname) : _POSTER.': '.$lname;
                            if ($ismod) {
                                $itemCheck = $tpl->getHtmlFrag('checkbox', ['name_attr' => 'id[]', 'value_attr' => (string)$id, 'is_legacy_check' => true]);
                                if (!$mark) {
                                    $markAll = $tpl->getHtmlFrag('checkbox', ['name_attr' => 'markcheck', 'input_id' => 'markcheck', 'is_plain' => true]);
                                    $checkb = $tpl->getHtmlPart('compact-list', ['items' => [
                                        ['content_html' => _CHECKALL.' '.$markAll.' | '.$itemCheck, 'is_break_before' => true],
                                    ]]);
                                } else {
                                    $checkb = $itemCheck;
                                }
                                $mark++;
                            } else {
                                $checkb = '';
                            }
                            $topicList .= ($view) ? $tpl->getHtmlFrag('forum-category-row', ['is_topic_list' => true, 'link' => $tlink, 'replies' => $comments, 'posts' => $post, 'views' => $counter, 'last_date' => $ldata, 'last_poster' => $lposter, 'last_link' => $lpost.$checkb]) : '';
                        }
                        $topicList .= $tpl->getHtmlFrag('forum-category-table', []);
                        if ($ismod) {
                            $topicList .= $tpl->getHtmlPart('fieldset-panel', ['legend' => _CHECKOP, 'is_moder_mass' => true, 'is_action_label' => true, 'content' => tmoder(1)
                                .$tpl->getHtmlFrag('hidden', ['name_attr' => 'op', 'value_attr' => 'move', 'input_attr' => ''])
                                .$tpl->getHtmlFrag('hidden', ['name_attr' => 'cat', 'value_attr' => (string)$catid, 'input_attr' => ''])
                                .$tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'label' => _OK])]);
                            $cont .= $tpl->getHtmlPart('form-wrap', ['action' => 'index.php?name='.$conf['name'], 'content_html' => $topicList]);
                        } else {
                            $cont .= $topicList;
                        }
                    } else {
                        if ((int)$num > 1) setError(404);
                        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
                    }
                    $order = (is_moder($conf['name'])) ? "pid = '0' AND cid = '".$catid."'" : "pid = '0' AND cid = '".$catid."' AND time <= NOW() AND status != '0'";
                    $pnum = getTplPager([
                        'limit'     => $listnum,
                        'maxpg'     => $conf['forum']['pnum'],
                        'table'     => '_forum',
                        'field'     => 'id',
                        'mod'       => $conf['name'],
                        'where'     => $order,
                        'url_extra' => ['cat' => $catid],
                        'prefix'    => 'new/',
                    ]);
                    $cont .= $tpl->getHtmlFrag('forum-topic-view', ['button' => $newbt, 'pager' => $pnum]);
                    $b_can = $tpl->getHtmlFrag('span', ['is_bold' => true, 'is_success' => true, 'text' => _ISCAN]);
                    $b_not = $tpl->getHtmlFrag('span', ['is_bold' => true, 'is_danger' => true, 'text' => _NOTCAN]);
                    $infov = ($isview) ? sprintf(_ACINFOV, $b_can) : sprintf(_ACINFOV, $b_not);
                    $infor = ($isread) ? sprintf(_ACINFOR, $b_can) : sprintf(_ACINFOR, $b_not);
                    $infot = ($istopic) ? sprintf(_ACINFOT, $b_can) : sprintf(_ACINFOT, $b_not);
                    $infop = ($isreply) ? sprintf(_ACINFOP, $b_can) : sprintf(_ACINFOP, $b_not);
                    $infoe = ($isedit) ? sprintf(_ACINFOE, $b_can) : sprintf(_ACINFOE, $b_not);
                    $infod = ($isdelete) ? sprintf(_ACINFOD, $b_can) : sprintf(_ACINFOD, $b_not);
                    $infom = ($ismod) ? sprintf(_ACINFOM, $b_can) : sprintf(_ACINFOM, $b_not);
                    $cont .= $tpl->getHtmlFrag('forum-list-info', [
                        'new'         => $tpl->getHtmlFrag('inline-badge', ['title_text' => _ISNEWPOST, 'is_topic_new' => true, 'label' => _ISNEWPOST]),
                        'old'         => $tpl->getHtmlFrag('inline-badge', ['title_text' => _NONEWPOST, 'is_topic_old' => true, 'label' => _NONEWPOST]),
                        'popular_new' => $tpl->getHtmlFrag('inline-badge', ['title_text' => _TPOPN, 'is_topic_popular_new' => true, 'label' => _TPOPN]),
                        'popular'     => $tpl->getHtmlFrag('inline-badge', ['title_text' => _TPOP, 'is_topic_popular_old' => true, 'label' => _TPOP]),
                        'announce'    => $tpl->getHtmlFrag('inline-badge', ['title_text' => _TANNOUN, 'is_topic_announcement' => true, 'label' => _TANNOUN]),
                        'hot'         => $tpl->getHtmlFrag('inline-badge', ['title_text' => _THOT, 'is_topic_hot' => true, 'label' => _THOT]),
                        'mod'         => $tpl->getHtmlFrag('inline-badge', ['title_text' => _TOPICM, 'is_topic_moderated' => true, 'label' => _TOPICM]),
                        'admin'       => $tpl->getHtmlFrag('inline-badge', ['title_text' => _TOPICA, 'is_topic_admin' => true, 'label' => _TOPICA]),
                        'closed'      => $tpl->getHtmlFrag('inline-badge', ['title_text' => _TOPICN, 'is_topic_closed' => true, 'label' => _TOPICN]),
                        'pinned'      => $tpl->getHtmlFrag('inline-badge', ['title_text' => _TOPICP, 'is_topic_pending' => true, 'label' => _TOPICP]),
                        'perm_view'   => $infov,
                        'perm_read'   => $infor,
                        'perm_topic'  => $infot,
                        'perm_reply'  => $infop,
                        'perm_edit'   => $infoe,
                        'perm_delete' => $infod,
                        'perm_mod'    => $infom,
                    ]);
                } else {
                    $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 5]);
                    $cont = $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _NOVIEW, 'meta' => $meta]);
                }
                $show = false;
            } else {
                $cont = $tpl->getHtmlFrag('forum-category-table', []);
            }
        } else {
            $cont = '';
        }
        if ($show) $cont .= $tpl->getHtmlFrag('forum-list-info', [
            'is_category_info' => true,
            'new'    => $tpl->getHtmlFrag('inline-badge', ['title_text' => _ISNEWPOST, 'is_forum_new' => true, 'label' => _ISNEWPOST]),
            'nonew'  => $tpl->getHtmlFrag('inline-badge', ['title_text' => _NONEWPOST, 'is_forum_old' => true, 'label' => _NONEWPOST]),
            'closed' => $tpl->getHtmlFrag('inline-badge', ['title_text' => _FCLOSED, 'is_forum_closed' => true, 'label' => _FCLOSED]),
        ]);
        echo $cont;
    } else {
        setHead(['title' => _FORUM]);
        $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 5]);
        echo $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _NO_INFO, 'meta' => $meta]);
    }
    setFoot();
}

function view(): void {
    global $db, $user, $conf, $tpl, $prs;
    $rows = [];
    $where = [];
    $users = [];
    $topic = getVar('req', 'id', 'num');
    $last = (filter_input(INPUT_GET, 'last', FILTER_DEFAULT) !== null) ? 1 : 0;
    $ordern = (is_moder($conf['name'])) ? 'WHERE (id = :id1 OR pid = :id2)' : "WHERE (id = :id1 OR pid = :id2) AND time <= NOW() AND status != '0'";
    $opars = ['id1' => $topic, 'id2' => $topic];
    [$numfor] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_forum '.$ordern, $opars));
    if ($topic && $numfor > 0) {
        $fornum = max(1, (int)getUserNews($conf['forum']['num']));
        $numpages = max(1, (int)ceil($numfor / $fornum));
        $num = (int)(getVar('req', 'num', 'num') ?: 1);
        $num = ($last && $conf['forum']['sort']) ? $numpages : $num;
        if ($num < 1 || $num > $numpages) setError(404);
        $offset = ($num-1) * $fornum;
        if ($conf['forum']['sort']) {
            $sort = 'ASC';
            $pos = ($num) ? $offset+1 : 1;
        } else {
            $sort = 'DESC';
            $pos = $numfor;
            if ($numfor > $offset) $pos -= $offset;
        }
        $word = getVar('req', 'word', 'word');
        $orderw = (is_moder($conf['name'])) ? 'WHERE (s.id = :id1 OR s.pid = :id2)' : "WHERE (s.id = :id1 OR s.pid = :id2) AND s.time <= NOW() AND s.status != '0'";
        $query = $db->getSqlQuery('SELECT s.id, s.pid, s.cid, s.uid, s.name, s.title, s.time, s.body, s.field, s.comments, s.counter, s.score, s.ratings, s.ip, s.euid, s.eip, s.etime, s.status, c.title, c.pread, c.ppost, c.preply, c.pedit, c.pdelete, c.pmod FROM '.PREFIX_DB.'_forum AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) '.$orderw.' ORDER BY s.time '.$sort.' LIMIT '.$offset.', '.$fornum, $opars);
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_forum SET counter=counter+1 WHERE id = :id', ['id' => $topic]);
        while ([$id, $pid, $cid, $uid, $name, $title, $time, $hometext, $field, $comments, $counter, $score, $ratings, $ipsend, $euid, $eip, $etime, $status, $ctitle, $authr, $authp, $authy, $authe, $authd, $authm] = $db->getSqlRow($query)) {
            $rows[] = [$id, $pid, $cid, $uid, $name, $title, $time, $hometext, $field, $comments, $counter, $score, $ratings, $ipsend, $euid, $eip, $etime, $status, $ctitle, $authr, $authp, $authy, $authe, $authd, $authm];
            if ($uid) $where[] = $uid;
            unset($id, $pid, $cid, $uid, $name, $title, $time, $hometext, $field, $comments, $counter, $score, $ratings, $ipsend, $euid, $eip, $etime, $status, $ctitle, $authr, $authp, $authy, $authe, $authd, $authm);
        }
        if (!$rows) setError(404);
        if ($where) {
            $query = $db->getSqlQuery('SELECT u.id, u.name, u.rank, u.email, u.website, u.avatar, u.regdate, u.origin, u.sig, u.viewmail, u.points, u.warnings, u.gender, u.votes, u.tvotes, g.name, g.rank, g.color FROM '.PREFIX_DB.'_users AS u LEFT JOIN '.PREFIX_DB.'_groups AS g ON ((g.extra=1 AND u.grp=g.id) OR (g.extra!=1 AND u.points>=g.points)) WHERE u.id IN ('.implode(', ', $where).') ORDER BY g.extra ASC, g.points ASC');
            while ([$uid, $nick, $rank, $mail, $site, $avatar, $reg, $from, $sig, $view, $point, $warn, $gender, $votes, $total, $gname, $grank, $gcolor] = $db->getSqlRow($query)) {
                $users[] = [$uid, $nick, $rank, $mail, $site, $avatar, $reg, $from, $sig, $view, $point, $warn, $gender, $votes, $total, $gname, $grank, $gcolor];
                unset($uid, $nick, $rank, $mail, $site, $avatar, $reg, $from, $sig, $view, $point, $warn, $gender, $votes, $total, $gname, $grank, $gcolor);
            }
        }
        if ($num == 1) {
            $tstatus = $rows[0][17];
        } else {
            [$tstatus] = $db->getSqlRow($db->getSqlQuery('SELECT status FROM '.PREFIX_DB.'_forum WHERE id = :id', ['id' => $topic]));
        }
        $isread = is_acess((string)($rows[0][19] ?? ''));
        $istopic = is_acess((string)($rows[0][20] ?? ''));
        $isreply = is_acess((string)($rows[0][21] ?? ''));
        $isedit = is_acess((string)($rows[0][22] ?? ''));
        $isdelete = is_acess((string)($rows[0][23] ?? ''));
        $ismod = is_acess((string)($rows[0][24] ?? ''));
        $seodesc = cutstr(trim(strip_tags($prs->filterContent($rows[0][7], false, $conf['name']))), 160);
        $seoimg = getImgText($rows[0][7], '', false);
        $seoimg = $seoimg ? $conf['homeurl'].'/'.$seoimg : '';
        if (!$ismod) {
            if (!$isread) setError(403);
            elseif ($tstatus <= 1) setError(404);
        }
        setHead([
            'title' => $rows[0][5],
            'ctitle' => $rows[0][18],
            'cid' => $rows[0][2],
            'desc' => $seodesc,
            'img' => $seoimg,
            'time' => $rows[0][6],
            'author' => $rows[0][4] ?: $conf['sitename'],
        ]);
        if ($ismod || ($isread && $tstatus > 1)) {
            $atopic = (is_moder($conf['name']) || $istopic)
                ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$rows[0][2], 'title' => _NEWTOPIC, 'is_account_button' => true, 'label' => _OPEN])
                : $tpl->getHtmlFrag('inline-badge', ['title_text' => sprintf(_ACINFOT, _NOTCAN), 'is_account_button' => true, 'is_hidden' => true, 'label' => _OPEN]);
            $areply = (is_moder($conf['name']) || ($isreply && $tstatus))
                ? $tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$rows[0][2].'&amp;pid='.$topic, 'title' => _TOPICREPLY, 'is_account_button' => true, 'label' => _REPLY])
                : $tpl->getHtmlFrag('inline-badge', ['title_text' => sprintf(_ACINFOP, _NOTCAN), 'is_account_button' => true, 'is_hidden' => true, 'label' => _REPLY]);
            $pnum = getPageNumbers($conf['name'], $numfor, $numpages, $fornum, 'op=view&id='.$topic.'&', $conf['forum']['pnum'], $num);
            $favor = getFavoriteButton($topic, $conf['name']);
            $cont = $tpl->getHtmlFrag('forum-topic-view', ['open' => true, 'atopic' => $atopic, 'areply' => $areply, 'title' => filterTextHighlight($rows[0][5], $word), 'favor' => $favor]);
            foreach ($rows as $val) {
                $fid = $val[0];
                $fcat = $val[2];
                unset($uid, $nick, $rank, $mail, $site, $avatar, $reg, $from, $sig, $view, $point, $warn, $gender, $votes, $total, $gname, $grank, $gcolor);
                if (!empty($users) && is_array($users)) {
                    foreach ($users as $urow) {
                        if (strtolower($val[3]) == strtolower($urow[0])) {
                            $uid = $urow[0];
                            $nick = $urow[1];
                            $rank = $urow[2];
                            $mail = $urow[3];
                            $site = $urow[4];
                            $avatar = $urow[5];
                            $reg = $urow[6];
                            $from = $urow[7];
                            $sig = $urow[8];
                            $view = $urow[9];
                            $point = $urow[10];
                            $warn = $urow[11];
                            $gender = $urow[12];
                            $votes = $urow[13];
                            $total = $urow[14];
                            $gname = $urow[15];
                            $grank = $urow[16];
                            $gcolor = $urow[17];
                        }
                    }
                }
                $avname = (!empty($nick)) ? $nick : ($val[4] ?: (string)_ANONYM);
                $avatar = (!empty($nick)) ? (($avatar && file_exists($conf['users']['adirectory'].'/'.$avatar)) ? $conf['users']['adirectory'].'/'.$avatar : $conf['users']['adirectory'].'/default/00.gif') : $conf['users']['adirectory'].'/default/0.gif';
                $date = $tpl->getHtmlFrag('inline-badge', ['title_text' => _PADD, 'is_comment_date' => true, 'label' => format_time($val[6], _TIMESTRING)]);
                if (($ismod || $conf['forum']['ledit']) && $val[16]) {
                    $date .= $tpl->getHtmlFrag('inline-badge', ['title_text' => _PEDIT, 'is_topic_edit' => true, 'label' => format_time($val[16], _TIMESTRING)]);
                }
                $rating = ($pos == 1) ? getRatingAsync(1, $fid, $conf['name'], $val[12], $val[11], '', 1) : '';
                $ip = ($ismod && $val[13]) ? Geoip::getIpHtml($val[13]) : '';
                $amess = $tpl->getHtmlFrag('link', ['href' => '#'.$fid, 'title' => _MESSAGE.': '.$pos, 'label' => (string)$pos, 'is_card_id' => true]);
                $rank = (!empty($rank)) ? $rank : '';
                $trank = (!empty($gname)) ? _GROUP.': '.$gname : _RANK;
                $rlink = (!empty($grank) && file_exists(img_find('ranks/'.$grank))) ? $tpl->getHtmlFrag('image', ['src' => img_find('ranks/'.$grank), 'alt' => $trank, 'title' => $trank]) : '';
                $rate = (!empty($uid)) ? getRatingAsync(0, $uid, 'account', $votes, $total, $fid, 1) : '';
                $utip = getUserTip((string)($gname ?? ''), $point ?? 0, (string)($reg ?? ''), (int)($gender ?? 0), (string)($from ?? ''), (string)($warn ?? ''), empty($nick), (int)$val[3] > 0 && empty($val[4]));
                $uname_html = (!empty($nick)) ? user_info($nick, false) : htmlspecialchars($avname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $fields = getTplViewFieldRows(['field' => $val[8], 'mod' => $conf['name']]);
                $sig = (!empty($sig)) ? $tpl->getHtmlFrag('block-content', ['is_signature' => true, 'content' => $sig]) : '';
                $uitems = [];
                if (is_moder($conf['name']) || ($isreply && $tstatus && $conf['forum']['qreply'])) {
                    $uitems[] = $tpl->getHtmlFrag('link', ['href' => '#', 'title' => _PERSONAL, 'label' => _PERS, 'link_attr' => getTplEditorInsertAttr('name', $avname)]);
                }
                if ($conf['forum']['privat'] && $conf['privat']['act'] && !empty($nick)) {
                    $uitems[] = $tpl->getHtmlFrag('link', ['href' => 'index.php?name=account&amp;op=privat&amp;uname='.urlencode($nick), 'title' => _SENDMES, 'label' => _MESSAGE]);
                }
                if ($conf['forum']['profil'] && !empty($nick)) {
                    $uitems[] = $tpl->getHtmlFrag('link', ['href' => 'index.php?name=account&amp;op=view&amp;uname='.urlencode($nick), 'title' => _PERSONALINFO, 'label' => _ACCOUNT]);
                }
                if ($conf['forum']['web'] && !empty($site)) {
                    $uitems[] = $tpl->getHtmlFrag('link', ['href' => $site, 'title' => _DOWNLLINK, 'label' => _SITE, 'is_blank' => true]);
                }
                if (is_moder($conf['name']) || ($isreply && $tstatus)) {
                    $qhref = 'index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$fcat.'&amp;pid='.$topic.'&amp;qid='.$fid;
                    $uitems[] = $tpl->getHtmlFrag('link', ['href' => $qhref, 'title' => _QREPLY, 'label' => _REPLY]);
                }
                $usermenu = getActionMenu($uitems, true);
                $warn = '';
                $thank = '';
                $edit_href = 'index.php?go=1&amp;op=updatePost&amp;id='.$fid.'&amp;cid='.$fcat.'&amp;typ=1&amp;mod='.$conf['name'];
                $edit = ($ismod || ($isedit && $val[3] == (int)$user[0] && $tstatus))
                    ? $tpl->getHtmlFrag('link', ['href' => $edit_href, 'hx_target' => '#repfor'.$fid, 'title' => _ONEDIT, 'label' => _ONEDIT, 'is_htmx' => true])
                      .'||'
                      .$tpl->getHtmlFrag('link', ['href' => 'index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$fcat.'&amp;id='.$fid.'&amp;pid='.$topic, 'title' => _FULLEDIT, 'label' => _FULLEDIT])
                      .'||'
                    : '';
                $edit .= ($ismod || ($isdelete && $val[3] == (int)$user[0]))
                    ? $tpl->getHtmlFrag('link', [
                        'href'         => 'index.php?name='.$conf['name'].'&amp;op=delete&amp;cat='.$fcat.'&amp;id='.$fid,
                        'confirm_text' => _DELETE.' &quot;'.$val[5].'&quot;?',
                        'title'        => _ONDELETE,
                        'label'        => _ONDELETE,
                        'is_delete'    => true,
                      ])
                    : '';
                $edit = ($edit) ? getActionMenu(explode('||', $edit)) : '';
                $body_html = filterTextHighlight($prs->filterContent($val[7], false, $conf['name']), $word);
                $text = $tpl->getHtmlFrag('block-content', ['id' => 'repfor'.$fid, 'content' => $body_html]);
                if ($fields) $text .= filterTextHighlight($prs->filterContent("\n\n".$fields, false, $conf['name']), $word);
                $cont .= $tpl->getHtmlFrag('forum-post', ['id' => $fid, 'username' => $avname, 'username_html' => $uname_html, 'report' => $utip, 'date' => $date, 'rating' => $rating, 'ip' => $ip, 'post_count' => $amess, 'avatar' => $avatar, 'rank' => $rank, 'rank_link' => $rlink, 'user_rate' => $rate, 'text' => $text, 'sig' => $prs->filterContent($sig, false, $conf['name']), 'btn_user' => $usermenu, 'btn_warn' => $warn, 'btn_thank' => $thank, 'btn_edit' => $edit, 'is_closed' => !$val[17], 'closed_title' => _PCLOSED]);
                if ($conf['forum']['sort']) { $pos++; } else { $pos--; }
            }
            $pnum = getPageNumbers($conf['name'], $numfor, $numpages, $fornum, 'op=view&id='.$topic.'&', $conf['forum']['pnum'], $num);
            $cont .= $tpl->getHtmlFrag('forum-topic-view', ['atopic' => $atopic, 'areply' => $areply, 'pager' => $pnum]);
            if ($ismod) {
                $selmm = tmoder(1)
                    .$tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'move', 'extra' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'cat', 'value_attr' => (string)$rows[0][2], 'input_attr' => '']).$tpl->getHtmlFrag('hidden', ['name_attr' => 'id[]', 'value_attr' => (string)$topic, 'input_attr' => '']), 'name' => '', 'val' => '', 'select' => false, 'show_preview' => false, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _OK]);
                $cont .= $tpl->getHtmlPart('form-wrap', ['action' => 'index.php?name='.$conf['name'], 'content_html' => $tpl->getHtmlPart('fieldset-panel', ['legend' => _OPMOD, 'is_moder_mass' => true, 'is_action_label' => true, 'content' => $selmm])]);
            }
            if (is_moder($conf['name']) || ($isreply && $tstatus)) $cont .= quickreply($topic, $rows[0][2], $rows[0][5]);
        }
        echo $cont;
    setFoot();
    } else {
        setError(404);
    }
}

function quickreply(int|string|null $id, int|string|null $catid, string $subject): string {
    global $conf, $tpl;
    $id = (int)$id;
    $catid = (int)$catid;
    if ($conf['forum']['qreply'] == 1 && $id > 0 && $catid > 0) {
        $rows = (!is_user()) ? $tpl->getHtmlFrag('form-field-row', [
            'label' => _YOURNAME,
            'field_html' => $tpl->getHtmlFrag('input', ['input_attr' => 'placeholder="'._YOURNAME.'" required', 'itype' => 'text', 'name_attr' => 'postname', 'value_attr' => _ANONYM]),
        ]) : '';
        $rows .= $tpl->getHtmlFrag('form-field-row', ['label' => _TEXT, 'field_html' => getTplTextarea(['id' => '1', 'name' => 'hometext', 'value' => '', 'mod' => $conf['name'], 'rows' => '10', 'placeholder' => _TEXT, 'required' => '1'])]);
        $rows .= getTplFieldsIn(['mod' => $conf['name']]);
        $hide = $tpl->getHtmlFrag('hidden', ['name_attr' => 'subject', 'value_attr' => $subject, 'input_attr' => ''])
            .$tpl->getHtmlFrag('hidden', ['name_attr' => 'pid', 'value_attr' => (string)$id, 'input_attr' => ''])
            .$tpl->getHtmlFrag('hidden', ['name_attr' => 'cat', 'value_attr' => (string)$catid, 'input_attr' => ''])
            .$tpl->getHtmlFrag('hidden', ['name_attr' => 'posttype', 'value_attr' => 'save', 'input_attr' => '']);
        $rows .= $tpl->getHtmlFrag('form-field-row', ['label' => '', 'field_html' => $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'send', 'extra' => $hide, 'name' => '', 'val' => '', 'select' => false, 'show_preview' => false, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _SEND])]);
        $cont = $tpl->getHtmlPart('form-add', [
            'action' => 'index.php?name='.$conf['name'],
            'method' => 'post',
            'form_name' => 'post',
            'form_attr' => 'class="sl-forum-reply-form"',
            'fields' => $rows,
        ]);
        return $tpl->getHtmlFrag('title', ['title' => _QUICKREPLY, 'is_forum_heading' => true]).$cont;
    }
    return '';
}

function move(): void {
    global $db, $conf;
    $cat = getVar('post', 'cat', 'num');
    $catid = $cat;
    if ($conf['forum']['add'] && $catid) {
        [$authm] = $db->getSqlRow($db->getSqlQuery('SELECT pmod FROM '.PREFIX_DB.'_categories WHERE id = :catid', ['catid' => $catid]));
        $ismod = is_acess($authm);
        $id = getVar('post', 'id[]', '', []);
        $tmove = getVar('post', 'tmove', 'text');
        $move = (is_numeric($tmove[0])) ? (int)$tmove : (int)substr($tmove, 1);
        if ($ismod && is_array($id) && $tmove[0]) {
            foreach ($id as $val) {
                if ((int)$val) {
                    if ($tmove[0] == 's') {
                        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_forum SET status = :tmove WHERE id = :val', ['tmove' => $move, 'val' => $val]);
                    } elseif ($tmove[0] == 'd') {
                        delete($catid, $val);
                    } elseif (is_numeric($tmove[0])) {
                        $rcatids = catids($conf['name'], $move);
                        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_forum SET cid = :tmove WHERE id = :id_val OR pid = :pid_val', ['tmove' => $move, 'id_val' => $val, 'pid_val' => $val]);
                        [$rnpost] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_forum WHERE pid = :val', ['val' => $val]));
                        $wrnpost = ($rnpost) ? ', posts=posts+'.$rnpost : '';
                        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET topics=topics+1'.$wrnpost.', lpost = :val WHERE id IN ('.$rcatids.')', ['val' => $val]);

                        $catids = catids($conf['name'], $catid);
                        [$lid] = $db->getSqlRow($db->getSqlQuery('SELECT lpost FROM '.PREFIX_DB.'_categories WHERE id = :catid', ['catid' => $catid]));
                        [$npost] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_forum WHERE pid = :val', ['val' => $val]));
                        $wnpost = ($npost) ? ', posts=posts-'.$npost : '';
                        if ($lid == $val) {
                            [$lid] = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_forum WHERE cid = :catid AND ((pid != \'0\' && status = \'1\') || (pid = \'0\' && status > \'1\')) ORDER BY id DESC LIMIT 1', ['catid' => $catid]));
                            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET topics=topics-1'.$wnpost.', lpost = :lid WHERE id IN ('.$catids.')', ['lid' => $lid]);
                        } else {
                            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET topics=topics-1'.$wnpost.' WHERE id IN ('.$catids.')');
                        }
                    }
                }
            }
        }
    }
    $link = ($catid) ? '&cat='.$catid : '';
    setRedirect('index.php?name='.$conf['name'].$link);
}

function add(): void {
    global $db, $user, $conf, $stop, $tpl;
    $cat = getVar('req', 'cat', 'num');
    $catid = $cat;
    [$ctitle, $authp, $authy, $authe, $authm] = $db->getSqlRow($db->getSqlQuery('SELECT title, ppost, preply, pedit, pmod FROM '.PREFIX_DB.'_categories WHERE id = :catid', ['catid' => $catid]));
    $istopic = is_acess($authp);
    $isreply = is_acess($authy);
    $isedit = is_acess($authe);
    $ismod = is_acess($authm);

    $form = false;
    $id = getVar('req', 'id', 'num');
    $pid = getVar('req', 'pid', 'num');
    $qpid = 0;
    $fid = 0;
    $ftitle = '';
    $ftext = '';
    $field = '';
    $status = 3;
    $time = '';
    $subh = 0;

    $where = (is_moder($conf['name'])) ? 'WHERE id = :pid' : 'WHERE id = :pid AND status != \'0\'';
    [$fstatus] = $db->getSqlRow($db->getSqlQuery('SELECT status FROM '.PREFIX_DB.'_forum '.$where, ['pid' => $pid]));

    if ($conf['forum']['add'] && $id) {
        $fid = $id;
        [$qpid, $uid, $subject, $time, $hometext, $field, $status] = $db->getSqlRow($db->getSqlQuery('SELECT pid, uid, title, time, body, field, status FROM '.PREFIX_DB.'_forum WHERE id = :id', ['id' => $id]));
        if ($ismod || ($isedit && $uid == (int)$user[0] && $fstatus > 2)) {
            $subh = ($qpid) ? 1 : 0;
            $info = _EDITS.': '.$subject;
            $head = $conf['defis'].' '._FORUM.' '.$conf['defis'].' '.$ctitle.' '.$conf['defis'].' '.$info;
            $form = true;
        }
        $subold = $subject;
        $subject = getVar('post', 'subject', 'title');
        $subject = $subject ?: $subold;
        $txtold = $hometext;
        $hometext = getVar('post', 'hometext', 'raw');
        $hometext = $hometext ?: $txtold;

    } elseif ($conf['forum']['add'] && ($istopic || $isreply)) {
        $fid = getVar('post', 'fid', 'num');

        $qid = getVar('req', 'qid', 'num');
        $subh = (!empty($pid) || !empty($qpid)) ? 1 : 0;

        if ($pid) {
            $id = ($qid) ? $qid : $pid;
            [$ftitle, $ftext, $status] = $db->getSqlRow($db->getSqlQuery('SELECT title, body, status FROM '.PREFIX_DB.'_forum WHERE id = :id', ['id' => $id]));
            $form = (is_moder($conf['name'])) ? true : (($fstatus > 2) ? true : false);

        } else {
            $form = true;
        }

        $subject = getVar('post', 'subject', 'title');
        $subject = $ftitle ?: $subject;
        $hometext = getVar('post', 'hometext', 'raw');
        $hometext = ($qid && $ftext) ? '[quote]'.$ftext.'[/quote]' : $hometext;
        $field = getVar('post', 'field', 'field');
        $status = getVar('post', 'status', 'num', 3);
        $time = getVar('req', 'time', 'time');
        $info = (!empty($ftext)) ? _PUBLICIN.': '.$ftitle : _PUBLICIN.': '.$ctitle;
        $head = _FORUM.' '.$ctitle.' '.$info;

    }
    if ($form) {
        setHead(['title' => $head]);
        $cont = ($stop) ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]) : '';
        $psubject = (!$subh) ? $subject : '';
        if ($hometext) $cont .= getTplPreviewContent(['title' => $psubject, 'texta' => $hometext, 'textb' => '', 'mod' => $conf['name']]);
        $userinfo = getUserInfo();
        if ($userinfo['access'] || (!is_user() && !$conf['forum']['anonpost'])) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _POSTNOTE]);
        $cont .= $tpl->getHtmlFrag('title', ['title' => $info, 'is_forum_heading' => true]);
        $rows = (!is_user()) ? $tpl->getHtmlFrag('form-field-row', [
            'label' => _YOURNAME,
            'field_html' => $tpl->getHtmlFrag('input', ['input_attr' => 'placeholder="'._YOURNAME.'" required', 'itype' => 'text', 'name_attr' => 'postname', 'value_attr' => _ANONYM]),
        ]) : '';
        $rows .= ($subh) ? $tpl->getHtmlFrag('hidden', ['name_attr' => 'subject', 'value_attr' => $subject, 'input_attr' => '']) : $tpl->getHtmlFrag('form-field-row', [
            'label' => _TITLE,
            'field_html' => $tpl->getHtmlFrag('input', ['input_attr' => 'maxlength="100" placeholder="'._TITLE.'" required', 'itype' => 'text', 'name_attr' => 'subject', 'value_attr' => $subject]),
        ]);
        $rows .= $tpl->getHtmlFrag('form-field-row', ['label' => _TEXT, 'field_html' => getTplTextarea(['id' => '1', 'name' => 'hometext', 'value' => $hometext, 'mod' => $conf['name'], 'rows' => '15', 'placeholder' => _TEXT, 'required' => '1'])]);
        $rows .= getTplFieldsIn(['field' => $field, 'mod' => $conf['name']]);
        $rows .= ($ismod) ? $tpl->getHtmlFrag('form-field-row', ['label' => _OPMOD, 'field_html' => pmoder($status, $subh)]).$tpl->getHtmlFrag('form-field-row', ['label' => _CHNGSTORY, 'field_html' => getTplAddDateTime(['name' => 'time', 'time' => $time, 'with' => true, 'max' => 16])]) : '';
        $hide = $tpl->getHtmlFrag('hidden', ['name_attr' => 'id', 'value_attr' => (string)$id, 'input_attr' => ''])
            .$tpl->getHtmlFrag('hidden', ['name_attr' => 'fid', 'value_attr' => (string)$fid, 'input_attr' => ''])
            .$tpl->getHtmlFrag('hidden', ['name_attr' => 'pid', 'value_attr' => (string)$pid, 'input_attr' => ''])
            .$tpl->getHtmlFrag('hidden', ['name_attr' => 'cat', 'value_attr' => (string)$catid, 'input_attr' => '']);
        $rows .= $tpl->getHtmlFrag('form-field-row', ['label' => '', 'field_html' => $hide.$tpl->getHtmlFrag('form-submit', ['button_type' => 'submit', 'op' => 'send', 'extra' => '', 'name' => '', 'val' => '', 'select' => true, 'show_preview' => true, 'show_delete' => false, 'label_preview' => _PREVIEW, 'label_save' => _SEND, 'label_delete' => _DELETE, 'label' => _OK])]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'action' => 'index.php?name='.$conf['name'],
            'method' => 'post',
            'form_name' => 'post',
            'form_attr' => 'class="sl-forum-reply-form"',
            'fields' => $rows,
        ]);
    } else {
        $info = ($conf['forum']['add']) ? _NOVIEW : _WARNPF;
        $head = _FORUM.' '.$ctitle.' '.$ctitle;
        setHead(['title' => $head]);
        $cont = $tpl->getHtmlFrag('title', ['title' => $ctitle, 'is_forum_heading' => true]);
        $meta = $tpl->getHtmlFrag('meta-refresh', ['url' => 'index.php?name='.$conf['name'], 'secs' => 5]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $info, 'meta' => $meta]);
    }
    echo $cont;
    setFoot();
}

function tmoder(int $typ): string {
    global $conf, $tpl;
    $mass = [_FMODC => 's0', _FMODCA => 's1', _FMODCR => 's2', _FMODCW => 's3', _FMODCH => 's4', _FMODCO => 's5'];
    $mass = ($typ) ? array_merge($mass, [_DELETE => 'd']) : $mass;
    $opts1 = '';
    foreach ($mass as $vn => $vv) $opts1 .= $tpl->getHtmlFrag('select-option', ['value_attr' => $vv, 'label_text' => $vn, 'is_selected' => false]);
    $opts = $tpl->getHtmlFrag('forum-optgroup', ['label' => _OPMOD, 'is_label' => true, 'options_html' => $opts1]);
    $opts .= $tpl->getHtmlFrag('forum-optgroup', ['label' => _MOVETO, 'is_label' => true, 'options_html' => getTplCategorySelect($conf['name'], 0, '', '', '', '1')]);
    return $tpl->getHtmlFrag('select', ['name_attr' => 'tmove', 'options_html' => $opts, 'select_attr' => 'title="'._CHECKOP.'"']);
}

function pmoder(int|string $status, int $subh): string {
    global $conf, $tpl;
    $mass = ($subh) ? [_CLOSE => 0, _OPEN => 1] : [_FMODC => 0, _FMODCA => 1, _FMODCR => 2, _FMODCW => 3, _FMODCH => 4, _FMODCO => 5];
    $opts = '';
    foreach ($mass as $vn => $vv) {
        $opts .= $tpl->getHtmlFrag('select-option', ['value_attr' => (string)$vv, 'label_text' => $vn, 'is_selected' => $status == $vv]);
    }
    return $tpl->getHtmlFrag('select', ['name_attr' => 'status', 'options_html' => $opts, 'select_attr' => 'title="'._CHECKOP.'"']);
}

function send(): void {
    global $db, $user, $conf, $stop, $tpl;
    $cat = getVar('req', 'cat', 'num');
    $catid = $cat;
    if ($conf['forum']['add'] && $catid) {
        [$ctitle, $authp, $authy, $authe, $authm] = $db->getSqlRow($db->getSqlQuery('SELECT title, ppost, preply, pedit, pmod FROM '.PREFIX_DB.'_categories WHERE id = :catid', ['catid' => $catid]));
        $istopic = is_acess($authp);
        $isreply = is_acess($authy);
        $isedit = is_acess($authe);
        $ismod = is_acess($authm);

        $fid = getVar('post', 'fid', 'num');
        $id = $fid;
        $pid = getVar('post', 'pid', 'num');
        $postname = filterText(substr(getVar('post', 'postname', 'text'), 0, 25));
        $subject = getVar('post', 'subject', 'text');
        $hometext = getVar('post', 'hometext', 'text');

        $checks = str_replace(["\n", "\r", "\t"], ' ', $hometext);
        $words = explode(' ', $checks);
        for ($ix = 0; $ix < count($words); $ix++) $size = strlen($words[$ix]);
        $hometext = filterHtml($hometext);
        $status = getVar('post', 'status', 'num', 0);

        $field = getVar('post', 'field', 'field');
        $time = ($ismod) ? getVar('req', 'time', 'time') : date('Y-m-d H:i:s');
        $postid = (is_user()) ? (int)$user[0] : 0;
        $ip = getIp();
        $fpid = 0;
        $lpid = 0;

        $stop = [];
        if (!$subject) $stop[] = _CERROR;
        if (!$hometext) $stop[] = _CERROR1;
        if ($size > $conf['forum']['letter']) $stop[] = _CERROR2;
        if (!$postname && !is_user()) $stop[] = _CERROR3;

        if (!$stop && getVar('post', 'posttype', 'var') == 'save') {
            $where = (is_moder($conf['name'])) ? 'WHERE id = :pid' : 'WHERE id = :pid AND status != \'0\'';
            [$fstatus] = $db->getSqlRow($db->getSqlQuery('SELECT status FROM '.PREFIX_DB.'_forum '.$where, ['pid' => $pid]));

            if ($id) {
                [$fpid, $uid, $ftime] = $db->getSqlRow($db->getSqlQuery('SELECT pid, uid, time FROM '.PREFIX_DB.'_forum WHERE id = :id', ['id' => $id]));
                $fpid = ($fpid) ? $fpid : $id;
                if ($ismod || ($isedit && $uid == (int)$user[0] && $fstatus > 2)) {
                    $ftime = ($ismod) ? $time : $ftime;
                    if ($ismod) {
                        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_forum SET title = :subject, time = :ftime, body = :body, field = :field, euid = :postid, eip = :ip, etime = NOW(), status = :status WHERE id = :id', ['subject' => $subject, 'ftime' => $ftime, 'body' => $hometext, 'field' => $field, 'postid' => $postid, 'ip' => $ip, 'status' => $status, 'id' => $id]);
                    } else {
                        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_forum SET title = :subject, time = :ftime, body = :body, field = :field, euid = :postid, eip = :ip, etime = NOW() WHERE id = :id', ['subject' => $subject, 'ftime' => $ftime, 'body' => $hometext, 'field' => $field, 'postid' => $postid, 'ip' => $ip, 'id' => $id]);
                    }
                }

            } else {
                if ($ismod) {
                    $userinfo = getUserInfo();
                    $postname = ($userinfo['name']) ? $userinfo['name'] : $postname;
                    $status = ($status) ? $status : (($pid) ? 1 : 3);
                } elseif (is_user()) {
                    $userinfo = getUserInfo();
                    $postname = $userinfo['name'];
                    $status = ($userinfo['access']) ? 0 : (($pid) ? 1 : 3);
                } else {
                    $postid = '';
                    $status = ($conf['forum']['anonpost'] == 1) ? (($pid) ? 1 : 3) : 0;
                }
                $insert = false;

                if ($pid && $isreply) {
                    $insert = (is_moder($conf['name'])) ? true : (($fstatus > 2) ? true : false);

                } elseif ($istopic) {
                    $insert = true;
                }

                if ($insert) {
                    $catids = catids($conf['name'], $catid);
                    $db->getSqlQuery(
                        'INSERT INTO '.PREFIX_DB.'_forum (id, pid, cid, uid, name, title, time, body, field, ip, luid, lname, ltime, status) VALUES (NULL, :pid, :catid, :postid, :postname, :subject, :time, :body, :field, :ip, :luid, :lname, :ltime, :status)',
                        ['pid' => $pid, 'catid' => $catid, 'postid' => $postid, 'postname' => $postname, 'subject' => $subject, 'time' => $time, 'body' => $hometext, 'field' => $field, 'ip' => $ip, 'luid' => $postid, 'lname' => $postname, 'ltime' => $time, 'status' => $status]
                    );
                    [$lpid, $ltime] = $db->getSqlRow($db->getSqlQuery('SELECT id, time FROM '.PREFIX_DB.'_forum WHERE cid = :catid AND uid = :postid ORDER BY id DESC LIMIT 1', ['catid' => $catid, 'postid' => $postid]));
                    if ($pid) {
                        $lname = (isset($uname) && $uname) ? $uname : $postname;
                        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_forum SET comments = comments+1, luid = :postid, lname = :lname, lpost = :lpost, ltime = :time WHERE id = :pid', ['postid' => $postid, 'lname' => $lname, 'lpost' => $lpid, 'time' => $time, 'pid' => $pid]);
                        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET posts = posts+1, lpost = :pid WHERE id IN ('.$catids.')', ['pid' => $pid]);
                        if ($conf['forum']['addmail']) {
                            [$muid] = $db->getSqlRow($db->getSqlQuery('SELECT uid FROM '.PREFIX_DB.'_forum WHERE id = :pid', ['pid' => $pid]));
                            if ($postid != $muid) {
                                [$mail, $fsmail] = $db->getSqlRow($db->getSqlQuery('SELECT email, fsmail FROM '.PREFIX_DB.'_users WHERE id = :muid', ['muid' => $muid]));
                                if ($mail && $fsmail) {
                                    $finurl = $conf['homeurl'].'/index.php?name=forum&amp;op=view&amp;id='.$pid.'#'.$lpid;
                                    $link = $tpl->getHtmlFrag('link', ['href' => $finurl, 'title' => $finurl, 'label' => $finurl]);
                                    $subject = $conf['sitename'].' - '._FORUM;
                                    $message = str_replace('[text]', sprintf(_ADDMAILF, $postname, $link), $conf['mtemp']);
                                    addMail($mail, $conf['adminmail'], $subject, $message, 0, 3);
                                }
                            }
                        }
                        updatePoints(14);
                    } else {
                        if (strtotime($ltime) > time()) {
                            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET topics = topics+1, posts = posts+1 WHERE id IN ('.$catids.')');
                        } else {
                            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET topics = topics+1, posts = posts+1, lpost = :lpost WHERE id IN ('.$catids.')', ['lpost' => $lpid]);
                        }
                        updatePoints(13);
                    }
                }
            }
            $lid = ($fpid) ? $fpid : (($pid) ? $pid.'&last=1#'.$lpid : '');
            $link = ($lid) ? '&op=view&id='.$lid : '&cat='.$catid;
            setRedirect('index.php?name='.$conf['name'].$link);
        } else {
            add();
        }
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function delete(int|string|null $catid = null, int|string|null $id = null): void {
    global $db, $user, $conf;
    $hasargs = ($catid !== null || $id !== null);
    $catid = ($catid !== null && $catid !== '') ? $catid : getVar('req', 'cat', 'num');
    $id = ($id !== null && $id !== '') ? $id : getVar('req', 'id', 'num');
    $lid = 0;
    if ($conf['forum']['add'] && $catid && $id) {
        [$authd, $authm] = $db->getSqlRow($db->getSqlQuery('SELECT pdelete, pmod FROM '.PREFIX_DB.'_categories WHERE id = :catid', ['catid' => $catid]));
        $isdelete = is_acess($authd);
        $ismod = is_acess($authm);

        [$pid, $uid] = $db->getSqlRow($db->getSqlQuery('SELECT pid, uid FROM '.PREFIX_DB.'_forum WHERE id = :id', ['id' => $id]));
        if ($ismod || ($isdelete && $uid == (int)$user[0])) {
            $recycle = (int)$conf['forum']['recycle'];

            if ($recycle && $recycle != $catid) {
                $rcatids = catids($conf['name'], $recycle);
                if ($pid) {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB."_forum SET pid = '0', cid = :recycle WHERE id = :id", ['recycle' => $recycle, 'id' => $id]);
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET topics = topics+1, lpost = :id WHERE id IN ('.$rcatids.')', ['id' => $id]);
                } else {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_forum SET cid = :recycle WHERE id = :id OR pid = :pid', ['recycle' => $recycle, 'id' => $id, 'pid' => $id]);
                    [$rnpost] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_forum WHERE pid = :id', ['id' => $id]));
                    $wrnpost = ($rnpost) ? ', posts=posts+'.$rnpost : '';
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET topics = topics+1'.$wrnpost.', lpost = :id WHERE id IN ('.$rcatids.')', ['id' => $id]);
                }
            }

            $catids = catids($conf['name'], $catid);

            if ($pid) {
                [$lid] = $db->getSqlRow($db->getSqlQuery('SELECT lpost FROM '.PREFIX_DB.'_forum WHERE id = :pid', ['pid' => $pid]));
                if ($lid == $id) {
                    [$lid, $luid, $lname, $ltime] = $db->getSqlRow($db->getSqlQuery('SELECT id, uid, name, time FROM '.PREFIX_DB.'_forum WHERE pid = :pid1 OR id = :pid2 ORDER BY id DESC LIMIT 1', ['pid1' => $pid, 'pid2' => $pid]));
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_forum SET comments = comments-1, luid = :luid, lname = :lname, lpost = :lid, ltime = :ltime WHERE id = :pid', ['luid' => $luid, 'lname' => $lname, 'lid' => $lid, 'ltime' => $ltime, 'pid' => $pid]);
                } else {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_forum SET comments = comments-1 WHERE id = :pid', ['pid' => $pid]);
                }
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET posts = posts-1 WHERE id IN ('.$catids.')');

            } else {
                [$lid] = $db->getSqlRow($db->getSqlQuery('SELECT lpost FROM '.PREFIX_DB.'_categories WHERE id = :catid', ['catid' => $catid]));
                [$npost] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_forum WHERE pid = :id', ['id' => $id]));
                $wnpost = ($npost) ? ', posts=posts-'.$npost : '';
                if ($lid == $id) {
                    [$lid] = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_forum WHERE cid = :catid AND ((pid != '0' && status = '1') || (pid = '0' && status > '1')) ORDER BY id DESC LIMIT 1", ['catid' => $catid]));
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET topics = topics-1'.$wnpost.', lpost = :lid WHERE id IN ('.$catids.')', ['lid' => $lid]);
                } else {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET topics = topics-1'.$wnpost.' WHERE id IN ('.$catids.')');
                }
            }

            if (!$recycle || $recycle == $catid) {
                if ($uid) {
                    if ($pid) {
                        updatePoints(14, $uid, 1);
                    } else {
                        updatePoints(13, $uid, 1);
                    }
                }
                [$fid, $fuid] = $db->getSqlRow($db->getSqlQuery('SELECT id, uid FROM '.PREFIX_DB."_favorites WHERE fid = :id AND modul = 'forum'", ['id' => $id]));
                if ($fid) {
                    if ($fuid) updatePoints(44, $fuid, 1);
                    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE id = :fid', ['fid' => $fid]);
                }
                $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_forum WHERE id = :id1 OR pid = :id2', ['id1' => $id, 'id2' => $id]);
            }

        }

        $lid = ($pid) ? $pid.'&last=1#'.$lid : '';
        $link = ($lid) ? '&op=view&id='.$lid : '&cat='.$catid;
        if (!$hasargs) setRedirect('index.php?name='.$conf['name'].$link);
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch ($op) {
    default: forum(); break;
    case 'view': view(); break;
    case 'move': move(); break;
    case 'add': add(); break;
    case 'send': send(); break;
    case 'delete': delete(); break;
}
