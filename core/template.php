<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Set пlobal еemplate мars
if (!function_exists('getTemplateVars')) {
    function getTemplateVars(): array {
        static $cache = [];
        global $conf;
        $theme = getTheme();
        if (isset($cache[$theme])) return $cache[$theme];
        return $cache[$theme] = [
            '{%theme%}' => $theme,
            '{%lang%}' => substr(_LOCALE, 0, 2),
            '{%sitename%}' => $conf['sitename'] ?? '',
            '{%logo%}' => $conf['site_logo'] ?? '',
            '{%homeurl%}' => $conf['homeurl'] ?? '',
            '{%slogan%}' => $conf['slogan'] ?? '',
            '{%home%}' => _HOME,
            '{%account%}' => _ACCOUNT,
            '{%album%}' => _ALBUM,
            '{%alinks%}' => _A_LINKS,
            '{%feedback%}' => _FEEDBACK,
            '{%content%}' => _CONTENT,
            '{%faq%}' => _FAQ,
            '{%files%}' => _FILES,
            '{%forum%}' => _FORUM,
            '{%help%}' => _HELP,
            '{%radio%}' => _RADIO,
            '{%jokes%}' => _JOKES,
            '{%links%}' => _LINKS,
            '{%media%}' => _MEDIA,
            '{%users%}' => _USERS,
            '{%news%}' => _NEWS,
            '{%order%}' => _ORDER,
            '{%pages%}' => _PAGES,
            '{%recommend%}' => _RECOMMEND,
            '{%rss%}' => _RSS,
            '{%search%}' => _SEARCH,
            '{%shop%}' => _SHOP,
            '{%topusers%}' => _TOPUSERS,
            '{%voting%}' => _VOTING,
            '{%favorites%}' => _S_FAVORITEN,
            '{%homepage%}' => _S_STARTSEITE,
        ];
    }
}

# Set template of head
if (!function_exists('setTemplateHead')) {
    function setTemplateHead(string $sub, array $val = []): string {
        global $user, $conf, $confu;
        if (is_user()) {
            $uname = htmlspecialchars(substr((string)$user[1], 0, 25), ENT_QUOTES, 'UTF-8');
            $userinfo = getusrinfo();
            $avpath = BASE_DIR.'/'.$confu['adirectory'].'/'.$userinfo['user_avatar'];
            $avatar = (!empty($userinfo['user_avatar']) && is_file($avpath)) ? $userinfo['user_avatar'] : 'default/00.gif';
            $login = setTemplateBasic('login-logged', [
                '{%title%}' => _ACCOUNT,
                '{%avatar%}' => $confu['adirectory'].'/'.$avatar,
                '{%user%}' => $uname,
                '{%logout%}' => _LOGOUT,
            ]);
        } else {
            if ($confu['enter']) {
                $gfx = (int)($conf['gfx_chk'] ?? 0);
                $captcha = in_array($gfx, [2, 4, 5, 7], true) ? getCaptcha(2) : '';
                $login = setTemplateBasic('login', [
                    '{%login%}' => _LOGIN,
                    '{%nickname%}' => _NICKNAME,
                    '{%password%}' => _PASSWORD,
                    '{%captcha%}' => $captcha,
                    '{%lost%}' => _PASSFOR,
                    '{%register%}' => _REG,
                ]);
            } else {
                $login = setTemplateBasic('login-without', ['{%register%}' => _BREG]);
            }
        }
        $vars = getTemplateVars();
        $vars['{%login%}'] = $login;
        return strtr($sub, $val + $vars);
    }
}

# Set template of basic
if (!function_exists('setTemplateBasic')) {
    function setTemplateBasic(string $tpl, array $val = []): string {
        $vars = getTemplateVars();
        $raw = getThemeLoad($tpl);
        if ($raw === null) return setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => sprintf(_ERRORTPL, $tpl)]);
        return strtr($raw, $val + $vars);
    }
}

