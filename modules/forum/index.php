<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
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
        $ulast = (is_array($uinfo) && !empty($uinfo['lastvis'])) ? intval($uinfo['lastvis']) : 0;
        $head = ($id) ? _FORUM.' '.$rows[0][1] : _FORUM;
        setHead(['title' => $head]);
        $cnt = 0;
        foreach ($rows as $val) {
            if ($val[4] == $id && is_acess($val[10])) {
                if ($id) {
                    if (!$cnt) {
                        $h1 = $tpl->getHtmlFrag('breadcrumb-link', ['href' => 'index.php?name='.$conf['name'], 'title' => _FORUM, 'label' => _FORUM]);
                        $h2 = $tpl->getHtmlFrag('breadcrumb-link', ['href' => 'index.php?name='.$mod.'&amp;cat='.$rows[0][0], 'title' => $rows[0][1], 'label' => $rows[0][1]]);
                        $heading = $h1.' '.urldecode($conf['forum']['defis']).' '.$h2;
                        $cont = $tpl->getHtmlFrag('forum-cat-wrap', ['open' => true, 'heading' => $heading, 'col_forum' => _FORUM, 'col_topics' => _NEWTOPICS, 'col_messages' => cutstr(_MESSAGES, 5, 1), 'col_last' => _LASTMESSAGE]);
                    } else {
                        $cont = '';
                    }
                    $ttit = ($val[2]) ? $val[2] : $val[1];
                    $tlink = ($val[5] || is_moder($conf['name'])) ? $tpl->getHtmlFrag('breadcrumb-link', ['href' => 'index.php?name='.$mod.'&amp;cat='.$val[0], 'title' => $ttit, 'label' => $val[1]]) : $val[1];
                    $cat_url = 'index.php?name='.$mod.'&amp;cat='.$val[0];
                    if (!$val[5]) {
                        if ($val[3]) {
                            $imglink = $tpl->getHtmlFrag('forum-status-img', ['src' => img_find('categories/'.$val[3]), 'label' => _FCLOSED, 'cls' => 'sl_hidden']);
                            $timg = (is_moder($conf['name'])) ? $tpl->getHtmlFrag('forum-status-img-link', ['href' => $cat_url, 'title' => _FCLOSED, 'icon_html' => $imglink]) : $imglink;
                        } else {
                            $timg = (is_moder($conf['name'])) ? getTplForumIcon($cat_url, _FCLOSED, 'sl_f_clos') : $tpl->getHtmlFrag('forum-status-icon', ['label' => _FCLOSED, 'class' => 'sl_f_clos']);
                        }
                    } elseif ($val[21] > $ulast) {
                        if ($val[3]) {
                            $imglink = $tpl->getHtmlFrag('forum-status-img', ['src' => img_find('categories/'.$val[3]), 'label' => _ISNEWPOST, 'cls' => '']);
                            $timg = $tpl->getHtmlFrag('forum-status-img-link', ['href' => $cat_url, 'title' => _ISNEWPOST, 'icon_html' => $imglink]);
                        } else {
                            $timg = getTplForumIcon($cat_url, _ISNEWPOST, 'sl_f_new');
                        }
                    } else {
                        if ($val[3]) {
                            $imglink = $tpl->getHtmlFrag('forum-status-img', ['src' => img_find('categories/'.$val[3]), 'label' => _NONEWPOST, 'cls' => 'sl_hidden']);
                            $timg = $tpl->getHtmlFrag('forum-status-img-link', ['href' => $cat_url, 'title' => _NONEWPOST, 'icon_html' => $imglink]);
                        } else {
                            $timg = getTplForumIcon($cat_url, _NONEWPOST, 'sl_f_old');
                        }
                    }
                    if ($val[9]) {
                        $data = _DATE.': '.format_time($val[21], _TIMESTRING);
                        $topic_href = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $val[9], 'title' => $val[17]]);
                        $topic_link = ($val[5]) ? $tpl->getHtmlFrag('breadcrumb-link', ['href' => $topic_href, 'title' => $val[17], 'label' => cutstr($val[17], 14)]) : cutstr($val[17], 14);
                        $topic = _TOPIC.': '.$topic_link;
                        $post = ($val[18]) ? user_info($val[19]) : $val[19];
                        $post = _POSTER.': '.$post;
                        $lid = ($val[20]) ? $val[20] : $val[9];
                        $lpost = ($val[5]) ? getTplForumIcon($topic_href.'&amp;last=1#'.$lid, _LASTMESSAGE, 'sl_f_last') : $tpl->getHtmlFrag('forum-status-icon', ['label' => _LASTMESSAGE, 'class' => 'sl_f_last']);
                    } else {
                        $data = _NO_INFO;
                        $topic = $post = $lpost = '';
                    }
                    $cont .= $tpl->getHtmlFrag('forum-cat-basic', ['icon' => $timg, 'link' => $tlink, 'desc' => $val[2], 'topics' => $val[7], 'posts' => $val[8], 'date' => $data, 'last_topic' => $topic, 'last_post' => $post, 'last_link' => $lpost]);
                    echo $cont;
                } else {
                    $heading = $tpl->getHtmlFrag('breadcrumb-link', ['href' => 'index.php?name='.$mod.'&amp;cat='.$val[0], 'title' => $val[1], 'label' => $val[1]]);
                    $cont = $tpl->getHtmlFrag('forum-cat-wrap', ['open' => true, 'heading' => $heading, 'col_forum' => _FORUM, 'col_topics' => _NEWTOPICS, 'col_messages' => cutstr(_MESSAGES, 5, 1), 'col_last' => _LASTMESSAGE]);
                    foreach ($rows as $valb) {
                        if ($val[0] == $valb[4] && is_acess($valb[10])) {
                            $ttit = ($valb[2]) ? $valb[2] : $valb[1];
                            $tlink = ($valb[5] || is_moder($conf['name'])) ? $tpl->getHtmlFrag('breadcrumb-link', ['href' => 'index.php?name='.$mod.'&amp;cat='.$valb[0], 'title' => $ttit, 'label' => $valb[1]]) : $valb[1];
                            $cat_url = 'index.php?name='.$mod.'&amp;cat='.$valb[0];
                            if (!$valb[5]) {
                                if ($valb[3]) {
                                    $imglink = $tpl->getHtmlFrag('forum-status-img', ['src' => img_find('categories/'.$valb[3]), 'label' => _FCLOSED, 'cls' => 'sl_hidden']);
                                    $timg = (is_moder($conf['name'])) ? $tpl->getHtmlFrag('forum-status-img-link', ['href' => $cat_url, 'title' => _FCLOSED, 'icon_html' => $imglink]) : $imglink;
                                } else {
                                    $timg = (is_moder($conf['name'])) ? getTplForumIcon($cat_url, _FCLOSED, 'sl_f_clos') : $tpl->getHtmlFrag('forum-status-icon', ['label' => _FCLOSED, 'class' => 'sl_f_clos']);
                                }
                            } elseif ($valb[21] > $ulast) {
                                if ($valb[3]) {
                                    $imglink = $tpl->getHtmlFrag('forum-status-img', ['src' => img_find('categories/'.$valb[3]), 'label' => _ISNEWPOST, 'cls' => '']);
                                    $timg = $tpl->getHtmlFrag('forum-status-img-link', ['href' => $cat_url, 'title' => _ISNEWPOST, 'icon_html' => $imglink]);
                                } else {
                                    $timg = getTplForumIcon($cat_url, _ISNEWPOST, 'sl_f_new');
                                }
                            } else {
                                if ($valb[3]) {
                                    $imglink = $tpl->getHtmlFrag('forum-status-img', ['src' => img_find('categories/'.$valb[3]), 'label' => _NONEWPOST, 'cls' => 'sl_hidden']);
                                    $timg = $tpl->getHtmlFrag('forum-status-img-link', ['href' => $cat_url, 'title' => _NONEWPOST, 'icon_html' => $imglink]);
                                } else {
                                    $timg = getTplForumIcon($cat_url, _NONEWPOST, 'sl_f_old');
                                }
                            }
                            if ($valb[9]) {
                                $data = _DATE.': '.format_time($valb[21], _TIMESTRING);
                                $topic_href = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $valb[9], 'title' => $valb[17]]);
                                $topic_link = ($valb[5]) ? $tpl->getHtmlFrag('breadcrumb-link', ['href' => $topic_href, 'title' => $valb[17], 'label' => cutstr($valb[17], 14)]) : cutstr($valb[17], 14);
                                $topic = _TOPIC.': '.$topic_link;
                                $post = ($valb[18]) ? user_info($valb[19]) : $valb[19];
                                $post = _POSTER.': '.$post;
                                $lid = ($valb[20]) ? $valb[20] : $valb[9];
                                $lpost = ($valb[5]) ? getTplForumIcon($topic_href.'&amp;last=1#'.$lid, _LASTMESSAGE, 'sl_f_last') : $tpl->getHtmlFrag('forum-status-icon', ['label' => _LASTMESSAGE, 'class' => 'sl_f_last']);
                            } else {
                                $data = _NO_INFO;
                                $topic = $post = $lpost = '';
                            }
                            $cont .= $tpl->getHtmlFrag('forum-cat-basic', ['icon' => $timg, 'link' => $tlink, 'desc' => $valb[2], 'topics' => $valb[7], 'posts' => $valb[8], 'date' => $data, 'last_topic' => $topic, 'last_post' => $post, 'last_link' => $lpost]);
                        }
                    }
                    $cont .= $tpl->getHtmlFrag('forum-cat-wrap', []);
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
                    $cat = intval($id);
                    $lang = ($conf['multilingual']) ? 'AND (c.lang = :locale OR c.lang = \'\') AND s.cid = :cat' : 'AND s.cid = :cat';
                    $lpars = ['cat' => $cat];
                    if ($conf['multilingual']) {
                        $lpars['locale'] = $locale;
                    }
                    $listnum = intval($conf['forum']['listnum']);
                    $ordern = (is_moder($conf['name'])) ? "WHERE s.pid = '0'" : "WHERE s.pid = '0' AND s.time <= NOW() AND s.status != '0'";
                    $num = getVar('req', 'num', 'num') ?: 1;
                    $offset = ($num-1) * $listnum;
                    $offset = intval($offset);
                    $query = $db->getSqlQuery('SELECT s.id, s.cid, s.name, s.title, s.time, s.body, s.comments, s.counter, s.score, s.ratings, s.ip, s.luid, s.lname, s.lpost, s.ltime, s.status, c.id, c.title, c.intro, c.img, u.name FROM '.PREFIX_DB.'_forum AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.id) '.$ordern.' '.$lang.' ORDER BY s.status DESC, s.ltime DESC LIMIT '.$offset.', '.$listnum, $lpars);
                    $newbt = ($istopic)
                        ? $tpl->getHtmlFrag('link-btn', ['href' => 'index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$rows[0][0], 'title' => _NEWTOPIC, 'class' => 'sl_but', 'label' => _OPEN])
                        : $tpl->getHtmlFrag('span-btn', ['title' => sprintf(_ACINFOT, _NOTCAN), 'class' => 'sl_but sl_hidden', 'label' => _OPEN]);
                    $cat_link = $tpl->getHtmlFrag('breadcrumb-link', ['href' => 'index.php?name='.$mod.'&amp;cat='.$rows[0][0], 'title' => $rows[0][1], 'label' => $rows[0][1]]);
                    $cont = $tpl->getHtmlFrag('forum-list-wrap', ['open' => true, 'button' => $newbt, 'cat' => $cat_link]);
                    if ($db->getSqlRowCount($query) > 0) {
                        $mark = 0;
                        $cont .= $tpl->getHtmlFrag('forum-list-basic-wrap', ['open' => true, 'col_topics' => _NEWTOPICS, 'col_posts' => _POSTS, 'col_poster' => _POSTER, 'col_views' => cutstr(_TVIEWS, 5, 1), 'col_last' => _LASTMESSAGE]);
                        $cont .= ($ismod) ? $tpl->getHtmlFrag('forum-mod-form-open', ['action' => 'index.php?name='.$conf['name']]) : '';
                        while ([$id, $cid, $uname, $title, $time, $hometext, $comments, $counter, $score, $ratings, $ipsend, $luid, $lname, $lid, $ltime, $status, $cat, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($query)) {
                            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]);
                            $view = 0;
                            if (!$status && is_moder($conf['name'])) {
                                $timg = getTplForumIcon($thref, $title, 'sl_t_clos_m', _TOPICM);
                                $tlink = $tpl->getHtmlFrag('breadcrumb-link', ['href' => $thref, 'title' => $title, 'label' => $title]);
                                $lpost = getTplForumIcon($thref.'&amp;last=1#'.$lid, _LASTMESSAGE, 'sl_f_last');
                                $view = 1;
                            } elseif ($status == 1) {
                                if (is_moder($conf['name'])) {
                                    $timg = getTplForumIcon($thref, $title, 'sl_t_clos_a', _TOPICA);
                                    $tlink = $tpl->getHtmlFrag('breadcrumb-link', ['href' => $thref, 'title' => $title, 'label' => $title]);
                                    $lpost = getTplForumIcon($thref.'&amp;last=1#'.$lid, _LASTMESSAGE, 'sl_f_last');
                                } else {
                                    $timg = $tpl->getHtmlFrag('forum-status-icon', ['label' => _TOPICA, 'class' => 'sl_t_clos_a']);
                                    $tlink = $title;
                                    $lpost = $tpl->getHtmlFrag('forum-status-icon', ['label' => _LASTMESSAGE, 'class' => 'sl_f_last']);
                                }
                                $view = 1;
                            } elseif ($status == 2) {
                                $timg = getTplForumIcon($thref, $title, 'sl_t_clos_n', _TOPICN);
                                $tlink = $tpl->getHtmlFrag('breadcrumb-link', ['href' => $thref, 'title' => $title, 'label' => $title]);
                                $lpost = getTplForumIcon($thref.'&amp;last=1#'.$lid, _LASTMESSAGE, 'sl_f_last');
                                $view = 1;
                            } elseif ($status == 3 && $time <= date('Y-m-d H:i:s')) {
                                if ($ltime > $ulast) {
                                    $timg = ($comments > $conf['forum']['pop']) ? getTplForumIcon($thref, $title, 'sl_t_pop', _TPOPN) : getTplForumIcon($thref, $title, 'sl_t_new', _ISNEWPOST);
                                } else {
                                    $timg = ($comments > $conf['forum']['pop']) ? getTplForumIcon($thref, $title, 'sl_t_pold', _TPOP) : getTplForumIcon($thref, $title, 'sl_t_old', _NONEWPOST);
                                }
                                $tlink = $tpl->getHtmlFrag('breadcrumb-link', ['href' => $thref, 'title' => $title, 'label' => $title]);
                                $lpost = getTplForumIcon($thref.'&amp;last=1#'.$lid, _LASTMESSAGE, 'sl_f_last');
                                $view = 1;
                            } elseif ($status == 3 && $time > date('Y-m-d H:i:s') && is_moder($conf['name'])) {
                                $timg = getTplForumIcon($thref, $title, 'sl_t_clos_p', _TOPICP);
                                $tlink = $tpl->getHtmlFrag('breadcrumb-link', ['href' => $thref, 'title' => $title, 'label' => $title]);
                                $lpost = getTplForumIcon($thref.'&amp;last=1#'.$lid, _LASTMESSAGE, 'sl_f_last');
                                $view = 1;
                            } elseif ($status == 4 || $status == 5) {
                                $timg = ($status == 4) ? getTplForumIcon($thref, $title, 'sl_t_hot', _THOT) : getTplForumIcon($thref, $title, 'sl_t_announ', _TANNOUN);
                                $tlink = $tpl->getHtmlFrag('breadcrumb-link', ['href' => $thref, 'title' => $title, 'label' => $title]);
                                $lpost = getTplForumIcon($thref.'&amp;last=1#'.$lid, _LASTMESSAGE, 'sl_f_last');
                                $view = 1;
                            }
                            $ldata = _DATE.': '.format_time($ltime, _TIMESTRING);
                            $post = ($nick) ? user_info($nick) : $uname.' ('._ANONYM.')';
                            $lposter = ($luid) ? _POSTER.': '.user_info($lname) : _POSTER.': '.$lname;
                            if ($ismod) {
                                $checkb = (!$mark)
                                    ? $tpl->getHtmlFrag('forum-mod-checkall', ['all_label' => _CHECKALL, 'id' => (string)$id])
                                    : $tpl->getHtmlFrag('forum-mod-check', ['id' => (string)$id]);
                                $mark++;
                            } else {
                                $checkb = '';
                            }
                            $cont .= ($view) ? $tpl->getHtmlFrag('forum-list-basic', ['icon' => $timg, 'link' => $tlink, 'replies' => $comments, 'posts' => $post, 'views' => $counter, 'last_date' => $ldata, 'last_poster' => $lposter, 'last_link' => $lpost.$checkb]) : '';
                        }
                        $cont .= $tpl->getHtmlFrag('forum-list-basic-wrap', []);
                        if ($ismod) {
                            $selmm = tmoder(1)
                                .getTplHiddenInput('op', 'move')
                                .getTplHiddenInput('cat', (string)$cat)
                                .$tpl->getHtmlFrag('form-submit-btn', ['label' => _OK, 'class' => 'sl_but_blue'])
                                .'</form>';
                            $cont .= $tpl->getHtmlFrag('forum-view-change', ['title' => _CHECKOP, 'content' => $selmm]);
                        }
                    } else {
                        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
                    }
                    $order = (is_moder($conf['name'])) ? "pid = '0' AND cid = '".$cat."'" : "pid = '0' AND cid = '".$cat."' AND time <= NOW() AND status != '0'";
                    $pnum = setArticleNumbers('forum-pagenum', $conf['name'], $listnum, 'cat='.$cat.'&', 'id', '_forum', 'cid', $order, $conf['forum']['pnum']);
                    $cont .= $tpl->getHtmlFrag('forum-list-wrap', ['button' => $newbt, 'pager' => $pnum]);
                    $b_can = $tpl->getHtmlFrag('bold-text', ['text' => _ISCAN]);
                    $b_not = $tpl->getHtmlFrag('bold-text', ['text' => _NOTCAN]);
                    $infov = ($isview) ? sprintf(_ACINFOV, $b_can) : sprintf(_ACINFOV, $b_not);
                    $infor = ($isread) ? sprintf(_ACINFOR, $b_can) : sprintf(_ACINFOR, $b_not);
                    $infot = ($istopic) ? sprintf(_ACINFOT, $b_can) : sprintf(_ACINFOT, $b_not);
                    $infop = ($isreply) ? sprintf(_ACINFOP, $b_can) : sprintf(_ACINFOP, $b_not);
                    $infoe = ($isedit) ? sprintf(_ACINFOE, $b_can) : sprintf(_ACINFOE, $b_not);
                    $infod = ($isdelete) ? sprintf(_ACINFOD, $b_can) : sprintf(_ACINFOD, $b_not);
                    $infom = ($ismod) ? sprintf(_ACINFOM, $b_can) : sprintf(_ACINFOM, $b_not);
                    $cont .= $tpl->getHtmlFrag('forum-list-info', [
                        'new'         => $tpl->getHtmlFrag('span-btn', ['title' => _ISNEWPOST, 'class' => 'sl_t_new', 'label' => _ISNEWPOST]),
                        'old'         => $tpl->getHtmlFrag('span-btn', ['title' => _NONEWPOST, 'class' => 'sl_t_old', 'label' => _NONEWPOST]),
                        'popular_new' => $tpl->getHtmlFrag('span-btn', ['title' => _TPOPN, 'class' => 'sl_t_pop', 'label' => _TPOPN]),
                        'popular'     => $tpl->getHtmlFrag('span-btn', ['title' => _TPOP, 'class' => 'sl_t_pold', 'label' => _TPOP]),
                        'announce'    => $tpl->getHtmlFrag('span-btn', ['title' => _TANNOUN, 'class' => 'sl_t_announ', 'label' => _TANNOUN]),
                        'hot'         => $tpl->getHtmlFrag('span-btn', ['title' => _THOT, 'class' => 'sl_t_hot', 'label' => _THOT]),
                        'mod'         => $tpl->getHtmlFrag('span-btn', ['title' => _TOPICM, 'class' => 'sl_t_clos_m', 'label' => _TOPICM]),
                        'admin'       => $tpl->getHtmlFrag('span-btn', ['title' => _TOPICA, 'class' => 'sl_t_clos_a', 'label' => _TOPICA]),
                        'closed'      => $tpl->getHtmlFrag('span-btn', ['title' => _TOPICN, 'class' => 'sl_t_clos_n', 'label' => _TOPICN]),
                        'pinned'      => $tpl->getHtmlFrag('span-btn', ['title' => _TOPICP, 'class' => 'sl_t_clos_p', 'label' => _TOPICP]),
                        'perm_view'   => $infov,
                        'perm_read'   => $infor,
                        'perm_topic'  => $infot,
                        'perm_reply'  => $infop,
                        'perm_edit'   => $infoe,
                        'perm_delete' => $infod,
                        'perm_mod'    => $infom,
                    ]);
                } else {
                    $meta = getTplMetaRefresh('index.php?name='.$conf['name'], 5);
                    $cont = $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _NOVIEW, 'meta' => $meta]);
                }
                $show = false;
            } else {
                $cont = $tpl->getHtmlFrag('forum-cat-wrap', []);
            }
        } else {
            $cont = '';
        }
        if ($show) $cont .= $tpl->getHtmlFrag('forum-cat-info', [
            'new'    => $tpl->getHtmlFrag('span-btn', ['title' => _ISNEWPOST, 'class' => 'sl_f_new', 'label' => _ISNEWPOST]),
            'nonew'  => $tpl->getHtmlFrag('span-btn', ['title' => _NONEWPOST, 'class' => 'sl_f_old', 'label' => _NONEWPOST]),
            'closed' => $tpl->getHtmlFrag('span-btn', ['title' => _FCLOSED, 'class' => 'sl_f_clos', 'label' => _FCLOSED]),
        ]);
        echo $cont;
    } else {
        setHead(['title' => _FORUM]);
        $meta = getTplMetaRefresh('index.php?name='.$conf['name'], 5);
        echo $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _NO_INFO, 'meta' => $meta]);
    }
    setFoot();
}

