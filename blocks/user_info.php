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
	$uid = intval($userinfo['id']);
	$ava = (file_exists($conf['users']['adirectory'].'/'.$userinfo['avatar'])) ? $userinfo['avatar'] : 'default/00.gif';
	$prin = 0;
	$prout = 0;
	if ($conf['privat']['act']) {
		list($prin) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB."_privat WHERE uidin='".$uid."' AND status = '0'"));
		list($prout) = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB."_privat WHERE uidout='".$uid."' AND status = '0'"));
	}
	$data = [
		'is_user' => true,
		'avatar_url' => $conf['users']['adirectory'].'/'.$ava,
		'greeting_label' => _HELLO.',',
		'greeting_name' => $uname,
		'audio_src' => ($prin > 0) ? 'sound/privat-'.$locale.'.mp3' : '',
		'has_privat' => $conf['privat']['act'],
		'privat_label' => _PRIVAT,
		'inbox_label' => _PRINNO,
		'inbox_count' => $prin,
		'outbox_label' => _PROUTNO,
		'outbox_count' => $prout,
		'has_fav' => $conf['favorites']['favact'],
		'favorites_label' => _FAVORITES,
		'change_label' => _CHANGE,
		'logout_label' => _LOGOUT,
	];
} else {
	$captcha = ($conf['gfx_chk'] == 2 || $conf['gfx_chk'] == 4 || $conf['gfx_chk'] == 5 || $conf['gfx_chk'] == 7) ? getCaptcha(2) : '';
	$data = [
		'is_user' => false,
		'avatar_url' => $conf['users']['adirectory'].'/default/0.gif',
		'greeting_label' => _WELCOMETO.',',
		'greeting_name' => _ANONYM,
		'register_label' => _BREG,
		'passfor_label' => _PASSFOR,
		'nickname_label' => _NICKNAME,
		'password_label' => _PASSWORD,
		'name_input'     => $tpl->getHtmlFrag('input', [
			'input_attr' => 'maxlength="25" placeholder="'._NICKNAME.'" required',
			'is_block' => true,
			'itype' => 'text',
			'name_attr' => 'user_name',
			'value_attr' => '',
		]),
		'captcha_html'   => $captcha,
		'hidden_inputs'  => $tpl->getHtmlFrag('hidden', ['name_attr' => 'refer', 'value_attr' => '1', 'input_attr' => '']).$tpl->getHtmlFrag('hidden', ['name_attr' => 'op', 'value_attr' => 'login', 'input_attr' => '']),
		'login_label'    => _LOGIN,
		'has_network' => $conf['users']['network'],
		'network_label' => _LOGINNETWORK,
		'networks_html' => ($conf['users']['network']) ? getNetworks() : '',
	];
}
$data['session_html'] = ($conf['session']) ? getUserSessionInfo(1) : '';
$content = $tpl->getHtmlPart('block-user-info', $data);
