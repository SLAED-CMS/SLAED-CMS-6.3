<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Set global template vars
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

# Apply {% if [!]flag %} ... {% elseif [!]flag %} ... {% else %} ... {% endif %}
# Supports negation (!flag), elseif chains, and non-empty variable checks ({%if var%})
if (!function_exists('setTemplateIf')) {
    function setTemplateIf(string $html, array $flags, array $vars = []): string {
        if ($html === '' || !str_contains($html, '{%')) return $html;
        foreach ($flags as $k => $v) {
            if ($v === 'true') $flags[$k] = true;
            if ($v === 'false') $flags[$k] = false;
        }
        $resolve = static function(string $name, bool $neg, array $flags, array $vars): bool {
            $val = array_key_exists($name, $flags)
                ? !empty($flags[$name])
                : !empty($vars['{%'.$name.'%}']);
            return $neg ? !$val : $val;
        };
        $re = '/(\{\%\s*(?:(?:else)?if\s+!?[a-zA-Z0-9_]+|else|endif)\s*\%\})/';
        $parts = preg_split($re, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts) || count($parts) < 2) return $html;
        $out = '';
        $stack = [];
        $emit = true;
        foreach ($parts as $p) {
            if (preg_match('/^\{\%\s*if\s+(!?)([a-zA-Z0-9_]+)\s*\%\}$/', $p, $m)) {
                $cond = $resolve($m[2], $m[1] === '!', $flags, $vars);
                $stack[] = ['active' => $cond, 'seen' => $cond, 'parent' => $emit];
                $emit = $emit && $cond;
                continue;
            }
            if (preg_match('/^\{\%\s*elseif\s+(!?)([a-zA-Z0-9_]+)\s*\%\}$/', $p, $m)) {
                if ($stack === []) continue;
                $i = count($stack) - 1;
                if ($stack[$i]['seen']) { $emit = false; continue; }
                $cond = $resolve($m[2], $m[1] === '!', $flags, $vars);
                $stack[$i]['active'] = $cond;
                $stack[$i]['seen'] = $cond;
                $emit = $stack[$i]['parent'] && $cond;
                continue;
            }
            if (preg_match('/^\{\%\s*else\s*\%\}$/', $p)) {
                if ($stack === []) continue;
                $i = count($stack) - 1;
                if ($stack[$i]['seen']) { $emit = false; continue; }
                $stack[$i]['seen'] = true;
                $emit = $stack[$i]['parent'] && !$stack[$i]['active'];
                continue;
            }
            if (preg_match('/^\{\%\s*endif\s*\%\}$/', $p)) {
                if ($stack === []) continue;
                $emit = array_pop($stack)['parent'];
                continue;
            }
            if ($emit) $out .= $p;
        }
        return $out;
    }
}

# Set template of head
if (!function_exists('setTemplateHead')) {
    function setTemplateHead(string $sub, array $val = []): string {
        global $user, $conf;
        if (is_user()) {
            $uname = htmlspecialchars(substr((string)$user[1], 0, 25), ENT_QUOTES, 'UTF-8');
            $userinfo = getUserInfo();
            $avpath = BASE_DIR.'/'.$conf['users']['adirectory'].'/'.$userinfo['avatar'];
            $avatar = (!empty($userinfo['avatar']) && is_file($avpath)) ? $userinfo['avatar'] : 'default/00.gif';
            $login = setTemplateBasic('login-logged', [
                '{%title%}' => _ACCOUNT,
                '{%avatar%}' => $conf['users']['adirectory'].'/'.$avatar,
                '{%user%}' => $uname,
                '{%logout%}' => _LOGOUT,
            ]);
        } else {
            if ($conf['users']['enter']) {
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
        if (!getThemeFile($tpl)) return setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => sprintf(_ERRORTPL, $tpl)]);
        $raw = getThemeLoad($tpl);
        $flags = [];
        if (isset($val['if_flag']) && is_array($val['if_flag'])) {
            $flags = $val['if_flag'];
            unset($val['if_flag']);
        }
        $raw = setTemplateIf($raw, $flags, $val + $vars);
        return strtr($raw, $val + $vars);
    }
}

# Set template of block
if (!function_exists('setTemplateBlock')) {
    function setTemplateBlock(string $tpl, array $val = []): string {
        global $pos, $bfile, $b_id;
        $flags = [];
        if (isset($val['if_flag']) && is_array($val['if_flag'])) {
            $flags = $val['if_flag'];
            unset($val['if_flag']);
        }
        $theme = getTheme();
        if ($pos === 's' || $pos === 'o') {
            $bname = empty($bfile) ? 'fly-block-'.$b_id : 'fly-'.str_replace('.php', '', $bfile);
        } else {
            $bname = empty($bfile) ? 'block-'.$b_id : str_replace('.php', '', $bfile);
        }
        $direct = BASE_DIR.'/templates/'.$theme.'/'.$bname.'.html';
        if (is_file($direct)) {
            static $dircache = [];
            $mtime = filemtime($direct) ?: 0;
            $rawtpl = null;
            if (isset($dircache[$direct]) && $dircache[$direct]['mtime'] === $mtime) {
                $rawtpl = $dircache[$direct]['raw'];
            } else {
                $rawread = file_get_contents($direct);
                if ($rawread !== false) {
                    $dircache[$direct] = ['mtime' => $mtime, 'raw' => $rawread];
                    $rawtpl = $rawread;
                }
            }
            if ($rawtpl !== null) {
                $rawtpl = setTemplateIf($rawtpl, $flags, $val + ['{%theme%}' => $theme]);
                return strtr($rawtpl, $val + ['{%theme%}' => $theme]);
            }
        }
        $fallback = match ($pos) {
            'l' => 'block-left',
            'r' => 'block-right',
            'c' => 'block-center',
            'd' => 'block-down',
            's', 'o' => 'block-fly',
            default => 'block-all',
        };
        if ($flags !== []) $val['if_flag'] = $flags;
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
        if (!getThemeFile($tpl)) return sprintf('<fieldset><legend>%s</legend>%s</fieldset>', _ERROR, sprintf(_ERRORTPL, $tpl));
        $raw = getThemeLoad($tpl);
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
