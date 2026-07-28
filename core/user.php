<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Render the comment list and submission form for an item
function setComShow(int $id = 0, int $acomm = 0): string {
    global $conf, $user, $tpl;
    $cont = $tpl->getHtmlFrag('title', ['title' => _COMMENTS, 'is_level_two' => true]);
    $cont .= $tpl->getHtmlFrag('block-content', ['id' => 'repcsave', 'content' => ashowcom($id, $conf['name'])]);
    if (!is_user() && $conf['comments']['anonpost'] == 0) {
        $cont .= $tpl->getHtmlFrag('alert', ['text' => _NOANONCOMMENTS, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
    } else {
        $userinfo = getUserInfo();
        if ($acomm == 1 || $userinfo['access'] || (!is_user() && $conf['comments']['anonpost'] == 1)) $cont .= $tpl->getHtmlFrag('alert', ['text' => _POSTNOTE, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
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
                    'id' => 1,
                    'name' => 'text',
                    'value' => '',
                    'mod' => $conf['name'],
                    'rows' => '5',
                    'placeholder' => _COMMENT,
                ]),
            ]);
        $submit = $tpl->getHtmlFrag('form-submit', ['button_type' => 'submit',
            'label' => _COMMENTREPLY,
            'title' => _COMMENTREPLY,
            'hx_post' => 'index.php?go=1&op=addComment&id='.$id.'&mod='.$conf['name'].'&token='.getPageToken(),
            'hx_include' => '#formcsave',
            'hx_target' => '#repcsave',
            'hx_on_click' => 'if (!document.getElementById(\'formcsave\').querySelector(\'[name=&quot;text&quot;]\').value.trim()) { alert(\''._CERROR1.'\'); event.preventDefault(); }',
            'hx_on_after' => 'document.getElementById(\'formcsave\').reset()',
        ]);
        $cont .= $tpl->getHtmlPart('form-add', [
            'no_action' => true,
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

# Validate and save a new comment; echoes the updated comment list on success
function addComment() {
    global $db, $user, $conf, $tpl;
    $id       = getVar('req', 'id',   'num',  0);
    $mod      = filterVar(getVar('req', 'mod',  'text', ''));
    $acomm    = getCommentMode($mod, $id);
    $postname = filterText(substr(getVar('post', 'name', 'raw', ''), 0, 25));
    $ip       = getip();
    $comment  = trim(getVar('post', 'text', 'raw', ''));
    [$date] = $db->getSqlRow($db->getSqlQuery('SELECT time FROM '.PREFIX_DB.'_comment WHERE ip = :ip ORDER BY id DESC LIMIT 1', ['ip' => $ip]));
    $stime = ($date ? strtotime($date) : 0) + $conf['comments']['send'];
    $checks = str_replace(["\n", "\r", "\t"], ' ', $comment);
    $words = array_map(static fn(string $one): int => mb_strlen($one, 'UTF-8'), explode(' ', $checks));
    $long = $words ? max($words) : 0;
    $stop = '';
    if ($comment === '') $stop = _CERROR1;
    if ($long > $conf['comments']['letter']) $stop = _CERROR2;
    if ((!is_user() && $postname === '') || (!is_user() && $conf['comments']['anonpost'] == 0)) $stop = _CERROR3;
    if ($stime > time()) $stop = sprintf(_CERROR5, $conf['comments']['send']);
    if (!is_moder($mod) && (($conf['comments']['link'] == 1 && !is_user()) || ($conf['comments']['link'] == 2)) && stripos($comment, 'http://') !== false) $stop = _CERROR9;
    $urlclick = (!is_moder($mod) && (($conf['comments']['alink'] == 1 && !is_user()) || ($conf['comments']['alink'] == 2))) ? 1 : 0;
    if (checkCaptcha('comment')) $stop = _SECCODEINCOR;
    if (!$stop && $acomm) {
        $comment = filterHtml($comment, $urlclick);
        if (is_user()) {
            $postid = intval($user[0]);
            $userinfo = getUserInfo();
            $postname = $userinfo['name'];
            $status = (!is_moder($mod) && ($acomm == 1 || $userinfo['access'])) ? 0 : 1;
        } else {
            $postid = '0';
            $postname = $postname;
            $status = (!is_moder($mod) && ($acomm == 1 || $conf['comments']['anonpost'] == 1)) ? 0 : 1;
        }
        $db->getSqlQuery(
            'INSERT INTO '.PREFIX_DB.'_comment VALUES (NULL, :cid, :modul, NOW(), :uid, :name, :ip, :comment, :status)',
            ['cid' => $id, 'modul' => $mod, 'uid' => $postid, 'name' => $postname, 'ip' => $ip, 'comment' => $comment, 'status' => $status]
        );
        if ($status) numcom($id, $mod, 0, $postid);
        [$lcom_id] = $db->getSqlRow($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_comment WHERE cid = :cid AND uid = :uid ORDER BY id DESC LIMIT 1', ['cid' => $id, 'uid' => $postid]));
        $finishlink = $conf['homeurl'].'/index.php?name='.$mod.'&op=view&id='.$id.'#'.$lcom_id;
        $clink = $tpl->getHtmlFrag('link', ['href' => $finishlink, 'title' => '', 'label_html' => $finishlink]);
        addAdminMail($conf['comments']['addmail'], $mod, $postname, getModuleName($mod), 1, $clink);
        echo ashowcom($id, $mod);
    } else {
        $stop = ($stop) ? $stop : _ERROR;
        echo $tpl->getHtmlFrag('alert', ['text' => $stop, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
    }
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
            if (is_moder(isset($conf['name']))) {
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
                        'cid' => $cid, 'typ' => '0', 'mod' => $mod, 'text' => $hometext, 'rows' => 15,
                    ])
                    : $prs->filterContent($hometext, false, $mod, 2);
                echo $content;
            } else {
                $postid = (is_user()) ? intval($user[0]) : 0;
                $ip = getip();
                $checks = str_replace(["\n", "\r", "\t"], ' ', $text);
                $e = explode(' ', $checks);
                for ($a = 0; $a < count($e); $a++) $o = strlen($e[$a]);
                $stop = '';
                if ($text == '') $stop[] = _CERROR1;
                if ($o > $conf['forum']['letter']) $stop[] = _CERROR2;
                if (!$stop) {
                    $htext = filterHtml($text);
                    $db->getSqlQuery(
                        'UPDATE '.PREFIX_DB.'_forum SET body = :body, euid = :euid, eip = :eip, etime = NOW() WHERE id = :id',
                        ['body' => $htext, 'euid' => $postid, 'eip' => $ip, 'id' => $id]
                    );
                    echo $prs->filterContent($htext, false, $mod, 2);
                } else {
                    return $tpl->getHtmlFrag('alert', ['text' => $stop, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
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
function getProfileModules(): array {
    return [
        'comm' => ['title' => _COMMENTS, 'icon' => 'chat-text', 'table' => 'comment', 'where' => "uid = :uid AND status != '0'", 'rate' => [], 'fav' => ''],
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
    global $db, $conf, $tpl, $prs;
    if ($uid < 1 || ($conf['users']['prof'] == 1 && !is_user() && !isAdmin())) return '';
    $limit = intval(getUserNews(25));
    $parts = [];
    $params = [];
    $lists = [];
    foreach (getProfileModules() as $mod => $inf) {
        if ($mod != 'comm' && !is_active($mod)) continue;
        if ($mod == 'comm') {
            $parts[] = "(SELECT 'comm' AS mkey, id, cid AS ref, modul AS sub, body AS title, time, 0 AS rc, 0 AS rt FROM ".PREFIX_DB.'_comment WHERE '.str_replace(':uid', ':ucomm', $inf['where']).' ORDER BY id DESC LIMIT 0,'.$limit.')';
        } else {
            $ron = !empty(explode('|', (string)($conf['ratings'][$mod] ?? ''))[1]);
            $rsel = ($ron && $inf['rate']) ? $inf['rate'][0].' AS rc, '.$inf['rate'][1].' AS rt' : '0 AS rc, 0 AS rt';
            $parts[] = "(SELECT '".$mod."' AS mkey, id, 0 AS ref, '' AS sub, title, time, ".$rsel.' FROM '.PREFIX_DB.'_'.$inf['table'].' WHERE '.str_replace(':uid', ':u'.$mod, $inf['where']).' ORDER BY id DESC LIMIT 0,'.$limit.')';
        }
        $params['u'.$mod] = $uid;
        $lists[$mod] = [];
    }
    if (!$parts) return '';
    $result = $db->getSqlQuery(implode(' UNION ALL ', $parts), $params);
    while ([$key, $id, $cid, $cmod, $label, $time, $cnt, $tot] = $db->getSqlRow($result)) {
        if ($key == 'comm') {
            $label = cutstr(str_replace([_QUOTE, _CODE], '', filterText($prs->filterContent($label, false, $conf['name']))), 70);
            $href = getSeoUrl(['name' => $cmod, 'op' => 'view', 'id' => $cid]).'#'.$id;
        } elseif ($key == 'jokes') {
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