# Set template of block
if (!function_exists('setTemplateBlock')) {
    function setTemplateBlock(string $tpl, array $val = []): string {
        global $pos, $blockfile, $b_id;
        $theme = getTheme();
        if ($pos === 's' || $pos === 'o') {
            $bname = empty($blockfile) ? 'fly-block-'.$b_id : 'fly-'.str_replace('.php', '', $blockfile);
        } else {
            $bname = empty($blockfile) ? 'block-'.$b_id : str_replace('.php', '', $blockfile);
        }
        $direct = BASE_DIR.'/templates/'.$theme.'/'.$bname.'.html';
        if (is_file($direct)) {
            static $dircache = [];
            $mtime = filemtime($direct) ?: 0;
            if (!isset($dircache[$direct]) || $dircache[$direct]['mtime'] !== $mtime) {
                $raw = file_get_contents($direct);
                if ($raw === false) {
                    $raw = null;
                } else {
                    $dircache[$direct] = ['mtime' => $mtime, 'raw' => $raw];
                }
            }
            if (isset($dircache[$direct])) return strtr($dircache[$direct]['raw'], $val + ['{%theme%}' => $theme]);
        }
        $fallback = match ($pos) {
            'l' => 'block-left',
            'r' => 'block-right',
            'c' => 'block-center',
            'd' => 'block-down',
            's', 'o' => 'block-fly',
            default => 'block-all',
        };
        $out = setTemplateBasic($fallback, $val);
        if (!$out) $out = setTemplateBasic('block-all', $val);
        return $out ?: strtr('<fieldset><legend>{%title%}</legend>{%content%}</fieldset>', $val);
    }
}

# Set template of foot
if (!function_exists('setTemplateFoot')) {
    function setTemplateFoot(string $sub, array $val = []): string {
        $vars = getTemplateVars();
        $vars['{%login%}'] = '';
        return strtr($sub, $val + $vars);
    }
}

# Set template of warning
if (!function_exists('setTemplateWarning')) {
    function setTemplateWarning(string $tpl, array $set = [], array $val = []): string {
        $theme = getTheme();
        $raw = getThemeLoad($tpl);
        if ($raw === null) return sprintf('<fieldset><legend>%s</legend>%s</fieldset>', _ERROR, sprintf(_ERRORTPL, $tpl));
        $text = $set['text'] ?? '';
        if (is_array($text)) $text = implode('<br>', $text);
        $url  = $set['url'] ?? '';
        $time = (int)($set['time'] ?? 0);
        $meta = ($url !== '' || $time > 0) ? '<meta http-equiv="refresh" content="'.$time.'; url=index.php'.$url.'">' : '';
        $vars = [
            '{%theme%}' => $theme,
            '{%text%}' => $text,
            '{%meta%}' => $meta,
            '{%id%}' => $set['id'] ?? 'warn',
        ];
        return strtr($raw, $val + $vars);
    }
}

# OLD DELETE
if (!function_exists("tpl_eval")) {
    function tpl_eval() {
        global $theme, $conf;
        $arg = func_get_args();
        $lan = array(_SEARCH);
        $cont = getThemeFile($arg[0]);
        if ($cont) eval("\$rfl = \"".addslashes(file_get_contents($cont))."\";");
        return ($cont) ? stripslashes($rfl) : tpl_warn("warn", sprintf(_ERRORTPL, $arg[0]), "", "", "warn");
    }
}

if (!function_exists('tpl_func')) {
    function tpl_func() {
        global $theme, $conf;
        static $argc, $cach, $cont;
        $arg = func_get_args();
        $lan = array();
        if ($argc != $arg[0] || !isset($cach)) {
            $argc = $arg[0];
            $cont = getThemeFile($argc);
            if ($cont) $cach = file_get_contents($cont);
        }
        if ($cont) eval("\$rfl = \"".addslashes($cach)."\";");
        return ($cont) ? stripslashes($rfl) : tpl_warn('warn', sprintf(_ERRORTPL, $arg[0]), '', '', 'warn');
    }
}

if (!function_exists("tpl_warn")) {
    function tpl_warn() {
        global $theme, $conf;
        $arg = func_get_args();
        $lan = array();
        $arg[1] = (is_array($arg[1])) ? implode("<br>", $arg[1]) : $arg[1];
        if ($arg[2] || intval($arg[3])) $arg[2] = "<meta http-equiv=\"refresh\" content=\"".$arg[3]."; url=index.php".$arg[2]."\">";
        $arg[3] = $arg[4] ;
        $cont = getThemeFile($arg[0]);
        if ($cont) eval("\$rfl = \"".addslashes(file_get_contents($cont))."\";");
        return ($cont) ? stripslashes($rfl) : sprintf(_ERRORTPL, $arg[0]);
    }
}
