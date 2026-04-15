<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('account')) die('Illegal file access');

function getAccountSearch(): string {
    global $afile, $tpl;
    $search = getVar('req', 'search', 'num', 2);
    $chng = getVar('req', 'chng');
    $search = $search > 0 ? $search : 2;
    $searchopts =
        $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _ID, 'is_selected' => $search === 1]) .
        $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _NICKNAME, 'is_selected' => $search === 2]) .
        $tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _EMAIL, 'is_selected' => $search === 3]) .
        $tpl->getHtmlFrag('select-option', ['value_attr' => '4', 'label_text' => _IP, 'is_selected' => $search === 4]) .
        $tpl->getHtmlFrag('select-option', ['value_attr' => '5', 'label_text' => _URL, 'is_selected' => $search === 5]);
    $form = $tpl->getHtmlFrag('form', [
        'action_url' => $afile.'.php?name=account',
        'content_html' =>
            _SEARCH.': '.
            $tpl->getHtmlFrag('select', ['name_attr' => 'search', 'options_html' => $searchopts]).
            ' '.
            $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'chng', 'value_attr' => $chng, 'maxlength_num' => 30]).
            ' '.
            $tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
    ]);
    return $tpl->getHtmlPart('searchbox', ['searchbox' => $form]);
}


function account(): void {
    global $db, $afile, $conf, $tpl;
    $search = getVar('req', 'search', 'num');
    $chng = getVar('req', 'chng');
    $search = $search > 0 ? $search : 2;
    setHead();
    $cont = getTplAdminTabs([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'subtitle_html' => getAccountSearch(),
    ]);
    $where = '1 = 1';
    $wcnt = '1 = 1';
    $order = 'ORDER BY u.id DESC';
    $params = [];
    if ($search == 1 && $chng) {
        $where = 'u.id LIKE :search';
        $wcnt = 'id LIKE :search';
        $order = 'ORDER BY u.id ASC';
        $params['search'] = '%'.$chng.'%';
    } elseif ($search == 2 && $chng) {
        $where = 'u.name LIKE :search';
        $wcnt = 'name LIKE :search';
        $order = 'ORDER BY u.name ASC';
        $params['search'] = '%'.$chng.'%';
    } elseif ($search == 3 && $chng) {
        $where = 'u.email LIKE :search';
        $wcnt = 'email LIKE :search';
        $order = 'ORDER BY u.email ASC';
        $params['search'] = '%'.$chng.'%';
    } elseif ($search == 4 && $chng) {
        $where = 'u.ip LIKE :search';
        $wcnt = 'ip LIKE :search';
        $order = 'ORDER BY u.ip ASC';
        $params['search'] = '%'.$chng.'%';
    } elseif ($search == 5 && $chng) {
        $where = 'u.website LIKE :search';
        $wcnt = 'website LIKE :search';
        $order = 'ORDER BY u.website ASC';
        $params['search'] = '%'.$chng.'%';
    } elseif ($search == 6 && $chng) {
        $where = 'u.grp = :grp';
        $wcnt = 'grp = :grp';
        $order = 'ORDER BY u.id ASC';
        $params['grp'] = $chng;
    } elseif ($search == 7 && $chng) {
        $where = 'u.points >= :pts';
        $wcnt = 'points >= :pts';
        $order = 'ORDER BY u.id ASC';
        $params['pts'] = $chng;
    }
    $num = getVar('get', 'num', 'num', '1');
    $num = $num > 0 ? $num : 1;
    $offset = ($num - 1) * $conf['users']['anum'];
    $pars = $params;
    $params['offset'] = $offset;
    $params['limit'] = $conf['users']['anum'];
    $sql = 'SELECT u.id, u.name, u.email, u.website, u.regdate, u.lastvis, u.points, u.ip, u.gender, u.agent, g.name, g.color FROM '.PREFIX_DB.'_users AS u LEFT JOIN '.PREFIX_DB.'_groups AS g ON (g.id = u.grp) WHERE '.$where.' '.$order.' LIMIT :offset, :limit';
    $res = $db->getSqlQuery($sql,$params);
    $body = '';
    if ($db->getSqlRowCount($res) > 0) {
        $rows = [];
        while ([$uid, $name, $mail, $site, $reg, $last, $point, $ip, $gender, $agent, $gname, $gcolor] = $db->getSqlRow($res)) {
            $sgroup = $gname ?: _NO;
            $web = $site ? domain($site, 40) : _NO;
            $titleitems = [
                ['label' => _HASH, 'value' => md5($agent)],
                ['label' => _LAST_VISIT, 'value' => format_time($last, _TIMESTRING)],
                ['label' => _SPEC_GROUP, 'value' => $sgroup],
                ['label' => _SITE, 'value' => filterTextHighlight($web, $chng)],
                ['label' => _GENDER, 'value' => gender($gender)],
                ['label' => _POINTS, 'value' => (string)$point, 'is_last' => true],
            ];
            $delhref = $afile.'.php?name=account&amp;op=delete&amp;id='.$uid.'&amp;token='.getSiteToken();
            $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['content_html' => filterTextHighlight((string)$uid, $chng)],
                    ['content_html' => $tpl->getHtmlFrag('title-tip', [
                        'items' => $titleitems,
                        'title_text' => $name,
                    ]).filterTextHighlight($name, $chng)],
                    ['content_html' => filterTextHighlight(user_geo_ip($ip, 4), $chng)],
                    ['content_html' => filterTextHighlight($mail, $chng)],
                    ['content_html' => format_time($reg, _TIMESTRING)],
                    ['content_html' => $tpl->getHtmlFrag('row-actions', [
                        'trigger_label' => _FUNCTIONS,
                        'items' => [
                            [
                                'href' => $afile.'.php?name=account&amp;op=add&amp;id='.$uid,
                                'label' => _FULLEDIT,
                                'title' => _FULLEDIT,
                            ],
                            [
                                'href' => $afile.'.php?name=security&amp;op=banlist&amp;new_ip='.$ip,
                                'label' => _BANIPSENDER,
                                'title' => _BANIPSENDER,
                                'onclick_attr' => 'OnClick="return DelCheck(this, \''._BANIPSENDER.' &quot;'.htmlspecialchars($ip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"',
                            ],
                            [
                                'href' => $delhref,
                                'label' => _ONDELETE,
                                'title' => _ONDELETE,
                                'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"',
                            ],
                        ],
                    ])],
                ],
            ])]);
        }
        $body .= $tpl->getHtmlFrag('table', [
            'head' => [
                ['content' => _ID],
                ['content' => _NICKNAME],
                ['content' => _IP],
                ['content' => _EMAIL],
                ['content' => _REG],
                ['content' => _FUNCTIONS, 'nosort' => true],
            ],
            'rows_html' => implode('', $rows),
            'is_wrapless' => true,
        ]);
        [$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_users WHERE '.$wcnt, $pars));
        $count = (int)$count;
        if ($count > (int)$conf['users']['anum']) {
            $pages = (int)ceil($count / (int)$conf['users']['anum']);
            $curr = max(1, min($num, $pages));
            $maxpg = (int)$conf['users']['anump'];
            $dots = $tpl->getHtmlFrag('pager-dots', []);
            $base = $afile.'.php?name=account'.($search ? '&search='.$search : '').($chng !== '' ? '&chng='.urlencode($chng) : '').'&num=';
            $link = static function(string $href, string $label, bool $cur = false, bool $nav = false) use ($tpl): string {
                return $tpl->getHtmlFrag('pager-link', $href !== ''
                    ? ['href' => $href, 'label' => $label, 'title' => $label, 'is_nav' => $nav]
                    : ['label' => $label, 'title' => $label, 'is_cur' => $cur, 'is_nav' => $nav]
                );
            };
            $items = '';
            $nnum = $maxpg + 1;
            for ($i = 1; $i <= $pages; $i++) {
                if ($i === $curr) {
                    $items .= $link('', (string)$i, true);
                } elseif ($i === 1 || $i === $pages || (($i > ($curr - $maxpg)) && ($i < ($curr + $maxpg)))) {
                    $items .= $link($base.$i, (string)$i).' ';
                }
                if ($i < $pages) {
                    if (($curr > $nnum) && ($i === 1)) $items .= $dots;
                    if (($curr < ($pages - $maxpg)) && ($i === ($pages - 1))) $items .= $dots;
                }
            }
            $body .= $tpl->getHtmlFrag('pager', [
                'prev' => ($curr > 1) ? $link($base.($curr - 1), _BACK, false, true) : $link('', _BACK, true, true),
                'items' => $items,
                'next' => ($curr < $pages) ? $link($base.($curr + 1), _NEXT, false, true) : $link('', _NEXT, true, true),
            ]);
        }
    } else {
        $body .= $tpl->getHtmlFrag('alert', ['text' => _USERNOEXIST]);
    }
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop, $tpl;
    $id = getVar('req', 'id', 'num');
    if ($id > 0) {
        $result = $db->getSqlQuery('SELECT id, name, rank, email, website, avatar, regdate, occ, origin, interest, sig, viewmail, password, storynum, blockon, block, theme, newslet, lang, points, warnings, access, grp, birthday, gender, field FROM '.PREFIX_DB.'_users WHERE id = :id', ['id' => $id]);
        [$uid, $uname, $rank, $email, $site, $avatar, $reg, $occ, $from, $inter, $sig, $view, $pass, $story, $blockon, $block, $theme, $news, $lang, $point, $warn, $access, $group, $birth, $gender, $field] = $db->getSqlRow($result);
        $warn = ($warn) ? explode('|', $warn) : [];
    } else {
        $uid = getVar('post', 'uid', 'num', 0);
        $uname = getVar('post', 'uname', 'name', '');
        $rank = getVar('post', 'rank', 'text', '');
        $email = getVar('post', 'email', 'text', '');
        $site = getVar('post', 'site', 'url', 'http://');
        $avatar = getVar('post', 'avatar', 'text', '');
        $reg = getVar('post', 'reg', 'time', date('Y-m-d H:i:s'));
        $occ = getVar('post', 'occ', 'text', '');
        $from = getVar('post', 'from', 'text', '');
        $inter = getVar('post', 'inter', 'text', '');
        $sig = getVar('post', 'sig', 'text', '');
        $view = getVar('post', 'view', 'num', 0);
        $pass = getVar('post', 'pass', 'text', '');
        $story = getVar('post', 'story', 'num', (int)($conf['news']['num'] ?? 10));
        $blockon = getVar('post', 'blockon', 'num', 0);
        $block = getVar('post', 'block', 'text', '');
        $theme = getVar('post', 'theme', 'text', '');
        $news = getVar('post', 'news', 'num', 0);
        $lang = getVar('post', 'lang', 'text', '');
        $point = getVar('post', 'point', 'text', '0');
        $warn = getVar('post', 'warn', '', []);
        $access = getVar('post', 'access', 'num', 0);
        $group = getVar('post', 'group', 'num', 0);
        $birth = getVar('post', 'birth', 'time', '');
        $gender = getVar('post', 'gender', 'num', 0);
        $field = getVar('post', 'field', 'field', '');
    }
    $uname = (string)$uname;
    $rank = (string)$rank;
    $email = (string)$email;
    $site = (string)$site;
    $avatar = (string)$avatar;
    $reg = (string)$reg;
    $occ = (string)$occ;
    $from = (string)$from;
    $inter = (string)$inter;
    $sig = (string)$sig;
    $pass = (string)$pass;
    $block = (string)$block;
    $theme = (string)$theme;
    $lang = (string)$lang;
    $point = (string)$point;
    $birth = (string)$birth;
    $field = (string)$field;
    $warn = is_array($warn) ? $warn : [];
    setHead();
    $cont = getTplAdminTabs([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'subtitle_html' => getAccountSearch(),
        'tab'  => 1,
    ]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
    $rows = [
        [
            'label_html' => _NICKNAME.':',
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'uname', 'value_attr' => $uname, 'maxlength_num' => 25, 'placeholder_text' => _NICKNAME, 'is_required' => true]),
        ],
        [
            'label_html' => _URANK.':',
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'rank', 'value_attr' => $rank, 'maxlength_num' => 25, 'placeholder_text' => _URANK]),
        ],
        [
            'label_html' => _EMAIL.':',
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'email', 'name_attr' => 'email', 'value_attr' => $email, 'maxlength_num' => 255, 'placeholder_text' => _EMAIL, 'is_required' => true]),
        ],
        [
            'label_html' => _SITEURL.':',
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'url', 'name_attr' => 'site', 'value_attr' => $site, 'maxlength_num' => 255, 'placeholder_text' => _SITEURL]),
        ],
    ];
    if ($avatar !== '') {
        $rows[] = [
            'label_html' => _AVATAR.':',
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'avatar', 'value_attr' => $avatar, 'maxlength_num' => 255, 'placeholder_text' => _AVATAR]),
        ];
    }
    $rows[] = [
        'label_html' => _REG.':',
        'field_html' => getTplAddDateTime(['name' => 'reg', 'time' => (string)($reg ?? ''), 'with' => true, 'max' => 16, 'attr' => 'class="sl-select-config"']),
    ];
    $rows[] = [
        'label_html' => _OCCUPATION.':',
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'occ', 'value_attr' => $occ, 'maxlength_num' => 100, 'placeholder_text' => _OCCUPATION]),
    ];
    $rows[] = [
        'label_html' => _LOCATION.':',
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'from', 'value_attr' => $from, 'maxlength_num' => 100, 'placeholder_text' => _LOCATION]),
    ];
    $rows[] = [
        'label_html' => _INTERESTS.':',
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'inter', 'value_attr' => $inter, 'maxlength_num' => 150, 'placeholder_text' => _INTERESTS]),
    ];
    $rows[] = [
        'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _SIGNATURE, 'hint' => _SIGNATURE_TEXT]),
        'field_html' => getTplTextarea(['id' => '1', 'name' => 'sig', 'value' => $sig, 'mod' => 'account', 'rows' => '5', 'placeholder' => _SIGNATURE, 'required' => '']),
    ];
    $rows[] = [
        'label_html' => _ALLOWUSERS,
        'field_html' => getTplRadioGroup(['name' => 'view', 'value' => (string)$view, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
    ];
    if ($conf['users']['news'] == 1) {
        $storyopts = '';
        for ($n = 3; $n <= 20; $n++) {
            $storyopts .= $tpl->getHtmlFrag('select-option', [
                'value_attr' => (string)$n,
                'label_text' => (string)$n,
                'is_selected' => $n == $story,
            ]);
        }
        $rows[] = [
            'label_html' => _C_12.':',
            'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'story', 'options_html' => $storyopts]),
        ];
    }
    $rows[] = [
        'label_html' => _ACTIVATEPERSONAL,
        'field_html' => getTplRadioGroup(['name' => 'blockon', 'value' => (string)$blockon, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
    ];
    $rows[] = [
        'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _MENUCONF, 'hint' => _MENUINFO]),
        'field_html' => getTplTextarea(['id' => '2', 'name' => 'block', 'value' => $block, 'mod' => 'account', 'rows' => '5', 'placeholder' => _MENUCONF, 'required' => '']),
    ];
    if ($conf['users']['theme']) {
        $themeopts = '';
        $themecount = 0;
        foreach (scandir('templates') as $file) {
            if (!preg_match('/\./', $file) && $file != 'admin') {
                $themeopts .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => $file,
                    'label_text' => $file,
                    'is_selected' => $file == $theme,
                ]);
                $themecount++;
            }
        }
        if ($themecount > 1) {
            $rows[] = [
                'label_html' => _THEME.':',
                'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'theme', 'options_html' => $themeopts]),
            ];
        }
    }
    $rows[] = [
        'label_html' => _RNEWSLETTER.':',
        'field_html' => getTplRadioGroup(['name' => 'news', 'value' => (string)$news, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
    ];
    if ($conf['multilingual'] == 1) {
        $rows[] = [
            'label_html' => _LANGUAGE.':',
            'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'lang', 'options_html' => language($lang)]),
        ];
    }
    $rows[] = [
        'label_html' => _POINTS.':',
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'point', 'value_attr' => $point, 'placeholder_text' => _POINTS]),
    ];
    for ($i = 0; $i < 5; $i++) {
        $a = $i + 1;
        $rows[] = [
            'label_html' => _UWARN.' - '.$a.':',
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'warn[]', 'value_attr' => empty($warn[$i]) ? '' : filterText((string)$warn[$i]), 'placeholder_text' => _UWARN.' - '.$a]),
        ];
    }
    $rows[] = [
        'label_html' => _UACESS,
        'field_html' => getTplRadioGroup(['name' => 'access', 'value' => (string)$access, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
    ];
    $grpopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _NO]);
    $result = $db->getSqlQuery('SELECT id, name FROM '.PREFIX_DB.'_groups WHERE extra = :extra', ['extra' => '1']);
    while ([$grid, $grname] = $db->getSqlRow($result)) {
        $grpopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$grid,
            'label_text' => $grname,
            'is_selected' => $grid == $group,
        ]);
    }
    $gender = intval($gender ?? 0);
    $rows[] = [
        'label_html' => _SPEC_GROUP.':',
        'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'group', 'options_html' => $grpopts]),
    ];
    $rows[] = [
        'label_html' => _BIRTHDAY.':',
        'field_html' => getTplAddDateTime(['name' => 'birth', 'time' => (string)$birth, 'with' => false, 'max' => 10, 'attr' => 'class="sl-select-config"']),
    ];
    $rows[] = [
        'label_html' => _GENDER.':',
        'field_html' => get_gender('gender', $gender, 'sl-select-config'),
    ];
    $fieldvals = explode('|', $field);
    $fieldcfgs = explode('||', (string)$conf['fields']['account']);
    foreach ($fieldcfgs as $idx => $cfg) {
        if ($cfg === '') {
            continue;
        }
        preg_match('#(.*)\|(.*)\|(.*)\|(.*)#i', $cfg, $out);
        if (($out[1] ?? '0') === '0') {
            continue;
        }
        $fieldvalue = $fieldvals[$idx] ?? ($out[2] ?? '');
        $required = (($out[4] ?? '0') == 1);
        $fieldhtml = '';
        if (($out[3] ?? '0') == 1) {
            $fieldhtml = $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'field[]', 'value_attr' => $fieldvalue ? getConst($fieldvalue) : '', 'placeholder_text' => $fieldvalue ? getConst($fieldvalue) : '', 'is_required' => $required]);
        } elseif (($out[3] ?? '0') == 2) {
            $fieldhtml = $tpl->getHtmlFrag('textarea', ['name_attr' => 'field[]', 'value_text' => $fieldvalue, 'rows_num' => 5, 'is_required' => $required]);
        } elseif (($out[3] ?? '0') == 3) {
            $fieldopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _NO]);
            foreach (explode(',', (string)($out[2] ?? '')) as $value) {
                if ($value !== '') {
                    $fieldopts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $value, 'label_text' => $value, 'is_selected' => $value == $fieldvalue]);
                }
            }
            $fieldhtml = $tpl->getHtmlFrag('select', ['name_attr' => 'field[]', 'options_html' => $fieldopts, 'select_attr' => $required ? 'required' : '']);
        } elseif (($out[3] ?? '0') == 4) {
            $fieldhtml = getTplAddDateTime(['name' => 'field[]', 'time' => (string)$fieldvalue, 'with' => true, 'max' => 16, 'attr' => 'class="sl-select-config"']);
        } elseif (($out[3] ?? '0') == 5) {
            $fieldhtml = getTplAddDateTime(['name' => 'field[]', 'time' => (string)$fieldvalue, 'with' => false, 'max' => 10, 'attr' => 'class="sl-select-config"']);
        }
        if ($fieldhtml !== '') {
            $rows[] = [
                'label_html' => getConst((string)$out[1]).':',
                'field_html' => $fieldhtml,
            ];
        }
    }
    $rows[] = [
        'label_html' => _PASSWORD.':',
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'password', 'name_attr' => 'pass', 'value_attr' => '', 'maxlength_num' => 25, 'placeholder_text' => _PASSWORD]),
    ];
    $rows[] = [
        'label_html' => _RETYPEPASSWORD.':',
        'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'password', 'name_attr' => 'pass2', 'value_attr' => '', 'maxlength_num' => 25, 'placeholder_text' => _RETYPEPASSWORD]),
    ];
    $rows[] = [
        'label_html' => _MAIL_SENDE,
        'field_html' => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'mail', 'value_attr' => '1', 'is_checked' => getVar('cookie', 'sl_close_9', 'num') != 0, 'input_attr' => 'OnClick="CloseOpen(\'sl_close_9\', 0);"']),
    ];
    $rows[] = [
        'label_html' => '',
        'field_html' => $tpl->getHtmlFrag('div-collapse', [
            'target_id' => 'sl_close_9',
            'content_html' => $tpl->getHtmlFrag('div', ['rows' => [[
                'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _MAIL_TEXT, 'hint' => _MAIL_PASS_INFO]),
                'field_html' => getTplTextarea(['id' => '3', 'name' => 'mailtext', 'value' => replace_break(str_replace('[text]', _FOLLOWINGMEM."\n\n"._NICKNAME.': [login]\n'._PASSWORD.': [pass]', $conf['mtemp'])), 'mod' => 'account', 'rows' => '10', 'placeholder' => _MAIL_TEXT, 'required' => '']),
            ]]]),
        ]),
        'is_full' => true,
    ];
    $hidden = [
        ['nameattr' => 'uid', 'valueattr' => (string)$uid],
        ['nameattr' => 'name', 'valueattr' => 'account'],
        ['nameattr' => 'op', 'valueattr' => 'addsave'],
        ['nameattr' => 'token', 'valueattr' => getSiteToken()],
    ];
    if ($conf['users']['news'] != 1) {
        $hidden[] = ['nameattr' => 'story', 'valueattr' => (string)$conf['news']['num']];
    }
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('form', [
        'action_url' => $afile.'.php',
        'hidden' => $hidden,
        'rows' => $rows,
        'submit_label' => _SAVE,
    ])]);
    echo $cont;
    setFoot();
}

