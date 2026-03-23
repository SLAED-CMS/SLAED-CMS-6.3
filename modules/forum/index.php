<?php
# Author: Eduard Laas
# Copyright Â© 2005 - 2026 SLAED
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
                    $cont = (!$cnt) ? setTemplateBasic('forum-cat-wrap', ['if_flag' => ['open' => true], '{%heading%}' => '<a href="index.php?name='.$conf['name'].'" title="'._FORUM.'">'._FORUM.'</a> '.urldecode($conf['forum']['defis']).' <a href="index.php?name='.$mod.'&amp;cat='.$rows[0][0].'" title="'.$rows[0][1].'">'.$rows[0][1].'</a>', '{%col_forum%}' => _FORUM, '{%col_topics%}' => _NEWTOPICS, '{%col_messages%}' => cutstr(_MESSAGES, 5, 1), '{%col_last%}' => _LASTMESSAGE]) : '';
                    $ttit = ($val[2]) ? $val[2] : $val[1];
                    $tlink = ($val[5] || is_moder($conf['name'])) ? '<a href="index.php?name='.$mod.'&amp;cat='.$val[0].'" title="'.$ttit.'">'.$val[1].'</a>' : $val[1];
                    if (!$val[5]) {
                        $imglink = ($val[3]) ? '<img src="'.img_find('categories/'.$val[3]).'" alt="'._FCLOSED.'" title="'._FCLOSED.'" class="sl_hidden">' : '<span title="'._FCLOSED.'" class="sl_f_clos"></span>';
                        $timg = (is_moder($conf['name'])) ? '<a href="index.php?name='.$mod.'&amp;cat='.$val[0].'" title="'._FCLOSED.'">'.$imglink.'</a>' : $imglink;
                    } elseif ($val[21] > $ulast) {
                        $imglink = ($val[3]) ? '<img src="'.img_find('categories/'.$val[3]).'" alt="'._ISNEWPOST.'" title="'._ISNEWPOST.'">' : '<span title="'._ISNEWPOST.'" class="sl_f_new"></span>';
                        $timg = '<a href="index.php?name='.$mod.'&amp;cat='.$val[0].'" title="'._ISNEWPOST.'">'.$imglink.'</a>';
                    } else {
                        $imglink = ($val[3]) ? '<img src="'.img_find('categories/'.$val[3]).'" alt="'._NONEWPOST.'" title="'._NONEWPOST.'" class="sl_hidden">' : '<span title="'._NONEWPOST.'" class="sl_f_old"></span>';
                        $timg = '<a href="index.php?name='.$mod.'&amp;cat='.$val[0].'" title="'._NONEWPOST.'">'.$imglink.'</a>';
                    }
                    if ($val[9]) {
                        $data = _DATE.': '.format_time($val[21], _TIMESTRING);
                        $topic = ($val[5]) ? _TOPIC.': <a href="index.php?name='.$mod.'&amp;op=view&amp;id='.$val[9].'" title="'.$val[17].'">'.cutstr($val[17], 14).'</a>' : _TOPIC.': '.cutstr($val[17], 14);
                        $post = ($val[18]) ? user_info($val[19]) : $val[19];
                        $post = _POSTER.': '.$post;
                        $lid = ($val[20]) ? $val[20] : $val[9];
                        $lpost = ($val[5]) ? '<a href="index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$val[9].'&amp;last=1#'.$lid.'" title="'._LASTMESSAGE.'"><span title="'._LASTMESSAGE.'" class="sl_f_last"></span></a>' : '<span title="'._LASTMESSAGE.'" class="sl_f_last"></span>';
                    } else {
                        $data = _NO_INFO;
                        $topic = $post = $lpost = '';
                    }
                    $cont .= setTemplateBasic('forum-cat-basic', ['{%icon%}' => $timg, '{%link%}' => $tlink, '{%desc%}' => $val[2], '{%topics%}' => $val[7], '{%posts%}' => $val[8], '{%date%}' => $data, '{%last_topic%}' => $topic, '{%last_post%}' => $post, '{%last_link%}' => $lpost]);
                    echo $cont;
                } else {
                    $cont = setTemplateBasic('forum-cat-wrap', ['if_flag' => ['open' => true], '{%heading%}' => '<a href="index.php?name='.$mod.'&amp;cat='.$val[0].'" title="'.$val[1].'">'.$val[1].'</a>', '{%col_forum%}' => _FORUM, '{%col_topics%}' => _NEWTOPICS, '{%col_messages%}' => cutstr(_MESSAGES, 5, 1), '{%col_last%}' => _LASTMESSAGE]);
                    foreach ($rows as $valb) {
                        if ($val[0] == $valb[4] && is_acess($valb[10])) {
                            $ttit = ($valb[2]) ? $valb[2] : $valb[1];
                            $tlink = ($valb[5] || is_moder($conf['name'])) ? '<a href="index.php?name='.$mod.'&amp;cat='.$valb[0].'" title="'.$ttit.'">'.$valb[1].'</a>' : $valb[1];
                            if (!$valb[5]) {
                                $imglink = ($valb[3]) ? '<img src="'.img_find('categories/'.$valb[3]).'" alt="'._FCLOSED.'" title="'._FCLOSED.'" class="sl_hidden">' : '<span title="'._FCLOSED.'" class="sl_f_clos"></span>';
                                $timg = (is_moder($conf['name'])) ? '<a href="index.php?name='.$mod.'&amp;cat='.$valb[0].'" title="'._FCLOSED.'">'.$imglink.'</a>' : $imglink;
                            } elseif ($valb[21] > $ulast) {
                                $imglink = ($valb[3]) ? '<img src="'.img_find('categories/'.$valb[3]).'" alt="'._ISNEWPOST.'" title="'._ISNEWPOST.'">' : '<span title="'._ISNEWPOST.'" class="sl_f_new"></span>';
                                $timg = '<a href="index.php?name='.$mod.'&amp;cat='.$valb[0].'" title="'._ISNEWPOST.'">'.$imglink.'</a>';
                            } else {
                                $imglink = ($valb[3]) ? '<img src="'.img_find('categories/'.$valb[3]).'" alt="'._NONEWPOST.'" title="'._NONEWPOST.'" class="sl_hidden">' : '<span title="'._NONEWPOST.'" class="sl_f_old"></span>';
                                $timg = '<a href="index.php?name='.$mod.'&amp;cat='.$valb[0].'" title="'._NONEWPOST.'">'.$imglink.'</a>';
                            }
                            if ($valb[9]) {
                                $data = _DATE.': '.format_time($valb[21], _TIMESTRING);
                                $topic = ($valb[5]) ? _TOPIC.': <a href="index.php?name='.$mod.'&amp;op=view&amp;id='.$valb[9].'" title="'.$valb[17].'">'.cutstr($valb[17], 14).'</a>' : _TOPIC.': '.cutstr($valb[17], 14);
                                $post = ($valb[18]) ? user_info($valb[19]) : $valb[19];
                                $post = _POSTER.': '.$post;
                                $lid = ($valb[20]) ? $valb[20] : $valb[9];
                                $lpost = ($valb[5]) ? '<a href="index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$valb[9].'&amp;last=1#'.$lid.'" title="'._LASTMESSAGE.'"><span title="'._LASTMESSAGE.'" class="sl_f_last"></span></a>' : '<span title="'._LASTMESSAGE.'" class="sl_f_last"></span>';
                            } else {
                                $data = _NO_INFO;
                                $topic = $post = $lpost = '';
                            }
                            $cont .= setTemplateBasic('forum-cat-basic', ['{%icon%}' => $timg, '{%link%}' => $tlink, '{%desc%}' => $valb[2], '{%topics%}' => $valb[7], '{%posts%}' => $valb[8], '{%date%}' => $data, '{%last_topic%}' => $topic, '{%last_post%}' => $post, '{%last_link%}' => $lpost]);
                        }
                    }
                    $cont .= setTemplateBasic('forum-cat-wrap', []);
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
                    $newbt = ($istopic) ? '<a href="index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$rows[0][0].'" title="'._NEWTOPIC.'" class="sl_but">'._OPEN.'</a>' : '<span title="'.sprintf(_ACINFOT, _NOTCAN).'" class="sl_but sl_hidden">'._OPEN.'</span>';
                    $cont = setTemplateBasic('forum-list-wrap', ['if_flag' => ['open' => true], '{%button%}' => $newbt, '{%cat%}' => '<a href="index.php?name='.$mod.'&amp;cat='.$rows[0][0].'" title="'.$rows[0][1].'">'.$rows[0][1].'</a>']);
                    if ($db->getSqlRowCount($query) > 0) {
                        $mark = 0;
                        $cont .= setTemplateBasic('forum-list-basic-wrap', ['if_flag' => ['open' => true], '{%col_topics%}' => _NEWTOPICS, '{%col_posts%}' => _POSTS, '{%col_poster%}' => _POSTER, '{%col_views%}' => cutstr(_TVIEWS, 5, 1), '{%col_last%}' => _LASTMESSAGE]);
                        $cont .= ($ismod) ? '<form action="index.php?name='.$conf['name'].'" method="post">' : '';
                        while ([$id, $cid, $uname, $title, $time, $hometext, $comments, $counter, $score, $ratings, $ipsend, $luid, $lname, $lid, $ltime, $status, $cat, $ctitle, $cdesc, $cimg, $nick] = $db->getSqlRow($query)) {
                            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title, 'ctitle' => $ctitle]);
                            $view = 0;
                            if (!$status && is_moder($conf['name'])) {
                                $timg = '<a href="'.$thref.'" title="'.$title.'"><span title="'._TOPICM.'" class="sl_t_clos_m"></span></a>';
                                $tlink = '<a href="'.$thref.'" title="'.$title.'">'.$title.'</a>';
                                $lpost = '<a href="'.$thref.'&amp;last=1#'.$lid.'" title="'._LASTMESSAGE.'"><span title="'._LASTMESSAGE.'" class="sl_f_last"></span></a>';
                                $view = 1;
                            } elseif ($status == 1) {
                                if (is_moder($conf['name'])) {
                                    $timg = '<a href="'.$thref.'" title="'.$title.'"><span title="'._TOPICA.'" class="sl_t_clos_a"></span></a>';
                                    $tlink = '<a href="'.$thref.'" title="'.$title.'">'.$title.'</a>';
                                    $lpost = '<a href="'.$thref.'&amp;last=1#'.$lid.'" title="'._LASTMESSAGE.'"><span title="'._LASTMESSAGE.'" class="sl_f_last"></span></a>';
                                } else {
                                    $timg = '<span title="'._TOPICA.'" class="sl_t_clos_a"></span>';
                                    $tlink = $title;
                                    $lpost = '<span title="'._LASTMESSAGE.'" class="sl_f_last"></span>';
                                }
                                $view = 1;
                            } elseif ($status == 2) {
                                $timg = '<a href="'.$thref.'" title="'.$title.'"><span title="'._TOPICN.'" class="sl_t_clos_n"></span></a>';
                                $tlink = '<a href="'.$thref.'" title="'.$title.'">'.$title.'</a>';
                                $lpost = '<a href="'.$thref.'&amp;last=1#'.$lid.'" title="'._LASTMESSAGE.'"><span title="'._LASTMESSAGE.'" class="sl_f_last"></span></a>';
                                $view = 1;
                            } elseif ($status == 3 && $time <= date('Y-m-d H:i:s')) {
                                if ($ltime > $ulast) {
                                    $timg = ($comments > $conf['forum']['pop']) ? '<a href="'.$thref.'" title="'.$title.'"><span title="'._TPOPN.'" class="sl_t_pop"></span></a>' : '<a href="'.$thref.'" title="'.$title.'"><span title="'._ISNEWPOST.'" class="sl_t_new"></span></a>';
                                } else {
                                    $timg = ($comments > $conf['forum']['pop']) ? '<a href="'.$thref.'" title="'.$title.'"><span title="'._TPOP.'" class="sl_t_pold"></span></a>' : '<a href="'.$thref.'" title="'.$title.'"><span title="'._NONEWPOST.'" class="sl_t_old"></span></a>';
                                }
                                $tlink = '<a href="'.$thref.'" title="'.$title.'">'.$title.'</a>';
                                $lpost = '<a href="'.$thref.'&amp;last=1#'.$lid.'" title="'._LASTMESSAGE.'"><span title="'._LASTMESSAGE.'" class="sl_f_last"></span></a>';
                                $view = 1;
                            } elseif ($status == 3 && $time > date('Y-m-d H:i:s') && is_moder($conf['name'])) {
                                $timg = '<a href="'.$thref.'" title="'.$title.'"><span title="'._TOPICP.'" class="sl_t_clos_p"></span></a>';
                                $tlink = '<a href="'.$thref.'" title="'.$title.'">'.$title.'</a>';
                                $lpost = '<a href="'.$thref.'&amp;last=1#'.$lid.'" title="'._LASTMESSAGE.'"><span title="'._LASTMESSAGE.'" class="sl_f_last"></span></a>';
                                $view = 1;
                            } elseif ($status == 4 || $status == 5) {
                                $timg = ($status == 4) ? '<a href="'.$thref.'" title="'.$title.'"><span title="'._THOT.'" class="sl_t_hot"></span></a>' : '<a href="'.$thref.'" title="'.$title.'"><span title="'._TANNOUN.'" class="sl_t_announ"></span></a>';
                                $tlink = '<a href="'.$thref.'" title="'.$title.'">'.$title.'</a>';
                                $lpost = '<a href="'.$thref.'&amp;last=1#'.$lid.'" title="'._LASTMESSAGE.'"><span title="'._LASTMESSAGE.'" class="sl_f_last"></span></a>';
                                $view = 1;
                            }
                            $ldata = _DATE.': '.format_time($ltime, _TIMESTRING);
                            $post = ($nick) ? user_info($nick) : $uname.' ('._ANONYM.')';
                            $lposter = ($luid) ? _POSTER.': '.user_info($lname) : _POSTER.': '.$lname;
                            if ($ismod) {
                                $checkb = (!$mark) ? '<br>'._CHECKALL." <input type=\"checkbox\" name=\"markcheck\" id=\"markcheck\" OnClick=\"CheckBox('#markcheck', '.sl_check')\"> | <input type=\"checkbox\" name=\"id[]\" class=\"sl_check\" value=\"".$id.'">' : ' <input type="checkbox" name="id[]" class="sl_check" value="'.$id.'">';
                                $mark++;
                            } else {
                                $checkb = '';
                            }
                            $cont .= ($view) ? setTemplateBasic('forum-list-basic', ['{%icon%}' => $timg, '{%link%}' => $tlink, '{%replies%}' => $comments, '{%posts%}' => $post, '{%views%}' => $counter, '{%last_date%}' => $ldata, '{%last_poster%}' => $lposter, '{%last_link%}' => $lpost.$checkb]) : '';
                        }
                        $cont .= setTemplateBasic('forum-list-basic-wrap', []);
                        if ($ismod) {
                            $selmm = tmoder(1).'<input type="hidden" name="op" value="move"><input type="hidden" name="cat" value="'.$cat.'"> <input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
                            $cont .= setTemplateBasic('forum-view-change', ['{%title%}' => _CHECKOP, '{%content%}' => $selmm]);
                        }
                    } else {
                        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
                    }
                    $order = (is_moder($conf['name'])) ? "pid = '0' AND cid = '".$cat."'" : "pid = '0' AND cid = '".$cat."' AND time <= NOW() AND status != '0'";
                    $pnum = setArticleNumbers('forum-pagenum', $conf['name'], $listnum, 'cat='.$cat.'&', 'id', '_forum', 'cid', $order, $conf['forum']['pnum']);
                    $cont .= setTemplateBasic('forum-list-wrap', ['{%button%}' => $newbt, '{%pager%}' => $pnum]);
                    $infov = ($isview) ? sprintf(_ACINFOV, '<b>'._ISCAN.'</b>') : sprintf(_ACINFOV, '<b>'._NOTCAN.'</b>');
                    $infor = ($isread) ? sprintf(_ACINFOR, '<b>'._ISCAN.'</b>') : sprintf(_ACINFOR, '<b>'._NOTCAN.'</b>');
                    $infot = ($istopic) ? sprintf(_ACINFOT, '<b>'._ISCAN.'</b>') : sprintf(_ACINFOT, '<b>'._NOTCAN.'</b>');
                    $infop = ($isreply) ? sprintf(_ACINFOP, '<b>'._ISCAN.'</b>') : sprintf(_ACINFOP, '<b>'._NOTCAN.'</b>');
                    $infoe = ($isedit) ? sprintf(_ACINFOE, '<b>'._ISCAN.'</b>') : sprintf(_ACINFOE, '<b>'._NOTCAN.'</b>');
                    $infod = ($isdelete) ? sprintf(_ACINFOD, '<b>'._ISCAN.'</b>') : sprintf(_ACINFOD, '<b>'._NOTCAN.'</b>');
                    $infom = ($ismod) ? sprintf(_ACINFOM, '<b>'._ISCAN.'</b>') : sprintf(_ACINFOM, '<b>'._NOTCAN.'</b>');
                    $cont .= setTemplateBasic('forum-list-info', ['{%new%}' => '<span title="'._ISNEWPOST.'" class="sl_t_new">'._ISNEWPOST.'</span>', '{%old%}' => '<span title="'._NONEWPOST.'" class="sl_t_old">'._NONEWPOST.'</span>', '{%popular_new%}' => '<span title="'._TPOPN.'" class="sl_t_pop">'._TPOPN.'</span>', '{%popular%}' => '<span title="'._TPOP.'" class="sl_t_pold">'._TPOP.'</span>', '{%announce%}' => '<span title="'._TANNOUN.'" class="sl_t_announ">'._TANNOUN.'</span>', '{%hot%}' => '<span title="'._THOT.'" class="sl_t_hot">'._THOT.'</span>', '{%mod%}' => '<span title="'._TOPICM.'" class="sl_t_clos_m">'._TOPICM.'</span>', '{%admin%}' => '<span title="'._TOPICA.'" class="sl_t_clos_a">'._TOPICA.'</span>', '{%closed%}' => '<span title="'._TOPICN.'" class="sl_t_clos_n">'._TOPICN.'</span>', '{%pinned%}' => '<span title="'._TOPICP.'" class="sl_t_clos_p">'._TOPICP.'</span>', '{%perm_view%}' => $infov, '{%perm_read%}' => $infor, '{%perm_topic%}' => $infot, '{%perm_reply%}' => $infop, '{%perm_edit%}' => $infoe, '{%perm_delete%}' => $infod, '{%perm_mod%}' => $infom]);
                } else {
                    $meta = '<meta http-equiv="refresh" content="5; url=index.php?name='.$conf['name'].'">';
                    $cont = $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _NOVIEW, 'meta' => $meta]);
                }
                $show = false;
            } else {
                $cont = setTemplateBasic('forum-cat-wrap', []);
            }
        } else {
            $cont = '';
        }
        if ($show) $cont .= setTemplateBasic('forum-cat-info', ['{%new%}' => '<span title="'._ISNEWPOST.'" class="sl_f_new">'._ISNEWPOST.'</span>', '{%nonew%}' => '<span title="'._NONEWPOST.'" class="sl_f_old">'._NONEWPOST.'</span>', '{%closed%}' => '<span title="'._FCLOSED.'" class="sl_f_clos">'._FCLOSED.'</span>']);
        echo $cont;
    } else {
        setHead(['title' => _FORUM]);
        $meta = '<meta http-equiv="refresh" content="5; url=index.php?name='.$conf['name'].'">';
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
            $atopic = (is_moder($conf['name']) || $istopic) ? '<a href="index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$rows[0][2].'" title="'._NEWTOPIC.'" class="sl_but">'._OPEN.'</a>' : '<span title="'.sprintf(_ACINFOT, _NOTCAN).'" class="sl_but sl_hidden">'._OPEN.'</span>';
            $areply = (is_moder($conf['name']) || ($isreply && $tstatus)) ? '<a href="index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$rows[0][2].'&amp;pid='.$topic.'" title="'._TOPICREPLY.'" class="sl_but">'._REPLY.'</a>' : '<span title="'.sprintf(_ACINFOP, _NOTCAN).'" class="sl_but sl_hidden">'._REPLY.'</span>';
            $pnum = setPageNumbers('forum-pagenum', $conf['name'], $numfor, $numpages, $fornum, 'op=view&id='.$topic.'&', $conf['forum']['pnum'], $num);
            $favor = getFavorBtn($topic, $conf['name']);
            $cont = setTemplateBasic('forum-view-wrap', ['if_flag' => ['open' => true], '{%atopic%}' => $atopic, '{%areply%}' => $areply, '{%title%}' => filterTextHighlight($rows[0][5], $word), '{%favor%}' => $favor]);
            foreach ($rows as $val) {
                $fid = $val[0];
                $fcat = $val[2];
                /*
                $id = $val[0];
                $pid = $val[1];
                $cid = $val[2];
                $uid = $val[3];
                $name = $val[4];
                $title = $val[5];
                $time = $val[6];
                $hometext = $val[7];
                $field = $val[8];
                $comments = $val[9];
                $counter = $val[10];
                $score = $val[11];
                $ratings = $val[12];
                $ipsend = $val[13];
                $euid = $val[14];
                $eip = $val[15];
                $etime = $val[16];
                $status = $val[17];
                */
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
                $date = (($ismod || $conf['forum']['ledit']) && $val[16]) ? '<span title="'._PADD.'" class="sl_t_post">'.format_time($val[6], _TIMESTRING).'</span><span title="'._PEDIT.'" class="sl_t_edit">'.format_time($val[16], _TIMESTRING).'</span>' : '<span title="'._PADD.'" class="sl_t_post">'.format_time($val[6], _TIMESTRING).'</span>';
                $rating = ($pos == 1) ? ajax_rating(1, $fid, $conf['name'], $val[12], $val[11], '', 1) : '';
                $ip = ($ismod && $val[13]) ? user_geo_ip($val[13], 4) : '';
                $amess = '<a href="#'.$fid.'" title="'._MESSAGE.': '.$pos.'" class="sl_pnum">'.$pos.'</a>';
                $rank = (!empty($rank)) ? $rank : '';
                $trank = (!empty($gname)) ? _GROUP.': '.$gname : _RANK;
                $rlink = (!empty($grank) && file_exists(img_find('ranks/'.$grank))) ? '<img src="'.img_find('ranks/'.$grank).'" alt="'.$trank.'" title="'.$trank.'">' : '';
                $rate = (!empty($uid)) ? ajax_rating(0, $uid, 'account', $votes, $total, $fid, 1) : '';
                $rwarn = (!empty($warn)) ? _UWARNS.': '.warnings($warn) : '';
                $group = (!empty($gname)) ? _GROUP.': <span style="color: '.$gcolor.'">'.$gname.'</span>' : '';
                $point = ($conf['users']['point'] && !empty($point)) ? _POINTS.': '.$point : '';
                $regdate = (!empty($reg)) ? _REG.': '.format_time($reg) : _NO_INFO;
                $gender = (!empty($gender)) ? _GENDER.': '.gender($gender) : '';
                $from = (!empty($from)) ? _FROM.': '.$from : '';
                $fields = fields_out($val[8], $conf['name']);
                $sig = (!empty($sig)) ? '<hr>'.$sig : '';
                $personal = (is_moder($conf['name']) || ($isreply && $tstatus && $conf['forum']['qreply'])) ? "<a href=\"javascript: InsertCode('name', '".$avname."', '', '', '1');\" title=\""._PERSONAL.'" class="sl_but_blue">'._PERS.'</a>' : '';
                $privat = ($conf['forum']['privat'] && $conf['privat']['act'] && !empty($nick)) ? '<a href="index.php?name=account&amp;op=privat&amp;uname='.urlencode($nick).'" title="'._SENDMES.'" class="sl_but_green">'._MESSAGE.'</a>' : '';
                $profil = ($conf['forum']['profil'] && !empty($nick)) ? '<a href="index.php?name=account&amp;op=view&amp;uname='.urlencode($nick).'" title="'._PERSONALINFO.'" class="sl_but">'._ACCOUNT.'</a>' : '';
                $web = ($conf['forum']['web'] && !empty($site)) ? '<a href="'.$site.'" target="_blank" title="'._DOWNLLINK.'" class="sl_but">'._SITE.'</a>' : '';
                
                #$warn = "<a href=\"javascript: scroll(0, 0);\" title=\""._WARNM."\">"._WARNM."</a>";
                #$thank = "<a href=\"javascript: scroll(0, 0);\" title=\""._THANK."\">"._THANK."</a>";
                $warn = '';
                $thank = '';
                
                $qreply = (is_moder($conf['name']) || ($isreply && $tstatus)) ? '<a href="index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$fcat.'&amp;pid='.$topic.'&amp;qid='.$fid.'" title="'._QREPLY.'" class="sl_but_blue">'._REPLY.'</a>' : '';
                $edit = ($ismod || ($isedit && $val[3] == intval($user[0]) && $tstatus)) ? "<a href=\"#\" OnClick=\"AjaxLoad('GET', '1', 'for".$fid."', 'go=1&amp;op=editpost&amp;id=".$fid.'&amp;cid='.$fcat.'&amp;typ=1&amp;mod='.$conf['name']."', ''); return false;\" title=\""._ONEDIT.'">'._ONEDIT.'</a>||<a href="index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$fcat.'&amp;id='.$fid.'&amp;pid='.$topic.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||' : '';
                $edit .= ($ismod || ($isdelete && $val[3] == intval($user[0]))) ? '<a href="index.php?name='.$conf['name'].'&amp;op=delete&amp;cat='.$fcat.'&amp;id='.$fid."\" OnClick=\"return DelCheck(this, '"._DELETE.' &quot;'.$val[5]."&quot;?');\" title=\""._ONDELETE.'">'._ONDELETE.'</a>' : '';
                $edit = ($edit) ? add_menu($edit) : '';
                $hclass = (!$val[17]) ? 'title="'._PCLOSED.'" class="sl_hidden"' : '';
                $text = ($fields) ? '<div id="repfor'.$fid.'">'.filterTextHighlight(filterReplaceText(filterMarkdown($val[7], $conf['name'], false), $conf['name']), $word).'</div>'.filterTextHighlight(filterReplaceText(filterMarkdown('<br><br>'.$fields, $conf['name'], false), $conf['name']), $word) : '<div id="repfor'.$fid.'">'.filterTextHighlight(filterReplaceText(filterMarkdown($val[7], $conf['name'], false), $conf['name']), $word).'</div>';
                $cont .= setTemplateBasic('forum-view-basic', ['{%id%}' => $fid, '{%username%}' => $avname, '{%date%}' => $date, '{%rating%}' => $rating, '{%ip%}' => $ip, '{%post_count%}' => $amess, '{%avatar%}' => $avatar, '{%rank%}' => $rank, '{%rank_link%}' => $rlink, '{%user_rate%}' => $rate, '{%warn%}' => $rwarn, '{%group%}' => $group, '{%points%}' => $point, '{%regdate%}' => $regdate, '{%gender%}' => $gender, '{%from%}' => $from, '{%text%}' => $text, '{%sig%}' => filterReplaceText(filterMarkdown($sig, $conf['name'], false), $conf['name']), '{%btn_personal%}' => $personal, '{%btn_pm%}' => $privat, '{%btn_profile%}' => $profil, '{%btn_web%}' => $web, '{%btn_warn%}' => $warn, '{%btn_thank%}' => $thank, '{%btn_reply%}' => $qreply, '{%btn_edit%}' => $edit, '{%hclass%}' => $hclass]);
                if ($conf['forum']['sort']) { $pos++; } else { $pos--; }
            }
            $pnum = setPageNumbers('forum-pagenum', $conf['name'], $numfor, $numpages, $fornum, 'op=view&id='.$topic.'&', $conf['forum']['pnum'], $num);
            $cont .= setTemplateBasic('forum-view-wrap', ['{%atopic%}' => $atopic, '{%areply%}' => $areply, '{%pager%}' => $pnum]);
            if ($ismod) {
                $selmm = '<form action="index.php?name='.$conf['name'].'" method="post">'.tmoder(1).' <input type="hidden" name="op" value="move"><input type="hidden" name="cat" value="'.$rows[0][2].'"><input type="hidden" name="id[]" value="'.$topic.'"> <input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
                $cont .= setTemplateBasic('forum-view-change', ['{%title%}' => _OPMOD.': ', '{%content%}' => $selmm]);
            }
            if (is_moder($conf['name']) || ($isreply && $tstatus)) $cont .= quickreply($topic, $rows[0][2], $rows[0][5]);
        } else {
            $meta = '<meta http-equiv="refresh" content="5; url=index.php?name='.$conf['name'].'">';
            $cont = $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _NOVIEW, 'meta' => $meta]);
        }
        echo $cont;
    setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function quickreply(int|string|null $id, int|string|null $catid, string $subject): string {
    global $conf;
    $id = (int)$id;
    $catid = (int)$catid;
    if ($conf['forum']['qreply'] == 1 && $id > 0 && $catid > 0) {
        $cont = '<form action="index.php?name='.$conf['name'].'" method="post" name="post" enctype="multipart/form-data"><table class="sl_table_form">';
        $cont .= (!is_user()) ? '<tr><td>'._YOURNAME.':</td><td><input type="text" name="postname" value="'._ANONYM.'" class="sl_field '.$conf['style'].'" placeholder="'._YOURNAME.'" required></td></tr>' : '';
        $cont .= '<tr><td>'._TEXT.':</td><td>'.textarea('1', 'hometext', '', $conf['name'], '10', _TEXT, '1').'</td></tr>'
        .fields_in(isset($field), $conf['name'])
        .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="subject" value="'.$subject.'"><input type="hidden" name="pid" value="'.$id.'"><input type="hidden" name="cat" value="'.$catid.'"><input type="hidden" name="posttype" value="save"><input type="hidden" name="op" value="send"><input type="submit" value="'._SEND.'" class="sl_but_blue"></td></tr>'
        .'</table></form>';
        return setTemplateBasic('forum-all-open', ['{%title%}' => _QUICKREPLY]).$cont;
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
        $cont .= setTemplateBasic('forum-all-open', ['{%title%}' => $info]);
        $cont .= '<form action="index.php?name='.$conf['name'].'" method="post" name="post" enctype="multipart/form-data"><table class="sl_table_form">';
        $cont .= (!is_user()) ? '<tr><td>'._YOURNAME.':</td><td><input type="text" name="postname" value="'._ANONYM.'" class="sl_field '.$conf['style'].'" placeholder="'._YOURNAME.'" required></td></tr>' : '';
        $cont .= ($subh) ? '<input type="hidden" name="subject" value="'.$subject.'">' : '<tr><td>'._TITLE.':</td><td><input type="text" name="subject" value="'.$subject.'" maxlength="100" class="sl_field '.$conf['style'].'" placeholder="'._TITLE.'" required></td></tr>';
        $cont .= '<tr><td>'._TEXT.':</td><td>'.textarea('1', 'hometext', $hometext, $conf['name'], '15', _TEXT, '1').'</td></tr>'.fields_in($field, $conf['name']);
        $cont .= ($ismod) ? '<tr><td>'._OPMOD.':</td><td>'.pmoder($status, $subh).'</td></tr><tr><td>'._CHNGSTORY.':</td><td>'.datetime(1, 'time', $time, 16, $conf['style']).'</td></tr>' : '';
        $cont .= '<tr><td colspan="2" class="sl_center">'
        .'<input type="hidden" name="id" value="'.$id.'">'
        .'<input type="hidden" name="fid" value="'.$fid.'">'
        .'<input type="hidden" name="pid" value="'.$pid.'">'
        .'<input type="hidden" name="cat" value="'.$catid.'">'
        .ad_save('', '', 'send').'</td></tr></table></form>';
    } else {
        $info = ($conf['forum']['add']) ? _NOVIEW : _WARNPF;
        $head = _FORUM.' '.$ctitle.' '.$ctitle;
        setHead(['title' => $head]);
        $cont = setTemplateBasic('forum-all-open', ['{%title%}' => $ctitle]);
        $meta = '<meta http-equiv="refresh" content="5; url=index.php?name='.$conf['name'].'">';
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $info, 'meta' => $meta]);
    }
    echo $cont;
    setFoot();
}

