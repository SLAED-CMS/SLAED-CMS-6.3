<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Render one stored comment from the row the comment subsystem answered with: the author card, the moderation actions and the body
# The running number belongs to the page the row is shown on and the token to the actions inside it, so both are passed in rather than resolved here
# Every action posts to its own route and carries no token in its URL; the token travels as a request header the comment element declares once for all of them
# A comment that offers no action declares no token either, so a reader who may change nothing is served no credential at all
function getCommentView(array $val, int $numb, string $token): string {
    global $conf, $user, $tpl, $prs;
    $cmid = $val['id'];
    $cmod = $val['modul'];
    $when = $val['time'];
    $cnam = $val['name'];
    $stat = $val['status'];
    $deep = intval($val['depth'] ?? 0);
    if (($val['deleted'] ?? '') !== '') {
        return $tpl->getHtmlFrag('comment', [
            'id' => $cmid,
            'depth' => $deep,
            'is_gone' => true,
            'username' => _ANONYM,
            'username_html' => htmlspecialchars((string)_ANONYM, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'date' => $tpl->getHtmlFrag('inline-badge', ['title_text' => (string)_PADD, 'label' => format_time($when, _TIMESTRING), 'is_comment_date' => true]),
            'text' => $tpl->getHtmlFrag('alert', ['text' => _COMMENTS_GONE, 'meta' => '', 'type' => 'info', 'is_warn' => false]),
            'closed_title' => _COMMENTS_GONE,
            'share_url' => '#'.$cmid,
        ]);
    }
    $usr = $val['user'] ?? [];
    $auid = intval($usr['id'] ?? 0);
    $anam = (string)($usr['name'] ?? '');
    $mods = is_moder($cmod);
    $avname = (!empty($anam)) ? $anam : ($cnam ?: (string)_ANONYM);
    $date = $tpl->getHtmlFrag('inline-badge', ['title_text' => (string)_PADD, 'label' => format_time($when, _TIMESTRING), 'is_comment_date' => true]);
    $ip = $mods ? Geoip::getIpHtml((string)$val['ip'], true) : '';
    $amess = $numb ? $tpl->getHtmlFrag('link', ['href' => '#'.$cmid, 'title' => (string)_COMMENT.': '.(string)$numb, 'label' => (string)$numb, 'is_card_id' => true]) : '';
    $gone = (intval($val['uid']) > 0 && empty($cnam));
    $avatar = (!empty($anam)) ? getUserAvatarUrl(['avatar' => (string)($usr['avatar'] ?? '')]) : getUserAvatarUrl([], $gone);
    $agnam = (string)($usr['gname'] ?? '');
    $trank = (!empty($agnam)) ? _GROUP.': '.$agnam : _RANK;
    $rimg = (!empty($usr['grank'])) ? getThemeImagePath('ranks/'.$usr['grank']) : '';
    $rlink = ($rimg && file_exists($rimg)) ? $tpl->getHtmlFrag('image', ['src' => $rimg, 'alt' => $trank, 'title' => $trank]) : '';
    $rate = $auid ? getRatingAsync(0, $auid, 'account', $usr['votes'] ?? 0, $usr['tvotes'] ?? 0, $cmid, 1) : '';
    $utip = getUserTip($agnam, $usr['points'] ?? 0, (string)($usr['regdate'] ?? ''), (int)($usr['gender'] ?? 0), (string)($usr['origin'] ?? ''), (string)($usr['warnings'] ?? ''), empty($anam), $gone);
    $unam = (!empty($anam)) ? user_info($anam, false) : htmlspecialchars($avname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $sig = (!empty($usr['sig'])) ? $tpl->getHtmlFrag('block-content', ['is_signature' => true, 'content' => $usr['sig']]) : '';
    $aweb = (string)($usr['website'] ?? '');
    $uitems = [];
    if (($mods || is_user() || $conf['comments']['anonpost'] != 0) && $deep + 2 <= 20) {
        $uitems[] = ['href' => '#formcsave', 'title' => _COMMENTS_REPLY, 'icon_name' => 'reply', 'link_attr' => 'data-sl-reply="'.$cmid.'"'];
    }
    if ($conf['comments']['privat'] && $conf['privat']['act'] && !empty($anam)) {
        $uitems[] = ['href' => 'index.php?name=account&op=privat&uname='.urlencode($anam), 'title' => _SENDMES, 'icon_name' => 'envelope'];
    }
    if ($conf['comments']['profil'] && !empty($anam)) {
        $uitems[] = ['href' => 'index.php?name=account&op=view&uname='.urlencode($anam), 'title' => _PERSONALINFO, 'icon_name' => 'person'];
    }
    if ($conf['comments']['web'] && !empty($aweb)) {
        $uitems[] = ['href' => $aweb, 'title' => _DOWNLLINK, 'icon_name' => 'globe', 'is_blank' => true];
    }
    $act = 'index.php?go=1&op=';
    $one = '[id=\''.$cmid.'\']';
    $form = ['href' => $act.'updateComment&id='.$cmid.'&typ=1', 'title' => _ONEDIT, 'icon_name' => 'pencil-square', 'is_htmx' => true, 'hx_target' => '#repcom'.$cmid];
    $items = [];
    if ($mods) {
        $items[] = $form;
        $items[] = ['href' => $act.'updateCommentStatus&id='.$cmid.'&typ=0&numb='.$numb, 'title' => _FMODC, 'icon_name' => 'eye-slash',
            'is_htmx' => true, 'is_post' => true, 'hx_target' => $one, 'hx_swap' => 'none'];
        $items[] = ['href' => $act.'updateCommentStatus&id='.$cmid.'&typ=1&numb='.$numb, 'title' => _ACTIVATE, 'icon_name' => 'eye',
            'is_htmx' => true, 'is_post' => true, 'hx_target' => $one, 'hx_swap' => 'none'];
        $items[] = ['href' => $act.'deleteComment&id='.$cmid, 'title' => _ONDELETE, 'icon_name' => 'trash', 'confirm_text' => (string)_ONDELETE.'?',
            'is_htmx' => true, 'is_post' => true, 'hx_target' => $one, 'hx_swap' => 'none'];
    } elseif (is_user() && $auid > 0 && $auid === intval($user[0]) && time() < strtotime($when) + $conf['comments']['edit']) {
        $items[] = $form;
    }
    $text = $tpl->getHtmlFrag('block-content', ['id' => 'repcom'.$cmid, 'content' => $prs->filterContent($val['body'], true, $cmod, 2, $val['format'])]);
    return $tpl->getHtmlFrag('comment', [
        'id' => $cmid,
        'depth' => $deep,
        'token' => $items ? $token : '',
        'username' => $avname,
        'username_html' => $unam,
        'report' => $utip,
        'date' => $date,
        'ip' => $ip,
        'post_count' => $amess,
        'avatar' => $avatar,
        'avatar_html' => $tpl->getHtmlFrag('image', [
            'src' => $avatar,
            'alt' => $avname,
            'title' => $avname,
            'is_avatar' => true,
        ]),
        'rank' => (string)($usr['rank'] ?? ''),
        'rank_link' => $rlink,
        'user_rate' => $rate,
        'text' => $text,
        'sig' => $sig,
        'btn_user' => getActionMenu($uitems, true),
        'btn_warn' => '',
        'btn_thank' => '',
        'btn_edit' => getActionMenu($items),
        'is_closed' => !$stat,
        'closed_title' => _PCLOSED,
        'share_url' => '#'.$cmid,
    ]);
}

# Render the comment rows of one page and, while the discussion continues, the control that appends the next page to the list the reader is on
# The control replaces itself with the answer, so it always stands at the end of what is loaded and no second element has to be kept in step with it
# Its href is the ordinary page URL the numbered pager also links to, so a reader without HTMX follows it as a plain link and lands on that page
function getCommentRows(array $data, string $mod, int $cid, string $token, string $pag): string {
    global $conf, $tpl;
    $numb = $data['first'];
    $cont = '';
    $open = [];
    $more = static function (array $val) use ($tpl, $mod, $cid, $token, $pag): string {
        return $tpl->getHtmlFrag('link', [
            'href' => getSeoUrl(['name' => $mod, $pag.'&all' => $val['id']]).'#'.$val['id'],
            'hx_url' => 'index.php?go=1&op=getCommentBranch&id='.$val['id'].'&skip='.$val['shown'],
            'hx_headers' => $token,
            'title' => (string)_COMMENTS_REPLIES,
            'label' => (string)_COMMENTS_REPLIES.' ('.($val['kids'] - $val['shown']).')',
            'is_htmx' => true,
            'hx_target' => 'this',
            'hx_swap' => 'outerHTML',
            'is_card_id' => true,
        ]);
    };
    foreach ($data['rows'] as $val) {
        if ($val['depth']) {
            $cont .= getCommentView($val, 0, $token);
            continue;
        }
        if ($open) $cont .= $more($open);
        $open = (intval($val['kids'] ?? 0) > intval($val['shown'] ?? 0)) ? $val : [];
        $cont .= getCommentView($val, $numb, $token);
        if ($conf['comments']['sort']) { $numb++; } else { $numb--; }
    }
    if ($open) $cont .= $more($open);
    if ($data['page'] >= $data['pages']) return $cont;
    $next = $data['page'] + 1;
    return $cont.$tpl->getHtmlFrag('link', [
        'href' => getSeoUrl(['name' => $mod, $pag.'&com' => $next]).'#comm',
        'hx_url' => 'index.php?go=1&op=getCommentPage&id='.$cid.'&mod='.$mod.'&com='.$next,
        'hx_headers' => $token,
        'title' => (string)_COMMENTS_MORE,
        'label' => (string)_COMMENTS_MORE,
        'is_htmx' => true,
        'hx_target' => 'this',
        'hx_swap' => 'outerHTML',
        'is_button_blue' => true,
    ]);
}

# Render the comment list of one target: the rows, the pagination and the author records come from the comment subsystem, this function only assembles the markup
# The page counts root comments and every root arrives with its branch behind it, so the running number follows the roots and a reply carries none
# The rows have a container of their own, because a fragment response appends to them and the pager below them is not a comment
function getCommentList(int $cid = 0, string $mod = '', int $page = 0, int $full = 0): string {
    global $conf, $tpl, $com;
    $mod = filterVar($mod);
    $data = $com->getList($mod, $cid, $page ?: 1, $full);
    $num = getVar('get', 'num', 'num');
    $pag = empty($num) ? 'op=view&id='.$cid : 'op=view&id='.$cid.'&num='.$num;
    $rows = ($data['total'] < 1)
        ? $tpl->getHtmlFrag('alert', ['text' => _NOCOMMENTS, 'meta' => '', 'type' => 'info', 'is_warn' => false])
        : getCommentRows($data, $mod, $cid, getPageToken(), $pag);
    $out = $tpl->getHtmlFrag('block-content', ['id' => 'repcrows', 'content' => $rows]);
    if ($data['total'] < 1) return $out;
    return $out.getPageNumbers($mod, $data['total'], $data['pages'], $data['limit'], $pag.'&', $conf['comments']['nump'], $data['page'], '#comm', 'com');
}

# Answer one page of the comment list as the sequence of fragments the reader appends to the list already on the page
# A page beyond the last is refused rather than clamped, because the clamp would answer the last page a second time and duplicate every row of it
function getCommentPage(): void {
    global $com;
    $id = getVar('req', 'id', 'num', 0);
    $mod = filterVar(getVar('req', 'mod', 'text', ''));
    $page = getVar('req', 'com', 'num', 1);
    $data = $com->getList($mod, $id, $page);
    if ($data['total'] < 1 || $data['page'] !== $page) return;
    echo getCommentRows($data, $mod, $id, getPageToken(), 'op=view&id='.$id);
}

# Answer the replies of one comment the reader has not been shown yet, as the sequence of fragments appended in place of the control that asked for them
# The control the answer ends with carries the new offset, so a branch can be walked in slices without the page ever loading a whole discussion at once
function getCommentBranch(): void {
    global $conf, $tpl, $com;
    $id = getVar('req', 'id', 'num', 0);
    $skip = getVar('req', 'skip', 'num', 0);
    $reps = max(1, intval($conf['comments']['reps'] ?? 5));
    $data = $com->getBranch($id, $reps, $skip);
    if (!$data['rows']) return;
    $token = getPageToken();
    $cont = '';
    foreach ($data['rows'] as $val) $cont .= getCommentView($val, 0, $token);
    if ($data['left'] > 0) {
        $seen = $skip + count($data['rows']);
        $cont .= $tpl->getHtmlFrag('link', [
            'href' => '#'.$id,
            'hx_url' => 'index.php?go=1&op=getCommentBranch&id='.$id.'&skip='.$seen,
            'hx_headers' => $token,
            'title' => (string)_COMMENTS_REPLIES,
            'label' => (string)_COMMENTS_REPLIES.' ('.$data['left'].')',
            'is_htmx' => true,
            'hx_target' => 'this',
            'hx_swap' => 'outerHTML',
            'is_card_id' => true,
        ]);
    }
    echo $cont;
}

# Render the comment list and submission form for an item
# The list, the status zone and the form are three regions a fragment response addresses on its own, so an add never replaces the region it was submitted from
# The editor field is named rather than numbered, because a view page already carries the target id and every comment id as element ids and a numeric one collides with them
function setComShow(int $id = 0, int $acomm = 0): string {
    global $conf, $user, $tpl, $com;
    $full = getVar('get', 'all', 'num', 0);
    $page = getVar('get', 'com', 'num', 0) ?: $com->getRootPage($full ?: getVar('get', 'at', 'num', 0));
    $cont = $tpl->getHtmlFrag('title', ['title' => _COMMENTS, 'is_level_two' => true]);
    $cont .= $tpl->getHtmlFrag('block-content', ['id' => 'repcsave', 'content' => getCommentList($id, $conf['name'], $page, $full)]);
    $cont .= $tpl->getHtmlFrag('block-content', ['id' => 'repcstat', 'content' => '']);
    if (!is_user() && $conf['comments']['anonpost'] == 0) {
        $cont .= $tpl->getHtmlFrag('alert', ['text' => _NOANONCOMMENTS, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
    } else {
        $userinfo = getUserInfo();
        $mode = CommentMode::tryFrom($acomm) ?? CommentMode::Disabled;
        $note = ($mode === CommentMode::Moderated || $userinfo['access'] || (!is_user() && $conf['comments']['anonpost'] == 1));
        if ($note) $cont .= $tpl->getHtmlFrag('alert', ['text' => _POSTNOTE, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
        if (is_user()) {
            $name_field = filterText(substr($user[1], 0, 25)).$tpl->getHtmlFrag('hidden', ['name_attr' => 'name', 'value_attr' => '', 'input_attr' => '']);
        } else {
            $name_field = $tpl->getHtmlFrag('input', [
                'input_attr' => 'maxlength="25"',
                'itype' => 'text',
                'name_attr' => 'name',
                'value_attr' => _ANONYM,
            ]);
        }
        $fields = $tpl->getHtmlFrag('form-field-row', ['label' => _YOURNAME, 'field_html' => $name_field])
            .$tpl->getHtmlFrag('form-field-row', [
                'label' => _COMMENT,
                'hide_label' => true,
                'field_html' => getTplTextarea([
                    'id' => 'ctext',
                    'name' => 'text',
                    'value' => '',
                    'mod' => $conf['name'],
                    'rows' => '5',
                    'placeholder' => _COMMENT,
                ]),
            ])
            .$tpl->getHtmlFrag('hidden', ['name_attr' => 'reqkey', 'value_attr' => '', 'input_attr' => 'data-sl-reqkey'])
            .$tpl->getHtmlFrag('hidden', ['name_attr' => 'pid', 'value_attr' => '', 'input_attr' => 'data-sl-reply-to'])
            .$tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getPageToken()]);
        $post = 'index.php?go=1&op=addComment&id='.$id.'&mod='.$conf['name'].'&com='.$page;
        $submit = $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit',
            'label' => _COMMENTREPLY,
            'title' => _COMMENTREPLY,
            'hx_post' => $post,
            'hx_include' => '#formcsave',
            'hx_target' => '#repcrows',
            'hx_swap' => 'afterbegin',
            'hx_on_click' => 'if (!document.getElementById(\'formcsave\').querySelector(\'[name=&quot;text&quot;]\').value.trim()) { alert(\''._CERROR1.'\'); event.preventDefault(); }',
        ]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'action' => $post,
            'form_id' => 'formcsave',
            'form_name' => 'post',
            'no_enctype' => true,
            'fields' => $fields,
            'captcha' => getPageCaptcha('comment'),
            'submit' => $submit,
        ]);
    }
    return $tpl->getHtmlFrag('block-content', ['id' => 'comm', 'is_comments_section' => true, 'content' => $cont]);
}

# Render the active site message box for the current language and user role
function setMessageShow(): string {
    global $db, $afile, $conf, $currentlang, $tpl, $prs;
    if ($conf['message'] == 1) {
        $adminNote = static function (string $viewType, string $duration, string $editUrl) use ($tpl): string {
            $edit = $tpl->getHtmlFrag('link', ['href' => $editUrl, 'title' => _EDIT, 'label' => _EDIT]);
            return $tpl->getHtmlFrag('block-content', [
                'is_center' => true,
                'content' => '['._VIEW.': '.$viewType.' | '._PURCHASED.': '.$duration.' | '.$edit.' ]',
            ]);
        };
        $params = [];
        $querylang = ($conf['multilingual'] == 1) ? 'AND (lang = :lang OR lang = \'\')' : '';
        if ($conf['multilingual'] == 1) {
            $params['lang'] = $currentlang;
        }
        $messageBox = static fn(string $title, string $body): string => $tpl->getHtmlFrag('block-content', [
            'is_message_box' => true,
            'content' => $tpl->getHtmlFrag('title', ['title' => $title, 'is_level_two' => true]).$body,
        ]);
        $result = $db->getSqlQuery('SELECT id, title, body, expire, view FROM '.PREFIX_DB.'_message WHERE status = 1 '.$querylang, $params);
        if ($db->getSqlRowCount($result) > 0) {
            while ([$mid, $title, $body, $expire, $view] = $db->getSqlRow($result)) {
                $mid = intval($mid);
                if ($expire && $expire < time()) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_message SET status = 0, expire = 0 WHERE id = :mid', ['mid' => $mid]);
                $body = $prs->filterContent($body, false, 'all', 2);
                $exp = intval($expire - time());
                $exp = ($exp > 0) ? getDuration($exp) : _UNLIMITED;
                if ($view == 4 && is_moder()) {
                    if (is_moder()) $body .= $adminNote(_MVADMIN, $exp, $afile.'.php?op=msg_add&id='.$mid);
                    return $messageBox($title, $body);
                } elseif (($view == 3 && is_user()) || ($view == 3 && is_user() && is_moder())) {
                    if (is_moder()) $body .= $adminNote(_MVUSERS, $exp, $afile.'.php?op=msg_add&id='.$mid);
                    return $messageBox($title, $body);
                } elseif (($view == 2 && !is_user()) || ($view == 2 && !is_user() && is_moder())) {
                    if (is_moder()) $body .= $adminNote(_MVANON, $exp, $afile.'.php?op=msg_add&id='.$mid);
                    return $messageBox($title, $body);
                } elseif ($view == 1) {
                    if (is_moder()) $body .= $adminNote(_MVALL, $exp, $afile.'.php?op=msg_add&id='.$mid);
                    return $messageBox($title, $body);
                }
            }
        }
    }
    return '';
}

# Build the account navigation items with icon, tone, tooltip and optional badge/sub texts per active module; $home prepends the cabinet home link for inner pages
function getUserNavItems(bool $home = false): array {
    global $db, $conf;
    $uid = intval((getUserInfo() ?? [])['id'] ?? 0);
    if ($conf['name'] !== 'account') getLang('account');
    $items = [];
    if ($home) $items[] = ['label' => _HOME, 'title' => _RETURNACCOUNT, 'href' => 'index.php?name=account', 'icon' => 'house'];
    if ($conf['privat']['act']) {
        [$new] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat WHERE uidin = :uid AND status = 0', ['uid' => $uid]));
        $items[] = ['label' => _MESSAGES, 'title' => _PRIVAT, 'href' => 'index.php?name=account&op=privat', 'icon' => 'envelope', 'badge' => $new ? (string)$new : ''];
    }
    if (is_active('clients') && isModGroup('clients')) {
        getLang('clients');
        $items[] = ['label' => _PRODUCTS, 'title' => _PRODUCTSINFO, 'href' => 'index.php?name=clients', 'icon' => 'box-seam'];
    }
    if (is_active('shop')) {
        getLang('shop');
        $items[] = ['label' => _CLIENT, 'title' => _CLIENTINFO, 'href' => 'index.php?name=shop&op=clients', 'icon' => 'people'];
        if (($conf['shop']['part'] ?? 0) === 1) {
            $items[] = ['label' => _PARTNER, 'title' => _PARTNERINFO, 'href' => 'index.php?name=shop&op=partners', 'icon' => 'briefcase'];
        }
    }
    if (is_active('help') && isModGroup('help')) {
        getLang('help');
        $items[] = ['label' => _HELP, 'title' => _HELPINFO, 'href' => 'index.php?name=help', 'icon' => 'life-preserver'];
    }
    if ($conf['favorites']['favact']) {
        [$fnum] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_favorites WHERE uid = :uid', ['uid' => $uid]));
        $items[] = [
            'label' => _FAVORITES, 'title' => _FAVORITES, 'href' => 'index.php?name=account&op=favorites',
            'icon' => 'star', 'sub' => $fnum.' / '.$conf['favorites']['favorites'],
        ];
    }
    $items[] = ['label' => _INFO, 'title' => _PERSONALINFO, 'href' => 'index.php?name=account&op=view&id='.$uid, 'icon' => 'person-vcard'];
    $items[] = ['label' => _CHANGE, 'title' => _CHANGE, 'href' => 'index.php?name=account&op=edithome', 'icon' => 'gear'];
    $items[] = ['label' => _LOGOUT, 'title' => _LOGOUT, 'href' => 'index.php?name=account&op=logout', 'icon' => 'box-arrow-right'];
    foreach ($items as $pos => $item) {
        $items[$pos]['tone'] = $pos % 6;
        if (!isset($item['sub'])) $items[$pos]['sub'] = ($item['title'] !== $item['label']) ? $item['title'] : '';
    }
    return $items;
}

# Render the compact account navigation strip with icon tiles for inner cabinet pages
function getUserNav(): string {
    global $tpl;
    return $tpl->getHtmlFrag('account-nav', ['items' => getUserNavItems(true)]);
}

# Check if the logged-in user meets the group or points requirement for a module
function isModGroup(string $name): int {
    global $db, $user;
    if (is_user()) {
        $uid = intval($user[0]);
        $row = $db->getSqlRow($db->getSqlQuery('SELECT points, grp FROM '.PREFIX_DB.'_users WHERE id = :id', ['id' => $uid]));
        $points = $row['points'] ?? 0;
        $group = $row['grp'] ?? 0;
        $mod_conf = $conf['modules'][$name] ?? [];
        $mgroup = intval($mod_conf['group'] ?? 0);
        $grpoints = 0;
        $grextra = 0;
        if ($mgroup) {
            $ginfo = $db->getSqlRow($db->getSqlQuery('SELECT points, extra FROM '.PREFIX_DB.'_groups WHERE id = :id', ['id' => $mgroup]));
            $grpoints = intval($ginfo['points'] ?? 0);
            $grextra = $ginfo['extra'] ?? 0;
        }
        if (intval($group) && $group !== '' && $group == $mgroup && $grextra === '1') {
            return 1;
        } elseif ((intval($points) && $points >= $grpoints && $grextra !== '1') || $mgroup === 0) {
            return 1;
        }
    }
    return 0;
}

# Fetch the full database record for the currently logged-in user
function getUserInfo() {
    global $db, $user;
    $uid = (isset($user[0])) ? intval($user[0]) : 0;
    if (is_user() && $uid) {
        $info = $db->getSqlRow($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_users WHERE id = :uid', ['uid' => $uid]));
        return $info;
    }
}

# Resolve the avatar URL for a user record; system avatars (user/guest/deleted) and presets come from the active theme, uploaded files from the avatar upload directory
function getUserAvatarUrl(array $userinfo = [], bool $deleted = false): string {
    global $conf;
    $base = 'templates/'.getTheme().'/images/avatars/';
    if ($deleted) return $base.'system/deleted.svg';
    if (!$userinfo) return $base.'system/guest.svg';
    $ava = $userinfo['avatar'] ?? '';
    if (str_starts_with($ava, 'presets/')) return (preg_match('#^presets/[\w.-]+\.(gif|png|jpe?g|svg)$#i', $ava) && file_exists($base.$ava)) ? $base.$ava : $base.'system/user.svg';
    return ($ava && file_exists($conf['users']['adirectory'].'/'.$ava)) ? $conf['users']['adirectory'].'/'.$ava : $base.'system/user.svg';
}

# Resolve point-level data for a user: reached point groups, ring color, progress percent and next-group hint; group color/rank override the point-group ones
function getUserLevelData(int $point, string $gcolor = '', string $grank = ''): array {
    global $db, $conf;
    $rgroup = [];
    $uranks = '';
    $ucolor = '';
    $base = 0;
    $next = 0;
    $level = 0;
    $nextlab = '';
    if ($conf['users']['point'] && $point) {
        $result = $db->getSqlQuery('SELECT name, rank, points, color FROM '.PREFIX_DB."_groups WHERE extra != '1' ORDER BY points ASC");
        while ([$guname, $gurank, $gupts, $gucol] = $db->getSqlRow($result)) {
            if ((int)$gupts > $point) {
                if (!$next) $next = (int)$gupts;
                continue;
            }
            $rgroup[] = $guname;
            $uranks = $gurank;
            $ucolor = $gucol;
            $base = (int)$gupts;
        }
        if ($next > $base) {
            $level = min(99, intval(($point - $base) / ($next - $base) * 100));
            $nextlab = sprintf(_ACCOUNT_NEXT, $next - $point);
        } else {
            $level = 100;
        }
    }
    $ring = $gcolor ?: $ucolor;
    return [
        'groups' => $rgroup,
        'rank' => $grank ?: $uranks,
        'ring' => ($ring && preg_match('/^#[0-9a-f]{6}$/i', $ring)) ? $ring : '',
        'level' => $level,
        'nextlab' => $nextlab,
    ];
}

# Drop the cached sidebar private-message counters so the next page render recounts them after a mailbox mutation
function deletePrivatCounts(): void {
    global $conf;
    unset($_SESSION[$conf['user_c'].'-privat']);
}

# Render the user's custom sidebar block if enabled
function getUserBlock(): string {
    global $db, $user, $tpl, $prs;
    $uid = (isset($user[0])) ? intval($user[0]) : 0;
    $block = (isset($user[4])) ? intval($user[4]) : 0;
    if (is_user() && $block) {
        [$userblock] = $db->getSqlRow($db->getSqlQuery('SELECT block FROM '.PREFIX_DB.'_users WHERE id = :uid', ['uid' => $uid]));
        $userblock = $prs->filterContent($userblock, false, 'account', 2);
        return $tpl->getHtmlFrag('block-all', ['title' => _MENUFOR, 'content' => $userblock]);
    }
    return '';
}

# Validate and save a new comment together with the notification of the admins subscribed to its module; answers the one comment that was stored instead of the whole list
# The handler owns the transaction the comment and its queue row share: a comment that never commits leaves no mail behind, and a job is written once per stored comment
# The queue row is a stored job and nothing more, so no message is delivered inside the request and no delivery outcome can reach this path or take the comment with it
function addComment(): void {
    global $conf, $tpl, $com, $db;
    $id   = getVar('req', 'id',   'num',  0);
    $mod  = filterVar(getVar('req', 'mod',  'text', ''));
    $name = filterText(substr(getVar('post', 'name', 'raw', ''), 0, 25));
    $body = trim(getVar('post', 'text', 'raw', ''));
    $key = (string)getVar('req', 'reqkey', 'var', '');
    $page = getVar('req', 'com', 'num', 1);
    $pid = getVar('req', 'pid', 'num', 0);
    $back = 'index.php?name='.$mod.'&op=view&id='.$id;
    $live = !empty($_SERVER['HTTP_HX_REQUEST']);
    $own = $db->setSqlBegin();
    $new = $com->addComment($mod, $id, $body, $name, $key, $pid);
    if ($new['error'] === '' && $new['new']) {
        $link = $conf['homeurl'].'/index.php?name='.$mod.'&op=view&id='.$id.'&at='.$new['id'].'#'.$new['id'];
        $clink = $tpl->getHtmlFrag('link', ['href' => $link, 'title' => '', 'label_html' => $link]);
        addAdminMail($conf['comments']['addmail'], $mod, $new['name'], getModuleName($mod), 1, $clink);
    }
    if ($new['error'] !== '' || ($own && !$db->setSqlCommit())) {
        if ($own) $db->setSqlRollback();
        if (!$live) setRedirect($back.'#comm', false, 303, $new['error'] ?: (string)_ERROR, true);
        echo $tpl->getHtmlFrag('alert', ['text' => $new['error'] ?: _ERROR, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
        return;
    }
    $row = $new['id'] ? $com->getComment(intval($new['id'])) : [];
    $open = $row && $row['status'] === CommentStatus::Published->value;
    if (!$live) setRedirect($back.($open ? '#'.$row['id'] : '#comm'), false, 303, $open ? '' : (string)_POSTNOTE, false);
    header('HX-Trigger: sl-comment-add');
    $note = static function (string $text, string $meta) use ($tpl): void {
        header('HX-Retarget: #repcstat');
        header('HX-Reswap: innerHTML');
        echo $tpl->getHtmlFrag('alert', ['text' => $text, 'meta' => $meta, 'type' => 'info', 'is_warn' => false]);
    };
    if (!$open) {
        $note((string)_POSTNOTE, '');
        return;
    }
    $data = $com->getList($mod, $id, $page);
    $numb = $data['first'];
    $at = -1;
    foreach ($data['rows'] as $pos => $one) {
        if ($one['id'] === $row['id']) {
            $at = $pos;
            break;
        }
        if ($one['depth']) continue;
        if ($conf['comments']['sort']) { $numb++; } else { $numb--; }
    }
    if ($at < 0) {
        $seen = $back.'&at='.$row['id'].'#'.$row['id'];
        $note((string)_COMMENTS_ADDED, $tpl->getHtmlFrag('link', ['href' => $seen, 'title' => (string)_COMMENT, 'label' => (string)_COMMENT.': '.$row['id'], 'is_card_id' => true]));
        return;
    }
    $size = $data['limit'];
    $total = $data['total'];
    $drop = 0;
    if (!$row['pid']) {
        if ($data['isasc']) {
            if ($total > 1 && ($total - 1) % $size === 0) $drop = intdiv($total - 1, $size);
        } elseif ($total > $size) {
            $drop = 2;
        }
    }
    $off = $drop ? intval($com->getList($mod, $id, $drop)['rows'][0]['id'] ?? 0) : 0;
    $oob = $off ? $tpl->getHtmlFrag('swap-oob', ['id' => $off]) : '';
    foreach ($off ? $com->getBranch($off, 500)['rows'] : [] as $kid) $oob .= $tpl->getHtmlFrag('swap-oob', ['id' => $kid['id']]);
    if ($at > 0) header('HX-Retarget: [id=\''.$data['rows'][$at - 1]['id'].'\']');
    header('HX-Reswap: '.($at > 0 ? 'afterend' : ($total === 1 ? 'innerHTML' : 'afterbegin')));
    echo getCommentView($row, $row['pid'] ? 0 : $numb, getPageToken()).$oob;
}

# Validate and update an existing forum post in-place
function updatePost() {
    global $db, $user, $conf, $tpl, $prs;
    $conf['forum'] = $conf['forum'] ?? [];
    $id    = getVar('post', 'id',  'num',  0)  ?: getVar('get', 'id',  'num',  0);
    $cid   = getVar('post', 'cid', 'num',  0)  ?: getVar('get', 'cid', 'num',  0);
    $typ   = getVar('post', 'typ', 'num',  0)  ?: getVar('get', 'typ', 'num',  0);
    $mod   = filterVar(getVar('post', 'mod', 'text', '') ?: getVar('get', 'mod', 'text', ''));
    $text  = trim(getVar('post', 'text', 'raw', ''));
    if ($conf['forum']['add'] && $id && $cid) {
        [$pedit, $pmod] = $db->getSqlRow($db->getSqlQuery('SELECT pedit, pmod FROM '.PREFIX_DB.'_categories WHERE id = :cid', ['cid' => $cid]));
        $isedit = is_acess($pedit);
        $ismod = is_acess($pmod);
        [$pid, $uid, $hometext, $fstatus] = $db->getSqlRow($db->getSqlQuery('SELECT pid, uid, body, status FROM '.PREFIX_DB.'_forum WHERE id = :id', ['id' => $id]));
        if ($pid) {
            # The authority is the moderator flag of the category this message belongs to, not a module name taken from the request
            if ($ismod) {
                [$fstatus] = $db->getSqlRow($db->getSqlQuery('SELECT status FROM '.PREFIX_DB.'_forum WHERE id = :pid', ['pid' => $pid]));
            } else {
                [$fstatus] = $db->getSqlRow($db->getSqlQuery('SELECT status FROM '.PREFIX_DB.'_forum WHERE id = :pid AND status != 0', ['pid' => $pid]));
            }
        }
        if ($ismod || ($isedit && $uid == intval($user[0]) && $fstatus > 2)) {
            if (!$text) {
                $content = $typ
                    ? getTplAjaxTextarea([
                        'obj' => 'for'.$id, 'go' => '1', 'op' => 'updatePost', 'id' => $id,
                        'cid' => $cid, 'typ' => '0', 'mod' => $mod, 'text' => $hometext, 'rows' => 10,
                    ])
                    : $prs->filterContent($hometext, false, $mod, 2);
                echo $content;
            } else {
                $postid = (is_user()) ? intval($user[0]) : 0;
                $ip = getip();
                # The longest word decides, measured in characters: the previous loop kept only the last one and counted bytes,
                # which halved the allowance for every alphabet that does not fit in one byte
                $long = 0;
                foreach (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                    $long = max($long, mb_strlen($word));
                }
                $stop = [];
                if ($text == '') $stop[] = _CERROR1;
                if ($long > intval($conf['forum']['letter'])) $stop[] = _CERROR2;
                if (!$stop) {
                    $htext = filterHtml($text);
                    $db->getSqlQuery(
                        'UPDATE '.PREFIX_DB.'_forum SET body = :body, euid = :euid, eip = :eip, etime = NOW() WHERE id = :id',
                        ['body' => $htext, 'euid' => $postid, 'eip' => $ip, 'id' => $id]
                    );
                    echo $prs->filterContent($htext, false, $mod, 2);
                } else {
                    # Echoed rather than returned: the route calls this for its output and discards whatever it hands back
                    echo $tpl->getHtmlFrag('alert', ['messages' => $stop, 'type' => 'warn', 'is_warn' => true]);
                }
            }
        } else {
            return $tpl->getHtmlFrag('alert', ['text' => _ERROR, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
        }
    } else {
        return $tpl->getHtmlFrag('alert', ['text' => _ERROR, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
    }
}

# Render the private-message inbox, outbox, saved or detail view
function getPrivateMessageView(int $obj = 0, string|array $stop = '', string $info = '', int $typ = 0): string {
    global $db, $user, $conf, $tpl, $prs;
    $typ = $typ ?: getVar('get', 'typ', 'num', 0);
    $uid = intval($user[0]);
    $newlistnum = intval($conf['privat']['num']);
    $cid = getVar('get', 'cid', 'num', 1);
    $offset = ($cid-1) * $newlistnum;
    $offset = intval($offset);
    $conf['name'] = 'account';
    $cont = '';
    $messageList = static fn(array $rows, array $headers): string => $tpl->getHtmlPart('content-list', [
        'rows' => $rows,
        'table_open' => ['open' => true, 'headers' => $headers],
        'table_close' => [],
        'empty_alert' => ['text' => _NO_INFO, 'meta' => '', 'type' => 'info', 'is_warn' => false],
    ]);
    if ($typ == 1) {
        [$pr_num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat WHERE uidin = :uid AND status <= 1', ['uid' => $uid]));
        $fstatus = '';
        if ($pr_num >= $conf['privat']['messin']) {
            $messinfo = sprintf(_PRINEXIT, $conf['privat']['messin']);
            $fstatus = 'warn';
        } elseif ($pr_num >= ($conf['privat']['messin'] / 2)) {
            $acmess = ($conf['privat']['messin'] - $pr_num);
            $messinfo = sprintf(_PRINMAX, $conf['privat']['messin'], $pr_num, $acmess);
            $fstatus = 'info';
        }
        if ($fstatus) $cont .= $tpl->getHtmlFrag('alert', ['text' => $messinfo, 'meta' => '', 'type' => $fstatus, 'is_warn' => $fstatus !== 'info']);
        if ($stop) {
            $cont .= $tpl->getHtmlFrag('alert', ['text' => is_array($stop) ? '' : $stop, 'messages' => is_array($stop) ? $stop : [], 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
        } elseif ($info) {
            $cont .= $tpl->getHtmlFrag('alert', ['text' => $info, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
        }
        $result = $db->getSqlQuery('SELECT p.id, p.uidin, p.uidout, p.title, p.time, p.status, u.name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uidout = u.id) WHERE p.uidin = :uid AND p.status <= 1 ORDER BY p.time DESC LIMIT '.intval($offset).', '.intval($newlistnum), ['uid' => $uid]);
        $rows = [];
        while ($row = $db->getSqlRow($result)) {
            [$id, $uidin, $uidout, $title, $date, $status, $user_name] = $row;
            $ititle = $status ? _PROLD : _PRNEW;
            $url = 'index.php?go=1&op=getPrivateMessageView&id='.$id.'&cid=1&typ=4&mod=1&token='.getSiteToken();
            $title_html = $tpl->getHtmlFrag('span', ['title' => $ititle, 'is_message_in' => true, 'is_hidden' => (bool)$status, 'text' => ''])
                .$tpl->getHtmlFrag('link', ['href' => $url, 'is_htmx' => true, 'hx_target' => '#repprmessin', 'title' => $title, 'label' => cutstr($title, 35)]);
            $post_html = ($user_name) ? user_info($user_name) : _ANONYM;
            $items = [
                ['href' => $url, 'title' => _SHOW, 'icon_name' => 'eye', 'is_htmx' => true, 'hx_target' => '#repprmessin'],
                [
                    'href' => 'index.php?go=1&op=setPrivateMessageSaved&id='.$id.'&token='.getSiteToken(),
                    'title' => _SAVE, 'icon_name' => 'archive', 'is_htmx' => true, 'hx_target' => '#repprmessin',
                ],
                [
                    'href' => 'index.php?go=1&op=deletePrivateMessage&id='.$id.'&typ=1&token='.getSiteToken(),
                    'title' => _DELETE, 'icon_name' => 'trash', 'is_htmx' => true, 'hx_target' => '#repprmessin',
                ],
            ];
            $rows[] = ['cells' => [
                ['content_html' => $title_html],
                ['content_html' => $post_html],
                ['text' => format_time($date, _TIMESTRING)],
                ['content_html' => getActionMenu($items)],
            ]];
        }
        $cont .= $messageList($rows, [['text' => _TITLE], ['text' => _PRSE], ['text' => _DATE], ['text' => _FUNCTIONS, 'no_sort' => true]]);
        $numpages = ceil($pr_num / $newlistnum);
        $cont .= getAsyncPager('pagenum', $pr_num, $numpages, $newlistnum, $conf['privat']['nump'], $cid, '0', 1, 'getPrivateMessageView', 'prmessin', 0, '1', '');
    } elseif ($typ == 2) {
        $result = $db->getSqlQuery('SELECT p.id, p.uidin, p.uidout, p.title, p.time, p.status, u.name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uidin = u.id) WHERE p.uidout = :uid AND p.status <= 1 ORDER BY p.time DESC LIMIT '.intval($offset).', '.intval($newlistnum), ['uid' => $uid]);
        $rows = [];
        while ($row = $db->getSqlRow($result)) {
            [$id, $uidin, $uidout, $title, $date, $status, $user_name] = $row;
            $ititle = $status ? _PROLD : _PROUTNEW;
            $url = 'index.php?go=1&op=getPrivateMessageView&id='.$id.'&cid=2&typ=4&mod=2&token='.getSiteToken();
            $title_html = $tpl->getHtmlFrag('span', ['title' => $ititle, 'is_message_out' => true, 'is_hidden' => (bool)$status, 'text' => ''])
                .$tpl->getHtmlFrag('link', ['href' => $url, 'is_htmx' => true, 'hx_target' => '#repprmessou', 'title' => $title, 'label' => cutstr($title, 35)]);
            $post_html = ($user_name) ? user_info($user_name) : _ANONYM;
            $items = [['href' => $url, 'title' => _SHOW, 'icon_name' => 'eye', 'is_htmx' => true, 'hx_target' => '#repprmessou']];
            if (!$status) {
                $items[] = [
                    'href' => 'index.php?go=1&op=deletePrivateMessage&id='.$id.'&typ=2&token='.getSiteToken(),
                    'title' => _DELETE, 'icon_name' => 'trash', 'is_htmx' => true, 'hx_target' => '#repprmessou',
                ];
            }
            $rows[] = ['cells' => [
                ['content_html' => $title_html],
                ['content_html' => $post_html],
                ['text' => format_time($date, _TIMESTRING)],
                ['content_html' => getActionMenu($items)],
            ]];
        }
        $cont .= $messageList($rows, [['text' => _TITLE], ['text' => _PRRE], ['text' => _DATE], ['text' => _FUNCTIONS, 'no_sort' => true]]);
        [$pr_num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat WHERE uidout = :uid AND status <= 1', ['uid' => $uid]));
        $numpages = ceil($pr_num / $newlistnum);
        $cont .= getAsyncPager('pagenum', $pr_num, $numpages, $newlistnum, $conf['privat']['nump'], $cid, '0', 1, 'getPrivateMessageView', 'prmessou', 0, '2', '');
    } elseif ($typ == 3) {
        [$pr_num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat WHERE uidin = :uid AND status = 2', ['uid' => $uid]));
        $fstatus = '';
        if ($pr_num >= $conf['privat']['messsav']) {
            $messinfo = sprintf(_PRSAVEEXIT, $conf['privat']['messsav']);
            $fstatus = 'warn';
        } elseif ($pr_num >= ($conf['privat']['messsav'] / 2)) {
            $acmess = ($conf['privat']['messsav'] - $pr_num);
            $messinfo = sprintf(_PRSAVEMAX, $conf['privat']['messsav'], $pr_num, $acmess);
            $fstatus = 'info';
        }
        if ($fstatus) $cont .= $tpl->getHtmlFrag('alert', ['text' => $messinfo, 'meta' => '', 'type' => $fstatus, 'is_warn' => $fstatus !== 'info']);
        $result = $db->getSqlQuery('SELECT p.id, p.uidin, p.uidout, p.title, p.time, p.status, u.name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uidout=u.id) WHERE p.uidin = :uid AND p.status = 2 ORDER BY p.time DESC LIMIT '.intval($offset).', '.intval($newlistnum), ['uid' => $uid]);
        $rows = [];
        while ($row = $db->getSqlRow($result)) {
            [$id, $uidin, $uidout, $title, $date, $status, $user_name] = $row;
            $url = 'index.php?go=1&op=getPrivateMessageView&id='.$id.'&cid=1&typ=4&mod=3&token='.getSiteToken();
            $title_html = $tpl->getHtmlFrag('span', ['title' => _PRMOVE, 'is_message_save' => true, 'text' => ''])
                .$tpl->getHtmlFrag('link', ['href' => $url, 'is_htmx' => true, 'hx_target' => '#repprmesssa', 'title' => $title, 'label' => cutstr($title, 35)]);
            $post_html = ($user_name) ? user_info($user_name) : _ANONYM;
            $items = [
                ['href' => $url, 'title' => _SHOW, 'icon_name' => 'eye', 'is_htmx' => true, 'hx_target' => '#repprmesssa'],
                [
                    'href' => 'index.php?go=1&op=deletePrivateMessage&id='.$id.'&typ=3&token='.getSiteToken(),
                    'title' => _DELETE, 'icon_name' => 'trash', 'is_htmx' => true, 'hx_target' => '#repprmesssa',
                ],
            ];
            $rows[] = ['cells' => [
                ['content_html' => $title_html],
                ['content_html' => $post_html],
                ['text' => format_time($date, _TIMESTRING)],
                ['content_html' => getActionMenu($items)],
            ]];
        }
        $cont .= $messageList($rows, [['text' => _TITLE], ['text' => _PRSE], ['text' => _DATE], ['text' => _FUNCTIONS, 'no_sort' => true]]);
        $numpages = ceil($pr_num / $newlistnum);
        $cont .= getAsyncPager('pagenum', $pr_num, $numpages, $newlistnum, $conf['privat']['nump'], $cid, '0', 1, 'getPrivateMessageView', 'prmesssa', 0, '3', '');
    } elseif ($typ == 4) {
        if ($stop) {
            $cont .= $tpl->getHtmlFrag('alert', ['text' => is_array($stop) ? '' : $stop, 'messages' => is_array($stop) ? $stop : [], 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
        } elseif ($info) {
            $cont .= $tpl->getHtmlFrag('alert', ['text' => $info, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
        }
        $id  = getVar('get', 'id',  'num', 0);
        $cid = getVar('get', 'cid', 'num', 0);
        $mod = getVar('get', 'mod', 'num', 0);
        if ($mod == 1) {
            $prmid = 'prmessin';
        } elseif ($mod == 2) {
            $prmid = 'prmessou';
        } elseif ($mod == 3) {
            $prmid = 'prmesssa';
        } else {
            $prmid = 'prmessfo';
        }
        if ($id) {
            if ($cid == '2') {
                [$idp, $uidin, $uidout, $title, $body, $date, $ip_sender, $status, $user_name] = $db->getSqlRow($db->getSqlQuery('SELECT p.id, p.uidin, p.uidout, p.title, p.body, p.time, p.ip, p.status, u.name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uidin = u.id) WHERE p.id = :id AND p.uidout = :uid LIMIT 1', ['id' => $id, 'uid' => $uid]));
            } else {
                [$idp, $uidin, $uidout, $title, $body, $date, $ip_sender, $status, $user_name] = $db->getSqlRow($db->getSqlQuery('SELECT p.id, p.uidin, p.uidout, p.title, p.body, p.time, p.ip, p.status, u.name FROM '.PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uidout = u.id) WHERE p.id = :id AND p.uidin = :uid LIMIT 1', ['id' => $id, 'uid' => $uid]));
                if (!$status) {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_privat SET status = 1 WHERE id = :id AND uidin = :uid AND status != 2', ['id' => $id, 'uid' => $uid]);
                    deletePrivatCounts();
                }
            }
            if ($idp) {
                # UNBEKANTE VARIABLEN INITIALISIERUNG VERHINDERN
                $com_name = $com_id = '';

                $result = $db->getSqlQuery('SELECT u.id, u.name, u.rank, u.email, u.website, u.avatar, u.regdate, u.origin, u.sig, u.viewmail, u.points, u.warnings, u.gender, u.votes, u.tvotes, g.name, g.rank, g.color FROM '.PREFIX_DB.'_users AS u LEFT JOIN '.PREFIX_DB.'_groups AS g ON ((g.extra=1 AND u.grp=g.id) OR (g.extra!=1 AND u.points>=g.points)) WHERE u.id = :uidout ORDER BY g.extra DESC, g.points DESC', ['uidout' => $uidout]);
                [$user_id, $user_name, $user_rank, $user_email, $user_website, $user_avatar, $user_regdate, $user_from, $user_sig, $user_viewemail, $user_points, $user_warnings, $user_gender, $user_votes, $user_totalvotes, $user_gname, $user_grank, $user_gcolor] = $db->getSqlRow($result);
                $avname = ($user_name) ? $user_name : ($com_name ?: (string)_ANONYM);
                $date = $tpl->getHtmlFrag('inline-badge', ['title_text' => _PADD, 'label' => format_time($date, _TIMESTRING), 'is_comment_date' => true]);
                $ip = (is_moder($conf['name'])) ? Geoip::getIpHtml($ip_sender, true) : '';
                $avatar = ($user_name) ? getUserAvatarUrl(['avatar' => $user_avatar]) : getUserAvatarUrl([], true);
                $rank = ($user_rank) ? $user_rank : '';
                $trank = ($user_gname) ? _GROUP.': '.$user_gname : _RANK;
                $rlink = ($user_grank && file_exists(getThemeImagePath('ranks/'.$user_grank))) ? $tpl->getHtmlFrag('image', ['src' => getThemeImagePath('ranks/'.$user_grank), 'alt' => $trank, 'title' => $trank]) : '';
                $rate = getRatingAsync(0, $user_id, $conf['name'], $user_votes, $user_totalvotes, $com_id, 1);
                $utip = getUserTip((string)($user_gname ?? ''), $user_points ?? 0, (string)($user_regdate ?? ''), (int)($user_gender ?? 0), (string)($user_from ?? ''), (string)($user_warnings ?? ''), empty($user_name), (int)$uidout > 0);
                $uname_html = (!empty($user_name)) ? user_info($user_name, false) : htmlspecialchars($avname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $sig = ($user_sig) ? $tpl->getHtmlFrag('block-content', ['is_signature' => true, 'content' => $user_sig]) : '';
                $uitems = [];
                if ($conf['privat']['profil'] && $user_name) {
                    $uitems[] = ['href' => 'index.php?name=account&op=view&uname='.urlencode($user_name), 'title' => _PERSONALINFO, 'icon_name' => 'person'];
                }
                if ($conf['privat']['web'] && $user_website) {
                    $uitems[] = ['href' => $user_website, 'title' => _DOWNLLINK, 'icon_name' => 'globe', 'is_blank' => true];
                }
                $usermenu = getActionMenu($uitems, true);
                $edit = '';
                if (($uidin == $uid) || ($uidout == $uid && !$status)) {
                    $edit = getActionMenu([[
                        'href' => 'index.php?go=1&op=deletePrivateMessage&id='.$idp.'&typ='.$mod.'&token='.getSiteToken(),
                        'title' => _ONDELETE,
                        'icon_name' => 'trash',
                        'is_htmx' => true,
                        'hx_target' => '#rep'.$prmid,
                    ]]);
                }
                $rankHtml = ($rank) ? $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => $rank]) : '';
                $cont .= $tpl->getHtmlFrag('forum-post', [
                    'id' => 'pm'.$idp,
                    'username' => $avname,
                    'username_html' => $uname_html,
                    'report' => $utip,
                    'date' => $date,
                    'ip' => $ip,
                    'meta_title' => cutstr($title, 35),
                    'avatar' => $avatar,
                    'avatar_html' => $tpl->getHtmlFrag('image', [
                        'src' => $avatar, 'alt' => $avname, 'title' => $avname, 'is_avatar' => true,
                    ]),
                    'rank_html' => $rankHtml,
                    'rank_link' => $rlink,
                    'user_rate' => $rate,
                    'text' => $prs->filterContent($body, false, $conf['name'], 2),
                    'sig' => $prs->filterContent($sig, false, $conf['name'], 2),
                    'btn_user' => $usermenu,
                    'btn_edit' => $edit,
                    'is_private_message' => true,
                ]);
            }
        }
        if (!$info && (!$cid || $cid == '1')) {
            $name = getVar('post', 'name', 'raw', '') ?: urldecode(getVar('get', 'uname', 'raw', ''));
            $sname = filterText(substr($name, 0, 25));
            $stitle = filterText(trim(getVar('post', 'title', 'raw', '')));
            $stext = filterText(trim(getVar('post', 'text', 'raw', '')));
            $rpost = ($sname) ? $sname : (($user_name ?? '') ? $user_name : '');
            $rtitle = ($stitle) ? $stitle : (($title ?? '') ? _PRREP.': '.$title : '');
            $rcontent = ($stext) ? $stext : (($body ?? '') ? '[quote]'.$body.'[/quote]' : '');

            $idp = ($id) ? '2' : '1';
            $formId = 'form'.$prmid;
            $recipient = getTplUserSearchInput([
                    'name' => 'name',
                    'input_id' => 'privat_message_name',
                    'list_id' => 'privat_message_name_list',
                    'maxlength' => 25,
                    'value' => $rpost,
            ]);
            $fields = $tpl->getHtmlFrag('form-field-row', ['label' => _PRRE, 'field_html' => $recipient])
                .$tpl->getHtmlFrag('form-field-row', ['label' => _TITLE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'title', 'value_attr' => $rtitle, 'maxlength_num' => 100])])
                .$tpl->getHtmlFrag('form-field-row', [
                    'label' => _MESSAGE,
                    'hide_label' => true,
                    'field_html' => getTplTextarea([
                        'id' => $idp,
                        'name' => 'text',
                        'value' => $rcontent,
                        'mod' => $conf['name'],
                        'rows' => '15',
                        'placeholder' => _MESSAGE,
                    ]),
                ]);
            $submit = $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit',
                'label' => _SEND,
                'title' => _SEND,
                'hx_post' => 'index.php?go=1&op=addPrivateMessage',
                'hx_include' => '#'.$formId,
                'hx_target' => '#rep'.$prmid,
                'hx_on_click' => 'if (!document.getElementById(\''.$formId.'\').querySelector(\'[name=&quot;name&quot;]\').value.trim()) { alert(\''._CERROR6.'\'); event.preventDefault(); }',
            ]);
            $cont .= $tpl->getHtmlPart('form-add', [
                'no_action' => true,
                'form_id' => $formId,
                'form_name' => 'post',
                'no_enctype' => true,
                'fields' => $fields,
                'submit' => $submit,
            ]);
        }
    }
    if ($obj) { return $cont; }
    echo $cont;
    return '';
}

# Validate and send a new private message; returns the updated inbox view
function addPrivateMessage() {
    global $db, $user, $conf, $tpl, $mailer;
    $postname = filterText(substr(getVar('post', 'name',  'raw', ''), 0, 25));
    $title    = trim(getVar('post', 'title', 'raw', ''));
    $text     = trim(getVar('post', 'text',  'raw', ''));
    $ip = getip();

    $uidin = (is_user_id($postname)) ? is_user_id($postname) : '';
    $uidout = (is_user()) ? intval($user[0]) : '';

    [$date] = $db->getSqlRow($db->getSqlQuery('SELECT time FROM '.PREFIX_DB.'_privat WHERE uidout = :uidout ORDER BY id DESC LIMIT 1', ['uidout' => $uidout]));
    $stime = strtotime($date) + $conf['privat']['send'];
    $checks = str_replace(["\n", "\r", "\t"], ' ', $text);
    $e = explode(' ', $checks);
    for ($a = 0; $a < count($e); $a++) $o = strlen($e[$a]);

    $stop = [];
    if (!$postname) {
        $stop[] = _CERROR6;
    } elseif (!$uidin) {
        $stop[] = _CERROR7;
    }
    if ($conf['privat']['himself'] && $uidin == $uidout) $stop[] = _CERROR8;
    if (!$title) $stop[] = _CERROR;
    if (!$text) $stop[] = _CERROR1;
    if ($o > $conf['privat']['letter']) $stop[] = _CERROR2;
    if (!$uidout) $stop[] = _CERROR3;
    if ($stime > time()) $stop[] = sprintf(_CERROR5, $conf['privat']['send']);

    [$pr_num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat WHERE uidin = :uidin AND status <= 1', ['uidin' => $uidin]));
    if ($pr_num >= $conf['privat']['messin']) $stop[] = sprintf(_PRSENDOVER, $postname);

    if (!$stop && $conf['privat']['act'] && is_user()) {
        $title = filterHtml($title, 1);
        $text = filterHtml($text);
        $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_privat VALUES (NULL, :uidin, :uidout, :title, :body, NOW(), :ip, 0)', ['uidin' => $uidin, 'uidout' => $uidout, 'title' => $title, 'body' => $text, 'ip' => $ip]);
        deletePrivatCounts();
        updatePoints(45);
        if ($conf['privat']['newmail']) {
            [$user_email, $user_psmail] = $db->getSqlRow($db->getSqlQuery('SELECT email, fsmail FROM '.PREFIX_DB.'_users WHERE id = :uidin', ['uidin' => $uidin]));
            if ($user_email && $user_psmail) {
                [$id] = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_privat WHERE uidin = :uidin AND uidout = :uidout ORDER BY id DESC LIMIT 1', ['uidin' => $uidin, 'uidout' => $uidout]));
                $uname = filterText(substr($user[1], 0, 25));
                $finishlink = $conf['homeurl'].'/index.php?name=account&op=privat&id='.$id.'#prmess';
                $link = $tpl->getHtmlFrag('link', ['href' => $finishlink, 'title' => '', 'label_html' => $finishlink]);
                $subject = $conf['sitename'].' - '._PRIVAT;
                $message = str_replace('[text]', sprintf(_PRNEWMAIL, $uname, $link), $conf['mtemp']);
                $mailer->addQueue(['kind' => 'privat', 'email' => $user_email, 'title' => $subject, 'body' => $message, 'sender' => $conf['adminmail'], 'prio' => 3]);
            }
        }
        $info = sprintf(_PRSENDED, $postname);
        return getPrivateMessageView(0, '', $info, 4);
    } else {
        $stop = ($stop) ? (array)$stop : _ERROR;
        return getPrivateMessageView(0, $stop, '', 4);
    }
}

# Move a received private message to the user's saved folder
function setPrivateMessageSaved() {
    global $db, $conf, $user;
    $uid = (is_user()) ? intval($user[0]) : 0;
    $id = getVar('get', 'id', 'num', 0);
    [$pr_num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_privat WHERE uidin = :uid AND status = 2', ['uid' => $uid]));
    $pr_numi = $pr_num + 1;
    $stop = '';
    $info = '';
    if ($pr_num >= $conf['privat']['messsav']) {
        $stop = sprintf(_PRSAVEEXIT, $conf['privat']['messsav']);
    } elseif ($pr_numi >= ($conf['privat']['messsav'] / 2)) {
        $acmess = ($conf['privat']['messsav'] - $pr_numi);
        $info = sprintf(_PRSAVEMAX, $conf['privat']['messsav'], $pr_numi, $acmess);
    }
    if (!$stop && $conf['privat']['act'] && $uid && $id) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_privat SET status = 2 WHERE id = :id AND uidin = :uid', ['id' => $id, 'uid' => $uid]);
        deletePrivatCounts();
    }
    return getPrivateMessageView(0, $stop, $info, 1);
}

# Delete a private message from inbox or outbox and return the updated view
function deletePrivateMessage() {
    global $db, $conf, $user;
    $uid = (is_user()) ? intval($user[0]) : 0;
    $id  = getVar('get', 'id',  'num', 0);
    $typ = getVar('get', 'typ', 'num', 1);
    if ($conf['privat']['act'] && $uid && $id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_privat WHERE (id = :id_in AND uidin = :uid_in) OR (id = :id_out AND uidout = :uid_out AND status = 0)', ['id_in' => $id, 'uid_in' => $uid, 'id_out' => $id, 'uid_out' => $uid]);
        deletePrivatCounts();
    }
    return getPrivateMessageView(0, '', '', $typ);
}

# Per-module map for profile contribution views: label constant, icon name, table, where clause and rating column pair (count, total); fav marks the favorites modul key
# The comment entry carries no table, where or rate: its rows are read through the comment subsystem, and a table name left here would be a second way into a table with one owner
function getProfileModules(): array {
    return [
        'comm' => ['title' => _COMMENTS, 'icon' => 'chat-text', 'fav' => ''],
        'faq' => ['title' => _FAQ, 'icon' => 'question-circle', 'table' => 'faq', 'where' => "uid = :uid AND time <= NOW() AND status != '0'", 'rate' => ['ratings', 'score'], 'fav' => 'faq'],
        'files' => ['title' => _FILES, 'icon' => 'file-earmark-arrow-down', 'table' => 'files', 'where' => "uid = :uid AND time <= NOW() AND status != '0'", 'rate' => ['votes', 'tvotes'], 'fav' => 'files'],
        'forum' => ['title' => _FORUM, 'icon' => 'window-stack', 'table' => 'forum', 'where' => "uid = :uid AND pid = '0' AND time <= NOW() AND status > '1'", 'rate' => ['ratings', 'score'], 'fav' => 'forum'],
        'jokes' => ['title' => _JOKES, 'icon' => 'emoji-smile', 'table' => 'jokes', 'where' => "uid = :uid AND time <= NOW() AND status != '0'", 'rate' => ['ratetot', 'rating'], 'fav' => ''],
        'links' => ['title' => _LINKS, 'icon' => 'link-45deg', 'table' => 'links', 'where' => "uid = :uid AND time <= NOW() AND status != '0'", 'rate' => ['votes', 'tvotes'], 'fav' => 'links'],
        'media' => ['title' => _MEDIA, 'icon' => 'camera-reels', 'table' => 'media', 'where' => "uid = :uid AND time <= NOW() AND status != '0'", 'rate' => ['votes', 'tvotes'], 'fav' => 'media'],
        'news' => ['title' => _NEWS, 'icon' => 'newspaper', 'table' => 'news', 'where' => "uid = :uid AND time <= NOW() AND status != '0'", 'rate' => ['ratings', 'score'], 'fav' => 'news'],
        'pages' => ['title' => _PAGES, 'icon' => 'file-text', 'table' => 'pages', 'where' => "uid = :uid AND time <= NOW() AND status != '0'", 'rate' => ['ratings', 'score'], 'fav' => 'pages'],
    ];
}

# Build the "last activity" feed with per-module tabs for a public profile as one UNION ALL round-trip; shared by the profile view page and the own-profile page
function getProfileLastView(int $uid): string {
    global $db, $conf, $tpl, $prs, $com;
    if ($uid < 1 || ($conf['users']['prof'] == 1 && !is_user() && !isAdmin())) return '';
    $limit = intval(getUserNews(25));
    $parts = [];
    $params = [];
    $lists = ['comm' => []];
    foreach (getProfileModules() as $mod => $inf) {
        if ($mod == 'comm' || !is_active($mod)) continue;
        $ron = !empty(explode('|', (string)($conf['ratings'][$mod] ?? ''))[1]);
        $rsel = ($ron && $inf['rate']) ? $inf['rate'][0].' AS rc, '.$inf['rate'][1].' AS rt' : '0 AS rc, 0 AS rt';
        $from = PREFIX_DB.'_'.$inf['table'].' WHERE '.str_replace(':uid', ':u'.$mod, $inf['where']);
        $parts[] = "(SELECT '".$mod."' AS mkey, id, 0 AS ref, '' AS sub, title, time, ".$rsel.' FROM '.$from.' ORDER BY id DESC LIMIT 0,'.$limit.')';
        $params['u'.$mod] = $uid;
        $lists[$mod] = [];
    }
    foreach ($com->getUserList($uid, $limit) as $row) {
        $when = $row['time'];
        $text = cutstr(str_replace([_QUOTE, _CODE], '', filterText($prs->filterContent($row['body'], true, $conf['name'], 0, $row['format']))), 70);
        $lists['comm'][] = [
            'datehtml' => $tpl->getHtmlFrag('date-badge', ['iso' => date('c', strtotime($when)), 'title' => format_time($when, _TIMESTRING), 'text' => format_time($when)]),
            'href' => getSeoUrl(['name' => $row['modul'], 'op' => 'view', 'id' => $row['cid']]).'#'.$row['id'],
            'label' => $text,
            'rating' => '',
        ];
    }
    if ($parts) {
        $result = $db->getSqlQuery(implode(' UNION ALL ', $parts), $params);
        while ([$key, $id, $cid, $cmod, $label, $time, $cnt, $tot] = $db->getSqlRow($result)) {
            if ($key == 'jokes') {
                $href = getSeoUrl(['name' => 'jokes']).'#'.$id;
            } elseif ($key == 'forum') {
                $href = getSeoUrl(['name' => $key, 'op' => 'view', 'id' => $id, 'title' => $label]);
            } else {
                $href = getSeoUrl(['name' => $key, 'op' => 'view', 'id' => $id, 'title' => $label]).'#'.$id;
            }
            $lists[$key][] = [
                'datehtml' => $tpl->getHtmlFrag('date-badge', ['iso' => date('c', strtotime($time)), 'title' => format_time($time, _TIMESTRING), 'text' => format_time($time)]),
                'href' => $href,
                'label' => $label,
                'rating' => ($cnt > 0) ? number_format($tot / $cnt, 2) : '',
            ];
        }
    }
    $tabs = [];
    $texts = [];
    foreach (getProfileModules() as $mod => $inf) {
        if (!isset($lists[$mod])) continue;
        $tabs[] = $inf['title'];
        $texts[] = $tpl->getHtmlPart('account-profile-feed-list', ['entries' => $lists[$mod], 'icon_name' => $inf['icon'], 'empty_text' => _NO_INFO]);
    }
    return $tpl->getHtmlPart('account-profile-feed', [
        'title' => _LASTACTIVITY,
        'tabs_html' => getNaviTabs(0, 'profeed', $tabs, $texts),
    ]);
}

# Render the favorites star button for an item as a round mini toggle (add/on/limit-reached state) with a tooltip panel
# The whole favorites set of the user is loaded once per request into a static cache, so list pages render many stars without per-item SQL
function getFavoriteButton(?int $fid, string $mod): string {
    global $db, $conf, $user, $tpl;
    static $cache = null;
    $fid = (int)$fid;
    $uid = (is_user()) ? intval($user[0]) : 0;
    if (!$conf['favorites']['favact'] || !$uid || $fid < 1) return '';
    if ($cache === null || $cache['uid'] !== $uid) {
        $cache = ['uid' => $uid, 'num' => 0, 'items' => []];
        $result = $db->getSqlQuery('SELECT fid, modul FROM '.PREFIX_DB.'_favorites WHERE uid = :uid', ['uid' => $uid]);
        while ([$itemid, $itemmod] = $db->getSqlRow($result)) {
            $cache['items'][$itemmod.'-'.$itemid] = true;
            $cache['num']++;
        }
    }
    $repid = 'rep'.$fid.$mod;
    if (!empty($cache['items'][$mod.'-'.$fid])) return $tpl->getHtmlFrag('favorite', ['rep_id' => $repid, 'is_on' => true]);
    if ($cache['num'] >= $conf['favorites']['favorites']) return $tpl->getHtmlFrag('favorite', ['rep_id' => $repid, 'is_limit' => true, 'title' => sprintf(_FAVOR_EXIT, $conf['favorites']['favorites'])]);
    return $tpl->getHtmlFrag('favorite', ['rep_id' => $repid, 'href' => 'index.php?go=1&op=addFavorite&id='.$fid.'&mod='.$mod.'&token='.getPageToken()]);
}

# Add an item to the user's favorites list and echo the updated toggle button
function addFavorite() {
    global $db, $conf, $user;
    $id = getVar('get', 'id',  'num',  0);
    $mod = filterVar(getVar('get', 'mod', 'text', ''));
    $uid = (is_user()) ? intval($user[0]) : 0;
    if ($conf['favorites']['favact'] && $uid && $id && $mod) {
        [$fav] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_favorites WHERE uid = :uid AND fid = :fid AND modul = :modul', ['uid' => $uid, 'fid' => $id, 'modul' => $mod]));
        if ($fav) {
            echo getFavoriteButton($id, $mod);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_favorites VALUES (NULL, :uid, :fid, :modul)', ['uid' => $uid, 'fid' => $id, 'modul' => $mod]);
            updatePoints(44);
        }
    }
    echo getFavoriteButton($id, $mod);
}

# Render the paginated favorites list for the logged-in user
function getFavoriteList(int $obj = 0): string {
    global $db, $conf, $user, $tpl;
    $uid = intval($user[0]);
    $newlistnum = intval($conf['favorites']['num']);
    $cid = getVar('get', 'cid', 'num', 1);
    $offset = ($cid - 1) * $newlistnum;
    $offset = intval($offset);
    $a = ($cid) ? $offset + 1 : 1;

    [$fav_num] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_favorites WHERE uid = :uid', ['uid' => $uid]));
    if ($fav_num >= $conf['favorites']['favorites']) {
        $favinfo = sprintf(_FAVOR_EXIT, $conf['favorites']['favorites']);
        $fstatus = 'warn';
    } else {
        $acfavor = ($conf['favorites']['favorites'] - $fav_num);
        $favinfo = sprintf(_FAVOR_MAX, $conf['favorites']['favorites'], $fav_num, $acfavor);
        $fstatus = 'info';
    }

    $fmassiv = [];
    $ffmassiv = [];
    $result = $db->getSqlQuery('SELECT fid, modul FROM '.PREFIX_DB.'_favorites WHERE uid = :uid ORDER BY id DESC LIMIT '.intval($offset).', '.intval($newlistnum), ['uid' => $uid]);
    while ([$fid, $modul] = $db->getSqlRow($result)) $fmassiv[$modul][] = $fid;

    if ($fmassiv) {
        foreach ($fmassiv as $key => $val) {
            $ids = array_values(array_filter(array_map('intval', $val), static fn($v) => $v > 0));
            if (!$ids) continue;
            $pp = [];
            $pm = ['uid' => $uid];
            foreach ($ids as $k => $v) {
                $ph = 'f'.$k;
                $pp[] = ':'.$ph;
                $pm[$ph] = $v;
            }
            $in = implode(', ', $pp);
            $numl = count($val);
            if ($key == 'faq') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_faq AS n ON (f.fid=n.id) WHERE f.uid = :uid AND n.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title] = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title];
            } elseif ($key == 'files') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_files AS n ON (f.fid=n.id) WHERE f.uid = :uid AND n.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title] = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title];
            } elseif ($key == 'forum') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_forum AS n ON (f.fid=n.id) WHERE f.uid = :uid AND n.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title] = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title];
            } elseif ($key == 'help') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_help AS n ON (f.fid=n.id) WHERE f.uid = :uid AND n.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title] = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title];
            } elseif ($key == 'links') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_links AS n ON (f.fid=n.id) WHERE f.uid = :uid AND n.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title] = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title];
            } elseif ($key == 'media') {
                $conf['media'] = $conf['media'] ?? [];
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title, n.subtitle FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_media AS n ON (f.fid=n.id) WHERE f.uid = :uid AND n.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title, $subtitle] = $db->getSqlRow($result)) {
                    $title = ($subtitle) ? $title.' '.urldecode($conf['media']['mdefis']).' '.$subtitle : $title;
                    $ffmassiv[] = [$id, $fid, $modul, $title];
                }
            } elseif ($key == 'news') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_news AS n ON (f.fid=n.id) WHERE f.uid = :uid AND n.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title] = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title];
            } elseif ($key == 'pages') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_pages AS n ON (f.fid=n.id) WHERE f.uid = :uid AND n.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title] = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title];
            } elseif ($key == 'shop') {
                $result = $db->getSqlQuery('SELECT f.id, f.fid, f.modul, n.title FROM '.PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_products AS n ON (f.fid=n.id) WHERE f.uid = :uid AND n.id IN ('.$in.') ORDER BY f.id DESC LIMIT 0, '.intval($numl), $pm);
                while ([$id, $fid, $modul, $title] = $db->getSqlRow($result)) $ffmassiv[] = [$id, $fid, $modul, $title];
            }
        }
    }
    $cont = $tpl->getHtmlFrag('alert', ['text' => $favinfo, 'meta' => '', 'type' => $fstatus, 'is_warn' => $fstatus !== 'info']);
    if ($ffmassiv) {
        $rows = [];
        foreach ($ffmassiv as $key => $val) {
            $id = $val[0];
            $fid = $val[1];
            $modul = $val[2];
            $title = $val[3];
            $surl = 'index.php?name='.$modul.'&op=view&id='.$fid;
            $items = [
                ['href' => $surl, 'title' => _SHOW, 'icon_name' => 'eye'],
                [
                    'href' => 'index.php?go=1&op=deleteFavorite&id='.$id.'&token='.getSiteToken(),
                    'title' => _DELETE, 'icon_name' => 'trash', 'is_htmx' => true, 'hx_target' => '#repfavorliste',
                ],
            ];
            $rows[] = [
                'id' => (string)$a,
                'cells' => [
                    ['href' => $surl, 'title' => $title, 'text' => cutstr($title, 100)],
                    ['is_num' => true, 'content_html' => getActionMenu($items).' '.$tpl->getHtmlFrag('link', ['href' => '#'.$a, 'title' => (string)$a, 'label' => (string)$a, 'is_num_anchor' => true])],
                ],
            ];
            $a++;
        }
        $cont .= $tpl->getHtmlPart('content-list', [
            'rows' => $rows,
            'table_open' => ['open' => true, 'col_id' => _ID, 'col_title' => _TITLE],
            'table_close' => [],
        ]);
        $numpages = ceil($fav_num / $newlistnum);
        $cont .= getAsyncPager('pagenum', $fav_num, $numpages, $newlistnum, $conf['favorites']['nump'], $cid, '0', 1, 'getFavoriteList', 'favorliste', 0, '', '');
    } else {
        $cont = $tpl->getHtmlFrag('alert', ['text' => _NO_INFO, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
    }
    if ($obj) { return $cont; }
    echo $cont;
    return '';
}

# Delete a favorite entry and return the refreshed favorites list
function deleteFavorite(): string {
    global $db, $conf, $user;
    $uid = (is_user()) ? intval($user[0]) : 0;
    $id = getVar('get', 'id', 'num', 0);
    if ($conf['favorites']['favact'] && $uid && $id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE id = :id AND uid = :uid', ['id' => $id, 'uid' => $uid]);
    return getFavoriteList(0);
}

# Output the RSS 2.0 feed for the specified module and optional category
function getRssChannel() {
    global $db, $conf, $prs;
    header_remove('X-Content-Type-Options');
    header('Content-Type: application/rss+xml; charset='._CHARSET);
    header('Content-Encoding: none');

    $name = filterVar(getVar('post', 'name', 'text', '') ?: getVar('get', 'name', 'text', ''));
    $hmodul = explode(',', $conf['module']);
    $hi = mt_rand(0, count($hmodul) - 1);
    $cname = $hmodul[$hi];
    $name = ($name) ? $name : $cname;
    $cat  = getVar('post', 'cat', 'num', 0) ?: getVar('get', 'cat', 'num', 0);
    $num  = getVar('post', 'num', 'num', 0) ?: getVar('get', 'num', 'num', 0);
    $num = ($num) ? (($num <= $conf['rss']['max']) ? $num : $conf['rss']['max']) : $conf['rss']['min'];
    $id   = getVar('post', 'id',  'num', 0) ?: getVar('get', 'id',  'num', 0);

    if (($name == 'content') && $id) {
        $result = $db->getSqlQuery('SELECT id, title, body, time FROM '.PREFIX_DB.'_content WHERE id = :id AND time <= NOW()', ['id' => $id]);
    } elseif ($name == 'faq') {
        $params = [];
        $where = $cat ? 'WHERE s.cid = :cat AND s.time <= NOW() AND s.status != 0' : 'WHERE s.time <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->getSqlQuery('SELECT s.id, s.name, s.title, s.time, s.body, c.title, u.name FROM '.PREFIX_DB.'_faq AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.id) '.$where.' ORDER BY s.time DESC LIMIT '.intval($num), $params);
    } elseif ($name == 'files') {
        $params = [];
        $where = $cat ? 'WHERE s.cid = :cat AND s.time <= NOW() AND s.status != 0' : 'WHERE s.time <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->getSqlQuery('SELECT s.id, s.name, s.title, s.time, s.intro, c.title, u.name FROM '.PREFIX_DB.'_files AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.id) '.$where.' ORDER BY s.time DESC LIMIT '.intval($num), $params);
    } elseif ($name == 'links') {
        $params = [];
        $where = $cat ? 'WHERE s.cid = :cat AND s.time <= NOW() AND s.status != 0' : 'WHERE s.time <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->getSqlQuery('SELECT s.id, s.name, s.title, s.time, s.intro, c.title, u.name FROM '.PREFIX_DB.'_links AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.id) '.$where.' ORDER BY s.time DESC LIMIT '.intval($num), $params);
    } elseif ($name == 'media') {
        $params = [];
        $where = $cat ? 'WHERE s.cid = :cat AND s.time <= NOW() AND s.status != 0' : 'WHERE s.time <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->getSqlQuery('SELECT s.id, s.name, s.title, s.time, s.intro, c.title, u.name FROM '.PREFIX_DB.'_media AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.id) '.$where.' ORDER BY s.time DESC LIMIT '.intval($num), $params);
    } elseif ($name == 'pages') {
        $params = [];
        $where = $cat ? 'WHERE s.cid = :cat AND s.time <= NOW() AND s.status != 0' : 'WHERE s.time <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->getSqlQuery('SELECT s.id, s.name, s.title, s.time, s.intro, c.title, u.name FROM '.PREFIX_DB.'_pages AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.id) '.$where.' ORDER BY s.time DESC LIMIT '.intval($num), $params);
    } elseif ($name == 'shop') {
        $params = [];
        $where = $cat ? 'WHERE s.cid = :cat AND s.time <= NOW() AND s.status = 1' : 'WHERE s.time <= NOW() AND s.status = 1';
        if ($cat) $params['cat'] = $cat;
        $result = $db->getSqlQuery('SELECT s.id, s.title, s.time, s.intro, c.title FROM '.PREFIX_DB.'_products AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) '.$where.' ORDER BY s.time DESC LIMIT '.intval($num), $params);
    } elseif ($name == 'news') {
        $params = [];
        $where = $cat ? 'WHERE s.cid = :cat AND s.time <= NOW() AND s.status != 0' : 'WHERE s.time <= NOW() AND s.status != 0';
        if ($cat) $params['cat'] = $cat;
        $result = $db->getSqlQuery('SELECT s.id, s.name, s.title, s.time, s.intro, c.title, u.name FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid=c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid=u.id) '.$where.' ORDER BY s.time DESC LIMIT '.intval($num), $params);
        $name = 'news';
    } else {
        $result = '';
        $name = '';
    }

    $content = '<?xml version="1.0" encoding="'._CHARSET."\"?>\n"
    ."<rss version=\"2.0\">\n"
    ."<channel>\n"
    .'<title>'.htmlspecialchars($conf['sitename'])."</title>\n"
    .'<link>'.$conf['homeurl']."</link>\n"
    .'<description>'.htmlspecialchars($conf['slogan'])."</description>\n"
    .'<generator>SLAED CMS '.$conf['version']."</generator>\n"
    .'<copyright>Copyright (c) SLAED CMS '.$conf['version']."</copyright>\n"
    .'<language>'.htmlspecialchars(substr(_LOCALE, 0, 2))."</language>\n"
    .'<lastBuildDate>'.date('D, j M Y H:m:s O')."</lastBuildDate>\n\n";
    if ($name && $name != 'content' && $name != 'shop' && $result) {
        while ([$rid, $uname, $rtitle, $rtime, $rhometext, $rctitle, $user_name] = $db->getSqlRow($result)) {
            $rauthor = ($user_name) ? $user_name : (($uname) ? $uname : _ANONYM);
            $content .= "<item>\n"
            .'<title>'.htmlspecialchars($rtitle)."</title>\n"
            .'<pubDate>'.htmlspecialchars(date('D, j M Y H:m:s O', strtotime($rtime)))."</pubDate>\n"
            .'<guid>'.$conf['homeurl'].'/index.php?name='.$name.'&op=view&id='.$rid."</guid>\n"
            .'<link>'.$conf['homeurl'].'/index.php?name='.$name.'&op=view&id='.$rid."</link>\n"
            .'<description>'.htmlspecialchars($prs->filterContent($rhometext, false, $name))."</description>\n"
            .'<comments>'.$conf['homeurl'].'/index.php?name='.$name.'&op=view&id='.$rid.'#'.$rid."</comments>\n";
            $content .= ($rctitle) ? '<category>'.htmlspecialchars($rctitle)."</category>\n" : '';
            $content .= '<author>antispam@antispam.com ('.htmlspecialchars($rauthor).")</author>\n"
            ."</item>\n\n";
        }
    } elseif ($name && $name == 'content' && $result) {
        [$rid, $rtitle, $rhometext, $rtime] = $db->getSqlRow($result);
        $content .= "<item>\n"
        .'<title>'.htmlspecialchars($rtitle)."</title>\n"
        .'<pubDate>'.htmlspecialchars(date('D, j M Y H:m:s O', strtotime($rtime)))."</pubDate>\n"
        .'<guid>'.$conf['homeurl'].'/index.php?name='.$name.'&op=view&id='.$rid."</guid>\n"
        .'<link>'.$conf['homeurl'].'/index.php?name='.$name.'&op=view&id='.$rid."</link>\n"
        .'<description>'.htmlspecialchars($prs->filterContent($rhometext, false, $name))."</description>\n"
        ."</item>\n\n";
    } elseif ($name && $name == 'shop' && $result) {
        while ([$rid, $rtitle, $rtime, $rhometext, $rctitle] = $db->getSqlRow($result)) {
            $content .= "<item>\n"
            .'<title>'.htmlspecialchars($rtitle)."</title>\n"
            .'<pubDate>'.htmlspecialchars(date('D, j M Y H:m:s O', strtotime($rtime)))."</pubDate>\n"
            .'<guid>'.$conf['homeurl'].'/index.php?name='.$name.'&op=view&id='.$rid."</guid>\n"
            .'<link>'.$conf['homeurl'].'/index.php?name='.$name.'&op=view&id='.$rid."</link>\n"
            .'<description>'.htmlspecialchars($prs->filterContent($rhometext, false, $name))."</description>\n"
            .'<comments>'.$conf['homeurl'].'/index.php?name='.$name.'&op=view&id='.$rid.'#'.$rid."</comments>\n";
            $content .= ($rctitle) ? '<category>'.htmlspecialchars($rctitle)."</category>\n" : '';
            $content .= "</item>\n\n";
        }
    }
    $content .= "</channel>\n</rss>";
    return $content;
}

