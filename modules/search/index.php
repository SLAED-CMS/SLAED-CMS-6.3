<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function getSearchRow(string $val, string $afile, int $id, string $date, int $cid, ?string $ctitle, ?string $cdesc, ?string $nick, ?string $uname, string $editop, bool $haspost, string $evurl): array {
    $date = '<time datetime="'.date('c', strtotime($date)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($date).'</time>';
    $modul = '<a href="index.php?name='.$val.'" title="'._MODUL.'" class="sl_modul">'.getModuleName($val).'</a>';
    $cdesc = $cdesc ?: $ctitle;
    $ctitle = $ctitle ? '<a href="index.php?name='.$val.'&amp;cat='.$cid.'" title="'.htmlspecialchars($cdesc, ENT_QUOTES, 'UTF-8').'" class="sl_cat">'.htmlspecialchars(cutstr($ctitle, 15), ENT_QUOTES, 'UTF-8').'</a>' : '';
    $post = '';
    if ($haspost) {
        $pval = $nick ? user_info($nick) : ($uname ?: _ANONYM);
        $post = '<span title="'._POSTEDBY.'" class="sl_post">'.$pval.'</span>';
    }
    $edit = is_moder($val) ? add_menu('<a href="'.$afile.'.php?op='.$editop.'&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$evurl.'" target="_blank" title="'._WINDOWNEW.'">'._WINDOWNEW.'</a>') : '';
    return [$date, $modul, $ctitle, $post, $edit];
}

function search(): void {
    global $db, $afile, $conf;
    $search = explode(',', $conf['search']['mods']);
    $word = getVar('req', 'word', 'word', 0);
    $mod = getVar('req', 'mod', 'var', '');
    $mod = in_array($mod, $search, true) ? $mod : '';
    $typ = getVar('req', 'typ', 'num', 0);
    $num = getVar('req', 'num', 'num', 1);
    $modcont = '';
    foreach ($search as $val) {
        if (is_active($val) && $val != '') {
            $sel = ($val == $mod && $mod != '') ? ' selected' : '';
            $modcont .= '<option value="'.$val.'"'.$sel.'>'.getModuleName($val).'</option>';
        }
    }
    $stop = ($word && mb_strlen($word) < $conf['search']['slet']) ? _SEARCHLETMIN.': '.$conf['search']['slet'] : '';
    setHead(['title' => _SEARCH]);
    $cont = setTemplateBasic('title', ['{%title%}' => _SEARCH]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="index.php?name='.htmlspecialchars($conf['name'], ENT_QUOTES, 'UTF-8').'" method="post"><table class="sl_table_form">'
    .'<tr><td>'._MODUL.':</td><td><select name="mod" onchange="submit()" class="sl_field '.htmlspecialchars($conf['style'], ENT_QUOTES, 'UTF-8').'"><option value="">'._SEARCHALL.'</option>'.$modcont.'</select></td></tr>';
    if ($mod && $mod == 'media') {
        $cont .= '<tr><td>'._SEARCHFROM.':</td><td><select name="typ" class="sl_field '.htmlspecialchars($conf['style'], ENT_QUOTES, 'UTF-8').'">'
        .'<option value="1"';
        if ($typ == '1') $cont .= ' selected';
        $cont .= '>'._MTITLE.'</option>'
        .'<option value="2"';
        if ($typ == '2' || !$typ) $cont .= ' selected';
        $cont .= '>'._DESCRIPTION.'</option>'
        .'<option value="3"';
        if ($typ == '3') $cont .= ' selected';
        $cont .= '>'._MDIRECTOR.'</option>'
        .'<option value="4"';
        if ($typ == '4') $cont .= ' selected';
        $cont .= '>'._MROLES.'</option>'
        .'<option value="5"';
        if ($typ == '5') $cont .= ' selected';
        $cont .= '>'._MYEAR.'</option>'
        .'</select></td></tr>';
    }
    $cont .= '<tr><td>'._SEARCH.':</td><td><input type="text" name="word" value="'.htmlspecialchars($word, ENT_QUOTES, 'UTF-8').'" maxlength="100" class="sl_field '.htmlspecialchars($conf['style'], ENT_QUOTES, 'UTF-8').'" placeholder="'._SEARCH.'" required></td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="submit" title="'._SEARCH.'" value="'._SEARCH.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    if (!$stop && $word) {
        if ($conf['search']['asearch']) {
            $result = $db->getSqlQuery('SELECT word FROM '.PREFIX_DB.'_search WHERE word = :word', ['word' => $word]);
            if ($db->getSqlRowCount($result) > 0) {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_search SET time = NOW(), score = score+1 WHERE word = :word', ['word' => $word]);
            } else {
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_search VALUES (NULL, :word, :mod, NOW(), \'0\')', ['word' => $word, 'mod' => $mod]);
            }
        }
        $lim = (int)($conf['search']['slimit'] ?? 500);
        $modmap = [
            'faq'   => ['sql' => 'SELECT f.id, f.name, f.title, f.time, c.id, c.title, c.intro, u.name FROM '.PREFIX_DB.'_faq AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.time <= NOW() AND f.status != \'0\' AND (f.title LIKE :word1 OR f.body LIKE :word2) ORDER BY f.time DESC LIMIT :lim', 'editop' => 'faq_add', 'wcount' => 2],
            'files' => ['sql' => 'SELECT f.id, f.name, f.title, f.time, c.id, c.title, c.intro, u.name FROM '.PREFIX_DB.'_files AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.time <= NOW() AND f.status != \'0\' AND (f.title LIKE :word1 OR f.intro LIKE :word2 OR f.body LIKE :word3) ORDER BY f.time DESC LIMIT :lim', 'editop' => 'files_add', 'wcount' => 3],
            'jokes' => ['sql' => 'SELECT j.id, j.name, j.title, j.time, c.id, c.title, c.intro, u.name FROM '.PREFIX_DB.'_jokes AS j LEFT JOIN '.PREFIX_DB.'_categories AS c ON (j.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (j.uid = u.id) WHERE j.time <= NOW() AND j.status != \'0\' AND (j.title LIKE :word1 OR j.body LIKE :word2) ORDER BY j.time DESC LIMIT :lim', 'editop' => 'jokes_add', 'wcount' => 2],
            'links' => ['sql' => 'SELECT l.id, l.name, l.title, l.time, c.id, c.title, c.intro, u.name FROM '.PREFIX_DB.'_links AS l LEFT JOIN '.PREFIX_DB.'_categories AS c ON (l.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (l.uid = u.id) WHERE l.time <= NOW() AND l.status != \'0\' AND (l.title LIKE :word1 OR l.intro LIKE :word2 OR l.body LIKE :word3 OR l.url LIKE :word4) ORDER BY l.time DESC LIMIT :lim', 'editop' => 'links_add', 'wcount' => 4],
            'news'  => ['sql' => 'SELECT s.id, s.name, s.title, s.time, c.id, c.title, c.intro, u.name FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE s.time <= NOW() AND s.status != \'0\' AND (s.title LIKE :word1 OR s.intro LIKE :word2 OR s.body LIKE :word3) ORDER BY s.time DESC LIMIT :lim', 'editop' => 'news_add', 'wcount' => 3],
            'pages' => ['sql' => 'SELECT p.id, p.name, p.title, p.time, c.id, c.title, c.intro, u.name FROM '.PREFIX_DB.'_pages AS p LEFT JOIN '.PREFIX_DB.'_categories AS c ON (p.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uid = u.id) WHERE p.time <= NOW() AND p.status != \'0\' AND (p.title LIKE :word1 OR p.intro LIKE :word2 OR p.body LIKE :word3) ORDER BY p.time DESC LIMIT :lim', 'editop' => 'page_add', 'wcount' => 3],
        ];
        $a = 1;
        $conts = [];
        foreach ($search as $val) {
            if (($mod === '' || $mod === $val) && is_active($val) && $val !== '') {
                if (isset($modmap[$val])) {
                    $cfg = $modmap[$val];
                    $params = ['lim' => $lim];
                    for ($cnt = 1; $cnt <= $cfg['wcount']; $cnt++) $params['word'.$cnt] = '%'.$word.'%';
                    $result = $db->getSqlQuery($cfg['sql'], $params);
                    while ([$id, $uname, $title, $date, $cid, $ctitle, $cdesc, $nick] = $db->getSqlRow($result)) {
                        $evurl = ($val == 'jokes')
                            ? 'index.php?name='.$val.'&amp;cat='.$cid.'&amp;word='.urlencode($word).'#'.$id
                            : 'index.php?name='.$val.'&amp;op=view&amp;id='.$id.'&amp;word='.urlencode($word);
                        $title = '<a href="'.$evurl.'" title="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'">'.filterTextHighlight($title, $word).'</a> '.new_graphic($date);
                        [$date, $modul, $ctitle, $post, $edit] = getSearchRow($val, $afile, (int)$id, $date, (int)$cid, $ctitle, $cdesc, $nick, $uname, $cfg['editop'], true, $evurl);
                        $conts[] = [$a, $id, $title, $date, $modul, $ctitle, $post, $edit];
                        $a++;
                    }
                } elseif ($val == 'auto_links') {
                    $result = $db->getSqlQuery('SELECT id, title, added FROM '.PREFIX_DB.'_auto_links WHERE hits != \'0\' AND (title LIKE :word1 OR description LIKE :word2 OR link LIKE :word3) ORDER BY added DESC LIMIT :lim', ['word1' => '%'.$word.'%', 'word2' => '%'.$word.'%', 'word3' => '%'.$word.'%', 'lim' => $lim]);
                    while ([$id, $title, $date] = $db->getSqlRow($result)) {
                        $evurl = 'index.php?name='.$val.'&amp;op=view&amp;id='.$id;
                        $title = '<a href="'.$evurl.'" title="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'">'.filterTextHighlight($title, $word).'</a> '.new_graphic($date);
                        $date = '<time datetime="'.date('c', strtotime($date)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($date).'</time>';
                        $modul = '<a href="index.php?name='.$val.'" title="'._MODUL.'" class="sl_modul">'.getModuleName($val).'</a>';
                        $edit = is_moder($val) ? add_menu('<a href="'.$afile.'.php?op=auto_links_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$evurl.'" target="_blank" title="'._WINDOWNEW.'">'._WINDOWNEW.'</a>') : '';
                        $conts[] = [$a, $id, $title, $date, $modul, '', '', $edit];
                        $a++;
                    }
                } elseif ($val == 'forum') {
                    $rid = (int)($conf['forum']['recycle'] ?? 0);
                    if (is_moder('forum') || !$rid) {
                        $frecycle = '';
                        $fparams = ['word1' => '%'.$word.'%', 'word2' => '%'.$word.'%'];
                    } else {
                        $frecycle = 'f.cid != :rid AND';
                        $fparams = ['rid' => $rid, 'word1' => '%'.$word.'%', 'word2' => '%'.$word.'%'];
                    }
                    $fparams['lim'] = $lim;
                    $result = $db->getSqlQuery('SELECT f.id, f.pid, f.name, f.title, f.time, c.id, c.title, c.intro, u.name FROM '.PREFIX_DB.'_forum AS f LEFT JOIN '.PREFIX_DB.'_categories AS c ON (f.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE '.$frecycle.' f.pid = \'0\' AND f.time <= NOW() AND f.status != \'0\' AND (f.title LIKE :word1 OR f.body LIKE :word2) ORDER BY f.time DESC LIMIT :lim', $fparams);
                    while ([$id, $pid, $uname, $title, $date, $cid, $ctitle, $cdesc, $nick] = $db->getSqlRow($result)) {
                        $id = (!$pid) ? $id : $pid;
                        $evurl = 'index.php?name='.$val.'&amp;op=view&amp;id='.$id.'&amp;word='.urlencode($word);
                        $title = '<a href="'.$evurl.'" title="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'">'.filterTextHighlight($title, $word).'</a> '.new_graphic($date);
                        $date = '<time datetime="'.date('c', strtotime($date)).'" title="'._CHNGSTORY.'" class="sl_date">'.format_time($date).'</time>';
                        $modul = '<a href="index.php?name='.$val.'" title="'._MODUL.'" class="sl_modul">'.getModuleName($val).'</a>';
                        $cdesc = $cdesc ?: $ctitle;
                        $ctitle = $ctitle ? '<a href="index.php?name='.$val.'&amp;cat='.$cid.'" title="'.htmlspecialchars($cdesc, ENT_QUOTES, 'UTF-8').'" class="sl_cat">'.htmlspecialchars(cutstr($ctitle, 15), ENT_QUOTES, 'UTF-8').'</a>' : '';
                        $pval = $nick ? user_info($nick) : ($uname ?: _ANONYM);
                        $post = '<span title="'._POSTEDBY.'" class="sl_post">'.$pval.'</span>';
                        $edit = is_moder($val) ? add_menu('<a href="index.php?name='.$val.'&amp;op=add&amp;cat='.$cid.'&amp;id='.$id.'&amp;pid='.$pid.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$evurl.'" target="_blank" title="'._WINDOWNEW.'">'._WINDOWNEW.'</a>') : '';
                        $conts[] = [$a, $id, $title, $date, $modul, $ctitle, $post, $edit];
                        $a++;
                    }
                } elseif ($val == 'media') {
                    if ($typ == 1 && $word) {
                        $qstr = '(m.title LIKE :word1 OR m.subtitle LIKE :word2) ORDER BY m.title ASC';
                    } elseif ($typ == 2 && $word) {
                        $qstr = '(m.description LIKE :word1) ORDER BY m.description ASC';
                    } elseif ($typ == 3 && $word) {
                        $qstr = '(m.director LIKE :word1) ORDER BY m.director ASC';
                    } elseif ($typ == 4 && $word) {
                        $qstr = '(m.roles LIKE :word1) ORDER BY m.roles ASC';
                    } elseif ($typ == 5 && $word) {
                        $qstr = '(m.year LIKE :word1) ORDER BY m.year ASC';
                    } else {
                        $qstr = '(m.title LIKE :word1 OR m.subtitle LIKE :word2 OR m.description LIKE :word3) ORDER BY m.time DESC';
                    }
                    $result = $db->getSqlQuery('SELECT m.id, m.name, m.title, m.subtitle, m.time, c.id, c.title, c.intro, u.name FROM '.PREFIX_DB.'_media AS m LEFT JOIN '.PREFIX_DB.'_categories AS c ON (m.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (m.uid = u.id) WHERE m.time <= NOW() AND m.status != \'0\' AND '.$qstr.' LIMIT :lim', ['word1' => '%'.$word.'%', 'word2' => '%'.$word.'%', 'word3' => '%'.$word.'%', 'lim' => $lim]);
                    while ([$id, $uname, $title, $subtitle, $date, $cid, $ctitle, $cdesc, $nick] = $db->getSqlRow($result)) {
                        $title = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
                        $evurl = 'index.php?name='.$val.'&amp;op=view&amp;id='.$id.'&amp;word='.urlencode($word);
                        $title = '<a href="'.$evurl.'" title="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'">'.filterTextHighlight($title, $word).'</a> '.new_graphic($date);
                        [$date, $modul, $ctitle, $post, $edit] = getSearchRow($val, $afile, (int)$id, $date, (int)$cid, $ctitle, $cdesc, $nick, $uname, 'media_add', true, $evurl);
                        $conts[] = [$a, $id, $title, $date, $modul, $ctitle, $post, $edit];
                        $a++;
                    }
                } elseif ($val == 'shop') {
                    $result = $db->getSqlQuery('SELECT p.id, p.time, p.title, c.id, c.title, c.intro FROM '.PREFIX_DB.'_products AS p LEFT JOIN '.PREFIX_DB.'_categories AS c ON (p.cid = c.id) WHERE time <= NOW() AND status = \'1\' AND (p.title LIKE :word1 OR p.intro LIKE :word2 OR p.body LIKE :word3) ORDER BY time DESC LIMIT :lim', ['word1' => '%'.$word.'%', 'word2' => '%'.$word.'%', 'word3' => '%'.$word.'%', 'lim' => $lim]);
                    while ([$id, $date, $title, $cid, $ctitle, $cdesc] = $db->getSqlRow($result)) {
                        $evurl = 'index.php?name='.$val.'&amp;op=view&amp;id='.$id.'&amp;word='.urlencode($word);
                        $title = '<a href="'.$evurl.'" title="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'">'.filterTextHighlight($title, $word).'</a> '.new_graphic($date);
                        [$date, $modul, $ctitle, $post, $edit] = getSearchRow($val, $afile, (int)$id, $date, (int)$cid, $ctitle, $cdesc, '', '', 'shop_products_add', false, $evurl);
                        $conts[] = [$a, $id, $title, $date, $modul, $ctitle, $post, $edit];
                        $a++;
                    }
                }
            }
        }
        $anum = $a - 1;
        $set = ($num - 1) * $conf['search']['snum'];
        $tnum = ($set) ? $conf['search']['snum'] + $set : $conf['search']['snum'];
        for ($cnt = $set; $cnt < $tnum; $cnt++) {
            if (isset($conts[$cnt]) && $conts[$cnt] != '') $cont .= setTemplateBasic('basic-search', ['{%n%}' => $conts[$cnt][0], '{%title%}' => $conts[$cnt][2], '{%date%}' => $conts[$cnt][3], '{%modul%}' => $conts[$cnt][4], '{%ctitle%}' => $conts[$cnt][5], '{%post%}' => $conts[$cnt][6], '{%admin%}' => $conts[$cnt][7]]);
        }
        if (!$anum) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => _NOMATCHES]);
        $pnum = ceil($anum / $conf['search']['snum']);
        $lsear = ($typ) ? '&typ='.$typ : '';
        $cont .= ($anum > $conf['search']['snum']) ? setPageNumbers('pagenum', $conf['name'], $anum, $pnum, $conf['search']['snum'], 'mod='.$mod.'&word='.urlencode($word).$lsear.'&', $conf['search']['snump']) : setNaviLower($conf['name']);
    } else {
        $winfo = ($stop) ? $stop : _SEARCHINFO;
        $wtyp = ($stop) ? 'warn' : 'info';
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => $wtyp, 'text' => $winfo]);
    }
    echo $cont;
    setFoot();
}

switch ($op) {
    default: search(); break;
}
