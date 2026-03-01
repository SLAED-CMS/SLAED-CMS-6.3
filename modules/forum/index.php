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
    global $db, $conf, $locale;
    $massiv = [];
    $mod = ($conf['name']) ? analyze($conf['name']) : 0;
    $cat = getVar('req', 'cat', 'num');
    $id = $cat;
    $params = ['mod' => $mod];
    if ($id) {
        $where = 'WHERE c.modul = :mod AND (c.parentid = :parentid OR c.id = :cid)';
        $params['parentid'] = $id;
        $params['cid'] = $id;
        if ($conf['multilingual']) {
            $where .= ' AND (c.language = :locale OR c.language = \'\')';
            $params['locale'] = $locale;
        }
    } elseif ($conf['multilingual']) {
        $where = 'WHERE c.modul = :mod AND (c.language = :locale OR c.language = \'\')';
        $params['locale'] = $locale;
    } else {
        $where = 'WHERE c.modul = :mod';
    }
    $result = $db->sql_query('SELECT c.id, c.title, c.description, c.img, c.parentid, c.cstatus, c.ordern, c.topics, c.posts, c.lpost_id, c.auth_view, c.auth_read, c.auth_post, c.auth_reply, c.auth_edit, c.auth_delete, c.auth_mod, f.title, f.l_uid, f.l_name, f.l_id, f.l_time FROM '.PREFIX_DB.'_categories AS c LEFT JOIN '.PREFIX_DB.'_forum AS f ON (c.lpost_id = f.id) '.$where.' ORDER BY c.ordern', $params);
    while ([$cid, $title, $description, $img, $parentid, $status, $ordern, $topics, $posts, $lpid, $authv, $authr, $authp, $authy, $authe, $authd, $authm, $ftitle, $fuid, $fname, $flid, $fltime] = $db->sql_fetchrow($result)) {
        $massiv[] = [$cid, $title, $description, $img, $parentid, $status, $ordern, $topics, $posts, $lpid, $authv, $authr, $authp, $authy, $authe, $authd, $authm, $ftitle, $fuid, $fname, $flid, $fltime];
        unset($cid, $title, $description, $img, $parentid, $status, $ordern, $topics, $posts, $lpid, $authv, $authr, $authp, $authy, $authe, $authd, $authm, $ftitle, $fuid, $fname, $flid, $fltime);
    }
    if ($massiv) {
        $isview = is_acess($massiv[0][10]);
        $isread = is_acess($massiv[0][11]);
        $istopic = is_acess($massiv[0][12]);
        $isreply = is_acess($massiv[0][13]);
        $isedit = is_acess($massiv[0][14]);
        $isdelete = is_acess($massiv[0][15]);
        $ismod = is_acess($massiv[0][16]);
        $userinfo = getusrinfo();
        $ulastvisit = (is_array($userinfo) && !empty($userinfo['user_lastvisit'])) ? intval($userinfo['user_lastvisit']) : 0;
        $pagetitle = ($id) ? _FORUM.' '.$massiv[0][1] : _FORUM;
        setHead(['title' => $pagetitle]);
        $a = 0;
        foreach ($massiv as $val) {
            if ($val[4] == $id && is_acess($val[10])) {
                if ($id) {
                    $cont = (!$a) ? setTemplateBasic('forum-cat-open', ['{%heading%}' => '<a href="index.php?name='.$conf['name'].'" title="'._FORUM.'">'._FORUM.'</a> '.urldecode($conf['forum']['defis']).' <a href="index.php?name='.$mod.'&amp;cat='.$massiv[0][0].'" title="'.$massiv[0][1].'">'.$massiv[0][1].'</a>', '{%col_forum%}' => _FORUM, '{%col_topics%}' => _NEWTOPICS, '{%col_messages%}' => cutstr(_MESSAGES, 5, 1), '{%col_last%}' => _LASTMESSAGE]) : '';
                    $ttitle= ($val[2]) ? $val[2] : $val[1];
                    $tlink = ($val[5] || is_moder($conf['name'])) ? '<a href="index.php?name='.$mod.'&amp;cat='.$val[0].'" title="'.$ttitle.'">'.$val[1].'</a>' : $val[1];
                    if (!$val[5]) {
                        $imglink = ($val[3]) ? '<img src="'.img_find('categories/'.$val[3]).'" alt="'._FCLOSED.'" title="'._FCLOSED.'" class="sl_hidden">' : '<span title="'._FCLOSED.'" class="sl_f_clos"></span>';
                        $timg = (is_moder($conf['name'])) ? '<a href="index.php?name='.$mod.'&amp;cat='.$val[0].'" title="'._FCLOSED.'">'.$imglink.'</a>' : $imglink;
                    } elseif ($val[21] > $ulastvisit) {
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
                        $lpost = ($val[5]) ? '<a href="index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$val[9].'&amp;last#'.$lid.'" title="'._LASTMESSAGE.'"><span title="'._LASTMESSAGE.'" class="sl_f_last"></span></a>' : '<span title="'._LASTMESSAGE.'" class="sl_f_last"></span>';
                    } else {
                        $data = _NO_INFO;
                        $topic = $post = $lpost = '';
                    }
                    $cont .= setTemplateBasic('forum-cat-basic', ['{%icon%}' => $timg, '{%link%}' => $tlink, '{%desc%}' => $val[2], '{%topics%}' => $val[7], '{%posts%}' => $val[8], '{%date%}' => $data, '{%last_topic%}' => $topic, '{%last_post%}' => $post, '{%last_link%}' => $lpost]);
                    echo $cont;
                } else {
                    $cont = setTemplateBasic('forum-cat-open', ['{%heading%}' => '<a href="index.php?name='.$mod.'&amp;cat='.$val[0].'" title="'.$val[1].'">'.$val[1].'</a>', '{%col_forum%}' => _FORUM, '{%col_topics%}' => _NEWTOPICS, '{%col_messages%}' => cutstr(_MESSAGES, 5, 1), '{%col_last%}' => _LASTMESSAGE]);
                    foreach ($massiv as $val2) {
                        if ($val[0] == $val2[4] && is_acess($val2[10])) {
                            $ttitle= ($val2[2]) ? $val2[2] : $val2[1];
                            $tlink = ($val2[5] || is_moder($conf['name'])) ? '<a href="index.php?name='.$mod.'&amp;cat='.$val2[0].'" title="'.$ttitle.'">'.$val2[1].'</a>' : $val2[1];
                            if (!$val2[5]) {
                                $imglink = ($val2[3]) ? '<img src="'.img_find('categories/'.$val2[3]).'" alt="'._FCLOSED.'" title="'._FCLOSED.'" class="sl_hidden">' : '<span title="'._FCLOSED.'" class="sl_f_clos"></span>';
                                $timg = (is_moder($conf['name'])) ? '<a href="index.php?name='.$mod.'&amp;cat='.$val2[0].'" title="'._FCLOSED.'">'.$imglink.'</a>' : $imglink;
                            } elseif ($val2[21] > $ulastvisit) {
                                $imglink = ($val2[3]) ? '<img src="'.img_find('categories/'.$val2[3]).'" alt="'._ISNEWPOST.'" title="'._ISNEWPOST.'">' : '<span title="'._ISNEWPOST.'" class="sl_f_new"></span>';
                                $timg = '<a href="index.php?name='.$mod.'&amp;cat='.$val2[0].'" title="'._ISNEWPOST.'">'.$imglink.'</a>';
                            } else {
                                $imglink = ($val2[3]) ? '<img src="'.img_find('categories/'.$val2[3]).'" alt="'._NONEWPOST.'" title="'._NONEWPOST.'" class="sl_hidden">' : '<span title="'._NONEWPOST.'" class="sl_f_old"></span>';
                                $timg = '<a href="index.php?name='.$mod.'&amp;cat='.$val2[0].'" title="'._NONEWPOST.'">'.$imglink.'</a>';
                            }
                            if ($val2[9]) {
                                $data = _DATE.': '.format_time($val2[21], _TIMESTRING);
                                $topic = ($val2[5]) ? _TOPIC.': <a href="index.php?name='.$mod.'&amp;op=view&amp;id='.$val2[9].'" title="'.$val2[17].'">'.cutstr($val2[17], 14).'</a>' : _TOPIC.': '.cutstr($val2[17], 14);
                                $post = ($val2[18]) ? user_info($val2[19]) : $val2[19];
                                $post = _POSTER.': '.$post;
                                $lid = ($val2[20]) ? $val2[20] : $val2[9];
                                $lpost = ($val2[5]) ? '<a href="index.php?name='.$conf['name'].'&amp;op=view&amp;id='.$val2[9].'&amp;last#'.$lid.'" title="'._LASTMESSAGE.'"><span title="'._LASTMESSAGE.'" class="sl_f_last"></span></a>' : '<span title="'._LASTMESSAGE.'" class="sl_f_last"></span>';
                            } else {
                                $data = _NO_INFO;
                                $topic = $post = $lpost = '';
                            }
                            $cont .= setTemplateBasic('forum-cat-basic', ['{%icon%}' => $timg, '{%link%}' => $tlink, '{%desc%}' => $val2[2], '{%topics%}' => $val2[7], '{%posts%}' => $val2[8], '{%date%}' => $data, '{%last_topic%}' => $topic, '{%last_post%}' => $post, '{%last_link%}' => $lpost]);
                        }
                    }
                    $cont .= setTemplateBasic('forum-cat-close', []);
                    echo $cont;
                }
                $a++;
            }
        }
        $teml = true;
        unset($cont);
        if ($id) {
            if (!$a) {
                if ($isview) {
                    $cat = intval($id);
                    $lang = ($conf['multilingual']) ? 'AND (c.language = :locale OR c.language = \'\') AND s.catid = :cat' : 'AND s.catid = :cat';
                    $listparams = ['cat' => $cat];
                    if ($conf['multilingual']) {
                        $listparams['locale'] = $locale;
                    }
                    $listnum = intval($conf['forum']['listnum']);
                    $ordern = (is_moder($conf['name'])) ? "WHERE s.pid = '0'" : "WHERE s.pid = '0' AND s.time <= NOW() AND s.status != '0'";
                    $num = getVar('req', 'num', 'num') ?: 1;
                    $offset = ($num-1) * $listnum;
                    $offset = intval($offset);
                    $result = $db->sql_query('SELECT s.id, s.catid, s.name, s.title, s.time, s.hometext, s.comments, s.counter, s.score, s.ratings, s.ip_send, s.l_uid, s.l_name, s.l_id, s.l_time, s.status, c.id, c.title, c.description, c.img, u.user_name FROM '.PREFIX_DB.'_forum AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.catid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.user_id) '.$ordern.' '.$lang.' ORDER BY s.status DESC, s.l_time DESC LIMIT '.$offset.', '.$listnum, $listparams);
                    $newtop = ($istopic) ? '<a href="index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$massiv[0][0].'" title="'._NEWTOPIC.'" class="sl_but">'._OPEN.'</a>' : '<span title="'.sprintf(_ACINFOT, _NOTCAN).'" class="sl_but sl_hidden">'._OPEN.'</span>';
                    $cont = setTemplateBasic('forum-list-open', ['{%button%}' => $newtop, '{%cat%}' => '<a href="index.php?name='.$mod.'&amp;cat='.$massiv[0][0].'" title="'.$massiv[0][1].'">'.$massiv[0][1].'</a>']);
                    if ($db->sql_numrows($result) > 0) {
                        $b = 0;
                        $cont .= setTemplateBasic('forum-list-basic-open', ['{%col_topics%}' => _NEWTOPICS, '{%col_posts%}' => _POSTS, '{%col_poster%}' => _POSTER, '{%col_views%}' => cutstr(_TVIEWS, 5, 1), '{%col_last%}' => _LASTMESSAGE]);
                        $cont .= ($ismod) ? '<form action="index.php?name='.$conf['name'].'" method="post">' : '';
                        while ([$sid, $catid, $uname, $title, $time, $hometext, $comments, $counter, $score, $ratings, $ipsend, $luid, $lname, $lid, $ltime, $status, $cid, $ctitle, $cdesc, $cimg, $nick] = $db->sql_fetchrow($result)) {
                            $thref = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $sid, 'title' => $title, 'ctitle' => $ctitle]);
                            $view = 0;
                            if (!$status && is_moder($conf['name'])) {
                                $timg = '<a href="'.$thref.'" title="'.$title.'"><span title="'._TOPICM.'" class="sl_t_clos_m"></span></a>';
                                $tlink = '<a href="'.$thref.'" title="'.$title.'">'.$title.'</a>';
                                $lpost = '<a href="'.$thref.'&amp;last#'.$lid.'" title="'._LASTMESSAGE.'"><span title="'._LASTMESSAGE.'" class="sl_f_last"></span></a>';
                                $view = 1;
                            } elseif ($status == 1) {
                                if (is_moder($conf['name'])) {
                                    $timg = '<a href="'.$thref.'" title="'.$title.'"><span title="'._TOPICA.'" class="sl_t_clos_a"></span></a>';
                                    $tlink = '<a href="'.$thref.'" title="'.$title.'">'.$title.'</a>';
                                    $lpost = '<a href="'.$thref.'&amp;last#'.$lid.'" title="'._LASTMESSAGE.'"><span title="'._LASTMESSAGE.'" class="sl_f_last"></span></a>';
                                } else {
                                    $timg = '<span title="'._TOPICA.'" class="sl_t_clos_a"></span>';
                                    $tlink = $title;
                                    $lpost = '<span title="'._LASTMESSAGE.'" class="sl_f_last"></span>';
                                }
                                $view = 1;
                            } elseif ($status == 2) {
                                $timg = '<a href="'.$thref.'" title="'.$title.'"><span title="'._TOPICN.'" class="sl_t_clos_n"></span></a>';
                                $tlink = '<a href="'.$thref.'" title="'.$title.'">'.$title.'</a>';
                                $lpost = '<a href="'.$thref.'&amp;last#'.$lid.'" title="'._LASTMESSAGE.'"><span title="'._LASTMESSAGE.'" class="sl_f_last"></span></a>';
                                $view = 1;
                            } elseif ($status == 3 && $time <= date('Y-m-d H:i:s')) {
                                if ($ltime > $ulastvisit) {
                                    $timg = ($comments > $conf['forum']['pop']) ? '<a href="'.$thref.'" title="'.$title.'"><span title="'._TPOPN.'" class="sl_t_pop"></span></a>' : '<a href="'.$thref.'" title="'.$title.'"><span title="'._ISNEWPOST.'" class="sl_t_new"></span></a>';
                                } else {
                                    $timg = ($comments > $conf['forum']['pop']) ? '<a href="'.$thref.'" title="'.$title.'"><span title="'._TPOP.'" class="sl_t_pold"></span></a>' : '<a href="'.$thref.'" title="'.$title.'"><span title="'._NONEWPOST.'" class="sl_t_old"></span></a>';
                                }
                                $tlink = '<a href="'.$thref.'" title="'.$title.'">'.$title.'</a>';
                                $lpost = '<a href="'.$thref.'&amp;last#'.$lid.'" title="'._LASTMESSAGE.'"><span title="'._LASTMESSAGE.'" class="sl_f_last"></span></a>';
                                $view = 1;
                            } elseif ($status == 3 && $time > date('Y-m-d H:i:s') && is_moder($conf['name'])) {
                                $timg = '<a href="'.$thref.'" title="'.$title.'"><span title="'._TOPICP.'" class="sl_t_clos_p"></span></a>';
                                $tlink = '<a href="'.$thref.'" title="'.$title.'">'.$title.'</a>';
                                $lpost = '<a href="'.$thref.'&amp;last#'.$lid.'" title="'._LASTMESSAGE.'"><span title="'._LASTMESSAGE.'" class="sl_f_last"></span></a>';
                                $view = 1;
                            } elseif ($status == 4 || $status == 5) {
                                $timg = ($status == 4) ? '<a href="'.$thref.'" title="'.$title.'"><span title="'._THOT.'" class="sl_t_hot"></span></a>' : '<a href="'.$thref.'" title="'.$title.'"><span title="'._TANNOUN.'" class="sl_t_announ"></span></a>';
                                $tlink = '<a href="'.$thref.'" title="'.$title.'">'.$title.'</a>';
                                $lpost = '<a href="'.$thref.'&amp;last#'.$lid.'" title="'._LASTMESSAGE.'"><span title="'._LASTMESSAGE.'" class="sl_f_last"></span></a>';
                                $view = 1;
                            }
                            $ldata = _DATE.': '.format_time($ltime, _TIMESTRING);
                            $post = ($nick) ? user_info($nick) : $uname.' ('._ANONYM.')';
                            $lposter = ($luid) ? _POSTER.': '.user_info($lname) : _POSTER.': '.$lname;
                            if ($ismod) {
                                $checkb = (!$b) ? '<br>'._CHECKALL." <input type=\"checkbox\" name=\"markcheck\" id=\"markcheck\" OnClick=\"CheckBox('#markcheck', '.sl_check')\"> | <input type=\"checkbox\" name=\"id[]\" class=\"sl_check\" value=\"".$sid.'">' : ' <input type="checkbox" name="id[]" class="sl_check" value="'.$sid.'">';
                                $b++;
                            } else {
                                $checkb = '';
                            }
                            $cont .= ($view) ? setTemplateBasic('forum-list-basic', ['{%icon%}' => $timg, '{%link%}' => $tlink, '{%replies%}' => $comments, '{%posts%}' => $post, '{%views%}' => $counter, '{%last_date%}' => $ldata, '{%last_poster%}' => $lposter, '{%last_link%}' => $lpost.$checkb]) : '';
                        }
                        $cont .= setTemplateBasic('forum-list-basic-close', []);
                        if ($ismod) {
                            $selmm = tmoder(1).'<input type="hidden" name="op" value="move"><input type="hidden" name="cat" value="'.$cat.'"> <input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
                            $cont .= setTemplateBasic('forum-view-change', ['{%title%}' => _CHECKOP, '{%content%}' => $selmm]);
                        }
                    } else {
                        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
                    }
                    $ordernum = (is_moder($conf['name'])) ? "pid = '0' AND catid = '".$cat."'" : "pid = '0' AND catid = '".$cat."' AND time <= NOW() AND status != '0'";
                    $pnum = setArticleNumbers('forum-pagenum', $conf['name'], $listnum, 'cat='.$cat.'&', 'id', '_forum', 'catid', $ordernum, $conf['forum']['pnum']);
                    $cont .= setTemplateBasic('forum-list-close', ['{%button%}' => $newtop, '{%pager%}' => $pnum]);
                    $infov = ($isview) ? sprintf(_ACINFOV, '<b>'._ISCAN.'</b>') : sprintf(_ACINFOV, '<b>'._NOTCAN.'</b>');
                    $infor = ($isread) ? sprintf(_ACINFOR, '<b>'._ISCAN.'</b>') : sprintf(_ACINFOR, '<b>'._NOTCAN.'</b>');
                    $infot = ($istopic) ? sprintf(_ACINFOT, '<b>'._ISCAN.'</b>') : sprintf(_ACINFOT, '<b>'._NOTCAN.'</b>');
                    $infop = ($isreply) ? sprintf(_ACINFOP, '<b>'._ISCAN.'</b>') : sprintf(_ACINFOP, '<b>'._NOTCAN.'</b>');
                    $infoe = ($isedit) ? sprintf(_ACINFOE, '<b>'._ISCAN.'</b>') : sprintf(_ACINFOE, '<b>'._NOTCAN.'</b>');
                    $infod = ($isdelete) ? sprintf(_ACINFOD, '<b>'._ISCAN.'</b>') : sprintf(_ACINFOD, '<b>'._NOTCAN.'</b>');
                    $infom = ($ismod) ? sprintf(_ACINFOM, '<b>'._ISCAN.'</b>') : sprintf(_ACINFOM, '<b>'._NOTCAN.'</b>');
                    $cont .= setTemplateBasic('forum-list-info', ['{%new%}' => '<span title="'._ISNEWPOST.'" class="sl_t_new">'._ISNEWPOST.'</span>', '{%old%}' => '<span title="'._NONEWPOST.'" class="sl_t_old">'._NONEWPOST.'</span>', '{%popular_new%}' => '<span title="'._TPOPN.'" class="sl_t_pop">'._TPOPN.'</span>', '{%popular%}' => '<span title="'._TPOP.'" class="sl_t_pold">'._TPOP.'</span>', '{%announce%}' => '<span title="'._TANNOUN.'" class="sl_t_announ">'._TANNOUN.'</span>', '{%hot%}' => '<span title="'._THOT.'" class="sl_t_hot">'._THOT.'</span>', '{%mod%}' => '<span title="'._TOPICM.'" class="sl_t_clos_m">'._TOPICM.'</span>', '{%admin%}' => '<span title="'._TOPICA.'" class="sl_t_clos_a">'._TOPICA.'</span>', '{%closed%}' => '<span title="'._TOPICN.'" class="sl_t_clos_n">'._TOPICN.'</span>', '{%pinned%}' => '<span title="'._TOPICP.'" class="sl_t_clos_p">'._TOPICP.'</span>', '{%perm_view%}' => $infov, '{%perm_read%}' => $infor, '{%perm_topic%}' => $infot, '{%perm_reply%}' => $infop, '{%perm_edit%}' => $infoe, '{%perm_delete%}' => $infod, '{%perm_mod%}' => $infom]);
                } else {
                    $cont = setTemplateWarning('warn', ['text' => _NOVIEW, 'url' => '?name='.$conf['name'], 'time' => 5, 'id' => 'warn']);
                }
                $teml = false;
            } else {
                $cont = setTemplateBasic('forum-cat-close', []);
            }
        } else {
            $cont = '';
        }
        if ($teml) $cont .= setTemplateBasic('forum-cat-info', ['{%new%}' => '<span title="'._ISNEWPOST.'" class="sl_f_new">'._ISNEWPOST.'</span>', '{%nonew%}' => '<span title="'._NONEWPOST.'" class="sl_f_old">'._NONEWPOST.'</span>', '{%closed%}' => '<span title="'._FCLOSED.'" class="sl_f_clos">'._FCLOSED.'</span>']);
        echo $cont;
    } else {
        setHead(['title' => _FORUM]);
        echo setTemplateWarning('warn', ['time' => '5', 'url' => '?name='.$conf['name'], 'id' => 'warn', 'text' => _NO_INFO]);
    }
    setFoot();
}