function tmoder(int $typ): string {
    global $conf;
    $cont = '<select name="tmove" title="'._CHECKOP.'" class="sl_field '.$conf['style'].'">';
    $cont .= '<optgroup label="'._OPMOD.'" class="sl_label">';
    $mass = [_FMODC => 's0', _FMODCA => 's1', _FMODCR => 's2', _FMODCW => 's3', _FMODCH => 's4', _FMODCO => 's5'];
    $mass = ($typ) ? array_merge($mass, [_DELETE => 'd']) : $mass;
    foreach ($mass as $vn => $vv) $cont .= '<option value="'.$vv.'">'.$vn.'</option>';
    $cont .= '</optgroup><optgroup label="'._MOVETO.'" class="sl_label">'.getcat($conf['name'], 0, '', '', '', '1').'</optgroup>';
    $cont .= '</select>';
    return $cont;
}

function pmoder(int|string $status, int $subh): string {
    global $conf;
    $cont = '<select name="status" title="'._CHECKOP.'" class="sl_field '.$conf['style'].'">';
    $mass = ($subh) ? [_CLOSE => 0, _OPEN => 1] : [_FMODC => 0, _FMODCA => 1, _FMODCR => 2, _FMODCW => 3, _FMODCH => 4, _FMODCO => 5];
    foreach ($mass as $vn => $vv) {
        $sel = ($status == $vv) ? ' selected' : '';
        $cont .= '<option value="'.$vv.'"'.$sel.'>'.$vn.'</option>';
    }
    $cont .= '</select>';
    return $cont;
}

function send(): void {
    global $db, $user, $conf, $stop;
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
                                    $link = '<a href="'.$finurl.'">'.$finurl.'</a>';
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