# Output the OpenSearch description XML for browser search integration
function getOpenSearch() {
    global $conf;
    header('Content-Type: application/opensearchdescription+xml');
    header('Content-Encoding: none');
    return '<?xml version="1.0" encoding="'._CHARSET."\"?>\n"
    ."<OpenSearchDescription xmlns=\"http://a9.com/-/spec/opensearch/1.1/\">\n"
    .'<ShortName>'.htmlspecialchars($conf['sitename'])."</ShortName>\n"
    .'<Description>'.htmlspecialchars($conf['slogan'])."</Description>\n"
    .'<Url type="application/atom+xml" template="'.$conf['homeurl']."/index.php?name=search&word={searchTerms}\"/>\n"
    .'<Url type="application/rss+xml" template="'.$conf['homeurl']."/index.php?name=search&word={searchTerms}\"/>\n"
    .'<Url type="text/html" template="'.$conf['homeurl']."/index.php?name=search&word={searchTerms}\"/>\n"
    .(is_file(BASE_DIR.'/templates/'.$conf['theme'].'/images/favicon.svg')
        ? '<Image height="16" width="16" type="image/svg+xml">'.$conf['homeurl'].'/templates/'.$conf['theme'].'/images/favicon.svg</Image>\n'
        : '')
    .'<Attribution>Copyright (c) SLAED CMS '.$conf['version']."</Attribution>\n"
    .'<Language>'.htmlspecialchars(substr(_LOCALE, 0, 2))."</Language>\n"
    ."</OpenSearchDescription>\n";
}