function view(): void {
    global $db, $user, $conf;
    $cmassiv = [];
    $where = [];
    $umassiv = [];
    $id = getVar('req', 'id', 'num');
    $last = getVar('get', 'last', 'num') ? 1 : 0;
    $ordern = (is_moder($conf['name'])) ? 'WHERE (id = :id1 OR pid = :id2)' : "WHERE (id = :id1 OR pid = :id2) AND time <= NOW() AND status != '0'";
    $orderparams = ['id1' => $id, 'id2' => $id];
    [$numfor] = $db->sql_fetchrow($db->sql_query('SELECT COUNT(id) FROM '.PREFIX_DB.'_forum '.$ordern, $orderparams));
    if ($id && $numfor > 0) {
        $fornum = user_news($user[3] ?? 0, $conf['forum']['num']);
        $numpages = ceil($numfor / $fornum);
        $num = getVar('req', 'num', 'num') ?: 1;
        $num = ($last && $conf['forum']['sort']) ? $numpages : $num;
        $offset = ($num-1) * $fornum;
        if ($conf['forum']['sort']) {
            $sort = 'ASC';
            $a = ($num) ? $offset+1 : 1;
        } else {
            $sort = 'DESC';
            $a = $numfor;
            if ($numfor > $offset) $a -= $offset;
        }
        $word = getVar('req', 'word', 'word');
        $orderw = (is_moder($conf['name'])) ? 'WHERE (s.id = :id1 OR s.pid = :id2)' : "WHERE (s.id = :id1 OR s.pid = :id2) AND s.time <= NOW() AND s.status != '0'";
        $result = $db->sql_query('SELECT s.id, s.pid, s.catid, s.uid, s.name, s.title, s.time, s.hometext, s.field, s.comments, s.counter, s.score, s.ratings, s.ip_send, s.e_uid, s.e_ip_send, s.e_time, s.status, c.title, c.auth_read, c.auth_post, c.auth_reply, c.auth_edit, c.auth_delete, c.auth_mod FROM '.PREFIX_DB.'_forum AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.catid=c.id) '.$orderw.' ORDER BY s.time '.$sort.' LIMIT '.$offset.', '.$fornum, $orderparams);
        $db->sql_query('UPDATE '.PREFIX_DB.'_forum SET counter=counter+1 WHERE id = :id', ['id' => $id]);
        while ([$sid, $pid, $catid, $uid, $name, $title, $time, $hometext, $field, $comments, $counter, $score, $ratings, $ipsend, $euid, $eip, $etime, $status, $ctitle, $authr, $authp, $authy, $authe, $authd, $authm] = $db->sql_fetchrow($result)) {
            $cmassiv[] = [$sid, $pid, $catid, $uid, $name, $title, $time, $hometext, $field, $comments, $counter, $score, $ratings, $ipsend, $euid, $eip, $etime, $status, $ctitle, $authr, $authp, $authy, $authe, $authd, $authm];
            if ($uid) $where[] = $uid;
            unset($sid, $pid, $catid, $uid, $name, $title, $time, $hometext, $field, $comments, $counter, $score, $ratings, $ipsend, $euid, $eip, $etime, $status, $ctitle, $authr, $authp, $authy, $authe, $authd, $authm);
        }
        if ($where) {
            $result2 = $db->sql_query('SELECT u.user_id, u.user_name, u.user_rank, u.user_email, u.user_website, u.user_avatar, u.user_regdate, u.user_from, u.user_sig, u.user_viewemail, u.user_points, u.user_warnings, u.user_gender, u.user_votes, u.user_totalvotes, g.name, g.rank, g.color FROM '.PREFIX_DB.'_users AS u LEFT JOIN '.PREFIX_DB.'_groups AS g ON ((g.extra=1 AND u.user_group=g.id) OR (g.extra!=1 AND u.user_points>=g.points)) WHERE u.user_id IN ('.implode(', ', $where).') ORDER BY g.extra ASC, g.points ASC');
            while ([$uid, $nick, $rank, $mail, $site, $avatar, $reg, $from, $sig, $view, $point, $warn, $gender, $votes, $total, $gname, $grank, $gcolor] = $db->sql_fetchrow($result2)) {
                $umassiv[] = [$uid, $nick, $rank, $mail, $site, $avatar, $reg, $from, $sig, $view, $point, $warn, $gender, $votes, $total, $gname, $grank, $gcolor];
                unset($uid, $nick, $rank, $mail, $site, $avatar, $reg, $from, $sig, $view, $point, $warn, $gender, $votes, $total, $gname, $grank, $gcolor);
            }
        }
        if ($num == 1) {
            $tstatus = $cmassiv[0][17];
        } else {
            [$tstatus] = $db->sql_fetchrow($db->sql_query('SELECT status FROM '.PREFIX_DB.'_forum WHERE id = :id', ['id' => $id]));
        }
        $isread = is_acess($cmassiv[0][19]);
        $istopic = is_acess($cmassiv[0][20]);
        $isreply = is_acess($cmassiv[0][21]);
        $isedit = is_acess($cmassiv[0][22]);
        $isdelete = is_acess($cmassiv[0][23]);
        $ismod = is_acess($cmassiv[0][24]);
        $seodesc = cutstr(trim(strip_tags(bb_decode($cmassiv[0][7], $conf['name']))), 160);
        $seoimg  = getImgText($cmassiv[0][7], '', false);
        $seoimg  = $seoimg ? $conf['homeurl'].'/'.$seoimg : '';
        setHead([
            'title' => $cmassiv[0][5],
            'ctitle' => $cmassiv[0][18],
            'desc' => $seodesc,
            'img' => $seoimg,
            'time' => $cmassiv[0][6],
            'author' => $cmassiv[0][4] ?: $conf['sitename'],
        ]);
        if ($ismod || ($isread && $tstatus > 1)) {
            $atopic = (is_moder($conf['name']) || $istopic) ? '<a href="index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$cmassiv[0][2].'" title="'._NEWTOPIC.'" class="sl_but">'._OPEN.'</a>' : '<span title="'.sprintf(_ACINFOT, _NOTCAN).'" class="sl_but sl_hidden">'._OPEN.'</span>';
            $areply = (is_moder($conf['name']) || ($isreply && $tstatus)) ? '<a href="index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$cmassiv[0][2].'&amp;pid='.$id.'" title="'._TOPICREPLY.'" class="sl_but">'._REPLY.'</a>' : '<span title="'.sprintf(_ACINFOP, _NOTCAN).'" class="sl_but sl_hidden">'._REPLY.'</span>';
            $pnum = setPageNumbers('forum-pagenum', $conf['name'], $numfor, $numpages, $fornum, 'op=view&id='.$id.'&', $conf['forum']['pnum'], $num);
            $favor = favorview($id, $conf['name']);
            $cont = setTemplateBasic('forum-view-open', ['{%atopic%}' => $atopic, '{%areply%}' => $areply, '{%title%}' => search_color($cmassiv[0][5], $word), '{%favor%}' => $favor]);
            foreach ($cmassiv as $val) {
                $fid = $val[0];
                $fcat = $val[2];
                /*
                $sid = $val[0];
                $pid = $val[1];
                $catid = $val[2];
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
                if (!empty($umassiv) && is_array($umassiv)) {
                    foreach ($umassiv as $val2) {
                        if (strtolower($val[3]) == strtolower($val2[0])) {
                            $uid = $val2[0];
                            $nick = $val2[1];
                            $rank = $val2[2];
                            $mail = $val2[3];
                            $site = $val2[4];
                            $avatar = $val2[5];
                            $reg = $val2[6];
                            $from = $val2[7];
                            $sig = $val2[8];
                            $view = $val2[9];
                            $point = $val2[10];
                            $warn = $val2[11];
                            $gender = $val2[12];
                            $votes = $val2[13];
                            $total = $val2[14];
                            $gname = $val2[15];
                            $grank = $val2[16];
                            $gcolor = $val2[17];
                        }
                    }
                }
                $avname = (!empty($nick)) ? $nick : $val[4].' ('._ANONYM.')';
                $avatar = (!empty($nick)) ? (($avatar && file_exists($conf['users']['adirectory'].'/'.$avatar)) ? $conf['users']['adirectory'].'/'.$avatar : $conf['users']['adirectory'].'/default/00.gif') : $conf['users']['adirectory'].'/default/0.gif';
                $date = (($ismod || $conf['forum']['ledit']) && $val[16]) ? '<span title="'._PADD.'" class="sl_t_post">'.format_time($val[6], _TIMESTRING).'</span><span title="'._PEDIT.'" class="sl_t_edit">'.format_time($val[16], _TIMESTRING).'</span>' : '<span title="'._PADD.'" class="sl_t_post">'.format_time($val[6], _TIMESTRING).'</span>';
                $rating = ($a == 1) ? ajax_rating(1, $fid, $conf['name'], $val[12], $val[11], '', 1) : '';
                $ip = ($ismod && $val[13]) ? user_geo_ip($val[13], 4) : '';
                $amess = '<a href="#'.$fid.'" title="'._MESSAGE.': '.$a.'" class="sl_pnum">'.$a.'</a>';
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
                
                $qreply = (is_moder($conf['name']) || ($isreply && $tstatus)) ? '<a href="index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$fcat.'&amp;pid='.$id.'&amp;qid='.$fid.'" title="'._QREPLY.'" class="sl_but_blue">'._REPLY.'</a>' : '';
                $edit = ($ismod || ($isedit && $val[3] == intval($user[0]) && $tstatus)) ? "<a href=\"#\" OnClick=\"AjaxLoad('GET', '1', 'for".$fid."', 'go=1&amp;op=editpost&amp;id=".$fid.'&amp;cid='.$fcat.'&amp;typ=1&amp;mod='.$conf['name']."', ''); return false;\" title=\""._ONEDIT.'">'._ONEDIT.'</a>||<a href="index.php?name='.$conf['name'].'&amp;op=add&amp;cat='.$fcat.'&amp;id='.$fid.'&amp;pid='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||' : '';
                $edit .= ($ismod || ($isdelete && $val[3] == intval($user[0]))) ? '<a href="index.php?name='.$conf['name'].'&amp;op=delete&amp;cat='.$fcat.'&amp;id='.$fid."\" OnClick=\"return DelCheck(this, '"._DELETE.' &quot;'.$val[5]."&quot;?');\" title=\""._ONDELETE.'">'._ONDELETE.'</a>' : '';
                $edit = ($edit) ? add_menu($edit) : '';
                $hclass = (!$val[17]) ? 'title="'._PCLOSED.'" class="sl_hidden"' : '';
                $text = ($fields) ? '<div id="repfor'.$fid.'">'.search_color(bb_decode($val[7], $conf['name']), $word).'</div>'.search_color(bb_decode('<br><br>'.$fields, $conf['name']), $word) : '<div id="repfor'.$fid.'">'.search_color(bb_decode($val[7], $conf['name']), $word).'</div>';
                $cont .= setTemplateBasic('forum-view-basic', ['{%id%}' => $fid, '{%username%}' => $avname, '{%date%}' => $date, '{%rating%}' => $rating, '{%ip%}' => $ip, '{%post_count%}' => $amess, '{%avatar%}' => $avatar, '{%rank%}' => $rank, '{%rank_link%}' => $rlink, '{%user_rate%}' => $rate, '{%warn%}' => $rwarn, '{%group%}' => $group, '{%points%}' => $point, '{%regdate%}' => $regdate, '{%gender%}' => $gender, '{%from%}' => $from, '{%text%}' => $text, '{%sig%}' => bb_decode($sig, $conf['name']), '{%btn_personal%}' => $personal, '{%btn_pm%}' => $privat, '{%btn_profile%}' => $profil, '{%btn_web%}' => $web, '{%btn_warn%}' => $warn, '{%btn_thank%}' => $thank, '{%btn_reply%}' => $qreply, '{%btn_edit%}' => $edit, '{%hclass%}' => $hclass]);
                if ($conf['forum']['sort']) { $a++; } else { $a--; }
            }
            $pnum = setPageNumbers('forum-pagenum', $conf['name'], $numfor, $numpages, $fornum, 'op=view&id='.$id.'&', $conf['forum']['pnum'], $num);
            $cont .= setTemplateBasic('forum-view-close', ['{%atopic%}' => $atopic, '{%areply%}' => $areply, '{%pager%}' => $pnum]);
            if ($ismod) {
                $selmm = '<form action="index.php?name='.$conf['name'].'" method="post">'.tmoder(1).' <input type="hidden" name="op" value="move"><input type="hidden" name="cat" value="'.$cmassiv[0][2].'"><input type="hidden" name="id[]" value="'.$id.'"> <input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
                $cont .= setTemplateBasic('forum-view-change', ['{%title%}' => _OPMOD.': ', '{%content%}' => $selmm]);
            }
            if (is_moder($conf['name']) || ($isreply && $tstatus)) $cont .= quickreply($id, $cmassiv[0][2], $cmassiv[0][5]);
        } else {
            $cont = setTemplateWarning('warn', ['text' => _NOVIEW, 'url' => '?name='.$conf['name'], 'time' => 5, 'id' => 'warn']);
        }
        echo $cont;
    setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

function quickreply(int|string $id, int|string $catid, string $subject): string {
    global $conf;
    if ($conf['forum']['qreply'] == 1) {
        $cont = '<form action="index.php?name='.$conf['name'].'" method="post" name="post" enctype="multipart/form-data"><table class="sl_table_form">';
        $cont .= (!is_user()) ? '<tr><td>'._YOURNAME.':</td><td><input type="text" name="postname" value="'._ANONYM.'" class="sl_field '.$conf['style'].'" placeholder="'._YOURNAME.'" required></td></tr>' : '';
        $cont .= '<tr><td>'._TEXT.':</td><td>'.textarea('1', 'hometext', '', $conf['name'], '10', _TEXT, '1').'</td></tr>'
        .fields_in(isset($field), $conf['name'])
        .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="subject" value="'.$subject.'"><input type="hidden" name="pid" value="'.$id.'"><input type="hidden" name="cat" value="'.$catid.'"><input type="hidden" name="posttype" value="save"><input type="hidden" name="op" value="send"><input type="submit" value="'._SEND.'" class="sl_but_blue"></td></tr>'
        .'</table></form>';
        return setTemplateBasic('forum-all-open', ['{%title%}' => _QUICKREPLY]).$cont.setTemplateBasic('forum-all-close', []);
    }
    return '';
}

function move(): void {
    global $db, $conf;
    $cat = getVar('post', 'cat', 'num');
    $catid = $cat;
    if ($conf['forum']['add'] && $catid) {
        [$authm] = $db->sql_fetchrow($db->sql_query('SELECT auth_mod FROM '.PREFIX_DB.'_categories WHERE id = :catid', ['catid' => $catid]));
        $ismod = is_acess($authm);
        $id = getVar('post', 'id', 'array', []);
        $tmove = getVar('post', 'tmove', 'text');
        $move = (is_numeric($tmove[0])) ? intval($tmove) : intval(substr($tmove, 1));
        if ($ismod && is_array($id) && $tmove[0]) {
            foreach ($id as $val) {
                if (intval($val)) {
                    if ($tmove[0] == 's') {
                        $db->sql_query('UPDATE '.PREFIX_DB.'_forum SET status = :tmove WHERE id = :val', ['tmove' => $move, 'val' => $val]);
                    } elseif ($tmove[0] == 'd') {
                        delete($catid, $val);
                    } elseif (is_numeric($tmove[0])) {
                        $rcatids = catids($conf['name'], $move);
                        $db->sql_query('UPDATE '.PREFIX_DB.'_forum SET catid = :tmove WHERE id = :id_val OR pid = :pid_val', ['tmove' => $move, 'id_val' => $val, 'pid_val' => $val]);
                        [$rnpost] = $db->sql_fetchrow($db->sql_query('SELECT COUNT(id) FROM '.PREFIX_DB.'_forum WHERE pid = :val', ['val' => $val]));
                        $wrnpost = ($rnpost) ? ', posts=posts+'.$rnpost : '';
                        $db->sql_query('UPDATE '.PREFIX_DB.'_categories SET topics=topics+1'.$wrnpost.', lpost_id = :val WHERE id IN ('.$rcatids.')', ['val' => $val]);
            
                        $catids = catids($conf['name'], $catid);
                        [$lid] = $db->sql_fetchrow($db->sql_query('SELECT lpost_id FROM '.PREFIX_DB.'_categories WHERE id = :catid', ['catid' => $catid]));
                        [$npost] = $db->sql_fetchrow($db->sql_query('SELECT COUNT(id) FROM '.PREFIX_DB.'_forum WHERE pid = :val', ['val' => $val]));
                        $wnpost = ($npost) ? ', posts=posts-'.$npost : '';
                        if ($lid == $val) {
                            [$lid] = $db->sql_fetchrow($db->sql_query('SELECT id FROM '.PREFIX_DB.'_forum WHERE catid = :catid AND ((pid != \'0\' && status = \'1\') || (pid = \'0\' && status > \'1\')) ORDER BY id DESC LIMIT 1', ['catid' => $catid]));
                            $db->sql_query('UPDATE '.PREFIX_DB.'_categories SET topics=topics-1'.$wnpost.', lpost_id = :lid WHERE id IN ('.$catids.')', ['lid' => $lid]);
                        } else {
                            $db->sql_query('UPDATE '.PREFIX_DB.'_categories SET topics=topics-1'.$wnpost.' WHERE id IN ('.$catids.')');
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
    global $db, $user, $conf, $stop;
    $cat = getVar('req', 'cat', 'num');
    $catid = $cat;
    [$ctitle, $authp, $authy, $authe, $authm] = $db->sql_fetchrow($db->sql_query('SELECT title, auth_post, auth_reply, auth_edit, auth_mod FROM '.PREFIX_DB.'_categories WHERE id = :catid', ['catid' => $catid]));
    $istopic = is_acess($authp);
    $isreply = is_acess($authy);
    $isedit = is_acess($authe);
    $ismod = is_acess($authm);
    
    $form = false;
    $id = getVar('req', 'id', 'num');
    $pid = getVar('req', 'pid', 'num');
    
    $where = (is_moder($conf['name'])) ? 'WHERE id = :pid' : 'WHERE id = :pid AND status != \'0\'';
    [$fstatus] = $db->sql_fetchrow($db->sql_query('SELECT status FROM '.PREFIX_DB.'_forum '.$where, ['pid' => $pid]));

    if ($conf['forum']['add'] && $id) {
        $fid = $id;
        [$qpid, $uid, $subject, $time, $hometext, $field, $status] = $db->sql_fetchrow($db->sql_query('SELECT pid, uid, title, time, hometext, field, status FROM '.PREFIX_DB.'_forum WHERE id = :id', ['id' => $id]));
        if ($ismod || ($isedit && $uid == intval($user[0]) && $fstatus > 2)) {
            $subh = ($qpid) ? 1 : 0;
            $info = _EDITS.': '.$subject;
            $pagetitle = $conf['defis'].' '._FORUM.' '.$conf['defis'].' '.$ctitle.' '.$conf['defis'].' '.$info;
            $form = true;
        }
        $oldsubject = $subject;
        $subject = getVar('post', 'subject', 'text');
        $subject = ($subject) ? save_text($subject, 1) : $oldsubject;
        $oldhometext = $hometext;
        $hometext = getVar('post', 'hometext', 'text');
        $hometext = ($hometext) ? save_text($hometext) : $oldhometext;

    } elseif ($conf['forum']['add'] && ($istopic || $isreply)) {
        $fid = getVar('post', 'fid', 'num');

        $qid = getVar('req', 'qid', 'num');
        $subh = (!empty($pid) || !empty($qpid)) ? 1 : 0;

        if ($pid) {
            $id = ($qid) ? $qid : $pid;
            [$ftitle, $ftext, $status] = $db->sql_fetchrow($db->sql_query('SELECT title, hometext, status FROM '.PREFIX_DB.'_forum WHERE id = :id', ['id' => $id]));
            $form = (is_moder($conf['name'])) ? true : (($fstatus > 2) ? true : false);
        
        } else {
            $form = true;
        }

        $subject = getVar('post', 'subject', 'text');
        $subject = (!empty($ftitle)) ? $ftitle : ($subject ? save_text($subject, 1) : '');
        $hometext = getVar('post', 'hometext', 'text');
        $hometext = ($qid && $ftext) ? '[quote]'.$ftext.'[/quote]' : ($hometext ? save_text($hometext) : '');
        $field = getVar('post', 'field', 'field');
        $status = getVar('post', 'status', 'num', 3);
        $time = save_datetime(1, 'time');
        $info = (!empty($ftext)) ? _PUBLICIN.': '.$ftitle : _PUBLICIN.': '.$ctitle;
        $pagetitle = _FORUM.' '.$ctitle.' '.$info;
        
    }
    if ($form) {
        setHead(['title' => $pagetitle]);
        $cont = ($stop) ? setTemplateWarning('warn', ['text' => $stop, 'url' => '', 'time' => 0, 'id' => 'warn']) : '';
        $psubject = (!$subh) ? $subject : '';
        if ($hometext) $cont .= preview($psubject, $hometext, '', $field, $conf['name']);
        $userinfo = getusrinfo();
        if ($userinfo['user_acess'] || (!is_user() && !$conf['forum']['anonpost'])) $cont .= setTemplateWarning('warn', ['text' => _POSTNOTE, 'url' => '', 'time' => 0, 'id' => 'warn']);
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
        $pagetitle = _FORUM.' '.$ctitle.' '.$ctitle;
        setHead(['title' => $pagetitle]);
        $cont = setTemplateBasic('forum-all-open', ['{%title%}' => $ctitle]);
        $cont .= setTemplateWarning('warn', ['text' => $info, 'url' => '?name='.$conf['name'], 'time' => 5, 'id' => 'warn']);
    }
    $cont .= setTemplateBasic('forum-all-close', []);
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
        [$ctitle, $authp, $authy, $authe, $authm] = $db->sql_fetchrow($db->sql_query('SELECT title, auth_post, auth_reply, auth_edit, auth_mod FROM '.PREFIX_DB.'_categories WHERE id = :catid', ['catid' => $catid]));
        $istopic = is_acess($authp);
        $isreply = is_acess($authy);
        $isedit = is_acess($authe);
        $ismod = is_acess($authm);
        
        $fid = getVar('post', 'fid', 'num');
        $id = $fid;
        $pid = getVar('post', 'pid', 'num');
        $postname = text_filter(substr(getVar('post', 'postname', 'text'), 0, 25));
        $subject = getVar('post', 'subject', 'text');
        $hometext = getVar('post', 'hometext', 'text');

        $checks = str_replace(["\n", "\r", "\t"], ' ', $hometext);
        $e = explode(' ', $checks);
        for ($a = 0; $a < count($e); $a++) $o = strlen($e[$a]);
        $hometext = save_text($hometext);
        $status = getVar('post', 'status', 'num', 0);
        
        $field = getVar('post', 'field', 'field');
        $time = ($ismod) ? save_datetime(1, 'time') : save_datetime(1);
        $postid = (is_user()) ? intval($user[0]) : '';
        $ip = getIp();
        
        $stop = [];
        if (!$subject) $stop[] = _CERROR;
        if (!$hometext) $stop[] = _CERROR1;
        if ($o > $conf['forum']['letter']) $stop[] = _CERROR2;
        if (!$postname && !is_user()) $stop[] = _CERROR3;

        if (!$stop && getVar('post', 'posttype', 'text') == 'save') {
            $where = (is_moder($conf['name'])) ? 'WHERE id = :pid' : 'WHERE id = :pid AND status != \'0\'';
            [$fstatus] = $db->sql_fetchrow($db->sql_query('SELECT status FROM '.PREFIX_DB.'_forum '.$where, ['pid' => $pid]));
            
            if ($id) {
                [$fpid, $uid, $ftime] = $db->sql_fetchrow($db->sql_query('SELECT pid, uid, time FROM '.PREFIX_DB.'_forum WHERE id = :id', ['id' => $id]));
                $fpid = ($fpid) ? $fpid : $id;
                if ($ismod || ($isedit && $uid == intval($user[0]) && $fstatus > 2)) {
                    $ftime = ($ismod) ? $time : $ftime;
                    if ($ismod) {
                        $db->sql_query('UPDATE '.PREFIX_DB.'_forum SET title = :subject, time = :ftime, hometext = :hometext, field = :field, e_uid = :postid, e_ip_send = :ip, e_time = NOW(), status = :status WHERE id = :id', ['subject' => $subject, 'ftime' => $ftime, 'hometext' => $hometext, 'field' => $field, 'postid' => $postid, 'ip' => $ip, 'status' => $status, 'id' => $id]);
                    } else {
                        $db->sql_query('UPDATE '.PREFIX_DB.'_forum SET title = :subject, time = :ftime, hometext = :hometext, field = :field, e_uid = :postid, e_ip_send = :ip, e_time = NOW() WHERE id = :id', ['subject' => $subject, 'ftime' => $ftime, 'hometext' => $hometext, 'field' => $field, 'postid' => $postid, 'ip' => $ip, 'id' => $id]);
                    }
                }
            
            } else {
                if ($ismod) {
                    $userinfo = getusrinfo();
                    $postname = ($userinfo['user_name']) ? $userinfo['user_name'] : $postname;
                    $status = ($status) ? $status : (($pid) ? 1 : 3);
                } elseif (is_user()) {
                    $userinfo = getusrinfo();
                    $postname = $userinfo['user_name'];
                    $status = ($userinfo['user_acess']) ? 0 : (($pid) ? 1 : 3);
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
                    $db->sql_query('INSERT INTO '.PREFIX_DB.'_forum (id, pid, catid, uid, name, title, time, hometext, field, ip_send, l_uid, l_name, l_time, status) VALUES (NULL, :pid, :catid, :postid, :postname, :subject, :time, :hometext, :field, :ip, :l_uid, :l_name, :l_time, :status)', ['pid' => $pid, 'catid' => $catid, 'postid' => $postid, 'postname' => $postname, 'subject' => $subject, 'time' => $time, 'hometext' => $hometext, 'field' => $field, 'ip' => $ip, 'l_uid' => $postid, 'l_name' => $postname, 'l_time' => $time, 'status' => $status]);
                    [$lpid, $ltime] = $db->sql_fetchrow($db->sql_query('SELECT id, time FROM '.PREFIX_DB.'_forum WHERE catid = :catid AND uid = :postid ORDER BY id DESC LIMIT 1', ['catid' => $catid, 'postid' => $postid]));
                    if ($pid) {
                        $lname = (isset($uname) && $uname) ? $uname : $postname;
                        $db->sql_query('UPDATE '.PREFIX_DB.'_forum SET comments = comments+1, l_uid = :postid, l_name = :lname, l_id = :lpost_id, l_time = :time WHERE id = :pid', ['postid' => $postid, 'lname' => $lname, 'lpost_id' => $lpid, 'time' => $time, 'pid' => $pid]);
                        $db->sql_query('UPDATE '.PREFIX_DB.'_categories SET posts = posts+1, lpost_id = :pid WHERE id IN ('.$catids.')', ['pid' => $pid]);
                        if ($conf['forum']['addmail']) {
                            [$muid] = $db->sql_fetchrow($db->sql_query('SELECT uid FROM '.PREFIX_DB.'_forum WHERE id = :pid', ['pid' => $pid]));
                            if ($postid != $muid) {
                                [$mail, $fsmail] = $db->sql_fetchrow($db->sql_query('SELECT user_email, user_fsmail FROM '.PREFIX_DB.'_users WHERE user_id = :muid', ['muid' => $muid]));
                                if ($mail && $fsmail) {
                                    $finishlink = $conf['homeurl'].'/index.php?name=forum&amp;op=view&amp;id='.$pid.'#'.$lpid;
                                    $link = '<a href="'.$finishlink.'">'.$finishlink.'</a>';
                                    $subject = $conf['sitename'].' - '._FORUM;
                                    $message = str_replace('[text]', sprintf(_ADDMAILF, $postname, $link), $conf['mtemp']);
                                    mail_send($mail, $conf['adminmail'], $subject, $message, 0, 3);
                                }
                            }
                        }
                        update_points(14);
                    } else {
                        if (strtotime($ltime) > time()) {
                            $db->sql_query('UPDATE '.PREFIX_DB.'_categories SET topics = topics+1, posts = posts+1 WHERE id IN ('.$catids.')');
                        } else {
                            $db->sql_query('UPDATE '.PREFIX_DB.'_categories SET topics = topics+1, posts = posts+1, lpost_id = :lpost_id WHERE id IN ('.$catids.')', ['lpost_id' => $lpid]);
                        }
                        update_points(13);
                    }
                }
            }
            $lid = ($fpid) ? $fpid : (($pid) ? $pid.'&last#'.$lpid : '');
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
    if ($conf['forum']['add'] && $catid && $id) {
        [$authd, $authm] = $db->sql_fetchrow($db->sql_query('SELECT auth_delete, auth_mod FROM '.PREFIX_DB.'_categories WHERE id = :catid', ['catid' => $catid]));
        $isdelete = is_acess($authd);
        $ismod = is_acess($authm);
        
        [$pid, $uid] = $db->sql_fetchrow($db->sql_query('SELECT pid, uid FROM '.PREFIX_DB.'_forum WHERE id = :id', ['id' => $id]));
        if ($ismod || ($isdelete && $uid == intval($user[0]))) {
            $recycle = intval($conf['forum']['recycle']);
            
            if ($recycle && $recycle != $catid) {
                $rcatids = catids($conf['name'], $recycle);
                if ($pid) {
                    $db->sql_query('UPDATE '.PREFIX_DB."_forum SET pid = '0', catid = :recycle WHERE id = :id", ['recycle' => $recycle, 'id' => $id]);
                    $db->sql_query('UPDATE '.PREFIX_DB.'_categories SET topics = topics+1, lpost_id = :id WHERE id IN ('.$rcatids.')', ['id' => $id]);
                } else {
                    $db->sql_query('UPDATE '.PREFIX_DB.'_forum SET catid = :recycle WHERE id = :id OR pid = :pid', ['recycle' => $recycle, 'id' => $id, 'pid' => $id]);
                    [$rnpost] = $db->sql_fetchrow($db->sql_query('SELECT COUNT(id) FROM '.PREFIX_DB.'_forum WHERE pid = :id', ['id' => $id]));
                    $wrnpost = ($rnpost) ? ', posts=posts+'.$rnpost : '';
                    $db->sql_query('UPDATE '.PREFIX_DB.'_categories SET topics = topics+1'.$wrnpost.', lpost_id = :id WHERE id IN ('.$rcatids.')', ['id' => $id]);
                }
            }
            
            $catids = catids($conf['name'], $catid);

            if ($pid) {
                [$lid] = $db->sql_fetchrow($db->sql_query('SELECT l_id FROM '.PREFIX_DB.'_forum WHERE id = :pid', ['pid' => $pid]));
                if ($lid == $id) {
                    [$lid, $luid, $lname, $ltime] = $db->sql_fetchrow($db->sql_query('SELECT id, uid, name, time FROM '.PREFIX_DB.'_forum WHERE pid = :pid1 OR id = :pid2 ORDER BY id DESC LIMIT 1', ['pid1' => $pid, 'pid2' => $pid]));
                    $db->sql_query('UPDATE '.PREFIX_DB.'_forum SET comments = comments-1, l_uid = :luid, l_name = :lname, l_id = :lid, l_time = :ltime WHERE id = :pid', ['luid' => $luid, 'lname' => $lname, 'lid' => $lid, 'ltime' => $ltime, 'pid' => $pid]);
                } else {
                    $db->sql_query('UPDATE '.PREFIX_DB.'_forum SET comments = comments-1 WHERE id = :pid', ['pid' => $pid]);
                }
                $db->sql_query('UPDATE '.PREFIX_DB.'_categories SET posts = posts-1 WHERE id IN ('.$catids.')');

            } else {
                [$lid] = $db->sql_fetchrow($db->sql_query('SELECT lpost_id FROM '.PREFIX_DB.'_categories WHERE id = :catid', ['catid' => $catid]));
                [$npost] = $db->sql_fetchrow($db->sql_query('SELECT COUNT(id) FROM '.PREFIX_DB.'_forum WHERE pid = :id', ['id' => $id]));
                $wnpost = ($npost) ? ', posts=posts-'.$npost : '';
                if ($lid == $id) {
                    [$lid] = $db->sql_fetchrow($db->sql_query('SELECT id FROM '.PREFIX_DB."_forum WHERE catid = :catid AND ((pid != '0' && status = '1') || (pid = '0' && status > '1')) ORDER BY id DESC LIMIT 1", ['catid' => $catid]));
                    $db->sql_query('UPDATE '.PREFIX_DB.'_categories SET topics = topics-1'.$wnpost.', lpost_id = :lid WHERE id IN ('.$catids.')', ['lid' => $lid]);
                } else {
                    $db->sql_query('UPDATE '.PREFIX_DB.'_categories SET topics = topics-1'.$wnpost.' WHERE id IN ('.$catids.')');
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
                [$fid, $fuid] = $db->sql_fetchrow($db->sql_query('SELECT id, uid FROM '.PREFIX_DB."_favorites WHERE fid = :id AND modul = 'forum'", ['id' => $id]));
                if ($fid) {
                    if ($fuid) update_points(44, $fuid, 1);
                    $db->sql_query('DELETE FROM '.PREFIX_DB.'_favorites WHERE id = :fid', ['fid' => $fid]);
                }
                $db->sql_query('DELETE FROM '.PREFIX_DB.'_forum WHERE id = :id1 OR pid = :id2', ['id1' => $id, 'id2' => $id]);
            }
            
        }
        
        $lid = ($pid) ? $pid.'&last#'.$lid : '';
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