function addsave(): void {
    global $db, $afile, $conf, $stop, $prs;
    $stop = [];
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
        $uid = getVar('post', 'uid', 'num');
        $uname = getVar('post', 'uname', 'name');
        $rank = getVar('post', 'rank');
        $email = getVar('post', 'email');
        $site = getVar('post', 'site', 'url');
        $avatar = getVar('post', 'avatar', '', 'default/00.gif');
        $reg = getVar('req', 'reg', 'time');
        $occ = getVar('post', 'occ');
        $from = getVar('post', 'from');
        $inter = getVar('post', 'inter');
        $sig = getVar('post', 'sig', 'text');
        $view = getVar('post', 'view', 'num');
        $pass = getVar('post', 'pass');
        $pass2 = getVar('post', 'pass2');
        $story = getVar('post', 'story', 'num');
        $blockon = getVar('post', 'blockon', 'num');
        $block = getVar('post', 'block', 'text');
        $theme = getVar('post', 'theme');
        $news = getVar('post', 'news', 'num');
        $lang = getVar('post', 'lang');
        $point = getVar('post', 'point', 'num');
        $warnvals = getVar('post', 'warn[]', 'num');
        $warnings = is_array($warnvals) ? filterText(implode('|', str_replace('|', '', $warnvals))) : 0;
        $access = getVar('post', 'access', 'num');
        $group = getVar('post', 'group');
        $birth = getVar('req', 'birth', 'date');
        $gender = getVar('post', 'gender');
        $field = getVar('post', 'field', 'field');
        $mail = getVar('post', 'mail', 'num');

        if (!$uid && (!$uname || !$email || !$pass || !$pass2)) $stop[] = _ERROR_ALL;
        if ($uname) {
            [$existId, $existName] = $db->getSqlRow($db->getSqlQuery('SELECT id, name FROM '.PREFIX_DB.'_users WHERE name = :name', ['name' => $uname]));
            [$tempId, $tempName] = $db->getSqlRow($db->getSqlQuery('SELECT id, name FROM '.PREFIX_DB.'_users_temp WHERE name = :name', ['name' => $uname]));
            if (($uid != $existId && $uname == $existName) || ($uid != $tempId && $uname == $tempName)) $stop[] = _USEREXIST;
            [$emailId, $existEmail] = $db->getSqlRow($db->getSqlQuery('SELECT id, email FROM '.PREFIX_DB.'_users WHERE email = :email', ['email' => $email]));
            [$tempEmailId, $tempEmail] = $db->getSqlRow($db->getSqlQuery('SELECT id, email FROM '.PREFIX_DB.'_users_temp WHERE email = :email', ['email' => $email]));
            if (($uid != $emailId && $email == $existEmail) || ($uid != $tempEmailId && $email == $tempEmail)) $stop[] = _ERROR_EMAIL;
        } else {
            $stop[] = _ERROR_ALL;
        }
        if (!analyze_name($uname)) $stop[] = _ERRORINVNICK;
        checkemail($email);
        if ($pass != $pass2) $stop[] = _ERROR_PASS;
        if (!$stop) {
            $text = _SUCCSAVE;
            if ($uid) {
                if ($pass && $pass == $pass2) {
                    $saltpass = getPassHash($pass);
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET name = :name, rank = :rank, email = :email, website = :website, avatar = :avatar, regdate = :regdate, occ = :occ, origin = :from, interest = :interests, sig = :sig, viewmail = :viewemail, password = :password, storynum = :storynum, blockon = :blockon, block = :block, theme = :theme, newslet = :newsletter, lang = :lang, points = :points, warnings = :warnings, access = :access, grp = :group, birthday = :birthday, gender = :gender, field = :field WHERE id = :id', [
                        'name' => $uname, 'rank' => $rank, 'email' => $email, 'website' => $site, 'avatar' => $avatar, 'regdate' => $reg, 'occ' => $occ, 'from' => $from, 'interests' => $inter, 'sig' => $sig, 'viewemail' => $view, 'password' => $saltpass, 'storynum' => $story, 'blockon' => $blockon, 'block' => $block, 'theme' => $theme, 'newsletter' => $news, 'lang' => $lang, 'points' => $point, 'warnings' => $warnings, 'access' => $access, 'group' => $group, 'birthday' => $birth, 'gender' => $gender, 'field' => $field, 'id' => $uid
                    ]);
                } else {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET name = :name, rank = :rank, email = :email, website = :website, avatar = :avatar, regdate = :regdate, occ = :occ, origin = :from, interest = :interests, sig = :sig, viewmail = :viewemail, storynum = :storynum, blockon = :blockon, block = :block, theme = :theme, newslet = :newsletter, lang = :lang, points = :points, warnings = :warnings, access = :access, grp = :group, birthday = :birthday, gender = :gender, field = :field WHERE id = :id', [
                        'name' => $uname, 'rank' => $rank, 'email' => $email, 'website' => $site, 'avatar' => $avatar, 'regdate' => $reg, 'occ' => $occ, 'from' => $from, 'interests' => $inter, 'sig' => $sig, 'viewemail' => $view, 'storynum' => $story, 'blockon' => $blockon, 'block' => $block, 'theme' => $theme, 'newsletter' => $news, 'lang' => $lang, 'points' => $point, 'warnings' => $warnings, 'access' => $access, 'group' => $group, 'birthday' => $birth, 'gender' => $gender, 'field' => $field, 'id' => $uid
                    ]);
                }
            } else {
                $saltpass = getPassHash($pass);
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_users (name, rank, email, website, avatar, regdate, occ, origin, interest, sig, viewmail, password, storynum, blockon, block, theme, newslet, lang, points, warnings, access, grp, birthday, gender, field) VALUES (:name, :rank, :email, :website, :avatar, :regdate, :occ, :from, :interests, :sig, :viewemail, :password, :storynum, :blockon, :block, :theme, :newsletter, :lang, :points, :warnings, :access, :group, :birthday, :gender, :field)', [
                    'name' => $uname, 'rank' => $rank, 'email' => $email, 'website' => $site, 'avatar' => $avatar, 'regdate' => $reg, 'occ' => $occ, 'from' => $from, 'interests' => $inter, 'sig' => $sig, 'viewemail' => $view, 'password' => $saltpass, 'storynum' => $story, 'blockon' => $blockon, 'block' => $block, 'theme' => $theme, 'newsletter' => $news, 'lang' => $lang, 'points' => $point, 'warnings' => $warnings, 'access' => $access, 'group' => $group, 'birthday' => $birth, 'gender' => $gender, 'field' => $field
                ]);
            }
            if ($mail) {
                $subject = $conf['sitename'].' - '._USERPASSWORD.' '.$uname;
                $mailtext = getVar('post', 'mailtext', 'text');
                $msg = nl2br($prs->filterContent(str_replace('[pass]', $pass, str_replace('[login]', $uname, $mailtext)), false, 'account'), false);
                addMail($email, $conf['adminmail'], $subject, $msg, 0, 3);
                $text = _MAIL_SEND;
            }
            setRedirect($afile.'.php?name=account', false, 302, $text);
        } else {
            add();
        }
    }
}

