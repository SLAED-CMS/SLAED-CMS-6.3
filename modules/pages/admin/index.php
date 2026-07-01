<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('pages')) die('Illegal file access');

# Render pages admin search
function getPagesSearch(): string {
    global $afile, $tpl;
    $search = getVar('req', 'search', 'num', 2);
    $chng = (string)getVar('req', 'chng');
    $stat = getVar('req', 'status', 'num', 0);
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $opts =
        $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _ID, 'is_selected' => $search === 1]) .
        $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _TITLE, 'is_selected' => $search === 2]) .
        $tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _POSTEDBY, 'is_selected' => $search === 3]) .
        $tpl->getHtmlFrag('select-option', ['value_attr' => '4', 'label_text' => _CATEGORY, 'is_selected' => $search === 4]) .
        $tpl->getHtmlFrag('select-option', ['value_attr' => '5', 'label_text' => _IP, 'is_selected' => $search === 5]);
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=pages',
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
    return $tpl->getHtmlPart('searchbox', ['searchbox' => $form]);
}

function pages(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $search = getVar('req', 'search', 'num', 2);
    $chng = (string)getVar('req', 'chng');
    $search = ($search >= 1 && $search <= 5) ? $search : 2;
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['pages']['anum'] ?? 25;
    $anump = $conf['pages']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    $ops = ['name=pages', 'name=pages&amp;op=add', 'name=pages&amp;status=1', 'name=pages&amp;op=config', 'name=pages&amp;op=info'];
    $tabs = [_HOME, _ADD, _NEW, _PREFERENCES, _DOCS];
    $sub = getPagesSearch();
    if (getVar('req', 'status', 'num', 0) == 1) {
        $status = '0';
        $refer = '&amp;refer=1';
        $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => 2, 'subtitle_html' => $sub]);
    } else {
        $status = '1';
        $refer = '';
        $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'subtitle_html' => $sub]);
    }
    $where = 'p.status = :status';
    $wcnt = 'status = :status';
    $pars = ['status' => $status];
    if ($chng !== '') {
        if ($search === 1) {
            $pars['find'] = '%'.$chng.'%';
            $where .= ' AND p.id LIKE :find';
            $wcnt .= ' AND id LIKE :find';
        } elseif ($search === 2) {
            $pars['find'] = '%'.$chng.'%';
            $where .= ' AND p.title LIKE :find';
            $wcnt .= ' AND title LIKE :find';
        } elseif ($search === 3) {
            $pars['fnam'] = '%'.$chng.'%';
            $pars['fusr'] = '%'.$chng.'%';
            $where .= ' AND (p.name LIKE :fnam OR u.name LIKE :fusr)';
            $wcnt .= ' AND (name LIKE :fnam OR uid IN (SELECT id FROM '.PREFIX_DB.'_users WHERE name LIKE :fusr))';
        } elseif ($search === 4) {
            $pars['find'] = '%'.$chng.'%';
            $where .= ' AND p.cid IN (SELECT id FROM '.PREFIX_DB.'_categories WHERE modul = \'pages\' AND title LIKE :find)';
            $wcnt .= ' AND cid IN (SELECT id FROM '.PREFIX_DB.'_categories WHERE modul = \'pages\' AND title LIKE :find)';
        } elseif ($search === 5) {
            $pars['find'] = '%'.$chng.'%';
            $where .= ' AND p.ip LIKE :find';
            $wcnt .= ' AND ip LIKE :find';
        }
    }
    $field = 'name=pages'.($status === '0' ? '&amp;status=1' : '').'&amp;search='.$search.($chng !== '' ? '&amp;chng='.urlencode($chng) : '').'&amp;';
    $result = $db->getSqlQuery('SELECT p.id, p.cid, p.name, p.title, p.time, p.ip, t.title, u.name FROM '.PREFIX_DB.'_pages AS p LEFT JOIN '.PREFIX_DB.'_categories AS t ON (p.cid = t.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uid = u.id) WHERE '.$where.' ORDER BY p.time DESC LIMIT '.$offset.', '.$anum, $pars);
    if ($db->getSqlRowCount($result) > 0) {
        $rows = '';
        while ([$id, $cid, $uname, $title, $time, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $ctitle = $cid ? $ctitle : _NO;
            $ip = $ip ? Geoip::getIpHtml($ip) : _NO;
            $post = $nick ? filterTextHighlight(user_info($nick), $chng) : filterTextHighlight($uname ?: _ANONYM, $chng);
            $items = [];
            if ($status && time() >= strtotime($time)) {
                $items[] = ['href' => 'index.php?name=pages&amp;op=view&amp;id='.$id, 'label' => _MVIEW, 'title' => _MVIEW];
                $active = '1';
            } else {
                $active = '0';
            }
            $items[] = ['href' => $afile.'.php?name=pages&amp;op=add&amp;id='.$id, 'label' => _FULLEDIT, 'title' => _FULLEDIT];
            $items[] = [
                'href' => $afile.'.php?name=pages&amp;op=delete&amp;id='.$id.$refer.'&amp;token='.getSiteToken(),
                'label' => _ONDELETE,
                'title' => _ONDELETE,
                'onclick_attr' => ' OnClick="return confirm(\''._DELETE.' &quot;'.addslashes($title).'&quot;?\')"',
            ];
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['is_col_id' => true, 'content_html' => filterTextHighlight((string)$id, $chng)],
                    ['is_truncate' => true, 'title_text' => $title, 'content_html' => $tpl->getHtmlFrag('popover', [
                        'items' => [
                            ['label' => _CATEGORY, 'value' => $cid ? filterTextHighlight($ctitle, $chng) : _NO],
                            ['label' => _DATE, 'value' => format_time($time, _TIMESTRING)],
                            ['label' => _IP, 'value' => $ip ? filterTextHighlight($ip, $chng) : _NO, 'is_last' => true],
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
        $catres = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = \'pages\' ORDER BY ordern ASC');
        while ([$catid, $cattitle] = $db->getSqlRow($catres)) {
            $catopts .= $tpl->getHtmlFrag('select-option', [
                'value_attr' => (string)$catid,
                'label_text' => $cattitle,
            ]);
        }
        $modopts =
            $tpl->getHtmlFrag('select-option', ['value_attr' => 'a1', 'label_text' => _ACTIVATE])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 'a0', 'label_text' => _DEACTIVATE])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 'h1', 'label_text' => _LHOME])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 'h0', 'label_text' => _LNHOME])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 't1', 'label_text' => _LADATE])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 'c0', 'label_text' => _DEACTIVATE])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 'c1', 'label_text' => _APOSTMOD])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 'c2', 'label_text' => _APOSTNOMOD])
            .$tpl->getHtmlFrag('select-option', ['value_attr' => 'd', 'label_text' => _DELETE]);
        $actopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _OPMOD, 'is_selected' => true])
            .$tpl->getHtmlFrag('select-optgroup', ['label_text' => _OPMOD, 'options_html' => $modopts])
            .$tpl->getHtmlFrag('select-optgroup', ['label_text' => _MOVETO, 'options_html' => $catopts]);
        $pager = getTplPager(['limit' => $anum, 'maxpg' => $anump, 'url' => $field, 'table' => '_pages', 'field' => 'id', 'where' => $wcnt, 'where_params' => $pars]);
        $actions = $tpl->getHtmlFrag('inline-badge', ['is_action_label' => true, 'label' => _CHECKOP]).' '.$tpl->getHtmlFrag('select', ['name_attr' => 'typ', 'options_html' => $actopts])
            .$tpl->getHtmlFrag('button', ['button_type' => 'submit', 'submit_label' => _OK]);
        $body = $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php?name=pages&amp;op=actions',
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
    global $db, $afile, $conf, $stop, $tpl;
    $id = getVar('req', 'id', 'num', 0);
    $pid = $id;
    if ($pid) {
        $result = $db->getSqlQuery('SELECT p.cid, p.name, p.title, p.time, p.intro, p.body, p.ihome, p.acomm, u.name FROM '.PREFIX_DB.'_pages AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uid = u.id) WHERE p.id = :pid', ['pid' => $pid]);
        [$cat, $uname, $subject, $time, $hometext, $bodytext, $ihome, $acomm, $nick] = $db->getSqlRow($result);
        $postname = $nick ?: ($uname ?: _ANONYM);
    } else {
        $pid = getVar('post', 'pid', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $subject = getVar('post', 'subject', 'title', '');
        $cat = getVar('post', 'cat', 'num', 0);
        $hometext = getVar('post', 'hometext', 'text', '');
        $bodytext = getVar('post', 'bodytext', 'text', '');
        $time = getVar('req', 'time', 'time');
        $acomm = getVar('post', 'acomm', 'num', 0);
        $ihome = getVar('post', 'ihome', 'num', 0);
    }
    setHead();
    $ops = ['name=pages', 'name=pages&amp;op=add', 'name=pages&amp;status=1', 'name=pages&amp;op=config', 'name=pages&amp;op=info'];
    $tabs = [_HOME, _ADD, _NEW, _PREFERENCES, _DOCS];
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'lines' => array_values((array)$stop)]);
    if ($hometext) $cont .= getTplPreviewContent(['title' => $subject, 'texta' => $hometext, 'textb' => $bodytext, 'mod' => 'pages']);
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _PAGENOTE]);
    $catopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _HOMECAT, 'is_selected' => !$cat]);
    $catres = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = \'pages\' ORDER BY ordern ASC');
    while ([$cid, $ctitle] = $db->getSqlRow($catres)) {
        $catopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$cid,
            'label_text' => $ctitle,
            'is_selected' => (int)$cid === (int)$cat,
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
        ['label_html' => _TEXT, 'field_html' => getTplTextarea(['id' => '1', 'name' => 'hometext', 'value' => $hometext, 'mod' => 'pages', 'rows' => 5, 'placeholder' => _TEXT, 'required' => '1']), 'is_full' => true, 'field_unwrapped' => true],
        ['label_html' => _ENDTEXT, 'field_html' => getTplTextarea(['id' => '2', 'name' => 'bodytext', 'value' => $bodytext, 'mod' => 'pages', 'rows' => 15, 'placeholder' => _ENDTEXT, 'required' => '0']), 'is_full' => true, 'field_unwrapped' => true],
        ['label_html' => _CHNGSTORY, 'field_html' => getTplAddDateTime(['name' => 'time', 'time' => $time, 'with' => true, 'max' => 16])],
        ['label_html' => _PUBHOME, 'field_html' => getTplRadioGroup(['name' => 'ihome', 'value' => (string)$ihome, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _COMMENTS, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'acomm', 'options_html' => $commopts])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=pages&amp;op=save',
        'hidden' => [
            ['nameattr' => 'pid', 'valueattr' => (string)$pid],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'actions_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'posttype', 'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => 'preview', 'label_text' => _PREVIEW]).$tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SEND]).($pid ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'delete', 'label_text' => _DELETE]) : ''), 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
        'rows' => $rows,
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $pid = getVar('post', 'pid', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $subject = getVar('post', 'subject', 'title', '');
    $cat = getVar('post', 'cat', 'num', 0);
    $hometext = getVar('post', 'hometext', 'text', '');
    $bodytext = getVar('post', 'bodytext', 'text', '');
    $ihome = getVar('post', 'ihome', 'num', 0);
    $acomm = getVar('post', 'acomm', 'num', 0);
    $time = getVar('req', 'time', 'time');
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
            if ($pid) {
                setContentActive('_pages', [$pid], 35);
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_pages SET cid = :cat, uid = :uid, name = :name, title = :title, time = :time, intro = :intro, body = :body, ihome = :ihome, acomm = :acomm WHERE id = :pid', ['cat' => $cat, 'uid' => $postid, 'name' => $postname, 'title' => $subject, 'time' => $time, 'intro' => $hometext, 'body' => $bodytext, 'ihome' => $ihome, 'acomm' => $acomm, 'pid' => $pid]);
            } else {
                $ip = getip();
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_pages (id, cid, uid, name, title, time, intro, body, comments, counter, ihome, acomm, score, ratings, ip, status) VALUES (NULL, :cat, :uid, :name, :title, :time, :intro, :body, \'0\', \'0\', :ihome, :acomm, \'0\', \'0\', :ip, \'1\')', ['cat' => $cat, 'uid' => $postid, 'name' => $postname, 'title' => $subject, 'time' => $time, 'intro' => $hometext, 'body' => $bodytext, 'ihome' => $ihome, 'acomm' => $acomm, 'ip' => $ip]);
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
        updatePagesAction($pid, 'd');
        return;
    }
    setRedirect($afile.'.php?name=pages', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

# Apply selected page list action
function updatePagesAction(int|array $ids = 0, string $vtyp = ''): void {
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
    $refval = getVar('req', 'refer', 'num', 0);
    $refer = ($refval == 1) ? '&status=1' : '';
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
                setContentActive('_pages', $all, 35);
            } else {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_pages SET status = :typ WHERE id IN ('.$in.')', ['typ' => 0] + $pars);
            }
        } elseif ($typ[0] === 'h') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_pages SET ihome = :typ WHERE id IN ('.$in.')', ['typ' => (int)substr($typ, 1)] + $pars);
        } elseif ($typ[0] === 't') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_pages SET time = NOW() WHERE id IN ('.$in.')', $pars);
        } elseif ($typ[0] === 'c') {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_pages SET acomm = :typ WHERE id IN ('.$in.')', ['typ' => (int)substr($typ, 1)] + $pars);
        } elseif ($typ[0] === 'd') {
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid IN ('.$in.') AND modul = \'pages\'', $pars);
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid IN ('.$in.') AND modul = \'pages\'', $pars);
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_pages WHERE id IN ('.$in.')', $pars);
        } elseif (is_numeric($typ)) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_pages SET cid = :typ WHERE id IN ('.$in.')', ['typ' => (int)$typ] + $pars);
        }
    }
    $succ = ($typ !== '' && $typ[0] === 'd') ? _SUCCDELETE : _SUCCSTATUS;
    setRedirect($afile.'.php?name=pages'.$refer, false, 302, $iswarn ? _TOKENMISS : $succ, $iswarn);
}

