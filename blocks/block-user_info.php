<?php
# Author: Eduard Laas
# Copyright © 2005 - 2021 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $db, $locale, $conf, $tpl;
if (is_user()) {
	$userinfo = getUserInfo();
	$uname = $userinfo['name'];
	$user_id = intval($userinfo['id']);
	$user_avatar = (file_exists($conf['users']['adirectory'].'/'.$userinfo['avatar'])) ? $userinfo['avatar'] : 'default/00.gif';
	$content = $tpl->getHtmlFrag('block-user-avatar', [
		'name'       => $uname,
		'avatar_url' => $conf['users']['adirectory'].'/'.$user_avatar,
		'greeting'   => _HELLO.',<br>'.$uname,
	]);
	if ($conf['privat']['act']) {
		list($prin) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB."_privat WHERE uidin='".$user_id."' AND status = '0'"));
		list($prout) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB."_privat WHERE uidout='".$user_id."' AND status = '0'"));
		if ($prin > 0) {
			$content .= $tpl->getHtmlFrag('block-user-pm-notification', [
				'audio_src'    => 'sound/privat-'.$locale.'.mp3',
				'unread_count' => $prin,
			]);
		}
		$content .= $tpl->getHtmlFrag('block-user-pm-table', [
			'privat_label' => _PRIVAT,
			'inbox_label'  => _PRINNO,
			'inbox_count'  => $prin,
			'outbox_label' => _PROUTNO,
			'outbox_count' => $prout,
		]);
	}
	$favorites_row = ($conf['favorites']['favact']) ? $tpl->getHtmlFrag('block-user-favorite-row', ['favorites_label' => _FAVORITES]) : '';
	$content .= $tpl->getHtmlFrag('block-user-actions-table', [
		'favorites_row' => $favorites_row,
		'change_label'  => _CHANGE,
		'logout_label'  => _LOGOUT,
	]);
} else {
	$captcha = ($conf['gfx_chk'] == 2 || $conf['gfx_chk'] == 4 || $conf['gfx_chk'] == 5 || $conf['gfx_chk'] == 7) ? getCaptcha(2) : '';
	$network_row = ($conf['users']['network']) ? $tpl->getHtmlFrag('block-network-row', [
		'network_label' => _LOGINNETWORK,
		'networks_html' => getNetworks(),
	]) : '';
	$content = $tpl->getHtmlFrag('block-user-avatar', [
		'name'       => _ANONYM,
		'avatar_url' => $conf['users']['adirectory'].'/default/0.gif',
		'greeting'   => _WELCOMETO.',<br>'._ANONYM,
	]);
	$content .= $tpl->getHtmlFrag('block-user-guest-links', [
		'register_label' => _BREG,
		'passfor_label'  => _PASSFOR,
	]);
	$content .= $tpl->getHtmlFrag('block-login-form', [
		'nickname_label' => _NICKNAME,
		'password_label' => _PASSWORD,
		'name_input'     => getTplTextInput('user_name', '', 'sl_field sl_bl_field', 'maxlength="25" placeholder="'._NICKNAME.'" required'),
		'captcha_html'   => $captcha,
		'hidden_inputs'  => getTplHiddenInput('refer', '1').getTplHiddenInput('op', 'login'),
		'login_label'    => _LOGIN,
		'network_row'    => $network_row,
	]);
}
if ($conf['session']) $content .= $tpl->getHtmlFrag('block-user-session-info', ['content_html' => getUserSessionInfo(1)]);
