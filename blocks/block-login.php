<?php
# Author: Eduard Laas
# Copyright © 2005 - 2021 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
	header('Location: ../index.php');
	exit;
}

global $conf, $tpl;
$captcha = ($conf['gfx_chk'] == 2 || $conf['gfx_chk'] == 4 || $conf['gfx_chk'] == 5 || $conf['gfx_chk'] == 7) ? getCaptcha(2) : '';
$network_row = ($conf['users']['network']) ? $tpl->getHtmlFrag('block-network-row', [
	'network_label' => _LOGINNETWORK,
	'networks_html' => getNetworks(),
]) : '';
$content = $tpl->getHtmlFrag('block-login-form', [
	'nickname_label' => _NICKNAME,
	'password_label' => _PASSWORD,
	'name_input'     => getTplTextInput('user_name', '', 'sl-field--block', 'maxlength="25" placeholder="'._NICKNAME.'" required'),
	'captcha_html'   => $captcha,
	'hidden_inputs'  => getTplHiddenInput(['name' => 'refer', 'value' => '1']).getTplHiddenInput(['name' => 'op', 'value' => 'login']),
	'login_label'    => _LOGIN,
	'network_row'    => $network_row,
]);
