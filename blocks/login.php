<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('BLOCK_FILE')) {
    header('Location: ../index.php');
    exit;
}

global $conf, $tpl;
$captcha = getCaptcha('login');
$content = $tpl->getHtmlFrag('block-login-form', [
    'nickname_label' => _NICKNAME,
    'password_label' => _PASSWORD,
    'name_input' => $tpl->getHtmlFrag('input', [
        'input_attr' => 'maxlength="25" placeholder="'._NICKNAME.'" required',
        'is_block' => true,
        'itype' => 'text',
        'name_attr' => 'user_name',
        'value_attr' => '',
    ]),
    'captcha_html' => $captcha,
    'hidden_inputs' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'refer', 'value_attr' => '1', 'input_attr' => '']).$tpl->getHtmlFrag('hidden', ['name_attr' => 'op', 'value_attr' => 'login', 'input_attr' => '']).$tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('account'), 'input_attr' => '']),
    'login_label' => _LOGIN,
    'oauth_html' => Oauth::getButtons(),
]);
