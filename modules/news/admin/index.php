<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('news')) die('Illegal file access');

# Render news admin search
function getNewsSearch(): string {
    global $afile, $tpl;
    $search = getVar('req', 'search', 'num', 2);
    $chng = (string)getVar('req', 'chng');
    $stat = getVar('req', 'status', 'num', 0);
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $opts =
        $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _ID, 'is_selected' => $search === 1]).
        $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _TITLE, 'is_selected' => $search === 2]).
        $tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _POSTEDBY, 'is_selected' => $search === 3]).
        $tpl->getHtmlFrag('select-option', ['value_attr' => '4', 'label_text' => _CATEGORY, 'is_selected' => $search === 4]).
        $tpl->getHtmlFrag('select-option', ['value_attr' => '5', 'label_text' => _IP, 'is_selected' => $search === 5]);
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=news',
        'hidden' => array_filter([
            $stat === 1 ? ['nameattr' => 'status', 'valueattr' => '1'] : null,
        ]),
        'content_html' =>
            _SEARCH.': '.
            $tpl->getHtmlFrag('select', ['name_attr' => 'search', 'options_html' => $opts]).
            ' '.
            $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'chng', 'value_attr' => $chng, 'maxlength_num' => 60]).
            ' '.
            $tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
    ]);
    return $tpl->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => $form]);
}