function newuser(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'subtitle_html' => getAccountSearch(),
        'tab'  => 2,
    ]);
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $conf['users']['anum'];
    $result = $db->getSqlQuery('SELECT id, name, email, password, regdate, code FROM '.PREFIX_DB.'_users_temp LIMIT :offset, :limit', ['offset' => $offset, 'limit' => $conf['users']['anum']]);
    $body = '';
    if ($db->getSqlRowCount($result) > 0) {
        $rows = [];
        while ([$uid, $name, $mail, $pass, $reg, $check] = $db->getSqlRow($result)) {
            $delhref = $afile.'.php?name=account&amp;op=newdrop&amp;id='.$uid.'&amp;token='.getSiteToken();
            $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['content_html' => (string)$uid],
                    ['content_html' => $name],
                    ['content_html' => $mail],
                    ['content_html' => $pass],
                    ['content_html' => $reg],
                    ['content_html' => $tpl->getHtmlFrag('row-actions', [
                        'trigger_label' => _FUNCTIONS,
                        'items' => [
                            [
                                'href' => $conf['homeurl'].'/index.php?name=account&amp;op=activate&amp;user='.urlencode($name).'&amp;num='.$check,
                                'label' => _ACTIVATE,
                                'title' => _ACTIVATE,
                            ],
                            [
                                'href' => $delhref,
                                'label' => _ONDELETE,
                                'title' => _ONDELETE,
                                'onclick_attr' => 'OnClick="return DelCheck(this, \''._DELETE.' &quot;'.htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'&quot;?\');"',
                            ],
                        ],
                    ])],
                ],
            ])]);
        }
        $body .= $tpl->getHtmlFrag('table', [
            'head' => [
                ['content' => _ID],
                ['content' => _NICKNAME],
                ['content' => _EMAIL],
                ['content' => _PASSWORD],
                ['content' => _REG],
                ['content' => _FUNCTIONS, 'nosort' => true],
            ],
            'rows_html' => implode('', $rows),
            'is_wrapless' => true,
        ]);
        $cont .= getTplPager([
            'table' => '_users_temp',
            'field' => 'id',
            'limit' => (int)$conf['users']['anum'],
            'maxpg' => (int)$conf['users']['anump'],
            'url' => 'name=account&op=newuser&',
        ]);
    } else {
        $body .= $tpl->getHtmlFrag('alert', ['text' => _NO_INFO]);
    }
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    echo $cont;
    setFoot();
}

