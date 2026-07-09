<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $db, $conf, $tpl;
if (is_user()) {
    $userinfo = getUserInfo();
    $uid = intval($userinfo['id']);
    $prin = 0;
    $prout = 0;
    if ($conf['privat']['act']) {
        $cache = $_SESSION[$conf['user_c'].'-privat'] ?? null;
        if (is_array($cache) && $cache['uid'] === $uid && (time() - $cache['time']) < 60) {
            [$prin, $prout] = $cache['counts'];
        } else {
            [$prin, $prout] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(CASE WHEN uidin = :uin THEN 1 END), COUNT(CASE WHEN uidout = :uout THEN 1 END) FROM '.PREFIX_DB.'_privat WHERE status = 0 AND (uidin = :uinw OR uidout = :uoutw)', ['uin' => $uid, 'uout' => $uid, 'uinw' => $uid, 'uoutw' => $uid]));
            $prin = intval($prin);
            $prout = intval($prout);
            $_SESSION[$conf['user_c'].'-privat'] = ['uid' => $uid, 'time' => time(), 'counts' => [$prin, $prout]];
        }
    }
    $gname = '';
    $rank = '';
    $ngname = '';
    $ngpts = 0;
    $points = intval($userinfo['points'] ?? 0);
    $grp = intval($userinfo['grp'] ?? 0);
    if ($conf['users']['point'] || $grp) {
        $result = $db->getSqlQuery('SELECT id, name, points, extra FROM '.PREFIX_DB.'_groups ORDER BY points ASC');
        while ([$gid, $name, $gpts, $extra] = $db->getSqlRow($result)) {
            if ($extra == 1) {
                if ($grp && $gid == $grp) $gname = $name;
                continue;
            }
            if (!$conf['users']['point']) continue;
            if ($gpts <= $points) {
                $rank = $name;
            } elseif ($ngname === '') {
                $ngname = $name;
                $ngpts = intval($gpts);
            }
        }
        if ($gname === '' && $points) $gname = $rank;
    }
    $data = [
        'is_user' => true,
        'avatar_url' => getUserAvatarUrl($userinfo),
        'greeting_label' => _HELLO.',',
        'greeting_name' => $userinfo['name'],
        'has_meta' => ($gname !== '' || ($conf['users']['point'] && $points)),
        'group_name' => $gname,
        'points_label' => ($conf['users']['point'] && $points) ? _POINTS : '',
        'points_count' => $points,
        'ngroup_name' => $ngname,
        'ngroup_points' => $ngpts,
        'ngroup_pct' => ($ngpts > 0) ? min(99, intval(floor($points / $ngpts * 100))) : 0,
        'has_privat' => $conf['privat']['act'],
        'privat_label' => _PRIVAT,
        'inbox_label' => _PRINNO,
        'inbox_count' => $prin,
        'has_new_in' => ($prin > 0),
        'outbox_label' => _PROUTNO,
        'outbox_count' => $prout,
        'has_fav' => $conf['favorites']['favact'],
        'favorites_label' => _FAVORITES,
        'change_label' => _CHANGE,
        'logout_label' => _LOGOUT,
    ];
} else {
    $captcha = getCaptcha('login');
    $data = [
        'is_user' => false,
        'avatar_url' => getUserAvatarUrl(),
        'greeting_label' => _WELCOMETO.',',
        'greeting_name' => _ANONYM,
        'register_label' => _BREG,
        'passfor_label' => _PASSFOR,
        'nickname_label' => _NICKNAME,
        'password_label' => _PASSWORD,
        'name_input' => $tpl->getHtmlFrag('input', [
            'maxlength_num' => 25,
            'placeholder_text' => _NICKNAME,
            'autocomplete_attr' => 'username',
            'is_required' => true,
            'is_block' => true,
            'itype' => 'text',
            'name_attr' => 'user_name',
            'value_attr' => '',
        ]),
        'captcha_html' => $captcha,
        'hidden_inputs' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'refer', 'value_attr' => '1', 'input_attr' => '']).$tpl->getHtmlFrag('hidden', ['name_attr' => 'op', 'value_attr' => 'login', 'input_attr' => '']).$tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('account'), 'input_attr' => '']),
        'login_label' => _LOGIN,
        'has_network' => $conf['users']['network'],
        'network_label' => _LOGINNETWORK,
        'networks_html' => ($conf['users']['network']) ? getNetworks() : '',
    ];
}
$data['session_html'] = ($conf['session']) ? getUserSessionInfo(1) : '';
$content = $tpl->getHtmlPart('block-user-info', $data);
