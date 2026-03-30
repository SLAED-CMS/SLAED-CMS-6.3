<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('news')) die('Illegal file access');

function news(): void {
    global $db, $afile, $conf, $tpl;
        setHead();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['news']['anum'] ?? 25;
    $anump = $conf['news']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    if (getVar('get', 'status', 'num', 0) == 1) {
        $status = '0';
        $field = 'name=news&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = setAdminNavi(['ops' => ['name=news', 'name=news&amp;op=add', 'name=news&amp;status=1', 'name=news&amp;op=config', 'name=news&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 2]);
    } else {
        $status = '1';
        $field = 'name=news&amp;';
        $refer = '';
        $cont = setAdminNavi(['ops' => ['name=news', 'name=news&amp;op=add', 'name=news&amp;status=1', 'name=news&amp;op=config', 'name=news&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO]]);
    }
    $result = $db->getSqlQuery('SELECT s.id, s.cid, s.name, s.title, s.time, s.vote, s.ip, c.title, u.name FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE s.status = :status ORDER BY s.fix DESC, s.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $hide = getAdminHidden('name', 'news').getAdminHidden('op', 'actions').getAdminHidden('refer', '1');
        $head = $tpl->getHtmlFrag('admin-article-list-head', [
            'checkall_html' => '<th data-sort-method="none"><input type="checkbox" name="markcheck" id="markcheck" title="'._CHECKALL.'" OnClick="CheckBox(\'#markcheck\', \'.sl_check\')"></th>',
            'functions_label' => _FUNCTIONS,
            'id_label' => _ID,
            'postedby_label' => _POSTEDBY,
            'status_label' => _STATUS,
            'title_label' => _TITLE,
        ]);
        $rows = '';
        while ([$id, $cid, $uname, $title, $time, $vote, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $ctitle = ($cid) ? $ctitle : _NO;
            $ip = ($ip) ? user_geo_ip($ip, 4) : _NO;
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            if ($status && time() >= strtotime($time)) {
                $view = adminLinkAction('index.php?name=news&amp;op=view&amp;id='.$id, _MVIEW, _MVIEW);
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $vote = ($vote) ? adminLinkAction($afile.'.php?name=voting&amp;op=add&amp;id='.$vote, _EDITVOTE, _EDITVOTE) : '';
            $acts = adminMenuItems([
                $view,
                $vote,
                adminLinkAction($afile.'.php?name=news&amp;op=add&amp;id='.$id, _FULLEDIT, _FULLEDIT),
                adminDeleteAction($afile.'.php?name=news&amp;op=actions&amp;typ=d&amp;id='.$id.$refer, _DELETE.' "'.$title.'"?', _ONDELETE, _ONDELETE),
            ]);
            $rows .= getAdminTableRow($tpl->getHtmlFrag('admin-article-list-row', [
                'actions_html' => $acts,
                'checkbox_html' => '<td><input type="checkbox" name="id[]" class="sl_check" value="'.$id.'"></td>',
                'id_text' => (string)$id,
                'post_html' => $post,
                'status_html' => ad_status('', $active),
                'title_html' => adminTitleTipLabel(_CATEGORY.': '.$ctitle.'<br>'._DATE.': '.format_time($time, _TIMESTRING).'<br>'._IP.': '.$ip, $title, cutstr($title, 60)),
            ]));
        }
        $selms = _CHECKOP.': '.edit_list('news', 'typ', '').' <input type="submit" value="'._OK.'" class="sl_but_blue">';
        $numpt = setArticleNumbers('pagenum', '', $anum, $field, 'id', '_news', '', 'status = \''.$status.'\'', $anump);
        $bottom = $tpl->getHtmlFrag('list-bottom', ['pager' => $numpt, 'select' => $selms]);
        $cont .= getAdminListForm(getAdminTable($head, $rows), $bottom, $hide);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop, $tpl;
    $id = getVar('req', 'id', 'num', 0);
    if ($id) {
        $result = $db->getSqlQuery('SELECT s.cid, s.name, s.title, s.time, s.intro, s.body, s.field, s.vote, s.ihome, s.acomm, s.assoc, s.fix, u.name FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE id = :id', ['id' => $id]);
        [$cat, $uname, $subject, $time, $hometext, $bodytext, $field, $vote, $ihome, $acomm, $associated, $fix, $nick] = $db->getSqlRow($result);
        $cat = (int)$cat;
        $uname = (string)$uname;
        $subject = (string)$subject;
        $time = (string)$time;
        $hometext = (string)$hometext;
        $bodytext = (string)$bodytext;
        $field = (string)$field;
        $vote = (int)$vote;
        $ihome = (int)$ihome;
        $acomm = (int)$acomm;
        $associated = $associated ? explode(',', (string)$associated) : [];
        $fix = (int)$fix;
        $nick = (string)$nick;
        $postname = $nick ?: ($uname ?: _ANONYM);
    } else {
        $id = getVar('post', 'id', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $subject = getVar('post', 'subject', 'title', '');
        $associated = getVar('post', 'associated', 'array', []);
        $cat = getVar('post', 'cat', 'num', 0);
        $hometext = getVar('post', 'hometext', 'text', '');
        $bodytext = getVar('post', 'bodytext', 'text', '');
        $field = getVar('post', 'field', 'field');
        $vote = getVar('post', 'vote', 'num', 0);
        $time = getVar('req', 'time', 'time');
        $ihome = getVar('post', 'ihome', 'num', 0);
        $acomm = getVar('post', 'acomm', 'num', 0);
        $fix = getVar('post', 'fix', 'num', 0);
    }
    setHead();
    $cont = setAdminNavi(['ops' => ['name=news', 'name=news&amp;op=add', 'name=news&amp;status=1', 'name=news&amp;op=config', 'name=news&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => implode('<br>', (array)$stop)]);
    $homepre = ($vote) ? '<div id="repnews">'.getVotingView($vote, 'news').'</div><hr>'.$hometext : $hometext;
    if ($homepre) $cont .= preview($subject, $homepre, $bodytext, $field, 'news');
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _PAGENOTE]);
    $hide = getAdminHidden('name', 'news');
    $rows = '';
    $asso = '';
    $result2 = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = \'news\' ORDER BY parent, title');
    if ($db->getSqlRowCount($result2) > 0) {
        $asso = '<table class="sl_form"><tr>';
        $a = 0;
        while ([$cid, $ctitle] = $db->getSqlRow($result2)) {
            if ($a == 2) {
                $asso .= '</tr><tr>';
                $a = 0;
            }
            $check = '';
            if ($associated && is_array($associated)) {
                foreach ($associated as $val) {
                    if ((int)$val === (int)$cid) $check = ' checked';
                }
            }
            $asso .= '<td><input type="checkbox" name="associated[]" value="'.$cid.'"'.$check.'> '.$ctitle.'</td>';
            $a++;
        }
        $asso .= '</tr></table>';
    }
    $rows .= $tpl->getHtmlFrag('admin-news-add-rows', [
        'acomm_html' => com_access('acomm', $acomm, 'sl_form'),
        'acomm_label' => _COMMENTS.':',
        'associated_html' => $asso,
        'associated_label_html' => getAdminHintLabel(_ASSOTOPIC, _ASSOTOPICI),
        'body_html' => textarea('2', 'bodytext', $bodytext, 'news', '15', _ENDTEXT, '0'),
        'body_label' => _ENDTEXT.':',
        'cat_html' => getcat('news', $cat, 'cat', 'sl_form', '<option value="">'._HOMECAT.'</option>'),
        'cat_label' => _CATEGORY.':',
        'field_html' => fields_in($field, 'news'),
        'fix_html' => radio_form($fix, 'fix'),
        'fix_label' => _FIXED.'?',
        'hometext_html' => textarea('1', 'hometext', $hometext, 'news', '5', _TEXT, '1'),
        'hometext_label' => _TEXT.':',
        'ihome_html' => radio_form($ihome, 'ihome'),
        'ihome_label' => _PUBHOME,
        'postname_html' => getUserSearch('postname', $postname, '25', 'sl_form', '1'),
        'postname_label' => _POSTEDBY.':',
        'save_html' => ad_save('id', $id, 'save'),
        'subject_label' => _TITLE.':',
        'subject_value' => $subject,
        'time_html' => datetime(1, 'time', $time, 16, 'sl_form'),
        'time_label' => _CHNGSTORY.':',
        'vote_html' => add_voting('news', 'vote', $vote, 'sl_form'),
        'vote_label' => _VOTING.':',
    ]);
    $cont .= getAdminForm($afile.'.php', $rows, $hide);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $id = getVar('post', 'id', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $subject = getVar('post', 'subject', 'title', '');
    $associated = getVar('post', 'associated', 'array', []);
    $associated = implode(',', is_array($associated) ? $associated : []);
    $cat = getVar('post', 'cat', 'num', 0);
    $hometext = getVar('post', 'hometext', 'text', '');
    $bodytext = getVar('post', 'bodytext', 'text', '');
    $field = getVar('post', 'field', 'field');
    $vote = getVar('post', 'vote', 'num', 0);
    $ihome = getVar('post', 'ihome', 'num', 0);
    $acomm = getVar('post', 'acomm', 'num', 0);
    $time = getVar('req', 'time', 'time');
    $fix = getVar('post', 'fix', 'num', 0);
    $stop = [];
    if (!$subject) $stop[] = _CERROR;
    if (!$hometext) $stop[] = _CERROR1;
    if (!$postname) $stop[] = _CERROR3;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype === 'save') {
        $postid = is_user_id($postname) ?: 0;
        $postname = !is_user_id($postname) ? filterText(substr($postname, 0, 25)) : '';
        if ($id) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET cid = :cat, uid = :uid, name = :name, title = :title, time = :time, intro = :intro, body = :body, field = :field, vote = :vote, ihome = :ihome, acomm = :acomm, assoc = :assoc, fix = :fix, status = \'1\' WHERE id = :id', ['cat' => $cat, 'uid' => $postid, 'name' => $postname, 'title' => $subject, 'time' => $time, 'intro' => $hometext, 'body' => $bodytext, 'field' => $field, 'vote' => $vote, 'ihome' => $ihome, 'acomm' => $acomm, 'assoc' => $associated, 'fix' => $fix, 'id' => $id]);
        } else {
            $ip = getip();
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_news (id, cid, uid, name, title, time, intro, body, field, vote, comments, counter, ihome, acomm, score, ratings, assoc, ip, fix, status) VALUES (NULL, :cat, :uid, :name, :title, :time, :intro, :body, :field, :vote, \'0\', \'0\', :ihome, :acomm, \'0\', \'0\', :assoc, :ip, :fix, \'1\')', ['cat' => $cat, 'uid' => $postid, 'name' => $postname, 'title' => $subject, 'time' => $time, 'intro' => $hometext, 'body' => $bodytext, 'field' => $field, 'vote' => $vote, 'ihome' => $ihome, 'acomm' => $acomm, 'assoc' => $associated, 'ip' => $ip, 'fix' => $fix]);
        }
        setRedirect($afile.'.php?name=news');
    } elseif ($posttype === 'delete') {
        actions($id, 'd');
    } else {
        add();
    }
}

function actions(int|array $ids = 0, string $vtyp = ''): void {
    global $db, $afile;
    $id = getVar('req', 'id', 'array', []);
    $req = $id;
    if (!is_array($req) || $req === []) {
        $id = getVar('req', 'id', 'num', 0);
        $single = $id;
        $req = ($single > 0) ? [$single] : [];
    }
    if (!is_array($ids)) $ids = ($ids > 0) ? [$ids] : [];
    $all = array_unique(array_filter(array_map('intval', array_merge($req, $ids)), static fn($v): bool => $v > 0));
    $typ = $vtyp ?: getVar('post', 'typ', 'text', getVar('get', 'typ', 'text', ''));
    $refer = getVar('req', 'refer', 'num', 0) ? '&status=1' : '';
    if ($all && $typ !== '') {
        $keys = [];
        $pars = [];
        foreach (array_values($all) as $i => $val) {
            $key = 'id'.$i;
            $keys[] = ':'.$key;
            $pars[$key] = $val;
        }
        $in = implode(', ', $keys);
        if ($typ[0] === 'a') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET status = :typ WHERE id IN ('.$in.')', ['typ' => (int)substr($typ, 1)] + $pars);
        } elseif ($typ[0] === 'f') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET fix = :typ WHERE id IN ('.$in.')', ['typ' => (int)substr($typ, 1)] + $pars);
        } elseif ($typ[0] === 'h') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET ihome = :typ WHERE id IN ('.$in.')', ['typ' => (int)substr($typ, 1)] + $pars);
        } elseif ($typ[0] === 't') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET time = NOW() WHERE id IN ('.$in.')', $pars);
        } elseif ($typ[0] === 'c') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET acomm = :typ WHERE id IN ('.$in.')', ['typ' => (int)substr($typ, 1)] + $pars);
        } elseif ($typ[0] === 'd') {
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid IN ('.$in.') AND modul = \'news\'', $pars);
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid IN ('.$in.') AND modul = \'news\'', $pars);
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_news WHERE id IN ('.$in.')', $pars);
        } elseif (is_numeric($typ)) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET cid = :typ WHERE id IN ('.$in.')', ['typ' => (int)$typ] + $pars);
        }
    }
    setRedirect($afile.'.php?name=news'.$refer);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=news', 'name=news&amp;op=add', 'name=news&amp;status=1', 'name=news&amp;op=config', 'name=news&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 3]);
    $cont .= checkPerms(CONFIG_DIR.'/news.php');
    $cont .= getAdminBox($tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'news',
        'op' => 'configsave',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_cdefis' => _CDEFIS,
        'cdefis_label' => _CDEFIS,
        'defis' => urldecode($conf['news']['defis'] ?? ''),
        '_bascol' => _BASCOL,
        'bascol_label' => _BASCOL,
        'bascol' => $conf['news']['bascol'] ?? 1,
        '_c11' => _C_11,
        'c11_label' => _C_11,
        'asocnum' => $conf['news']['asocnum'] ?? 10,
        '_c13' => _C_13,
        'c13_label' => _C_13,
        'listnum' => $conf['news']['listnum'] ?? 10,
        '_c33' => _C_33,
        'c33_label' => _C_33,
        'num' => $conf['news']['num'] ?? 25,
        '_c34' => _C_34,
        'c34_label' => _C_34,
        'anum' => $conf['news']['anum'] ?? 25,
        '_c35' => _C_35,
        'c35_label' => _C_35,
        'nump' => $conf['news']['nump'] ?? 10,
        '_c36' => _C_36,
        'c36_label' => _C_36,
        'anump' => $conf['news']['anump'] ?? 10,
        '_homcat' => _HOMCAT,
        'homcat_label' => _HOMCAT,
        'r_homcat' => radio_form($conf['news']['homcat'] ?? 0, 'homcat'),
        '_viewcat' => _VIEWCAT,
        'viewcat_label' => _VIEWCAT,
        'r_viewcat' => radio_form($conf['news']['viewcat'] ?? 0, 'viewcat'),
        '_c32' => _C_32,
        'c32_label' => _C_32,
        'r_catdesc' => radio_form($conf['news']['catdesc'] ?? 0, 'catdesc'),
        '_c15' => _C_15,
        'c15_label' => _C_15,
        'r_subcat' => radio_form($conf['news']['subcat'] ?? 0, 'subcat'),
        '_addamail' => _ADDAMAIL,
        'addamail_label' => _ADDAMAIL,
        'r_addmail' => radio_form($conf['news']['addmail'] ?? 0, 'addmail'),
        '_c39' => _C_39,
        'c39_label' => _C_39,
        'r_add' => radio_form($conf['news']['add'] ?? 0, 'add'),
        '_c40' => _C_40,
        'c40_label' => _C_40,
        'r_addquest' => radio_form($conf['news']['addquest'] ?? 0, 'addquest'),
        '_c37' => _C_37,
        'c37_label' => _C_37,
        'r_autor' => radio_form($conf['news']['autor'] ?? 0, 'autor'),
        '_c17' => _C_17,
        'c17_label' => _C_17,
        'r_date' => radio_form($conf['news']['date'] ?? 0, 'date'),
        '_c18' => _C_18,
        'c18_label' => _C_18,
        'r_read' => radio_form($conf['news']['read'] ?? 0, 'read'),
        '_c19' => _C_19,
        'c19_label' => _C_19,
        'r_rate' => radio_form($conf['news']['rate'] ?? 0, 'rate'),
        '_c20' => _C_20,
        'c20_label' => _C_20,
        'r_letter' => radio_form($conf['news']['letter'] ?? 0, 'letter'),
        '_c23' => _C_23,
        'c23_label' => _C_23,
        'r_assoc' => radio_form($conf['news']['assoc'] ?? 0, 'assoc'),
        'news' => true,
    ]));
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $cont = [
        'defis' => getVar('post', 'defis', 'defis', '%3E'),
        'bascol' => getVar('post', 'bascol', 'num', 1),
        'asocnum' => getVar('post', 'asocnum', 'num', 10),
        'listnum' => getVar('post', 'listnum', 'num', 10),
        'num' => getVar('post', 'num', 'num', 25),
        'anum' => getVar('post', 'anum', 'num', 25),
        'nump' => getVar('post', 'nump', 'num', 10),
        'anump' => getVar('post', 'anump', 'num', 10),
        'homcat' => getVar('post', 'homcat', 'num', 0),
        'viewcat' => getVar('post', 'viewcat', 'num', 0),
        'catdesc' => getVar('post', 'catdesc', 'num', 0),
        'subcat' => getVar('post', 'subcat', 'num', 0),
        'addmail' => getVar('post', 'addmail', 'num', 0),
        'add' => getVar('post', 'add', 'num', 0),
        'addquest' => getVar('post', 'addquest', 'num', 0),
        'autor' => getVar('post', 'autor', 'num', 0),
        'date' => getVar('post', 'date', 'num', 0),
        'read' => getVar('post', 'read', 'num', 0),
        'rate' => getVar('post', 'rate', 'num', 0),
        'letter' => getVar('post', 'letter', 'num', 0),
        'assoc' => getVar('post', 'assoc', 'num', 0),
    ];
    setConfigFile('news.php', $cont);
    setRedirect($afile.'.php?name=news&op=config');
}

function info(): void {
    $cont = setAdminNavi(['ops' => ['name=news', 'name=news&amp;op=add', 'name=news&amp;status=1', 'name=news&amp;op=config', 'name=news&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 4]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: news(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'actions': actions(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
