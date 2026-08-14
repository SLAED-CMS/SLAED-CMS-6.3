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
    $text = $tpl->getHtmlFrag('block-content', ['id' => 'repcom'.$cmid, 'content' => $prs->filterContent($val['body'], true, $cmod, 2, 'breaks')]);
    $sent = (string)($val['edited'] ?? '');
    $mark = ($sent !== '') ? $tpl->getHtmlFrag('inline-badge', ['title_text' => (string)_COMMENTS_EDITED, 'label' => format_time($sent, _TIMESTRING), 'is_comment_edit' => true]) : '';
    return $tpl->getHtmlFrag('comment', [
        'id' => $cmid,
        'depth' => $deep,
        'token' => $items ? $token : '',
        'username' => $avname,
        'username_html' => $unam,
        'report' => $utip,
        'date' => $date,
        'edited' => $mark,
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
                    'store' => 'comment.body',
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
# The unread badge is capped at 99+ because the counter sits in a round badge on the tile and a four-digit number would stretch it across the label
# Only the inner strip names its unread badge: that is the one an out-of-band swap reaches, and the cabinet home page draws its own tiles from the same items
function getUserNavItems(bool $home = false): array {
    global $db, $conf, $prv;
    $uid = intval((getUserInfo() ?? [])['id'] ?? 0);
    if ($conf['name'] !== 'account') getLang('account');
    $items = [];
    if ($home) $items[] = ['label' => _HOME, 'title' => _RETURNACCOUNT, 'href' => 'index.php?name=account', 'icon' => 'house'];
    if ($conf['privat']['act']) {
        $new = $prv->getUnreadCount($uid);
        $mark = ($new > 99) ? '99+' : (string)$new;
        $items[] = [
            'label' => _MESSAGES, 'title' => _PRIVAT, 'href' => 'index.php?name=account&op=privat', 'icon' => 'envelope',
            'badge' => $new ? $mark : '', 'badge_id' => $home ? 'pmbadgenav' : '',
        ];
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
                        'cid' => $cid, 'typ' => '0', 'mod' => $mod, 'store' => 'forum.body', 'text' => $hometext, 'rows' => 10,
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
                $htext = filterHtml($text);
                $room = checkEditorTextRoom($htext, 'forum.body');
                $stop = [];
                if ($text == '') $stop[] = _CERROR1;
                $limit = intval($conf['forum']['letter']);
                if ($limit > 0 && $long > $limit) $stop[] = _CERROR2;
                if ($room !== '') $stop[] = $room;
                if (!$stop) {
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

# The shelf strip of the private message page: the three mailboxes and the compose action, each with the quota ring that replaced the half-capacity alert
# Tone comes from the one ladder of getPercentTone(), the same one the server gauges and the debug panel read, and part is the per cent the template draws as the arc
# A shelf answers a solid ring where nothing can be measured and where nothing is left: under a whole arc the track carries its tone, so the join of the two ends never shows
# The unread badge belongs to the inbox and the saved box, never to the outbox, whose unread means the other side has not read it
# Count and note are two texts and never one: the theme decides whether a separator stands between them, and a shelf that answers only one of them gets no separator at all
# The ring reads as a whole sentence where there is a per cent to read out, and as the label with its note where there is none: no locale composes that from punctuation
# A shelf is a link to its own mailbox and «write» is the fourth one, because a mailbox the reader cannot reach is a picture and not a strip of navigation
# The badge of a shelf keeps its box even at zero: an out-of-band swap replaces an element that is there, and a counter that vanished has nothing left to replace
function getPrivatShelves(int $typ = 1): string {
    global $conf, $user, $tpl, $prv;
    if (!is_user() || !$conf['privat']['act']) return '';
    $uid = intval($user[0]);
    $items = [];
    foreach ([
        [PrivatBox::Inbox, (string)_PRIN, 'envelope', 'pmbadgein', 1],
        [PrivatBox::Outbox, (string)_PROUT, 'send', '', 2],
        [PrivatBox::Saved, (string)_PRSAVE, 'bookmark', 'pmbadgesv', 3],
    ] as [$box, $lab, $icon, $mark, $num]) {
        ['has' => $has, 'max' => $max, 'part' => $part] = $prv->getBoxFill($uid, $box);
        $new = $mark ? $prv->getUnreadBoxCount($uid, $box) : 0;
        $tone = ($max > 0) ? getPercentTone($part) : 'none';
        $items[] = [
            'label' => $lab,
            'icon' => $icon,
            'tone' => $tone,
            'href' => 'index.php?name=account&op=privat&typ='.$num,
            'current' => $typ === $num,
            'part' => number_format($part, 1, '.', ''),
            'full' => $max < 1 || $part >= 100,
            'ring' => ($max > 0) ? sprintf(_PRFILLED, $lab, round($part)) : '',
            'count' => ($max > 0) ? sprintf(_PRQUOTA, $has, $max) : (string)$has,
            'note' => ($max > 0) ? '' : (string)_PRNOLIMIT,
            'free' => ($tone === 'danger') ? sprintf(_PRFREE, max(0, $max - $has)) : '',
            'badge' => $new ? (($new > 99) ? '99+' : (string)$new) : '',
            'badge_id' => $mark,
        ];
    }
    $items[] = [
        'label' => (string)_PRWRITE, 'icon' => 'pencil-square', 'tone' => 'info', 'part' => '0.0', 'full' => true,
        'href' => 'index.php?name=account&op=privat&typ=4', 'current' => $typ === 4,
        'ring' => '', 'count' => '', 'note' => (string)_PRNEW, 'free' => '', 'badge' => '', 'badge_id' => '',
    ];
    return $tpl->getHtmlFrag('privat-shelves', ['nav_label' => _PRIVAT, 'items' => $items]);
}

# The focus deck: the unread of both received mailboxes as cards above the list, so what still wants attention is not buried on page four of a mailbox
# It reads the predicate of the cabinet badge and never a mailbox, because a message saved without being read is exactly the one that still needs answering
# The deck is a snapshot like everything else the page renders: an action taken inside it moves the badges and redraws the list, and the deck itself stays as it was drawn
# Nothing unread means no deck at all rather than an empty one, which is why this answers an empty string and the shell prints nothing where it stood
# It stands over the inbox and nowhere else: the outbox has no unread of its own to answer for, a saved message is not going anywhere, and «write» is an action and not a mailbox
# Every action of a slot names the inbox, because that is the list under the deck and the one an answer redraws; the subsystem authorizes each of them by the reader's own side
# Each of them carries the page, the search, the filters and the sort the reader is holding, so acting from the deck lands back on the selection the list was drawn under
# The tail counts what the cap left out and sends the reader to the mailbox filter that comes closest to the deck, which is the inbox unread and not the saved one
function getPrivatFocus(int $typ = 1): string {
    global $conf, $user, $tpl, $prv;
    if (!is_user() || !$conf['privat']['act'] || $typ !== 1) return '';
    $uid = intval($user[0]);
    $new = $prv->getUnreadCount($uid);
    if ($new < 1) return '';
    $rows = $prv->getRecentList($uid, 6, true);
    $tok = getSiteToken();
    $past = date('Y-m-d', strtotime('-1 day'));
    $state = '&pnum='.getVar('req', 'pnum', 'num', 1).getPrivatPick()['link'];
    $slots = [];
    foreach ($rows as $one) {
        $when = strtotime($one['time']);
        $open = 'index.php?go=1&op=setPrivateMessageRead&id='.$one['id'].'&cid=1';
        $base = 'index.php?go=1&op=updatePrivatBox&typ=1'.$state.'&id%5B%5D='.$one['id'].'&act=';
        $mkact = static fn(string $href, string $title, string $icon, bool $view = false, bool $reply = false): array => [
            'href' => $href, 'title' => $title, 'icon' => $icon, 'is_reply' => $reply,
            'target' => $view ? 'prview' : 'prlist', 'confirm' => ($icon === 'trash') ? (string)_ONDELETE : '',
        ];
        $acts = [$mkact($open, (string)_SHOW, 'eye', true), $mkact($open, (string)_PRREP, 'reply', true, true)];
        $acts[] = $mkact($base.'read', (string)_PRIVAT_READ, 'check2');
        $acts[] = $one['saved']
            ? $mkact($base.'unsave', (string)_PRIVAT_UNSAVE, 'bookmark-dash')
            : $mkact($base.'save', (string)_SAVE, 'archive');
        $acts[] = $mkact($base.'delete', (string)_DELETE, 'trash');
        $slots[] = [
            'avatar' => ($one['name'] !== '') ? getUserAvatarUrl(['avatar' => $one['avatar']]) : getUserAvatarUrl([], true),
            'author' => ($one['name'] !== '') ? $one['name'] : (string)_ANONYM,
            'stamp' => date('Y-m-d\TH:i', $when),
            'when' => match (true) {
                date('Y-m-d', $when) === date('Y-m-d') => date('H:i', $when),
                date('Y-m-d', $when) === $past => (string)_YESTERDAY,
                default => date('d.m', $when),
            },
            'title' => $one['title'],
            'snip' => $one['snip'],
            'href' => 'index.php?name=account&op=privat&id='.$one['id'],
            'open' => $open,
            'is_keep' => $one['saved'] > 0,
            'acts' => $acts,
        ];
    }
    return $tpl->getHtmlFrag('privat-focus', [
        'label' => (string)_PRFOCUS,
        'count' => sprintf(_PRFOCUSN, $new),
        'token' => $tok,
        'slots' => $slots,
        'more' => ($new > count($slots)) ? (string)($new - count($slots)) : '',
        'more_label' => (string)_MORE,
        'more_href' => 'index.php?name=account&op=privat&typ=1&stat=unread',
    ]);
}

# The three navigation counters one mutation moves, rendered as out-of-band swaps beside the answer that carries them
# They answer the mailbox and never the current selection, so a filter that hides everything still leaves the shelves counting what is really there
# The two shelf badges split the unread between the inbox and the saved box and add up to the cabinet one, which is why all three travel together
function getPrivatBadges(): string {
    global $user, $tpl, $prv;
    $uid = intval($user[0] ?? 0);
    $out = '';
    foreach ([
        ['pmbadgein', $prv->getUnreadBoxCount($uid, PrivatBox::Inbox)],
        ['pmbadgesv', $prv->getUnreadBoxCount($uid, PrivatBox::Saved)],
        ['pmbadgenav', $prv->getUnreadCount($uid)],
    ] as [$key, $new]) {
        $out .= $tpl->getHtmlFrag('span', [
            'id' => $key,
            'is_cab_badge' => true,
            'is_oob' => true,
            'is_hidden' => $new < 1,
            'text' => ($new > 99) ? '99+' : (string)$new,
        ]);
    }
    return $out;
}

# The selection a request carries, read under fixed names and reduced to a fixed set of values, because a sort and a filter are conditions and never fragments of SQL from an address
# The term keeps the per cent and the underscore a reader typed: the subsystem escapes both against the escape character it declares in the statement rather than dropping them here
# The ready query string travels beside the values, because the pager, every row action and the bulk route all have to carry the same selection back with them
function getPrivatPick(): array {
    $stat = (string)getVar('req', 'stat', 'var', '');
    $perd = (string)getVar('req', 'perd', 'var', '');
    $sort = (string)getVar('req', 'sort', 'var', '');
    $out = [
        'find' => trim((string)getVar('req', 'query', 'word', '')),
        'stat' => in_array($stat, ['unread', 'read'], true) ? $stat : '',
        'perd' => in_array($perd, ['new', 'old'], true) ? $perd : '',
        'sort' => in_array($sort, ['old', 'unread', 'name'], true) ? $sort : '',
    ];
    $link = '';
    foreach (['query' => $out['find'], 'stat' => $out['stat'], 'perd' => $out['perd'], 'sort' => $out['sort']] as $key => $val) {
        if ($val !== '') $link .= '&'.$key.'='.urlencode($val);
    }
    return $out + ['link' => $link];
}

# Build one dense list row of a mailbox, which is the same shape whether it is drawn inside its list or swapped out of band beside the answer that changed it
# The mailbox decides the state flag and the action set, so a row never has to be told twice which box it stands in, and only a received one can carry the unread mark
# Today and yesterday are read as a time and an older day as a date, because the day header standing above the row already carries the day itself
# The subject is a link to the deep route as well as the swap, so the row still opens its message where the swap cannot run
# Every action of a row names its own id and its own act in the address and sends no body at all: the row stands inside the selection form, whose values would otherwise override both
function getPrivatRowData(array $row, int $typ, string $tok, int $pick = 0, string $state = ''): array {
    global $tpl;
    $when = strtotime($row['time']);
    $past = date('Y-m-d', strtotime('-1 day'));
    $link = 'index.php?go=1&op=setPrivateMessageRead&id='.$row['id'].'&cid='.(($typ == 2) ? 2 : 1).'&row=1'.$state;
    $base = 'index.php?go=1&op=updatePrivatBox&typ='.$typ.$state.'&id%5B%5D='.$row['id'].'&act=';
    $mkact = static fn(string $href, string $title, string $icon, bool $stay = false): array => [
        'href' => $href, 'title' => $title, 'icon_name' => $icon,
        'is_htmx' => true, 'is_post' => true, 'hx_headers' => $tok, 'no_params' => true,
        'hx_target' => $stay ? '#prview' : '#prlist',
        'confirm_text' => ($icon === 'trash') ? (string)_ONDELETE : '',
    ];
    $mark = match ($typ) {
        3 => $tpl->getHtmlFrag('span', ['title' => _PRMOVE, 'is_message_save' => true, 'text' => '']),
        2 => $tpl->getHtmlFrag('span', [
            'title' => $row['viewed'] ? _PROLD : _PROUTNEW,
            'is_message_out' => !$row['viewed'], 'is_message_read' => $row['viewed'] > 0, 'text' => '',
        ]),
        default => $tpl->getHtmlFrag('span', [
            'title' => $row['viewed'] ? _PROLD : _PRNEW,
            'is_message_in' => true, 'is_hidden' => $row['viewed'] > 0, 'text' => '',
        ]),
    };
    $acts = [$mkact($link, (string)_SHOW, 'eye', true)];
    if ($typ == 1) $acts[] = $mkact($base.'save', (string)_SAVE, 'archive');
    if ($typ == 3) $acts[] = $mkact($base.'unsave', (string)_PRIVAT_UNSAVE, 'bookmark-dash');
    if ($typ != 2) {
        $acts[] = $row['viewed']
            ? $mkact($base.'unread', (string)_PRIVAT_NEW, 'envelope')
            : $mkact($base.'read', (string)_PRIVAT_READ, 'envelope-open');
    }
    $acts[] = $mkact($base.'delete', (string)_DELETE, 'trash');
    return [
        'row_id' => 'pmrow'.$row['id'],
        'is_new' => !$row['viewed'] && $typ != 2,
        'is_open' => $pick === $row['id'],
        'pick_label' => $row['title'],
        'pick_html' => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'id[]', 'value_attr' => (string)$row['id'], 'is_check' => true]),
        'avatar' => ($row['name'] !== '') ? getUserAvatarUrl(['avatar' => $row['avatar']]) : getUserAvatarUrl([], true),
        'open_href' => 'index.php?name=account&op=privat&id='.$row['id'].$state,
        'open_post' => $link,
        'token' => $tok,
        'title' => $row['title'],
        'stamp' => date('Y-m-d\TH:i', $when),
        'when' => (date('Y-m-d', $when) >= $past) ? date('H:i', $when) : date('d.m', $when),
        'author' => (string)_ANONYM,
        'author_html' => ($row['name'] !== '') ? user_info($row['name']) : '',
        'snip' => $row['snip'],
        'mark_html' => $mark,
        'actions_html' => getActionMenu($acts),
    ];
}

# Render one column of the private message layout: a mailbox as the left one for typ 1 to 3, one message or the compose state as the right one, the empty pane for nothing at all
# Every mailbox read runs through the private-message subsystem, so no state column is restated here and a list, its counter and its quota can never disagree
# This function only reads: opening a message is what marks it read and that is a POST route of its own, which hands the row it has already loaded down instead of having it read twice
# A stored message is source from stage 2 on: the body is rendered safe in the format its own row names, and the title is plain text the template escapes where it prints it
# The right column carries what the reply form has to hold in a hidden textarea of its own, because that form stands below the swapped region and is filled through the editor API and never by writing its field
# The bulk action and its button stand in the footer of the column while the selection lives in the scrolling form above it, so both are bound back to that form by name instead of by nesting
# Only a caller that names no column at all is answered from the request: zero means the empty pane, and a page that asks for it while the address carries a mailbox must not be handed that mailbox instead
# The compose state names what bounds a send before the writer runs into it: the interval out of the settings, and the two lengths the stored columns allow
function getPrivateMessageView(string|array $stop = '', string $info = '', int $typ = -1, array $view = []): string {
    global $db, $user, $conf, $tpl, $prs, $prv;
    if (!is_user() || !$conf['privat']['act']) return $tpl->getHtmlFrag('alert', ['text' => _ERROR, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
    if ($typ < 0) $typ = getVar('req', 'typ', 'num', 0);
    $uid = intval($user[0]);
    $tok = getSiteToken();
    $conf['name'] = 'account';
    $note = '';
    if ($stop) {
        $note = $tpl->getHtmlFrag('alert', [
            'text' => is_array($stop) ? '' : $stop, 'messages' => is_array($stop) ? $stop : [],
            'meta' => '', 'type' => 'warn', 'is_warn' => true,
        ]);
    } elseif ($info) {
        $note = $tpl->getHtmlFrag('alert', ['text' => $info, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
    }
    if ($typ >= 1 && $typ <= 3) {
        $box = match ($typ) {2 => PrivatBox::Outbox, 3 => PrivatBox::Saved, default => PrivatBox::Inbox};
        $pick = getPrivatPick();
        $data = $prv->getMessageList($uid, $box, getVar('req', 'pnum', 'num', 1), $pick);
        $seek = $pick['find'] !== '' || $pick['stat'] !== '' || $pick['perd'] !== '';
        $state = '&pnum='.$data['page'].$pick['link'];
        $max = $prv->getBoxLimit($box);
        if ($max > 0 && $data['total'] >= $max) {
            $note .= $tpl->getHtmlFrag('alert', [
                'text' => sprintf(($typ == 3) ? _PRSAVEEXIT : _PRINEXIT, $max),
                'meta' => '', 'type' => 'warn', 'is_warn' => true,
            ]);
        }
        $open = getVar('req', 'id', 'num', 0);
        $past = date('Y-m-d', strtotime('-1 day'));
        $days = [];
        foreach ($data['rows'] as $one) {
            $day = date('Y-m-d', strtotime($one['time']));
            if (!isset($days[$day])) {
                $days[$day] = [
                    'label' => match ($day) {
                        date('Y-m-d') => (string)_TODAY,
                        $past => (string)_YESTERDAY,
                        default => format_time($one['time'], _DATESTRING),
                    },
                    'count' => 0,
                    'rows' => [],
                ];
            }
            $days[$day]['count']++;
            $days[$day]['rows'][] = getPrivatRowData($one, $typ, $tok, $open, $state);
        }
        $mass = [];
        if ($typ != 2) {
            $mass['read'] = (string)_PRIVAT_READ;
            $mass['unread'] = (string)_PRIVAT_NEW;
        }
        if ($typ == 1) $mass['save'] = (string)_SAVE;
        if ($typ == 3) $mass['unsave'] = (string)_PRIVAT_UNSAVE;
        $mass['delete'] = (string)_DELETE;
        $opts = '';
        foreach ($mass as $key => $lab) $opts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $key, 'label_text' => $lab]);
        $bulk = $tpl->getHtmlFrag('inline-badge', ['is_action_label' => true, 'label' => _CHECKOP])
            .$tpl->getHtmlFrag('select', [
                'name_attr' => 'act', 'title' => _CHECKOP, 'options_html' => $opts, 'select_attr' => 'form="prbulk"',
            ])
            .$tpl->getHtmlFrag('button', [
                'button_type' => 'submit', 'submit_label' => _OK, 'title' => _CHECKOP, 'input_attr' => 'form="prbulk"',
                'hx_post' => 'index.php?go=1&op=updatePrivatBox',
                'hx_include' => '#prbulk', 'hx_target' => '#prlist',
            ]);
        $keys = $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => $tok, 'input_attr' => ''])
            .$tpl->getHtmlFrag('hidden', ['name_attr' => 'typ', 'value_attr' => (string)$typ, 'input_attr' => ''])
            .$tpl->getHtmlFrag('hidden', ['name_attr' => 'pnum', 'value_attr' => (string)$data['page'], 'input_attr' => '']);
        foreach (['query' => $pick['find'], 'stat' => $pick['stat'], 'perd' => $pick['perd'], 'sort' => $pick['sort']] as $key => $val) {
            if ($val !== '') $keys .= $tpl->getHtmlFrag('hidden', ['name_attr' => $key, 'value_attr' => $val, 'input_attr' => '']);
        }
        return $tpl->getHtmlPart('privat-list', [
            'alert_html' => $note,
            'groups' => array_values($days),
            'empty_title' => $seek ? (string)_PRNOHIT : (string)_NO_INFO,
            'empty_icon' => $seek ? 'funnel' : 'inbox',
            'found' => $seek ? sprintf(_PRFOUND, $data['total'], $prv->getMessageCount($uid, $box)) : '',
            'shown' => $data['total'] ? sprintf(_PRSHOWN, $data['offset'] + 1, min($data['offset'] + $data['limit'], $data['total'])) : '',
            'bulk_url' => 'index.php?go=1&op=updatePrivatBox',
            'hidden_html' => $keys,
            'pager_html' => getTplPagerView($data['page'], $data['pages'], intval($conf['privat']['nump']), static fn(int $i): array => [
                'query' => 'index.php?go=1&op=getPrivateMessageView&typ='.$typ.'&pnum='.$i.$pick['link'],
                'target_id' => 'prlist',
            ], ['count' => $data['total'], 'limit' => $data['limit'], 'page' => $data['limit']]),
            'bulk_html' => $tpl->getHtmlFrag('block-content', ['is_bulk_bar' => true, 'content' => $bulk]),
        ]);
    }
    if ($typ == 4) {
        if (!$view) {
            $post = filterText(mb_substr(urldecode((string)getVar('req', 'uname', 'raw', '')), 0, 25));
            $back = '';
            $head = '';
            $from = getVar('req', 'id', 'num', 0);
            if ($from && getVar('req', 'fwd', 'num', 0) == 1) {
                $side = (getVar('req', 'cid', 'num', 0) == 2) ? PrivatBox::Outbox : PrivatBox::Inbox;
                $old = $prv->getMessageView($uid, $from, $side);
                if ($old) {
                    $post = '';
                    $back = (getEditorMode() !== 'html') ? getDecodedText(replace_break($old['body'])) : $old['body'];
                    $head = _PRFWD.': '.$old['title'];
                }
            }
            $wait = intval($conf['privat']['send']);
            $chips = $wait ? $tpl->getHtmlFrag('span', ['title' => '', 'chip_tone' => 'info', 'icon_name' => 'hourglass-split', 'text' => sprintf(_PRWAIT, $wait)]) : '';
            $chips .= $tpl->getHtmlFrag('span', ['title' => '', 'chip_tone' => 'neutral', 'icon_name' => 'person', 'text' => sprintf(_PRLIMTO, 25)]);
            $chips .= $tpl->getHtmlFrag('span', ['title' => '', 'chip_tone' => 'neutral', 'icon_name' => 'type', 'text' => sprintf(_PRLIMSUB, 100)]);
            return $tpl->getHtmlPart('privat-view', [
                'alert_html' => $note,
                'token' => $tok,
                'title' => (string)_PRNEW,
                'chips_html' => $chips,
                'is_wipe' => true,
                'wipe_label' => (string)_PRWIPE,
                'is_carry' => $post !== '' || $head !== '' || $info !== '',
                'carry_name' => $post,
                'carry_title' => $head,
                'carry_body' => $back,
            ]);
        }
        $sql = 'SELECT u.id, u.name, u.avatar, u.website, u.sig, g.name AS gname FROM '.PREFIX_DB.'_users AS u'
            .' LEFT JOIN '.PREFIX_DB.'_groups AS g ON ((g.extra = 1 AND u.grp = g.id) OR (g.extra != 1 AND u.points >= g.points))'
            .' WHERE u.id = :pid ORDER BY g.extra DESC, g.points DESC';
        $mate = $db->getSqlRow($db->getSqlQuery($sql, ['pid' => $view['partner']])) ?: [];
        $pname = (string)($mate['name'] ?? '');
        $show = ($pname !== '') ? $pname : (string)_ANONYM;
        $gname = (string)($mate['gname'] ?? '');
        $mine = $view['uidin'] === $uid;
        $mod = $mine ? ($view['saved'] ? 3 : 1) : 2;
        $base = 'index.php?go=1&op=updatePrivatBox&typ='.$mod.'&id%5B%5D='.$view['id'].'&act=';
        $acts = [];
        if ($mine) {
            $acts[] = $view['saved']
                ? ['href' => $base.'unsave', 'title' => (string)_PRIVAT_UNSAVE, 'icon' => 'bookmark-dash', 'on' => true, 'confirm' => '']
                : ['href' => $base.'save', 'title' => (string)_SAVE, 'icon' => 'archive', 'on' => false, 'confirm' => ''];
            $acts[] = $view['viewed']
                ? ['href' => $base.'unread', 'title' => (string)_PRIVAT_NEW, 'icon' => 'envelope', 'on' => false, 'confirm' => '']
                : ['href' => $base.'read', 'title' => (string)_PRIVAT_READ, 'icon' => 'envelope-open', 'on' => false, 'confirm' => ''];
        }
        $acts[] = ['href' => $base.'delete', 'title' => (string)_DELETE, 'icon' => 'trash', 'on' => false, 'confirm' => (string)_ONDELETE];
        $chips = $tpl->getHtmlFrag('inline-badge', [
            'title_text' => _PADD, 'label' => format_time($view['time'], _TIMESTRING), 'is_comment_date' => true,
        ]);
        if ($view['saved']) {
            $chips .= $tpl->getHtmlFrag('span', ['title' => _PRMOVE, 'is_message_save' => true, 'text' => _PRMOVE]);
        } elseif (!$mine) {
            $chips .= $tpl->getHtmlFrag('span', [
                'title' => $view['viewed'] ? _PROLD : _PROUTNEW, 'text' => $view['viewed'] ? _PROLD : _PROUTNEW,
                'is_message_out' => !$view['viewed'], 'is_message_read' => $view['viewed'] > 0,
            ]);
        } else {
            $chips .= $tpl->getHtmlFrag('span', [
                'title' => $view['viewed'] ? _PROLD : _PRNEW, 'text' => $view['viewed'] ? _PROLD : _PRNEW,
                'is_message_in' => true,
            ]);
        }
        if ($conf['privat']['profil'] && $pname !== '') {
            $chips .= $tpl->getHtmlFrag('link', [
                'href' => 'index.php?name=account&op=view&uname='.urlencode($pname),
                'title' => _PERSONALINFO, 'label' => _PERSONALINFO, 'icon_name' => 'person-badge', 'chip_tone' => 'neutral',
            ]);
        }
        if ($conf['privat']['web'] && ($mate['website'] ?? '')) {
            $chips .= $tpl->getHtmlFrag('link', [
                'href' => (string)$mate['website'], 'title' => _DOWNLLINK, 'label' => _DOWNLLINK,
                'icon_name' => 'globe', 'chip_tone' => 'neutral', 'is_blank' => true,
            ]);
        }
        if (is_moder($conf['name'])) $chips .= Geoip::getIpHtml($view['ip'], true);
        $quote = '[quote]'.$view['body'].'[/quote]';
        if (getEditorMode() !== 'html') $quote = getDecodedText(replace_break($quote));
        return $tpl->getHtmlPart('privat-view', [
            'alert_html' => $note,
            'token' => $tok,
            'title' => $view['title'],
            'avatar' => ($pname !== '') ? getUserAvatarUrl(['avatar' => (string)($mate['avatar'] ?? '')]) : getUserAvatarUrl([], true),
            'author' => $show,
            'author_html' => ($pname !== '') ? user_info($pname, false) : '',
            'note' => ($gname !== '') ? _GROUP.': '.$gname : (string)_RANK,
            'chips_html' => $chips,
            'text_html' => $prs->filterContent($view['body'], true, $conf['name'], 2, 'breaks'),
            'sig_html' => ($mate['sig'] ?? '') ? $prs->filterContent((string)$mate['sig'], false, $conf['name'], 2) : '',
            'is_reply' => true,
            'reply_label' => (string)_PRREP,
            'forward_href' => 'index.php?go=1&op=getPrivateMessageView&typ=4&id='.$view['id'].'&fwd=1&cid='.($mine ? 1 : 2),
            'forward_label' => (string)_PRFORWARD,
            'acts' => $acts,
            'is_carry' => true,
            'carry_name' => $pname,
            'carry_title' => _PRREP.': '.$view['title'],
            'carry_body' => $quote,
        ]);
    }
    return $tpl->getHtmlPart('privat-view', [
        'alert_html' => $note,
        'token' => $tok,
        'is_blank' => true,
        'blank_icon' => 'envelope-open',
        'blank_title' => (string)_PRPICK,
        'blank_text' => (string)_PRPICKT,
    ]);
}

# Validate and send a new private message; answers the compose view with the outcome of the send
# The subsystem answers a machine code and the id it stored, so the notification links the message that was really written instead of the newest row of the table
# The recipient of the notification is read back from the stored message rather than resolved from the name again, so the mail reaches the account the message went to
# The preference the notification asks is psmail, the private-message one the profile form offers, and no longer the forum preference fsmail that this path used to read
# Points and the queued mail are side effects of a send that was really stored: both run after the subsystem answered ok, and neither can take an accepted message back
# Both fields travel to the subsystem as the author submitted them: from stage 2 on a message stores source, and the writer that used to escape it on the way in is gone
function addPrivateMessage(): void {
    global $user, $conf, $tpl, $mailer, $db, $prv;
    $name = filterText(mb_substr((string)getVar('post', 'name', 'raw', ''), 0, 25));
    $uid = (is_user()) ? intval($user[0]) : 0;
    if (!$conf['privat']['act'] || !$uid) {
        echo getPrivateMessageView((string)_ERROR, '', 4);
        return;
    }
    $new = $prv->addMessage(
        $uid,
        $name,
        (string)getVar('post', 'title', 'raw', ''),
        (string)getVar('post', 'text', 'raw', ''),
        getIp()
    );
    if ($new['error'] !== 'ok') {
        $note = match ($new['error']) {
            'no_recipient' => (string)_CERROR6,
            'unknown_recipient' => (string)_CERROR7,
            'self' => (string)_CERROR8,
            'no_title' => (string)_CERROR,
            'no_body' => (string)_CERROR1,
            'word_long' => (string)_CERROR2,
            'not_logged' => (string)_CERROR3,
            'flood' => sprintf(_CERROR5, intval($conf['privat']['send'])),
            'quota' => sprintf(_PRSENDOVER, $name),
            'no_room' => (string)($new['note'] ?? _ERROR),
            default => (string)_ERROR,
        };
        echo getPrivateMessageView([$note], '', 4);
        return;
    }
    updatePoints(45);
    if ($conf['privat']['newmail']) {
        $sent = $prv->getMessageView($uid, $new['id'], PrivatBox::Outbox);
        [$mail, $wish] = $db->getSqlRow($db->getSqlQuery(
            'SELECT email, psmail FROM '.PREFIX_DB.'_users WHERE id = :uidin',
            ['uidin' => intval($sent['partner'] ?? 0)]
        ));
        if ($mail && $wish) {
            $back = $conf['homeurl'].'/index.php?name=account&op=privat&id='.$new['id'].'#prmess';
            $link = $tpl->getHtmlFrag('link', ['href' => $back, 'title' => '', 'label_html' => $back]);
            $text = str_replace('[text]', sprintf(_PRNEWMAIL, filterText(mb_substr($user[1], 0, 25)), $link), $conf['mtemp']);
            $mailer->addQueue([
                'kind' => 'privat', 'email' => $mail, 'title' => $conf['sitename'].' - '._PRIVAT,
                'body' => $text, 'sender' => $conf['adminmail'], 'prio' => 3,
            ]);
        }
    }
    echo getPrivateMessageView('', sprintf(_PRSENDED, $name), 4);
}

# Open one message of a mailbox and answer the right column of the layout, with the row it changed and the navigation counters as out-of-band swaps beside it
# Opening a received message is what marks it read, so this is a POST route and not a link: the row is read once here and handed to the view instead of being read a second time
# Only a read that really changed something carries the out-of-band part: a message that was open already moves no counter and leaves its row exactly as the list drew it
# The row travels back only where the caller stands on it: a list row asks, the deck and the deep link do not, because a swap with no target is an error and not a no-op
# What that costs is one stale mark in a list the reader is not looking at, which is the snapshot the whole page already is, and the next list request draws it right
# Nothing else follows the mutation: the filtered total, the pager and the row set are what the last list request answered, and they come back into line on the next one
# A message the reader holds no copy of answers the empty pane, because an id alone is not a permission and a refusal has nothing to show
# The row it swaps back carries the selection it was drawn under, so its own actions still answer the same page, filter, search and sort the reader is looking at
function setPrivateMessageRead(): void {
    global $conf, $user, $tpl, $prv;
    $uid = (is_user()) ? intval($user[0]) : 0;
    $id = getVar('req', 'id', 'num', 0);
    $box = (getVar('req', 'cid', 'num', 0) == 2) ? PrivatBox::Outbox : PrivatBox::Inbox;
    if (!$uid || !$conf['privat']['act'] || !$id) {
        echo getPrivateMessageView((string)_ERROR, '', 4);
        return;
    }
    $view = $prv->getMessageView($uid, $id, $box);
    if (!$view) {
        echo getPrivateMessageView('', '', 0);
        return;
    }
    $seen = $box === PrivatBox::Inbox && !$view['viewed'] && $prv->setMessageRead($uid, [$id], true);
    if ($seen) $view['viewed'] = 1;
    echo getPrivateMessageView('', '', 4, $view);
    if (!$seen) return;
    if (getVar('req', 'row', 'num', 0) == 1) {
        $state = '&pnum='.getVar('req', 'pnum', 'num', 1).getPrivatPick()['link'];
        echo $tpl->getHtmlFrag('privat-row', getPrivatRowData($view, $view['saved'] ? 3 : 1, getSiteToken(), $id, $state) + ['is_oob' => true]);
    }
    echo getPrivatBadges();
}

# Apply one mailbox action to the messages a view has selected and answer that mailbox again
# One row action and a bulk action are the same request with one id or many, so a mailbox has one mutation route and every selection travels through the same checks
# The action is taken from the fixed set its own mailbox offers, and every submitted id is rechecked against the reader inside the transaction of the subsystem
# The inbox offers unsave too, because the focus deck stands over it and shows saved messages never read: the write authorizes itself by the reader's own side either way
# An empty selection is a mistake and says so; an action a mailbox never offered can only come from a forged request and answers the generic error
# The full-folder message is kept for a folder that really cannot take the batch, so a save refused for any other reason is not reported as a quota that was never reached
function updatePrivatBox(): void {
    global $conf, $user, $prv;
    $uid = (is_user()) ? intval($user[0]) : 0;
    $typ = getVar('req', 'typ', 'num', 1);
    $typ = ($typ >= 1 && $typ <= 3) ? $typ : 1;
    $act = (string)getVar('req', 'act', 'var', '');
    $ids = getVar('req', 'id[]', 'num', []);
    $box = match ($typ) {2 => PrivatBox::Outbox, 3 => PrivatBox::Saved, default => PrivatBox::Inbox};
    $keep = match ($typ) {
        2 => ['delete'],
        3 => ['read', 'unread', 'unsave', 'delete'],
        default => ['read', 'unread', 'save', 'unsave', 'delete'],
    };
    if (!$uid || !$conf['privat']['act'] || !in_array($act, $keep, true)) {
        echo getPrivateMessageView((string)_ERROR, '', $typ);
        return;
    }
    if (!$ids) {
        echo getPrivateMessageView((string)_PRIVAT_NOSEL, '', $typ);
        return;
    }
    $done = match ($act) {
        'read' => $prv->setMessageRead($uid, $ids, true),
        'unread' => $prv->setMessageRead($uid, $ids, false),
        'save' => $prv->setMessageSaved($uid, $ids, true),
        'unsave' => $prv->setMessageSaved($uid, $ids, false),
        default => $prv->deleteMessage($uid, $ids, $box),
    };
    $max = $prv->getBoxLimit(PrivatBox::Saved);
    $has = ($act === 'save') ? $prv->getMessageCount($uid, PrivatBox::Saved) : 0;
    $stop = ($act === 'save' && $max > 0 && ($has + count($ids)) > $max) ? sprintf(_PRSAVEEXIT, $max) : (string)_ERROR;
    echo getPrivateMessageView($done ? '' : $stop, '', $typ);
    echo getPrivatBadges();
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
        $text = cutstr(str_replace([_QUOTE, _CODE], '', filterText($prs->filterContent($row['body'], true, $conf['name'], 0, 'breaks'))), 70);
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
# Every address is escaped before it enters the document: the ampersand of a query string is a character reference in XML, and a reader that parses strictly rejects the whole feed
function getRssChannel() {
    global $db, $conf, $prs;
    header_remove('X-Content-Type-Options');
    header('Content-Type: application/rss+xml; charset='._CHARSET);

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
    .'<link>'.htmlspecialchars($conf['homeurl'])."</link>\n"
    .'<description>'.htmlspecialchars($conf['slogan'])."</description>\n"
    .'<generator>SLAED CMS '.$conf['version']."</generator>\n"
    .'<copyright>Copyright (c) SLAED CMS '.$conf['version']."</copyright>\n"
    .'<language>'.htmlspecialchars(substr(_LOCALE, 0, 2))."</language>\n"
    .'<lastBuildDate>'.date('D, j M Y H:i:s O')."</lastBuildDate>\n\n";
    if ($name && $name != 'content' && $name != 'shop' && $result) {
        while ([$rid, $uname, $rtitle, $rtime, $rhometext, $rctitle, $user_name] = $db->getSqlRow($result)) {
            $rauthor = ($user_name) ? $user_name : (($uname) ? $uname : _ANONYM);
            $rurl = htmlspecialchars($conf['homeurl'].'/index.php?name='.$name.'&op=view&id='.$rid);
            $content .= "<item>\n"
            .'<title>'.htmlspecialchars($rtitle)."</title>\n"
            .'<pubDate>'.htmlspecialchars(date('D, j M Y H:i:s O', strtotime($rtime)))."</pubDate>\n"
            .'<guid>'.$rurl."</guid>\n"
            .'<link>'.$rurl."</link>\n"
            .'<description>'.htmlspecialchars($prs->filterContent($rhometext, false, $name))."</description>\n"
            .'<comments>'.$rurl.'#'.$rid."</comments>\n";
            $content .= ($rctitle) ? '<category>'.htmlspecialchars($rctitle)."</category>\n" : '';
            $content .= '<author>antispam@antispam.com ('.htmlspecialchars($rauthor).")</author>\n"
            ."</item>\n\n";
        }
    } elseif ($name && $name == 'content' && $result) {
        [$rid, $rtitle, $rhometext, $rtime] = $db->getSqlRow($result);
        $rurl = htmlspecialchars($conf['homeurl'].'/index.php?name='.$name.'&op=view&id='.$rid);
        $content .= "<item>\n"
        .'<title>'.htmlspecialchars($rtitle)."</title>\n"
        .'<pubDate>'.htmlspecialchars(date('D, j M Y H:i:s O', strtotime($rtime)))."</pubDate>\n"
        .'<guid>'.$rurl."</guid>\n"
        .'<link>'.$rurl."</link>\n"
        .'<description>'.htmlspecialchars($prs->filterContent($rhometext, false, $name))."</description>\n"
        ."</item>\n\n";
    } elseif ($name && $name == 'shop' && $result) {
        while ([$rid, $rtitle, $rtime, $rhometext, $rctitle] = $db->getSqlRow($result)) {
            $rurl = htmlspecialchars($conf['homeurl'].'/index.php?name='.$name.'&op=view&id='.$rid);
            $content .= "<item>\n"
            .'<title>'.htmlspecialchars($rtitle)."</title>\n"
            .'<pubDate>'.htmlspecialchars(date('D, j M Y H:i:s O', strtotime($rtime)))."</pubDate>\n"
            .'<guid>'.$rurl."</guid>\n"
            .'<link>'.$rurl."</link>\n"
            .'<description>'.htmlspecialchars($prs->filterContent($rhometext, false, $name))."</description>\n"
            .'<comments>'.$rurl.'#'.$rid."</comments>\n";
            $content .= ($rctitle) ? '<category>'.htmlspecialchars($rctitle)."</category>\n" : '';
            $content .= "</item>\n\n";
        }
    }
    $content .= "</channel>\n</rss>";
    return $content;
}

# Output the OpenSearch description XML for browser search integration
# Every address is escaped before it enters the document: an ampersand of a query string is a character reference in XML, and a browser that parses this strictly drops the whole file
function getOpenSearch() {
    global $conf;
    header('Content-Type: application/opensearchdescription+xml');
    $find = htmlspecialchars($conf['homeurl'].'/index.php?name=search&word={searchTerms}');
    return '<?xml version="1.0" encoding="'._CHARSET."\"?>\n"
    ."<OpenSearchDescription xmlns=\"http://a9.com/-/spec/opensearch/1.1/\">\n"
    .'<ShortName>'.htmlspecialchars($conf['sitename'])."</ShortName>\n"
    .'<Description>'.htmlspecialchars($conf['slogan'])."</Description>\n"
    .'<Url type="application/atom+xml" template="'.$find."\"/>\n"
    .'<Url type="application/rss+xml" template="'.$find."\"/>\n"
    .'<Url type="text/html" template="'.$find."\"/>\n"
    .(is_file(BASE_DIR.'/templates/'.$conf['theme'].'/images/favicon.svg')
        ? '<Image height="16" width="16" type="image/svg+xml">'.htmlspecialchars($conf['homeurl'].'/templates/'.$conf['theme'].'/images/favicon.svg')."</Image>\n"
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