# Return the processed sitemap XSL template with localized placeholder strings
function getOpenXsl(): string {
    global $conf;
    $path = SITEMAP_DIR.'/sitemap.xsl';
    if (file_exists($path)) {
        $file = file_get_contents($path);
        $licens = getLicenseHtml();
        $title = $conf['sitename'].' - '._SITEMAP;
        $langs = ['$lan[0]' => $title, '$lan[1]' => $licens, '$lan[2]' => _SITEMAP_XML, '$lan[3]' => _URL, '$lan[4]' => _PRIORITY, '$lan[5]' => _CHANGEFREQ, '$lan[6]' => _LASTMOD];
        $cont = strtr($file, $langs);
    } else {
        $cont = '';
    }
    return $cont;
}

# Show statistic
switch(getVar('get', 'stat', 'num', 0)) {
    case 1:
    $img = getVar('get', 'img', 'num', 0) ? '_'.getVar('get', 'img', 'num', 0) : '';
    $slog = COUNTER_DIR.'/statistic.log';
    $sdate = (is_file($slog) && is_readable($slog)) ? file($slog) : [];
    $con = explode('|', trim($sdate[0]));
    $image = imagecreatefrompng(getThemeImagePath('banners/stat'.$img.'.png'));
    $white = imagecolorallocate($image, 255, 255, 255);
    imagestring($image, 1, 22, 4, $con[2].'/'.$con[1], $white);
    header('Content-type: image/png');
    imagepng($image);
    exit;
}