function pointreset(): void {
    global $afile, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'subtitle_html' => getAccountSearch(),
        'tab'  => 3,
    ]);
    $rows = [
        [
            'label_html' => _POINTS.':',
            'field_html' => getTplRadioGroup([
                'name' => 'points',
                'value' => '0',
                'options' => [
                    ['value' => '1', 'label' => _YES],
                    ['value' => '0', 'label' => _NO],
                ],
            ]),
        ],
        [
            'label_html' => _RATINGS.':',
            'field_html' => getTplRadioGroup([
                'name' => 'votes',
                'value' => '0',
                'options' => [
                    ['value' => '1', 'label' => _YES],
                    ['value' => '0', 'label' => _NO],
                ],
            ]),
        ],
        [
            'label_html' => _SIGNATURE.':',
            'field_html' => getTplRadioGroup([
                'name' => 'sig',
                'value' => '0',
                'options' => [
                    ['value' => '1', 'label' => _YES],
                    ['value' => '0', 'label' => _NO],
                ],
            ]),
        ],
        [
            'label_html' => _UWARNS.':',
            'field_html' => getTplRadioGroup([
                'name' => 'warnings',
                'value' => '0',
                'options' => [
                    ['value' => '1', 'label' => _YES],
                    ['value' => '0', 'label' => _NO],
                ],
            ]),
        ],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'account'],
            ['nameattr' => 'op', 'valueattr' => 'resave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function resave(): void {
    global $db, $afile;
    $warn = !checkSiteToken();
    $points = getVar('post', 'points', 'num');
    $votes = getVar('post', 'votes', 'num');
    $warnings = getVar('post', 'warnings', 'num');
    $sig = getVar('post', 'sig', 'num');
    if (!$warn) {
        if ($points == 1) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET points = :zero', ['zero' => '0']);
        if ($votes == 1) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET votes = :zero, tvotes = :zero', ['zero' => '0']);
        if ($warnings == 1) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET warnings = :zero', ['zero' => '0']);
        if ($sig == 1) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET sig = :empty', ['empty' => '']);
    }
    setRedirect($afile.'.php?name=account', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'subtitle_html' => getAccountSearch(),
        'tab'  => 4,
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/users.php');
    $minpassopts = '';
    for ($n = 3; $n <= 10; $n++) {
        $minpassopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$n,
            'label_text' => (string)$n,
            'is_selected' => $n == $conf['users']['minpass'],
        ]);
    }
    $rows = [
        [
            'label_html' => _ADIR,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'adirectory', 'value_attr' => (string)$conf['users']['adirectory'], 'is_config' => true]),
        ],
        [
            'label_html' => _ATYPE,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'atypefile', 'value_attr' => (string)$conf['users']['atypefile'], 'is_config' => true]),
        ],
        [
            'label_html' => _ASIZE,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'amaxsize', 'value_attr' => (string)$conf['users']['amaxsize'], 'is_config' => true]),
        ],
        [
            'label_html' => _AWIDTH._AIN,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'awidth', 'value_attr' => (string)$conf['users']['awidth'], 'is_config' => true]),
        ],
        [
            'label_html' => _AHEIGHT._AIN,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'aheight', 'value_attr' => (string)$conf['users']['aheight'], 'is_config' => true]),
        ],
        [
            'label_html' => _VOTING_TIME,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'user', 'value_attr' => (string)intval($conf['users']['user_t'] / 86400), 'is_config' => true]),
        ],
        [
            'label_html' => _C_34,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)$conf['users']['anum'], 'is_config' => true]),
        ],
        [
            'label_html' => _C_36,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)$conf['users']['anump'], 'is_config' => true]),
        ],
        [
            'label_html' => _PASSWDLEN,
            'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'minpass', 'options_html' => $minpassopts, 'is_config' => true]),
        ],
        [
            'label_html' => _LOGINFL,
            'field_html' => $tpl->getHtmlFrag('select', [
                'name_attr' => 'enter',
                'options_html' =>
                    $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _LOGINL, 'is_selected' => $conf['users']['enter'] == '0']) .
                    $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _LOGINF, 'is_selected' => $conf['users']['enter'] == '1']),
                'is_config' => true,
            ]),
        ],
        [
            'label_html' => _UPDATE_POINTS,
            'field_html' => getTplRadioGroup(['name' => 'point', 'value' => (string)$conf['users']['point'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
        ],
        [
            'label_html' => _AUPLOAD,
            'field_html' => getTplRadioGroup(['name' => 'aupload', 'value' => (string)$conf['users']['aupload'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
        ],
        [
            'label_html' => _NO_MAIL_REG,
            'field_html' => getTplRadioGroup(['name' => 'nomail', 'value' => (string)$conf['users']['nomail'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
        ],
        [
            'label_html' => _USERSHOMENUM,
            'field_html' => getTplRadioGroup(['name' => 'news', 'value' => (string)$conf['users']['news'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
        ],
        [
            'label_html' => _USERIPCHECK,
            'field_html' => getTplRadioGroup(['name' => 'check', 'value' => (string)$conf['users']['check'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
        ],
        [
            'label_html' => _REGACT,
            'field_html' => getTplRadioGroup(['name' => 'reg', 'value' => (string)$conf['users']['reg'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
        ],
        [
            'label_html' => _SELTHEME,
            'field_html' => getTplRadioGroup(['name' => 'theme', 'value' => (string)$conf['users']['theme'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
        ],
        [
            'label_html' => _PROFACT,
            'field_html' => getTplRadioGroup(['name' => 'prof', 'value' => (string)$conf['users']['prof'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
        ],
        [
            'label_html' => _NETWORKACTIVE,
            'field_html' => getTplRadioGroup(['name' => 'network', 'value' => (string)$conf['users']['network'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
        ],
        [
            'label_html' => _RULACT,
            'field_html' => getTplRadioGroup(['name' => 'rule', 'value' => (string)$conf['users']['rule'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
        ],
        [
            'label_html' => _RULES,
            'field_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'rules', 'value_text' => (string)$conf['users']['rules'], 'rows_num' => 6, 'is_config' => true]),
        ],
        [
            'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _NETWORKCODE, 'hint' => _NOKOMA]),
            'field_html' => Editor::getCode(['id' => 'code', 'name' => 'code', 'lang' => 'html', 'text' => (string)$conf['users']['network_c']]),
            'is_full' => true,
        ],
        [
            'label_html' => _NAME_BLOCK,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'name', 'value_attr' => (string)$conf['users']['name_b'], 'is_config' => true]),
        ],
        [
            'label_html' => _MAIL_BLOCK,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'mail', 'value_attr' => (string)$conf['users']['mail_b'], 'is_config' => true]),
        ],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'account'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $afile, $conf;
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
        $protect = ['\n' => '', '\t' => '', '\r' => '', ' ' => ''];
        $cont = [
            'adirectory' => getVar('post', 'adirectory', 'title'),
            'atypefile' => strtolower(strtr(getVar('post', 'atypefile', 'title', 'gif,jpg,jpeg,png'), $protect)),
            'amaxsize' => getVar('post', 'amaxsize', 'num', 51200),
            'awidth' => getVar('post', 'awidth', 'num', 100),
            'aheight' => getVar('post', 'aheight', 'num', 100),
            'user_t' => getVar('post', 'user', 'num', 30) * 86400,
            'anum' => getVar('post', 'anum', 'num', 50),
            'anump' => getVar('post', 'anump', 'num', 10),
            'minpass' => getVar('post', 'minpass', 'num'),
            'enter' => getVar('post', 'enter', 'num'),
            'point' => getVar('post', 'point', 'num'),
            'aupload' => getVar('post', 'aupload', 'num'),
            'nomail' => getVar('post', 'nomail', 'num'),
            'news' => getVar('post', 'news', 'num'),
            'check' => getVar('post', 'check', 'num'),
            'reg' => getVar('post', 'reg', 'num'),
            'theme' => getVar('post', 'theme', 'num'),
            'prof' => getVar('post', 'prof', 'num'),
            'network' => getVar('post', 'network', 'num'),
            'rule' => getVar('post', 'rule', 'num'),
            'rules' => getVar('post', 'rules', 'text'),
            'network_c' => "<<<HTML\n".getVar('post', 'code', 'text')."\nHTML",
            'name_b' => strtolower(strtr(getVar('post', 'name', 'text'), $protect)),
            'mail_b' => strtolower(strtr(getVar('post', 'mail', 'text'), $protect)),
            'points' => $conf['users']['points']
        ];
        setConfigFile('users.php', $cont);
    }
    setRedirect($afile.'.php?name=account&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function newdrop(): void {
    global $db, $afile;
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
        $id = getVar('get', 'id', 'num');
        if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_users_temp WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=account&op=newuser', false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function delete(): void {
    global $db, $afile;
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
        $id = getVar('get', 'id', 'num');
        if ($id) {
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_users WHERE id = :id', ['id' => $id]);
            $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE uid = :id', ['id' => $id]);
            # $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE uid = :id', ['id' => $id]);
        }
    }
    setRedirect($afile.'.php?name=account', false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops'  => ['name=account', 'name=account&amp;op=add', 'name=account&amp;op=newuser', 'name=account&amp;op=pointreset', 'name=account&amp;op=config', 'name=account&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW_USER, _NULLPOINTS, _PREFERENCES, _INFO],
        'subtitle_html' => getAccountSearch(),
    ]);
}

switch ($op) {
    default: account(); break;
    case 'add': add(); break;
    case 'addsave': addsave(); break;
    case 'newuser': newuser(); break;
    case 'newdrop': newdrop(); break;
    case 'delete': delete(); break;
    case 'pointreset': pointreset(); break;
    case 'resave': resave(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