function delete(int $did = 0): void {
    $id = $did ?: getVar('req', 'id', 'num', 0);
    updatePagesAction($id, 'd');
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $ops = ['name=pages', 'name=pages&amp;op=add', 'name=pages&amp;status=1', 'name=pages&amp;op=config', 'name=pages&amp;op=info'];
    $tabs = [_HOME, _ADD, _NEW, _PREFERENCES, _DOCS];
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => 3]);
    $cont .= checkPerms(CONFIG_DIR.'/pages.php');
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows = [
        ['label_html' => _CDEFIS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'defis', 'value_attr' => urldecode($conf['pages']['defis'] ?? '')])],
        ['label_html' => _PAGELINKNUM, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'linknum', 'value_attr' => $conf['pages']['linknum'] ?? 10])],
        ['label_html' => _C_13, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'listnum', 'value_attr' => $conf['pages']['listnum'] ?? 10])],
        ['label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => $conf['pages']['num'] ?? 25])],
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => $conf['pages']['anum'] ?? 25])],
        ['label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'nump', 'value_attr' => $conf['pages']['nump'] ?? 10])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => $conf['pages']['anump'] ?? 10])],
        ['label_html' => _HOMCAT, 'field_html' => getTplRadioGroup(['name' => 'homcat', 'value' => (string)($conf['pages']['homcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _VIEWCAT, 'field_html' => getTplRadioGroup(['name' => 'viewcat', 'value' => (string)($conf['pages']['viewcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_32, 'field_html' => getTplRadioGroup(['name' => 'catdesc', 'value' => (string)($conf['pages']['catdesc'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_15, 'field_html' => getTplRadioGroup(['name' => 'subcat', 'value' => (string)($conf['pages']['subcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _ADDAMAIL, 'field_html' => getTplRadioGroup(['name' => 'addmail', 'value' => (string)($conf['pages']['addmail'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_39, 'field_html' => getTplRadioGroup(['name' => 'add', 'value' => (string)($conf['pages']['add'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_40, 'field_html' => getTplRadioGroup(['name' => 'addquest', 'value' => (string)($conf['pages']['addquest'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_37, 'field_html' => getTplRadioGroup(['name' => 'autor', 'value' => (string)($conf['pages']['autor'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_17, 'field_html' => getTplRadioGroup(['name' => 'date', 'value' => (string)($conf['pages']['date'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_18, 'field_html' => getTplRadioGroup(['name' => 'read', 'value' => (string)($conf['pages']['read'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_19, 'field_html' => getTplRadioGroup(['name' => 'rate', 'value' => (string)($conf['pages']['rate'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_20, 'field_html' => getTplRadioGroup(['name' => 'letter', 'value' => (string)($conf['pages']['letter'] ?? 0), 'options' => $yesno])],
        ['label_html' => _PAGELINK, 'field_html' => getTplRadioGroup(['name' => 'link', 'value' => (string)($conf['pages']['link'] ?? 0), 'options' => $yesno])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=pages&amp;op=configsave',
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
            'linknum' => getVar('post', 'linknum', 'num', 10),
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
            'link' => getVar('post', 'link', 'num', 0),
        ];
        setConfigFile('pages.php', $cont);
    }
    setRedirect($afile.'.php?name=pages&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=pages', 'name=pages&amp;op=add', 'name=pages&amp;status=1', 'name=pages&amp;op=config', 'name=pages&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _DOCS],
    ]);
}

switch ($op) {
    default: pages(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'actions': updatePagesAction(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