function view(): void {
    global $db, $user, $conf, $tpl;
    $rows = [];
    $where = [];
    $users = [];
    $topic = getVar('req', 'id', 'num');
    $last = (filter_input(INPUT_GET, 'last', FILTER_DEFAULT) !== null) ? 1 : 0;
    $ordern = (is_moder($conf['name'])) ? 'WHERE (id = :id1 OR pid = :id2)' : "WHERE (id = :id1 OR pid = :id2) AND time <= NOW() AND status != '0'";
    $opars = ['id1' => $topic, 'id2' => $topic];
    [$numfor] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_forum '.$ordern, $opars));
    if ($topic && $numfor > 0) {
        $fornum = getUserNews($conf['forum']['num']);
        $numpages = ceil($numfor / $fornum);
        $num = getVar('req', 'num', 'num') ?: 1;
        $num = ($last && $conf['forum']['sort']) ? $numpages : $num;
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
        $isread = is_acess($rows[0][19]);
        $istopic = is_acess($rows[0][20]);
        $isreply = is_acess($rows[0][21]);
        $isedit = is_acess($rows[0][22]);
        $isdelete = is_acess($rows[0][23]);
        $ismod = is_acess($rows[0][24]);
        $seodesc = cutstr(trim(strip_tags(filterReplaceText(filterMarkdown($rows[0][7], $conf['name'], false), $conf['name']))), 160);
        $seoimg = getImgText($rows[0][7], '', false);
        $seoimg = $seoimg ? $conf['homeurl'].'/'.$seoimg : '';
        setHead([
            'title' => $rows[0][5],
            'ctitle' => $rows[0][18],
            'desc' => $seodesc,
            'img' => $seoimg,
            'time' => $rows[0][6],
            'author' => $rows[0][4] ?: $conf['sitename'],
        ]);
        if ($ismod || ($isread && $tstatus > 1)) {
            $atopic = (is_moder($conf['name']) || $istopic)
                ? $tpl->getHtmlFrag('link-btn', ['href' => 'index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$rows[0][2], 'title' => _NEWTOPIC, 'class' => 'sl_but', 'label' => _OPEN])
                : $tpl->getHtmlFrag('span-btn', ['title' => sprintf(_ACINFOT, _NOTCAN), 'class' => 'sl_but sl_hidden', 'label' => _OPEN]);
            $areply = (is_moder($conf['name']) || ($isreply && $tstatus))
                ? $tpl->getHtmlFrag('link-btn', ['href' => 'index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$rows[0][2].'&amp;pid='.$topic, 'title' => _TOPICREPLY, 'class' => 'sl_but', 'label' => _REPLY])
                : $tpl->getHtmlFrag('span-btn', ['title' => sprintf(_ACINFOP, _NOTCAN), 'class' => 'sl_but sl_hidden', 'label' => _REPLY]);
            $pnum = setPageNumbers('forum-pagenum', $conf['name'], $numfor, $numpages, $fornum, 'op=view&id='.$topic.'&', $conf['forum']['pnum'], $num);
            $favor = getFavoriteButton($topic, $conf['name']);
            $cont = $tpl->getHtmlFrag('forum-view-wrap', ['open' => true, 'atopic' => $atopic, 'areply' => $areply, 'title' => filterTextHighlight($rows[0][5], $word), 'favor' => $favor]);
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
                $avname = (!empty($nick)) ? $nick : $val[4].' ('._ANONYM.')';
                $avatar = (!empty($nick)) ? (($avatar && file_exists($conf['users']['adirectory'].'/'.$avatar)) ? $conf['users']['adirectory'].'/'.$avatar : $conf['users']['adirectory'].'/default/00.gif') : $conf['users']['adirectory'].'/default/0.gif';
                $date = $tpl->getHtmlFrag('span-btn', ['title' => _PADD, 'class' => 'sl_t_post', 'label' => format_time($val[6], _TIMESTRING)]);
                if (($ismod || $conf['forum']['ledit']) && $val[16]) {
                    $date .= $tpl->getHtmlFrag('span-btn', ['title' => _PEDIT, 'class' => 'sl_t_edit', 'label' => format_time($val[16], _TIMESTRING)]);
                }
                $rating = ($pos == 1) ? getRatingAsync(1, $fid, $conf['name'], $val[12], $val[11], '', 1) : '';
                $ip = ($ismod && $val[13]) ? user_geo_ip($val[13], 4) : '';
                $amess = $tpl->getHtmlFrag('forum-post-anchor', ['id' => (string)$fid, 'title' => _MESSAGE.': '.$pos, 'pos' => (string)$pos]);
                $rank = (!empty($rank)) ? $rank : '';
                $trank = (!empty($gname)) ? _GROUP.': '.$gname : _RANK;
                $rlink = (!empty($grank) && file_exists(img_find('ranks/'.$grank))) ? $tpl->getHtmlFrag('forum-rank-img', ['src' => img_find('ranks/'.$grank), 'title' => $trank]) : '';
                $rate = (!empty($uid)) ? getRatingAsync(0, $uid, 'account', $votes, $total, $fid, 1) : '';
                $rwarn = (!empty($warn)) ? _UWARNS.': '.warnings($warn) : '';
                $group = (!empty($gname)) ? $tpl->getHtmlFrag('forum-group-span', ['label' => _GROUP, 'color' => $gcolor, 'name' => $gname]) : '';
                $point = ($conf['users']['point'] && !empty($point)) ? _POINTS.': '.$point : '';
                $regdate = (!empty($reg)) ? _REG.': '.format_time($reg) : _NO_INFO;
                $gender = (!empty($gender)) ? _GENDER.': '.gender($gender) : '';
                $from = (!empty($from)) ? _FROM.': '.$from : '';
                $fields = fields_out($val[8], $conf['name']);
                $sig = (!empty($sig)) ? $tpl->getHtmlFrag('forum-sig', ['content' => $sig]) : '';
                $personal = (is_moder($conf['name']) || ($isreply && $tstatus && $conf['forum']['qreply']))
                    ? $tpl->getHtmlFrag('link-btn', ['href' => "javascript: InsertCode('name', '".$avname."', '', '', '1');", 'title' => _PERSONAL, 'class' => 'sl_but_blue', 'label' => _PERS])
                    : '';
                $privat = ($conf['forum']['privat'] && $conf['privat']['act'] && !empty($nick))
                    ? $tpl->getHtmlFrag('link-btn', ['href' => 'index.php?name=account&amp;op=privat&amp;uname='.urlencode($nick), 'title' => _SENDMES, 'class' => 'sl_but_green', 'label' => _MESSAGE])
                    : '';
                $profil = ($conf['forum']['profil'] && !empty($nick))
                    ? $tpl->getHtmlFrag('link-btn', ['href' => 'index.php?name=account&amp;op=view&amp;uname='.urlencode($nick), 'title' => _PERSONALINFO, 'class' => 'sl_but', 'label' => _ACCOUNT])
                    : '';
                $web = ($conf['forum']['web'] && !empty($site))
                    ? $tpl->getHtmlFrag('link-btn-blank', ['href' => $site, 'title' => _DOWNLLINK, 'class' => 'sl_but', 'label' => _SITE])
                    : '';

                #$warn = "<a href=\"javascript: scroll(0, 0);\" title=\""._WARNM."\">"._WARNM."</a>";
                #$thank = "<a href=\"javascript: scroll(0, 0);\" title=\""._THANK."\">"._THANK."</a>";
                $warn = '';
                $thank = '';

                $qreply = (is_moder($conf['name']) || ($isreply && $tstatus))
                    ? $tpl->getHtmlFrag('link-btn', ['href' => 'index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$fcat.'&amp;pid='.$topic.'&amp;qid='.$fid, 'title' => _QREPLY, 'class' => 'sl_but_blue', 'label' => _REPLY])
                    : '';
                $edit_href = 'index.php?go=1&amp;op=updatePost&amp;id='.$fid.'&amp;cid='.$fcat.'&amp;typ=1&amp;mod='.$conf['name'];
                $edit = ($ismod || ($isedit && $val[3] == intval($user[0]) && $tstatus))
                    ? $tpl->getHtmlFrag('forum-htmx-edit-link', ['href' => $edit_href, 'target' => '#repfor'.$fid, 'title' => _ONEDIT, 'label' => _ONEDIT])
                      .'||'
                      .$tpl->getHtmlFrag('breadcrumb-link', ['href' => 'index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$fcat.'&amp;id='.$fid.'&amp;pid='.$topic, 'title' => _FULLEDIT, 'label' => _FULLEDIT])
                      .'||'
                    : '';
                $edit .= ($ismod || ($isdelete && $val[3] == intval($user[0])))
                    ? $tpl->getHtmlFrag('forum-delete-link', [
                        'href'    => 'index.php?name='.$conf['name'].'&amp;op=delete&amp;cat='.$fcat.'&amp;id='.$fid,
                        'confirm' => _DELETE.' &quot;'.$val[5].'&quot;?',
                        'title'   => _ONDELETE,
                        'label'   => _ONDELETE,
                      ])
                    : '';
                $edit = ($edit) ? add_menu($edit) : '';
                $hclass = (!$val[17]) ? 'title="'._PCLOSED.'" class="sl_hidden"' : '';
                $body_html = filterTextHighlight(filterReplaceText(filterMarkdown($val[7], $conf['name'], false), $conf['name']), $word);
                $text = $tpl->getHtmlFrag('forum-post-div', ['id' => (string)$fid, 'content' => $body_html]);
                if ($fields) $text .= filterTextHighlight(filterReplaceText(filterMarkdown("\n\n".$fields, $conf['name'], false), $conf['name']), $word);
                $cont .= $tpl->getHtmlFrag('forum-view-basic', ['id' => $fid, 'username' => $avname, 'date' => $date, 'rating' => $rating, 'ip' => $ip, 'post_count' => $amess, 'avatar' => $avatar, 'rank' => $rank, 'rank_link' => $rlink, 'user_rate' => $rate, 'warn' => $rwarn, 'group' => $group, 'points' => $point, 'regdate' => $regdate, 'gender' => $gender, 'from' => $from, 'text' => $text, 'sig' => filterReplaceText(filterMarkdown($sig, $conf['name'], false), $conf['name']), 'btn_personal' => $personal, 'btn_pm' => $privat, 'btn_profile' => $profil, 'btn_web' => $web, 'btn_warn' => $warn, 'btn_thank' => $thank, 'btn_reply' => $qreply, 'btn_edit' => $edit, 'hclass' => $hclass]);
                if ($conf['forum']['sort']) { $pos++; } else { $pos--; }
            }
            $pnum = setPageNumbers('forum-pagenum', $conf['name'], $numfor, $numpages, $fornum, 'op=view&id='.$topic.'&', $conf['forum']['pnum'], $num);
            $cont .= $tpl->getHtmlFrag('forum-view-wrap', ['atopic' => $atopic, 'areply' => $areply, 'pager' => $pnum]);
            if ($ismod) {
                $selmm = $tpl->getHtmlFrag('forum-mod-form-open', ['action' => 'index.php?name='.$conf['name']])
                    .tmoder(1)
                    .getTplFormSubmit('move', _OK, getTplHiddenInput('cat', (string)$rows[0][2]).getTplHiddenInput('id[]', (string)$topic))
                    .'</form>';
                $cont .= $tpl->getHtmlFrag('forum-view-change', ['title' => _OPMOD.': ', 'content' => $selmm]);
            }
            if (is_moder($conf['name']) || ($isreply && $tstatus)) $cont .= quickreply($topic, $rows[0][2], $rows[0][5]);
        } else {
            $meta = getTplMetaRefresh('index.php?name='.$conf['name'], 5);
            $cont = $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _NOVIEW, 'meta' => $meta]);
        }
        echo $cont;
    setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function quickreply(int|string|null $id, int|string|null $catid, string $subject): string {
    global $conf, $tpl;
    $id = (int)$id;
    $catid = (int)$catid;
    if ($conf['forum']['qreply'] == 1 && $id > 0 && $catid > 0) {
        $rows = (!is_user()) ? getTplFormAddRow(_YOURNAME, getTplTextInput('postname', _ANONYM, 'sl_field '.$conf['style'], 'placeholder="'._YOURNAME.'" required')) : '';
        $rows .= getTplFormAddRow(_TEXT, textarea('1', 'hometext', '', $conf['name'], '10', _TEXT, '1'));
        $rows .= fields_in(isset($field), $conf['name']);
        $hide = getTplHiddenInput('subject', $subject).getTplHiddenInput('pid', (string)$id).getTplHiddenInput('cat', (string)$catid).getTplHiddenInput('posttype', 'save');
        $rows .= getTplFormCenterRow(getTplFormSubmit('send', _SEND, $hide));
        $cont = getTplForumReplyForm($conf['name'], $rows);
        return $tpl->getHtmlFrag('forum-all-open', ['title' => _QUICKREPLY]).$cont;
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
        $id = getVar('post', 'id', 'array', []);
        $tmove = getVar('post', 'tmove', 'text');
        $move = (is_numeric($tmove[0])) ? intval($tmove) : intval(substr($tmove, 1));
        if ($ismod && is_array($id) && $tmove[0]) {
            foreach ($id as $val) {
                if (intval($val)) {
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
        if ($ismod || ($isedit && $uid == intval($user[0]) && $fstatus > 2)) {
            $subh = ($qpid) ? 1 : 0;
            $info = _EDITS.': '.$subject;
            $head = $conf['defis'].' '._FORUM.' '.$conf['defis'].' '.$ctitle.' '.$conf['defis'].' '.$info;
            $form = true;
        }
        $subold = $subject;
        $subject = getVar('post', 'subject', 'text');
        $subject = ($subject) ? filterHtml($subject, 1) : $subold;
        $txtold = $hometext;
        $hometext = getVar('post', 'hometext', 'text');
        $hometext = ($hometext) ? filterHtml($hometext) : $txtold;

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

        $subject = getVar('post', 'subject', 'text');
        $subject = (!empty($ftitle)) ? $ftitle : ($subject ? filterHtml($subject, 1) : '');
        $hometext = getVar('post', 'hometext', 'text');
        $hometext = ($qid && $ftext) ? '[quote]'.$ftext.'[/quote]' : ($hometext ? filterHtml($hometext) : '');
        $field = getVar('post', 'field', 'field');
        $status = getVar('post', 'status', 'num', 3);
        $time = getVar('req', 'time', 'time');
        $info = (!empty($ftext)) ? _PUBLICIN.': '.$ftitle : _PUBLICIN.': '.$ctitle;
        $head = _FORUM.' '.$ctitle.' '.$info;

    }
    if ($form) {
        setHead(['title' => $head]);
        $cont = ($stop) ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]) : '';
        $psubject = (!$subh) ? $subject : '';
        if ($hometext) $cont .= preview($psubject, $hometext, '', $field, $conf['name']);
        $userinfo = getUserInfo();
        if ($userinfo['access'] || (!is_user() && !$conf['forum']['anonpost'])) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _POSTNOTE]);
        $cont .= $tpl->getHtmlFrag('forum-all-open', ['title' => $info]);
        $rows = (!is_user()) ? getTplFormAddRow(_YOURNAME, getTplTextInput('postname', _ANONYM, 'sl_field '.$conf['style'], 'placeholder="'._YOURNAME.'" required')) : '';
        $rows .= ($subh) ? getTplHiddenInput('subject', $subject) : getTplFormAddRow(_TITLE, getTplTextInput('subject', $subject, 'sl_field '.$conf['style'], 'maxlength="100" placeholder="'._TITLE.'" required'));
        $rows .= getTplFormAddRow(_TEXT, textarea('1', 'hometext', $hometext, $conf['name'], '15', _TEXT, '1'));
        $rows .= fields_in($field, $conf['name']);
        $rows .= ($ismod) ? getTplFormAddRow(_OPMOD, pmoder($status, $subh)).getTplFormAddRow(_CHNGSTORY, datetime(1, 'time', $time, 16, $conf['style'])) : '';
        $hide = getTplHiddenInput('id', (string)$id).getTplHiddenInput('fid', (string)$fid).getTplHiddenInput('pid', (string)$pid).getTplHiddenInput('cat', (string)$catid);
        $rows .= getTplFormCenterRow($hide.ad_save('', '', 'send'));
        $cont .= getTplForumReplyForm($conf['name'], $rows);
    } else {
        $info = ($conf['forum']['add']) ? _NOVIEW : _WARNPF;
        $head = _FORUM.' '.$ctitle.' '.$ctitle;
        setHead(['title' => $head]);
        $cont = $tpl->getHtmlFrag('forum-all-open', ['title' => $ctitle]);
        $meta = getTplMetaRefresh('index.php?name='.$conf['name'], 5);
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
    foreach ($mass as $vn => $vv) $opts1 .= getTplSelectOption($vv, $vn);
    $opts = $tpl->getHtmlFrag('forum-optgroup', ['label' => _OPMOD, 'class' => 'sl_label', 'options_html' => $opts1]);
    $opts .= $tpl->getHtmlFrag('forum-optgroup', ['label' => _MOVETO, 'class' => 'sl_label', 'options_html' => getcat($conf['name'], 0, '', '', '', '1')]);
    return getTplFormSelect('tmove', $opts, 'sl_field '.$conf['style'], 'title="'._CHECKOP.'"');
}

function pmoder(int|string $status, int $subh): string {
    global $conf, $tpl;
    $mass = ($subh) ? [_CLOSE => 0, _OPEN => 1] : [_FMODC => 0, _FMODCA => 1, _FMODCR => 2, _FMODCW => 3, _FMODCH => 4, _FMODCO => 5];
    $opts = '';
    foreach ($mass as $vn => $vv) {
        $opts .= getTplSelectOption((string)$vv, $vn, $status == $vv);
    }
    return getTplFormSelect('status', $opts, 'sl_field '.$conf['style'], 'title="'._CHECKOP.'"');
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
        $postid = (is_user()) ? intval($user[0]) : 0;
        $ip = getIp();
        $fpid = 0;
        $lpid = 0;

        $stop = [];
        if (!$subject) $stop[] = _CERROR;
        if (!$hometext) $stop[] = _CERROR1;
        if ($size > $conf['forum']['letter']) $stop[] = _CERROR2;
        if (!$postname && !is_user()) $stop[] = _CERROR3;

        if (!$stop && getVar('post', 'posttype', 'text') == 'save') {
            $where = (is_moder($conf['name'])) ? 'WHERE id = :pid' : 'WHERE id = :pid AND status != \'0\'';
            [$fstatus] = $db->getSqlRow($db->getSqlQuery('SELECT status FROM '.PREFIX_DB.'_forum '.$where, ['pid' => $pid]));

            if ($id) {
                [$fpid, $uid, $ftime] = $db->getSqlRow($db->getSqlQuery('SELECT pid, uid, time FROM '.PREFIX_DB.'_forum WHERE id = :id', ['id' => $id]));
                $fpid = ($fpid) ? $fpid : $id;
                if ($ismod || ($isedit && $uid == intval($user[0]) && $fstatus > 2)) {
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
                    $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_forum (id, pid, cid, uid, name, title, time, body, field, ip, luid, lname, ltime, status) VALUES (NULL, :pid, :catid, :postid, :postname, :subject, :time, :body, :field, :ip, :luid, :lname, :ltime, :status)', ['pid' => $pid, 'catid' => $catid, 'postid' => $postid, 'postname' => $postname, 'subject' => $subject, 'time' => $time, 'body' => $hometext, 'field' => $field, 'ip' => $ip, 'luid' => $postid, 'lname' => $postname, 'ltime' => $time, 'status' => $status]);
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
                                    $link = $tpl->getHtmlFrag('breadcrumb-link', ['href' => $finurl, 'title' => $finurl, 'label' => $finurl]);
                                    $subject = $conf['sitename'].' - '._FORUM;
                                    $message = str_replace('[text]', sprintf(_ADDMAILF, $postname, $link), $conf['mtemp']);
                                    addMail($mail, $conf['adminmail'], $subject, $message, 0, 3);
                                }
                            }
                        }
                        update_points(14);
                    } else {
                        if (strtotime($ltime) > time()) {
                            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET topics = topics+1, posts = posts+1 WHERE id IN ('.$catids.')');
                        } else {
                            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET topics = topics+1, posts = posts+1, lpost = :lpost WHERE id IN ('.$catids.')', ['lpost' => $lpid]);
                        }
                        update_points(13);
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
        if ($ismod || ($isdelete && $uid == intval($user[0]))) {
            $recycle = intval($conf['forum']['recycle']);

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
                        update_points(14, $uid, 1);
                    } else {
                        update_points(13, $uid, 1);
                    }
                }
                [$fid, $fuid] = $db->getSqlRow($db->getSqlQuery('SELECT id, uid FROM '.PREFIX_DB."_favorites WHERE fid = :id AND modul = 'forum'", ['id' => $id]));
                if ($fid) {
                    if ($fuid) update_points(44, $fuid, 1);
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

switch($op) {
    default: forum(); break;
    case 'view': view(); break;
    case'move': move(); break;
    case 'add': add(); break;
    case 'send': send(); break;
    case 'delete': delete(); break;
}
