<?php
declare(strict_types=1);

if (!defined('_LOCALE')) define('_LOCALE', 'en_US');
if (!defined('_HOME')) define('_HOME', 'Home');
if (!defined('_ACCOUNT')) define('_ACCOUNT', 'Account');
if (!defined('_ALBUM')) define('_ALBUM', 'Album');
if (!defined('_A_LINKS')) define('_A_LINKS', 'Links');
if (!defined('_FEEDBACK')) define('_FEEDBACK', 'Feedback');
if (!defined('_CONTENT')) define('_CONTENT', 'Content');
if (!defined('_FAQ')) define('_FAQ', 'FAQ');
if (!defined('_FILES')) define('_FILES', 'Files');
if (!defined('_FORUM')) define('_FORUM', 'Forum');
if (!defined('_HELP')) define('_HELP', 'Help');
if (!defined('_RADIO')) define('_RADIO', 'Radio');
if (!defined('_JOKES')) define('_JOKES', 'Jokes');
if (!defined('_LINKS')) define('_LINKS', 'Links');
if (!defined('_MEDIA')) define('_MEDIA', 'Media');
if (!defined('_USERS')) define('_USERS', 'Users');
if (!defined('_NEWS')) define('_NEWS', 'News');
if (!defined('_ORDER')) define('_ORDER', 'Order');
if (!defined('_PAGES')) define('_PAGES', 'Pages');
if (!defined('_RECOMMEND')) define('_RECOMMEND', 'Recommend');
if (!defined('_RSS')) define('_RSS', 'RSS');
if (!defined('_SEARCH')) define('_SEARCH', 'Search');
if (!defined('_SHOP')) define('_SHOP', 'Shop');
if (!defined('_TOPUSERS')) define('_TOPUSERS', 'Top');
if (!defined('_VOTING')) define('_VOTING', 'Voting');
if (!defined('_S_FAVORITEN')) define('_S_FAVORITEN', 'Favorites');
if (!defined('_S_STARTSEITE')) define('_S_STARTSEITE', 'Start');
if (!defined('_LOGOUT')) define('_LOGOUT', 'Logout');
if (!defined('_BREG')) define('_BREG', 'Register');
if (!defined('_ERROR')) define('_ERROR', 'Error');
if (!defined('_ERRORTPL')) define('_ERRORTPL', 'Template error: %s');
if (!defined('_LOGIN')) define('_LOGIN', 'Login');
if (!defined('_NICKNAME')) define('_NICKNAME', 'Nickname');
if (!defined('_PASSWORD')) define('_PASSWORD', 'Password');
if (!defined('_PASSFOR')) define('_PASSFOR', 'Lost password');
if (!defined('_REG')) define('_REG', 'Register now');

if (!function_exists('getTheme')) {
    function getTheme(): string
    {
        return $GLOBALS['__test_theme'] ?? 'default';
    }
}

if (!function_exists('is_user')) {
    function is_user(): bool
    {
        return (bool)($GLOBALS['__test_is_user'] ?? false);
    }
}

if (!function_exists('getUserInfo')) {
    function getUserInfo(): array
    {
        return $GLOBALS['__test_user_info'] ?? [];
    }
}

if (!function_exists('getCaptcha')) {
    function getCaptcha(int $mode = 0): string
    {
        return $GLOBALS['__test_captcha'] ?? '';
    }
}

if (!function_exists('getSiteToken')) {
    function getSiteToken(string $name): string
    {
        return $GLOBALS['__test_token'][$name] ?? 'token';
    }
}

if (!class_exists('Template', false)) {
    require_once BASE_DIR.'/core/classes/template.php';
}