function news(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $ops = ['name=news', 'name=news&op=add', 'name=news&status=1', 'name=news&op=config', 'name=news&op=info'];
    $tabs = [_HOME, _ADD, _NEW, _PREFERENCES, _DOCS];
    $search = getVar('req', 'search', 'num', 2);
    $chng = (string)getVar('req', 'chng');
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['news']['anum'] ?? 25;
    $anump = $conf['news']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    $sub = getNewsSearch();
    if (getVar('req', 'status', 'num', 0) == 1) {
        $status = '0';
        $refer = '&refer=1';
        $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => 2, 'subtitle_html' => $sub]);
    } else {
        $status = '1';
        $refer = '';
        $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'subtitle_html' => $sub]);
    }
    $where = 's.status = :status';
    $wcnt = 'status = :status';
    $pars = ['status' => $status];
    if ($chng !== '') {
        if ($search === 1) {
            $pars['find'] = '%'.$chng.'%';
            $where .= ' AND s.id LIKE :find';
            $wcnt .= ' AND id LIKE :find';
        } elseif ($search === 2) {
            $pars['find'] = '%'.$chng.'%';
            $where .= ' AND s.title LIKE :find';
            $wcnt .= ' AND title LIKE :find';
        } elseif ($search === 3) {
            $pars['fnam'] = '%'.$chng.'%';
            $pars['fusr'] = '%'.$chng.'%';
            $where .= ' AND (s.name LIKE :fnam OR u.name LIKE :fusr)';
            $wcnt .= ' AND (name LIKE :fnam OR uid IN (SELECT id FROM '.PREFIX_DB.'_users WHERE name LIKE :fusr))';
        } elseif ($search === 4) {
            $pars['find'] = '%'.$chng.'%';
            $where .= ' AND s.cid IN (SELECT id FROM '.PREFIX_DB.'_categories WHERE modul = \'news\' AND title LIKE :find)';
            $wcnt .= ' AND cid IN (SELECT id FROM '.PREFIX_DB.'_categories WHERE modul = \'news\' AND title LIKE :find)';
        } elseif ($search === 5) {
            $pars['find'] = '%'.$chng.'%';
            $where .= ' AND s.ip LIKE :find';
            $wcnt .= ' AND ip LIKE :find';
        }
    }
    $field = 'name=news'.($status === '0' ? '&status=1' : '').'&search='.$search.($chng !== '' ? '&chng='.urlencode($chng) : '').'&';
    $result = $db->getSqlQuery('SELECT s.id, s.cid, s.name, s.title, s.time, s.vote, s.ip, c.title, u.name FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE '.$where.' ORDER BY s.fix DESC, s.time DESC LIMIT '.$offset.', '.$anum, $pars);
    if ($db->getSqlRowCount($result) > 0) {
        $rows = '';
        while ([$id, $cid, $uname, $title, $time, $vote, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $ctit = $ctitle ?: _NO;
            $post = $nick ? filterTextHighlight(user_info($nick), $chng) : filterTextHighlight($uname ?: _ANONYM, $chng);
            $items = [];
            if ($status && time() >= strtotime($time)) {
                $items[] = ['href' => 'index.php?name=news&op=view&id='.$id, 'label' => _MVIEW, 'title' => _MVIEW];
                $active = '1';
            } else {
                $active = '0';
            }
            if ($vote) $items[] = ['href' => $afile.'.php?name=voting&op=add&id='.$vote, 'label' => _EDITVOTE, 'title' => _EDITVOTE];
            $items[] = ['href' => $afile.'.php?name=news&op=add&id='.$id, 'label' => _FULLEDIT, 'title' => _FULLEDIT];
            $items[] = [
                'href' => $afile.'.php?name=news&op=actions&typ=d&id='.$id.$refer.'&token='.getSiteToken(),
                'label' => _ONDELETE,
                'title' => _ONDELETE,
                'onclick_attr' => ' OnClick="return confirm(\''._DELETE.' &quot;'.addslashes($title).'&quot;?\')"',
            ];
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['is_col_id' => true, 'content_html' => filterTextHighlight((string)$id, $chng)],
                    ['is_truncate' => true, 'title_text' => $title, 'content_html' => $tpl->getHtmlFrag('popover', [
                        'items' => [
                            ['label' => _CATEGORY, 'value' => $cid ? filterTextHighlight($ctit, $chng) : _NO],
                            ['label' => _DATE, 'value' => format_time($time, _TIMESTRING)],
                            ['label' => _IP, 'value' => $ip ? filterTextHighlight(Geoip::getIpHtml($ip), $chng) : _NO, 'is_last' => true],
                        ],
                        'title_text' => $title,
                    ]).filterTextHighlight($title, $chng)],
                    ['is_col_author' => true, 'content_html' => $post],
                    ['is_col_status' => true, 'content_html' => ad_status('', $active)],
                    ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('popover', ['trigger_label' => _FUNCTIONS, 'items' => $items])],
                    ['is_col_check' => true, 'content_html' => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'id[]', 'value_attr' => (string)$id, 'is_check' => true])],
                ],
            ])]);
        }
        $catopts = '';
        $catres = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = \'news\' ORDER BY parent, title');
        while ([$catid, $cattitle] = $db->getSqlRow($catres)) {
            $catopts .= $tpl->getHtmlFrag('select-option', [
                'value_attr' => (string)$catid,
                'label_text' => $cattitle,
            ]);
        }
        $modopts =
            $tpl->getHtmlFrag('select-option', ['value_attr' => 'a1', 'label_text' => _ACTIVATE])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 'a0', 'label_text' => _DEACTIVATE])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 'f1', 'label_text' => _FIXED])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 'f0', 'label_text' => _LNFIX])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 'h1', 'label_text' => _LHOME])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 'h0', 'label_text' => _LNHOME])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 't1', 'label_text' => _LADATE])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 'c1', 'label_text' => _APOSTMOD])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 'c0', 'label_text' => _APOSTNOMOD])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 'd', 'label_text' => _DELETE]);
        $actopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _OPMOD, 'is_selected' => true])
            .$tpl->getHtmlFrag('select-optgroup', ['label_text' => _OPMOD, 'options_html' => $modopts])
            .$tpl->getHtmlFrag('select-optgroup', ['label_text' => _MOVETO, 'options_html' => $catopts]);
        $pager = getTplPager(['limit' => $anum, 'maxpg' => $anump, 'url' => $field, 'table' => '_news', 'field' => 'id', 'where' => $wcnt, 'where_params' => $pars]);
        $actions = $tpl->getHtmlFrag('inline-badge', ['is_action_label' => true, 'label' => _CHECKOP]).' '.$tpl->getHtmlFrag('select', ['name_attr' => 'typ', 'options_html' => $actopts])
            .$tpl->getHtmlFrag('button', ['button_type' => 'submit', 'submit_label' => _OK]);
        $body = $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php?name=news&op=actions',
            'hidden' => array_filter([
                ['nameattr' => 'token', 'valueattr' => getSiteToken()],
                $status === '0' ? ['nameattr' => 'refer', 'valueattr' => '1'] : null,
            ]),
            'content_html' => $tpl->getHtmlFrag('table', [
                'is_wrapless' => true,
                'is_fixed' => true,
                'head' => [
                    ['content' => _ID, 'is_col_id' => true],
                    ['content' => _TITLE, 'is_truncate' => true],
                    ['content' => _POSTEDBY, 'is_col_author' => true],
                    ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
                    ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
                    ['content' => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'markcheck', 'input_id' => 'markcheck', 'is_check' => true, 'input_attr' => 'title="'._CHECKALL.'"']), 'is_col_check' => true, 'nosort' => true],
                ],
                'rows_html' => $rows,
            ]),
            'actions_html' => $tpl->getHtmlFrag('module-foot', [
                'is_list' => true,
                'pager_html' => $pager,
                'actions_html' => $actions,
            ]),
        ]);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    } else {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $locale, $stop, $tpl;
    $id = getVar('req', 'id', 'num', 0);
    if ($id) {
        $result = $db->getSqlQuery('SELECT s.cid, s.name, s.title, s.time, s.intro, s.body, s.field, s.vote, s.ihome, s.acomm, s.assoc, s.fix, u.name FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE s.id = :id', ['id' => $id]);
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
        $associated = getVar('post', 'associated[]', '', []);
        $associated = is_array($associated) ? $associated : [];
        $cat = getVar('post', 'cat', 'num', 0);
        $hometext = getVar('post', 'hometext', 'raw', '');
        $bodytext = getVar('post', 'bodytext', 'raw', '');
        $fieldp = getVar('post', 'field[]', 'raw', []);
        $field = is_array($fieldp) ? implode('|', array_map('strval', $fieldp)) : '';
        $vote = getVar('post', 'vote', 'num', 0);
        $time = getVar('req', 'time', 'time');
        $ihome = getVar('post', 'ihome', 'num', 0);
        $acomm = getVar('post', 'acomm', 'num', 0);
        $fix = getVar('post', 'fix', 'num', 0);
    }
    setHead();
    $ops = ['name=news', 'name=news&op=add', 'name=news&status=1', 'name=news&op=config', 'name=news&op=info'];
    $tabs = [_HOME, _ADD, _NEW, _PREFERENCES, _DOCS];
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'lines' => array_values((array)$stop)]);
    $homepre = ($vote) ? $tpl->getHtmlFrag('block-content', ['id' => 'repnews', 'is_section' => true, 'content' => getVotingView($vote, 'news'), 'has_hr' => true]).$hometext : $hometext;
    if ($homepre) $cont .= getTplPreviewContent(['title' => $subject, 'texta' => $homepre, 'textb' => $bodytext, 'field' => $field, 'mod' => 'news']);
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _PAGENOTE]);
    $catopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _HOMECAT, 'is_selected' => !$cat]);
    $catres = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = \'news\' ORDER BY parent, title');
    $assohtml = '';
    while ([$cid, $ctitle] = $db->getSqlRow($catres)) {
        $catopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$cid,
            'label_text' => $ctitle,
            'is_selected' => (int)$cid === (int)$cat,
        ]);
        $assohtml .= $tpl->getHtmlFrag('checkbox', [
            'is_right' => true,
            'name_attr' => 'associated[]',
            'value_attr' => (string)$cid,
            'is_checked' => in_array((string)$cid, array_map('strval', $associated), true),
            'label_text' => $ctitle,
        ]);
    }
    $voteopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _NO, 'is_selected' => !$vote]);
    $vpars = ['modul' => 'news'];
    if ($conf['multilingual'] == 1) {
        $where = "(lang = :locale OR lang = '') AND modul = :modul AND time <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')";
        $vpars['locale'] = $locale;
    } else {
        $where = "modul = :modul AND time <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')";
    }
    $voting = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_voting WHERE '.$where.' ORDER BY id DESC', $vpars);
    while ([$vid, $vtitle] = $db->getSqlRow($voting)) {
        $voteopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$vid,
            'label_text' => $vtitle,
            'is_selected' => (int)$vote === (int)$vid,
        ]);
    }
    $commopts =
        $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _DEACTIVATE, 'is_selected' => $acomm == 0])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _APOSTMOD, 'is_selected' => $acomm == 1])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _APOSTNOMOD, 'is_selected' => $acomm == 2]);
    $rows = [
        [
            'label_html' => _POSTEDBY,
            'field_html' => getTplUserSearchInput([
                'input_id' => 'postname',
                'list_id' => 'postname_list',
                'maxlength' => 25,
                'minlength' => (int)$conf['search']['slet'],
                'name' => 'postname',
                'tip' => sprintf(_USERSEARCHTIP, (int)$conf['search']['slet']),
                'value' => $postname,
            ]),
        ],
        ['label_html' => _TITLE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'subject', 'value_attr' => $subject, 'maxlength_num' => 255, 'is_required' => true])],
        ['label_html' => _CATEGORY, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'cat', 'options_html' => $catopts])],
        ['label_html' => _TEXT, 'field_html' => getTplTextarea(['id' => '1', 'name' => 'hometext', 'value' => $hometext, 'mod' => 'news', 'rows' => 5, 'placeholder' => _TEXT, 'required' => '1', 'autofocus' => true]), 'is_full' => true, 'field_unwrapped' => true],
        ['label_html' => _ENDTEXT, 'field_html' => getTplTextarea(['id' => '2', 'name' => 'bodytext', 'value' => $bodytext, 'mod' => 'news', 'rows' => 15, 'placeholder' => _ENDTEXT, 'required' => '0']), 'is_full' => true, 'field_unwrapped' => true],
        ['label_html' => _CHNGSTORY, 'field_html' => getTplAddDateTime(['name' => 'time', 'time' => $time, 'with' => true, 'max' => 16])],
        ['label_html' => _VOTING, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'vote', 'options_html' => $voteopts])],
        ['label_html' => _PUBHOME, 'field_html' => getTplRadioGroup(['name' => 'ihome', 'value' => (string)$ihome, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _COMMENTS, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'acomm', 'options_html' => $commopts])],
        ['label_html' => _FIXED.'?', 'field_html' => getTplRadioGroup(['name' => 'fix', 'value' => (string)$fix, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _ASSOTOPIC, 'hint' => _ASSOTOPICI]), 'field_html' => $assohtml, 'is_full' => true],
    ];
    $rows = array_merge($rows, getTplAddFieldRows(['field' => $field, 'mod' => 'news']));
    $posttypeopts
        = $tpl->getHtmlFrag('select-option', ['value_attr' => 'preview', 'label_text' => _PREVIEW])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SEND])
        .($id ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'delete', 'label_text' => _DELETE]) : '');
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=news&op=save',
        'hidden' => [
            ['nameattr' => 'id', 'valueattr' => (string)$id],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'actions_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'posttype', 'options_html' => $posttypeopts, 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
        'rows' => $rows,
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $id = getVar('post', 'id', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $subject = getVar('post', 'subject', 'title', '');
    $associated = getVar('post', 'associated[]', '', []);
    $associated = implode(',', is_array($associated) ? $associated : []);
    $cat = getVar('post', 'cat', 'num', 0);
    $hometext = getVar('post', 'hometext', 'text', '');
    $bodytext = getVar('post', 'bodytext', 'text', '');
    $fieldp = getVar('post', 'field[]', 'raw', []);
    $field = is_array($fieldp) ? filterFields($fieldp) : '';
    $vote = getVar('post', 'vote', 'num', 0);
    $ihome = getVar('post', 'ihome', 'num', 0);
    $acomm = getVar('post', 'acomm', 'num', 0);
    $time = getVar('req', 'time', 'time');
    $fix = getVar('post', 'fix', 'num', 0);
    $posttype = getVar('post', 'posttype', 'text', '');
    $iswarn = !checkSiteToken();
    $stop = [];
    if (!$iswarn) {
        if (!$subject) $stop[] = _CERROR;
        if (!$hometext) $stop[] = _CERROR1;
        if (!$postname) $stop[] = _CERROR3;
        if (!$stop && $posttype === 'save') {
            $postid = is_user_id($postname) ?: 0;
            $postname = !is_user_id($postname) ? filterText(substr($postname, 0, 25)) : '';
            if ($id) {
                setContentActive('_news', [$id], 31);
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET cid = :cat, uid = :uid, name = :name, title = :title, time = :time, intro = :intro, body = :body, field = :field, vote = :vote, ihome = :ihome, acomm = :acomm, assoc = :assoc, fix = :fix WHERE id = :id', ['cat' => $cat, 'uid' => $postid, 'name' => $postname, 'title' => $subject, 'time' => $time, 'intro' => $hometext, 'body' => $bodytext, 'field' => $field, 'vote' => $vote, 'ihome' => $ihome, 'acomm' => $acomm, 'assoc' => $associated, 'fix' => $fix, 'id' => $id]);
            } else {
                $ip = getip();
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_news (id, cid, uid, name, title, time, intro, body, field, vote, comments, counter, ihome, acomm, score, ratings, assoc, ip, fix, status) VALUES (NULL, :cat, :uid, :name, :title, :time, :intro, :body, :field, :vote, \'0\', \'0\', :ihome, :acomm, \'0\', \'0\', :assoc, :ip, :fix, \'1\')', ['cat' => $cat, 'uid' => $postid, 'name' => $postname, 'title' => $subject, 'time' => $time, 'intro' => $hometext, 'body' => $bodytext, 'field' => $field, 'vote' => $vote, 'ihome' => $ihome, 'acomm' => $acomm, 'assoc' => $associated, 'ip' => $ip, 'fix' => $fix]);
            }
        }
    }
    if ($stop) {
        add();
        return;
    }
    if ($posttype === 'preview') {
        add();
        return;
    }
    if ($posttype === 'delete') {
        actions($id, 'd');
        return;
    }
    setRedirect($afile.'.php?name=news', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function actions(int|array $ids = 0, string $vtyp = ''): void {
    global $db, $afile;
    $id = getVar('req', 'id[]', '', []);
    $req = $id;
    if (!is_array($req) || $req === []) {
        $id = getVar('req', 'id', 'num', 0);
        $single = $id;
        $req = ($single > 0) ? [$single] : [];
    }
    if (!is_array($ids)) $ids = ($ids > 0) ? [$ids] : [];
    $all = array_unique(array_filter(array_map('intval', array_merge($req, $ids)), static fn($val): bool => $val > 0));
    $typ = $vtyp ?: getVar('post', 'typ', 'text', getVar('get', 'typ', 'text', ''));
    $refer_val = getVar('req', 'refer', 'num', 0);
    $refer = ($refer_val == 1) ? '&status=1' : '';
    $iswarn = !checkSiteToken();
    if (!$iswarn && $all && $typ !== '') {
        $keys = [];
        $pars = [];
        foreach (array_values($all) as $pos => $val) {
            $key = 'id'.$pos;
            $keys[] = ':'.$key;
            $pars[$key] = $val;
        }
        $in = implode(', ', $keys);
        if ($typ[0] === 'a') {
            if ((int)substr($typ, 1) === 1) {
                setContentActive('_news', $all, 31);
            } else {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_news SET status = :typ WHERE id IN ('.$in.')', ['typ' => 0] + $pars);
            }
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
    $succ = ($typ !== '' && $typ[0] === 'd') ? _SUCCDELETE : _SUCCSTATUS;
    if ($refer_val == 2) {
        setRedirect('index.php?name=news', false, 302, $iswarn ? _TOKENMISS : $succ, $iswarn);
        return;
    }
    setRedirect($afile.'.php?name=news'.$refer, false, 302, $iswarn ? _TOKENMISS : $succ, $iswarn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $ops = ['name=news', 'name=news&op=add', 'name=news&status=1', 'name=news&op=config', 'name=news&op=info'];
    $tabs = [_HOME, _ADD, _NEW, _PREFERENCES, _DOCS];
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => 3]);
    $cont .= checkPerms(CONFIG_DIR.'/news.php');
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows = [
        ['label_html' => _CDEFIS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'defis', 'value_attr' => urldecode($conf['news']['defis'] ?? '')])],
        ['label_html' => _BASCOL, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'bascol', 'value_attr' => $conf['news']['bascol'] ?? 1])],
        ['label_html' => _C_11, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'asocnum', 'value_attr' => $conf['news']['asocnum'] ?? 10])],
        ['label_html' => _C_13, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'listnum', 'value_attr' => $conf['news']['listnum'] ?? 10])],
        ['label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => $conf['news']['num'] ?? 25])],
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => $conf['news']['anum'] ?? 25])],
        ['label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'nump', 'value_attr' => $conf['news']['nump'] ?? 10])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => $conf['news']['anump'] ?? 10])],
        ['label_html' => _HOMCAT, 'field_html' => getTplRadioGroup(['name' => 'homcat', 'value' => (string)($conf['news']['homcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _VIEWCAT, 'field_html' => getTplRadioGroup(['name' => 'viewcat', 'value' => (string)($conf['news']['viewcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_32, 'field_html' => getTplRadioGroup(['name' => 'catdesc', 'value' => (string)($conf['news']['catdesc'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_15, 'field_html' => getTplRadioGroup(['name' => 'subcat', 'value' => (string)($conf['news']['subcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _ADDAMAIL, 'field_html' => getTplRadioGroup(['name' => 'addmail', 'value' => (string)($conf['news']['addmail'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_39, 'field_html' => getTplRadioGroup(['name' => 'add', 'value' => (string)($conf['news']['add'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_40, 'field_html' => getTplRadioGroup(['name' => 'addquest', 'value' => (string)($conf['news']['addquest'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_37, 'field_html' => getTplRadioGroup(['name' => 'autor', 'value' => (string)($conf['news']['autor'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_17, 'field_html' => getTplRadioGroup(['name' => 'date', 'value' => (string)($conf['news']['date'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_18, 'field_html' => getTplRadioGroup(['name' => 'read', 'value' => (string)($conf['news']['read'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_19, 'field_html' => getTplRadioGroup(['name' => 'rate', 'value' => (string)($conf['news']['rate'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_20, 'field_html' => getTplRadioGroup(['name' => 'letter', 'value' => (string)($conf['news']['letter'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_23, 'field_html' => getTplRadioGroup(['name' => 'assoc', 'value' => (string)($conf['news']['assoc'] ?? 0), 'options' => $yesno])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=news&op=configsave',
        'hidden' => [['nameattr' => 'token', 'valueattr' => getSiteToken()]],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
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
    }
    setRedirect($afile.'.php?name=news&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=news', 'name=news&op=add', 'name=news&status=1', 'name=news&op=config', 'name=news&op=info'],
        'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _DOCS],
    ]);
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
